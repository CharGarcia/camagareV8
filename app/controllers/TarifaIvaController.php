<?php
/**
 * Controlador TarifaIva - Gestión de tarifas IVA
 * Tabla tarifa_iva. Clic en fila para editar.
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\TarifaIva;

class TarifaIvaController extends Controller
{
    private TarifaIva $model;
    private const BASE_PATH = '/config/tarifa-iva';

    public function __construct()
    {
        parent::__construct();
        $this->model = new TarifaIva();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        $ordenCol = trim($_GET['sort'] ?? 'porcentaje_iva');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? 'asc'));
        $buscar = trim($_GET['b'] ?? $_GET['buscar'] ?? '');
        if (!in_array($ordenCol, TarifaIva::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'porcentaje_iva';
        }
        if ($ordenDir !== 'ASC' && $ordenDir !== 'DESC') {
            $ordenDir = 'ASC';
        }

        $rows = $this->model->getAll($ordenCol, $ordenDir, $buscar);

        $this->viewWithLayout('layouts.main', 'tarifaIva.index', [
            'titulo' => 'Tarifas IVA',
            'rows' => $rows,
            'rowsHtml' => $this->renderFilasHtml($rows),
            'ordenCol' => $ordenCol,
            'ordenDir' => $ordenDir,
            'buscar' => $buscar,
        ]);
    }

    /**
     * AJAX: listado de tarifas IVA (tabla), para búsqueda y ordenamiento en
     * tiempo real sin recargar la página. Mismo patrón que
     * ConfigController::asientosTipoListAjax.
     */
    public function searchAjax(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);
        header('Content-Type: application/json');

        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? 'porcentaje_iva');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? 'ASC'));
        $buscar = trim($_GET['b'] ?? $_POST['b'] ?? '');
        if (!in_array($ordenCol, TarifaIva::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'porcentaje_iva';
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
            return '<tr><td colspan="4" class="text-center py-5 text-muted"><i class="bi bi-percent fs-3 d-block mb-2"></i>No hay tarifas IVA registradas.</td></tr>';
        }
        $html = '';
        foreach ($rows as $r) {
            $id = (int) ($r['id'] ?? 0);
            $status = (int) ($r['status'] ?? 1);
            $html .= '<tr class="tarifa-row" role="button" tabindex="0" data-id="' . $id . '"'
                . ' data-codigo="' . htmlspecialchars($r['codigo'] ?? '') . '"'
                . ' data-tarifa="' . htmlspecialchars($r['tarifa'] ?? '') . '"'
                . ' data-porcentaje="' . (int) ($r['porcentaje_iva'] ?? 0) . '"'
                . ' data-status="' . $status . '">';
            $html .= '<td><code>' . htmlspecialchars($r['codigo'] ?? '') . '</code></td>';
            $html .= '<td>' . htmlspecialchars($r['tarifa'] ?? '') . '</td>';
            $html .= '<td class="text-center">' . (int) ($r['porcentaje_iva'] ?? 0) . '%</td>';
            $html .= '<td class="text-center">' . ($status ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>') . '</td>';
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
        $codigo = trim($_POST['codigo'] ?? '');
        $tarifa = trim($_POST['tarifa'] ?? '');
        $porcentajeIva = (int) ($_POST['porcentaje_iva'] ?? 0);
        $status = !empty($_POST['status']);

        if ($id <= 0) {
            $_SESSION['tarifa_iva_msg'] = ['danger', 'ID inválido.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($codigo === '') {
            $_SESSION['tarifa_iva_msg'] = ['danger', 'El código es obligatorio.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($this->model->actualizar($id, $codigo, $tarifa, $porcentajeIva, $status ? 1 : 0)) {
            $_SESSION['tarifa_iva_msg'] = ['success', 'Tarifa IVA actualizada correctamente.'];
        } else {
            $_SESSION['tarifa_iva_msg'] = ['danger', 'Error al actualizar.'];
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

        $codigo = trim($_POST['codigo'] ?? '');
        $tarifa = trim($_POST['tarifa'] ?? '');
        $porcentajeIva = (int) ($_POST['porcentaje_iva'] ?? 0);
        $status = !empty($_POST['status']);

        if ($codigo === '') {
            $_SESSION['tarifa_iva_msg'] = ['danger', 'El código es obligatorio.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        try {
            $this->model->crear($codigo, $tarifa, $porcentajeIva, $status ? 1 : 0);
            $_SESSION['tarifa_iva_msg'] = ['success', 'Tarifa IVA creada correctamente.'];
        } catch (\Throwable $e) {
            $_SESSION['tarifa_iva_msg'] = ['danger', 'Error al crear: ' . $e->getMessage()];
        }

        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    private function requireNivel(int $min): void
    {
        $nivel = (int) ($_SESSION['nivel'] ?? 0);
        if ($nivel < $min) {
            $_SESSION['tarifa_iva_msg'] = ['danger', 'No tiene permisos.'];
            header('Location: ' . BASE_URL . '/config');
            exit;
        }
    }
}
