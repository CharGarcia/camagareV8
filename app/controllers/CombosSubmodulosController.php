<?php
/**
 * Controlador CombosSubmodulos - Catálogo global de combos (paquetes) de submódulos.
 * CRUD y aplicar combo a un usuario: solo superadmin (nivel 3).
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\ComboSubmodulo;
use App\models\EmpresaAsignada;
use App\models\PermisoSubmodulo;

class CombosSubmodulosController extends Controller
{
    private ComboSubmodulo $model;
    private const BASE_PATH = '/config/combos-submodulos';

    public function __construct()
    {
        parent::__construct();
        $this->model = new ComboSubmodulo();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireNivel(3);

        $buscar = trim($_GET['buscar'] ?? '');
        $rows = $this->model->getAll($buscar);

        foreach ($rows as &$r) {
            $r['items'] = $this->model->getItems((int) $r['id']);
        }
        unset($r);

        $catalogo = (new PermisoSubmodulo())->getCatalogoSubmodulos();
        $modulosCatalogo = $this->agruparCatalogoPorModulo($catalogo);

        $msg = $_SESSION['combos_msg'] ?? null;
        unset($_SESSION['combos_msg']);

        $this->viewWithLayout('layouts.main', 'config.combosSubmodulos.index', [
            'titulo' => 'Combos de Submódulos',
            'rows' => $rows,
            'buscar' => $buscar,
            'modulosCatalogo' => $modulosCatalogo,
            'msg' => $msg,
        ]);
    }

    private function agruparCatalogoPorModulo(array $catalogo): array
    {
        $modulos = [];
        foreach ($catalogo as $r) {
            $idMod = (int) ($r['id_modulo'] ?? 0);
            $idSub = (int) ($r['id_submodulo'] ?? 0);
            if ($idMod <= 0 || $idSub <= 0) continue;
            if (!isset($modulos[$idMod])) {
                $modulos[$idMod] = [
                    'id_modulo' => $idMod,
                    'nombre_modulo' => $r['nombre_modulo'] ?? '',
                    'submodulos' => [],
                ];
            }
            $modulos[$idMod]['submodulos'][] = [
                'id_submodulo' => $idSub,
                'nombre_submodulo' => $r['nombre_submodulo'] ?? '',
            ];
        }
        return array_values($modulos);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->requireNivel(3);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
        $items = $this->parsearItems($_POST['submodulos'] ?? []);

        try {
            $this->model->crear([
                'nombre' => $_POST['nombre'] ?? '',
                'descripcion' => $_POST['descripcion'] ?? '',
                'precio' => $_POST['precio'] ?? null,
                'clase_color' => $_POST['clase_color'] ?? 'primary',
                'orden' => $_POST['orden'] ?? 0,
                'activo' => !empty($_POST['activo']),
            ], $items, $idUsuario);
            $_SESSION['combos_msg'] = ['success', 'Combo creado correctamente.'];
        } catch (\InvalidArgumentException $e) {
            $_SESSION['combos_msg'] = ['danger', $e->getMessage()];
        } catch (\Throwable $e) {
            $_SESSION['combos_msg'] = ['danger', 'Error al crear el combo: ' . $e->getMessage()];
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
            $_SESSION['combos_msg'] = ['danger', 'Identificador inválido.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
        $items = $this->parsearItems($_POST['submodulos'] ?? []);

        try {
            $ok = $this->model->actualizar($id, [
                'nombre' => $_POST['nombre'] ?? '',
                'descripcion' => $_POST['descripcion'] ?? '',
                'precio' => $_POST['precio'] ?? null,
                'clase_color' => $_POST['clase_color'] ?? 'primary',
                'orden' => $_POST['orden'] ?? 0,
                'activo' => !empty($_POST['activo']),
            ], $items, $idUsuario);
            $_SESSION['combos_msg'] = $ok
                ? ['success', 'Combo actualizado correctamente.']
                : ['danger', 'Error al actualizar el combo.'];
        } catch (\InvalidArgumentException $e) {
            $_SESSION['combos_msg'] = ['danger', $e->getMessage()];
        } catch (\Throwable $e) {
            $_SESSION['combos_msg'] = ['danger', 'Error al actualizar el combo: ' . $e->getMessage()];
        }

        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    public function eliminar(): void
    {
        $this->requireAuth();
        $this->requireNivel(3);

        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);

        if ($id <= 0) {
            $_SESSION['combos_msg'] = ['danger', 'Identificador inválido.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $ok = $this->model->eliminar($id, $idUsuario);
        $_SESSION['combos_msg'] = $ok
            ? ['success', 'Combo eliminado correctamente.']
            : ['danger', 'No se pudo eliminar el combo.'];

        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    /**
     * Aplica un combo a un usuario+empresa. Llamado por AJAX desde /config/permisos-modulos.
     * Cualquier admin (nivel 2+) que ya pueda gestionar a ese usuario y esa empresa.
     */
    public function aplicar(): void
    {
        $this->requireAuth();
        $this->requireNivel(3);
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
            exit;
        }

        $idCombo = (int) ($_POST['id_combo'] ?? 0);
        $idUsuario = (int) ($_POST['id_usuario'] ?? 0);
        $idEmpresa = (int) ($_POST['id_empresa'] ?? 0);
        $idActual = (int) ($_SESSION['id_usuario'] ?? 0);
        $nivel = (int) ($_SESSION['nivel'] ?? 1);

        if ($idCombo <= 0 || $idUsuario <= 0 || $idEmpresa <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Datos incompletos.']);
            exit;
        }

        if (!$this->puedeGestionar($idActual, $nivel, $idUsuario, $idEmpresa)) {
            echo json_encode(['ok' => false, 'error' => 'Sin permiso para gestionar ese usuario/empresa.']);
            exit;
        }

        $resultado = $this->model->aplicarAUsuario($idCombo, $idUsuario, $idEmpresa, $idActual);
        if (!$resultado['ok']) {
            echo json_encode(['ok' => false, 'error' => 'No se pudo aplicar el combo (verifique que esté activo y tenga submódulos).']);
            exit;
        }

        echo json_encode([
            'ok' => true,
            'aplicados' => $resultado['aplicados'],
            'combo' => $resultado['combo']['nombre'] ?? '',
        ]);
        exit;
    }

    private function puedeGestionar(int $idActual, int $nivel, int $idUsuarioDestino, int $idEmpresa): bool
    {
        if ($nivel >= 3) return true;
        if ($idActual === $idUsuarioDestino) {
            $empresas = (new EmpresaAsignada())->getEmpresasParaPermisos($idUsuarioDestino, $idActual, $nivel);
            foreach ($empresas as $e) {
                if ((int) ($e['id_empresa'] ?? 0) === $idEmpresa) return true;
            }
            return false;
        }
        $data = (new EmpresaAsignada())->getUsuariosAsignables($idActual, $nivel, '', 1, 1000);
        $puedeUsuario = false;
        foreach ($data['rows'] as $r) {
            if ((int) ($r['id_usuario'] ?? 0) === $idUsuarioDestino) { $puedeUsuario = true; break; }
        }
        if (!$puedeUsuario) return false;

        $empresas = (new EmpresaAsignada())->getEmpresasParaPermisos($idUsuarioDestino, $idActual, $nivel);
        foreach ($empresas as $e) {
            if ((int) ($e['id_empresa'] ?? 0) === $idEmpresa) return true;
        }
        return false;
    }

    /**
     * $submodulos viene como array de "idModulo:idSubmodulo" (checkboxes del formulario).
     */
    private function parsearItems(array $submodulos): array
    {
        $items = [];
        foreach ($submodulos as $par) {
            if (!is_string($par) || !str_contains($par, ':')) continue;
            [$idMod, $idSub] = explode(':', $par, 2);
            $idMod = (int) $idMod;
            $idSub = (int) $idSub;
            if ($idMod <= 0 || $idSub <= 0) continue;
            $items[] = ['id_modulo' => $idMod, 'id_submodulo' => $idSub];
        }
        return $items;
    }

    private function requireNivel(int $min): void
    {
        $nivel = (int) ($_SESSION['nivel'] ?? 0);
        if ($nivel < $min) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                $this->json(['ok' => false, 'error' => 'No tiene permisos.'], 403);
            }
            $_SESSION['combos_msg'] = ['danger', 'No tiene permisos.'];
            header('Location: ' . BASE_URL . '/config');
            exit;
        }
    }
}
