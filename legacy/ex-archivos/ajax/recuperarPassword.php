<?php
/**
 * @deprecated Use AuthController::solicitarRecuperar y AuthController::enviarCorreoRecuperar
 * Rutas: /auth/solicitar-recuperar, /auth/enviar-correo-recuperar
 */
include(__DIR__ . "/../../conexiones/conectalogin.php");
require_once(__DIR__ . "/../helpers/helpers.php");
require_once(__DIR__ . "/../../../bootstrap.php"); // Autoloader para EmailConfigService
use App\services\EmailConfigService;
$con = conenta_login();
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

if ($action == 'recuperar_password') {
    $correo_receptor = strtolower($_POST['correo']);
    // comprobar si el usuario existe y si esta activo.
    $sql = mysqli_query($con, "SELECT * FROM usuarios WHERE mail = '" . $correo_receptor . "' and estado = '1'");
    $row_sql = mysqli_fetch_array($sql);
    $id_user = isset($row_sql['id']) ? $row_sql['id'] : 0;
    $nombre = $row_sql['nombre'];
    //actualizar el usuario y agergar un token
    if ($id_user > 0) {
        echo json_encode(['status' => 'success', 'id_user' => $id_user, 'nombre' => $nombre, 'message' => "Se ha enviado un correo a $correo_receptor para restablecer su cuenta."]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'El usuario no esta registrado en el sistema.']);
    }
}


if ($action == 'enviar_correo_recuperar_clave') {
    header('Content-Type: application/json; charset=utf-8');
    $correo_receptor = $_GET['correo'] ?? '';
    $nombre = $_GET['nombre'] ?? '';
    $id_user = (int) ($_GET['id_user'] ?? 0);

    if (!$correo_receptor || !$id_user) {
        echo json_encode(['ok' => false, 'error' => 'Faltan parámetros']);
        exit;
    }

    $token = token();
    $sql_update = mysqli_query($con, "UPDATE usuarios SET token ='" . mysqli_real_escape_string($con, $token) . "' WHERE id='" . $id_user . "'");
    $host = $_SERVER['HTTP_HOST'] ?? 'admin.camagare.com';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $url_recovery = $scheme . '://' . $host . '/sistema/public/auth/confirmUser/' . urlencode($correo_receptor) . '/' . $token;

    // Solo se usa configuración desde la tabla correos_config (codigo: recuperar_password)
    $baseCorreo = EmailConfigService::getDataForSendEmail('recuperar_password');
    if (!$baseCorreo) {
        logEmailError('recuperar_password', 'No hay configuración en correos_config con codigo recuperar_password');
        echo json_encode(['ok' => false, 'error' => 'No hay configuración de correo. Cree una en Config → Correos con código recuperar_password.']);
        exit;
    }

    $data = array_merge($baseCorreo, [
        'nombre' => $nombre,
        'template' => 'email_cambioPassword',
        'asunto' => "Recuperar cuenta CaMaGaRe",
        'receptor' => $correo_receptor,
        'url_recovery' => $url_recovery,
    ]);

    $ok = sendEmail($data);
    if ($ok) {
        $query_insert = mysqli_query($con, "INSERT INTO recuperaciones_clave_usuario(id_user, nombre, mail) VALUES('" . $id_user . "','" . mysqli_real_escape_string($con, $nombre) . "', '" . mysqli_real_escape_string($con, $correo_receptor) . "')");
        echo json_encode(['ok' => true]);
    } else {
        $err = $GLOBALS['LAST_EMAIL_ERROR'] ?? 'Error desconocido al enviar';
        logEmailError('recuperar_password', $err);
        echo json_encode(['ok' => false, 'error' => $err]);
    }
    exit;
}

function logEmailError(string $contexto, string $mensaje): void {
    $dir = dirname(__DIR__, 3) . '/storage/logs';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $file = $dir . '/email_errors.log';
    @file_put_contents($file, date('Y-m-d H:i:s') . " [{$contexto}] {$mensaje}\n", FILE_APPEND);
}
