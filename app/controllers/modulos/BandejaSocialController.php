<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Services\modulos\CrmBandejaService;
use App\Services\modulos\CrmConfiguracionService;
use App\Traits\CrmControllerTrait;

/**
 * Bandeja social (modulos/bandeja_social): conversaciones de Instagram
 * (mensajes directos y comentarios). Diseño: docs/diseno/crm-redes-fase1.md §9.2.
 *
 * Permisos: r ver · w responder · u asignar / cerrar / enlazar contacto.
 * Sin acceso total (t): solo las conversaciones asignadas al usuario y las que
 * están sin asignar (§10 del diseño).
 */
class BandejaSocialController extends BaseModuloController
{
    use CrmControllerTrait;

    private CrmBandejaService $bandeja;

    protected function getRutaModulo(): string
    {
        return 'modulos/bandeja_social';
    }

    public function __construct()
    {
        parent::__construct();
        $this->bandeja = CrmBandejaService::crear();
    }

    public function index(): void
    {
        $this->requireLeer();
        $config = CrmConfiguracionService::crear();
        $disponible = $config->disponible();
        $datos = ['usuarios' => [], 'embudos' => []];
        if ($disponible) {
            try {
                $config->asegurarInicial($this->idEmpresa(), $this->idUsuario());
                $datos['usuarios'] = $config->getCatalogos($this->idEmpresa())['usuarios'];
                $datos['embudos'] = array_map(fn($e) => ['id' => $e['id'], 'nombre' => $e['nombre'], 'es_defecto' => $e['es_defecto']],
                    $config->getEmbudosConEtapas($this->idEmpresa()));
            } catch (\Throwable $e) {
                \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            }
        }
        $this->viewWithLayout('layouts.main', 'modulos/bandeja_social/index', [
            'titulo'        => 'Bandeja social',
            'fullWidth'     => true,
            'perm'          => $this->getPermisos(),
            'rutaModulo'    => $this->getRutaModulo(),
            'disponible'    => $disponible,
            'usuarios'      => $datos['usuarios'],
            'embudos'       => $datos['embudos'],
            'idUsuario'     => $this->idUsuario(),
            'puedeOportunidad' => !empty($this->permisosModuloPorRuta('modulos/crm')['crear']),
        ]);
    }

    /** Sin acceso total: solo las suyas y las sin asignar (null = ve todas). */
    private function filtroBandeja(): ?int
    {
        return $this->idUsuarioFiltro();
    }

    /** Conversación visible para el usuario, o corta con 404/403. */
    private function conversacionAccesible(int $id): array
    {
        $det = $this->bandeja->getDetalle($id, $this->idEmpresa());
        if (!$det) {
            $this->json(['ok' => false, 'error' => 'Conversación no encontrada.'], 404);
        }
        $filtro = $this->filtroBandeja();
        if ($filtro !== null && $det['id_responsable'] !== null && (int) $det['id_responsable'] !== $filtro) {
            $this->json(['ok' => false, 'error' => 'Esta conversación está asignada a otro usuario.'], 403);
        }
        return $det;
    }

