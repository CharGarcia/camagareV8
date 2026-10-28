<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\Helpers\DocumentoOrigenAsiento;
use App\Helpers\OrigenAsiento;
use App\Helpers\ReportePdf;
use App\repositories\modulos\DimensionMovimientoRepository;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Pestañas «Documentos» y «Resumen» de Proyectos y de Centros de costo, y su PDF / Excel.
 *
 * Todo sale de las líneas de asiento que llevan la dimensión (DimensionMovimientoRepository):
 * solo asientos contabilizados de producción. Los importes van con el signo natural de la
 * clase: ingresos (4) = haber − debe; costos y gastos (5 y 6) = debe − haber.
 *
 * `$entidad` es la fila del proyecto o del centro de costo (la del repositorio de su módulo).
 */
class DimensionReporteService
{
    public function __construct(private DimensionMovimientoRepository $repo)
    {
    }

    public static function crear(): self
    {
        return new self(new DimensionMovimientoRepository());
    }

    // ── Eliminación ─────────────────────────────────────────────────────────

    /**
     * Un proyecto o centro de costo ya usado no se elimina: sus asientos, periodos del plan
     * de cuentas o presupuestos quedarían apuntando a un registro borrado. Se ofrece
     * inactivarlo, que lo saca de las listas sin perder la historia.
     */
    public function validarSinUsos(string $tipo, int $id, int $idEmpresa): void
    {
        $usos = $this->repo->usos($tipo, $id, $idEmpresa);
        if ($usos === []) {
            return;
        }
        $partes = [];
        foreach ($usos as $que => $n) {
            $partes[] = $n . ' ' . $que;
        }
        $nombre = $tipo === 'proyecto' ? 'el proyecto' : 'el centro de costo';
        throw new \InvalidArgumentException(
            "No se puede eliminar {$nombre}: ya se usa en " . implode(', ', $partes)
            . '. Si ya no lo va a usar, márquelo como inactivo.'
        );
    }

    // ── Datos de las pestañas ───────────────────────────────────────────────

    /** Documentos (uno por asiento) con su origen legible y, si se puede, cómo abrirlo. */
    public function documentos(string $tipo, int $id, int $idEmpresa, ?string $desde, ?string $hasta): array
    {
        $filas = $this->repo->documentos($tipo, $id, $idEmpresa, $this->fechaValida($desde), $this->fechaValida($hasta));
        $truncado = count($filas) > DimensionMovimientoRepository::MAX_DOCUMENTOS;
        if ($truncado) {
            array_pop($filas);
        }
        foreach ($filas as &$f) {
            $origen = (string) ($f['modulo_origen'] ?? '');
            $f['origen_nombre'] = OrigenAsiento::etiqueta($origen);
            // El visor de documento origen solo sabe abrir los módulos de su mapa.
            $f['abrible'] = DocumentoOrigenAsiento::paraModulo($origen) !== null && (int) ($f['id_referencia_origen'] ?? 0) > 0;
            $f['fecha_txt'] = $this->fecha($f['fecha_asiento'] ?? '');
            $f['debe'] = round((float) $f['debe'], 2);
            $f['haber'] = round((float) $f['haber'], 2);
        }
        unset($f);
        return ['filas' => $filas, 'truncado' => $truncado, 'max' => DimensionMovimientoRepository::MAX_DOCUMENTOS];
    }

    /** ¿El documento aparece en la pestaña Documentos de esta dimensión? (guard del visor) */
    public function documentoPertenece(string $tipo, int $id, string $modulo, int $idRef, int $idEmpresa): bool
    {
        if ($id <= 0 || $idRef <= 0 || $modulo === '') {
            return false;
        }
        return $this->repo->documentoPertenece($tipo, $id, $modulo, $idRef, $idEmpresa);
    }

