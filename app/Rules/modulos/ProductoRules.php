<?php
declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

class ProductoRules
{
    /**
     * Largo máximo del nombre. Es el mismo tope que el SRI admite en la
     * descripción de cada ítem del comprobante (ver SriFichaTecnica): el nombre
     * del producto es lo que viaja en esa etiqueta, así que no tiene sentido
     * aceptar uno más largo ni cortar uno que el SRI sí recibiría.
     */
    public const MAX_NOMBRE = 300;

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
        } elseif (mb_strlen(trim($data['nombre'])) > self::MAX_NOMBRE) {
            $errores[] = 'El nombre no puede exceder ' . self::MAX_NOMBRE . ' caracteres.';
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
