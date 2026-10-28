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

    /** Espacio (mm) que necesita un listado para empezar en la hoja actual: título, cabecera y una fila. */
    private const ESPACIO_MIN_LISTADO = 35.0;

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
     * Ruta en disco del logo de la empresa ('' si no hay): el del primer establecimiento que
     * tenga uno (en el orden de siempre, el principal primero) y, si ningún establecimiento
     * tiene, el del primer punto de emisión con logo (hay empresas que solo lo cargaron ahí).
     * Solo se devuelve si es una imagen legible: ante una imagen que no puede medir, Html2Pdf
     * aborta el PDF entero, y un logo dañado no debe impedir sacar el reporte.
     */
    public static function logo(int $idEmpresa): string
    {
        $modelo = new \App\models\Empresa();
        $establecimientos = $modelo->getEstablecimientos($idEmpresa);
        foreach ($establecimientos as $est) {
            $ruta = self::rutaLogo((string) ($est['logo_ruta'] ?? ''));
            if ($ruta !== '') {
                return $ruta;
            }
        }
        foreach ($establecimientos as $est) {
            foreach ($modelo->getPuntosEmision((int) $est['id']) as $pto) {
                $ruta = self::rutaLogo((string) ($pto['logo_ruta'] ?? ''));
                if ($ruta !== '') {
                    return $ruta;
                }
            }
        }
        return '';
    }

    /** URL pública guardada en BD → ruta en disco de una imagen legible ('' si no lo es). */
    private static function rutaLogo(string $ruta): string
    {
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
     *
     * Todo en un solo HTML: solo para listados cortos. Un listado que puede traer cientos
     * de filas va con escribir()/escribirListado(), por bloques (ver escribir()).
     */
    public static function listado(array $cols, array $filas, string $etiquetaTotal, string $vacio = 'Sin resultados para los filtros aplicados.'): string
    {
        $cuerpo = implode('', self::filasHtml($cols, $filas, $vacio));
        $html = "<table><thead>" . self::cabeceraHtml($cols) . "</thead><tbody>{$cuerpo}</tbody></table>";
        if ($filas) {
            $html .= "<table class='tot'>" . self::filaTotales($cols, $filas, $etiquetaTotal) . '</table>';
        }
        return $html;
    }

    /**
     * Documento completo escrito por bloques (App\Helpers\PdfPorBloques): lo mismo que
     * pagina() + listado(), pero cada fila en su propio writeHTML. Html2Pdf tarda de forma
     * cuadrática con el tamaño de cada writeHTML (Reporte de Ingresos y Egresos, vista
     * Documento: 500 filas 10 s, 1.000 filas 35 s); por bloques es lineal. Usarlo en los
     * listados que pueden traer cientos de filas. `$cabecera` es lo que va sobre la tabla
     * (encabezado, filtros, indicadores).
     */
    public static function escribir(\Spipu\Html2Pdf\Html2Pdf $html2pdf, float $pt, string $cabecera, array $cols, array $filas,
                                    string $etiquetaTotal, string $vacio = 'Sin resultados para los filtros aplicados.',
                                    string $despues = ''): void
    {
        self::escribirListado($html2pdf, $pt, self::inicio($pt, $cabecera), $cols, $filas, $etiquetaTotal, $vacio, $despues);
    }

    /**
     * Primer bloque de un PDF escrito por bloques: estilos, <page> con los márgenes y el pie
     * "Página x/y" de pagina(), y lo que va arriba de la primera tabla. La página se cierra
     * en el mismo bloque pero sigue abierta para los writeHTML siguientes. `$css` agrega
     * estilos propios del reporte.
     */
    public static function inicio(float $pt, string $cabecera, string $css = ''): string
    {
        return '<style>' . self::css($pt) . $css . '</style>'
             . '<page backtop="7mm" backbottom="7mm" backleft="3mm" backright="3mm" footer="page">'
             . $cabecera . '</page>';
    }

    /**
     * Escribe por bloques un listado (cabecera de columnas repetida en cada hoja y totales
     * al final) a continuación de `$antes`, que es inicio() en el primer listado del PDF o
     * el título de la sección en los siguientes. Varios listados seguidos en el mismo PDF
     * (p. ej. las secciones del Resumen Diario) se escriben llamándolo una vez por cada uno.
     */
    public static function escribirListado(\Spipu\Html2Pdf\Html2Pdf $html2pdf, float $pt, string $antes, array $cols, array $filas,
                                           string $etiquetaTotal, string $vacio = 'Sin resultados para los filtros aplicados.',
                                           string $despues = ''): void
    {
        $p = $html2pdf->pdf;
        $horizontal = $p->getPageWidth() > $p->getPageHeight();
        // Un listado que no es el primero del PDF y empieza al pie de la hoja: a la hoja
        // siguiente, para no dejar su título y la cabecera de columnas solos abajo.
        if (!str_contains($antes, '<page') && $p->getPage() > 0
            && $p->GetY() + self::ESPACIO_MIN_LISTADO > $p->getPageHeight() - $p->getBreakMargin()) {
            $html2pdf->writeHTML("<page pageset='old'></page>");
        }
        $final =($filas ? "<table class='tot'>" . self::filaTotales($cols, $filas, $etiquetaTotal) . '</table>' : '') . $despues;
        PdfPorBloques::escribir($html2pdf, $antes, self::cabeceraHtml($cols), self::filasHtml($cols, $filas, $vacio, $pt, $horizontal), $final);
    }

    /** <tr> con los rótulos de las columnas. */
    private static function cabeceraHtml(array $cols): string
    {
        $th = '';
        foreach ($cols as $c) {
            $th .= "<th style='width:{$c['w']}%;'>" . htmlspecialchars($c['lbl']) . '</th>';
        }
        return "<tr>{$th}</tr>";
    }

    /**
     * Un <tr> por fila (sombreado alterno); sin filas, una fila con el aviso $vacio. Con
     * `$pt` cada fila va como ['html', 'alto'] con su alto estimado, para que PdfPorBloques
     * no la deje partida al pie de la hoja cuando trae varias líneas.
     */
    private static function filasHtml(array $cols, array $filas, string $vacio, ?float $pt = null, bool $horizontal = false): array
    {
        $trs = [];
        $i = 0;
        foreach ($filas as $r) {
            $zebra = (++$i % 2 === 0) ? 'background:#f6f8fa;' : '';
            $tr = '<tr>';
            $lineas = 1;
            foreach ($cols as $c) {
                $cls   = ($c['cls'] ?? '') !== '' ? " class='{$c['cls']}'" : '';
                $valor = ($c['val'])($r);
                $tr   .= "<td{$cls} style='width:{$c['w']}%;{$zebra}'>" . $valor . '</td>';
                if ($pt !== null) {
                    $lineas = max($lineas, self::lineas($valor, self::anchoPt((float) $c['w'], $horizontal), $pt));
                }
            }
            $tr .= '</tr>';
            $trs[] = $pt === null ? $tr : ['html' => $tr, 'alto' => self::altoFila($lineas, $pt)];
        }
        if (!$trs) {
            $trs[] = "<tr><td class='text-center' style='width:100%;padding:8px;'>" . htmlspecialchars($vacio) . '</td></tr>';
        }
        return $trs;
    }

    /** Líneas que ocupa el HTML de una celda en su ancho: los <br> y el ajuste por palabras. */
    private static function lineas(string $html, float $anchoPt, float $pt): int
    {
        $total = 0;
        foreach (preg_split('/<br\s*\/?>/i', $html) ?: [''] as $tramo) {
            $texto  = html_entity_decode(strip_tags($tramo), ENT_QUOTES, 'UTF-8');
            $total += max(1, (int) ceil(self::anchoEm($texto) * $pt / max($anchoPt, 1.0)));
        }
        return $total;
    }

    /**
     * Alto (mm) que se reserva para una fila: el de PdfPorBloques (13 mm, unas tres líneas)
     * o más si la fila trae más líneas. Interlineado ~1,25 em más el relleno de la celda.
     */
    private static function altoFila(int $lineas, float $pt): float
    {
        return max(13.0, $lineas * $pt * 1.25 * 0.3528 + 3.0);
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
            table.fila { margin-bottom: 0; }
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
