<?php

declare(strict_types=1);

namespace App\Services\modulos;

/**
 * Cálculos del motor de producción, sin acceso a la base (docs/diseno/produccion-fase1.md §4):
 *  - escalar una fórmula a una cantidad (teórico de entradas, salidas y recursos);
 *  - importe de los recursos (MO, máquina, CIF);
 *  - reparto del costo conjunto entre la salida principal y los coproductos,
 *    descontando el valor de los subproductos y separando la merma anormal.
 *
 * Todo importe final va a 2 decimales y la última salida absorbe el redondeo, para que
 * lo que entra al inventario cuadre al centavo con el costo de la orden.
 */
class ProduccionCosteoService
{
    public const ROLES_SALIDA = ['principal', 'coproducto', 'subproducto', 'desperdicio'];

    private static function r2(float $v): float
    {
        // Mismo criterio que CMG_r2: medio hacia arriba sobre la representación decimal.
        return round((float) sprintf('%.10F', $v), 2);
    }

    private static function r6(float $v): float
    {
        return round($v, 6);
    }

    /**
     * Factor de escala: cuántas "veces" la cantidad base de la fórmula.
     */
    public static function factor(float $cantidadBase, float $cantidad): float
    {
        return $cantidadBase > 0 ? $cantidad / $cantidadBase : 0.0;
    }

    /**
     * Cantidad teórica de una línea de la fórmula para producir $cantidad de la salida principal.
     * Insumos: con su merma %. Salidas: rendimiento esperado (la principal es la cantidad pedida).
     */
    public static function cantidadTeorica(array $linea, float $cantidadBase, float $cantidad): float
    {
        $rol   = (string) $linea['rol'];
        $modo  = (string) ($linea['modo_cantidad'] ?? 'proporcional');
        $valor = (float) ($linea['cantidad'] ?? 0);

        if ($rol === 'principal') {
            return self::r6($cantidad);
        }

        $base = match ($modo) {
            'porcentaje' => $cantidad * $valor / 100,
            'fija'       => $valor,
            default      => $valor * self::factor($cantidadBase, $cantidad),
        };

        if ($rol === 'insumo') {
            $base *= 1 + ((float) ($linea['merma_pct'] ?? 0)) / 100;
        }
        return self::r6($base);
    }

    /**
     * Importe de un recurso.
     *  - unidad: cantidad × tarifa por cada unidad producida de la principal;
     *  - lote:   cantidad × tarifa una sola vez por orden;
     *  - hora:   horas de la cantidad base × factor × tarifa;
     *  - pct_mp: tarifa % sobre el costo de materiales.
     */
    public static function importeRecurso(array $recurso, float $cantidadBase, float $producida, float $costoMateriales): float
    {
        $cant   = (float) ($recurso['cantidad'] ?? 0);
        $tarifa = (float) ($recurso['tarifa'] ?? 0);
        $v = match ((string) ($recurso['base'] ?? 'unidad')) {
            'lote'   => $cant * $tarifa,
            'hora'   => $cant * self::factor($cantidadBase, $producida) * $tarifa,
            'pct_mp' => $costoMateriales * $tarifa / 100 * ($cant > 0 ? $cant : 1),
            default  => $cant * $tarifa * $producida,
        };
        return self::r2(max(0.0, $v));
    }

