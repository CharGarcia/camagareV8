<?php
/** @var string $titulo @var array $perm @var string $rutaModulo @var bool $instalado @var ?array $config
 *  @var array $filtros @var array $pestanas @var array $formulas @var array $productos @var array $insumos @var array $bodegas
 *  @var array $usuarios @var string $base */
use App\Helpers\ColumnasReporte;

$urlBase  = rtrim($base, '/') . '/' . $rutaModulo;
$idModulo = str_replace('-', '_', basename($rutaModulo));
$h = static fn($v): string => htmlspecialchars((string) $v);
$stat = fn(string $id, string $icono, string $color, string $label): string =>
    '<div class="cmg-control-card__stat"><i class="bi ' . $icono . ' bg-' . $color . ' bg-opacity-10 text-' . $color . '"></i><div>'
    . '<div class="cmg-control-card__stat-value" id="' . $id . '">—</div><div class="cmg-control-card__stat-label">' . $label . '</div></div></div>';
$lbl = static fn(string $t): string => '<label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">' . $t . '</label>';
?>
<script>document.body.classList.add('cmg-no-app-shell');</script>
<style>
    /* La página hace scroll; la tabla solo necesita el horizontal (tarjeta de control fija, §9). */
    .produccion-reportes-scroll { overflow-x: auto; }
    .produccion-reportes-scroll thead th { background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; white-space: nowrap; }
    .produccion-reportes-scroll td { white-space: nowrap; }
    #form-filtros-prep .form-select, #form-filtros-prep .form-control, #form-filtros-prep .input-group-text, #form-filtros-prep .btn { height: 28px; font-size: .75rem; }
    #form-filtros-prep .prep-campo { min-width: 0; }
    #form-filtros-prep .prep-lista .list-group-item { font-size: .75rem; padding: .3rem .6rem; }
    @media (max-width: 767.98px) {
        #modulo-<?= $idModulo ?> .produccion-reportes-scroll { max-height: none !important; height: auto !important; overflow-y: visible !important; }
    }
</style>

