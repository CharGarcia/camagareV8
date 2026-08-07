<?php
/**
 * Controlador UnidadesMedida - Gestión de tipos de unidad y unidades de medida
 * Tablas: unidades_tipo_modelo, unidades_medida_modelo (FK id_tipo)
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\UnidadesTipo;
use App\models\UnidadMedida;

class UnidadesMedidaController extends Controller
{
    private UnidadesTipo $modelTipo;
    private UnidadMedida $modelUnidad;
    private const BASE_PATH = '/config/unidades-medida';

    public function __construct()
    {
        parent::__construct();
        $this->modelTipo = new UnidadesTipo();
        $this->modelUnidad = new UnidadMedida();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        $tab = trim($_GET['tab'] ?? 'tipos');
        if (!in_array($tab, ['tipos', 'unidades'], true)) {
            $tab = 'tipos';
        }

        $ordenColTipo = trim($_GET['sort_tipo'] ?? 'nombre');
        $ordenDirTipo = strtoupper(trim($_GET['dir_tipo'] ?? 'asc'));
        $buscarTipo = trim($_GET['b_tipo'] ?? '');

        $ordenColUni = trim($_GET['sort_uni'] ?? 'nombre');
        $ordenDirUni = strtoupper(trim($_GET['dir_uni'] ?? 'asc'));
        $buscarUni = trim($_GET['b_uni'] ?? '');
        $filtroTipo = trim($_GET['f_tipo'] ?? '') !== '' ? (int) $_GET['f_tipo'] : null;

        if (!in_array($ordenColTipo, UnidadesTipo::COLUMNAS_ORDEN, true)) {
            $ordenColTipo = 'nombre';
        }
        if ($ordenDirTipo !== 'ASC' && $ordenDirTipo !== 'DESC') {
            $ordenDirTipo = 'ASC';
        }
        if (!in_array($ordenColUni, UnidadMedida::COLUMNAS_ORDEN, true)) {
            $ordenColUni = 'nombre';
        }
        if ($ordenDirUni !== 'ASC' && $ordenDirUni !== 'DESC') {
            $ordenDirUni = 'ASC';
        }

        $rowsTipos = $this->modelTipo->getAll($ordenColTipo, $ordenDirTipo, $buscarTipo);
        $rowsUnidades = $this->modelUnidad->getAll($ordenColUni, $ordenDirUni, $buscarUni, $filtroTipo);
        $tiposParaSelect = $this->modelTipo->getActivos();

        $this->viewWithLayout('layouts.main', 'unidadesMedida.index', [
            'titulo' => 'Unidades de medida',
            'rowsTipos' => $rowsTipos,
            'rowsUnidades' => $rowsUnidades,
            'rowsTiposHtml' => $this->renderFilasTiposHtml($rowsTipos),
            'rowsUnidadesHtml' => $this->renderFilasUnidadesHtml($rowsUnidades),
            'tiposParaSelect' => $tiposParaSelect,
            'tab' => $tab,
            'ordenColTipo' => $ordenColTipo,
            'ordenDirTipo' => $ordenDirTipo,
            'buscarTipo' => $buscarTipo,
            'ordenColUni' => $ordenColUni,
            'ordenDirUni' => $ordenDirUni,
            'buscarUni' => $buscarUni,
            'filtroTipo' => $filtroTipo,
        ]);
    }

    /**
     * AJAX: listado de tipos de unidad (tabla), para búsqueda y ordenamiento
     * en tiempo real sin recargar la página. Mismo patrón que
     * ConfigController::asientosTipoListAjax.
     */
    public function tiposSearchAjax(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);
        header('Content-Type: application/json');

        $ordenCol = trim($_GET['sort_tipo'] ?? $_POST['sort_tipo'] ?? 'nombre');
        $ordenDir = strtoupper(trim($_GET['dir_tipo'] ?? $_POST['dir_tipo'] ?? 'ASC'));
        $buscar = trim($_GET['b_tipo'] ?? $_POST['b_tipo'] ?? '');
        if (!in_array($ordenCol, UnidadesTipo::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'nombre';
        }
        if ($ordenDir !== 'ASC' && $ordenDir !== 'DESC') {
            $ordenDir = 'ASC';
        }

        $rows = $this->modelTipo->getAll($ordenCol, $ordenDir, $buscar);

        echo json_encode([
            'ok' => true,
            'rows' => $this->renderFilasTiposHtml($rows),
        ]);
        exit;
    }

    /**
     * AJAX: listado de unidades de medida (tabla), para búsqueda, filtro por
     * tipo y ordenamiento en tiempo real sin recargar la página.
     */
    public function unidadesSearchAjax(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);
        header('Content-Type: application/json');

        $ordenCol = trim($_GET['sort_uni'] ?? $_POST['sort_uni'] ?? 'nombre');
        $ordenDir = strtoupper(trim($_GET['dir_uni'] ?? $_POST['dir_uni'] ?? 'ASC'));
        $buscar = trim($_GET['b_uni'] ?? $_POST['b_uni'] ?? '');
        $filtroTipo = trim($_GET['f_tipo'] ?? $_POST['f_tipo'] ?? '') !== '' ? (int) ($_GET['f_tipo'] ?? $_POST['f_tipo']) : null;
        if (!in_array($ordenCol, UnidadMedida::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'nombre';
        }
        if ($ordenDir !== 'ASC' && $ordenDir !== 'DESC') {
            $ordenDir = 'ASC';
        }

        $rows = $this->modelUnidad->getAll($ordenCol, $ordenDir, $buscar, $filtroTipo);

        echo json_encode([
            'ok' => true,
            'rows' => $this->renderFilasUnidadesHtml($rows),
        ]);
        exit;
    }

    private function renderFilasTiposHtml(array $rows): string
    {
        if (empty($rows)) {
            return '<tr><td colspan="4" class="text-center py-4 text-muted">No hay tipos de unidad.</td></tr>';
        }
        $html = '';
        foreach ($rows as $r) {
            $estado = (int) ($r['estado'] ?? 1);
            $html .= '<tr class="tipo-row" role="button" tabindex="0" data-id="' . (int) ($r['id'] ?? 0) . '"'
                . ' data-codigo="' . htmlspecialchars($r['codigo'] ?? '') . '"'
                . ' data-nombre="' . htmlspecialchars($r['nombre'] ?? '') . '"'
                . ' data-descripcion="' . htmlspecialchars($r['descripcion'] ?? '') . '"'
                . ' data-estado="' . $estado . '">';
            $html .= '<td><code>' . htmlspecialchars($r['codigo'] ?? '') . '</code></td>';
            $html .= '<td>' . htmlspecialchars($r['nombre'] ?? '') . '</td>';
            $html .= '<td class="small text-muted">' . htmlspecialchars($r['descripcion'] ?? '') . '</td>';
            $html .= '<td class="text-center">' . ($estado ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>') . '</td>';
            $html .= '</tr>';
        }
        return $html;
    }

    private function renderFilasUnidadesHtml(array $rows): string
    {
        if (empty($rows)) {
            return '<tr><td colspan="6" class="text-center py-4 text-muted">No hay unidades de medida.</td></tr>';
        }
        $html = '';
        foreach ($rows as $r) {
            $estado = (int) ($r['estado'] ?? 1);
            $esBase = (int) ($r['es_base'] ?? 0);
            $html .= '<tr class="unidad-row" role="button" tabindex="0" data-id="' . (int) ($r['id'] ?? 0) . '"'
                . ' data-id-tipo="' . (int) ($r['id_tipo'] ?? 0) . '"'
                . ' data-codigo="' . htmlspecialchars($r['codigo'] ?? '') . '"'
                . ' data-nombre="' . htmlspecialchars($r['nombre'] ?? '') . '"'
                . ' data-abreviatura="' . htmlspecialchars($r['abreviatura'] ?? '') . '"'
                . ' data-es-base="' . $esBase . '"'
                . ' data-factor-base="' . htmlspecialchars((string) ($r['factor_base'] ?? '1')) . '"'
                . ' data-estado="' . $estado . '">';
            $html .= '<td><code>' . htmlspecialchars($r['codigo'] ?? '') . '</code></td>';
            $html .= '<td>' . htmlspecialchars($r['nombre'] ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($r['abreviatura'] ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($r['tipo_nombre'] ?? '') . '</td>';
            $html .= '<td class="text-end">' . ($esBase ? '<span class="badge bg-info">Base</span>' : htmlspecialchars((string) ($r['factor_base'] ?? '1'))) . '</td>';
            $html .= '<td class="text-center">' . ($estado ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>') . '</td>';
            $html .= '</tr>';
        }
        return $html;
    }

    public function tipoStore(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $codigo = trim($_POST['codigo'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $estado = !empty($_POST['estado']);

        if ($nombre === '') {
            $_SESSION['unidades_msg'] = ['danger', 'El nombre es obligatorio.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        try {
            $this->modelTipo->crear($codigo, $nombre, $descripcion, $estado ? 1 : 0);
            $_SESSION['unidades_msg'] = ['success', 'Tipo de unidad creado correctamente.'];
        } catch (\Throwable $e) {
            $_SESSION['unidades_msg'] = ['danger', 'Error al crear: ' . $e->getMessage()];
        }

        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    public function tipoUpdate(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $id = (int) ($_POST['id'] ?? 0);
        $codigo = trim($_POST['codigo'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $estado = !empty($_POST['estado']);

        if ($id <= 0) {
            $_SESSION['unidades_msg'] = ['danger', 'ID inválido.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }
        if ($nombre === '') {
            $_SESSION['unidades_msg'] = ['danger', 'El nombre es obligatorio.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($this->modelTipo->actualizar($id, $codigo, $nombre, $descripcion, $estado ? 1 : 0)) {
            $_SESSION['unidades_msg'] = ['success', 'Tipo de unidad actualizado correctamente.'];
        } else {
            $_SESSION['unidades_msg'] = ['danger', 'Error al actualizar.'];
        }

        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    public function unidadStore(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $idTipo = (int) ($_POST['id_tipo'] ?? 0);
        $codigo = trim($_POST['codigo'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $abreviatura = trim($_POST['abreviatura'] ?? '');
        $esBase = !empty($_POST['es_base']);
        $factorBase = (float) ($_POST['factor_base'] ?? 1);
        $estado = !empty($_POST['estado']);

        if ($idTipo <= 0) {
            $_SESSION['unidades_msg'] = ['danger', 'Seleccione un tipo de unidad.'];
            $this->redirect(BASE_URL . self::BASE_PATH . '?tab=unidades');
        }
        if ($nombre === '') {
            $_SESSION['unidades_msg'] = ['danger', 'El nombre es obligatorio.'];
            $this->redirect(BASE_URL . self::BASE_PATH . '?tab=unidades');
        }

        try {
            $this->modelUnidad->crear($idTipo, $codigo, $nombre, $abreviatura, $esBase ? 1 : 0, $factorBase, $estado ? 1 : 0);
            $_SESSION['unidades_msg'] = ['success', 'Unidad de medida creada correctamente.'];
        } catch (\Throwable $e) {
            $_SESSION['unidades_msg'] = ['danger', 'Error al crear: ' . $e->getMessage()];
        }

        $this->redirect(BASE_URL . self::BASE_PATH . '?tab=unidades');
    }

    public function unidadUpdate(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $id = (int) ($_POST['id'] ?? 0);
        $idTipo = (int) ($_POST['id_tipo'] ?? 0);
        $codigo = trim($_POST['codigo'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $abreviatura = trim($_POST['abreviatura'] ?? '');
        $esBase = !empty($_POST['es_base']);
        $factorBase = (float) ($_POST['factor_base'] ?? 1);
        $estado = !empty($_POST['estado']);

        if ($id <= 0) {
            $_SESSION['unidades_msg'] = ['danger', 'ID inválido.'];
            $this->redirect(BASE_URL . self::BASE_PATH . '?tab=unidades');
        }
        if ($idTipo <= 0) {
            $_SESSION['unidades_msg'] = ['danger', 'Seleccione un tipo de unidad.'];
            $this->redirect(BASE_URL . self::BASE_PATH . '?tab=unidades');
        }
        if ($nombre === '') {
            $_SESSION['unidades_msg'] = ['danger', 'El nombre es obligatorio.'];
            $this->redirect(BASE_URL . self::BASE_PATH . '?tab=unidades');
        }

        if ($this->modelUnidad->actualizar($id, $idTipo, $codigo, $nombre, $abreviatura, $esBase ? 1 : 0, $factorBase, $estado ? 1 : 0)) {
            $_SESSION['unidades_msg'] = ['success', 'Unidad de medida actualizada correctamente.'];
        } else {
            $_SESSION['unidades_msg'] = ['danger', 'Error al actualizar.'];
        }

        $this->redirect(BASE_URL . self::BASE_PATH . '?tab=unidades');
    }

    private function requireNivel(int $min): void
    {
        $nivel = (int) ($_SESSION['nivel'] ?? 0);
        if ($nivel < $min) {
            $_SESSION['unidades_msg'] = ['danger', 'No tiene permisos.'];
            header('Location: ' . BASE_URL . '/config');
            exit;
        }
    }
}
