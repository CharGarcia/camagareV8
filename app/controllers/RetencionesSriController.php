<?php
/**
 * Controlador RetencionesSri - Gestión de retenciones SRI
 * Solo edición (clic en fila). Tabla retenciones_sri.
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\RetencionSri;

class RetencionesSriController extends Controller
{
    private RetencionSri $model;
    private const BASE_PATH = '/config/retenciones-sri';

    public function __construct()
    {
        parent::__construct();
        $this->model = new RetencionSri();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        $ordenCol = trim($_GET['sort'] ?? 'codigo_ret');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? 'asc'));
        $buscar = trim($_GET['b'] ?? $_GET['buscar'] ?? '');
        if (!in_array($ordenCol, RetencionSri::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'codigo_ret';
        }
        if ($ordenDir !== 'ASC' && $ordenDir !== 'DESC') {
            $ordenDir = 'ASC';
        }

        $rows = $this->model->getAll($ordenCol, $ordenDir, $buscar);

        $this->viewWithLayout('layouts.main', 'retencionesSri.index', [
            'titulo' => 'Retenciones SRI',
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
        $codigoRet = trim($_POST['codigo_ret'] ?? '');
        $conceptoRet = trim($_POST['concepto_ret'] ?? '');
        $porcentajeRet = (float) ($_POST['porcentaje_ret'] ?? 0);
        $impuestoRet = trim($_POST['impuesto_ret'] ?? 'RENTA');
        $codAnexoRet = trim($_POST['cod_anexo_ret'] ?? '');
        $status = !empty($_POST['status']);
        $desde = trim($_POST['desde'] ?? '');
        $hasta = trim($_POST['hasta'] ?? '');

        if ($id <= 0) {
            $_SESSION['retenciones_msg'] = ['danger', 'ID inválido.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($codigoRet === '') {
            $_SESSION['retenciones_msg'] = ['danger', 'El código de retención es obligatorio.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($this->model->existeCodigoConceptoPorcentaje($codigoRet, $conceptoRet, $porcentajeRet, $id)) {
            $_SESSION['retenciones_msg'] = ['danger', 'Ya existe una retención con el mismo código, descripción y porcentaje.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }
        if ($this->model->existeConceptoVigencia($conceptoRet, $desde, $hasta, $id)) {
            $_SESSION['retenciones_msg'] = ['danger', 'Ya existe una retención con la misma descripción y vigencia (desde-hasta).'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($this->model->actualizar($id, $codigoRet, $conceptoRet, $porcentajeRet, $impuestoRet, $codAnexoRet, $status ? 1 : 0, $desde, $hasta)) {
            $_SESSION['retenciones_msg'] = ['success', 'Retención actualizada correctamente.'];
        } else {
            $_SESSION['retenciones_msg'] = ['danger', 'Error al actualizar.'];
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

        $codigoRet = trim($_POST['codigo_ret'] ?? '');
        $conceptoRet = trim($_POST['concepto_ret'] ?? '');
        $porcentajeRet = (float) ($_POST['porcentaje_ret'] ?? 0);
        $impuestoRet = trim($_POST['impuesto_ret'] ?? 'RENTA');
        $codAnexoRet = trim($_POST['cod_anexo_ret'] ?? '');
        $status = !empty($_POST['status']);
        $desde = trim($_POST['desde'] ?? '');
        $hasta = trim($_POST['hasta'] ?? '');

        if ($codigoRet === '') {
            $_SESSION['retenciones_msg'] = ['danger', 'El código de retención es obligatorio.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($this->model->existeCodigoConceptoPorcentaje($codigoRet, $conceptoRet, $porcentajeRet, null)) {
            $_SESSION['retenciones_msg'] = ['danger', 'Ya existe una retención con el mismo código, descripción y porcentaje.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }
        if ($this->model->existeConceptoVigencia($conceptoRet, $desde, $hasta, null)) {
            $_SESSION['retenciones_msg'] = ['danger', 'Ya existe una retención con la misma descripción y vigencia (desde-hasta).'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        try {
            $this->model->crear($codigoRet, $conceptoRet, $porcentajeRet, $impuestoRet, $codAnexoRet, $status ? 1 : 0, $desde, $hasta);
            $_SESSION['retenciones_msg'] = ['success', 'Retención creada correctamente.'];
        } catch (\Throwable $e) {
            $_SESSION['retenciones_msg'] = ['danger', 'Error al crear: ' . $e->getMessage()];
        }

        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    private function requireNivel(int $min): void
    {
        $nivel = (int) ($_SESSION['nivel'] ?? 0);
        if ($nivel < $min) {
            $_SESSION['retenciones_msg'] = ['danger', 'No tiene permisos.'];
            header('Location: ' . BASE_URL . '/config');
            exit;
        }
    }
}
