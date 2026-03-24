<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
        content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <title>Recuperar cuenta</title>
</head>

<body style="margin:0; padding:0; background-color:#f5f5f5;">
    <table align="center" cellpadding="0" cellspacing="0" width="100%" bgcolor="#ffffff"
        style="max-width:600px; margin:20px auto; font-family:Arial, sans-serif;">
        <tr>
            <td style="padding:20px; text-align:center; background-color:#244180; color:#ffffff;">
                <h1 style="margin:0; font-size:24px;">CaMaGaRe</h1>
            </td>
        </tr>
        <tr>
            <td style="padding:20px;">
                <p style="font-size:18px; color:#244180; margin:0 0 16px;">
                    Hola <?= htmlspecialchars(ucwords($data['nombre']), ENT_QUOTES, 'UTF-8') ?>,
                </p>
                <p style="font-size:15px; color:#7f7f7f; margin:0 0 12px;">
                    Has solicitado restablecer tu contraseña para el usuario
                    <strong><?= htmlspecialchars($data['receptor'], ENT_QUOTES, 'UTF-8') ?></strong>.
                </p>
                <p style="font-size:15px; color:#7f7f7f; margin:0 0 24px;">
                    Haz clic en el botón de abajo para confirmar y crear tu nueva contraseña:
                </p>
                <p style="text-align:center; margin:0 0 32px;">
                    <a href="<?= htmlspecialchars($data['url_recovery'], ENT_QUOTES, 'UTF-8') ?>"
                        target="_blank"
                        style="
               display:inline-block;
               padding:12px 24px;
               background-color:#307cf4;
               color:#ffffff;
               text-decoration:none;
               text-transform:uppercase;
               font-weight:bold;
               border-radius:4px;
             ">
                        Cambiar contraseña
                    </a>
                </p>
                <p style="font-size:15px; color:#0a4661; border-top:1px solid #ccc; padding-top:12px; margin:0;">
                    Si no puedes hacer clic en el botón, copia y pega esta URL en tu navegador:
                </p>
                <p style="margin:8px 0 24px; font-family:monospace; font-size:14px; color:#0a4661;">
                    <span style="pointer-events:none; cursor:text; user-select:all;">
                        <?= htmlspecialchars($data['url_recovery'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </p>
                <p style="font-size:14px; color:#3b74d7; text-align:center; margin:0;">
                    <a href="https://www.camagare.com" target="_blank" style="color:#3b74d7; text-decoration:none;">
                        www.camagare.com
                    </a>
                </p>
            </td>
        </tr>
    </table>
</body>

</html>