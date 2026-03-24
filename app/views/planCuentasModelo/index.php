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
$msg = $_SESSION['plan_cuentas_modelo_msg'] ?? null;
unset($_SESSION['plan_cuentas_modelo_msg']);

function thSort($base, $col, $label, $ordenCol, $ordenDir, $buscar, $align = '') {
    $dir = ($ordenCol === $col && strtolower($ordenDir) === 'asc') ? 'desc' : 'asc';
    $url = rtrim($base, '/') . '/config/plan-cuentas-modelo?sort=' . urlencode($col) . '&dir=' . $dir;
    if ($buscar !== '') $url .= '&b=' . urlencode($buscar);
    $cls = trim('text-decoration-none ' . $align);
    return '<a href="' . htmlspecialchars($url) . '" class="' . $cls . '" title="Ordenar por ' . htmlspecialchars($label) . '">' . htmlspecialchars($label) . '</a>';
}
?>
<style>
.pcm-row { cursor: pointer; }
.pcm-row:hover { background-color: rgba(0,0,0,.04); }
.pcm-scroll { max-height: calc(100vh - 280px); overflow-y: auto; }
.pcm-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
</style>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-journal-bookmark"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">Plan de cuentas modelo. Clic en fila para editar. <i class="bi bi-plus-circle"></i> para crear cuenta hija.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>
</div>

<form method="GET" action="<?= rtrim($base, '/') ?>/config/plan-cuentas-modelo" class="mb-3">
    <input type="hidden" name="sort" value="<?= htmlspecialchars($ordenCol) ?>">
    <input type="hidden" name="dir" value="<?= htmlspecialchars($ordenDir) ?>">
    <div class="input-group input-group-sm" style="max-width: 320px;">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" name="b" class="form-control" placeholder="Buscar código, nombre, SRI..." value="<?= htmlspecialchars($buscar) ?>">
        <button type="submit" class="btn btn-outline-primary">Buscar</button>
        <?php if ($buscar !== ''): ?>
        <a href="<?= rtrim($base, '/') ?>/config/plan-cuentas-modelo?sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>" class="btn btn-outline-secondary">Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<div class="card cmg-table-card">
    <div class="card-body p-0">
        <?php if ($msg): ?>
        <div class="alert alert-<?= htmlspecialchars($msg[0]) ?> alert-dismissible fade show m-3 mb-2" role="alert">
            <?= htmlspecialchars($msg[1]) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        <div class="pcm-scroll">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= thSort($base, 'nivel', 'Nivel', $ordenCol, $ordenDir, $buscar) ?></th>
                        <th><?= thSort($base, 'codigo', 'Código', $ordenCol, $ordenDir, $buscar) ?></th>
                        <th><?= thSort($base, 'nombre', 'Nombre', $ordenCol, $ordenDir, $buscar) ?></th>
                        <th class="text-center" style="width:55px">Crear</th>
                        <th class="text-center" style="width:55px">Eliminar</th>
                        <th>SRI</th>
                        <th>Supercias ESF</th>
                        <th>Supercias ERI</th>
                        <th>Supercias ECP-1</th>
                        <th>Supercias ECP-2</th>
                        <th class="text-center"><?= thSort($base, 'status', 'Status', $ordenCol, $ordenDir, $buscar, 'text-center d-inline-block') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <?php $id = (int)($r['id'] ?? 0); $status = (int)($r['status'] ?? 1); $puedeEliminar = !empty($r['puede_eliminar']); $puedeCrearHijo = !empty($r['puede_crear_hijo']); $nivelHijo = (int)($r['nivel_hijo'] ?? 0); $siguienteCodigo = $r['siguiente_codigo'] ?? ''; ?>
                    <tr class="pcm-row" role="button" tabindex="0" data-id="<?= $id ?>"
                        data-codigo="<?= htmlspecialchars($r['codigo'] ?? '') ?>"
                        data-nivel="<?= htmlspecialchars($r['nivel'] ?? '') ?>"
                        data-nombre="<?= htmlspecialchars($r['nombre'] ?? '') ?>"
                        data-codigo-sri="<?= htmlspecialchars($r['codigo_sri'] ?? '') ?>"
                        data-supercias-esf="<?= htmlspecialchars($r['supercias_esf'] ?? '') ?>"
                        data-supercias-eri="<?= htmlspecialchars($r['supercias_eri'] ?? '') ?>"
                        data-supercias-ecp-codigo="<?= htmlspecialchars($r['supercias_ecp_codigo'] ?? '') ?>"
                        data-supercias-ecp-subcodigo="<?= htmlspecialchars($r['supercias_ecp_subcodigo'] ?? '') ?>"
                        data-status="<?= $status ?>"
                        data-codigo-padre="<?= htmlspecialchars($r['codigo'] ?? '') ?>"
                        data-nivel-padre="<?= htmlspecialchars($r['nivel'] ?? '') ?>"
                        data-nivel-hijo="<?= $nivelHijo ?>"
                        data-siguiente-codigo="<?= htmlspecialchars($siguienteCodigo) ?>">
                        <td><?= htmlspecialchars($r['nivel'] ?? '') ?></td>
                        <td><code><?= htmlspecialchars($r['codigo'] ?? '') ?></code></td>
                        <td style="padding-left: <?= max(0, ((int)($r['nivel'] ?? 1) - 1) * 1.5) ?>em"><?= htmlspecialchars($r['nombre'] ?? '') ?></td>
                        <td class="text-center p-1" onclick="event.stopPropagation()">
                            <?php if ($puedeCrearHijo): ?>
                            <button type="button" class="btn btn-link btn-sm text-primary p-0 btn-crear-hijo" title="Crear cuenta de nivel <?= $nivelHijo ?>"><i class="bi bi-plus-circle"></i></button>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center p-1" onclick="event.stopPropagation()">
                            <?php if ($puedeEliminar): ?>
                            <form method="POST" action="<?= $base ?>/config/planCuentasModeloDelete" class="d-inline" onsubmit="return confirm('¿Eliminar esta cuenta?');">
                                <input type="hidden" name="id" value="<?= $id ?>">
                                <button type="submit" class="btn btn-link btn-sm text-danger p-0" title="Eliminar"><i class="bi bi-trash"></i></button>
                            </form>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><small><?= htmlspecialchars($r['codigo_sri'] ?? '') ?></small></td>
                        <td><small><?= htmlspecialchars($r['supercias_esf'] ?? '') ?></small></td>
                        <td><small><?= htmlspecialchars($r['supercias_eri'] ?? '') ?></small></td>
                        <td><small><?= htmlspecialchars($r['supercias_ecp_codigo'] ?? '') ?></small></td>
                        <td><small><?= htmlspecialchars($r['supercias_ecp_subcodigo'] ?? '') ?></small></td>
                        <td class="text-center">
                            <?php if ($status): ?><span class="badge bg-success">Activo</span><?php else: ?><span class="badge bg-secondary">Inactivo</span><?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (empty($rows)): ?>
            <p class="text-muted text-center py-4 mb-0">No hay cuentas en el plan modelo.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Nueva cuenta (crear bajo padre) -->
