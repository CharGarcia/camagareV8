<?php
/**
 * Controlador UsuariosSistema - Gestión de usuarios del sistema
 * Tabla usuarios. Muestra datos del usuario y empresas asignadas.
 * Usa el modal de crear usuario existente (nombre + correo).
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\Usuario;

class UsuariosSistemaController extends Controller
{
    private Usuario $model;
    private const BASE_PATH = '/config/usuarios-sistema';

    public function __construct()
    {
        parent::__construct();
        $this->model = new Usuario();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        $idActual = (int) ($_SESSION['id_usuario'] ?? 0);
        $nivel = (int) ($_SESSION['nivel'] ?? 1);
        $buscar = trim($_GET['b'] ?? $_POST['b'] ?? $_GET['buscar'] ?? $_POST['buscar'] ?? '');
        $page = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? 'nombre');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? 'asc'));
        $perPage = 20;

        if (!in_array($ordenCol, Usuario::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'nombre';
        }
        if ($ordenDir !== 'ASC' && $ordenDir !== 'DESC') {
            $ordenDir = 'ASC';
        }

        $result = $this->model->getTodosParaListado($idActual, $nivel, $buscar, $page, $perPage, $ordenCol, $ordenDir);
        $rows = $result['rows'];
        $total = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $msg = $_SESSION['usuarios_msg'] ?? null;
        unset($_SESSION['usuarios_msg']);

        // Límite de usuarios para admins
        $limiteUsuarios = null;
        $idEmpresaActual = (int) ($_SESSION['id_empresa'] ?? 0);
        if ($idEmpresaActual > 0) {
            $modelAsignada = new \App\models\EmpresaAsignada();
            $limiteUsuarios = $modelAsignada->getLimiteUsuariosEmpresa($idEmpresaActual);
        }

        $this->viewWithLayout('layouts.main', 'usuariosSistema.index', [
            'titulo' => 'Usuarios del sistema',
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'perPage' => $perPage,
            'buscar' => $buscar,
            'nivel' => $nivel,
            'ordenCol' => $ordenCol,
            'ordenDir' => $ordenDir,
            'msg' => $msg,
            'limiteUsuarios' => $limiteUsuarios,
        ]);
    }

    public function update(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $id = (int) ($_POST['id'] ?? 0);
        $mail = trim($_POST['mail'] ?? '');
        $nivel = (int) ($_POST['nivel'] ?? 1);
        $estado = !empty($_POST['estado']);

        if ($id <= 0) {
            $this->json(['ok' => false, 'msg' => 'ID inválido.']);
        }

        $nivelActual = (int) ($_SESSION['nivel'] ?? 1);
        if ($nivelActual < 3 && $nivel > 1) {
            $this->json(['ok' => false, 'msg' => 'Solo el super administrador puede asignar nivel de administrador.']);
        }

        try {
            if ($this->model->actualizar($id, $mail, $nivel, $estado ? 1 : 0)) {
                $this->json(['ok' => true, 'msg' => 'Usuario actualizado correctamente.']);
            } else {
                $this->json(['ok' => false, 'msg' => 'No se realizaron cambios o hubo un error al actualizar.']);
            }
        } catch (\InvalidArgumentException $e) {
            $this->json(['ok' => false, 'msg' => $e->getMessage()]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
        }
    }

    public function eliminar(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $id = (int) ($_POST['id'] ?? 0);
        $idActual = (int) ($_SESSION['id_usuario'] ?? 0);

        if ($id <= 0) {
            $this->json(['ok' => false, 'msg' => 'ID de usuario no válido.']);
        }

        if ($id === $idActual) {
            $this->json(['ok' => false, 'msg' => 'No puede eliminarse a sí mismo.']);
        }

        try {
            if ($this->model->eliminar($id, $idActual)) {
                $this->json(['ok' => true, 'msg' => 'Usuario eliminado correctamente.']);
            } else {
                $this->json(['ok' => false, 'msg' => 'No se pudo eliminar el usuario.']);
            }
        } catch (\RuntimeException $e) {
            $this->json(['ok' => false, 'msg' => $e->getMessage()]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'msg' => 'Error inesperado: ' . $e->getMessage()]);
        }
    }

    public function reenviarInvitacion(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['ok' => false, 'msg' => 'Método no permitido']);
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->json(['ok' => false, 'msg' => 'ID inválido.']);
        }

        $row = $this->model->getDatosInvitacion($id);

        if (!$row) {
            $this->json(['ok' => false, 'msg' => 'Usuario no encontrado.']);
        }

        $token = $row['token'] ?? '';
        if ($token === '') {
            $this->json(['ok' => false, 'msg' => 'El usuario ya completó su registro o no tiene una invitación pendiente.']);
        }

        $nombre = $row['nombre'];
        $correo = $row['mail'];

        try {
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $urlEmail = urlencode($correo);
            $urlInvite = $scheme . '://' . $host . rtrim(BASE_URL, '/') . '/registro/index/' . $urlEmail . '/' . $token;

            require_once MVC_APP . '/helpers/mail.php';
            if (enviar_correo_nuevo_usuario($nombre, $correo, $urlInvite)) {
                $this->json(['ok' => true, 'msg' => 'Invitación reenviada correctamente a ' . $correo]);
            } else {
                $err = $GLOBALS['LAST_EMAIL_ERROR'] ?? 'Error al enviar el correo.';
                $this->json(['ok' => false, 'msg' => $err]);
            }
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
        }
    }

    private function requireNivel(int $min): void
    {
        $nivel = (int) ($_SESSION['nivel'] ?? 0);
        if ($nivel < $min) {
            $_SESSION['usuarios_msg'] = ['danger', 'No tiene permisos.'];
            header('Location: ' . BASE_URL . '/config');
            exit;
        }
    }
}
