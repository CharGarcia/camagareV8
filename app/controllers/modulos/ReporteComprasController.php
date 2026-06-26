<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\ReporteComprasRepository;

class ReporteComprasController extends BaseModuloController
{
    private ReporteComprasRepository $repository;

    protected function getRutaModulo(): string
    {
        return 'modulos/reporte_compras';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new ReporteComprasRepository();
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());

        $anios = $this->repository->getAniosDisponibles($idEmpresa);
        $tiposComprobante = $this->repository->getTiposComprobante($idEmpresa);

        $this->viewWithLayout('layouts.main', 'modulos/reporte_compras/index', [
            'titulo'           => 'Reporte de Compras',
            'perm'             => $this->getPermisos(),
            'vistaConfig'      => $prefsVista,
            'rutaModulo'       => $this->getRutaModulo(),
            'anios'            => $anios,
            'tiposComprobante' => $tiposComprobante,
            'fullWidth'        => true,
            'base'             => BASE_URL,
        ]);
    }

    private function getFiltrosDesdeRequest(): array
    {
        return [
            'tipo_documento'    => $_REQUEST['tipo_documento']    ?? 'TODOS',
            'agrupar_por'       => $_REQUEST['agrupar_por']       ?? 'NINGUNO',
            'fecha_desde'       => $_REQUEST['fecha_desde']       ?? '',
            'fecha_hasta'       => $_REQUEST['fecha_hasta']       ?? '',
            'id_proveedor'      => $_REQUEST['id_proveedor']      ?? '',
            'id_producto'       => $_REQUEST['id_producto']       ?? '',
            'tipo_comprobante'  => $_REQUEST['tipo_comprobante']  ?? '',
        ];
    }

    public function generarAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $filtros   = $this->getFiltrosDesdeRequest();

            if ($filtros['agrupar_por'] === 'PROVEEDOR') {
                $rows = $this->repository->getReporteAgrupadoProveedor($idEmpresa, $filtros);
            } elseif ($filtros['agrupar_por'] === 'PRODUCTO') {
                $rows = $this->repository->getReporteAgrupadoProducto($idEmpresa, $filtros);
            } elseif ($filtros['agrupar_por'] === 'FECHA') {
                $rows = $this->repository->getReporteAgrupadoFecha($idEmpresa, $filtros);
            } elseif ($filtros['agrupar_por'] === 'MES') {
                $rows = $this->repository->getReporteAgrupadoMes($idEmpresa, $filtros);
            } else {
                $rows = $this->repository->getReporteDetallado($idEmpresa, $filtros);
            }

            $stats          = $this->repository->getEstadisticas($idEmpresa, $filtros);

            ob_start();
            if (empty($rows)) {
                $colSpan = ($filtros['agrupar_por'] === 'NINGUNO') ? 12 :
                           (($filtros['agrupar_por'] === 'PRODUCTO') ? 7 : 6);
                echo '<tr><td colspan="' . $colSpan . '" class="text-center py-5 text-muted"><i class="bi bi-file-earmark-bar-graph fs-3 d-block mb-2"></i>No se encontraron resultados.</td></tr>';
            } else {
                foreach ($rows as $r) {
                    echo $this->renderFilaHtml($r, $filtros['agrupar_por']);
                }
            }
            $rowsHtml = ob_get_clean();

            $jsonOutput = json_encode([
                'ok'         => true,
                'rows'       => $rowsHtml,
                'rawData'    => $rows,
                'stats'      => $stats,
                'agrupacion' => $filtros['agrupar_por'],
            ]);

            if ($jsonOutput === false) {
                error_log('ReporteCompras JSON Encode Error: ' . json_last_error_msg());
                echo json_encode(['ok' => false, 'error' => 'Error de codificación de datos.']);
            } else {
                echo $jsonOutput;
            }

        } catch (\Throwable $e) {
            error_log('ReporteCompras Exception: ' . $e->getMessage() . ' on line ' . $e->getLine());
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function renderFilaHtml(array $r, string $agruparPor): string
    {
        $html = '<tr class="align-middle">';

        $base0   = number_format((float)($r['base_0']    ?? 0), 2);
        $baseIva = number_format((float)($r['base_iva']  ?? 0), 2);
        $iva     = number_format((float)($r['valor_iva'] ?? 0), 2);
        $total   = number_format((float)($r['total']     ?? 0), 2);

        if ($agruparPor === 'PROVEEDOR') {
            $html .= "<td><span class='fw-bold'>" . htmlspecialchars($r['proveedor_nombre'] ?? '') . "</span><br><small class='text-muted'>" . htmlspecialchars($r['proveedor_ruc'] ?? '') . "</small></td>";
            $html .= "<td class='text-center'>" . (int)($r['cantidad_comprobantes'] ?? 0) . "</td>";
            $html .= "<td class='text-end'>$base0</td>";
            $html .= "<td class='text-end'>$baseIva</td>";
            $html .= "<td class='text-end'>$iva</td>";
            $html .= "<td class='text-end fw-bold text-danger'>$total</td>";
        } elseif ($agruparPor === 'PRODUCTO') {
            $tarifa = (float)($r['tarifa_iva'] ?? 0);
            $html .= "<td><span class='fw-bold'>" . htmlspecialchars($r['producto_nombre'] ?? '') . "</span><br><small class='text-muted'>" . htmlspecialchars($r['producto_codigo'] ?? '') . "</small></td>";
            $html .= "<td class='text-center'>" . (float)($r['cantidad_comprada'] ?? 0) . "</td>";
            $html .= "<td class='text-center'>{$tarifa}%</td>";
            $html .= "<td class='text-end'>$base0</td>";
            $html .= "<td class='text-end'>$baseIva</td>";
            $html .= "<td class='text-end'>$iva</td>";
            $html .= "<td class='text-end fw-bold text-danger'>$total</td>";
        } elseif ($agruparPor === 'FECHA') {
            $html .= "<td><span class='fw-bold'>" . date('d/m/Y', strtotime($r['fecha'] ?? '')) . "</span></td>";
            $html .= "<td class='text-center'>" . (int)($r['cantidad_comprobantes'] ?? 0) . "</td>";
            $html .= "<td class='text-end'>$base0</td>";
            $html .= "<td class='text-end'>$baseIva</td>";
            $html .= "<td class='text-end'>$iva</td>";
            $html .= "<td class='text-end fw-bold text-danger'>$total</td>";
        } elseif ($agruparPor === 'MES') {
            $html .= "<td><span class='fw-bold'>" . self::formatearMes($r['mes'] ?? '') . "</span></td>";
            $html .= "<td class='text-center'>" . (int)($r['cantidad_comprobantes'] ?? 0) . "</td>";
            $html .= "<td class='text-end'>$base0</td>";
            $html .= "<td class='text-end'>$baseIva</td>";
            $html .= "<td class='text-end'>$iva</td>";
            $html .= "<td class='text-end fw-bold text-danger'>$total</td>";
        } else {
            // DETALLADO / NINGUNO
            $retenciones = number_format((float)($r['retenciones'] ?? 0), 2);
            $tipoDoc     = htmlspecialchars($r['tipo_comprobante_nombre'] ?? '');
            $nroAut      = htmlspecialchars($r['numero_autorizacion'] ?? '');

            $html .= "<td class='text-center'>"  . date('d/m/Y', strtotime($r['fecha_emision']  ?? '')) . "</td>";
            $html .= "<td class='text-center text-muted small'>" . date('d/m/Y', strtotime($r['fecha_registro'] ?? $r['fecha_emision'] ?? '')) . "</td>";
            $html .= "<td><span class='fw-bold'>" . htmlspecialchars($r['numero_documento'] ?? '') . "</span></td>";
            $html .= "<td><span class='fw-bold'>" . htmlspecialchars($r['proveedor_nombre'] ?? '') . "</span><br><small class='text-muted'>" . htmlspecialchars($r['proveedor_ruc'] ?? '') . "</small></td>";
            $html .= "<td><span class='badge bg-primary bg-opacity-10 text-primary border border-primary' style='font-size:.7rem;'>$tipoDoc</span></td>";
            $html .= "<td>" . htmlspecialchars($r['usuario_nombre'] ?? '') . "</td>";
            $html .= "<td class='text-muted small' title='" . htmlspecialchars($nroAut) . "'>" . (strlen($nroAut) > 15 ? substr($nroAut, 0, 12) . '…' : $nroAut) . "</td>";
            $html .= "<td class='text-end'>$base0</td>";
            $html .= "<td class='text-end'>$baseIva</td>";
            $html .= "<td class='text-end'>$iva</td>";
            $html .= "<td class='text-end fw-bold text-danger'>$total</td>";
            $html .= "<td class='text-end text-warning'>$retenciones</td>";
        }

        $html .= '</tr>';
        return $html;
    }

    /**
     * Convierte 'YYYY-MM' a un nombre legible: 'Enero 2026'.
     */
    private static function formatearMes(string $mes): string
    {
        if ($mes === '' || strpos($mes, '-') === false) {
            return htmlspecialchars($mes);
        }
        [$anio, $num] = explode('-', $mes);
        $nombres = [
            '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
            '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
            '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre',
        ];
        $nombre = $nombres[str_pad($num, 2, '0', STR_PAD_LEFT)] ?? $num;
        return $nombre . ' ' . $anio;
    }

    public function getProveedoresAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['q'] ?? '');

        $repo   = new \App\repositories\modulos\ProveedorRepository();
        $result = $repo->getListado($idEmpresa, $buscar, 1, 15, 'razon_social', 'ASC');

        $data = array_map(function ($row) {
            return [
                'id'            => $row['id'],
                'nombre'        => $row['razon_social'] ?? $row['nombre'] ?? '',
                'identificacion'=> $row['identificacion'] ?? '',
            ];
        }, $result['rows'] ?? []);

        echo json_encode(['ok' => true, 'data' => $data]);
        exit;
    }

    public function getProductosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['q'] ?? '');

        $repo   = new \App\repositories\modulos\ProductoRepository();
        $result = $repo->getListado($idEmpresa, $buscar, 1, 15, 'nombre', 'ASC', null, 'compra');

        echo json_encode(['ok' => true, 'data' => $result['rows']]);
        exit;
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros   = $this->getFiltrosDesdeRequest();

        if ($filtros['agrupar_por'] === 'PROVEEDOR') {
            $rows = $this->repository->getReporteAgrupadoProveedor($idEmpresa, $filtros);
        } elseif ($filtros['agrupar_por'] === 'PRODUCTO') {
            $rows = $this->repository->getReporteAgrupadoProducto($idEmpresa, $filtros);
        } elseif ($filtros['agrupar_por'] === 'FECHA') {
            $rows = $this->repository->getReporteAgrupadoFecha($idEmpresa, $filtros);
        } elseif ($filtros['agrupar_por'] === 'MES') {
            $rows = $this->repository->getReporteAgrupadoMes($idEmpresa, $filtros);
        } else {
            $rows = $this->repository->getReporteDetallado($idEmpresa, $filtros);
        }

        try {
            $empresa       = (new \App\models\Empresa())->getPorId($idEmpresa);
            $nombreEmpresa = $empresa['nombre'] ?? '';

            if ($filtros['agrupar_por'] === 'PROVEEDOR') {
                $headers = ['RUC/Cédula', 'Proveedor', 'Nro Comprobantes', 'Base 0%', 'Base IVA', 'IVA', 'Total'];
                $exportData = [];
                foreach ($rows as $r) {
                    $exportData[] = [
                        $r['proveedor_ruc'],
                        $r['proveedor_nombre'],
                        $r['cantidad_comprobantes'],
                        (float)$r['base_0'],
                        (float)$r['base_iva'],
                        (float)$r['valor_iva'],
                        (float)$r['total'],
                    ];
                }
            } elseif ($filtros['agrupar_por'] === 'PRODUCTO') {
                $headers = ['Código', 'Producto', 'Cant. Comprada', 'Tipo IVA', 'Base 0%', 'Base IVA', 'IVA', 'Total'];
                $exportData = [];
                foreach ($rows as $r) {
                    $exportData[] = [
                        $r['producto_codigo'],
                        $r['producto_nombre'],
                        (float)$r['cantidad_comprada'],
                        $r['tarifa_iva'] . '%',
                        (float)$r['base_0'],
                        (float)$r['base_iva'],
                        (float)$r['valor_iva'],
                        (float)$r['total'],
                    ];
                }
            } elseif ($filtros['agrupar_por'] === 'FECHA') {
                $headers = ['Fecha', 'Nro Comprobantes', 'Base 0%', 'Base IVA', 'IVA', 'Total'];
                $exportData = [];
                foreach ($rows as $r) {
                    $exportData[] = [
                        date('d/m/Y', strtotime($r['fecha'])),
                        $r['cantidad_comprobantes'],
                        (float)$r['base_0'],
                        (float)$r['base_iva'],
                        (float)$r['valor_iva'],
                        (float)$r['total'],
                    ];
                }
            } elseif ($filtros['agrupar_por'] === 'MES') {
                $headers = ['Mes', 'Nro Comprobantes', 'Base 0%', 'Base IVA', 'IVA', 'Total'];
                $exportData = [];
                foreach ($rows as $r) {
                    $exportData[] = [
                        self::formatearMes($r['mes'] ?? ''),
                        $r['cantidad_comprobantes'],
                        (float)$r['base_0'],
                        (float)$r['base_iva'],
                        (float)$r['valor_iva'],
                        (float)$r['total'],
                    ];
                }
            } else {
                $headers = ['F. Emisión', 'F. Registro', 'Nro Documento', 'Proveedor', 'RUC/Cédula',
                            'Tipo', 'Usuario', 'Nro Autorización',
                            'Base 0%', 'Base IVA', 'IVA', 'Total', 'Retenciones'];
                $exportData = [];
                foreach ($rows as $r) {
                    $exportData[] = [
                        date('d/m/Y', strtotime($r['fecha_emision'])),
                        date('d/m/Y', strtotime($r['fecha_registro'] ?? $r['fecha_emision'])),
                        $r['numero_documento']         ?? '',
                        $r['proveedor_nombre']         ?? '',
                        $r['proveedor_ruc']            ?? '',
                        $r['tipo_comprobante_nombre']  ?? '',
                        $r['usuario_nombre']           ?? '',
                        $r['numero_autorizacion']      ?? '',
                        (float)($r['base_0']      ?? 0),
                        (float)($r['base_iva']    ?? 0),
                        (float)($r['valor_iva']   ?? 0),
                        (float)($r['total']       ?? 0),
                        (float)($r['retenciones'] ?? 0),
                    ];
                }
            }

            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcel('Compras', $headers, $exportData, 'Reporte_Compras', $nombreEmpresa);
            exit;
        } catch (\Throwable $e) {
            echo 'Error al generar Excel: ' . $e->getMessage();
        }
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros   = $this->getFiltrosDesdeRequest();

        if ($filtros['agrupar_por'] === 'PROVEEDOR') {
            $rows = $this->repository->getReporteAgrupadoProveedor($idEmpresa, $filtros);
        } elseif ($filtros['agrupar_por'] === 'PRODUCTO') {
            $rows = $this->repository->getReporteAgrupadoProducto($idEmpresa, $filtros);
        } elseif ($filtros['agrupar_por'] === 'FECHA') {
            $rows = $this->repository->getReporteAgrupadoFecha($idEmpresa, $filtros);
        } elseif ($filtros['agrupar_por'] === 'MES') {
            $rows = $this->repository->getReporteAgrupadoMes($idEmpresa, $filtros);
        } else {
            $rows = $this->repository->getReporteDetallado($idEmpresa, $filtros);
        }

        $totales = $this->repository->getEstadisticas($idEmpresa, $filtros);

        try {
            $empresa       = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
            $nombreEmpresa = $empresa['nombre'] ?? 'REPORTE DE COMPRAS';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) require_once $autoload;

            ob_start();
            ?>
            <style>
                table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 8.5pt; margin: 0 auto 20px auto; }
                th { background: #f2f2f2; border: 1px solid #ccc; padding: 5px; text-align: center; }
                td { border: 1px solid #ccc; padding: 5px; }
                .text-end { text-align: right; }
                .text-center { text-align: center; }
                .header { text-align: center; margin-bottom: 20px; }
            </style>
            <div class="header">
                <h2><?= htmlspecialchars($nombreEmpresa) ?></h2>
                <h3>Reporte de Compras</h3>
                <p>Fecha de reporte: <?= date('d-m-Y H:i:s') ?></p>
            </div>
            <table>
                <thead>
                    <?php if ($filtros['agrupar_por'] === 'PROVEEDOR'): ?>
                        <tr><th>Proveedor</th><th>Nro Comp.</th><th>Base 0%</th><th>Base IVA</th><th>IVA</th><th>Total</th></tr>
                    <?php elseif ($filtros['agrupar_por'] === 'PRODUCTO'): ?>
                        <tr><th>Producto</th><th>Cant.</th><th>T. IVA</th><th>Base 0%</th><th>Base IVA</th><th>IVA</th><th>Total</th></tr>
                    <?php elseif ($filtros['agrupar_por'] === 'FECHA'): ?>
                        <tr><th>Fecha</th><th>Nro Comp.</th><th>Base 0%</th><th>Base IVA</th><th>IVA</th><th>Total</th></tr>
                    <?php elseif ($filtros['agrupar_por'] === 'MES'): ?>
                        <tr><th>Mes</th><th>Nro Comp.</th><th>Base 0%</th><th>Base IVA</th><th>IVA</th><th>Total</th></tr>
                    <?php else: ?>
                        <tr><th>F. Emisión</th><th>Nro Documento</th><th>Proveedor</th><th>Tipo</th><th>Usuario</th><th>Base 0%</th><th>Base IVA</th><th>IVA</th><th>Total</th><th>Retenciones</th></tr>
                    <?php endif; ?>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <?php if ($filtros['agrupar_por'] === 'PROVEEDOR'): ?>
                                <td><?= htmlspecialchars($r['proveedor_nombre']) ?><br><small><?= htmlspecialchars($r['proveedor_ruc']) ?></small></td>
                                <td class="text-center"><?= $r['cantidad_comprobantes'] ?></td>
                                <td class="text-end"><?= number_format((float)$r['base_0'], 2) ?></td>
                                <td class="text-end"><?= number_format((float)$r['base_iva'], 2) ?></td>
                                <td class="text-end"><?= number_format((float)$r['valor_iva'], 2) ?></td>
                                <td class="text-end"><strong><?= number_format((float)$r['total'], 2) ?></strong></td>
                            <?php elseif ($filtros['agrupar_por'] === 'PRODUCTO'): ?>
                                <td><?= htmlspecialchars($r['producto_nombre']) ?></td>
                                <td class="text-center"><?= (float)$r['cantidad_comprada'] ?></td>
                                <td class="text-center"><?= $r['tarifa_iva'] ?>%</td>
                                <td class="text-end"><?= number_format((float)$r['base_0'], 2) ?></td>
                                <td class="text-end"><?= number_format((float)$r['base_iva'], 2) ?></td>
                                <td class="text-end"><?= number_format((float)$r['valor_iva'], 2) ?></td>
                                <td class="text-end"><strong><?= number_format((float)$r['total'], 2) ?></strong></td>
                            <?php elseif ($filtros['agrupar_por'] === 'FECHA'): ?>
                                <td class="text-center"><?= date('d/m/Y', strtotime($r['fecha'])) ?></td>
                                <td class="text-center"><?= $r['cantidad_comprobantes'] ?></td>
                                <td class="text-end"><?= number_format((float)$r['base_0'], 2) ?></td>
                                <td class="text-end"><?= number_format((float)$r['base_iva'], 2) ?></td>
                                <td class="text-end"><?= number_format((float)$r['valor_iva'], 2) ?></td>
                                <td class="text-end"><strong><?= number_format((float)$r['total'], 2) ?></strong></td>
                            <?php elseif ($filtros['agrupar_por'] === 'MES'): ?>
                                <td class="text-center"><?= self::formatearMes($r['mes'] ?? '') ?></td>
                                <td class="text-center"><?= $r['cantidad_comprobantes'] ?></td>
                                <td class="text-end"><?= number_format((float)$r['base_0'], 2) ?></td>
                                <td class="text-end"><?= number_format((float)$r['base_iva'], 2) ?></td>
                                <td class="text-end"><?= number_format((float)$r['valor_iva'], 2) ?></td>
                                <td class="text-end"><strong><?= number_format((float)$r['total'], 2) ?></strong></td>
                            <?php else: ?>
                                <td class="text-center"><?= date('d/m/Y', strtotime($r['fecha_emision'])) ?></td>
                                <td><?= htmlspecialchars($r['numero_documento']) ?></td>
                                <td><?= htmlspecialchars($r['proveedor_nombre']) ?><br><small><?= htmlspecialchars($r['proveedor_ruc']) ?></small></td>
                                <td class="text-center"><?= htmlspecialchars($r['tipo_comprobante_nombre'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['usuario_nombre'] ?? '') ?></td>
                                <td class="text-end"><?= number_format((float)($r['base_0'] ?? 0), 2) ?></td>
                                <td class="text-end"><?= number_format((float)($r['base_iva'] ?? 0), 2) ?></td>
                                <td class="text-end"><?= number_format((float)($r['valor_iva'] ?? 0), 2) ?></td>
                                <td class="text-end"><strong><?= number_format((float)($r['total'] ?? 0), 2) ?></strong></td>
                                <td class="text-end"><?= number_format((float)($r['retenciones'] ?? 0), 2) ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background-color: #e9ecef;">
                        <?php if ($filtros['agrupar_por'] === 'PROVEEDOR' || $filtros['agrupar_por'] === 'FECHA' || $filtros['agrupar_por'] === 'MES'): ?>
                            <th colspan="2" class="text-center" style="font-size: 10pt;">TOTALES:</th>
                        <?php elseif ($filtros['agrupar_por'] === 'PRODUCTO'): ?>
                            <th colspan="3" class="text-center" style="font-size: 10pt;">TOTALES:</th>
                        <?php else: ?>
                            <th colspan="5" class="text-center" style="font-size: 10pt;">TOTALES:</th>
                        <?php endif; ?>
                        <th class="text-end"><?= number_format((float)$totales['total_base_0'],   2) ?></th>
                        <th class="text-end"><?= number_format((float)$totales['total_base_iva'], 2) ?></th>
                        <th class="text-end"><?= number_format((float)$totales['total_iva'],      2) ?></th>
                        <th class="text-end" style="color:#dc3545;font-weight:bold;">$<?= number_format((float)$totales['gran_total'], 2) ?></th>
                        <?php if ($filtros['agrupar_por'] === 'NINGUNO'): ?>
                        <th class="text-end">-</th>
                        <?php endif; ?>
                    </tr>
                </tfoot>
            </table>
            <?php
            $html = ob_get_clean();
            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $html2pdf->writeHTML($html);
            $html2pdf->output('ReporteCompras_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
    }
}
