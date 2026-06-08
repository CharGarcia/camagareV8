<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enlace no disponible</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg,#f8fafc 0%,#e2e8f0 100%); min-height:100dvh; display:flex; align-items:center; justify-content:center; font-family:'Segoe UI',sans-serif; }
        .card-err { max-width:440px; width:100%; border-radius:1.5rem; overflow:hidden; box-shadow:0 10px 25px rgba(0,0,0,.08); }
        .icon-circle { width:72px; height:72px; border-radius:50%; background:#fee2e2; display:flex; align-items:center; justify-content:center; margin:0 auto 1rem; }
    </style>
</head>
<body>
<div class="card-err card border-0 mx-3">
    <div style="background:linear-gradient(135deg,#1e293b 0%,#334155 100%);padding:2rem;text-align:center;color:#fff;">
        <h5 class="mb-0 fw-bold">Firma Electrónica</h5>
    </div>
    <div class="card-body text-center p-4">
        <div class="icon-circle">
            <i class="bi bi-x-circle-fill text-danger fs-2"></i>
        </div>
        <h5 class="fw-bold text-danger mb-2">Enlace no disponible</h5>
        <p class="text-muted"><?= htmlspecialchars($mensaje ?? 'El enlace no es válido o ha expirado.', ENT_QUOTES) ?></p>
        <hr>
        <p class="small text-muted mb-0">Si necesita un nuevo enlace, comuníquese con la empresa.</p>
    </div>
</div>
</body>
</html>
