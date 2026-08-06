<?php

namespace App\Controllers\Modulos;

use App\Services\Modulos\PedidoService;
use App\Repositories\Modulos\PedidoRepository;
use App\Repositories\Modulos\ResponsableTrasladoRepository;
use App\Rules\Modulos\PedidoRules;
use App\models\Empresa;
use App\Traits\BloqueoEdicionTrait;
use Exception;

class PedidosController extends BaseModuloController {
    use BloqueoEdicionTrait;

    /** Tabla protegida por el bloqueo de edición (compartida con Consignaciones de Venta). */
    private const TABLA_BLOQUEO = 'pedidos_cabecera';

    private $service;
    private $repository;

    public function __construct() {
        parent::__construct();
        $this->repository = new PedidoRepository();
        $this->service = new PedidoService();
    }

    protected function getRutaModulo(): string {
        return 'modulos/pedidos';
    }

    public function index() {
        try {
            $db = \App\core\Database::getConnection();
            $db->exec("ALTER TABLE responsables_traslado ADD COLUMN IF NOT EXISTS email VARCHAR(150)");
            $db->exec("ALTER TABLE pedidos_detalle DROP COLUMN IF EXISTS id_empresa");
        } catch (\Throwable $e) {}

        $this->requireLeer();

        $perm = $this->getPermisos();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        
        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? $_GET['buscar'] ?? $_POST['buscar'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'numero_pedido');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'asc'));
        $perPage  = 20;

        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $result['rows'];
        $total = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $empresaModel = new Empresa();
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        $puntos = [];
        $empresaData = $this->repository->getEmpresaConfig($idEmpresa);

        if (!empty($establecimientos)) {
            $puntos = $empresaModel->getPuntosEmision((int) $establecimientos[0]['id']);
            try {
                $estRepo   = new \App\repositories\modulos\EmpresaRepository();
                $estConfig = $estRepo->getEstablecimientoConfig((int) $establecimientos[0]['id']);
                if ($estConfig) {
                    $empresaData = array_merge($empresaData ?? [], $estConfig);
                }
            } catch (\Throwable $e) {}
        }

        $responsableRepo = new ResponsableTrasladoRepository();
        $responsables = $responsableRepo->listarPorEmpresa($idEmpresa);

        $this->viewWithLayout('layouts.main', 'modulos/pedidos/index', [
            'titulo' => 'Pedidos de Ventas',
            'perm' => $perm,
            'rutaModulo' => $this->getRutaModulo(),
            'puntos' => $puntos,
            'responsables' => $responsables,
            'empresa' => $empresaData,
            'tarifasIva' => $this->repository->getTarifasIva(),
            'unidades' => $this->repository->getUnidadesMedida($idEmpresa),
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'perPage' => $perPage,
            'buscar' => $buscar,
            'ordenCol' => $ordenCol,
            'ordenDir' => $ordenDir,
            'vistaConfig' => $prefsVista,
            'fullWidth' => true
        ]);
    }

    /** data: URI del logo del primer establecimiento (para el membrete de los reportes). '' si no hay logo. */
    private function logoDataUri(int $idEmpresa): string
    {
        $empresaModel = new Empresa();
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        $ruta = $establecimientos[0]['logo_ruta'] ?? '';
        if (empty($ruta)) {
            return '';
        }
        $clean = ltrim((string) $ruta, '/');
        if (strpos($clean, 'sistema/public/') === 0) {
            $clean = substr($clean, strlen('sistema/public/'));
        } elseif (strpos($clean, 'sistema/') === 0) {
            $clean = substr($clean, strlen('sistema/'));
        }
        if (strpos($clean, 'public/') === 0) {
            $clean = substr($clean, strlen('public/'));
        }
        foreach ([MVC_ROOT . '/public/' . $clean, MVC_ROOT . '/' . $clean] as $cand) {
            if (is_file($cand)) {
                $tipo = strtolower(pathinfo($cand, PATHINFO_EXTENSION)) === 'png' ? 'png' : 'jpeg';
                return 'data:image/' . $tipo . ';base64,' . base64_encode((string) file_get_contents($cand));
            }
        }
        return '';
    }

    /** Exporta el listado (filtrado) de pedidos a PDF, con membrete de la empresa. */
    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? 'numero_pedido');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? 'asc'));

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $data = $this->service->getListado($idEmpresa, $buscar, 1, 0, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $data['rows'];

        try {
            $empresaModel  = new Empresa();
            $empresa       = $empresaModel->getPorId($idEmpresa);
            $nombreEmpresa = $empresa['nombre'] ?? 'REPORTE DE PEDIDOS';
            $logoUri       = $this->logoDataUri($idEmpresa);

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            ob_start();
?>
            <style>
                table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 7.5pt; table-layout: fixed; }
                th { background: #f2f2f2; border: 1px solid #ccc; padding: 4px; text-align: left; }
                td { border: 1px solid #ccc; padding: 4px; overflow: hidden; word-wrap: break-word; }
                .header { text-align: center; margin-bottom: 15px; width: 100%; }
                .header img { max-height: 18mm; margin-bottom: 4px; }
                h1 { margin: 0; font-size: 14pt; color: #333; }
                h2 { margin: 3px 0 0 0; color: #666; font-size: 10pt; text-transform: uppercase; }
            </style>
            <page backtop="10mm" backbottom="10mm" backleft="10mm" backright="10mm">
                <div class="header">
                    <?php if ($logoUri !== ''): ?>
                        <img src="<?= $logoUri ?>">
                    <?php endif; ?>
                    <h1><?= htmlspecialchars($nombreEmpresa) ?></h1>
                    <h2>Listado de Pedidos</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 12%">Nro. Pedido</th>
                            <th style="width: 10%">Fecha Emisión</th>
                            <th style="width: 10%">Fecha Entrega</th>
                            <th style="width: 24%">Cliente</th>
                            <th style="width: 16%">Resp. Entrega</th>
                            <th style="width: 18%">Observaciones</th>
                            <th style="width: 10%">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) ($r['numero_pedido'] ?? '')) ?></td>
                                <td><?= !empty($r['fecha_pedido']) ? date('d-m-Y', strtotime($r['fecha_pedido'])) : '-' ?></td>
                                <td><?= !empty($r['fecha_entrega']) ? date('d-m-Y', strtotime($r['fecha_entrega'])) : '-' ?></td>
                                <td><?= htmlspecialchars((string) ($r['cliente_nombre'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string) ($r['responsable_entrega'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string) ($r['observaciones'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string) ($r['estado'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </page>
<?php
            $content = ob_get_clean();

            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $html2pdf->writeHTML($content);
            $html2pdf->output('Pedidos_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            http_response_code(500); echo 'Error al generar PDF: ' . $e->getMessage();
            exit;
        }
    }

    /** Exporta el listado (filtrado) de pedidos a Excel. */
    public function exportExcel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? 'numero_pedido');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? 'asc'));

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $data = $this->service->getListado($idEmpresa, $buscar, 1, 0, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $data['rows'];

        try {
            $empresaModel  = new Empresa();
            $empresa       = $empresaModel->getPorId($idEmpresa);
            $nombreEmpresa = $empresa['nombre'] ?? '';

            $headers = ['Nro. Pedido', 'Fecha Emisión', 'Fecha Entrega', 'Cliente', 'Resp. Entrega', 'Observaciones', 'Estado'];

            $exportData = [];
            foreach ($rows as $r) {
                $exportData[] = [
                    (string) ($r['numero_pedido'] ?? ''),
                    !empty($r['fecha_pedido']) ? date('d-m-Y', strtotime($r['fecha_pedido'])) : '',
                    !empty($r['fecha_entrega']) ? date('d-m-Y', strtotime($r['fecha_entrega'])) : '',
                    (string) ($r['cliente_nombre'] ?? ''),
                    (string) ($r['responsable_entrega'] ?? ''),
                    (string) ($r['observaciones'] ?? ''),
                    (string) ($r['estado'] ?? ''),
                ];
            }

            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcel('Pedidos', $headers, $exportData, 'Listado de Pedidos', $nombreEmpresa);
            exit;
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            if (!headers_sent()) {
                http_response_code(500); echo 'Error al generar Excel: ' . $e->getMessage();
            }
            exit;
        }
    }

    /**
     * Datos del pedido listos para pre-cargar el modal "Nueva Factura" de Facturas
     * de Venta (botón "Facturar" del pedido). No crea nada: solo arma el payload
     * con exactamente el mismo shape de cliente/producto que ya usa Factura de
     * Venta (seleccionarCliente/seleccionarProductoEnFila), para que las reglas de
     * facturación libre / afecta inventario se validen igual que si el usuario
     * hubiera escrito la factura a mano.
     */
    public function datosParaFacturarAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        if (!$id) { echo json_encode(['ok' => false, 'mensaje' => 'ID requerido.']); exit; }

        try {
            if (!\App\Helpers\Permisos::puedeCrear('modulos/factura-venta')) {
                echo json_encode(['ok' => false, 'mensaje' => 'No tiene permiso para crear facturas de venta.']);
                exit;
            }

            $cabecera = $this->repository->obtenerPorId($id, $idEmpresa);
            if (!$cabecera) { echo json_encode(['ok' => false, 'mensaje' => 'Pedido no encontrado.']); exit; }

            $estado = $cabecera['estado'] ?? '';
            if ($estado === 'Procesado') {
                echo json_encode(['ok' => false, 'mensaje' => 'Este pedido ya está Procesado; no se puede generar otra factura desde él.']);
                exit;
            }
            if ($estado === 'Anulado') {
                echo json_encode(['ok' => false, 'mensaje' => 'Este pedido está Anulado; no se puede facturar.']);
                exit;
            }

            $detalles = $this->repository->obtenerDetalles($id, $idEmpresa);
            if (empty($detalles)) {
                echo json_encode(['ok' => false, 'mensaje' => 'El pedido no tiene ítems.']);
                exit;
            }

            $clienteRepo = new \App\repositories\modulos\ClienteRepository();
            $cliente = $clienteRepo->getPorId((int) $cabecera['id_cliente'], $idEmpresa);
            if (!$cliente) {
                echo json_encode(['ok' => false, 'mensaje' => 'El cliente del pedido ya no existe.']);
                exit;
            }

            $productoRepo = new \App\repositories\modulos\ProductoRepository();
            $lineas = [];
            $advertencias = [];
            foreach ($detalles as $d) {
                $idProducto = (int) ($d['id_producto'] ?? 0);
                $producto = $idProducto ? $productoRepo->getPorId($idProducto, $idEmpresa) : null;
                if (!$producto) {
                    $advertencias[] = 'El producto "' . ($d['producto_nombre'] ?? $idProducto) . '" ya no está disponible para facturar y se omitió.';
                    continue;
                }
                $producto['precios_lista'] = $productoRepo->getPrecios($idProducto, $idEmpresa);
                $producto['variantes']     = $productoRepo->getVariantes($idProducto, $idEmpresa);
                $lineas[] = ['producto' => $producto, 'cantidad' => (float) $d['cantidad']];
            }

            if (empty($lineas)) {
                echo json_encode(['ok' => false, 'mensaje' => 'Ningún producto del pedido está disponible para facturar.']);
                exit;
            }

            echo json_encode([
                'ok'            => true,
                'numero_pedido' => $cabecera['numero_pedido'] ?? '',
                'cliente'       => $cliente,
                'lineas'        => $lineas,
                'advertencias'  => $advertencias,
            ]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => 'Error al preparar la factura: ' . $e->getMessage()]);
        }
        exit;
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        $buscar    = trim($_GET['b'] ?? $_GET['q'] ?? $_POST['b'] ?? $_POST['q'] ?? '');
        $page      = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'numero_pedido');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'asc'));
        $perPage   = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $result['rows'];
        $total = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to   = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="9" class="text-center py-5 text-muted"><i class="bi bi-cart-x fs-3 d-block mb-2"></i>No se encontraron pedidos.</td></tr>';
        } else {
            foreach ($rows as $r) {
                if (!empty($r['fecha_pedido'])) {
                    $r['fecha_pedido'] = date('d-m-Y', strtotime($r['fecha_pedido']));
                } else {
                    $r['fecha_pedido'] = '';
                }
                
                $estadoVal = $r['estado'] ?? 'Pendiente';
                $badgeColor = match(strtoupper($estadoVal)) {
                    'PENDIENTE' => 'warning',
                    'FACTURADO', 'PROCESADO' => 'success',
                    'ANULADO'   => 'danger',
                    default     => 'secondary',
                };
                
                $fechaEntrega = !empty($r['fecha_entrega']) ? date('d-m-Y', strtotime($r['fecha_entrega'])) : '';
                $rangoHorario = '';
                if (!empty($r['hora_inicial_entrega']) || !empty($r['hora_maxima_entrega'])) {
                    $ini = !empty($r['hora_inicial_entrega']) ? date('H:i', strtotime($r['hora_inicial_entrega'])) : '--:--';
                    $max = !empty($r['hora_maxima_entrega']) ? date('H:i', strtotime($r['hora_maxima_entrega'])) : '--:--';
                    $rangoHorario = "$ini - $max";
                }

                echo '<tr class="pedido-row" role="button" tabindex="0" onclick="editarPedido(' . $r['id'] . ')">
                        <td class="ps-3" data-col="numero_pedido"><code class="text-secondary">' . htmlspecialchars($r['numero_pedido'] ?? '') . '</code></td>
                        <td data-col="fecha_pedido">' . htmlspecialchars($r['fecha_pedido']) . '</td>
                        <td data-col="fecha_entrega">' . htmlspecialchars($fechaEntrega) . '</td>
                        <td data-col="rango_horario">' . htmlspecialchars($rangoHorario) . '</td>
                        <td class="fw-medium text-truncate" data-col="cliente_nombre" style="max-width:250px">' . htmlspecialchars($r['cliente_nombre'] ?? '') . '</td>
                        <td class="text-truncate" style="max-width:200px" data-col="responsable_entrega">' . htmlspecialchars($r['responsable_entrega'] ?? '') . '</td>
                        <td class="text-truncate" style="max-width:200px" data-col="observaciones">' . htmlspecialchars($r['observaciones'] ?? '') . '</td>
                        <td class="text-truncate" style="max-width:200px" data-col="observaciones_internas">' . htmlspecialchars($r['observaciones_internas'] ?? '') . '</td>
                        <td class="text-center" data-col="estado">
                            <span class="badge bg-' . $badgeColor . ' bg-opacity-10 text-' . $badgeColor . ' border border-' . $badgeColor . ' border-opacity-25">
                                ' . htmlspecialchars($estadoVal) . '
                            </span>
                        </td>
                      </tr>';
            }
        }
        $rowsHtml = ob_get_clean();

        ob_start();
        $prevDisabled = ($page <= 1) ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        echo '<div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary border-end-0 rounded-end-0" ' . $prevDisabled . ' onclick="PED_cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary rounded-start-0" ' . $nextDisabled . ' onclick="PED_cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>
              </div>';
        $paginationHtml = ob_get_clean();

        echo json_encode([
            'ok'        => true,
            'rows'      => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'      => "$from-$to/$total",
            'total'     => $total,
            'pdf_url'   => BASE_URL . '/' . $this->getRutaModulo() . '/export-pdf?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir",
            'excel_url' => BASE_URL . '/' . $this->getRutaModulo() . '/export-excel?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir"
        ]);
        exit;
    }

    public function listarAjax() {
        $this->requireLeer();
        try {
            $buscar = $_POST['buscar'] ?? '';
            $filtros = ['buscar' => $buscar];
            $pedidos = $this->repository->listar($_SESSION['id_empresa'], $filtros);

            $this->json([
                'status' => true,
                'data' => $pedidos
            ]);
        } catch (Exception $e) {
            $this->json(['status' => false, 'message' => $e->getMessage()]);
        }
    }

    public function obtenerPedidoAjax() {
        $this->requireLeer();
        try {
            $id = $_POST['id'];
            $pedido = $this->repository->obtenerPorId($id, $_SESSION['id_empresa']);
            $detalles = $this->repository->obtenerDetalles($id, $_SESSION['id_empresa']);

            $this->json([
                'status' => true,
                'data' => [
                    'cabecera' => $pedido,
                    'detalles' => $detalles
                ]
            ]);
        } catch (Exception $e) {
            $this->json(['status' => false, 'message' => $e->getMessage()]);
        }
    }

    public function guardarAjax() {
        $this->requireCrear(); // O requireActualizar dependiendo de si es nuevo
        try {
            $datos = $_POST['cabecera'];
            $detalles = $_POST['detalles'] ?? [];

            PedidoRules::validar($datos, $detalles);

            $res = $this->service->guardarPedido($datos, $detalles, $_SESSION['id_empresa'], $_SESSION['id_usuario']);

            $this->json([
                'status' => true,
                'message' => 'Pedido guardado con éxito',
                'id' => $res
            ]);
        } catch (Exception $e) {
            $this->json(['status' => false, 'message' => $e->getMessage()]);
        }
    }

    public function eliminarAjax() {
        $this->requireEliminar();
        try {
            $id = $_POST['id'];
            $this->service->eliminarPedido($id, $_SESSION['id_empresa'], $_SESSION['id_usuario']);
            $this->json(['status' => true, 'message' => 'Pedido eliminado con éxito']);
        } catch (Exception $e) {
            $this->json(['status' => false, 'message' => $e->getMessage()]);
        }
    }

    /** Cabecera + detalles de un pedido, listos para PDF/Excel/Correo. Null si no existe. */
    private function cargarPedidoParaDocumento(int $id, int $idEmpresa): ?array
    {
        $cabecera = $this->repository->obtenerPorId($id, $idEmpresa);
        if (!$cabecera) {
            return null;
        }
        $detalles = $this->repository->obtenerDetalles($id, $idEmpresa);
        return ['cabecera' => $cabecera, 'detalles' => $detalles];
    }

    /** Datos de la empresa (con el logo del establecimiento del pedido) para el PDF. */
    private function cargarEmpresaParaPdf(int $idEmpresa, ?int $idEstablecimiento = null): array
    {
        $empresaModel = new Empresa();
        $empresa      = $empresaModel->getPorId($idEmpresa) ?? [];

        $estRepo   = new \App\repositories\modulos\EmpresaRepository();
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

    public function pdf(): void
    {
        $this->requireLeer();

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        if (!$id) { http_response_code(400); echo 'ID requerido'; exit; }

        try {
            $doc = $this->cargarPedidoParaDocumento($id, $idEmpresa);
            if (!$doc) { http_response_code(404); echo 'Pedido no encontrado'; exit; }

            $empresa = $this->cargarEmpresaParaPdf($idEmpresa, !empty($doc['cabecera']['id_establecimiento']) ? (int) $doc['cabecera']['id_establecimiento'] : null);

            (new \App\Services\modulos\PedidoPdfService())->generar($doc['cabecera'], $doc['detalles'], $empresa, 'D');
        } catch (\Throwable $e) {
            http_response_code(500);
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
        exit;
    }

    /** Genera el Excel del pedido (mismas columnas que el PDF). */
    public function excel(): void
    {
        $this->requireLeer();

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        if (!$id) { http_response_code(400); echo 'ID requerido'; exit; }

        try {
            $doc = $this->cargarPedidoParaDocumento($id, $idEmpresa);
            if (!$doc) { http_response_code(404); echo 'Pedido no encontrado'; exit; }
            $cabecera = $doc['cabecera'];
            $detalles = $doc['detalles'];

            $empresa = $this->cargarEmpresaParaPdf($idEmpresa, !empty($cabecera['id_establecimiento']) ? (int) $cabecera['id_establecimiento'] : null);
            $numero  = (string) ($cabecera['numero_pedido'] ?? '');

            require_once MVC_ROOT . '/vendor/autoload.php';

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Pedido');

            $sheet->setCellValue('A1', strtoupper((string) ($empresa['nombre'] ?? '')));
            $sheet->mergeCells('A1:C1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

            $sheet->setCellValue('A2', 'PEDIDO N.° ' . ($numero !== '' ? $numero : '—'));
            $sheet->mergeCells('A2:C2');
            $sheet->getStyle('A2')->getFont()->setBold(true);

            $fecha = !empty($cabecera['fecha_pedido']) ? date('d-m-Y', strtotime((string) $cabecera['fecha_pedido'])) : '';
            $sheet->setCellValue('A3', 'Fecha pedido: ' . $fecha);
            $sheet->setCellValue('A4', 'Cliente: ' . (string) ($cabecera['cliente_nombre'] ?? ''));
            $sheet->setCellValue('A5', 'Identificación: ' . (string) ($cabecera['cliente_identificacion'] ?? ''));
            $sheet->setCellValue('A6', 'Resp. entrega: ' . (string) ($cabecera['responsable_entrega'] ?? ''));
            $sheet->setCellValue('B6', 'Estado: ' . ucfirst((string) ($cabecera['estado'] ?? '')));

            $headerRow = 8;
            $headers = ['Código', 'Descripción', 'Cantidad'];
            $col = 'A';
            foreach ($headers as $h) { $sheet->setCellValue($col . $headerRow, $h); $col++; }
            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '3C465A']],
            ];
            $sheet->getStyle('A' . $headerRow . ':C' . $headerRow)->applyFromArray($headerStyle);

            $row = $headerRow + 1;
            foreach ($detalles as $d) {
                $sheet->setCellValueExplicit('A' . $row, (string) ($d['producto_codigo'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('B' . $row, (string) ($d['producto_nombre'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue('C' . $row, (float) ($d['cantidad'] ?? 0));
                $row++;
            }
            if ($row > $headerRow + 1) {
                $sheet->getStyle('C' . ($headerRow + 1) . ':C' . ($row - 1))->getNumberFormat()->setFormatCode('#,##0.00');
            }

            $obs = trim((string) ($cabecera['observaciones'] ?? ''));
            if ($obs !== '') {
                $row++;
                $sheet->setCellValue('A' . $row, 'Observaciones: ' . $obs);
                $sheet->mergeCells('A' . $row . ':C' . $row);
            }

            foreach (['A', 'B', 'C'] as $c) {
                $sheet->getColumnDimension($c)->setAutoSize(true);
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $nombre = 'Pedido_' . ($numero !== '' ? $numero : 'comprobante') . '.xlsx';

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

    /** Envía por correo el PDF del pedido. */
    public function enviarCorreoAjax(): void
    {
        ob_start();
        $this->requireLeer();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        if (!$id) { if (ob_get_level() > 0) ob_end_clean(); echo json_encode(['ok' => false, 'mensaje' => 'ID requerido.']); exit; }

        try {
            $doc = $this->cargarPedidoParaDocumento($id, $idEmpresa);
            if (!$doc) { if (ob_get_level() > 0) ob_end_clean(); echo json_encode(['ok' => false, 'mensaje' => 'Pedido no encontrado.']); exit; }
            $cabecera = $doc['cabecera'];

            $empresa = $this->cargarEmpresaParaPdf($idEmpresa, !empty($cabecera['id_establecimiento']) ? (int) $cabecera['id_establecimiento'] : null);
            $numero  = (string) ($cabecera['numero_pedido'] ?? '');

            $pdfString = (new \App\Services\modulos\PedidoPdfService())->generar($cabecera, $doc['detalles'], $empresa, 'S');

            // Destinatarios: el que venga del formulario o, en su defecto, el del cliente.
            $correosDestino = trim($_POST['correos'] ?? '');
            if ($correosDestino === '') {
                $correosDestino = (string) ($cabecera['cliente_email'] ?? '');
            }
            if ($correosDestino === '') {
                if (ob_get_level() > 0) ob_end_clean();
                echo json_encode(['ok' => false, 'mensaje' => 'El cliente no tiene correo registrado. Ingrese uno para enviar.']);
                exit;
            }

            $clienteNombre = (string) ($cabecera['cliente_nombre'] ?? 'Cliente');
            $empresaNombre = (string) ($empresa['nombre'] ?? '');
            $asunto = 'Pedido ' . ($numero !== '' ? $numero : '') . ($empresaNombre !== '' ? ' — ' . $empresaNombre : '');
            $cuerpo = "<div style='font-family:Arial,sans-serif;line-height:1.5;'>"
                . "<p>Estimad@ " . htmlspecialchars($clienteNombre) . ",</p>"
                . "<p>Adjunto encontrará el comprobante del pedido <strong>" . htmlspecialchars($numero) . "</strong>.</p>"
                . "<p>Saludos cordiales,<br>" . htmlspecialchars($empresaNombre) . "</p>"
                . "</div>";

            $emailSvc = new \App\Services\EnvioDocumentosSRIService();
            $enviado  = $emailSvc->enviarPdfSimple(
                $idEmpresa, $correosDestino, $clienteNombre, $asunto, $cuerpo, $pdfString,
                'Pedido_' . ($numero !== '' ? $numero : 'comprobante'), $empresaNombre
            );

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

    public function getSecuencialAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idPunto = (int) ($_GET['id_punto_emision'] ?? 0);
        $tipoDoc = 'Pedidos';

        $secuencialService = new \App\Services\SecuencialService();
        $res = $secuencialService->obtenerSiguienteSecuencial($idPunto, $tipoDoc);

        echo json_encode(array_merge(['status' => true], $res));
        exit;
    }

    public function buscarProductosAjax() {
        $this->requireLeer();
        header('Content-Type: application/json');
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $buscar = trim($_GET['term'] ?? $_GET['q'] ?? '');

            $db = \App\core\Database::getConnection();
            $sql = "SELECT p.id, p.codigo, p.nombre
                    FROM productos p
                    WHERE p.id_empresa = :id_empresa 
                      AND p.eliminado = false 
                      AND p.status = 1
                      AND (p.codigo ILIKE :q OR p.nombre ILIKE :q)
                    ORDER BY p.nombre ASC 
                    LIMIT 15";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':id_empresa' => $idEmpresa,
                ':q' => '%' . $buscar . '%'
            ]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            echo json_encode(['ok' => true, 'data' => $rows]);
        } catch (\Exception $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'data' => [], 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function buscarClientesAjax() {
        $this->requireLeer();
        try {
            $term = $_GET['term'] ?? '';
            $db = \App\core\Database::getConnection();
            $sql = "SELECT id, identificacion, nombre 
                    FROM clientes 
                    WHERE (nombre ILIKE :term OR identificacion ILIKE :term) 
                    AND id_empresa = :id_empresa 
                    AND status = '1' 
                    LIMIT 10";
            $stmt = $db->prepare($sql);
            $stmt->execute(['term' => "%$term%", 'id_empresa' => $_SESSION['id_empresa']]);
            echo json_encode($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            echo json_encode([]);
        }
    }

    public function guardarResponsableAjax() {
        $this->requireCrear();
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];
            $nombre = trim($_POST['nombre'] ?? '');
            $identificacion = trim($_POST['identificacion'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $email = trim($_POST['email'] ?? '');

            if (empty($nombre)) {
                throw new Exception('El nombre es obligatorio');
            }

            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('El formato del correo electrónico no es válido');
            }

            $db = \App\core\Database::getConnection();
            $db->beginTransaction();

            $sql = "INSERT INTO responsables_traslado (id_empresa, nombre, identificacion, telefono, email, estado, created_by, updated_by, created_at, updated_at, eliminado)
                    VALUES (:id_empresa, :nombre, :identificacion, :telefono, :email, 'activo', :id_usuario, :id_usuario, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, false)
                    RETURNING id, nombre, email";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':id_empresa' => $idEmpresa,
                ':nombre' => $nombre,
                ':identificacion' => $identificacion,
                ':telefono' => $telefono,
                ':email' => $email,
                ':id_usuario' => $idUsuario
            ]);

            $newRow = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Audit log
            try {
                $sqlLog = "INSERT INTO log_sistema (id_usuario, id_empresa, accion, tabla_afectada, datos_nuevos)
                           VALUES (:id_usuario, :id_empresa, 'CREAR', 'responsables_traslado', :datos_nuevos)";
                $stmtLog = $db->prepare($sqlLog);
                $stmtLog->execute([
                    ':id_usuario' => $idUsuario,
                    ':id_empresa' => $idEmpresa,
                    ':datos_nuevos' => json_encode($newRow)
                ]);
            } catch (\Throwable $e) {}

            $db->commit();

            $this->json([
                'status' => true,
                'message' => 'Responsable creado con éxito',
                'data' => $newRow
            ]);
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            $this->json(['status' => false, 'message' => $e->getMessage()]);
        }
    }

    /** Un usuario tomó/renovó el uso exclusivo de un pedido (edición o consumo desde Consignaciones). */
    public function bloquearAjax(): void {
        $this->requireLeer();
        $this->tomarBloqueoAjax(self::TABLA_BLOQUEO);
    }

    public function renovarBloqueoAjax(): void {
        $this->requireLeer();
        $this->renovarBloqueoAjaxTrait(self::TABLA_BLOQUEO);
    }

    public function liberarBloqueoAjax(): void {
        $this->requireLeer();
        $this->liberarBloqueoAjaxTrait(self::TABLA_BLOQUEO);
    }

    public function countPendientesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $db  = \App\core\Database::getConnection();
            $sql = "SELECT COUNT(*) FROM pedidos_cabecera
                    WHERE id_empresa = :id_empresa AND estado = 'Pendiente' AND eliminado = false AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";
            $st  = $db->prepare($sql);
            $st->execute([':id_empresa' => $idEmpresa]);
            $count = (int) $st->fetchColumn();
            echo json_encode(['ok' => true, 'count' => $count]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'count' => 0]);
        }
        exit;
    }
}
