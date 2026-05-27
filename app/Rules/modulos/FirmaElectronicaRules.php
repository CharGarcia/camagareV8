<?php
declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

class FirmaElectronicaRules
{
    public function validar(array $data): void
    {
        $errores = [];

        if (empty($data['id_empresa'])) {
            $errores[] = 'El identificador de empresa es obligatorio.';
        }
        if (empty($data['id_usuario'])) {
            $errores[] = 'El usuario responsable es obligatorio.';
        }

        $tipoId = trim($data['tipo_identificacion'] ?? '');
        if (!in_array($tipoId, ['cedula', 'pasaporte'], true)) {
            $errores[] = 'El tipo de identificación no es válido.';
        }

        $numId = trim($data['numero_identificacion'] ?? '');
        if ($numId === '') {
            $errores[] = 'El número de identificación es obligatorio.';
        } elseif ($tipoId === 'cedula' && !preg_match('/^\d{10}$/', $numId)) {
            $errores[] = 'La cédula debe tener exactamente 10 dígitos numéricos.';
        } elseif ($tipoId === 'pasaporte' && !preg_match('/^[a-zA-Z0-9]{1,20}$/', $numId)) {
            $errores[] = 'El pasaporte solo puede contener letras y números (máx. 20).';
        }

        if (empty(trim($data['nombres'] ?? ''))) {
            $errores[] = 'El nombre es obligatorio.';
        }
        if (empty(trim($data['apellidos'] ?? ''))) {
            $errores[] = 'El apellido es obligatorio.';
        }

        if (empty(trim($data['fecha_caducidad'] ?? ''))) {
            $errores[] = 'La fecha de caducidad de la firma es obligatoria.';
        }

        $correo = trim($data['correo'] ?? '');
        if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El correo electrónico no tiene un formato válido.';
        }

        $sexo = trim($data['sexo'] ?? '');
        if ($sexo !== '' && !in_array($sexo, ['hombre', 'mujer'], true)) {
            $errores[] = 'El sexo debe ser hombre o mujer.';
        }

        $tipoPago = trim($data['tipo_pago'] ?? '');
        if ($tipoPago !== '' && !in_array($tipoPago, ['transferencia', 'tarjeta'], true)) {
            $errores[] = 'El tipo de pago no es válido.';
        }

        if (!empty($errores)) {
            throw new Exception(implode(' ', $errores));
        }
    }
}
