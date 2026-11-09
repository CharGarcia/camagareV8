<?php
declare(strict_types=1);

namespace App\Helpers;

/**
 * Deja lo que el cron crea dentro de storage/ con el mismo dueño que storage/.
 *
 * En producción el cron corre como root y Apache como www-data. Una carpeta que
 * crea el cron (p. ej. storage/crm_media/{empresa}/{AAAA-MM} al bajar un adjunto
 * de Instagram) queda de root con permisos 0750, y desde entonces Apache no puede
 * leer esos archivos ni guardar otros en la misma carpeta.
 *
 * Solo actúa si el proceso corre como root (en Windows/XAMPP o bajo Apache no
 * hace nada). Nunca falla: si no puede cambiar el dueño, lo deja como está.
 */
final class DuenoStorage
{
    /**
     * Cambia al dueño de storage/ la ruta indicada y cada carpeta entre storage/ y ella.
     * $rutaAbs debe estar dentro de MVC_ROOT/storage; si no, no hace nada.
     */
    public static function igualar(string $rutaAbs): void
    {
        if (!function_exists('posix_geteuid') || posix_geteuid() !== 0 || !defined('MVC_ROOT')) {
            return;
        }
        $base = realpath(MVC_ROOT . '/storage');
        $ruta = realpath($rutaAbs);
        if ($base === false || $ruta === false || !str_starts_with($ruta, $base . DIRECTORY_SEPARATOR)) {
            return;
        }
        $uid = @fileowner($base);
        $gid = @filegroup($base);
        if ($uid === false || $gid === false || $uid === 0) {
            return;
        }
        $actual = $base;
        foreach (explode(DIRECTORY_SEPARATOR, substr($ruta, strlen($base) + 1)) as $parte) {
            if ($parte === '') {
                continue;
            }
            $actual .= DIRECTORY_SEPARATOR . $parte;
            if (@fileowner($actual) !== $uid) {
                @chown($actual, $uid);
                @chgrp($actual, $gid);
            }
        }
    }
}
