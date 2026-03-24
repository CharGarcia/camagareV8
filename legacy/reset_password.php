<?php
/**
 * Restablece la contraseña de un usuario a "1234" (MD5).
 * USO: http://localhost/sistema/reset_password.php?cedula=1234567890
 * ELIMINAR después de usar por seguridad.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$cedula = isset($_GET['cedula']) ? trim($_GET['cedula']) : '';

if (empty($cedula)) {
    die('Uso: reset_password.php?cedula=TU_CEDULA');
}

require_once __DIR__ . '/app/bootstrap.php';
$con = getConnection();

$cedula = mysqli_real_escape_string($con, $cedula);
$nuevaClave = md5('1234');

$r = mysqli_query($con, "UPDATE usuarios SET password = '$nuevaClave' WHERE cedula = '$cedula' AND estado = '1'");

if (mysqli_affected_rows($con) > 0) {
    echo "OK: Contraseña restablecida a <strong>1234</strong> para cédula $cedula. Ya puedes iniciar sesión.";
} else {
    echo "No se encontró usuario con cédula $cedula y estado=1. Verifica la cédula.";
}
