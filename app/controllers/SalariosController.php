<?php
/**
 * Controlador Salarios - Gestión de salarios por año
 * Tabla salarios. Año único. Clic en fila para editar.
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\Salario;

class SalariosController extends Controller
{
    private Salario $model;
    private const BASE_PATH = '/config/salarios';

    public function __construct()
    {
        parent::__construct();
        $this->model = new Salario();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        $ordenCol = trim($_GET['sort'] ?? 'ano');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? 'desc'));
        $buscar = trim($_GET['b'] ?? $_GET['buscar'] ?? '');
        if (!in_array($ordenCol, Salario::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'ano';
        }
        if ($ordenDir !== 'ASC' && $ordenDir !== 'DESC') {
            $ordenDir = 'DESC';
        }

        $rows = $this->model->getAll($ordenCol, $ordenDir, $buscar);

        $this->viewWithLayout('layouts.main', 'salarios.index', [
            'titulo' => 'Salarios',
            'rows' => $rows,
            'rowsHtml' => $this->renderFilasHtml($rows),
            'ordenCol' => $ordenCol,
            'ordenDir' => $ordenDir,
            'buscar' => $buscar,
        ]);
    }

    /**
     * AJAX: listado de salarios (tabla), para búsqueda y ordenamiento en
     * tiempo real sin recargar la página. Mismo patrón que
     * ConfigController::asientosTipoListAjax.
     */
    public function searchAjax(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);
        header('Content-Type: application/json');

        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? 'ano');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? 'DESC'));
        $buscar = trim($_GET['b'] ?? $_POST['b'] ?? '');
        if (!in_array($ordenCol, Salario::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'ano';
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
            return '<tr><td colspan="12" class="text-center py-5 text-muted"><i class="bi bi-cash-coin fs-3 d-block mb-2"></i>No hay salarios configurados.</td></tr>';
        }
        $html = '';
        foreach ($rows as $r) {
            $id = (int) ($r['id'] ?? $r['id_salario'] ?? 0);
            $status = (int) ($r['status'] ?? 1);
            $ano = (int) ($r['ano'] ?? date('Y'));
            $sbu = (float) ($r['sbu'] ?? 0);
            $horaNormal = (float) ($r['hora_normal'] ?? 0);
            $horaNocturna = (float) ($r['hora_nocturna'] ?? 0);
            $horaSuplementaria = (float) ($r['hora_suplementaria'] ?? 0);
            $horaExtraordinaria = (float) ($r['hora_extraordinaria'] ?? 0);
            $fondoReserva = (float) ($r['fondo_reserva'] ?? 0);
            $aportePersonal = (float) ($r['aporte_personal'] ?? 0);
            $aportePatronal = (float) ($r['aporte_patronal'] ?? 0);
            $extConyugue = (float) ($r['ext_conyugue'] ?? 0);
            $adicional = (float) ($r['adicional'] ?? 0);

            $html .= '<tr class="salario-row" role="button" tabindex="0" data-id="' . $id . '"'
                . ' data-ano="' . $ano . '"'
                . ' data-sbu="' . $sbu . '"'
                . ' data-hora-normal="' . $horaNormal . '"'
                . ' data-hora-nocturna="' . $horaNocturna . '"'
                . ' data-hora-suplementaria="' . $horaSuplementaria . '"'
                . ' data-hora-extraordinaria="' . $horaExtraordinaria . '"'
                . ' data-fondo-reserva="' . $fondoReserva . '"'
                . ' data-aporte-personal="' . $aportePersonal . '"'
                . ' data-aporte-patronal="' . $aportePatronal . '"'
                . ' data-ext-conyugue="' . $extConyugue . '"'
                . ' data-adicional="' . $adicional . '"'
                . ' data-status="' . $status . '">';
            $html .= '<td><strong>' . $ano . '</strong></td>';
            $html .= '<td class="text-end">' . number_format($sbu, 2, ',', '.') . '</td>';
            $html .= '<td class="text-end">' . number_format($horaNormal, 2, ',', '.') . '</td>';
            $html .= '<td class="text-end">' . number_format($horaNocturna, 2, ',', '.') . '%</td>';
            $html .= '<td class="text-end">' . number_format($horaSuplementaria, 2, ',', '.') . '%</td>';
            $html .= '<td class="text-end">' . number_format($horaExtraordinaria, 2, ',', '.') . '%</td>';
            $html .= '<td class="text-end">' . number_format($fondoReserva, 2, ',', '.') . '%</td>';
            $html .= '<td class="text-end">' . number_format($extConyugue, 2, ',', '.') . '%</td>';
            $html .= '<td class="text-end">' . number_format($aportePatronal, 2, ',', '.') . '%</td>';
            $html .= '<td class="text-end">' . number_format($aportePersonal, 2, ',', '.') . '%</td>';
            $html .= '<td class="text-end">' . number_format($adicional, 2, ',', '.') . '%</td>';
            $html .= '<td class="text-center">' . ($status ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>') . '</td>';
            $html .= '<td class="text-end">'
                . '<form method="POST" action="' . BASE_URL . '/config/salariosDelete" class="d-inline" onsubmit="return confirm(&quot;¿Eliminar esta configuración de salarios para el año ' . $ano . '?&quot;);" onclick="event.stopPropagation();">'
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
        $ano = (int) ($_POST['ano'] ?? 0);
        $sbu = (float) str_replace(',', '.', (string) ($_POST['sbu'] ?? 0));
        $horaNormal = (float) str_replace(',', '.', (string) ($_POST['hora_normal'] ?? 0));
        $horaNocturna = (float) str_replace(',', '.', (string) ($_POST['hora_nocturna'] ?? 0));
        $horaSuplementaria = (float) str_replace(',', '.', (string) ($_POST['hora_suplementaria'] ?? 0));
        $horaExtraordinaria = (float) str_replace(',', '.', (string) ($_POST['hora_extraordinaria'] ?? 0));
        $fondoReserva = (float) str_replace(',', '.', (string) ($_POST['fondo_reserva'] ?? 0));
        $aportePersonal = (float) str_replace(',', '.', (string) ($_POST['aporte_personal'] ?? 0));
        $aportePatronal = (float) str_replace(',', '.', (string) ($_POST['aporte_patronal'] ?? 0));
        $extConyugue = (float) str_replace(',', '.', (string) ($_POST['ext_conyugue'] ?? 0));
        $adicional = (float) str_replace(',', '.', (string) ($_POST['adicional'] ?? 0));
        $status = isset($_POST['status']) ? (int) $_POST['status'] : 1;

        if ($id <= 0) {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'ID inválido.']);
                return;
            }
            $_SESSION['salarios_msg'] = ['danger', 'ID inválido.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($ano < 1900 || $ano > 2100) {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'Año inválido.']);
                return;
            }
            $_SESSION['salarios_msg'] = ['danger', 'Año inválido.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($this->model->existeAno($ano, $id)) {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'Ya existe un salario configurado para el año ' . $ano . '.']);
                return;
            }
            $_SESSION['salarios_msg'] = ['danger', 'Ya existe un salario configurado para el año ' . $ano . '.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        try {
            if ($this->model->actualizar($id, $ano, $sbu, $horaNormal, $horaNocturna, $horaSuplementaria, $horaExtraordinaria, $fondoReserva, $aportePersonal, $aportePatronal, $extConyugue, $adicional, $status)) {
                if ($esAjax) {
                    $this->json(['ok' => true, 'msg' => 'Salario actualizado correctamente.']);
                    return;
                }
                $_SESSION['salarios_msg'] = ['success', 'Salario actualizado correctamente.'];
            } else {
                if ($esAjax) {
                    $this->json(['ok' => false, 'error' => 'Error al actualizar.']);
                    return;
                }
                $_SESSION['salarios_msg'] = ['danger', 'Error al actualizar.'];
            }
        } catch (\Throwable $e) {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'Error: ' . $e->getMessage()]);
                return;
            }
            $_SESSION['salarios_msg'] = ['danger', 'Error: ' . $e->getMessage()];
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
        $ano = (int) ($_POST['ano'] ?? 0);
        $sbu = (float) str_replace(',', '.', (string) ($_POST['sbu'] ?? 0));
        $horaNormal = (float) str_replace(',', '.', (string) ($_POST['hora_normal'] ?? 0));
        $horaNocturna = (float) str_replace(',', '.', (string) ($_POST['hora_nocturna'] ?? 0));
        $horaSuplementaria = (float) str_replace(',', '.', (string) ($_POST['hora_suplementaria'] ?? 0));
        $horaExtraordinaria = (float) str_replace(',', '.', (string) ($_POST['hora_extraordinaria'] ?? 0));
        $fondoReserva = (float) str_replace(',', '.', (string) ($_POST['fondo_reserva'] ?? 0));
        $aportePersonal = (float) str_replace(',', '.', (string) ($_POST['aporte_personal'] ?? 0));
        $aportePatronal = (float) str_replace(',', '.', (string) ($_POST['aporte_patronal'] ?? 0));
        $extConyugue = (float) str_replace(',', '.', (string) ($_POST['ext_conyugue'] ?? 0));
        $adicional = (float) str_replace(',', '.', (string) ($_POST['adicional'] ?? 0));
        $status = isset($_POST['status']) ? (int) $_POST['status'] : 1;

        if ($ano < 1900 || $ano > 2100) {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'Año inválido.']);
                return;
            }
            $_SESSION['salarios_msg'] = ['danger', 'Año inválido.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($this->model->existeAno($ano, null)) {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'Ya existe un salario configurado para el año ' . $ano . '.']);
                return;
            }
            $_SESSION['salarios_msg'] = ['danger', 'Ya existe un salario configurado para el año ' . $ano . '.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        try {
            $this->model->crear($ano, $sbu, $horaNormal, $horaNocturna, $horaSuplementaria, $horaExtraordinaria, $fondoReserva, $aportePersonal, $aportePatronal, $extConyugue, $adicional, $status);
            if ($esAjax) {
                $this->json(['ok' => true, 'msg' => 'Salario creado correctamente.']);
                return;
            }
            $_SESSION['salarios_msg'] = ['success', 'Salario creado correctamente.'];
        } catch (\Throwable $e) {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'Error al crear: ' . $e->getMessage()]);
                return;
            }
            $_SESSION['salarios_msg'] = ['danger', 'Error al crear: ' . $e->getMessage()];
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
            $_SESSION['salarios_msg'] = ['danger', 'ID inválido.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($this->model->eliminar($id)) {
            $_SESSION['salarios_msg'] = ['success', 'Salario eliminado correctamente.'];
        } else {
            $_SESSION['salarios_msg'] = ['danger', 'Error al eliminar.'];
        }

        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    private function requireNivel(int $min): void
    {
        $nivel = (int) ($_SESSION['nivel'] ?? 0);
        if ($nivel < $min) {
            $_SESSION['salarios_msg'] = ['danger', 'No tiene permisos.'];
            header('Location: ' . BASE_URL . '/config');
            exit;
        }
    }
}
