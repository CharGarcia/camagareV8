<?php

/**
 * Enlace de invitación no válido. La ve gente ajena al sistema, así que el
 * mensaje evita jerga interna y dice qué puede hacer.
 *
 * @var string $tituloError
 * @var string $detalle
 */

$base = rtrim(BASE_URL ?? '', '/');
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Enlace no disponible</title>
    <link rel="shortcut icon" type="image/png" href="<?= $base ?>/image/logofinal.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #12151a; color: #e9ecef; min-height: 100vh;
               display: flex; align-items: center; justify-content: center; padding: 2rem; }
        .caja { max-width: 480px; text-align: center; }
    </style>
</head>
<body>
    <div class="caja">
        <i class="bi bi-camera-video-off text-secondary" style="font-size: 3.5rem;"></i>
        <h4 class="fw-bold mt-3"><?= htmlspecialchars($tituloError) ?></h4>
        <p class="text-secondary"><?= htmlspecialchars($detalle) ?></p>
    </div>
</body>
</html>
