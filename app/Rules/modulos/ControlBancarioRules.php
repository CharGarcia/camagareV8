<?php

declare(strict_types=1);

namespace App\Rules\modulos;

class ControlBancarioRules
{
    private const TIPOS_VALIDOS = ['DEPOSITO', 'CHEQUE', 'TRANSFERENCIA', 'NOTA_DEBITO', 'NOTA_CREDITO', 'OTRO'];
    private const DIRECCIONES_VALIDAS = ['EMITIDO', 'RECIBIDO'];

    public function validarClasificacion(array $data): void
    {
        if (empty($data['id_asiento_detalle'])) {
            throw new \Exception('Falta indicar el movimiento a clasificar.');
        }

        if (empty($data['id_forma_pago'])) {
            throw new \Exception('Falta indicar la cuenta bancaria.');
        }

        $tipo = strtoupper((string) ($data['tipo_transaccion'] ?? ''));
        if (!in_array($tipo, self::TIPOS_VALIDOS, true)) {
            throw new \Exception('El tipo de transacción no es válido.');
        }

        if ($tipo === 'CHEQUE') {
            $direccion = strtoupper((string) ($data['cheque_direccion'] ?? ''));
            if (!in_array($direccion, self::DIRECCIONES_VALIDAS, true)) {
                throw new \Exception('Para un cheque debe indicar si fue emitido o recibido.');
            }
            if (empty(trim((string) ($data['numero_cheque'] ?? '')))) {
                throw new \Exception('Debe indicar el número de cheque.');
            }
        }

        foreach (['fecha_cheque', 'fecha_banco'] as $campo) {
            $valor = $data[$campo] ?? null;
            if (!empty($valor) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $valor)) {
                throw new \Exception('La fecha ingresada no es válida.');
            }
        }
    }

    public function validarConciliacion(array $data): void
    {
        if (empty($data['id_forma_pago'])) {
            throw new \Exception('Falta indicar la cuenta bancaria.');
        }

        foreach (['fecha_inicio', 'fecha_fin'] as $campo) {
            $valor = (string) ($data[$campo] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
                throw new \Exception('Debe indicar un rango de fechas válido para conciliar.');
            }
        }

        if ($data['fecha_inicio'] > $data['fecha_fin']) {
            throw new \Exception('La fecha de inicio no puede ser posterior a la fecha de fin.');
        }

        if (isset($data['saldo_banco']) && $data['saldo_banco'] !== null && $data['saldo_banco'] !== '') {
            if (!is_numeric($data['saldo_banco'])) {
                throw new \Exception('El saldo del banco debe ser un valor numérico.');
            }
        }
    }
}
