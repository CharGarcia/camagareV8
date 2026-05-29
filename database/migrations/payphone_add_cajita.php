<?php
/**
 * Migración: agrega columna tipo_flujo a payphone_transacciones
 * Valores: 'boton' (redirección clásica) | 'cajita' (widget embebido)
 * Ejecutar: php database/migrations/payphone_add_cajita.php
 */

define('MVC_ROOT',   dirname(__DIR__, 2));
define('MVC_CONFIG', MVC_ROOT . '/config');

$config = require MVC_CONFIG . '/database.php';
$dsn    = sprintf('pgsql:host=%s;port=%d;dbname=%s', $config['host'], $config['port'], $config['name']);
$pdo    = new PDO($dsn, $config['user'], $config['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$pdo->exec("
    ALTER TABLE payphone_transacciones
        ADD COLUMN IF NOT EXISTS tipo_flujo VARCHAR(20) NOT NULL DEFAULT 'boton';

    COMMENT ON COLUMN payphone_transacciones.tipo_flujo IS
        'boton = botón de pago por redirección | cajita = widget embebido (Cajita de Pagos)';
");

echo "✓ Columna tipo_flujo agregada a payphone_transacciones.\n";