<div class="container-fluid pt-0 pb-3 px-0 px-md-3" id="modulo-<?= $idModulo ?>">
    <?php if (!$instalado || !$config): ?>
        <div class="alert alert-info py-2 small"><i class="bi bi-info-circle me-1"></i>
            Producción aún no está configurado en esta empresa. Guarde primero la Configuración de Producción.
        </div>
    <?php endif; ?>

    <!-- ── Tarjeta de control fija ── -->
    <div class="card cmg-control-card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-header bg-white border-bottom py-2 px-3 d-flex justify-content-between align-items-center gap-2">
            <h5 class="mb-0 fw-bold"><i class="bi bi-bar-chart-line me-2 text-primary"></i><?= $h($titulo) ?></h5>
            <?= ColumnasReporte::boton($rutaModulo) ?>
        </div>
        <div class="card-body p-3">
            <!-- Todos los filtros y los botones en una sola fila (en pantallas chicas bajan a la siguiente). -->
            <form id="form-filtros-prep" onsubmit="event.preventDefault(); window.PREP_cargar();" class="d-flex flex-wrap flex-xl-nowrap align-items-start gap-2" autocomplete="off">
                <div style="flex:0 0 115px;">
                    <?= $lbl('Cierre desde') ?>
                    <input type="date" id="prep-desde" class="form-control form-control-sm" value="<?= $h($filtros['desde']) ?>">
                </div>
                <div style="flex:0 0 115px;">
                    <?= $lbl('Cierre hasta') ?>
                    <input type="date" id="prep-hasta" class="form-control form-control-sm" value="<?= $h($filtros['hasta']) ?>">
                </div>
                <div class="position-relative prep-campo" style="flex:1 1 210px;">
                    <?= $lbl('Fórmula') ?>
                    <input type="hidden" id="prep-formula" value="<?= (int) $filtros['id_formula'] ?: '' ?>">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-diagram-3"></i></span>
                        <input type="text" id="prep-formula-txt" class="form-control border-start-0 px-1" placeholder="Buscar fórmula (vacío = todas)">
                    </div>
                    <div id="prep-formula-lista" class="list-group shadow-sm position-absolute w-100 d-none prep-lista" style="z-index:1060;max-height:260px;overflow:auto;top:100%;"></div>
                </div>
                <div class="prep-campo" style="flex:1 1 140px;">
                    <?= $lbl('Bodega de destino') ?>
                    <select id="prep-bodega" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <?php foreach ($bodegas as $b): ?>
                            <option value="<?= (int) $b['id'] ?>" <?= (int) $filtros['id_bodega'] === (int) $b['id'] ? 'selected' : '' ?>><?= $h($b['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($usuarios): ?>
                    <div class="prep-campo" style="flex:1 1 130px;">
                        <?= $lbl('Usuario') ?>
                        <select id="prep-usuario" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <?php foreach ($usuarios as $u): ?>
                                <option value="<?= (int) $u['id'] ?>" <?= (int) $filtros['id_usuario'] === (int) $u['id'] ? 'selected' : '' ?>><?= $h($u['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="prep-campo" style="flex:1 1 120px;">
                    <?= $lbl('Responsable') ?>
                    <input type="text" id="prep-responsable" class="form-control form-control-sm" placeholder="Nombre" value="<?= $h($filtros['responsable']) ?>">
                </div>
                <div class="position-relative prep-campo" style="flex:1 1 210px;">
                    <?= $lbl('Producto fabricado') ?>
                    <input type="hidden" id="prep-producto" value="<?= (int) $filtros['id_producto'] ?: '' ?>">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-box-seam"></i></span>
                        <input type="text" id="prep-producto-txt" class="form-control border-start-0 px-1" placeholder="Buscar producto (vacío = todos)">
                    </div>
                    <div id="prep-producto-lista" class="list-group shadow-sm position-absolute w-100 d-none prep-lista" style="z-index:1060;max-height:260px;overflow:auto;top:100%;"></div>
                </div>
                <div class="position-relative prep-campo" style="flex:1 1 200px;">
                    <?= $lbl('Insumo') ?>
                    <input type="hidden" id="prep-insumo" value="<?= (int) $filtros['id_insumo'] ?: '' ?>">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-box-arrow-up"></i></span>
                        <input type="text" id="prep-insumo-txt" class="form-control border-start-0 px-1" placeholder="Buscar insumo (vacío = todos)">
                    </div>
                    <div id="prep-insumo-lista" class="list-group shadow-sm position-absolute w-100 d-none prep-lista" style="z-index:1060;max-height:260px;overflow:auto;top:100%;"></div>
                </div>
                <div style="flex:0 0 auto;">
                    <label class="form-label small fw-bold mb-1 d-block" style="font-size:.65rem;">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm px-2" onclick="window.PREP_limpiar()"><i class="bi bi-eraser me-1"></i>Limpiar</button>
                        <button type="submit" class="btn btn-primary btn-sm px-3"><i class="bi bi-search me-1"></i>Mostrar</button>
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
                        <i class="bi <?= $p['icono'] ?> me-1"></i><?= $h($p['titulo']) ?></button></li>
                <?php endforeach; ?>
            </ul>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-danger" onclick="window.PREP_exportar('pdf')" title="Descargar PDF"><i class="bi bi-file-earmark-pdf"></i> PDF</button>
                <button type="button" class="btn btn-outline-success" onclick="window.PREP_exportar('excel')" title="Descargar Excel"><i class="bi bi-file-earmark-spreadsheet"></i> Excel</button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="produccion-reportes-scroll w-100">
                <table class="table table-hover table-sm mb-0 align-middle" style="font-size:.8rem;" id="prep-tabla"></table>
            </div>
        </div>
    </div>
</div>

<?= ColumnasReporte::modal($rutaModulo) ?>

<script>
    window.PREP_URL_BASE = '<?= $urlBase ?>';
    window.PREP_FORMULAS = <?= json_encode(array_map(static fn($fo) => ['id' => (int) $fo['id'], 'texto' => $fo['codigo'] . ' v' . $fo['version'] . ' · ' . $fo['nombre']], $formulas), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    window.PREP_PRODUCTOS = <?= json_encode(array_map(static fn($p) => ['id' => (int) $p['id'], 'texto' => $p['codigo'] . ' - ' . $p['nombre']], $productos), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    window.PREP_INSUMOS = <?= json_encode(array_map(static fn($p) => ['id' => (int) $p['id'], 'texto' => $p['codigo'] . ' - ' . $p['nombre']], $insumos), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS) ?>;
</script>
<script src="<?= rtrim($base, '/') ?>/js/modulos/produccion_reportes.js?v=<?= asset_ver('/js/modulos/produccion_reportes.js') ?>"></script>
