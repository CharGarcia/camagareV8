<?php
/** @var array $perm */
/** @var array $tipos */
/** @var array $usuarios */

$base = BASE_URL;

// Nombre/ícono visible por módulo (fallback: humaniza la ruta MVC si no está mapeado aquí).
$modulosInfo = [
    'modulos/compras' => ['nombre' => 'Compras', 'icono' => 'bi-cart3'],
    'modulos/roles-pago' => ['nombre' => 'Roles de Pago', 'icono' => 'bi-people'],
    'modulos/factura-venta' => ['nombre' => 'Ventas', 'icono' => 'bi-receipt'],
];
$infoModulo = function (string $ruta) use ($modulosInfo): array {
    if (isset($modulosInfo[$ruta])) return $modulosInfo[$ruta];
    $slug = preg_replace('#^modulos/#', '', $ruta);
    return ['nombre' => ucwords(str_replace(['-', '_'], ' ', $slug)), 'icono' => 'bi-diagram-3'];
};
?>

<style>
    .ap-cfg-status-switch:checked { background-color: #198754 !important; border-color: #198754 !important; }
    .ap-cfg-status-switch:not(:checked) { background-color: #dc3545 !important; border-color: #dc3545 !important; }
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2 px-1">
    <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-sliders text-primary me-2"></i><?= htmlspecialchars($titulo) ?></h5>
</div>

<div class="card cmg-table-card border-0 shadow-sm rounded-3 w-100">
    <div class="card-header bg-white py-2 px-3 border-bottom">
        <span class="text-muted small">
            Cada fila es un punto de aprobación (checkpoint) de un módulo, con sus propios aprobadores. Los cambios se guardan automáticamente.
        </span>
    </div>

    <div class="card-body p-0">
        <div class="aprobaciones-config-scroll">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 py-2" data-col="modulo" style="width:140px;">Módulo</th>
                        <th data-col="proceso" style="width:200px;">Proceso</th>
                        <th data-col="aprobadores" style="min-width:280px;">Aprobadores</th>
                        <th data-col="umbral" style="width:150px;">Monto mínimo</th>
                        <th class="text-center" data-col="status" style="width:90px;">Status</th>
                        <th class="text-end pe-3" data-col="guardado" style="width:40px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tipos)): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">Todavía no hay tipos de aprobación registrados en el catálogo.</td></tr>
                    <?php else: foreach ($tipos as $t): $idTipo = (int) $t['id_tipo']; $info = $infoModulo($t['modulo_ruta'] ?? ''); ?>
                        <tr>
                            <td class="ps-3" data-col="modulo">
                                <i class="bi <?= $info['icono'] ?> text-primary me-1"></i><?= htmlspecialchars($info['nombre']) ?>
                            </td>
                            <td data-col="proceso">
                                <div class="fw-medium"><?= htmlspecialchars($t['nombre']) ?></div>
                                <?php if (!empty($t['descripcion'])): ?>
                                    <div class="text-muted" style="font-size:.72rem;"><?= htmlspecialchars($t['descripcion']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td data-col="aprobadores">
                                <div class="position-relative" style="max-width:340px;">
                                    <div class="form-control form-control-sm d-flex flex-wrap align-items-center gap-1 ap-cfg-input-wrap"
                                         id="ap-wrap-<?= $idTipo ?>" style="min-height:31px; cursor:text;">
                                        <div class="d-flex flex-wrap gap-1" id="ap-chips-<?= $idTipo ?>"></div>
                                        <input type="text" class="ap-cfg-buscar border-0 flex-grow-1 p-0" id="ap-buscar-<?= $idTipo ?>"
                                               data-tipo="<?= $idTipo ?>" placeholder="Buscar usuario…" autocomplete="off"
                                               style="outline:none; min-width:80px;" <?= empty($perm['actualizar']) ? 'disabled' : '' ?>>
                                    </div>
                                    <div class="list-group shadow-sm d-none" id="ap-dropdown-<?= $idTipo ?>" style="z-index:1055; width:100%; max-height:220px; overflow:auto;"></div>
                                </div>
                            </td>
                            <td data-col="umbral">
                                <input type="number" step="0.01" min="0" class="form-control form-control-sm ap-cfg-umbral"
                                       id="ap-umbral-<?= $idTipo ?>" data-tipo="<?= $idTipo ?>"
                                       value="<?= $t['umbral_monto'] !== null ? htmlspecialchars((string) $t['umbral_monto']) : '' ?>"
                                       placeholder="Sin mínimo" <?= empty($perm['actualizar']) ? 'disabled' : '' ?>>
                            </td>
                            <td class="text-center" data-col="status">
                                <div class="form-check form-switch d-flex justify-content-center mb-0">
                                    <input class="form-check-input ap-cfg-status-switch" type="checkbox" role="switch"
                                           id="ap-req-<?= $idTipo ?>" data-tipo="<?= $idTipo ?>" <?= !empty($t['requiere_aprobacion']) ? 'checked' : '' ?>
                                           <?= empty($perm['actualizar']) ? 'disabled' : '' ?>>
                                </div>
                            </td>
                            <td class="text-end pe-3" data-col="guardado">
                                <span id="ap-cfg-msg-<?= $idTipo ?>" class="small"></span>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const AP_CFG_URL = '<?= $base ?>/modulos/aprobaciones-config';
const AP_CFG_PUEDE_EDITAR = <?= !empty($perm['actualizar']) ? 'true' : 'false' ?>;
const AP_CFG_USUARIOS = <?= json_encode(array_map(static fn($u) => ['id' => (int) $u['id'], 'nombre' => $u['nombre']], $usuarios), JSON_UNESCAPED_UNICODE) ?>;

// Aprobadores seleccionados por tipo: { idTipo: Set(idUsuario) }
const AP_CFG_SELECCION = {};
<?php foreach ($tipos as $t): ?>
    AP_CFG_SELECCION[<?= (int) $t['id_tipo'] ?>] = new Set(<?= json_encode(array_map('intval', $t['usuarios_aprobadores'])) ?>);
<?php endforeach; ?>

function AP_CFG_nombreUsuario(id) {
    const u = AP_CFG_USUARIOS.find(x => x.id === id);
    return u ? u.nombre : ('Usuario #' + id);
}

// Si se queda sin aprobadores, no puede seguir Activo: lo apaga solo (evita guardar un estado inválido).
function AP_CFG_sinAprobadoresDesactivar(idTipo) {
    if (AP_CFG_SELECCION[idTipo].size > 0) return;
    const chk = document.getElementById('ap-req-' + idTipo);
    if (chk && chk.checked) chk.checked = false;
}

function AP_CFG_renderChips(idTipo) {
    const cont = document.getElementById('ap-chips-' + idTipo);
    const sel = AP_CFG_SELECCION[idTipo];
    cont.innerHTML = '';
    sel.forEach(uid => {
        const chip = document.createElement('span');
        chip.className = 'badge bg-primary bg-opacity-10 text-primary border border-primary d-inline-flex align-items-center gap-1';
        chip.style.fontWeight = '500';
        chip.innerHTML = `${AP_CFG_nombreUsuario(uid).replace(/</g, '&lt;')}` + (AP_CFG_PUEDE_EDITAR ? ' <i class="bi bi-x-lg" style="cursor:pointer; font-size:.65rem;"></i>' : '');
        if (AP_CFG_PUEDE_EDITAR) {
            chip.querySelector('i').addEventListener('click', () => {
                sel.delete(uid);
                AP_CFG_renderChips(idTipo);
                AP_CFG_sinAprobadoresDesactivar(idTipo);
                AP_CFG_guardar(idTipo);
            });
        }
        cont.appendChild(chip);
    });
}

function AP_CFG_setupBuscador(idTipo) {
    const input = document.getElementById('ap-buscar-' + idTipo);
    const dropdown = document.getElementById('ap-dropdown-' + idTipo);
    const wrap = document.getElementById('ap-wrap-' + idTipo);
    const sel = AP_CFG_SELECCION[idTipo];

    // El desplegable se saca de la fila de la tabla y se ancla al <body> con
    // position:fixed calculado por JS — así nunca queda recortado por el
    // overflow de la tabla/fila (antes quedaba "escondido" dentro de la fila).
    dropdown.style.position = 'fixed';
    document.body.appendChild(dropdown);

    function ocultar() { dropdown.classList.add('d-none'); dropdown.innerHTML = ''; }

    function posicionar() {
        const r = wrap.getBoundingClientRect();
        dropdown.style.bottom = 'auto';
        dropdown.style.left   = r.left + 'px';
        dropdown.style.width  = r.width + 'px';
        dropdown.style.top    = (r.bottom + 4) + 'px'; // siempre debajo del campo
    }

    function buscar() {
        const q = input.value.trim().toLowerCase();
        if (q === '') { ocultar(); return; } // no mostrar nada hasta que se escriba algo
        const candidatos = AP_CFG_USUARIOS.filter(u => !sel.has(u.id) && u.nombre.toLowerCase().includes(q)).slice(0, 8);
        if (candidatos.length === 0) { ocultar(); return; }
        dropdown.innerHTML = candidatos.map(u =>
            `<button type="button" class="list-group-item list-group-item-action small py-1 px-2" data-uid="${u.id}">${u.nombre.replace(/</g, '&lt;')}</button>`
        ).join('');
        dropdown.classList.remove('d-none');
        posicionar();
        dropdown.querySelectorAll('[data-uid]').forEach(btn => {
            btn.addEventListener('click', () => {
                sel.add(parseInt(btn.dataset.uid, 10));
                AP_CFG_renderChips(idTipo);
                input.value = '';
                ocultar();
                input.focus();
                AP_CFG_guardar(idTipo);
            });
        });
    }

    wrap.addEventListener('click', () => input.focus());

    input.addEventListener('focus', buscar);
    input.addEventListener('input', buscar);
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Backspace' && input.value === '' && sel.size > 0) {
            const ultimo = Array.from(sel).pop();
            sel.delete(ultimo);
            AP_CFG_renderChips(idTipo);
            AP_CFG_sinAprobadoresDesactivar(idTipo);
            AP_CFG_guardar(idTipo);
        }
    });
    document.addEventListener('click', (e) => {
        if (!input.contains(e.target) && !dropdown.contains(e.target)) ocultar();
    });
    document.addEventListener('scroll', ocultar, true);
    window.addEventListener('resize', ocultar);
}

