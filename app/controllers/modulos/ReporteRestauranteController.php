<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\ReporteRestauranteRepository;

/**
 * Reportes del POS Restaurantes: ventas por mesa, por mesero, por ítem del
 * menú y por categoría del menú. Mismo patrón que ReportePosController: un
 * solo select "ver_por" que cambia de vista sin recargar la página.
 */
class ReporteRestauranteController extends BaseModuloController
{
    private ReporteRestauranteRepository $repository;

    protected function getRutaModulo(): string
    {
        return 'modulos/reporte-restaurante';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new ReporteRestauranteRepository();
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $this->viewWithLayout('layouts.main', 'modulos/reporte_restaurante/index', [
            'titulo'      => 'Reportes Restaurante',
            'perm'        => $this->getPermisos(),
            'rutaModulo'  => $this->getRutaModulo(),
            'mesas'       => $this->repository->getMesas($idEmpresa),
            'meseros'     => $this->repository->getMeseros($idEmpresa),
            'menuItems'   => $this->repository->getMenuItems($idEmpresa),
            'categorias'  => $this->repository->getCategoriasMenu($idEmpresa),
            'formasPago'  => $this->repository->getFormasPago($idEmpresa),
            // Ancho del papel de la tirilla (Configuración Restaurante): lo lee
            // el partial de estilos para ajustar el tamaño de letra.
            'anchoTirilla' => (new \App\Services\modulos\ConfiguracionRestauranteService())
                ->getAnchoTirilla($idEmpresa),
            'fullWidth'   => true,
        ]);
    }

    private function getFiltrosDesdeRequest(): array
    {
        return [
            'ver_por'        => $_REQUEST['ver_por'] ?? 'MESAS',
            'fecha_desde'    => $_REQUEST['fecha_desde'] ?? '',
            'fecha_hasta'    => $_REQUEST['fecha_hasta'] ?? '',
            'id_mesa'        => $_REQUEST['id_mesa'] ?? '',
            'id_usuario'     => $_REQUEST['id_usuario'] ?? '',
            'id_menu_item'   => $_REQUEST['id_menu_item'] ?? '',
            'id_categoria'   => $_REQUEST['id_categoria'] ?? '',
            'id_forma_pago'  => $_REQUEST['id_forma_pago'] ?? '',
        ];
    }

    /** Título legible de la vista activa, para encabezar la tirilla y el correo. */
    private function tituloVista(string $verPor): string
    {
        return match ($verPor) {
            'MESERO'    => 'Ventas por mesero',
            'MENU'      => 'Ítems del menú más vendidos',
            'CATEGORIA' => 'Ventas por categoría',
            default     => 'Ventas por mesa',
        };
    }

    /**
     * Filtros aplicados, en texto, para que la tirilla y el correo digan de qué
     * es el reporte. Solo se listan los que el usuario realmente puso.
     */
    private function resumenFiltros(int $idEmpresa, array $filtros): array
    {
        $nombrePorId = static function (array $lista, $id): string {
            foreach ($lista as $x) {
                if ((int) $x['id'] === (int) $id) {
                    return (string) $x['nombre'];
                }
            }
            return '';
        };

        $resumen = [];
        if (!empty($filtros['fecha_desde']) || !empty($filtros['fecha_hasta'])) {
            $desde = $filtros['fecha_desde'] ? date('d-m-Y', strtotime($filtros['fecha_desde'])) : '—';
            $hasta = $filtros['fecha_hasta'] ? date('d-m-Y', strtotime($filtros['fecha_hasta'])) : '—';
            $resumen['Periodo'] = $desde . ' a ' . $hasta;
        }
        if (!empty($filtros['id_mesa'])) {
            $resumen['Mesa'] = $nombrePorId($this->repository->getMesas($idEmpresa), $filtros['id_mesa']);
        }
        if (!empty($filtros['id_usuario'])) {
            $resumen['Mesero'] = $nombrePorId($this->repository->getMeseros($idEmpresa), $filtros['id_usuario']);
        }
        if (!empty($filtros['id_menu_item'])) {
            $resumen['Ítem'] = $nombrePorId($this->repository->getMenuItems($idEmpresa), $filtros['id_menu_item']);
        }
        if (!empty($filtros['id_categoria'])) {
            $resumen['Categoría'] = $nombrePorId($this->repository->getCategoriasMenu($idEmpresa), $filtros['id_categoria']);
        }
        if (!empty($filtros['id_forma_pago'])) {
            $resumen['Forma de pago'] = $nombrePorId($this->repository->getFormasPago($idEmpresa), $filtros['id_forma_pago']);
        }

        return array_filter($resumen, fn($v) => $v !== '');
    }

