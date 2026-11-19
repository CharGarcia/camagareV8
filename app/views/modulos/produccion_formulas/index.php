<?php
/** @var string $titulo @var array $perm @var string $rutaModulo @var array $vistaConfig @var bool $instalado
 *  @var ?array $config @var string $filasHtml @var int $total @var int $perPage @var string $buscar @var string $estado
 *  @var string $ordenJson @var string $ordenParam @var array $modos @var array $tiposRecurso @var array $basesRecurso
 *  @var array $metodos @var array $usuariosFiltro @var string $base */
$urlBase = rtrim($base, '/') . '/' . $rutaModulo;
$to = min($perPage, $total);
$pages = max(1, (int) ceil($total / max(1, $perPage)));
$qExport = 'b=' . urlencode($buscar) . '&orden=' . urlencode($ordenParam);
$columnasTabla = [
    'codigo'        => 'Código',
    'nombre'        => 'Descripción',
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
    /* Tablas de ítems del modal: mismo diseño que el detalle de Facturas de Venta (.table-detalle / .input-detalle). */
    #modalFormula .table-detalle th { font-size: .7rem !important; text-transform: uppercase; background-color: #f8f9fa; padding: 4px 8px !important; }
    #modalFormula .table-detalle td { padding: 0 !important; vertical-align: middle; }
    #modalFormula .table-detalle td.pfo-celda-texto { padding: 2px 8px !important; }
    #modalFormula .input-detalle { border: none; background: transparent; width: 100%; height: 30px !important; font-size: .82rem !important; padding: 2px 8px !important; border-radius: 0; }
    #modalFormula .input-detalle:focus { background: #fff; box-shadow: inset 0 0 0 1px #0d6efd; outline: none; }
    #modalFormula .input-detalle:disabled { background: transparent; color: #495057; opacity: 1; }
    #modalFormula .table-detalle tbody tr:hover { background-color: rgba(13, 110, 253, .03); }
    /* Lista flotante del buscador de productos (fixed, en <body>): sobre el modal (5060). */
    .pfo-dd { z-index: 6000; max-height: 260px; overflow-y: auto; }
    #modalFormula .form-text { font-size: .72rem; }
    .pfo-ayuda { cursor: pointer; font-size: .85rem; }
    /* z-index: por encima del navbar (5000) y de los modales (5060); debajo de SweetAlert (10000). */
    .pfo-popover { --bs-popover-max-width: 380px; --bs-popover-font-size: .78rem; --bs-popover-zindex: 9000; z-index: 9000; }
    .pfo-popover ul { padding-left: 1.1rem; margin-bottom: .35rem; }
    .pfo-popover p { margin-bottom: .35rem; }
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
        Producción aún no está configurado en esta empresa. Guarde primero la Configuración de Producción.
    </div>
<?php endif; ?>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <!-- Buscador y Exportación -->
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php
            // Buscador estándar (FiltrosModal), igual que Proveedores: texto libre sobre código,
            // nombre y producto + embudo con todos los filtros + chips de los activos. Las claves
            // (key) deben existir en los mapas de ProduccionFormulaRepository::getListado().
            $tF = 'Fórmula';
            $filtrosFormulas = [
                // ── Fórmula ──
                ['tab' => $tF, 'key' => 'codigo',  'label' => 'Código',  'icon' => 'bi-upc',        'type' => 'text',         'grupo' => 'Fórmula', 'col' => 3],
                ['tab' => $tF, 'key' => 'nombre',  'label' => 'Descripción', 'icon' => 'bi-card-text',  'type' => 'text',         'grupo' => 'Fórmula', 'col' => 3],
                ['tab' => $tF, 'key' => 'estado',  'label' => 'Estado',  'icon' => 'bi-flag',       'type' => 'select',       'grupo' => 'Fórmula', 'col' => 3, 'options' => [
                    ['v' => 'borrador', 'l' => 'Borrador'],
                    ['v' => 'vigente',  'l' => 'Vigente'],
                    ['v' => 'inactiva', 'l' => 'Inactiva'],
                ]],
                ['tab' => $tF, 'key' => 'version', 'label' => 'Versión', 'icon' => 'bi-123',        'type' => 'number_range', 'grupo' => 'Fórmula', 'col' => 3],
                // ── Producto que fabrica ──
                ['tab' => $tF, 'key' => 'producto_codigo', 'label' => 'Código del producto', 'icon' => 'bi-upc-scan', 'type' => 'text',         'grupo' => 'Producto que fabrica', 'col' => 3],
                ['tab' => $tF, 'key' => 'producto',        'label' => 'Producto',            'icon' => 'bi-box-seam', 'type' => 'text',         'grupo' => 'Producto que fabrica', 'col' => 3],
                ['tab' => $tF, 'key' => 'rinde',           'label' => 'Rinde (cantidad base)', 'icon' => 'bi-rulers', 'type' => 'number_range', 'grupo' => 'Producto que fabrica', 'col' => 3],
                ['tab' => $tF, 'key' => 'vida',            'label' => 'Vida útil (días)',    'icon' => 'bi-hourglass-split', 'type' => 'number_range', 'grupo' => 'Producto que fabrica', 'col' => 3],
                // ── Contenido ──
                ['tab' => $tF, 'key' => 'insumo',  'label' => 'Usa el insumo',       'icon' => 'bi-box-arrow-up',     'type' => 'text',         'grupo' => 'Contenido', 'col' => 3],
                ['tab' => $tF, 'key' => 'salida',  'label' => 'Produce (salida)',    'icon' => 'bi-box-arrow-in-down', 'type' => 'text',        'grupo' => 'Contenido', 'col' => 3],
                ['tab' => $tF, 'key' => 'insumos', 'label' => 'N.º de insumos',      'icon' => 'bi-list-ol',          'type' => 'number_range', 'grupo' => 'Contenido', 'col' => 3],
                ['tab' => $tF, 'key' => 'salidas', 'label' => 'N.º de salidas',      'icon' => 'bi-list-ol',          'type' => 'number_range', 'grupo' => 'Contenido', 'col' => 3],
                ['tab' => $tF, 'key' => 'metodo',  'label' => 'Reparto del costo',   'icon' => 'bi-pie-chart',        'type' => 'select',       'grupo' => 'Contenido', 'col' => 6, 'options' => array_merge(
                    [['v' => 'config', 'l' => 'El de la configuración']],
                    array_map(fn($k, $l) => ['v' => (string) $k, 'l' => (string) $l], array_keys($metodos), array_values($metodos))
                )],
                // ── Registro ──
                ['tab' => $tF, 'key' => 'registro',   'label' => 'Fecha de registro',    'icon' => 'bi-calendar-event', 'type' => 'date_range', 'grupo' => 'Registro', 'col' => 4, 'atajos' => true],
                ['tab' => $tF, 'key' => 'modificado', 'label' => 'Última modificación',  'icon' => 'bi-calendar-check', 'type' => 'date_range', 'grupo' => 'Registro', 'col' => 4, 'atajos' => true],
                ['tab' => $tF, 'key' => 'usuario',    'label' => 'Usuario que registró', 'icon' => 'bi-person-gear',    'type' => 'select',     'grupo' => 'Registro', 'col' => 4,
                    'options' => array_map(fn($u) => ['v' => (string) $u['id'], 'l' => (string) $u['nombre']], $usuariosFiltro ?? [])],
            ];
            ?>
            <link rel="stylesheet" href="<?= rtrim($base, '/') ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
            <script src="<?= rtrim($base, '/') ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
            <div id="fmBuscadorPFO"></div>
            <input type="hidden" id="pfo-buscar" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosModal) return;
                    new FiltrosModal({
                        containerId: 'fmBuscadorPFO',
                        hiddenInputId: 'pfo-buscar',
                        placeholder: 'Buscar en todas las columnas...',
                        titulo: 'Filtros de fórmulas',
                        inputWidth: 420,
                        extraId: 'fmExtraPFO', // columnas + PDF + Excel, pegados al final del grupo
                        fields: <?= json_encode($filtrosFormulas, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                        loadingTarget: '#pfo-tbody',
                        onApply: () => window.PFO_buscar && window.PFO_buscar(1),
                    }).init();
                });
            </script>

            <?php // FiltrosModal (extraId) mueve estos botones dentro del input-group del buscador; si el JS no corre, quedan aquí. ?>
            <div id="fmExtraPFO" class="btn-group btn-group-sm">
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
                        <th class="sortable-header" role="button" data-sort="nombre" data-col="nombre">Descripción <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
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

