<?php
/**
 * Controlador BancosEcuador - Gestión de bancos de Ecuador
 * Tabla bancos_ecuador. Clic en fila para editar.
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\BancoEcuador;

class BancosEcuadorController extends Controller
{
    private BancoEcuador $model;
    private const BASE_PATH = '/config/bancos-ecuador';

    public function __construct()
    {
        parent::__construct();
        $this->model = new BancoEcuador();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        $ordenCol = trim($_GET['sort'] ?? 'nombre_banco');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? 'asc'));
        $buscar = trim($_GET['b'] ?? $_GET['buscar'] ?? '');
        if (!in_array($ordenCol, BancoEcuador::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'nombre_banco';
        }
        if ($ordenDir !== 'ASC' && $ordenDir !== 'DESC') {
            $ordenDir = 'ASC';
        }

        $rows = $this->model->getAll($ordenCol, $ordenDir, $buscar);

        $this->viewWithLayout('layouts.main', 'bancosEcuador.index', [
            'titulo' => 'Bancos Ecuador',
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
        $codigoBanco = trim($_POST['codigo_banco'] ?? '');
        $nombreBanco = trim($_POST['nombre_banco'] ?? '');
        $spi = trim($_POST['spi'] ?? '');
        $sci = trim($_POST['sci'] ?? '');
        $status = !empty($_POST['status']);

        if ($id <= 0) {
            $_SESSION['bancos_msg'] = ['danger', 'ID inválido.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($codigoBanco === '') {
            $_SESSION['bancos_msg'] = ['danger', 'El código es obligatorio.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($this->model->actualizar($id, $codigoBanco, $nombreBanco, $spi, $sci, $status ? 1 : 0)) {
            $_SESSION['bancos_msg'] = ['success', 'Banco actualizado correctamente.'];
        } else {
            $_SESSION['bancos_msg'] = ['danger', 'Error al actualizar.'];
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

        $codigoBanco = trim($_POST['codigo_banco'] ?? '');
        $nombreBanco = trim($_POST['nombre_banco'] ?? '');
        $spi = trim($_POST['spi'] ?? '');
        $sci = trim($_POST['sci'] ?? '');
        $status = !empty($_POST['status']);

        if ($codigoBanco === '') {
            $_SESSION['bancos_msg'] = ['danger', 'El código es obligatorio.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        try {
            $this->model->crear($codigoBanco, $nombreBanco, $spi, $sci, $status ? 1 : 0);
            $_SESSION['bancos_msg'] = ['success', 'Banco creado correctamente.'];
        } catch (\Throwable $e) {
            $_SESSION['bancos_msg'] = ['danger', 'Error al crear: ' . $e->getMessage()];
        }

        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    private function requireNivel(int $min): void
    {
        $nivel = (int) ($_SESSION['nivel'] ?? 0);
        if ($nivel < $min) {
            $_SESSION['bancos_msg'] = ['danger', 'No tiene permisos.'];
            header('Location: ' . BASE_URL . '/config');
            exit;
        }
    }
}
