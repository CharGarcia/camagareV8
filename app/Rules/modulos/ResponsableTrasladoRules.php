<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use InvalidArgumentException;

class ResponsableTrasladoRules
{
    public const ESTADOS = ['activo', 'inactivo'];

    public function validarCrear(array $data): void
    {
        $this->validarBase($data);
    }

    public function validarActualizar(array $data): void
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID del responsable de traslado requerido.');
        }
        $this->validarBase($data);
    }

    private function validarBase(array $data): void
    {
        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre del responsable es obligatorio.');
        }
        if (mb_strlen($nombre) > 100) {
            throw new InvalidArgumentException('El nombre no puede superar 100 caracteres.');
        }

        // Identificación opcional: hay responsables antiguos registrados solo con
        // nombre desde el modal rápido de Pedidos. Si viene, sí se valida.
        $identificacion = trim((string) ($data['identificacion'] ?? ''));
        if ($identificacion !== '') {
            if (mb_strlen($identificacion) > 20) {
                throw new InvalidArgumentException('La identificación no puede superar 20 caracteres.');
            }
            if (!preg_match('/^[A-Za-z0-9\-]+$/', $identificacion)) {
                throw new InvalidArgumentException('La identificación solo admite letras, números y guiones.');
            }
        }

        $telefono = trim((string) ($data['telefono'] ?? ''));
        if ($telefono !== '' && mb_strlen($telefono) > 20) {
            throw new InvalidArgumentException('El teléfono no puede superar 20 caracteres.');
        }

        $email = trim((string) ($data['email'] ?? ''));
        if ($email !== '') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('El formato del correo electrónico no es válido.');
            }
            if (mb_strlen($email) > 150) {
                throw new InvalidArgumentException('El correo no puede superar 150 caracteres.');
            }
        }

        $estado = (string) ($data['estado'] ?? 'activo');
        if (!in_array($estado, self::ESTADOS, true)) {
            throw new InvalidArgumentException('El estado debe ser "activo" o "inactivo".');
        }
    }
}
