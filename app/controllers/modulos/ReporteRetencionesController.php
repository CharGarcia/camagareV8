<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\ReporteRetencionesRepository;

class ReporteRetencionesController extends BaseModuloController
{
    private ReporteRetencionesRepository $repository;

    protected function getRutaModulo(): string
    {
        return 'modulos/reporte_retenciones';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new ReporteRetencionesRepository();
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        // El año actual siempre debe poder seleccionarse, aunque todavía no tenga retenciones registradas.
        $anios = $this->repository->getAnios($idEmpresa);
        $anioActual = (int) date('Y');
        if (!in_array($anioActual, $anios, true)) {
            $anios[] = $anioActual;
            rsort($anios);
        }

        $this->viewWithLayout('layouts.main', 'modulos/reporte_retenciones/index', [
            'titulo'      => 'Reporte de Retenciones',
            'perm'        => $this->getPermisos(),
            'rutaModulo'  => $this->getRutaModulo(),
            'conceptos'   => $this->repository->getConceptosSri(),
            'anios'       => $anios,
            'anioActual'  => $anioActual,
            'fullWidth'   => true,
            'base'        => BASE_URL,
        ]);
    }

    private function getFiltros(): array
    {
        // "Mostrar" sólo ofrece Compras o Ventas: el tipo de sujeto (proveedor/cliente)
        // queda implícito, no se recibe por separado.
        $tipoRetencion = strtoupper(trim($_REQUEST['tipo_retencion'] ?? 'COMPRA'));
        if (!in_array($tipoRetencion, ['COMPRA', 'VENTA'], true)) {
            $tipoRetencion = 'COMPRA';
        }

        return [
            'tipo_retencion'   => $tipoRetencion,
            'ver_por'          => strtoupper(trim($_REQUEST['ver_por'] ?? 'DETALLE')), // DETALLE | CABECERA | TERCERO
            'fecha_desde'      => trim($_REQUEST['fecha_desde'] ?? ''),
            'fecha_hasta'      => trim($_REQUEST['fecha_hasta'] ?? ''),
            'anio'             => (int) ($_REQUEST['anio'] ?? 0),
            'mes'              => (int) ($_REQUEST['mes'] ?? 0),
            'tercero_tipo'     => $tipoRetencion === 'COMPRA' ? 'PROVEEDOR' : 'CLIENTE',
            'tercero_id'       => (int) ($_REQUEST['tercero_id'] ?? 0),
            'codigo_impuesto'  => trim($_REQUEST['codigo_impuesto'] ?? ''),
            'codigo_retencion' => trim($_REQUEST['codigo_retencion'] ?? ''),
            'estado'           => strtoupper(trim($_REQUEST['estado'] ?? 'TODOS')),
            'buscar'           => trim($_REQUEST['buscar'] ?? ''),
        ];
    }

    public function generarAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $f = $this->getFiltros();

            $stats = $this->repository->getEstadisticas($idEmpresa, $f);

            switch ($f['ver_por']) {
                case 'CABECERA':
                    $rows = $this->repository->getReporteAgrupadoCabecera($idEmpresa, $f);
                    $rowsHtml = $this->renderCabecera($rows);
                    break;
                case 'TERCERO':
                    $rows = $this->repository->getReporteAgrupadoTercero($idEmpresa, $f);
                    $rowsHtml = $this->renderTercero($rows);
                    break;
                default:
                    $rows = $this->repository->getReporteDetallado($idEmpresa, $f);
                    $rowsHtml = $this->renderDetalle($rows);
            }

