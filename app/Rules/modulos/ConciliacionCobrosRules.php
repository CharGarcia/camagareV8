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
     * Valida los documentos con los que se completa una línea del banco (uno o varios, de uno
     * o varios clientes): al menos uno, sin repetir ninguno, y la suma de lo asignado no puede
     * superar lo recibido en el banco. Cada asignación se valida además contra el saldo
     * pendiente actual de su documento con validarMatchLinea().
     */
    public function validarAsignaciones(array $asignaciones, float $montoLinea): void
    {
        if (count($asignaciones) < 1) {
            throw new \Exception('Selecciona el cliente y el documento a cobrar antes de confirmar (botón de lupa).');
        }

        $vistos = [];
        $total = 0.0;
        foreach ($asignaciones as $a) {
            $clave = strtoupper((string) ($a['tipo_documento'] ?? '')) . ':' . (int) ($a['id_documento'] ?? 0);
            if (isset($vistos[$clave])) {
                throw new \Exception('Un mismo documento está seleccionado más de una vez.');
            }
            $vistos[$clave] = true;
            $total += (float) ($a['monto_aplicar'] ?? 0);
        }

        if (round($total, 2) > round($montoLinea, 2) + 0.01) {
            throw new \Exception('La suma asignada ($' . number_format($total, 2) . ') supera el monto recibido en el banco ($' . number_format($montoLinea, 2) . ').');
        }
    }

    /**
     * Valida el ajuste manual de una línea (confirmación o corrección de la sugerencia).
     * $saldoPendienteDocumento es el saldo pendiente ACTUAL del documento elegido (recalculado
     * en el momento de confirmar, ver ConciliacionCobrosService::confirmarLinea); si se indica,
     * el monto a aplicar no puede superarlo (no se puede cobrar más de lo que el documento debe).
     */
    public function validarMatchLinea(array $data, float $montoLinea, ?float $saldoPendienteDocumento = null, float $apartadoOtrasLineas = 0.0): void
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
        if ($saldoPendienteDocumento !== null) {
            // Lo que otras líneas ya confirmadas (sin ingreso generado aún) tienen apartado de
            // este documento todavía no descuenta la cuenta por cobrar: se resta aquí para que el
            // mismo saldo no se cobre dos veces.
            $disponible = round($saldoPendienteDocumento - $apartadoOtrasLineas, 2);
            if ($disponible <= 0.009) {
                throw new \Exception('El documento ' . ($data['numero_documento'] ?? '') . ' ya tiene todo su saldo ($' . number_format($saldoPendienteDocumento, 2) . ') apartado por otras líneas confirmadas. No se puede cobrar dos veces.');
            }
            if ($montoAplicar > $disponible + 0.01) {
                $detalle = $apartadoOtrasLineas > 0.009
                    ? ' Saldo de la cuenta por cobrar: $' . number_format($saldoPendienteDocumento, 2) . '; ya apartado por otras líneas confirmadas: $' . number_format($apartadoOtrasLineas, 2) . '.'
                    : '';
                throw new \Exception('El monto a aplicar ($' . number_format($montoAplicar, 2) . ') supera el saldo disponible del documento ($' . number_format($disponible, 2) . ').' . $detalle);
            }
        }
    }
}
