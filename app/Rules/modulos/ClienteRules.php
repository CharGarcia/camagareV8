<?php
declare(strict_types=1);

namespace App\Rules\modulos;

class ClienteRules
{
    /**
     * Valida los datos básicos de un cliente.
     * @throws \InvalidArgumentException si la validación falla.
     */
    public function validar(array $data): void
    {
        if (trim($data['nombre'] ?? '') === '') {
            throw new \InvalidArgumentException('El nombre es obligatorio.');
        }

        if (trim($data['identificacion'] ?? '') === '') {
            throw new \InvalidArgumentException('La identificación es obligatoria.');
        }

        if (trim($data['tipo_id'] ?? '') === '') {
            throw new \InvalidArgumentException('El tipo de identificación es obligatorio.');
        }

        if (empty(trim($data['email'] ?? ''))) {
            throw new \InvalidArgumentException('El correo electrónico es obligatorio.');
        } else {
            $emails = array_map('trim', explode(',', trim($data['email'])));
            foreach ($emails as $email) {
                if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new \InvalidArgumentException("El formato del correo electrónico '{$email}' no es válido.");
                }
            }
        }
    }
}
