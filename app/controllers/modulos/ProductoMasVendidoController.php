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

        // Clic en la fila: abre el modal con los clientes que compraron ese producto. Un ítem libre
        // (sin producto del catálogo) se identifica por su descripción, como en el ranking.
        $html = '<tr class="align-middle pmv-fila-producto" role="button" title="Ver clientes que lo compraron"'
            . ' data-id-producto="' . ($r['id_producto'] !== null ? (int) $r['id_producto'] : '') . '"'
            . ' data-nombre="' . htmlspecialchars($r['producto_nombre'] ?? '') . '"'
            . ' data-codigo="' . htmlspecialchars($r['producto_codigo'] ?? '') . '"'
            . ' onclick="window.PMV_verDetalle(this)">';
        $html .= "<td class='text-center'><span class='badge border {$badgeColor}'>#{$ranking}</span></td>";
        $html .= "<td><span class='fw-bold'>" . htmlspecialchars($r['producto_nombre'] ?? '') . "</span><br><small class='text-muted'>" . htmlspecialchars($r['producto_codigo'] ?? '') . "</small></td>";
        $html .= "<td class='text-center fw-bold'>{$cantidad}</td>";
        $html .= "<td class='text-center'>" . (int) ($r['num_documentos'] ?? 0) . "</td>";
        $html .= "<td class='text-end fw-bold text-success'>\${$total}</td>";
        $html .= '</tr>';

        return $html;
    }

    /** Producto del modal de detalle: id del catálogo o, para un ítem libre, su descripción. */
    private function productoDetalleDesdeRequest(): array
    {
        $id = trim((string) ($_REQUEST['det_id_producto'] ?? ''));
        $nombre = trim((string) ($_REQUEST['det_nombre'] ?? ''));
        if ($id === '' && $nombre === '') {
            throw new \RuntimeException('Producto no válido.');
        }
        return [$id !== '' ? (int) $id : null, $nombre];
    }

    /** Clientes que compraron un producto con los filtros actuales del reporte (modal de detalle). */
    public function detalleClientesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            [$idProducto, $nombre] = $this->productoDetalleDesdeRequest();
            echo json_encode([
                'ok'   => true,
                'rows' => $this->repository->getClientesProducto((int) $_SESSION['id_empresa'], $this->getFiltrosDesdeRequest(), $idProducto, $nombre),
            ]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function fechaTxt(string $f): string
    {
        return ($t = strtotime($f)) ? date('d-m-Y', $t) : $f;
    }

    private function periodoTxt(array $filtros): string
    {
        if ($filtros['fecha_desde'] === '' && $filtros['fecha_hasta'] === '') {
            return 'Todo el período';
        }
        return 'Período del ' . $this->fechaTxt($filtros['fecha_desde']) . ' al ' . $this->fechaTxt($filtros['fecha_hasta']);
    }

    private function tipoDocumentoTxt(array $filtros): string
    {
        return match ($filtros['tipo_documento']) {
            'RECIBO' => 'Recibos de venta',
            'AMBOS'  => 'Facturas y Recibos de venta',
            default  => 'Facturas de venta',
        };
    }

    /**
     * Datos de las exportaciones del detalle de un producto: los mismos clientes del modal y,
     * si el usuario escribió algo en su buscador, solo los que coinciden (todas las palabras,
     * sin distinguir mayúsculas), para descargar lo que está viendo.
     */
    private function datosDetalleExport(): array
    {
        [$idProducto, $nombre] = $this->productoDetalleDesdeRequest();
        $filtros = $this->getFiltrosDesdeRequest();
        $rows = $this->repository->getClientesProducto((int) $_SESSION['id_empresa'], $filtros, $idProducto, $nombre);

        $buscar = trim((string) ($_REQUEST['buscar'] ?? ''));
        if ($buscar !== '') {
            $palabras = preg_split('/\s+/u', mb_strtolower($buscar));
            $rows = array_values(array_filter($rows, static function (array $r) use ($palabras): bool {
                $texto = mb_strtolower($r['cliente_ruc'] . ' ' . $r['cliente_nombre']);
                foreach ($palabras as $p) {
                    if (!str_contains($texto, $p)) {
                        return false;
                    }
                }
                return true;
            }));
        }

        $producto = [
            'nombre' => $nombre,
            'codigo' => trim((string) ($_REQUEST['det_codigo'] ?? '')),
        ];

        return [$filtros, $producto, $rows, $buscar];
    }

    public function exportDetalleExcel(): void
    {
        $this->requireLeer();

        try {
            [$filtros, $producto, $rows, $buscar] = $this->datosDetalleExport();
            $empresa = (new \App\models\Empresa())->getPorId((int) $_SESSION['id_empresa']) ?? [];

            $headers = ['RUC/Cédula', 'Cliente', 'Cant. Vendida', 'N° Documentos', 'Total Vendido', '% del Producto'];
            $data = [];
            foreach ($rows as $r) {
                $data[] = [$r['cliente_ruc'], $r['cliente_nombre'], $r['cantidad_vendida'], $r['num_documentos'],
                           $r['total_vendido'], $r['pct']];
            }
            $data[] = ['', 'TOTAL', array_sum(array_column($rows, 'cantidad_vendida')), '',
                       array_sum(array_column($rows, 'total_vendido')), ''];

            $info = [
                'Producto'   => ($producto['codigo'] !== '' ? $producto['codigo'] . ' - ' : '') . $producto['nombre'],
                'Período'    => $this->periodoTxt($filtros),
                'Documentos' => $this->tipoDocumentoTxt($filtros),
            ];
            if ($buscar !== '') {
                $info['Filtro'] = $buscar;
            }

            (new \App\Services\ReportService())->exportToExcel('ProductoMasVendido_Clientes', $headers, $data,
                'Clientes', ($empresa['nombre'] ?? '') . ' - Clientes que compraron el producto', $info);
            exit;
        } catch (\Throwable $e) {
            echo 'Error al generar Excel: ' . $e->getMessage();
        }
    }

    public function exportDetallePdf(): void
    {
        $this->requireLeer();

        try {
            [$filtros, $producto, $rows, $buscar] = $this->datosDetalleExport();
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $empresa = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];

            $pdf = \App\Helpers\ReportePdf::class;
            $pt  = 8.0;
            $wCliente = 41.0;
            $anchoCli = $pdf::anchoPt($wCliente, false);

            $filtrosTxt = [
                'Producto'   => $producto['nombre'],
                'Código'     => $producto['codigo'] !== '' ? $producto['codigo'] : '—',
                'Desde'      => $this->fechaTxt($filtros['fecha_desde']),
                'Hasta'      => $this->fechaTxt($filtros['fecha_hasta']),
                'Documentos' => $this->tipoDocumentoTxt($filtros),
            ];
            if ($buscar !== '') {
                $filtrosTxt['Filtro'] = $buscar;
            }

            $suma = static fn (string $k): \Closure => static fn (array $fs): float => array_sum(array_column($fs, $k));
            $cols = [
                ['lbl' => 'RUC / Cédula', 'w' => 15.0,
                 'val' => static fn (array $r): string => htmlspecialchars((string) $r['cliente_ruc'])],
                ['lbl' => 'Cliente', 'w' => $wCliente,
                 'val' => static fn (array $r): string => $pdf::texto((string) $r['cliente_nombre'], $anchoCli, $pt)],
                ['lbl' => 'Cant. vendida', 'w' => 12.0, 'cls' => 'text-end',
                 'val' => static fn (array $r): string => number_format((float) $r['cantidad_vendida'], 2),
                 'tot' => $suma('cantidad_vendida')],
                ['lbl' => 'Docs.', 'w' => 8.0, 'cls' => 'text-center',
                 'val' => static fn (array $r): string => (string) (int) $r['num_documentos']],
                ['lbl' => 'Total vendido', 'w' => 13.0, 'cls' => 'text-end',
                 'val' => static fn (array $r): string => '<b>' . number_format((float) $r['total_vendido'], 2) . '</b>',
                 'tot' => $suma('total_vendido')],
                ['lbl' => '% producto', 'w' => 11.0, 'cls' => 'text-end',
                 'val' => static fn (array $r): string => number_format((float) $r['pct'], 2) . '%'],
            ];

            $n = count($rows);

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }
            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
            // Por bloques: con un solo writeHTML el tiempo crece al cuadrado de las filas.
            $pdf::escribir($html2pdf, $pt,
                $pdf::encabezado($idEmpresa, $empresa['nombre'] ?? '', 'Clientes que compraron el producto',
                    $producto['nombre'] . ' · ' . $this->periodoTxt($filtros))
                . $pdf::filtros($filtrosTxt),
                $cols, $rows, 'TOTAL (' . $n . ' ' . ($n === 1 ? 'cliente' : 'clientes') . ')',
                'No se encontraron clientes para este producto.');
            $html2pdf->output('ProductoMasVendido_Clientes_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
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
    /**
     * Escribe el PDF del ranking (descarga y correo) con el formato común de los reportes
     * (App\Helpers\ReportePdf), por bloques: con "Todos" puede traer miles de productos y,
     * en un solo writeHTML, el tiempo crece al cuadrado de las filas. Los totales son los
     * de todos los productos del período, aunque la tabla muestre solo el top elegido.
     */
    private function escribirReporte(\Spipu\Html2Pdf\Html2Pdf $html2pdf, array $rows, array $stats, string $nombreEmpresa): void
    {
        $pdf = \App\Helpers\ReportePdf::class;
        $pt  = 8.0;
        $anchoProd = $pdf::anchoPt(40.0, false);
        $cols = [
            ['lbl' => 'Ranking', 'w' => 9.0, 'cls' => 'text-center',
             'val' => static fn (array $r): string => '#' . (int) ($r['ranking'] ?? 0)],
            ['lbl' => 'Código', 'w' => 15.0,
             'val' => static fn (array $r): string => htmlspecialchars((string) ($r['producto_codigo'] ?? ''))],
            ['lbl' => 'Producto', 'w' => 40.0,
             'val' => static fn (array $r): string => $pdf::texto((string) ($r['producto_nombre'] ?? ''), $anchoProd, $pt)],
            ['lbl' => 'Cant. Vendida', 'w' => 13.0, 'cls' => 'text-end',
             'val' => static fn (array $r): string => number_format((float) $r['cantidad_vendida'], 2),
             'tot' => static fn (): float => (float) $stats['total_unidades']],
            ['lbl' => 'N° Documentos', 'w' => 10.0, 'cls' => 'text-center',
             'val' => static fn (array $r): string => (string) (int) $r['num_documentos']],
            ['lbl' => 'Total Vendido', 'w' => 13.0, 'cls' => 'text-end',
             'val' => static fn (array $r): string => '<b>' . number_format((float) $r['total_vendido'], 2) . '</b>',
             'tot' => static fn (): float => (float) $stats['total_vendido']],
        ];
        $kpis = [
            ['PRODUCTOS DISTINTOS', (string) (int) $stats['productos_distintos']],
            ['UNIDADES VENDIDAS', number_format((float) $stats['total_unidades'], 2)],
            ['TOTAL VENDIDO', '$' . number_format((float) $stats['total_vendido'], 2), true],
        ];
        $etiqueta = count($rows) < (int) $stats['productos_distintos'] ? 'TOTALES (todos los productos)' : 'TOTALES';
        $pdf::escribir($html2pdf, $pt,
            $pdf::encabezado((int) $_SESSION['id_empresa'], $nombreEmpresa, 'Reporte de Productos Más Vendidos')
            . $pdf::indicadores($kpis),
            $cols, $rows, $etiqueta, 'No se encontraron ventas para los filtros aplicados.');
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

            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
            $this->escribirReporte($html2pdf, $rows, $stats, $nombreEmpresa);
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
        // Soltar el candado de la sesión antes de armar el reporte y enviarlo por correo: si no, las demás
        // peticiones del usuario hacen fila hasta que termine.
        session_write_close();
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
                $pdfPath = $tmpBase . '.pdf';
                $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
                $this->escribirReporte($html2pdf, $rows, $stats, $nombreEmpresa);
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
