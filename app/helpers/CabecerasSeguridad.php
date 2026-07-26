<?php
/**
 * CabecerasSeguridad — cabeceras HTTP de seguridad para todas las respuestas web.
 *
 * Se aplican desde PHP (no desde Nginx) para que viajen con el `git pull` del
 * despliegue y valgan igual en XAMPP, sin depender de que alguien recuerde
 * editar la configuración del servidor.
 *
 * Calibradas contra el uso real del sistema:
 *   - 1.617 manejadores inline (onclick/onchange) en las vistas ⇒ la CSP necesita
 *     'unsafe-inline' en script-src. Quitarlo exige refactorizar las vistas.
 *   - Cámara y GPS se usan en clientes, proveedores, puntos de servicio,
 *     consignaciones, empleados y la asistencia pública ⇒ Permissions-Policy
 *     debe permitirlos en el propio origen.
 *   - El portal de reservas (/reservas/{slug}) se entrega como <iframe> para que
 *     el cliente lo incruste en SU web ⇒ es la única ruta que no se protege
 *     contra framing (ver CONTROLADORES_EMBEBIBLES).
 */

declare(strict_types=1);

namespace App\Helpers;

class CabecerasSeguridad
{
    /**
     * Controladores cuyas páginas están pensadas para incrustarse en sitios de
     * terceros. Añadir aquí rompe la protección contra clickjacking de esa ruta:
     * hacerlo solo si el módulo entrega un <iframe> para la web del cliente.
     */
    private const CONTROLADORES_EMBEBIBLES = ['Reservas'];

    /** Orígenes externos que el sistema carga hoy. */
    private const CDN_SCRIPTS = [
        'https://cdn.jsdelivr.net',
        'https://cdnjs.cloudflare.com',
        'https://cdn.quilljs.com',
        'https://cdn.payphonetodoesposible.com',
    ];

    private const CDN_ESTILOS = [
        'https://cdn.jsdelivr.net',
        'https://cdnjs.cloudflare.com',
        'https://cdn.quilljs.com',
        'https://fonts.googleapis.com',
    ];

    private const CDN_FUENTES = [
        'https://fonts.gstatic.com',
        'https://cdn.jsdelivr.net',
        'https://cdnjs.cloudflare.com',
    ];

    private const MARCOS_PERMITIDOS = [
        'https://pay.payphonetodoesposible.com',
        'https://dashboard.kushkipagos.com',
        'https://www.openstreetmap.org',
    ];

    /**
     * @param string $controlador Nombre del controlador resuelto por el Router.
     * @param array  $config      config/app.php ya cargado.
     */
    public static function aplicar(string $controlador, array $config = []): void
    {
        if (headers_sent()) {
            return;
        }

        // Oculta la versión de PHP (ayuda a quien busca exploits conocidos).
        header_remove('X-Powered-By');

        // Impide que el navegador "adivine" el tipo de un archivo servido: un
        // .txt subido que el navegador interprete como HTML sería XSS almacenado.
        header('X-Content-Type-Options: nosniff');

        // No filtrar la URL completa (lleva ids de documento) a sitios externos.
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Cámara y ubicación sí (asistencia, geolocalización de entregas);
        // micrófono, pagos por API del navegador y sensores, no.
        header('Permissions-Policy: camera=(self), geolocation=(self), microphone=(), payment=(), usb=(), magnetometer=(), accelerometer=()');

        // Clickjacking: nadie puede meter el sistema en un iframe ajeno.
        $embebible = in_array($controlador, self::CONTROLADORES_EMBEBIBLES, true);
        if (!$embebible) {
            header('X-Frame-Options: SAMEORIGIN');
        }

        // HSTS: solo tiene sentido servido por HTTPS. Sin includeSubDomains para
        // no arrastrar subdominios que aún no tengan certificado.
        if (self::esHttps()) {
            $maxAge = (int) ($config['security']['hsts_max_age'] ?? 15552000); // 180 días
            if ($maxAge > 0) {
                header('Strict-Transport-Security: max-age=' . $maxAge);
            }
        }

        self::aplicarCsp($config, $embebible);
    }

    /**
     * Content-Security-Policy. Modo configurable en config/app.php:
     *   'off'          no se envía
     *   'report-only'  se envía como Content-Security-Policy-Report-Only: el
     *                  navegador NO bloquea nada y anota las violaciones en su
     *                  consola. Es el modo con el que se calibra.
     *   'enforce'      bloquea de verdad.
     */
    private static function aplicarCsp(array $config, bool $embebible): void
    {
        $modo = (string) ($config['security']['csp'] ?? 'report-only');
        if ($modo === 'off') {
            return;
        }

        $scripts = implode(' ', self::CDN_SCRIPTS);
        $estilos = implode(' ', self::CDN_ESTILOS);
        $fuentes = implode(' ', self::CDN_FUENTES);
        $marcos  = implode(' ', self::MARCOS_PERMITIDOS);

        $directivas = [
            "default-src 'self'",
            // Evita que una inyección cambie la base de las URLs relativas.
            "base-uri 'self'",
            // Sin Flash/applets.
            "object-src 'none'",
            // Un formulario inyectado no puede enviar los datos a otro servidor.
            "form-action 'self'",
            // 'unsafe-inline' es obligatorio mientras existan los onclick inline.
            "script-src 'self' 'unsafe-inline' {$scripts}",
            "style-src 'self' 'unsafe-inline' {$estilos}",
            "font-src 'self' data: {$fuentes}",
            // https: cubre logos de empresa, códigos QR y teselas de mapa.
            "img-src 'self' data: blob: https:",
            "connect-src 'self' https:",
            "frame-src 'self' {$marcos}",
        ];

        if (!$embebible) {
            // Equivalente moderno de X-Frame-Options; se envían ambos porque los
            // navegadores viejos solo entienden el primero.
            $directivas[] = "frame-ancestors 'self'";
        }

        $politica = implode('; ', $directivas);

        if ($modo === 'enforce') {
            header('Content-Security-Policy: ' . $politica);
        } else {
            header('Content-Security-Policy-Report-Only: ' . $politica);
        }
    }

    /** ¿La petición llegó por HTTPS? Contempla el proxy inverso de Nginx. */
    public static function esHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
            return true;
        }
        $proto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        return strtolower((string) $proto) === 'https';
    }
}
