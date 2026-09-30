<?php

declare(strict_types=1);

namespace App\Helpers;

use App\core\Database;

/**
 * Liquidaciones de compra "pagadas en el sistema anterior".
 *
 * El sistema anterior no registraba pagos de liquidaciones hasta 2020; al migrarlas salían
 * pendientes de pago aunque estaban pagadas. La migración las marca con la columna
 * `liquidaciones_cabecera.pagada_sistema_anterior` (liquidaciones migradas con fecha de emisión
 * hasta FECHA_CORTE y saldo pendiente) y TODO cálculo de saldo de una liquidación la trata como
 * saldo 0 — sin crear egresos ni mover caja/bancos.
 *
 * Mientras el SQL de la columna no se haya aplicado en un ambiente, flag() devuelve 'false' y
 * todo se comporta como antes (el código no se rompe por desplegarlo antes que el SQL).
 */
final class LiquidacionPagoAnterior
{
    public const COLUMNA     = 'pagada_sistema_anterior';
    public const FECHA_CORTE = '2020-12-31';

    private static ?bool $existe = null;

    /** ¿Existe la columna en la base? (una consulta por proceso). */
    public static function existe(): bool
    {
        if (self::$existe === null) {
            try {
                $st = Database::getConnection()->query(
                    "SELECT 1 FROM information_schema.columns
                      WHERE table_name = 'liquidaciones_cabecera' AND column_name = '" . self::COLUMNA . "' LIMIT 1"
                );
                self::$existe = $st !== false && $st->fetchColumn() !== false;
            } catch (\Throwable $e) {
                self::$existe = false;
            }
        }
        return self::$existe;
    }

    /** Expresión SQL booleana: la liquidación del alias está marcada como pagada en el sistema anterior. */
    public static function flag(string $alias): string
    {
        return self::existe() ? "COALESCE($alias." . self::COLUMNA . ", false)" : 'false';
    }

    /** Envuelve una expresión de saldo: 0 si la liquidación está marcada, la expresión si no. */
    public static function saldo(string $alias, string $saldoExpr): string
    {
        return "(CASE WHEN " . self::flag($alias) . " THEN 0 ELSE $saldoExpr END)";
    }
}
