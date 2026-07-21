<?php
/**
 * Cuerpo del correo de documentos legales (acuerdo de datos + contrato de uso).
 * Variables disponibles: $data (array), $correoDestino (string).
 */
$empresa   = htmlspecialchars((string) ($data['empresa_nombre'] ?? ''));
$ruc       = htmlspecialchars((string) ($data['empresa_ruc'] ?? ''));
$acuerdo   = htmlspecialchars((string) ($data['acuerdo_titulo'] ?? 'Acuerdo de uso de datos'));
$contrato  = htmlspecialchars((string) ($data['contrato_titulo'] ?? 'Contrato de uso del sistema'));
$url       = (string) ($data['url_aceptacion'] ?? '');
$sistema   = htmlspecialchars((string) ($data['sistema_nombre'] ?? 'CaMaGaRe'));
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
            <h2 style="margin:0;font-size:19px;">Documentos legales para el uso del sistema</h2>
          </td>
        </tr>

        <tr>
          <td style="padding:26px 28px;">
            <p style="margin:0 0 14px;font-size:14px;">Estimados señores de <b><?= $empresa ?></b><?= $ruc !== '' ? ' (RUC ' . $ruc . ')' : '' ?>,</p>

            <p style="margin:0 0 14px;font-size:14px;line-height:1.6;">
              Les damos la bienvenida a <b><?= $sistema ?></b>. Adjuntamos en formato PDF los documentos
              que regulan la prestación del servicio y el tratamiento de su información:
            </p>

            <table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px;">
              <tr>
                <td style="padding:10px 14px;background:#f8f9fa;border-left:3px solid #0d6efd;font-size:13px;">
                  📄 <b><?= $acuerdo ?></b>
                </td>
              </tr>
              <tr><td style="height:6px;"></td></tr>
              <tr>
                <td style="padding:10px 14px;background:#f8f9fa;border-left:3px solid #198754;font-size:13px;">
                  📄 <b><?= $contrato ?></b>
                </td>
              </tr>
            </table>

            <p style="margin:0 0 18px;font-size:14px;line-height:1.6;">
              Le agradecemos revisarlos y registrar su aceptación en línea. Al aceptar quedará
              constancia de la fecha, hora y dirección IP.
            </p>

            <?php if ($url !== ''): ?>
            <table cellpadding="0" cellspacing="0" style="margin:0 auto 20px;">
              <tr>
                <td align="center" style="background:#198754;border-radius:6px;">
                  <a href="<?= htmlspecialchars($url) ?>"
                     style="display:inline-block;padding:13px 30px;color:#ffffff;text-decoration:none;font-size:15px;font-weight:bold;">
                     Revisar y aceptar documentos
                  </a>
                </td>
              </tr>
            </table>

            <p style="margin:0 0 6px;font-size:12px;color:#666;">Si el botón no funciona, copie y pegue este enlace:</p>
            <p style="margin:0 0 18px;font-size:11px;color:#0d6efd;word-break:break-all;"><?= htmlspecialchars($url) ?></p>
            <?php endif; ?>

            <p style="margin:0;font-size:12px;color:#888;line-height:1.6;">
              Si tiene alguna consulta sobre estos documentos, responda a este correo.
            </p>
          </td>
        </tr>

        <tr>
          <td style="background:#f8f9fa;padding:14px 28px;text-align:center;font-size:11px;color:#999;">
            Este es un mensaje automático de <?= $sistema ?>. Por favor no lo elimine: contiene sus documentos contractuales.
          </td>
        </tr>

      </table>
    </td></tr>
  </table>
</body>
</html>
