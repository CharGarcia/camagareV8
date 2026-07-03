<?php

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\Services\ContadoresNavbarService;
use App\Traits\PermisoModuloTrait;

/**
 * Endpoint unificado de contadores del navbar (badges de avisos).
 *
 * Reemplaza a los ~10 endpoints countBorradoresAjax/countPendientesAjax por
 * una sola llamada con caché. Incluye únicamente los contadores cuyo módulo el
 * usuario tiene permiso de 'ver' (Nivel 3 ve todo). Tareas es global por usuario.
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

    /** GET /contadores/navbarAjax → { ok:true, contadores:{...} } */
    public function navbarAjax(): void
    {
        $this->requireAuth();

        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);

        // Liberar el lock de sesión cuanto antes: este endpoint solo LEE la sesión
        // (nunca escribe) y se consulta con alta frecuencia (polling del navbar).
        // Los valores de $_SESSION siguen siendo legibles tras cerrar la escritura.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        try {
            $contadores = $this->service->getContadores(
                $idEmpresa,
                $idUsuario,
                fn (string $ruta): bool => $this->permisosModuloPorRuta($ruta)['ver'] === true
            );
            $this->json(['ok' => true, 'contadores' => $contadores]);
        } catch (\Throwable $e) {
            error_log('ContadoresController::navbarAjax ' . $e->getMessage());
            $this->json(['ok' => false, 'contadores' => (object) []]);
        }
    }
}
