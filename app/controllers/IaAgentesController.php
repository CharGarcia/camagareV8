<?php
/**
 * Controlador IaAgentes - Gestión del catálogo global de plantillas/prompts
 * de agentes de IA (módulo IA Soporte). Solo superadmin (nivel 3).
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\IaAgente;
use App\Services\LogSistemaService;

class IaAgentesController extends Controller
{
    private IaAgente $model;
    private const BASE_PATH = '/config/ia-agentes';

    public function __construct()
    {
        parent::__construct();
        $this->model = new IaAgente();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireNivel(3);

        $ordenCol = trim($_GET['sort'] ?? 'orden');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? 'asc'));
        $buscar = trim($_GET['b'] ?? $_GET['buscar'] ?? '');

        $rows = $this->model->getAll($ordenCol, $ordenDir, $buscar);

        $this->viewWithLayout('layouts.main', 'iaAgentes.index', [
            'titulo'   => 'Agentes de IA Soporte',
            'rows'     => $rows,
            'ordenCol' => $ordenCol,
            'ordenDir' => $ordenDir,
            'buscar'   => $buscar,
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->requireNivel(3);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $data = $this->recogerDatos();
        if ($data['nombre'] === '' || $data['prompt_sistema'] === '') {
            $_SESSION['ia_agentes_msg'] = ['danger', 'El nombre y el prompt del sistema son obligatorios.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        try {
            $data['created_by'] = (int) ($_SESSION['id_usuario'] ?? 0);
            $id = $this->model->crear($data);
            (new LogSistemaService())->registrar((int) $_SESSION['id_usuario'], null, 'crear', 'ia_agentes', $id, null, $data);
            $_SESSION['ia_agentes_msg'] = ['success', 'Agente creado correctamente.'];
        } catch (\Throwable $e) {
            $_SESSION['ia_agentes_msg'] = ['danger', 'Error al crear: ' . $e->getMessage()];
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
        $data = $this->recogerDatos();

        if ($id <= 0 || $data['nombre'] === '' || $data['prompt_sistema'] === '') {
            $_SESSION['ia_agentes_msg'] = ['danger', 'Datos inválidos.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $antes = $this->model->find($id);
        $data['updated_by'] = (int) ($_SESSION['id_usuario'] ?? 0);

        if ($this->model->actualizar($id, $data)) {
            (new LogSistemaService())->registrar((int) $_SESSION['id_usuario'], null, 'actualizar', 'ia_agentes', $id, $antes, $data);
            $_SESSION['ia_agentes_msg'] = ['success', 'Agente actualizado correctamente.'];
        } else {
            $_SESSION['ia_agentes_msg'] = ['danger', 'Error al actualizar.'];
        }

        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    public function delete(): void
    {
        $this->requireAuth();
        $this->requireNivel(3);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $id = (int) ($_POST['id'] ?? 0);
        $antes = $id > 0 ? $this->model->find($id) : null;

        if ($id > 0 && $antes && $this->model->eliminarLogico($id, (int) ($_SESSION['id_usuario'] ?? 0))) {
            (new LogSistemaService())->registrar((int) $_SESSION['id_usuario'], null, 'eliminar', 'ia_agentes', $id, $antes, null);
            $_SESSION['ia_agentes_msg'] = ['success', 'Agente eliminado correctamente.'];
        } else {
            $_SESSION['ia_agentes_msg'] = ['danger', 'No se pudo eliminar el agente.'];
        }

        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    private function recogerDatos(): array
    {
        return [
            'nombre'         => trim($_POST['nombre'] ?? ''),
            'descripcion'    => trim($_POST['descripcion'] ?? '') ?: null,
            'icono'          => trim($_POST['icono'] ?? '') ?: 'bi-robot',
            'prompt_sistema' => trim($_POST['prompt_sistema'] ?? ''),
            'orden'          => (int) ($_POST['orden'] ?? 0),
            'activo'         => !empty($_POST['activo']),
        ];
    }

    private function requireNivel(int $min): void
    {
        $nivel = (int) ($_SESSION['nivel'] ?? 0);
        if ($nivel < $min) {
            $_SESSION['ia_agentes_msg'] = ['danger', 'No tiene permisos. Solo el superadministrador gestiona los agentes de IA.'];
            header('Location: ' . BASE_URL . '/config');
            exit;
        }
    }
}
