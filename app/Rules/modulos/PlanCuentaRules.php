<?php

declare(strict_types=1);

namespace App\Rules\modulos;

class PlanCuentaRules
{
    public function validate(array $data): void
    {
        if (empty($data['codigo'])) {
            throw new \Exception('El código de la cuenta es obligatorio.');
        }
        if (empty($data['nombre'])) {
            throw new \Exception('El nombre de la cuenta es obligatorio.');
        }
        if (empty($data['nivel'])) {
            throw new \Exception('El nivel de la cuenta es obligatorio.');
        }

        $nivel = (int) $data['nivel'];
        if ($nivel >= 1 && $nivel <= 4) {
            if ($data['nombre'] !== mb_strtoupper($data['nombre'])) {
                throw new \Exception('Las cuentas de nivel 1 al 4 deben estar en MAYÚSCULAS.');
            }
        }
    }
}
