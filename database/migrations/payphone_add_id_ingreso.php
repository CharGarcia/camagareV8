<?php
/**
 * Migración: agrega columna id_ingreso a payphone_transacciones.
 * Vincula la transacción Payphone con el ingreso (cobro) generado al aprobarse,
 * garantizando que no se cree más de un ingreso por transacción.
 * Ejecutar: C:\xampp\php\php.exe database/migrations/payphone_add_id_ingreso.php
 */

define('MVC_ROOT',   dirname(__DIR__, 2));
define('MVC_CONFIG', MVC_ROOT . '/config');

$config = require MVC_CONFIG . '/database.php';
$dsn    = sprintf('pgsql:host=%s;port=%d;dbname=%s', $config['host'], $config['port'], $config['name']);
$pdo    = new PDO($dsn, $config['user'], $config['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$pdo->exec("
    ALTER TABLE payphone_transacciones
        ADD COLUMN IF NOT EXISTS id_ingreso INTEGER;

    COMMENT ON COLUMN payphone_transacciones.id_ingreso IS
        'Ingreso (cobro) generado automáticamente al aprobarse el pago. NULL si aún no se generó.';
");

echo "✓ Columna id_ingreso agregada a payphone_transacciones.\n";
