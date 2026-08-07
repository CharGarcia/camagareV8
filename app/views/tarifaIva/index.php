<?php
/** @var string $titulo */
/** @var array $rows */
/** @var string $ordenCol */
/** @var string $ordenDir */
/** @var string $buscar */
$base = BASE_URL;
$rows = $rows ?? [];
$ordenCol = $ordenCol ?? 'porcentaje_iva';
$ordenDir = $ordenDir ?? 'asc';
$buscar = $buscar ?? '';
$msg = $_SESSION['tarifa_iva_msg'] ?? null;
unset($_SESSION['tarifa_iva_msg']);
$rowsHtml = $rowsHtml ?? '';
?>
<style>
.tarifa-row { cursor: pointer; }
.tarifa-row:hover { background-color: rgba(0,0,0,.04); }
.tarifa-iva-scroll { max-height: calc(100dvh - 280px); overflow-y: auto; }
.tarifa-iva-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
</style>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-percent"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">Haga clic en una fila para editar. Tarifas IVA para facturación.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevaTarifa"><i class="bi bi-plus-lg"></i> Crear nueva</button>
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
    <input type="text" id="input-buscar-tarifaiva" class="form-control" placeholder="Buscar código, tarifa o porcentaje..." value="<?= htmlspecialchars($buscar) ?>" autocomplete="off">
</div>

<div class="card cmg-table-card">
    <div class="card-body p-0">
        <div class="tarifa-iva-scroll">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="sortable-header" data-sort="codigo" role="button">Código <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="tarifa" role="button">Tarifa <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" data-sort="porcentaje_iva" role="button">% IVA <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" data-sort="status" role="button">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyTarifaIva"><?= $rowsHtml ?></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Nueva tarifa -->
<div class="modal fade" id="modalNuevaTarifa" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/tarifaIvaStore">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Nueva tarifa IVA</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="new-codigo" class="form-label">Código SRI <span class="text-danger">*</span></label>
                            <input type="text" id="new-codigo" name="codigo" class="form-control" required placeholder="Ej: 0, 2, 6, 7" maxlength="2">
                        </div>
                        <div class="col-md-4">
                            <label for="new-porcentaje_iva" class="form-label">% IVA</label>
                            <input type="number" id="new-porcentaje_iva" name="porcentaje_iva" class="form-control" min="0" max="100" value="0">
                        </div>
                        <div class="col-md-4">
                            <label for="new-status" class="form-label">Estado</label>
                            <select id="new-status" name="status" class="form-select">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="new-tarifa" class="form-label">Descripción / Tarifa</label>
                            <input type="text" id="new-tarifa" name="tarifa" class="form-control" placeholder="Ej: 0%, 12%, No objeto de impuesto, Exento de IVA">
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

<!-- Modal Editar tarifa -->
<div class="modal fade" id="modalEditarTarifa" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/tarifaIvaUpdate">
                <input type="hidden" name="id" id="edit-id" value="">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Editar tarifa IVA</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="edit-codigo" class="form-label">Código SRI <span class="text-danger">*</span></label>
                            <input type="text" id="edit-codigo" name="codigo" class="form-control" required placeholder="Ej: 0, 2, 6, 7" maxlength="2">
                        </div>
                        <div class="col-md-4">
                            <label for="edit-porcentaje_iva" class="form-label">% IVA</label>
                            <input type="number" id="edit-porcentaje_iva" name="porcentaje_iva" class="form-control" min="0" max="100" value="0">
                        </div>
                        <div class="col-md-4">
                            <label for="edit-status" class="form-label">Estado</label>
                            <select id="edit-status" name="status" class="form-select">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="edit-tarifa" class="form-label">Descripción / Tarifa</label>
                            <input type="text" id="edit-tarifa" name="tarifa" class="form-control" placeholder="Ej: 0%, 12%, No objeto de impuesto">
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
    var base = '<?= $base ?>';
    var modal = document.getElementById('modalEditarTarifa');
    var form = modal ? modal.querySelector('form') : null;

    function abrirModalTarifa(row) {
        if (!modal || !form) return;
        form.querySelector('#edit-id').value = row.dataset.id || '';
        form.querySelector('#edit-codigo').value = row.dataset.codigo || '';
        form.querySelector('#edit-tarifa').value = row.dataset.tarifa || '';
        form.querySelector('#edit-porcentaje_iva').value = row.dataset.porcentaje || '0';
        form.querySelector('#edit-status').value = row.dataset.status || '1';
        new bootstrap.Modal(modal).show();
    }

    // Delegación de eventos: las filas se reemplazan en cada búsqueda/orden
    // AJAX, por lo que el listener va en el tbody (contenedor fijo).
    var tbodyTarifaIva = document.getElementById('tbodyTarifaIva');
    if (tbodyTarifaIva) {
        tbodyTarifaIva.addEventListener('click', function(e) {
            var row = e.target.closest('.tarifa-row');
            if (row) abrirModalTarifa(row);
        });
        tbodyTarifaIva.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            var row = e.target.closest('.tarifa-row');
            if (row) { e.preventDefault(); abrirModalTarifa(row); }
        });
    }

    // Búsqueda y orden en tiempo real: reemplazan solo la tabla vía AJAX, sin
    // recargar la página (el input nunca pierde el foco). Mismo patrón que
    // ASIENTOTIPO_cargarListado (public/js/modulos/asientos_tipo_modal.js).
    var timer = null;
    window.TARIFAIVA_currentSort = '<?= htmlspecialchars($ordenCol) ?>';
    window.TARIFAIVA_currentDir = '<?= htmlspecialchars($ordenDir) ?>';

    window.TARIFAIVA_cargarListado = function() {
        var inputB = document.getElementById('input-buscar-tarifaiva');
        var b = inputB ? inputB.value.trim() : '';
        var tbodyEl = document.getElementById('tbodyTarifaIva');
        if (tbodyEl) tbodyEl.innerHTML = '<tr><td colspan="4" class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary"></span> Cargando...</td></tr>';

        fetch(base + '/config/tarifa-iva-search?b=' + encodeURIComponent(b) + '&sort=' + window.TARIFAIVA_currentSort + '&dir=' + window.TARIFAIVA_currentDir, {
                credentials: 'same-origin'
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.ok && tbodyEl) tbodyEl.innerHTML = data.rows;
            })
            .catch(function() {
                if (tbodyEl) tbodyEl.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-4">Error al cargar.</td></tr>';
            });
    };

    var inputBuscar = document.getElementById('input-buscar-tarifaiva');
    if (inputBuscar) {
        inputBuscar.addEventListener('input', function() {
            clearTimeout(timer);
            timer = setTimeout(function() {
                TARIFAIVA_cargarListado();
            }, 400);
        });
    }

    if (window.CMG_initSort) {
        window.CMG_initSort('tarifa-iva', function(col, dir) {
            window.TARIFAIVA_currentSort = col;
            window.TARIFAIVA_currentDir = dir;
            TARIFAIVA_cargarListado();
        }, { col: window.TARIFAIVA_currentSort, dir: window.TARIFAIVA_currentDir });
    }
})();
</script>
