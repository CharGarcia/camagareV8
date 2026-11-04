<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\models\Empresa;
use App\repositories\modulos\ProduccionReporteRepository;
use App\Services\modulos\ProduccionConfiguracionService;

/**
 * Reportes de producción (modulos/produccion-reportes): producción por producto, consumo
 * de insumos (teórico vs. real), órdenes cerradas con costo y merma, y costo por mes. Solo documentos de
 * producción (AmbienteReporte). Sin acceso total, cada usuario ve solo sus órdenes.
 * Solo lectura: el controlador pide los datos al repositorio y los presenta.
 */
class ProduccionReportesController extends BaseModuloController
{
    private const RUTA_MODULO = 'modulos/produccion-reportes';

    public const PESTANAS = [
        'producido' => ['titulo' => 'Producción por producto', 'icono' => 'bi-box-seam'],
        'consumo'   => ['titulo' => 'Consumo de insumos',      'icono' => 'bi-box-arrow-up'],
        'ordenes'   => ['titulo' => 'Órdenes cerradas',        'icono' => 'bi-list-check'],
        'mes'       => ['titulo' => 'Costo por mes',           'icono' => 'bi-calendar3'],
    ];

    private ProduccionReporteRepository $repo;

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function __construct()
    {
        parent::__construct();
        $this->repo = new ProduccionReporteRepository();
    }

    private function filtros(): array
    {
        return [
            'desde'    => trim((string) ($_GET['desde'] ?? date('Y-m-01'))),
            'hasta'    => trim((string) ($_GET['hasta'] ?? date('Y-m-d'))),
            'producto' => trim((string) ($_GET['producto'] ?? '')),
        ];
    }

    private function idUsuarioFiltro(): ?int
    {
        return empty($this->getPermisos()['todo']) ? (int) $_SESSION['id_usuario'] : null;
    }

    private function pestana(): string
    {
        $p = (string) ($_GET['pestana'] ?? 'producido');
        return isset(self::PESTANAS[$p]) ? $p : 'producido';
    }

    /** Columnas de cada pestaña: [clave, título, tipo (txt|num|money|pct|fecha)]. */
    private static function columnas(string $pestana): array
    {
        return match ($pestana) {
            'consumo' => [
                ['codigo', 'Código', 'txt'], ['nombre', 'Insumo', 'txt'], ['medida', 'Unidad', 'txt'], ['ordenes', 'Órdenes', 'num'],
                ['teorico', 'Teórico', 'num'], ['real', 'Real', 'num'], ['diferencia', 'Diferencia', 'num'],
                ['diferencia_pct', 'Dif. %', 'pct'], ['costo', 'Costo', 'money'],
            ],
            'ordenes' => [
                ['numero', 'Número', 'txt'], ['fecha_fin', 'Cierre', 'fecha'], ['nombre', 'Producto', 'txt'], ['lote', 'Lote', 'txt'],
                ['cantidad_planificada', 'Planificado', 'num'], ['cantidad_producida', 'Producido', 'num'],
                ['rendimiento_pct', 'Rendim. %', 'pct'], ['cantidad_merma', 'Merma', 'num'],
                ['costo_materiales', 'Materiales', 'money'], ['costo_mano_obra', 'Mano obra', 'money'], ['costo_cif', 'CIF', 'money'],
                ['valor_subproductos', 'Subprod.', 'money'], ['costo_merma', 'Costo merma', 'money'],
                ['desvio_consumo', 'Desvío consumo', 'money'], ['costo_total', 'Costo total', 'money'],
            ],
            'mes' => [
                ['mes', 'Mes', 'txt'], ['ordenes', 'Órdenes', 'num'], ['producido', 'Producido', 'num'],
                ['costo_materiales', 'Materiales', 'money'], ['costo_mano_obra', 'Mano obra', 'money'], ['costo_cif', 'CIF', 'money'],
                ['valor_subproductos', 'Subprod.', 'money'], ['costo_merma', 'Costo merma', 'money'],
                ['costo_total', 'Costo total', 'money'], ['costo_unitario', 'Costo unit.', 'money'],
            ],
            default => [
                ['codigo', 'Código', 'txt'], ['nombre', 'Producto', 'txt'], ['rol', 'Tipo', 'txt'], ['medida', 'Unidad', 'txt'],
                ['ordenes', 'Órdenes', 'num'], ['teorico', 'Teórico', 'num'], ['real', 'Producido', 'num'],
                ['costo', 'Costo', 'money'], ['costo_unitario', 'Costo unit.', 'money'],
            ],
        };
    }

