<?php
declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

/**
 * Validaciones de negocio para Retornos de Consignaciones en Ventas.
 */
class RetornoCvRules
{
    public function validarCreacion(array $data): void
    {
        if (empty($data['id_cliente'])) {
            throw new Exception("El cliente es obligatorio.");
        }
        if (empty($data['fecha_retorno'])) {
            throw new Exception("La fecha del retorno es obligatoria.");
        }
        if (empty($data['detalles']) || !is_array($data['detalles'])) {
            throw new Exception("Debe agregar al menos un producto a retornar.");
        }

        $tieneLineas = false;
        foreach ($data['detalles'] as $idx => $det) {
            $cant = (float) ($det['cantidad'] ?? 0);
            if ($cant <= 0) {
                // Se ignoran líneas con cantidad 0 (el usuario no las devuelve).
                continue;
            }
            $tieneLineas = true;

            if (empty($det['id_consignacion_detalle'])) {
                throw new Exception("Hay una línea sin consignación de origen en la fila " . ($idx + 1) . ".");
            }
        }

        if (!$tieneLineas) {
            throw new Exception("Debe indicar una cantidad mayor a 0 en al menos un producto.");
        }
    }
}
