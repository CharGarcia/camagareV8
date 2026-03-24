<?php
/**
 * Controlador Auth - Login y recuperación de contraseña
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\Usuario;

class AuthController extends Controller
{
    public function index(): void
    {
        if (isset($_SESSION['id_usuario'])) {
            $this->redirect(BASE_URL . '/home/index');
        }
        $this->view('auth.login', ['error' => $_GET['error'] ?? null]);
    }

    public function login(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . '/');
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $cedula = trim($_POST['cedula'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if ($cedula === '' || $password === '') {
            $this->redirect(BASE_URL . '/?error=1');
        }

        $model = new Usuario();
        $user = $model->validaLogin($cedula, $password);

        if (!$user) {
            $this->redirect(BASE_URL . '/?error=1');
        }

        $_SESSION['nivel'] = $user['nivel'];
        $_SESSION['nombre'] = $user['nombre'];
        $_SESSION['id_usuario'] = $user['id'];

        $empresas = $model->getEmpresasAsignadasParaLogin($_SESSION['id_usuario']);
        if (($empresas['numrows'] ?? 0) == 0) {
            unset($_SESSION['id_usuario']);
            $this->redirect(BASE_URL . '/?error=2');
        }

        $emp = ($empresas['numrows'] == 1)
            ? ['id_empresa' => $empresas['id_empresa'], 'ruc_empresa' => $empresas['ruc_empresa']]
            : $model->getPrimeraEmpresaAsignada($_SESSION['id_usuario']);

        if ($emp) {
            $_SESSION['id_empresa'] = $emp['id_empresa'];
            $_SESSION['ruc_empresa'] = $emp['ruc_empresa'];
        }

        session_regenerate_id(true);
        session_write_close();

        $this->redirect(BASE_URL . '/home/index');
    }

    /**
     * Cambiar contraseña del usuario actual.
     * Soporta contraseña actual en MD5 o bcrypt; guarda la nueva en bcrypt.
     */
    public function cambiarClave(): void
    {
        $this->requireAuth();
        $idUsuario = (int) $_SESSION['id_usuario'];

        $error = null;
        $exito = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $claveActual = trim($_POST['clave_actual'] ?? '');
            $nuevaClave = trim($_POST['nueva_clave'] ?? '');
            $repetirClave = trim($_POST['repetir_clave'] ?? '');

            if (strlen($nuevaClave) < 4) {
                $error = 'La nueva contraseña debe tener al menos 4 caracteres.';
            } elseif ($nuevaClave !== $repetirClave) {
                $error = 'Las contraseñas nuevas no coinciden.';
            } else {
                $model = new Usuario();
                if ($model->cambiarPassword($idUsuario, $claveActual, $nuevaClave)) {
                    $exito = true;
                } else {
                    $error = 'La contraseña actual no es correcta.';
                }
            }
        }

        $this->viewWithLayout('layouts.main', 'auth.cambiarClave', [
            'titulo' => 'Cambiar contraseña',
            'error' => $error,
            'exito' => $exito,
        ]);
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
        $this->redirect(BASE_URL . '/');
    }

    /**
     * Confirmar recuperación de contraseña.
     * URL: /auth/confirmUser/email/token
     * Muestra formulario para nueva contraseña; POST la actualiza.
     */
    public function confirmUser(): void
    {
        $email = trim($_GET['email'] ?? $_POST['email'] ?? '');
        $token = trim($_GET['token'] ?? $_POST['token'] ?? '');

        if ($email === '' || $token === '') {
            $this->view('auth.confirmUser', ['error' => 'Enlace inválido o expirado.', 'email' => '', 'token' => '']);
            return;
        }

        $model = new Usuario();
        $usuario = $model->getUsuarioPorCorreoYToken($email, $token);

        if (!$usuario) {
            $this->view('auth.confirmUser', ['error' => 'Enlace inválido o expirado.', 'email' => $email, 'token' => $token]);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $password = trim($_POST['password'] ?? '');
            $confirmar = trim($_POST['confirmar_password'] ?? '');
            if (strlen($password) < 4) {
                $this->view('auth.confirmUser', ['error' => 'La contraseña debe tener al menos 4 caracteres.', 'email' => $email, 'token' => $token]);
                return;
            }
            if ($password !== $confirmar) {
                $this->view('auth.confirmUser', ['error' => 'Las contraseñas no coinciden.', 'email' => $email, 'token' => $token]);
                return;
            }
            if ($model->actualizarPasswordPorRecuperacion((int) $usuario['id'], $token, $password)) {
                $this->view('auth.confirmUser', ['exito' => true, 'email' => $email]);
                return;
            }
            $this->view('auth.confirmUser', ['error' => 'No se pudo actualizar la contraseña.', 'email' => $email, 'token' => $token]);
            return;
        }

        $this->view('auth.confirmUser', ['email' => $email, 'token' => $token]);
    }

    /**
     * Solicitar recuperación de contraseña (AJAX).
     * Valida que el correo exista y esté activo. Retorna id_user y nombre para el siguiente paso.
     * POST: correo
     */
    public function solicitarRecuperar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['status' => 'error', 'message' => 'Método no permitido'], 405);
        }

        $correo = strtolower(trim($_POST['correo'] ?? ''));
        if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $this->json(['status' => 'error', 'message' => 'Correo no válido.']);
        }

        $model = new Usuario();
        $usuario = $model->getUsuarioActivoPorCorreo($correo);

        if (!$usuario) {
            $this->json(['status' => 'error', 'message' => 'El usuario no está registrado en el sistema.']);
        }

        $this->json([
            'status' => 'success',
            'id_user' => (int) $usuario['id'],
            'nombre' => $usuario['nombre'],
            'message' => "Se ha enviado un correo a {$correo} para restablecer su cuenta.",
        ]);
    }

    /**
     * Enviar correo de recuperación (AJAX).
     * Requiere configuración en correos_config con codigo recuperar_password.
     * POST: id_user, nombre, correo
     */
    public function enviarCorreoRecuperar(): void
    {
        if (ob_get_length()) ob_clean();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['ok' => false, 'error' => 'Método no permitido'], 405);
        }

        $idUser = (int) ($_POST['id_user'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $correo = strtolower(trim($_POST['correo'] ?? ''));

        if ($idUser <= 0 || $correo === '') {
            $this->json(['ok' => false, 'error' => 'Faltan parámetros.']);
        }

        $token = token_recuperar();
        $model = new Usuario();
        $model->actualizarToken($idUser, $token);

        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $urlRecovery = $scheme . '://' . $host . rtrim(BASE_URL, '/') . '/auth/confirmUser/' . urlencode($correo) . '/' . $token;

        $ok = enviar_correo_recuperar_clave($nombre, $correo, $urlRecovery);

        if ($ok) {
            try {
                $model->registrarRecuperacion($idUser, $nombre, $correo);
            } catch (\Throwable $e) {
                // El correo ya se envió; el registro es solo auditoría
            }
            $this->json(['ok' => true, 'msg' => 'Correo enviado correctamente']);
        } else {
            $err = $GLOBALS['LAST_EMAIL_ERROR'] ?? 'Error al enviar el correo.';
            $this->logEmailError('recuperar_password', $err);
            $this->json(['ok' => false, 'error' => $err]);
        }
    }

    private function logEmailError(string $contexto, string $mensaje): void
    {
        $dir = MVC_ROOT . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($dir . '/email_errors.log', date('Y-m-d H:i:s') . " [{$contexto}] {$mensaje}\n", FILE_APPEND);
    }
}
