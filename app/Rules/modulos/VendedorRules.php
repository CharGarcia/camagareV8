<?php
declare(strict_types=1);

namespace App\Rules\modulos;

class VendedorRules
{
    /**
     * Valida los datos básicos de un vendedor.
     */
    public function validar(array $data): void
    {
        if (trim($data['nombre'] ?? '') === '') {
            throw new \InvalidArgumentException('El nombre del vendedor es obligatorio.');
        }

        if (trim($data['identificacion'] ?? '') === '') {
            throw new \InvalidArgumentException('La identificación del vendedor es obligatoria.');
        }

        if (!empty($data['correo']) && !filter_var($data['correo'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('El formato del correo electrónico no es válido.');
        }
    }
}
