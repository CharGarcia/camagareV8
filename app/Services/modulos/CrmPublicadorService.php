<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\Helpers\CryptoHelper;
use App\repositories\modulos\CrmPublicacionRepository;
use App\Rules\modulos\CrmPublicacionRules;
use App\Services\InstagramApiService;
use App\Services\LinkedInApiService;
use App\Services\LogSistemaService;

/**
 * CRM fase 3: publica lo programado (cron, cada minuto). Diseño:
 * docs/diseno/crm-redes-fase3.md §3 y §6.
 *
 * Cada destino (red/cuenta) va por su cuenta, «alquilado» 10 minutos al
 * tomarlo de la cola (SKIP LOCKED): dos cron nunca trabajan el mismo.
 *
 * Nunca publicar dos veces:
 *  - Antes de la llamada que publica, el destino queda en «publicando».
 *  - Si el proceso muere ahí, al volver a tomarlo: en Instagram se consulta el
 *    contenedor (si ya está PUBLISHED se marca publicada, si no se publica);
 *    en LinkedIn, que no tiene cómo comprobarlo, queda «con error» pidiendo
 *    revisar la red (una persona decide si reintentar).
 *
 * Reintentos: hasta 3 intentos (a los 5 y 15 min). Cuenta desconectada o sin
 * acceso: error inmediato (hay que reconectar, reintentar no sirve).
 */
class CrmPublicadorService
{
    public const MAX_INTENTOS = 3;
    private const ESPERA_REINTENTO = [1 => 300, 2 => 900];
    /** Esperas de un minuto a que Instagram procese un video antes de darlo por fallido. */
    private const MAX_ESPERAS_PROCESO = 30;

    /** @var callable(int):string */
    private $urlMedio;

    public function __construct(
        private CrmPublicacionRepository $repo,
        private CrmMedioService $medios,
        private LogSistemaService $log,
        private ?InstagramApiService $igFijo = null,   // solo pruebas
        private ?LinkedInApiService $liFijo = null,    // solo pruebas
        ?callable $urlMedio = null
    ) {
        $this->urlMedio = $urlMedio ?? static fn(int $idMedio): string
            => url_absoluta('instagram-datos/medio?t=' . rawurlencode(CrmMedioService::tokenPublico($idMedio)));
    }

    public static function crear(?InstagramApiService $ig = null, ?LinkedInApiService $li = null, ?callable $urlMedio = null): self
    {
        return new self(new CrmPublicacionRepository(), CrmMedioService::crear(), new LogSistemaService(), $ig, $li, $urlMedio);
    }

    public function disponible(): bool
    {
        return $this->repo->disponible();
    }

