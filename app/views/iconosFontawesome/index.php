<?php
/** @var string $titulo */
/** @var array $rows */
/** @var string $ordenCol */
/** @var string $ordenDir */
/** @var string $buscar */
/** @var array<int, int> $refsMap */
$base = BASE_URL;
$rows = $rows ?? [];
$refsMap = $refsMap ?? [];
$ordenCol = $ordenCol ?? 'nombre_icono';
$ordenDir = $ordenDir ?? 'asc';
$buscar = $buscar ?? '';
$msg = $_SESSION['iconos_msg'] ?? null;
unset($_SESSION['iconos_msg']);

function thSort($base, $col, $label, $ordenCol, $ordenDir, $buscar, $align = '') {
    $dir = ($ordenCol === $col && strtolower($ordenDir) === 'asc') ? 'desc' : 'asc';
    $url = rtrim($base, '/') . '/config/iconos-fontawesome?sort=' . urlencode($col) . '&dir=' . $dir;
    if ($buscar !== '') $url .= '&b=' . urlencode($buscar);
    $cls = trim('text-decoration-none ' . $align);
    return '<a href="' . htmlspecialchars($url) . '" class="' . $cls . '" title="Ordenar por ' . htmlspecialchars($label) . '">' . htmlspecialchars($label) . '</a>';
}

?>
<style>
.icono-row { cursor: pointer; }
.icono-row:hover { background-color: rgba(0,0,0,.04); }
.iconos-fontawesome-header { flex-shrink: 0; }
.iconos-fontawesome-scroll { max-height: calc(100vh - 280px); overflow-y: auto; }
.iconos-fontawesome-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
.icon-preview { width: 28px; text-align: center; font-size: 1.1rem; }
</style>
<div class="iconos-fontawesome-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-emoji-smile"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">Haga clic en una fila para editar. Iconos usados en módulos y submódulos.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoIcono"><i class="bi bi-plus-lg"></i> Crear nuevo</button>
    </div>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= htmlspecialchars($msg[0]) ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($msg[1]) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<form method="GET" action="<?= rtrim($base, '/') ?>/config/iconos-fontawesome" class="mb-3">
    <input type="hidden" name="sort" value="<?= htmlspecialchars($ordenCol) ?>">
    <input type="hidden" name="dir" value="<?= htmlspecialchars($ordenDir) ?>">
    <div class="input-group input-group-sm" style="max-width: 320px;">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" name="b" class="form-control" placeholder="Buscar por nombre..." value="<?= htmlspecialchars($buscar) ?>">
        <button type="submit" class="btn btn-outline-primary">Buscar</button>
        <?php if ($buscar !== ''): ?>
        <a href="<?= rtrim($base, '/') ?>/config/iconos-fontawesome?sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>" class="btn btn-outline-secondary">Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<div class="card cmg-table-card">
    <div class="card-body p-0">
        <div class="iconos-fontawesome-scroll">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="icon-preview"></th>
                        <th><?= thSort($base, 'nombre_icono', 'Nombre del icono', $ordenCol, $ordenDir, $buscar) ?></th>
                        <th class="text-end" style="width: 5rem;">Uso</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <?php
                    $id = (int)($r['id'] ?? $r['id_icono'] ?? 0);
                    $nombreIcono = $r['nombre_icono'] ?? '';
                    $refs = (int) ($refsMap[$id] ?? 0);
                    $cls = iconoClase($nombreIcono);
                    ?>
                    <tr class="icono-row" role="button" tabindex="0" data-id="<?= $id ?>" data-nombre="<?= htmlspecialchars($nombreIcono) ?>" data-refs="<?= $refs ?>">
                        <td class="icon-preview">
                            <i class="<?= htmlspecialchars($cls) ?>" title="<?= htmlspecialchars($nombreIcono) ?>"></i>
                        </td>
                        <td><code><?= htmlspecialchars($nombreIcono) ?></code></td>
                        <td class="text-end small text-muted"><?= $refs ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (empty($rows)): ?>
            <p class="text-muted text-center py-4 mb-0">No hay iconos registrados.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<form id="formEliminarIcono" method="POST" action="<?= $base ?>/config/iconosFontawesomeDelete" class="d-none" aria-hidden="true">
    <input type="hidden" name="id" id="del-id" value="">
</form>

<!-- Modal Nuevo icono -->
<div class="modal fade" id="modalNuevoIcono" tabindex="-1" aria-labelledby="modalNuevoIconoLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/iconosFontawesomeStore">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalNuevoIconoLabel"><i class="bi bi-plus-circle"></i> Nuevo icono</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="new-nombre_icono" class="form-label">Nombre del icono <span class="text-danger">*</span></label>
                            <input type="text" id="new-nombre_icono" name="nombre_icono" class="form-control" required placeholder="Ej: fa-folder, fas fa-file, bi bi-house">
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

<!-- Modal Editar icono -->
<div class="modal fade" id="modalEditarIcono" tabindex="-1" aria-labelledby="modalEditarIconoLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/iconosFontawesomeUpdate">
                <input type="hidden" name="id" id="edit-id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditarIconoLabel"><i class="bi bi-pencil"></i> Editar icono</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="edit-nombre_icono" class="form-label">Nombre del icono <span class="text-danger">*</span></label>
                            <input type="text" id="edit-nombre_icono" name="nombre_icono" class="form-control" required placeholder="Ej: fa-folder, fas fa-file, bi bi-house">
                        </div>
                    </div>
                </div>
                <div class="modal-footer d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <button type="submit" form="formEliminarIcono" class="btn btn-outline-danger btn-sm" id="btn-eliminar-icono" title="">Eliminar</button>
                    <div class="ms-auto d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Guardar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    var modal = document.getElementById('modalEditarIcono');
    var form = modal ? modal.querySelector('form') : null;
    if (!modal || !form) return;

    var formDel = document.getElementById('formEliminarIcono');
    var delId = document.getElementById('del-id');
    var btnEliminar = document.getElementById('btn-eliminar-icono');

    document.querySelectorAll('.icono-row').forEach(function(row) {
        row.addEventListener('click', function() {
            form.querySelector('#edit-id').value = this.dataset.id || '';
            form.querySelector('#edit-nombre_icono').value = this.dataset.nombre || '';
            if (delId) delId.value = this.dataset.id || '';
            var refs = parseInt(this.dataset.refs || '0', 10);
            if (btnEliminar) {
                btnEliminar.disabled = refs > 0;
                btnEliminar.title = refs > 0 ? 'No se puede eliminar: está en uso en módulos o submódulos del menú.' : 'Eliminar este icono';
            }
            new bootstrap.Modal(modal).show();
        });
        row.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });
    });

    if (formDel && btnEliminar) {
        formDel.addEventListener('submit', function(e) {
            if (btnEliminar.disabled) {
                e.preventDefault();
                return false;
            }
            if (!confirm('¿Eliminar este icono de forma permanente?')) {
                e.preventDefault();
                return false;
            }
        });
    }
})();
</script>
