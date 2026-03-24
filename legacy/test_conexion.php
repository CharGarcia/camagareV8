<?php
/**
 * Script de diagnóstico: verifica conexión a BD y tablas necesarias para el login
 * Acceder: http://localhost/sistema/test_conexion.php
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

echo "<h2>Diagnóstico de conexión</h2>";

// 1. Cargar config desde parametros.xml
$paramFile = __DIR__ . '/config/parametros.xml';
if (!file_exists($paramFile)) {
    die("<p style='color:red'>❌ No existe config/parametros.xml</p>");
}
$xml = simplexml_load_file($paramFile);
$config = [
    'host' => (string) $xml->host_db,
    'user' => (string) $xml->user_db,
    'pass' => (string) $xml->pass_db,
    'name' => (string) $xml->db_name,
];
echo "<p>✓ Config cargada: host={$config['host']}, user={$config['user']}, db={$config['name']}</p>";

// 2. Probar conexión
try {
    $con = new mysqli($config['host'], $config['user'], $config['pass'], $config['name']);
    if ($con->connect_error) {
        die("<p style='color:red'>❌ Error de conexión: " . $con->connect_error . "</p>");
    }
    $con->set_charset('utf8');
    echo "<p>✓ Conexión exitosa</p>";
} catch (Exception $e) {
    die("<p style='color:red'>❌ " . $e->getMessage() . "</p>");
}

// 3. Verificar tablas
$tablas = ['usuarios', 'empresa_asignada', 'empresas'];
foreach ($tablas as $t) {
    $r = $con->query("SHOW TABLES LIKE '$t'");
    if ($r && $r->num_rows > 0) {
        echo "<p>✓ Tabla <strong>$t</strong> existe</p>";
    } else {
        echo "<p style='color:orange'>⚠ Tabla <strong>$t</strong> no existe</p>";
    }
}

// 4. Contar usuarios
$r = $con->query("SELECT COUNT(*) as n FROM usuarios WHERE estado='1'");
$row = $r ? $r->fetch_assoc() : null;
$num = $row['n'] ?? 0;
echo "<p>Usuarios activos (estado=1): <strong>$num</strong></p>";

if ($num > 0) {
    $r = $con->query("SELECT id, nombre, cedula, estado FROM usuarios WHERE estado='1' LIMIT 5");
    echo "<p>Ejemplo de usuarios:</p><ul>";
    while ($u = $r->fetch_assoc()) {
        echo "<li>ID={$u['id']} | {$u['nombre']} | cédula={$u['cedula']}</li>";
    }
    echo "</ul>";
}

// 5. Verificar empresa_asignada
$r = $con->query("SELECT COUNT(*) as n FROM empresa_asignada");
$row = $r ? $r->fetch_assoc() : null;
$empAsig = $row['n'] ?? 0;
echo "<p>Registros en empresa_asignada: <strong>$empAsig</strong></p>";

$con->close();
echo "<hr><p><small>Si todo está ✓, el problema puede ser: cédula/contraseña incorrectos, o la contraseña en BD debe estar en MD5.</small></p>";
