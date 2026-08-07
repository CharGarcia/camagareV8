<?php
/** @var string $titulo */
/** @var array $rows */
/** @var string $ordenCol */
/** @var string $ordenDir */
/** @var string $buscar */
$base = BASE_URL;
$rows = $rows ?? [];
$ordenCol = $ordenCol ?? 'codigo';
$ordenDir = $ordenDir ?? 'asc';
$buscar = $buscar ?? '';
$msg = $_SESSION['tipos_novedades_nomina_msg'] ?? null;
unset($_SESSION['tipos_novedades_nomina_msg']);
$rowsHtml = $rowsHtml ?? '';
?>
<style>
.tipo-novedad-row { cursor: pointer; }
.tipo-novedad-row:hover { background-color: rgba(0,0,0,.04); }
.tipos-novedades-scroll { max-height: calc(100dvh - 280px); overflow-y: auto; }
.tipos-novedades-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
</style>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-journal-plus"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">Haga clic en una fila para editar. Tipos de novedades para nómina. Código único.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoTipoNovedad"><i class="bi bi-plus-lg"></i> Crear nuevo</button>
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
    <input type="text" id="input-buscar-tnovedades" class="form-control" placeholder="Buscar código o nombre..." value="<?= htmlspecialchars($buscar) ?>" autocomplete="off">
</div>

<div class="card cmg-table-card">
    <div class="card-body p-0">
        <div class="tipos-novedades-scroll">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="sortable-header" data-sort="codigo" role="button">Código <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="nombre" role="button">Nombre <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" data-sort="status" role="button">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end" style="width: 80px;">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tbodyTNovedades"><?= $rowsHtml ?></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Nuevo tipo de novedad -->
<div class="modal fade" id="modalNuevoTipoNovedad" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/tiposNovedadesNominaStore" id="form-crear-tipo-novedad">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Nuevo tipo de novedad</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="crear-tipo-novedad-msg" class="d-none"></div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="new-codigo" class="form-label">Código <span class="text-danger">*</span></label>
                            <input type="text" id="new-codigo" name="codigo" class="form-control form-control-sm" required placeholder="Ej: 01">
                        </div>
                        <div class="col-md-4">
                            <label for="new-status" class="form-label">Estado</label>
                            <select id="new-status" name="status" class="form-select form-select-sm">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="new-nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                            <input type="text" id="new-nombre" name="nombre" class="form-control form-control-sm" required placeholder="Ej: Ingreso fijo mensual gravable">
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

