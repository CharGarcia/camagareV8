<?php
/**
 * DashboardController - Tablero de indicadores (módulo operativo).
 * Ruta MVC: modulos/dashboard  (registrar submódulo en submodulos_menu y
 * permisos en /config/permisos-modulos).
 */

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Services\modulos\DashboardService;

class DashboardController extends BaseModuloController
{
    protected function getRutaModulo(): string
    {
        return 'modulos/dashboard';
    }

    public function index(): void
    {
        $this->requireLeer();

        $this->viewWithLayout('layouts.main', 'modulos/dashboard/index', [
            'titulo'     => 'Dashboard',
            'perm'       => $this->getPermisos(),
            'rutaModulo' => $this->getRutaModulo(),
            'base'       => BASE_URL,
        ]);
    }

    /** Datos del dashboard (JSON) según filtros de período. */
    public function dataAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        if ($idEmpresa <= 0) {
            echo json_encode(['ok' => false, 'error' => 'No hay empresa seleccionada.']);
            exit;
        }

        try {
            $emisor       = (new \App\repositories\modulos\EmpresaRepository())->getEmisorConfig($idEmpresa);
            $tipoAmbiente = (string) ($emisor['tipo_ambiente'] ?? '1');
            if ($tipoAmbiente === '') $tipoAmbiente = '1';

            $anio      = (int) ($_POST['anio']       ?? 0);
            $mes       = isset($_POST['mes']) ? (int) $_POST['mes'] : 0;
            $cantMeses = (int) ($_POST['cant_meses'] ?? 6);

            $service = new DashboardService();
            $data = $service->getDashboardData($idEmpresa, $tipoAmbiente, $anio, $mes, $cantMeses);
            $data['tipo_ambiente']       = $tipoAmbiente;
            $data['tipo_ambiente_label'] = $tipoAmbiente === '2' ? 'Producción' : 'Pruebas';
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}
