<?php
/** @var string $titulo */
/** @var array $rows */
/** @var string $ordenCol */
/** @var string $ordenDir */
/** @var string $buscar */
$base = BASE_URL;
$rows = $rows ?? [];
$ordenCol = $ordenCol ?? 'ano';
$ordenDir = $ordenDir ?? 'desc';
$buscar = $buscar ?? '';
$msg = $_SESSION['salarios_msg'] ?? null;
unset($_SESSION['salarios_msg']);

function thSort($base, $col, $label, $ordenCol, $ordenDir, $buscar, $align = '') {
    $dir = ($ordenCol === $col && strtolower($ordenDir) === 'asc') ? 'desc' : 'asc';
    $url = rtrim($base, '/') . '/config/salarios?sort=' . urlencode($col) . '&dir=' . $dir;
    if ($buscar !== '') $url .= '&b=' . urlencode($buscar);
    $cls = trim('text-decoration-none ' . $align);
    return '<a href="' . htmlspecialchars($url) . '" class="' . $cls . '" title="Ordenar por ' . htmlspecialchars($label) . '">' . htmlspecialchars($label) . '</a>';
}
?>
<style>
.salario-row { cursor: pointer; }
.salario-row:hover { background-color: rgba(0,0,0,.04); }
.salarios-scroll { max-height: calc(100vh - 280px); overflow-y: auto; }
.salarios-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
</style>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-cash-coin"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">Configuración SBU, horas y porcentajes por año. Clic en fila para editar.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoSalario"><i class="bi bi-plus-lg"></i> Crear nuevo</button>
    </div>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= htmlspecialchars($msg[0]) ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($msg[1]) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<form method="GET" action="<?= rtrim($base, '/') ?>/config/salarios" class="mb-3">
    <input type="hidden" name="sort" value="<?= htmlspecialchars($ordenCol) ?>">
    <input type="hidden" name="dir" value="<?= htmlspecialchars($ordenDir) ?>">
    <div class="input-group input-group-sm" style="max-width: 320px;">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" name="b" class="form-control" placeholder="Buscar por año o SBU..." value="<?= htmlspecialchars($buscar) ?>">
        <button type="submit" class="btn btn-outline-primary">Buscar</button>
        <?php if ($buscar !== ''): ?>
        <a href="<?= rtrim($base, '/') ?>/config/salarios?sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>" class="btn btn-outline-secondary">Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<div class="card cmg-table-card">
    <div class="card-body p-0">
        <div class="salarios-scroll">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= thSort($base, 'ano', 'Año', $ordenCol, $ordenDir, $buscar) ?></th>
                        <th class="text-end"><?= thSort($base, 'sbu', 'SBU', $ordenCol, $ordenDir, $buscar, 'text-end d-inline-block') ?></th>
                        <th class="text-end">Factor Hora normal</th>
                        <th class="text-end">H. nocturna %</th>
                        <th class="text-end">H. suplem. %</th>
                        <th class="text-end">H. extraord. %</th>
                        <th class="text-end">Fondo res. %</th>
                        <th class="text-end">Ext. cónyuge %</th>
                        <th class="text-end">Aporte patr. %</th>
                        <th class="text-end">Aporte pers. %</th>
                        <th class="text-end">Adicional % (secap-iece)</th>
                        <th class="text-center"><?= thSort($base, 'status', 'Estado', $ordenCol, $ordenDir, $buscar, 'text-center d-inline-block') ?></th>
                        <th class="text-end" style="width: 80px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <?php
                    $id = (int)($r['id'] ?? $r['id_salario'] ?? 0);
                    $status = (int)($r['status'] ?? 1);
                    $ano = (int)($r['ano'] ?? date('Y'));
                    $sbu = (float)($r['sbu'] ?? 0);
                    $horaNormal = (float)($r['hora_normal'] ?? 0);
                    $horaNocturna = (float)($r['hora_nocturna'] ?? 0);
                    $horaSuplementaria = (float)($r['hora_suplementaria'] ?? 0);
                    $horaExtraordinaria = (float)($r['hora_extraordinaria'] ?? 0);
                    $fondoReserva = (float)($r['fondo_reserva'] ?? 0);
                    $aportePersonal = (float)($r['aporte_personal'] ?? 0);
                    $aportePatronal = (float)($r['aporte_patronal'] ?? 0);
                    $extConyugue = (float)($r['ext_conyugue'] ?? 0);
                    $adicional = (float)($r['adicional'] ?? 0);
                    ?>
                    <tr class="salario-row" role="button" tabindex="0" data-id="<?= $id ?>"
                        data-ano="<?= $ano ?>"
                        data-sbu="<?= $sbu ?>"
                        data-hora-normal="<?= $horaNormal ?>"
                        data-hora-nocturna="<?= $horaNocturna ?>"
                        data-hora-suplementaria="<?= $horaSuplementaria ?>"
                        data-hora-extraordinaria="<?= $horaExtraordinaria ?>"
                        data-fondo-reserva="<?= $fondoReserva ?>"
                        data-aporte-personal="<?= $aportePersonal ?>"
                        data-aporte-patronal="<?= $aportePatronal ?>"
                        data-ext-conyugue="<?= $extConyugue ?>"
                        data-adicional="<?= $adicional ?>"
                        data-status="<?= $status ?>">
                        <td><strong><?= $ano ?></strong></td>
                        <td class="text-end"><?= number_format($sbu, 2, ',', '.') ?></td>
                        <td class="text-end"><?= number_format($horaNormal, 2, ',', '.') ?></td>
                        <td class="text-end"><?= number_format($horaNocturna, 2, ',', '.') ?>%</td>
                        <td class="text-end"><?= number_format($horaSuplementaria, 2, ',', '.') ?>%</td>
                        <td class="text-end"><?= number_format($horaExtraordinaria, 2, ',', '.') ?>%</td>
                        <td class="text-end"><?= number_format($fondoReserva, 2, ',', '.') ?>%</td>
                        <td class="text-end"><?= number_format($extConyugue, 2, ',', '.') ?>%</td>
                        <td class="text-end"><?= number_format($aportePatronal, 2, ',', '.') ?>%</td>
                        <td class="text-end"><?= number_format($aportePersonal, 2, ',', '.') ?>%</td>
                        <td class="text-end"><?= number_format($adicional, 2, ',', '.') ?>%</td>
                        <td class="text-center">
                            <?php if ($status): ?>
                            <span class="badge bg-success">Activo</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <form method="POST" action="<?= $base ?>/config/salariosDelete" class="d-inline" onsubmit="return confirm('¿Eliminar esta configuración de salarios para el año <?= $ano ?>?');" onclick="event.stopPropagation();">
                                <input type="hidden" name="id" value="<?= $id ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1" title="Eliminar"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (empty($rows)): ?>
            <p class="text-muted text-center py-4 mb-0">No hay salarios configurados.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$currentYear = (int) date('Y');
