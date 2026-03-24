<?php
/** @var array $opciones */
/** @var string $titulo */
/** @var int $nivel */
/** @var bool $puedeCrear */
$base = BASE_URL;
$puedeCrear = $puedeCrear ?? false;
$msg = $_SESSION['config_msg'] ?? null;
unset($_SESSION['config_msg']);
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-gear"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">Opciones de configuración según tu nivel de acceso.</p>
    </div>
    <?php if ($puedeCrear): ?>
    <button type="button" class="btn btn-primary btn-sm" style="padding:4px 10px;font-size:0.8rem" data-bs-toggle="modal" data-bs-target="#modalNuevaTarjeta">
        <i class="bi bi-plus-lg"></i> Nueva tarjeta
    </button>
    <?php endif; ?>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg[0] ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($msg[1]) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (empty($opciones)): ?>
<div class="alert alert-info">No hay opciones de configuración disponibles para tu nivel.</div>
<?php else: ?>
<div class="row g-3" id="contenedor-tarjetas-config"<?= $puedeCrear ? ' data-sortable="1"' : '' ?>>
    <?php foreach ($opciones as $op): ?>
    <?php $opId = (int) ($op['id'] ?? 0); $mostrarAcciones = $puedeCrear && $opId > 0; $inactiva = (int)($op['activo'] ?? 1) === 0; ?>
    <div class="col-md-6 col-lg-4 tarjeta-config" data-id="<?= $opId ?>" <?= $mostrarAcciones ? 'data-opcion="' . htmlspecialchars(json_encode($op)) . '"' : '' ?>>
        <div class="card h-100 border-0 shadow-sm<?= $inactiva ? ' opacity-75' : '' ?>">
            <div class="card-header bg-<?= htmlspecialchars($op['clase_color'] ?? 'primary') ?> text-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 d-flex align-items-center gap-2">
                <?php if ($puedeCrear): ?>
                <span class="tarjeta-grip me-2 opacity-90" style="font-size:1.5rem;line-height:1" title="Arrastrar para ordenar"><i class="bi bi-grip-vertical"></i></span>
                <?php endif; ?>
                <?php if ($inactiva): ?><span class="badge bg-secondary">Oculta</span><?php endif; ?>
                    <?php
                    $icono = $op['icono'] ?? 'gear';
                    if (str_starts_with($icono, 'bi-') || str_starts_with($icono, 'bi ')) {
                        echo '<i class="bi ' . htmlspecialchars($icono) . '"></i>';
                    } elseif (str_contains($icono, 'fa-') || str_contains($icono, 'fas ') || str_contains($icono, 'far ')) {
                        echo '<i class="' . htmlspecialchars($icono) . '"></i>';
                    } else {
                        echo '<i class="bi bi-' . htmlspecialchars($icono) . '"></i>';
                    }
                    ?>
                    <?= htmlspecialchars($op['nombre']) ?>
                </h6>
                <?php if ($mostrarAcciones): ?>
                <div class="dropdown">
                    <button class="btn btn-sm btn-light rounded-circle p-1" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Acciones" style="width:28px;height:28px;line-height:1">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><button type="button" class="dropdown-item btn-editar-tarjeta"><i class="bi bi-pencil me-2"></i>Editar</button></li>
                        <li>
                            <form method="POST" action="<?= $base ?>/config/deleteOption" class="d-inline" onsubmit="return confirm('¿Eliminar esta tarjeta?');">
                                <input type="hidden" name="id" value="<?= $opId ?>">
                                <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Eliminar</button>
                            </form>
                        </li>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!empty($op['descripcion'])): ?>
                <p class="card-text small text-muted mb-3"><?= htmlspecialchars($op['descripcion']) ?></p>
                <?php endif; ?>
                <?php if (!empty($op['enlaces'])): ?>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($op['enlaces'] as $enlace): ?>
                    <?php
                    $ruta = $enlace['ruta'];
                    if (!preg_match('#^https?://#', $ruta)) {
                        $ruta = str_starts_with($ruta, $base) ? $ruta : (str_starts_with($ruta, '/') ? $base . $ruta : $base . '/' . ltrim($ruta, '/'));
                    }
                    ?>
                    <a href="<?= htmlspecialchars($ruta) ?>" class="btn btn-<?= htmlspecialchars($enlace['clase_btn'] ?? 'outline-primary') ?> btn-sm">
                        <?= htmlspecialchars($enlace['etiqueta']) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <span class="text-muted small">Sin enlaces configurados</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($puedeCrear): ?>
