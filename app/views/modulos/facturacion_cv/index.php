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
/** @var array $empresa */
/** @var array $vendedores */
/** @var array $puntos */

$base = BASE_URL;
$urlBaseFac = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');
$urlBaseClientes = rtrim($base, '/') . '/modulos/clientes';

$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>

<style>
    .fac-header { flex-shrink: 0; }
    .factcv-scroll { max-height: calc(100dvh - 240px); overflow-y: auto; }
    .factcv-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
    .factcv-row { cursor: pointer; }
    .factcv-row:hover { background-color: rgba(0, 0, 0, .04); }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<div class="fac-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-receipt-cutoff"></i> <?= htmlspecialchars($titulo) ?></h5>
    <div class="d-flex gap-2">
        <?php if (!empty($perm['crear']) || !empty($perm['todo'])): ?>
            <button type="button" class="btn btn-primary btn-sm px-3" onclick="abrirModalFacturacionNueva()">
                <i class="bi bi-plus-lg"></i> Nueva
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
            // Las claves (key) deben existir en los mapas de ConsignacionFacturaRepository::getListado().
            $opcionesSerie    = array_map(fn($s) => ['v' => $s['establecimiento'] . '-' . $s['punto_emision'], 'l' => $s['establecimiento'] . '-' . $s['punto_emision']], $seriesFiltro ?? []);
            $opcionesVendedor = array_map(fn($v) => ['v' => (string) $v['id'], 'l' => $v['nombre']], $opcionesFiltros['vendedores'] ?? []);
            $opcionesUsuario  = array_map(fn($u) => ['v' => (string) $u['id'], 'l' => $u['nombre']], $opcionesFiltros['usuarios'] ?? []);
            // Dos pestañas: "Facturación" (filtros por campo) y "Detalles" (solo la búsqueda
            // libre dentro de los documentos, ver `busquedaDetalle` abajo).
            $tF = 'Facturación';
            // Orden pensado en filas de 12 columnas:
            //   Documento: [Fecha 6][Estado 3][Serie 3]
            //              [Nº documento 3][Secuencial 3][Factura 3][Consignación 3]
            //              [Asiento 4][Vendedor 4][Usuario 4]
            //   Valores:   [Total 4][Subtotal 4][IVA 4]
            //   Cliente:   [Cliente 4][RUC 4][Observaciones 4]
            $filtrosFacturacion = [
                // ── Documento ──
                ['tab' => $tF, 'key' => 'fecha',         'label' => 'Fecha de emisión',     'icon' => 'bi-calendar-event',  'type' => 'date_range',   'grupo' => 'Documento', 'col' => 6, 'atajos' => true],
                ['tab' => $tF, 'key' => 'estado',        'label' => 'Estado',               'icon' => 'bi-flag',            'type' => 'select',       'grupo' => 'Documento', 'col' => 3, 'options' => [
                    ['v' => 'borrador',  'l' => 'Borrador'],
                    ['v' => 'facturada', 'l' => 'Facturada'],
                    ['v' => 'anulada',   'l' => 'Anulada'],
                ]],
                ['tab' => $tF, 'key' => 'serie',         'label' => 'Serie',                'icon' => 'bi-upc-scan',        'type' => 'select',       'grupo' => 'Documento', 'col' => 3, 'options' => $opcionesSerie],
                ['tab' => $tF, 'key' => 'numero',        'label' => 'Nº documento',         'icon' => 'bi-hash',            'type' => 'text',         'grupo' => 'Documento', 'col' => 3, 'placeholder' => '001-001-000000123'],
                ['tab' => $tF, 'key' => 'secuencial',    'label' => 'Secuencial',           'icon' => 'bi-123',             'type' => 'text',         'grupo' => 'Documento', 'col' => 3, 'placeholder' => 'Sin ceros'],
                ['tab' => $tF, 'key' => 'factura',       'label' => 'Factura de venta',     'icon' => 'bi-receipt',         'type' => 'text',         'grupo' => 'Documento', 'col' => 3, 'placeholder' => '001-001-000000123'],
                ['tab' => $tF, 'key' => 'consignacion',  'label' => 'Consignación de origen', 'icon' => 'bi-box-seam',      'type' => 'text',         'grupo' => 'Documento', 'col' => 3, 'placeholder' => '001-001-000000123'],
                ['tab' => $tF, 'key' => 'asiento',       'label' => 'Asiento de reingreso', 'icon' => 'bi-journal-check',   'type' => 'select',       'grupo' => 'Documento', 'col' => 4, 'options' => [
                    ['v' => 'si', 'l' => 'Con asiento'],
                    ['v' => 'no', 'l' => 'Sin asiento'],
                ]],
                ['tab' => $tF, 'key' => 'id_vendedor',   'label' => 'Vendedor',             'icon' => 'bi-person-badge',    'type' => 'select',       'grupo' => 'Documento', 'col' => 4, 'options' => $opcionesVendedor],
                ['tab' => $tF, 'key' => 'id_usuario',    'label' => 'Usuario que registró', 'icon' => 'bi-person-gear',     'type' => 'select',       'grupo' => 'Documento', 'col' => 4, 'options' => $opcionesUsuario],
                // ── Valores ──
                ['tab' => $tF, 'key' => 'total',         'label' => 'Total',                'icon' => 'bi-currency-dollar', 'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                ['tab' => $tF, 'key' => 'subtotal',      'label' => 'Subtotal',             'icon' => 'bi-receipt',         'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                ['tab' => $tF, 'key' => 'impuesto',      'label' => 'IVA',                  'icon' => 'bi-percent',         'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                // ── Cliente ──
                ['tab' => $tF, 'key' => 'cliente',       'label' => 'Cliente',              'icon' => 'bi-person',          'type' => 'text',         'grupo' => 'Cliente', 'col' => 4],
                ['tab' => $tF, 'key' => 'ruc',           'label' => 'RUC / Cédula',         'icon' => 'bi-card-text',       'type' => 'text',         'grupo' => 'Cliente', 'col' => 4],
                ['tab' => $tF, 'key' => 'observaciones', 'label' => 'Observaciones',        'icon' => 'bi-chat-left-text',  'type' => 'text',         'grupo' => 'Cliente', 'col' => 4],
            ];
            ?>
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
            <div id="fmBuscadorFAC"></div>
            <input type="hidden" id="b" name="b" value="<?= htmlspecialchars($buscar) ?>">

            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosModal) return;
                    new FiltrosModal({
                        containerId: 'fmBuscadorFAC',
                        hiddenInputId: 'b',
                        placeholder: 'Buscar en todas las columnas...',
                        titulo: 'Filtros de facturación de consignaciones',
                        inputWidth: 420,
                        extraId: 'fmExtraFAC',   // columnas + PDF + Excel, pegados al final del grupo
                        // Pestaña Detalles: búsqueda libre dentro de los documentos (productos con
                        // lote/NUP y consignación de origen, e información adicional).
                        busquedaDetalle: {
                            tab: 'Detalles',
                            url: `<?= $urlBaseFac ?>/buscarDetallesAjax`,
                            label: 'Buscar libremente dentro de las facturaciones',
                            placeholder: 'Producto, código, lote, NUP, bodega, consignación de origen, información adicional...',
                            columns: [
                                { key: 'origen',       label: 'Tipo' },
                                { key: 'tipo',         label: 'Código' },
                                { key: 'descripcion',  label: 'Descripción' },
                                { key: 'extra',        label: 'Lote / NUP / Caducidad', class: 'font-monospace' },
                                { key: 'consignacion', label: 'Consignación', class: 'font-monospace' },
                                { key: 'cantidad',     label: 'Cant.', align: 'end' },
                                { key: 'monto',        label: 'Valor', align: 'end' },
                                { key: 'numero',       label: 'Documento', class: 'font-monospace fw-semibold' },
                                { key: 'fecha',        label: 'Fecha' },
                                { key: 'cliente',      label: 'Cliente' },
                                { key: 'estado_label', label: 'Estado' },
                            ],
                            onSelect: (row, fm) => fm.aplicarFiltro({ key: 'numero', value: row.numero }),
                            onOpen: (row, fm) => {
                                fm.hide();
                                // abrirModalFacturacionVer lee el documento del data-row de la fila.
                                const fila = { id: row.id, estado: row.estado, id_factura: row.id_factura, serie: row.serie, secuencial: row.secuencial };
                                setTimeout(() => window.abrirModalFacturacionVer({ dataset: { row: JSON.stringify(fila) } }), 350);
                            },
                        },
                        fields: <?= json_encode($filtrosFacturacion, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                        loadingTarget: '#grid-body',   // se atenúa mientras se busca
                        onApply: () => { g_paginaActual = 1; return cargarGrid(); },
                    }).init();
                });
            </script>

            <?php // FiltrosModal (extraId) mueve estos botones dentro del input-group del buscador; si el JS no corre, quedan aquí. ?>
            <div id="fmExtraFAC" class="btn-group btn-group-sm">
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas([
                    'fecha'      => 'Fecha',
                    'secuencial' => 'Secuencial',
                    'cliente'    => 'Cliente',
                    'factura'    => 'Factura',
                    'observaciones' => 'Observaciones',
                    'estado'     => 'Estado'
                ], $vistaConfig ?? [], 'facturacion-cv'); ?>

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
        <div class="factcv-scroll w-100">
            <table class="table table-hover table-sm mb-0" id="tablaFacturacion">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-col="fecha">Fecha <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-col="secuencial">Secuencial <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-col="cliente">Cliente <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-col="factura">Factura <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-col="observaciones">Observaciones <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center pe-3 sortable-header" role="button" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="grid-body">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-receipt fs-3 d-block mb-2"></i>No hay facturaciones registradas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r):
                            $dataJson = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                            $badge = \App\controllers\modulos\FacturacionCvController::badgeEstado($r['estado'] ?? 'borrador', !empty($r['id_cambio_producto']));
                        ?>
                            <tr class="factcv-row" role="button" tabindex="0" data-row="<?= $dataJson ?>" onclick="abrirModalFacturacionVer(this)">
                                <td class="ps-3" data-col="fecha"><?= htmlspecialchars($r['fecha_emision'] ?? '') ?></td>
                                <td data-col="secuencial"><?= htmlspecialchars(($r['serie'] ?? '') . '-' . ($r['secuencial'] ?? '')) ?></td>
                                <td data-col="cliente" class="text-truncate" style="max-width:230px" title="<?= htmlspecialchars($r['cliente_nombre'] ?? '') ?>"><?= htmlspecialchars($r['cliente_nombre'] ?? '') ?></td>
                                <td data-col="factura"><?= htmlspecialchars($r['numero_factura'] ?? '—') ?></td>
                                <td data-col="observaciones" class="text-truncate" style="max-width:280px" title="<?= htmlspecialchars($r['observaciones'] ?? '') ?>"><?= htmlspecialchars($r['observaciones'] ?? '') ?></td>
                                <td class="text-center pe-3" data-col="estado"><?= $badge ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    window.RUTA_MODULO_FACCV = '<?= $urlBaseFac ?>';
    window.FACCV_PERM = {
        crear: <?= (!empty($perm['crear']) || !empty($perm['todo'])) ? 'true' : 'false' ?>,
        actualizar: <?= (!empty($perm['actualizar']) || !empty($perm['todo'])) ? 'true' : 'false' ?>,
        eliminar: <?= (!empty($perm['eliminar']) || !empty($perm['todo'])) ? 'true' : 'false' ?>,
        // Tras "Generar factura" se ofrece ir a Facturas de Venta solo si el usuario
        // puede ver ese módulo (solo UX: el destino valida su propio permiso al entrar).
        ver_factura_venta: <?= \App\Helpers\Permisos::puedeVer('modulos/factura-venta') ? 'true' : 'false' ?>
    };
    window.RUTA_MODULO_FACTURA_VENTA = '<?= rtrim($base, '/') ?>/modulos/factura-venta';
    window.EMPRESA_CONFIG = {
        facturacion_inventario: <?= (($empresa['facturacion_inventario'] ?? true) === 'true' || ($empresa['facturacion_inventario'] ?? true) === true) ? 'true' : 'false' ?>,
        decimales_precio: <?= (int) ($empresa['decimales_precio'] ?? 2) ?>,
        decimales_cantidad: <?= (int) ($empresa['decimales_cantidad'] ?? 2) ?>,
        id_forma_pago_sri_def: <?= (isset($empresa['id_forma_pago_sri_def']) && $empresa['id_forma_pago_sri_def'] !== null && $empresa['id_forma_pago_sri_def'] !== '') ? (int)$empresa['id_forma_pago_sri_def'] : 'null' ?>,
        editar_descuento_factura: <?= (($empresa['editar_descuento_factura'] ?? true) === 'true' || ($empresa['editar_descuento_factura'] ?? true) === true) ? 'true' : 'false' ?>,
        mostrar_cajero_factura: <?= (($empresa['mostrar_cajero_factura'] ?? false) === 'true' || ($empresa['mostrar_cajero_factura'] ?? false) === true) ? 'true' : 'false' ?>,
        mostrar_vendedor_factura: <?= (($empresa['mostrar_vendedor_factura'] ?? false) === 'true' || ($empresa['mostrar_vendedor_factura'] ?? false) === true) ? 'true' : 'false' ?>
    };
    // Cajero = usuario que genera la factura (igual que en Factura de Venta).
    window.FACCV_USUARIO_NOMBRE = '<?= htmlspecialchars($_SESSION['nombre'] ?? '', ENT_QUOTES) ?>';
