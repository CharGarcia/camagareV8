<?php
/**
 * Application - Front Controller
 */

declare(strict_types=1);

namespace App\core;

class Application
{
    private Router $router;
    private string $basePath;
    private array $config;

    public function __construct(string $basePath = '')
    {
        $this->basePath = $basePath;
        $this->router = new Router($basePath);
        $this->config = require MVC_CONFIG . '/app.php';
    }

    public function run(): void
    {
        $dispatch = $this->router->dispatch();
        $controller = $dispatch['controller'];
        $action = $dispatch['action'];

        // La API móvil (/api/v1/*) es stateless: no usa cookie de sesión de navegador.
        // $_SESSION se rellena por request a partir del JWT (ver ApiAuthMiddleware) y
        // no se persiste entre requests.
        $isApi = str_starts_with($controller, 'api\\');

        if ($isApi) {
            if (session_status() === PHP_SESSION_NONE) {
                // Sesión real pero SIN cookie: código reutilizado de la web (p. ej.
                // Controller::requireAuth()) llama session_start() por su cuenta: si
                // session_status() siguiera en NONE, esa llamada REINICIARÍA $_SESSION
                // y borraría lo que ApiAuthMiddleware acaba de rellenar desde el JWT.
                // Arrancar la sesión aquí (con id aleatorio propio y sin cookie) evita
                // ese re-inicio; no se persiste de forma útil entre requests.
                ini_set('session.use_cookies', '0');
                ini_set('session.use_only_cookies', '0');
                ini_set('session.use_trans_sid', '0');
                ini_set('session.gc_maxlifetime', '120');
                session_id(bin2hex(random_bytes(16)));
                session_start();
                $_SESSION = [];
            }
        } elseif (session_status() === PHP_SESSION_NONE) {
            session_name($this->config['session']['name'] ?? 'PHPSESSID');
            // Cookie path=/ para que funcione cuando BASE_URL está vacío (sitio en raíz)
            session_set_cookie_params([
                'lifetime' => $this->config['session']['lifetime'] ?? 7200,
                'path' => '/',
                'domain' => '',
                'secure' => false,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }

        // Controladores públicos (sin autenticación requerida)
        $publicControllers = ['Auth', 'Registro', 'SolicitudFirma', 'FacturaExpressPublico', 'WhatsappWebhook', 'Reservas', 'Payphone', 'CargasInventarioAprobacion', 'Asistencia', 'ImportacionesAprobacion', 'TransferenciasAprobacion'];

        // La autenticación de /api/v1/* la resuelve ApiAuthMiddleware (Bearer JWT) dentro
        // de cada ApiBaseController; aquí no se bloquea por falta de sesión de navegador.
        if (!$isApi && !isset($_SESSION['id_usuario']) && !in_array($controller, $publicControllers, true)) {
            $isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                   || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
            if ($isAjax) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => 'Sesión expirada. Por favor recarga e inicia sesión.']);
                exit;
            }
            $base = rtrim(defined('BASE_URL') ? BASE_URL : '', '/');
            $loginUrl = $base === '' ? '/' : $base . '/';
            header('Location: ' . $loginUrl);
            exit;
        }

        $controllerName = 'App\\controllers\\' . $controller . 'Controller';

        if (!class_exists($controllerName)) {
            $this->handleError(404, "Controlador no encontrado: {$dispatch['controller']}");
            return;
        }

        $controller = new $controllerName();
        if (!method_exists($controller, $action)) {
            $this->handleError(404, "Acción no encontrada: {$action}");
            return;
        }

        $controller->$action();
    }

    private function handleError(int $code, string $message): void
    {
        // El mensaje técnico queda solo en el log; nunca se muestra al usuario.
        error_log("ROUTING ERROR $code: $message");
        http_response_code($code);

        // Siempre renderizar la vista de error si existe (también en modo debug).
        $viewPath = MVC_APP . '/views/errors/' . $code . '.php';
        if (file_exists($viewPath)) {
            require $viewPath;
            return;
        }

        // Sin vista: en debug mostrar el detalle técnico; en producción, mensaje genérico.
        if ($this->config['debug'] ?? false) {
            echo "<h1>Error {$code}</h1><p>" . htmlspecialchars($message) . "</p>";
        } else {
            echo "<h1>Error {$code}</h1>";
        }
    }
}
