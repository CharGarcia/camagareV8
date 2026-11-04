<?php
/** @var string $titulo @var array $perm @var string $rutaModulo @var bool $instalado @var ?array $config
 *  @var array $filtros @var array $pestanas @var string $base */
$urlBase = rtrim($base, '/') . '/' . $rutaModulo;
$idModulo = str_replace('-', '_', basename($rutaModulo));
$stat = fn(string $id, string $icono, string $color, string $label): string =>
    '<div class="cmg-control-card__stat"><i class="bi ' . $icono . ' bg-' . $color . ' bg-opacity-10 text-' . $color . '"></i><div>'
    . '<div class="cmg-control-card__stat-value" id="' . $id . '">—</div><div class="cmg-control-card__stat-label">' . $label . '</div></div></div>';
?>
<script>document.body.classList.add('cmg-no-app-shell');</script>
<style>
    .produccion-reportes-scroll { overflow-x: auto; }
    .produccion-reportes-scroll thead th { background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; white-space: nowrap; }
    .produccion-reportes-scroll td { white-space: nowrap; }
    #form-filtros-prep .form-select, #form-filtros-prep .form-control, #form-filtros-prep .input-group-text, #form-filtros-prep .btn { height: 28px; font-size: .75rem; }
    @media (max-width: 767.98px) {
        #modulo-<?= $idModulo ?> .produccion-reportes-scroll { max-height: none !important; height: auto !important; overflow-y: visible !important; }
    }
</style>

<div class="container-fluid pt-0 pb-3 px-0 px-md-3" id="modulo-<?= $idModulo ?>">
    <?php if (!$instalado || !$config): ?>
        <div class="alert alert-info py-2 small"><i class="bi bi-info-circle me-1"></i>
            Producción aún no está configurado en esta empresa. Guarde primero la
            <a href="<?= rtrim($base, '/') ?>/modulos/produccion-configuracion">Configuración de Producción</a>.
        </div>
    <?php endif; ?>

    <div class="card cmg-control-card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-header bg-white border-bottom py-2 px-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-bar-chart-line me-2 text-primary"></i><?= htmlspecialchars($titulo) ?></h5>
        </div>
        <div class="card-body p-3">
            <form id="form-filtros-prep" onsubmit="event.preventDefault(); window.PREP_cargar();" class="d-flex flex-wrap align-items-start gap-2">
                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Cierre desde</label>
                    <input type="date" id="prep-desde" class="form-control form-control-sm" style="width:115px;" value="<?= htmlspecialchars($filtros['desde']) ?>">
                </div>
                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Cierre hasta</label>
                    <input type="date" id="prep-hasta" class="form-control form-control-sm" style="width:115px;" value="<?= htmlspecialchars($filtros['hasta']) ?>">
                </div>
                <div class="d-flex flex-wrap align-items-start gap-2">
                    <div style="width:440px;">
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Producto fabricado</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" id="prep-producto" class="form-control border-start-0 px-1" placeholder="Código o nombre (vacío = todos)" value="<?= htmlspecialchars($filtros['producto']) ?>">
                        </div>
                    </div>
                    <div>
                        <label class="form-label small fw-bold mb-1 d-block" style="font-size:.65rem;">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm px-2" onclick="window.PREP_limpiar()"><i class="bi bi-eraser me-1"></i>Limpiar</button>
                            <button type="submit" class="btn btn-primary btn-sm px-3"><i class="bi bi-search me-1"></i>Mostrar</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-footer bg-white border-top py-2 px-3">
            <div class="cmg-control-card__stats">
                <?= $stat('prep-stat-ordenes', 'bi-file-earmark-text', 'primary', 'Órdenes cerradas') ?>
                <?= $stat('prep-stat-materiales', 'bi-box-arrow-up', 'secondary', 'Materiales') ?>
                <?= $stat('prep-stat-conversion', 'bi-person-gear', 'info', 'Mano de obra + CIF') ?>
                <?= $stat('prep-stat-costo', 'bi-cash-stack', 'success', 'Costo producido') ?>
                <?= $stat('prep-stat-merma', 'bi-exclamation-octagon', 'danger', 'Merma anormal') ?>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <ul class="nav nav-tabs border-0" id="prep-tabs">
                <?php $i = 0; foreach ($pestanas as $k => $p): ?>
                    <li class="nav-item"><button type="button" class="nav-link py-1 small <?= $i++ === 0 ? 'active' : '' ?>" data-pestana="<?= $k ?>">
                        <i class="bi <?= $p['icono'] ?> me-1"></i><?= htmlspecialchars($p['titulo']) ?></button></li>
                <?php endforeach; ?>
            </ul>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-danger" onclick="window.PREP_exportar('pdf')"><i class="bi bi-file-earmark-pdf"></i> PDF</button>
                <button type="button" class="btn btn-outline-success" onclick="window.PREP_exportar('excel')"><i class="bi bi-file-earmark-spreadsheet"></i> Excel</button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="produccion-reportes-scroll w-100">
                <table class="table table-hover table-sm mb-0 align-middle" style="font-size:.8rem;" id="prep-tabla"></table>
            </div>
        </div>
    </div>
</div>

<script>
    window.PREP_URL_BASE = '<?= $urlBase ?>';
</script>
<script src="<?= rtrim($base, '/') ?>/js/modulos/produccion_reportes.js?v=<?= asset_ver('/js/modulos/produccion_reportes.js') ?>"></script>
