<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

class PeriodosContablesRules
{
    public function validar(array $data): void
    {
        if (empty(trim($data['nombre'] ?? ''))) {
            throw new Exception('El nombre del periodo es requerido.');
        }

        if (empty(trim($data['fecha_inicial'] ?? ''))) {
            throw new Exception('La fecha inicial es requerida.');
        }

        if (empty(trim($data['fecha_final'] ?? ''))) {
            throw new Exception('La fecha final es requerida.');
        }

        if (strtotime($data['fecha_inicial']) > strtotime($data['fecha_final'])) {
            throw new Exception('La fecha inicial no puede ser mayor a la fecha final.');
        }
    }
}