    /**
     * Resumen del avance. Para un proyecto suma el presupuesto, la ejecución presupuestaria
     * (costos y gastos / presupuesto), el avance en el tiempo y el avance físico digitado.
     */
    public function resumen(string $tipo, int $id, int $idEmpresa, array $entidad, ?string $desde, ?string $hasta): array
    {
        $desde = $this->fechaValida($desde);
        $hasta = $this->fechaValida($hasta);

        $cuentas = [];
        $ingresos = 0.0;
        $costos = 0.0;
        foreach ($this->repo->porCuenta($tipo, $id, $idEmpresa, $desde, $hasta) as $c) {
            $clase = substr((string) $c['codigo'], 0, 1);
            $debe = (float) $c['debe'];
            $haber = (float) $c['haber'];
            // Saldo con el signo natural de la cuenta: deudoras (1, 5, 6) debe − haber.
            $saldo = in_array($clase, ['2', '3', '4'], true) ? $haber - $debe : $debe - $haber;
            if ($clase === '4') $ingresos += $saldo;
            if ($clase === '5' || $clase === '6') $costos += $saldo;
            $cuentas[] = [
                'codigo' => $c['codigo'],
                'nombre' => $c['nombre'],
                'grupo'  => $this->grupoClase($clase),
                'debe'   => round($debe, 2),
                'haber'  => round($haber, 2),
                'saldo'  => round($saldo, 2),
            ];
        }

        $meses = [];
        foreach ($this->repo->porMes($tipo, $id, $idEmpresa, $desde, $hasta) as $m) {
            $ing = round((float) $m['ingresos'], 2);
            $cg  = round((float) $m['costos_gastos'], 2);
            $meses[] = [
                'mes' => $m['mes'],
                'mes_txt' => $this->nombreMes((string) $m['mes']),
                'ingresos' => $ing,
                'costos_gastos' => $cg,
                'resultado' => round($ing - $cg, 2),
                'documentos' => (int) $m['documentos'],
            ];
        }

        $out = [
            'tipo' => $tipo,
            'ingresos' => round($ingresos, 2),
            'costos_gastos' => round($costos, 2),
            'resultado' => round($ingresos - $costos, 2),
            'documentos' => array_sum(array_column($meses, 'documentos')),
            'cuentas' => $cuentas,
            'meses' => $meses,
            'desde' => $desde,
            'hasta' => $hasta,
        ];

        if ($tipo === 'proyecto') {
            $presupuesto = round((float) ($entidad['presupuesto'] ?? 0), 2);
            $out['presupuesto'] = $presupuesto;
            $out['por_ejecutar'] = round($presupuesto - $costos, 2);
            $out['ejecucion_pct'] = $presupuesto > 0 ? round($costos / $presupuesto * 100, 2) : null;
            $out['avance_fisico_pct'] = round((float) ($entidad['porcentaje_ejecucion'] ?? 0), 2);
            $out['tiempo_pct'] = $this->avanceTiempo($entidad['fecha_inicio'] ?? null, $entidad['fecha_fin'] ?? null);
            $out['fecha_inicio_txt'] = $this->fecha((string) ($entidad['fecha_inicio'] ?? ''));
            $out['fecha_fin_txt'] = $this->fecha((string) ($entidad['fecha_fin'] ?? ''));
        }
        return $out;
    }

    // ── PDF ─────────────────────────────────────────────────────────────────

