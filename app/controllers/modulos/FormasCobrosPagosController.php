<?php
declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\FormaPagoRepository;
use App\Services\modulos\FormaPagoService;
use App\Helpers\PreferenciasHelper;

class FormasCobrosPagosController extends BaseModuloController
{
    private FormaPagoService $service;

    protected function getRutaModulo(): string
    {
        return 'modulos/formas_cobros_pagos';
    }

    public function __construct()
    {
        parent::__construct();
        $this->service = new FormaPagoService(new FormaPagoRepository());
    }

    public function index(): void
    {
        $this->requireLeer();
        
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $prefsVista = PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());

        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'nombre');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'ASC'));
        $perPage  = 20;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir);
        $totalPages = (int) ceil($result['total'] / $perPage);

        // Cargar datos necesarios para el modal
        $bancos = $this->service->getBancos();

        $this->viewWithLayout('layouts.main', 'modulos/formas_cobros_pagos/index', [
            'titulo'      => 'Formas de Pago',
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
            'bancos'      => $bancos,
            'fullWidth'   => true
        ]);
    }

    public function getFormaAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id = (int)($_GET['id'] ?? 0);
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $data = $this->service->getPorId($id, $idEmpresa);

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
        
        $cuentas = $this->service->buscarCuentas($idEmpresa, $q);
        echo json_encode(['ok' => true, 'data' => $cuentas]);
        exit;
    }

    public function guardarAjax(): void
    {
        header('Content-Type: application/json');

        try {
            $data = $_POST;
            $data['id_empresa'] = (int)$_SESSION['id_empresa'];
            $data['usuario_id'] = (int)$_SESSION['id_usuario'];
            
            // Convertir checkbox activo a boolean real
            $data['activo'] = isset($_POST['activo']) && ($_POST['activo'] === '1' || $_POST['activo'] === 'on' || $_POST['activo'] === 'true');

            $id = (int)($data['id'] ?? 0);
            if ($id > 0) {
                $this->requireActualizar();
                $id = $this->service->guardar($data);
                $msg = 'Forma de pago actualizada satisfactoriamente.';
            } else {
                $this->requireCrear();
                $id = $this->service->guardar($data);
                $msg = 'Forma de pago registrada satisfactoriamente.';
            }
            
            echo json_encode([
                'ok' => true, 
                'mensaje' => $msg, 
                'id' => $id
            ]);
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
            $usuarioId = (int)$_SESSION['id_usuario'];

            $this->service->eliminar($id, $idEmpresa, $usuarioId);
            echo json_encode(['ok' => true, 'mensaje' => 'Registro eliminado exitosamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }
}
