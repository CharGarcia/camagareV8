<?php
declare(strict_types=1);

namespace App\Rules\modulos;

class CitaAgendaRules
{
    private const ESTADOS_VALIDOS = [
        'pendiente', 'confirmada', 'en_curso', 'completada', 'cancelada', 'no_asistio'
    ];

    public function validarCita(array $d): void
    {
        if (empty($d['fecha_inicio'])) {
            throw new \InvalidArgumentException('La fecha y hora de inicio es obligatoria.');
        }
        if (empty($d['fecha_fin'])) {
            throw new \InvalidArgumentException('La fecha y hora de fin es obligatoria.');
        }

        $inicio = strtotime($d['fecha_inicio']);
        $fin    = strtotime($d['fecha_fin']);

        if ($inicio === false || $inicio <= 0) {
            throw new \InvalidArgumentException('La fecha de inicio no es válida.');
        }
        if ($fin === false || $fin <= 0) {
            throw new \InvalidArgumentException('La fecha de fin no es válida.');
        }
        if ($fin <= $inicio) {
            throw new \InvalidArgumentException('La hora de fin debe ser posterior a la hora de inicio.');
        }

        // La fecha/hora de inicio no puede ser anterior al momento actual
        if ($inicio < time()) {
            throw new \InvalidArgumentException('La fecha y hora de inicio no puede ser anterior a la fecha y hora actual.');
        }

        if (!empty($d['estado']) && !in_array($d['estado'], self::ESTADOS_VALIDOS, true)) {
            throw new \InvalidArgumentException('Estado de cita no válido.');
        }

        if (!empty($d['titulo']) && mb_strlen($d['titulo']) > 200) {
            throw new \InvalidArgumentException('El título no puede superar los 200 caracteres.');
        }
    }
}
