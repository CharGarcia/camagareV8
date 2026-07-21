<?php
/**
 * Router - Enrutador de peticiones
 */

declare(strict_types=1);

namespace App\core;

class Router
{
    private array $routes = [];
    private string $basePath = '';

    public function __construct(string $basePath = '')
    {
        $this->basePath = rtrim($basePath, '/');
        $this->loadRoutes();
    }

    private function loadRoutes(): void
    {
        $routesFile = MVC_ROOT . '/routes/web.php';
        if (file_exists($routesFile)) {
            $this->routes = require $routesFile;
        }
    }

    public function dispatch(): array
    {
        $controller = $_GET['controller'] ?? ($this->routes['default_controller'] ?? 'Empresa');
        $action = $_GET['action'] ?? 'index';

        $pathInfo = $_SERVER['PATH_INFO'] ?? '';
        if ($pathInfo === '' && isset($_SERVER['REQUEST_URI'])) {
            $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            if ($uri !== false) {
                $uri = $this->normalizePathForRouting($uri);
                $base = rtrim($this->basePath, '/');
                if ($base !== '' && str_starts_with($uri, $base)) {
                    $pathInfo = substr($uri, strlen($base)) ?: '/';
                } elseif ($base === '') {
                    // Sitio en raíz (BASE_URL vacío): la URI completa es la ruta MVC
                    $pathInfo = ($uri !== '' && $uri !== '/') ? $uri : '/';
                }
            }
        }
        if ($pathInfo !== '' && $pathInfo !== '/') {
            $parts = array_filter(explode('/', trim($pathInfo, '/')));
            $parts = array_values($parts);
            
            if (count($parts) >= 1) {
                if (strtolower($parts[0]) === 'modulos') {
                    if (count($parts) >= 2) {
                        $controllerName = $this->toCamelCase($parts[1]);
                        $controller = 'modulos\\' . ucfirst($controllerName);
                    } else {
                        $controller = 'modulos\\Index';
                    }
                    if (count($parts) >= 3) {
                        $action = $this->toCamelCase($parts[2]);
                    } else {
                        $action = 'index';
                    }
                } elseif (strtolower($parts[0]) === 'api') {
                    // /api/{version}/{controlador}/{accion} → App\controllers\api\{version}\{Controlador}Controller
                    // Version fija por ahora a lo que venga en la URL (ej. 'v1'); si falta, 'v1' por defecto.
                    $version = !empty($parts[1]) ? preg_replace('/[^a-z0-9]/i', '', $parts[1]) : 'v1';
                    if (count($parts) >= 3) {
                        $controllerName = $this->toCamelCase($parts[2]);
                        $controller = 'api\\' . $version . '\\' . ucfirst($controllerName);
                    } else {
                        $controller = 'api\\' . $version . '\\Index';
                    }
                    if (count($parts) >= 4) {
                        $action = $this->toCamelCase($parts[3]);
                    } else {
                        $action = 'index';
                    }
                } else {
                    $controllerName = $this->toCamelCase($parts[0]);
                    $controller = ucfirst($controllerName);
                    if (count($parts) >= 2) {
                        $action = $this->toCamelCase($parts[1]);
                    }
                }
            }
            // URL limpia: /config/provincia-ciudad/ciudades → tab=ciudades
            if (count($parts) >= 3 && ($parts[1] ?? '') === 'provincia-ciudad' && in_array($parts[2], ['provincias', 'ciudades'], true)) {
                $_GET['tab'] = $parts[2];
            }
            // /auth/confirmUser/email/token → email y token para recuperar contraseña
            if (count($parts) >= 4 && ($parts[1] ?? '') === 'confirmUser') {
                $_GET['email'] = urldecode($parts[2] ?? '');
                $_GET['token'] = $parts[3] ?? '';
            }
            // /registro/index/email/token → email y token para completar registro de nuevo usuario
            if (count($parts) >= 4 && ($parts[0] ?? '') === 'registro' && ($parts[1] ?? '') === 'index') {
                $_GET['email'] = urldecode($parts[2] ?? '');
                $_GET['token'] = $parts[3] ?? '';
            }
            // /solicitud-firma/* → formulario público de firma electrónica (sin auth)
            if (($parts[0] ?? '') === 'solicitud-firma') {
                $controller = 'SolicitudFirma';
                $part1      = $parts[1] ?? '';
                // Sub-rutas AJAX: /solicitud-firma/ciudades y /solicitud-firma/sri
                if (in_array($part1, ['ciudades', 'sri'], true)) {
                    $action = $part1;
                } elseif (!empty($part1) && ($parts[2] ?? '') === 'enviar') {
                    // /solicitud-firma/{token}/enviar → POST del formulario
                    $action          = 'enviar';
                    $_GET['token']   = $part1;
                } elseif (!empty($part1)) {
                    // /solicitud-firma/{token} → mostrar formulario
                    $action          = 'index';
                    $_GET['token']   = $part1;
                } else {
                    $action = 'index';
                }
            }

            // /aceptar-documentos/{token}[/aceptar] → aceptación pública de los
            // documentos legales (acuerdo de datos + contrato de uso), sin auth
            if (($parts[0] ?? '') === 'aceptar-documentos') {
                $controller    = 'AceptacionDocumentos';
                $_GET['token'] = $parts[1] ?? ($_GET['token'] ?? '');
                $action        = (($parts[2] ?? '') === 'aceptar') ? 'aceptar' : 'index';
            }

            // /aprobar-carga-inventario/{token}[/aprobar|/rechazar] → aprobación pública por token (sin auth)
            if (($parts[0] ?? '') === 'aprobar-carga-inventario') {
                $controller    = 'CargasInventarioAprobacion';
                $_GET['token'] = $parts[1] ?? ($_GET['token'] ?? '');
                $sub           = $parts[2] ?? '';
                $action        = in_array($sub, ['aprobar', 'rechazar'], true) ? $sub : 'index';
            }

            // /aprobar-importacion/{token}[/aprobar|/rechazar] → aprobación pública por token (sin auth)
            if (($parts[0] ?? '') === 'aprobar-importacion') {
                $controller    = 'ImportacionesAprobacion';
                $_GET['token'] = $parts[1] ?? ($_GET['token'] ?? '');
                $sub           = $parts[2] ?? '';
                $action        = in_array($sub, ['aprobar', 'rechazar'], true) ? $sub : 'index';
            }

            // /aprobar-transferencia/{token}[/aprobar|/rechazar] → aprobación pública por token (sin auth)
            if (($parts[0] ?? '') === 'aprobar-transferencia') {
                $controller    = 'TransferenciasAprobacion';
                $_GET['token'] = $parts[1] ?? ($_GET['token'] ?? '');
                $sub           = $parts[2] ?? '';
                $action        = in_array($sub, ['aprobar', 'rechazar'], true) ? $sub : 'index';
            }

            // /factura-express/* → formulario público QR (sin auth)
            if (($parts[0] ?? '') === 'factura-express') {
                $controller = 'FacturaExpressPublico';
                $part1      = $parts[1] ?? '';
                if (!empty($part1) && ($parts[2] ?? '') === 'enviar') {
                    // /factura-express/{token}/enviar → POST del formulario
                    $action        = 'enviar';
                    $_GET['token'] = $part1;
                } elseif (!empty($part1) && ($parts[2] ?? '') === 'estado') {
                    // /factura-express/{token}/estado → consulta de estado por el cliente
                    $action        = 'estado';
                    $_GET['token'] = $part1;
                } elseif (!empty($part1) && ($parts[2] ?? '') === 'sri') {
                    // /factura-express/{token}/sri → AJAX consulta SRI por identificación
                    $action        = 'consultarSri';
                    $_GET['token'] = $part1;
                } elseif (!empty($part1)) {
                    // /factura-express/{token} → mostrar formulario
                    $action        = 'index';
                    $_GET['token'] = $part1;
                } else {
                    $action = 'index';
                }
            }

            // /pago/{token} → página pública de pago con tarjeta (sin auth)
            if (($parts[0] ?? '') === 'pago') {
                $controller      = 'Payphone';
                $action          = 'pago';
                $_GET['token']   = $parts[1] ?? '';
            }

            // /payphone/* → retorno de pagos Payphone (sin auth)
            if (($parts[0] ?? '') === 'payphone') {
                $controller = 'Payphone';
                $sub        = $parts[1] ?? 'retorno';
                $accionesValidas = ['retorno', 'cancelacion', 'cajita-retorno'];
                $action = in_array($sub, $accionesValidas, true) ? $this->toCamelCase($sub) : 'retorno';
            }

            // /reservas/{slug}/* → portal público de reserva de citas (sin auth)
            if (($parts[0] ?? '') === 'reservas') {
                $controller = 'Reservas';
                $slug       = $parts[1] ?? '';
                $sub        = $parts[2] ?? '';
                $_GET['slug'] = $slug;
                $accionesAjax = ['disponibilidad', 'verificar-cliente', 'reservar', 'confirmacion'];
                if (!empty($slug) && in_array($sub, $accionesAjax, true)) {
                    $action = $this->toCamelCase($sub);
                } elseif (!empty($slug)) {
                    $action = 'index';
                } else {
                    $action = 'index';
                }
            }
        }

        return [
            'controller' => $this->sanitizeController($controller),
            'action' => $this->sanitizeAction($action),
        ];
    }

