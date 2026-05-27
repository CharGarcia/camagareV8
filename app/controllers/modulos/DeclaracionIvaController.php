<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\DeclaracionIvaRepository;
use App\Services\modulos\DeclaracionIvaService;

class DeclaracionIvaController extends BaseModuloController
{
    private $service;
    private $repository;

    protected function getRutaModulo(): string
    {
        return 'modulos/declaracion-iva';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new DeclaracionIvaRepository();
        $this->service    = new DeclaracionIvaService($this->repository);
    }

    public function index(): void
    {
        $this->requireLeer();
        
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $anio = $_GET['anio'] ?? date('Y');
        $mes  = $_GET['mes']  ?? date('m');

        $estructura = $this->repository->getEstructuraFormulario();
        $anios      = $this->repository->getAniosConVentas($idEmpresa);

        $this->viewWithLayout('layouts.main', 'modulos/declaracion_iva/index', [
            'titulo' => 'Declaración de IVA (form 104 SRI)',
            'perm' => $this->getPermisos(),
            'anio' => (int) $anio,
            'mes' => $mes,
            'anios' => $anios,
            'estructura' => $estructura,
            'base' => BASE_URL,
            'rutaModulo' => $this->getRutaModulo()
        ]);
    }

    public function auditarAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $anio = $_GET['anio'] ?? date('Y');
        $periodo = $_GET['periodo'] ?? date('m');
        $tipo = $_GET['tipo_periodo'] ?? 'mensual';

        try {
            if ($tipo === 'semestral') {
                // Periodo semestral: 1 = Ene-Jun, 2 = Jul-Dic
                if ($periodo == '1') {
                    $fechaDesde = "{$anio}-01-01";
                    $fechaHasta = "{$anio}-06-30";
                } else {
                    $fechaDesde = "{$anio}-07-01";
                    $fechaHasta = "{$anio}-12-31";
                }
            } else {
                // Periodo mensual
                $fechaDesde = "{$anio}-{$periodo}-01";
                $fechaHasta = date("Y-m-t", strtotime($fechaDesde));
            }

            $resultado = $this->service->auditarPeriodo($idEmpresa, (string)$anio, (string)$periodo);
            $resumen   = $this->repository->getResumenPorCasilleros($idEmpresa, $fechaDesde, $fechaHasta);
            $estructura = $this->repository->getEstructuraFormulario();
            
            $resultado['resumen'] = $resumen;
            $resultado['estructura'] = $estructura;
            
            echo json_encode($resultado);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function sincronizarAjax(): void
    {
        $this->requireActualizar(); // Requiere permiso de modificación para regenerar
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $anio = $_POST['anio'] ?? date('Y');
        $mes  = $_POST['mes']  ?? date('m');

        try {
            $resultado = $this->service->sincronizarPeriodo($idEmpresa, (string)$anio, (string)$mes, $idUsuario);
            echo json_encode($resultado);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }
}
