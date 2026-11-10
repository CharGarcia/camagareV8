<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\Helpers\CryptoHelper;
use App\repositories\modulos\CrmBotRepository;
use App\repositories\modulos\CrmCanalRepository;
use App\Rules\modulos\CrmBotRules;
use App\repositories\modulos\CrmCatalogoRepository;
use App\repositories\modulos\CrmContactoRepository;
use App\repositories\modulos\CrmConversacionRepository;
use App\repositories\modulos\CrmMetaAppRepository;
use App\repositories\modulos\CrmWebhookEventoRepository;
use App\Services\ErrorLogService;
use App\Services\InstagramApiService;
use App\Services\LogSistemaService;

/**
 * CRM: recepción y procesamiento de los eventos del webhook de Instagram
 * (mensajes directos, lecturas y comentarios). Diseño: docs/diseno/crm-redes-fase1.md §6.
 *
 * Flujo:
 *  1. recibir(): valida la firma de Meta y guarda el evento crudo en la cola.
 *  2. procesar(): por evento, con candado propio (webhook y cron no chocan).
 *     Primero lo que va a Instagram (perfil del remitente, adjuntos), FUERA de la
 *     transacción; luego, en una transacción corta y bajo candados: contacto,
 *     conversación (con el reparto de la cuenta) y mensaje.
 *  3. Todo es idempotente: Meta reintenta y el mismo mid / id de comentario no
 *     se guarda dos veces.
 *
 * Endpoint público (CLAUDE.md §6): la cuenta solo se resuelve si su empresa
 * está activa; si no, el evento queda «ignorado» como si la cuenta no existiera.
 */
class CrmWebhookService
{
    public const DIR_ADJUNTOS = '/storage/crm_media';

    public function __construct(
        private CrmWebhookEventoRepository $eventos,
        private CrmCanalRepository $canales,
        private CrmConversacionRepository $conversaciones,
        private CrmContactoRepository $contactos,
        private CrmCatalogoRepository $catalogos,
        private ?InstagramApiService $apiFija,   // solo pruebas; si no, la app de cada empresa
        private LogSistemaService $log,
        private CrmMetaAppRepository $apps,
        private ?CrmBotRepository $bot = null
    ) {
        $this->bot ??= new CrmBotRepository();
    }

    public static function crear(?InstagramApiService $api = null): self
    {
        return new self(
            new CrmWebhookEventoRepository(), new CrmCanalRepository(), new CrmConversacionRepository(),
            new CrmContactoRepository(), new CrmCatalogoRepository(), $api, new LogSistemaService(), new CrmMetaAppRepository(),
            new CrmBotRepository()
        );
    }

    public function disponible(): bool
    {
        return $this->eventos->disponible();
    }

    private function api(int $idEmpresa): InstagramApiService
    {
        return $this->apiFija ?? InstagramApiService::paraEmpresa($idEmpresa);
    }

