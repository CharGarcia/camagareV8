<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$baseDir = dirname(__DIR__, 3); // raíz del proyecto (sistema)
include($baseDir . '/legacy/conexiones/conectalogin.php');
require_once($baseDir . '/bootstrap.php');
use App\services\EmailConfigService;
$conn = conenta_login();
$conn->set_charset("utf8");

date_default_timezone_set('America/Guayaquil');
// Configuración desde tabla correos_config (codigo: notificaciones)
$cfgCorreo = EmailConfigService::getPhpMailerConfig('notificaciones');

// Consulta SQL para obtener obligaciones pendientes agrupadas por usuario
$sql = "SELECT tar.*, concat('Razón social: ', emp.nombre,' - Nombre comercial: ', emp.nombre_comercial) as empresa_nombre, 
        concat(obli.descripcion,' ', tar.detalle) as obligacion_descripcion, 
        usu.mail as usuario_email, usu.nombre as usuario
    FROM tareas_por_hacer as tar
    INNER JOIN obligaciones_empresas as obli ON obli.id = tar.id_obligacion
    INNER JOIN empresas as emp ON emp.id = tar.id_empresa
    INNER JOIN usuarios_tareas as usu_tar ON usu_tar.id_tarea = tar.id
    INNER JOIN usuarios as usu ON usu.id = usu_tar.id_usuario
    WHERE tar.status = 1 AND tar.fecha_a_realizar <= CURDATE() ";

$result = $conn->query($sql);

// Agrupar las obligaciones por usuario
$usuarios_obligaciones = [];

while ($row = $result->fetch_assoc()) {
    $email = $row['usuario_email'];
    if (!isset($usuarios_obligaciones[$email])) {
        $usuarios_obligaciones[$email] = [
            'nombre' => $row['usuario'],
            'obligaciones' => []
        ];
    }
    $usuarios_obligaciones[$email]['obligaciones'][] = [
        'empresa' => $row['empresa_nombre'],
        'obligacion' => $row['obligacion_descripcion'],
        'fecha_vencimiento' => $row['fecha_a_realizar']
    ];
}

// Enviar un solo correo por usuario (solo si hay config en correos_config)
if ($cfgCorreo) {
    foreach ($usuarios_obligaciones as $email => $data) {
        envia_recordatorio_por_correo($cfgCorreo, $email, $data['nombre'], $data['obligaciones']);
    }
}

echo "Correos enviados.";

function envia_recordatorio_por_correo(array $cfg, $to, $usuario, $obligaciones)
{
    $docMailDir = __DIR__;
    require_once($docMailDir . "/phpmailer.php");
    require_once($docMailDir . "/smtp.php");
    require_once($docMailDir . "/exception.php");

    $phpmailer = new \PHPMailer\PHPMailer\PHPMailer();
    $phpmailer->SMTPDebug  = 0;
    $phpmailer->isSMTP();
    $phpmailer->SMTPAuth   = true;
    $phpmailer->Host       = $cfg['host'];
    $phpmailer->Username   = $cfg['username'];
    $phpmailer->Password   = $cfg['password'];
    $phpmailer->Port       = $cfg['port'];
    $phpmailer->SMTPSecure = $cfg['smtpSecure'];
    $phpmailer->CharSet    = 'UTF-8'; // IMPORTANTE

    $phpmailer->setFrom($cfg['from'], $cfg['fromName'] ?: "CaMaGaRe");
    $phpmailer->addAddress($to);
    $phpmailer->Subject = "Recordatorio de tareas pendientes";
    $phpmailer->isHTML(true);

    // Si tienes mbstring, úsalo para mayúsculas con tildes
    if (function_exists('mb_strtoupper')) {
        $usuario_mayus = mb_strtoupper($usuario, 'UTF-8');
    } else {
        $usuario_mayus = strtoupper($usuario);
    }

    $mensaje  = "<p>Estimado(a) <strong>" . $usuario_mayus . "</strong>,</p>";
    $mensaje .= "<p>Tienes las siguientes tareas pendientes:</p><ul>";

    foreach ($obligaciones as $obligacion) {
        // Limpiamos por seguridad, pero respetando UTF-8
        $empresa    = htmlspecialchars($obligacion['empresa'], ENT_QUOTES, 'UTF-8');
        $texto_obli = htmlspecialchars($obligacion['obligacion'], ENT_QUOTES, 'UTF-8');
        $fecha_venc = date("d-m-Y", strtotime($obligacion['fecha_vencimiento']));

        $mensaje .= "<li>";
        $mensaje .= $empresa . "<br>";
        $mensaje .= "<strong>Tarea por realizar:</strong> " . $texto_obli . "<br>";
        $mensaje .= "<strong>Fecha de vencimiento:</strong> " . $fecha_venc;
        $mensaje .= "</li><br>";
    }

    $mensaje .= "</ul><p>Por favor, no te olvides de cumplir con estas tareas.</p>";
    $mensaje .= "<p>Atentamente,<br>CaMaGaRe</p>";

    $phpmailer->Body = $mensaje;

    $phpmailer->send();
}
