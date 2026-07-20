<?php

declare(strict_types=1);

namespace App\Services\modulos;

/**
 * Cálculo del Décimo Tercero Sueldo (Ecuador, Art. 111 Código de Trabajo).
 *
 *  - Período: 1-diciembre del año anterior a 30-noviembre del año de declaración.
 *  - Fecha límite de pago: 24 de diciembre (única para todo el país, sin región).
 *  - Valor = total percibido en el período / 12 (proporcional por construcción:
 *    si trabajó menos meses, el total_ganado ya es menor).
 *  - Si el empleado mensualiza, ya se pagó en el rol mes a mes: valor = 0 aquí
 *    (solo se informa al Ministerio, no se vuelve a pagar).
 */
class DecimoTerceroCalculoService
{
    use Dias360Trait;

    /**
     * Período de cálculo y fecha límite de pago para un año de declaración.
     * @return array{fecha_desde:string, fecha_hasta:string, fecha_limite:string}
     */
    public function periodoNacional(int $anio): array
    {
        return [
            'fecha_desde'  => ($anio - 1) . '-12-01',
            'fecha_hasta'  => $anio . '-11-30',
            'fecha_limite' => $anio . '-12-24',
        ];
    }

    /** Valor del décimo tercero: 0 si ya se paga mensualizado, si no total_ganado/12. */
    public function valor(float $totalGanado, bool $mensualiza): float
    {
        if ($mensualiza || $totalGanado <= 0) return 0.0;
        return round($totalGanado / 12, 2);
    }
}
