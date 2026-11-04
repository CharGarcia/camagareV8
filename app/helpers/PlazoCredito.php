<?php
declare(strict_types=1);

namespace App\Helpers;

/**
 * Tope único del plazo de crédito (clientes, proveedores, facturas de venta y compras).
 *
 * Origen: fichas de cliente con un número de teléfono escrito en "Plazo (días)"
 * (993024389) o con el máximo de un integer (2147483647) se copiaban a cada factura
 * como días de crédito, y el cálculo del vencimiento (fecha + días) se salía del rango
 * de timestamp: «SQLSTATE[22008] timestamp out of range», que tumbaba el Dashboard y
 * Cuentas por Cobrar/Pagar. Ver docs/manual/conceptos/plazo-credito.md.
 *
 * - validar(): para lo que digita el usuario → devuelve el mensaje de error (o null);
 *   cada Rules lo lanza a su manera.
 * - acotar(): para lo que llega de un XML del SRI (no se puede rechazar un comprobante
 *   que el SRI ya autorizó) → deja el plazo en el tope. El XML se guarda intacto.
 */
final class PlazoCredito
{
    /** Máximo de días de crédito admitido: 10 años. */
    public const MAX_DIAS = 3650;

    /** Días que representa un plazo según su unidad (dias / meses / anos). */
    public static function enDias($plazo, ?string $unidad = 'dias'): int
    {
        $p = (int) $plazo;
        return match (self::unidad($unidad)) {
            'meses' => $p * 30,
            'anos'  => $p * 365,
            default => $p,
        };
    }

    /**
     * Mensaje de error si el plazo es negativo o supera el tope; null si es válido.
     *
     * @param mixed $plazo valor tal como llega (int o texto del formulario)
     */
    public static function validar($plazo, ?string $unidad = 'dias', string $etiqueta = 'El plazo de crédito'): ?string
    {
        if ($plazo === null || $plazo === '') {
            return null;
        }
        if (!is_numeric($plazo) || (float) $plazo != (int) $plazo) {
            return "{$etiqueta}: debe ser un número entero.";
        }
        // Un texto numérico gigante (p. ej. un teléfono con código de país) se pasa del
        // int de PHP: se compara como float antes de convertirlo.
        if ((float) $plazo > PHP_INT_MAX / 365) {
            return self::mensajeTope($etiqueta);
        }
        $dias = self::enDias($plazo, $unidad);
        if ($dias < 0) {
            return "{$etiqueta}: no se admiten valores negativos.";
        }
        if ($dias > self::MAX_DIAS) {
            return self::mensajeTope($etiqueta);
        }
        return null;
    }

    /** Plazo acotado al tope, en su misma unidad (para datos que vienen de un XML). */
    public static function acotar($plazo, ?string $unidad = 'dias'): int
    {
        $p = is_numeric($plazo) ? (float) $plazo : 0.0;
        if ($p <= 0) {
            return 0;
        }
        $max = match (self::unidad($unidad)) {
            'meses' => intdiv(self::MAX_DIAS, 30),
            'anos'  => intdiv(self::MAX_DIAS, 365),
            default => self::MAX_DIAS,
        };
        return (int) min($p, $max);
    }

    private static function mensajeTope(string $etiqueta): string
    {
        return sprintf(
            '%s: el máximo permitido es %s días (10 años). Revise que no se haya escrito un teléfono o una fecha en ese campo.',
            $etiqueta,
            number_format(self::MAX_DIAS, 0, ',', '.')
        );
    }

    private static function unidad(?string $unidad): string
    {
        $u = strtolower(trim((string) $unidad));
        $u = str_replace(['ñ', 'Ñ'], 'n', $u);
        return match ($u) {
            'mes', 'meses'  => 'meses',
            'ano', 'anos'   => 'anos',
            default         => 'dias',
        };
    }
}
