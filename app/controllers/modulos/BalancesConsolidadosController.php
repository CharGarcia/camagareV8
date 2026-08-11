<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\ConsolidacionGruposRepository;
use App\repositories\modulos\EmpresaRepository;
use App\Rules\modulos\ConsolidacionGruposRules;
use App\Services\LogSistemaService;
use App\Services\modulos\ConsolidacionGruposService;
use Throwable;

/**
 * Módulo "Balances Consolidados" (Contabilidad): configuración de grupos de cuentas
 * equivalentes entre establecimientos del mismo RUC. Solo configura — el reporte
 * consolidado en sí se ve en Estados Financieros / Balance de Comprobación.
 */
class BalancesConsolidadosController extends BaseModuloController
{
    private ConsolidacionGruposService $service;

    protected function getRutaModulo(): string
    {
        return 'modulos/balances-consolidados';
    }

    public function __construct()
    {
        parent::__construct();
        $this->service = new ConsolidacionGruposService(
            new ConsolidacionGruposRepository(),
            new ConsolidacionGruposRules(),
            new LogSistemaService(),
            new EmpresaRepository()
        );
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        $idsGrupo = (new EmpresaRepository())->getIdsGrupoRucAccesible($idEmpresa, $idUsuario);

        $this->viewWithLayout('layouts.main', 'modulos.balances_consolidados.index', [
            'titulo'     => 'Balances Consolidados',
            'perm'       => $this->getPermisos(),
            'rutaModulo' => $this->getRutaModulo(),
            'hayGrupo'   => count($idsGrupo) > 1,
            'fullWidth'  => true,
        ]);
    }

    public function listarAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        try {
            $this->json(['ok' => true, 'data' => $this->service->listarGrupos($idEmpresa, $idUsuario)]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /** GET: establecimientos del RUC + su plan de cuentas (para armar el picker del modal). */
    public function establecimientosAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $idGrupoExcluir = !empty($_GET['id_grupo']) ? (int) $_GET['id_grupo'] : null;
        try {
            $this->json(['ok' => true, 'data' => $this->service->getEstablecimientosConCuentas($idEmpresa, $idUsuario, $idGrupoExcluir)]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    public function guardarAjax(): void
    {
        $this->requireCrear();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $data = json_decode(file_get_contents('php://input') ?: '[]', true) ?: $_POST;
        try {
            $res = $this->service->guardarGrupo($idEmpresa, $idUsuario, $data);
            $this->json(['ok' => true] + $res);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    public function eliminarAjax(): void
    {
        $this->requireEliminar();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $this->service->eliminarGrupo($idEmpresa, $idUsuario, $id);
            $this->json(['ok' => true]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }
}
