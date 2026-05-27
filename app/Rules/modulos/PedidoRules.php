<?php

namespace App\Rules\Modulos;

use Exception;

class PedidoRules {
    public static function validar($datos, $detalles) {
        if (empty($datos['id_cliente'])) {
            throw new Exception("Debe seleccionar un cliente.");
        }
        if (empty($detalles)) {
            throw new Exception("El pedido debe tener al menos un producto.");
        }
        foreach ($detalles as $det) {
            if ($det['cantidad'] <= 0) {
                throw new Exception("La cantidad de los productos debe ser mayor a cero.");
            }
        }
        return true;
    }
}