<?php if (!empty($permProductos['crear'])): ?>
    <?php // Crear producto al vuelo (§9): el modal de Productos va como hermano, fuera del de la fórmula. ?>
    <?php include_once MVC_APP . '/views/modulos/productos/modal.php'; ?>
    <script src="<?= rtrim($base, '/') ?>/js/modulos/productos_modal.js?v=<?= asset_ver('/js/modulos/productos_modal.js') ?>"></script>
<?php endif; ?>

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
    // Selector «Unidad» de las líneas: unidades activas (id, id_tipo, nombre, factor_base) y si ya
    // existe la columna produccion_formulas_lineas.id_unidad_medida (database/2026-11-19_…sql).
    window.PFO_UNIDADES = <?= json_encode(array_map(fn($u) => ['id' => (int) $u['id'], 'id_tipo' => (int) $u['id_tipo'], 'nombre' => (string) $u['nombre']], $unidades ?? []), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
    window.PFO_UNIDAD_LINEA = <?= !empty($unidadLinea) ? 'true' : 'false' ?>;
    // Borrador de una fórmula nueva sin guardar (localStorage): uno por empresa y usuario.
    window.PFO_BORRADOR_KEY = 'cmg_pfo_borrador_<?= (int) ($_SESSION['id_empresa'] ?? 0) ?>_<?= (int) ($_SESSION['id_usuario'] ?? 0) ?>';
    window.currentSorts = <?= $ordenJson ?>;
</script>
<script src="<?= rtrim($base, '/') ?>/js/modulos/produccion_formulas.js?v=<?= asset_ver('/js/modulos/produccion_formulas.js') ?>"></script>
