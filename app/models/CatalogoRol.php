<?php

declare(strict_types=1);

namespace App\models;

/**
 * Catálogo fijo del módulo Roles de Pago: tipos de corrida y estados.
 */
final class CatalogoRol
{
    public const TIPOS = [
        'MENSUAL'  => 'Rol Mensual',
        'QUINCENA' => 'Quincena',
        'SEMANAL'  => 'Semanal',
    ];

    public const ESTADOS = [
        'borrador'      => 'Borrador',
        'generado'      => 'Generado',
        'pagado'        => 'Pagado',
        'contabilizado' => 'Contabilizado',
        'anulado'       => 'Anulado',
    ];

    /** tipo_rol -> valor de novedades.aplica_en */
    public const APLICA_EN = [
        'MENSUAL'  => 'rol',
        'QUINCENA' => 'quincena',
        'SEMANAL'  => 'semanal',
    ];

    public static function tipos(): array
    {
        return self::TIPOS;
    }

    public static function estados(): array
    {
        return self::ESTADOS;
    }

    public static function esTipoValido(string $t): bool
    {
        return array_key_exists($t, self::TIPOS);
    }

    public static function nombreTipo(string $t): string
    {
        return self::TIPOS[$t] ?? $t;
    }

    public static function nombreEstado(string $e): string
    {
        return self::ESTADOS[$e] ?? $e;
    }

    public static function aplicaEn(string $tipo): string
    {
        return self::APLICA_EN[$tipo] ?? 'rol';
    }
}