    public function listarAjax(): void
    {
        $this->requireLeer();
        $this->requireCrmDisponible();
        try {
            $f = [
                'alcance' => (string) ($_GET['alcance'] ?? 'todas'),
                'tipo'    => (string) ($_GET['tipo'] ?? ''),
                'estado'  => (string) ($_GET['estado'] ?? 'abierta'),
                'b'       => trim((string) ($_GET['b'] ?? '')),
                'bot'     => (string) ($_GET['bot'] ?? ''),
                'antes'   => preg_match('/^\d{4}-\d{2}-\d{2}[ T][\d:.]+$/', (string) ($_GET['antes'] ?? '')) ? $_GET['antes'] : null,
            ];
            $this->json([
                'ok'        => true,
                'rows'      => $this->bandeja->listar($this->idEmpresa(), $f, $this->idUsuario(), $this->filtroBandeja()),
                'sin_leer'  => $this->bandeja->contarSinLeer($this->idEmpresa(), $this->filtroBandeja()),
            ]);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    /** Abre la conversación (y la marca leída). `desde_id` = solo mensajes nuevos (sondeo). */
    public function conversacionAjax(): void
    {
        $this->requireLeer();
        $this->requireCrmDisponible();
        $det = $this->conversacionAccesible((int) ($_GET['id'] ?? 0));
        try {
            $r = $this->bandeja->abrir($det, $this->idEmpresa(), max(0, (int) ($_GET['desde_id'] ?? 0)));
            if ((int) $det['sin_leer'] > 0) {
                \App\Services\ContadoresNavbarService::invalidarCrm($this->idEmpresa(), $this->idUsuario());
            }
            if (empty($_GET['desde_id']) && !empty($det['id_contacto'])) {
                $r['oportunidades'] = \App\Services\modulos\CrmOportunidadService::crear()
                    ->getPorContacto((int) $det['id_contacto'], $this->idEmpresa(), null);
            }
            $this->json(['ok' => true] + $r);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function enviarAjax(): void
    {
        $this->requireCrear();
        $this->requireCrmDisponible();
        $det = $this->conversacionAccesible($this->postInt('id'));
        $idEmpresa = $this->idEmpresa();
        $idUsuario = $this->idUsuario();
        session_write_close();   // Instagram puede tardar: no retener la sesión
        try {
            $m = $this->bandeja->enviar((int) $det['id'], (string) ($_POST['modo'] ?? 'dm'), (string) ($_POST['texto'] ?? ''), $idEmpresa, $idUsuario);
            $this->json(['ok' => true, 'mensaje' => $m, 'enviado' => $m['error'] === null, 'error_envio' => $m['error']]);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    /** Imagen por mensaje directo (campo de archivo `imagen`). */
    public function enviarImagenAjax(): void
    {
        $this->requireCrear();
        $this->requireCrmDisponible();
        $det = $this->conversacionAccesible($this->postInt('id'));
        $f = $_FILES['imagen'] ?? null;
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $err = (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE);
            $this->json(['ok' => false, 'error' => in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'La imagen es demasiado grande.' : 'No se recibió la imagen.'], 400);
        }
        $idEmpresa = $this->idEmpresa();
        $idUsuario = $this->idUsuario();
        session_write_close();   // Instagram puede tardar: no retener la sesión
        try {
            $m = $this->bandeja->enviarImagen((int) $det['id'], (string) $f['tmp_name'], (int) $f['size'], $idEmpresa, $idUsuario,
                self::urlImagenPublica(...));
            $this->json(['ok' => true, 'mensaje' => $m, 'enviado' => $m['error'] === null, 'error_envio' => $m['error']]);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    /** Enlace público y temporal que descarga Instagram (absoluto: lo abre un servidor externo). */
    private static function urlImagenPublica(string $token): string
    {
        return url_absoluta('instagram-datos/imagen?t=' . rawurlencode($token));
    }

    public function reintentarAjax(): void
    {
        $this->requireCrear();
        $this->requireCrmDisponible();
        $m = $this->bandeja->getMensaje($this->postInt('id_mensaje'), $this->idEmpresa());
        if (!$m) {
            $this->json(['ok' => false, 'error' => 'Mensaje no encontrado.'], 404);
        }
        $this->conversacionAccesible((int) $m['id_conversacion']);
        $idEmpresa = $this->idEmpresa();
        $idUsuario = $this->idUsuario();
        session_write_close();
        try {
            $r = $this->bandeja->reintentar((int) $m['id'], $idEmpresa, $idUsuario, self::urlImagenPublica(...));
            $this->json(['ok' => true, 'mensaje' => $r, 'enviado' => $r['error'] === null, 'error_envio' => $r['error']]);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    /** `id_responsable` vacío = sin asignar; «yo» = el usuario actual. */
    public function asignarAjax(): void
    {
        $this->requireActualizar();
        $this->requireCrmDisponible();
        $det = $this->conversacionAccesible($this->postInt('id'));
        try {
            $resp = (string) ($_POST['id_responsable'] ?? '');
            $idResp = $resp === 'yo' ? $this->idUsuario() : ((int) $resp ?: null);
            $this->bandeja->asignar((int) $det['id'], $idResp, $this->idEmpresa(), $this->idUsuario());
            $this->json(['ok' => true, 'mensaje' => $idResp ? 'Conversación asignada.' : 'Conversación sin asignar.']);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function estadoAjax(): void
    {
        $this->requireActualizar();
        $this->requireCrmDisponible();
        $det = $this->conversacionAccesible($this->postInt('id'));
        try {
            $estado = (string) ($_POST['estado'] ?? '');
            $this->bandeja->cambiarEstado((int) $det['id'], $estado, $this->idEmpresa(), $this->idUsuario());
            $this->json(['ok' => true, 'mensaje' => $estado === 'cerrada' ? 'Conversación cerrada.' : 'Conversación reabierta.']);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    /** Pausar (`pausado`) o reactivar (`activo`) el asistente virtual en la conversación. */
    public function botEstadoAjax(): void
    {
        $this->requireActualizar();
        $this->requireCrmDisponible();
        $det = $this->conversacionAccesible($this->postInt('id'));
        $bot = \App\Services\modulos\CrmBotService::crear();
        if (!$bot->disponible()) {
            $this->json(['ok' => false, 'error' => 'El asistente virtual no está instalado.'], 503);
        }
        try {
            $estado = (string) ($_POST['estado'] ?? '');
            $bot->cambiarEstadoConversacion((int) $det['id'], $estado, $this->idEmpresa(), $this->idUsuario());
            $this->json(['ok' => true, 'mensaje' => $estado === 'activo'
                ? 'El asistente vuelve a responder en esta conversación.' : 'El asistente ya no responderá en esta conversación.']);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function enlazarContactoAjax(): void
    {
        $this->requireActualizar();
        $this->requireCrmDisponible();
        $det = $this->conversacionAccesible($this->postInt('id'));
        try {
            $this->bandeja->enlazarContacto((int) $det['id'], $this->postInt('id_contacto'), $this->idEmpresa(), $this->idUsuario(), $this->filtroBandeja());
            $this->json(['ok' => true, 'mensaje' => 'Conversación enlazada al contacto.']);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    /** Búsqueda de contactos para «Enlazar a contacto existente». */
    public function buscarContactosAjax(): void
    {
        $this->requireLeer();
        $this->requireCrmDisponible();
        $this->json(['ok' => true, 'data' => \App\Services\modulos\CrmContactoService::crear()
            ->buscar($this->idEmpresa(), trim((string) ($_GET['q'] ?? '')), $this->filtroBandeja())]);
    }

    /** Crea la oportunidad de la conversación (requiere permiso de crear en el CRM). */
    public function crearOportunidadAjax(): void
    {
        $this->requireLeer();
        $this->requireCrmDisponible();
        if (empty($this->permisosModuloPorRuta('modulos/crm')['crear'])) {
            $this->json(['ok' => false, 'error' => 'No tiene permiso para crear oportunidades en el CRM.'], 403);
        }
        $det = $this->conversacionAccesible($this->postInt('id'));
        try {
            $r = $this->bandeja->crearOportunidad($det, $_POST, $this->idEmpresa(), $this->idUsuario());
            $this->json(['ok' => true, 'id' => $r['id'], 'numero' => $r['numero'],
                'mensaje' => $r['ya_existia'] ? 'La oportunidad ya estaba registrada; no se creó otra.' : "Oportunidad #{$r['numero']} creada en el CRM."]);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function respuestasRapidasAjax(): void
    {
        $this->requireLeer();
        $this->json(['ok' => true, 'data' => $this->bandeja->respuestasRapidas($this->idEmpresa(), $this->idUsuario())]);
    }

    /** Aviso del menú: conversaciones abiertas con mensajes sin leer. */
    public function contarAjax(): void
    {
        $this->requireLeer();
        if (!CrmConfiguracionService::crear()->disponible()) {
            $this->json(['ok' => true, 'sin_leer' => 0]);
        }
        $this->json(['ok' => true, 'sin_leer' => $this->bandeja->contarSinLeer($this->idEmpresa(), $this->filtroBandeja())]);
    }

    /**
     * Adjunto de un mensaje (imagen, audio, video): se sirve con permiso y
     * acceso a la conversación, nunca por una URL pública.
     */
    public function adjunto(): void
    {
        $this->requireLeer();
        $m = $this->bandeja->getMensaje((int) ($_GET['id'] ?? 0), $this->idEmpresa());
        if (!$m) {
            http_response_code(404);
            exit;
        }
        $this->conversacionAccesible((int) $m['id_conversacion']);
        $ruta = $this->bandeja->rutaAdjunto($m);
        if (!$ruta) {
            http_response_code(404);
            exit;
        }
        session_write_close();
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($ruta) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($ruta));
        header('Content-Disposition: inline; filename="' . basename($ruta) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=86400');
        readfile($ruta);
        exit;
    }
}
