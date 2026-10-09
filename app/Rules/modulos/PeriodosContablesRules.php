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

    /** Un período no puede superponerse con otro: no se sabría si esas fechas están bloqueadas. */
    public function validarSinCruce(?array $otro): void
    {
        if ($otro === null) {
            return;
        }
        $f = static fn($d) => date('d-m-Y', strtotime((string) $d));
        throw new Exception('Las fechas se cruzan con el período "' . $otro['nombre'] . '" ('
            . $f($otro['fecha_inicial']) . ' a ' . $f($otro['fecha_final']) . '). Ajuste las fechas para que no se superpongan.');
    }

    /**
     * Reabrir un período cerrado puede dejar la contabilidad distinta de lo ya declarado: lo
     * hace solo quien tiene acceso total, con motivo, y nunca dentro de un año cerrado con el
     * Cierre del Ejercicio (ese año se reabre revirtiendo el cierre).
     */
    public function validarReapertura(bool $puedeReabrir, string $motivo, ?int $anioCerrado): void
    {
        if (!$puedeReabrir) {
            throw new Exception('Solo un usuario con acceso total en Periodos Contables puede reabrir un período cerrado.');
        }
        if ($anioCerrado !== null) {
            throw new Exception("El período pertenece al ejercicio {$anioCerrado}, cerrado con el Cierre del Ejercicio. Para reabrirlo, revierta ese cierre.");
        }
        if (mb_strlen(trim($motivo)) < 5) {
            throw new Exception('Indique el motivo de la reapertura (al menos 5 caracteres).');
        }
    }

    /** Las fechas de un período cerrado no se mueven: achicarlo desbloquearía días sin reabrirlo. */
    public function validarFechasCerrado(array $antes, array $data): void
    {
        $fi = substr((string) $antes['fecha_inicial'], 0, 10);
        $ff = substr((string) $antes['fecha_final'], 0, 10);
        if (trim((string) $data['fecha_inicial']) !== $fi || trim((string) $data['fecha_final']) !== $ff) {
            throw new Exception('No se pueden cambiar las fechas de un período cerrado. Reábralo primero (indicando el motivo) y luego cambie las fechas.');
        }
    }

    /** Eliminar un período cerrado lo desbloquearía sin dejar rastro de una reapertura. */
    public function validarEliminar(array $periodo): void
    {
        if ((int) ($periodo['status'] ?? 1) === 0) {
            throw new Exception('No se puede eliminar un período cerrado. Reábralo primero (indicando el motivo) y luego elimínelo.');
        }
    }
}