$anosOptions = range($currentYear + 2, $currentYear - 5);
?>

<!-- Modal Nuevo salario -->
<div class="modal fade" id="modalNuevoSalario" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/salariosStore" id="form-crear-salario">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Nuevo salario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="crear-salario-msg" class="d-none"></div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="new-ano" class="form-label">Año <span class="text-danger">*</span></label>
                            <select id="new-ano" name="ano" class="form-select form-select-sm" required>
                                <?php foreach ($anosOptions as $a): ?>
                                <option value="<?= $a ?>"<?= $a === $currentYear ? ' selected' : '' ?>><?= $a ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="new-sbu" class="form-label">SBU (Salario Básico Unificado) <span class="text-danger">*</span></label>
                            <input type="number" id="new-sbu" name="sbu" class="form-control form-control-sm" step="0.01" min="0" required placeholder="0.00" value="">
                        </div>
                        <div class="col-md-4">
                            <label for="new-status" class="form-label">Estado</label>
                            <select id="new-status" name="status" class="form-select form-select-sm">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="new-hora-normal" class="form-label">Factor Hora normal</label>
                            <input type="number" id="new-hora-normal" name="hora_normal" class="form-control form-control-sm" step="0.01" min="0" placeholder="0.00" value="0">
                        </div>
                        <div class="col-md-4">
                            <label for="new-hora-nocturna" class="form-label">Hora nocturna %</label>
                            <input type="number" id="new-hora-nocturna" name="hora_nocturna" class="form-control form-control-sm" step="0.01" min="0" placeholder="0" value="0">
                        </div>
                        <div class="col-md-4">
                            <label for="new-hora-suplementaria" class="form-label">Hora suplementaria %</label>
                            <input type="number" id="new-hora-suplementaria" name="hora_suplementaria" class="form-control form-control-sm" step="0.01" min="0" placeholder="0" value="0">
                        </div>
                        <div class="col-md-4">
                            <label for="new-hora-extraordinaria" class="form-label">Hora extraordinaria %</label>
                            <input type="number" id="new-hora-extraordinaria" name="hora_extraordinaria" class="form-control form-control-sm" step="0.01" min="0" placeholder="0" value="0">
                        </div>
                        <div class="col-md-4">
                            <label for="new-fondo-reserva" class="form-label">Fondo de reserva %</label>
                            <input type="number" id="new-fondo-reserva" name="fondo_reserva" class="form-control form-control-sm" step="0.01" min="0" placeholder="0" value="0">
                        </div>
                        <div class="col-md-4">
                            <label for="new-ext-conyugue" class="form-label">Ext. cónyuge %</label>
                            <input type="number" id="new-ext-conyugue" name="ext_conyugue" class="form-control form-control-sm" step="0.01" min="0" placeholder="0" value="0">
                        </div>
                        <div class="col-md-4">
                            <label for="new-aporte-patronal" class="form-label">Aporte patronal %</label>
                            <input type="number" id="new-aporte-patronal" name="aporte_patronal" class="form-control form-control-sm" step="0.01" min="0" placeholder="0" value="0">
                        </div>
                        <div class="col-md-4">
                            <label for="new-aporte-personal" class="form-label">Aporte personal %</label>
                            <input type="number" id="new-aporte-personal" name="aporte_personal" class="form-control form-control-sm" step="0.01" min="0" placeholder="0" value="0">
                        </div>
                        <div class="col-md-4">
                            <label for="new-adicional" class="form-label">Adicional % <span class="text-muted fw-normal">(secap-iece)</span></label>
                            <input type="number" id="new-adicional" name="adicional" class="form-control form-control-sm" step="0.01" min="0" placeholder="0" value="0">
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

