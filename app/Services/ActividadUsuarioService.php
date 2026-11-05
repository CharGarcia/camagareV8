<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Cache;
use App\repositories\ActividadUsuarioRepository;

/**
 * Registra en qué empresa trabaja cada usuario cada día (Métricas del sistema, Fase B).
 *
 * Se llama desde AuthMiddleware (web) y ApiAuthMiddleware (app móvil). Es telemetría:
 * NUNCA debe bloquear ni frenar la navegación, así que todo error se traga en silencio.
 *
 * Frenos para no escribir en cada petición:
 *  - Web: la sesión PHP guarda cuándo y para qué empresa se registró (5 min, o al cambiar
 *    de empresa / de día).
 *  - Móvil (JWT, sin sesión PHP persistente): APCu por usuario+empresa durante 5 min.
 *  - Siempre: el upsert del repositorio solo actualiza si pasaron 4 minutos.
 * Si la tabla todavía no existe (SQL sin aplicar), se recuerda en APCu una hora y no se
 * vuelve a intentar.
 */
class ActividadUsuarioService
{
    public const CANAL_WEB   = 'web';
    public const CANAL_MOVIL = 'movil';

    private const INTERVALO = 300;
    private const CACHE_SIN_TABLA = 'metricas_actividad:sin_tabla';

    public static function registrarWeb(): void
    {
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        if ($idUsuario <= 0 || $idEmpresa <= 0) {
            return;
        }

        $clave = date('Y-m-d') . '|' . $idEmpresa;
        if (($_SESSION['_actividad_clave'] ?? '') === $clave
            && time() - (int) ($_SESSION['_actividad_last'] ?? 0) < self::INTERVALO) {
            return;
        }
        // Se marca antes de escribir: si la BD falla, igual se espera al siguiente intervalo.
        $_SESSION['_actividad_clave'] = $clave;
        $_SESSION['_actividad_last']  = time();

        self::registrar($idUsuario, $idEmpresa, self::CANAL_WEB);
    }

    public static function registrarMovil(int $idUsuario, ?int $idEmpresa): void
    {
        if ($idUsuario <= 0 || (int) $idEmpresa <= 0) {
            return;
        }
        $clave = 'metricas_actividad:movil:' . $idUsuario . ':' . (int) $idEmpresa . ':' . date('Ymd');
        if (Cache::get($clave) !== null) {
            return;
        }
        Cache::set($clave, 1, self::INTERVALO);

        self::registrar($idUsuario, (int) $idEmpresa, self::CANAL_MOVIL);
    }

    private static function registrar(int $idUsuario, int $idEmpresa, string $canal): void
    {
        if (Cache::get(self::CACHE_SIN_TABLA) !== null) {
            return;
        }
        try {
            (new ActividadUsuarioRepository())->registrar($idUsuario, $idEmpresa, $canal);
        } catch (\Throwable $e) {
            // 42P01 = la tabla no existe todavía: no reintentar durante una hora.
            if ($e instanceof \PDOException && ($e->errorInfo[0] ?? $e->getCode()) === '42P01') {
                Cache::set(self::CACHE_SIN_TABLA, 1, 3600);
            }
        }
    }
}
