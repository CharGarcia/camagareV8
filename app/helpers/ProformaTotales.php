<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Desglose de totales de una Proforma, tal como lo muestra la pantalla del módulo.
 *
 * Es la única fuente del bloque de totales: la usan el PDF y la exportación a Excel,
 * para que ninguna salida pueda divergir de lo que el usuario vio al guardar.
 *
 * Los valores NO se recalculan: salen de los impuestos guardados en cada línea y de
 * los totales de la cabecera —que el modal calculó con los decimales y el modo de
 * cálculo del IVA configurados por la empresa (`decimales_precio`, `decimales_cantidad`,
 * `calculo_iva_facturacion`)—. Es el mismo criterio del RIDE de Facturas de Venta.
 */
class ProformaTotales
{
    /**
     * @param array $cabecera Cabecera de la proforma (total_sin_impuestos, total_descuento,
     *                        total_ice, importe_total).
     * @param array $detalles Líneas, cada una con su arreglo `impuestos`.
     * @return array{
     *   subtotal_bruto: float, subtotal_neto: float, descuento: float, ice: float,
     *   iva: float, total: float, grupos: array<int,array{tasa:float,base:float,iva:float}>
     * }
     */
    public static function desglosar(array $cabecera, array $detalles): array
    {
        $ice = (float) ($cabecera['total_ice'] ?? 0);

        // Bases e IVA por tarifa, desde los impuestos guardados de cada línea. Si una
        // línea no tiene impuestos guardados, su base cuenta como tarifa 0.
        $grupos   = [];
        $sumBases = 0.0; $sumDesc = 0.0; $sumIva = 0.0;
        foreach ($detalles as $d) {
            $sumDesc += (float) ($d['descuento'] ?? 0);
            $tieneImp = false;
            foreach ($d['impuestos'] ?? [] as $imp) {
                if ((string) ($imp['codigo_impuesto'] ?? '2') !== '2') continue;
                $tasa = (float) ($imp['tarifa'] ?? 0);
                $base = isset($imp['base_imponible'])
                    ? (float) $imp['base_imponible']
                    : self::baseLinea($d);
                $val  = (float) ($imp['valor'] ?? 0);
                $k    = (string) $tasa;
                if (!isset($grupos[$k])) $grupos[$k] = ['tasa' => $tasa, 'base' => 0.0, 'iva' => 0.0];
                $grupos[$k]['base'] += $base;
                $grupos[$k]['iva']  += $val;
                $sumBases += $base;
                $sumIva   += $val;
                $tieneImp  = true;
            }
            if (!$tieneImp) {
                $base = self::baseLinea($d);
                if (!isset($grupos['0'])) $grupos['0'] = ['tasa' => 0.0, 'base' => 0.0, 'iva' => 0.0];
                $grupos['0']['base'] += $base;
                $sumBases += $base;
            }
        }
        ksort($grupos, SORT_NUMERIC);

        // Los totales guardados en la cabecera mandan: son los que calculó la pantalla.
        $subtotalNeto = isset($cabecera['total_sin_impuestos'])
            ? (float) $cabecera['total_sin_impuestos'] : $sumBases;
        $descuento    = isset($cabecera['total_descuento'])
            ? (float) $cabecera['total_descuento'] : $sumDesc;
        $total        = isset($cabecera['importe_total'])
            ? (float) $cabecera['importe_total'] : $subtotalNeto + $ice + $sumIva;

        // Reconciliar el IVA con el total guardado: cuando la empresa calcula el IVA al
        // subtotal por tarifa, la suma de los IVA por línea puede diferir en ±1 centavo.
        // El desfase se absorbe en el grupo de mayor IVA para que Subtotal + IVA = TOTAL.
        if (isset($cabecera['importe_total']) && $grupos) {
            $ivaObjetivo = round($total - $subtotalNeto - $ice, 2);
            $desfase     = round($ivaObjetivo - $sumIva, 2);
            if (abs($desfase) >= 0.01 && abs($desfase) <= 0.05) {
                $kMax = null; $vMax = -INF;
                foreach ($grupos as $k => $g) {
                    if ($g['iva'] > $vMax) { $vMax = $g['iva']; $kMax = $k; }
                }
                if ($kMax !== null) {
                    $grupos[$kMax]['iva'] = round($grupos[$kMax]['iva'] + $desfase, 2);
                    $sumIva = round($sumIva + $desfase, 2);
                }
            }
        }

        return [
            // "Subtotal" es el bruto (antes de descuento), igual que en la pantalla.
            'subtotal_bruto' => round($subtotalNeto + $descuento, 2),
            'subtotal_neto'  => $subtotalNeto,
            'descuento'      => $descuento,
            'ice'            => $ice,
            'iva'            => $grupos ? $sumIva : round($total - $subtotalNeto - $ice, 2),
            'total'          => $total,
            'grupos'         => array_values($grupos),
        ];
    }

    /**
     * Filas del bloque de totales, en el mismo orden que el pie del modal:
     * Subtotal, un "Subtotal {tarifa}%" por tarifa, el descuento, el ICE si lo hay,
     * un "(+) IVA {tarifa}%" por tarifa mayor a cero y el TOTAL aparte.
     *
     * @return array<int,array{0:string,1:float}> Pares [etiqueta, valor] sin el TOTAL.
     */
    public static function filas(array $desglose): array
    {
        $filas = [['Subtotal', $desglose['subtotal_bruto']]];
        foreach ($desglose['grupos'] as $g) {
            $filas[] = ['Subtotal ' . self::pct($g['tasa']) . '%', $g['base']];
        }
        $filas[] = ['(-) Descuento', $desglose['descuento']];
        if ($desglose['ice'] > 0) $filas[] = ['ICE', $desglose['ice']];
        foreach ($desglose['grupos'] as $g) {
            if ($g['tasa'] > 0) $filas[] = ['(+) IVA ' . self::pct($g['tasa']) . '%', $g['iva']];
        }
        if (!$desglose['grupos']) $filas[] = ['(+) IVA', $desglose['iva']];
        return $filas;
    }

    /** Porcentaje sin ceros sobrantes: 15 → "15", 12.50 → "12.5". */
    public static function pct(float $v): string
    {
        $t = rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
        return $t === '' ? '0' : $t;
    }

    /**
     * Base imponible de la línea: el valor guardado (lo que mostró la pantalla al
     * grabar); solo si falta se recalcula como cantidad × precio − descuento.
     */
    public static function baseLinea(array $d): float
    {
        if (isset($d['precio_total_sin_impuesto']) && $d['precio_total_sin_impuesto'] !== '') {
            return (float) $d['precio_total_sin_impuesto'];
        }
        $bruto = round((float) ($d['cantidad'] ?? 0) * (float) ($d['precio_unitario'] ?? 0), 2);
        return max(0.0, round($bruto - (float) ($d['descuento'] ?? 0), 2));
    }

    /** Tarifa de IVA de una línea (código de impuesto 2); 0 si no la tiene. */
    public static function tarifaIva(array $d): float
    {
        foreach ($d['impuestos'] ?? [] as $imp) {
            if ((string) ($imp['codigo_impuesto'] ?? '2') === '2') {
                return (float) ($imp['tarifa'] ?? 0);
            }
        }
        return 0.0;
    }
}
