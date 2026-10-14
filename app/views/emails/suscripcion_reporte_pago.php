<?php
/**
 * Correo a la empresa que controla las suscripciones: un cliente reportó el pago de su
 * suscripción (mensaje y/o comprobante adjunto). Variables en scope (desde
 * SuscripcionReportePagoService::correoControladora):
 * $data = [id, empresa, ruc, empresa_mail, usuario, usuario_mail, mensaje, archivo, estado,
 *          fecha_limite, fecha_periodo, documento, saldo, dias_revision, url]
 */
$data = $data ?? [];
$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$f = static function ($v) {
    $p = explode('-', substr((string) $v, 0, 10));
    return count($p) === 3 ? $p[2] . '-' . $p[1] . '-' . $p[0] : (string) $v;
};
$filas = [
    'Empresa'              => $data['empresa'] . ($data['ruc'] !== '' ? ' (' . $data['ruc'] . ')' : ''),
    'Reportado por'        => $data['usuario'] . ($data['usuario_mail'] !== '' ? ' — ' . $data['usuario_mail'] : ''),
    'Correo de la empresa' => $data['empresa_mail'],
    'Fecha límite de pago' => $data['fecha_limite'] !== '' ? $f($data['fecha_limite']) : '',
    'Documento pendiente'  => $data['documento'],
    'Saldo pendiente'      => $data['saldo'] !== null ? '$ ' . number_format((float) $data['saldo'], 2, '.', ',') : '',
    'Comprobante adjunto'  => $data['archivo'] !== '' ? $data['archivo'] : 'No adjuntó archivo',
];
?>
<div style="font-family: Arial, Helvetica, sans-serif; color:#2d3748; max-width:640px; margin:0 auto;">
    <h2 style="color:#0d6efd; margin-bottom:4px;">Pago de suscripción reportado</h2>
    <p style="margin:0 0 16px;">
        <strong><?= $h($data['empresa']) ?></strong> informó desde el sistema el pago de su suscripción.
        Revisa que el pago sea correcto y regístralo en <strong>Suscripciones</strong>.
    </p>

    <table cellpadding="0" cellspacing="0" style="width:100%; border-collapse:collapse; font-size:13px; margin-bottom:16px;">
        <?php foreach ($filas as $etiqueta => $valor): if ($valor === '' || $valor === null) continue; ?>
        <tr>
            <td style="padding:8px; border-bottom:1px solid #edf2f7; color:#718096; white-space:nowrap;"><?= $h($etiqueta) ?></td>
            <td style="padding:8px; border-bottom:1px solid #edf2f7;"><?= $h($valor) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <?php if (trim((string) $data['mensaje']) !== ''): ?>
    <p style="margin:0 0 6px; color:#718096; font-size:13px;">Mensaje del cliente:</p>
    <div style="padding:10px 12px; background:#f7fafc; border-left:3px solid #0d6efd; font-size:13px; margin-bottom:16px;">
        <?= nl2br($h($data['mensaje'])) ?>
    </div>
    <?php endif; ?>

    <p style="margin:0 0 8px; font-size:13px;">
        Mientras lo revisas, el aviso de suscripción vencida <strong>no se le muestra</strong> al cliente
        (máximo <?= (int) $data['dias_revision'] ?> días). Si registras el cobro, el aviso desaparece
        solo. Si el pago no es correcto, recházalo en <strong>Suscripciones → Pagos reportados</strong>
        indicando el motivo: el aviso le vuelve a salir y le llega ese motivo por correo.
    </p>
    <p style="margin:16px 0;">
        <a href="<?= $h($data['url']) ?>" style="background:#0d6efd; color:#fff; padding:8px 16px; border-radius:4px; text-decoration:none; font-size:13px;">Abrir Suscripciones</a>
    </p>

    <p style="margin:18px 0 0; font-size:12px; color:#718096;">
        Este es un aviso automático; por favor no respondas a este correo.
    </p>
</div>
