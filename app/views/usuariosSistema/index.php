<?php

/** @var string $titulo */
/** @var array $rows */
/** @var int $total */
/** @var int $page */
/** @var int $totalPages */
/** @var string $buscar */
/** @var int $nivel */
$base = BASE_URL;
$rows = $rows ?? [];
$total = $total ?? 0;
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$perPage = $perPage ?? 20;
$ordenCol = $ordenCol ?? 'nombre';
$ordenDir = $ordenDir ?? 'asc';
$buscar = $buscar ?? '';
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to = $total > 0 ? min($page * $perPage, $total) : 0;
$msg = $msg ?? null;
$limiteUsuarios = $limiteUsuarios ?? null;
$urlRecuperar = rtrim(BASE_URL, '/') . '/auth/enviar-correo-recuperar';

function nivelTexto(int $n): string
{
    return match ($n) {
        3 => 'Super Admin',
        2 => 'Administrador',
        default => 'Usuario',
    };
}

$urlBaseUsuarios = rtrim($base, '/') . '/config/usuarios-sistema';

function thSortUsuarios($urlBase, $col, $label, $ordenCol, $ordenDir, $buscar, $page, $align = '')
{
    $dir = ($ordenCol === $col && strtolower($ordenDir) === 'asc') ? 'desc' : 'asc';
    $cls = trim('btn btn-link p-0 text-decoration-none ' . $align);
    $html = '<form method="POST" action="' . htmlspecialchars($urlBase) . '" class="d-inline">';
    $html .= '<input type="hidden" name="sort" value="' . htmlspecialchars($col) . '">';
    $html .= '<input type="hidden" name="dir" value="' . $dir . '">';
    $html .= '<input type="hidden" name="page" value="' . (int)$page . '">';
    $html .= '<input type="hidden" name="b" value="' . htmlspecialchars($buscar) . '">';
    $html .= '<button type="submit" class="' . $cls . '" title="Ordenar por ' . htmlspecialchars($label) . '">' . htmlspecialchars($label) . '</button>';
    $html .= '</form>';
    return $html;
}
?>
<style>
    .usuarios-sistema-header {
        flex-shrink: 0;
    }

    .usuarios-sistema-scroll {
        max-height: calc(100dvh - 280px);
        overflow-y: auto;
    }

    .usuarios-sistema-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }

    .usuario-row {
        cursor: pointer;
    }

    .usuario-row:hover {
        background-color: rgba(0, 0, 0, .04);
    }

    .empresas-usuario-scroll {
        max-height: 280px;
        overflow-y: auto;
    }

    .empresas-usuario-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #fff;
        box-shadow: 0 1px 0 #dee2e6;
    }
</style>
<div class="usuarios-sistema-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-people"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">
            <?= $nivel >= 3 ? 'Todos los usuarios del sistema. Clic en fila para ver detalles.' : 'Usuarios que tiene asignados. Clic en fila para ver detalles.' ?>
        </p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <?php if ($limiteUsuarios !== null): ?>
            <?php
            $limiteLleno  = $limiteUsuarios['actual'] >= $limiteUsuarios['max'];
            $disponibles  = max(0, $limiteUsuarios['max'] - $limiteUsuarios['actual']);
            ?>
            <span class="badge <?= $limiteLleno ? 'bg-danger' : 'bg-success bg-opacity-10 text-success border border-success' ?> px-3 py-2"
                  style="font-size:.8rem;" title="Usuarios de esta empresa">
                <i class="bi bi-people me-1"></i>
                <?= $limiteUsuarios['actual'] ?>/<?= $limiteUsuarios['max'] ?> usuarios &nbsp;·&nbsp;
                <?= $limiteLleno ? '<strong>Sin cupos disponibles</strong>' : "<strong>{$disponibles}</strong> disponible" . ($disponibles !== 1 ? 's' : '') ?>
            </span>
        <?php endif; ?>
        <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
        <?php if (!$limiteLleno || $nivel >= 3): ?>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCrearUsuario">
            <i class="bi bi-person-plus"></i> Crear usuario
        </button>
        <?php else: ?>
        <button type="button" class="btn btn-secondary btn-sm" disabled title="Límite de usuarios alcanzado para esta empresa">
            <i class="bi bi-person-plus"></i> Crear usuario
        </button>
        <?php endif; ?>
    </div>
