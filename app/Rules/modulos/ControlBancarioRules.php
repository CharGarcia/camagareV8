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
}
