<?php

/** @var string $titulo @var array $perm @var string $rutaModulo */
/** @var array $rows @var int $total @var int $page @var int $totalPages @var int $perPage @var string $buscar @var string $ordenCol @var string $ordenDir @var array $vistaConfig */

use App\Helpers\PreferenciasHelper;

$base = BASE_URL;
$urlBase = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');
$vistaConfig = $vistaConfig ?? [];
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
$diasLbl = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];
$nombreDias = function (string $csv) use ($diasLbl) {
    $out = [];
    foreach (array_filter(array_map('trim', explode(',', $csv))) as $d) $out[] = $diasLbl[(int) $d] ?? $d;
    return implode(' ', $out);
};
?>

<style>
    .horarios-scroll { max-height: calc(100dvh - 250px); overflow-y: auto; }
    .horarios-scroll thead th { position: sticky; top: 0; z-index: 10; background: #f8f9fa; }
    .horario-row { cursor: pointer; }
</style>

<?= PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig) ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2 text-primary"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="abrirModalHorario()"><i class="bi bi-plus-lg me-1"></i> Nuevo</button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_busqueda.css?v=<?= time() ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_busqueda.js?v=<?= time() ?>"></script>
            <div id="fbBuscadorHOR" style="width: 420px;"></div>
            <input type="hidden" id="buscarHor" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosBusqueda) return;
                    new FiltrosBusqueda({
                        containerId: 'fbBuscadorHOR',
                        hiddenInputId: 'buscarHor',
                        fields: [
                            { key: 'nombre', label: 'Nombre', icon: 'bi-clock', type: 'text' },
                            { key: 'estado', label: 'Estado', icon: 'bi-flag', type: 'select', options: [
                                { v: 'activo', l: 'Activo' }, { v: 'inactivo', l: 'Inactivo' }
                            ]},
                        ],
                        quickFilters: [
                            { id: 'qf_act', label: 'Activos', mk: () => ({ key: 'estado', op: '=', value: 'activo', display: 'Activo' }) },
                        ],
                        onApply: () => window.cambiarPaginaAjax && window.cambiarPaginaAjax(1),
                    }).init();
                });
            </script>
            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = ['nombre' => 'Nombre', 'horario' => 'Horario', 'tolerancia' => 'Tolerancia', 'horas' => 'Horas', 'dias' => 'Días', 'estado' => 'Estado'];
                ?>
                <?= PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig, $rutaModulo) ?>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?> / <?= $total ?></span>
            <div id="wrapper-pagination" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(<?= $page - 1 ?>)" <?= $page <= 1 ? 'disabled' : '' ?>><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(<?= $page + 1 ?>)" <?= $page >= $totalPages ? 'disabled' : '' ?>><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="horarios-scroll">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light shadow-sm">
                    <tr>
                        <th class="ps-3 sortable-header" data-sort="nombre" role="button" data-col="nombre">Nombre <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" data-sort="hora_entrada" role="button" data-col="horario">Horario <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center" data-col="tolerancia">Tolerancia</th>
                        <th class="text-center sortable-header" data-sort="horas_jornada" role="button" data-col="horas">Horas <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th data-col="dias">Días</th>
                        <th class="text-center sortable-header" data-sort="estado" role="button" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center" style="width: 40px;"></th>
                    </tr>
                </thead>
                <tbody id="tbodyHorarios">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">No hay turnos registrados. Crea uno para poder calcular jornadas.</td></tr>
                    <?php else: foreach ($rows as $t):
                        $activo = ($t['estado'] ?? 'activo') === 'activo';
                        $cruza = !empty($t['cruza_medianoche']);
                    ?>
                        <tr class="horario-row" role="button" data-row='<?= htmlspecialchars(json_encode($t), ENT_QUOTES) ?>' onclick="abrirModalHorario(this)">
                            <td class="ps-3 fw-medium" data-col="nombre"><?= htmlspecialchars((string) $t['nombre']) ?></td>
                            <td class="text-center" data-col="horario"><?= htmlspecialchars(substr((string) $t['hora_entrada'], 0, 5)) ?> – <?= htmlspecialchars(substr((string) $t['hora_salida'], 0, 5)) ?><?= $cruza ? ' <span class="badge bg-dark bg-opacity-10 text-dark">+1d</span>' : '' ?></td>
                            <td class="text-center" data-col="tolerancia"><?= (int) $t['tolerancia_min'] ?> min</td>
                            <td class="text-center" data-col="horas"><?= htmlspecialchars(number_format((float) $t['horas_jornada'], 1)) ?></td>
                            <td class="small text-muted" data-col="dias"><?= htmlspecialchars($nombreDias((string) $t['dias_semana'])) ?></td>
                            <td class="text-center" data-col="estado"><span class="badge bg-<?= $activo ? 'success' : 'secondary' ?> bg-opacity-10 text-<?= $activo ? 'success' : 'secondary' ?> border border-<?= $activo ? 'success' : 'secondary' ?> border-opacity-25"><?= $activo ? 'Activo' : 'Inactivo' ?></span></td>
                            <td class="text-center pe-3" onclick="event.stopPropagation()"><?php if ($perm['eliminar']): ?><button class="btn btn-outline-danger btn-xs border-0 px-2" onclick="eliminarHorario(<?= (int) $t['id'] ?>)" title="Eliminar"><i class="bi bi-trash"></i></button><?php endif; ?></td>
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

