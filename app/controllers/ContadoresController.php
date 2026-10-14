<?php

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\PermisoSubmodulo;
use App\Services\ContadoresNavbarService;
use App\Services\SesionActivaService;
use App\Services\SuscripcionReportePagoService;
use App\Services\VigenciaSuscripcionService;
use App\Traits\PermisoModuloTrait;

/**
 * Endpoint unificado de contadores del navbar (badges de avisos).
 *
 * Reemplaza a los ~10 endpoints countBorradoresAjax/countPendientesAjax por
 * una sola llamada con caché. Incluye únicamente los contadores cuyo módulo el
 * usuario tiene permiso de 'ver' (Nivel 3 ve todo). Tareas es global por usuario.
 *
 * Desde el 13-09-2026 devuelve también `sesion_activa`, absorbiendo el sondeo
 * que hacía `partials/scripts.php` contra /auth/verificar-sesion. Eran dos
 * peticiones cada 5 s por pestaña abierta (24 por minuto) y ahora es una cada
 * 30 s (2 por minuto): el mismo aviso al usuario con 1/12 del trabajo para el
 * servidor. /auth/verificar-sesion sigue existiendo — lo usa el respaldo de
 * scripts.php para las pantallas que no cargan el navbar.
 */
class ContadoresController extends Controller
{
    use PermisoModuloTrait;

