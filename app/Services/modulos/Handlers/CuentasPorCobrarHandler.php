<?php
declare(strict_types=1);

namespace App\Services\modulos\Handlers;

use App\repositories\modulos\CuentasPorCobrarRepository;

/**
 * Envío de estados de cuenta a los clientes con saldo pendiente.
 * - Correo: detalle HTML en 4 niveles (total vencido / total general / por factura / por línea).
 * - WhatsApp: total por cobrar (plantilla de texto) o PDF de estado de cuenta (adjunto).
 */
class CuentasPorCobrarHandler extends BaseHandler
{
    public function ejecutar(int $idEmpresa, ?int $idEstablecimiento, int $idUsuario, array $parametros): array
    {
        return match ($this->accion) {
            'enviar_estado_correo'   => $this->enviarCorreo($idEmpresa, $parametros),
            'enviar_estado_whatsapp' => $this->enviarWhatsapp($idEmpresa, $parametros),
            default => throw new \RuntimeException("Acción '{$this->accion}' no implementada en CuentasPorCobrarHandler."),
        };
    }

    // ── Correo ────────────────────────────────────────────────────────────────
    private function enviarCorreo(int $idEmpresa, array $p): array
    {
        $nivel        = trim($p['nivel_detalle'] ?? 'por_factura');
        $soloVencidas = $this->boolParam($p['solo_vencidas'] ?? true);
        $diasMin      = max(0, (int)($p['dias_min_vencido'] ?? 0));
        $asuntoTpl    = trim($p['asunto'] ?? '');
        $cuerpoTpl    = trim($p['cuerpo'] ?? '');

        if ($asuntoTpl === '' || $cuerpoTpl === '') {
            return ['registros' => 0, 'mensaje' => 'Debe configurar el asunto y el mensaje del correo en la automatización.'];
        }

        $clientes = $this->agruparPorCliente($idEmpresa, $soloVencidas, $diasMin);
        if (empty($clientes)) {
            return ['registros' => 0, 'mensaje' => 'No hay clientes con saldo pendiente para los filtros configurados.'];
        }

        $empresaNombre = $this->getEmpresaNombre($idEmpresa);
        $mailService   = new \App\Services\EnvioDocumentosSRIService();

        $enviadas = 0; $sinCorreo = 0; $errores = 0;

        foreach ($clientes as $cli) {
            if ($cli['email'] === '') { $sinCorreo++; continue; }

            $reemplazos = $this->etiquetas($cli, $empresaNombre);
            $asunto     = strtr($asuntoTpl, $reemplazos);
            $cuerpo     = nl2br(htmlspecialchars(strtr($cuerpoTpl, $reemplazos), ENT_QUOTES, 'UTF-8'));
            $detalle    = $this->construirDetalleHtml($cli, $idEmpresa, $nivel);

            $html = "<div style='font-family:Arial,sans-serif;line-height:1.5;color:#333;'>
                        <div>{$cuerpo}</div>{$detalle}
                     </div>";

            try {
                $ok = $mailService->enviarAvisoSimple($idEmpresa, $cli['email'], $cli['nombre'], $asunto, $html, $empresaNombre);
                $ok ? $enviadas++ : $errores++;
            } catch (\Throwable $e) {
                $errores++;
            }
        }

        $msg = "Se enviaron {$enviadas} estado(s) de cuenta por correo.";
        if ($sinCorreo > 0) $msg .= " ({$sinCorreo} sin correo registrado)";
        if ($errores > 0)   $msg .= " ({$errores} con error de envío)";
        return ['registros' => $enviadas, 'mensaje' => $msg];
    }