<style>
#contenedor-tarjetas-config[data-sortable="1"] .tarjeta-grip { cursor: grab; -webkit-text-stroke: 0.35px currentColor; }
#contenedor-tarjetas-config[data-sortable="1"] .tarjeta-grip:active { cursor: grabbing; }
</style>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<!-- Modal Editar tarjeta (debe estar antes del script que lo usa) -->
<div class="modal fade" id="modalEditarTarjeta" tabindex="-1" aria-labelledby="modalEditarTarjetaLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/updateOption">
                <input type="hidden" name="id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditarTarjetaLabel"><i class="bi bi-pencil"></i> Editar tarjeta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2" style="--bs-gutter-y:0.5rem">
                        <div class="col-md-6">
                            <label class="form-label">Nombre <span class="text-danger">*</span></label>
                            <input type="text" name="nombre" class="form-control" required style="height:28px;padding:2px 8px;font-size:0.85rem" placeholder="Ej: Apariencia">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Icono (Bootstrap Icons)</label>
                            <select name="icono" class="form-select" style="height:28px;padding:2px 8px;font-size:0.85rem">
                                <option value="gear">gear</option>
                                <option value="palette">palette</option>
                                <option value="person-gear">person-gear</option>
                                <option value="shield-check">shield-check</option>
                                <option value="database">database</option>
                                <option value="key">key</option>
                                <option value="bell">bell</option>
                                <option value="sliders">sliders</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <input type="text" name="descripcion" class="form-control" style="height:28px;padding:2px 8px;font-size:0.85rem" placeholder="Breve descripción de la opción">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Color del encabezado</label>
                            <select name="clase_color" class="form-select" style="height:28px;padding:2px 8px;font-size:0.85rem">
                                <option value="primary">primary</option>
                                <option value="secondary">secondary</option>
                                <option value="success">success</option>
                                <option value="info">info</option>
                                <option value="warning">warning</option>
                                <option value="danger">danger</option>
                                <option value="dark">dark</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Nivel mínimo</label>
                            <select name="nivel_minimo" class="form-select" style="height:28px;padding:2px 8px;font-size:0.85rem">
                                <option value="1">Usuario</option>
                                <option value="2">Administrador</option>
                                <option value="3">Super administrador</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Orden</label>
                            <input type="number" name="orden" class="form-control" value="0" min="0" style="height:28px;padding:2px 8px;font-size:0.85rem">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" name="activo" id="edit-activo" class="form-check-input" value="1">
                                <label class="form-check-label" for="edit-activo">Visible (activa)</label>
                            </div>
                        </div>
                        <div class="col-12 mt-2">
                            <hr>
                            <h6 class="text-muted">Enlaces</h6>
                            <p class="small text-muted mb-2">Botones que aparecerán en la tarjeta.</p>
                            <div id="contenedorEnlacesEdit"></div>
                            <button type="button" class="btn btn-outline-secondary btn-sm mt-1" id="btnAgregarEnlaceEdit" style="padding:4px 10px;font-size:0.8rem">
                                <i class="bi bi-plus"></i> Agregar enlace
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="padding:4px 12px;font-size:0.8rem">Cancelar</button>
                    <button type="submit" class="btn btn-primary" style="padding:4px 12px;font-size:0.8rem"><i class="bi bi-check-lg"></i> Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Nueva tarjeta -->
