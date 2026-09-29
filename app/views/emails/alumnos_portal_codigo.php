<?php
/**
 * Código de verificación del portal de representantes (Alumnos).
 * Variables: $data = [codigo, minutos, empresa_nombre, nombre].
 */
$codigo  = htmlspecialchars((string) ($data['codigo'] ?? ''), ENT_QUOTES, 'UTF-8');
$minutos = (int) ($data['minutos'] ?? 10);
$empresa = htmlspecialchars((string) ($data['empresa_nombre'] ?? ''), ENT_QUOTES, 'UTF-8');
$nombre  = htmlspecialchars((string) ($data['nombre'] ?? ''), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;color:#333;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:24px 0;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 6px rgba(0,0,0,.08);">
        <tr><td style="background:#0d6efd;padding:20px 28px;color:#ffffff;">
          <h2 style="margin:0;font-size:19px;">Código de verificación</h2>
        </td></tr>
        <tr><td style="padding:26px 28px;">
          <p style="margin:0 0 14px;font-size:14px;">Hola<?= $nombre !== '' ? ' <b>' . $nombre . '</b>' : '' ?>,</p>
          <p style="margin:0 0 18px;font-size:14px;line-height:1.6;">
            Use este código para actualizar los datos de sus hijos<?= $empresa !== '' ? ' en <b>' . $empresa . '</b>' : '' ?>:
          </p>
          <p style="margin:0 0 18px;text-align:center;">
            <span style="display:inline-block;font-size:32px;letter-spacing:8px;font-weight:bold;background:#f1f5ff;border:1px solid #cfe0ff;border-radius:8px;padding:12px 22px;color:#0d6efd;"><?= $codigo ?></span>
          </p>
          <p style="margin:0 0 10px;font-size:13px;line-height:1.6;color:#555;">
            Vence en <?= $minutos ?> minutos y sirve para <b>un solo envío</b> del formulario.
          </p>
          <p style="margin:0;font-size:12px;line-height:1.6;color:#888;">
            Si usted no lo pidió, ignore este correo: nadie puede ver ni cambiar sus datos sin este código.
          </p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
