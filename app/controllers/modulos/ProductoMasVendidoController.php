<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\ProductoMasVendidoRepository;

class ProductoMasVendidoController extends BaseModuloController
{
    private ProductoMasVendidoRepository $repository;

    protected function getRutaModulo(): string
    {
        return 'modulos/producto_mas_vendido';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new ProductoMasVendidoRepository();
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $anios = $this->repository->getAniosDisponibles($idEmpresa);

        $this->viewWithLayout('layouts.main', 'modulos/producto_mas_vendido/index', [
            'titulo'     => 'Productos Más Vendidos',
            'perm'       => $this->getPermisos(),
            'rutaModulo' => $this->getRutaModulo(),
            'anios'      => $anios,
            'fullWidth'  => true,
            'base'       => BASE_URL,
        ]);
    }

    private function getFiltrosDesdeRequest(): array
    {
        return [
            'tipo_documento' => $_REQUEST['tipo_documento'] ?? 'FACTURA',
            'fecha_desde'    => $_REQUEST['fecha_desde'] ?? '',
            'fecha_hasta'    => $_REQUEST['fecha_hasta'] ?? '',
            'id_cliente'     => $_REQUEST['id_cliente'] ?? '',
            'id_producto'    => $_REQUEST['id_producto'] ?? '',
            'top_n'          => $_REQUEST['top_n'] ?? '20',
        ];
    }

    /**
     * Obtiene el ranking completo, las estadísticas globales (sobre el total,
     * antes de recortar) y las filas ya recortadas según el Top N elegido.
     */
    private function obtenerDatos(int $idEmpresa, array $filtros): array
    {
        $todos = $this->repository->getTopProductos($idEmpresa, $filtros);
        $stats = $this->calcularEstadisticas($todos);

        $topN = $filtros['top_n'] ?? '20';
        $rows = ($topN === 'TODOS') ? $todos : array_slice($todos, 0, max(1, (int) $topN));

        return [$rows, $stats];
    }

    private function calcularEstadisticas(array $todos): array
    {
        $totalUnidades = 0.0;
        $totalVendido  = 0.0;
        foreach ($todos as $r) {
            $totalUnidades += (float) $r['cantidad_vendida'];
            $totalVendido  += (float) $r['total_vendido'];
        }

        return [
            'productos_distintos' => count($todos),
            'total_unidades'      => $totalUnidades,
            'total_vendido'       => $totalVendido,
            'top1_nombre'         => $todos[0]['producto_nombre'] ?? '',
            'top1_cantidad'       => (float) ($todos[0]['cantidad_vendida'] ?? 0),
        ];
    }

    public function generarAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $filtros = $this->getFiltrosDesdeRequest();

            [$rows, $stats] = $this->obtenerDatos($idEmpresa, $filtros);

            ob_start();
            if (empty($rows)) {
                echo '<tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-graph-up fs-3 d-block mb-2"></i>No se encontraron resultados.</td></tr>';
            } else {
                foreach ($rows as $r) {
                    echo $this->renderFilaHtml($r);
                }
            }
            $rowsHtml = ob_get_clean();

            echo json_encode([
                'ok'      => true,
                'rows'    => $rowsHtml,
                'rawData' => $rows,
                'stats'   => $stats,
            ]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function renderFilaHtml(array $r): string
    {
        $ranking = (int) ($r['ranking'] ?? 0);
        $badgeColor = $ranking <= 3
            ? 'bg-warning bg-opacity-25 text-warning border-warning'
            : 'bg-light text-secondary border-secondary';

        $cantidad = number_format((float) ($r['cantidad_vendida'] ?? 0), 2);
        $total    = number_format((float) ($r['total_vendido'] ?? 0), 2);

        $html = '<tr class="align-middle">';
        $html .= "<td class='text-center'><span class='badge border {$badgeColor}'>#{$ranking}</span></td>";
        $html .= "<td><span class='fw-bold'>" . htmlspecialchars($r['producto_nombre'] ?? '') . "</span><br><small class='text-muted'>" . htmlspecialchars($r['producto_codigo'] ?? '') . "</small></td>";
        $html .= "<td class='text-center fw-bold'>{$cantidad}</td>";
        $html .= "<td class='text-center'>" . (int) ($r['num_documentos'] ?? 0) . "</td>";
        $html .= "<td class='text-end fw-bold text-success'>\${$total}</td>";
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
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['q'] ?? '');

        $repo = new \App\repositories\modulos\ProductoRepository();
        $result = $repo->getListado($idEmpresa, $buscar, 1, 15, 'nombre', 'ASC', null, 'venta');

        echo json_encode(['ok' => true, 'data' => $result['rows']]);
        exit;
    }

