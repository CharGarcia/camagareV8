<?php
declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\PayphoneRepository;
use App\Services\PayphoneService;

class ConfiguracionPayphoneController extends BaseModuloController
{
    private PayphoneService    $pp;
    private PayphoneRepository $repo;
    private const RUTA_MODULO  = 'modulos/configuracion-payphone';

    public function __construct()
    {
        parent::__construct();
        $this->repo = new PayphoneRepository();
        $this->pp   = new PayphoneService($this->repo);
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function index(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $config    = $this->repo->getConfig($idEmpresa);
        $permisos  = $this->getPermisos();

        $this->viewWithLayout('layouts.main', 'modulos/configuracion_payphone/index', [
            'titulo'   => 'Configuración Payphone',
            'config'   => $config,
            'permisos' => $permisos,
            'urlBase'  => rtrim(BASE_URL, '/') . '/' . self::RUTA_MODULO,
        ]);
    }

    public function guardar(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $id > 0 ? $this->requireActualizar() : $this->requireCrear();
        header('Content-Type: application/json');

        try {
            $this->pp->guardarConfig([
                'id_empresa' => (int) $_SESSION['id_empresa'],
                'token'      => trim($_POST['token']    ?? ''),
                'store_id'   => trim($_POST['store_id'] ?? '') ?: null,
                'ambiente'   => in_array($_POST['ambiente'] ?? '', ['production', 'sandbox']) ? $_POST['ambiente'] : 'production',
                'activo'     => (($_POST['activo'] ?? '0') === '1'),
                'id_usuario' => (int) $_SESSION['id_usuario'],
            ]);
            echo json_encode(['ok' => true, 'mensaje' => 'Configuración guardada correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function probarConexion(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $resultado = $this->pp->testConexion((int) $_SESSION['id_empresa']);
            echo json_encode($resultado);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }
}
