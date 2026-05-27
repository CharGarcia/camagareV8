<?php
/**
 * Vista pública: resultado de pago Payphone
 * Variables: $estado, $transaccion, $resultado
 */
$estado      = $estado ?? 'error';
$transaccion = $transaccion ?? null;
$monto       = $transaccion ? \App\Services\PayphoneService::centavosADolares((int)$transaccion['monto']) : 0;
$descripcion = $transaccion['descripcion'] ?? '';

$cfgs = [
    'aprobado'  => ['icon' => 'bi-check-circle-fill', 'color' => '#198754', 'bg' => '#d1e7dd', 'titulo' => 'Pago aprobado',   'texto' => 'Tu pago fue procesado correctamente.'],
    'cancelado' => ['icon' => 'bi-x-circle-fill',     'color' => '#6c757d', 'bg' => '#e2e3e5', 'titulo' => 'Pago cancelado',  'texto' => 'Cancelaste el proceso de pago.'],
    'rechazado' => ['icon' => 'bi-exclamation-circle-fill', 'color' => '#dc3545', 'bg' => '#f8d7da', 'titulo' => 'Pago rechazado', 'texto' => 'Tu pago fue rechazado. Intenta con otro método.'],
    'error'     => ['icon' => 'bi-exclamation-triangle-fill', 'color' => '#dc3545', 'bg' => '#f8d7da', 'titulo' => 'Error en el pago', 'texto' => 'Hubo un problema al procesar el pago.'],
    'pendiente' => ['icon' => 'bi-hourglass-split',   'color' => '#ffc107', 'bg' => '#fff3cd', 'titulo' => 'Pago en proceso', 'texto' => 'Tu pago está siendo procesado. Recibirás confirmación pronto.'],
];
$cfg = $cfgs[$estado] ?? $cfgs['error'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($cfg['titulo']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f1f5f9; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', sans-serif; }
        .result-card { max-width: 460px; width: 100%; margin: 2rem auto; }
        .result-icon { font-size: 3.5rem; }
    </style>
</head>
<body>
<div class="container result-card">
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <!-- Cabecera de color -->
        <div class="py-4 text-center" style="background:<?= $cfg['bg'] ?>;">
            <i class="bi <?= $cfg['icon'] ?> result-icon" style="color:<?= $cfg['color'] ?>;"></i>
            <h5 class="fw-bold mt-2 mb-0" style="color:<?= $cfg['color'] ?>;"><?= htmlspecialchars($cfg['titulo']) ?></h5>
        </div>

        <div class="card-body p-4">
            <p class="text-center text-muted mb-4"><?= htmlspecialchars($cfg['texto']) ?></p>

            <?php if ($transaccion): ?>
            <dl class="row small mb-0">
                <?php if ($descripcion): ?>
                <dt class="col-5 text-muted">Descripción</dt>
                <dd class="col-7"><?= htmlspecialchars($descripcion) ?></dd>
                <?php endif; ?>

                <dt class="col-5 text-muted">Monto</dt>
                <dd class="col-7 fw-semibold">$<?= number_format($monto, 2) ?> <?= htmlspecialchars($transaccion['moneda'] ?? 'USD') ?></dd>

                <?php if (!empty($transaccion['authorization_code'])): ?>
                <dt class="col-5 text-muted">Autorización</dt>
                <dd class="col-7 font-monospace"><?= htmlspecialchars($transaccion['authorization_code']) ?></dd>
                <?php endif; ?>

                <dt class="col-5 text-muted">Fecha</dt>
                <dd class="col-7"><?= date('d-m-Y H:i:s', strtotime($transaccion['updated_at'] ?? $transaccion['created_at'])) ?></dd>

                <dt class="col-5 text-muted">Referencia</dt>
                <dd class="col-7 font-monospace small text-truncate" title="<?= htmlspecialchars($transaccion['client_transaction_id']) ?>">
                    <?= htmlspecialchars(substr($transaccion['client_transaction_id'], 0, 30)) ?>…
                </dd>
            </dl>
            <?php endif; ?>

            <?php if ($estado === 'error' && !empty($resultado['mensaje'])): ?>
            <div class="alert alert-danger py-2 small mt-3 mb-0">
                <?= htmlspecialchars($resultado['mensaje']) ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="card-footer bg-white border-0 text-center pb-4">
            <?php if ($estado === 'aprobado' && !empty($transaccion['url_exito'])): ?>
            <a href="<?= htmlspecialchars($transaccion['url_exito']) ?>" class="btn btn-success btn-sm px-4">
                <i class="bi bi-arrow-right-circle me-1"></i>Continuar
            </a>
            <?php elseif (in_array($estado, ['cancelado', 'rechazado', 'error'])): ?>
            <button onclick="history.back()" class="btn btn-outline-secondary btn-sm px-4">
                <i class="bi bi-arrow-left me-1"></i>Volver e intentar de nuevo
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
