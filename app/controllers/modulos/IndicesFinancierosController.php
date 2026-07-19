<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\ActivoFijoRepository;
use App\repositories\modulos\CuentasPorCobrarRepository;
use App\repositories\modulos\CuentasPorPagarRepository;
use App\repositories\modulos\EstadosFinancierosRepository;
use App\repositories\modulos\IndicesFinancierosRepository;
use App\repositories\modulos\ReporteComprasRepository;
use App\repositories\modulos\ReporteInventarioRepository;
use App\repositories\modulos\ReporteVentasRepository;
use App\models\Empresa;
use App\Rules\modulos\IndicesFinancierosRules;
use App\Services\LogSistemaService;
use App\Services\modulos\IndicesFinancierosService;

class IndicesFinancierosController extends BaseModuloController
{
    private IndicesFinancierosService $service;

    protected function getRutaModulo(): string
    {
        return 'modulos/indices-financieros';
    }

    public function __construct()
    {
        parent::__construct();

        $repository = new IndicesFinancierosRepository();
        $this->service = new IndicesFinancierosService(
            $repository,
            new IndicesFinancierosRules($repository),
            new LogSistemaService(),
            new EstadosFinancierosRepository(),
            new CuentasPorCobrarRepository(),
            new CuentasPorPagarRepository(),
            new ReporteInventarioRepository(),
            new ReporteVentasRepository(),
            new ReporteComprasRepository(),
            new ActivoFijoRepository()
        );
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        // Siembra idempotente: no duplica si ya existen los códigos estándar.
        $this->service->inicializarIndicesEstandar($idEmpresa, $idUsuario);

        $fechaInicio = $_GET['fecha_inicio'] ?? date('Y-01-01');
        $fechaFin = $_GET['fecha_fin'] ?? date('Y-m-d');

        $this->viewWithLayout('layouts.main', 'modulos/indices_financieros/index', [
            'titulo' => 'Índices Financieros',
            'perm' => $this->getPermisos(),
            'rutaModulo' => $this->getRutaModulo(),
            'fechaInicio' => $fechaInicio,
            'fechaFin' => $fechaFin,
            'indices' => $this->service->calcularTodos($idEmpresa, $fechaInicio, $fechaFin),
            'cuentasSinClasificar' => $this->service->getCuentasSinClasificar($idEmpresa),
            'clasificacion' => $this->service->getClasificacion($idEmpresa),
            'grupos' => $this->service->getGrupos($idEmpresa),
            'catalogoIndices' => $this->service->getIndices($idEmpresa),
        ]);
    }

    /** Descarga el PDF con datos de la empresa (logo/RUC) y 3 firmas de responsabilidad. */
    public function pdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $fechaInicio = $_GET['fecha_inicio'] ?? date('Y-01-01');
        $fechaFin = $_GET['fecha_fin'] ?? date('Y-m-d');

        $indices = $this->service->calcularTodos($idEmpresa, $fechaInicio, $fechaFin);
        $empresa = $this->cargarEmpresaParaPdf($idEmpresa);
        $nombreUsuario = (string) ($_SESSION['nombre'] ?? '');

