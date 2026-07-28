<?php
/**
 * Configuración general del sistema.
 * PHP 8+ | Bootstrap 5+ | PostgreSQL
 *
 * SEGURIDAD: este archivo ESTÁ VERSIONADO EN GIT.
 *   - Nunca escribir aquí secretos (API keys, contraseñas, tokens).
 *   - Los valores por defecto son los SEGUROS de producción (sin depuración).
 *
 * Cada entorno ajusta lo suyo en config/local.php (no versionado, ver
 * local.php.example) o en variables de entorno. Se mezclan sobre estos valores
 * con esta prioridad:
 *
 *      variable de entorno  >  config/local.php  >  este archivo
 *
 * Así el servidor deja de tener config/app.php modificado a mano y el
 * `git pull` del despliegue ya no choca con cambios locales.
 */

declare(strict_types=1);

$defaults = [
    'name' => 'CaMaGaRe',

    // Valores por defecto = producción. En desarrollo se activan desde local.php.
    'env'   => 'production',
    'debug' => false,
    // Muestra notices/warnings en pantalla durante el login. Solo para depurar
    // en un entorno que NO sea de clientes: expone rutas, SQL y versiones.
    'show_login_errors' => false,

    'url'      => '/sistema/public',
    'timezone' => 'America/Guayaquil',

    // Consulta RUC/cédula (servicio externo). Si el VPS no alcanza la URL, ponga false y cargue datos a mano.
    'sri_identification_enabled' => true,
    'sri_identification_url'     => 'http://137.184.159.242:4000/api/sri-identification',

    'session' => [
        'name'     => 'CMG_SESSION',
        'lifetime' => 7200,
    ],

    // ── Cabeceras de seguridad (app/helpers/CabecerasSeguridad.php) ─────────
    'security' => [
        // Content-Security-Policy: 'report-only' | 'enforce' | 'off'.
        // Se arranca en 'report-only': el navegador no bloquea nada y anota las
        // violaciones en su consola (F12). Cuando se navegue el sistema completo
        // sin violaciones propias, pasar a 'enforce' desde config/local.php.
        'csp' => 'report-only',

        // Vigencia de HSTS en segundos; solo se envía bajo HTTPS. 0 lo desactiva.
        // Subir a 31536000 (1 año) cuando el certificado esté consolidado.
        'hsts_max_age' => 15552000,

        // Freno de fuerza bruta en el login (app/Services/LoginRateLimitService.php).
        // El sistema admite contraseñas de 4 caracteres, así que este bloqueo es
        // la defensa principal contra el adivinado de credenciales: sin él, probar
        // todas las combinaciones cortas es cuestión de minutos.
        'login' => [
            'activo'               => true,
            // Por cuenta: 5 fallos en 15 minutos ⇒ 15 minutos de espera.
            'max_intentos_usuario' => 5,
            'ventana_minutos'      => 15,
            'bloqueo_minutos'      => 15,
            // Por IP: frena al que va probando cédulas distintas desde el mismo sitio.
            // Holgado a propósito, para no castigar a una oficina con IP compartida.
            'max_intentos_ip'      => 20,
            'bloqueo_ip_minutos'   => 30,
            // Cuánto se conserva el historial de intentos (auditoría).
            'dias_retencion'       => 90,
        ],

        // Protección CSRF: 'log' | 'enforce' | 'off'.
        // Arranca en 'log': no rechaza nada y anota en storage/logs/csrf.log las
        // peticiones que se habrían rechazado. Si tras usar el sistema con
        // normalidad ese archivo sigue vacío, pasar a 'enforce'.
        'csrf' => 'log',
    ],

    // Opciones SSL del SMTP.
    // El correo se conecta por IPv4 resuelto a IP (ver _mail_resolve_ipv4_host: evita
    // que PHPMailer intente IPv6 y cuelgue hasta el timeout en droplets sin ruta IPv6).
    // Pero al conectar por IP, la verificación del NOMBRE del certificado no puede
    // coincidir (la IP no está en el CN/SAN del cert) y el TLS falla con
    // "Peer certificate CN did not match expected CN". Por eso se desactiva
    // verify_peer_name; se MANTIENE verify_peer: el certificado sigue teniendo que ser
    // válido y emitido por una CA de confianza (no acepta certificados auto-firmados).
    'mail_smtp_options' => [
        'ssl' => [
            'verify_peer'       => true,
            'verify_peer_name'  => false,
            'allow_self_signed' => false,
        ],
    ],

    // SECRETO: definir en config/local.php ('2captcha_api_key') o en la
    // variable de entorno CMG_2CAPTCHA_KEY. Nunca en este archivo.
    '2captcha_api_key' => '',

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

// ── config/local.php (no versionado) ─────────────────────────────────────────
$localPath = __DIR__ . '/local.php';
if (is_file($localPath)) {
    $local = require $localPath;
    if (is_array($local)) {
        // 'database' pertenece a config/database.php; no se mezcla aquí.
        unset($local['database']);
        $defaults = array_replace_recursive($defaults, $local);
    }
}

// ── Variables de entorno (última palabra) ────────────────────────────────────
$envBool = static function (string $name, ?bool $actual): ?bool {
    $raw = getenv($name);
    if ($raw === false || $raw === '') {
        return $actual;
    }
    return filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $actual;
};

$envStr = static function (string $name, string $actual): string {
    $raw = getenv($name);
    return ($raw === false || $raw === '') ? $actual : (string) $raw;
};

$defaults['env']               = $envStr('CMG_ENV', (string) $defaults['env']);
$defaults['debug']             = (bool) $envBool('CMG_DEBUG', (bool) $defaults['debug']);
$defaults['show_login_errors'] = (bool) $envBool('CMG_SHOW_LOGIN_ERRORS', (bool) $defaults['show_login_errors']);
$defaults['2captcha_api_key']  = $envStr('CMG_2CAPTCHA_KEY', (string) $defaults['2captcha_api_key']);

return $defaults;
