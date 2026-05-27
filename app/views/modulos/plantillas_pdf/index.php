<?php
/** @var array $rows */
/** @var int $total */
/** @var int $page */
/** @var int $totalPages */
/** @var int $from */
/** @var int $to */
/** @var string $buscar */
/** @var string $tipoFiltro */
/** @var array $tiposDoc */
/** @var array $permisos */
$base = BASE_URL;
$ruta = $base . '/modulos/plantillas-pdf';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-file-earmark-pdf text-danger"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">Diseña los PDF de facturas y documentos electrónicos.</p>
    </div>
    <?php if ($permisos['crear'] ?? false): ?>
    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalNueva" style="padding:4px 12px;font-size:0.8rem">
        <i class="bi bi-plus-lg"></i> Nueva plantilla
    </button>
    <?php endif; ?>
</div>

<!-- Buscador y filtros -->
<div class="d-flex gap-2 flex-wrap mb-3 align-items-center">
    <div class="input-group input-group-sm" style="max-width:280px">
        <span class="input-group-text bg-white border-end-0 py-0" style="height:28px"><i class="bi bi-search text-muted"></i></span>
        <input type="search" id="inp-buscar" class="form-control border-start-0" placeholder="Buscar plantilla…"
               value="<?= htmlspecialchars($buscar) ?>" autocomplete="off" style="height:28px;font-size:0.8rem">
    </div>
    <select id="sel-tipo" class="form-select form-select-sm" style="max-width:200px;height:28px;font-size:0.8rem">
        <option value="">Todos los tipos</option>
        <?php foreach ($tiposDoc as $k => $v): ?>
        <option value="<?= htmlspecialchars($k) ?>" <?= $tipoFiltro === $k ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option>
        <?php endforeach; ?>
    </select>
    <small class="text-muted ms-auto"><?= $from ?>–<?= $to ?> de <?= $total ?></small>
</div>

<!-- Tabla -->
<div class="table-responsive">
    <table class="table table-hover table-sm align-middle mb-0" style="font-size:0.82rem">
        <thead class="table-light">
            <tr>
                <th style="width:35%">Nombre</th>
                <th style="width:20%">Tipo de Documento</th>
                <th style="width:25%">Descripción</th>
                <th class="text-center" style="width:10%">Estado</th>
                <th class="text-center" style="width:10%">Acciones</th>
            </tr>
        </thead>
        <tbody id="tbody-plantillas">
        <?php if (empty($rows)): ?>
            <tr><td colspan="5" class="text-center py-5 text-muted">
                <i class="bi bi-file-earmark-pdf fs-3 d-block mb-2"></i>
                No hay plantillas. Crea la primera con el botón "Nueva plantilla".
            </td></tr>
        <?php else: ?>
            <?php foreach ($rows as $r): ?>
            <?php
                $activa = (bool)($r['es_activa'] ?? false);
                $rowJson = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
            ?>
            <tr data-row="<?= $rowJson ?>">
                <td>
                    <span class="fw-medium"><?= htmlspecialchars($r['nombre']) ?></span>
                    <?php if ($activa): ?>
                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 ms-1" style="font-size:0.7rem">Activa</span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($tiposDoc[$r['tipo_documento']] ?? $r['tipo_documento']) ?></td>
                <td class="text-muted text-truncate" style="max-width:200px"><?= htmlspecialchars($r['descripcion'] ?? '') ?></td>
                <td class="text-center">
                    <?php if ($activa): ?>
                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Activa</span>
                    <?php else: ?>
                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Borrador</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <div class="d-flex gap-1 justify-content-center">
                        <?php if ($permisos['actualizar'] ?? false): ?>
                        <a href="<?= $ruta ?>?action=disenador&id=<?= (int)$r['id'] ?>"
                           class="btn btn-sm btn-outline-primary" style="padding:2px 6px;font-size:0.75rem" title="Diseñar">
                            <i class="bi bi-pencil-square"></i>
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-editar" style="padding:2px 6px;font-size:0.75rem" title="Editar datos">
                            <i class="bi bi-sliders"></i>
                        </button>
                        <?php if (!$activa): ?>
                        <button type="button" class="btn btn-sm btn-outline-success btn-activar" style="padding:2px 6px;font-size:0.75rem" title="Activar" data-id="<?= (int)$r['id'] ?>">
                            <i class="bi bi-toggle-off"></i>
                        </button>
                        <?php else: ?>
                        <button type="button" class="btn btn-sm btn-outline-warning btn-desactivar" style="padding:2px 6px;font-size:0.75rem" title="Desactivar" data-id="<?= (int)$r['id'] ?>">
                            <i class="bi bi-toggle-on"></i>
                        </button>
                        <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($permisos['eliminar'] ?? false): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger btn-eliminar" style="padding:2px 6px;font-size:0.75rem" title="Eliminar" data-id="<?= (int)$r['id'] ?>">
                            <i class="bi bi-trash"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Paginación -->
