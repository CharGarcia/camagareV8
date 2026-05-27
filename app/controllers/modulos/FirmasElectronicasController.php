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
    private const STORAGE_DIR   = 'firmas_electronicas';
    private const EXTS_PERMITIDAS = ['jpg', 'jpeg', 'png', 'pdf', 'webp'];
    private const MAX_BYTES      = 5 * 1024 * 1024; // 5 MB

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
        $perPage    = 20;

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
        $tiposFirma = $this->getTiposFirma($idEmpresa);

        $this->viewWithLayout('layouts.main', 'modulos.firmas_electronicas.index', [
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
        $perPage    = max(1, (int) ($_GET['perPage'] ?? 20));

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
        try {
            $svc    = new \App\Services\SriIdentificationService();
            $result = $svc->consultar($identificacion);
            echo json_encode($result);
        } catch (\Throwable $e) {
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
            $errorFactura = $this->verificarFacturaActiva($id, $idEmpresa);
            if ($errorFactura) { echo json_encode(['ok' => false, 'error' => $errorFactura]); exit; }
            $this->service->actualizar($id, $idEmpresa, $data);
            echo json_encode(['ok' => true, 'msg' => 'Firma electrónica actualizada correctamente.']);
        } catch (\Throwable $e) {
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

        $tiposPermitidos = [
            'cedula_frontal', 'cedula_posterior', 'selfie',
            'comprobante_transferencia',
            'ruc_empresa', 'constitucion_compania', 'nombramiento', 'aceptacion_nombramiento',
            'otro',
        ];
        if (!in_array($tipo, $tiposPermitidos, true)) $tipo = 'otro';

        try {
            if ($idFirma <= 0) throw new \Exception('ID de firma no válido.');

            $firma = $this->repo->findById($idFirma, $idEmpresa);
            if (!$firma) throw new \Exception('Firma no encontrada o no pertenece a esta empresa.');

            if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
                throw new \Exception('No se recibió ningún archivo o hubo un error al subirlo.');
            }

            $archivo = $_FILES['archivo'];
            if ($archivo['size'] > self::MAX_BYTES) {
                throw new \Exception('El archivo supera el tamaño máximo permitido (5 MB).');
            }

            $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, self::EXTS_PERMITIDAS, true)) {
                throw new \Exception('Tipo de archivo no permitido. Use: ' . implode(', ', self::EXTS_PERMITIDAS));
            }

            $dir = MVC_ROOT . '/storage/' . self::STORAGE_DIR . '/' . $idEmpresa;
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $nombreArchivo = $idFirma . '_' . $tipo . '_' . uniqid() . '.' . $ext;
            $rutaCompleta  = $dir . '/' . $nombreArchivo;

            if (!move_uploaded_file($archivo['tmp_name'], $rutaCompleta)) {
                throw new \Exception('Error al guardar el archivo en el servidor.');
            }

            $this->repo->beginTransaction();
            $adjId = $this->repo->createAdjunto([
                'id_firma'        => $idFirma,
                'id_empresa'      => $idEmpresa,
                'tipo'            => $tipo,
                'nombre_original' => $archivo['name'],
                'nombre_archivo'  => $nombreArchivo,
                'ruta_relativa'   => self::STORAGE_DIR . '/' . $idEmpresa . '/' . $nombreArchivo,
                'mime_type'       => $archivo['type'],
                'tamano_bytes'    => $archivo['size'],
                'created_by'      => $idUsuario,
            ]);
            $this->repo->commit();

            echo json_encode([
                'ok'             => true,
                'msg'            => 'Archivo cargado correctamente.',
                'id'             => $adjId,
                'nombre_original'=> $archivo['name'],
                'tipo'           => $tipo,
                'url_ver'        => BASE_URL . '/modulos/firmas_electronicas/verAdjunto?id=' . $adjId,
            ]);
        } catch (\Throwable $e) {
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

            $firma = $this->repo->getDetalleCompleto($idFirma, $idEmpresa);
            if (!$firma) throw new \Exception('Firma no encontrada.');

            if (empty($firma['fecha_caducidad'])) {
                throw new \Exception('La firma no tiene fecha de caducidad registrada. Guarda la firma con fecha de caducidad antes de generar la factura.');
            }

            $idProducto = (int)($firma['id_producto'] ?? 0);
            if ($idProducto <= 0) throw new \Exception('La firma no tiene una Validez de Firma seleccionada. Guarda la firma antes de generar la factura.');

            // Verificar que no exista factura activa
            if (!empty($firma['id_factura'])) {
                $db = \App\core\Database::getConnection();
                $stChk = $db->prepare("SELECT estado, eliminado FROM ventas_cabecera WHERE id = :id");
                $stChk->execute([':id' => $firma['id_factura']]);
                $factExist = $stChk->fetch(\PDO::FETCH_ASSOC);
                if ($factExist && $factExist['eliminado'] !== 't' && $factExist['estado'] !== 'anulada') {
                    throw new \Exception('Ya existe una factura activa para esta firma (estado: ' . ucfirst($factExist['estado'] ?? '') . '). No se puede generar otra.');
                }
            }

            // Determinar datos de facturación
            $mismosDatos = ($firma['facturacion_mismos_datos'] === 't' || $firma['facturacion_mismos_datos'] === true);

            if ($mismosDatos) {
                $tipoIdStr = $firma['tipo_identificacion'] ?? 'cedula';
                $numId     = $firma['numero_identificacion'] ?? '';
                $nombres   = trim(($firma['apellidos'] ?? '') . ' ' . ($firma['nombres'] ?? ''));
                $correo    = $firma['correo'] ?? '';
                $telefono  = $firma['telefono'] ?? '';
                $direccion = $firma['direccion'] ?? '';
            } else {
                $tipoIdStr = $firma['facturacion_tipo_id'] ?? 'cedula';
                $numId     = $firma['facturacion_num_id'] ?? '';
                $nombres   = $firma['facturacion_nombres'] ?? '';
                $correo    = $firma['facturacion_correo'] ?? '';
                $telefono  = $firma['facturacion_telefono'] ?? '';
                $direccion = $firma['facturacion_direccion'] ?? '';
            }

            if (trim($numId) === '') throw new \Exception('No hay número de identificación para la facturación.');
            if (trim($nombres) === '') throw new \Exception('No hay nombres para la facturación.');

            $tipoIdCodigo = match($tipoIdStr) {
                'ruc'       => '04',
                'pasaporte' => '06',
                default     => '05',
            };

            $db = \App\core\Database::getConnection();

            // Buscar o crear cliente
            $stBuscar = $db->prepare("SELECT id FROM clientes WHERE id_empresa = :ie AND identificacion = :id AND eliminado = false LIMIT 1");
            $stBuscar->execute([':ie' => $idEmpresa, ':id' => $numId]);
            $idCliente = $stBuscar->fetchColumn();

            if (!$idCliente) {
                $stCrear = $db->prepare(
                    "INSERT INTO clientes (id_empresa, id_usuario, nombre, tipo_id, identificacion, telefono, email, direccion, plazo, status, created_by, created_at, eliminado)
                     VALUES (:ie, :iu, :nom, :tid, :idd, :tel, :email, :dir, 0, 1, :iu, CURRENT_TIMESTAMP, false) RETURNING id"
                );
                $stCrear->execute([
                    ':ie'   => $idEmpresa,
                    ':iu'   => $idUsuario,
                    ':nom'  => mb_strtoupper(trim($nombres), 'UTF-8'),
                    ':tid'  => $tipoIdCodigo,
                    ':idd'  => $numId,
                    ':tel'  => $telefono ?: '',
                    ':email'=> $correo ?: '',
                    ':dir'  => $direccion ?: '',
                ]);
                $idCliente = $stCrear->fetchColumn();
            }

            if (!$idCliente) throw new \Exception('No se pudo encontrar o crear el cliente.');

            // Obtener primer punto de emisión activo
            $stPunto = $db->prepare(
                "SELECT p.id, p.codigo_punto, e.id AS id_establecimiento, e.codigo AS cod_establecimiento
                 FROM empresa_punto_emision p
                 LEFT JOIN empresa_establecimiento e ON e.id = p.id_establecimiento
                 WHERE p.id_empresa = :ie AND p.eliminado = false AND e.eliminado = false
                 ORDER BY e.codigo ASC, p.codigo_punto ASC
                 LIMIT 1"
            );
            $stPunto->execute([':ie' => $idEmpresa]);
            $punto = $stPunto->fetch(\PDO::FETCH_ASSOC);
            if (!$punto) throw new \Exception('No hay puntos de emisión configurados para esta empresa.');

            // Secuencial
            $secInfo = (new \App\Services\SecuencialService())->obtenerSiguienteSecuencial((int)$punto['id'], 'Facturas de venta');

            // Producto con IVA
            $stProd = $db->prepare(
                "SELECT p.id, p.nombre, p.precio_base, p.codigo,
                        ti.id AS id_tarifa_iva, ti.porcentaje_iva, ti.codigo AS codigo_porcentaje_iva
                 FROM productos p
                 LEFT JOIN tarifa_iva ti ON ti.id = p.tarifa_iva
                 WHERE p.id = :id AND p.id_empresa = :ie AND p.eliminado = false"
            );
            $stProd->execute([':id' => $idProducto, ':ie' => $idEmpresa]);
            $prod = $stProd->fetch(\PDO::FETCH_ASSOC);
            if (!$prod) throw new \Exception('Producto/Validez de Firma no encontrado.');

            $precioBase    = round((float)($prod['precio_base'] ?? 0), 2);
            $porcentajeIva = (float)($prod['porcentaje_iva'] ?? 0);
            $valorIva      = round($precioBase * $porcentajeIva / 100, 2);
            $precioTotal   = round($precioBase + $valorIva, 2);

            $empresaData = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];

            $dataFact = [
                'id_empresa'           => $idEmpresa,
                'id_usuario'           => $idUsuario,
                'empresa_config'       => $empresaData,
                'id_cliente'           => (int)$idCliente,
                'id_establecimiento'   => (int)$punto['id_establecimiento'],
                'id_punto_emision'     => (int)$punto['id'],
                'establecimiento'      => $punto['cod_establecimiento'],
                'punto_emision'        => $punto['codigo_punto'],
                'secuencial'           => $secInfo['formateado'],
                'fecha_emision'        => date('Y-m-d'),
                'estado'               => 'borrador',
                'total_sin_impuestos'  => $precioBase,
                'total_descuento'      => 0,
                'importe_total'        => $precioTotal,
                'detalles' => [
                    [
                        'id_producto'               => $idProducto,
                        'descripcion'               => $prod['nombre'],
                        'cantidad'                  => 1,
                        'precio_unitario'           => $precioBase,
                        'descuento'                 => 0,
                        'precio_total_sin_impuesto' => $precioBase,
                        'codigo_principal'          => $prod['codigo'] ?: $prod['nombre'],
                        'id_tarifa_iva'             => (int)($prod['id_tarifa_iva'] ?? 0),
                        'impuestos' => [
                            [
                                'codigo_impuesto'   => '2',
                                'codigo_porcentaje' => $prod['codigo_porcentaje_iva'] ?? '0',
                                'tarifa'            => $porcentajeIva,
                                'base_imponible'    => $precioBase,
                                'valor'             => $valorIva,
                            ]
                        ],
                    ]
                ],
                'pagos' => [
                    ['forma_pago' => '01', 'total' => $precioTotal, 'plazo' => 0, 'unidad_tiempo' => 'dias']
                ],
            ];

            $factService = new \App\Services\modulos\FacturaVentaService(
                new \App\repositories\modulos\FacturaVentaRepository(),
                new \App\Rules\modulos\FacturaVentaRules(),
                new \App\Services\LogSistemaService()
            );
            $idFactura = $factService->crear($dataFact);

            // Asociar factura a la firma
            $db->prepare("UPDATE firmas_electronicas SET id_factura = :if, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND id_empresa = :ie")
               ->execute([':if' => $idFactura, ':id' => $idFirma, ':ie' => $idEmpresa]);

            echo json_encode([
                'ok'                      => true,
                'msg'                     => 'Factura generada correctamente.',
                'id_factura'              => $idFactura,
                'factura_estado'          => 'borrador',
                'factura_establecimiento' => $punto['cod_establecimiento'],
                'factura_punto_emision'   => $punto['codigo_punto'],
                'factura_secuencial'      => $secInfo['formateado'],
                'factura_importe_total'   => $precioTotal,
                'url'                     => BASE_URL . '/modulos/factura-venta',
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
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
            'tipo_persona'          => in_array(trim($_POST['tipo_persona'] ?? ''), ['natural', 'juridica'], true)
                                         ? trim($_POST['tipo_persona']) : 'natural',
            'con_ruc'               => !empty($_POST['con_ruc']),
            'ruc_empresa'           => trim($_POST['ruc_empresa'] ?? ''),
            'nombre_empresa'        => trim($_POST['nombre_empresa'] ?? ''),
            'cargo'                 => trim($_POST['cargo'] ?? ''),
            'tipo_identificacion'   => trim($_POST['tipo_identificacion'] ?? 'cedula'),
            'numero_identificacion' => trim($_POST['numero_identificacion'] ?? ''),
            'codigo_dactilar'       => trim($_POST['codigo_dactilar'] ?? ''),
            'nombres'               => trim($_POST['nombres'] ?? ''),
            'apellidos'             => trim($_POST['apellidos'] ?? ''),
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
            'pendiente'   => ['warning',  'Pendiente'],
            'en_proceso'  => ['info',     'En Proceso'],
            'emitida'     => ['success',  'Emitida'],
            'cancelada'   => ['danger',   'Cancelada'],
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
            'confirmado'  => ['success', 'Confirmado'],
            'rechazado'   => ['danger',  'Rechazado'],
        ];
        [$color, $label] = $map[$estado] ?? ['secondary', ucfirst($estado)];
        return "<span class=\"badge bg-{$color} bg-opacity-10 text-{$color} border border-{$color} border-opacity-25\">{$label}</span>";
    }

    private function verificarFacturaActiva(int $idFirma, int $idEmpresa): ?string
    {
        try {
            $db = \App\core\Database::getConnection();
            $st = $db->prepare(
                "SELECT v.estado
                 FROM firmas_electronicas f
                 JOIN ventas_cabecera v ON v.id = f.id_factura
                 WHERE f.id = :id AND f.id_empresa = :ie
                   AND f.eliminado = false AND f.id_factura IS NOT NULL
                   AND v.eliminado = false
                   AND v.estado IN ('borrador', 'autorizada')"
            );
            $st->execute([':id' => $idFirma, ':ie' => $idEmpresa]);
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                return 'Esta firma tiene una factura activa (estado: ' . ucfirst($row['estado']) . '). Primero debe eliminar o anular la factura desde el módulo de Facturas de Venta.';
            }
        } catch (\Throwable) {}
        return null;
    }

    private function badgeEstadoFactura(?int $idFactura, ?string $estado, bool $eliminada): string
    {
        if (!$idFactura || $eliminada) {
            return '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25" style="font-size:.7rem;">Por Facturar</span>';
        }
        return match($estado) {
            'borrador'   => '<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25" style="font-size:.7rem;">Borrador</span>',
            'autorizada' => '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size:.7rem;"><i class="bi bi-check-circle me-1"></i>Facturado</span>',
            'anulada'    => '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25" style="font-size:.7rem;">Anulada</span>',
            default      => '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25" style="font-size:.7rem;">' . htmlspecialchars(ucfirst($estado ?? '?')) . '</span>',
        };
    }

    private function getTiposFirma(int $idEmpresa): array
    {
        try {
            $db = \App\core\Database::getConnection();
            $st = $db->prepare(
                "SELECT p.id, p.nombre,
                        ROUND(
                            (p.precio_base + COALESCE(p.valor_ice, 0))
                            * (1 + COALESCE(ti.porcentaje_iva, 0)::numeric / 100),
                        2) AS pvp
                 FROM productos p
                 INNER JOIN categorias c ON c.id = p.id_categoria
                 LEFT  JOIN tarifa_iva ti ON ti.id = p.tarifa_iva
                 WHERE p.id_empresa = :ie
                   AND p.eliminado = false
                   AND p.status = 1
                   AND UPPER(c.nombre) LIKE '%FIRMA%'
                 ORDER BY p.nombre ASC"
            );
            $st->execute([':ie' => $idEmpresa]);
            return $st->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    // ── Solicitudes (formulario público por email) ────────────

    public function enviarSolicitud(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $correo    = trim($_POST['correo_destino'] ?? '');
        $nombre    = trim($_POST['nombre_destino'] ?? '');

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['ok' => false, 'msg' => 'Correo electrónico no válido.']);
            return;
        }

        // Generar token único de 64 chars hex
        $token = bin2hex(random_bytes(32));

        // Obtener nombre de la empresa
        $empresaRow = (new \App\models\Empresa())->getPorId($idEmpresa);
        $empresaNombre = $empresaRow['nombre'] ?? 'Firma Electrónica';

        $expiraAt = date('Y-m-d H:i:s', strtotime('+7 days'));
        $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $urlForm  = $scheme . '://' . $host . rtrim(BASE_URL, '/') . '/solicitud-firma/' . $token;

        try {
            $idSol = $this->solRepo->crear([
                'id_empresa'     => $idEmpresa,
                'token'          => $token,
                'correo_destino' => $correo,
                'nombre_destino' => $nombre ?: null,
                'expira_at'      => $expiraAt,
                'created_by'     => $idUsuario,
            ]);

            $enviado = enviar_correo_solicitud_firma($correo, [
                'nombre_destino' => $nombre,
                'empresa_nombre' => $empresaNombre,
                'url_formulario' => $urlForm,
                'expira'         => date('d-m-Y H:i', strtotime($expiraAt)),
            ]);

            if (!$enviado) {
                $err = $GLOBALS['LAST_EMAIL_ERROR'] ?? 'Error desconocido al enviar el correo.';
                echo json_encode(['ok' => false, 'msg' => 'Solicitud creada pero no se pudo enviar el correo: ' . $err]);
                return;
            }

            echo json_encode(['ok' => true, 'msg' => 'Formulario enviado a ' . $correo, 'id' => $idSol]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
        }
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
