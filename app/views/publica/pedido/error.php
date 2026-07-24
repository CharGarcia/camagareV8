<?php
/** @var string $mensaje */
$base = rtrim(BASE_URL ?? '', '/');
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>No disponible</title>
    <link rel="shortcut icon" type="image/png" href="<?= $base ?>/image/logofinal.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body style="background:#f4f6f9;">
    <div class="d-flex align-items-center justify-content-center" style="min-height:100vh;">
        <div class="text-center px-4">
            <i class="bi bi-qr-code-scan text-muted" style="font-size:3rem;"></i>
            <h5 class="mt-3"><?= htmlspecialchars($mensaje) ?></h5>
            <p class="text-muted small">Si crees que es un error, avísale a alguien del restaurante.</p>
        </div>
    </div>
</body>
</html>
