<?php
/**
 * Aviso standalone cuando se pide la pantalla de venta sin turno de caja
 * abierto para el punto de emisión (link directo, caja cerrada en otra
 * pestaña, etc.). El mostrador real vive en modulos/caja_sesion/venta.php.
 *
 * @var string $titulo
 * @var string $rutaModulo
 * @var int    $idPuntoEmision
 */
$base = rtrim(BASE_URL ?? '', '/');
$rutaAjax = $base . '/' . $rutaModulo;
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($titulo) ?> | CaMaGaRe</title>
    <link rel="shortcut icon" type="image/png" href="<?= $base ?>/image/logofinal.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        html, body { height: 100%; }
        body {
            background: #f4f6f9;
            display: flex; align-items: center; justify-content: center;
            padding: 24px;
        }
        .vp-wrap { width: 100%; max-width: 440px; text-align: center; }
        .vp-icon { font-size: 2.6rem; color: #dc3545; margin-bottom: 10px; }
        .vp-card { border: none; border-radius: 14px; box-shadow: 0 10px 30px -12px rgba(20,26,36,.25); }
        .vp-card .card-body { padding: 34px 28px; }
    </style>
</head>
<body>
<div class="vp-wrap">
    <div class="card vp-card">
        <div class="card-body">
            <div class="vp-icon"><i class="bi bi-lock-fill"></i></div>
            <h5 class="fw-semibold mb-1">No hay caja abierta</h5>
            <p class="text-muted small mb-4">
                No se encontró un turno abierto para este punto de emisión.
                Vuelve a la pantalla anterior y ábrelo antes de vender.
            </p>
            <a href="<?= $rutaAjax ?>" class="btn btn-primary w-100">
                <i class="bi bi-arrow-left me-1"></i>Volver a caja
            </a>
        </div>
    </div>
</div>
</body>
</html>
