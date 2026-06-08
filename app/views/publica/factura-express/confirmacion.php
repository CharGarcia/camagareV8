<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud Enviada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); min-height: 100dvh; font-family: 'Segoe UI', sans-serif; }
        .check-circle { width: 80px; height: 80px; background: #22c55e; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; }
    </style>
</head>
<body>
<?php
$plantilla    = $resultado['plantilla'] ?? [];
$solicitud    = $resultado['data'] ?? $resultado['solicitud'] ?? [];
$solicitud['token_cliente'] = $resultado['token_cliente'] ?? ($solicitud['token_cliente'] ?? '');
$solicitud['monto_total']   = $resultado['monto_total'] ?? ($solicitud['monto_total'] ?? 0);
$solicitud['estado']        = $solicitud['estado'] ?? 'pendiente';
$items        = json_decode($solicitud['items_json'] ?? '[]', true) ?: [];
$mensaje      = $plantilla['mensaje_gracias'] ?? '¡Gracias por tu solicitud! La hemos recibido correctamente.';
$tokenCliente = $solicitud['token_cliente'] ?? '';
$base         = rtrim(BASE_URL, '/');
$urlEstado    = $tokenCliente ? $base . '/factura-express/' . $tokenCliente . '/estado' : '';
?>
<div class="container py-5" style="max-width: 600px;">
    <div class="card border-0 shadow-sm rounded-4 text-center p-4 p-md-5">
        <div class="check-circle">
            <i class="bi bi-check-lg text-white fs-2"></i>
        </div>
        <h3 class="fw-bold mb-2 text-success">¡Solicitud enviada!</h3>
        <p class="text-muted mb-4"><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></p>

        <?php if (!empty($solicitud['nombre_cliente'])): ?>
        <div class="p-3 bg-light rounded-3 text-start mb-4">
            <div class="row g-2" style="font-size:.85rem">
                <div class="col-6">
                    <div class="text-muted small">Cliente</div>
                    <div class="fw-medium"><?= htmlspecialchars($solicitud['nombre_cliente']) ?></div>
                </div>
                <div class="col-6">
                    <div class="text-muted small">Identificación</div>
                    <div><?= htmlspecialchars($solicitud['identificacion'] ?? '') ?></div>
                </div>
                <?php if (!empty($solicitud['correo_cliente'])): ?>
                <div class="col-12">
                    <div class="text-muted small">Correo</div>
                    <div><?= htmlspecialchars($solicitud['correo_cliente']) ?></div>
                </div>
                <?php endif; ?>
                <div class="col-6">
                    <div class="text-muted small">Monto total</div>
                    <div class="fw-bold text-success fs-5">$<?= number_format((float)($solicitud['monto_total'] ?? 0), 2) ?></div>
                </div>
                <div class="col-6">
                    <div class="text-muted small">Estado</div>
                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 fw-medium">
                        <?= $solicitud['estado'] === 'facturada' ? 'Facturada' : 'Pendiente de revisión' ?>
                    </span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($urlEstado): ?>
        <div class="alert alert-info border-0 rounded-3 py-2 mb-4" style="font-size:.85rem">
            <i class="bi bi-info-circle me-1"></i>
            Puedes consultar el estado de tu solicitud en cualquier momento:
            <div class="mt-1">
                <a href="<?= htmlspecialchars($urlEstado, ENT_QUOTES) ?>" class="fw-medium text-break" target="_blank">
                    <?= htmlspecialchars($urlEstado) ?>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <p class="text-muted small mb-0">
            <i class="bi bi-clock me-1"></i>Recibirás una confirmación por correo cuando tu factura esté lista.
        </p>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
