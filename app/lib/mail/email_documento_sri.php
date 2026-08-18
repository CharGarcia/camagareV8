<?php
/**
 * Plantilla HTML del correo de comprobantes electrónicos autorizados por el SRI.
 *
 * La usa App\Services\EnvioDocumentosSRIService (enviarSiAplica y enviarAvisoAnulacion).
 * Se renderiza con ob_start() + require, igual que el resto de plantillas de lib/mail.
 *
 * $data:
 *   nombre_destino     string  Nombre del cliente/proveedor
 *   nombre_documento   string  'Factura electrónica', 'Nota de Crédito electrónica', ...
 *   num_comprobante    string  001-111-000119087
 *   fecha_emision      string  d-m-Y (vacío si no aplica)
 *   num_autorizacion   string  Clave de acceso / número de autorización
 *   valor_total        string  Ya formateado, p. ej. '21.75 $' (vacío = no mostrar la fila)
 *   cuerpo_personalizado string HTML del cuerpo configurado por la empresa (opcional)
 *   empresa_nombre     string
 *   empresa_ruc        string
 *   logo_cid           string  CID de la imagen embebida (vacío = sin logo)
 *   anulado            bool    Variante de aviso de anulación
 */
$nombreDestino    = (string) ($data['nombre_destino'] ?? '');
$nombreDocumento  = (string) ($data['nombre_documento'] ?? 'Comprobante electrónico');
$numComprobante   = (string) ($data['num_comprobante'] ?? '');
$fechaEmision     = (string) ($data['fecha_emision'] ?? '');
$numAutorizacion  = (string) ($data['num_autorizacion'] ?? '');
$valorTotal       = (string) ($data['valor_total'] ?? '');
$cuerpoPers       = trim((string) ($data['cuerpo_personalizado'] ?? ''));
$empresaNombre    = (string) ($data['empresa_nombre'] ?? '');
$empresaRuc       = (string) ($data['empresa_ruc'] ?? '');
$logoCid          = (string) ($data['logo_cid'] ?? '');
$anulado          = !empty($data['anulado']);

$AZUL   = '#21496b'; // cabecera
$TEXTO  = '#333333';
$SUAVE  = '#7b8794';
$CAJA   = '#f1f5f9';
$PIE    = '#eceff3';
$ROJO   = '#b00020';

$esc = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= $esc($nombreDocumento) ?></title>
    <style>
        /* El cuerpo personalizado se escribe con el editor Quill (modulo Empresa >
           Configuracion Correo), que marca la alineacion con clases en vez de estilos
           inline. Sin estas reglas el texto centrado/derecha se veria alineado a la
           izquierda en el correo. */
        .cmg-cuerpo-personalizado p { margin: 0 0 10px; }
        .cmg-cuerpo-personalizado p:last-child { margin-bottom: 0; }
        .cmg-cuerpo-personalizado .ql-align-center { text-align: center; }
        .cmg-cuerpo-personalizado .ql-align-right  { text-align: right; }
        .cmg-cuerpo-personalizado .ql-align-justify { text-align: justify; }
    </style>
