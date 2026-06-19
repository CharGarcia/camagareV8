<?php

declare(strict_types=1);

namespace App\Rules\modulos;

class ProformaRules
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

        if (empty($data['id_establecimiento']) || (int) $data['id_establecimiento'] <= 0) {
            $errores[] = 'Debe seleccionar un establecimiento.';
        }

        if (empty($data['id_punto_emision']) || (int) $data['id_punto_emision'] <= 0) {
            $errores[] = 'Debe seleccionar un punto de emisión.';
        }

        if (empty($data['secuencial'])) {
            $errores[] = 'El secuencial es obligatorio.';
        }

        if (empty($data['detalles']) || !is_array($data['detalles']) || count($data['detalles']) === 0) {
            $errores[] = 'Debe agregar al menos un ítem a la proforma.';
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

        $diasVigencia = (int) ($data['dias_vigencia'] ?? 15);
        if ($diasVigencia < 1 || $diasVigencia > 3650) {
            $errores[] = 'Los días de vigencia deben estar entre 1 y 3650.';
        }

        return $errores;
    }
}
