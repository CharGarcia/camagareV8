<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\Helpers\CryptoHelper;
use App\repositories\modulos\CrmMetaAppRepository;
use App\repositories\modulos\CrmPrivacidadRepository;
use App\Services\ErrorLogService;
use App\Services\InstagramApiService;
use App\Services\LogSistemaService;

/**
 * Callbacks de privacidad que exige Meta para la app de Instagram
 * (diseño §12): eliminación de datos y desautorización.
 *
 * El user_id del signed_request puede ser:
 *  - una CUENTA conectada (Instagram Login: quien autorizó la app) → se desconecta,
 *    se borra su acceso y su perfil, y se vacía el contenido de todas sus
 *    conversaciones y de los contactos que el sistema creó desde ellas;
 *  - una PERSONA que escribió (IGSID) → se vacían sus conversaciones, sus
 *    comentarios y lo que de Instagram tengan sus contactos.
 * Se aplican los dos casos: lo que no exista simplemente no se encuentra.
 */
class CrmPrivacidadService
{
    public function __construct(
        private CrmPrivacidadRepository $repo,
        private ?InstagramApiService $apiFija,   // solo pruebas; si no, la app de cada empresa
        private LogSistemaService $log,
        private CrmMetaAppRepository $apps
    ) {
    }

    public static function crear(?InstagramApiService $api = null): self
    {
        return new self(new CrmPrivacidadRepository(), $api, new LogSistemaService(), new CrmMetaAppRepository());
    }

    public function disponible(): bool
    {
        return $this->repo->disponible();
    }

