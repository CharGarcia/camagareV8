<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\ConsignacionFacturaRepository;
use App\Rules\modulos\ConsignacionFacturaRules;
use App\Services\LogSistemaService;
use App\Services\modulos\ConsignacionFacturaService;
use Exception;

/**
 * Facturación de Consignaciones en Ventas (documento independiente).
 *
 * Documento tipo factura (serie/secuencial propios, cliente a facturar, vendedor)
 * que agrupa líneas de una o varias consignaciones ENTREGADAS y, en un segundo
 * paso, genera la Factura de Venta relacionada.
 */
class FacturacionCvController extends BaseModuloController
{
    private ConsignacionFacturaService $service;
    private const RUTA_MODULO = 'modulos/facturacion-cv';
    private const TIPO_SECUENCIAL = 'Facturacion consignaciones ventas';

    public function __construct()
    {
        parent::__construct();
        try {
            $db = \App\Core\Database::getConnection();
            $db->exec("CREATE TABLE IF NOT EXISTS consignaciones_facturas (
                id SERIAL PRIMARY KEY, id_empresa INTEGER NOT NULL, fecha_emision DATE,
                serie VARCHAR(7), secuencial VARCHAR(20), id_punto_emision INTEGER,
                establecimiento VARCHAR(3), punto_emision VARCHAR(3), tipo_ambiente VARCHAR(1) DEFAULT '1',
                id_cliente INTEGER, id_vendedor INTEGER, observaciones TEXT,
                id_factura INTEGER, numero_factura VARCHAR(50),
                subtotal NUMERIC(15,6) DEFAULT 0, impuesto NUMERIC(15,6) DEFAULT 0, total NUMERIC(15,6) DEFAULT 0,
                id_asiento_reingreso INTEGER, estado VARCHAR(20) DEFAULT 'borrador',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_by INTEGER, updated_by INTEGER, eliminado BOOLEAN DEFAULT FALSE, deleted_at TIMESTAMP, deleted_by INTEGER)");
            foreach ([
                "fecha_emision DATE", "serie VARCHAR(7)", "secuencial VARCHAR(20)", "id_punto_emision INTEGER",
                "establecimiento VARCHAR(3)", "punto_emision VARCHAR(3)", "tipo_ambiente VARCHAR(1)",
                "id_cliente INTEGER", "id_vendedor INTEGER", "observaciones TEXT", "numero_factura VARCHAR(50)",
                "subtotal NUMERIC(15,6)", "impuesto NUMERIC(15,6)", "total NUMERIC(15,6)", "id_asiento_reingreso INTEGER",
                "info_adicional TEXT", "dias_credito INTEGER DEFAULT 0", "forma_pago_sri VARCHAR(10)",
                "pagos_sri TEXT", "plazo_unidad VARCHAR(10) DEFAULT 'dias'"
            ] as $col) {
                $db->exec("ALTER TABLE consignaciones_facturas ADD COLUMN IF NOT EXISTS $col");
            }
            $db->exec("CREATE TABLE IF NOT EXISTS consignaciones_facturas_detalles (
                id SERIAL PRIMARY KEY, id_consignacion_factura INTEGER NOT NULL, id_empresa INTEGER NOT NULL,
                id_consignacion INTEGER NOT NULL, id_consignacion_detalle INTEGER NOT NULL, id_producto INTEGER NOT NULL,
                cantidad NUMERIC(15,6) NOT NULL, precio_unitario NUMERIC(15,6) DEFAULT 0,
                id_impuesto INTEGER, porcentaje_impuesto NUMERIC(5,2) DEFAULT 0, valor_impuesto NUMERIC(15,6) DEFAULT 0,
                subtotal NUMERIC(15,6) DEFAULT 0, total NUMERIC(15,6) DEFAULT 0, id_bodega INTEGER,
                lote VARCHAR(100), nup VARCHAR(100), fecha_caducidad DATE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, eliminado BOOLEAN DEFAULT FALSE, deleted_at TIMESTAMP, deleted_by INTEGER)");
            foreach (["id_impuesto INTEGER", "porcentaje_impuesto NUMERIC(5,2)", "valor_impuesto NUMERIC(15,6)", "subtotal NUMERIC(15,6)", "total NUMERIC(15,6)", "descuento NUMERIC(15,6) DEFAULT 0"] as $col) {
                $db->exec("ALTER TABLE consignaciones_facturas_detalles ADD COLUMN IF NOT EXISTS $col");
            }
        } catch (\Throwable $e) {}

        // La cabecera del DOCUMENTO no usa id_consignacion (la relación con la(s)
        // consignación(es) vive en el detalle). Si una versión previa creó la tabla
        // con id_consignacion NOT NULL, se relaja para no romper el INSERT del documento.
        try {
            \App\Core\Database::getConnection()->exec("ALTER TABLE consignaciones_facturas ALTER COLUMN id_consignacion DROP NOT NULL");
        } catch (\Throwable $e) {}

        $this->service = new ConsignacionFacturaService(
            new ConsignacionFacturaRepository(),
            new ConsignacionFacturaRules(),
            new LogSistemaService()
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

        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'desc'));
        $perPage  = 20;

        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $result['rows'];
        $total = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        foreach ($rows as &$r) {
            if (!empty($r['fecha_emision'])) $r['fecha_emision'] = date('d-m-Y', strtotime($r['fecha_emision']));
        }
        unset($r);

