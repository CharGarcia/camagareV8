<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\core\Controller;
use App\Services\modulos\NotaDebitoService;
use App\repositories\modulos\NotaDebitoRepository;
use App\repositories\modulos\FacturaVentaRepository;
use App\models\Empresa;

class NotaDebitoController extends BaseModuloController
{
    private $service;
    private $repository;

    protected function getRutaModulo(): string
    {
        return 'modulos/nota_debito';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new NotaDebitoRepository();
        $rules            = new \App\Rules\modulos\NotaDebitoRules();
        $logService       = new \App\Services\LogSistemaService();
        $this->service    = new NotaDebitoService($this->repository, $rules, $logService);
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

        // Fusionar config del establecimiento principal (incluye id_forma_pago_sri_def,
        // usado para preseleccionar la forma de pago por defecto en la pestaña de pagos).
        if (!empty($establecimientos)) {
            try {
                $estRepo   = new \App\repositories\modulos\EmpresaRepository();
                $estConfig = $estRepo->getEstablecimientoConfig((int) $establecimientos[0]['id']);
                if ($estConfig) {
                    $empresaData = array_merge($empresaData ?? [], $estConfig);
                }
            } catch (\Throwable $e) {}
        }

        // Solo se ofrecen como Serie los puntos de emisión que ya tienen
        // configurado el secuencial inicial para "Nota de débito"
        // (Empresa → Secuenciales); sin eso no se puede emitir válidamente.
        $secRepo = new \App\repositories\SecuencialRepository();
        $puntos = [];
        foreach ($establecimientos as $est) {
            $pts = $empresaModel->getPuntosEmision((int) $est['id']);
            foreach ($pts as $p) {
                $config = $secRepo->getConfigSecuencial((int) $p['id'], 'Nota de débito');
                if (empty($config['id'])) {
                    continue;
                }
                $p['cod_establecimiento'] = $est['codigo'];
                $puntos[] = $p;
            }
        }

        $total = $result['total'];
        $this->viewWithLayout('layouts.main', 'modulos/nota_debito/index', [
            'titulo'      => 'Notas de Débito',
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
            echo '<tr><td colspan="10" class="text-center py-5 text-muted"><i class="bi bi-file-earmark-minus fs-3 d-block mb-2"></i>No se encontraron notas de débito.</td></tr>';
        } else {
            foreach ($rows as $r) {
                $rowData      = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                $numero       = htmlspecialchars(($r['establecimiento'] ?? '') . '-' . ($r['punto_emision'] ?? '') . '-' . ($r['secuencial'] ?? ''));
                $fecha        = !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '—';
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

                echo '<tr class="nd-row" role="button" tabindex="0" data-row=\'' . $rowData . '\' onclick="window.ND_abrirModalND(this)">
                        <td class="ps-3" data-col="numero"><code>' . $numero . '</code></td>
                        <td data-col="fecha_emision">' . $fecha . '</td>
                        <td class="fw-medium text-truncate" data-col="cliente_nombre" style="max-width:200px">' . htmlspecialchars($r['cliente_nombre'] ?? '—') . '</td>
                        <td data-col="cliente_ruc"><small class="text-muted">' . htmlspecialchars($r['cliente_ruc'] ?? '—') . '</small></td>
                        <td data-col="num_doc_modificado"><small class="text-muted">' . htmlspecialchars($r['num_doc_modificado'] ?? '—') . '</small></td>
                        <td class="text-end" data-col="total_sin_impuestos">$' . number_format((float)($r['total_sin_impuestos'] ?? 0), 2) . '</td>
                        <td class="text-end fw-bold" data-col="importe_total">$' . number_format((float)($r['importe_total'] ?? 0), 2) . '</td>
                        <td data-col="usuario_nombre">' . htmlspecialchars($r['usuario_nombre'] ?? '—') . '</td>
                        <td class="text-center" data-col="estado_correo">' . $correoBadge . '</td>
                        <td class="text-center pe-3" data-col="estado">' . $estadoBadge . '</td>
                      </tr>';
            }
        }
        $rowsHtml = ob_get_clean();

        ob_start();
        $prevDisabled = ($page <= 1)           ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        echo '<button type="button" class="btn btn-outline-secondary" ' . $prevDisabled . ' onclick="window.ND_cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>
              <button type="button" class="btn btn-outline-secondary" ' . $nextDisabled . ' onclick="window.ND_cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>';
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

        $headers    = ['Número', 'Fecha Emisión', 'Cliente', 'Identificación', 'Doc. Modificado', 'Subtotal', 'Total', 'Estado'];
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
                (float)($r['importe_total'] ?? 0),
                ucfirst((string)($r['estado'] ?? '')),
            ];
        }

