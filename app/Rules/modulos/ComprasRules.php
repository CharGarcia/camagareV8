<?php

declare(strict_types=1);

namespace App\Rules\modulos;

class ComprasRules
{
    /**
     * Valida los datos de una compra antes de guardar.
     */
    public function validar(array $data): void
    {
        if (empty($data['id_proveedor'])) {
            throw new \Exception('Debe seleccionar un proveedor.');
        }

        if (empty($data['tipo_comprobante'])) {
            throw new \Exception('Debe seleccionar el tipo de comprobante.');
        }

        if (empty($data['fecha_emision'])) {
            throw new \Exception('Debe ingresar la fecha de emisión del comprobante.');
        }

        // La fecha de emisión no puede ser futura
        if ($data['fecha_emision'] > date('Y-m-d')) {
            throw new \Exception('La fecha de emisión no puede ser una fecha futura.');
        }

        if (!empty($data['fecha_caducidad']) && $data['fecha_emision'] > $data['fecha_caducidad']) {
            throw new \Exception('La fecha de emisión no puede ser posterior a la fecha de caducidad del comprobante.');
        }

        // Obtener tipo de empresa
        $idEmpresa = (int)($data['id_empresa'] ?? 0);
        $empModel  = new \App\models\Empresa();
        $empData   = $empModel->getPorId($idEmpresa);
        $esPersonaNatural = (($empData['tipo'] ?? '01') === '01');

        if (!$esPersonaNatural) {
            if (empty($data['id_sustento_tributario'])) {
                throw new \Exception('Debe seleccionar el código de sustento tributario.');
            }
        }

        // Validar número del comprobante del proveedor
        if (empty($data['establecimiento_prov']) || strlen((string)$data['establecimiento_prov']) !== 3) {
            throw new \Exception('El establecimiento del proveedor debe tener exactamente 3 dígitos.');
        }
        if (empty($data['punto_emision_prov']) || strlen((string)$data['punto_emision_prov']) !== 3) {
            throw new \Exception('El punto de emisión del proveedor debe tener exactamente 3 dígitos.');
        }
        if (empty($data['secuencial_prov'])) {
            throw new \Exception('El número secuencial del comprobante es obligatorio.');
        }

        // Validar rango de autorización
        $sec = (int)$data['secuencial_prov'];
        if (!empty($data['autorizacion_desde']) && $sec < (int)$data['autorizacion_desde']) {
            throw new \Exception('El número secuencial es menor al rango permitido por la autorización.');
        }
        if (!empty($data['autorizacion_hasta']) && $sec > (int)$data['autorizacion_hasta']) {
            throw new \Exception('El número secuencial es mayor al rango permitido por la autorización.');
        }

        // Autorización: 10 dígitos (físico) o 49 (electrónico)
        if (!$esPersonaNatural) {
            $numAuth = (string)($data['numero_autorizacion'] ?? '');
            $tipoReg = $data['tipo_registro'] ?? 'fisica';

            if ($tipoReg === 'fisica') {
                if (strlen($numAuth) !== 10) {
                    throw new \Exception('Para registros físicos, el número de autorización debe tener exactamente 10 dígitos.');
                }
            } else if ($tipoReg === 'electronico') {
                if (strlen($numAuth) !== 49) {
                    throw new \Exception('Para registros electrónicos, el número de autorización debe tener exactamente 49 dígitos.');
                }
            }

            if (empty($data['fecha_caducidad'])) {
                throw new \Exception('La fecha de caducidad es obligatoria.');
            }
        }

        // Al menos un ítem
        if (empty($data['detalles']) || !is_array($data['detalles']) || count($data['detalles']) === 0) {
            throw new \Exception('La compra debe tener al menos un ítem en el detalle.');
        }

        foreach ($data['detalles'] as $idx => $det) {
            $num = $idx + 1;
            if (empty($det['descripcion'])) {
                throw new \Exception("El ítem #{$num} debe tener una descripción.");
            }
            if ((float)($det['cantidad'] ?? 0) <= 0) {
                throw new \Exception("La cantidad del ítem #{$num} debe ser mayor a cero.");
            }
            if ((float)($det['precio_unitario'] ?? 0) < 0) {
                throw new \Exception("El precio unitario del ítem #{$num} no puede ser negativo.");
            }
        }

        // Al menos una forma de pago
        if (empty($data['pagos']) || !is_array($data['pagos']) || count($data['pagos']) === 0) {
            throw new \Exception('Debe ingresar al menos una forma de pago.');
        }

        foreach ($data['pagos'] as $idx => $pago) {
            if ((float)($pago['total'] ?? 0) <= 0) {
                throw new \Exception('El total de la forma de pago #' . ($idx + 1) . ' debe ser mayor a cero.');
            }
        }

        // Validar retenciones (si hay)
        if (!empty($data['retenciones']) && is_array($data['retenciones'])) {
            foreach ($data['retenciones'] as $idx => $ret) {
                $num = $idx + 1;
                if (empty($ret['cod_ret_air'])) {
                    throw new \Exception("La retención #{$num} debe tener un código.");
                }
                if ((float)($ret['base_imp_air'] ?? 0) <= 0) {
                    throw new \Exception("La base imponible de la retención #{$num} debe ser mayor a cero.");
                }
                if ((float)($ret['porcentaje_air'] ?? 0) <= 0) {
                    throw new \Exception("El porcentaje de la retención #{$num} debe ser mayor a cero.");
                }
            }
        }

        // Validar asiento contable (si aplica)
        if (!empty($data['asiento']) && is_array($data['asiento'])) {
            $totalDebe  = 0;
            $totalHaber = 0;
            foreach ($data['asiento'] as $linea) {
                $totalDebe  += (float)($linea['debe']  ?? 0);
                $totalHaber += (float)($linea['haber'] ?? 0);
            }
            if (abs($totalDebe - $totalHaber) > 0.01) {
                throw new \Exception(
                    sprintf(
                        'El asiento contable no cuadra. Debe: $%.2f — Haber: $%.2f — Diferencia: $%.2f',
                        $totalDebe,
                        $totalHaber,
                        abs($totalDebe - $totalHaber)
                    )
                );
            }
        }
    }
}
