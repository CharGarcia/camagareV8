<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

class CentroCostoRules
{
    public function validar(array $data): void
    {
        if (empty($data['nombre'])) {
            throw new Exception("El nombre del centro de costos es obligatorio.");
        }

        if (strlen($data['nombre']) > 100) {
            throw new Exception("El nombre no puede exceder los 100 caracteres.");
        }

        if (!empty($data['codigo']) && strlen($data['codigo']) > 20) {
            throw new Exception("El código no puede exceder los 20 caracteres.");
        }
        
        if (empty($data['id_empresa'])) {
            throw new Exception("La empresa es obligatoria.");
        }
    }
}
