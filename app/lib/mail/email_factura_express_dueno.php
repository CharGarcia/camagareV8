<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nueva Solicitud de Factura Express</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;">
<?php
$solicitud = $data['solicitud'] ?? [];
$items     = json_decode($solicitud['items_json'] ?? '[]', true) ?: [];
$urlSolicitudes = url_absoluta('modulos/factura-express-solicitudes');
?>
<table align="center" cellpadding="0" cellspacing="0" width="100%"
       style="max-width:600px;margin:40px auto;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;border-radius:12px;overflow:hidden;box-shadow:0 4px 6px rgba(0,0,0,.05);border:1px solid #e2e8f0;">
    <tr>
        <td style="padding:32px 24px;text-align:center;background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%);color:#fff;">
            <h2 style="margin:0;font-size:22px;font-weight:700;">Nueva Solicitud de Factura Express</h2>
            <p style="margin:8px 0 0;opacity:.8;font-size:13px;">
                <i><?= htmlspecialchars($solicitud['nombre_plantilla'] ?? '', ENT_QUOTES, 'UTF-8') ?></i>
            </p>
        </td>
    </tr>
    <tr>
        <td style="padding:28px 28px 16px;">
            <p style="font-size:15px;color:#1e293b;margin:0 0 20px;font-weight:600;">
                Un cliente ha enviado una nueva solicitud:
            </p>

            <table width="100%" cellpadding="6" cellspacing="0" style="background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;margin-bottom:20px;font-size:13px;">
                <tr>
                    <td style="color:#64748b;white-space:nowrap;width:40%;padding:6px 10px;">Cliente:</td>
                    <td style="font-weight:600;color:#1e293b;padding:6px 10px;"><?= htmlspecialchars($solicitud['nombre_cliente'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <tr style="background:#f1f5f9;">
                    <td style="color:#64748b;padding:6px 10px;">Identificación:</td>
                    <td style="color:#1e293b;padding:6px 10px;"><?= htmlspecialchars($solicitud['identificacion'] ?? '', ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($solicitud['tipo_identificacion'] ?? '', ENT_QUOTES, 'UTF-8') ?>)</td>
                </tr>
                <?php if (!empty($solicitud['correo_cliente'])): ?>
                <tr>
                    <td style="color:#64748b;padding:6px 10px;">Correo:</td>
                    <td style="color:#1e293b;padding:6px 10px;"><?= htmlspecialchars($solicitud['correo_cliente'], ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($solicitud['telefono_cliente'])): ?>
                <tr style="background:#f1f5f9;">
                    <td style="color:#64748b;padding:6px 10px;">Teléfono:</td>
                    <td style="color:#1e293b;padding:6px 10px;"><?= htmlspecialchars($solicitud['telefono_cliente'], ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <?php endif; ?>
                <tr <?= empty($solicitud['telefono_cliente']) ? '' : 'style="background:#f1f5f9;"' ?>>
                    <td style="color:#64748b;padding:6px 10px;">Monto total:</td>
                    <td style="font-weight:700;color:#16a34a;font-size:15px;padding:6px 10px;">$<?= number_format((float)($solicitud['monto_total'] ?? 0), 2) ?></td>
                </tr>
            </table>

            <?php if (!empty($items)): ?>
            <p style="font-size:13px;color:#64748b;font-weight:600;margin:0 0 8px;">Ítems solicitados:</p>
            <table width="100%" cellpadding="5" cellspacing="0" style="border-collapse:collapse;font-size:12px;margin-bottom:20px;">
                <thead>
                    <tr style="background:#1e3a5f;color:#fff;">
                        <th style="padding:6px 10px;text-align:left;">Descripción</th>
                        <th style="padding:6px 10px;text-align:center;">Cant.</th>
                        <th style="padding:6px 10px;text-align:right;">P. Unit.</th>
                        <th style="padding:6px 10px;text-align:right;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $i => $it): ?>
                    <tr style="background:<?= $i % 2 === 0 ? '#fff' : '#f8fafc' ?>;">
                        <td style="padding:5px 10px;color:#1e293b;"><?= htmlspecialchars($it['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td style="padding:5px 10px;text-align:center;"><?= number_format((float)($it['cantidad'] ?? 1), 2) ?></td>
                        <td style="padding:5px 10px;text-align:right;">$<?= number_format((float)($it['precio_unitario'] ?? 0), 2) ?></td>
                        <td style="padding:5px 10px;text-align:right;font-weight:600;">$<?= number_format((float)($it['precio_unitario'] ?? 0) * (float)($it['cantidad'] ?? 1), 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <table cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td align="center" style="padding:8px 0 24px;">
                        <a href="<?= htmlspecialchars($urlSolicitudes, ENT_QUOTES, 'UTF-8') ?>"
                           style="display:inline-block;background:#0f172a;color:#fff;text-decoration:none;padding:12px 32px;border-radius:8px;font-size:14px;font-weight:600;">
                            Ver solicitud en el sistema
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td style="padding:16px 28px;border-top:1px solid #e2e8f0;background:#f8fafc;font-size:12px;color:#94a3b8;text-align:center;">
            Este mensaje fue generado automáticamente por el sistema. Por favor no responder.
        </td>
    </tr>
</table>
</body>
</html>
