<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

class DecimoTerceroRules
{
    public const TIPOS_PAGO = ['P', 'A', 'RP', 'RA'];
    public const BASES_CALCULO = ['todos', 'solo_iess'];

    public function validarCalculo(array $data): void
    {
        $anio = (int) ($data['anio'] ?? 0);
        if ($anio < 2000 || $anio > 2100) {
            throw new Exception('El año de declaración no es válido.');
        }
        if (!in_array($data['base_calculo'] ?? '', self::BASES_CALCULO, true)) {
            throw new Exception('La base de cálculo no es válida.');
        }
    }

    public function validarRecalculo(array $cabecera, bool $tienePagos): void
    {
        if ($tienePagos) {
            throw new Exception('Ya se registraron pagos (Egresos) sobre esta declaración; no se puede recalcular.');
        }
    }

    public function validarExportacion(array $cabecera): void
    {
        if (($cabecera['estado'] ?? '') !== 'calculado') {
            throw new Exception('Calcule la declaración antes de exportar el archivo.');
        }
    }

    public function validarAnulacion(array $cabecera, bool $tienePagos): void
    {
        if ($tienePagos) {
            throw new Exception('No se puede anular: ya hay pagos (Egresos) registrados sobre esta declaración.');
        }
    }

    public function validarDetalle(array $campos): void
    {
        if (isset($campos['tipo_pago']) && !in_array($campos['tipo_pago'], self::TIPOS_PAGO, true)) {
            throw new Exception('El tipo de pago no es válido (use P, A, RP o RA).');
        }
        if (isset($campos['valor_retencion']) && (float) $campos['valor_retencion'] < 0) {
            throw new Exception('El valor de retención no puede ser negativo.');
        }
        if (isset($campos['total_ganado']) && (float) $campos['total_ganado'] < 0) {
            throw new Exception('El total ganado no puede ser negativo.');
        }
    }
}
