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

$base = BASE_URL;
$urlBaseRet = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');

$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>

<style>
    .ret-header { flex-shrink: 0; }
    .retcv-scroll {
        max-height: calc(100dvh - 240px);
        overflow-y: auto;
    }
    .retcv-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }
    .retorno-row { cursor: pointer; }
    .retorno-row:hover { background-color: rgba(0, 0, 0, .04); }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>

<!-- Encabezado -->
<div class="ret-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-arrow-return-left"></i> <?= htmlspecialchars($titulo) ?></h5>
    <div class="d-flex gap-2">
        <?php if (!empty($perm['crear']) || !empty($perm['todo'])): ?>
            <button type="button" class="btn btn-primary btn-sm px-3" onclick="abrirModalRetornoNuevo()">
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
            // Las claves (key) deben existir en los mapas de RetornoCvRepository::getListado().
            $opcionesSerie       = array_map(fn($s) => ['v' => $s['establecimiento'] . '-' . $s['punto_emision'], 'l' => $s['establecimiento'] . '-' . $s['punto_emision']], $seriesFiltro ?? []);
            $opcionesResponsable = array_map(fn($r) => ['v' => (string) $r['id'], 'l' => $r['nombre']], $opcionesFiltros['responsables'] ?? []);
            $opcionesUsuario     = array_map(fn($u) => ['v' => (string) $u['id'], 'l' => $u['nombre']], $opcionesFiltros['usuarios'] ?? []);
            // Dos pestañas: "Retorno" (filtros por campo) y "Detalles" (solo la búsqueda libre
            // dentro de los retornos, ver `busquedaDetalle` abajo).
            $tR = 'Retorno';
            // Orden pensado en filas de 12 columnas:
            //   Documento: [Fecha 6][Estado 3][Serie 3]
            //              [Nº retorno 3][Secuencial 3][Consignación 3][Asiento 3]
            //              [Responsable 6][Usuario 6]
            //   Valores:   [Total 4][Subtotal 4][IVA 4]
            //   Cliente:   [Cliente 4][RUC 4][Motivo 4]
            //              [Observaciones 12]
            $filtrosRetornos = [
                // ── Documento ──
                ['tab' => $tR, 'key' => 'fecha',          'label' => 'Fecha del retorno',    'icon' => 'bi-calendar-event',  'type' => 'date_range',   'grupo' => 'Documento', 'col' => 6, 'atajos' => true],
                ['tab' => $tR, 'key' => 'estado',         'label' => 'Estado',               'icon' => 'bi-flag',            'type' => 'select',       'grupo' => 'Documento', 'col' => 3, 'options' => [
                    ['v' => 'Borrador', 'l' => 'Borrador'],
                    ['v' => 'Emitida',  'l' => 'Emitida'],
                    ['v' => 'Anulada',  'l' => 'Anulada'],
                ]],
                ['tab' => $tR, 'key' => 'serie',          'label' => 'Serie',                'icon' => 'bi-upc-scan',        'type' => 'select',       'grupo' => 'Documento', 'col' => 3, 'options' => $opcionesSerie],
                ['tab' => $tR, 'key' => 'numero',         'label' => 'Nº retorno',           'icon' => 'bi-hash',            'type' => 'text',         'grupo' => 'Documento', 'col' => 3, 'placeholder' => '001-001-000000123'],
                ['tab' => $tR, 'key' => 'secuencial',     'label' => 'Secuencial',           'icon' => 'bi-123',             'type' => 'text',         'grupo' => 'Documento', 'col' => 3, 'placeholder' => 'Sin ceros'],
                ['tab' => $tR, 'key' => 'consignacion',   'label' => 'Consignación de origen', 'icon' => 'bi-box-seam',      'type' => 'text',         'grupo' => 'Documento', 'col' => 3, 'placeholder' => '001-001-000000123'],
                ['tab' => $tR, 'key' => 'asiento',        'label' => 'Asiento contable',     'icon' => 'bi-journal-check',   'type' => 'select',       'grupo' => 'Documento', 'col' => 3, 'options' => [
                    ['v' => 'si', 'l' => 'Con asiento'],
                    ['v' => 'no', 'l' => 'Sin asiento'],
                ]],
                ['tab' => $tR, 'key' => 'id_responsable', 'label' => 'Responsable de traslado', 'icon' => 'bi-truck',        'type' => 'select',       'grupo' => 'Documento', 'col' => 6, 'options' => $opcionesResponsable],
                ['tab' => $tR, 'key' => 'id_usuario',     'label' => 'Usuario que registró', 'icon' => 'bi-person-gear',     'type' => 'select',       'grupo' => 'Documento', 'col' => 6, 'options' => $opcionesUsuario],
                // ── Valores ──
                ['tab' => $tR, 'key' => 'total',          'label' => 'Total',                'icon' => 'bi-currency-dollar', 'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                ['tab' => $tR, 'key' => 'subtotal',       'label' => 'Subtotal',             'icon' => 'bi-receipt',         'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                ['tab' => $tR, 'key' => 'impuesto',       'label' => 'IVA',                  'icon' => 'bi-percent',         'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                // ── Cliente ──
                ['tab' => $tR, 'key' => 'cliente',        'label' => 'Cliente',              'icon' => 'bi-person',          'type' => 'text',         'grupo' => 'Cliente', 'col' => 4],
                ['tab' => $tR, 'key' => 'ruc',            'label' => 'RUC / Cédula',         'icon' => 'bi-card-text',       'type' => 'text',         'grupo' => 'Cliente', 'col' => 4],
                ['tab' => $tR, 'key' => 'motivo',         'label' => 'Motivo',               'icon' => 'bi-chat',            'type' => 'text',         'grupo' => 'Cliente', 'col' => 4],
                ['tab' => $tR, 'key' => 'observaciones',  'label' => 'Observaciones',        'icon' => 'bi-chat-left-text',  'type' => 'text',         'grupo' => 'Cliente', 'col' => 12],
            ];
            ?>
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
            <div id="fmBuscadorRET"></div>
            <input type="hidden" id="b" name="b" value="<?= htmlspecialchars($buscar) ?>">

            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosModal) return;
                    new FiltrosModal({
                        containerId: 'fmBuscadorRET',
                        hiddenInputId: 'b',
                        placeholder: 'Buscar en todas las columnas...',
                        titulo: 'Filtros de retornos de consignaciones',
                        inputWidth: 420,
                        extraId: 'fmExtraRET',   // columnas + PDF + Excel, pegados al final del grupo
                        // Pestaña Detalles: búsqueda libre dentro de los retornos (productos retornados
                        // con lote/NUP, bodega y consignación de origen).
                        busquedaDetalle: {
                            tab: 'Detalles',
                            url: `<?= $urlBaseRet ?>/buscarDetallesAjax`,
                            label: 'Buscar libremente dentro de los retornos',
                            placeholder: 'Producto, código, lote, NUP, bodega, consignación de origen...',
                            columns: [
                                { key: 'tipo',         label: 'Código' },
                                { key: 'descripcion',  label: 'Producto' },
                                { key: 'extra',        label: 'Lote / NUP / Caducidad', class: 'font-monospace' },
                                { key: 'bodega',       label: 'Bodega' },
                                { key: 'consignacion', label: 'Consignación', class: 'font-monospace' },
                                { key: 'cantidad',     label: 'Cant.', align: 'end' },
                                { key: 'monto',        label: 'Valor', align: 'end' },
                                { key: 'numero',       label: 'Retorno', class: 'font-monospace fw-semibold' },
                                { key: 'fecha',        label: 'Fecha' },
                                { key: 'cliente',      label: 'Cliente' },
                                { key: 'estado',       label: 'Estado' },
                            ],
                            onSelect: (row, fm) => fm.aplicarFiltro({ key: 'numero', value: row.numero }),
                            onOpen: (row, fm) => {
                                fm.hide();
                                // abrirModalRetornoVer lee el retorno del data-row de la fila.
                                const datos = JSON.stringify({ id: row.id, estado: row.estado, serie: row.serie, secuencial: row.secuencial });
                                setTimeout(() => window.abrirModalRetornoVer({ getAttribute: () => datos }), 350);
                            },
                        },
                        fields: <?= json_encode($filtrosRetornos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                        loadingTarget: '#grid-body',   // se atenúa mientras se busca
                        onApply: () => { g_paginaActual = 1; return cargarGrid(); },
                    }).init();
                });
            </script>

            <?php // FiltrosModal (extraId) mueve estos botones dentro del input-group del buscador; si el JS no corre, quedan aquí. ?>
            <div id="fmExtraRET" class="btn-group btn-group-sm">
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas([
                    'fecha_retorno' => 'Fecha',
                    'secuencial'    => 'Secuencial',
                    'cliente'       => 'Cliente',
                    'motivo'        => 'Motivo',
                    'estado'        => 'Estado'
                ], $vistaConfig ?? [], 'retornos-cv'); ?>

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
        <div class="retcv-scroll w-100">
            <table class="table table-hover table-sm mb-0" id="tablaRetornos">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-col="fecha_retorno">Fecha <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-col="secuencial">Secuencial <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-col="cliente">Cliente <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-col="motivo">Motivo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center pe-3 sortable-header" role="button" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="grid-body">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="bi bi-arrow-return-left fs-3 d-block mb-2"></i>
                                No se encontraron retornos.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r):
                            $dataJson = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                            $statusBadge = \App\controllers\modulos\RetornosCvController::badgeEstado($r['estado'] ?? '');
                        ?>
                            <tr class="retorno-row" role="button" tabindex="0" data-row="<?= $dataJson ?>" onclick="abrirModalRetornoVer(this)">
                                <td class="ps-3" data-col="fecha_retorno"><?= htmlspecialchars($r['fecha_retorno'] ?? '') ?></td>
                                <td data-col="secuencial"><?= htmlspecialchars(($r['serie'] ?? '') . '-' . ($r['secuencial'] ?? '')) ?></td>
                                <td data-col="cliente" class="text-truncate" style="max-width:250px" title="<?= htmlspecialchars($r['cliente_nombre'] ?? '') ?>"><?= htmlspecialchars($r['cliente_nombre'] ?? '') ?></td>
                                <td data-col="motivo" class="text-truncate" style="max-width:220px" title="<?= htmlspecialchars($r['motivo'] ?? '—') ?>"><?= htmlspecialchars($r['motivo'] ?? '—') ?></td>
                                <td class="text-center pe-3" data-col="estado"><?= $statusBadge ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Globales definidos ANTES del modal para que su IIFE los lea correctamente -->
