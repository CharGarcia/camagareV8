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

function thSortTipo($urlBase, $col, $label, $ordenCol, $ordenDir, $buscar) {
    $dir = ($ordenCol === $col && strtolower($ordenDir) === 'asc') ? 'desc' : 'asc';
    $params = ['tab' => 'tipos'];
    if ($col !== 'nombre') $params['sort_tipo'] = $col;
    if ($dir !== 'asc') $params['dir_tipo'] = $dir;
    if ($buscar !== '') $params['b_tipo'] = $buscar;
    return '<a href="' . htmlspecialchars($urlBase . '?' . http_build_query($params)) . '" class="text-decoration-none" title="Ordenar por ' . htmlspecialchars($label) . '">' . htmlspecialchars($label) . '</a>';
}
function thSortUni($urlBase, $col, $label, $ordenCol, $ordenDir, $buscar, $filtro) {
    $dir = ($ordenCol === $col && strtolower($ordenDir) === 'asc') ? 'desc' : 'asc';
    $params = ['tab' => 'unidades'];
    if ($col !== 'nombre') $params['sort_uni'] = $col;
    if ($dir !== 'asc') $params['dir_uni'] = $dir;
    if ($buscar !== '') $params['b_uni'] = $buscar;
    if ($filtro !== null && $filtro > 0) $params['f_tipo'] = $filtro;
    return '<a href="' . htmlspecialchars($urlBase . '?' . http_build_query($params)) . '" class="text-decoration-none" title="Ordenar por ' . htmlspecialchars($label) . '">' . htmlspecialchars($label) . '</a>';
}
?>
<style>
.unidades-scroll { max-height: calc(100vh - 320px); overflow-y: auto; }
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
        <form method="GET" action="<?= $urlBase ?>" class="mb-3">
            <input type="hidden" name="tab" value="tipos">
            <?php if ($ordenColTipo !== 'nombre' || $ordenDirTipo !== 'asc'): ?>
            <input type="hidden" name="sort_tipo" value="<?= htmlspecialchars($ordenColTipo) ?>">
            <input type="hidden" name="dir_tipo" value="<?= htmlspecialchars($ordenDirTipo) ?>">
            <?php endif; ?>
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <div class="input-group input-group-sm" style="max-width: 280px;">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="b_tipo" class="form-control" placeholder="Buscar tipo..." value="<?= htmlspecialchars($buscarTipo) ?>">
                    <button type="submit" class="btn btn-outline-primary">Buscar</button>
                </div>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoTipo"><i class="bi bi-plus-lg"></i> Nuevo tipo</button>
            </div>
        </form>
        <div class="unidades-scroll">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= thSortTipo($urlBase, 'codigo', 'Código', $ordenColTipo, $ordenDirTipo, $buscarTipo) ?></th>
                        <th><?= thSortTipo($urlBase, 'nombre', 'Nombre', $ordenColTipo, $ordenDirTipo, $buscarTipo) ?></th>
                        <th>Descripción</th>
                        <th class="text-center"><?= thSortTipo($urlBase, 'estado', 'Estado', $ordenColTipo, $ordenDirTipo, $buscarTipo) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rowsTipos as $r): ?>
                    <?php $estado = (int)($r['estado'] ?? 1); ?>
                    <tr class="tipo-row" data-id="<?= (int)($r['id'] ?? 0) ?>"
                        data-codigo="<?= htmlspecialchars($r['codigo'] ?? '') ?>"
                        data-nombre="<?= htmlspecialchars($r['nombre'] ?? '') ?>"
                        data-descripcion="<?= htmlspecialchars($r['descripcion'] ?? '') ?>"
                        data-estado="<?= $estado ?>">
                        <td><code><?= htmlspecialchars($r['codigo'] ?? '') ?></code></td>
                        <td><?= htmlspecialchars($r['nombre'] ?? '') ?></td>
                        <td class="small text-muted"><?= htmlspecialchars($r['descripcion'] ?? '') ?></td>
                        <td class="text-center">
                            <?php if ($estado): ?><span class="badge bg-success">Activo</span><?php else: ?><span class="badge bg-secondary">Inactivo</span><?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (empty($rowsTipos)): ?><p class="text-muted text-center py-4 mb-0">No hay tipos de unidad.</p><?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($tab === 'unidades'): ?>
        <form method="GET" action="<?= $urlBase ?>" class="mb-3">
            <input type="hidden" name="tab" value="unidades">
            <?php if ($ordenColUni !== 'nombre' || $ordenDirUni !== 'asc'): ?>
            <input type="hidden" name="sort_uni" value="<?= htmlspecialchars($ordenColUni) ?>">
            <input type="hidden" name="dir_uni" value="<?= htmlspecialchars($ordenDirUni) ?>">
            <?php endif; ?>
            <?php if ($filtroTipo): ?><input type="hidden" name="f_tipo" value="<?= (int)$filtroTipo ?>"><?php endif; ?>
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <div class="input-group input-group-sm" style="max-width: 280px;">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="b_uni" class="form-control" placeholder="Buscar unidad..." value="<?= htmlspecialchars($buscarUni) ?>">
                    <button type="submit" class="btn btn-outline-primary">Buscar</button>
                </div>
                <select name="f_tipo" class="form-select form-select-sm" style="max-width: 200px;" onchange="this.form.submit()">
                    <option value="">Todos los tipos</option>
                    <?php foreach ($tiposParaSelect as $t): ?>
                    <option value="<?= (int)$t['id'] ?>" <?= $filtroTipo === (int)$t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['nombre'] ?? $t['codigo'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevaUnidad"><i class="bi bi-plus-lg"></i> Nueva unidad</button>
            </div>
        </form>
        <div class="unidades-scroll">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= thSortUni($urlBase, 'codigo', 'Código', $ordenColUni, $ordenDirUni, $buscarUni, $filtroTipo) ?></th>
                        <th><?= thSortUni($urlBase, 'nombre', 'Nombre', $ordenColUni, $ordenDirUni, $buscarUni, $filtroTipo) ?></th>
                        <th><?= thSortUni($urlBase, 'abreviatura', 'Abreviatura', $ordenColUni, $ordenDirUni, $buscarUni, $filtroTipo) ?></th>
                        <th>Tipo</th>
                        <th class="text-end">Factor base</th>
                        <th class="text-center"><?= thSortUni($urlBase, 'estado', 'Estado', $ordenColUni, $ordenDirUni, $buscarUni, $filtroTipo) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rowsUnidades as $r): ?>
                    <?php $estado = (int)($r['estado'] ?? 1); $esBase = (int)($r['es_base'] ?? 0); ?>
                    <tr class="unidad-row" data-id="<?= (int)($r['id'] ?? 0) ?>"
                        data-id-tipo="<?= (int)($r['id_tipo'] ?? 0) ?>"
                        data-codigo="<?= htmlspecialchars($r['codigo'] ?? '') ?>"
                        data-nombre="<?= htmlspecialchars($r['nombre'] ?? '') ?>"
                        data-abreviatura="<?= htmlspecialchars($r['abreviatura'] ?? '') ?>"
                        data-es-base="<?= $esBase ?>"
                        data-factor-base="<?= htmlspecialchars($r['factor_base'] ?? '1') ?>"
                        data-estado="<?= $estado ?>">
                        <td><code><?= htmlspecialchars($r['codigo'] ?? '') ?></code></td>
                        <td><?= htmlspecialchars($r['nombre'] ?? '') ?></td>
                        <td><?= htmlspecialchars($r['abreviatura'] ?? '') ?></td>
                        <td><?= htmlspecialchars($r['tipo_nombre'] ?? '') ?></td>
                        <td class="text-end"><?= $esBase ? '<span class="badge bg-info">Base</span>' : htmlspecialchars($r['factor_base'] ?? '1') ?></td>
                        <td class="text-center">
                            <?php if ($estado): ?><span class="badge bg-success">Activo</span><?php else: ?><span class="badge bg-secondary">Inactivo</span><?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (empty($rowsUnidades)): ?><p class="text-muted text-center py-4 mb-0">No hay unidades de medida.</p><?php endif; ?>
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
    var modalTipo = document.getElementById('modalEditarTipo');
    var formTipo = modalTipo ? modalTipo.querySelector('form') : null;
    if (modalTipo && formTipo) {
        document.querySelectorAll('.tipo-row').forEach(function(row) {
            row.addEventListener('click', function() {
                formTipo.querySelector('#edit-tipo-id').value = this.dataset.id || '';
                formTipo.querySelector('#edit-tipo-codigo').value = this.dataset.codigo || '';
                formTipo.querySelector('#edit-tipo-nombre').value = this.dataset.nombre || '';
                formTipo.querySelector('#edit-tipo-descripcion').value = this.dataset.descripcion || '';
                formTipo.querySelector('#edit-tipo-estado').checked = this.dataset.estado === '1';
                new bootstrap.Modal(modalTipo).show();
            });
        });
    }
    var modalUni = document.getElementById('modalEditarUnidad');
    var formUni = modalUni ? modalUni.querySelector('form') : null;
    if (modalUni && formUni) {
        document.querySelectorAll('.unidad-row').forEach(function(row) {
            row.addEventListener('click', function() {
                formUni.querySelector('#edit-uni-id').value = this.dataset.id || '';
                formUni.querySelector('#edit-uni-id-tipo').value = this.dataset.idTipo || '';
                formUni.querySelector('#edit-uni-codigo').value = this.dataset.codigo || '';
                formUni.querySelector('#edit-uni-nombre').value = this.dataset.nombre || '';
                formUni.querySelector('#edit-uni-abreviatura').value = this.dataset.abreviatura || '';
                formUni.querySelector('#edit-uni-es-base').checked = this.dataset.esBase === '1';
                formUni.querySelector('#edit-uni-factor-base').value = this.dataset.factorBase || '1';
                formUni.querySelector('#edit-uni-estado').checked = this.dataset.estado === '1';
                new bootstrap.Modal(modalUni).show();
            });
        });
    }
})();
</script>
