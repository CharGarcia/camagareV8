<?php
/**
 * Controlador Modulo - CRUD de módulos y submodulos
 * Solo nivel 3 (Super Admin)
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\ModuloSubmodulo;

class ModuloController extends Controller
{
    private ModuloSubmodulo $model;
    private const PER_PAGE = 20;
    private const TODOS = 100000; // "sin paginación": la búsqueda es client-side
    private const BASE_PATH = '/config/modulo';

    public function __construct()
    {
        parent::__construct();
        $this->model = new ModuloSubmodulo();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireNivel(3);

        $tipo = trim($_GET['tipo'] ?? $_SESSION['modulo_vista']['tipo'] ?? 'modulos');
        if (!in_array($tipo, ['modulos', 'submodulos', 'iconos'], true)) {
            $tipo = 'modulos';
        }

        // Guardar pestaña activa en sesión (para volver tras guardar/editar)
        $_SESSION['modulo_vista'] = ['tipo' => $tipo];

        $modulos = $this->model->getModulos();
        $iconos = $this->model->getIconos();

        // Volumen bajo: cargamos TODAS las listas de una vez. El cambio de pestaña
        // y la búsqueda son client-side (instantáneos y con URL siempre limpia).
        $rowsModulos    = $this->model->getModulosListado('', 1, self::TODOS)['rows'];
        $rowsSubmodulos = $this->model->getSubmodulosListado('', 1, self::TODOS)['rows'];
        $rowsIconos     = $this->model->getIconosListado('', 1, self::TODOS)['rows'];

        $this->viewWithLayout('layouts.main', 'modulo.index', [
            'titulo' => 'Módulos y submódulos',
            'tipo' => $tipo,
            'rowsModulos' => $rowsModulos,
            'rowsSubmodulos' => $rowsSubmodulos,
            'rowsIconos' => $rowsIconos,
            'modulos' => $modulos,
            'iconos' => $iconos,
        ]);
    }

    public function storeModulo(): void
    {
        $this->requireAuth();
        $this->requireNivel(3);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['modulo_vista'] = $_SESSION['modulo_vista'] ?? ['tipo' => 'modulos', 'buscar' => '', 'page' => 1];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $nombre = trim($_POST['nombre_modulo'] ?? '');
        $idIcono = (int) ($_POST['id_icono'] ?? 0);

        if ($nombre === '') {
            $_SESSION['modulo_msg'] = ['danger', 'El nombre del módulo es obligatorio.'];
            $_SESSION['modulo_vista'] = ['tipo' => 'modulos', 'buscar' => '', 'page' => 1];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }
        if ($idIcono <= 0) {
            $_SESSION['modulo_msg'] = ['danger', 'Seleccione un icono.'];
            $_SESSION['modulo_vista'] = ['tipo' => 'modulos', 'buscar' => '', 'page' => 1];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }
        if ($this->model->existeModuloNombre($nombre)) {
            $_SESSION['modulo_msg'] = ['danger', 'Ya existe un módulo con ese nombre.'];
            $_SESSION['modulo_vista'] = ['tipo' => 'modulos', 'buscar' => '', 'page' => 1];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        try {
            $this->model->crearModulo($nombre, $idIcono);
            $_SESSION['modulo_msg'] = ['success', 'Módulo creado correctamente.'];
        } catch (\Throwable $e) {
            $_SESSION['modulo_msg'] = ['danger', 'Error al guardar: ' . $e->getMessage()];
        }
        $_SESSION['modulo_vista'] = ['tipo' => 'modulos', 'buscar' => '', 'page' => 1];
        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    public function updateModulo(): void
    {
        $this->requireAuth();
        $this->requireNivel(3);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['modulo_vista'] = $_SESSION['modulo_vista'] ?? ['tipo' => 'modulos', 'buscar' => '', 'page' => 1];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $idModulo = (int) ($_POST['mod_id_modulo'] ?? 0);
        $nombre = trim($_POST['mod_nombre_modulo'] ?? '');
        $idIcono = (int) ($_POST['mod_id_icono'] ?? 0);

        if ($idModulo <= 0 || $nombre === '' || $idIcono <= 0) {
            $_SESSION['modulo_msg'] = ['danger', 'Datos incompletos.'];
            $_SESSION['modulo_vista'] = ['tipo' => 'modulos', 'buscar' => '', 'page' => 1];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }
        if ($this->model->existeModuloNombre($nombre, $idModulo)) {
            $_SESSION['modulo_msg'] = ['danger', 'Ya existe un módulo con ese nombre.'];
            $_SESSION['modulo_vista'] = ['tipo' => 'modulos', 'buscar' => '', 'page' => 1];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        try {
            $this->model->actualizarModulo($idModulo, $nombre, $idIcono);
            $_SESSION['modulo_msg'] = ['success', 'Módulo actualizado correctamente.'];
        } catch (\Throwable $e) {
            $_SESSION['modulo_msg'] = ['danger', 'Error al actualizar: ' . $e->getMessage()];
        }
        $_SESSION['modulo_vista'] = ['tipo' => 'modulos', 'buscar' => '', 'page' => 1];
        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    public function storeSubmodulo(): void
    {
        $this->requireAuth();
        $this->requireNivel(3);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['modulo_vista'] = $_SESSION['modulo_vista'] ?? ['tipo' => 'submodulos', 'buscar' => '', 'page' => 1];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $idModulo = (int) ($_POST['id_modulo'] ?? 0);
        $nombre = trim($_POST['nombre_submodulo'] ?? '');
        $ruta = trim($_POST['ruta'] ?? '');
        $idIcono = (int) ($_POST['id_icono'] ?? 0);

        if ($idModulo <= 0) {
            $_SESSION['modulo_msg'] = ['danger', 'Seleccione un módulo.'];
            $_SESSION['modulo_vista'] = ['tipo' => 'submodulos', 'buscar' => '', 'page' => 1];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }
        if ($nombre === '') {
            $_SESSION['modulo_msg'] = ['danger', 'El nombre del submódulo es obligatorio.'];
            $_SESSION['modulo_vista'] = ['tipo' => 'submodulos', 'buscar' => '', 'page' => 1];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }
        if ($ruta === '') {
            $_SESSION['modulo_msg'] = ['danger', 'La ruta es obligatoria.'];
            $_SESSION['modulo_vista'] = ['tipo' => 'submodulos', 'buscar' => '', 'page' => 1];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }
        if ($idIcono <= 0) {
            $_SESSION['modulo_msg'] = ['danger', 'Seleccione un icono.'];
            $_SESSION['modulo_vista'] = ['tipo' => 'submodulos', 'buscar' => '', 'page' => 1];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }
        if ($this->model->existeSubmoduloNombre($nombre)) {
            $_SESSION['modulo_msg'] = ['danger', 'Ya existe un submódulo con ese nombre.'];
            $_SESSION['modulo_vista'] = ['tipo' => 'submodulos', 'buscar' => '', 'page' => 1];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        try {
            $this->model->crearSubmodulo($idModulo, $nombre, $ruta, $idIcono);
            $_SESSION['modulo_msg'] = ['success', 'Submódulo creado correctamente.'];
        } catch (\Throwable $e) {
            $_SESSION['modulo_msg'] = ['danger', 'Error al guardar: ' . $e->getMessage()];
        }
        $_SESSION['modulo_vista'] = ['tipo' => 'submodulos', 'buscar' => '', 'page' => 1];
        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    public function updateSubmodulo(): void
    {
        $this->requireAuth();
        $this->requireNivel(3);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['modulo_vista'] = $_SESSION['modulo_vista'] ?? ['tipo' => 'submodulos', 'buscar' => '', 'page' => 1];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $idSubmodulo = (int) ($_POST['mod_id_submodulo'] ?? 0);
        $idModulo = (int) ($_POST['mod_id_modulo_sub'] ?? 0);
        $nombre = trim($_POST['mod_nombre_submodulo'] ?? '');
        $ruta = trim($_POST['mod_ruta'] ?? '');
        $idIcono = (int) ($_POST['mod_id_icono_sub'] ?? 0);

        if ($idSubmodulo <= 0 || $idModulo <= 0 || $nombre === '' || $ruta === '' || $idIcono <= 0) {
            $_SESSION['modulo_msg'] = ['danger', 'Datos incompletos.'];
            $_SESSION['modulo_vista'] = ['tipo' => 'submodulos', 'buscar' => '', 'page' => 1];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }
        if ($this->model->existeSubmoduloNombre($nombre, $idSubmodulo)) {
            $_SESSION['modulo_msg'] = ['danger', 'Ya existe un submódulo con ese nombre.'];
            $_SESSION['modulo_vista'] = ['tipo' => 'submodulos', 'buscar' => '', 'page' => 1];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        try {
            $this->model->actualizarSubmodulo($idSubmodulo, $idModulo, $nombre, $ruta, $idIcono);
            $_SESSION['modulo_msg'] = ['success', 'Submódulo actualizado correctamente.'];
        } catch (\Throwable $e) {
            $_SESSION['modulo_msg'] = ['danger', 'Error al actualizar: ' . $e->getMessage()];
        }
        $_SESSION['modulo_vista'] = ['tipo' => 'submodulos', 'buscar' => '', 'page' => 1];
        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    public function deleteModulo(): void
    {
        $this->requireAuth();
        $this->requireNivel(3);

        $idModulo = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        if ($idModulo <= 0) {
            $_SESSION['modulo_msg'] = ['danger', 'ID inválido.'];
            $_SESSION['modulo_vista'] = ['tipo' => 'modulos', 'buscar' => '', 'page' => 1];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        try {
            $this->model->eliminarModulo($idModulo);
            $_SESSION['modulo_msg'] = ['success', 'Módulo eliminado correctamente.'];
        } catch (\Throwable $e) {
            $_SESSION['modulo_msg'] = ['danger', 'Error al eliminar: ' . $e->getMessage()];
        }
        $_SESSION['modulo_vista'] = ['tipo' => 'modulos', 'buscar' => '', 'page' => 1];
        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    public function deleteSubmodulo(): void
    {
        $this->requireAuth();
        $this->requireNivel(3);

        $idSubmodulo = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        if ($idSubmodulo <= 0) {
            $_SESSION['modulo_msg'] = ['danger', 'ID inválido.'];
            $_SESSION['modulo_vista'] = ['tipo' => 'submodulos', 'buscar' => '', 'page' => 1];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        try {
            $this->model->eliminarSubmodulo($idSubmodulo);
            $_SESSION['modulo_msg'] = ['success', 'Submódulo eliminado correctamente.'];
        } catch (\Throwable $e) {
            $_SESSION['modulo_msg'] = ['danger', 'Error al eliminar: ' . $e->getMessage()];
        }
        $_SESSION['modulo_vista'] = ['tipo' => 'submodulos', 'buscar' => '', 'page' => 1];
        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    public function toggleSubmoduloStatus(): void
    {
        $this->requireAuth();
        $this->requireNivel(3);

        $idSubmodulo = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        if ($idSubmodulo <= 0) {
            $_SESSION['modulo_msg'] = ['danger', 'ID inválido.'];
            $_SESSION['modulo_vista'] = ['tipo' => 'submodulos', 'buscar' => '', 'page' => 1];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        try {
            $this->model->toggleStatusSubmodulo($idSubmodulo);
            $_SESSION['modulo_msg'] = ['success', 'Estado actualizado.'];
        } catch (\Throwable $e) {
            $_SESSION['modulo_msg'] = ['danger', 'Error: ' . $e->getMessage()];
        }
        $_SESSION['modulo_vista'] = ['tipo' => 'submodulos', 'buscar' => '', 'page' => 1];
        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    public function storeIcono(): void
    {
        $this->requireAuth();
        $this->requireNivel(3);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['modulo_vista'] = $_SESSION['modulo_vista'] ?? ['tipo' => 'iconos', 'buscar' => '', 'page' => 1];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $nombre = trim($_POST['nombre_icono'] ?? '');
        if ($nombre === '') {
            $_SESSION['modulo_msg'] = ['danger', 'El nombre del icono es obligatorio.'];
            $_SESSION['modulo_vista'] = ['tipo' => 'iconos', 'buscar' => '', 'page' => 1];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }
        if ($this->model->existeIconoNombre($nombre)) {
            $_SESSION['modulo_msg'] = ['danger', 'Ya existe un icono con ese nombre.'];
            $_SESSION['modulo_vista'] = ['tipo' => 'iconos', 'buscar' => '', 'page' => 1];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        try {
            $this->model->crearIcono($nombre);
            $_SESSION['modulo_msg'] = ['success', 'Icono creado correctamente.'];
        } catch (\Throwable $e) {
            $_SESSION['modulo_msg'] = ['danger', 'Error al guardar: ' . $e->getMessage()];
        }
        $_SESSION['modulo_vista'] = ['tipo' => 'iconos', 'buscar' => '', 'page' => 1];
        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    public function updateIcono(): void
    {
        $this->requireAuth();
        $this->requireNivel(3);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['modulo_vista'] = $_SESSION['modulo_vista'] ?? ['tipo' => 'iconos', 'buscar' => '', 'page' => 1];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $id = (int) ($_POST['mod_id_icono'] ?? 0);
        $nombre = trim($_POST['mod_nombre_icono'] ?? '');
        if ($id <= 0 || $nombre === '') {
            $_SESSION['modulo_msg'] = ['danger', 'Datos incompletos.'];
            $_SESSION['modulo_vista'] = ['tipo' => 'iconos', 'buscar' => '', 'page' => 1];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }
        if ($this->model->existeIconoNombre($nombre, $id)) {
            $_SESSION['modulo_msg'] = ['danger', 'Ya existe un icono con ese nombre.'];
            $_SESSION['modulo_vista'] = ['tipo' => 'iconos', 'buscar' => '', 'page' => 1];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        try {
            $this->model->actualizarIcono($id, $nombre);
            $_SESSION['modulo_msg'] = ['success', 'Icono actualizado correctamente.'];
        } catch (\Throwable $e) {
            $_SESSION['modulo_msg'] = ['danger', 'Error al actualizar: ' . $e->getMessage()];
        }
        $_SESSION['modulo_vista'] = ['tipo' => 'iconos', 'buscar' => '', 'page' => 1];
        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    private function requireNivel(int $min): void
    {
        $nivel = (int) ($_SESSION['nivel'] ?? 0);
        if ($nivel < $min) {
            $_SESSION['modulo_msg'] = ['danger', 'No tiene permisos para acceder.'];
            header('Location: ' . BASE_URL . '/config');
            exit;
        }
    }
}