    private function datos(string $pestana): array
    {
        $idEmpresa = (int) $_SESSION['id_empresa'];
        if (!ProduccionConfiguracionService::crear()->get($idEmpresa)) {
            return [];
        }
        $f = $this->filtros();
        $rows = match ($pestana) {
            'consumo' => $this->repo->consumo($idEmpresa, $f, $this->idUsuarioFiltro()),
            'ordenes' => $this->repo->ordenes($idEmpresa, $f, $this->idUsuarioFiltro()),
            'mes'     => self::conTotal($this->repo->porMes($idEmpresa, $f, $this->idUsuarioFiltro())),
            default   => $this->repo->producido($idEmpresa, $f, $this->idUsuarioFiltro()),
        };
        $roles = ['principal' => 'Principal', 'coproducto' => 'Coproducto', 'subproducto' => 'Subproducto', 'desperdicio' => 'Desperdicio'];
        foreach ($rows as &$r) {
            if (isset($r['rol'])) {
                $r['rol'] = $roles[$r['rol']] ?? $r['rol'];
            }
        }
        unset($r);
        return $rows;
    }

    /**
     * Fila TOTAL de la pestaña por mes. Producido y costo unitario solo si todo el rango es
     * de un mismo producto (no se suman unidades de productos distintos).
     */
    private static function conTotal(array $rows): array
    {
        if ($rows === []) {
            return [];
        }
        $t = ['mes' => 'TOTAL', '_total' => true];
        foreach (['ordenes', 'costo_materiales', 'costo_mano_obra', 'costo_cif', 'valor_subproductos', 'costo_merma', 'costo_total'] as $k) {
            $t[$k] = array_sum(array_map('floatval', array_column($rows, $k)));
        }
        $unico = count(array_unique(array_column($rows, 'id_producto'))) === 1
            && max(array_map('intval', array_column($rows, 'productos'))) === 1;
        if ($unico) {
            $t['producido'] = array_sum(array_map('floatval', array_column($rows, 'producido')));
            $t['costo_unitario'] = $t['producido'] > 0 ? $t['costo_total'] / $t['producido'] : null;
        }
        $rows[] = $t;
        return $rows;
    }

