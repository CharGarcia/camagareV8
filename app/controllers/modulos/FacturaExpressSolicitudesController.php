<?php
declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\FacturaExpressQrRepository;
use App\Rules\modulos\FacturaExpressQrRules;
use App\Services\LogSistemaService;
use App\Services\modulos\FacturaExpressQrService;

class FacturaExpressSolicitudesController extends BaseModuloController
{
    private FacturaExpressQrService $service;
    private const RUTA_MODULO = 'modulos/factura-express-solicitudes';

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

    // ── Vista principal ───────────────────────────────────────────────────────
    public function index(): void
    {
        $this->requireLeer();

        // 1. Interceptar parámetro ?empresa= desde el código QR para autoseleccionar empresa
        $empresaGet = (int) ($_GET['empresa'] ?? 0);
        if ($empresaGet > 0 && $empresaGet !== (int)($_SESSION['id_empresa'] ?? 0)) {
            $idUsuario = (int) $_SESSION['id_usuario'];
            $nivel = (int) $_SESSION['nivel'];
            $tieneAcceso = false;

            if ($nivel >= 3) {
                $tieneAcceso = true;
            } else {
                $model = new \App\models\Empresa();
                $asignadas = $model->getEmpresasAsignadas($idUsuario);
                $ids = array_column($asignadas, 'id_empresa');
                if (in_array($empresaGet, array_map('intval', $ids))) {
                    $tieneAcceso = true;
                }
            }

            if ($tieneAcceso) {
                $_SESSION['id_empresa'] = $empresaGet;
                $model = new \App\models\Empresa();
                $empData = $model->getPorId($empresaGet);
                if ($empData) {
                    $_SESSION['ruc_empresa'] = $empData['identificacion'] ?? '';
                }
            } else {
                $_SESSION['navbar_error'] = 'No tienes permiso para acceder a la empresa del código QR.';
            }

            // Redirigir para limpiar la URL
            header('Location: ' . BASE_URL . '/' . self::RUTA_MODULO);
            exit;
        }

        $perm       = $this->getPermisos();
        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        // El estado se filtra desde el modal de filtros (estado:valor dentro de b).
        // Al entrar sin búsqueda se muestran todas las solicitudes.
        $buscar   = trim((string) ($_GET['b'] ?? ''));
        $estado   = trim($_GET['estado'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'created_at');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage = $this->porPagina();

        $result     = $this->service->getListadoSolicitudes($idEmpresa, $buscar, $estado, $page, $perPage, $ordenCol, $ordenDir);
        $totalPages = $perPage > 0 ? (int) ceil($result['total'] / $perPage) : 1;
        $from       = $result['total'] > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to         = $result['total'] > 0 ? min($page * $perPage, $result['total']) : 0;

        $empresaConfig = $this->service->getEmpresaConfig($idEmpresa);

        $this->viewWithLayout('layouts.main', 'modulos.factura-express-solicitudes.index', [
            'titulo'        => 'Solicitudes Factura Express',
            'perm'          => $perm,
            'rutaModulo'    => self::RUTA_MODULO,
            'rows'          => $result['rows'],
            'total'         => $result['total'],
            'page'          => $page,
            'totalPages'    => $totalPages,
            'perPage'       => $perPage,
            'from'          => $from,
            'to'            => $to,
            'buscar'        => $buscar,
            'estadoFiltro'  => $estado,
            'ordenCol'      => $ordenCol,
            'ordenDir'      => $ordenDir,
            'vistaConfig'   => $prefsVista,
            'tarifasIva'    => $this->service->getTarifasIva(),
            'decimalesPrec' => (int) ($empresaConfig['decimales_precio'] ?? 2),
            'fullWidth'     => true,
        ]);
    }

    // ── AJAX: búsqueda/paginación ─────────────────────────────────────────────
    public function searchAjax(): void
    {
        $this->requireLeer();

        // Limpiar cualquier output previo (warnings de PHP, etc.)
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $buscar    = trim($_GET['b'] ?? '');
            $estado    = trim($_GET['estado'] ?? '');   // el estado viaja en b (estado:valor)
            $page      = max(1, (int) ($_GET['page'] ?? 1));
            $ordenCol  = trim($_GET['sort'] ?? 'created_at');
            $ordenDir  = strtoupper(trim($_GET['dir'] ?? 'DESC'));
            $perPage = $this->porPagina();

            $result     = $this->service->getListadoSolicitudes($idEmpresa, $buscar, $estado, $page, $perPage, $ordenCol, $ordenDir);
            $total      = $result['total'];
            $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
            $from       = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
            $to         = $total > 0 ? min($page * $perPage, $total) : 0;

            $estadoClases = [
                'pendiente' => 'warning',
                'aprobada'  => 'info',
                'rechazada' => 'danger',
                'facturada' => 'success',
            ];

            ob_start();
            foreach ($result['rows'] as $r) {
                $cls   = $estadoClases[$r['estado'] ?? 'pendiente'] ?? 'secondary';
                $lbl   = ucfirst($r['estado'] ?? '');
                $fecha = !empty($r['created_at']) ? date('d-m-Y H:i', strtotime($r['created_at'])) : '—';
                $monto = number_format((float)($r['monto_total'] ?? 0), 2);

                echo '<tr class="fexsol-row" role="button" tabindex="0" data-row="' . htmlspecialchars(json_encode($r), ENT_QUOTES) . '" onclick="fexsolAbrirSolicitud(this)">';
                echo '<td class="ps-3 text-nowrap" data-col="created_at"><small>' . $fecha . '</small></td>';
                echo '<td class="fw-medium" data-col="nombre_cliente">' . htmlspecialchars($r['nombre_cliente'] ?? '') . '</td>';
                echo '<td data-col="identificacion"><small class="text-muted">' . htmlspecialchars($r['identificacion'] ?? '') . '</small></td>';
                echo '<td data-col="correo_cliente"><small>' . htmlspecialchars($r['correo_cliente'] ?? '—') . '</small></td>';
                echo '<td data-col="nombre_plantilla"><small class="text-muted">' . htmlspecialchars($r['nombre_plantilla'] ?? '—') . '</small></td>';
                echo '<td class="text-end fw-bold" data-col="monto_total">$' . $monto . '</td>';
                echo '<td class="text-center pe-3" data-col="estado"><span class="badge bg-' . $cls . ' bg-opacity-10 text-' . $cls . ' border border-' . $cls . ' border-opacity-25">' . $lbl . '</span></td>';
                echo '</tr>';
            }
            $rowsHtml = ob_get_clean();

            ob_start();
            echo '<button type="button" class="btn btn-outline-secondary btn-sm" onclick="fexsolCambiarPagina(' . ($page - 1) . ')" ' . ($page <= 1 ? 'disabled' : '') . '><i class="bi bi-chevron-left"></i></button>';
            echo '<button type="button" class="btn btn-outline-secondary btn-sm" onclick="fexsolCambiarPagina(' . ($page + 1) . ')" ' . ($page >= $totalPages ? 'disabled' : '') . '><i class="bi bi-chevron-right"></i></button>';
            $paginHtml = ob_get_clean();

            $urlExportBase = rtrim(BASE_URL, '/') . '/modulos/factura-express-solicitudes';
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
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ── Exportación del listado (PDF / Excel) ─────────────────────────────────

    /**
     * Solicitudes a exportar: las mismas del listado (búsqueda, filtros del modal y
     * orden de pantalla), sin paginar.
     */
    private function filasExportacion(): array
    {
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim((string) ($_GET['b'] ?? ''));
        $ordenCol  = trim((string) ($_GET['sort'] ?? 'created_at'));
        $ordenDir  = strtoupper(trim((string) ($_GET['dir'] ?? 'DESC')));

        $result = $this->service->getListadoSolicitudes($idEmpresa, $buscar, '', 1, 0, $ordenCol, $ordenDir);
        return [$result['rows'], $buscar];
    }

    private static function fechaExport(?string $f): string
    {
        return !empty($f) ? date('d-m-Y H:i:s', strtotime($f)) : '-';
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
            $esc = static fn ($v): string => htmlspecialchars((string) $v);
            $txt = static fn (string $k, float $w) => static fn (array $r): string
                => \App\Helpers\ReportePdf::texto((string) ($r[$k] ?? '') ?: '-', \App\Helpers\ReportePdf::anchoPt($w, true), $pt);

            $cols = [
                ['lbl' => 'Fecha',          'w' => 12, 'cls' => 'text-center', 'val' => static fn (array $r): string => self::fechaExport($r['created_at'] ?? null)],
                ['lbl' => 'Cliente',        'w' => 21, 'cls' => '', 'val' => $txt('nombre_cliente', 21)],
                ['lbl' => 'Identificación', 'w' => 11, 'cls' => '', 'val' => $txt('identificacion', 11)],
                ['lbl' => 'Correo',         'w' => 18, 'cls' => '', 'val' => $txt('correo_cliente', 18)],
                ['lbl' => 'Teléfono',       'w' => 9,  'cls' => '', 'val' => $txt('telefono_cliente', 9)],
                ['lbl' => 'Plantilla',      'w' => 12, 'cls' => '', 'val' => $txt('nombre_plantilla', 12)],
                ['lbl' => 'Monto',          'w' => 9,  'cls' => 'text-end',
                 'val' => static fn (array $r): string => number_format((float) ($r['monto_total'] ?? 0), 2),
                 'tot' => static fn (array $f): float => array_sum(array_map(static fn ($r) => (float) ($r['monto_total'] ?? 0), $f))],
                ['lbl' => 'Estado',         'w' => 8,  'cls' => 'text-center', 'val' => static fn (array $r): string => $esc(ucfirst((string) ($r['estado'] ?? '')))],
            ];

            $porEstado = array_count_values(array_map(static fn ($r) => (string) ($r['estado'] ?? ''), $rows));
            $n         = count($rows);
            $empresa   = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
            $cabecera  = \App\Helpers\ReportePdf::encabezado($idEmpresa, (string) ($empresa['nombre'] ?? ''), 'Solicitudes Factura Express')
                . ($buscar !== '' ? \App\Helpers\ReportePdf::filtros(['Búsqueda y filtros' => $buscar]) : '')
                . \App\Helpers\ReportePdf::indicadores([
                    ['Solicitudes', number_format($n)],
                    ['Pendientes', number_format($porEstado['pendiente'] ?? 0), false, '#997404'],
                    ['Facturadas', number_format($porEstado['facturada'] ?? 0), false, '#146c43'],
                    ['Rechazadas', number_format($porEstado['rechazada'] ?? 0), false, '#b02a37'],
                    ['Monto total', '$' . number_format(array_sum(array_map(static fn ($r) => (float) ($r['monto_total'] ?? 0), $rows)), 2), true],
                ]);

            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            \App\Helpers\ReportePdf::escribir($html2pdf, $pt, $cabecera, $cols, $rows,
                "TOTALES ({$n} " . ($n === 1 ? 'solicitud' : 'solicitudes') . ')',
                'Sin solicitudes para los filtros aplicados.');
            $html2pdf->output('Solicitudes_Factura_Express_' . date('Ymd_His') . '.pdf', 'D');
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

            $headers = ['Fecha', 'Cliente', 'Tipo Id.', 'Identificación', 'Correo', 'Teléfono', 'Plantilla', 'Monto', 'Estado', 'Procesado por', 'Fecha de proceso', 'Nota'];
            $data = [];
            foreach ($rows as $r) {
                $data[] = [
                    self::fechaExport($r['created_at'] ?? null),
                    (string) ($r['nombre_cliente'] ?? ''),
                    (string) ($r['tipo_identificacion'] ?? ''),
                    (string) ($r['identificacion'] ?? ''),
                    (string) ($r['correo_cliente'] ?? ''),
                    (string) ($r['telefono_cliente'] ?? ''),
                    (string) ($r['nombre_plantilla'] ?? ''),
                    round((float) ($r['monto_total'] ?? 0), 2),
                    ucfirst((string) ($r['estado'] ?? '')),
                    (string) ($r['aprobado_por_nombre'] ?? ''),
                    !empty($r['aprobado_at']) ? self::fechaExport($r['aprobado_at']) : '',
                    (string) ($r['nota_aprobacion'] ?? ''),
                ];
            }

            $empresa = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
            $info    = $buscar !== '' ? ['Búsqueda y filtros' => $buscar] : [];
            (new \App\Services\ReportService())->exportToExcel(
                'Solicitudes_Factura_Express', $headers, $data, 'Solicitudes', (string) ($empresa['nombre'] ?? ''), $info
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

    // ── AJAX: datos de solicitudes en JSON (para el panel móvil) ──────────────
    public function panelAjax(): void
    {
        $this->requireLeer();
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');

        try {
            $idEmpresa   = (int) $_SESSION['id_empresa'];
            $buscar      = trim($_GET['b'] ?? '');
            $estado      = trim($_GET['estado'] ?? 'pendiente');
            $page        = max(1, (int) ($_GET['page'] ?? 1));
            $perPage     = 20;
            $idPlantilla = (int) ($_GET['id_plantilla'] ?? 0) ?: null;

            $result = $this->service->getListadoSolicitudes($idEmpresa, $buscar, $estado, $page, $perPage, 'created_at', 'DESC', $idPlantilla);
            $total  = $result['total'];

            echo json_encode([
                'ok'    => true,
                'rows'  => $result['rows'],
                'total' => $total,
                'page'  => $page,
                'pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ── AJAX: buscador de productos (para editar ítems al aprobar) ────────────
    public function getProductosAjax(): void
    {
        $this->requireLeer();
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $buscar    = trim($_GET['q'] ?? '');

            $repo   = new \App\repositories\modulos\ProductoRepository();
            $result = $repo->getListado($idEmpresa, $buscar, 1, 15, 'nombre', 'ASC', null, 'venta', true);

            echo json_encode(['ok' => true, 'data' => $result['rows']]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'data' => [], 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ── Aprobar solicitud y facturar ──────────────────────────────────────────
    public function aprobar(): void
    {
        $this->requireActualizar();

        // Evitar que cualquier warning/notice previo corrompa el JSON de respuesta
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');

        try {
            $id        = (int) ($_POST['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];
            $items     = json_decode($_POST['items_json'] ?? '[]', true) ?: [];

            $this->service->aprobarYFacturar($id, $idEmpresa, $idUsuario, ['items' => $items]);
            echo json_encode(['ok' => true, 'mensaje' => 'Solicitud aprobada y factura generada correctamente.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    // ── AJAX: contador de solicitudes pendientes (para badge del navbar) ─────
    public function countPendientesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $db        = \App\core\Database::getConnection();
            $sql       = "SELECT COUNT(*) FROM factura_express_solicitudes
                          WHERE id_empresa = :id_empresa AND estado = 'pendiente' AND eliminado = false";
            $st = $db->prepare($sql);
            $st->execute([':id_empresa' => $idEmpresa]);
            echo json_encode(['ok' => true, 'count' => (int) $st->fetchColumn()]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'count' => 0]);
        }
        exit;
    }

    // ── Rechazar solicitud ────────────────────────────────────────────────────
    public function rechazar(): void
    {
        $this->requireActualizar();

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');

        try {
            $id        = (int) ($_POST['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];
            $nota      = trim($_POST['nota'] ?? '');

            $this->service->rechazarSolicitud($id, $idEmpresa, $idUsuario, $nota);
            echo json_encode(['ok' => true, 'mensaje' => 'Solicitud rechazada.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }
}
