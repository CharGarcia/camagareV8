<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Services\modulos\MayoresService;
use App\repositories\modulos\MayoresRepository;
use App\repositories\modulos\PlanCuentaRepository;
use App\repositories\modulos\ClienteRepository;
use App\repositories\modulos\ProveedorRepository;
use App\repositories\modulos\EmpleadoRepository;
use App\Services\ReportService;

class MayoresController extends BaseModuloController
{
    private MayoresService $service;

    protected function getRutaModulo(): string
    {
        return 'modulos/mayores';
    }

    public function __construct()
    {
        parent::__construct();
        $this->service = new MayoresService(new MayoresRepository(), new ReportService());
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $aniosDisponibles = $this->service->getAniosDisponibles($idEmpresa);
        if (empty($aniosDisponibles)) {
            $aniosDisponibles = [(int) date('Y')];
        }

        $this->viewWithLayout('layouts.main', 'modulos.mayores.index', [
            'titulo' => 'Mayores',
            'fechaInicio' => date('Y-01-01'),
            'fechaFin' => date('Y-12-31'),
            'aniosDisponibles' => $aniosDisponibles,
            'centrosCosto' => $this->service->getCentrosCostoActivos($idEmpresa),
            'proyectos' => $this->service->getProyectosActivos($idEmpresa),
            'rutaModulo' => $this->getRutaModulo(),
            'perm' => $this->getPermisos(),
            'fullWidth' => true,
        ]);
    }

    private function getFiltrosDesdeRequest(): array
    {
        return [
            'fecha_inicio' => $_GET['fecha_inicio'] ?? date('Y-01-01'),
            'fecha_fin' => $_GET['fecha_fin'] ?? date('Y-12-31'),
            'codigo_cuenta' => trim($_GET['codigo_cuenta'] ?? ''),
            'tipo_entidad' => trim($_GET['tipo_entidad'] ?? ''),
            'id_entidad' => !empty($_GET['id_entidad']) ? (int) $_GET['id_entidad'] : null,
            'id_centro_costo' => !empty($_GET['centro_costo']) ? (int) $_GET['centro_costo'] : null,
            'id_proyecto' => !empty($_GET['proyecto']) ? (int) $_GET['proyecto'] : null,
        ];
    }

    public function generarAjax(): void
    {
        $this->requireLeer();
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $datos = $this->service->generarMayor($idEmpresa, $this->getFiltrosDesdeRequest());
            $this->json(['success' => true, 'data' => $datos]);
        } catch (\Throwable $th) {
            $this->json(['success' => false, 'error' => $th->getMessage()]);
        }
    }

    public function getCuentasAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $q = trim($_GET['q'] ?? '');

        $repo = new PlanCuentaRepository();
        $data = $repo->searchCuentas($idEmpresa, $q);

        $this->json(['success' => true, 'data' => $data]);
    }

    public function getClientesAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['q'] ?? '');

        $repo = new ClienteRepository();
        $result = $repo->getListado($idEmpresa, $buscar, 1, 15, 'nombre', 'ASC', null, true);

        $data = array_map(function ($row) {
            return [
                'id' => $row['id'],
                'nombre' => $row['nombre'] ?? '',
                'identificacion' => $row['identificacion'] ?? '',
            ];
        }, $result['rows'] ?? []);

        $this->json(['success' => true, 'data' => $data]);
    }

    public function getProveedoresAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['q'] ?? '');

        $repo = new ProveedorRepository();
        $result = $repo->getListado($idEmpresa, $buscar, 1, 15, 'razon_social', 'ASC');

        $data = array_map(function ($row) {
            return [
                'id' => $row['id'],
                'nombre' => $row['razon_social'] ?? $row['nombre'] ?? '',
                'identificacion' => $row['identificacion'] ?? '',
            ];
        }, $result['rows'] ?? []);

        $this->json(['success' => true, 'data' => $data]);
    }

    public function getEmpleadosAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = mb_strtolower(trim($_GET['q'] ?? ''));

        $repo = new EmpleadoRepository();
        $empleados = $repo->getActivosParaSelect($idEmpresa);

        if ($buscar !== '') {
            $empleados = array_values(array_filter($empleados, function ($e) use ($buscar) {
                return str_contains(mb_strtolower($e['nombres_apellidos'] ?? ''), $buscar)
                    || str_contains(mb_strtolower($e['identificacion'] ?? ''), $buscar);
            }));
        }
        $empleados = array_slice($empleados, 0, 15);

        $data = array_map(function ($row) {
            return [
                'id' => $row['id'],
                'nombre' => $row['nombres_apellidos'] ?? '',
                'identificacion' => $row['identificacion'] ?? '',
            ];
        }, $empleados);

        $this->json(['success' => true, 'data' => $data]);
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros = $this->getFiltrosDesdeRequest();

        $empresaModel = new \App\models\Empresa();
        $empresa = $empresaModel->getPorId($idEmpresa);
        $empresaNombre = $empresa['nombre_comercial'] ?: $empresa['nombre'];
        $rangoFechas = $filtros['fecha_inicio'] . ' al ' . $filtros['fecha_fin'];

        $datos = $this->service->generarMayor($idEmpresa, $filtros);
        $this->service->exportarExcel($datos, $empresaNombre, $rangoFechas);
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros = $this->getFiltrosDesdeRequest();

        $empresaModel = new \App\models\Empresa();
        $empresa = $empresaModel->getPorId($idEmpresa);
        $empresaNombre = $empresa['nombre_comercial'] ?: $empresa['nombre'];
        $rangoFechas = $filtros['fecha_inicio'] . ' al ' . $filtros['fecha_fin'];

        $datos = $this->service->generarMayor($idEmpresa, $filtros);
        $this->service->exportarPdf($datos, $empresaNombre, $rangoFechas);
    }
}