    private function getRows(int $idEmpresa, array $filtros): array
    {
        return match ($filtros['ver_por']) {
            'MESERO'     => $this->repository->getVentasPorMesero($idEmpresa, $filtros),
            'MENU'       => $this->repository->getVentasPorMenu($idEmpresa, $filtros),
            'CATEGORIA'  => $this->repository->getVentasPorCategoria($idEmpresa, $filtros),
            default      => $this->repository->getVentasPorMesa($idEmpresa, $filtros),
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
                echo '<tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-graph-up fs-3 d-block mb-2"></i>No se encontraron resultados.</td></tr>';
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
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            error_log('[ReporteRestaurante] ' . $e->getMessage());
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function renderFilaHtml(array $r, string $verPor): string
    {
        $money = fn($v) => number_format((float) ($v ?? 0), 2);
        $html = '<tr class="align-middle">';

        if ($verPor === 'MESERO') {
            $html .= "<td>" . htmlspecialchars($r['mesero_nombre'] ?? '') . "</td>";
            $html .= "<td class='text-center'>" . (int) ($r['cantidad_comandas'] ?? 0) . "</td>";
            $html .= "<td class='text-center'>" . (int) ($r['cantidad_documentos'] ?? 0) . "</td>";
            $html .= "<td class='text-end fw-bold text-success'>$" . $money($r['total']) . "</td>";
        } elseif ($verPor === 'MENU') {
            $html .= "<td><span class='fw-bold'>" . htmlspecialchars($r['item_nombre'] ?? '') . "</span></td>";
            $html .= "<td>" . htmlspecialchars($r['categoria_nombre'] ?? '') . "</td>";
            $html .= "<td class='text-center'>" . (float) ($r['cantidad_vendida'] ?? 0) . "</td>";
            $html .= "<td class='text-end fw-bold text-success'>$" . $money($r['total']) . "</td>";
        } elseif ($verPor === 'CATEGORIA') {
            $html .= "<td><span class='fw-bold'>" . htmlspecialchars($r['categoria_nombre'] ?? '') . "</span></td>";
            $html .= "<td class='text-center'>" . (float) ($r['cantidad_vendida'] ?? 0) . "</td>";
            $html .= "<td class='text-end fw-bold text-success'>$" . $money($r['total']) . "</td>";
        } else {
            // MESAS
            $html .= "<td><span class='fw-bold'>" . htmlspecialchars($r['mesa_nombre'] ?? '') . "</span></td>";
            $html .= "<td>" . htmlspecialchars($r['ubicacion'] ?? '') . "</td>";
            $html .= "<td class='text-center'>" . (int) ($r['cantidad_comandas'] ?? 0) . "</td>";
            $html .= "<td class='text-center'>" . (int) ($r['cantidad_documentos'] ?? 0) . "</td>";
            $html .= "<td class='text-end fw-bold text-success'>$" . $money($r['total']) . "</td>";
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
            $reportService->exportToExcel('ReporteRestaurante', $headers, $exportData, 'Reporte_Restaurante', $nombreEmpresa);
            exit;
        } catch (\Throwable $e) {
            echo "Error al generar Excel: " . $e->getMessage();
        }
    }

    private function armarExportacion(array $rows, string $verPor): array
    {
        if ($verPor === 'MESERO') {
            $headers = ['Mesero', 'Comandas', 'Documentos', 'Total'];
            $exportData = array_map(fn($r) => [
                $r['mesero_nombre'], (int) $r['cantidad_comandas'], (int) $r['cantidad_documentos'], (float) $r['total'],
            ], $rows);
        } elseif ($verPor === 'MENU') {
            $headers = ['Ítem', 'Categoría', 'Cant. Vendida', 'Total'];
            $exportData = array_map(fn($r) => [
                $r['item_nombre'], $r['categoria_nombre'], (float) $r['cantidad_vendida'], (float) $r['total'],
            ], $rows);
        } elseif ($verPor === 'CATEGORIA') {
            $headers = ['Categoría', 'Cant. Vendida', 'Total'];
            $exportData = array_map(fn($r) => [
                $r['categoria_nombre'], (float) $r['cantidad_vendida'], (float) $r['total'],
            ], $rows);
        } else {
            $headers = ['Mesa', 'Ubicación', 'Comandas', 'Documentos', 'Total'];
            $exportData = array_map(fn($r) => [
                $r['mesa_nombre'], $r['ubicacion'], (int) $r['cantidad_comandas'], (int) $r['cantidad_documentos'], (float) $r['total'],
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
            $nombreEmpresa = $empresa['nombre'] ?? 'REPORTE RESTAURANTE';

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
                <h3>Reporte Restaurante</h3>
                <p>Fecha de reporte: <?= date('d-m-Y H:i:s') ?></p>
                <p>Comandas: <?= (int) $stats['cantidad_comandas'] ?> — Documentos: <?= (int) $stats['cantidad_documentos'] ?> — Total: $<?= number_format((float) $stats['total_vendido'], 2) ?></p>
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
            $html2pdf->output('ReporteRestaurante_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            echo "Error al generar PDF: " . $e->getMessage();
        }
    }

    /**
     * Tirilla del reporte: la misma vista, pero en papel térmico, para dejarla
     * en la caja al cerrar el turno sin tener que abrir el PDF. Devuelve una
     * página completa que se imprime sola al abrirse, igual que las tirillas del
     * POS y de las comandas.
     */
    public function imprimirTirilla(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros   = $this->getFiltrosDesdeRequest();

        $rows  = $this->getRows($idEmpresa, $filtros);
        $stats = $this->repository->getEstadisticas($idEmpresa, $filtros);

        $empresa = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];

        $this->view('modulos/reporte_restaurante/tirilla', [
            'empresa'       => $empresa,
            'titulo'        => $this->tituloVista($filtros['ver_por']),
            'resumen'       => $this->resumenFiltros($idEmpresa, $filtros),
            'filas'         => $this->armarFilasTirilla($rows, $filtros['ver_por']),
            'stats'         => $stats,
            'anchoTirilla'  => (new \App\Services\modulos\ConfiguracionRestauranteService())
                ->getAnchoTirilla($idEmpresa),
        ]);
    }

    /**
     * Reduce cada fila del reporte a "concepto + detalle + importe": en 58 u 80
     * mm no caben las cuatro o cinco columnas de la pantalla, así que el dato
     * secundario (ubicación, categoría, número de comandas) va como sublínea.
     *
     * @return list<array{concepto:string, detalle:string, total:float}>
     */
    private function armarFilasTirilla(array $rows, string $verPor): array
    {
        $num = static fn($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');

        return array_map(static function (array $r) use ($verPor, $num): array {
            if ($verPor === 'MESERO') {
                return [
                    'concepto' => (string) ($r['mesero_nombre'] ?? ''),
                    'detalle'  => (int) ($r['cantidad_comandas'] ?? 0) . ' comanda(s) — '
                                . (int) ($r['cantidad_documentos'] ?? 0) . ' doc.',
                    'total'    => (float) ($r['total'] ?? 0),
                ];
            }
            if ($verPor === 'MENU') {
                return [
                    'concepto' => (string) ($r['item_nombre'] ?? ''),
                    'detalle'  => trim(($r['categoria_nombre'] ?? '') . ' — ' . $num($r['cantidad_vendida'] ?? 0) . ' u.', ' —'),
                    'total'    => (float) ($r['total'] ?? 0),
                ];
            }
            if ($verPor === 'CATEGORIA') {
                return [
                    'concepto' => (string) ($r['categoria_nombre'] ?? ''),
                    'detalle'  => $num($r['cantidad_vendida'] ?? 0) . ' u.',
                    'total'    => (float) ($r['total'] ?? 0),
                ];
            }
            return [
                'concepto' => (string) ($r['mesa_nombre'] ?? ''),
                'detalle'  => trim(($r['ubicacion'] ?? '') . ' — '
                            . (int) ($r['cantidad_comandas'] ?? 0) . ' comanda(s)', ' —'),
                'total'    => (float) ($r['total'] ?? 0),
            ];
        }, $rows);
    }

    /** Envía el reporte (con los filtros actuales) por correo, con el PDF adjunto. */
    public function enviarCorreoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $filtros   = $this->getFiltrosDesdeRequest();

            $destinatarios = array_values(array_filter(
                array_map('trim', explode(',', trim($_POST['correos'] ?? ''))),
                fn($c) => filter_var($c, FILTER_VALIDATE_EMAIL) !== false
            ));

            if (empty($destinatarios)) {
                echo json_encode(['ok' => false, 'mensaje' => 'Ingrese al menos un correo válido.']);
                exit;
            }

            $rows = $this->getRows($idEmpresa, $filtros);
            if (empty($rows)) {
                echo json_encode(['ok' => false, 'mensaje' => 'El reporte no tiene resultados: no hay nada que enviar.']);
                exit;
            }

            $stats   = $this->repository->getEstadisticas($idEmpresa, $filtros);
            $empresa = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
            $nombreEmpresa = $empresa['nombre'] ?? 'Reporte Restaurante';
            $tituloVista   = $this->tituloVista($filtros['ver_por']);

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) require_once $autoload;

            // El PDF se arma con la MISMA tabla que descarga el botón PDF, para
            // que lo que llega por correo y lo que se descarga no difieran.
            [$headers, $exportData] = $this->armarExportacion($rows, $filtros['ver_por']);

            $adjuntos = [];
            $pdfPath  = sys_get_temp_dir() . '/rres_' . $idEmpresa . '_' . time() . '.pdf';
            try {
                $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
                $html2pdf->writeHTML($this->construirHtmlPdf($nombreEmpresa, $tituloVista, $headers, $exportData, $stats));
                $html2pdf->output($pdfPath, 'F');
                $adjuntos[$pdfPath] = 'ReporteRestaurante_' . date('Ymd') . '.pdf';
            } catch (\Throwable $e) {
                // Sin PDF el correo igual sale: el resumen del cuerpo ya es útil.
                error_log('[ReporteRestaurante] PDF del correo no generado: ' . $e->getMessage());
            }

            $filasResumen = '';
            foreach ($this->resumenFiltros($idEmpresa, $filtros) as $etiqueta => $valor) {
                $filasResumen .= '<tr><td style="padding:4px 12px;color:#666;">' . htmlspecialchars($etiqueta)
                              . '</td><td style="padding:4px 12px;">' . htmlspecialchars($valor) . '</td></tr>';
            }

            $cuerpo = '
                <div style="font-family:Arial,sans-serif;color:#333;max-width:600px;margin:auto;">
                    <h2 style="color:#2563eb;">' . htmlspecialchars($tituloVista) . '</h2>
                    <p>Adjunto el reporte del restaurante de <strong>' . htmlspecialchars($nombreEmpresa) . '</strong>.</p>
                    <table style="border-collapse:collapse;font-size:14px;">
                        ' . $filasResumen . '
                        <tr><td style="padding:4px 12px;color:#666;">Comandas</td><td style="padding:4px 12px;"><strong>' . (int) ($stats['cantidad_comandas'] ?? 0) . '</strong></td></tr>
                        <tr><td style="padding:4px 12px;color:#666;">Documentos</td><td style="padding:4px 12px;">' . (int) ($stats['cantidad_documentos'] ?? 0) . '</td></tr>
                        <tr><td style="padding:4px 12px;color:#666;">Total vendido</td><td style="padding:4px 12px;">$ ' . number_format((float) ($stats['total_vendido'] ?? 0), 2) . '</td></tr>
                    </table>
                    <p style="color:#888;font-size:12px;margin-top:24px;">Reporte generado el ' . date('d-m-Y H:i:s') . '.</p>
                </div>';

            $ok = enviar_correo_reporte(
                $destinatarios,
                'Reporte Restaurante: ' . $tituloVista . ' - ' . $nombreEmpresa,
                $cuerpo,
                $adjuntos
            );

            foreach (array_keys($adjuntos) as $path) {
                if (file_exists($path)) {
                    unlink($path);
                }
            }

            echo json_encode($ok
                ? ['ok' => true, 'mensaje' => 'Reporte enviado a ' . implode(', ', $destinatarios)]
                : ['ok' => false, 'mensaje' => $GLOBALS['LAST_EMAIL_ERROR'] ?? 'No se pudo enviar el correo. Verifica la configuración de correo.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    /** Tabla del reporte en HTML, compartida por el PDF que se descarga y el que se envía por correo. */
    private function construirHtmlPdf(string $nombreEmpresa, string $tituloVista, array $headers, array $exportData, array $stats): string
    {
        ob_start();
        ?>
        <style>
            table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 9pt; margin: 0 auto 20px auto; }
            th { background: #f2f2f2; border: 1px solid #ccc; padding: 6px; text-align: center; }
            td { border: 1px solid #ccc; padding: 6px; }
            .text-end { text-align: right; }
            .header { text-align: center; margin-bottom: 20px; }
        </style>
        <div class="header">
            <h2><?= htmlspecialchars($nombreEmpresa) ?></h2>
            <h3><?= htmlspecialchars($tituloVista) ?></h3>
            <p>Fecha de reporte: <?= date('d-m-Y H:i:s') ?></p>
            <p>Comandas: <?= (int) ($stats['cantidad_comandas'] ?? 0) ?> — Documentos: <?= (int) ($stats['cantidad_documentos'] ?? 0) ?> — Total: $<?= number_format((float) ($stats['total_vendido'] ?? 0), 2) ?></p>
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
        return (string) ob_get_clean();
    }
}
