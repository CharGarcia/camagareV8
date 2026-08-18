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
            $msg = "Correo NO enviado ($tipoDocumento): el destinatario no tiene un email válido.";
            error_log("[SRI Correo] $msg (raw: " . $emailDestinoRaw . ")");
            \App\Services\ErrorLogService::registrarManual($msg, ['ruta' => 'EnvioDocumentosSRIService', 'accion' => 'enviarSiAplica']);
            return false;
        }

        // 3. Determinar credenciales SMTP
        // Los datos del emisor se resuelven antes porque el nombre de la empresa es el
        // remitente visible del correo (fromName), no solo la firma del cuerpo.
        $datosEmpresa = $this->datosEmpresaCorreo($idEmpresa);
        $tipoCorreo = $correoConfig['tipo_correo'] ?? 'camagare';
        $smtpData = null;

        if ($tipoCorreo === 'camagare') {
            // Usar correo general del sistema
            $smtpData = EmailConfigService::getPhpMailerConfig('envio_documentos_sri');
            if (!$smtpData) {
                $msg = "Correo NO enviado ($tipoDocumento): falta la configuración de correo del sistema 'envio_documentos_sri' (inexistente o inactiva).";
                error_log("[SRI Correo] $msg");
                \App\Services\ErrorLogService::registrarManual($msg, ['ruta' => 'EnvioDocumentosSRIService', 'accion' => 'enviarSiAplica']);
                return false;
            }
            if ($datosEmpresa['nombre'] !== '') {
                $smtpData['fromName'] = $datosEmpresa['nombre'];
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
                'fromName' => $datosEmpresa['nombre'] !== '' ? $datosEmpresa['nombre'] : 'Emisor Electrónico',
                'smtpSecure' => $enc,
            ];
        }

        // 4. Preparar el contenido del correo (plantilla lib/mail/email_documento_sri.php)
        $asunto              = $correoConfig['asunto_correo'] ?: 'Comprobante Electrónico Autorizado';
        $cuerpoPersonalizado = $correoConfig['cuerpo_correo'] ?? '';

        $nombreDocCorreo  = $this->nombreDocumentoCorreo($tipoDocumento);
        $nombreDocArchivo = $this->nombreDocumentoArchivo($tipoDocumento);

        // Numero de documento visible (ej: 001-001-000000001)
        $secuencial      = $cabecera['secuencial'] ?? '';
        $establecimiento = $cabecera['establecimiento'] ?? '001';
        $puntoEmision    = $cabecera['punto_emision'] ?? '001';
        if (!empty($secuencial)) {
            $numComprobante = $establecimiento . '-' . $puntoEmision . '-' . str_pad((string)$secuencial, 9, '0', STR_PAD_LEFT);
        } else {
            $numComprobante = (string)($cabecera['clave_acceso'] ?? time());
        }

        $claveAcceso = $cabecera['clave_acceso'] ?? $numAutorizacion;

        // Fecha de emision del documento en formato d-m-Y
        $fechaEmisionFmt = '';
        if (!empty($cabecera['fecha_emision'])) {
            $ts = strtotime((string)$cabecera['fecha_emision']);
            $fechaEmisionFmt = $ts ? date('d-m-Y', $ts) : (string)$cabecera['fecha_emision'];
        }

        // Valor total: las retenciones usan total_retenido y las guias de remision no tienen monto.
        $totalRaw = $cabecera['importe_total'] ?? $cabecera['total_retenido'] ?? null;
        $valorTotalFmt = ($totalRaw !== null && $totalRaw !== '')
            ? number_format((float)$totalRaw, 2, '.', '') . ' $'
            : '';

        // Logo del emisor para la cabecera del correo
        $logoPath     = $this->resolverLogoEmpresa($idEmpresa, isset($cabecera['id_establecimiento']) ? (int)$cabecera['id_establecimiento'] : null);
        $logoCid      = $logoPath !== '' ? 'logoempresa' : '';

        // La empresa puede elegir enviar SOLO su propio contenido, sin el diseño del
        // sistema (Empresa > Configuración Correo). Si eligió eso pero dejó el cuerpo
        // vacío, se usa igualmente el diseño para no enviar un correo en blanco.
        $modoCuerpo = $correoConfig['modo_cuerpo_correo'] ?? 'diseno';
        if ($modoCuerpo === 'propio' && trim($cuerpoPersonalizado) !== '') {
            $htmlCuerpo = $cuerpoPersonalizado;
            $logoPath   = '';
            $logoCid    = '';
        } else {
            $htmlCuerpo = $this->renderPlantillaDocumento([
                'nombre_destino'       => $nombreDestino,
                'nombre_documento'     => $nombreDocCorreo,
                'num_comprobante'      => $numComprobante,
                'fecha_emision'        => $fechaEmisionFmt,
                'num_autorizacion'     => (string)$claveAcceso,
                'valor_total'          => $valorTotalFmt,
                'cuerpo_personalizado' => $cuerpoPersonalizado,
                'empresa_nombre'       => $datosEmpresa['nombre'],
                'empresa_ruc'          => $datosEmpresa['ruc'],
                'logo_cid'             => $logoCid,
                'anulado'              => false,
            ]);
        }

        // Nombre base para los archivos adjuntos (ej: Factura_001-001-000000001)
        $baseName = str_replace(' ', '_', $nombreDocArchivo) . '_' . $numComprobante;
        // 5. Enviar usando PHPMailer
        $enviado = $this->enviarPhpMailer($smtpData, $listaDestinos, $nombreDestino, $asunto, $htmlCuerpo, $baseName, $xmlString, $pdfString, [], $logoPath, $logoCid);
        if (!$enviado) {
            \App\Services\ErrorLogService::registrarManual(
                "Correo NO enviado ($tipoDocumento) a " . implode(', ', $listaDestinos) . ": el servidor SMTP rechazó o falló el envío. Revise host/usuario/contraseña de 'envio_documentos_sri' o del correo propio de la empresa.",
                ['ruta' => 'EnvioDocumentosSRIService', 'accion' => 'enviarSiAplica']
            );
        }
        return $enviado;
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
        string $pdfString,
        string $pasarela = 'Payphone'
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
                'pasarela'       => $pasarela,
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
     * Envía un correo de prueba con la configuración de correo indicada
     * (Camagare o propio), sin depender de que ya esté guardada en BD —
     * usa los datos tal como están en el formulario al momento de probar.
     */
    public function enviarCorreoPrueba(string $tipoCorreo, array $datosSmtp, string $destino, string $nombreEmpresa): array
    {
        if ($tipoCorreo === 'camagare') {
            $smtpData = EmailConfigService::getPhpMailerConfig('envio_documentos_sri');
            if (!$smtpData) {
                return ['ok' => false, 'error' => "No hay una configuración de correo del sistema activa ('envio_documentos_sri')."];
            }
        } else {
            $enc = !empty($datosSmtp['ssl_habilitado']) ? 'tls' : '';
            $smtpData = [
                'host'       => trim($datosSmtp['host'] ?? ''),
                'port'       => (int) ($datosSmtp['puerto'] ?? 587),
                'username'   => trim($datosSmtp['correo_emisor'] ?? ''),
                'password'   => (string) ($datosSmtp['password_correo_emisor'] ?? ''),
                'from'       => trim($datosSmtp['correo_emisor'] ?? ''),
                'fromName'   => $nombreEmpresa ?: 'Emisor Electrónico',
                'smtpSecure' => $enc,
            ];
            if ($smtpData['host'] === '' || $smtpData['username'] === '') {
                return ['ok' => false, 'error' => 'Complete al menos el host y el correo emisor para poder probar el correo propio.'];
            }
        }

        $docMailDir = MVC_APP . '/lib/mail';
        if (!file_exists($docMailDir . '/phpmailer.php')) {
            return ['ok' => false, 'error' => 'No se encuentra la librería PHPMailer en el servidor.'];
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
            $mail->addAddress($destino);
            $mail->Subject = 'Correo de prueba' . ($nombreEmpresa ? " - {$nombreEmpresa}" : '');
            $mail->isHTML(true);
            $mail->Body = "<div style='font-family: Arial, sans-serif; line-height: 1.5;'>"
                . "<p>Este es un correo de prueba enviado desde la configuración de correo de <strong>"
                . htmlspecialchars($nombreEmpresa ?: 'su empresa') . "</strong>.</p>"
                . "<p>Si usted recibió este mensaje, la configuración de envío de correo está funcionando correctamente.</p>"
                . "</div>";

            $mail->send();
            return ['ok' => true];
        } catch (Exception $e) {
            return ['ok' => false, 'error' => $mail->ErrorInfo ?: $e->getMessage()];
        }
    }

    /**
     * Envía al cliente la confirmación de una transacción de Nuvei ya resuelta
     * (aprobada o rechazada), con transaction_ID y código de autorización —
     * requisito explícito de Nuvei para poder salir a producción.
     */
    public function enviarConfirmacionPagoNuvei(
        int    $idEmpresa,
        string $correoDestino,
        string $clienteNombre,
        string $empresaNombre,
        float  $monto,
        string $descripcion,
        bool   $aprobado,
        string $transactionId,
        string $authorizationCode,
        ?string $mensajeRechazo = null
    ): bool {
        $empresaRepo  = new EmpresaRepository();
        $correoConfig = $empresaRepo->getCorreoConfig($idEmpresa);

        $tipoCorreo = $correoConfig['tipo_correo'] ?? 'camagare';

        if ($tipoCorreo === 'camagare') {
            $smtpData = EmailConfigService::getPhpMailerConfig('envio_documentos_sri');
            if (!$smtpData) {
                error_log('[Nuvei] No hay configuración SMTP (envio_documentos_sri).');
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
            $algunDestino = false;
            foreach (preg_split('/[\s,;]+/', $correoDestino) as $dest) {
                $dest = trim($dest);
                if (filter_var($dest, FILTER_VALIDATE_EMAIL)) {
                    $mail->addAddress($dest, $clienteNombre);
                    $algunDestino = true;
                }
            }
            if (!$algunDestino) {
                error_log('[Nuvei] Sin destinatarios válidos: ' . $correoDestino);
                return false;
            }

            $mail->Subject = ($aprobado ? 'Pago aprobado' : 'Pago rechazado') . ' — ' . $descripcion;

            $data = [
                'cliente_nombre'      => $clienteNombre,
                'empresa_nombre'      => $empresaNombre,
                'monto'               => $monto,
                'descripcion'         => $descripcion,
                'aprobado'            => $aprobado,
                'transaction_id'      => $transactionId,
                'authorization_code'  => $authorizationCode,
                'mensaje_rechazo'     => $mensajeRechazo,
            ];
            ob_start();
            require $docMailDir . '/email_confirmacion_pago_nuvei.php';
            $mail->Body = ob_get_clean();
            $mail->isHTML(true);

            return $mail->send();
        } catch (Exception $e) {
            error_log('[Nuvei] Mailer Error: ' . ($mail->ErrorInfo ?? $e->getMessage()));
            return false;
        }
    }

    /**
     * Envía al cliente el enlace para registrar una tarjeta (Add Card de Nuvei)
     * y habilitar el cobro automático recurrente — usado por Suscripciones.
     */
    public function enviarRegistroTarjeta(
        int    $idEmpresa,
        string $correoDestino,
        string $clienteNombre,
        string $empresaNombre,
        string $urlRegistro
    ): bool {
        $empresaRepo  = new EmpresaRepository();
        $correoConfig = $empresaRepo->getCorreoConfig($idEmpresa);

        $tipoCorreo = $correoConfig['tipo_correo'] ?? 'camagare';

        if ($tipoCorreo === 'camagare') {
            $smtpData = EmailConfigService::getPhpMailerConfig('envio_documentos_sri');
            if (!$smtpData) {
                error_log('[NuveiTarjeta] No hay configuración SMTP (envio_documentos_sri).');
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
            $algunDestino = false;
            foreach (preg_split('/[\s,;]+/', $correoDestino) as $dest) {
                $dest = trim($dest);
                if (filter_var($dest, FILTER_VALIDATE_EMAIL)) {
                    $mail->addAddress($dest, $clienteNombre);
                    $algunDestino = true;
                }
            }
            if (!$algunDestino) {
                error_log('[NuveiTarjeta] Sin destinatarios válidos: ' . $correoDestino);
                return false;
            }

            $mail->Subject = 'Registra tu tarjeta para el cobro automático — ' . $empresaNombre;

            $data = [
                'cliente_nombre' => $clienteNombre,
                'empresa_nombre' => $empresaNombre,
                'url_registro'   => $urlRegistro,
            ];
            ob_start();
            require $docMailDir . '/email_registro_tarjeta_nuvei.php';
            $mail->Body = ob_get_clean();
            $mail->isHTML(true);

            return $mail->send();
        } catch (Exception $e) {
            error_log('[NuveiTarjeta] Mailer Error: ' . ($mail->ErrorInfo ?? $e->getMessage()));
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

        $nombreDocCorreo  = $this->nombreDocumentoCorreo($tipoDocumento);
        $nombreDocArchivo = $this->nombreDocumentoArchivo($tipoDocumento);

        $secuencial      = $cabecera['secuencial'] ?? '';
        $establecimiento = $cabecera['establecimiento'] ?? '001';
        $puntoEmision    = $cabecera['punto_emision'] ?? '001';
        $numComprobante  = !empty($secuencial)
            ? $establecimiento . '-' . $puntoEmision . '-' . str_pad((string)$secuencial, 9, '0', STR_PAD_LEFT)
            : (string)($cabecera['clave_acceso'] ?? '');

        $asunto = "Comprobante ANULADO: {$nombreDocArchivo} {$numComprobante}";

        $fechaEmisionFmt = '';
        if (!empty($cabecera['fecha_emision'])) {
            $ts = strtotime((string)$cabecera['fecha_emision']);
            $fechaEmisionFmt = $ts ? date('d-m-Y', $ts) : (string)$cabecera['fecha_emision'];
        }

        $totalRaw = $cabecera['importe_total'] ?? $cabecera['total_retenido'] ?? null;
        $valorTotalFmt = ($totalRaw !== null && $totalRaw !== '')
            ? number_format((float)$totalRaw, 2, '.', '') . ' $'
            : '';

        $datosEmpresa = $this->datosEmpresaCorreo($idEmpresa);
        $logoPath     = $this->resolverLogoEmpresa($idEmpresa, isset($cabecera['id_establecimiento']) ? (int)$cabecera['id_establecimiento'] : null);
        $logoCid      = $logoPath !== '' ? 'logoempresa' : '';

        $htmlCuerpo = $this->renderPlantillaDocumento([
            'nombre_destino'   => $nombreDestino,
            'nombre_documento' => $nombreDocCorreo,
            'num_comprobante'  => $numComprobante,
            'fecha_emision'    => $fechaEmisionFmt,
            'num_autorizacion' => (string)($cabecera['clave_acceso'] ?? ''),
            'valor_total'      => $valorTotalFmt,
            'empresa_nombre'   => $datosEmpresa['nombre'],
            'empresa_ruc'      => $datosEmpresa['ruc'],
            'logo_cid'         => $logoCid,
            'anulado'          => true,
        ]);

        $baseName = str_replace(' ', '_', $nombreDocArchivo) . '_ANULADO_' . $numComprobante;

        // Sin adjuntos (aviso informativo)
        return $this->enviarPhpMailer($smtpData, $listaDestinos, $nombreDestino, $asunto, $htmlCuerpo, $baseName, '', '', [], $logoPath, $logoCid);
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

    /**
     * Envía un correo con SOLO un PDF adjunto (sin XML), usando la misma resolución de
     * credenciales SMTP que el envío de comprobantes. Útil para documentos no electrónicos
     * (p. ej. Consignaciones en Ventas).
     */
    public function enviarPdfSimple(
        int    $idEmpresa,
        string $emailDestino,
        string $nombreDestino,
        string $asunto,
        string $cuerpoHtml,
        string $pdfString,
        string $baseName,
        string $empresaNombre = '',
        array  $adjuntosExtra = []
    ): bool {
        $empresaRepo  = new EmpresaRepository();
        $correoConfig = $empresaRepo->getCorreoConfig($idEmpresa);

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

        // Credenciales SMTP (misma lógica que enviarSiAplica / enviarAvisoSimple)
        $tipoCorreo = $correoConfig['tipo_correo'] ?? 'camagare';
        if ($tipoCorreo === 'camagare') {
            $smtpData = EmailConfigService::getPhpMailerConfig('envio_documentos_sri');
            if (!$smtpData) {
                error_log("[Correo PDF] Falta config 'envio_documentos_sri'.");
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

        return $this->enviarPhpMailer($smtpData, $listaDestinos, $nombreDestino, $asunto, $cuerpoHtml, $baseName, '', $pdfString, $adjuntosExtra);
    }

    /**
     * @param array $adjuntosExtra Lista de adjuntos adicionales opcionales, cada uno
     *                             ['contenido' => string, 'nombre' => string]. Ej.: la
     *                             ficha de productos con imágenes de una Proforma.
     */
    /**
     * Nombre del documento tal como se muestra al destinatario en el correo.
     * Incluye el genero correcto de "electronica/electronico".
     */
    private function nombreDocumentoCorreo(string $tipoDocumento): string
    {
        return match ($tipoDocumento) {
            'factura_venta'      => 'Factura electrónica',
            'nota_credito'       => 'Nota de Crédito electrónica',
            'nota_debito'        => 'Nota de Débito electrónica',
            'factura_reembolso'  => 'Factura de Reembolso electrónica',
            'retencion_compra'   => 'Comprobante de Retención electrónico',
            'guia_remision'      => 'Guía de Remisión electrónica',
            'liquidacion_compra' => 'Liquidación de Compra electrónica',
            default              => 'Comprobante electrónico',
        };
    }

    /**
     * Nombre corto del documento, usado para el nombre de los archivos adjuntos.
     */
    private function nombreDocumentoArchivo(string $tipoDocumento): string
    {
        return match ($tipoDocumento) {
            'factura_venta'      => 'Factura',
            'nota_credito'       => 'Nota de Crédito',
            'nota_debito'        => 'Nota de Débito',
            'factura_reembolso'  => 'Factura de Reembolso',
            'retencion_compra'   => 'Comprobante de Retención',
            'guia_remision'      => 'Guía de Remisión',
            'liquidacion_compra' => 'Liquidación de Compra',
            default              => 'Comprobante Electrónico',
        };
    }

    /**
     * Nombre y RUC de la empresa emisora para la firma del correo.
     */
    private function datosEmpresaCorreo(int $idEmpresa): array
    {
        try {
            $emisor = (new EmpresaRepository())->getEmisorConfig($idEmpresa) ?? [];
        } catch (\Throwable) {
            $emisor = [];
        }

        $nombre = trim((string)($emisor['nombre_comercial'] ?? ''));
        if ($nombre === '') {
            $nombre = trim((string)($emisor['nombre'] ?? ''));
        }

        return [
            'nombre' => $nombre,
            'ruc'    => trim((string)($emisor['ruc'] ?? '')),
        ];
    }

    /**
     * Ruta absoluta en disco del logo de la empresa (vive en empresa_establecimiento.logo_ruta).
     * Misma resolucion de rutas que usan los PDF, para no duplicar criterios.
     */
    private function resolverLogoEmpresa(int $idEmpresa, ?int $idEstablecimiento): string
    {
        $logoRuta = '';
        try {
            $repo             = new EmpresaRepository();
            $establecimientos = $repo->getEstablecimientos($idEmpresa);
            foreach ($establecimientos as $est) {
                $esElEstablecimiento = !empty($idEstablecimiento)
                    ? (int)$est['id'] === (int)$idEstablecimiento
                    : true; // si el documento no trae establecimiento, usar el primero
                if ($esElEstablecimiento) {
                    $logoRuta = (string)($est['logo_ruta'] ?? '');
                    break;
                }
            }
        } catch (\Throwable) {
            return '';
        }

        if ($logoRuta === '') {
            return '';
        }

        $clean = ltrim($logoRuta, '/');
        foreach (['sistema/public/', 'sistema/', 'public/'] as $prefijo) {
            if (strpos($clean, $prefijo) === 0) {
                $clean = substr($clean, strlen($prefijo));
                break;
            }
        }

        foreach ([\MVC_ROOT . '/public/' . $clean, \MVC_ROOT . '/' . $clean] as $candidato) {
            if (is_file($candidato)) {
                return $candidato;
            }
        }

        return '';
    }

    /**
     * Renderiza la plantilla HTML del correo de comprobantes (lib/mail/email_documento_sri.php).
     */
    private function renderPlantillaDocumento(array $data): string
    {
        $plantilla = MVC_APP . '/lib/mail/email_documento_sri.php';
        if (!is_file($plantilla)) {
            error_log('[SRI Correo] No se encuentra la plantilla email_documento_sri.php.');
            return '';
        }
        ob_start();
        require $plantilla;
        return (string) ob_get_clean();
    }
    private function enviarPhpMailer(array $smtpData, array $toEmails, string $toName, string $subject, string $bodyHtml, string $baseName, string $xmlString, string $pdfString, array $adjuntosExtra = [], string $logoPath = '', string $logoCid = ''): bool
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

            // Logo de la empresa embebido en el HTML (cid:) porque los clientes de
            // correo bloquean por defecto las imagenes remotas.
            if ($logoPath !== '' && $logoCid !== '' && is_file($logoPath)) {
                $mail->addEmbeddedImage($logoPath, $logoCid, basename($logoPath));
            }

            // Adjuntos
            if (!empty($xmlString)) {
                $mail->addStringAttachment($xmlString, $baseName . '.xml', 'base64', 'application/xml');
            }
            if (!empty($pdfString)) {
                $mail->addStringAttachment($pdfString, $baseName . '.pdf', 'base64', 'application/pdf');
            }
            foreach ($adjuntosExtra as $adj) {
                if (empty($adj['contenido']) || empty($adj['nombre'])) continue;
                $mail->addStringAttachment($adj['contenido'], $adj['nombre'], 'base64', 'application/pdf');
            }

            return $mail->send();
        } catch (Exception $e) {
            error_log('[SRI Correo] Mailer Error: ' . ($mail->ErrorInfo ?? $e->getMessage()));
            return false;
        }
    }
}
