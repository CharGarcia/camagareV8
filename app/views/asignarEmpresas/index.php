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
$limiteUsuarios = $limiteUsuarios ?? null;
$limiteLleno = $limiteUsuarios !== null && $limiteUsuarios['actual'] >= $limiteUsuarios['max'];
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-building"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">
            <?= $nivel >= 3 ? 'Puede asignar empresas a administradores y usuarios finales.' : 'Ve usuarios asignados a sus empresas. Puede asignar o quitar empresas que tenga asignadas.' ?>
        </p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <?php if ($limiteUsuarios !== null): ?>
            <?php $disponibles = max(0, $limiteUsuarios['max'] - $limiteUsuarios['actual']); ?>
            <span class="badge <?= $limiteLleno ? 'bg-danger' : 'bg-success bg-opacity-10 text-success border border-success' ?> px-3 py-2"
                  style="font-size:.8rem;" title="Usuarios de esta empresa">
                <i class="bi bi-people me-1"></i>
                <?= $limiteUsuarios['actual'] ?>/<?= $limiteUsuarios['max'] ?> usuarios &nbsp;·&nbsp;
                <?= $limiteLleno ? '<strong>Sin cupos disponibles</strong>' : "<strong>{$disponibles}</strong> disponible" . ($disponibles !== 1 ? 's' : '') ?>
            </span>
        <?php endif; ?>
        <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
        <?php if (!$limiteLleno || $nivel >= 3): ?>
        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalCrearUsuario">
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

<?php if ($limiteLleno): ?>
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

<div class="card mb-3">
    <div class="card-body py-2 d-flex align-items-center gap-2 flex-wrap">
        <div class="input-group input-group-sm" style="max-width:320px">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" id="asigEmpInputBuscar" class="form-control" placeholder="Buscar por nombre, cédula o tipo..." value="<?= htmlspecialchars($buscar) ?>" autocomplete="off">
        </div>
        <?php if ($nivel < 3): ?>
        <span class="text-muted small">Solo se muestran los usuarios asignados a sus empresas.</span>
        <?php endif; ?>
    </div>
</div>