    /** PDF del resumen + el detalle de documentos, con el encabezado y logo de los reportes. */
    public function pdf(string $tipo, int $id, int $idEmpresa, array $entidad, ?string $desde, ?string $hasta): void
    {
        $r = $this->resumen($tipo, $id, $idEmpresa, $entidad, $desde, $hasta);
        $docs = $this->documentos($tipo, $id, $idEmpresa, $desde, $hasta);
        $empresa = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
        $pt = 7.5;
        $e = static fn ($v): string => htmlspecialchars((string) $v);
        $m = static fn ($v): string => ((float) $v < 0 ? '-$' : '$') . number_format(abs((float) $v), 2);
        $txt = static fn (string $v, float $w): string => ReportePdf::texto($v, ReportePdf::anchoPt($w, false), $pt);

        $titulo = ($tipo === 'proyecto' ? 'Avance del proyecto ' : 'Resumen del centro de costo ')
            . trim((!empty($entidad['codigo']) ? $entidad['codigo'] . ' - ' : '') . ($entidad['nombre'] ?? ''));

        $filtros = ['Período' => $this->textoPeriodo($r['desde'], $r['hasta'])];
        if ($tipo === 'proyecto') {
            $filtros['Cliente'] = (string) (($entidad['cliente_nombre'] ?? '') ?: '-');
            $filtros['Fechas del proyecto'] = ($r['fecha_inicio_txt'] ?: '-') . ' a ' . ($r['fecha_fin_txt'] ?: '-');
            $filtros['Estado'] = ucfirst((string) ($entidad['estado'] ?? ''));
        } else {
            $filtros['Estado'] = ucfirst((string) ($entidad['estado'] ?? ''));
        }

        $kpis = [];
        if ($tipo === 'proyecto') {
            $kpis[] = ['PRESUPUESTO', $m($r['presupuesto'])];
            $kpis[] = ['EJECUTADO (COSTOS Y GASTOS)', $m($r['costos_gastos']), true];
            $kpis[] = ['% EJECUCIÓN', $r['ejecucion_pct'] === null ? 'Sin presupuesto' : number_format($r['ejecucion_pct'], 2) . '%',
                false, ($r['ejecucion_pct'] ?? 0) > 100 ? '#b02a37' : ''];
            $kpis[] = ['POR EJECUTAR', $m($r['por_ejecutar']), false, $r['por_ejecutar'] < 0 ? '#b02a37' : ''];
            $kpis[] = ['AVANCE EN EL TIEMPO', $r['tiempo_pct'] === null ? '-' : number_format($r['tiempo_pct'], 0) . '%'];
            $kpis[] = ['AVANCE FÍSICO', number_format($r['avance_fisico_pct'], 2) . '%'];
        }
        $kpis2 = [
            ['INGRESOS', $m($r['ingresos']), false, '#146c43'],
            ['COSTOS Y GASTOS', $m($r['costos_gastos']), false, '#b02a37'],
            ['RESULTADO', $m($r['resultado']), true, $r['resultado'] < 0 ? '#b02a37' : '#146c43'],
            ['DOCUMENTOS', (string) count($docs['filas'])],
        ];

        $seccion = static fn (string $t): string => "<table class='fil-tit'><tr><td style='width:100%;'>" . htmlspecialchars($t) . '</td></tr></table>';

        $colsCuenta = [
            ['lbl' => 'Cuenta', 'w' => 52, 'cls' => '', 'val' => static fn (array $c): string => $txt($c['codigo'] . ' - ' . $c['nombre'], 52)],
            ['lbl' => 'Grupo', 'w' => 15, 'cls' => '', 'val' => static fn (array $c): string => $e($c['grupo'])],
            ['lbl' => 'Debe', 'w' => 11, 'cls' => 'text-end', 'val' => static fn (array $c): string => number_format($c['debe'], 2),
             'tot' => static fn (array $rs): float => array_sum(array_column($rs, 'debe'))],
            ['lbl' => 'Haber', 'w' => 11, 'cls' => 'text-end', 'val' => static fn (array $c): string => number_format($c['haber'], 2),
             'tot' => static fn (array $rs): float => array_sum(array_column($rs, 'haber'))],
            ['lbl' => 'Saldo', 'w' => 11, 'cls' => 'text-end', 'val' => static fn (array $c): string => '<b>' . number_format($c['saldo'], 2) . '</b>'],
        ];
        $colsMes = [
            ['lbl' => 'Mes', 'w' => 28, 'cls' => '', 'val' => static fn (array $x): string => $e($x['mes_txt'])],
            ['lbl' => 'Documentos', 'w' => 12, 'cls' => 'text-center', 'val' => static fn (array $x): string => (string) $x['documentos'],
             'tot' => static fn (array $rs): float => (float) array_sum(array_column($rs, 'documentos')), 'fmt' => static fn (float $v): string => (string) (int) $v],
            ['lbl' => 'Ingresos', 'w' => 20, 'cls' => 'text-end', 'val' => static fn (array $x): string => number_format($x['ingresos'], 2),
             'tot' => static fn (array $rs): float => array_sum(array_column($rs, 'ingresos'))],
            ['lbl' => 'Costos y gastos', 'w' => 20, 'cls' => 'text-end', 'val' => static fn (array $x): string => number_format($x['costos_gastos'], 2),
             'tot' => static fn (array $rs): float => array_sum(array_column($rs, 'costos_gastos'))],
            ['lbl' => 'Resultado', 'w' => 20, 'cls' => 'text-end', 'val' => static fn (array $x): string => '<b>' . number_format($x['resultado'], 2) . '</b>',
             'tot' => static fn (array $rs): float => array_sum(array_column($rs, 'resultado'))],
        ];
        $colsDoc = [
            ['lbl' => 'Fecha', 'w' => 9, 'cls' => 'text-center', 'val' => static fn (array $d): string => $e($d['fecha_txt'])],
            ['lbl' => 'Asiento', 'w' => 9, 'cls' => '', 'val' => static fn (array $d): string => $txt((string) ($d['numero_comprobante'] ?: '#' . $d['id_asiento']), 9)],
            ['lbl' => 'Origen', 'w' => 13, 'cls' => '', 'val' => static fn (array $d): string => $txt((string) $d['origen_nombre'], 13)],
            ['lbl' => 'Documento', 'w' => 13, 'cls' => '', 'val' => static fn (array $d): string => $txt((string) ($d['documento'] ?? ''), 13)],
            ['lbl' => 'Tercero / concepto', 'w' => 34, 'cls' => '',
             'val' => static fn (array $d): string => $txt((string) (($d['tercero'] ?? '') ?: ($d['concepto'] ?? '')), 34)],
            ['lbl' => 'Debe', 'w' => 11, 'cls' => 'text-end', 'val' => static fn (array $d): string => number_format($d['debe'], 2),
             'tot' => static fn (array $rs): float => array_sum(array_column($rs, 'debe'))],
            ['lbl' => 'Haber', 'w' => 11, 'cls' => 'text-end', 'val' => static fn (array $d): string => number_format($d['haber'], 2),
             'tot' => static fn (array $rs): float => array_sum(array_column($rs, 'haber'))],
        ];

        $autoload = MVC_ROOT . '/vendor/autoload.php';
        if (file_exists($autoload)) require_once $autoload;
        $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
        $html2pdf->pdf->SetTitle($titulo);

        $cabecera = ReportePdf::encabezado($idEmpresa, (string) ($empresa['nombre'] ?? ''), $titulo,
                (string) ($entidad['descripcion'] ?? ''))
            . ReportePdf::filtros($filtros)
            . ($kpis ? ReportePdf::indicadores($kpis) : '')
            . ReportePdf::indicadores($kpis2)
            . $seccion('Resumen por cuenta contable');
        ReportePdf::escribirListado($html2pdf, $pt, ReportePdf::inicio($pt, $cabecera), $colsCuenta, $r['cuentas'],
            'TOTALES', 'Sin movimientos en el período.');
        ReportePdf::escribirListado($html2pdf, $pt, $seccion('Avance por mes'), $colsMes, $r['meses'], 'TOTALES', 'Sin movimientos en el período.');
        $nDocs = count($docs['filas']);
        ReportePdf::escribirListado($html2pdf, $pt, $seccion('Documentos asociados'), $colsDoc, $docs['filas'],
            "TOTALES ({$nDocs} " . ($nDocs === 1 ? 'documento' : 'documentos') . ')', 'Sin documentos en el período.',
            $docs['truncado'] ? "<p style='font-size:7pt;color:#b02a37;'>Se muestran los " . $docs['max'] . ' documentos más recientes: acote el período para ver el resto.</p>' : '');

        $html2pdf->output($this->nombreArchivo($tipo, $entidad) . '_' . date('Ymd_His') . '.pdf', 'D');
    }

