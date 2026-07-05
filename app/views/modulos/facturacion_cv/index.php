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
        <div class="d-flex align-items-center gap-2">
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_busqueda.css?v=<?= time() ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_busqueda.js?v=<?= time() ?>"></script>
            <div id="fbBuscadorFAC" style="width: 460px;"></div>
            <input type="hidden" id="b" name="b" value="<?= htmlspecialchars($buscar) ?>">

            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosBusqueda) return;
                    new FiltrosBusqueda({
                        containerId: 'fbBuscadorFAC',
                        hiddenInputId: 'b',
                        fields: [
                            { key: 'secuencial', label: 'Secuencial', icon: 'bi-hash',   type: 'text' },
                            { key: 'cliente',    label: 'Cliente',    icon: 'bi-person', type: 'text' },
                            { key: 'factura',    label: 'Factura',    icon: 'bi-receipt', type: 'text' },
                            { key: 'estado',     label: 'Estado',     icon: 'bi-flag',   type: 'select', options: [
                                { v: 'borrador',  l: 'Borrador' },
                                { v: 'facturada', l: 'Facturada' },
                                { v: 'anulada',   l: 'Anulada' },
                            ]},
                        ],
                        onApply: () => { g_paginaActual = 1; cargarGrid(); },
                    }).init();
                });
            </script>

            <div class="btn-group btn-group-sm">
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas([
                    'fecha'      => 'Fecha',
                    'secuencial' => 'Número',
                    'cliente'    => 'Cliente',
                    'factura'    => 'Factura',
                    'total'      => 'Total',
                    'estado'     => 'Estado'
                ], $vistaConfig ?? [], 'facturacion-cv'); ?>

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
        <div class="factcv-scroll w-100">
            <table class="table table-hover table-sm mb-0" id="tablaFacturacion">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-col="fecha">Fecha <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-col="secuencial">Número <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-col="cliente">Cliente <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-col="factura">Factura <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header" role="button" data-col="total">Total <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center pe-3 sortable-header" role="button" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="grid-body">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-receipt fs-3 d-block mb-2"></i>No hay facturaciones registradas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r):
                            $dataJson = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                            $badge = \App\controllers\modulos\FacturacionCvController::badgeEstado($r['estado'] ?? 'borrador');
                        ?>
                            <tr class="factcv-row" role="button" tabindex="0" data-row="<?= $dataJson ?>" onclick="abrirModalFacturacionVer(this)">
                                <td class="ps-3" data-col="fecha"><?= htmlspecialchars($r['fecha_emision'] ?? '') ?></td>
                                <td data-col="secuencial" class="fw-bold text-primary"><?= htmlspecialchars(($r['serie'] ?? '') . '-' . ($r['secuencial'] ?? '')) ?></td>
                                <td data-col="cliente" class="text-truncate" style="max-width:230px" title="<?= htmlspecialchars($r['cliente_nombre'] ?? '') ?>"><?= htmlspecialchars($r['cliente_nombre'] ?? '') ?></td>
                                <td data-col="factura"><?= htmlspecialchars($r['numero_factura'] ?? '—') ?></td>
                                <td data-col="total" class="text-end"><?= number_format((float)($r['total'] ?? 0), 2) ?></td>
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
        eliminar: <?= (!empty($perm['eliminar']) || !empty($perm['todo'])) ? 'true' : 'false' ?>
    };
    window.EMPRESA_CONFIG = {
        facturacion_inventario: <?= (($empresa['facturacion_inventario'] ?? true) === 'true' || ($empresa['facturacion_inventario'] ?? true) === true) ? 'true' : 'false' ?>,
        decimales_precio: <?= (int) ($empresa['decimales_precio'] ?? 2) ?>,
        decimales_cantidad: <?= (int) ($empresa['decimales_cantidad'] ?? 2) ?>
    };
</script>

<?php include __DIR__ . '/modal_facturacion.php'; ?>

<!-- Modal reutilizable de creación rápida de cliente -->
<?php
$permCli = ['crear' => true, 'actualizar' => true, 'eliminar' => false, 'todo' => true];
include dirname(__DIR__) . '/clientes/modal_cliente.php';
?>
<script src="<?= BASE_URL ?>/js/modulos/clientes_modal.js?v=<?= time() ?>"></script>

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
        try {
            const tbody = document.getElementById('grid-body');
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></td></tr>';
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
                if (typeof guardarPreferenciaVista === 'function') {
                    guardarPreferenciaVista('facturacion-cv', '__ordenCol__', g_ordenCol);
                    guardarPreferenciaVista('facturacion-cv', '__ordenDir__', g_ordenDir);
                }
            }
        } catch (e) {
            console.error(e);
            Swal.fire('Error', 'No se pudo cargar la lista', 'error');
        }
    }
</script>
