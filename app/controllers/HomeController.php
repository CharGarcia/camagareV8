<?php
/**
 * Controlador Home - Página principal
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\Empresa;
use App\models\ModuloMenu;
use App\repositories\UsuarioPreferenciaRepository;
use App\Services\AccesosFrecuentesService;
use App\Services\UsuarioPreferenciaService;
use App\Traits\PermisoModuloTrait;

class HomeController extends Controller
{
    use PermisoModuloTrait;

    public function index(): void
    {
        $this->requireAuth();

        if (empty($_SESSION['id_empresa'])) {
            $model = new Empresa();
            $nivelUsuario = (int) ($_SESSION['nivel'] ?? 1);
            try {
                // Nivel 3 (superadmin): acceso total, no depende de empresa_asignada.
                $empresas = $nivelUsuario >= 3 ? $model->getTodasActivas() : $model->getEmpresasAsignadas((int) $_SESSION['id_usuario']);
            } catch (\Throwable $e) {
                $empresas = [];
            }
            if (!empty($empresas)) {
                $primera = $empresas[0];
                $_SESSION['id_empresa'] = $primera['id_empresa'];
                $_SESSION['ruc_empresa'] = $primera['ruc'] ?? '';
            }
        }

        $nivel      = (int) ($_SESSION['nivel'] ?? 1);
        $idEmpresa  = (int) ($_SESSION['id_empresa'] ?? 0);
        $sinEmpresa = $nivel === 3 && $idEmpresa <= 0;

        // Al ingresar se muestra esta pantalla con los submódulos que el usuario más
        // usa últimamente en la empresa activa (AccesosFrecuentesService). Si tiene
        // asignado el dashboard (Nivel 3 siempre; los demás con permiso de lectura),
        // su ícono va primero. Quien prefiera entrar directo al dashboard lo elige en
        // Personalizar menú → «Al ingresar mostrar» (preferencia 'inicio').
        // Sin accesos ni dashboard (usuario nuevo o tabla sin crear): la bienvenida.
        $accesos = [];
        $accesoDashboard = null;
        if ($idEmpresa > 0 && !$sinEmpresa) {
            $idUsuario = (int) $_SESSION['id_usuario'];
            $puedeDashboard = $nivel >= 3
                || !empty($this->permisosModuloPorRuta('modulos/dashboard')['ver']);

            $prefMenu = (new UsuarioPreferenciaService(new UsuarioPreferenciaRepository()))->obtenerMenuUsuario($idUsuario);
            if (($prefMenu['inicio'] ?? 'accesos') === 'dashboard' && $puedeDashboard) {
                $this->redirect(rtrim(BASE_URL, '/') . '/modulos/dashboard');
                return;
            }

            try {
                $menuModulos = (new ModuloMenu())->getModulosConSubmodulos($idUsuario, $idEmpresa, $nivel);
            } catch (\Throwable $e) {
                $menuModulos = [];
            }
            $servicio = new AccesosFrecuentesService();
            $accesos = $servicio->obtener($idUsuario, $idEmpresa, $menuModulos, 12);
            if ($puedeDashboard) {
                $accesoDashboard = $servicio->accesoDashboard($menuModulos);
            }
        }

        $this->viewWithLayout('layouts.main', 'home.index', [
            'titulo' => 'Inicio',
            'sinEmpresaSuperAdmin' => $sinEmpresa,
            'accesos' => $accesos,
            'accesoDashboard' => $accesoDashboard,
        ]);
    }

    /**
     * Módulos en proceso de migración a MVC.
     * Se muestra cuando el menú apunta a un módulo legacy que aún no tiene versión MVC.
     */
    public function moduloEnConstruccion(): void
    {
        $this->requireAuth();
        $this->viewWithLayout('layouts.main', 'home.moduloEnConstruccion', [
            'titulo' => 'Módulo en desarrollo',
        ]);
    }
}
