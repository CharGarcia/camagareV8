<?php
/**
 * Controlador Home - Página principal
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\Empresa;
use App\Services\DashboardService;

class HomeController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        if (empty($_SESSION['id_empresa'])) {
            $model = new Empresa();
            try {
                $empresas = $model->getEmpresasAsignadas((int) $_SESSION['id_usuario']);
            } catch (\Throwable $e) {
                $empresas = [];
            }
            if (!empty($empresas)) {
                $primera = $empresas[0];
                $_SESSION['id_empresa'] = $primera['id_empresa'];
                $_SESSION['ruc_empresa'] = $primera['ruc'] ?? '';
            }
        }

        $nivel = (int) ($_SESSION['nivel'] ?? 1);
        $sinEmpresa = $nivel === 3 && empty($_SESSION['id_empresa']);

        $this->viewWithLayout('layouts.main', 'home.index', [
            'titulo' => 'Inicio',
            'sinEmpresaSuperAdmin' => $sinEmpresa,
            // El dashboard (datos financieros de toda la empresa) no se muestra a Nivel 1.
            'mostrarDashboard'     => $nivel !== 1,
        ]);
    }

    public function dashboardDataAjax(): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        // El dashboard no está disponible para usuarios Nivel 1.
        if ((int) ($_SESSION['nivel'] ?? 1) === 1) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'No autorizado.']);
            exit;
        }

        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        if ($idEmpresa <= 0) {
            echo json_encode(['ok' => false, 'error' => 'No hay empresa seleccionada.']);
            exit;
        }

        try {
            $emisor       = (new \App\repositories\modulos\EmpresaRepository())->getEmisorConfig($idEmpresa);
            $tipoAmbiente = (string)($emisor['tipo_ambiente'] ?? '1');
            if ($tipoAmbiente === '') $tipoAmbiente = '1';

            // Parámetros de filtro desde POST
            $anio      = (int) ($_POST['anio']       ?? 0);
            $mes       = isset($_POST['mes']) ? (int)$_POST['mes'] : 0;
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

    /**
     * Módulos en proceso de migración a MVC.
     * Se muestra cuando el menú apunta a un módulo legacy que aún no tiene versión MVC.
     */
    public function moduloEnConstruccion(): void
    {
        $this->requireAuth();
        $this->viewWithLayout('layouts.main', 'home.moduloEnConstruccion', [
            'titulo' => 'Módulo en desarrollo',
        ]);
    }
}
