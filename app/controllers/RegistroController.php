<?php
/**
 * Controlador Registro - Finalizar el registro por invitación del usuario
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\Usuario;

class RegistroController extends Controller
{
    /**
     * Muestra el formulario de finalización de registro.
     * URL: /registro/index/email/token
     */
    public function index(): void
    {
        $email = trim($_GET['email'] ?? '');
        $token = trim($_GET['token'] ?? '');

        if ($email === '' || $token === '') {
            $this->view('registro.error', ['mensaje' => 'El enlace de invitación no es válido o ha expirado.']);
            return;
        }

        $model = new Usuario();
        $usuario = $model->getUsuarioPorCorreoYToken($email, $token);

        if (!$usuario) {
            $this->view('registro.error', ['mensaje' => 'El enlace de invitación no es válido o ya ha sido utilizado.']);
            return;
        }

        $this->view('registro.index', [
            'titulo' => 'Completar Registro',
            'email' => $email,
            'token' => $token,
            'nombre' => ''
        ]);
    }

    /**
     * Procesa la finalización del registro (POST).
     */
    public function completar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . '/');
        }

        $email = trim($_POST['email'] ?? '');
        $token = trim($_POST['token'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $cedula = trim($_POST['cedula'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmar = $_POST['confirmar_password'] ?? '';
        $telefono = trim($_POST['telefono'] ?? '');

        if ($email === '' || $token === '') {
            $this->json(['ok' => false, 'error' => 'Datos de invitación inválidos.']);
        }

        $model = new Usuario();
        $usuario = $model->getUsuarioPorCorreoYToken($email, $token);

        if (!$usuario) {
            $this->json(['ok' => false, 'error' => 'La invitación ya no es válida.']);
        }

        if ($nombre === '' || $cedula === '' || $password === '') {
            $this->json(['ok' => false, 'error' => 'Todos los campos marcados con (*) son obligatorios.']);
        }

        if (strlen($password) < 4) {
            $this->json(['ok' => false, 'error' => 'La contraseña debe tener al menos 4 caracteres.']);
        }

        if ($password !== $confirmar) {
            $this->json(['ok' => false, 'error' => 'Las contraseñas no coinciden.']);
        }

        try {
            $id = (int) $usuario['id'];
            if ($model->completarRegistro($id, $nombre, $cedula, $password, $telefono, $token)) {
                $this->json(['ok' => true, 'msg' => 'Registro completado con éxito. Ahora puede iniciar sesión.']);
            } else {
                $this->json(['ok' => false, 'error' => 'No se pudo completar el registro. Intente nuevamente.']);
            }
        } catch (\InvalidArgumentException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Error inesperado: ' . $e->getMessage()]);
        }
    }
}
