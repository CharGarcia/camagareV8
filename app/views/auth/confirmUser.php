<?php
/** @var string $email */
/** @var string $token */
/** @var string|null $error */
/** @var bool $exito */
$base = BASE_URL ?? '/sistema/public';
$error = $error ?? null;
$exito = $exito ?? false;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $exito ? 'Contraseña actualizada' : 'Restablecer contraseña' ?> | CaMaGaRe ERP</title>
    <link rel="shortcut icon" type="image/png" href="<?= rtrim(BASE_URL ?? '', '/') ?>/image/logofinal.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100 bg-light">
    <div class="card shadow-sm" style="width: 100%; max-width: 400px;">
        <div class="card-header bg-primary text-white text-center py-3">
            <h4 class="mb-0 fw-bold">CaMaGaRe ERP</h4>
            <small class="opacity-75"><?= $exito ? 'Contraseña actualizada' : 'Restablecer contraseña' ?></small>
        </div>
        <div class="card-body">
            <?php if ($exito): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> Tu contraseña se ha actualizado correctamente.
                </div>
                <a href="<?= $base ?>/auth/index" class="btn btn-primary w-100">
                    <i class="bi bi-box-arrow-in-right"></i> Iniciar sesión
                </a>
            <?php elseif ($error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                </div>
                <a href="<?= $base ?>/auth/index" class="btn btn-outline-primary w-100">
                    <i class="bi bi-arrow-left"></i> Volver al inicio
                </a>
            <?php else: ?>
                <p class="text-muted small">Ingresa tu nueva contraseña (mínimo 4 caracteres).</p>
                <form method="POST" action="">
                    <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <div class="mb-3">
                        <label for="password" class="form-label">Nueva contraseña <span class="text-danger">*</span></label>
                        <input type="password" id="password" name="password" class="form-control" required minlength="4" placeholder="Mínimo 4 caracteres" autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="confirmar_password" class="form-label">Confirmar contraseña <span class="text-danger">*</span></label>
                        <input type="password" id="confirmar_password" name="confirmar_password" class="form-control" required minlength="4" placeholder="Repite la contraseña">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-key"></i> Cambiar contraseña
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
