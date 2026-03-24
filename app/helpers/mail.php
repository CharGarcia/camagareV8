<?php
/**
 * Helper para envío de correos usando correos_config
 */

if (!function_exists('enviar_correo_recuperar_clave')) {
    /**
     * Envía el correo de recuperación de contraseña.
     * Usa la configuración de correos_config (codigo: recuperar_password).
     *
     * @param string $nombre Nombre del usuario
     * @param string $correoDestino Correo del destinatario
     * @param string $urlRecovery URL del enlace para restablecer
     * @return bool true si se envió correctamente
     */
    function enviar_correo_recuperar_clave(string $nombre, string $correoDestino, string $urlRecovery): bool
    {
        $base = \App\services\EmailConfigService::getDataForSendEmail('recuperar_password');
        if (!$base) {
            $GLOBALS['LAST_EMAIL_ERROR'] = 'No hay configuración en correos_config (codigo: recuperar_password)';
            return false;
        }

        $docMailDir = MVC_ROOT . '/legacy/ex-archivos/documentos_mail';
        if (!file_exists($docMailDir . '/phpmailer.php')) {
            $GLOBALS['LAST_EMAIL_ERROR'] = 'No se encuentra PHPMailer';
            return false;
        }

        require_once $docMailDir . '/phpmailer.php';
        require_once $docMailDir . '/smtp.php';
        require_once $docMailDir . '/exception.php';

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $GLOBALS['LAST_EMAIL_ERROR'] = null;

        try {
            $mail->isSMTP();
            $mail->Host = $base['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $base['emisor'];
            $mail->Password = $base['pass'];
            $mail->SMTPSecure = $base['smtp_secure'] ?? 'tls';
            $mail->Port = $base['port'];
            $mail->CharSet = 'UTF-8';

            // Opciones SSL para localhost (XAMPP): evita fallos de verificación de certificado
            $config = require MVC_CONFIG . '/app.php';
            if (!empty($config['mail_smtp_options'])) {
                $mail->SMTPOptions = $config['mail_smtp_options'];
            }

            $mail->setFrom($base['emisor'], $base['empresa']);
            $mail->addAddress($correoDestino);
            $mail->Subject = 'Recuperar cuenta CaMaGaRe';

            $data = [
                'nombre' => $nombre,
                'receptor' => $correoDestino,
                'url_recovery' => $urlRecovery,
            ];
            ob_start();
            require $docMailDir . '/email_cambioPassword.php';
            $mail->Body = ob_get_clean();
            $mail->isHTML(true);

            $ok = $mail->send();
            return $ok;
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            $GLOBALS['LAST_EMAIL_ERROR'] = $mail->ErrorInfo ?? $e->getMessage();
            error_log('Mailer Error: ' . ($GLOBALS['LAST_EMAIL_ERROR']));
            return false;
        }
    }
}
