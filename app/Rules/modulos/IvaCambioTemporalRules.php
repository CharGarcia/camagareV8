<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

/**
 * Validaciones del cambio temporal de IVA en productos (prestadores de servicios turísticos).
 */
class IvaCambioTemporalRules
{
    public const APLICA_A = ['todos', 'servicios', 'bienes'];

    /**
     * Tope del período. Los feriados con IVA reducido duran de 1 a 5 días; un rango
     * de meses casi siempre es un error de digitación que dejaría la tarifa baja
     * mucho más tiempo del que permite el decreto.
     */
    public const MAX_DIAS = 31;

    public const MAX_OBSERVACIONES = 500;

    /**
     * @param array $d id_tarifa_actual, id_tarifa_nueva, aplica_a, fecha_desde, fecha_hasta, observaciones
     * @param array|null $tarifaActual fila de tarifa_iva (null si no existe)
     * @param array|null $tarifaNueva  fila de tarifa_iva (null si no existe)
     * @param string $hoy fecha de hoy (Y-m-d, hora de Ecuador)
     */
    public function validarProgramacion(array $d, ?array $tarifaActual, ?array $tarifaNueva, string $hoy): void
    {
        $errores = [];

        if ($tarifaActual === null) {
            $errores[] = 'Seleccione la tarifa actual.';
        }
        if ($tarifaNueva === null || (int) ($tarifaNueva['status'] ?? 0) !== 1) {
            $errores[] = 'Seleccione una tarifa nueva vigente.';
        }
        if ($tarifaActual !== null && $tarifaNueva !== null && (int) $tarifaActual['id'] === (int) $tarifaNueva['id']) {
            $errores[] = 'La tarifa nueva debe ser distinta de la actual.';
        }
        if (!in_array($d['aplica_a'] ?? '', self::APLICA_A, true)) {
            $errores[] = 'Indique a qué productos se aplica (todos, solo servicios o solo bienes).';
        }

        $desde = $this->fecha($d['fecha_desde'] ?? '');
        $hasta = $this->fecha($d['fecha_hasta'] ?? '');
        if ($desde === null || $hasta === null) {
            $errores[] = 'Indique las fechas desde y hasta.';
        } else {
            if ($desde < $hoy) {
                $errores[] = 'La fecha desde no puede ser anterior a hoy.';
            }
            if ($hasta < $desde) {
                $errores[] = 'La fecha hasta no puede ser anterior a la fecha desde.';
            } elseif ((new \DateTime($desde))->diff(new \DateTime($hasta))->days + 1 > self::MAX_DIAS) {
                $errores[] = 'El período no puede superar ' . self::MAX_DIAS . ' días.';
            }
        }

        if (mb_strlen((string) ($d['observaciones'] ?? '')) > self::MAX_OBSERVACIONES) {
            $errores[] = 'Las observaciones no pueden superar ' . self::MAX_OBSERVACIONES . ' caracteres.';
        }

        if ($errores) {
            throw new Exception(implode(' ', $errores));
        }
    }

    /** Devuelve la fecha en Y-m-d si es válida; null si no. */
    private function fecha(string $v): ?string
    {
        $v = trim($v);
        $dt = \DateTime::createFromFormat('!Y-m-d', $v);
        return ($dt && $dt->format('Y-m-d') === $v) ? $v : null;
    }
}
