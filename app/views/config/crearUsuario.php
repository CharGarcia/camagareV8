<?php
/** @var string $titulo */
/** @var int $nivel */
/** @var array|null $msg */
$base = BASE_URL;
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-person-plus"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">Cree un nuevo usuario. Luego asígnele empresas en "Asignar empresas".</p>
    </div>
    <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= htmlspecialchars($msg[0]) ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($msg[1]) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= $base ?>/config/crear-usuario">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nombre <span class="text-danger">*</span></label>
                    <input type="text" name="nombre" class="form-control" required placeholder="Nombre completo" value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Cédula <span class="text-danger">*</span></label>
                    <input type="text" name="cedula" class="form-control" required placeholder="Número de cédula" value="<?= htmlspecialchars($_POST['cedula'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Contraseña <span class="text-danger">*</span></label>
                    <input type="password" name="password" class="form-control" required minlength="4" placeholder="Mínimo 4 caracteres">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Confirmar contraseña <span class="text-danger">*</span></label>
                    <input type="password" name="password2" class="form-control" required minlength="4" placeholder="Repita la contraseña">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Nivel</label>
                    <select name="nivel" class="form-select">
                        <option value="1" <?= ($_POST['nivel'] ?? '1') === '1' ? 'selected' : '' ?>>Usuario</option>
                        <option value="2" <?= ($_POST['nivel'] ?? '') === '2' ? 'selected' : '' ?> <?= $nivel < 3 ? 'disabled' : '' ?>>Administrador</option>
                        <option value="3" <?= ($_POST['nivel'] ?? '') === '3' ? 'selected' : '' ?> <?= $nivel < 3 ? 'disabled' : '' ?>>Super administrador</option>
                    </select>
                    <?php if ($nivel < 3): ?>
                    <small class="text-muted">Solo el super admin puede crear administradores.</small>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Correo (opcional)</label>
                    <input type="email" name="mail" class="form-control" placeholder="correo@ejemplo.com" value="<?= htmlspecialchars($_POST['mail'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Crear usuario</button>
                    <a href="<?= $base ?>/config/permisos-modulos" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </div>
        </form>
    </div>
</div>
