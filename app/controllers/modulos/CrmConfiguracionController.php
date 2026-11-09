<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Services\modulos\CrmConfiguracionService;
use App\Traits\CrmControllerTrait;

/**
 * Configuración del CRM (modulos/crm_configuracion): embudos, etapas, orígenes
 * y motivos de pérdida. En el paso 4 del diseño se suma la conexión de Instagram.
 * Diseño: docs/diseno/crm-redes-fase1.md §9.3.
 */
class CrmConfiguracionController extends BaseModuloController
{
    use CrmControllerTrait;

    private const CATALOGOS = ['origenes', 'motivos'];

    private CrmConfiguracionService $configCrm;

    protected function getRutaModulo(): string
    {
        return 'modulos/crm_configuracion';
    }

    public function __construct()
    {
        parent::__construct();
        $this->configCrm = CrmConfiguracionService::crear();
    }

    // ── Pantalla ─────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireLeer();
        $disponible = $this->configCrm->disponible();
        if ($disponible) {
            try {
                $this->configCrm->asegurarInicial($this->idEmpresa(), $this->idUsuario());
            } catch (\Throwable $e) {
                \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            }
        }
        // Resultado del retorno de Instagram (instagramCallback): de un solo uso.
        $flash = $_SESSION['crm_config_flash'] ?? null;
        unset($_SESSION['crm_config_flash']);

