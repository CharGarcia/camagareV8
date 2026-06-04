<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\core\Controller;
use App\repositories\modulos\ReporteVentasRepository;

class ReporteVentasController extends BaseModuloController
{
    private ReporteVentasRepository $repository;

    protected function getRutaModulo(): string
    {
        return 'modulos/reporte_ventas';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new ReporteVentasRepository();
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        
        // Obtener tarifas IVA para el filtro
        $facturaRepo = new \App\repositories\modulos\FacturaVentaRepository();
        $tarifasIva = $facturaRepo->getTarifasIva();

        // Obtener los años disponibles para el filtro
        $anios = $this->repository->getAniosDisponibles($idEmpresa);

        $this->viewWithLayout('layouts.main', 'modulos/reporte_ventas/index', [
            'titulo'      => 'Reporte de Ventas',
            'perm'        => $this->getPermisos(),
            'vistaConfig' => $prefsVista,
            'rutaModulo'  => $this->getRutaModulo(),
            'tarifasIva'  => $tarifasIva,
            'anios'       => $anios,
            'fullWidth'   => true,
            'base'        => BASE_URL
        ]);
    }

    private function getFiltrosDesdeRequest(): array
    {
        return [
            'tipo_documento' => $_REQUEST['tipo_documento'] ?? 'FACTURA',
            'agrupar_por'    => $_REQUEST['agrupar_por'] ?? 'NINGUNO',
            'fecha_desde'    => $_REQUEST['fecha_desde'] ?? '',
            'fecha_hasta'    => $_REQUEST['fecha_hasta'] ?? '',
            'id_cliente'     => $_REQUEST['id_cliente'] ?? '',
            'id_producto'    => $_REQUEST['id_producto'] ?? '',
            'estado'         => $_REQUEST['estado'] ?? 'TODOS',
        ];
    }

