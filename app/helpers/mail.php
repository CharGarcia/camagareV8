<?php
/**
 * Helper para envío de correos usando correos_config
 */

if (!function_exists('_mail_resolve_ipv4_host')) {
    /**
     * Resuelve un hostname forzando IPv4 para evitar timeouts con IPv6.
     * En servidores con IPv6 habilitado pero sin ruta IPv6 válida (ej: DigitalOcean),
     * PHPMailer intenta IPv6 primero y falla. Esto devuelve solo IPs IPv4.
     */
    function _mail_resolve_ipv4_host(string $host): string
    {
        $resolved = @gethostbynamel($host);
        if (!$resolved) {
            return $host;
        }
        $ipv4 = array_filter($resolved, fn($ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4));
        if (!empty($ipv4)) {
            return implode(';', array_values($ipv4));
        }
        return $host;
    }
}

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

        $docMailDir = MVC_APP . '/lib/mail';
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
            $mail->Host = _mail_resolve_ipv4_host($base['host']);
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

    /**
     * Notifica a los aprobadores que hay una carga de inventario pendiente.
     * Usa la configuración del sistema (correos_config: recuperar_password).
     *
     * @param string[] $destinatarios Correos de los aprobadores.
     * @param array    $data          numero, tipo, fecha, total_lineas, empresa, creador, url.
     */
    function notificar_carga_inventario_pendiente(array $destinatarios, array $data): bool
    {
        $destinatarios = array_values(array_filter(array_map('trim', $destinatarios), static fn($c) => $c !== '' && filter_var($c, FILTER_VALIDATE_EMAIL)));
        if (empty($destinatarios)) {
            $GLOBALS['LAST_EMAIL_ERROR'] = 'Sin destinatarios válidos';
            return false;
        }

        $base = \App\services\EmailConfigService::getDataForSendEmail('recuperar_password');
        if (!$base) {
            $GLOBALS['LAST_EMAIL_ERROR'] = 'No hay configuración en correos_config (codigo: recuperar_password)';
            return false;
        }

        $docMailDir = MVC_APP . '/lib/mail';
        require_once $docMailDir . '/phpmailer.php';
        require_once $docMailDir . '/smtp.php';
        require_once $docMailDir . '/exception.php';

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $GLOBALS['LAST_EMAIL_ERROR'] = null;

        try {
            $mail->isSMTP();
            $mail->Host = _mail_resolve_ipv4_host($base['host']);
            $mail->SMTPAuth = true;
            $mail->Username = $base['emisor'];
            $mail->Password = $base['pass'];
            $mail->SMTPSecure = $base['smtp_secure'] ?? 'tls';
            $mail->Port = $base['port'];
            $mail->CharSet = 'UTF-8';

            $config = require MVC_CONFIG . '/app.php';
            if (!empty($config['mail_smtp_options'])) {
                $mail->SMTPOptions = $config['mail_smtp_options'];
            }

            $mail->setFrom($base['emisor'], $base['empresa']);
            foreach ($destinatarios as $c) { $mail->addAddress($c); }
            $mail->Subject = 'Carga de inventario pendiente de aprobación #' . ($data['numero'] ?? '');

            $e = static fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
            $url = $data['url'] ?? '';
            $mail->Body = '
                <div style="font-family:Arial,sans-serif;color:#333;max-width:600px;margin:auto;">
                    <h2 style="color:#2563eb;">Carga de inventario pendiente</h2>
                    <p>Hay una carga de inventario que requiere su aprobación en <strong>' . $e($data['empresa']) . '</strong>.</p>
                    <table style="border-collapse:collapse;font-size:14px;">
                        <tr><td style="padding:4px 12px;color:#666;">N°</td><td style="padding:4px 12px;"><strong>#' . $e($data['numero']) . '</strong></td></tr>
                        <tr><td style="padding:4px 12px;color:#666;">Tipo</td><td style="padding:4px 12px;">' . $e(ucfirst((string) ($data['tipo'] ?? ''))) . '</td></tr>
                        <tr><td style="padding:4px 12px;color:#666;">Fecha</td><td style="padding:4px 12px;">' . $e($data['fecha']) . '</td></tr>
                        <tr><td style="padding:4px 12px;color:#666;">Líneas</td><td style="padding:4px 12px;">' . $e($data['total_lineas']) . '</td></tr>
                        <tr><td style="padding:4px 12px;color:#666;">Registrada por</td><td style="padding:4px 12px;">' . $e($data['creador']) . '</td></tr>
                    </table>
                    ' . ($url ? '<p style="margin-top:20px;"><a href="' . $e($url) . '" style="background:#2563eb;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;">Revisar y aprobar</a></p>' : '') . '
                    <p style="color:#888;font-size:12px;margin-top:24px;">El inventario se afecta únicamente cuando la carga es aprobada.</p>
                </div>';
            $mail->isHTML(true);

            return $mail->send();
        } catch (\PHPMailer\PHPMailer\Exception $ex) {
            $GLOBALS['LAST_EMAIL_ERROR'] = $mail->ErrorInfo ?? $ex->getMessage();
            error_log('Mailer Error (carga inventario): ' . ($GLOBALS['LAST_EMAIL_ERROR']));
            return false;
        }
    }

    /**
     * Notifica a los aprobadores que hay una importación pendiente de aprobación.
     * Usa la configuración del sistema (correos_config: recuperar_password).
     *
     * @param string[] $destinatarios Correos de los aprobadores.
     * @param array    $data          numero_importacion, proveedor, costo_total_nacionalizado, empresa, creador, url.
     */
    function notificar_importacion_pendiente(array $destinatarios, array $data): bool
    {
        $destinatarios = array_values(array_filter(array_map('trim', $destinatarios), static fn($c) => $c !== '' && filter_var($c, FILTER_VALIDATE_EMAIL)));
        if (empty($destinatarios)) {
            $GLOBALS['LAST_EMAIL_ERROR'] = 'Sin destinatarios válidos';
            return false;
        }

        $base = \App\services\EmailConfigService::getDataForSendEmail('recuperar_password');
        if (!$base) {
            $GLOBALS['LAST_EMAIL_ERROR'] = 'No hay configuración en correos_config (codigo: recuperar_password)';
            return false;
        }

        $docMailDir = MVC_APP . '/lib/mail';
        require_once $docMailDir . '/phpmailer.php';
        require_once $docMailDir . '/smtp.php';
        require_once $docMailDir . '/exception.php';

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $GLOBALS['LAST_EMAIL_ERROR'] = null;

        try {
            $mail->isSMTP();
            $mail->Host = _mail_resolve_ipv4_host($base['host']);
            $mail->SMTPAuth = true;
            $mail->Username = $base['emisor'];
            $mail->Password = $base['pass'];
            $mail->SMTPSecure = $base['smtp_secure'] ?? 'tls';
            $mail->Port = $base['port'];
            $mail->CharSet = 'UTF-8';

            $config = require MVC_CONFIG . '/app.php';
            if (!empty($config['mail_smtp_options'])) {
                $mail->SMTPOptions = $config['mail_smtp_options'];
            }

            $mail->setFrom($base['emisor'], $base['empresa']);
            foreach ($destinatarios as $c) { $mail->addAddress($c); }
            $mail->Subject = 'Importación pendiente de aprobación #' . ($data['numero_importacion'] ?? '');

            $e = static fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
            $url = $data['url'] ?? '';
            $mail->Body = '
                <div style="font-family:Arial,sans-serif;color:#333;max-width:600px;margin:auto;">
                    <h2 style="color:#2563eb;">Importación pendiente de nacionalización</h2>
                    <p>Hay una importación que requiere su aprobación en <strong>' . $e($data['empresa']) . '</strong>.</p>
                    <table style="border-collapse:collapse;font-size:14px;">
                        <tr><td style="padding:4px 12px;color:#666;">N°</td><td style="padding:4px 12px;"><strong>' . $e($data['numero_importacion']) . '</strong></td></tr>
                        <tr><td style="padding:4px 12px;color:#666;">Proveedor</td><td style="padding:4px 12px;">' . $e($data['proveedor']) . '</td></tr>
                        <tr><td style="padding:4px 12px;color:#666;">Costo nacionalizado</td><td style="padding:4px 12px;">$ ' . number_format((float) ($data['costo_total_nacionalizado'] ?? 0), 2) . '</td></tr>
                        <tr><td style="padding:4px 12px;color:#666;">Solicitada por</td><td style="padding:4px 12px;">' . $e($data['creador']) . '</td></tr>
                    </table>
                    ' . ($url ? '<p style="margin-top:20px;"><a href="' . $e($url) . '" style="background:#2563eb;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;">Revisar y aprobar</a></p>' : '') . '
                    <p style="color:#888;font-size:12px;margin-top:24px;">El inventario y el asiento contable se generan únicamente cuando la importación es aprobada.</p>
                </div>';
            $mail->isHTML(true);

            return $mail->send();
        } catch (\PHPMailer\PHPMailer\Exception $ex) {
            $GLOBALS['LAST_EMAIL_ERROR'] = $mail->ErrorInfo ?? $ex->getMessage();
            error_log('Mailer Error (importaciones): ' . ($GLOBALS['LAST_EMAIL_ERROR']));
            return false;
        }
    }

    /**
     * Envía el correo de invitación para nuevo usuario.
     * Usa la configuración de correos_config (codigo: nuevo_usuario).
     *
     * @param string $nombre Nombre del usuario
     * @param string $correoDestino Correo del destinatario
     * @param string $urlInvite URL del enlace para completar registro
     * @return bool true si se envió correctamente
     */
    function enviar_correo_nuevo_usuario(string $nombre, string $correoDestino, string $urlInvite): bool
    {
        $base = \App\services\EmailConfigService::getDataForSendEmail('nuevo_usuario');
        if (!$base) {
            $GLOBALS['LAST_EMAIL_ERROR'] = 'No hay configuración en correos_config (codigo: nuevo_usuario)';
            return false;
        }

        $docMailDir = MVC_APP . '/lib/mail';
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
            $mail->Host = _mail_resolve_ipv4_host($base['host']);
            $mail->SMTPAuth = true;
            $mail->Username = $base['emisor'];
            $mail->Password = $base['pass'];
            $mail->SMTPSecure = $base['smtp_secure'] ?? 'tls';
            $mail->Port = $base['port'];
            $mail->CharSet = 'UTF-8';

            // Opciones SSL para localhost (XAMPP)
            $config = require MVC_CONFIG . '/app.php';
            if (!empty($config['mail_smtp_options'])) {
                $mail->SMTPOptions = $config['mail_smtp_options'];
            }

            $mail->setFrom($base['emisor'], $base['empresa']);
            $mail->addAddress($correoDestino);
            $mail->Subject = 'Invitación al Sistema CaMaGaRe';

            $data = [
                'nombre' => $nombre,
                'receptor' => $correoDestino,
                'url_invite' => $urlInvite,
            ];
            ob_start();
            require $docMailDir . '/email_nuevoUsuario.php';
            $mail->Body = ob_get_clean();
            $mail->isHTML(true);

            return $mail->send();
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            $GLOBALS['LAST_EMAIL_ERROR'] = $mail->ErrorInfo ?? $e->getMessage();
            error_log('Mailer Error (Invitación): ' . ($GLOBALS['LAST_EMAIL_ERROR']));
            return false;
        }
    }
}

