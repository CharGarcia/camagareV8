<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\core\Controller;
use App\models\modulos\FacturaVenta;
use App\Services\modulos\FacturaVentaService;
use App\repositories\modulos\FacturaVentaRepository;
use App\repositories\modulos\InventarioRepository;
use App\models\Empresa;

class FacturaVentaController extends BaseModuloController
{
    private $service;
    private $repository;

    protected function getRutaModulo(): string
    {
        return 'modulos/factura-venta';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new FacturaVentaRepository();
        $rules            = new \App\Rules\modulos\FacturaVentaRules();
        $logService       = new \App\Services\LogSistemaService();
        $this->service    = new FacturaVentaService($this->repository, $rules, $logService);
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

        // Cargar datos maestros para el modal de nueva factura
        $empresaModel = new \App\models\Empresa();
        $empresaData  = $empresaModel->getPorId($idEmpresa);

        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        $puntos = [];
        if (!empty($establecimientos)) {
            $puntos = $empresaModel->getPuntosEmision((int) $establecimientos[0]['id']);
            // Fusionar config del establecimiento principal en $empresaData
            try {
                $estRepo   = new \App\repositories\modulos\EmpresaRepository();
                $estConfig = $estRepo->getEstablecimientoConfig((int) $establecimientos[0]['id']);
                if ($estConfig) {
                    $empresaData = array_merge($empresaData ?? [], $estConfig);
                }
            } catch (\Throwable $e) {
                // Migración pendiente �€” se usan valores por defecto
            }
        }

        $vendedorRepo = new \App\repositories\modulos\VendedorRepository();
        $vendedores = $vendedorRepo->getListado($idEmpresa, '', 1, 1000, 'nombre', 'ASC')['rows'];

        $bodegaRepo = new \App\repositories\modulos\BodegaRepository();
        $bodegas = $bodegaRepo->getBodegasPermitidas((int)$_SESSION['id_usuario'], $idEmpresa, (int)$_SESSION['nivel']);

        $total = $result['total'];
        $permNC = $this->permisosModuloPorRuta('modulos/notas_credito');
        $permGR = $this->permisosModuloPorRuta('modulos/guias_remision');

        $this->viewWithLayout('layouts.main', 'modulos/factura_venta/index', [
            'titulo'      => 'Facturas de Venta',
            'perm'        => $perm,
            'permNC'      => $permNC,
            'permGR'      => $permGR,
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
            echo '<tr><td colspan="16" class="text-center py-5 text-muted"><i class="bi bi-receipt fs-3 d-block mb-2"></i>No se encontraron facturas.</td></tr>';
        } else {
            foreach ($rows as $r) {
                echo $this->renderFilaHtml($r);
            }
        }
        $rowsHtml = ob_get_clean();

        ob_start();
        $prevDisabled = ($page <= 1)           ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        echo '<button type="button" class="btn btn-outline-secondary" ' . $prevDisabled . ' onclick="window.FV_cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>
              <button type="button" class="btn btn-outline-secondary" ' . $nextDisabled . ' onclick="window.FV_cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>';
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
            echo json_encode(['ok' => false, 'mensaje' => 'Factura no encontrada']);
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

    public function nuevo(): void
    {
        $this->requireCrear();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $empresaModel = new \App\models\Empresa();
        $empresaData  = $empresaModel->getPorId($idEmpresa);

        // Cargar datos maestros para la vista de nueva factura
        $vendedorRepo = new \App\repositories\modulos\VendedorRepository();
        $vendedores = $vendedorRepo->getListado($idEmpresa, '', 1, 1000, 'nombre', 'ASC')['rows'];

        $bodegaRepo = new \App\repositories\modulos\BodegaRepository();
        $bodegas = $bodegaRepo->getBodegasPermitidas((int)$_SESSION['id_usuario'], $idEmpresa, (int)$_SESSION['nivel']);

        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        $puntos = [];
        if (!empty($establecimientos)) {
            $puntos = $empresaModel->getPuntosEmision((int) $establecimientos[0]['id']);
        }

        $this->viewWithLayout('layouts.main', 'modulos/factura_venta/nuevo', [
            'titulo' => 'Nueva Factura',
            'perm' => $this->getPermisos(),
            'empresa' => $empresaData,
            'formasPago' => $this->repository->getFormasPago(),
            'impuestos' => $this->repository->getImpuestosConfig(),
            'tarifasIva' => $this->repository->getTarifasIva(),
            'unidades' => $this->repository->getUnidadesMedida(),
            'bodegas' => $bodegas,
            'vendedores' => $vendedores,
            'puntos' => $puntos,
            'base' => BASE_URL,
            'rutaModulo' => $this->getRutaModulo(),
            'fullWidth' => true
        ]);
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
        $tipo    = $_GET['tipo'] ?? 'factura'; // 'factura', 'retencion', etc.

        // Mapeo simple de tipo corto a nombre oficial en el servicio
        $mapTipos = [
            'factura'   => 'Facturas de venta',
            'retencion' => 'Retenciones de compras',
            'nc'        => 'Nota de crédito',
            'nd'        => 'Nota de débito',
            'guia'      => 'Guía de remisión',
            'ingresos'  => 'Ingresos',
        ];

        $tipoDoc = $mapTipos[$tipo] ?? 'Facturas de venta';

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

            // Si los datos vienen como un JSON en la clave 'data' (común en envíos complejos)
            if (isset($_POST['data'])) {
                $data = json_decode($_POST['data'], true);
            }

            $data['id_empresa'] = (int) $_SESSION['id_empresa'];
            $data['id_usuario'] = (int) $_SESSION['id_usuario'];

            // Cargar configuración de la empresa para que el service pueda usarla
            $empresaModel = new \App\models\Empresa();
            $empresaData  = $empresaModel->getPorId($data['id_empresa']) ?? [];
            $data['empresa_config'] = $empresaData;

            $idExistente = !empty($data['id']) ? (int) $data['id'] : 0;

            if ($idExistente > 0) {
                $this->requireActualizar();
                
                // Verificar si la factura está en estado borrador
                $cabeceraDb = $this->repository->getPorId($idExistente);
                if ($cabeceraDb && ($cabeceraDb['estado'] ?? '') !== 'borrador') {
                    // Factura autorizada: solo actualizar el vendedor.
                    $idVendedor = !empty($data['id_vendedor']) ? (int) $data['id_vendedor'] : null;
                    $this->service->actualizarVendedor($idExistente, $idVendedor, $data['id_empresa'], $data['id_usuario']);

                    // Si no tiene asiento contable, generarlo por primera vez.
                    // Si ya tiene uno, no se toca.
                    $asientoWarning = null;
                    if (empty($cabeceraDb['id_asiento_contable'])) {
                        $numFactura = $cabeceraDb['establecimiento'] . '-'
                                    . $cabeceraDb['punto_emision'] . '-'
                                    . str_pad((string)$cabeceraDb['secuencial'], 9, '0', STR_PAD_LEFT);
                        $data['fecha_emision'] = $cabeceraDb['fecha_emision'];
                        $data['id_cliente']    = $cabeceraDb['id_cliente'];
                        try {
                            $this->service->procesarAsientoContable($idExistente, $data, $numFactura);
                        } catch (\Throwable $eAsiento) {
                            error_log("[FacturaVenta] Asiento no generado para factura $idExistente: " . $eAsiento->getMessage());
                            $asientoWarning = $eAsiento->getMessage();
                        }
                    }

                    $id = $idExistente;
                    $mensaje = 'Factura actualizada exitosamente.';
                } else {
                    // Actualización completa de factura existente (borrador)
                    $id = $this->service->actualizar($idExistente, $data);
                    $asientoWarning = $this->service->getLastAsientoWarning();
                    $mensaje = 'Factura actualizada exitosamente.';
                }
            } else {
                // Creación de nueva factura
                $this->requireCrear();
                $id = $this->service->crear($data);
                $asientoWarning = $this->service->getLastAsientoWarning();
                $mensaje = 'Factura guardada exitosamente.';
            }

            $ventaDB = $this->repository->getPorId($id);
            $rowHtml = $ventaDB ? $this->renderFilaHtml($ventaDB) : '';

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
        $idVenta   = (int) ($_GET['id'] ?? $_POST['id'] ?? $_GET['id_venta'] ?? $_POST['id_venta'] ?? 0);

        try {
            $data = $_POST;
            if (isset($_POST['data'])) {
                $data = json_decode($_POST['data'], true);
            }
            if (empty($data)) {
                $data = $_GET;
            }

            if ($idVenta > 0 && empty($data['detalles'])) {
                // Obtener datos reales de base de datos
                $ventaDB = $this->service->getPorId($idVenta, $idEmpresa);
                $idAsiento = (int)($ventaDB['id_asiento_contable'] ?? 0);

                if ($idAsiento > 0) {
                    $asientoRepo = new \App\repositories\modulos\AsientoContableRepository();
                    $asientoRules = new \App\Rules\modulos\AsientoContableRules();
                    $logService = new \App\Services\LogSistemaService();
                    $asientoService = new \App\Services\modulos\AsientoContableService($asientoRepo, $asientoRules, $logService);
                    
                    $cabeceraDb = $asientoService->getDetalleAsiento($idAsiento, $idEmpresa);
                    $detallesDb = $cabeceraDb['detalles'] ?? [];
                    $detalles = [];
                    foreach ($detallesDb as $det) {
                        $detalles[] = [
                            'id_cuenta_contable'  => (int)$det['id_cuenta_contable'],
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
                'importe_total' => $data['importe_total'] ?? $data['total'] ?? 0,
                'total_sin_impuestos' => $data['total_sin_impuestos'] ?? $data['subtotal'] ?? 0,
                'total_descuento' => $data['total_descuento'] ?? $data['descuento'] ?? 0,
                'propina' => $data['propina'] ?? 0,
                'id_cliente' => $data['id_cliente'] ?? 0,
            ];

            if (isset($data['ivas'])) {
                $ivasArr = json_decode($data['ivas'], true);
                if (is_array($ivasArr)) {
                    $ivaSum = 0;
                    foreach ($ivasArr as $iv) {
                        $ivaSum += (float)($iv['valor'] ?? 0);
                    }
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
        // $this->requireModificar();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $idVenta   = (int) ($_POST['id_venta'] ?? 0);

        if ($idVenta <= 0) {
            echo json_encode(['ok' => false, 'error' => 'ID de factura inválido.']);
            exit;
        }

        try {
            // Obtener datos completos de la factura para generar el asiento
            $data = $this->service->getPorId($idVenta, $idEmpresa);
            if (!$data) {
                throw new \Exception("Factura no encontrada.");
            }
            
            // Adjuntar detalles necesarios para la cabecera del asiento y recálculo
            $data['id_empresa'] = $idEmpresa;
            $data['id_usuario'] = $idUsuario;
            $data['id_venta'] = $idVenta;
            
            // Construir numFactura para la referencia
            $numFactura = ($data['establecimiento'] ?? '001') . '-' . ($data['punto_emision'] ?? '001') . '-' . str_pad((string)($data['secuencial'] ?? 0), 9, '0', STR_PAD_LEFT);

            // Generar o actualizar el asiento
            $this->service->procesarAsientoContable($idVenta, $data, $numFactura);

            echo json_encode(['ok' => true, 'mensaje' => 'Asiento generado/actualizado con �exito.']);
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
            $factura       = $this->repository->getPorId($id);
            if (!$factura || (int)($factura['id_empresa'] ?? 0) !== $idEmpresa) {
                http_response_code(404); echo 'Factura no encontrada'; exit;
            }

            $detalles      = $this->repository->getDetalles($id);
            foreach ($detalles as &$d) {
                $d['impuestos'] = $this->repository->getImpuestosDetalle((int)$d['id']);
            }
            unset($d);

            $pagos         = $this->repository->getPagos($id);
            $infoAdicional = $this->repository->getInfoAdicional($id);

            $empresaModel  = new \App\models\Empresa();
            $empresa       = $empresaModel->getPorId($idEmpresa) ?? [];

            $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
            if (!empty($establecimientos)) {
                // Asignar campos críticos del establecimiento antes del try/catch
                if (!empty($establecimientos[0]['logo_ruta'])) {
                    $empresa['logo_ruta'] = $establecimientos[0]['logo_ruta'];
                }
                if (!empty($establecimientos[0]['direccion'])) {
                    $empresa['direccion_establecimiento'] = $establecimientos[0]['direccion'];
                }
                if (!empty($establecimientos[0]['leyenda_pdf_titulo'])) {
                    $empresa['leyenda_pdf_titulo'] = $establecimientos[0]['leyenda_pdf_titulo'];
                }
                if (!empty($establecimientos[0]['leyenda_pdf_mensaje'])) {
                    $empresa['leyenda_pdf_mensaje'] = $establecimientos[0]['leyenda_pdf_mensaje'];
                }
                try {
                    $estRepo   = new \App\repositories\modulos\EmpresaRepository();
                    $estConfig = $estRepo->getEstablecimientoConfig((int) $establecimientos[0]['id']);
                    if ($estConfig) {
                        $estConfig['direccion_matriz'] = $empresa['direccion'] ?? '';
                        $estConfig['direccion_establecimiento'] = $establecimientos[0]['direccion'] ?? '';

                        if (!empty($establecimientos[0]['logo_ruta'])) {
                            $estConfig['logo_ruta'] = $establecimientos[0]['logo_ruta'];
                        }

                        if (!empty($establecimientos[0]['leyenda_pdf_titulo'])) {
                            $estConfig['leyenda_pdf_titulo'] = $establecimientos[0]['leyenda_pdf_titulo'];
                        }
                        if (!empty($establecimientos[0]['leyenda_pdf_mensaje'])) {
                            $estConfig['leyenda_pdf_mensaje'] = $establecimientos[0]['leyenda_pdf_mensaje'];
                        }

                        $empresa = array_merge($empresa, $estConfig);
                    }
                } catch (\Throwable $e) {}
            }

            $renderer  = new \App\Services\PlantillasPdfRendererService();
            $plantilla = $renderer->getPlantillaActiva($idEmpresa, 'factura_venta');

            if ($plantilla) {
                $renderer->generar($plantilla, $factura, $detalles, $pagos, $infoAdicional, $empresa);
            } else {
                $pdfService = new \App\Services\modulos\FacturaVentaPdfService();
                $pdfService->generar($factura, $detalles, $pagos, $infoAdicional, $empresa);
            }
        } catch (\Throwable $e) {
            http_response_code(500); echo 'Error al generar PDF: ' . $e->getMessage();
        }
        exit;
    }

    public function getPlantillasWhatsappAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idFactura = (int) ($_GET['id_factura'] ?? 0);
        
        try {
            // Verificar si tiene configuración de WhatsApp
            $configModel = new \App\models\WhatsappConfig();
            $config = $configModel->obtenerConfiguracion($idEmpresa);

            if (!$config || empty($config['access_token']) || empty($config['phone_number_id'])) {
                echo json_encode([
                    'ok' => true,
                    'configurado' => false
                ]);
                return;
            }

            $modeloPlantilla = new \App\models\WhatsappPlantilla();
            $plantillas = $modeloPlantilla->getPlantillasAprobadas($idEmpresa);

            $telefonoCliente = '593';
            if ($idFactura > 0) {
                $factura = $this->repository->getPorId($idFactura);
                if ($factura && !empty($factura['id_cliente'])) {
                    $stmtCl = $this->db->prepare("SELECT telefono FROM clientes WHERE id = ? AND id_empresa = ? AND eliminado = FALSE");
                    $stmtCl->execute([(int)$factura['id_cliente'], $idEmpresa]);
                    $cliente = $stmtCl->fetch(\PDO::FETCH_ASSOC);
                    if ($cliente && !empty($cliente['telefono'])) {
                        $tel = trim($cliente['telefono']);
                        // Si empieza con 0, lo quitamos y agregamos 593
                        if (str_starts_with($tel, '0')) {
                            $telefonoCliente = '593' . substr($tel, 1);
                        } elseif (!str_starts_with($tel, '593')) {
                            // Si no empieza con 593 ni con 0, le agregamos el 593
                            $telefonoCliente = '593' . $tel;
                        } else {
                            $telefonoCliente = $tel;
                        }
                    }
                }
            }

            // Buscar plantilla por defecto (que contenga "venta" o "factura" en el nombre)
            $idPlantillaDefault = 0;
            foreach ($plantillas as $p) {
                if (stripos($p['nombre'], 'venta') !== false || stripos($p['nombre'], 'factura') !== false) {
                    $idPlantillaDefault = $p['id'];
                    break;
                }
            }

            echo json_encode([
                'ok' => true,
                'plantillas' => $plantillas,
                'telefono_cliente' => $telefonoCliente,
                'id_plantilla_default' => $idPlantillaDefault
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function enviarWhatsappAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
            return;
        }

        $idEmpresa   = (int) $_SESSION['id_empresa'];
        $idFactura   = (int) ($_POST['id_factura'] ?? 0);
        $idPlantilla = (int) ($_POST['id_plantilla'] ?? 0);
        $telefono    = trim($_POST['telefono'] ?? '');

        if ($idFactura <= 0 || $idPlantilla <= 0 || empty($telefono)) {
            echo json_encode(['ok' => false, 'error' => 'Datos incompletos.']);
            return;
        }

        try {
            // Obtener Factura
            $factura = $this->repository->getPorId($idFactura);
            if (!$factura || (int)($factura['id_empresa'] ?? 0) !== $idEmpresa) {
                echo json_encode(['ok' => false, 'error' => 'Factura no encontrada.']);
                return;
            }

            // Obtener Plantilla
            $modeloPlantilla = new \App\models\WhatsappPlantilla();
            $stmt = $this->db->prepare("SELECT * FROM whatsapp_plantillas WHERE id = ? AND id_empresa = ?");
            $stmt->execute([$idPlantilla, $idEmpresa]);
            $plantillaMeta = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$plantillaMeta || $plantillaMeta['estado_meta'] !== 'APPROVED') {
                echo json_encode(['ok' => false, 'error' => 'Plantilla no válida o no aprobada.']);
                return;
            }

            // --- Generar el PDF como String ---
            $detalles = $this->repository->getDetalles($idFactura);
            foreach ($detalles as &$d) {
                $d['impuestos'] = $this->repository->getImpuestosDetalle((int)$d['id']);
            }
            unset($d);

            $pagos         = $this->repository->getPagos($idFactura);
            $infoAdicional = $this->repository->getInfoAdicional($idFactura);

            $empresaModel  = new \App\models\Empresa();
            $empresa       = $empresaModel->getPorId($idEmpresa) ?? [];

            $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
            if (!empty($establecimientos)) {
                // Asignar campos críticos del establecimiento antes del try/catch
                if (!empty($establecimientos[0]['logo_ruta'])) {
                    $empresa['logo_ruta'] = $establecimientos[0]['logo_ruta'];
                }
                if (!empty($establecimientos[0]['direccion'])) {
                    $empresa['direccion_establecimiento'] = $establecimientos[0]['direccion'];
                }
                if (!empty($establecimientos[0]['leyenda_pdf_titulo'])) {
                    $empresa['leyenda_pdf_titulo'] = $establecimientos[0]['leyenda_pdf_titulo'];
                }
                if (!empty($establecimientos[0]['leyenda_pdf_mensaje'])) {
                    $empresa['leyenda_pdf_mensaje'] = $establecimientos[0]['leyenda_pdf_mensaje'];
                }
                try {
                    $estRepo   = new \App\repositories\modulos\EmpresaRepository();
                    $estConfig = $estRepo->getEstablecimientoConfig((int) $establecimientos[0]['id']);
                    if ($estConfig) {
                        $estConfig['direccion_matriz'] = $empresa['direccion'] ?? '';
                        $estConfig['direccion_establecimiento'] = $establecimientos[0]['direccion'] ?? '';

                        if (!empty($establecimientos[0]['logo_ruta'])) {
                            $estConfig['logo_ruta'] = $establecimientos[0]['logo_ruta'];
                        }

                        if (!empty($establecimientos[0]['leyenda_pdf_titulo'])) {
                            $estConfig['leyenda_pdf_titulo'] = $establecimientos[0]['leyenda_pdf_titulo'];
                        }
                        if (!empty($establecimientos[0]['leyenda_pdf_mensaje'])) {
                            $estConfig['leyenda_pdf_mensaje'] = $establecimientos[0]['leyenda_pdf_mensaje'];
                        }

                        $empresa = array_merge($empresa, $estConfig);
                    }
                } catch (\Throwable $e) {}
            }

            $renderer  = new \App\Services\PlantillasPdfRendererService();
            $plantillaPdf = $renderer->getPlantillaActiva($idEmpresa, 'factura_venta');

            if ($plantillaPdf) {
                $pdfString = $renderer->generar($plantillaPdf, $factura, $detalles, $pagos, $infoAdicional, $empresa, 'S');
            } else {
                $pdfService = new \App\Services\modulos\FacturaVentaPdfService();
                $pdfString = $pdfService->generar($factura, $detalles, $pagos, $infoAdicional, $empresa, 'S');
            }

            if (empty($pdfString)) {
                echo json_encode(['ok' => false, 'error' => 'No se pudo generar el PDF de la factura.']);
                return;
            }

            // Guardar el PDF en un archivo temporal para enviarlo
            $tmpPdfPath = sys_get_temp_dir() . '/factura_' . $idFactura . '_' . time() . '.pdf';
            file_put_contents($tmpPdfPath, $pdfString);

            $whatsappService = new \App\services\WhatsappService();
            
            // Subir Media
            $uploadResult = $whatsappService->uploadMessageMedia($idEmpresa, $tmpPdfPath, 'application/pdf');
            unlink($tmpPdfPath); // Borrar temporal inmediatamente

            if (!$uploadResult['success']) {
                echo json_encode(['ok' => false, 'error' => 'Error subiendo PDF a Meta: ' . $uploadResult['message']]);
                return;
            }

            // Mapear Componentes
            $componentes = json_decode($plantillaMeta['componentes'], true) ?? [];
            $apiComponents = [];

            // Datos para las variables (1: Nombre, 2: Numero Factura, 3: Total)
            $numeroFactura = ($factura['establecimiento'] ?? '') . '-' . ($factura['punto_emision'] ?? '') . '-' . ($factura['secuencial'] ?? '');
            $total = number_format((float)($factura['importe_total'] ?? 0), 2);
            $nombreCliente = $factura['cliente_nombre'] ?? 'Cliente';

            foreach ($componentes as $comp) {
                $type = $comp['type'] ?? '';

                if ($type === 'HEADER') {
                    $format = $comp['format'] ?? '';
                    if ($format === 'DOCUMENT') {
                        $apiComponents[] = [
                            'type' => 'header',
                            'parameters' => [
                                [
                                    'type' => 'document',
                                    'document' => [
                                        'id' => $uploadResult['media_id'],
                                        'filename' => 'Factura_' . $numeroFactura . '.pdf'
                                    ]
                                ]
                            ]
                        ];
                    }
                } elseif ($type === 'BODY') {
                    $texto = $comp['text'] ?? '';
                    if (preg_match_all('/{{(\d+)}}/', $texto, $matches)) {
                        $numVars = max($matches[1]);
                        $parameters = [];
                        for ($i = 1; $i <= $numVars; $i++) {
                            $val = '';
                            if ($i == 1) $val = $nombreCliente;
                            elseif ($i == 2) $val = $numeroFactura;
                            elseif ($i == 3) $val = '$' . $total;
                            else $val = '-'; // Por si hay más variables no mapeadas

                            $parameters[] = [
                                'type' => 'text',
                                'text' => $val
                            ];
                        }

                        $apiComponents[] = [
                            'type' => 'body',
                            'parameters' => $parameters
                        ];
                    }
                }
            }

            // Enviar Mensaje
            $result = $whatsappService->sendTemplateMessage(
                $idEmpresa,
                $telefono,
                $plantillaMeta['nombre'],
                $plantillaMeta['idioma'],
                $apiComponents
            );

            if (!$result['success']) {
                echo json_encode(['ok' => false, 'error' => 'Error enviando mensaje: ' . $result['message']]);
                return;
            }

            // --- Guardar en la Base de Datos para el Webhook ---
            try {
                $metaMessageId = $result['data']['messages'][0]['id'] ?? null;
                $repoMsj = new \App\repositories\modulos\WhatsappMensajeRepository();
                $idChat = $repoMsj->getOrCreateChat($idEmpresa, $telefono, $nombreCliente, 'Factura enviada', false);
                $repoMsj->saveMessage(
                    $idEmpresa,
                    $idChat,
                    'OUT',
                    $telefono,
                    'template',
                    ['template' => $plantillaMeta['nombre']],
                    $metaMessageId,
                    'sent'
                );
            } catch (\Throwable $ex) {
                // Si falla al guardar en BD, no detenemos el proceso porque el mensaje ya se envió
                error_log("Error guardando mensaje en BD: " . $ex->getMessage());
            }

            echo json_encode(['ok' => true, 'mensaje' => 'Factura enviada por WhatsApp exitosamente.']);

        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => 'Error inesperado: ' . $e->getMessage()]);
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
        $result = $repo->getListado($idEmpresa, $buscar, 1, 10, 'nombre', 'ASC');

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
        $result = $repo->getListado($idEmpresa, $buscar, 1, 15, 'nombre', 'ASC', null, 'venta');

        // Cargar precios adicionales y variantes para cada producto
        $rows = array_map(function ($p) use ($repo, $idEmpresa) {
            $p['precios_lista'] = $repo->getPrecios((int)$p['id'], $idEmpresa);
            $p['variantes']     = $repo->getVariantes((int)$p['id'], $idEmpresa);
            return $p;
        }, $result['rows']);

        echo json_encode(['ok' => true, 'data' => $rows]);
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

        // Solo se puede eliminar si está en estado borrador
        $factura = $this->repository->getPorId($id);
        if (!$factura || (int)($factura['id_empresa'] ?? 0) !== $idEmpresa) {
            echo json_encode(['ok' => false, 'mensaje' => 'Factura no encontrada.']);
            exit;
        }
        if (($factura['estado'] ?? '') !== 'borrador') {
            echo json_encode(['ok' => false, 'mensaje' => 'Solo se pueden eliminar facturas en estado borrador.']);
            exit;
        }

        $db = \App\core\Database::getConnection();
        $db->beginTransaction();
        try {
            $this->service->eliminar($id, $idEmpresa, $idUsuario);
            $db->commit();
            echo json_encode(['ok' => true, 'mensaje' => 'Factura eliminada correctamente.']);
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function getLotesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $idProducto = (int) ($_GET['id_producto'] ?? 0);
        $idBodega   = (int) ($_GET['id_bodega'] ?? 0);
        $idVenta    = (int) ($_GET['id_venta'] ?? 0);

        if (!$idProducto || !$idBodega) {
            echo json_encode(['ok' => false, 'mensaje' => 'Faltan parámetros']);
            exit;
        }

        $repoInv = new \App\repositories\modulos\InventarioRepository();
        $excludeId   = ($idVenta > 0 ? $idVenta : null);
        $excludeTipo = ($idVenta > 0 ? 'factura_venta' : null);
        
        $lotes = $repoInv->getLotesDisponibles($idProducto, $idBodega, $idEmpresa, $excludeId, $excludeTipo);
        $stockTotal = $repoInv->getStockActual($idProducto, $idBodega, $idEmpresa, $excludeId, $excludeTipo);

        echo json_encode([
            'ok' => true, 
            'data' => $lotes,
            'stock_total' => $stockTotal
        ]);
        exit;
    }





    public function enviarSriAjax(): void
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
            $envioService = new \App\Services\Sri\SriEnvioService();
            $resultado    = $envioService->enviarFacturaVenta($id, $idEmpresa, $idUsuario);

            echo json_encode([
                'ok'                  => $resultado['ok'],
                'estado'              => $resultado['estado'],
                'estado_correo'       => $resultado['estado_correo'] ?? null,
                'mensaje'             => $resultado['mensaje'],
                'numero_autorizacion' => $resultado['numero_autorizacion'] ?? '',
                'fecha_autorizacion'  => $resultado['fecha_autorizacion']  ?? '',
                'errores'             => $resultado['errores'] ?? [],
            ]);
        } catch (\Throwable $e) {
            // Registrar error en el log SRI antes de responder
            try {
                $repo = new \App\repositories\modulos\FacturaVentaRepository();
                $cab  = $repo->getPorId($id);
                if ($cab && (int)$cab['id_empresa'] === $idEmpresa) {
                    (new \App\models\SriEnvioLog())->registrar([
                        'id_empresa'      => $idEmpresa,
                        'tipo_comprobante'=> 'factura_venta',
                        'id_comprobante'  => $id,
                        'clave_acceso'    => $cab['clave_acceso'] ?? null,
                        'tipo_ambiente'   => $cab['tipo_ambiente'] ?? '1',
                        'accion'          => 'error',
                        'estado_sri'      => 'ERROR',
                        'mensaje'         => $e->getMessage(),
                        'created_by'      => $idUsuario,
                    ]);
                }
            } catch (\Throwable) {}
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function getHistorialSriAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $tipo      = $_GET['tipo'] ?? 'factura_venta';

        if (!$id) { echo json_encode(['ok' => false, 'data' => []]); exit; }

        $logs = (new \App\models\SriEnvioLog())->getPorComprobante($tipo, $id, $idEmpresa);
        echo json_encode(['ok' => true, 'data' => $logs]);
        exit;
    }

    public function eliminarLogSriAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $idLog     = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$idLog) { echo json_encode(['ok' => false, 'mensaje' => 'ID requerido.']); exit; }

        $model = new \App\models\SriEnvioLog();
        $log   = $model->getPorId($idLog, $idEmpresa);

        if (!$log) {
            echo json_encode(['ok' => false, 'mensaje' => 'Registro no encontrado.']);
            exit;
        }
        if ($log['tipo_ambiente'] !== '1') {
            echo json_encode(['ok' => false, 'mensaje' => 'Los registros de ambiente de producción no se pueden eliminar.']);
            exit;
        }

        echo json_encode(['ok' => $model->eliminar($idLog, $idEmpresa)]);
        exit;
    }

    public function exportarXmlAjax(): void
    {
        $this->requireLeer();

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) { http_response_code(400); echo 'ID requerido'; exit; }

        try {
            $factura = $this->repository->getPorId($id);
            if (!$factura || (int)($factura['id_empresa'] ?? 0) !== $idEmpresa) {
                http_response_code(404); echo 'Factura no encontrada'; exit;
            }

            $numero   = ($factura['establecimiento'] ?? '001') . '-'
                      . ($factura['punto_emision']   ?? '001') . '-'
                      . str_pad((string)($factura['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT);
            $filename = 'factura_' . $numero . '.xml';

            // Servir desde detalle_xml si ya está persistido
            if (!empty($factura['detalle_xml'])) {
                header('Content-Type: application/xml; charset=UTF-8');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                echo $factura['detalle_xml'];
                exit;
            }

            // Fallback: regenerar, persistir y servir
            $detalles = $this->repository->getDetalles($id);
            foreach ($detalles as &$d) {
                $d['impuestos'] = $this->repository->getImpuestosDetalle((int)$d['id']);
            }
            unset($d);

            $pagos         = $this->repository->getPagos($id);
            $infoAdicional = $this->repository->getInfoAdicional($id);

            $empresaModel = new \App\models\Empresa();
            $empresa      = $empresaModel->getPorId($idEmpresa) ?? [];

            // Dirección del establecimiento (fallback: empresa.direccion)
            $dirEstablecimiento = null;
            if (!empty($factura['id_establecimiento'])) {
                try {
                    $estRepo = new \App\repositories\modulos\EmpresaRepository();
                    foreach ($estRepo->getEstablecimientos($idEmpresa) as $est) {
                        if ((int)$est['id'] === (int)$factura['id_establecimiento']) {
                            $dirEstablecimiento = $est['direccion'] ?? null;
                            break;
                        }
                    }
                } catch (\Throwable $e) {
                    // Usa empresa.direccion como fallback
                }
            }

            $xmlService = new \App\Services\Xml\XmlFacturaVentaService();
            $xmlString  = $xmlService->generar($factura, $detalles, $pagos, $infoAdicional, $empresa, $dirEstablecimiento);

            // Persistir para futuras descargas
            try {
                $this->repository->updateDetalleXml($id, $xmlString);
            } catch (\Throwable $e) {
                error_log('[FacturaVenta] No se pudo persistir detalle_xml en fallback: ' . $e->getMessage());
            }

            header('Content-Type: application/xml; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo $xmlString;
        } catch (\Throwable $e) {
            http_response_code(500); echo 'Error al generar XML: ' . $e->getMessage();
        }
        exit;
    }

    public function anularAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        if (!$id) {
            echo json_encode(['ok' => false, 'mensaje' => 'ID requerido.']);
            exit;
        }

        try {
            $this->service->anular($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Factura anulada correctamente e inventario reintegrado.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function getNotasCreditoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $numero    = $_GET['numero'] ?? '';
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (empty($numero)) {
            echo json_encode(['ok' => false, 'mensaje' => 'Número de factura requerido']);
            exit;
        }

        $repoNC = new \App\Repositories\modulos\NotaCreditoRepository();
        $notas  = $repoNC->getPorDocumentoModificado($numero, $idEmpresa);

        echo json_encode(['ok' => true, 'data' => $notas]);
        exit;
    }

    public function getGuiasAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $numero    = $_GET['numero'] ?? '';
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (empty($numero)) {
            echo json_encode(['ok' => false, 'mensaje' => 'Número de factura requerido']);
            exit;
        }

        $repoGR = new \App\Repositories\modulos\GuiaRemisionRepository();
        $guias  = $repoGR->getPorDocumentoSustento($numero, $idEmpresa);

        echo json_encode(['ok' => true, 'data' => $guias]);
        exit;
    }

    public function descargarXmlOriginalAjax(): void
    {
        $this->requireLeer();

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) { http_response_code(400); echo 'ID requerido'; exit; }

        $factura = $this->repository->getPorId($id);
        if (!$factura || (int)($factura['id_empresa'] ?? 0) !== $idEmpresa) {
            http_response_code(404); echo 'Factura no encontrada'; exit;
        }

        $xmlContent = $factura['detalle_xml'] ?? '';
        if (empty($xmlContent)) {
            http_response_code(404); echo 'Sin XML almacenado para esta factura'; exit;
        }

        $numero   = ($factura['establecimiento'] ?? '001') . '-'
                  . ($factura['punto_emision']   ?? '001') . '-'
                  . str_pad((string)($factura['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT);
        $filename = 'factura_' . $numero . '.xml';

        header('Content-Type: application/xml; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $xmlContent;
        exit;
    }

    public function countBorradoresAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $db  = \App\core\Database::getConnection();
            $sql = "SELECT COUNT(*) FROM ventas_cabecera
                    WHERE id_empresa = :id_empresa AND estado = 'borrador' AND eliminado = false AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";
            $st  = $db->prepare($sql);
            $st->execute([':id_empresa' => $idEmpresa]);
            $count = (int) $st->fetchColumn();
            echo json_encode(['ok' => true, 'count' => $count]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'count' => 0]);
        }
        exit;
    }

    public function getCobrosVinculadosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) {
            echo json_encode(['ok' => false, 'mensaje' => 'ID de factura requerido.']);
            exit;
        }

        try {
            $db = \App\core\Database::getConnection();

            // Verificar que la factura pertenece a la empresa
            $stFact = $db->prepare(
                "SELECT id, importe_total, establecimiento, punto_emision, secuencial
                 FROM ventas_cabecera
                 WHERE id = ? AND id_empresa = ? AND eliminado = false"
            );
            $stFact->execute([$id, $idEmpresa]);
            $factura = $stFact->fetch(\PDO::FETCH_ASSOC);

            if (!$factura) {
                echo json_encode(['ok' => false, 'mensaje' => 'Factura no encontrada.']);
                exit;
            }

            // Número de factura formateado (el mismo formato usado en num_doc_modificado y num_doc_sustento)
            $numeroFactura = $factura['establecimiento'] . '-'
                           . $factura['punto_emision']   . '-'
                           . $factura['secuencial'];

            // ── Cobros vinculados ──────────────────────────────────────────────────
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
                          WHERE id2.tipo_documento           = 'FACTURA'
                            AND id2.id_referencia_documento  = ?
                            AND ic.id_empresa                = ?
                            AND ic.eliminado                 = false
                          GROUP BY ic.id, ic.numero_ingreso, ic.fecha_emision, ic.monto_total,
                                   ic.estado, ic.observaciones, id2.monto_cobrado, u.nombre
                          ORDER BY ic.fecha_emision DESC, ic.id DESC";

            $stCobros = $db->prepare($sqlCobros);
            $stCobros->execute([$id, $idEmpresa]);
            $cobros = $stCobros->fetchAll(\PDO::FETCH_ASSOC);

            // ── Retenciones en Ventas ──────────────────────────────────────────────
            $sqlRet = "SELECT COALESCE(SUM(r.total_renta + r.total_iva + r.total_isd), 0)
                       FROM retencion_venta_cabecera r
                       WHERE r.id_empresa = ? AND r.eliminado = false
                         AND (r.id_venta = ? 
                              OR EXISTS (
                                  SELECT 1 FROM retencion_venta_detalle rd 
                                  WHERE rd.id_retencion = r.id 
                                    AND rd.num_doc_sustento = ?
                              )
                             )";
            $stRet = $db->prepare($sqlRet);
            $stRet->execute([$idEmpresa, $id, $numeroFactura]);
            $totalRetenciones = (float) $stRet->fetchColumn();

            $sqlRetDetalle = "SELECT r.id, r.establecimiento, r.punto_emision, r.secuencial,
                                     r.fecha_emision, r.total_renta, r.total_iva, r.total_isd,
                                     (r.total_renta + r.total_iva + r.total_isd) AS total_retenido,
                                     r.origen
                              FROM retencion_venta_cabecera r
                              WHERE r.id_empresa = ? AND r.eliminado = false
                                AND (r.id_venta = ? 
                                     OR EXISTS (
                                         SELECT 1 FROM retencion_venta_detalle rd 
                                         WHERE rd.id_retencion = r.id 
                                           AND rd.num_doc_sustento = ?
                                     )
                                    )
                              ORDER BY r.fecha_emision DESC";
            $stRetDetalle = $db->prepare($sqlRetDetalle);
            $stRetDetalle->execute([$idEmpresa, $id, $numeroFactura]);
            $retencionesArray = $stRetDetalle->fetchAll(\PDO::FETCH_ASSOC);

            // ── Total Notas de Crédito ─────────────────────────────────────────────
            $sqlNC = "SELECT COALESCE(SUM(nc.importe_total), 0)
                      FROM notas_credito_cabecera nc
                      WHERE nc.num_doc_modificado = ?
                        AND nc.id_empresa         = ?
                        AND nc.eliminado          = false
                        AND nc.estado            != 'anulado'";
            $stNC = $db->prepare($sqlNC);
            $stNC->execute([$numeroFactura, $idEmpresa]);
            $totalNC = (float) $stNC->fetchColumn();

            // ── Transacciones Payphone vinculadas ─────────────────────────────────
            $stPP = $db->prepare(
                "SELECT client_transaction_id, payment_id, monto, estado,
                        transaction_status, authorization_code, created_at, updated_at, tipo_flujo, id_ingreso
                 FROM payphone_transacciones
                 WHERE id_empresa    = ?
                   AND modulo        = 'factura_venta'
                   AND id_referencia = ?
                   AND eliminado     = false
                 ORDER BY created_at DESC"
            );
            $stPP->execute([$idEmpresa, $id]);
            $pagosTarjeta = $stPP->fetchAll(\PDO::FETCH_ASSOC);

            echo json_encode([
                'ok'                => true,
                'factura'           => $factura,
                'cobros'            => $cobros,
                'total_retenciones' => round($totalRetenciones, 2),
                'retenciones'       => $retencionesArray,
                'total_nc'          => round($totalNC, 2),
                'pagos_tarjeta'     => $pagosTarjeta,
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Devuelve los catálogos necesarios para el formulario de cobro rápido:
     * puntos de emisión, conceptos de ingreso, formas de cobro y bancos.
     */
    public function getIngresosCatalogosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $db        = \App\core\Database::getConnection();
        $puntos    = [];
        $conceptos = [];
        $formas    = [];
        $bancos    = [];

        // ── 1. Puntos de Emisión ──────────────────────────────────────────────────
        try {
            $stP = $db->prepare(
                "SELECT p.id         AS id_punto,
                        e.codigo     AS cod_establecimiento,
                        p.codigo_punto,
                        p.id_establecimiento
                 FROM empresa_punto_emision p
                 JOIN empresa_establecimiento e ON e.id = p.id_establecimiento
                 WHERE p.id_empresa = ?
                   AND p.eliminado  = false
                   AND e.eliminado  = false
                 ORDER BY e.codigo, p.codigo_punto"
            );
            $stP->execute([$idEmpresa]);
            $puntos = $stP->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            // Fallback: tabla renombrada (empresa_puntos_emision)
            try {
                $stP2 = $db->prepare(
                    "SELECT id AS id_punto,
                            establecimiento AS cod_establecimiento,
                            punto           AS codigo_punto,
                            id_establecimiento
                     FROM empresa_puntos_emision
                     WHERE id_empresa = ? AND eliminado = false
                     ORDER BY establecimiento, punto"
                );
                $stP2->execute([$idEmpresa]);
                $puntos = $stP2->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Throwable $ignored) {}
        }

        // ── 2. Conceptos de Ingreso ───────────────────────────────────────────────
        $error_conceptos = null;
        try {
            $stC = $db->prepare(
                "SELECT id, nombre, comportamiento
                 FROM empresa_opciones_ingreso_egreso
                 WHERE id_empresa = ? AND aplica_ingresos = TRUE
                   AND UPPER(estado) = 'ACTIVO' AND eliminado = FALSE
                 ORDER BY nombre ASC"
            );
            $stC->execute([$idEmpresa]);
            $conceptos = $stC->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $ignored) {
            $error_conceptos = $ignored->getMessage();
        }

        // ── 3. Formas de Cobro ────────────────────────────────────────────────────
        $error_formas = null;
        try {
            $stF = $db->prepare(
                "SELECT id, nombre, tipo
                 FROM empresa_formas_pago
                 WHERE id_empresa = ? AND eliminado = false AND activo = true
                   AND (aplica_en = 'AMBAS' OR aplica_en = 'INGRESO')
                 ORDER BY nombre ASC"
            );
            $stF->execute([$idEmpresa]);
            $formas = $stF->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $ignored) {
            $error_formas = $ignored->getMessage();
        }

        // ── 4. Bancos (opcional — tabla puede no existir) ─────────────────────────
        $error_bancos = null;
        try {
            $stB = $db->prepare(
                "SELECT id, nombre_banco
                 FROM empresa_bancos
                 WHERE id_empresa = ? AND eliminado = false AND estado = true
                 ORDER BY nombre_banco ASC"
            );
            $stB->execute([$idEmpresa]);
            $bancos = $stB->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $ignored) {
            $error_bancos = $ignored->getMessage();
        }

        echo json_encode([
            'ok'   => true,
            'data' => [
                'puntos'       => $puntos,
                'conceptos'    => $conceptos,
                'formas_cobro' => $formas,
                'bancos'       => $bancos,
                'debug_errors' => [
                    'conceptos' => $error_conceptos ?? null,
                    'formas' => $error_formas ?? null,
                    'bancos' => $error_bancos ?? null
                ]
            ]
        ]);
        exit;
    }

    /**
     * Registra un cobro (ingreso) vinculado a una factura de venta.
     * Patrón equivalente a LiquidacionCompraController::registrarEgresoAjax().
     */
    public function registrarCobroRapidoAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true);

        if (!$data || empty($data['id_factura'])) {
            echo json_encode(['ok' => false, 'mensaje' => 'Datos inválidos o ID de factura faltante.']);
            exit;
        }

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $db = \App\core\Database::getConnection();

            // Obtener factura con nombre del cliente
            $stFact = $db->prepare(
                "SELECT v.*, c.nombre AS cliente_nombre
                 FROM ventas_cabecera v
                 INNER JOIN clientes c ON c.id = v.id_cliente
                 WHERE v.id = ? AND v.id_empresa = ? AND v.eliminado = false"
            );
            $stFact->execute([(int)$data['id_factura'], $idEmpresa]);
            $factura = $stFact->fetch(\PDO::FETCH_ASSOC);

            if (!$factura) {
                throw new \Exception('Factura no encontrada.');
            }

            // Obtener punto de emisión (con fallback para tabla renombrada)
            $punto = null;
            try {
                $stPunto = $db->prepare(
                    "SELECT p.id,
                            e.codigo       AS establecimiento,
                            p.codigo_punto AS punto,
                            p.id_establecimiento
                     FROM empresa_punto_emision p
                     JOIN empresa_establecimiento e ON e.id = p.id_establecimiento
                     WHERE p.id = ? AND p.id_empresa = ? AND p.eliminado = false"
                );
                $stPunto->execute([(int)$data['id_punto_emision'], $idEmpresa]);
                $punto = $stPunto->fetch(\PDO::FETCH_ASSOC);
            } catch (\Throwable $ignored) {}

            if (!$punto) {
                // Fallback: empresa_puntos_emision (tabla renombrada)
                $stPunto2 = $db->prepare(
                    "SELECT id, establecimiento, punto, id_establecimiento
                     FROM empresa_puntos_emision
                     WHERE id = ? AND id_empresa = ? AND eliminado = false"
                );
                $stPunto2->execute([(int)$data['id_punto_emision'], $idEmpresa]);
                $punto = $stPunto2->fetch(\PDO::FETCH_ASSOC);
            }

            if (!$punto) {
                throw new \Exception('Punto de emisión no válido.');
            }

            // Obtener siguiente secuencial
            $secuencialService = new \App\Services\SecuencialService();
            $secRes = $secuencialService->obtenerSiguienteSecuencial((int)$data['id_punto_emision'], 'Ingresos');

            // Calcular saldo anterior
            $stSaldo = $db->prepare(
                "SELECT COALESCE(SUM(id2.monto_cobrado), 0)
                 FROM ingresos_detalle id2
                 INNER JOIN ingresos_cabecera ic2 ON id2.id_ingreso = ic2.id
                 WHERE id2.tipo_documento = 'FACTURA'
                   AND id2.id_referencia_documento = ?
                   AND ic2.estado != 'anulado'
                   AND ic2.eliminado = false"
            );
            $stSaldo->execute([(int)$data['id_factura']]);
            $totalCobrado  = (float) $stSaldo->fetchColumn();
            $saldoAnterior = round((float)$factura['importe_total'] - $totalCobrado, 2);
            $montoCobrar   = round((float)$data['monto_cobrar'], 2);

            $numDoc = $factura['establecimiento'] . '-'
                    . $factura['punto_emision']   . '-'
                    . $factura['secuencial'];

            $payload = [
                'id_empresa'          => $idEmpresa,
                'id_establecimiento'  => (int)($punto['id_establecimiento'] ?? 0),
                'id_punto_emision'    => (int)$punto['id'],
                'id_cliente'          => (int)$factura['id_cliente'],
                'id_usuario'          => $idUsuario,
                'fecha_emision'       => $data['fecha_emision'],
                'establecimiento'     => $punto['establecimiento'],
                'punto_emision'       => $punto['punto'],
                'secuencial'          => $secRes['formateado'],
                'numero_ingreso'      => str_pad((string)($punto['establecimiento'] ?? '001'), 3, '0', STR_PAD_LEFT)
                                         . '-' . str_pad((string)($punto['punto'] ?? '001'), 3, '0', STR_PAD_LEFT)
                                         . '-' . $secRes['formateado'],
                'tipo_ingreso'        => 'FACTURA_VENTA',
                'id_ingreso_concepto' => !empty($data['id_ingreso_concepto']) ? (int)$data['id_ingreso_concepto'] : null,
                'monto_total'         => $montoCobrar,
                'observaciones'       => !empty($data['observaciones'])
                                            ? $data['observaciones']
                                            : 'Cobro de factura ' . $numDoc,
                'recibo_de'           => $factura['cliente_nombre'],
                'id_recibo_cliente'   => (int)$factura['id_cliente'],
                'detalles'            => [[
                    'tipo_documento'          => 'FACTURA',
                    'id_referencia_documento' => (int)$data['id_factura'],
                    'numero_documento'        => $numDoc,
                    'descripcion'             => 'Cobro de factura ' . $numDoc,
                    'monto_documento'         => (float)$factura['importe_total'],
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

    public function prepararPagoTarjetaAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa  = (int) $_SESSION['id_empresa'];
            $idFactura  = (int) ($_POST['id_factura'] ?? 0);
            $idUsuario  = (int) $_SESSION['id_usuario'];

            if ($idFactura <= 0) {
                echo json_encode(['ok' => false, 'mensaje' => 'ID de factura inválido.']);
                exit;
            }

            $factura = $this->repository->getPorId($idFactura);
            if (!$factura || (int)$factura['id_empresa'] !== $idEmpresa) {
                echo json_encode(['ok' => false, 'mensaje' => 'Factura no encontrada.']);
                exit;
            }
            if (($factura['estado'] ?? '') !== 'autorizado') {
                echo json_encode(['ok' => false, 'mensaje' => 'Solo se pueden pagar facturas autorizadas.']);
                exit;
            }

            // Forma de cobro seleccionada (debe ser tipo TARJETA)
            $ppRepo        = new \App\repositories\PayphoneRepository();
            $idFormaCobro  = (int) ($_POST['id_forma_cobro'] ?? 0);

            $formaCobro = null;
            if ($idFormaCobro > 0) {
                $stFc = $this->db->prepare(
                    "SELECT id, nombre, tipo FROM empresa_formas_pago
                     WHERE id = ? AND id_empresa = ? AND eliminado = false AND activo = true"
                );
                $stFc->execute([$idFormaCobro, $idEmpresa]);
                $formaCobro = $stFc->fetch(\PDO::FETCH_ASSOC);
            }

            if (!$formaCobro || strtoupper((string) $formaCobro['tipo']) !== 'TARJETA') {
                echo json_encode([
                    'ok'      => false,
                    'mensaje' => 'Debes seleccionar una forma de cobro de tipo "Tarjeta" para enviar el cobro con tarjeta.',
                ]);
                exit;
            }

            $total = (float) ($factura['importe_total'] ?? 0);

            // Calcular total cobrado real desde ingresos
            $stCob = $this->db->prepare(
                "SELECT COALESCE(SUM(id2.monto_cobrado), 0)
                 FROM ingresos_detalle id2
                 INNER JOIN ingresos_cabecera ic2 ON id2.id_ingreso = ic2.id
                 WHERE id2.tipo_documento = 'FACTURA'
                   AND id2.id_referencia_documento = ?
                   AND ic2.estado != 'anulado'
                   AND ic2.eliminado = false"
            );
            $stCob->execute([$idFactura]);
            $cobrado = (float) $stCob->fetchColumn();

            // Pagos con tarjeta (Payphone) APROBADOS aún SIN ingreso vinculado
            // (los que ya generaron ingreso se cuentan vía la suma de ingresos)
            $stPP = $this->db->prepare(
                "SELECT COALESCE(SUM(monto), 0)
                 FROM payphone_transacciones
                 WHERE id_empresa    = ?
                   AND modulo        = 'factura_venta'
                   AND id_referencia = ?
                   AND estado        = 'aprobado'
                   AND id_ingreso    IS NULL
                   AND eliminado     = false"
            );
            $stPP->execute([$idEmpresa, $idFactura]);
            $pagadoTarjeta = ((float) $stPP->fetchColumn()) / 100;

            $saldo = round($total - $cobrado - $pagadoTarjeta, 2);

            if ($saldo <= 0) {
                echo json_encode(['ok' => false, 'mensaje' => 'Esta factura ya se encuentra pagada en su totalidad.']);
                exit;
            }

            // Monto a cobrar (parcial permitido). Si no llega, usar el saldo completo.
            $montoCobrar = round((float) ($_POST['monto'] ?? 0), 2);
            if ($montoCobrar <= 0) {
                $montoCobrar = $saldo;
            }
            if ($montoCobrar > $saldo + 0.001) {
                echo json_encode(['ok' => false, 'mensaje' => 'El monto a cobrar ($' . number_format($montoCobrar, 2) . ') no puede superar el saldo pendiente ($' . number_format($saldo, 2) . ').']);
                exit;
            }

            // Evitar enviar un segundo enlace mientras hay uno pendiente reciente (15 min)
            $stPend = $this->db->prepare(
                "SELECT COUNT(*)
                 FROM payphone_transacciones
                 WHERE id_empresa    = ?
                   AND modulo        = 'factura_venta'
                   AND id_referencia = ?
                   AND estado        = 'pendiente'
                   AND eliminado     = false
                   AND created_at >= (CURRENT_TIMESTAMP - INTERVAL '15 minutes')"
            );
            $stPend->execute([$idEmpresa, $idFactura]);
            if ((int) $stPend->fetchColumn() > 0) {
                echo json_encode(['ok' => false, 'mensaje' => 'Ya existe un enlace de pago pendiente enviado en los �ultimos 15 minutos. Espera a que el cliente lo complete o a que expire.']);
                exit;
            }

            $pp     = new \App\Services\PayphoneService(new \App\repositories\PayphoneRepository());
            $numero = ($factura['establecimiento'] ?? '001') . '-' . ($factura['punto_emision'] ?? '001') . '-' . str_pad((string)($factura['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT);

            // Usar correo del POST si viene, sino el de la factura
            $correoPost    = trim($_POST['correo_destino'] ?? '');
            $correoCliente = filter_var($correoPost, FILTER_VALIDATE_EMAIL)
                ? $correoPost
                : trim($factura['cliente_email'] ?? '');

            if (!filter_var($correoCliente, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['ok' => false, 'mensaje' => 'El correo ingresado no es válido.']);
                exit;
            }

            // URL pública absoluta (BASE_URL puede estar vacío en producción)
            $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $urlBaseAbs = $scheme . '://' . $host . rtrim(BASE_URL, '/');

            // Payphone usa la Response URL registrada en su panel (/payphone/retorno).
            // No se define url_exito: el cliente externo ve la página de resultado "aprobado".
            $cajita = $pp->prepararCajita($idEmpresa, [
                'monto'          => \App\Services\PayphoneService::dolaresACentavos($montoCobrar),
                'descripcion'    => 'Factura ' . $numero,
                'modulo'         => 'factura_venta',
                'id_referencia'  => $idFactura,
                'url_retorno'    => $urlBaseAbs . '/payphone/retorno',
                'url_cancelacion'=> $urlBaseAbs . '/payphone/cancelacion',
                'url_exito'      => null,
                'id_usuario'     => $idUsuario,
                'email'          => $correoCliente,
                'id_forma_cobro' => $idFormaCobro,
            ]);

            if (!$cajita['ok']) {
                echo json_encode($cajita);
                exit;
            }

            // Generar enlace público absoluto para el cliente
            $urlPago       = $urlBaseAbs . '/pago/' . $cajita['client_transaction_id'];
            $descripcion   = 'Factura ' . $numero;

            // Obtener nombre de la empresa
            $empresaModel  = new \App\models\Empresa();
            $empresaData   = $empresaModel->getPorId($idEmpresa) ?? [];
            $empresaNombre = $empresaData['nombre_comercial'] ?? $empresaData['razon_social'] ?? '';

            // Generar PDF de la factura (igual que reenviarCorreoAjax)
            $pdfString = '';
            try {
                $detalles = $this->repository->getDetalles($idFactura);
                foreach ($detalles as &$d) {
                    $d['impuestos'] = $this->repository->getImpuestosDetalle((int)$d['id']);
                }
                unset($d);
                $pagos         = $this->repository->getPagos($idFactura);
                $infoAdicional = $this->repository->getInfoAdicional($idFactura);

                $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
                if (!empty($establecimientos)) {
                    if (!empty($establecimientos[0]['logo_ruta']))    $empresaData['logo_ruta'] = $establecimientos[0]['logo_ruta'];
                    if (!empty($establecimientos[0]['direccion']))     $empresaData['direccion_establecimiento'] = $establecimientos[0]['direccion'];
                    try {
                        $estRepo   = new \App\repositories\modulos\EmpresaRepository();
                        $estConfig = $estRepo->getEstablecimientoConfig((int) $establecimientos[0]['id']);
                        if ($estConfig) {
                            $estConfig['direccion_matriz']          = $empresaData['direccion'] ?? '';
                            $estConfig['direccion_establecimiento'] = $establecimientos[0]['direccion'] ?? '';
                            if (!empty($establecimientos[0]['logo_ruta'])) $estConfig['logo_ruta'] = $establecimientos[0]['logo_ruta'];
                            $empresaData = array_merge($empresaData, $estConfig);
                        }
                    } catch (\Throwable $e) {}
                }

                $renderer     = new \App\Services\PlantillasPdfRendererService();
                $plantillaPdf = $renderer->getPlantillaActiva($idEmpresa, 'factura_venta');
                if ($plantillaPdf) {
                    $pdfString = $renderer->generar($plantillaPdf, $factura, $detalles, $pagos, $infoAdicional, $empresaData, 'S');
                } else {
                    $pdfService = new \App\Services\modulos\FacturaVentaPdfService();
                    $pdfString  = $pdfService->generar($factura, $detalles, $pagos, $infoAdicional, $empresaData, 'S');
                }
            } catch (\Throwable $e) {
                // Si falla el PDF seguimos, solo enviamos sin adjunto
                error_log('[PagoTarjeta] Error generando PDF: ' . $e->getMessage());
            }

            // Enviar correo usando la misma config que el envío de comprobantes
            $emailSvc = new \App\Services\EnvioDocumentosSRIService();
            $enviado  = $emailSvc->enviarEnlacePagoTarjeta(
                $idEmpresa,
                $correoCliente,
                $factura['cliente_nombre'] ?? '',
                $empresaNombre,
                $montoCobrar,
                $descripcion,
                $urlPago,
                $pdfString
            );

            if (!$enviado) {
                echo json_encode(['ok' => false, 'mensaje' => 'No se pudo enviar el correo. Verifica la configuración de correo de la empresa.']);
                exit;
            }

            echo json_encode([
                'ok'      => true,
                'mensaje' => 'Enlace de pago enviado al correo ' . $correoCliente,
                'correo'  => $correoCliente,
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Reversa un pago con tarjeta (Payphone) y anula automáticamente el ingreso vinculado.
     * Solo aplica a transacciones de tipo tarjeta en estado 'aprobado'.
     */
    public function anularPagoTarjetaAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];
            $ctid      = trim($_POST['client_transaction_id'] ?? '');

            if ($ctid === '') {
                echo json_encode(['ok' => false, 'mensaje' => 'Transacción no especificada.']);
                exit;
            }

            $ppRepo = new \App\repositories\PayphoneRepository();
            $trans  = $ppRepo->getTransaccionByClientId($ctid);

            if (!$trans || (int) $trans['id_empresa'] !== $idEmpresa || ($trans['modulo'] ?? '') !== 'factura_venta') {
                echo json_encode(['ok' => false, 'mensaje' => 'Pago con tarjeta no encontrado.']);
                exit;
            }
            if (($trans['estado'] ?? '') !== 'aprobado') {
                echo json_encode(['ok' => false, 'mensaje' => 'Solo se puede reversar un pago en estado Aprobado.']);
                exit;
            }

            // 1) Solicitar el reverso a Payphone
            $pp  = new \App\Services\PayphoneService($ppRepo);
            $rev = $pp->reversarPago($ctid, $idUsuario);

            if (!$rev['ok']) {
                echo json_encode(['ok' => false, 'mensaje' => 'No se pudo reversar en Payphone: ' . ($rev['mensaje'] ?? 'error desconocido.')]);
                exit;
            }

            // 2) Reverso aprobado → anular automáticamente el ingreso vinculado
            $idIngreso = (int) ($trans['id_ingreso'] ?? 0);
            if ($idIngreso > 0) {
                try {
                    $ingresoService = new \App\Services\modulos\IngresoService(
                        new \App\repositories\modulos\IngresoRepository(),
                        new \App\Rules\modulos\IngresoRules(),
                        new \App\Services\LogSistemaService()
                    );
                    $ingresoService->anular($idIngreso, $idEmpresa, $idUsuario);
                } catch (\Throwable $e) {
                    // El reverso ya se hizo; informar pero no revertir el estado Payphone
                    echo json_encode([
                        'ok'      => true,
                        'aviso'   => 'El pago fue reversado, pero no se pudo anular el ingreso automáticamente: ' . $e->getMessage(),
                        'mensaje' => 'Pago reversado. Revisa la anulación del ingreso manualmente.',
                    ]);
                    exit;
                }
            }

            echo json_encode([
                'ok'      => true,
                'mensaje' => 'Pago con tarjeta reversado y cobro anulado. La factura queda pendiente de pago.',
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Cancela una solicitud de pago con tarjeta (Payphone) que está PENDIENTE.
     * Se usa cuando se envió el enlace al cliente y se desea anular esa solicitud.
     */
    public function cancelarPagoTarjetaAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];
            $ctid      = trim($_POST['client_transaction_id'] ?? '');

            if ($ctid === '') {
                echo json_encode(['ok' => false, 'mensaje' => 'Transacción no especificada.']);
                exit;
            }

            $ppRepo = new \App\repositories\PayphoneRepository();
            $trans  = $ppRepo->getTransaccionByClientId($ctid);

            if (!$trans || (int) $trans['id_empresa'] !== $idEmpresa || ($trans['modulo'] ?? '') !== 'factura_venta') {
                echo json_encode(['ok' => false, 'mensaje' => 'Solicitud de pago no encontrada.']);
                exit;
            }

            $pp  = new \App\Services\PayphoneService($ppRepo);
            $res = $pp->cancelarPagoPendiente($ctid, $idUsuario);

            if (!$res['ok']) {
                echo json_encode(['ok' => false, 'mensaje' => $res['mensaje'] ?? 'No se pudo cancelar la solicitud.']);
                exit;
            }

            echo json_encode(['ok' => true, 'mensaje' => 'Solicitud de pago cancelada.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function reenviarCorreoAjax(): void
    {
        ob_start();
        $this->requireLeer();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) {
            echo json_encode(['ok' => false, 'mensaje' => 'ID requerido.']);
            exit;
        }

        try {
            $factura = $this->repository->getPorId($id);
            if (!$factura || (int)($factura['id_empresa'] ?? 0) !== $idEmpresa) {
                echo json_encode(['ok' => false, 'mensaje' => 'Factura no encontrada.']);
                exit;
            }

            if (($factura['estado'] ?? '') !== 'autorizado') {
                echo json_encode(['ok' => false, 'mensaje' => 'La factura debe estar autorizada para enviar el correo.']);
                exit;
            }

            $detalles = $this->repository->getDetalles($id);
            foreach ($detalles as &$d) {
                $d['impuestos'] = $this->repository->getImpuestosDetalle((int)$d['id']);
            }
            unset($d);

            $pagos    = $this->repository->getPagos($id);
            $infoAdicional = $this->repository->getInfoAdicional($id);

            $empresaModel = new \App\models\Empresa();
            $empresa      = $empresaModel->getPorId($idEmpresa) ?? [];

            $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
            if (!empty($establecimientos)) {
                // Asignar campos críticos del establecimiento antes del try/catch
                if (!empty($establecimientos[0]['logo_ruta'])) {
                    $empresa['logo_ruta'] = $establecimientos[0]['logo_ruta'];
                }
                if (!empty($establecimientos[0]['direccion'])) {
                    $empresa['direccion_establecimiento'] = $establecimientos[0]['direccion'];
                }
                if (!empty($establecimientos[0]['leyenda_pdf_titulo'])) {
                    $empresa['leyenda_pdf_titulo'] = $establecimientos[0]['leyenda_pdf_titulo'];
                }
                if (!empty($establecimientos[0]['leyenda_pdf_mensaje'])) {
                    $empresa['leyenda_pdf_mensaje'] = $establecimientos[0]['leyenda_pdf_mensaje'];
                }
                try {
                    $estRepo   = new \App\repositories\modulos\EmpresaRepository();
                    $estConfig = $estRepo->getEstablecimientoConfig((int) $establecimientos[0]['id']);
                    if ($estConfig) {
                        $estConfig['direccion_matriz'] = $empresa['direccion'] ?? '';
                        $estConfig['direccion_establecimiento'] = $establecimientos[0]['direccion'] ?? '';

                        if (!empty($establecimientos[0]['logo_ruta'])) {
                            $estConfig['logo_ruta'] = $establecimientos[0]['logo_ruta'];
                        }

                        if (!empty($establecimientos[0]['leyenda_pdf_titulo'])) {
                            $estConfig['leyenda_pdf_titulo'] = $establecimientos[0]['leyenda_pdf_titulo'];
                        }
                        if (!empty($establecimientos[0]['leyenda_pdf_mensaje'])) {
                            $estConfig['leyenda_pdf_mensaje'] = $establecimientos[0]['leyenda_pdf_mensaje'];
                        }

                        $empresa = array_merge($empresa, $estConfig);
                    }
                } catch (\Throwable $e) {}
            }

            $renderer  = new \App\Services\PlantillasPdfRendererService();
            $plantillaPdf = $renderer->getPlantillaActiva($idEmpresa, 'factura_venta');

            if ($plantillaPdf) {
                $pdfString = $renderer->generar($plantillaPdf, $factura, $detalles, $pagos, $infoAdicional, $empresa, 'S');
            } else {
                $pdfService = new \App\Services\modulos\FacturaVentaPdfService();
                $pdfString = $pdfService->generar($factura, $detalles, $pagos, $infoAdicional, $empresa, 'S');
            }

            $xmlString = $factura['detalle_xml'] ?? '';
            $numAut = $factura['numero_autorizacion'] ?? '';

            if (empty($xmlString)) {
                // Generar XML dinámicamente como fallback para facturas antiguas
                $dirEstablecimiento = null;
                if (!empty($factura['id_establecimiento'])) {
                    try {
                        $estRepo = new \App\repositories\modulos\EmpresaRepository();
                        foreach ($estRepo->getEstablecimientos($idEmpresa) as $est) {
                            if ((int)$est['id'] === (int)$factura['id_establecimiento']) {
                                $dirEstablecimiento = $est['direccion'] ?? null;
                                break;
                            }
                        }
                    } catch (\Throwable $e) {}
                }
                $xmlService = new \App\Services\Xml\XmlFacturaVentaService();
                $xmlString  = $xmlService->generar($factura, $detalles, $pagos, $infoAdicional, $empresa, $dirEstablecimiento);

                // Guardar para futuros usos si es posible
                try {
                    $this->repository->updateDetalleXml($id, $xmlString);
                } catch (\Throwable $e) {}
            }

            $correosDestino = trim($_POST['correos'] ?? '');
            
            $emailSvc = new \App\Services\EnvioDocumentosSRIService();
            $enviado = $emailSvc->enviarSiAplica($idEmpresa, 'factura_venta', $factura, $xmlString, $pdfString, $numAut, true, $correosDestino);

            // Limpiar cualquier output perdido antes de mandar JSON
            ob_end_clean();

            if ($enviado) {
                $db = \App\core\Database::getConnection();
                $db->prepare("UPDATE ventas_cabecera SET estado_correo = 'enviado' WHERE id = ?")->execute([$id]);
                echo json_encode(['ok' => true, 'mensaje' => 'Correo enviado correctamente.']);
            } else {
                echo json_encode(['ok' => false, 'mensaje' => 'No se pudo enviar el correo. Verifica la configuración o el correo del destinatario.']);
            }

        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => 'Error al enviar correo: ' . $e->getMessage()]);
        }
        exit;
    }

    private function renderFilaHtml(array $r): string
    {
        $rowData      = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
        $numero       = htmlspecialchars(($r['establecimiento'] ?? '') . '-' . ($r['punto_emision'] ?? '') . '-' . ($r['secuencial'] ?? ''));
        $fecha        = !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '-';
        $estado       = $r['estado'] ?? 'borrador';
        $estadoClass  = match ($estado) {
            'aprobado', 'autorizado' => 'bg-success bg-opacity-10 text-success border-success',
            'anulado'                => 'bg-danger bg-opacity-10 text-danger border-danger',
            'borrador'               => 'bg-secondary bg-opacity-10 text-secondary border-secondary',
            default                  => 'bg-primary bg-opacity-10 text-primary border-primary',
        };
        $estadoBadge  = '<span class="badge ' . $estadoClass . ' border border-opacity-25">' . ucfirst($estado) . '</span>';
        $estadoCorreo = $r['estado_correo'] ?? 'pendiente';
        $correoClass  = $estadoCorreo === 'enviado'
            ? 'bg-success bg-opacity-10 text-success border-success'
            : 'bg-warning bg-opacity-10 text-warning border-warning';
        $correoBadge  = '<span class="badge ' . $correoClass . ' border border-opacity-25">' . ucfirst($estadoCorreo) . '</span>';
        $ivaCalc      = max(0, (float)($r['importe_total'] ?? 0) - (float)($r['total_sin_impuestos'] ?? 0) + (float)($r['total_descuento'] ?? 0) - (float)($r['total_ice'] ?? 0) - (float)($r['propina'] ?? 0));

        // Calcular estado de pago
        $importeTotal = (float)($r['importe_total'] ?? 0);
        $cobrado      = (float)($r['total_cobrado'] ?? 0);
        $nc           = (float)($r['total_nc'] ?? 0);
        $retencion    = (float)($r['total_retencion'] ?? 0);
        $saldo        = max(0, $importeTotal - $cobrado - $nc - $retencion);

        if ($saldo <= 0.01) {
            $estadoPagoBadge = '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Pagada</span>';
        } elseif (($cobrado + $nc + $retencion) > 0) {
            $estadoPagoBadge = '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">Abonada</span>';
        } else {
            $estadoPagoBadge = '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Pendiente</span>';
        }

        return '<tr class="factura-row" role="button" tabindex="0" data-row=\'' . $rowData . '\' onclick="abrirModalFacturaVer(this)">
                <td class="ps-3" data-col="numero"><code class="text-secondary">' . $numero . '</code></td>
                <td data-col="fecha_emision">' . $fecha . '</td>
                <td class="fw-medium text-truncate" data-col="cliente_nombre" style="max-width:200px">' . htmlspecialchars($r['cliente_nombre'] ?? '') . '</td>
                <td data-col="cliente_ruc"><small class="text-muted">' . htmlspecialchars($r['cliente_ruc'] ?? '') . '</small></td>
                <td class="text-end" data-col="total_sin_impuestos">$' . number_format((float)($r['total_sin_impuestos'] ?? 0), 2) . '</td>
                <td class="text-end text-danger" data-col="total_descuento">$' . number_format((float)($r['total_descuento'] ?? 0), 2) . '</td>
                <td class="text-end" data-col="iva">$' . number_format($ivaCalc, 2) . '</td>
                <td class="text-end" data-col="total_ice">$' . number_format((float)($r['total_ice'] ?? 0), 2) . '</td>
                <td class="text-end" data-col="propina">$' . number_format((float)($r['propina'] ?? 0), 2) . '</td>
                <td class="text-end fw-bold" data-col="importe_total">$' . number_format((float)($r['importe_total'] ?? 0), 2) . '</td>
                <td data-col="vendedor_nombre"><span class="text-muted">' . htmlspecialchars($r['vendedor_nombre'] ?? '') . '</span></td>
                <td data-col="observaciones" class="text-truncate" style="max-width:180px">' . htmlspecialchars($r['observaciones'] ?? '') . '</td>
                <td data-col="usuario_nombre">' . htmlspecialchars($r['usuario_nombre'] ?? '') . '</td>
                <td class="text-center" data-col="estado_correo">' . $correoBadge . '</td>
                <td class="text-center" data-col="estado_pago">' . $estadoPagoBadge . '</td>
                <td class="text-center pe-3" data-col="estado">' . $estadoBadge . '</td>
              </tr>';
    }
}
