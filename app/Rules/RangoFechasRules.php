<?php

declare(strict_types=1);

namespace App\Rules;

/**
 * Rango de fechas opcional (Y-m-d) de una consulta, como el estado de cuenta de las
 * fichas de proveedor y cliente. Cualquiera de los dos límites puede venir vacío (null).
 */
class RangoFechasRules
{
    public function validar(?string $desde, ?string $hasta): void
    {
        foreach (['Desde' => $desde, 'Hasta' => $hasta] as $etiqueta => $fecha) {
            if ($fecha === null) {
                continue;
            }
            $d = \DateTime::createFromFormat('!Y-m-d', $fecha);
            if (!$d || $d->format('Y-m-d') !== $fecha) {
                throw new \InvalidArgumentException("La fecha \"{$etiqueta}\" no es válida.");
            }
        }

        if ($desde !== null && $hasta !== null && $desde > $hasta) {
            throw new \InvalidArgumentException('La fecha "Desde" no puede ser mayor que la fecha "Hasta".');
        }
    }
}
