<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\ReporteCarteraRepository;
use App\repositories\modulos\ClienteRepository;
use App\repositories\modulos\ProveedorRepository;
use App\models\Empresa;

class ReporteCarteraController extends BaseModuloController
{
    private ReporteCarteraRepository $repository;
    private const RUTA_MODULO = 'modulos/reporte_cartera';

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new ReporteCarteraRepository();
    }

    public function index(): void
    {
        $this->requireLeer();

        $this->viewWithLayout('layouts.main', 'modulos/reporte_cartera/index', [
            'titulo'     => 'Reporte de Cartera',
            'perm'       => $this->getPermisos(),
            'rutaModulo' => self::RUTA_MODULO,
            'fullWidth'  => true,
            'base'       => BASE_URL,
        ]);
    }

    // ────────────────────────────────────────────────────────────────
    // FILTROS
    // ────────────────────────────────────────────────────────────────
    private function getFiltrosDesdeRequest(): array
    {
        $tipo = strtoupper(trim((string) ($_REQUEST['tipo'] ?? 'CLIENTE')));

        $idsRaw = $_REQUEST['id_entidad'] ?? [];
        if (!is_array($idsRaw)) {
            $idsRaw = $idsRaw !== '' ? [$idsRaw] : [];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $idsRaw))));

        return [
            'tipo'        => $tipo === 'PROVEEDOR' ? 'PROVEEDOR' : 'CLIENTE',
            'ids'         => $ids,
            'todos'       => !empty($_REQUEST['todos']),
            'fecha_desde' => trim((string) ($_REQUEST['fecha_desde'] ?? '')),
            'fecha_hasta' => trim((string) ($_REQUEST['fecha_hasta'] ?? '')),
            // Número de documento (factura, recibo, compra, saldo inicial...) elegido
            // en el buscador "Documento": limita el estado de cuenta a ese documento
            // y a los abonos que lo cancelan.
            'documento'   => mb_substr(trim((string) ($_REQUEST['documento'] ?? '')), 0, 60),
        ];
    }

    /**
     * IDs de entidad a incluir: la selección manual, o —si "todos" está
     * activo— todos los clientes/proveedores con saldo pendiente a la fecha
     * de corte (una sola consulta agregada, no una por entidad).
     */
    private function resolverIds(int $idEmpresa, array $filtros): array
    {
        if (!$filtros['todos']) {
            return $filtros['ids'];
        }

        $fechaHasta = $filtros['fecha_hasta'] !== '' ? $filtros['fecha_hasta'] : null;
        $rows = $filtros['tipo'] === 'PROVEEDOR'
            ? $this->repository->getProveedoresConSaldoPendiente($idEmpresa, $fechaHasta)
            : $this->repository->getClientesConSaldoPendiente($idEmpresa, $fechaHasta);

        return array_map(fn($r) => (int) $r['id'], $rows);
    }

    private function etiquetaEntidad(string $tipo): string
    {
        return $tipo === 'PROVEEDOR' ? 'Proveedor' : 'Cliente';
    }

    // ────────────────────────────────────────────────────────────────
    // CONSTRUCCIÓN DE LOS ESTADOS DE CUENTA (uno por entidad seleccionada)
    // ────────────────────────────────────────────────────────────────
    private function construirLedgers(int $idEmpresa, array $filtros): array
    {
        $ledgers = [];
        foreach ($this->resolverIds($idEmpresa, $filtros) as $idEntidad) {
            $ledger = $this->construirLedger($idEmpresa, $filtros['tipo'], $idEntidad, $filtros['fecha_desde'], $filtros['fecha_hasta'], $filtros['documento'] ?? '');
            if ($ledger) {
                $ledgers[] = $ledger;
            }
        }
        return $ledgers;
    }

    private function construirLedger(int $idEmpresa, string $tipo, int $idEntidad, string $fechaDesde, string $fechaHasta, string $documento = ''): ?array
    {
        $doc = $documento !== '' ? $documento : null;
        if ($tipo === 'PROVEEDOR') {
            $entidad = $this->repository->getProveedorPorId($idEmpresa, $idEntidad);
            if (!$entidad) return null;
            $movimientos   = $this->repository->getMovimientosProveedor($idEmpresa, $idEntidad, $fechaDesde !== '' ? $fechaDesde : null, $fechaHasta !== '' ? $fechaHasta : null, $doc);
            $saldoAnterior = $fechaDesde !== '' ? $this->repository->getSaldoAnteriorProveedor($idEmpresa, $idEntidad, $fechaDesde, $doc) : 0.0;
        } else {
            $entidad = $this->repository->getClientePorId($idEmpresa, $idEntidad);
            if (!$entidad) return null;
            $movimientos   = $this->repository->getMovimientosCliente($idEmpresa, $idEntidad, $fechaDesde !== '' ? $fechaDesde : null, $fechaHasta !== '' ? $fechaHasta : null, $doc);
            $saldoAnterior = $fechaDesde !== '' ? $this->repository->getSaldoAnteriorCliente($idEmpresa, $idEntidad, $fechaDesde, $doc) : 0.0;
        }

        $saldo = $saldoAnterior;
        $totalCargos = 0.0;
        $totalAbonos = 0.0;
        foreach ($movimientos as &$m) {
            $monto = (float) $m['monto'];
            if ($m['tipo_movimiento'] === 'CARGO') {
                $saldo += $monto;
                $totalCargos += $monto;
            } else {
                $saldo -= $monto;
                $totalAbonos += $monto;
            }
            $m['saldo'] = $saldo;
        }
        unset($m);

        return [
            'entidad'        => $entidad,
            'saldo_anterior' => $saldoAnterior,
            'movimientos'    => $movimientos,
            'total_cargos'   => $totalCargos,
            'total_abonos'   => $totalAbonos,
            'saldo_final'    => $saldo,
        ];
    }

    private function calcularEstadisticas(array $ledgers): array
    {
        $totalCargos = 0.0;
        $totalAbonos = 0.0;
        $totalSaldo  = 0.0;
        foreach ($ledgers as $l) {
            $totalCargos += $l['total_cargos'];
            $totalAbonos += $l['total_abonos'];
            $totalSaldo  += $l['saldo_final'];
        }

        return [
            'entidades'    => count($ledgers),
            'total_cargos' => $totalCargos,
            'total_abonos' => $totalAbonos,
            'saldo_total'  => $totalSaldo,
        ];
    }

    private function formatearRango(array $filtros): string
    {
        $d = $filtros['fecha_desde'];
        $h = $filtros['fecha_hasta'];
        if ($d !== '' && $h !== '') return date('d-m-Y', strtotime($d)) . ' al ' . date('d-m-Y', strtotime($h));
        if ($d !== '') return 'Desde ' . date('d-m-Y', strtotime($d));
        if ($h !== '') return 'Hasta ' . date('d-m-Y', strtotime($h));
        return 'Todas las fechas';
    }

    /**
     * Documentos (cargos) de las entidades seleccionadas, para el buscador
     * del filtro "Documento". Sin entidad seleccionada y sin "Todos" no hay
     * nada que listar: el documento se busca en base al cliente/proveedor.
     */
    public function getDocumentosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $filtros   = $this->getFiltrosDesdeRequest();
            $q         = mb_substr(trim((string) ($_REQUEST['q'] ?? '')), 0, 60);

            if (empty($filtros['ids']) && !$filtros['todos']) {
                echo json_encode(['ok' => true, 'data' => [], 'mensaje' => 'Seleccione primero un ' . $this->etiquetaEntidad($filtros['tipo']) . '.']);
                exit;
            }

            $rows = $this->repository->getDocumentosEntidad($idEmpresa, $filtros['tipo'], $filtros['todos'] ? [] : $filtros['ids'], $q, 20);
            $data = array_map(fn($r) => [
                'numero'  => (string) $r['numero'],
                'origen'  => ucfirst(strtolower(str_replace('_', ' ', (string) $r['origen']))),
                'fecha'   => !empty($r['fecha']) ? date('d-m-Y', strtotime((string) $r['fecha'])) : '',
                'total'   => number_format((float) $r['total'], 2),
                'entidad' => (string) ($r['nombre_entidad'] ?? ''),
            ], $rows);

            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'data' => [], 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ────────────────────────────────────────────────────────────────
    // GENERAR (AJAX)
    // ────────────────────────────────────────────────────────────────
    public function generarAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $filtros = $this->getFiltrosDesdeRequest();

            if (empty($filtros['ids']) && !$filtros['todos']) {
                echo json_encode([
                    'ok'    => true,
                    'html'  => '<div class="text-center text-muted py-5"><i class="bi bi-funnel fs-3 d-block mb-2"></i>Seleccione al menos un ' . $this->etiquetaEntidad($filtros['tipo']) . ', o marque "Todos" para generar el estado de cuenta.</div>',
                    'stats' => ['entidades' => 0, 'total_cargos' => 0, 'total_abonos' => 0, 'saldo_total' => 0],
                ]);
                exit;
            }

            $ledgers = $this->construirLedgers($idEmpresa, $filtros);
            $stats   = $this->calcularEstadisticas($ledgers);

            ob_start();
            if (empty($ledgers)) {
                $mensaje = $filtros['todos']
                    ? 'No hay ' . strtolower($this->etiquetaEntidad($filtros['tipo'])) . 's con saldo pendiente a la fecha de corte seleccionada.'
                    : 'No se encontraron datos para la selección actual.';
                echo '<div class="text-center text-muted py-5"><i class="bi bi-inbox fs-3 d-block mb-2"></i>' . htmlspecialchars($mensaje) . '</div>';
            } else {
                foreach ($ledgers as $ledger) {
                    echo $this->renderSeccionScreen($ledger, $filtros['tipo'], $filtros['documento'] ?? '');
                }
            }
            $html = (string) ob_get_clean();

            echo json_encode(['ok' => true, 'html' => $html, 'stats' => $stats]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function renderSeccionScreen(array $ledger, string $tipo, string $documento = ''): string
    {
        $e = $ledger['entidad'];
        $etiqueta = $this->etiquetaEntidad($tipo);
        $deudor = $ledger['saldo_final'] > 0.005;
        $idEntidad = (int) ($e['id'] ?? 0);
        $emailEntidad = (string) ($e['email'] ?? '');

        ob_start();
        ?>
        <div class="card border-0 shadow-sm rounded-3 mb-3">
            <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <!-- Acciones del estado de cuenta de ESTA entidad (PDF / Excel / Correo) -->
                    <div class="btn-group btn-group-sm" role="group" aria-label="Acciones">
                        <button type="button" class="btn btn-outline-danger" onclick="window.RC_exportarPDF(<?= $idEntidad ?>)" title="Descargar PDF">
                            <i class="bi bi-file-earmark-pdf"></i>
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="window.RC_exportarExcel(<?= $idEntidad ?>)" title="Descargar Excel">
                            <i class="bi bi-file-earmark-spreadsheet"></i>
                        </button>
                        <button type="button" class="btn btn-outline-primary" onclick="window.RC_abrirModalCorreo(<?= $idEntidad ?>, <?= htmlspecialchars(json_encode($emailEntidad), ENT_QUOTES) ?>)" title="Enviar por correo">
                            <i class="bi bi-envelope"></i>
                        </button>
                    </div>
                    <div class="vr mx-1"></div>
                    <div>
                        <span class="fw-bold"><?= htmlspecialchars($e['nombre'] ?? '') ?></span>
                        <small class="text-muted d-block">
                            <?= htmlspecialchars($etiqueta) ?> · <?= htmlspecialchars($e['identificacion'] ?? '') ?>
                            <?= $emailEntidad !== '' ? ' · ' . htmlspecialchars($emailEntidad) : '' ?>
                            <?= $documento !== '' ? ' · <span class="badge bg-info bg-opacity-10 text-info border border-info">Documento: ' . htmlspecialchars($documento) . '</span>' : '' ?>
                        </small>
                    </div>
                </div>
                <span class="badge <?= $deudor ? 'bg-danger' : 'bg-success' ?> bg-opacity-10 <?= $deudor ? 'text-danger' : 'text-success' ?> border <?= $deudor ? 'border-danger' : 'border-success' ?>">
                    Saldo: $<?= number_format($ledger['saldo_final'], 2) ?>
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr class="text-secondary small">
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Documento</th>
                                <th>Detalle</th>
                                <th class="text-end">Deuda Generada</th>
                                <th class="text-end">Abono</th>
                                <th class="text-end">Saldo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="table-light">
                                <td colspan="6" class="fw-bold small">Saldo Anterior</td>
                                <td class="text-end fw-bold small"><?= number_format($ledger['saldo_anterior'], 2) ?></td>
                            </tr>
                            <?php if (empty($ledger['movimientos'])): ?>
                                <tr><td colspan="7" class="text-center text-muted py-3">Sin movimientos en el período seleccionado.</td></tr>
                            <?php else: foreach ($ledger['movimientos'] as $m): ?>
                                <tr>
                                    <td class="small text-nowrap"><?= date('d-m-Y', strtotime($m['fecha'])) ?></td>
                                    <td class="small"><?= htmlspecialchars(ucfirst(strtolower(str_replace('_', ' ', $m['origen'])))) ?></td>
                                    <td class="small"><?= htmlspecialchars($m['numero_documento'] ?? '') ?></td>
                                    <td class="small"><?= htmlspecialchars($m['detalle'] ?? '') ?></td>
                                    <td class="text-end small text-danger"><?= $m['tipo_movimiento'] === 'CARGO' ? number_format((float) $m['monto'], 2) : '' ?></td>
                                    <td class="text-end small text-success"><?= $m['tipo_movimiento'] === 'ABONO' ? number_format((float) $m['monto'], 2) : '' ?></td>
                                    <td class="text-end small fw-bold"><?= number_format((float) $m['saldo'], 2) ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-secondary">
                                <th colspan="4" class="text-end small">TOTALES:</th>
                                <th class="text-end small text-danger"><?= number_format($ledger['total_cargos'], 2) ?></th>
                                <th class="text-end small text-success"><?= number_format($ledger['total_abonos'], 2) ?></th>
                                <th class="text-end small">$<?= number_format($ledger['saldo_final'], 2) ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    // ────────────────────────────────────────────────────────────────
    // AUTOCOMPLETAR
    // ────────────────────────────────────────────────────────────────
    public function getClientesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['q'] ?? '');

        $repo = new ClienteRepository();
        $result = $repo->getListado($idEmpresa, $buscar, 1, 15, 'nombre', 'ASC');

        $data = array_map(fn($row) => [
            'id'             => $row['id'],
            'nombre'         => $row['nombre'] ?? '',
            'identificacion' => $row['identificacion'] ?? '',
        ], $result['rows'] ?? []);

        echo json_encode(['ok' => true, 'data' => $data]);
        exit;
    }

    public function getProveedoresAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['q'] ?? '');

        $repo = new ProveedorRepository();
        $result = $repo->getListado($idEmpresa, $buscar, 1, 15, 'razon_social', 'ASC');

        $data = array_map(fn($row) => [
            'id'             => $row['id'],
            'nombre'         => $row['razon_social'] ?? '',
            'identificacion' => $row['identificacion'] ?? '',
        ], $result['rows'] ?? []);

        echo json_encode(['ok' => true, 'data' => $data]);
        exit;
    }

    // ────────────────────────────────────────────────────────────────
    // PDF / EXCEL / CORREO
    // ────────────────────────────────────────────────────────────────
    private function construirHtmlReportePdf(array $ledgers, string $nombreEmpresa, array $filtros): string
    {
        $etiqueta = $this->etiquetaEntidad($filtros['tipo']);
        $rango = $this->formatearRango($filtros);

        ob_start();
        ?>
        <style>
            table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 8.5pt; margin: 0 auto 14px auto; }
            th { background: #f2f2f2; border: 1px solid #ccc; padding: 4px; text-align: center; }
            td { border: 1px solid #ccc; padding: 4px; }
            .text-end { text-align: right; }
            .text-center { text-align: center; }
            .header { text-align: center; margin-bottom: 14px; }
            .saldo-row td { background: #f8f9fa; font-weight: bold; }
            .total-row td { background: #e9ecef; font-weight: bold; }
        </style>
        <?php foreach ($ledgers as $i => $ledger): $e = $ledger['entidad']; ?>
            <div class="header" <?= $i > 0 ? 'style="page-break-before: always;"' : '' ?>>
                <h2><?= htmlspecialchars($nombreEmpresa) ?></h2>
                <h3>Estado de Cuenta - <?= htmlspecialchars($etiqueta) ?></h3>
                <p><strong><?= htmlspecialchars($e['nombre'] ?? '') ?></strong> — <?= htmlspecialchars($e['identificacion'] ?? '') ?></p>
                <p>Período: <?= htmlspecialchars($rango) ?> &nbsp;|&nbsp; Fecha de reporte: <?= date('d-m-Y H:i:s') ?></p>
                <?php if (($filtros['documento'] ?? '') !== ''): ?>
                    <p>Documento: <strong><?= htmlspecialchars($filtros['documento']) ?></strong></p>
                <?php endif; ?>
            </div>
            <table>
                <thead>
                    <tr><th>Fecha</th><th>Tipo</th><th>Documento</th><th>Detalle</th><th>Deuda Generada</th><th>Abono</th><th>Saldo</th></tr>
                </thead>
                <tbody>
                    <tr class="saldo-row">
                        <td colspan="6" class="text-end">Saldo Anterior</td>
                        <td class="text-end"><?= number_format($ledger['saldo_anterior'], 2) ?></td>
                    </tr>
                    <?php foreach ($ledger['movimientos'] as $m): ?>
                        <tr>
                            <td class="text-center"><?= date('d-m-Y', strtotime($m['fecha'])) ?></td>
                            <td class="text-center"><?= htmlspecialchars(ucfirst(strtolower(str_replace('_', ' ', $m['origen'])))) ?></td>
                            <td><?= htmlspecialchars($m['numero_documento'] ?? '') ?></td>
                            <td><?= htmlspecialchars($m['detalle'] ?? '') ?></td>
                            <td class="text-end"><?= $m['tipo_movimiento'] === 'CARGO' ? number_format((float) $m['monto'], 2) : '' ?></td>
                            <td class="text-end"><?= $m['tipo_movimiento'] === 'ABONO' ? number_format((float) $m['monto'], 2) : '' ?></td>
                            <td class="text-end"><?= number_format((float) $m['saldo'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <td colspan="4" class="text-end">TOTALES</td>
                        <td class="text-end"><?= number_format($ledger['total_cargos'], 2) ?></td>
                        <td class="text-end"><?= number_format($ledger['total_abonos'], 2) ?></td>
                        <td class="text-end">$<?= number_format($ledger['saldo_final'], 2) ?></td>
                    </tr>
                </tbody>
            </table>
        <?php endforeach; ?>
        <?php
        return (string) ob_get_clean();
    }

    /** @return array{0: array, 1: array} [headers, secciones] para ReportService::exportToExcelSeccionado() */
    private function datosExportExcel(array $ledgers): array
    {
        $headers = ['Fecha', 'Tipo', 'Documento', 'Detalle', 'Deuda Generada', 'Abono', 'Saldo'];
        $secciones = [];

        foreach ($ledgers as $ledger) {
            $e = $ledger['entidad'];
            $filas = [['', 'Saldo Anterior', '', '', '', '', round($ledger['saldo_anterior'], 2)]];

            foreach ($ledger['movimientos'] as $m) {
                $filas[] = [
                    date('d-m-Y', strtotime($m['fecha'])),
                    ucfirst(strtolower(str_replace('_', ' ', $m['origen']))),
                    $m['numero_documento'] ?? '',
                    $m['detalle'] ?? '',
                    $m['tipo_movimiento'] === 'CARGO' ? round((float) $m['monto'], 2) : '',
                    $m['tipo_movimiento'] === 'ABONO' ? round((float) $m['monto'], 2) : '',
                    round((float) $m['saldo'], 2),
                ];
            }

            $secciones[] = [
                'titulo'  => trim(($e['nombre'] ?? '') . ' - ' . ($e['identificacion'] ?? '')),
                'filas'   => $filas,
                'resumen' => ['', '', '', 'TOTALES', round($ledger['total_cargos'], 2), round($ledger['total_abonos'], 2), round($ledger['saldo_final'], 2)],
            ];
        }

        return [$headers, $secciones];
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros = $this->getFiltrosDesdeRequest();

        if (empty($filtros['ids']) && !$filtros['todos']) {
            echo 'Seleccione al menos un ' . $this->etiquetaEntidad($filtros['tipo']) . ', o marque "Todos", para exportar.';
            return;
        }

        $ledgers = $this->construirLedgers($idEmpresa, $filtros);

        if (empty($ledgers)) {
            echo $filtros['todos']
                ? 'No hay ' . strtolower($this->etiquetaEntidad($filtros['tipo'])) . 's con saldo pendiente a la fecha de corte seleccionada.'
                : 'Seleccione al menos un ' . $this->etiquetaEntidad($filtros['tipo']) . ' para exportar.';
            return;
        }

        try {
            $empresa = (new Empresa())->getPorId($idEmpresa);
            $nombreEmpresa = $empresa['nombre'] ?? '';

            [$headers, $secciones] = $this->datosExportExcel($ledgers);

            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcelSeccionado('Cartera', $headers, $secciones, 'Reporte de Cartera', $nombreEmpresa);
            exit;
        } catch (\Throwable $e) {
            echo 'Error al generar Excel: ' . $e->getMessage();
        }
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros = $this->getFiltrosDesdeRequest();

        if (empty($filtros['ids']) && !$filtros['todos']) {
            echo 'Seleccione al menos un ' . $this->etiquetaEntidad($filtros['tipo']) . ', o marque "Todos", para exportar.';
            return;
        }

        $ledgers = $this->construirLedgers($idEmpresa, $filtros);

        if (empty($ledgers)) {
            echo $filtros['todos']
                ? 'No hay ' . strtolower($this->etiquetaEntidad($filtros['tipo'])) . 's con saldo pendiente a la fecha de corte seleccionada.'
                : 'Seleccione al menos un ' . $this->etiquetaEntidad($filtros['tipo']) . ' para exportar.';
            return;
        }

        try {
            $empresa = (new Empresa())->getPorId($idEmpresa) ?? [];
            $nombreEmpresa = $empresa['nombre'] ?? 'REPORTE DE CARTERA';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) require_once $autoload;

            $html = $this->construirHtmlReportePdf($ledgers, $nombreEmpresa, $filtros);
            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
            $html2pdf->writeHTML($html);
            $html2pdf->output('ReporteCartera_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
    }

    /** Envía el estado de cuenta (con los filtros actuales) por correo, adjuntando PDF y/o Excel. */
    public function enviarCorreoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $filtros = $this->getFiltrosDesdeRequest();

            $correosRaw = trim($_POST['correos'] ?? '');
            $destinatarios = array_values(array_filter(
                array_map('trim', explode(',', $correosRaw)),
                fn($c) => filter_var($c, FILTER_VALIDATE_EMAIL) !== false
            ));

            if (empty($destinatarios)) {
                echo json_encode(['ok' => false, 'mensaje' => 'Ingrese al menos un correo válido.']);
                exit;
            }

            if (empty($filtros['ids']) && !$filtros['todos']) {
                echo json_encode(['ok' => false, 'mensaje' => 'Seleccione al menos un ' . $this->etiquetaEntidad($filtros['tipo']) . ', o marque "Todos", antes de enviar.']);
                exit;
            }

            $ledgers = $this->construirLedgers($idEmpresa, $filtros);
            if (empty($ledgers)) {
                $mensaje = $filtros['todos']
                    ? 'No hay ' . strtolower($this->etiquetaEntidad($filtros['tipo'])) . 's con saldo pendiente a la fecha de corte seleccionada.'
                    : 'Seleccione al menos un ' . $this->etiquetaEntidad($filtros['tipo']) . ' antes de enviar.';
                echo json_encode(['ok' => false, 'mensaje' => $mensaje]);
                exit;
            }

            $adjuntar = $_POST['adjuntar'] ?? 'pdf'; // pdf | excel | ambos

            $empresa = (new Empresa())->getPorId($idEmpresa) ?? [];
            $nombreEmpresa = $empresa['nombre'] ?? 'Reporte de Cartera';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) require_once $autoload;

            $adjuntosPaths = [];
            $tmpBase = sys_get_temp_dir() . '/rc_' . $idEmpresa . '_' . time();

            if ($adjuntar === 'pdf' || $adjuntar === 'ambos') {
                $html = $this->construirHtmlReportePdf($ledgers, $nombreEmpresa, $filtros);
                $pdfPath = $tmpBase . '.pdf';
                $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
                $html2pdf->writeHTML($html);
                $html2pdf->output($pdfPath, 'F');
                $adjuntosPaths[$pdfPath] = 'ReporteCartera_' . date('Ymd') . '.pdf';
            }

            if ($adjuntar === 'excel' || $adjuntar === 'ambos') {
                [$headers, $secciones] = $this->datosExportExcel($ledgers);
                $excelPath = $tmpBase . '.xlsx';
                (new \App\Services\ReportService())->guardarExcelSeccionadoEnArchivo($headers, $secciones, 'Reporte de Cartera', $nombreEmpresa, $excelPath);
                $adjuntosPaths[$excelPath] = 'ReporteCartera_' . date('Ymd') . '.xlsx';
            }

            $stats = $this->calcularEstadisticas($ledgers);
            $etiqueta = $this->etiquetaEntidad($filtros['tipo']);

            $asunto = 'Estado de Cuenta - ' . $nombreEmpresa;
            $cuerpo = '
                <div style="font-family:Arial,sans-serif;color:#333;max-width:600px;margin:auto;">
                    <h2 style="color:#2563eb;">Reporte de Cartera</h2>
                    <p>Adjunto el estado de cuenta de <strong>' . htmlspecialchars($nombreEmpresa) . '</strong>.</p>'
                    . (($filtros['documento'] ?? '') !== '' ? '<p>Documento: <strong>' . htmlspecialchars($filtros['documento']) . '</strong></p>' : '') . '
                    <table style="border-collapse:collapse;font-size:14px;">
                        <tr><td style="padding:4px 12px;color:#666;">' . htmlspecialchars($etiqueta) . 's incluidos</td><td style="padding:4px 12px;"><strong>' . (int) $stats['entidades'] . '</strong></td></tr>
                        <tr><td style="padding:4px 12px;color:#666;">Total deuda generada</td><td style="padding:4px 12px;">$ ' . number_format($stats['total_cargos'], 2) . '</td></tr>
                        <tr><td style="padding:4px 12px;color:#666;">Total abonos</td><td style="padding:4px 12px;">$ ' . number_format($stats['total_abonos'], 2) . '</td></tr>
                        <tr><td style="padding:4px 12px;color:#666;">Saldo final</td><td style="padding:4px 12px;"><strong>$ ' . number_format($stats['saldo_total'], 2) . '</strong></td></tr>
                    </table>
                    <p style="color:#888;font-size:12px;margin-top:24px;">Reporte generado el ' . date('d-m-Y H:i:s') . '.</p>
                </div>';

            $ok = enviar_correo_reporte($destinatarios, $asunto, $cuerpo, $adjuntosPaths);

            foreach (array_keys($adjuntosPaths) as $path) {
                if (file_exists($path)) {
                    unlink($path);
                }
            }

            echo json_encode($ok
                ? ['ok' => true, 'mensaje' => 'Reporte enviado a ' . implode(', ', $destinatarios)]
                : ['ok' => false, 'mensaje' => $GLOBALS['LAST_EMAIL_ERROR'] ?? 'No se pudo enviar el correo. Verifica la configuración de correo.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => 'Error al enviar correo: ' . $e->getMessage()]);
        }
        exit;
    }
}
