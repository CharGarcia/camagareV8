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
$rowsHtml = $rowsHtml ?? '';
?>
<style>
.banco-row { cursor: pointer; }
.banco-row:hover { background-color: rgba(0,0,0,.04); }
.bancos-ecuador-header { flex-shrink: 0; }
.bancos-ecuador-scroll { max-height: calc(100dvh - 280px); overflow-y: auto; }
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

<div class="input-group input-group-sm mb-3" style="max-width: 320px;">
    <span class="input-group-text"><i class="bi bi-search"></i></span>
    <input type="text" id="input-buscar-bancos" class="form-control" placeholder="Buscar en código, nombre, spi, sci..." value="<?= htmlspecialchars($buscar) ?>" autocomplete="off">
</div>

<div class="card cmg-table-card">
    <div class="card-body p-0">
        <div class="bancos-ecuador-scroll">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="sortable-header" data-sort="codigo_banco" role="button">Código <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="nombre_banco" role="button">Nombre <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="spi" role="button">SPI <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="sci" role="button">SCI <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" data-sort="status" role="button">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyBancos"><?= $rowsHtml ?></tbody>
            </table>
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
    var base = '<?= $base ?>';
    var modal = document.getElementById('modalEditarBanco');
    var form = modal ? modal.querySelector('form') : null;

    function abrirModalBanco(row) {
        if (!modal || !form) return;
        form.querySelector('#edit-id').value = row.dataset.id || '';
        form.querySelector('#edit-codigo_banco').value = row.dataset.codigo || '';
        form.querySelector('#edit-nombre_banco').value = row.dataset.nombre || '';
        form.querySelector('#edit-spi').value = row.dataset.spi || '';
        form.querySelector('#edit-sci').value = row.dataset.sci || '';
        form.querySelector('#edit-status').value = row.dataset.status || '1';
        new bootstrap.Modal(modal).show();
    }

    // Delegación de eventos: las filas se reemplazan en cada búsqueda/orden
    // AJAX, por lo que el listener va en el tbody (contenedor fijo), no en cada <tr>.
    var tbodyBancos = document.getElementById('tbodyBancos');
    if (tbodyBancos) {
        tbodyBancos.addEventListener('click', function(e) {
            var row = e.target.closest('.banco-row');
            if (row) abrirModalBanco(row);
        });
        tbodyBancos.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            var row = e.target.closest('.banco-row');
            if (row) {
                e.preventDefault();
                abrirModalBanco(row);
            }
        });
    }

    // Búsqueda y orden en tiempo real: reemplazan solo la tabla vía AJAX, sin
    // recargar la página (el input nunca pierde el foco). Mismo patrón que
    // ASIENTOTIPO_cargarListado (public/js/modulos/asientos_tipo_modal.js).
    var timer = null;
    window.BANCOS_currentSort = '<?= htmlspecialchars($ordenCol) ?>';
    window.BANCOS_currentDir = '<?= htmlspecialchars($ordenDir) ?>';

    window.BANCOS_cargarListado = function() {
        var inputB = document.getElementById('input-buscar-bancos');
        var b = inputB ? inputB.value.trim() : '';
        var tbodyEl = document.getElementById('tbodyBancos');
        if (tbodyEl) tbodyEl.innerHTML = '<tr><td colspan="5" class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary"></span> Cargando...</td></tr>';

        fetch(base + '/config/bancos-ecuador-search?b=' + encodeURIComponent(b) + '&sort=' + window.BANCOS_currentSort + '&dir=' + window.BANCOS_currentDir, {
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

    var inputBuscar = document.getElementById('input-buscar-bancos');
    if (inputBuscar) {
        inputBuscar.addEventListener('input', function() {
            clearTimeout(timer);
            timer = setTimeout(function() {
                BANCOS_cargarListado();
            }, 400);
        });
    }

    if (window.CMG_initSort) {
        window.CMG_initSort('bancos-ecuador', function(col, dir) {
            window.BANCOS_currentSort = col;
            window.BANCOS_currentDir = dir;
            BANCOS_cargarListado();
        }, { col: window.BANCOS_currentSort, dir: window.BANCOS_currentDir });
    }
})();
</script>
