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
        // Todo ítem del menú necesita un IVA con el que facturarse: si no hay
        // producto vinculado (que ya trae el suyo), la tarifa del propio ítem
        // es obligatoria — el menú es su propio catálogo vendible, igual que
        // productos, y ninguna línea puede llegar a la factura sin IVA.
        if (empty($data['id_producto']) && empty($data['id_tarifa_iva'])) {
            $errores[] = 'Selecciona la tarifa de IVA del ítem (obligatoria cuando no tiene un producto vinculado).';
        }

        if (!empty($errores)) {
            throw new Exception(implode(' ', $errores));
        }
    }
}
