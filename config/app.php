<?php
/**
 * Configuración general del sistema
 * PHP 8+ | Bootstrap 5+ | MySQL
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
];
