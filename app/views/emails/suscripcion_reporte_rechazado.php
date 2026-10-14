<?php
/**
 * Correo al cliente: la empresa que controla las suscripciones rechazó el pago que
 * reportó. Variables en scope (desde SuscripcionReportePagoService::correoRechazo):
 * $data = [empresa, usuario, fecha, fecha_limite, archivo, motivo, url]
 */
$data = $data ?? [];
$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$f = static function ($v) {
    $v = (string) $v;
    $p = explode('-', substr($v, 0, 10));
    return count($p) === 3 ? $p[2] . '-' . $p[1] . '-' . $p[0] . (strlen($v) > 10 ? substr($v, 10) : '') : $v;
};
?>
<div style="font-family: Arial, Helvetica, sans-serif; color:#2d3748; max-width:640px; margin:0 auto;">
    <h2 style="color:#c0392b; margin-bottom:4px;">Tu reporte de pago fue rechazado</h2>
    <p style="margin:0 0 16px;">
        Hola<?= $data['usuario'] !== '' ? ' ' . $h($data['usuario']) : '' ?>, revisamos el pago de la suscripción que
        <?= $data['empresa'] !== '' ? 'reportaste para <strong>' . $h($data['empresa']) . '</strong>' : 'reportaste' ?>
        el <?= $h($f($data['fecha'])) ?> y no pudimos confirmarlo.
    </p>

    <p style="margin:0 0 6px; color:#718096; font-size:13px;">Motivo:</p>
    <div style="padding:10px 12px; background:#fdf2f2; border-left:3px solid #c0392b; font-size:13px; margin-bottom:16px;">
        <?= nl2br($h($data['motivo'])) ?>
    </div>

    <?php if ($data['archivo'] !== ''): ?>
    <p style="margin:0 0 8px; font-size:13px;">Comprobante enviado: <?= $h($data['archivo']) ?></p>
    <?php endif; ?>
    <p style="margin:0 0 8px; font-size:13px;">
        Al ingresar al sistema verás de nuevo el aviso de la suscripción, desde donde puedes
        enviarnos otro comprobante o un mensaje.
    </p>
    <p style="margin:16px 0;">
        <a href="<?= $h($data['url']) ?>" style="background:#0d6efd; color:#fff; padding:8px 16px; border-radius:4px; text-decoration:none; font-size:13px;">Ingresar al sistema</a>
    </p>

    <p style="margin:18px 0 0; font-size:12px; color:#718096;">
        Este es un aviso automático; por favor no respondas a este correo.
    </p>
</div>
