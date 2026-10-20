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
     * Fecha de emisión y fecha/horas de entrega. Antes vivía inline en PedidosController::guardarAjax().
     */
    public static function validarFechaHoraEntrega($datos): void
    {
        $fechaPedido = $datos['fecha_pedido'] ?? '';
        $fechaEntrega = $datos['fecha_entrega'] ?? '';
        $horaInicial = $datos['hora_inicial_entrega'] ?? '';
        $horaMaxima = $datos['hora_maxima_entrega'] ?? '';

        $today = date('Y-m-d');

        // Fecha de emisión: solo al crear. Al editar se respeta la original, para que un
        // pedido de días anteriores se pueda seguir guardando. Se compara solo el día:
        // los pedidos generados desde una proforma traen también la hora.
        if (empty($datos['id']) && !empty($fechaPedido) && substr((string) $fechaPedido, 0, 10) < $today) {
            throw new Exception('La fecha de emisión no puede ser anterior a la fecha actual.');
        }

        if (!empty($fechaEntrega) && $fechaEntrega < $today) {
            throw new Exception('La fecha de entrega no puede ser menor a la fecha actual.');
        }

        if (!empty($fechaPedido) && !empty($fechaEntrega) && $fechaEntrega < substr((string) $fechaPedido, 0, 10)) {
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

        // Las dos horas pueden ser iguales (entrega a una hora exacta); solo se
        // rechaza que la inicial quede después de la máxima.
        if (!empty($horaInicial) && !empty($horaMaxima) && $horaInicial > $horaMaxima) {
            throw new Exception('La hora inicial no puede ser mayor a la hora máxima de entrega.');
        }

        // Entrega hoy: la ventana no puede haber terminado ya. Se mira la hora máxima;
        // la inicial sí puede haber pasado (entre 14:00 y 18:00 siendo las 15:00).
        if ($fechaEntrega === $today && !empty($horaMaxima) && substr((string) $horaMaxima, 0, 5) < date('H:i')) {
            throw new Exception('La hora máxima de entrega no puede ser anterior a la hora actual.');
        }
    }
}
