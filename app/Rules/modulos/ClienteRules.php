<?php
declare(strict_types=1);

namespace App\Rules\modulos;

class ClienteRules
{
    // Códigos de identificador_comprador_vendedor (tipo=1).
    private const RUC = '04';
    private const CEDULA = '05';
    private const PASAPORTE = '06';

    /**
     * Valida los datos básicos de un cliente.
     * @throws \InvalidArgumentException si la validación falla.
     */
    public function validar(array $data): void
    {
        if (trim($data['nombre'] ?? '') === '') {
            throw new \InvalidArgumentException('El nombre es obligatorio.');
        }

        $tipoId = trim($data['tipo_id'] ?? '');
        if ($tipoId === '') {
            throw new \InvalidArgumentException('El tipo de identificación es obligatorio.');
        }

        $identificacion = trim($data['identificacion'] ?? '');
        if ($identificacion === '') {
            throw new \InvalidArgumentException('La identificación es obligatoria.');
        }

        if ($tipoId === self::CEDULA) {
            if (!preg_match('/^[0-9]{10}$/', $identificacion)) {
                throw new \InvalidArgumentException('La cédula debe tener exactamente 10 dígitos numéricos.');
            }
        } elseif ($tipoId === self::RUC) {
            if (!preg_match('/^[0-9]{13}$/', $identificacion)) {
                throw new \InvalidArgumentException('El RUC debe tener exactamente 13 dígitos numéricos.');
            }
            $sufijo = substr($identificacion, -3);
            if ($sufijo !== '001' && $sufijo !== '002') {
                throw new \InvalidArgumentException('El RUC debe terminar en 001 o 002.');
            }
        } elseif ($tipoId === self::PASAPORTE) {
            if (mb_strlen($identificacion) > 20) {
                throw new \InvalidArgumentException('El pasaporte no puede exceder 20 caracteres.');
            }
        } elseif (mb_strlen($identificacion) > 30) {
            throw new \InvalidArgumentException('El número de identificación no puede exceder 30 caracteres.');
        }

        if (trim($data['email'] ?? '') === '') {
            throw new \InvalidArgumentException('El correo electrónico es obligatorio.');
        }

        $emails = array_map('trim', explode(',', trim($data['email'])));
        foreach ($emails as $email) {
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException("El formato del correo electrónico '{$email}' no es válido.");
            }
        }
    }
}
