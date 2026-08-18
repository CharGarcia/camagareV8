<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\core\Controller;
use App\Services\modulos\NotaCreditoService;
use App\repositories\modulos\NotaCreditoRepository;
use App\repositories\modulos\FacturaVentaRepository;
use App\models\Empresa;
use App\repositories\modulos\BodegaRepository;

class NotasCreditoController extends BaseModuloController
{
    private $service;
    private $repository;

    protected function getRutaModulo(): string
    {
        return 'modulos/notas_credito';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new NotaCreditoRepository();
        $rules            = new \App\Rules\modulos\NotaCreditoRules();
        $logService       = new \App\Services\LogSistemaService();
        $this->service    = new NotaCreditoService($this->repository, $rules, $logService);
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_emision');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage  = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $result = $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $totalPages = (int) ceil($result['total'] / $perPage);

        $empresaModel = new Empresa();
        $empresaData  = $empresaModel->getPorId($idEmpresa);
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        
        $secRepo = new \App\repositories\SecuencialRepository();
        $puntos = [];
        foreach ($establecimientos as $est) {
            $pts = $empresaModel->getPuntosEmision((int) $est['id']);
            foreach ($pts as $p) {
                $config = $secRepo->getConfigSecuencial((int) $p['id'], 'Nota de crédito');
                if (empty($config['id'])) {
                    continue;
                }
                $p['cod_establecimiento'] = $est['codigo'];
                $puntos[] = $p;
            }
        }

        $bodegaRepo = new BodegaRepository();
        $bodegas = $bodegaRepo->getBodegasPermitidas((int)$_SESSION['id_usuario'], $idEmpresa, (int)$_SESSION['nivel']);

        $total = $result['total'];
        $this->viewWithLayout('layouts.main', 'modulos/notas_credito/index', [
            'titulo'      => 'Notas de Crédito',
            'perm'        => $perm,
            'rows'        => $result['rows'],
            'total'       => $total,
            'page'        => $page,
            'totalPages'  => $totalPages,
            'perPage'     => $perPage,
            'from'        => $total > 0 ? (($page - 1) * $perPage) + 1 : 0,
            'to'          => $total > 0 ? min($page * $perPage, $total) : 0,
            'buscar'      => $buscar,
            'ordenCol'    => $ordenCol,
            'ordenDir'    => $ordenDir,
            'vistaConfig' => $prefsVista,
            'base'        => BASE_URL,
            'rutaModulo'  => $this->getRutaModulo(),
            'empresa'     => $empresaData,
            'establecimientos' => $establecimientos,
            'puntos'      => $puntos,
            'bodegas'     => $bodegas,
            'fullWidth'   => true,
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        $buscar     = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page       = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol   = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_emision');
        $ordenDir   = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage    = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $result     = $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows       = $result['rows'];
        $total      = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        $from       = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to         = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="12" class="text-center py-5 text-muted"><i class="bi bi-file-earmark-minus fs-3 d-block mb-2"></i>No se encontraron notas de crédito.</td></tr>';
        } else {
            foreach ($rows as $r) {
                $rowData      = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                $numero       = htmlspecialchars(($r['establecimiento'] ?? '') . '-' . ($r['punto_emision'] ?? '') . '-' . ($r['secuencial'] ?? ''));
                $fecha        = !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '�€”';
                $estado       = $r['estado'] ?? 'borrador';
                $estadoClass  = match ($estado) {
                    'autorizado' => 'bg-success bg-opacity-10 text-success border-success',
                    'anulado'    => 'bg-danger bg-opacity-10 text-danger border-danger',
                    'borrador'   => 'bg-secondary bg-opacity-10 text-secondary border-secondary',
                    default      => 'bg-primary bg-opacity-10 text-primary border-primary',
                };
                $estadoBadge  = '<span class="badge ' . $estadoClass . ' border border-opacity-25">' . ucfirst($estado) . '</span>';

                $estadoCorreo = $r['estado_correo'] ?? 'pendiente';
                $correoClass  = $estadoCorreo === 'enviado'
                    ? 'bg-success bg-opacity-10 text-success border-success'
                    : 'bg-warning bg-opacity-10 text-warning border-warning';
                $correoBadge  = '<span class="badge ' . $correoClass . ' border border-opacity-25">' . ucfirst($estadoCorreo) . '</span>';

                echo '<tr class="nc-row" role="button" tabindex="0" data-row=\'' . $rowData . '\' onclick="window.NC_abrirModalNC(this)">
                        <td class="ps-3" data-col="numero"><code>' . $numero . '</code></td>
                        <td data-col="fecha_emision">' . $fecha . '</td>
                        <td class="fw-medium text-truncate" data-col="cliente_nombre" style="max-width:200px">' . htmlspecialchars($r['cliente_nombre'] ?? '�€”') . '</td>
                        <td data-col="cliente_ruc"><small class="text-muted">' . htmlspecialchars($r['cliente_ruc'] ?? '�€”') . '</small></td>
                        <td data-col="num_doc_modificado"><small class="text-muted">' . htmlspecialchars($r['num_doc_modificado'] ?? '�€”') . '</small></td>
                        <td class="text-end" data-col="total_sin_impuestos">$' . number_format((float)($r['total_sin_impuestos'] ?? 0), 2) . '</td>
                        <td class="text-end text-danger" data-col="total_descuento">$' . number_format((float)($r['total_descuento'] ?? 0), 2) . '</td>
                        <td class="text-end fw-bold" data-col="importe_total">$' . number_format((float)($r['importe_total'] ?? 0), 2) . '</td>
                        <td class="text-truncate" data-col="motivo" style="max-width:180px">' . htmlspecialchars($r['motivo'] ?? '') . '</td>
                        <td data-col="usuario_nombre">' . htmlspecialchars($r['usuario_nombre'] ?? '�€”') . '</td>
                        <td class="text-center" data-col="estado_correo">' . $correoBadge . '</td>
                        <td class="text-center pe-3" data-col="estado">' . $estadoBadge . '</td>
                      </tr>';
            }
        }
        $rowsHtml = ob_get_clean();

        ob_start();
        $prevDisabled = ($page <= 1)           ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        echo '<button type="button" class="btn btn-outline-secondary" ' . $prevDisabled . ' onclick="window.NC_cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>
              <button type="button" class="btn btn-outline-secondary" ' . $nextDisabled . ' onclick="window.NC_cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>';
        $paginationHtml = ob_get_clean();

        $urlBase = BASE_URL . '/' . $this->getRutaModulo();
        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'       => "$from-$to/$total",
            'total'      => $total,
            'pdf_url'    => $urlBase . '/export-pdf?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir",
            'excel_url'  => $urlBase . '/export-excel?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir",
        ]);
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EXPORTAR EXCEL / PDF (LISTADO)
    // ─────────────────────────────────────────────────────────────────────────

