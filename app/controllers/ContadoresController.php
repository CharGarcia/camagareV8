<?php

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\PermisoSubmodulo;
use App\Services\ContadoresNavbarService;
use App\Services\SesionActivaService;
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
