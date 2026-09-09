<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

class DeclaracionIvaRules
{
    public function validarGuardado(array $data, ?array $existente): void
    {
        $tipoPeriodo = (string) ($data['tipo_periodo'] ?? '');
        if (!in_array($tipoPeriodo, ['mensual', 'semestral'], true)) {
            throw new Exception('El tipo de período debe ser mensual o semestral.');
        }

        $anio = (int) ($data['periodo_anio'] ?? 0);
        if ($anio < 2000 || $anio > 2100) {
            throw new Exception('El año del período no es válido.');
        }

        $periodoValor = (int) ($data['periodo_valor'] ?? 0);
        if ($tipoPeriodo === 'mensual' && ($periodoValor < 1 || $periodoValor > 12)) {
            throw new Exception('El mes del período no es válido.');
        }
        if ($tipoPeriodo === 'semestral' && ($periodoValor < 1 || $periodoValor > 2)) {
            throw new Exception('El semestre del período no es válido.');
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
        // Se valida el MISMO importe con el que se va a generar el egreso
        // (DeclaracionIvaService::generarEgreso usa total_a_pagar, es decir el casillero 902 tal
        // como queda en el formulario). Validar iva_a_pagar dejaba bloqueado el egreso cuando el
        // 902 tiene una fórmula que da un valor mayor que el neto calculado internamente.
        // El fallback a iva_a_pagar cubre las declaraciones guardadas antes de existir la columna.
        $aPagar = (float) ($declaracion['total_a_pagar'] ?? $declaracion['iva_a_pagar'] ?? 0);
        if ($aPagar <= 0.0) {
            throw new Exception('Esta declaración no tiene valor a pagar; no se puede generar un egreso.');
        }
    }
}
