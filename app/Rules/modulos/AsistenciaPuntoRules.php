<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

class AsistenciaPuntoRules
{
    public function validate(array $data): void
    {
        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($nombre === '') {
            throw new Exception('El nombre del punto de servicio es obligatorio.');
        }

        $radio = (int) ($data['radio_m'] ?? 150);
        if ($radio < 10 || $radio > 5000) {
            throw new Exception('El radio de geocerca debe estar entre 10 y 5000 metros.');
        }

        // Si exige GPS, las coordenadas del punto son obligatorias (para el cruce anti-fraude).
        if (!empty($data['exige_gps'])) {
            $lat = $data['latitud'] ?? '';
            $lng = $data['longitud'] ?? '';
            if ($lat === '' || $lat === null || $lng === '' || $lng === null) {
                throw new Exception('Si el punto exige GPS, debe registrar su latitud y longitud.');
            }
            if (!is_numeric($lat) || (float) $lat < -90 || (float) $lat > 90) {
                throw new Exception('La latitud del punto no es válida.');
            }
            if (!is_numeric($lng) || (float) $lng < -180 || (float) $lng > 180) {
                throw new Exception('La longitud del punto no es válida.');
            }
        }

        if (!empty($data['estado']) && !in_array($data['estado'], ['activo', 'inactivo'], true)) {
            throw new Exception('El estado del punto no es válido.');
        }
    }
}
