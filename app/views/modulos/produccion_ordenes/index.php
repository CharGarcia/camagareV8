<?php
/** @var string $titulo @var array $perm @var string $rutaModulo @var array $vistaConfig @var bool $instalado
 *  @var ?array $config @var string $filasHtml @var int $total @var int $perPage @var string $buscar @var array $filtros
 *  @var array $resumen @var array $bodegas @var array $estados @var array $tipos @var string $ordenJson
 *  @var string $ordenParam @var string $base */
$urlBase = rtrim($base, '/') . '/' . $rutaModulo;
$idModulo = str_replace('-', '_', basename($rutaModulo));
$pages = max(1, (int) ceil($total / max(1, $perPage)));
$q = http_build_query(['b' => $buscar] + $filtros + ['orden' => $ordenParam]);
$columnasTabla = [
    'numero'               => 'Número',
    'fecha_inicio'         => 'Fecha',
    'producto'             => 'Producto',
    'lote'                 => 'Lote',
    'cantidad_planificada' => 'Planificado',
    'cantidad_producida'   => 'Producido',
    'costo_total'          => 'Costo',
    'bodegas'              => 'Bodegas',
    'fecha_fin'            => 'Cierre',
    'estado'               => 'Estado',
];
$stat = function (string $id, string $icono, string $color, string $valor, string $label): string {
    return '<div class="cmg-control-card__stat"><i class="bi ' . $icono . ' bg-' . $color . ' bg-opacity-10 text-' . $color . '"></i><div>'
         . '<div class="cmg-control-card__stat-value" id="' . $id . '">' . $valor . '</div>'
         . '<div class="cmg-control-card__stat-label">' . $label . '</div></div></div>';
};
?>
<script>document.body.classList.add('cmg-no-app-shell');</script>
<style>
    .produccion-ordenes-scroll { overflow-x: auto; }
    .produccion-ordenes-scroll thead th { background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; white-space: nowrap; }
    .por-row { cursor: pointer; }
    .por-row:hover { background-color: rgba(0, 0, 0, .04); }
    #form-filtros-por .form-select, #form-filtros-por .form-control, #form-filtros-por .input-group-text, #form-filtros-por .btn { height: 28px; font-size: .75rem; }
    .por-lineas td { vertical-align: middle; }
    .por-lineas input, .por-lineas select { padding: 0 4px; height: 22px; font-size: .78rem; border: 1px solid #dee2e6; border-radius: 3px; width: 100%; }
    .por-lineas input[readonly] { background: #f8f9fa; }
    .por-dd { z-index: 1090; max-height: 230px; overflow-y: auto; }
    .por-modal .form-text { font-size: .72rem; }
    @media (max-width: 767.98px) {
        #modulo-<?= $idModulo ?> .produccion-ordenes-scroll { max-height: none !important; height: auto !important; overflow-y: visible !important; }
    }
</style>

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>

<div class="container-fluid pt-0 pb-3 px-0 px-md-3" id="modulo-<?= $idModulo ?>">
    <?php if (!$instalado): ?>
        <div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle me-1"></i>
            El módulo Producción aún no está instalado en la base de datos: falta aplicar <code>database/2026-11-03_produccion.sql</code>.
        </div>
    <?php elseif (!$config): ?>
        <div class="alert alert-info py-2 small"><i class="bi bi-info-circle me-1"></i>
            Producción aún no está configurado en esta empresa. Guarde primero la
            <a href="<?= rtrim($base, '/') ?>/modulos/produccion-configuracion">Configuración de Producción</a>.
        </div>
    <?php endif; ?>

    <!-- ── Tarjeta de control fija ── -->
    <div class="card cmg-control-card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-header bg-white border-bottom py-2 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0 fw-bold"><i class="bi bi-gear-wide-connected me-2 text-primary"></i><?= htmlspecialchars($titulo) ?></h5>
            <?php if ($config && !empty($perm['crear'])): ?>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-warning btn-sm px-3" onclick="window.POR_nueva('rapida')" title="Consumir y producir en un solo paso">
                        <i class="bi bi-lightning-charge me-1"></i> Producción rápida
                    </button>
                    <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="window.POR_nueva('orden')">
                        <i class="bi bi-plus-lg me-1"></i> Nueva orden
                    </button>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-body p-3">
            <form id="form-filtros-por" onsubmit="event.preventDefault(); window.POR_buscar(1);" class="d-flex flex-wrap align-items-start gap-2">
                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Desde</label>
                    <input type="date" id="por-desde" class="form-control form-control-sm" style="width:115px;" value="<?= htmlspecialchars($filtros['desde']) ?>">
                </div>
                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Hasta</label>
                    <input type="date" id="por-hasta" class="form-control form-control-sm" style="width:115px;" value="<?= htmlspecialchars($filtros['hasta']) ?>">
                </div>
                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Estado</label>
                    <select id="por-estado" class="form-select form-select-sm" style="width:135px;" onchange="window.POR_buscar(1)">
                        <option value="">Todos</option>
                        <?php foreach ($estados as $k => $l): ?>
                            <option value="<?= $k ?>" <?= $filtros['estado'] === $k ? 'selected' : '' ?>><?= htmlspecialchars($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Tipo</label>
                    <select id="por-tipo" class="form-select form-select-sm" style="width:160px;" onchange="window.POR_buscar(1)">
                        <option value="">Todos</option>
                        <?php foreach ($tipos as $k => $l): ?>
                            <option value="<?= $k ?>" <?= $filtros['tipo'] === $k ? 'selected' : '' ?>><?= htmlspecialchars($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="d-flex flex-wrap align-items-start gap-2">
                    <div style="width:440px;">
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Buscar</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" id="por-buscar" class="form-control border-start-0 px-1" placeholder="Número, producto, lote o responsable…"
                                   autocomplete="off" value="<?= htmlspecialchars($buscar) ?>">
                        </div>
                    </div>
                    <div>
                        <label class="form-label small fw-bold mb-1 d-block" style="font-size:.65rem;">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm px-2" onclick="window.POR_limpiar()"><i class="bi bi-eraser me-1"></i>Limpiar</button>
                            <button type="submit" class="btn btn-primary btn-sm px-3"><i class="bi bi-search me-1"></i>Mostrar</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-footer bg-white border-top py-2 px-3">
            <div class="cmg-control-card__stats">
                <?= $stat('por-stat-ordenes', 'bi-file-earmark-text', 'primary', (string) (int) ($resumen['ordenes'] ?? 0), 'Órdenes') ?>
                <?= $stat('por-stat-abiertas', 'bi-hourglass-split', 'warning', (string) (int) ($resumen['abiertas'] ?? 0), 'Abiertas') ?>
                <?= $stat('por-stat-cerradas', 'bi-check2-circle', 'success', (string) (int) ($resumen['cerradas'] ?? 0), 'Cerradas') ?>
                <?= $stat('por-stat-costo', 'bi-cash-stack', 'info', '$' . number_format((float) ($resumen['costo'] ?? 0), 2), 'Costo producido') ?>
                <?= $stat('por-stat-merma', 'bi-exclamation-octagon', 'danger', '$' . number_format((float) ($resumen['merma'] ?? 0), 2), 'Merma anormal') ?>
            </div>
        </div>
    </div>

    <!-- ── Tabla ── -->
    <div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="btn-group btn-group-sm">
                <a id="por-pdf" href="<?= $urlBase ?>/export-pdf?<?= $q ?>" class="btn btn-outline-danger"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                <a id="por-excel" href="<?= $urlBase ?>/export-excel?<?= $q ?>" class="btn btn-outline-success"><i class="bi bi-file-earmark-spreadsheet"></i> Excel</a>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span id="por-info" class="text-muted small fw-medium"><?= $total > 0 ? 1 : 0 ?>-<?= min($perPage, $total) ?>/<?= $total ?></span>
                <div id="por-paginacion" class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" disabled onclick="window.POR_buscar(window.POR_pagina - 1)"><i class="bi bi-chevron-left"></i></button>
                    <button type="button" class="btn btn-outline-secondary" <?= $pages <= 1 ? 'disabled' : '' ?> onclick="window.POR_buscar(window.POR_pagina + 1)"><i class="bi bi-chevron-right"></i></button>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="produccion-ordenes-scroll w-100">
                <table class="table table-hover table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3 sortable-header" role="button" data-sort="numero" data-col="numero">Número <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="sortable-header" role="button" data-sort="fecha_inicio" data-col="fecha_inicio">Fecha <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="sortable-header" role="button" data-sort="producto" data-col="producto">Producto <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th data-col="lote">Lote</th>
                            <th class="sortable-header text-end" role="button" data-sort="cantidad_planificada" data-col="cantidad_planificada">Planificado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="sortable-header text-end" role="button" data-sort="cantidad_producida" data-col="cantidad_producida">Producido <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="sortable-header text-end" role="button" data-sort="costo_total" data-col="costo_total">Costo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th data-col="bodegas">Bodegas</th>
                            <th class="sortable-header" role="button" data-sort="fecha_fin" data-col="fecha_fin">Cierre <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="sortable-header text-center pe-3" role="button" data-sort="estado" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        </tr>
                    </thead>
                    <tbody id="por-tbody"><?= $filasHtml ?></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/modal_nueva.php'; ?>
<?php include __DIR__ . '/modal_orden.php'; ?>

<script>
    window.POR_URL_BASE = '<?= $urlBase ?>';
    window.POR_MODULO   = '<?= $rutaModulo ?>';
    window.POR_PERM     = <?= json_encode([
        'crear'      => !empty($perm['crear']),
        'actualizar' => !empty($perm['actualizar']),
        'eliminar'   => !empty($perm['eliminar']),
    ]) ?>;
    window.POR_CONFIG   = <?= json_encode($config ? [
        'id_bodega_insumos'    => (int) ($config['id_bodega_insumos'] ?? 0),
        'id_bodega_terminado'  => (int) ($config['id_bodega_terminado'] ?? 0),
        'id_bodega_cuarentena' => (int) ($config['id_bodega_cuarentena'] ?? 0),
        'usar_cuarentena'      => !empty($config['usar_cuarentena']),
        'exigir_lote_insumos'  => !empty($config['exigir_lote_insumos']),
    ] : null) ?>;
    window.POR_PAGES    = <?= (int) $pages ?>;
    window.currentSorts = <?= $ordenJson ?>;
</script>
<script src="<?= rtrim($base, '/') ?>/js/modulos/produccion_ordenes.js?v=<?= asset_ver('/js/modulos/produccion_ordenes.js') ?>"></script>
