<?php
/** @var string $titulo */
/** @var string $tipo */
/** @var array $rowsModulos */
/** @var array $rowsSubmodulos */
/** @var array $rowsIconos */
/** @var array $modulos */
/** @var array $iconos */
$base = BASE_URL;
$msg = $_SESSION['modulo_msg'] ?? null;
unset($_SESSION['modulo_msg']);
?>
<?php /* Hay filtros/pestañas encima de la tabla: se desactiva el app-shell para que la página no quede bloqueada. */ ?>
<script>document.body.classList.add('cmg-no-app-shell');</script>
<style>
.cmg-modulo-icono-fila { display: flex; align-items: stretch; gap: 0.5rem; }
.cmg-table-card .table-responsive thead th { position: sticky; top: 0; z-index: 2; background: #f8f9fa; }
.cmg-modulo-icono-preview {
    flex: 0 0 2.25rem;
    width: 2.25rem;
    min-height: 2.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: var(--bs-secondary-color, #6c757d);
    border: 1px solid var(--bs-border-color, #dee2e6);
    border-radius: 0.25rem;
    background: var(--bs-body-bg, #fff);
}
.cmg-modulo-icono-fila .form-select { flex: 1 1 auto; min-width: 0; }
</style>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-collection"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">Crear y editar módulos y submódulos del menú.</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoModulo">
            <i class="bi bi-plus-lg"></i> Nuevo módulo
        </button>
        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoSubmodulo">
            <i class="bi bi-plus-lg"></i> Nuevo submódulo
        </button>
        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoIcono">
            <i class="bi bi-plus-lg"></i> Nuevo icono
        </button>
    </div>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= htmlspecialchars($msg[0]) ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($msg[1]) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-center">
            <div class="col-auto">
                <button type="button" class="btn btn-sm cmg-tab-btn <?= $tipo === 'modulos' ? 'btn-primary' : 'btn-outline-secondary' ?>" data-tab="modulos">
                    <i class="bi bi-folder"></i> Módulos
                </button>
                <button type="button" class="btn btn-sm cmg-tab-btn <?= $tipo === 'submodulos' ? 'btn-primary' : 'btn-outline-secondary' ?>" data-tab="submodulos">
                    <i class="bi bi-file-earmark"></i> Submódulos
                </button>
                <button type="button" class="btn btn-sm cmg-tab-btn <?= $tipo === 'iconos' ? 'btn-primary' : 'btn-outline-secondary' ?>" data-tab="iconos">
                    <i class="bi bi-emoji-smile"></i> Iconos
                </button>
            </div>
            <div class="col-auto flex-grow-1">
                <div class="input-group input-group-sm" style="max-width:260px">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" id="inputBuscarModulo" class="form-control" placeholder="Buscar..." autocomplete="off">
                    <button type="button" id="btnLimpiarBuscarModulo" class="btn btn-outline-secondary" title="Limpiar búsqueda"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card cmg-table-card">
    <div class="card-body p-0">
        <!-- Panel: Módulos -->
        <div class="cmg-tabpanel" data-tabpanel="modulos" style="<?= $tipo === 'modulos' ? '' : 'display:none' ?>">
            <div class="table-responsive" style="max-height:calc(100dvh - 290px);overflow-y:auto;">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th>
                            <th>Icono</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rowsModulos as $r): ?>
                        <tr class="btn-editar-modulo" style="cursor:pointer"
                            data-id="<?= (int)($r['id_modulo'] ?? 0) ?>"
                            data-nombre="<?= htmlspecialchars($r['nombre_modulo'] ?? '') ?>"
                            data-icono="<?= (int)($r['id_icono'] ?? 0) ?>">
                            <td><?= htmlspecialchars($r['nombre_modulo'] ?? '') ?></td>
                            <td><i class="<?= htmlspecialchars(iconoClase($r['nombre_icono'] ?? null)) ?>"></i> <?= htmlspecialchars($r['nombre_icono'] ?? '') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Panel: Submódulos -->
        <div class="cmg-tabpanel" data-tabpanel="submodulos" style="<?= $tipo === 'submodulos' ? '' : 'display:none' ?>">
            <div class="table-responsive" style="max-height:calc(100dvh - 290px);overflow-y:auto;">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Módulo</th>
                            <th>Submódulo</th>
                            <th>Icono</th>
                            <th>Ruta</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rowsSubmodulos as $r): ?>
                        <?php $activo = (int)($r['status'] ?? 1) === 1; ?>
                        <tr class="btn-editar-submodulo <?= $activo ? '' : 'table-secondary' ?>" style="cursor:pointer"
                            data-id="<?= (int)($r['id_submodulo'] ?? 0) ?>"
                            data-modulo="<?= (int)($r['id_modulo'] ?? 0) ?>"
                            data-nombre="<?= htmlspecialchars($r['nombre_submodulo'] ?? '') ?>"
                            data-ruta="<?= htmlspecialchars($r['ruta'] ?? '') ?>"
                            data-icono="<?= (int)($r['id_icono'] ?? 0) ?>">
                            <td><?= htmlspecialchars($r['nombre_modulo'] ?? '') ?></td>
                            <td><?= htmlspecialchars($r['nombre_submodulo'] ?? '') ?></td>
                            <td><i class="<?= htmlspecialchars(iconoClase($r['nombre_icono'] ?? null)) ?>"></i></td>
                            <td><code class="small"><?= htmlspecialchars($r['ruta'] ?? '') ?></code></td>
                            <td>
                                <form method="POST" action="<?= $base ?>/config/moduloToggleSubmoduloStatus" class="d-inline cmg-no-row-click">
                                    <input type="hidden" name="id" value="<?= (int)($r['id_submodulo'] ?? 0) ?>">
                                    <button type="submit" class="btn btn-sm cmg-btn-table <?= $activo ? 'btn-success' : 'btn-outline-secondary' ?>" title="<?= $activo ? 'Activo - clic para desactivar' : 'Inactivo - clic para activar' ?>">
                                        <?= $activo ? 'Activo' : 'Inactivo' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Panel: Iconos -->
        <div class="cmg-tabpanel" data-tabpanel="iconos" style="<?= $tipo === 'iconos' ? '' : 'display:none' ?>">
            <div class="table-responsive" style="max-height:calc(100dvh - 290px);overflow-y:auto;">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th>
                            <th>Vista previa</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rowsIconos as $r): ?>
                        <tr class="btn-editar-icono" style="cursor:pointer"
                            data-id="<?= (int)($r['id'] ?? 0) ?>"
                            data-nombre="<?= htmlspecialchars($r['nombre_icono'] ?? '') ?>">
                            <td><code><?= htmlspecialchars($r['nombre_icono'] ?? '') ?></code></td>
                            <td><i class="<?= htmlspecialchars(iconoClase($r['nombre_icono'] ?? null)) ?>"></i></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- /.card-body -->
    <div id="cmgModuloSinResultados" class="text-center py-5 text-muted" style="display:none">
        <i class="bi bi-search" style="font-size:2rem"></i>
        <p class="mb-0 mt-2">No hay coincidencias</p>
    </div>
    <div class="card-footer py-2 small text-muted">
        <span id="cmgModuloContador">0</span> registro(s)
    </div>
</div>
<input type="hidden" id="cmgModuloTabActiva" value="<?= htmlspecialchars($tipo) ?>">

<!-- Modal Nuevo módulo -->
<div class="modal fade" id="modalNuevoModulo" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/moduloStoreModulo">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Nuevo módulo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="nombre_modulo" class="form-control" required maxlength="100" placeholder="Ej: Ventas">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Icono <span class="text-danger">*</span></label>
                        <div class="cmg-modulo-icono-fila">
                            <span class="cmg-modulo-icono-preview" id="preview_icono_nuevo_modulo" aria-hidden="true"></span>
                            <select name="id_icono" id="sel_icono_nuevo_modulo" class="form-select cmg-select-icono-preview" data-preview-target="preview_icono_nuevo_modulo" required>
                            <option value="" data-icon-class="">Seleccione...</option>
                            <?php foreach ($iconos as $ico): ?>
                            <?php $nomIco = $ico['nombre_icono'] ?? ''; ?>
                            <option value="<?= (int)($ico['id'] ?? 0) ?>" data-icon-class="<?= htmlspecialchars(iconoClase($nomIco)) ?>"><?= htmlspecialchars($nomIco) ?></option>
                            <?php endforeach; ?>
                        </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar módulo -->
<div class="modal fade" id="modalEditarModulo" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/moduloUpdateModulo">
                <input type="hidden" name="mod_id_modulo" id="mod_id_modulo">
                <input type="hidden" name="id" id="del_id_modulo">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Editar módulo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="mod_nombre_modulo" id="mod_nombre_modulo" class="form-control" required maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Icono <span class="text-danger">*</span></label>
                        <div class="cmg-modulo-icono-fila">
                            <span class="cmg-modulo-icono-preview" id="preview_icono_edit_modulo" aria-hidden="true"></span>
                            <select name="mod_id_icono" id="mod_id_icono" class="form-select cmg-select-icono-preview" data-preview-target="preview_icono_edit_modulo" required>
                            <?php foreach ($iconos as $ico): ?>
                            <?php $nomIco = $ico['nombre_icono'] ?? ''; ?>
                            <option value="<?= (int)($ico['id'] ?? 0) ?>" data-icon-class="<?= htmlspecialchars(iconoClase($nomIco)) ?>"><?= htmlspecialchars($nomIco) ?></option>
                            <?php endforeach; ?>
                        </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-outline-danger me-auto" formaction="<?= $base ?>/config/moduloDeleteModulo" formnovalidate onclick="return confirm('¿Eliminar este módulo y todos sus submódulos?');"><i class="bi bi-trash"></i> Eliminar</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Nuevo submódulo -->
<div class="modal fade" id="modalNuevoSubmodulo" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/moduloStoreSubmodulo">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Nuevo submódulo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Módulo <span class="text-danger">*</span></label>
                        <select name="id_modulo" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($modulos as $m): ?>
                            <option value="<?= (int)($m['id_modulo'] ?? 0) ?>"><?= htmlspecialchars($m['nombre_modulo'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="nombre_submodulo" class="form-control" required maxlength="100" placeholder="Ej: Clientes">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ruta <span class="text-danger">*</span></label>
                        <input type="text" name="ruta" class="form-control" required maxlength="200" placeholder="/sistema/modulos/clientes.php" value="/sistema/modulos/">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Icono <span class="text-danger">*</span></label>
                        <div class="cmg-modulo-icono-fila">
                            <span class="cmg-modulo-icono-preview" id="preview_icono_nuevo_submodulo" aria-hidden="true"></span>
                            <select name="id_icono" id="sel_icono_nuevo_submodulo" class="form-select cmg-select-icono-preview" data-preview-target="preview_icono_nuevo_submodulo" required>
                            <option value="" data-icon-class="">Seleccione...</option>
                            <?php foreach ($iconos as $ico): ?>
                            <?php $nomIco = $ico['nombre_icono'] ?? ''; ?>
                            <option value="<?= (int)($ico['id'] ?? 0) ?>" data-icon-class="<?= htmlspecialchars(iconoClase($nomIco)) ?>"><?= htmlspecialchars($nomIco) ?></option>
                            <?php endforeach; ?>
                        </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar submódulo -->
<div class="modal fade" id="modalEditarSubmodulo" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/moduloUpdateSubmodulo">
                <input type="hidden" name="mod_id_submodulo" id="mod_id_submodulo">
                <input type="hidden" name="id" id="del_id_submodulo">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Editar submódulo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Módulo <span class="text-danger">*</span></label>
                        <select name="mod_id_modulo_sub" id="mod_id_modulo_sub" class="form-select" required>
                            <?php foreach ($modulos as $m): ?>
                            <option value="<?= (int)($m['id_modulo'] ?? 0) ?>"><?= htmlspecialchars($m['nombre_modulo'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="mod_nombre_submodulo" id="mod_nombre_submodulo" class="form-control" required maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ruta <span class="text-danger">*</span></label>
                        <input type="text" name="mod_ruta" id="mod_ruta" class="form-control" required maxlength="200">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Icono <span class="text-danger">*</span></label>
                        <div class="cmg-modulo-icono-fila">
                            <span class="cmg-modulo-icono-preview" id="preview_icono_edit_submodulo" aria-hidden="true"></span>
                            <select name="mod_id_icono_sub" id="mod_id_icono_sub" class="form-select cmg-select-icono-preview" data-preview-target="preview_icono_edit_submodulo" required>
                            <?php foreach ($iconos as $ico): ?>
                            <?php $nomIco = $ico['nombre_icono'] ?? ''; ?>
                            <option value="<?= (int)($ico['id'] ?? 0) ?>" data-icon-class="<?= htmlspecialchars(iconoClase($nomIco)) ?>"><?= htmlspecialchars($nomIco) ?></option>
                            <?php endforeach; ?>
                        </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-outline-danger me-auto" formaction="<?= $base ?>/config/moduloDeleteSubmodulo" formnovalidate onclick="return confirm('¿Eliminar este submódulo?');"><i class="bi bi-trash"></i> Eliminar</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    // --- Pestañas + búsqueda client-side (URL siempre limpia, sin recarga) ---
    var inputBuscar = document.getElementById('inputBuscarModulo');
    var btnLimpiar  = document.getElementById('btnLimpiarBuscarModulo');
    var msgSinResultados = document.getElementById('cmgModuloSinResultados');
    var contador    = document.getElementById('cmgModuloContador');
    var tabActivaEl = document.getElementById('cmgModuloTabActiva');
    var tabActiva   = tabActivaEl ? tabActivaEl.value : 'modulos';

    function cmgPanelActivo() {
        return document.querySelector('.cmg-tabpanel[data-tabpanel="' + tabActiva + '"]');
    }

    function cmgFiltrarTabla() {
        var panel = cmgPanelActivo();
        if (!panel) return;
        var q = (inputBuscar ? inputBuscar.value : '').trim().toLowerCase();
        var visibles = 0;
        panel.querySelectorAll('tbody tr').forEach(function(tr) {
            var match = q === '' || tr.textContent.toLowerCase().indexOf(q) !== -1;
            tr.style.display = match ? '' : 'none';
            if (match) visibles++;
        });
        if (msgSinResultados) msgSinResultados.style.display = (visibles === 0) ? '' : 'none';
        if (contador) contador.textContent = visibles;
    }

    function cmgCambiarTab(tab) {
        tabActiva = tab;
        document.querySelectorAll('.cmg-tabpanel').forEach(function(p) {
            p.style.display = (p.getAttribute('data-tabpanel') === tab) ? '' : 'none';
        });
        document.querySelectorAll('.cmg-tab-btn').forEach(function(b) {
            var on = b.getAttribute('data-tab') === tab;
            b.classList.toggle('btn-primary', on);
            b.classList.toggle('btn-outline-secondary', !on);
        });
        cmgFiltrarTabla();
    }

    document.querySelectorAll('.cmg-tab-btn').forEach(function(btn) {
        btn.addEventListener('click', function() { cmgCambiarTab(this.getAttribute('data-tab')); });
    });

    if (inputBuscar) {
        inputBuscar.addEventListener('input', cmgFiltrarTabla);
        inputBuscar.focus();
    }
    if (btnLimpiar) {
        btnLimpiar.addEventListener('click', function() {
            if (inputBuscar) { inputBuscar.value = ''; cmgFiltrarTabla(); inputBuscar.focus(); }
        });
    }

    // Contador inicial
    cmgFiltrarTabla();

    function cmgIconoPreviewActualizar(select) {
        if (!select) return;
        var tid = select.getAttribute('data-preview-target');
        if (!tid) return;
        var el = document.getElementById(tid);
        if (!el) return;
        el.innerHTML = '';
        var opt = select.options[select.selectedIndex];
        var cls = opt ? (opt.getAttribute('data-icon-class') || '').trim() : '';
        if (!cls) return;
        var i = document.createElement('i');
        i.className = cls;
        i.setAttribute('aria-hidden', 'true');
        el.appendChild(i);
    }

    document.querySelectorAll('select.cmg-select-icono-preview').forEach(function(sel) {
        sel.addEventListener('change', function() { cmgIconoPreviewActualizar(sel); });
    });

    document.getElementById('modalNuevoModulo')?.addEventListener('shown.bs.modal', function() {
        cmgIconoPreviewActualizar(document.getElementById('sel_icono_nuevo_modulo'));
    });
    document.getElementById('modalNuevoSubmodulo')?.addEventListener('shown.bs.modal', function() {
        cmgIconoPreviewActualizar(document.getElementById('sel_icono_nuevo_submodulo'));
    });

    // Clic en la fila = abrir modal de edición (con Eliminar dentro).
    // Se ignora si el clic viene de un control interactivo (botón de Estado).
    function cmgClicFilaValido(e) {
        return !e.target.closest('.cmg-no-row-click, a, button, input, select');
    }

    document.querySelectorAll('.btn-editar-modulo').forEach(function(fila) {
        fila.addEventListener('click', function(e) {
            if (!cmgClicFilaValido(e)) return;
            document.getElementById('mod_id_modulo').value = this.dataset.id || '';
            document.getElementById('del_id_modulo').value = this.dataset.id || '';
            document.getElementById('mod_nombre_modulo').value = this.dataset.nombre || '';
            document.getElementById('mod_id_icono').value = this.dataset.icono || '';
            new bootstrap.Modal(document.getElementById('modalEditarModulo')).show();
        });
    });
    document.getElementById('modalEditarModulo')?.addEventListener('shown.bs.modal', function() {
        cmgIconoPreviewActualizar(document.getElementById('mod_id_icono'));
    });

    document.querySelectorAll('.btn-editar-submodulo').forEach(function(fila) {
        fila.addEventListener('click', function(e) {
            if (!cmgClicFilaValido(e)) return;
            document.getElementById('mod_id_submodulo').value = this.dataset.id || '';
            document.getElementById('del_id_submodulo').value = this.dataset.id || '';
            document.getElementById('mod_id_modulo_sub').value = this.dataset.modulo || '';
            document.getElementById('mod_nombre_submodulo').value = this.dataset.nombre || '';
            document.getElementById('mod_ruta').value = this.dataset.ruta || '';
            document.getElementById('mod_id_icono_sub').value = this.dataset.icono || '';
            new bootstrap.Modal(document.getElementById('modalEditarSubmodulo')).show();
        });
    });
    document.getElementById('modalEditarSubmodulo')?.addEventListener('shown.bs.modal', function() {
        cmgIconoPreviewActualizar(document.getElementById('mod_id_icono_sub'));
    });

    document.querySelectorAll('.btn-editar-icono').forEach(function(fila) {
        fila.addEventListener('click', function(e) {
            if (!cmgClicFilaValido(e)) return;
            document.getElementById('edit_icono_id').value = this.dataset.id || '';
            document.getElementById('mod_nombre_icono').value = this.dataset.nombre || '';
            new bootstrap.Modal(document.getElementById('modalEditarIcono')).show();
        });
    });
})();
</script>

<!-- Modal Nuevo icono -->
<div class="modal fade" id="modalNuevoIcono" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/moduloStoreIcono">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Nuevo icono</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre (clase CSS) <span class="text-danger">*</span></label>
                        <input type="text" name="nombre_icono" class="form-control" required maxlength="100" placeholder="Ej: fas fa-user o bi-person">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar icono -->
<div class="modal fade" id="modalEditarIcono" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/moduloUpdateIcono">
                <input type="hidden" name="mod_id_icono" id="edit_icono_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Editar icono</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre (clase CSS) <span class="text-danger">*</span></label>
                        <input type="text" name="mod_nombre_icono" id="mod_nombre_icono" class="form-control" required maxlength="100">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
