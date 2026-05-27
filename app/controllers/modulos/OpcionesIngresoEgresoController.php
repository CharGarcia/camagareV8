<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\PlanCuentaRepository;
use App\Services\modulos\OpcionIngresoEgresoService;
use App\Helpers\PreferenciasHelper;

class OpcionesIngresoEgresoController extends BaseModuloController
{
    private OpcionIngresoEgresoService $service;

    protected function getRutaModulo(): string
    {
        return 'modulos/opciones_ingreso_egreso';
    }

    public function __construct()
    {
        parent::__construct();
        $this->service = new OpcionIngresoEgresoService();
    }

    public function index(): void
    {
        $this->requireLeer();
        
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $prefsVista = PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());

        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'nombre');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'ASC'));
        $perPage  = 20;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir);
        $totalPages = (int) ceil($result['total'] / $perPage);

        $this->viewWithLayout('layouts.main', 'modulos/opciones_ingreso_egreso/index', [
            'titulo'      => 'Opciones de Ingreso/Egreso',
            'perm'        => $this->getPermisos(),
            'rows'        => $result['rows'],
            'total'       => $result['total'],
            'page'        => $page,
            'totalPages'  => $totalPages,
            'perPage'     => $perPage,
            'from'        => $result['total'] > 0 ? (($page - 1) * $perPage) + 1 : 0,
            'to'          => $result['total'] > 0 ? min($page * $perPage, $result['total']) : 0,
            'buscar'      => $buscar,
            'ordenCol'    => $ordenCol,
            'ordenDir'    => $ordenDir,
            'vistaConfig' => $prefsVista,
            'rutaModulo'  => $this->getRutaModulo(),
            'fullWidth'   => true
        ]);
    }

    public function getAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $id = (int)($_GET['id'] ?? 0);
        $idEmpresa = (int)$_SESSION['id_empresa'];
        
        $data = $this->service->getById($id, $idEmpresa);
        if ($data) {
            echo json_encode(['ok' => true, 'data' => $data]);
        } else {
            echo json_encode(['ok' => false, 'mensaje' => 'Registro no encontrado.']);
        }
        exit;
    }

    public function searchCuentasAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $q = trim($_GET['q'] ?? '');
        $idEmpresa = (int)$_SESSION['id_empresa'];
        
        $repoCta = new PlanCuentaRepository();
        $cuentas = $repoCta->searchCuentas($idEmpresa, $q, '', 20);
        echo json_encode(['ok' => true, 'data' => $cuentas]);
        exit;
    }

    public function guardarAjax(): void
    {
        header('Content-Type: application/json');

        try {
            $data = $_POST;
            $data['id_empresa'] = (int)$_SESSION['id_empresa'];
            $data['id_usuario'] = (int)$_SESSION['id_usuario'];
            
            // Normalizar checks booleanos
            $data['aplica_ingresos'] = isset($_POST['aplica_ingresos']) && ($_POST['aplica_ingresos'] === 'on' || $_POST['aplica_ingresos'] === '1');
            $data['aplica_egresos'] = isset($_POST['aplica_egresos']) && ($_POST['aplica_egresos'] === 'on' || $_POST['aplica_egresos'] === '1');

            $id = (int)($data['id'] ?? 0);
            if ($id > 0) {
                $this->requireActualizar();
                $this->service->actualizar($id, $data['id_empresa'], $data);
                $msg = "Registro actualizado exitosamente.";
            } else {
                $this->requireCrear();
                $id = $this->service->registrar($data);
                $msg = "Registro creado exitosamente.";
            }

            echo json_encode(['ok' => true, 'mensaje' => $msg, 'id' => $id]);
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
            $id = (int)($_POST['id'] ?? 0);
            $idEmpresa = (int)$_SESSION['id_empresa'];
            $idUsuario = (int)$_SESSION['id_usuario'];

            $this->service->eliminar($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Registro eliminado correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }
}
