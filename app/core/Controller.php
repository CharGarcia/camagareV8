<?php
/**
 * Controlador base
 */

declare(strict_types=1);

namespace App\core;

abstract class Controller
{
    protected \mysqli $db;
    protected array $config;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->config = require MVC_CONFIG . '/app.php';
    }

    protected function view(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $viewPath = MVC_APP . '/views/' . str_replace('.', '/', $view) . '.php';
        if (file_exists($viewPath)) {
            require $viewPath;
        } else {
            throw new \RuntimeException("Vista no encontrada: {$view}");
        }
    }

    protected function getLayoutData(): array
    {
        $empresas = [];
        $menuModulos = [];
        if (isset($_SESSION['id_usuario'])) {
            $model = new \App\models\Empresa();
            $empresas = $model->getEmpresasAsignadas((int) $_SESSION['id_usuario']);
            $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
            if ($idEmpresa > 0) {
                try {
                    $menuModel = new \App\models\ModuloMenu();
                    $nivel = (int) ($_SESSION['nivel'] ?? 1);
                    $menuModulos = $menuModel->getModulosConSubmodulos(
                        (int) $_SESSION['id_usuario'],
                        $idEmpresa,
                        $nivel
                    );
                } catch (\Throwable $e) {
                    $menuModulos = [];
                }
            }
        }
        return [
            'empresas' => $empresas,
            'nombre' => $_SESSION['nombre'] ?? '',
            'app_name' => $this->config['name'] ?? 'CaMaGaRe',
            'menuModulos' => $menuModulos,
        ];
    }

    protected function viewWithLayout(string $layout, string $view, array $data = []): void
    {
        $data = array_merge($this->getLayoutData(), $data);
        extract($data, EXTR_SKIP);
        ob_start();
        $viewPath = MVC_APP . '/views/' . str_replace('.', '/', $view) . '.php';
        if (file_exists($viewPath)) {
            require $viewPath;
        } else {
            throw new \RuntimeException("Vista no encontrada: {$view}");
        }
        $contenido = ob_get_clean();
        $layoutPath = MVC_APP . '/views/' . str_replace('.', '/', $layout) . '.php';
        if (file_exists($layoutPath)) {
            require $layoutPath;
        } else {
            throw new \RuntimeException("Layout no encontrado: {$layout}");
        }
    }

    protected function redirect(string $url, int $code = 302): never
    {
        header('Location: ' . $url, true, $code);
        exit;
    }

    protected function requireAuth(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['id_usuario'])) {
            $this->redirect('/sistema/');
        }
    }

    protected function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
