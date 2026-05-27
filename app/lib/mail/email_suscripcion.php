<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($asunto ?? 'Notificación de Suscripción') ?></title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
        .header { padding: 28px 32px; background: #1a56db; color: #fff; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 700; }
        .header p { margin: 6px 0 0; font-size: 13px; opacity: .85; }
        .body { padding: 28px 32px; color: #333; }
        .body p { margin: 0 0 14px; font-size: 14px; line-height: 1.6; }
        .card { background: #f8f9fa; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px 20px; margin: 16px 0; }
        .card .row { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px dashed #e2e8f0; font-size: 13px; }
        .card .row:last-child { border-bottom: none; }
        .card .label { color: #6b7280; }
        .card .value { font-weight: 600; color: #111827; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-danger  { background: #fee2e2; color: #991b1b; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .footer { padding: 16px 32px; background: #f8f9fa; text-align: center; font-size: 11px; color: #9ca3af; border-top: 1px solid #e2e8f0; }
        .alert { padding: 12px 16px; border-radius: 6px; margin: 16px 0; font-size: 13px; }
        .alert-danger  { background: #fee2e2; color: #991b1b; border-left: 4px solid #dc2626; }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-warning { background: #fef3c7; color: #92400e; border-left: 4px solid #f59e0b; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>
            <?php if (($tipo ?? '') === 'factura_generada'): ?>
                📄 Nueva Factura de Suscripción
            <?php elseif (($tipo ?? '') === 'cobro_exitoso'): ?>
                ✅ Cobro Procesado Exitosamente
            <?php elseif (($tipo ?? '') === 'cobro_fallido'): ?>
                ❌ Error en el Cobro de Suscripción
            <?php elseif (($tipo ?? '') === 'suspension'): ?>
                ⚠️ Suscripción Suspendida
            <?php elseif (($tipo ?? '') === 'vencimiento_proximo'): ?>
                🔔 Próximo Cobro de Suscripción
            <?php else: ?>
                Notificación de Suscripción
            <?php endif; ?>
        </h1>
        <p><?= date('d \d\e F \d\e Y') ?></p>
    </div>

    <div class="body">
        <p>Estimado/a <strong><?= htmlspecialchars($data['cliente_nombre'] ?? '') ?></strong>,</p>

        <?php if (($tipo ?? '') === 'factura_generada'): ?>
            <p>Se ha generado una nueva factura correspondiente a su suscripción. A continuación encontrará el detalle:</p>
            <div class="alert alert-success"><strong>Factura emitida correctamente.</strong> Revise su portal o contáctenos si tiene alguna consulta.</div>

        <?php elseif (($tipo ?? '') === 'cobro_exitoso'): ?>
            <p>Su cobro automático ha sido procesado de forma exitosa. Su suscripción continúa activa.</p>
            <div class="alert alert-success"><strong>Pago confirmado.</strong></div>

        <?php elseif (($tipo ?? '') === 'cobro_fallido'): ?>
            <p>No pudimos procesar el cobro automático de su suscripción. Por favor verifique los datos de su tarjeta o contáctenos para regularizar su cuenta.</p>
            <div class="alert alert-danger"><strong>Acción requerida:</strong> Actualice su método de pago para evitar la suspensión del servicio.</div>

        <?php elseif (($tipo ?? '') === 'suspension'): ?>
            <p>Su suscripción ha sido <strong>suspendida</strong> debido a múltiples intentos de cobro fallidos. Para reactivar el servicio, comuníquese con nosotros.</p>
            <div class="alert alert-warning"><strong>Servicio suspendido.</strong> Contáctenos para reactivar su cuenta.</div>

        <?php elseif (($tipo ?? '') === 'vencimiento_proximo'): ?>
            <p>Le recordamos que su próximo cobro de suscripción está próximo. Asegúrese de tener los fondos disponibles.</p>
        <?php endif; ?>

        <!-- Detalle de la suscripción -->
        <div class="card">
            <div class="row"><span class="label">Plan</span><span class="value"><?= htmlspecialchars($data['plan_nombre'] ?? '') ?></span></div>
            <?php if (!empty($data['monto'])): ?>
                <div class="row"><span class="label">Monto</span><span class="value">$<?= htmlspecialchars($data['monto']) ?></span></div>
            <?php endif; ?>
            <div class="row"><span class="label">Fecha</span><span class="value"><?= htmlspecialchars($data['fecha_cobro'] ?? '') ?></span></div>
            <?php if (!empty($data['proximo_cobro'])): ?>
                <div class="row">
                    <span class="label">Próximo cobro</span>
                    <span class="value"><?= date('d-m-Y', strtotime($data['proximo_cobro'])) ?></span>
                </div>
            <?php endif; ?>
        </div>

        <p style="font-size:13px; color:#6b7280;">Si tiene alguna consulta, no dude en contactarnos respondiendo este correo.</p>
    </div>

    <div class="footer">
        Este correo es generado automáticamente por el sistema. Por favor no responda directamente a este mensaje.
    </div>
</div>
</body>
</html>
