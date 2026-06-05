<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Helper para el código de porcentaje de IVA del SRI (tabla 16 ficha técnica).
 *
 * El "codigoPorcentaje" del SRI NO es el porcentaje en sí, sino un código fijo:
 *   0  → 0%
 *   1  → 0% (gravada con tarifa 0 — uso histórico)
 *   2  → 12% (histórico, ya no vigente)
 *   3  → 14% (transitorio 2016)
 *   4  → 15% (vigente desde abril 2024)
 *   5  → 5%
 *   6  → No objeto de impuesto
 *   7  → Exento de IVA
 *   8  → IVA diferenciado / tarifa especial
 *   10 → 13%
 *
 * Hardcodear el código (p. ej. '2' para 12%) provoca "ERROR EN DIFERENCIAS"
 * del SRI cuando la tarifa real difiere del código declarado.
 */
class SriIvaHelper
{
    /** Mapa porcentaje (entero) → codigoPorcentaje SRI. */
    private const MAPA_PORCENTAJE = [
        0  => '0',
        5  => '5',
        12 => '2',
        13 => '10',
        14 => '3',
        15 => '4',
        8  => '8',
    ];

    /**
     * Devuelve el codigoPorcentaje del SRI para una tarifa de IVA dada.
     *
     * @param float|int|string $porcentaje Porcentaje de IVA (ej: 15, 12, 0).
     * @return string codigoPorcentaje del SRI (ej: '4' para 15%).
     */
    public static function codigoPorcentaje($porcentaje): string
    {
        $pct = (int) round((float) $porcentaje);
        return self::MAPA_PORCENTAJE[$pct] ?? '0';
    }
}
