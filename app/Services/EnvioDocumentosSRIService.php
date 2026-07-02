<?php
declare(strict_types=1);

namespace App\Services;

use App\repositories\modulos\EmpresaRepository;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EnvioDocumentosSRIService
{
    /**
     * Evalúa si debe enviar el correo y lo envía si corresponde.
     *
     * @param int $idEmpresa ID de la empresa
     * @param string $tipoDocumento 'factura_venta', 'retencion_compra', etc.
     * @param array $cabecera Datos de la cabecera del documento (debe contener cliente_email o sujeto_email, cliente_nombre, etc.)
     * @param string $xmlString Contenido XML autorizado
     * @param string $pdfString Contenido PDF generado
     * @param string $numAutorizacion Número de autorización SRI
     * @return bool
     */
    public function enviarSiAplica(
        int $idEmpresa,
        string $tipoDocumento,
        array $cabecera,
        string $xmlString,
        string $pdfString,
        string $numAutorizacion,
        bool $forzarEnvio = false,
        ?string $destinatariosAlternativos = null
    ): bool {
        // 1. Obtener la configuración de correo de la empresa
        $empresaRepo = new EmpresaRepository();
        $correoConfig = $empresaRepo->getCorreoConfig($idEmpresa);

        if (!$forzarEnvio && (empty($correoConfig) || empty($correoConfig['envio_automatico']))) {
            return false; // No tiene configurado el envío automático y no se forzó
        }

        // 2. Extraer datos del destinatario
        if (!empty($destinatariosAlternativos)) {
            $emailDestinoRaw = $destinatariosAlternativos;
        } else {
            // En facturas es cliente_email/cliente_nombre. En retenciones podría ser proveedor_email/proveedor_nombre.
            $emailDestinoRaw = $cabecera['cliente_email'] ?? $cabecera['proveedor_email'] ?? $cabecera['email'] ?? '';
        }
        
        $nombreDestino = $cabecera['cliente_nombre'] ?? $cabecera['proveedor_nombre'] ?? $cabecera['nombre'] ?? 'Cliente/Proveedor';

        // Convertir string separado por comas o punto y coma en array de correos válidos
        $listaCorreosRaw = preg_split('/[\s,;]+/', $emailDestinoRaw);
        $listaDestinos = [];
        foreach ($listaCorreosRaw as $c) {
            $c = trim($c);
            if (filter_var($c, FILTER_VALIDATE_EMAIL)) {
                $listaDestinos[] = $c;
            }
        }

        if (empty($listaDestinos)) {
            error_log("[SRI Correo] No se puede enviar correo: destinatarios inválidos o vacíos para doc $tipoDocumento.");
            return false;
        }

        // 3. Determinar credenciales SMTP
        $tipoCorreo = $correoConfig['tipo_correo'] ?? 'camagare';
        $smtpData = null;

        if ($tipoCorreo === 'camagare') {
            // Usar correo general del sistema
            $smtpData = EmailConfigService::getPhpMailerConfig('envio_documentos_sri');
            if (!$smtpData) {
                error_log("[SRI Correo] Error: No existe configuración en correos_config con código 'envio_documentos_sri'.");
                return false;
            }
        } else {
            // Usar correo propio configurado por la empresa
            $enc = $correoConfig['ssl_habilitado'] ? 'tls' : ''; // o ssl dependiendo de la lógica, default tls
            $smtpData = [
                'host' => $correoConfig['host'] ?? '',
                'port' => (int)($correoConfig['puerto'] ?? 587),
                'username' => $correoConfig['correo_emisor'] ?? '',
                'password' => $correoConfig['password_correo_emisor'] ?? '',
                'from' => $correoConfig['correo_emisor'] ?? '',
                'fromName' => 'Emisor Electrónico', // Se puede mejorar con el nombre de la empresa
                'smtpSecure' => $enc,
            ];
        }

        // 4. Preparar el contenido del correo
        $asunto = $correoConfig['asunto_correo'] ?: 'Comprobante Electrónico Autorizado';
        $cuerpoPersonalizado = $correoConfig['cuerpo_correo'] ?? '';

        $nombreDocStr = match ($tipoDocumento) {
            'factura_venta' => 'Factura',
            'nota_credito' => 'Nota de Crédito',
            'retencion_compra' => 'Comprobante de Retención',
            'guia_remision' => 'Guía de Remisión',
            'liquidacion_compra' => 'Liquidación de Compra',
            default => 'Comprobante Electrónico',
        };

        // Construir el HTML
        $htmlCuerpo = "<div style='font-family: Arial, sans-serif; line-height: 1.5;'>";
        $htmlCuerpo .= "<p><strong>Estimad@:</strong> " . htmlspecialchars($nombreDestino) . "</p>";
        $htmlCuerpo .= "<p><strong>Tipo de documento:</strong> " . $nombreDocStr . "</p>";

        // Nombre base para los archivos adjuntos y número de documento visible (ej: 001-001-000000001)
        $secuencial = $cabecera['secuencial'] ?? '';
        $establecimiento = $cabecera['establecimiento'] ?? '001';
        $puntoEmision = $cabecera['punto_emision'] ?? '001';
        $numComprobante = "";
        if (!empty($secuencial)) {
            $numComprobante = $establecimiento . '-' . $puntoEmision . '-' . str_pad((string)$secuencial, 9, '0', STR_PAD_LEFT);
        } else {
            $numComprobante = $cabecera['clave_acceso'] ?? time();
        }

        $claveAcceso = $cabecera['clave_acceso'] ?? $numAutorizacion;
        $htmlCuerpo .= "<p><strong>Número de documento:</strong> " . htmlspecialchars((string)$numComprobante) . "</p>";
        $htmlCuerpo .= "<p><strong>Número de autorización:</strong> " . htmlspecialchars($claveAcceso) . "</p>";
        $htmlCuerpo .= "<hr style='border: 0; border-top: 1px solid #ccc; margin: 20px 0;'>";
        if (!empty($cuerpoPersonalizado)) {
            $htmlCuerpo .= "<div>" . $cuerpoPersonalizado . "</div>";
        } else {
            $htmlCuerpo .= "<p>Adjuntamos a este correo su comprobante electrónico en formato PDF y XML.</p>";
        }
        $htmlCuerpo .= "</div>";

        // Nombre base para los archivos adjuntos (ej: Factura_001-001-000000001)


        $baseName = str_replace(' ', '_', $nombreDocStr) . '_' . $numComprobante;

        // 5. Enviar usando PHPMailer
        return $this->enviarPhpMailer($smtpData, $listaDestinos, $nombreDestino, $asunto, $htmlCuerpo, $baseName, $xmlString, $pdfString);
    }

    /**
     * Envía al cliente el enlace de pago con tarjeta + PDF de la factura adjunto.
     * Usa la misma configuración SMTP que el envío de comprobantes.
     */
    public function enviarEnlacePagoTarjeta(
        int    $idEmpresa,
        string $correoDestino,
        string $clienteNombre,
        string $empresaNombre,
        float  $monto,
        string $descripcion,
        string $urlPago,
        string $pdfString
    ): bool {
        $empresaRepo  = new EmpresaRepository();
        $correoConfig = $empresaRepo->getCorreoConfig($idEmpresa);

        $tipoCorreo = $correoConfig['tipo_correo'] ?? 'camagare';

        if ($tipoCorreo === 'camagare') {
            $smtpData = EmailConfigService::getPhpMailerConfig('envio_documentos_sri');
            if (!$smtpData) {
                error_log('[PagoTarjeta] No hay configuración SMTP (envio_documentos_sri).');
                return false;
            }
        } else {
            $enc = !empty($correoConfig['ssl_habilitado']) ? 'tls' : '';
            $smtpData = [
                'host'        => $correoConfig['host']                  ?? '',
                'port'        => (int)($correoConfig['puerto']          ?? 587),
                'username'    => $correoConfig['correo_emisor']         ?? '',
                'password'    => $correoConfig['password_correo_emisor']?? '',
                'from'        => $correoConfig['correo_emisor']         ?? '',
                'fromName'    => $empresaNombre,
                'smtpSecure'  => $enc,
            ];
        }

        $docMailDir = MVC_APP . '/lib/mail';
        require_once $docMailDir . '/phpmailer.php';
        require_once $docMailDir . '/smtp.php';
        require_once $docMailDir . '/exception.php';

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = _mail_resolve_ipv4_host($smtpData['host']);
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtpData['username'];
            $mail->Password   = $smtpData['password'];
            $mail->SMTPSecure = $smtpData['smtpSecure'] ?? 'tls';
            $mail->Port       = $smtpData['port'];
            $mail->CharSet    = 'UTF-8';

            $config = require MVC_CONFIG . '/app.php';
            if (!empty($config['mail_smtp_options'])) {
                $mail->SMTPOptions = $config['mail_smtp_options'];
            }

            $mail->setFrom($smtpData['from'], $smtpData['fromName']);
            // Soporta uno o varios correos separados por coma/punto y coma
            $algunDestino = false;
            foreach (preg_split('/[\s,;]+/', $correoDestino) as $dest) {
                $dest = trim($dest);
                if (filter_var($dest, FILTER_VALIDATE_EMAIL)) {
                    $mail->addAddress($dest, $clienteNombre);
                    $algunDestino = true;
                }
            }
            if (!$algunDestino) {
                error_log('[PagoTarjeta] Sin destinatarios válidos: ' . $correoDestino);
                return false;
            }
            $mail->Subject = 'Enlace de pago con tarjeta — ' . $descripcion;

            // Cuerpo del correo
            $data = [
                'cliente_nombre' => $clienteNombre,
                'empresa_nombre' => $empresaNombre,
                'monto'          => $monto,
                'descripcion'    => $descripcion,
                'url_pago'       => $urlPago,
            ];
            ob_start();
            require $docMailDir . '/email_pago_tarjeta.php';
            $mail->Body = ob_get_clean();
            $mail->isHTML(true);

            // PDF adjunto
            if (!empty($pdfString)) {
                $nombreArchivo = str_replace([' ', '/'], '_', $descripcion) . '.pdf';
                $mail->addStringAttachment($pdfString, $nombreArchivo, 'base64', 'application/pdf');
            }

            return $mail->send();
        } catch (Exception $e) {
            error_log('[PagoTarjeta] Mailer Error: ' . ($mail->ErrorInfo ?? $e->getMessage()));
            return false;
        }
    }

    /**
     * Envía al cliente un aviso de que su comprobante fue ANULADO.
     * Usa la misma configuración SMTP que el envío de comprobantes.
     *
     * @param array $cabecera Debe contener cliente_email/email, cliente_nombre y datos del documento.
     */
    public function enviarAvisoAnulacion(
        int    $idEmpresa,
        string $tipoDocumento,
        array  $cabecera,
        ?string $destinatariosAlternativos = null
    ): bool {
        $empresaRepo  = new EmpresaRepository();
        $correoConfig = $empresaRepo->getCorreoConfig($idEmpresa);

        // Destinatarios
        $emailDestinoRaw = $destinatariosAlternativos
            ?? ($cabecera['cliente_email'] ?? $cabecera['proveedor_email'] ?? $cabecera['email'] ?? '');
        $nombreDestino = $cabecera['cliente_nombre'] ?? $cabecera['proveedor_nombre'] ?? $cabecera['nombre'] ?? 'Cliente';

        $listaDestinos = [];
        foreach (preg_split('/[\s,;]+/', (string)$emailDestinoRaw) as $c) {
            $c = trim($c);
            if (filter_var($c, FILTER_VALIDATE_EMAIL)) {
                $listaDestinos[] = $c;
            }
        }
        if (empty($listaDestinos)) {
            error_log("[SRI Correo] Aviso anulación: sin destinatarios válidos para doc $tipoDocumento.");
            return false;
        }

        // Credenciales SMTP (misma lógica que enviarSiAplica)
        $tipoCorreo = $correoConfig['tipo_correo'] ?? 'camagare';
        if ($tipoCorreo === 'camagare') {
            $smtpData = EmailConfigService::getPhpMailerConfig('envio_documentos_sri');
            if (!$smtpData) {
                error_log("[SRI Correo] Aviso anulación: falta config 'envio_documentos_sri'.");
                return false;
            }
        } else {
            $enc = !empty($correoConfig['ssl_habilitado']) ? 'tls' : '';
            $smtpData = [
                'host'       => $correoConfig['host'] ?? '',
                'port'       => (int)($correoConfig['puerto'] ?? 587),
                'username'   => $correoConfig['correo_emisor'] ?? '',
                'password'   => $correoConfig['password_correo_emisor'] ?? '',
                'from'       => $correoConfig['correo_emisor'] ?? '',
                'fromName'   => 'Emisor Electrónico',
                'smtpSecure' => $enc,
            ];
        }

        $nombreDocStr = match ($tipoDocumento) {
            'factura_venta'      => 'Factura',
            'nota_credito'       => 'Nota de Crédito',
            'retencion_compra'   => 'Comprobante de Retención',
            'guia_remision'      => 'Guía de Remisión',
            'liquidacion_compra' => 'Liquidación de Compra',
            default              => 'Comprobante Electrónico',
        };

        $secuencial      = $cabecera['secuencial'] ?? '';
        $establecimiento = $cabecera['establecimiento'] ?? '001';
        $puntoEmision    = $cabecera['punto_emision'] ?? '001';
        $numComprobante  = !empty($secuencial)
            ? $establecimiento . '-' . $puntoEmision . '-' . str_pad((string)$secuencial, 9, '0', STR_PAD_LEFT)
            : (string)($cabecera['clave_acceso'] ?? '');

        $asunto = "Comprobante ANULADO: {$nombreDocStr} {$numComprobante}";

        $htmlCuerpo  = "<div style='font-family: Arial, sans-serif; line-height: 1.5;'>";
        $htmlCuerpo .= "<p><strong>Estimad@:</strong> " . htmlspecialchars($nombreDestino) . "</p>";
        $htmlCuerpo .= "<p>Le informamos que el siguiente comprobante electrónico ha sido <strong style='color:#b00020;'>ANULADO</strong>:</p>";
        $htmlCuerpo .= "<p><strong>Tipo de documento:</strong> " . $nombreDocStr . "</p>";
        $htmlCuerpo .= "<p><strong>Número de documento:</strong> " . htmlspecialchars((string)$numComprobante) . "</p>";
        if (!empty($cabecera['clave_acceso'])) {
            $htmlCuerpo .= "<p><strong>Clave de acceso:</strong> " . htmlspecialchars((string)$cabecera['clave_acceso']) . "</p>";
        }
        $htmlCuerpo .= "<hr style='border:0;border-top:1px solid #ccc;margin:16px 0;'>";
        $htmlCuerpo .= "<p>Este comprobante ya no tiene validez. Si tiene alguna duda, comuníquese con nosotros.</p>";
        $htmlCuerpo .= "</div>";

        $baseName = str_replace(' ', '_', $nombreDocStr) . '_ANULADO_' . $numComprobante;

        // Sin adjuntos (aviso informativo)
        return $this->enviarPhpMailer($smtpData, $listaDestinos, $nombreDestino, $asunto, $htmlCuerpo, $baseName, '', '');
    }

    /**
     * Envía un aviso simple (HTML, sin adjuntos) usando la config de correo de la empresa.
     * Pensado para avisos de vencimiento de suscripciones u otros recordatorios.
     *
     * @param string $emailDestino   Uno o varios correos separados por coma/punto y coma
     * @param string $nombreDestino  Nombre del destinatario
     * @param string $empresaNombre  Nombre que aparece como remitente (fromName)
     */
    public function enviarAvisoSimple(
        int    $idEmpresa,
        string $emailDestino,
        string $nombreDestino,
        string $asunto,
        string $cuerpoHtml,
        string $empresaNombre = ''
    ): bool {
        $empresaRepo  = new EmpresaRepository();
        $correoConfig = $empresaRepo->getCorreoConfig($idEmpresa);

        // Destinatarios válidos
        $listaDestinos = [];
        foreach (preg_split('/[\s,;]+/', $emailDestino) as $c) {
            $c = trim($c);
            if (filter_var($c, FILTER_VALIDATE_EMAIL)) {
                $listaDestinos[] = $c;
            }
        }
        if (empty($listaDestinos)) {
            return false;
        }

        // Credenciales SMTP (misma lógica que enviarSiAplica)
        $tipoCorreo = $correoConfig['tipo_correo'] ?? 'camagare';
        if ($tipoCorreo === 'camagare') {
            $smtpData = EmailConfigService::getPhpMailerConfig('envio_documentos_sri');
            if (!$smtpData) {
                error_log("[Aviso Suscripción] Falta config 'envio_documentos_sri'.");
                return false;
            }
            if ($empresaNombre !== '') {
                $smtpData['fromName'] = $empresaNombre;
            }
        } else {
            $enc = !empty($correoConfig['ssl_habilitado']) ? 'tls' : '';
            $smtpData = [
                'host'       => $correoConfig['host'] ?? '',
                'port'       => (int)($correoConfig['puerto'] ?? 587),
                'username'   => $correoConfig['correo_emisor'] ?? '',
                'password'   => $correoConfig['password_correo_emisor'] ?? '',
                'from'       => $correoConfig['correo_emisor'] ?? '',
                'fromName'   => $empresaNombre !== '' ? $empresaNombre : 'Notificaciones',
                'smtpSecure' => $enc,
            ];
        }

        return $this->enviarPhpMailer($smtpData, $listaDestinos, $nombreDestino, $asunto, $cuerpoHtml, 'aviso', '', '');
    }

    private function enviarPhpMailer(array $smtpData, array $toEmails, string $toName, string $subject, string $bodyHtml, string $baseName, string $xmlString, string $pdfString): bool
    {
        $docMailDir = MVC_APP . '/lib/mail';
        if (!file_exists($docMailDir . '/phpmailer.php')) {
            error_log('[SRI Correo] No se encuentra la librería PHPMailer en lib/mail.');
            return false;
        }

        require_once $docMailDir . '/phpmailer.php';
        require_once $docMailDir . '/smtp.php';
        require_once $docMailDir . '/exception.php';

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = _mail_resolve_ipv4_host($smtpData['host']);
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtpData['username'];
            $mail->Password   = $smtpData['password'];
            $mail->SMTPSecure = $smtpData['smtpSecure'] ?? 'tls';
            $mail->Port       = $smtpData['port'];
            $mail->CharSet    = 'UTF-8';

            $config = require MVC_CONFIG . '/app.php';
            if (!empty($config['mail_smtp_options'])) {
                $mail->SMTPOptions = $config['mail_smtp_options'];
            }

            $mail->setFrom($smtpData['from'], $smtpData['fromName']);
            
            foreach ($toEmails as $email) {
                $mail->addAddress($email, $toName);
            }
            
            $mail->Subject = $subject;

            $mail->Body = $bodyHtml;
            $mail->isHTML(true);

            // Adjuntos
            if (!empty($xmlString)) {
                $mail->addStringAttachment($xmlString, $baseName . '.xml', 'base64', 'application/xml');
            }
            if (!empty($pdfString)) {
                $mail->addStringAttachment($pdfString, $baseName . '.pdf', 'base64', 'application/pdf');
            }

            return $mail->send();
        } catch (Exception $e) {
            error_log('[SRI Correo] Mailer Error: ' . ($mail->ErrorInfo ?? $e->getMessage()));
            return false;
        }
    }
}
