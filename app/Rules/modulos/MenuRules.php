<?php
declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

class MenuRules
{
    public function validar(array $data): void
    {
        $errores = [];

        if (empty($data['id_empresa'])) {
            $errores[] = 'El identificador de la empresa es obligatorio.';
        }
        if (empty(trim($data['nombre'] ?? ''))) {
            $errores[] = 'El nombre del ítem es obligatorio.';
        } elseif (mb_strlen(trim($data['nombre'])) > 200) {
            $errores[] = 'El nombre no puede exceder 200 caracteres.';
        }
        if ((float) ($data['precio'] ?? -1) < 0) {
            $errores[] = 'El precio no puede ser negativo.';
        }
        // El producto vinculado es obligatorio: sin él el ítem no se puede cobrar
        // (ComandaService rechaza al cobrar cualquier línea sin producto), no
        // mueve inventario y no tiene de dónde tomar precio ni foto. La carta es
        // una forma de presentar el catálogo, no un catálogo aparte.
        if (empty($data['id_producto'])) {
            $errores[] = 'Selecciona el producto vinculado: todo ítem del menú debe apuntar a un producto.';
        }
        // Todo ítem necesita además un IVA con el que facturarse. La tarifa del
        // ítem manda sobre la del producto vinculado (ver
        // MenuRepository::getDisponibles); si se deja vacía se hereda la del
        // producto, así que solo falta cuando tampoco hay producto.
        if (empty($data['id_producto']) && empty($data['id_tarifa_iva'])) {
            $errores[] = 'Selecciona la tarifa de IVA del ítem.';
        }

        if (!empty($errores)) {
            throw new Exception(implode(' ', $errores));
        }
    }
}
