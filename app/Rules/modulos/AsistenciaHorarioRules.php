<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

class AsistenciaHorarioRules
{
    public function validate(array $data): void
    {
        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($nombre === '') {
            throw new Exception('El nombre del horario es obligatorio.');
        }

        foreach (['hora_entrada' => 'entrada', 'hora_salida' => 'salida'] as $campo => $etq) {
            $val = trim((string) ($data[$campo] ?? ''));
            if ($val === '' || !preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $val)) {
                throw new Exception("La hora de {$etq} es obligatoria y debe tener formato HH:MM.");
            }
        }

        $tol = (int) ($data['tolerancia_min'] ?? 5);
        if ($tol < 0 || $tol > 240) {
            throw new Exception('La tolerancia debe estar entre 0 y 240 minutos.');
        }

        $horas = (float) ($data['horas_jornada'] ?? 8);
        if ($horas <= 0 || $horas > 24) {
            throw new Exception('Las horas de jornada deben estar entre 0 y 24.');
        }

        // dias_semana: lista de 1..7 separada por comas.
        $dias = trim((string) ($data['dias_semana'] ?? '1,2,3,4,5'));
        if ($dias !== '') {
            foreach (explode(',', $dias) as $d) {
                $d = trim($d);
                if ($d !== '' && (!ctype_digit($d) || (int) $d < 1 || (int) $d > 7)) {
                    throw new Exception('Los días de la semana deben ser números del 1 (lunes) al 7 (domingo).');
                }
            }
        }

        if (!empty($data['estado']) && !in_array($data['estado'], ['activo', 'inactivo'], true)) {
            throw new Exception('El estado del horario no es válido.');
        }
    }

    /** Validación de la asignación de un horario a un empleado. */
    public function validateAsignacion(array $data): void
    {
        if (empty($data['id_empleado']) || (int) $data['id_empleado'] <= 0) {
            throw new Exception('Debe seleccionar un empleado.');
        }
        if (empty($data['id_horario']) || (int) $data['id_horario'] <= 0) {
            throw new Exception('Debe seleccionar un horario.');
        }
        $desde = trim((string) ($data['vigente_desde'] ?? ''));
        if ($desde === '' || strtotime($desde) === false) {
            throw new Exception('La fecha "vigente desde" es obligatoria y debe ser válida.');
        }
        $hasta = trim((string) ($data['vigente_hasta'] ?? ''));
        if ($hasta !== '') {
            if (strtotime($hasta) === false) {
                throw new Exception('La fecha "vigente hasta" no es válida.');
            }
            if (strtotime($hasta) < strtotime($desde)) {
                throw new Exception('La fecha "vigente hasta" no puede ser anterior a "vigente desde".');
            }
        }
    }
}
