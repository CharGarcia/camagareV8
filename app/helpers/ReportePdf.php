<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Piezas comunes de los PDF de reportes generados con Html2Pdf, con el mismo formato que
 * el Reporte de Ventas: encabezado con el logo del establecimiento, caja "Filtros
 * aplicados", banda de indicadores, listado con anchos por columna y fila de totales.
 *
 * El listado se describe con una lista de columnas:
 *   ['lbl' => 'Total', 'w' => 12.5, 'cls' => 'text-end',
 *    'val' => fn(array $fila): string,      // HTML ya escapado de la celda
 *    'tot' => fn(array $filas): float|null, // opcional: valor de la fila de totales
 *    'fmt' => fn(float $v): string]         // opcional: formato del total (por defecto 2 decimales)
 * El `width:%` va repetido en el <th> y en cada <td>: es lo único que hace que Html2Pdf
 * respete el ancho (con el ancho solo en el <th> ensancha la columna hasta que el texto
 * quepa en una línea y la tabla se sale de la hoja).
 */
final class ReportePdf
{
    /** Ancho útil de A4 con los márgenes de pagina(), en puntos. */
    public const UTIL_VERTICAL   = 549.9;
    public const UTIL_HORIZONTAL = 796.5;

    /**
     * Ancho de cada carácter en Helvetica/Arial (la fuente core con que escribe Html2Pdf),
     * en milésimas de em. Lo que no está en la tabla vale 556, el ancho del dígito.
     */
    private const ANCHO_HELVETICA = [
        ' ' => 278, '!' => 278, '"' => 355, '#' => 556, '$' => 556, '%' => 889, '&' => 667, "'" => 191,
        '(' => 333, ')' => 333, '*' => 389, '+' => 584, ',' => 278, '-' => 333, '.' => 278, '/' => 278,
        ':' => 278, ';' => 278, '<' => 584, '=' => 584, '>' => 584, '?' => 556, '@' => 1015,
        '[' => 278, '\\' => 278, ']' => 278, '^' => 469, '_' => 556, '`' => 333,
        '{' => 334, '|' => 260, '}' => 334, '~' => 584,
        'A' => 667, 'B' => 667, 'C' => 722, 'D' => 722, 'E' => 667, 'F' => 611, 'G' => 778, 'H' => 722,
        'I' => 278, 'J' => 500, 'K' => 667, 'L' => 556, 'M' => 833, 'N' => 722, 'O' => 778, 'P' => 667,
        'Q' => 778, 'R' => 722, 'S' => 667, 'T' => 611, 'U' => 722, 'V' => 667, 'W' => 944, 'X' => 667,
        'Y' => 667, 'Z' => 611,
        'a' => 556, 'b' => 556, 'c' => 500, 'd' => 556, 'e' => 556, 'f' => 278, 'g' => 556, 'h' => 556,
        'i' => 222, 'j' => 222, 'k' => 500, 'l' => 222, 'm' => 833, 'n' => 556, 'o' => 556, 'p' => 556,
        'q' => 556, 'r' => 333, 's' => 500, 't' => 278, 'u' => 556, 'v' => 500, 'w' => 722, 'x' => 500,
        'y' => 500, 'z' => 500,
    ];

    private const SIN_TILDE = [
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n','à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u',
        'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N','À'=>'A','È'=>'E','Ì'=>'I','Ò'=>'O','Ù'=>'U',
    ];

    /** Ancho de un texto en em (1 em = el tamaño de la fuente en puntos). */
    private static function anchoEm(string $texto): float
    {
        $ancho = 0.0;
        foreach (mb_str_split(strtr($texto, self::SIN_TILDE)) as $ch) {
            $ancho += (self::ANCHO_HELVETICA[$ch] ?? 556) / 1000;
        }
        return $ancho;
    }

    /** Ancho en puntos del contenido de una columna de `$w` % (descontando padding y bordes). */
    public static function anchoPt(float $w, bool $horizontal): float
    {
        return $w / 100 * ($horizontal ? self::UTIL_HORIZONTAL : self::UTIL_VERTICAL) - 6.0;
    }

