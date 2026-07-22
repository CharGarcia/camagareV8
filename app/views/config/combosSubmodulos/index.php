<?php
/** @var string $titulo */
/** @var array $rows */
/** @var string $buscar */
/** @var array $modulosCatalogo */
$base = BASE_URL;
$rows = $rows ?? [];
$buscar = $buscar ?? '';
$modulosCatalogo = $modulosCatalogo ?? [];
$msg = $msg ?? null;

$colores = ['primary', 'secondary', 'success', 'info', 'warning', 'danger', 'dark'];
?>
<style>
.combos-row { cursor: pointer; }
.combos-row:hover { background-color: rgba(0,0,0,.04); }
.combos-scroll { max-height: calc(100dvh - 280px); overflow-y: auto; }
.combos-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
.combos-desc-cell { max-width: 320px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
#combos-catalogo-checklist { max-height: 340px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: .375rem; padding: .5rem .75rem; }
#combos-catalogo-checklist .cat-modulo-titulo { font-size: .75rem; font-weight: 600; color: #6c757d; text-transform: uppercase; margin-top: .5rem; }
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0"><i class="bi bi-box-seam"></i> <?= htmlspecialchars($titulo) ?></h5>
    <div class="d-flex gap-2">
        <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
        <button type="button" class="btn btn-primary btn-sm" onclick="abrirModalCrearCombo()"><i class="bi bi-plus-lg"></i> Nuevo Combo</button>
    </div>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= htmlspecialchars($msg[0]) ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($msg[1]) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<form method="GET" action="<?= rtrim($base, '/') ?>/config/combos-submodulos" class="mb-3">
    <div class="input-group input-group-sm" style="max-width: 380px;">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" name="buscar" class="form-control" placeholder="Buscar combo por nombre o descripción..." value="<?= htmlspecialchars($buscar) ?>">
        <button type="submit" class="btn btn-outline-primary">Buscar</button>
        <?php if ($buscar !== ''): ?>
        <a href="<?= rtrim($base, '/') ?>/config/combos-submodulos" class="btn btn-outline-secondary">Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<div class="card cmg-table-card">
    <div class="card-body p-0">
        <div class="combos-scroll">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Nombre</th>
                        <th class="combos-desc-cell">Descripción</th>
                        <th class="text-center">Submódulos</th>
                        <th class="text-center">Precio</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center pe-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <?php
                    $id = (int) $r['id'];
                    $c_nombre = htmlspecialchars($r['nombre'] ?? '');
                    $c_desc = htmlspecialchars($r['descripcion'] ?? '');
                    $c_total = (int) ($r['total_submodulos'] ?? 0);
                    $c_precio = $r['precio'] !== null ? number_format((float) $r['precio'], 2) : null;
                    $c_activo = !empty($r['activo']);
                    $c_color = htmlspecialchars($r['clase_color'] ?? 'primary');
                    $items = $r['items'] ?? [];
                    $listaSubmodulos = array_map(static fn ($it) => $it['nombre_submodulo'] ?? '', $items);

                    $rj = htmlspecialchars(json_encode([
                        'id' => $id,
                        'nombre' => $r['nombre'] ?? '',
                        'descripcion' => $r['descripcion'] ?? '',
                        'precio' => $r['precio'],
                        'clase_color' => $r['clase_color'] ?? 'primary',
                        'orden' => (int) ($r['orden'] ?? 0),
                        'activo' => $c_activo,
                        'items' => array_map(static fn ($it) => [
                            'id_modulo' => (int) $it['id_modulo'],
                            'id_submodulo' => (int) $it['id_submodulo'],
                        ], $items),
                    ]), ENT_QUOTES, 'UTF-8');
                    ?>
                    <tr class="combos-row" role="button" tabindex="0" data-json="<?= $rj ?>" onclick="abrirModalEditarCombo(this)">
                        <td class="ps-3"><span class="badge bg-<?= $c_color ?>"><?= $c_nombre ?></span></td>
                        <td class="combos-desc-cell" title="<?= $c_desc ?>"><?= $c_desc ?></td>
                        <td class="text-center" title="<?= htmlspecialchars(implode(', ', $listaSubmodulos)) ?>">
                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25"><?= $c_total ?></span>
                        </td>
                        <td class="text-center"><?= $c_precio !== null ? '$' . $c_precio : '<span class="text-muted">—</span>' ?></td>
                        <td class="text-center">
                            <?php if ($c_activo): ?>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Activo</span>
                            <?php else: ?>
                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center pe-3" onclick="event.stopPropagation()">
                            <form method="POST" action="<?= $base ?>/config/combos-submodulos?action=eliminar" class="d-inline" onsubmit="return confirm('¿Eliminar el combo &quot;<?= addslashes($c_nombre) ?>&quot;?');">
                                <input type="hidden" name="id" value="<?= $id ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-1 border-0" title="Eliminar">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (empty($rows)): ?>
            <p class="text-muted text-center py-4 mb-0">No hay combos registrados o no coinciden con la búsqueda.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Combo -->
<div class="modal fade" id="modalCombo" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light border-bottom-0 pb-3">
                <h5 class="modal-title fw-bold" id="modalComboTitulo"><i class="bi bi-plus-circle"></i> Nuevo Combo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formCombo" method="POST" action="<?= $base ?>/config/combos-submodulos?action=store">
                <input type="hidden" name="id" id="combo_id" value="">
                <div class="modal-body border-top px-4 py-4">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label text-muted small fw-bold mb-1">Nombre *</label>
                            <input type="text" class="form-control" name="nombre" id="combo_nombre" required placeholder="Ej. Solo Facturación Electrónica">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small fw-bold mb-1">Precio (referencia)</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" name="precio" id="combo_precio" step="0.01" min="0" placeholder="Opcional">
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small fw-bold mb-1">Descripción</label>
                            <textarea class="form-control" name="descripcion" id="combo_descripcion" rows="2" placeholder="Para qué tipo de cliente sirve este combo"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small fw-bold mb-1">Color</label>
                            <select class="form-select" name="clase_color" id="combo_color">
                                <?php foreach ($colores as $col): ?>
                                <option value="<?= $col ?>"><?= ucfirst($col) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small fw-bold mb-1">Orden</label>
                            <input type="number" class="form-control" name="orden" id="combo_orden" value="0" min="0">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" role="switch" id="combo_activo" name="activo" value="1" checked>
                                <label class="form-check-label fw-medium" for="combo_activo">Visible (activo)</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label text-muted small fw-bold mb-0">Submódulos incluidos *</label>
                                <input type="text" id="combo-catalogo-buscar" class="form-control form-control-sm" style="max-width:220px;" placeholder="Filtrar...">
                            </div>
                            <div id="combos-catalogo-checklist">
                                <?php foreach ($modulosCatalogo as $mod): ?>
                                <div class="cat-modulo-bloque">
                                    <div class="cat-modulo-titulo"><?= htmlspecialchars($mod['nombre_modulo']) ?></div>
                                    <?php foreach ($mod['submodulos'] as $sub): ?>
                                    <div class="form-check cat-submodulo-item">
                                        <input class="form-check-input combo-sub-check" type="checkbox" name="submodulos[]"
                                               value="<?= (int) $mod['id_modulo'] ?>:<?= (int) $sub['id_submodulo'] ?>"
                                               id="csub-<?= (int) $mod['id_modulo'] ?>-<?= (int) $sub['id_submodulo'] ?>">
                                        <label class="form-check-label small" for="csub-<?= (int) $mod['id_modulo'] ?>-<?= (int) $sub['id_submodulo'] ?>">
                                            <?= htmlspecialchars($sub['nombre_submodulo']) ?>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endforeach; ?>
                                <?php if (empty($modulosCatalogo)): ?>
                                <p class="text-muted small mb-0">No hay submódulos en el catálogo.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0 py-3 px-4">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 shadow-sm"><i class="bi bi-check-lg"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function limpiarChecksCombo() {
    document.querySelectorAll('.combo-sub-check').forEach(function(c) { c.checked = false; });
}

function abrirModalCrearCombo() {
    document.getElementById('modalComboTitulo').innerHTML = '<i class="bi bi-plus-circle"></i> Nuevo Combo';
    document.getElementById('formCombo').action = '<?= $base ?>/config/combos-submodulos?action=store';
    document.getElementById('combo_id').value = '';
    document.getElementById('combo_nombre').value = '';
    document.getElementById('combo_descripcion').value = '';
    document.getElementById('combo_precio').value = '';
    document.getElementById('combo_color').value = 'primary';
    document.getElementById('combo_orden').value = '0';
    document.getElementById('combo_activo').checked = true;
    limpiarChecksCombo();
    new bootstrap.Modal(document.getElementById('modalCombo')).show();
}

function abrirModalEditarCombo(tr) {
    const data = JSON.parse(tr.getAttribute('data-json'));
    document.getElementById('modalComboTitulo').innerHTML = '<i class="bi bi-pencil"></i> Editar Combo';
    document.getElementById('formCombo').action = '<?= $base ?>/config/combos-submodulos?action=update';
    document.getElementById('combo_id').value = data.id;
    document.getElementById('combo_nombre').value = data.nombre || '';
    document.getElementById('combo_descripcion').value = data.descripcion || '';
    document.getElementById('combo_precio').value = data.precio !== null ? data.precio : '';
    document.getElementById('combo_color').value = data.clase_color || 'primary';
    document.getElementById('combo_orden').value = data.orden || 0;
    document.getElementById('combo_activo').checked = !!data.activo;

    limpiarChecksCombo();
    (data.items || []).forEach(function(it) {
        var chk = document.getElementById('csub-' + it.id_modulo + '-' + it.id_submodulo);
        if (chk) chk.checked = true;
    });

    new bootstrap.Modal(document.getElementById('modalCombo')).show();
}

document.getElementById('formCombo').addEventListener('submit', function(e) {
    var marcados = document.querySelectorAll('.combo-sub-check:checked').length;
    if (marcados === 0) {
        e.preventDefault();
        alert('Seleccione al menos un submódulo para el combo.');
    }
});

(function() {
    var buscador = document.getElementById('combo-catalogo-buscar');
    if (!buscador) return;
    buscador.addEventListener('input', function() {
        var q = buscador.value.toLowerCase().trim();
        document.querySelectorAll('.cat-modulo-bloque').forEach(function(bloque) {
            var algunaVisible = false;
            bloque.querySelectorAll('.cat-submodulo-item').forEach(function(item) {
                var texto = item.textContent.toLowerCase();
                var visible = !q || texto.indexOf(q) !== -1;
                item.style.display = visible ? '' : 'none';
                if (visible) algunaVisible = true;
            });
            bloque.style.display = algunaVisible ? '' : 'none';
        });
    });
})();
</script>
