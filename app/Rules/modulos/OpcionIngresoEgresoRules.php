<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

class OpcionIngresoEgresoRules
{
    public function validar(array $data): void
    {
        if (empty($data['nombre'])) {
            throw new Exception("El campo Nombre es obligatorio.");
        }

        if (empty($data['aplica_ingresos']) && empty($data['aplica_egresos'])) {
            throw new Exception("Debe seleccionar al menos una aplicación (Ingresos o Egresos).");
        }
    }
}
