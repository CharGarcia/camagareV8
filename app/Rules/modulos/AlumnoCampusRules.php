<?php
declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

class AlumnoCampusRules
{
    public function validar(array $data): void
    {
        $errores = [];

        if (empty($data['id_empresa'])) {
            $errores[] = 'El identificador de la empresa es obligatorio.';
        }
        if (empty($data['id_usuario'])) {
            $errores[] = 'El usuario responsable es obligatorio.';
        }
        if (empty(trim($data['nombre'] ?? ''))) {
            $errores[] = 'El nombre del campus es obligatorio.';
        } elseif (mb_strlen(trim($data['nombre'])) > 150) {
            $errores[] = 'El nombre del campus no puede exceder 150 caracteres.';
        }

        if (!empty($errores)) {
            throw new Exception(implode(' ', $errores));
        }
    }
}
