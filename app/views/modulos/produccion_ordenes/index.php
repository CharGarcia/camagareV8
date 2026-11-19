<?php
/** @var string $titulo @var array $perm @var string $rutaModulo @var array $vistaConfig @var bool $instalado
 *  @var ?array $config @var string $filasHtml @var int $total @var int $perPage @var string $buscar
 *  @var array $bodegas @var array $estados @var array $tipos @var array $usuariosFiltro @var string $ordenJson
 *  @var string $ordenParam @var string $base */
$urlBase = rtrim($base, '/') . '/' . $rutaModulo;
$pages = max(1, (int) ceil($total / max(1, $perPage)));
$q = http_build_query(['b' => $buscar, 'orden' => $ordenParam]);
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
?>
<style>
    .produccion-ordenes-scroll thead th { background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; white-space: nowrap; }
    .por-row { cursor: pointer; }
    .por-row:hover { background-color: rgba(0, 0, 0, .04); }
    /* Tablas de ítems de los modales: mismo diseño que el detalle de Facturas de Venta / Fórmulas
       (.table-detalle + controles sin borde que se marcan en azul al enfocar). */
    .por-lineas.table-detalle th { font-size: .7rem !important; text-transform: uppercase; background-color: #f8f9fa; padding: 4px 8px !important; }
    .por-lineas.table-detalle td { padding: 3px 8px; vertical-align: middle; font-size: .82rem; }
    .por-lineas.table-detalle td.p-0 { padding: 0 !important; }
    .por-lineas.table-detalle tbody tr:hover { background-color: rgba(13, 110, 253, .03); }
    .por-lineas input:not([type="checkbox"]), .por-lineas select {
        border: none; background: transparent; width: 100%; height: 30px; font-size: .82rem; padding: 2px 8px; border-radius: 0;
    }
    .por-lineas input:not([type="checkbox"]):focus, .por-lineas select:focus { background: #fff; box-shadow: inset 0 0 0 1px #0d6efd; outline: none; }
    .por-lineas input[readonly] { background: #f8f9fa; }
    .por-lineas input:disabled { color: #adb5bd; }
    .por-dd { z-index: 1090; max-height: 230px; overflow-y: auto; }
    .por-modal .form-text { font-size: .72rem; }
</style>

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-gear-wide-connected text-primary me-2"></i><?= htmlspecialchars($titulo) ?></h5>
    <?php if ($config && !empty($perm['crear'])): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="window.POR_nueva('orden')"><i class="bi bi-plus-lg"></i> Nueva</button>
    <?php endif; ?>
</div>

<?php if (!$instalado): ?>
    <div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle me-1"></i>
        El módulo Producción aún no está instalado en la base de datos: falta aplicar <code>database/2026-11-03_produccion.sql</code>.
    </div>
<?php elseif (!$config): ?>
    <div class="alert alert-info py-2 small"><i class="bi bi-info-circle me-1"></i>
        Producción aún no está configurado en esta empresa. Guarde primero la Configuración de Producción.
    </div>
<?php endif; ?>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <!-- Buscador y Exportación -->
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php
            // Buscador estándar (FiltrosModal), igual que Proveedores: texto libre sobre número, producto,
            // lote, responsable y observaciones + embudo con todos los filtros + chips de los activos.
            // Las claves (key) deben existir en los mapas de ProduccionOrdenRepository::whereListado().
            $tO = 'Orden';
            $opcMapa = fn(array $mapa) => array_map(fn($k, $l) => ['v' => (string) $k, 'l' => (string) $l], array_keys($mapa), array_values($mapa));
            $opcBodegas = array_map(fn($b) => ['v' => (string) $b['id'], 'l' => (string) $b['nombre']], $bodegas ?? []);
            $filtrosOrdenes = [
                // ── Orden ──
                ['tab' => $tO, 'key' => 'numero', 'label' => 'Número',  'icon' => 'bi-hash',           'type' => 'text',       'grupo' => 'Orden', 'col' => 3],
                ['tab' => $tO, 'key' => 'estado', 'label' => 'Estado',  'icon' => 'bi-flag',           'type' => 'select',     'grupo' => 'Orden', 'col' => 3, 'options' => $opcMapa($estados ?? [])],
                ['tab' => $tO, 'key' => 'tipo',   'label' => 'Tipo',    'icon' => 'bi-diagram-2',      'type' => 'select',     'grupo' => 'Orden', 'col' => 3, 'options' => $opcMapa($tipos ?? [])],
                ['tab' => $tO, 'key' => 'responsable', 'label' => 'Responsable', 'icon' => 'bi-person', 'type' => 'text',      'grupo' => 'Orden', 'col' => 3],
                ['tab' => $tO, 'key' => 'fecha',  'label' => 'Fecha de la orden', 'icon' => 'bi-calendar-event', 'type' => 'date_range', 'grupo' => 'Orden', 'col' => 6, 'atajos' => true],
                ['tab' => $tO, 'key' => 'cierre', 'label' => 'Fecha de cierre',   'icon' => 'bi-calendar-check', 'type' => 'date_range', 'grupo' => 'Orden', 'col' => 6, 'atajos' => true],
                // ── Producto ──
                ['tab' => $tO, 'key' => 'producto_codigo', 'label' => 'Código del producto', 'icon' => 'bi-upc-scan', 'type' => 'text', 'grupo' => 'Producto', 'col' => 3],
                ['tab' => $tO, 'key' => 'producto',  'label' => 'Producto',  'icon' => 'bi-box-seam',      'type' => 'text',       'grupo' => 'Producto', 'col' => 3],
                ['tab' => $tO, 'key' => 'lote',      'label' => 'Lote',      'icon' => 'bi-upc',           'type' => 'text',       'grupo' => 'Producto', 'col' => 3],
                ['tab' => $tO, 'key' => 'caducidad', 'label' => 'Caducidad', 'icon' => 'bi-hourglass-split', 'type' => 'date_range', 'grupo' => 'Producto', 'col' => 3],
                // ── Cantidades y costos ──
                ['tab' => $tO, 'key' => 'planificado', 'label' => 'Planificado',   'icon' => 'bi-clipboard-data', 'type' => 'number_range', 'grupo' => 'Cantidades y costos', 'col' => 3],
                ['tab' => $tO, 'key' => 'cantidad',    'label' => 'Producido',     'icon' => 'bi-box-arrow-in-down', 'type' => 'number_range', 'grupo' => 'Cantidades y costos', 'col' => 3],
                ['tab' => $tO, 'key' => 'costo',       'label' => 'Costo total',   'icon' => 'bi-cash-stack',     'type' => 'number_range', 'grupo' => 'Cantidades y costos', 'col' => 3],
                ['tab' => $tO, 'key' => 'merma',       'label' => 'Merma anormal ($)', 'icon' => 'bi-exclamation-octagon', 'type' => 'number_range', 'grupo' => 'Cantidades y costos', 'col' => 3],
                // ── Bodegas y contabilidad ──
                ['tab' => $tO, 'key' => 'bodega_insumos', 'label' => 'Bodega de insumos', 'icon' => 'bi-box-arrow-up',      'type' => 'select', 'grupo' => 'Bodegas y contabilidad', 'col' => 4, 'options' => $opcBodegas],
                ['tab' => $tO, 'key' => 'bodega_destino', 'label' => 'Bodega de destino', 'icon' => 'bi-box-arrow-in-down', 'type' => 'select', 'grupo' => 'Bodegas y contabilidad', 'col' => 4, 'options' => $opcBodegas],
                ['tab' => $tO, 'key' => 'asiento',        'label' => 'Asiento contable',  'icon' => 'bi-journal-text',      'type' => 'select', 'grupo' => 'Bodegas y contabilidad', 'col' => 4, 'options' => [
                    ['v' => 'si', 'l' => 'Con asiento'],
                    ['v' => 'no', 'l' => 'Sin asiento'],
                ]],
                // ── Registro ──
                ['tab' => $tO, 'key' => 'registro', 'label' => 'Fecha de registro',    'icon' => 'bi-calendar-plus', 'type' => 'date_range', 'grupo' => 'Registro', 'col' => 6, 'atajos' => true],
                ['tab' => $tO, 'key' => 'usuario',  'label' => 'Usuario que registró', 'icon' => 'bi-person-gear',   'type' => 'select',     'grupo' => 'Registro', 'col' => 6,
                    'options' => array_map(fn($u) => ['v' => (string) $u['id'], 'l' => (string) $u['nombre']], $usuariosFiltro ?? [])],
            ];
            ?>
            <link rel="stylesheet" href="<?= rtrim($base, '/') ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
            <script src="<?= rtrim($base, '/') ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
            <div id="fmBuscadorPOR"></div>
            <input type="hidden" id="por-buscar" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosModal) return;
                    new FiltrosModal({
                        containerId: 'fmBuscadorPOR',
                        hiddenInputId: 'por-buscar',
                        placeholder: 'Buscar en todas las columnas...',
                        titulo: 'Filtros de órdenes de producción',
                        inputWidth: 420,
                        extraId: 'fmExtraPOR', // columnas + PDF + Excel, pegados al final del grupo
                        fields: <?= json_encode($filtrosOrdenes, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                        loadingTarget: '#por-tbody',
                        onApply: () => window.POR_buscar && window.POR_buscar(1),
                    }).init();
                });
            </script>

            <?php // FiltrosModal (extraId) mueve estos botones dentro del input-group del buscador; si el JS no corre, quedan aquí. ?>
            <div id="fmExtraPOR" class="btn-group btn-group-sm">
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>
                <a id="por-pdf" href="<?= $urlBase ?>/export-pdf?<?= $q ?>" class="btn btn-outline-danger" title="Descargar PDF"><i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span></a>
                <a id="por-excel" href="<?= $urlBase ?>/export-excel?<?= $q ?>" class="btn btn-outline-success" title="Descargar Excel"><i class="bi bi-file-earmark-spreadsheet"></i><span class="d-none d-md-inline"> Excel</span></a>
            </div>
        </div>

        <!-- Paginación -->
        <div class="d-flex align-items-center gap-3">
            <span id="por-info" class="text-muted small fw-medium"><?= $total > 0 ? 1 : 0 ?>-<?= min($perPage, $total) ?>/<?= $total ?></span>
            <div id="por-paginacion" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" disabled onclick="window.POR_buscar(window.POR_pagina - 1)"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" <?= $pages <= 1 ? 'disabled' : '' ?> onclick="window.POR_buscar(window.POR_pagina + 1)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <!-- Tabla -->
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
<?php // Pestaña «Asiento» editable: componente común (Compras, Ingresos, etc.). ?>
<script src="<?= rtrim($base, '/') ?>/js/modulos/asiento_contable_tab.js?v=<?= asset_ver('/js/modulos/asiento_contable_tab.js') ?>"></script>
<script src="<?= rtrim($base, '/') ?>/js/modulos/produccion_ordenes.js?v=<?= asset_ver('/js/modulos/produccion_ordenes.js') ?>"></script>
