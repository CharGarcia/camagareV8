<?php
/**
 * Migración: agrega columna modalidad_tarjeta a empresa_formas_pago.
 * Aplica solo a formas de tipo TARJETA. Valores: DEBITO | CREDITO | AMBAS.
 * Ejecutar: C:\xampp\php\php.exe database/migrations/formas_pago_add_modalidad_tarjeta.php
 */

define('MVC_ROOT',   dirname(__DIR__, 2));
define('MVC_CONFIG', MVC_ROOT . '/config');

$config = require MVC_CONFIG . '/database.php';
$dsn    = sprintf('pgsql:host=%s;port=%d;dbname=%s', $config['host'], $config['port'], $config['name']);
$pdo    = new PDO($dsn, $config['user'], $config['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$pdo->exec("
    ALTER TABLE empresa_formas_pago
        ADD COLUMN IF NOT EXISTS modalidad_tarjeta VARCHAR(20);

    COMMENT ON COLUMN empresa_formas_pago.modalidad_tarjeta IS
        'Solo para tipo TARJETA: DEBITO, CREDITO o AMBAS.';
");

echo "✓ Columna modalidad_tarjeta agregada a empresa_formas_pago.\n";