    // ── WhatsApp ──────────────────────────────────────────────────────────────
    // El mensaje y el adjunto los define la plantilla aprobada por Meta.
    // - Si la plantilla tiene header de tipo DOCUMENT → se adjunta el PDF.
    // - Las variables {{1}}..{{n}} del cuerpo se rellenan automáticamente con el
    //   contexto del cliente, en orden fijo (ver contextoVariables()).
    private function enviarWhatsapp(int $idEmpresa, array $p): array
    {
        $soloVencidas = $this->boolParam($p['solo_vencidas'] ?? true);
        $diasMin      = max(0, (int)($p['dias_min_vencido'] ?? 0));
        $plantilla    = trim($p['plantilla_whatsapp'] ?? '');
        $nivelPdf     = trim($p['nivel_detalle_pdf'] ?? 'por_factura');

        if ($plantilla === '') {
            return ['registros' => 0, 'mensaje' => 'Debe seleccionar la plantilla de WhatsApp en la automatización.'];
        }

        // Resolver la plantilla: idioma, n.º de variables del cuerpo y si lleva documento
        $plModel = new \App\models\WhatsappPlantilla();
        $tpl = null;
        foreach ($plModel->getPlantillasAprobadas($idEmpresa) as $pl) {
            if ((string)$pl['nombre'] === $plantilla) { $tpl = $pl; break; }
        }
        if ($tpl === null) {
            return ['registros' => 0, 'mensaje' => "La plantilla '{$plantilla}' no existe o no está aprobada."];
        }
        $idioma      = (string)($tpl['idioma'] ?? 'es');
        $componentes = json_decode($tpl['componentes'] ?? '[]', true) ?: [];
        [$numVars, $reqDocumento] = $this->analizarPlantilla($componentes);

        $clientes = $this->agruparPorCliente($idEmpresa, $soloVencidas, $diasMin);
        if (empty($clientes)) {
            return ['registros' => 0, 'mensaje' => 'No hay clientes con saldo pendiente para los filtros configurados.'];
        }

        $empresaNombre = $this->getEmpresaNombre($idEmpresa);
        $waService     = new \App\services\WhatsappService();
        $waRepo        = new \App\repositories\modulos\WhatsappMensajeRepository();

        $enviadas = 0; $sinTel = 0; $errores = 0;

        foreach ($clientes as $cli) {
            $telefono = $this->normalizarTelefono($cli['telefono']);
            if ($telefono === '') { $sinTel++; continue; }

            // Valores que rellenan las variables del cuerpo, en orden fijo
            $ctx = $this->contextoVariables($cli, $empresaNombre);

            $components     = [];
            $valoresUsados  = [];
            $rutaTmp        = null;

            // Header documento: solo si la plantilla lo declara
            if ($reqDocumento) {
                try {
                    $rutaTmp = $this->generarPdfTmp($cli, $idEmpresa, $empresaNombre, $nivelPdf);
                    $subida  = $waService->uploadMessageMedia($idEmpresa, $rutaTmp, 'application/pdf');
                    if (!($subida['success'] ?? false)) {
                        $errores++;
                        @unlink($rutaTmp);
                        continue;
                    }
                    $components[] = [
                        'type'       => 'header',
                        'parameters' => [[
                            'type'     => 'document',
                            'document' => ['id' => $subida['media_id'], 'filename' => 'Estado_de_cuenta.pdf'],
                        ]],
                    ];
                } catch (\Throwable $e) {
                    $errores++;
                    if ($rutaTmp) @unlink($rutaTmp);
                    continue;
                }
            }

            // Variables del cuerpo: se rellenan solas con el contexto, en orden
            if ($numVars > 0) {
                $params = [];
                for ($i = 0; $i < $numVars; $i++) {
                    $valor = $ctx[$i] ?? '';
                    $valoresUsados[] = $valor;
                    $params[] = ['type' => 'text', 'text' => $valor];
                }
                $components[] = ['type' => 'body', 'parameters' => $params];
            }

            try {
                $resp = $waService->sendTemplateMessage($idEmpresa, $telefono, $plantilla, $idioma, $components);
                if ($resp['success'] ?? false) {
                    $enviadas++;
                    // Registrar en el Chat Center (no debe interrumpir el envío si falla)
                    try {
                        $metaMsgId = $resp['data']['messages'][0]['id'] ?? null;
                        $idChat = $waRepo->getOrCreateChat($idEmpresa, $telefono, $cli['nombre'], 'CxC: ' . $plantilla, false);
                        $waRepo->saveMessage(
                            $idEmpresa, $idChat, 'OUT', $telefono, $reqDocumento ? 'document' : 'template',
                            [
                                'template'      => $plantilla,
                                'variables'     => $valoresUsados,
                                'template_text' => $reqDocumento ? 'Estado de cuenta (PDF)' : 'Estado de cuenta',
                            ],
                            $metaMsgId, 'sent'
                        );
                    } catch (\Throwable $e) {
                        error_log('[CxC WhatsApp] Enviado pero no registrado en chat: ' . $e->getMessage());
                    }
                } else {
                    $errores++;
                }
            } catch (\Throwable $e) {
                $errores++;
            } finally {
                if ($rutaTmp) @unlink($rutaTmp);
            }
        }

        $msg = "Se enviaron {$enviadas} estado(s) de cuenta por WhatsApp.";
        if ($sinTel > 0)  $msg .= " ({$sinTel} sin teléfono registrado)";
        if ($errores > 0) $msg .= " ({$errores} con error de envío)";
        return ['registros' => $enviadas, 'mensaje' => $msg];
    }

