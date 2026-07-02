<?php
/**
 * Vista de error 404 — Página no encontrada.
 * La carga App\core\Application::handleError() cuando no existe el controlador o la acción.
 *
 * @var int|null    $code    Código HTTP (opcional; por defecto 404).
 * @var string|null $message Mensaje técnico interno (no se muestra al usuario).
 */
$base = rtrim(defined('BASE_URL') ? (BASE_URL ?? '') : '', '/');
$inicioUrl = $base === '' ? '/' : $base . '/';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Página no encontrada | CaMaGaRe ERP</title>
    <link rel="shortcut icon" type="image/png" href="<?= $base ?>/image/logofinal.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <?php require __DIR__ . '/../partials/theme-vars.php'; ?>
    <link href="<?= $base ?>/css/app.css" rel="stylesheet">
    <link href="<?= $base ?>/css/theme.css" rel="stylesheet">
    <style>
        .error-404-wrap { min-height: 100vh; }
        .error-404-code {
            font-size: clamp(6rem, 22vw, 11rem);
            font-weight: 800;
            line-height: 1;
            letter-spacing: -0.03em;
            color: var(--bs-primary, #0d6efd);
        }
        .error-404-code .bi {
            font-size: clamp(4.5rem, 16vw, 8rem);
            vertical-align: -0.06em;
        }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center error-404-wrap">
    <div class="card shadow-sm border-0 text-center" style="width: 100%; max-width: 520px;">
        <div class="card-body p-4 p-md-5">
            <div class="error-404-code mb-2">
                4<i class="bi bi-emoji-frown"></i>4
            </div>
            <h1 class="h4 fw-bold mb-2">Página no encontrada</h1>
            <p class="text-muted mb-4">
                Lo sentimos, la página que buscas no existe, fue movida o el enlace es incorrecto.
            </p>
            <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center">
                <a href="<?= htmlspecialchars($inicioUrl) ?>" class="btn btn-primary px-4">
                    <i class="bi bi-house-door me-1"></i> Ir al inicio
                </a>
                <button type="button" class="btn btn-outline-secondary px-4" onclick="history.back()">
                    <i class="bi bi-arrow-left me-1"></i> Volver
                </button>
            </div>
        </div>
        <div class="card-footer bg-transparent border-0 pb-4">
            <small class="text-muted">CaMaGaRe ERP</small>
        </div>
    </div>
</body>
</html>
