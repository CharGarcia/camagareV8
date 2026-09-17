<?php

/** @var array $perm */
/** @var string $rutaModulo */
/** @var array $rows */
/** @var int $total */
/** @var int $page */
/** @var int $totalPages */
/** @var int $perPage */
/** @var string $buscar */
/** @var string $ordenCol */
/** @var string $ordenDir */
/** @var int $decPrecio */

$decPrecio = $decPrecio ?? 2;
$base = BASE_URL;
$urlBaseProd = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');

$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>

<style>
    .productos-header {
        flex-shrink: 0;
    }

    .productos-scroll {
        max-height: calc(100dvh - 240px);
        overflow-y: auto;
    }

    .productos-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }

    .producto-row {
        cursor: pointer;
    }

    .producto-row:hover {
        background-color: rgba(0, 0, 0, .04);
    }

    .tab-pestaña .nav-link {
        font-size: .85rem;
        padding: .4rem .75rem;
        font-weight: 600;
    }
</style>

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<div class="productos-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-box"></i> Productos y servicios</h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="abrirModalProductoCrear()">
            <i class="bi bi-plus-lg me-1"></i> Nuevo
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php
            // Buscador estándar (FiltrosModal): texto libre sobre las columnas del listado
            // (sin sugerencias) + botón embudo que abre un modal con todos los filtros +
            // chips de los activos. Las claves (key) deben existir en los mapas de
            // ProductoRepository::getListado().
            // Dos pestañas: "Producto" (filtros por campo) y "Detalles" (búsqueda libre
            // dentro de variantes, componentes, precios adicionales y códigos de proveedor;
            // ver `busquedaDetalle` abajo, sin filtros por campo).
            $opcFiltro = $opcionesFiltro ?? [];
            $opcIdNombre = fn(string $k) => array_map(fn($x) => ['v' => (string) $x['id'], 'l' => (string) $x['nombre']], $opcFiltro[$k] ?? []);
            $opcionesSiNo = fn(string $si, string $no) => [['v' => 'si', 'l' => $si], ['v' => 'no', 'l' => $no]];
            $tPr = 'Producto';
            // Filas de 12 columnas:
            //   Producto:       [Código 3][Cód. auxiliar 3][Barras 3][Descripción 3]
            //   Clasificación:  [Tipo 3][Categoría 3][Marca 3][Estado 3]
            //   Inventario:     [Inventariable 4][Medida 4][Ubicación 4]
            //                   [Saldo 4][Stock mínimo 4][Stock máximo 4]
            //                   [Bajo el mínimo 6][Kit 6]
            //   Precios:        [Precio base 4][PVP final 4][Tipo IVA 4]
            //                   [Con ICE 3][Para venta 3][Para compra 3][Precios adicionales 3]
            //   Registro:       [Fecha de registro 6][Usuario 6]
            $filtrosProductos = [
                // ── Producto ──
                ['tab' => $tPr, 'key' => 'codigo',     'label' => 'Código',           'icon' => 'bi-hash',    'type' => 'text', 'grupo' => 'Producto', 'col' => 3],
                ['tab' => $tPr, 'key' => 'codigo_aux', 'label' => 'Código auxiliar',  'icon' => 'bi-tag',     'type' => 'text', 'grupo' => 'Producto', 'col' => 3],
                ['tab' => $tPr, 'key' => 'barras',     'label' => 'Código de barras', 'icon' => 'bi-upc-scan','type' => 'text', 'grupo' => 'Producto', 'col' => 3],
                ['tab' => $tPr, 'key' => 'nombre',     'label' => 'Descripción',      'icon' => 'bi-box',     'type' => 'text', 'grupo' => 'Producto', 'col' => 3],
                // ── Clasificación ──
                ['tab' => $tPr, 'key' => 'tipo',         'label' => 'Tipo',      'icon' => 'bi-grid',        'type' => 'select', 'grupo' => 'Clasificación', 'col' => 3, 'options' => [
                    ['v' => 'bien',     'l' => 'Bien'],
                    ['v' => 'servicio', 'l' => 'Servicio'],
                ]],
                ['tab' => $tPr, 'key' => 'id_categoria', 'label' => 'Categoría', 'icon' => 'bi-folder',      'type' => 'select', 'grupo' => 'Clasificación', 'col' => 3, 'options' => $opcIdNombre('categorias')],
                ['tab' => $tPr, 'key' => 'id_marca',     'label' => 'Marca',     'icon' => 'bi-patch-check', 'type' => 'select', 'grupo' => 'Clasificación', 'col' => 3, 'options' => $opcIdNombre('marcas')],
                ['tab' => $tPr, 'key' => 'estado',       'label' => 'Estado',    'icon' => 'bi-flag',        'type' => 'select', 'grupo' => 'Clasificación', 'col' => 3, 'options' => [
                    ['v' => 'activo',   'l' => 'Activo'],
                    ['v' => 'inactivo', 'l' => 'Inactivo'],
                ]],
                // ── Inventario ──
                ['tab' => $tPr, 'key' => 'inventariable', 'label' => 'Inventariable',    'icon' => 'bi-clipboard-check', 'type' => 'select', 'grupo' => 'Inventario', 'col' => 4, 'options' => [
                    ['v' => 'true',  'l' => 'Sí'],
                    ['v' => 'false', 'l' => 'No'],
                ]],
                ['tab' => $tPr, 'key' => 'id_medida',     'label' => 'Unidad de medida', 'icon' => 'bi-rulers',          'type' => 'select',       'grupo' => 'Inventario', 'col' => 4, 'options' => $opcIdNombre('medidas')],
                ['tab' => $tPr, 'key' => 'ubicacion',     'label' => 'Ubicación',        'icon' => 'bi-geo-alt',         'type' => 'text',         'grupo' => 'Inventario', 'col' => 4],
                ['tab' => $tPr, 'key' => 'stock',         'label' => 'Saldo (todas las bodegas)', 'icon' => 'bi-boxes', 'type' => 'number_range', 'grupo' => 'Inventario', 'col' => 4],
                ['tab' => $tPr, 'key' => 'stock_min',     'label' => 'Stock mínimo',     'icon' => 'bi-arrow-down-circle','type' => 'number_range', 'grupo' => 'Inventario', 'col' => 4],
                ['tab' => $tPr, 'key' => 'stock_max',     'label' => 'Stock máximo',     'icon' => 'bi-arrow-up-circle', 'type' => 'number_range', 'grupo' => 'Inventario', 'col' => 4],
                ['tab' => $tPr, 'key' => 'bajo_minimo',   'label' => 'Saldo bajo el stock mínimo', 'icon' => 'bi-exclamation-triangle', 'type' => 'select', 'grupo' => 'Inventario', 'col' => 6, 'options' => $opcionesSiNo('Bajo el mínimo', 'No está bajo el mínimo')],
                ['tab' => $tPr, 'key' => 'kit',           'label' => 'Kit (con componentes)', 'icon' => 'bi-diagram-3',   'type' => 'select', 'grupo' => 'Inventario', 'col' => 6, 'options' => $opcionesSiNo('Con componentes', 'Sin componentes')],
                // ── Precios e impuestos ──
                ['tab' => $tPr, 'key' => 'precio',        'label' => 'Precio base',      'icon' => 'bi-currency-dollar', 'type' => 'number_range', 'grupo' => 'Precios e impuestos', 'col' => 4],
                ['tab' => $tPr, 'key' => 'pvp',           'label' => 'PVP final',        'icon' => 'bi-cash',            'type' => 'number_range', 'grupo' => 'Precios e impuestos', 'col' => 4],
                ['tab' => $tPr, 'key' => 'id_tarifa_iva', 'label' => 'Tipo de IVA',      'icon' => 'bi-percent',         'type' => 'select',       'grupo' => 'Precios e impuestos', 'col' => 4, 'options' => $opcIdNombre('tarifas_iva')],
                ['tab' => $tPr, 'key' => 'con_ice',       'label' => 'ICE',              'icon' => 'bi-droplet',         'type' => 'select', 'grupo' => 'Precios e impuestos', 'col' => 3, 'options' => $opcionesSiNo('Con ICE', 'Sin ICE')],
                ['tab' => $tPr, 'key' => 'para_venta',    'label' => 'Se vende',         'icon' => 'bi-cart',            'type' => 'select', 'grupo' => 'Precios e impuestos', 'col' => 3, 'options' => $opcionesSiNo('Sí', 'No')],
                ['tab' => $tPr, 'key' => 'para_compra',   'label' => 'Se compra',        'icon' => 'bi-bag',             'type' => 'select', 'grupo' => 'Precios e impuestos', 'col' => 3, 'options' => $opcionesSiNo('Sí', 'No')],
                ['tab' => $tPr, 'key' => 'con_precios',   'label' => 'Precios adicionales', 'icon' => 'bi-tags',         'type' => 'select', 'grupo' => 'Precios e impuestos', 'col' => 3, 'options' => $opcionesSiNo('Con precios adicionales', 'Sin precios adicionales')],
                // ── Registro ──
                ['tab' => $tPr, 'key' => 'registro', 'label' => 'Fecha de registro',    'icon' => 'bi-calendar-event', 'type' => 'date_range', 'grupo' => 'Registro', 'col' => 6, 'atajos' => true],
                ['tab' => $tPr, 'key' => 'usuario',  'label' => 'Usuario que registró', 'icon' => 'bi-person-gear',    'type' => 'select',     'grupo' => 'Registro', 'col' => 6, 'options' => $opcIdNombre('usuarios')],
            ];
            ?>
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
            <div id="fmBuscadorPROD"></div>
            <input type="hidden" id="buscarProducto" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosModal) return;
                    new FiltrosModal({
                        containerId: 'fmBuscadorPROD',
                        hiddenInputId: 'buscarProducto',
                        placeholder: 'Buscar en todas las columnas...',
                        titulo: 'Filtros de productos',
                        inputWidth: 420,
                        extraId: 'fmExtraPROD',   // columnas + PDF + Excel + acciones, pegados al final del grupo
                        // Pestaña Detalles: búsqueda libre dentro de los productos (variantes,
                        // componentes de kits, precios adicionales y códigos de proveedor).
                        busquedaDetalle: {
                            tab: 'Detalles',
                            url: `<?= BASE_URL ?>/<?= $rutaModulo ?>/buscarDetallesAjax`,
                            label: 'Buscar libremente dentro de los productos',
                            placeholder: 'Variante (talla, color...), componente de un kit, lista de precios, código o proveedor...',
                            columns: [
                                { key: 'origen',  label: 'Tipo' },
                                { key: 'detalle', label: 'Detalle' },
                                { key: 'valor',   label: 'Valor / Código' },
                                { key: 'monto',   label: 'Precio', align: 'end' },
                                { key: 'codigo',  label: 'Código', class: 'font-monospace fw-semibold' },
                                { key: 'nombre',  label: 'Producto' },
                                { key: 'estado',  label: 'Estado' },
                            ],
                            onSelect: (row, fm) => fm.aplicarFiltro({ key: 'codigo', value: row.codigo }),
                            onOpen: (row, fm) => {
                                fm.hide();
                                if (row.fila && typeof window.abrirModalProductoEditar === 'function') {
                                    setTimeout(() => window.abrirModalProductoEditar(row.fila), 350);
                                } else {
                                    fm.aplicarFiltro({ key: 'codigo', value: row.codigo });
                                }
                            },
                        },
                        fields: <?= json_encode($filtrosProductos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                        loadingTarget: '#tbodyProductos',   // se atenúa mientras se busca
                        onApply: () => window.fetchSearch && window.fetchSearch(1),
                    }).init();
                });
            </script>

            <?php // FiltrosModal (extraId) mueve estos botones dentro del input-group del buscador; si el JS no corre, quedan aquí. ?>
            <div id="fmExtraPROD" class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'codigo' => 'Código',
                    'codigo_auxiliar' => 'Cód. Aux.',
                    'codigo_barras' => 'Barras',
                    'nombre' => 'Descripción',
                    'tipo_produccion' => 'Tipo',
                    'nombre_categoria' => 'Categoría',
                    'nombre_marca' => 'Marca',
                    'nombre_medida' => 'Medida',
                    'ubicacion' => 'Ubicación',
                    'precio_base' => 'Precio Base',
                    'nombre_tarifa_iva' => 'Tipo IVA',
                    'valor_iva' => 'Valor IVA',
                    'valor_ice' => 'ICE',
                    'pvp' => 'PVP Final',
                    'inventariable' => 'Inv.',
                    'stock_minimo' => 'Mín.',
                    'stock_maximo' => 'Máx.',
                    'saldo_actual' => 'Saldo',
                    'status' => 'Estado'
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>
                <a id="btnExportPdf" href="<?= $urlBaseProd ?>/export-pdf?b=<?= urlencode($buscar) ?>&orden=<?= urlencode($ordenParam ?? '') ?>" class="btn btn-outline-danger" title="Descargar PDF"><i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span></a>
                <a id="btnExportExcel" href="<?= $urlBaseProd ?>/export-excel?b=<?= urlencode($buscar) ?>&orden=<?= urlencode($ordenParam ?? '') ?>" class="btn btn-outline-success" title="Descargar Excel"><i class="bi bi-file-earmark-spreadsheet"></i><span class="d-none d-md-inline"> Excel</span></a>
                <?php if (!empty($perm['actualizar'])): ?>
                    <button type="button" class="btn btn-outline-primary" onclick="actualizarCostosMasivo()" title="Recalcula el costo de todos los productos inventariables desde el Kardex">
                        <i class="bi bi-arrow-repeat"></i> Actualizar Costos
                    </button>
                <?php endif; ?>
                <?php if ($perm['crear']): ?>
                    <button type="button" class="btn btn-outline-primary d-none" id="btnCopiarProductosEmpresa" title="Copiar todos los productos a otra empresa" onclick="abrirModalCopiarProductosEmpresa()">
                        <i class="bi bi-arrow-left-right"></i> Copiar a otra empresa
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?> / <?= $total ?></span>
            <div id="paginationContainer" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(<?= $page - 1 ?>)" <?= $page <= 1 ? 'disabled' : '' ?>><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(<?= $page + 1 ?>)" <?= $page >= $totalPages ? 'disabled' : '' ?>><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="productos-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" data-sort="codigo" role="button" data-col="codigo">Código <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="codigo_auxiliar" role="button" data-col="codigo_auxiliar">Cód. Aux. <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="codigo_barras" role="button" data-col="codigo_barras">Barras <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="nombre" role="button" data-col="nombre">Descripción <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" data-sort="tipo_produccion" role="button" data-col="tipo_produccion">Tipo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="nombre_categoria" role="button" data-col="nombre_categoria">Categoría <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="nombre_marca" role="button" data-col="nombre_marca">Marca <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="nombre_medida" role="button" data-col="nombre_medida">Medida <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="ubicacion" role="button" data-col="ubicacion">Ubicación <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header" data-sort="precio_base" role="button" data-col="precio_base">P. Base <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" data-sort="nombre_tarifa_iva" role="button" data-col="nombre_tarifa_iva">Tipo IVA <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header" data-sort="valor_iva" role="button" data-col="valor_iva">Val. IVA <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header" data-sort="valor_ice" role="button" data-col="valor_ice">ICE <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header" data-sort="pvp" role="button" data-col="pvp">PVP Final <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" data-sort="inventariable" role="button" data-col="inventariable">Inv. <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header" data-sort="stock_minimo" role="button" data-col="stock_minimo">Mín. <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header" data-sort="stock_maximo" role="button" data-col="stock_maximo">Máx. <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header" data-sort="saldo_actual" role="button" data-col="saldo_actual" title="Saldo actual sumando todas las bodegas">Saldo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center pe-3 sortable-header" data-sort="status" role="button" data-col="status">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyProductos">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="19" class="text-center py-5 text-muted">No se encontraron productos.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <tr class="producto-row" role="button" data-row='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>' onclick="abrirModalProductoEditar(this)">
                                <td class="ps-3 fw-bold" data-col="codigo"><?= htmlspecialchars((string)($r['codigo'] ?? '')) ?></td>
                                <td data-col="codigo_auxiliar"><?= htmlspecialchars((string)($r['codigo_auxiliar'] ?? '-')) ?></td>
                                <td data-col="codigo_barras"><?= htmlspecialchars((string)($r['codigo_barras'] ?? '-')) ?></td>
                                <td data-col="nombre" class="text-wrap" style="max-width:300px"><span class="fw-medium"><?= htmlspecialchars((string)($r['nombre'] ?? '')) ?></span></td>
                                <td class="text-center" data-col="tipo_produccion">
                                    <span class="badge rounded-pill bg-light text-dark border small"><i class="bi bi-<?= ($r['tipo_produccion'] ?? '01') == '01' ? 'box text-primary' : 'gear-wide-connected text-info' ?> me-1"></i> <?= ($r['tipo_produccion'] ?? '01') == '01' ? 'Bien' : 'Servicio' ?></span>
                                </td>
                                <td data-col="nombre_categoria"><?= htmlspecialchars((string)($r['nombre_categoria'] ?? '-')) ?></td>
                                <td data-col="nombre_marca"><?= htmlspecialchars((string)($r['nombre_marca'] ?? '-')) ?></td>
                                <td data-col="nombre_medida"><?= htmlspecialchars((string)($r['nombre_medida'] ?? '-')) ?></td>
                                <td data-col="ubicacion"><?= htmlspecialchars((string)($r['ubicacion'] ?? '-')) ?></td>
                                <td class="text-end fw-medium" data-col="precio_base">$<?= number_format((float)($r['precio_base'] ?? 0), $decPrecio) ?></td>
                                <td class="text-center" data-col="nombre_tarifa_iva"><span class="small"><?= htmlspecialchars((string)($r['nombre_tarifa_iva'] ?? '-')) ?></span></td>
                                <td class="text-end text-muted" data-col="valor_iva">$<?= number_format((float)($r['valor_iva'] ?? 0), $decPrecio) ?></td>
                                <td class="text-end text-muted" data-col="valor_ice">$<?= number_format((float)($r['valor_ice'] ?? 0), $decPrecio) ?></td>
                                <td class="text-end fw-bold text-primary" data-col="pvp">$<?= number_format((float)($r['pvp'] ?? 0), $decPrecio) ?></td>
                                <td class="text-center" data-col="inventariable"><?= ($r['inventariable'] ?? false) ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>' ?></td>
                                <td class="text-end" data-col="stock_minimo"><?= number_format((float)($r['stock_minimo'] ?? 0), 2) ?></td>
                                <td class="text-end" data-col="stock_maximo"><?= number_format((float)($r['stock_maximo'] ?? 0), 2) ?></td>
                                <?php
                                    // Saldo solo para productos que manejan inventario. Los servicios
                                    // y los productos no inventariables no tienen existencias.
                                    $esInventariable = in_array((string)($r['inventariable'] ?? ''), ['1', 't', 'true'], true);
                                    $saldo = (float)($r['saldo_actual'] ?? 0);
                                    $abrevMedida = trim((string)($r['abreviatura_medida'] ?? ''));
                                ?>
                                <td class="text-end fw-medium <?= $saldo < 0 ? 'text-danger' : '' ?>" data-col="saldo_actual">
                                    <?php if ($esInventariable): ?>
                                        <?= number_format($saldo, 2) ?><?php if ($abrevMedida !== ''): ?> <span class="text-muted small"><?= htmlspecialchars($abrevMedida) ?></span><?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center pe-3" data-col="status">
                                    <span class="badge bg-<?= ($r['status'] ?? 1) == 1 ? 'success' : 'danger' ?> bg-opacity-10 text-<?= ($r['status'] ?? 1) == 1 ? 'success' : 'danger' ?> border border-<?= ($r['status'] ?? 1) == 1 ? 'success' : 'danger' ?> border-opacity-10"><?= ($r['status'] ?? 1) == 1 ? 'Activo' : 'Inactivo' ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    window.BASE_URL = '<?= $base ?>';
    window.PROD_DEC_PRECIO = <?= $decPrecio ?>;
