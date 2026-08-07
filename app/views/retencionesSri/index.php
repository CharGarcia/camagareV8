<?php
/** @var string $titulo */
/** @var array $rows */
/** @var string $ordenCol */
/** @var string $ordenDir */
/** @var string $buscar */
$base = BASE_URL;
$rows = $rows ?? [];
$ordenCol = $ordenCol ?? 'codigo_ret';
$ordenDir = $ordenDir ?? 'asc';
$buscar = $buscar ?? '';
$msg = $_SESSION['retenciones_msg'] ?? null;
unset($_SESSION['retenciones_msg']);

$rowsHtml = $rowsHtml ?? '';
?>
<style>
.retencion-row { cursor: pointer; }
.retencion-row:hover { background-color: rgba(0,0,0,.04); }
.retenciones-sri-header { flex-shrink: 0; }
.retenciones-sri-scroll { max-height: calc(100dvh - 280px); overflow-y: auto; }
.retenciones-sri-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
</style>
<div class="retenciones-sri-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-receipt"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">Haga clic en una fila para editar. Retenciones del SRI.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevaRetencion"><i class="bi bi-plus-lg"></i> Crear nuevo</button>
    </div>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= htmlspecialchars($msg[0]) ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($msg[1]) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="input-group input-group-sm mb-3" style="max-width: 320px;">
    <span class="input-group-text"><i class="bi bi-search"></i></span>
    <input type="text" id="input-buscar-retenciones" class="form-control" placeholder="Buscar en código, descripción, impuesto..." value="<?= htmlspecialchars($buscar) ?>" autocomplete="off">
</div>

<div class="card cmg-table-card">
    <div class="card-body p-0">
        <div class="retenciones-sri-scroll">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="sortable-header" data-sort="codigo_ret" role="button">Código <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="concepto_ret" role="button">Descripción <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header" data-sort="porcentaje_ret" role="button">% <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="impuesto_ret" role="button">Impuesto <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="cod_anexo_ret" role="button">Cód. ATS <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" data-sort="status" role="button">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="desde" role="button">Desde <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="hasta" role="button">Hasta <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyRetenciones"><?= $rowsHtml ?></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Nueva retención -->
<div class="modal fade" id="modalNuevaRetencion" tabindex="-1" aria-labelledby="modalNuevaRetencionLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/retencionesSriStore">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalNuevaRetencionLabel"><i class="bi bi-plus-circle"></i> Nueva retención SRI</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">No se permite duplicar: mismo código+descripción+porcentaje, ni misma descripción+vigencia (desde-hasta).</p>
                    <div class="row g-3">
                        <div class="col-md-2">
                            <label for="new-codigo_ret" class="form-label">Código <span class="text-danger">*</span></label>
                            <input type="text" id="new-codigo_ret" name="codigo_ret" class="form-control" required placeholder="Ej: 301">
                        </div>
                        <div class="col-md-10">
                            <label for="new-concepto_ret" class="form-label">Descripción</label>
                            <input type="text" id="new-concepto_ret" name="concepto_ret" class="form-control" placeholder="Concepto de la retención">
                        </div>
                        <div class="col-md-4">
                            <label for="new-porcentaje_ret" class="form-label">Porcentaje %</label>
                            <input type="number" id="new-porcentaje_ret" name="porcentaje_ret" class="form-control" step="0.01" min="0" max="100" placeholder="0" value="0">
                        </div>
                        <div class="col-md-4">
                            <label for="new-impuesto_ret" class="form-label">Impuesto</label>
                            <select id="new-impuesto_ret" name="impuesto_ret" class="form-select">
                                <option value="RENTA">RENTA</option>
                                <option value="IVA">IVA</option>
                                <option value="ISD">ISD</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="new-cod_anexo_ret" class="form-label">Código ATS</label>
                            <input type="text" id="new-cod_anexo_ret" name="cod_anexo_ret" class="form-control" placeholder="Código anexo">
                        </div>
                        <div class="col-md-4">
                            <label for="new-status" class="form-label">Estado</label>
                            <select id="new-status" name="status" class="form-select">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="new-desde" class="form-label">Desde (vigencia inicial)</label>
                            <input type="date" id="new-desde" name="desde" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label for="new-hasta" class="form-label">Hasta (vigencia final)</label>
                            <input type="date" id="new-hasta" name="hasta" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Crear</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar retención -->
