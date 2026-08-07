<?php
/** @var string $titulo */
/** @var array $rowsProvincias */
/** @var array $rowsCiudades */
/** @var array $provinciasParaSelect */
/** @var string $ordenColProv */
/** @var string $ordenDirProv */
/** @var string $buscarProv */
/** @var string $ordenColCiud */
/** @var string $ordenDirCiud */
/** @var string $buscarCiud */
/** @var string $filtroProv */
$base = BASE_URL;
$rowsProvincias = $rowsProvincias ?? [];
$rowsCiudades = $rowsCiudades ?? [];
$provinciasParaSelect = $provinciasParaSelect ?? [];
$ordenColProv = $ordenColProv ?? 'nombre';
$ordenDirProv = $ordenDirProv ?? 'asc';
$buscarProv = $buscarProv ?? '';
$ordenColCiud = $ordenColCiud ?? 'nombre';
$ordenDirCiud = $ordenDirCiud ?? 'asc';
$buscarCiud = $buscarCiud ?? '';
$filtroProv = $filtroProv ?? '';
$tabActivo = trim($_GET['tab'] ?? 'provincias');
if (!in_array($tabActivo, ['provincias', 'ciudades'], true)) {
    $tabActivo = 'provincias';
}
$msg = $_SESSION['provincia_ciudad_msg'] ?? null;
unset($_SESSION['provincia_ciudad_msg']);

