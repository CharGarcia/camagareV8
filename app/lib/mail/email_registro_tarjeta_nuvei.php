<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registro de tarjeta</title>
<style>
  body{margin:0;padding:0;background:#f4f6f8;font-family:'Segoe UI',Arial,sans-serif;color:#333}
  .wrap{max-width:580px;margin:32px auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.1)}
  .header{background:#0d6efd;padding:28px 32px;text-align:center}
  .header h1{margin:0;color:#fff;font-size:22px;font-weight:700}
  .header p{margin:6px 0 0;color:#cfe2ff;font-size:13px}
  .body{padding:32px}
  .body p{margin:0 0 14px;font-size:15px;line-height:1.6}
  .btn{display:block;width:fit-content;margin:24px auto;padding:14px 36px;background:#0d6efd;color:#fff;border-radius:8px;text-decoration:none;font-size:15px;font-weight:700;text-align:center}
  .note{font-size:12px;color:#888;text-align:center;margin-top:20px;line-height:1.5}
  .footer{background:#f4f6f8;padding:18px 32px;text-align:center;font-size:11px;color:#aaa}
  .security{display:flex;align-items:center;justify-content:center;gap:6px;font-size:12px;color:#555;margin-top:18px}
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <h1>💳 Registra tu tarjeta</h1>
    <p><?= htmlspecialchars($data['empresa_nombre'] ?? '') ?></p>
  </div>
  <div class="body">
    <p>Hola <?= htmlspecialchars($data['cliente_nombre'] ?? 'Cliente') ?>,</p>
    <p>Para activar el cobro automático de tu suscripción, registra tu tarjeta de forma segura haciendo clic en el botón de abajo. <strong>No se realizará ningún cobro en este momento</strong> — solo se guarda tu tarjeta para los cobros periódicos futuros.</p>

    <a href="<?= htmlspecialchars($data['url_registro']) ?>" class="btn">Registrar tarjeta</a>

    <p class="note">
      Si no reconoces esta solicitud, ignora este mensaje.
    </p>

    <div class="security">
      <span>🔒</span>
      <span>Procesado de forma segura por <strong>Nuvei</strong></span>
    </div>
  </div>
  <div class="footer">
    &copy; <?= date('Y') ?> <?= htmlspecialchars($data['empresa_nombre'] ?? '') ?> — No respondas a este correo.
  </div>
</div>
</body>
</html>
