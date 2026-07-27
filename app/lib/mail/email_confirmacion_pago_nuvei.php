<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Confirmación de pago</title>
<style>
  body{margin:0;padding:0;background:#f4f6f8;font-family:'Segoe UI',Arial,sans-serif;color:#333}
  .wrap{max-width:580px;margin:32px auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.1)}
  .header{padding:28px 32px;text-align:center}
  .header.ok{background:#198754}
  .header.fail{background:#dc3545}
  .header h1{margin:0;color:#fff;font-size:22px;font-weight:700}
  .header p{margin:6px 0 0;color:#fff;opacity:.85;font-size:13px}
  .body{padding:32px}
  .body p{margin:0 0 14px;font-size:15px;line-height:1.6}
  .detail-box{background:#f8f9fa;border-left:4px solid <?= $data['aprobado'] ? '#198754' : '#dc3545' ?>;border-radius:4px;padding:14px 18px;margin:20px 0}
  .detail-box .label{font-size:11px;color:#888;text-transform:uppercase;letter-spacing:.5px}
  .detail-box .value{font-size:20px;font-weight:700;color:#333;margin-top:2px}
  .detail-box .ref{font-size:13px;color:#555;margin-top:4px}
  .datos{width:100%;border-collapse:collapse;margin-top:16px}
  .datos td{padding:6px 0;font-size:13px;border-bottom:1px solid #eee}
  .datos td:first-child{color:#888;width:45%}
  .datos td:last-child{font-weight:600;color:#333;font-family:monospace}
  .footer{background:#f4f6f8;padding:18px 32px;text-align:center;font-size:11px;color:#aaa}
  .security{display:flex;align-items:center;justify-content:center;gap:6px;font-size:12px;color:#555;margin-top:18px}
</style>
</head>
<body>
<div class="wrap">
  <div class="header <?= $data['aprobado'] ? 'ok' : 'fail' ?>">
    <h1><?= $data['aprobado'] ? '✓ Pago aprobado' : '✗ Pago rechazado' ?></h1>
    <p><?= htmlspecialchars($data['empresa_nombre'] ?? '') ?></p>
  </div>
  <div class="body">
    <p>Hola <?= htmlspecialchars($data['cliente_nombre'] ?? 'Cliente') ?>,</p>
    <p>
      <?= $data['aprobado']
          ? 'Tu pago fue procesado correctamente. Aquí tienes el detalle de la transacción.'
          : 'Tu pago no pudo ser procesado. Puedes intentarlo nuevamente o usar otro método de pago.' ?>
    </p>

    <div class="detail-box">
      <div class="label">Monto</div>
      <div class="value">$ <?= number_format((float)($data['monto'] ?? 0), 2) ?> USD</div>
      <div class="ref"><?= htmlspecialchars($data['descripcion'] ?? '') ?></div>
    </div>

    <table class="datos">
      <tr><td>ID de transacción</td><td><?= htmlspecialchars($data['transaction_id'] ?? '—') ?></td></tr>
      <?php if (!empty($data['authorization_code'])): ?>
      <tr><td>Código de autorización</td><td><?= htmlspecialchars($data['authorization_code']) ?></td></tr>
      <?php endif; ?>
      <tr><td>Fecha</td><td><?= date('d-m-Y H:i:s') ?></td></tr>
    </table>

    <div class="security">
      <span>🔒</span>
      <span>Pago seguro procesado por <strong>Nuvei</strong> · PCI DSS</span>
    </div>
  </div>
  <div class="footer">
    &copy; <?= date('Y') ?> <?= htmlspecialchars($data['empresa_nombre'] ?? '') ?> — No respondas a este correo.
  </div>
</div>
</body>
</html>