if (!function_exists('enviar_correo_notificacion_tarea')) {
    /**
     * Envía notificaciones a los clientes sobre cambios de estado en sus tareas.
     */
    function enviar_correo_notificacion_tarea(array $destinatarios, array $data, array $adjuntosPaths = []): bool
    {
        $base = \App\services\EmailConfigService::getDataForSendEmail('notificaciones');
        if (!$base) {
            $GLOBALS['LAST_EMAIL_ERROR'] = 'No hay configuración en correos_config para "notificaciones"';
            return false;
        }

        $docMailDir = MVC_APP . '/lib/mail';
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
            $mail->Host = _mail_resolve_ipv4_host($base['host']);
            $mail->SMTPAuth = true;
            $mail->Username = $base['emisor'];
            $mail->Password = $base['pass'];
            $mail->SMTPSecure = $base['smtp_secure'] ?? 'tls';
            $mail->Port = $base['port'];
            $mail->CharSet = 'UTF-8';

            $config = require MVC_CONFIG . '/app.php';
            if (!empty($config['mail_smtp_options'])) {
                $mail->SMTPOptions = $config['mail_smtp_options'];
            }

            $mail->setFrom($base['emisor'], $base['empresa']);
            
            // Añadir todos los destinatarios recibidos (separados por coma en la DB original)
            foreach ($destinatarios as $dest) {
                if (filter_var($dest, FILTER_VALIDATE_EMAIL)) {
                    $mail->addAddress($dest);
                }
            }
            if (empty($mail->getAllRecipientAddresses())) {
                $GLOBALS['LAST_EMAIL_ERROR'] = 'No hay correos destinatarios válidos.';
                return false;
            }

            // Asignar Asunto según el estado
            if ($data['estado'] === 'cancelada') {
                $mail->Subject = 'Aviso de Cancelación de Tarea: ' . $data['obligacion_nombre'];
            } else {
                $mail->Subject = 'Confirmación de Tarea Realizada: ' . $data['obligacion_nombre'];
            }

            ob_start();
            require MVC_APP . '/views/emails/notificacion_tarea.php';
            $mail->Body = ob_get_clean();
            $mail->isHTML(true);

            // Archivos adjuntos
            foreach ($adjuntosPaths as $filePath) {
                if (file_exists($filePath)) {
                    // Obtiene el nombre original del archivo para mostrarlo
                    $mail->addAttachment($filePath, basename($filePath));
                }
            }

            return $mail->send();
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            $GLOBALS['LAST_EMAIL_ERROR'] = $mail->ErrorInfo ?? $e->getMessage();
            error_log('Mailer Error (NotificacionTarea): ' . ($GLOBALS['LAST_EMAIL_ERROR']));
            return false;
        }
    }
}

