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
        <div class="d-flex align-items-center gap-2">
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_busqueda.css?v=<?= time() ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_busqueda.js?v=<?= time() ?>"></script>
            <div id="fbBuscadorCAM" style="width: 460px;"></div>
            <input type="hidden" id="b" name="b" value="<?= htmlspecialchars($buscar) ?>">

            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosBusqueda) return;
                    new FiltrosBusqueda({
                        containerId: 'fbBuscadorCAM',
                        hiddenInputId: 'b',
                        fields: [
                            { key: 'secuencial', label: 'Secuencial', icon: 'bi-hash',   type: 'text' },
                            { key: 'cliente',    label: 'Cliente',    icon: 'bi-person', type: 'text' },
                            { key: 'motivo',     label: 'Motivo',     icon: 'bi-chat',   type: 'text' },
                            { key: 'estado',     label: 'Estado',     icon: 'bi-flag',   type: 'select', options: [
                                { v: 'Emitida',  l: 'Emitida' },
                                { v: 'Borrador', l: 'Borrador' },
                                { v: 'Anulada',  l: 'Anulada' },
                            ]},
                        ],
                        quickFilters: [
                            { id: 'qf_emitida', label: 'Emitidos', mk: () => ({ key: 'estado', op: '=', value: 'Emitida', display: 'Emitidos' }) },
                        ],
                        onApply: () => { g_paginaActual = 1; cargarGrid(); },
                    }).init();
                });
            </script>

            <div class="btn-group btn-group-sm">
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas([
                    'fecha_cambio' => 'Fecha',
                    'secuencial'   => 'Secuencial',
                    'cliente'      => 'Cliente',
                    'motivo'       => 'Motivo',
                    'diferencia'   => 'Diferencia',
                    'estado'       => 'Estado'
                ], $vistaConfig ?? [], 'cambio-producto-cv'); ?>

                <a class="btn btn-outline-danger pdf-export-btn" href="<?= BASE_URL ?>/<?= $rutaModulo ?>/export-pdf?b=<?= urlencode($buscar) ?>&sort=<?= $ordenCol ?>&dir=<?= $ordenDir ?>" target="_blank" title="Exportar a PDF"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                <a class="btn btn-outline-success excel-export-btn" href="<?= BASE_URL ?>/<?= $rutaModulo ?>/export-excel?b=<?= urlencode($buscar) ?>&sort=<?= $ordenCol ?>&dir=<?= $ordenDir ?>" title="Exportar a Excel"><i class="bi bi-file-earmark-excel"></i> Excel</a>
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
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-col="fecha_cambio">Fecha <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-col="secuencial">Secuencial <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-col="cliente">Cliente <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-col="motivo">Motivo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header" role="button" data-col="diferencia">Diferencia <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center pe-3 sortable-header" role="button" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="grid-body">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-arrow-left-right fs-3 d-block mb-2"></i>
                                No se encontraron cambios.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r):
                            $dataJson = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                            $statusBadge = \App\controllers\modulos\CambioProductoCvController::badgeEstado($r['estado'] ?? '');
                        ?>
                            <tr class="cambio-row" role="button" tabindex="0" data-row="<?= $dataJson ?>" onclick="abrirModalCambioVer(this)">
                                <td class="ps-3" data-col="fecha_cambio"><?= htmlspecialchars($r['fecha_cambio'] ?? '') ?></td>
                                <td data-col="secuencial" class="fw-bold text-primary"><?= htmlspecialchars(($r['serie'] ?? '') . '-' . ($r['secuencial'] ?? '')) ?></td>
                                <td data-col="cliente" class="text-truncate" style="max-width:250px" title="<?= htmlspecialchars($r['cliente_nombre'] ?? '') ?>"><?= htmlspecialchars($r['cliente_nombre'] ?? '') ?></td>
                                <td data-col="motivo" class="text-truncate" style="max-width:220px" title="<?= htmlspecialchars($r['motivo'] ?? '—') ?>"><?= htmlspecialchars($r['motivo'] ?? '—') ?></td>
                                <td data-col="diferencia" class="text-end"><?= number_format((float)($r['diferencia'] ?? 0), 2) ?></td>
                                <td class="text-center pe-3" data-col="estado"><?= $statusBadge ?></td>
                            </tr>
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
        try {
            const tbody = document.getElementById('grid-body');
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></td></tr>';

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

                if (typeof guardarPreferenciaVista === 'function') {
                    guardarPreferenciaVista('cambio-producto-cv', '__ordenCol__', g_ordenCol);
                    guardarPreferenciaVista('cambio-producto-cv', '__ordenDir__', g_ordenDir);
                }
            }
        } catch (e) {
            console.error(e);
            Swal.fire('Error', 'No se pudo cargar la lista', 'error');
        }
    }
</script>