    // ── Excel ───────────────────────────────────────────────────────────────

    /** Excel con tres hojas (Resumen, Por mes, Documentos), logo y datos de la empresa. */
    public function excel(string $tipo, int $id, int $idEmpresa, array $entidad, ?string $desde, ?string $hasta): void
    {
        $r = $this->resumen($tipo, $id, $idEmpresa, $entidad, $desde, $hasta);
        $docs = $this->documentos($tipo, $id, $idEmpresa, $desde, $hasta);
        $empresa = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
        $titulo = ($tipo === 'proyecto' ? 'Avance del proyecto ' : 'Resumen del centro de costo ')
            . trim((!empty($entidad['codigo']) ? $entidad['codigo'] . ' - ' : '') . ($entidad['nombre'] ?? ''));
        $logo = ReportePdf::logo($idEmpresa);

        $ss = new Spreadsheet();

        // Hoja 1: indicadores + resumen por cuenta.
        $s = $ss->getActiveSheet();
        $s->setTitle('Resumen');
        $fila = $this->cabeceraExcel($s, $empresa, $titulo, $this->textoPeriodo($r['desde'], $r['hasta']), $logo, 5);
        $ind = [];
        if ($tipo === 'proyecto') {
            $ind[] = ['Presupuesto', $r['presupuesto'], '#,##0.00'];
            $ind[] = ['Ejecutado (costos y gastos)', $r['costos_gastos'], '#,##0.00'];
            $ind[] = ['% Ejecución', $r['ejecucion_pct'] === null ? 'Sin presupuesto' : $r['ejecucion_pct'] / 100, '0.00%'];
            $ind[] = ['Por ejecutar', $r['por_ejecutar'], '#,##0.00'];
            $ind[] = ['Avance en el tiempo', $r['tiempo_pct'] === null ? '-' : $r['tiempo_pct'] / 100, '0%'];
            $ind[] = ['Avance físico', $r['avance_fisico_pct'] / 100, '0.00%'];
        }
        $ind[] = ['Ingresos', $r['ingresos'], '#,##0.00'];
        $ind[] = ['Costos y gastos', $r['costos_gastos'], '#,##0.00'];
        $ind[] = ['Resultado', $r['resultado'], '#,##0.00'];
        $ind[] = ['Documentos', count($docs['filas']), '0'];
        foreach ($ind as [$lbl, $val, $fmt]) {
            $s->setCellValue("A{$fila}", $lbl);
            $s->getStyle("A{$fila}")->getFont()->setBold(true);
            $s->setCellValue("B{$fila}", $val);
            if (is_numeric($val)) $s->getStyle("B{$fila}")->getNumberFormat()->setFormatCode($fmt);
            $fila++;
        }
        $fila++;
        $this->tablaExcel($s, $fila, ['Código', 'Cuenta', 'Grupo', 'Debe', 'Haber', 'Saldo'],
            array_map(static fn ($c) => [$c['codigo'], $c['nombre'], $c['grupo'], $c['debe'], $c['haber'], $c['saldo']], $r['cuentas']),
            [4, 5, 6], [4, 5]);

        // Hoja 2: por mes.
        $s2 = $ss->createSheet();
        $s2->setTitle('Por mes');
        $f2 = $this->cabeceraExcel($s2, $empresa, $titulo, $this->textoPeriodo($r['desde'], $r['hasta']), $logo, 5);
        $this->tablaExcel($s2, $f2, ['Mes', 'Documentos', 'Ingresos', 'Costos y gastos', 'Resultado'],
            array_map(static fn ($x) => [$x['mes_txt'], $x['documentos'], $x['ingresos'], $x['costos_gastos'], $x['resultado']], $r['meses']),
            [3, 4, 5], [2, 3, 4, 5]);

        // Hoja 3: documentos.
        $s3 = $ss->createSheet();
        $s3->setTitle('Documentos');
        $f3 = $this->cabeceraExcel($s3, $empresa, $titulo, $this->textoPeriodo($r['desde'], $r['hasta']), $logo, 9);
        $this->tablaExcel($s3, $f3, ['Fecha', 'Asiento', 'Origen', 'Documento', 'Tercero', 'Concepto', 'Cuentas', 'Debe', 'Haber'],
            array_map(static fn ($d) => [$d['fecha_txt'], (string) ($d['numero_comprobante'] ?: '#' . $d['id_asiento']), $d['origen_nombre'],
                (string) ($d['documento'] ?? ''), (string) ($d['tercero'] ?? ''), (string) ($d['concepto'] ?? ''), (string) ($d['cuentas'] ?? ''),
                $d['debe'], $d['haber']], $docs['filas']),
            [8, 9], [8, 9]);

        $ss->setActiveSheetIndex(0);
        (new \App\Services\ReportService())->descargarSpreadsheet($ss, $this->nombreArchivo($tipo, $entidad));
    }

