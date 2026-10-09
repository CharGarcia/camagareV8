<?php
declare(strict_types=1);

namespace App\controllers\modulos;

use App\Helpers\OrdenListado;
use App\Helpers\PreferenciasHelper;
use App\Services\ErrorLogService;
use App\Services\modulos\CierreEjercicioService;

/**
 * Cierre del Ejercicio: genera, consulta y revierte el paso de un año contable al siguiente
 * (asiento de cierre al 31-12, asiento de apertura al 01-01 y bloqueo del año).
 * La lógica vive en CierreEjercicioService.
 */
class CierreEjercicioController extends BaseModuloController
{
    private const RUTA_MODULO = 'modulos/cierre_ejercicio';

    private CierreEjercicioService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = CierreEjercicioService::crear();
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function index(): void
    {
        $this->requireLeer();
        $perm = $this->getPermisos();
        $prefsVista = PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $orden = OrdenListado::leer($prefsVista, 'anio', 'DESC');
        $this->porPagina();

        $this->viewWithLayout('layouts.main', 'modulos.cierre_ejercicio.index', [
            'titulo'          => 'Cierre del Ejercicio',
            'fullWidth'       => true,
            'perm'            => $perm,
            'rutaModulo'      => self::RUTA_MODULO,
            'vistaConfig'     => $prefsVista,
            'ordenCol'        => OrdenListado::primeraCol($orden, 'anio'),
            'ordenDir'        => OrdenListado::primeraDir($orden, 'DESC'),
            'tablaDisponible' => $this->service->tablaDisponible(),
        ]);
    }

    /** GET: filas del listado (JSON); la vista las pinta. */
    public function searchAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        try {
            if (!$this->service->tablaDisponible()) {
                $this->json(['ok' => true, 'rows' => [], 'total' => 0, 'page' => 1, 'total_pages' => 1]);
            }
            $prefsVista = PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
            $orden   = OrdenListado::leer($prefsVista, 'anio', 'DESC');
            $buscar  = trim((string) ($_GET['b'] ?? ''));
            $page    = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = $this->porPagina();

            $res = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $orden, $this->filtroPropios());
            $rows = array_map(fn(array $r) => $this->formatearFila($r), $res['rows']);
            $qs = '?b=' . urlencode($buscar) . '&orden=' . urlencode(OrdenListado::aCadena($orden));

