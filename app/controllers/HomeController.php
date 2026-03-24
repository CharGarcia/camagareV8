<?php
/**
 * Controlador Home - Página principal
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\Empresa;

class HomeController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        if (empty($_SESSION['id_empresa'])) {
            $model = new Empresa();
            $empresas = $model->getEmpresasAsignadas((int) $_SESSION['id_usuario']);
            if (!empty($empresas)) {
                $primera = $empresas[0];
                $_SESSION['id_empresa'] = $primera['id_empresa'];
                $_SESSION['ruc_empresa'] = $primera['ruc'] ?? '';
            }
        }

        $nivel = (int) ($_SESSION['nivel'] ?? 1);
        $sinEmpresa = $nivel === 3 && empty($_SESSION['id_empresa']);

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
