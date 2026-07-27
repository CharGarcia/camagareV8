<?php
/**
 * Departamentos del taller.
 *
 * Cada taller arma su propio flujo: los departamentos que se definan aquí son
 * las columnas del tablero y las pantallas de tablet de modulos/taller-estacion.
 *
 * El checklist de recepción es otro módulo: modulos/taller-checklist.
 *
 * @var string $titulo
 * @var array  $perm
 * @var string $rutaModulo
 * @var array  $rows
 * @var int    $total
 * @var int    $page
 * @var int    $totalPages
 * @var string $buscar
 * @var array  $vistaConfig
 */
$base    = rtrim(BASE_URL, '/');
$urlBase = $base . '/' . ltrim($rutaModulo, '/');
?>

<style>
    .dep-scroll { max-height: calc(100dvh - 230px); overflow-y: auto; }
    .dep-scroll thead th {
        position: sticky; top: 0; z-index: 1;
        background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6;
    }
    .dep-color { width: 14px; height: 14px; border-radius: 4px; display: inline-block; }
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-diagram-3 text-primary"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if (!empty($perm['crear']) || !empty($perm['todo'])): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="depNuevo()">
            <i class="bi bi-plus-lg"></i> Nuevo
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="small text-muted">
            <i class="bi bi-list-ol me-1"></i>
            El orden define cómo se muestran en el tablero y en las tablets.
        </div>
        <span class="text-muted small fw-medium"><?= (int) $total ?> departamento(s)</span>
    </div>
    <div class="card-body p-0">
                <div class="dep-scroll">
                    <table class="table table-hover table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3" style="width:60px">Orden</th>
                                <th>Departamento</th>
                                <th style="width:90px">Código</th>
                                <th class="text-center" style="width:110px">Rol</th>
                                <th class="text-center" style="width:80px">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rows)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="bi bi-diagram-3 fs-3 d-block mb-2"></i>
                                        Todavía no hay departamentos. Cree los del taller: mecánica, enderezada, pintura, armado…
                                    </td>
                                </tr>
                            <?php else: foreach ($rows as $r): ?>
                                <tr role="button" onclick='depEditar(<?= json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                    <td class="ps-3 text-muted"><?= (int) ($r['orden'] ?? 0) ?></td>
                                    <td>
                                        <span class="dep-color me-2" style="background:<?= htmlspecialchars($r['color'] ?? '#0d6efd') ?>"></span>
                                        <i class="bi <?= htmlspecialchars($r['icono'] ?? 'bi-tools') ?> text-muted me-1"></i>
                                        <span class="fw-semibold"><?= htmlspecialchars($r['nombre'] ?? '') ?></span>
                                        <?php if (!empty($r['descripcion'])): ?>
                                            <div class="small text-muted"><?= htmlspecialchars($r['descripcion']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted"><?= htmlspecialchars($r['codigo'] ?? '') ?></td>
                                    <td class="text-center">
                                        <?php if (\App\Helpers\Booleano::es($r['es_diagnostico'] ?? false)): ?>
                                            <span class="badge bg-info bg-opacity-10 text-info">Diagnóstico</span>
                                        <?php elseif (\App\Helpers\Booleano::es($r['es_control_calidad'] ?? false)): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success">Calidad</span>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if (\App\Helpers\Booleano::es($r['activo'] ?? false)): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
    </div>
</div>

<!-- Modal del departamento -->
<div class="modal fade" id="modalDepartamento" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="depTitulo">Nuevo departamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="dep_id">
                <div class="row g-2">
                    <div class="col-8">
                        <label class="form-label small mb-1 fw-bold text-muted">Nombre <span class="text-danger">*</span></label>
                        <input type="text" id="dep_nombre" class="form-control form-control-sm" maxlength="100" placeholder="Ej. Pintura">
                    </div>
                    <div class="col-4">
                        <label class="form-label small mb-1 fw-bold text-muted">Código</label>
                        <input type="text" id="dep_codigo" class="form-control form-control-sm" maxlength="20" placeholder="PIN">
                    </div>
                    <div class="col-12">
                        <label class="form-label small mb-1 fw-bold text-muted">Descripción</label>
                        <input type="text" id="dep_descripcion" class="form-control form-control-sm" maxlength="300">
                    </div>
                    <div class="col-4">
                        <label class="form-label small mb-1 fw-bold text-muted">Color</label>
                        <input type="color" id="dep_color" class="form-control form-control-sm form-control-color" value="#0d6efd">
                    </div>
                    <div class="col-4">
                        <label class="form-label small mb-1 fw-bold text-muted">Ícono</label>
                        <select id="dep_icono" class="form-select form-select-sm">
                            <option value="bi-tools">Herramientas</option>
                            <option value="bi-clipboard-pulse">Diagnóstico</option>
                            <option value="bi-gear">Motor</option>
                            <option value="bi-cone-striped">Suspensión</option>
                            <option value="bi-record-circle">Frenos</option>
                            <option value="bi-lightning-charge">Electricidad</option>
                            <option value="bi-snow">Aire acondicionado</option>
                            <option value="bi-hammer">Enderezada</option>
                            <option value="bi-brush">Preparación</option>
                            <option value="bi-palette">Pintura</option>
                            <option value="bi-stars">Pulido</option>
                            <option value="bi-nut">Armado</option>
                            <option value="bi-droplet-half">Lavado</option>
                            <option value="bi-patch-check">Control de calidad</option>
                            <option value="bi-box-seam">Desarme</option>
                        </select>
                    </div>
                    <div class="col-4">
                        <label class="form-label small mb-1 fw-bold text-muted">Orden</label>
                        <input type="number" id="dep_orden" class="form-control form-control-sm" value="0" min="0">
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="dep_es_diagnostico">
                            <label class="form-check-label small" for="dep_es_diagnostico">
                                Es el departamento de diagnóstico
                                <span class="text-muted d-block x-small">Puede trabajar antes de que el cliente apruebe el presupuesto.</span>
                            </label>
                        </div>
                        <div class="form-check form-switch mt-1">
                            <input class="form-check-input" type="checkbox" id="dep_es_control_calidad">
                            <label class="form-check-label small" for="dep_es_control_calidad">Es el departamento de control de calidad</label>
                        </div>
                        <div class="form-check form-switch mt-1">
                            <input class="form-check-input" type="checkbox" id="dep_activo" checked>
                            <label class="form-check-label small" for="dep_activo">Activo</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between bg-light border-top p-2">
                <div>
                    <button type="button" class="btn btn-outline-danger btn-sm px-3 d-none" id="dep_btn_eliminar" onclick="depEliminar()">
                        <i class="bi bi-trash3 me-1"></i> Eliminar
                    </button>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-sm px-4" onclick="depGuardar()">
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
    const PUEDE_ELIMINAR = <?= (!empty($perm['eliminar']) || !empty($perm['todo'])) ? 'true' : 'false' ?>;
    let modal = null;

    const $ = (id) => document.getElementById(id);
    const val = (id) => ($(id) ? $(id).value.trim() : '');
    const setVal = (id, v) => { if ($(id)) $(id).value = (v === null || v === undefined) ? '' : v; };
    const error = (m) => Swal.fire('Atención', m || 'Ocurrió un error.', 'error');

    async function post(url, body) {
        const fd = new FormData();
        Object.entries(body || {}).forEach(([k, v]) => fd.append(k, v === null || v === undefined ? '' : v));
        const res = await fetch(url, { method: 'POST', body: fd });
        return res.json();
    }

    window.depNuevo = function () {
        setVal('dep_id', '');
        setVal('dep_nombre', '');
        setVal('dep_codigo', '');
        setVal('dep_descripcion', '');
        setVal('dep_color', '#0d6efd');
        setVal('dep_icono', 'bi-tools');
        setVal('dep_orden', '0');
        $('dep_es_diagnostico').checked = false;
        $('dep_es_control_calidad').checked = false;
        $('dep_activo').checked = true;
        $('depTitulo').textContent = 'Nuevo departamento';
        $('dep_btn_eliminar').classList.add('d-none');

        modal = modal || new bootstrap.Modal($('modalDepartamento'));
        modal.show();
    };

    window.depEditar = function (d) {
        setVal('dep_id', d.id);
        setVal('dep_nombre', d.nombre || '');
        setVal('dep_codigo', d.codigo || '');
        setVal('dep_descripcion', d.descripcion || '');
        setVal('dep_color', d.color || '#0d6efd');
        setVal('dep_icono', d.icono || 'bi-tools');
        setVal('dep_orden', d.orden || 0);
        $('dep_es_diagnostico').checked = (d.es_diagnostico === true || d.es_diagnostico === 't');
        $('dep_es_control_calidad').checked = (d.es_control_calidad === true || d.es_control_calidad === 't');
        $('dep_activo').checked = (d.activo === true || d.activo === 't');
        $('depTitulo').textContent = 'Editar departamento';
        $('dep_btn_eliminar').classList.toggle('d-none', !PUEDE_ELIMINAR);

        modal = modal || new bootstrap.Modal($('modalDepartamento'));
        modal.show();
    };

    window.depGuardar = async function () {
        if (!val('dep_nombre')) return error('Escriba el nombre del departamento.');

        const res = await fetch(`${RUTA}/store`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: val('dep_id') || null,
                nombre: val('dep_nombre'),
                codigo: val('dep_codigo'),
                descripcion: val('dep_descripcion'),
                color: val('dep_color'),
                icono: val('dep_icono'),
                orden: val('dep_orden') || 0,
                es_diagnostico: $('dep_es_diagnostico').checked,
                es_control_calidad: $('dep_es_control_calidad').checked,
                activo: $('dep_activo').checked
            })
        });
        const data = await res.json();
        if (!data.ok) return error(data.error);
        window.location.reload();
    };

    window.depEliminar = async function () {
        const c = await Swal.fire({
            title: '¿Eliminar el departamento?', icon: 'warning', showCancelButton: true,
            confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545'
        });
        if (!c.isConfirmed) return;

        const data = await post(`${RUTA}/eliminar`, { id: val('dep_id') });
        if (!data.ok) return error(data.error);
        window.location.reload();
    };

})();
</script>
