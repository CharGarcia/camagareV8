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

$base        = BASE_URL;
$urlBase     = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');
$rows        = $rows       ?? [];
$total       = $total      ?? 0;
$page        = $page       ?? 1;
$totalPages  = $totalPages ?? 1;
$perPage     = $perPage    ?? 20;
$ordenCol    = $ordenCol   ?? 'nombre';
$ordenDir    = $ordenDir   ?? 'ASC';
$buscar      = $buscar     ?? '';
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
$tiposId = ['04' => 'RUC', '05' => 'Cédula', '06' => 'Pasaporte'];
?>
<style>
.transp-header { flex-shrink: 0; }
.transp-scroll { max-height: calc(100dvh - 240px); overflow-y: auto; }
.transp-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
.transp-row { cursor: pointer; }
.transp-row:hover { background-color: rgba(0,0,0,.04); }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>

<div class="transp-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-truck me-1"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="TR_abrirCrear()">
            <i class="bi bi-plus-lg"></i> Nuevo
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <form method="POST" action="<?= $urlBase ?>" class="d-flex align-items-center m-0" onsubmit="event.preventDefault(); TR_fetchSearch(1);">
                <input type="hidden" name="page" value="1">
                <input type="hidden" name="sort" value="<?= htmlspecialchars($ordenCol) ?>">
                <input type="hidden" name="dir"  value="<?= htmlspecialchars($ordenDir) ?>">
                <div class="input-group input-group-sm" style="width:300px">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="b" id="buscarTransportista" class="form-control border-start-0 ps-0 shadow-none border"
                           placeholder="Buscar nombre, identificación, placa…"
                           value="<?= htmlspecialchars($buscar) ?>" autocomplete="off">
                    <?php if ($buscar !== ''): ?>
                        <a href="<?= $urlBase ?>" class="btn border border-start-0 text-muted" title="Limpiar"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </div>
                <button type="submit" class="d-none">Buscar</button>
            </form>

            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'nombre'         => 'Nombre / Razón Social',
                    'tipo_id'        => 'Tipo ID',
                    'identificacion' => 'Identificación',
                    'placa'          => 'Placa',
                    'telefono'       => 'Teléfono',
                    'email'          => 'Email',
                    'estado'         => 'Estado'
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>

                <a id="btnExportPdf"
                   href="<?= $urlBase ?>/export-pdf?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                   class="btn btn-outline-danger" title="Descargar PDF">
                    <i class="bi bi-file-earmark-pdf"></i> PDF
                </a>
                <a id="btnExportExcel"
                   href="<?= $urlBase ?>/export-excel?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                   class="btn btn-outline-success" title="Descargar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                </a>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <span id="trPaginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="trPaginationContainer" class="btn-group btn-group-sm">
                <?php if ($page <= 1): ?>
                    <button type="button" class="btn btn-outline-secondary" disabled><i class="bi bi-chevron-left"></i></button>
                <?php else: ?>
                    <button type="button" class="btn btn-outline-secondary" onclick="TR_fetchSearch(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <?php endif; ?>
                <?php if ($page >= $totalPages): ?>
                    <button type="button" class="btn btn-outline-secondary" disabled><i class="bi bi-chevron-right"></i></button>
                <?php else: ?>
                    <button type="button" class="btn btn-outline-secondary" onclick="TR_fetchSearch(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="transp-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="nombre" data-col="nombre">Nombre / Razón Social <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="tipo_id" data-col="tipo_id">Tipo ID <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="identificacion" data-col="identificacion">Identificación <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="placa" data-col="placa">Placa <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="telefono" data-col="telefono">Teléfono <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="email" data-col="email">Email <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center pe-3 sortable-header" role="button" data-sort="estado" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyTransportistas">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-truck fs-3 d-block mb-2"></i>No se encontraron transportistas.</td></tr>
                    <?php else: foreach ($rows as $r):
                        $rowData     = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                        $estadoClass = $r['estado'] === 'activo'
                            ? 'bg-success bg-opacity-10 text-success border-success'
                            : 'bg-secondary bg-opacity-10 text-secondary border-secondary';
                    ?>
                        <tr class="transp-row" role="button" tabindex="0" data-row='<?= $rowData ?>' onclick="TR_abrirEditar(this)">
                            <td class="ps-3 fw-medium text-truncate" style="max-width:250px" data-col="nombre"><?= htmlspecialchars($r['nombre']) ?></td>
                            <td data-col="tipo_id"><small class="text-muted"><?= $tiposId[$r['tipo_id']] ?? $r['tipo_id'] ?></small></td>
                            <td data-col="identificacion"><code class="text-secondary"><?= htmlspecialchars($r['identificacion']) ?></code></td>
                            <td data-col="placa"><?= htmlspecialchars($r['placa'] ?? '-') ?></td>
                            <td data-col="telefono"><?= htmlspecialchars($r['telefono'] ?? '-') ?></td>
                            <td class="text-truncate" style="max-width:180px" data-col="email"><?= htmlspecialchars($r['email'] ?? '-') ?></td>
                            <td class="text-center pe-3" data-col="estado">
                                <span class="badge <?= $estadoClass ?> border border-opacity-25"><?= ucfirst($r['estado']) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/modal_transportista.php'; ?>
