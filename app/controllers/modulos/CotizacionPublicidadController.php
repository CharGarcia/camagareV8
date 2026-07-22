<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\CotizacionPublicidadRepository;
use App\Services\modulos\CotizacionPublicidadService;
use App\Rules\modulos\CotizacionPublicidadRules;
use App\Services\LogSistemaService;
use App\models\Empresa;
use TCPDF;

class CotizacionPublicidadController extends BaseModuloController
{
    private CotizacionPublicidadRepository $repository;
    private CotizacionPublicidadService $service;

    protected function getRutaModulo(): string
    {
        return 'modulos/cotizacion-publicidad';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new CotizacionPublicidadRepository();
        $rules            = new CotizacionPublicidadRules();
        $logService       = new LogSistemaService();
        $this->service    = new CotizacionPublicidadService($this->repository, $rules, $logService);
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_emision');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage  = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result     = $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $total      = $result['total'];
        $totalPages = (int) ceil($total / $perPage);

        $vendedorRepo = new \App\repositories\modulos\VendedorRepository();
        $vendedores   = $vendedorRepo->getListado($idEmpresa, '', 1, 1000, 'nombre', 'ASC')['rows'];

        $tarifasIva = (new \App\models\TarifaIva())->getActivos();
        $categorias = $this->repository->getCategorias($idEmpresa);

        $permClientes  = $this->permisosModuloPorRuta('modulos/clientes');
        $permProductos = $this->permisosModuloPorRuta('modulos/productos');

        $puntos = $this->cargarTodosPuntos($idEmpresa);

        $this->viewWithLayout('layouts.main', 'modulos/cotizacion_publicidad/index', [
            'titulo'        => 'Cotización de Publicidad',
            'perm'          => $perm,
            'permClientes'  => $permClientes,
            'permProductos' => $permProductos,
            'rows'         => $result['rows'],
            'total'        => $total,
            'page'         => $page,
            'totalPages'   => $totalPages,
            'perPage'      => $perPage,
            'from'         => $total > 0 ? (($page - 1) * $perPage) + 1 : 0,
            'to'           => $total > 0 ? min($page * $perPage, $total) : 0,
            'buscar'       => $buscar,
            'ordenCol'     => $ordenCol,
            'ordenDir'     => $ordenDir,
            'vistaConfig'  => $prefsVista,
            'rutaModulo'   => $this->getRutaModulo(),
            'vendedores'   => $vendedores,
            'tarifasIva'   => $tarifasIva,
            'categorias'   => $categorias,
            'puntos'       => $puntos,
            'fullWidth'    => true,
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        $buscar     = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page       = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol   = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_emision');
        $ordenDir   = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage    = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result     = $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $total      = $result['total'];
        $totalPages = (int) ceil($total / $perPage);
        $from       = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to         = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($result['rows'])) {
            echo '<tr><td colspan="10" class="text-center py-5 text-muted"><i class="bi bi-megaphone fs-3 d-block mb-2"></i>No se encontraron cotizaciones.</td></tr>';
        } else {
            foreach ($result['rows'] as $r) {
                echo $this->renderFilaHtml($r);
            }
        }
        $rowsHtml = ob_get_clean();

        ob_start();
        $prevDisabled = ($page <= 1)           ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        echo '<button type="button" class="btn btn-outline-secondary" ' . $prevDisabled . ' onclick="cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>';
        echo '<button type="button" class="btn btn-outline-secondary" ' . $nextDisabled . ' onclick="cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>';
        $paginationHtml = ob_get_clean();

        $urlBase = rtrim(BASE_URL, '/') . '/' . $this->getRutaModulo();
        $bEnc    = urlencode($buscar);
        $sEnc    = urlencode($ordenCol);
        $dEnc    = urlencode($ordenDir);

        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'       => "$from-$to/$total",
            'total'      => $total,
            'excel_url'  => "{$urlBase}/exportExcelAjax?b={$bEnc}&sort={$sEnc}&dir={$dEnc}",
        ]);
        exit;
    }

    public function getCotizacionAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) {
            echo json_encode(['ok' => false, 'mensaje' => 'ID requerido']);
            exit;
        }

        $cabecera = $this->repository->getPorId($id);
        if (!$cabecera || (int) $cabecera['id_empresa'] !== $idEmpresa) {
            echo json_encode(['ok' => false, 'mensaje' => 'Cotización no encontrada']);
            exit;
        }

        $detalles = $this->repository->getDetalles($id);
        $costos   = $this->repository->getCostosPorCotizacion($id);

        echo json_encode([
            'ok'       => true,
            'cabecera' => $cabecera,
            'detalles' => $detalles,
            'costos'   => $costos,
        ]);
        exit;
    }

    public function guardarAjax(): void
    {
        header('Content-Type: application/json');
        try {
            $rawBody = file_get_contents('php://input');
            $data    = !empty($rawBody) ? (json_decode($rawBody, true) ?? $_POST) : $_POST;

            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];
            $data['id_empresa'] = $idEmpresa;
            $data['id_usuario'] = $idUsuario;

            $id = (int) ($data['id'] ?? 0);
            if ($id > 0) {
                $this->requireActualizar();
                $id = $this->service->actualizar($id, $data);
                $msg = 'Cotización actualizada correctamente.';
            } else {
                $this->requireCrear();
                $id = $this->service->crear($data);
                $msg = 'Cotización creada correctamente.';
            }

            $cotizacion = $this->repository->getPorId($id);
            $rowHtml    = $cotizacion ? $this->renderFilaHtml($cotizacion) : '';

            echo json_encode(['ok' => true, 'id' => $id, 'msg' => $msg, 'rowHtml' => $rowHtml]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function nuevaVersionAjax(): void
    {
        header('Content-Type: application/json');
        try {
            $this->requireCrear();
            $id        = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            if (!$id) throw new \RuntimeException('ID requerido.');

            $idNueva = $this->service->nuevaVersion($id, $idEmpresa, $idUsuario);

            echo json_encode(['ok' => true, 'id' => $idNueva]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function cambiarEstadoAjax(): void
    {
        header('Content-Type: application/json');
        try {
            $id        = (int) ($_POST['id'] ?? 0);
            $estado    = trim($_POST['estado'] ?? '');
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            if (!$id || !$estado) {
                throw new \RuntimeException('Parámetros inválidos.');
            }

            $this->requireActualizar();
            $this->service->cambiarEstado($id, $estado, $idEmpresa, $idUsuario);

            $cotizacion = $this->repository->getPorId($id);
            $rowHtml    = $cotizacion ? $this->renderFilaHtml($cotizacion) : '';

            echo json_encode(['ok' => true, 'rowHtml' => $rowHtml]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function guardarCostosAjax(): void
    {
        header('Content-Type: application/json');
        try {
            $this->requireActualizar();
            $rawBody = file_get_contents('php://input');
            $data    = !empty($rawBody) ? (json_decode($rawBody, true) ?? $_POST) : $_POST;

            $id        = (int) ($data['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];
            $costos    = $data['costos'] ?? [];

            if (!$id) throw new \RuntimeException('ID requerido.');

            $this->service->guardarCostos($id, $idEmpresa, $idUsuario, $costos);

            echo json_encode(['ok' => true, 'costos' => $this->repository->getCostosPorCotizacion($id)]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function eliminarAjax(): void
    {
        header('Content-Type: application/json');
        try {
            $id        = (int) ($_POST['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            if (!$id) throw new \RuntimeException('ID requerido.');

            $this->requireEliminar();
            $ok = $this->service->eliminar($id, $idEmpresa, $idUsuario);

            echo json_encode(['ok' => $ok]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Datos para prefil del modal "Generar Factura": cliente de la cotización,
     * subtotal (suma de ítems, sin comisión/IVA) y si se puede generar (no debe
     * existir una factura activa —no anulada— asociada).
     */
    public function getDatosFacturaAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $cotizacion = $this->repository->getPorId($id);
        if (!$cotizacion || (int) $cotizacion['id_empresa'] !== $idEmpresa) {
            echo json_encode(['ok' => false, 'error' => 'Cotización no encontrada.']);
            exit;
        }

        $facturaRepo = new \App\repositories\modulos\FacturaVentaRepository();
        $facturas    = $facturaRepo->getPorCotizacionPublicidad($id, $idEmpresa);
        $hayActiva   = false;
        foreach ($facturas as $f) {
            if (($f['estado'] ?? '') !== 'anulada') { $hayActiva = true; break; }
        }

        $perm = $this->getPermisos();
        $puedeGenerar = !empty($perm['crear'])
            && in_array($cotizacion['estado'], ['aprobada', 'convertida'], true)
            && !$hayActiva;

        $mensaje = '';
        if (!$puedeGenerar) {
            if ($hayActiva) {
                $mensaje = 'Ya existe una factura activa para esta cotización.';
            } elseif (!in_array($cotizacion['estado'], ['aprobada', 'convertida'], true)) {
                $mensaje = 'La cotización debe estar aprobada para generar una factura.';
            } else {
                $mensaje = 'No tiene permiso para generar la factura.';
            }
        }

        echo json_encode([
            'ok' => true,
            'cotizacion' => [
                'id_cliente'          => $cotizacion['id_cliente'],
                'cliente_nombre'      => $cotizacion['cliente_nombre'],
                'cliente_ruc'         => $cotizacion['cliente_ruc'],
                'total_sin_impuestos' => (float) $cotizacion['total_sin_impuestos'],
            ],
            'puede_generar' => $puedeGenerar,
            'mensaje'       => $mensaje,
        ]);
        exit;
    }

    public function convertirAFacturaAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');
        try {
            $rawBody = file_get_contents('php://input');
            $data    = !empty($rawBody) ? (json_decode($rawBody, true) ?? $_POST) : $_POST;

            $id        = (int) ($data['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            if (!$id) throw new \RuntimeException('ID requerido.');

            $res = $this->service->convertirAFactura($id, $idEmpresa, $idUsuario, $data);

            echo json_encode(['ok' => true, 'id_factura' => (int) ($res['id_factura'] ?? 0)]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function getProductosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $q         = trim($_GET['q'] ?? '');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $repo      = new \App\repositories\modulos\ProductoRepository();
        $result    = $repo->getListado($idEmpresa, $q, 1, 15, 'nombre', 'ASC', null, 'venta', true);
        echo json_encode(['ok' => true, 'data' => $result['rows']]);
        exit;
    }

    public function getSecuencialFacturaAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idPunto = (int) ($_GET['id_punto_emision'] ?? 0);
        if (!$idPunto) {
            echo json_encode(['ok' => false, 'error' => 'Punto de emisión requerido.']);
            exit;
        }
        try {
            $res = (new \App\Services\SecuencialService())->obtenerSiguienteSecuencial($idPunto, 'Facturas de venta');
            echo json_encode(array_merge(['ok' => true], $res));
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function getFacturasAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) {
            echo json_encode(['ok' => false, 'error' => 'ID requerido.']);
            exit;
        }

        try {
            $cotizacion = $this->repository->getPorId($id);
            if (!$cotizacion || (int) $cotizacion['id_empresa'] !== $idEmpresa) {
                throw new \RuntimeException('Cotización no encontrada.');
            }
            $facturaRepo = new \App\repositories\modulos\FacturaVentaRepository();
            $facturas = $facturaRepo->getPorCotizacionPublicidad($id, $idEmpresa);
            echo json_encode(['ok' => true, 'facturas' => $facturas]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function getClientesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $q         = trim($_GET['q'] ?? '');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $repo      = new \App\repositories\modulos\ClienteRepository();
        $result    = $repo->getListado($idEmpresa, $q, 1, 10, 'nombre', 'ASC', null, true);
        echo json_encode(['ok' => true, 'data' => $result['rows']]);
        exit;
    }

    public function getProveedoresAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $q         = trim($_GET['q'] ?? '');
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $db  = \App\core\Database::getConnection();
        $sql = "SELECT p.id, p.razon_social AS nombre, p.identificacion
                FROM proveedores p
                WHERE p.id_empresa = ? AND p.eliminado = false
                  AND (p.razon_social ILIKE ? OR p.identificacion ILIKE ?)
                ORDER BY p.razon_social ASC
                LIMIT 20";
        $st = $db->prepare($sql);
        $st->execute([$idEmpresa, "%$q%", "%$q%"]);
        echo json_encode(['ok' => true, 'data' => $st->fetchAll(\PDO::FETCH_ASSOC)]);
        exit;
    }

    /**
     * Facturas de compra (compras_cabecera) ya registradas de un proveedor,
     * para tomar el subtotal como costo. Excluye notas de crédito/débito y las
     * facturas que ya fueron vinculadas a otra línea de esta misma cotización.
     */
    public function getFacturasProveedorAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        try {
            $idProveedor  = (int) ($_GET['id_proveedor'] ?? 0);
            $idCotizacion = (int) ($_GET['id_cotizacion'] ?? 0);
            $q            = trim($_GET['q'] ?? '');
            $idEmpresa    = (int) $_SESSION['id_empresa'];

            if (!$idProveedor) {
                echo json_encode(['ok' => false, 'error' => 'Proveedor requerido.']);
                exit;
            }

            $excluir = [];
            if ($idCotizacion) {
                try {
                    $excluir = $this->repository->getIdsCompraUsados($idCotizacion);
                } catch (\Throwable $e) {
                    // Columna id_compra no desplegada aún en esta base: no filtra duplicados, pero no rompe la búsqueda.
                    $excluir = [];
                }
            }

            $params = [$idEmpresa, $idProveedor];
            $where  = "c.id_empresa = ? AND c.id_proveedor = ? AND c.eliminado = false AND c.tipo_comprobante NOT IN ('04','05')";
            if ($q !== '') {
                $where .= " AND (CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov,'-',c.secuencial_prov) ILIKE ? OR c.numero_autorizacion ILIKE ?)";
                $params[] = "%$q%";
                $params[] = "%$q%";
            }
            if (!empty($excluir)) {
                $ph = implode(',', array_fill(0, count($excluir), '?'));
                $where .= " AND c.id NOT IN ($ph)";
                array_push($params, ...$excluir);
            }

            $db  = \App\core\Database::getConnection();
            $sql = "SELECT c.id,
                           CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov,'-',c.secuencial_prov) AS numero,
                           c.fecha_emision, c.total_sin_impuestos, c.importe_total
                    FROM compras_cabecera c
                    WHERE $where
                    ORDER BY c.fecha_emision DESC
                    LIMIT 30";
            $st = $db->prepare($sql);
            $st->execute($params);
            echo json_encode(['ok' => true, 'data' => $st->fetchAll(\PDO::FETCH_ASSOC)]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function getCategoriasAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        echo json_encode(['ok' => true, 'data' => $this->repository->getCategorias($idEmpresa)]);
        exit;
    }

    public function guardarCategoriaAjax(): void
    {
        header('Content-Type: application/json');
        try {
            $this->requireCrear();
            $nombre    = trim($_POST['nombre'] ?? '');
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            if ($nombre === '') throw new \RuntimeException('El nombre es obligatorio.');

            $id = $this->repository->insertCategoria($idEmpresa, $nombre, $idUsuario);
            echo json_encode(['ok' => true, 'id' => $id, 'nombre' => $nombre]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function eliminarCategoriaAjax(): void
    {
        header('Content-Type: application/json');
        try {
            $this->requireEliminar();
            $id        = (int) ($_POST['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            if (!$id) throw new \RuntimeException('ID requerido.');

            $ok = $this->repository->eliminarCategoria($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => $ok]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function exportExcelAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? '');
        $ordenCol  = trim($_GET['sort'] ?? 'fecha_emision');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? 'DESC'));

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result = $this->repository->getListado($idEmpresa, $buscar, 1, 100000, $ordenCol, $ordenDir, $idUsuarioFiltro);

        $headers = ['Número', 'Fecha', 'Cliente', 'RUC/CI', 'Contacto', 'Proyecto', 'Vendedor', 'Presupuesto', 'Comisión %', 'Total', 'Estado', 'Observaciones'];
        $exportData = [];
        foreach ($result['rows'] as $r) {
            $numero = str_pad((string) $r['numero'], 3, '0', STR_PAD_LEFT) . '-' . date('Y', strtotime($r['fecha_emision'])) . ' V' . $r['version'];
            $exportData[] = [
                $numero,
                !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '',
                $r['cliente_nombre'] ?? '',
                $r['cliente_ruc'] ?? '',
                $r['contacto'] ?? '',
                $r['proyecto'] ?? '',
                $r['vendedor_nombre'] ?? '',
                number_format((float) ($r['presupuesto'] ?? 0), 2),
                number_format((float) ($r['comision'] ?? 0), 2),
                number_format((float) ($r['importe_total'] ?? 0), 2),
                ucfirst($r['estado'] ?? ''),
                $r['observaciones'] ?? '',
            ];
        }

        (new \App\Services\ReportService())->exportToExcel(
            'CotizacionesPublicidad',
            $headers,
            $exportData,
            'Cotizaciones de Publicidad',
            'Empresa ID ' . $idEmpresa
        );
        exit;
    }

    public function exportarPdfAjax(): void
    {
        $this->requireLeer();
        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $cabecera = $this->repository->getPorId($id);
        if (!$cabecera || (int) $cabecera['id_empresa'] !== $idEmpresa) {
            http_response_code(404);
            echo 'Cotización no encontrada.';
            exit;
        }

        $detalles = $this->repository->getDetalles($id);

        $empresaModel = new Empresa();
        $empresa      = $empresaModel->getPorId($idEmpresa) ?? [];
        $estabs       = $empresaModel->getEstablecimientos($idEmpresa);
        if (!empty($estabs[0]['logo_ruta'])) {
            $empresa['logo_ruta'] = $estabs[0]['logo_ruta'];
        }

        try {
            $renderer  = new \App\Services\PlantillasPdfRendererService();
            $plantilla = $renderer->getPlantillaActiva($idEmpresa, 'cotizacion_publicidad');

            if ($plantilla) {
                $renderer->generar($plantilla, $cabecera, $detalles, [], [], $empresa);
            } else {
                $this->renderPdfBasico($cabecera, $detalles, $empresa);
            }
        } catch (\Throwable $e) {
            $this->renderPdfBasico($cabecera, $detalles, $empresa);
        }
        exit;
    }

    private function cargarTodosPuntos(int $idEmpresa): array
    {
        $db = \App\core\Database::getConnection();
        try {
            $st = $db->prepare(
                "SELECT p.id              AS id,
                        p.id             AS id_punto,
                        e.codigo         AS cod_establecimiento,
                        e.id             AS id_establecimiento,
                        p.codigo_punto,
                        p.nombre
                 FROM empresa_punto_emision p
                 JOIN empresa_establecimiento e ON e.id = p.id_establecimiento
                 WHERE p.id_empresa = ?
                   AND p.eliminado  = false
                   AND e.eliminado  = false
                 ORDER BY e.codigo, p.codigo_punto"
            );
            $st->execute([$idEmpresa]);
            return $st->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            try {
                $st2 = $db->prepare(
                    "SELECT id,
                            id               AS id_punto,
                            establecimiento  AS cod_establecimiento,
                            punto            AS codigo_punto,
                            id_establecimiento,
                            '' AS nombre
                     FROM empresa_puntos_emision
                     WHERE id_empresa = ? AND eliminado = false
                     ORDER BY establecimiento, punto"
                );
                $st2->execute([$idEmpresa]);
                return $st2->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Throwable $ignored) { return []; }
        }
    }

    private function renderFilaHtml(array $r): string
    {
        $rowData = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
        $numero  = htmlspecialchars(str_pad((string) $r['numero'], 3, '0', STR_PAD_LEFT) . '-' . date('Y', strtotime($r['fecha_emision'])) . ' V' . $r['version']);
        $fecha   = !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '-';
        $estado  = $r['estado'] ?? 'borrador';

        $estadoClass = match ($estado) {
            'aprobada'   => 'bg-success bg-opacity-10 text-success border border-success border-opacity-25',
            'anulada'    => 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25',
            'convertida' => 'bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25',
            'rechazada'  => 'bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25',
            default      => 'bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25',
        };
        $estadoLabel = match ($estado) {
            'borrador'   => 'Borrador',
            'aprobada'   => 'Aprobada',
            'rechazada'  => 'Rechazada',
            'convertida' => 'Convertida',
            'anulada'    => 'Anulada',
            default      => ucfirst($estado),
        };

        $cliente   = htmlspecialchars($r['cliente_nombre'] ?? '-');
        $contacto  = htmlspecialchars($r['contacto'] ?? '-');
        $proyecto  = htmlspecialchars($r['proyecto'] ?? '-');
        $vendedor  = htmlspecialchars($r['vendedor_nombre'] ?? '-');
        $presup    = number_format((float) ($r['presupuesto'] ?? 0), 2);
        $comision  = number_format((float) ($r['comision'] ?? 0), 2);
        $total     = number_format((float) ($r['importe_total'] ?? 0), 2);
        $obs       = htmlspecialchars(mb_substr($r['observaciones'] ?? '', 0, 60));
        $id        = (int) $r['id'];

        return "
        <tr class=\"cotpub-row\" role=\"button\" tabindex=\"0\" data-id=\"{$id}\"
            data-row=\"{$rowData}\" onclick=\"CP.verDetalle({$id})\">
            <td class=\"ps-3 fw-medium\" data-col=\"numero\"><code class=\"text-secondary\">{$numero}</code></td>
            <td data-col=\"fecha_emision\">{$fecha}</td>
            <td class=\"text-truncate\" style=\"max-width:200px;\" data-col=\"cliente_nombre\">{$cliente}</td>
            <td data-col=\"contacto\">{$contacto}</td>
            <td class=\"text-truncate\" style=\"max-width:180px;\" data-col=\"proyecto\">{$proyecto}</td>
            <td data-col=\"vendedor_nombre\">{$vendedor}</td>
            <td class=\"text-end\" data-col=\"presupuesto\">\${$presup}</td>
            <td class=\"text-end\" data-col=\"comision\">{$comision}%</td>
            <td class=\"text-end fw-semibold\" data-col=\"importe_total\">\${$total}</td>
            <td class=\"text-center\" data-col=\"estado\">
                <span class=\"badge {$estadoClass}\">{$estadoLabel}</span>
            </td>
            <td class=\"text-truncate text-muted small\" style=\"max-width:180px;\" data-col=\"observaciones\">{$obs}</td>
        </tr>";
    }

    /**
     * PDF de respaldo (sin plantilla configurada en /config/plantillas-pdf) generado
     * con TCPDF, forzando la descarga del archivo (Output ..., 'D').
     */
    private function renderPdfBasico(array $cabecera, array $detalles, array $empresa = []): void
    {
        $numero = str_pad((string) $cabecera['numero'], 3, '0', STR_PAD_LEFT) . '-' . date('Y', strtotime($cabecera['fecha_emision'])) . ' V' . $cabecera['version'];

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Sistema');
        $pdf->SetAuthor($empresa['nombre'] ?? '');
        $pdf->SetTitle('Cotización de Publicidad ' . $numero);
        $pdf->SetMargins(12, 10, 12);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();

        $w = 186; // ancho útil (210 - 12 - 12)

        // ── Encabezado: empresa + título ──
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell($w, 6, strtoupper((string) ($empresa['nombre'] ?? '')), 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 8);
        $lineas = array_filter([
            !empty($empresa['ruc']) ? 'RUC: ' . $empresa['ruc'] : '',
            (string) ($empresa['direccion_matriz'] ?? $empresa['direccion'] ?? ''),
            !empty($empresa['telefono']) ? 'Tel: ' . $empresa['telefono'] : '',
        ]);
        foreach ($lineas as $ln) {
            $pdf->Cell($w, 4, $ln, 0, 1, 'L');
        }
        $pdf->Ln(2);
        $pdf->SetFont('helvetica', 'B', 13);
        $pdf->Cell($w, 7, 'COTIZACIÓN DE PUBLICIDAD', 0, 1, 'C');
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(180, 0, 0);
        $pdf->Cell($w, 6, $numero, 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(2);

        // ── Datos del cliente ──
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(25, 5, 'Cliente:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell($w - 25, 5, ($cabecera['cliente_nombre'] ?? '') . ' — ' . ($cabecera['cliente_ruc'] ?? ''), 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(25, 5, 'Proyecto:', 0, 0, 'L');
        $pdf->Cell(($w - 25) / 2, 5, (string) ($cabecera['proyecto'] ?? '-'), 0, 0, 'L');
        $pdf->Cell(25, 5, 'Contacto:', 0, 0, 'L');
        $pdf->Cell(($w - 25) / 2 - 25, 5, (string) ($cabecera['contacto'] ?? '-'), 0, 1, 'L');
        $pdf->Cell(25, 5, 'Fecha:', 0, 0, 'L');
        $pdf->Cell($w - 25, 5, date('d-m-Y', strtotime($cabecera['fecha_emision'] ?? 'now')), 0, 1, 'L');
        $pdf->Ln(2);

        // ── Tabla de detalle ──
        $colCat = 28; $colDesc = 66; $colCiu = 16; $colDia = 14; $colCant = 14; $colPrec = 22; $colSub = $w - $colCat - $colDesc - $colCiu - $colDia - $colCant - $colPrec;

        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell($colCat, 6, 'Categoría', 1, 0, 'C', true);
        $pdf->Cell($colDesc, 6, 'Descripción', 1, 0, 'C', true);
        $pdf->Cell($colCiu, 6, 'Ciudades', 1, 0, 'C', true);
        $pdf->Cell($colDia, 6, 'Días', 1, 0, 'C', true);
        $pdf->Cell($colCant, 6, 'Cant.', 1, 0, 'C', true);
        $pdf->Cell($colPrec, 6, 'P.Unit.', 1, 0, 'C', true);
        $pdf->Cell($colSub, 6, 'Subtotal', 1, 1, 'C', true);

        $pdf->SetFont('helvetica', '', 8);
        foreach ($detalles as $d) {
            $pdf->Cell($colCat, 6, (string) ($d['categoria_nombre'] ?? '-'), 1, 0, 'L');
            $pdf->Cell($colDesc, 6, (string) $d['descripcion'], 1, 0, 'L');
            $pdf->Cell($colCiu, 6, (string) (int) $d['ciudades'], 1, 0, 'C');
            $pdf->Cell($colDia, 6, (string) (int) $d['dias'], 1, 0, 'C');
            $pdf->Cell($colCant, 6, number_format((float) $d['cantidad'], 2), 1, 0, 'R');
            $pdf->Cell($colPrec, 6, number_format((float) $d['precio_unitario'], 2), 1, 0, 'R');
            $pdf->Cell($colSub, 6, number_format((float) $d['precio_total_sin_impuesto'], 2), 1, 1, 'R');
        }
        $pdf->Ln(3);

        // ── Totales ──
        $labelW = $w - 40;
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell($labelW, 5, 'Subtotal', 0, 0, 'R');
        $pdf->Cell(40, 5, '$' . number_format((float) ($cabecera['total_sin_impuestos'] ?? 0), 2), 0, 1, 'R');
        $pdf->Cell($labelW, 5, 'Comisión de agencia (' . number_format((float) ($cabecera['comision'] ?? 0), 2) . '%)', 0, 0, 'R');
        $pdf->Cell(40, 5, '$' . number_format((float) ($cabecera['total_comision'] ?? 0), 2), 0, 1, 'R');
        $pdf->Cell($labelW, 5, 'IVA', 0, 0, 'R');
        $pdf->Cell(40, 5, '$' . number_format((float) ($cabecera['total_iva'] ?? 0), 2), 0, 1, 'R');
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell($labelW, 6, 'TOTAL', 0, 0, 'R');
        $pdf->Cell(40, 6, '$' . number_format((float) ($cabecera['importe_total'] ?? 0), 2), 0, 1, 'R');

        if (!empty($cabecera['observaciones'])) {
            $pdf->Ln(3);
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell($w, 5, 'Observaciones:', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 8);
            $pdf->MultiCell($w, 4, (string) $cabecera['observaciones'], 0, 'L');
        }

        $nombreArchivo = 'CotizacionPublicidad_' . str_replace([' ', '/'], '_', $numero) . '.pdf';
        $pdf->Output($nombreArchivo, 'D');
    }
}
