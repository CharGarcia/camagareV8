<?php
/**
 * Cuerpo del correo con el que se le pide al empleado que llene su solicitud de
 * vacaciones. Variables disponibles: $data (array).
 */
$empleado = htmlspecialchars((string) ($data['empleado_nombre'] ?? ''), ENT_QUOTES, 'UTF-8');
$empresa  = htmlspecialchars((string) ($data['empresa_nombre'] ?? ''), ENT_QUOTES, 'UTF-8');
$expira   = htmlspecialchars((string) ($data['expira'] ?? ''), ENT_QUOTES, 'UTF-8');
$url      = (string) ($data['url'] ?? '');
$urlSeg   = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;color:#333;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:24px 0;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 6px rgba(0,0,0,.08);">

        <tr>
          <td style="background:#0d6efd;padding:20px 28px;color:#ffffff;">
            <h2 style="margin:0;font-size:19px;">Solicitud de vacaciones</h2>
          </td>
        </tr>

        <tr>
          <td style="padding:26px 28px;">
            <p style="margin:0 0 14px;font-size:14px;">Hola <b><?= $empleado ?></b>,</p>

            <p style="margin:0 0 18px;font-size:14px;line-height:1.6;">
              <?= $empresa !== '' ? '<b>' . $empresa . '</b> le env&iacute;a este enlace' : 'Le enviamos este enlace' ?>
              para que solicite sus vacaciones. Al abrirlo ver&aacute; los d&iacute;as que tiene disponibles y
              podr&aacute; indicar desde y hasta cu&aacute;ndo quiere tomarlas.
            </p>

            <?php if ($url !== ''): ?>
            <table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px;">
              <tr><td align="center">
                <a href="<?= $urlSeg ?>" style="display:inline-block;background:#0d6efd;color:#ffffff;text-decoration:none;padding:12px 26px;border-radius:6px;font-size:15px;font-weight:bold;">
                  Solicitar mis vacaciones
                </a>
              </td></tr>
            </table>

            <p style="margin:0 0 14px;font-size:12px;line-height:1.6;color:#666;">
              Si el bot&oacute;n no funciona, copie y pegue esta direcci&oacute;n en su navegador:<br>
              <span style="color:#0d6efd;word-break:break-all;"><?= $urlSeg ?></span>
            </p>
            <?php endif; ?>

            <table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px;">
              <tr>
                <td style="padding:10px 14px;background:#fff8e1;border-left:3px solid #ffc107;font-size:13px;line-height:1.6;">
                  El enlace es <b>personal</b> y sirve <b>una sola vez</b><?= $expira !== '' ? '. Caduca el <b>' . $expira . '</b>' : '' ?>.
                  No lo comparta.
                </td>
              </tr>
            </table>

            <p style="margin:0;font-size:13px;line-height:1.6;color:#666;">
              Cuando env&iacute;e el formulario, su solicitud queda en revisi&oacute;n y le avisaremos si fue
              aprobada o rechazada.
            </p>
          </td>
        </tr>

        <tr>
          <td style="padding:14px 28px;background:#f8f9fa;border-top:1px solid #e9ecef;font-size:11px;color:#888;">
            Este es un correo autom&aacute;tico<?= $empresa !== '' ? ' de ' . $empresa : '' ?>. No responda a este mensaje.
          </td>
        </tr>

      </table>
    </td></tr>
  </table>
</body>
</html>
