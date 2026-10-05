<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Services\modulos\SuscripcionDevengoService;

/**
 * Reporte de Ingresos Diferidos (NIIF 15): saldos de las suscripciones que reconocen el ingreso
 * durante el período, al cierre de un mes — diferido corriente / no corriente y devengado por
 * facturar (mes caído) — por documento, con la conciliación contra el mayor. Solo lectura.
 *
 * La lógica vive en SuscripcionDevengoService (la misma que usa Auditoría Contable).
 */
class ReporteIngresosDiferidosController extends BaseModuloController
{
    private SuscripcionDevengoService $service;

    protected function getRutaModulo(): string
    {
        return 'modulos/reporte_ingresos_diferidos';
    }

    public function __construct()
    {
        parent::__construct();
        $this->service = SuscripcionDevengoService::crear();
    }

    /** Registros propios vs. acceso total (CLAUDE.md §6). */
    private function idUsuarioFiltro(): ?int
    {
        return empty($this->getPermisos()['todo']) ? (int) $_SESSION['id_usuario'] : null;
    }

    public function index(): void
    {
        $this->requireLeer();
        $this->viewWithLayout('layouts.main', 'modulos/reporte_ingresos_diferidos/index', [
            'titulo'      => 'Ingresos Diferidos',
            'perm'        => $this->getPermisos(),
            'rutaModulo'  => $this->getRutaModulo(),
            'vistaConfig' => \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo()),
            'mesDefecto'  => (new \DateTimeImmutable('first day of this month'))->modify('-1 month')->format('Y-m'),
            'mesMaximo'   => date('Y-m'),
            'fullWidth'   => true,
            'base'        => BASE_URL,
        ]);
    }

    private function getFiltros(): array
    {
        $mes = (string) ($_REQUEST['mes'] ?? '');
        return [
            'mes'        => preg_match('/^\d{4}-\d{2}$/', $mes) ? $mes : date('Y-m'),
            'id_cliente' => (int) ($_REQUEST['id_cliente'] ?? 0) ?: null,
        ];
    }

    public function generarAjax(): void
    {
        $this->requireLeer();
        try {
            $f   = $this->getFiltros();
            $rep = $this->service->reporteSaldos((int) $_SESSION['id_empresa'], $f['mes'], $f['id_cliente'], $this->idUsuarioFiltro());
            $qs  = http_build_query(['mes' => $f['mes'], 'id_cliente' => $f['id_cliente'] ?? '']);
            $url = BASE_URL . '/' . $this->getRutaModulo();
            $this->json(['ok' => true] + $rep + [
                'excel_url' => $url . '/exportExcel?' . $qs,
                'pdf_url'   => $url . '/exportPdf?' . $qs,
            ]);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['ok' => false, 'mensaje' => 'No se pudo generar el reporte.']);
        }
    }

    public function buscarClientesAjax(): void
    {
        $this->requireLeer();
        $this->json(['ok' => true, 'data' => $this->service->buscarClientes(
            (int) $_SESSION['id_empresa'], trim((string) ($_GET['q'] ?? '')), $this->idUsuarioFiltro()
        )]);
    }

    // ── Acciones del contador: devengo del mes y apertura ───────────────────
    // El devengo corre solo cada día (cron). Estas acciones sirven para adelantar un mes,
    // revertirlo o registrar la apertura; los permisos son los de este módulo.

    /** Respuesta de error de una acción: los errores de negocio se muestran tal cual; el resto se registra. */
    private function errorAccion(\Throwable $e): never
    {
        if (!($e instanceof \DomainException || $e instanceof \InvalidArgumentException || $e instanceof \RuntimeException)) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'] ?? '']);
        }
        $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
    }

    /** Vista previa del devengo de un mes (?mes=YYYY-MM). */
    public function devengoMesPreviewAjax(): void
    {
        $this->requireLeer();
        try {
            $this->json(['ok' => true] + $this->service->previsualizarMes((int) $_SESSION['id_empresa'], trim((string) ($_GET['mes'] ?? ''))));
        } catch (\Throwable $e) {
            $this->errorAccion($e);
        }
    }

    /** Genera (adelanta) el asiento de devengo del mes (POST mes=YYYY-MM). */
    public function devengarMesAjax(): void
    {
        $this->requireCrear();
        try {
            $res = $this->service->devengarMes((int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario'], trim((string) ($_POST['mes'] ?? '')));
            $this->json(['ok' => true, 'mensaje' => 'Devengo del mes registrado.'] + $res);
        } catch (\Throwable $e) {
            $this->errorAccion($e);
        }
    }

    /** Revierte el devengo de un mes: anula sus asientos (POST mes=YYYY-MM). */
    public function revertirDevengoMesAjax(): void
    {
        $this->requireEliminar();
        try {
            $res = $this->service->revertirMes((int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario'], trim((string) ($_POST['mes'] ?? '')));
            $this->json(['ok' => true, 'mensaje' => 'Devengo del mes revertido.'] + $res);
        } catch (\Throwable $e) {
            $this->errorAccion($e);
        }
    }

    /** Vista previa de la apertura al cierre de un mes (?mes=YYYY-MM). */
    public function aperturaPreviewAjax(): void
    {
        $this->requireLeer();
        try {
            $this->json(['ok' => true] + $this->service->previsualizarApertura((int) $_SESSION['id_empresa'], trim((string) ($_GET['mes'] ?? ''))));
        } catch (\Throwable $e) {
            $this->errorAccion($e);
        }
    }

    /** Registra la apertura (POST mes=YYYY-MM). */
    public function aplicarAperturaAjax(): void
    {
        $this->requireCrear();
        try {
            $res = $this->service->aplicarApertura((int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario'], trim((string) ($_POST['mes'] ?? '')));
            $this->json(['ok' => true, 'mensaje' => 'Apertura registrada.'] + $res);
        } catch (\Throwable $e) {
            $this->errorAccion($e);
        }
    }

    /** Revierte la apertura de un mes de corte (POST mes=YYYY-MM). */
    public function revertirAperturaAjax(): void
    {
        $this->requireEliminar();
        try {
            $res = $this->service->revertirApertura((int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario'], trim((string) ($_POST['mes'] ?? '')));
            $this->json(['ok' => true, 'mensaje' => 'Apertura revertida.'] + $res);
        } catch (\Throwable $e) {
            $this->errorAccion($e);
        }
    }

    // ── Exportaciones ────────────────────────────────────────────────────────

    public function exportExcel(): void
    {
        $this->requireLeer();
        try {
            $autoload = \MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) require_once $autoload;
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $f = $this->getFiltros();
            $libro = $this->service->reporteSaldosExcel(
                $idEmpresa, $f['mes'], (string) ((new \App\models\Empresa())->getPorId($idEmpresa)['nombre'] ?? ''),
                $f['id_cliente'], $this->idUsuarioFiltro()
            );
            (new \App\Services\ReportService())->descargarSpreadsheet($libro, 'Ingresos_Diferidos');
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            if (!headers_sent()) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['error' => $e->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE);
            }
        }
        exit;
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        try {
            $f   = $this->getFiltros();
            $rep = $this->service->reporteSaldos($idEmpresa, $f['mes'], $f['id_cliente'], $this->idUsuarioFiltro());
        } catch (\Throwable $e) {
            if (!headers_sent()) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['error' => $e->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE);
            }
            exit;
        }
        $empresa = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];

        $autoload = \MVC_ROOT . '/vendor/autoload.php';
        if (file_exists($autoload)) require_once $autoload;

        $money = fn ($v) => $v === null ? '—' : '$' . number_format((float) $v, 2);
        $t = $rep['totales'];

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
            <h4>Ingresos diferidos de suscripciones — saldos al <?= date('d-m-Y', strtotime($rep['fecha_corte'])) ?></h4>
            <p style="font-size:8pt">Generado: <?= date('d-m-Y H:i:s') ?></p>
        </div>
        <table class="kpi" style="margin-bottom:10px">
            <tr>
                <td class="c"><strong>Diferido corriente</strong><br><?= $money($t['corriente']) ?></td>
                <td class="c"><strong>Diferido no corriente</strong><br><?= $money($t['no_corriente']) ?></td>
                <td class="c"><strong>Por facturar</strong><br><?= $money($t['por_facturar']) ?></td>
                <td class="c"><strong>Documentos</strong><br><?= count($rep['filas']) ?></td>
            </tr>
        </table>
        <table style="margin-bottom:10px">
            <thead><tr>
                <th style="width:30%">Conciliación</th><th style="width:28%">Cuenta</th>
                <th style="width:14%" class="r">Según cronograma</th><th style="width:14%" class="r">Según mayor</th><th style="width:14%" class="r">Diferencia</th>
            </tr></thead>
            <tbody>
                <?php foreach ($rep['conciliacion'] as $c): ?>
                    <tr>
                        <td><?= htmlspecialchars($c['concepto']) ?></td>
                        <td><?= htmlspecialchars($c['cuenta'] ?: 'sin cuenta configurada') ?></td>
                        <td class="r"><?= $money($c['cronograma']) ?></td>
                        <td class="r"><?= $money($c['mayor']) ?></td>
                        <td class="r"><?= $money($c['diferencia']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!empty($rep['conciliacion_nota'])): ?>
            <p style="font-size:7pt"><?= htmlspecialchars($rep['conciliacion_nota']) ?></p>
        <?php endif; ?>
        <table>
            <thead><tr>
                <th style="width:24%">Cliente</th><th style="width:11%">RUC/Cédula</th><th style="width:7%" class="c">Suscripción</th>
                <th style="width:15%">Documento</th><th style="width:8%">Fecha</th><th style="width:7%">Último mes</th>
                <th style="width:10%" class="r">Corriente</th><th style="width:9%" class="r">No corriente</th><th style="width:9%" class="r">Por facturar</th>
            </tr></thead>
            <tbody>
                <?php foreach ($rep['filas'] as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['cliente']) ?></td>
                        <td><?= htmlspecialchars($r['identificacion']) ?></td>
                        <td class="c">#<?= (int) $r['id_suscripcion'] ?></td>
                        <td><?= htmlspecialchars($r['documento']) ?></td>
                        <td><?= $r['fecha'] ? date('d-m-Y', strtotime($r['fecha'])) : '' ?></td>
                        <td><?= htmlspecialchars($r['ultimo_mes']) ?></td>
                        <td class="r"><?= $r['corriente'] ? $money($r['corriente']) : '' ?></td>
                        <td class="r"><?= $r['no_corriente'] ? $money($r['no_corriente']) : '' ?></td>
                        <td class="r"><?= $r['por_facturar'] ? $money($r['por_facturar']) : '' ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($rep['filas'])): ?>
                    <tr><td colspan="9" class="c">Sin saldos de ingresos diferidos a esa fecha.</td></tr>
                <?php else: ?>
                    <tr>
                        <td colspan="6"><strong>TOTAL</strong></td>
                        <td class="r"><strong><?= $money($t['corriente']) ?></strong></td>
                        <td class="r"><strong><?= $money($t['no_corriente']) ?></strong></td>
                        <td class="r"><strong><?= $money($t['por_facturar']) ?></strong></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
        $html = ob_get_clean();
        try {
            $pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $pdf->writeHTML($html);
            $pdf->output('Ingresos_Diferidos_' . date('Ymd_His') . '.pdf', 'D');
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
        exit;
    }
}
