<?php

declare(strict_types=1);

namespace App\Rules\modulos;

class TransportistaRules
{
    public function validarCrear(array $data): void
    {
        $this->validarBase($data);
    }

    public function validarActualizar(array $data): void
    {
        if (empty($data['id'])) {
            throw new \InvalidArgumentException('ID del transportista requerido.');
        }
        $this->validarBase($data);
    }

    private function validarBase(array $data): void
    {
        if (empty(trim($data['nombre'] ?? ''))) {
            throw new \InvalidArgumentException('El nombre del transportista es requerido.');
        }
        if (strlen(trim($data['nombre'])) > 300) {
            throw new \InvalidArgumentException('El nombre no puede superar 300 caracteres.');
        }
        if (empty(trim($data['identificacion'] ?? ''))) {
            throw new \InvalidArgumentException('La identificación del transportista es requerida.');
        }
        $tiposPermitidos = ['04', '05', '06'];
        if (empty($data['tipo_id']) || !in_array($data['tipo_id'], $tiposPermitidos, true)) {
            throw new \InvalidArgumentException('El tipo de identificación no es válido. Use 04=RUC, 05=Cédula, 06=Pasaporte.');
        }
        // Se admiten VARIOS correos (separados por coma, punto y coma o espacio):
        // la guía de remisión se envía a todos. Antes se validaba la cadena
        // completa con FILTER_VALIDATE_EMAIL y "a@x.com, b@y.com" se rechazaba.
        if (!empty($data['email'])) {
            $correos   = preg_split('/[\s,;]+/', trim((string)$data['email']), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $invalidos = array_values(array_filter($correos, fn($c) => !filter_var($c, FILTER_VALIDATE_EMAIL)));
            if ($invalidos) {
                throw new \InvalidArgumentException('Correo(s) con formato inválido: ' . implode(', ', $invalidos));
            }
            if (mb_strlen(implode(', ', $correos)) > 200) {
                throw new \InvalidArgumentException('Los correos no pueden superar 200 caracteres en total.');
            }
        }
        if (!empty($data['placa']) && strlen($data['placa']) > 8) {
            throw new \InvalidArgumentException('La placa no puede superar 8 caracteres.');
        }
    }
}
