<?php
/** @var string $titulo */
/** @var array $rows */
/** @var string $ordenCol */
/** @var string $ordenDir */
/** @var string $buscar */
$base = BASE_URL;
$rows = $rows ?? [];
$ordenCol = $ordenCol ?? 'codigo_ret';
$ordenDir = $ordenDir ?? 'asc';
$buscar = $buscar ?? '';
$msg = $_SESSION['retenciones_msg'] ?? null;
unset($_SESSION['retenciones_msg']);

function fmtFecha(?string $f): string {
    if ($f === null || $f === '' || $f === '0000-00-00' || $f === '0000-00-00 00:00:00') return '';
    $d = @strtotime($f);
    if (!$d || $d <= 0) return '';
    return date('d-m-Y', $d);
}

function thSort($base, $col, $label, $ordenCol, $ordenDir, $buscar, $align = '') {
    $dir = ($ordenCol === $col && strtolower($ordenDir) === 'asc') ? 'desc' : 'asc';
    $url = rtrim($base, '/') . '/config/retenciones-sri?sort=' . urlencode($col) . '&dir=' . $dir;
    if ($buscar !== '') $url .= '&b=' . urlencode($buscar);
    $cls = trim('text-decoration-none ' . $align);
    return '<a href="' . htmlspecialchars($url) . '" class="' . $cls . '" title="Ordenar por ' . htmlspecialchars($label) . '">' . htmlspecialchars($label) . '</a>';
}
?>
<style>
.retencion-row { cursor: pointer; }
.retencion-row:hover { background-color: rgba(0,0,0,.04); }
.retenciones-sri-header { flex-shrink: 0; }
.retenciones-sri-scroll { max-height: calc(100vh - 280px); overflow-y: auto; }
.retenciones-sri-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
</style>
<div class="retenciones-sri-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-receipt"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">Haga clic en una fila para editar. Retenciones del SRI.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevaRetencion"><i class="bi bi-plus-lg"></i> Crear nuevo</button>
    </div>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= htmlspecialchars($msg[0]) ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($msg[1]) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<form method="GET" action="<?= rtrim($base, '/') ?>/config/retenciones-sri" class="mb-3">
    <input type="hidden" name="sort" value="<?= htmlspecialchars($ordenCol) ?>">
    <input type="hidden" name="dir" value="<?= htmlspecialchars($ordenDir) ?>">
    <div class="input-group input-group-sm" style="max-width: 320px;">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" name="b" class="form-control" placeholder="Buscar en código, descripción, impuesto..." value="<?= htmlspecialchars($buscar) ?>">
        <button type="submit" class="btn btn-outline-primary">Buscar</button>
        <?php if ($buscar !== ''): ?>
        <a href="<?= rtrim($base, '/') ?>/config/retenciones-sri?sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>" class="btn btn-outline-secondary">Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<div class="card cmg-table-card">
    <div class="card-body p-0">
        <div class="retenciones-sri-scroll">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= thSort($base, 'codigo_ret', 'Código', $ordenCol, $ordenDir, $buscar) ?></th>
                        <th><?= thSort($base, 'concepto_ret', 'Descripción', $ordenCol, $ordenDir, $buscar) ?></th>
                        <th class="text-end"><?= thSort($base, 'porcentaje_ret', '%', $ordenCol, $ordenDir, $buscar, 'text-end d-inline-block') ?></th>
                        <th><?= thSort($base, 'impuesto_ret', 'Impuesto', $ordenCol, $ordenDir, $buscar) ?></th>
                        <th><?= thSort($base, 'cod_anexo_ret', 'Cód. ATS', $ordenCol, $ordenDir, $buscar) ?></th>
                        <th class="text-center"><?= thSort($base, 'status', 'Estado', $ordenCol, $ordenDir, $buscar, 'text-center d-inline-block') ?></th>
                        <th><?= thSort($base, 'desde', 'Desde', $ordenCol, $ordenDir, $buscar) ?></th>
                        <th><?= thSort($base, 'hasta', 'Hasta', $ordenCol, $ordenDir, $buscar) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <?php
                    $id = (int)($r['id'] ?? $r['id_ret'] ?? 0);
                    $status = (int)($r['status'] ?? 1);
                    $desde = $r['desde'] ?? '';
                    $hasta = $r['hasta'] ?? '';
                    ?>
                    <tr class="retencion-row" role="button" tabindex="0" data-id="<?= $id ?>"
                        data-codigo="<?= htmlspecialchars($r['codigo_ret'] ?? '') ?>"
                        data-concepto="<?= htmlspecialchars($r['concepto_ret'] ?? '') ?>"
                        data-porcentaje="<?= htmlspecialchars($r['porcentaje_ret'] ?? '') ?>"
                        data-impuesto="<?= htmlspecialchars($r['impuesto_ret'] ?? 'RENTA') ?>"
                        data-codanexo="<?= htmlspecialchars($r['cod_anexo_ret'] ?? '') ?>"
                        data-status="<?= $status ?>"
                        data-desde="<?= htmlspecialchars($desde) ?>"
                        data-hasta="<?= htmlspecialchars($hasta) ?>">
                        <td><code><?= htmlspecialchars($r['codigo_ret'] ?? '') ?></code></td>
                        <td><?= htmlspecialchars($r['concepto_ret'] ?? '') ?></td>
                        <td class="text-end"><?= htmlspecialchars($r['porcentaje_ret'] ?? '') ?></td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($r['impuesto_ret'] ?? '') ?></span></td>
                        <td><?= htmlspecialchars($r['cod_anexo_ret'] ?? '') ?></td>
                        <td class="text-center">
                            <?php if ($status): ?>
                            <span class="badge bg-success">Activo</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars(fmtFecha($desde) ?: '-') ?></td>
                        <td><?= htmlspecialchars(fmtFecha($hasta) ?: '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (empty($rows)): ?>
            <p class="text-muted text-center py-4 mb-0">No hay retenciones registradas.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Nueva retención -->
<div class="modal fade" id="modalNuevaRetencion" tabindex="-1" aria-labelledby="modalNuevaRetencionLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/retencionesSriStore">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalNuevaRetencionLabel"><i class="bi bi-plus-circle"></i> Nueva retención SRI</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">No se permite duplicar: mismo código+descripción+porcentaje, ni misma descripción+vigencia (desde-hasta).</p>
                    <div class="row g-3">
                        <div class="col-md-2">
                            <label for="new-codigo_ret" class="form-label">Código <span class="text-danger">*</span></label>
                            <input type="text" id="new-codigo_ret" name="codigo_ret" class="form-control" required placeholder="Ej: 301">
                        </div>
                        <div class="col-md-10">
                            <label for="new-concepto_ret" class="form-label">Descripción</label>
                            <input type="text" id="new-concepto_ret" name="concepto_ret" class="form-control" placeholder="Concepto de la retención">
                        </div>
                        <div class="col-md-4">
                            <label for="new-porcentaje_ret" class="form-label">Porcentaje %</label>
                            <input type="number" id="new-porcentaje_ret" name="porcentaje_ret" class="form-control" step="0.01" min="0" max="100" placeholder="0" value="0">
                        </div>
                        <div class="col-md-4">
                            <label for="new-impuesto_ret" class="form-label">Impuesto</label>
                            <select id="new-impuesto_ret" name="impuesto_ret" class="form-select">
                                <option value="RENTA">RENTA</option>
                                <option value="IVA">IVA</option>
                                <option value="ISD">ISD</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="new-cod_anexo_ret" class="form-label">Código ATS</label>
                            <input type="text" id="new-cod_anexo_ret" name="cod_anexo_ret" class="form-control" placeholder="Código anexo">
                        </div>
                        <div class="col-md-4">
                            <label for="new-status" class="form-label">Estado</label>
                            <select id="new-status" name="status" class="form-select">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="new-desde" class="form-label">Desde (vigencia inicial)</label>
                            <input type="date" id="new-desde" name="desde" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label for="new-hasta" class="form-label">Hasta (vigencia final)</label>
                            <input type="date" id="new-hasta" name="hasta" class="form-control">
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

<!-- Modal Editar retención -->
<div class="modal fade" id="modalEditarRetencion" tabindex="-1" aria-labelledby="modalEditarRetencionLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/retencionesSriUpdate">
                <input type="hidden" name="id" id="edit-id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditarRetencionLabel"><i class="bi bi-pencil"></i> Editar retención SRI</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-2">
                            <label for="edit-codigo_ret" class="form-label">Código <span class="text-danger">*</span></label>
                            <input type="text" id="edit-codigo_ret" name="codigo_ret" class="form-control" required placeholder="Ej: 301">
                        </div>
                        <div class="col-md-10">
                            <label for="edit-concepto_ret" class="form-label">Descripción</label>
                            <input type="text" id="edit-concepto_ret" name="concepto_ret" class="form-control" placeholder="Concepto de la retención">
                        </div>
                        <div class="col-md-4">
                            <label for="edit-porcentaje_ret" class="form-label">Porcentaje %</label>
                            <input type="number" id="edit-porcentaje_ret" name="porcentaje_ret" class="form-control" step="0.01" min="0" max="100" placeholder="0">
                        </div>
                        <div class="col-md-4">
                            <label for="edit-impuesto_ret" class="form-label">Impuesto</label>
                            <select id="edit-impuesto_ret" name="impuesto_ret" class="form-select">
                                <option value="RENTA">RENTA</option>
                                <option value="IVA">IVA</option>
                                <option value="ISD">ISD</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="edit-cod_anexo_ret" class="form-label">Código ATS</label>
                            <input type="text" id="edit-cod_anexo_ret" name="cod_anexo_ret" class="form-control" placeholder="Código anexo">
                        </div>
                        <div class="col-md-4">
                            <label for="edit-status" class="form-label">Estado</label>
                            <select id="edit-status" name="status" class="form-select">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="edit-desde" class="form-label">Desde (vigencia inicial)</label>
                            <input type="date" id="edit-desde" name="desde" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label for="edit-hasta" class="form-label">Hasta (vigencia final)</label>
                            <input type="date" id="edit-hasta" name="hasta" class="form-control">
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
    function toYyyyMmDd(s) {
        if (!s || s === '0000-00-00') return '';
        var m = s.match(/^(\d{4})-(\d{1,2})-(\d{1,2})/);
        if (m) return m[1] + '-' + m[2].padStart(2,'0') + '-' + m[3].padStart(2,'0');
        m = s.match(/^(\d{1,2})-(\d{1,2})-(\d{4})/);
        if (m) return m[3] + '-' + m[2].padStart(2,'0') + '-' + m[1].padStart(2,'0');
        return s;
    }
    var modal = document.getElementById('modalEditarRetencion');
    var form = modal ? modal.querySelector('form') : null;
    if (!modal || !form) return;

    document.querySelectorAll('.retencion-row').forEach(function(row) {
        row.addEventListener('click', function() {
            form.querySelector('#edit-id').value = this.dataset.id || '';
            form.querySelector('#edit-codigo_ret').value = this.dataset.codigo || '';
            form.querySelector('#edit-concepto_ret').value = this.dataset.concepto || '';
            form.querySelector('#edit-porcentaje_ret').value = this.dataset.porcentaje || '';
            form.querySelector('#edit-impuesto_ret').value = this.dataset.impuesto || 'RENTA';
            form.querySelector('#edit-cod_anexo_ret').value = this.dataset.codanexo || '';
            form.querySelector('#edit-status').value = this.dataset.status || '1';
            form.querySelector('#edit-desde').value = toYyyyMmDd(this.dataset.desde || '');
            form.querySelector('#edit-hasta').value = toYyyyMmDd(this.dataset.hasta || '');
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
