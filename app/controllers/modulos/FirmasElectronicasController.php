<?php
declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\FirmaElectronicaRepository;
use App\repositories\modulos\FirmaSolicitudRepository;
use App\Rules\modulos\FirmaElectronicaRules;
use App\Services\LogSistemaService;
use App\Services\modulos\FirmaElectronicaService;

class FirmasElectronicasController extends BaseModuloController
{
    private const RUTA_MODULO   = 'modulos/firmas_electronicas';

    private FirmaElectronicaService $service;
    private FirmaElectronicaRepository $repo;
    private FirmaSolicitudRepository $solRepo;

    public function __construct()
    {
        parent::__construct();
        $this->repo    = new FirmaElectronicaRepository();
        $this->solRepo = new FirmaSolicitudRepository();
        $rules         = new FirmaElectronicaRules();
        $logService    = new LogSistemaService();
        $this->service = new FirmaElectronicaService($this->repo, $rules, $logService);
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    // ── Index ─────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireLeer();

        $perm      = $this->getPermisos();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $buscar     = trim($_GET['b'] ?? '');
        $page       = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol   = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'created_at');
        $ordenDir   = strtoupper(trim($_GET['dir']  ?? $prefsVista['__ordenDir__'] ?? 'desc'));
        $perPage = $this->porPagina();

        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows       = $result['rows'];
        $total      = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        foreach ($rows as &$r) {
            if (!empty($r['created_at'])) $r['created_at'] = date('d-m-Y H:i:s', strtotime($r['created_at']));
            if (!empty($r['updated_at'])) $r['updated_at'] = date('d-m-Y H:i:s', strtotime($r['updated_at']));
            $r['con_ruc']                  = ($r['con_ruc'] ?? 'f') === 't' || $r['con_ruc'] === true;
            $r['facturacion_mismos_datos'] = ($r['facturacion_mismos_datos'] ?? 't') === 't' || $r['facturacion_mismos_datos'] === true;
            $r['factura_eliminada']        = ($r['factura_eliminada'] ?? 'f') === 't' || $r['factura_eliminada'] === true;
        }
        unset($r);

        // Cargar provincias para el modal
        $provincias = (new \App\models\Provincia())->getTodas();

        // Cargar productos con categoría "firmas" para el selector
        $tiposFirma = $this->service->getTiposFirma($idEmpresa);
        $configModulo = $this->service->getConfigModulo($idEmpresa);

