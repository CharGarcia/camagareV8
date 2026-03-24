<?php
/**
 * Controlador TarifaIva - Gestión de tarifas IVA
 * Tabla tarifa_iva. Clic en fila para editar.
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\TarifaIva;

class TarifaIvaController extends Controller
{
    private TarifaIva $model;
    private const BASE_PATH = '/config/tarifa-iva';

    public function __construct()
    {
        parent::__construct();
        $this->model = new TarifaIva();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        $ordenCol = trim($_GET['sort'] ?? 'porcentaje_iva');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? 'asc'));
        $buscar = trim($_GET['b'] ?? $_GET['buscar'] ?? '');
        if (!in_array($ordenCol, TarifaIva::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'porcentaje_iva';
        }
        if ($ordenDir !== 'ASC' && $ordenDir !== 'DESC') {
            $ordenDir = 'ASC';
        }

        $rows = $this->model->getAll($ordenCol, $ordenDir, $buscar);

        $this->viewWithLayout('layouts.main', 'tarifaIva.index', [
            'titulo' => 'Tarifas IVA',
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
        $codigo = trim($_POST['codigo'] ?? '');
        $tarifa = trim($_POST['tarifa'] ?? '');
        $porcentajeIva = (int) ($_POST['porcentaje_iva'] ?? 0);
        $status = !empty($_POST['status']);

        if ($id <= 0) {
            $_SESSION['tarifa_iva_msg'] = ['danger', 'ID inválido.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($codigo === '') {
            $_SESSION['tarifa_iva_msg'] = ['danger', 'El código es obligatorio.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($this->model->actualizar($id, $codigo, $tarifa, $porcentajeIva, $status ? 1 : 0)) {
            $_SESSION['tarifa_iva_msg'] = ['success', 'Tarifa IVA actualizada correctamente.'];
        } else {
            $_SESSION['tarifa_iva_msg'] = ['danger', 'Error al actualizar.'];
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

        $codigo = trim($_POST['codigo'] ?? '');
        $tarifa = trim($_POST['tarifa'] ?? '');
        $porcentajeIva = (int) ($_POST['porcentaje_iva'] ?? 0);
        $status = !empty($_POST['status']);

        if ($codigo === '') {
            $_SESSION['tarifa_iva_msg'] = ['danger', 'El código es obligatorio.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        try {
            $this->model->crear($codigo, $tarifa, $porcentajeIva, $status ? 1 : 0);
            $_SESSION['tarifa_iva_msg'] = ['success', 'Tarifa IVA creada correctamente.'];
        } catch (\Throwable $e) {
            $_SESSION['tarifa_iva_msg'] = ['danger', 'Error al crear: ' . $e->getMessage()];
        }

        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    private function requireNivel(int $min): void
    {
        $nivel = (int) ($_SESSION['nivel'] ?? 0);
        if ($nivel < $min) {
            $_SESSION['tarifa_iva_msg'] = ['danger', 'No tiene permisos.'];
            header('Location: ' . BASE_URL . '/config');
            exit;
        }
    }
}
