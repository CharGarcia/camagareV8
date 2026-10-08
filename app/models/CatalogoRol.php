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

    /**
     * Color Bootstrap e ícono (Bootstrap Icons) de cada tipo de rol, para que el listado y
     * el modal los distingan de un vistazo: mensual azul, quincena gris oscuro, semanal ámbar.
     */
    public const ESTILO_TIPO = [
        'MENSUAL'  => ['color' => 'primary', 'icono' => 'bi-calendar-month'],
        'QUINCENA' => ['color' => 'dark',    'icono' => 'bi-calendar2-week'],
        'SEMANAL'  => ['color' => 'warning', 'icono' => 'bi-calendar-week'],
    ];

    /** Badge HTML (ícono + nombre) del tipo de rol, ya escapado. */
    public static function badgeTipo(string $t): string
    {
        $e = self::ESTILO_TIPO[$t] ?? ['color' => 'secondary', 'icono' => 'bi-calendar'];
        return '<span class="badge bg-' . $e['color'] . ' bg-opacity-10 text-' . $e['color']
            . ' border border-' . $e['color'] . ' border-opacity-25 fw-medium">'
            . '<i class="bi ' . $e['icono'] . ' me-1"></i>'
            . htmlspecialchars(self::nombreTipo($t), ENT_QUOTES, 'UTF-8') . '</span>';
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
