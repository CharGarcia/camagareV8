<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Lector del bloque <otrosRubrosTerceros> de la factura electrónica del SRI.
 *
 * Es un nodo del esquema oficial de factura (versión 2.1.0): rubros que el emisor
 * cobra por cuenta de terceros y que SÍ forman parte del importe total. Caso real
 * (hotel, FIDEICOMISO LANDUNI 001-002-000143619):
 *
 *   <otrosRubrosTerceros>
 *     <rubro><concepto>TASA DE PERNOCTACION</concepto><total>2.5000</total></rubro>
 *   </otrosRubrosTerceros>
 *
 *   importeTotal 174.77 = subtotal 138.06 + IVA 20.71 + propina 13.50 + rubros 2.50
 *
 * No confundir con los "valores de terceros" de las planillas de luz/agua
 * (App\Helpers\RubrosTerceros): esos viven en <infoAdicional> y quedan FUERA del
 * importe total. Estos van DENTRO y, por decisión del usuario (2026-10-09), se
 * contabilizan en el mismo gasto de la compra.
 */
class OtrosRubrosTerceros
{
    /**
     * @return array{items: array<int, array{concepto: string, total: float}>, total: float}
     */
    public static function leer(\SimpleXMLElement $xml): array
    {
        $items = [];
        if (isset($xml->otrosRubrosTerceros->rubro)) {
            foreach ($xml->otrosRubrosTerceros->rubro as $rubro) {
                $total = round((float) ($rubro->total ?? 0), 2);
                $items[] = [
                    'concepto' => trim((string) ($rubro->concepto ?? '')),
                    'total'    => $total,
                ];
            }
        }

        return [
            'items' => $items,
            'total' => round(array_sum(array_column($items, 'total')), 2),
        ];
    }
}