<div class="card cmg-table-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Usuario</th>
                        <th>Cédula</th>
                        <th>Tipo</th>
                        <th>Empresas</th>
                    </tr>
                </thead>
                <tbody id="tbodyAsigEmp">
                    <?php if (empty($rows)): ?>
                    <tr><td colspan="4" class="text-center py-5 text-muted"><i class="bi bi-people fs-3 d-block mb-2"></i>No hay usuarios para asignar.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                        <?php $tipo = (int)($r['nivel'] ?? 1) >= 2 ? 'Administrador' : 'Usuario'; ?>
                        <tr class="row-usuario" role="button" tabindex="0" style="cursor:pointer"
                            data-id="<?= (int)($r['id_usuario'] ?? 0) ?>"
                            data-nombre="<?= htmlspecialchars($r['nombre'] ?? '') ?>">
                            <td><?= htmlspecialchars($r['nombre'] ?? '') ?></td>
                            <td><?= htmlspecialchars($r['cedula'] ?? '') ?></td>
                            <td><span class="badge bg-<?= $tipo === 'Administrador' ? 'info' : 'secondary' ?>"><?= $tipo ?></span></td>
                            <td><span class="badge bg-light text-dark"><?= (int)($r['total_empresas'] ?? 0) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer py-2" id="paginacionAsigEmp">
        <?php if ($totalPages > 1): ?>
        <nav>
            <ul class="pagination pagination-sm mb-0 justify-content-center">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <button type="button" class="page-link" onclick="ASIGEMP_cambiarPagina(<?= $i ?>)"><?= $i ?></button>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
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
                        <label class="form-label small" for="select-empresa"><i class="bi bi-search"></i> Buscar empresa</label>
                        <select id="select-empresa" class="form-select form-select-sm">
                            <option value="">Buscar empresa por nombre o RUC...</option>
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

    // Delegación de eventos: las filas se reemplazan en cada búsqueda AJAX,
    // por lo que el listener va en el tbody (contenedor fijo), no en cada <tr>.
    var tbodyAsigEmp = document.getElementById('tbodyAsigEmp');
    if (tbodyAsigEmp) {
        tbodyAsigEmp.addEventListener('click', function(e) {
            var row = e.target.closest('.row-usuario');
            if (row) abrirModalUsuario(row);
        });
        tbodyAsigEmp.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            var row = e.target.closest('.row-usuario');
            if (row) {
                e.preventDefault();
                abrirModalUsuario(row);
            }
        });
    }

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

    // Buscador de empresas disponibles. Se consulta al servidor mientras se escribe
    // (el endpoint acepta 'q'), así no depende de que quepan todas en el desplegable.
    var tsEmpresa = null;

    function opcionesEmpresa(empresas) {
        return (empresas || []).map(function(e) {
            var ruc = e.ruc ? ' (' + e.ruc + ')' : '';
            return { value: String(e.id_empresa), text: (e.nombre_comercial || 'Empresa') + ruc };
        });
    }

    function pedirEmpresasDisponibles(query, callback) {
        if (!idUsuario) { callback([]); return; }
        fetch(base + '/config/asignar-empresas?action=empresasDisponibles&id_usuario=' + idUsuario + '&q=' + encodeURIComponent(query || ''), {
                credentials: 'same-origin'
            })
            .then(function(r) { return r.ok ? r.json() : { empresas: [] }; })
            .then(function(data) { callback(opcionesEmpresa(data.empresas)); })
            .catch(function() { callback([]); });
    }

    // El layout carga Tom Select al final del body, después de este script: por eso la
    // inicialización espera al evento load (y se reintenta al abrir el modal).
    function initBuscadorEmpresa() {
        if (tsEmpresa || typeof TomSelect === 'undefined') return;
        tsEmpresa = new TomSelect('#select-empresa', {
            create: false,
            placeholder: 'Escriba el nombre o el RUC de la empresa...',
            maxOptions: 200,
            loadThrottle: 300,
            load: function(query, callback) { pedirEmpresasDisponibles(query, callback); }
        });
    }
    window.addEventListener('load', initBuscadorEmpresa);

    function cargarEmpresasDisponibles() {
        initBuscadorEmpresa();
        // Las empresas disponibles dependen del usuario del modal: se vacía la lista
        // anterior antes de traer la del usuario que se acaba de abrir.
        if (tsEmpresa) {
            tsEmpresa.clear(true);
            tsEmpresa.clearOptions();
        } else {
            selectEmpresa.innerHTML = '<option value="">Cargando...</option>';
        }

        pedirEmpresasDisponibles('', function(opciones) {
            if (tsEmpresa) {
                tsEmpresa.addOptions(opciones);
                tsEmpresa.refreshOptions(false);
                return;
            }
            selectEmpresa.innerHTML = '<option value="">Seleccione empresa...</option>';
            opciones.forEach(function(o) {
                var op = document.createElement('option');
                op.value = o.value;
                op.textContent = o.text;
                selectEmpresa.appendChild(op);
            });
        });
    }

    // Búsqueda en tiempo real: reemplaza solo la tabla y la paginación vía AJAX,
    // sin recargar la página (el input nunca pierde el foco). Mismo patrón que
    // ASIENTOTIPO_cargarListado (public/js/modulos/asientos_tipo_modal.js).
    var asigEmpTimer = null;
    window.ASIGEMP_cargarListado = function(page) {
        page = page || 1;
        var inputB = document.getElementById('asigEmpInputBuscar');
        var b = inputB ? inputB.value.trim() : '';
        var tbodyEl = document.getElementById('tbodyAsigEmp');
        if (tbodyEl) tbodyEl.innerHTML = '<tr><td colspan="4" class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary"></span> Cargando...</td></tr>';

        fetch(base + '/config/asignar-empresas?action=search&b=' + encodeURIComponent(b) + '&page=' + page, {
                credentials: 'same-origin'
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.ok) {
                    if (tbodyEl) tbodyEl.innerHTML = data.rows;
                    var pag = document.getElementById('paginacionAsigEmp');
                    if (pag) pag.innerHTML = data.pagination;
                }
            })
            .catch(function() {
                if (tbodyEl) tbodyEl.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-4">Error al cargar.</td></tr>';
            });
    };

    window.ASIGEMP_cambiarPagina = function(page) {
        if (page < 1) return;
        ASIGEMP_cargarListado(page);
    };

    var asigEmpInputBuscar = document.getElementById('asigEmpInputBuscar');
    if (asigEmpInputBuscar) {
        asigEmpInputBuscar.addEventListener('input', function() {
            clearTimeout(asigEmpTimer);
            asigEmpTimer = setTimeout(function() {
                ASIGEMP_cargarListado(1);
            }, 400);
        });
    }

    document.getElementById('btn-agregar-empresa').addEventListener('click', function() {
        var idEmp = tsEmpresa ? tsEmpresa.getValue() : selectEmpresa.value;
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