    public function generarAjax(): void
    {
        error_log("GENERARAjax INICIADO " . json_encode($_REQUEST));
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $filtros = $this->getFiltrosDesdeRequest();

            // Consultar datos
            if ($filtros['agrupar_por'] === 'CLIENTE') {
                $rows = $this->repository->getReporteAgrupadoCliente($idEmpresa, $filtros);
            } elseif ($filtros['agrupar_por'] === 'PRODUCTO') {
                $rows = $this->repository->getReporteAgrupadoProducto($idEmpresa, $filtros);
            } elseif ($filtros['agrupar_por'] === 'FECHA') {
                $rows = $this->repository->getReporteAgrupadoFecha($idEmpresa, $filtros);
            } else {
                $rows = $this->repository->getReporteDetallado($idEmpresa, $filtros);
            }

            // Consultar estadísticas globales (solo afectan a las facturas, sin agrupar por detalle)
            $stats = $this->repository->getEstadisticas($idEmpresa, $filtros);

            // Resumen de estados
            $resumenEstados = $this->repository->getResumenEstados($idEmpresa, $filtros);

            // Generar HTML de las filas según la agrupación
            ob_start();
            if (empty($rows)) {
                $colSpan = ($filtros['agrupar_por'] === 'NINGUNO') ? 8 : 
                           (($filtros['agrupar_por'] === 'CLIENTE') ? 6 : 
                           (($filtros['agrupar_por'] === 'PRODUCTO') ? 7 : 5));
                echo '<tr><td colspan="'.$colSpan.'" class="text-center py-5 text-muted"><i class="bi bi-file-earmark-bar-graph fs-3 d-block mb-2"></i>No se encontraron resultados.</td></tr>';
            } else {
                foreach ($rows as $r) {
                    echo $this->renderFilaAgrupadaHtml($r, $filtros['agrupar_por']);
                }
            }
            $rowsHtml = ob_get_clean();

            $jsonOutput = json_encode([
                'ok'         => true,
                'rows'       => $rowsHtml,
                'rawData'    => $rows,
                'stats'      => $stats,
                'estados'    => $resumenEstados,
                'agrupacion' => $filtros['agrupar_por']
            ]);
            
            if ($jsonOutput === false) {
                error_log("ReporteVentas JSON Encode Error: " . json_last_error_msg());
                echo json_encode(['ok' => false, 'error' => 'Error de codificación de datos.']);
            } else {
                echo $jsonOutput;
            }

        } catch (\Throwable $e) {
            error_log("ReporteVentas Exception: " . $e->getMessage() . " on line " . $e->getLine());
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function renderFilaAgrupadaHtml(array $r, string $agruparPor): string
    {
        $html = '<tr class="align-middle">';
        
        $base0   = number_format((float)($r['base_0'] ?? 0), 2);
        $baseIva = number_format((float)($r['base_iva'] ?? 0), 2);
        $iva     = number_format((float)($r['valor_iva'] ?? 0), 2);
        $total   = number_format((float)($r['total'] ?? 0), 2);

        if ($agruparPor === 'CLIENTE') {
            $html .= "<td><span class='fw-bold'>".htmlspecialchars($r['cliente_nombre'] ?? '')."</span><br><small class='text-muted'>".htmlspecialchars($r['cliente_ruc'] ?? '')."</small></td>";
            $html .= "<td class='text-center'>".(int)($r['cantidad_facturas'] ?? 0)."</td>";
            $html .= "<td class='text-end'>$base0</td>";
            $html .= "<td class='text-end'>$baseIva</td>";
            $html .= "<td class='text-end'>$iva</td>";
            $html .= "<td class='text-end fw-bold text-success'>$total</td>";
        } elseif ($agruparPor === 'PRODUCTO') {
            $tarifa = (float)($r['tarifa_iva'] ?? 0);
            $html .= "<td><span class='fw-bold'>".htmlspecialchars($r['producto_nombre'] ?? '')."</span><br><small class='text-muted'>".htmlspecialchars($r['producto_codigo'] ?? '')."</small></td>";
            $html .= "<td class='text-center'>".(float)($r['cantidad_vendida'] ?? 0)."</td>";
            $html .= "<td class='text-center'>{$tarifa}%</td>";
            $html .= "<td class='text-end'>$base0</td>";
            $html .= "<td class='text-end'>$baseIva</td>";
            $html .= "<td class='text-end'>$iva</td>";
            $html .= "<td class='text-end fw-bold text-success'>$total</td>";
        } elseif ($agruparPor === 'FECHA') {
            $html .= "<td><span class='fw-bold'>".date('d/m/Y', strtotime($r['fecha'] ?? ''))."</span></td>";
            $html .= "<td class='text-center'>".(int)($r['cantidad_facturas'] ?? 0)."</td>";
            $html .= "<td class='text-end'>$base0</td>";
            $html .= "<td class='text-end'>$baseIva</td>";
            $html .= "<td class='text-end'>$iva</td>";
            $html .= "<td class='text-end fw-bold text-success'>$total</td>";
        } else {
            // DETALLADO / NINGUNO
            $estado = strtolower($r['estado'] ?? '');
            $badgeColor = match($estado) {
                'autorizado', 'autorizada' => 'bg-success bg-opacity-10 text-success border-success',
                'borrador' => 'bg-secondary bg-opacity-10 text-secondary border-secondary',
                'anulado' => 'bg-danger bg-opacity-10 text-danger border-danger',
                default => 'bg-primary bg-opacity-10 text-primary border-primary'
            };
            
            $html .= "<td class='text-center'>".date('d/m/Y', strtotime($r['fecha_emision'] ?? ''))."</td>";
            $html .= "<td><span class='fw-bold'>".htmlspecialchars($r['numero_factura'] ?? '')."</span></td>";
            $html .= "<td><span class='fw-bold'>".htmlspecialchars($r['cliente_nombre'] ?? '')."</span><br><small class='text-muted'>".htmlspecialchars($r['cliente_ruc'] ?? '')."</small></td>";
            $html .= "<td class='text-center'><span class='badge border {$badgeColor}'>".strtoupper($estado)."</span></td>";
            $html .= "<td class='text-end'>$base0</td>";
            $html .= "<td class='text-end'>$baseIva</td>";
            $html .= "<td class='text-end'>$iva</td>";
            $html .= "<td class='text-end fw-bold text-success'>$total</td>";
        }
        
        $html .= '</tr>';
        return $html;
    }

    public function getClientesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['q'] ?? '');

        $repo = new \App\repositories\modulos\ClienteRepository();
        $result = $repo->getListado($idEmpresa, $buscar, 1, 15, 'nombre', 'ASC');

