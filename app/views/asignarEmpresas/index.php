<?php
/** @var string $titulo */
/** @var array $rows */
/** @var int $total */
/** @var int $page */
/** @var int $totalPages */
/** @var string $buscar */
/** @var int $nivel */
$base = BASE_URL;
$msg = $_SESSION['asignar_msg'] ?? null;
unset($_SESSION['asignar_msg']);
$nivelLabel = $nivel >= 3 ? 'Super administrador' : 'Administrador';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-building"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">
            <?= $nivel >= 3 ? 'Puede asignar empresas a administradores y usuarios finales.' : 'Ve usuarios asignados a sus empresas. Puede asignar o quitar empresas que tenga asignadas.' ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalCrearUsuario"><i class="bi bi-person-plus"></i> Crear usuario</button>
    </div>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= htmlspecialchars($msg[0]) ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($msg[1]) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="<?= $base ?>/config/asignar-empresas" class="row g-2 align-items-center">
            <div class="col-auto flex-grow-1">
                <div class="input-group input-group-sm">
                    <input type="text" name="b" class="form-control" placeholder="Buscar por nombre o cédula..." value="<?= htmlspecialchars($buscar) ?>" style="max-width:280px">
                    <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card cmg-table-card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-sm">
                <thead class="table-light">
                    <tr>
                        <th>Usuario</th>
                        <th>Cédula</th>
                        <th>Tipo</th>
                        <th>Empresas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <?php
                    $tipo = (int)($r['nivel'] ?? 1) >= 2 ? 'Administrador' : 'Usuario';
                    ?>
                    <tr class="row-usuario" role="button" tabindex="0" style="cursor:pointer"
                        data-id="<?= (int)($r['id_usuario'] ?? 0) ?>"
                        data-nombre="<?= htmlspecialchars($r['nombre'] ?? '') ?>">
                        <td><?= htmlspecialchars($r['nombre'] ?? '') ?></td>
                        <td><?= htmlspecialchars($r['cedula'] ?? '') ?></td>
                        <td><span class="badge bg-<?= $tipo === 'Administrador' ? 'info' : 'secondary' ?>"><?= $tipo ?></span></td>
                        <td><span class="badge bg-light text-dark"><?= (int)($r['total_empresas'] ?? 0) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (empty($rows)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-people" style="font-size:2rem"></i>
            <p class="mb-0 mt-2">No hay usuarios para asignar</p>
            <?php if ($nivel < 3): ?>
            <p class="small">Los administradores ven solo los usuarios asignados a las empresas que tienen acceso.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="card-footer py-2">
        <nav>
            <ul class="pagination pagination-sm mb-0 justify-content-center">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= $base ?>/config/asignar-empresas?page=<?= $i ?><?= $buscar ? '&b=' . urlencode($buscar) : '' ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Crear Usuario -->
<div class="modal fade" id="modalCrearUsuario" tabindex="-1" aria-labelledby="modalCrearUsuarioLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/crear-usuario">
                <input type="hidden" name="redirect" value="asignar-empresas">
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

<!-- Modal Gestionar empresas -->
<div class="modal fade" id="modalEmpresas" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-building"></i> Empresas asignadas a <span id="modal-nombre-usuario"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="modal-id-usuario">
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small">Agregar empresa</label>
                        <select id="select-empresa" class="form-select form-select-sm">
                            <option value="">Cargando...</option>
                        </select>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <button type="button" class="btn btn-sm btn-primary" id="btn-agregar-empresa">
                            <i class="bi bi-plus"></i> Asignar
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>Empresa</th><th>RUC</th><th class="text-end">Quitar</th></tr></thead>
                        <tbody id="tbody-empresas"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var base = '<?= $base ?>';
    var modal = document.getElementById('modalEmpresas');
    var tbody = document.getElementById('tbody-empresas');
    var selectEmpresa = document.getElementById('select-empresa');
    var idUsuario = 0;

    function abrirModalUsuario(el) {
        idUsuario = parseInt(el.dataset.id, 10);
        document.getElementById('modal-nombre-usuario').textContent = el.dataset.nombre || '';
        document.getElementById('modal-id-usuario').value = idUsuario;
        cargarEmpresas();
        cargarEmpresasDisponibles();
        new bootstrap.Modal(modal).show();
    }

    document.querySelectorAll('.row-usuario').forEach(function(row) {
        row.addEventListener('click', function(e) {
            abrirModalUsuario(this);
        });
        row.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                abrirModalUsuario(this);
            }
        });
    });

    function cargarEmpresas() {
        tbody.innerHTML = '<tr><td colspan="3" class="text-center">Cargando...</td></tr>';
        fetch(base + '/config/asignar-empresas?action=empresasUsuario&id=' + idUsuario)
            .then(function(r) { return r.json(); })
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
                                i.type = 'hidden'; i.name = 'action'; i.value = 'quitar';
                                var i2 = document.createElement('input');
                                i2.type = 'hidden'; i2.name = 'id'; i2.value = id;
                                f.appendChild(i); f.appendChild(i2);
                                document.body.appendChild(f);
                                f.submit();
                            }
                        });
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="3" class="text-muted">Sin empresas asignadas</td></tr>';
                }
            })
            .catch(function() { tbody.innerHTML = '<tr><td colspan="3" class="text-danger">Error al cargar</td></tr>'; });
    }

    function cargarEmpresasDisponibles() {
        selectEmpresa.innerHTML = '<option value="">Cargando...</option>';
        fetch(base + '/config/asignar-empresas?action=empresasDisponibles&id_usuario=' + idUsuario)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                selectEmpresa.innerHTML = '<option value="">Seleccione empresa...</option>';
                (data.empresas || []).forEach(function(e) {
                    var o = document.createElement('option');
                    o.value = e.id_empresa;
                    o.textContent = (e.nombre_comercial || '') + ' (' + (e.ruc || '') + ')';
                    selectEmpresa.appendChild(o);
                });
            })
            .catch(function() { selectEmpresa.innerHTML = '<option value="">Error</option>'; });
    }

    document.getElementById('btn-agregar-empresa').addEventListener('click', function() {
        var idEmp = selectEmpresa.value;
        if (!idEmp) { alert('Seleccione una empresa'); return; }
        var f = document.createElement('form');
        f.method = 'POST';
        f.action = base + '/config/asignar-empresas';
        var i = document.createElement('input');
        i.type = 'hidden'; i.name = 'action'; i.value = 'asignar';
        var i2 = document.createElement('input');
        i2.type = 'hidden'; i2.name = 'id_empresa'; i2.value = idEmp;
        var i3 = document.createElement('input');
        i3.type = 'hidden'; i3.name = 'id_usuario'; i3.value = idUsuario;
        f.appendChild(i); f.appendChild(i2); f.appendChild(i3);
        document.body.appendChild(f);
        f.submit();
    });
})();
</script>
