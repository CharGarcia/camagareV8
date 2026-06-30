<?php
/**
 * Controlador Home - Página principal
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\Empresa;
use App\Traits\PermisoModuloTrait;

class HomeController extends Controller
{
    use PermisoModuloTrait;

    public function index(): void
    {
        $this->requireAuth();

        if (empty($_SESSION['id_empresa'])) {
            $model = new Empresa();
            try {
                $empresas = $model->getEmpresasAsignadas((int) $_SESSION['id_usuario']);
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

        // El dashboard es un módulo (modulos/dashboard). Al ingresar se redirige
        // allí cuando el usuario puede verlo: Nivel 3 siempre; los demás solo si
        // tienen el módulo asignado (permiso de lectura). Sin empresa activa o sin
        // permiso, se queda en la bienvenida.
        if ($idEmpresa > 0 && !$sinEmpresa) {
            $puedeDashboard = $nivel >= 3
                || !empty($this->permisosModuloPorRuta('modulos/dashboard')['ver']);
            if ($puedeDashboard) {
                $this->redirect(rtrim(BASE_URL, '/') . '/modulos/dashboard');
                return;
            }
        }

        $this->viewWithLayout('layouts.main', 'home.index', [
            'titulo' => 'Inicio',
            'sinEmpresaSuperAdmin' => $sinEmpresa,
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