$urlBase = rtrim($base, '/') . '/config/provincia-ciudad';
$urlCiudades = $urlBase . '/ciudades';
$rowsProvinciasHtml = $rowsProvinciasHtml ?? '';
$rowsCiudadesHtml = $rowsCiudadesHtml ?? '';
?>
<style>
.provincia-ciudad-scroll { max-height: calc(100dvh - 320px); overflow-y: auto; }
.provincia-ciudad-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
.prov-row, .ciud-row { cursor: pointer; }
.prov-row:hover, .ciud-row:hover { background-color: rgba(0,0,0,.04); }
</style>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">Gestión de provincias y ciudades. Clic en fila para editar.</p>
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
                <ul class="nav nav-tabs mb-3" id="tabsProvinciaCiudad" role="tablist">
            <li class="nav-item" role="presentation">
                <a href="<?= htmlspecialchars($urlBase) ?>" class="nav-link <?= $tabActivo === 'provincias' ? 'active' : '' ?>">Provincias</a>
            </li>
            <li class="nav-item" role="presentation">
                <a href="<?= htmlspecialchars($urlCiudades) ?>" class="nav-link <?= $tabActivo === 'ciudades' ? 'active' : '' ?>">Ciudades</a>
            </li>
        </ul>

        <div class="tab-content" id="tabsProvinciaCiudadContent">
                <div class="tab-pane fade <?= $tabActivo === 'provincias' ? 'show active' : '' ?>" id="pane-provincias" role="tabpanel">
                <div class="mb-3 d-flex gap-2 flex-wrap align-items-center">
                    <div class="input-group input-group-sm" style="max-width: 280px;">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" id="input-buscar-prov" class="form-control" placeholder="Buscar provincia..." value="<?= htmlspecialchars($buscarProv) ?>" autocomplete="off">
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevaProvincia"><i class="bi bi-plus-lg"></i> Nueva provincia</button>
                </div>
                <div id="provScrollWrap" class="provincia-ciudad-scroll border rounded mt-2">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="sortable-header" data-sort="codigo" role="button">Código <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                                <th class="sortable-header" data-sort="nombre" role="button">Nombre <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            </tr>
                        </thead>
                        <tbody id="tbodyProv"><?= $rowsProvinciasHtml ?></tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade <?= $tabActivo === 'ciudades' ? 'show active' : '' ?>" id="pane-ciudades" role="tabpanel">
                <div class="mb-3 d-flex gap-2 flex-wrap align-items-center">
                    <select id="select-filtro-prov" class="form-select form-select-sm" style="max-width: 200px;">
                        <option value="">Todas las provincias</option>
                        <?php foreach ($provinciasParaSelect as $p): ?>
                        <option value="<?= htmlspecialchars($p['codigo'] ?? '') ?>" <?= ($filtroProv === ($p['codigo'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars($p['nombre'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="input-group input-group-sm" style="max-width: 280px;">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" id="input-buscar-ciud" class="form-control" placeholder="Buscar ciudad..." value="<?= htmlspecialchars($buscarCiud) ?>" autocomplete="off">
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevaCiudad"><i class="bi bi-plus-lg"></i> Nueva ciudad</button>
                </div>
                <div id="ciudScrollWrap" class="provincia-ciudad-scroll border rounded mt-2">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="sortable-header" data-sort="codigo" role="button">Código <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                                <th class="sortable-header" data-sort="nombre" role="button">Nombre <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                                <th class="sortable-header" data-sort="cod_prov" role="button">Provincia <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            </tr>
                        </thead>
                        <tbody id="tbodyCiud"><?= $rowsCiudadesHtml ?></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nueva provincia -->
<div class="modal fade" id="modalNuevaProvincia" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/provincia-ciudad-provincia-store">
                <input type="hidden" name="tab" value="provincias">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Nueva provincia</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="new-prov-codigo" class="form-label">Código <span class="text-danger">*</span></label>
                        <input type="text" id="new-prov-codigo" name="codigo" class="form-control" required placeholder="Ej: 17" maxlength="10">
                    </div>
                    <div class="mb-3">
                        <label for="new-prov-nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" id="new-prov-nombre" name="nombre" class="form-control" required placeholder="Nombre de la provincia">
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

<!-- Modal Editar provincia -->
<div class="modal fade" id="modalEditarProvincia" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/provincia-ciudad-provincia-update">
                <input type="hidden" name="tab" value="provincias">
                <input type="hidden" name="codigo_actual" id="edit-prov-codigo-actual" value="">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Editar provincia</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit-prov-codigo" class="form-label">Código <span class="text-danger">*</span></label>
                        <input type="text" id="edit-prov-codigo" name="codigo" class="form-control" required placeholder="Ej: 17" maxlength="10">
                    </div>
                    <div class="mb-3">
                        <label for="edit-prov-nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" id="edit-prov-nombre" name="nombre" class="form-control" required placeholder="Nombre de la provincia">
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

<!-- Modal Nueva ciudad -->
<div class="modal fade" id="modalNuevaCiudad" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/provincia-ciudad-ciudad-store">
                <input type="hidden" name="tab" value="ciudades">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Nueva ciudad</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="new-ciud-cod-prov" class="form-label">Provincia <span class="text-danger">*</span></label>
                        <select id="new-ciud-cod-prov" name="cod_prov" class="form-select" required>
                            <option value="">Seleccione provincia...</option>
                            <?php foreach ($provinciasParaSelect as $p): ?>
                            <option value="<?= htmlspecialchars($p['codigo'] ?? '') ?>"><?= htmlspecialchars($p['nombre'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="new-ciud-codigo" class="form-label">Código <span class="text-danger">*</span></label>
                        <input type="text" id="new-ciud-codigo" name="codigo" class="form-control" required placeholder="Ej: 001" maxlength="20">
                    </div>
                    <div class="mb-3">
                        <label for="new-ciud-nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" id="new-ciud-nombre" name="nombre" class="form-control" required placeholder="Nombre de la ciudad">
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

<!-- Modal Editar ciudad -->
<div class="modal fade" id="modalEditarCiudad" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/provincia-ciudad-ciudad-update">
                <input type="hidden" name="tab" value="ciudades">
                <input type="hidden" name="codigo_actual" id="edit-ciud-codigo-actual" value="">
                <input type="hidden" name="cod_prov_actual" id="edit-ciud-cod-prov-actual" value="">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Editar ciudad</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit-ciud-cod-prov" class="form-label">Provincia <span class="text-danger">*</span></label>
                        <select id="edit-ciud-cod-prov" name="cod_prov" class="form-select" required>
                            <option value="">Seleccione provincia...</option>
                            <?php foreach ($provinciasParaSelect as $p): ?>
                            <option value="<?= htmlspecialchars($p['codigo'] ?? '') ?>"><?= htmlspecialchars($p['nombre'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit-ciud-codigo" class="form-label">Código <span class="text-danger">*</span></label>
                        <input type="text" id="edit-ciud-codigo" name="codigo" class="form-control" required placeholder="Ej: 001" maxlength="20">
                    </div>
                    <div class="mb-3">
                        <label for="edit-ciud-nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" id="edit-ciud-nombre" name="nombre" class="form-control" required placeholder="Nombre de la ciudad">
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
    var modalProv = document.getElementById('modalEditarProvincia');
    var modalCiud = document.getElementById('modalEditarCiudad');

    function abrirModalProv(row) {
        if (!modalProv) return;
        document.getElementById('edit-prov-codigo-actual').value = row.dataset.codigo || '';
        document.getElementById('edit-prov-codigo').value = row.dataset.codigo || '';
        document.getElementById('edit-prov-nombre').value = row.dataset.nombre || '';
        new bootstrap.Modal(modalProv).show();
    }
    var tbodyProv = document.getElementById('tbodyProv');
    if (tbodyProv) {
        tbodyProv.addEventListener('click', function(e) {
            var row = e.target.closest('.prov-row');
            if (row) abrirModalProv(row);
        });
        tbodyProv.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            var row = e.target.closest('.prov-row');
            if (row) { e.preventDefault(); abrirModalProv(row); }
        });
    }

    function abrirModalCiud(row) {
        if (!modalCiud) return;
        document.getElementById('edit-ciud-codigo-actual').value = row.dataset.codigo || '';
        document.getElementById('edit-ciud-cod-prov-actual').value = row.dataset.codProv || '';
        document.getElementById('edit-ciud-codigo').value = row.dataset.codigo || '';
        document.getElementById('edit-ciud-nombre').value = row.dataset.nombre || '';
        document.getElementById('edit-ciud-cod-prov').value = row.dataset.codProv || '';
        new bootstrap.Modal(modalCiud).show();
    }
    var tbodyCiud = document.getElementById('tbodyCiud');
    if (tbodyCiud) {
        tbodyCiud.addEventListener('click', function(e) {
            var row = e.target.closest('.ciud-row');
            if (row) abrirModalCiud(row);
        });
        tbodyCiud.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            var row = e.target.closest('.ciud-row');
            if (row) { e.preventDefault(); abrirModalCiud(row); }
        });
    }

    // Búsqueda y orden en tiempo real (pestaña Provincias): reemplazan solo
    // la tabla vía AJAX, sin recargar la página. Mismo patrón que
    // ASIENTOTIPO_cargarListado (public/js/modulos/asientos_tipo_modal.js).
    var timerProv = null;
    window.PROV_currentSort = '<?= htmlspecialchars($ordenColProv) ?>';
    window.PROV_currentDir = '<?= htmlspecialchars($ordenDirProv) ?>';
    window.PROV_cargarListado = function() {
        var inputB = document.getElementById('input-buscar-prov');
        var b = inputB ? inputB.value.trim() : '';
        var tbodyEl = document.getElementById('tbodyProv');
        if (tbodyEl) tbodyEl.innerHTML = '<tr><td colspan="2" class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary"></span> Cargando...</td></tr>';
        fetch(base + '/config/provincia-ciudad-provincias-search?b_prov=' + encodeURIComponent(b) + '&sort_prov=' + window.PROV_currentSort + '&dir_prov=' + window.PROV_currentDir, { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) { if (data.ok && tbodyEl) tbodyEl.innerHTML = data.rows; })
            .catch(function() { if (tbodyEl) tbodyEl.innerHTML = '<tr><td colspan="2" class="text-center text-danger py-4">Error al cargar.</td></tr>'; });
    };
    var inputBuscarProv = document.getElementById('input-buscar-prov');
    if (inputBuscarProv) {
        inputBuscarProv.addEventListener('input', function() {
            clearTimeout(timerProv);
            timerProv = setTimeout(function() { PROV_cargarListado(); }, 400);
        });
    }
    if (window.CMG_initSort && document.getElementById('tbodyProv')) {
        window.CMG_initSort('provincia-ciudad-provincias', function(col, dir) {
            window.PROV_currentSort = col;
            window.PROV_currentDir = dir;
            PROV_cargarListado();
        }, { col: window.PROV_currentSort, dir: window.PROV_currentDir, container: '#provScrollWrap' });
    }

    // Búsqueda, filtro por provincia y orden en tiempo real (pestaña Ciudades).
    var timerCiud = null;
    window.CIUD_currentSort = '<?= htmlspecialchars($ordenColCiud) ?>';
    window.CIUD_currentDir = '<?= htmlspecialchars($ordenDirCiud) ?>';
    window.CIUD_cargarListado = function() {
        var inputB = document.getElementById('input-buscar-ciud');
        var selectProv = document.getElementById('select-filtro-prov');
        var b = inputB ? inputB.value.trim() : '';
        var fProv = selectProv ? selectProv.value : '';
        var tbodyEl = document.getElementById('tbodyCiud');
        if (tbodyEl) tbodyEl.innerHTML = '<tr><td colspan="3" class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary"></span> Cargando...</td></tr>';
        fetch(base + '/config/provincia-ciudad-ciudades-search?b_ciud=' + encodeURIComponent(b) + '&f_prov=' + encodeURIComponent(fProv) + '&sort_ciud=' + window.CIUD_currentSort + '&dir_ciud=' + window.CIUD_currentDir, { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) { if (data.ok && tbodyEl) tbodyEl.innerHTML = data.rows; })
            .catch(function() { if (tbodyEl) tbodyEl.innerHTML = '<tr><td colspan="3" class="text-center text-danger py-4">Error al cargar.</td></tr>'; });
    };
    var inputBuscarCiud = document.getElementById('input-buscar-ciud');
    if (inputBuscarCiud) {
        inputBuscarCiud.addEventListener('input', function() {
            clearTimeout(timerCiud);
            timerCiud = setTimeout(function() { CIUD_cargarListado(); }, 400);
        });
    }
    var selectFiltroProv = document.getElementById('select-filtro-prov');
    if (selectFiltroProv) {
        selectFiltroProv.addEventListener('change', function() { CIUD_cargarListado(); });
    }
    if (window.CMG_initSort && document.getElementById('tbodyCiud')) {
        window.CMG_initSort('provincia-ciudad-ciudades', function(col, dir) {
            window.CIUD_currentSort = col;
            window.CIUD_currentDir = dir;
            CIUD_cargarListado();
        }, { col: window.CIUD_currentSort, dir: window.CIUD_currentDir, container: '#ciudScrollWrap' });
    }
})();
</script>
