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
