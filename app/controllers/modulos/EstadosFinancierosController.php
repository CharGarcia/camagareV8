<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Services\modulos\EstadosFinancierosService;
use App\Services\modulos\EmpresaService;
use App\repositories\modulos\EstadosFinancierosRepository;
use App\Services\ReportService;
use App\core\Database;
use Exception;

class EstadosFinancierosController extends BaseModuloController
{
    private EstadosFinancierosService $service;
    private EmpresaService $empresaService;

    public function __construct()
    {
        parent::__construct();
        
        $this->service = new EstadosFinancierosService(
            new EstadosFinancierosRepository(),
            new ReportService()
        );
        $this->empresaService = new EmpresaService(
            new \App\repositories\modulos\EmpresaRepository(Database::getConnection())
        );
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        
        $aniosDisponibles = $this->service->getAniosDisponibles($idEmpresa);
        if (empty($aniosDisponibles)) {
            $aniosDisponibles = [(int)date('Y')];
        }

        $centrosCosto = $this->service->getCentrosCostoActivos($idEmpresa);
        $proyectos = $this->service->getProyectosActivos($idEmpresa);

        // Variables para la vista
        $fechaInicio = date('Y-01-01');
        $fechaFin = date('Y-12-31');
        
        $perm = $this->getPermisos();
        $idUsuario = (int) $_SESSION['id_usuario'];

        // Sincronizar asientos pendientes automáticamente
        $sincronizador = new \App\Services\modulos\SincronizadorAsientosService();
        $sincronizador->sincronizar($idEmpresa, $idUsuario);
        $warnings = $sincronizador->getWarnings();
        
        $this->viewWithLayout('layouts.main', 'modulos.estados_financieros.index', [
            'titulo' => 'Estados Financieros',
            'warnings' => $warnings,
            'fechaInicio' => $fechaInicio,
            'fechaFin' => $fechaFin,
            'aniosDisponibles' => $aniosDisponibles,
            'centrosCosto' => $centrosCosto,
            'proyectos' => $proyectos,
            'rutaModulo' => $this->getRutaModulo(),
            'perm' => $perm,
            'fullWidth' => true
        ]);
    }

    public function generarEstadoResultados(): void
    {
        try {
            $this->requireLeer();
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $fechaInicio = $_GET['fecha_inicio'] ?? date('Y-01-01');
            $fechaFin = $_GET['fecha_fin'] ?? date('Y-12-31');
            
            // TODO: Agregar filtros si existen
            $idCentroCosto = !empty($_GET['centro_costo']) ? (int)$_GET['centro_costo'] : null;
            $idProyecto = !empty($_GET['proyecto']) ? (int)$_GET['proyecto'] : null;
            $nivel = !empty($_GET['nivel']) ? (int)$_GET['nivel'] : 5;

            $datos = $this->service->getEstadoResultados($idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto, $nivel);

            $this->json(['success' => true, 'data' => $datos]);
        } catch (\Throwable $th) {
            $this->json(['success' => false, 'error' => $th->getMessage() . ' en ' . $th->getFile() . ':' . $th->getLine()]);
        }
    }

    public function generarEstadoSituacionFinanciera(): void
    {
        try {
            $this->requireLeer();
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $fechaInicio = $_GET['fecha_inicio'] ?? date('Y-01-01');
            $fechaFin = $_GET['fecha_fin'] ?? date('Y-12-31');
            
            // TODO: Agregar filtros si existen
            $idCentroCosto = !empty($_GET['centro_costo']) ? (int)$_GET['centro_costo'] : null;
            $idProyecto = !empty($_GET['proyecto']) ? (int)$_GET['proyecto'] : null;
            $nivel = !empty($_GET['nivel']) ? (int)$_GET['nivel'] : 5;

            $datos = $this->service->getEstadoSituacionFinanciera($idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto, $nivel);

            $this->json(['success' => true, 'data' => $datos]);
        } catch (\Throwable $th) {
            $this->json(['success' => false, 'error' => $th->getMessage() . ' en ' . $th->getFile() . ':' . $th->getLine()]);
        }
    }

    public function exportar(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $tipo = $_GET['tipo'] ?? 'resultados';
        $formato = $_GET['formato'] ?? 'excel';
        $fechaInicio = $_GET['fecha_inicio'] ?? date('Y-01-01');
        $fechaFin = $_GET['fecha_fin'] ?? date('Y-12-31');
        
        $idCentroCosto = !empty($_GET['centro_costo']) ? (int)$_GET['centro_costo'] : null;
        $idProyecto = !empty($_GET['proyecto']) ? (int)$_GET['proyecto'] : null;
        $nivel = !empty($_GET['nivel']) ? (int)$_GET['nivel'] : 5;

        $empresa = $this->empresaService->obtenerPorId($idEmpresa);
        $empresaNombre = $empresa['nombre_comercial'] ?: $empresa['nombre'];
        $rangoFechas = $fechaInicio . ' al ' . $fechaFin;

        if ($tipo === 'resultados') {
            $datos = $this->service->getEstadoResultados($idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto, $nivel);
        } else {
            $datos = $this->service->getEstadoSituacionFinanciera($idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto, $nivel);
        }

        if ($formato === 'pdf') {
            $this->service->exportarPdf($tipo, $datos, $empresaNombre, $rangoFechas);
        } else {
            $this->service->exportarExcel($tipo, $datos, $empresaNombre, $rangoFechas);
        }
    }

    protected function getRutaModulo(): string
    {
        return 'modulos/estados-financieros';
    }

    public function generarMayorAuxiliar(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $this->requireLeer();
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $codigoCuenta = $_GET['codigo_cuenta'] ?? '';
            $fechaInicio = $_GET['fecha_inicio'] ?? date('Y-01-01');
            $fechaFin = $_GET['fecha_fin'] ?? date('Y-12-31');
            
            $idCentroCosto = !empty($_GET['centro_costo']) ? (int) $_GET['centro_costo'] : null;
            $idProyecto = !empty($_GET['proyecto']) ? (int) $_GET['proyecto'] : null;

            if (empty($codigoCuenta)) {
                echo json_encode(['success' => false, 'error' => 'Código de cuenta requerido']);
                return;
            }

            $datos = $this->service->generarMayorAuxiliar(
                $idEmpresa,
                $codigoCuenta,
                $fechaInicio,
                $fechaFin,
                $idCentroCosto,
                $idProyecto
            );

            echo json_encode(['success' => true, 'data' => $datos]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