document.addEventListener('DOMContentLoaded', () => {
    Object.keys(AP_CFG_SELECCION).forEach(idTipo => {
        AP_CFG_renderChips(idTipo);
        if (!AP_CFG_PUEDE_EDITAR) return;
        AP_CFG_setupBuscador(idTipo);
        document.getElementById('ap-req-' + idTipo).addEventListener('change', function () {
            if (this.checked && AP_CFG_SELECCION[idTipo].size === 0) {
                this.checked = false;
                Swal.fire('Falta un aprobador', 'Agrega al menos un usuario aprobador antes de activar este checkpoint.', 'warning');
                return;
            }
            AP_CFG_guardar(idTipo);
        });
        document.getElementById('ap-umbral-' + idTipo).addEventListener('change', () => AP_CFG_guardar(idTipo));
    });
});

async function AP_CFG_guardar(idTipo) {
    const msg = document.getElementById('ap-cfg-msg-' + idTipo);
    msg.innerHTML = '<i class="bi bi-arrow-repeat text-muted"></i>';

    const requiere = document.getElementById('ap-req-' + idTipo).checked;
    const umbral = document.getElementById('ap-umbral-' + idTipo).value;
    const aprobadores = Array.from(AP_CFG_SELECCION[idTipo]);

    const fd = new FormData();
    fd.append('id_tipo', idTipo);
    if (requiere) fd.append('requiere_aprobacion', '1');
    if (umbral !== '') fd.append('umbral_monto', umbral);
    aprobadores.forEach(uid => fd.append('usuarios_aprobadores[]', uid));

    try {
        const res = await fetch(`${AP_CFG_URL}/guardarAjax`, { method: 'POST', body: fd });
        const json = await res.json();
        msg.innerHTML = json.ok
            ? '<i class="bi bi-check-circle text-success" title="Guardado"></i>'
            : '<i class="bi bi-exclamation-circle text-danger" title="' + json.mensaje.replace(/"/g, '') + '"></i>';
    } catch (err) {
        msg.innerHTML = '<i class="bi bi-exclamation-circle text-danger" title="Error de conexión"></i>';
    }
    if (msg.querySelector('.bi-check-circle')) {
        setTimeout(() => { if (msg.querySelector('.bi-check-circle')) msg.innerHTML = ''; }, 2000);
    }
}
</script>
