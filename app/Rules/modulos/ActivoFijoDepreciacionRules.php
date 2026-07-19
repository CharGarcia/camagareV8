<?php

declare(strict_types=1);

namespace App\Rules\modulos;

class ActivoFijoDepreciacionRules
{
    public function validarPeriodo(int $anio, int $mes): void
    {
        if ($mes < 1 || $mes > 12) {
            throw new \Exception('Mes de período no válido.');
        }
        if ($anio < 2000 || $anio > 2100) {
            throw new \Exception('Año de período no válido.');
        }

        $finPeriodo = mktime(23, 59, 59, $mes, (int) date('t', mktime(0, 0, 0, $mes, 1, $anio)), $anio);
        if ($finPeriodo > time()) {
            throw new \Exception('No se puede generar la depreciación de un período futuro.');
        }
    }
}
