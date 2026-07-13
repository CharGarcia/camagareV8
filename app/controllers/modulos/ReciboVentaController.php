<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Services\modulos\ReciboVentaService;
use App\repositories\modulos\ReciboVentaRepository;
use App\models\Empresa;

/**
 * Controlador del módulo Recibos de Venta.
 * Espejo simplificado de FacturaVentaController: sin SRI, XML, notas de crédito,
 * retenciones, guías, correo ni WhatsApp. Mantiene inventario, asiento contable
 * y cobros (tipo_documento = 'RECIBO').
 */
class ReciboVentaController extends BaseModuloController
{
    private $service;
    private $repository;

    protected function getRutaModulo(): string
    {
        return 'modulos/recibo-venta';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new ReciboVentaRepository();
        $rules            = new \App\Rules\modulos\ReciboVentaRules();
        $logService       = new \App\Services\LogSistemaService();
        $this->service    = new ReciboVentaService($this->repository, $rules, $logService);
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? $_GET['buscar'] ?? $_POST['buscar'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_emision');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage  = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $result = $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $totalPages = (int) ceil($result['total'] / $perPage);

        $empresaModel = new Empresa();
        $empresaData  = $empresaModel->getPorId($idEmpresa);

        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        $puntos = [];
        if (!empty($establecimientos)) {
            $puntos = $empresaModel->getPuntosEmision((int) $establecimientos[0]['id']);
            try {
                $estRepo   = new \App\repositories\modulos\EmpresaRepository();
                $estConfig = $estRepo->getEstablecimientoConfig((int) $establecimientos[0]['id']);
                if ($estConfig) {
                    $empresaData = array_merge($empresaData ?? [], $estConfig);
                }
            } catch (\Throwable $e) {}
        }

        $vendedorRepo = new \App\repositories\modulos\VendedorRepository();
        $vendedores = $vendedorRepo->getListado($idEmpresa, '', 1, 1000, 'nombre', 'ASC')['rows'];

        $bodegaRepo = new \App\repositories\modulos\BodegaRepository();
        $bodegas = $bodegaRepo->getBodegasPermitidas((int)$_SESSION['id_usuario'], $idEmpresa, (int)$_SESSION['nivel']);

        $total = $result['total'];

        $this->viewWithLayout('layouts.main', 'modulos/recibos_venta/index', [
            'titulo'      => 'Recibos de Venta',
            'perm'        => $perm,
            'rows'        => $result['rows'],
            'total'       => $total,
            'page'        => $page,
            'totalPages'  => $totalPages,
            'perPage'     => $perPage,
            'from'        => $total > 0 ? (($page - 1) * $perPage) + 1 : 0,
            'to'          => $total > 0 ? min($page * $perPage, $total) : 0,
            'buscar'      => $buscar,
            'ordenCol'    => $ordenCol,
            'ordenDir'    => $ordenDir,
            'vistaConfig' => $prefsVista,
            'base'        => BASE_URL,
            'rutaModulo'  => $this->getRutaModulo(),
            'empresa'     => $empresaData,
            'formasPago'  => $this->repository->getFormasPago(),
            'tarifasIva'  => $this->repository->getTarifasIva(),
            'unidades'    => $this->repository->getUnidadesMedida(),
            'bodegas'     => $bodegas,
            'vendedores'  => $vendedores,
            'puntos'      => $puntos,
            'fullWidth'   => true,
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
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $result     = $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows       = $result['rows'];
        $total      = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        $from       = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to         = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="16" class="text-center py-5 text-muted"><i class="bi bi-receipt-cutoff fs-3 d-block mb-2"></i>No se encontraron recibos.</td></tr>';
        } else {
            foreach ($rows as $r) {
                echo $this->renderFilaHtml($r);
            }
        }
        $rowsHtml = ob_get_clean();

        ob_start();
        $prevDisabled = ($page <= 1)           ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        echo '<button type="button" class="btn btn-outline-secondary" ' . $prevDisabled . ' onclick="window.RV_cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>
              <button type="button" class="btn btn-outline-secondary" ' . $nextDisabled . ' onclick="window.RV_cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>';
        $paginationHtml = ob_get_clean();

        $urlBase = BASE_URL . '/' . $this->getRutaModulo();
        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'       => "$from-$to/$total",
            'total'      => $total,
            'pdf_url'    => $urlBase . '/export-pdf?b='    . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir",
            'excel_url'  => $urlBase . '/export-excel?b='  . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir",
        ]);
        exit;
    }

