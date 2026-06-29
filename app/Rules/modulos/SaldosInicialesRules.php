<?php

declare(strict_types=1);

namespace App\Rules\modulos;

class SaldosInicialesRules
{
    public function validarCxc(array $data): void
    {
        if (empty(trim($data['nro_documento'] ?? ''))) {
            throw new \InvalidArgumentException('El número de documento es obligatorio.');
        }
        if (!preg_match('/^\d{3}-\d{3}-\d{9}$/', trim($data['nro_documento']))) {
            throw new \InvalidArgumentException('El número de documento debe tener el formato 000-000-000000000.');
        }
        if (empty($data['fecha_emision'])) {
            throw new \InvalidArgumentException('La fecha de emisión es obligatoria.');
        }
        if (empty($data['id_cliente']) || (int)$data['id_cliente'] <= 0) {
            throw new \InvalidArgumentException('Debe seleccionar un cliente registrado.');
        }
        if (empty(trim($data['nombre_cliente'] ?? ''))) {
            throw new \InvalidArgumentException('El nombre del cliente es obligatorio.');
        }
        $saldo = (float)($data['saldo_inicial'] ?? 0);
        if ($saldo <= 0) {
            throw new \InvalidArgumentException('El saldo pendiente debe ser mayor a 0.');
        }
    }

    public function validarCxp(array $data): void
    {
        $tiposValidos = ['FACTURA_COMPRA', 'LIQUIDACION', 'NOTA_CREDITO', 'NOTA_DEBITO'];
        if (!in_array($data['tipo_documento'] ?? '', $tiposValidos, true)) {
            throw new \InvalidArgumentException('Tipo de documento no válido.');
        }
        if (empty(trim($data['nro_documento'] ?? ''))) {
            throw new \InvalidArgumentException('El número de documento es obligatorio.');
        }
        if (!preg_match('/^\d{3}-\d{3}-\d{9}$/', trim($data['nro_documento']))) {
            throw new \InvalidArgumentException('El número de documento debe tener el formato 000-000-000000000.');
        }
        if (empty($data['fecha_emision'])) {
            throw new \InvalidArgumentException('La fecha de emisión es obligatoria.');
        }
        if (empty($data['id_proveedor']) || (int)$data['id_proveedor'] <= 0) {
            throw new \InvalidArgumentException('Debe seleccionar un proveedor registrado.');
        }
        if (empty(trim($data['nombre_proveedor'] ?? ''))) {
            throw new \InvalidArgumentException('El nombre del proveedor es obligatorio.');
        }
        $saldo = (float)($data['saldo_inicial'] ?? 0);
        if ($saldo <= 0) {
            throw new \InvalidArgumentException('El saldo pendiente debe ser mayor a 0.');
        }
    }

    public function validarConsignacion(array $data): void
    {
        if (empty($data['fecha_emision'])) {
            throw new \InvalidArgumentException('La fecha es obligatoria.');
        }
        if (!empty($data['nro_documento']) && !preg_match('/^\d{3}-\d{3}-\d{9}$/', trim($data['nro_documento']))) {
            throw new \InvalidArgumentException('El número de documento debe tener el formato 000-000-000000000.');
        }
        if (empty($data['id_cliente']) || (int)$data['id_cliente'] <= 0) {
            throw new \InvalidArgumentException('Debe seleccionar un cliente registrado.');
        }
        if (empty($data['id_producto']) || (int)$data['id_producto'] <= 0) {
            throw new \InvalidArgumentException('Debe seleccionar un producto registrado.');
        }
        if ((float)($data['cantidad'] ?? 0) <= 0) {
            throw new \InvalidArgumentException('La cantidad debe ser mayor a 0.');
        }
    }

    public function validarInventario(array $data): void
    {
        if (empty($data['id_producto']) || (int)$data['id_producto'] <= 0) {
            throw new \InvalidArgumentException('Debe seleccionar un producto registrado.');
        }
        if (empty($data['id_bodega']) || (int)$data['id_bodega'] <= 0) {
            throw new \InvalidArgumentException('Debe seleccionar una bodega.');
        }
        if ((float)($data['cantidad'] ?? 0) <= 0) {
            throw new \InvalidArgumentException('La cantidad debe ser mayor a 0.');
        }
    }

    public function validarAnticipo(array $data): void
    {
        if (empty($data['id_forma_pago']) || (int)$data['id_forma_pago'] <= 0) {
            throw new \InvalidArgumentException('Debe seleccionar una forma de anticipo.');
        }
        if (empty($data['id_tercero']) || (int)$data['id_tercero'] <= 0) {
            throw new \InvalidArgumentException('El anticipo debe estar atado a un cliente o proveedor.');
        }
        if (empty($data['fecha_saldo'])) {
            throw new \InvalidArgumentException('La fecha de saldo es obligatoria.');
        }
        if ((float)($data['saldo_inicial'] ?? 0) <= 0) {
            throw new \InvalidArgumentException('El saldo inicial debe ser mayor a 0.');
        }
    }

    public function validarBanco(array $data): void
    {
        if (empty($data['id_forma_pago']) || (int)$data['id_forma_pago'] <= 0) {
            throw new \InvalidArgumentException('Debe seleccionar una cuenta o tarjeta.');
        }
        if (empty($data['fecha_saldo'])) {
            throw new \InvalidArgumentException('La fecha de saldo es obligatoria.');
        }
        if (!isset($data['saldo_inicial']) || $data['saldo_inicial'] === '') {
            throw new \InvalidArgumentException('El saldo inicial es obligatorio.');
        }
    }
}