    /**
     * Texto escapado que cabe en su columna. Html2Pdf reparte en líneas por los espacios,
     * pero NO parte una palabra más larga que la columna: esa desborda sobre la vecina.
     * Se cortan a mano solo esas, midiendo el ancho real en la fuente y por carácter (no
     * por byte: cortar a media secuencia UTF-8 rompe la tilde).
     */
    public static function texto(string $t, float $anchoPt, float $pts): string
    {
        $palabras = preg_split('/\s+/', trim($t)) ?: [];
        foreach ($palabras as $i => $p) {
            if (self::anchoEm($p) * $pts <= $anchoPt) {
                continue;
            }
            $trozos = [''];
            $acum   = 0.0;
            foreach (mb_str_split($p) as $ch) {
                $a = self::anchoEm($ch) * $pts;
                if ($acum + $a > $anchoPt && $acum > 0.0) {
                    $trozos[] = '';
                    $acum     = 0.0;
                }
                $trozos[count($trozos) - 1] .= $ch;
                $acum += $a;
            }
            $palabras[$i] = implode("\n", $trozos);
        }
        return nl2br(htmlspecialchars(implode(' ', $palabras)));
    }

    /**
     * Reparte el ancho que dejaron libre las columnas ocultas entre las marcadas como
     * `flex` (las descriptivas), en proporción a su ancho, para que la suma vuelva a 100.
     */
    public static function completarAnchos(array $cols): array
    {
        $cols  = array_values($cols);
        $suma  = array_sum(array_column($cols, 'w'));
        $flex  = array_sum(array_map(static fn ($c) => !empty($c['flex']) ? $c['w'] : 0, $cols));
        $falta = 100 - $suma;
        if (abs($falta) < 0.01) {
            return $cols;
        }
        foreach ($cols as &$c) {
            if ($flex > 0) {
                if (!empty($c['flex'])) {
                    $c['w'] = round($c['w'] + $falta * $c['w'] / $flex, 2);
                }
            } else {
                $c['w'] = round($c['w'] * 100 / $suma, 2);
            }
        }
        unset($c);
        return $cols;
    }

    /**
     * Ruta en disco del logo del establecimiento principal ('' si no hay). Solo se devuelve
     * si es una imagen legible: ante una imagen que no puede medir, Html2Pdf aborta el PDF
     * entero, y un logo dañado no debe impedir sacar el reporte.
     */
    public static function logo(int $idEmpresa): string
    {
        $ruta = (string) ((new \App\models\Empresa())->getEstablecimientos($idEmpresa)[0]['logo_ruta'] ?? '');
        if ($ruta === '') {
            return '';
        }
        $clean = ltrim($ruta, '/');
        foreach (['sistema/public/', 'sistema/', 'public/'] as $prefijo) {
            if (strpos($clean, $prefijo) === 0) {
                $clean = substr($clean, strlen($prefijo));
            }
        }
        foreach ([MVC_ROOT . '/public/' . $clean, MVC_ROOT . '/' . $clean] as $cand) {
            if (is_file($cand) && @getimagesize($cand)) {
                return $cand;
            }
        }
        return '';
    }

    /**
     * Encabezado: logo a la izquierda del nombre de la empresa, título, subtítulo opcional
     * y fecha de generación. Html2Pdf no admite float ni flex: va en una tabla de tres
     * celdas (logo | textos | celda vacía del mismo ancho) para que el nombre quede
     * centrado en la hoja. Sin logo, encabezado centrado.
     */
    public static function encabezado(int $idEmpresa, string $nombreEmpresa, string $titulo, string $subtitulo = ''): string
    {
        $textos = '<h2>' . htmlspecialchars($nombreEmpresa) . '</h2>'
            . '<h3>' . htmlspecialchars($titulo) . '</h3>'
            . ($subtitulo !== '' ? '<h4>' . htmlspecialchars($subtitulo) . '</h4>' : '')
            . '<p>Generado: ' . date('d-m-Y H:i:s') . '</p>';
        $logo = self::logo($idEmpresa);
        if ($logo === '') {
            return "<div class=\"header\" style=\"margin-bottom:8px;\">{$textos}</div>";
        }
        $celda = 'border:none;padding:0;vertical-align:middle;';
        return '<table style="margin-bottom:8px;"><tr>'
            . "<td style=\"width:22%;{$celda}\"><img src=\"" . htmlspecialchars($logo) . "\" style=\"max-width:40mm;max-height:18mm;\"></td>"
            . "<td style=\"width:56%;{$celda}\"><div class=\"header\">{$textos}</div></td>"
            . "<td style=\"width:22%;{$celda}\"></td>"
            . '</tr></table>';
    }

