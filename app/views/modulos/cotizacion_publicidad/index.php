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
/** @var array $vendedores */
/** @var array $tarifasIva */
/** @var array $categorias */
/** @var array $vistaConfig */

$base    = BASE_URL;
$urlBase = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');

$rows       = $rows ?? [];
$total      = $total ?? 0;
$page       = $page ?? 1;
$totalPages = $totalPages ?? 1;
$perPage    = $perPage ?? 20;
$ordenCol   = $ordenCol ?? 'fecha_emision';
$ordenDir   = $ordenDir ?? 'DESC';
$buscar     = $buscar ?? '';
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-megaphone me-1 text-primary"></i><?= htmlspecialchars($titulo) ?></h5>
    <?php if (!empty($perm['crear'])): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="CP.nueva()">
            <i class="bi bi-plus-lg me-1"></i>Nueva
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php
            // Buscador estándar: texto libre sobre las columnas del listado (sin sugerencias),
            // botón embudo que abre el modal de filtros y chips dentro de la caja.
            // Las claves (key) deben existir en los mapas de CotizacionPublicidadRepository::getListado().
            $opcionesVendedor  = array_map(fn($x) => ['v' => (string) $x['id'], 'l' => $x['nombre']], $vendedoresFiltro ?? []);
            $opcionesUsuario   = array_map(fn($x) => ['v' => (string) $x['id'], 'l' => $x['nombre']], $usuariosFiltro ?? []);
            $opcionesCategoria = array_map(fn($x) => ['v' => (string) $x['id'], 'l' => $x['nombre']], $categoriasFiltro ?? []);
            $tC = 'Cotización';
            // Orden pensado en filas de 12 columnas:
            //   Documento: [Fecha de emisión 6][Estado 3][Nº cotización 3]
            //              [Número 3][Versión 3][Categoría 3][Usuario que registró 3]
            //   Valores:   [Total 4][Presupuesto 4][Comisión % 4]
            //              [Subtotal 4][Valor comisión 4][IVA 4]
            //   Cliente:   [Cliente 3][RUC / CI 3][Ejecutivo 3][Contacto 3]
            //              [Proyecto 4][Observaciones 4][Factura generada 4]
            $filtrosCotizaciones = [
                // ── Documento ──
                ['tab' => $tC, 'key' => 'fecha',  'label' => 'Fecha de emisión', 'icon' => 'bi-calendar-event', 'type' => 'date_range', 'grupo' => 'Documento', 'col' => 6, 'atajos' => true],
                ['tab' => $tC, 'key' => 'estado', 'label' => 'Estado',           'icon' => 'bi-flag',           'type' => 'select',     'grupo' => 'Documento', 'col' => 3, 'options' => [
                    ['v' => 'borrador',   'l' => 'Borrador'],
                    ['v' => 'aprobada',   'l' => 'Aprobada'],
                    ['v' => 'rechazada',  'l' => 'Rechazada'],
                    ['v' => 'convertida', 'l' => 'Convertida'],
                    ['v' => 'anulada',    'l' => 'Anulada'],
                ]],
                // Nº visible exacto (número-año V versión), el mismo formato de la columna Número.
                ['tab' => $tC, 'key' => 'cotizacion',   'label' => 'Nº cotización',        'icon' => 'bi-upc',            'type' => 'text',         'grupo' => 'Documento', 'col' => 3, 'placeholder' => '001-2026 V1'],
                ['tab' => $tC, 'key' => 'numero',       'label' => 'Número',               'icon' => 'bi-hash',           'type' => 'number_range', 'grupo' => 'Documento', 'col' => 3],
                ['tab' => $tC, 'key' => 'version',      'label' => 'Versión',              'icon' => 'bi-layers',         'type' => 'number_range', 'grupo' => 'Documento', 'col' => 3],
                ['tab' => $tC, 'key' => 'id_categoria', 'label' => 'Categoría',            'icon' => 'bi-tags',           'type' => 'select',       'grupo' => 'Documento', 'col' => 3, 'options' => $opcionesCategoria],
                ['tab' => $tC, 'key' => 'id_usuario',   'label' => 'Usuario que registró', 'icon' => 'bi-person-gear',    'type' => 'select',       'grupo' => 'Documento', 'col' => 3, 'options' => $opcionesUsuario],
                // ── Valores ──
                ['tab' => $tC, 'key' => 'total',          'label' => 'Total',          'icon' => 'bi-currency-dollar', 'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                ['tab' => $tC, 'key' => 'presupuesto',    'label' => 'Presupuesto',    'icon' => 'bi-cash-stack',      'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                ['tab' => $tC, 'key' => 'comision',       'label' => 'Comisión %',     'icon' => 'bi-percent',         'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                ['tab' => $tC, 'key' => 'subtotal',       'label' => 'Subtotal',       'icon' => 'bi-receipt-cutoff',  'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                ['tab' => $tC, 'key' => 'valor_comision', 'label' => 'Valor comisión', 'icon' => 'bi-coin',            'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                ['tab' => $tC, 'key' => 'iva',            'label' => 'IVA',            'icon' => 'bi-percent',         'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                // ── Cliente ──
                ['tab' => $tC, 'key' => 'cliente',     'label' => 'Cliente',       'icon' => 'bi-person',            'type' => 'text',   'grupo' => 'Cliente', 'col' => 3],
                ['tab' => $tC, 'key' => 'ruc',         'label' => 'RUC / CI',      'icon' => 'bi-card-text',         'type' => 'text',   'grupo' => 'Cliente', 'col' => 3],
                ['tab' => $tC, 'key' => 'id_vendedor', 'label' => 'Ejecutivo',     'icon' => 'bi-person-badge',      'type' => 'select', 'grupo' => 'Cliente', 'col' => 3, 'options' => $opcionesVendedor],
                ['tab' => $tC, 'key' => 'contacto',    'label' => 'Contacto',      'icon' => 'bi-person-lines-fill', 'type' => 'text',   'grupo' => 'Cliente', 'col' => 3],
                ['tab' => $tC, 'key' => 'proyecto',    'label' => 'Proyecto',      'icon' => 'bi-kanban',            'type' => 'text',   'grupo' => 'Cliente', 'col' => 4],
                ['tab' => $tC, 'key' => 'obs',         'label' => 'Observaciones', 'icon' => 'bi-chat-left-text',    'type' => 'text',   'grupo' => 'Cliente', 'col' => 4],
                ['tab' => $tC, 'key' => 'factura',     'label' => 'Factura generada', 'icon' => 'bi-receipt',        'type' => 'text',   'grupo' => 'Cliente', 'col' => 4, 'placeholder' => '001-001-000000123'],
            ];
            ?>
            <link rel="stylesheet" href="<?= rtrim($base, '/') ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
            <script src="<?= rtrim($base, '/') ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
            <div id="fmBuscadorCP"></div>
            <input type="hidden" id="buscarCotizacion" value="<?= htmlspecialchars($buscar) ?>">
            <script>
            document.addEventListener('DOMContentLoaded', () => {
                if (!window.FiltrosModal) return;
                new FiltrosModal({
                    containerId: 'fmBuscadorCP',
                    hiddenInputId: 'buscarCotizacion',
                    placeholder: 'Buscar en todas las columnas...',
                    titulo: 'Filtros de cotizaciones',
                    inputWidth: 420,
                    extraId: 'fmExtraCP',   // columnas + Excel, pegados al final del grupo
                    // Pestaña Detalles: búsqueda libre dentro de las cotizaciones (líneas
                    // cotizadas y costos por proveedor). Cada coincidencia dice a qué
                    // cotización pertenece.
                    busquedaDetalle: {
                        tab: 'Detalles',
                        url: `<?= BASE_URL ?>/<?= $rutaModulo ?>/buscarDetallesAjax`,
                        label: 'Buscar libremente dentro de las cotizaciones',
                        placeholder: 'Descripción, categoría, valor, proveedor, factura del proveedor...',
                        columns: [
                            { key: 'origen',      label: 'Tipo' },
                            { key: 'tipo',        label: 'Categoría / Proveedor' },
                            { key: 'descripcion', label: 'Descripción' },
                            { key: 'extra',       label: 'Factura proveedor', class: 'font-monospace' },
                            { key: 'cantidad',    label: 'Cant.', align: 'end' },
                            { key: 'monto',       label: 'Valor', align: 'end' },
                            { key: 'numero',      label: 'Cotización', class: 'font-monospace fw-semibold' },
                            { key: 'fecha',       label: 'Fecha' },
                            { key: 'cliente',     label: 'Cliente' },
                            { key: 'estado',      label: 'Estado' },
                        ],
                        onSelect: (row, fm) => fm.aplicarFiltro({ key: 'cotizacion', value: row.numero }),
                        onOpen: (row, fm) => { fm.hide(); setTimeout(() => window.CP && CP.verDetalle(row.id_cotizacion), 350); },
                    },
                    fields: <?= json_encode($filtrosCotizaciones, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                    loadingTarget: '#tbodyCotizaciones',   // se atenúa mientras se busca
                    onApply: () => window.fetchSearch && window.fetchSearch(1),
                }).init();
            });
            </script>

            <?php // FiltrosModal (extraId) mueve estos botones dentro del grupo del buscador; si el JS no corre, quedan aquí. ?>
            <div id="fmExtraCP" class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'numero'          => 'Número',
                    'fecha_emision'   => 'Fecha',
                    'cliente_nombre'  => 'Cliente',
                    'contacto'        => 'Contacto',
                    'proyecto'        => 'Proyecto',
                    'vendedor_nombre' => 'Ejecutivo',
                    'presupuesto'     => 'Presupuesto',
                    'comision'        => 'Comisión %',
                    'importe_total'   => 'Total',
                    'estado'          => 'Estado',
                    'observaciones'   => 'Observaciones',
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>

                <a id="btnExportExcel" href="<?= $urlBase ?>/exportExcelAjax?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                    class="btn btn-outline-success" title="Exportar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i><span class="d-none d-md-inline"> Excel</span>
                </a>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="paginationContainer" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" <?= $page <= 1 ? 'disabled' : '' ?> onclick="cambiarPaginaAjax(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" <?= $page >= $totalPages ? 'disabled' : '' ?> onclick="cambiarPaginaAjax(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="cotizacion-publicidad-scroll w-100" style="max-height:calc(100dvh - 240px);overflow-y:auto;">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="numero"          data-col="numero">          Número        <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header"       role="button" data-sort="fecha_emision"  data-col="fecha_emision">   Fecha         <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header"       role="button" data-sort="cliente_nombre" data-col="cliente_nombre">  Cliente       <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th data-col="contacto">Contacto</th>
                        <th class="sortable-header"       role="button" data-sort="proyecto"       data-col="proyecto">        Proyecto      <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header"       role="button" data-sort="vendedor_nombre" data-col="vendedor_nombre">Ejecutivo     <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-end" role="button" data-sort="presupuesto"    data-col="presupuesto">    Presupuesto   <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-end" role="button" data-sort="comision"      data-col="comision">      Comisión %    <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-end" role="button" data-sort="importe_total" data-col="importe_total"> Total         <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-center" role="button" data-sort="estado"    data-col="estado">         Estado        <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th data-col="observaciones">Observaciones</th>
                    </tr>
                </thead>
                <tbody id="tbodyCotizaciones">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="11" class="text-center py-5 text-muted">
                            <i class="bi bi-megaphone fs-3 d-block mb-2"></i>No se encontraron cotizaciones.
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r):
                            $estado = $r['estado'] ?? 'borrador';
                            $estadoClass = match($estado) {
                                'aprobada'   => 'bg-success bg-opacity-10 text-success border border-success border-opacity-25',
                                'anulada'    => 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25',
                                'convertida' => 'bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25',
                                'rechazada'  => 'bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25',
                                default      => 'bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25',
                            };
                            $estadoLabel = match($estado) {
                                'borrador' => 'Borrador', 'aprobada' => 'Aprobada', 'rechazada' => 'Rechazada',
                                'convertida' => 'Convertida', 'anulada' => 'Anulada', default => ucfirst($estado),
                            };
                            $numero = str_pad((string)$r['numero'], 3, '0', STR_PAD_LEFT) . '-' . date('Y', strtotime($r['fecha_emision'])) . ' V' . $r['version'];
                            $fecha  = !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '-';
                        ?>
                        <tr class="cotpub-row" role="button" tabindex="0"
                            data-row='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>'
                            onclick="CP.verDetalle(<?= (int)$r['id'] ?>)">
                            <td class="ps-3 fw-medium" data-col="numero"><code class="text-secondary"><?= htmlspecialchars($numero) ?></code></td>
                            <td data-col="fecha_emision"><?= $fecha ?></td>
                            <td class="text-truncate" style="max-width:200px;" data-col="cliente_nombre"><?= htmlspecialchars($r['cliente_nombre'] ?? '-') ?></td>
                            <td data-col="contacto"><?= htmlspecialchars($r['contacto'] ?? '-') ?></td>
                            <td class="text-truncate" style="max-width:180px;" data-col="proyecto"><?= htmlspecialchars($r['proyecto'] ?? '-') ?></td>
                            <td data-col="vendedor_nombre"><?= htmlspecialchars($r['vendedor_nombre'] ?? '-') ?></td>
                            <td class="text-end" data-col="presupuesto">$<?= number_format((float)($r['presupuesto'] ?? 0), 2) ?></td>
                            <td class="text-end" data-col="comision"><?= number_format((float)($r['comision'] ?? 0), 2) ?>%</td>
                            <td class="text-end fw-semibold" data-col="importe_total">$<?= number_format((float)($r['importe_total'] ?? 0), 2) ?></td>
                            <td class="text-center" data-col="estado">
                                <span class="badge <?= $estadoClass ?>"><?= $estadoLabel ?></span>
                            </td>
                            <td class="text-truncate text-muted small" style="max-width:180px;" data-col="observaciones"><?= htmlspecialchars(mb_substr($r['observaciones'] ?? '', 0, 60)) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
window.BASE_URL   = '<?= $base ?>';
window.CP_CONFIG   = {
    urlBase:    '<?= $urlBase ?>',
    tarifasIva: <?= json_encode(array_values($tarifasIva ?? [])) ?>,
    categorias: <?= json_encode(array_values($categorias ?? [])) ?>,
    storageKey: 'cp_borrador_<?= (int)($_SESSION['id_empresa'] ?? 0) ?>_<?= (int)($_SESSION['id_usuario'] ?? 0) ?>',
    perm: {
        ver:        <?= json_encode(!empty($perm['ver'])) ?>,
        crear:      <?= json_encode(!empty($perm['crear'])) ?>,
        actualizar: <?= json_encode(!empty($perm['actualizar'])) ?>,
        eliminar:   <?= json_encode(!empty($perm['eliminar'])) ?>,
    },
};
</script>

<?php include __DIR__ . '/modal_cotizacion_publicidad.php'; ?>
<script src="<?= $base ?>/js/modulos/cotizacion_publicidad_modal.js?v=<?= asset_ver('/js/modulos/cotizacion_publicidad_modal.js') ?>"></script>

<!-- Elementos sombra para evitar errores en los scripts originales de clientes/productos -->
<div id="shadow-elements" class="d-none">
    <input id="buscarCliente">
    <input id="buscarProducto">
    <div id="tbodyClientes"></div>
    <div id="tbodyProductos"></div>
</div>
<?php
// Incluir los modales COMPLETOS de Clientes y Productos (botones "Cliente nuevo" /
// "Producto nuevo" de la barra de acciones), reutilizando su Controller/Service/JS
// tal cual — mismo patrón que app/views/modulos/factura_venta/index.php.
$rutaModuloOriginal = $rutaModulo;
$permOriginal       = $perm;
$ordenColOriginal    = $ordenCol;
$ordenDirOriginal    = $ordenDir;
$pageOriginal        = $page ?? 1;
$totalPagesOriginal  = $totalPages ?? 1;

$urlBaseClientes = BASE_URL . '/modulos/clientes';
$perm       = ['crear' => true, 'editar' => true, 'eliminar' => true];
$rutaModulo = 'modulos/productos';
$canCreateVend = true;
$ordenCol   = 'nombre';
$ordenDir   = 'ASC';
$page       = 1;
$totalPages = 1;

include dirname(__DIR__) . '/clientes/modal_cliente.php';
include dirname(__DIR__) . '/productos/modal.php';

$rutaModulo = $rutaModuloOriginal;
$perm       = $permOriginal;
$ordenCol   = $ordenColOriginal;
$ordenDir   = $ordenDirOriginal;
$page       = $pageOriginal;
$totalPages = $totalPagesOriginal;
?>
<script src="<?= $base ?>/js/modulos/clientes_modal.js?v=<?= asset_ver('/js/modulos/clientes_modal.js') ?>"></script>
<script src="<?= $base ?>/js/modulos/productos_modal.js?v=<?= asset_ver('/js/modulos/productos_modal.js') ?>"></script>

<script>
(function () {
    'use strict';
    const urlBase   = '<?= $urlBase ?>';
    const inputBusc = document.getElementById('buscarCotizacion');
    window.currentSort = '<?= $ordenCol ?>';
    window.currentDir  = '<?= $ordenDir ?>';
    window.currentPage = <?= $page ?>;

    let timerId;
    const debounce = (fn, ms = 350) => (...a) => { clearTimeout(timerId); timerId = setTimeout(() => fn(...a), ms); };

    window.cambiarPaginaAjax = (n) => window.fetchSearch(n);

    window.fetchSearch = async (page = 1) => {
        const b = inputBusc ? inputBusc.value.trim() : '';
        const uri = `${urlBase}/searchAjax?b=${encodeURIComponent(b)}&page=${page}&sort=${window.currentSort}&dir=${window.currentDir}`;
        // Mismo indicador que el buscador (FiltrosModal): la tabla se atenúa mientras se
        // busca, también al paginar u ordenar (que llaman a esta función directo).
        const tbody = document.getElementById('tbodyCotizaciones');
        if (tbody) tbody.classList.add('fm-cargando-target');
        try {
            const data = await (await fetch(uri)).json();
            if (!data.ok) return;
            window.currentPage = page;
            document.getElementById('tbodyCotizaciones').innerHTML      = data.rows;
            document.getElementById('paginationContainer').innerHTML    = data.pagination;
            document.getElementById('paginationInfo').textContent       = data.info;
            if (data.excel_url) document.getElementById('btnExportExcel').href = data.excel_url;

            document.querySelectorAll('.sortable-header').forEach(th => {
                const icon  = th.querySelector('i');
                const field = th.dataset.sort;
                icon.className = field === window.currentSort
                    ? (window.currentDir.toLowerCase() === 'asc' ? 'bi bi-sort-alpha-down text-primary ms-1' : 'bi bi-sort-alpha-up text-primary ms-1')
                    : 'bi bi-arrow-down-up small text-muted ms-1';
            });
        } catch (e) { console.error('Error búsqueda cotizaciones:', e); }
        finally { if (tbody) tbody.classList.remove('fm-cargando-target'); }
    };

    document.querySelectorAll('.sortable-header').forEach(h => {
        h.addEventListener('click', () => {
            const f = h.dataset.sort;
            if (window.currentSort === f) window.currentDir = window.currentDir.toLowerCase() === 'asc' ? 'DESC' : 'ASC';
            else { window.currentSort = f; window.currentDir = 'ASC'; }
            if (typeof window.guardarOrdenacionVista === 'function') window.guardarOrdenacionVista('<?= $rutaModulo ?>', window.currentSort, window.currentDir);
            fetchSearch(1);
        });
    });

    if (inputBusc) inputBusc.addEventListener('input', debounce(() => fetchSearch(1), 400));
})();
</script>

<style>
.cotpub-row { cursor: pointer; }
.cotpub-row:hover { background-color: rgba(0,0,0,.04); }
.cotizacion-publicidad-scroll thead th { position: sticky; top: 0; z-index: 1; }
</style>
