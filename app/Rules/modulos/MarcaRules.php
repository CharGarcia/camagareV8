<?php
declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

class MarcaRules
{
    /**
     * Valida los datos requeridos para crear/actualizar una marca.
     */
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
            $errores[] = 'El nombre de la marca es obligatorio.';
        } elseif (mb_strlen(trim($data['nombre'])) > 100) {
            $errores[] = 'El nombre de la marca no puede exceder 100 caracteres.';
        }

        if (!empty($errores)) {
            throw new Exception(implode(' ', $errores));
        }
    }
}
