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

$base = BASE_URL;
$urlBaseClientes = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');

$rows       = $rows       ?? [];
$total      = $total      ?? 0;
$page       = $page       ?? 1;
$totalPages = $totalPages ?? 1;
$perPage    = $perPage    ?? 20;
$ordenCol   = $ordenCol   ?? 'nombre';
$ordenDir   = $ordenDir   ?? 'asc';
$buscar     = $buscar     ?? '';

$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>

<style>
    .clientes-header { flex-shrink: 0; }
    .clientes-scroll { max-height: calc(100vh - 240px); overflow-y: auto; }
    .clientes-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
    .cliente-row { cursor: pointer; }
    .cliente-row:hover { background-color: rgba(0, 0, 0, .04); }
    .field-sri-locked { background-color: #f0fff4 !important; }
</style>

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<div class="clientes-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-people"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="abrirModalClienteCrear()"><i class="bi bi-plus-lg"></i> Nuevo</button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_busqueda.css?v=<?= time() ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_busqueda.js?v=<?= time() ?>"></script>
            <div id="fbBuscadorCLI" style="width: 480px;"></div>
            <input type="hidden" id="buscarCliente" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosBusqueda) return;
                    new FiltrosBusqueda({
                        containerId: 'fbBuscadorCLI',
                        hiddenInputId: 'buscarCliente',
                        fields: [
                            { key: 'nombre',    label: 'Nombre',             icon: 'bi-person',        type: 'text' },
                            { key: 'ruc',       label: 'RUC / Cédula',       icon: 'bi-card-text',     type: 'text' },
                            { key: 'email',     label: 'Email',              icon: 'bi-envelope',      type: 'text' },
                            { key: 'telefono',  label: 'Teléfono',           icon: 'bi-telephone',     type: 'text' },
                            { key: 'direccion', label: 'Dirección',          icon: 'bi-geo',           type: 'text' },
                            { key: 'ciudad',    label: 'Ciudad',             icon: 'bi-geo-alt',       type: 'text' },
                            { key: 'provincia', label: 'Provincia',          icon: 'bi-map',           type: 'text' },
                            { key: 'vendedor',  label: 'Vendedor',           icon: 'bi-person-badge',  type: 'text' },
                            { key: 'plazo',     label: 'Plazo (días)',       icon: 'bi-calendar-range', type: 'number_range' },
                            { key: 'tipo',      label: 'Tipo identificación', icon: 'bi-credit-card',  type: 'select', options: [
                                { v: '04', l: 'RUC' },
                                { v: '05', l: 'Cédula' },
                                { v: '06', l: 'Pasaporte' },
                                { v: '07', l: 'Consumidor Final' },
                                { v: '08', l: 'Identificación Exterior' },
                            ]},
                            { key: 'estado',    label: 'Estado',             icon: 'bi-flag',          type: 'select', options: [
                                { v: 'activo',   l: 'Activo' },
                                { v: 'inactivo', l: 'Inactivo' },
                            ]},
                        ],
                        quickFilters: [
                            { id: 'qf_activo',    label: 'Activos',    mk: () => ({ key: 'estado', op: '=', value: 'activo',   display: 'Activo' }) },
                            { id: 'qf_inactivo',  label: 'Inactivos',  mk: () => ({ key: 'estado', op: '=', value: 'inactivo', display: 'Inactivo' }) },
                            { id: 'qf_con_plazo', label: 'Con plazo',  mk: () => ({ key: 'plazo',  op: '>', value: '0',        display: '> 0 días' }) },
                        ],
                        onApply: () => window.fetchSearch && window.fetchSearch(1),
                    }).init();
                });
            </script>

            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'identificacion' => 'Identificación',
                    'nombre_tipo_id' => 'Tipo Id.',
                    'nombre' => 'Razón Social',
                    'email' => 'Correo',
                    'telefono' => 'Teléfono',
                    'direccion' => 'Dirección',
                    'plazo' => 'Plazo',
                    'nombre_provincia' => 'Provincia',
                    'nombre_ciudad' => 'Ciudad',
                    'nombre_vendedor' => 'Vendedor',
                    'status' => 'Estado'
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>
                <a id="btnExportPdf" href="<?= $urlBaseClientes ?>/export-pdf?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>" class="btn btn-outline-danger" title="PDF"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                <a id="btnExportExcel" href="<?= $urlBaseClientes ?>/export-excel?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>" class="btn btn-outline-success" title="Excel"><i class="bi bi-file-earmark-spreadsheet"></i> Excel</a>
                <a href="<?= $urlBaseClientes ?>/mapa" class="btn btn-outline-secondary" title="Mapa de clientes"><i class="bi bi-map"></i> Mapa</a>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="paginationContainer" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(<?= $page - 1 ?>)" <?= $page <= 1 ? 'disabled' : '' ?>><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(<?= $page + 1 ?>)" <?= $page >= $totalPages ? 'disabled' : '' ?>><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="clientes-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="identificacion" data-col="identificacion">Identificación <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="nombre_tipo_id" data-col="nombre_tipo_id">Tipo Id. <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="nombre" data-col="nombre">Razón Social <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="email" data-col="email">Correo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="telefono" data-col="telefono">Teléfono <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="direccion" data-col="direccion">Dirección <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-center" role="button" data-sort="plazo" data-col="plazo">Plazo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="nombre_provincia" data-col="nombre_provincia">Provincia <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="nombre_ciudad" data-col="nombre_ciudad">Ciudad <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="nombre_vendedor" data-col="nombre_vendedor">Vendedor <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center pe-3 sortable-header" role="button" data-sort="status" data-col="status">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyClientes">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="11" class="text-center py-5 text-muted">No se encontraron clientes.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <tr class="cliente-row" role="button" data-cliente='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>' onclick="abrirModalClienteEditar(this)">
                                <td class="ps-3" data-col="identificacion"><code class="text-secondary"><?= htmlspecialchars($r['identificacion'] ?? '') ?></code></td>
                                <td data-col="nombre_tipo_id"><?= htmlspecialchars($r['nombre_tipo_id'] ?? $r['tipo_id'] ?? '-') ?></td>
                                <td class="fw-medium text-truncate" style="max-width:250px" data-col="nombre"><?= htmlspecialchars($r['nombre'] ?? '') ?></td>
                                <td data-col="email"><?= htmlspecialchars($r['email'] ?? '-') ?></td>
                                <td data-col="telefono"><?= htmlspecialchars($r['telefono'] ?? '-') ?></td>
                                <td data-col="direccion" class="text-truncate" style="max-width:200px"><?= htmlspecialchars($r['direccion'] ?? '-') ?></td>
                                <td data-col="plazo" class="text-center"><?= (int)($r['plazo'] ?? 0) ?></td>
                                <td data-col="nombre_provincia"><?= htmlspecialchars($r['nombre_provincia'] ?? '-') ?></td>
                                <td data-col="nombre_ciudad"><?= htmlspecialchars($r['nombre_ciudad'] ?? '-') ?></td>
                                <td data-col="nombre_vendedor"><?= htmlspecialchars($r['nombre_vendedor'] ?? '-') ?></td>
                                <td class="text-center pe-3" data-col="status">
                                    <span class="badge bg-<?= ($r['status'] ?? 1) == 1 ? 'success' : 'secondary' ?> bg-opacity-10 text-<?= ($r['status'] ?? 1) == 1 ? 'success' : 'secondary' ?> border border-<?= ($r['status'] ?? 1) == 1 ? 'success' : 'secondary' ?> border-opacity-25"><?= ($r['status'] ?? 1) == 1 ? 'Activo' : 'Inactivo' ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>window.BASE_URL = '<?= $base ?>';</script>
