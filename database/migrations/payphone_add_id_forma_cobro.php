<?php
/**
 * Migración: agrega columna id_forma_cobro a payphone_transacciones.
 * Guarda la forma de cobro (tipo TARJETA) seleccionada al generar el enlace,
 * para que el ingreso se registre con esa forma exacta (ej. Visa Crédito / Débito).
 * Ejecutar: C:\xampp\php\php.exe database/migrations/payphone_add_id_forma_cobro.php
 */

define('MVC_ROOT',   dirname(__DIR__, 2));
define('MVC_CONFIG', MVC_ROOT . '/config');

$config = require MVC_CONFIG . '/database.php';
$dsn    = sprintf('pgsql:host=%s;port=%d;dbname=%s', $config['host'], $config['port'], $config['name']);
$pdo    = new PDO($dsn, $config['user'], $config['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$pdo->exec("
    ALTER TABLE payphone_transacciones
        ADD COLUMN IF NOT EXISTS id_forma_cobro INTEGER;

    COMMENT ON COLUMN payphone_transacciones.id_forma_cobro IS
        'Forma de cobro (tipo TARJETA) seleccionada al generar el enlace; usada al registrar el ingreso.';
");

echo "✓ Columna id_forma_cobro agregada a payphone_transacciones.\n";