if (!function_exists('enviar_correo_recordatorio_tareas')) {
    /**
     * Recordatorio diario a un responsable con sus tareas vencidas / por vencer.
     * Usa la configuración correos_config con código "notificaciones".
     *
     * @param string $correo Correo del responsable
     * @param string $nombre Nombre del responsable
     * @param array  $tareas [ ['obligacion','cliente_nombre','fecha_tarea','estado'], ... ]
     * @return bool
     */
    function enviar_correo_recordatorio_tareas(string $correo, string $nombre, array $tareas): bool
    {
        if (empty($tareas) || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $GLOBALS['LAST_EMAIL_ERROR'] = 'Sin tareas o correo inválido.';
            return false;
        }

        $base = \App\services\EmailConfigService::getDataForSendEmail('notificaciones');
        if (!$base) {
            $GLOBALS['LAST_EMAIL_ERROR'] = 'No hay configuración en correos_config para "notificaciones"';
            return false;
        }

        $docMailDir = MVC_APP . '/lib/mail';
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
            $mail->Host = _mail_resolve_ipv4_host($base['host']);
            $mail->SMTPAuth = true;
            $mail->Username = $base['emisor'];
            $mail->Password = $base['pass'];
            $mail->SMTPSecure = $base['smtp_secure'] ?? 'tls';
            $mail->Port = $base['port'];
            $mail->CharSet = 'UTF-8';

            $config = require MVC_CONFIG . '/app.php';
            if (!empty($config['mail_smtp_options'])) {
                $mail->SMTPOptions = $config['mail_smtp_options'];
            }

            $mail->setFrom($base['emisor'], $base['empresa']);
            $mail->addAddress($correo);

            $n = count($tareas);
            $mail->Subject = 'Recordatorio: ' . $n . ' tarea' . ($n === 1 ? '' : 's')
                           . ' vencida' . ($n === 1 ? '' : 's') . ' o por vencer';

            ob_start();
            require MVC_APP . '/views/emails/recordatorio_tareas.php';
            $mail->Body = ob_get_clean();
            $mail->isHTML(true);

            return $mail->send();
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            $GLOBALS['LAST_EMAIL_ERROR'] = $mail->ErrorInfo ?? $e->getMessage();
            error_log('Mailer Error (RecordatorioTareas): ' . ($GLOBALS['LAST_EMAIL_ERROR']));
            return false;
        }
    }
}

