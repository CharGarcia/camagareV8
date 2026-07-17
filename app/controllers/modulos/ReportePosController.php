<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\ReportePosRepository;

/**
 * Reportes del Punto de Venta: resumen de turnos (arqueo), ventas por forma
 * de pago, productos más vendidos en el POS y ventas por cajero. Mismo
 * patrón que ReporteVentasController/ReporteIngresosEgresosController: un
 * solo select "ver_por" que cambia de vista sin recargar la página.
 */
class ReportePosController extends BaseModuloController
{
    private ReportePosRepository $repository;

    protected function getRutaModulo(): string
    {
        return 'modulos/reporte-pos';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new ReportePosRepository();
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $this->viewWithLayout('layouts.main', 'modulos/reporte_pos/index', [
            'titulo'      => 'Reportes POS',
            'perm'        => $this->getPermisos(),
            'rutaModulo'  => $this->getRutaModulo(),
            'puntos'      => $this->repository->getPuntosConTurno($idEmpresa),
            'cajeros'     => $this->repository->getCajerosConTurno($idEmpresa),
            'fullWidth'   => true,
        ]);
    }

    private function getFiltrosDesdeRequest(): array
    {
        return [
            'ver_por'          => $_REQUEST['ver_por'] ?? 'TURNOS',
            'fecha_desde'      => $_REQUEST['fecha_desde'] ?? '',
            'fecha_hasta'      => $_REQUEST['fecha_hasta'] ?? '',
            'id_punto_emision' => $_REQUEST['id_punto_emision'] ?? '',
            'id_usuario'       => $_REQUEST['id_usuario'] ?? '',
        ];
    }

    private function getRows(int $idEmpresa, array $filtros): array
    {
        return match ($filtros['ver_por']) {
            'FORMA_PAGO' => $this->repository->getVentasPorFormaPago($idEmpresa, $filtros),
            'PRODUCTOS'  => $this->repository->getProductosMasVendidos($idEmpresa, $filtros),
            'CAJERO'     => $this->repository->getVentasPorCajero($idEmpresa, $filtros),
            default      => $this->repository->getResumenTurnos($idEmpresa, $filtros),
        };
    }

    public function generarAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $filtros = $this->getFiltrosDesdeRequest();

            $rows = $this->getRows($idEmpresa, $filtros);
            $stats = $this->repository->getEstadisticas($idEmpresa, $filtros);

            ob_start();
            if (empty($rows)) {
                echo '<tr><td colspan="8" class="text-center py-5 text-muted"><i class="bi bi-graph-up fs-3 d-block mb-2"></i>No se encontraron resultados.</td></tr>';
            } else {
                foreach ($rows as $r) {
                    echo $this->renderFilaHtml($r, $filtros['ver_por']);
                }
            }
            $rowsHtml = ob_get_clean();

