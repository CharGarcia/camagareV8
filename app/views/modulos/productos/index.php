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
        max-height: calc(100vh - 240px);
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
        <div class="d-flex align-items-center gap-2">
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_busqueda.css?v=<?= time() ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_busqueda.js?v=<?= time() ?>"></script>
            <div id="fbBuscadorPROD" style="width: 480px;"></div>
            <input type="hidden" id="buscarProducto" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosBusqueda) return;
                    new FiltrosBusqueda({
                        containerId: 'fbBuscadorPROD',
                        hiddenInputId: 'buscarProducto',
                        fields: [
                            { key: 'nombre',       label: 'Nombre',           icon: 'bi-box',             type: 'text' },
                            { key: 'codigo',       label: 'Código',           icon: 'bi-hash',            type: 'text' },
                            { key: 'codigo_aux',   label: 'Código auxiliar',  icon: 'bi-tag',             type: 'text' },
                            { key: 'barras',       label: 'Código barras',    icon: 'bi-upc-scan',        type: 'text' },
                            { key: 'categoria',    label: 'Categoría',        icon: 'bi-folder',          type: 'text' },
                            { key: 'marca',        label: 'Marca',            icon: 'bi-patch-check',     type: 'text' },
                            { key: 'medida',       label: 'Unidad de medida', icon: 'bi-rulers',          type: 'text' },
                            { key: 'precio',       label: 'Precio base',      icon: 'bi-currency-dollar', type: 'number_range' },
                            { key: 'stock',        label: 'Stock',            icon: 'bi-boxes',           type: 'number_range' },
                            { key: 'stock_min',    label: 'Stock mínimo',     icon: 'bi-arrow-down-circle', type: 'number_range' },
                            { key: 'stock_max',    label: 'Stock máximo',     icon: 'bi-arrow-up-circle', type: 'number_range' },
                            { key: 'tipo',         label: 'Tipo',             icon: 'bi-grid',            type: 'select', options: [
                                { v: 'bien',     l: 'Bien' },
                                { v: 'servicio', l: 'Servicio' },
                            ]},
                            { key: 'inventariable', label: 'Inventariable',   icon: 'bi-clipboard-check', type: 'select', options: [
                                { v: 'true',  l: 'Sí' },
                                { v: 'false', l: 'No' },
                            ]},
                            { key: 'estado',       label: 'Estado',           icon: 'bi-flag',            type: 'select', options: [
                                { v: 'activo',   l: 'Activo' },
                                { v: 'inactivo', l: 'Inactivo' },
                            ]},
                        ],
                        quickFilters: [
                            { id: 'qf_activo',         label: 'Activos',         mk: () => ({ key: 'estado',        op: '=',  value: 'activo',   display: 'Activo' }) },
                            { id: 'qf_inactivo',       label: 'Inactivos',       mk: () => ({ key: 'estado',        op: '=',  value: 'inactivo', display: 'Inactivo' }) },
                            { id: 'qf_sin_stock',      label: 'Sin stock',       mk: () => ({ key: 'stock',         op: '<=', value: '0',        display: '≤ 0' }) },
                            { id: 'qf_inventariable',  label: 'Inventariables',  mk: () => ({ key: 'inventariable', op: '=',  value: 'true',     display: 'Sí' }) },
                            { id: 'qf_no_inventariable', label: 'No inventariables', mk: () => ({ key: 'inventariable', op: '=', value: 'false', display: 'No' }) },
                        ],
                        onApply: () => window.fetchSearch && window.fetchSearch(1),
                    }).init();
                });
            </script>

            <div class="btn-group btn-group-sm">
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
                    'precio_base' => 'Precio Base',
                    'nombre_tarifa_iva' => 'Tipo IVA',
                    'valor_iva' => 'Valor IVA',
                    'valor_ice' => 'ICE',
                    'pvp' => 'PVP Final',
                    'inventariable' => 'Inv.',
                    'stock_minimo' => 'Mín.',
                    'stock_maximo' => 'Máx.',
                    'status' => 'Estado'
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>
                <a id="btnExportPdf" href="<?= $urlBaseProd ?>/export-pdf?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>" class="btn btn-outline-danger" title="PDF"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                <a id="btnExportExcel" href="<?= $urlBaseProd ?>/export-excel?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>" class="btn btn-outline-success" title="Excel"><i class="bi bi-file-earmark-spreadsheet"></i> Excel</a>
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
                        <th class="text-end sortable-header" data-sort="precio_base" role="button" data-col="precio_base">P. Base <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" data-sort="nombre_tarifa_iva" role="button" data-col="nombre_tarifa_iva">Tipo IVA <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header" data-sort="valor_iva" role="button" data-col="valor_iva">Val. IVA <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header" data-sort="valor_ice" role="button" data-col="valor_ice">ICE <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header" data-sort="pvp" role="button" data-col="pvp">PVP Final <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" data-sort="inventariable" role="button" data-col="inventariable">Inv. <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header" data-sort="stock_minimo" role="button" data-col="stock_minimo">Mín. <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header" data-sort="stock_maximo" role="button" data-col="stock_maximo">Máx. <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center pe-3 sortable-header" data-sort="status" role="button" data-col="status">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyProductos">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="17" class="text-center py-5 text-muted">No se encontraron productos.</td>
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
                                <td class="text-end fw-medium" data-col="precio_base">$<?= number_format((float)($r['precio_base'] ?? 0), 2) ?></td>
                                <td class="text-center" data-col="nombre_tarifa_iva"><span class="small"><?= htmlspecialchars((string)($r['nombre_tarifa_iva'] ?? '-')) ?></span></td>
                                <td class="text-end text-muted" data-col="valor_iva">$<?= number_format((float)($r['valor_iva'] ?? 0), 2) ?></td>
                                <td class="text-end text-muted" data-col="valor_ice">$<?= number_format((float)($r['valor_ice'] ?? 0), 2) ?></td>
                                <td class="text-end fw-bold text-primary" data-col="pvp">$<?= number_format((float)($r['pvp'] ?? 0), 2) ?></td>
                                <td class="text-center" data-col="inventariable"><?= ($r['inventariable'] ?? false) ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>' ?></td>
                                <td class="text-end" data-col="stock_minimo"><?= number_format((float)($r['stock_minimo'] ?? 0), 2) ?></td>
                                <td class="text-end" data-col="stock_maximo"><?= number_format((float)($r['stock_maximo'] ?? 0), 2) ?></td>
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
</script>
<?php include 'modal.php'; ?>
<script src="<?= $base ?>/js/modulos/productos_modal.js?v=<?= time() ?>"></script>
<script src="<?= $base ?>/js/modulos/categorias_modal.js?v=<?= time() ?>"></script>
<script src="<?= $base ?>/js/modulos/marcas_modal.js?v=<?= time() ?>"></script>

