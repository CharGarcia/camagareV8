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
        <?php if (!empty($perm['crear']) || !empty($perm['todo'])): ?>
            <button type="button" class="btn btn-primary btn-sm px-3" onclick="abrirModalConsignacionNueva()">
                <i class="bi bi-plus-lg"></i> Nueva
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Filtros y Opciones -->
<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            
            <div class="d-flex align-items-center gap-2">
                <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_busqueda.css?v=<?= time() ?>">
                <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_busqueda.js?v=<?= time() ?>"></script>
                <div id="fbBuscadorCONS" style="width: 480px;"></div>
                <input type="hidden" id="b" name="b" value="<?= htmlspecialchars($buscar) ?>">
                
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        if (!window.FiltrosBusqueda) return;
                        new FiltrosBusqueda({
                            containerId: 'fbBuscadorCONS',
                            hiddenInputId: 'b',
                            fields: [
                                { key: 'serie',      label: 'Serie/Secuencial',  icon: 'bi-hash',           type: 'text' },
                                { key: 'cliente',    label: 'Cliente',           icon: 'bi-person',         type: 'text' },
                                { key: 'vendedor',   label: 'Vendedor',          icon: 'bi-person-badge',   type: 'text' },
                                { key: 'estado',     label: 'Estado',            icon: 'bi-flag',           type: 'select', options: [
                                    { v: 'Emitida',   l: 'Emitida' },
                                    { v: 'Borrador',  l: 'Borrador' },
                                    { v: 'Entregada', l: 'Entregada' },
                                    { v: 'Anulada',   l: 'Anulada' },
                                ]},
                            ],
                            quickFilters: [
                                { id: 'qf_emitida',   label: 'Emitidas',   mk: () => ({ key: 'estado', op: '=', value: 'Emitida',   display: 'Emitidas' }) },
                                { id: 'qf_entregada', label: 'Entregadas', mk: () => ({ key: 'estado', op: '=', value: 'Entregada', display: 'Entregadas' }) },
                            ],
                            onApply: () => { g_paginaActual = 1; cargarGrid(); },
                        }).init();
                    });
                </script>

                <div class="btn-group btn-group-sm">
                    <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas([
                        'fecha_emision' => 'Fecha',
                        'secuencial' => 'Secuencial',
                        'cliente' => 'Cliente',
                        'vendedor' => 'Asesor',
                        'observaciones' => 'Observaciones',
                        'estado' => 'Estado'
                    ], $vistaConfig ?? [], 'consignaciones-ventas'); ?>

                    <a class="btn btn-outline-danger pdf-export-btn" href="<?= BASE_URL ?>/<?= $rutaModulo ?>/export-pdf?b=<?= urlencode($buscar) ?>&sort=<?= $ordenCol ?>&dir=<?= $ordenDir ?>" target="_blank" title="Exportar a PDF"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                    <a class="btn btn-outline-success excel-export-btn" href="<?= BASE_URL ?>/<?= $rutaModulo ?>/export-excel?b=<?= urlencode($buscar) ?>&sort=<?= $ordenCol ?>&dir=<?= $ordenDir ?>" title="Exportar a Excel"><i class="bi bi-file-earmark-excel"></i> Excel</a>
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
                                    <td data-col="secuencial" class="fw-bold text-primary"><?= htmlspecialchars(($r['serie'] ?? '') . '-' . ($r['secuencial'] ?? '')) ?></td>
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
        try {
            const tbody = document.getElementById('grid-body');
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></td></tr>';
            
            const b_input = document.getElementById('b');
            g_buscar = b_input ? b_input.value : '';

            const params = new URLSearchParams({
                b: g_buscar,
                page: g_paginaActual,
                sort: g_ordenCol,
                dir: g_ordenDir
            });
            
            const res = await fetch(`${RUTA_MODULO_CONSIGNACION}/searchAjax?${params.toString()}`);
            if(!res.ok) throw new Error('Error en red');
            const data = await res.json();
            
            if(data.ok) {
                tbody.innerHTML = data.rows;
                document.getElementById('pagination-info').textContent = data.info;
                document.getElementById('pagination-controls').innerHTML = data.pagination;
                document.querySelector('.pdf-export-btn').href = data.pdf_url;
                document.querySelector('.excel-export-btn').href = data.excel_url;
                
                if (typeof guardarPreferenciaVista === 'function') {
                    guardarPreferenciaVista('consignaciones-ventas', '__ordenCol__', g_ordenCol);
                    guardarPreferenciaVista('consignaciones-ventas', '__ordenDir__', g_ordenDir);
                }
            }
        } catch(e) {
            console.error(e);
            Swal.fire('Error', 'No se pudo cargar la lista', 'error');
        }
    }
</script>
