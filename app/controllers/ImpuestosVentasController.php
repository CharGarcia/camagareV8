<?php
/**
 * Controlador ImpuestosVentas - Gestión de impuestos de ventas
 * Tabla impuestos_ventas. Clic en fila para editar. Código único.
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\ImpuestoVenta;

class ImpuestosVentasController extends Controller
{
    private ImpuestoVenta $model;
    private const BASE_PATH = '/config/impuestos-ventas';

    public function __construct()
    {
        parent::__construct();
        $this->model = new ImpuestoVenta();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        $ordenCol = trim($_GET['sort'] ?? 'codigo');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? 'asc'));
        $buscar = trim($_GET['b'] ?? $_GET['buscar'] ?? '');
        if (!in_array($ordenCol, ImpuestoVenta::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'codigo';
        }
        if ($ordenDir !== 'ASC' && $ordenDir !== 'DESC') {
            $ordenDir = 'ASC';
        }

        $rows = $this->model->getAll($ordenCol, $ordenDir, $buscar);

        $this->viewWithLayout('layouts.main', 'impuestosVentas.index', [
            'titulo' => 'Impuestos ventas',
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
        $codigo = trim($_POST['codigo'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $status = !empty($_POST['status']);

        if ($id <= 0) {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'ID inválido.']);
                return;
            }
            $_SESSION['impuestos_ventas_msg'] = ['danger', 'ID inválido.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($codigo === '') {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'El código es obligatorio.']);
                return;
            }
            $_SESSION['impuestos_ventas_msg'] = ['danger', 'El código es obligatorio.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($nombre === '') {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'El nombre es obligatorio.']);
                return;
            }
            $_SESSION['impuestos_ventas_msg'] = ['danger', 'El nombre es obligatorio.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($this->model->existeCodigo($codigo, $id)) {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'Ya existe un impuesto de venta con ese código.']);
                return;
            }
            $_SESSION['impuestos_ventas_msg'] = ['danger', 'Ya existe un impuesto de venta con ese código.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        try {
            if ($this->model->actualizar($id, $codigo, $nombre, $status ? 1 : 0)) {
                if ($esAjax) {
                    $this->json(['ok' => true, 'msg' => 'Impuesto de venta actualizado correctamente.']);
                    return;
                }
                $_SESSION['impuestos_ventas_msg'] = ['success', 'Impuesto de venta actualizado correctamente.'];
            } else {
                if ($esAjax) {
                    $this->json(['ok' => false, 'error' => 'Error al actualizar.']);
                    return;
                }
                $_SESSION['impuestos_ventas_msg'] = ['danger', 'Error al actualizar.'];
            }
        } catch (\Throwable $e) {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'Error: ' . $e->getMessage()]);
                return;
            }
            $_SESSION['impuestos_ventas_msg'] = ['danger', 'Error: ' . $e->getMessage()];
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
        $status = !empty($_POST['status']);

        if ($codigo === '') {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'El código es obligatorio.']);
                return;
            }
            $_SESSION['impuestos_ventas_msg'] = ['danger', 'El código es obligatorio.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($nombre === '') {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'El nombre es obligatorio.']);
                return;
            }
            $_SESSION['impuestos_ventas_msg'] = ['danger', 'El nombre es obligatorio.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($this->model->existeCodigo($codigo, null)) {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'Ya existe un impuesto de venta con ese código.']);
                return;
            }
            $_SESSION['impuestos_ventas_msg'] = ['danger', 'Ya existe un impuesto de venta con ese código.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        try {
            $this->model->crear($codigo, $nombre, $status ? 1 : 0);
            if ($esAjax) {
                $this->json(['ok' => true, 'msg' => 'Impuesto de venta creado correctamente.']);
                return;
            }
            $_SESSION['impuestos_ventas_msg'] = ['success', 'Impuesto de venta creado correctamente.'];
        } catch (\Throwable $e) {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'Error al crear: ' . $e->getMessage()]);
                return;
            }
            $_SESSION['impuestos_ventas_msg'] = ['danger', 'Error al crear: ' . $e->getMessage()];
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
            $_SESSION['impuestos_ventas_msg'] = ['danger', 'ID inválido.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($this->model->eliminar($id)) {
            $_SESSION['impuestos_ventas_msg'] = ['success', 'Impuesto de venta eliminado correctamente.'];
        } else {
            $_SESSION['impuestos_ventas_msg'] = ['danger', 'Error al eliminar.'];
        }

        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    private function requireNivel(int $min): void
    {
        $nivel = (int) ($_SESSION['nivel'] ?? 0);
        if ($nivel < $min) {
            $_SESSION['impuestos_ventas_msg'] = ['danger', 'No tiene permisos.'];
            header('Location: ' . BASE_URL . '/config');
            exit;
        }
    }
}