<div class="modal fade" id="modalNuevaTarjeta" tabindex="-1" aria-labelledby="modalNuevaTarjetaLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/storeOption">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalNuevaTarjetaLabel"><i class="bi bi-plus-circle"></i> Nueva tarjeta de configuración</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2" style="--bs-gutter-y:0.5rem">
                        <div class="col-md-6">
                            <label class="form-label">Nombre <span class="text-danger">*</span></label>
                            <input type="text" name="nombre" class="form-control" required style="height:28px;padding:2px 8px;font-size:0.85rem" placeholder="Ej: Apariencia">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Icono (Bootstrap Icons)</label>
                            <select name="icono" class="form-select" style="height:28px;padding:2px 8px;font-size:0.85rem">
                                <option value="gear">gear</option>
                                <option value="palette">palette</option>
                                <option value="person-gear">person-gear</option>
                                <option value="shield-check">shield-check</option>
                                <option value="database">database</option>
                                <option value="key">key</option>
                                <option value="bell">bell</option>
                                <option value="sliders">sliders</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <input type="text" name="descripcion" class="form-control" style="height:28px;padding:2px 8px;font-size:0.85rem" placeholder="Breve descripción de la opción">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Color del encabezado</label>
                            <select name="clase_color" class="form-select" style="height:28px;padding:2px 8px;font-size:0.85rem">
                                <option value="primary">primary</option>
                                <option value="secondary">secondary</option>
                                <option value="success">success</option>
                                <option value="info">info</option>
                                <option value="warning">warning</option>
                                <option value="danger">danger</option>
                                <option value="dark">dark</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Nivel mínimo</label>
                            <select name="nivel_minimo" class="form-select" style="height:28px;padding:2px 8px;font-size:0.85rem">
                                <option value="1">Usuario</option>
                                <option value="2">Administrador</option>
                                <option value="3">Super administrador</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Orden</label>
                            <input type="number" name="orden" class="form-control" value="0" min="0" style="height:28px;padding:2px 8px;font-size:0.85rem">
                        </div>
                        <div class="col-12 mt-2">
                            <hr>
                            <h6 class="text-muted">Enlaces (opcional)</h6>
                            <p class="small text-muted mb-2">Agrega botones que aparecerán en la tarjeta.</p>
                            <div id="contenedorEnlaces">
                                <div class="enlace-fila row g-2 mb-2 align-items-end">
                                    <div class="col-4">
                                        <input type="text" name="enlace_etiqueta[]" class="form-control form-control-sm" style="height:28px;padding:2px 8px;font-size:0.85rem" placeholder="Etiqueta">
                                    </div>
                                    <div class="col-4">
                                        <input type="text" name="enlace_ruta[]" class="form-control form-control-sm" style="height:28px;padding:2px 8px;font-size:0.85rem" placeholder="/ruta o URL">
                                    </div>
                                    <div class="col-3">
                                        <select name="enlace_clase_btn[]" class="form-select form-select-sm" style="height:28px;padding:2px 8px;font-size:0.85rem">
                                            <option value="outline-primary">outline-primary</option>
                                            <option value="primary">primary</option>
                                            <option value="secondary">secondary</option>
                                            <option value="success">success</option>
                                        </select>
                                    </div>
                                    <div class="col-1">
                                        <button type="button" class="btn btn-outline-danger btn-sm quitar-enlace" title="Quitar" style="padding:2px 6px"><i class="bi bi-dash"></i></button>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm mt-1" id="btnAgregarEnlace" style="padding:4px 10px;font-size:0.8rem">
                                <i class="bi bi-plus"></i> Agregar enlace
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="padding:4px 12px;font-size:0.8rem">Cancelar</button>
                    <button type="submit" class="btn btn-primary" style="padding:4px 12px;font-size:0.8rem"><i class="bi bi-check-lg"></i> Crear tarjeta</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    var contenedor = document.getElementById('contenedorEnlaces');
    var btnAgregar = document.getElementById('btnAgregarEnlace');
    if (!contenedor || !btnAgregar) return;

    var plantilla = '<div class="enlace-fila row g-2 mb-2 align-items-end">' +
        '<div class="col-4"><input type="text" name="enlace_etiqueta[]" class="form-control form-control-sm" style="height:28px;padding:2px 8px;font-size:0.85rem" placeholder="Etiqueta"></div>' +
        '<div class="col-4"><input type="text" name="enlace_ruta[]" class="form-control form-control-sm" style="height:28px;padding:2px 8px;font-size:0.85rem" placeholder="/ruta o URL"></div>' +
        '<div class="col-3"><select name="enlace_clase_btn[]" class="form-select form-select-sm" style="height:28px;padding:2px 8px;font-size:0.85rem">' +
        '<option value="outline-primary">outline-primary</option><option value="primary">primary</option><option value="secondary">secondary</option><option value="success">success</option></select></div>' +
        '<div class="col-1"><button type="button" class="btn btn-outline-danger btn-sm quitar-enlace" title="Quitar" style="padding:2px 6px"><i class="bi bi-dash"></i></button></div></div>';

    btnAgregar.addEventListener('click', function() {
        contenedor.insertAdjacentHTML('beforeend', plantilla);
        bindQuitar();
    });

    function bindQuitar() {
        contenedor.querySelectorAll('.quitar-enlace').forEach(function(btn) {
            btn.onclick = function() {
                var fila = this.closest('.enlace-fila');
                if (contenedor.querySelectorAll('.enlace-fila').length > 1) fila.remove();
            };
        });
    }
    bindQuitar();
})();

