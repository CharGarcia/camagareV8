<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Vista "Por antigüedad" de Cuentas por Cobrar y Cuentas por Pagar: el saldo pendiente de
 * cada cliente/proveedor repartido en columnas según los días vencidos (`dias_vencido` de
 * cada fila, que ya sigue el selector "Vencimiento según"): Por vencer, 1-N, …, Más de N.
 *
 * Gemelos en el navegador: CXC_renderAntiguedad / CXP_renderAntiguedad.
 */
final class AntiguedadCartera
{
    /** Tramos por defecto (días de mora). */
    public const TRAMOS_DEFECTO = [30, 60, 90];

    /**
     * "15,30,60,90" → [15, 30, 60, 90]. Días enteros entre 1 y 3650, sin repetidos y
     * ascendentes; entre 1 y 6 tramos (más columnas no caben en el PDF). Cualquier otra
     * cosa → los tramos por defecto.
     */
    public static function tramos(string $raw): array
    {
        $t = array_values(array_unique(array_filter(
            array_map('intval', explode(',', $raw)),
            static fn (int $d): bool => $d >= 1 && $d <= 3650
        )));
        sort($t);
        return ($t && count($t) <= 6) ? $t : self::TRAMOS_DEFECTO;
    }

    /** Etiquetas de las columnas: Por vencer, 1 - 30 días, …, Más de N días. */
    public static function columnas(array $tramos): array
    {
        $cols  = ['Por vencer'];
        $desde = 1;
        foreach ($tramos as $t) {
            $cols[] = "{$desde} - {$t} días";
            $desde  = $t + 1;
        }
        $cols[] = 'Más de ' . end($tramos) . ' días';
        return $cols;
    }

    /** Columna (índice de columnas()) que le toca a un documento con esta mora. */
    public static function columna(int $diasVencido, array $tramos): int
    {
        if ($diasVencido <= 0) {
            return 0;
        }
        foreach ($tramos as $i => $t) {
            if ($diasVencido <= $t) {
                return $i + 1;
            }
        }
        return count($tramos) + 1;
    }

    /**
     * Una fila por tercero (cédula y RUC juntos, IdentificacionTercero::claveGrupo) con su
     * saldo pendiente por columna. Solo cuenta lo que tiene saldo > 0: lo pagado no envejece.
     * Terceros en orden alfabético.
     *
     * @return array<int,array{nombre:string,ruc:string,docs:int,cols:float[],total:float}>
     */
    public static function agrupar(array $filas, array $tramos, string $campoNombre, string $campoRuc): array
    {
        $nCols  = count($tramos) + 2;
        $grupos = [];
        foreach ($filas as $r) {
            $saldo = (float)($r['saldo'] ?? 0);
            if ($saldo <= 0) {
                continue;
            }
            $nombre = (string)($r[$campoNombre] ?? '') ?: 'Sin nombre';
            $ruc    = (string)($r[$campoRuc] ?? '');
            $k = IdentificacionTercero::claveGrupo($ruc, $nombre);
            $grupos[$k] ??= ['nombre' => $nombre, 'ruc' => $ruc, 'docs' => 0, 'cols' => array_fill(0, $nCols, 0.0), 'total' => 0.0];
            $grupos[$k]['docs']++;
            $grupos[$k]['cols'][self::columna((int)($r['dias_vencido'] ?? 0), $tramos)] += $saldo;
            $grupos[$k]['total'] += $saldo;
        }
        usort($grupos, static fn ($a, $b) => strcmp(OrdenFilas::normalizar($a['nombre']), OrdenFilas::normalizar($b['nombre'])));
        return $grupos;
    }