<!-- Modal Editar salario -->
<div class="modal fade" id="modalEditarSalario" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/salariosUpdate" id="form-editar-salario">
                <input type="hidden" name="id" id="edit-id" value="">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Editar salario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="editar-salario-msg" class="d-none"></div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="edit-ano" class="form-label">Año <span class="text-danger">*</span></label>
                            <input type="number" id="edit-ano" name="ano" class="form-control form-control-sm" min="1900" max="2100" required placeholder="2025" value="">
                        </div>
                        <div class="col-md-4">
                            <label for="edit-sbu" class="form-label">SBU (Salario Básico Unificado) <span class="text-danger">*</span></label>
                            <input type="number" id="edit-sbu" name="sbu" class="form-control form-control-sm" step="0.01" min="0" required placeholder="0.00" value="">
                        </div>
                        <div class="col-md-4">
                            <label for="edit-status" class="form-label">Estado</label>
                            <select id="edit-status" name="status" class="form-select form-select-sm">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="edit-hora-normal" class="form-label">Factor Hora normal</label>
                            <input type="number" id="edit-hora-normal" name="hora_normal" class="form-control form-control-sm" step="0.01" min="0" placeholder="0.00" value="0">
                        </div>
                        <div class="col-md-4">
                            <label for="edit-hora-nocturna" class="form-label">Hora nocturna %</label>
                            <input type="number" id="edit-hora-nocturna" name="hora_nocturna" class="form-control form-control-sm" step="0.01" min="0" placeholder="0" value="0">
                        </div>
                        <div class="col-md-4">
                            <label for="edit-hora-suplementaria" class="form-label">Hora suplementaria %</label>
                            <input type="number" id="edit-hora-suplementaria" name="hora_suplementaria" class="form-control form-control-sm" step="0.01" min="0" placeholder="0" value="0">
                        </div>
                        <div class="col-md-4">
                            <label for="edit-hora-extraordinaria" class="form-label">Hora extraordinaria %</label>
                            <input type="number" id="edit-hora-extraordinaria" name="hora_extraordinaria" class="form-control form-control-sm" step="0.01" min="0" placeholder="0" value="0">
                        </div>
                        <div class="col-md-4">
                            <label for="edit-fondo-reserva" class="form-label">Fondo de reserva %</label>
                            <input type="number" id="edit-fondo-reserva" name="fondo_reserva" class="form-control form-control-sm" step="0.01" min="0" placeholder="0" value="0">
                        </div>
                        <div class="col-md-4">
                            <label for="edit-ext-conyugue" class="form-label">Ext. cónyuge %</label>
                            <input type="number" id="edit-ext-conyugue" name="ext_conyugue" class="form-control form-control-sm" step="0.01" min="0" placeholder="0" value="0">
                        </div>
                        <div class="col-md-4">
                            <label for="edit-aporte-patronal" class="form-label">Aporte patronal %</label>
                            <input type="number" id="edit-aporte-patronal" name="aporte_patronal" class="form-control form-control-sm" step="0.01" min="0" placeholder="0" value="0">
                        </div>
                        <div class="col-md-4">
                            <label for="edit-aporte-personal" class="form-label">Aporte personal %</label>
                            <input type="number" id="edit-aporte-personal" name="aporte_personal" class="form-control form-control-sm" step="0.01" min="0" placeholder="0" value="0">
                        </div>
                        <div class="col-md-4">
                            <label for="edit-adicional" class="form-label">Adicional % <span class="text-muted fw-normal">(secap-iece)</span></label>
                            <input type="number" id="edit-adicional" name="adicional" class="form-control form-control-sm" step="0.01" min="0" placeholder="0" value="0">
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
    var base = '<?= rtrim($base ?? BASE_URL, "/") ?>';
    var modalEditar = document.getElementById('modalEditarSalario');
    var modalNuevo = document.getElementById('modalNuevoSalario');

    function mostrarMsgForm(containerId, tipo, texto) {
        var el = document.getElementById(containerId);
        if (!el) return;
        el.className = 'alert alert-' + (tipo === 'error' ? 'danger' : 'success') + ' alert-dismissible fade show mb-3';
        el.innerHTML = texto + ' <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>';
        el.classList.remove('d-none');
    }
    function ocultarMsgForm(containerId) {
        var el = document.getElementById(containerId);
        if (el) el.classList.add('d-none');
    }
    function enviarFormAjax(form, msgContainerId, url) {
        return fetch(url, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.ok) {
                mostrarMsgForm(msgContainerId, 'success', res.msg || 'Guardado correctamente.');
                setTimeout(function() { window.location.href = base + '/config/salarios'; }, 1500);
            } else {
                mostrarMsgForm(msgContainerId, 'error', res.error || 'Error desconocido.');
                return Promise.reject();
            }
        })
        .catch(function(err) {
            mostrarMsgForm(msgContainerId, 'error', err.message || 'Error de conexión. Intente de nuevo.');
            return Promise.reject();
        });
    }

    if (modalEditar) {
        var formEditar = modalEditar.querySelector('#form-editar-salario');
        document.querySelectorAll('.salario-row').forEach(function(row) {
            row.addEventListener('click', function() {
                ocultarMsgForm('editar-salario-msg');
                formEditar.querySelector('#edit-id').value = this.dataset.id || '';
                formEditar.querySelector('#edit-ano').value = this.dataset.ano || '';
                formEditar.querySelector('#edit-sbu').value = this.dataset.sbu || '0';
                formEditar.querySelector('#edit-hora-normal').value = this.dataset.horaNormal || '0';
                formEditar.querySelector('#edit-hora-nocturna').value = this.dataset.horaNocturna || '0';
                formEditar.querySelector('#edit-hora-suplementaria').value = this.dataset.horaSuplementaria || '0';
                formEditar.querySelector('#edit-hora-extraordinaria').value = this.dataset.horaExtraordinaria || '0';
                formEditar.querySelector('#edit-fondo-reserva').value = this.dataset.fondoReserva || '0';
                formEditar.querySelector('#edit-aporte-personal').value = this.dataset.aportePersonal || '0';
                formEditar.querySelector('#edit-aporte-patronal').value = this.dataset.aportePatronal || '0';
                formEditar.querySelector('#edit-ext-conyugue').value = this.dataset.extConyugue || '0';
                formEditar.querySelector('#edit-adicional').value = this.dataset.adicional || '0';
                formEditar.querySelector('#edit-status').value = this.dataset.status || '1';
                new bootstrap.Modal(modalEditar).show();
            });
            row.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); this.click(); }
            });
        });
        if (formEditar) {
            formEditar.addEventListener('submit', function(e) {
                e.preventDefault();
                ocultarMsgForm('editar-salario-msg');
                var btn = formEditar.querySelector('button[type="submit"]');
                var txtOrig = btn ? btn.innerHTML : '';
                if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...'; }
                enviarFormAjax(formEditar, 'editar-salario-msg', base + '/config/salariosUpdate')
                    .catch(function() { if (btn) { btn.disabled = false; btn.innerHTML = txtOrig; } });
            });
        }
        modalEditar.addEventListener('show.bs.modal', function() { ocultarMsgForm('editar-salario-msg'); });
    }

    if (modalNuevo) {
        modalNuevo.addEventListener('show.bs.modal', function() { ocultarMsgForm('crear-salario-msg'); });
        var formCrear = document.getElementById('form-crear-salario');
        if (formCrear) {
            formCrear.addEventListener('submit', function(e) {
                e.preventDefault();
                ocultarMsgForm('crear-salario-msg');
                var btn = formCrear.querySelector('button[type="submit"]');
                var txtOrig = btn ? btn.innerHTML : '';
                if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...'; }
                enviarFormAjax(formCrear, 'crear-salario-msg', base + '/config/salariosStore')
                    .catch(function() { if (btn) { btn.disabled = false; btn.innerHTML = txtOrig; } });
            });
        }
    }
})();
</script>
