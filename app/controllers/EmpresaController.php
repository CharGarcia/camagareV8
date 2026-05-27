<?php

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\Empresa;

class EmpresaController extends Controller
{
    /**
     * Establece la empresa activa en la sesión.
     * Llamado desde el selector de empresas en el navbar.
     */
    public function setEmpresa(): void
    {
        $this->requireAuth();
        
        $idEmpresa = (int) ($_POST['id_empresa'] ?? 0);
        $ruc = trim($_POST['ruc_empresa'] ?? '');
        
        if ($idEmpresa > 0) {
            // Verificar que el usuario tenga acceso a esta empresa (opcional pero recomendado)
            $model = new Empresa();
            $idUsuario = (int) $_SESSION['id_usuario'];
            $nivel = (int) $_SESSION['nivel'];
            
            // Si no es superadmin, verificar asignación
            if ($nivel < 3) {
                $asignadas = $model->getEmpresasAsignadas($idUsuario);
                $ids = array_column($asignadas, 'id_empresa');
                if (!in_array($idEmpresa, array_map('intval', $ids))) {
                    $_SESSION['navbar_error'] = 'No tienes permiso para acceder a esta empresa.';
                    $this->redirect($_SERVER['HTTP_REFERER'] ?? (BASE_URL . '/home/index'));
                }
            }
            
            // Establecer en sesión
            $_SESSION['id_empresa'] = $idEmpresa;
            $_SESSION['ruc_empresa'] = $ruc;
            
            // Redirigir a la misma página donde estaba o al home
            $redir = $_SERVER['HTTP_REFERER'] ?? (BASE_URL . '/home/index');
            $this->redirect($redir);
        } else {
            $this->redirect(BASE_URL . '/home/index');
        }
    }
}
