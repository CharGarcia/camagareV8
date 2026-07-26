<?php
/**
 * Csrf — protección contra Cross-Site Request Forgery.
 *
 * El problema que resuelve: un usuario con sesión abierta visita una página
 * maliciosa; esa página envía en su nombre un POST al ERP y el navegador adjunta
 * la cookie de sesión automáticamente. Sin token, el sistema no distingue esa
 * petición de una legítima y crea la factura, cambia la configuración o elimina
 * el registro. La cookie es `SameSite=Lax`, lo que ya frena los POST entre
 * sitios en navegadores modernos, pero no es una defensa suficiente por sí sola.
 *
 * Diseño: un token por sesión, validado de forma centralizada en
 * `Application::run()`. Los módulos no tienen que hacer nada — el token viaja
 * solo, inyectado por `public/js/csrf.js` en fetch, XHR y formularios.
 *
 * Qué NO se valida (y por qué):
 *   - `/api/v1/*`: es stateless y se autentica con Bearer JWT. Sin cookie no hay
 *     CSRF posible: el navegador de la víctima no adjunta el token de la app móvil.
 *   - Controladores públicos (webhooks de WhatsApp/Payphone/Nuvei, portales de
 *     reservas, asistencia, factura express): no hay sesión autenticada que
 *     suplantar, y sus vistas no cargan el layout que inyecta el token.
 *   - Endpoints del agente de Chrome: se autentican con su propio token.
 */

declare(strict_types=1);

namespace App\Helpers;

class Csrf
{
    private const CLAVE_SESION = '_csrf_token';
    public  const CAMPO        = 'csrf_token';
    public  const CABECERA     = 'X-CSRF-Token';

    /** Métodos que modifican estado y por tanto se validan. */
    private const METODOS_PROTEGIDOS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * Token de la sesión actual; se genera la primera vez que se pide.
     * Es estable durante toda la sesión: así una pestaña abierta hace rato sigue
     * funcionando y no hay que refrescar tokens por formulario.
     */
    public static function token(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            return '';
        }
        if (empty($_SESSION[self::CLAVE_SESION]) || !is_string($_SESSION[self::CLAVE_SESION])) {
            $_SESSION[self::CLAVE_SESION] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::CLAVE_SESION];
    }

    /** Comparación en tiempo constante (no filtra información por el tiempo de respuesta). */
    public static function validar(?string $recibido): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            return false;
        }
        $esperado = $_SESSION[self::CLAVE_SESION] ?? '';
        if (!is_string($esperado) || $esperado === '' || !is_string($recibido) || $recibido === '') {
            return false;
        }
        return hash_equals($esperado, $recibido);
    }

    /** Token que trae la petición: cabecera (fetch/XHR) o campo del formulario. */
    public static function tokenDePeticion(): ?string
    {
        $cabecera = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (is_string($cabecera) && $cabecera !== '') {
            return $cabecera;
        }
        $campo = $_POST[self::CAMPO] ?? '';
        return (is_string($campo) && $campo !== '') ? $campo : null;
    }

    /** Campo oculto para formularios que se envían sin JavaScript. */
    public static function campoOculto(): string
    {
        return '<input type="hidden" name="' . self::CAMPO . '" value="'
            . htmlspecialchars(self::token(), ENT_QUOTES) . '">';
    }

    /**
     * ¿Esta petición debe validarse?
     *
     * @param string $controlador Controlador resuelto por el Router (ej. 'modulos\Productos').
     * @param array  $exentos     Controladores sin validación (públicos, webhooks…).
     */
    public static function requiereValidacion(string $controlador, array $exentos = []): bool
    {
        $metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (!in_array($metodo, self::METODOS_PROTEGIDOS, true)) {
            return false;
        }
        // La API móvil es stateless (Bearer JWT), no usa cookie de sesión.
        if (str_starts_with($controlador, 'api\\')) {
            return false;
        }
        // Comparación por el nombre corto del controlador ('modulos\Egresos' → 'Egresos')
        $corto = str_contains($controlador, '\\')
            ? substr($controlador, strrpos($controlador, '\\') + 1)
            : $controlador;

        return !in_array($controlador, $exentos, true) && !in_array($corto, $exentos, true);
    }

    /**
     * Respuesta a un token inválido. JSON si la petición es AJAX, página si no.
     * El mensaje evita tecnicismos: la causa habitual es una sesión caducada con
     * la pestaña abierta, no un ataque.
     */
    public static function rechazar(): void
    {
        $esAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
                && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) && str_contains((string) $_SERVER['HTTP_ACCEPT'], 'application/json'))
            || (isset($_SERVER['HTTP_X_CSRF_TOKEN']));

        http_response_code(419);

        if ($esAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok'    => false,
                'error' => 'Su sesión expiró o la página estuvo abierta demasiado tiempo. Recargue (F5) e inténtelo otra vez.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><title>Sesión expirada</title>'
            . '<div style="font-family:system-ui,sans-serif;max-width:520px;margin:80px auto;text-align:center">'
            . '<h2>Sesión expirada</h2>'
            . '<p>La página estuvo abierta demasiado tiempo y su sesión ya no es válida.</p>'
            . '<p><a href="' . htmlspecialchars(rtrim(defined('BASE_URL') ? BASE_URL : '', '/') . '/', ENT_QUOTES) . '">Volver a iniciar sesión</a></p>'
            . '</div>';
        exit;
    }

    /**
     * Deja constancia de una petición que habría sido rechazada (modo 'log').
     * Sirve para detectar, antes de activar el bloqueo, cualquier punto del
     * sistema que todavía no esté enviando el token.
     */
    public static function registrarFallo(string $controlador, string $accion): void
    {
        $dir = (defined('MVC_ROOT') ? MVC_ROOT : dirname(__DIR__, 2)) . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $linea = sprintf(
            "[%s] %s %s -> %s::%s | usuario=%s | origen=%s | referer=%s\n",
            date('Y-m-d H:i:s'),
            $_SERVER['REQUEST_METHOD'] ?? '?',
            $_SERVER['REQUEST_URI'] ?? '?',
            $controlador,
            $accion,
            $_SESSION['id_usuario'] ?? '-',
            $_SERVER['HTTP_ORIGIN'] ?? '-',
            $_SERVER['HTTP_REFERER'] ?? '-'
        );
        @file_put_contents($dir . '/csrf.log', $linea, FILE_APPEND | LOCK_EX);
    }
}
