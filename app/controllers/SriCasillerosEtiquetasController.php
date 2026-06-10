<?php
/**
 * Controlador SriCasillerosEtiquetasController
 * Gestión de la Estructura Formulario 104
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\SriCasilleroEtiqueta;

class SriCasillerosEtiquetasController extends Controller
{
    private SriCasilleroEtiqueta $model;
    private const BASE_PATH = '/config/sri-casilleros-etiquetas';

    public function __construct()
    {
        parent::__construct();
        $this->model = new SriCasilleroEtiqueta();
        $this->verificarEstructuraBaseDatos();
    }

    private function verificarEstructuraBaseDatos(): void
    {
        try {
            $db = \App\core\Database::getConnection();
            $st = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'sri_casilleros_etiquetas' AND column_name = 'id'");
            if ($st->rowCount() === 0) {
                $db->exec("ALTER TABLE sri_casilleros_etiquetas ADD COLUMN id SERIAL PRIMARY KEY");
            }
        } catch (\Throwable $e) {
            // Ignorar errores en producción
        }
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireNivel(3); // Solo Superadmin

        $ordenCol = trim($_GET['sort'] ?? 'seccion');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? 'asc'));
        $buscar = trim($_GET['b'] ?? $_GET['buscar'] ?? '');
        
        if (!in_array($ordenCol, SriCasilleroEtiqueta::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'seccion';
        }
        if ($ordenDir !== 'ASC' && $ordenDir !== 'DESC') {
            $ordenDir = 'ASC';
        }

        $rows = $this->model->getAll($ordenCol, $ordenDir, $buscar);

        $this->viewWithLayout('layouts.main', 'config.sriCasillerosEtiquetas.index', [
            'titulo' => 'Estructura Formulario 104',
            'rows' => $rows,
            'ordenCol' => $ordenCol,
            'ordenDir' => $ordenDir,
            'buscar' => $buscar,
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->requireNivel(3);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $casilleroBruto = trim($_POST['casillero_bruto'] ?? '');
        $casilleroNeto = trim($_POST['casillero_neto'] ?? '');
        $casilleroImpuesto = trim($_POST['casillero_impuesto'] ?? '');
        $formulaBruto = trim($_POST['formula_bruto'] ?? '');
        $formulaNeto = trim($_POST['formula_neto'] ?? '');
        $formulaImpuesto = trim($_POST['formula_impuesto'] ?? '');

        $seccion = trim($_POST['seccion'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $orden = (int) ($_POST['orden'] ?? 0);
        $indent = (int) ($_POST['indent'] ?? 0);
        $bold = !empty($_POST['bold']);
        $tipo = trim($_POST['tipo'] ?? 'valor');
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);

        if ($seccion === '' || $descripcion === '') {
            $_SESSION['sri_etiquetas_msg'] = ['danger', 'Sección y Descripción son obligatorios.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        try {
            $this->model->crear(
                $casilleroBruto, $casilleroNeto, $casilleroImpuesto,
                $seccion, $descripcion, $orden, $indent, $bold, $tipo,
                $formulaBruto, $formulaNeto, $formulaImpuesto,
                $idUsuario
            );
            $_SESSION['sri_etiquetas_msg'] = ['success', 'Fila creada correctamente.'];
        } catch (\Throwable $e) {
            $_SESSION['sri_etiquetas_msg'] = ['danger', 'Error al crear: ' . $e->getMessage()];
        }

        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    public function update(): void
    {
        $this->requireAuth();
        $this->requireNivel(3);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['sri_etiquetas_msg'] = ['danger', 'Identificador inválido.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $casilleroBruto = trim($_POST['casillero_bruto'] ?? '');
        $casilleroNeto = trim($_POST['casillero_neto'] ?? '');
        $casilleroImpuesto = trim($_POST['casillero_impuesto'] ?? '');
        $formulaBruto = trim($_POST['formula_bruto'] ?? '');
        $formulaNeto = trim($_POST['formula_neto'] ?? '');
        $formulaImpuesto = trim($_POST['formula_impuesto'] ?? '');

        $seccion = trim($_POST['seccion'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $orden = (int) ($_POST['orden'] ?? 0);
        $indent = (int) ($_POST['indent'] ?? 0);
        $bold = !empty($_POST['bold']);
        $tipo = trim($_POST['tipo'] ?? 'valor');
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);

        if ($seccion === '' || $descripcion === '') {
            $_SESSION['sri_etiquetas_msg'] = ['danger', 'Sección y Descripción son obligatorios.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($this->model->actualizar(
            $id, $casilleroBruto, $casilleroNeto, $casilleroImpuesto,
            $seccion, $descripcion, $orden, $indent, $bold, $tipo,
            $formulaBruto, $formulaNeto, $formulaImpuesto,
            $idUsuario
        )) {
            $_SESSION['sri_etiquetas_msg'] = ['success', 'Fila actualizada correctamente.'];
        } else {
            $_SESSION['sri_etiquetas_msg'] = ['danger', 'Error al actualizar.'];
        }

        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    public function delete(): void
    {
        $this->requireAuth();
        $this->requireNivel(3);

        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);

        if ($id <= 0) {
            $_SESSION['sri_etiquetas_msg'] = ['danger', 'Identificador inválido.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($this->model->eliminar($id, $idUsuario)) {
            $_SESSION['sri_etiquetas_msg'] = ['success', 'Fila eliminada correctamente.'];
        } else {
            $_SESSION['sri_etiquetas_msg'] = ['danger', 'Error al eliminar.'];
        }

        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    private function requireNivel(int $min): void
    {
        $nivel = (int) ($_SESSION['nivel'] ?? 0);
        if ($nivel < $min) {
            $_SESSION['config_msg'] = ['danger', 'No tiene permisos para acceder a esta sección.'];
            header('Location: ' . BASE_URL . '/config');
            exit;
        }
    }
}
