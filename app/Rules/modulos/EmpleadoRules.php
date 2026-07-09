<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

class EmpleadoRules
{
    /**
     * Valida los datos obligatorios y lógica de negocio para un empleado.
     */
    public function validate(array $data): void
    {
        if (empty($data['tipo_id'])) {
            throw new Exception('El tipo de identificación es obligatorio.');
        }

        $identificacion = trim($data['identificacion'] ?? '');
        if (empty($identificacion)) {
            throw new Exception('La identificación es obligatoria.');
        }

        // Validación básica de cédula ecuatoriana si aplica
        if ($data['tipo_id'] === 'cedula' && strlen($identificacion) !== 10) {
            throw new Exception('La cédula debe tener exactamente 10 dígitos.');
        }

        if (empty($data['nombres_apellidos'])) {
            throw new Exception('Nombres y apellidos son obligatorios.');
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('El correo electrónico no tiene un formato válido.');
        }

        if (!empty($data['sexo']) && !in_array($data['sexo'], ['M', 'F', 'O'])) {
            throw new Exception('El sexo seleccionado no es válido.');
        }
        
        if (!empty($data['tipo_cuenta']) && !in_array($data['tipo_cuenta'], ['ahorros', 'corriente', 'virtual'])) {
            throw new Exception('El tipo de cuenta no es válido.');
        }

        if (!empty($data['fondos_reserva']) && !in_array($data['fondos_reserva'], ['rol', 'planilla', 'no_se_paga'])) {
            throw new Exception('Opción de fondos de reserva no válida.');
        }

        if (!empty($data['decimo_tercero']) && !in_array($data['decimo_tercero'], ['mensualiza', 'acumula'])) {
            throw new Exception('Opción de décimo tercero no válida.');
        }

        if (!empty($data['decimo_cuarto']) && !in_array($data['decimo_cuarto'], ['mensualiza', 'acumula'])) {
            throw new Exception('Opción de décimo cuarto no válida.');
        }

        if (!empty($data['region']) && !in_array($data['region'], ['costa', 'sierra', 'oriente', 'insular'])) {
            throw new Exception('Región seleccionada no válida.');
        }

        if (isset($data['sueldo_base']) && floatval($data['sueldo_base']) < 0) {
            throw new Exception('El sueldo base no puede ser negativo.');
        }

        if (isset($data['valor_semanal']) && floatval($data['valor_semanal']) < 0) {
            throw new Exception('El valor semanal no puede ser negativo.');
        }

        if (isset($data['valor_quincena']) && floatval($data['valor_quincena']) < 0) {
            throw new Exception('El valor de quincena no puede ser negativo.');
        }

        if (!empty($data['periodos']) && is_array($data['periodos'])) {
            $this->validatePeriodos($data['periodos']);
        }
    }

    /**
     * Valida el conjunto de períodos de empleo (ingreso/salida). Reglas:
     *  - No puede haber un período sin fecha de salida salvo el más reciente (no se
     *    permite agregar un período nuevo mientras exista uno anterior abierto).
     *  - La salida no puede ser anterior al ingreso.
     *  - Los períodos no pueden solaparse.
     */
    public function validatePeriodos(array $periodos): void
    {
        $ps = array_values(array_filter($periodos, fn($p) => !empty($p['fecha_ingreso'])));
        foreach ($ps as $p) $this->validarRangoPeriodo($p);

        if (count($ps) <= 1) return;

        // Ordenar por fecha de ingreso ascendente
        usort($ps, fn($a, $b) => strcmp($this->dia($a['fecha_ingreso']), $this->dia($b['fecha_ingreso'])));
        $n = count($ps);

        foreach ($ps as $i => $p) {
            $esUltimo = ($i === $n - 1);
            if (!$esUltimo && empty($p['fecha_salida'])) {
                throw new Exception('No puede agregar un nuevo período mientras exista un período anterior sin fecha de salida. Registre primero la fecha de salida del período abierto.');
            }
            if ($i > 0) {
                $prevSal = $ps[$i - 1]['fecha_salida'] ?? null;
                if (!empty($prevSal) && $this->dia($p['fecha_ingreso']) <= $this->dia($prevSal)) {
                    throw new Exception('Los períodos no pueden solaparse: la fecha de ingreso debe ser posterior a la fecha de salida del período anterior.');
                }
            }
        }
    }

    private function validarRangoPeriodo(array $p): void
    {
        $ing = $p['fecha_ingreso'] ?? null;
        $sal = $p['fecha_salida'] ?? null;
        if (!empty($ing) && !empty($sal) && $this->dia($sal) < $this->dia($ing)) {
            throw new Exception('La fecha de salida no puede ser anterior a la fecha de ingreso.');
        }
    }

    private function dia($fecha): string
    {
        return substr((string) $fecha, 0, 10);
    }
}
