<?php

declare(strict_types=1);

namespace App\Rules\modulos;

/**
 * Validaciones del Resumen diario: el reporte es de UN día.
 */
class ReporteResumenDiarioRules
{
    /** Devuelve la fecha normalizada (Y-m-d) o lanza InvalidArgumentException. */
    public function validarFecha(string $fecha): string
    {
        $fecha = trim($fecha);
        if ($fecha === '') {
            throw new \InvalidArgumentException('Elija el día del resumen.');
        }
        $d = \DateTime::createFromFormat('!Y-m-d', $fecha);
        if (!$d || $d->format('Y-m-d') !== $fecha) {
            throw new \InvalidArgumentException('La fecha del resumen no es válida.');
        }
        return $fecha;
    }
}
