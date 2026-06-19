<?php
declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

class ConsignacionVentaRules
{
    public function validarCreacion(array $data): void
    {
        if (empty($data['id_cliente'])) {
            throw new Exception("El cliente es obligatorio.");
        }
        if (empty($data['fecha_emision'])) {
            throw new Exception("La fecha de emisión es obligatoria.");
        }
        if (empty($data['id_bodega'])) {
            throw new Exception("La bodega es obligatoria.");
        }
        if (empty($data['detalles']) || !is_array($data['detalles']) || count($data['detalles']) === 0) {
            throw new Exception("Debe agregar al menos un producto a la consignación.");
        }
        $idEmpresa = $data['id_empresa'] ?? 0;
        $repoProd = new \App\repositories\modulos\ProductoRepository();

        $toBool = fn($v) => ($v === true || $v === 't' || $v === 'true' || $v === 1 || $v === '1' || $v === 'Y');
        $estConfig = $data['empresa_config'] ?? [];
        $obligatorioLotes = $toBool($estConfig['obligatorio_lotes'] ?? false);
        $obligatorioCaducidad = $toBool($estConfig['obligatorio_caducidad'] ?? false);
        $obligatorioNup = $toBool($estConfig['obligatorio_nup'] ?? false);

        foreach ($data['detalles'] as $idx => $det) {
            if (empty($det['id_producto'])) {
                throw new Exception("Hay un producto sin identificar en la fila " . ($idx + 1));
            }
            if (!isset($det['cantidad']) || (float)$det['cantidad'] <= 0) {
                throw new Exception("La cantidad debe ser mayor a 0 en la fila " . ($idx + 1));
            }
            if (!isset($det['precio_unitario']) || (float)$det['precio_unitario'] < 0) {
                throw new Exception("El precio no puede ser negativo en la fila " . ($idx + 1));
            }

            // Validar campos obligatorios según inventariable y config de empresa
            $prodData = $repoProd->findById((int)$det['id_producto'], (int)$idEmpresa);
            if (!$prodData) {
                throw new Exception("Producto no encontrado en la fila " . ($idx + 1));
            }

            $esInventariable = $toBool($prodData['inventariable'] ?? false);
            $tipoProduccion = trim((string)($prodData['tipo_produccion'] ?? ''));
            $nombreItem = $prodData['nombre'] ?? "Fila " . ($idx + 1);

            // Servicios (tipo 02) o productos no inventariables no requieren estos campos
            if ($tipoProduccion === '02' || !$esInventariable) {
                continue;
            }

            if ($obligatorioLotes && empty($det['lote'])) {
                throw new Exception("{$nombreItem}: El número de lote es obligatorio.");
            }

            if ($obligatorioCaducidad && empty($det['fecha_caducidad'])) {
                throw new Exception("{$nombreItem}: La fecha de caducidad es obligatoria.");
            }

            if ($obligatorioNup && empty($det['nup'])) {
                throw new Exception("{$nombreItem}: El número de serie (NUP) es obligatorio.");
            }
        }
    }
}
