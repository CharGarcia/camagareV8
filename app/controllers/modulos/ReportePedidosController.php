<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\ReportePedidosRepository;

class ReportePedidosController extends BaseModuloController
{
    private ReportePedidosRepository $repository;

    protected function getRutaModulo(): string
    {
        return 'modulos/reporte_pedidos';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new ReportePedidosRepository();
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        $anios = $this->repository->getAniosDisponibles($idEmpresa);

        $responsableRepo = new \App\repositories\modulos\ResponsableTrasladoRepository();
        $responsables = $responsableRepo->listarPorEmpresa($idEmpresa);

        $this->viewWithLayout('layouts.main', 'modulos/reporte_pedidos/index', [
            'titulo'       => 'Reporte de Pedidos',
            'perm'         => $this->getPermisos(),
            'vistaConfig'  => $prefsVista,
            'rutaModulo'   => $this->getRutaModulo(),
            'anios'        => $anios,
            'responsables' => $responsables,
            'fullWidth'    => true,
            'base'         => BASE_URL,
        ]);
    }

    private function getFiltrosDesdeRequest(): array
    {
        return [
            'agrupar_por'             => $_REQUEST['agrupar_por'] ?? 'NINGUNO',
            'fecha_desde'             => $_REQUEST['fecha_desde'] ?? '',
            'fecha_hasta'             => $_REQUEST['fecha_hasta'] ?? '',
            'id_cliente'              => $_REQUEST['id_cliente'] ?? '',
            'estado'                  => $_REQUEST['estado'] ?? 'TODOS',
            'id_responsable_entrega'  => $_REQUEST['id_responsable_entrega'] ?? '',
            'producto_texto'          => trim($_REQUEST['producto_texto'] ?? ''),
            'buscar'                  => trim($_REQUEST['buscar'] ?? ''),
        ];
    }

    private function idUsuarioFiltro(): ?int
    {
        $perm = $this->getPermisos();
        return empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;
    }

    private function obtenerFilas(int $idEmpresa, array $filtros): array
    {
        $idUsuarioFiltro = $this->idUsuarioFiltro();
        return match ($filtros['agrupar_por']) {
            'CLIENTE'     => $this->repository->getReporteAgrupadoCliente($idEmpresa, $filtros, $idUsuarioFiltro),
            'PRODUCTO'    => $this->repository->getReporteAgrupadoProducto($idEmpresa, $filtros, $idUsuarioFiltro),
            'ESTADO'      => $this->repository->getReporteAgrupadoEstado($idEmpresa, $filtros, $idUsuarioFiltro),
            'RESPONSABLE' => $this->repository->getReporteAgrupadoResponsable($idEmpresa, $filtros, $idUsuarioFiltro),
            'FECHA'       => $this->repository->getReporteAgrupadoFecha($idEmpresa, $filtros, $idUsuarioFiltro),
            'MES'         => $this->repository->getReporteAgrupadoMes($idEmpresa, $filtros, $idUsuarioFiltro),
            default       => $this->repository->getReporteDetallado($idEmpresa, $filtros, $idUsuarioFiltro),
        };
    }

    public function generarAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $filtros   = $this->getFiltrosDesdeRequest();
            $idUsuarioFiltro = $this->idUsuarioFiltro();

            $rows           = $this->obtenerFilas($idEmpresa, $filtros);
            $stats          = $this->repository->getEstadisticas($idEmpresa, $filtros, $idUsuarioFiltro);
            $resumenEstados = $this->repository->getResumenEstados($idEmpresa, $filtros, $idUsuarioFiltro);

            ob_start();
            if (empty($rows)) {
                $colSpan = $filtros['agrupar_por'] === 'NINGUNO' ? 7 : 3;
                echo '<tr><td colspan="' . $colSpan . '" class="text-center py-5 text-muted"><i class="bi bi-file-earmark-bar-graph fs-3 d-block mb-2"></i>No se encontraron resultados.</td></tr>';
            } else {
                foreach ($rows as $r) {
                    echo $this->renderFilaAgrupadaHtml($r, $filtros['agrupar_por']);
                }
            }
            $rowsHtml = ob_get_clean();

