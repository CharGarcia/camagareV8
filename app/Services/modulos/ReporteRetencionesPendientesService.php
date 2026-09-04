<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\ReporteRetencionesPendientesRepository;
use App\Rules\modulos\ReporteRetencionesPendientesRules;
use App\Services\EnvioDocumentosSRIService;
use App\Services\LogSistemaService;

/**
 * Envío de avisos por correo de facturas de venta sin comprobante de retención.
 *
 *  - Individual: un correo por factura, con destinatario/asunto/mensaje editables.
 *  - Agrupado:   un correo por CLIENTE con la tabla de todas sus facturas
 *                pendientes seleccionadas (un cliente nunca ve facturas ajenas).
 *
 * Cada envío exitoso deja una fila en retencion_venta_avisos (por factura) y un
 * registro en log_sistema. El correo sale por el mismo servicio SMTP del resto
 * del sistema (EnvioDocumentosSRIService::enviarAvisoSimple, config por empresa).
 */
class ReporteRetencionesPendientesService
{
    private ReporteRetencionesPendientesRepository $repo;
    private ReporteRetencionesPendientesRules $rules;
    private LogSistemaService $log;

    public function __construct(?ReporteRetencionesPendientesRepository $repo = null)
    {
        $this->repo  = $repo ?? new ReporteRetencionesPendientesRepository();
        $this->rules = new ReporteRetencionesPendientesRules();
        $this->log   = new LogSistemaService();
    }

    // ── Envío individual ─────────────────────────────────────────────────────

    /**
     * @return array{mensaje:string, correo:string}
     */
    public function enviarAvisoIndividual(
        int $idEmpresa,
        int $idUsuario,
        int $idVenta,
        string $correo,
        string $asunto,
        string $mensajeExtra,
        ?int $idUsuarioFiltro = null
    ): array {
        $this->rules->validarEnvioIndividual($idVenta, $correo);
        $direcciones = $this->rules->direccionesValidas($correo);

        $factura = $this->rules->validarPendiente($this->repo->getPendientePorId($idVenta, $idEmpresa, $idUsuarioFiltro));
        $empresa = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
        $nombreEmpresa = (string) ($empresa['nombre_comercial'] ?? $empresa['nombre'] ?? '');

        $asuntoFinal = $asunto !== '' ? $asunto
            : 'Comprobante de retención pendiente — Factura ' . ($factura['numero_factura'] ?? '');
        $html = $this->renderCuerpoIndividual($factura, $mensajeExtra, $nombreEmpresa);

        $ok = (new EnvioDocumentosSRIService())->enviarAvisoSimple(
            $idEmpresa, implode(',', $direcciones), (string) ($factura['cliente_nombre'] ?? ''), $asuntoFinal, $html, $nombreEmpresa
        );
        if (!$ok) {
            $detalle = $GLOBALS['LAST_EMAIL_ERROR'] ?? null;
            throw new \Exception('No se pudo enviar el correo. Verifique la configuración de correo de la empresa.'
                . ($detalle ? ' Detalle: ' . $detalle : ''));
        }

        $this->registrarEnvio($idEmpresa, $idUsuario, [$factura], $direcciones, $asuntoFinal, 'INDIVIDUAL', null);

        return [
            'mensaje' => 'Aviso enviado correctamente a ' . implode(', ', $direcciones),
            'correo'  => implode(', ', $direcciones),
        ];
    }

    // ── Envío agrupado (un correo por cliente) ───────────────────────────────

