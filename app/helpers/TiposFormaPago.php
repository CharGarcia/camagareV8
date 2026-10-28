<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Catálogo de los tipos de forma de cobro/pago (empresa_formas_pago.tipo) y si cada uno
 * mueve dinero: entra o sale de caja o bancos.
 *
 * - Anticipo: el dinero entró (o salió) cuando se registró el anticipo, con un ingreso o
 *   egreso de opción ANTICIPO_CLIENTE/PROVEEDOR; aplicarlo a una factura o compra no
 *   mueve dinero, y contarlo otra vez lo duplicaría.
 * - Otros: cruces, compensaciones y demás formas que no pasan por caja ni bancos.
 *
 * Es la fuente única: el selector del modal de Formas de cobro y pago se arma desde aquí
 * y el Reporte de Ingresos y Egresos lo usa para separar lo no monetario de la entrada y
 * salida de dinero. Un tipo nuevo se agrega aquí, con su marca.
 */
final class TiposFormaPago
{
    /** tipo => [nombre que ve el usuario, ¿mueve dinero?]. En el orden del selector. */
    public const CATALOGO = [
        'EFECTIVO' => ['nombre' => 'Efectivo', 'monetario' => true],
        'BANCO'    => ['nombre' => 'Bancaria', 'monetario' => true],
        'TARJETA'  => ['nombre' => 'Tarjeta',  'monetario' => true],
        'PAYPHONE' => ['nombre' => 'Payphone', 'monetario' => true],
        'NUVEI'    => ['nombre' => 'Nuvei',    'monetario' => true],
        'ANTICIPO' => ['nombre' => 'Anticipo', 'monetario' => false],
        'OTRO'     => ['nombre' => 'Otros',    'monetario' => false],
    ];

    /** ¿El tipo mueve dinero? Un tipo que no está en el catálogo se considera que sí. */
    public static function esMonetario(string $tipo): bool
    {
        return self::CATALOGO[strtoupper($tipo)]['monetario'] ?? true;
    }

    /** Tipos que no mueven dinero (p. ej. ['ANTICIPO', 'OTRO']). */
    public static function noMonetarios(): array
    {
        return array_keys(array_filter(self::CATALOGO, static fn (array $t): bool => !$t['monetario']));
    }

    /** Lista SQL de los tipos que no mueven dinero: 'ANTICIPO', 'OTRO' (valores fijos del catálogo). */
    public static function noMonetariosSql(): string
    {
        return "'" . implode("', '", self::noMonetarios()) . "'";
    }
}
