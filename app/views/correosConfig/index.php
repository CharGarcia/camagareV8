<?php
/** @var string $titulo */
/** @var array $rows */
/** @var string $ordenCol */
/** @var string $ordenDir */
/** @var string $buscar */
/** @var array $codigosSugeridos */
$base = BASE_URL;
$rows = $rows ?? [];
$ordenCol = $ordenCol ?? 'codigo';
$ordenDir = $ordenDir ?? 'asc';
$buscar = $buscar ?? '';
$codigosSugeridos = $codigosSugeridos ?? [];
$msg = $_SESSION['correos_config_msg'] ?? null;
unset($_SESSION['correos_config_msg']);

function thSort($base, $col, $label, $ordenCol, $ordenDir, $buscar, $align = '') {
    $dir = ($ordenCol === $col && strtolower($ordenDir) === 'asc') ? 'desc' : 'asc';
    $url = rtrim($base, '/') . '/config/correos-config?sort=' . urlencode($col) . '&dir=' . $dir;
    if ($buscar !== '') $url .= '&b=' . urlencode($buscar);
    $cls = trim('text-decoration-none ' . $align);
    return '<a href="' . htmlspecialchars($url) . '" class="' . $cls . '" title="Ordenar por ' . htmlspecialchars($label) . '">' . htmlspecialchars($label) . '</a>';
}
?>
<style>
.correo-row { cursor: pointer; }
.correo-row:hover { background-color: rgba(0,0,0,.04); }
.correos-scroll { max-height: calc(100vh - 280px); overflow-y: auto; }
.correos-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
</style>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-envelope-at"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">Correos por propósito: recuperar contraseña, notificaciones, cobros, etc. Clic en fila para editar.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoCorreo"><i class="bi bi-plus-lg"></i> Crear nuevo</button>
    </div>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= htmlspecialchars($msg[0]) ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($msg[1]) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<form method="GET" action="<?= rtrim($base, '/') ?>/config/correos-config" class="mb-3">
    <input type="hidden" name="sort" value="<?= htmlspecialchars($ordenCol) ?>">
    <input type="hidden" name="dir" value="<?= htmlspecialchars($ordenDir) ?>">
    <div class="input-group input-group-sm" style="max-width: 320px;">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" name="b" class="form-control" placeholder="Buscar por código, nombre o email..." value="<?= htmlspecialchars($buscar) ?>">
        <button type="submit" class="btn btn-outline-primary">Buscar</button>
        <?php if ($buscar !== ''): ?>
        <a href="<?= rtrim($base, '/') ?>/config/correos-config?sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>" class="btn btn-outline-secondary">Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<div class="card cmg-table-card">
    <div class="card-body p-0">
        <div class="correos-scroll">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= thSort($base, 'codigo', 'Código', $ordenCol, $ordenDir, $buscar) ?></th>
                        <th><?= thSort($base, 'nombre', 'Nombre', $ordenCol, $ordenDir, $buscar) ?></th>
                        <th><?= thSort($base, 'email', 'Email', $ordenCol, $ordenDir, $buscar) ?></th>
                        <th>Host SMTP</th>
                        <th class="text-center">Puerto</th>
                        <th>Encryption</th>
                        <th class="text-center"><?= thSort($base, 'status', 'Estado', $ordenCol, $ordenDir, $buscar, 'text-center d-inline-block') ?></th>
                        <th class="text-end" style="width: 80px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <?php
                    $id = (int)($r['id'] ?? $r['id_correo_config'] ?? 0);
                    $status = (int)($r['status'] ?? 1);
                    ?>
                    <tr class="correo-row" role="button" tabindex="0" data-id="<?= $id ?>"
                        data-codigo="<?= htmlspecialchars($r['codigo'] ?? '') ?>"
                        data-nombre="<?= htmlspecialchars($r['nombre'] ?? '') ?>"
                        data-email="<?= htmlspecialchars($r['email'] ?? '') ?>"
                        data-nombre-remitente="<?= htmlspecialchars($r['nombre_remitente'] ?? '') ?>"
                        data-host-smtp="<?= htmlspecialchars($r['host_smtp'] ?? '') ?>"
                        data-puerto-smtp="<?= htmlspecialchars($r['puerto_smtp'] ?? '587') ?>"
                        data-usuario-smtp="<?= htmlspecialchars($r['usuario_smtp'] ?? '') ?>"
                        data-encryption="<?= htmlspecialchars($r['encryption'] ?? 'tls') ?>"
                        data-status="<?= $status ?>">
                        <td><code><?= htmlspecialchars($r['codigo'] ?? '') ?></code></td>
                        <td><?= htmlspecialchars($r['nombre'] ?? '') ?></td>
                        <td><small><?= htmlspecialchars($r['email'] ?? '') ?></small></td>
                        <td><small><?= htmlspecialchars($r['host_smtp'] ?? '') ?: '—' ?></small></td>
                        <td class="text-center"><?= (int)($r['puerto_smtp'] ?? 0) ?: '—' ?></td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($r['encryption'] ?? '') ?: 'none' ?></span></td>
                        <td class="text-center">
                            <?php if ($status): ?>
                            <span class="badge bg-success">Activo</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <form method="POST" action="<?= $base ?>/config/correosConfigDelete" class="d-inline" onsubmit="return confirm('¿Eliminar esta configuración de correo?');" onclick="event.stopPropagation();">
                                <input type="hidden" name="id" value="<?= $id ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1" title="Eliminar"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (empty($rows)): ?>
            <p class="text-muted text-center py-4 mb-0">No hay configuraciones de correo. Cree una para recuperar contraseña, notificaciones, cobros, etc.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Nuevo correo -->