            $urlBase = BASE_URL . '/' . $this->getRutaModulo();
            $qs = http_build_query($filtros);
            echo json_encode([
                'ok'        => true,
                'rows'      => $rowsHtml,
                'total'     => count($rows),
                'stats'     => $stats,
                'ver_por'   => $filtros['ver_por'],
                'excel_url' => $urlBase . '/exportExcel?' . $qs,
                'pdf_url'   => $urlBase . '/exportPdf?' . $qs,
            ]);
        } catch (\Throwable $e) {
            error_log('[ReportePos] ' . $e->getMessage());
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function renderFilaHtml(array $r, string $verPor): string
    {
        $money = fn($v) => number_format((float) ($v ?? 0), 2);
        $html = '<tr class="align-middle">';

        if ($verPor === 'FORMA_PAGO') {
            $html .= "<td>" . htmlspecialchars($r['forma_pago'] ?? '') . "</td>";
            $html .= "<td>" . htmlspecialchars($r['tipo'] ?? '') . "</td>";
            $html .= "<td class='text-center'>" . (int) ($r['cantidad_ventas'] ?? 0) . "</td>";
            $html .= "<td class='text-end fw-bold text-success'>$" . $money($r['total']) . "</td>";
        } elseif ($verPor === 'PRODUCTOS') {
            $html .= "<td><span class='fw-bold'>" . htmlspecialchars($r['producto_nombre'] ?? '') . "</span><br><small class='text-muted'>" . htmlspecialchars($r['producto_codigo'] ?? '') . "</small></td>";
            $html .= "<td class='text-center'>" . (float) ($r['cantidad_vendida'] ?? 0) . "</td>";
            $html .= "<td class='text-end fw-bold text-success'>$" . $money($r['total']) . "</td>";
        } elseif ($verPor === 'CAJERO') {
            $html .= "<td>" . htmlspecialchars($r['cajero_nombre'] ?? '') . "</td>";
            $html .= "<td class='text-center'>" . (int) ($r['cantidad_ventas'] ?? 0) . "</td>";
            $html .= "<td class='text-end fw-bold text-success'>$" . $money($r['total']) . "</td>";
        } else {
            // TURNOS
            $estado = strtolower($r['estado'] ?? '');
            $badgeColor = $estado === 'abierta'
                ? 'bg-success bg-opacity-10 text-success border-success'
                : 'bg-secondary bg-opacity-10 text-secondary border-secondary';
            $numeroPunto = htmlspecialchars(($r['cod_establecimiento'] ?? '') . '-' . ($r['codigo_punto'] ?? ''));
            $diferencia = $r['diferencia'] ?? null;
            $diferenciaHtml = $diferencia === null
                ? '<span class="text-muted">—</span>'
                : '<span class="' . ((float) $diferencia < 0 ? 'text-danger' : 'text-success') . '">$' . $money($diferencia) . '</span>';

            $html .= "<td class='text-center'>#" . (int) ($r['id'] ?? 0) . "</td>";
            $html .= "<td>{$numeroPunto}</td>";
            $html .= "<td>" . htmlspecialchars($r['cajero_nombre'] ?? '') . "</td>";
            $html .= "<td class='text-center'>" . date('d-m-Y H:i', strtotime($r['fecha_apertura'] ?? '')) . "</td>";
            $html .= "<td class='text-center'>" . (!empty($r['fecha_cierre']) ? date('d-m-Y H:i', strtotime($r['fecha_cierre'])) : '<span class="text-muted">—</span>') . "</td>";
            $html .= "<td class='text-center'><span class='badge border {$badgeColor}'>" . strtoupper($estado) . "</span></td>";
            $html .= "<td class='text-center'>" . (int) ($r['cantidad_documentos'] ?? 0) . "</td>";
            $html .= "<td class='text-end'>$" . $money($r['fondo_inicial']) . "</td>";
            $html .= "<td class='text-end fw-bold text-success'>$" . $money($r['total_vendido']) . "</td>";
            $html .= "<td class='text-end'>$" . $money($r['monto_contado']) . "</td>";
            $html .= "<td class='text-end'>{$diferenciaHtml}</td>";
        }

        $html .= '</tr>';
        return $html;
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros = $this->getFiltrosDesdeRequest();
        $rows = $this->getRows($idEmpresa, $filtros);

        try {
            $empresa = (new \App\models\Empresa())->getPorId($idEmpresa);
            $nombreEmpresa = $empresa['nombre'] ?? '';

            [$headers, $exportData] = $this->armarExportacion($rows, $filtros['ver_por']);

            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcel('ReportesPOS', $headers, $exportData, 'Reportes_POS', $nombreEmpresa);
            exit;
        } catch (\Throwable $e) {
            echo "Error al generar Excel: " . $e->getMessage();
        }
    }

    private function armarExportacion(array $rows, string $verPor): array
    {
        if ($verPor === 'FORMA_PAGO') {
            $headers = ['Forma de pago', 'Tipo', 'Cant. Ventas', 'Total'];
            $exportData = array_map(fn($r) => [
                $r['forma_pago'], $r['tipo'], (int) $r['cantidad_ventas'], (float) $r['total'],
            ], $rows);
        } elseif ($verPor === 'PRODUCTOS') {
            $headers = ['Código', 'Producto', 'Cant. Vendida', 'Total'];
            $exportData = array_map(fn($r) => [
                $r['producto_codigo'], $r['producto_nombre'], (float) $r['cantidad_vendida'], (float) $r['total'],
            ], $rows);
        } elseif ($verPor === 'CAJERO') {
            $headers = ['Cajero', 'Cant. Ventas', 'Total'];
            $exportData = array_map(fn($r) => [
                $r['cajero_nombre'], (int) $r['cantidad_ventas'], (float) $r['total'],
            ], $rows);
        } else {
            $headers = ['Turno', 'Punto', 'Cajero', 'Apertura', 'Cierre', 'Estado', 'Docs', 'Fondo Inicial', 'Total Vendido', 'Monto Contado', 'Diferencia'];
            $exportData = array_map(fn($r) => [
                '#' . $r['id'],
                $r['cod_establecimiento'] . '-' . $r['codigo_punto'],
                $r['cajero_nombre'],
                date('d-m-Y H:i', strtotime($r['fecha_apertura'])),
                !empty($r['fecha_cierre']) ? date('d-m-Y H:i', strtotime($r['fecha_cierre'])) : '',
                strtoupper($r['estado']),
                (int) $r['cantidad_documentos'],
                (float) $r['fondo_inicial'],
                (float) $r['total_vendido'],
                $r['monto_contado'] !== null ? (float) $r['monto_contado'] : null,
                $r['diferencia'] !== null ? (float) $r['diferencia'] : null,
            ], $rows);
        }

        return [$headers, $exportData];
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros = $this->getFiltrosDesdeRequest();
        $rows = $this->getRows($idEmpresa, $filtros);
        $stats = $this->repository->getEstadisticas($idEmpresa, $filtros);

        try {
            $empresa = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
            $nombreEmpresa = $empresa['nombre'] ?? 'REPORTES POS';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) require_once $autoload;

            [$headers, $exportData] = $this->armarExportacion($rows, $filtros['ver_por']);

            ob_start();
            ?>
            <style>
                table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 9pt; margin: 0 auto 20px auto; }
                th { background: #f2f2f2; border: 1px solid #ccc; padding: 6px; text-align: center; }
                td { border: 1px solid #ccc; padding: 6px; }
                .text-end { text-align: right; }
                .text-center { text-align: center; }
                .header { text-align: center; margin-bottom: 20px; }
            </style>
            <div class="header">
                <h2><?= htmlspecialchars($nombreEmpresa) ?></h2>
                <h3>Reportes POS</h3>
                <p>Fecha de reporte: <?= date('d-m-Y H:i:s') ?></p>
                <p>Ventas: <?= (int) $stats['cantidad_ventas'] ?> — Total: $<?= number_format((float) $stats['total_vendido'], 2) ?></p>
            </div>
            <table>
                <thead>
                    <tr><?php foreach ($headers as $h): ?><th><?= htmlspecialchars($h) ?></th><?php endforeach; ?></tr>
                </thead>
                <tbody>
                    <?php foreach ($exportData as $fila): ?>
                        <tr>
                            <?php foreach ($fila as $val): ?>
                                <td class="<?= is_float($val) ? 'text-end' : '' ?>"><?= is_float($val) ? number_format($val, 2) : htmlspecialchars((string) $val) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
            $html = ob_get_clean();
            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $html2pdf->writeHTML($html);
            $html2pdf->output('ReportesPOS_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            echo "Error al generar PDF: " . $e->getMessage();
        }
    }
}
