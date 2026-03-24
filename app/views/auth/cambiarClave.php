<?php
/** @var string|null $error */
/** @var bool $exito */
$base = BASE_URL ?? '/sistema/public';
$error = $error ?? null;
$exito = $exito ?? false;
$titulo = 'Cambiar contraseña';
?>
<div class="d-flex justify-content-center">
    <div class="card shadow-sm" style="width: 100%; max-width: 420px;">
        <div class="card-header bg-warning text-dark text-center py-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-key"></i> Cambiar contraseña</h5>
            <small class="opacity-75">Actualiza tu contraseña de acceso</small>
        </div>
        <div class="card-body">
            <?php if ($exito): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> La contraseña se actualizó correctamente.
                </div>
                <a href="<?= $base ?>/home/index" class="btn btn-primary w-100">
                    <i class="bi bi-house"></i> Ir al inicio
                </a>
            <?php else: ?>
                <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>
                <p class="text-muted small">Ingresa tu contraseña actual y la nueva contraseña (mínimo 4 caracteres).</p>
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="clave_actual" class="form-label">Contraseña actual <span class="text-danger">*</span></label>
                        <input type="password" id="clave_actual" name="clave_actual" class="form-control" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="nueva_clave" class="form-label">Nueva contraseña <span class="text-danger">*</span></label>
                        <input type="password" id="nueva_clave" name="nueva_clave" class="form-control" required minlength="4" placeholder="Mínimo 4 caracteres">
                    </div>
                    <div class="mb-3">
                        <label for="repetir_clave" class="form-label">Repetir contraseña <span class="text-danger">*</span></label>
                        <input type="password" id="repetir_clave" name="repetir_clave" class="form-control" required minlength="4">
                    </div>
                    <button type="submit" class="btn btn-warning w-100">
                        <i class="bi bi-key"></i> Actualizar contraseña
                    </button>
                </form>
                <div class="mt-3 text-center">
                    <a href="<?= $base ?>/config" class="text-decoration-none small">
                        <i class="bi bi-arrow-left"></i> Volver al inicio
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