<script>
(function () {
    'use strict';
    const urlBase = '<?= $urlBase ?>';
    const inputB = document.getElementById('buscarHor');
    let currentSort = '<?= $ordenCol ?>', currentDir = '<?= $ordenDir ?>', mHor = null;
    const err = (m) => window.Swal ? Swal.fire({ icon: 'error', title: 'Error', text: m }) : alert(m);
    const getHor = () => (mHor = mHor || new bootstrap.Modal(document.getElementById('modalHorario')));

    window.cambiarPaginaAjax = (p) => cargarListado(p);

    async function cargarListado(page = 1) {
        const b = inputB ? inputB.value.trim() : '';
        const uri = `${urlBase}/searchAjax?b=${encodeURIComponent(b)}&page=${page}&sort=${currentSort}&dir=${currentDir}`;
        try {
            const resp = await fetch(uri); const data = await resp.json();
            if (data.ok) {
                window.currentPage = page;
                document.getElementById('tbodyHorarios').innerHTML = data.rows;
                document.getElementById('wrapper-pagination').innerHTML = data.pagination;
                document.getElementById('paginationInfo').textContent = data.info;
                document.querySelectorAll('.sortable-header').forEach(th => {
                    const icon = th.querySelector('i'); if (!icon) return;
                    if (th.dataset.sort === currentSort) icon.className = (currentDir.toLowerCase() === 'asc') ? 'bi bi-sort-down-alt text-primary ms-1' : 'bi bi-sort-up text-primary ms-1';
                    else icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
                });
            }
        } catch (e) {}
    }

    window.abrirModalHorario = function (tr) {
        let d = {};
        if (tr) { try { d = JSON.parse(tr.getAttribute('data-row')); } catch (e) {} }
        document.getElementById('horarioTitulo').textContent = d.id ? 'Editar turno' : 'Nuevo turno';
        document.getElementById('btnEliminarHorario').style.display = d.id ? '' : 'none';
        document.getElementById('hor_id').value = d.id || '';
        document.getElementById('hor_nombre').value = d.nombre || '';
        document.getElementById('hor_entrada').value = (d.hora_entrada || '').substring(0, 5);
        document.getElementById('hor_salida').value = (d.hora_salida || '').substring(0, 5);
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
        const dias = Array.from(document.querySelectorAll('.dia-chk')).filter(c => c.checked).map(c => c.value).join(',');
        const fd = new FormData();
        if (id) fd.append('id', id);
        fd.append('nombre', document.getElementById('hor_nombre').value.trim());
        fd.append('hora_entrada', document.getElementById('hor_entrada').value);
        fd.append('hora_salida', document.getElementById('hor_salida').value);
        if (document.getElementById('hor_cruza').checked) fd.append('cruza_medianoche', '1');
        fd.append('tolerancia_min', document.getElementById('hor_tolerancia').value);
        fd.append('horas_jornada', document.getElementById('hor_horas').value);
        fd.append('dias_semana', dias);
        fd.append('estado', document.getElementById('hor_estado').value);
        fetch(`${urlBase}/${id ? 'update' : 'store'}`, { method: 'POST', body: fd })
            .then(r => r.json()).then(j => {
                if (!j.ok) { err(j.error); return; }
                getHor().hide();
                if (window.Swal) Swal.fire({ icon: 'success', title: j.msg, timer: 1000, showConfirmButton: false });
                cargarListado(window.currentPage || 1);
            }).catch(() => err('Error de red.'));
    };

    window.eliminarHorario = function (id) {
        const run = () => { const fd = new FormData(); fd.append('id_eliminar', id);
            fetch(`${urlBase}/delete`, { method: 'POST', body: fd }).then(r => r.json()).then(j => { if (j.ok) { cargarListado(window.currentPage || 1); if (window.Swal) Swal.fire({ icon: 'success', title: j.msg, timer: 1000, showConfirmButton: false }); } else err(j.error); }); };
        if (window.Swal) Swal.fire({ icon: 'warning', title: '¿Eliminar turno?', showCancelButton: true, confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545' }).then(r => { if (r.isConfirmed) run(); });
        else if (confirm('¿Eliminar turno?')) run();
    };
    window.eliminarHorarioModal = function () { const id = document.getElementById('hor_id').value.trim(); if (id) { getHor().hide(); window.eliminarHorario(id); } };

    if (window.CMG_initSort) window.CMG_initSort('horarios', (col, dir) => { currentSort = col; currentDir = dir; cargarListado(1); }, { col: currentSort, dir: currentDir });
})();
</script>