</script>

<?php include __DIR__ . '/modal_facturacion.php'; ?>

<!-- Modal reutilizable de creación rápida de cliente -->
<?php
$permCli = ['crear' => true, 'actualizar' => true, 'eliminar' => false, 'todo' => true];
include dirname(__DIR__) . '/clientes/modal_cliente.php';
?>
<script src="<?= BASE_URL ?>/js/modulos/clientes_modal.js?v=<?= asset_ver('/js/modulos/clientes_modal.js') ?>"></script>

<script>
    let g_ordenCol = '<?= addslashes($ordenCol) ?>';
    let g_ordenDir = '<?= addslashes($ordenDir) ?>';
    let g_paginaActual = <?= (int)$page ?>;
    let g_buscar = '<?= addslashes($buscar) ?>';

    document.addEventListener("DOMContentLoaded", function () {
        document.querySelectorAll('#tablaFacturacion th.sortable-header').forEach(th => {
            th.addEventListener('click', () => {
                const col = th.dataset.col;
                if (g_ordenCol === col) { g_ordenDir = g_ordenDir === 'ASC' ? 'DESC' : 'ASC'; }
                else { g_ordenCol = col; g_ordenDir = 'ASC'; }
                actualizarIconosOrden(col, g_ordenDir, 'tablaFacturacion');
                if (typeof window.guardarOrdenacionVista === 'function') window.guardarOrdenacionVista('facturacion_cv', g_ordenCol, g_ordenDir);
                cargarGrid();
            });
        });
        // Pintar la flecha activa con el orden ya aplicado al cargar la página.
        actualizarIconosOrden(g_ordenCol, g_ordenDir, 'tablaFacturacion');
    });

    function actualizarIconosOrden(col, dir, tableId) {
        document.querySelectorAll(`#${tableId} th.sortable-header`).forEach(th => {
            const icon = th.querySelector('i');
            if (icon) {
                icon.className = 'bi bi-arrow-down-up text-muted ms-1';
                if (th.dataset.col === col) icon.className = dir === 'ASC' ? 'bi bi-sort-alpha-down text-primary ms-1' : 'bi bi-sort-alpha-up text-primary ms-1';
            }
        });
    }

    function cambiarPaginaAjax(p) { g_paginaActual = p; cargarGrid(); }

    async function cargarGrid() {
        // Mismo indicador que el buscador (FiltrosModal): la tabla se atenúa en vez de
        // vaciarse; también al paginar y ordenar, que llaman a esta función directo.
        const tbody = document.getElementById('grid-body');
        if (tbody) tbody.classList.add('fm-cargando-target');
        try {
            const b_input = document.getElementById('b');
            g_buscar = b_input ? b_input.value : '';
            const params = new URLSearchParams({ b: g_buscar, page: g_paginaActual, sort: g_ordenCol, dir: g_ordenDir });
            const res = await fetch(`${RUTA_MODULO_FACCV}/searchAjax?${params.toString()}`);
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
