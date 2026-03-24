<?php
/**
 * Configuración de tema - colores y apariencia del sistema
 * Se puede personalizar desde Configuración > Apariencia
 */

return [
    // Cuerpo - degradado de fondo
    'body' => [
        'gradient_start' => '#e8f4f8',
        'gradient_end' => '#f0f7fa',
        'gradient_angle' => '135deg',
    ],

    // Color principal (navbar, botones, headers)
    'primary' => [
        'main' => '#6eb5d0',
        'hover' => '#5ca3bd',
        'text' => '#ffffff',
    ],

    // Enlaces
    'links' => [
        'color' => '#0d6efd',
        'hover' => '#0a58ca',
    ],

    // Tipografía
    'typography' => [
        'font_size_base' => '0.9375rem',  // 15px
        'font_family' => 'system-ui, -apple-system, sans-serif',
    ],

    // Bordes
    'borders' => [
        'radius' => '0.375rem',   // 6px - Bootstrap default
        'radius_sm' => '0.25rem',
        'radius_lg' => '0.5rem',
    ],

    // Presets de color principal
    'presets' => [
        'celeste_suave' => '#6eb5d0',
        'celeste_claro' => '#87CEEB',
        'celeste_polvo' => '#B0E0E6',
        'celeste_agua' => '#7EC8E3',
        'azul_cielo' => '#89CFF0',
        'verde_agua' => '#7FDBDA',
        'azul_oscuro' => '#0d6efd',
        'verde' => '#198754',
    ],
];