    /**
     * Excel: una fila por tercero y la fila TOTAL al final. Descarga y termina.
     *
     * @param string $etiqueta "Cliente" | "Proveedor"
     */
    public static function exportarExcel(string $archivo, string $etiqueta, array $grupos, array $tramos, string $nombreEmpresa, array $filtrosTxt): void
    {
        $cols    = self::columnas($tramos);
        $headers = array_merge([$etiqueta, 'RUC/Cédula', 'Docs.'], $cols, ['Total']);
        $data    = [];
        $tot     = array_fill(0, count($cols), 0.0);
        $totDocs = 0;
        foreach ($grupos as $g) {
            $data[] = array_merge([$g['nombre'], $g['ruc'], $g['docs']], array_map(static fn ($v) => round($v, 2), $g['cols']), [round($g['total'], 2)]);
            foreach ($g['cols'] as $i => $v) {
                $tot[$i] += $v;
            }
            $totDocs += $g['docs'];
        }
        $data[]   = array_merge(['TOTAL (' . count($grupos) . ')', '', $totDocs], array_map(static fn ($v) => round($v, 2), $tot), [round(array_sum($tot), 2)]);
        $formatos = array_fill_keys(range(4, 4 + count($cols)), '#,##0.00');
        $filtrosTxt = ['Vista' => 'Por antigüedad (' . implode(', ', $tramos) . ' días)'] + $filtrosTxt;

        (new \App\Services\ReportService())->exportToExcel($archivo, $headers, $data, 'Antigüedad', $nombreEmpresa, $filtrosTxt, $formatos);
        exit;
    }

    /**
     * Partes del PDF para PdfPorBloques::escribir(): [thead, filas, final, horizontal].
     * Con $sinTercero (un solo cliente/proveedor) no va la columna del nombre ni la fila
     * TOTAL, que repetiría su única fila. Horizontal si hay más de 5 columnas de días.
     * El CSS de la página debe definir .text-end, .text-center y table.fila.
     */
    public static function partesPdf(array $grupos, array $tramos, string $etiqueta, bool $sinTercero): array
    {
        $e     = static fn ($v): string => htmlspecialchars((string)$v);
        $cols  = self::columnas($tramos);
        $nCols = count($cols);
        $wTer  = $sinTercero ? 0 : ($nCols > 5 ? 22 : 28);
        $wDocs = $sinTercero ? 8 : 5;
        $wNum  = round((100 - $wTer - $wDocs) / ($nCols + 1), 2);
        if ($sinTercero) {
            $wDocs = round(100 - $wNum * ($nCols + 1), 2);
        } else {
            $wTer = round(100 - $wDocs - $wNum * ($nCols + 1), 2);
        }
        $money = static fn (float $v): string => $v > 0.004 ? '$' . number_format($v, 2) : '-';
        $ter   = static fn (string $html): string => $sinTercero ? '' : $html;

        $thead = '<tr>' . $ter("<th style='width:{$wTer}%;'>" . $e($etiqueta) . '</th>') . "<th style='width:{$wDocs}%;'>Docs.</th>";
        foreach ($cols as $c) {
            $thead .= "<th style='width:{$wNum}%;'>" . $e($c) . '</th>';
        }
        $thead .= "<th style='width:{$wNum}%;'>Total</th></tr>";

        $filas   = [];
        $tot     = array_fill(0, $nCols, 0.0);
        $totDocs = 0;
        foreach ($grupos as $g) {
            $tr = '<tr>' . $ter("<td style='width:{$wTer}%;'>" . $e($g['nombre']) . '</td>') . "<td class='text-center' style='width:{$wDocs}%;'>{$g['docs']}</td>";
            foreach ($g['cols'] as $i => $v) {
                $tot[$i] += $v;
                // Las columnas vencidas en rojo; "Por vencer" en negro.
                $estilo = $i > 0 && $v > 0.004 ? 'color:#dc3545;' : '';
                $tr .= "<td class='text-end' style='width:{$wNum}%;{$estilo}'>" . $money($v) . '</td>';
            }
            $tr .= "<td class='text-end' style='width:{$wNum}%;font-weight:bold;'>" . $money($g['total']) . '</td></tr>';
            $filas[] = $tr;
            $totDocs += $g['docs'];
        }
        if (!$filas) {
            $filas[] = "<tr><td class='text-center' style='width:100%;'>No hay saldos pendientes con los filtros aplicados.</td></tr>";
        }

        $final = '';
        if (!$sinTercero) {
            $final = "<table class='fila'><tr style='background:#f8f9fa;font-weight:bold;'>
                        <td class='text-end' style='width:{$wTer}%;'>TOTAL (" . count($grupos) . ")</td>
                        <td class='text-center' style='width:{$wDocs}%;'>{$totDocs}</td>";
            foreach ($tot as $v) {
                $final .= "<td class='text-end' style='width:{$wNum}%;'>" . $money($v) . '</td>';
            }
            $final .= "<td class='text-end' style='width:{$wNum}%;'>" . $money(array_sum($tot)) . '</td></tr></table>';
        }
        return [$thead, $filas, $final, $nCols > 5];
    }
}
