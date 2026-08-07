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
            'rowsHtml' => $this->renderFilasHtml($rows),
            'ordenCol' => $ordenCol,
            'ordenDir' => $ordenDir,
            'buscar' => $buscar,
        ]);
    }

    /**
     * AJAX: listado de retenciones SRI (tabla), para búsqueda y ordenamiento
     * en tiempo real sin recargar la página. Mismo patrón que
     * ConfigController::asientosTipoListAjax.
     */
    public function searchAjax(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);
        header('Content-Type: application/json');

        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? 'codigo_ret');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? 'ASC'));
        $buscar = trim($_GET['b'] ?? $_POST['b'] ?? '');
        if (!in_array($ordenCol, RetencionSri::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'codigo_ret';
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

    private function fmtFecha(?string $f): string
    {
        if ($f === null || $f === '' || $f === '0000-00-00' || $f === '0000-00-00 00:00:00') {
            return '';
        }
        $d = @strtotime($f);
        if (!$d || $d <= 0) {
            return '';
        }
        return date('d-m-Y', $d);
    }

    /**
     * Renderiza el <tbody> completo (filas o mensaje de "sin resultados").
     * Usado tanto por la carga inicial (vista) como por searchAjax.
     */
    private function renderFilasHtml(array $rows): string
    {
        if (empty($rows)) {
            return '<tr><td colspan="8" class="text-center py-5 text-muted"><i class="bi bi-receipt fs-3 d-block mb-2"></i>No hay retenciones registradas.</td></tr>';
        }
        $html = '';
        foreach ($rows as $r) {
            $id = (int) ($r['id'] ?? $r['id_ret'] ?? 0);
            $status = (int) ($r['status'] ?? 1);
            $desde = $r['desde'] ?? '';
            $hasta = $r['hasta'] ?? '';
            $html .= '<tr class="retencion-row" role="button" tabindex="0" data-id="' . $id . '"'
                . ' data-codigo="' . htmlspecialchars($r['codigo_ret'] ?? '') . '"'
                . ' data-concepto="' . htmlspecialchars($r['concepto_ret'] ?? '') . '"'
                . ' data-porcentaje="' . htmlspecialchars((string) ($r['porcentaje_ret'] ?? '')) . '"'
                . ' data-impuesto="' . htmlspecialchars($r['impuesto_ret'] ?? 'RENTA') . '"'
                . ' data-codanexo="' . htmlspecialchars($r['cod_anexo_ret'] ?? '') . '"'
                . ' data-status="' . $status . '"'
                . ' data-desde="' . htmlspecialchars($desde) . '"'
                . ' data-hasta="' . htmlspecialchars($hasta) . '">';
            $html .= '<td><code>' . htmlspecialchars($r['codigo_ret'] ?? '') . '</code></td>';
            $html .= '<td>' . htmlspecialchars($r['concepto_ret'] ?? '') . '</td>';
            $html .= '<td class="text-end">' . htmlspecialchars((string) ($r['porcentaje_ret'] ?? '')) . '</td>';
            $html .= '<td><span class="badge bg-secondary">' . htmlspecialchars($r['impuesto_ret'] ?? '') . '</span></td>';
            $html .= '<td>' . htmlspecialchars($r['cod_anexo_ret'] ?? '') . '</td>';
            $html .= '<td class="text-center">' . ($status ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>') . '</td>';
            $html .= '<td>' . htmlspecialchars($this->fmtFecha($desde) ?: '-') . '</td>';
            $html .= '<td>' . htmlspecialchars($this->fmtFecha($hasta) ?: '-') . '</td>';
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