    private static function celda($v, string $tipo): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        return match ($tipo) {
            'num'   => rtrim(rtrim(number_format((float) $v, 4, '.', ','), '0'), '.'),
            'money' => number_format((float) $v, 2, '.', ','),
            'pct'   => number_format((float) $v, 2, '.', ',') . ' %',
            'fecha' => date('d-m-Y', strtotime((string) $v)),
            default => htmlspecialchars((string) $v),
        };
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $config = ProduccionConfiguracionService::crear();
        $this->viewWithLayout('layouts.main', 'modulos/produccion_reportes/index', [
            'titulo'     => 'Reportes de producción',
            'perm'       => $this->getPermisos(),
            'rutaModulo' => self::RUTA_MODULO,
            'instalado'  => $config->instalado(),
            'config'     => $config->instalado() ? $config->get($idEmpresa) : null,
            'filtros'    => $this->filtros(),
            'pestanas'   => self::PESTANAS,
            'base'       => BASE_URL,
        ]);
    }

    public function datosAjax(): void
    {
        $this->requireLeer();
        $pestana = $this->pestana();
        $cols = self::columnas($pestana);
        $rows = $this->datos($pestana);

        $html = '<thead class="table-light"><tr>';
        foreach ($cols as [$k, $t, $tipo]) {
            $html .= '<th class="' . ($tipo === 'txt' || $tipo === 'fecha' ? '' : 'text-end') . '">' . $t . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        if ($rows === []) {
            $html .= '<tr><td colspan="' . count($cols) . '" class="text-center text-muted py-4">Sin órdenes cerradas en el período.</td></tr>';
        }
        foreach ($rows as $r) {
            $html .= empty($r['_total']) ? '<tr>' : '<tr class="fw-bold table-light">';
            foreach ($cols as [$k, , $tipo]) {
                $html .= '<td class="' . ($tipo === 'txt' || $tipo === 'fecha' ? '' : 'text-end') . '">' . self::celda($r[$k] ?? null, $tipo) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody>';

        $res = ProduccionConfiguracionService::crear()->get((int) $_SESSION['id_empresa'])
            ? $this->repo->resumen((int) $_SESSION['id_empresa'], $this->filtros(), $this->idUsuarioFiltro())
            : [];
        $this->json(['ok' => true, 'html' => $html, 'resumen' => $res]);
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        $pestana = $this->pestana();
        $cols = self::columnas($pestana);
        try {
            $data = [];
            foreach ($this->datos($pestana) as $r) {
                $fila = [];
                foreach ($cols as [$k, , $tipo]) {
                    $v = $r[$k] ?? null;
                    $fila[] = in_array($tipo, ['num', 'money', 'pct'], true) ? ($v === null ? null : (float) $v)
                        : ($tipo === 'fecha' && $v ? date('d-m-Y', strtotime((string) $v)) : (string) ($v ?? ''));
                }
                $data[] = $fila;
            }
            $empresa = (new Empresa())->getPorId((int) $_SESSION['id_empresa']) ?? [];
            (new \App\Services\ReportService())->exportToExcel(
                self::PESTANAS[$pestana]['titulo'],
                array_column($cols, 1),
                $data,
                'Produccion_' . $pestana,
                (string) ($empresa['nombre'] ?? '')
            );
        } catch (\Throwable $e) {
            echo 'Error al generar Excel: ' . htmlspecialchars($e->getMessage());
        }
        exit;
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $pestana = $this->pestana();
        $cols = self::columnas($pestana);
        $rows = $this->datos($pestana);
        $f = $this->filtros();
        try {
            $empresa = (new Empresa())->getPorId((int) $_SESSION['id_empresa']) ?? [];
            $ancho = round(100 / count($cols), 2) . '%';
            ob_start();
            ?>
            <style>
                table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 7pt; }
                th { background: #f2f2f2; border: 1px solid #ccc; padding: 3px; text-align: left; }
                td { border: 1px solid #ccc; padding: 3px; }
                .r { text-align: right; }
                h1 { margin: 0; font-size: 13pt; text-align: center; } h2 { margin: 3px 0 10px; font-size: 9pt; text-align: center; color: #666; }
            </style>
            <page backtop="10mm" backbottom="10mm" backleft="8mm" backright="8mm" orientation="landscape">
                <h1><?= htmlspecialchars((string) ($empresa['nombre'] ?? '')) ?></h1>
                <h2><?= htmlspecialchars(mb_strtoupper(self::PESTANAS[$pestana]['titulo'])) ?> · <?= date('d-m-Y', strtotime($f['desde'])) ?> al <?= date('d-m-Y', strtotime($f['hasta'])) ?></h2>
                <table>
                    <thead><tr><?php foreach ($cols as [, $t, $tipo]): ?><th style="width:<?= $ancho ?>" class="<?= in_array($tipo, ['txt', 'fecha'], true) ? '' : 'r' ?>"><?= $t ?></th><?php endforeach; ?></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr><?php foreach ($cols as [$k, , $tipo]): ?><td style="width:<?= $ancho ?><?= empty($r['_total']) ? '' : ';font-weight:bold;background:#f2f2f2' ?>" class="<?= in_array($tipo, ['txt', 'fecha'], true) ? '' : 'r' ?>"><?= self::celda($r[$k] ?? null, $tipo) ?></td><?php endforeach; ?></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </page>
            <?php
            $html = ob_get_clean();
            $pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $pdf->writeHTML($html);
            $pdf->output('Produccion_' . $pestana . '_' . date('Ymd_His') . '.pdf', 'D');
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo 'Error al generar PDF: ' . htmlspecialchars($e->getMessage());
        }
        exit;
    }
}
