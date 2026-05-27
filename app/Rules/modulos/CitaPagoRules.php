<?php
declare(strict_types=1);

namespace App\Rules\modulos;

class CitaPagoRules
{
    private const ESTADOS_VALIDOS  = ['pendiente', 'completado', 'fallido', 'reembolsado'];
    private const GATEWAYS_VALIDOS = ['stripe', 'paypal', 'transferencia', 'sitio', 'efectivo', 'tarjeta'];
    private const TIPOS_VALIDOS    = ['total', 'anticipo'];

    public function validarPago(array $d): void
    {
        if (empty($d['id_cita']) || (int)$d['id_cita'] <= 0) {
            throw new \InvalidArgumentException('Debe seleccionar una cita válida.');
        }
        if (!isset($d['monto']) || (float)$d['monto'] <= 0) {
            throw new \InvalidArgumentException('El monto debe ser mayor a cero.');
        }
        if ((float)$d['monto'] > 9999999.99) {
            throw new \InvalidArgumentException('El monto excede el límite permitido.');
        }
        if (empty($d['tipo_pago']) || !in_array($d['tipo_pago'], self::TIPOS_VALIDOS, true)) {
            throw new \InvalidArgumentException('Tipo de pago no válido. Use: ' . implode(', ', self::TIPOS_VALIDOS));
        }
        if (empty($d['gateway']) || !in_array($d['gateway'], self::GATEWAYS_VALIDOS, true)) {
            throw new \InvalidArgumentException('Método de pago no válido.');
        }
        if (empty($d['estado']) || !in_array($d['estado'], self::ESTADOS_VALIDOS, true)) {
            throw new \InvalidArgumentException('Estado de pago no válido.');
        }
        if (!empty($d['referencia_externa']) && mb_strlen($d['referencia_externa']) > 200) {
            throw new \InvalidArgumentException('La referencia no puede superar los 200 caracteres.');
        }
    }
}
