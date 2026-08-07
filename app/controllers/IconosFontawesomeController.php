<?php
/**
 * Controlador IconosFontawesome - Gestión de iconos FontAwesome
 * Tabla iconos_fontawesome. Clic en fila para editar.
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\IconoFontawesome;

class IconosFontawesomeController extends Controller
{
    private IconoFontawesome $model;
    private const BASE_PATH = '/config/iconos-fontawesome';

    public function __construct()
    {
        parent::__construct();
        $this->model = new IconoFontawesome();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        $ordenCol = trim($_GET['sort'] ?? 'nombre_icono');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? 'asc'));
        $buscar = trim($_GET['b'] ?? $_GET['buscar'] ?? '');
        if (!in_array($ordenCol, IconoFontawesome::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'nombre_icono';
        }
        if ($ordenDir !== 'ASC' && $ordenDir !== 'DESC') {
            $ordenDir = 'ASC';
        }

        $rows = $this->model->getAll($ordenCol, $ordenDir, $buscar);
        $refsMap = $this->model->contarReferenciasPorIds($this->extraerIds($rows));

        $this->viewWithLayout('layouts.main', 'iconosFontawesome.index', [
            'titulo' => 'Iconos FontAwesome',
            'rows' => $rows,
            'refsMap' => $refsMap,
            'rowsHtml' => $this->renderFilasHtml($rows, $refsMap),
            'ordenCol' => $ordenCol,
            'ordenDir' => $ordenDir,
            'buscar' => $buscar,
        ]);
    }

    /**
     * AJAX: listado de iconos (tabla), para búsqueda y ordenamiento en tiempo
     * real sin recargar la página. Mismo patrón que
     * ConfigController::asientosTipoListAjax.
     */
    public function searchAjax(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);
        header('Content-Type: application/json');

        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? 'nombre_icono');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? 'ASC'));
        $buscar = trim($_GET['b'] ?? $_POST['b'] ?? '');
        if (!in_array($ordenCol, IconoFontawesome::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'nombre_icono';
        }
        if ($ordenDir !== 'ASC' && $ordenDir !== 'DESC') {
            $ordenDir = 'ASC';
        }

        $rows = $this->model->getAll($ordenCol, $ordenDir, $buscar);
        $refsMap = $this->model->contarReferenciasPorIds($this->extraerIds($rows));

        echo json_encode([
            'ok' => true,
            'rows' => $this->renderFilasHtml($rows, $refsMap),
        ]);
        exit;
    }

    private function extraerIds(array $rows): array
    {
        $ids = [];
        foreach ($rows as $r) {
            $ids[] = (int) ($r['id'] ?? $r['id_icono'] ?? 0);
        }
        return $ids;
    }

    /**
     * Renderiza el <tbody> completo (filas o mensaje de "sin resultados").
     * Usado tanto por la carga inicial (vista) como por searchAjax.
     */
    private function renderFilasHtml(array $rows, array $refsMap): string
    {
        if (empty($rows)) {
            return '<tr><td colspan="3" class="text-center py-5 text-muted"><i class="bi bi-emoji-smile fs-3 d-block mb-2"></i>No hay iconos registrados.</td></tr>';
        }
        $html = '';
        foreach ($rows as $r) {
            $id = (int) ($r['id'] ?? $r['id_icono'] ?? 0);
            $nombreIcono = $r['nombre_icono'] ?? '';
            $refs = (int) ($refsMap[$id] ?? 0);
            $cls = iconoClase($nombreIcono);
            $html .= '<tr class="icono-row" role="button" tabindex="0" data-id="' . $id . '" data-nombre="' . htmlspecialchars($nombreIcono) . '" data-refs="' . $refs . '">';
            $html .= '<td class="icon-preview"><i class="' . htmlspecialchars($cls) . '" title="' . htmlspecialchars($nombreIcono) . '"></i></td>';
            $html .= '<td><code>' . htmlspecialchars($nombreIcono) . '</code></td>';
            $html .= '<td class="text-end small text-muted">' . $refs . '</td>';
            $html .= '</tr>';
        }
        return $html;
    }

    public function update(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $id = (int) ($_POST['id'] ?? 0);
        $nombreIcono = trim($_POST['nombre_icono'] ?? '');

        if ($id <= 0) {
            $_SESSION['iconos_msg'] = ['danger', 'ID inválido.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($nombreIcono === '') {
            $_SESSION['iconos_msg'] = ['danger', 'El nombre del icono es obligatorio.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($this->model->existeNombreOtro($nombreIcono, $id)) {
            $_SESSION['iconos_msg'] = ['danger', 'Ya existe otro icono con el mismo nombre.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($this->model->actualizar($id, $nombreIcono)) {
            $_SESSION['iconos_msg'] = ['success', 'Icono actualizado correctamente.'];
        } else {
            $_SESSION['iconos_msg'] = ['danger', 'Error al actualizar.'];
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

        $nombreIcono = trim($_POST['nombre_icono'] ?? '');

        if ($nombreIcono === '') {
            $_SESSION['iconos_msg'] = ['danger', 'El nombre del icono es obligatorio.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($this->model->existeNombreOtro($nombreIcono, null)) {
            $_SESSION['iconos_msg'] = ['danger', 'Ya existe otro icono con el mismo nombre.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        try {
            $this->model->crear($nombreIcono);
            $_SESSION['iconos_msg'] = ['success', 'Icono creado correctamente.'];
        } catch (\Throwable $e) {
            $_SESSION['iconos_msg'] = ['danger', 'Error al crear: ' . $e->getMessage()];
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
            $_SESSION['iconos_msg'] = ['danger', 'ID inválido.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($this->model->contarReferenciasEnMenus($id) > 0) {
            $_SESSION['iconos_msg'] = ['danger', 'No se puede eliminar: el icono está asignado en módulos o submódulos del menú.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($this->model->eliminar($id)) {
            $_SESSION['iconos_msg'] = ['success', 'Icono eliminado correctamente.'];
        } else {
            $_SESSION['iconos_msg'] = ['danger', 'No se pudo eliminar el icono.'];
        }

        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    private function requireNivel(int $min): void
    {
        $nivel = (int) ($_SESSION['nivel'] ?? 0);
        if ($nivel < $min) {
            $_SESSION['iconos_msg'] = ['danger', 'No tiene permisos.'];
            header('Location: ' . BASE_URL . '/config');
            exit;
        }
    }
}
