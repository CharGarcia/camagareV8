<?php
declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\FacturaExpressQrRepository;
use App\Rules\modulos\FacturaExpressQrRules;
use App\Services\LogSistemaService;
use App\Services\modulos\FacturaExpressQrService;

class FacturaExpressConfigController extends BaseModuloController
{
    private FacturaExpressQrService $service;
    private const RUTA_MODULO = 'modulos/factura-express-config';

    public function __construct()
    {
        parent::__construct();
        $this->service = new FacturaExpressQrService(
            new FacturaExpressQrRepository(),
            new FacturaExpressQrRules(),
            new LogSistemaService()
        );
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    // ── Vista principal: listado de plantillas ────────────────────────────────
    public function index(): void
    {
        $this->requireLeer();

        $perm       = $this->getPermisos();
        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        $buscar   = trim($_GET['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'created_at');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage = $this->porPagina();

        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir);
        $totalPages = $perPage > 0 ? (int) ceil($result['total'] / $perPage) : 1;
        $from       = $result['total'] > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to         = $result['total'] > 0 ? min($page * $perPage, $result['total']) : 0;

        $empresaRepo   = new \App\repositories\modulos\EmpresaRepository();
        $establecimientos = $empresaRepo->getEstablecimientos($idEmpresa);
        $puntosEmision    = $empresaRepo->getPuntosEmision($idEmpresa);

        $db = \App\core\Database::getConnection();
        $stFp = $db->prepare("SELECT codigo, nombre FROM formas_pago_sri WHERE status = 1 ORDER BY nombre ASC");
        $stFp->execute();
        $formasPago = $stFp->fetchAll(\PDO::FETCH_ASSOC);

        // Decimales de precio configurados: para que el precio unitario que se
        // captura al armar una plantilla respete la misma configuración que el
        // resto del sistema (mismo helper que ya usa FacturaExpressSolicitudesController).
        $empresaConfig = $this->service->getEmpresaConfig($idEmpresa);

        $this->viewWithLayout('layouts.main', 'modulos.factura-express-config.index', [
            'titulo'           => 'Configuración QR',
            'perm'             => $perm,
            'rutaModulo'       => self::RUTA_MODULO,
            'rows'             => $result['rows'],
            'total'            => $result['total'],
            'page'             => $page,
            'totalPages'       => $totalPages,
            'perPage'          => $perPage,
            'from'             => $from,
            'to'               => $to,
            'buscar'           => $buscar,
            'ordenCol'         => $ordenCol,
            'ordenDir'         => $ordenDir,
            'vistaConfig'      => $prefsVista,
            'establecimientos' => $establecimientos,
            'puntosEmision'    => $puntosEmision,
            'formasPago'       => $formasPago,
            'decimalesPrec'    => (int) ($empresaConfig['decimales_precio'] ?? 2),
            'fullWidth'        => true,
        ]);
    }

    // ── AJAX: búsqueda/paginación de plantillas ───────────────────────────────
    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? '');
        $page      = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = $this->porPagina();
        $ordenCol  = trim($_GET['sort'] ?? 'created_at');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? 'DESC'));

        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir);
        $total      = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        $from       = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to         = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        foreach ($result['rows'] as $r) {
            $activo     = (bool) $r['activo'];
            $clsActivo  = $activo ? 'success' : 'secondary';
            $lblActivo  = $activo ? 'Activo' : 'Inactivo';
            $pendientes = (int) ($r['solicitudes_pendientes'] ?? 0);
            $urlPublica = rtrim(BASE_URL, '/') . '/factura-express/' . htmlspecialchars($r['token']);

            echo '<tr class="fexqr-row" role="button" tabindex="0" data-row=\'' . htmlspecialchars(json_encode($r), ENT_QUOTES) . '\' onclick="fexqrAbrirEditar(this)">';
            echo '<td class="ps-3 fw-medium" data-col="nombre">' . htmlspecialchars($r['nombre']) . '</td>';
            echo '<td data-col="descripcion"><small class="text-muted">' . htmlspecialchars($r['descripcion'] ?? '—') . '</small></td>';
            echo '<td class="text-center" data-col="total_items">' . (int)($r['total_items'] ?? 0) . '</td>';
            echo '<td class="text-center" data-col="solicitudes">';
            if ($pendientes > 0) {
                echo '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 fw-bold">'
                   . $pendientes . ' pendiente' . ($pendientes > 1 ? 's' : '') . '</span>';
            } else {
                echo '<span class="text-muted small">' . (int)($r['total_solicitudes'] ?? 0) . '</span>';
            }
            echo '</td>';
            echo '<td class="text-center" data-col="activo"><span class="badge bg-' . $clsActivo . ' bg-opacity-10 text-' . $clsActivo . ' border border-' . $clsActivo . ' border-opacity-25">' . $lblActivo . '</span></td>';
            // Mismos botones que pinta la vista en la carga inicial (QR Solicitudes / QR Clientes).
            $nombreJs = htmlspecialchars($r['nombre'], ENT_QUOTES);
            $urlPanel = rtrim(BASE_URL, '/') . '/modulos/factura-express-solicitudes?empresa=' . $idEmpresa;
            echo '<td class="text-center pe-3 text-nowrap">';
            echo '<button type="button" class="btn btn-outline-warning btn-sm py-1 px-2 me-1 position-relative" onclick="event.stopPropagation();fexqrMostrarQrAdmin(\'' . $urlPanel . '\', \'' . $nombreJs . '\')" title="QR Solicitudes">'
               . '<i class="bi bi-qr-code-scan me-1"></i> QR Solicitudes';
            if ($pendientes > 0) {
                echo '<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.6rem">'
                   . $pendientes . '<span class="visually-hidden">pendientes</span></span>';
            }
            echo '</button>';
            echo '<button type="button" class="btn btn-outline-secondary btn-sm py-1 px-2" onclick="event.stopPropagation();fexqrMostrarQr(\'' . htmlspecialchars($r['token'], ENT_QUOTES) . '\',\'' . $nombreJs . '\',\'' . htmlspecialchars($r['descripcion'] ?? '', ENT_QUOTES) . '\')" title="QR Clientes">'
               . '<i class="bi bi-qr-code me-1"></i> QR Clientes</button>';
            echo '</td>';
            echo '</tr>';
        }
        $rowsHtml = ob_get_clean();

        ob_start();
        echo '<button type="button" class="btn btn-outline-secondary btn-sm" onclick="fexqrCambiarPagina(' . ($page - 1) . ')" ' . ($page <= 1 ? 'disabled' : '') . '><i class="bi bi-chevron-left"></i></button>';
        echo '<button type="button" class="btn btn-outline-secondary btn-sm" onclick="fexqrCambiarPagina(' . ($page + 1) . ')" ' . ($page >= $totalPages ? 'disabled' : '') . '><i class="bi bi-chevron-right"></i></button>';
        $paginHtml = ob_get_clean();

        $urlExportBase = rtrim(BASE_URL, '/') . '/modulos/factura-express-config';
        $pdfUrl   = $urlExportBase . '/export-pdf?b='   . urlencode($buscar) . '&sort=' . urlencode($ordenCol) . '&dir=' . urlencode($ordenDir);
        $excelUrl = $urlExportBase . '/export-excel?b=' . urlencode($buscar) . '&sort=' . urlencode($ordenCol) . '&dir=' . urlencode($ordenDir);

        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginHtml,
            'info'       => "$from-$to/$total",
            'pdf_url'    => $pdfUrl,
            'excel_url'  => $excelUrl,
        ]);
    }

    // ── Exportación del listado (PDF / Excel) ─────────────────────────────────

    /**
     * Plantillas a exportar: las mismas del listado (búsqueda, filtros del modal y orden
     * de pantalla), sin paginar, con la serie y la forma de pago ya en texto.
     */
    private function filasExportacion(): array
    {
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim((string) ($_GET['b'] ?? ''));
        $ordenCol  = trim((string) ($_GET['sort'] ?? 'created_at'));
        $ordenDir  = strtoupper(trim((string) ($_GET['dir'] ?? 'DESC')));

        $rows = $this->service->getListado($idEmpresa, $buscar, 1, 0, $ordenCol, $ordenDir)['rows'];

        $series = [];
        foreach ((new \App\repositories\modulos\EmpresaRepository())->getPuntosEmision($idEmpresa) as $p) {
            $series[(int) $p['id']] = trim(($p['cod_establecimiento'] ?? '') . '-' . ($p['codigo_punto'] ?? ''), '-');
        }
        $formasPago = $this->service->getFormasPagoSriMapa();

        foreach ($rows as &$r) {
            $r['serie_txt']      = $series[(int) ($r['id_punto_emision'] ?? 0)] ?? '';
            $r['forma_pago_txt'] = (string) ($formasPago[(string) ($r['forma_pago'] ?? '')] ?? ($r['forma_pago'] ?? ''));
        }
        unset($r);
        return [$rows, $buscar];
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        try {
            [$rows, $buscar] = $this->filasExportacion();
            $idEmpresa = (int) $_SESSION['id_empresa'];

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            $pt  = 7.5;
            $txt = static fn (string $k, float $w) => static fn (array $r): string
                => \App\Helpers\ReportePdf::texto((string) ($r[$k] ?? '') ?: '-', \App\Helpers\ReportePdf::anchoPt($w, true), $pt);
            $ent = static fn (string $k) => [
                'val' => static fn (array $r): string => number_format((int) ($r[$k] ?? 0)),
                'tot' => static fn (array $f): float => (float) array_sum(array_map(static fn ($r) => (int) ($r[$k] ?? 0), $f)),
                'fmt' => static fn (float $v): string => number_format($v, 0),
            ];

            $cols = [
                ['lbl' => 'Nombre',        'w' => 18, 'cls' => '', 'val' => $txt('nombre', 18)],
                ['lbl' => 'Descripción',   'w' => 20, 'cls' => '', 'val' => $txt('descripcion', 20)],
                ['lbl' => 'Serie',         'w' => 8,  'cls' => 'text-center', 'val' => $txt('serie_txt', 8)],
                ['lbl' => 'Forma de pago', 'w' => 16, 'cls' => '', 'val' => $txt('forma_pago_txt', 16)],
                ['lbl' => 'Aprobación',    'w' => 8,  'cls' => 'text-center', 'val' => static fn (array $r): string => !empty($r['requiere_aprobacion']) ? 'Manual' : 'Automática'],
                ['lbl' => 'Ítems',         'w' => 6,  'cls' => 'text-end'] + $ent('total_items'),
                ['lbl' => 'Solicitudes',   'w' => 8,  'cls' => 'text-end'] + $ent('total_solicitudes'),
                ['lbl' => 'Pendientes',    'w' => 8,  'cls' => 'text-end'] + $ent('solicitudes_pendientes'),
                ['lbl' => 'Estado',        'w' => 8,  'cls' => 'text-center', 'val' => static fn (array $r): string => !empty($r['activo']) ? 'Activo' : 'Inactivo'],
            ];

            $n        = count($rows);
            $activas  = count(array_filter($rows, static fn ($r) => !empty($r['activo'])));
            $empresa  = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
            $cabecera = \App\Helpers\ReportePdf::encabezado($idEmpresa, (string) ($empresa['nombre'] ?? ''), 'Plantillas QR - Factura Express')
                . ($buscar !== '' ? \App\Helpers\ReportePdf::filtros(['Búsqueda y filtros' => $buscar]) : '')
                . \App\Helpers\ReportePdf::indicadores([
                    ['Plantillas', number_format($n)],
                    ['Activas', number_format($activas), false, '#146c43'],
                    ['Inactivas', number_format($n - $activas), false, '#6c757d'],
                    ['Solicitudes pendientes', number_format(array_sum(array_map(static fn ($r) => (int) ($r['solicitudes_pendientes'] ?? 0), $rows))), true, '#997404'],
                ]);

            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            \App\Helpers\ReportePdf::escribir($html2pdf, $pt, $cabecera, $cols, $rows,
                "TOTALES ({$n} " . ($n === 1 ? 'plantilla' : 'plantillas') . ')',
                'Sin plantillas para los filtros aplicados.');
            $html2pdf->output('Plantillas_QR_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode(['ok' => false, 'error' => 'Error al generar el PDF: ' . $e->getMessage()]);
            exit;
        }
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        try {
            [$rows, $buscar] = $this->filasExportacion();
            $idEmpresa = (int) $_SESSION['id_empresa'];

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            $headers = ['Nombre', 'Descripción', 'Serie', 'Forma de pago', 'Aprobación', 'Máx. solicitudes por hora',
                        'Ítems', 'Solicitudes', 'Pendientes', 'Estado', 'Creada', 'Enlace público'];
            $base = rtrim(BASE_URL, '/');
            if (!preg_match('#^https?://#i', $base)) {
                $esHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
                $base = ($esHttps ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/' . ltrim($base, '/');
            }
            $data = [];
            foreach ($rows as $r) {
                $data[] = [
                    (string) ($r['nombre'] ?? ''),
                    (string) ($r['descripcion'] ?? ''),
                    (string) $r['serie_txt'],
                    (string) $r['forma_pago_txt'],
                    !empty($r['requiere_aprobacion']) ? 'Manual' : 'Automática',
                    (int) ($r['max_solicitudes_hora'] ?? 0),
                    (int) ($r['total_items'] ?? 0),
                    (int) ($r['total_solicitudes'] ?? 0),
                    (int) ($r['solicitudes_pendientes'] ?? 0),
                    !empty($r['activo']) ? 'Activo' : 'Inactivo',
                    !empty($r['created_at']) ? date('d-m-Y H:i:s', strtotime($r['created_at'])) : '',
                    rtrim($base, '/') . '/factura-express/' . ($r['token'] ?? ''),
                ];
            }

            $empresa = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
            $info    = $buscar !== '' ? ['Búsqueda y filtros' => $buscar] : [];
            (new \App\Services\ReportService())->exportToExcel(
                'Plantillas_QR', $headers, $data, 'Plantillas QR', (string) ($empresa['nombre'] ?? ''), $info
            );
            exit;
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode(['ok' => false, 'error' => 'Error al generar el Excel: ' . $e->getMessage()]);
            exit;
        }
    }

    // ── AJAX: obtener datos completos de una plantilla ────────────────────────
    public function getPlantillaAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $id        = (int) ($_GET['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $data      = $this->service->getPlantillaConItems($id, $idEmpresa);
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\Exception $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    // ── AJAX: obtener URL + QR de una plantilla ───────────────────────────────
    public function getUrlQrAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $id        = (int) ($_GET['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $url       = $this->service->getUrlQr($id, $idEmpresa);
            $qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?data=' . urlencode($url) . '&size=280x280&margin=10';
            echo json_encode(['ok' => true, 'url' => $url, 'qr_image_url' => $qrImageUrl]);
        } catch (\Exception $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    // ── CRUD plantillas ───────────────────────────────────────────────────────
    public function store(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        try {
            $data               = $_POST;
            $data['id_empresa'] = (int) $_SESSION['id_empresa'];
            $data['id_usuario'] = (int) $_SESSION['id_usuario'];
            $data['activo']              = !empty($data['activo']);
            $data['requiere_aprobacion'] = !empty($data['requiere_aprobacion']);
            $data['items']               = json_decode($data['items_json'] ?? '[]', true) ?: [];

            $id = $this->service->crearPlantilla($data);
            echo json_encode(['ok' => true, 'id' => $id, 'mensaje' => 'Plantilla creada correctamente.']);
        } catch (\InvalidArgumentException $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        } catch (\Exception $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => 'Error al crear: ' . $e->getMessage()]);
        }
    }

    public function update(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $id                 = (int) ($_POST['id'] ?? 0);
            $idEmpresa          = (int) $_SESSION['id_empresa'];
            $data               = $_POST;
            $data['id_empresa'] = $idEmpresa;
            $data['id_usuario'] = (int) $_SESSION['id_usuario'];
            $data['activo']              = !empty($data['activo']);
            $data['requiere_aprobacion'] = !empty($data['requiere_aprobacion']);
            $data['items']               = json_decode($data['items_json'] ?? '[]', true) ?: [];

            $this->service->actualizarPlantilla($id, $idEmpresa, $data);
            echo json_encode(['ok' => true, 'mensaje' => 'Plantilla actualizada correctamente.']);
        } catch (\InvalidArgumentException $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        } catch (\Exception $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => 'Error al actualizar: ' . $e->getMessage()]);
        }
    }

    public function delete(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        try {
            $id        = (int) ($_POST['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            $this->service->eliminarPlantilla($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Plantilla eliminada correctamente.']);
        } catch (\Exception $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    // ── AJAX: buscar productos/servicios activos del catálogo ─────────────────
    public function buscarProductosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $q         = trim($_GET['q'] ?? '');

            $db = \App\core\Database::getConnection();
            $st = $db->prepare("
                SELECT p.id,
                       p.codigo,
                       p.nombre,
                       p.precio_base                                              AS precio_unitario,
                       COALESCE(ti.porcentaje_iva, 0)                            AS porcentaje_iva,
                       ti.tarifa                                                 AS nombre_iva
                FROM productos p
                LEFT JOIN tarifa_iva ti ON ti.id = p.tarifa_iva
                WHERE p.id_empresa = :emp
                  AND p.eliminado  = false
                  AND p.status     = 1
                  AND (p.nombre ILIKE :q OR p.codigo ILIKE :q)
                ORDER BY p.nombre ASC
                LIMIT 15
            ");
            $st->execute([':emp' => $idEmpresa, ':q' => "%{$q}%"]);
            $rows = $st->fetchAll(\PDO::FETCH_ASSOC);

            echo json_encode(['ok' => true, 'data' => $rows]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'data' => [], 'error' => $e->getMessage()]);
        }
    }
}
