<?php
/** @var array $usuario */
/** @var string|null $error */
/** @var string|null $exito */
$base = BASE_URL ?? '/sistema/public';
$error = $error ?? null;
$exito = $exito ?? null;
$usuario = $usuario ?? [];
$nivelTexto = match ((int)($usuario['nivel'] ?? 1)) {
    3 => 'Super administrador',
    2 => 'Administrador',
    default => 'Usuario',
};
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-person-circle"></i> Mi perfil</h5>
        <p class="text-muted mb-0 small">Actualiza tu información personal (excepto el nivel).</p>
    </div>
    <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
</div>

<?php if ($exito): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle"></i> <?= htmlspecialchars($exito) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card shadow-sm" style="max-width: 520px;">
    <div class="card-body">
        <form method="POST" action="<?= $base ?>/perfil/guardar">
            <div class="mb-3">
                <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                <input type="text" id="nombre" name="nombre" class="form-control" required
                       value="<?= htmlspecialchars($usuario['nombre'] ?? '') ?>" placeholder="Tu nombre completo">
            </div>
            <div class="mb-3">
                <label for="cedula" class="form-label">Cédula <span class="text-danger">*</span></label>
                <input type="text" id="cedula" name="cedula" class="form-control" required
                       value="<?= htmlspecialchars($usuario['cedula'] ?? '') ?>" placeholder="Número de cédula">
            </div>
            <div class="mb-3">
                <label for="mail" class="form-label">Correo electrónico</label>
                <input type="email" id="mail" name="mail" class="form-control"
                       value="<?= htmlspecialchars($usuario['mail'] ?? '') ?>" placeholder="correo@ejemplo.com">
            </div>
            <div class="mb-3">
                <label class="form-label text-muted">Nivel</label>
                <input type="text" class="form-control bg-light" readonly
                       value="<?= htmlspecialchars($nivelTexto) ?>">
                <small class="text-muted">El nivel no puede ser modificado desde aquí.</small>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> Guardar cambios
                </button>
                <a href="<?= $base ?>/auth/cambiar-clave" class="btn btn-outline-warning">
                    <i class="bi bi-key"></i> Cambiar contraseña
                </a>
            </div>
        </form>
    </div>
</div>
