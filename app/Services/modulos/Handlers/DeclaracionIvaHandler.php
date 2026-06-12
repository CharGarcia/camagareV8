<?php
declare(strict_types=1);

namespace App\Services\modulos\Handlers;

use App\repositories\modulos\DeclaracionIvaRepository;

/**
 * Aviso del IVA a pagar por correo.
 *
 * Calcula el IVA a pagar del período (mes en curso por defecto) reutilizando
 * DeclaracionIvaService::getResumenPago() y envía un correo con el desglose:
 * IVA en ventas, crédito tributario, retenciones y total a pagar.
 *
 * El "cuándo" lo decide la automatización (cron); este handler solo calcula y envía.
 */
class DeclaracionIvaHandler extends BaseHandler
{
    public function ejecutar(int $idEmpresa, ?int $idEstablecimiento, int $idUsuario, array $parametros): array
    {
        return match ($this->accion) {
            'avisar_iva_a_pagar' => $this->avisarIvaAPagar($idEmpresa, $idUsuario, $parametros),
            default => throw new \RuntimeException("Acción '{$this->accion}' no implementada en DeclaracionIvaHandler."),
        };
    }

    private function avisarIvaAPagar(int $idEmpresa, int $idUsuario, array $p): array
    {
        $periodoTipo = trim((string)($p['periodo'] ?? 'mes_en_curso'));
        $asuntoTpl   = trim((string)($p['asunto'] ?? ''));
        $cuerpoTpl   = trim((string)($p['cuerpo'] ?? ''));
        $destinosCfg = trim((string)($p['destinatarios'] ?? ''));

        if ($asuntoTpl === '' || $cuerpoTpl === '') {
            return ['registros' => 0, 'mensaje' => 'Debe configurar el asunto y el mensaje del correo en la automatización.'];
        }

        // Período a calcular según la zona horaria de Ecuador.
        $ahora = new \DateTime('now', new \DateTimeZone('America/Guayaquil'));
        if ($periodoTipo === 'mes_anterior') {
            $ahora->modify('first day of last month');
        }
        $anio = $ahora->format('Y');
        $mes  = $ahora->format('m');

        $service = new \App\services\modulos\DeclaracionIvaService(new DeclaracionIvaRepository());
        $resumen = $service->getResumenPago($idEmpresa, $anio, $mes, true, $idUsuario);

        // Destinatarios: los configurados en la automatización o el correo de la empresa.
        $empresaArr  = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
        $destinatario = $destinosCfg !== '' ? $destinosCfg : trim((string)($empresaArr['mail'] ?? ''));
        if ($destinatario === '') {
            return ['registros' => 0, 'mensaje' => 'No hay correo de destino: configure los destinatarios en la automatización o el correo de la empresa.'];
        }

        $reemplazos = [
            '{empresa}'            => $resumen['empresa'],
            '{periodo}'           => $resumen['periodo'],
            '{iva_ventas}'        => '$' . $this->money($resumen['iva_ventas']),
            '{credito_tributario}'=> '$' . $this->money($resumen['credito_tributario']),
            '{retenciones}'       => '$' . $this->money($resumen['retenciones']),
            '{iva_a_pagar}'       => '$' . $this->money($resumen['a_pagar']),
            '{saldo_favor}'       => '$' . $this->money($resumen['saldo_favor']),
            '{fecha_limite}'      => $resumen['fecha_limite'],
        ];

        $asunto = strtr($asuntoTpl, $reemplazos);
        $cuerpo = nl2br(htmlspecialchars(strtr($cuerpoTpl, $reemplazos), ENT_QUOTES, 'UTF-8'));
        $tabla  = $this->construirDesgloseHtml($resumen);
        $avisos = $this->construirAvisosHtml($resumen);

        $html = "<div style='font-family:Arial,sans-serif;line-height:1.5;color:#333;'>
                    <div>{$cuerpo}</div>{$tabla}{$avisos}
                 </div>";

        $mailService = new \App\Services\EnvioDocumentosSRIService();
        try {
            $ok = $mailService->enviarAvisoSimple(
                $idEmpresa,
                $destinatario,
                $resumen['empresa'],
                $asunto,
                $html,
                $resumen['empresa']
            );
        } catch (\Throwable $e) {
            return ['registros' => 0, 'mensaje' => 'Error al enviar el correo: ' . $e->getMessage()];
        }

        if (!$ok) {
            return ['registros' => 0, 'mensaje' => 'No se pudo enviar el correo (revise la configuración de correo de la empresa).'];
        }

        return [
            'registros' => 1,
            'mensaje'   => "Aviso de IVA enviado: {$resumen['periodo']}, a pagar \${$this->money($resumen['a_pagar'])} (límite {$resumen['fecha_limite']}).",
        ];
    }

