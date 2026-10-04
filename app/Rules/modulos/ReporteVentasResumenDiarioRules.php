<?php

declare(strict_types=1);

namespace App\Rules\modulos;

/**
 * Validaciones del Resumen diario del Reporte de Ventas.
 *
 * El resumen va día por día y una de sus salidas es una tirilla térmica: sin tope, un
 * período de un año serían cientos de bloques de papel y varias consultas pesadas por
 * día. Se exige un período cerrado (desde y hasta) de hasta MAX_DIAS días.
 */
class ReporteVentasResumenDiarioRules
{
    public const MAX_DIAS = 31;

    public function validarPeriodo(string $desde, string $hasta): void
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $desde);
        $h = \DateTimeImmutable::createFromFormat('!Y-m-d', $hasta);
        if (!$d || !$h || $d->format('Y-m-d') !== $desde || $h->format('Y-m-d') !== $hasta) {
            throw new \InvalidArgumentException('El resumen diario necesita la Fecha Desde y la Fecha Hasta.');
        }
        if ($h < $d) {
            throw new \InvalidArgumentException('La Fecha Hasta no puede ser anterior a la Fecha Desde.');
        }
        if ($d->diff($h)->days + 1 > self::MAX_DIAS) {
            throw new \InvalidArgumentException('El resumen diario abarca hasta ' . self::MAX_DIAS
                . ' días. Acorte el período (por ejemplo, un mes).');
        }
    }
}
