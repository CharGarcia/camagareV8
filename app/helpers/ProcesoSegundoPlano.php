<?php
declare(strict_types=1);

namespace App\Helpers;

/**
 * Lanza un script CLI de `scripts/` desligado del request (no bloquea la respuesta).
 * Windows (XAMPP): start /B; Linux (producción): nohup … &. Mismo mecanismo que el envío en
 * lote al SRI (EnvioLoteSriController::lanzarWorker). El binario de PHP se toma de
 * config/app.php → 'sri_lote_php_bin' si está definido.
 */
final class ProcesoSegundoPlano
{
    /** @param array<string,int|string> $args  ['emision' => 12] → --emision=12 */
    public static function lanzar(string $script, array $args = []): bool
    {
        $ruta = MVC_ROOT . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . basename($script);
        if (!is_file($ruta)) {
            return false;
        }
        $params = '';
        foreach ($args as $k => $v) {
            $params .= ' ' . escapeshellarg('--' . preg_replace('/[^a-z0-9_]/i', '', (string) $k) . '=' . $v);
        }
        $php = self::phpBin();
        try {
            if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
                $h = popen('start /B "" ' . escapeshellarg($php) . ' ' . escapeshellarg($ruta) . $params, 'r');
                if ($h === false) {
                    return false;
                }
                pclose($h);
                return true;
            }
            @exec('nohup ' . escapeshellarg($php) . ' ' . escapeshellarg($ruta) . $params . ' > /dev/null 2>&1 &');
            return true;
        } catch (\Throwable $e) {
            error_log('[ProcesoSegundoPlano] No se pudo lanzar ' . $script . ': ' . $e->getMessage());
            return false;
        }
    }

    private static function phpBin(): string
    {
        $cfg = is_file(MVC_CONFIG . '/app.php') ? require MVC_CONFIG . '/app.php' : [];
        $bin = trim((string) ($cfg['sri_lote_php_bin'] ?? ''));
        if ($bin !== '') {
            return $bin;
        }
        if (strncasecmp(PHP_OS, 'WIN', 3) === 0 && is_file('C:\\xampp\\php\\php.exe')) {
            return 'C:\\xampp\\php\\php.exe';
        }
        return 'php';
    }
}