        $this->service->exportarPdf($indices, $empresa, $fechaInicio, $fechaFin, $nombreUsuario);
    }

    /** Mismo patrón que IngresosController::cargarEmpresaParaPdf(): nombre/RUC de empresas + logo del primer establecimiento. */
    private function cargarEmpresaParaPdf(int $idEmpresa): array
    {
        $empresaModel = new Empresa();
        $empresa = $empresaModel->getPorId($idEmpresa) ?? [];
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        if (!empty($establecimientos[0]['logo_ruta'])) {
            $empresa['logo_ruta'] = $establecimientos[0]['logo_ruta'];
        }
        return $empresa;
    }

    public function calcularAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $fechaInicio = $_GET['fecha_inicio'] ?? date('Y-01-01');
        $fechaFin = $_GET['fecha_fin'] ?? date('Y-m-d');

        echo json_encode(['ok' => true, 'data' => $this->service->calcularTodos($idEmpresa, $fechaInicio, $fechaFin)]);
        exit;
    }

    // ── Nivel 1: clasificación de cuentas ──────────────────────────────

    public function cuentasSinClasificarAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        echo json_encode(['ok' => true, 'data' => $this->service->getCuentasSinClasificar($idEmpresa)]);
        exit;
    }

    public function guardarClasificacionAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];
            $idCuenta = (int) ($_POST['id_cuenta'] ?? 0);
            $grupo = (string) ($_POST['grupo'] ?? '');

            $this->service->guardarClasificacion($idEmpresa, $idCuenta, $grupo, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Clasificación guardada correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    // ── Nivel 2: grupos personalizados ─────────────────────────────────

    public function buscarCuentasAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['q'] ?? '');
        echo json_encode(['ok' => true, 'data' => $this->service->getCuentasParaSelector($idEmpresa, $buscar)]);
        exit;
    }

    public function getGrupoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idGrupo = (int) ($_GET['id'] ?? 0);
        echo json_encode(['ok' => true, 'data' => $this->service->getCuentasDeGrupo($idGrupo)]);
        exit;
    }

    public function guardarGrupoAjax(): void
    {
        if (!empty($_POST['id'])) {
            $this->requireActualizar();
        } else {
            $this->requireCrear();
        }
        header('Content-Type: application/json');

        try {
            $data = $_POST;
            $data['id_empresa'] = (int) $_SESSION['id_empresa'];
            $data['id_usuario'] = (int) $_SESSION['id_usuario'];
            $data['id_cuentas'] = json_decode($_POST['id_cuentas'] ?? '[]', true) ?: [];

            $idExistente = !empty($data['id']) ? (int) $data['id'] : 0;
            $id = $idExistente > 0 ? $this->service->actualizarGrupo($idExistente, $data) : $this->service->crearGrupo($data);

            echo json_encode(['ok' => true, 'mensaje' => 'Grupo guardado correctamente.', 'id' => $id]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function eliminarGrupoAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        try {
            $id = (int) ($_POST['id'] ?? 0);
            $this->service->eliminarGrupo($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            echo json_encode(['ok' => true, 'mensaje' => 'Grupo eliminado correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    // ── Índices personalizados ─────────────────────────────────────────

    public function guardarIndiceAjax(): void
    {
        if (!empty($_POST['id'])) {
            $this->requireActualizar();
        } else {
            $this->requireCrear();
        }
        header('Content-Type: application/json');

        try {
            $data = $_POST;
            $data['id_empresa'] = (int) $_SESSION['id_empresa'];
            $data['id_usuario'] = (int) $_SESSION['id_usuario'];
            $data['tipo'] = 'personalizado';
            $data['formula'] = json_decode($_POST['formula'] ?? '', true);
            if (!is_array($data['formula'])) {
                throw new \Exception('La fórmula del índice no es válida.');
            }

            $idExistente = !empty($data['id']) ? (int) $data['id'] : 0;
            $id = $idExistente > 0 ? $this->service->actualizarIndice($idExistente, $data) : $this->service->crearIndice($data);

            echo json_encode(['ok' => true, 'mensaje' => 'Índice guardado correctamente.', 'id' => $id]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function eliminarIndiceAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        try {
            $id = (int) ($_POST['id'] ?? 0);
            $this->service->eliminarIndice($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            echo json_encode(['ok' => true, 'mensaje' => 'Índice eliminado correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function cambiarActivoIndiceAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $id = (int) ($_POST['id'] ?? 0);
            $activo = !empty($_POST['activo']) && $_POST['activo'] !== 'false';
            $this->service->cambiarActivo($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario'], $activo);
            echo json_encode(['ok' => true, 'mensaje' => 'Estado actualizado correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }
}
