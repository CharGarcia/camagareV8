<?php

declare(strict_types=1);

namespace App\Rules\modulos;

class LiquidacionCompraRules
{
    /**
     * Valida los datos de la liquidación antes de guardar.
     */
    public function validar(array $data): void
    {
        if (empty($data['id_proveedor'])) {
            throw new \Exception('Debe seleccionar un proveedor.');
        }

        if (empty($data['fecha_emision'])) {
            throw new \Exception('Debe ingresar la fecha de emisión.');
        }

        if (empty($data['id_punto_emision'])) {
            throw new \Exception('Debe seleccionar la serie de emisión.');
        }

        if (empty($data['id_sustento_tributario'])) {
            throw new \Exception('Debe seleccionar el código de sustento tributario.');
        }

        if (empty($data['secuencial'])) {
            throw new \Exception('El secuencial es obligatorio.');
        }

        if (empty($data['detalles']) || !is_array($data['detalles'])) {
            throw new \Exception('La liquidación debe tener al menos un ítem.');
        }

        foreach ($data['detalles'] as $idx => $det) {
            if (empty($det['descripcion'])) {
                throw new \Exception("El ítem " . ($idx + 1) . " debe tener una descripción.");
            }
            if ((float)($det['cantidad'] ?? 0) <= 0) {
                throw new \Exception("La cantidad del ítem " . ($idx + 1) . " debe ser mayor a cero.");
            }
        }

        if (empty($data['pagos']) || !is_array($data['pagos'])) {
            throw new \Exception('Debe ingresar al menos una forma de pago.');
        }

        // Validar tipo de identificación del proveedor (Cédula 05 o Pasaporte 06)
        $this->validarTipoIdentificacionProveedor((int)$data['id_proveedor'], (int)$data['id_empresa']);
    }

    private function validarTipoIdentificacionProveedor(int $idProveedor, int $idEmpresa): void
    {
        $db = \App\core\Database::getConnection();
        $st = $db->prepare("SELECT tipo_id_proveedor, identificacion FROM proveedores WHERE id = ? AND id_empresa = ? AND eliminado = false");
        $st->execute([$idProveedor, $idEmpresa]);
        $prov = $st->fetch();

        if (!$prov) {
            throw new \Exception('Proveedor no encontrado o eliminado.');
        }

        $tipoId = str_pad((string)($prov['tipo_id_proveedor'] ?? ''), 2, '0', STR_PAD_LEFT);
        // 01=RUC, 02=Cédula, 03=Pasaporte (Tipo 1)
        // 04=RUC, 05=Cédula, 06=Pasaporte, 08=Ext (Tipo 2)
        // Las liquidaciones solo permiten Cédula, Pasaporte o Exterior.
        if (!in_array($tipoId, ['02', '03', '05', '06', '08'])) {
            throw new \Exception('Las liquidaciones de compra solo pueden emitirse a proveedores con Cédula, Pasaporte o Identificación del Exterior. El tipo "' . $tipoId . '" no es permitido para este documento.');
        }
    }
}
