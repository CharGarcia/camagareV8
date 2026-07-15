<?php
declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

class AsientosTipoRules
{
    private const TIPOS_PERMITIDOS = [
        'ventas_factura',
        // Ojo: el valor canónico es 'recibos_venta' (lo usan ReciboVentaService, el builder y los
        // asientos_tipo ya creados). 'ventas_recibo' era un residuo que no coincidía con nada.
        'recibos_venta',
        'adquisiciones_compras',
        'retenciones_venta',
        'retenciones_compra',
        'ingresos_egresos',
        'cobros_pagos',
        'nomina'
    ];

    /**
     * Valida los datos requeridos para crear/actualizar un asiento tipo.
     */
    public function validar(array $data): void
    {
        $errores = [];

        $tipo = trim($data['tipo_asiento'] ?? '');
        if (empty($tipo)) {
            $errores[] = 'El tipo de asiento es obligatorio.';
        } elseif (!preg_match('/^[a-z0-9_]+$/', $tipo)) {
            $errores[] = 'El tipo de asiento debe contener únicamente letras minúsculas, números o guiones bajos.';
        }

        if (empty(trim($data['referencia'] ?? ''))) {
            $errores[] = 'La referencia es obligatoria.';
        }

        $codigo = trim($data['codigo'] ?? '');
        if (empty($codigo)) {
            $errores[] = 'El código es obligatorio.';
        } elseif (!preg_match('/^[A-Z0-9_]+$/', $codigo)) {
            $errores[] = 'El código debe contener únicamente letras mayúsculas, números o guiones bajos.';
        }

        if (!empty($errores)) {
            throw new Exception(implode(' ', $errores));
        }
    }
}
