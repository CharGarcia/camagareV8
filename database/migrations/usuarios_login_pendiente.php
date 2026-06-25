<?php
/**
 * Migración: agrega columna login_pendiente_empresa a usuarios.
 * Marca momentánea: cuando el usuario pulsa "Generar descarga del SRI", el sistema guarda
 * aquí la empresa activa; la extensión la lee (y limpia) para loguearse en el SRI con las
 * credenciales de esa empresa.
 * Ejecutar:  php database/migrations/usuarios_login_pendiente.php
 */

define('MVC_ROOT',   dirname(__DIR__, 2));
define('MVC_CONFIG', MVC_ROOT . '/config');

$config = require MVC_CONFIG . '/database.php';
$dsn    = sprintf('pgsql:host=%s;port=%d;dbname=%s', $config['host'], $config['port'], $config['name']);
$pdo    = new PDO($dsn, $config['user'], $config['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$pdo->exec("ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS login_pendiente_empresa INTEGER;");

echo "✓ Columna login_pendiente_empresa agregada a usuarios.\n";