if (!function_exists('enviar_correo_solicitud_firma')) {
    /**
     * Envía el correo de solicitud de formulario de firma electrónica.
     * Usa la configuración correos_config con codigo: notificaciones.
     *
     * @param string $correoDestino Correo del cliente
     * @param array  $data  { nombre_destino, empresa_nombre, url_formulario, expira }
     * @return bool
     */
    function enviar_correo_solicitud_firma(string $correoDestino, array $data): bool
    {
        $base = \App\services\EmailConfigService::getDataForSendEmail('notificaciones');
        if (!$base) {
            $GLOBALS['LAST_EMAIL_ERROR'] = 'No hay configuración en correos_config (codigo: notificaciones)';
            return false;
        }

        $docMailDir = MVC_APP . '/lib/mail';
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
            $mail->Host       = $base['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $base['emisor'];
            $mail->Password   = $base['pass'];
            $mail->SMTPSecure = $base['smtp_secure'] ?? 'tls';
            $mail->Port       = $base['port'];
            $mail->CharSet    = 'UTF-8';

            $config = require MVC_CONFIG . '/app.php';
            if (!empty($config['mail_smtp_options'])) {
                $mail->SMTPOptions = $config['mail_smtp_options'];
            }

            $mail->setFrom($base['emisor'], $base['empresa']);
            $mail->addAddress($correoDestino);
            $mail->Subject = 'Formulario de Firma Electrónica - ' . ($data['empresa_nombre'] ?? '');

            ob_start();
            require $docMailDir . '/email_solicitud_firma.php';
            $mail->Body = ob_get_clean();
            $mail->isHTML(true);

            return $mail->send();
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            $GLOBALS['LAST_EMAIL_ERROR'] = $mail->ErrorInfo ?? $e->getMessage();
            error_log('Mailer Error (SolicitudFirma): ' . ($GLOBALS['LAST_EMAIL_ERROR']));
            return false;
        }
    }
}

