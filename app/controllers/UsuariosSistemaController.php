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
            $_SESSION['usuarios_msg'] = ['danger', 'ID inválido.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $nivelActual = (int) ($_SESSION['nivel'] ?? 1);
        if ($nivelActual < 3 && $nivel > 1) {
            $_SESSION['usuarios_msg'] = ['danger', 'Solo el super administrador puede asignar nivel de administrador.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        try {
            if ($this->model->actualizar($id, $mail, $nivel, $estado ? 1 : 0)) {
                $_SESSION['usuarios_msg'] = ['success', 'Usuario actualizado correctamente.'];
            } else {
                $_SESSION['usuarios_msg'] = ['danger', 'Error al actualizar.'];
            }
        } catch (\InvalidArgumentException $e) {
            $_SESSION['usuarios_msg'] = ['danger', $e->getMessage()];
        } catch (\Throwable $e) {
            $_SESSION['usuarios_msg'] = ['danger', 'Error: ' . $e->getMessage()];
        }

        $this->redirect(BASE_URL . self::BASE_PATH);
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
