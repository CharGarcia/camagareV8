<?php
/**
 * Controlador ComprobantesAutorizados - Gestión de comprobantes autorizados
 * Tabla comprobantes_autorizados. Clic en fila para editar.
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\ComprobanteAutorizado;

class ComprobantesAutorizadosController extends Controller
{
    private ComprobanteAutorizado $model;
    private const BASE_PATH = '/config/comprobantes-autorizados';

    public function __construct()
    {
        parent::__construct();
        $this->model = new ComprobanteAutorizado();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        $ordenCol = trim($_GET['sort'] ?? 'codigo_comprobante');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? 'asc'));
        $buscar = trim($_GET['b'] ?? $_GET['buscar'] ?? '');
        if (!in_array($ordenCol, ComprobanteAutorizado::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'codigo_comprobante';
        }
        if ($ordenDir !== 'ASC' && $ordenDir !== 'DESC') {
            $ordenDir = 'ASC';
        }

        $rows = $this->model->getAll($ordenCol, $ordenDir, $buscar);

        $this->viewWithLayout('layouts.main', 'comprobantesAutorizados.index', [
            'titulo' => 'Comprobantes autorizados',
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

        $esAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
        $id = (int) ($_POST['id'] ?? 0);
        $codigoComprobante = trim($_POST['codigo_comprobante'] ?? '');
        $comprobante = trim($_POST['comprobante'] ?? '');
        $status = !empty($_POST['status']);

        if ($id <= 0) {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'ID inválido.']);
                return;
            }
            $_SESSION['comprobantes_msg'] = ['danger', 'ID inválido.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($codigoComprobante === '') {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'El código es obligatorio.']);
                return;
            }
            $_SESSION['comprobantes_msg'] = ['danger', 'El código es obligatorio.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($comprobante === '') {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'El nombre del comprobante es obligatorio.']);
                return;
            }
            $_SESSION['comprobantes_msg'] = ['danger', 'El nombre del comprobante es obligatorio.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($this->model->existeCodigo($codigoComprobante, $id)) {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'Ya existe un comprobante con ese código.']);
                return;
            }
            $_SESSION['comprobantes_msg'] = ['danger', 'Ya existe un comprobante con ese código.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        try {
            if ($this->model->actualizar($id, $codigoComprobante, $comprobante, $status ? 1 : 0)) {
                if ($esAjax) {
                    $this->json(['ok' => true, 'msg' => 'Comprobante actualizado correctamente.']);
                    return;
                }
                $_SESSION['comprobantes_msg'] = ['success', 'Comprobante actualizado correctamente.'];
            } else {
                if ($esAjax) {
                    $this->json(['ok' => false, 'error' => 'Error al actualizar.']);
                    return;
                }
                $_SESSION['comprobantes_msg'] = ['danger', 'Error al actualizar.'];
            }
        } catch (\Throwable $e) {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'Error: ' . $e->getMessage()]);
                return;
            }
            $_SESSION['comprobantes_msg'] = ['danger', 'Error: ' . $e->getMessage()];
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

        $esAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
        $codigoComprobante = trim($_POST['codigo_comprobante'] ?? '');
        $comprobante = trim($_POST['comprobante'] ?? '');
        $status = !empty($_POST['status']);

        if ($codigoComprobante === '') {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'El código es obligatorio.']);
                return;
            }
            $_SESSION['comprobantes_msg'] = ['danger', 'El código es obligatorio.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($comprobante === '') {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'El nombre del comprobante es obligatorio.']);
                return;
            }
            $_SESSION['comprobantes_msg'] = ['danger', 'El nombre del comprobante es obligatorio.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($this->model->existeCodigo($codigoComprobante, null)) {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'Ya existe un comprobante con ese código.']);
                return;
            }
            $_SESSION['comprobantes_msg'] = ['danger', 'Ya existe un comprobante con ese código.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        try {
            $this->model->crear($codigoComprobante, $comprobante, $status ? 1 : 0);
            if ($esAjax) {
                $this->json(['ok' => true, 'msg' => 'Comprobante creado correctamente.']);
                return;
            }
            $_SESSION['comprobantes_msg'] = ['success', 'Comprobante creado correctamente.'];
        } catch (\Throwable $e) {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'Error al crear: ' . $e->getMessage()]);
                return;
            }
            $_SESSION['comprobantes_msg'] = ['danger', 'Error al crear: ' . $e->getMessage()];
        }

        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    public function delete(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['comprobantes_msg'] = ['danger', 'ID inválido.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($this->model->eliminar($id)) {
            $_SESSION['comprobantes_msg'] = ['success', 'Comprobante eliminado correctamente.'];
        } else {
            $_SESSION['comprobantes_msg'] = ['danger', 'Error al eliminar.'];
        }

        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    private function requireNivel(int $min): void
    {
        $nivel = (int) ($_SESSION['nivel'] ?? 0);
        if ($nivel < $min) {
            $_SESSION['comprobantes_msg'] = ['danger', 'No tiene permisos.'];
            header('Location: ' . BASE_URL . '/config');
            exit;
        }
    }
}