    private ContadoresNavbarService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new ContadoresNavbarService();
    }

    /** GET /contadores/navbarAjax → { ok:true, sesion_activa:bool, contadores:{...} } */
    public function navbarAjax(): void
    {
        $this->requireAuth();

        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
        $nivel     = (int) ($_SESSION['nivel'] ?? 1);

        // ── Sesión desplazada por otro dispositivo ───────────────────────────
        // Va ANTES del session_write_close(): validarToken() escribe
        // $_SESSION['_sesion_last_touch'] para hacer el UPDATE de actividad solo
        // cada 5 min, y con la sesión ya cerrada ese write se perdería, así que el
        // UPDATE volvería a ejecutarse en CADA sondeo. Orden invertido = una
        // escritura de más en la BD por cada petición.
        $sesionActiva = true;
        $token = (string) ($_SESSION['session_token'] ?? '');
        if ($token !== '') {
            try {
                $sesionActiva = (new SesionActivaService())->validarToken($token);
            } catch (\Throwable $e) {
                // Mismo criterio que AuthMiddleware: un error de BD no echa a nadie.
                $sesionActiva = true;
            }
        }

        // Liberar el lock de sesión cuanto antes: de aquí en adelante solo se LEE
        // (nunca se escribe) y este endpoint se consulta con alta frecuencia.
        // Los valores de $_SESSION siguen siendo legibles tras cerrar la escritura.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        // Sesión desplazada: no tiene sentido calcular los contadores, el navegador
        // va camino del login.
        if (!$sesionActiva) {
            $this->json(['ok' => true, 'sesion_activa' => false, 'contadores' => (object) []]);
        }

        try {
            $contadores = $this->service->getContadores(
                $idEmpresa,
                $idUsuario,
                fn (string $ruta): bool => $this->permisosModuloPorRuta($ruta)['ver'] === true,
                $nivel
            );
            $this->json(['ok' => true, 'sesion_activa' => true, 'contadores' => $contadores]);
        } catch (\Throwable $e) {
            error_log('ContadoresController::navbarAjax ' . $e->getMessage());
            // sesion_activa = true a propósito: que fallen los contadores no es
            // motivo para sacar al usuario del sistema.
            $this->json(['ok' => false, 'sesion_activa' => true, 'contadores' => (object) []]);
        }
    }

    /**
     * GET /contadores/avisoSuscripcionAjax[?forzar=1] → { ok, mostrar, vigencia, puede_ver_detalle }
     *
     * Modal de vigencia de la suscripción del sistema. Sin `forzar`, solo responde
     * mostrar=true una vez por ingreso o cambio de empresa (marca en sesión que ponen
     * AuthController y EmpresaController) y solo si está vencida o a ≤ 2 días. Con
     * `forzar` (clic en el aviso del navbar) devuelve el detalle siempre que haya vigencia.
     * Lo ven todos los usuarios de la empresa activa (decisión del usuario, 09-10-2026).
     */
    public function avisoSuscripcionAjax(): void
    {
        $this->requireAuth();

        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $forzar    = !empty($_GET['forzar']);
        $pendiente = !empty($_SESSION[VigenciaSuscripcionService::CLAVE_SESION]);

        // Se consume ANTES de cerrar la escritura de la sesión: una revisión por ingreso.
        if ($pendiente) {
            unset($_SESSION[VigenciaSuscripcionService::CLAVE_SESION]);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if ($idEmpresa <= 0 || (!$pendiente && !$forzar)) {
            $this->json(['ok' => true, 'mostrar' => false]);
        }

        try {
            // En vivo (sin caché): un pago recién registrado debe verse ya.
            $vig = (new VigenciaSuscripcionService())->estado($idEmpresa, false);
        } catch (\Throwable $e) {
            error_log('ContadoresController::avisoSuscripcionAjax ' . $e->getMessage());
            $this->json(['ok' => false, 'mostrar' => false]);
        }

        $mostrar = $forzar ? $vig !== null : VigenciaSuscripcionService::requiereModal($vig);
        $this->json([
            'ok'                => true,
            'mostrar'           => $mostrar,
            'vigencia'          => $mostrar ? $vig : null,
            'puede_ver_detalle' => $mostrar && $this->permisosModuloPorRuta(ContadoresNavbarService::RUTA_EMPRESA)['ver'] === true,
            // Formulario «Ya pagué / enviar mensaje» del modal (falso sin el SQL aplicado).
            'puede_reportar'    => $mostrar && (new SuscripcionReportePagoService())->disponible(),
            'max_mb'            => (int) (SuscripcionReportePagoService::MAX_BYTES / 1048576),
            'dias_revision'     => SuscripcionReportePagoService::DIAS_REVISION,
        ]);
    }

    /**
     * POST /contadores/reportarPagoSuscripcionAjax (multipart: mensaje, comprobante, token_guardado)
     * → { ok, mensaje, ya_existia, correo_enviado }
     *
     * El usuario de la empresa activa reporta el pago de la suscripción desde el modal de
     * vigencia: se avisa por correo a la controladora y el aviso deja de salir mientras se
     * revisa. Lo puede enviar cualquier usuario de la empresa, igual que ve el modal.
     */
    public function reportarPagoSuscripcionAjax(): void
    {
        $this->requireAuth();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->json(['ok' => false, 'mensaje' => 'Método no permitido.'], 405);
        }

        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        if ($idEmpresa <= 0) {
            $this->json(['ok' => false, 'mensaje' => 'Selecciona una empresa.']);
        }

        try {
            $res = (new SuscripcionReportePagoService())->reportar(
                $idEmpresa,
                $idUsuario,
                (string) ($_POST['mensaje'] ?? ''),
                $_FILES['comprobante'] ?? null,
                $_POST['token_guardado'] ?? null
            );
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['ok' => false, 'mensaje' => 'No se pudo enviar el reporte. Inténtalo de nuevo.']);
        }

        $dias = SuscripcionReportePagoService::DIAS_REVISION;
        if ($res['ya_existia']) {
            $msg = 'Tu reporte ya estaba registrado; no se envió otro.';
        } elseif ($res['correo_enviado']) {
            $msg = 'Recibimos tu reporte y lo revisaremos. Mientras tanto no verás este aviso (máximo ' . $dias . ' días).';
        } else {
            $msg = 'Tu reporte quedó registrado y lo revisaremos, aunque no se pudo enviar el correo de aviso. Mientras tanto no verás este aviso (máximo ' . $dias . ' días).';
        }
        $this->json(['ok' => true, 'mensaje' => $msg] + $res);
    }

    /**
     * Avisos del navbar que, al hacer clic, abren su módulo YA FILTRADO por los
     * documentos que cuentan: ruta MVC => [aviso => filtro del buscador].
     * Whitelist: lo único que puede quedar en sesión sale de aquí.
     */
    private const FILTROS_AVISO = [
        'modulos/factura-venta'       => ['novedad_sri' => 'sri:novedad', 'borrador' => 'estado:borrador'],
        'modulos/liquidacion-compra'  => ['novedad_sri' => 'sri:novedad', 'borrador' => 'estado:borrador'],
        'modulos/retenciones_compras' => ['novedad_sri' => 'sri:novedad', 'borrador' => 'estado:borrador'],
        'modulos/notas_credito'       => ['novedad_sri' => 'sri:novedad', 'borrador' => 'estado:borrador'],
        'modulos/guias_remision'      => ['novedad_sri' => 'sri:novedad', 'borrador' => 'estado:borrador'],
        'modulos/car-wash'            => ['borrador' => 'estado:borrador'],
        // Aviso de la empresa que vende el sistema (nivel 3): empresas activas sin
        // controladora / sin suscripción. Mismos filtros del modal de Empresas del sistema.
        'config/empresas-sistema'     => [
            // Las que venden el sistema no necesitan controladora: se excluyen, igual que en el conteo.
            'sin_controladora' => 'estado:1 controladora:sin -suscripcion:vendedora',
            'sin_suscripcion'  => 'estado:1 suscripcion:sin',
        ],
    ];

    /**
     * POST /contadores/filtroAvisoAjax (ruta, aviso) — el navbar lo llama al hacer clic en
     * un aviso; deja el filtro en $_SESSION['aviso_filtro'][ruta] y el JS navega a la URL
     * limpia del módulo, cuyo index() lo toma con BaseModuloController::filtroDesdeAviso().
     * El permiso de ver lo valida el propio módulo al abrirse.
     */
    public function filtroAvisoAjax(): void
    {
        $this->requireAuth();

        $ruta  = trim((string) ($_POST['ruta'] ?? ''));
        $aviso = trim((string) ($_POST['aviso'] ?? ''));
        $filtro = self::FILTROS_AVISO[$ruta][$aviso] ?? null;
        if ($filtro === null) {
            $this->json(['ok' => false, 'error' => 'Aviso no válido.'], 422);
        }

        $_SESSION['aviso_filtro'][$ruta] = $filtro;
        $this->json(['ok' => true]);
    }

    /**
     * GET /contadores/tareasAlertasAjax → lista de la campana de tareas (vencidas y
     * por vencer). Se pide solo al abrir el desplegable, no en el sondeo del navbar.
     * Tareas es global por usuario: basta la sesión.
     */
    public function tareasAlertasAjax(): void
    {
        $this->requireAuth();

        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);

        // Solo LEE la sesión: se libera el lock igual que en navbarAjax.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        try {
            $this->json(['ok' => true] + $this->service->getTareasAlertas($idUsuario));
        } catch (\Throwable $e) {
            error_log('ContadoresController::tareasAlertasAjax ' . $e->getMessage());
            $this->json(['ok' => false, 'error' => 'No se pudo cargar la lista de tareas.']);
        }
    }

    /**
     * POST /contadores/marcarSubmoduloVistoAjax — el navbar la dispara cuando detecta
     * que la ruta actual coincide con un submódulo "nuevo" (ver navbar.php). Marca
     * la visita para que deje de aparecer en el aviso. Nunca debe romper la página.
     */
    public function marcarSubmoduloVistoAjax(): void
    {
        $this->requireAuth();

        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $ruta = trim((string) ($_POST['ruta'] ?? ''));

        try {
            if ($idUsuario > 0 && $idEmpresa > 0 && $ruta !== '') {
                $model = new PermisoSubmodulo();
                $idSubmodulo = $model->getIdSubmoduloPorRutaMvc($ruta);
                if ($idSubmodulo !== null) {
                    $model->marcarSubmoduloVisto($idUsuario, $idEmpresa, $idSubmodulo);
                }
            }
            $this->json(['ok' => true]);
        } catch (\Throwable $e) {
            $this->json(['ok' => true]);
        }
    }

    /**
     * POST /contadores/marcarTodosSubmodulosVistosAjax — botón "Marcar todos como
     * vistos" del modal de avisos. Nunca debe romper la página.
     */
    public function marcarTodosSubmodulosVistosAjax(): void
    {
        $this->requireAuth();

        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);

        try {
            if ($idUsuario > 0 && $idEmpresa > 0) {
                (new PermisoSubmodulo())->marcarTodosSubmodulosVistos($idUsuario, $idEmpresa);
            }
            $this->json(['ok' => true]);
        } catch (\Throwable $e) {
            $this->json(['ok' => true]);
        }
    }
}
