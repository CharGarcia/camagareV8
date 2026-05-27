<?php
declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\FacturaExpressQrRepository;
use App\Rules\modulos\FacturaExpressQrRules;
use App\Services\LogSistemaService;
use App\Services\modulos\FacturaExpressQrService;

class FacturaExpressConfigController extends BaseModuloController
{
    private FacturaExpressQrService $service;
    private const RUTA_MODULO = 'modulos/factura-express-config';

    public function __construct()
    {
        parent::__construct();
        $this->service = new FacturaExpressQrService(
            new FacturaExpressQrRepository(),
            new FacturaExpressQrRules(),
            new LogSistemaService()
        );
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    // ── Vista principal: listado de plantillas ────────────────────────────────
    public function index(): void
    {
        $this->requireLeer();

        $perm       = $this->getPermisos();
        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        $buscar   = trim($_GET['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? 'created_at');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? 'DESC'));
        $perPage  = 20;

        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir);
        $totalPages = $perPage > 0 ? (int) ceil($result['total'] / $perPage) : 1;
        $from       = $result['total'] > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to         = $result['total'] > 0 ? min($page * $perPage, $result['total']) : 0;

        $empresaRepo   = new \App\repositories\modulos\EmpresaRepository();
        $establecimientos = $empresaRepo->getEstablecimientos($idEmpresa);
        $puntosEmision    = $empresaRepo->getPuntosEmision($idEmpresa);

        $db = \App\core\Database::getConnection();
        $stFp = $db->prepare("SELECT codigo, nombre FROM formas_pago_sri WHERE status = 1 ORDER BY nombre ASC");
        $stFp->execute();
        $formasPago = $stFp->fetchAll(\PDO::FETCH_ASSOC);

        $this->viewWithLayout('layouts.main', 'modulos.factura-express-config.index', [
            'titulo'           => 'Configuración QR',
            'perm'             => $perm,
            'rutaModulo'       => self::RUTA_MODULO,
            'rows'             => $result['rows'],
            'total'            => $result['total'],
            'page'             => $page,
            'totalPages'       => $totalPages,
            'perPage'          => $perPage,
            'from'             => $from,
            'to'               => $to,
            'buscar'           => $buscar,
            'ordenCol'         => $ordenCol,
            'ordenDir'         => $ordenDir,
            'vistaConfig'      => $prefsVista,
            'establecimientos' => $establecimientos,
            'puntosEmision'    => $puntosEmision,
            'formasPago'       => $formasPago,
            'fullWidth'        => true,
        ]);
    }

    // ── AJAX: búsqueda/paginación de plantillas ───────────────────────────────
    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? '');
        $page      = max(1, (int) ($_GET['page'] ?? 1));
        $perPage   = 20;
        $ordenCol  = trim($_GET['sort'] ?? 'created_at');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? 'DESC'));

        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir);
        $total      = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        $from       = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to         = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        foreach ($result['rows'] as $r) {
            $activo     = (bool) $r['activo'];
            $clsActivo  = $activo ? 'success' : 'secondary';
            $lblActivo  = $activo ? 'Activo' : 'Inactivo';
            $pendientes = (int) ($r['solicitudes_pendientes'] ?? 0);
            $urlPublica = rtrim(BASE_URL, '/') . '/factura-express/' . htmlspecialchars($r['token']);

            echo '<tr class="fexqr-row" role="button" tabindex="0" data-row=\'' . htmlspecialchars(json_encode($r), ENT_QUOTES) . '\' onclick="fexqrAbrirEditar(this)">';
            echo '<td class="ps-3 fw-medium" data-col="nombre">' . htmlspecialchars($r['nombre']) . '</td>';
            echo '<td data-col="descripcion"><small class="text-muted">' . htmlspecialchars($r['descripcion'] ?? '—') . '</small></td>';
            echo '<td class="text-center" data-col="total_items">' . (int)($r['total_items'] ?? 0) . '</td>';
            echo '<td class="text-center" data-col="solicitudes">';
            if ($pendientes > 0) {
                echo '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 fw-bold">'
                   . $pendientes . ' pendiente' . ($pendientes > 1 ? 's' : '') . '</span>';
            } else {
                echo '<span class="text-muted small">' . (int)($r['total_solicitudes'] ?? 0) . '</span>';
            }
            echo '</td>';
            echo '<td class="text-center" data-col="activo"><span class="badge bg-' . $clsActivo . ' bg-opacity-10 text-' . $clsActivo . ' border border-' . $clsActivo . ' border-opacity-25">' . $lblActivo . '</span></td>';
            echo '<td class="text-center pe-3 text-nowrap">';
            echo '<button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 me-1" onclick="event.stopPropagation();fexqrMostrarQr(\'' . $r['token'] . '\',\'' . htmlspecialchars($r['nombre'], ENT_QUOTES) . '\',\'' . htmlspecialchars($r['descripcion'] ?? '', ENT_QUOTES) . '\')" title="Ver QR"><i class="bi bi-qr-code"></i></button>';
            echo '<a href="' . $urlPublica . '" target="_blank" class="btn btn-outline-primary btn-sm py-0 px-2" onclick="event.stopPropagation()" title="Ver formulario público"><i class="bi bi-box-arrow-up-right"></i></a>';
            echo '</td>';
            echo '</tr>';
        }
        $rowsHtml = ob_get_clean();

        ob_start();
        echo '<button type="button" class="btn btn-outline-secondary btn-sm" onclick="fexqrCambiarPagina(' . ($page - 1) . ')" ' . ($page <= 1 ? 'disabled' : '') . '><i class="bi bi-chevron-left"></i></button>';
        echo '<button type="button" class="btn btn-outline-secondary btn-sm" onclick="fexqrCambiarPagina(' . ($page + 1) . ')" ' . ($page >= $totalPages ? 'disabled' : '') . '><i class="bi bi-chevron-right"></i></button>';
        $paginHtml = ob_get_clean();

        $urlExportBase = rtrim(BASE_URL, '/') . '/modulos/factura-express-config';
        $pdfUrl   = $urlExportBase . '/export-pdf?b='   . urlencode($buscar) . '&sort=' . urlencode($ordenCol) . '&dir=' . urlencode($ordenDir);
        $excelUrl = $urlExportBase . '/export-excel?b=' . urlencode($buscar) . '&sort=' . urlencode($ordenCol) . '&dir=' . urlencode($ordenDir);

        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginHtml,
            'info'       => "$from-$to/$total",
            'pdf_url'    => $pdfUrl,
            'excel_url'  => $excelUrl,
        ]);
    }

    // ── AJAX: obtener datos completos de una plantilla ────────────────────────
    public function getPlantillaAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $id        = (int) ($_GET['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $data      = $this->service->getPlantillaConItems($id, $idEmpresa);
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\Exception $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    // ── AJAX: obtener URL + QR de una plantilla ───────────────────────────────
    public function getUrlQrAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $id        = (int) ($_GET['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $url       = $this->service->getUrlQr($id, $idEmpresa);
            $qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?data=' . urlencode($url) . '&size=280x280&margin=10';
            echo json_encode(['ok' => true, 'url' => $url, 'qr_image_url' => $qrImageUrl]);
        } catch (\Exception $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    // ── CRUD plantillas ───────────────────────────────────────────────────────
    public function store(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        try {
            $data               = $_POST;
            $data['id_empresa'] = (int) $_SESSION['id_empresa'];
            $data['id_usuario'] = (int) $_SESSION['id_usuario'];
            $data['activo']              = !empty($data['activo']);
            $data['requiere_aprobacion'] = !empty($data['requiere_aprobacion']);
            $data['items']               = json_decode($data['items_json'] ?? '[]', true) ?: [];

            $id = $this->service->crearPlantilla($data);
            echo json_encode(['ok' => true, 'id' => $id, 'mensaje' => 'Plantilla creada correctamente.']);
        } catch (\InvalidArgumentException $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        } catch (\Exception $e) {
            echo json_encode(['ok' => false, 'mensaje' => 'Error al crear: ' . $e->getMessage()]);
        }
    }

    public function update(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $id                 = (int) ($_POST['id'] ?? 0);
            $idEmpresa          = (int) $_SESSION['id_empresa'];
            $data               = $_POST;
            $data['id_empresa'] = $idEmpresa;
            $data['id_usuario'] = (int) $_SESSION['id_usuario'];
            $data['activo']              = !empty($data['activo']);
            $data['requiere_aprobacion'] = !empty($data['requiere_aprobacion']);
            $data['items']               = json_decode($data['items_json'] ?? '[]', true) ?: [];

            $this->service->actualizarPlantilla($id, $idEmpresa, $data);
            echo json_encode(['ok' => true, 'mensaje' => 'Plantilla actualizada correctamente.']);
        } catch (\InvalidArgumentException $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        } catch (\Exception $e) {
            echo json_encode(['ok' => false, 'mensaje' => 'Error al actualizar: ' . $e->getMessage()]);
        }
    }

    public function delete(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        try {
            $id        = (int) ($_POST['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            $this->service->eliminarPlantilla($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Plantilla eliminada correctamente.']);
        } catch (\Exception $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    // ── AJAX: buscar productos/servicios activos del catálogo ─────────────────
    public function buscarProductosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $q         = trim($_GET['q'] ?? '');

            $db = \App\core\Database::getConnection();
            $st = $db->prepare("
                SELECT p.id,
                       p.codigo,
                       p.nombre,
                       p.precio_base                                              AS precio_unitario,
                       COALESCE(ti.porcentaje_iva, 0)                            AS porcentaje_iva
                FROM productos p
                LEFT JOIN tarifa_iva ti ON ti.id = p.tarifa_iva
                WHERE p.id_empresa = :emp
                  AND p.eliminado  = false
                  AND p.status     = 1
                  AND (p.nombre ILIKE :q OR p.codigo ILIKE :q)
                ORDER BY p.nombre ASC
                LIMIT 15
            ");
            $st->execute([':emp' => $idEmpresa, ':q' => "%{$q}%"]);
            $rows = $st->fetchAll(\PDO::FETCH_ASSOC);

            echo json_encode(['ok' => true, 'data' => $rows]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'data' => [], 'error' => $e->getMessage()]);
        }
    }
}
