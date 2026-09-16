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

        $repository = new CambioProductoCvRepository();
        $rules      = new CambioProductoCvRules();
        $logService = new LogSistemaService();
        $this->service = new CambioProductoCvService($repository, $rules, $logService);
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    /**
     * Cabecera del cambio de la empresa activa, cortando con 403 si el usuario
     * no tiene acceso total y el documento lo creó otro (mismo criterio que el
     * listado). Para las acciones que reciben un id suelto.
     */
    private function docPropioOCortar(int $id): ?array
    {
        $doc = $this->service->getPorId($id, (int) $_SESSION['id_empresa']);
        $this->requireRegistroPropio($doc);
        return $doc;
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

        // Series REALMENTE usadas en cambios de productos guardados, para el
        // filtro "Serie" del buscador — a diferencia de $puntos (solo sirve para
        // elegir la serie de un cambio NUEVO), esto incluye series de cualquier
        // establecimiento y aunque el punto ya no tenga secuencial configurado.
        $seriesFiltro = (new CambioProductoCvRepository())->getSeriesDistintas($idEmpresa);

        $this->viewWithLayout('layouts.main', 'modulos.cambio_producto_cv.index', [
            'titulo'       => 'Cambios de productos',
            'perm'         => $perm,
            'rutaModulo'   => self::RUTA_MODULO,
            'empresa'      => $empresaData,
            'puntos'       => $puntos,
            'seriesFiltro' => $seriesFiltro,
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
                        <td data-col="secuencial">' . htmlspecialchars(($r['serie'] ?? '') . '-' . ($r['secuencial'] ?? '')) . '</td>
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

    /** Filas del listado con el filtro/orden actual, sin paginar (para exportar). */
    private function filasParaExport(): array
    {
        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $buscar     = trim($_GET['b'] ?? '');
        $ordenCol   = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_cambio');
        $ordenDir   = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        // perPage = 0 => sin LIMIT (todas las filas que calcen con el filtro actual).
        $data = $this->service->getListado($idEmpresa, $buscar, 1, 0, $ordenCol, $ordenDir, $idUsuarioFiltro);
        return $data['rows'] ?? [];
    }

    /** Exporta el listado (con el filtro/orden actual del buscador) a PDF. */
    public function exportPdf(): void
    {
        $this->requireLeer();
        $rows = $this->filasParaExport();

        try {
            $empresaModel  = new \App\models\Empresa();
            $empresa       = $empresaModel->getPorId((int) $_SESSION['id_empresa']);
            $nombreEmpresa = $empresa['nombre'] ?? '';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            ob_start();
            ?>
            <style>
                table { width:100%; border-collapse:collapse; font-family:Arial,sans-serif; font-size:7pt; }
                th { background:#f2f2f2; border:1px solid #ccc; padding:3px; text-align:left; }
                td { border:1px solid #ccc; padding:3px; }
                .r { text-align:right; }
                h2 { font-family:Arial,sans-serif; font-size:12pt; margin:0 0 2px 0; }
                .sub { font-family:Arial,sans-serif; font-size:8pt; color:#555; margin-bottom:6px; }
            </style>
            <page backtop="8mm" backbottom="8mm" backleft="6mm" backright="6mm">
                <h2><?= htmlspecialchars($nombreEmpresa) ?></h2>
                <div class="sub">Cambios de Producto de Consignaciones &mdash; <?= date('d-m-Y H:i:s') ?></div>
                <table>
                    <thead>
                        <tr>
                            <th style="width:10%">Fecha</th>
                            <th style="width:13%">Secuencial</th>
                            <th style="width:25%">Cliente</th>
                            <th style="width:12%">Identificación</th>
                            <th style="width:20%">Motivo</th>
                            <th style="width:10%" class="r">Diferencia</th>
                            <th style="width:10%">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r):
                        $numero = ($r['serie'] ?? '') . '-' . ($r['secuencial'] ?? '');
                    ?>
                        <tr>
                            <td><?= !empty($r['fecha_cambio']) ? date('d-m-Y', strtotime($r['fecha_cambio'])) : '-' ?></td>
                            <td><?= htmlspecialchars($numero) ?></td>
                            <td><?= htmlspecialchars((string) ($r['cliente_nombre'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($r['cliente_identificacion'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($r['motivo'] ?? '-')) ?></td>
                            <td class="r"><?= number_format((float) ($r['diferencia'] ?? 0), 2) ?></td>
                            <td><?= ucfirst((string) ($r['estado'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </page>
            <?php
            $content = ob_get_clean();

            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $html2pdf->writeHTML($content);
            $html2pdf->output('Cambios_producto_cv_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo 'Error al generar PDF: ' . $e->getMessage();
            exit;
        }
    }

    /** Exporta el listado (con el filtro/orden actual del buscador) a Excel. */
    public function exportExcel(): void
    {
        $this->requireLeer();
        $rows = $this->filasParaExport();

        try {
            $empresaModel  = new \App\models\Empresa();
            $empresa       = $empresaModel->getPorId((int) $_SESSION['id_empresa']);
            $nombreEmpresa = $empresa['nombre'] ?? '';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            $headers = ['Fecha', 'Secuencial', 'Cliente', 'Identificación', 'Motivo', 'Diferencia', 'Estado'];

            $exportData = [];
            foreach ($rows as $r) {
                $numero = ($r['serie'] ?? '') . '-' . ($r['secuencial'] ?? '');
                $exportData[] = [
                    !empty($r['fecha_cambio']) ? date('d-m-Y', strtotime($r['fecha_cambio'])) : '-',
                    $numero,
                    (string) ($r['cliente_nombre'] ?? ''),
                    (string) ($r['cliente_identificacion'] ?? ''),
                    (string) ($r['motivo'] ?? '-'),
                    number_format((float) ($r['diferencia'] ?? 0), 2, '.', ''),
                    ucfirst((string) ($r['estado'] ?? '')),
                ];
            }

            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcel('Cambios_producto_cv', $headers, $exportData, 'Cambios de Producto de Consignaciones', $nombreEmpresa);
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo 'Error al generar Excel: ' . $e->getMessage();
            exit;
        }
    }

    public function store(): void
    {
        // El body se lee ANTES del guard porque el permiso depende de la operación:
        // crear un documento nuevo exige 'w', pero editar uno existente exige solo 'u'
        // (mismo criterio que Ingresos y Facturas de Venta). Cuando esto empezaba con
        // requireCrear() incondicional, el permiso "Actualizar" no servía por sí solo:
        // quien tenía 'u' sin 'w' recibía 403 al guardar cualquier cambio.
        $input = json_decode(file_get_contents('php://input'), true);
        if (!empty($input['id'])) {
            $this->requireActualizar();
        } else {
            $this->requireCrear();
        }
        header('Content-Type: application/json');

        try {
            if (!$input) {
                throw new Exception("Datos no recibidos.");
            }

            $input['id_empresa'] = (int) $_SESSION['id_empresa'];
            $input['id_usuario'] = (int) $_SESSION['id_usuario'];
            $input['empresa_config'] = $this->getEmpresaConfig($input['id_empresa']);

            if (!empty($input['id'])) {
                $this->docPropioOCortar((int) $input['id']);
                $this->service->actualizar((int) $input['id'], $input['id_empresa'], $input);
                echo json_encode(['ok' => true, 'msg' => 'Cambio actualizado correctamente.']);
            } else {
                // El número lo asigna el servidor al guardar (no el que se vio al abrir el
                // modal), así que se informa cuál quedó.
                $id     = $this->service->crear($input);
                $numero = $this->service->getUltimoNumeroGenerado();
                echo json_encode([
                    'ok'     => true,
                    'msg'    => 'Cambio ' . ($numero ?? '') . ' registrado correctamente. El inventario ha sido actualizado.',
                    'id'     => $id,
                    'numero' => $numero,
                ]);
            }
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
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
            $this->docPropioOCortar($id);

            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            $this->service->eliminar($id, $idEmpresa, $idUsuario, $this->getEmpresaConfig($idEmpresa));
            echo json_encode(['ok' => true, 'msg' => 'Cambio eliminado. El inventario ha sido reversado.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
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
            $this->docPropioOCortar($id);

            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            $this->service->cambiarEstado($id, $idEmpresa, $idUsuario, $estado, $this->getEmpresaConfig($idEmpresa));
            echo json_encode(['ok' => true, 'msg' => 'Estado actualizado a ' . $estado . '.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
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
            $this->docPropioOCortar($id);
            $data = $this->service->getDetalleCompleto($id, $idEmpresa);
            if (!$data) throw new Exception("Cambio no encontrado.");
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
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
            $this->requireRegistroPropio($cab ?: null);
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
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
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
            $this->docPropioOCortar($id);
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

    /** Genera el Excel del cambio (mismas secciones que el PDF: Devuelve / Entrega). */
    public function excel(): void
    {
        $this->requireLeer();

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        if (!$id) { http_response_code(400); echo 'ID requerido'; exit; }

        try {
            $this->docPropioOCortar($id);
            $cambio = $this->service->getDetalleCompleto($id, $idEmpresa);
            if (!$cambio) { http_response_code(404); echo 'Cambio no encontrado'; exit; }

            $detalles     = $cambio['detalles'] ?? [];
            $devoluciones = array_values(array_filter($detalles, fn($d) => ($d['tipo_linea'] ?? '') === 'devolucion'));
            $entregas     = array_values(array_filter($detalles, fn($d) => ($d['tipo_linea'] ?? '') === 'entrega'));

            $empresaModel = new \App\models\Empresa();
            $empresa      = $empresaModel->getPorId($idEmpresa) ?? [];

            $numero = trim((string)($cambio['serie'] ?? '') . '-' . (string)($cambio['secuencial'] ?? ''), '-');

            require_once MVC_ROOT . '/vendor/autoload.php';

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Cambio');

            $sheet->setCellValue('A1', strtoupper((string)($empresa['nombre'] ?? '')));
            $sheet->mergeCells('A1:F1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

            $sheet->setCellValue('A2', 'CAMBIO DE PRODUCTOS N.° ' . ($numero !== '' ? $numero : '—'));
            $sheet->mergeCells('A2:F2');
            $sheet->getStyle('A2')->getFont()->setBold(true);

            $fecha = !empty($cambio['fecha_cambio']) ? date('d-m-Y', strtotime((string)$cambio['fecha_cambio'])) : '';
            $sheet->setCellValue('A3', 'Fecha: ' . $fecha);
            $sheet->setCellValue('C3', 'Cliente: ' . (string)($cambio['cliente_nombre'] ?? ''));
            $sheet->setCellValue('A4', 'Identificación: ' . (string)($cambio['cliente_identificacion'] ?? ''));
            $sheet->setCellValue('C4', 'Estado: ' . ucfirst((string)($cambio['estado'] ?? '')));

            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '3C465A']],
            ];

            $row = 6;
            $escribirTabla = function (string $titulo, array $filas) use ($sheet, $headerStyle, &$row) {
                $sheet->setCellValue('A' . $row, $titulo);
                $sheet->getStyle('A' . $row)->getFont()->setBold(true);
                $row++;

                // Origen (factura / cambio / consignación de la que sale la línea) y NUP:
                // el cambio se hace por unidad, así que el documento debe decir cuál.
                $headers = ['Origen', 'Código', 'Descripción', 'Lote', 'NUP', 'Cantidad', 'P. Unitario', 'Total'];
                $col = 'A';
                foreach ($headers as $h) { $sheet->setCellValue($col . $row, $h); $col++; }
                $sheet->getStyle('A' . $row . ':H' . $row)->applyFromArray($headerStyle);
                $row++;

                $inicio = $row;
                if (empty($filas)) {
                    $sheet->setCellValue('A' . $row, 'Sin productos.');
                    $sheet->mergeCells('A' . $row . ':H' . $row);
                    $row++;
                } else {
                    foreach ($filas as $d) {
                        $sheet->setCellValueExplicit('A' . $row, \App\Services\modulos\CambioProductoCvPdfService::etiquetaOrigen($d), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $sheet->setCellValueExplicit('B' . $row, (string)($d['producto_codigo'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $sheet->setCellValueExplicit('C' . $row, (string)($d['producto_nombre'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $sheet->setCellValueExplicit('D' . $row, (string)($d['lote'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $sheet->setCellValueExplicit('E' . $row, (string)($d['nup'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $sheet->setCellValue('F' . $row, (float)($d['cantidad'] ?? 0));
                        $sheet->setCellValue('G' . $row, (float)($d['precio_unitario'] ?? 0));
                        $sheet->setCellValue('H' . $row, (float)($d['total'] ?? 0));
                        $row++;
                    }
                }
                $sheet->getStyle('F' . $inicio . ':H' . ($row - 1))->getNumberFormat()->setFormatCode('#,##0.00');
                $row++;
            };

            $escribirTabla('Productos que devuelve', $devoluciones);
            $escribirTabla('Productos que entrega a cambio', $entregas);

            $totales = [
                'Total devuelto'  => (float)($cambio['subtotal_devuelto'] ?? 0),
                'Total entregado' => (float)($cambio['subtotal_entregado'] ?? 0),
                'Diferencia'      => (float)($cambio['diferencia'] ?? 0),
            ];
            foreach ($totales as $label => $valor) {
                $sheet->setCellValue('E' . $row, $label);
                $sheet->getStyle('E' . $row)->getFont()->setBold(true);
                $sheet->setCellValue('F' . $row, $valor);
                $sheet->getStyle('F' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                $row++;
            }

            $row++;
            $sheet->setCellValue('A' . $row, 'Motivo: ' . (string)($cambio['motivo'] ?? ''));
            $sheet->mergeCells('A' . $row . ':F' . $row);
            $row++;
            $sheet->setCellValue('A' . $row, 'Observaciones: ' . (string)($cambio['observaciones'] ?? ''));
            $sheet->mergeCells('A' . $row . ':F' . $row);

            foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $c) {
                $sheet->getColumnDimension($c)->setAutoSize(true);
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $nombre = 'Cambio_' . ($numero !== '' ? $numero : 'comprobante') . '.xlsx';

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $nombre . '"');
            header('Cache-Control: max-age=0');
            $writer->save('php://output');
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            http_response_code(500); echo 'Error al generar Excel: ' . $e->getMessage();
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
            $this->docPropioOCortar($id);
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
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
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
            // Búsqueda estándar del sistema: todas las palabras en cualquier orden y
            // sin distinguir tildes (ClienteRepository::buscarAutocomplete). Aquí se
            // buscan también los clientes inactivos: el cambio puede venir de una
            // factura antigua de un cliente que ya se dio de baja.
            $rows = (new \App\repositories\modulos\ClienteRepository())
                ->buscarAutocomplete($idEmpresa, $q, 15, false);
            echo json_encode(['ok' => true, 'data' => $rows]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Líneas disponibles para devolver (factura + cambios previos, con saldo), una por ítem.
     * Con `id_cliente` acota a ese cliente (y admite `q` vacío: todo lo pendiente del cliente);
     * sin cliente busca entre todos por NUP, lote, número de documento o producto, y el
     * navegador fija el cliente del cambio con el de la línea que se agregue.
     */
    public function buscarLineasOrigenAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idCliente = (int) ($_GET['id_cliente'] ?? 0);
            $q         = trim($_GET['q'] ?? '');
            $excluir   = (int) ($_GET['excluir'] ?? 0);
            if ($idCliente <= 0 && $q === '') throw new Exception("Indique un NUP, un número de documento o un producto, o seleccione el cliente.");

            $rows = $this->service->getLineasDisponiblesCliente($idEmpresa, $idCliente > 0 ? $idCliente : null, $q, $excluir > 0 ? $excluir : null);
            echo json_encode(['ok' => true, 'data' => $rows]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Qué se puede ENTREGAR a cambio, en una sola respuesta con tres grupos:
     *  - `consignaciones`: líneas de consignaciones Entregadas con saldo en poder del
     *    cliente (por número de consignación, cliente, NUP, lote o producto);
     *  - `inventario`: existencias por bodega / lote / NUP que coinciden con la búsqueda;
     *  - `catalogo`: productos del catálogo (bienes), aunque no tengan stock registrado.
     * Con `id_cliente` las consignaciones se acotan a ese cliente.
     */
    public function buscarEntregasAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idCliente = (int) ($_GET['id_cliente'] ?? 0);
            $q         = trim($_GET['q'] ?? '');
            $excluir   = (int) ($_GET['excluir'] ?? 0);
            if ($idCliente <= 0 && $q === '') throw new Exception("Indique un número de consignación, un NUP, un lote o un producto.");

            $consignaciones = $this->service->getLineasConsignacionDisponibles($idEmpresa, $q, $idCliente > 0 ? $idCliente : null, $excluir > 0 ? $excluir : null);

            $inventario = [];
            $catalogo   = [];
            if ($q !== '') {
                $inventario = $this->service->buscarInventario($idEmpresa, $q, 30);

                $repo = new ProductoRepository();
                $res  = $repo->getListado($idEmpresa, $q, 1, 15, 'nombre', 'ASC', null, null, true);
                foreach (($res['rows'] ?? []) as $p) {
                    // Solo bienes/productos, no servicios.
                    if ((string)($p['tipo_produccion'] ?? '01') === '02') continue;
                    $catalogo[] = [
                        'id'              => (int) $p['id'],
                        'codigo'          => $p['codigo'] ?? '',
                        'nombre'          => $p['nombre'] ?? '',
                        'inventariable'   => $p['inventariable'] ?? null,
                        'tipo_produccion' => $p['tipo_produccion'] ?? '01',
                    ];
                }
            }

            echo json_encode(['ok' => true, 'consignaciones' => $consignaciones, 'inventario' => $inventario, 'catalogo' => $catalogo]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
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
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
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
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
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
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
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
        // Fecha del documento: solo pesa si este tipo numera por fecha de emisión
        // (Empresa → Secuenciales); en modo consecutivo el servidor la ignora.
        $fecha = trim($_GET['fecha'] ?? '') ?: null;
        $res = $secuencialService->obtenerSiguienteSecuencial($idPunto, self::TIPO_SECUENCIAL, $fecha);
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
