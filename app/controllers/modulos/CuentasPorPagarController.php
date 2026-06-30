<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\CuentasPorPagarRepository;
use App\Services\LogSistemaService;
use PDO;

class CuentasPorPagarController extends BaseModuloController
{
    private CuentasPorPagarRepository $repo;
    private LogSistemaService $log;

    protected function getRutaModulo(): string
    {
        return 'modulos/cuentas_por_pagar';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repo = new CuentasPorPagarRepository();
        $this->log  = new LogSistemaService();
    }

    // ─────────────────────────────────────────────────────────────────────
    // HELPERS JSON
    // ─────────────────────────────────────────────────────────────────────

    private function jsonSuccess(array $data): never
    {
        $this->json(array_merge(['ok' => true], $data));
    }

    private function jsonError(string $mensaje, int $code = 200): never
    {
        $this->json(['ok' => false, 'error' => $mensaje], $code);
    }

    // ─────────────────────────────────────────────────────────────────────
    // VISTA PRINCIPAL
    // ─────────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $anios      = $this->repo->getAniosDisponibles($idEmpresa);
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());

        $this->viewWithLayout('layouts.main', 'modulos/cuentas_por_pagar/index', [
            'titulo'      => 'Cuentas por Pagar',
            'perm'        => $this->getPermisos(),
            'vistaConfig' => $prefsVista,
            'rutaModulo'  => $this->getRutaModulo(),
            'anios'       => $anios,
            'fullWidth'   => true,
            'base'        => BASE_URL,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – LISTADO PRINCIPAL
    // ─────────────────────────────────────────────────────────────────────

    public function generarAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros   = $this->getFiltros();

        $filas      = $this->repo->getListado($idEmpresa, $filtros);
        $stats      = $this->repo->getEstadisticas($idEmpresa, $filtros);
        $antiguedad = $this->repo->getAntiguedad($idEmpresa, $filtros);

        foreach ($filas as &$f) {
            $f['total']          = number_format((float)$f['total'],          2, '.', '');
            $f['total_pagado']   = number_format((float)$f['total_pagado'],   2, '.', '');
            $f['total_nc']       = number_format((float)($f['total_nc'] ?? 0),2, '.', '');
            $f['total_nd']       = number_format((float)($f['total_nd'] ?? 0),2, '.', '');
            $f['total_retenido'] = number_format((float)($f['total_retenido'] ?? 0), 2, '.', '');
            $f['saldo']          = number_format((float)$f['saldo'],          2, '.', '');
            $f['dias_vencido']   = (int)($f['dias_vencido'] ?? 0);
        }
        unset($f);

        $this->jsonSuccess([
            'filas'      => $filas,
            'stats'      => $stats,
            'antiguedad' => $antiguedad,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – REGISTRAR PAGO
    // ─────────────────────────────────────────────────────────────────────

    public function registrarPagoAjax(): void
    {
        $this->requireCrear();
        $idEmpresa   = (int) $_SESSION['id_empresa'];
        $idUsuario   = (int) $_SESSION['id_usuario'];

        $idDoc       = (int)($_POST['id_doc']           ?? 0);
        $tipoFuente  = trim($_POST['tipo_fuente']        ?? 'COMPRA');
        $monto       = (float)($_POST['monto']           ?? 0);
        $idFormaPago = (int)($_POST['id_forma_pago']     ?? 0);
        $idPunto     = (int)($_POST['id_punto_emision']  ?? 0);
        $idConcepto  = !empty($_POST['id_egreso_concepto']) ? (int)$_POST['id_egreso_concepto'] : null;
        $fechaPago   = trim($_POST['fecha_pago']          ?? date('Y-m-d'));
        $observ      = trim($_POST['observaciones']       ?? '');
        $tipoOp      = trim($_POST['tipo_operacion_bancaria'] ?? '');
        $numOp       = trim($_POST['numero_operacion']        ?? '');
        // fecha_cobro: aplica cuando tipo_operacion es CHEQUE; de lo contrario se usa fecha_pago
        $fechaCobro  = !empty($_POST['fecha_cobro']) ? trim($_POST['fecha_cobro']) : $fechaPago;

        if ($idDoc <= 0 || $monto <= 0 || $idFormaPago <= 0 || $idPunto <= 0) {
            $this->jsonError('Datos incompletos. Verifique serie, monto y forma de pago.');
        }

        // Validar punto de emisión
        $punto = $this->repo->getPuntoEmisionPorId($idPunto, $idEmpresa);
        if (!$punto) {
            $this->jsonError('Punto de emisión no válido.');
        }

        // Validar documento y saldo
        $doc = $this->repo->getDocumentoParaPago($idDoc, $tipoFuente, $idEmpresa);
        if (!$doc) {
            $this->jsonError('Documento no encontrado.');
        }

        $saldo     = (float)$doc['saldo'];
        $totalDoc  = (float)$doc['importe_total'];
        $tipoDocEg = $tipoFuente === 'LIQUIDACION' ? 'LIQUIDACION' : 'COMPRA';

        if ($saldo <= 0) {
            $this->jsonError('Este documento ya se encuentra pagado.');
        }
        if ($monto > $saldo + 0.001) {
            $this->jsonError("El monto ($monto) supera el saldo pendiente ($saldo).");
        }

        try {
            $secuencialService = new \App\Services\SecuencialService();
            $secRes    = $secuencialService->obtenerSiguienteSecuencial($idPunto, 'Egresos');
            $secuencial = $secRes['formateado'];

            $codEst  = str_pad((string)($punto['establecimiento'] ?? '001'), 3, '0', STR_PAD_LEFT);
            $codPto  = str_pad((string)($punto['punto']           ?? '001'), 3, '0', STR_PAD_LEFT);
            $numEgr  = "{$codEst}-{$codPto}-{$secuencial}";
            $numDoc  = $doc['numero_documento'] ?? '';

            $payload = [
                'id_empresa'         => $idEmpresa,
                'id_punto_emision'   => $idPunto,
                'establecimiento'    => $codEst,
                'punto_emision'      => $codPto,
                'secuencial'         => $secuencial,
                'numero_egreso'      => $numEgr,
                'fecha_emision'      => $fechaPago ?: date('Y-m-d'),
                'tipo_egreso'        => $tipoFuente === 'LIQUIDACION' ? 'COMPRA_LIQUIDACION' : 'COMPRA_FACTURA',
                'tipo_sujeto'        => 'PROVEEDOR',
                'id_proveedor'       => (int)$doc['id_proveedor'],
                'id_empleado'        => null,
                'id_egreso_concepto' => $idConcepto,
                'monto_total'        => $monto,
                'observaciones'      => $observ ?: "Pago de {$tipoDocEg} {$numDoc}",
                'estado'             => 'registrado',
                'usuario_id'         => $idUsuario,
                'detalles' => [[
                    'tipo_documento'          => $tipoDocEg,
                    'id_referencia_documento' => $idDoc,
                    'numero_documento'        => $numDoc,
                    'descripcion'             => "Pago de {$tipoDocEg} {$numDoc}",
                    'monto_documento'         => $totalDoc,
                    'saldo_anterior'          => $saldo,
                    'monto_pagado'            => $monto,
                    'saldo_actual'            => max(0.0, $saldo - $monto),
                ]],
                'pagos' => [[
                    'id_forma_pago'           => $idFormaPago,
                    'monto'                   => $monto,
                    'fecha_cobro'             => $fechaCobro,
                    'referencia'              => $numOp  ?: null,
                    'tipo_operacion_bancaria' => $tipoOp ?: null,
                    'numero_cheque'           => ($tipoOp === 'CHEQUE' ? $numOp : null) ?: null,
                ]],
            ];

            $egresoService = new \App\Services\modulos\EgresoService(
                new \App\repositories\modulos\EgresoRepository(),
                new \App\Rules\modulos\EgresoRules(),
                $this->log
            );

            $idEgreso = $egresoService->registrar($payload);

            $nuevoSaldo = $saldo - $monto;
            $this->jsonSuccess([
                'mensaje'      => "Pago registrado correctamente. Egreso: {$numEgr}",
                'id_egreso'    => $idEgreso,
                'numero_egreso'=> $numEgr,
                'nuevo_saldo'  => number_format($nuevoSaldo, 2, '.', ''),
                'pagado'       => $nuevoSaldo <= 0.001,
            ]);
        } catch (\Throwable $e) {
            error_log('[CxP registrarPago] ' . $e->getMessage());
            $this->jsonError('Error al registrar el pago: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – DATOS EN TIEMPO REAL PARA EL MODAL DE PAGO
    // ─────────────────────────────────────────────────────────────────────

    public function getDocumentoParaPagoInfoAjax(): void
    {
        $this->requireLeer();
        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $idDoc      = (int) ($_GET['id_doc']       ?? 0);
        $tipoFuente = trim($_GET['tipo_fuente']    ?? 'COMPRA');

        if ($idDoc <= 0) {
            $this->jsonError('ID inválido.');
            return;
        }

        $doc = $this->repo->getDocumentoParaPago($idDoc, $tipoFuente, $idEmpresa);
        if (!$doc) {
            $this->jsonError('Documento no encontrado.');
            return;
        }

        $this->jsonSuccess(['doc' => $doc]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – HISTORIAL DE PAGOS
    // ─────────────────────────────────────────────────────────────────────

    public function historialPagosAjax(): void
    {
        $this->requireLeer();
        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $idDoc      = (int)($_GET['id_doc']       ?? 0);
        $tipoFuente = trim($_GET['tipo_fuente']   ?? 'COMPRA');

        if ($idDoc <= 0) {
            $this->jsonError('ID de documento inválido.');
        }

        $historial = $this->repo->getHistorialPagos($idDoc, $tipoFuente, $idEmpresa);
        $this->jsonSuccess(['historial' => $historial]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – CATÁLOGOS PARA EL MODAL PAGO
    // ─────────────────────────────────────────────────────────────────────

    public function getCatalogosPagoAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $this->jsonSuccess([
            'puntos'    => $this->repo->getPuntosEmision($idEmpresa),
            'conceptos' => $this->repo->getConceptos($idEmpresa),
            'formas'    => $this->repo->getFormasPago($idEmpresa),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – SIGUIENTE SECUENCIAL DE EGRESO
    // ─────────────────────────────────────────────────────────────────────

    public function getSecuencialAjax(): void
    {
        $this->requireLeer();
        $idPunto = (int)($_GET['id_punto_emision'] ?? 0);
        if ($idPunto <= 0) {
            $this->jsonError('ID de punto de emisión inválido.');
        }
        $secuencialService = new \App\Services\SecuencialService();
        $res = $secuencialService->obtenerSiguienteSecuencial($idPunto, 'Egresos');
        $this->jsonSuccess($res);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – BÚSQUEDA DE PROVEEDORES
    // ─────────────────────────────────────────────────────────────────────

    public function getProveedoresAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $q         = trim($_GET['q'] ?? '');

        if (strlen($q) < 2) {
            $this->jsonSuccess(['proveedores' => []]);
        }

        $sql = "SELECT id, razon_social AS nombre, identificacion
                FROM proveedores
                WHERE id_empresa = :id_empresa
                  AND eliminado  = false
                  AND (LOWER(razon_social) LIKE :q OR identificacion LIKE :q2)
                ORDER BY razon_social LIMIT 15";

        $st = $this->repo->getDb()->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':q' => '%' . strtolower($q) . '%', ':q2' => '%' . $q . '%']);
        $this->jsonSuccess(['proveedores' => $st->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // EXPORTACIÓN EXCEL
    // ─────────────────────────────────────────────────────────────────────

    public function exportExcel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros   = $this->getFiltros();

        $filas = $this->repo->getListado($idEmpresa, $filtros);

        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="cuentas_por_pagar_' . date('Ymd_His') . '.xls"');
        header('Cache-Control: max-age=0');
        echo "\xEF\xBB\xBF"; // BOM UTF-8

        $headers = ['Tipo', 'Documento', 'Proveedor', 'RUC', 'F.Emisión', 'F.Vencimiento', 'Días Vencidos', 'Total', 'Pagado', 'NC/Ret.', 'Saldo', 'Estado'];
        echo implode("\t", $headers) . "\n";

        foreach ($filas as $r) {
            $dias     = (int)($r['dias_vencido'] ?? 0);
            $saldo    = (float)$r['saldo'];
            $estadoCxP = $saldo <= 0 ? 'PAGADA' : ($dias > 0 ? "VENCIDA ({$dias} días)" : 'VIGENTE');
            $ncRet = number_format((float)($r['total_nc'] ?? 0) + (float)($r['total_retenido'] ?? 0), 2);
            echo implode("\t", [
                $r['tipo_fuente']     === 'LIQUIDACION' ? 'Liquidación' : 'Factura',
                $r['numero_documento'] ?? '',
                $r['proveedor_nombre'] ?? '',
                $r['proveedor_ruc']    ?? '',
                $r['fecha_emision'] ? date('d-m-Y', strtotime($r['fecha_emision'])) : '',
                $r['fecha_vencimiento'] ? date('d-m-Y', strtotime($r['fecha_vencimiento'])) : '',
                $dias > 0 ? $dias : 0,
                number_format((float)$r['total'], 2),
                number_format((float)$r['total_pagado'], 2),
                $ncRet,
                number_format($saldo, 2),
                $estadoCxP,
            ]) . "\n";
        }
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────
    // EXPORTACIÓN PDF
    // ─────────────────────────────────────────────────────────────────────

    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros   = $this->getFiltros();

        $filas = $this->repo->getListado($idEmpresa, $filtros);
        $stats = $this->repo->getEstadisticas($idEmpresa, $filtros);

        try {
            $empresa       = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
            $nombreEmpresa = $empresa['nombre'] ?? 'Cuentas por Pagar';

            $totalSaldo   = 0;
            $totalPagado  = 0;
            $totalTotal   = 0;
            $filaHtml     = '';

            foreach ($filas as $r) {
                $dias  = (int)($r['dias_vencido'] ?? 0);
                $ts    = (float)$r['total'];
                $tp    = (float)$r['total_pagado'];
                $tsal  = (float)$r['saldo'];
                $tret  = (float)($r['total_retenido'] ?? 0) + (float)($r['total_nc'] ?? 0);
                $totalTotal  += $ts;
                $totalPagado += $tp;
                $totalSaldo  += $tsal;
                $color = $dias > 0 && $tsal > 0 ? 'color:#dc3545;' : '';
                $badge = $tsal <= 0
                    ? "<small style='color:#6c757d;'>Pagada</small>"
                    : ($dias > 0
                        ? "<small style='color:#dc3545;font-weight:bold;'>{$dias}d vencida</small>"
                        : "<small style='color:#198754;'>Vigente</small>");
                $fVenc = !empty($r['fecha_vencimiento']) ? date('d-m-Y', strtotime($r['fecha_vencimiento'])) : '—';
                $fEmis = !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '—';
                $tipo  = $r['tipo_fuente'] === 'LIQUIDACION' ? 'Liq.' : 'Fac.';

                $filaHtml .= "<tr style='{$color}'>
                    <td><small style='color:#6c757d;'>{$tipo}</small><br>" . htmlspecialchars($r['numero_documento'] ?? '') . "</td>
                    <td>" . htmlspecialchars($r['proveedor_nombre'] ?? '') . "<br><small style='color:#6c757d;'>" . htmlspecialchars($r['proveedor_ruc'] ?? '') . "</small></td>
                    <td class='text-center'>{$fEmis}</td>
                    <td class='text-center'>{$fVenc}<br>{$badge}</td>
                    <td class='text-end'>\$" . number_format($ts, 2) . "</td>
                    <td class='text-end' style='color:#198754;'>\$" . number_format($tp + $tret, 2) . "</td>
                    <td class='text-end' style='{$color}font-weight:bold;'>\$" . number_format($tsal, 2) . "</td>
                </tr>";
            }

            ob_start();
            ?>
            <style>
                body { font-family: Arial, sans-serif; font-size: 8pt; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
                th { background: #e9ecef; border: 1px solid #ccc; padding: 4px 5px; text-align: center; font-size: 8pt; }
                td { border: 1px solid #ddd; padding: 3px 5px; font-size: 7.5pt; }
                .text-end { text-align: right; }
                .text-center { text-align: center; }
                .header { text-align: center; margin-bottom: 12px; }
                .stats { display: table; width: 100%; margin-bottom: 10px; }
                .stat-box { display: table-cell; border: 1px solid #ccc; padding: 5px; text-align: center; }
                .stat-val { font-size: 11pt; font-weight: bold; }
            </style>
            <div class="header">
                <h2><?= htmlspecialchars($nombreEmpresa) ?></h2>
                <h3>Cuentas por Pagar</h3>
                <p>Generado: <?= date('d-m-Y H:i:s') ?></p>
            </div>
            <div class="stats">
                <div class="stat-box">
                    <div>Documentos</div>
                    <div class="stat-val"><?= $stats['total_docs'] ?></div>
                </div>
                <div class="stat-box">
                    <div>Saldo Total</div>
                    <div class="stat-val">$<?= number_format($stats['total_saldo'], 2) ?></div>
                </div>
                <div class="stat-box" style="color:#dc3545;">
                    <div>Vencido</div>
                    <div class="stat-val">$<?= number_format($stats['total_vencido'], 2) ?></div>
                </div>
                <div class="stat-box" style="color:#198754;">
                    <div>Al Día</div>
                    <div class="stat-val">$<?= number_format($stats['total_al_dia'], 2) ?></div>
                </div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Documento</th>
                        <th>Proveedor / RUC</th>
                        <th>F. Emisión</th>
                        <th>F. Vencimiento</th>
                        <th>Total</th>
                        <th>Pagado/Ret/NC</th>
                        <th>Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    <?= $filaHtml ?>
                </tbody>
                <tfoot>
                    <tr style="background:#f8f9fa;font-weight:bold;">
                        <th colspan="4" class="text-end">TOTALES:</th>
                        <th class="text-end">$<?= number_format($totalTotal, 2) ?></th>
                        <th class="text-end" style="color:#198754;">$<?= number_format($totalPagado, 2) ?></th>
                        <th class="text-end" style="color:#dc3545;">$<?= number_format($totalSaldo, 2) ?></th>
                    </tr>
                </tfoot>
            </table>
            <?php
            $html     = ob_get_clean();
            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $html2pdf->writeHTML($html);
            $html2pdf->output('CuentasPorPagar_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // PRIVADOS
    // ─────────────────────────────────────────────────────────────────────

    private function getFiltros(): array
    {
        return [
            'estado'       => $_REQUEST['estado']       ?? 'PENDIENTES',
            'fecha_desde'  => $_REQUEST['fecha_desde']  ?? '',
            'fecha_hasta'  => $_REQUEST['fecha_hasta']  ?? '',
            'id_proveedor' => $_REQUEST['id_proveedor'] ?? '',
            'tipo_fuente'  => $_REQUEST['tipo_fuente']  ?? '',
        ];
    }

    // ─── SALDOS INICIALES CXP (para mostrar en la vista de CXP) ─────────────

    public function getSaldosInicialesCxpAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros = [
            'estado'         => $_GET['estado']         ?? 'TODOS',
            'tipo_documento' => $_GET['tipo_documento']  ?? '',
        ];
        $filas = $this->repo->getSaldosInicialesCxp($idEmpresa, $filtros);
        $this->jsonSuccess(['filas' => $filas]);
    }
}
