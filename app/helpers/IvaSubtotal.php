<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * IVA por línea según el modo de cálculo del establecimiento
 * (`empresa_establecimiento.calculo_iva_facturacion`).
 *
 *   - `linea_linea`: el IVA de cada línea se redondea por su cuenta; el total es su suma.
 *   - `subtotal`   : el IVA de cada tarifa es round(Σ bases × %), UNA sola vez.
 *
 * En modo `subtotal` el documento igual guarda el IVA de cada línea (el SRI lo pide en
 * cada <detalle>), y redondeando cada una por su cuenta la suma difiere del IVA de la
 * tarifa en hasta medio centavo por línea: con 500 líneas, ~0,23. El XML, el RIDE y el
 * modal solo concilian hasta 0,05, así que en documentos grandes volvía a aparecer el
 * IVA línea a línea, descuadrado con el importe total.
 *
 * repartir() reparte esos centavos entre las líneas de mayor residuo de redondeo
 * (ninguna se mueve más de 0,01), para que Σ IVA de líneas = IVA de la tarifa EXACTO.
 * Es el mismo algoritmo que `CMG_repartirIvaSubtotal()` de public/js/app.js.
 */
final class IvaSubtotal
{
    /** Normaliza el valor guardado de la configuración: todo lo que no sea 'subtotal' es línea a línea. */
    public static function modo(?array $config): string
    {
        return (($config['calculo_iva_facturacion'] ?? 'linea_linea') === 'subtotal') ? 'subtotal' : 'linea_linea';
    }

    /**
     * @param array<int|string, array{grupo:string|int, base:float, pct:float}> $lineas
     *        Una entrada por línea (la clave se conserva). `grupo` identifica la tarifa
     *        (id o código): las líneas se agrupan por él. `base` es la base imponible
     *        del IVA de la línea, ya redondeada a centavos.
     * @param string $modo 'subtotal' | 'linea_linea'
     * @return array<int|string, float> IVA de cada línea, con las mismas claves.
     */
    public static function repartir(array $lineas, string $modo): array
    {
        $iva    = [];
        $grupos = [];
        foreach ($lineas as $k => $l) {
            $base    = (float) $l['base'];
            $pct     = (float) $l['pct'];
            $iva[$k] = round($base * $pct / 100, 2);
            if ($modo !== 'subtotal' || $pct <= 0) {
                continue;
            }
            $g = (string) $l['grupo'];
            $grupos[$g] ??= ['pct' => $pct, 'base' => 0.0, 'claves' => []];
            $grupos[$g]['base']    += $base;
            $grupos[$g]['claves'][] = $k;
        }

        foreach ($grupos as $g) {
            $objetivo = round(round($g['base'], 2) * $g['pct'] / 100, 2);
            $suma     = 0.0;
            foreach ($g['claves'] as $k) {
                $suma += $iva[$k];
            }
            $centavos = (int) round(($objetivo - round($suma, 2)) * 100);
            if ($centavos === 0) {
                continue;
            }

            // Residuo = exacto − redondeado. Faltan centavos → subir primero las que más
            // perdieron al redondear; sobran → bajar primero las que más ganaron.
            $residuo = [];
            foreach ($g['claves'] as $k) {
                $residuo[$k] = (float) $lineas[$k]['base'] * $g['pct'] / 100 - $iva[$k];
            }
            $orden = $g['claves'];
            usort($orden, static fn($a, $b) => $centavos > 0
                ? $residuo[$b] <=> $residuo[$a]
                : $residuo[$a] <=> $residuo[$b]);

            $paso = $centavos > 0 ? 0.01 : -0.01;
            $n    = count($orden);
            for ($i = 0; $centavos !== 0; $i = ($i + 1) % $n) {
                $iva[$orden[$i]] = round($iva[$orden[$i]] + $paso, 2);
                $centavos += $centavos > 0 ? -1 : 1;
            }
        }

        return $iva;
    }
}
