<?php
declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

/**
 * Validaciones de negocio para Cambios de productos (`modulos/cambio-producto-cv`).
 */
class CambioProductoCvRules
{
    /**
     * Numeración obligatoria al EMITIR (solo en la creación): sin punto de emisión el
     * documento quedaría sin número de serie.
     *
     * El secuencial y la serie NO se validan aquí: ya no llegan del navegador (eran la vista
     * previa del modal), los reserva el servidor al guardar —CambioProductoCvService::
     * reservarNumero()—, que es también quien avisa si el punto no tiene numeración configurada.
     */
    public function validarNumeracion(array $data): void
    {
        if (empty($data['id_punto_emision'])) {
            throw new Exception("Debe seleccionar la serie (punto de emisión). Configúrela en Empresa → Secuenciales.");
        }
    }

    public function validarCreacion(array $data): void
    {
        if (empty($data['id_cliente'])) {
            throw new Exception("El cliente es obligatorio.");
        }
        if (empty($data['fecha_cambio'])) {
            throw new Exception("La fecha del cambio es obligatoria.");
        }

        $devoluciones = array_filter($data['devoluciones'] ?? [], fn($d) => (float)($d['cantidad'] ?? 0) > 0);
        $entregas     = array_filter($data['entregas'] ?? [],    fn($d) => (float)($d['cantidad'] ?? 0) > 0);

        if (empty($devoluciones)) {
            throw new Exception("Debe agregar al menos un producto a devolver (con cantidad mayor a 0).");
        }

        foreach ($devoluciones as $idx => $det) {
            if (empty($det['origen_tipo']) || empty($det['id_origen_detalle'])) {
                throw new Exception("Hay una línea de devolución sin origen (factura o cambio) en la fila " . ($idx + 1) . ".");
            }
            if (!in_array($det['origen_tipo'], ['FACTURA', 'CAMBIO'], true)) {
                throw new Exception("Origen de devolución no válido en la fila " . ($idx + 1) . ".");
            }
        }

        foreach ($entregas as $idx => $det) {
            if (empty($det['id_producto'])) {
                throw new Exception("Hay una línea de entrega sin producto en la fila " . ($idx + 1) . ".");
            }
        }
    }
}
