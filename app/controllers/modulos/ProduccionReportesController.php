<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Helpers\ColumnasReporte;
use App\Helpers\ReportePdf;
use App\models\Empresa;
use App\repositories\modulos\ProduccionReporteRepository;
use App\Services\modulos\ProduccionConfiguracionService;
use App\Services\modulos\ReportePersonalizacionService;

/**
 * Reportes de producción (modulos/produccion-reportes): producción por producto, consumo
 * de insumos (teórico vs. real), órdenes cerradas con costo y merma, y costo por mes. Solo documentos de
 * producción (AmbienteReporte). Sin acceso total, cada usuario ve solo sus órdenes.
 *
 * Cada pestaña es una vista de ColumnasReporte::CATALOGO: pantalla, Excel y PDF se pintan
 * recorriendo lo que resuelve ReportePersonalizacionService (botón «Personalizar», CLAUDE.md §9).
 * El PDF usa las piezas comunes de ReportePdf (logo, filtros aplicados, indicadores, listado).
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

    /** Formato de cada columna (las del catálogo de ColumnasReporte): txt | num | money | pct | fecha. */
    private const FORMATO = [
        'ordenes' => 'num', 'teorico' => 'num', 'real' => 'num', 'diferencia' => 'num', 'producido' => 'num',
        'cantidad_planificada' => 'num', 'cantidad_producida' => 'num', 'cantidad_merma' => 'num',
        'diferencia_pct' => 'pct', 'rendimiento_pct' => 'pct',
        'costo' => 'money', 'costo_unitario' => 'money', 'costo_materiales' => 'money', 'costo_mano_obra' => 'money',
        'costo_cif' => 'money', 'valor_subproductos' => 'money', 'costo_merma' => 'money', 'desvio_consumo' => 'money',
        'costo_total' => 'money',
        'fecha_fin' => 'fecha',
    ];

    /** Columnas que se suman en la fila de totales del PDF (las de importe y el conteo de órdenes). */
    private const SUMAN = [
        'ordenes', 'costo', 'costo_materiales', 'costo_mano_obra', 'costo_cif', 'valor_subproductos', 'costo_merma',
        'desvio_consumo', 'costo_total',
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
        $fecha = static fn(string $v, string $def): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : $def;
        return [
            'desde'       => $fecha(trim((string) ($_GET['desde'] ?? '')), date('Y-m-01')),
            'hasta'       => $fecha(trim((string) ($_GET['hasta'] ?? '')), date('Y-m-d')),
            'id_producto' => max(0, (int) ($_GET['id_producto'] ?? 0)),
            'id_insumo'   => max(0, (int) ($_GET['id_insumo'] ?? 0)),
            'responsable' => mb_substr(trim((string) ($_GET['responsable'] ?? '')), 0, 100),
            'id_formula'  => max(0, (int) ($_GET['id_formula'] ?? 0)),
            'id_bodega'   => max(0, (int) ($_GET['id_bodega'] ?? 0)),
            // Filtro por usuario solo con acceso total: sin él ya ve únicamente sus órdenes.
            'id_usuario'  => empty($this->getPermisos()['todo']) ? 0 : max(0, (int) ($_GET['id_usuario'] ?? 0)),
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

    /** Columnas de la vista ya resueltas para la empresa (pantalla / excel / pdf + orientación). */
    private function columnas(string $pestana): array
    {
        return ReportePersonalizacionService::crear()->resolver((int) $_SESSION['id_empresa'], self::RUTA_MODULO, $pestana);
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

    private static function celda($v, string $clave): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        return match (self::FORMATO[$clave] ?? 'txt') {
            'num'   => rtrim(rtrim(number_format((float) $v, 4, '.', ','), '0'), '.'),
            'money' => number_format((float) $v, 2, '.', ','),
            'pct'   => number_format((float) $v, 2, '.', ',') . ' %',
            'fecha' => date('d-m-Y', strtotime((string) $v)),
            default => htmlspecialchars((string) $v),
        };
    }

    /** Descripción legible de los filtros aplicados (caja del PDF y encabezado del Excel). */
    private function filtrosTexto(array $f): array
    {
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $out = ['Período (cierre)' => date('d-m-Y', strtotime($f['desde'])) . ' al ' . date('d-m-Y', strtotime($f['hasta']))];
        if ($f['id_formula']) {
            foreach ($this->repo->formulasParaFiltro($idEmpresa) as $x) {
                if ((int) $x['id'] === $f['id_formula']) {
                    $out['Fórmula'] = $x['codigo'] . ' v' . $x['version'] . ' · ' . $x['nombre'];
                }
            }
        }
        if ($f['id_bodega']) {
            foreach (ProduccionConfiguracionService::crear()->repo()->getBodegas($idEmpresa) as $b) {
                if ((int) $b['id'] === $f['id_bodega']) {
                    $out['Bodega de destino'] = $b['nombre'];
                }
            }
        }
        if ($f['id_usuario']) {
            foreach ((new \App\repositories\modulos\ProduccionOrdenRepository())->getUsuariosConOrdenes($idEmpresa) as $u) {
                if ((int) $u['id'] === $f['id_usuario']) {
                    $out['Usuario'] = $u['nombre'];
                }
            }
        }
        if ($f['id_producto']) {
            foreach ($this->repo->productosParaFiltro($idEmpresa) as $p) {
                if ((int) $p['id'] === $f['id_producto']) {
                    $out['Producto fabricado'] = $p['codigo'] . ' - ' . $p['nombre'];
                }
            }
        }
        if ($f['id_insumo']) {
            foreach ($this->repo->insumosParaFiltro($idEmpresa) as $p) {
                if ((int) $p['id'] === $f['id_insumo']) {
                    $out['Insumo'] = $p['codigo'] . ' - ' . $p['nombre'];
                }
            }
        }
        foreach (['responsable' => 'Responsable'] as $k => $lbl) {
            if ($f[$k] !== '') {
                $out[$lbl] = $f[$k];
            }
        }
        return $out;
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $config = ProduccionConfiguracionService::crear();
        $instalado = $config->instalado();
        $cfg = $instalado ? $config->get($idEmpresa) : null;
        $this->viewWithLayout('layouts.main', 'modulos/produccion_reportes/index', [
            'titulo'     => 'Reportes de producción',
            'fullWidth'  => true,
            'perm'       => $this->getPermisos(),
            'rutaModulo' => self::RUTA_MODULO,
            'instalado'  => $instalado,
            'config'     => $cfg,
            'filtros'    => $this->filtros(),
            'pestanas'   => self::PESTANAS,
            // Catálogos de los filtros.
            'formulas'   => $cfg ? $this->repo->formulasParaFiltro($idEmpresa) : [],
            'productos'  => $cfg ? $this->repo->productosParaFiltro($idEmpresa) : [],
            'insumos'    => $cfg ? $this->repo->insumosParaFiltro($idEmpresa) : [],
            'bodegas'    => $cfg ? $config->repo()->getBodegas($idEmpresa) : [],
            'usuarios'   => $cfg && !empty($this->getPermisos()['todo'])
                ? (new \App\repositories\modulos\ProduccionOrdenRepository())->getUsuariosConOrdenes($idEmpresa) : [],
            'base'       => BASE_URL,
        ]);
    }

    public function datosAjax(): void
    {
        $this->requireLeer();
        $pestana = $this->pestana();
        $cols = $this->columnas($pestana)['pantalla'];
        $rows = $this->datos($pestana);

        $html = '<thead class="table-light"><tr>';
        $i = 0;
        foreach ($cols as $clave => $c) {
            $html .= '<th class="' . ($i++ === 0 ? 'ps-3 ' : '') . ColumnasReporte::claseAlinear($c['alinear']) . '">' . htmlspecialchars($c['titulo']) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        if ($rows === []) {
            $html .= '<tr><td colspan="' . count($cols) . '" class="text-center text-muted py-4">Sin órdenes cerradas con los filtros aplicados.</td></tr>';
        }
        foreach ($rows as $r) {
            $html .= empty($r['_total']) ? '<tr>' : '<tr class="fw-bold table-light">';
            $i = 0;
            foreach ($cols as $clave => $c) {
                $html .= '<td class="' . ($i++ === 0 ? 'ps-3 ' : '') . ColumnasReporte::claseAlinear($c['alinear']) . '">' . self::celda($r[$clave] ?? null, $clave) . '</td>';
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
        $cols = $this->columnas($pestana)['excel'];
        try {
            $data = [];
            foreach ($this->datos($pestana) as $r) {
                $fila = [];
                foreach ($cols as $clave => $c) {
                    $v = $r[$clave] ?? null;
                    $tipo = self::FORMATO[$clave] ?? 'txt';
                    $fila[] = in_array($tipo, ['num', 'money', 'pct'], true) ? ($v === null ? null : (float) $v)
                        : ($tipo === 'fecha' && $v ? date('d-m-Y', strtotime((string) $v)) : (string) ($v ?? ''));
                }
                $data[] = $fila;
            }
            $empresa = (new Empresa())->getPorId((int) $_SESSION['id_empresa']) ?? [];
            $titulos = array_map(static fn(array $c): string => (string) ($c['excel'][0] ?? $c['titulo']), $cols);
            (new \App\Services\ReportService())->exportToExcel(
                self::PESTANAS[$pestana]['titulo'],
                array_values($titulos),
                $data,
                'Produccion_' . $pestana,
                (string) ($empresa['nombre'] ?? '')
            );
        } catch (\Throwable $e) {
            echo 'Error al generar Excel: ' . htmlspecialchars($e->getMessage());
        }
        exit;
    }

    /**
     * PDF con el formato común de reportes (ReportePdf): encabezado con el logo del
     * establecimiento, caja «Filtros aplicados», indicadores y listado con totales; columnas
     * y orientación según la personalización de la empresa.
     */
    public function exportPdf(): void
    {
        $this->requireLeer();
        $pestana = $this->pestana();
        $res     = $this->columnas($pestana);
        $f       = $this->filtros();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        try {
            // La fila TOTAL del «Costo por mes» se reemplaza por la fila de totales del listado.
            $rows = array_values(array_filter($this->datos($pestana), static fn(array $r): bool => empty($r['_total'])));
            $empresa = (new Empresa())->getPorId($idEmpresa) ?? [];
            $resumen = ProduccionConfiguracionService::crear()->get($idEmpresa)
                ? $this->repo->resumen($idEmpresa, $f, $this->idUsuarioFiltro()) : [];

            $columnas = $res['pdf'];
            $anchos = ColumnasReporte::anchosPdf(array_map(static fn(array $c): float => (float) $c['ancho'], $columnas));
            $cols = [];
            foreach ($columnas as $clave => $c) {
                $col = [
                    'lbl' => $c['corto'],
                    'w'   => $anchos[$clave],
                    'cls' => ColumnasReporte::claseAlinear($c['alinear']),
                    'val' => static fn(array $r): string => self::celda($r[$clave] ?? null, $clave),
                ];
                if (in_array($clave, self::SUMAN, true)) {
                    $col['tot'] = static fn(array $filas): float => array_sum(array_map(static fn($r) => (float) ($r[$clave] ?? 0), $filas));
                    if ((self::FORMATO[$clave] ?? '') === 'num') {
                        $col['fmt'] = static fn(float $v): string => number_format($v, 0, '.', ',');
                    }
                }
                $cols[] = $col;
            }

            $kpis = [
                ['Órdenes cerradas', (string) (int) ($resumen['ordenes'] ?? 0)],
                ['Materiales', '$' . number_format((float) ($resumen['materiales'] ?? 0), 2)],
                ['Mano de obra + CIF', '$' . number_format((float) ($resumen['conversion'] ?? 0), 2)],
                ['Costo producido', '$' . number_format((float) ($resumen['costo'] ?? 0), 2), true],
                ['Merma anormal', '$' . number_format((float) ($resumen['merma'] ?? 0), 2), false, '#b02a37'],
            ];
            $cabecera = ReportePdf::encabezado($idEmpresa, (string) ($empresa['nombre'] ?? ''), 'Reportes de producción', self::PESTANAS[$pestana]['titulo'])
                . ReportePdf::filtros($this->filtrosTexto($f))
                . ReportePdf::indicadores($kpis);

            $horizontal = (bool) $res['horizontal'];
            $pt = ColumnasReporte::fuentePdf(count($cols), $horizontal);
            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf($horizontal ? 'L' : 'P', 'A4', 'es');
            ReportePdf::escribir($html2pdf, $pt, $cabecera, $cols, $rows, 'TOTALES', 'Sin órdenes cerradas con los filtros aplicados.');
            $html2pdf->output('Produccion_' . $pestana . '_' . date('Ymd_His') . '.pdf', 'D');
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            header('Content-Type: text/html');
            echo 'Error al generar PDF: ' . htmlspecialchars($e->getMessage());
        }
        exit;
    }
}
