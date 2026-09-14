<?php

namespace App\Rules\Modulos;

use Exception;

/**
 * Validaciones de negocio de Pedidos, compartidas por el controlador web
 * (PedidosController::guardarAjax) y el de la API móvil (api\v1\PedidosController::crear),
 * para no duplicar la regla en dos sitios.
 */
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

        self::validarFechaHoraEntrega($datos);

        return true;
    }

    /**
     * Fecha y horas de entrega. Antes vivía inline en PedidosController::guardarAjax().
     */
    public static function validarFechaHoraEntrega($datos): void
    {
        $fechaPedido = $datos['fecha_pedido'] ?? '';
        $fechaEntrega = $datos['fecha_entrega'] ?? '';
        $horaInicial = $datos['hora_inicial_entrega'] ?? '';
        $horaMaxima = $datos['hora_maxima_entrega'] ?? '';

        if (!empty($fechaEntrega)) {
            $today = date('Y-m-d');
            if ($fechaEntrega < $today) {
                throw new Exception('La fecha de entrega no puede ser menor a la fecha actual.');
            }
        }

        if (!empty($fechaPedido) && !empty($fechaEntrega) && $fechaEntrega < $fechaPedido) {
            throw new Exception('La fecha de entrega no puede ser menor a la fecha del pedido.');
        }

        // Formato de las horas. En el modal se escriben a mano (campo de texto con
        // máscara 00:00, sin el selector del navegador), así que el formato deja de
        // estar garantizado por el input: se valida aquí antes de tocar la BD, y
        // además la comparación de abajo es textual y solo es correcta con HH:MM.
        foreach (['hora inicial' => $horaInicial, 'hora máxima' => $horaMaxima] as $etiqueta => $hora) {
            if (!empty($hora) && !preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', (string) $hora)) {
                throw new Exception("La {$etiqueta} de entrega no tiene un formato válido (00:00).");
            }
        }

        if (!empty($horaInicial) && !empty($horaMaxima)) {
            if ($horaInicial > $horaMaxima) {
                throw new Exception('La hora inicial no puede ser mayor a la hora máxima de entrega.');
            }
            if ($horaInicial === $horaMaxima) {
                throw new Exception('La hora máxima no puede ser igual a la hora inicial.');
            }
        }
    }
}
