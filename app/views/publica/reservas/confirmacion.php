<?php
$color   = $portalConfig['color_primario'] ?? '#0d6efd';
$empresa = htmlspecialchars($portalConfig['nombre_empresa'] ?? '', ENT_QUOTES);
$titulo  = htmlspecialchars($portalConfig['titulo']         ?? 'Portal de Reservas', ENT_QUOTES);

$fechaIni     = '';
$nombreTipo   = '';
$nombreRec    = '';
$estadoCita   = '';
$requiereConf = (bool) ($portalConfig['requiere_confirmacion'] ?? false);

if ($cita) {
    $fechaIni   = $cita['fecha_inicio'] ? date('d/m/Y H:i', strtotime($cita['fecha_inicio'])) : '';
    $nombreTipo = $cita['nombre_tipo'] ?? '';
    $nombreRec  = $cita['nombre_recurso'] ?? '';
    $estadoCita = $cita['estado'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reserva Confirmada — <?= $empresa ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f1f5f9; min-height: 100vh; font-family: 'Segoe UI', sans-serif; }
        .portal-header { background: <?= $color ?>; color: #fff; padding: 2rem 1.5rem 1.5rem; text-align: center; }
        .check-circle { width: 80px; height: 80px; border-radius: 50%; background: rgba(255,255,255,.2);
                        display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1rem; }
    </style>
</head>
<body>

<div class="portal-header">
    <div class="check-circle">
        <i class="bi bi-check-lg" style="font-size:2.5rem;"></i>
    </div>
    <h4 class="fw-bold mb-1">¡Reserva registrada!</h4>
    <p class="mb-0 opacity-75 small"><?= $empresa ?></p>
</div>

<div class="container py-4" style="max-width:500px;">

    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-body p-4">
            <?php if ($requiereConf): ?>
            <div class="alert alert-warning py-2 small mb-3">
                <i class="bi bi-hourglass-split me-1"></i>
                Tu reserva está <strong>pendiente de confirmación</strong>. La empresa se pondrá en contacto contigo.
            </div>
            <?php else: ?>
            <div class="alert alert-success py-2 small mb-3">
                <i class="bi bi-check-circle me-1"></i>
                Tu reserva ha sido <strong>confirmada automáticamente</strong>.
            </div>
            <?php endif; ?>

            <?php if ($cita): ?>
            <h6 class="fw-bold mb-3">Detalles de tu cita</h6>
            <dl class="row mb-0 small">
                <dt class="col-5 text-muted">Fecha y hora</dt>
                <dd class="col-7 fw-semibold"><?= $fechaIni ?></dd>

                <?php if ($nombreTipo): ?>
                <dt class="col-5 text-muted">Tipo</dt>
                <dd class="col-7"><?= htmlspecialchars($nombreTipo, ENT_QUOTES) ?></dd>
                <?php endif; ?>

                <?php if ($nombreRec): ?>
                <dt class="col-5 text-muted">Con</dt>
                <dd class="col-7"><?= htmlspecialchars($nombreRec, ENT_QUOTES) ?></dd>
                <?php endif; ?>

                <dt class="col-5 text-muted">Estado</dt>
                <dd class="col-7">
                    <?php if ($estadoCita === 'confirmada'): ?>
                        <span class="badge bg-success">Confirmada</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark">Pendiente</span>
                    <?php endif; ?>
                </dd>

                <dt class="col-5 text-muted">Nº de reserva</dt>
                <dd class="col-7 font-monospace">#<?= $cita['id'] ?? '—' ?></dd>
            </dl>
            <?php endif; ?>
        </div>
    </div>

    <div class="text-center">
        <a href="<?= htmlspecialchars(BASE_URL . '/reservas/' . $slug, ENT_QUOTES) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Nueva reserva
        </a>
    </div>
</div>

</body>
</html>