</div>

<?php if ($msg): ?>
    <div class="alert alert-<?= htmlspecialchars($msg[0]) ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($msg[1]) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($limiteLleno ?? false): ?>
<div class="alert alert-warning d-flex align-items-center gap-3 py-2 px-3" role="alert">
    <i class="bi bi-whatsapp text-success fs-4 flex-shrink-0"></i>
    <div class="small">
        <strong>Ha alcanzado el límite de usuarios para esta empresa.</strong>
        Para ampliar el número de usuarios, comuníquese con el administrador por WhatsApp:
        <a href="https://wa.me/593958924831" target="_blank" class="fw-bold text-success text-decoration-none ms-1">
            <i class="bi bi-whatsapp"></i> 0958924831
        </a>
    </div>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center gap-2 mb-2 flex-wrap">
    <form method="POST" action="<?= $urlBaseUsuarios ?>" class="d-flex align-items-center gap-2">
        <input type="hidden" name="page" value="1">
        <input type="hidden" name="sort" value="<?= htmlspecialchars($ordenCol) ?>">
        <input type="hidden" name="dir" value="<?= htmlspecialchars($ordenDir) ?>">
        <div class="input-group input-group-sm" style="max-width: 320px;">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" name="b" class="form-control" placeholder="Buscar por nombre, cédula o correo..." value="<?= htmlspecialchars($buscar) ?>">
            <button type="submit" class="btn btn-outline-primary">Buscar</button>
            <?php if ($buscar !== '' || $page > 1): ?>
                <a href="<?= $urlBaseUsuarios ?>" class="btn btn-outline-secondary" title="Volver a página 1 sin filtros">Limpiar</a>
            <?php endif; ?>
        </div>
    </form>
    <?php if ($total > 0): ?>
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted small"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <?php if ($page <= 1): ?>
                <button type="button" class="btn btn-sm btn-outline-secondary" disabled aria-label="Anterior"><i class="fas fa-angle-left"></i></button>
            <?php else: ?>
                <form method="POST" action="<?= $urlBaseUsuarios ?>" class="d-inline">
                    <input type="hidden" name="page" value="<?= $page - 1 ?>">
                    <input type="hidden" name="b" value="<?= htmlspecialchars($buscar) ?>">
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($ordenCol) ?>">
                    <input type="hidden" name="dir" value="<?= htmlspecialchars($ordenDir) ?>">
                    <button type="submit" class="btn btn-sm btn-outline-secondary" aria-label="Anterior"><i class="fas fa-angle-left"></i></button>
                </form>
            <?php endif; ?>
            <?php if ($page >= $totalPages): ?>
                <button type="button" class="btn btn-sm btn-outline-secondary" disabled aria-label="Siguiente"><i class="fas fa-angle-right"></i></button>
            <?php else: ?>
                <form method="POST" action="<?= $urlBaseUsuarios ?>" class="d-inline">
                    <input type="hidden" name="page" value="<?= $page + 1 ?>">
                    <input type="hidden" name="b" value="<?= htmlspecialchars($buscar) ?>">
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($ordenCol) ?>">
                    <input type="hidden" name="dir" value="<?= htmlspecialchars($ordenDir) ?>">
                    <button type="submit" class="btn btn-sm btn-outline-secondary" aria-label="Siguiente"><i class="fas fa-angle-right"></i></button>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<div class="card cmg-table-card">
    <div class="card-body p-0">
        <div class="usuarios-sistema-scroll">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= thSortUsuarios($urlBaseUsuarios, 'nombre', 'Nombre', $ordenCol, $ordenDir, $buscar, $page) ?></th>
                        <th><?= thSortUsuarios($urlBaseUsuarios, 'cedula', 'Cédula', $ordenCol, $ordenDir, $buscar, $page) ?></th>
                        <th><?= thSortUsuarios($urlBaseUsuarios, 'mail', 'Correo', $ordenCol, $ordenDir, $buscar, $page) ?></th>
                        <th><?= thSortUsuarios($urlBaseUsuarios, 'nivel', 'Nivel', $ordenCol, $ordenDir, $buscar, $page) ?></th>
                        <th><?= thSortUsuarios($urlBaseUsuarios, 'estado', 'Estado', $ordenCol, $ordenDir, $buscar, $page) ?></th>
                        <th class="text-center">Empresas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <?php
                        $nivelU = (int)($r['nivel'] ?? 1);
                        $estado = (int)($r['estado'] ?? 1);
                        $empresas = $r['empresas'] ?? [];
                        ?>
                        <tr class="usuario-row" role="button" tabindex="0"
                            data-id="<?= (int)($r['id'] ?? 0) ?>"
                            data-nombre="<?= htmlspecialchars($r['nombre'] ?? '') ?>"
                            data-cedula="<?= htmlspecialchars($r['cedula'] ?? '') ?>"
                            data-mail="<?= htmlspecialchars($r['mail'] ?? '') ?>"
                            data-nivel="<?= $nivelU ?>"
                            data-estado="<?= $estado ?>"
                            data-empresas="<?= count($empresas) ?>"
                            data-token="<?= htmlspecialchars($r['token'] ?? '') ?>">
                            <td><?= htmlspecialchars($r['nombre'] ?? '') ?></td>
                            <td><code><?= htmlspecialchars($r['cedula'] ?? '') ?></code></td>
                            <td><?= htmlspecialchars($r['mail'] ?? '-') ?></td>
                            <td><span class="badge bg-<?= $nivelU >= 3 ? 'danger' : ($nivelU >= 2 ? 'info' : 'secondary') ?>"><?= nivelTexto($nivelU) ?></span></td>
                            <td>
                                <?php if ($estado): ?>
                                    <span class="badge bg-success">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><span class="badge bg-light text-dark"><?= count($empresas) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (empty($rows)): ?>
                <p class="text-muted text-center py-4 mb-0">No hay usuarios registrados.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Crear Usuario -->
