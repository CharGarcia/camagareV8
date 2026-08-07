<?php
/**
 * Controlador IdentificadoresCompradorVendedor - Gestión de tipos de identificación comprador/vendedor
 * Tabla identificador_comprador_vendedor. Clic en fila para editar. Código único.
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\IdentificadorCompradorVendedor;

class IdentificadoresCompradorVendedorController extends Controller
{
    private IdentificadorCompradorVendedor $model;
    private const BASE_PATH = '/config/identificadores-comprador-vendedor';

    public function __construct()
    {
        parent::__construct();
        $this->model = new IdentificadorCompradorVendedor();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        $ordenCol = trim($_GET['sort'] ?? 'codigo');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? 'asc'));
        $buscar = trim($_GET['b'] ?? $_GET['buscar'] ?? '');
        if (!in_array($ordenCol, IdentificadorCompradorVendedor::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'codigo';
        }
        if ($ordenDir !== 'ASC' && $ordenDir !== 'DESC') {
            $ordenDir = 'ASC';
        }

        $rows = $this->model->getAll($ordenCol, $ordenDir, $buscar);

        $this->viewWithLayout('layouts.main', 'identificadoresCompradorVendedor.index', [
            'titulo' => 'Tipos de identificación comprador y vendedor',
            'rows' => $rows,
            'rowsHtml' => $this->renderFilasHtml($rows),
            'ordenCol' => $ordenCol,
            'ordenDir' => $ordenDir,
            'buscar' => $buscar,
        ]);
    }

    /**
     * AJAX: listado de identificadores comprador/vendedor (tabla), para
     * búsqueda y ordenamiento en tiempo real sin recargar la página. Mismo
     * patrón que ConfigController::asientosTipoListAjax.
     */
    public function searchAjax(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);
        header('Content-Type: application/json');

        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? 'codigo');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? 'ASC'));
        $buscar = trim($_GET['b'] ?? $_POST['b'] ?? '');
        if (!in_array($ordenCol, IdentificadorCompradorVendedor::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'codigo';
        }
        if ($ordenDir !== 'ASC' && $ordenDir !== 'DESC') {
            $ordenDir = 'ASC';
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
            return '<tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-person-badge fs-3 d-block mb-2"></i>No hay identificadores registrados.</td></tr>';
        }
        $html = '';
        foreach ($rows as $r) {
            $id = (int) ($r['id'] ?? $r['id_identificador_comprador_vendedor'] ?? 0);
            $status = (int) ($r['status'] ?? 1);
            $tipo = (int) ($r['tipo'] ?? 1);
            $tipoBadge = $tipo === 2
                ? '<span class="badge bg-primary"><i class="bi bi-shop"></i> Vendedor</span>'
                : '<span class="badge bg-info"><i class="bi bi-cart-check"></i> Comprador</span>';
            $html .= '<tr class="identificador-row" role="button" tabindex="0" data-id="' . $id . '"'
                . ' data-codigo="' . htmlspecialchars($r['codigo'] ?? '') . '"'
                . ' data-nombre="' . htmlspecialchars($r['nombre'] ?? '') . '"'
                . ' data-tipo="' . $tipo . '"'
                . ' data-status="' . $status . '">';
            $html .= '<td><code>' . htmlspecialchars($r['codigo'] ?? '') . '</code></td>';
            $html .= '<td>' . htmlspecialchars($r['nombre'] ?? '') . '</td>';
            $html .= '<td>' . $tipoBadge . '</td>';
            $html .= '<td class="text-center">' . ($status ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>') . '</td>';
            $html .= '<td class="text-end">'
                . '<form method="POST" action="' . BASE_URL . '/config/identificadoresCompradorVendedorDelete" class="d-inline" onsubmit="return confirm(&quot;¿Eliminar este identificador?&quot;);" onclick="event.stopPropagation();">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . '<button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1" title="Eliminar"><i class="bi bi-trash"></i></button>'
                . '</form></td>';
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

        $esAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
        $id = (int) ($_POST['id'] ?? 0);
        $codigo = trim((string) ($_POST['codigo'] ?? ''));
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $tipo = isset($_POST['tipo']) ? (int) $_POST['tipo'] : 1;
        $status = isset($_POST['status']) ? (int) $_POST['status'] : 1;

        if ($id <= 0) {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'ID inválido.']);
                return;
            }
            $_SESSION['identificadores_comprador_vendedor_msg'] = ['danger', 'ID inválido.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($codigo === '') {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'El código es obligatorio.']);
                return;
            }
            $_SESSION['identificadores_comprador_vendedor_msg'] = ['danger', 'El código es obligatorio.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($nombre === '') {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'El nombre es obligatorio.']);
                return;
            }
            $_SESSION['identificadores_comprador_vendedor_msg'] = ['danger', 'El nombre es obligatorio.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($this->model->existeCodigo($codigo, $id)) {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'Ya existe un identificador con ese código.']);
                return;
            }
            $_SESSION['identificadores_comprador_vendedor_msg'] = ['danger', 'Ya existe un identificador con ese código.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        try {
            if ($this->model->actualizar($id, $codigo, $nombre, $tipo, $status ? 1 : 0)) {
                if ($esAjax) {
                    $this->json(['ok' => true, 'msg' => 'Identificador actualizado correctamente.']);
                    return;
                }
                $_SESSION['identificadores_comprador_vendedor_msg'] = ['success', 'Identificador actualizado correctamente.'];
            } else {
                if ($esAjax) {
                    $this->json(['ok' => false, 'error' => 'Error al actualizar.']);
                    return;
                }
                $_SESSION['identificadores_comprador_vendedor_msg'] = ['danger', 'Error al actualizar.'];
            }
        } catch (\Throwable $e) {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'Error: ' . $e->getMessage()]);
                return;
            }
            $_SESSION['identificadores_comprador_vendedor_msg'] = ['danger', 'Error: ' . $e->getMessage()];
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
        $codigo = trim((string) ($_POST['codigo'] ?? ''));
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $tipo = isset($_POST['tipo']) ? (int) $_POST['tipo'] : 1;
        $status = isset($_POST['status']) ? (int) $_POST['status'] : 1;

        if ($codigo === '') {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'El código es obligatorio.']);
                return;
            }
            $_SESSION['identificadores_comprador_vendedor_msg'] = ['danger', 'El código es obligatorio.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($nombre === '') {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'El nombre es obligatorio.']);
                return;
            }
            $_SESSION['identificadores_comprador_vendedor_msg'] = ['danger', 'El nombre es obligatorio.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($this->model->existeCodigo($codigo, null)) {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'Ya existe un identificador con ese código.']);
                return;
            }
            $_SESSION['identificadores_comprador_vendedor_msg'] = ['danger', 'Ya existe un identificador con ese código.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        try {
            $this->model->crear($codigo, $nombre, $tipo, $status ? 1 : 0);
            if ($esAjax) {
                $this->json(['ok' => true, 'msg' => 'Identificador creado correctamente.']);
                return;
            }
            $_SESSION['identificadores_comprador_vendedor_msg'] = ['success', 'Identificador creado correctamente.'];
        } catch (\Throwable $e) {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'Error al crear: ' . $e->getMessage()]);
                return;
            }
            $_SESSION['identificadores_comprador_vendedor_msg'] = ['danger', 'Error al crear: ' . $e->getMessage()];
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
            $_SESSION['identificadores_comprador_vendedor_msg'] = ['danger', 'ID inválido.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($this->model->eliminar($id)) {
            $_SESSION['identificadores_comprador_vendedor_msg'] = ['success', 'Identificador eliminado correctamente.'];
        } else {
            $_SESSION['identificadores_comprador_vendedor_msg'] = ['danger', 'Error al eliminar.'];
        }

        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    private function requireNivel(int $min): void
    {
        $nivel = (int) ($_SESSION['nivel'] ?? 0);
        if ($nivel < $min) {
            $_SESSION['identificadores_comprador_vendedor_msg'] = ['danger', 'No tiene permisos.'];
            header('Location: ' . BASE_URL . '/config');
            exit;
        }
    }
}