if (!function_exists('enviar_correo_suscripcion')) {
    /**
     * Envía notificaciones de suscripciones al cliente.
     * Usa configuración de correos_config (codigo: notificaciones).
     *
     * @param string $correoDestino Email del cliente
     * @param string $asunto        Asunto del correo
     * @param array  $data          Datos para la plantilla (cliente_nombre, plan_nombre, monto, fecha_cobro, proximo_cobro, tipo)
     * @param string $tipo          Tipo: factura_generada|cobro_exitoso|cobro_fallido|suspension|vencimiento_proximo
     * @return bool
     */
    function enviar_correo_suscripcion(string $correoDestino, string $asunto, array $data, string $tipo): bool
    {
        $base = \App\services\EmailConfigService::getDataForSendEmail('notificaciones');
        if (!$base) {
            $GLOBALS['LAST_EMAIL_ERROR'] = 'No hay configuración en correos_config (codigo: notificaciones)';
            return false;
        }

        $docMailDir = MVC_APP . '/lib/mail';
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
            $mail->Host       = $base['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $base['emisor'];
            $mail->Password   = $base['pass'];
            $mail->SMTPSecure = $base['smtp_secure'] ?? 'tls';
            $mail->Port       = $base['port'];
            $mail->CharSet    = 'UTF-8';

            $config = require MVC_CONFIG . '/app.php';
            if (!empty($config['mail_smtp_options'])) {
                $mail->SMTPOptions = $config['mail_smtp_options'];
            }

            $mail->setFrom($base['emisor'], $base['empresa']);
            $mail->addAddress($correoDestino);
            $mail->Subject = $asunto;

            ob_start();
            require $docMailDir . '/email_suscripcion.php';
            $mail->Body = ob_get_clean();
            $mail->isHTML(true);

            return $mail->send();
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            $GLOBALS['LAST_EMAIL_ERROR'] = $mail->ErrorInfo ?? $e->getMessage();
            error_log('Mailer Error (Suscripcion): ' . ($GLOBALS['LAST_EMAIL_ERROR']));
            return false;
        }
    }
}

