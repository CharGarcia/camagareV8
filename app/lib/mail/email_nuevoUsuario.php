<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
        content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <title>Invitación al Sistema</title>
</head>

<body style="margin:0; padding:0; background-color:#f8fafc;">
    <table align="center" cellpadding="0" cellspacing="0" width="100%" bgcolor="#ffffff"
        style="max-width:600px; margin:40px auto; font-family:'Segoe UI', Roboto, Helvetica, Arial, sans-serif; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;">
        <tr>
            <td style="padding:40px 20px; text-align:center; background: linear-gradient(135deg, #1e293b 0%, #334155 100%); color:#ffffff;">
                <h1 style="margin:0; font-size:28px; font-weight: 700; letter-spacing: -0.5px;">CaMaGaRe</h1>
                <p style="margin: 8px 0 0; opacity: 0.8; font-size: 14px;">Gestión Empresarial Inteligente</p>
            </td>
        </tr>
        <tr>
            <td style="padding:40px 30px;">
                <p style="font-size:20px; color:#1e293b; margin:0 0 20px; font-weight: 600;">
                    ¡Hola, <?= htmlspecialchars(ucwords($data['nombre']), ENT_QUOTES, 'UTF-8') ?>!
                </p>
                <p style="font-size:16px; color:#475569; margin:0 0 16px; line-height: 1.6;">
                    Has sido invitado a formar parte de nuestra plataforma. Para comenzar a utilizar el sistema, es necesario que completes tu registro de perfil.
                </p>
                <p style="font-size:16px; color:#475569; margin:0 0 32px; line-height: 1.6;">
                    Al hacer clic en el siguiente botón, podrás ingresar tus datos personales, identificación y establecer tu contraseña de acceso.
                </p>
                <p style="text-align:center; margin:0 0 40px;">
                    <a href="<?= htmlspecialchars($data['url_invite'], ENT_QUOTES, 'UTF-8') ?>"
                        target="_blank"
                        style="
               display:inline-block;
               padding:14px 32px;
               background-color:#3b82f6;
               color:#ffffff;
               text-decoration:none;
               font-weight:600;
               border-radius:8px;
               transition: background-color 0.2s;
             ">
                        Completar mi Registro
                    </a>
                </p>
                <div style="background-color: #f1f5f9; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
                    <p style="font-size:13px; color:#64748b; margin:0 0 10px; font-weight: 500;">
                        Si el botón no funciona, copia y pega el siguiente enlace:
                    </p>
                    <p style="margin:0; font-family:monospace; font-size:13px; color:#3b82f6; word-break: break-all;">
                        <?= htmlspecialchars($data['url_invite'], ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </div>
                <p style="font-size:14px; color:#94a3b8; text-align:center; margin:0; border-top: 1px solid #e2e8f0; padding-top: 30px;">
                    Este enlace de invitación es de un solo uso. Si no esperabas esta invitación, puedes ignorar este mensaje.
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding:20px; text-align:center; background-color:#f8fafc; color:#94a3b8; font-size:12px;">
                &copy; <?= date('Y') ?> CaMaGaRe. Todos los derechos reservados.
            </td>
        </tr>
    </table>
</body>

</html>
