<?php
/** @var string $titulo */
/** @var bool $sinEmpresaSuperAdmin */
$sinEmpresaSuperAdmin = $sinEmpresaSuperAdmin ?? false;
$base = rtrim(BASE_URL ?? '', '/');
?>
<?php if ($sinEmpresaSuperAdmin): ?>
<div class="container py-3">
    <div class="alert alert-info mb-0" role="alert">
        <strong>Sin empresa asignada.</strong> Cree la primera empresa en
        <a href="<?= htmlspecialchars($base) ?>/config/empresas-sistema" class="alert-link">Configuración → Empresas del sistema</a>
        y luego asígnela a su usuario si hace falta. Los módulos del menú aparecerán cuando tenga una empresa activa en la barra superior.
    </div>
</div>
<?php endif; ?>
<div class="d-flex flex-column align-items-center justify-content-center py-5" style="min-height: 60vh;">
    <img src="<?= $base ?>/image/logofinal.png" alt="CaMaGaRe" class="img-fluid mb-3" style="max-height: 200px;">
    <h4 class="fw-bold text-primary mb-0">CaMaGaRe</h4>
</div>
