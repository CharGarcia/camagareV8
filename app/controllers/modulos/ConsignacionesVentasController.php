<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\ConsignacionVentaRepository;
use App\Rules\modulos\ConsignacionVentaRules;
use App\Services\LogSistemaService;
use App\Services\modulos\ConsignacionVentaService;
use Exception;

class ConsignacionesVentasController extends BaseModuloController
{
    private ConsignacionVentaService $service;
    private const RUTA_MODULO = 'modulos/consignaciones-ventas';

    public function __construct()
    {
        parent::__construct();
        try {
            $db = \App\Core\Database::getConnection();
            $db->exec("ALTER TABLE consignaciones_ventas_detalles ADD COLUMN IF NOT EXISTS nup VARCHAR(100)");
            $db->exec("ALTER TABLE consignaciones_ventas_detalles ADD COLUMN IF NOT EXISTS lote VARCHAR(100)");
            $db->exec("ALTER TABLE consignaciones_ventas_detalles ADD COLUMN IF NOT EXISTS fecha_caducidad DATE");
            $db->exec("ALTER TABLE consignaciones_ventas_detalles ADD COLUMN IF NOT EXISTS id_pedido_detalle INTEGER NULL");
        } catch (\Throwable $e) {}

        $repository = new ConsignacionVentaRepository();
        $rules = new ConsignacionVentaRules();
        $logService = new LogSistemaService();
        $this->service = new ConsignacionVentaService($repository, $rules, $logService);
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

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? $_GET['buscar'] ?? $_POST['buscar'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_emision');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'desc'));
        $perPage  = 20;

        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $result['rows'];
        $total = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        // Formato fechas
        foreach ($rows as &$r) {
            if (!empty($r['fecha_emision'])) $r['fecha_emision'] = date('d-m-Y', strtotime($r['fecha_emision']));
        }
        unset($r);

        // Cargar config empresa
        $empresaModel = new \App\models\Empresa();
        $empresaData  = $empresaModel->getPorId($idEmpresa);
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        if (!empty($establecimientos)) {
            try {
                $estRepo   = new \App\repositories\modulos\EmpresaRepository();
                $estConfig = $estRepo->getEstablecimientoConfig((int) $establecimientos[0]['id']);
                if ($estConfig) {
                    $empresaData = array_merge($empresaData ?? [], $estConfig);
                }
            } catch (\Throwable $e) {}
        }

        $bodegaRepo = new \App\repositories\modulos\BodegaRepository();
        $bodegas = $bodegaRepo->getBodegasPermitidas((int)$_SESSION['id_usuario'], $idEmpresa, (int)$_SESSION['nivel']);

        $vendedorRepo = new \App\repositories\modulos\VendedorRepository();
        $vendedores = $vendedorRepo->getVendedoresActivos($idEmpresa, $idUsuarioFiltro);

        $responsableRepo = new \App\repositories\modulos\ResponsableTrasladoRepository();
        $responsables = $responsableRepo->listarPorEmpresa($idEmpresa);

        $this->viewWithLayout('layouts.main', 'modulos.consignaciones_ventas.index', [
            'titulo'         => 'Consignaciones en Ventas',
            'perm'           => $perm,
            'rutaModulo'     => self::RUTA_MODULO,
            'bodegas'        => $bodegas,
            'vendedores'     => $vendedores,
            'responsables'   => $responsables,
            'empresa'        => $empresaData,
            'rows'           => $rows,
            'total'          => $total,
            'page'       => $page,
            'totalPages' => $totalPages,
            'perPage'    => $perPage,
            'buscar'     => $buscar,
            'ordenCol'   => $ordenCol,
            'ordenDir'   => $ordenDir,
            'vistaConfig'=> $prefsVista,
            'fullWidth'  => true,
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $buscar    = trim($_GET['b'] ?? $_GET['q'] ?? $_POST['b'] ?? $_POST['q'] ?? '');
        $page      = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_emision');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'desc'));
        $perPage   = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $result['rows'];
        $total = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to   = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-box-seam fs-3 d-block mb-2"></i>No se encontraron consignaciones.</td></tr>';
        } else {
            foreach ($rows as $r) {
                if (!empty($r['fecha_emision'])) $r['fecha_emision'] = date('d-m-Y', strtotime($r['fecha_emision']));

                $dataJson = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                
                $statusBadge = '';
                if ($r['estado'] === 'Emitida') {
                    $statusBadge = '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">Emitida</span>';
                } elseif ($r['estado'] === 'Entregada') {
                    $statusBadge = '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Entregada</span>';
                } else {
                    $statusBadge = '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Anulada</span>';
                }

                echo '<tr class="consignacion-row" role="button" tabindex="0" data-row=\'' . $dataJson . '\' onclick="abrirModalConsignacionVer(this)">
                        <td class="ps-3" data-col="fecha_emision">' . htmlspecialchars($r['fecha_emision'] ?? '') . '</td>
                        <td data-col="secuencial" class="fw-bold text-primary">' . htmlspecialchars(($r['serie'] ?? '') . '-' . ($r['secuencial'] ?? '')) . '</td>
                        <td data-col="cliente" class="text-truncate" style="max-width:250px">' . htmlspecialchars($r['cliente_nombre'] ?? '') . '</td>
                        <td data-col="vendedor" class="text-truncate" style="max-width:150px">' . htmlspecialchars($r['vendedor_nombre'] ?? '—') . '</td>
                        <td data-col="observaciones" class="text-truncate" style="max-width:200px">' . htmlspecialchars($r['observaciones'] ?? '—') . '</td>
                        <td class="text-center pe-3" data-col="estado">' . $statusBadge . '</td>
                      </tr>';
            }
        }
        $rowsHtml = ob_get_clean();

        ob_start();
        $prevDisabled = ($page <= 1) ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        echo '<div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary border-end-0 rounded-end-0" ' . $prevDisabled . ' onclick="cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary rounded-start-0" ' . $nextDisabled . ' onclick="cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>
              </div>';
        $paginationHtml = ob_get_clean();

        echo json_encode([
            'ok'        => true,
            'rows'      => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'      => "$from-$to/$total",
            'total'     => $total,
            'pdf_url'   => BASE_URL . '/' . self::RUTA_MODULO . '/export-pdf?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir",
            'excel_url' => BASE_URL . '/' . self::RUTA_MODULO . '/export-excel?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir"
        ]);
        exit;
    }

    public function store(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        try {
            $db = \App\Core\Database::getConnection();
            $db->exec("ALTER TABLE consignaciones_ventas_detalles ADD COLUMN IF NOT EXISTS nup VARCHAR(100)");
            $db->exec("ALTER TABLE consignaciones_ventas_detalles ADD COLUMN IF NOT EXISTS lote VARCHAR(100)");
            $db->exec("ALTER TABLE consignaciones_ventas_detalles ADD COLUMN IF NOT EXISTS fecha_caducidad DATE");
            $db->exec("ALTER TABLE consignaciones_ventas_detalles ADD COLUMN IF NOT EXISTS id_pedido_detalle INTEGER NULL");
        } catch (\Throwable $e) {}

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                throw new Exception("Datos no recibidos.");
            }

            $input['id_empresa'] = (int) $_SESSION['id_empresa'];
            $input['id_usuario'] = (int) $_SESSION['id_usuario'];

            // Cargar configuración de la empresa (para tipo_ambiente, etc)
            $empresaModel = new \App\models\Empresa();
            $empresaData  = $empresaModel->getPorId($input['id_empresa']) ?? [];
            
            // Cargar config específica de establecimiento si existe
            $establecimientos = $empresaModel->getEstablecimientos($input['id_empresa']);
            if (!empty($establecimientos)) {
                try {
                    $estRepo   = new \App\repositories\modulos\EmpresaRepository();
                    $estConfig = $estRepo->getEstablecimientoConfig((int) $establecimientos[0]['id']);
                    if ($estConfig) {
                        $empresaData = array_merge($empresaData, $estConfig);
                    }
                } catch (\Throwable $e) {}
            }
            $input['empresa_config'] = $empresaData;

            if (!empty($input['id'])) {
                // Actualizar
                $this->requireActualizar();
                $this->service->actualizar((int) $input['id'], (int) $input['id_empresa'], $input);
                echo json_encode(['ok' => true, 'msg' => 'Consignación de Venta actualizada correctamente.']);
            } else {
                // Crear
                $id = $this->service->crear($input);
                echo json_encode(['ok' => true, 'msg' => 'Consignación de Venta registrada correctamente.', 'id' => $id]);
            }
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function eliminar(): void
    {
        $this->requireEliminar(); // usando el permiso de eliminar
        header('Content-Type: application/json');

        try {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception("ID no válido.");

            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            $this->service->eliminar($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Consignación eliminada correctamente y el inventario ha sido devuelto.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function getEstablecimientosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $empresaModel = new \App\models\Empresa();
        $establecimientos = $empresaModel->getEstablecimientos((int)$_SESSION['id_empresa']);

        echo json_encode(['ok' => true, 'data' => $establecimientos]);
        exit;
    }

    public function getPuntosEmisionAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEst = (int) ($_GET['id_establecimiento'] ?? 0);
        $empresaModel = new \App\models\Empresa();
        $puntos = $empresaModel->getPuntosEmision($idEst);

        // Filtrar solo los puntos de emisión que tienen el secuencial configurado
        $repoSecuencial = new \App\repositories\SecuencialRepository();
        $puntosFiltrados = [];
        foreach ($puntos as $p) {
            $config = $repoSecuencial->getConfigSecuencial((int)$p['id'], 'Consignaciones ventas');
            if (!empty($config['id'])) {
                $puntosFiltrados[] = $p;
            }
        }

        echo json_encode(['ok' => true, 'data' => array_values($puntosFiltrados)]);
        exit;
    }

    public function getSecuencialAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idPunto = (int) ($_GET['id_punto_emision'] ?? 0);
        
        // Verificar si existe la configuración del secuencial
        $repo = new \App\repositories\SecuencialRepository();
        $config = $repo->getConfigSecuencial($idPunto, 'Consignaciones ventas');
        
        if (empty($config['id'])) {
            echo json_encode([
                'ok' => false, 
                'msg' => 'No hay configuración de secuencial para "Consignaciones ventas" en este punto de emisión. Por favor configurar en el módulo Empresa / Secuenciales.'
            ]);
            exit;
        }

        $secuencialService = new \App\Services\SecuencialService();
        $res = $secuencialService->obtenerSiguienteSecuencial($idPunto, 'Consignaciones ventas');

        echo json_encode(array_merge(['ok' => true], $res));
        exit;
    }

    public function getDetalleAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $id = (int) ($_GET['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];

            $data = $this->service->getDetalleCompleto($id, $idEmpresa);
            if (!$data) throw new Exception("Consignación no encontrada.");

            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    public function guardarResponsableAjax() {
        $this->requireCrear();
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];
            $nombre = trim($_POST['nombre'] ?? '');
            $identificacion = trim($_POST['identificacion'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $email = trim($_POST['email'] ?? '');

            if (empty($nombre)) {
                throw new \Exception('El nombre es obligatorio');
            }

            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \Exception('El formato del correo electrónico no es válido');
            }

            $db = \App\core\Database::getConnection();
            $db->beginTransaction();

            $sql = "INSERT INTO responsables_traslado (id_empresa, nombre, identificacion, telefono, email, estado, created_by, updated_by, created_at, updated_at, eliminado)
                    VALUES (:id_empresa, :nombre, :identificacion, :telefono, :email, 'activo', :id_usuario, :id_usuario, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, false)
                    RETURNING id, nombre, email";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':id_empresa' => $idEmpresa,
                ':nombre' => $nombre,
                ':identificacion' => $identificacion,
                ':telefono' => $telefono,
                ':email' => $email,
                ':id_usuario' => $idUsuario
            ]);

            $newRow = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Audit log
            try {
                $sqlLog = "INSERT INTO log_sistema (id_usuario, id_empresa, accion, tabla_afectada, datos_nuevos)
                           VALUES (:id_usuario, :id_empresa, 'CREAR', 'responsables_traslado', :datos_nuevos)";
                $stmtLog = $db->prepare($sqlLog);
                $stmtLog->execute([
                    ':id_usuario' => $idUsuario,
                    ':id_empresa' => $idEmpresa,
                    ':datos_nuevos' => json_encode($newRow)
                ]);
            } catch (\Exception $e) {
                // Ignore log error
            }

            $db->commit();

            echo json_encode([
                'status' => true,
                'message' => 'Responsable creado con éxito',
                'data' => $newRow
            ]);
        } catch (\Exception $e) {
            if (isset($db)) $db->rollBack();
            echo json_encode([
                'status' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function getProductosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = 'tipo:01 ' . trim($_GET['q'] ?? '');
        $idBodega = (int) ($_GET['id_bodega'] ?? 0);
        $idConsignacion = (int) ($_GET['id_consignacion'] ?? 0);

        $repo = new \App\repositories\modulos\ProductoRepository();
        $result = $repo->getListado($idEmpresa, $buscar, 1, 15, 'nombre', 'ASC', null, null);

        $repoInv = new \App\repositories\modulos\InventarioRepository();

        $rows = array_map(function ($p) use ($repo, $repoInv, $idEmpresa, $idBodega, $idConsignacion) {
            $p['precios_lista'] = $repo->getPrecios((int)$p['id'], $idEmpresa);
            $p['variantes']     = $repo->getVariantes((int)$p['id'], $idEmpresa);
            
            $stock = 0.0;
            if ($idBodega > 0 && ($p['inventariable'] == true || $p['inventariable'] == 'true' || $p['inventariable'] == 1)) {
                $stock = $repoInv->getStockActual(
                    (int)$p['id'],
                    $idBodega,
                    $idEmpresa,
                    $idConsignacion > 0 ? $idConsignacion : null,
                    $idConsignacion > 0 ? 'consignacion_venta' : null
                );
            }
            $p['stock_actual'] = $stock;
            
            return $p;
        }, $result['rows']);

        echo json_encode(['ok' => true, 'data' => $rows]);
        exit;
    }

    public function getLotesDisponiblesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $idProducto = (int) ($_GET['id_producto'] ?? 0);
        $idBodega   = (int) ($_GET['id_bodega'] ?? 0);
        $idVenta    = (int) ($_GET['id_consignacion'] ?? 0);

        if (!$idProducto || !$idBodega) {
            echo json_encode(['ok' => false, 'mensaje' => 'Faltan parámetros']);
            exit;
        }

        $repoInv = new \App\repositories\modulos\InventarioRepository();
        $excludeId   = ($idVenta > 0 ? $idVenta : null);
        $excludeTipo = ($idVenta > 0 ? 'consignacion_venta' : null);
        
        $lotes = $repoInv->getLotesDisponibles($idProducto, $idBodega, $idEmpresa, $excludeId, $excludeTipo);
        $stockTotal = $repoInv->getStockActual($idProducto, $idBodega, $idEmpresa, $excludeId, $excludeTipo);

        echo json_encode([
            'ok' => true, 
            'data' => $lotes,
            'stock_total' => $stockTotal
        ]);
        exit;
    }

    public function getPedidosPendientesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $buscar = trim($_GET['q'] ?? '');

            $db = \App\Core\Database::getConnection();
            $params = [':id_empresa' => $idEmpresa];
            $where = "WHERE p.id_empresa = :id_empresa AND p.estado = 'Pendiente' AND p.eliminado = false";

            if ($buscar !== '') {
                $where .= " AND ((p.establecimiento || '-' || p.punto_emision || '-' || p.secuencial) ILIKE :buscar OR p.secuencial ILIKE :buscar OR c.nombre ILIKE :buscar)";
                $params[':buscar'] = '%' . $buscar . '%';
            }

            $sql = "SELECT p.id, 
                           (p.establecimiento || '-' || p.punto_emision || '-' || p.secuencial) AS numero_pedido, 
                           p.fecha_pedido, p.id_cliente, c.id_vendedor, 
                           c.nombre as cliente_nombre, c.identificacion as cliente_identificacion
                    FROM pedidos_cabecera p
                    JOIN clientes c ON p.id_cliente = c.id
                    $where
                    ORDER BY p.fecha_pedido DESC, p.id DESC
                    LIMIT 20";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            echo json_encode(['ok' => true, 'data' => $rows]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function cargarPedidoDetalleAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $id = (int) ($_GET['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];

            $db = \App\Core\Database::getConnection();
            
            $sql = "SELECT p.*, c.nombre as cliente_nombre, c.identificacion as cliente_identificacion, c.id_vendedor, c.direccion as cliente_direccion
                    FROM pedidos_cabecera p
                    JOIN clientes c ON p.id_cliente = c.id
                    WHERE p.id = :id AND p.id_empresa = :id_empresa AND p.eliminado = false";
            $stmt = $db->prepare($sql);
            $stmt->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
            $cabecera = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$cabecera) {
                throw new \Exception("Pedido no encontrado o no pertenece a la empresa.");
            }

            $sqlD = "SELECT d.*, p.nombre as producto_nombre, p.codigo as producto_codigo, p.tipo_produccion, p.inventariable, p.precio_base as precio_base
                     FROM pedidos_detalle d
                     JOIN productos p ON d.id_producto = p.id
                     WHERE d.id_pedido = :id_pedido AND d.eliminado = false
                     ORDER BY d.id ASC";
            $stmtD = $db->prepare($sqlD);
            $stmtD->execute([':id_pedido' => $id]);
            $detalles = $stmtD->fetchAll(\PDO::FETCH_ASSOC);

            $repoProd = new \App\repositories\modulos\ProductoRepository();
            foreach ($detalles as &$d) {
                // Calculate quantity already consigned in non-deleted consignments
                $sqlCons = "SELECT COALESCE(SUM(cantidad), 0) FROM consignaciones_ventas_detalles WHERE id_pedido_detalle = :id_pd AND (eliminado = false OR eliminado IS NULL)";
                $stmtCons = $db->prepare($sqlCons);
                $stmtCons->execute([':id_pd' => $d['id']]);
                $consignado = (float) $stmtCons->fetchColumn();

                $d['cantidad_consignada'] = $consignado;
                $d['cantidad_pendiente']  = max(0.0, ((float)$d['cantidad']) - $consignado);
                $d['precios_lista']       = $repoProd->getPrecios((int)$d['id_producto'], $idEmpresa);
            }
            unset($d);

            echo json_encode([
                'ok' => true,
                'data' => [
                    'cabecera' => $cabecera,
                    'detalles' => $detalles
                ]
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}
