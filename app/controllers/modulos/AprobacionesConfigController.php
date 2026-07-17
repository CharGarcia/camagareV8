<?php
declare(strict_types=1);

namespace App\controllers\modulos;

use App\Services\modulos\AprobacionesService;

/**
 * Configuración centralizada del motor de Aprobaciones: por empresa, qué
 * tipos exigen aprobación y quiénes son los aprobadores.
 */
class AprobacionesConfigController extends BaseModuloController
{
    private AprobacionesService $service;
    private const RUTA_MODULO = 'modulos/aprobaciones-config';

    public function __construct()
    {
        parent::__construct();
        $this->service = new AprobacionesService();
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);

        $this->viewWithLayout('layouts.main', 'modulos.aprobaciones_config.index', [
            'titulo'   => 'Configuración de Aprobaciones',
            'perm'     => $this->getPermisos(),
            'tipos'    => $this->service->getConfigEmpresa($idEmpresa),
            'usuarios' => $this->service->getUsuariosEmpresa($idEmpresa),
            'rutaModulo' => self::RUTA_MODULO,
            'fullWidth'  => false,
        ]);
    }

    public function guardarAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
        $idTipo    = (int) ($_POST['id_tipo'] ?? 0);

        if (!$idTipo) {
            echo json_encode(['ok' => false, 'mensaje' => 'Tipo inválido.']);
            return;
        }

        try {
            $this->service->guardarConfig($idEmpresa, $idTipo, [
                'requiere_aprobacion'  => !empty($_POST['requiere_aprobacion']),
                'usuarios_aprobadores' => $_POST['usuarios_aprobadores'] ?? [],
                'umbral_monto'         => $_POST['umbral_monto'] ?? null,
            ], $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Configuración guardada.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }
}
