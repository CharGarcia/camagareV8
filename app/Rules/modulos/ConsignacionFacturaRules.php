<?php
declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

/**
 * Validaciones del documento de Facturación de Consignaciones.
 * La validación de saldo (cantidad ≤ saldo facturable) se hace en el Service
 * contra la BD (autoritativo) al guardar y al generar la factura.
 */
class ConsignacionFacturaRules
{
    public function validarDocumento(array $data): void
    {
        if (empty($data['id_cliente']) || (int) $data['id_cliente'] <= 0) {
            throw new Exception('Debe seleccionar el cliente a facturar.');
        }
        if (empty($data['fecha_emision'])) {
            throw new Exception('La fecha de emisión es obligatoria.');
        }
        if (empty($data['id_punto_emision']) || (int) $data['id_punto_emision'] <= 0) {
            throw new Exception('Debe seleccionar la serie (punto de emisión).');
        }
        if (empty($data['secuencial'])) {
            throw new Exception('Falta el secuencial. Configure el punto de emisión en Empresa / Secuenciales.');
        }

        $detalles = $data['detalles'] ?? [];
        if (!is_array($detalles) || count($detalles) === 0) {
            throw new Exception('Agregue al menos una línea de consignación a facturar.');
        }

        $hayCantidad = false;
        foreach ($detalles as $d) {
            $cant = (float) ($d['cantidad'] ?? 0);
            if ((int) ($d['id_consignacion_detalle'] ?? 0) <= 0) {
                continue;
            }
            if ($cant < 0) {
                throw new Exception('La cantidad a facturar no puede ser negativa.');
            }
            if ($cant > 0) {
                $hayCantidad = true;
            }
        }
        if (!$hayCantidad) {
            throw new Exception('Indique una cantidad mayor a cero en al menos una línea.');
        }
    }
}