    /** Logo + empresa + título + período; devuelve la fila donde empieza el contenido. */
    private function cabeceraExcel(Worksheet $s, array $empresa, string $titulo, string $periodo, string $logo, int $cols): int
    {
        $col = 'A';
        if ($logo !== '') {
            try {
                $d = new Drawing();
                $d->setPath($logo);
                $d->setResizeProportional(true);
                $d->setWidthAndHeight(110, 60);
                $d->setCoordinates('A1');
                $d->setOffsetX(4);
                $d->setOffsetY(4);
                $d->setWorksheet($s);
                $col = 'B';
                $s->getColumnDimension('A')->setWidth(18);
            } catch (\Throwable $e) {
                $col = 'A';
            }
        }
        $ult = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(max($cols, 2));
        $lineas = [
            [mb_strtoupper((string) ($empresa['nombre'] ?? '')), 14, true],
            [!empty($empresa['ruc']) ? 'RUC: ' . $empresa['ruc'] : '', 9, false],
            [$titulo, 12, true],
            ['Período: ' . $periodo . '   ·   Generado: ' . date('d-m-Y H:i:s'), 9, false],
        ];
        foreach ($lineas as $i => [$texto, $tam, $negrita]) {
            $f = $i + 1;
            $s->setCellValueExplicit("{$col}{$f}", $texto, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $s->mergeCells("{$col}{$f}:{$ult}{$f}");
            $st = $s->getStyle("{$col}{$f}");
            $st->getFont()->setSize($tam)->setBold($negrita);
            $st->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        return 6;
    }

    /**
     * Tabla con encabezado azul, bordes y fila de totales. `$numCols` = columnas con formato
     * de importe (1-based); `$totCols` = columnas que se suman.
     */
    private function tablaExcel(Worksheet $s, int $fila, array $headers, array $datos, array $numCols, array $totCols): void
    {
        $n = count($headers);
        $ult = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($n);
        foreach ($headers as $i => $h) {
            $s->setCellValueExplicit(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1) . $fila, $h, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        }
        $s->getStyle("A{$fila}:{$ult}{$fila}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $ini = $fila;
        foreach ($datos as $row) {
            $fila++;
            foreach (array_values($row) as $i => $v) {
                $celda = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1) . $fila;
                if (is_string($v)) {
                    $s->setCellValueExplicit($celda, $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                } else {
                    $s->setCellValue($celda, $v);
                }
            }
        }
        if ($datos) {
            $fila++;
            $s->setCellValue("A{$fila}", 'TOTALES');
            foreach ($totCols as $c) {
                $l = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
                $s->setCellValue("{$l}{$fila}", '=SUM(' . $l . ($ini + 1) . ':' . $l . ($fila - 1) . ')');
            }
            $s->getStyle("A{$fila}:{$ult}{$fila}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E3E9F0']],
            ]);
        }
        $s->getStyle("A{$ini}:{$ult}{$fila}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        foreach ($numCols as $c) {
            $l = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
            $s->getStyle("{$l}" . ($ini + 1) . ":{$l}{$fila}")->getNumberFormat()->setFormatCode('#,##0.00');
        }
        for ($i = 1; $i <= $n; $i++) {
            if ($i === 1 && $s->getColumnDimension('A')->getWidth() > 0) continue; // A ya tiene el ancho del logo
            $s->getColumnDimensionByColumn($i)->setAutoSize(true);
        }
    }

    // ── Utilidades ──────────────────────────────────────────────────────────

    /** % del plazo transcurrido hoy (0-100), o null sin fechas válidas. */
    private function avanceTiempo(?string $inicio, ?string $fin): ?float
    {
        $i = $inicio ? strtotime($inicio) : false;
        $f = $fin ? strtotime($fin) : false;
        if (!$i || !$f || $f <= $i) return null;
        $hoy = strtotime(date('Y-m-d'));
        return round(max(0, min(100, ($hoy - $i) / ($f - $i) * 100)), 2);
    }

    private function grupoClase(string $clase): string
    {
        return match ($clase) {
            '1' => 'Activo', '2' => 'Pasivo', '3' => 'Patrimonio', '4' => 'Ingresos',
            '5' => 'Costos / gastos', '6' => 'Gastos', default => 'Otros',
        };
    }

    private function fechaValida(?string $f): ?string
    {
        $f = trim((string) $f);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) ? $f : null;
    }

    /** d-m-Y (§9). */
    private function fecha(string $f): string
    {
        $ts = $f !== '' ? strtotime($f) : false;
        return $ts ? date('d-m-Y', $ts) : '';
    }

    private function textoPeriodo(?string $desde, ?string $hasta): string
    {
        if (!$desde && !$hasta) return 'Todo';
        return ($desde ? $this->fecha($desde) : 'inicio') . ' a ' . ($hasta ? $this->fecha($hasta) : 'hoy');
    }

    private function nombreMes(string $ym): string
    {
        $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        [$a, $mm] = array_pad(explode('-', $ym), 2, '01');
        return ($meses[(int) $mm - 1] ?? $mm) . ' ' . $a;
    }

    private function nombreArchivo(string $tipo, array $entidad): string
    {
        $base = ($tipo === 'proyecto' ? 'Proyecto_' : 'Centro_costo_') . (($entidad['codigo'] ?? '') ?: ($entidad['id'] ?? ''));
        return preg_replace('/[^A-Za-z0-9_\-]/', '_', $base);
    }
}
