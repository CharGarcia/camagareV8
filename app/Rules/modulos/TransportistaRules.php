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
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('El email no tiene un formato válido.');
        }
        if (!empty($data['placa']) && strlen($data['placa']) > 8) {
            throw new \InvalidArgumentException('La placa no puede superar 8 caracteres.');
        }
    }
}