    private function getListadoParaExport(): array
    {
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? 'fecha_emision');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? 'DESC'));

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        return $this->repository->getListado($idEmpresa, $buscar, 1, 0, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        $rows = $this->getListadoParaExport()['rows'];

        $idEmpresa     = (int) $_SESSION['id_empresa'];
        $empresaModel  = new Empresa();
        $empresa       = $empresaModel->getPorId($idEmpresa);
        $nombreEmpresa = $empresa['nombre'] ?? '';

        $headers    = ['Número', 'Fecha Emisión', 'Cliente', 'Identificación', 'Doc. Modificado', 'Subtotal', 'Descuento', 'Total', 'Motivo', 'Estado'];
        $exportData = [];
        foreach ($rows as $r) {
            $numero = ($r['establecimiento'] ?? '') . '-' . ($r['punto_emision'] ?? '') . '-' . ($r['secuencial'] ?? '');
            $exportData[] = [
                (string)$numero,
                !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '',
                (string)($r['cliente_nombre'] ?? ''),
                (string)($r['cliente_ruc'] ?? ''),
                (string)($r['num_doc_modificado'] ?? ''),
                (float)($r['total_sin_impuestos'] ?? 0),
                (float)($r['total_descuento'] ?? 0),
                (float)($r['importe_total'] ?? 0),
                (string)($r['motivo'] ?? ''),
                ucfirst((string)($r['estado'] ?? '')),
            ];
        }

        try {
            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }
            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcel('Notas de Crédito', $headers, $exportData, 'Listado Notas de Crédito', $nombreEmpresa);
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo 'Error al generar Excel: ' . $e->getMessage();
            exit;
        }
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $rows = $this->getListadoParaExport()['rows'];

        $idEmpresa     = (int) $_SESSION['id_empresa'];
        $empresaModel  = new Empresa();
        $empresa       = $empresaModel->getPorId($idEmpresa);
        $nombreEmpresa = $empresa['nombre'] ?? 'REPORTE DE NOTAS DE CRÉDITO';

        try {
            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            ob_start();
?>
            <style>
                table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 7pt; table-layout: fixed; }
                th { background: #f2f2f2; border: 1px solid #ccc; padding: 3px; text-align: left; }
                td { border: 1px solid #ccc; padding: 3px; overflow: hidden; word-wrap: break-word; }
                .text-end { text-align: right; }
                .header { text-align: center; margin-bottom: 15px; width: 100%; }
                h1 { margin: 0; font-size: 14pt; color: #333; }
                h2 { margin: 3px 0 0 0; color: #666; font-size: 10pt; text-transform: uppercase; }
            </style>
            <page backtop="10mm" backbottom="10mm" backleft="10mm" backright="10mm">
                <div class="header">
                    <h1><?= htmlspecialchars($nombreEmpresa) ?></h1>
                    <h2>Listado de Notas de Crédito</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 10%">Número</th>
                            <th style="width: 8%">Fecha</th>
                            <th style="width: 22%">Cliente</th>
                            <th style="width: 10%">Identificación</th>
                            <th style="width: 12%">Doc. Modificado</th>
                            <th style="width: 12%" class="text-end">Total</th>
                            <th style="width: 18%">Motivo</th>
                            <th style="width: 8%">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <?php $numero = ($r['establecimiento'] ?? '') . '-' . ($r['punto_emision'] ?? '') . '-' . ($r['secuencial'] ?? ''); ?>
                            <tr>
                                <td><?= htmlspecialchars((string)$numero) ?></td>
                                <td><?= !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '-' ?></td>
                                <td><?= htmlspecialchars((string)($r['cliente_nombre'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($r['cliente_ruc'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($r['num_doc_modificado'] ?? '')) ?></td>
                                <td class="text-end"><?= number_format((float)($r['importe_total'] ?? 0), 2) ?></td>
                                <td><?= htmlspecialchars((string)($r['motivo'] ?? '')) ?></td>
                                <td><?= ucfirst((string)($r['estado'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </page>
<?php
            $content = ob_get_clean();

            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $html2pdf->writeHTML($content);
            $html2pdf->output('NotasCredito_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo 'Error al generar PDF: ' . $e->getMessage();
            exit;
        }
    }

    public function getNcAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) {
            echo json_encode(['ok' => false, 'mensaje' => 'ID requerido']);
            exit;
        }

        $cabecera = $this->repository->getPorId($id);
        if (!$cabecera || (int)($cabecera['id_empresa'] ?? 0) !== $idEmpresa) {
            echo json_encode(['ok' => false, 'mensaje' => 'Nota de crédito no encontrada']);
            exit;
        }

        $detalles = $this->repository->getDetalles($id);
        foreach ($detalles as &$d) {
            $d['impuestos'] = $this->repository->getImpuestosDetalle((int) $d['id']);
        }
        unset($d);

        echo json_encode([
            'ok'             => true,
            'cabecera'       => $cabecera,
            'detalles'       => $detalles,
            'info_adicional' => $this->repository->getInfoAdicional($id),
        ]);
        exit;
    }

    /**
     * Vista previa del asiento contable de una nota de crédito de venta (pestaña del modal).
     * Si ya tiene asiento guardado devuelve sus líneas; si no, devuelve la sugerencia del builder
     * (asiento inverso de la venta). Para una NC nueva (id = 0) devuelve vacío.
     */
    public function getAsientoSugeridoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $id        = (int) ($_GET['id'] ?? $_GET['id_nota_credito'] ?? 0);

        try {
            if ($id <= 0) {
                echo json_encode(['ok' => true, 'detalles' => [], 'es_guardado' => false]);
                exit;
            }

            $cab = $this->repository->getPorId($id);
            if (!$cab || (int) ($cab['id_empresa'] ?? 0) !== $idEmpresa) {
                echo json_encode(['ok' => true, 'detalles' => []]);
                exit;
            }

            // 1. Asiento guardado → devolver sus líneas.
            $idAsiento = (int) ($cab['id_asiento_contable'] ?? 0);
            if ($idAsiento > 0) {
                $asientoRepo    = new \App\repositories\modulos\AsientoContableRepository();
                $asientoRules   = new \App\Rules\modulos\AsientoContableRules();
                $asientoService = new \App\Services\modulos\AsientoContableService($asientoRepo, $asientoRules, new \App\Services\LogSistemaService());
                $cabA = $asientoService->getDetalleAsiento($idAsiento, $idEmpresa);

                $detalles = [];
                foreach (($cabA['detalles'] ?? []) as $det) {
                    $detalles[] = [
                        'id_cuenta_contable'   => (int) $det['id_cuenta_contable'],
                        'cuenta_codigo'        => $det['codigo_cuenta'] ?? $det['cuenta_codigo'] ?? '',
                        'cuenta_nombre'        => $det['nombre_cuenta'] ?? $det['cuenta_nombre'] ?? '',
                        'debe'                 => (float) $det['debe'],
                        'haber'                => (float) $det['haber'],
                        'referencia_detalle'   => $det['referencia_detalle'] ?? '',
                        'documento_referencia' => $det['documento_referencia'] ?? '',
                    ];
                }
                echo json_encode(['ok' => true, 'detalles' => $detalles, 'es_guardado' => true]);
                exit;
            }

            // 2. Sin asiento guardado: sugerencia del builder (inverso de la venta).
            $builder = new \App\Services\modulos\AsientoBuilderService();
            $detalles = $builder->generarAsientoNotaCreditoVenta($idEmpresa, $id);
            echo json_encode(['ok' => true, 'detalles' => $detalles, 'es_guardado' => false]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function buscarFacturasAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['q'] ?? '');
        $idCliente = (int) ($_GET['id_cliente'] ?? 0);

        // El documento a modificar siempre se busca en base al cliente seleccionado.
        if ($idCliente <= 0) {
            echo json_encode(['ok' => true, 'data' => []]);
            exit;
        }

        $data = [];

        // 1. Facturas de venta del cliente (autorizadas/aprobadas).
        $facturaRepo = new FacturaVentaRepository();
        foreach ($facturaRepo->getFacturasPorCliente($idEmpresa, $idCliente, $buscar) as $f) {
            $est = str_pad((string)($f['establecimiento'] ?? ''), 3, '0', STR_PAD_LEFT);
            $pto = str_pad((string)($f['punto_emision'] ?? ''), 3, '0', STR_PAD_LEFT);
            $sec = str_pad((string)($f['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT);
            $num = "$est-$pto-$sec";
            $data[] = [
                'origen'         => 'venta',
                'id'             => (int) $f['id'],
                'num'            => $num,
                'num_doc'        => $num,
                'fecha_emision'  => $f['fecha_emision'],
                'importe_total'  => (float) ($f['importe_total'] ?? 0),
                'estado'         => $f['estado'],
                'id_cliente'     => (int) $f['id_cliente'],
                'cliente_nombre' => $f['cliente_nombre'] ?? '',
                'cliente_ruc'    => $f['cliente_ruc'] ?? '',
            ];
        }

        // 2. Saldos iniciales (cuentas por cobrar) del cliente.
        $siRepo = new \App\repositories\modulos\SaldosInicialesRepository();
        $cxc = $siRepo->getCxcListado($idEmpresa, ['id_cliente' => $idCliente, 'estado' => 'TODOS']);
        foreach ($cxc as $s) {
            $num = trim((string)($s['nro_documento'] ?? ''));
            if ($buscar !== '' && stripos($num, $buscar) === false) {
                continue;
            }
            $data[] = [
                'origen'         => 'saldo_inicial',
                'id'             => (int) $s['id'],
                'num'            => $num,
                'num_doc'        => $num,
                'fecha_emision'  => $s['fecha_emision'],
                'importe_total'  => (float) ($s['saldo_inicial'] ?? 0),
                'estado'         => 'saldo_inicial',
                'id_cliente'     => (int) ($s['id_cliente'] ?? 0),
                'cliente_nombre' => $s['nombre_cliente'] ?? '',
                'cliente_ruc'    => $s['ruc_cliente'] ?? '',
            ];
        }

        echo json_encode(['ok' => true, 'data' => $data]);
        exit;
    }

    public function getFacturaDetallesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idFactura = (int) ($_GET['id_factura'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $facturaRepo = new FacturaVentaRepository();
        $factura = $facturaRepo->getPorId($idFactura);

        if (!$factura || (int)$factura['id_empresa'] !== $idEmpresa) {
            echo json_encode(['ok' => false, 'mensaje' => 'Factura no encontrada']);
            exit;
        }

        $detalles = $facturaRepo->getDetalles($idFactura);
        foreach ($detalles as &$d) {
            $d['impuestos'] = $facturaRepo->getImpuestosDetalle((int)$d['id']);
        }

        echo json_encode([
            'ok'       => true,
            'cabecera' => $factura,
            'detalles' => $detalles
        ]);
        exit;
    }

    public function guardarAjax(): void
    {
        header('Content-Type: application/json');

        try {
            $data = $_POST;
            if (isset($_POST['data'])) {
                $data = json_decode($_POST['data'], true);
            }

            $data['id_empresa'] = (int) $_SESSION['id_empresa'];
            $data['id_usuario'] = (int) $_SESSION['id_usuario'];

            $empresaModel = new Empresa();
            $empresaData  = $empresaModel->getPorId($data['id_empresa']) ?? [];
            // Fusionar config del establecimiento (decimales, etc.) para que el XML
            // use los mismos decimales que el sistema.
            try {
                $establecimientos = $empresaModel->getEstablecimientos($data['id_empresa']);
                if (!empty($establecimientos)) {
                    $estRepo   = new \App\repositories\modulos\EmpresaRepository();
                    $estConfig = $estRepo->getEstablecimientoConfig((int) $establecimientos[0]['id']);
                    if ($estConfig) {
                        $empresaData = array_merge($empresaData, $estConfig);
                    }
                }
            } catch (\Throwable $e) {}
            $data['empresa_config'] = $empresaData;

            if (!empty($data['id_punto_emision'])) {
                $db = \App\core\Database::getConnection();
                $st = $db->prepare("
                    SELECT p.id_establecimiento, p.codigo_punto, e.codigo AS cod_establecimiento
                    FROM empresa_punto_emision p
                    JOIN empresa_establecimiento e ON e.id = p.id_establecimiento
                    WHERE p.id = ?
                    LIMIT 1
                ");
                $st->execute([$data['id_punto_emision']]);
                $puntoRow = $st->fetch(\PDO::FETCH_ASSOC);

                if ($puntoRow) {
                    if (empty($data['id_establecimiento'])) {
                        $data['id_establecimiento'] = $puntoRow['id_establecimiento'];
                    }
                    if (empty($data['establecimiento'])) {
                        $data['establecimiento'] = $puntoRow['cod_establecimiento'];
                    }
                    if (empty($data['punto_emision'])) {
                        $data['punto_emision'] = $puntoRow['codigo_punto'];
                    }
                }
            }



            $idExistente = !empty($data['id']) ? (int) $data['id'] : 0;

            if ($idExistente > 0) {
                $this->requireActualizar();
                $id = $this->service->actualizar($idExistente, $data);
                $mensaje = 'Nota de crédito actualizada exitosamente.';
            } else {
                $this->requireCrear();
                $id = $this->service->crear($data);
                $mensaje = 'Nota de crédito guardada exitosamente.';
            }

            echo json_encode(['ok' => true, 'mensaje' => $mensaje, 'id' => $id]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function eliminarAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        // Nivel 3 (superadmin) puede eliminar fuera de borrador — ver
        // NotaCreditoService::eliminar(), que hace la verificación real.
        $esSuperAdmin = (int) ($_SESSION['nivel'] ?? 1) === 3;

        try {
            $this->service->eliminar($id, $idEmpresa, $idUsuario, $esSuperAdmin);
            echo json_encode(['ok' => true, 'mensaje' => 'Nota de crédito eliminada correctamente.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }
    public function anularAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $this->service->anular($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Nota de crédito anulada correctamente.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function getTarifasIvaAjax(): void
    {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'data' => $this->repository->getTarifasIva()]);
        exit;
    }

    public function autorizarSRIAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $envioService = new \App\Services\Sri\SriEnvioService();
            $resultado    = $envioService->enviarNotaCredito($id, $idEmpresa, $idUsuario);

            echo json_encode([
                'ok'                  => $resultado['ok'],
                'estado'              => $resultado['estado'],
                'mensaje'             => $resultado['mensaje'],
                'numero_autorizacion' => $resultado['numero_autorizacion'] ?? '',
                'fecha_autorizacion'  => $resultado['fecha_autorizacion']  ?? '',
                'errores'             => $resultado['errores'] ?? [],
            ]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Arma el arreglo de empresa enriquecido con la configuración del
     * establecimiento (igual que el RIDE de factura de venta) y devuelve también
     * la dirección del establecimiento para el XML.
     *
     * @return array{0: array, 1: ?string} [empresa, dirEstablecimiento]
     */
    private function construirEmpresaComprobante(int $idEmpresa, array $nc): array
    {
        $empresaModel = new Empresa();
        $empresa = $empresaModel->getPorId($idEmpresa) ?? [];
        $dirEstablecimiento = null;

        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);

        // Preferir el establecimiento de la NC; si no, el primero disponible.
        $est = null;
        if (!empty($nc['id_establecimiento'])) {
            foreach ($establecimientos as $e) {
                if ((int)$e['id'] === (int)$nc['id_establecimiento']) { $est = $e; break; }
            }
        }
        if (!$est && !empty($establecimientos)) $est = $establecimientos[0];

        if ($est) {
            $dirEstablecimiento = $est['direccion'] ?? null;
            if (!empty($est['logo_ruta']))           $empresa['logo_ruta'] = $est['logo_ruta'];
            if (!empty($est['direccion']))           $empresa['direccion_establecimiento'] = $est['direccion'];
            if (!empty($est['leyenda_pdf_titulo']))  $empresa['leyenda_pdf_titulo'] = $est['leyenda_pdf_titulo'];
            if (!empty($est['leyenda_pdf_mensaje'])) $empresa['leyenda_pdf_mensaje'] = $est['leyenda_pdf_mensaje'];

            try {
                $estRepo   = new \App\repositories\modulos\EmpresaRepository();
                $estConfig = $estRepo->getEstablecimientoConfig((int)$est['id']);
                if ($estConfig) {
                    $estConfig['direccion_matriz']          = $empresa['direccion'] ?? '';
                    $estConfig['direccion_establecimiento'] = $est['direccion'] ?? '';
                    if (!empty($est['logo_ruta']))           $estConfig['logo_ruta'] = $est['logo_ruta'];
                    if (!empty($est['leyenda_pdf_titulo']))  $estConfig['leyenda_pdf_titulo'] = $est['leyenda_pdf_titulo'];
                    if (!empty($est['leyenda_pdf_mensaje'])) $estConfig['leyenda_pdf_mensaje'] = $est['leyenda_pdf_mensaje'];
                    $empresa = array_merge($empresa, $estConfig);
                }
            } catch (\Throwable $e) {}
        }

        return [$empresa, $dirEstablecimiento];
    }

    public function exportPdfDoc(): void
    {
        $this->requireLeer();
        $id = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        try {
            $nc = $this->repository->getPorId($id);
            if (!$nc || (int)$nc['id_empresa'] !== $idEmpresa) {
                die('Nota de crédito no encontrada');
            }

            $detalles = $this->repository->getDetalles($id);
            foreach ($detalles as &$d) {
                $d['impuestos'] = $this->repository->getImpuestosDetalle((int)$d['id']);
            }
            unset($d);

            [$empresa] = $this->construirEmpresaComprobante($idEmpresa, $nc);
            $infoAdicional = $this->repository->getInfoAdicional($id);

            $this->generarPdfNotaCredito($idEmpresa, $nc, $detalles, $empresa, $infoAdicional, 'D');
        } catch (\Throwable $e) {
            die('Error al generar PDF: ' . $e->getMessage());
        }
        exit;
    }

    /**
     * Genera el PDF de una nota de crédito usando la plantilla activa (tipo
     * 'nota_credito') del módulo Plantillas de Documentos; si la empresa no
     * tiene una configurada, usa el diseño original hardcodeado (fallback).
     */
    private function generarPdfNotaCredito(int $idEmpresa, array $nc, array $detalles, array $empresa, array $infoAdicional, string $outputDest)
    {
        $renderer  = new \App\Services\PlantillasPdfRendererService();
        $plantilla = $renderer->getPlantillaActiva($idEmpresa, 'nota_credito');
        if ($plantilla) {
            return $renderer->generar($plantilla, $nc, $detalles, [], $infoAdicional, $empresa, $outputDest);
        }
        $pdfService = new \App\Services\modulos\NotaCreditoPdfService();
        if ($outputDest === 'S') {
            return $pdfService->generarBytes($nc, $detalles, $empresa, $infoAdicional);
        }
        return $pdfService->generar($nc, $detalles, $empresa, $infoAdicional, $outputDest);
    }

    public function exportExcelDoc(): void
    {
        $this->requireLeer();
        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) { http_response_code(400); echo 'ID requerido'; exit; }

        try {
            $nc = $this->repository->getPorId($id);
            if (!$nc || (int) ($nc['id_empresa'] ?? 0) !== $idEmpresa) {
                http_response_code(404); echo 'Nota de crédito no encontrada'; exit;
            }

            $detalles = $this->repository->getDetalles($id);
            [$empresa] = $this->construirEmpresaComprobante($idEmpresa, $nc);

            $numero = ($nc['establecimiento'] ?? '001') . '-' . ($nc['punto_emision'] ?? '001') . '-'
                    . str_pad((string) ($nc['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT);

            require_once MVC_ROOT . '/vendor/autoload.php';

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Nota Credito');

            $sheet->setCellValue('A1', strtoupper((string) ($empresa['nombre'] ?? '')));
            $sheet->mergeCells('A1:F1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

            $sheet->setCellValue('A2', 'NOTA DE CRÉDITO N.° ' . $numero);
            $sheet->mergeCells('A2:F2');
            $sheet->getStyle('A2')->getFont()->setBold(true);

            $fecha = !empty($nc['fecha_emision']) ? date('d-m-Y', strtotime((string) $nc['fecha_emision'])) : '';
            $sheet->setCellValue('A3', 'Fecha: ' . $fecha);
            $sheet->setCellValue('C3', 'Cliente: ' . (string) ($nc['cliente_nombre'] ?? ''));
            $sheet->setCellValue('A4', 'Identificación: ' . (string) ($nc['cliente_ruc'] ?? ''));
            $sheet->setCellValue('C4', 'Estado: ' . ucfirst((string) ($nc['estado'] ?? '')));

            $docModificado = (string) ($nc['num_doc_modificado'] ?? '');
            $fechaSustento = !empty($nc['fecha_emision_docs_sustento']) ? date('d-m-Y', strtotime((string) $nc['fecha_emision_docs_sustento'])) : '';
            $sheet->setCellValue('A5', 'Documento modificado: ' . $docModificado . ($fechaSustento ? ' (' . $fechaSustento . ')' : ''));
            $sheet->setCellValue('C5', 'Motivo: ' . (string) ($nc['motivo'] ?? ''));

            $headerRow = 7;
            $headers = ['Código', 'Descripción', 'Cantidad', 'P. Unitario', 'Descuento', 'Subtotal'];
            $col = 'A';
            foreach ($headers as $h) {
                $sheet->setCellValue($col . $headerRow, $h);
                $col++;
            }
            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '3C465A']],
            ];
            $sheet->getStyle('A' . $headerRow . ':F' . $headerRow)->applyFromArray($headerStyle);

            $row = $headerRow + 1;
            foreach ($detalles as $d) {
                $sheet->setCellValueExplicit('A' . $row, (string) ($d['codigo_principal'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('B' . $row, (string) ($d['descripcion'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue('C' . $row, (float) ($d['cantidad'] ?? 0));
                $sheet->setCellValue('D' . $row, (float) ($d['precio_unitario'] ?? 0));
                $sheet->setCellValue('E' . $row, (float) ($d['descuento'] ?? 0));
                $sheet->setCellValue('F' . $row, (float) ($d['precio_total_sin_impuesto'] ?? 0));
                $row++;
            }
            if ($row > $headerRow + 1) {
                $sheet->getStyle('C' . ($headerRow + 1) . ':F' . ($row - 1))->getNumberFormat()->setFormatCode('#,##0.00');
            }

            $subtotal = (float) ($nc['total_sin_impuestos'] ?? 0);
            $descuentoTotal = (float) ($nc['total_descuento'] ?? 0);
            $total = (float) ($nc['importe_total'] ?? 0);
            $iva = $total - $subtotal + $descuentoTotal;

            $row += 1;
            $totales = [
                'Subtotal sin impuestos' => $subtotal,
                'Descuento' => $descuentoTotal,
                'IVA' => $iva,
                'TOTAL' => $total,
            ];
            foreach ($totales as $label => $valor) {
                $sheet->setCellValue('E' . $row, $label);
                $sheet->getStyle('E' . $row)->getFont()->setBold(true);
                $sheet->setCellValue('F' . $row, $valor);
                $sheet->getStyle('F' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                $row++;
            }

            foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $c) {
                $sheet->getColumnDimension($c)->setAutoSize(true);
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $nombre = 'NotaCredito_' . $numero . '.xlsx';

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

    public function exportXmlDoc(): void
    {
        $this->requireLeer();
        $id = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        try {
            $nc = $this->repository->getPorId($id);
            if (!$nc || (int)$nc['id_empresa'] !== $idEmpresa) {
                die('Nota de crédito no encontrada');
            }

            $numero = ($nc['establecimiento'] ?? '001') . '-' . ($nc['punto_emision'] ?? '001') . '-' . str_pad((string)$nc['secuencial'], 9, '0', STR_PAD_LEFT);

            // Servir el XML ya persistido (autorizado por el SRI cuando aplica).
            if (!empty($nc['detalle_xml'])) {
                header('Content-Type: application/xml; charset=UTF-8');
                header('Content-Disposition: attachment; filename="nc_' . $numero . '.xml"');
                echo $nc['detalle_xml'];
                exit;
            }

            // Fallback: generar el XML, persistirlo en detalle_xml y servirlo.
            $detalles = $this->repository->getDetalles($id);
            foreach ($detalles as &$d) {
                $d['impuestos'] = $this->repository->getImpuestosDetalle((int)$d['id']);
            }
            unset($d);

            [$empresa, $dirEstablecimiento] = $this->construirEmpresaComprobante($idEmpresa, $nc);
            $infoAdicional = $this->repository->getInfoAdicional($id);

            $xmlService = new \App\Services\Xml\XmlNotaCreditoService();
            $xmlString  = $xmlService->generar($nc, $detalles, $infoAdicional, $empresa, $dirEstablecimiento);

            try { $this->repository->updateDetalleXml($id, $xmlString); } catch (\Throwable $e) {}

            header('Content-Type: application/xml; charset=UTF-8');
            header('Content-Disposition: attachment; filename="nc_' . $numero . '.xml"');
            echo $xmlString;
        } catch (\Throwable $e) {
            die('Error al generar XML: ' . $e->getMessage());
        }
        exit;
    }

    public function enviarCorreoAjax(): void
    {
        ob_start();
        $this->requireLeer();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) {
            ob_end_clean();
            echo json_encode(['ok' => false, 'mensaje' => 'ID requerido.']);
            exit;
        }

        try {
            $nc = $this->repository->getPorId($id);
            if (!$nc || (int)($nc['id_empresa'] ?? 0) !== $idEmpresa) {
                ob_end_clean();
                echo json_encode(['ok' => false, 'mensaje' => 'Nota de crédito no encontrada.']);
                exit;
            }
            if (($nc['estado'] ?? '') !== 'autorizado') {
                ob_end_clean();
                echo json_encode(['ok' => false, 'mensaje' => 'La nota de crédito debe estar autorizada para enviar el correo.']);
                exit;
            }

            $detalles = $this->repository->getDetalles($id);
            foreach ($detalles as &$d) {
                $d['impuestos'] = $this->repository->getImpuestosDetalle((int)$d['id']);
            }
            unset($d);

            $infoAdicional = $this->repository->getInfoAdicional($id);
            [$empresa, $dirEstablecimiento] = $this->construirEmpresaComprobante($idEmpresa, $nc);

            $pdfString = $this->generarPdfNotaCredito($idEmpresa, $nc, $detalles, $empresa, $infoAdicional, 'S');

            // XML autorizado persistido; si no existe (NC vieja), se regenera y guarda.
            $xmlString = $nc['detalle_xml'] ?? '';
            if (empty($xmlString)) {
                $xmlService = new \App\Services\Xml\XmlNotaCreditoService();
                $xmlString  = $xmlService->generar($nc, $detalles, $infoAdicional, $empresa, $dirEstablecimiento);
                try { $this->repository->updateDetalleXml($id, $xmlString); } catch (\Throwable $e) {}
            }

            $numAut         = $nc['numero_autorizacion'] ?? $nc['clave_acceso'] ?? '';
            $correosDestino = trim($_POST['correos'] ?? '');

            $emailSvc = new \App\Services\EnvioDocumentosSRIService();
            $enviado  = $emailSvc->enviarSiAplica($idEmpresa, 'nota_credito', $nc, $xmlString, $pdfString, $numAut, true, $correosDestino);

            ob_end_clean();
            if ($enviado) {
                $db = \App\core\Database::getConnection();
                $db->prepare("UPDATE notas_credito_cabecera SET estado_correo = 'enviado' WHERE id = ?")->execute([$id]);
                echo json_encode(['ok' => true, 'mensaje' => 'Correo enviado correctamente.']);
            } else {
                echo json_encode(['ok' => false, 'mensaje' => 'No se pudo enviar el correo. Verifica la configuración o el correo del destinatario.']);
            }
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            ob_end_clean();
            echo json_encode(['ok' => false, 'mensaje' => 'Error al enviar correo: ' . $e->getMessage()]);
        }
        exit;
    }

    public function getHistorialSriAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $logs = (new \App\models\SriEnvioLog())->getPorComprobante('nota_credito', $id, $idEmpresa);
        echo json_encode(['ok' => true, 'data' => $logs]);
        exit;
    }
    public function getSecuencialAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idPt = (int) ($_GET['id_punto'] ?? $_GET['id_punto_emision'] ?? 0);

        if ($idPt <= 0) {
            echo json_encode(['ok' => false, 'mensaje' => 'Punto de emisión requerido.']);
            exit;
        }

        // Mismo servicio centralizado que usa factura de venta: respeta el
        // secuencial inicial configurado, filtra por ambiente/eliminado y
        // detecta huecos en la numeración.
        $secuencialService = new \App\Services\SecuencialService();
        $res = $secuencialService->obtenerSiguienteSecuencial($idPt, 'Nota de crédito');

        echo json_encode(array_merge(['ok' => true], $res));
        exit;
    }

    public function descargarXmlOriginalAjax(): void
    {
        $this->requireLeer();

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) { http_response_code(400); echo 'ID requerido'; exit; }

        $nc = $this->repository->getPorId($id);
        if (!$nc || (int)($nc['id_empresa'] ?? 0) !== $idEmpresa) {
            http_response_code(404); echo 'Nota de crédito no encontrada'; exit;
        }

        $numero   = ($nc['establecimiento'] ?? '001') . '-'
                  . ($nc['punto_emision']   ?? '001') . '-'
                  . str_pad((string)($nc['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT);
        $filename = 'nc_' . $numero . '.xml';

        // Servir desde detalle_xml si existe
        if (!empty($nc['detalle_xml'])) {
            header('Content-Type: application/xml; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo $nc['detalle_xml'];
            exit;
        }

        // Fallback: regenerar y persistir
        try {
            $detalles = $this->repository->getDetalles($id);
            foreach ($detalles as &$d) {
                $d['impuestos'] = $this->repository->getImpuestosDetalle((int)$d['id']);
            }
            unset($d);

            $empresa = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];

            $dirEstablecimiento = null;
            if (!empty($nc['id_establecimiento'])) {
                $estRepo = new \App\repositories\modulos\EmpresaRepository();
                foreach ($estRepo->getEstablecimientos($idEmpresa) as $est) {
                    if ((int)$est['id'] === (int)$nc['id_establecimiento']) {
                        $dirEstablecimiento = $est['direccion'] ?? null;
                        break;
                    }
                }
            }

            $xml = (new \App\Services\Xml\XmlNotaCreditoService())
                ->generar($nc, $detalles, $this->repository->getInfoAdicional($id), $empresa, $dirEstablecimiento);

            $this->repository->updateDetalleXml($id, $xml);

            header('Content-Type: application/xml; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo $xml;
        } catch (\Throwable $e) {
            http_response_code(500); echo 'Error generando XML: ' . $e->getMessage();
        }
        exit;
    }

    public function countBorradoresAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $db  = \App\core\Database::getConnection();
            $sql = "SELECT COUNT(*) FROM notas_credito_cabecera
                    WHERE id_empresa = :id_empresa AND estado = 'borrador' AND eliminado = false AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";
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