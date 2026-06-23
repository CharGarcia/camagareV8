<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

class AsientoProgramadoRules
{
    /**
     * Valida los datos requeridos para crear/actualizar un asiento programado.
     */
    public function validar(array $data): void
    {
        $errores = [];

        $idAsientoTipo = (int) ($data['id_asiento_tipo'] ?? 0);
        $tipoRef = trim($data['tipo_referencia'] ?? '');
        
        if ($idAsientoTipo <= 0 && $tipoRef !== 'iva_ventas_factura' && $tipoRef !== 'retenciones_venta_debe' && $tipoRef !== 'retenciones_venta_haber') {
            $errores[] = 'El tipo de asiento base es obligatorio.';
        }

        $idCuenta = (int) ($data['id_cuenta'] ?? 0);
        if ($idCuenta <= 0) {
            $errores[] = 'La cuenta contable del plan de cuentas es obligatoria.';
        }

        $tipoRef = trim($data['tipo_referencia'] ?? '');
        $idRef = (int) ($data['id_referencia'] ?? 0);

        if ($tipoRef !== '') {
            $allowedTypes = [
                'cliente', 'proveedor', 'empleado', 'asientos tipo', 'producto', 'categoria', 'marca', 'iva', 'iva_ventas_factura',
                'ventas_factura', 'ventas_recibo', 'adquisiciones_compras', 'retenciones_venta', 'retenciones_compra',
                'ingresos_egresos', 'cobros_pagos', 'nomina', 'retenciones_venta_debe', 'retenciones_venta_haber'
            ];
            if (!in_array($tipoRef, $allowedTypes, true)) {
                $errores[] = 'El tipo de referencia de entidad no es válido.';
            }
            if ($idRef <= 0) {
                $errores[] = 'Debe seleccionar una entidad (Cliente/Proveedor) específica para este tipo de referencia.';
            }
        } else {
            if ($idRef > 0) {
                $errores[] = 'Si selecciona una entidad, debe especificar su tipo de referencia correspondientemente.';
            }
        }

        if (!empty($errores)) {
            throw new Exception(implode(' ', $errores));
        }
    }
}
