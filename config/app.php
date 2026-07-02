<?php
/**
 * Configuración general del sistema
 * PHP 8+ | Bootstrap 5+ | PostgreSQL
 */

return [
    'name' => 'CaMaGaRe',
    'env' => 'development',
    'debug' => true,
    // Solo en depuración en servidor: true para ver notices/warnings en pantalla (login y POST login).
    'show_login_errors' => true,
    'url' => '/sistema/public',
    'timezone' => 'America/Guayaquil',
    // Consulta RUC/cédula (servicio externo). Si el VPS no alcanza la URL, ponga false y cargue datos a mano.
    'sri_identification_enabled' => true,
    'sri_identification_url' => 'http://137.184.159.242:4000/api/sri-identification',
    'session' => [
        'name' => 'CMG_SESSION',
        'lifetime' => 7200,
    ],
    // En localhost a veces falla la verificación SSL de SMTP. Activar para pruebas.
    'mail_smtp_options' => [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ],
    // API key de 2captcha para resolver reCAPTCHA v3 Enterprise del portal SRI
    '2captcha_api_key' => 'f40ccb9455dfd2bdb8bd2d3e7ccce8fa',

    // Suspende el modo AUTOMÁTICO de descargas SRI (cron directo y automatizaciones).
    // Se reemplaza por la "descarga asistida" (visor remoto + humano en el loop) para
    // no resolver el captcha de forma automática y evitar bloqueos del usuario en el SRI.
    // Palanca reversible: poner en false para reactivar el modo automático.
    'sri_descarga_auto_suspendida' => true,

    // Binario de PHP CLI para lanzar el worker de envío en lote al SRI en segundo
    // plano (scripts/procesar_lote_sri.php). Vacío = autodetección
    // (Windows: C:\xampp\php\php.exe si existe; en otro caso 'php' del PATH).
    // Si el worker no arranca solo, indique aquí la ruta absoluta al ejecutable.
    'sri_lote_php_bin' => '',
];
