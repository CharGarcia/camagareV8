<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Services\modulos\ReasignarEstablecimientoService;
use App\repositories\modulos\ReasignarEstablecimientoRepository;
use Throwable;

/**
 * Módulo "Reasignar establecimiento": reasigna la sucursal propia (id_establecimiento) de
 * documentos ya registrados (típicamente migrados/importados en el establecimiento equivocado),
 * por filtros y en lote. No cambia el número del documento ni cascada a relacionados.
 */
class ReasignarEstablecimientoController extends BaseModuloController
{
    private const RUTA_MODULO = 'modulos/reasignar-establecimiento';
    private ReasignarEstablecimientoService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new ReasignarEstablecimientoService();
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function index(): void
    {
        $this->requireLeer();
        $perm = $this->getPermisos();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $establecimientos = $this->service->repo()->establecimientos($idEmpresa);

        $this->viewWithLayout('layouts.main', 'modulos.reasignar_establecimiento.index', [
            'titulo'           => 'Reasignar establecimiento',
            'perm'             => $perm,
            'rutaModulo'       => self::RUTA_MODULO,
            'establecimientos' => $establecimientos,
            'tipos'            => [
                'compras'           => 'Compras (incluye NC/ND de compra)',
                'retenciones_venta' => 'Retenciones de venta',
            ],
        ]);
    }

    /** GET: establecimientos activos de la empresa. */
    public function establecimientosAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $this->json(['ok' => true, 'data' => $this->service->repo()->establecimientos($idEmpresa)]);
    }

    /** GET: años/meses con documentos del tipo (para poblar los selectores desde datos reales). */
    public function periodosAjax(): void
    {
        $this->requireLeer();
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $tipo = (string) ($_GET['tipo'] ?? '');
            if (!in_array($tipo, ReasignarEstablecimientoRepository::tiposValidos(), true)) {
                throw new \RuntimeException('Tipo de documento no válido.');
            }
            $perm = $this->getPermisos();
            $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;
            $this->json(['ok' => true, 'data' => $this->service->repo()->periodos($idEmpresa, $tipo, $idUsuarioFiltro)]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /** GET: lista documentos por tipo + filtros. */
    public function listarAjax(): void
    {
        $this->requireLeer();
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $tipo = (string) ($_GET['tipo'] ?? '');
            if (!in_array($tipo, ReasignarEstablecimientoRepository::tiposValidos(), true)) {
                throw new \RuntimeException('Tipo de documento no válido.');
            }
            $perm = $this->getPermisos();
            $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

            $filtros = $this->filtrosGet();
            $page    = max(1, (int) ($_GET['page'] ?? 1));

            $res     = $this->service->repo()->listar($idEmpresa, $tipo, $filtros, $idUsuarioFiltro, $page, 200);
            $resumen = $this->service->repo()->resumenPorEstablecimiento($idEmpresa, $tipo, $filtros, $idUsuarioFiltro);
            $this->json(['ok' => true, 'rows' => $res['rows'], 'total' => $res['total'], 'resumen' => $resumen]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /** POST: reasigna los documentos seleccionados (ids[]) al establecimiento destino. */
    public function reasignarAjax(): void
    {
        $this->requireActualizar();
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];
            $tipo = (string) ($_POST['tipo'] ?? '');
            $idEstDestino = (int) ($_POST['id_establecimiento_destino'] ?? 0);
            $ids = $_POST['ids'] ?? [];
            if (!is_array($ids)) { $ids = array_filter(array_map('trim', explode(',', (string) $ids))); }

            $perm = $this->getPermisos();
            $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

            $res = $this->service->reasignar($idEmpresa, $tipo, $ids, $idEstDestino, $idUsuario, $idUsuarioFiltro);
            $this->json($res);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'reasignados' => 0, 'mensaje' => 'Error: ' . $e->getMessage()]);
        }
    }

    private function filtrosGet(): array
    {
        return [
            'desde'         => trim($_GET['desde'] ?? '') ?: null,
            'hasta'         => trim($_GET['hasta'] ?? '') ?: null,
            'id_est_origen' => (int) ($_GET['id_est_origen'] ?? 0),
            'buscar'        => trim($_GET['buscar'] ?? ''),
            'sin_establecimiento' => !empty($_GET['sin_establecimiento']) && $_GET['sin_establecimiento'] !== 'false',
        ];
    }
}