    /**
     * Verificación GET de Meta: el token debe ser el de la app de alguna empresa
     * activa. Se busca por su SHA-256 y se compara el descifrado en tiempo constante.
     */
    public function verifyTokenValido(string $token): bool
    {
        if ($this->apiFija) {
            return $this->apiFija->verifyTokenValido($token);
        }
        if ($token === '') {
            return false;
        }
        foreach ($this->apps->buscarPorVerifyHash(hash('sha256', $token)) as $a) {
            if (hash_equals(CryptoHelper::desencriptar((string) $a['verify_token_cifrado']), $token)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Valida la firma con la app de Meta de la empresa dueña de la cuenta y encola
     * el evento anotando esa app. Devuelve el id del evento, o null si ninguna app
     * candidata valida la firma (en ese caso no se guarda nada).
     */
    public function recibir(string $cuerpo, string $firma): ?int
    {
        $json = json_decode($cuerpo, true);
        if (!is_array($json)) {
            return null;
        }
        $appId = $this->appQueFirmo($json, $cuerpo, $firma);
        if ($appId === null) {
            return null;
        }
        return $this->eventos->insertar((string) ($json['object'] ?? ''), $cuerpo, true, $appId);
    }

    /**
     * Candidatas: las apps de las empresas que tienen las cuentas del evento; si
     * ninguna cuenta es conocida, todas las apps registradas (para responder 200 a
     * Meta en eventos de cuentas ya liberadas y que no los reintente sin fin).
     */
    private function appQueFirmo(array $json, string $cuerpo, string $firma): ?string
    {
        if ($this->apiFija) {
            return $this->apiFija->firmaValida($cuerpo, $firma) ? $this->apiFija->appId() : null;
        }
        $cuentas = array_map(fn($e) => (string) ($e['id'] ?? ''), (array) ($json['entry'] ?? []));
        $empresas = $this->canales->empresasDeCuentas(CrmCanalService::TIPO_INSTAGRAM, $cuentas);
        $candidatas = $empresas ? $this->apps->porEmpresas($empresas) : $this->apps->todasActivas();
        $probadas = [];
        foreach ($candidatas as $a) {
            if (isset($probadas[$a['app_id']])) {
                continue;
            }
            $probadas[$a['app_id']] = true;
            $api = new InstagramApiService(['app_id' => $a['app_id'], 'app_secret' => CryptoHelper::desencriptar((string) $a['app_secret_cifrado'])]);
            if ($api->firmaValida($cuerpo, $firma)) {
                return (string) $a['app_id'];
            }
        }
        return null;
    }

    /** ¿La empresa usa la app que firmó el evento? (sin app anotada = eventos anteriores / pruebas). */
    private function empresaUsaApp(int $idEmpresa, ?string $appId): bool
    {
        if ($appId === null || $this->apiFija) {
            return true;
        }
        static $cache = [];
        if (!array_key_exists($idEmpresa, $cache)) {
            $cache[$idEmpresa] = $this->apps->getPorEmpresa($idEmpresa)['app_id'] ?? null;
        }
        return $cache[$idEmpresa] !== null && hash_equals((string) $cache[$idEmpresa], $appId);
    }

    /**
     * Cron: procesa la cola (pendientes y con error que aún tienen reintentos).
     * @return array{procesados:int, ignorados:int, errores:int}
     */
    public function procesarPendientes(int $limite = 200): array
    {
        $res = ['procesados' => 0, 'ignorados' => 0, 'errores' => 0];
        foreach ($this->eventos->idsPendientes($limite) as $id) {
            $estado = $this->procesar($id);
            if ($estado === 'procesado') $res['procesados']++;
            elseif ($estado === 'ignorado') $res['ignorados']++;
            elseif ($estado === 'error') $res['errores']++;
        }
        return $res;
    }

    public function purgar(int $dias = 30): int
    {
        return $this->eventos->purgar($dias);
    }

    /** Procesa un evento. Devuelve procesado | ignorado | error | ocupado | ya. */
    public function procesar(int $idEvento): string
    {
        if (!$this->eventos->tomar($idEvento)) {
            return 'ocupado';
        }
        try {
            $ev = $this->eventos->getPorProcesar($idEvento);
            if (!$ev) {
                return 'ya';
            }
            try {
                [$estado, $idEmpresa, $detalle] = $this->procesarPayload($ev['payload'], $ev['app_id'] ?? null);
                $this->eventos->marcar($idEvento, $estado, $idEmpresa, $detalle);
                return $estado;
            } catch (\Throwable $e) {
                ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => 'procesar', 'id_evento' => $idEvento]);
                $this->eventos->marcarError($idEvento, get_class($e) . ': ' . $e->getMessage());
                return 'error';
            }
        } finally {
            $this->eventos->soltar($idEvento);
        }
    }

    /** @return array{0:string, 1:?int, 2:?string} [estado, id_empresa, detalle] */
    private function procesarPayload(array $p, ?string $appId = null): array
    {
        if (($p['object'] ?? '') !== 'instagram') {
            return ['ignorado', null, 'El evento no es de Instagram.'];
        }
        $idEmpresa = null;
        $hechos = 0;
        $notas = [];
        foreach ((array) ($p['entry'] ?? []) as $entry) {
            $cuenta = (string) ($entry['id'] ?? '');
            $canal = $cuenta !== '' ? $this->canales->getActivoPorExterno(CrmCanalService::TIPO_INSTAGRAM, $cuenta) : null;
            if (!$canal) {
                $notas[] = "Cuenta {$cuenta} sin canal activo.";
                continue;
            }
            // La app que firmó el evento debe ser la de la empresa dueña de la cuenta.
            if (!$this->empresaUsaApp((int) $canal['id_empresa'], $appId)) {
                $notas[] = "Cuenta {$cuenta}: el evento vino firmado por otra app de Meta.";
                continue;
            }
            $idEmpresa = (int) $canal['id_empresa'];
            $canal['token'] = CryptoHelper::desencriptar((string) $canal['token_cifrado']);
            unset($canal['token_cifrado']);
            $horaEntrada = $this->fecha($entry['time'] ?? null);

            foreach ((array) ($entry['messaging'] ?? []) as $m) {
                if ($this->procesarMensajeria($canal, (array) $m)) $hechos++;
            }
            // Los comentarios llegan como changes[] o, en Instagram Login, como field/value en la entrada.
            $cambios = (array) ($entry['changes'] ?? []);
            if (isset($entry['field'])) {
                $cambios[] = ['field' => $entry['field'], 'value' => $entry['value'] ?? []];
            }
            foreach ($cambios as $c) {
                if (($c['field'] ?? '') === 'comments' && $this->procesarComentario($canal, (array) ($c['value'] ?? []), $horaEntrada)) {
                    $hechos++;
                }
            }
        }
        return [$hechos > 0 ? 'procesado' : 'ignorado', $idEmpresa, $notas ? implode(' ', $notas) : null];
    }

    // ── Mensajes directos ────────────────────────────────────────────────────

    private function procesarMensajeria(array $canal, array $m): bool
    {
        $idEmpresa = (int) $canal['id_empresa'];
        $cuenta = (string) $canal['id_externo'];
        $emisor = (string) ($m['sender']['id'] ?? '');
        $receptor = (string) ($m['recipient']['id'] ?? '');
        $fecha = $this->fecha($m['timestamp'] ?? null);

        // Lectura: el cliente vio los mensajes de la empresa.
        if (isset($m['read'])) {
            $conv = $this->conversaciones->buscarHilo($idEmpresa, (int) $canal['id'], 'dm', $emisor);
            if ($conv) {
                $this->conversaciones->marcarLeidos((int) $conv['id'], $fecha);
            }
            return (bool) $conv;
        }

        $msg = $m['message'] ?? null;
        if (!is_array($msg) || empty($msg['mid'])) {
            return false;   // reacciones, postbacks y otros: no aplican en esta fase
        }
        $mid = (string) $msg['mid'];
        // Eco: lo escribió la propia cuenta (desde la app de Instagram o desde el sistema).
        $eco = !empty($msg['is_echo']) || $emisor === $cuenta;
        $igsid = $eco ? $receptor : $emisor;
        if ($igsid === '' || $igsid === $cuenta) {
            return false;
        }
        if (!empty($msg['is_deleted'])) {
            return $this->conversaciones->marcarMensajeBorrado($idEmpresa, $mid) > 0;
        }

        $info = $this->interpretarMensaje($msg);

        // Fuera de la transacción: perfil (solo si la persona es nueva) y adjunto.
        $perfil = null;
        if (!$this->contactos->getPorInstagramId($idEmpresa, $igsid) && $canal['token'] !== '') {
            try {
                $perfil = $this->api($idEmpresa)->perfilRemitente($canal['token'], $igsid);
            } catch (\Throwable $e) {
                $perfil = null;   // sin perfil se crea con un nombre provisional
            }
        }
        $ruta = $info['adjunto_url'] ? $this->guardarAdjunto($idEmpresa, $mid, $info['adjunto_url']) : null;

        return $this->enTransaccion(function () use ($canal, $igsid, $perfil, $info, $mid, $eco, $fecha, $ruta, $m) {
            $conv = $this->obtenerConversacion($canal, 'dm', $igsid, null, $perfil, 'instagram_dm');
            // Eco de un mensaje enviado desde la Bandeja: ya existe (sin id todavía); no duplicar.
            if ($eco && $this->conversaciones->adoptarEco((int) $conv['id'], $mid, $info['texto'], $info['tipo'])) {
                if ($ruta) @unlink(MVC_ROOT . '/storage/' . $ruta);   // el original ya está guardado con el saliente
                return true;
            }
            $contenido = $m;
            if ($ruta) $contenido['adjunto_guardado'] = $ruta;
            $nuevo = $this->conversaciones->insertarMensajeSiNuevo((int) $canal['id_empresa'], [
                'id_conversacion' => $conv['id'],
                'direccion'       => $eco ? 'OUT' : 'IN',
                'autor'           => $eco ? 'usuario' : 'cliente',
                'tipo'            => $info['tipo'],
                'texto'           => $info['texto'],
                'adjunto_ruta'    => $ruta,
                'id_externo'      => $mid,
                'estado_envio'    => $eco ? 'enviado' : null,
                'contenido'       => $contenido,
                'fecha'           => $fecha,
            ]);
            if ($nuevo !== null) {
                $this->conversaciones->registrarMensaje((int) $conv['id'], ($eco ? 'Tú: ' : '') . $info['extracto'], $fecha, !$eco);
                if ($this->bot->disponible()) {
                    if ($eco) {
                        // Alguien respondió desde la app de Instagram: el asistente se calla en esta conversación.
                        $this->bot->pausarPorPersona((int) $conv['id'], (int) $canal['id_empresa']);
                    } else {
                        $this->bot->encolar((int) $conv['id'], (int) $canal['id_empresa'], CrmBotRules::SEGUNDOS_AGRUPAR);
                    }
                }
            }
            return true;
        });
    }

    /**
     * Tipo, texto, extracto para la bandeja y URL del adjunto a guardar.
     * @return array{tipo:string, texto:?string, extracto:string, adjunto_url:?string}
     */
    private function interpretarMensaje(array $msg): array
    {
        $texto = isset($msg['text']) ? trim((string) $msg['text']) : null;
        $tipo = 'text';
        $etiqueta = '';
        $url = null;
        $adj = $msg['attachments'][0] ?? null;
        if (is_array($adj)) {
            $tipo = (string) ($adj['type'] ?? 'otro');
            $etiquetas = [
                'image' => '[Imagen]', 'video' => '[Video]', 'audio' => '[Audio]', 'file' => '[Archivo]',
                'story_mention' => '[Te mencionó en su historia]', 'share' => '[Publicación compartida]',
                'ig_reel' => '[Reel compartido]', 'reel' => '[Reel compartido]', 'ig_post' => '[Publicación compartida]',
            ];
            $etiqueta = $etiquetas[$tipo] ?? '[Adjunto]';
            if (in_array($tipo, ['image', 'video', 'audio', 'file', 'story_mention'], true)) {
                $url = (string) ($adj['payload']['url'] ?? '') ?: null;
            }
        } elseif (isset($msg['reply_to']['story'])) {
            $tipo = 'story_reply';
            $etiqueta = '[Respondió a tu historia]';
            $url = (string) ($msg['reply_to']['story']['url'] ?? '') ?: null;
        } elseif ($texto === null || $texto === '') {
            $tipo = 'otro';
            $etiqueta = '[Mensaje]';
        }
        $extracto = trim($etiqueta . ' ' . mb_substr((string) $texto, 0, 120));
        return ['tipo' => $tipo, 'texto' => $texto !== '' ? $texto : null, 'extracto' => $extracto, 'adjunto_url' => $url];
    }

    // ── Comentarios ──────────────────────────────────────────────────────────

    private function procesarComentario(array $canal, array $v, string $fecha): bool
    {
        $idComentario = (string) ($v['id'] ?? '');
        $autor = (string) ($v['from']['id'] ?? '');
        // Las respuestas de la propia cuenta no abren conversación consigo misma.
        if ($idComentario === '' || $autor === '' || $autor === (string) $canal['id_externo']) {
            return false;
        }
        $hilo = (string) ($v['parent_id'] ?? '') ?: $idComentario;
        $perfil = ['name' => '', 'username' => (string) ($v['from']['username'] ?? '')];
        $texto = trim((string) ($v['text'] ?? ''));

        $idConv = 0;
        $esNuevo = false;
        $ok = $this->enTransaccion(function () use ($canal, $hilo, $autor, $perfil, $v, $idComentario, $texto, $fecha, &$idConv, &$esNuevo) {
            $conv = $this->obtenerConversacion($canal, 'comentario', $hilo, (string) ($v['media']['id'] ?? '') ?: null, $perfil, 'instagram_comentario', $autor);
            $idConv = (int) $conv['id'];
            $nuevo = $this->conversaciones->insertarMensajeSiNuevo((int) $canal['id_empresa'], [
                'id_conversacion' => $conv['id'],
                'direccion'       => 'IN',
                'autor'           => 'cliente',
                'tipo'            => 'comment',
                'texto'           => $texto !== '' ? $texto : null,
                'id_externo'      => $idComentario,
                'contenido'       => $v,
                'fecha'           => $fecha,
            ]);
            if ($nuevo !== null) {
                $esNuevo = true;
                $this->conversaciones->registrarMensaje((int) $conv['id'], mb_substr($texto, 0, 120) ?: '[Comentario]', $fecha, true);
            }
            return true;
        });
        // Fuera de la transacción (llama a Instagram) y solo la primera vez que llega el comentario.
        if ($esNuevo && $idConv > 0 && $texto !== '') {
            $this->respuestaAutomatica($canal, $idConv, $idComentario, (string) ($v['media']['id'] ?? ''), $texto);
        }
        return $ok;
    }

    /**
     * «Comenta INFO y te escribimos»: si la publicación comentada tiene respuesta
     * automática y el comentario trae su palabra clave, envía el mensaje privado.
     * Nunca hace fallar el evento: el comentario ya quedó en la Bandeja y una
     * persona puede responderlo a mano.
     */
    private function respuestaAutomatica(array $canal, int $idConv, string $idComentario, string $idPublicacionExterna, string $texto): void
    {
        try {
            $idEmpresa = (int) $canal['id_empresa'];
            $auto = (new \App\repositories\modulos\CrmPublicacionRepository())->respuestaAutoDeExterna($idEmpresa, (int) $canal['id'], $idPublicacionExterna);
            if (!$auto || !\App\Rules\modulos\CrmPublicacionRules::comentarioPide($texto, (string) $auto['auto_palabra'])) {
                return;
            }
            CrmBandejaService::crear($this->apiFija)->enviarRespuestaAutomatica($idConv, $idComentario, (string) $auto['auto_mensaje'], $idEmpresa, (int) $auto['id']);
        } catch (\Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => 'respuestaAutomatica', 'id_conversacion' => $idConv]);
        }
    }

    // ── Conversación y contacto ──────────────────────────────────────────────

    /**
     * Busca o crea la conversación del hilo (bajo candado). Al crearla aplica la
     * regla de reparto de la cuenta y busca o crea el contacto de la persona.
     * $igsidPersona: quién escribe, si no es el propio hilo (comentarios).
     */
    private function obtenerConversacion(array $canal, string $tipo, string $hilo, ?string $publicacion, ?array $perfil, string $tipoOrigen, ?string $igsidPersona = null): array
    {
        $idEmpresa = (int) $canal['id_empresa'];
        $idCanal = (int) $canal['id'];
        $this->conversaciones->lockHilo($idCanal, $tipo, $hilo);
        $conv = $this->conversaciones->buscarHilo($idEmpresa, $idCanal, $tipo, $hilo);
        if ($conv) {
            return $conv;
        }
        $responsable = $this->elegirResponsable($canal);
        $idContacto = $this->obtenerContacto($idEmpresa, $igsidPersona ?? $hilo, $perfil, $tipoOrigen, $responsable);
        $id = $this->conversaciones->crear($idEmpresa, [
            'id_canal' => $idCanal, 'tipo' => $tipo, 'id_hilo_externo' => $hilo,
            'id_publicacion_externa' => $publicacion, 'id_contacto' => $idContacto, 'id_responsable' => $responsable,
        ]);
        return ['id' => $id, 'id_contacto' => $idContacto, 'id_responsable' => $responsable];
    }

    /** Decisión 5: sin asignar, fija o por turnos (el de menos conversaciones abiertas). */
    private function elegirResponsable(array $canal): ?int
    {
        return (new CrmRepartoService($this->conversaciones))->elegir($canal);
    }

    private function obtenerContacto(int $idEmpresa, string $igsid, ?array $perfil, string $tipoOrigen, ?int $responsable): int
    {
        $this->contactos->lockInstagram($idEmpresa, $igsid);
        $existente = $this->contactos->getPorInstagramId($idEmpresa, $igsid);
        if ($existente) {
            return (int) $existente['id'];
        }
        $usuario = ltrim((string) ($perfil['username'] ?? ''), '@');
        $nombre = trim((string) ($perfil['name'] ?? '')) ?: ($usuario !== '' ? '@' . $usuario : 'Instagram ' . substr($igsid, -6));
        $datos = [
            'nombre' => $nombre, 'instagram_id' => $igsid, 'instagram_usuario' => $usuario !== '' ? $usuario : null,
            'id_origen' => $this->catalogos->origenPorCanal($idEmpresa, $tipoOrigen), 'id_responsable' => $responsable,
        ];
        $id = $this->contactos->insertar($datos, $idEmpresa, null);
        $this->log->registrar(0, $idEmpresa, 'CREAR', 'crm_contactos', $id, null, $datos + ['via' => 'webhook_instagram']);
        return $id;
    }

    // ── Utilidades ───────────────────────────────────────────────────────────

    /** Guarda el adjunto en storage/crm_media/{empresa}/{AAAA-MM}/ (no público). Devuelve la ruta relativa. */
    private function guardarAdjunto(int $idEmpresa, string $mid, string $url): ?string
    {
        $sub = $idEmpresa . '/' . date('Y-m');
        $dir = MVC_ROOT . self::DIR_ADJUNTOS . '/' . $sub;
        try {
            $archivo = $this->api($idEmpresa)->descargarAdjunto($url, $dir, substr(sha1($mid), 0, 24));
        } catch (\Throwable $e) {
            return null;
        }
        return $archivo ? 'crm_media/' . $sub . '/' . $archivo : null;
    }

    /** Hora de Meta (milisegundos o segundos) → 'Y-m-d H:i:s' en la zona del sistema. */
    private function fecha($ts): string
    {
        if (is_numeric($ts) && (float) $ts > 0) {
            $s = (int) $ts;
            if ($s > 99999999999) {
                $s = intdiv($s, 1000);
            }
            return date('Y-m-d H:i:s', $s);
        }
        if (is_string($ts) && ($t = strtotime($ts)) !== false) {
            return date('Y-m-d H:i:s', $t);
        }
        return date('Y-m-d H:i:s');
    }

    private function enTransaccion(callable $fn)
    {
        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();
        try {
            $r = $fn();
            if ($managed) $db->commit();
            return $r;
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }
}
