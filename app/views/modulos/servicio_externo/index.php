<?php
/** @var string $titulo */
/** @var array $perm */
/** @var string $rutaModulo */
/** @var array $empresa */
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
/** @var array $seriesFiltro */
/** @var array $formasPago */
/** @var array $bodegas */

use App\controllers\modulos\ServicioExternoController;

$base = BASE_URL;
$urlBase = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');

$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>

<style>
    .se-header { flex-shrink: 0; }
    .servicioexterno-scroll {
        max-height: calc(100dvh - 240px);
        overflow-y: auto;
    }
    .servicioexterno-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }
    .se-row { cursor: pointer; }
    .se-row:hover { background-color: rgba(0, 0, 0, .04); }
    /* Grilla de ítems (igual que factura de venta) */
    #modalOrdenSE .table-detalle th { font-size: 0.7rem; text-transform: uppercase; padding: 4px 8px !important; background-color: #f8f9fa; }
    #modalOrdenSE .table-detalle td { padding: 0 !important; vertical-align: middle; }
    #modalOrdenSE .input-detalle { border: none; background: transparent; height: 30px !important; font-size: 0.82rem !important; padding: 2px 8px !important; }
    #modalOrdenSE .input-detalle:focus { background: #fff; box-shadow: inset 0 0 0 1px #0d6efd; outline: none; }
    #modalOrdenSE .row-detalle:hover { background-color: rgba(13, 110, 253, 0.03); }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>