if (!function_exists('enviar_correo_pago_tarjeta')) {
    /**
     * Envía al cliente el enlace para pagar con tarjeta (Cajita de Pagos Payphone).
     *
     * @param string $correoDestino  Email del cliente
     * @param array  $data  {
     *   cliente_nombre, empresa_nombre, monto (float en USD), descripcion, url_pago
     * }
     * @return bool
     */
    function enviar_correo_pago_tarjeta(string $correoDestino, array $data): bool
    {
        $base = \App\services\EmailConfigService::getDataForSendEmail('notificaciones');
        if (!$base) {
            $GLOBALS['LAST_EMAIL_ERROR'] = 'No hay configuración en correos_config (codigo: notificaciones)';
            return false;
        }

        $docMailDir = MVC_APP . '/lib/mail';
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
            $mail->Host       = _mail_resolve_ipv4_host($base['host']);
            $mail->SMTPAuth   = true;
            $mail->Username   = $base['emisor'];
            $mail->Password   = $base['pass'];
            $mail->SMTPSecure = $base['smtp_secure'] ?? 'tls';
            $mail->Port       = $base['port'];
            $mail->CharSet    = 'UTF-8';

            $config = require MVC_CONFIG . '/app.php';
            if (!empty($config['mail_smtp_options'])) {
                $mail->SMTPOptions = $config['mail_smtp_options'];
            }

            $mail->setFrom($base['emisor'], $base['empresa']);
            $mail->addAddress($correoDestino, $data['cliente_nombre'] ?? '');
            $mail->Subject = 'Enlace de pago con tarjeta — ' . ($data['descripcion'] ?? 'Pago pendiente');

            ob_start();
            require $docMailDir . '/email_pago_tarjeta.php';
            $mail->Body = ob_get_clean();
            $mail->isHTML(true);

            return $mail->send();
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            $GLOBALS['LAST_EMAIL_ERROR'] = $mail->ErrorInfo ?? $e->getMessage();
            error_log('Mailer Error (PagoTarjeta): ' . ($GLOBALS['LAST_EMAIL_ERROR']));
            return false;
        }
    }
}