    /**
     * Solicitud de eliminación. Devuelve lo que Meta espera: url de estado y
     * código de confirmación. Firma inválida = InvalidArgumentException.
     * @return array{url:string, confirmation_code:string}
     */
    public function solicitarEliminacion(string $signedRequest, string $urlEstadoBase): array
    {
        [$idExterno, $empresas, $appId] = $this->idDeSolicitud($signedRequest);
        $codigo = strtoupper(bin2hex(random_bytes(8)));
        $idSolicitud = $this->repo->registrar($codigo, 'eliminacion', $idExterno);
        try {
            $detalle = $this->eliminar($idExterno, $empresas, $appId);
            $total = array_sum($detalle);
            $this->repo->finalizar($idSolicitud, $total > 0 ? 'completada' : 'sin_datos', $detalle);
        } catch (\Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => 'eliminar', 'id_solicitud' => $idSolicitud]);
            $this->repo->finalizar($idSolicitud, 'error', []);
        }
        return ['url' => $urlEstadoBase . '?codigo=' . $codigo, 'confirmation_code' => $codigo];
    }

    /** Quitaron la app: la cuenta se desconecta (sin borrar su historial). */
    public function desautorizar(string $signedRequest): void
    {
        [$idExterno, $empresas] = $this->idDeSolicitud($signedRequest);
        $idSolicitud = $this->repo->registrar(strtoupper(bin2hex(random_bytes(8))), 'desautorizacion', $idExterno);
        $canales = $this->repo->canalesPorExterno($idExterno, $empresas);
        $n = 0;
        if ($canales) {
            $this->enTransaccion(function () use ($canales, &$n) {
                $n = $this->repo->desconectarCanales(array_column($canales, 'id'), false);
                foreach ($canales as $c) {
                    $this->log->registrar(0, (int) $c['id_empresa'], 'DESAUTORIZAR_META', 'crm_canales', (int) $c['id'], null, ['estado' => 'desconectado']);
                }
            });
        }
        $this->repo->finalizar($idSolicitud, $n > 0 ? 'completada' : 'sin_datos', ['canales' => $n]);
    }

    /** Estado legible de una solicitud (página pública por código). */
    public function estado(string $codigo): ?array
    {
        $codigo = strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', $codigo));
        return $codigo !== '' ? $this->repo->getPorCodigo($codigo) : null;
    }

    /**
     * Valida el signed_request con la app de Meta de alguna empresa donde aparece
     * ese id (o, si no aparece en ninguna, con las apps registradas) y devuelve
     * [user_id, empresas que usan esa app, app_id]. Todo el borrado se acota a esas
     * empresas: una app no puede borrar datos de otra empresa.
     * Con API fija (pruebas): empresas = null (sin acotar).
     * @return array{0:string, 1:?array, 2:?string}
     */
    private function idDeSolicitud(string $signedRequest): array
    {
        if ($this->apiFija) {
            $datos = $this->apiFija->leerSignedRequest($signedRequest);
            return [$this->idValido($datos), null, null];
        }
        // El user_id se lee SIN confiar todavía, solo para saber qué apps probar.
        $partes = explode('.', $signedRequest, 2);
        $sinVerificar = count($partes) === 2 ? json_decode((string) base64_decode(strtr($partes[1], '-_', '+/'), false), true) : null;
        $idPropuesto = is_array($sinVerificar) ? trim((string) ($sinVerificar['user_id'] ?? '')) : '';
        $empresas = $idPropuesto !== '' ? $this->repo->empresasDeId($idPropuesto) : [];
        $candidatas = $empresas ? $this->apps->porEmpresas($empresas) : $this->apps->todasActivas();
        foreach ($candidatas as $a) {
            $api = new InstagramApiService(['app_id' => $a['app_id'], 'app_secret' => CryptoHelper::desencriptar((string) $a['app_secret_cifrado'])]);
            $datos = $api->leerSignedRequest($signedRequest);
            if ($datos !== null) {
                return [$this->idValido($datos), $this->apps->empresasDeApp((string) $a['app_id']), (string) $a['app_id']];
            }
        }
        throw new \InvalidArgumentException('Solicitud no válida.');
    }

    private function idValido(?array $datos): string
    {
        $id = is_array($datos) ? trim((string) ($datos['user_id'] ?? '')) : '';
        if ($id === '' || !preg_match('/^[0-9A-Za-z_\-]{1,64}$/', $id)) {
            throw new \InvalidArgumentException('Solicitud no válida.');
        }
        return $id;
    }

    /** @return array<string,int> conteos de lo borrado (sin datos personales) */
    private function eliminar(string $idExterno, ?array $empresas = null, ?string $appId = null): array
    {
        $canales = $this->repo->canalesPorExterno($idExterno, $empresas);
        $convs = array_merge(
            $this->repo->conversacionesDeCanales(array_column($canales, 'id')),
            $this->repo->conversacionesDePersona($idExterno, $empresas)
        );
        $idsConv = array_values(array_unique(array_map('intval', array_column($convs, 'id'))));
        $adjuntos = $this->repo->adjuntos($idsConv, $idExterno, $empresas);

        $detalle = $this->enTransaccion(function () use ($canales, $convs, $idsConv, $idExterno, $empresas, $appId) {
            $d = [
                'mensajes'       => $this->repo->vaciarMensajesDeConversaciones($idsConv) + $this->repo->vaciarComentariosDePersona($idExterno, $empresas),
                'contactos'      => $this->repo->anonimizarContactos($idsConv, $idExterno, $empresas),
                'conversaciones' => $this->repo->vaciarConversaciones($idsConv),
                'cuentas'        => $this->repo->desconectarCanales(array_column($canales, 'id'), true),
                'eventos'        => $this->repo->vaciarEventos($idExterno, $appId),
            ];
            // Auditoría por empresa afectada: solo conteos, nunca el contenido.
            $empresas = array_unique(array_merge(array_column($canales, 'id_empresa'), array_column($convs, 'id_empresa')));
            foreach ($empresas as $idEmpresa) {
                $this->log->registrar(0, (int) $idEmpresa, 'ELIMINAR_DATOS_META', 'crm_canales', null, null, $d);
            }
            return $d;
        });

        // Archivos: después del COMMIT (si algo fallaba antes, siguen ahí para reintentar).
        $detalle['adjuntos'] = $this->borrarArchivos($adjuntos);
        return $detalle;
    }

    private function borrarArchivos(array $rutas): int
    {
        $base = realpath(MVC_ROOT . CrmWebhookService::DIR_ADJUNTOS);
        $n = 0;
        foreach (array_unique($rutas) as $rel) {
            $rel = (string) $rel;
            if (!$base || !str_starts_with($rel, 'crm_media/') || str_contains($rel, '..')) {
                continue;
            }
            $abs = realpath(MVC_ROOT . '/storage/' . $rel);
            if ($abs && str_starts_with($abs, $base) && is_file($abs) && @unlink($abs)) {
                $n++;
            }
        }
        return $n;
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
