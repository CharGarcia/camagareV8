<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

class MarcacionRules
{
    public const TIPOS   = ['entrada', 'salida', 'inicio_break', 'fin_break'];
    public const METODOS = ['qr_punto', 'geo', 'manual', 'facial'];

    public function validate(array $data): void
    {
        if (empty($data['id_empleado']) || (int) $data['id_empleado'] <= 0) {
            throw new Exception('No se pudo identificar al empleado.');
        }

        $tipo = trim((string) ($data['tipo'] ?? ''));
        if (!in_array($tipo, self::TIPOS, true)) {
            throw new Exception('El tipo de marcación no es válido.');
        }

        $metodo = trim((string) ($data['metodo'] ?? 'qr_punto'));
        if (!in_array($metodo, self::METODOS, true)) {
            throw new Exception('El método de marcación no es válido.');
        }

        // Coordenadas opcionales, pero si vienen deben ser válidas.
        if (isset($data['latitud']) && $data['latitud'] !== null && $data['latitud'] !== '') {
            if (!is_numeric($data['latitud']) || (float) $data['latitud'] < -90 || (float) $data['latitud'] > 90) {
                throw new Exception('La latitud de la marcación no es válida.');
            }
        }
        if (isset($data['longitud']) && $data['longitud'] !== null && $data['longitud'] !== '') {
            if (!is_numeric($data['longitud']) || (float) $data['longitud'] < -180 || (float) $data['longitud'] > 180) {
                throw new Exception('La longitud de la marcación no es válida.');
            }
        }
    }
}