    /**
     * Analiza los componentes de una plantilla: cuántas variables {{n}} tiene
     * el cuerpo y si requiere un documento adjunto (header DOCUMENT).
     * @return array{0:int,1:bool} [numVariables, requiereDocumento]
     */
    private function analizarPlantilla(array $componentes): array
    {
        $numVars = 0; $reqDoc = false;
        foreach ($componentes as $c) {
            $type = strtoupper($c['type'] ?? '');
            if ($type === 'BODY') {
                if (preg_match_all('/{{(\d+)}}/', $c['text'] ?? '', $m)) {
                    $numVars = max($numVars, (int)max($m[1]));
                }
            } elseif ($type === 'HEADER' && strtoupper($c['format'] ?? '') === 'DOCUMENT') {
                $reqDoc = true;
            }
        }
        return [$numVars, $reqDoc];
    }

    /**
     * Valores con los que se rellenan las variables del cuerpo de la plantilla,
     * EN ORDEN: {{1}}=cliente, {{2}}=total vencido, {{3}}=total general,
     * {{4}}=n.º facturas, {{5}}=empresa.
     */
    private function contextoVariables(array $cli, string $empresaNombre): array
    {
        return [
            $cli['nombre'],
            '$' . $this->money($cli['total_vencido']),
            '$' . $this->money($cli['total_general']),
            (string)count($cli['facturas']),
            $empresaNombre,
        ];
    }

    // ── Agrupación ────────────────────────────────────────────────────────────
    private function agruparPorCliente(int $idEmpresa, bool $soloVencidas, int $diasMin): array
    {
        $repo     = new CuentasPorCobrarRepository();
        $facturas = $repo->getFacturasPendientesParaEnvio($idEmpresa, $soloVencidas, $diasMin);

        $clientes = [];
        foreach ($facturas as $f) {
            $idc = (int)$f['id_cliente'];
            if (!isset($clientes[$idc])) {
                $clientes[$idc] = [
                    'id_cliente'    => $idc,
                    'nombre'        => trim((string)($f['cliente_nombre'] ?? 'Cliente')),
                    'email'         => trim((string)($f['cliente_email'] ?? '')),
                    'telefono'      => trim((string)($f['cliente_telefono'] ?? '')),
                    'ruc'           => trim((string)($f['cliente_ruc'] ?? '')),
                    'facturas'      => [],
                    'total_general' => 0.0,
                    'total_vencido' => 0.0,
                ];
            }
            $saldo = (float)$f['saldo'];
            $clientes[$idc]['facturas'][]      = $f;
            $clientes[$idc]['total_general']  += $saldo;
            if ((int)$f['dias_vencido'] > 0) {
                $clientes[$idc]['total_vencido'] += $saldo;
            }
        }
        return $clientes;
    }

