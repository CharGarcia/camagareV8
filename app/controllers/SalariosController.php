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
