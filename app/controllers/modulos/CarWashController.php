<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\OrdenCarWashRepository;
use App\Rules\modulos\OrdenCarWashRules;
use App\Services\LogSistemaService;
use App\Services\modulos\OrdenCarWashService;
use Exception;

/**
 * Servicio Car-Wash.
 *
 * Registra el ingreso de vehículos al lavadero, los servicios/productos que se
 * realizan, las novedades encontradas y la próxima cita. Desde la orden se puede
 * generar un documento de venta (Factura SRI o Recibo de Venta) — ver Fase 2.
 */
class CarWashController extends BaseModuloController
{
    private OrdenCarWashService $service;
    private OrdenCarWashRepository $repository;
    private const RUTA_MODULO = 'modulos/car-wash';
    private const TIPO_SECUENCIAL = 'Ordenes car-wash';

    public function __construct()
    {
        parent::__construct();
        $this->repository = new OrdenCarWashRepository();
        $rules      = new OrdenCarWashRules();
        $logService = new LogSistemaService();
        $this->service = new OrdenCarWashService($this->repository, $rules, $logService);
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    // ─── Vista principal (tablero + historial) ────────────────────────────────

    public function index(): void
    {
        $this->requireLeer();
        $perm = $this->getPermisos();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        $buscar   = trim($_GET['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_ingreso');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'desc'));
        $perPage  = 20;

        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        // Listado estándar
        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows  = $result['rows'];
        $total = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $empresaData = $this->getEmpresaConfig($idEmpresa);

        // Series (puntos de emisión) para la numeración, formas de pago y bodegas (emisión de documento).
        $empresaRepo = new \App\repositories\modulos\EmpresaRepository();
        $puntos = $empresaRepo->getPuntosEmision($idEmpresa);
        $formasPago = $this->repository->getFormasPago();
        $bodegaRepo = new \App\repositories\modulos\BodegaRepository();
        $bodegas = $bodegaRepo->getBodegasPermitidas((int) $_SESSION['id_usuario'], $idEmpresa, (int) ($_SESSION['nivel'] ?? 1));
        $tarifasIva = $this->repository->getTarifasIva();
        $unidades   = $this->repository->getUnidadesMedida();

        $this->viewWithLayout('layouts.main', 'modulos.car_wash.index', [
            'titulo'      => 'Servicio Car-Wash',
            'perm'        => $perm,
            'rutaModulo'  => self::RUTA_MODULO,
            'empresa'     => $empresaData,
            'puntos'      => $puntos,
            'formasPago'  => $formasPago,
            'bodegas'     => $bodegas,
            'tarifasIva'  => $tarifasIva,
            'unidades'    => $unidades,
            'rows'        => $rows,
            'total'       => $total,
            'page'        => $page,
            'totalPages'  => $totalPages,
            'perPage'     => $perPage,
            'buscar'      => $buscar,
            'ordenCol'    => $ordenCol,
            'ordenDir'    => $ordenDir,
            'vistaConfig' => $prefsVista,
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $buscar   = trim($_GET['b'] ?? $_GET['q'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_ingreso');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'desc'));
        $perPage  = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows  = $result['rows'];
        $total = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to   = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-droplet-half fs-3 d-block mb-2"></i>No se encontraron órdenes.</td></tr>';
        } else {
            foreach ($rows as $r) {
                echo self::filaHistorial($r);
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
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'       => "$from-$to/$total",
            'total'      => $total,
            'pdf_url'    => BASE_URL . '/' . self::RUTA_MODULO . '/export-pdf?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir",
            'excel_url'  => BASE_URL . '/' . self::RUTA_MODULO . '/export-excel?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir",
        ]);
        exit;
    }

    /** Refresca las tarjetas del tablero operativo. */
    public function tableroAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $tablero = $this->service->getTablero($idEmpresa, $idUsuarioFiltro);
        echo json_encode(['ok' => true, 'data' => $tablero]);
        exit;
    }

    // ─── Crear / actualizar ───────────────────────────────────────────────────

    public function store(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                throw new Exception("Datos no recibidos.");
            }
            $input['id_empresa'] = (int) $_SESSION['id_empresa'];
            $input['id_usuario'] = (int) $_SESSION['id_usuario'];

            if (!empty($input['id'])) {
                $this->requireActualizar();
                $this->service->actualizar((int) $input['id'], $input['id_empresa'], $input);
                echo json_encode(['ok' => true, 'msg' => 'Orden actualizada correctamente.']);
            } else {
                $id = $this->service->crear($input);
                echo json_encode(['ok' => true, 'msg' => 'Orden registrada correctamente.', 'id' => $id]);
            }
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function cambiarEstadoAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $id     = (int) ($_POST['id'] ?? 0);
            $estado = trim($_POST['estado'] ?? '');
            if ($id <= 0) throw new Exception("ID no válido.");

            $this->service->cambiarEstado($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario'], $estado);
            echo json_encode(['ok' => true, 'msg' => 'Estado actualizado.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function eliminar(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        try {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception("ID no válido.");

            $this->service->eliminar($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            echo json_encode(['ok' => true, 'msg' => 'Orden eliminada.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function getDetalleAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $id = (int) ($_GET['id'] ?? 0);
            $data = $this->service->getDetalleCompleto($id, (int) $_SESSION['id_empresa']);
            if (!$data) throw new Exception("Orden no encontrada.");
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ─── PDF de la orden (encabezado estilo comprobante de ingresos) ──────────

    public function exportarPdfAjax(): void
    {
        $this->requireLeer();

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        if (!$id) { http_response_code(400); echo 'ID requerido'; exit; }

        try {
            $orden = $this->service->getDetalleCompleto($id, $idEmpresa);
            if (!$orden) { http_response_code(404); echo 'Orden no encontrada'; exit; }

            $empresa = $this->cargarEmpresaPdf($idEmpresa);
            (new \App\Services\modulos\OrdenCarWashPdfService())->generar($orden, $empresa, 'D');
        } catch (\Throwable $e) {
            http_response_code(500);
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
        exit;
    }

    /** Envía el PDF de la orden por correo (mismo patrón que consignaciones de venta). */
    public function enviarCorreoAjax(): void
    {
        ob_start();
        $this->requireLeer();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        if (!$id) { if (ob_get_level() > 0) ob_end_clean(); echo json_encode(['ok' => false, 'mensaje' => 'ID requerido.']); exit; }

        try {
            $orden = $this->service->getDetalleCompleto($id, $idEmpresa);
            if (!$orden) { if (ob_get_level() > 0) ob_end_clean(); echo json_encode(['ok' => false, 'mensaje' => 'Orden no encontrada.']); exit; }

            $empresa = $this->cargarEmpresaPdf($idEmpresa);
            $pdfString = (new \App\Services\modulos\OrdenCarWashPdfService())->generar($orden, $empresa, 'S');

            $numero = trim((string)($orden['numero_orden'] ?? ''));

            // Destinatarios: los del formulario o, en su defecto, el correo del cliente.
            $correosDestino = trim($_POST['correos'] ?? '');
            if ($correosDestino === '') {
                $correosDestino = (string)($orden['cliente_email'] ?? '');
            }
            if ($correosDestino === '') {
                if (ob_get_level() > 0) ob_end_clean();
                echo json_encode(['ok' => false, 'mensaje' => 'El cliente no tiene correo registrado. Ingrese uno para enviar.']);
                exit;
            }

            $clienteNombre = (string)($orden['cliente_nombre'] ?? 'Cliente');
            $empresaNombre = (string)($empresa['nombre'] ?? '');
            $asunto = 'Orden Car-Wash ' . ($numero !== '' ? $numero : '') . ($empresaNombre !== '' ? ' — ' . $empresaNombre : '');
            $cuerpo = "<div style='font-family:Arial,sans-serif;line-height:1.5;'>"
                . "<p>Estimad@ " . htmlspecialchars($clienteNombre) . ",</p>"
                . "<p>Adjunto encontrará el comprobante de la orden de servicio <strong>" . htmlspecialchars($numero) . "</strong>.</p>"
                . "<p>Saludos cordiales,<br>" . htmlspecialchars($empresaNombre) . "</p>"
                . "</div>";

            $emailSvc = new \App\Services\EnvioDocumentosSRIService();
            $enviado  = $emailSvc->enviarPdfSimple(
                $idEmpresa, $correosDestino, $clienteNombre, $asunto, $cuerpo, $pdfString,
                'Orden_CarWash_' . ($numero !== '' ? $numero : 'orden'), $empresaNombre
            );

            if (ob_get_level() > 0) ob_end_clean();
            echo json_encode($enviado
                ? ['ok' => true, 'mensaje' => 'Correo enviado correctamente.']
                : ['ok' => false, 'mensaje' => 'No se pudo enviar el correo. Verifica la configuración de correo o el destinatario.']);
        } catch (\Throwable $e) {
            if (ob_get_level() > 0) ob_end_clean();
            echo json_encode(['ok' => false, 'mensaje' => 'Error al enviar correo: ' . $e->getMessage()]);
        }
        exit;
    }

    /** Datos de la empresa (con logo del establecimiento y config) para el PDF. */
    private function cargarEmpresaPdf(int $idEmpresa): array
    {
        $empresaModel = new \App\models\Empresa();
        $empresa = $empresaModel->getPorId($idEmpresa) ?? [];
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        if (!empty($establecimientos[0]['logo_ruta'])) {
            $empresa['logo_ruta'] = $establecimientos[0]['logo_ruta'];
        }
        try {
            $estRepo   = new \App\repositories\modulos\EmpresaRepository();
            $estConfig = $estRepo->getEstablecimientoConfig((int) ($establecimientos[0]['id'] ?? 0));
            if ($estConfig) { $empresa = array_merge($empresa, $estConfig); }
        } catch (\Throwable $e) {}
        return $empresa;
    }

    // ─── Secuencial (mismas reglas que recibo de venta) ───────────────────────

    public function getSecuencialAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idPunto = (int) ($_GET['id_punto_emision'] ?? 0);
        if ($idPunto <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'Punto de emisión no válido.']);
            exit;
        }
        $secuencialService = new \App\Services\SecuencialService();
        $res = $secuencialService->obtenerSiguienteSecuencial($idPunto, self::TIPO_SECUENCIAL);
        echo json_encode(array_merge(['ok' => true], $res));
        exit;
    }

    // ─── Generar documento de venta (Factura / Recibo) ────────────────────────

    public function generarDocumentoAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        try {
            $idOrden = (int) ($_POST['id_orden'] ?? 0);
            $tipo    = strtoupper(trim($_POST['tipo'] ?? ''));
            if ($idOrden <= 0) throw new \Exception('Orden no válida.');
            if (!in_array($tipo, ['FACTURA', 'RECIBO'], true)) throw new \Exception('Tipo de documento no válido.');

            $extra = [
                'forma_pago' => trim($_POST['forma_pago'] ?? '01'),
                'id_bodega'  => (int) ($_POST['id_bodega'] ?? 0),
            ];

            $idEmpresa = (int) $_SESSION['id_empresa'];
            $res = $this->service->generarDocumento($idOrden, $idEmpresa, (int) $_SESSION['id_usuario'], $tipo, $extra, $this->getEmpresaConfig($idEmpresa));

            $etq = $tipo === 'FACTURA' ? 'Factura' : 'Recibo';
            echo json_encode([
                'ok'               => true,
                'msg'              => $etq . ' ' . $res['numero_documento'] . ' generado correctamente.',
                'tipo_documento'   => $res['tipo'],
                'id_documento'     => $res['id_documento'],
                'numero_documento' => $res['numero_documento'],
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ─── Autocompletes del modal ──────────────────────────────────────────────

    public function buscarVehiculosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $q = trim($_GET['q'] ?? $_GET['term'] ?? '');
        if (mb_strlen($q) < 2) {
            echo json_encode(['ok' => true, 'data' => []]);
            exit;
        }
        echo json_encode(['ok' => true, 'data' => $this->service->buscarVehiculos($idEmpresa, $q)]);
        exit;
    }

    public function buscarClientesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $q = trim($_GET['q'] ?? $_GET['term'] ?? '');
            $db = \App\core\Database::getConnection();
            $sql = "SELECT id, identificacion, nombre, direccion, email AS correo, telefono
                    FROM clientes
                    WHERE (nombre ILIKE :q OR identificacion ILIKE :q)
                      AND id_empresa = :e AND status = '1' AND eliminado = false
                    ORDER BY nombre ASC
                    LIMIT 10";
            $st = $db->prepare($sql);
            $st->execute([':q' => "%$q%", ':e' => (int) $_SESSION['id_empresa']]);
            echo json_encode(['ok' => true, 'data' => $st->fetchAll(\PDO::FETCH_ASSOC)]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'data' => [], 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Busca servicios/productos del catálogo con datos completos (precios de lista,
     * variantes, tarifa de IVA), igual que factura de venta.
     */
    public function getProductosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $buscar = trim($_GET['q'] ?? $_GET['term'] ?? '');
            $repo = new \App\repositories\modulos\ProductoRepository();
            $result = $repo->getListado($idEmpresa, $buscar, 1, 15, 'nombre', 'ASC', null, 'venta', true);
            $rows = array_map(function ($p) use ($repo, $idEmpresa) {
                $p['precios_lista'] = $repo->getPrecios((int) $p['id'], $idEmpresa);
                $p['variantes']     = $repo->getVariantes((int) $p['id'], $idEmpresa);
                return $p;
            }, $result['rows']);
            echo json_encode(['ok' => true, 'data' => $rows]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'data' => [], 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Alias por compatibilidad (misma respuesta que getProductosAjax). */
    public function buscarProductosAjax(): void
    {
        $this->getProductosAjax();
    }

    /** Lotes disponibles de un producto/bodega (para columnas de lote, igual que factura/recibo). */
    public function getLotesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $idProducto = (int) ($_GET['id_producto'] ?? 0);
        $idBodega   = (int) ($_GET['id_bodega'] ?? 0);
        if (!$idProducto || !$idBodega) {
            echo json_encode(['ok' => false, 'mensaje' => 'Faltan parámetros']);
            exit;
        }
        $repoInv = new \App\repositories\modulos\InventarioRepository();
        $lotes = $repoInv->getLotesDisponibles($idProducto, $idBodega, $idEmpresa, null, null);
        $stockTotal = $repoInv->getStockActual($idProducto, $idBodega, $idEmpresa, null, null);
        echo json_encode(['ok' => true, 'data' => $lotes, 'stock_total' => $stockTotal]);
        exit;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** Badge HTML según el estado operativo de la orden. */
    public static function badgeEstado(string $estado): string
    {
        switch ($estado) {
            case 'borrador':
                return '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">Borrador</span>';
            case 'facturado':
                return '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Facturado</span>';
            case 'anulado':
                return '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Anulado</span>';
            default:
                return '<span class="badge bg-secondary bg-opacity-10 text-secondary">' . htmlspecialchars($estado ?: 'Borrador') . '</span>';
        }
    }

    private static function filaHistorial(array $r): string
    {
        $fecha = !empty($r['fecha_ingreso']) ? date('d-m-Y H:i', strtotime($r['fecha_ingreso'])) : '';
        $dataJson = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
        $badge = self::badgeEstado($r['estado'] ?? '');
        return '<tr class="cw-row" role="button" tabindex="0" data-row=\'' . $dataJson . '\' onclick="cwAbrirVer(this)">
                    <td class="ps-3" data-col="fecha_ingreso">' . htmlspecialchars($fecha) . '</td>
                    <td data-col="numero_orden" class="fw-bold text-primary">' . htmlspecialchars($r['numero_orden'] ?? '') . '</td>
                    <td data-col="placa" class="fw-semibold">' . htmlspecialchars($r['placa'] ?? '') . '</td>
                    <td data-col="cliente" class="text-truncate" style="max-width:240px">' . htmlspecialchars($r['cliente_nombre'] ?? '') . '</td>
                    <td data-col="total" class="text-end pe-3">' . number_format((float) ($r['total'] ?? 0), 2) . '</td>
                    <td class="text-center pe-3" data-col="estado">' . $badge . '</td>
                  </tr>';
    }

    private function getEmpresaConfig(int $idEmpresa): array
    {
        $empresaModel = new \App\models\Empresa();
        $empresaData  = $empresaModel->getPorId($idEmpresa) ?? [];
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        if (!empty($establecimientos)) {
            try {
                $estRepo   = new \App\repositories\modulos\EmpresaRepository();
                $estConfig = $estRepo->getEstablecimientoConfig((int) $establecimientos[0]['id']);
                if ($estConfig) {
                    $empresaData = array_merge($empresaData, $estConfig);
                }
            } catch (\Throwable $e) {}
        }
        return $empresaData;
    }
}
