<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Desglose de totales de una Proforma, tal como lo muestra la pantalla del módulo.
 *
 * Es la única fuente del bloque de totales: la usan el PDF y la exportación a Excel,
 * para que ninguna salida pueda divergir de lo que el usuario vio al guardar.
 *
 * desglosar() no recalcula: lee los impuestos de cada línea y los totales de la
 * cabecera. Antes de llamarlo, las salidas pasan por recalcularIva(), que aplica el
 * modo de IVA VIGENTE de la empresa (`calculo_iva_facturacion`: al subtotal o ítem por
 * ítem) sobre las bases guardadas, para que el PDF, el Excel y las conversiones usen
 * siempre la configuración actual aunque la proforma se haya grabado con otra.
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

    /**
     * IVA de cada línea según la configuración de facturación (`calculo_iva_facturacion`),
     * sobre la base ya calculada de la línea (precio_total_sin_impuesto):
     *   - 'linea_linea': cada línea round(base × %), y el total es su suma.
     *   - 'subtotal'   : el IVA de cada tarifa es round(Σ bases × %) y los centavos se
     *                    reparten entre sus líneas (IvaSubtotal::repartir, el mismo
     *                    algoritmo que CMG_repartirIvaSubtotal() del modal).
     * Actualiza base_imponible y valor del impuesto IVA (código 2) de cada línea.
     *
     * Única implementación: la usan el guardado y las conversiones (ProformaService),
     * el PDF y el Excel.
     *
     * @return array{0: array, 1: array{subtotal: float, descuento: float, iva: float}}
     */
    public static function aplicarModoIva(array $detalles, string $modo): array
    {
        $lineasIva = [];
        $idxIva    = [];
        foreach ($detalles as $k => $d) {
            if (!is_array($d)) continue;
            $idxIva[$k] = null;
            foreach ($d['impuestos'] ?? [] as $i => $imp) {
                if ((string) ($imp['codigo_impuesto'] ?? '2') === '2') { $idxIva[$k] = $i; break; }
            }
            $pct   = $idxIva[$k] !== null ? (float) ($d['impuestos'][$idxIva[$k]]['tarifa'] ?? 0) : 0.0;
            $idTar = (int) ($d['id_tarifa_iva'] ?? 0);
            $lineasIva[$k] = [
                'grupo' => $idTar > 0 ? 'id:' . $idTar : 'pct:' . $pct,
                'base'  => round(self::baseLinea($d), 2),
                'pct'   => $pct,
            ];
        }

        $ivaLineas = IvaSubtotal::repartir($lineasIva, $modo);

        $subtotal = 0.0; $descuento = 0.0; $iva = 0.0;
        foreach ($detalles as $k => &$d) {
            if (!is_array($d)) continue;
            $base = $lineasIva[$k]['base'];
            $val  = (float) ($ivaLineas[$k] ?? 0);
            if ($idxIva[$k] !== null) {
                $d['impuestos'][$idxIva[$k]]['base_imponible'] = $base;
                $d['impuestos'][$idxIva[$k]]['valor']          = $val;
            }
            $subtotal  = round($subtotal + $base, 2);
            $descuento = round($descuento + (float) ($d['descuento'] ?? 0), 2);
            $iva       = round($iva + $val, 2);
        }
        unset($d);

        return [$detalles, ['subtotal' => $subtotal, 'descuento' => $descuento, 'iva' => $iva]];
    }

    /**
     * Recalcula el IVA de la proforma con la configuración VIGENTE y ajusta los totales
     * de la cabecera (total_sin_impuestos, total_descuento, importe_total). Cantidades,
     * precios, descuentos y bases de cada línea se respetan tal como se cotizaron.
     *
     * Así una proforma guardada con otro modo de IVA (o antes de que la proforma
     * respetara la configuración) se imprime, exporta y convierte con el modo actual.
     *
     * @return array{0: array, 1: array} [cabecera, detalles]
     */
    public static function recalcularIva(array $cabecera, array $detalles, array $config): array
    {
        if (empty($detalles)) return [$cabecera, $detalles];
        [$detalles, $tot] = self::aplicarModoIva($detalles, IvaSubtotal::modo($config));
        $cabecera['total_sin_impuestos'] = $tot['subtotal'];
        $cabecera['total_descuento']     = $tot['descuento'];
        $cabecera['importe_total']       = round($tot['subtotal'] + (float) ($cabecera['total_ice'] ?? 0) + $tot['iva'], 2);
        return [$cabecera, $detalles];
    }

    /**
     * Decimales de cantidad y de precio configurados por la empresa/establecimiento,
     * acotados a 0..6 (mismo criterio que el modal, el PDF y el Excel).
     *
     * @return array{0:int,1:int} [decimales_cantidad, decimales_precio]
     */
    public static function decimales(array $config): array
    {
        return [
            max(0, min(6, (int) ($config['decimales_cantidad'] ?? 2))),
            max(0, min(6, (int) ($config['decimales_precio']   ?? 2))),
        ];
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