</script>
<?php include 'modal.php'; ?>

<!-- Modal: Copiar todos los productos a otra empresa -->
<div class="modal fade" id="modalCopiarProductosEmpresa" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-arrow-left-right text-primary me-2"></i>Copiar productos a otra empresa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-primary bg-primary bg-opacity-10 py-2 px-3 small border-primary border-opacity-25 mb-3">
                    <i class="bi bi-info-circle-fill me-1"></i>
                    Copia todos los productos activos de esta empresa hacia la empresa que elija. Si un producto ya existe allí (mismo código), no se duplica ni se sobrescribe.
                </div>
                <label for="copiarProductosEmpresaSelect" class="form-label small fw-bold">Empresa destino</label>
                <select class="form-select form-select-sm" id="copiarProductosEmpresaSelect"></select>
            </div>
            <div class="modal-footer bg-light border-top p-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm px-3" id="btnConfirmarCopiarProductos" onclick="confirmarCopiarProductosEmpresa()">
                    <i class="bi bi-arrow-left-right me-1"></i> Copiar
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $base ?>/js/modulos/productos_modal.js?v=<?= asset_ver('/js/modulos/productos_modal.js') ?>"></script>
<script src="<?= $base ?>/js/modulos/categorias_modal.js?v=<?= asset_ver('/js/modulos/categorias_modal.js') ?>"></script>
<script src="<?= $base ?>/js/modulos/marcas_modal.js?v=<?= asset_ver('/js/modulos/marcas_modal.js') ?>"></script>

