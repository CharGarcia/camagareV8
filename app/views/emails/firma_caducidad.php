<?php
/**
 * Correo de aviso: la firma electrónica de la empresa está por caducar (o
 * acaba de caducar). Variables en scope (desde enviar_correo_firma_caducidad):
 * $data = [empresa_nombre, empresa_ruc, fecha_expiracion (Y-m-d), dias, url]
 */
$data = $data ?? [];

$dias  = (int) ($data['dias'] ?? 0);
$fecha = (string) ($data['fecha_expiracion'] ?? '');
$ts    = $fecha !== '' ? strtotime($fecha) : false;
$fechaTexto = $ts !== false ? date('d-m-Y', $ts) : $fecha;

if ($dias > 1) {
    $titulo  = 'Su firma electrónica caduca en ' . $dias . ' días';
    $color   = '#d68910';
    $mensaje = 'La firma electrónica con la que se firman sus comprobantes caduca en <strong>' . $dias . ' días</strong>.';
} elseif ($dias === 1) {
    $titulo  = 'Su firma electrónica caduca mañana';
    $color   = '#d68910';
    $mensaje = 'La firma electrónica con la que se firman sus comprobantes caduca <strong>mañana</strong>.';
} elseif ($dias === 0) {
    $titulo  = 'Su firma electrónica caduca hoy';
    $color   = '#c0392b';
    $mensaje = 'La firma electrónica con la que se firman sus comprobantes caduca <strong>hoy</strong>.';
} else {
    $atras   = abs($dias);
    $titulo  = 'Su firma electrónica caducó';
    $color   = '#c0392b';
    $mensaje = 'La firma electrónica con la que se firman sus comprobantes caducó hace <strong>'
             . $atras . ' día' . ($atras === 1 ? '' : 's') . '</strong>.';
}
$url = (string) ($data['url'] ?? '');
?>
<div style="font-family: Arial, Helvetica, sans-serif; color:#2d3748; max-width:640px; margin:0 auto;">
    <h2 style="color:<?= $color ?>; margin-bottom:4px;"><?= htmlspecialchars($titulo) ?></h2>
    <p style="margin:0 0 16px;">
        Hola, <?= $mensaje ?>
        A partir de esa fecha <strong>el SRI rechazará todos los comprobantes electrónicos</strong>
        (facturas, retenciones, notas de crédito, guías) que se intenten enviar con ella.
    </p>

    <table cellpadding="0" cellspacing="0" style="width:100%; border-collapse:collapse; font-size:13px; margin-bottom:16px;">
        <tr>
            <td style="padding:8px; border-bottom:1px solid #edf2f7; color:#718096; width:38%;">Empresa</td>
            <td style="padding:8px; border-bottom:1px solid #edf2f7;"><?= htmlspecialchars((string) ($data['empresa_nombre'] ?? '')) ?></td>
        </tr>
        <tr>
            <td style="padding:8px; border-bottom:1px solid #edf2f7; color:#718096;">RUC</td>
            <td style="padding:8px; border-bottom:1px solid #edf2f7;"><?= htmlspecialchars((string) ($data['empresa_ruc'] ?? '')) ?></td>
        </tr>
        <tr>
            <td style="padding:8px; border-bottom:1px solid #edf2f7; color:#718096;">Fecha de caducidad</td>
            <td style="padding:8px; border-bottom:1px solid #edf2f7;"><strong><?= htmlspecialchars($fechaTexto) ?></strong></td>
        </tr>
    </table>

    <p style="margin:0 0 8px;">
        <strong>Qué hacer:</strong> solicite la renovación del certificado a su entidad
        certificadora (ANF, UANATACA, Security Data, Banco Central u otra) y, cuando lo
        reciba, cargue el nuevo archivo <code>.p12</code> con su clave en la ficha de la
        empresa, pestaña <strong>Firma</strong>, marcándolo como firma activa.
    </p>
    <p style="margin:0 0 8px;">
        La renovación con la entidad certificadora no es inmediata: mientras no haya una
        firma vigente cargada, no se puede facturar electrónicamente.
    </p>
    <?php if ($url !== ''): ?>
    <p style="margin:16px 0 0;">
        <a href="<?= htmlspecialchars($url) ?>"
           style="display:inline-block; background:#2c3e50; color:#fff; text-decoration:none; padding:10px 18px; border-radius:4px; font-size:13px;">
            Ir a la ficha de la empresa
        </a>
    </p>
    <?php endif; ?>

    <p style="margin:18px 0 0; font-size:12px; color:#718096;">
        Este es un aviso automático; por favor no respondas a este correo.
    </p>
</div>
