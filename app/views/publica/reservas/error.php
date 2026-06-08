<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal de Reservas — Error</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f1f5f9; min-height: 100dvh; display: flex; align-items: center; justify-content: center; }
    </style>
</head>
<body>
<div class="text-center px-3">
    <div class="mb-4">
        <i class="bi bi-calendar-x text-danger" style="font-size:4rem;"></i>
    </div>
    <h4 class="fw-bold text-dark">Portal no disponible</h4>
    <p class="text-muted"><?= htmlspecialchars($mensaje ?? 'El portal de reservas no existe o no está activo.', ENT_QUOTES) ?></p>
    <p class="text-muted small">Si crees que esto es un error, contacta con la empresa directamente.</p>
</div>
</body>
</html>
