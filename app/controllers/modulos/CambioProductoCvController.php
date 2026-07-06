<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\CambioProductoCvRepository;
use App\repositories\modulos\ProductoRepository;
use App\Rules\modulos\CambioProductoCvRules;
use App\Services\LogSistemaService;
use App\Services\modulos\CambioProductoCvService;
use Exception;

/**
 * Cambios de productos (`modulos/cambio-producto-cv`).
 *
 * Registra el cambio de productos de un cliente: lo que DEVUELVE (entrada de
 * inventario, desde una factura de venta o un cambio anterior) y lo que RECIBE a
 * cambio (salida de inventario). La diferencia de valor es informativa.
 */
class CambioProductoCvController extends BaseModuloController
{
    private CambioProductoCvService $service;
    private const RUTA_MODULO = 'modulos/cambio-producto-cv';
    private const TIPO_SECUENCIAL = 'Cambios de productos';

    public function __construct()
    {
        parent::__construct();
        try {
            $db = \App\Core\Database::getConnection();
            $db->exec("ALTER TABLE cambios_producto_cv ADD COLUMN IF NOT EXISTS id_asiento_contable INTEGER NULL");
        } catch (\Throwable $e) {}

        $repository = new CambioProductoCvRepository();
        $rules      = new CambioProductoCvRules();
        $logService = new LogSistemaService();
        $this->service = new CambioProductoCvService($repository, $rules, $logService);
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
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_cambio');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'desc'));
        $perPage  = 20;

        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $result['rows'];
        $total = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        foreach ($rows as &$r) {
            if (!empty($r['fecha_cambio'])) $r['fecha_cambio'] = date('d-m-Y', strtotime($r['fecha_cambio']));
        }
        unset($r);

        $empresaData = $this->getEmpresaConfig($idEmpresa);

        // Serie unificada (establecimiento-punto): solo puntos con secuencial de cambios configurado.
        $empresaRepo    = new \App\repositories\modulos\EmpresaRepository();
        $repoSecuencial = new \App\repositories\SecuencialRepository();
        $puntos = [];
        foreach ($empresaRepo->getPuntosEmision($idEmpresa) as $p) {
            $cfg = $repoSecuencial->getConfigSecuencial((int) $p['id'], self::TIPO_SECUENCIAL);
            if (!empty($cfg['id'])) {
                $puntos[] = $p;
            }
        }

        $this->viewWithLayout('layouts.main', 'modulos.cambio_producto_cv.index', [
            'titulo'       => 'Cambios de productos',
            'perm'         => $perm,
            'rutaModulo'   => self::RUTA_MODULO,
            'empresa'      => $empresaData,
            'puntos'       => $puntos,
            'rows'         => $rows,
            'total'        => $total,
            'page'         => $page,
            'totalPages'   => $totalPages,
            'perPage'      => $perPage,
            'buscar'       => $buscar,
            'ordenCol'     => $ordenCol,
            'ordenDir'     => $ordenDir,
            'vistaConfig'  => $prefsVista,
            'fullWidth'    => true,
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
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_cambio');
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
            echo '<tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-arrow-left-right fs-3 d-block mb-2"></i>No se encontraron cambios.</td></tr>';
        } else {
            foreach ($rows as $r) {
                if (!empty($r['fecha_cambio'])) $r['fecha_cambio'] = date('d-m-Y', strtotime($r['fecha_cambio']));
                $dataJson = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                $statusBadge = self::badgeEstado($r['estado'] ?? '');

                echo '<tr class="cambio-row" role="button" tabindex="0" data-row=\'' . $dataJson . '\' onclick="abrirModalCambioVer(this)">
                        <td class="ps-3" data-col="fecha_cambio">' . htmlspecialchars($r['fecha_cambio'] ?? '') . '</td>
                        <td data-col="secuencial" class="fw-bold text-primary">' . htmlspecialchars(($r['serie'] ?? '') . '-' . ($r['secuencial'] ?? '')) . '</td>
                        <td data-col="cliente" class="text-truncate" style="max-width:250px">' . htmlspecialchars($r['cliente_nombre'] ?? '') . '</td>
                        <td data-col="motivo" class="text-truncate" style="max-width:220px">' . htmlspecialchars($r['motivo'] ?? '—') . '</td>
                        <td data-col="diferencia" class="text-end pe-3">' . number_format((float)($r['diferencia'] ?? 0), 2) . '</td>
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
            if (!$input) {
                throw new Exception("Datos no recibidos.");
            }

            $input['id_empresa'] = (int) $_SESSION['id_empresa'];
            $input['id_usuario'] = (int) $_SESSION['id_usuario'];
            $input['empresa_config'] = $this->getEmpresaConfig($input['id_empresa']);

            if (!empty($input['id'])) {
                $this->requireActualizar();
                $this->service->actualizar((int) $input['id'], $input['id_empresa'], $input);
                echo json_encode(['ok' => true, 'msg' => 'Cambio actualizado correctamente.']);
            } else {
                $id = $this->service->crear($input);
                echo json_encode(['ok' => true, 'msg' => 'Cambio registrado correctamente. El inventario ha sido actualizado.', 'id' => $id]);
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
            if ($id <= 0) throw new Exception("ID no válido.");

            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            $this->service->eliminar($id, $idEmpresa, $idUsuario, $this->getEmpresaConfig($idEmpresa));
            echo json_encode(['ok' => true, 'msg' => 'Cambio eliminado. El inventario ha sido reversado.']);
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

            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            $this->service->cambiarEstado($id, $idEmpresa, $idUsuario, $estado, $this->getEmpresaConfig($idEmpresa));
            echo json_encode(['ok' => true, 'msg' => 'Estado actualizado a ' . $estado . '.']);
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
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $data = $this->service->getDetalleCompleto($id, $idEmpresa);
            if (!$data) throw new Exception("Cambio no encontrado.");
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Devuelve el asiento contable (a costo) del cambio: el guardado si existe,
     * o la sugerencia (neto Inventario vs Costo de Ventas).
     */
    public function getAsientoSugeridoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $idCambio  = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

        try {
            if ($idCambio <= 0) {
                echo json_encode(['ok' => true, 'detalles' => [], 'es_guardado' => false]);
                exit;
            }

            $cab = $this->service->getPorId($idCambio, $idEmpresa) ?? [];
            $idAsiento = (int) ($cab['id_asiento_contable'] ?? 0);

            if ($idAsiento <= 0 && !empty($cab)) {
                try {
                    $this->service->procesarAsientoContable($idCambio, ['id_empresa' => $idEmpresa, 'id_usuario' => $idUsuario]);
                    $cab = $this->service->getPorId($idCambio, $idEmpresa) ?? [];
                    $idAsiento = (int) ($cab['id_asiento_contable'] ?? 0);
                } catch (\Throwable $e) {}
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

            $detalles = $this->service->obtenerAsientoSugerido($idEmpresa, $idCambio);
            echo json_encode(['ok' => true, 'detalles' => $detalles, 'es_guardado' => false]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Genera el PDF del cambio (modelo general, con hook de plantilla por empresa). */
    public function pdf(): void
    {
        $this->requireLeer();

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        if (!$id) { http_response_code(400); echo 'ID requerido'; exit; }

        try {
            $cambio = $this->service->getDetalleCompleto($id, $idEmpresa);
            if (!$cambio) { http_response_code(404); echo 'Cambio no encontrado'; exit; }

            try {
                $db = \App\Core\Database::getConnection();
                $st = $db->prepare("SELECT nombre FROM usuarios WHERE id = :u LIMIT 1");
                $st->execute([':u' => (int) ($cambio['created_by'] ?? 0)]);
                $cambio['usuario_nombre'] = (string) ($st->fetchColumn() ?: '');
            } catch (\Throwable $e) {
                $cambio['usuario_nombre'] = '';
            }

            $detalles = $cambio['detalles'] ?? [];
            $empresa  = $this->cargarEmpresaParaPdf($idEmpresa);

            $renderer  = new \App\Services\PlantillasPdfRendererService();
            $plantilla = $renderer->getPlantillaActiva($idEmpresa, 'cambio_producto_cv');
            if ($plantilla) {
                $renderer->generar($plantilla, $cambio, $detalles, [], [], $empresa, 'D');
            } else {
                (new \App\Services\modulos\CambioProductoCvPdfService())
                    ->generar($cambio, $detalles, $empresa, 'D');
            }
        } catch (\Throwable $e) {
            http_response_code(500);
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
        exit;
    }

    /** Envía por correo SOLO el PDF del cambio. */
    public function enviarCorreoAjax(): void
    {
        ob_start();
        $this->requireLeer();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        if (!$id) { if (ob_get_level() > 0) ob_end_clean(); echo json_encode(['ok' => false, 'mensaje' => 'ID requerido.']); exit; }

        try {
            $cambio = $this->service->getDetalleCompleto($id, $idEmpresa);
            if (!$cambio) { if (ob_get_level() > 0) ob_end_clean(); echo json_encode(['ok' => false, 'mensaje' => 'Cambio no encontrado.']); exit; }

            try {
                $db = \App\Core\Database::getConnection();
                $st = $db->prepare("SELECT nombre FROM usuarios WHERE id = :u LIMIT 1");
                $st->execute([':u' => (int) ($cambio['created_by'] ?? 0)]);
                $cambio['usuario_nombre'] = (string) ($st->fetchColumn() ?: '');
            } catch (\Throwable $e) {
                $cambio['usuario_nombre'] = '';
            }

            $detalles = $cambio['detalles'] ?? [];
            $empresa  = $this->cargarEmpresaParaPdf($idEmpresa);

            $renderer  = new \App\Services\PlantillasPdfRendererService();
            $plantilla = $renderer->getPlantillaActiva($idEmpresa, 'cambio_producto_cv');
            if ($plantilla) {
                $pdfString = $renderer->generar($plantilla, $cambio, $detalles, [], [], $empresa, 'S');
            } else {
                $pdfString = (new \App\Services\modulos\CambioProductoCvPdfService())->generar($cambio, $detalles, $empresa, 'S');
            }

            $numero = trim((string)($cambio['serie'] ?? '') . '-' . (string)($cambio['secuencial'] ?? ''), '-');

            $correosDestino = trim($_POST['correos'] ?? '');
            if ($correosDestino === '') {
                $correosDestino = (string)($cambio['cliente_email'] ?? '');
            }
            if ($correosDestino === '') {
                if (ob_get_level() > 0) ob_end_clean();
                echo json_encode(['ok' => false, 'mensaje' => 'El cliente no tiene correo registrado. Ingrese uno para enviar.']);
                exit;
            }

            $clienteNombre = (string)($cambio['cliente_nombre'] ?? 'Cliente');
            $empresaNombre = (string)($empresa['nombre'] ?? '');
            $asunto = 'Cambio de productos ' . ($numero !== '' ? $numero : '') . ($empresaNombre !== '' ? ' — ' . $empresaNombre : '');
            $cuerpo = "<div style='font-family:Arial,sans-serif;line-height:1.5;'>"
                . "<p>Estimad@ " . htmlspecialchars($clienteNombre) . ",</p>"
                . "<p>Adjunto encontrará el comprobante del cambio de productos <strong>" . htmlspecialchars($numero) . "</strong>.</p>"
                . "<p>Saludos cordiales,<br>" . htmlspecialchars($empresaNombre) . "</p>"
                . "</div>";

            $emailSvc = new \App\Services\EnvioDocumentosSRIService();
            $enviado  = $emailSvc->enviarPdfSimple(
                $idEmpresa, $correosDestino, $clienteNombre, $asunto, $cuerpo, $pdfString,
                'Cambio_' . ($numero !== '' ? $numero : 'comprobante'), $empresaNombre
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

    // ─── Buscadores ───────────────────────────────────────────────────────────

    /** Busca clientes por nombre o identificación. */
    public function buscarClientesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $q = trim($_GET['q'] ?? '');
        if (mb_strlen($q) < 2) { echo json_encode(['ok' => true, 'data' => []]); exit; }

        try {
            $db = \App\Core\Database::getConnection();
            $sql = "SELECT id, nombre, identificacion, direccion, email
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

    /** Líneas del cliente disponibles para devolver (factura + cambios previos, con saldo). */
    public function buscarLineasOrigenAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idCliente = (int) ($_GET['id_cliente'] ?? 0);
            $q         = trim($_GET['q'] ?? '');
            $excluir   = (int) ($_GET['excluir'] ?? 0);
            if ($idCliente <= 0) throw new Exception("Cliente no válido.");

            $rows = $this->service->getLineasDisponiblesCliente($idEmpresa, $idCliente, $q, $excluir > 0 ? $excluir : null);
            echo json_encode(['ok' => true, 'data' => $rows]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Busca productos de catálogo (para la entrega). */
    public function buscarProductosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $q = trim($_GET['q'] ?? '');
        if (mb_strlen($q) < 2) { echo json_encode(['ok' => true, 'data' => []]); exit; }

        try {
            $repo = new ProductoRepository();
            $res  = $repo->getListado($idEmpresa, $q, 1, 15, 'nombre', 'ASC', null, null, true);
            $data = [];
            foreach (($res['rows'] ?? []) as $p) {
                // Solo bienes/productos, no servicios.
                if ((string)($p['tipo_produccion'] ?? '01') === '02') continue;
                $data[] = [
                    'id'              => (int) $p['id'],
                    'codigo'          => $p['codigo'] ?? '',
                    'nombre'          => $p['nombre'] ?? '',
                    'inventariable'   => $p['inventariable'] ?? null,
                    'tipo_produccion' => $p['tipo_produccion'] ?? '01',
                ];
            }
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Precios de lista de un producto (para la entrega). */
    public function getPreciosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $idProducto = (int) ($_GET['id_producto'] ?? 0);
        try {
            $repo = new ProductoRepository();
            $precios = $idProducto > 0 ? $repo->getPrecios($idProducto, $idEmpresa) : [];
            echo json_encode(['ok' => true, 'data' => $precios]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Bodegas de la empresa (para elegir de dónde sale la entrega). */
    public function getBodegasAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        try {
            $db = \App\Core\Database::getConnection();
            $st = $db->prepare("SELECT id, nombre FROM bodegas WHERE id_empresa = :e AND eliminado = false ORDER BY nombre ASC");
            $st->execute([':e' => $idEmpresa]);
            echo json_encode(['ok' => true, 'data' => $st->fetchAll(\PDO::FETCH_ASSOC)]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ─── Secuencial (mismo patrón que Consignaciones/Retornos) ────────────────

    public function getEstablecimientosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $empresaModel = new \App\models\Empresa();
        $establecimientos = $empresaModel->getEstablecimientos((int) $_SESSION['id_empresa']);
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

        $repoSecuencial = new \App\repositories\SecuencialRepository();
        $puntosFiltrados = [];
        foreach ($puntos as $p) {
            $config = $repoSecuencial->getConfigSecuencial((int) $p['id'], self::TIPO_SECUENCIAL);
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

        $repo = new \App\repositories\SecuencialRepository();
        $config = $repo->getConfigSecuencial($idPunto, self::TIPO_SECUENCIAL);

        if (empty($config['id'])) {
            echo json_encode([
                'ok'  => false,
                'msg' => 'No hay configuración de secuencial para "' . self::TIPO_SECUENCIAL . '" en este punto de emisión. Configúrelo en Empresa / Secuenciales.'
            ]);
            exit;
        }

        $secuencialService = new \App\Services\SecuencialService();
        $res = $secuencialService->obtenerSiguienteSecuencial($idPunto, self::TIPO_SECUENCIAL);
        echo json_encode(array_merge(['ok' => true], $res));
        exit;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** Badge HTML según el estado del cambio (Emitida | Borrador | Anulada). */
    public static function badgeEstado(string $estado): string
    {
        switch ($estado) {
            case 'Emitida':
                return '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Emitida</span>';
            case 'Borrador':
                return '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">Borrador</span>';
            case 'Anulada':
                return '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Anulada</span>';
            default:
                return '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">' . htmlspecialchars($estado) . '</span>';
        }
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
}