<?php if ($totalPages > 1): ?>
<div class="d-flex justify-content-center gap-2 mt-3">
    <?php if ($page > 1): ?>
    <a href="<?= $ruta ?>?b=<?= urlencode($buscar) ?>&tipo=<?= urlencode($tipoFiltro) ?>&page=<?= $page - 1 ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-chevron-left"></i></a>
    <?php endif; ?>
    <span class="btn btn-sm disabled">Página <?= $page ?> de <?= $totalPages ?></span>
    <?php if ($page < $totalPages): ?>
    <a href="<?= $ruta ?>?b=<?= urlencode($buscar) ?>&tipo=<?= urlencode($tipoFiltro) ?>&page=<?= $page + 1 ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-chevron-right"></i></a>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Modal Nueva Plantilla -->
<?php if ($permisos['crear'] ?? false): ?>
<div class="modal fade" id="modalNueva" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle text-danger"></i> Nueva plantilla</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label">Tipo de documento <span class="text-danger">*</span></label>
                    <select id="new-tipo" class="form-select form-select-sm" style="height:28px;font-size:0.85rem;padding:2px 8px">
                        <?php foreach ($tiposDoc as $k => $v): ?>
                        <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label">Nombre <span class="text-danger">*</span></label>
                    <input type="text" id="new-nombre" class="form-control form-control-sm" placeholder="Ej: Factura estándar empresa" style="height:28px;font-size:0.85rem">
                </div>
                <div class="mb-2">
                    <label class="form-label">Descripción</label>
                    <input type="text" id="new-descripcion" class="form-control form-control-sm" placeholder="Descripción opcional" style="height:28px;font-size:0.85rem">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger btn-sm" id="btn-crear-plantilla"><i class="bi bi-check-lg"></i> Crear</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal Editar Plantilla -->
<?php if ($permisos['actualizar'] ?? false): ?>
<div class="modal fade" id="modalEditar" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-sliders"></i> Editar plantilla</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="edit-id">
                <div class="mb-2">
                    <label class="form-label">Tipo de documento <span class="text-danger">*</span></label>
                    <select id="edit-tipo" class="form-select form-select-sm" style="height:28px;font-size:0.85rem;padding:2px 8px">
                        <?php foreach ($tiposDoc as $k => $v): ?>
                        <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label">Nombre <span class="text-danger">*</span></label>
                    <input type="text" id="edit-nombre" class="form-control form-control-sm" style="height:28px;font-size:0.85rem">
                </div>
                <div class="mb-2">
                    <label class="form-label">Descripción</label>
                    <input type="text" id="edit-descripcion" class="form-control form-control-sm" style="height:28px;font-size:0.85rem">
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <?php if ($permisos['eliminar'] ?? false): ?>
                <button type="button" class="btn btn-outline-danger btn-sm" id="btn-eliminar-desde-modal"><i class="bi bi-trash"></i> Eliminar</button>
                <?php else: ?>
                <div></div>
                <?php endif; ?>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-sm" id="btn-guardar-edicion"><i class="bi bi-check-lg"></i> Guardar</button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
