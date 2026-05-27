<?php
declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

class VehiculoRules
{
    /**
     * Valida los datos requeridos para crear/actualizar un vehículo.
     */
    public function validar(array $data): void
    {
        $errores = [];

        if (empty($data['id_empresa'])) {
            $errores[] = 'El identificador de la empresa es obligatorio.';
        }

        if (empty(trim($data['marca'] ?? ''))) {
            $errores[] = 'La marca es obligatoria.';
        }

        if (empty(trim($data['placa'] ?? ''))) {
            $errores[] = 'La placa es obligatoria.';
        }

        if (empty(trim($data['propietario'] ?? ''))) {
            $errores[] = 'El propietario es obligatorio.';
        }

        if (isset($data['anio']) && (!is_numeric($data['anio']) || $data['anio'] < 1900 || $data['anio'] > 2100)) {
            $errores[] = 'El año no es válido.';
        }

        if (!empty($data['correo']) && !filter_var($data['correo'], FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El correo electrónico no tiene un formato válido.';
        }

        if (!empty($data['telefono']) && !preg_match('/^[0-9]{10}$/', $data['telefono'])) {
            $errores[] = 'El teléfono debe contener exactamente 10 dígitos numéricos.';
        }

        if (!empty($errores)) {
            throw new Exception(implode(' ', $errores));
        }
    }
}