    /**
     * Costo de cierre de una orden.
     *
     * @param float $costoMateriales costo real de los consumos
     * @param float $costoManoObra   MO aplicada
     * @param float $costoCif        CIF + máquina aplicados
     * @param float $costoOtros      costos imputados (compras/servicios)
     * @param array $salidas  [['key'=>…, 'rol'=>…, 'cantidad'=>float, 'precio'=>float, 'reparto_pct'=>?float, 'vnr'=>?float], …]
     * @param string $metodo  valor_venta | unidades | porcentaje
     * @param float $mermaAnormal cantidad de la principal perdida de forma anormal
     *
     * @return array{total: float, subproductos: float, conjunto: float, merma: float,
     *               salidas: array<string, array{costo_total: float, costo_unitario: float}>}
     */
    public static function costearCierre(
        float $costoMateriales,
        float $costoManoObra,
        float $costoCif,
        float $costoOtros,
        array $salidas,
        string $metodo,
        float $mermaAnormal = 0.0
    ): array {
        $total = self::r2($costoMateriales) + self::r2($costoManoObra) + self::r2($costoCif) + self::r2($costoOtros);
        $total = self::r2($total);

        $res = [];

        // 1) Subproductos: a su valor neto realizable, sin pasar del costo total.
        $valorSub = 0.0;
        foreach ($salidas as $s) {
            if ($s['rol'] !== 'subproducto' || (float) $s['cantidad'] <= 0) {
                continue;
            }
            $v = self::r2((float) $s['cantidad'] * max(0.0, (float) ($s['vnr'] ?? 0)));
            if ($valorSub + $v > $total) {
                $v = self::r2($total - $valorSub);
            }
            $valorSub = self::r2($valorSub + $v);
            $res[$s['key']] = ['costo_total' => $v, 'costo_unitario' => self::r6($v / (float) $s['cantidad'])];
        }

        $conjunto = self::r2($total - $valorSub);

        // 2) Reparto del costo conjunto entre principal y coproductos.
        $conjuntas = array_values(array_filter(
            $salidas,
            fn($s) => in_array($s['rol'], ['principal', 'coproducto'], true) && (float) $s['cantidad'] > 0
        ));

        $pesos = [];
        foreach ($conjuntas as $s) {
            $pesos[$s['key']] = match ($metodo) {
                'unidades'   => (float) $s['cantidad'],
                'porcentaje' => max(0.0, (float) ($s['reparto_pct'] ?? 0)),
                default      => (float) $s['cantidad'] * max(0.0, (float) ($s['precio'] ?? 0)),
            };
        }
        $sumaPesos = array_sum($pesos);
        if ($sumaPesos <= 0) {
            // Sin precios ni porcentajes: se reparte por unidades físicas.
            foreach ($conjuntas as $s) {
                $pesos[$s['key']] = (float) $s['cantidad'];
            }
            $sumaPesos = array_sum($pesos);
        }

        $asignado = 0.0;
        $n = count($conjuntas);
        foreach ($conjuntas as $i => $s) {
            $v = ($i === $n - 1)
                ? self::r2($conjunto - $asignado)
                : self::r2($sumaPesos > 0 ? $conjunto * $pesos[$s['key']] / $sumaPesos : 0);
            $asignado = self::r2($asignado + $v);
            $res[$s['key']] = ['costo_total' => $v, 'costo_unitario' => self::r6($v / (float) $s['cantidad'])];
        }

        // 3) Merma anormal: sale del costo de la principal y va a gasto (NIC 2.16).
        $costoMerma = 0.0;
        foreach ($conjuntas as $s) {
            if ($s['rol'] !== 'principal' || $mermaAnormal <= 0) {
                continue;
            }
            $buenas = (float) $s['cantidad'];
            $actual = $res[$s['key']]['costo_total'];
            $costoMerma = self::r2($actual * $mermaAnormal / ($buenas + $mermaAnormal));
            $queda = self::r2($actual - $costoMerma);
            $res[$s['key']] = ['costo_total' => $queda, 'costo_unitario' => self::r6($queda / $buenas)];
        }

        // Desperdicio: sin valor.
        foreach ($salidas as $s) {
            if ($s['rol'] === 'desperdicio' && !isset($res[$s['key']])) {
                $res[$s['key']] = ['costo_total' => 0.0, 'costo_unitario' => 0.0];
            }
        }

        return [
            'total'        => $total,
            'subproductos' => $valorSub,
            'conjunto'     => $conjunto,
            'merma'        => $costoMerma,
            'salidas'      => $res,
        ];
    }
}