<script>
    (function() {
        'use strict';
        const urlBase = '<?= $urlBaseProd ?>';
        const inputBuscar = document.getElementById('buscarProducto');
        window.currentSort = '<?= $ordenCol ?>';
        window.currentDir = '<?= $ordenDir ?>';
        window.currentPage = <?= $page ?>;
        let timerId;

        const debounce = (func, delay = 400) => (...args) => {
            clearTimeout(timerId);
            timerId = setTimeout(() => func.apply(this, args), delay);
        };

        window.cambiarPaginaAjax = (n) => window.fetchSearch(n);

        window.fetchSearch = async (page = 1) => {
            const term = inputBuscar ? inputBuscar.value.trim() : '';
            const uri = `${urlBase}/searchAjax?b=${encodeURIComponent(term)}&page=${page}&sort=${window.currentSort}&dir=${window.currentDir}`;
            try {
                const resp = await fetch(uri);
                const data = await resp.json();
                if (data.ok) {
                    window.currentPage = page;
                    document.getElementById('tbodyProductos').innerHTML = data.rows;
                    document.getElementById('paginationContainer').innerHTML = data.pagination;
                    document.getElementById('paginationInfo').textContent = data.info;
                    document.getElementById('btnExportPdf').href = data.pdf_url;
                    document.getElementById('btnExportExcel').href = data.excel_url;

                    document.querySelectorAll('.sortable-header').forEach(th => {
                        const icon = th.querySelector('i');
                        if (!icon) return;
                        const field = th.dataset.sort;
                        if (field === window.currentSort) {
                            icon.className = (window.currentDir.toLowerCase() === 'asc') ? 'bi bi-sort-alpha-down text-primary ms-1' : 'bi bi-sort-alpha-up text-primary ms-1';
                        } else {
                            icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
                        }
                    });
                }
            } catch (e) {
                console.error(e);
            }
        };

        document.querySelectorAll('.sortable-header').forEach(h => {
            h.addEventListener('click', () => {
                const f = h.dataset.sort;
                if (window.currentSort === f) window.currentDir = (window.currentDir.toLowerCase() === 'asc') ? 'DESC' : 'ASC';
                else {
                    window.currentSort = f;
                    window.currentDir = 'ASC';
                }
                if (typeof window.guardarOrdenacionVista === 'function') window.guardarOrdenacionVista('<?= basename($rutaModulo) ?>', window.currentSort, window.currentDir);
                fetchSearch(1);
            });
        });

        if (inputBuscar) inputBuscar.addEventListener('input', debounce(() => fetchSearch(1), 400));
    })();
</script>