    // ── Detalle HTML (correo) ─────────────────────────────────────────────────
    private function construirDetalleHtml(array $cli, int $idEmpresa, string $nivel): string
    {
        if ($nivel === 'total_general' || $nivel === 'total_vencido') {
            $monto = $nivel === 'total_vencido' ? $cli['total_vencido'] : $cli['total_general'];
            $etiq  = $nivel === 'total_vencido' ? 'Total por cobrar vencido' : 'Total por cobrar general';
            return "<p style='margin-top:14px;font-size:15px;'><b>{$etiq}:</b> $" . $this->money($monto) . "</p>";
        }

        $repo = new CuentasPorCobrarRepository();
        $html = '<table style="width:100%;border-collapse:collapse;margin-top:14px;font-size:13px;">';

        if ($nivel === 'por_linea') {
            foreach ($cli['facturas'] as $f) {
                $html .= '<tr><td colspan="4" style="background:#0d6efd;color:#fff;padding:6px 8px;font-weight:bold;">'
                       . 'Factura ' . htmlspecialchars($f['numero_factura'])
                       . ' &nbsp;|&nbsp; Vence: ' . $this->fecha($f['fecha_vencimiento'])
                       . ' &nbsp;|&nbsp; Saldo: $' . $this->money((float)$f['saldo']) . '</td></tr>';
                $html .= '<tr style="background:#f2f2f2;">
                            <th style="text-align:left;padding:4px 8px;border:1px solid #ddd;">Descripción</th>
                            <th style="text-align:right;padding:4px 8px;border:1px solid #ddd;">Cant.</th>
                            <th style="text-align:right;padding:4px 8px;border:1px solid #ddd;">P. Unit.</th>
                            <th style="text-align:right;padding:4px 8px;border:1px solid #ddd;">Total</th>
                          </tr>';
                foreach ($repo->getLineasFactura((int)$f['id']) as $l) {
                    $html .= '<tr>'
                           . '<td style="padding:4px 8px;border:1px solid #ddd;">' . htmlspecialchars((string)$l['descripcion']) . '</td>'
                           . '<td style="padding:4px 8px;border:1px solid #ddd;text-align:right;">' . rtrim(rtrim(number_format((float)$l['cantidad'], 2), '0'), '.') . '</td>'
                           . '<td style="padding:4px 8px;border:1px solid #ddd;text-align:right;">$' . $this->money((float)$l['precio_unitario']) . '</td>'
                           . '<td style="padding:4px 8px;border:1px solid #ddd;text-align:right;">$' . $this->money((float)$l['precio_total_sin_impuesto']) . '</td>'
                           . '</tr>';
                }
            }
        } else {
            // por_factura
            $html .= '<tr style="background:#f2f2f2;">
                        <th style="text-align:left;padding:6px 8px;border:1px solid #ddd;">Factura</th>
                        <th style="text-align:left;padding:6px 8px;border:1px solid #ddd;">Emisión</th>
                        <th style="text-align:left;padding:6px 8px;border:1px solid #ddd;">Vencimiento</th>
                        <th style="text-align:right;padding:6px 8px;border:1px solid #ddd;">Días venc.</th>
                        <th style="text-align:right;padding:6px 8px;border:1px solid #ddd;">Saldo</th>
                      </tr>';
            foreach ($cli['facturas'] as $f) {
                $dias = (int)$f['dias_vencido'];
                $colorDias = $dias > 0 ? 'color:#dc3545;font-weight:bold;' : 'color:#198754;';
                $html .= '<tr>'
                       . '<td style="padding:6px 8px;border:1px solid #ddd;">' . htmlspecialchars($f['numero_factura']) . '</td>'
                       . '<td style="padding:6px 8px;border:1px solid #ddd;">' . $this->fecha($f['fecha_emision']) . '</td>'
                       . '<td style="padding:6px 8px;border:1px solid #ddd;">' . $this->fecha($f['fecha_vencimiento']) . '</td>'
                       . '<td style="padding:6px 8px;border:1px solid #ddd;text-align:right;' . $colorDias . '">' . ($dias > 0 ? $dias : 'Al día') . '</td>'
                       . '<td style="padding:6px 8px;border:1px solid #ddd;text-align:right;">$' . $this->money((float)$f['saldo']) . '</td>'
                       . '</tr>';
            }
        }

        $html .= '<tr><td colspan="' . ($nivel === 'por_linea' ? 3 : 4) . '" style="text-align:right;padding:8px;font-weight:bold;border-top:2px solid #333;">TOTAL PENDIENTE:</td>'
               . '<td style="text-align:right;padding:8px;font-weight:bold;border-top:2px solid #333;">$' . $this->money($cli['total_general']) . '</td></tr>';
        $html .= '</table>';
        return $html;
    }

