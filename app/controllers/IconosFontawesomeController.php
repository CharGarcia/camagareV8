<?php
/**
 * Controlador IconosFontawesome - Gestión de iconos FontAwesome
 * Tabla iconos_fontawesome. Clic en fila para editar.
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\IconoFontawesome;

class IconosFontawesomeController extends Controller
{
    private IconoFontawesome $model;
    private const BASE_PATH = '/config/iconos-fontawesome';

    public function __construct()
    {
        parent::__construct();
        $this->model = new IconoFontawesome();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        $ordenCol = trim($_GET['sort'] ?? 'nombre_icono');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? 'asc'));
        $buscar = trim($_GET['b'] ?? $_GET['buscar'] ?? '');
        if (!in_array($ordenCol, IconoFontawesome::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'nombre_icono';
        }
        if ($ordenDir !== 'ASC' && $ordenDir !== 'DESC') {
            $ordenDir = 'ASC';
        }

        $rows = $this->model->getAll($ordenCol, $ordenDir, $buscar);

        $this->viewWithLayout('layouts.main', 'iconosFontawesome.index', [
            'titulo' => 'Iconos FontAwesome',
            'rows' => $rows,
            'ordenCol' => $ordenCol,
            'ordenDir' => $ordenDir,
            'buscar' => $buscar,
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
        $nombreIcono = trim($_POST['nombre_icono'] ?? '');

        if ($id <= 0) {
            $_SESSION['iconos_msg'] = ['danger', 'ID inválido.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($nombreIcono === '') {
            $_SESSION['iconos_msg'] = ['danger', 'El nombre del icono es obligatorio.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($this->model->actualizar($id, $nombreIcono)) {
            $_SESSION['iconos_msg'] = ['success', 'Icono actualizado correctamente.'];
        } else {
            $_SESSION['iconos_msg'] = ['danger', 'Error al actualizar.'];
        }

        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $nombreIcono = trim($_POST['nombre_icono'] ?? '');

        if ($nombreIcono === '') {
            $_SESSION['iconos_msg'] = ['danger', 'El nombre del icono es obligatorio.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        try {
            $this->model->crear($nombreIcono);
            $_SESSION['iconos_msg'] = ['success', 'Icono creado correctamente.'];
        } catch (\Throwable $e) {
            $_SESSION['iconos_msg'] = ['danger', 'Error al crear: ' . $e->getMessage()];
        }

        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    private function requireNivel(int $min): void
    {
        $nivel = (int) ($_SESSION['nivel'] ?? 0);
        if ($nivel < $min) {
            $_SESSION['iconos_msg'] = ['danger', 'No tiene permisos.'];
            header('Location: ' . BASE_URL . '/config');
            exit;
        }
    }
}
