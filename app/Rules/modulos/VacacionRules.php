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

    /**
     * Marcar períodos ya tomados o pagados antes de usar el sistema.
     *
     * @param array<int, float> $dias      número de período => días a cerrar
     * @param array<int, array> $periodos  períodos del empleado (VacacionCalculoService::periodos()), por número
     * @param array<int, array> $marcados  períodos ya marcados del empleado, por número
     */
    public function validarMarcaPeriodos(string $estado, array $dias, array $periodos, array $marcados): void
    {
        if (!in_array($estado, ['tomado', 'pagado'], true)) {
            throw new Exception('Indique si los períodos fueron tomados o pagados.');
        }
        if (empty($dias)) {
            throw new Exception('Seleccione al menos un período.');
        }
        foreach ($dias as $numero => $d) {
            $p = $periodos[$numero] ?? null;
            if ($p === null) {
                throw new Exception("El período {$numero} no corresponde a la antigüedad del empleado.");
            }
            if (empty($p['completo'])) {
                throw new Exception("El período {$numero} todavía está en curso: solo se marcan años de trabajo completos.");
            }
            if (isset($marcados[$numero])) {
                throw new Exception("El período {$numero} ya está marcado. Actualice la ventana e inténtelo de nuevo.");
            }
            if ($d <= 0) {
                throw new Exception("Los días del período {$numero} deben ser mayores a cero.");
            }
            if ($d > (float) $p['dias_derecho']) {
                throw new Exception("El período {$numero} da derecho a {$p['dias_derecho']} días: no se pueden marcar {$d}.");
            }
        }
    }
}
