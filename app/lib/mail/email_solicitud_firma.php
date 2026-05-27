<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Formulario de Firma Electrónica</title>
</head>
<body style="margin:0;padding:0;background-color:#f8fafc;">
<table align="center" cellpadding="0" cellspacing="0" width="100%" bgcolor="#ffffff"
       style="max-width:600px;margin:40px auto;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;border-radius:12px;overflow:hidden;box-shadow:0 4px 6px rgba(0,0,0,.05);border:1px solid #e2e8f0;">
    <tr>
        <td style="padding:40px 20px;text-align:center;background:linear-gradient(135deg,#1e293b 0%,#334155 100%);color:#fff;">
            <h1 style="margin:0;font-size:28px;font-weight:700;letter-spacing:-.5px;"><?= htmlspecialchars($data['empresa_nombre'] ?? 'CaMaGaRe', ENT_QUOTES, 'UTF-8') ?></h1>
            <p style="margin:8px 0 0;opacity:.8;font-size:14px;">Firma Electrónica</p>
        </td>
    </tr>
    <tr>
        <td style="padding:40px 30px;">
            <p style="font-size:20px;color:#1e293b;margin:0 0 20px;font-weight:600;">
                ¡Hola<?= !empty($data['nombre_destino']) ? ', ' . htmlspecialchars($data['nombre_destino'], ENT_QUOTES, 'UTF-8') : '' ?>!
            </p>
            <p style="font-size:16px;color:#475569;margin:0 0 16px;line-height:1.6;">
                Te enviamos este enlace para que puedas completar el formulario de solicitud de <strong>Firma Electrónica</strong>.
            </p>
            <p style="font-size:15px;color:#475569;margin:0 0 8px;line-height:1.6;">
                Por favor completa tus datos personales, elige el tipo de firma y adjunta los documentos requeridos.
            </p>
            <p style="font-size:13px;color:#94a3b8;margin:0 0 32px;">
                Este enlace expira el <strong><?= htmlspecialchars($data['expira'], ENT_QUOTES, 'UTF-8') ?></strong>.
            </p>
            <table cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td align="center" style="padding:10px 0 32px;">
                        <a href="<?= htmlspecialchars($data['url_formulario'], ENT_QUOTES, 'UTF-8') ?>"
                           style="display:inline-block;background:#3b82f6;color:#fff;text-decoration:none;padding:16px 40px;border-radius:8px;font-size:16px;font-weight:600;letter-spacing:.3px;">
                            Completar Formulario
                        </a>
                    </td>
                </tr>
            </table>
            <p style="font-size:13px;color:#94a3b8;line-height:1.6;margin:0 0 8px;">
                Si el botón no funciona, copia y pega este enlace en tu navegador:
            </p>
            <p style="font-size:12px;color:#3b82f6;word-break:break-all;margin:0;">
                <?= htmlspecialchars($data['url_formulario'], ENT_QUOTES, 'UTF-8') ?>
            </p>
        </td>
    </tr>
    <tr>
        <td style="padding:20px 30px;background:#f8fafc;border-top:1px solid #e2e8f0;text-align:center;">
            <p style="font-size:12px;color:#94a3b8;margin:0;">
                Este es un correo automático, por favor no respondas a este mensaje.
            </p>
        </td>
    </tr>
</table>
</body>
</html>
