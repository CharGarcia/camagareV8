<?php
declare(strict_types=1);

namespace App\Rules\modulos;

class SuscripcionesRules
{
    public function validar(array $data, array $detalle = []): void
    {
        if (empty($data['id_cliente'])) {
            throw new \InvalidArgumentException('Debe seleccionar un cliente.|#susc_search_cliente');
        }

        if (empty($data['id_periodicidad'])) {
            throw new \InvalidArgumentException('Debe seleccionar una periodicidad.|#susc_id_periodicidad');
        }

        if (empty(trim($data['fecha_inicio'] ?? ''))) {
            throw new \InvalidArgumentException('La fecha de inicio es obligatoria.|#susc_fecha_inicio');
        }

        if (!strtotime($data['fecha_inicio'])) {
            throw new \InvalidArgumentException('La fecha de inicio no es válida.|#susc_fecha_inicio');
        }

        if (!empty($data['fecha_fin']) && strtotime($data['fecha_fin']) <= strtotime($data['fecha_inicio'])) {
            throw new \InvalidArgumentException('La fecha de fin debe ser posterior a la fecha de inicio.|#susc_fecha_fin');
        }

        $formasValidas = ['credito', 'tarjeta'];
        if (!in_array($data['forma_cobro'] ?? 'credito', $formasValidas, true)) {
            throw new \InvalidArgumentException('La forma de cobro no es válida.|#susc_forma_cobro');
        }

        $estadosValidos = ['activo', 'pausado', 'suspendido', 'cancelado'];
        if (!in_array($data['estado'] ?? 'activo', $estadosValidos, true)) {
            throw new \InvalidArgumentException('El estado no es válido.|#susc_estado');
        }

        $comprobantesValidos = ['factura', 'recibo'];
        if (!in_array($data['tipo_comprobante'] ?? 'factura', $comprobantesValidos, true)) {
            throw new \InvalidArgumentException('El tipo de comprobante no es válido.|#susc_tipo_comprobante');
        }

        if (empty($detalle)) {
            throw new \InvalidArgumentException('Debe agregar al menos un producto o servicio a la suscripción.');
        }

        foreach ($detalle as $idx => $item) {
            $fila = $idx + 1;
            if (empty($item['id_producto'])) {
                throw new \InvalidArgumentException("Fila {$fila}: debe seleccionar un producto.|[name=\"detalle[{$idx}][descripcion]\"]");
            }
            $cantidad = (float) ($item['cantidad'] ?? 0);
            if ($cantidad <= 0) {
                throw new \InvalidArgumentException("Fila {$fila}: la cantidad debe ser mayor a cero.|[name=\"detalle[{$idx}][cantidad]\"]");
            }
            $precio = (float) ($item['precio_unitario'] ?? 0);
            if ($precio < 0) {
                throw new \InvalidArgumentException("Fila {$fila}: el precio no puede ser negativo.|[name=\"detalle[{$idx}][precio_unitario]\"]");
            }
        }
    }
}
