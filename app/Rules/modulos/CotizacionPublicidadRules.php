<?php

declare(strict_types=1);

namespace App\Rules\modulos;

class CotizacionPublicidadRules
{
    public function validar(array $data): array
    {
        $errores = [];

        if (empty($data['fecha_emision'])) {
            $errores[] = 'La fecha de emisión es obligatoria.';
        }

        if (empty($data['id_cliente']) || (int) $data['id_cliente'] <= 0) {
            $errores[] = 'Debe seleccionar un cliente.';
        }

        if (empty($data['id_tarifa_iva']) || (int) $data['id_tarifa_iva'] <= 0) {
            $errores[] = 'Debe seleccionar la tarifa de IVA.';
        }

        $comision = (float) ($data['comision'] ?? 0);
        if ($comision < 0 || $comision > 100) {
            $errores[] = 'La comisión de agencia debe estar entre 0 y 100.';
        }

        if (empty($data['detalles']) || !is_array($data['detalles']) || count($data['detalles']) === 0) {
            $errores[] = 'Debe agregar al menos un ítem a la cotización.';
        } else {
            foreach ($data['detalles'] as $i => $det) {
                $fila = $i + 1;
                if (empty($det['descripcion'])) {
                    $errores[] = "Fila {$fila}: la descripción es obligatoria.";
                }
                if (!isset($det['cantidad']) || (float) $det['cantidad'] <= 0) {
                    $errores[] = "Fila {$fila}: la cantidad debe ser mayor a cero.";
                }
                if (!isset($det['precio_unitario']) || (float) $det['precio_unitario'] < 0) {
                    $errores[] = "Fila {$fila}: el precio unitario no puede ser negativo.";
                }
            }
        }

        return $errores;
    }
}