    /**
     * @param int[]  $idsVentas   Facturas seleccionadas.
     * @param array  $correosEdit {id_cliente => "correo1, correo2"} revisados en el modal.
     *                            Si la clave existe se usa tal cual (vacío = omitir al cliente);
     *                            si no, se usa el correo de la ficha del cliente.
     * @return array{mensaje:string, enviados:int, sin_email:int, con_error:int, no_disponibles:int}
     */
    public function enviarAvisosAgrupados(
        int $idEmpresa,
        int $idUsuario,
        array $idsVentas,
        array $correosEdit,
        string $mensajeExtra = '',
        ?int $idUsuarioFiltro = null
    ): array {
        $idsVentas = array_values(array_unique(array_filter(array_map('intval', $idsVentas), fn ($i) => $i > 0)));
        $this->rules->validarLote($idsVentas);

        @set_time_limit(300);

        // 1) Recargar cada factura desde BD (valida empresa/estado y que siga sin retención)
        $porCliente    = [];
        $noDisponibles = 0;
        foreach ($idsVentas as $idVenta) {
            $fac = $this->repo->getPendientePorId($idVenta, $idEmpresa, $idUsuarioFiltro);
            if (!$fac) { $noDisponibles++; continue; }
            $idCli = (int) ($fac['id_cliente'] ?? 0);
            if (!isset($porCliente[$idCli])) {
                $porCliente[$idCli] = [
                    'nombre'   => (string) ($fac['cliente_nombre'] ?? ''),
                    'email'    => trim((string) ($fac['cliente_email'] ?? '')),
                    'facturas' => [],
                ];
            }
            $porCliente[$idCli]['facturas'][] = $fac;
        }
        if (empty($porCliente)) {
            throw new \Exception('Ninguna de las facturas seleccionadas sigue pendiente de retención.');
        }

        $empresa = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
        $nombreEmpresa = (string) ($empresa['nombre_comercial'] ?? $empresa['nombre'] ?? '');
        $emailSvc = new EnvioDocumentosSRIService();

        // 2) Un correo por cliente
        $enviados = $sinEmail = $conError = 0;
        foreach ($porCliente as $idCli => $cli) {
            $emailStr = array_key_exists($idCli, $correosEdit)
                ? trim((string) $correosEdit[$idCli])
                : $cli['email'];
            $direcciones = $this->rules->direccionesValidas($emailStr);
            if (empty($direcciones)) { $sinEmail++; continue; }

            usort($cli['facturas'], fn ($a, $b) => strcmp((string) $a['fecha_emision'], (string) $b['fecha_emision']));
            $n = count($cli['facturas']);
            $asunto = $n === 1
                ? 'Comprobante de retención pendiente — Factura ' . ($cli['facturas'][0]['numero_factura'] ?? '')
                : "Comprobantes de retención pendientes — {$n} facturas";
            $html = $this->renderCuerpoAgrupado($cli['nombre'], $cli['facturas'], $mensajeExtra, $nombreEmpresa);

            $ok = $emailSvc->enviarAvisoSimple($idEmpresa, implode(',', $direcciones), $cli['nombre'], $asunto, $html, $nombreEmpresa);
            if (!$ok) { $conError++; continue; }

            $enviados++;
            $lote = 'L' . date('YmdHis') . '-' . $idCli . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
            $this->registrarEnvio($idEmpresa, $idUsuario, $cli['facturas'], $direcciones, $asunto, 'AGRUPADO', $lote);
        }

        $partes = ["{$enviados} correo(s) enviado(s)."];
        if ($sinEmail)      $partes[] = "{$sinEmail} cliente(s) sin correo registrado.";
        if ($conError)      $partes[] = "{$conError} correo(s) con error de envío.";
        if ($noDisponibles) $partes[] = "{$noDisponibles} factura(s) ya no disponible(s) (ya tienen retención o cambiaron de estado).";

        return [
            'mensaje'        => implode(' ', $partes),
            'enviados'       => $enviados,
            'sin_email'      => $sinEmail,
            'con_error'      => $conError,
            'no_disponibles' => $noDisponibles,
        ];
    }

    /** Historial de avisos de una factura. */
    public function getAvisosPorVenta(int $idVenta, int $idEmpresa): array
    {
        return $this->repo->getAvisosPorVenta($idVenta, $idEmpresa);
    }

    // ── Persistencia + auditoría ─────────────────────────────────────────────