        $this->viewWithLayout('layouts.main', 'modulos.firmas_electronicas.index', [
            'configModulo'  => $configModulo,
            'urlEnlaceFijo' => $configModulo['enlace_fijo_codigo'] !== ''
                ? FirmaElectronicaService::urlEnlaceFijo($configModulo['enlace_fijo_codigo']) : '',
            'seriesFact'    => $this->service->getSeriesFacturacion($idEmpresa),
            'formasCobro'   => $this->service->getFormasCobro($idEmpresa),
            'usuariosEmp'   => $this->service->getUsuariosEmpresa($idEmpresa),
            'titulo'      => 'Firmas Electrónicas',
            'perm'        => $perm,
            'rutaModulo'  => self::RUTA_MODULO,
            'rows'        => $rows,
            'total'       => $total,
            'page'        => $page,
            'totalPages'  => $totalPages,
            'perPage'     => $perPage,
            'buscar'      => $buscar,
            'ordenCol'    => $ordenCol,
            'ordenDir'    => $ordenDir,
            'vistaConfig' => $prefsVista,
            'provincias'  => $provincias,
            'tiposFirma'  => $tiposFirma,
            // Selects del modal de filtros (solo lo usado; mismo alcance de registros propios)
            'opcionesFiltro' => $this->service->getOpcionesFiltroListado($idEmpresa, $idUsuarioFiltro),
            'fullWidth'   => true,
        ]);
    }

    // ── AJAX búsqueda ─────────────────────────────────────────

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $buscar     = trim($_GET['b'] ?? '');
        $page       = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol   = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'created_at');
        $ordenDir   = strtoupper(trim($_GET['dir']  ?? $prefsVista['__ordenDir__'] ?? 'desc'));
        $perPage    = $this->porPagina();

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows       = $result['rows'];
        $total      = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        $from       = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to         = $total > 0 ? min($page * $perPage, $total) : 0;

        $base = BASE_URL;
        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="10" class="text-center py-5 text-muted"><i class="bi bi-pen fs-3 d-block mb-2"></i>No se encontraron firmas electrónicas.</td></tr>';
        } else {
            foreach ($rows as $r) {
                $created = !empty($r['created_at']) ? date('d-m-Y H:i:s', strtotime($r['created_at'])) : '—';
                $updated = !empty($r['updated_at']) ? date('d-m-Y H:i:s', strtotime($r['updated_at'])) : '—';
                $r['created_at'] = $created;
                $r['updated_at'] = $updated;
                $r['con_ruc']                  = ($r['con_ruc'] ?? 'f') === 't' || $r['con_ruc'] === true;
                $r['facturacion_mismos_datos'] = ($r['facturacion_mismos_datos'] ?? 't') === 't' || $r['facturacion_mismos_datos'] === true;
                $r['factura_eliminada']        = ($r['factura_eliminada'] ?? 'f') === 't' || $r['factura_eliminada'] === true;

                $badgeEstado     = $this->badgeEstado($r['estado'] ?? 'pendiente');
                $badgeEstadoPago = $this->badgeEstadoPago($r['estado_pago'] ?? 'pendiente');
                $badgeCaducidad  = $this->badgeCaducidad($r['fecha_caducidad'] ?? null);
                $badgeFactura    = $this->badgeEstadoFactura(
                    $r['id_factura'] ? (int)$r['id_factura'] : null,
                    $r['factura_estado'] ?? null,
                    $r['factura_eliminada']
                );
                $dataAttr = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                echo '<tr class="firma-row" role="button" tabindex="0" data-row=\'' . $dataAttr . '\' onclick="abrirModalFirmaEditar(this)">';
                echo '<td class="ps-3 fw-medium" data-col="nombres">' . htmlspecialchars(($r['nombres'] ?? '') . ' ' . ($r['apellidos'] ?? '')) . '</td>';
                echo '<td data-col="numero_identificacion">' . htmlspecialchars($r['numero_identificacion'] ?? '') . '</td>';
                echo '<td data-col="nombre_producto" class="text-muted small">' . htmlspecialchars($r['nombre_producto'] ?? '—') . '</td>';
                echo '<td data-col="telefono">' . htmlspecialchars($r['telefono'] ?? '—') . '</td>';
                echo '<td data-col="correo" class="d-none d-lg-table-cell small">' . htmlspecialchars($r['correo'] ?? '—') . '</td>';
                echo '<td class="text-center" data-col="estado_pago">' . $badgeEstadoPago . '</td>';
                echo '<td class="text-center" data-col="factura_estado">' . $badgeFactura . '</td>';
                echo '<td class="text-center" data-col="estado">' . $badgeEstado . '</td>';
                echo '<td class="text-center" data-col="fecha_caducidad">' . $badgeCaducidad . '</td>';
                echo '<td class="text-end pe-3 small text-muted" data-col="created_at">' . $created . '</td>';
                echo '</tr>';
            }
        }
        $rowsHtml = ob_get_clean();

        ob_start();
        $prevDisabled = ($page <= 1) ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        echo '<div class="btn-group btn-group-sm">'
           . '<button type="button" class="btn btn-outline-secondary border-end-0 rounded-end-0" ' . $prevDisabled . ' onclick="cambiarPaginaFirmas(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>'
           . '<button type="button" class="btn btn-outline-secondary rounded-start-0" ' . $nextDisabled . ' onclick="cambiarPaginaFirmas(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>'
           . '</div>';
        $paginationHtml = ob_get_clean();

        $urlBase = $base . '/' . self::RUTA_MODULO;
        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'       => "$from-$to/$total",
            'total'      => $total,
            'pdf_url'    => $urlBase . '/export-pdf?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir",
            'excel_url'  => $urlBase . '/export-excel?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir",
        ]);
        exit;
    }

    // ── Detalle AJAX ──────────────────────────────────────────

    public function getDetalleAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        try {
            if ($id <= 0) throw new \Exception('ID no válido.');

            $firma    = $this->repo->getDetalleCompleto($id, $idEmpresa);
            if (!$firma) throw new \Exception('Firma no encontrada.');

            $adjuntos = $this->repo->getAdjuntos($id, $idEmpresa);
            $base     = BASE_URL;
            $fmt      = fn($d) => !empty($d) ? date('d-m-Y H:i:s', strtotime($d)) : '—';

            echo json_encode([
                'ok'   => true,
                'data' => [
                    'creado_at'        => $fmt($firma['created_at'] ?? null),
                    'creado_por'       => $firma['creado_por_nombre'] ?? 'Sistema',
                    'actualizado_at'   => $fmt($firma['updated_at'] ?? null),
                    'actualizado_por'  => $firma['actualizado_por_nombre'] ?? '—',
                    'adjuntos'         => array_map(function($a) use ($base) {
                        return [
                            'id'             => $a['id'],
                            'tipo'           => $a['tipo'],
                            'nombre_original'=> $a['nombre_original'],
                            'created_at'     => !empty($a['created_at']) ? date('d-m-Y H:i:s', strtotime($a['created_at'])) : '—',
                            'url_ver'        => $base . '/modulos/firmas_electronicas/verAdjunto?id=' . $a['id'],
                        ];
                    }, $adjuntos),
                ]
            ]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ── Consulta SRI ─────────────────────────────────────────

    public function consultarSri(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json; charset=utf-8');
        $identificacion = trim($_POST['identificacion'] ?? $_GET['identificacion'] ?? '');
        if ($identificacion === '') {
            echo json_encode(['ok' => false, 'error' => 'Identificación vacía.']);
            exit;
        }
        // Soltar el candado de la sesión antes de esperar al SRI: si no, las demás peticiones
        // del usuario hacen fila hasta que responda.
        session_write_close();

        try {
            $svc    = new \App\Services\SriIdentificationService();
            $result = $svc->consultar($identificacion);
            echo json_encode($result);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ── Ciudades por provincia (AJAX) ─────────────────────────

    public function ciudadesPorProvincia(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $codProv   = trim($_GET['cod_prov'] ?? '');
        if ($codProv === '') {
            echo json_encode(['ok' => true, 'data' => []]);
            exit;
        }

        $ciudades = (new \App\models\Ciudad())->getPorProvincia($codProv);
        echo json_encode(['ok' => true, 'data' => $ciudades]);
        exit;
    }

    // ── CRUD ──────────────────────────────────────────────────

    public function store(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $data                = $this->recogerFormulario();
        $data['id_empresa']  = (int) $_SESSION['id_empresa'];
        $data['id_usuario']  = (int) $_SESSION['id_usuario'];

        try {
            $id = $this->service->crear($data);
            echo json_encode(['ok' => true, 'msg' => 'Firma electrónica registrada correctamente.', 'id' => $id]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function update(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $data                = $this->recogerFormulario();
        $data['id_empresa']  = $idEmpresa;
        $data['id_usuario']  = (int) $_SESSION['id_usuario'];
        $data['updated_by']  = (int) $_SESSION['id_usuario'];

        try {
            if ($id <= 0) throw new \Exception('ID no válido.');
            // Con factura activa: el Service decide qué se puede cambiar (todo bloqueado solo si ya está emitida).
            $this->service->actualizar($id, $idEmpresa, $data);
            echo json_encode(['ok' => true, 'msg' => 'Firma electrónica actualizada correctamente.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function delete(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id_eliminar'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            if ($id <= 0) throw new \Exception('ID no válido.');
            $errorFactura = $this->verificarFacturaActiva($id, $idEmpresa);
            if ($errorFactura) { echo json_encode(['ok' => false, 'error' => $errorFactura]); exit; }
            $this->service->eliminar($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Firma electrónica eliminada correctamente.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ── Adjuntos ──────────────────────────────────────────────

    public function uploadAdjunto(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $idFirma   = (int) ($_POST['id_firma'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $tipo      = trim($_POST['tipo'] ?? 'otro');

        try {
            if ($idFirma <= 0) throw new \Exception('ID de firma no válido.');
            $adj = $this->service->subirAdjuntoInterno($idFirma, $idEmpresa, $idUsuario, $tipo, $_FILES['archivo'] ?? []);

            echo json_encode([
                'ok'             => true,
                'msg'            => 'Archivo cargado correctamente.',
                'id'             => $adj['id'],
                'nombre_original'=> $adj['nombre_original'],
                'tipo'           => $adj['tipo'],
                'url_ver'        => BASE_URL . '/modulos/firmas_electronicas/verAdjunto?id=' . $adj['id'],
            ]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function deleteAdjunto(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            if ($id <= 0) throw new \Exception('ID no válido.');
            $adj = $this->repo->getAdjuntoPorId($id, $idEmpresa);
            if (!$adj) throw new \Exception('Adjunto no encontrado.');

            $ruta = MVC_ROOT . '/storage/' . $adj['ruta_relativa'];
            if (file_exists($ruta)) @unlink($ruta);

            $this->repo->deleteAdjunto($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Archivo eliminado.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function verAdjunto(): void
    {
        $this->requireLeer();

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $adj = $this->repo->getAdjuntoPorId($id, $idEmpresa);
        if (!$adj) {
            http_response_code(404);
            exit('Archivo no encontrado.');
        }

        $ruta = MVC_ROOT . '/storage/' . $adj['ruta_relativa'];
        if (!file_exists($ruta)) {
            http_response_code(404);
            exit('Archivo no disponible en disco.');
        }

        $mime = $adj['mime_type'] ?: mime_content_type($ruta);
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . addslashes($adj['nombre_original']) . '"');
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        exit;
    }

    // ── Exportación ───────────────────────────────────────────

    public function exportPdf(): void
    {
        $this->requireLeer();

        $idEmpresa       = (int) $_SESSION['id_empresa'];
        $buscar          = trim($_GET['b'] ?? '');
        $ordenCol        = trim($_GET['sort'] ?? 'created_at');
        $ordenDir        = strtoupper(trim($_GET['dir'] ?? 'desc'));
        $perm            = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $data = $this->service->getListado($idEmpresa, $buscar, 1, 0, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $data['rows'];

        try {
            $empresa      = (new \App\models\Empresa())->getPorId($idEmpresa);
            $nombreEmpresa = $empresa['nombre'] ?? 'Firmas Electrónicas';

            require_once MVC_ROOT . '/vendor/autoload.php';

            ob_start();
            ?>
            <style>
                table { width:100%; border-collapse:collapse; font-family:Arial,sans-serif; font-size:7pt; table-layout:fixed; }
                th { background:#f2f2f2; border:1px solid #ccc; padding:3px; text-align:left; }
                td { border:1px solid #ccc; padding:3px; overflow:hidden; word-wrap:break-word; }
                .header { text-align:center; margin-bottom:12px; }
                h1 { margin:0; font-size:13pt; color:#333; }
                h2 { margin:3px 0 0; color:#666; font-size:9pt; text-transform:uppercase; }
            </style>
            <page backtop="10mm" backbottom="10mm" backleft="10mm" backright="10mm">
                <div class="header">
                    <h1><?= htmlspecialchars($nombreEmpresa) ?></h1>
                    <h2>Listado de Firmas Electrónicas</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th style="width:22%">Nombre</th>
                            <th style="width:12%">Identificación</th>
                            <th style="width:18%">Tipo Firma</th>
                            <th style="width:12%">Teléfono</th>
                            <th style="width:18%">Correo</th>
                            <th style="width:9%">Pago</th>
                            <th style="width:9%">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars(($r['nombres'] ?? '') . ' ' . ($r['apellidos'] ?? '')) ?></td>
                                <td><?= htmlspecialchars($r['numero_identificacion'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['nombre_producto'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($r['telefono'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($r['correo'] ?? '—') ?></td>
                                <td><?= htmlspecialchars(ucfirst($r['estado_pago'] ?? '')) ?></td>
                                <td><?= htmlspecialchars(ucfirst($r['estado'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </page>
            <?php
            $content = ob_get_clean();
            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $html2pdf->writeHTML($content);
            $html2pdf->output('FirmasElectronicas_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo 'Error al generar PDF: ' . $e->getMessage();
            exit;
        }
    }

    public function exportExcel(): void
    {
        $this->requireLeer();

        $idEmpresa       = (int) $_SESSION['id_empresa'];
        $buscar          = trim($_GET['b'] ?? '');
        $ordenCol        = trim($_GET['sort'] ?? 'created_at');
        $ordenDir        = strtoupper(trim($_GET['dir'] ?? 'desc'));
        $perm            = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $data = $this->service->getListado($idEmpresa, $buscar, 1, 0, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $data['rows'];

        try {
            $empresa       = (new \App\models\Empresa())->getPorId($idEmpresa);
            $nombreEmpresa = $empresa['nombre'] ?? '';

            require_once MVC_ROOT . '/vendor/autoload.php';

            $headers    = ['Nombres', 'Apellidos', 'Tipo ID', 'Nro. Identificación', 'Tipo Firma', 'Teléfono', 'Correo', 'Estado Pago', 'Estado', 'Fecha'];
            $exportData = [];
            foreach ($rows as $r) {
                $exportData[] = [
                    $r['nombres'] ?? '',
                    $r['apellidos'] ?? '',
                    ucfirst($r['tipo_identificacion'] ?? ''),
                    $r['numero_identificacion'] ?? '',
                    $r['nombre_producto'] ?? '—',
                    $r['telefono'] ?? '—',
                    $r['correo'] ?? '—',
                    ucfirst($r['estado_pago'] ?? ''),
                    ucfirst($r['estado'] ?? ''),
                    !empty($r['created_at']) ? date('d-m-Y H:i:s', strtotime($r['created_at'])) : '—',
                ];
            }

            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcel('Firmas Electrónicas', $headers, $exportData, 'Listado Firmas Electrónicas', $nombreEmpresa);
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo 'Error al generar Excel: ' . $e->getMessage();
            exit;
        }
    }

    // ── Generar Factura desde Firma ───────────────────────────

    public function generarFacturaDesdeFirma(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json; charset=utf-8');

        $idFirma   = (int) ($_POST['id_firma'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            if ($idFirma <= 0) throw new \Exception('ID de firma no válido.');
            $res = $this->service->generarFacturaBorrador($idFirma, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Factura generada correctamente.', 'url' => BASE_URL . '/modulos/factura-venta'] + $res);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ── Trámite (pago, Uanataca, historial) ───────────────────

    public function getTramiteAjax(): void
    {
        $this->requireLeer();
        $this->responderJson(function () {
            $d = $this->service->getDatosTramite((int) ($_GET['id'] ?? 0), (int) $_SESSION['id_empresa']);
            $f = $d['firma'];
            $fmt = fn($v) => !empty($v) ? date('d-m-Y H:i:s', strtotime($v)) : null;
            return [
                'estado'              => $f['estado'],
                'estado_label'        => FirmaElectronicaService::etiquetaEstado((string) $f['estado']),
                'estado_pago'         => $f['estado_pago'],
                'tipo_pago'           => $f['tipo_pago'],
                'origen'              => $f['origen'] ?? 'interno',
                'total'               => $d['total'],
                'pago_motivo_rechazo' => $f['pago_motivo_rechazo'],
                'id_factura'          => $f['id_factura'],
                'factura_estado'      => $f['factura_estado'],
                'factura_numero'      => !empty($f['factura_secuencial']) ? $f['factura_establecimiento'] . '-' . $f['factura_punto_emision'] . '-' . $f['factura_secuencial'] : null,
                'id_ingreso'          => $f['id_ingreso'],
                'correo'              => $f['correo'],
                'correo_enviado_at'   => $fmt($f['correo_confirmacion_enviado_at']),
                'correo_confirmado_at'=> $fmt($f['correo_confirmado_at']),
                'uanataca_referencia' => $f['uanataca_referencia'],
                'uanataca_registrada_at' => $fmt($f['uanataca_registrada_at']),
                'observacion_cliente' => $f['observacion_cliente'],
                'url_portal'          => $d['url_portal'],
                'fecha_caducidad'     => !empty($f['fecha_caducidad']) ? date('d-m-Y', strtotime($f['fecha_caducidad'])) : null,
                'id_firma_renovacion' => $f['id_firma_renovacion'] ?? null,
                'avisos'              => array_map(fn($a) => [
                    'canal' => $a['canal'], 'manual' => in_array($a['manual'], [true, 't', 1, '1'], true),
                    'enviado' => in_array($a['enviado'], [true, 't', 1, '1'], true), 'dias' => (int) $a['dias'],
                    'destino' => $a['destino'], 'error' => $a['error'], 'fecha' => $fmt($a['created_at']),
                ], $this->service->getAvisosVencimiento((int) $f['id'], (int) $_SESSION['id_empresa'])),
                // Caducidad si se emitiera hoy (vigencia del tipo de firma): valor inicial de "Marcar emitida".
                'caducidad_sugerida'  => $this->service->caducidadEstimada((int) ($f['id_producto'] ?? 0), (int) $_SESSION['id_empresa']),
                'uanataca_api'        => $this->service->getConfigModulo((int) $_SESSION['id_empresa'])['uanataca_activo'],
                'uanataca_uuid'       => $f['uanataca_uuid'],
                'uanataca_estado'     => $f['uanataca_estado'],
                'uanataca_estado_label' => \App\Services\UanatacaApiService::ESTADOS[$f['uanataca_estado'] ?? ''] ?? $f['uanataca_estado'],
                'uanataca_estado_at'  => $fmt($f['uanataca_estado_at']),
                'uanataca_comentarios'=> $f['uanataca_comentarios'],
                'uanataca_error'      => $f['uanataca_error'],
                'uanataca_faltantes'  => $f['estado'] === 'por_registrar' && empty($f['uanataca_uuid'])
                    ? $this->service->faltantesUanataca((int) $f['id'], (int) $_SESSION['id_empresa']) : [],
                'eventos'             => array_map(fn($ev) => [
                    'titulo'  => $ev['titulo'], 'detalle' => $ev['detalle'], 'usuario' => $ev['usuario'],
                    'visible_cliente' => in_array($ev['visible_cliente'], [true, 't', 1, '1'], true),
                    'fecha'   => $fmt($ev['created_at']),
                ], array_reverse($d['eventos'])),
                // Datos para el registro manual en Uanataca (botones de copiar)
                'uanataca' => $this->datosUanataca($f),
                'adjuntos' => array_map(fn($a) => [
                    'id' => $a['id'], 'tipo' => $a['tipo'], 'nombre' => $a['nombre_original'],
                    'etiqueta' => \App\Rules\modulos\FirmaElectronicaRules::ETIQUETAS_ADJUNTO[$a['tipo']] ?? $a['tipo'],
                    'url' => BASE_URL . '/modulos/firmas_electronicas/verAdjunto?id=' . $a['id'],
                ], $d['adjuntos']),
            ];
        });
    }

    public function accionTramiteAjax(): void
    {
        $this->requireActualizar();
        $this->responderJson(function () {
            $this->service->accionTramite(
                (int) ($_POST['id'] ?? 0), (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario'],
                trim($_POST['accion'] ?? ''),
                [
                    'referencia'      => $_POST['referencia'] ?? '',
                    'fecha_caducidad' => $_POST['fecha_caducidad'] ?? '',
                    'nota'            => $_POST['nota'] ?? '',
                    'visible_cliente' => !empty($_POST['visible_cliente']),
                ]
            );
            return ['msg' => 'Trámite actualizado.'];
        });
    }

    public function aprobarPagoAjax(): void
    {
        $this->requireActualizar();
        $this->responderJson(function () {
            $res = $this->service->aprobarPago(
                (int) ($_POST['id'] ?? 0), (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario'],
                (int) ($_POST['id_forma_cobro'] ?? 0), trim($_POST['referencia'] ?? '')
            );
            return ['msg' => $res['ya_existia'] ? 'El pago ya estaba registrado; no se creó otro.' : 'Pago aprobado: se generó la factura y su cobro.'];
        });
    }

    public function rechazarPagoAjax(): void
    {
        $this->requireActualizar();
        $this->responderJson(function () {
            $this->service->rechazarPago((int) ($_POST['id'] ?? 0), (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario'], (string) ($_POST['motivo'] ?? ''));
            return ['msg' => 'Comprobante rechazado. Se avisó al cliente.'];
        });
    }

    public function reenviarConfirmacionAjax(): void
    {
        $this->requireActualizar();
        $this->responderJson(function () {
            $idFirma   = (int) ($_POST['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            // Si la factura sigue en borrador, primero se intenta autorizar.
            $this->service->emitirFacturaYConfirmar($idFirma, $idEmpresa);
            $ok = $this->service->enviarCorreoConfirmacion($idFirma, $idEmpresa, (int) $_SESSION['id_usuario']);
            if (!$ok) throw new \Exception('No se pudo enviar el correo. Revise la configuración de correo de la empresa.');
            return ['msg' => 'Correo con la factura enviado.'];
        });
    }

    public function marcarCorreoConfirmadoAjax(): void
    {
        $this->requireActualizar();
        $this->responderJson(function () {
            $this->service->marcarCorreoConfirmado((int) ($_POST['id'] ?? 0), (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            return ['msg' => 'Correo marcado como confirmado.'];
        });
    }

    public function urlPortalAjax(): void
    {
        $this->requireActualizar();
        $this->responderJson(fn() => ['url' => $this->service->getUrlPortalFirma((int) ($_POST['id'] ?? 0), (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario'])]);
    }

    // ── API de Uanataca ───────────────────────────────────────

    public function probarUanatacaAjax(): void
    {
        $this->requireActualizar();
        $this->responderJson(function () {
            $r = $this->service->probarConexionUanataca((int) $_SESSION['id_empresa']);
            return ['msg' => 'Conexión correcta. Saldo de Uanacréditos: $' . number_format($r['saldo'], 2) . ' · ' . $r['productos'] . ' productos.'] + $r;
        });
    }

    public function saldoUanatacaAjax(): void
    {
        $this->requireLeer();
        $this->responderJson(fn() => ['saldo' => $this->service->getSaldoUanataca((int) $_SESSION['id_empresa'])]);
    }

    public function catalogoUanatacaAjax(): void
    {
        $this->requireActualizar();
        $this->responderJson(fn() => $this->service->getCatalogoUanataca((int) $_SESSION['id_empresa']));
    }

    public function guardarMapeoUanatacaAjax(): void
    {
        $this->requireActualizar();
        $this->responderJson(function () {
            $mapeo = json_decode((string) ($_POST['mapeo'] ?? '{}'), true);
            $this->service->guardarMapeoUanataca((int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario'], is_array($mapeo) ? $mapeo : []);
            return ['msg' => 'Productos de Uanataca guardados.'];
        });
    }

    /**
     * PDF de la factura de venta de la firma (pestaña Facturación): el mismo que descarga
     * Facturas de Venta, con el permiso de ver de este módulo (DocumentoVentaPdfService).
     * Lo pide CMG_pdfDocumento(): ante un error responde JSON {error}.
     */
    public function pdfFacturaAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idFact = $this->service->idFacturaParaPdf((int) ($_GET['id'] ?? 0), $idEmpresa);
        if (!$idFact) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Esta firma no tiene una factura de venta.']);
            exit;
        }
        // El PDF tarda: sin el candado de la sesión, las demás peticiones del usuario no esperan.
        session_write_close();
        try {
            if (!(new \App\Services\modulos\DocumentoVentaPdfService())->descargar('FACTURA', $idFact, $idEmpresa)) {
                http_response_code(404);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Factura no encontrada.']);
            }
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode(['error' => 'Error al generar el PDF: ' . $e->getMessage()]);
        }
        exit;
    }

    public function enviarUanatacaAjax(): void
    {
        $this->requireActualizar();
        // Uanataca puede tardar (documentos en base64): sin soltar la sesión, las demás
        // peticiones del usuario hacen fila hasta que responda.
        session_write_close();
        $this->responderJson(function () {
            $uuid = $this->service->enviarAUanataca((int) ($_POST['id'] ?? 0), (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            return ['msg' => 'Solicitud creada en Uanataca (' . $uuid . '). El cliente recibirá el correo de Uanataca para validar su identidad.'];
        });
    }

    public function sincronizarUanatacaAjax(): void
    {
        $this->requireActualizar();
        $this->responderJson(function () {
            $r = $this->service->sincronizarUanataca((int) ($_POST['id'] ?? 0), (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            return ['msg' => 'Estado en Uanataca: ' . $r['etiqueta'] . ($r['cambio'] ? ' (actualizado).' : ' (sin cambios).')];
        });
    }

    public function avisoVencimientoAjax(): void
    {
        $this->requireActualizar();
        $this->responderJson(fn() => ['msg' => $this->service->avisoVencimientoManual(
            (int) ($_POST['id'] ?? 0), (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']
        )]);
    }

    public function guardarConfigAjax(): void
    {
        $this->requireActualizar();
        $this->responderJson(function () {
            $cfg = $this->service->guardarConfigModulo((int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario'], [
                'id_punto_emision'       => (int) ($_POST['id_punto_emision'] ?? 0),
                'id_usuario_responsable' => (int) ($_POST['id_usuario_responsable'] ?? 0),
                'payphone_activo'        => !empty($_POST['payphone_activo']),
                'transferencia_activa'   => !empty($_POST['transferencia_activa']),
                'datos_transferencia'    => (string) ($_POST['datos_transferencia'] ?? ''),
                'enlace_fijo_activo'     => !empty($_POST['enlace_fijo_activo']),
                'uanataca_activo'           => !empty($_POST['uanataca_activo']),
                'uanataca_ambiente'         => (string) ($_POST['uanataca_ambiente'] ?? 'pruebas'),
                'uanataca_usuario'          => (string) ($_POST['uanataca_usuario'] ?? ''),
                'uanataca_clave'            => (string) ($_POST['uanataca_clave'] ?? ''),
                'uanataca_envio_automatico' => !empty($_POST['uanataca_envio_automatico']),
                'uanataca_exigir_correo'    => !empty($_POST['uanataca_exigir_correo']),
                'avisos_vencimiento_activo' => !empty($_POST['avisos_vencimiento_activo']),
                'avisos_dias_correo'        => (string) ($_POST['avisos_dias_correo'] ?? ''),
                'avisos_dias_whatsapp'      => (string) ($_POST['avisos_dias_whatsapp'] ?? ''),
            ], !empty($_POST['regenerar_enlace']));
            if (isset($_POST['vig']) && is_array($_POST['vig'])) {
                $this->service->guardarVigencias((int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario'], $_POST['vig']);
            }
            if (isset($_POST['tpf']) && is_array($_POST['tpf'])) {
                $this->service->guardarTiposPersona((int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario'], $_POST['tpf']);
            }
            return ['msg' => 'Configuración guardada.', 'url_enlace_fijo' => FirmaElectronicaService::urlEnlaceFijo($cfg['enlace_fijo_codigo'])];
        });
    }
    // ── Helpers ───────────────────────────────────────────────

    private function recogerFormulario(): array
    {
        // Convertir fechas de dd-mm-yyyy a yyyy-mm-dd para la BD
        $convFecha = function (string $val): ?string {
            $val = trim($val);
            if ($val === '') return null;
            if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $val, $m)) {
                return $m[3] . '-' . $m[2] . '-' . $m[1];
            }
            return $val; // ya viene en formato correcto
        };

        return [
            'id_producto'           => (int) ($_POST['id_producto'] ?? 0) ?: null,
            'nombre_producto'       => trim($_POST['nombre_producto'] ?? ''),
            'tipo_persona'          => in_array(trim($_POST['tipo_persona'] ?? ''), ['natural', 'juridica', 'miembro'], true)
                                         ? trim($_POST['tipo_persona']) : 'natural',
            'con_ruc'               => !empty($_POST['con_ruc']),
            'ruc_empresa'           => trim($_POST['ruc_empresa'] ?? ''),
            'nombre_empresa'        => trim($_POST['nombre_empresa'] ?? ''),
            'cargo'                 => trim($_POST['cargo'] ?? ''),
            'tipo_identificacion'   => trim($_POST['tipo_identificacion'] ?? 'cedula'),
            'numero_identificacion' => trim($_POST['numero_identificacion'] ?? ''),
            'codigo_dactilar'       => trim($_POST['codigo_dactilar'] ?? ''),
            'nombres'               => trim($_POST['nombres'] ?? ''),
            'apellidos'             => trim(trim($_POST['apellido_paterno'] ?? '') . ' ' . trim($_POST['apellido_materno'] ?? '')) ?: trim($_POST['apellidos'] ?? ''),
            'apellido_paterno'      => trim($_POST['apellido_paterno'] ?? ''),
            'apellido_materno'      => trim($_POST['apellido_materno'] ?? ''),
            'departamento'          => trim($_POST['departamento'] ?? ''),
            'rep_tipo_identificacion' => in_array($_POST['rep_tipo_identificacion'] ?? '', ['cedula', 'pasaporte'], true) ? $_POST['rep_tipo_identificacion'] : 'cedula',
            'rep_identificacion'    => trim($_POST['rep_identificacion'] ?? ''),
            'rep_nombres'           => trim($_POST['rep_nombres'] ?? ''),
            'rep_apellidos'         => trim($_POST['rep_apellidos'] ?? ''),
            'fecha_nacimiento'      => $convFecha(trim($_POST['fecha_nacimiento'] ?? '')),
            'telefono'              => trim($_POST['telefono'] ?? ''),
            'correo'                => trim($_POST['correo'] ?? ''),
            'cod_prov'              => trim($_POST['cod_prov'] ?? ''),
            'cod_ciudad'            => trim($_POST['cod_ciudad'] ?? ''),
            'nacionalidad'          => trim($_POST['nacionalidad'] ?? ''),
            'sexo'                  => trim($_POST['sexo'] ?? ''),
            'direccion'             => trim($_POST['direccion'] ?? ''),
            'tipo_pago'                 => trim($_POST['tipo_pago'] ?? ''),
            'estado_pago'               => trim($_POST['estado_pago'] ?? 'pendiente'),
            'estado'                    => trim($_POST['estado'] ?? 'pendiente'),
            'fecha_caducidad'           => $convFecha(trim($_POST['fecha_caducidad'] ?? '')),
            'observaciones'             => trim($_POST['observaciones'] ?? ''),
            'facturacion_mismos_datos'  => !empty($_POST['facturacion_mismos_datos']),
            'facturacion_tipo_id'       => trim($_POST['facturacion_tipo_id'] ?? ''),
            'facturacion_num_id'        => trim($_POST['facturacion_num_id'] ?? ''),
            'facturacion_nombres'       => trim($_POST['facturacion_nombres'] ?? ''),
            'facturacion_direccion'     => trim($_POST['facturacion_direccion'] ?? ''),
            'facturacion_correo'        => trim($_POST['facturacion_correo'] ?? ''),
            'facturacion_telefono'      => trim($_POST['facturacion_telefono'] ?? ''),
        ];
    }

    private function badgeEstado(string $estado): string
    {
        $map = [
            'borrador'      => ['secondary', 'Borrador'],
            'pendiente'     => ['warning',   'Pendiente de pago'],
            'por_registrar' => ['info',      'Por registrar'],
            'en_proceso'    => ['primary',   'En trámite'],
            'aprobada'      => ['success',   'Aprobada'],
            'observada'     => ['danger',    'Observada'],
            'emitida'       => ['success',   'Emitida'],
            'cancelada'     => ['dark',      'Cancelada'],
        ];
        [$color, $label] = $map[$estado] ?? ['secondary', ucfirst($estado)];
        return "<span class=\"badge bg-{$color} bg-opacity-10 text-{$color} border border-{$color} border-opacity-25\">{$label}</span>";
    }

    private function badgeCaducidad(?string $fecha): string
    {
        if ($fecha === null || $fecha === '') {
            return '<span class="text-muted small">—</span>';
        }
        $ts  = strtotime($fecha);
        $hoy = time();
        $diff = (int) (($ts - $hoy) / 86400); // días restantes

        $fmtFecha = date('d-m-Y', $ts);

        if ($diff < 0) {
            return '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25" title="Vencida">'
                 . '<i class="bi bi-exclamation-circle me-1"></i>' . $fmtFecha . '</span>';
        }
        if ($diff <= 30) {
            return '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25" title="Vence en ' . $diff . ' días">'
                 . '<i class="bi bi-clock me-1"></i>' . $fmtFecha . '</span>';
        }
        return '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">'
             . $fmtFecha . '</span>';
    }

    private function badgeEstadoPago(string $estado): string
    {
        $map = [
            'pendiente'   => ['warning', 'Pendiente'],
            'en_revision' => ['info',    'En revisión'],
            'confirmado'  => ['success', 'Confirmado'],
            'rechazado'   => ['danger',  'Rechazado'],
        ];
        [$color, $label] = $map[$estado] ?? ['secondary', ucfirst($estado)];
        return "<span class=\"badge bg-{$color} bg-opacity-10 text-{$color} border border-{$color} border-opacity-25\">{$label}</span>";
    }

    private function verificarFacturaActiva(int $idFirma, int $idEmpresa): ?string
    {
        return $this->service->errorFacturaActiva($idFirma, $idEmpresa);
    }

    /** Ejecuta una acción AJAX y responde {ok, ...} o {ok:false, error}. */
    private function responderJson(callable $fn): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            echo json_encode(['ok' => true] + $fn());
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'] ?? '']);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Campos tal como se escriben en el portal de Uanataca (para copiarlos). */
    private function datosUanataca(array $f): array
    {
        $tipoId = ['cedula' => 'Cédula', 'ruc' => 'RUC', 'pasaporte' => 'Pasaporte'][$f['tipo_identificacion'] ?? ''] ?? ($f['tipo_identificacion'] ?? '');
        $campos = [
            ['Tipo de firma',          $f['nombre_producto'] ?? ''],
            ['Tipo de persona',        ($f['tipo_persona'] ?? '') === 'juridica' ? 'Representante legal' : 'Persona natural'],
            ['Tipo de identificación', $tipoId],
            ['Identificación',         $f['numero_identificacion'] ?? ''],
            ['Código dactilar',        $f['codigo_dactilar'] ?? ''],
            ['Nombres',                $f['nombres'] ?? ''],
            ['Primer apellido',        $f['apellido_paterno'] ?: ($f['apellidos'] ?? '')],
            ['Segundo apellido',       $f['apellido_materno'] ?? ''],
            ['Fecha de nacimiento',    !empty($f['fecha_nacimiento']) ? date('d/m/Y', strtotime($f['fecha_nacimiento'])) : ''],
            ['Sexo',                   ['hombre' => 'Masculino', 'mujer' => 'Femenino'][$f['sexo'] ?? ''] ?? ''],
            ['Nacionalidad',           $f['nacionalidad'] ?? ''],
            ['Celular',                $f['telefono'] ?? ''],
            ['Correo',                 $f['correo'] ?? ''],
            ['Provincia',              $f['provincia_nombre'] ?? ''],
            ['Ciudad',                 $f['ciudad_nombre'] ?? ''],
            ['Dirección',              $f['direccion'] ?? ''],
        ];
        if (in_array($f['con_ruc'] ?? false, [true, 't', 1, '1'], true)) {
            $campos[] = ['Con RUC', 'Sí'];
        }
        if (!empty($f['telefono2'])) {
            $campos[] = ['Otro teléfono', $f['telefono2']];
        }
        if (!empty($f['correo2'])) {
            $campos[] = ['Correo alterno', $f['correo2']];
        }
        if (in_array($f['tipo_persona'] ?? '', ['juridica', 'miembro'], true)) {
            $campos[1] = ['Tipo de persona', $f['tipo_persona'] === 'miembro' ? 'Miembro de empresa' : 'Representante legal'];
            $campos[] = ['RUC de la empresa', $f['ruc_empresa'] ?? ''];
            $campos[] = ['Razón social',      $f['nombre_empresa'] ?? ''];
            $campos[] = ['Cargo',             $f['cargo'] ?? ''];
            $campos[] = ['Motivo',            $f['motivo'] ?? ''];
        }
        if (($f['tipo_persona'] ?? '') === 'miembro') {
            $campos[] = ['Departamento',                     $f['departamento'] ?? ''];
            $campos[] = ['Identificación del representante', $f['rep_identificacion'] ?? ''];
            $campos[] = ['Nombres del representante',        $f['rep_nombres'] ?? ''];
            $campos[] = ['Apellidos del representante',      $f['rep_apellidos'] ?? ''];
        }
        return array_map(fn($c) => ['campo' => $c[0], 'valor' => (string) $c[1]], $campos);
    }
    private function badgeEstadoFactura(?int $idFactura, ?string $estado, bool $eliminada): string
    {
        if (!$idFactura || $eliminada) {
            return '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25" style="font-size:.7rem;">Por Facturar</span>';
        }
        return match($estado) {
            'borrador'   => '<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25" style="font-size:.7rem;">Borrador</span>',
            'autorizado' => '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size:.7rem;"><i class="bi bi-check-circle me-1"></i>Facturado</span>',
            'anulado'    => '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25" style="font-size:.7rem;">Anulada</span>',
            default      => '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25" style="font-size:.7rem;">' . htmlspecialchars(ucfirst($estado ?? '?')) . '</span>',
        };
    }

    // ── Solicitudes (formulario público por email) ────────────

    public function enviarSolicitud(): void
    {
        $this->requireCrear();
        // Soltar el candado de la sesión antes de esperar al SMTP/WhatsApp: si no, las demás
        // peticiones del usuario hacen fila hasta que respondan ($_SESSION se sigue leyendo).
        session_write_close();
        $this->responderJson(function () {
            $res = $this->service->enviarEnlace((int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario'], [
                'correo'       => $_POST['correo_destino'] ?? '',
                'nombre'       => $_POST['nombre_destino'] ?? '',
                'telefono'     => $_POST['telefono_destino'] ?? '',
                'por_correo'   => !empty($_POST['por_correo']),
                'por_whatsapp' => !empty($_POST['por_whatsapp']),
                'id_firma'     => (int) ($_POST['id_firma'] ?? 0),
            ]);
            $partes = [];
            if ($res['correo'] === true)  $partes[] = 'Correo enviado.';
            if ($res['correo'] === false) $partes[] = 'El correo no se pudo enviar: ' . $res['correo_error'];
            if ($res['whatsapp'] === 'api')   $partes[] = 'WhatsApp enviado.';
            if ($res['whatsapp'] === 'wa_me') $partes[] = 'Se abrirá WhatsApp con el mensaje listo para enviar.';
            return $res + ['msg' => implode(' ', $partes)];
        });
    }
    public function getSolicitudes(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $page      = max(1, (int)($_GET['page'] ?? 1));
        $perPage   = 15;

        $this->solRepo->expirarVencidos();
        $result = $this->solRepo->getListado($idEmpresa, $page, $perPage);

        $rows = array_map(function (array $r) {
            $r['created_at']    = !empty($r['created_at'])    ? date('d-m-Y H:i', strtotime($r['created_at']))    : '—';
            $r['expira_at']     = !empty($r['expira_at'])     ? date('d-m-Y H:i', strtotime($r['expira_at']))     : '—';
            $r['completado_at'] = !empty($r['completado_at']) ? date('d-m-Y H:i', strtotime($r['completado_at'])) : null;
            $r['firma_nombre']  = trim(($r['firma_apellidos'] ?? '') . ' ' . ($r['firma_nombres'] ?? '')) ?: null;
            return $r;
        }, $result['rows']);

        echo json_encode(['ok' => true, 'rows' => $rows, 'total' => $result['total'], 'page' => $page]);
    }

    public function cancelarSolicitud(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $id        = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'ID inválido.']);
            return;
        }

        $sol = $this->solRepo->getById($id, $idEmpresa);
        if (!$sol) {
            echo json_encode(['ok' => false, 'msg' => 'Solicitud no encontrada.']);
            return;
        }
        if ($sol['estado'] !== 'pendiente') {
            echo json_encode(['ok' => false, 'msg' => 'Solo se pueden cancelar solicitudes pendientes.']);
            return;
        }

        $ok = $this->solRepo->cancelar($id, $idEmpresa, $idUsuario);
        echo json_encode(['ok' => $ok, 'msg' => $ok ? 'Solicitud cancelada.' : 'No se pudo cancelar.']);
    }
}