    private function construirDesgloseHtml(array $r): string
    {
        $fila = fn(string $label, float $valor, string $extra = '') =>
            "<tr{$extra}>
                <td style='padding:6px 10px;border:1px solid #dee2e6;'>{$label}</td>
                <td style='padding:6px 10px;border:1px solid #dee2e6;text-align:right;'>\$" . $this->money($valor) . "</td>
             </tr>";

        $resaltado = " style='background:#f1f3f5;font-weight:bold;'";

        $rows  = $fila('IVA en ventas (cobrado)', $r['iva_ventas']);
        $rows .= $fila('(−) Crédito tributario (IVA en compras)', $r['credito_tributario']);
        $rows .= $fila('(−) Retenciones de IVA que le hicieron', $r['retenciones']);
        $rows .= $fila('IVA a pagar', $r['a_pagar'], $resaltado);
        if ($r['saldo_favor'] > 0) {
            $rows .= $fila('Saldo a favor (crédito próximo mes)', $r['saldo_favor']);
        }

        return "<table style='border-collapse:collapse;margin-top:14px;min-width:340px;font-size:14px;'>
                    <thead>
                        <tr style='background:#0d6efd;color:#fff;'>
                            <th colspan='2' style='padding:8px 10px;text-align:left;'>Resumen IVA — {$r['periodo']}</th>
                        </tr>
                    </thead>
                    <tbody>{$rows}</tbody>
                    <tfoot>
                        <tr>
                            <td colspan='2' style='padding:6px 10px;border:1px solid #dee2e6;color:#6c757d;'>
                                Fecha límite de pago: <strong>{$r['fecha_limite']}</strong>
                            </td>
                        </tr>
                    </tfoot>
                </table>";
    }

    /**
     * Bloques informativos que se agregan siempre:
     *  - Aviso de que los valores son referenciales.
     *  - Si no hubo facturas de venta emitidas: advertencia de multa por no presentación
     *    y sugerencia de automatizar una factura de valor mínimo.
     */
    private function construirAvisosHtml(array $r): string
    {
        $referencial = "<div style='margin-top:14px;padding:10px 12px;border-left:4px solid #0d6efd;background:#e7f1ff;font-size:13px;color:#333;'>
                            <strong>ℹ️ Importante:</strong> Los valores de este resumen son <strong>referenciales</strong> y se calculan a partir de los documentos registrados en el sistema. Para determinar con exactitud el valor a pagar, realice una revisión contable más completa antes de presentar su declaración.
                        </div>";

        $sinVentas = '';
        if ((int)($r['num_facturas_venta'] ?? 0) === 0) {
            $periodo = htmlspecialchars((string)$r['periodo'], ENT_QUOTES, 'UTF-8');
            $limite  = htmlspecialchars((string)$r['fecha_limite'], ENT_QUOTES, 'UTF-8');
            $sinVentas = "<div style='margin-top:12px;padding:10px 12px;border-left:4px solid #dc3545;background:#fde8ea;font-size:13px;color:#333;'>
                            <strong>⚠️ No se registran facturas de venta emitidas en {$periodo}.</strong>
                            Aun así, debe presentar la declaración de IVA el próximo mes, <strong>hasta el {$limite}</strong>.
                            Olvidar presentarla puede ocasionar una <strong>multa por no presentación</strong>, incluso si no hubo ventas.
                          </div>
                          <div style='margin-top:8px;padding:10px 12px;border-left:4px solid #ffc107;background:#fff8e1;font-size:13px;color:#333;'>
                            <strong>💡 Sugerencia:</strong> para evitar este riesgo, puede programar el sistema en el módulo de <strong>Automatizaciones</strong> para emitir automáticamente una factura con un valor mínimo cada mes, de modo que siempre exista movimiento y declaración.
                          </div>";
        }

        return $referencial . $sinVentas;
    }

    private function money(float $v): string
    {
        return number_format($v, 2, '.', ',');
    }
}