<?php include 'modal_cliente.php'; ?>
<script src="<?= $base ?>/js/modulos/clientes_modal.js?v=<?= time() ?>"></script>

<script>
    (function() {
        'use strict';
        const urlBase = '<?= $urlBaseClientes ?>';
        const inputBuscar = document.getElementById('buscarCliente');
        window.currentSort = '<?= $ordenCol ?>';
        window.currentDir = '<?= $ordenDir ?>';
        window.currentPage = <?= $page ?>;
        let timerId;

        const debounce = (func, delay = 350) => (...args) => {
            clearTimeout(timerId);
            timerId = setTimeout(() => func.apply(this, args), delay);
        };

        window.cambiarPaginaAjax = (n) => window.fetchSearch(n);

        window.fetchSearch = async (page = 1) => {
            const term = inputBuscar ? inputBuscar.value.trim() : '';
            const uri = `${urlBase}/searchAjax?b=${encodeURIComponent(term)}&page=${page}&sort=${window.currentSort}&dir=${window.currentDir}`;
            try {
                const resp = await fetch(uri);
                const data = await resp.json();
                if (data.ok) {
                    window.currentPage = page;
                    document.getElementById('tbodyClientes').innerHTML = data.rows;
                    document.getElementById('paginationContainer').innerHTML = data.pagination;
                    document.getElementById('paginationInfo').textContent = data.info;
                    document.getElementById('btnExportPdf').href = data.pdf_url;
                    document.getElementById('btnExportExcel').href = data.excel_url;

                    document.querySelectorAll('.sortable-header').forEach(th => {
                        const icon = th.querySelector('i');
                        const field = th.dataset.sort;
                        if (field === window.currentSort) {
                            icon.className = (window.currentDir.toLowerCase() === 'asc') ? 'bi bi-sort-alpha-down text-primary ms-1' : 'bi bi-sort-alpha-up text-primary ms-1';
                        } else {
                            icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
                        }
                    });
                }
            } catch (e) { console.error(e); }
        };

        document.querySelectorAll('.sortable-header').forEach(h => {
            h.addEventListener('click', () => {
                const f = h.dataset.sort;
                if (window.currentSort === f) window.currentDir = (window.currentDir.toLowerCase() === 'asc') ? 'DESC' : 'ASC';
                else { window.currentSort = f; window.currentDir = 'ASC'; }
                if (typeof window.guardarOrdenacionVista === 'function') window.guardarOrdenacionVista('clientes', window.currentSort, window.currentDir);
                fetchSearch(1);
            });
        });

        if (inputBuscar) inputBuscar.addEventListener('input', debounce(() => fetchSearch(1), 400));
    })();
</script>