<div class="modal fade" id="modalNuevaCuenta" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/planCuentasModeloStore">
                <input type="hidden" name="codigo_padre" id="new-codigo-padre">
                <input type="hidden" name="nivel_padre" id="new-nivel-padre">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Crear cuenta de nivel <span id="new-nivel-label">2</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-light py-2 mb-2 small">
                        <strong>Padre:</strong> <span id="new-padre-info"></span> &nbsp;|&nbsp; <strong>Código asignado:</strong> <code id="new-codigo-asignado"></code>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label">Código SRI</label>
                            <input type="text" name="codigo_sri" class="form-control" placeholder="">
                        </div>
                    </div>
                    <div class="mb-2 mt-1">
                        <label class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" id="new-nombre" class="form-control" required placeholder="Nombre de la cuenta">
                    </div>
                    <div class="row g-2 small">
                        <div class="col-md-3">
                            <label class="form-label">Supercias ESF</label>
                            <input type="text" name="supercias_esf" class="form-control form-control-sm" placeholder="">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Supercias ERI</label>
                            <input type="text" name="supercias_eri" class="form-control form-control-sm" placeholder="">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">ECP Código</label>
                            <input type="text" name="supercias_ecp_codigo" class="form-control form-control-sm" placeholder="">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">ECP Subcódigo</label>
                            <input type="text" name="supercias_ecp_subcodigo" class="form-control form-control-sm" placeholder="">
                        </div>
                    </div>
                    <div class="form-check mt-2">
                        <input type="checkbox" name="status" id="new-status" class="form-check-input" value="1" checked>
                        <label class="form-check-label" for="new-status">Activo</label>
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