            echo json_encode([
                'ok'         => true,
                'rows'       => $rowsHtml,
                'rawData'    => $rows,
                'stats'      => $stats,
                'estados'    => $resumenEstados,
                'agrupacion' => $filtros['agrupar_por'],
            ]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function renderFilaAgrupadaHtml(array $r, string $agruparPor): string
    {
        $html = '<tr class="align-middle">';
        $cant = number_format((float) ($r['cantidad_total'] ?? 0), 2);

        if ($agruparPor === 'CLIENTE') {
            $html .= "<td><span class='fw-bold'>" . htmlspecialchars($r['cliente_nombre'] ?? '') . "</span><br><small class='text-muted'>" . htmlspecialchars($r['cliente_ruc'] ?? '') . "</small></td>";
            $html .= "<td class='text-center'>" . (int) ($r['cantidad_pedidos'] ?? 0) . "</td>";
            $html .= "<td class='text-end fw-bold'>$cant</td>";
        } elseif ($agruparPor === 'PRODUCTO') {
            $html .= "<td><span class='fw-bold'>" . htmlspecialchars($r['producto_nombre'] ?? '') . "</span><br><small class='text-muted'>" . htmlspecialchars($r['producto_codigo'] ?? '') . "</small></td>";
            $html .= "<td class='text-center'>" . (int) ($r['cantidad_pedidos'] ?? 0) . "</td>";
            $html .= "<td class='text-end fw-bold'>$cant</td>";
        } elseif ($agruparPor === 'ESTADO') {
            $badgeColor = match (strtolower($r['estado'] ?? '')) {
                'pendiente' => 'warning', 'procesado' => 'success', 'anulado' => 'danger', default => 'secondary',
            };
            $html .= "<td><span class='badge bg-{$badgeColor} bg-opacity-10 text-{$badgeColor} border border-{$badgeColor}'>" . htmlspecialchars(strtoupper($r['estado'] ?? '')) . "</span></td>";
            $html .= "<td class='text-center'>" . (int) ($r['cantidad_pedidos'] ?? 0) . "</td>";
            $html .= "<td class='text-end fw-bold'>$cant</td>";
        } elseif ($agruparPor === 'RESPONSABLE') {
            $html .= "<td>" . htmlspecialchars($r['responsable_entrega'] ?? '') . "</td>";
            $html .= "<td class='text-center'>" . (int) ($r['cantidad_pedidos'] ?? 0) . "</td>";
            $html .= "<td class='text-end fw-bold'>$cant</td>";
        } elseif ($agruparPor === 'FECHA') {
            $html .= "<td><span class='fw-bold'>" . date('d/m/Y', strtotime($r['fecha'] ?? '')) . "</span></td>";
            $html .= "<td class='text-center'>" . (int) ($r['cantidad_pedidos'] ?? 0) . "</td>";
            $html .= "<td class='text-end fw-bold'>$cant</td>";
        } elseif ($agruparPor === 'MES') {
            $html .= "<td><span class='fw-bold'>" . self::formatearMes($r['mes'] ?? '') . "</span></td>";
            $html .= "<td class='text-center'>" . (int) ($r['cantidad_pedidos'] ?? 0) . "</td>";
            $html .= "<td class='text-end fw-bold'>$cant</td>";
        } else {
            // DETALLADO / NINGUNO
            $estado = strtolower($r['estado'] ?? '');
            $badgeColor = match ($estado) {
                'pendiente' => 'warning', 'procesado' => 'success', 'anulado' => 'danger', default => 'secondary',
            };
            $html .= "<td class='text-center'>" . date('d/m/Y', strtotime($r['fecha_pedido'] ?? '')) . "</td>";
            $html .= "<td><span class='fw-bold'>" . htmlspecialchars($r['numero_pedido'] ?? '') . "</span></td>";
            $html .= "<td><span class='fw-bold'>" . htmlspecialchars($r['cliente_nombre'] ?? '') . "</span><br><small class='text-muted'>" . htmlspecialchars($r['cliente_ruc'] ?? '') . "</small></td>";
            $html .= "<td class='text-center'><span class='badge bg-{$badgeColor} bg-opacity-10 text-{$badgeColor} border border-{$badgeColor}'>" . htmlspecialchars(strtoupper($estado)) . "</span></td>";
            $html .= "<td class='text-center'>" . (!empty($r['fecha_entrega']) ? date('d/m/Y', strtotime($r['fecha_entrega'])) : '-') . "</td>";
            $html .= "<td>" . htmlspecialchars($r['responsable_entrega'] ?? '-') . "</td>";
            $html .= "<td class='text-end fw-bold'>$cant</td>";
        }

        $html .= '</tr>';
        return $html;
    }

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

    private function encabezadosPorAgrupacion(string $agruparPor): array
    {
        return match ($agruparPor) {
            'CLIENTE'     => ['RUC/Cédula', 'Cliente', 'Nro Pedidos', 'Cant. Pedida'],
            'PRODUCTO'    => ['Código', 'Producto', 'Nro Pedidos', 'Cant. Pedida'],
            'ESTADO'      => ['Estado', 'Nro Pedidos', 'Cant. Pedida'],
            'RESPONSABLE' => ['Responsable Entrega', 'Nro Pedidos', 'Cant. Pedida'],
            'FECHA'       => ['Fecha', 'Nro Pedidos', 'Cant. Pedida'],
            'MES'         => ['Mes', 'Nro Pedidos', 'Cant. Pedida'],
            default       => ['Fecha', 'Nro Pedido', 'Cliente', 'RUC/Cédula', 'Estado', 'Fecha Entrega', 'Resp. Entrega', 'Cant. Pedida'],
        };
    }

    private function filaExport(array $r, string $agruparPor): array
    {
        return match ($agruparPor) {
            'CLIENTE'     => [$r['cliente_ruc'], $r['cliente_nombre'], (int) $r['cantidad_pedidos'], (float) $r['cantidad_total']],
            'PRODUCTO'    => [$r['producto_codigo'], $r['producto_nombre'], (int) $r['cantidad_pedidos'], (float) $r['cantidad_total']],
            'ESTADO'      => [strtoupper($r['estado']), (int) $r['cantidad_pedidos'], (float) $r['cantidad_total']],
            'RESPONSABLE' => [$r['responsable_entrega'], (int) $r['cantidad_pedidos'], (float) $r['cantidad_total']],
            'FECHA'       => [date('d/m/Y', strtotime($r['fecha'])), (int) $r['cantidad_pedidos'], (float) $r['cantidad_total']],
            'MES'         => [self::formatearMes($r['mes'] ?? ''), (int) $r['cantidad_pedidos'], (float) $r['cantidad_total']],
            default       => [
                date('d/m/Y', strtotime($r['fecha_pedido'])),
                $r['numero_pedido'],
                $r['cliente_nombre'],
                $r['cliente_ruc'],
                strtoupper($r['estado']),
                !empty($r['fecha_entrega']) ? date('d/m/Y', strtotime($r['fecha_entrega'])) : '',
                $r['responsable_entrega'] ?? '',
                (float) ($r['cantidad_total'] ?? 0),
            ],
        };
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros   = $this->getFiltrosDesdeRequest();
        $rows      = $this->obtenerFilas($idEmpresa, $filtros);

        try {
            $empresa = (new \App\models\Empresa())->getPorId($idEmpresa);
            $nombreEmpresa = $empresa['nombre'] ?? '';

            $headers = $this->encabezadosPorAgrupacion($filtros['agrupar_por']);
            $exportData = array_map(fn($r) => $this->filaExport($r, $filtros['agrupar_por']), $rows);

            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcel('Pedidos', $headers, $exportData, 'Reporte_Pedidos', $nombreEmpresa);
            exit;
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            http_response_code(500); echo "Error al generar Excel: " . $e->getMessage();
            exit;
        }
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros   = $this->getFiltrosDesdeRequest();
        $rows      = $this->obtenerFilas($idEmpresa, $filtros);
        $stats     = $this->repository->getEstadisticas($idEmpresa, $filtros, $this->idUsuarioFiltro());

        try {
            $empresa = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
            $nombreEmpresa = $empresa['nombre'] ?? 'REPORTE DE PEDIDOS';
            $headers = $this->encabezadosPorAgrupacion($filtros['agrupar_por']);

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
            </style>
            <div class="header">
                <h2><?= htmlspecialchars($nombreEmpresa) ?></h2>
                <h3>Reporte de Pedidos</h3>
                <p>Fecha de reporte: <?= date('d-m-Y H:i:s') ?></p>
            </div>
            <table>
                <thead>
                    <tr><?php foreach ($headers as $h): ?><th><?= htmlspecialchars($h) ?></th><?php endforeach; ?></tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): $fila = $this->filaExport($r, $filtros['agrupar_por']); ?>
                        <tr>
                            <?php foreach ($fila as $i => $v): ?>
                                <td class="<?= is_float($v) || is_int($v) ? 'text-end' : '' ?>">
                                    <?= is_float($v) ? number_format($v, 2) : htmlspecialchars((string) $v) ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php // Nota: los valores enteros (Nro Pedidos) se muestran sin decimales; solo "Cant. Pedida" (float) usa number_format. ?>
                </tbody>
                <tfoot>
                    <tr style="background-color: #e9ecef;">
                        <th colspan="<?= count($headers) - 1 ?>" class="text-center">TOTALES: <?= (int) $stats['total_pedidos'] ?> pedido(s)</th>
                        <th class="text-end"><?= number_format((float) $stats['total_cantidad'], 2) ?></th>
                    </tr>
                </tfoot>
            </table>
<?php
            $html = ob_get_clean();
            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
            $html2pdf->writeHTML($html);
            $html2pdf->output('ReportePedidos_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            http_response_code(500); echo "Error al generar PDF: " . $e->getMessage();
            exit;
        }
    }
}
