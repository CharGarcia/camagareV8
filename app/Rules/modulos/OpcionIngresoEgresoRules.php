<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

class OpcionIngresoEgresoRules
{
    /** Largo máximo del nombre / concepto (mismo maxlength que modal_opcion.php). */
    public const MAX_NOMBRE = 50;

    public function validar(array $data): void
    {
        if (empty($data['nombre'])) {
            throw new Exception("El campo Nombre es obligatorio.");
        }

        if (mb_strlen(trim((string) $data['nombre'])) > self::MAX_NOMBRE) {
            throw new Exception('El Nombre / Concepto admite hasta ' . self::MAX_NOMBRE . ' caracteres.');
        }

        if (empty($data['aplica_ingresos']) && empty($data['aplica_egresos'])) {
            throw new Exception("Debe seleccionar al menos una aplicación (Ingresos o Egresos).");
        }
    }
}
