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
/** @var array $seriesFiltro */

$base = BASE_URL;
$urlBaseCons = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');

$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>

<style>
    .cons-header {
        flex-shrink: 0;
    }
    .cons-scroll {
        max-height: calc(100dvh - 240px);
        overflow-y: auto;
    }
    .cons-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }
    .consignacion-row {
        cursor: pointer;
    }
    .consignacion-row:hover {
        background-color: rgba(0, 0, 0, .04);
    }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>

<!-- Encabezado -->
<div class="cons-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-box-seam"></i> <?= htmlspecialchars($titulo) ?></h5>
    <div class="d-flex gap-2">
        <?php if (!empty($perm['crear'])): ?>
            <button type="button" class="btn btn-primary btn-sm px-3" onclick="abrirModalConsignacionNueva()">
                <i class="bi bi-plus-lg"></i> Nueva
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Filtros y Opciones -->
<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <?php
                // Buscador: texto libre sobre las columnas del listado (sin sugerencias) +
                // botón embudo que abre un modal con todos los filtros + chips de los activos.
                // Las claves (key) deben existir en los mapas de ConsignacionVentaRepository::getListado().
                $opcionesSerie       = array_map(fn($s) => ['v' => $s['establecimiento'] . '-' . $s['punto_emision'], 'l' => $s['establecimiento'] . '-' . $s['punto_emision']], $seriesFiltro ?? []);
                $opcionesVendedor    = array_map(fn($v) => ['v' => (string) $v['id'], 'l' => $v['nombre']], $opcionesFiltros['vendedores'] ?? []);
                $opcionesResponsable = array_map(fn($r) => ['v' => (string) $r['id'], 'l' => $r['nombre']], $opcionesFiltros['responsables'] ?? []);
                $opcionesUsuario     = array_map(fn($u) => ['v' => (string) $u['id'], 'l' => $u['nombre']], $opcionesFiltros['usuarios'] ?? []);
                $siNo = fn(string $si, string $no) => [['v' => 'si', 'l' => $si], ['v' => 'no', 'l' => $no]];
                // Dos pestañas: "Consignación" (filtros por campo) y "Detalles" (solo la búsqueda
                // libre dentro de las consignaciones, ver `busquedaDetalle` abajo).
                $tC = 'Consignación';
                // Orden pensado en filas de 12 columnas:
                //   Documento: [Fecha de emisión 6][Fecha de entrega 6]
                //              [Estado 3][Serie 3][Nº consignación 3][Secuencial 3]
                //              [Asiento 4][Facturación 4][Usuario 4]
                //   Valores:   [Total 4][Subtotal 4][IVA 4]
                //   Cliente:   [Cliente 4][RUC 4][Asesor 4]
                //              [Responsable de traslado 4][Punto de llegada 4][Observaciones 4]
                $filtrosConsignaciones = [
                    // ── Documento ──
                    ['tab' => $tC, 'key' => 'fecha',          'label' => 'Fecha de emisión',     'icon' => 'bi-calendar-event',  'type' => 'date_range',   'grupo' => 'Documento', 'col' => 6, 'atajos' => true],
                    ['tab' => $tC, 'key' => 'fecha_entrega',  'label' => 'Fecha de entrega',     'icon' => 'bi-calendar-check',  'type' => 'date_range',   'grupo' => 'Documento', 'col' => 6],
                    ['tab' => $tC, 'key' => 'estado',         'label' => 'Estado',               'icon' => 'bi-flag',            'type' => 'select',       'grupo' => 'Documento', 'col' => 3, 'options' => [
                        ['v' => 'Borrador',  'l' => 'Borrador'],
                        ['v' => 'Emitida',   'l' => 'Emitida (pendiente de entrega)'],
                        ['v' => 'Entregada', 'l' => 'Entregada'],
                        ['v' => 'Anulada',   'l' => 'Anulada'],
                    ]],
                    ['tab' => $tC, 'key' => 'serie',          'label' => 'Serie',                'icon' => 'bi-upc-scan',        'type' => 'select',       'grupo' => 'Documento', 'col' => 3, 'options' => $opcionesSerie],
                    ['tab' => $tC, 'key' => 'numero',         'label' => 'Nº consignación',      'icon' => 'bi-hash',            'type' => 'text',         'grupo' => 'Documento', 'col' => 3, 'placeholder' => '001-001-000000123'],
                    ['tab' => $tC, 'key' => 'secuencial',     'label' => 'Secuencial',           'icon' => 'bi-123',             'type' => 'text',         'grupo' => 'Documento', 'col' => 3, 'placeholder' => 'Sin ceros'],
                    ['tab' => $tC, 'key' => 'asiento',        'label' => 'Asiento contable',     'icon' => 'bi-journal-check',   'type' => 'select',       'grupo' => 'Documento', 'col' => 4, 'options' => $siNo('Con asiento', 'Sin asiento')],
                    ['tab' => $tC, 'key' => 'facturada',      'label' => 'Facturación',          'icon' => 'bi-receipt',         'type' => 'select',       'grupo' => 'Documento', 'col' => 4, 'options' => $siNo('Con factura de consignación', 'Sin factura de consignación')],
                    ['tab' => $tC, 'key' => 'id_usuario',     'label' => 'Usuario que registró', 'icon' => 'bi-person-gear',     'type' => 'select',       'grupo' => 'Documento', 'col' => 4, 'options' => $opcionesUsuario],
                    // ── Valores ──
                    ['tab' => $tC, 'key' => 'total',          'label' => 'Total',                'icon' => 'bi-currency-dollar', 'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                    ['tab' => $tC, 'key' => 'subtotal',       'label' => 'Subtotal',             'icon' => 'bi-receipt',         'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                    ['tab' => $tC, 'key' => 'impuesto',       'label' => 'IVA',                  'icon' => 'bi-percent',         'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                    // ── Cliente ──
                    ['tab' => $tC, 'key' => 'cliente',        'label' => 'Cliente',              'icon' => 'bi-person',          'type' => 'text',         'grupo' => 'Cliente', 'col' => 4],
                    ['tab' => $tC, 'key' => 'ruc',            'label' => 'RUC / Cédula',         'icon' => 'bi-card-text',       'type' => 'text',         'grupo' => 'Cliente', 'col' => 4],
                    ['tab' => $tC, 'key' => 'id_vendedor',    'label' => 'Asesor',               'icon' => 'bi-person-badge',    'type' => 'select',       'grupo' => 'Cliente', 'col' => 4, 'options' => $opcionesVendedor],
                    ['tab' => $tC, 'key' => 'id_responsable', 'label' => 'Responsable de traslado', 'icon' => 'bi-truck',        'type' => 'select',       'grupo' => 'Cliente', 'col' => 4, 'options' => $opcionesResponsable],
                    ['tab' => $tC, 'key' => 'punto_llegada',  'label' => 'Punto de llegada',     'icon' => 'bi-geo-alt',         'type' => 'text',         'grupo' => 'Cliente', 'col' => 4],
                    ['tab' => $tC, 'key' => 'observaciones',  'label' => 'Observaciones',        'icon' => 'bi-chat-left-text',  'type' => 'text',         'grupo' => 'Cliente', 'col' => 4],
                ];
                ?>
                <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
                <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
                <div id="fmBuscadorCONS"></div>
                <input type="hidden" id="b" name="b" value="<?= htmlspecialchars($buscar) ?>">

                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        if (!window.FiltrosModal) return;
                        new FiltrosModal({
                            containerId: 'fmBuscadorCONS',
                            hiddenInputId: 'b',
                            placeholder: 'Buscar en todas las columnas...',
                            titulo: 'Filtros de consignaciones',
                            inputWidth: 420,
                            extraId: 'fmExtraCONS',   // columnas + PDF + Excel, pegados al final del grupo
                            // Pestaña Detalles: búsqueda libre dentro de las consignaciones (productos
                            // con lote/NUP y documentos relacionados). Cada coincidencia dice a qué
                            // consignación pertenece.
                            busquedaDetalle: {
                                tab: 'Detalles',
                                url: `<?= BASE_URL ?>/<?= $rutaModulo ?>/buscarDetallesAjax`,
                                label: 'Buscar libremente dentro de las consignaciones',
                                placeholder: 'Producto, código, lote, NUP, bodega, factura, retorno o cambio relacionado...',
                                columns: [
                                    { key: 'origen',      label: 'Tipo' },
                                    { key: 'tipo',        label: 'Código / Nº' },
                                    { key: 'descripcion', label: 'Descripción' },
                                    { key: 'extra',       label: 'Lote / NUP / Caducidad', class: 'font-monospace' },
                                    { key: 'cantidad',    label: 'Cant.', align: 'end' },
                                    { key: 'monto',       label: 'Valor', align: 'end' },
                                    { key: 'numero',      label: 'Consignación', class: 'font-monospace fw-semibold' },
                                    { key: 'fecha',       label: 'Fecha' },
                                    { key: 'cliente',     label: 'Cliente' },
                                    { key: 'estado',      label: 'Estado' },
                                ],
                                onSelect: (row, fm) => fm.aplicarFiltro({ key: 'numero', value: row.numero }),
                                onOpen: (row, fm) => {
                                    fm.hide();
                                    // abrirModalConsignacionVer lee la cabecera del data-row de la fila.
                                    const fila = { getAttribute: () => JSON.stringify(row.registro || {}) };
                                    setTimeout(() => abrirModalConsignacionVer(fila), 350);
                                },
                            },
                            fields: <?= json_encode($filtrosConsignaciones, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                            loadingTarget: '#grid-body',   // se atenúa mientras se busca
                            onApply: () => { g_paginaActual = 1; return cargarGrid(); },
                        }).init();
                    });
                </script>

                <?php // FiltrosModal (extraId) mueve estos botones dentro del input-group del buscador; si el JS no corre, quedan aquí. ?>
                <div id="fmExtraCONS" class="btn-group btn-group-sm">
                    <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas([
                        'fecha_emision' => 'Fecha',
                        'secuencial' => 'Secuencial',
                        'cliente' => 'Cliente',
                        'vendedor' => 'Asesor',
                        'observaciones' => 'Observaciones',
                        'estado' => 'Estado'
                    ], $vistaConfig ?? [], 'consignaciones-ventas'); ?>

                    <a class="btn btn-outline-danger pdf-export-btn" href="<?= BASE_URL ?>/<?= $rutaModulo ?>/export-pdf?b=<?= urlencode($buscar) ?>&sort=<?= $ordenCol ?>&dir=<?= $ordenDir ?>" target="_blank" title="Exportar a PDF"><i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span></a>
                    <a class="btn btn-outline-success excel-export-btn" href="<?= BASE_URL ?>/<?= $rutaModulo ?>/export-excel?b=<?= urlencode($buscar) ?>&sort=<?= $ordenCol ?>&dir=<?= $ordenDir ?>" title="Exportar a Excel"><i class="bi bi-file-earmark-excel"></i><span class="d-none d-md-inline"> Excel</span></a>
                </div>
            </div>

            <!-- Paginación -->
            <div class="d-flex align-items-center gap-3">
                <span id="pagination-info" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
                <div id="pagination-controls" class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" <?= $page <= 1 ? 'disabled' : '' ?> onclick="cambiarPaginaAjax(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                    <button type="button" class="btn btn-outline-secondary" <?= $page >= $totalPages ? 'disabled' : '' ?> onclick="cambiarPaginaAjax(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="cons-scroll w-100">
                <table class="table table-hover table-sm mb-0" id="tablaConsignaciones">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3 sortable-header" role="button" data-col="fecha_emision">Fecha <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="sortable-header" role="button" data-col="secuencial">Secuencial <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="sortable-header" role="button" data-col="cliente">Cliente <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="sortable-header" role="button" data-col="vendedor">Asesor <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="sortable-header" role="button" data-col="observaciones">Observaciones <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="text-center pe-3 sortable-header" role="button" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        </tr>
                    </thead>
                    <tbody id="grid-body">
                        <?php if (empty($rows)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-box-seam fs-3 d-block mb-2"></i>
                                    No se encontraron consignaciones.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rows as $r): 
                                $dataJson = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                                switch ($r['estado'] ?? 'Borrador') {
                                    case 'Entregada':
                                        $statusBadge = '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Entregada</span>';
                                        break;
                                    case 'Anulada':
                                        $statusBadge = '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Anulada</span>';
                                        break;
                                    case 'Emitida': // legado
                                        $statusBadge = '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">Emitida</span>';
                                        break;
                                    default:
                                        $statusBadge = '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Borrador</span>';
                                }
                            ?>
                                <tr class="consignacion-row" role="button" tabindex="0" data-row="<?= $dataJson ?>" onclick="abrirModalConsignacionVer(this)">
                                    <td class="ps-3" data-col="fecha_emision"><?= htmlspecialchars($r['fecha_emision'] ?? '') ?></td>
                                    <td data-col="secuencial"><?= htmlspecialchars(($r['serie'] ?? '') . '-' . ($r['secuencial'] ?? '')) ?></td>
                                    <td data-col="cliente" class="text-truncate" style="max-width:250px" title="<?= htmlspecialchars($r['cliente_nombre'] ?? '') ?>"><?= htmlspecialchars($r['cliente_nombre'] ?? '') ?></td>
                                    <td data-col="vendedor" class="text-truncate" style="max-width:150px" title="<?= htmlspecialchars($r['vendedor_nombre'] ?? '—') ?>"><?= htmlspecialchars($r['vendedor_nombre'] ?? '—') ?></td>
                                    <td data-col="observaciones" class="text-truncate" style="max-width:200px" title="<?= htmlspecialchars($r['observaciones'] ?? '—') ?>"><?= htmlspecialchars($r['observaciones'] ?? '—') ?></td>
                                    <td class="text-center pe-3" data-col="estado"><?= $statusBadge ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php include __DIR__ . '/modal_consignacion.php'; ?>

<!-- ESTILOS Y SCRIPTS PROPIOS DEL MODULO -->
<style>
    .cmg-table-card { transition: all 0.2s ease; }
    .cmg-table-card:hover { box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.05) !important; }
    #tablaConsignaciones tbody tr { transition: background-color 0.15s ease; cursor: pointer; }
    #tablaConsignaciones tbody tr:hover { background-color: #f8f9fa !important; }
    .cmg-search-group .form-control:focus { box-shadow: none; border-color: #dee2e6; }
    .cmg-search-group .input-group-text, .cmg-search-group .form-control { border-color: #e9ecef; }
</style>

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig, 'consignaciones-ventas', 'estiloVistaColumnasConsignacion'); ?>

<script>
    if (typeof window.RUTA_MODULO_CONSIGNACION === 'undefined') {
        window.RUTA_MODULO_CONSIGNACION = '<?= $urlBaseCons ?>';
    }
    let g_ordenCol = '<?= addslashes($ordenCol) ?>';
    let g_ordenDir = '<?= addslashes($ordenDir) ?>';
    let g_paginaActual = <?= (int)$page ?>;
    let g_buscar = '<?= addslashes($buscar) ?>';

    const EMPRESA_CONFIG = {
        facturacion_libre: <?= (($empresa['facturacion_libre'] ?? false) === 'true' || ($empresa['facturacion_libre'] ?? false) === true) ? 'true' : 'false' ?>,
        facturacion_inventario: <?= (($empresa['facturacion_inventario'] ?? true) === 'true'  || ($empresa['facturacion_inventario'] ?? true)  === true)  ? 'true' : 'false' ?>,
        obligatorio_lotes: <?= (($empresa['obligatorio_lotes'] ?? false) === 'true'    || ($empresa['obligatorio_lotes'] ?? false)    === true)    ? 'true' : 'false' ?>,
        obligatorio_caducidad: <?= (($empresa['obligatorio_caducidad'] ?? false) === 'true' || ($empresa['obligatorio_caducidad'] ?? false) === true) ? 'true' : 'false' ?>,
        obligatorio_nup: <?= (($empresa['obligatorio_nup'] ?? false) === 'true'       || ($empresa['obligatorio_nup'] ?? false)       === true)       ? 'true' : 'false' ?>,
        decimales_precio: <?= (int) ($empresa['decimales_precio'] ?? 2) ?>,
        decimales_cantidad: <?= (int) ($empresa['decimales_cantidad'] ?? 2) ?>
    };

    document.addEventListener("DOMContentLoaded", function() {
        const headers = document.querySelectorAll('#tablaConsignaciones th.sortable-header');
        headers.forEach(th => {
            th.addEventListener('click', () => {
                const col = th.dataset.col;
                if (g_ordenCol === col) {
                    g_ordenDir = g_ordenDir === 'ASC' ? 'DESC' : 'ASC';
                } else {
                    g_ordenCol = col;
                    g_ordenDir = 'ASC';
                }
                actualizarIconosOrden(col, g_ordenDir, 'tablaConsignaciones');
                if (typeof window.guardarOrdenacionVista === 'function') {
                    window.guardarOrdenacionVista('consignaciones_ventas', g_ordenCol, g_ordenDir);
                }
                cargarGrid();
            });
        });
        // Pintar la flecha activa con el orden ya aplicado al cargar la página.
        actualizarIconosOrden(g_ordenCol, g_ordenDir, 'tablaConsignaciones');
    });

    function actualizarIconosOrden(col, dir, tableId) {
        const headers = document.querySelectorAll(`#${tableId} th.sortable-header`);
        headers.forEach(th => {
            const icon = th.querySelector('i');
            if(icon) {
                icon.className = 'bi bi-arrow-down-up text-muted ms-1';
                if(th.dataset.col === col) {
                    icon.className = dir === 'ASC' ? 'bi bi-sort-alpha-down text-primary ms-1' : 'bi bi-sort-alpha-up text-primary ms-1';
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
        // Solo vale la ÚLTIMA búsqueda: la anterior se cancela y nunca pinta encima.
        if (window.CV_busquedaCtrl) window.CV_busquedaCtrl.abort();
        const ctrl = new AbortController();
        window.CV_busquedaCtrl = ctrl;
        try {
            const b_input = document.getElementById('b');
            g_buscar = b_input ? b_input.value : '';

            const params = new URLSearchParams({
                b: g_buscar,
                page: g_paginaActual,
                sort: g_ordenCol,
                dir: g_ordenDir
            });

            const res = await fetch(`${RUTA_MODULO_CONSIGNACION}/searchAjax?${params.toString()}`, { signal: ctrl.signal });
            if(!res.ok) throw new Error('Error en red');
            const data = await res.json();
            if (ctrl !== window.CV_busquedaCtrl) return;

            if(data.ok) {
                tbody.innerHTML = data.rows;
                document.getElementById('pagination-info').textContent = data.info;
                document.getElementById('pagination-controls').innerHTML = data.pagination;
                document.querySelector('.pdf-export-btn').href = data.pdf_url;
                document.querySelector('.excel-export-btn').href = data.excel_url;
            }
        } catch(e) {
            if (e.name === 'AbortError') return;
            console.error(e);
            Swal.fire('Error', 'No se pudo cargar la lista', 'error');
        } finally {
            if (tbody && ctrl === window.CV_busquedaCtrl) tbody.classList.remove('fm-cargando-target');
        }
    }
</script>

<script src="<?= rtrim(BASE_URL, "/") ?>/js/modulos/asiento_contable_tab.js?v=<?= asset_ver('/js/modulos/asiento_contable_tab.js') ?>"></script>
