<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

class DeclaracionRetencionesRules
{
    public function validarGuardado(array $data, ?array $existente): void
    {
        $anio = (int) ($data['periodo_anio'] ?? 0);
        if ($anio < 2000 || $anio > 2100) {
            throw new Exception('El año del período no es válido.');
        }

        $mes = (int) ($data['periodo_mes'] ?? 0);
        if ($mes < 1 || $mes > 12) {
            throw new Exception('El mes del período no es válido.');
        }

        if ($existente && ($existente['estado'] ?? '') === 'pagado') {
            throw new Exception('Esta declaración ya tiene un egreso generado. Anule el egreso desde el módulo de Egresos para poder modificarla.');
        }
    }

    public function validarGenerarEgreso(array $declaracion): void
    {
        if (!empty($declaracion['id_egreso'])) {
            throw new Exception('Esta declaración ya tiene un egreso generado.');
        }
        if ((float) ($declaracion['total_retenido'] ?? 0) <= 0.0) {
            throw new Exception('Esta declaración no tiene valor a pagar; no se puede generar un egreso.');
        }
    }
}