<div class="modal fade" id="modalEditarRetencion" tabindex="-1" aria-labelledby="modalEditarRetencionLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/retencionesSriUpdate">
                <input type="hidden" name="id" id="edit-id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditarRetencionLabel"><i class="bi bi-pencil"></i> Editar retención SRI</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-2">
                            <label for="edit-codigo_ret" class="form-label">Código <span class="text-danger">*</span></label>
                            <input type="text" id="edit-codigo_ret" name="codigo_ret" class="form-control" required placeholder="Ej: 301">
                        </div>
                        <div class="col-md-10">
                            <label for="edit-concepto_ret" class="form-label">Descripción</label>
                            <input type="text" id="edit-concepto_ret" name="concepto_ret" class="form-control" placeholder="Concepto de la retención">
                        </div>
                        <div class="col-md-4">
                            <label for="edit-porcentaje_ret" class="form-label">Porcentaje %</label>
                            <input type="number" id="edit-porcentaje_ret" name="porcentaje_ret" class="form-control" step="0.01" min="0" max="100" placeholder="0">
                        </div>
                        <div class="col-md-4">
                            <label for="edit-impuesto_ret" class="form-label">Impuesto</label>
                            <select id="edit-impuesto_ret" name="impuesto_ret" class="form-select">
                                <option value="RENTA">RENTA</option>
                                <option value="IVA">IVA</option>
                                <option value="ISD">ISD</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="edit-cod_anexo_ret" class="form-label">Código ATS</label>
                            <input type="text" id="edit-cod_anexo_ret" name="cod_anexo_ret" class="form-control" placeholder="Código anexo">
                        </div>
                        <div class="col-md-4">
                            <label for="edit-status" class="form-label">Estado</label>
                            <select id="edit-status" name="status" class="form-select">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="edit-desde" class="form-label">Desde (vigencia inicial)</label>
                            <input type="date" id="edit-desde" name="desde" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label for="edit-hasta" class="form-label">Hasta (vigencia final)</label>
                            <input type="date" id="edit-hasta" name="hasta" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    function toYyyyMmDd(s) {
        if (!s || s === '0000-00-00') return '';
        var m = s.match(/^(\d{4})-(\d{1,2})-(\d{1,2})/);
        if (m) return m[1] + '-' + m[2].padStart(2,'0') + '-' + m[3].padStart(2,'0');
        m = s.match(/^(\d{1,2})-(\d{1,2})-(\d{4})/);
        if (m) return m[3] + '-' + m[2].padStart(2,'0') + '-' + m[1].padStart(2,'0');
        return s;
    }
    var base = '<?= $base ?>';
    var modal = document.getElementById('modalEditarRetencion');
    var form = modal ? modal.querySelector('form') : null;

    function abrirModalRetencion(row) {
        if (!modal || !form) return;
        form.querySelector('#edit-id').value = row.dataset.id || '';
        form.querySelector('#edit-codigo_ret').value = row.dataset.codigo || '';
        form.querySelector('#edit-concepto_ret').value = row.dataset.concepto || '';
        form.querySelector('#edit-porcentaje_ret').value = row.dataset.porcentaje || '';
        form.querySelector('#edit-impuesto_ret').value = row.dataset.impuesto || 'RENTA';
        form.querySelector('#edit-cod_anexo_ret').value = row.dataset.codanexo || '';
        form.querySelector('#edit-status').value = row.dataset.status || '1';
        form.querySelector('#edit-desde').value = toYyyyMmDd(row.dataset.desde || '');
        form.querySelector('#edit-hasta').value = toYyyyMmDd(row.dataset.hasta || '');
        new bootstrap.Modal(modal).show();
    }

    // Delegación de eventos: las filas se reemplazan en cada búsqueda/orden
    // AJAX, por lo que el listener va en el tbody (contenedor fijo).
    var tbodyRetenciones = document.getElementById('tbodyRetenciones');
    if (tbodyRetenciones) {
        tbodyRetenciones.addEventListener('click', function(e) {
            var row = e.target.closest('.retencion-row');
            if (row) abrirModalRetencion(row);
        });
        tbodyRetenciones.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            var row = e.target.closest('.retencion-row');
            if (row) { e.preventDefault(); abrirModalRetencion(row); }
        });
    }

    // Búsqueda y orden en tiempo real: reemplazan solo la tabla vía AJAX, sin
    // recargar la página (el input nunca pierde el foco). Mismo patrón que
    // ASIENTOTIPO_cargarListado (public/js/modulos/asientos_tipo_modal.js).
    var timer = null;
    window.RETENCIONES_currentSort = '<?= htmlspecialchars($ordenCol) ?>';
    window.RETENCIONES_currentDir = '<?= htmlspecialchars($ordenDir) ?>';

    window.RETENCIONES_cargarListado = function() {
        var inputB = document.getElementById('input-buscar-retenciones');
        var b = inputB ? inputB.value.trim() : '';
        var tbodyEl = document.getElementById('tbodyRetenciones');
        if (tbodyEl) tbodyEl.innerHTML = '<tr><td colspan="8" class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary"></span> Cargando...</td></tr>';

        fetch(base + '/config/retenciones-sri-search?b=' + encodeURIComponent(b) + '&sort=' + window.RETENCIONES_currentSort + '&dir=' + window.RETENCIONES_currentDir, {
                credentials: 'same-origin'
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.ok && tbodyEl) tbodyEl.innerHTML = data.rows;
            })
            .catch(function() {
                if (tbodyEl) tbodyEl.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">Error al cargar.</td></tr>';
            });
    };

    var inputBuscar = document.getElementById('input-buscar-retenciones');
    if (inputBuscar) {
        inputBuscar.addEventListener('input', function() {
            clearTimeout(timer);
            timer = setTimeout(function() {
                RETENCIONES_cargarListado();
            }, 400);
        });
    }

    if (window.CMG_initSort) {
        window.CMG_initSort('retenciones-sri', function(col, dir) {
            window.RETENCIONES_currentSort = col;
            window.RETENCIONES_currentDir = dir;
            RETENCIONES_cargarListado();
        }, { col: window.RETENCIONES_currentSort, dir: window.RETENCIONES_currentDir });
    }
})();
</script>
