<?php
declare(strict_types=1);

namespace App\controllers\modulos;

use App\Services\PlantillasPdfService;

class PlantillasPdfController extends BaseModuloController
{
    private PlantillasPdfService $service;
    private const RUTA_MODULO = 'modulos/plantillas-pdf';

    public function __construct()
    {
        parent::__construct();
        $this->service = new PlantillasPdfService();
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    // Dispatcher principal — URL siempre /modulos/plantillas-pdf
    public function index(): void
    {
        $sub = trim($_GET['action'] ?? $_POST['action'] ?? '');

        match ($sub) {
            'disenador'           => $this->accionDisenador(),
            'store'               => $this->accionStore(),
            'update'              => $this->accionUpdate(),
            'delete'              => $this->accionDelete(),
            'activar'             => $this->accionActivar(),
            'desactivar'          => $this->accionDesactivar(),
            'guardar-diseno'      => $this->accionGuardarDiseno(),
            'campos-disponibles'  => $this->accionCamposDisponibles(),
            default               => $this->accionLista(),
        };
    }

    private function accionLista(): void
    {
        $this->requireLeer();

        $idEmpresa = (int)($_SESSION['id_empresa'] ?? 0);
        $buscar    = trim($_GET['b'] ?? '');
        $tipo      = trim($_GET['tipo'] ?? '');
        $page      = max(1, (int)($_GET['page'] ?? 1));
        $perPage   = 20;

        $result     = $this->service->listar($idEmpresa, $buscar, $tipo, $page, $perPage);
        $total      = $result['total'];
        $totalPages = (int)ceil($total / $perPage);

        $this->viewWithLayout('layouts.main', 'modulos.plantillas_pdf.index', [
            'titulo'     => 'Plantillas de Documentos',
            'rows'       => $result['rows'],
            'total'      => $total,
            'page'       => $page,
            'totalPages' => $totalPages,
            'perPage'    => $perPage,
            'from'       => $total > 0 ? (($page - 1) * $perPage) + 1 : 0,
            'to'         => $total > 0 ? min($page * $perPage, $total) : 0,
            'buscar'     => $buscar,
            'tipoFiltro' => $tipo,
            'tiposDoc'   => PlantillasPdfService::getTiposDocumento(),
            'permisos'   => $this->getPermisos(),
        ]);
    }

    private function accionDisenador(): void
    {
        $this->requireLeer();

        $id        = (int)($_GET['id'] ?? 0);
        $idEmpresa = (int)($_SESSION['id_empresa'] ?? 0);

        try {
            $plantilla = $this->service->getPorId($id, $idEmpresa);
            $campos    = $this->service->getCamposDisponibles($plantilla['tipo_documento']);

            $this->viewWithLayout('layouts.main', 'modulos.plantillas_pdf.disenador', [
                'titulo'    => 'Diseñador: ' . htmlspecialchars($plantilla['nombre']),
                'plantilla' => $plantilla,
                'campos'    => $campos,
                'tiposDoc'  => PlantillasPdfService::getTiposDocumento(),
                'fullWidth' => true,
            ]);
        } catch (\Throwable $e) {
            $_SESSION['error_msg'] = $e->getMessage();
            $this->redirect(BASE_URL . '/' . self::RUTA_MODULO);
        }
    }

    private function accionStore(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int)($_SESSION['id_empresa'] ?? 0);
            $idUsuario = (int)($_SESSION['id_usuario'] ?? 0);

            $data = [
                'id_empresa'     => $idEmpresa,
                'tipo_documento' => trim($_POST['tipo_documento'] ?? 'factura_venta'),
                'nombre'         => trim($_POST['nombre'] ?? ''),
                'descripcion'    => trim($_POST['descripcion'] ?? ''),
                'created_by'     => $idUsuario,
            ];

            $id = $this->service->crear($data);
            echo json_encode(['ok' => true, 'mensaje' => 'Plantilla creada.', 'id' => $id]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    private function accionUpdate(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $id        = (int)($_POST['id'] ?? 0);
            $idEmpresa = (int)($_SESSION['id_empresa'] ?? 0);
            $idUsuario = (int)($_SESSION['id_usuario'] ?? 0);

            $data = [
                'tipo_documento' => trim($_POST['tipo_documento'] ?? ''),
                'nombre'         => trim($_POST['nombre'] ?? ''),
                'descripcion'    => trim($_POST['descripcion'] ?? ''),
                'updated_by'     => $idUsuario,
            ];

            $this->service->actualizar($id, $idEmpresa, $data);
            echo json_encode(['ok' => true, 'mensaje' => 'Plantilla actualizada.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    private function accionDelete(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        try {
            $id        = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
            $idEmpresa = (int)($_SESSION['id_empresa'] ?? 0);
            $idUsuario = (int)($_SESSION['id_usuario'] ?? 0);

            $this->service->eliminar($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Plantilla eliminada.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    private function accionActivar(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $id        = (int)($_POST['id'] ?? 0);
            $idEmpresa = (int)($_SESSION['id_empresa'] ?? 0);

            $this->service->activar($id, $idEmpresa);
            echo json_encode(['ok' => true, 'mensaje' => 'Plantilla activada correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    private function accionDesactivar(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $id        = (int)($_POST['id'] ?? 0);
            $idEmpresa = (int)($_SESSION['id_empresa'] ?? 0);

            $this->service->desactivar($id, $idEmpresa);
            echo json_encode(['ok' => true, 'mensaje' => 'Plantilla desactivada.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    private function accionGuardarDiseno(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $id                = (int)($_POST['id'] ?? 0);
            $idEmpresa         = (int)($_SESSION['id_empresa'] ?? 0);
            $idUsuario         = (int)($_SESSION['id_usuario'] ?? 0);
            $configuracionJson = $_POST['configuracion'] ?? '';

            $this->service->guardarDiseno($id, $idEmpresa, $configuracionJson, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Diseño guardado correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    private function accionCamposDisponibles(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $tipo   = trim($_GET['tipo'] ?? 'factura_venta');
        $campos = $this->service->getCamposDisponibles($tipo);
        echo json_encode(['ok' => true, 'campos' => $campos]);
        exit;
    }
}
