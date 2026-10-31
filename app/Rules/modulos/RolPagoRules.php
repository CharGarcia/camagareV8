<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use App\models\CatalogoRol;
use Exception;

class RolPagoRules
{
    public function validateCabecera(array $data): void
    {
        $tipo = trim((string) ($data['tipo_rol'] ?? ''));
        if (!CatalogoRol::esTipoValido($tipo)) {
            throw new Exception('El tipo de rol no es válido.');
        }

        $mes = (int) ($data['periodo_mes'] ?? 0);
        if ($mes < 1 || $mes > 12) {
            throw new Exception('El mes del período debe estar entre 1 y 12.');
        }

        $anio = (int) ($data['periodo_anio'] ?? 0);
        if ($anio < 2000 || $anio > 2100) {
            throw new Exception('El año del período no es válido.');
        }

        $num = (int) ($data['numero_periodo'] ?? 0);
        // Una sola quincena por mes (la 1): con dos, las novedades de quincena del mes se
        // cargaban en ambas corridas.
        if ($tipo === 'QUINCENA' && $num !== 1) {
            throw new Exception('Solo se puede generar una quincena por mes.');
        }
        if ($tipo === 'SEMANAL' && ($num < 1 || $num > 5)) {
            throw new Exception('La semana debe estar entre 1 y 5.');
        }
    }
}
