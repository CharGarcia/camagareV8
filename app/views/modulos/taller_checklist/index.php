<?php
/**
 * Checklist de recepción del taller.
 *
 * Lo que se revisa al recibir cada vehículo, agrupado en accesorios,
 * carrocería, documentos y niveles. Se copia a cada orden como evidencia del
 * estado en que llegó el carro.
 *
 * @var string $titulo
 * @var array  $perm
 * @var string $rutaModulo
 * @var array  $rows
 * @var int    $total
 * @var string $buscar
 * @var string $ordenCol
 * @var string $ordenDir
 * @var array  $vistaConfig
 * @var array  $grupos      clave => etiqueta
 */
$base    = rtrim(BASE_URL, '/');
$urlBase = $base . '/' . ltrim($rutaModulo, '/');

$puedeCrear   = !empty($perm['crear']) || !empty($perm['todo']);
$puedeEditar  = !empty($perm['actualizar']) || !empty($perm['todo']);
$puedeBorrar  = !empty($perm['eliminar']) || !empty($perm['todo']);

// Se muestra agrupado por grupo: así se lee igual que en la orden.
$porGrupo = [];
foreach ($rows as $r) {
    $porGrupo[$r['grupo'] ?? 'accesorios'][] = $r;
}
?>

<style>
    .chk-scroll { max-height: calc(100dvh - 230px); overflow-y: auto; }
    .chk-scroll thead th {
        position: sticky; top: 0; z-index: 1;
        background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6;
    }
    .chk-grupo td { background: #eef1f5; font-weight: 700; font-size: .72rem; text-transform: uppercase; letter-spacing: .03em; }
    .chk-row { cursor: pointer; }
    .chk-row:hover { background-color: rgba(0, 0, 0, .04); }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-list-check text-primary"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if ($puedeCrear): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="chkNuevo()">
            <i class="bi bi-plus-lg"></i> Nuevo
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <form method="GET" action="<?= $urlBase ?>" class="d-flex gap-2">
                <input type="text" name="b" class="form-control form-control-sm" style="width:260px"
                       placeholder="Buscar punto de revisión..." value="<?= htmlspecialchars($buscar) ?>">
                <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-search"></i></button>
            </form>
        </div>
        <span class="text-muted small fw-medium"><?= (int) $total ?> punto(s)</span>
    </div>

    <div class="card-body p-0">
        <div class="chk-scroll w-100">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width:80px" data-col="orden">Orden</th>
                        <th data-col="item">Qué se revisa</th>
                        <th class="text-center" style="width:110px" data-col="activo">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="3" class="text-center py-5 text-muted">
                                <i class="bi bi-list-check fs-3 d-block mb-2"></i>
                                Todavía no hay puntos de revisión. Agregue lo que el taller revisa al recibir un
                                vehículo: llanta de emergencia, gata, documentos, nivel de aceite…
                            </td>
                        </tr>
                    <?php else: foreach ($porGrupo as $grupo => $items): ?>
                        <tr class="chk-grupo">
                            <td colspan="3" class="ps-3 text-muted">
                                <?= htmlspecialchars($grupos[$grupo] ?? $grupo) ?>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary ms-1"><?= count($items) ?></span>
                            </td>
                        </tr>
                        <?php foreach ($items as $r): ?>
                            <tr class="chk-row" onclick='chkEditar(<?= json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                <td class="ps-3 text-muted" data-col="orden"><?= (int) ($r['orden'] ?? 0) ?></td>
                                <td data-col="item"><?= htmlspecialchars($r['item'] ?? '') ?></td>
                                <td class="text-center" data-col="activo">
                                    <?php if (\App\Helpers\Booleano::es($r['activo'] ?? false)): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal del punto de revisión -->
<div class="modal fade" id="modalChecklist" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold" id="chkTitulo">Nuevo punto de revisión</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="chk_id">
                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label small mb-1 fw-bold text-muted">Grupo</label>
                        <select id="chk_grupo" class="form-select form-select-sm">
                            <?php foreach ($grupos as $k => $lbl): ?>
                                <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small mb-1 fw-bold text-muted">Qué se revisa <span class="text-danger">*</span></label>
                        <input type="text" id="chk_item" class="form-control form-control-sm" maxlength="150" placeholder="Ej. Llanta de emergencia">
                    </div>
                    <div class="col-6">
                        <label class="form-label small mb-1 fw-bold text-muted">Orden</label>
                        <input type="number" id="chk_orden" class="form-control form-control-sm" min="0" value="0">
                    </div>
                    <div class="col-6 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="chk_activo" checked>
                            <label class="form-check-label small" for="chk_activo">Activo</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="small text-muted">
                            Los cambios aplican a las órdenes nuevas. Las ya registradas conservan lo que se
                            revisó ese día.
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between bg-light border-top p-2">
                <div>
                    <button type="button" class="btn btn-outline-danger btn-sm px-3 d-none" id="chk_btn_eliminar" onclick="chkEliminar()">
                        <i class="bi bi-trash3 me-1"></i> Eliminar
                    </button>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-sm px-4" onclick="chkGuardar()">
                        <i class="bi bi-check2-circle me-1"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    const RUTA = '<?= $urlBase ?>';
    const PUEDE_EDITAR = <?= $puedeEditar ? 'true' : 'false' ?>;
    const PUEDE_BORRAR = <?= $puedeBorrar ? 'true' : 'false' ?>;
    let modal = null;

    const $ = (id) => document.getElementById(id);
    const val = (id) => ($(id) ? String($(id).value).trim() : '');
    const setVal = (id, v) => { if ($(id)) $(id).value = (v === null || v === undefined) ? '' : v; };
    const error = (m) => Swal.fire('Atención', m || 'Ocurrió un error.', 'error');

    async function post(url, body) {
        const fd = new FormData();
        Object.entries(body || {}).forEach(([k, v]) => fd.append(k, v === null || v === undefined ? '' : v));
        const res = await fetch(url, { method: 'POST', body: fd });
        return res.json();
    }

    window.chkNuevo = function () {
        setVal('chk_id', '');
        setVal('chk_grupo', 'accesorios');
        setVal('chk_item', '');
        setVal('chk_orden', '0');
        $('chk_activo').checked = true;
        $('chkTitulo').textContent = 'Nuevo punto de revisión';
        $('chk_btn_eliminar').classList.add('d-none');

        modal = modal || new bootstrap.Modal($('modalChecklist'));
        modal.show();
        setTimeout(() => $('chk_item').focus(), 300);
    };

    window.chkEditar = function (r) {
        if (!PUEDE_EDITAR) return;

        setVal('chk_id', r.id);
        setVal('chk_grupo', r.grupo || 'accesorios');
        setVal('chk_item', r.item || '');
        setVal('chk_orden', r.orden || 0);
        $('chk_activo').checked = (r.activo === true || r.activo === 't');
        $('chkTitulo').textContent = 'Editar punto de revisión';
        $('chk_btn_eliminar').classList.toggle('d-none', !PUEDE_BORRAR);

        modal = modal || new bootstrap.Modal($('modalChecklist'));
        modal.show();
    };

    window.chkGuardar = async function () {
        if (!val('chk_item')) return error('Escriba qué se revisa.');

        const res = await fetch(`${RUTA}/store`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: val('chk_id') || null,
                grupo: val('chk_grupo'),
                item: val('chk_item'),
                orden: val('chk_orden') || 0,
                activo: $('chk_activo').checked
            })
        });
        const data = await res.json();
        if (!data.ok) return error(data.error);
        window.location.reload();
    };

    window.chkEliminar = async function () {
        const c = await Swal.fire({
            title: '¿Eliminar el punto de revisión?',
            text: 'Dejará de aparecer en las órdenes nuevas.',
            icon: 'warning', showCancelButton: true,
            confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545'
        });
        if (!c.isConfirmed) return;

        const data = await post(`${RUTA}/eliminar`, { id: val('chk_id') });
        if (!data.ok) return error(data.error);
        window.location.reload();
    };
})();
</script>
