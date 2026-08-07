<?php
/** @var string $titulo */
/** @var array $rows */
/** @var array $comprobantes */
/** @var string $ordenCol */
/** @var string $ordenDir */
/** @var string $buscar */
$base = BASE_URL;
$rows = $rows ?? [];
$comprobantes = $comprobantes ?? [];
$ordenCol = $ordenCol ?? 'codigo';
$ordenDir = $ordenDir ?? 'asc';
$buscar = $buscar ?? '';
$msg = $_SESSION['sustento_tributario_msg'] ?? null;
unset($_SESSION['sustento_tributario_msg']);
$rowsHtml = $rowsHtml ?? '';
?>
<style>
.sustento-row { cursor: pointer; }
.sustento-row:hover { background-color: rgba(0,0,0,.04); }
.sustento-scroll { max-height: calc(100dvh - 280px); overflow-y: auto; }
.sustento-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
.min-h-badge { min-height: 28px; }
.badge-comp-rm { cursor: pointer; }
</style>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-receipt-cutoff"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">Haga clic en una fila para editar. Código y nombre únicos. Tipo comprobante: varios códigos separados por coma.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoSustento"><i class="bi bi-plus-lg"></i> Crear nuevo</button>
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
    <input type="text" id="input-buscar-sustento" class="form-control" placeholder="Buscar código, nombre o tipo..." value="<?= htmlspecialchars($buscar) ?>" autocomplete="off">
</div>

<div class="card cmg-table-card">
    <div class="card-body p-0">
        <div class="sustento-scroll">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="sortable-header" data-sort="codigo" role="button">Código <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="nombre" role="button">Nombre <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="tipo_comprobante" role="button">Códigos de tipos de comprobantes autorizados <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" data-sort="status" role="button">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end" style="width: 80px;">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tbodySustento"><?= $rowsHtml ?></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Nuevo sustento -->
<div class="modal fade" id="modalNuevoSustento" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/sustentoTributarioStore" id="form-crear-sustento">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Nuevo sustento tributario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="crear-sustento-msg" class="d-none"></div>
                    <ul class="nav nav-tabs mb-3" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#pane-crear-datos" type="button" role="tab">Datos del sustento</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-crear-comprobantes" type="button" role="tab">Comprobantes autorizados</button>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="pane-crear-datos" role="tabpanel">
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
                                    <input type="text" id="new-nombre" name="nombre" class="form-control form-control-sm" required placeholder="Ej: Venta de bienes">
                                </div>
                                <input type="hidden" id="new-tipo-comprobante" name="tipo_comprobante" value="">
                            </div>
                        </div>
                        <div class="tab-pane fade" id="pane-crear-comprobantes" role="tabpanel">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Comprobantes asignados</label>
                                <div id="new-asignados" class="d-flex flex-wrap gap-1 mb-2 min-h-badge"></div>
                                <small class="text-muted" id="new-sin-asignados">Ninguno. Agregue desde la lista.</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Agregar comprobante</label>
                                <div class="input-group input-group-sm" style="max-width: 320px;">
                                    <select id="new-select-comprobante" class="form-select form-select-sm">
                                        <option value="">Seleccione...</option>
                                        <?php foreach ($comprobantes as $c): ?>
                                        <option value="<?= htmlspecialchars($c['codigo_comprobante'] ?? '') ?>" data-nombre="<?= htmlspecialchars($c['comprobante'] ?? '') ?>"><?= htmlspecialchars(($c['codigo_comprobante'] ?? '') . ' - ' . ($c['comprobante'] ?? '')) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="new-btn-agregar"><i class="bi bi-plus"></i> Agregar</button>
                                </div>
                            </div>
                            <?php if (empty($comprobantes)): ?>
                            <p class="text-muted mb-0">No hay comprobantes autorizados registrados.</p>
                            <?php endif; ?>
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

