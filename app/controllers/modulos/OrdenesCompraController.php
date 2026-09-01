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
                          WHERE id_empresa = :id_empresa AND estado = 'borrador' AND eliminado = false AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa_amb)";
            $st = $db->prepare($sql);
            $st->execute([':id_empresa' => $idEmpresa, ':id_empresa_amb' => $idEmpresa]);
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
        $secRepo = new \App\repositories\SecuencialRepository();
        $puntosEmision = [];
        foreach ($empresaRepo->getPuntosEmision($idEmpresa) as $p) {
            $config = $secRepo->getConfigSecuencial((int) $p['id'], 'Órdenes de compra');
            if (empty($config['id'])) {
                continue;
            }
            $puntosEmision[] = $p;
        }

        // Series REALMENTE usadas en órdenes de compra guardadas, para el filtro
        // "Serie" del buscador — a diferencia de $puntosEmision (solo sirve para
        // elegir la serie de una orden NUEVA), esto incluye series de cualquier
        // establecimiento y aunque el punto ya no tenga secuencial configurado.
        $repoVista    = new OrdenCompraRepository();
        $seriesFiltro = $repoVista->getSeriesDistintas($idEmpresa);
        $tarifasIva   = $repoVista->getTarifasIva();

        $this->viewWithLayout('layouts.main', 'modulos.ordenes-compra.index', [
            'titulo'          => 'Órdenes de Compra',
            'perm'            => $perm,
            'rutaModulo'      => self::RUTA_MODULO,
            'seriesFiltro'    => $seriesFiltro,
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
            'tarifasIva'      => $tarifasIva,
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
                'enviado'   => '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">Enviado</span>',
                'aprobado'  => '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Aprobado</span>',
                'parcial'   => '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">Recibido parcial</span>',
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
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
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
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
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
            $sql = "SELECT id, identificacion, razon_social, email
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
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
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
            // Precio unitario sugerido = precio de COSTO del producto (esto es una compra al
            // proveedor, no una venta): usar precio_base aquí mostraría el precio de venta.
            // Se devuelve también la tarifa de IVA del producto para precargar el selector
            // de IVA de la línea al elegirlo en el detalle.
            $sql = "SELECT p.id, p.codigo, p.nombre AS descripcion, p.costo_producto AS precio_unitario,
                           ti.codigo AS codigo_iva, COALESCE(ti.porcentaje_iva, 0) AS porcentaje_iva
                    FROM productos p
                    LEFT JOIN tarifa_iva ti ON ti.id = p.tarifa_iva
                    WHERE p.id_empresa = :id_empresa AND p.eliminado = false AND p.status = 1
                      AND (p.nombre ILIKE :b OR p.codigo ILIKE :b)
                    ORDER BY p.nombre ASC
                    LIMIT 20";
            $st = $db->prepare($sql);
            $st->execute([':id_empresa' => $idEmpresa, ':b' => '%' . $buscar . '%']);
            $rows = $st->fetchAll(\PDO::FETCH_ASSOC);
            echo json_encode(['ok' => true, 'data' => $rows]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
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
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
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
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
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
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
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
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Anula una orden Enviada o Aprobada (no la elimina, solo cambia su estado). */
    public function anularAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $id        = (int) ($_POST['id'] ?? 0);

        try {
            if ($id <= 0) throw new \Exception('ID de orden no válido.');
            $this->service->anular($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Orden de compra anulada correctamente.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    /** Duplica una orden Enviada/Aprobada/Recibida Parcialmente en una nueva orden Borrador. */
    public function duplicarAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $idEmpresa      = (int) $_SESSION['id_empresa'];
        $idUsuario      = (int) $_SESSION['id_usuario'];
        $id             = (int) ($_POST['id'] ?? 0);
        $anularOriginal = !empty($_POST['anular_original']);

        try {
            if ($id <= 0) throw new \Exception('ID de orden no válido.');
            $idNueva = $this->service->duplicar($id, $idEmpresa, $idUsuario, $anularOriginal);
            echo json_encode(['ok' => true, 'mensaje' => 'Se creó una nueva orden en Borrador.', 'id' => $idNueva]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
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

    /** Carga cabecera + detalle de una orden puntual para PDF/Excel/Correo. */
    private function cargarOrdenParaDocumento(int $id, int $idEmpresa): ?array
    {
        $orden = $this->service->getById($id, $idEmpresa);
        if (!$orden) {
            return null;
        }
        $detalles = $orden['detalle'];
        unset($orden['detalle']);
        return ['cabecera' => $orden, 'detalles' => $detalles];
    }

    /** Datos de la empresa (con el logo del establecimiento de la orden) para el PDF. */
    private function cargarEmpresaParaPdf(int $idEmpresa, ?int $idEstablecimiento = null): array
    {
        $empresaModel = new \App\models\Empresa();
        $empresa      = $empresaModel->getPorId($idEmpresa) ?? [];

        $estRepo   = new EmpresaRepository();
        $estConfig = $idEstablecimiento ? $estRepo->getEstablecimientoConfig($idEstablecimiento) : null;
        if (!empty($estConfig['logo_ruta'])) {
            $empresa['logo_ruta'] = $estConfig['logo_ruta'];
        } else {
            $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
            if (!empty($establecimientos[0]['logo_ruta'])) {
                $empresa['logo_ruta'] = $establecimientos[0]['logo_ruta'];
            }
        }
        return $empresa;
    }

    /** Genera el PDF de una orden de compra puntual. */
    public function pdf(): void
    {
        $this->requireLeer();

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        if (!$id) { http_response_code(400); echo 'ID requerido'; exit; }

        try {
            $doc = $this->cargarOrdenParaDocumento($id, $idEmpresa);
            if (!$doc) { http_response_code(404); echo 'Orden de compra no encontrada'; exit; }

            $empresa = $this->cargarEmpresaParaPdf($idEmpresa, !empty($doc['cabecera']['id_establecimiento']) ? (int) $doc['cabecera']['id_establecimiento'] : null);

            (new \App\Services\modulos\OrdenCompraPdfService())->generar($doc['cabecera'], $doc['detalles'], $empresa, 'D');
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            http_response_code(500);
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
        exit;
    }

    /** Genera el Excel de una orden de compra puntual (mismas columnas que el PDF). */
    public function excel(): void
    {
        $this->requireLeer();

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        if (!$id) { http_response_code(400); echo 'ID requerido'; exit; }

        try {
            $doc = $this->cargarOrdenParaDocumento($id, $idEmpresa);
            if (!$doc) { http_response_code(404); echo 'Orden de compra no encontrada'; exit; }
            $cabecera = $doc['cabecera'];
            $detalles = $doc['detalles'];

            $empresa = $this->cargarEmpresaParaPdf($idEmpresa, !empty($cabecera['id_establecimiento']) ? (int) $cabecera['id_establecimiento'] : null);
            $numero  = (string) ($cabecera['numero_orden'] ?? '');

            require_once MVC_ROOT . '/vendor/autoload.php';

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Orden de Compra');

            $sheet->setCellValue('A1', strtoupper((string) ($empresa['nombre'] ?? '')));
            $sheet->mergeCells('A1:G1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

            $sheet->setCellValue('A2', 'ORDEN DE COMPRA N.° ' . ($numero !== '' ? $numero : '—'));
            $sheet->mergeCells('A2:G2');
            $sheet->getStyle('A2')->getFont()->setBold(true);

            $fecha = !empty($cabecera['fecha_orden']) ? date('d-m-Y', strtotime((string) $cabecera['fecha_orden'])) : '';
            $sheet->setCellValue('A3', 'Fecha orden: ' . $fecha);
            $sheet->setCellValue('A4', 'Proveedor: ' . (string) ($cabecera['proveedor_nombre'] ?? ''));
            $sheet->setCellValue('A5', 'Identificación: ' . (string) ($cabecera['proveedor_identificacion'] ?? ''));
            $sheet->setCellValue('A6', 'Estado: ' . ucfirst((string) ($cabecera['estado'] ?? '')));

            $headerRow = 8;
            // En el Excel la nota sí va como columna propia (a diferencia del PDF, donde se
            // imprime bajo la descripción): es una hoja de cálculo y conviene poder filtrarla.
            $headers = ['Código', 'Descripción', 'Cantidad', 'P. Unitario', 'IVA', 'Subtotal', 'Notas'];
            $col = 'A';
            foreach ($headers as $h) { $sheet->setCellValue($col . $headerRow, $h); $col++; }
            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '3C465A']],
            ];
            $sheet->getStyle('A' . $headerRow . ':G' . $headerRow)->applyFromArray($headerStyle);

            $row = $headerRow + 1;
            foreach ($detalles as $d) {
                $cantidad = (float) ($d['cantidad'] ?? 0);
                $precio   = (float) ($d['precio_unitario'] ?? 0);
                $subtotal = round($cantidad * $precio, 2);

                $sheet->setCellValueExplicit('A' . $row, (string) ($d['codigo'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('B' . $row, (string) ($d['descripcion'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue('C' . $row, $cantidad);
                $sheet->setCellValue('D' . $row, $precio);
                $sheet->setCellValue('E' . $row, ((float) ($d['porcentaje_iva'] ?? 0)) / 100);
                $sheet->setCellValue('F' . $row, $subtotal);
                $sheet->setCellValueExplicit('G' . $row, (string) ($d['notas'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $row++;
            }
            if ($row > $headerRow + 1) {
                $sheet->getStyle('C' . ($headerRow + 1) . ':D' . ($row - 1))->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle('E' . ($headerRow + 1) . ':E' . ($row - 1))->getNumberFormat()->setFormatCode('0%');
                $sheet->getStyle('F' . ($headerRow + 1) . ':F' . ($row - 1))->getNumberFormat()->setFormatCode('#,##0.00');
            }

            // Mismos totales (y mismo redondeo) que el PDF y el modal.
            $t     = \App\Services\modulos\OrdenCompraService::calcularTotales($detalles);
            $lineas = [['SUBTOTAL', $t['subtotal'], false]];
            foreach ($t['grupos'] as $g) {
                $lineas[] = ['Subtotal ' . $g['label'], $g['base'], false];
            }
            foreach ($t['grupos'] as $g) {
                if ($g['porcentaje'] > 0) {
                    $lineas[] = ['IVA ' . rtrim(rtrim(number_format($g['porcentaje'], 2, '.', ''), '0'), '.') . '%', $g['iva'], false];
                }
            }
            if ($t['total_iva'] <= 0) {
                $lineas[] = ['IVA', 0.0, false];
            }
            $lineas[] = ['TOTAL', $t['total'], true];

            foreach ($lineas as [$etiqueta, $valor, $destacada]) {
                $sheet->setCellValue('E' . $row, $etiqueta);
                $sheet->getStyle('E' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                $sheet->setCellValue('F' . $row, $valor);
                $sheet->getStyle('F' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                if ($destacada) {
                    $sheet->getStyle('E' . $row . ':F' . $row)->getFont()->setBold(true);
                }
                $row++;
            }

            $obs = trim((string) ($cabecera['observaciones'] ?? ''));
            if ($obs !== '') {
                $row++;
                $sheet->setCellValue('A' . $row, 'Observaciones: ' . $obs);
                $sheet->mergeCells('A' . $row . ':G' . $row);
            }

            foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G'] as $c) {
                $sheet->getColumnDimension($c)->setAutoSize(true);
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $nombre = 'OrdenCompra_' . ($numero !== '' ? $numero : 'comprobante') . '.xlsx';

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $nombre . '"');
            header('Cache-Control: max-age=0');
            $writer->save('php://output');
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            http_response_code(500); echo 'Error al generar Excel: ' . $e->getMessage();
        }
        exit;
    }

    /** Envía por correo el PDF de una orden de compra puntual. */
    public function enviarCorreoAjax(): void
    {
        ob_start();
        $this->requireActualizar();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        if (!$id) { if (ob_get_level() > 0) ob_end_clean(); echo json_encode(['ok' => false, 'mensaje' => 'ID requerido.']); exit; }

        try {
            $doc = $this->cargarOrdenParaDocumento($id, $idEmpresa);
            if (!$doc) { if (ob_get_level() > 0) ob_end_clean(); echo json_encode(['ok' => false, 'mensaje' => 'Orden de compra no encontrada.']); exit; }
            $cabecera = $doc['cabecera'];

            $empresa = $this->cargarEmpresaParaPdf($idEmpresa, !empty($cabecera['id_establecimiento']) ? (int) $cabecera['id_establecimiento'] : null);
            $numero  = (string) ($cabecera['numero_orden'] ?? '');

            $pdfString = (new \App\Services\modulos\OrdenCompraPdfService())->generar($cabecera, $doc['detalles'], $empresa, 'S');

            // Destinatarios: el que venga del formulario o, en su defecto, el del proveedor.
            $correosDestino = trim($_POST['correos'] ?? '');
            if ($correosDestino === '') {
                $correosDestino = (string) ($cabecera['proveedor_email'] ?? '');
            }
            if ($correosDestino === '') {
                if (ob_get_level() > 0) ob_end_clean();
                echo json_encode(['ok' => false, 'mensaje' => 'El proveedor no tiene correo registrado. Ingrese uno para enviar.']);
                exit;
            }

            // Botón de aprobación en el correo + transición de estado: solo la PRIMERA vez
            // que se envía (todavía en borrador). Un reenvío o una orden ya avanzada de
            // estado no lo necesita/incluye ni vuelve a cambiar el estado.
            $primerEnvio = ($cabecera['estado'] ?? '') === 'borrador';
            $urlAprobar  = '';
            if ($primerEnvio) {
                try {
                    $token   = $this->service->obtenerTokenAprobacion($id, $idEmpresa);
                    $host    = $_SERVER['HTTP_HOST'] ?? '';
                    $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $basePub = rtrim(defined('BASE_URL') ? BASE_URL : '', '/');
                    $urlAprobar = ($host !== '' ? $scheme . '://' . $host : '') . $basePub . '/aprobar-orden-compra/' . $token;
                } catch (\Throwable $e) {
                    $urlAprobar = '';
                }
            }

            $proveedorNombre = (string) ($cabecera['proveedor_nombre'] ?? 'Proveedor');
            $empresaNombre   = (string) ($empresa['nombre'] ?? '');
            $asunto = 'Orden de Compra ' . ($numero !== '' ? $numero : '') . ($empresaNombre !== '' ? ' — ' . $empresaNombre : '');
            $cuerpo = $this->_construirCorreoOrdenCompra($proveedorNombre, $numero, $empresaNombre, $urlAprobar);

            $emailSvc = new \App\Services\EnvioDocumentosSRIService();
            $enviado  = $emailSvc->enviarPdfSimple(
                $idEmpresa, $correosDestino, $proveedorNombre, $asunto, $cuerpo, $pdfString,
                'OrdenCompra_' . ($numero !== '' ? $numero : 'comprobante'), $empresaNombre
            );

            if ($enviado && $primerEnvio) {
                $this->service->marcarEnviado($id, $idEmpresa, $idUsuario);
            }

            if (ob_get_level() > 0) ob_end_clean();
            if ($enviado) {
                echo json_encode(['ok' => true, 'mensaje' => 'Correo enviado correctamente.']);
            } else {
                echo json_encode(['ok' => false, 'mensaje' => 'No se pudo enviar el correo. Verifica la configuración de correo o el destinatario.']);
            }
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            if (ob_get_level() > 0) ob_end_clean();
            echo json_encode(['ok' => false, 'mensaje' => 'Error al enviar correo: ' . $e->getMessage()]);
        }
        exit;
    }

    /** Aprobación manual desde el sistema (botón junto a "Enviar por correo"), sin pasar por el enlace del proveedor. */
    public function aprobarManualAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        if (!$id) { echo json_encode(['ok' => false, 'mensaje' => 'ID requerido.']); exit; }

        try {
            $nombreUsuario = trim((string) ($_SESSION['nombre'] ?? $_SESSION['nombre_usuario'] ?? 'Usuario interno'));
            $this->service->marcarAprobado($id, $idEmpresa, $idUsuario, 'Manual (' . $nombreUsuario . ')');
            echo json_encode(['ok' => true, 'mensaje' => 'Orden aprobada correctamente.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    /** Cuerpo HTML del correo de la orden de compra, con botón de aprobación si $urlAprobar viene informado. */
    private function _construirCorreoOrdenCompra(string $proveedor, string $numero, string $empresaNombre, string $urlAprobar): string
    {
        $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $botonHtml = '';
        if ($urlAprobar !== '') {
            $botonHtml =
                '<tr><td style="padding:6px 0 2px;">'
              . '<a href="' . $e($urlAprobar) . '" target="_blank" '
              . 'style="display:inline-block;background:#16a34a;color:#ffffff;text-decoration:none;'
              . 'font-weight:700;font-size:15px;padding:13px 26px;border-radius:8px;">✓ Aprobar esta orden de compra</a>'
              . '</td></tr>';
        }

        return
            '<div style="font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#1e293b;max-width:560px;">'
          . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" '
          . 'style="background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">'
          . '<tr><td style="background:#1f4e79;color:#ffffff;padding:18px 22px;font-size:18px;font-weight:700;">'
          . $e($empresaNombre) . '</td></tr>'
          . '<tr><td style="padding:22px;">'
          . '<p style="margin:0 0 12px;font-size:15px;">Estimad@ <strong>' . $e($proveedor) . '</strong>,</p>'
          . '<p style="margin:0 0 16px;font-size:14px;line-height:1.5;">Adjuntamos en PDF la orden de compra '
          . '<strong>N.º ' . $e($numero) . '</strong> para su revisión'
          . ($urlAprobar !== '' ? ', y le pedimos confirmarla con el botón:' : '.') . '</p>'
          . '<table role="presentation" cellpadding="0" cellspacing="0">' . $botonHtml . '</table>'
          . '<p style="margin:18px 0 0;font-size:13px;color:#475569;">Quedamos atentos a cualquier consulta.<br>'
          . 'Saludos cordiales,<br><strong>' . $e($empresaNombre) . '</strong></p>'
          . '</td></tr>'
          . '</table></div>';
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
