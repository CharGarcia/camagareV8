<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Services\modulos\ReasignarEstablecimientoService;
use App\repositories\modulos\ReasignarEstablecimientoRepository;
use Throwable;

/**
 * Módulo "Reasignar establecimiento": reasigna la sucursal propia (id_establecimiento) de
 * documentos ya registrados (típicamente migrados/importados en el establecimiento equivocado),
 * por filtros y en lote. No cambia el número del documento. Si el documento tiene contabilidad,
 * pagos o inventario generados, requiere confirmación explícita para anularlos y reasignar (ver
 * ReasignarEstablecimientoService::reasignar()); si tiene retención de compra, queda bloqueado.
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

        // "Origen" (filtro de la lista): solo los establecimientos de la empresa activa, porque
        // el listado de documentos solo muestra los de esta empresa. "Destino" (a dónde mover):
        // también los de otras empresas del mismo grupo RUC — ver ReasignarEstablecimientoService.
        $establecimientos = $this->service->repo()->establecimientos($idEmpresa);
        $establecimientosDestino = $this->service->establecimientosGrupoRuc($idEmpresa);

        $this->viewWithLayout('layouts.main', 'modulos.reasignar_establecimiento.index', [
            'titulo'           => 'Reasignar documentos a otro establecimiento',
            'perm'             => $perm,
            'rutaModulo'       => self::RUTA_MODULO,
            'establecimientos' => $establecimientos,
            'establecimientosDestino' => $establecimientosDestino,
            'idEmpresaActual'  => $idEmpresa,
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
            // Sin filtro de "registros propios": esta herramienta corrige documentos migrados/
            // importados en lote, casi nunca creados por quien los está corrigiendo — igual
            // criterio que los módulos de Reporte. El acceso ya lo controla requireLeer/Actualizar.
            $this->json(['ok' => true, 'data' => $this->service->repo()->periodos($idEmpresa, $tipo, null)]);
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
            // Sin filtro de "registros propios" — ver comentario de periodosAjax().
            $idUsuarioFiltro = null;

            $filtros = $this->filtrosGet();
            $page    = max(1, (int) ($_GET['page'] ?? 1));

            $res     = $this->service->repo()->listar($idEmpresa, $tipo, $filtros, $idUsuarioFiltro, $page, 200);
            $resumen = $this->service->repo()->resumenPorEstablecimiento($idEmpresa, $tipo, $filtros, $idUsuarioFiltro);
            $this->json(['ok' => true, 'rows' => $res['rows'], 'total' => $res['total'], 'resumen' => $resumen]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /**
     * GET/POST: resumen de vínculos (contabilidad/pagos/inventario/retención de compra) de los
     * documentos seleccionados. Sirve para avisar antes de reasignar — no modifica nada.
     */
    public function verificarVinculosAjax(): void
    {
        $this->requireLeer();
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $tipo = (string) ($_POST['tipo'] ?? $_GET['tipo'] ?? '');
            if (!in_array($tipo, ReasignarEstablecimientoRepository::tiposValidos(), true)) {
                throw new \RuntimeException('Tipo de documento no válido.');
            }
            $ids = $_POST['ids'] ?? $_GET['ids'] ?? [];
            if (!is_array($ids)) { $ids = array_filter(array_map('trim', explode(',', (string) $ids))); }

            $this->json(['ok' => true, 'data' => $this->service->verificarVinculos($idEmpresa, $tipo, $ids)]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /**
     * POST: reasigna los documentos seleccionados (ids[]) al establecimiento destino. Si alguno
     * tiene contabilidad/pagos/inventario, se omite salvo que venga anular_vinculos=1 (en ese
     * caso se anulan/revierten esos vínculos y se reasigna, todo por documento). Los que tienen
     * retención de compra asociada siempre quedan bloqueados, sin importar este flag.
     */
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
            $anularVinculos = !empty($_POST['anular_vinculos']);

            // Sin filtro de "registros propios" — ver comentario de periodosAjax().
            $res = $this->service->reasignar($idEmpresa, $tipo, $ids, $idEstDestino, $idUsuario, null, $anularVinculos);
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
