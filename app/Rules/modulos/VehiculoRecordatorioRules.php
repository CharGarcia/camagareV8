<?php
declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

class VehiculoRecordatorioRules
{
    /**
     * Valida un recordatorio agregado a mano. La fecha no puede quedar en el pasado,
     * salvo que se esté editando uno ya vencido sin cambiarle la fecha.
     */
    public function validar(array $data, ?string $fechaAnterior = null): void
    {
        $errores = [];

        $fecha = (string) ($data['fecha'] ?? '');
        $d = \DateTime::createFromFormat('Y-m-d', $fecha);
        if (!$d || $d->format('Y-m-d') !== $fecha) {
            $errores[] = 'La fecha del recordatorio no es válida.';
        } elseif ($fecha < date('Y-m-d') && $fecha !== $fechaAnterior) {
            $errores[] = 'La fecha del recordatorio no puede ser anterior a hoy.';
        }

        $motivo = trim((string) ($data['motivo'] ?? ''));
        if ($motivo === '') {
            $errores[] = 'El motivo es obligatorio.';
        } elseif (mb_strlen($motivo) > 200) {
            $errores[] = 'El motivo no puede pasar de 200 caracteres.';
        }

        if (!empty($errores)) {
            throw new Exception(implode(' ', $errores));
        }
    }
}
