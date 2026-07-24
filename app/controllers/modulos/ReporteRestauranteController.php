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
            'fullWidth'   => true,
        ]);
    }

    private function getFiltrosDesdeRequest(): array
    {
        return [
            'ver_por'      => $_REQUEST['ver_por'] ?? 'MESAS',
            'fecha_desde'  => $_REQUEST['fecha_desde'] ?? '',
            'fecha_hasta'  => $_REQUEST['fecha_hasta'] ?? '',
            'id_mesa'      => $_REQUEST['id_mesa'] ?? '',
            'id_usuario'   => $_REQUEST['id_usuario'] ?? '',
            'id_menu_item' => $_REQUEST['id_menu_item'] ?? '',
            'id_categoria' => $_REQUEST['id_categoria'] ?? '',
        ];
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
}