        try {
            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }
            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcel('Notas de Débito', $headers, $exportData, 'Listado Notas de Débito', $nombreEmpresa);
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
        $nombreEmpresa = $empresa['nombre'] ?? 'REPORTE DE NOTAS DE DÉBITO';

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
                    <h2>Listado de Notas de Débito</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 12%">Número</th>
                            <th style="width: 10%">Fecha</th>
                            <th style="width: 26%">Cliente</th>
                            <th style="width: 12%">Identificación</th>
                            <th style="width: 14%">Doc. Modificado</th>
                            <th style="width: 14%" class="text-end">Total</th>
                            <th style="width: 12%">Estado</th>
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
            $html2pdf->output('NotasDebito_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo 'Error al generar PDF: ' . $e->getMessage();
            exit;
        }
    }

    public function getNdAjax(): void
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
            echo json_encode(['ok' => false, 'mensaje' => 'Nota de débito no encontrada']);
            exit;
        }

        echo json_encode([
            'ok'             => true,
            'cabecera'       => $cabecera,
            'motivos'        => $this->repository->getMotivos($id),
            'impuestos'      => $this->repository->getImpuestos($id),
            'pagos'          => $this->repository->getPagos($id),
            'info_adicional' => $this->repository->getInfoAdicional($id),
        ]);
        exit;
    }

    /**
     * Vista previa del asiento contable de una nota de débito de venta (pestaña del modal).
     */
    public function getAsientoSugeridoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $id        = (int) ($_GET['id'] ?? $_GET['id_nota_debito'] ?? 0);

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

            $builder = new \App\Services\modulos\AsientoBuilderService();
            $detalles = $builder->generarAsientoNotaDebitoVenta($idEmpresa, $id);
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

        if ($idCliente <= 0) {
            echo json_encode(['ok' => true, 'data' => []]);
            exit;
        }

        $data = [];

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

        echo json_encode([
            'ok'       => true,
            'cabecera' => $factura,
        ]);
        exit;
    }

    /**
     * Detalle de la factura de venta que modifica esta ND (pestaña
     * "Factura relacionada" del modal). A diferencia de getFacturaDetallesAjax
     * (que recibe el id de la factura directo, usado al elegirla desde el
     * buscador), aquí se parte del id de la propia ND y se resuelve la
     * factura por su num_doc_modificado.
     */
    public function getFacturaRelacionadaAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $nd = $this->repository->getPorId($id);
        if (!$nd || (int)($nd['id_empresa'] ?? 0) !== $idEmpresa) {
            echo json_encode(['ok' => false, 'mensaje' => 'Nota de débito no encontrada.']);
            exit;
        }

        $numDocModificado = trim((string)($nd['num_doc_modificado'] ?? ''));
        if ($numDocModificado === '') {
            echo json_encode(['ok' => false, 'mensaje' => 'La nota de débito no tiene documento relacionado.']);
            exit;
        }

        $facturaRepo = new FacturaVentaRepository();
        $factura = $facturaRepo->getPorNumeroCompleto($numDocModificado, $idEmpresa);
        if (!$factura) {
            echo json_encode(['ok' => false, 'mensaje' => 'No se encontró la factura relacionada (' . $numDocModificado . ').']);
            exit;
        }

        // getPorId trae cliente + total_cobrado/total_nc/total_nd/total_retencion
        // ya calculados (mismas subconsultas que usa la pestaña de Pagos de Factura de Venta).
        $facturaCompleta = $facturaRepo->getPorId((int)$factura['id']) ?? $factura;
        $detalles = $facturaRepo->getDetalles((int)$factura['id']);

        $saldo = (float)($facturaCompleta['importe_total'] ?? 0)
               + (float)($facturaCompleta['total_nd'] ?? 0)
               - (float)($facturaCompleta['total_cobrado'] ?? 0)
               - (float)($facturaCompleta['total_retencion'] ?? 0)
               - (float)($facturaCompleta['total_nc'] ?? 0);

        echo json_encode([
            'ok'       => true,
            'factura'  => $facturaCompleta,
            'detalles' => $detalles,
            'saldo_pendiente' => round(max(0, $saldo), 2),
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
                $mensaje = 'Nota de débito actualizada exitosamente.';
            } else {
                $this->requireCrear();
                $id = $this->service->crear($data);
                $mensaje = 'Nota de débito guardada exitosamente.';
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

        try {
            $this->service->eliminar($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Nota de débito eliminada correctamente.']);
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
            echo json_encode(['ok' => true, 'mensaje' => 'Nota de débito anulada correctamente.']);
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

    public function getFormasPagoAjax(): void
    {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'data' => $this->repository->getFormasPago()]);
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
            $resultado    = $envioService->enviarNotaDebito($id, $idEmpresa, $idUsuario);

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
     * establecimiento (igual que Nota de Crédito) y devuelve también la
     * dirección del establecimiento para el XML.
     *
     * @return array{0: array, 1: ?string} [empresa, dirEstablecimiento]
     */
    private function construirEmpresaComprobante(int $idEmpresa, array $nd): array
    {
        $empresaModel = new Empresa();
        $empresa = $empresaModel->getPorId($idEmpresa) ?? [];
        $dirEstablecimiento = null;

        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);

        $est = null;
        if (!empty($nd['id_establecimiento'])) {
            foreach ($establecimientos as $e) {
                if ((int)$e['id'] === (int)$nd['id_establecimiento']) { $est = $e; break; }
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
            $nd = $this->repository->getPorId($id);
            if (!$nd || (int)$nd['id_empresa'] !== $idEmpresa) {
                die('Nota de débito no encontrada');
            }

            $motivos   = $this->repository->getMotivos($id);
            $impuestos = $this->repository->getImpuestos($id);
            $pagos     = $this->repository->getPagos($id);
            [$empresa] = $this->construirEmpresaComprobante($idEmpresa, $nd);
            $infoAdicional = $this->repository->getInfoAdicional($id);

            $pdfService = new \App\Services\modulos\NotaDebitoPdfService();
            $pdfService->generar($nd, $motivos, $impuestos, $pagos, $empresa, $infoAdicional, 'D');
        } catch (\Throwable $e) {
            die('Error al generar PDF: ' . $e->getMessage());
        }
        exit;
    }

    public function exportExcelDoc(): void
    {
        $this->requireLeer();
        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) { http_response_code(400); echo 'ID requerido'; exit; }

        try {
            $nd = $this->repository->getPorId($id);
            if (!$nd || (int) ($nd['id_empresa'] ?? 0) !== $idEmpresa) {
                http_response_code(404); echo 'Nota de débito no encontrada'; exit;
            }

            $motivos   = $this->repository->getMotivos($id);
            $impuestos = $this->repository->getImpuestos($id);
            $pagos     = $this->repository->getPagos($id);
            [$empresa] = $this->construirEmpresaComprobante($idEmpresa, $nd);

            $numero = ($nd['establecimiento'] ?? '001') . '-' . ($nd['punto_emision'] ?? '001') . '-'
                    . str_pad((string) ($nd['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT);

            require_once MVC_ROOT . '/vendor/autoload.php';

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Nota Debito');

            $sheet->setCellValue('A1', strtoupper((string) ($empresa['nombre'] ?? '')));
            $sheet->mergeCells('A1:D1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

            $sheet->setCellValue('A2', 'NOTA DE DÉBITO N.° ' . $numero);
            $sheet->mergeCells('A2:D2');
            $sheet->getStyle('A2')->getFont()->setBold(true);

            $fecha = !empty($nd['fecha_emision']) ? date('d-m-Y', strtotime((string) $nd['fecha_emision'])) : '';
            $sheet->setCellValue('A3', 'Fecha: ' . $fecha);
            $sheet->setCellValue('C3', 'Cliente: ' . (string) ($nd['cliente_nombre'] ?? ''));
            $sheet->setCellValue('A4', 'Identificación: ' . (string) ($nd['cliente_ruc'] ?? ''));
            $sheet->setCellValue('C4', 'Estado: ' . ucfirst((string) ($nd['estado'] ?? '')));

            $docModificado = (string) ($nd['num_doc_modificado'] ?? '');
            $fechaSustento = !empty($nd['fecha_emision_docs_sustento']) ? date('d-m-Y', strtotime((string) $nd['fecha_emision_docs_sustento'])) : '';
            $sheet->setCellValue('A5', 'Documento modificado: ' . $docModificado . ($fechaSustento ? ' (' . $fechaSustento . ')' : ''));

            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '3C465A']],
            ];

            // ── Motivos ──────────────────────────────────────────────────────
            $headerRow = 7;
            $sheet->setCellValue('A' . $headerRow, 'Motivo');
            $sheet->setCellValue('D' . $headerRow, 'Valor');
            $sheet->mergeCells('A' . $headerRow . ':C' . $headerRow);
            $sheet->getStyle('A' . $headerRow . ':D' . $headerRow)->applyFromArray($headerStyle);

            $row = $headerRow + 1;
            foreach ($motivos as $m) {
                $sheet->setCellValueExplicit('A' . $row, (string) ($m['razon'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->mergeCells('A' . $row . ':C' . $row);
                $sheet->setCellValue('D' . $row, (float) ($m['valor'] ?? 0));
                $sheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                $row++;
            }

            // ── Totales (a partir de impuestos IVA código 2) ────────────────
            $subtotMap = []; $ivaMap = []; $tarifaMap = [];
            foreach ($impuestos as $imp) {
                if ((string) ($imp['codigo_impuesto'] ?? '') !== '2') continue;
                $codPct = (string) ($imp['codigo_porcentaje'] ?? '0');
                $subtotMap[$codPct] = ($subtotMap[$codPct] ?? 0.0) + (float) ($imp['base_imponible'] ?? 0);
                $ivaMap[$codPct]    = ($ivaMap[$codPct] ?? 0.0) + (float) ($imp['valor'] ?? 0);
                $tarifaMap[$codPct] = (float) ($imp['tarifa'] ?? 0);
            }
            ksort($ivaMap);

            $subtotal = (float) ($nd['total_sin_impuestos'] ?? 0);
            $total = (float) ($nd['importe_total'] ?? 0);

            $row += 1;
            $totales = ['Subtotal sin impuestos' => $subtotal];
            foreach ($ivaMap as $codPct => $ivaVal) {
                $tarPct = $tarifaMap[$codPct] ?? 0.0;
                $tarLabel = $tarPct == (int) $tarPct ? (string) (int) $tarPct : number_format($tarPct, 2);
                $totales["IVA {$tarLabel}%"] = $ivaVal;
            }
            $totales['TOTAL'] = $total;

            foreach ($totales as $label => $valor) {
                $sheet->setCellValue('C' . $row, $label);
                $sheet->getStyle('C' . $row)->getFont()->setBold(true);
                $sheet->setCellValue('D' . $row, $valor);
                $sheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                $row++;
            }

            // ── Forma de pago ────────────────────────────────────────────────
            if (!empty($pagos)) {
                $row += 1;
                $sheet->setCellValue('A' . $row, 'Forma de pago');
                $sheet->getStyle('A' . $row)->getFont()->setBold(true);
                $row++;
                foreach ($pagos as $p) {
                    $sheet->setCellValue('A' . $row, (string) ($p['forma_pago'] ?? ''));
                    $sheet->setCellValue('C' . $row, (float) ($p['total'] ?? 0));
                    $sheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                    $row++;
                }
            }

            foreach (['A', 'B', 'C', 'D'] as $c) {
                $sheet->getColumnDimension($c)->setAutoSize(true);
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $nombre = 'NotaDebito_' . $numero . '.xlsx';

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
            $nd = $this->repository->getPorId($id);
            if (!$nd || (int)$nd['id_empresa'] !== $idEmpresa) {
                die('Nota de débito no encontrada');
            }

            $numero = ($nd['establecimiento'] ?? '001') . '-' . ($nd['punto_emision'] ?? '001') . '-' . str_pad((string)$nd['secuencial'], 9, '0', STR_PAD_LEFT);

            if (!empty($nd['detalle_xml'])) {
                header('Content-Type: application/xml; charset=UTF-8');
                header('Content-Disposition: attachment; filename="nd_' . $numero . '.xml"');
                echo $nd['detalle_xml'];
                exit;
            }

            $motivos   = $this->repository->getMotivos($id);
            $impuestos = $this->repository->getImpuestos($id);
            $pagos     = $this->repository->getPagos($id);
            [$empresa, $dirEstablecimiento] = $this->construirEmpresaComprobante($idEmpresa, $nd);
            $infoAdicional = $this->repository->getInfoAdicional($id);

            $xmlService = new \App\Services\Xml\XmlNotaDebitoService();
            $xmlString  = $xmlService->generar($nd, $motivos, $impuestos, $pagos, $infoAdicional, $empresa, $dirEstablecimiento);

            try { $this->repository->updateDetalleXml($id, $xmlString); } catch (\Throwable $e) {}

            header('Content-Type: application/xml; charset=UTF-8');
            header('Content-Disposition: attachment; filename="nd_' . $numero . '.xml"');
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
            $nd = $this->repository->getPorId($id);
            if (!$nd || (int)($nd['id_empresa'] ?? 0) !== $idEmpresa) {
                ob_end_clean();
                echo json_encode(['ok' => false, 'mensaje' => 'Nota de débito no encontrada.']);
                exit;
            }
            if (($nd['estado'] ?? '') !== 'autorizado') {
                ob_end_clean();
                echo json_encode(['ok' => false, 'mensaje' => 'La nota de débito debe estar autorizada para enviar el correo.']);
                exit;
            }

            $motivos   = $this->repository->getMotivos($id);
            $impuestos = $this->repository->getImpuestos($id);
            $pagos     = $this->repository->getPagos($id);
            $infoAdicional = $this->repository->getInfoAdicional($id);
            [$empresa, $dirEstablecimiento] = $this->construirEmpresaComprobante($idEmpresa, $nd);

            $pdfService = new \App\Services\modulos\NotaDebitoPdfService();
            $pdfString  = $pdfService->generarBytes($nd, $motivos, $impuestos, $pagos, $empresa, $infoAdicional);

            $xmlString = $nd['detalle_xml'] ?? '';
            if (empty($xmlString)) {
                $xmlService = new \App\Services\Xml\XmlNotaDebitoService();
                $xmlString  = $xmlService->generar($nd, $motivos, $impuestos, $pagos, $infoAdicional, $empresa, $dirEstablecimiento);
                try { $this->repository->updateDetalleXml($id, $xmlString); } catch (\Throwable $e) {}
            }

            $numAut         = $nd['numero_autorizacion'] ?? $nd['clave_acceso'] ?? '';
            $correosDestino = trim($_POST['correos'] ?? '');

            $emailSvc = new \App\Services\EnvioDocumentosSRIService();
            $enviado  = $emailSvc->enviarSiAplica($idEmpresa, 'nota_debito', $nd, $xmlString, $pdfString, $numAut, true, $correosDestino);

            ob_end_clean();
            if ($enviado) {
                $db = \App\core\Database::getConnection();
                $db->prepare("UPDATE nota_debito_cabecera SET estado_correo = 'enviado' WHERE id = ?")->execute([$id]);
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

        $logs = (new \App\models\SriEnvioLog())->getPorComprobante('nota_debito', $id, $idEmpresa);
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

        $secuencialService = new \App\Services\SecuencialService();
        $res = $secuencialService->obtenerSiguienteSecuencial($idPt, 'Nota de débito');

        echo json_encode(array_merge(['ok' => true], $res));
        exit;
    }

    public function descargarXmlOriginalAjax(): void
    {
        $this->requireLeer();

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) { http_response_code(400); echo 'ID requerido'; exit; }

        $nd = $this->repository->getPorId($id);
        if (!$nd || (int)($nd['id_empresa'] ?? 0) !== $idEmpresa) {
            http_response_code(404); echo 'Nota de débito no encontrada'; exit;
        }

        $numero   = ($nd['establecimiento'] ?? '001') . '-'
                  . ($nd['punto_emision']   ?? '001') . '-'
                  . str_pad((string)($nd['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT);
        $filename = 'nd_' . $numero . '.xml';

        if (!empty($nd['detalle_xml'])) {
            header('Content-Type: application/xml; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo $nd['detalle_xml'];
            exit;
        }

        try {
            $motivos   = $this->repository->getMotivos($id);
            $impuestos = $this->repository->getImpuestos($id);
            $pagos     = $this->repository->getPagos($id);
            $empresa   = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];

            $dirEstablecimiento = null;
            if (!empty($nd['id_establecimiento'])) {
                $estRepo = new \App\repositories\modulos\EmpresaRepository();
                foreach ($estRepo->getEstablecimientos($idEmpresa) as $est) {
                    if ((int)$est['id'] === (int)$nd['id_establecimiento']) {
                        $dirEstablecimiento = $est['direccion'] ?? null;
                        break;
                    }
                }
            }

            $xml = (new \App\Services\Xml\XmlNotaDebitoService())
                ->generar($nd, $motivos, $impuestos, $pagos, $this->repository->getInfoAdicional($id), $empresa, $dirEstablecimiento);

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
            $sql = "SELECT COUNT(*) FROM nota_debito_cabecera
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
