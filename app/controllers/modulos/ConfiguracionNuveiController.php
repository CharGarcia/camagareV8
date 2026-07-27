<?php
declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\NuveiRepository;
use App\Services\NuveiService;

class ConfiguracionNuveiController extends BaseModuloController
{
    private NuveiService    $nuvei;
    private NuveiRepository $repo;
    private const RUTA_MODULO = 'modulos/configuracion-nuvei';

    public function __construct()
    {
        parent::__construct();
        $this->repo  = new NuveiRepository();
        $this->nuvei = new NuveiService($this->repo);
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

        $this->viewWithLayout('layouts.main', 'modulos/configuracion_nuvei/index', [
            'titulo'   => 'Configuración Nuvei',
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
            $this->nuvei->guardarConfig([
                'id_empresa'         => (int) $_SESSION['id_empresa'],
                'ambiente'           => in_array($_POST['ambiente'] ?? '', ['production', 'sandbox']) ? $_POST['ambiente'] : 'sandbox',
                'app_code_server'    => trim($_POST['app_code_server']    ?? '') ?: null,
                'app_key_server'     => trim($_POST['app_key_server']     ?? '') ?: null,
                'app_code_linktopay' => trim($_POST['app_code_linktopay'] ?? '') ?: null,
                'app_key_linktopay'  => trim($_POST['app_key_linktopay']  ?? '') ?: null,
                'activo_checkout'    => (($_POST['activo_checkout']  ?? '0') === '1'),
                'activo_addcard'     => (($_POST['activo_addcard']   ?? '0') === '1'),
                'activo_linktopay'   => (($_POST['activo_linktopay'] ?? '0') === '1'),
                'id_usuario'         => (int) $_SESSION['id_usuario'],
            ]);
            echo json_encode(['ok' => true, 'mensaje' => 'Configuración guardada correctamente.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function probarConexion(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $resultado = $this->nuvei->testConexion((int) $_SESSION['id_empresa']);
            echo json_encode($resultado);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function probarConexionLinktopay(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $resultado = $this->nuvei->testConexionLinkToPay((int) $_SESSION['id_empresa']);
            echo json_encode($resultado);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }
}
