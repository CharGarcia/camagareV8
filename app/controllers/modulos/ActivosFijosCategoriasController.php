<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\ActivoFijoCategoriaRepository;
use App\Rules\modulos\ActivoFijoCategoriaRules;
use App\Services\LogSistemaService;
use App\Services\modulos\ActivoFijoCategoriaService;

class ActivosFijosCategoriasController extends BaseModuloController
{
    private ActivoFijoCategoriaService $service;

    protected function getRutaModulo(): string
    {
        return 'modulos/activos-fijos-categorias';
    }

    public function __construct()
    {
        parent::__construct();
        $repository = new ActivoFijoCategoriaRepository();
        $this->service = new ActivoFijoCategoriaService(
            $repository,
            new ActivoFijoCategoriaRules($repository),
            new LogSistemaService()
        );
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['b'] ?? '');

        $this->viewWithLayout('layouts.main', 'modulos/activos_fijos_categorias/index', [
            'titulo'     => 'Categorías de Activos Fijos',
            'perm'       => $this->getPermisos(),
            'rows'       => $this->service->getListado($idEmpresa, $buscar),
            'buscar'     => $buscar,
            'rutaModulo' => $this->getRutaModulo(),
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['b'] ?? '');
        $rows = $this->service->getListado($idEmpresa, $buscar);

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-diagram-3 fs-3 d-block mb-2"></i>No hay categorías registradas.</td></tr>';
        } else {
            foreach ($rows as $r) {
                // Postgres vía PDO devuelve boolean como 't'/'f': empty('f') sería false, hay que comparar explícito.
                $estadoActivo = ($r['estado'] === true || $r['estado'] === 't' || $r['estado'] === '1' || $r['estado'] === 1);
                $estCls = $estadoActivo ? 'bg-success bg-opacity-10 text-success border-success' : 'bg-secondary bg-opacity-10 text-secondary border-secondary';
                $estTxt = $estadoActivo ? 'Activa' : 'Inactiva';
                echo '<tr role="button" onclick="AFC_abrirModal(' . (int) $r['id'] . ')">
                        <td class="ps-3" data-col="nombre">' . htmlspecialchars($r['nombre']) . '</td>
                        <td class="text-end" data-col="porcentaje">' . number_format((float) $r['porcentaje_depreciacion_anual'], 2) . '%</td>
                        <td data-col="cuenta_activo">' . htmlspecialchars(($r['cuenta_activo_codigo'] ?? '') . ' - ' . ($r['cuenta_activo_nombre'] ?? '')) . '</td>
                        <td data-col="cuenta_dep_acum">' . htmlspecialchars(($r['cuenta_dep_acum_codigo'] ?? '') . ' - ' . ($r['cuenta_dep_acum_nombre'] ?? '')) . '</td>
                        <td data-col="cuenta_gasto">' . htmlspecialchars(($r['cuenta_gasto_codigo'] ?? '') . ' - ' . ($r['cuenta_gasto_nombre'] ?? '')) . '</td>
                        <td class="text-center pe-3" data-col="estado"><span class="badge ' . $estCls . ' border border-opacity-25">' . $estTxt . '</span></td>
                      </tr>';
            }
        }
        $rowsHtml = ob_get_clean();

        echo json_encode(['ok' => true, 'rows' => $rowsHtml, 'total' => count($rows)]);
        exit;
    }

    public function getCategoriaAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $categoria = $this->service->getPorId($id, $idEmpresa);

        echo json_encode($categoria ? ['ok' => true, 'data' => $categoria] : ['ok' => false, 'mensaje' => 'Categoría no encontrada.']);
        exit;
    }

    /** Categorías activas para el select del modal "Nuevo Activo Fijo". */
    public function getActivasAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        echo json_encode(['ok' => true, 'data' => $this->service->getActivasParaSelect($idEmpresa)]);
        exit;
    }

    public function guardarAjax(): void
    {
        if (!empty($_POST['id'])) {
            $this->requireActualizar();
        } else {
            $this->requireCrear();
        }
        header('Content-Type: application/json');

        try {
            $data = $_POST;
            $data['id_empresa'] = (int) $_SESSION['id_empresa'];
            $data['id_usuario'] = (int) $_SESSION['id_usuario'];

            $idExistente = !empty($data['id']) ? (int) $data['id'] : 0;
            $id = $idExistente > 0 ? $this->service->actualizar($idExistente, $data) : $this->service->crear($data);

            echo json_encode(['ok' => true, 'mensaje' => 'Categoría guardada correctamente.', 'id' => $id]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function eliminarAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        try {
            $id = (int) ($_POST['id'] ?? 0);
            $this->service->eliminar($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            echo json_encode(['ok' => true, 'mensaje' => 'Categoría eliminada correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }
}
