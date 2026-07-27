<?php
/**
 * Migración: agrega columna email a nuvei_transacciones.
 * Necesaria para poder enviar el correo de confirmación de pago (requisito de
 * Nuvei) desde el webhook/onResponse, que llega desacoplado de la sesión HTTP
 * original — sin esta columna no habría forma de saber a quién notificar.
 * Ejecutar: C:\xampp\php\php.exe database/migrations/nuvei_add_email_transacciones.php
 */

define('MVC_ROOT',   dirname(__DIR__, 2));
define('MVC_CONFIG', MVC_ROOT . '/config');

$config = require MVC_CONFIG . '/database.php';
$dsn    = sprintf('pgsql:host=%s;port=%d;dbname=%s', $config['host'], $config['port'], $config['name']);
$pdo    = new PDO($dsn, $config['user'], $config['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$pdo->exec("
    ALTER TABLE nuvei_transacciones
        ADD COLUMN IF NOT EXISTS email VARCHAR(150);

    COMMENT ON COLUMN nuvei_transacciones.email IS
        'Correo del cliente, para enviar la confirmación de pago tras aprobarse/rechazarse.';
");

echo "✓ Columna email agregada a nuvei_transacciones.\n";