<div class="modal fade" id="modalNuevoCorreo" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/correosConfigStore" id="form-crear-correo">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Nueva configuración de correo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="crear-correo-msg" class="d-none"></div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="new-codigo" class="form-label">Código <span class="text-danger">*</span></label>
                            <input type="text" id="new-codigo" name="codigo" class="form-control form-control-sm" required placeholder="Ej: recuperar_password" list="datalist-codigos" pattern="[a-z0-9_]+" title="Solo letras minúsculas, números y guiones bajos">
                            <datalist id="datalist-codigos">
                                <?php foreach ($codigosSugeridos as $cod => $nom): ?>
                                <option value="<?= htmlspecialchars($cod) ?>" label="<?= htmlspecialchars($nom) ?>">
                                <?php endforeach; ?>
                            </datalist>
                            <small class="text-muted">Identificador único que usará la aplicación</small>
                        </div>
                        <div class="col-md-6">
                            <label for="new-nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                            <input type="text" id="new-nombre" name="nombre" class="form-control form-control-sm" required placeholder="Ej: Recuperación de contraseña">
                        </div>
                        <div class="col-md-6">
                            <label for="new-email" class="form-label">Correo remitente <span class="text-danger">*</span></label>
                            <input type="email" id="new-email" name="email" class="form-control form-control-sm" required placeholder="correo@ejemplo.com">
                        </div>
                        <div class="col-md-6">
                            <label for="new-nombre-remitente" class="form-label">Nombre del remitente</label>
                            <input type="text" id="new-nombre-remitente" name="nombre_remitente" class="form-control form-control-sm" placeholder="Mi Sistema">
                        </div>
                        <div class="col-md-6">
                            <label for="new-host-smtp" class="form-label">Host SMTP <span class="text-danger">*</span></label>
                            <input type="text" id="new-host-smtp" name="host_smtp" class="form-control form-control-sm" required value="smtp.gmail.com" placeholder="smtp.gmail.com">
                        </div>
                        <div class="col-md-3">
                            <label for="new-puerto-smtp" class="form-label">Puerto</label>
                            <input type="number" id="new-puerto-smtp" name="puerto_smtp" class="form-control form-control-sm" min="1" max="65535" value="587">
                        </div>
                        <div class="col-md-3">
                            <label for="new-encryption" class="form-label">Cifrado</label>
                            <select id="new-encryption" name="encryption" class="form-select form-select-sm">
                                <option value="tls" selected>TLS</option>
                                <option value="ssl">SSL</option>
                                <option value="">Ninguno</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="new-usuario-smtp" class="form-label">Usuario SMTP</label>
                            <input type="text" id="new-usuario-smtp" name="usuario_smtp" class="form-control form-control-sm" placeholder="correo@ejemplo.com" autocomplete="username">
                        </div>
                        <div class="col-md-6">
                            <label for="new-password-smtp" class="form-label">Contraseña SMTP <span class="text-danger">*</span></label>
                            <input type="password" id="new-password-smtp" name="password_smtp" class="form-control form-control-sm" required placeholder="••••••••" autocomplete="new-password">
                        </div>
                        <div class="col-md-6">
                            <label for="new-status" class="form-label">Estado</label>
                            <select id="new-status" name="status" class="form-select form-select-sm">
                                <option value="1" selected>Activo</option>
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

