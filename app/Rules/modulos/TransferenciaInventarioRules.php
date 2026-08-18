<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

/**
 * Validaciones de negocio de una transferencia de inventario. No toca la base
 * de datos: la validación de stock disponible vive en el Service, que es quien
 * tiene el candado tomado sobre el stock (§8).
 */
class TransferenciaInventarioRules
{
    public function validar(array $data): void
    {
        if (empty($data['fecha_transferencia'])) {
            throw new Exception('La fecha de la transferencia es obligatoria.');
        }
        if (strtotime((string) $data['fecha_transferencia']) > strtotime(date('Y-m-d 23:59:59'))) {
            throw new Exception('La fecha de la transferencia no puede ser posterior a la fecha actual.');
        }

        $origen  = (int) ($data['id_bodega_origen'] ?? 0);
        $destino = (int) ($data['id_bodega_destino'] ?? 0);

        if ($origen <= 0) {
            throw new Exception('Debe seleccionar la bodega de origen.');
        }
        if ($destino <= 0) {
            throw new Exception('Debe seleccionar la bodega de destino.');
        }
        if ($origen === $destino) {
            throw new Exception('La bodega de origen y la de destino no pueden ser la misma.');
        }

        $lineas = $data['detalles'] ?? [];
        if (!is_array($lineas) || count($lineas) === 0) {
            throw new Exception('Debe agregar al menos un producto a la transferencia.');
        }

        $vistos = [];
        foreach ($lineas as $i => $l) {
            $n = $i + 1;

            if (empty($l['id_producto'])) {
                throw new Exception("Línea {$n}: debe seleccionar un producto.");
            }
            $cantidad = (float) ($l['cantidad'] ?? 0);
            if ($cantidad <= 0) {
                throw new Exception("Línea {$n}: la cantidad debe ser mayor a cero.");
            }

            // Una serie (NUP) identifica una unidad concreta: no puede repetirse
            // dentro del mismo documento ni llevar cantidad distinta de 1.
            $nup = trim((string) ($l['nup'] ?? ''));
            if ($nup !== '') {
                if (abs($cantidad - 1.0) > 0.000001) {
                    throw new Exception("Línea {$n}: una serie/NUP identifica una sola unidad, la cantidad debe ser 1.");
                }
                $claveNup = mb_strtoupper($nup);
                if (isset($vistos[$claveNup])) {
                    throw new Exception("La serie/NUP '{$nup}' está repetida en la transferencia.");
                }
                $vistos[$claveNup] = true;
            }

            if (!empty($l['fecha_caducidad']) && strtotime((string) $l['fecha_caducidad']) === false) {
                throw new Exception("Línea {$n}: la fecha de caducidad no es válida.");
            }
        }
    }
}
