<?php
/**
 * Configuración de base de datos MySQL
 * Usa parametros.xml del sistema principal para compatibilidad
 */

$parametrosPath = __DIR__ . '/parametros.xml';
if (file_exists($parametrosPath)) {
    $xml = simplexml_load_file($parametrosPath);
    return [
        'host' => (string) $xml->host_db,
        'user' => (string) $xml->user_db,
        'pass' => (string) $xml->pass_db,
        'name' => (string) $xml->db_name,
        'charset' => 'utf8mb4',
    ];
}

return [
    'host' => '127.0.0.1',
    'user' => 'root',
    'pass' => '',
    'name' => 'camagare_v8',
    'charset' => 'utf8mb4',
];