    /**
     * HTML del cuerpo del reporte, reutilizado por exportPdf() y enviarCorreoAjax().
     */
    private function construirHtmlReporte(array $rows, array $stats, string $nombreEmpresa): string
    {
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
            <h3>Reporte de Productos Más Vendidos</h3>
            <p>Fecha de reporte: <?= date('d-m-Y H:i:s') ?></p>
            <p>Productos distintos: <?= (int) $stats['productos_distintos'] ?> &nbsp;|&nbsp;
               Unidades vendidas: <?= number_format($stats['total_unidades'], 2) ?> &nbsp;|&nbsp;
               Total vendido: $<?= number_format($stats['total_vendido'], 2) ?></p>
        </div>
        <table>
            <thead>
                <tr><th>Ranking</th><th>Código</th><th>Producto</th><th>Cant. Vendida</th><th>N° Documentos</th><th>Total Vendido</th></tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td class="text-center">#<?= (int) ($r['ranking'] ?? 0) ?></td>
                        <td><?= htmlspecialchars($r['producto_codigo'] ?? '') ?></td>
                        <td><?= htmlspecialchars($r['producto_nombre'] ?? '') ?></td>
                        <td class="text-center"><?= number_format((float) $r['cantidad_vendida'], 2) ?></td>
                        <td class="text-center"><?= (int) $r['num_documentos'] ?></td>
                        <td class="text-end"><strong>$<?= number_format((float) $r['total_vendido'], 2) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background-color:#e9ecef;">
                    <th colspan="3" class="text-center">TOTALES:</th>
                    <th class="text-center"><?= number_format($stats['total_unidades'], 2) ?></th>
                    <th></th>
                    <th class="text-end" style="color:#198754;">$<?= number_format($stats['total_vendido'], 2) ?></th>
                </tr>
            </tfoot>
        </table>
        <?php
        return ob_get_clean();
    }

    private function guardarExcelEnArchivo(array $rows, array $stats, string $nombreEmpresa, string $path): void
    {
        $headers = ['Ranking', 'Código', 'Producto', 'Cant. Vendida', 'N° Documentos', 'Total Vendido'];
        $exportData = [];
        foreach ($rows as $r) {
            $exportData[] = [
                (int) $r['ranking'],
                $r['producto_codigo'],
                $r['producto_nombre'],
                (float) $r['cantidad_vendida'],
                (int) $r['num_documentos'],
                (float) $r['total_vendido'],
            ];
        }

        $reportService = new \App\Services\ReportService();
        $reportService->guardarExcelEnArchivo($headers, $exportData, 'Productos Más Vendidos', $nombreEmpresa, $path);
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros = $this->getFiltrosDesdeRequest();

        [$rows, ] = $this->obtenerDatos($idEmpresa, $filtros);

        try {
            $empresa = (new \App\models\Empresa())->getPorId($idEmpresa);
            $nombreEmpresa = $empresa['nombre'] ?? '';

            $headers = ['Ranking', 'Código', 'Producto', 'Cant. Vendida', 'N° Documentos', 'Total Vendido'];
            $exportData = [];
            foreach ($rows as $r) {
                $exportData[] = [
                    (int) $r['ranking'],
                    $r['producto_codigo'],
                    $r['producto_nombre'],
                    (float) $r['cantidad_vendida'],
                    (int) $r['num_documentos'],
                    (float) $r['total_vendido'],
                ];
            }

            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcel('Productos_Mas_Vendidos', $headers, $exportData, 'Productos Más Vendidos', $nombreEmpresa);
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

        [$rows, $stats] = $this->obtenerDatos($idEmpresa, $filtros);

        try {
            $empresa = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
            $nombreEmpresa = $empresa['nombre'] ?? 'REPORTE DE PRODUCTOS MÁS VENDIDOS';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) require_once $autoload;

            $html = $this->construirHtmlReporte($rows, $stats, $nombreEmpresa);
            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
            $html2pdf->writeHTML($html);
            $html2pdf->output('ProductosMasVendidos_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            echo "Error al generar PDF: " . $e->getMessage();
        }
    }

    /** Envía el reporte (con los filtros actuales) por correo, adjuntando PDF y/o Excel. */
    public function enviarCorreoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $filtros = $this->getFiltrosDesdeRequest();

            $correosRaw = trim($_POST['correos'] ?? '');
            $destinatarios = array_values(array_filter(
                array_map('trim', explode(',', $correosRaw)),
                fn($c) => filter_var($c, FILTER_VALIDATE_EMAIL) !== false
            ));

            if (empty($destinatarios)) {
                echo json_encode(['ok' => false, 'mensaje' => 'Ingrese al menos un correo válido.']);
                exit;
            }

            $adjuntar = $_POST['adjuntar'] ?? 'pdf'; // pdf | excel | ambos

            [$rows, $stats] = $this->obtenerDatos($idEmpresa, $filtros);

            $empresa = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
            $nombreEmpresa = $empresa['nombre'] ?? 'Reporte de Productos Más Vendidos';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) require_once $autoload;