<!-- Modal Editar tipo de novedad -->
<div class="modal fade" id="modalEditarTipoNovedad" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/tiposNovedadesNominaUpdate" id="form-editar-tipo-novedad">
                <input type="hidden" name="id" id="edit-id" value="">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Editar tipo de novedad</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="editar-tipo-novedad-msg" class="d-none"></div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="edit-codigo" class="form-label">Código <span class="text-danger">*</span></label>
                            <input type="text" id="edit-codigo" name="codigo" class="form-control form-control-sm" required placeholder="Ej: 01">
                        </div>
                        <div class="col-md-4">
                            <label for="edit-status" class="form-label">Estado</label>
                            <select id="edit-status" name="status" class="form-select form-select-sm">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="edit-nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                            <input type="text" id="edit-nombre" name="nombre" class="form-control form-control-sm" required placeholder="Ej: Ingreso fijo mensual gravable">
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
    var base = '<?= rtrim($base ?? BASE_URL, "/") ?>';
    var modalEditar = document.getElementById('modalEditarTipoNovedad');
    var modalNuevo = document.getElementById('modalNuevoTipoNovedad');

    function mostrarMsgForm(containerId, tipo, texto) {
        var el = document.getElementById(containerId);
        if (!el) return;
        el.className = 'alert alert-' + (tipo === 'error' ? 'danger' : 'success') + ' alert-dismissible fade show mb-3';
        el.innerHTML = texto + ' <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>';
        el.classList.remove('d-none');
    }
    function ocultarMsgForm(containerId) {
        var el = document.getElementById(containerId);
        if (el) el.classList.add('d-none');
    }
    function enviarFormAjax(form, msgContainerId, url) {
        return fetch(url, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.ok) {
                mostrarMsgForm(msgContainerId, 'success', res.msg || 'Guardado correctamente.');
                setTimeout(function() { window.location.href = base + '/config/tipos-novedades-nomina'; }, 1500);
            } else {
                mostrarMsgForm(msgContainerId, 'error', res.error || 'Error desconocido.');
                return Promise.reject();
            }
        })
        .catch(function(err) {
            mostrarMsgForm(msgContainerId, 'error', err.message || 'Error de conexión. Intente de nuevo.');
            return Promise.reject();
        });
    }

    if (modalEditar) {
        var formEditar = modalEditar.querySelector('#form-editar-tipo-novedad');
        var tbodyTNovedades = document.getElementById('tbodyTNovedades');
        function abrirModalTNovedad(row) {
            ocultarMsgForm('editar-tipo-novedad-msg');
            formEditar.querySelector('#edit-id').value = row.dataset.id || '';
            formEditar.querySelector('#edit-codigo').value = row.dataset.codigo || '';
            formEditar.querySelector('#edit-nombre').value = row.dataset.nombre || '';
            formEditar.querySelector('#edit-status').value = row.dataset.status || '1';
            new bootstrap.Modal(modalEditar).show();
        }
        // Delegación de eventos: las filas se reemplazan en cada búsqueda/orden
        // AJAX, por lo que el listener va en el tbody (contenedor fijo).
        if (tbodyTNovedades) {
            tbodyTNovedades.addEventListener('click', function(e) {
                if (e.target.closest('form, button')) return;
                var row = e.target.closest('.tipo-novedad-row');
                if (row) abrirModalTNovedad(row);
            });
            tbodyTNovedades.addEventListener('keydown', function(e) {
                if ((e.key !== 'Enter' && e.key !== ' ') || e.target.closest('form, button')) return;
                var row = e.target.closest('.tipo-novedad-row');
                if (row) { e.preventDefault(); abrirModalTNovedad(row); }
            });
        }
        if (formEditar) {
            formEditar.addEventListener('submit', function(e) {
                e.preventDefault();
                ocultarMsgForm('editar-tipo-novedad-msg');
                var btn = formEditar.querySelector('button[type="submit"]');
                var txtOrig = btn ? btn.innerHTML : '';
                if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...'; }
                enviarFormAjax(formEditar, 'editar-tipo-novedad-msg', base + '/config/tiposNovedadesNominaUpdate')
                    .catch(function() { if (btn) { btn.disabled = false; btn.innerHTML = txtOrig; } });
            });
        }
        modalEditar.addEventListener('show.bs.modal', function() { ocultarMsgForm('editar-tipo-novedad-msg'); });
    }

    if (modalNuevo) {
        modalNuevo.addEventListener('show.bs.modal', function() { ocultarMsgForm('crear-tipo-novedad-msg'); });
        var formCrear = document.getElementById('form-crear-tipo-novedad');
        if (formCrear) {
            formCrear.addEventListener('submit', function(e) {
                e.preventDefault();
                ocultarMsgForm('crear-tipo-novedad-msg');
                var btn = formCrear.querySelector('button[type="submit"]');
                var txtOrig = btn ? btn.innerHTML : '';
                if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...'; }
                enviarFormAjax(formCrear, 'crear-tipo-novedad-msg', base + '/config/tiposNovedadesNominaStore')
                    .catch(function() { if (btn) { btn.disabled = false; btn.innerHTML = txtOrig; } });
            });
        }
    }

    // Búsqueda y orden en tiempo real: reemplazan solo la tabla vía AJAX, sin
    // recargar la página (el input nunca pierde el foco). Mismo patrón que
    // ASIENTOTIPO_cargarListado (public/js/modulos/asientos_tipo_modal.js).
    var timer = null;
    window.TNOVEDADES_currentSort = '<?= htmlspecialchars($ordenCol) ?>';
    window.TNOVEDADES_currentDir = '<?= htmlspecialchars($ordenDir) ?>';

    window.TNOVEDADES_cargarListado = function() {
        var inputB = document.getElementById('input-buscar-tnovedades');
        var b = inputB ? inputB.value.trim() : '';
        var tbodyEl = document.getElementById('tbodyTNovedades');
        if (tbodyEl) tbodyEl.innerHTML = '<tr><td colspan="4" class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary"></span> Cargando...</td></tr>';

        fetch(base + '/config/tipos-novedades-nomina-search?b=' + encodeURIComponent(b) + '&sort=' + window.TNOVEDADES_currentSort + '&dir=' + window.TNOVEDADES_currentDir, {
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

    var inputBuscar = document.getElementById('input-buscar-tnovedades');
    if (inputBuscar) {
        inputBuscar.addEventListener('input', function() {
            clearTimeout(timer);
            timer = setTimeout(function() {
                TNOVEDADES_cargarListado();
            }, 400);
        });
    }

    if (window.CMG_initSort) {
        window.CMG_initSort('tipos-novedades-nomina', function(col, dir) {
            window.TNOVEDADES_currentSort = col;
            window.TNOVEDADES_currentDir = dir;
            TNOVEDADES_cargarListado();
        }, { col: window.TNOVEDADES_currentSort, dir: window.TNOVEDADES_currentDir });
    }
})();
</script>
