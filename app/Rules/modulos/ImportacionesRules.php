<?php

declare(strict_types=1);

namespace App\Rules\modulos;

class ImportacionesRules
{
    private const TIPOS_GASTO = [
        'arancel_ad_valorem', 'fodinfa', 'iva_importacion', 'isd',
        'flete_internacional', 'seguro', 'agente_afianzado',
        'almacenaje', 'transporte_interno', 'otro',
    ];

    /**
     * Tolerancia (USD) entre el FOB sumado de las líneas de producto y el total
     * facturado por el/los proveedor(es) del exterior.
     */
    private const TOLERANCIA_FOB = 0.50;

    /**
     * Valida los datos de una importación antes de guardar (cabecera + líneas + facturas exterior).
     */
    public function validar(array $data): void
    {
        if (empty($data['id_proveedor'])) {
            throw new \Exception('Debe seleccionar el proveedor del exterior.');
        }

        if (empty($data['id_bodega_destino'])) {
            throw new \Exception('Debe seleccionar la bodega destino.');
        }

        if (empty($data['id_establecimiento']) || empty($data['id_punto_emision'])) {
            throw new \Exception('Debe seleccionar la serie (establecimiento y punto de emisión) para numerar la importación.');
        }

        $criterio = $data['criterio_prorrateo'] ?? 'fob';
        if (!in_array($criterio, ['fob', 'peso', 'volumen', 'cantidad'], true)) {
            throw new \Exception('Criterio de prorrateo inválido.');
        }

        // El proveedor exterior debe tener identificación tipo "Exterior" (catálogo SRI 08).
        $this->validarProveedorExterior((int) $data['id_proveedor'], (int) ($data['id_empresa'] ?? 0));

        if (empty($data['detalles']) || !is_array($data['detalles'])) {
            throw new \Exception('La importación debe tener al menos una línea de producto.');
        }

        $sumaFob = 0.0;
        foreach ($data['detalles'] as $idx => $det) {
            $num = $idx + 1;
            if ((float) ($det['cantidad'] ?? 0) <= 0) {
                throw new \Exception("La cantidad de la línea #{$num} debe ser mayor a cero.");
            }
            if ((float) ($det['precio_unitario_fob'] ?? 0) < 0) {
                throw new \Exception("El precio FOB unitario de la línea #{$num} no puede ser negativo.");
            }
            if ($criterio === 'peso' && (float) ($det['peso_kg'] ?? 0) <= 0) {
                throw new \Exception("La línea #{$num} debe tener peso (kg) porque el criterio de prorrateo es por peso.");
            }
            if ($criterio === 'volumen' && (float) ($det['volumen_m3'] ?? 0) <= 0) {
                throw new \Exception("La línea #{$num} debe tener volumen (m3) porque el criterio de prorrateo es por volumen.");
            }
            $cant = (float) ($det['cantidad'] ?? 0);
            $precio = (float) ($det['precio_unitario_fob'] ?? 0);
            $sumaFob += (float) ($det['precio_total_fob'] ?? ($cant * $precio));
        }

        if (empty($data['facturas_exterior']) || !is_array($data['facturas_exterior'])) {
            throw new \Exception('Debe registrar al menos una factura del proveedor del exterior.');
        }

        $sumaFacturas = 0.0;
        foreach ($data['facturas_exterior'] as $idx => $f) {
            $num = $idx + 1;
            if ((float) ($f['monto_usd'] ?? 0) <= 0) {
                throw new \Exception("El monto de la factura del exterior #{$num} debe ser mayor a cero.");
            }
            $sumaFacturas += (float) $f['monto_usd'];
        }

        if (abs($sumaFob - $sumaFacturas) > self::TOLERANCIA_FOB) {
            throw new \Exception(sprintf(
                'El total FOB de las líneas de producto ($%.2f) no coincide con el total facturado por el proveedor del exterior ($%.2f). Verifique los montos.',
                $sumaFob,
                $sumaFacturas
            ));
        }

        if (!empty($data['gastos']) && is_array($data['gastos'])) {
            foreach ($data['gastos'] as $idx => $g) {
                $num = $idx + 1;
                if (empty($g['tipo_gasto']) || !in_array($g['tipo_gasto'], self::TIPOS_GASTO, true)) {
                    throw new \Exception("El gasto #{$num} tiene un tipo inválido.");
                }
                $origen = $g['origen'] ?? 'dai_manual';
                if (!in_array($origen, ['dai_manual', 'compra_vinculada', 'liquidacion_vinculada'], true)) {
                    throw new \Exception("El gasto #{$num} tiene un origen inválido.");
                }
                if ($origen === 'compra_vinculada' && empty($g['id_compra'])) {
                    throw new \Exception("El gasto #{$num} debe indicar la compra que se está vinculando.");
                }
                if ($origen === 'liquidacion_vinculada' && empty($g['id_liquidacion_compra'])) {
                    throw new \Exception("El gasto #{$num} debe indicar la liquidación de compra que se está vinculando.");
                }
                if ((float) ($g['monto'] ?? 0) <= 0) {
                    throw new \Exception("El monto del gasto #{$num} debe ser mayor a cero.");
                }
            }
        }
    }

    /**
     * No se puede nacionalizar (procesar a inventario) sin haber capturado al menos
     * un gasto de nacionalización (aunque sea $0 de arancel, debe quedar registrado
     * el intento de costeo real; evita procesar solo con el FOB del proveedor).
     */
    public function validarParaNacionalizar(array $importacion, array $gastos): void
    {
        if (($importacion['estado'] ?? '') === 'nacionalizada') {
            throw new \Exception('Esta importación ya fue procesada a inventario.');
        }
        if (($importacion['estado'] ?? '') === 'pendiente_aprobacion') {
            throw new \Exception('Esta importación ya está pendiente de aprobación.');
        }
        if (($importacion['estado'] ?? '') === 'anulada') {
            throw new \Exception('No se puede procesar una importación anulada.');
        }
        if (empty($gastos)) {
            throw new \Exception('Debe registrar al menos un gasto de nacionalización antes de procesar el inventario (aunque sea el arancel o el flete).');
        }
    }

    private function validarProveedorExterior(int $idProveedor, int $idEmpresa): void
    {
        $db = \App\core\Database::getConnection();
        $st = $db->prepare("SELECT tipo_id_proveedor, razon_social FROM proveedores WHERE id = ? AND id_empresa = ? AND eliminado = false");
        $st->execute([$idProveedor, $idEmpresa]);
        $prov = $st->fetch();

        if (!$prov) {
            throw new \Exception('Proveedor no encontrado o eliminado.');
        }

        $tipoId = str_pad((string) ($prov['tipo_id_proveedor'] ?? ''), 2, '0', STR_PAD_LEFT);
        // 08 = Identificación del Exterior (Tabla 2 SRI). El proveedor de una importación
        // no tiene RUC ecuatoriano.
        if ($tipoId !== '08') {
            throw new \Exception('El proveedor de una importación debe tener identificación del exterior. "' . $prov['razon_social'] . '" no está configurado como proveedor del exterior.');
        }
    }
}
