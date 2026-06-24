<?php
/**
 * Migración: agrega columna agente_token a sri_config_descarga_auto.
 * Necesaria para el agente de escritorio SRI (descarga desde la PC del operador).
 * Ejecutar:  php database/migrations/sri_config_agente_token.php
 */

define('MVC_ROOT',   dirname(__DIR__, 2));
define('MVC_CONFIG', MVC_ROOT . '/config');

$config = require MVC_CONFIG . '/database.php';
$dsn    = sprintf('pgsql:host=%s;port=%d;dbname=%s', $config['host'], $config['port'], $config['name']);
$pdo    = new PDO($dsn, $config['user'], $config['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$pdo->exec("
    ALTER TABLE sri_config_descarga_auto
        ADD COLUMN IF NOT EXISTS agente_token VARCHAR(64);

    COMMENT ON COLUMN sri_config_descarga_auto.agente_token IS
        'Token (64 hex) para autenticar el agente de escritorio SRI de esta empresa. Regenerable.';
");

// Índice parcial (en sentencia aparte: la columna ya existe arriba)
$pdo->exec("
    CREATE INDEX IF NOT EXISTS idx_sri_config_agente_token
        ON sri_config_descarga_auto(agente_token)
        WHERE agente_token IS NOT NULL AND eliminado = FALSE;
");

echo "✓ Columna agente_token agregada a sri_config_descarga_auto.\n";
