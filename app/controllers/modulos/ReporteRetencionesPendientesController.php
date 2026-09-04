<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\ReporteRetencionesPendientesRepository;
use App\Services\modulos\ReporteRetencionesPendientesService;

/**
 * Reporte de Retenciones de Venta Pendientes: facturas de venta autorizadas
 * que no tienen comprobante de retención, con envío de avisos por correo
 * (individual por factura o agrupado por cliente).
 */
class ReporteRetencionesPendientesController extends BaseModuloController
{
    private ReporteRetencionesPendientesRepository $repository;
    private ReporteRetencionesPendientesService $service;

    protected function getRutaModulo(): string
    {
        return 'modulos/reporte_retenciones_pendientes';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new ReporteRetencionesPendientesRepository();
        $this->service    = new ReporteRetencionesPendientesService($this->repository);
    }

    /** Registros propios vs. acceso total (CLAUDE.md §6). */
    private function idUsuarioFiltro(): ?int
    {
        return empty($this->getPermisos()['todo']) ? (int) $_SESSION['id_usuario'] : null;
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $anios = $this->repository->getAnios($idEmpresa);
        $anioActual = (int) date('Y');
        if (!in_array($anioActual, $anios, true)) {
            $anios[] = $anioActual;
            rsort($anios);
        }

        $this->viewWithLayout('layouts.main', 'modulos/reporte_retenciones_pendientes/index', [
            'titulo'     => 'Retenciones de Venta Pendientes',
            'perm'       => $this->getPermisos(),
            'rutaModulo' => $this->getRutaModulo(),
            'vistaConfig' => \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo()),
            'anios'      => $anios,
            'anioActual' => $anioActual,
            'fullWidth'  => true,
            'base'       => BASE_URL,
        ]);
    }

    private function getFiltros(): array
    {
        $aviso = strtoupper(trim($_REQUEST['aviso'] ?? 'TODOS'));
        if (!in_array($aviso, ['TODOS', 'SIN', 'CON'], true)) {
            $aviso = 'TODOS';
        }
        $fecha = fn (string $k): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_REQUEST[$k] ?? '') ? $_REQUEST[$k] : '';

        return [
            'anio'         => (int) ($_REQUEST['anio'] ?? 0),
            'mes'          => (int) ($_REQUEST['mes'] ?? 0),
            'fecha_desde'  => $fecha('fecha_desde'),
            'fecha_hasta'  => $fecha('fecha_hasta'),
            'id_cliente'   => (int) ($_REQUEST['id_cliente'] ?? 0),
            'aviso'        => $aviso,
            'buscar'       => trim($_REQUEST['buscar'] ?? ''),
        ];
    }

    // ── Listado ──────────────────────────────────────────────────────────────

    public function generarAjax(): void
    {
        $this->requireLeer();
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $f    = $this->getFiltros();
            $filt = $this->idUsuarioFiltro();

            $rows  = $this->repository->getPendientes($idEmpresa, $f, $filt);
            $stats = $this->repository->getEstadisticas($idEmpresa, $f, $filt);

            $urlBase = BASE_URL . '/' . $this->getRutaModulo();
            $qs      = http_build_query($f);
            $this->json([
                'ok'        => true,
                'rows'      => $rows,
                'total'     => count($rows),
                'stats'     => [
                    'n_facturas'     => (int) ($stats['n_facturas'] ?? 0),
                    'n_clientes'     => (int) ($stats['n_clientes'] ?? 0),
                    'total_subtotal' => (float) ($stats['total_subtotal'] ?? 0),
                    'total_general'  => (float) ($stats['total_general'] ?? 0),
                    'n_avisadas'     => (int) ($stats['n_avisadas'] ?? 0),
                    'n_sin_correo'   => (int) ($stats['n_sin_correo'] ?? 0),
                ],
                'excel_url' => $urlBase . '/exportExcel?' . $qs,
                'pdf_url'   => $urlBase . '/exportPdf?' . $qs,
            ]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    public function buscarClientesAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $q = trim($_GET['q'] ?? '');
        $this->json(['ok' => true, 'data' => $this->repository->buscarClientes($idEmpresa, $q)]);
    }

    /** Historial de avisos enviados de una factura. */
    public function avisosAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idVenta   = (int) ($_GET['id_venta'] ?? 0);
        if ($idVenta <= 0) {
            $this->json(['ok' => false, 'mensaje' => 'Factura no indicada.']);
        }
        try {
            $this->json(['ok' => true, 'data' => $this->service->getAvisosPorVenta($idVenta, $idEmpresa)]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    // ── Envío de avisos ──────────────────────────────────────────────────────

    /** Un correo por factura (modal individual). */
    public function enviarEmailAjax(): void
    {
        $this->requireLeer();
        try {
            $res = $this->service->enviarAvisoIndividual(
                (int) $_SESSION['id_empresa'],
                (int) $_SESSION['id_usuario'],
                (int) ($_POST['id_venta'] ?? 0),
                trim($_POST['email'] ?? ''),
                trim($_POST['asunto'] ?? ''),
                trim($_POST['mensaje'] ?? ''),
                $this->idUsuarioFiltro()
            );
            $this->json(['ok' => true] + $res);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /** Un correo por cliente con todas sus facturas seleccionadas (modal agrupado). */
    public function enviarEmailAgrupadoAjax(): void
    {
        $this->requireLeer();
        try {
            $ids = json_decode($_POST['ids'] ?? '[]', true);
            $correos = json_decode($_POST['correos'] ?? '{}', true);
            $res = $this->service->enviarAvisosAgrupados(
                (int) $_SESSION['id_empresa'],
                (int) $_SESSION['id_usuario'],
                is_array($ids) ? $ids : [],
                is_array($correos) ? $correos : [],
                trim($_POST['mensaje'] ?? ''),
                $this->idUsuarioFiltro()
            );
            $this->json(['ok' => true] + $res);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    // ── Exportaciones ────────────────────────────────────────────────────────

    /** [headers, data, right, money] del listado completo (sin tope). */
    private function datosExport(int $idEmpresa, array $f): array
    {
        $rows = $this->repository->getPendientes($idEmpresa, $f, $this->idUsuarioFiltro(), 0);
        $headers = ['Factura', 'Fecha', 'Días', 'Cliente', 'Identificación', 'Correo', 'Subtotal', 'Impuestos', 'Total', 'Avisos', 'Último aviso'];
        $data = array_map(fn ($r) => [
            $r['numero_factura'],
            !empty($r['fecha_emision']) ? date('d/m/Y', strtotime($r['fecha_emision'])) : '',
            (int) $r['dias'],
            $r['cliente_nombre'],
            $r['cliente_ruc'],
            $r['cliente_email'] ?? '',
            (float) $r['total_sin_impuestos'],
            (float) $r['impuestos'],
            (float) $r['importe_total'],
            (int) $r['n_avisos'],
            !empty($r['ultimo_aviso']) ? date('d/m/Y H:i', strtotime($r['ultimo_aviso'])) : '',
        ], $rows);
        return ['headers' => $headers, 'data' => $data, 'right' => [2, 6, 7, 8, 9], 'money' => [6, 7, 8]];
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $nombreEmpresa = (new \App\models\Empresa())->getPorId($idEmpresa)['nombre'] ?? '';
        $exp = $this->datosExport($idEmpresa, $this->getFiltros());
        try {
            (new \App\Services\ReportService())->exportToExcel(
                'Retenciones_Venta_Pendientes', $exp['headers'], $exp['data'], 'Pendientes', $nombreEmpresa
            );
        } catch (\Throwable $e) {
            echo 'Error al generar Excel: ' . $e->getMessage();
        }
        exit;
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $f       = $this->getFiltros();
        $exp     = $this->datosExport($idEmpresa, $f);
        $stats   = $this->repository->getEstadisticas($idEmpresa, $f, $this->idUsuarioFiltro());
        $empresa = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];

        $autoload = \MVC_ROOT . '/vendor/autoload.php';
        if (file_exists($autoload)) require_once $autoload;

        $money  = fn ($v) => number_format((float) $v, 2);
        $right  = array_flip($exp['right']);
        $money2 = array_flip($exp['money']);
        $anchos = [11, 7, 4, 22, 9, 16, 7, 7, 7, 4, 6]; // % por columna (suman 100)

        ob_start(); ?>
        <style>
            table { width:100%; border-collapse:collapse; font-family:Arial,sans-serif; font-size:7pt; table-layout:fixed; }
            th { background:#f2f2f2; border:1px solid #ccc; padding:3px; }
            td { border:1px solid #ccc; padding:3px; overflow:hidden; }
            .r { text-align:right; } .c { text-align:center; }
            .head { text-align:center; margin-bottom:10px; }
            .kpi td { border:1px solid #ccc; padding:6px; font-size:9pt; }
        </style>
        <div class="head">
            <h3><?= htmlspecialchars($empresa['nombre'] ?? '') ?></h3>
            <h4>Facturas de venta sin comprobante de retención</h4>
            <p style="font-size:8pt">Generado: <?= date('d-m-Y H:i:s') ?></p>
        </div>
        <table class="kpi" style="margin-bottom:10px">
            <tr>
                <td class="c"><strong>Facturas</strong><br><?= (int) ($stats['n_facturas'] ?? 0) ?></td>
                <td class="c"><strong>Clientes</strong><br><?= (int) ($stats['n_clientes'] ?? 0) ?></td>
                <td class="c"><strong>Subtotal</strong><br>$<?= $money($stats['total_subtotal'] ?? 0) ?></td>
                <td class="c"><strong>Total</strong><br>$<?= $money($stats['total_general'] ?? 0) ?></td>
                <td class="c"><strong>Con aviso</strong><br><?= (int) ($stats['n_avisadas'] ?? 0) ?></td>
                <td class="c"><strong>Sin correo</strong><br><?= (int) ($stats['n_sin_correo'] ?? 0) ?></td>
            </tr>
        </table>
        <table>
            <thead>
                <tr><?php foreach ($exp['headers'] as $i => $h): ?><th style="width:<?= $anchos[$i] ?>%" class="<?= isset($right[$i]) ? 'r' : '' ?>"><?= htmlspecialchars($h) ?></th><?php endforeach; ?></tr>
            </thead>
            <tbody>
                <?php foreach ($exp['data'] as $fila): ?>
                    <tr>
                        <?php foreach ($fila as $i => $val): ?>
                            <td class="<?= isset($right[$i]) ? 'r' : '' ?>"><?= isset($money2[$i]) ? '$' . $money($val) : htmlspecialchars((string) $val) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($exp['data'])): ?>
                    <tr><td colspan="<?= count($exp['headers']) ?>" class="c">Sin facturas pendientes para los filtros seleccionados.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
        $html = ob_get_clean();
        try {
            $pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $pdf->writeHTML($html);
            $pdf->output('RetencionesVentaPendientes_' . date('Ymd_His') . '.pdf', 'D');
        } catch (\Throwable $e) {
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
        exit;
    }
}