        $this->viewWithLayout('layouts.main', 'modulos/crm_configuracion/index', [
            'titulo'         => 'Configuración del CRM',
            'fullWidth'      => true,
            'perm'           => $this->getPermisos(),
            'rutaModulo'     => $this->getRutaModulo(),
            'disponible'     => $disponible,
            // App de Meta de ESTA empresa (sin el App Secret) y las URLs que hay que
            // registrar en ella. Las ve quien tenga acceso al módulo.
            'appMeta'        => $disponible ? \App\Services\modulos\CrmMetaAppService::crear()->obtener($this->idEmpresa()) : null,
            'urlsMeta'       => self::urlsMeta(),
            'flash'          => is_array($flash) ? $flash : null,
        ]);
    }

    // ── Instagram ────────────────────────────────────────────────────────────

    private const OAUTH_SESION  = 'crm_ig_oauth';
    private const OAUTH_MINUTOS = 10;

    /** Dirección de retorno registrada en la app de Meta (debe coincidir exacto). */
    private function redirectUri(): string
    {
        return url_absoluta('modulos/crm_configuracion/instagramCallback');
    }

    /** Las cuatro URLs que la empresa registra en su app de Meta. */
    private static function urlsMeta(): array
    {
        return [
            'oauth'        => url_absoluta('modulos/crm_configuracion/instagramCallback'),
            'webhook'      => url_absoluta('instagram-webhook'),
            'desautorizar' => url_absoluta('instagram-datos/desautorizar'),
            'eliminar'     => url_absoluta('instagram-datos/eliminar'),
        ];
    }

    /** Lista de canales (sin tokens). */
    public function canalesAjax(): void
    {
        $this->requireLeer();
        $this->requireCrmDisponible();
        $svc = \App\Services\modulos\CrmCanalService::crear();
        $this->json([
            'ok' => true,
            'canales' => $svc->getCanales($this->idEmpresa()),
            'api_configurada' => $svc->apiConfigurada($this->idEmpresa()),
        ]);
    }

    // ── Asistente virtual (fase 2) ───────────────────────────────────────────

    /** El SQL de la fase 2 está aplicado; si no, 503 con un mensaje claro. */
    private function botDisponible(): \App\Services\modulos\CrmBotService
    {
        $bot = \App\Services\modulos\CrmBotService::crear();
        if (!$bot->disponible()) {
            $this->json(['ok' => false, 'error' => 'El asistente virtual todavía no está instalado en la base de datos (SQL de la fase 2).'], 503);
        }
        return $bot;
    }

    /** Configuración de una cuenta, estado de la IA de la empresa y consumo del mes. */
    public function botAjax(): void
    {
        $this->requireLeer();
        $this->requireCrmDisponible();
        $bot = $this->botDisponible();
        try {
            $this->json([
                'ok' => true,
                'config' => $bot->obtenerConfig((int) ($_GET['id_canal'] ?? 0), $this->idEmpresa()),
                'ia' => $bot->estadoIa($this->idEmpresa()),
                'consumo' => $bot->consumoMes($this->idEmpresa()),
            ]);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function guardarBotAjax(): void
    {
        $this->requireActualizar();
        $this->requireCrmDisponible();
        $bot = $this->botDisponible();
        try {
            $d = $_POST;
            $d['horario_atencion'] = json_decode((string) ($_POST['horario_atencion'] ?? '{}'), true) ?: [];
            $r = $bot->guardarConfig($this->postInt('id_canal'), $d, $this->idEmpresa(), $this->idUsuario());
            $this->json(['ok' => true, 'mensaje' => $r['activo'] ? 'Asistente guardado y activo.' : 'Asistente guardado (inactivo).']);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    /** Conversación simulada: no envía nada a Instagram ni escribe en el CRM. */
    public function probarBotAjax(): void
    {
        $this->requireActualizar();
        $this->requireCrmDisponible();
        $bot = $this->botDisponible();
        $idEmpresa = $this->idEmpresa();
        $idUsuario = $this->idUsuario();
        session_write_close();   // la IA puede tardar: no retener la sesión
        try {
            $mensajes = json_decode((string) ($_POST['mensajes'] ?? '[]'), true);
            $r = $bot->probar($this->postInt('id_canal'), is_array($mensajes) ? $mensajes : [], $idEmpresa, $idUsuario);
            $this->json(['ok' => true] + $r);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    // ── App de Meta de la empresa ────────────────────────────────────────────

    /** Datos de la app (sin el App Secret) para repintar la tarjeta. */
    public function appMetaAjax(): void
    {
        $this->requireLeer();
        $this->requireCrmDisponible();
        $this->json(['ok' => true, 'app' => \App\Services\modulos\CrmMetaAppService::crear()->obtener($this->idEmpresa())]);
    }

    /** Registra o actualiza App ID / App Secret. App Secret vacío al editar = se conserva. */
    public function guardarAppMetaAjax(): void
    {
        $svc = \App\Services\modulos\CrmMetaAppService::crear();
        $svc->obtener($this->idEmpresa())['configurada'] ? $this->requireActualizar() : $this->requireCrear();
        $this->requireCrmDisponible();
        try {
            $svc->guardar($this->idEmpresa(), (string) ($_POST['app_id'] ?? ''), (string) ($_POST['app_secret'] ?? ''), $this->idUsuario());
            $this->json(['ok' => true, 'mensaje' => 'App de Meta guardada.', 'app' => $svc->obtener($this->idEmpresa())]);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function regenerarTokenMetaAjax(): void
    {
        $this->requireActualizar();
        $this->requireCrmDisponible();
        try {
            $svc = \App\Services\modulos\CrmMetaAppService::crear();
            $svc->regenerarToken($this->idEmpresa(), $this->idUsuario());
            $this->json(['ok' => true, 'mensaje' => 'Token regenerado. Péguelo también en la configuración del webhook en Meta.', 'app' => $svc->obtener($this->idEmpresa())]);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function quitarAppMetaAjax(): void
    {
        $this->requireEliminar();
        $this->requireCrmDisponible();
        try {
            $svc = \App\Services\modulos\CrmMetaAppService::crear();
            $svc->quitar($this->idEmpresa(), $this->idUsuario());
            $this->json(['ok' => true, 'mensaje' => 'App de Meta quitada.', 'app' => $svc->obtener($this->idEmpresa())]);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    /**
     * Inicia la conexión: genera el `state` (aleatorio, de un solo uso, ligado a
     * la empresa y al usuario, vence en 10 min) y devuelve la URL de Instagram.
     */
    public function conectarInstagramAjax(): void
    {
        $this->requireCrear();
        $this->requireCrmDisponible();
        try {
            $state = bin2hex(random_bytes(24));
            $url = \App\Services\modulos\CrmCanalService::crear()->urlConexion($state, $this->redirectUri(), $this->idEmpresa());
            $_SESSION[self::OAUTH_SESION] = [
                'state'      => $state,
                'id_empresa' => $this->idEmpresa(),
                'id_usuario' => $this->idUsuario(),
                'expira'     => time() + self::OAUTH_MINUTOS * 60,
            ];
            $this->json(['ok' => true, 'url' => $url]);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    /**
     * Retorno de Instagram (?code=…&state=… o ?error=…). Valida el `state`,
     * suelta la sesión mientras habla con Meta, guarda el resultado como aviso
     * de un solo uso y vuelve a la URL limpia del módulo.
     */
    public function instagramCallback(): void
    {
        $this->requireCrear();
        $volver = rtrim(BASE_URL, '/') . '/modulos/crm_configuracion';
        $pend = $_SESSION[self::OAUTH_SESION] ?? null;
        unset($_SESSION[self::OAUTH_SESION]);

        $state = (string) ($_GET['state'] ?? '');
        $valido = is_array($pend)
            && $state !== '' && hash_equals((string) $pend['state'], $state)
            && (int) $pend['expira'] >= time()
            && (int) $pend['id_empresa'] === $this->idEmpresa()
            && (int) $pend['id_usuario'] === $this->idUsuario();

        if (!$valido) {
            $_SESSION['crm_config_flash'] = ['tipo' => 'error', 'texto' => 'La conexión con Instagram venció o no se inició desde esta pantalla. Vuelva a pulsar «Conectar Instagram».'];
            header('Location: ' . $volver);
            exit;
        }
        if (!empty($_GET['error'])) {
            $_SESSION['crm_config_flash'] = ['tipo' => 'warning', 'texto' => 'Se canceló la autorización en Instagram; no se conectó ninguna cuenta.'];
            header('Location: ' . $volver);
            exit;
        }
        $code = (string) ($_GET['code'] ?? '');
        $idEmpresa = $this->idEmpresa();
        $idUsuario = $this->idUsuario();

        // Las llamadas a Meta pueden tardar: sin el candado de la sesión, el resto
        // de pestañas del usuario sigue funcionando mientras tanto.
        session_write_close();
        try {
            if ($code === '' || !$this->configCrm->disponible()) {
                throw new \RuntimeException('Instagram no devolvió el código de autorización.');
            }
            $r = \App\Services\modulos\CrmCanalService::crear()->completarConexion($code, $this->redirectUri(), $idEmpresa, $idUsuario);
            $flash = ['tipo' => 'success', 'texto' => "Cuenta @{$r['usuario']} conectada.", 'avisos' => $r['avisos']];
        } catch (\Throwable $e) {
            $negocio = ($e instanceof \InvalidArgumentException || $e instanceof \RuntimeException) && !($e instanceof \PDOException);
            if (!$negocio) {
                \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            }
            $flash = ['tipo' => 'error', 'texto' => $negocio ? $e->getMessage() : 'No se pudo conectar la cuenta. Intente de nuevo.'];
        }
        session_start();
        $_SESSION['crm_config_flash'] = $flash;
        header('Location: ' . $volver);
        exit;
    }

    // ── LinkedIn (fase 3) ────────────────────────────────────────────────────

    private const OAUTH_LI_SESION = 'crm_li_oauth';

    /** Dirección de retorno registrada en la app de LinkedIn (debe coincidir exacto). */
    private function redirectUriLinkedin(): string
    {
        return url_absoluta('modulos/crm_configuracion/linkedinCallback');
    }

    private function linkedin(): \App\Services\modulos\CrmLinkedinService
    {
        $svc = \App\Services\modulos\CrmLinkedinService::crear();
        if (!$svc->disponible()) {
            $this->json(['ok' => false, 'error' => 'LinkedIn todavía no está instalado en la base de datos (SQL de la fase 3).'], 503);
        }
        return $svc;
    }

    /** App de LinkedIn (sin el secreto) y la dirección de retorno que se registra en ella. */
    public function linkedinAppAjax(): void
    {
        $this->requireLeer();
        $this->requireCrmDisponible();
        $this->json(['ok' => true, 'app' => $this->linkedin()->obtenerApp($this->idEmpresa()), 'redirect' => $this->redirectUriLinkedin()]);
    }

    public function guardarLinkedinAppAjax(): void
    {
        $svc = $this->linkedin();
        $svc->obtenerApp($this->idEmpresa())['configurada'] ? $this->requireActualizar() : $this->requireCrear();
        $this->requireCrmDisponible();
        try {
            $svc->guardarApp($this->idEmpresa(), (string) ($_POST['client_id'] ?? ''), (string) ($_POST['client_secret'] ?? ''),
                !empty($_POST['paginas_aprobadas']), $this->idUsuario());
            $this->json(['ok' => true, 'mensaje' => 'App de LinkedIn guardada.', 'app' => $svc->obtenerApp($this->idEmpresa())]);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function quitarLinkedinAppAjax(): void
    {
        $this->requireEliminar();
        $this->requireCrmDisponible();
        try {
            $this->linkedin()->quitarApp($this->idEmpresa(), $this->idUsuario());
            $this->json(['ok' => true, 'mensaje' => 'App de LinkedIn quitada.']);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    /** `paginas=1`: además pide permiso para las páginas de empresa. */
    public function conectarLinkedinAjax(): void
    {
        $this->requireCrear();
        $this->requireCrmDisponible();
        try {
            $paginas = !empty($_POST['paginas']);
            $state = bin2hex(random_bytes(24));
            $url = $this->linkedin()->urlConexion($state, $this->redirectUriLinkedin(), $this->idEmpresa(), $paginas);
            $_SESSION[self::OAUTH_LI_SESION] = [
                'state' => $state, 'id_empresa' => $this->idEmpresa(), 'id_usuario' => $this->idUsuario(),
                'paginas' => $paginas, 'expira' => time() + self::OAUTH_MINUTOS * 60,
            ];
            $this->json(['ok' => true, 'url' => $url]);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    /** Retorno de LinkedIn: mismo cuidado que instagramCallback (state de un solo uso, sesión suelta, URL limpia). */
    public function linkedinCallback(): void
    {
        $this->requireCrear();
        $volver = rtrim(BASE_URL, '/') . '/modulos/crm_configuracion';
        $pend = $_SESSION[self::OAUTH_LI_SESION] ?? null;
        unset($_SESSION[self::OAUTH_LI_SESION]);
        $state = (string) ($_GET['state'] ?? '');
        $valido = is_array($pend) && $state !== '' && hash_equals((string) $pend['state'], $state)
            && (int) $pend['expira'] >= time() && (int) $pend['id_empresa'] === $this->idEmpresa()
            && (int) $pend['id_usuario'] === $this->idUsuario();
        if (!$valido) {
            $_SESSION['crm_config_flash'] = ['tipo' => 'error', 'texto' => 'La conexión con LinkedIn venció o no se inició desde esta pantalla. Vuelva a pulsar «Conectar LinkedIn».'];
            header('Location: ' . $volver);
            exit;
        }
        if (!empty($_GET['error'])) {
            $_SESSION['crm_config_flash'] = ['tipo' => 'warning', 'texto' => 'Se canceló la autorización en LinkedIn; no se conectó ninguna cuenta.'];
            header('Location: ' . $volver);
            exit;
        }
        $code = (string) ($_GET['code'] ?? '');
        $idEmpresa = $this->idEmpresa();
        $idUsuario = $this->idUsuario();
        session_write_close();
        try {
            $svc = \App\Services\modulos\CrmLinkedinService::crear();
            if ($code === '' || !$svc->disponible()) {
                throw new \RuntimeException('LinkedIn no devolvió el código de autorización.');
            }
            $r = $svc->completarConexion($code, $this->redirectUriLinkedin(), $idEmpresa, $idUsuario, (bool) $pend['paginas']);
            $flash = $r['cuentas']
                ? ['tipo' => 'success', 'texto' => 'LinkedIn conectado: ' . implode(', ', $r['cuentas']) . '.', 'avisos' => $r['avisos']]
                : ['tipo' => 'warning', 'texto' => 'No se conectó ninguna cuenta de LinkedIn.', 'avisos' => $r['avisos']];
        } catch (\Throwable $e) {
            $negocio = ($e instanceof \InvalidArgumentException || $e instanceof \RuntimeException) && !($e instanceof \PDOException);
            if (!$negocio) {
                \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            }
            $flash = ['tipo' => 'error', 'texto' => $negocio ? $e->getMessage() : 'No se pudo conectar LinkedIn. Intente de nuevo.'];
        }
        session_start();
        $_SESSION['crm_config_flash'] = $flash;
        header('Location: ' . $volver);
        exit;
    }

    public function desconectarCanalAjax(): void
    {
        $this->requireEliminar();
        $this->requireCrmDisponible();
        try {
            \App\Services\modulos\CrmCanalService::crear()->desconectar($this->postInt('id'), $this->idEmpresa(), $this->idUsuario());
            $this->json(['ok' => true, 'mensaje' => 'Cuenta desconectada. Ya no se recibirán ni enviarán mensajes por ella.']);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    /** Lee el perfil con el token guardado: confirma que la conexión sigue viva. */
    public function probarCanalAjax(): void
    {
        $this->requireLeer();
        $this->requireCrmDisponible();
        $id = $this->postInt('id');
        $idEmpresa = $this->idEmpresa();
        session_write_close();   // llamada externa: no retener la sesión
        try {
            $p = \App\Services\modulos\CrmCanalService::crear()->probar($id, $idEmpresa);
            $this->json(['ok' => true, 'mensaje' => "Conexión correcta con @{$p['username']}."]);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    /** Regla de asignación de conversaciones nuevas y ventana humana (7 días). */
    public function guardarCanalAjax(): void
    {
        $this->requireActualizar();
        $this->requireCrmDisponible();
        try {
            $usuarios = is_array($_POST['usuarios'] ?? null) ? $_POST['usuarios'] : [];
            \App\Services\modulos\CrmCanalService::crear()->guardarAjustes(
                $this->postInt('id'), (string) ($_POST['regla'] ?? ''), $usuarios,
                ($_POST['ventana_humana'] ?? '0') === '1', $this->idEmpresa(), $this->idUsuario()
            );
            $this->json(['ok' => true, 'mensaje' => 'Ajustes guardados.']);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    // ── Embudos y catálogos ──────────────────────────────────────────────────

    /** Todo lo configurable, incluidos los inactivos. Siembra la primera vez. */
    public function datosAjax(): void
    {
        $this->requireLeer();
        $this->requireCrmDisponible();
        try {
            $this->configCrm->asegurarInicial($this->idEmpresa(), $this->idUsuario());
            $this->json(['ok' => true, 'embudos' => $this->configCrm->getEmbudosConEtapas($this->idEmpresa(), false)]
                + $this->configCrm->getCatalogos($this->idEmpresa(), false)
                + ['permisos' => $this->getPermisos()]);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function guardarEmbudoAjax(): void
    {
        $this->postInt('id') > 0 ? $this->requireActualizar() : $this->requireCrear();
        $this->requireCrmDisponible();
        try {
            $id = $this->configCrm->guardarEmbudo($_POST, $this->idEmpresa(), $this->idUsuario());
            $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Embudo guardado.']);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function eliminarEmbudoAjax(): void
    {
        $this->requireEliminar();
        $this->requireCrmDisponible();
        try {
            $this->configCrm->eliminarEmbudo($this->postInt('id'), $this->idEmpresa(), $this->idUsuario());
            $this->json(['ok' => true, 'mensaje' => 'Embudo eliminado.']);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function guardarEtapaAjax(): void
    {
        $this->postInt('id') > 0 ? $this->requireActualizar() : $this->requireCrear();
        $this->requireCrmDisponible();
        try {
            $id = $this->configCrm->guardarEtapa($_POST, $this->idEmpresa(), $this->idUsuario());
            $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Etapa guardada.']);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    /** `ids[]` = etapas intermedias en el orden nuevo. */
    public function reordenarEtapasAjax(): void
    {
        $this->requireActualizar();
        $this->requireCrmDisponible();
        try {
            $ids = is_array($_POST['ids'] ?? null) ? $_POST['ids'] : [];
            $this->configCrm->reordenarEtapas($this->postInt('id_embudo'), $ids, $this->idEmpresa(), $this->idUsuario());
            $this->json(['ok' => true]);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function eliminarEtapaAjax(): void
    {
        $this->requireEliminar();
        $this->requireCrmDisponible();
        try {
            $this->configCrm->eliminarEtapa($this->postInt('id'), $this->idEmpresa(), $this->idUsuario());
            $this->json(['ok' => true, 'mensaje' => 'Etapa eliminada.']);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    /** `catalogo` = origenes | motivos. */
    public function guardarCatalogoAjax(): void
    {
        $this->postInt('id') > 0 ? $this->requireActualizar() : $this->requireCrear();
        $this->requireCrmDisponible();
        try {
            $id = $this->configCrm->guardarCatalogo($this->catalogo(), $_POST, $this->idEmpresa(), $this->idUsuario());
            $this->json(['ok' => true, 'id' => $id, 'mensaje' => 'Guardado.']);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function eliminarCatalogoAjax(): void
    {
        $this->requireEliminar();
        $this->requireCrmDisponible();
        try {
            $this->configCrm->eliminarCatalogo($this->catalogo(), $this->postInt('id'), $this->idEmpresa(), $this->idUsuario());
            $this->json(['ok' => true, 'mensaje' => 'Eliminado.']);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    private function catalogo(): string
    {
        $c = (string) ($_POST['catalogo'] ?? '');
        if (!in_array($c, self::CATALOGOS, true)) {
            throw new \InvalidArgumentException('Catálogo no válido.');
        }
        return $c;
    }
}