<!-- Modal Editar cuenta -->
<div class="modal fade" id="modalEditarCuenta" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/planCuentasModeloUpdate">
                <input type="hidden" name="id" id="edit-id">
                <input type="hidden" name="codigo" id="edit-codigo" value="">
                <input type="hidden" name="nivel" id="edit-nivel" value="">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Editar cuenta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-2">
                        <div class="col-md-4">
                            <label class="form-label">Código</label>
                            <input type="text" id="edit-codigo-display" class="form-control bg-light" readonly tabindex="-1" placeholder="" style="cursor:not-allowed">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Nivel</label>
                            <input type="text" id="edit-nivel-display" class="form-control bg-light" readonly tabindex="-1" placeholder="" style="cursor:not-allowed">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check mb-2">
                                <input type="checkbox" name="status" id="edit-status" class="form-check-input" value="1">
                                <label class="form-check-label" for="edit-status">Activo</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Nombre de la cuenta contable <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" id="edit-nombre" class="form-control" required placeholder="Nombre de la cuenta">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Código formulario SRI Impuesto a la Renta</label>
                        <input type="text" name="codigo_sri" id="edit-codigo-sri" class="form-control" placeholder="">
                    </div>
                    <p class="text-muted small mb-2">Códigos para la superintendencia de compañías.</p>
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label">ESF</label>
                            <input type="text" name="supercias_esf" id="edit-supercias-esf" class="form-control form-control-sm" placeholder="">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">ERI</label>
                            <input type="text" name="supercias_eri" id="edit-supercias-eri" class="form-control form-control-sm" placeholder="">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">ECP principal</label>
                            <input type="text" name="supercias_ecp_codigo" id="edit-supercias-ecp-codigo" class="form-control form-control-sm" placeholder="">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">ECP secundario</label>
                            <input type="text" name="supercias_ecp_subcodigo" id="edit-supercias-ecp-subcodigo" class="form-control form-control-sm" placeholder="">
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
    var modal = document.getElementById('modalEditarCuenta');
    var form = modal ? modal.querySelector('form') : null;
    if (!modal || !form) return;

    var modalNueva = document.getElementById('modalNuevaCuenta');
    var formNueva = modalNueva ? modalNueva.querySelector('form') : null;
    document.querySelectorAll('.btn-crear-hijo').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            var row = this.closest('.pcm-row');
            if (!row || !formNueva) return;
            formNueva.querySelector('#new-codigo-padre').value = row.dataset.codigoPadre || '';
            formNueva.querySelector('#new-nivel-padre').value = row.dataset.nivelPadre || '1';
            formNueva.querySelector('#new-nivel-label').textContent = row.dataset.nivelHijo || '2';
            formNueva.querySelector('#new-padre-info').textContent = (row.dataset.codigo || '') + ' - ' + (row.dataset.nombre || '');
            formNueva.querySelector('#new-codigo-asignado').textContent = row.dataset.siguienteCodigo || '';
            formNueva.querySelector('#new-nombre').value = '';
            new bootstrap.Modal(modalNueva).show();
        });
    });

    document.querySelectorAll('.pcm-row').forEach(function(row) {
        row.addEventListener('click', function(e) {
            if (e.target.closest('.btn-crear-hijo, form')) return;
            form.querySelector('#edit-id').value = this.dataset.id || '';
            var codigoVal = (this.dataset.codigo || '').replace(/&quot;/g, '"');
            form.querySelector('#edit-codigo').value = codigoVal;
            var codigoDisp = form.querySelector('#edit-codigo-display');
            if (codigoDisp) codigoDisp.value = codigoVal;
            var nivelVal = this.dataset.nivel || '1';
            form.querySelector('#edit-nivel').value = nivelVal;
            var disp = form.querySelector('#edit-nivel-display');
            if (disp) disp.value = nivelVal;
            form.querySelector('#edit-nombre').value = (this.dataset.nombre || '').replace(/&quot;/g, '"');
            form.querySelector('#edit-codigo-sri').value = this.dataset.codigoSri || '';
            form.querySelector('#edit-supercias-esf').value = this.dataset.superciasEsf || '';
            form.querySelector('#edit-supercias-eri').value = this.dataset.superciasEri || '';
            form.querySelector('#edit-supercias-ecp-codigo').value = this.dataset.superciasEcpCodigo || '';
            form.querySelector('#edit-supercias-ecp-subcodigo').value = this.dataset.superciasEcpSubcodigo || '';
            form.querySelector('#edit-status').checked = this.dataset.status === '1';
            new bootstrap.Modal(modal).show();
        });
        row.addEventListener('keydown', function(e) {
            if (e.target.closest('.btn-crear-hijo, form')) return;
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); this.click(); }
        });
    });
})();
</script>
