<?php
/**
 * Controlador base
 */

declare(strict_types=1);

namespace App\core;

abstract class Controller
{
    protected \PDO $db;
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
        $idEmpresaFavorita = null;
        if (isset($_SESSION['id_usuario'])) {
            $idUsuario = (int) $_SESSION['id_usuario'];
            $model = new \App\models\Empresa();
            $usuarioModel = new \App\models\Usuario();

            try {
                $empresas = $model->getEmpresasAsignadas($idUsuario);
                // Si no hay empresas pero la sesión tiene id_empresa, obtener solo esa
                if (empty($empresas) && isset($_SESSION['id_empresa']) && (int)$_SESSION['id_empresa'] > 0) {
                    $empresa = $model->getPorId((int)$_SESSION['id_empresa']);
                    if ($empresa) {
                        // Normalizar: getPorId devuelve 'id', pero getEmpresasAsignadas devuelve 'id_empresa'
                        if (isset($empresa['id']) && !isset($empresa['id_empresa'])) {
                            $empresa['id_empresa'] = $empresa['id'];
                        }
                        $empresas = [$empresa];
                    }
                }
                $perfil = $usuarioModel->getPerfil($idUsuario);
                // getPerfil no traia id_empresa_favorita, voy a usar una consulta directa o actualizar getPerfil
                // Para ser rápido y seguro, consulto directo el campo:
                $resFav = $this->db->prepare("SELECT id_empresa_favorita FROM usuarios WHERE id = ?");
                $resFav->execute([$idUsuario]);
                $idEmpresaFavorita = $resFav->fetchColumn();
                $idEmpresaFavorita = $idEmpresaFavorita ? (int) $idEmpresaFavorita : null;
            } catch (\Throwable $e) {
                // Si falla, intentar obtener al menos la empresa de la sesión
                $empresas = [];
                if (isset($_SESSION['id_empresa']) && (int)$_SESSION['id_empresa'] > 0) {
                    try {
                        $empresa = $model->getPorId((int)$_SESSION['id_empresa']);
                        if ($empresa) {
                            // Normalizar la estructura
                            if (isset($empresa['id']) && !isset($empresa['id_empresa'])) {
                                $empresa['id_empresa'] = $empresa['id'];
                            }
                            $empresas = [$empresa];
                        }
                    } catch (\Throwable $e2) {
                        // Silenciar error fallback
                    }
                }
            }

            $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
            if ($idEmpresa > 0) {
// ... rest remains same
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
            'idEmpresaFavorita' => $idEmpresaFavorita,
        ];
    }

    protected function viewWithLayout(string $layout, string $view, array $data = []): void
    {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }
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
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                $this->json(['ok' => false, 'mensaje' => 'La sesión ha expirado. Por favor, recarga la página e inicia sesión nuevamente.'], 401);
            }
            $this->redirect(rtrim(BASE_URL ?? '', '/') . '/');
        }
    }

    protected function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_FLAGS);
        exit;
    }
}
