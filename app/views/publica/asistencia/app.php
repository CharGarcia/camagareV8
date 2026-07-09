<?php
/** @var string $base @var string $token @var string $nombre @var bool $valido */
$h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#0d6efd">
    <title>Mi credencial · Asistencia</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background: #0d6efd; color: #fff; min-height: 100dvh; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .card { background: #fff; color: #1a1a1a; border-radius: 20px; box-shadow: 0 20px 50px rgba(0,0,0,.25); max-width: 420px; width: 100%; padding: 32px 26px; text-align: center; }
        .ico { width: 72px; height: 72px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 34px; margin-bottom: 14px; }
        .ok { background: #e7f5ec; color: #1a9c53; }
        .err { background: #fdeaea; color: #d13438; }
        h1 { font-size: 1.35rem; margin: 6px 0 4px; }
        .nombre { font-size: 1.05rem; font-weight: 700; color: #0d6efd; margin-bottom: 14px; }
        p { color: #555; line-height: 1.5; font-size: .95rem; }
        .steps { text-align: left; margin: 18px 0 6px; padding: 0; list-style: none; }
        .steps li { display: flex; gap: 10px; align-items: flex-start; margin-bottom: 12px; font-size: .92rem; color: #333; }
        .steps .n { flex: 0 0 26px; height: 26px; border-radius: 50%; background: #0d6efd; color: #fff; font-weight: 700; display: flex; align-items: center; justify-content: center; font-size: .85rem; }
        .hint { margin-top: 18px; font-size: .8rem; color: #888; }
    </style>
</head>
<body>
    <div class="card">
        <?php if (!$valido): ?>
            <div class="ico err">✕</div>
            <h1>Credencial no válida</h1>
            <p>Este enlace no corresponde a una credencial activa. Solicita a tu empresa un nuevo enlace personal.</p>
        <?php else: ?>
            <div class="ico ok">✓</div>
            <h1>¡Listo!</h1>
            <div class="nombre"><?= $h($nombre) ?></div>
            <p>Tu teléfono quedó vinculado a tu credencial. Ya no necesitas volver a abrir este enlace.</p>
            <ul class="steps">
                <li><span class="n">1</span><span>Cuando llegues a tu punto de servicio, abre la cámara y <b>escanea el QR del sitio</b>.</span></li>
                <li><span class="n">2</span><span>Se abrirá la pantalla de marcación con tu identidad ya cargada.</span></li>
                <li><span class="n">3</span><span>Toma la <b>selfie</b>, permite la <b>ubicación</b> y presiona <b>Entrada</b> o <b>Salida</b>.</span></li>
            </ul>
            <p class="hint">Consejo: agrega esta página a tu pantalla de inicio para tenerla siempre a mano.</p>
        <?php endif; ?>
    </div>

    <?php if ($valido): ?>
    <script>
        // Guardar el token personal en este dispositivo (sin contraseñas).
        try {
            localStorage.setItem('casis_emp_token', <?= json_encode($token) ?>);
            localStorage.setItem('casis_emp_nombre', <?= json_encode($nombre) ?>);
        } catch (e) {}
    </script>
    <?php endif; ?>
</body>
</html>
