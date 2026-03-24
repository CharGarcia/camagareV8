<?php
/**
 * Controlador SustentoTributario - Gestión de sustento tributario
 * Tabla sustento_tributario. Código y nombre únicos. tipo_comprobante: códigos separados por coma.
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\SustentoTributario;
use App\models\ComprobanteAutorizado;

class SustentoTributarioController extends Controller
{
    private SustentoTributario $model;
    private const BASE_PATH = '/config/sustento-tributario';

    public function __construct()
    {
        parent::__construct();
        $this->model = new SustentoTributario();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        $ordenCol = trim($_GET['sort'] ?? 'codigo');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? 'asc'));
        $buscar = trim($_GET['b'] ?? $_GET['buscar'] ?? '');
        if (!in_array($ordenCol, SustentoTributario::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'codigo';
        }
        if ($ordenDir !== 'ASC' && $ordenDir !== 'DESC') {
            $ordenDir = 'ASC';
        }

        $rows = $this->model->getAll($ordenCol, $ordenDir, $buscar);

        $compModel = new ComprobanteAutorizado();
        $comprobantes = $compModel->getAll('codigo_comprobante', 'ASC', '');

        $this->viewWithLayout('layouts.main', 'sustentoTributario.index', [
            'titulo' => 'Sustento tributario',
            'rows' => $rows,
            'ordenCol' => $ordenCol,
            'ordenDir' => $ordenDir,
            'buscar' => $buscar,
            'comprobantes' => $comprobantes,
        ]);
    }

    private function normalizarTipoComprobante(mixed $val): string
    {
        if (is_array($val)) {
            $arr = array_map('trim', array_filter($val));
            return implode(',', $arr);
        }
        return trim((string) $val);
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
        $codigo = trim($_POST['codigo'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $tipoComprobante = $this->normalizarTipoComprobante($_POST['tipo_comprobante'] ?? '');
        $status = !empty($_POST['status']);

        if ($id <= 0) {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'ID inválido.']);
                return;
            }
            $_SESSION['sustento_tributario_msg'] = ['danger', 'ID inválido.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($codigo === '') {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'El código es obligatorio.']);
                return;
            }
            $_SESSION['sustento_tributario_msg'] = ['danger', 'El código es obligatorio.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($nombre === '') {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'El nombre es obligatorio.']);
                return;
            }
            $_SESSION['sustento_tributario_msg'] = ['danger', 'El nombre es obligatorio.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($this->model->existeCodigo($codigo, $id)) {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'Ya existe un sustento con ese código.']);
                return;
            }
            $_SESSION['sustento_tributario_msg'] = ['danger', 'Ya existe un sustento con ese código.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($this->model->existeNombre($nombre, $id)) {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'Ya existe un sustento con ese nombre.']);
                return;
            }
            $_SESSION['sustento_tributario_msg'] = ['danger', 'Ya existe un sustento con ese nombre.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        try {
            if ($this->model->actualizar($id, $codigo, $nombre, $tipoComprobante, $status ? 1 : 0)) {
                if ($esAjax) {
                    $this->json(['ok' => true, 'msg' => 'Sustento tributario actualizado correctamente.']);
                    return;
                }
                $_SESSION['sustento_tributario_msg'] = ['success', 'Sustento tributario actualizado correctamente.'];
            } else {
                if ($esAjax) {
                    $this->json(['ok' => false, 'error' => 'Error al actualizar.']);
                    return;
                }
                $_SESSION['sustento_tributario_msg'] = ['danger', 'Error al actualizar.'];
            }
        } catch (\Throwable $e) {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'Error: ' . $e->getMessage()]);
                return;
            }
            $_SESSION['sustento_tributario_msg'] = ['danger', 'Error: ' . $e->getMessage()];
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
        $codigo = trim($_POST['codigo'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $tipoComprobante = $this->normalizarTipoComprobante($_POST['tipo_comprobante'] ?? '');
        $status = !empty($_POST['status']);

        if ($codigo === '') {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'El código es obligatorio.']);
                return;
            }
            $_SESSION['sustento_tributario_msg'] = ['danger', 'El código es obligatorio.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($nombre === '') {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'El nombre es obligatorio.']);
                return;
            }
            $_SESSION['sustento_tributario_msg'] = ['danger', 'El nombre es obligatorio.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($this->model->existeCodigo($codigo, null)) {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'Ya existe un sustento con ese código.']);
                return;
            }
            $_SESSION['sustento_tributario_msg'] = ['danger', 'Ya existe un sustento con ese código.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($this->model->existeNombre($nombre, null)) {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'Ya existe un sustento con ese nombre.']);
                return;
            }
            $_SESSION['sustento_tributario_msg'] = ['danger', 'Ya existe un sustento con ese nombre.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        try {
            $this->model->crear($codigo, $nombre, $tipoComprobante, $status ? 1 : 0);
            if ($esAjax) {
                $this->json(['ok' => true, 'msg' => 'Sustento tributario creado correctamente.']);
                return;
            }
            $_SESSION['sustento_tributario_msg'] = ['success', 'Sustento tributario creado correctamente.'];
        } catch (\Throwable $e) {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'Error al crear: ' . $e->getMessage()]);
                return;
            }
            $_SESSION['sustento_tributario_msg'] = ['danger', 'Error al crear: ' . $e->getMessage()];
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
            $_SESSION['sustento_tributario_msg'] = ['danger', 'ID inválido.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($this->model->eliminar($id)) {
            $_SESSION['sustento_tributario_msg'] = ['success', 'Sustento tributario eliminado correctamente.'];
        } else {
            $_SESSION['sustento_tributario_msg'] = ['danger', 'Error al eliminar.'];
        }

        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    private function requireNivel(int $min): void
    {
        $nivel = (int) ($_SESSION['nivel'] ?? 0);
        if ($nivel < $min) {
            $_SESSION['sustento_tributario_msg'] = ['danger', 'No tiene permisos.'];
            header('Location: ' . BASE_URL . '/config');
            exit;
        }
    }
}
