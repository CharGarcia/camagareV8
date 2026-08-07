<?php
/** @var string $titulo */
/** @var array $rowsTipos */
/** @var array $rowsUnidades */
/** @var array $tiposParaSelect */
/** @var string $tab */
/** @var string $ordenColTipo */
/** @var string $ordenDirTipo */
/** @var string $buscarTipo */
/** @var string $ordenColUni */
/** @var string $ordenDirUni */
/** @var string $buscarUni */
/** @var int|null $filtroTipo */
$base = BASE_URL;
$rowsTipos = $rowsTipos ?? [];
$rowsUnidades = $rowsUnidades ?? [];
$tiposParaSelect = $tiposParaSelect ?? [];
$tab = $tab ?? 'tipos';
$ordenColTipo = $ordenColTipo ?? 'nombre';
$ordenDirTipo = $ordenDirTipo ?? 'asc';
$buscarTipo = $buscarTipo ?? '';
$ordenColUni = $ordenColUni ?? 'nombre';
$ordenDirUni = $ordenDirUni ?? 'asc';
$buscarUni = $buscarUni ?? '';
$filtroTipo = $filtroTipo ?? null;
$msg = $_SESSION['unidades_msg'] ?? null;
unset($_SESSION['unidades_msg']);

$urlBase = rtrim($base, '/') . '/config/unidades-medida';
$rowsTiposHtml = $rowsTiposHtml ?? '';
$rowsUnidadesHtml = $rowsUnidadesHtml ?? '';
?>
<style>
.unidades-scroll { max-height: calc(100dvh - 320px); overflow-y: auto; }
.unidades-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
.tipo-row, .unidad-row { cursor: pointer; }
.tipo-row:hover, .unidad-row:hover { background-color: rgba(0,0,0,.04); }
</style>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-rulers"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">Gestión de tipos de unidad y unidades de medida. Clic en fila para editar.</p>
    </div>
    <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= htmlspecialchars($msg[0]) ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($msg[1]) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <ul class="nav nav-tabs mb-3" id="tabsUnidades" role="tablist">
            <li class="nav-item" role="presentation">
                <a href="<?= htmlspecialchars($urlBase . '?tab=tipos' . ($buscarTipo ? '&b_tipo=' . urlencode($buscarTipo) : '')) ?>" class="nav-link <?= $tab === 'tipos' ? 'active' : '' ?>">Tipos de unidad</a>
            </li>
            <li class="nav-item" role="presentation">
                <a href="<?= htmlspecialchars($urlBase . '?tab=unidades' . ($buscarUni ? '&b_uni=' . urlencode($buscarUni) : '') . ($filtroTipo ? '&f_tipo=' . (int)$filtroTipo : '')) ?>" class="nav-link <?= $tab === 'unidades' ? 'active' : '' ?>">Unidades de medida</a>
            </li>
        </ul>

        <?php if ($tab === 'tipos'): ?>
        <div class="mb-3">
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <div class="input-group input-group-sm" style="max-width: 280px;">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" id="input-buscar-tipo" class="form-control" placeholder="Buscar tipo..." value="<?= htmlspecialchars($buscarTipo) ?>" autocomplete="off">
                </div>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoTipo"><i class="bi bi-plus-lg"></i> Nuevo tipo</button>
            </div>
        </div>
        <div class="unidades-scroll">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="sortable-header" data-sort="codigo" role="button">Código <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="nombre" role="button">Nombre <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th>Descripción</th>
                        <th class="text-center sortable-header" data-sort="estado" role="button">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyTipos"><?= $rowsTiposHtml ?></tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($tab === 'unidades'): ?>
        <div class="mb-3">
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <div class="input-group input-group-sm" style="max-width: 280px;">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" id="input-buscar-uni" class="form-control" placeholder="Buscar unidad..." value="<?= htmlspecialchars($buscarUni) ?>" autocomplete="off">
                </div>
                <select id="select-filtro-tipo" class="form-select form-select-sm" style="max-width: 200px;">
                    <option value="">Todos los tipos</option>
                    <?php foreach ($tiposParaSelect as $t): ?>
                    <option value="<?= (int)$t['id'] ?>" <?= $filtroTipo === (int)$t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['nombre'] ?? $t['codigo'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevaUnidad"><i class="bi bi-plus-lg"></i> Nueva unidad</button>
            </div>
        </div>
        <div class="unidades-scroll">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="sortable-header" data-sort="codigo" role="button">Código <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="nombre" role="button">Nombre <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="abreviatura" role="button">Abreviatura <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th>Tipo</th>
                        <th class="text-end">Factor base</th>
                        <th class="text-center sortable-header" data-sort="estado" role="button">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyUnidades"><?= $rowsUnidadesHtml ?></tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Nuevo tipo -->
<div class="modal fade" id="modalNuevoTipo" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/unidadesMedidaTipoStore">
                <input type="hidden" name="tab" value="tipos">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Nuevo tipo de unidad</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Código</label>
                        <input type="text" name="codigo" class="form-control" placeholder="Ej: PESO">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" class="form-control" required placeholder="Ej: Peso">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea name="descripcion" class="form-control" rows="2" placeholder="Descripción opcional"></textarea>
                    </div>
                    <div class="mb-0">
                        <div class="form-check">
                            <input type="checkbox" name="estado" id="new-tipo-estado" class="form-check-input" value="1" checked>
                            <label class="form-check-label" for="new-tipo-estado">Activo</label>
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

<!-- Modal Editar tipo -->
<div class="modal fade" id="modalEditarTipo" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/unidadesMedidaTipoUpdate">
                <input type="hidden" name="id" id="edit-tipo-id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Editar tipo de unidad</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Código</label>
                        <input type="text" name="codigo" id="edit-tipo-codigo" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" id="edit-tipo-nombre" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea name="descripcion" id="edit-tipo-descripcion" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-0">
                        <div class="form-check">
                            <input type="checkbox" name="estado" id="edit-tipo-estado" class="form-check-input" value="1">
                            <label class="form-check-label" for="edit-tipo-estado">Activo</label>
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

<!-- Modal Nueva unidad -->
<div class="modal fade" id="modalNuevaUnidad" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/unidadesMedidaUnidadStore">
                <input type="hidden" name="tab" value="unidades">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Nueva unidad de medida</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Tipo <span class="text-danger">*</span></label>
                        <select name="id_tipo" class="form-select" required>
                            <option value="">-- Seleccione --</option>
                            <?php foreach ($tiposParaSelect as $t): ?>
                            <option value="<?= (int)$t['id'] ?>" <?= $filtroTipo === (int)$t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['nombre'] ?? $t['codigo'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">Código</label>
                            <input type="text" name="codigo" class="form-control" placeholder="Ej: KG">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Abreviatura</label>
                            <input type="text" name="abreviatura" class="form-control" placeholder="Ej: kg">
                        </div>
                    </div>
                    <div class="mb-3 mt-2">
                        <label class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" class="form-control" required placeholder="Ej: Kilogramo">
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="form-check mt-2">
                                <input type="checkbox" name="es_base" id="new-uni-es-base" class="form-check-input" value="1">
                                <label class="form-check-label" for="new-uni-es-base">Es unidad base</label>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Factor base</label>
                            <input type="number" name="factor_base" class="form-control" step="0.000001" value="1" min="0">
                        </div>
                    </div>
                    <div class="mb-0 mt-2">
                        <div class="form-check">
                            <input type="checkbox" name="estado" id="new-uni-estado" class="form-check-input" value="1" checked>
                            <label class="form-check-label" for="new-uni-estado">Activo</label>
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

<!-- Modal Editar unidad -->
<div class="modal fade" id="modalEditarUnidad" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/unidadesMedidaUnidadUpdate">
                <input type="hidden" name="id" id="edit-uni-id">
                <input type="hidden" name="tab" value="unidades">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Editar unidad de medida</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Tipo <span class="text-danger">*</span></label>
                        <select name="id_tipo" id="edit-uni-id-tipo" class="form-select" required>
                            <option value="">-- Seleccione --</option>
                            <?php foreach ($tiposParaSelect as $t): ?>
                            <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['nombre'] ?? $t['codigo'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">Código</label>
                            <input type="text" name="codigo" id="edit-uni-codigo" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Abreviatura</label>
                            <input type="text" name="abreviatura" id="edit-uni-abreviatura" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3 mt-2">
                        <label class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" id="edit-uni-nombre" class="form-control" required>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="form-check mt-2">
                                <input type="checkbox" name="es_base" id="edit-uni-es-base" class="form-check-input" value="1">
                                <label class="form-check-label" for="edit-uni-es-base">Es unidad base</label>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Factor base</label>
                            <input type="number" name="factor_base" id="edit-uni-factor-base" class="form-control" step="0.000001" min="0">
                        </div>
                    </div>
                    <div class="mb-0 mt-2">
                        <div class="form-check">
                            <input type="checkbox" name="estado" id="edit-uni-estado" class="form-check-input" value="1">
                            <label class="form-check-label" for="edit-uni-estado">Activo</label>
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

    var modalTipo = document.getElementById('modalEditarTipo');
    var formTipo = modalTipo ? modalTipo.querySelector('form') : null;
    function abrirModalTipo(row) {
        if (!modalTipo || !formTipo) return;
        formTipo.querySelector('#edit-tipo-id').value = row.dataset.id || '';
        formTipo.querySelector('#edit-tipo-codigo').value = row.dataset.codigo || '';
        formTipo.querySelector('#edit-tipo-nombre').value = row.dataset.nombre || '';
        formTipo.querySelector('#edit-tipo-descripcion').value = row.dataset.descripcion || '';
        formTipo.querySelector('#edit-tipo-estado').checked = row.dataset.estado === '1';
        new bootstrap.Modal(modalTipo).show();
    }
    var tbodyTipos = document.getElementById('tbodyTipos');
    if (tbodyTipos) {
        tbodyTipos.addEventListener('click', function(e) {
            var row = e.target.closest('.tipo-row');
            if (row) abrirModalTipo(row);
        });
        tbodyTipos.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            var row = e.target.closest('.tipo-row');
            if (row) { e.preventDefault(); abrirModalTipo(row); }
        });
    }

    var modalUni = document.getElementById('modalEditarUnidad');
    var formUni = modalUni ? modalUni.querySelector('form') : null;
    function abrirModalUnidad(row) {
        if (!modalUni || !formUni) return;
        formUni.querySelector('#edit-uni-id').value = row.dataset.id || '';
        formUni.querySelector('#edit-uni-id-tipo').value = row.dataset.idTipo || '';
        formUni.querySelector('#edit-uni-codigo').value = row.dataset.codigo || '';
        formUni.querySelector('#edit-uni-nombre').value = row.dataset.nombre || '';
        formUni.querySelector('#edit-uni-abreviatura').value = row.dataset.abreviatura || '';
        formUni.querySelector('#edit-uni-es-base').checked = row.dataset.esBase === '1';
        formUni.querySelector('#edit-uni-factor-base').value = row.dataset.factorBase || '1';
        formUni.querySelector('#edit-uni-estado').checked = row.dataset.estado === '1';
        new bootstrap.Modal(modalUni).show();
    }
    var tbodyUnidades = document.getElementById('tbodyUnidades');
    if (tbodyUnidades) {
        tbodyUnidades.addEventListener('click', function(e) {
            var row = e.target.closest('.unidad-row');
            if (row) abrirModalUnidad(row);
        });
        tbodyUnidades.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            var row = e.target.closest('.unidad-row');
            if (row) { e.preventDefault(); abrirModalUnidad(row); }
        });
    }

    // Búsqueda y orden en tiempo real (pestaña Tipos de unidad): reemplazan
    // solo la tabla vía AJAX, sin recargar la página. Mismo patrón que
    // ASIENTOTIPO_cargarListado (public/js/modulos/asientos_tipo_modal.js).
    var timerTipo = null;
    window.UTIPO_currentSort = '<?= htmlspecialchars($ordenColTipo) ?>';
    window.UTIPO_currentDir = '<?= htmlspecialchars($ordenDirTipo) ?>';
    window.UTIPO_cargarListado = function() {
        var inputB = document.getElementById('input-buscar-tipo');
        var b = inputB ? inputB.value.trim() : '';
        var tbodyEl = document.getElementById('tbodyTipos');
        if (tbodyEl) tbodyEl.innerHTML = '<tr><td colspan="4" class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary"></span> Cargando...</td></tr>';
        fetch(base + '/config/unidades-medida-tipos-search?b_tipo=' + encodeURIComponent(b) + '&sort_tipo=' + window.UTIPO_currentSort + '&dir_tipo=' + window.UTIPO_currentDir, { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) { if (data.ok && tbodyEl) tbodyEl.innerHTML = data.rows; })
            .catch(function() { if (tbodyEl) tbodyEl.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-4">Error al cargar.</td></tr>'; });
    };
    var inputBuscarTipo = document.getElementById('input-buscar-tipo');
    if (inputBuscarTipo) {
        inputBuscarTipo.addEventListener('input', function() {
            clearTimeout(timerTipo);
            timerTipo = setTimeout(function() { UTIPO_cargarListado(); }, 400);
        });
    }
    if (window.CMG_initSort && document.getElementById('tbodyTipos')) {
        window.CMG_initSort('unidades-medida-tipos', function(col, dir) {
            window.UTIPO_currentSort = col;
            window.UTIPO_currentDir = dir;
            UTIPO_cargarListado();
        }, { col: window.UTIPO_currentSort, dir: window.UTIPO_currentDir });
    }

    // Búsqueda, filtro por tipo y orden en tiempo real (pestaña Unidades de medida).
    var timerUni = null;
    window.UUNI_currentSort = '<?= htmlspecialchars($ordenColUni) ?>';
    window.UUNI_currentDir = '<?= htmlspecialchars($ordenDirUni) ?>';
    window.UUNI_cargarListado = function() {
        var inputB = document.getElementById('input-buscar-uni');
        var selectTipo = document.getElementById('select-filtro-tipo');
        var b = inputB ? inputB.value.trim() : '';
        var fTipo = selectTipo ? selectTipo.value : '';
        var tbodyEl = document.getElementById('tbodyUnidades');
        if (tbodyEl) tbodyEl.innerHTML = '<tr><td colspan="6" class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary"></span> Cargando...</td></tr>';
        fetch(base + '/config/unidades-medida-unidades-search?b_uni=' + encodeURIComponent(b) + '&f_tipo=' + encodeURIComponent(fTipo) + '&sort_uni=' + window.UUNI_currentSort + '&dir_uni=' + window.UUNI_currentDir, { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) { if (data.ok && tbodyEl) tbodyEl.innerHTML = data.rows; })
            .catch(function() { if (tbodyEl) tbodyEl.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">Error al cargar.</td></tr>'; });
    };
    var inputBuscarUni = document.getElementById('input-buscar-uni');
    if (inputBuscarUni) {
        inputBuscarUni.addEventListener('input', function() {
            clearTimeout(timerUni);
            timerUni = setTimeout(function() { UUNI_cargarListado(); }, 400);
        });
    }
    var selectFiltroTipo = document.getElementById('select-filtro-tipo');
    if (selectFiltroTipo) {
        selectFiltroTipo.addEventListener('change', function() { UUNI_cargarListado(); });
    }
    if (window.CMG_initSort && document.getElementById('tbodyUnidades')) {
        window.CMG_initSort('unidades-medida-unidades', function(col, dir) {
            window.UUNI_currentSort = col;
            window.UUNI_currentDir = dir;
            UUNI_cargarListado();
        }, { col: window.UUNI_currentSort, dir: window.UUNI_currentDir });
    }
})();
</script>