if (!function_exists('enviar_correo_factura_express_dueno')) {
    /**
     * Notifica al dueño del negocio de una nueva solicitud Factura Express QR.
     * Usa la configuración de correos_config (codigo: notificaciones).
     */
    function enviar_correo_factura_express_dueno(array $resultado): bool
    {
        $base = \App\services\EmailConfigService::getDataForSendEmail('notificaciones');
        if (!$base) {
            $GLOBALS['LAST_EMAIL_ERROR'] = 'No hay configuración en correos_config (codigo: notificaciones)';
            return false;
        }

        $docMailDir = MVC_APP . '/lib/mail';
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
            $mail->Host       = _mail_resolve_ipv4_host($base['host']);
            $mail->SMTPAuth   = true;
            $mail->Username   = $base['emisor'];
            $mail->Password   = $base['pass'];
            $mail->SMTPSecure = $base['smtp_secure'] ?? 'tls';
            $mail->Port       = $base['port'];
            $mail->CharSet    = 'UTF-8';

            $config = require MVC_CONFIG . '/app.php';
            if (!empty($config['mail_smtp_options'])) {
                $mail->SMTPOptions = $config['mail_smtp_options'];
            }

            $mail->setFrom($base['emisor'], $base['empresa']);

            // La notificación va al correo de la EMPRESA que generó la factura express.
            // Si la empresa no tiene correo válido configurado, se usa la cuenta SMTP
            // como respaldo para no perder el aviso.
            $correoEmpresa = trim((string) ($resultado['plantilla']['empresa_mail'] ?? ''));
            $destinoDueno  = filter_var($correoEmpresa, FILTER_VALIDATE_EMAIL) ? $correoEmpresa : $base['emisor'];
            $nombreEmpresa = $resultado['plantilla']['empresa_nombre'] ?? $base['empresa'];
            $mail->addAddress($destinoDueno, $nombreEmpresa);

            $solicitudData = $resultado['data'] ?? $resultado['solicitud'] ?? $resultado;
            $solicitudData['nombre_plantilla'] = $resultado['plantilla']['nombre'] ?? ($solicitudData['nombre_plantilla'] ?? '');
            $solicitudData['token_cliente']    = $resultado['token_cliente'] ?? ($solicitudData['token_cliente'] ?? '');
            $mail->Subject = 'Nueva solicitud Factura Express: ' . ($solicitudData['nombre_cliente'] ?? '');

            $data = ['solicitud' => $solicitudData];
            ob_start();
            require $docMailDir . '/email_factura_express_dueno.php';
            $mail->Body = ob_get_clean();
            $mail->isHTML(true);

            return $mail->send();
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            $GLOBALS['LAST_EMAIL_ERROR'] = $mail->ErrorInfo ?? $e->getMessage();
            error_log('Mailer Error (FexprDueno): ' . ($GLOBALS['LAST_EMAIL_ERROR']));
            return false;
        }
    }
}

if (!function_exists('enviar_correo_factura_express_cliente')) {
    /**
     * Envía confirmación al cliente de que su solicitud Factura Express QR fue recibida.
     * Usa la configuración de correos_config (codigo: notificaciones).
     */
    function enviar_correo_factura_express_cliente(array $resultado): bool
    {
        $solicitud = $resultado['data'] ?? $resultado['solicitud'] ?? $resultado;
        $solicitud['nombre_plantilla'] = $resultado['plantilla']['nombre'] ?? ($solicitud['nombre_plantilla'] ?? '');
        $solicitud['token_cliente']    = $resultado['token_cliente'] ?? ($solicitud['token_cliente'] ?? '');
        $correo    = $solicitud['correo_cliente'] ?? '';
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $base = \App\services\EmailConfigService::getDataForSendEmail('notificaciones');
        if (!$base) {
            $GLOBALS['LAST_EMAIL_ERROR'] = 'No hay configuración en correos_config (codigo: notificaciones)';
            return false;
        }

        $docMailDir = MVC_APP . '/lib/mail';
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
            $mail->Host       = _mail_resolve_ipv4_host($base['host']);
            $mail->SMTPAuth   = true;
            $mail->Username   = $base['emisor'];
            $mail->Password   = $base['pass'];
            $mail->SMTPSecure = $base['smtp_secure'] ?? 'tls';
            $mail->Port       = $base['port'];
            $mail->CharSet    = 'UTF-8';

            $config = require MVC_CONFIG . '/app.php';
            if (!empty($config['mail_smtp_options'])) {
                $mail->SMTPOptions = $config['mail_smtp_options'];
            }

            $mail->setFrom($base['emisor'], $base['empresa']);
            $mail->addAddress($correo, $solicitud['nombre_cliente'] ?? '');
            $mail->Subject = 'Hemos recibido tu solicitud de factura';

            $data = ['solicitud' => $solicitud];
            ob_start();
            require $docMailDir . '/email_factura_express_cliente.php';
            $mail->Body = ob_get_clean();
            $mail->isHTML(true);

            return $mail->send();
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            $GLOBALS['LAST_EMAIL_ERROR'] = $mail->ErrorInfo ?? $e->getMessage();
            error_log('Mailer Error (FexprCliente): ' . ($GLOBALS['LAST_EMAIL_ERROR']));
            return false;
        }
    }
}
