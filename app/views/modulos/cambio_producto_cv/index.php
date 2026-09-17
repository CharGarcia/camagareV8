<?php
/** @var string $titulo */
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
/** @var array $vistaConfig */
/** @var array $puntos */
/** @var int $decCant */

$base = BASE_URL;
$urlBaseCam = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');

$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>

<style>
    .cam-header { flex-shrink: 0; }
    .cambios-scroll {
        max-height: calc(100dvh - 240px);
        overflow-y: auto;
    }
    .cambios-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }
    .cambio-row { cursor: pointer; }
    .cambio-row:hover { background-color: rgba(0, 0, 0, .04); }

    /* Listado de dos lados: fecha (celeste), lo que ENTRA (izquierda, rojo) y lo que SALE (derecha, verde). */
    .cambios-scroll thead th.cam-th-fecha { --bs-table-bg: #4fc3f7; --bs-table-color: #0d3c55; background-color: #4fc3f7; color: #0d3c55; }
    .cambios-scroll thead th.cam-th-entra { --bs-table-bg: #dc3545; --bs-table-color: #fff; background-color: #dc3545; color: #fff; }
    .cambios-scroll thead th.cam-th-sale  { --bs-table-bg: #198754; --bs-table-color: #fff; background-color: #198754; color: #fff; }
    #tablaCambios .cam-lado-sale { border-left: 2px solid #adb5bd; }
    #tablaCambios td { vertical-align: middle; }
    #tablaCambios .cam-producto { max-width: 280px; }
    /* Sin columna Estado: un cambio anulado va tachado y uno en borrador, atenuado. */
    #tablaCambios .cam-fila-anulada > td { color: #adb5bd; text-decoration: line-through; }
    #tablaCambios .cam-fila-borrador > td { color: #6c757d; font-style: italic; }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>

<!-- Encabezado -->
<div class="cam-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-arrow-left-right"></i> <?= htmlspecialchars($titulo) ?></h5>
    <div class="d-flex gap-2">
        <?php if (!empty($perm['crear']) || !empty($perm['todo'])): ?>
            <button type="button" class="btn btn-primary btn-sm px-3" onclick="abrirModalCambioNuevo()">
                <i class="bi bi-plus-lg"></i> Nuevo
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php
            // Buscador: texto libre sobre las columnas del listado (sin sugerencias) + botón
            // embudo que abre un modal con todos los filtros + chips de los activos.
            // Las claves (key) deben existir en los mapas de CambioProductoCvRepository::getListado().
            $opcionesSerie       = array_map(fn($s) => ['v' => $s['establecimiento'] . '-' . $s['punto_emision'], 'l' => $s['establecimiento'] . '-' . $s['punto_emision']], $seriesFiltro ?? []);
            $opcionesResponsable = array_map(fn($r) => ['v' => (string) $r['id'], 'l' => $r['nombre']], $opcionesFiltros['responsables'] ?? []);
            $opcionesUsuario     = array_map(fn($u) => ['v' => (string) $u['id'], 'l' => $u['nombre']], $opcionesFiltros['usuarios'] ?? []);
            // Dos pestañas: "Cambio" (filtros por campo) y "Detalles" (solo la búsqueda libre
            // dentro de los cambios, ver `busquedaDetalle` abajo).
            $tC = 'Cambio';
            // Orden pensado en filas de 12 columnas:
            //   Documento: [Fecha 6][Estado 3][Serie 3]
            //              [Nº cambio 3][Secuencial 3][Documento de origen 3][Asiento 3]
            //              [Responsable 6][Usuario 6]
            //   Valores:   [Diferencia 4][Subtotal devuelto 4][Subtotal entregado 4]
            //   Cliente:   [Cliente 4][RUC 4][Motivo 4]
            //              [Observaciones 12]
            $filtrosCambios = [
                // ── Documento ──
                ['tab' => $tC, 'key' => 'fecha',              'label' => 'Fecha del cambio',     'icon' => 'bi-calendar-event',  'type' => 'date_range',   'grupo' => 'Documento', 'col' => 6, 'atajos' => true],
                ['tab' => $tC, 'key' => 'estado',             'label' => 'Estado',               'icon' => 'bi-flag',            'type' => 'select',       'grupo' => 'Documento', 'col' => 3, 'options' => [
                    ['v' => 'Borrador', 'l' => 'Borrador'],
                    ['v' => 'Emitida',  'l' => 'Emitida'],
                    ['v' => 'Anulada',  'l' => 'Anulada'],
                ]],
                ['tab' => $tC, 'key' => 'serie',              'label' => 'Serie',                'icon' => 'bi-upc-scan',        'type' => 'select',       'grupo' => 'Documento', 'col' => 3, 'options' => $opcionesSerie],
                ['tab' => $tC, 'key' => 'numero',             'label' => 'Nº cambio',            'icon' => 'bi-hash',            'type' => 'text',         'grupo' => 'Documento', 'col' => 3, 'placeholder' => '001-001-000000123'],
                ['tab' => $tC, 'key' => 'secuencial',         'label' => 'Secuencial',           'icon' => 'bi-123',             'type' => 'text',         'grupo' => 'Documento', 'col' => 3, 'placeholder' => 'Sin ceros'],
                ['tab' => $tC, 'key' => 'documento_origen',   'label' => 'Documento de origen',  'icon' => 'bi-link-45deg',      'type' => 'text',         'grupo' => 'Documento', 'col' => 3, 'placeholder' => '001-001-000000123'],
                ['tab' => $tC, 'key' => 'asiento',            'label' => 'Asiento contable',     'icon' => 'bi-journal-check',   'type' => 'select',       'grupo' => 'Documento', 'col' => 3, 'options' => [
                    ['v' => 'si', 'l' => 'Con asiento'],
                    ['v' => 'no', 'l' => 'Sin asiento'],
                ]],
                ['tab' => $tC, 'key' => 'id_responsable',     'label' => 'Responsable de traslado', 'icon' => 'bi-truck',        'type' => 'select',       'grupo' => 'Documento', 'col' => 6, 'options' => $opcionesResponsable],
                ['tab' => $tC, 'key' => 'id_usuario',         'label' => 'Usuario que registró', 'icon' => 'bi-person-gear',     'type' => 'select',       'grupo' => 'Documento', 'col' => 6, 'options' => $opcionesUsuario],
                // ── Valores ──
                ['tab' => $tC, 'key' => 'diferencia',         'label' => 'Diferencia',           'icon' => 'bi-plus-slash-minus','type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                ['tab' => $tC, 'key' => 'subtotal_devuelto',  'label' => 'Subtotal devuelto',    'icon' => 'bi-box-arrow-in-left', 'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                ['tab' => $tC, 'key' => 'subtotal_entregado', 'label' => 'Subtotal entregado',   'icon' => 'bi-box-arrow-right', 'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                // ── Cliente ──
                ['tab' => $tC, 'key' => 'cliente',            'label' => 'Cliente',              'icon' => 'bi-person',          'type' => 'text',         'grupo' => 'Cliente', 'col' => 4],
                ['tab' => $tC, 'key' => 'ruc',                'label' => 'RUC / Cédula',         'icon' => 'bi-card-text',       'type' => 'text',         'grupo' => 'Cliente', 'col' => 4],
                ['tab' => $tC, 'key' => 'motivo',             'label' => 'Motivo',               'icon' => 'bi-chat',            'type' => 'text',         'grupo' => 'Cliente', 'col' => 4],
                ['tab' => $tC, 'key' => 'observaciones',      'label' => 'Observaciones',        'icon' => 'bi-chat-left-text',  'type' => 'text',         'grupo' => 'Cliente', 'col' => 12],
            ];
            ?>
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
            <div id="fmBuscadorCAM"></div>
            <input type="hidden" id="b" name="b" value="<?= htmlspecialchars($buscar) ?>">

            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosModal) return;
                    new FiltrosModal({
                        containerId: 'fmBuscadorCAM',
                        hiddenInputId: 'b',
                        placeholder: 'Buscar en todas las columnas...',
                        titulo: 'Filtros de cambios de productos',
                        inputWidth: 420,
                        extraId: 'fmExtraCAM',   // columnas + PDF + Excel, pegados al final del grupo
                        // Pestaña Detalles: búsqueda libre dentro de los cambios (líneas devueltas y
                        // entregadas con lote/NUP, bodega y documento de origen).
                        busquedaDetalle: {
                            tab: 'Detalles',
                            url: `<?= $urlBaseCam ?>/buscarDetallesAjax`,
                            label: 'Buscar libremente dentro de los cambios',
                            placeholder: 'Producto, código, lote, NUP, bodega, factura de venta o documento de origen...',
                            columns: [
                                { key: 'origen',      label: 'Línea' },
                                { key: 'tipo',        label: 'Código' },
                                { key: 'descripcion', label: 'Producto' },
                                { key: 'extra',       label: 'Lote / NUP / Caducidad', class: 'font-monospace' },
                                { key: 'bodega',      label: 'Bodega' },
                                { key: 'documento',   label: 'Documento de origen' },
                                { key: 'cantidad',    label: 'Cant.', align: 'end' },
                                { key: 'monto',       label: 'Valor', align: 'end' },
                                { key: 'numero',      label: 'Cambio', class: 'font-monospace fw-semibold' },
                                { key: 'fecha',       label: 'Fecha' },
                                { key: 'cliente',     label: 'Cliente' },
                                { key: 'estado',      label: 'Estado' },
                            ],
                            onSelect: (row, fm) => fm.aplicarFiltro({ key: 'numero', value: row.numero }),
                            onOpen: (row, fm) => {
                                fm.hide();
                                // abrirModalCambioVer lee el cambio del data-row de la fila.
                                const datos = JSON.stringify({ id: row.id, estado: row.estado, serie: row.serie, secuencial: row.secuencial });
                                setTimeout(() => window.abrirModalCambioVer({ getAttribute: () => datos }), 350);
                            },
                        },
                        fields: <?= json_encode($filtrosCambios, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                        loadingTarget: '#grid-body',   // se atenúa mientras se busca
                        onApply: () => { g_paginaActual = 1; return cargarGrid(); },
                    }).init();
                });
            </script>

            <?php // FiltrosModal (extraId) mueve estos botones dentro del input-group del buscador; si el JS no corre, quedan aquí. ?>
            <div id="fmExtraCAM" class="btn-group btn-group-sm">
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas([
                    'fecha_cambio'  => 'Fecha',
                    'dev_cantidad'  => 'Entra: cantidad',
                    'dev_producto'  => 'Entra: producto',
                    'dev_lote'      => 'Entra: lote',
                    'dev_nup'       => 'Entra: NUP',
                    'dev_bodega'    => 'Entra: bodega',
                    'dev_factura'   => 'Entra: factura',
                    'ent_cantidad'  => 'Sale: cantidad',
                    'ent_producto'  => 'Sale: producto',
                    'ent_lote'      => 'Sale: lote',
                    'ent_nup'       => 'Sale: NUP',
                    'ent_bodega'    => 'Sale: bodega',
                    'cliente'       => 'Cliente',
                    'observaciones' => 'Observaciones',
                ], $vistaConfig ?? [], 'cambio-producto-cv'); ?>

                <a class="btn btn-outline-danger pdf-export-btn" href="<?= BASE_URL ?>/<?= $rutaModulo ?>/export-pdf?b=<?= urlencode($buscar) ?>&sort=<?= $ordenCol ?>&dir=<?= $ordenDir ?>" target="_blank" title="Exportar a PDF"><i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span></a>
                <a class="btn btn-outline-success excel-export-btn" href="<?= BASE_URL ?>/<?= $rutaModulo ?>/export-excel?b=<?= urlencode($buscar) ?>&sort=<?= $ordenCol ?>&dir=<?= $ordenDir ?>" title="Exportar a Excel"><i class="bi bi-file-earmark-excel"></i><span class="d-none d-md-inline"> Excel</span></a>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <span id="pagination-info" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="pagination-controls" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" <?= $page <= 1 ? 'disabled' : '' ?> onclick="cambiarPaginaAjax(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" <?= $page >= $totalPages ? 'disabled' : '' ?> onclick="cambiarPaginaAjax(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="cambios-scroll w-100">
            <table class="table table-hover table-sm mb-0" id="tablaCambios">
                <?php // Fecha en celeste. En rojo: lo que ENTRA (devolución). En verde: lo que SALE (entrega). ?>
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header cam-th-fecha" role="button" data-col="fecha_cambio" title="Fecha de emisión del cambio">Fecha <i class="bi bi-arrow-down-up small ms-1"></i></th>
                        <th class="text-end sortable-header cam-th-entra" role="button" data-col="dev_cantidad" title="Producto que entra">Cantidad <i class="bi bi-arrow-down-up small ms-1"></i></th>
                        <th class="sortable-header cam-th-entra" role="button" data-col="dev_producto" title="Producto que entra">Producto <i class="bi bi-arrow-down-up small ms-1"></i></th>
                        <th class="sortable-header cam-th-entra" role="button" data-col="dev_lote" title="Producto que entra">Lote <i class="bi bi-arrow-down-up small ms-1"></i></th>
                        <th class="sortable-header cam-th-entra" role="button" data-col="dev_nup" title="NUP del producto que entra">NUP <i class="bi bi-arrow-down-up small ms-1"></i></th>
                        <th class="sortable-header cam-th-entra" role="button" data-col="dev_bodega" title="Bodega a la que entra">Bodega <i class="bi bi-arrow-down-up small ms-1"></i></th>
                        <th class="sortable-header cam-th-entra" role="button" data-col="dev_factura" title="Factura de venta de la que viene">Factura <i class="bi bi-arrow-down-up small ms-1"></i></th>
                        <th class="text-end sortable-header cam-th-sale cam-lado-sale" role="button" data-col="ent_cantidad" title="Producto que sale">Cantidad <i class="bi bi-arrow-down-up small ms-1"></i></th>
                        <th class="sortable-header cam-th-sale" role="button" data-col="ent_producto" title="Producto que sale">Producto <i class="bi bi-arrow-down-up small ms-1"></i></th>
                        <th class="sortable-header cam-th-sale" role="button" data-col="ent_lote" title="Producto que sale">Lote <i class="bi bi-arrow-down-up small ms-1"></i></th>
                        <th class="sortable-header cam-th-sale" role="button" data-col="ent_nup" title="NUP del producto que sale">NUP <i class="bi bi-arrow-down-up small ms-1"></i></th>
                        <th class="sortable-header cam-th-sale" role="button" data-col="ent_bodega" title="Bodega de la que sale">Bodega <i class="bi bi-arrow-down-up small ms-1"></i></th>
                        <th class="sortable-header cam-th-sale" role="button" data-col="cliente">Cliente <i class="bi bi-arrow-down-up small ms-1"></i></th>
                        <th class="pe-3 sortable-header cam-th-sale" role="button" data-col="observaciones">Observaciones <i class="bi bi-arrow-down-up small ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="grid-body">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="14" class="text-center py-5 text-muted">
                                <i class="bi bi-arrow-left-right fs-3 d-block mb-2"></i>
                                No se encontraron cambios.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php include __DIR__ . '/_fila.php'; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    window.RUTA_MODULO_CAMBIO = '<?= $urlBaseCam ?>';
    window.EMPRESA_CONFIG = {
        facturacion_inventario: <?= (($empresa['facturacion_inventario'] ?? true) === 'true' || ($empresa['facturacion_inventario'] ?? true) === true) ? 'true' : 'false' ?>,
        decimales_precio: <?= (int) ($empresa['decimales_precio'] ?? 2) ?>,
        decimales_cantidad: <?= (int) ($empresa['decimales_cantidad'] ?? 2) ?>
    };
</script>

<?php include __DIR__ . '/modal_cambio.php'; ?>

<script>
    let g_ordenCol = '<?= addslashes($ordenCol) ?>';
    let g_ordenDir = '<?= addslashes($ordenDir) ?>';
    let g_paginaActual = <?= (int)$page ?>;
    let g_buscar = '<?= addslashes($buscar) ?>';

    document.addEventListener("DOMContentLoaded", function () {
        const headers = document.querySelectorAll('#tablaCambios th.sortable-header');
        headers.forEach(th => {
            th.addEventListener('click', () => {
                const col = th.dataset.col;
                if (g_ordenCol === col) {
                    g_ordenDir = g_ordenDir === 'ASC' ? 'DESC' : 'ASC';
                } else {
                    g_ordenCol = col;
                    g_ordenDir = 'ASC';
                }
                actualizarIconosOrden(col, g_ordenDir, 'tablaCambios');
                if (typeof window.guardarOrdenacionVista === 'function') {
                    window.guardarOrdenacionVista('cambio_producto_cv', g_ordenCol, g_ordenDir);
                }
                cargarGrid();
            });
        });
        // Pintar la flecha activa con el orden ya aplicado al cargar la página.
        actualizarIconosOrden(g_ordenCol, g_ordenDir, 'tablaCambios');
    });

    function actualizarIconosOrden(col, dir, tableId) {
        // Encabezados con fondo de color: íconos en blanco sobre rojo/verde y oscuros sobre el
        // celeste de Fecha (tenues si la columna no ordena).
        document.querySelectorAll(`#${tableId} th.sortable-header`).forEach(th => {
            const icon = th.querySelector('i');
            if (icon) {
                const claro = th.classList.contains('cam-th-fecha');
                icon.className = 'bi bi-arrow-down-up small ms-1 ' + (claro ? 'text-black-50' : 'text-white-50');
                if (th.dataset.col === col) {
                    icon.className = (dir === 'ASC' ? 'bi bi-sort-alpha-down ms-1 ' : 'bi bi-sort-alpha-up ms-1 ') + (claro ? 'text-dark' : 'text-white');
                }
            }
        });
    }

    function cambiarPaginaAjax(p) {
        g_paginaActual = p;
        cargarGrid();
    }

    async function cargarGrid() {
        // Mismo indicador que el buscador (FiltrosModal): la tabla se atenúa en vez de
        // vaciarse; también al paginar y ordenar, que llaman a esta función directo.
        const tbody = document.getElementById('grid-body');
        if (tbody) tbody.classList.add('fm-cargando-target');
        try {
            const b_input = document.getElementById('b');
            g_buscar = b_input ? b_input.value : '';

            const params = new URLSearchParams({ b: g_buscar, page: g_paginaActual, sort: g_ordenCol, dir: g_ordenDir });
            const res = await fetch(`${RUTA_MODULO_CAMBIO}/searchAjax?${params.toString()}`);
            if (!res.ok) throw new Error('Error en red');
            const data = await res.json();

            if (data.ok) {
                tbody.innerHTML = data.rows;
                document.getElementById('pagination-info').textContent = data.info;
                document.getElementById('pagination-controls').innerHTML = data.pagination;
                document.querySelector('.pdf-export-btn').href = data.pdf_url;
                document.querySelector('.excel-export-btn').href = data.excel_url;
            }
        } catch (e) {
            console.error(e);
            Swal.fire('Error', 'No se pudo cargar la lista', 'error');
        } finally {
            if (tbody) tbody.classList.remove('fm-cargando-target');
        }
    }
</script>

<script src="<?= rtrim(BASE_URL, "/") ?>/js/modulos/asiento_contable_tab.js?v=<?= asset_ver('/js/modulos/asiento_contable_tab.js') ?>"></script>