        echo json_encode(['ok' => true, 'data' => $result['rows']]);
        exit;
    }

    public function buscarProductosAjax(): void
    {
        $this->requireLeer();
        
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['q'] ?? '');

        $repo = new \App\repositories\modulos\ProductoRepository();
        // Solo productos de tipo 'venta'
        $result = $repo->getListado($idEmpresa, $buscar, 1, 15, 'nombre', 'ASC', null, 'venta');

        echo json_encode(['ok' => true, 'data' => $result['rows']]);
        exit;
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros = $this->getFiltrosDesdeRequest();

        // Consultar datos
        if ($filtros['agrupar_por'] === 'CLIENTE') {
            $rows = $this->repository->getReporteAgrupadoCliente($idEmpresa, $filtros);
        } elseif ($filtros['agrupar_por'] === 'PRODUCTO') {
            $rows = $this->repository->getReporteAgrupadoProducto($idEmpresa, $filtros);
        } elseif ($filtros['agrupar_por'] === 'FECHA') {
            $rows = $this->repository->getReporteAgrupadoFecha($idEmpresa, $filtros);
        } else {
            $rows = $this->repository->getReporteDetallado($idEmpresa, $filtros);
        }

        try {
            $empresa = (new \App\models\Empresa())->getPorId($idEmpresa);
            $nombreEmpresa = $empresa['nombre'] ?? '';

            if ($filtros['agrupar_por'] === 'CLIENTE') {
                $headers = ['RUC/Cédula', 'Cliente', 'Nro Facturas', 'Base 0%', 'Base IVA', 'IVA', 'Total'];
                $exportData = [];
                foreach ($rows as $r) {
                    $exportData[] = [
                        $r['cliente_ruc'],
                        $r['cliente_nombre'],
                        $r['cantidad_facturas'],
                        (float)$r['base_0'],
                        (float)$r['base_iva'],
                        (float)$r['valor_iva'],
                        (float)$r['total']
                    ];
                }
            } elseif ($filtros['agrupar_por'] === 'PRODUCTO') {
                $headers = ['Código', 'Producto', 'Cant. Vendida', 'Tipo IVA', 'Base 0%', 'Base IVA', 'IVA', 'Total'];
                $exportData = [];
                foreach ($rows as $r) {
                    $exportData[] = [
                        $r['producto_codigo'],
                        $r['producto_nombre'],
                        (float)$r['cantidad_vendida'],
                        $r['tarifa_iva'] . '%',
                        (float)$r['base_0'],
                        (float)$r['base_iva'],
                        (float)$r['valor_iva'],
                        (float)$r['total']
                    ];
                }
            } elseif ($filtros['agrupar_por'] === 'FECHA') {
                $headers = ['Fecha', 'Nro Facturas', 'Base 0%', 'Base IVA', 'IVA', 'Total'];
                $exportData = [];
                foreach ($rows as $r) {
                    $exportData[] = [
                        date('d/m/Y', strtotime($r['fecha'])),
                        $r['cantidad_facturas'],
                        (float)$r['base_0'],
                        (float)$r['base_iva'],
                        (float)$r['valor_iva'],
                        (float)$r['total']
                    ];
                }
            } else {
                $headers = ['Fecha', 'Factura', 'Cliente', 'RUC/Cédula', 'Estado', 'Base 0%', 'Base IVA', 'IVA', 'Total'];
                $exportData = [];
                foreach ($rows as $r) {
                    $exportData[] = [
                        date('d/m/Y', strtotime($r['fecha_emision'])),
                        $r['numero_factura'],
                        $r['cliente_nombre'],
                        $r['cliente_ruc'],
                        strtoupper($r['estado']),
                        (float)$r['base_0'],
                        (float)$r['base_iva'],
                        (float)$r['valor_iva'],
                        (float)$r['total']
                    ];
                }
            }

            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcel('Ventas', $headers, $exportData, 'Reporte_Ventas', $nombreEmpresa);
            exit;
        } catch (\Throwable $e) {
            echo "Error al generar Excel: " . $e->getMessage();
        }
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros = $this->getFiltrosDesdeRequest();

        // Consultar datos
        if ($filtros['agrupar_por'] === 'CLIENTE') {
            $rows = $this->repository->getReporteAgrupadoCliente($idEmpresa, $filtros);
        } elseif ($filtros['agrupar_por'] === 'PRODUCTO') {
            $rows = $this->repository->getReporteAgrupadoProducto($idEmpresa, $filtros);
        } elseif ($filtros['agrupar_por'] === 'FECHA') {
            $rows = $this->repository->getReporteAgrupadoFecha($idEmpresa, $filtros);
        } else {
            $rows = $this->repository->getReporteDetallado($idEmpresa, $filtros);
        }
        
        $totales = $this->repository->getEstadisticas($idEmpresa, $filtros);

        try {
            $empresa   = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
            $nombreEmpresa = $empresa['nombre'] ?? 'REPORTE DE VENTAS';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) require_once $autoload;

            ob_start();
            ?>
            <style>
                table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 9pt; margin-bottom: 20px; }
                th { background: #f2f2f2; border: 1px solid #ccc; padding: 6px; text-align: center; }
                td { border: 1px solid #ccc; padding: 6px; }
                .text-end { text-align: right; }
                .text-center { text-align: center; }
                .header { text-align: center; margin-bottom: 20px; }
                .totales-table th { background: #e0e0e0; }
            </style>
            <div class="header">
                <h2><?= htmlspecialchars($nombreEmpresa) ?></h2>
                <h3>Reporte de Ventas</h3>
                <p>Fecha de reporte: <?= date('d-m-Y H:i:s') ?></p>
            </div>
            <table>
                <thead>
                    <?php if ($filtros['agrupar_por'] === 'CLIENTE'): ?>
                        <tr><th>Cliente</th><th>Nro Facturas</th><th>Base 0%</th><th>Base IVA</th><th>IVA</th><th>Total</th></tr>
                    <?php elseif ($filtros['agrupar_por'] === 'PRODUCTO'): ?>
                        <tr><th>Producto</th><th>Cant.</th><th>T. IVA</th><th>Base 0%</th><th>Base IVA</th><th>IVA</th><th>Total</th></tr>
                    <?php elseif ($filtros['agrupar_por'] === 'FECHA'): ?>
                        <tr><th>Fecha</th><th>Nro Facturas</th><th>Base 0%</th><th>Base IVA</th><th>IVA</th><th>Total</th></tr>
                    <?php else: ?>
                        <tr><th>Fecha</th><th>Factura</th><th>Cliente</th><th>Estado</th><th>Base 0%</th><th>Base IVA</th><th>IVA</th><th>Total</th></tr>
                    <?php endif; ?>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <?php if ($filtros['agrupar_por'] === 'CLIENTE'): ?>
                                <td><?= htmlspecialchars($r['cliente_nombre']) ?></td>
                                <td class="text-center"><?= $r['cantidad_facturas'] ?></td>
                                <td class="text-end"><?= number_format((float)$r['base_0'], 2) ?></td>
                                <td class="text-end"><?= number_format((float)$r['base_iva'], 2) ?></td>
                                <td class="text-end"><?= number_format((float)$r['valor_iva'], 2) ?></td>
                                <td class="text-end"><strong><?= number_format((float)$r['total'], 2) ?></strong></td>
                            <?php elseif ($filtros['agrupar_por'] === 'PRODUCTO'): ?>
                                <td><?= htmlspecialchars($r['producto_nombre']) ?></td>
                                <td class="text-center"><?= (float)$r['cantidad_vendida'] ?></td>
                                <td class="text-center"><?= $r['tarifa_iva'] ?>%</td>
                                <td class="text-end"><?= number_format((float)$r['base_0'], 2) ?></td>
                                <td class="text-end"><?= number_format((float)$r['base_iva'], 2) ?></td>
                                <td class="text-end"><?= number_format((float)$r['valor_iva'], 2) ?></td>
                                <td class="text-end"><strong><?= number_format((float)$r['total'], 2) ?></strong></td>
                            <?php elseif ($filtros['agrupar_por'] === 'FECHA'): ?>
                                <td class="text-center"><?= date('d/m/Y', strtotime($r['fecha'])) ?></td>
                                <td class="text-center"><?= $r['cantidad_facturas'] ?></td>
                                <td class="text-end"><?= number_format((float)$r['base_0'], 2) ?></td>
                                <td class="text-end"><?= number_format((float)$r['base_iva'], 2) ?></td>
                                <td class="text-end"><?= number_format((float)$r['valor_iva'], 2) ?></td>
                                <td class="text-end"><strong><?= number_format((float)$r['total'], 2) ?></strong></td>
                            <?php else: ?>
                                <td class="text-center"><?= date('d/m/Y', strtotime($r['fecha_emision'])) ?></td>
                                <td><?= htmlspecialchars($r['numero_factura']) ?></td>
                                <td><?= htmlspecialchars($r['cliente_nombre']) ?></td>
                                <td class="text-center"><?= strtoupper($r['estado']) ?></td>
                                <td class="text-end"><?= number_format((float)$r['base_0'], 2) ?></td>
                                <td class="text-end"><?= number_format((float)$r['base_iva'], 2) ?></td>
                                <td class="text-end"><?= number_format((float)$r['valor_iva'], 2) ?></td>
                                <td class="text-end"><strong><?= number_format((float)$r['total'], 2) ?></strong></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div style="width: 50%; float: right;">
                <table class="totales-table">
                    <tr><th>Total Base 0%</th><td class="text-end"><?= number_format((float)$totales['total_base_0'], 2) ?></td></tr>
                    <tr><th>Total Base IVA</th><td class="text-end"><?= number_format((float)$totales['total_base_iva'], 2) ?></td></tr>
                    <tr><th>Total IVA</th><td class="text-end"><?= number_format((float)$totales['total_iva'], 2) ?></td></tr>
                    <tr><th>GRAN TOTAL</th><td class="text-end" style="font-weight: bold; font-size: 11pt;">$<?= number_format((float)$totales['gran_total'], 2) ?></td></tr>
                </table>
            </div>
            
            <?php
            $html = ob_get_clean();
            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $html2pdf->writeHTML($html);
            $html2pdf->output('ReporteVentas_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            echo "Error al generar PDF: " . $e->getMessage();
        }
    }
}
