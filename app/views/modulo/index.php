<?php
/** @var string $titulo */
/** @var string $tipo */
/** @var array $rows */
/** @var int $total */
/** @var int $page */
/** @var int $totalPages */
/** @var string $buscar */
/** @var array $modulos */
/** @var array $iconos */
$base = BASE_URL;
$msg = $_SESSION['modulo_msg'] ?? null;
unset($_SESSION['modulo_msg']);
?>
<style>
.cmg-modulo-icono-fila { display: flex; align-items: stretch; gap: 0.5rem; }
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
        <?php if ($tipo === 'iconos'): ?>
        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoIcono">
            <i class="bi bi-plus-lg"></i> Nuevo icono
        </button>
        <?php endif; ?>
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
        <form method="GET" action="<?= $base ?>/config/modulo" class="row g-2 align-items-center">
            <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">
            <div class="col-auto">
                <a href="<?= $base ?>/config/modulo?tipo=modulos<?= $buscar ? '&b=' . urlencode($buscar) : '' ?>" class="btn btn-sm <?= $tipo === 'modulos' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <i class="bi bi-folder"></i> Módulos
                </a>
                <a href="<?= $base ?>/config/modulo?tipo=submodulos<?= $buscar ? '&b=' . urlencode($buscar) : '' ?>" class="btn btn-sm <?= $tipo === 'submodulos' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <i class="bi bi-file-earmark"></i> Submódulos
                </a>
                <a href="<?= $base ?>/config/modulo?tipo=iconos<?= $buscar ? '&b=' . urlencode($buscar) : '' ?>" class="btn btn-sm <?= $tipo === 'iconos' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <i class="bi bi-emoji-smile"></i> Iconos
                </a>
            </div>
            <div class="col-auto flex-grow-1">
                <div class="input-group input-group-sm">
                    <input type="text" name="b" class="form-control" placeholder="Buscar..." value="<?= htmlspecialchars($buscar) ?>" style="max-width:200px">
                    <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card cmg-table-card">
    <div class="card-body">
        <?php if ($tipo === 'iconos'): ?>
        <div class="table-responsive">
            <table class="table table-hover table-sm">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th>
                        <th>Vista previa</th>
                        <th class="text-end" style="width:80px">Opciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($r['nombre_icono'] ?? '') ?></code></td>
                        <td><i class="<?= htmlspecialchars(iconoClase($r['nombre_icono'] ?? null)) ?>"></i></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary btn-editar-icono cmg-btn-table"
                                data-id="<?= (int)($r['id'] ?? 0) ?>"
                                data-nombre="<?= htmlspecialchars($r['nombre_icono'] ?? '') ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php elseif ($tipo === 'modulos'): ?>
        <div class="table-responsive">
            <table class="table table-hover table-sm">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th>
                        <th>Icono</th>
                        <th class="text-end" style="width:100px">Opciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['nombre_modulo'] ?? '') ?></td>
                        <td><i class="<?= htmlspecialchars(iconoClase($r['nombre_icono'] ?? null)) ?>"></i> <?= htmlspecialchars($r['nombre_icono'] ?? '') ?></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary cmg-btn-table" data-bs-toggle="modal" data-bs-target="#modalEditarModulo"
                                data-id="<?= (int)($r['id_modulo'] ?? 0) ?>"
                                data-nombre="<?= htmlspecialchars($r['nombre_modulo'] ?? '') ?>"
                                data-icono="<?= (int)($r['id_icono'] ?? 0) ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" action="<?= $base ?>/config/moduloDeleteModulo" class="d-inline" onsubmit="return confirm('¿Eliminar este módulo y todos sus submódulos?');">
                                <input type="hidden" name="id" value="<?= (int)($r['id_modulo'] ?? 0) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger cmg-btn-table" title="Eliminar"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-sm">
                <thead class="table-light">
                    <tr>
                        <th>Módulo</th>
                        <th>Submódulo</th>
                        <th>Icono</th>
                        <th>Ruta</th>
                        <th>Estado</th>
                        <th class="text-end" style="width:120px">Opciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <?php $activo = (int)($r['status'] ?? 1) === 1; ?>
                    <tr class="<?= $activo ? '' : 'table-secondary' ?>">
                        <td><?= htmlspecialchars($r['nombre_modulo'] ?? '') ?></td>
                        <td><?= htmlspecialchars($r['nombre_submodulo'] ?? '') ?></td>
                        <td><i class="<?= htmlspecialchars(iconoClase($r['nombre_icono'] ?? null)) ?>"></i></td>
                        <td><code class="small"><?= htmlspecialchars($r['ruta'] ?? '') ?></code></td>
                        <td>
                            <form method="POST" action="<?= $base ?>/config/moduloToggleSubmoduloStatus" class="d-inline">
                                <input type="hidden" name="id" value="<?= (int)($r['id_submodulo'] ?? 0) ?>">
                                <button type="submit" class="btn btn-sm cmg-btn-table <?= $activo ? 'btn-success' : 'btn-outline-secondary' ?>" title="<?= $activo ? 'Activo - clic para desactivar' : 'Inactivo - clic para activar' ?>">
                                    <?= $activo ? 'Activo' : 'Inactivo' ?>
                                </button>
                            </form>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary btn-editar-submodulo cmg-btn-table"
                                data-id="<?= (int)($r['id_submodulo'] ?? 0) ?>"
                                data-modulo="<?= (int)($r['id_modulo'] ?? 0) ?>"
                                data-nombre="<?= htmlspecialchars($r['nombre_submodulo'] ?? '') ?>"
                                data-ruta="<?= htmlspecialchars($r['ruta'] ?? '') ?>"
                                data-icono="<?= (int)($r['id_icono'] ?? 0) ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" action="<?= $base ?>/config/moduloDeleteSubmodulo" class="d-inline" onsubmit="return confirm('¿Eliminar este submódulo?');">
                                <input type="hidden" name="id" value="<?= (int)($r['id_submodulo'] ?? 0) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger cmg-btn-table" title="Eliminar"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if (empty($rows)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-inbox" style="font-size:2rem"></i>
            <p class="mb-0 mt-2">No hay registros</p>
        </div>
        <?php endif; ?>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="card-footer py-2">
        <nav>
            <ul class="pagination pagination-sm mb-0 justify-content-center">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= $base ?>/config/modulo?tipo=<?= urlencode($tipo) ?>&page=<?= $i ?><?= $buscar ? '&b=' . urlencode($buscar) : '' ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

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
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
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

    document.getElementById('modalEditarModulo')?.addEventListener('show.bs.modal', function(e) {
        var btn = e.relatedTarget;
        if (btn) {
            document.getElementById('mod_id_modulo').value = btn.dataset.id || '';
            document.getElementById('mod_nombre_modulo').value = btn.dataset.nombre || '';
            document.getElementById('mod_id_icono').value = btn.dataset.icono || '';
        }
    });
    document.getElementById('modalEditarModulo')?.addEventListener('shown.bs.modal', function() {
        cmgIconoPreviewActualizar(document.getElementById('mod_id_icono'));
    });

    document.querySelectorAll('.btn-editar-submodulo').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('mod_id_submodulo').value = this.dataset.id || '';
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

    document.querySelectorAll('.btn-editar-icono').forEach(function(btn) {
        btn.addEventListener('click', function() {
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
