<?php
declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

class AlumnoRules
{
    private const CEDULA = '05';
    private const PASAPORTE = '06';

    public function validar(array $data): void
    {
        if (trim($data['nombres'] ?? '') === '') {
            throw new Exception('Los nombres del alumno son obligatorios.');
        }
        if (trim($data['apellidos'] ?? '') === '') {
            throw new Exception('Los apellidos del alumno son obligatorios.');
        }
        if (empty($data['id_cliente'])) {
            throw new Exception('El representante (cliente que factura) es obligatorio.');
        }

        $tipoId = trim($data['tipo_identificacion'] ?? '');
        $identificacion = trim($data['numero_identificacion'] ?? '');
        if ($tipoId !== '' && $identificacion !== '') {
            if ($tipoId === self::CEDULA && !preg_match('/^[0-9]{10}$/', $identificacion)) {
                throw new Exception('La cédula del alumno debe tener exactamente 10 dígitos numéricos.');
            }
            if ($tipoId === self::PASAPORTE && mb_strlen($identificacion) > 20) {
                throw new Exception('El pasaporte del alumno no puede exceder 20 caracteres.');
            }
        }

        if (!empty($data['sexo']) && !in_array($data['sexo'], ['M', 'F', 'O'], true)) {
            throw new Exception('El sexo del alumno no es válido.');
        }

        $estadosValidos = ['activo', 'retirado', 'egresado', 'suspendido'];
        if (!empty($data['estado_academico']) && !in_array($data['estado_academico'], $estadosValidos, true)) {
            throw new Exception('El estado académico no es válido.');
        }

        if (!empty($data['periodos']) && is_array($data['periodos'])) {
            $this->validarPeriodos($data['periodos']);
        }
        if (!empty($data['horarios']) && is_array($data['horarios'])) {
            $this->validarHorarios($data['horarios']);
        }
    }

    /**
     * Máximo un período abierto (matrícula vigente) y sin solapes entre
     * períodos del mismo alumno. Mismo criterio que EmpleadoRules::validatePeriodos.
     */
    public function validarPeriodos(array $periodos): void
    {
        $ps = array_values(array_filter($periodos, fn($p) => !empty($p['fecha_ingreso'])));
        if (count($ps) === 0) {
            return;
        }

        usort($ps, fn($a, $b) => strcmp($a['fecha_ingreso'], $b['fecha_ingreso']));

        $abiertos = 0;
        foreach ($ps as $p) {
            if (!empty($p['fecha_salida']) && $p['fecha_salida'] < $p['fecha_ingreso']) {
                throw new Exception('La fecha de salida de una matrícula no puede ser anterior a la fecha de ingreso.');
            }
            if (empty($p['fecha_salida'])) {
                $abiertos++;
            }
        }
        if ($abiertos > 1) {
            throw new Exception('El alumno no puede tener más de una matrícula vigente (sin fecha de salida) al mismo tiempo.');
        }

        $n = count($ps);
        for ($i = 1; $i < $n; $i++) {
            $prevSalida = $ps[$i - 1]['fecha_salida'] ?? null;
            if (!empty($prevSalida) && $ps[$i]['fecha_ingreso'] <= $prevSalida) {
                throw new Exception('Los períodos de matrícula no pueden solaparse entre sí.');
            }
        }
    }

    public function validarHorarios(array $horarios): void
    {
        foreach ($horarios as $h) {
            if (empty($h['dia_semana']) || empty($h['hora_inicio']) || empty($h['hora_fin'])) {
                continue;
            }
            if ($h['hora_fin'] <= $h['hora_inicio']) {
                throw new Exception('La hora de fin del horario debe ser posterior a la hora de inicio.');
            }
        }
    }
}
