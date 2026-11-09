<?php

declare(strict_types=1);

namespace App\Rules\modulos;

/** CRM: reglas de actividades. */
class CrmActividadRules
{
    public const TIPOS = ['tarea', 'llamada', 'reunion', 'nota', 'correo'];

    public function validar(array $d): void
    {
        if (!in_array($d['tipo'] ?? '', self::TIPOS, true)) {
            throw new \InvalidArgumentException('Tipo de actividad no válido.');
        }
        $titulo = trim((string) ($d['titulo'] ?? ''));
        if ($titulo === '') {
            throw new \InvalidArgumentException('Escriba el título de la actividad.');
        }
        if (mb_strlen($titulo) > 200) {
            throw new \InvalidArgumentException('El título no puede superar 200 caracteres.');
        }
        if (empty($d['id_oportunidad']) && empty($d['id_contacto'])) {
            throw new \InvalidArgumentException('La actividad debe pertenecer a una oportunidad o a un contacto.');
        }
        $v = (string) ($d['vence_at'] ?? '');
        if ($v !== '' && self::normalizarFechaHora($v) === null) {
            throw new \InvalidArgumentException('La fecha de vencimiento no es válida.');
        }
        if ($d['tipo'] !== 'nota' && $v === '') {
            throw new \InvalidArgumentException('Indique para cuándo es la actividad.');
        }
    }

    /** Acepta `Y-m-d`, `Y-m-d H:i`, `Y-m-d\TH:i` (input datetime-local) y con segundos. */
    public static function normalizarFechaHora(string $v): ?string
    {
        $v = str_replace('T', ' ', trim($v));
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'] as $fmt) {
            $dt = \DateTime::createFromFormat('!' . $fmt, $v);
            if ($dt !== false && $dt->format($fmt) === $v) {
                return $fmt === 'Y-m-d' ? $dt->format('Y-m-d 23:59:00') : $dt->format('Y-m-d H:i:s');
            }
        }
        return null;
    }
}
