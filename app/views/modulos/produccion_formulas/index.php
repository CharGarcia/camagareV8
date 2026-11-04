<?php
/** @var string $titulo @var array $perm @var string $rutaModulo @var array $vistaConfig @var bool $instalado
 *  @var ?array $config @var string $filasHtml @var int $total @var int $perPage @var string $buscar @var string $estado
 *  @var string $ordenJson @var string $ordenParam @var array $modos @var array $tiposRecurso @var array $basesRecurso
 *  @var array $metodos @var string $base */
$urlBase = rtrim($base, '/') . '/' . $rutaModulo;
$to = min($perPage, $total);
$pages = max(1, (int) ceil($total / max(1, $perPage)));
$qExport = 'b=' . urlencode($buscar) . '&estado=' . urlencode($estado) . '&orden=' . urlencode($ordenParam);
$columnasTabla = [
    'codigo'        => 'Código',
    'nombre'        => 'Nombre',
    'producto'      => 'Producto',
    'version'       => 'Versión',
    'cantidad_base' => 'Rinde',
    'lineas'        => 'Insumos / Salidas',
    'estado'        => 'Estado',
    'updated_at'    => 'Modificado',
];
?>
<style>
    .produccion-formulas-scroll thead th { background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; white-space: nowrap; }
    .pfo-row { cursor: pointer; }
    .pfo-row:hover { background-color: rgba(0, 0, 0, .04); }
    .pfo-lineas td { vertical-align: middle; }
    .pfo-lineas input, .pfo-lineas select { padding: 0 4px; height: 22px; font-size: .78rem; border: 1px solid #dee2e6; border-radius: 3px; width: 100%; }
    .pfo-dd { z-index: 1090; max-height: 230px; overflow-y: auto; }
    #modalFormula .form-text { font-size: .72rem; }
</style>

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-diagram-3 text-primary me-2"></i><?= htmlspecialchars($titulo) ?></h5>
    <?php if ($config && !empty($perm['crear'])): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="window.PFO_nueva()"><i class="bi bi-plus-lg"></i> Nueva</button>
    <?php endif; ?>
</div>

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

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <div class="input-group input-group-sm" style="width:380px;">
                <span class="input-group-text bg-white text-muted"><i class="bi bi-search"></i></span>
                <input type="text" id="pfo-buscar" class="form-control" placeholder="Código, nombre o producto (estado:vigente)…"
                       autocomplete="off" value="<?= htmlspecialchars($buscar) ?>">
            </div>
            <select id="pfo-estado" class="form-select form-select-sm" style="width:130px;" onchange="window.PFO_buscar(1)">
                <option value="">Todos</option>
                <option value="borrador" <?= $estado === 'borrador' ? 'selected' : '' ?>>Borrador</option>
                <option value="vigente"  <?= $estado === 'vigente' ? 'selected' : '' ?>>Vigentes</option>
                <option value="inactiva" <?= $estado === 'inactiva' ? 'selected' : '' ?>>Inactivas</option>
            </select>
            <div class="btn-group btn-group-sm">
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>
                <a id="pfo-pdf" href="<?= $urlBase ?>/export-pdf?<?= $qExport ?>" class="btn btn-outline-danger" title="Descargar PDF"><i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span></a>
                <a id="pfo-excel" href="<?= $urlBase ?>/export-excel?<?= $qExport ?>" class="btn btn-outline-success" title="Descargar Excel"><i class="bi bi-file-earmark-spreadsheet"></i><span class="d-none d-md-inline"> Excel</span></a>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span id="pfo-info" class="text-muted small fw-medium"><?= $total > 0 ? 1 : 0 ?>-<?= $to ?>/<?= $total ?></span>
            <div id="pfo-paginacion" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" disabled onclick="window.PFO_buscar(window.PFO_pagina - 1)"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" <?= $pages <= 1 ? 'disabled' : '' ?> onclick="window.PFO_buscar(window.PFO_pagina + 1)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="produccion-formulas-scroll w-100">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="codigo" data-col="codigo">Código <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="nombre" data-col="nombre">Nombre <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="producto" data-col="producto">Producto <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-center" role="button" data-sort="version" data-col="version">Versión <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-end" role="button" data-sort="cantidad_base" data-col="cantidad_base">Rinde <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center" data-col="lineas" title="Insumos / salidas">Ins. / Sal.</th>
                        <th class="sortable-header text-center" role="button" data-sort="estado" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header pe-3" role="button" data-sort="updated_at" data-col="updated_at">Modificado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="pfo-tbody"><?= $filasHtml ?></tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/modal.php'; ?>

<script>
    window.PFO_URL_BASE = '<?= $urlBase ?>';
    window.PFO_MODULO   = '<?= $rutaModulo ?>';
    window.PFO_PERM     = <?= json_encode([
        'crear'      => !empty($perm['crear']),
        'actualizar' => !empty($perm['actualizar']),
        'eliminar'   => !empty($perm['eliminar']),
    ]) ?>;
    window.PFO_ACTIVO   = <?= $config ? 'true' : 'false' ?>;
    window.PFO_METODO_CFG = '<?= htmlspecialchars((string) ($config['metodo_reparto'] ?? 'valor_venta')) ?>';
    window.PFO_MODOS    = <?= json_encode($modos, JSON_UNESCAPED_UNICODE) ?>;
    window.PFO_TIPOS_RECURSO = <?= json_encode($tiposRecurso, JSON_UNESCAPED_UNICODE) ?>;
    window.PFO_BASES_RECURSO = <?= json_encode($basesRecurso, JSON_UNESCAPED_UNICODE) ?>;
    window.PFO_PAGES    = <?= (int) $pages ?>;
    window.currentSorts = <?= $ordenJson ?>;
</script>
<script src="<?= rtrim($base, '/') ?>/js/modulos/produccion_formulas.js?v=<?= asset_ver('/js/modulos/produccion_formulas.js') ?>"></script>
