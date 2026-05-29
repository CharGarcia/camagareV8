<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Enlace de pago</title>
<style>
  body{margin:0;padding:0;background:#f4f6f8;font-family:'Segoe UI',Arial,sans-serif;color:#333}
  .wrap{max-width:580px;margin:32px auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.1)}
  .header{background:#0d6efd;padding:28px 32px;text-align:center}
  .header h1{margin:0;color:#fff;font-size:22px;font-weight:700}
  .header p{margin:6px 0 0;color:#cfe2ff;font-size:13px}
  .body{padding:32px}
  .body p{margin:0 0 14px;font-size:15px;line-height:1.6}
  .detail-box{background:#f8f9fa;border-left:4px solid #0d6efd;border-radius:4px;padding:14px 18px;margin:20px 0}
  .detail-box .label{font-size:11px;color:#888;text-transform:uppercase;letter-spacing:.5px}
  .detail-box .value{font-size:20px;font-weight:700;color:#0d6efd;margin-top:2px}
  .detail-box .ref{font-size:13px;color:#555;margin-top:4px}
  .btn{display:block;width:fit-content;margin:24px auto;padding:14px 36px;background:#0d6efd;color:#fff;border-radius:8px;text-decoration:none;font-size:15px;font-weight:700;text-align:center}
  .note{font-size:12px;color:#888;text-align:center;margin-top:20px;line-height:1.5}
  .footer{background:#f4f6f8;padding:18px 32px;text-align:center;font-size:11px;color:#aaa}
  .security{display:flex;align-items:center;justify-content:center;gap:6px;font-size:12px;color:#555;margin-top:18px}
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <h1>💳 Pago con tarjeta</h1>
    <p><?= htmlspecialchars($data['empresa_nombre'] ?? '') ?></p>
  </div>
  <div class="body">
    <p>Hola <?= htmlspecialchars($data['cliente_nombre'] ?? 'Cliente') ?>,</p>
    <p>Tienes un saldo pendiente de pago. Haz clic en el botón de abajo para completar tu pago de forma segura con tarjeta de crédito o débito.</p>

    <div class="detail-box">
      <div class="label">Total a pagar</div>
      <div class="value">$ <?= number_format((float)($data['monto'] ?? 0), 2) ?> USD</div>
      <div class="ref"><?= htmlspecialchars($data['descripcion'] ?? '') ?></div>
    </div>

    <a href="<?= htmlspecialchars($data['url_pago']) ?>" class="btn">Pagar ahora</a>

    <p class="note">
      Este enlace es de un solo uso y expira en <strong>10 minutos</strong> una vez abierto.<br>
      Si no solicitaste este cobro, ignora este mensaje.
    </p>

    <div class="security">
      <span>🔒</span>
      <span>Pago seguro procesado por <strong>Payphone</strong> · PCI DSS 4.0</span>
    </div>
  </div>
  <div class="footer">
    &copy; <?= date('Y') ?> <?= htmlspecialchars($data['empresa_nombre'] ?? '') ?> — No respondas a este correo.
  </div>
</div>
</body>
</html>
