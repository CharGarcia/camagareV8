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
        // Manejador global de errores: registra en `errores_sistema` los fatales y las
        // excepciones NO capturadas (que PHP convierte en fatal al morir). Es post-mortem
        // vía register_shutdown_function: NO cambia cómo se muestran los errores.
        \App\Services\ErrorLogService::registrarHandlersGlobales();

        $dispatch = $this->router->dispatch();
        $controller = $dispatch['controller'];
        $action = $dispatch['action'];

        // Cabeceras de seguridad (clickjacking, sniffing, CSP, HSTS). Antes de
        // cualquier salida y con el controlador ya resuelto, porque el portal de
        // reservas es la única ruta que sí debe poder incrustarse en otra web.
        \App\Helpers\CabecerasSeguridad::aplicar($controller, $this->config);

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
                //
                // Directorio de sesiones PROPIO, aislado del de la web. El recolector de
                // PHP borra, del save_path del request, TODOS los archivos de sesión más
                // viejos que el gc_maxlifetime vigente, sin mirar a qué usuario pertenece
                // cada uno. Con el save_path compartido, un solo request de la API —que
                // baja gc_maxlifetime a 120 s— podía barrer de golpe las sesiones de todos
                // los usuarios de la web con más de 2 minutos sin actividad, echándolos al
                // login sin relación entre ellos. Aislado aquí, ese barrido solo alcanza la
                // basura que genera la propia API (un archivo por request, sin cookie).
                $rutaSesionesApi = MVC_ROOT . '/storage/sessions_api';
                if (is_dir($rutaSesionesApi) || @mkdir($rutaSesionesApi, 0775, true) || is_dir($rutaSesionesApi)) {
                    session_save_path($rutaSesionesApi);
                    // Este directorio no lo recorre el limpiador del sistema (solo mira los
                    // save_path de php.ini), así que la API recoge su propia basura.
                    ini_set('session.gc_probability', '1');
                    ini_set('session.gc_divisor', '100');
                    ini_set('session.gc_maxlifetime', '120');
                }
                // Si el directorio no se pudo crear se sigue con el save_path compartido,
                // pero SIN bajar gc_maxlifetime: mejor acumular basura unas horas que
                // arriesgar el barrido masivo de las sesiones de la web.
                ini_set('session.use_cookies', '0');
                ini_set('session.use_only_cookies', '0');
                ini_set('session.use_trans_sid', '0');
                session_id(bin2hex(random_bytes(16)));
                session_start();
                $_SESSION = [];
            }
        } elseif (session_status() === PHP_SESSION_NONE) {
            $lifetime = (int) ($this->config['session']['lifetime'] ?? 7200);
            session_name($this->config['session']['name'] ?? 'PHPSESSID');
            // El servidor debe conservar el archivo de sesión al menos tanto como dura la
            // cookie: sin esto, session.gc_maxlifetime queda en el default del php.ini del
            // sistema (24 min en muchas instalaciones Ubuntu/Debian), y el propio SO borra
            // el archivo de sesión por inactividad mucho antes de que la cookie expire,
            // cerrando la sesión "de pronto" aunque el navegador la crea válida por 2h.
            ini_set('session.gc_maxlifetime', (string) $lifetime);
            // Cookie path=/ para que funcione cuando BASE_URL está vacío (sitio en raíz)
            $cookieBase = [
                'path' => '/',
                'domain' => '',
                // Bajo HTTPS la cookie se marca Secure para que nunca viaje en
                // claro; en desarrollo (HTTP) queda en false o no habría sesión.
                'secure' => \App\Helpers\CabecerasSeguridad::esHttps(),
                'httponly' => true,
                'samesite' => 'Lax',
            ];
            session_set_cookie_params(['lifetime' => $lifetime] + $cookieBase);
            session_start();

            // Cookie DESLIZANTE. PHP solo manda la cookie al crear la sesión (y al
            // regenerar el id en el login), con un Expires ABSOLUTO: sin esto la sesión
            // moría exactamente $lifetime segundos después de entrar aunque el usuario
            // estuviera trabajando, echándolo al login a media tarea. Reenviarla en cada
            // request hace que el plazo cuente desde la última actividad, igual que el
            // gc_maxlifetime del archivo de sesión en el servidor.
            if (isset($_SESSION['id_usuario'])) {
                setcookie(session_name(), session_id(), ['expires' => time() + $lifetime] + $cookieBase);
            }
        }

        // Controladores públicos (sin autenticación requerida)
        $publicControllers = ['Auth', 'Registro', 'SolicitudFirma', 'FacturaExpressPublico', 'WhatsappWebhook', 'Reservas', 'Payphone', 'Nuvei', 'CargasInventarioAprobacion', 'Asistencia', 'ImportacionesAprobacion', 'TransferenciasAprobacion', 'AceptacionDocumentos', 'PedidoPublico', 'VideollamadaInvitado'];

        // Acciones concretas que NO usan la sesión del navegador porque se autentican
        // con su propio token (extensión de Chrome). La cookie de sesión es SameSite=Lax,
        // así que no viaja en el POST cross-site que hace la extensión: sin esta excepción
        // la petición se redirige al login, el fetch sigue la redirección y devuelve el
        // HTML de login con HTTP 200. Se exceptúan por acción, no el controlador entero.
        $publicActions = [
            'modulos\\DescargasSri' => [
                'agenteRegistrarClavesAjax',
                'agenteRegistrarXmlsAjax',
                'agenteClavesPendientesAjax',
                'agenteLoginPendienteAjax',
            ],
        ];
        $esAccionPublica = isset($publicActions[$controller])
            && in_array($action, $publicActions[$controller], true);

        // La autenticación de /api/v1/* la resuelve ApiAuthMiddleware (Bearer JWT) dentro
        // de cada ApiBaseController; aquí no se bloquea por falta de sesión de navegador.
        if (!$isApi && !$esAccionPublica && !isset($_SESSION['id_usuario']) && !in_array($controller, $publicControllers, true)) {
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

        // ── Protección CSRF ──────────────────────────────────────────────────
        // Punto único de validación para todo el sistema: los módulos no hacen
        // nada, el token lo inyecta public/js/csrf.js en fetch, XHR y formularios.
        // Quedan fuera los controladores públicos (sus vistas no cargan el layout
        // que inyecta el token) y /api/v1/*, que es stateless con Bearer JWT.
        //
        // Modo en config/app.php → security.csrf:
        //   'log'      registra en storage/logs/csrf.log lo que se habría rechazado
        //   'enforce'  rechaza de verdad
        //   'off'      desactivado
        //
        // 'Auth' es público para la AUTENTICACIÓN (hay que poder llegar al login
        // sin sesión), pero NO está exento de CSRF: sus tres vistas —login,
        // confirmación de usuario y cambio de contraseña— sí cargan el token.
        // Proteger el login evita que una web maliciosa fuerce a la víctima a
        // iniciar sesión con la cuenta del atacante.
        $csrfExentos = array_values(array_diff($publicControllers, ['Auth']));

        $modoCsrf = (string) ($this->config['security']['csrf'] ?? 'log');
        if ($modoCsrf !== 'off'
            && !$esAccionPublica
            && \App\Helpers\Csrf::requiereValidacion($controller, $csrfExentos)
            && !\App\Helpers\Csrf::validar(\App\Helpers\Csrf::tokenDePeticion())
        ) {
            \App\Helpers\Csrf::registrarFallo($controller, $action);
            if ($modoCsrf === 'enforce') {
                \App\Helpers\Csrf::rechazar();
            }
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