    /**
     * Algunos Nginx dejan REQUEST_URI como /index.php/auth/login; sin esto el router
     * interpreta mal el controlador (p. ej. "Index" en lugar de "Auth").
     */
    private function normalizePathForRouting(string $path): string
    {
        if (str_starts_with($path, '/index.php')) {
            $rest = substr($path, strlen('/index.php'));
            if ($rest === '' || $rest === false) {
                return '/';
            }
            return str_starts_with($rest, '/') ? $rest : '/' . $rest;
        }
        return $path;
    }

    private function sanitizeController(string $name): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_\\\\]/', '', $name) ?: 'Empresa';
        if (str_contains($name, '\\')) {
            return $name; // Ya viene con formato modulos\Nombre
        }
        return ucfirst($name);
    }

    private function sanitizeAction(string $name): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $name) ?: 'index';
        return $this->toCamelCase($name);
    }

    private function toCamelCase(string $str): string
    {
        if (str_contains($str, '-') || str_contains($str, '_')) {
            $str = str_replace(['-', '_'], ' ', strtolower($str));
            return lcfirst(str_replace(' ', '', ucwords($str)));
        }
        return $str;
    }

    public function url(string $controller, string $action = 'index', array $params = []): string
    {
        $url = $this->basePath . '?controller=' . urlencode($controller) . '&action=' . urlencode($action);
        foreach ($params as $k => $v) {
            $url .= '&' . urlencode($k) . '=' . urlencode((string) $v);
        }
        return $url;
    }
}
