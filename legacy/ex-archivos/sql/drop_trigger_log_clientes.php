<?php
/**
 * Script de mantenimiento: elimina triggers en la tabla clientes que insertan en log_clientes.
 * Ejecutar UNA VEZ para evitar registros duplicados.
 *
 * Uso: php drop_trigger_log_clientes.php
 *   o visitar: /sistema/sql/drop_trigger_log_clientes.php (requiere sesión)
 */
$run_from_cli = (php_sapi_name() === 'cli');
if (!$run_from_cli) {
    require_once dirname(__DIR__) . '/app/bootstrap.php';
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['ruc_empresa'] ?? '')) {
        die('Debe iniciar sesión y seleccionar empresa.');
    }
}

$config = require dirname(__DIR__) . '/app/Config/database.php';
$con = @mysqli_connect($config['host'], $config['user'], $config['pass'], $config['name']);
if (!$con) {
    die("Error: No se pudo conectar a la base de datos.\n");
}

$db_name = mysqli_real_escape_string($con, $config['name']);
$r = mysqli_query($con, "SELECT TRIGGER_NAME FROM information_schema.TRIGGERS 
    WHERE TRIGGER_SCHEMA = '$db_name' AND EVENT_OBJECT_TABLE = 'clientes'");

$dropped = [];
while ($row = mysqli_fetch_assoc($r)) {
    $name = $row['TRIGGER_NAME'];
    if (mysqli_query($con, "DROP TRIGGER IF EXISTS `$db_name`.`$name`")) {
        $dropped[] = $name;
    }
}

if (empty($dropped)) {
    $msg = "No se encontraron triggers en la tabla clientes. El log se registra correctamente desde PHP.";
} else {
    $msg = "Triggers eliminados: " . implode(', ', $dropped) . ". Ahora solo se crea 1 registro en log_clientes (después del UPDATE).";
}

if ($run_from_cli) {
    echo $msg . "\n";
} else {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Mantenimiento</title></head><body>';
    echo '<p>' . htmlspecialchars($msg) . '</p>';
    echo '<p><a href="../cliente">Volver a Clientes</a></p></body></html>';
}
