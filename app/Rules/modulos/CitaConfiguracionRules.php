<?php
declare(strict_types=1);

namespace App\Rules\modulos;

class CitaConfiguracionRules
{
    public function validarTipo(array $data): void
    {
        if (trim($data['nombre'] ?? '') === '') {
            throw new \InvalidArgumentException('El nombre del tipo de cita es obligatorio.');
        }
        $duracion = (int)($data['duracion_minutos'] ?? 0);
        if ($duracion < 5 || $duracion > 480) {
            throw new \InvalidArgumentException('La duración debe estar entre 5 y 480 minutos.');
        }
        if ((float)($data['precio'] ?? 0) < 0) {
            throw new \InvalidArgumentException('El precio no puede ser negativo.');
        }
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', trim($data['color'] ?? ''))) {
            throw new \InvalidArgumentException('El color debe ser un código hexadecimal válido (#RRGGBB).');
        }
        if (!in_array($data['tipo_pago'] ?? '', ['sin_pago', 'total', 'anticipo'], true)) {
            throw new \InvalidArgumentException('El tipo de pago no es válido.');
        }
        if (($data['tipo_pago'] ?? '') === 'anticipo') {
            $ant = (float)($data['anticipo_porcentaje'] ?? 0);
            if ($ant <= 0 || $ant >= 100) {
                throw new \InvalidArgumentException('El porcentaje de anticipo debe estar entre 1 y 99.');
            }
        }
    }

    public function validarRecurso(array $data): void
    {
        if (trim($data['nombre'] ?? '') === '') {
            throw new \InvalidArgumentException('El nombre del recurso es obligatorio.');
        }
        if (!in_array($data['tipo'] ?? '', ['persona', 'sala', 'equipo'], true)) {
            throw new \InvalidArgumentException('El tipo debe ser: persona, sala o equipo.');
        }
    }

    public function validarHorario(array $data): void
    {
        $dia = (int)($data['dia_semana'] ?? 0);
        if ($dia < 1 || $dia > 7) {
            throw new \InvalidArgumentException('El día de la semana no es válido.');
        }
        if (empty(trim($data['hora_inicio'] ?? '')) || empty(trim($data['hora_fin'] ?? ''))) {
            throw new \InvalidArgumentException('La hora de inicio y fin son obligatorias.');
        }
        if ($data['hora_inicio'] >= $data['hora_fin']) {
            throw new \InvalidArgumentException('La hora de fin debe ser posterior a la hora de inicio.');
        }
    }

    public function validarPortal(array $data): void
    {
        $slug = trim($data['slug'] ?? '');
        if ($slug === '') {
            throw new \InvalidArgumentException('El slug del portal es obligatorio.');
        }
        if (!preg_match('/^[a-z0-9\-]{3,100}$/', $slug)) {
            throw new \InvalidArgumentException('El slug solo puede contener letras minúsculas, números y guiones (mínimo 3 caracteres).');
        }
        $maxDias = (int)($data['max_dias_anticipacion'] ?? 0);
        if ($maxDias < 1 || $maxDias > 365) {
            throw new \InvalidArgumentException('Los días máximos de anticipación deben estar entre 1 y 365.');
        }
        $minHoras = (int)($data['min_horas_anticipacion'] ?? -1);
        if ($minHoras < 0 || $minHoras > 168) {
            throw new \InvalidArgumentException('Las horas mínimas de anticipación deben estar entre 0 y 168.');
        }
    }
}
