<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\CuentasPorCobrarRepository;
use App\services\WhatsappService;
use App\services\EmailConfigService;
use App\Services\LogSistemaService;
use PDO;

class CuentasPorCobrarController extends BaseModuloController
{
    private CuentasPorCobrarRepository $repo;

    protected function getRutaModulo(): string
    {
        return 'modulos/cuentas_por_cobrar';
    }

    private LogSistemaService $log;

    public function __construct()
    {
        parent::__construct();
        $this->repo = new CuentasPorCobrarRepository();
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

        $anios        = $this->repo->getAniosDisponibles($idEmpresa);
        $tieneWA      = $this->repo->tieneWhatsappConfigurado($idEmpresa);
        $prefsVista   = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());

        $this->viewWithLayout('layouts.main', 'modulos/cuentas_por_cobrar/index', [
            'titulo'      => 'Cuentas por Cobrar',
            'perm'        => $this->getPermisos(),
            'vistaConfig' => $prefsVista,
            'rutaModulo'  => $this->getRutaModulo(),
            'anios'       => $anios,
            'tieneWA'     => $tieneWA,
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

        $filtros = $this->getFiltros();

        $filas      = $this->repo->getListado($idEmpresa, $filtros);
        $stats      = $this->repo->getEstadisticas($idEmpresa, $filtros);
        $antiguedad = $this->repo->getAntiguedad($idEmpresa, $filtros);

        // Formateamos filas
        foreach ($filas as &$f) {
            $f['total']        = number_format((float)$f['total'],        2, '.', '');
            $f['total_cobrado']= number_format((float)$f['total_cobrado'],2, '.', '');
            $f['saldo']        = number_format((float)$f['saldo'],        2, '.', '');
            $f['dias_vencido'] = (int)($f['dias_vencido'] ?? 0);
        }
        unset($f);

        $this->jsonSuccess([
            'filas'      => $filas,
            'stats'      => $stats,
            'antiguedad' => $antiguedad,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – REGISTRAR COBRO
    // ─────────────────────────────────────────────────────────────────────

    public function registrarCobroAjax(): void
    {
        $this->requireCrear();
        $idEmpresa    = (int) $_SESSION['id_empresa'];
        $idUsuario    = (int) $_SESSION['id_usuario'];

        $idVenta      = (int)($_POST['id_venta']          ?? 0);
        $monto        = (float)($_POST['monto']           ?? 0);
        $idFormaCobro = (int)($_POST['id_forma_cobro']    ?? 0);
        $idPunto      = (int)($_POST['id_punto_emision']  ?? 0);
        $idConcepto   = !empty($_POST['id_ingreso_concepto']) ? (int)$_POST['id_ingreso_concepto'] : null;
        $fechaCobro   = trim($_POST['fecha_cobro']        ?? date('Y-m-d'));
        $observ       = trim($_POST['observaciones']      ?? '');
        $tipoOp       = trim($_POST['tipo_operacion_bancaria'] ?? '');
        $numOp        = trim($_POST['numero_operacion']        ?? '');

        if ($idVenta <= 0 || $monto <= 0 || $idFormaCobro <= 0 || $idPunto <= 0) {
            $this->jsonError('Datos incompletos. Verifique serie, monto y forma de cobro.');
            return;
        }

        // Validar punto de emisión
        $punto = $this->repo->getPuntoEmisionPorId($idPunto, $idEmpresa);
        if (!$punto) {
            $this->jsonError('Punto de emisión no válido.');
            return;
        }

        // Validar factura y saldo
        $factura = $this->repo->getFacturaParaCobro($idVenta, $idEmpresa);
        if (!$factura) {
            $this->jsonError('Factura no encontrada.');
            return;
        }
        $saldo       = (float)$factura['saldo'];
        $totalFact   = (float)$factura['importe_total'];
        if ($saldo <= 0) {
            $this->jsonError('Esta factura ya se encuentra pagada.');
            return;
        }
        if ($monto > $saldo + 0.001) {
            $this->jsonError("El monto ($monto) supera el saldo pendiente ($saldo).");
            return;
        }

        try {
            // Obtener siguiente secuencial mediante SecuencialService
            $secuencialService = new \App\Services\SecuencialService();
            $secRes    = $secuencialService->obtenerSiguienteSecuencial($idPunto, 'Ingresos');
            $secuencial = $secRes['formateado'];

            $codEst  = str_pad((string)($punto['establecimiento'] ?? '001'), 3, '0', STR_PAD_LEFT);
            $codPto  = str_pad((string)($punto['punto']           ?? '001'), 3, '0', STR_PAD_LEFT);
            $numDoc  = "{$codEst}-{$codPto}-{$secuencial}";
            $numFact = ($factura['establecimiento'] ?? '') . '-'
                     . ($factura['punto_emision']   ?? '') . '-'
                     . ($factura['secuencial']       ?? '');

            // Delegar al IngresoService (igual que FacturaVentaController)
            $payload = [
                'id_empresa'          => $idEmpresa,
                'id_establecimiento'  => (int)($punto['id_establecimiento'] ?? 0),
                'id_punto_emision'    => $idPunto,
                'id_cliente'          => (int)$factura['id_cliente'],
                'id_usuario'          => $idUsuario,
                'fecha_emision'       => $fechaCobro ?: date('Y-m-d'),
                'establecimiento'     => $codEst,
                'punto_emision'       => $codPto,
                'secuencial'          => $secuencial,
                'numero_ingreso'      => $numDoc,
                'tipo_ingreso'        => 'FACTURA_VENTA',
                'id_ingreso_concepto' => $idConcepto,
                'monto_total'         => $monto,
                'observaciones'       => $observ ?: "Cobro de factura {$numFact}",
                'recibo_de'           => $factura['cliente_nombre'] ?? '',
                'id_recibo_cliente'   => (int)$factura['id_cliente'],
                'detalles'            => [[
                    'tipo_documento'          => 'FACTURA',
                    'id_referencia_documento' => $idVenta,
                    'numero_documento'        => $numFact,
                    'descripcion'             => "Cobro de factura {$numFact}",
                    'monto_documento'         => $totalFact,
                    'saldo_anterior'          => $saldo,
                    'monto_cobrado'           => $monto,
                    'saldo_actual'            => max(0.0, $saldo - $monto),
                ]],
                'pagos' => [[
                    'id_forma_cobro'          => $idFormaCobro,
                    'monto'                   => $monto,
                    'fecha_cobro'             => $fechaCobro,
                    'observaciones'           => $observ ?: null,
                    'tipo_operacion_bancaria' => $tipoOp ?: null,
                    'numero_cheque'           => $numOp  ?: null,
                    'referencia'              => $numOp  ?: null,
                ]],
            ];

            $ingresoService = new \App\Services\modulos\IngresoService(
                new \App\repositories\modulos\IngresoRepository(),
                new \App\Rules\modulos\IngresoRules(),
                new \App\Services\LogSistemaService()
            );

            $idIngreso = $ingresoService->crear($payload);

            $nuevoSaldo = $saldo - $monto;
            $this->jsonSuccess([
                'mensaje'        => "Cobro registrado correctamente. Ingreso: {$numDoc}",
                'id_ingreso'     => $idIngreso,
                'numero_ingreso' => $numDoc,
                'nuevo_saldo'    => number_format($nuevoSaldo, 2, '.', ''),
                'pagada'         => $nuevoSaldo <= 0.001,
            ]);
        } catch (\Throwable $e) {
            error_log('[CxC registrarCobro] ' . $e->getMessage());
            $this->jsonError('Error al registrar el cobro: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – HISTORIAL DE COBROS
    // ─────────────────────────────────────────────────────────────────────

    public function historialCobrosAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idVenta   = (int)($_GET['id_venta'] ?? 0);

        if ($idVenta <= 0) {
            $this->jsonError('ID de venta inválido.');
            return;
        }

        $historial = $this->repo->getHistorialCobros($idVenta, $idEmpresa);
        $this->jsonSuccess(['historial' => $historial]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – FORMAS DE COBRO
    // ─────────────────────────────────────────────────────────────────────

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – CATÁLOGOS PARA EL MODAL COBRO (puntos, conceptos, formas)
    // ─────────────────────────────────────────────────────────────────────

    public function getCatalogosCobroAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $this->jsonSuccess([
            'puntos'    => $this->repo->getPuntosEmision($idEmpresa),
            'conceptos' => $this->repo->getConceptos($idEmpresa),
            'formas'    => $this->repo->getFormasCobro($idEmpresa),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – SIGUIENTE SECUENCIAL DE INGRESO PARA UN PUNTO DE EMISIÓN
    // ─────────────────────────────────────────────────────────────────────

    public function getSecuencialAjax(): void
    {
        $this->requireLeer();
        $idPunto = (int) ($_GET['id_punto_emision'] ?? 0);
        if ($idPunto <= 0) {
            $this->jsonError('ID de punto de emisión inválido.');
            return;
        }
        $secuencialService = new \App\Services\SecuencialService();
        $res = $secuencialService->obtenerSiguienteSecuencial($idPunto, 'Ingresos');
        $this->jsonSuccess($res);
    }

    public function getFormasCobroAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $this->jsonSuccess(['formas' => $this->repo->getFormasCobro($idEmpresa)]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – PLANTILLAS WHATSAPP
    // ─────────────────────────────────────────────────────────────────────

    public function getPlantillasWAAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $this->jsonSuccess(['plantillas' => $this->repo->getPlantillasWA($idEmpresa)]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – ENVIAR EMAIL
    // ─────────────────────────────────────────────────────────────────────

    public function enviarEmailAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $idVenta   = (int)($_POST['id_venta'] ?? 0);
        $emailDest = trim($_POST['email'] ?? '');
        $asunto    = trim($_POST['asunto'] ?? '');
        $mensaje   = trim($_POST['mensaje'] ?? '');

        if ($idVenta <= 0 || !filter_var($emailDest, FILTER_VALIDATE_EMAIL)) {
            $this->jsonError('Datos incompletos o email inválido.');
            return;
        }

        $factura = $this->repo->getFacturaParaCobro($idVenta, $idEmpresa);
        if (!$factura) {
            $this->jsonError('Factura no encontrada.');
            return;
        }

        $smtpData = EmailConfigService::getPhpMailerConfig('envio_documentos_sri');
        if (!$smtpData) {
            $this->jsonError('No hay configuración de correo activa (envio_documentos_sri).');
            return;
        }

        $docMailDir = MVC_APP . '/lib/mail';
        require_once $docMailDir . '/phpmailer.php';
        require_once $docMailDir . '/smtp.php';
        require_once $docMailDir . '/exception.php';

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $smtpData['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtpData['username'];
            $mail->Password   = $smtpData['password'];
            $mail->SMTPSecure = $smtpData['smtpSecure'] ?? 'tls';
            $mail->Port       = $smtpData['port'];
            $mail->CharSet    = 'UTF-8';

            $cfg = require MVC_CONFIG . '/app.php';
            if (!empty($cfg['mail_smtp_options'])) {
                $mail->SMTPOptions = $cfg['mail_smtp_options'];
            }

            $mail->setFrom($smtpData['from'], $smtpData['fromName']);
            $mail->addAddress($emailDest, $factura['cliente_nombre']);

            $asuntoFinal = $asunto ?: 'Recordatorio de pago — Factura ' . $factura['numero_factura'];
            $mail->Subject = $asuntoFinal;

            // HTML del correo
            $mail->isHTML(true);
            $mail->Body = $this->renderEmailBody($factura, $mensaje);
            $mail->AltBody = strip_tags($mail->Body);

            $mail->send();

            $this->log->registrar(
                (int)$_SESSION['id_usuario'],
                $idEmpresa,
                'EMAIL_CXC',
                'ventas_cabecera',
                $idVenta,
                null,
                ['email' => $emailDest]
            );

            $this->jsonSuccess(['mensaje' => 'Correo enviado correctamente a ' . $emailDest]);
        } catch (\Throwable $e) {
            error_log('[CxC enviarEmail] ' . $e->getMessage());
            $this->jsonError('Error al enviar correo: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – ENVIAR WHATSAPP
    // ─────────────────────────────────────────────────────────────────────

    public function enviarWhatsappAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $idVenta      = (int)($_POST['id_venta'] ?? 0);
        $telefono     = preg_replace('/[^0-9]/', '', trim($_POST['telefono'] ?? ''));
        $nombrePlant  = trim($_POST['template_name'] ?? '');
        $idioma       = trim($_POST['idioma'] ?? 'es');
        $components   = json_decode($_POST['components'] ?? '[]', true) ?? [];

        if ($idVenta <= 0 || strlen($telefono) < 7 || !$nombrePlant) {
            $this->jsonError('Datos incompletos.');
            return;
        }

        $factura = $this->repo->getFacturaParaCobro($idVenta, $idEmpresa);
        if (!$factura) {
            $this->jsonError('Factura no encontrada.');
            return;
        }

        $waService = new WhatsappService();
        $result    = $waService->sendTemplateMessage($idEmpresa, $telefono, $nombrePlant, $idioma, $components);

        if (!($result['success'] ?? false)) {
            $this->jsonError('Error al enviar WhatsApp: ' . ($result['message'] ?? 'Desconocido'));
            return;
        }

        $this->log->registrar(
            (int)$_SESSION['id_usuario'],
            $idEmpresa,
            'WHATSAPP_CXC',
            'ventas_cabecera',
            $idVenta,
            null,
            ['telefono' => $telefono, 'template' => $nombrePlant]
        );

        $this->jsonSuccess(['mensaje' => 'WhatsApp enviado correctamente.']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX – BÚSQUEDA DE CLIENTES
    // ─────────────────────────────────────────────────────────────────────

    public function getClientesAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $q         = trim($_GET['q'] ?? '');

        if (strlen($q) < 2) {
            $this->jsonSuccess(['clientes' => []]);
            return;
        }

        $sql = "SELECT id, nombre, identificacion
                FROM clientes
                WHERE id_empresa = :id_empresa
                  AND eliminado  = false
                  AND (LOWER(nombre) LIKE :q OR identificacion LIKE :q2)
                ORDER BY nombre LIMIT 15";

        $st = $this->repo->getDb()->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':q' => '%' . strtolower($q) . '%', ':q2' => '%' . $q . '%']);
        $this->jsonSuccess(['clientes' => $st->fetchAll(PDO::FETCH_ASSOC)]);
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
        header('Content-Disposition: attachment; filename="cuentas_por_cobrar_' . date('Ymd_His') . '.xls"');
        header('Cache-Control: max-age=0');
        echo "\xEF\xBB\xBF"; // BOM UTF-8

        $headers = ['Factura', 'Cliente', 'RUC/Cédula', 'F.Emisión', 'F.Vencimiento', 'Días Vencidos', 'Total', 'Cobrado', 'Saldo', 'Estado'];
        echo implode("\t", $headers) . "\n";

        foreach ($filas as $r) {
            $dias = (int)($r['dias_vencido'] ?? 0);
            $estadoCxC = $dias > 0 ? "VENCIDA ({$dias} días)" : ($dias <= 0 ? 'VIGENTE' : '');
            echo implode("\t", [
                $r['numero_factura'] ?? '',
                $r['cliente_nombre'] ?? '',
                $r['cliente_ruc']    ?? '',
                $r['fecha_emision']  ? date('d-m-Y', strtotime($r['fecha_emision'])) : '',
                $r['fecha_vencimiento'] ? date('d-m-Y', strtotime($r['fecha_vencimiento'])) : '',
                $dias > 0 ? $dias : 0,
                number_format((float)$r['total'], 2),
                number_format((float)$r['total_cobrado'], 2),
                number_format((float)$r['saldo'], 2),
                $estadoCxC,
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
            $nombreEmpresa = $empresa['nombre'] ?? 'Cuentas por Cobrar';

            $totalSaldo   = 0;
            $totalCobrado = 0;
            $totalTotal   = 0;
            $filaHtml = '';
            foreach ($filas as $r) {
                $dias = (int)($r['dias_vencido'] ?? 0);
                $ts   = (float)$r['total'];
                $tc   = (float)$r['total_cobrado'];
                $tsal = (float)$r['saldo'];
                $totalTotal   += $ts;
                $totalCobrado += $tc;
                $totalSaldo   += $tsal;
                $color = $dias > 0 ? 'color:#dc3545;' : '';
                $badge = $dias > 0 ? "<small style='color:#dc3545;font-weight:bold;'> ({$dias}d vencida)</small>" : "<small style='color:#198754;'>Vigente</small>";
                $fVenc = !empty($r['fecha_vencimiento']) ? date('d-m-Y', strtotime($r['fecha_vencimiento'])) : '—';
                $fEmis = !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '—';
                $filaHtml .= "<tr style='{$color}'>
                    <td>" . htmlspecialchars($r['numero_factura'] ?? '') . "</td>
                    <td>" . htmlspecialchars($r['cliente_nombre'] ?? '') . "<br><small style='color:#6c757d;'>" . htmlspecialchars($r['cliente_ruc'] ?? '') . "</small></td>
                    <td class='text-center'>{$fEmis}</td>
                    <td class='text-center'>{$fVenc} {$badge}</td>
                    <td class='text-end'>\$" . number_format($ts, 2) . "</td>
                    <td class='text-end' style='color:#198754;'>\$" . number_format($tc, 2) . "</td>
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
                <h3>Cuentas por Cobrar</h3>
                <p>Generado: <?= date('d-m-Y H:i:s') ?></p>
            </div>
            <div class="stats">
                <div class="stat-box">
                    <div>Facturas</div>
                    <div class="stat-val"><?= $stats['total_facturas'] ?></div>
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
                        <th>Factura</th>
                        <th>Cliente / RUC</th>
                        <th>F. Emisión</th>
                        <th>F. Vencimiento</th>
                        <th>Total</th>
                        <th>Cobrado</th>
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
                        <th class="text-end" style="color:#198754;">$<?= number_format($totalCobrado, 2) ?></th>
                        <th class="text-end" style="color:#dc3545;">$<?= number_format($totalSaldo, 2) ?></th>
                    </tr>
                </tfoot>
            </table>
            <?php
            $html     = ob_get_clean();
            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $html2pdf->writeHTML($html);
            $html2pdf->output('CuentasPorCobrar_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // PRIVADOS AUXILIARES
    // ─────────────────────────────────────────────────────────────────────

    private function getFiltros(): array
    {
        return [
            'estado'      => $_REQUEST['estado']      ?? 'PENDIENTES',
            'fecha_desde' => $_REQUEST['fecha_desde'] ?? '',
            'fecha_hasta' => $_REQUEST['fecha_hasta'] ?? '',
            'id_cliente'  => $_REQUEST['id_cliente']  ?? '',
        ];
    }

    private function renderEmailBody(array $factura, string $mensajeExtra): string
    {
        $nombre   = htmlspecialchars($factura['cliente_nombre'] ?? '');
        $nroFact  = htmlspecialchars($factura['numero_factura'] ?? '');
        $total    = '$' . number_format((float)($factura['total'] ?? 0), 2);
        $saldo    = '$' . number_format((float)($factura['saldo'] ?? 0), 2);
        $vence    = !empty($factura['fecha_vencimiento'])
                    ? date('d-m-Y', strtotime($factura['fecha_vencimiento']))
                    : '—';
        $msg      = nl2br(htmlspecialchars($mensajeExtra));

        return <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
body{font-family:Arial,sans-serif;font-size:14px;color:#333;}
.card{background:#f8f9fa;border-left:4px solid #e63946;padding:16px 20px;margin:16px 0;border-radius:4px;}
.label{color:#6c757d;font-size:12px;text-transform:uppercase;margin-bottom:2px;}
.value{font-size:16px;font-weight:bold;}
.saldo{color:#e63946;font-size:22px;}
.footer{color:#aaa;font-size:11px;margin-top:20px;}
</style></head><body>
<p>Estimado/a <strong>{$nombre}</strong>,</p>
<p>Le recordamos que tiene un saldo pendiente de pago correspondiente a:</p>
<div class="card">
  <div><div class="label">Factura</div><div class="value">{$nroFact}</div></div>
  <div style="margin-top:10px;"><div class="label">Total Factura</div><div class="value">{$total}</div></div>
  <div style="margin-top:10px;"><div class="label">Saldo Pendiente</div><div class="saldo">{$saldo}</div></div>
  <div style="margin-top:10px;"><div class="label">Fecha de Vencimiento</div><div class="value">{$vence}</div></div>
</div>
{$msg}
<p>Por favor, regularice su cuenta a la brevedad posible. Si ya realizó el pago, por favor ignorer este mensaje.</p>
<p class="footer">Este es un mensaje automático. Por favor no responda a este correo.</p>
</body></html>
HTML;
    }
}
