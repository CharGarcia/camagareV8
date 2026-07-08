<?php

declare(strict_types=1);

namespace App\Services\modulos;

use DateTime;

/**
 * Cálculos de vacaciones (Ecuador): días de derecho por antigüedad, saldo y valor.
 *
 *  - Derecho por año de servicio: 15 días; desde el 6º año +1 día por cada año
 *    excedente, hasta un máximo de 15 días adicionales (máx 30). Art. 69 CT.
 *  - Valor ≈ (sueldo_base / 30) × días gozados (equivale a 1/24 anual para 15 días).
 */
class VacacionCalculoService
{
    public const DIAS_BASE = 15;
    public const EXTRA_MAX = 15;

    /** Días de derecho que se ganan en el n-ésimo año de servicio. */
    public function derechoDelAnio(int $n): int
    {
        return self::DIAS_BASE + max(0, min($n - 5, self::EXTRA_MAX));
    }

    /**
     * Antigüedad y derecho acumulado a una fecha de referencia.
     * @return array{anios_completos:int, anios_texto:string, derecho_anio_actual:int, total_derecho:float}
     */
    public function antiguedad(string $fechaIngreso, ?string $fechaRef = null): array
    {
        $ini = new DateTime(substr($fechaIngreso, 0, 10));
        $ref = new DateTime($fechaRef ? substr($fechaRef, 0, 10) : 'now');
        if ($ref < $ini) $ref = clone $ini;

        $diff = $ini->diff($ref);
        $aniosCompletos = (int) $diff->y;
        $fraccion = ($diff->m / 12) + ($diff->d / 365.25);

        // Derecho acumulado por cada año completo + proporcional del año en curso.
        $total = 0.0;
        for ($k = 1; $k <= $aniosCompletos; $k++) {
            $total += $this->derechoDelAnio($k);
        }
        $derechoActual = $this->derechoDelAnio($aniosCompletos + 1);
        $total += $derechoActual * $fraccion;

        return [
            'anios_completos'     => $aniosCompletos,
            'anios_texto'         => "{$diff->y} año(s), {$diff->m} mes(es), {$diff->d} día(s)",
            'derecho_anio_actual' => $derechoActual,
            'total_derecho'       => round($total, 2),
        ];
    }

    /** Valor a pagar de las vacaciones gozadas. */
    public function valor(float $sueldoBase, float $diasGozados): float
    {
        if ($sueldoBase <= 0 || $diasGozados <= 0) return 0.0;
        return round(($sueldoBase / 30) * $diasGozados, 2);
    }

    /** Saldo = derecho acumulado − días ya gozados. */
    public function saldo(float $totalDerecho, float $diasGozadosTotal): float
    {
        return round($totalDerecho - $diasGozadosTotal, 2);
    }
}