<!-- Modal Editar correo -->
<div class="modal fade" id="modalEditarCorreo" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/correosConfigUpdate" id="form-editar-correo">
                <input type="hidden" name="id" id="edit-id" value="">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Editar configuración de correo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="editar-correo-msg" class="d-none"></div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="edit-codigo" class="form-label">Código <span class="text-danger">*</span></label>
                            <input type="text" id="edit-codigo" name="codigo" class="form-control form-control-sm" required placeholder="recuperar_password" list="datalist-codigos-edit" pattern="[a-z0-9_]+">
                            <datalist id="datalist-codigos-edit">
                                <?php foreach ($codigosSugeridos as $cod => $nom): ?>
                                <option value="<?= htmlspecialchars($cod) ?>" label="<?= htmlspecialchars($nom) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-md-6">
                            <label for="edit-nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                            <input type="text" id="edit-nombre" name="nombre" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit-email" class="form-label">Correo remitente <span class="text-danger">*</span></label>
                            <input type="email" id="edit-email" name="email" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit-nombre-remitente" class="form-label">Nombre del remitente</label>
                            <input type="text" id="edit-nombre-remitente" name="nombre_remitente" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-6">
                            <label for="edit-host-smtp" class="form-label">Host SMTP <span class="text-danger">*</span></label>
                            <input type="text" id="edit-host-smtp" name="host_smtp" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-3">
                            <label for="edit-puerto-smtp" class="form-label">Puerto</label>
                            <input type="number" id="edit-puerto-smtp" name="puerto_smtp" class="form-control form-control-sm" min="1" max="65535">
                        </div>
                        <div class="col-md-3">
                            <label for="edit-encryption" class="form-label">Cifrado</label>
                            <select id="edit-encryption" name="encryption" class="form-select form-select-sm">
                                <option value="tls">TLS</option>
                                <option value="ssl">SSL</option>
                                <option value="">Ninguno</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit-usuario-smtp" class="form-label">Usuario SMTP</label>
                            <input type="text" id="edit-usuario-smtp" name="usuario_smtp" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-6">
                            <label for="edit-password-smtp" class="form-label">Contraseña SMTP</label>
                            <input type="password" id="edit-password-smtp" name="password_smtp" class="form-control form-control-sm" placeholder="Dejar en blanco para mantener la actual" autocomplete="new-password">
                        </div>
                        <div class="col-md-6">
                            <label for="edit-status" class="form-label">Estado</label>
                            <select id="edit-status" name="status" class="form-select form-select-sm">
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
    var base = '<?= rtrim($base ?? BASE_URL, "/") ?>';
    var modalEditar = document.getElementById('modalEditarCorreo');
    var modalNuevo = document.getElementById('modalNuevoCorreo');

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
                setTimeout(function() { window.location.href = base + '/config/correos-config'; }, 1500);
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
        var formEditar = modalEditar.querySelector('#form-editar-correo');
        document.querySelectorAll('.correo-row').forEach(function(row) {
            row.addEventListener('click', function() {
                ocultarMsgForm('editar-correo-msg');
                var d = this.dataset;
                formEditar.querySelector('#edit-id').value = d.id || '';
                formEditar.querySelector('#edit-codigo').value = d.codigo || '';
                formEditar.querySelector('#edit-nombre').value = d.nombre || '';
                formEditar.querySelector('#edit-email').value = d.email || '';
                formEditar.querySelector('#edit-nombre-remitente').value = d.nombreRemitente || '';
                formEditar.querySelector('#edit-host-smtp').value = d.hostSmtp || '';
                formEditar.querySelector('#edit-puerto-smtp').value = d.puertoSmtp || '587';
                formEditar.querySelector('#edit-usuario-smtp').value = d.usuarioSmtp || '';
                formEditar.querySelector('#edit-password-smtp').value = '';
                formEditar.querySelector('#edit-encryption').value = d.encryption || 'tls';
                formEditar.querySelector('#edit-status').value = d.status || '1';
                new bootstrap.Modal(modalEditar).show();
            });
            row.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); this.click(); }
            });
        });
        if (formEditar) {
            formEditar.addEventListener('submit', function(e) {
                e.preventDefault();
                ocultarMsgForm('editar-correo-msg');
                var btn = formEditar.querySelector('button[type="submit"]');
                var txtOrig = btn ? btn.innerHTML : '';
                if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...'; }
                enviarFormAjax(formEditar, 'editar-correo-msg', base + '/config/correosConfigUpdate')
                    .catch(function() { if (btn) { btn.disabled = false; btn.innerHTML = txtOrig; } });
            });
        }
        modalEditar.addEventListener('show.bs.modal', function() { ocultarMsgForm('editar-correo-msg'); });
    }

    if (modalNuevo) {
        modalNuevo.addEventListener('show.bs.modal', function() { ocultarMsgForm('crear-correo-msg'); });
        var formCrear = document.getElementById('form-crear-correo');
        if (formCrear) {
            formCrear.addEventListener('submit', function(e) {
                e.preventDefault();
                ocultarMsgForm('crear-correo-msg');
                var btn = formCrear.querySelector('button[type="submit"]');
                var txtOrig = btn ? btn.innerHTML : '';
                if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...'; }
                enviarFormAjax(formCrear, 'crear-correo-msg', base + '/config/correosConfigStore')
                    .catch(function() { if (btn) { btn.disabled = false; btn.innerHTML = txtOrig; } });
            });
        }
    }
})();
</script>