    /**
     * Graba una fila por factura en retencion_venta_avisos (transacción) y el
     * registro en log_sistema. El correo ya salió: si esto falla se propaga la
     * excepción para que el usuario sepa que el envío no quedó registrado.
     */
    private function registrarEnvio(int $idEmpresa, int $idUsuario, array $facturas, array $direcciones, string $asunto, string $tipo, ?string $lote): void
    {
        $correo = implode(', ', $direcciones);
        $this->repo->beginTransaction();
        try {
            foreach ($facturas as $fac) {
                $this->repo->registrarAviso([
                    'id_empresa'     => $idEmpresa,
                    'id_venta'       => (int) $fac['id'],
                    'id_cliente'     => (int) ($fac['id_cliente'] ?? 0),
                    'tipo_envio'     => $tipo,
                    'id_lote'        => $lote,
                    'correo_destino' => $correo,
                    'asunto'         => $asunto,
                    'created_by'     => $idUsuario,
                ]);
            }
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw new \Exception('El correo se envió pero no se pudo registrar el aviso: ' . $e->getMessage());
        }

        foreach ($facturas as $fac) {
            $this->log->registrar(
                $idUsuario,
                $idEmpresa,
                $tipo === 'AGRUPADO' ? 'EMAIL_RETENCION_PENDIENTE_AGRUPADO' : 'EMAIL_RETENCION_PENDIENTE',
                'ventas_cabecera',
                (int) $fac['id'],
                null,
                [
                    'factura' => $fac['numero_factura'] ?? '',
                    'cliente' => $fac['cliente_nombre'] ?? '',
                    'email'   => $correo,
                    'asunto'  => $asunto,
                    'lote'    => $lote,
                ]
            );
        }
    }

    // ── Cuerpos HTML de los correos ──────────────────────────────────────────

    private function fmtFecha(?string $f): string
    {
        return !empty($f) ? date('d-m-Y', strtotime($f)) : '—';
    }