(function () {
    const RUTA = '<?= $ruta ?>';

    async function ajaxPost(accion, body) {
        const fd = new FormData();
        fd.append('action', accion);
        for (const [k, v] of Object.entries(body)) fd.append(k, v);
        const res = await fetch(RUTA, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        return res.json();
    }

    function toast(msg, tipo = 'success') {
        const el = document.createElement('div');
        el.className = `alert alert-${tipo} alert-dismissible fade show position-fixed bottom-0 end-0 m-3`;
        el.style.zIndex = 9999;
        el.innerHTML = msg + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 3500);
    }

    // Crear plantilla
    document.getElementById('btn-crear-plantilla')?.addEventListener('click', async () => {
        const tipo  = document.getElementById('new-tipo').value;
        const nom   = document.getElementById('new-nombre').value.trim();
        const desc  = document.getElementById('new-descripcion').value.trim();
        if (!nom) { toast('El nombre es obligatorio.', 'danger'); return; }

        const r = await ajaxPost('store', { tipo_documento: tipo, nombre: nom, descripcion: desc });
        if (r.ok) {
            toast(r.mensaje);
            setTimeout(() => location.reload(), 800);
        } else {
            toast(r.mensaje, 'danger');
        }
    });

    // Abrir modal editar
    document.querySelectorAll('.btn-editar').forEach(btn => {
        btn.addEventListener('click', () => {
            const row = JSON.parse(btn.closest('tr').dataset.row);
            document.getElementById('edit-id').value          = row.id;
            document.getElementById('edit-tipo').value        = row.tipo_documento;
            document.getElementById('edit-nombre').value      = row.nombre;
            document.getElementById('edit-descripcion').value = row.descripcion || '';
            const btnElModal = document.getElementById('btn-eliminar-desde-modal');
            if (btnElModal) btnElModal.dataset.id = row.id;
            new bootstrap.Modal(document.getElementById('modalEditar')).show();
        });
    });

    // Guardar edición
    document.getElementById('btn-guardar-edicion')?.addEventListener('click', async () => {
        const id   = document.getElementById('edit-id').value;
        const tipo = document.getElementById('edit-tipo').value;
        const nom  = document.getElementById('edit-nombre').value.trim();
        const desc = document.getElementById('edit-descripcion').value.trim();
        if (!nom) { toast('El nombre es obligatorio.', 'danger'); return; }

        const r = await ajaxPost('update', { id, tipo_documento: tipo, nombre: nom, descripcion: desc });
        if (r.ok) {
            toast(r.mensaje);
            setTimeout(() => location.reload(), 800);
        } else {
            toast(r.mensaje, 'danger');
        }
    });

    // Activar
    document.querySelectorAll('.btn-activar').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (!confirm('¿Activar esta plantilla? Se desactivará la plantilla activa actual para este tipo de documento.')) return;
            const r = await ajaxPost('activar', { id: btn.dataset.id });
            if (r.ok) { toast(r.mensaje); setTimeout(() => location.reload(), 800); }
            else toast(r.mensaje, 'danger');
        });
    });

    // Desactivar
    document.querySelectorAll('.btn-desactivar').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (!confirm('¿Desactivar esta plantilla? Se usará el diseño por defecto del sistema.')) return;
            const r = await ajaxPost('desactivar', { id: btn.dataset.id });
            if (r.ok) { toast(r.mensaje); setTimeout(() => location.reload(), 800); }
            else toast(r.mensaje, 'danger');
        });
    });

    // Eliminar desde tabla
    document.querySelectorAll('.btn-eliminar').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (!confirm('¿Eliminar esta plantilla? Esta acción no se puede deshacer.')) return;
            const r = await ajaxPost('delete', { id: btn.dataset.id });
            if (r.ok) { toast(r.mensaje); setTimeout(() => location.reload(), 800); }
            else toast(r.mensaje, 'danger');
        });
    });

    // Eliminar desde modal editar
    document.getElementById('btn-eliminar-desde-modal')?.addEventListener('click', async () => {
        const id = document.getElementById('btn-eliminar-desde-modal').dataset.id;
        if (!confirm('¿Eliminar esta plantilla? Esta acción no se puede deshacer.')) return;
        const r = await ajaxPost('delete', { id });
        if (r.ok) { toast(r.mensaje); setTimeout(() => location.reload(), 800); }
        else toast(r.mensaje, 'danger');
    });

    // Buscador y filtro tipo en tiempo real
    let timerBuscar;
    document.getElementById('inp-buscar')?.addEventListener('input', () => {
        clearTimeout(timerBuscar);
        timerBuscar = setTimeout(() => {
            const b    = document.getElementById('inp-buscar').value;
            const tipo = document.getElementById('sel-tipo').value;
            location.href = RUTA + '?b=' + encodeURIComponent(b) + '&tipo=' + encodeURIComponent(tipo);
        }, 400);
    });
    document.getElementById('sel-tipo')?.addEventListener('change', () => {
        const b    = document.getElementById('inp-buscar').value;
        const tipo = document.getElementById('sel-tipo').value;
        location.href = RUTA + '?b=' + encodeURIComponent(b) + '&tipo=' + encodeURIComponent(tipo);
    });
})();
</script>