            $urlBase = BASE_URL . '/' . $this->getRutaModulo();
            $qs      = http_build_query($f);
            echo json_encode([
                'ok'        => true,
                'rows'      => $rowsHtml,
                'total'     => count($rows),
                'ver_por'   => $f['ver_por'],
                'stats'     => [
                    'total_renta'   => (float)($stats['total_renta'] ?? 0),
                    'total_iva'     => (float)($stats['total_iva'] ?? 0),
                    'total_isd'     => (float)($stats['total_isd'] ?? 0),
                    'total_general' => (float)($stats['total_general'] ?? 0),
                    'n_compras'     => (int)($stats['n_compras'] ?? 0),
                    'n_ventas'      => (int)($stats['n_ventas'] ?? 0),
                    'n_lineas'      => (int)($stats['n_lineas'] ?? 0),
                ],
                'excel_url' => $urlBase . '/exportExcel?' . $qs,
                'pdf_url'   => $urlBase . '/exportPdf?' . $qs,
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function buscarTercerosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $tipo = strtoupper(trim($_GET['tipo'] ?? 'CLIENTE'));
        $q    = trim($_GET['q'] ?? '');
        echo json_encode(['ok' => true, 'data' => $this->repository->buscarTerceros($idEmpresa, $tipo, $q)]);
        exit;
    }

    // ── Render de filas ───────────────────────────────────────────────────────

    private function badgeTipo(string $tipo): string
    {
        return $tipo === 'COMPRA'
            ? '<span class="badge bg-warning bg-opacity-10 text-warning-emphasis border border-warning border-opacity-25">Compra</span>'
            : '<span class="badge bg-info bg-opacity-10 text-info-emphasis border border-info border-opacity-25">Venta</span>';
    }

    private function nombreImpuesto(?string $cod): string
    {
        return ReporteRetencionesRepository::IMPUESTOS[strtoupper($cod ?? '')] ?? (string)$cod;
    }

    private function renderDetalle(array $rows): string
    {
        if (empty($rows)) {
            return '<tr><td colspan="11" class="text-center py-5 text-muted"><i class="bi bi-search fs-3 d-block mb-2"></i>Sin resultados para los filtros seleccionados.</td></tr>';
        }
        $h = '';
        foreach ($rows as $r) {
            $fecha = !empty($r['fecha']) ? date('d-m-Y', strtotime($r['fecha'])) : '—';
            $h .= '<tr>'
                . '<td>' . $this->badgeTipo($r['tipo_retencion']) . '</td>'
                . '<td><code class="text-secondary">' . htmlspecialchars($r['numero'] ?? '') . '</code></td>'
                . '<td>' . $fecha . '</td>'
                . '<td class="text-truncate" style="max-width:200px" title="' . htmlspecialchars($r['tercero_nombre'] ?? '') . '">' . htmlspecialchars($r['tercero_nombre'] ?? '—') . '<div class="small text-muted">' . htmlspecialchars($r['tercero_ident'] ?? '') . '</div></td>'
                . '<td>' . htmlspecialchars($r['periodo_fiscal'] ?? '') . '</td>'
                . '<td><span class="badge bg-light text-dark border">' . htmlspecialchars($this->nombreImpuesto($r['codigo_impuesto'])) . '</span></td>'
                . '<td>' . htmlspecialchars($r['codigo_retencion'] ?? '') . '</td>'
                . '<td class="text-truncate text-muted" style="max-width:220px" title="' . htmlspecialchars($r['concepto'] ?? '') . '">' . htmlspecialchars($r['concepto'] ?? '') . '</td>'
                . '<td class="text-end">$' . number_format((float)($r['base_imponible'] ?? 0), 2) . '</td>'
                . '<td class="text-end">' . number_format((float)($r['porcentaje'] ?? 0), 2) . '%</td>'
                . '<td class="text-end fw-bold">$' . number_format((float)($r['valor_retenido'] ?? 0), 2) . '</td>'
                . '</tr>';
        }
        return $h;
    }

    private function renderCabecera(array $rows): string
    {
        if (empty($rows)) {
            return '<tr><td colspan="9" class="text-center py-5 text-muted"><i class="bi bi-receipt fs-3 d-block mb-2"></i>Sin resultados para los filtros seleccionados.</td></tr>';
        }
        $h = '';
        foreach ($rows as $r) {
            $fecha = !empty($r['fecha']) ? date('d-m-Y', strtotime($r['fecha'])) : '—';
            $h .= '<tr>'
                . '<td>' . $this->badgeTipo($r['tipo_retencion']) . '</td>'
                . '<td><code class="text-secondary">' . htmlspecialchars($r['numero'] ?? '') . '</code></td>'
                . '<td>' . $fecha . '</td>'
                . '<td class="text-truncate" style="max-width:220px" title="' . htmlspecialchars($r['tercero_nombre'] ?? '') . '">' . htmlspecialchars($r['tercero_nombre'] ?? '—') . '</td>'
                . '<td class="text-end">$' . number_format((float)($r['total_renta'] ?? 0), 2) . '</td>'
                . '<td class="text-end">$' . number_format((float)($r['total_iva'] ?? 0), 2) . '</td>'
                . '<td class="text-end">$' . number_format((float)($r['total_isd'] ?? 0), 2) . '</td>'
                . '<td class="text-end fw-bold">$' . number_format((float)($r['total'] ?? 0), 2) . '</td>'
                . '<td class="text-center">' . (int)($r['n_lineas'] ?? 0) . '</td>'
                . '</tr>';
        }
        return $h;
    }

    private function renderTercero(array $rows): string
    {
        if (empty($rows)) {
            return '<tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-people fs-3 d-block mb-2"></i>Sin resultados para los filtros seleccionados.</td></tr>';
        }
        $h = '';
        foreach ($rows as $r) {
            $tterc = $r['tercero_tipo'] === 'PROVEEDOR' ? 'Proveedor' : 'Cliente';
            $h .= '<tr>'
                . '<td>' . $this->badgeTipo($r['tipo_retencion']) . '</td>'
                . '<td><span class="badge bg-light text-dark border">' . $tterc . '</span></td>'
                . '<td class="fw-medium">' . htmlspecialchars($r['tercero_nombre'] ?? '—') . '<div class="small text-muted">' . htmlspecialchars($r['tercero_ident'] ?? '') . '</div></td>'
                . '<td class="text-center">' . (int)($r['comprobantes'] ?? 0) . '</td>'
                . '<td class="text-center">' . (int)($r['lineas'] ?? 0) . '</td>'
                . '<td class="text-end fw-bold">$' . number_format((float)($r['total'] ?? 0), 2) . '</td>'
                . '</tr>';
        }
        return $h;
    }

    // ── Exportaciones ─────────────────────────────────────────────────────────

    public function exportExcel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $f = $this->getFiltros();
        $nombreEmpresa = (new \App\models\Empresa())->getPorId($idEmpresa)['nombre'] ?? '';

        $exp = $this->datosExport($idEmpresa, $f);
        try {
            (new \App\Services\ReportService())->exportToExcel(
                'Retenciones', $exp['headers'], $exp['data'], 'Reporte_Retenciones', $nombreEmpresa
            );
        } catch (\Throwable $e) {
            echo 'Error al generar Excel: ' . $e->getMessage();
        }
        exit;
    }

