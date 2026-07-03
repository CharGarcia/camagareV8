<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Envoltura mínima sobre APCu con degradación segura.
 *
 * Si APCu no está disponible (p. ej. entorno local sin la extensión, o CLI con
 * apc.enable_cli=0), get() devuelve siempre null y set()/delete() no hacen nada:
 * el sistema sigue funcionando, solo que sin caché (consulta directa cada vez).
 *
 * Uso pensado para datos pequeños y de alta frecuencia (contadores del navbar).
 */
class Cache
{
    private static ?bool $enabled = null;

    /** ¿APCu está instalado y habilitado en este SAPI? */
    public static function disponible(): bool
    {
        if (self::$enabled === null) {
            self::$enabled = function_exists('apcu_enabled') && apcu_enabled();
        }
        return self::$enabled;
    }

    /** Devuelve el valor cacheado, o null si no existe o no hay APCu. */
    public static function get(string $clave): mixed
    {
        if (!self::disponible()) {
            return null;
        }
        $ok = false;
        $val = apcu_fetch($clave, $ok);
        return $ok ? $val : null;
    }

    /** Guarda un valor con TTL (segundos). No-op si no hay APCu. */
    public static function set(string $clave, mixed $valor, int $ttlSegundos = 30): void
    {
        if (!self::disponible()) {
            return;
        }
        apcu_store($clave, $valor, $ttlSegundos);
    }

    /** Elimina una clave de la caché. No-op si no hay APCu. */
    public static function delete(string $clave): void
    {
        if (!self::disponible()) {
            return;
        }
        apcu_delete($clave);
    }
}
