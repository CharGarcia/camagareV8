<?php
/** @var string $titulo */
/** @var bool $sinEmpresaSuperAdmin */
$sinEmpresaSuperAdmin = $sinEmpresaSuperAdmin ?? false;
$base = rtrim(BASE_URL ?? '', '/');
?>

<?php if ($sinEmpresaSuperAdmin): ?>
<div class="alert alert-info border-0 shadow-sm rounded-3 mb-3 d-flex align-items-center">
    <i class="bi bi-info-circle-fill fs-4 me-3"></i>
    <div>
        <strong class="d-block mb-1">Super administrador sin empresa activa.</strong>
        Cree la primera empresa en <a href="<?= $base ?>/config/empresas-sistema" class="alert-link fw-semibold">Empresas del sistema</a>.
    </div>
</div>
<?php endif; ?>

<div class="d-flex align-items-center justify-content-center" style="min-height:60vh">
    <div class="text-center">
        <img src="<?= $base ?>/image/logofinal.png" alt="CaMaGaRe ERP" class="mb-3 d-block mx-auto" style="max-height:140px;width:auto">
        <h4 class="fw-bold mb-3">Bienvenid@<?= !empty($_SESSION['nombre']) ? ', ' . htmlspecialchars($_SESSION['nombre']) : '' ?></h4>
        <p class="mb-3" style="font-size:5rem;line-height:1.1"><span class="fw-bold text-primary">CaMaGaRe</span> <span class="text-muted">ERP</span></p>
        <p class="fst-italic text-muted mb-0">
            <span class="fw-bold text-dark">C</span>ontrol <span class="fw-bold text-dark">A</span>dministrativo &nbsp;--&nbsp;
            <span class="fw-bold text-dark">M</span>anejo <span class="fw-bold text-dark">A</span>utomatizado &nbsp;--&nbsp;
            <span class="fw-bold text-dark">G</span>estión <span class="fw-bold text-dark">A</span>nalítica &nbsp;--&nbsp;
            <span class="fw-bold text-dark">R</span>esultados <span class="fw-bold text-dark">E</span>mpresariales
        </p>
    </div>
</div>