<script>
    window.RUTA_MODULO_RETORNO = '<?= $urlBaseRet ?>';
    window.EMPRESA_CONFIG = {
        facturacion_inventario: <?= (($empresa['facturacion_inventario'] ?? true) === 'true' || ($empresa['facturacion_inventario'] ?? true) === true) ? 'true' : 'false' ?>,
        decimales_precio: <?= (int) ($empresa['decimales_precio'] ?? 2) ?>,
        decimales_cantidad: <?= (int) ($empresa['decimales_cantidad'] ?? 2) ?>
    };
</script>

<?php include __DIR__ . '/modal_retorno.php'; ?>

<script>
    let g_ordenCol = '<?= addslashes($ordenCol) ?>';
    let g_ordenDir = '<?= addslashes($ordenDir) ?>';
    let g_paginaActual = <?= (int)$page ?>;
    let g_buscar = '<?= addslashes($buscar) ?>';

    document.addEventListener("DOMContentLoaded", function () {
        const headers = document.querySelectorAll('#tablaRetornos th.sortable-header');
        headers.forEach(th => {
            th.addEventListener('click', () => {
                const col = th.dataset.col;
                if (g_ordenCol === col) {
                    g_ordenDir = g_ordenDir === 'ASC' ? 'DESC' : 'ASC';
                } else {
                    g_ordenCol = col;
                    g_ordenDir = 'ASC';
                }
                actualizarIconosOrden(col, g_ordenDir, 'tablaRetornos');
                if (typeof window.guardarOrdenacionVista === 'function') {
                    window.guardarOrdenacionVista('retornos_cv', g_ordenCol, g_ordenDir);
                }
                cargarGrid();
            });
        });
        // Pintar la flecha activa con el orden ya aplicado al cargar la página.
        actualizarIconosOrden(g_ordenCol, g_ordenDir, 'tablaRetornos');
    });

    function actualizarIconosOrden(col, dir, tableId) {
        document.querySelectorAll(`#${tableId} th.sortable-header`).forEach(th => {
            const icon = th.querySelector('i');
            if (icon) {
                icon.className = 'bi bi-arrow-down-up text-muted ms-1';
                if (th.dataset.col === col) {
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
        if (window.RCV_busquedaCtrl) window.RCV_busquedaCtrl.abort();
        const ctrl = new AbortController();
        window.RCV_busquedaCtrl = ctrl;
        try {
            const b_input = document.getElementById('b');
            g_buscar = b_input ? b_input.value : '';

            const params = new URLSearchParams({ b: g_buscar, page: g_paginaActual, sort: g_ordenCol, dir: g_ordenDir });
            const res = await fetch(`${RUTA_MODULO_RETORNO}/searchAjax?${params.toString()}`, { signal: ctrl.signal });
            if (!res.ok) throw new Error('Error en red');
            const data = await res.json();
            if (ctrl !== window.RCV_busquedaCtrl) return;

            if (data.ok) {
                tbody.innerHTML = data.rows;
                document.getElementById('pagination-info').textContent = data.info;
                document.getElementById('pagination-controls').innerHTML = data.pagination;
                document.querySelector('.pdf-export-btn').href = data.pdf_url;
                document.querySelector('.excel-export-btn').href = data.excel_url;
            }
        } catch (e) {
            if (e.name === 'AbortError') return;
            console.error(e);
            Swal.fire('Error', 'No se pudo cargar la lista', 'error');
        } finally {
            if (tbody && ctrl === window.RCV_busquedaCtrl) tbody.classList.remove('fm-cargando-target');
        }
    }
</script>

<script src="<?= rtrim(BASE_URL, "/") ?>/js/modulos/asiento_contable_tab.js?v=<?= asset_ver('/js/modulos/asiento_contable_tab.js') ?>"></script>
