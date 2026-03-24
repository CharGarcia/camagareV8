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
        date_default_timezone_set($this->config['timezone'] ?? 'UTC');

        if (session_status() === PHP_SESSION_NONE) {
            session_name($this->config['session']['name'] ?? 'PHPSESSID');
            session_start();
        }

        $dispatch = $this->router->dispatch();
        $controller = $dispatch['controller'];
        $action = $dispatch['action'];

        // Si no hay sesión y no es Auth, mostrar login
        if (!isset($_SESSION['id_usuario']) && $controller !== 'Auth') {
            header('Location: /sistema/public/');
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
        http_response_code($code);
        if ($this->config['debug'] ?? false) {
            echo "<h1>Error {$code}</h1><p>" . htmlspecialchars($message) . "</p>";
        } else {
            $viewPath = MVC_APP . '/views/errors/' . $code . '.php';
            if (file_exists($viewPath)) {
                require $viewPath;
            } else {
                echo "<h1>Error {$code}</h1>";
            }
        }
    }
}
