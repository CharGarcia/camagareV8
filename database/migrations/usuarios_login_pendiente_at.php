<?php
/**
 * Migración: agrega columna login_pendiente_at (timestamp) a usuarios.
 * Da CADUCIDAD a la marca de login pendiente: la extensión solo debe loguear en el SRI dentro de
 * una ventana corta tras pulsar "Generar descarga del SRI" (por seguridad; evita que la extensión
 * inicie sesión sola cada vez que se abre el portal del SRI). Ver Usuario::getLoginPendiente().
 * Ejecutar:  php database/migrations/usuarios_login_pendiente_at.php
 */

define('MVC_ROOT',   dirname(__DIR__, 2));
define('MVC_CONFIG', MVC_ROOT . '/config');

$config = require MVC_CONFIG . '/database.php';
$dsn    = sprintf('pgsql:host=%s;port=%d;dbname=%s', $config['host'], $config['port'], $config['name']);
$pdo    = new PDO($dsn, $config['user'], $config['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$pdo->exec("ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS login_pendiente_at TIMESTAMP;");

echo "✓ Columna login_pendiente_at agregada a usuarios.\n";
