<?php
/**
 * Controlador Empresa - Selección de empresa
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\Empresa;

class EmpresaController extends Controller
{
    public function index(): void
    {
        $this->menu();
    }

    public function menu(): void
    {
        $this->requireAuth();

        $model = new Empresa();
        $empresas = $model->getEmpresasAsignadas((int) $_SESSION['id_usuario']);

        $this->view('empresa.menu', [
            'empresas' => $empresas,
            'nombre' => $_SESSION['nombre'] ?? '',
        ]);
    }

    public function setEmpresa(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . '/home/index');
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $idUsuario = (int) ($_POST['id_usuario'] ?? 0);
        $idEmpresa = (int) ($_POST['id_empresa'] ?? 0);
        $rucEmpresa = trim($_POST['ruc_empresa'] ?? '');

        if ($idUsuario && $idEmpresa && $rucEmpresa !== '') {
            $_SESSION['id_usuario'] = $idUsuario;
            $_SESSION['id_empresa'] = $idEmpresa;
            $_SESSION['ruc_empresa'] = $rucEmpresa;
        }

        $this->redirect(BASE_URL . '/home/index');
    }
}
