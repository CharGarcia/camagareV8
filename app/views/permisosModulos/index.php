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
/** @var array $combosActivos */
$base = BASE_URL;
$opcionesUsuarios = $opcionesUsuarios ?? [];
$opcionesEmpresas = $opcionesEmpresas ?? [];
$combosActivos = $combosActivos ?? [];
$msg = $_SESSION['permisos_msg'] ?? null;
unset($_SESSION['permisos_msg']);
$limiteUsuarios = $limiteUsuarios ?? null;
$limiteLleno = $limiteUsuarios !== null && $limiteUsuarios['actual'] >= $limiteUsuarios['max'];
?>
<style>
/* Contenedor scrollable propio (evita el CSS global de cmg-table-card que recortaba filas) */
.permisos-tabla-wrap {
    max-height: calc(100dvh - 320px);
    overflow-y: auto;
    overflow-x: hidden;
    border: 1px solid #dee2e6;
    border-radius: .375rem;
}
.permisos-tabla-wrap thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #f8f9fa;
    box-shadow: inset 0 -1px 0 #dee2e6;
}
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-shield-lock"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">
            Seleccione usuario, luego empresa, y finalmente Mostrar para asignar permisos.
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
    <div class="card-body">
        <form method="GET" action="<?= $base ?>/config/permisos-modulos" id="form-permisos-buscar">
            <input type="hidden" name="mostrar" value="1">
            <input type="hidden" name="u" id="input-u" value="<?= (int)$idUsuarioSel ?>">
            <div id="paso1" style="display:<?= ($idUsuarioSel && $idEmpresaSel) ? 'none' : 'block' ?>;">
                <div class="row g-3 align-items-end">
                    <div class="col-md-9">
                        <label class="form-label small">Usuario</label>
                        <select id="select-usuario" class="form-select">
                            <option value="">Seleccione usuario...</option>
                            <?php foreach ($opcionesUsuarios as $opt): ?>
                                <option value="<?= (int)$opt['value'] ?>" <?= ($opt['value'] ?? 0) == $idUsuarioSel ? 'selected' : '' ?>><?= htmlspecialchars($opt['text'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="button" id="btn-siguiente" class="btn btn-primary w-100"><i class="bi bi-arrow-right"></i> Seleccionar empresa</button>
                    </div>
                </div>
            </div>
            <div id="paso2" style="display:<?= ($idUsuarioSel && $idEmpresaSel) ? 'block' : 'none' ?>;">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label small">Usuario</label>
                        <input type="text" id="usuario-texto" class="form-control bg-light" readonly value="<?= htmlspecialchars(($usuarioSel['nombre'] ?? '') . ' (' . ($usuarioSel['cedula'] ?? '') . ')') ?>">
                    </div>
                </div>
                <div class="row g-3 mt-1 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label small">Empresa</label>
                        <select id="select-empresa" name="e" class="form-select">
                            <?php foreach ($opcionesEmpresas as $opt): ?>
                                <option value="<?= (int)$opt['value'] ?>" <?= ($opt['value'] ?? 0) == $idEmpresaSel ? 'selected' : '' ?>><?= htmlspecialchars($opt['text'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div id="empresa-msg" class="text-danger small mt-1 d-none"><i class="bi bi-exclamation-circle"></i> Debe seleccionar una empresa.</div>
                    </div>
                    <div class="col-md-4 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-eye"></i> Mostrar módulos</button>
                        <button type="button" id="btn-anterior" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Empezar de nuevo</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    var form = document.getElementById('form-permisos-buscar');
    var selectEmpresa = document.getElementById('select-empresa');
    var empresaMsg = document.getElementById('empresa-msg');
    if (!form || !selectEmpresa || !empresaMsg) return;

    form.addEventListener('submit', function(e) {
        if (!selectEmpresa.value) {
            e.preventDefault();
            empresaMsg.classList.remove('d-none');
            selectEmpresa.focus();
        } else {
            empresaMsg.classList.add('d-none');
        }
    });

    selectEmpresa.addEventListener('change', function() {
        empresaMsg.classList.add('d-none');
    });
})();
</script>

<?php if (!empty($modulos)): ?>
    <div class="card" id="card-modulos">
        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <strong><i class="bi bi-person-fill"></i> <?= htmlspecialchars($usuarioSel['nombre'] ?? '') ?> - <i class="bi bi-building"></i> <?= htmlspecialchars($empresaSel['nombre_comercial'] ?? $empresaSel['ruc'] ?? '') ?></strong>
            <?php if ((int)($usuarioSel['nivel'] ?? 0) < 3): ?>
            <div class="d-flex gap-2 flex-wrap">
                <?php if ($nivel >= 3 && !empty($combosActivos)): ?>
                <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalAplicarCombo">
                    <i class="bi bi-box-seam"></i> Aplicar combo
                </button>
                <?php endif; ?>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCopiarDesdeEmpresa">
                    <i class="bi bi-building-gear"></i> Copiar desde otra empresa
                </button>
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCopiarPermisos">
                    <i class="bi bi-files"></i> Copiar permisos a otro usuario
                </button>
            </div>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <form method="POST" action="<?= $base ?>/config/permisos-modulos" id="form-permisos">
                <input type="hidden" name="action" value="guardar">
                <input type="hidden" name="id_usuario" value="<?= (int)$idUsuarioSel ?>">
                <input type="hidden" name="id_empresa" value="<?= (int)$idEmpresaSel ?>">
                <div class="mb-2 d-flex align-items-center gap-2">
                    <div class="input-group input-group-sm" style="max-width:350px">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" id="buscador-submodulo" class="form-control" placeholder="Buscar módulo o submódulo...">
                        <button type="button" id="btn-limpiar-busqueda" class="btn btn-outline-secondary d-none"><i class="bi bi-x-lg"></i></button>
                    </div>
                    <small id="buscador-conteo" class="text-muted"></small>
                </div>
                <div class="permisos-tabla-wrap">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Módulo</th>
                                <th>Submódulo</th>
                                <th class="text-center" style="width:70px">Ver</th>
                                <th class="text-center" style="width:70px">Crear</th>
                                <th class="text-center" style="width:70px">Actualizar</th>
                                <th class="text-center" style="width:70px">Eliminar</th>
                                <th class="text-center bg-warning bg-opacity-10" style="width:90px" title="Marcar para ver todos los registros del módulo, de lo contrario solo verá los suyos.">Ver Todo <i class="bi bi-info-circle small"></i></th>
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
                                        <td class="text-center align-middle bg-warning bg-opacity-10 border-start border-warning border-opacity-25">
                                            <input type="hidden" name="perm[<?= (int)$sub['id_submodulo'] ?>][t]" value="0">
                                            <input type="checkbox" class="form-check-input perm-check perm-t border-warning" name="perm[<?= (int)$sub['id_submodulo'] ?>][t]" value="1" <?= ($sub['t'] ?? 0) ? 'checked' : '' ?>>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-2 small text-muted">
                    <i class="bi bi-info-circle"></i> Los cambios se guardan automáticamente al marcar cada casilla.
                    <span id="perm-status" class="ms-2"></span>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function() {
            var form = document.getElementById('form-permisos');
            if (!form) return;

            // El guardado es inmediato vía AJAX; evitar submit tradicional del form
            form.addEventListener('submit', function(e) { e.preventDefault(); });

            var base       = '<?= $base ?>';
            var idUsuario  = '<?= (int)$idUsuarioSel ?>';
            var idEmpresa  = '<?= (int)$idEmpresaSel ?>';
            var statusEl   = document.getElementById('perm-status');
            var statusTimer = null;

            function setStatus(tipo, texto) {
                if (!statusEl) return;
                clearTimeout(statusTimer);
                var color = tipo === 'ok' ? 'text-success' : (tipo === 'err' ? 'text-danger' : 'text-secondary');
                var icon  = tipo === 'ok' ? 'bi-check-circle-fill' : (tipo === 'err' ? 'bi-x-circle-fill' : 'bi-arrow-repeat');
                statusEl.className = color;
                statusEl.innerHTML = '<i class="bi ' + icon + '"></i> ' + texto;
                if (tipo === 'ok') {
                    statusTimer = setTimeout(function() { statusEl.innerHTML = ''; }, 2000);
                }
            }

            // Guarda la fila (submódulo) completa según el estado actual de sus checks
            function guardarFila(row) {
                var idSub = row.getAttribute('data-sub');
                var idMod = row.querySelector('input[name$="[id_modulo]"]').value;
                var get = function(cls) {
                    var c = row.querySelector('.' + cls);
                    return (c && c.checked) ? '1' : '0';
                };

                var fd = new FormData();
                fd.append('id_usuario', idUsuario);
                fd.append('id_empresa', idEmpresa);
                fd.append('id_modulo', idMod);
                fd.append('id_submodulo', idSub);
                fd.append('ver', get('perm-ver'));
                fd.append('crear', get('perm-crear'));
                fd.append('actualizar', get('perm-actualizar'));
                fd.append('eliminar', get('perm-eliminar'));
                fd.append('t', get('perm-t'));

                setStatus('load', 'Guardando...');
                fetch(base + '/config/permisos-modulos?action=guardarUno', {
                    method: 'POST', body: fd, credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function(r) { return r.json(); })
                .then(function(j) {
                    if (j.ok) {
                        setStatus('ok', 'Guardado');
                        actualizarIndicadorFila(row);
                    } else {
                        setStatus('err', j.error || 'Error al guardar');
                    }
                })
                .catch(function() { setStatus('err', 'Error de conexión'); });
            }

            // Actualiza el ícono de "con permisos" junto al nombre del submódulo
            function actualizarIndicadorFila(row) {
                var tieneAlguno = ['perm-ver','perm-crear','perm-actualizar','perm-eliminar'].some(function(cls) {
                    var c = row.querySelector('.' + cls);
                    return c && c.checked;
                });
                var celdaNombre = row.children[1];
                var icono = celdaNombre.querySelector('.bi-check-circle-fill');
                if (tieneAlguno && !icono) {
                    celdaNombre.insertAdjacentHTML('beforeend', ' <i class="bi bi-check-circle-fill text-success" title="Con permisos"></i>');
                } else if (!tieneAlguno && icono) {
                    icono.remove();
                }
            }

            document.querySelectorAll('.perm-row').forEach(function(row) {
                row.style.cursor = 'pointer';

                // Clic en la fila (fuera de un checkbox): alterna todos y guarda
                row.addEventListener('click', function(e) {
                    if (e.target.type === 'checkbox') return;
                    var checks = this.querySelectorAll('.perm-check');
                    var allChecked = Array.from(checks).every(function(c) { return c.checked; });
                    checks.forEach(function(c) { c.checked = !allChecked; });
                    guardarFila(this);
                });

                // Cambio en un checkbox individual: guarda
                row.querySelectorAll('.perm-check').forEach(function(chk) {
                    chk.addEventListener('change', function() {
                        guardarFila(row);
                    });
                });
            });
        })();
    </script>

    <script>
        (function() {
            var buscador  = document.getElementById('buscador-submodulo');
            var btnLimpiar = document.getElementById('btn-limpiar-busqueda');
            var conteo    = document.getElementById('buscador-conteo');
            if (!buscador) return;

            var allRows = Array.from(document.querySelectorAll('.perm-row'));
            var total   = allRows.length;

            function filtrar() {
                var q = buscador.value.toLowerCase().trim();
                var visibles = 0;
                allRows.forEach(function(row) {
                    var modulo    = (row.children[0].textContent || '').toLowerCase();
                    var submodulo = (row.children[1].textContent || '').toLowerCase();
                    var visible   = !q || modulo.indexOf(q) !== -1 || submodulo.indexOf(q) !== -1;
                    row.style.display = visible ? '' : 'none';
                    if (visible) visibles++;
                });
                btnLimpiar.classList.toggle('d-none', !q);
                conteo.textContent = q ? ('Mostrando ' + visibles + ' de ' + total) : '';
            }

            buscador.addEventListener('input', filtrar);
            btnLimpiar.addEventListener('click', function() {
                buscador.value = '';
                filtrar();
                buscador.focus();
            });
        })();
    </script>

    <!-- Modal Aplicar combo -->
    <?php if ($nivel >= 3 && !empty($combosActivos)): ?>
    <div class="modal fade" id="modalAplicarCombo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-box-seam"></i> Aplicar combo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-light border small mb-3">
                        <i class="bi bi-info-circle"></i> Se agregarán con acceso total los submódulos del combo a
                        <strong><?= htmlspecialchars($usuarioSel['nombre'] ?? '') ?></strong>
                        en <strong><?= htmlspecialchars($empresaSel['nombre_comercial'] ?? $empresaSel['ruc'] ?? '') ?></strong>.
                        Los permisos que ya tenía <strong>no se eliminan</strong>.
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Combo</label>
                        <select id="combo-select" class="form-select">
                            <option value="">Seleccione un combo...</option>
                            <?php foreach ($combosActivos as $c): ?>
                            <option value="<?= (int) $c['id'] ?>">
                                <?= htmlspecialchars($c['nombre']) ?> (<?= (int) $c['total_submodulos'] ?> submódulos<?= $c['precio'] !== null ? ', $' . number_format((float) $c['precio'], 2) : '' ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="combo-aplicar-msg" class="small mt-2"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" id="btn-aplicar-combo" class="btn btn-success"><i class="bi bi-box-seam"></i> Aplicar combo</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function() {
            var modalEl = document.getElementById('modalAplicarCombo');
            if (!modalEl) return;

            var base = '<?= $base ?>';
            var idUsuario = '<?= (int)$idUsuarioSel ?>';
            var idEmpresa = '<?= (int)$idEmpresaSel ?>';
            var selCombo = document.getElementById('combo-select');
            var btnAplicar = document.getElementById('btn-aplicar-combo');
            var msgEl = document.getElementById('combo-aplicar-msg');

            function setMsg(tipo, texto) {
                var c = tipo === 'ok' ? 'text-success' : (tipo === 'err' ? 'text-danger' : 'text-secondary');
                msgEl.className = c + ' small mt-2';
                msgEl.innerHTML = texto;
            }

            btnAplicar.addEventListener('click', function() {
                var idCombo = selCombo.value;
                if (!idCombo) { setMsg('err', 'Seleccione un combo.'); return; }

                btnAplicar.disabled = true;
                setMsg('load', '<i class="bi bi-arrow-repeat"></i> Aplicando...');

                var fd = new FormData();
                fd.append('id_combo', idCombo);
                fd.append('id_usuario', idUsuario);
                fd.append('id_empresa', idEmpresa);

                fetch(base + '/config/combos-submodulos?action=aplicar', {
                    method: 'POST', body: fd, credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function(r) { return r.json(); })
                .then(function(j) {
                    if (j.ok) {
                        setMsg('ok', '<i class="bi bi-check-circle-fill"></i> Combo "' + j.combo + '" aplicado (' + j.aplicados + ' submódulos). Actualizando lista...');
                        setTimeout(function() {
                            window.location.href = base + '/config/permisos-modulos?u=' + idUsuario + '&e=' + idEmpresa + '&mostrar=1';
                        }, 900);
                    } else {
                        btnAplicar.disabled = false;
                        setMsg('err', '<i class="bi bi-x-circle-fill"></i> ' + (j.error || 'Error al aplicar el combo.'));
                    }
                })
                .catch(function() {
                    btnAplicar.disabled = false;
                    setMsg('err', 'Error de conexión.');
                });
            });

            modalEl.addEventListener('hidden.bs.modal', function() {
                selCombo.value = '';
                btnAplicar.disabled = false;
                setMsg('', '');
            });
        })();
    </script>
    <?php endif; ?>

    <!-- Modal Copiar permisos a otro usuario -->
    <div class="modal fade" id="modalCopiarPermisos" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-files"></i> Copiar permisos a otro usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-light border small mb-3">
                        <i class="bi bi-info-circle"></i> Se copiarán los permisos de
                        <strong><?= htmlspecialchars($usuarioSel['nombre'] ?? '') ?></strong>
                        (empresa <strong><?= htmlspecialchars($empresaSel['nombre_comercial'] ?? $empresaSel['ruc'] ?? '') ?></strong>)
                        al usuario y empresa que seleccione. <strong>Se reemplazarán</strong> los permisos actuales del destino.
                    </div>
                    <?php
                    // Nivel del usuario origen. Solo se permite copiar a usuarios de nivel <= origen,
                    // y nunca a superadministradores (nivel 3).
                    $nivelOrigen = (int)($usuarioSel['nivel'] ?? 0);
                    ?>
                    <div class="mb-3">
                        <label class="form-label small">Usuario destino</label>
                        <select id="copia-usuario" class="form-select">
                            <option value="">Seleccione usuario...</option>
                            <?php foreach ($opcionesUsuarios as $opt): ?>
                                <?php
                                $valOpt   = (int)($opt['value'] ?? 0);
                                $nivelOpt = (int)($opt['nivel'] ?? 0);
                                if ($valOpt === (int)$idUsuarioSel) continue;          // no a sí mismo
                                if ($nivelOpt >= 3) continue;                          // nunca a superadmin
                                if ($nivelOpt > $nivelOrigen) continue;                // usuario(1) no puede a admin(2)
                                ?>
                                <option value="<?= $valOpt ?>"><?= htmlspecialchars($opt['text'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Empresa destino</label>
                        <select id="copia-empresa" class="form-select" disabled>
                            <option value="">Seleccione primero el usuario...</option>
                        </select>
                    </div>
                    <div id="copia-msg" class="small mt-2"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" id="btn-copiar-permisos" class="btn btn-primary"><i class="bi bi-files"></i> Copiar permisos</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function() {
            var modalEl = document.getElementById('modalCopiarPermisos');
            if (!modalEl) return;

            var base       = '<?= $base ?>';
            var idUsuOrig  = '<?= (int)$idUsuarioSel ?>';
            var idEmpOrig  = '<?= (int)$idEmpresaSel ?>';
            var selUsuario = document.getElementById('copia-usuario');
            var selEmpresa = document.getElementById('copia-empresa');
            var btnCopiar  = document.getElementById('btn-copiar-permisos');
            var msgEl      = document.getElementById('copia-msg');

            var tsUsu = null, tsEmp = null;
            if (typeof TomSelect !== 'undefined') {
                tsUsu = new TomSelect('#copia-usuario', { create: false, placeholder: 'Buscar usuario...', maxOptions: 500 });
                tsEmp = new TomSelect('#copia-empresa', { create: false, placeholder: 'Buscar empresa...', maxOptions: 500 });
                tsEmp.disable();
            }

            function setMsg(tipo, texto) {
                var c = tipo === 'ok' ? 'text-success' : (tipo === 'err' ? 'text-danger' : 'text-secondary');
                msgEl.className = c + ' small mt-2';
                msgEl.innerHTML = texto;
            }

            function getUsuDestino() { return tsUsu ? tsUsu.getValue() : selUsuario.value; }
            function getEmpDestino() { return tsEmp ? tsEmp.getValue() : selEmpresa.value; }

            function cargarEmpresas(idU) {
                if (tsEmp) { tsEmp.clear(); tsEmp.clearOptions(); tsEmp.disable(); }
                else { selEmpresa.innerHTML = '<option value="">...</option>'; selEmpresa.disabled = true; }
                if (!idU) return;
                fetch(base + '/config/permisos-modulos?action=empresasJson&u=' + encodeURIComponent(idU) + '&q=', { credentials: 'same-origin' })
                    .then(function(r) { return r.ok ? r.json() : []; })
                    .then(function(data) {
                        if (Array.isArray(data) && data.length > 0) {
                            if (tsEmp) { tsEmp.addOptions(data); tsEmp.refreshOptions(false); tsEmp.enable(); }
                            else {
                                selEmpresa.innerHTML = '<option value="">Seleccione empresa...</option>';
                                data.forEach(function(o) {
                                    var op = document.createElement('option');
                                    op.value = o.value; op.textContent = o.text;
                                    selEmpresa.appendChild(op);
                                });
                                selEmpresa.disabled = false;
                            }
                        } else {
                            setMsg('err', 'El usuario destino no tiene empresas asignadas.');
                        }
                    })
                    .catch(function() { setMsg('err', 'Error al cargar empresas.'); });
            }

            var onChangeUsu = function() {
                setMsg('', '');
                cargarEmpresas(getUsuDestino());
            };
            if (tsUsu) tsUsu.on('change', onChangeUsu);
            else selUsuario.addEventListener('change', onChangeUsu);

            btnCopiar.addEventListener('click', function() {
                var idUd = getUsuDestino();
                var idEd = getEmpDestino();
                if (!idUd) { setMsg('err', 'Seleccione el usuario destino.'); return; }
                if (!idEd) { setMsg('err', 'Seleccione la empresa destino.'); return; }

                btnCopiar.disabled = true;
                setMsg('load', '<i class="bi bi-arrow-repeat"></i> Copiando...');

                var fd = new FormData();
                fd.append('id_usuario_origen', idUsuOrig);
                fd.append('id_empresa_origen', idEmpOrig);
                fd.append('id_usuario_destino', idUd);
                fd.append('id_empresa_destino', idEd);

                fetch(base + '/config/permisos-modulos?action=copiarPermisos', {
                    method: 'POST', body: fd, credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function(r) { return r.json(); })
                .then(function(j) {
                    btnCopiar.disabled = false;
                    if (j.ok) {
                        setMsg('ok', '<i class="bi bi-check-circle-fill"></i> Permisos copiados correctamente.');
                    } else {
                        setMsg('err', '<i class="bi bi-x-circle-fill"></i> ' + (j.error || 'Error al copiar.'));
                    }
                })
                .catch(function() {
                    btnCopiar.disabled = false;
                    setMsg('err', 'Error de conexión.');
                });
            });
        })();
    </script>

    <!-- Modal Copiar permisos desde otra empresa (mismo usuario, empresa diferente) -->
    <?php if ((int)($usuarioSel['nivel'] ?? 0) < 3): ?>
    <div class="modal fade" id="modalCopiarDesdeEmpresa" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-building-gear"></i> Copiar permisos desde otra empresa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-light border small mb-3">
                        <i class="bi bi-info-circle"></i> Se copiarán los permisos que tiene
                        <strong><?= htmlspecialchars($usuarioSel['nombre'] ?? '') ?></strong>
                        en la empresa seleccionada hacia
                        <strong><?= htmlspecialchars($empresaSel['nombre_comercial'] ?? $empresaSel['ruc'] ?? '') ?></strong>.
                        <strong>Se reemplazarán</strong> los permisos actuales de esta empresa para dicho usuario.
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Empresa de origen (de donde se copian los permisos)</label>
                        <select id="copiaEmp-empresa-origen" class="form-select">
                            <option value="">Cargando...</option>
                        </select>
                    </div>
                    <div id="copiaEmp-msg" class="small mt-2"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" id="btn-copiar-desde-empresa" class="btn btn-primary" disabled>
                        <i class="bi bi-building-gear"></i> Copiar permisos
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function() {
            var modalEl       = document.getElementById('modalCopiarDesdeEmpresa');
            if (!modalEl) return;

            var base          = '<?= $base ?>';
            var idUsuario     = '<?= (int)$idUsuarioSel ?>';
            var idEmpresaActual = '<?= (int)$idEmpresaSel ?>';
            var selOrigen     = document.getElementById('copiaEmp-empresa-origen');
            var btnCopiar     = document.getElementById('btn-copiar-desde-empresa');
            var msgEl         = document.getElementById('copiaEmp-msg');
            var tsOrigen      = null;

            function setMsg(tipo, texto) {
                var c = tipo === 'ok' ? 'text-success' : (tipo === 'err' ? 'text-danger' : 'text-secondary');
                msgEl.className = c + ' small mt-2';
                msgEl.innerHTML = texto;
            }

            if (typeof TomSelect !== 'undefined') {
                tsOrigen = new TomSelect('#copiaEmp-empresa-origen', {
                    create: false,
                    placeholder: 'Seleccione empresa origen...',
                    maxOptions: 500
                });
                tsOrigen.disable();
                tsOrigen.on('change', function(val) {
                    btnCopiar.disabled = !val;
                    setMsg('', '');
                });
            } else {
                selOrigen.addEventListener('change', function() {
                    btnCopiar.disabled = !this.value;
                    setMsg('', '');
                });
            }

            modalEl.addEventListener('show.bs.modal', function() {
                setMsg('', '');
                btnCopiar.disabled = true;
                if (tsOrigen) { tsOrigen.clear(); tsOrigen.clearOptions(); tsOrigen.disable(); }
                else { selOrigen.innerHTML = '<option value="">Cargando...</option>'; }

                fetch(base + '/config/permisos-modulos?action=empresasJson&u=' + encodeURIComponent(idUsuario) + '&q=', {
                    credentials: 'same-origin'
                })
                .then(function(r) { return r.ok ? r.json() : []; })
                .then(function(data) {
                    var filtered = data.filter(function(o) {
                        return String(o.value) !== String(idEmpresaActual);
                    });

                    if (filtered.length === 0) {
                        if (tsOrigen) tsOrigen.disable();
                        else selOrigen.innerHTML = '<option value="">Sin otras empresas disponibles</option>';
                        setMsg('err', 'El usuario no tiene otras empresas asignadas.');
                        return;
                    }

                    if (tsOrigen) {
                        tsOrigen.addOptions(filtered);
                        tsOrigen.refreshOptions(false);
                        tsOrigen.enable();
                    } else {
                        selOrigen.innerHTML = '<option value="">Seleccione empresa origen...</option>';
                        filtered.forEach(function(o) {
                            var op = document.createElement('option');
                            op.value = o.value;
                            op.textContent = o.text;
                            selOrigen.appendChild(op);
                        });
                    }
                })
                .catch(function() { setMsg('err', 'Error al cargar empresas.'); });
            });

            btnCopiar.addEventListener('click', function() {
                var idOrigen = tsOrigen ? tsOrigen.getValue() : selOrigen.value;
                if (!idOrigen) { setMsg('err', 'Seleccione la empresa origen.'); return; }

                btnCopiar.disabled = true;
                setMsg('load', '<i class="bi bi-arrow-repeat"></i> Copiando...');

                var fd = new FormData();
                fd.append('id_usuario_origen',  idUsuario);
                fd.append('id_empresa_origen',   idOrigen);
                fd.append('id_usuario_destino',  idUsuario);
                fd.append('id_empresa_destino',  idEmpresaActual);

                fetch(base + '/config/permisos-modulos?action=copiarPermisos', {
                    method: 'POST', body: fd, credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function(r) { return r.json(); })
                .then(function(j) {
                    if (j.ok) {
                        setMsg('ok', '<i class="bi bi-check-circle-fill"></i> Permisos copiados correctamente. Actualizando lista...');
                        setTimeout(function() {
                            window.location.href = base + '/config/permisos-modulos?u=' + idUsuario + '&e=' + idEmpresaActual + '&mostrar=1';
                        }, 900);
                    } else {
                        btnCopiar.disabled = false;
                        setMsg('err', '<i class="bi bi-x-circle-fill"></i> ' + (j.error || 'Error al copiar.'));
                    }
                })
                .catch(function() {
                    btnCopiar.disabled = false;
                    setMsg('err', 'Error de conexión.');
                });
            });
        })();
    </script>
    <?php endif; ?>
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
            fetch(base + '/config/permisos-modulos?action=empresasJson&u=' + encodeURIComponent(idU) + '&q=', {
                    credentials: 'same-origin'
                })
                .then(function(r) {
                    return r.ok ? r.json() : [];
                })
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
            paso2.style.display = 'block';
        });

        btnAnterior.addEventListener('click', function() {
            window.location = base + '/config/permisos-modulos?limpiar=1';
        });
    });
</script>