<script src="<?= $base ?>/js/modulos/transportistas_modal.js?v=<?= time() ?>"></script>

<script>
(function () {
    'use strict';
    const urlBase    = '<?= $urlBase ?>';
    const inputBuscar = document.getElementById('buscarTransportista');

    window.TR_currentSort = '<?= $ordenCol ?>';
    window.TR_currentDir  = '<?= $ordenDir ?>';
    window.TR_currentPage = <?= $page ?>;

    let timerBuscar;

    // ── Fetch tabla ─────────────────────────────────────────────────────────
    window.TR_fetchSearch = async function (page = 1) {
        const term = inputBuscar ? inputBuscar.value.trim() : '';
        const uri  = `${urlBase}/search-ajax?b=${encodeURIComponent(term)}&page=${page}&sort=${window.TR_currentSort}&dir=${window.TR_currentDir}`;
        try {
            const resp = await fetch(uri);
            const data = await resp.json();
            if (!data.ok) return;
            window.TR_currentPage = page;
            document.getElementById('tbodyTransportistas').innerHTML    = data.rows;
            document.getElementById('trPaginationContainer').innerHTML  = data.pagination;
            document.getElementById('trPaginationInfo').textContent     = data.info;
            if (data.pdf_url)   document.getElementById('btnExportPdf').href   = data.pdf_url;
            if (data.excel_url) document.getElementById('btnExportExcel').href = data.excel_url;

            document.querySelectorAll('.sortable-header').forEach(th => {
                const icon  = th.querySelector('i');
                const field = th.dataset.sort;
                if (!icon) return;
                if (field === window.TR_currentSort) {
                    icon.className = (window.TR_currentDir.toUpperCase() === 'ASC')
                        ? 'bi bi-sort-alpha-down text-primary ms-1'
                        : 'bi bi-sort-alpha-up text-primary ms-1';
                } else {
                    icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
                }
            });
        } catch (e) { console.error(e); }
    };

    // Ordenamiento
    document.querySelectorAll('.sortable-header').forEach(h => {
        h.addEventListener('click', () => {
            const f = h.dataset.sort;
            if (window.TR_currentSort === f) {
                window.TR_currentDir = window.TR_currentDir.toUpperCase() === 'ASC' ? 'DESC' : 'ASC';
            } else {
                window.TR_currentSort = f;
                window.TR_currentDir  = 'ASC';
            }
            window.TR_fetchSearch(1);
        });
    });

    if (inputBuscar) {
        inputBuscar.addEventListener('input', () => {
            clearTimeout(timerBuscar);
            timerBuscar = setTimeout(() => window.TR_fetchSearch(1), 400);
        });
    }
})();
</script>
