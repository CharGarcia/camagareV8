<?php
/**
 * Invitación al portal de representantes (envío masivo desde el listado de Alumnos).
 * Variables: $data = [nombre, empresa_nombre, url].
 */
$nombre  = htmlspecialchars((string) ($data['nombre'] ?? ''), ENT_QUOTES, 'UTF-8');
$empresa = htmlspecialchars((string) ($data['empresa_nombre'] ?? ''), ENT_QUOTES, 'UTF-8');
$url     = (string) ($data['url'] ?? '');
$urlSeg  = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;color:#333;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:24px 0;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 6px rgba(0,0,0,.08);">
        <tr><td style="background:#0d6efd;padding:20px 28px;color:#ffffff;">
          <h2 style="margin:0;font-size:19px;">Actualice los datos de sus hijos</h2>
        </td></tr>
        <tr><td style="padding:26px 28px;">
          <p style="margin:0 0 14px;font-size:14px;">Estimado(a) <b><?= $nombre ?></b>,</p>
          <p style="margin:0 0 18px;font-size:14px;line-height:1.6;">
            <?= $empresa !== '' ? '<b>' . $empresa . '</b> le invita' : 'Le invitamos' ?> a revisar y actualizar los datos de facturación
            y la información de sus hijos (datos personales, salud, contacto de emergencia y personas autorizadas a retirarlos).
          </p>
          <table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px;"><tr><td align="center">
            <a href="<?= $urlSeg ?>" style="display:inline-block;background:#0d6efd;color:#ffffff;text-decoration:none;padding:12px 26px;border-radius:6px;font-size:15px;font-weight:bold;">Actualizar datos</a>
          </td></tr></table>
          <p style="margin:0 0 10px;font-size:13px;line-height:1.6;color:#555;">
            Ingrese con su cédula o RUC: le enviaremos un código de verificación a este correo.
          </p>
          <p style="margin:0;font-size:12px;line-height:1.6;color:#666;">
            Si el botón no funciona, copie y pegue esta dirección en su navegador:<br>
            <span style="color:#0d6efd;word-break:break-all;"><?= $urlSeg ?></span>
          </p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
