<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

class EgresoRules
{
    public function validar(array $data): void
    {
        // Validaciones Básicas
        if (empty($data['fecha_emision'])) {
            throw new Exception("La fecha de emisión es obligatoria.");
        }
        
        if (strtotime($data['fecha_emision']) > strtotime(date('Y-m-d'))) {
            throw new Exception("La fecha de emisión no puede ser posterior a la fecha actual.");
        }

        if (empty($data['tipo_egreso'])) {
            throw new Exception("El tipo de egreso es obligatorio.");
        }
        
        if (empty($data['tipo_sujeto'])) {
            throw new Exception("El tipo de sujeto (Proveedor, Empleado) es obligatorio.");
        }

        if (empty($data['numero_egreso'])) {
            throw new Exception("El número de egreso no ha sido generado.");
        }

        // Validar Sujeto según el Tipo
        if ($data['tipo_sujeto'] === 'PROVEEDOR' && empty($data['id_proveedor'])) {
            throw new Exception("Debe seleccionar el Proveedor para este egreso.");
        }
        
        if ($data['tipo_sujeto'] === 'EMPLEADO' && empty($data['id_empleado'])) {
            throw new Exception("Debe seleccionar el Empleado para este egreso.");
        }

        // Validar Detalles
        if (empty($data['detalles']) || !is_array($data['detalles'])) {
            throw new Exception("El egreso debe tener al menos una línea de detalle o documento.");
        }

        // Un egreso puede combinar documentos de módulo (Compra/Liquidación/Nómina/...) con
        // líneas MANUAL ("otros conceptos") a la vez. Si se mezclan, cada línea MANUAL debe
        // traer su propia cuenta contable: sin eso, el asiento la clasificaría por defecto en
        // la cuenta "oficial" del concepto de cabecera (p. ej. Cuentas por Pagar de la compra),
        // metiendo mal un gasto que no tiene relación con esa cartera.
        $tiposDetalle = array_unique(array_map(fn($d) => $d['tipo_documento'] ?? '', $data['detalles']));
        $hayModulo = !empty(array_diff($tiposDetalle, ['MANUAL']));

        $totalDetalle = 0;
        foreach ($data['detalles'] as $idx => $det) {
            $monto = (float) ($det['monto_pagado'] ?? 0);
            if ($monto <= 0) {
                throw new Exception("El monto a pagar en la línea #" . ($idx + 1) . " debe ser mayor a 0.");
            }

            $tipoDoc = $det['tipo_documento'] ?? '';
            if ($tipoDoc !== 'MANUAL') {
                $saldoAnt = (float) ($det['saldo_anterior'] ?? 0);
                if ($monto > $saldoAnt + 0.01) {
                     throw new Exception("En la línea #" . ($idx + 1) . ", el monto a pagar ($" . number_format($monto, 2) . ") no puede superar el saldo pendiente ($" . number_format($saldoAnt, 2) . ").");
                }
            } elseif ($hayModulo && empty($det['id_cuenta_contable'])) {
                throw new Exception("La línea #" . ($idx + 1) . " (otros conceptos) debe indicar su cuenta contable, porque este egreso también paga un documento de otro tipo.");
            }

            $totalDetalle += $monto;
        }

        // Validar Formas de Pago
        if (empty($data['pagos']) || !is_array($data['pagos'])) {
            throw new Exception("Debe registrar al menos una forma de pago (salida de dinero).");
        }

        $totalPagos = 0;
        foreach ($data['pagos'] as $idx => $p) {
            if (empty($p['id_forma_pago'])) {
                throw new Exception("Falta seleccionar la forma de pago en la línea #" . ($idx + 1) . ".");
            }
            $monto = (float) ($p['monto'] ?? 0);
            if ($monto <= 0) {
                throw new Exception("El monto de pago #" . ($idx + 1) . " debe ser mayor a cero.");
            }
            $totalPagos += $monto;
        }

        // Validar Cuadratura
        if (abs($totalDetalle - $totalPagos) > 0.01) {
            throw new Exception("El total detallado ($" . number_format($totalDetalle, 2) . ") no coincide con el total pagado ($" . number_format($totalPagos, 2) . ").");
        }

        // Validar Monto Total
        $montoTotalInformado = (float) ($data['monto_total'] ?? 0);
        if (abs($montoTotalInformado - $totalDetalle) > 0.01) {
            throw new Exception("Inconsistencia en la sumatoria total.");
        }
    }
}
