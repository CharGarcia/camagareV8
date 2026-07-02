<?php
/**
 * Funciones auxiliares globales
 */

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = rtrim(BASE_URL ?? '', '/');
        $path = ltrim($path, '/');
        return $path !== '' ? "{$base}/{$path}" : $base;
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return (BASE_URL ?? '') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('url_absoluta')) {
    /**
     * URL absoluta (con esquema y dominio) para usar en correos u otros contextos
     * externos donde una URL relativa no sirve. Usa APP_URL (config/local.php) si
     * está definido; si no, la deriva del request actual ($_SERVER).
     */
    function url_absoluta(string $path = ''): string
    {
        $root = (defined('APP_URL') && APP_URL !== '') ? rtrim(APP_URL, '/') : '';

        if ($root === '') {
            $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                   || (($_SERVER['SERVER_PORT'] ?? '') == 443)
                   || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
            $scheme = $https ? 'https' : 'http';
            $host   = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
            if ($host !== '') {
                $root = $scheme . '://' . $host;
            }
        }

        $full = $root . rtrim(BASE_URL ?? '', '/');
        $path = ltrim($path, '/');
        return $path !== '' ? "{$full}/{$path}" : $full;
    }
}

if (!function_exists('iconoClase')) {
    function iconoClase(?string $nombre): string
    {
        if (empty($nombre)) return 'bi bi-folder';
        $n = trim($nombre);
        if (str_starts_with($n, 'bi ') || str_starts_with($n, 'bi-')) return $n;
        if (str_starts_with($n, 'fas ') || str_starts_with($n, 'far ') || str_starts_with($n, 'fab ') || str_starts_with($n, 'fa-solid ') || str_starts_with($n, 'fa-regular ')) return $n;
        if (preg_match('/^fa\s+fa-/', $n)) return 'fas ' . preg_replace('/^fa\s+fa-/', 'fa-', $n);
        if (str_starts_with($n, 'fa-')) return 'fas ' . $n;
        if (str_contains($n, 'fa-')) return $n;
        return 'bi bi-' . $n;
    }
}
