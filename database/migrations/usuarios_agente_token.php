<?php
/**
 * Migración: agrega columna agente_token a la tabla usuarios.
 * El token del agente/extensión pasa a ser POR USUARIO (un solo token sirve para
 * todas las empresas que el usuario maneja; el servidor identifica la empresa por
 * el RUC del comprobante).
 * Ejecutar:  php database/migrations/usuarios_agente_token.php
 */

define('MVC_ROOT',   dirname(__DIR__, 2));
define('MVC_CONFIG', MVC_ROOT . '/config');

$config = require MVC_CONFIG . '/database.php';
$dsn    = sprintf('pgsql:host=%s;port=%d;dbname=%s', $config['host'], $config['port'], $config['name']);
$pdo    = new PDO($dsn, $config['user'], $config['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$pdo->exec("ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS agente_token VARCHAR(64);");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_usuarios_agente_token ON usuarios(agente_token) WHERE agente_token IS NOT NULL;");

echo "✓ Columna agente_token agregada a usuarios.\n";