    private function renderCuerpoIndividual(array $fac, string $mensajeExtra, string $nombreEmpresa): string
    {
        $nombre   = htmlspecialchars((string) ($fac['cliente_nombre'] ?? ''));
        $empresa  = htmlspecialchars($nombreEmpresa);
        $numero   = htmlspecialchars((string) ($fac['numero_factura'] ?? ''));
        $fecha    = $this->fmtFecha($fac['fecha_emision'] ?? null);
        $subtotal = '$' . number_format((float) ($fac['total_sin_impuestos'] ?? 0), 2);
        $imp      = '$' . number_format((float) ($fac['impuestos'] ?? 0), 2);
        $total    = '$' . number_format((float) ($fac['importe_total'] ?? 0), 2);
        // En el esquema offline del SRI la clave de acceso es el número de autorización.
        $aut      = htmlspecialchars((string) ($fac['clave_acceso'] ?? ''));
        $msg      = trim($mensajeExtra) !== '' ? '<p>' . nl2br(htmlspecialchars($mensajeExtra)) . '</p>' : '';

        return <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
body{font-family:Arial,sans-serif;font-size:14px;color:#333;}
.card{background:#f8f9fa;border-left:4px solid #0d6efd;padding:16px 20px;margin:16px 0;border-radius:4px;}
.label{color:#6c757d;font-size:12px;text-transform:uppercase;margin-bottom:2px;}
.value{font-size:16px;font-weight:bold;}
.total{color:#0d6efd;font-size:22px;}
.footer{color:#aaa;font-size:11px;margin-top:20px;}
</style></head><body>
<p>Estimado/a <strong>{$nombre}</strong>,</p>
<p>De acuerdo con nuestros registros, la siguiente factura emitida por <strong>{$empresa}</strong> a su nombre
<strong>aún no registra el comprobante de retención</strong> correspondiente:</p>
<div class="card">
  <div><div class="label">Factura</div><div class="value">{$numero}</div></div>
  <div style="margin-top:10px;"><div class="label">Fecha de emisión</div><div class="value">{$fecha}</div></div>
  <div style="margin-top:10px;"><div class="label">Subtotal (base imponible)</div><div class="value">{$subtotal}</div></div>
  <div style="margin-top:10px;"><div class="label">Impuestos</div><div class="value">{$imp}</div></div>
  <div style="margin-top:10px;"><div class="label">Total</div><div class="total">{$total}</div></div>
  <div style="margin-top:10px;"><div class="label">N° de autorización</div><div style="font-size:12px;word-break:break-all;">{$aut}</div></div>
</div>
{$msg}
<p>Si usted actúa como agente de retención, le agradeceremos remitirnos el <strong>comprobante de retención electrónico</strong>
a la brevedad posible. Si esta factura no está sujeta a retención o ya nos envió el comprobante, por favor ignore este mensaje.</p>
<p class="footer">Este es un mensaje automático enviado por {$empresa}. Por favor no responda a este correo.</p>
</body></html>
HTML;
    }

    private function renderCuerpoAgrupado(string $nombreCliente, array $facturas, string $mensajeExtra, string $nombreEmpresa): string
    {
        $nombre  = htmlspecialchars($nombreCliente);
        $empresa = htmlspecialchars($nombreEmpresa);
        $n       = count($facturas);
        $intro   = $n === 1 ? 'la siguiente factura' : "las siguientes <strong>{$n} facturas</strong>";
        $msg     = trim($mensajeExtra) !== '' ? '<p>' . nl2br(htmlspecialchars($mensajeExtra)) . '</p>' : '';

        $td = 'padding:6px 10px;border-bottom:1px solid #e9ecef;font-size:13px;';
        $th = 'padding:8px 10px;font-size:12px;';
        $filas = '';
        $sumSub = $sumImp = $sumTot = 0.0;
        foreach ($facturas as $f) {
            $sumSub += (float) ($f['total_sin_impuestos'] ?? 0);
            $sumImp += (float) ($f['impuestos'] ?? 0);
            $sumTot += (float) ($f['importe_total'] ?? 0);
            $filas .= '<tr>'
                . '<td style="' . $td . '">' . htmlspecialchars((string) ($f['numero_factura'] ?? '')) . '</td>'
                . '<td style="' . $td . 'text-align:center;">' . $this->fmtFecha($f['fecha_emision'] ?? null) . '</td>'
                . '<td style="' . $td . 'text-align:right;">$' . number_format((float) ($f['total_sin_impuestos'] ?? 0), 2) . '</td>'
                . '<td style="' . $td . 'text-align:right;">$' . number_format((float) ($f['impuestos'] ?? 0), 2) . '</td>'
                . '<td style="' . $td . 'text-align:right;font-weight:bold;">$' . number_format((float) ($f['importe_total'] ?? 0), 2) . '</td>'
                . '</tr>';
        }
        $fSub = number_format($sumSub, 2);
        $fImp = number_format($sumImp, 2);
        $fTot = number_format($sumTot, 2);
        $tf = 'padding:10px;text-align:right;font-weight:bold;border-top:2px solid #343a40;white-space:nowrap;';

        return <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;font-size:14px;color:#333;">
<p>Estimado/a <strong>{$nombre}</strong>,</p>
<p>De acuerdo con nuestros registros, {$intro} emitidas por <strong>{$empresa}</strong> a su nombre
<strong>aún no registran el comprobante de retención</strong> correspondiente:</p>
<table style="border-collapse:collapse;width:100%;max-width:680px;background:#f8f9fa;" cellpadding="0" cellspacing="0">
  <thead>
    <tr style="background:#343a40;color:#ffffff;">
      <th style="{$th}text-align:left;">Factura</th>
      <th style="{$th}text-align:center;">Emisión</th>
      <th style="{$th}text-align:right;">Subtotal</th>
      <th style="{$th}text-align:right;">Impuestos</th>
      <th style="{$th}text-align:right;">Total</th>
    </tr>
  </thead>
  <tbody>{$filas}</tbody>
  <tfoot>
    <tr>
      <td colspan="2" style="{$tf}">TOTALES:</td>
      <td style="{$tf}">\${$fSub}</td>
      <td style="{$tf}">\${$fImp}</td>
      <td style="{$tf}color:#0d6efd;font-size:16px;">\${$fTot}</td>
    </tr>
  </tfoot>
</table>
{$msg}
<p>Si usted actúa como agente de retención, le agradeceremos remitirnos los <strong>comprobantes de retención electrónicos</strong>
a la brevedad posible. Si alguna de estas facturas no está sujeta a retención o ya nos envió el comprobante, por favor ignore este mensaje.</p>
<p style="color:#aaa;font-size:11px;margin-top:20px;">Este es un mensaje automático enviado por {$empresa}. Por favor no responda a este correo.</p>
</body></html>
HTML;
    }
}