</head>
<body style="margin:0;padding:0;background:#ffffff;">
<table align="center" cellpadding="0" cellspacing="0" border="0" width="100%"
       style="max-width:600px;margin:0 auto;border-collapse:collapse;font-family:Arial,Helvetica,sans-serif;">

    <!-- Cabecera con el logo de la empresa -->
    <tr>
        <td align="center" style="background:<?= $AZUL ?>;padding:22px 24px;">
            <?php if ($logoCid !== ''): ?>
                <img src="cid:<?= $esc($logoCid) ?>" alt="<?= $esc($empresaNombre) ?>"
                     style="display:block;border:0;max-width:220px;max-height:90px;height:auto;">
            <?php else: ?>
                <span style="display:block;color:#ffffff;font-size:19px;font-weight:bold;letter-spacing:.5px;">
                    <?= $esc($empresaNombre) ?>
                </span>
            <?php endif; ?>
        </td>
    </tr>

    <!-- Cuerpo -->
    <tr>
        <td style="background:#ffffff;padding:26px 28px 8px;">
            <p style="margin:0 0 18px;font-size:14px;color:<?= $TEXTO ?>;">
                Estimado(a) <strong><?= $esc($nombreDestino) ?></strong>:
            </p>

            <?php if ($cuerpoPers !== ''): ?>
                <?php /* La empresa configuro su propio texto (modulo Empresa > Configuracion Correo):
                         reemplaza el mensaje por defecto. El numero de documento no se pierde porque
                         en ese caso se muestra dentro de la caja de datos. */ ?>
                <div class="cmg-cuerpo-personalizado" style="margin:0 0 18px;font-size:14px;color:<?= $TEXTO ?>;line-height:1.6;">
                    <?= $cuerpoPers ?>
                </div>
            <?php elseif ($anulado): ?>
                <p style="margin:0 0 18px;font-size:14px;color:<?= $TEXTO ?>;line-height:1.6;">
                    Le informamos que su <strong><?= $esc($nombreDocumento) ?></strong>
                    <?php if ($numComprobante !== ''): ?>
                        <strong>N.&ordm; <?= $esc($numComprobante) ?></strong>
                    <?php endif; ?>
                    ha sido <strong style="color:<?= $ROJO ?>;">ANULADA</strong> y ya no tiene validez.
                </p>
            <?php else: ?>
                <p style="margin:0 0 18px;font-size:14px;color:<?= $TEXTO ?>;line-height:1.6;">
                    Su <strong><?= $esc($nombreDocumento) ?></strong>
                    <?php if ($numComprobante !== ''): ?>
                        <strong>N.&ordm; <?= $esc($numComprobante) ?></strong>
                    <?php endif; ?>
                    ha sido generada correctamente. Adjuntamos el documento correspondiente.
                </p>
            <?php endif; ?>

            <!-- Datos del comprobante -->
            <table cellpadding="0" cellspacing="0" border="0" width="100%"
                   style="border-collapse:collapse;background:<?= $CAJA ?>;margin:0 0 20px;">
                <tr>
                    <td style="padding:16px 18px;font-size:13px;color:<?= $TEXTO ?>;line-height:1.7;">
                        <?php if ($cuerpoPers !== '' && $numComprobante !== ''): ?>
                            <div><strong style="color:<?= $AZUL ?>;">N.&ordm; de documento:</strong> <?= $esc($numComprobante) ?></div>
                        <?php endif; ?>
                        <?php if ($fechaEmision !== ''): ?>
                            <div><strong style="color:<?= $AZUL ?>;">Fecha de emisión:</strong> <?= $esc($fechaEmision) ?></div>
                        <?php endif; ?>
                        <?php if ($numAutorizacion !== ''): ?>
                            <div style="word-break:break-all;">
                                <strong style="color:<?= $AZUL ?>;">N.&ordm; de autorización:</strong> <?= $esc($numAutorizacion) ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($valorTotal !== ''): ?>
                            <div><strong style="color:<?= $AZUL ?>;">Valor total:</strong> <?= $esc($valorTotal) ?></div>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <p style="margin:0 0 22px;font-size:12px;color:<?= $SUAVE ?>;line-height:1.6;">
                Si tiene alguna duda, responda a este correo o comuníquese con nosotros.
            </p>

            <p style="margin:0 0 4px;font-size:13px;color:<?= $TEXTO ?>;">Atentamente,</p>
            <p style="margin:0 0 22px;font-size:13px;color:<?= $TEXTO ?>;line-height:1.6;">
                <strong><?= $esc($empresaNombre) ?></strong>
                <?php if ($empresaRuc !== ''): ?>
                    <br>RUC: <?= $esc($empresaRuc) ?>
                <?php endif; ?>
            </p>
        </td>
    </tr>

    <!-- Pie -->
    <tr>
        <td align="center" style="background:<?= $PIE ?>;padding:14px 24px;">
            <p style="margin:0;font-size:11px;color:<?= $SUAVE ?>;line-height:1.5;">
                Este mensaje y su archivo adjunto son confidenciales y están dirigidos exclusivamente al destinatario.
            </p>
        </td>
    </tr>
</table>
</body>
</html>
