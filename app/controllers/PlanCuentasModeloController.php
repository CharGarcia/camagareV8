<?php
/**
 * Controlador PlanCuentasModelo - Gestión del plan de cuentas modelo
 * Tabla: plan_cuentas_modelo
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\PlanCuentasModelo;

class PlanCuentasModeloController extends Controller
{
    private PlanCuentasModelo $model;
    private const BASE_PATH = '/config/plan-cuentas-modelo';

    public function __construct()
    {
        parent::__construct();
        $this->model = new PlanCuentasModelo();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        $ordenCol = trim($_GET['sort'] ?? 'codigo');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? 'asc'));
        $buscar = trim($_GET['b'] ?? $_GET['buscar'] ?? '');
        if (!in_array($ordenCol, PlanCuentasModelo::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'codigo';
        }
        if ($ordenDir !== 'ASC' && $ordenDir !== 'DESC') {
            $ordenDir = 'ASC';
        }

        $rows = $this->model->getAll($ordenCol, $ordenDir, $buscar);
        $todosCodigos = $this->model->getTodosCodigos();
        $rows = $this->model->enriquecerFilasParaIndex($rows, $todosCodigos);

        $this->viewWithLayout('layouts.main', 'planCuentasModelo.index', [
            'titulo' => 'Plan de cuentas modelo',
            'rows' => $rows,
            'ordenCol' => $ordenCol,
            'ordenDir' => $ordenDir,
            'buscar' => $buscar,
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $nombre = trim($_POST['nombre'] ?? '');
        if ($nombre === '') {
            $_SESSION['plan_cuentas_modelo_msg'] = ['danger', 'El nombre es obligatorio.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $codigoPadre = trim($_POST['codigo_padre'] ?? '');
        if ($codigoPadre !== '') {
            $codigo = $this->model->getSiguienteCodigoHijo($codigoPadre);
            $nivelPadre = (int) (trim($_POST['nivel_padre'] ?? '0'));
            $nivel = (string) ($nivelPadre + 1);
        } else {
            $codigo = trim($_POST['codigo'] ?? '');
            $nivel = trim($_POST['nivel'] ?? '1');
        }

        $data = [
            'codigo' => $codigo,
            'nivel' => $nivel,
            'nombre' => $nombre,
            'codigo_sri' => trim($_POST['codigo_sri'] ?? ''),
            'supercias_esf' => trim($_POST['supercias_esf'] ?? ''),
            'supercias_eri' => trim($_POST['supercias_eri'] ?? ''),
            'supercias_ecp_codigo' => trim($_POST['supercias_ecp_codigo'] ?? ''),
            'supercias_ecp_subcodigo' => trim($_POST['supercias_ecp_subcodigo'] ?? ''),
            'status' => !empty($_POST['status']),
        ];

        try {
            $this->model->crear($data);
            $_SESSION['plan_cuentas_modelo_msg'] = ['success', 'Cuenta creada correctamente.'];
        } catch (\Throwable $e) {
            $_SESSION['plan_cuentas_modelo_msg'] = ['danger', 'Error al crear: ' . $e->getMessage()];
        }

        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    public function update(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $id = (int) ($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        if ($id <= 0) {
            $_SESSION['plan_cuentas_modelo_msg'] = ['danger', 'ID inválido.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }
        if ($nombre === '') {
            $_SESSION['plan_cuentas_modelo_msg'] = ['danger', 'El nombre es obligatorio.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $data = [
            'codigo' => trim($_POST['codigo'] ?? ''),
            'nivel' => trim($_POST['nivel'] ?? '1'),
            'nombre' => $nombre,
            'codigo_sri' => trim($_POST['codigo_sri'] ?? ''),
            'supercias_esf' => trim($_POST['supercias_esf'] ?? ''),
            'supercias_eri' => trim($_POST['supercias_eri'] ?? ''),
            'supercias_ecp_codigo' => trim($_POST['supercias_ecp_codigo'] ?? ''),
            'supercias_ecp_subcodigo' => trim($_POST['supercias_ecp_subcodigo'] ?? ''),
            'status' => !empty($_POST['status']),
        ];

        if ($this->model->actualizar($id, $data)) {
            $_SESSION['plan_cuentas_modelo_msg'] = ['success', 'Cuenta actualizada correctamente.'];
        } else {
            $_SESSION['plan_cuentas_modelo_msg'] = ['danger', 'Error al actualizar.'];
        }

        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    public function delete(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['plan_cuentas_modelo_msg'] = ['danger', 'ID inválido.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $cuenta = $this->model->getById($id);
        if (!$cuenta) {
            $_SESSION['plan_cuentas_modelo_msg'] = ['danger', 'Cuenta no encontrada.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if (!$this->model->puedeEliminar($cuenta['codigo'] ?? '')) {
            $_SESSION['plan_cuentas_modelo_msg'] = ['danger', 'No se puede eliminar: esta cuenta tiene cuentas hijas (nivel inferior) que dependen de ella.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($this->model->eliminar($id)) {
            $_SESSION['plan_cuentas_modelo_msg'] = ['success', 'Cuenta eliminada correctamente.'];
        } else {
            $_SESSION['plan_cuentas_modelo_msg'] = ['danger', 'Error al eliminar.'];
        }

        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    private function requireNivel(int $min): void
    {
        $nivel = (int) ($_SESSION['nivel'] ?? 0);
        if ($nivel < $min) {
            $_SESSION['plan_cuentas_modelo_msg'] = ['danger', 'No tiene permisos.'];
            header('Location: ' . BASE_URL . '/config');
            exit;
        }
    }
}
