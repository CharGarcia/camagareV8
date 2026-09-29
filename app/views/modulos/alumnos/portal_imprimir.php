<?php
/**
 * Hoja imprimible con el QR del portal de representantes. Standalone.
 * $url (absoluta), $activo, $empresa.
 */
$e = static fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$qr = 'https://api.qrserver.com/v1/create-qr-code/?data=' . rawurlencode($url) . '&size=600x600&margin=10';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>QR · Portal de representantes</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #222; margin: 0; padding: 30px; text-align: center; }
        h1 { font-size: 28px; margin: 0 0 6px; }
        h2 { font-size: 18px; font-weight: normal; color: #555; margin: 0 0 24px; }
        img { width: 340px; height: 340px; border: 1px solid #ddd; border-radius: 12px; padding: 10px; }
        ol { text-align: left; display: inline-block; font-size: 17px; line-height: 1.8; margin: 24px auto; }
        .url { font-size: 12px; color: #777; word-break: break-all; }
        .aviso { background: #fff3cd; border: 1px solid #ffe69c; padding: 10px; border-radius: 6px; margin-bottom: 16px; }
        @media print { .no-print { display: none; } body { padding: 10px; } }
    </style>
</head>
<body>
    <?php if (!$activo): ?><div class="aviso no-print">El portal está desactivado: el QR no funcionará hasta activarlo.</div><?php endif; ?>
    <h1>Actualice los datos de sus hijos</h1>
    <h2><?= $e($empresa) ?></h2>
    <img src="<?= $e($qr) ?>" alt="QR">
    <br>
    <ol>
        <li>Escanee el código con la cámara de su celular.</li>
        <li>Escriba su cédula o RUC (del representante a quien se factura).</li>
        <li>Ingrese el código que le llegará a su correo.</li>
        <li>Revise y actualice los datos, o registre a su hijo o hija.</li>
    </ol>
    <p class="url"><?= $e($url) ?></p>
    <p class="no-print"><button onclick="window.print()" style="padding:10px 22px;font-size:15px;">Imprimir</button></p>
    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 400));</script>
</body>
</html>
