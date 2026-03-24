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

        $this->viewWithLayout('layouts.main', 'home.index', [
            'titulo' => 'Inicio',
        ]);
    }
}
