<?php
/** @var string $titulo */
/** @var int $nivel */
/** @var int $idUsuarioSel */
/** @var int $idEmpresaSel */
/** @var array|null $usuarioSel */
/** @var array|null $empresaSel */
/** @var array $modulos */
/** @var array $opcionesUsuarios */
/** @var array $opcionesEmpresas */
$base = BASE_URL;
$opcionesUsuarios = $opcionesUsuarios ?? [];
$opcionesEmpresas = $opcionesEmpresas ?? [];
$msg = $_SESSION['permisos_msg'] ?? null;
unset($_SESSION['permisos_msg']);
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-shield-lock"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">
            Seleccione usuario, luego empresa, y finalmente Mostrar para asignar permisos.
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
    <div class="card-body">
        <form method="GET" action="<?= $base ?>/config/permisos-modulos" id="form-permisos-buscar">
            <input type="hidden" name="mostrar" value="1">
            <input type="hidden" name="u" id="input-u" value="<?= (int)$idUsuarioSel ?>">
            <div id="paso1" class="row g-3 align-items-end" style="display:<?= ($idUsuarioSel && $idEmpresaSel) ? 'none' : 'flex' ?>;">
                <div class="col-md-6">
                    <label class="form-label small">Usuario</label>
                    <select id="select-usuario" class="form-select">
                        <option value="">Seleccione usuario...</option>
                        <?php foreach ($opcionesUsuarios as $opt): ?>
                        <option value="<?= (int)$opt['value'] ?>" <?= ($opt['value'] ?? 0) == $idUsuarioSel ? 'selected' : '' ?>><?= htmlspecialchars($opt['text'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <button type="button" id="btn-siguiente" class="btn btn-primary"><i class="bi bi-arrow-right"></i> Siguiente</button>
                </div>
            </div>
            <div id="paso2" class="row g-3 align-items-end" style="display:<?= ($idUsuarioSel && $idEmpresaSel) ? 'flex' : 'none' ?>;">
                <div class="col-md-4">
                    <label class="form-label small">Usuario</label>
                    <input type="text" id="usuario-texto" class="form-control bg-light" readonly value="<?= htmlspecialchars(($usuarioSel['nombre'] ?? '') . ' (' . ($usuarioSel['cedula'] ?? '') . ')') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Empresa</label>
                    <select id="select-empresa" name="e" class="form-select">
                        <?php foreach ($opcionesEmpresas as $opt): ?>
                        <option value="<?= (int)$opt['value'] ?>" <?= ($opt['value'] ?? 0) == $idEmpresaSel ? 'selected' : '' ?>><?= htmlspecialchars($opt['text'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end gap-2">
                    <button type="button" id="btn-anterior" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Anterior</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-eye"></i> Mostrar</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($modulos)): ?>
<div class="card cmg-table-card" id="card-modulos">
    <div class="card-header bg-light py-2">
        <strong><i class="bi bi-person-fill"></i> <?= htmlspecialchars($usuarioSel['nombre'] ?? '') ?> — <i class="bi bi-building"></i> <?= htmlspecialchars($empresaSel['nombre_comercial'] ?? $empresaSel['ruc'] ?? '') ?></strong>
    </div>
    <div class="card-body">
        <form method="POST" action="<?= $base ?>/config/permisos-modulos" id="form-permisos">
            <input type="hidden" name="action" value="guardar">
            <input type="hidden" name="id_usuario" value="<?= (int)$idUsuarioSel ?>">
            <input type="hidden" name="id_empresa" value="<?= (int)$idEmpresaSel ?>">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Módulo</th>
                            <th>Submódulo</th>
                            <th class="text-center" style="width:70px">Ver</th>
                            <th class="text-center" style="width:70px">Crear</th>
                            <th class="text-center" style="width:70px">Actualizar</th>
                            <th class="text-center" style="width:70px">Eliminar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($modulos as $mod): ?>
                        <?php foreach ($mod['submodulos'] as $sub): ?>
                        <tr class="perm-row" data-sub="<?= (int)$sub['id_submodulo'] ?>">
                            <td class="align-middle"><span class="text-muted small"><?= htmlspecialchars($mod['nombre_modulo'] ?? '') ?></span></td>
                            <td class="align-middle"><?= htmlspecialchars($sub['nombre_submodulo'] ?? '') ?><?= (($sub['ver'] ?? 0) || ($sub['crear'] ?? 0) || ($sub['actualizar'] ?? 0) || ($sub['eliminar'] ?? 0)) ? ' <i class="bi bi-check-circle-fill text-success" title="Con permisos"></i>' : '' ?></td>
                            <td class="text-center align-middle">
                                <input type="hidden" name="perm[<?= (int)$sub['id_submodulo'] ?>][id_modulo]" value="<?= (int)($mod['id_modulo'] ?? 0) ?>">
                                <input type="hidden" name="perm[<?= (int)$sub['id_submodulo'] ?>][ver]" value="0">
                                <input type="checkbox" class="form-check-input perm-check perm-ver" name="perm[<?= (int)$sub['id_submodulo'] ?>][ver]" value="1" <?= ($sub['ver'] ?? 0) ? 'checked' : '' ?>>
                            </td>
                            <td class="text-center align-middle">
                                <input type="hidden" name="perm[<?= (int)$sub['id_submodulo'] ?>][crear]" value="0">
                                <input type="checkbox" class="form-check-input perm-check perm-crear" name="perm[<?= (int)$sub['id_submodulo'] ?>][crear]" value="1" <?= ($sub['crear'] ?? 0) ? 'checked' : '' ?>>
                            </td>
                            <td class="text-center align-middle">
                                <input type="hidden" name="perm[<?= (int)$sub['id_submodulo'] ?>][actualizar]" value="0">
                                <input type="checkbox" class="form-check-input perm-check perm-actualizar" name="perm[<?= (int)$sub['id_submodulo'] ?>][actualizar]" value="1" <?= ($sub['actualizar'] ?? 0) ? 'checked' : '' ?>>
                            </td>
                            <td class="text-center align-middle">
                                <input type="hidden" name="perm[<?= (int)$sub['id_submodulo'] ?>][eliminar]" value="0">
                                <input type="checkbox" class="form-check-input perm-check perm-eliminar" name="perm[<?= (int)$sub['id_submodulo'] ?>][eliminar]" value="1" <?= ($sub['eliminar'] ?? 0) ? 'checked' : '' ?>>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg"></i> Guardar permisos</button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    var form = document.getElementById('form-permisos');
    if (!form) return;

    document.querySelectorAll('.perm-row').forEach(function(row) {
        row.style.cursor = 'pointer';
        row.addEventListener('click', function(e) {
            if (e.target.type === 'checkbox') return;
            var checks = this.querySelectorAll('.perm-check');
            var allChecked = Array.from(checks).every(function(c) { return c.checked; });
            checks.forEach(function(c) { c.checked = !allChecked; });
        });
    });
})();
</script>
<?php endif; ?>

<!-- Modal Crear Usuario -->
<div class="modal fade" id="modalCrearUsuario" tabindex="-1" aria-labelledby="modalCrearUsuarioLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/crear-usuario">
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

<script>
window.addEventListener('load', function() {
    var base = '<?= $base ?>';
    if (window.location.search.indexOf('v=1') !== -1) {
        history.replaceState({}, '', base + '/config/permisos-modulos');
    }
    var paso1 = document.getElementById('paso1');
    var paso2 = document.getElementById('paso2');
    var selectUsuario = document.getElementById('select-usuario');
    var selectEmpresa = document.getElementById('select-empresa');
    var inputU = document.getElementById('input-u');
    var usuarioTexto = document.getElementById('usuario-texto');
    var btnSiguiente = document.getElementById('btn-siguiente');
    var btnAnterior = document.getElementById('btn-anterior');
    if (!selectUsuario || typeof TomSelect === 'undefined') return;
    if (!btnSiguiente) return;

    var tsUsuario = new TomSelect('#select-usuario', {
        create: false,
        placeholder: 'Buscar usuario...',
        maxOptions: 500
    });

    var tsEmpresa = new TomSelect('#select-empresa', {
        create: false,
        placeholder: 'Buscar empresa...',
        maxOptions: 500
    });

    function actualizarUsuarioPaso2() {
        var idU = tsUsuario.getValue() || '';
        var opt = tsUsuario.options[idU] || tsUsuario.options[String(idU)];
        usuarioTexto.value = (opt && opt.text) ? opt.text : '';
        inputU.value = idU;
    }

    function cargarEmpresas(idU) {
        tsEmpresa.clear();
        tsEmpresa.clearOptions();
        if (!idU) return;
        fetch(base + '/config/permisos-modulos?action=empresasJson&u=' + encodeURIComponent(idU) + '&q=', { credentials: 'same-origin' })
            .then(function(r) { return r.ok ? r.json() : []; })
            .then(function(data) {
                if (Array.isArray(data) && data.length > 0) {
                    tsEmpresa.addOptions(data);
                    tsEmpresa.refreshOptions(false);
                }
            })
            .catch(function() {});
    }

    btnSiguiente.addEventListener('click', function() {
        var idU = tsUsuario.getValue() || '';
        if (!idU) {
            alert('Seleccione un usuario.');
            return;
        }
        actualizarUsuarioPaso2();
        cargarEmpresas(idU);
        paso1.style.display = 'none';
        paso2.style.display = 'flex';
    });

    btnAnterior.addEventListener('click', function() {
        window.location = base + '/config/permisos-modulos?limpiar=1';
    });
});
</script>
