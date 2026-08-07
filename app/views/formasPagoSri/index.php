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
$msg = $_SESSION['formas_pago_msg'] ?? null;
unset($_SESSION['formas_pago_msg']);
$rowsHtml = $rowsHtml ?? '';
?>
<style>
.forma-pago-row { cursor: pointer; }
.forma-pago-row:hover { background-color: rgba(0,0,0,.04); }
.formas-pago-scroll { max-height: calc(100dvh - 280px); overflow-y: auto; }
.formas-pago-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
</style>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-cash-stack"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">Haga clic en una fila para editar. Formas de pago del SRI. Código único.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevaFormaPago"><i class="bi bi-plus-lg"></i> Crear nuevo</button>
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
    <input type="text" id="input-buscar-formaspago" class="form-control" placeholder="Buscar código o nombre..." value="<?= htmlspecialchars($buscar) ?>" autocomplete="off">
</div>

<div class="card cmg-table-card">
    <div class="card-body p-0">
        <div class="formas-pago-scroll">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="sortable-header" data-sort="codigo" role="button">Código <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="nombre" role="button">Nombre <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" data-sort="status" role="button">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end" style="width: 80px;">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tbodyFormasPago"><?= $rowsHtml ?></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Nueva forma de pago -->
<div class="modal fade" id="modalNuevaFormaPago" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/formasPagoSriStore" id="form-crear-forma-pago">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Nueva forma de pago</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="crear-forma-pago-msg" class="d-none"></div>
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
                            <input type="text" id="new-nombre" name="nombre" class="form-control form-control-sm" required placeholder="Ej: Efectivo">
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

<!-- Modal Editar forma de pago -->
<div class="modal fade" id="modalEditarFormaPago" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/formasPagoSriUpdate" id="form-editar-forma-pago">
                <input type="hidden" name="id" id="edit-id" value="">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Editar forma de pago</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="editar-forma-pago-msg" class="d-none"></div>
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
                            <input type="text" id="edit-nombre" name="nombre" class="form-control form-control-sm" required placeholder="Ej: Efectivo">
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
    var modalEditar = document.getElementById('modalEditarFormaPago');
    var modalNuevo = document.getElementById('modalNuevaFormaPago');

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
                setTimeout(function() { window.location.href = base + '/config/formas-pago-sri'; }, 1500);
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
        var formEditar = modalEditar.querySelector('#form-editar-forma-pago');
        var tbodyFormasPago = document.getElementById('tbodyFormasPago');
        function abrirModalFormaPago(row) {
            ocultarMsgForm('editar-forma-pago-msg');
            formEditar.querySelector('#edit-id').value = row.dataset.id || '';
            formEditar.querySelector('#edit-codigo').value = row.dataset.codigo || '';
            formEditar.querySelector('#edit-nombre').value = row.dataset.nombre || '';
            formEditar.querySelector('#edit-status').value = row.dataset.status || '1';
            new bootstrap.Modal(modalEditar).show();
        }
        // Delegación de eventos: las filas se reemplazan en cada búsqueda/orden
        // AJAX, por lo que el listener va en el tbody (contenedor fijo).
        if (tbodyFormasPago) {
            tbodyFormasPago.addEventListener('click', function(e) {
                if (e.target.closest('form, button')) return;
                var row = e.target.closest('.forma-pago-row');
                if (row) abrirModalFormaPago(row);
            });
            tbodyFormasPago.addEventListener('keydown', function(e) {
                if ((e.key !== 'Enter' && e.key !== ' ') || e.target.closest('form, button')) return;
                var row = e.target.closest('.forma-pago-row');
                if (row) { e.preventDefault(); abrirModalFormaPago(row); }
            });
        }
        if (formEditar) {
            formEditar.addEventListener('submit', function(e) {
                e.preventDefault();
                ocultarMsgForm('editar-forma-pago-msg');
                var btn = formEditar.querySelector('button[type="submit"]');
                var txtOrig = btn ? btn.innerHTML : '';
                if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...'; }
                enviarFormAjax(formEditar, 'editar-forma-pago-msg', base + '/config/formasPagoSriUpdate')
                    .catch(function() { if (btn) { btn.disabled = false; btn.innerHTML = txtOrig; } });
            });
        }
        modalEditar.addEventListener('show.bs.modal', function() { ocultarMsgForm('editar-forma-pago-msg'); });
    }

    if (modalNuevo) {
        modalNuevo.addEventListener('show.bs.modal', function() { ocultarMsgForm('crear-forma-pago-msg'); });
        var formCrear = document.getElementById('form-crear-forma-pago');
        if (formCrear) {
            formCrear.addEventListener('submit', function(e) {
                e.preventDefault();
                ocultarMsgForm('crear-forma-pago-msg');
                var btn = formCrear.querySelector('button[type="submit"]');
                var txtOrig = btn ? btn.innerHTML : '';
                if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...'; }
                enviarFormAjax(formCrear, 'crear-forma-pago-msg', base + '/config/formasPagoSriStore')
                    .catch(function() { if (btn) { btn.disabled = false; btn.innerHTML = txtOrig; } });
            });
        }
    }

    // Búsqueda y orden en tiempo real: reemplazan solo la tabla vía AJAX, sin
    // recargar la página (el input nunca pierde el foco). Mismo patrón que
    // ASIENTOTIPO_cargarListado (public/js/modulos/asientos_tipo_modal.js).
    var timer = null;
    window.FORMASPAGO_currentSort = '<?= htmlspecialchars($ordenCol) ?>';
    window.FORMASPAGO_currentDir = '<?= htmlspecialchars($ordenDir) ?>';

    window.FORMASPAGO_cargarListado = function() {
        var inputB = document.getElementById('input-buscar-formaspago');
        var b = inputB ? inputB.value.trim() : '';
        var tbodyEl = document.getElementById('tbodyFormasPago');
        if (tbodyEl) tbodyEl.innerHTML = '<tr><td colspan="4" class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary"></span> Cargando...</td></tr>';

        fetch(base + '/config/formas-pago-sri-search?b=' + encodeURIComponent(b) + '&sort=' + window.FORMASPAGO_currentSort + '&dir=' + window.FORMASPAGO_currentDir, {
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

    var inputBuscar = document.getElementById('input-buscar-formaspago');
    if (inputBuscar) {
        inputBuscar.addEventListener('input', function() {
            clearTimeout(timer);
            timer = setTimeout(function() {
                FORMASPAGO_cargarListado();
            }, 400);
        });
    }

    if (window.CMG_initSort) {
        window.CMG_initSort('formas-pago-sri', function(col, dir) {
            window.FORMASPAGO_currentSort = col;
            window.FORMASPAGO_currentDir = dir;
            FORMASPAGO_cargarListado();
        }, { col: window.FORMASPAGO_currentSort, dir: window.FORMASPAGO_currentDir });
    }
})();
</script>
