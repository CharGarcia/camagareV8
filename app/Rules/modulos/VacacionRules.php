<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

class VacacionRules
{
    public function validate(array $data): void
    {
        if (empty($data['id_empleado'])) {
            throw new Exception('Debe seleccionar un empleado.');
        }
        $desde = trim((string) ($data['fecha_desde'] ?? ''));
        $hasta = trim((string) ($data['fecha_hasta'] ?? ''));
        if ($desde === '' || $hasta === '') {
            throw new Exception('Las fechas desde y hasta son obligatorias.');
        }
        if (strtotime($hasta) < strtotime($desde)) {
            throw new Exception('La fecha hasta no puede ser anterior a la fecha desde.');
        }
        if ((float) ($data['dias_gozados'] ?? 0) <= 0) {
            throw new Exception('Los días gozados deben ser mayores a cero.');
        }
        $mes = (int) ($data['periodo_mes'] ?? 0);
        if ($mes < 1 || $mes > 12) {
            throw new Exception('El mes del rol debe estar entre 1 y 12.');
        }
        if (!empty($data['estado']) && !in_array($data['estado'], ['registrado', 'pagado', 'anulado'], true)) {
            throw new Exception('El estado no es válido.');
        }
    }
}