    /**
     * Caja "Filtros aplicados" con dos pares etiqueta/valor por fila. Con un colspan en la
     * primera fila Html2Pdf ignora los anchos de la tabla: el título va en su propia tabla y
     * todas las filas de pares llevan las mismas cuatro celdas con los mismos anchos.
     */
    public static function filtros(array $filtrosTxt): string
    {
        if (!$filtrosTxt) {
            return '';
        }
        $e     = static fn ($v): string => htmlspecialchars((string) $v);
        $pares = [];
        foreach ($filtrosTxt as $lbl => $val) {
            $pares[] = [(string) $lbl, trim((string) $val)];
        }
        $filas = '';
        for ($i = 0, $n = count($pares); $i < $n; $i += 2) {
            [$lblA, $valA] = $pares[$i];
            [$lblB, $valB] = $pares[$i + 1] ?? ['', ''];
            $filas .= '<tr>'
                . "<td class='f-lbl' style='width:16%;'>" . ($lblA !== '' ? $e($lblA) . ':' : '') . '</td>'
                . "<td style='width:34%;'>" . $e($valA) . '</td>'
                . "<td class='f-lbl' style='width:16%;'>" . ($lblB !== '' ? $e($lblB) . ':' : '') . '</td>'
                . "<td style='width:34%;'>" . $e($valB) . '</td>'
                . '</tr>';
        }
        return "<table class='fil-tit'><tr><td style='width:100%;'>Filtros aplicados</td></tr></table>"
            . "<table class='filtros'>{$filas}</table>";
    }

    /**
     * Banda de indicadores: lista de [etiqueta, valor ya formateado, destacar?, color?].
     * `color` es un color CSS para el valor (p. ej. verde para ingresos, rojo para egresos).
     */
    public static function indicadores(array $kpis): string
    {
        if (!$kpis) {
            return '';
        }
        $w     = round(100 / count($kpis), 2);
        $celdas = '';
        foreach ($kpis as $k) {
            [$lbl, $val] = $k;
            $cls   = !empty($k[2]) ? 'k-val k-tot' : 'k-val';
            $color = !empty($k[3]) ? " style='color:{$k[3]};'" : '';
            $celdas .= "<td style='width:{$w}%;'><span class='k-lbl'>" . htmlspecialchars($lbl) . '</span><br>'
                     . "<span class='{$cls}'{$color}>" . htmlspecialchars($val) . '</span></td>';
        }
        return "<table class='kpis'><tr>{$celdas}</tr></table>";
    }

    /**
     * Listado (thead que Html2Pdf repite en cada página, filas alternas sombreadas) y, en
     * tabla aparte, la fila de totales: un <tfoot> se repetiría al pie de TODAS las páginas
     * con la cifra global, como si fuera el total de esa hoja.
     */
    public static function listado(array $cols, array $filas, string $etiquetaTotal, string $vacio = 'Sin resultados para los filtros aplicados.'): string
    {
        $th = '';
        foreach ($cols as $c) {
            $th .= "<th style='width:{$c['w']}%;'>" . htmlspecialchars($c['lbl']) . '</th>';
        }

        $cuerpo = '';
        $i = 0;
        foreach ($filas as $r) {
            $zebra = (++$i % 2 === 0) ? 'background:#f6f8fa;' : '';
            $cuerpo .= '<tr>';
            foreach ($cols as $c) {
                $cls = ($c['cls'] ?? '') !== '' ? " class='{$c['cls']}'" : '';
                $cuerpo .= "<td{$cls} style='width:{$c['w']}%;{$zebra}'>" . ($c['val'])($r) . '</td>';
            }
            $cuerpo .= '</tr>';
        }
        if ($cuerpo === '') {
            $cuerpo = "<tr><td colspan='" . count($cols) . "' class='text-center' style='width:100%;padding:8px;'>"
                    . htmlspecialchars($vacio) . '</td></tr>';
        }

        $html = "<table><thead><tr>{$th}</tr></thead><tbody>{$cuerpo}</tbody></table>";
        if ($filas) {
            $html .= "<table class='tot'>" . self::filaTotales($cols, $filas, $etiquetaTotal) . '</table>';
        }
        return $html;
    }

