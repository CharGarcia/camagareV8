<?php
/**
 * Correo de aviso: un comprobante lleva más de 1 hora enviado al SRI sin
 * resolución definitiva. Variables en scope (desde enviar_correo_sri_pendientes):
 * $data = [empresa_nombre, tipo_comprobante, id_comprobante, clave_acceso, minutos]
 */
$data = $data ?? [];

$NOMBRES_TIPO = [
    'factura_venta'      => 'Factura de Venta',
    'factura_reembolso'  => 'Factura de Reembolso',
    'nota_credito'       => 'Nota de Crédito',
    'nota_debito'        => 'Nota de Débito',
    'retencion_compra'   => 'Retención de Compra',
    'guia_remision'      => 'Guía de Remisión',
    'liquidacion_compra' => 'Liquidación de Compra',
];

$tipo    = (string) ($data['tipo_comprobante'] ?? '');
$nombre  = $NOMBRES_TIPO[$tipo] ?? ucfirst(str_replace('_', ' ', $tipo));
$minutos = (int) ($data['minutos'] ?? 0);
$horas   = floor($minutos / 60);
$mins    = $minutos % 60;
$tiempoTexto = $horas > 0
    ? $horas . 'h ' . $mins . 'min'
    : $mins . ' min';
?>
<div style="font-family: Arial, Helvetica, sans-serif; color:#2d3748; max-width:640px; margin:0 auto;">
    <h2 style="color:#c0392b; margin-bottom:4px;">Comprobante sin resolver en el SRI</h2>
    <p style="margin:0 0 16px;">
        Hola, una <strong><?= htmlspecialchars($nombre) ?></strong> lleva
        <strong><?= htmlspecialchars($tiempoTexto) ?></strong> enviada al SRI sin que haya
        respondido con una autorización o un rechazo definitivo.
    </p>

    <table cellpadding="0" cellspacing="0" style="width:100%; border-collapse:collapse; font-size:13px; margin-bottom:16px;">
        <tr>
            <td style="padding:8px; border-bottom:1px solid #edf2f7; color:#718096;">Tipo de comprobante</td>
            <td style="padding:8px; border-bottom:1px solid #edf2f7;"><?= htmlspecialchars($nombre) ?></td>
        </tr>
        <tr>
            <td style="padding:8px; border-bottom:1px solid #edf2f7; color:#718096;">ID interno</td>
            <td style="padding:8px; border-bottom:1px solid #edf2f7;">#<?= (int) ($data['id_comprobante'] ?? 0) ?></td>
        </tr>
        <tr>
            <td style="padding:8px; border-bottom:1px solid #edf2f7; color:#718096;">Clave de acceso</td>
            <td style="padding:8px; border-bottom:1px solid #edf2f7; font-family: monospace; font-size:11px; word-break:break-all;"><?= htmlspecialchars((string) ($data['clave_acceso'] ?? '')) ?></td>
        </tr>
    </table>

    <p style="margin:0 0 8px;">
        El sistema seguirá consultando automáticamente el estado en el SRI, pero
        <strong>recuerda que los comprobantes electrónicos deben enviarse con la
        fecha de emisión del día de hoy</strong> — si sigue sin resolverse hasta el
        final del día, ya no podrá reenviarse con esa fecha.
    </p>
    <p style="margin:0;">
        Te recomendamos revisar el comprobante en el sistema. Si el problema
        persiste, puedes anularlo y emitir uno nuevo.
    </p>

    <p style="margin:18px 0 0; font-size:12px; color:#718096;">
        Este es un aviso automático; por favor no respondas a este correo.
    </p>
</div>
