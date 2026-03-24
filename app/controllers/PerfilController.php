<?php
/**
 * Controlador Perfil - Editar datos del usuario actual
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\Usuario;

class PerfilController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $idUsuario = (int) $_SESSION['id_usuario'];

        $model = new Usuario();
        $usuario = $model->getPerfil($idUsuario);
        if (!$usuario) {
            $this->redirect(BASE_URL . '/home/index');
        }

        $this->viewWithLayout('layouts.main', 'perfil.index', [
            'titulo' => 'Mi perfil',
            'usuario' => $usuario,
            'error' => $_SESSION['perfil_error'] ?? null,
            'exito' => $_SESSION['perfil_exito'] ?? null,
        ]);
        unset($_SESSION['perfil_error'], $_SESSION['perfil_exito']);
    }

    public function guardar(): void
    {
        $this->requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . '/perfil');
        }

        $idUsuario = (int) $_SESSION['id_usuario'];
        $nombre = trim($_POST['nombre'] ?? '');
        $cedula = trim($_POST['cedula'] ?? '');
        $mail = trim($_POST['mail'] ?? '');

        $model = new Usuario();
        try {
            $model->actualizarPerfil($idUsuario, $nombre, $cedula, $mail);
            $_SESSION['perfil_exito'] = 'Datos actualizados correctamente.';
            $_SESSION['nombre'] = $nombre;
        } catch (\InvalidArgumentException $e) {
            $_SESSION['perfil_error'] = $e->getMessage();
        } catch (\Throwable $e) {
            $_SESSION['perfil_error'] = 'Error al actualizar: ' . $e->getMessage();
        }

        $this->redirect(BASE_URL . '/perfil');
    }
}
