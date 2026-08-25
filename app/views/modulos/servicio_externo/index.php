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
        <div class="d-flex align-items-center gap-2">
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_busqueda.css?v=<?= time() ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_busqueda.js?v=<?= time() ?>"></script>
            <div id="fbBuscadorSE" style="width: 460px;"></div>
            <input type="hidden" id="b" name="b" value="<?= htmlspecialchars($buscar) ?>">

            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosBusqueda) return;
                    new FiltrosBusqueda({
                        containerId: 'fbBuscadorSE',
                        hiddenInputId: 'b',
                        fields: [
                            { key: 'orden',   label: 'N° Orden', icon: 'bi-hash',    type: 'text' },
                            { key: 'equipo',  label: 'Equipo',   icon: 'bi-tools',   type: 'text' },
                            { key: 'cliente', label: 'Cliente',  icon: 'bi-person',  type: 'text' },
                            { key: 'estado',  label: 'Estado',   icon: 'bi-flag',    type: 'select', options: [
                                { v: 'borrador',  l: 'Borrador' },
                                { v: 'facturado', l: 'Facturado' },
                                { v: 'anulado',   l: 'Anulado' },
                            ]},
                            { key: 'serie',     label: 'Serie',       icon: 'bi-upc-scan', type: 'select', options: [
                                <?php foreach ($puntos as $p): ?>
                                { v: '<?= $p['cod_establecimiento'] ?>-<?= $p['codigo_punto'] ?>', l: '<?= $p['cod_establecimiento'] ?>-<?= $p['codigo_punto'] ?>' },
                                <?php endforeach; ?>
                            ]},
                            { key: 'secuencial', label: 'Secuencial', icon: 'bi-123',      type: 'text' },
                        ],
                        onApply: () => { g_paginaActual = 1; cargarGrid(); },
                    }).init();
                });
            </script>

            <div class="btn-group btn-group-sm">
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas([
                    'fecha_servicio' => 'Fecha',
                    'numero_orden'   => 'N° Orden',
                    'equipo'         => 'Equipo',
                    'cliente'        => 'Cliente',
                    'total'          => 'Total',
                    'estado'         => 'Estado',
                ], $vistaConfig ?? [], 'servicio-externo'); ?>

                <a class="btn btn-outline-danger pdf-export-btn" href="<?= $urlBase ?>/export-pdf?b=<?= urlencode($buscar) ?>&sort=<?= $ordenCol ?>&dir=<?= $ordenDir ?>" target="_blank" title="Exportar a PDF"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                <a class="btn btn-outline-success excel-export-btn" href="<?= $urlBase ?>/export-excel?b=<?= urlencode($buscar) ?>&sort=<?= $ordenCol ?>&dir=<?= $ordenDir ?>" title="Exportar a Excel"><i class="bi bi-file-earmark-excel"></i> Excel</a>
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
<script src="<?= BASE_URL ?>/js/modulos/clientes_modal.js?v=<?= time() ?>"></script>
<script src="<?= BASE_URL ?>/js/modulos/productos_modal.js?v=<?= time() ?>"></script>

<!-- Dropdown global de búsqueda de productos de la grilla (igual que factura) -->
<div id="se-dropdown-productos-global" class="list-group shadow position-fixed d-none" style="z-index: 9999; min-width: 400px; max-height: 250px; overflow-y: auto; background-color: white;"></div>

<script src="<?= BASE_URL ?>/js/modulos/servicio_externo.js?v=<?= time() ?>"></script>

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
        try {
            const tbody = document.getElementById('grid-body');
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></td></tr>';
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
    }
</script>
