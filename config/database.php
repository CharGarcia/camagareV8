<?php
/**
 * Credenciales de PostgreSQL (PDO + extensión pdo_pgsql en php.ini).
 *
 * SEGURIDAD: este archivo ESTÁ VERSIONADO EN GIT. Nunca escribir aquí la
 * contraseña ni ningún otro secreto: quien tenga acceso al repositorio (hoy o
 * tras una fuga futura) tendría la base de datos de todos los clientes.
 *
 * Orden de resolución (gana el primero que esté completo):
 *   1. Variables de entorno: DB_HOST, DB_PORT, DB_USER, DB_PASS, DB_NAME
 *   2. config/parametros.xml   (no versionado)
 *   3. config/local.php → clave 'database'  (no versionado)
 *
 * Si no hay credenciales por ninguna vía, el arranque se detiene con un mensaje
 * claro en lugar de intentar conectar con una contraseña por defecto.
 */

declare(strict_types=1);

$base = [
    'driver'  => 'pgsql',
    'host'    => '127.0.0.1',
    'port'    => 5432,
    'user'    => '',
    'pass'    => '',
    'name'    => '',
    'charset' => 'UTF8',
];

// ── 1. Variables de entorno ──────────────────────────────────────────────────
$env = [
    'host' => getenv('DB_HOST'),
    'port' => getenv('DB_PORT'),
    'user' => getenv('DB_USER'),
    'pass' => getenv('DB_PASS'),
    'name' => getenv('DB_NAME'),
];
if (!empty($env['user']) && !empty($env['name'])) {
    return array_merge($base, [
        'host' => $env['host'] !== false && $env['host'] !== '' ? (string) $env['host'] : $base['host'],
        'port' => $env['port'] !== false && (int) $env['port'] > 0 ? (int) $env['port'] : $base['port'],
        'user' => (string) $env['user'],
        'pass' => $env['pass'] !== false ? (string) $env['pass'] : '',
        'name' => (string) $env['name'],
    ]);
}

// ── 2. config/parametros.xml ─────────────────────────────────────────────────
$parametrosPath = __DIR__ . '/parametros.xml';
if (is_file($parametrosPath)) {
    $xml = @simplexml_load_file($parametrosPath);
    if ($xml !== false && (string) $xml->user_db !== '' && (string) $xml->db_name !== '') {
        $port = isset($xml->port_db) ? (int) (string) $xml->port_db : 0;
        return array_merge($base, [
            'host' => (string) $xml->host_db !== '' ? (string) $xml->host_db : $base['host'],
            'port' => $port > 0 ? $port : $base['port'],
            'user' => (string) $xml->user_db,
            'pass' => (string) $xml->pass_db,
            'name' => (string) $xml->db_name,
        ]);
    }
}

// ── 3. config/local.php → 'database' ─────────────────────────────────────────
$localPath = __DIR__ . '/local.php';
if (is_file($localPath)) {
    $local = require $localPath;
    $cfg   = is_array($local) && isset($local['database']) && is_array($local['database'])
        ? $local['database']
        : [];
    if (!empty($cfg['user']) && !empty($cfg['name'])) {
        return array_merge($base, array_intersect_key($cfg, $base));
    }
}

// ── Sin credenciales: detener con un mensaje entendible ──────────────────────
http_response_code(500);
exit(
    'Configuración de base de datos ausente. Defina las credenciales en '
    . 'config/parametros.xml, en config/local.php (clave "database") o en las '
    . 'variables de entorno DB_HOST/DB_PORT/DB_USER/DB_PASS/DB_NAME. '
    . 'Ver config/local.php.example.'
);
