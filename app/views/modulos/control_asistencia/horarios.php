<?php

/** @var string $titulo @var array $perm @var string $rutaModulo */
/** @var array $horarios @var array $asignaciones @var array $empleados @var array $puntos */

$base = BASE_URL;
$urlBase = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');
$h = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$diasLbl = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];
$nombreDias = function (string $csv) use ($diasLbl) {
    $out = [];
    foreach (array_filter(array_map('trim', explode(',', $csv))) as $d) {
        $out[] = $diasLbl[(int) $d] ?? $d;
    }
    return implode(' ', $out);
};
?>

<style>.casis-scroll { max-height: 40vh; overflow-y: auto; }</style>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2 text-primary"></i> <?= $h($titulo) ?></h5>
    <div class="d-flex align-items-center gap-2">
        <a href="<?= $urlBase ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-geo-alt-fill me-1"></i> Puntos</a>
        <a href="<?= $urlBase ?>/jornadas" class="btn btn-outline-secondary btn-sm"><i class="bi bi-calendar-check me-1"></i> Jornadas</a>
    </div>
</div>

<!-- TURNOS -->
<div class="card border-0 shadow-sm rounded-3 mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-2 px-3">
        <span class="fw-bold"><i class="bi bi-clock me-1 text-primary"></i> Turnos</span>
        <?php if ($perm['crear']): ?>
        <button class="btn btn-primary btn-sm" onclick="abrirModalHorario()"><i class="bi bi-plus-lg me-1"></i> Nuevo turno</button>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="casis-scroll">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light"><tr>
                    <th class="ps-3">Nombre</th><th class="text-center">Horario</th><th class="text-center">Tolerancia</th>
                    <th class="text-center">Horas</th><th>Días</th><th class="text-center">Estado</th><th style="width:40px;"></th>
                </tr></thead>
                <tbody>
                    <?php if (empty($horarios)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">Sin turnos. Crea uno para poder calcular jornadas.</td></tr>
                    <?php else: foreach ($horarios as $t):
                        $activo = ($t['estado'] ?? 'activo') === 'activo'; ?>
                        <tr role="button" data-row='<?= $h(json_encode($t)) ?>' onclick="abrirModalHorario(this)">
                            <td class="ps-3 fw-medium"><?= $h($t['nombre']) ?></td>
                            <td class="text-center"><?= $h(substr((string)$t['hora_entrada'],0,5)) ?> – <?= $h(substr((string)$t['hora_salida'],0,5)) ?><?= !empty($t['cruza_medianoche']) ? ' <span class="badge bg-dark bg-opacity-10 text-dark">+1d</span>' : '' ?></td>
                            <td class="text-center"><?= (int)$t['tolerancia_min'] ?> min</td>
                            <td class="text-center"><?= $h(number_format((float)$t['horas_jornada'],1)) ?></td>
                            <td class="small text-muted"><?= $h($nombreDias((string)$t['dias_semana'])) ?></td>
                            <td class="text-center"><span class="badge bg-<?= $activo?'success':'secondary' ?> bg-opacity-10 text-<?= $activo?'success':'secondary' ?> border border-<?= $activo?'success':'secondary' ?> border-opacity-25"><?= $activo?'Activo':'Inactivo' ?></span></td>
                            <td class="text-center pe-3" onclick="event.stopPropagation()"><?php if ($perm['eliminar']): ?><button class="btn btn-outline-danger btn-xs border-0" onclick="eliminarHorario(<?= (int)$t['id'] ?>)"><i class="bi bi-trash"></i></button><?php endif; ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ASIGNACIONES -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-2 px-3">
        <span class="fw-bold"><i class="bi bi-person-check me-1 text-primary"></i> Asignaciones (empleado → turno → punto)</span>
        <?php if ($perm['crear']): ?>
        <button class="btn btn-primary btn-sm" onclick="abrirModalAsignacion()"><i class="bi bi-plus-lg me-1"></i> Asignar</button>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="casis-scroll">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light"><tr>
                    <th class="ps-3">Empleado</th><th>Turno</th><th>Punto</th><th class="text-center">Desde</th><th class="text-center">Hasta</th><th style="width:40px;"></th>
                </tr></thead>
                <tbody>
                    <?php if (empty($asignaciones)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">Sin asignaciones. Asigna un turno a cada empleado (define su punto y detecta faltas).</td></tr>
                    <?php else: foreach ($asignaciones as $a): ?>
                        <tr>
                            <td class="ps-3 fw-medium"><?= $h($a['empleado_nombre']) ?></td>
                            <td><?= $h($a['horario_nombre']) ?></td>
                            <td class="small text-muted"><?= $h($a['punto_nombre'] ?? '—') ?></td>
                            <td class="text-center"><?= $a['vigente_desde'] ? date('d-m-Y', strtotime((string)$a['vigente_desde'])) : '—' ?></td>
                            <td class="text-center"><?= $a['vigente_hasta'] ? date('d-m-Y', strtotime((string)$a['vigente_hasta'])) : '<span class="text-success">vigente</span>' ?></td>
                            <td class="text-center pe-3"><?php if ($perm['eliminar']): ?><button class="btn btn-outline-danger btn-xs border-0" onclick="eliminarAsignacion(<?= (int)$a['id'] ?>)"><i class="bi bi-trash"></i></button><?php endif; ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Turno -->
<div class="modal fade" id="modalHorario" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow-lg">
    <div class="modal-header bg-light py-3"><h5 class="modal-title fw-bold"><i class="bi bi-clock me-2 text-primary"></i><span id="horarioTitulo">Nuevo turno</span></h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <input type="hidden" id="hor_id">
        <div class="mb-3"><label class="form-label small fw-semibold">Nombre <span class="text-danger">*</span></label><input id="hor_nombre" class="form-control form-control-sm" placeholder="Ej: Guardia diurno 8h"></div>
        <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label small fw-semibold">Hora entrada <span class="text-danger">*</span></label><input type="time" id="hor_entrada" class="form-control form-control-sm"></div>
            <div class="col-6"><label class="form-label small fw-semibold">Hora salida <span class="text-danger">*</span></label><input type="time" id="hor_salida" class="form-control form-control-sm"></div>
        </div>
        <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="hor_cruza"><label class="form-check-label small" for="hor_cruza">La salida es al día siguiente (turno nocturno)</label></div>
        <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label small fw-semibold">Tolerancia (min)</label><input type="number" id="hor_tolerancia" class="form-control form-control-sm" value="5" min="0" max="240"></div>
            <div class="col-6"><label class="form-label small fw-semibold">Horas de jornada</label><input type="number" step="0.5" id="hor_horas" class="form-control form-control-sm" value="8" min="0" max="24"></div>
        </div>
        <label class="form-label small fw-semibold d-block">Días laborables</label>
        <div class="d-flex flex-wrap gap-2 mb-3" id="hor_dias">
            <?php foreach ($diasLbl as $n => $l): ?>
            <div class="form-check"><input class="form-check-input dia-chk" type="checkbox" value="<?= $n ?>" id="dia<?= $n ?>" <?= $n <= 5 ? 'checked' : '' ?>><label class="form-check-label small" for="dia<?= $n ?>"><?= $l ?></label></div>
            <?php endforeach; ?>
        </div>
        <div class="mb-1"><label class="form-label small fw-semibold">Estado</label><select id="hor_estado" class="form-select form-select-sm"><option value="activo">Activo</option><option value="inactivo">Inactivo</option></select></div>
    </div>
    <div class="modal-footer bg-light p-2">
        <button class="btn btn-outline-danger btn-sm me-auto" id="btnEliminarHorario" style="display:none" onclick="eliminarHorarioModal()"><i class="bi bi-trash me-1"></i>Eliminar</button>
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
        <button class="btn btn-primary btn-sm px-4" onclick="guardarHorario()"><i class="bi bi-check-lg me-1"></i>Guardar</button>
    </div>
</div></div></div>

<!-- Modal Asignación -->
<div class="modal fade" id="modalAsignacion" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow-lg">
    <div class="modal-header bg-light py-3"><h5 class="modal-title fw-bold"><i class="bi bi-person-check me-2 text-primary"></i>Asignar turno</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-2"><label class="form-label small fw-semibold">Empleado <span class="text-danger">*</span></label>
            <select id="asg_empleado" class="form-select form-select-sm"><option value="">— Seleccionar —</option>
                <?php foreach ($empleados as $e): ?><option value="<?= (int)$e['id'] ?>"><?= $h($e['nombres_apellidos']) ?> (<?= $h($e['identificacion']) ?>)</option><?php endforeach; ?>
            </select></div>
        <div class="mb-2"><label class="form-label small fw-semibold">Turno <span class="text-danger">*</span></label>
            <select id="asg_horario" class="form-select form-select-sm"><option value="">— Seleccionar —</option>
                <?php foreach ($horarios as $t): ?><option value="<?= (int)$t['id'] ?>"><?= $h($t['nombre']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="mb-2"><label class="form-label small fw-semibold">Punto de servicio</label>
            <select id="asg_punto" class="form-select form-select-sm"><option value="">— Sin punto fijo —</option>
                <?php foreach ($puntos as $p): ?><option value="<?= (int)$p['id'] ?>"><?= $h($p['nombre']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="row g-2">
            <div class="col-6"><label class="form-label small fw-semibold">Vigente desde <span class="text-danger">*</span></label><input type="date" id="asg_desde" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>"></div>
            <div class="col-6"><label class="form-label small fw-semibold">Vigente hasta</label><input type="date" id="asg_hasta" class="form-control form-control-sm"></div>
        </div>
    </div>
    <div class="modal-footer bg-light p-2">
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
        <button class="btn btn-primary btn-sm px-4" onclick="guardarAsignacion()"><i class="bi bi-check-lg me-1"></i>Asignar</button>
    </div>
</div></div></div>

<script>
(function () {
    'use strict';
    const urlBase = '<?= $urlBase ?>';
    let mHor = null, mAsg = null;
    const err = (m) => window.Swal ? Swal.fire({icon:'error',title:'Error',text:m}) : alert(m);
    const okReload = (m) => { if (window.Swal) Swal.fire({icon:'success',title:m,timer:900,showConfirmButton:false}).then(()=>location.reload()); else location.reload(); };
    const getHor = () => (mHor = mHor || new bootstrap.Modal(document.getElementById('modalHorario')));
    const getAsg = () => (mAsg = mAsg || new bootstrap.Modal(document.getElementById('modalAsignacion')));

    window.abrirModalHorario = function (tr) {
        let d = {};
        if (tr) { try { d = JSON.parse(tr.getAttribute('data-row')); } catch(e){} }
        document.getElementById('horarioTitulo').textContent = d.id ? 'Editar turno' : 'Nuevo turno';
        document.getElementById('btnEliminarHorario').style.display = d.id ? '' : 'none';
        document.getElementById('hor_id').value = d.id || '';
        document.getElementById('hor_nombre').value = d.nombre || '';
        document.getElementById('hor_entrada').value = (d.hora_entrada || '').substring(0,5);
        document.getElementById('hor_salida').value = (d.hora_salida || '').substring(0,5);
        document.getElementById('hor_cruza').checked = (d.cruza_medianoche === true || d.cruza_medianoche === 't');
        document.getElementById('hor_tolerancia').value = d.tolerancia_min ?? 5;
        document.getElementById('hor_horas').value = d.horas_jornada ?? 8;
        document.getElementById('hor_estado').value = d.estado || 'activo';
        const dias = (d.dias_semana || '1,2,3,4,5').split(',').map(s => s.trim());
        document.querySelectorAll('.dia-chk').forEach(c => c.checked = dias.includes(c.value));
        getHor().show();
    };

    window.guardarHorario = function () {
        const id = document.getElementById('hor_id').value.trim();
        const dias = Array.from(document.querySelectorAll('.dia-chk')).filter(c=>c.checked).map(c=>c.value).join(',');
        const fd = new FormData();
        if (id) fd.append('id', id);
        fd.append('nombre', document.getElementById('hor_nombre').value.trim());
        fd.append('hora_entrada', document.getElementById('hor_entrada').value);
        fd.append('hora_salida', document.getElementById('hor_salida').value);
        if (document.getElementById('hor_cruza').checked) fd.append('cruza_medianoche','1');
        fd.append('tolerancia_min', document.getElementById('hor_tolerancia').value);
        fd.append('horas_jornada', document.getElementById('hor_horas').value);
        fd.append('dias_semana', dias);
        fd.append('estado', document.getElementById('hor_estado').value);
        fetch(`${urlBase}/${id?'updateHorario':'storeHorario'}`, {method:'POST',body:fd})
            .then(r=>r.json()).then(j=> j.ok ? okReload(j.msg) : err(j.error)).catch(()=>err('Error de red.'));
    };

    window.eliminarHorario = function (id) {
        const run = () => { const fd=new FormData(); fd.append('id_eliminar',id);
            fetch(`${urlBase}/deleteHorario`,{method:'POST',body:fd}).then(r=>r.json()).then(j=> j.ok?okReload(j.msg):err(j.error)); };
        if (window.Swal) Swal.fire({icon:'warning',title:'¿Eliminar turno?',showCancelButton:true,confirmButtonText:'Eliminar',confirmButtonColor:'#dc3545'}).then(r=>{if(r.isConfirmed)run();});
        else if (confirm('¿Eliminar turno?')) run();
    };
    window.eliminarHorarioModal = function () { const id=document.getElementById('hor_id').value.trim(); if(id){getHor().hide();window.eliminarHorario(id);} };

    window.abrirModalAsignacion = function () { getAsg().show(); };
    window.guardarAsignacion = function () {
        const fd = new FormData();
        fd.append('id_empleado', document.getElementById('asg_empleado').value);
        fd.append('id_horario', document.getElementById('asg_horario').value);
        fd.append('id_punto', document.getElementById('asg_punto').value);
        fd.append('vigente_desde', document.getElementById('asg_desde').value);
        fd.append('vigente_hasta', document.getElementById('asg_hasta').value);
        fetch(`${urlBase}/asignarHorario`, {method:'POST',body:fd})
            .then(r=>r.json()).then(j=> j.ok ? okReload(j.msg) : err(j.error)).catch(()=>err('Error de red.'));
    };
    window.eliminarAsignacion = function (id) {
        const run = () => { const fd=new FormData(); fd.append('id_eliminar',id);
            fetch(`${urlBase}/eliminarAsignacion`,{method:'POST',body:fd}).then(r=>r.json()).then(j=> j.ok?okReload(j.msg):err(j.error)); };
        if (window.Swal) Swal.fire({icon:'warning',title:'¿Eliminar asignación?',showCancelButton:true,confirmButtonText:'Eliminar',confirmButtonColor:'#dc3545'}).then(r=>{if(r.isConfirmed)run();});
        else if (confirm('¿Eliminar asignación?')) run();
    };
})();
</script>
