<?php
/** @var array $empresas */
/** @var string $nombre */
$base = BASE_URL;
ob_start();
?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-building"></i> Seleccionar Empresa</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Hola <?= htmlspecialchars($nombre) ?>, elige la empresa con la que trabajarás:</p>
                <div class="list-group list-group-flush">
                    <?php foreach ($empresas as $emp): ?>
                    <form method="POST" action="<?= $base ?>/empresa/setEmpresa">
                        <input type="hidden" name="id_usuario" value="<?= (int) ($_SESSION['id_usuario'] ?? 0) ?>">
                        <input type="hidden" name="id_empresa" value="<?= (int) $emp['id_empresa'] ?>">
                        <input type="hidden" name="ruc_empresa" value="<?= htmlspecialchars($emp['ruc'] ?? '') ?>">
                        <button type="submit" class="list-group-item list-group-item-action border-0">
                            <?= htmlspecialchars($emp['nombre_comercial'] ?? $emp['ruc'] ?? '') ?>
                        </button>
                    </form>
                    <?php endforeach; ?>
                </div>
                <hr>
                <a href="<?= rtrim(BASE_URL ?? '', '/') ?>/auth/logout" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Cerrar sesión
                </a>
            </div>
        </div>
    </div>
</div>
<?php
$contenido = ob_get_clean();
$titulo = 'Seleccionar Empresa';
require MVC_APP . '/views/layouts/guest.php';
?>