    /**
     * Fila de totales: la etiqueta absorbe con un colspan las columnas iniciales que no
     * totalizan nada y, desde ahí, cada columna conserva su ancho para calzar con el listado.
     */
    private static function filaTotales(array $cols, array $filas, string $etiqueta): string
    {
        $primera = null;
        foreach ($cols as $i => $c) {
            if (isset($c['tot'])) { $primera = $i; break; }
        }
        if ($primera === null) {
            return "<tr><td style='width:100%;' class='text-end'>" . htmlspecialchars($etiqueta) . '</td></tr>';
        }
        $html = '<tr>';
        if ($primera > 0) {
            $ancho = array_sum(array_column(array_slice($cols, 0, $primera), 'w'));
            $html .= "<td colspan='{$primera}' class='text-end' style='width:{$ancho}%;'>" . htmlspecialchars($etiqueta) . ':</td>';
        }
        for ($i = $primera, $n = count($cols); $i < $n; $i++) {
            $c   = $cols[$i];
            $val = '';
            if (isset($c['tot'])) {
                $monto = (float) ($c['tot'])($filas);
                $val   = isset($c['fmt']) ? ($c['fmt'])($monto) : number_format($monto, 2);
            }
            $html .= "<td class='text-end' style='width:{$c['w']}%;'>{$val}</td>";
        }
        return $html . '</tr>';
    }

    /** Estilos del reporte; `$pt` es el tamaño de letra del listado. */
    public static function css(float $pt): string
    {
        $fs = $pt . 'pt';
        return "
            body { font-family: Arial, sans-serif; color: #000; }
            table { width: 100%; border-collapse: collapse; table-layout: fixed; margin: 0 0 6px 0; }
            th { background: #e3e9f0; border: 1px solid #9aa7b4; padding: 3px; text-align: center;
                 font-size: {$fs}; font-weight: bold; color: #1b2a3a; }
            td { border: 1px solid #c3ccd6; padding: 2px 3px; font-size: {$fs}; word-wrap: break-word; }
            .text-end { text-align: right; }
            .text-center { text-align: center; }
            .sub { font-size: 6.5pt; color: #6a747e; }
            .header { text-align: center; }
            .header h2 { margin: 0 0 1px 0; font-size: 13pt; color: #1b2a3a; }
            .header h3 { margin: 0 0 1px 0; font-size: 10pt; color: #2c4a6b; }
            .header h4 { margin: 0 0 1px 0; font-size: 8.5pt; color: #2c4a6b; font-weight: normal; }
            .header p  { margin: 0; font-size: 7.5pt; color: #555; }
            table.fil-tit { margin-bottom: 0; }
            table.fil-tit td { background: #e9ecef; border: 1px solid #9aa7b4; padding: 3px 5px; font-size: 8.5pt; font-weight: bold; color: #1b2a3a; }
            table.filtros { margin-bottom: 8px; }
            table.filtros td { border: 1px solid #c3ccd6; padding: 2px 4px; font-size: 7.5pt; color: #000; }
            table.filtros td.f-lbl { background: #f8f9fa; font-weight: bold; }
            table.kpis { margin-bottom: 8px; }
            table.kpis td { border: 1px solid #c3ccd6; background: #f4f7fa; padding: 4px 3px; text-align: center; }
            .k-lbl { font-size: 6.5pt; color: #55606b; }
            .k-val { font-size: 10pt; font-weight: bold; color: #1b2a3a; }
            .k-tot { color: #146c43; }
            table.tot { margin-top: 0; }
            table.tot td { border: 1px solid #9aa7b4; background: #e3e9f0; font-weight: bold;
                           font-size: {$fs}; padding: 3px; color: #1b2a3a; }
        ";
    }

    /**
     * Documento completo: estilos + <page> con márgenes (los back* se SUMAN a los 5 mm por
     * defecto de Html2Pdf: con 3 mm a los lados quedan 8 mm reales, que es sobre lo que
     * están calculados UTIL_VERTICAL/UTIL_HORIZONTAL) y el pie "Página x/y".
     */
    public static function pagina(float $pt, string $contenido): string
    {
        return '<style>' . self::css($pt) . '</style>'
            . '<page backtop="7mm" backbottom="7mm" backleft="3mm" backright="3mm" footer="page">'
            . $contenido
            . '</page>';
    }
}
