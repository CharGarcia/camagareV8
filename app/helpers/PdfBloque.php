<?php

declare(strict_types=1);

namespace App\Helpers;

use TCPDF;

/**
 * Medición exacta de bloques en los PDF hechos con TCPDF (RIDE de compras, recibos,
 * notas de crédito/débito, reembolsos, órdenes de Car-Wash).
 *
 * Esos PDF decidían si el pie (totales + información adicional) pasaba a otra hoja
 * con un umbral fijo (`if ($y > 212) AddPage()`): si el detalle terminaba un poco más
 * abajo, el pie se iba solo a una hoja nueva aunque cupiera de sobra, y el PDF salía
 * con una segunda hoja casi vacía. Aquí el bloque se dibuja DE PRUEBA dentro de una
 * transacción de TCPDF y se deshace: si al dibujarlo el documento ganó una página, no
 * cabía. El resultado es exacto (mismas fuentes, anchos y saltos de línea que el
 * dibujo real) sin repetir en cada servicio una fórmula del alto que se desfase.
 *
 * Uso típico, en el `renderizar()` del servicio:
 *
 *     $yPie = $y + 2;
 *     if (!PdfBloque::cabe($this->pdf, fn() => $this->dibujarPie(..., $yPie))) {
 *         $this->pdf->AddPage();
 *         $yPie = 12;
 *     }
 *     $this->dibujarPie(..., $yPie);
 *
 * El bloque que se mide no debe incluir partes que ya tienen su propio salto (firmas,
 * leyenda al pie): si no caben, deben pasar solas a la hoja siguiente, no arrastrar
 * los totales con ellas.
 */
final class PdfBloque
{
    /**
     * ¿Cabe en la página actual lo que dibuja `$dibujar`? Lo dibuja de prueba y lo
     * deshace: el documento queda exactamente como estaba.
     */
    public static function cabe(TCPDF $pdf, callable $dibujar): bool
    {
        $paginas = $pdf->getNumPages();
        $pagina  = $pdf->getPage();

        $pdf->startTransaction();
        try {
            $dibujar();
            return $pdf->getNumPages() === $paginas && $pdf->getPage() === $pagina;
        } finally {
            // true: restaura el MISMO objeto (las closures del llamador lo tienen
            // capturado; con el valor devuelto quedarían apuntando a otro).
            $pdf->rollbackTransaction(true);
        }
    }
}
