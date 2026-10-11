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
        $tipoMenu = 'horizontal';
        $sidebarColapsado = false;
        if (isset($_SESSION['id_usuario'])) {
            $idUsuario = (int) $_SESSION['id_usuario'];
            $nivelUsuario = (int) ($_SESSION['nivel'] ?? 1);
            $model = new \App\models\Empresa();
            $usuarioModel = new \App\models\Usuario();

            try {
                // Nivel 3 (superadmin): acceso total, no depende de empresa_asignada.
                $empresas = $nivelUsuario >= 3 ? $model->getTodasActivas() : $model->getEmpresasAsignadas($idUsuario);
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
                    // Personalización del menú: es del USUARIO, igual en todas sus empresas
                    // (UsuarioPreferenciaService::obtenerMenuUsuario).
                    $menuUsuario = $this->leerMenuUsuario((int) $_SESSION['id_usuario']);
                    // Orden: si aún no lo guardó con el esquema por usuario, se respeta
                    // el que tenía antes en esta empresa (__orden_menu__ de 'navbar').
                    $orden = array_key_exists('orden', $menuUsuario)
                        ? $menuUsuario['orden']
                        : $this->leerOrdenMenuEmpresa((int) $_SESSION['id_usuario'], $idEmpresa);
                    $menuModulos = $this->aplicarOrdenPersonalizadoMenu($menuModulos, $orden);
                    if (($menuUsuario['tipo'] ?? '') === 'vertical') {
                        $tipoMenu = 'vertical';
                    }
                    $sidebarColapsado = !empty($menuUsuario['colapsado']);
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
            'tipoMenu' => $tipoMenu,
            'sidebarColapsado' => $sidebarColapsado,
        ];
    }

    /** Personalización del menú del usuario (tipo, orden, sidebar contraído). */
    private function leerMenuUsuario(int $idUsuario): array
    {
        try {
            $service = new \App\Services\UsuarioPreferenciaService(new \App\repositories\UsuarioPreferenciaRepository());
            return $service->obtenerMenuUsuario($idUsuario);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Orden del menú guardado antes por empresa (`__vista__.__orden_menu__` del
     * módulo 'navbar'). Solo se usa mientras el usuario no tenga su orden propio.
     */
    private function leerOrdenMenuEmpresa(int $idUsuario, int $idEmpresa): array
    {
        if ($idUsuario <= 0 || $idEmpresa <= 0) {
            return [];
        }
        try {
            $repo = new \App\repositories\UsuarioPreferenciaRepository();
            $orden = $repo->obtenerPreferencias($idUsuario, $idEmpresa, 'navbar')['__vista__']['__orden_menu__'] ?? [];
            return is_array($orden) ? $orden : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Reordena los módulos de primer nivel del navbar según el orden que el
     * usuario haya guardado (arrastrar y soltar) para la empresa activa.
     * Los módulos que aún no estén en la preferencia (nuevos, recién
     * asignados) se agregan al final en su orden original.
     */
    private function aplicarOrdenPersonalizadoMenu(array $menuModulos, $orden): array
    {
        if (empty($menuModulos) || !is_array($orden) || empty($orden)) {
            return $menuModulos;
        }

        $porId = [];
        foreach ($menuModulos as $mod) {
            $porId[(int) ($mod['id_modulo'] ?? 0)] = $mod;
        }

        $ordenados = [];
        foreach ($orden as $idMod) {
            $idMod = (int) $idMod;
            if (isset($porId[$idMod])) {
                $ordenados[] = $porId[$idMod];
                unset($porId[$idMod]);
            }
        }
        foreach ($porId as $mod) {
            $ordenados[] = $mod;
        }

        return $ordenados;
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