        $empresaData = $this->getEmpresaConfig($idEmpresa);

        // Vendedores activos.
        $vendedorRepo = new \App\repositories\modulos\VendedorRepository();
        $vendedores = $vendedorRepo->getVendedoresActivos($idEmpresa, $idUsuarioFiltro);

        // Puntos de emisión con secuencial de "Facturacion consignaciones ventas" configurado.
        $empresaRepo    = new \App\repositories\modulos\EmpresaRepository();
        $repoSecuencial = new \App\repositories\SecuencialRepository();
        $puntos = [];
        foreach ($empresaRepo->getPuntosEmision($idEmpresa) as $p) {
            $cfg = $repoSecuencial->getConfigSecuencial((int) $p['id'], self::TIPO_SECUENCIAL);
            if (!empty($cfg['id'])) {
                $puntos[] = $p;
            }
        }

        // Formas de pago SRI (para la pestaña Forma de pago).
        $formasPago = [];
        try {
            $formasPago = (new \App\repositories\modulos\FacturaVentaRepository())->getFormasPago();
        } catch (\Throwable $e) {}

        $this->viewWithLayout('layouts.main', 'modulos.facturacion_cv.index', [
            'titulo'      => 'Facturación de Consignaciones',
            'perm'        => $perm,
            'rutaModulo'  => self::RUTA_MODULO,
            'empresa'     => $empresaData,
            'vendedores'  => $vendedores,
            'puntos'      => $puntos,
            'formasPago'  => $formasPago,
            'rows'        => $rows,
            'total'       => $total,
            'page'        => $page,
            'totalPages'  => $totalPages,
            'perPage'     => $perPage,
            'buscar'      => $buscar,
            'ordenCol'    => $ordenCol,
            'ordenDir'    => $ordenDir,
            'vistaConfig' => $prefsVista,
            'fullWidth'   => true,
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
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'desc'));
        $perPage  = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $result['rows'];
        $total = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to   = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-receipt fs-3 d-block mb-2"></i>No hay facturaciones registradas.</td></tr>';
        } else {
            foreach ($rows as $r) {
                $fecha = !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '';
                $dataJson = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                $badge = self::badgeEstado($r['estado'] ?? 'borrador');

                echo '<tr class="factcv-row" role="button" tabindex="0" data-row=\'' . $dataJson . '\' onclick="abrirModalFacturacionVer(this)">
                        <td class="ps-3" data-col="fecha">' . htmlspecialchars($fecha) . '</td>
                        <td data-col="secuencial" class="fw-bold text-primary">' . htmlspecialchars(($r['serie'] ?? '') . '-' . ($r['secuencial'] ?? '')) . '</td>
                        <td data-col="cliente" class="text-truncate" style="max-width:230px">' . htmlspecialchars($r['cliente_nombre'] ?? '') . '</td>
                        <td data-col="factura">' . htmlspecialchars($r['numero_factura'] ?? '—') . '</td>
                        <td data-col="total" class="text-end pe-3">' . number_format((float) ($r['total'] ?? 0), 2) . '</td>
                        <td class="text-center pe-3" data-col="estado">' . $badge . '</td>
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

    public function store(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) throw new Exception('Datos no recibidos.');

            $input['id_empresa'] = (int) $_SESSION['id_empresa'];
            $input['id_usuario'] = (int) $_SESSION['id_usuario'];
            $input['empresa_config'] = $this->getEmpresaConfig($input['id_empresa']);

            if (!empty($input['id'])) {
                $this->requireActualizar();
                $this->service->actualizar((int) $input['id'], (int) $input['id_empresa'], $input);
                echo json_encode(['ok' => true, 'msg' => 'Documento actualizado correctamente.']);
            } else {
                $id = $this->service->crear($input);
                echo json_encode(['ok' => true, 'msg' => 'Documento guardado como borrador.', 'id' => $id]);
            }
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
            if ($id <= 0) throw new Exception('ID no válido.');
            $this->service->eliminar($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            echo json_encode(['ok' => true, 'msg' => 'Documento eliminado.']);
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
            if (!$data) throw new Exception('Documento no encontrado.');
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Paso 2: genera la factura de venta desde un documento borrador. */
    public function generarFacturaAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('Documento no válido.');
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];
            $res = $this->service->generarFactura($id, $idEmpresa, $idUsuario, $this->getEmpresaConfig($idEmpresa));
            echo json_encode(['ok' => true, 'msg' => 'Factura ' . $res['numero_factura'] . ' generada.', 'data' => $res]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Crea un nuevo borrador copiando un documento existente (p. ej. uno anulado). */
    public function duplicarAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        try {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('Documento no válido.');
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];
            $newId = $this->service->duplicar($id, $idEmpresa, $idUsuario, $this->getEmpresaConfig($idEmpresa));
            echo json_encode(['ok' => true, 'msg' => 'Nueva facturación creada en borrador.', 'id' => $newId]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Anula la factura generada (dispara la reversión automática del reingreso). */
    public function anularFacturaAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $idFactura = (int) ($_POST['id_factura'] ?? 0);
            if ($idFactura <= 0) throw new Exception('Factura no válida.');
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            $facturaService = new \App\Services\modulos\FacturaVentaService(
                new \App\repositories\modulos\FacturaVentaRepository(),
                new \App\Rules\modulos\FacturaVentaRules(),
                new LogSistemaService()
            );
            $facturaService->anular($idFactura, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Factura anulada. El saldo de las consignaciones fue liberado.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function buscarConsignacionesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $q = trim($_GET['q'] ?? '');
        if (mb_strlen($q) < 2) { echo json_encode(['ok' => true, 'data' => []]); exit; }
        $data = $this->service->buscarConsignacionesFacturables((int) $_SESSION['id_empresa'], $q);
        echo json_encode(['ok' => true, 'data' => $data]);
        exit;
    }

    public function getLineasFacturablesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idCons = (int) ($_GET['id'] ?? 0);
            if ($idCons <= 0) { echo json_encode(['ok' => true, 'data' => []]); exit; }
            $data = $this->service->getLineasFacturables((int) $_SESSION['id_empresa'], $idCons);
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Asiento contable de reingreso: el guardado (si existe) o la sugerencia (a costo). */
    public function getAsientoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idDoc     = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

        try {
            if ($idDoc <= 0) { echo json_encode(['ok' => true, 'detalles' => [], 'es_guardado' => false]); exit; }

            $doc = $this->service->getPorId($idDoc, $idEmpresa) ?? [];

            // Documento anulado: el asiento de reingreso fue reversado, no se muestra.
            if (($doc['estado'] ?? '') === 'anulada') {
                echo json_encode(['ok' => true, 'detalles' => [], 'es_guardado' => false, 'anulado' => true]);
                exit;
            }

            $idAsiento = (int) ($doc['id_asiento_reingreso'] ?? 0);

            if ($idAsiento > 0) {
                $asientoService = new \App\Services\modulos\AsientoContableService(
                    new \App\repositories\modulos\AsientoContableRepository(),
                    new \App\Rules\modulos\AsientoContableRules(),
                    new LogSistemaService()
                );
                $cab = $asientoService->getDetalleAsiento($idAsiento, $idEmpresa);
                $detalles = [];
                foreach (($cab['detalles'] ?? []) as $det) {
                    $detalles[] = [
                        'id_cuenta_contable' => (int) $det['id_cuenta_contable'],
                        'cuenta_codigo'      => $det['codigo_cuenta'] ?? $det['cuenta_codigo'] ?? '',
                        'cuenta_nombre'      => $det['nombre_cuenta'] ?? $det['cuenta_nombre'] ?? '',
                        'debe'               => (float) $det['debe'],
                        'haber'              => (float) $det['haber'],
                        'referencia_detalle' => $det['referencia_detalle'] ?? '',
                    ];
                }
                echo json_encode(['ok' => true, 'detalles' => $detalles, 'es_guardado' => true]);
                exit;
            }

            $detalles = $this->service->obtenerAsientoReingresoSugerido($idEmpresa, $idDoc);
            echo json_encode(['ok' => true, 'detalles' => $detalles, 'es_guardado' => false]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Búsqueda de clientes para el cliente a facturar (nombre / identificación). */
    public function getClientesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $q = trim($_GET['q'] ?? '');
        try {
            $db = \App\Core\Database::getConnection();
            $sql = "SELECT id, nombre, identificacion, direccion, email, id_vendedor,
                           COALESCE(plazo, 0) AS plazo, id_forma_pago_sri
                    FROM clientes
                    WHERE id_empresa = :e AND eliminado = false
                      AND (nombre ILIKE :q OR identificacion ILIKE :q)
                    ORDER BY nombre ASC LIMIT 15";
            $st = $db->prepare($sql);
            $st->execute([':e' => $idEmpresa, ':q' => '%' . $q . '%']);
            echo json_encode(['ok' => true, 'data' => $st->fetchAll(\PDO::FETCH_ASSOC)]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Un cliente por id con datos para autocompletar (vendedor, crédito, forma de pago, correo). */
    public function getClienteAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $id = (int) ($_GET['id'] ?? 0);
        try {
            if ($id <= 0) { echo json_encode(['ok' => false, 'error' => 'ID no válido.']); exit; }
            $db = \App\Core\Database::getConnection();
            $st = $db->prepare("SELECT id, nombre, identificacion, direccion, email, id_vendedor,
                                       COALESCE(plazo, 0) AS plazo, id_forma_pago_sri
                                FROM clientes WHERE id = :id AND id_empresa = :e AND eliminado = false LIMIT 1");
            $st->execute([':id' => $id, ':e' => $idEmpresa]);
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            echo json_encode($row ? ['ok' => true, 'data' => $row] : ['ok' => false, 'error' => 'Cliente no encontrado.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ─── Secuencial propio ────────────────────────────────────────────────────

    public function getPuntosEmisionAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEst = (int) ($_GET['id_establecimiento'] ?? 0);
        $empresaModel = new \App\models\Empresa();
        $puntos = $empresaModel->getPuntosEmision($idEst);
        $repoSecuencial = new \App\repositories\SecuencialRepository();
        $out = [];
        foreach ($puntos as $p) {
            $cfg = $repoSecuencial->getConfigSecuencial((int) $p['id'], self::TIPO_SECUENCIAL);
            if (!empty($cfg['id'])) $out[] = $p;
        }
        echo json_encode(['ok' => true, 'data' => array_values($out)]);
        exit;
    }

    public function getSecuencialAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idPunto = (int) ($_GET['id_punto_emision'] ?? 0);
        $repo = new \App\repositories\SecuencialRepository();
        $config = $repo->getConfigSecuencial($idPunto, self::TIPO_SECUENCIAL);
        if (empty($config['id'])) {
            echo json_encode(['ok' => false, 'msg' => 'No hay secuencial configurado para "' . self::TIPO_SECUENCIAL . '" en este punto de emisión. Configúrelo en Empresa / Secuenciales.']);
            exit;
        }
        $res = (new \App\Services\SecuencialService())->obtenerSiguienteSecuencial($idPunto, self::TIPO_SECUENCIAL);
        echo json_encode(array_merge(['ok' => true], $res));
        exit;
    }

    // ─── PDF / Correo ─────────────────────────────────────────────────────────

    public function pdf(): void
    {
        $this->requireLeer();
        $id = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        if (!$id) { http_response_code(400); echo 'ID requerido'; exit; }

        try {
            $doc = $this->service->getDetalleCompleto($id, $idEmpresa);
            if (!$doc) { http_response_code(404); echo 'Documento no encontrado'; exit; }
            $empresa = $this->cargarEmpresaParaPdf($idEmpresa);
            (new \App\Services\modulos\ConsignacionFacturaPdfService())->generar($doc, $doc['detalles'] ?? [], $empresa, 'D');
        } catch (\Throwable $e) {
            http_response_code(500);
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
        exit;
    }

    public function enviarCorreoAjax(): void
    {
        ob_start();
        $this->requireLeer();
        header('Content-Type: application/json');

        $id = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        if (!$id) { if (ob_get_level() > 0) ob_end_clean(); echo json_encode(['ok' => false, 'mensaje' => 'ID requerido.']); exit; }

        try {
            $doc = $this->service->getDetalleCompleto($id, $idEmpresa);
            if (!$doc) { if (ob_get_level() > 0) ob_end_clean(); echo json_encode(['ok' => false, 'mensaje' => 'Documento no encontrado.']); exit; }

            $empresa = $this->cargarEmpresaParaPdf($idEmpresa);
            $pdfString = (new \App\Services\modulos\ConsignacionFacturaPdfService())->generar($doc, $doc['detalles'] ?? [], $empresa, 'S');

            $numero = trim((string)($doc['serie'] ?? '') . '-' . (string)($doc['secuencial'] ?? ''), '-');
            $correosDestino = trim($_POST['correos'] ?? '');
            if ($correosDestino === '') $correosDestino = (string)($doc['cliente_email'] ?? '');
            if ($correosDestino === '') {
                if (ob_get_level() > 0) ob_end_clean();
                echo json_encode(['ok' => false, 'mensaje' => 'El cliente no tiene correo registrado. Ingrese uno para enviar.']);
                exit;
            }

            $clienteNombre = (string)($doc['cliente_nombre'] ?? 'Cliente');
            $empresaNombre = (string)($empresa['nombre'] ?? '');
            $asunto = 'Facturación de Consignación ' . ($numero !== '' ? $numero : '') . ($empresaNombre !== '' ? ' — ' . $empresaNombre : '');
            $cuerpo = "<div style='font-family:Arial,sans-serif;line-height:1.5;'>"
                . "<p>Estimad@ " . htmlspecialchars($clienteNombre) . ",</p>"
                . "<p>Adjunto el documento de facturación de consignación <strong>" . htmlspecialchars($numero) . "</strong>.</p>"
                . "<p>Saludos cordiales,<br>" . htmlspecialchars($empresaNombre) . "</p></div>";

            $emailSvc = new \App\Services\EnvioDocumentosSRIService();
            $enviado  = $emailSvc->enviarPdfSimple(
                $idEmpresa, $correosDestino, $clienteNombre, $asunto, $cuerpo, $pdfString,
                'FacturacionConsignacion_' . ($numero !== '' ? $numero : 'comprobante'), $empresaNombre
            );

            if (ob_get_level() > 0) ob_end_clean();
            echo json_encode($enviado
                ? ['ok' => true, 'mensaje' => 'Correo enviado correctamente.']
                : ['ok' => false, 'mensaje' => 'No se pudo enviar el correo. Verifica la configuración o el destinatario.']);
        } catch (\Throwable $e) {
            if (ob_get_level() > 0) ob_end_clean();
            echo json_encode(['ok' => false, 'mensaje' => 'Error al enviar correo: ' . $e->getMessage()]);
        }
        exit;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public static function badgeEstado(string $estado): string
    {
        switch ($estado) {
            case 'facturada':
                return '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Facturada</span>';
            case 'anulada':
                return '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Anulada</span>';
            case 'borrador':
            default:
                return '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">Borrador</span>';
        }
    }

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
