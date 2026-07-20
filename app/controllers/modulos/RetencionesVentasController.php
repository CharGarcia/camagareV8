<?php
declare(strict_types=1);

namespace App\controllers\modulos;

use App\Services\modulos\RetencionVentaService;
use App\repositories\modulos\RetencionVentaRepository;
use App\Rules\modulos\RetencionVentaRules;
use App\Services\LogSistemaService;
use App\models\Empresa;

class RetencionesVentasController extends BaseModuloController
{
    private RetencionVentaService    $service;
    private RetencionVentaRepository $repository;

    protected function getRutaModulo(): string
    {
        return 'modulos/retenciones_ventas';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new RetencionVentaRepository();
        $this->service    = new RetencionVentaService(
            $this->repository,
            new RetencionVentaRules(),
            new LogSistemaService()
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INDEX
    // ─────────────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        $buscar   = trim($_GET['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_emision');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage  = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $result     = $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $total      = $result['total'];
        $totalPages = (int) ceil($total / $perPage);

        $this->viewWithLayout('layouts.main', 'modulos/retenciones_ventas/index', [
            'titulo'      => 'Retenciones en Ventas',
            'perm'        => $perm,
            'rows'        => $result['rows'],
            'total'       => $total,
            'page'        => $page,
            'totalPages'  => $totalPages,
            'perPage'     => $perPage,
            'from'        => $total > 0 ? (($page - 1) * $perPage) + 1 : 0,
            'to'          => $total > 0 ? min($page * $perPage, $total) : 0,
            'buscar'      => $buscar,
            'ordenCol'    => $ordenCol,
            'ordenDir'    => $ordenDir,
            'vistaConfig' => $prefsVista,
            'base'        => BASE_URL,
            'rutaModulo'  => $this->getRutaModulo(),
            'fullWidth'   => true,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX — búsqueda / paginación
    // ─────────────────────────────────────────────────────────────────────────

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa  = (int) $_SESSION['id_empresa'];
            $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
            $buscar     = trim($_GET['b'] ?? '');
            $page       = max(1, (int) ($_GET['page'] ?? 1));
            $ordenCol   = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_emision');
            $ordenDir   = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
            $perPage    = 20;

            $perm = $this->getPermisos();
            $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

            $result     = $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
            $rows       = $result['rows'];
            $total      = $result['total'];
            $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
            $from       = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
            $to         = $total > 0 ? min($page * $perPage, $total) : 0;

            ob_start();
            if (empty($rows)) {
                echo '<tr><td colspan="10" class="text-center py-5 text-muted"><i class="fa-regular fa-file-lines fs-3 d-block mb-2"></i>No se encontraron retenciones.</td></tr>';
            } else {
                foreach ($rows as $r) {
                    $rowData = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                    $numero  = ($r['establecimiento'] ?? '') . '-' . ($r['punto_emision'] ?? '') . '-' . ($r['secuencial'] ?? '');
                    $fecha   = !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '—';
                    $origen  = ($r['origen'] ?? 'manual') === 'electronico'
                        ? '<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">Electrónico</span>'
                        : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Manual</span>';

                    echo '<tr class="retv-row" role="button" tabindex="0" data-row=\'' . $rowData . '\' onclick="window.RETV_abrirModal(this)">
                            <td class="ps-3"><code>' . htmlspecialchars($numero) . '</code></td>
                            <td>' . $fecha . '</td>
                            <td class="fw-medium text-truncate" style="max-width:200px">' . htmlspecialchars($r['cliente_nombre'] ?? '—') . '</td>
                            <td><small class="text-muted">' . htmlspecialchars($r['cliente_ruc'] ?? '—') . '</small></td>
                            <td><small>' . htmlspecialchars($r['periodo_fiscal'] ?? '—') . '</small></td>
                            <td class="text-end">$' . number_format((float)($r['total_renta'] ?? 0), 2) . '</td>
                            <td class="text-end">$' . number_format((float)($r['total_iva'] ?? 0), 2) . '</td>
                            <td class="text-end">$' . number_format((float)($r['total_isd'] ?? 0), 2) . '</td>
                            <td class="text-end fw-bold">$' . number_format((float)($r['total_retenido'] ?? 0), 2) . '</td>
                            <td class="text-center pe-3">' . $origen . '</td>
                          </tr>';
                }
            }
            $rowsHtml = ob_get_clean();

            ob_start();
            $prevDisabled = ($page <= 1)           ? 'disabled' : '';
            $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
            echo '<button type="button" class="btn btn-outline-secondary btn-sm" ' . $prevDisabled . ' onclick="window.RETV_cambiarPagina(' . ($page - 1) . ')"><i class="fa-solid fa-chevron-left"></i></button>
                  <button type="button" class="btn btn-outline-secondary btn-sm" ' . $nextDisabled . ' onclick="window.RETV_cambiarPagina(' . ($page + 1) . ')"><i class="fa-solid fa-chevron-right"></i></button>';
            $paginationHtml = ob_get_clean();

            echo json_encode([
                'ok'         => true,
                'rows'       => $rowsHtml,
                'pagination' => $paginationHtml,
                'info'       => "$from-$to/$total",
                'total'      => $total,
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => 'Error en el listado: ' . $e->getMessage()]);
        }
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX — obtener una retención
    // ─────────────────────────────────────────────────────────────────────────

    public function getByIdAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $id        = (int) ($_GET['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];

            if (!$id) {
                echo json_encode(['ok' => false, 'mensaje' => 'ID requerido']);
                exit;
            }

            $cabecera = $this->repository->getPorId($id, $idEmpresa);
            if (!$cabecera) {
                echo json_encode(['ok' => false, 'mensaje' => 'Retención no encontrada']);
                exit;
            }

            $lineas = $this->repository->getDetalle($id);
            echo json_encode(['ok' => true, 'cabecera' => $cabecera, 'lineas' => $lineas]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX — asiento contable de la retención (pestaña Asiento Contable)
    // ─────────────────────────────────────────────────────────────────────────

    public function getAsientoContableAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $id        = (int) ($_GET['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];

            if (!$id) {
                echo json_encode(['ok' => false, 'mensaje' => 'ID requerido']);
                exit;
            }

            $cabecera = $this->repository->getPorId($id, $idEmpresa);
            if (!$cabecera) {
                echo json_encode(['ok' => false, 'mensaje' => 'Retención no encontrada']);
                exit;
            }

            $idAsiento = (int) ($cabecera['id_asiento_contable'] ?? 0);
            $asiento   = $idAsiento > 0 ? $this->service->getAsientoRegistrado($idAsiento, $idEmpresa) : [];

            // Si no hay asiento vigente (no existe o está anulado), generarlo y enlazarlo ahora.
            if (empty($asiento) || ($asiento['estado'] ?? '') === 'anulado') {
                try {
                    $dataAsiento = $cabecera;
                    $dataAsiento['id_usuario'] = (int) $_SESSION['id_usuario'];
                    $this->service->procesarAsientoContable($id, $dataAsiento);

                    $cabecera  = $this->repository->getPorId($id, $idEmpresa);
                    $idAsiento = (int) ($cabecera['id_asiento_contable'] ?? 0);
                    $asiento   = $idAsiento > 0 ? $this->service->getAsientoRegistrado($idAsiento, $idEmpresa) : [];
                } catch (\Throwable $e) {
                    error_log('[RetencionVenta] Asiento no generado al consultar: ' . $e->getMessage());
                }
            }

            // Mostrar el asiento REGISTRADO (relación por id_asiento_contable).
            if (!empty($asiento)) {
                $detalles = array_map(static fn($d) => [
                    'cuenta_codigo' => $d['codigo_cuenta'] ?? '',
                    'cuenta_nombre' => $d['nombre_cuenta'] ?? '',
                    'debe'          => (float) ($d['debe'] ?? 0),
                    'haber'         => (float) ($d['haber'] ?? 0),
                ], $asiento['detalles'] ?? []);

                echo json_encode([
                    'ok'         => true,
                    'registrado' => true,
                    'numero'     => $asiento['numero_comprobante'] ?? '',
                    'estado'     => $asiento['estado'] ?? '',
                    'detalles'   => $detalles,
                ]);
                exit;
            }

            // No se pudo registrar (faltan cuentas / descuadre): mostrar el sugerido como referencia.
            $detalles = $this->service->obtenerAsientoSugerido($idEmpresa, $id);
            echo json_encode(['ok' => true, 'registrado' => false, 'detalles' => $detalles]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX — descargar XML de la retención
    // ─────────────────────────────────────────────────────────────────────────

    public function descargarXmlAjax(): void
    {
        $this->requireLeer();

        try {
            $id        = (int) ($_GET['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];

            if (!$id) {
                http_response_code(400);
                echo 'ID requerido'; exit;
            }

            $cabecera = $this->repository->getPorId($id, $idEmpresa);
            if (!$cabecera) {
                http_response_code(404);
                echo 'Retención no encontrada'; exit;
            }

            $xml = $cabecera['detalle_xml'] ?? '';
            if (empty($xml)) {
                http_response_code(404);
                echo 'Este registro no tiene XML almacenado.'; exit;
            }

            $numDoc = trim(($cabecera['establecimiento'] ?? '001') . '-'
                        . ($cabecera['punto_emision']   ?? '001') . '-'
                        . ($cabecera['secuencial']      ?? '000000001'));
            $filename = 'retencion_venta_' . $numDoc . '.xml';

            header('Content-Type: application/xml; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($xml));
            header('Cache-Control: no-cache');
            echo $xml;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo 'Error: ' . $e->getMessage();
        }
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX — exportar PDF del comprobante
    // ─────────────────────────────────────────────────────────────────────────

    public function exportPdfDoc(): void
    {
        $this->requireLeer();
        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        try {
            $cabecera = $this->repository->getPorId($id, $idEmpresa);
            if (!$cabecera) {
                die('Retención no encontrada');
            }

            $pdfService = new \App\Services\modulos\RetencionVentaPdfService();

            // Preferir el XML autorizado (documento oficial). Si no hay XML
            // (retención manual), armar el PDF con los datos de BD.
            $xml = trim($cabecera['detalle_xml'] ?? '');
            if ($xml !== '') {
                $pdfService->generarDesdeXml($xml);
            } else {
                $lineas  = $this->repository->getDetalle($id);
                $empresa = (new Empresa())->getPorId($idEmpresa) ?? [];
                $pdfService->generar($cabecera, $lineas, $empresa);
            }
        } catch (\Throwable $e) {
            die('Error al generar PDF: ' . $e->getMessage());
        }
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EXPORTAR LISTADO — PDF / Excel
    // ─────────────────────────────────────────────────────────────────────────

    /** Filas del listado completo (sin paginar) aplicando búsqueda y orden actuales. */
    private function filasParaExport(): array
    {
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? '');
        $ordenCol  = trim($_GET['sort'] ?? 'fecha_emision');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? 'DESC'));

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        // perPage = 0 → sin LIMIT, trae todo el listado filtrado.
        $data = $this->repository->getListado($idEmpresa, $buscar, 1, 0, $ordenCol, $ordenDir, $idUsuarioFiltro);
        return $data['rows'] ?? [];
    }

    private static function numeroRetencion(array $r): string
    {
        return ($r['establecimiento'] ?? '') . '-' . ($r['punto_emision'] ?? '') . '-' . ($r['secuencial'] ?? '');
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        try {
            $rows          = $this->filasParaExport();
            $empresa       = (new Empresa())->getPorId($idEmpresa) ?? [];
            $nombreEmpresa = $empresa['nombre'] ?? '';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            $headers = [
                'Número', 'Fecha Emisión', 'Cliente', 'RUC / Identificación', 'Período Fiscal',
                'Retenido Renta', 'Retenido IVA', 'Retenido ISD', 'Total Retenido', 'Origen',
            ];

            $exportData = [];
            foreach ($rows as $r) {
                $exportData[] = [
                    self::numeroRetencion($r),
                    !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '-',
                    (string)($r['cliente_nombre'] ?? ''),
                    (string)($r['cliente_ruc'] ?? ''),
                    (string)($r['periodo_fiscal'] ?? ''),
                    round((float)($r['total_renta'] ?? 0), 2),
                    round((float)($r['total_iva'] ?? 0), 2),
                    round((float)($r['total_isd'] ?? 0), 2),
                    round((float)($r['total_retenido'] ?? 0), 2),
                    (($r['origen'] ?? 'manual') === 'electronico' ? 'Electrónico' : 'Manual'),
                ];
            }

            (new \App\Services\ReportService())->exportToExcel(
                'Retenciones_Ventas_' . date('Ymd_His'),
                $headers,
                $exportData,
                'Retenciones Ventas',
                'Retenciones en Ventas' . ($nombreEmpresa !== '' ? ' — ' . $nombreEmpresa : '')
            );
        } catch (\Throwable $e) {
            die('Error al generar Excel: ' . $e->getMessage());
        }
        exit;
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        try {
            $rows          = $this->filasParaExport();
            $empresa       = (new Empresa())->getPorId($idEmpresa) ?? [];
            $nombreEmpresa = $empresa['nombre'] ?? 'Retenciones en Ventas';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            $tRenta = 0.0; $tIva = 0.0; $tIsd = 0.0; $tTotal = 0.0;
            foreach ($rows as $r) {
                $tRenta += (float)($r['total_renta'] ?? 0);
                $tIva   += (float)($r['total_iva'] ?? 0);
                $tIsd   += (float)($r['total_isd'] ?? 0);
                $tTotal += (float)($r['total_retenido'] ?? 0);
            }

            ob_start();
?>
            <style>
                table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 8pt; table-layout: fixed; }
                th { background: #f2f2f2; border: 1px solid #ccc; padding: 4px; text-align: left; }
                td { border: 1px solid #ccc; padding: 4px; overflow: hidden; word-wrap: break-word; }
                .num { text-align: right; }
                .center { text-align: center; }
                .header { text-align: center; margin-bottom: 8px; }
                .header h1 { font-size: 12pt; text-transform: uppercase; margin: 0; }
                .header h2 { font-size: 10pt; margin: 2px 0 0 0; }
                tfoot td { background: #f2f2f2; font-weight: bold; }
            </style>
            <page backtop="10mm" backbottom="10mm" backleft="10mm" backright="10mm">
                <div class="header">
                    <h1><?= htmlspecialchars($nombreEmpresa) ?></h1>
                    <h2>Retenciones en Ventas</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 12%">Número</th>
                            <th style="width: 9%">Fecha</th>
                            <th style="width: 22%">Cliente</th>
                            <th style="width: 11%">RUC / Ident.</th>
                            <th style="width: 8%">Período</th>
                            <th style="width: 9%" class="num">Renta</th>
                            <th style="width: 9%" class="num">IVA</th>
                            <th style="width: 8%" class="num">ISD</th>
                            <th style="width: 10%" class="num">Total Ret.</th>
                            <th style="width: 8%" class="center">Origen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="10" class="center">No se encontraron retenciones.</td></tr>
                        <?php else: foreach ($rows as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars(self::numeroRetencion($r)) ?></td>
                                <td><?= !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '-' ?></td>
                                <td><?= htmlspecialchars((string)($r['cliente_nombre'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($r['cliente_ruc'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($r['periodo_fiscal'] ?? '')) ?></td>
                                <td class="num"><?= number_format((float)($r['total_renta'] ?? 0), 2) ?></td>
                                <td class="num"><?= number_format((float)($r['total_iva'] ?? 0), 2) ?></td>
                                <td class="num"><?= number_format((float)($r['total_isd'] ?? 0), 2) ?></td>
                                <td class="num"><?= number_format((float)($r['total_retenido'] ?? 0), 2) ?></td>
                                <td class="center"><?= (($r['origen'] ?? 'manual') === 'electronico' ? 'Electrónico' : 'Manual') ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                    <?php if (!empty($rows)): ?>
                    <tfoot>
                        <tr>
                            <td colspan="5" class="num">TOTALES</td>
                            <td class="num"><?= number_format($tRenta, 2) ?></td>
                            <td class="num"><?= number_format($tIva, 2) ?></td>
                            <td class="num"><?= number_format($tIsd, 2) ?></td>
                            <td class="num"><?= number_format($tTotal, 2) ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </page>
<?php
            $content = ob_get_clean();

            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $html2pdf->writeHTML($content);
            $html2pdf->output('Retenciones_Ventas_' . date('Ymd_His') . '.pdf', 'D');
        } catch (\Throwable $e) {
            if (ob_get_length()) ob_end_clean();
            die('Error al generar PDF: ' . $e->getMessage());
        }
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX — guardar (crear/actualizar)
    // ─────────────────────────────────────────────────────────────────────────

    public function guardarAjax(): void
    {
        header('Content-Type: application/json');

        try {
            $data = isset($_POST['data']) ? json_decode($_POST['data'], true) : $_POST;

            $data['id_empresa'] = (int) $_SESSION['id_empresa'];
            $data['id_usuario'] = (int) $_SESSION['id_usuario'];

            $idExistente = !empty($data['id']) ? (int)$data['id'] : 0;

            if ($idExistente > 0) {
                $this->requireActualizar();
                $id      = $this->service->actualizar($idExistente, $data);
                $mensaje = 'Retención actualizada exitosamente.';
            } else {
                $this->requireCrear();
                $id      = $this->service->crear($data);
                $mensaje = 'Retención registrada exitosamente.';
            }

            echo json_encode(['ok' => true, 'mensaje' => $mensaje, 'id' => $id]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX — eliminar
    // ─────────────────────────────────────────────────────────────────────────

    public function eliminarAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $this->service->eliminar($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Retención eliminada correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX — catálogos
    // ─────────────────────────────────────────────────────────────────────────

    public function getRetencionesSriAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $impuesto = $_GET['impuesto'] ?? null;
            $buscar   = trim($_GET['q'] ?? '');
            $fecha    = $_GET['fecha'] ?? null;

            $data = $this->repository->getRetencionesSri($impuesto, $buscar ?: null, $fecha);
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function buscarVentasAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $buscar    = trim($_GET['q'] ?? '');

            $data = $this->repository->buscarVentasDisponibles($idEmpresa, $buscar);
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function getAuditoriaAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) {
            echo json_encode(['ok' => false, 'mensaje' => 'ID no proporcionado']);
            exit;
        }

        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $logService = new LogSistemaService();
        $logs       = $logService->getHistorial('retenciones_ventas', $id, $idEmpresa);

        echo json_encode(['ok' => true, 'data' => $logs]);
        exit;
    }

    public function getPorVentaAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idVenta   = (int) ($_GET['id_venta'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$idVenta) {
            echo json_encode(['ok' => false, 'mensaje' => 'ID de venta no válido']);
            return;
        }

        $rows = $this->repository->getPorVenta($idVenta, $idEmpresa);
        echo json_encode(['ok' => true, 'rows' => $rows]);
    }

    public function getClientesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $buscar    = trim($_GET['q'] ?? '');

            $repo   = new \App\repositories\modulos\ClienteRepository();
            // soloActivos = true: excluir clientes inactivos en la selección.
            $result = $repo->getListado($idEmpresa, $buscar, 1, 10, 'nombre', 'ASC', null, true);

            echo json_encode(['ok' => true, 'data' => $result['rows']]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function getComprobantesAutorizadosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        try {
            $data = $this->repository->getComprobantesAutorizados();
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }
}
