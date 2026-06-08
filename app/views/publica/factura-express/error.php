<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enlace no disponible</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); min-height: 100dvh; font-family: 'Segoe UI', sans-serif; }
    </style>
</head>
<body>
<div class="container py-5" style="max-width: 500px;">
    <div class="card border-0 shadow-sm rounded-4 text-center p-5">
        <div class="mb-3">
            <i class="bi bi-qr-code text-secondary opacity-25" style="font-size:4rem"></i>
        </div>
        <h4 class="fw-bold text-danger mb-2">Enlace no disponible</h4>
        <p class="text-muted mb-0"><?= htmlspecialchars($mensaje ?? 'Este enlace no está disponible o ha sido desactivado.', ENT_QUOTES) ?></p>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