    // ── PDF temporal (WhatsApp adjunto) ───────────────────────────────────────
    private function generarPdfTmp(array $cli, int $idEmpresa, string $empresaNombre, string $nivel): string
    {
        $autoload = MVC_ROOT . '/vendor/autoload.php';
        if (file_exists($autoload)) require_once $autoload;

        $detalle = $this->construirDetalleHtml($cli, $idEmpresa, $nivel === 'por_linea' ? 'por_linea' : 'por_factura');

        $content = '<page backtop="10mm" backbottom="10mm" backleft="10mm" backright="10mm">'
                 . '<div style="text-align:center;font-family:Arial;margin-bottom:10px;">'
                 . '<h1 style="font-size:14pt;margin:0;">' . htmlspecialchars($empresaNombre) . '</h1>'
                 . '<h2 style="font-size:11pt;color:#666;margin:2px 0;">ESTADO DE CUENTA</h2>'
                 . '</div>'
                 . '<div style="font-family:Arial;font-size:10pt;margin-bottom:8px;">'
                 . '<b>Cliente:</b> ' . htmlspecialchars($cli['nombre']) . '<br>'
                 . '<b>Identificación:</b> ' . htmlspecialchars($cli['ruc']) . '<br>'
                 . '<b>Fecha:</b> ' . date('d-m-Y')
                 . '</div>'
                 . $detalle
                 . '</page>';

        $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
        $html2pdf->writeHTML($content);
        $pdfString = $html2pdf->output('estado.pdf', 'S');

        $ruta = tempnam(sys_get_temp_dir(), 'cxc_') . '.pdf';
        file_put_contents($ruta, $pdfString);
        return $ruta;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    private function etiquetas(array $cli, string $empresaNombre): array
    {
        return [
            '{cliente}'       => $cli['nombre'],
            '{empresa}'       => $empresaNombre,
            '{total_general}' => '$' . $this->money($cli['total_general']),
            '{total_vencido}' => '$' . $this->money($cli['total_vencido']),
            '{num_facturas}'  => (string)count($cli['facturas']),
        ];
    }

    private function getEmpresaNombre(int $idEmpresa): string
    {
        $empresa = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
        return trim((string)($empresa['nombre_comercial'] ?? $empresa['nombre'] ?? ''));
    }

    private function money(float $v): string
    {
        return number_format($v, 2, '.', ',');
    }

    private function fecha($fecha): string
    {
        $f = (string)$fecha;
        if ($f === '') return '';
        try {
            return (new \DateTime($f))->format('d-m-Y');
        } catch (\Throwable) {
            return $f;
        }
    }

    private function boolParam($v): bool
    {
        if (is_bool($v)) return $v;
        $s = strtolower(trim((string)$v));
        return in_array($s, ['1', 'true', 'on', 'si', 'sí'], true);
    }

    private function normalizarTelefono(string $telefono): string
    {
        $tel = preg_replace('/\D/', '', $telefono);
        if ($tel === '' || strlen($tel) < 9) return '';
        if (str_starts_with($tel, '593')) return $tel;
        if (str_starts_with($tel, '0'))   return '593' . substr($tel, 1);
        return '593' . $tel;
    }
}