            $this->json([
                'ok'          => true,
                'rows'        => $rows,
                'total'       => $res['total'],
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => $perPage > 0 ? max(1, (int) ceil($res['total'] / $perPage)) : 1,
                'pdf_url'     => BASE_URL . '/' . self::RUTA_MODULO . '/exportPdf' . $qs,
                'excel_url'   => BASE_URL . '/' . self::RUTA_MODULO . '/exportExcel' . $qs,
            ]);
        } catch (\Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /** GET: años disponibles, año sugerido y configuración, para el modal de cierre nuevo. */
    public function contextoAjax(): void
    {
        $this->requireCrear();
        try {
            $this->json(['ok' => true, 'data' => $this->service->getContextoNuevo((int) $_SESSION['id_empresa'])]);
        } catch (\Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /** GET: vista previa de los dos asientos (no graba nada). */
    public function calcularAjax(): void
    {
        $this->requireCrear();
        try {
            $anio  = (int) ($_GET['anio'] ?? 0);
            $desde = array_key_exists('saldos_desde', $_GET) ? (string) $_GET['saldos_desde'] : 'auto';
            $this->json(['ok' => true, 'data' => $this->service->calcular((int) $_SESSION['id_empresa'], $anio, $desde)]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /** POST: genera el cierre del año. */
    public function generar(): void
    {
        $this->requireCrear();
        try {
            $anio  = (int) ($_POST['anio'] ?? 0);
            $desde = array_key_exists('saldos_desde', $_POST) ? (string) $_POST['saldos_desde'] : 'auto';
            $res = $this->service->generar(
                (int) $_SESSION['id_empresa'],
                (int) $_SESSION['id_usuario'],
                $anio,
                $desde,
                (string) ($_POST['observaciones'] ?? ''),
                $_POST['token_guardado'] ?? null
            );
            $msg = $res['existente']
                ? "El cierre del ejercicio {$res['anio']} ya estaba registrado; no se creó otro."
                : "Ejercicio {$res['anio']} cerrado: se registraron los asientos de cierre y apertura y se bloqueó el año.";
            $this->json(['ok' => true, 'id' => $res['id'], 'existente' => $res['existente'], 'msg' => $msg]);
        } catch (\Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /** GET: detalle de un cierre con las líneas de sus dos asientos. */
    public function detalleAjax(): void
    {
        $this->requireLeer();
        try {
            $c = $this->service->getDetalle((int) ($_GET['id'] ?? 0), (int) $_SESSION['id_empresa']);
            $this->requireRegistroPropio($c);
            $this->json(['ok' => true, 'data' => $this->formatearFila($c) + [
                'lineas_cierre'   => $c['lineas_cierre'],
                'lineas_apertura' => $c['lineas_apertura'],
                'es_ultimo'       => $c['es_ultimo'],
            ]]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /** POST: revierte el último cierre vigente. */
    public function revertir(): void
    {
        $this->requireEliminar();
        try {
            $this->service->revertir(
                (int) ($_POST['id'] ?? 0),
                (int) $_SESSION['id_empresa'],
                (int) $_SESSION['id_usuario'],
                (string) ($_POST['motivo'] ?? '')
            );
            $this->json(['ok' => true, 'msg' => 'Cierre revertido: se anularon sus asientos y se reabrió el año.']);
        } catch (\Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        [$rows, $nombreEmpresa] = $this->filasExportacion();
        $headers = ['Año', 'Fecha cierre', 'Saldos desde', 'Asiento cierre', 'Asiento apertura', 'Resultado del año',
                    'Resultados anteriores', 'Activos', 'Pasivos', 'Patrimonio', 'Estado', 'Registrado por', 'Registrado el'];
        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                $r['anio'], $r['fmt_fecha_cierre'], $r['fmt_saldos_desde'], $r['numero_cierre'] ?? '', $r['numero_apertura'] ?? '',
                (float) $r['resultado'], (float) $r['resultado_anterior'], (float) $r['total_activos'],
                (float) $r['total_pasivos'], (float) $r['total_patrimonio'], $r['estado_texto'],
                $r['creado_por_nombre'] ?? '', $r['fmt_created_at'],
            ];
        }
        (new \App\Services\ReportService())->exportToExcel('CierreEjercicio', $headers, $data, 'Cierre del Ejercicio', $nombreEmpresa);
        exit;
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        [$rows, $nombreEmpresa] = $this->filasExportacion();
        $autoload = MVC_ROOT . '/vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }
        $n = static fn($v) => number_format((float) $v, 2, ',', '.');
        ob_start();
        ?>
        <style>
            table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 8pt; }
            th { background: #f2f2f2; border: 1px solid #ccc; padding: 4px; text-align: left; }
            td { border: 1px solid #ccc; padding: 4px; }
            .r { text-align: right; }
            h1 { margin: 0; font-size: 14pt; text-align: center; }
            h2 { margin: 3px 0 12px 0; font-size: 10pt; text-align: center; color: #666; }
        </style>
        <page backtop="10mm" backbottom="10mm" backleft="8mm" backright="8mm">
            <h1><?= htmlspecialchars($nombreEmpresa) ?></h1>
            <h2>CIERRE DEL EJERCICIO</h2>
            <table>
                <thead>
                    <tr>
                        <th style="width:7%">Año</th>
                        <th style="width:11%">Saldos desde</th>
                        <th style="width:12%">Asiento cierre</th>
                        <th style="width:12%">Asiento apertura</th>
                        <th style="width:13%" class="r">Resultado del año</th>
                        <th style="width:13%" class="r">Activos</th>
                        <th style="width:13%" class="r">Patrimonio</th>
                        <th style="width:9%">Estado</th>
                        <th style="width:10%">Registrado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td style="width:7%"><?= (int) $r['anio'] ?></td>
                            <td style="width:11%"><?= htmlspecialchars($r['fmt_saldos_desde']) ?></td>
                            <td style="width:12%"><?= htmlspecialchars((string) ($r['numero_cierre'] ?? '')) ?></td>
                            <td style="width:12%"><?= htmlspecialchars((string) ($r['numero_apertura'] ?? '')) ?></td>
                            <td style="width:13%" class="r"><?= $n($r['resultado']) ?></td>
                            <td style="width:13%" class="r"><?= $n($r['total_activos']) ?></td>
                            <td style="width:13%" class="r"><?= $n($r['total_patrimonio']) ?></td>
                            <td style="width:9%"><?= htmlspecialchars($r['estado_texto']) ?></td>
                            <td style="width:10%"><?= htmlspecialchars(substr($r['fmt_created_at'], 0, 10)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </page>
        <?php
        $html = ob_get_clean();
        try {
            $pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $pdf->writeHTML($html);
            $pdf->output('CierreEjercicio_' . date('Ymd_His') . '.pdf', 'D');
        } catch (\Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            header('Content-Type: text/html; charset=utf-8');
            echo 'Error al generar PDF: ' . htmlspecialchars($e->getMessage());
        }
        exit;
    }

    // ── Internos ────────────────────────────────────────────────────────────

    /** §6: sin acceso total, solo los cierres que registró el usuario. */
    private function filtroPropios(): ?int
    {
        return empty($this->getPermisos()['todo']) ? (int) $_SESSION['id_usuario'] : null;
    }

    private function filasExportacion(): array
    {
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $prefsVista = PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $orden = OrdenListado::leer($prefsVista, 'anio', 'DESC');
        $res = $this->service->tablaDisponible()
            ? $this->service->getListado($idEmpresa, trim((string) ($_GET['b'] ?? '')), 1, 0, $orden, $this->filtroPropios())
            : ['rows' => []];
        $empresa = (new \App\models\Empresa())->getPorId($idEmpresa);
        return [array_map(fn(array $r) => $this->formatearFila($r), $res['rows']), (string) ($empresa['nombre'] ?? '')];
    }

    private function formatearFila(array $r): array
    {
        $f  = static fn($d) => !empty($d) ? date('d-m-Y', strtotime((string) $d)) : '';
        $fh = static fn($d) => !empty($d) ? date('d-m-Y H:i:s', strtotime((string) $d)) : '';
        $r['fmt_fecha_cierre']   = $f($r['fecha_cierre'] ?? null);
        $r['fmt_fecha_apertura'] = $f($r['fecha_apertura'] ?? null);
        $r['fmt_saldos_desde']   = !empty($r['saldos_desde']) ? $f($r['saldos_desde']) : 'Todo el histórico';
        $r['fmt_created_at']     = $fh($r['created_at'] ?? null);
        $r['fmt_revertido_at']   = $fh($r['revertido_at'] ?? null);
        $r['estado_texto']       = ($r['estado'] ?? '') === 'vigente' ? 'Vigente' : 'Revertido';
        unset($r['periodos']);
        return $r;
    }
}
