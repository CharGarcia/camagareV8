<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\ConciliacionCobrosRepository;
use App\repositories\modulos\IngresoRepository;
use App\Rules\modulos\ConciliacionCobrosRules;
use App\Services\LogSistemaService;
use App\Services\modulos\ConciliacionCobrosService;
use App\Services\modulos\ConciliacionImportService;
use App\Services\modulos\ConciliacionMatchService;

/**
 * Conciliación de Cobros Bancarios: sube el estado de cuenta del banco
 * (Excel/CSV o PDF), sugiere qué factura/cliente corresponde a cada línea
 * contra las facturas pendientes de cobro, y genera los Ingresos en lote
 * tras la confirmación del usuario.
 */
class ConciliacionCobrosController extends BaseModuloController
{
    private ConciliacionCobrosService $service;

    protected function getRutaModulo(): string
    {
        return 'modulos/conciliacion-cobros';
    }

    public function __construct()
    {
        parent::__construct();
        $logService = new LogSistemaService();
        $this->service = new ConciliacionCobrosService(
            new ConciliacionCobrosRepository(),
            new ConciliacionCobrosRules(),
            new ConciliacionImportService(),
            new ConciliacionMatchService(new IngresoRepository()),
            new IngresoRepository(),
            $logService,
        );
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $this->viewWithLayout('layouts.main', 'modulos.conciliacion_cobros.index', [
            'titulo' => 'Conciliación de Cobros Bancarios',
            'perm' => $this->getPermisos(),
            'rutaModulo' => $this->getRutaModulo(),
            'cuentas' => $this->service->getCuentasBancarias($idEmpresa),
            'puntosEmision' => $this->service->getPuntosEmision($idEmpresa),
            'perfiles' => $this->service->getPerfiles($idEmpresa),
            'cargas' => $this->service->listarCargas($idEmpresa),
            'clientes' => $this->service->getClientesConSaldoPendiente($idEmpresa),
            'fullWidth' => true,
        ]);
    }

    // ── Perfiles de mapeo ────────────────────────────────────────────────────

    public function listarPerfilesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        echo json_encode(['ok' => true, 'data' => $this->service->getPerfiles($idEmpresa)]);
        exit;
    }

    /** Sube un archivo de muestra y devuelve las primeras filas/líneas crudas (sin mapear aún). */
    public function previsualizarArchivoAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        try {
            $tipoArchivo = strtoupper(trim($_POST['tipo_archivo'] ?? ''));
            $filaInicio = (int) ($_POST['fila_inicio'] ?? 0);
            $regexPrueba = trim($_POST['regex_prueba'] ?? '') ?: null;
            $tipoCreditoPrueba = trim($_POST['tipo_credito_prueba'] ?? '') ?: null;
            $resultado = $this->service->previsualizarArchivo($_FILES['archivo'] ?? [], $tipoArchivo, $filaInicio, $regexPrueba, $tipoCreditoPrueba);
            echo json_encode(['ok' => true, 'data' => $resultado]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function guardarPerfilAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $data = json_decode(file_get_contents('php://input') ?: '[]', true) ?: $_POST;

        try {
            $perfil = $this->service->guardarPerfil($idEmpresa, $idUsuario, $data);
            echo json_encode(['ok' => true, 'data' => $perfil]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ── Cargas ───────────────────────────────────────────────────────────────

    public function subirArchivoAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $carga = $this->service->crearCarga($idEmpresa, $idUsuario, $_POST, $_FILES['archivo'] ?? []);
            echo json_encode(['ok' => true, 'data' => $carga]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function listarCargasAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        echo json_encode(['ok' => true, 'data' => $this->service->listarCargas($idEmpresa)]);
        exit;
    }

    public function listarLineasAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idCarga = (int) ($_GET['id_carga'] ?? 0);

        try {
            $lineas = $this->service->listarLineas($idCarga, $idEmpresa);
            echo json_encode(['ok' => true, 'data' => $lineas]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Buscador manual de documentos pendientes de un cliente (mismo criterio que el typeahead de Ingresos). */
    public function buscarDocumentosPendientesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idCliente = (int) ($_GET['id_cliente'] ?? 0);

        if ($idCliente <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Debe indicar un cliente.']);
            exit;
        }

        $docs = $this->service->buscarDocumentosPendientes($idEmpresa, $idCliente);
        echo json_encode(['ok' => true, 'data' => $docs]);
        exit;
    }

    public function confirmarLineaAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $data = json_decode(file_get_contents('php://input') ?: '[]', true) ?: $_POST;
        $idLinea = (int) ($data['id_linea'] ?? 0);

        try {
            $linea = $this->service->confirmarLinea($idEmpresa, $idUsuario, $idLinea, $data);
            echo json_encode(['ok' => true, 'data' => $linea]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function desconfirmarLineaAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $data = json_decode(file_get_contents('php://input') ?: '[]', true) ?: $_POST;
        $idLinea = (int) ($data['id_linea'] ?? 0);

        try {
            $linea = $this->service->desconfirmarLinea($idEmpresa, $idLinea);
            echo json_encode(['ok' => true, 'data' => $linea]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function reactivarLineaAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $data = json_decode(file_get_contents('php://input') ?: '[]', true) ?: $_POST;
        $idLinea = (int) ($data['id_linea'] ?? 0);

        try {
            $linea = $this->service->reactivarLinea($idEmpresa, $idLinea);
            echo json_encode(['ok' => true, 'data' => $linea]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Reactiva una línea APLICADO cuyo Ingreso fue anulado/eliminado después, sin resubir el extracto. */
    public function reactivarLineaAplicadaAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $data = json_decode(file_get_contents('php://input') ?: '[]', true) ?: $_POST;
        $idLinea = (int) ($data['id_linea'] ?? 0);

        try {
            $linea = $this->service->reactivarLineaAplicada($idEmpresa, $idLinea);
            echo json_encode(['ok' => true, 'data' => $linea]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function ignorarLineaAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $data = json_decode(file_get_contents('php://input') ?: '[]', true) ?: $_POST;
        $idLinea = (int) ($data['id_linea'] ?? 0);

        try {
            $this->service->ignorarLinea($idEmpresa, $idLinea);
            echo json_encode(['ok' => true]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function generarIngresosAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $data = json_decode(file_get_contents('php://input') ?: '[]', true) ?: $_POST;
        $idCarga = (int) ($data['id_carga'] ?? 0);

        try {
            $resultados = $this->service->generarIngresos($idEmpresa, $idUsuario, $idCarga);
            echo json_encode(['ok' => true, 'data' => $resultados]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}
