<?php
declare(strict_types=1);

namespace App\Rules\modulos;

class OrdenCompraRules
{
    public function validarCabecera(array $data): void
    {
        if (empty($data['id_proveedor']) || (int)$data['id_proveedor'] <= 0) {
            throw new \InvalidArgumentException('Debe seleccionar un proveedor.');
        }
        if (empty($data['id_establecimiento']) || (int)$data['id_establecimiento'] <= 0) {
            throw new \InvalidArgumentException('Debe seleccionar un establecimiento.');
        }
        if (empty($data['id_punto_emision']) || (int)$data['id_punto_emision'] <= 0) {
            throw new \InvalidArgumentException('Debe seleccionar un punto de emisión.');
        }
        if (empty(trim($data['fecha_orden'] ?? ''))) {
            throw new \InvalidArgumentException('La fecha de orden es obligatoria.');
        }
        if (!$this->esFechaValida($data['fecha_orden'])) {
            throw new \InvalidArgumentException('La fecha de orden no tiene un formato válido.');
        }
        if (!empty($data['fecha_recepcion']) && !$this->esFechaValida($data['fecha_recepcion'])) {
            throw new \InvalidArgumentException('La fecha de recepción no tiene un formato válido.');
        }
    }

    public function validarDetalle(array $items): void
    {
        if (empty($items)) {
            throw new \InvalidArgumentException('La orden debe tener al menos un ítem en el detalle.');
        }
        foreach ($items as $idx => $item) {
            $fila = $idx + 1;
            if (empty(trim($item['descripcion'] ?? ''))) {
                throw new \InvalidArgumentException("Ítem #{$fila}: la descripción es obligatoria.");
            }
            if ((float)($item['cantidad'] ?? 0) <= 0) {
                throw new \InvalidArgumentException("Ítem #{$fila}: la cantidad debe ser mayor a cero.");
            }
            if ((float)($item['precio_unitario'] ?? -1) < 0) {
                throw new \InvalidArgumentException("Ítem #{$fila}: el precio unitario no puede ser negativo.");
            }
        }
    }

    private function esFechaValida(string $fecha): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $fecha);
        return $d && $d->format('Y-m-d') === $fecha;
    }
}