// Modal Editar tarjeta
(function() {
    var modalEdit = document.getElementById('modalEditarTarjeta');
    var contenedorEdit = document.getElementById('contenedorEnlacesEdit');
    if (!modalEdit || !contenedorEdit) return;

    var plantillaEnlace = '<div class="enlace-fila row g-2 mb-2 align-items-end">' +
        '<div class="col-4"><input type="text" name="enlace_etiqueta[]" class="form-control form-control-sm" style="height:28px;padding:2px 8px;font-size:0.85rem" placeholder="Etiqueta"></div>' +
        '<div class="col-4"><input type="text" name="enlace_ruta[]" class="form-control form-control-sm" style="height:28px;padding:2px 8px;font-size:0.85rem" placeholder="/ruta o URL"></div>' +
        '<div class="col-3"><select name="enlace_clase_btn[]" class="form-select form-select-sm" style="height:28px;padding:2px 8px;font-size:0.85rem">' +
        '<option value="outline-primary">outline-primary</option><option value="primary">primary</option><option value="secondary">secondary</option><option value="success">success</option></select></div>' +
        '<div class="col-1"><button type="button" class="btn btn-outline-danger btn-sm quitar-enlace-edit" title="Quitar" style="padding:2px 6px"><i class="bi bi-dash"></i></button></div></div>';

    document.querySelectorAll('.btn-editar-tarjeta').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var card = this.closest('[data-opcion]');
            if (!card) return;
            var op = JSON.parse(card.getAttribute('data-opcion'));
            var form = modalEdit.querySelector('form');
            form.querySelector('input[name="id"]').value = op.id;
            form.querySelector('input[name="nombre"]').value = op.nombre || '';
            form.querySelector('input[name="descripcion"]').value = op.descripcion || '';
            form.querySelector('select[name="icono"]').value = op.icono || 'gear';
            form.querySelector('select[name="clase_color"]').value = op.clase_color || 'primary';
            form.querySelector('select[name="nivel_minimo"]').value = String(op.nivel_minimo || 1);
            form.querySelector('input[name="orden"]').value = op.orden || 0;
            var activoCheck = form.querySelector('input[name="activo"]');
            if (activoCheck) activoCheck.checked = (op.activo !== 0 && op.activo !== '0');

            contenedorEdit.innerHTML = '';
            var enlaces = op.enlaces || [];
            if (enlaces.length === 0) {
                contenedorEdit.innerHTML = plantillaEnlace;
            } else {
                enlaces.forEach(function(e) {
                    var div = document.createElement('div');
                    div.className = 'enlace-fila row g-2 mb-2 align-items-end';
                    div.innerHTML = '<div class="col-4"><input type="text" name="enlace_etiqueta[]" class="form-control form-control-sm" style="height:28px;padding:2px 8px;font-size:0.85rem" placeholder="Etiqueta"></div>' +
                        '<div class="col-4"><input type="text" name="enlace_ruta[]" class="form-control form-control-sm" style="height:28px;padding:2px 8px;font-size:0.85rem" placeholder="/ruta o URL"></div>' +
                        '<div class="col-3"><select name="enlace_clase_btn[]" class="form-select form-select-sm" style="height:28px;padding:2px 8px;font-size:0.85rem">' +
                        '<option value="outline-primary">outline-primary</option><option value="primary">primary</option><option value="secondary">secondary</option><option value="success">success</option></select></div>' +
                        '<div class="col-1"><button type="button" class="btn btn-outline-danger btn-sm quitar-enlace-edit" title="Quitar" style="padding:2px 6px"><i class="bi bi-dash"></i></button></div>';
                    var inputs = div.querySelectorAll('input');
                    inputs[0].value = e.etiqueta || '';
                    inputs[1].value = e.ruta || '';
                    div.querySelector('select').value = e.clase_btn || 'outline-primary';
                    contenedorEdit.appendChild(div);
                });
            }
            bindQuitarEdit();
            new bootstrap.Modal(modalEdit).show();
        });
    });

    var btnAgregarEdit = document.getElementById('btnAgregarEnlaceEdit');
    if (btnAgregarEdit) {
        btnAgregarEdit.addEventListener('click', function() {
            contenedorEdit.insertAdjacentHTML('beforeend', plantillaEnlace);
            bindQuitarEdit();
        });
    }

    function bindQuitarEdit() {
        contenedorEdit.querySelectorAll('.quitar-enlace-edit').forEach(function(btn) {
            btn.onclick = function() {
                var fila = this.closest('.enlace-fila');
                if (contenedorEdit.querySelectorAll('.enlace-fila').length > 1) fila.remove();
            };
        });
    }
})();

// Arrastrar y soltar para ordenar tarjetas
(function() {
    var contenedor = document.getElementById('contenedor-tarjetas-config');
    if (!contenedor || !contenedor.getAttribute('data-sortable') || typeof Sortable === 'undefined') return;

    var sortable = new Sortable(contenedor, {
        animation: 150,
        handle: '.tarjeta-grip',
        ghostClass: 'opacity-50',
        onEnd: function() {
            var ids = [];
            contenedor.querySelectorAll('.tarjeta-config[data-id]').forEach(function(el) {
                var id = parseInt(el.getAttribute('data-id'), 10);
                if (id > 0) ids.push(id);
            });
            if (ids.length === 0) return;

            var formData = new FormData();
            ids.forEach(function(id) { formData.append('orden[]', id); });

            fetch('<?= $base ?>/config/reordenar-opciones', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.ok) {
                    var navbar = document.getElementById('navbar-mensajes');
                    if (navbar) { navbar.textContent = data.msg; navbar.classList.add('text-success'); setTimeout(function() { navbar.textContent = '\u00A0'; navbar.classList.remove('text-success'); }, 2000); }
                }
            })
            .catch(function() {});
        }
    });
})();
</script>
<?php endif; ?>
