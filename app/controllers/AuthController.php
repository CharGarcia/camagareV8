<?php
/**
 * Controlador Auth - Login y recuperación de contraseña
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\Usuario;
use App\Services\SesionActivaService;

class AuthController extends Controller
{
    public function index(): void
    {
        $this->applyLoginDebugMode();
        if (isset($_SESSION['id_usuario'])) {
            $this->redirect(BASE_URL . '/home/index');           
        }
        $this->view('auth.login', ['error' => $_GET['error'] ?? null]);
    }

    public function login(): void
    {
        $this->applyLoginDebugMode();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . '/');
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $cedula     = trim($_POST['cedula'] ?? '');
        $password   = trim($_POST['password'] ?? '');
        $forceLogin = !empty($_POST['force_login']); // El usuario confirmó cerrar la sesión anterior
        $isAjax     = !empty($_POST['ajax_login']);   // Request desde fetch (JS)

        if ($cedula === '' || $password === '') {
            if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['error' => 'credenciales', 'msg' => 'Usuario o contraseña incorrectos.']); exit; }
            $this->redirect(BASE_URL . '/?error=1');
        }

        $model = new Usuario();
        $user  = $model->validaLogin($cedula, $password);

        if (!$user) {
            if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['error' => 'credenciales', 'msg' => 'Usuario o contraseña incorrectos.']); exit; }
            $this->redirect(BASE_URL . '/?error=1');
        }

        // --- Control de sesiones concurrentes ---
        $sesionSvc = new SesionActivaService();
        $sesionActiva = $sesionSvc->obtenerSesionActiva((int) $user['id']);

        if ($sesionActiva && !$forceLogin) {
            // Existe una sesión activa en otro dispositivo; notificar al cliente vía JSON
            $ultimaActividad = $sesionActiva['ultima_actividad'] ?? $sesionActiva['created_at'] ?? '';
            // Formatear fecha
            if ($ultimaActividad) {
                $dt = new \DateTime($ultimaActividad);
                $ultimaActividad = $dt->format('d-m-Y H:i:s');
            }
            header('Content-Type: application/json');
            echo json_encode([
                'sesion_activa'    => true,
                'ip'               => $sesionActiva['ip'] ?? 'desconocida',
                'ultima_actividad' => $ultimaActividad,
                'cedula'           => $cedula,
                'password_hash'    => '', // No enviamos la clave; el frontend la reenvía
            ]);
            exit;
        }

        // Si force_login o no había sesión activa: proceder con el login normal
        $_SESSION['nivel']      = $user['nivel'];
        $_SESSION['nombre']     = $user['nombre'];
        $_SESSION['id_usuario'] = $user['id'];

        $empresasLogin = $model->getEmpresasAsignadasParaLogin($_SESSION['id_usuario']);
        $numEmpresas   = (int) ($empresasLogin['numrows'] ?? 0);

        if ($numEmpresas === 0) {
            // Solo SuperAdmin puede entrar sin empresa (crear la primera en Configuración → Empresas)
            if ((int) $user['nivel'] !== 3) {
                unset($_SESSION['id_usuario'], $_SESSION['nivel'], $_SESSION['nombre']);
                if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['error' => 'sin_empresa', 'msg' => 'El usuario no tiene empresas asignadas.']); exit; }
                $this->redirect(BASE_URL . '/?error=2');
            }
            unset($_SESSION['id_empresa'], $_SESSION['ruc_empresa']);
        } else {
            $favId = (int) ($user['id_empresa_favorita'] ?? 0);
            $emp   = null;
            if ($favId > 0) {
                $emp = $model->getEmpresaAsignadaEspecifica((int) $user['id'], $favId);
            }

            if (!$emp) {
                $emp = ($numEmpresas === 1)
                    ? ['id_empresa' => $empresasLogin['id_empresa'], 'ruc_empresa' => $empresasLogin['ruc_empresa']]
                    : $model->getPrimeraEmpresaAsignada((int) $user['id']);
            }

            if ($emp) {
                $_SESSION['id_empresa']   = $emp['id_empresa'];
                $_SESSION['ruc_empresa']  = $emp['ruc_empresa'];
            }
        }

        session_regenerate_id(true);

        // Registrar nueva sesión activa (cierra la anterior si existe)
        $token = $sesionSvc->iniciarSesion((int) $user['id']);
        $_SESSION['session_token'] = $token;

        // Super admin sin ninguna empresa: ir directo a crear la primera (configuración desde cero)
        if ((int) $user['nivel'] === 3 && $numEmpresas === 0) {
            $_SESSION['empresas_msg'] = [
                'info',
                'Bienvenido. Cree su primera empresa aquí; podrá completar datos y asignaciones después. Luego use el menú Configuración para el resto del sistema.',
            ];
            if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['ok' => true, 'redirect' => BASE_URL . '/config/empresas-sistema']); exit; }
            $this->redirect(BASE_URL . '/config/empresas-sistema');
        }

        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['ok' => true, 'redirect' => BASE_URL . '/home/index']); exit; }
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

    /**
     * Endpoint AJAX para verificar si la sesión sigue activa.
     * El frontend hace polling cada X segundos para detectar si fue desplazado.
     * GET/POST /auth/verificar-sesion
     */
    public function verificarSesion(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        header('Content-Type: application/json');

        if (!isset($_SESSION['id_usuario'])) {
            echo json_encode(['activa' => false, 'razon' => 'no_sesion']);
            exit;
        }

        if (empty($_SESSION['session_token'])) {
            // Sesión PHP activa pero sin token (usuario que inició sesión antes de esta feature)
            echo json_encode(['activa' => true]);
            exit;
        }

        try {
            $sesionSvc = new SesionActivaService();
            $valido = $sesionSvc->validarToken($_SESSION['session_token']);
            echo json_encode(['activa' => $valido, 'razon' => $valido ? '' : 'desplazado']);
        } catch (\Throwable $e) {
            // Si hay error de BD, no cerrar sesión
            echo json_encode(['activa' => true]);
        }
        exit;
    }

    public function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Desactivar el token en BD antes de destruir la sesión
        if (!empty($_SESSION['session_token'])) {
            try {
                $sesionSvc = new SesionActivaService();
                $sesionSvc->cerrarSesion($_SESSION['session_token']);
            } catch (\Throwable $e) {
                // No interrumpir el logout si falla la BD
            }
        }

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

    /**
     * Si config show_login_errors es true, muestra notices/warnings en pantalla (solo depuración).
     * Desactivar en producción cuando termine.
     */
    private function applyLoginDebugMode(): void
    {
        if (empty($this->config['show_login_errors'])) {
            return;
        }
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
        error_reporting(E_ALL);
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