<script>
    (function() {
        'use strict';
        const urlBase = '<?= $urlBaseProd ?>';
        const inputBuscar = document.getElementById('buscarProducto');

        // Nombre del producto: no permitir Enter (evita saltos de línea) y, al salir
        // del campo, colapsar espacios dobles y recortar los extremos.
        const inputProdNombre = document.getElementById('prod_nombre');
        if (inputProdNombre) {
            inputProdNombre.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') e.preventDefault();
            });
            inputProdNombre.addEventListener('blur', () => {
                inputProdNombre.value = inputProdNombre.value.replace(/\s+/g, ' ').trim();
            });
        }
        window.currentSort = '<?= $ordenCol ?>';
        window.currentDir = '<?= $ordenDir ?>';
        // Orden múltiple (Shift+clic): lista completa de criterios, en el formato que
        // lee OrdenListado en PHP. currentSort/currentDir quedan como el principal.
        window.currentSorts = <?= $ordenJson ?? '[]' ?>;
        window.currentPage = <?= $page ?>;
        let sorter = null;
        let timerId;

        const debounce = (func, delay = 400) => (...args) => {
            clearTimeout(timerId);
            timerId = setTimeout(() => func.apply(this, args), delay);
        };

        window.cambiarPaginaAjax = (n) => window.fetchSearch(n);

        window.fetchSearch = async (page = 1) => {
            const term = inputBuscar ? inputBuscar.value.trim() : '';
            const orden = window.CMG_ordenParam(window.currentSorts || []);
            const uri = `${urlBase}/searchAjax?b=${encodeURIComponent(term)}&page=${page}&orden=${encodeURIComponent(orden)}`;
            // Mismo indicador que el buscador (FiltrosModal): la tabla se atenúa mientras
            // carga, también al paginar u ordenar (que llaman a esta función directo).
            const tbody = document.getElementById('tbodyProductos');
            if (tbody) tbody.classList.add('fm-cargando-target');
            // Solo vale la ÚLTIMA búsqueda: si llega otra (se sigue tecleando, se pagina)
            // se cancela la anterior, y una respuesta vieja nunca pinta sobre una nueva.
            if (window.PROD_busquedaCtrl) window.PROD_busquedaCtrl.abort();
            const ctrl = new AbortController();
            window.PROD_busquedaCtrl = ctrl;
            try {
                const resp = await fetch(uri, { signal: ctrl.signal });
                const data = await resp.json();
                if (ctrl !== window.PROD_busquedaCtrl) return; // llegó tarde
                if (data.ok) {
                    window.currentPage = page;
                    document.getElementById('tbodyProductos').innerHTML = data.rows;
                    document.getElementById('paginationContainer').innerHTML = data.pagination;
                    document.getElementById('paginationInfo').textContent = data.info;
                    document.getElementById('btnExportPdf').href = data.pdf_url;
                    document.getElementById('btnExportExcel').href = data.excel_url;

                    // Los íconos (incluida la prioridad 1/2/3 del orden múltiple) los
                    // repinta el motor global; aquí solo se le pide que se refresque.
                    if (sorter) sorter.refreshIcons();
                }
            } catch (e) {
                if (e.name === 'AbortError') return;  // la canceló una búsqueda más nueva
                console.error(e);
            } finally {
                if (tbody && ctrl === window.PROD_busquedaCtrl) tbody.classList.remove('fm-cargando-target');
            }
        };

        // Ordenamiento: motor global (window.CMG_initSort, en public/js/favoritos.js).
        // multi: clic normal ordena por una columna; Shift+clic encadena hasta 3
        // (ASC → DESC → fuera del orden), con la prioridad numerada en cada encabezado.
        // reload:false porque fetchSearch repinta todo lo que depende del orden.
        sorter = window.CMG_initSort('<?= basename($rutaModulo) ?>', (col, dir, sorts) => {
            window.currentSort  = col;
            window.currentDir   = dir;
            window.currentSorts = sorts;
            fetchSearch(1);
        }, { sorts: window.currentSorts, multi: true, container: '.productos-scroll', reload: false });

        if (inputBuscar) inputBuscar.addEventListener('input', debounce(() => fetchSearch(1), 400));
    })();
</script>