    /** @return array{publicadas:int, esperando:int, errores:int} */
    public function procesarPendientes(int $limite = 10): array
    {
        $r = ['publicadas' => 0, 'esperando' => 0, 'errores' => 0];
        if (!$this->disponible()) {
            return $r;
        }
        foreach ($this->repo->tomarDestinos($limite) as $t) {
            try {
                $res = $this->procesarDestino($t['id']);
            } catch (\Throwable $e) {
                \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => 'procesarDestino', 'id_destino' => $t['id']]);
                $res = 'error';
            }
            $r[$res === 'publicada' ? 'publicadas' : ($res === 'error' ? 'errores' : 'esperando')]++;
            try {
                $this->actualizarEstadoPublicacion($t['id_publicacion'], $t['id_empresa']);
            } catch (\Throwable $e) {
                \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => 'actualizarEstadoPublicacion']);
            }
        }
        return $r;
    }

    /** @return string publicada | esperando | error */
    public function procesarDestino(int $idDestino): string
    {
        $d = $this->repo->destinoParaPublicar($idDestino);
        if (!$d || !in_array($d['estado'], ['pendiente', 'preparando', 'publicando'], true)) {
            return 'esperando';
        }
        $esIg = $d['canal_tipo'] === 'instagram';

        // El proceso anterior murió justo al publicar.
        if ($d['estado'] === 'publicando' && !($esIg && $d['contenedor_externo'])) {
            return $this->error($d, 'No se pudo confirmar si la publicación salió. Revise la red: si no salió, use «Reintentar».', true);
        }
        if ($d['canal_estado'] !== 'activo') {
            return $this->error($d, 'La cuenta está desconectada o con el acceso vencido. Reconéctela en Configuración del CRM y use «Reintentar».', true);
        }
        $token = CryptoHelper::desencriptar((string) $d['token_cifrado']);
        if ($token === '') {
            return $this->error($d, 'La cuenta no tiene un acceso vigente. Reconéctela en Configuración del CRM.', true);
        }
        // Tope diario propio de la empresa (solo para lo que aún no empezó).
        if ($d['estado'] === 'pendiente' && !$d['contenedor_externo']) {
            $tope = $this->repo->config((int) $d['id_empresa'])['max_publicaciones_dia'];
            if ($this->repo->publicadasHoy((int) $d['id_empresa']) >= $tope) {
                $this->repo->actualizarDestino($idDestino, ['siguiente_intento_at' => date('Y-m-d 00:05:00', strtotime('+1 day')),
                    'error' => "Se alcanzó el tope de {$tope} publicaciones por día de la empresa: sale mañana."]);
                return 'esperando';
            }
        }
        try {
            return $esIg ? $this->publicarInstagram($d, $token) : $this->publicarLinkedIn($d, $token);
        } catch (\InvalidArgumentException $e) {
            // Datos o configuración: reintentar no lo arregla.
            return $this->error($d, $e->getMessage(), true);
        } catch (\RuntimeException $e) {
            return $this->fallo($d, $e->getMessage());
        }
    }

    // ── Instagram ────────────────────────────────────────────────────────────

    private function publicarInstagram(array $d, string $token): string
    {
        $api = $this->igFijo ?? InstagramApiService::paraEmpresa((int) $d['id_empresa']);
        $igId = (string) $d['canal_externo'];
        $contenedor = (string) ($d['contenedor_externo'] ?? '');
        $extra = $d['contenedores'];

        if ($contenedor === '') {
            $lim = $api->limitePublicacion($token, $igId);
            if ($lim['usadas'] >= $lim['tope']) {
                $this->repo->actualizarDestino((int) $d['id'], ['siguiente_intento_at' => date('Y-m-d H:i:s', time() + 3600),
                    'error' => "Instagram: se alcanzó el tope de {$lim['tope']} publicaciones en 24 horas. Se intentará en 1 hora."]);
                return 'esperando';
            }
            $medios = $this->repo->medios((int) $d['id_publicacion']);
            $texto = (string) ($d['texto'] ?? $d['texto_publicacion'] ?? '');
            $ia = in_array($d['es_contenido_ia'], [true, 't', 'true', 1, '1'], true);
            switch ($d['formato']) {
                case 'imagen':
                    $contenedor = $api->crearContenedor($token, $igId, InstagramApiService::CONTENEDOR_IMAGEN, [
                        'url' => $this->url($medios[0]), 'texto' => $texto, 'alt' => $medios[0]['texto_alternativo'] ?? '', 'ia' => $ia]);
                    break;
                case 'reel':
                    $contenedor = $api->crearContenedor($token, $igId, InstagramApiService::CONTENEDOR_REEL, [
                        'url' => $this->url($medios[0]), 'texto' => $texto, 'ia' => $ia]);
                    break;
                case 'carrusel':
                    $hijos = (array) ($extra['hijos'] ?? []);
                    foreach ($medios as $i => $m) {
                        if (isset($hijos[$i])) {
                            continue;   // creado en una pasada anterior
                        }
                        $hijos[$i] = $m['tipo'] === 'video'
                            ? $api->crearContenedor($token, $igId, InstagramApiService::CONTENEDOR_VIDEO_ITEM, ['url' => $this->url($m)])
                            : $api->crearContenedor($token, $igId, InstagramApiService::CONTENEDOR_IMAGEN, [
                                'url' => $this->url($m), 'alt' => $m['texto_alternativo'] ?? '', 'carrusel_item' => true]);
                        $extra['hijos'] = $hijos;
                        $this->repo->actualizarDestino((int) $d['id'], ['estado' => 'preparando', 'contenedores' => $extra]);
                    }
                    // Los videos del carrusel deben estar procesados antes de armar el carrusel.
                    foreach ($hijos as $h) {
                        $e = $api->estadoContenedor($token, (string) $h);
                        if ($e['estado'] === 'IN_PROGRESS') {
                            return $this->esperarProceso($d, $extra);
                        }
                        if ($e['estado'] !== 'FINISHED') {
                            $this->repo->actualizarDestino((int) $d['id'], ['contenedores' => null]);
                            throw new \RuntimeException('Instagram no pudo procesar un archivo del carrusel' . ($e['detalle'] ? ': ' . $e['detalle'] : '.'));
                        }
                    }
                    $contenedor = $api->crearContenedor($token, $igId, InstagramApiService::CONTENEDOR_CARRUSEL, [
                        'hijos' => array_values($hijos), 'texto' => $texto, 'ia' => $ia]);
                    break;
                default:
                    return $this->error($d, 'Formato no válido para Instagram.', true);
            }
            $this->repo->actualizarDestino((int) $d['id'], ['estado' => 'preparando', 'contenedor_externo' => $contenedor, 'contenedores' => $extra, 'error' => null]);
        }

        $e = $api->estadoContenedor($token, $contenedor);
        if ($e['estado'] === 'PUBLISHED') {
            return $this->publicada($d, '', '', 'Ya estaba publicada (se recuperó tras una interrupción).');
        }
        if ($e['estado'] === 'IN_PROGRESS') {
            return $this->esperarProceso($d, $extra);
        }
        if ($e['estado'] !== 'FINISHED') {
            $this->repo->actualizarDestino((int) $d['id'], ['contenedor_externo' => null, 'contenedores' => null]);
            throw new \RuntimeException('Instagram no pudo procesar el archivo' . ($e['detalle'] ? ': ' . $e['detalle'] : '.'));
        }

        $this->repo->actualizarDestino((int) $d['id'], ['estado' => 'publicando']);
        try {
            $idMedia = $api->publicarContenedor($token, $igId, $contenedor);
        } catch (\RuntimeException $ex) {
            // ¿Salió igual? (respuesta perdida): no publicar dos veces.
            try {
                if ($api->estadoContenedor($token, $contenedor)['estado'] === 'PUBLISHED') {
                    return $this->publicada($d, '', '', 'Publicada; Instagram no devolvió el enlace.');
                }
            } catch (\RuntimeException $ex2) {
                // sin confirmación: se trata como error
            }
            $this->repo->actualizarDestino((int) $d['id'], ['estado' => 'preparando']);
            throw $ex;
        }
        $enlace = '';
        try {
            $enlace = $api->datosPublicacion($token, $idMedia)['enlace'];
        } catch (\RuntimeException $ex) {
            // el enlace se completa con las métricas
        }
        return $this->publicada($d, $idMedia, $enlace);
    }

    /** Instagram sigue procesando (videos): se mira de nuevo en un minuto. */
    private function esperarProceso(array $d, array $extra): string
    {
        $extra['esperas'] = (int) ($extra['esperas'] ?? 0) + 1;
        if ($extra['esperas'] > self::MAX_ESPERAS_PROCESO) {
            $this->repo->actualizarDestino((int) $d['id'], ['contenedor_externo' => null, 'contenedores' => null]);
            return $this->fallo($d, 'Instagram tardó más de 30 minutos en procesar el video.');
        }
        $this->repo->actualizarDestino((int) $d['id'], ['estado' => 'preparando', 'contenedores' => $extra,
            'siguiente_intento_at' => date('Y-m-d H:i:s', time() + 60)]);
        return 'esperando';
    }

    /** URL pública firmada del medio; debe ser HTTPS (Instagram no descarga de otra forma). */
    private function url(array $medio): string
    {
        $url = ($this->urlMedio)((int) $medio['id']);
        if (!str_starts_with($url, 'https://')) {
            throw new \InvalidArgumentException('El servidor no tiene configurada su dirección pública con https (app_url en config/local.php): Instagram no puede descargar los archivos.');
        }
        return $url;
    }

    // ── LinkedIn ─────────────────────────────────────────────────────────────

    private function publicarLinkedIn(array $d, string $token): string
    {
        $api = $this->liFijo ?? LinkedInApiService::paraEmpresa((int) $d['id_empresa']);
        $autor = (string) $d['canal_externo'];
        $extra = $d['contenedores'];
        $imagenes = [];
        if ($d['formato'] === 'imagen') {
            $subidas = (array) ($extra['imagenes'] ?? []);
            foreach ($this->repo->medios((int) $d['id_publicacion']) as $i => $m) {
                if (!isset($subidas[$i])) {
                    $ruta = $this->medios->rutaAbsoluta($m) ?? throw new \InvalidArgumentException('Un archivo de la publicación ya no está en el servidor.');
                    $subidas[$i] = $api->subirImagen($token, $autor, $ruta);
                    $extra['imagenes'] = $subidas;
                    $this->repo->actualizarDestino((int) $d['id'], ['estado' => 'preparando', 'contenedores' => $extra]);
                }
                $imagenes[] = [$subidas[$i], (string) ($m['texto_alternativo'] ?? '')];
            }
        }
        $this->repo->actualizarDestino((int) $d['id'], ['estado' => 'publicando']);
        try {
            $urn = $api->publicar($token, $autor, (string) ($d['texto'] ?? $d['texto_publicacion'] ?? ''), $imagenes);
        } catch (\RuntimeException $e) {
            // LinkedIn respondió con error: no salió, se puede reintentar.
            $this->repo->actualizarDestino((int) $d['id'], ['estado' => 'preparando']);
            throw $e;
        }
        return $this->publicada($d, $urn, LinkedInApiService::enlace($urn));
    }

    // ── Resultados ───────────────────────────────────────────────────────────

    private function publicada(array $d, string $idExterno, string $enlace, ?string $nota = null): string
    {
        $this->repo->actualizarDestino((int) $d['id'], [
            'estado' => 'publicada', 'id_externo' => $idExterno !== '' ? $idExterno : null, 'enlace' => $enlace !== '' ? $enlace : null,
            'publicada_at' => date('Y-m-d H:i:s'), 'error' => $nota, 'siguiente_intento_at' => null,
        ]);
        $this->log->registrar(0, (int) $d['id_empresa'], 'PUBLICAR', 'crm_publicaciones_destinos', (int) $d['id'], null,
            ['id_publicacion' => (int) $d['id_publicacion'], 'red' => $d['canal_tipo'], 'cuenta' => $d['canal_usuario'], 'id_externo' => $idExterno ?: null]);
        return 'publicada';
    }

    /** Fallo que se puede reintentar: hasta MAX_INTENTOS. */
    private function fallo(array $d, string $mensaje): string
    {
        $intentos = (int) $d['intentos'] + 1;
        if ($intentos >= self::MAX_INTENTOS) {
            $this->repo->actualizarDestino((int) $d['id'], ['intentos' => $intentos]);
            return $this->error(['intentos' => $intentos] + $d, $mensaje . ' (después de ' . $intentos . ' intentos)', false);
        }
        $this->repo->actualizarDestino((int) $d['id'], [
            'intentos' => $intentos, 'error' => $mensaje,
            'siguiente_intento_at' => date('Y-m-d H:i:s', time() + (self::ESPERA_REINTENTO[$intentos] ?? 900)),
        ]);
        return 'esperando';
    }

    /** Error definitivo del destino (queda para que una persona use «Reintentar»). */
    private function error(array $d, string $mensaje, bool $sinReintento): string
    {
        $this->repo->actualizarDestino((int) $d['id'], ['estado' => 'con_error', 'error' => $mensaje, 'siguiente_intento_at' => null]);
        $this->log->registrar(0, (int) $d['id_empresa'], 'ERROR_PUBLICAR', 'crm_publicaciones_destinos', (int) $d['id'], null,
            ['id_publicacion' => (int) $d['id_publicacion'], 'red' => $d['canal_tipo'], 'error' => mb_substr($mensaje, 0, 300)]);
        return 'error';
    }

    /** Estado de la publicación según sus destinos (bajo candado). */
    public function actualizarEstadoPublicacion(int $idPub, int $idEmpresa): void
    {
        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();
        try {
            $p = $this->repo->bloquear($idPub, $idEmpresa);
            if ($p && in_array($p['estado'], ['programada', 'publicando', 'parcial', 'con_error'], true)) {
                $nuevo = CrmPublicacionRules::estadoSegunDestinos($this->repo->estadosDestinos($idPub));
                if ($nuevo !== $p['estado']) {
                    $extra = in_array($nuevo, ['publicada', 'parcial'], true) && $p['publicada_at'] === null ? ['publicada_at' => date('Y-m-d H:i:s')] : [];
                    $this->repo->cambiarEstado($idPub, $idEmpresa, $nuevo, null, $extra);
                    if (in_array($nuevo, ['publicada', 'parcial', 'con_error'], true)) {
                        $this->log->registrar(0, $idEmpresa, 'ESTADO_PUBLICACION', 'crm_publicaciones', $idPub, ['estado' => $p['estado']], ['estado' => $nuevo]);
                    }
                }
            }
            if ($managed) $db->commit();
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }
}
