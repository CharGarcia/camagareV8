<?php

declare(strict_types=1);

namespace App\Services\modulos;

use DateTime;

/**
 * Conteo de días laborados en base 30/360 (convención estándar de nómina
 * ecuatoriana), usado por los cálculos de Décimo Tercero y Décimo Cuarto.
 */
trait Dias360Trait
{
    /**
     * Días laborados (base 30/360) dentro de [periodoDesde, periodoHasta], sumando
     * todos los períodos de empleo del trabajador (empleado_periodos) que se
     * traslapen con el rango. Tope 360.
     *
     * @param array<int, array{fecha_ingreso:string, fecha_salida:?string}> $periodosEmpleado
     */
    public function diasLaborados360(array $periodosEmpleado, string $periodoDesde, string $periodoHasta): int
    {
        $desde = new DateTime($periodoDesde);
        $hasta = new DateTime($periodoHasta);
        $total = 0;

        foreach ($periodosEmpleado as $p) {
            $ingreso = new DateTime(substr($p['fecha_ingreso'], 0, 10));
            $salida  = !empty($p['fecha_salida']) ? new DateTime(substr($p['fecha_salida'], 0, 10)) : $hasta;

            $efDesde = $ingreso > $desde ? $ingreso : $desde;
            $efHasta = $salida < $hasta ? $salida : $hasta;
            if ($efDesde > $efHasta) continue;

            $total += $this->dias360($efDesde, $efHasta) + 1;
        }

        return min($total, 360);
    }

    /** Conteo de días entre dos fechas en base 30/360 (convención estándar de nómina). */
    private function dias360(DateTime $d1, DateTime $d2): int
    {
        $d1d = min((int) $d1->format('d'), 30);
        $d2d = (int) $d2->format('d');
        if ($d1d === 30 && $d2d === 31) $d2d = 30;
        $d2d = min($d2d, 30);

        return (((int) $d2->format('Y') - (int) $d1->format('Y')) * 360)
            + (((int) $d2->format('m') - (int) $d1->format('m')) * 30)
            + ($d2d - $d1d);
    }
}
