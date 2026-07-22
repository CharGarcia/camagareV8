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
            'producto_texto' => trim($_REQUEST['producto_texto'] ?? ''),
            'variante_texto' => trim($_REQUEST['variante_texto'] ?? ''),
            'estado'         => $_REQUEST['estado'] ?? 'TODOS',
            'buscar_info'    => trim($_REQUEST['buscar_info'] ?? ''),
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
            } elseif ($filtros['agrupar_por'] === 'VARIANTE') {
                $rows = $this->repository->getReporteAgrupadoVariante($idEmpresa, $filtros);
            } elseif ($filtros['agrupar_por'] === 'FECHA') {
                $rows = $this->repository->getReporteAgrupadoFecha($idEmpresa, $filtros);
            } elseif ($filtros['agrupar_por'] === 'MES') {
                $rows = $this->repository->getReporteAgrupadoMes($idEmpresa, $filtros);
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
                $colSpan = ($filtros['agrupar_por'] === 'NINGUNO') ? 12 :
                           (($filtros['agrupar_por'] === 'PRODUCTO') ? 7 :
                           (($filtros['agrupar_por'] === 'VARIANTE') ? 8 : 6));
                echo '<tr><td colspan="'.$colSpan.'" class="text-center py-5 text-muted"><i class="bi bi-file-earmark-bar-graph fs-3 d-block mb-2"></i>No se encontraron resultados.</td></tr>';
            } else {
                foreach ($rows as $r) {
                    echo $this->renderFilaAgrupadaHtml($r, $filtros['agrupar_por'], $filtros['tipo_documento'] ?? 'FACTURA');
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

    private function renderFilaAgrupadaHtml(array $r, string $agruparPor, string $tipoDocumento = 'FACTURA'): string
    {
        // Solo el modo detallado corresponde a un documento real: se marca la fila
        // para poder abrir el panel lateral con su detalle (ver offcanvas_doc_preview).
        $attrs = '';
        if (!in_array($agruparPor, ['CLIENTE', 'PRODUCTO', 'VARIANTE', 'FECHA', 'MES'], true) && !empty($r['id'])) {
            $tipoDoc = ($tipoDocumento === 'RECIBO') ? 'RECIBO' : 'FACTURA';
            $attrs = ' style="cursor:pointer;" title="Clic para ver el detalle"'
                   . ' data-doc-id="' . (int)$r['id'] . '"'
                   . ' data-doc-tipo="' . $tipoDoc . '"'
                   . ' data-doc-numero="' . htmlspecialchars($r['numero_factura'] ?? '', ENT_QUOTES) . '"'
                   . ' data-doc-sujeto="' . htmlspecialchars($r['cliente_nombre'] ?? '', ENT_QUOTES) . '"';
        }

        $html = '<tr class="align-middle"' . $attrs . '>';

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
        } elseif ($agruparPor === 'VARIANTE') {
            $tarifa = (float)($r['tarifa_iva'] ?? 0);
            $html .= "<td>".htmlspecialchars($r['producto_nombre'] ?? '')."</td>";
            $html .= "<td><span class='fw-bold'>".htmlspecialchars($r['variante_nombre'] ?? '')."</span>: ".htmlspecialchars($r['variante_valor'] ?? '')."</td>";
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
        } elseif ($agruparPor === 'MES') {
            $html .= "<td><span class='fw-bold'>".self::formatearMes($r['mes'] ?? '')."</span></td>";
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
            $retenciones = number_format((float)($r['retenciones'] ?? 0), 2);

            $html .= "<td class='text-center'>".date('d/m/Y', strtotime($r['fecha_emision'] ?? ''))."</td>";
            $html .= "<td><span class='fw-bold'>".htmlspecialchars($r['numero_factura'] ?? '')."</span></td>";
            $html .= "<td><span class='fw-bold'>".htmlspecialchars($r['cliente_nombre'] ?? '')."</span><br><small class='text-muted'>".htmlspecialchars($r['cliente_ruc'] ?? '')."</small></td>";
            $html .= "<td class='text-center'><span class='badge border {$badgeColor}'>".strtoupper($estado)."</span></td>";
            $html .= "<td>".htmlspecialchars($r['vendedor_nombre'] ?? '')."</td>";
            $html .= "<td>".htmlspecialchars($r['cajero_nombre']   ?? '')."</td>";
            $html .= "<td>".htmlspecialchars($r['usuario_nombre']  ?? '')."</td>";
            $html .= "<td class='text-end'>$base0</td>";
            $html .= "<td class='text-end'>$baseIva</td>";
            $html .= "<td class='text-end'>$iva</td>";
            $html .= "<td class='text-end fw-bold text-success'>$total</td>";
            $html .= "<td class='text-end text-danger'>$retenciones</td>";
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

    /** Autocompletado de ítems del documento (facturas o recibos). */
    public function buscarItemsAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $q    = trim($_GET['q'] ?? '');
        $tipo = $_GET['tipo_documento'] ?? 'FACTURA';
        echo json_encode(['ok' => true, 'data' => $this->repository->buscarItems($idEmpresa, $q, $tipo)]);
        exit;
    }

    /** Autocompletado de info adicional (nombre/valor del documento). */
    public function buscarInfoAdicionalAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $q    = trim($_GET['q'] ?? '');
        $tipo = $_GET['tipo_documento'] ?? 'FACTURA';
        echo json_encode(['ok' => true, 'data' => $this->repository->buscarInfoAdicional($idEmpresa, $q, $tipo)]);
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
        } elseif ($filtros['agrupar_por'] === 'VARIANTE') {
            $rows = $this->repository->getReporteAgrupadoVariante($idEmpresa, $filtros);
        } elseif ($filtros['agrupar_por'] === 'FECHA') {
            $rows = $this->repository->getReporteAgrupadoFecha($idEmpresa, $filtros);
        } elseif ($filtros['agrupar_por'] === 'MES') {
            $rows = $this->repository->getReporteAgrupadoMes($idEmpresa, $filtros);
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
            } elseif ($filtros['agrupar_por'] === 'VARIANTE') {
                $headers = ['Producto', 'Variante', 'Valor', 'Cant. Vendida', 'Tipo IVA', 'Base 0%', 'Base IVA', 'IVA', 'Total'];
                $exportData = [];
                foreach ($rows as $r) {
                    $exportData[] = [
                        $r['producto_nombre'],
                        $r['variante_nombre'],
                        $r['variante_valor'],
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
            } elseif ($filtros['agrupar_por'] === 'MES') {
                $headers = ['Mes', 'Nro Facturas', 'Base 0%', 'Base IVA', 'IVA', 'Total'];
                $exportData = [];
                foreach ($rows as $r) {
                    $exportData[] = [
                        self::formatearMes($r['mes'] ?? ''),
                        $r['cantidad_facturas'],
                        (float)$r['base_0'],
                        (float)$r['base_iva'],
                        (float)$r['valor_iva'],
                        (float)$r['total']
                    ];
                }
            } else {
                $headers = ['Fecha', 'Factura', 'Cliente', 'RUC/Cédula', 'Vendedor', 'Cajero', 'Usuario', 'Clave Acceso', 'Base 0%', 'Base IVA', 'IVA', 'Total', 'Retenciones'];
                $exportData = [];
                foreach ($rows as $r) {
                    $exportData[] = [
                        date('d/m/Y', strtotime($r['fecha_emision'])),
                        $r['numero_factura'],
                        $r['cliente_nombre'],
                        $r['cliente_ruc'],
                        $r['vendedor_nombre'] ?? '',
                        $r['cajero_nombre']   ?? '',
                        $r['usuario_nombre']  ?? '',
                        $r['clave_acceso']    ?? '',
                        (float)($r['base_0']    ?? 0),
                        (float)($r['base_iva']  ?? 0),
                        (float)($r['valor_iva'] ?? 0),
                        (float)($r['total']     ?? 0),
                        (float)($r['retenciones'] ?? 0),
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
        } elseif ($filtros['agrupar_por'] === 'VARIANTE') {
            $rows = $this->repository->getReporteAgrupadoVariante($idEmpresa, $filtros);
        } elseif ($filtros['agrupar_por'] === 'FECHA') {
            $rows = $this->repository->getReporteAgrupadoFecha($idEmpresa, $filtros);
        } elseif ($filtros['agrupar_por'] === 'MES') {
            $rows = $this->repository->getReporteAgrupadoMes($idEmpresa, $filtros);
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
                table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 9pt; margin: 0 auto 20px auto; }
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
                    <?php elseif ($filtros['agrupar_por'] === 'VARIANTE'): ?>
                        <tr><th>Producto</th><th>Variante</th><th>Cant.</th><th>T. IVA</th><th>Base 0%</th><th>Base IVA</th><th>IVA</th><th>Total</th></tr>
                    <?php elseif ($filtros['agrupar_por'] === 'FECHA'): ?>
                        <tr><th>Fecha</th><th>Nro Facturas</th><th>Base 0%</th><th>Base IVA</th><th>IVA</th><th>Total</th></tr>
                    <?php elseif ($filtros['agrupar_por'] === 'MES'): ?>
                        <tr><th>Mes</th><th>Nro Facturas</th><th>Base 0%</th><th>Base IVA</th><th>IVA</th><th>Total</th></tr>
                    <?php else: ?>
                        <tr><th>Fecha</th><th>Factura</th><th>Cliente</th><th>Estado</th><th>Vendedor</th><th>Cajero</th><th>Usuario</th><th>Base 0%</th><th>Base IVA</th><th>IVA</th><th>Total</th><th>Retenciones</th></tr>
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
                            <?php elseif ($filtros['agrupar_por'] === 'VARIANTE'): ?>
                                <td><?= htmlspecialchars($r['producto_nombre']) ?></td>
                                <td><?= htmlspecialchars($r['variante_nombre']) ?>: <?= htmlspecialchars($r['variante_valor']) ?></td>
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
                            <?php elseif ($filtros['agrupar_por'] === 'MES'): ?>
                                <td class="text-center"><?= self::formatearMes($r['mes'] ?? '') ?></td>
                                <td class="text-center"><?= $r['cantidad_facturas'] ?></td>
                                <td class="text-end"><?= number_format((float)$r['base_0'], 2) ?></td>
                                <td class="text-end"><?= number_format((float)$r['base_iva'], 2) ?></td>
                                <td class="text-end"><?= number_format((float)$r['valor_iva'], 2) ?></td>
                                <td class="text-end"><strong><?= number_format((float)$r['total'], 2) ?></strong></td>
                            <?php else: ?>
                                <td class="text-center"><?= date('d/m/Y', strtotime($r['fecha_emision'])) ?></td>
                                <td><?= htmlspecialchars($r['numero_factura']) ?></td>
                                <td><?= htmlspecialchars($r['cliente_nombre']) ?></td>
                                <td class="text-center"><?= htmlspecialchars(strtoupper($r['estado'] ?? '')) ?></td>
                                <td><?= htmlspecialchars($r['vendedor_nombre'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['cajero_nombre']   ?? '') ?></td>
                                <td><?= htmlspecialchars($r['usuario_nombre']  ?? '') ?></td>
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
                        <?php if ($filtros['agrupar_por'] === 'CLIENTE' || $filtros['agrupar_por'] === 'FECHA' || $filtros['agrupar_por'] === 'MES'): ?>
                            <th colspan="2" class="text-center" style="font-size: 10pt; vertical-align: middle;">TOTALES GENERALES:</th>
                        <?php elseif ($filtros['agrupar_por'] === 'PRODUCTO'): ?>
                            <th colspan="3" class="text-center" style="font-size: 10pt; vertical-align: middle;">TOTALES GENERALES:</th>
                        <?php elseif ($filtros['agrupar_por'] === 'VARIANTE'): ?>
                            <th colspan="4" class="text-center" style="font-size: 10pt; vertical-align: middle;">TOTALES GENERALES:</th>
                        <?php else: ?>
                            <th colspan="7" class="text-center" style="font-size: 10pt; vertical-align: middle;">TOTALES GENERALES:</th>
                        <?php endif; ?>

                        <th class="text-end" style="font-size: 10pt;"><?= number_format((float)$totales['total_base_0'], 2) ?></th>
                        <th class="text-end" style="font-size: 10pt;"><?= number_format((float)$totales['total_base_iva'], 2) ?></th>
                        <th class="text-end" style="font-size: 10pt;"><?= number_format((float)$totales['total_iva'], 2) ?></th>
                        <th class="text-end" style="font-size: 11pt; font-weight: bold; color: #198754;">$<?= number_format((float)$totales['gran_total'], 2) ?></th>
                        <?php if ($filtros['agrupar_por'] === 'NINGUNO'): ?>
                        <th class="text-end" style="font-size: 10pt;">-</th>
                        <?php endif; ?>
                    </tr>
                </tfoot>
            </table>
            
            <?php
            $html = ob_get_clean();
            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
            $html2pdf->writeHTML($html);
            $html2pdf->output('ReporteVentas_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            echo "Error al generar PDF: " . $e->getMessage();
        }
    }
}
