<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Escribe un listado largo en un PDF de Html2Pdf por bloques.
 *
 * Html2Pdf tarda de forma cuadrática con el tamaño de cada writeHTML (medido en Cuentas
 * por Cobrar: 500 filas 9 s, 2.000 filas 102 s); escribiendo fila a fila es lineal (2.000
 * filas ≈ 10 s). El salto de página lo decide este método antes de cada fila, para repetir
 * la cabecera de columnas arriba de cada hoja como hacía el <thead>, sin partir filas entre
 * hojas. Los estilos del primer writeHTML siguen vigentes en los siguientes; el CSS de la
 * página debe traer `table.fila { margin-bottom: 0; }` (cada fila es su propia tabla).
 *
 * Lo usan Cuentas por Cobrar y Cuentas por Pagar.
 */
final class PdfPorBloques
{
    /** Alto estimado por defecto de una fila (mm): 3 líneas de 8 pt + relleno. */
    private const ALTO_FILA = 13.0;

    /**
     * @param string $inicio HTML del primer bloque: <style>, <page …> con el encabezado,
     *                       filtros y tarjetas, y su </page> (la página sigue abierta).
     * @param string $thead  <tr> de la cabecera de columnas.
     * @param array  $filas  Un <tr> por fila, o ['html' => <tr> o lista de <tr>, 'alto' => mm]
     *                       cuando la fila puede ocupar más que el alto por defecto, o son
     *                       varias <tr> que deben ir juntas (p. ej. la cabecera de un grupo y
     *                       su primera fila). Con 'html_salto' se da la variante que se usa
     *                       cuando la fila abre hoja nueva (p. ej. sin repetir los rótulos de
     *                       columnas, que ya se dibujaron arriba).
     * @param string $final  HTML posterior (totales); puede ir vacío.
     */
    public static function escribir(\Spipu\Html2Pdf\Html2Pdf $html2pdf, string $inicio, string $thead, array $filas, string $final = ''): void
    {
        $html2pdf->writeHTML($inicio . "<table class='fila'>{$thead}</table>");
        $pdf = $html2pdf->pdf;
        foreach ($filas as $fila) {
            [$tr, $alto] = is_array($fila) ? [$fila['html'], (float)($fila['alto'] ?? self::ALTO_FILA)] : [$fila, self::ALTO_FILA];
            // Si no cabe, hoja nueva con la cabecera: Html2Pdf nunca parte una fila ni deja
            // una hoja sin cabecera.
            if ($pdf->GetY() + $alto > $pdf->getPageHeight() - $pdf->getBreakMargin()) {
                $html2pdf->writeHTML("<page pageset='old'><table class='fila'>{$thead}</table></page>");
                $tr = is_array($fila) ? ($fila['html_salto'] ?? $tr) : $tr;
            }
            // Cada <tr> en su propia tabla: si una fila de una sola celda (cabecera de grupo)
            // abre la tabla, Html2Pdf toma sus anchos y las filas siguientes se salen de la hoja.
            $html = '';
            foreach ((array)$tr as $t) {
                $html .= "<table class='fila'>{$t}</table>";
            }
            $html2pdf->writeHTML($html);
        }
        if ($final !== '') {
            $html2pdf->writeHTML($final);
        }
    }
}