            $adjuntosPaths = [];
            $tmpBase = sys_get_temp_dir() . '/pmv_' . $idEmpresa . '_' . time();

            if ($adjuntar === 'pdf' || $adjuntar === 'ambos') {
                $html = $this->construirHtmlReporte($rows, $stats, $nombreEmpresa);
                $pdfPath = $tmpBase . '.pdf';
                $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
                $html2pdf->writeHTML($html);
                $html2pdf->output($pdfPath, 'F');
                $adjuntosPaths[$pdfPath] = 'ProductosMasVendidos_' . date('Ymd') . '.pdf';
            }

            if ($adjuntar === 'excel' || $adjuntar === 'ambos') {
                $excelPath = $tmpBase . '.xlsx';
                $this->guardarExcelEnArchivo($rows, $stats, $nombreEmpresa, $excelPath);
                $adjuntosPaths[$excelPath] = 'ProductosMasVendidos_' . date('Ymd') . '.xlsx';
            }

            $asunto = 'Reporte: Productos Más Vendidos - ' . $nombreEmpresa;
            $cuerpo = '
                <div style="font-family:Arial,sans-serif;color:#333;max-width:600px;margin:auto;">
                    <h2 style="color:#2563eb;">Productos Más Vendidos</h2>
                    <p>Adjunto el reporte de productos más vendidos de <strong>' . htmlspecialchars($nombreEmpresa) . '</strong>.</p>
                    <table style="border-collapse:collapse;font-size:14px;">
                        <tr><td style="padding:4px 12px;color:#666;">Productos distintos</td><td style="padding:4px 12px;"><strong>' . (int) $stats['productos_distintos'] . '</strong></td></tr>
                        <tr><td style="padding:4px 12px;color:#666;">Unidades vendidas</td><td style="padding:4px 12px;">' . number_format($stats['total_unidades'], 2) . '</td></tr>
                        <tr><td style="padding:4px 12px;color:#666;">Total vendido</td><td style="padding:4px 12px;">$ ' . number_format($stats['total_vendido'], 2) . '</td></tr>
                        <tr><td style="padding:4px 12px;color:#666;">Producto más vendido</td><td style="padding:4px 12px;">' . htmlspecialchars($stats['top1_nombre']) . '</td></tr>
                    </table>
                    <p style="color:#888;font-size:12px;margin-top:24px;">Reporte generado el ' . date('d-m-Y H:i:s') . '.</p>
                </div>';

            $ok = enviar_correo_reporte($destinatarios, $asunto, $cuerpo, $adjuntosPaths);

            foreach (array_keys($adjuntosPaths) as $path) {
                if (file_exists($path)) {
                    unlink($path);
                }
            }

            echo json_encode($ok
                ? ['ok' => true, 'mensaje' => 'Reporte enviado a ' . implode(', ', $destinatarios)]
                : ['ok' => false, 'mensaje' => $GLOBALS['LAST_EMAIL_ERROR'] ?? 'No se pudo enviar el correo. Verifica la configuración de correo.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => 'Error al enviar correo: ' . $e->getMessage()]);
        }
        exit;
    }
}
