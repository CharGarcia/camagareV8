<?php

declare(strict_types=1);

namespace App\Services\modulos;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Excel de los Estados Financieros con el mismo formato que el PDF
 * (EstadosFinancierosPdfService): cabecera con logo y datos de la empresa, título y
 * periodo, secciones, filas jerárquicas por nivel de cuenta, totales resaltados y firmas.
 *
 * Reporte de un solo periodo: el saldo de cada cuenta va en la COLUMNA DE SU NIVEL
 * (formato contable escalonado). La columna más a la derecha es el nivel 1 —donde van
 * también los totales— y cada nivel más profundo queda una columna a la izquierda; en el
 * PDF es el mismo escalonado, pero dentro de una sola columna "Saldo".
 *
 * Comparativo por periodos: una columna por mes (y "Total" en Resultados), con la misma
 * jerarquía visual por nivel. Ahí no se separa por nivel: serían N columnas por mes.
 */
class EstadosFinancierosExcelService
{
    // Mismos colores que el PDF
    private const AZUL       = '1F3864';
    private const AZUL_SUAVE = 'E2E8F2';
    private const GRIS_FILA  = 'F4F6F9';
    private const GRIS_LINEA = 'CDD2DA';
    private const GRIS_TEXTO = '696E78';
    private const ROJO       = 'B02323';
    private const VERDE      = '166E3C';

    private const FORMATO_DINERO = '#,##0.00;-#,##0.00';

    private Worksheet $sheet;
    private array $empresa = [];
    private int $fila = 1;
    /** Número de columnas de la tabla (Código + Cuenta + valores). */
    private int $numCols = 3;
    private string $ultimaCol = 'C';
    /** Nivel más profundo presente (solo reporte de un periodo). */
    private int $nivelMax = 1;

    /**
     * @param string $tipo    resultados | situacion | resultados_periodos | situacion_periodos
     * @param array  $datos   Salida de EstadosFinancierosService::getEstado*()
     * @param array  $empresa Fila completa de `empresas`
     * @param array  $filtros fecha_inicio, fecha_fin, nivel, centro_costo (nombre|null), proyecto (nombre|null)
     */
    public function exportar(string $tipo, array $datos, array $empresa, array $filtros): void
    {
        $this->empresa = $empresa;
        $porPeriodos  = str_ends_with($tipo, '_periodos');
        $esResultados = str_starts_with($tipo, 'resultados');

        $fi = $this->fecha($filtros['fecha_inicio'] ?? '');
        $ff = $this->fecha($filtros['fecha_fin'] ?? '');

        $titulo = $esResultados ? 'ESTADO DE RESULTADOS' : 'ESTADO DE SITUACIÓN FINANCIERA';
        if ($porPeriodos) {
            $titulo .= ' POR PERIODOS';
            $subtitulo = 'Comparativo mensual del ' . $fi . ' al ' . $ff;
        } elseif ($esResultados) {
            $subtitulo = 'Del ' . $fi . ' al ' . $ff;
        } else {
            $subtitulo = 'Al ' . $ff;
        }

        $partes = [];
        if (!$porPeriodos && !$esResultados) {
            $partes[] = 'Período de cálculo: ' . $fi . ' al ' . $ff;
        }
        $partes[] = 'Nivel de detalle: hasta nivel ' . (int)($filtros['nivel'] ?? 5);
        $partes[] = 'Centro de costo: ' . (($filtros['centro_costo'] ?? null) ?: 'Todos');
        $partes[] = 'Proyecto: ' . (($filtros['proyecto'] ?? null) ?: 'Todos');

        $spreadsheet = new Spreadsheet();
        $this->sheet = $spreadsheet->getActiveSheet();
        $this->sheet->setTitle($esResultados ? 'Estado de Resultados' : 'Situación Financiera');
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);
        $this->sheet->setShowGridlines(false);

        // Columnas de valores
        if ($porPeriodos) {
            $cabValores = array_values($datos['periodos']);
            if ($esResultados) {
                $cabValores[] = 'Total';
            }
        } else {
            $this->nivelMax = $this->nivelMaximo($datos);
            $cabValores = [];
            for ($n = $this->nivelMax; $n >= 1; $n--) {
                $cabValores[] = 'Nivel ' . $n;
            }
        }
        $this->numCols   = 2 + count($cabValores);
        $this->ultimaCol = Coordinate::stringFromColumnIndex($this->numCols);