<div class="modal fade" id="modalCrearUsuario" tabindex="-1" aria-labelledby="modalCrearUsuarioLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/crear-usuario">
                <input type="hidden" name="redirect" value="usuarios-sistema">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCrearUsuarioLabel"><i class="bi bi-person-plus"></i> Crear usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Se enviará un correo al nuevo usuario para que complete su registro y defina su contraseña.</p>
                    <div class="mb-3">
                        <label for="crear-nombre" class="form-label">Nombre</label>
                        <input type="text" id="crear-nombre" name="nombre" class="form-control" required placeholder="Nombre completo">
                    </div>
                    <div class="mb-3">
                        <label for="crear-correo" class="form-label">Correo electrónico</label>
                        <input type="email" id="crear-correo" name="correo" class="form-control" required placeholder="correo@ejemplo.com">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-person-plus"></i> Crear usuario</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Detalle Usuario (con pestañas) -->
<div class="modal fade" id="modalDetalleUsuario" tabindex="-1" aria-labelledby="modalDetalleUsuarioLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalDetalleUsuarioLabel"><i class="bi bi-person"></i> <span id="modal-usuario-nombre"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body pb-0">
                <div id="usuario-modal-msg"></div>
                <input type="hidden" id="modal-usuario-id" value="">
                <ul class="nav nav-tabs mb-3" id="tabsUsuario" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tab-general" data-bs-toggle="tab" data-bs-target="#pane-general" type="button" role="tab">General</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-empresas" data-bs-toggle="tab" data-bs-target="#pane-empresas" type="button" role="tab">Empresas asignadas</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-restablecer" data-bs-toggle="tab" data-bs-target="#pane-restablecer" type="button" role="tab">Restablecer contraseña</button>
                    </li>
                </ul>
                <div class="tab-content mb-3" id="tabsUsuarioContent">
                    <div class="tab-pane fade show active" id="pane-general" role="tabpanel">
                        <form method="POST" action="<?= $base ?>/config/usuariosSistemaUpdate" id="form-editar-usuario">
                            <input type="hidden" name="id" id="edit-usuario-id" value="">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label text-muted small">Nombre</label>
                                    <p class="mb-0" id="info-nombre"></p>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted small">Cédula</label>
                                    <p class="mb-0"><code id="info-cedula"></code></p>
                                </div>
                                <div class="col-md-6">
                                    <label for="edit-mail" class="form-label">Correo</label>
                                    <input type="email" id="edit-mail" name="mail" class="form-control form-control-sm" placeholder="correo@ejemplo.com">
                                </div>
                                <div class="col-md-6">
                                    <label for="edit-nivel" class="form-label">Nivel</label>
                                    <select id="edit-nivel" name="nivel" class="form-select form-select-sm">
                                        <option value="1">Usuario</option>
                                        <?php if ($nivel >= 3): ?>
                                            <option value="2">Administrador</option>
                                            <option value="3">Super administrador</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="edit-estado" class="form-label">Estado</label>
                                    <select id="edit-estado" name="estado" class="form-select form-select-sm">
                                        <option value="1">Activo</option>
                                        <option value="0">Inactivo</option>
                                    </select>
                                </div>
                                <!-- Botón anterior removido -->
                            </div>
                            <div id="section-reenviar-invitacion" class="mt-4 p-3 bg-light border rounded d-none">
                                <h6 class="text-primary mb-2"><i class="bi bi-envelope-plus"></i> Invitación pendiente</h6>
                                <p class="text-muted small mb-3">Este usuario aún no ha completado su registro. Puede reenviarle el correo de bienvenida.</p>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="btn-reenviar-invitacion">
                                    <i class="bi bi-send"></i> Reenviar correo de invitación
                                </button>
                                <div id="msg-resend-invitation" class="form-text mt-2"></div>
                            </div>
                        </form>
                    </div>
                    <div class="tab-pane fade" id="pane-empresas" role="tabpanel">
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small">Agregar empresa</label>
                                <select id="select-empresa-usuario" class="form-select form-select-sm">
                                    <option value="">Cargando...</option>
                                </select>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <button type="button" class="btn btn-sm btn-primary" id="btn-agregar-empresa-usuario">
                                    <i class="bi bi-plus"></i> Asignar
                                </button>
                            </div>
                        </div>
                        <div class="empresas-usuario-scroll">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Empresa</th>
                                        <th>RUC</th>
                                        <th class="text-end">Quitar</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-empresas-usuario"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="pane-restablecer" role="tabpanel">
                        <p class="text-muted small">Se enviará un correo al usuario con un enlace para restablecer su contraseña.</p>
                        <p class="small mb-2"><strong>Correo de envío:</strong> <span id="correo-restablecer-actual" class="text-primary"></span></p>
                        <p class="small text-info mb-2 d-none" id="correo-restablecer-aviso">Si modificó el correo en la pestaña General, se enviará al nuevo correo configurado.</p>
                        <button type="button" class="btn btn-warning" id="btn-enviar-restablecer">
                            <i class="bi bi-envelope"></i> Enviar correo para restablecer contraseña
                        </button>
                        <div id="msg-restablecer" class="form-text mt-2"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <div>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="btn-eliminar-usuario">
                        <i class="bi bi-trash"></i> Eliminar usuario
                    </button>
                </div>
                <div class="d-flex gap-2">

                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" form="form-editar-usuario" class="btn btn-primary btn-sm">
                        <i class="bi bi-check-lg"></i> Guardar cambios
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        var base = '<?= $base ?>';
        var urlRecuperar = '<?= $urlRecuperar ?>';
        var modal = document.getElementById('modalDetalleUsuario');
        var tbody = document.getElementById('tbody-empresas-usuario');
        var selectEmpresa = document.getElementById('select-empresa-usuario');
        var idUsuario = 0;
        var mailOriginal = '';

        function actualizarCorreoRestablecer() {
            var actual = document.getElementById('edit-mail').value.trim();
            document.getElementById('correo-restablecer-actual').textContent = actual || '(sin correo)';
            var aviso = document.getElementById('correo-restablecer-aviso');
            if (actual && mailOriginal !== '' && actual !== mailOriginal) {
                aviso.classList.remove('d-none');
            } else {
                aviso.classList.add('d-none');
            }
        }

        function abrirModalUsuario(el) {
            idUsuario = parseInt(el.dataset.id, 10);
            document.getElementById('modal-usuario-id').value = idUsuario;
            document.getElementById('edit-usuario-id').value = idUsuario;
            document.getElementById('modal-usuario-nombre').textContent = el.dataset.nombre || '';
            document.getElementById('info-nombre').textContent = el.dataset.nombre || '';
            document.getElementById('info-cedula').textContent = el.dataset.cedula || '';
            mailOriginal = el.dataset.mail || '';
            document.getElementById('edit-mail').value = mailOriginal;
            document.getElementById('edit-nivel').value = el.dataset.nivel || '1';
            document.getElementById('edit-estado').value = el.dataset.estado === '1' ? '1' : '0';
            
            var token = el.dataset.token || '';
            var secResend = document.getElementById('section-reenviar-invitacion');
            if (token !== '') {
                secResend.classList.remove('d-none');
            } else {
                secResend.classList.add('d-none');
            }
            document.getElementById('msg-resend-invitation').textContent = '';

            // Limpiar mensajes previos
            var msgModal = document.getElementById('usuario-modal-msg');
            if (msgModal) msgModal.innerHTML = '';

            var mailVal = mailOriginal;
            if (mailVal && mailVal !== '-') {
                document.getElementById('btn-enviar-restablecer').disabled = false;
            } else {
                document.getElementById('btn-enviar-restablecer').disabled = true;
            }
            document.getElementById('msg-restablecer').textContent = '';
            document.getElementById('msg-restablecer').className = 'form-text mt-2';
            actualizarCorreoRestablecer();
            bootstrap.Tab.getInstance(document.getElementById('tab-general')) || new bootstrap.Tab(document.getElementById('tab-general'));
            document.getElementById('tab-general').click();
            cargarEmpresas();
            cargarEmpresasDisponibles();
            new bootstrap.Modal(modal).show();
        }

        // Interceptar formulario de edición (AJAX)
        var formEditar = document.getElementById('form-editar-usuario');
        if (formEditar) {
            formEditar.addEventListener('submit', function(e) {
                e.preventDefault();
                var msgModal = document.getElementById('usuario-modal-msg');
                msgModal.innerHTML = '<div class="alert alert-info py-2 small">Guardando...</div>';
                
                var formData = new FormData(this);
                fetch(this.action, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(r => r.json())
                .then(res => {
                    if (res.ok) {
                        msgModal.innerHTML = '<div class="alert alert-success py-2 small">' + res.msg + '</div>';
                        setTimeout(() => { location.reload(); }, 1200);
                    } else {
                        msgModal.innerHTML = '<div class="alert alert-danger py-2 small">' + res.msg + '</div>';
                    }
                })
                .catch(() => {
                    msgModal.innerHTML = '<div class="alert alert-danger py-2 small">Error de conexión al servidor.</div>';
                });
            });
        }

        document.querySelectorAll('.usuario-row').forEach(function(row) {
            row.addEventListener('click', function(e) {
                if (!e.target.closest('a')) abrirModalUsuario(this);
            });
            row.addEventListener('keydown', function(e) {
                if ((e.key === 'Enter' || e.key === ' ') && !e.target.closest('a')) {
                    e.preventDefault();
                    abrirModalUsuario(this);
                }
            });
        });

        function cargarEmpresas() {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center">Cargando...</td></tr>';
            fetch(base + '/config/asignar-empresas?action=empresasUsuario&id=' + idUsuario)
                .then(function(r) {
                    return r.json();
                })
                .then(function(data) {
                    if (data.html) {
                        tbody.innerHTML = data.html;
                        tbody.querySelectorAll('.btn-quitar-empresa').forEach(function(b) {
                            b.addEventListener('click', function() {
                                if (confirm('¿Quitar esta empresa?')) {
                                    var id = this.dataset.id;
                                    var f = document.createElement('form');
                                    f.method = 'POST';
                                    f.action = base + '/config/asignar-empresas';
                                    var i = document.createElement('input');
                                    i.type = 'hidden';
                                    i.name = 'action';
                                    i.value = 'quitar';
                                    var i2 = document.createElement('input');
                                    i2.type = 'hidden';
                                    i2.name = 'id';
                                    i2.value = id;
                                    var i3 = document.createElement('input');
                                    i3.type = 'hidden';
                                    i3.name = 'redirect';
                                    i3.value = 'usuarios-sistema';
                                    f.appendChild(i);
                                    f.appendChild(i2);
                                    f.appendChild(i3);
                                    document.body.appendChild(f);
                                    f.submit();
                                }
                            });
                        });
                    } else {
                        tbody.innerHTML = '<tr><td colspan="3" class="text-muted">Sin empresas asignadas</td></tr>';
                    }
                })
                .catch(function() {
                    tbody.innerHTML = '<tr><td colspan="3" class="text-danger">Error al cargar</td></tr>';
                });
        }

        function cargarEmpresasDisponibles() {
            selectEmpresa.innerHTML = '<option value="">Cargando...</option>';
            fetch(base + '/config/asignar-empresas?action=empresasDisponibles&id_usuario=' + idUsuario)
                .then(function(r) {
                    return r.json();
                })
                .then(function(data) {
                    selectEmpresa.innerHTML = '<option value="">Seleccione empresa...</option>';
                    (data.empresas || []).forEach(function(e) {
                        var o = document.createElement('option');
                        o.value = e.id_empresa;
                        o.textContent = (e.nombre_comercial || '') + ' (' + (e.ruc || '') + ')';
                        selectEmpresa.appendChild(o);
                    });
                })
                .catch(function() {
                    selectEmpresa.innerHTML = '<option value="">Error</option>';
                });
        }

        document.getElementById('btn-agregar-empresa-usuario').addEventListener('click', function() {
            var idEmp = selectEmpresa.value;
            if (!idEmp) {
                alert('Seleccione una empresa');
                return;
            }
            var f = document.createElement('form');
            f.method = 'POST';
            f.action = base + '/config/asignar-empresas';
            var i = document.createElement('input');
            i.type = 'hidden';
            i.name = 'action';
            i.value = 'asignar';
            var i2 = document.createElement('input');
            i2.type = 'hidden';
            i2.name = 'id_empresa';
            i2.value = idEmp;
            var i3 = document.createElement('input');
            i3.type = 'hidden';
            i3.name = 'id_usuario';
            i3.value = idUsuario;
            var i4 = document.createElement('input');
            i4.type = 'hidden';
            i4.name = 'redirect';
            i4.value = 'usuarios-sistema';
            f.appendChild(i);
            f.appendChild(i2);
            f.appendChild(i3);
            f.appendChild(i4);
            document.body.appendChild(f);
            f.submit();
        });

        document.getElementById('edit-mail').addEventListener('input', actualizarCorreoRestablecer);
        document.getElementById('tab-restablecer').addEventListener('shown.bs.tab', actualizarCorreoRestablecer);

        document.getElementById('btn-enviar-restablecer').addEventListener('click', function() {
            var btn = this;
            var msgDiv = document.getElementById('msg-restablecer');
            var nombre = document.getElementById('info-nombre').textContent;
            var correo = document.getElementById('edit-mail').value.trim();
            if (!correo) {
                msgDiv.textContent = 'El usuario no tiene correo registrado.';
                msgDiv.className = 'form-text mt-2 text-danger';
                return;
            }
            btn.disabled = true;
            msgDiv.textContent = 'Enviando...';
            msgDiv.className = 'form-text mt-2';
            var formData = new FormData();
            formData.append('id_user', idUsuario);
            formData.append('nombre', nombre);
            formData.append('correo', correo);
            fetch(urlRecuperar, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(function(r) {
                    return r.json().catch(function() {
                        return {};
                    });
                })
                .then(function(res) {
                    if (res && res.ok) {
                        msgDiv.textContent = 'Se ha enviado el correo para restablecer la contraseña.';
                        msgDiv.className = 'form-text mt-2 text-success';
                    } else {
                        msgDiv.textContent = (res && res.error) || 'Error al enviar. Verifique Config → Correos.';
                        msgDiv.className = 'form-text mt-2 text-danger';
                    }
                })
                .catch(function() {
                    msgDiv.textContent = 'Error al enviar. Intente de nuevo.';
                    msgDiv.className = 'form-text mt-2 text-danger';
                })
                .finally(function() {
                    btn.disabled = false;
                });
        });

        document.getElementById('btn-eliminar-usuario').addEventListener('click', function() {
            if (!idUsuario) return;
            var nombre = document.getElementById('info-nombre').textContent;
            var msg = '¿Está seguro de eliminar al usuario "' + nombre + '"?';

            if (confirm(msg)) {
                var msgModal = document.getElementById('usuario-modal-msg');
                msgModal.innerHTML = '<div class="alert alert-info py-2 small">Eliminando...</div>';
                
                var formData = new FormData();
                formData.append('id', idUsuario);
                
                fetch(base + '/config/usuariosSistemaEliminar', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(r => {
                    if (!r.ok) throw new Error('Error en la respuesta del servidor');
                    return r.json().catch(err => {
                        console.error('JSON parse error:', err);
                        throw new Error('La respuesta del servidor no es válida (JSON error)');
                    });
                })
                .then(res => {
                    if (res.ok) {
                        msgModal.innerHTML = '<div class="alert alert-success py-2 small">' + res.msg + '</div>';
                        setTimeout(() => { location.reload(); }, 1200);
                    } else {
                        msgModal.innerHTML = '<div class="alert alert-danger py-2 small">' + res.msg + '</div>';
                    }
                })
                .catch(err => {
                    msgModal.innerHTML = '<div class="alert alert-danger py-2 small">' + (err.message || 'Error de conexión') + '</div>';
                });
            }
        });

        // Reenviar invitación (NUEVO)
        var btnReenviar = document.getElementById('btn-reenviar-invitacion');
        if (btnReenviar) {
            btnReenviar.addEventListener('click', function() {
                var btn = this;
                var msgDiv = document.getElementById('msg-resend-invitation');
                if (!idUsuario) return;
                
                btn.disabled = true;
                msgDiv.textContent = 'Enviando...';
                msgDiv.className = 'form-text mt-2';
                
                var formData = new FormData();
                formData.append('id', idUsuario);
                
                fetch(base + '/config/usuarios-sistema-reenviar-invitacion', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(r => r.json())
                .then(res => {
                    if (res.ok) {
                        msgDiv.textContent = res.msg;
                        msgDiv.className = 'form-text mt-2 text-success';
                    } else {
                        msgDiv.textContent = res.msg || 'Error al enviar.';
                        msgDiv.className = 'form-text mt-2 text-danger';
                    }
                })
                .catch(() => {
                    msgDiv.textContent = 'Error de conexión.';
                    msgDiv.className = 'form-text mt-2 text-danger';
                })
                .finally(() => {
                    btn.disabled = false;
                });
            });
        }
    })();
</script>