    /** Construye [headers, data, right, money] según el modo (ver_por). Excel siempre exporta TODA la información disponible. */
    private function datosExport(int $idEmpresa, array $f): array
    {
        $tp = fn($t) => $t === 'COMPRA' ? 'Compra' : 'Venta';
        switch ($f['ver_por']) {
            case 'CABECERA':
                $rows = $this->repository->getReporteAgrupadoCabecera($idEmpresa, $f);
                $headers = ['Tipo', 'Número', 'Fecha', 'Período Fiscal', 'Sujeto', 'Identificación', 'Estado', 'Líneas', 'Renta', 'IVA', 'ISD', 'Total'];
                $data = array_map(fn($r) => [$tp($r['tipo_retencion']), $r['numero'],
                    !empty($r['fecha']) ? date('d/m/Y', strtotime($r['fecha'])) : '', $r['periodo_fiscal'],
                    $r['tercero_nombre'], $r['tercero_ident'], ucfirst(strtolower($r['estado'] ?? '')),
                    (int)$r['n_lineas'], (float)$r['total_renta'], (float)$r['total_iva'], (float)$r['total_isd'], (float)$r['total']], $rows);
                return ['headers' => $headers, 'data' => $data, 'right' => [7,8,9,10,11], 'money' => [8,9,10,11]];
            case 'TERCERO':
                $rows = $this->repository->getReporteAgrupadoTercero($idEmpresa, $f);
                $headers = ['Tipo', 'Sujeto', 'Identificación', 'Comprobantes', 'Líneas', 'Total Retenido'];
                $data = array_map(fn($r) => [$tp($r['tipo_retencion']), $r['tercero_nombre'], $r['tercero_ident'],
                    (int)$r['comprobantes'], (int)$r['lineas'], (float)$r['total']], $rows);
                return ['headers' => $headers, 'data' => $data, 'right' => [3,4,5], 'money' => [5]];
            default:
                $rows = $this->repository->getReporteDetallado($idEmpresa, $f);
                $headers = ['Tipo', 'Número', 'Fecha', 'Clave de Acceso', 'Sujeto', 'Identificación', 'Período Fiscal', 'Estado',
                    'Doc. Sustento', 'N° Doc. Sustento', 'Fecha Doc. Sustento', 'Impuesto', 'Código Retención', 'Concepto',
                    'Base Imponible', 'Porcentaje', 'Valor Retenido'];
                $data = array_map(fn($r) => [$tp($r['tipo_retencion']), $r['numero'],
                    !empty($r['fecha']) ? date('d/m/Y', strtotime($r['fecha'])) : '', $r['clave_acceso'],
                    $r['tercero_nombre'], $r['tercero_ident'], $r['periodo_fiscal'], ucfirst(strtolower($r['estado'] ?? '')),
                    $r['cod_doc_sustento'], $r['num_doc_sustento'],
                    !empty($r['fecha_doc_sustento']) ? date('d/m/Y', strtotime($r['fecha_doc_sustento'])) : '',
                    $this->nombreImpuesto($r['codigo_impuesto']), $r['codigo_retencion'], $r['concepto'],
                    (float)$r['base_imponible'], (float)$r['porcentaje'], (float)$r['valor_retenido']], $rows);
                return ['headers' => $headers, 'data' => $data, 'right' => [14,15,16], 'money' => [14,16]];
        }
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $f = $this->getFiltros();
        $exp   = $this->datosExport($idEmpresa, $f);
        $stats = $this->repository->getEstadisticas($idEmpresa, $f);
        $empresa = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];

