<?php
/**
 * Configuración de PostgreSQL (PDO + extensión pdo_pgsql en php.ini).
 *
 * Opcional: config/parametros.xml con host_db, port_db, user_db, pass_db, db_name.
 */

$parametrosPath = __DIR__ . '/parametros.xml';
if (file_exists($parametrosPath)) {
    $xml = simplexml_load_file($parametrosPath);
    $port = isset($xml->port_db) ? (int) (string) $xml->port_db : 5432;
    return [
        'driver' => 'pgsql',
        'host' => (string) $xml->host_db,
        'port' => $port > 0 ? $port : 5432,
        'user' => (string) $xml->user_db,
        'pass' => (string) $xml->pass_db,
        'name' => (string) $xml->db_name,
        'charset' => 'UTF8',
    ];
}

return [
    'driver' => 'pgsql',
    'host' => '127.0.0.1',
    'port' => 5432,
    'user' => 'postgres',
    'pass' => 'CmGr1980',
    'name' => 'camagare_v8',
    'charset' => 'UTF8',
];