        $this->cabecera($titulo, $subtitulo, implode('   ·   ', $partes));
        $filaCab = $this->fila;
        $this->cabeceraTabla(array_merge(['Código', 'Cuenta'], $cabValores));

        if ($porPeriodos) {
            $this->cuerpoPorPeriodos($esResultados, $datos);
        } else {
            $this->cuerpoSimple($esResultados, $datos);
        }

        $this->firmas();
        $this->configurarHoja($filaCab, $porPeriodos);

        $periodo = str_replace('-', '', (string)($filtros['fecha_inicio'] ?? '')) . '_' . str_replace('-', '', (string)($filtros['fecha_fin'] ?? ''));
        $nombreArchivo = str_replace(' ', '_', ucwords(mb_strtolower($titulo))) . '_' . trim($periodo, '_') . '.xlsx';

        if (ob_get_length()) {
            ob_end_clean();
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $nombreArchivo . '"');
        header('Cache-Control: max-age=0');
        (new Xlsx($spreadsheet))->save('php://output');
        exit;
    }

    // ───────────────────────────── Cabecera ─────────────────────────────

    private function cabecera(string $titulo, string $subtitulo, string $lineaFiltros): void
    {
        $s = $this->sheet;
        $colTexto = 'A';

        $logo = $this->resolverLogo($this->empresa);
        if ($logo !== '' && in_array(strtolower(pathinfo($logo, PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg', 'gif'], true)) {
            try {
                $d = new Drawing();
                $d->setPath($logo);
                // Dentro de la columna A (≈110 px) y de las 4 filas de la cabecera.
                $d->setResizeProportional(true);
                $d->setWidthAndHeight(110, 60);
                $d->setCoordinates('A1');
                $d->setOffsetX(4);
                $d->setOffsetY(4);
                $d->setWorksheet($s);
                $colTexto = 'B';
            } catch (\Throwable $e) {
                $colTexto = 'A';
            }
        }

        $nombre = mb_strtoupper((string)(($this->empresa['nombre'] ?? '') ?: ($this->empresa['nombre_comercial'] ?? '')));
        $lineaRuc = [];
        if (!empty($this->empresa['ruc'])) {
            $lineaRuc[] = 'RUC: ' . $this->empresa['ruc'];
        }
        $comercial = trim((string)($this->empresa['nombre_comercial'] ?? ''));
        if ($comercial !== '' && mb_strtoupper($comercial) !== $nombre) {
            $lineaRuc[] = $comercial;
        }
        $contacto = array_filter([
            trim((string)($this->empresa['telefono'] ?? '')) !== '' ? 'Tel.: ' . trim((string)$this->empresa['telefono']) : '',
            trim((string)($this->empresa['mail'] ?? '')),
        ]);

        // Bloque izquierdo: empresa. Bloque derecho (última columna, alineado a la derecha): título.
        $izq = [
            [$nombre, true, 12, self::AZUL],
            [implode('  ·  ', $lineaRuc), false, 9, self::GRIS_TEXTO],
            [trim((string)($this->empresa['direccion'] ?? '')), false, 9, self::GRIS_TEXTO],
            [implode('   ', $contacto), false, 9, self::GRIS_TEXTO],
        ];
        $der = [
            [$titulo, true, 13, self::AZUL, false],
            [$subtitulo, true, 10, '282828', false],
            ['Expresado en dólares de los Estados Unidos de América', false, 8, self::GRIS_TEXTO, true],
            ['', false, 9, self::GRIS_TEXTO, false],
        ];

        foreach ($izq as $i => [$texto, $bold, $size, $color]) {
            $r = $i + 1;
            $this->texto("{$colTexto}{$r}", $texto)->getFont()->setBold($bold)->setSize($size)->getColor()->setRGB($color);
            [$t, $b, $sz, $c, $it] = $der[$i];
            if ($t !== '') {
                $st = $this->texto("{$this->ultimaCol}{$r}", $t);
                $st->getFont()->setBold($b)->setItalic($it)->setSize($sz)->getColor()->setRGB($c);
                $st->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
        }

        // Línea divisoria azul y filtros aplicados
        $this->sheet->getStyle("A4:{$this->ultimaCol}4")->getBorders()->getBottom()
            ->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB(self::AZUL);
        $this->texto('A5', $lineaFiltros)->getFont()->setSize(8)->getColor()->setRGB(self::GRIS_TEXTO);
        $s->mergeCells("A5:{$this->ultimaCol}5");

        $this->fila = 7;
    }

    private function cabeceraTabla(array $titulos): void
    {
        $r = $this->fila;
        foreach ($titulos as $i => $t) {
            $this->texto(Coordinate::stringFromColumnIndex($i + 1) . $r, $t);
        }
        $st = $this->sheet->getStyle("A{$r}:{$this->ultimaCol}{$r}");
        $st->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $st->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::AZUL);
        $st->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $this->sheet->getStyle("C{$r}:{$this->ultimaCol}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $this->sheet->getRowDimension($r)->setRowHeight(20);
        $this->fila++;
    }

    // ───────────────────────────── Cuerpos ─────────────────────────────

    private function cuerpoSimple(bool $esResultados, array $datos): void
    {
        $t = $datos['totales'];
        $valor = fn(array $item) => (float)$item['saldo_final'];

        if ($esResultados) {
            $this->bloque('INGRESOS', $datos['ingresos'], $valor);
            $this->filaTotalSimple('TOTAL INGRESOS', (float)$t['ingresos']);
            $this->bloque('COSTOS', $datos['costos'], $valor);
            $this->filaTotalSimple('TOTAL COSTOS', (float)$t['costos']);
            $this->filaTotalSimple((float)$t['utilidad_bruta'] >= 0 ? 'UTILIDAD BRUTA' : 'PÉRDIDA BRUTA', (float)$t['utilidad_bruta'], true, true);
            $this->bloque('GASTOS', $datos['gastos'], $valor);
            $this->filaTotalSimple('TOTAL GASTOS', (float)$t['gastos']);
            $this->filaTotalSimple((float)$t['utilidad_neta'] >= 0 ? 'UTILIDAD DEL EJERCICIO' : 'PÉRDIDA DEL EJERCICIO', (float)$t['utilidad_neta'], true, true);
        } else {
            $this->bloque('ACTIVOS', $datos['activos'], $valor);
            $this->filaTotalSimple('TOTAL ACTIVOS', (float)$t['activos'], true);
            $this->bloque('PASIVOS', $datos['pasivos'], $valor);
            $this->filaTotalSimple('TOTAL PASIVOS', (float)$t['pasivos']);
            $this->bloque('PATRIMONIO', $datos['patrimonio'], $valor);
            $this->filaTotalSimple('TOTAL PATRIMONIO', (float)$t['patrimonio']);
            $this->filaTotalSimple('TOTAL PASIVO + PATRIMONIO', (float)$t['pasivo_patrimonio'], true);

            $dif = round((float)$t['activos'] - (float)$t['pasivo_patrimonio'], 2);
            if ($dif != 0) {
                $r = $this->fila++;
                $this->texto("B{$r}", 'Diferencia (Activo − Pasivo y Patrimonio), el balance no cuadra:');
                $this->sheet->setCellValue("{$this->ultimaCol}{$r}", $dif);
                $st = $this->sheet->getStyle("A{$r}:{$this->ultimaCol}{$r}");
                $st->getFont()->setBold(true)->setItalic(true)->getColor()->setRGB(self::ROJO);
                $this->sheet->getStyle("B{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $this->sheet->getStyle("{$this->ultimaCol}{$r}")->getNumberFormat()->setFormatCode(self::FORMATO_DINERO);
            }
        }
    }

    private function cuerpoPorPeriodos(bool $esResultados, array $datos): void
    {
        $claves = array_keys($datos['periodos']);

        $valoresItem = function (array $item) use ($claves, $esResultados): array {
            $v = [];
            foreach ($claves as $p) {
                $v[] = (float)($item['valores'][$p] ?? 0);
            }
            if ($esResultados) {
                $v[] = (float)($item['total'] ?? array_sum($item['valores'] ?? []));
            }
            return $v;
        };
        $valoresTotal = function (array $porPeriodo) use ($claves, $esResultados): array {
            $v = [];
            foreach ($claves as $p) {
                $v[] = (float)($porPeriodo[$p] ?? 0);
            }
            if ($esResultados) {
                $v[] = (float)($porPeriodo['total'] ?? array_sum(array_intersect_key($porPeriodo, array_flip($claves))));
            }
            return $v;
        };

        $t = $datos['totales'];
        if ($esResultados) {
            $this->bloque('INGRESOS', $datos['ingresos'], $valoresItem, true);
            $this->filaTotal('TOTAL INGRESOS', $valoresTotal($t['ingresos']));
            $this->bloque('COSTOS', $datos['costos'], $valoresItem, true);
            $this->filaTotal('TOTAL COSTOS', $valoresTotal($t['costos']));
            $this->filaTotal('UTILIDAD / PÉRDIDA BRUTA', $valoresTotal($t['utilidad_bruta']), true, true);
            $this->bloque('GASTOS', $datos['gastos'], $valoresItem, true);
            $this->filaTotal('TOTAL GASTOS', $valoresTotal($t['gastos']));
            $this->filaTotal('UTILIDAD / PÉRDIDA DEL EJERCICIO', $valoresTotal($t['utilidad_neta']), true, true);
        } else {
            $this->bloque('ACTIVOS', $datos['activos'], $valoresItem, true);
            $this->filaTotal('TOTAL ACTIVOS', $valoresTotal($t['activos']), true);
            $this->bloque('PASIVOS', $datos['pasivos'], $valoresItem, true);
            $this->filaTotal('TOTAL PASIVOS', $valoresTotal($t['pasivos']));
            $this->bloque('PATRIMONIO', $datos['patrimonio'], $valoresItem, true);
            $this->filaTotal('TOTAL PATRIMONIO', $valoresTotal($t['patrimonio']));
            $this->filaTotal('TOTAL PASIVO + PATRIMONIO', $valoresTotal($t['pasivo_patrimonio']), true);
        }
    }

    // ───────────────────────────── Filas ─────────────────────────────

    /** Título de sección + filas de sus cuentas. */
    private function bloque(string $titulo, array $items, callable $valores, bool $porPeriodos = false): void
    {
        $r = $this->fila++;
        $this->texto("A{$r}", $titulo);
        $st = $this->sheet->getStyle("A{$r}:{$this->ultimaCol}{$r}");
        $st->getFont()->setBold(true)->setSize(11)->getColor()->setRGB(self::AZUL);
        $st->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::AZUL_SUAVE);
        $this->sheet->getRowDimension($r)->setRowHeight(18);

        foreach ($items as $item) {
            $this->filaCuenta($item, $porPeriodos ? $valores($item) : [$valores($item)], $porPeriodos);
        }
    }

    /**
     * Fila de una cuenta. En el reporte de un periodo el valor se escribe en la columna de
     * su nivel; en el comparativo, un valor por columna de mes.
     */
    private function filaCuenta(array $item, array $valores, bool $porPeriodos): void
    {
        $nivel = max(1, (int)($item['nivel'] ?? 5));
        $r = $this->fila++;
        $s = $this->sheet;

        $nombre = (string)($item['nombre'] ?? '');
        if ($nivel === 1) {
            $nombre = mb_strtoupper($nombre);
        }
        $this->texto("A{$r}", (string)($item['codigo'] ?? ''));
        $this->texto("B{$r}", $nombre);
        $s->getStyle("B{$r}")->getAlignment()->setIndent($nivel - 1);

        if ($porPeriodos) {
            foreach ($valores as $i => $v) {
                $s->setCellValue(Coordinate::stringFromColumnIndex(3 + $i) . $r, $v);
            }
        } else {
            // Nivel 1 = última columna; cada nivel más profundo, una columna a la izquierda.
            $col = $this->numCols - (min($nivel, $this->nivelMax) - 1);
            $s->setCellValue(Coordinate::stringFromColumnIndex($col) . $r, $valores[0]);
        }

        $st = $s->getStyle("A{$r}:{$this->ultimaCol}{$r}");
        $st->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::GRIS_LINEA);
        $s->getStyle("C{$r}:{$this->ultimaCol}{$r}")->getNumberFormat()->setFormatCode(self::FORMATO_DINERO);

        $font = $st->getFont();
        if ($nivel === 1) {
            $font->setBold(true)->getColor()->setRGB(self::AZUL);
            $st->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::GRIS_FILA);
        } elseif ($nivel === 2) {
            $font->setBold(true)->getColor()->setRGB('1E1E1E');
        } elseif ($nivel === 3) {
            $font->getColor()->setRGB('1E1E1E');
        } else {
            $font->setSize(9)->getColor()->setRGB('464646');
        }
    }

    /** Total del reporte de un periodo: el valor va en la columna de nivel 1 (la última). */
    private function filaTotalSimple(string $titulo, float $valor, bool $destacada = false, bool $conSigno = false): void
    {
        $vals = array_fill(0, $this->numCols - 2, null);
        $vals[count($vals) - 1] = $valor;
        $this->filaTotal($titulo, $vals, $destacada, $conSigno);
    }

    /**
     * Fila de total. $destacada: fondo azul suave y doble línea inferior; $conSigno colorea
     * el valor en verde/rojo según sea utilidad o pérdida (igual que el PDF).
     */
    private function filaTotal(string $titulo, array $valores, bool $destacada = false, bool $conSigno = false): void
    {
        $r = $this->fila++;
        $s = $this->sheet;

        $this->texto("A{$r}", $titulo);
        $s->mergeCells("A{$r}:B{$r}");
        $s->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        foreach ($valores as $i => $v) {
            if ($v === null) {
                continue;
            }
            $celda = Coordinate::stringFromColumnIndex(3 + $i) . $r;
            $s->setCellValue($celda, $v);
            if ($conSigno) {
                $s->getStyle($celda)->getFont()->getColor()->setRGB($v < 0 ? self::ROJO : self::VERDE);
            }
        }

        $st = $s->getStyle("A{$r}:{$this->ultimaCol}{$r}");
        $st->getFont()->setBold(true)->setSize($destacada ? 11 : 10);
        if (!$conSigno) {
            $st->getFont()->getColor()->setRGB(self::AZUL);
        } else {
            $s->getStyle("A{$r}")->getFont()->getColor()->setRGB(self::AZUL);
        }
        $st->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($destacada ? self::AZUL_SUAVE : self::GRIS_FILA);
        $st->getBorders()->getTop()->setBorderStyle($destacada ? Border::BORDER_MEDIUM : Border::BORDER_THIN)->getColor()->setRGB(self::AZUL);
        if ($destacada) {
            $st->getBorders()->getBottom()->setBorderStyle(Border::BORDER_DOUBLE)->getColor()->setRGB(self::AZUL);
        }
        $s->getStyle("C{$r}:{$this->ultimaCol}{$r}")->getNumberFormat()->setFormatCode(self::FORMATO_DINERO);
        $s->getRowDimension($r)->setRowHeight($destacada ? 19 : 17);

        if ($destacada) {
            $this->fila++; // aire después de un total destacado, como en el PDF
        }
    }

    // ───────────────────────────── Firmas y hoja ─────────────────────────────

    private function firmas(): void
    {
        $s = $this->sheet;
        $r = $this->fila + 4;

        $bloques = [
            ['REPRESENTANTE LEGAL', (string)($this->empresa['nom_rep_legal'] ?? ''), (string)($this->empresa['ced_rep_legal'] ?? ''), 'C.I. / RUC'],
            ['CONTADOR', (string)($this->empresa['nombre_contador'] ?? ''), (string)($this->empresa['ruc_contador'] ?? ''), 'RUC'],
        ];
        // Representante en la columna Cuenta; Contador en las columnas de valores.
        $rangos = ["B", "C:{$this->ultimaCol}"];
        if ($this->numCols < 4) {
            $rangos = ["B", "C"];
        }

        foreach ($bloques as $i => [$cargo, $nombre, $id, $etiqueta]) {
            $rango = $rangos[$i];
            $lineas = [
                [trim($nombre) !== '' ? mb_strtoupper(trim($nombre)) : '', true, '141414'],
                [trim($id) !== '' ? $etiqueta . ': ' . trim($id) : '', false, '323232'],
                [$cargo, true, self::AZUL],
            ];
            foreach ($lineas as $j => [$texto, $bold, $color]) {
                $fila = $r + $j;
                [$ini, $fin] = str_contains($rango, ':') ? explode(':', $rango) : [$rango, $rango];
                $this->texto("{$ini}{$fila}", $texto);
                if ($ini !== $fin) {
                    $s->mergeCells("{$ini}{$fila}:{$fin}{$fila}");
                }
                $st = $s->getStyle("{$ini}{$fila}:{$fin}{$fila}");
                $st->getFont()->setBold($bold)->getColor()->setRGB($color);
                $st->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                if ($j === 0) {
                    $st->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('3C3C3C');
                }
            }
        }
        $this->fila = $r + 3;
    }

    private function configurarHoja(int $filaCab, bool $porPeriodos): void
    {
        $s = $this->sheet;
        $s->getColumnDimension('A')->setWidth(16);
        $s->getColumnDimension('B')->setWidth(50);
        for ($c = 3; $c <= $this->numCols; $c++) {
            $s->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(16);
        }
        $s->getRowDimension(1)->setRowHeight(18);

        // Encabezado de la tabla fijo al desplazarse y repetido al imprimir
        $s->freezePane('A' . ($filaCab + 1));
        $s->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($filaCab, $filaCab);
        $s->getPageSetup()
            ->setOrientation($porPeriodos || $this->numCols > 5 ? PageSetup::ORIENTATION_LANDSCAPE : PageSetup::ORIENTATION_PORTRAIT)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $s->getPageMargins()->setLeft(0.4)->setRight(0.4)->setTop(0.5)->setBottom(0.6);
        $s->getHeaderFooter()->setOddFooter('&L&8Emitido el ' . date('d-m-Y H:i:s') . '&R&8Página &P de &N');
        $s->setSelectedCell('A1');
    }

    // ───────────────────────────── Utilitarios ─────────────────────────────

    /** Escribe texto (siempre como cadena: los códigos de cuenta no deben volverse números). */
    private function texto(string $celda, string $valor): \PhpOffice\PhpSpreadsheet\Style\Style
    {
        $this->sheet->setCellValueExplicit($celda, $valor, DataType::TYPE_STRING);
        return $this->sheet->getStyle($celda);
    }

    private function nivelMaximo(array $datos): int
    {
        $max = 1;
        foreach (['ingresos', 'costos', 'gastos', 'activos', 'pasivos', 'patrimonio'] as $grupo) {
            foreach ($datos[$grupo] ?? [] as $item) {
                $max = max($max, (int)($item['nivel'] ?? 1));
            }
        }
        return min(5, $max);
    }

    private function fecha(string $ymd): string
    {
        $ts = $ymd !== '' ? strtotime($ymd) : false;
        return $ts ? date('d-m-Y', $ts) : $ymd;
    }

    /** Ruta física del logo de la empresa (mismo criterio que EstadosFinancierosPdfService). */
    private function resolverLogo(array $empresa): string
    {
        $rutas = array_filter([$empresa['logo_ruta'] ?? '', $empresa['logo'] ?? '']);
        foreach ($rutas as $ruta) {
            $clean = ltrim((string)$ruta, '/');
            if (preg_match('#^https?://[^/]+/(.*)$#i', $clean, $m)) {
                $clean = $m[1];
            }
            if (strpos($clean, 'sistema/public/') === 0) {
                $clean = substr($clean, strlen('sistema/public/'));
            } elseif (strpos($clean, 'sistema/') === 0) {
                $clean = substr($clean, strlen('sistema/'));
            }
            if (strpos($clean, 'public/') === 0) {
                $clean = substr($clean, strlen('public/'));
            }
            foreach ([\MVC_ROOT . '/public/' . $clean, \MVC_ROOT . '/' . $clean] as $cand) {
                if (is_file($cand)) {
                    return $cand;
                }
            }
        }
        return '';
    }
}