<!-- Modal Editar sustento -->
<div class="modal fade" id="modalEditarSustento" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/sustentoTributarioUpdate" id="form-editar-sustento">
                <input type="hidden" name="id" id="edit-id" value="">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Editar sustento tributario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="editar-sustento-msg" class="d-none"></div>
                    <ul class="nav nav-tabs mb-3" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#pane-editar-datos" type="button" role="tab">Datos del sustento</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-editar-comprobantes" type="button" role="tab">Comprobantes autorizados</button>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="pane-editar-datos" role="tabpanel">
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
                                    <input type="text" id="edit-nombre" name="nombre" class="form-control form-control-sm" required placeholder="Ej: Venta de bienes">
                                </div>
                                <input type="hidden" id="edit-tipo-comprobante" name="tipo_comprobante" value="">
                            </div>
                        </div>
                        <div class="tab-pane fade" id="pane-editar-comprobantes" role="tabpanel">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Comprobantes asignados</label>
                                <div id="edit-asignados" class="d-flex flex-wrap gap-1 mb-2 min-h-badge"></div>
                                <small class="text-muted" id="edit-sin-asignados">Ninguno. Agregue desde la lista.</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Agregar comprobante</label>
                                <div class="input-group input-group-sm" style="max-width: 320px;">
                                    <select id="edit-select-comprobante" class="form-select form-select-sm">
                                        <option value="">Seleccione...</option>
                                        <?php foreach ($comprobantes as $c): ?>
                                        <option value="<?= htmlspecialchars($c['codigo_comprobante'] ?? '') ?>" data-nombre="<?= htmlspecialchars($c['comprobante'] ?? '') ?>"><?= htmlspecialchars(($c['codigo_comprobante'] ?? '') . ' - ' . ($c['comprobante'] ?? '')) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="edit-btn-agregar"><i class="bi bi-plus"></i> Agregar</button>
                                </div>
                            </div>
                            <?php if (empty($comprobantes)): ?>
                            <p class="text-muted mb-0">No hay comprobantes autorizados registrados.</p>
                            <?php endif; ?>
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
    var modalEditar = document.getElementById('modalEditarSustento');
    var modalNuevo = document.getElementById('modalNuevoSustento');

    function renderAsignados(hiddenId, asignadosId, sinAsignadosId, codigos, selectId) {
        var hidden = document.getElementById(hiddenId);
        var cont = document.getElementById(asignadosId);
        var sin = document.getElementById(sinAsignadosId);
        if (!hidden || !cont) return;
        var arr = (codigos || []).filter(Boolean);
        hidden.value = arr.join(',');
        cont.innerHTML = '';
        var sel = selectId ? document.getElementById(selectId) : null;
        arr.forEach(function(cod) {
            var nom = cod;
            if (sel) {
                for (var i = 0; i < sel.options.length; i++) {
                    if (sel.options[i].value === cod) {
                        nom = sel.options[i].getAttribute('data-nombre') || cod;
                        break;
                    }
                }
            }
            var b = document.createElement('span');
            b.className = 'badge bg-primary badge-comp-rm';
            b.textContent = cod + ' - ' + nom + ' ×';
            b.dataset.codigo = cod;
            b.title = 'Quitar';
            cont.appendChild(b);
        });
        if (sin) sin.style.display = arr.length ? 'none' : '';
    }

    function initComprobantesUI(prefix) {
        var hidden = document.getElementById(prefix + '-tipo-comprobante');
        var asignados = document.getElementById(prefix + '-asignados');
        var sinAsignados = document.getElementById(prefix + '-sin-asignados');
        var select = document.getElementById(prefix + '-select-comprobante');
        var btnAgregar = document.getElementById(prefix + '-btn-agregar');
        if (!hidden || !asignados) return;

        function getCodigos() {
            var v = (hidden.value || '').trim();
            return v ? v.split(',').map(function(s) { return s.trim(); }).filter(Boolean) : [];
        }

        function addCodigo(cod, nom) {
            var arr = getCodigos();
            if (arr.indexOf(cod) !== -1) return;
            arr.push(cod);
            renderAsignados(prefix + '-tipo-comprobante', prefix + '-asignados', prefix + '-sin-asignados', arr, prefix + '-select-comprobante');
        }

        function removeCodigo(cod) {
            var arr = getCodigos().filter(function(c) { return c !== cod; });
            renderAsignados(prefix + '-tipo-comprobante', prefix + '-asignados', prefix + '-sin-asignados', arr, prefix + '-select-comprobante');
        }

        asignados.addEventListener('click', function(e) {
            var b = e.target.closest('.badge-comp-rm');
            if (b && b.dataset.codigo) removeCodigo(b.dataset.codigo);
        });

        if (btnAgregar && select) {
            btnAgregar.addEventListener('click', function() {
                var opt = select.options[select.selectedIndex];
                if (opt && opt.value) {
                    addCodigo(opt.value, opt.getAttribute('data-nombre') || opt.value);
                    select.selectedIndex = 0;
                }
            });
        }
    }

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
                setTimeout(function() { window.location.href = base + '/config/sustento-tributario'; }, 1500);
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
        var formEditar = modalEditar.querySelector('#form-editar-sustento');
        var tbodySustento = document.getElementById('tbodySustento');
        function abrirModalSustento(row) {
            ocultarMsgForm('editar-sustento-msg');
            formEditar.querySelector('#edit-id').value = row.dataset.id || '';
            formEditar.querySelector('#edit-codigo').value = row.dataset.codigo || '';
            formEditar.querySelector('#edit-nombre').value = row.dataset.nombre || '';
            formEditar.querySelector('#edit-status').value = row.dataset.status || '1';
            var tc = (row.dataset.tipoComprobante || '').trim();
            var codigos = tc ? tc.split(',').map(function(s) { return s.trim(); }).filter(Boolean) : [];
            formEditar.querySelector('#edit-tipo-comprobante').value = tc;
            renderAsignados('edit-tipo-comprobante', 'edit-asignados', 'edit-sin-asignados', codigos, 'edit-select-comprobante');
            new bootstrap.Modal(modalEditar).show();
        }
        // Delegación de eventos: las filas se reemplazan en cada búsqueda/orden
        // AJAX, por lo que el listener va en el tbody (contenedor fijo).
        if (tbodySustento) {
            tbodySustento.addEventListener('click', function(e) {
                if (e.target.closest('form, button')) return;
                var row = e.target.closest('.sustento-row');
                if (row) abrirModalSustento(row);
            });
            tbodySustento.addEventListener('keydown', function(e) {
                if ((e.key !== 'Enter' && e.key !== ' ') || e.target.closest('form, button')) return;
                var row = e.target.closest('.sustento-row');
                if (row) { e.preventDefault(); abrirModalSustento(row); }
            });
        }
        if (formEditar) {
            formEditar.addEventListener('submit', function(e) {
                e.preventDefault();
                ocultarMsgForm('editar-sustento-msg');
                var btn = formEditar.querySelector('button[type="submit"]');
                var txtOrig = btn ? btn.innerHTML : '';
                if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...'; }
                enviarFormAjax(formEditar, 'editar-sustento-msg', base + '/config/sustentoTributarioUpdate')
                    .catch(function() { if (btn) { btn.disabled = false; btn.innerHTML = txtOrig; } });
            });
        }
        modalEditar.addEventListener('show.bs.modal', function() { ocultarMsgForm('editar-sustento-msg'); });
    }

    if (modalNuevo) {
        modalNuevo.addEventListener('show.bs.modal', function() {
            ocultarMsgForm('crear-sustento-msg');
            renderAsignados('new-tipo-comprobante', 'new-asignados', 'new-sin-asignados', [], 'new-select-comprobante');
        });
        var formCrear = document.getElementById('form-crear-sustento');
        if (formCrear) {
            formCrear.addEventListener('submit', function(e) {
                e.preventDefault();
                ocultarMsgForm('crear-sustento-msg');
                var btn = formCrear.querySelector('button[type="submit"]');
                var txtOrig = btn ? btn.innerHTML : '';
                if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...'; }
                enviarFormAjax(formCrear, 'crear-sustento-msg', base + '/config/sustentoTributarioStore')
                    .catch(function() { if (btn) { btn.disabled = false; btn.innerHTML = txtOrig; } });
            });
        }
    }

    initComprobantesUI('new');
    initComprobantesUI('edit');

    // Búsqueda y orden en tiempo real: reemplazan solo la tabla vía AJAX, sin
    // recargar la página (el input nunca pierde el foco). Mismo patrón que
    // ASIENTOTIPO_cargarListado (public/js/modulos/asientos_tipo_modal.js).
    var timer = null;
    window.SUSTENTO_currentSort = '<?= htmlspecialchars($ordenCol) ?>';
    window.SUSTENTO_currentDir = '<?= htmlspecialchars($ordenDir) ?>';

    window.SUSTENTO_cargarListado = function() {
        var inputB = document.getElementById('input-buscar-sustento');
        var b = inputB ? inputB.value.trim() : '';
        var tbodyEl = document.getElementById('tbodySustento');
        if (tbodyEl) tbodyEl.innerHTML = '<tr><td colspan="5" class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary"></span> Cargando...</td></tr>';

        fetch(base + '/config/sustento-tributario-search?b=' + encodeURIComponent(b) + '&sort=' + window.SUSTENTO_currentSort + '&dir=' + window.SUSTENTO_currentDir, {
                credentials: 'same-origin'
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.ok && tbodyEl) tbodyEl.innerHTML = data.rows;
            })
            .catch(function() {
                if (tbodyEl) tbodyEl.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">Error al cargar.</td></tr>';
            });
    };

    var inputBuscar = document.getElementById('input-buscar-sustento');
    if (inputBuscar) {
        inputBuscar.addEventListener('input', function() {
            clearTimeout(timer);
            timer = setTimeout(function() {
                SUSTENTO_cargarListado();
            }, 400);
        });
    }

    if (window.CMG_initSort) {
        window.CMG_initSort('sustento-tributario', function(col, dir) {
            window.SUSTENTO_currentSort = col;
            window.SUSTENTO_currentDir = dir;
            SUSTENTO_cargarListado();
        }, { col: window.SUSTENTO_currentSort, dir: window.SUSTENTO_currentDir });
    }
})();
</script>
