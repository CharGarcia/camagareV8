<?php

declare(strict_types=1);

namespace App\Rules\modulos;

/**
 * Validaciones de la Declaración de Impuesto a la Renta: el período y los ajustes manuales
 * que el usuario escribe en pantalla (porcentajes, cargas familiares, valores que el sistema
 * no conoce). Devuelven los valores ya saneados para que el Service no vuelva a limpiarlos.
 */
class DeclaracionRentaRules
{
    public const CARGAS_MAX = 5;

    /** Año fiscal válido (entre 2000 y el año en curso + 1). */
    public function validarAnio($anio): int
    {
        $a = (int) $anio;
        $max = (int) date('Y') + 1;
        if ($a < 2000 || $a > $max) {
            throw new \InvalidArgumentException("Año fiscal inválido: {$anio}.");
        }
        return $a;
    }

    /**
     * Ajustes manuales. Claves admitidas (todas opcionales):
     *  - cargas_familiares (0..5), caso_especial (0/1)
     *  - otros_ingresos, otras_deducciones, gastos_no_deducibles, rentas_exentas,
     *    deducciones_adicionales, amortizacion_perdidas, anticipo_pagado,
     *    credito_anios_anteriores, otros_creditos  (montos >= 0)
     *  - participacion_trabajadores_pct, tarifa_pct (0..100)
     *  - fuente ('documentos' | 'contabilidad')
     */
    public function validarAjustes(array $in): array
    {
        $out = [];

        $cargas = (int) ($in['cargas_familiares'] ?? 0);
        if ($cargas < 0 || $cargas > self::CARGAS_MAX) {
            throw new \InvalidArgumentException('Las cargas familiares deben estar entre 0 y ' . self::CARGAS_MAX . '.');
        }
        $out['cargas_familiares'] = $cargas;
        $out['caso_especial'] = !empty($in['caso_especial']) && (string) $in['caso_especial'] !== '0';

        foreach (['otros_ingresos', 'otras_deducciones', 'gastos_no_deducibles', 'rentas_exentas',
                  'deducciones_adicionales', 'amortizacion_perdidas', 'anticipo_pagado',
                  'credito_anios_anteriores', 'otros_creditos'] as $k) {
            $out[$k] = $this->monto($in[$k] ?? 0, $k);
        }

        $out['participacion_trabajadores_pct'] = $this->porcentaje($in['participacion_trabajadores_pct'] ?? 15, 'participación a trabajadores', 15.0);
        $out['tarifa_pct'] = $this->porcentaje($in['tarifa_pct'] ?? 25, 'tarifa del impuesto', 25.0);

        $fuente = (string) ($in['fuente'] ?? 'documentos');
        $out['fuente'] = in_array($fuente, ['documentos', 'contabilidad'], true) ? $fuente : 'documentos';

        return $out;
    }

    private function monto($v, string $campo): float
    {
        if ($v === null || $v === '') {
            return 0.0;
        }
        $v = str_replace(',', '', (string) $v);
        if (!is_numeric($v)) {
            throw new \InvalidArgumentException("El valor de '{$campo}' no es numérico.");
        }
        $f = round((float) $v, 2);
        if ($f < 0) {
            throw new \InvalidArgumentException("El valor de '{$campo}' no puede ser negativo.");
        }
        return $f;
    }

    private function porcentaje($v, string $campo, float $defecto): float
    {
        if ($v === null || $v === '') {
            return $defecto;
        }
        if (!is_numeric($v)) {
            throw new \InvalidArgumentException("El porcentaje de {$campo} no es numérico.");
        }
        $f = (float) $v;
        if ($f < 0 || $f > 100) {
            throw new \InvalidArgumentException("El porcentaje de {$campo} debe estar entre 0 y 100.");
        }
        return round($f, 2);
    }
}