<!-- Encabezado -->
<div class="se-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-tools text-info"></i> <?= htmlspecialchars($titulo) ?></h5>
    <div class="d-flex gap-2">
        <?php if (!empty($perm['crear']) || !empty($perm['todo'])): ?>
            <button type="button" class="btn btn-primary btn-sm px-3" onclick="seAbrirNuevo()">
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
            // "Filtros" (embudo) que abre un modal con todos los filtros + chips de los activos.
            // Las claves (key) deben existir en los mapas de ServicioExternoRepository::getListado().
            $opcionesSerie   = array_map(fn($x) => ['v' => $x['establecimiento'] . '-' . $x['punto_emision'], 'l' => $x['establecimiento'] . '-' . $x['punto_emision']], $seriesFiltro ?? []);
            $opcionesUsuario = array_map(fn($u) => ['v' => (string) $u['id'], 'l' => $u['nombre']], $usuariosFiltro ?? []);
            $tO = 'Orden';
            // Filas de 12 columnas:
            //   Orden:   [Fecha 6][Estado 3][Serie 3] [N° Orden 4][Secuencial 4][Total 4]
            //            [Documento generado 4][Tipo de documento 4][N° documento 4] [Fecha de finalización 6][Usuario 6]
            //   Equipo:  [Equipo 3][Marca 3][Modelo 3][N° de serie 3]
            //   Cliente y trabajo: [Cliente 3][RUC 3][Dirección 3][Trabajo / observaciones 3]
            $filtrosServicioExterno = [
                ['tab' => $tO, 'key' => 'fecha',          'label' => 'Fecha del servicio', 'icon' => 'bi-calendar-event',  'type' => 'date_range',   'grupo' => 'Orden', 'col' => 6, 'atajos' => true],
                ['tab' => $tO, 'key' => 'estado',         'label' => 'Estado',             'icon' => 'bi-flag',            'type' => 'select',       'grupo' => 'Orden', 'col' => 3, 'options' => [
                    ['v' => 'borrador',  'l' => 'Borrador'],
                    ['v' => 'facturado', 'l' => 'Facturado'],
                    ['v' => 'anulado',   'l' => 'Anulado'],
                ]],
                ['tab' => $tO, 'key' => 'serie',          'label' => 'Serie',              'icon' => 'bi-upc-scan',        'type' => 'select',       'grupo' => 'Orden', 'col' => 3, 'options' => $opcionesSerie],
                ['tab' => $tO, 'key' => 'orden',          'label' => 'N° Orden',           'icon' => 'bi-hash',            'type' => 'text',         'grupo' => 'Orden', 'col' => 4, 'placeholder' => '001-001-000000123'],
                ['tab' => $tO, 'key' => 'secuencial',     'label' => 'Secuencial',         'icon' => 'bi-123',             'type' => 'text',         'grupo' => 'Orden', 'col' => 4, 'placeholder' => 'Sin ceros'],
                ['tab' => $tO, 'key' => 'total',          'label' => 'Total',              'icon' => 'bi-currency-dollar', 'type' => 'number_range', 'grupo' => 'Orden', 'col' => 4],
                ['tab' => $tO, 'key' => 'con_documento',  'label' => 'Documento de venta', 'icon' => 'bi-receipt',         'type' => 'select',       'grupo' => 'Orden', 'col' => 4, 'options' => [
                    ['v' => 'si', 'l' => 'Con documento generado'],
                    ['v' => 'no', 'l' => 'Sin documento generado'],
                ]],
                ['tab' => $tO, 'key' => 'tipo_documento', 'label' => 'Tipo de documento',  'icon' => 'bi-tag',             'type' => 'select',       'grupo' => 'Orden', 'col' => 4, 'options' => [
                    ['v' => 'FACTURA', 'l' => 'Factura'],
                    ['v' => 'RECIBO',  'l' => 'Recibo de venta'],
                ]],
                ['tab' => $tO, 'key' => 'documento',      'label' => 'N° documento generado', 'icon' => 'bi-file-earmark-text', 'type' => 'text',     'grupo' => 'Orden', 'col' => 4],
                ['tab' => $tO, 'key' => 'finalizacion',   'label' => 'Fecha de finalización', 'icon' => 'bi-calendar-check', 'type' => 'date_range',  'grupo' => 'Orden', 'col' => 6],
                ['tab' => $tO, 'key' => 'usuario',        'label' => 'Usuario que registró', 'icon' => 'bi-person-gear',   'type' => 'select',       'grupo' => 'Orden', 'col' => 6, 'options' => $opcionesUsuario],
                ['tab' => $tO, 'key' => 'equipo',         'label' => 'Equipo',             'icon' => 'bi-tools',           'type' => 'text',         'grupo' => 'Equipo', 'col' => 3],
                ['tab' => $tO, 'key' => 'marca',          'label' => 'Marca',              'icon' => 'bi-badge-tm',        'type' => 'text',         'grupo' => 'Equipo', 'col' => 3],
                ['tab' => $tO, 'key' => 'modelo',         'label' => 'Modelo',             'icon' => 'bi-cpu',             'type' => 'text',         'grupo' => 'Equipo', 'col' => 3],
                ['tab' => $tO, 'key' => 'serie_equipo',   'label' => 'N° de serie del equipo', 'icon' => 'bi-upc',         'type' => 'text',         'grupo' => 'Equipo', 'col' => 3],
                ['tab' => $tO, 'key' => 'cliente',        'label' => 'Cliente',            'icon' => 'bi-person',          'type' => 'text',         'grupo' => 'Cliente y trabajo', 'col' => 3],
                ['tab' => $tO, 'key' => 'ruc',            'label' => 'RUC / Cédula',       'icon' => 'bi-card-text',       'type' => 'text',         'grupo' => 'Cliente y trabajo', 'col' => 3],
                ['tab' => $tO, 'key' => 'direccion',      'label' => 'Dirección del servicio', 'icon' => 'bi-geo-alt',     'type' => 'text',         'grupo' => 'Cliente y trabajo', 'col' => 3],
                ['tab' => $tO, 'key' => 'trabajo',        'label' => 'Trabajo / observaciones', 'icon' => 'bi-chat-left-text', 'type' => 'text',     'grupo' => 'Cliente y trabajo', 'col' => 3],
            ];
            ?>
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
            <div id="fmBuscadorSE"></div>
            <input type="hidden" id="b" name="b" value="<?= htmlspecialchars($buscar) ?>">

            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosModal) return;
                    window.SE_filtros = new FiltrosModal({
                        containerId: 'fmBuscadorSE',
                        hiddenInputId: 'b',
                        placeholder: 'Buscar en todas las columnas...',
                        titulo: 'Filtros de servicio externo',
                        inputWidth: 420,
                        extraId: 'fmExtraSE',   // columnas + PDF + Excel, pegados al final del grupo
                        // Pestaña Detalles: búsqueda libre dentro de las órdenes (servicios/productos).
                        busquedaDetalle: {
                            tab: 'Detalles',
                            url: '<?= $urlBase ?>/buscarDetallesAjax',
                            label: 'Buscar libremente dentro de las órdenes',
                            placeholder: 'Servicio, producto, código, bodega, monto...',
                            columns: [
                                { key: 'origen',       label: 'Tipo' },
                                { key: 'referencia',   label: 'Código', class: 'font-monospace' },
                                { key: 'descripcion',  label: 'Descripción' },
                                { key: 'cantidad',     label: 'Cant.', align: 'end' },
                                { key: 'monto',        label: 'Total', align: 'end' },
                                { key: 'numero_orden', label: 'Orden', class: 'font-monospace fw-semibold' },
                                { key: 'fecha',        label: 'Fecha' },
                                { key: 'equipo',       label: 'Equipo' },
                                { key: 'cliente',      label: 'Cliente' },
                                { key: 'estado',       label: 'Estado' },
                            ],
                            onSelect: (row, fm) => fm.aplicarFiltro({ key: 'orden', value: row.numero_orden }),
                            onOpen: (row, fm) => { fm.hide(); setTimeout(() => window.seAbrirVerId && window.seAbrirVerId(row.id_orden, false), 350); },
                        },
                        fields: <?= json_encode($filtrosServicioExterno, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                        loadingTarget: '#grid-body',   // se atenúa mientras se busca
                        onApply: () => { g_paginaActual = 1; return cargarGrid(); },
                    });
                    window.SE_filtros.init();
                });
            </script>

            <?php // FiltrosModal (extraId) mueve estos botones dentro del input-group del buscador; si el JS no corre, quedan aquí. ?>
            <div id="fmExtraSE" class="btn-group btn-group-sm">
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas([
                    'fecha_servicio' => 'Fecha',
                    'numero_orden'   => 'N° Orden',
                    'equipo'         => 'Equipo',
                    'cliente'        => 'Cliente',
                    'total'          => 'Total',
                    'estado'         => 'Estado',
                ], $vistaConfig ?? [], 'servicio-externo'); ?>

                <a class="btn btn-outline-danger pdf-export-btn" href="<?= $urlBase ?>/export-pdf?b=<?= urlencode($buscar) ?>&sort=<?= $ordenCol ?>&dir=<?= $ordenDir ?>" target="_blank" title="Exportar a PDF"><i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span></a>
                <a class="btn btn-outline-success excel-export-btn" href="<?= $urlBase ?>/export-excel?b=<?= urlencode($buscar) ?>&sort=<?= $ordenCol ?>&dir=<?= $ordenDir ?>" title="Exportar a Excel"><i class="bi bi-file-earmark-excel"></i><span class="d-none d-md-inline"> Excel</span></a>
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
        <div class="servicioexterno-scroll w-100">
            <table class="table table-hover table-sm mb-0" id="tablaServicioExterno">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-col="fecha_servicio">Fecha <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-col="numero_orden">N° Orden <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-col="equipo">Equipo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-col="cliente">Cliente <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header" role="button" data-col="total">Total <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center pe-3 sortable-header" role="button" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="grid-body">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-tools fs-3 d-block mb-2"></i>
                                No se encontraron órdenes.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r):
                            $fecha = !empty($r['fecha_servicio']) ? date('d-m-Y H:i', strtotime($r['fecha_servicio'])) : '';
                            $dataJson = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                        ?>
                            <tr class="se-row" role="button" tabindex="0" data-row="<?= $dataJson ?>" onclick="seAbrirVer(this)">
                                <td class="ps-3" data-col="fecha_servicio"><?= htmlspecialchars($fecha) ?></td>
                                <td data-col="numero_orden" class="fw-bold text-primary"><?= htmlspecialchars($r['numero_orden'] ?? '') ?></td>
                                <td data-col="equipo" class="text-truncate" style="max-width:220px" title="<?= htmlspecialchars($r['equipo_descripcion'] ?? '') ?>"><?= htmlspecialchars($r['equipo_descripcion'] ?? '') ?></td>
                                <td data-col="cliente" class="text-truncate" style="max-width:220px" title="<?= htmlspecialchars($r['cliente_nombre'] ?? '') ?>"><?= htmlspecialchars($r['cliente_nombre'] ?? '') ?></td>
                                <td data-col="total" class="text-end"><?= number_format((float)($r['total'] ?? 0), 2) ?></td>
                                <td class="text-center pe-3" data-col="estado"><?= ServicioExternoController::badgeEstado($r['estado'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Globales para el modal (IIFE) -->
<script>
    window.RUTA_MODULO_SE = '<?= $urlBase ?>';
    window.SE_PERM = {
        crear:      <?= (!empty($perm['crear']) || !empty($perm['todo'])) ? 'true' : 'false' ?>,
        actualizar: <?= (!empty($perm['actualizar']) || !empty($perm['todo'])) ? 'true' : 'false' ?>,
        eliminar:   <?= (!empty($perm['eliminar']) || !empty($perm['todo'])) ? 'true' : 'false' ?>
    };
    <?php $bt = fn($k, $def) => ((($empresa[$k] ?? $def) === 'true' || ($empresa[$k] ?? $def) === true) ? 'true' : 'false'); ?>
    window.EMPRESA_CONFIG = {
        facturacion_libre: <?= $bt('facturacion_libre', false) ?>,
        facturacion_inventario: <?= $bt('facturacion_inventario', true) ?>,
        obligatorio_lotes: <?= $bt('obligatorio_lotes', false) ?>,
        obligatorio_caducidad: <?= $bt('obligatorio_caducidad', false) ?>,
        obligatorio_nup: <?= $bt('obligatorio_nup', false) ?>,
        mostrar_unidad_medida: <?= $bt('mostrar_unidad_medida', true) ?>,
        editar_precio_factura: <?= $bt('editar_precio_factura', true) ?>,
        editar_iva_factura: <?= $bt('editar_iva_factura', true) ?>,
        editar_descuento_factura: <?= $bt('editar_descuento_factura', true) ?>,
        calculo_iva: '<?= $empresa['calculo_iva_facturacion'] ?? 'linea_linea' ?>',
        decimales_precio: <?= (int)($empresa['decimales_precio'] ?? 2) ?>,
        decimales_cantidad: <?= (int)($empresa['decimales_cantidad'] ?? 2) ?>
    };
    window.TARIFAS_IVA = <?= json_encode($tarifasIva ?? []) ?>;
    window.UNIDADES = <?= json_encode($unidades ?? []) ?>;
    window.USUARIO_NOMBRE = '<?= htmlspecialchars($_SESSION['nombre'] ?? '', ENT_QUOTES) ?>';
    window.SE_PUNTOS = <?= json_encode(array_map(fn($p) => [
        'id'                  => (int)$p['id'],
        'id_establecimiento'  => (int)($p['id_establecimiento'] ?? 0),
        'cod_establecimiento' => $p['cod_establecimiento'] ?? '',
        'codigo_punto'        => $p['codigo_punto'] ?? '',
    ], $puntos ?? [])) ?>;
    window.SE_FORMAS_PAGO = <?= json_encode($formasPago ?? []) ?>;
    window.SE_BODEGAS = <?= json_encode(array_map(fn($b) => ['id' => (int)$b['id'], 'nombre' => $b['nombre'] ?? ''], $bodegas ?? [])) ?>;
</script>

<?php // Variables JS de favoritos/vistas (para la estrella de la serie, igual que factura)
echo \App\Helpers\PreferenciasHelper::getJavascriptVariables($rutaModulo); ?>

<?php include __DIR__ . '/modal_orden.php'; ?>

<!-- Modales reutilizados para crear entidades al vuelo (mismo patrón que Factura) -->
<?php
    include dirname(__DIR__) . '/clientes/modal_cliente.php';
    include dirname(__DIR__) . '/productos/modal.php';
?>
<script src="<?= BASE_URL ?>/js/modulos/clientes_modal.js?v=<?= asset_ver('/js/modulos/clientes_modal.js') ?>"></script>
<script src="<?= BASE_URL ?>/js/modulos/productos_modal.js?v=<?= asset_ver('/js/modulos/productos_modal.js') ?>"></script>

<!-- Dropdown global de búsqueda de productos de la grilla (igual que factura) -->
<div id="se-dropdown-productos-global" class="list-group shadow position-fixed d-none" style="z-index: 9999; min-width: 400px; max-height: 250px; overflow-y: auto; background-color: white;"></div>

<script src="<?= BASE_URL ?>/js/modulos/servicio_externo.js?v=<?= asset_ver('/js/modulos/servicio_externo.js') ?>"></script>

<script>
    let g_ordenCol = '<?= addslashes($ordenCol) ?>';
    let g_ordenDir = '<?= addslashes($ordenDir) ?>';
    let g_paginaActual = <?= (int)$page ?>;
    let g_buscar = '<?= addslashes($buscar) ?>';

    document.addEventListener("DOMContentLoaded", function () {
        document.querySelectorAll('#tablaServicioExterno th.sortable-header').forEach(th => {
            th.addEventListener('click', () => {
                const col = th.dataset.col;
                if (g_ordenCol === col) g_ordenDir = g_ordenDir === 'ASC' ? 'DESC' : 'ASC';
                else { g_ordenCol = col; g_ordenDir = 'ASC'; }
                actualizarIconosOrden(col, g_ordenDir);
                cargarGrid();
            });
        });
    });

    function actualizarIconosOrden(col, dir) {
        document.querySelectorAll('#tablaServicioExterno th.sortable-header').forEach(th => {
            const icon = th.querySelector('i');
            if (icon) {
                icon.className = 'bi bi-arrow-down-up text-muted ms-1';
                if (th.dataset.col === col) icon.className = dir === 'ASC' ? 'bi bi-sort-down text-primary ms-1' : 'bi bi-sort-up text-primary ms-1';
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
            const res = await fetch(`${window.RUTA_MODULO_SE}/searchAjax?${params.toString()}`);
            const data = await res.json();
            if (data.ok) {
                tbody.innerHTML = data.rows;
                document.getElementById('pagination-info').textContent = data.info;
                document.getElementById('pagination-controls').innerHTML = data.pagination;
                const pdf = document.querySelector('.pdf-export-btn'); if (pdf) pdf.href = data.pdf_url;
                const xls = document.querySelector('.excel-export-btn'); if (xls) xls.href = data.excel_url;
            }
        } catch (e) { console.error(e); Swal.fire('Error', 'No se pudo cargar la lista', 'error'); }
        finally { if (tbody) tbody.classList.remove('fm-cargando-target'); }
    }
</script>
