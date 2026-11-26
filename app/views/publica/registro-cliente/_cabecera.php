<?php
/**
 * Cabecera común de las páginas públicas del QR de registro de clientes.
 * @var array|null $cfg
 * @var string     $tituloPagina
 */
$empresaNombre = trim((string) (($cfg['empresa_nombre_comercial'] ?? '') ?: ($cfg['empresa_nombre'] ?? '')));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($tituloPagina, ENT_QUOTES) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); min-height: 100dvh; font-family: 'Segoe UI', sans-serif; }
        .page-header { background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%); color: #fff; padding: 1.75rem 1.25rem 1.5rem; }
        .form-label { font-size: .82rem; font-weight: 500; color: #374151; margin-bottom: .25rem; }
        .form-control, .form-select { font-size: .95rem; }
        .card { border-radius: 1rem; }
    </style>
</head>
<body>
<div class="page-header text-center">
    <?php if ($empresaNombre !== ''): ?>
        <div class="small text-uppercase opacity-75 fw-semibold mb-1" style="letter-spacing:.06em"><?= htmlspecialchars($empresaNombre, ENT_QUOTES) ?></div>
    <?php endif; ?>
    <h4 class="mb-0 fw-bold"><i class="bi bi-person-vcard me-2 opacity-75"></i><?= htmlspecialchars($tituloPagina, ENT_QUOTES) ?></h4>
</div>
<div class="container py-4" style="max-width: 560px;">
