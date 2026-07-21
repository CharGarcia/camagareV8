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
                // Si no hay id, asume que falta y recrea la llave primaria
                $db->exec("ALTER TABLE sri_casilleros_etiquetas DROP CONSTRAINT IF EXISTS sri_casilleros_etiquetas_pkey CASCADE");
                $db->exec("ALTER TABLE sri_casilleros_etiquetas ADD COLUMN id SERIAL PRIMARY KEY");
            }

            // Validar columnas de auditoría
            $st2 = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'sri_casilleros_etiquetas' AND column_name = 'updated_at'");
            if ($st2->rowCount() === 0) {
                $db->exec("ALTER TABLE sri_casilleros_etiquetas ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
                $db->exec("ALTER TABLE sri_casilleros_etiquetas ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
                $db->exec("ALTER TABLE sri_casilleros_etiquetas ADD COLUMN deleted_at TIMESTAMP NULL");
                $db->exec("ALTER TABLE sri_casilleros_etiquetas ADD COLUMN created_by INT NULL");
                $db->exec("ALTER TABLE sri_casilleros_etiquetas ADD COLUMN updated_by INT NULL");
                $db->exec("ALTER TABLE sri_casilleros_etiquetas ADD COLUMN deleted_by INT NULL");
            }

            // fuente_valor: indica cómo se llena el casillero (montos sincronizados o conteo de documentos)
            $st3 = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'sri_casilleros_etiquetas' AND column_name = 'fuente_valor'");
            if ($st3->rowCount() === 0) {
                $db->exec("ALTER TABLE sri_casilleros_etiquetas ADD COLUMN fuente_valor VARCHAR(50) DEFAULT 'documentos'");
            }
        } catch (\Throwable $e) {
            // Ignorar errores en producción
            error_log("Error migracion etiquetas: " . $e->getMessage());
        }
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireNivel(3); // Solo Superadmin

        $ordenCol = trim($_GET['sort'] ?? 'orden');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? 'DESC'));
        $buscar = trim($_GET['b'] ?? $_GET['buscar'] ?? '');
        
        if (!in_array($ordenCol, SriCasilleroEtiqueta::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'orden';
        }
        if ($ordenDir !== 'ASC' && $ordenDir !== 'DESC') {
            $ordenDir = 'DESC';
        }

        $rows = $this->model->getAll($ordenCol, $ordenDir, $buscar);
        $valoresDefecto = $this->model->getValoresPorDefecto();

        $this->viewWithLayout('layouts.main', 'config.sriCasillerosEtiquetas.index', [
            'titulo' => 'Estructura Formulario 104',
            'rows' => $rows,
            'ordenCol' => $ordenCol,
            'ordenDir' => $ordenDir,
            'buscar' => $buscar,
            'valoresDefecto' => $valoresDefecto,
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
        $editable = !empty($_POST['editable']);
        $tipo = trim($_POST['tipo'] ?? 'valor');
        $fuenteValor = trim($_POST['fuente_valor'] ?? 'documentos');
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
                $idUsuario, $fuenteValor, $editable
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
        $editable = !empty($_POST['editable']);
        $tipo = trim($_POST['tipo'] ?? 'valor');
        $fuenteValor = trim($_POST['fuente_valor'] ?? 'documentos');
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);

        if ($seccion === '' || $descripcion === '') {
            $_SESSION['sri_etiquetas_msg'] = ['danger', 'Sección y Descripción son obligatorios.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($this->model->actualizar(
            $id, $casilleroBruto, $casilleroNeto, $casilleroImpuesto,
            $seccion, $descripcion, $orden, $indent, $bold, $tipo,
            $formulaBruto, $formulaNeto, $formulaImpuesto,
            $idUsuario, $fuenteValor, $editable
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
