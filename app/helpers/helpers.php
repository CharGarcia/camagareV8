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

if (!function_exists('rutaAbrePestanaNueva')) {
    /**
     * ¿Ese submódulo del menú debe abrirse en una pestaña nueva?
     *
     * Son las pantallas STANDALONE del Punto de Venta: se dibujan sin el layout
     * del sistema (sin navbar ni menú), así que abiertas en la misma pestaña
     * dejan al usuario sin manera de volver. El botón "Restaurante" del navbar
     * ya las abre con target="_blank"; esto hace que el menú se comporte igual.
     *
     * Ojo con 'modulos/comandas': su index redirige al tablero de mesas
     * (modulos/mesas/tablero), que es standalone — por eso entra en la lista
     * aunque la ruta del submódulo no lo parezca. 'modulos/mesas' NO está: ese
     * es el listado administrativo de mesas, con layout normal.
     */
    function rutaAbrePestanaNueva(?string $ruta): bool
    {
        $r = strtolower(trim((string) $ruta));
        $r = ltrim($r, '/');
        $r = preg_replace('#^(sistema/)+#', '', $r);
        $r = str_replace('_', '-', ltrim($r, '/'));

        return in_array($r, [
            'modulos/comandas',   // redirige al tablero de mesas (standalone)
            'modulos/kds',        // pantalla de preparación (standalone)
            'modulos/caja-pos',   // apertura/cierre de caja (standalone)
        ], true);
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
