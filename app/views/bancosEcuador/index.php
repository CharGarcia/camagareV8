<?php
/** @var string $titulo */
/** @var array $rows */
/** @var string $ordenCol */
/** @var string $ordenDir */
/** @var string $buscar */
$base = BASE_URL;
$rows = $rows ?? [];
$ordenCol = $ordenCol ?? 'nombre_banco';
$ordenDir = $ordenDir ?? 'asc';
$buscar = $buscar ?? '';
$msg = $_SESSION['bancos_msg'] ?? null;
unset($_SESSION['bancos_msg']);

function thSort($base, $col, $label, $ordenCol, $ordenDir, $buscar, $align = '') {
    $dir = ($ordenCol === $col && strtolower($ordenDir) === 'asc') ? 'desc' : 'asc';
    $url = rtrim($base, '/') . '/config/bancos-ecuador?sort=' . urlencode($col) . '&dir=' . $dir;
    if ($buscar !== '') $url .= '&b=' . urlencode($buscar);
    $cls = trim('text-decoration-none ' . $align);
    return '<a href="' . htmlspecialchars($url) . '" class="' . $cls . '" title="Ordenar por ' . htmlspecialchars($label) . '">' . htmlspecialchars($label) . '</a>';
}
?>
<style>
.banco-row { cursor: pointer; }
.banco-row:hover { background-color: rgba(0,0,0,.04); }
.bancos-ecuador-header { flex-shrink: 0; }
.bancos-ecuador-scroll { max-height: calc(100vh - 280px); overflow-y: auto; }
.bancos-ecuador-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
</style>
<div class="bancos-ecuador-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-bank"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">Haga clic en una fila para editar. Bancos de Ecuador.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoBanco"><i class="bi bi-plus-lg"></i> Crear nuevo</button>
    </div>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= htmlspecialchars($msg[0]) ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($msg[1]) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<form method="GET" action="<?= rtrim($base, '/') ?>/config/bancos-ecuador" class="mb-3">
    <input type="hidden" name="sort" value="<?= htmlspecialchars($ordenCol) ?>">
    <input type="hidden" name="dir" value="<?= htmlspecialchars($ordenDir) ?>">
    <div class="input-group input-group-sm" style="max-width: 320px;">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" name="b" class="form-control" placeholder="Buscar en código, nombre, spi, sci..." value="<?= htmlspecialchars($buscar) ?>">
        <button type="submit" class="btn btn-outline-primary">Buscar</button>
        <?php if ($buscar !== ''): ?>
        <a href="<?= rtrim($base, '/') ?>/config/bancos-ecuador?sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>" class="btn btn-outline-secondary">Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<div class="card cmg-table-card">
    <div class="card-body p-0">
        <div class="bancos-ecuador-scroll">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= thSort($base, 'codigo_banco', 'Código', $ordenCol, $ordenDir, $buscar) ?></th>
                        <th><?= thSort($base, 'nombre_banco', 'Nombre', $ordenCol, $ordenDir, $buscar) ?></th>
                        <th><?= thSort($base, 'spi', 'SPI', $ordenCol, $ordenDir, $buscar) ?></th>
                        <th><?= thSort($base, 'sci', 'SCI', $ordenCol, $ordenDir, $buscar) ?></th>
                        <th class="text-center"><?= thSort($base, 'status', 'Estado', $ordenCol, $ordenDir, $buscar, 'text-center d-inline-block') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <?php
                    $id = (int)($r['id'] ?? $r['id_bancos'] ?? 0);
                    $status = (int)($r['status'] ?? 1);
                    ?>
                    <tr class="banco-row" role="button" tabindex="0" data-id="<?= $id ?>"
                        data-codigo="<?= htmlspecialchars($r['codigo_banco'] ?? '') ?>"
                        data-nombre="<?= htmlspecialchars($r['nombre_banco'] ?? '') ?>"
                        data-spi="<?= htmlspecialchars($r['spi'] ?? '') ?>"
                        data-sci="<?= htmlspecialchars($r['sci'] ?? '') ?>"
                        data-status="<?= $status ?>">
                        <td><code><?= htmlspecialchars($r['codigo_banco'] ?? '') ?></code></td>
                        <td><?= htmlspecialchars($r['nombre_banco'] ?? '') ?></td>
                        <td><?= htmlspecialchars($r['spi'] ?? '') ?></td>
                        <td><?= htmlspecialchars($r['sci'] ?? '') ?></td>
                        <td class="text-center">
                            <?php if ($status): ?>
                            <span class="badge bg-success">Activo</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Inactivo</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (empty($rows)): ?>
            <p class="text-muted text-center py-4 mb-0">No hay bancos registrados.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Nuevo banco -->
