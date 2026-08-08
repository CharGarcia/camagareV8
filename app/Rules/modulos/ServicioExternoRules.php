<?php
declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

/**
 * Validaciones de negocio para el módulo Servicio Externo.
 */
class ServicioExternoRules
{
    /** Estados válidos: nace 'borrador'; pasa a 'facturado' al emitir; 'anulado' si se anula. */
    public const ESTADOS = ['borrador', 'facturado', 'anulado'];

    public function validarCreacion(array $data): void
    {
        if (empty($data['id_cliente'])) {
            throw new Exception("Debe seleccionar un cliente.");
        }
        if (trim((string) ($data['equipo_descripcion'] ?? '')) === '') {
            throw new Exception("Debe indicar el equipo atendido.");
        }
        if (empty($data['fecha_servicio'])) {
            throw new Exception("La fecha del servicio es obligatoria.");
        }
        if (empty($data['id_punto_emision']) || (string) ($data['secuencial'] ?? '') === '') {
            throw new Exception("Falta la serie / secuencial. Seleccione el punto de emisión.");
        }
        if (empty($data['detalles']) || !is_array($data['detalles'])) {
            throw new Exception("Debe agregar al menos un repuesto o servicio.");
        }

        $tieneLineas = false;
        foreach ($data['detalles'] as $idx => $det) {
            $cant = (float) ($det['cantidad'] ?? 0);
            $desc = trim((string) ($det['descripcion'] ?? ''));
            if ($cant <= 0 && $desc === '') {
                continue; // línea vacía, se ignora
            }
            if ($desc === '') {
                throw new Exception("Hay una línea sin descripción en la fila " . ($idx + 1) . ".");
            }
            if ($cant <= 0) {
                throw new Exception("La cantidad debe ser mayor a 0 en \"" . $desc . "\".");
            }
            $tieneLineas = true;
        }
        if (!$tieneLineas) {
            throw new Exception("Debe agregar al menos un repuesto o servicio con cantidad mayor a 0.");
        }
    }

    /**
     * Valida que se pueda generar un documento de venta desde la orden.
     */
    public function validarGeneracionDocumento(array $orden, string $tipo, array $extra): void
    {
        $tipo = strtoupper($tipo);
        if (!in_array($tipo, ['FACTURA', 'RECIBO'], true)) {
            throw new Exception("Tipo de documento no válido.");
        }
        if (!empty($orden['id_documento'])) {
            throw new Exception("Esta orden ya generó un documento (" . ($orden['numero_documento'] ?? '') . ").");
        }
        if (empty($orden['id_cliente'])) {
            throw new Exception("Debe asignar un cliente a la orden antes de facturar.");
        }
        $estado = (string) ($orden['estado'] ?? 'borrador');
        if ($estado === 'anulado') {
            throw new Exception("La orden está anulada; no se puede facturar.");
        }
    }

    public function validarEstado(string $estado): void
    {
        if (!in_array($estado, self::ESTADOS, true)) {
            throw new Exception("Estado no válido.");
        }
    }
}
