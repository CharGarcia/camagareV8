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
            $base = rtrim($this->basePath, '/');
            if ($uri !== false && $base !== '' && str_starts_with($uri, $base)) {
                $pathInfo = substr($uri, strlen($base)) ?: '/';
            }
        }
        if ($pathInfo !== '' && $pathInfo !== '/') {
            $parts = array_filter(explode('/', trim($pathInfo, '/')));
            $parts = array_values($parts);
            if (count($parts) >= 1) {
                $controller = ucfirst(strtolower($parts[0]));
            }
            if (count($parts) >= 2) {
                $action = $this->toCamelCase($parts[1]);
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
        }

        return [
            'controller' => $this->sanitizeController($controller),
            'action' => $this->sanitizeAction($action),
        ];
    }

    private function sanitizeController(string $name): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_]/', '', $name) ?: 'Empresa';
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
