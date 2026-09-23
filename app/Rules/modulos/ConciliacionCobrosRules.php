<?php

declare(strict_types=1);

namespace App\Rules\modulos;

class ConciliacionCobrosRules
{
    public function validarCarga(array $data): void
    {
        if (empty($data['id_forma_pago'])) {
            throw new \Exception('Debe seleccionar la cuenta bancaria que recibió el cobro.');
        }
        if (empty($data['id_perfil'])) {
            throw new \Exception('Debe seleccionar el formato del banco (perfil de mapeo).');
        }
    }

    /**
     * Valida el ajuste manual de una línea (confirmación o corrección de la sugerencia).
     * $saldoPendienteDocumento es el saldo pendiente ACTUAL del documento elegido (recalculado
     * en el momento de confirmar, ver ConciliacionCobrosService::confirmarLinea); si se indica,
     * el monto a aplicar no puede superarlo (no se puede cobrar más de lo que el documento debe).
     */
    public function validarMatchLinea(array $data, float $montoLinea, ?float $saldoPendienteDocumento = null): void
    {
        if (empty($data['id_cliente'])) {
            throw new \Exception('Debe indicar el cliente de la línea.');
        }
        $tipoDoc = strtoupper((string) ($data['tipo_documento'] ?? ''));
        if (!in_array($tipoDoc, ['FACTURA', 'SALDO_INICIAL', 'RECIBO'], true)) {
            throw new \Exception('Debe seleccionar el documento (factura/saldo inicial/recibo) a cobrar.');
        }
        if (empty($data['id_documento'])) {
            throw new \Exception('Debe seleccionar el documento a cobrar.');
        }

        $montoAplicar = (float) ($data['monto_aplicar'] ?? 0);
        if ($montoAplicar <= 0) {
            throw new \Exception('El monto a aplicar debe ser mayor a cero.');
        }
        if ($montoAplicar > $montoLinea + 0.01) {
            throw new \Exception('El monto a aplicar no puede ser mayor al monto de la línea del banco.');
        }
        if ($saldoPendienteDocumento !== null && $montoAplicar > $saldoPendienteDocumento + 0.01) {
            throw new \Exception('El monto a aplicar ($' . number_format($montoAplicar, 2) . ') no puede ser mayor al saldo pendiente del documento ($' . number_format($saldoPendienteDocumento, 2) . ').');
        }
    }
}