<div class="modal fade" id="modalNuevoBanco" tabindex="-1" aria-labelledby="modalNuevoBancoLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/bancosEcuadorStore">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalNuevoBancoLabel"><i class="bi bi-plus-circle"></i> Nuevo banco</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="new-codigo_banco" class="form-label">Código <span class="text-danger">*</span></label>
                            <input type="text" id="new-codigo_banco" name="codigo_banco" class="form-control" required placeholder="Ej: 001">
                        </div>
                        <div class="col-md-4">
                            <label for="new-spi" class="form-label">SPI</label>
                            <input type="text" id="new-spi" name="spi" class="form-control" placeholder="SPI">
                        </div>
                        <div class="col-md-4">
                            <label for="new-sci" class="form-label">SCI</label>
                            <input type="text" id="new-sci" name="sci" class="form-control" placeholder="SCI">
                        </div>
                        <div class="col-12">
                            <label for="new-nombre_banco" class="form-label">Nombre</label>
                            <input type="text" id="new-nombre_banco" name="nombre_banco" class="form-control" placeholder="Nombre del banco">
                        </div>
                        <div class="col-12">
                            <label for="new-status" class="form-label">Estado</label>
                            <select id="new-status" name="status" class="form-select">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
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

<!-- Modal Editar banco -->
<div class="modal fade" id="modalEditarBanco" tabindex="-1" aria-labelledby="modalEditarBancoLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/bancosEcuadorUpdate">
                <input type="hidden" name="id" id="edit-id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditarBancoLabel"><i class="bi bi-pencil"></i> Editar banco</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="edit-codigo_banco" class="form-label">Código <span class="text-danger">*</span></label>
                            <input type="text" id="edit-codigo_banco" name="codigo_banco" class="form-control" required placeholder="Ej: 001">
                        </div>
                        <div class="col-md-4">
                            <label for="edit-spi" class="form-label">SPI</label>
                            <input type="text" id="edit-spi" name="spi" class="form-control" placeholder="SPI">
                        </div>
                        <div class="col-md-4">
                            <label for="edit-sci" class="form-label">SCI</label>
                            <input type="text" id="edit-sci" name="sci" class="form-control" placeholder="SCI">
                        </div>
                        <div class="col-12">
                            <label for="edit-nombre_banco" class="form-label">Nombre</label>
                            <input type="text" id="edit-nombre_banco" name="nombre_banco" class="form-control" placeholder="Nombre del banco">
                        </div>
                        <div class="col-12">
                            <label for="edit-status" class="form-label">Estado</label>
                            <select id="edit-status" name="status" class="form-select">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
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
    var modal = document.getElementById('modalEditarBanco');
    var form = modal ? modal.querySelector('form') : null;
    if (!modal || !form) return;

    document.querySelectorAll('.banco-row').forEach(function(row) {
        row.addEventListener('click', function() {
            form.querySelector('#edit-id').value = this.dataset.id || '';
            form.querySelector('#edit-codigo_banco').value = this.dataset.codigo || '';
            form.querySelector('#edit-nombre_banco').value = this.dataset.nombre || '';
            form.querySelector('#edit-spi').value = this.dataset.spi || '';
            form.querySelector('#edit-sci').value = this.dataset.sci || '';
            form.querySelector('#edit-status').value = this.dataset.status || '1';
            new bootstrap.Modal(modal).show();
        });
        row.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });
    });
})();
</script>
