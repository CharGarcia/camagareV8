<?php

declare(strict_types=1);

namespace App\Services\modulos;

use DateTime;

/**
 * Cálculo del Décimo Cuarto Sueldo (Ecuador, Art. 113 Código de Trabajo).
 *
 *  - Costa y Galápagos/Insular: período 1-marzo a 28/29-febrero, límite de pago 15-marzo.
 *  - Sierra y Amazonía: período 1-agosto a 31-julio, límite de pago 15-agosto.
 *  - Valor = SBU del año de declaración, prorrateado por días laborados (base 360).
 *  - Si el empleado mensualiza, ya se pagó en el rol mes a mes: valor = 0 aquí
 *    (solo se informa al Ministerio, no se vuelve a pagar).
 */
class DecimoCuartoCalculoService
{
    public const DIAS_ANIO = 360;

    /** Mapea empleados.region a los dos grupos que reconoce el Ministerio. */
    public function grupoRegion(string $region): string
    {
        return in_array($region, ['sierra', 'oriente'], true) ? 'sierra_amazonia' : 'costa_insular';
    }

    /**
     * Período de cálculo y fecha límite de pago para un grupo de región y año de declaración.
     * @return array{fecha_desde:string, fecha_hasta:string, fecha_limite:string}
     */
    public function periodoPorRegion(string $regionGrupo, int $anio): array
    {
        if ($regionGrupo === 'sierra_amazonia') {
            return [
                'fecha_desde'  => ($anio - 1) . '-08-01',
                'fecha_hasta'  => $anio . '-07-31',
                'fecha_limite' => $anio . '-08-15',
            ];
        }
        $ultimoFebrero = (new DateTime($anio . '-02-01'))->modify('last day of this month')->format('Y-m-d');
        return [
            'fecha_desde'  => ($anio - 1) . '-03-01',
            'fecha_hasta'  => $ultimoFebrero,
            'fecha_limite' => $anio . '-03-15',
        ];
    }

    /**
     * Días laborados (base 30/360) dentro del período, sumando todos los
     * períodos de empleo del trabajador (empleado_periodos) que se traslapen
     * con [periodoDesde, periodoHasta]. Tope 360.
     *
     * @param array<int, array{fecha_ingreso:string, fecha_salida:?string}> $periodosEmpleado
     */
    public function diasLaborados(array $periodosEmpleado, string $periodoDesde, string $periodoHasta): int
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

        return min($total, self::DIAS_ANIO);
    }

    /** Valor del décimo cuarto: 0 si ya se paga mensualizado, si no SBU proporcional a días/360. */
    public function valor(float $sbu, int $dias, bool $mensualiza): float
    {
        if ($mensualiza || $sbu <= 0 || $dias <= 0) return 0.0;
        return round($sbu * min($dias, self::DIAS_ANIO) / self::DIAS_ANIO, 2);
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
