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
        // El secuencial NO se valida aquí: ya no llega del navegador (era la vista previa del
        // modal), lo reserva el servidor al guardar —ConsignacionFacturaService::reservarNumero()—,
        // que es también quien avisa si el punto de emisión no tiene la numeración configurada.

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

        if (isset($data['empresa_config']) && is_array($data['empresa_config'])) {
            $this->validarVendedor($data['id_vendedor'] ?? null, $data['empresa_config']);
        }
    }

    /**
     * Con «Mostrar nombre del vendedor en la factura» activo en las Reglas de
     * Facturación del establecimiento, la factura lleva el vendedor en su
     * información adicional: sin vendedor saldría esa fila vacía. Se exige al
     * guardar el documento y otra vez al generar la factura (el borrador pudo
     * guardarse antes de activar el interruptor). Clave ausente = no se exige.
     */
    public static function vendedorObligatorio(array $empresaConfig): bool
    {
        $v = $empresaConfig['mostrar_vendedor_factura'] ?? false;
        return $v === true || in_array((string) $v, ['t', 'true', '1'], true);
    }

    public function validarVendedor($idVendedor, array $empresaConfig): void
    {
        if (self::vendedorObligatorio($empresaConfig) && (int) $idVendedor <= 0) {
            throw new Exception('Seleccione el vendedor: la configuración de facturación exige un vendedor en la factura.');
        }
    }
}
