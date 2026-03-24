<?php
/**
 * Diagnóstico de login - BORRAR después de usar
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== DEBUG LOGIN ===\n\n";

// 1. Conexión BD
try {
    $config = require MVC_CONFIG . '/database.php';
    $c = new mysqli($config['host'], $config['user'], $config['pass'], $config['name']);
    echo "1. BD: " . ($c->connect_error ? "ERROR: " . $c->connect_error : "OK") . "\n";
    if (!$c->connect_error) {
        $r = $c->query("SELECT id, cedula, nombre FROM usuarios WHERE nivel > 0 LIMIT 3");
        echo "   Usuarios: ";
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                echo $row['cedula'] . " ";
            }
        }
        echo "\n";
    }
} catch (Throwable $e) {
    echo "1. BD: ERROR " . $e->getMessage() . "\n";
}

// 2. Sesión
echo "2. Sesión: ";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo "ID=" . session_id() . " | path=" . session_save_path() . "\n";
echo "   writable: " . (is_writable(session_save_path()) ? "SI" : "NO") . "\n";

// 3. BASE_URL
echo "3. BASE_URL: " . (defined('BASE_URL') ? "'" . BASE_URL . "'" : "no definido") . "\n";

// 4. POST simulando login (si hay cedula en GET)
$cedula = $_GET['cedula'] ?? '';
if ($cedula !== '') {
    require_once MVC_APP . '/models/Usuario.php';
    $model = new \App\models\Usuario();
    $user = $model->validaLogin($cedula, $_GET['pass'] ?? '');
    echo "4. validaLogin('$cedula'): " . ($user ? "OK (id=" . $user['id'] . ")" : "FALLO") . "\n";
} else {
    echo "4. Para probar login: ?cedula=123&pass=xxx\n";
}

echo "\nBORRAR ESTE ARCHIVO: rm public/debug_login.php\n";
