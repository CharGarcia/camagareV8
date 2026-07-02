<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitud de Factura Recibida</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;">
<?php
$solicitud    = $data['solicitud'] ?? [];
$items        = json_decode($solicitud['items_json'] ?? '[]', true) ?: [];
$tokenCliente = $solicitud['token_cliente'] ?? '';
$urlEstado    = $tokenCliente ? url_absoluta('factura-express/' . $tokenCliente . '/estado') : '';
?>
<table align="center" cellpadding="0" cellspacing="0" width="100%"
       style="max-width:600px;margin:40px auto;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;border-radius:12px;overflow:hidden;box-shadow:0 4px 6px rgba(0,0,0,.05);border:1px solid #e2e8f0;">
    <tr>
        <td style="padding:32px 24px;text-align:center;background:linear-gradient(135deg,#14532d 0%,#16a34a 100%);color:#fff;">
            <h2 style="margin:0;font-size:22px;font-weight:700;">¡Solicitud recibida!</h2>
            <p style="margin:8px 0 0;opacity:.8;font-size:13px;">Hemos registrado tu solicitud correctamente</p>
        </td>
    </tr>
    <tr>
        <td style="padding:28px 28px 16px;">
            <p style="font-size:16px;color:#1e293b;font-weight:600;margin:0 0 8px;">
                Hola, <?= htmlspecialchars($solicitud['nombre_cliente'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            </p>
            <p style="font-size:14px;color:#475569;margin:0 0 20px;line-height:1.6;">
                Hemos recibido tu solicitud de factura y está siendo revisada. Te notificaremos cuando esté lista.
            </p>

            <table width="100%" cellpadding="6" cellspacing="0" style="background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;margin-bottom:20px;font-size:13px;">
                <tr>
                    <td style="color:#64748b;white-space:nowrap;width:45%;padding:6px 10px;">Identificación:</td>
                    <td style="color:#1e293b;padding:6px 10px;"><?= htmlspecialchars($solicitud['identificacion'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <tr style="background:#f1f5f9;">
                    <td style="color:#64748b;padding:6px 10px;">Plantilla:</td>
                    <td style="color:#1e293b;padding:6px 10px;"><?= htmlspecialchars($solicitud['nombre_plantilla'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <tr>
                    <td style="color:#64748b;padding:6px 10px;">Monto total:</td>
                    <td style="font-weight:700;color:#16a34a;font-size:15px;padding:6px 10px;">$<?= number_format((float)($solicitud['monto_total'] ?? 0), 2) ?></td>
                </tr>
            </table>

            <?php if (!empty($items)): ?>
            <p style="font-size:13px;color:#64748b;font-weight:600;margin:0 0 8px;">Detalle de tu solicitud:</p>
            <table width="100%" cellpadding="5" cellspacing="0" style="border-collapse:collapse;font-size:12px;margin-bottom:20px;">
                <thead>
                    <tr style="background:#16a34a;color:#fff;">
                        <th style="padding:6px 10px;text-align:left;">Descripción</th>
                        <th style="padding:6px 10px;text-align:center;">Cant.</th>
                        <th style="padding:6px 10px;text-align:right;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $i => $it): ?>
                    <tr style="background:<?= $i % 2 === 0 ? '#fff' : '#f0fdf4' ?>;">
                        <td style="padding:5px 10px;color:#1e293b;"><?= htmlspecialchars($it['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td style="padding:5px 10px;text-align:center;"><?= number_format((float)($it['cantidad'] ?? 1), 2) ?></td>
                        <td style="padding:5px 10px;text-align:right;font-weight:600;">$<?= number_format((float)($it['precio_unitario'] ?? 0) * (float)($it['cantidad'] ?? 1), 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <?php if ($urlEstado): ?>
            <table cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td align="center" style="padding:8px 0 16px;">
                        <a href="<?= htmlspecialchars($urlEstado, ENT_QUOTES, 'UTF-8') ?>"
                           style="display:inline-block;background:#16a34a;color:#fff;text-decoration:none;padding:12px 32px;border-radius:8px;font-size:14px;font-weight:600;">
                            Ver estado de mi solicitud
                        </a>
                    </td>
                </tr>
            </table>
            <p style="font-size:12px;color:#94a3b8;text-align:center;margin:0 0 16px;">
                También puedes copiar este enlace: <a href="<?= htmlspecialchars($urlEstado, ENT_QUOTES, 'UTF-8') ?>" style="color:#16a34a;word-break:break-all;"><?= htmlspecialchars($urlEstado, ENT_QUOTES, 'UTF-8') ?></a>
            </p>
            <?php endif; ?>
        </td>
    </tr>
    <tr>
        <td style="padding:16px 28px;border-top:1px solid #e2e8f0;background:#f8fafc;font-size:12px;color:#94a3b8;text-align:center;">
            Este mensaje fue generado automáticamente. Por favor no responder a este correo.
        </td>
    </tr>
</table>
</body>
</html>