        $autoload = \MVC_ROOT . '/vendor/autoload.php';
        if (file_exists($autoload)) require_once $autoload;

        $money  = fn($v) => number_format((float)$v, 2);
        $right  = array_flip($exp['right']);
        $money2 = array_flip($exp['money'] ?? []);

        ob_start(); ?>
        <style>
            table { width:100%; border-collapse:collapse; font-family:Arial,sans-serif; font-size:7pt; }
            th { background:#f2f2f2; border:1px solid #ccc; padding:3px; }
            td { border:1px solid #ccc; padding:3px; }
            .r { text-align:right; } .c { text-align:center; }
            .head { text-align:center; margin-bottom:10px; }
            .kpi td { border:1px solid #ccc; padding:6px; font-size:9pt; }
        </style>
        <?php $subtitulos = ['CABECERA' => ' — por comprobante', 'TERCERO' => ' — por sujeto retenido']; ?>
        <div class="head">
            <h3><?= htmlspecialchars($empresa['nombre'] ?? '') ?></h3>
            <h4>Reporte de Retenciones<?= $subtitulos[$f['ver_por']] ?? ' — detalle' ?></h4>
            <p style="font-size:8pt">Generado: <?= date('d-m-Y H:i:s') ?></p>
        </div>
        <table class="kpi" style="margin-bottom:10px">
            <tr>
                <td class="c"><strong>Renta</strong><br>$<?= $money($stats['total_renta'] ?? 0) ?></td>
                <td class="c"><strong>IVA</strong><br>$<?= $money($stats['total_iva'] ?? 0) ?></td>
                <td class="c"><strong>ISD</strong><br>$<?= $money($stats['total_isd'] ?? 0) ?></td>
                <td class="c"><strong>Total</strong><br>$<?= $money($stats['total_general'] ?? 0) ?></td>
                <td class="c"><strong>Compras</strong><br><?= (int)($stats['n_compras'] ?? 0) ?></td>
                <td class="c"><strong>Ventas</strong><br><?= (int)($stats['n_ventas'] ?? 0) ?></td>
            </tr>
        </table>
        <table>
            <thead>
                <tr><?php foreach ($exp['headers'] as $i => $h): ?><th class="<?= isset($right[$i]) ? 'r' : '' ?>"><?= htmlspecialchars($h) ?></th><?php endforeach; ?></tr>
            </thead>
            <tbody>
                <?php foreach ($exp['data'] as $fila): ?>
                    <tr>
                        <?php foreach ($fila as $i => $val): ?>
                            <td class="<?= isset($right[$i]) ? 'r' : '' ?>"><?= isset($money2[$i]) ? '$' . $money($val) : htmlspecialchars((string)$val) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        $html = ob_get_clean();
        try {
            $pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $pdf->writeHTML($html);
            $pdf->output('ReporteRetenciones_' . date('Ymd_His') . '.pdf', 'D');
        } catch (\Throwable $e) {
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
        exit;
    }
}
