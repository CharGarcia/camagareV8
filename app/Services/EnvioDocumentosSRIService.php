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
        $listaCorreosRaw = preg_split('/[,;]+/', $emailDestinoRaw);
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
