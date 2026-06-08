<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estado de tu Solicitud</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); min-height: 100dvh; font-family: 'Segoe UI', sans-serif; }
    </style>
</head>
<body>
<?php
$items  = $solicitud['items'] ?? [];
$estado = $solicitud['estado'] ?? 'pendiente';

$estadoInfo = [
    'pendiente' => ['color' => 'warning', 'icon' => 'bi-hourglass-split',  'label' => 'Pendiente de revisión'],
    'aprobada'  => ['color' => 'info',    'icon' => 'bi-check-circle',      'label' => 'Aprobada'],
    'rechazada' => ['color' => 'danger',  'icon' => 'bi-x-circle',          'label' => 'Rechazada'],
    'facturada' => ['color' => 'success', 'icon' => 'bi-receipt-cutoff',    'label' => 'Facturada'],
];
$info = $estadoInfo[$estado] ?? $estadoInfo['pendiente'];
$fecha = !empty($solicitud['created_at']) ? date('d-m-Y H:i', strtotime($solicitud['created_at'])) : '-';
?>
<div class="container py-4" style="max-width: 620px;">
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-<?= $info['color'] ?> bg-opacity-10 border-bottom py-3 text-center">
            <i class="bi <?= $info['icon'] ?> text-<?= $info['color'] ?> fs-2 d-block mb-1"></i>
            <h5 class="mb-0 fw-bold text-<?= $info['color'] ?>"><?= $info['label'] ?></h5>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3 mb-3" style="font-size:.85rem">
                <div class="col-6">
                    <div class="text-muted small">Cliente</div>
                    <div class="fw-medium"><?= htmlspecialchars($solicitud['nombre_cliente'] ?? '') ?></div>
                </div>
                <div class="col-6">
                    <div class="text-muted small">Identificación</div>
                    <div><?= htmlspecialchars($solicitud['identificacion'] ?? '') ?></div>
                </div>
                <?php if (!empty($solicitud['correo_cliente'])): ?>
                <div class="col-6">
                    <div class="text-muted small">Correo</div>
                    <div><?= htmlspecialchars($solicitud['correo_cliente']) ?></div>
                </div>
                <?php endif; ?>
                <div class="col-6">
                    <div class="text-muted small">Fecha de solicitud</div>
                    <div><?= $fecha ?></div>
                </div>
                <div class="col-6">
                    <div class="text-muted small">Monto total</div>
                    <div class="fw-bold text-success">$<?= number_format((float)($solicitud['monto_total'] ?? 0), 2) ?></div>
                </div>
            </div>

            <?php if (!empty($items)): ?>
            <div class="mb-3">
                <div class="small text-muted fw-medium mb-1">Ítems solicitados</div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0" style="font-size:.82rem">
                        <thead class="table-light">
                            <tr>
                                <th>Descripción</th>
                                <th class="text-center">Cant.</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $it): ?>
                            <tr>
                                <td><?= htmlspecialchars($it['descripcion'] ?? '') ?></td>
                                <td class="text-center"><?= number_format((float)($it['cantidad'] ?? 1), 2) ?></td>
                                <td class="text-end">$<?= number_format((float)($it['precio_unitario'] ?? 0) * (float)($it['cantidad'] ?? 1), 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($estado === 'rechazada' && !empty($solicitud['nota_aprobacion'])): ?>
            <div class="alert alert-danger border-0 rounded-3 py-2 mb-0" style="font-size:.85rem">
                <i class="bi bi-info-circle me-1"></i>
                <strong>Motivo:</strong> <?= htmlspecialchars($solicitud['nota_aprobacion']) ?>
            </div>
            <?php elseif ($estado === 'facturada'): ?>
            <div class="alert alert-success border-0 rounded-3 py-2 mb-0" style="font-size:.85rem">
                <i class="bi bi-check-circle me-1"></i>
                Tu factura ha sido emitida. La recibirás por correo electrónico.
            </div>
            <?php elseif ($estado === 'pendiente'): ?>
            <div class="alert alert-warning border-0 rounded-3 py-2 mb-0" style="font-size:.85rem">
                <i class="bi bi-hourglass-split me-1"></i>
                Tu solicitud está siendo revisada. Te notificaremos por correo cuando esté lista.
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
