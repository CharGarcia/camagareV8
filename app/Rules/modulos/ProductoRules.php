<?php
declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

class ProductoRules
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

        if (empty(trim($data['codigo'] ?? ''))) {
            $errores[] = 'El código del producto es obligatorio.';
        } elseif (mb_strlen(trim($data['codigo'])) > 50) {
            $errores[] = 'El código no puede exceder 50 caracteres.';
        }

        if (empty(trim($data['nombre'] ?? ''))) {
            $errores[] = 'El nombre del producto es obligatorio.';
        } elseif (mb_strlen(trim($data['nombre'])) > 200) {
            $errores[] = 'El nombre no puede exceder 200 caracteres.';
        }

        if (isset($data['precio_base']) && !is_numeric($data['precio_base'])) {
            $errores[] = 'El precio base debe ser un valor numérico.';
        } elseif (isset($data['precio_base']) && (float)$data['precio_base'] < 0) {
            $errores[] = 'El precio base no puede ser menor a cero.';
        }

        if (isset($data['tarifa_iva']) && !is_numeric($data['tarifa_iva'])) {
            $errores[] = 'La tarifa de IVA debe ser un valor numérico válido.';
        }

        if (!empty($errores)) {
            throw new Exception(implode(' ', $errores));
        }
    }
}
