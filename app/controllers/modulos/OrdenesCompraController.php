<?php
declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\OrdenCompraRepository;
use App\repositories\modulos\EmpresaRepository;
use App\Rules\modulos\OrdenCompraRules;
use App\Services\modulos\OrdenCompraService;
use App\Services\LogSistemaService;
use App\core\Database;

class OrdenesCompraController extends BaseModuloController
{
    private OrdenCompraService $service;
    private const RUTA_MODULO = 'modulos/ordenes-compra';

    public function __construct()
    {
        parent::__construct();
        $repository = new OrdenCompraRepository();
        $rules      = new OrdenCompraRules();
        $logService = new LogSistemaService();
        $this->service = new OrdenCompraService($repository, $rules, $logService);
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    // ── AJAX: contador de órdenes de compra en borrador (para badge del navbar) ─
    public function countBorradoresAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $db        = Database::getConnection();
            $sql       = "SELECT COUNT(*) FROM ordenes_compra
                          WHERE id_empresa = :id_empresa AND estado = 'borrador' AND eliminado = false AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";
            $st = $db->prepare($sql);
            $st->execute([':id_empresa' => $idEmpresa]);
            echo json_encode(['ok' => true, 'count' => (int) $st->fetchColumn()]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'count' => 0]);
        }
        exit;
    }

    public function index(): void
    {
        $this->requireLeer();

        $perm      = $this->getPermisos();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'created_at');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'desc'));
        $perPage  = 20;

        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows       = $result['rows'];
        $total      = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        foreach ($rows as &$r) {
            if (!empty($r['created_at'])) $r['created_at'] = date('d-m-Y H:i:s', strtotime($r['created_at']));
            if (!empty($r['updated_at'])) $r['updated_at'] = date('d-m-Y H:i:s', strtotime($r['updated_at']));
        }
        unset($r);

        // Cargar establecimientos y puntos de emisión para el modal
        $empresaRepo      = new EmpresaRepository();
        $establecimientos = $empresaRepo->getEstablecimientos($idEmpresa);
        $puntosEmision    = $empresaRepo->getPuntosEmision($idEmpresa);

        $this->viewWithLayout('layouts.main', 'modulos.ordenes-compra.index', [
            'titulo'          => 'Órdenes de Compra',
            'perm'            => $perm,
            'rutaModulo'      => self::RUTA_MODULO,
            'rows'            => $rows,
            'total'           => $total,
            'page'            => $page,
            'totalPages'      => $totalPages,
            'perPage'         => $perPage,
            'buscar'          => $buscar,
            'ordenCol'        => $ordenCol,
            'ordenDir'        => $ordenDir,
            'vistaConfig'     => $prefsVista,
            'establecimientos'=> $establecimientos,
            'puntosEmision'   => $puntosEmision,
            'fullWidth'       => true,
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: application/json');

        try {
            $idEmpresa  = (int) $_SESSION['id_empresa'];
            $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
            $buscar     = trim($_GET['b'] ?? $_GET['q'] ?? $_POST['b'] ?? '');
            $page       = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
            $ordenCol   = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'created_at');
            $ordenDir   = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'desc'));
            $perPage    = 20;

            $perm = $this->getPermisos();
            $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

            $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
            $rows       = $result['rows'];
            $total      = $result['total'];
            $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

            $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
            $to   = $total > 0 ? min($page * $perPage, $total) : 0;

            $estadoBadgeMap = [
                'borrador'  => '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Borrador</span>',
                'aprobado'  => '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Aprobado</span>',
                'anulado'   => '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Anulado</span>',
                'recibido'  => '<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">Recibido</span>',
            ];

            ob_start();
            if (empty($rows)) {
                echo '<tr><td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-cart3 fs-3 d-block mb-2"></i>No se encontraron órdenes de compra.</td></tr>';
            } else {
                foreach ($rows as $r) {
                    if (!empty($r['created_at'])) $r['created_at'] = date('d-m-Y H:i:s', strtotime($r['created_at']));
                    if (!empty($r['updated_at'])) $r['updated_at'] = date('d-m-Y H:i:s', strtotime($r['updated_at']));
                    // Fechas para display (formateadas) — el data-row mantiene formato ISO para el formulario
                    $fechaOrdenDisplay     = !empty($r['fecha_orden'])     ? date('d-m-Y', strtotime($r['fecha_orden']))     : '—';
                    $fechaRecepcionDisplay = !empty($r['fecha_recepcion']) ? date('d-m-Y', strtotime($r['fecha_recepcion'])) : '—';

                    $rowData     = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                    $estadoBadge = $estadoBadgeMap[$r['estado'] ?? 'borrador'] ?? '<span class="badge bg-secondary">-</span>';

                    echo '<tr class="oc-row" role="button" tabindex="0" data-row=\'' . $rowData . '\' onclick="ocAbrirEditar(this)">
                            <td class="ps-3" data-col="numero_orden"><code class="text-secondary">' . htmlspecialchars($r['numero_orden'] ?? '') . '</code></td>
                            <td data-col="fecha_orden">' . htmlspecialchars($fechaOrdenDisplay) . '</td>
                            <td class="fw-medium text-truncate" data-col="proveedor_nombre" style="max-width:250px">' . htmlspecialchars($r['proveedor_nombre'] ?? '—') . '</td>
                            <td data-col="proveedor_identificacion"><small>' . htmlspecialchars($r['proveedor_identificacion'] ?? '—') . '</small></td>
                            <td data-col="fecha_recepcion">' . htmlspecialchars($fechaRecepcionDisplay) . '</td>
                            <td class="text-truncate" data-col="observaciones" style="max-width:200px"><small>' . htmlspecialchars($r['observaciones'] ?? '—') . '</small></td>
                            <td class="text-center pe-3" data-col="estado">' . $estadoBadge . '</td>
                          </tr>';
                }
            }
            $rowsHtml = ob_get_clean();

            ob_start();
            $prevDisabled = ($page <= 1) ? 'disabled' : '';
            $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
            echo '<div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary border-end-0 rounded-end-0" ' . $prevDisabled . ' onclick="ocCambiarPagina(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>
                    <button type="button" class="btn btn-outline-secondary rounded-start-0" ' . $nextDisabled . ' onclick="ocCambiarPagina(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>
                  </div>';
            $paginationHtml = ob_get_clean();

            echo json_encode([
                'ok'         => true,
                'rows'       => $rowsHtml,
                'pagination' => $paginationHtml,
                'info'       => "$from-$to/$total",
                'total'      => $total,
                'pdf_url'    => BASE_URL . '/' . self::RUTA_MODULO . '/export-pdf?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir",
                'excel_url'  => BASE_URL . '/' . self::RUTA_MODULO . '/export-excel?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir",
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function getSiguienteSecuencial(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idPuntoEmision = (int) ($_GET['id_punto_emision'] ?? 0);
        if ($idPuntoEmision <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Punto de emisión no válido.']);
            exit;
        }
        try {
            $result = $this->service->getSiguienteSecuencial($idPuntoEmision);
            echo json_encode(['ok' => true, 'secuencial' => $result['formateado']]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function getProveedoresAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['q'] ?? '');
        try {
            $db  = Database::getConnection();
            $sql = "SELECT id, identificacion, razon_social
                    FROM proveedores
                    WHERE id_empresa = :id_empresa AND eliminado = false
                      AND (razon_social ILIKE :b OR identificacion ILIKE :b)
                    ORDER BY razon_social ASC
                    LIMIT 20";
            $st = $db->prepare($sql);
            $st->execute([':id_empresa' => $idEmpresa, ':b' => '%' . $buscar . '%']);
            $rows = $st->fetchAll(\PDO::FETCH_ASSOC);
            echo json_encode(['ok' => true, 'data' => $rows]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function getProductosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['q'] ?? '');
        try {
            $db  = Database::getConnection();
            $sql = "SELECT id, codigo, nombre AS descripcion, precio_base AS precio_unitario
                    FROM productos
                    WHERE id_empresa = :id_empresa AND eliminado = false
                      AND (nombre ILIKE :b OR codigo ILIKE :b)
                    ORDER BY nombre ASC
                    LIMIT 20";
            $st = $db->prepare($sql);
            $st->execute([':id_empresa' => $idEmpresa, ':b' => '%' . $buscar . '%']);
            $rows = $st->fetchAll(\PDO::FETCH_ASSOC);
            echo json_encode(['ok' => true, 'data' => $rows]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function getDetalle(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        try {
            if ($id <= 0) throw new \Exception('ID no válido.');
            $orden = $this->service->getById($id, $idEmpresa);
            if (!$orden) throw new \Exception('Orden no encontrada.');
            echo json_encode(['ok' => true, 'detalle' => $orden['detalle']]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function store(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        $data  = $this->_recogerCabecera($idEmpresa, $idUsuario);
        $items = $this->_recogerItems();

        try {
            $id = $this->service->crear($data, $items);
            echo json_encode(['ok' => true, 'msg' => 'Orden de compra creada correctamente.', 'id' => $id]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function update(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        $data         = $this->_recogerCabecera($idEmpresa, $idUsuario);
        $data['updated_by'] = $idUsuario;
        $items        = $this->_recogerItems();

        try {
            if ($id <= 0) throw new \Exception('ID de orden no válido.');
            $this->service->actualizar($id, $idEmpresa, $data, $items);
            echo json_encode(['ok' => true, 'msg' => 'Orden de compra actualizada correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function delete(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $id        = (int) ($_POST['id_eliminar'] ?? 0);

        try {
            if ($id <= 0) throw new \Exception('ID de orden no válido.');
            $this->service->eliminar($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Orden de compra eliminada correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? '');
        $ordenCol  = trim($_GET['sort'] ?? 'created_at');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? 'desc'));

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $data = $this->service->getListado($idEmpresa, $buscar, 1, 0, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $data['rows'];

        try {
            $empresaModel  = new \App\models\Empresa();
            $empresa       = $empresaModel->getPorId($idEmpresa);
            $nombreEmpresa = $empresa['nombre'] ?? 'REPORTE ÓRDENES DE COMPRA';

            if (file_exists(MVC_ROOT . '/vendor/autoload.php')) {
                require_once MVC_ROOT . '/vendor/autoload.php';
            }

            ob_start();
?>
<style>
table { width:100%; border-collapse:collapse; font-family:Arial,sans-serif; font-size:8pt; }
th { background:#f2f2f2; border:1px solid #ccc; padding:4px; text-align:left; }
td { border:1px solid #ccc; padding:4px; overflow:hidden; word-wrap:break-word; }
.header { text-align:center; margin-bottom:15px; }
h1 { margin:0; font-size:14pt; color:#333; }
h2 { margin:3px 0 0; color:#666; font-size:10pt; text-transform:uppercase; }
</style>
<page backtop="10mm" backbottom="10mm" backleft="10mm" backright="10mm">
    <div class="header">
        <h1><?= htmlspecialchars($nombreEmpresa) ?></h1>
        <h2>Órdenes de Compra</h2>
    </div>
    <table>
        <thead>
            <tr>
                <th style="width:15%">N° Orden</th>
                <th style="width:12%">Fecha</th>
                <th style="width:35%">Proveedor</th>
                <th style="width:15%">Identificación</th>
                <th style="width:12%">Recepción</th>
                <th style="width:11%">Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['numero_orden'] ?? '') ?></td>
                <td><?= !empty($r['fecha_orden']) ? date('d-m-Y', strtotime($r['fecha_orden'])) : '—' ?></td>
                <td><?= htmlspecialchars($r['proveedor_nombre'] ?? '') ?></td>
                <td><?= htmlspecialchars($r['proveedor_identificacion'] ?? '') ?></td>
                <td><?= !empty($r['fecha_recepcion']) ? date('d-m-Y', strtotime($r['fecha_recepcion'])) : '—' ?></td>
                <td><?= htmlspecialchars($r['estado'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</page>
<?php
            $content = ob_get_clean();
            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $html2pdf->writeHTML($content);
            $html2pdf->output('OrdeneCompra_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo "Error al generar PDF: " . $e->getMessage();
            exit;
        }
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? '');
        $ordenCol  = trim($_GET['sort'] ?? 'created_at');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? 'desc'));

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $data = $this->service->getListado($idEmpresa, $buscar, 1, 0, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $data['rows'];

        try {
            $empresaModel  = new \App\models\Empresa();
            $empresa       = $empresaModel->getPorId($idEmpresa);
            $nombreEmpresa = $empresa['nombre'] ?? '';

            if (file_exists(MVC_ROOT . '/vendor/autoload.php')) {
                require_once MVC_ROOT . '/vendor/autoload.php';
            }

            $headers    = ['N° Orden', 'Fecha Orden', 'Proveedor', 'Identificación', 'Fecha Recepción', 'Observaciones', 'Estado'];
            $exportData = [];
            foreach ($rows as $r) {
                $exportData[] = [
                    (string)($r['numero_orden'] ?? ''),
                    !empty($r['fecha_orden']) ? date('d-m-Y', strtotime($r['fecha_orden'])) : '',
                    (string)($r['proveedor_nombre'] ?? ''),
                    (string)($r['proveedor_identificacion'] ?? ''),
                    !empty($r['fecha_recepcion']) ? date('d-m-Y', strtotime($r['fecha_recepcion'])) : '',
                    (string)($r['observaciones'] ?? ''),
                    (string)($r['estado'] ?? ''),
                ];
            }

            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcel('Órdenes de Compra', $headers, $exportData, 'Listado Órdenes de Compra', $nombreEmpresa);
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo "Error al generar Excel: " . $e->getMessage();
            exit;
        }
    }

    private function _recogerCabecera(int $idEmpresa, int $idUsuario): array
    {
        return [
            'id_empresa'         => $idEmpresa,
            'id_proveedor'       => (int) ($_POST['id_proveedor'] ?? 0),
            'id_establecimiento' => (int) ($_POST['id_establecimiento'] ?? 0),
            'id_punto_emision'   => (int) ($_POST['id_punto_emision'] ?? 0),
            'fecha_orden'        => trim($_POST['fecha_orden'] ?? ''),
            'fecha_recepcion'    => trim($_POST['fecha_recepcion'] ?? '') ?: null,
            'observaciones'      => trim($_POST['observaciones'] ?? '') ?: null,
            'estado'             => trim($_POST['estado'] ?? 'borrador'),
            'created_by'         => $idUsuario,
            'updated_by'         => $idUsuario,
        ];
    }

    private function _recogerItems(): array
    {
        $raw = $_POST['items'] ?? '[]';
        if (is_string($raw)) {
            $items = json_decode($raw, true) ?? [];
        } else {
            $items = (array) $raw;
        }
        return $items;
    }
}
