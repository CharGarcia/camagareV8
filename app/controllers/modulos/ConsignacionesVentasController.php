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
    private \App\Services\modulos\ConsignacionFacturaService $facturaService;
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
            $db->exec("ALTER TABLE consignaciones_ventas ADD COLUMN IF NOT EXISTS id_asiento_contable INTEGER NULL");

            // Tablas puente de Facturación desde consignación (auto-fallback si no se corrió la migración).
            $db->exec("CREATE TABLE IF NOT EXISTS consignaciones_facturas (
                id SERIAL PRIMARY KEY, id_empresa INTEGER NOT NULL, id_consignacion INTEGER NOT NULL,
                id_factura INTEGER, numero_factura VARCHAR(50),
                subtotal NUMERIC(15,6) DEFAULT 0, impuesto NUMERIC(15,6) DEFAULT 0, total NUMERIC(15,6) DEFAULT 0,
                id_asiento_reingreso INTEGER, estado VARCHAR(20) DEFAULT 'activa',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_by INTEGER, updated_by INTEGER, eliminado BOOLEAN DEFAULT FALSE, deleted_at TIMESTAMP, deleted_by INTEGER)");
            $db->exec("CREATE TABLE IF NOT EXISTS consignaciones_facturas_detalles (
                id SERIAL PRIMARY KEY, id_consignacion_factura INTEGER NOT NULL, id_empresa INTEGER NOT NULL,
                id_consignacion INTEGER NOT NULL, id_consignacion_detalle INTEGER NOT NULL, id_producto INTEGER NOT NULL,
                cantidad NUMERIC(15,6) NOT NULL, precio_unitario NUMERIC(15,6) DEFAULT 0, id_bodega INTEGER,
                lote VARCHAR(100), nup VARCHAR(100), fecha_caducidad DATE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, eliminado BOOLEAN DEFAULT FALSE, deleted_at TIMESTAMP, deleted_by INTEGER)");
        } catch (\Throwable $e) {}

        $repository = new ConsignacionVentaRepository();
        $rules = new ConsignacionVentaRules();
        $logService = new LogSistemaService();
        $this->service = new ConsignacionVentaService($repository, $rules, $logService);
        $this->facturaService = new \App\Services\modulos\ConsignacionFacturaService(
            new \App\repositories\modulos\ConsignacionFacturaRepository(),
            new \App\Rules\modulos\ConsignacionFacturaRules(),
            $logService
        );
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
                
                $statusBadge = $this->badgeEstadoConsignacion($r['estado'] ?? 'Borrador');

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

    /**
     * Genera el PDF de la consignación. Si la empresa tiene una plantilla activa
     * ('consignacion') se usa el diseñador; si no, el modelo general.
     */
    public function pdf(): void
    {
        $this->requireLeer();

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        if (!$id) { http_response_code(400); echo 'ID requerido'; exit; }

        try {
            $cons = $this->service->getDetalleCompleto($id, $idEmpresa);
            if (!$cons) { http_response_code(404); echo 'Consignación no encontrada'; exit; }

            $detalles = $cons['detalles'] ?? [];
            $empresa  = $this->cargarEmpresaParaPdf($idEmpresa);

            // Cantidad retornada por línea (columna "Retorno" del PDF).
            $retornado = $this->service->getRetornadoPorLinea($id, $idEmpresa);
            foreach ($detalles as &$d) {
                $d['retornado'] = $retornado[(int)($d['id'] ?? 0)] ?? 0;
            }
            unset($d);

            // Fase 2 (personalización): renderer si hay plantilla activa 'consignacion'.
            $renderer  = new \App\Services\PlantillasPdfRendererService();
            $plantilla = $renderer->getPlantillaActiva($idEmpresa, 'consignacion');
            if ($plantilla) {
                $renderer->generar($plantilla, $cons, $detalles, [], [], $empresa, 'D');
            } else {
                (new \App\Services\modulos\ConsignacionVentaPdfService())
                    ->generar($cons, $detalles, $empresa, 'D');
            }
        } catch (\Throwable $e) {
            http_response_code(500);
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
        exit;
    }

    /** Datos de la empresa (con logo del establecimiento) para el PDF. */
    private function cargarEmpresaParaPdf(int $idEmpresa): array
    {
        $empresaModel = new \App\models\Empresa();
        $empresa      = $empresaModel->getPorId($idEmpresa) ?? [];
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        if (!empty($establecimientos[0]['logo_ruta'])) {
            $empresa['logo_ruta'] = $establecimientos[0]['logo_ruta'];
        }
        return $empresa;
    }

    /** Envía por correo el PDF de la consignación (solo el PDF, sin XML). */
    public function enviarCorreoAjax(): void
    {
        ob_start();
        $this->requireLeer();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        if (!$id) { if (ob_get_level() > 0) ob_end_clean(); echo json_encode(['ok' => false, 'mensaje' => 'ID requerido.']); exit; }

        try {
            $cons = $this->service->getDetalleCompleto($id, $idEmpresa);
            if (!$cons) { if (ob_get_level() > 0) ob_end_clean(); echo json_encode(['ok' => false, 'mensaje' => 'Consignación no encontrada.']); exit; }

            $detalles  = $cons['detalles'] ?? [];
            $empresa   = $this->cargarEmpresaParaPdf($idEmpresa);
            $retornado = $this->service->getRetornadoPorLinea($id, $idEmpresa);
            foreach ($detalles as &$d) {
                $d['retornado'] = $retornado[(int)($d['id'] ?? 0)] ?? 0;
            }
            unset($d);

            // PDF como string (misma lógica/hook que la descarga).
            $renderer  = new \App\Services\PlantillasPdfRendererService();
            $plantilla = $renderer->getPlantillaActiva($idEmpresa, 'consignacion');
            if ($plantilla) {
                $pdfString = $renderer->generar($plantilla, $cons, $detalles, [], [], $empresa, 'S');
            } else {
                $pdfString = (new \App\Services\modulos\ConsignacionVentaPdfService())->generar($cons, $detalles, $empresa, 'S');
            }

            $numero = trim((string)($cons['serie'] ?? '') . '-' . (string)($cons['secuencial'] ?? ''), '-');

            // Destinatarios: el que venga del formulario o, en su defecto, el del cliente.
            $correosDestino = trim($_POST['correos'] ?? '');
            if ($correosDestino === '') {
                $correosDestino = (string)($cons['cliente_email'] ?? '');
            }
            if ($correosDestino === '') {
                if (ob_get_level() > 0) ob_end_clean();
                echo json_encode(['ok' => false, 'mensaje' => 'El cliente no tiene correo registrado. Ingrese uno para enviar.']);
                exit;
            }

            $clienteNombre = (string)($cons['cliente_nombre'] ?? 'Cliente');
            $empresaNombre = (string)($empresa['nombre'] ?? '');
            $asunto = 'Consignación en Ventas ' . ($numero !== '' ? $numero : '') . ($empresaNombre !== '' ? ' — ' . $empresaNombre : '');
            $cuerpo = "<div style='font-family:Arial,sans-serif;line-height:1.5;'>"
                . "<p>Estimad@ " . htmlspecialchars($clienteNombre) . ",</p>"
                . "<p>Adjunto encontrará el comprobante de la consignación en ventas <strong>" . htmlspecialchars($numero) . "</strong>.</p>"
                . "<p>Saludos cordiales,<br>" . htmlspecialchars($empresaNombre) . "</p>"
                . "</div>";

            $emailSvc = new \App\Services\EnvioDocumentosSRIService();
            $enviado  = $emailSvc->enviarPdfSimple(
                $idEmpresa, $correosDestino, $clienteNombre, $asunto, $cuerpo, $pdfString,
                'Consignacion_' . ($numero !== '' ? $numero : 'comprobante'), $empresaNombre
            );

            if (ob_get_level() > 0) ob_end_clean();
            if ($enviado) {
                echo json_encode(['ok' => true, 'mensaje' => 'Correo enviado correctamente.']);
            } else {
                echo json_encode(['ok' => false, 'mensaje' => 'No se pudo enviar el correo. Verifica la configuración de correo o el destinatario.']);
            }
        } catch (\Throwable $e) {
            if (ob_get_level() > 0) ob_end_clean();
            echo json_encode(['ok' => false, 'mensaje' => 'Error al enviar correo: ' . $e->getMessage()]);
        }
        exit;
    }

    /** Entregas (evidencia GPS + firma desde la app móvil) de una consignación. */
    public function getEntregasAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idCons    = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            if ($idCons <= 0) {
                echo json_encode(['ok' => true, 'data' => []]);
                exit;
            }
            $rows = $this->service->getEntregasDeConsignacion($idCons, $idEmpresa);
            $base = rtrim(defined('BASE_URL') ? BASE_URL : '', '/');
            foreach ($rows as &$r) {
                $r['firma_url'] = !empty($r['firma_path'])
                    ? $base . '/' . ltrim((string) self::RUTA_MODULO, '/') . '/firmaEntrega?id=' . (int) $r['id']
                    : null;
            }
            unset($r);
            echo json_encode(['ok' => true, 'data' => $rows]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Sirve la imagen de la firma de una entrega (validando la empresa activa). */
    public function firmaEntrega(): void
    {
        $this->requireLeer();

        $idEntrega = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $rel = $idEntrega > 0 ? $this->service->getFirmaEntrega($idEntrega, $idEmpresa) : null;
        if (!$rel) { http_response_code(404); echo 'Firma no encontrada'; exit; }

        $abs = \MVC_ROOT . '/' . $rel;
        if (!is_file($abs)) { http_response_code(404); echo 'Archivo no encontrado'; exit; }

        $mime = 'image/png';
        $ext  = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg'], true)) $mime = 'image/jpeg';
        elseif ($ext === 'webp') $mime = 'image/webp';

        header('Content-Type: ' . $mime);
        header('Cache-Control: private, max-age=300');
        readfile($abs);
        exit;
    }

    /** Retornos asociados a una consignación (pestaña Retornos del modal). */
    public function getRetornosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idCons    = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            if ($idCons <= 0) {
                echo json_encode(['ok' => true, 'data' => []]);
                exit;
            }
            $data = $this->service->getRetornosDeConsignacion($idCons, $idEmpresa);
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Facturas generadas desde una consignación (historial de solo lectura de la pestaña Facturación). */
    public function getFacturasAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idCons    = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            if ($idCons <= 0) {
                echo json_encode(['ok' => true, 'data' => []]);
                exit;
            }
            $data = $this->facturaService->getFacturasDeConsignacion($idEmpresa, $idCons);
            foreach ($data as &$d) {
                if (!empty($d['fecha_emision'])) $d['fecha_emision'] = date('d-m-Y', strtotime($d['fecha_emision']));
                if (!empty($d['created_at']))   $d['created_at']   = date('d-m-Y H:i', strtotime($d['created_at']));
            }
            unset($d);
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Cambia el estado de la consignación (Borrador | Entregada | Anulada). */
    public function cambiarEstadoAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $id     = (int) ($_POST['id'] ?? 0);
            $estado = trim($_POST['estado'] ?? '');
            if ($id <= 0) throw new Exception("ID no válido.");

            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            // Ubicación capturada por el navegador al marcar Entregada manualmente (opcional).
            $datosEntrega = [
                'latitud'     => $_POST['latitud']     ?? null,
                'longitud'    => $_POST['longitud']    ?? null,
                'precision_m' => $_POST['precision_m'] ?? null,
            ];

            $this->service->cambiarEstado($id, $idEmpresa, $idUsuario, $estado, $datosEntrega);
            echo json_encode(['ok' => true, 'msg' => 'Estado actualizado a ' . $estado . '.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Badge HTML del estado de una consignación. */
    private function badgeEstadoConsignacion(string $estado): string
    {
        switch ($estado) {
            case 'Entregada':
                return '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Entregada</span>';
            case 'Facturada':
                return '<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">Facturada</span>';
            case 'Anulada':
                return '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Anulada</span>';
            case 'Emitida': // legado
                return '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">Emitida</span>';
            case 'Borrador':
            default:
                return '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Borrador</span>';
        }
    }

    /**
     * Asiento contable de la consignación: devuelve el asiento guardado (si existe) o la
     * sugerencia de reclasificación de inventario a costo para pintar la pestaña.
     */
    public function getAsientoSugeridoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $idCons    = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

        try {
            if ($idCons > 0) {
                $cab = $this->service->getPorId($idCons, $idEmpresa) ?? [];
                $idAsiento = (int) ($cab['id_asiento_contable'] ?? 0);

                // Si aún no tiene asiento, intentar generarlo ahora: procesarAsientoContable
                // solo persiste si la sugerencia queda COMPLETA (cuentas configuradas) y cuadrada.
                if ($idAsiento <= 0 && !empty($cab)) {
                    try {
                        $this->service->procesarAsientoContable($idCons, [
                            'id_empresa' => $idEmpresa,
                            'id_usuario' => $idUsuario,
                        ]);
                        $cab = $this->service->getPorId($idCons, $idEmpresa) ?? [];
                        $idAsiento = (int) ($cab['id_asiento_contable'] ?? 0);
                    } catch (\Throwable $e) {
                        // No fatal: si no se pudo, se devuelve la sugerencia para completarla a mano.
                    }
                }

                if ($idAsiento > 0) {
                    $asientoService = new \App\Services\modulos\AsientoContableService(
                        new \App\repositories\modulos\AsientoContableRepository(),
                        new \App\Rules\modulos\AsientoContableRules(),
                        new LogSistemaService()
                    );
                    $cabAsiento = $asientoService->getDetalleAsiento($idAsiento, $idEmpresa);
                    $detalles = [];
                    foreach (($cabAsiento['detalles'] ?? []) as $det) {
                        $detalles[] = [
                            'id_cuenta_contable'   => (int) $det['id_cuenta_contable'],
                            'cuenta_codigo'        => $det['codigo_cuenta'] ?? $det['cuenta_codigo'] ?? '',
                            'cuenta_nombre'        => $det['nombre_cuenta'] ?? $det['cuenta_nombre'] ?? '',
                            'debe'                 => (float) $det['debe'],
                            'haber'                => (float) $det['haber'],
                            'referencia_detalle'   => $det['referencia_detalle'] ?? '',
                            'documento_referencia' => $det['documento_referencia'] ?? '',
                        ];
                    }
                    echo json_encode(['ok' => true, 'detalles' => $detalles, 'es_guardado' => true]);
                    exit;
                }

                // Sin asiento guardado: proponer reclasificación por costo.
                $detalles = $this->service->obtenerAsientoSugerido($idEmpresa, $idCons);
                echo json_encode(['ok' => true, 'detalles' => $detalles, 'es_guardado' => false]);
                exit;
            }

            // Consignación nueva (sin id): el costo solo se conoce tras guardar.
            echo json_encode(['ok' => true, 'detalles' => [], 'es_guardado' => false]);
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
        $result = $repo->getListado($idEmpresa, $buscar, 1, 15, 'nombre', 'ASC', null, null, true);

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
