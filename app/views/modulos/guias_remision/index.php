<?php
/** @var array $perm */
/** @var array $rows */
/** @var int $total */
/** @var int $page */
/** @var int $totalPages */
/** @var int $perPage */
/** @var string $buscar */
/** @var string $ordenCol */
/** @var string $ordenDir */
/** @var string $rutaModulo */
/** @var array $empresa */
/** @var array $establecimientos */
/** @var array $puntos */
/** @var array $vistaConfig */

$base    = BASE_URL;
$urlBase = $base . '/' . $rutaModulo;
$from    = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to      = $total > 0 ? min($page * $perPage, $total) : 0;
?>
<style>
.gr-row { cursor: pointer; }
.gr-row:hover { background-color: rgba(0,0,0,.04); }
.gr-scroll { max-height: calc(100vh - 230px); overflow-y: auto; }
.gr-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
.gr-sortable-header { cursor: pointer; user-select: none; white-space: nowrap; }
.modal-gr .modal-header { background: #f8f9fa; border-bottom: 1px solid #dee2e6; padding: 0.75rem 1rem; }
.modal-gr .modal-body { padding: 0 !important; }
.modal-gr label { font-size: 0.85rem; font-weight: 600; color: #495057; margin-bottom: 3px !important; }
.table-detalle th { font-size: 0.7rem !important; text-transform: uppercase; background: #f8f9fa; padding: 4px 8px !important; }
.table-detalle td { padding: 0 !important; vertical-align: middle; }
.input-detalle { border: none; background: transparent; font-size: 0.82rem; padding: 2px 8px; height: 28px; width: 100%; }
.input-detalle:focus { background: #fff; box-shadow: inset 0 0 0 1px #0d6efd; outline: none; }
.row-detalle:hover { background: rgba(13,110,253,.03); }
.remove-row-gr { color: #dc3545; opacity: 0; transition: opacity .2s; }
.row-detalle:hover .remove-row-gr { opacity: 1; }
.dropdown-gr { z-index: 2000 !important; }
.hover-bg-light:hover { background-color: #f8f9fa; }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-truck me-2 text-primary"></i><?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="abrirModalGR()">
            <i class="bi bi-plus-lg"></i> Nueva
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <form id="gr-form-buscar" class="input-group input-group-sm" style="width:300px" onsubmit="event.preventDefault(); GR_buscar();">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" id="gr-buscar" class="form-control border-start-0 ps-0 shadow-none border"
                       placeholder="Buscar número, cliente, transportista..." value="<?= htmlspecialchars($buscar) ?>" autocomplete="off">
            </form>
            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'numero'                  => 'Número',
                    'fecha_emision'           => 'Emisión',
                    'cliente_nombre'          => 'Destinatario',
                    'cliente_ruc'             => 'RUC/Cédula',
                    'transportista_nombre'    => 'Transportista',
                    'placa'                   => 'Placa',
                    'motivo_traslado'         => 'Motivo',
                    'fecha_inicio_transporte' => 'F. Inicio',
                    'usuario_nombre'          => 'Usuario',
                    'estado'                  => 'Estado',
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>
                <a id="gr-pdf-btn"
                   href="<?= $urlBase ?>/export-pdf?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                   class="btn btn-outline-danger" title="Descargar PDF">
                    <i class="bi bi-file-earmark-pdf"></i> PDF
                </a>
                <a id="gr-excel-btn"
                   href="<?= $urlBase ?>/export-excel?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                   class="btn btn-outline-success" title="Descargar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                </a>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span id="gr-info" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="gr-paginacion" class="btn-group btn-group-sm">
                <?php if ($page <= 1): ?>
                    <button type="button" class="btn btn-outline-secondary" disabled><i class="bi bi-chevron-left"></i></button>
                <?php else: ?>
                    <button type="button" class="btn btn-outline-secondary" onclick="GR_cambiarPagina(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <?php endif; ?>
                <?php if ($page >= $totalPages): ?>
                    <button type="button" class="btn btn-outline-secondary" disabled><i class="bi bi-chevron-right"></i></button>
                <?php else: ?>
                    <button type="button" class="btn btn-outline-secondary" onclick="GR_cambiarPagina(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="gr-scroll w-100">
            <table class="table table-hover table-sm mb-0" style="table-layout:fixed;min-width:900px">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 gr-sortable-header" data-sort="numero" data-col="numero">
                            Número <i class="bi <?= $ordenCol === 'numero' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="gr-sortable-header" data-sort="fecha_emision" data-col="fecha_emision">
                            Emisión <i class="bi <?= $ordenCol === 'fecha_emision' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="gr-sortable-header" data-sort="cliente_nombre" data-col="cliente_nombre">
                            Destinatario <i class="bi <?= $ordenCol === 'cliente_nombre' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="gr-sortable-header" data-sort="cliente_ruc" data-col="cliente_ruc">
                            RUC/Cédula <i class="bi <?= $ordenCol === 'cliente_ruc' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="gr-sortable-header" data-sort="transportista_nombre" data-col="transportista_nombre">
                            Transportista <i class="bi <?= $ordenCol === 'transportista_nombre' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="gr-sortable-header" data-sort="placa" data-col="placa">
                            Placa <i class="bi <?= $ordenCol === 'placa' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="gr-sortable-header" data-sort="motivo_traslado" data-col="motivo_traslado">
                            Motivo <i class="bi <?= $ordenCol === 'motivo_traslado' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="gr-sortable-header" data-sort="fecha_inicio_transporte" data-col="fecha_inicio_transporte">
                            F. Inicio <i class="bi <?= $ordenCol === 'fecha_inicio_transporte' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="gr-sortable-header" data-sort="usuario_nombre" data-col="usuario_nombre">
                            Usuario <i class="bi <?= $ordenCol === 'usuario_nombre' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="text-center pe-3 gr-sortable-header" data-sort="estado" data-col="estado">
                            Estado <i class="bi <?= $ordenCol === 'estado' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                    </tr>
                </thead>
                <tbody id="gr-tbody">
                <?php if (empty($rows)): ?>
                    <tr><td colspan="10" class="text-center py-5 text-muted"><i class="bi bi-truck fs-3 d-block mb-2"></i>No se encontraron guías de remisión.</td></tr>
                <?php else: foreach ($rows as $r):
                    $rowData = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                    $numero  = ($r['establecimiento'] ?? '') . '-' . ($r['punto_emision'] ?? '') . '-' . ($r['secuencial'] ?? '');
                    $estado  = $r['estado'] ?? 'borrador';
                    $estadoClass = match($estado) {
                        'autorizado'              => 'bg-success bg-opacity-10 text-success border-success',
                        'anulado'                 => 'bg-danger bg-opacity-10 text-danger border-danger',
                        'no_autorizado','devuelta' => 'bg-warning bg-opacity-10 text-warning border-warning',
                        'borrador'                => 'bg-secondary bg-opacity-10 text-secondary border-secondary',
                        default                   => 'bg-primary bg-opacity-10 text-primary border-primary',
                    };
                ?>
                    <tr class="gr-row" role="button" tabindex="0" data-row='<?= $rowData ?>' onclick="abrirModalGR(this)">
                        <td class="ps-3" data-col="numero"><code class="text-secondary"><?= htmlspecialchars($numero) ?></code></td>
                        <td data-col="fecha_emision"><?= !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '-' ?></td>
                        <td class="fw-medium text-truncate" data-col="cliente_nombre" style="max-width:160px"><?= htmlspecialchars($r['cliente_nombre'] ?? '-') ?></td>
                        <td data-col="cliente_ruc"><small class="text-muted"><?= htmlspecialchars($r['cliente_ruc'] ?? '-') ?></small></td>
                        <td data-col="transportista_nombre" class="text-truncate" style="max-width:130px"><?= htmlspecialchars($r['transportista_nombre'] ?? '-') ?></td>
                        <td data-col="placa"><?= htmlspecialchars($r['placa'] ?? '-') ?></td>
                        <td data-col="motivo_traslado" class="text-truncate" style="max-width:130px"><?= htmlspecialchars($r['motivo_traslado'] ?? '-') ?></td>
                        <td data-col="fecha_inicio_transporte"><?= !empty($r['fecha_inicio_transporte']) ? date('d-m-Y', strtotime($r['fecha_inicio_transporte'])) : '-' ?></td>
                        <td data-col="usuario_nombre"><?= htmlspecialchars($r['usuario_nombre'] ?? '-') ?></td>
                        <td class="text-center pe-3" data-col="estado">
                            <span class="badge <?= $estadoClass ?> border border-opacity-25"><?= ucfirst(str_replace('_', ' ', $estado)) ?></span>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'modal_gr.php'; ?>
<?php include __DIR__ . '/../transportistas/modal_transportista.php'; ?>

<!-- ═══════════════════════════ JAVASCRIPT ═══════════════════════════ -->
<script src="<?= $base ?>/js/modulos/transportistas_modal.js?v=<?= time() ?>"></script>
<script src="<?= $base ?>/js/modulos/guias_remision_modal.js?v=<?= time() ?>"></script>
<script>
    const GR_urlBase   = '<?= $urlBase ?>';
    let GR_page        = <?= $page ?>;
    let GR_sort        = '<?= $ordenCol ?>';
    let GR_dir         = '<?= $ordenDir ?>';
    window.GR_establecimientos = <?= json_encode($establecimientos) ?>;

    // Sortable headers
    document.querySelectorAll('.gr-sortable-header').forEach(th => {
        th.addEventListener('click', function() {
            const col = this.dataset.sort;
            GR_dir = GR_sort === col ? (GR_dir === 'ASC' ? 'DESC' : 'ASC') : 'DESC';
            GR_sort = col;
            GR_cargar(1);
        });
    });

    function abrirModalGR(el) {
        if (!el) {
            window.GR_abrirCrear();
            return;
        }
        window.GR_abrirEditar(el);
    }

    function GR_buscar() { GR_cargar(1); }
    function GR_cambiarPagina(p) { GR_cargar(p); }

    function GR_cargar(p) {
        GR_page = p;
        const q   = document.getElementById('gr-buscar').value;
        const url = GR_urlBase + '/search-ajax?b=' + encodeURIComponent(q) + '&page=' + p + '&sort=' + GR_sort + '&dir=' + GR_dir;
        fetch(url)
            .then(r => r.json())
            .then(d => {
                if (!d.ok) return;
                document.getElementById('gr-tbody').innerHTML      = d.rows;
                document.getElementById('gr-paginacion').innerHTML = d.pagination;
                document.getElementById('gr-info').textContent    = d.info;
                if (d.pdf_url)   document.getElementById('gr-pdf-btn').href   = d.pdf_url;
                if (d.excel_url) document.getElementById('gr-excel-btn').href = d.excel_url;
                document.querySelectorAll('#gr-tbody .gr-row').forEach(el => {
                    el.onclick = function() { abrirModalGR(this); };
                });
            });
    }

    document.addEventListener('click', e => {
        ['gr-dropdown-transportista','gr-dropdown-cliente','gr-dropdown-factura'].forEach(id => {
            const dd = document.getElementById(id);
            if (dd && !dd.contains(e.target)) dd.style.display = 'none';
        });
    });

    document.getElementById('gr-buscar').addEventListener('keydown', e => { if (e.key === 'Enter') GR_buscar(); });

    // Carga inicial
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('#gr-tbody .gr-row').forEach(el => {
            el.onclick = function() { abrirModalGR(this); };
        });
    });
</script>