    public function getFacturaAjax(): void
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
        if (!$cabecera || (int)($cabecera['id_empresa'] ?? 0) !== $idEmpresa) {
            echo json_encode(['ok' => false, 'mensaje' => 'Recibo no encontrado']);
            exit;
        }

        $detalles = $this->repository->getDetalles($id);
        $prodRepo = new \App\repositories\modulos\ProductoRepository();
        foreach ($detalles as &$d) {
            $d['impuestos'] = $this->repository->getImpuestosDetalle((int) $d['id']);
            if (!empty($d['id_producto'])) {
                $d['precios_lista'] = $prodRepo->getPrecios((int)$d['id_producto'], $idEmpresa);
                $d['variantes']     = $prodRepo->getVariantes((int)$d['id_producto'], $idEmpresa);
            }
        }
        unset($d);

        echo json_encode([
            'ok'             => true,
            'cabecera'       => $cabecera,
            'detalles'       => $detalles,
            'pagos'          => $this->repository->getPagos($id),
            'info_adicional' => $this->repository->getInfoAdicional($id),
        ]);
        exit;
    }

    public function getEstablecimientosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $empresaModel = new Empresa();
        $establecimientos = $empresaModel->getEstablecimientos((int)$_SESSION['id_empresa']);
        echo json_encode(['ok' => true, 'data' => $establecimientos]);
        exit;
    }

    public function getPuntosEmisionAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEst = (int) ($_GET['id_establecimiento'] ?? 0);
        $empresaModel = new Empresa();
        $puntos = $empresaModel->getPuntosEmision($idEst);
        echo json_encode(['ok' => true, 'data' => $puntos]);
        exit;
    }

    public function getSecuencialAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idPunto = (int) ($_GET['id_punto_emision'] ?? 0);
        $tipo    = $_GET['tipo'] ?? 'recibo';

        // El cobro rápido (pestaña Pagos) pide el secuencial de 'Ingresos';
        // todo lo demás usa la numeración propia del recibo.
        $tipoDoc = ($tipo === 'ingresos') ? 'Ingresos' : 'Recibos de venta';

        $secuencialService = new \App\Services\SecuencialService();
        $res = $secuencialService->obtenerSiguienteSecuencial($idPunto, $tipoDoc);

        echo json_encode(array_merge(['ok' => true], $res));
        exit;
    }

    public function guardarAjax(): void
    {
        header('Content-Type: application/json');

        try {
            $data = $_POST;
            if (isset($_POST['data'])) {
                $data = json_decode($_POST['data'], true);
            }

            $data['id_empresa'] = (int) $_SESSION['id_empresa'];
            $data['id_usuario'] = (int) $_SESSION['id_usuario'];

            $empresaModel = new Empresa();
            $empresaData  = $empresaModel->getPorId($data['id_empresa']) ?? [];
            try {
                $establecimientos = $empresaModel->getEstablecimientos($data['id_empresa']);
                if (!empty($establecimientos)) {
                    $estRepo   = new \App\repositories\modulos\EmpresaRepository();
                    $estConfig = $estRepo->getEstablecimientoConfig((int) $establecimientos[0]['id']);
                    if ($estConfig) {
                        $empresaData = array_merge($empresaData, $estConfig);
                    }
                }
            } catch (\Throwable $e) {}
            $data['empresa_config'] = $empresaData;

            $idExistente = !empty($data['id']) ? (int) $data['id'] : 0;

            if ($idExistente > 0) {
                $this->requireActualizar();
                $id = $this->service->actualizar($idExistente, $data);
                $mensaje = 'Recibo actualizado exitosamente.';
            } else {
                $this->requireCrear();
                $id = $this->service->crear($data);
                $mensaje = 'Recibo guardado exitosamente.';
            }
            $asientoWarning = $this->service->getLastAsientoWarning();

            $reciboDB = $this->repository->getPorId($id);
            $rowHtml  = $reciboDB ? $this->renderFilaHtml($reciboDB) : '';

            echo json_encode(['ok' => true, 'mensaje' => $mensaje, 'id' => $id, 'asiento_warning' => $asientoWarning ?? null, 'rowHtml' => $rowHtml]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function getAsientoSugeridoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idRecibo  = (int) ($_GET['id'] ?? $_POST['id'] ?? $_GET['id_venta'] ?? $_POST['id_venta'] ?? 0);

        try {
            $data = $_POST;
            if (isset($_POST['data'])) {
                $data = json_decode($_POST['data'], true);
            }
            if (empty($data)) {
                $data = $_GET;
            }

            if ($idRecibo > 0 && empty($data['detalles'])) {
                $reciboDB  = $this->service->getPorId($idRecibo, $idEmpresa);
                $idAsiento = (int)($reciboDB['id_asiento_contable'] ?? 0);

                if ($idAsiento > 0) {
                    $asientoService = new \App\Services\modulos\AsientoContableService(
                        new \App\repositories\modulos\AsientoContableRepository(),
                        new \App\Rules\modulos\AsientoContableRules(),
                        new \App\Services\LogSistemaService()
                    );
                    $cabeceraDb = $asientoService->getDetalleAsiento($idAsiento, $idEmpresa);
                    $detallesDb = $cabeceraDb['detalles'] ?? [];
                    $detalles = [];
                    foreach ($detallesDb as $det) {
                        $detalles[] = [
                            'id_cuenta_contable'   => (int)$det['id_cuenta_contable'],
                            'cuenta_codigo'        => $det['codigo_cuenta'] ?? $det['cuenta_codigo'] ?? '',
                            'cuenta_nombre'        => $det['nombre_cuenta'] ?? $det['cuenta_nombre'] ?? '',
                            'debe'                 => (float)$det['debe'],
                            'haber'                => (float)$det['haber'],
                            'referencia_detalle'   => $det['referencia_detalle'] ?? '',
                            'documento_referencia' => $det['documento_referencia'] ?? '',
                        ];
                    }
                    echo json_encode(['ok' => true, 'data' => $detalles, 'detalles' => $detalles, 'es_guardado' => true]);
                    exit;
                }
            }

            $normalizedData = [
                'importe_total'       => $data['importe_total'] ?? $data['total'] ?? 0,
                'total_sin_impuestos' => $data['total_sin_impuestos'] ?? $data['subtotal'] ?? 0,
                'total_descuento'     => $data['total_descuento'] ?? $data['descuento'] ?? 0,
                'propina'             => $data['propina'] ?? 0,
                'id_cliente'          => $data['id_cliente'] ?? 0,
            ];
            if (isset($data['ivas'])) {
                $ivasArr = json_decode($data['ivas'], true);
                if (is_array($ivasArr)) {
                    $ivaSum = 0;
                    foreach ($ivasArr as $iv) $ivaSum += (float)($iv['valor'] ?? 0);
                    $normalizedData['iva'] = $ivaSum;
                }
            }

            $detallesSugeridos = $this->service->obtenerAsientoSugerido($idEmpresa, $normalizedData);
            echo json_encode(['ok' => true, 'data' => $detallesSugeridos, 'detalles' => $detallesSugeridos, 'es_guardado' => false]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function generarAsientoContableAjax(): void
    {
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $idRecibo  = (int) ($_POST['id_venta'] ?? $_POST['id_recibo'] ?? 0);

        if ($idRecibo <= 0) {
            echo json_encode(['ok' => false, 'error' => 'ID de recibo inválido.']);
            exit;
        }

        try {
            $data = $this->service->getPorId($idRecibo, $idEmpresa);
            if (!$data) {
                throw new \Exception('Recibo no encontrado.');
            }
            $data['id_empresa'] = $idEmpresa;
            $data['id_usuario'] = $idUsuario;

            $numRecibo = ($data['establecimiento'] ?? '001') . '-' . ($data['punto_emision'] ?? '001') . '-' . str_pad((string)($data['secuencial'] ?? 0), 9, '0', STR_PAD_LEFT);
            $this->service->procesarAsientoContable($idRecibo, $data, $numRecibo);

            echo json_encode(['ok' => true, 'mensaje' => 'Asiento generado/actualizado con éxito.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function exportarPdfAjax(): void
    {
        $this->requireLeer();

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) { http_response_code(400); echo 'ID requerido'; exit; }

        try {
            $recibo = $this->repository->getPorId($id);
            if (!$recibo || (int)($recibo['id_empresa'] ?? 0) !== $idEmpresa) {
                http_response_code(404); echo 'Recibo no encontrado'; exit;
            }

            $detalles = $this->repository->getDetalles($id);
            foreach ($detalles as &$d) {
                $d['impuestos'] = $this->repository->getImpuestosDetalle((int)$d['id']);
            }
            unset($d);

            $pagos         = $this->repository->getPagos($id);
            $infoAdicional = $this->repository->getInfoAdicional($id);

            $empresaModel = new Empresa();
            $empresa      = $empresaModel->getPorId($idEmpresa) ?? [];

            $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
            if (!empty($establecimientos)) {
                if (!empty($establecimientos[0]['logo_ruta']))  $empresa['logo_ruta'] = $establecimientos[0]['logo_ruta'];
                if (!empty($establecimientos[0]['direccion']))  $empresa['direccion_establecimiento'] = $establecimientos[0]['direccion'];
                try {
                    $estRepo   = new \App\repositories\modulos\EmpresaRepository();
                    $estConfig = $estRepo->getEstablecimientoConfig((int) $establecimientos[0]['id']);
                    if ($estConfig) {
                        $estConfig['direccion_matriz'] = $empresa['direccion'] ?? '';
                        $estConfig['direccion_establecimiento'] = $establecimientos[0]['direccion'] ?? '';
                        if (!empty($establecimientos[0]['logo_ruta'])) $estConfig['logo_ruta'] = $establecimientos[0]['logo_ruta'];
                        $empresa = array_merge($empresa, $estConfig);
                    }
                } catch (\Throwable $e) {}
            }

            $pdfService = new \App\Services\modulos\ReciboVentaPdfService();
            $pdfService->generar($recibo, $detalles, $pagos, $infoAdicional, $empresa);
        } catch (\Throwable $e) {
            http_response_code(500); echo 'Error al generar PDF: ' . $e->getMessage();
        }
        exit;
    }

    public function getClientesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['q'] ?? '');
        $repo = new \App\repositories\modulos\ClienteRepository();
        $result = $repo->getListado($idEmpresa, $buscar, 1, 10, 'nombre', 'ASC', null, true);
        echo json_encode(['ok' => true, 'data' => $result['rows']]);
        exit;
    }

    public function getProductosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['q'] ?? '');
        $repo = new \App\repositories\modulos\ProductoRepository();
        $result = $repo->getListado($idEmpresa, $buscar, 1, 15, 'nombre', 'ASC', null, 'venta', true);
        $rows = array_map(function ($p) use ($repo, $idEmpresa) {
            $p['precios_lista'] = $repo->getPrecios((int)$p['id'], $idEmpresa);
            $p['variantes']     = $repo->getVariantes((int)$p['id'], $idEmpresa);
            return $p;
        }, $result['rows']);
        echo json_encode(['ok' => true, 'data' => $rows]);
        exit;
    }

    public function getLotesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $idProducto = (int) ($_GET['id_producto'] ?? 0);
        $idBodega   = (int) ($_GET['id_bodega'] ?? 0);
        $idRecibo   = (int) ($_GET['id_venta'] ?? $_GET['id_recibo'] ?? 0);

        if (!$idProducto || !$idBodega) {
            echo json_encode(['ok' => false, 'mensaje' => 'Faltan parámetros']);
            exit;
        }

        $repoInv = new \App\repositories\modulos\InventarioRepository();
        $excludeId   = ($idRecibo > 0 ? $idRecibo : null);
        $excludeTipo = ($idRecibo > 0 ? 'recibo_venta' : null);

        $lotes = $repoInv->getLotesDisponibles($idProducto, $idBodega, $idEmpresa, $excludeId, $excludeTipo);
        $stockTotal = $repoInv->getStockActual($idProducto, $idBodega, $idEmpresa, $excludeId, $excludeTipo);

        echo json_encode(['ok' => true, 'data' => $lotes, 'stock_total' => $stockTotal]);
        exit;
    }

    public function eliminarAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        if (!$id) {
            echo json_encode(['ok' => false, 'mensaje' => 'ID requerido.']);
            exit;
        }

        try {
            $this->service->eliminar($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Recibo eliminado correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function anularAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        if (!$id) {
            echo json_encode(['ok' => false, 'mensaje' => 'ID requerido.']);
            exit;
        }

        try {
            $this->service->anular($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Recibo anulado correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function generarFacturaAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        // Generar una factura desde el recibo exige, además, permiso de CREAR
        // en el módulo de Facturas de venta.
        if (!\App\Helpers\Permisos::puedeCrear('modulos/factura-venta')) {
            echo json_encode(['ok' => false, 'mensaje' => 'No tiene permiso para crear facturas de venta.']);
            exit;
        }

        $id        = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        if (!$id) {
            echo json_encode(['ok' => false, 'mensaje' => 'ID de recibo requerido.']);
            exit;
        }

        try {
            // Configuración de empresa (tipo_ambiente, ruc, etc.) para la clave de acceso de la factura.
            $empresaModel = new Empresa();
            $empresaData  = $empresaModel->getPorId($idEmpresa) ?? [];
            try {
                $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
                if (!empty($establecimientos)) {
                    $estRepo   = new \App\repositories\modulos\EmpresaRepository();
                    $estConfig = $estRepo->getEstablecimientoConfig((int) $establecimientos[0]['id']);
                    if ($estConfig) {
                        $empresaData = array_merge($empresaData, $estConfig);
                    }
                }
            } catch (\Throwable $e) {}

            $res = $this->service->generarFacturaDesdeRecibo($id, $idEmpresa, $idUsuario, $empresaData);

            echo json_encode([
                'ok'             => true,
                'mensaje'        => 'Factura ' . $res['numero_factura'] . ' generada. El recibo quedó facturado.',
                'id_factura'     => $res['id_factura'],
                'numero_factura' => $res['numero_factura'],
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    // ── Pagos / cobros (tipo_documento = 'RECIBO') ──────────────────────────────

    public function getCobrosVinculadosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) {
            echo json_encode(['ok' => false, 'mensaje' => 'ID de recibo requerido.']);
            exit;
        }

        try {
            $db = \App\core\Database::getConnection();

            $stRec = $db->prepare(
                "SELECT id, importe_total, establecimiento, punto_emision, secuencial
                 FROM recibos_venta_cabecera
                 WHERE id = ? AND id_empresa = ? AND eliminado = false"
            );
            $stRec->execute([$id, $idEmpresa]);
            $recibo = $stRec->fetch(\PDO::FETCH_ASSOC);
            if (!$recibo) {
                echo json_encode(['ok' => false, 'mensaje' => 'Recibo no encontrado.']);
                exit;
            }

            $sqlCobros = "SELECT ic.id, ic.numero_ingreso, ic.fecha_emision, ic.monto_total,
                                 ic.estado, ic.observaciones,
                                 id2.monto_cobrado,
                                 u.nombre AS usuario_nombre,
                                 STRING_AGG(DISTINCT efp.nombre, ', ') AS formas_cobro
                          FROM ingresos_detalle id2
                          INNER JOIN ingresos_cabecera  ic  ON id2.id_ingreso    = ic.id
                          LEFT  JOIN usuarios           u   ON ic.id_usuario     = u.id
                          LEFT  JOIN ingresos_pagos     ip  ON ip.id_ingreso     = ic.id
                          LEFT  JOIN empresa_formas_pago efp ON efp.id           = ip.id_forma_cobro
                          WHERE id2.tipo_documento           = 'RECIBO'
                            AND id2.id_referencia_documento  = ?
                            AND ic.id_empresa                = ?
                            AND ic.eliminado                 = false
                          GROUP BY ic.id, ic.numero_ingreso, ic.fecha_emision, ic.monto_total,
                                   ic.estado, ic.observaciones, id2.monto_cobrado, u.nombre
                          ORDER BY ic.fecha_emision DESC, ic.id DESC";
            $stCobros = $db->prepare($sqlCobros);
            $stCobros->execute([$id, $idEmpresa]);
            $cobros = $stCobros->fetchAll(\PDO::FETCH_ASSOC);

            echo json_encode([
                'ok'                => true,
                'factura'           => $recibo,
                'cobros'            => $cobros,
                'total_retenciones' => 0,
                'retenciones'       => [],
                'total_nc'          => 0,
                'pagos_tarjeta'     => [],
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function getIngresosCatalogosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $db        = \App\core\Database::getConnection();
        $puntos = []; $conceptos = []; $formas = []; $bancos = [];

        try {
            $stP = $db->prepare(
                "SELECT p.id AS id_punto, e.codigo AS cod_establecimiento, p.codigo_punto, p.id_establecimiento
                 FROM empresa_punto_emision p
                 JOIN empresa_establecimiento e ON e.id = p.id_establecimiento
                 WHERE p.id_empresa = ? AND p.eliminado = false AND e.eliminado = false
                 ORDER BY e.codigo, p.codigo_punto"
            );
            $stP->execute([$idEmpresa]);
            $puntos = $stP->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            try {
                $stP2 = $db->prepare(
                    "SELECT id AS id_punto, establecimiento AS cod_establecimiento, punto AS codigo_punto, id_establecimiento
                     FROM empresa_puntos_emision WHERE id_empresa = ? AND eliminado = false ORDER BY establecimiento, punto"
                );
                $stP2->execute([$idEmpresa]);
                $puntos = $stP2->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Throwable $ignored) {}
        }

        try {
            $stC = $db->prepare(
                "SELECT id, nombre, comportamiento FROM empresa_opciones_ingreso_egreso
                 WHERE id_empresa = ? AND aplica_ingresos = TRUE AND UPPER(estado) = 'ACTIVO' AND eliminado = FALSE
                 ORDER BY nombre ASC"
            );
            $stC->execute([$idEmpresa]);
            $conceptos = $stC->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $ignored) {}

        try {
            $stF = $db->prepare(
                "SELECT id, nombre, tipo FROM empresa_formas_pago
                 WHERE id_empresa = ? AND eliminado = false AND activo = true
                   AND (aplica_en IN ('AMBAS','INGRESO') OR aplica_en IS NULL)
                 ORDER BY nombre ASC"
            );
            $stF->execute([$idEmpresa]);
            $formas = $stF->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $ignored) {}

        try {
            $stB = $db->prepare(
                "SELECT id, nombre_banco FROM empresa_bancos
                 WHERE id_empresa = ? AND eliminado = false AND estado = true ORDER BY nombre_banco ASC"
            );
            $stB->execute([$idEmpresa]);
            $bancos = $stB->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $ignored) {}

        echo json_encode([
            'ok'   => true,
            'data' => ['puntos' => $puntos, 'conceptos' => $conceptos, 'formas_cobro' => $formas, 'bancos' => $bancos],
        ]);
        exit;
    }

    public function registrarCobroRapidoAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true);

        if (!$data || empty($data['id_factura'])) {
            echo json_encode(['ok' => false, 'mensaje' => 'Datos inválidos o ID de recibo faltante.']);
            exit;
        }

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $db = \App\core\Database::getConnection();

            $stRec = $db->prepare(
                "SELECT v.*, c.nombre AS cliente_nombre
                 FROM recibos_venta_cabecera v
                 INNER JOIN clientes c ON c.id = v.id_cliente
                 WHERE v.id = ? AND v.id_empresa = ? AND v.eliminado = false"
            );
            $stRec->execute([(int)$data['id_factura'], $idEmpresa]);
            $recibo = $stRec->fetch(\PDO::FETCH_ASSOC);
            if (!$recibo) {
                throw new \Exception('Recibo no encontrado.');
            }
            if (in_array($recibo['estado'] ?? '', ['anulado', 'facturado'], true)) {
                throw new \Exception('El recibo está ' . $recibo['estado'] . '; no se pueden registrar cobros.');
            }

            $punto = null;
            try {
                $stPunto = $db->prepare(
                    "SELECT p.id, e.codigo AS establecimiento, p.codigo_punto AS punto, p.id_establecimiento
                     FROM empresa_punto_emision p
                     JOIN empresa_establecimiento e ON e.id = p.id_establecimiento
                     WHERE p.id = ? AND p.id_empresa = ? AND p.eliminado = false"
                );
                $stPunto->execute([(int)$data['id_punto_emision'], $idEmpresa]);
                $punto = $stPunto->fetch(\PDO::FETCH_ASSOC);
            } catch (\Throwable $ignored) {}
            if (!$punto) {
                $stPunto2 = $db->prepare(
                    "SELECT id, establecimiento, punto, id_establecimiento
                     FROM empresa_puntos_emision WHERE id = ? AND id_empresa = ? AND eliminado = false"
                );
                $stPunto2->execute([(int)$data['id_punto_emision'], $idEmpresa]);
                $punto = $stPunto2->fetch(\PDO::FETCH_ASSOC);
            }
            if (!$punto) {
                throw new \Exception('Punto de emisión no válido.');
            }

            $secuencialService = new \App\Services\SecuencialService();
            $secRes = $secuencialService->obtenerSiguienteSecuencial((int)$data['id_punto_emision'], 'Ingresos');

            $stSaldo = $db->prepare(
                "SELECT COALESCE(SUM(id2.monto_cobrado), 0)
                 FROM ingresos_detalle id2
                 INNER JOIN ingresos_cabecera ic2 ON id2.id_ingreso = ic2.id
                 WHERE id2.tipo_documento = 'RECIBO'
                   AND id2.id_referencia_documento = ?
                   AND ic2.estado != 'anulado' AND ic2.eliminado = false"
            );
            $stSaldo->execute([(int)$data['id_factura']]);
            $totalCobrado  = (float) $stSaldo->fetchColumn();
            $saldoAnterior = round((float)$recibo['importe_total'] - $totalCobrado, 2);
            $montoCobrar   = round((float)$data['monto_cobrar'], 2);

            $numDoc = $recibo['establecimiento'] . '-' . $recibo['punto_emision'] . '-' . $recibo['secuencial'];

            $payload = [
                'id_empresa'          => $idEmpresa,
                'id_establecimiento'  => (int)($punto['id_establecimiento'] ?? 0),
                'id_punto_emision'    => (int)$punto['id'],
                'id_cliente'          => (int)$recibo['id_cliente'],
                'id_usuario'          => $idUsuario,
                'fecha_emision'       => $data['fecha_emision'],
                'establecimiento'     => $punto['establecimiento'],
                'punto_emision'       => $punto['punto'],
                'secuencial'          => $secRes['formateado'],
                'numero_ingreso'      => str_pad((string)($punto['establecimiento'] ?? '001'), 3, '0', STR_PAD_LEFT)
                                         . '-' . str_pad((string)($punto['punto'] ?? '001'), 3, '0', STR_PAD_LEFT)
                                         . '-' . $secRes['formateado'],
                'tipo_ingreso'        => 'RECIBO_VENTA',
                'id_ingreso_concepto' => !empty($data['id_ingreso_concepto']) ? (int)$data['id_ingreso_concepto'] : null,
                'monto_total'         => $montoCobrar,
                'observaciones'       => !empty($data['observaciones']) ? $data['observaciones'] : 'Cobro de recibo ' . $numDoc,
                'recibo_de'           => $recibo['cliente_nombre'],
                'id_recibo_cliente'   => (int)$recibo['id_cliente'],
                'detalles'            => [[
                    'tipo_documento'          => 'RECIBO',
                    'id_referencia_documento' => (int)$data['id_factura'],
                    'numero_documento'        => $numDoc,
                    'descripcion'             => 'Cobro de recibo ' . $numDoc,
                    'monto_documento'         => (float)$recibo['importe_total'],
                    'saldo_anterior'          => $saldoAnterior,
                    'monto_cobrado'           => $montoCobrar,
                    'saldo_actual'            => max(0.0, $saldoAnterior - $montoCobrar),
                ]],
                'pagos' => [[
                    'id_forma_cobro'          => (int)$data['id_forma_cobro'],
                    'monto'                   => $montoCobrar,
                    'referencia'              => $data['referencia'] ?? null,
                    'tipo_operacion_bancaria' => $data['tipo_operacion_bancaria'] ?? null,
                    'numero_cheque'           => $data['numero_operacion'] ?? null,
                ]],
            ];

            $ingresoService = new \App\Services\modulos\IngresoService(
                new \App\repositories\modulos\IngresoRepository(),
                new \App\Rules\modulos\IngresoRules(),
                new \App\Services\LogSistemaService()
            );

            $idIngreso = $ingresoService->crear($payload);
            echo json_encode(['ok' => true, 'msg' => 'Cobro registrado con éxito.', 'id_ingreso' => $idIngreso]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    private function renderFilaHtml(array $r): string
    {
        $rowData      = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
        $numero       = htmlspecialchars(($r['establecimiento'] ?? '') . '-' . ($r['punto_emision'] ?? '') . '-' . ($r['secuencial'] ?? ''));
        $fecha        = !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '-';
        $estado       = $r['estado'] ?? 'emitido';
        $estadoClass  = match ($estado) {
            'facturado' => 'bg-primary bg-opacity-10 text-primary border-primary',
            'emitido'   => 'bg-success bg-opacity-10 text-success border-success',
            'anulado'   => 'bg-danger bg-opacity-10 text-danger border-danger',
            'borrador'  => 'bg-secondary bg-opacity-10 text-secondary border-secondary',
            default     => 'bg-info bg-opacity-10 text-info border-info',
        };
        $estadoBadge  = '<span class="badge ' . $estadoClass . ' border border-opacity-25">' . ucfirst($estado) . '</span>';

        $conImp = ($r['con_impuestos'] === true || $r['con_impuestos'] === 't' || $r['con_impuestos'] === 'true' || $r['con_impuestos'] == 1);
        $impBadge = $conImp
            ? '<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">Con impuestos</span>'
            : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Sin impuestos</span>';

        $ivaCalc = max(0, (float)($r['importe_total'] ?? 0) - (float)($r['total_sin_impuestos'] ?? 0) + (float)($r['total_descuento'] ?? 0) - (float)($r['total_ice'] ?? 0) - (float)($r['propina'] ?? 0));

        $importeTotal = (float)($r['importe_total'] ?? 0);
        $cobrado      = (float)($r['total_cobrado'] ?? 0);
        $saldo        = max(0, $importeTotal - $cobrado);

        if ($saldo <= 0.01) {
            $estadoPagoBadge = '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Pagado</span>';
        } elseif ($cobrado > 0) {
            $estadoPagoBadge = '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">Abonado</span>';
        } else {
            $estadoPagoBadge = '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Pendiente</span>';
        }

        return '<tr class="factura-row" role="button" tabindex="0" data-row=\'' . $rowData . '\' onclick="abrirModalFacturaVer(this)">
                <td class="ps-3" data-col="numero"><code class="text-secondary">' . $numero . '</code></td>
                <td data-col="fecha_emision">' . $fecha . '</td>
                <td class="fw-medium text-truncate" data-col="cliente_nombre" style="max-width:200px">' . htmlspecialchars($r['cliente_nombre'] ?? '') . '</td>
                <td data-col="cliente_ruc"><small class="text-muted">' . htmlspecialchars($r['cliente_ruc'] ?? '') . '</small></td>
                <td class="text-center" data-col="impuestos">' . $impBadge . '</td>
                <td class="text-end" data-col="total_sin_impuestos">$' . number_format((float)($r['total_sin_impuestos'] ?? 0), 2) . '</td>
                <td class="text-end text-danger" data-col="total_descuento">$' . number_format((float)($r['total_descuento'] ?? 0), 2) . '</td>
                <td class="text-end" data-col="iva">$' . number_format($ivaCalc, 2) . '</td>
                <td class="text-end" data-col="total_ice">$' . number_format((float)($r['total_ice'] ?? 0), 2) . '</td>
                <td class="text-end" data-col="propina">$' . number_format((float)($r['propina'] ?? 0), 2) . '</td>
                <td class="text-end fw-bold" data-col="importe_total">$' . number_format((float)($r['importe_total'] ?? 0), 2) . '</td>
                <td data-col="vendedor_nombre"><span class="text-muted">' . htmlspecialchars($r['vendedor_nombre'] ?? '') . '</span></td>
                <td data-col="observaciones" class="text-truncate" style="max-width:180px">' . htmlspecialchars($r['observaciones'] ?? '') . '</td>
                <td data-col="usuario_nombre">' . htmlspecialchars($r['usuario_nombre'] ?? '') . '</td>
                <td class="text-center" data-col="estado_pago">' . $estadoPagoBadge . '</td>
                <td class="text-center pe-3" data-col="estado">' . $estadoBadge . '</td>
              </tr>';
    }
}
