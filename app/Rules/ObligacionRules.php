<?php
declare(strict_types=1);

namespace App\Rules;

use InvalidArgumentException;

class ObligacionRules
{
    public function validar(array $data): void
    {
        $nombre = trim($data['nombre'] ?? '');
        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre de la obligación es obligatorio.');
        }
        if (mb_strlen($nombre, 'UTF-8') > 200) {
            throw new InvalidArgumentException('El nombre no puede superar los 200 caracteres.');
        }

        $desc = trim($data['descripcion'] ?? '');
        if ($desc !== '' && mb_strlen($desc, 'UTF-8') > 1000) {
            throw new InvalidArgumentException('La descripción no puede superar los 1000 caracteres.');
        }

        $status = (int) ($data['status'] ?? 1);
        if (!in_array($status, [0, 1], true)) {
            throw new InvalidArgumentException('El estado indicado no es válido.');
        }
    }
}
