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
            'rowsHtml' => $this->renderFilasHtml($rows),
            'ordenCol' => $ordenCol,
            'ordenDir' => $ordenDir,
            'buscar' => $buscar,
            'valoresDefecto' => $valoresDefecto,
        ]);
    }

    /**
     * AJAX: listado de filas del Formulario 104 (tabla), para búsqueda y
     * ordenamiento en tiempo real sin recargar la página. Mismo patrón que
     * ConfigController::asientosTipoListAjax.
     */
    public function searchAjax(): void
    {
        $this->requireAuth();
        $this->requireNivel(3);
        header('Content-Type: application/json');

        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? 'orden');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? 'DESC'));
        $buscar = trim($_GET['b'] ?? $_POST['b'] ?? '');
        if (!in_array($ordenCol, SriCasilleroEtiqueta::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'orden';
        }
        if ($ordenDir !== 'ASC' && $ordenDir !== 'DESC') {
            $ordenDir = 'DESC';
        }

        $rows = $this->model->getAll($ordenCol, $ordenDir, $buscar);

        echo json_encode([
            'ok' => true,
            'rows' => $this->renderFilasHtml($rows),
        ]);
        exit;
    }

    /**
     * Renderiza el <tbody> completo (filas o mensaje de "sin resultados").
     * Usado tanto por la carga inicial (vista) como por searchAjax.
     */
    private function renderFilasHtml(array $rows): string
    {
        if (empty($rows)) {
            return '<tr><td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-ui-checks-grid fs-3 d-block mb-2"></i>No hay filas registradas o no coinciden con la búsqueda.</td></tr>';
        }
        $html = '';
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            $c_seccion = htmlspecialchars($r['seccion'] ?? '');
            $c_desc = htmlspecialchars($r['descripcion'] ?? '');
            $c_bruto = htmlspecialchars($r['casillero_bruto'] ?? '');
            $c_neto = htmlspecialchars($r['casillero_neto'] ?? '');
            $c_impuesto = htmlspecialchars($r['casillero_impuesto'] ?? '');
            $c_orden = htmlspecialchars((string) ($r['orden'] ?? '0'));
            $c_indent = htmlspecialchars((string) ($r['indent'] ?? '0'));
            $c_bold = !empty($r['bold']);
            $c_editable = !empty($r['editable']);
            $c_tipo = htmlspecialchars($r['tipo'] ?? 'valor');

            $rj = htmlspecialchars(json_encode([
                'id' => $id,
                'seccion' => $c_seccion,
                'descripcion' => $c_desc,
                'casillero_bruto' => $c_bruto,
                'casillero_neto' => $c_neto,
                'casillero_impuesto' => $c_impuesto,
                'formula_bruto' => $r['formula_bruto'] ?? '',
                'formula_neto' => $r['formula_neto'] ?? '',
                'formula_impuesto' => $r['formula_impuesto'] ?? '',
                'orden' => $c_orden,
                'indent' => $c_indent,
                'bold' => $c_bold,
                'editable' => $c_editable,
                'tipo' => $c_tipo,
                'fuente_valor' => htmlspecialchars($r['fuente_valor'] ?? 'documentos'),
            ]), ENT_QUOTES, 'UTF-8');

            $html .= '<tr class="sri-row" role="button" tabindex="0" data-json="' . $rj . '" onclick="abrirModalEditar(this)">';
            $html .= '<td class="ps-3" data-col="seccion">' . $c_seccion . '</td>';
            $html .= '<td class="sri-desc-cell" data-col="descripcion" title="' . $c_desc . '">';
            $html .= $c_bold ? '<strong>' . $c_desc . '</strong>' : $c_desc;
            if ($c_tipo === 'titulo') {
                $html .= ' <span class="badge bg-info text-dark">TITULO</span>';
            }
            $html .= '</td>';
            $html .= '<td class="text-center">' . ($c_bruto ? '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">' . $c_bruto . '</span>' : '') . '</td>';
            $html .= '<td class="text-center">' . ($c_neto ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">' . $c_neto . '</span>' : '') . '</td>';
            $html .= '<td class="text-center">' . ($c_impuesto ? '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">' . $c_impuesto . '</span>' : '') . '</td>';
            $html .= '<td class="text-center">' . $c_orden . '</td>';
            $html .= '<td class="text-center pe-3" onclick="event.stopPropagation()">'
                . '<button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 border-0" onclick="confirmarEliminar(' . $id . ', \'' . htmlspecialchars(addslashes($c_desc)) . '\')" title="Eliminar"><i class="bi bi-trash"></i></button>'
                . '</td>';
            $html .= '</tr>';
        }
        return $html;
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
