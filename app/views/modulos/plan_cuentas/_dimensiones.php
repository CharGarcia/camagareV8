<?php
/**
 * Sección "Centro de costo / Proyecto por periodo" del modal de Plan de Cuentas.
 * La incluyen los dos modales de cuenta (plan_cuentas/index.php y plan_cuentas/modal.php).
 *
 * Es la sugerencia que se precarga en las líneas de los asientos según la fecha del asiento;
 * cambiarla nunca toca los asientos ya registrados. El modal la llena con
 * window.PCDIM_cargar(idCuenta, soloLectura) al abrir una cuenta de nivel 5.
 */
$pcdimCentros   = $centros ?? $centrosCosto ?? [];
$pcdimProyectos = $proyectos ?? [];
?>
<div id="pcdim_wrapper" class="mt-3">
    <div class="d-flex align-items-center mb-1">
        <span class="small fw-bold"><i class="bi bi-diagram-3 me-1 text-primary"></i>Centro de costo / Proyecto por periodo</span>
    </div>
    <div class="form-text mt-0 mb-2" style="font-size:.68rem;">
        Se sugiere en las líneas de los asientos según su fecha. Cambiarlo para otro periodo no modifica los asientos ya registrados.
    </div>

    <div id="pcdim_sin_cuenta" class="small text-muted fst-italic d-none">Guarde la cuenta para asignarle un centro de costo o proyecto.</div>
    <div id="pcdim_sin_tabla" class="small text-warning d-none"><i class="bi bi-exclamation-triangle me-1"></i>Falta aplicar la migración de periodos (20261028_plan_cuentas_dimension_vigencia.sql).</div>
    <?php if (empty($pcdimCentros) && empty($pcdimProyectos)): ?>
        <div class="small text-muted fst-italic">No hay centros de costo ni proyectos activos en la empresa.</div>
    <?php endif; ?>

    <div id="pcdim_cuerpo" class="d-none">
        <div class="table-responsive border rounded">
            <table class="table table-sm table-hover mb-0 align-middle" style="font-size:.75rem;">
                <thead class="table-light">
                    <tr>
                        <th>Desde</th>
                        <th>Hasta</th>
                        <?php if (!empty($pcdimCentros)): ?><th>Centro de costo</th><?php endif; ?>
                        <?php if (!empty($pcdimProyectos)): ?><th>Proyecto</th><?php endif; ?>
                        <th class="pcdim-acciones" style="width:52px;"></th>
                    </tr>
                </thead>
                <tbody id="pcdim_tbody"></tbody>
                <tfoot id="pcdim_form" class="d-none">
                    <tr>
                        <td class="p-0"><input type="date" id="pcdim_desde" class="form-control form-control-sm border-0" style="padding:0 4px;height:24px;font-size:.75rem;" title="Vacío = sin fecha de inicio"></td>
                        <td class="p-0"><input type="date" id="pcdim_hasta" class="form-control form-control-sm border-0" style="padding:0 4px;height:24px;font-size:.75rem;" title="Vacío = sin fecha de fin"></td>
                        <?php if (!empty($pcdimCentros)): ?>
                        <td class="p-0">
                            <select id="pcdim_centro" class="form-select form-select-sm border-0" style="padding:0 4px;height:24px;font-size:.75rem;">
                                <option value="">—</option>
                                <?php foreach ($pcdimCentros as $c): ?>
                                    <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars(trim(($c['codigo'] ?? '') . ' ' . $c['nombre'])) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <?php endif; ?>
                        <?php if (!empty($pcdimProyectos)): ?>
                        <td class="p-0">
                            <select id="pcdim_proyecto" class="form-select form-select-sm border-0" style="padding:0 4px;height:24px;font-size:.75rem;">
                                <option value="">—</option>
                                <?php foreach ($pcdimProyectos as $p): ?>
                                    <option value="<?= (int) $p['id'] ?>"><?= htmlspecialchars(trim(($p['codigo'] ?? '') . ' ' . $p['nombre'])) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <?php endif; ?>
                        <td class="p-0 text-center text-nowrap">
                            <button type="button" id="pcdim_btn_guardar" class="btn btn-sm btn-outline-success border-0 py-0 px-1" title="Guardar periodo"><i class="bi bi-check-lg"></i></button>
                            <button type="button" id="pcdim_btn_cancelar" class="btn btn-sm btn-outline-secondary border-0 py-0 px-1 d-none" title="Cancelar edición"><i class="bi bi-x"></i></button>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="form-text" style="font-size:.65rem;">Fechas vacías = sin límite. Los periodos de una misma cuenta no pueden cruzarse.</div>
    </div>
</div>

<script>
(function () {
    'use strict';
    if (window.__CMG_pcdim) return;
    window.__CMG_pcdim = true;

    const URL_PC = '<?= BASE_URL ?>/modulos/plan-cuentas';
    const $ = (id) => document.getElementById(id);
    let idCuentaActual = 0, idEditando = 0, guardando = false;

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const etiqueta = (cod, nom) => nom ? esc(((cod || '') + ' ' + nom).trim()) : '<span class="text-muted">—</span>';

    function limpiarForm() {
        idEditando = 0;
        ['pcdim_desde', 'pcdim_hasta', 'pcdim_centro', 'pcdim_proyecto'].forEach(id => { if ($(id)) $(id).value = ''; });
        $('pcdim_btn_cancelar').classList.add('d-none');
    }

    function pintar(rows, editable) {
        const tb = $('pcdim_tbody');
        const conCentro = !!$('pcdim_centro'), conProyecto = !!$('pcdim_proyecto');
        if (!rows.length) {
            const cols = 3 + (conCentro ? 1 : 0) + (conProyecto ? 1 : 0);
            tb.innerHTML = `<tr><td colspan="${cols}" class="text-center text-muted py-2">Sin centro de costo ni proyecto asignado.</td></tr>`;
        } else {
            tb.innerHTML = rows.map(r => `
                <tr data-id="${r.id}" class="${r.vigente_hoy ? 'table-success' : ''}">
                    <td>${r.desde_txt || '<span class="text-muted">Sin inicio</span>'}</td>
                    <td>${r.hasta_txt || '<span class="text-muted">Sin fin</span>'}${r.vigente_hoy ? ' <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Vigente</span>' : ''}</td>
                    ${conCentro ? `<td>${etiqueta(r.centro_codigo, r.centro_nombre)}</td>` : ''}
                    ${conProyecto ? `<td>${etiqueta(r.proyecto_codigo, r.proyecto_nombre)}</td>` : ''}
                    <td class="text-center text-nowrap pcdim-acciones">${editable ? `
                        <button type="button" class="btn btn-sm btn-outline-primary border-0 py-0 px-1 pcdim-editar" title="Editar"><i class="bi bi-pencil"></i></button>
                        <button type="button" class="btn btn-sm btn-outline-danger border-0 py-0 px-1 pcdim-eliminar" title="Eliminar"><i class="bi bi-trash"></i></button>` : ''}
                    </td>
                </tr>`).join('');
            tb.querySelectorAll('tr[data-id]').forEach(tr => {
                const r = rows.find(x => String(x.id) === tr.dataset.id);
                tr.querySelector('.pcdim-editar')?.addEventListener('click', () => {
                    idEditando = r.id;
                    $('pcdim_desde').value = r.vigente_desde || '';
                    $('pcdim_hasta').value = r.vigente_hasta || '';
                    if ($('pcdim_centro')) $('pcdim_centro').value = r.id_centro_costo || '';
                    if ($('pcdim_proyecto')) $('pcdim_proyecto').value = r.id_proyecto || '';
                    $('pcdim_btn_cancelar').classList.remove('d-none');
                });
                tr.querySelector('.pcdim-eliminar')?.addEventListener('click', () => eliminar(r.id));
            });
        }
    }

    async function recargar(soloLectura) {
        const resp = await fetch(`${URL_PC}/dimensionesAjax?id_cuenta=${idCuentaActual}`);
        const json = await resp.json();
        if (!json.ok) throw new Error(json.error || 'No se pudo cargar los periodos.');
        if (!json.disponible) {
            $('pcdim_sin_tabla').classList.remove('d-none');
            $('pcdim_cuerpo').classList.add('d-none');
            return;
        }
        const editable = !soloLectura && json.puede_editar;
        $('pcdim_cuerpo').classList.remove('d-none');
        $('pcdim_form').classList.toggle('d-none', !editable);
        document.querySelectorAll('#pcdim_wrapper .pcdim-acciones').forEach(el => el.classList.toggle('d-none', !editable));
        pintar(json.data || [], editable);
    }

    /** Lo llama el modal de cuenta al abrir. idCuenta = 0 → cuenta nueva (aún sin guardar). */
    window.PCDIM_cargar = async function (idCuenta, soloLectura) {
        idCuentaActual = parseInt(idCuenta || 0, 10);
        limpiarForm();
        $('pcdim_sin_tabla').classList.add('d-none');
        $('pcdim_tbody').innerHTML = '';
        $('pcdim_sin_cuenta').classList.toggle('d-none', idCuentaActual > 0);
        $('pcdim_cuerpo').classList.add('d-none');
        if (idCuentaActual <= 0 || (!$('pcdim_centro') && !$('pcdim_proyecto'))) return;
        try { await recargar(soloLectura); }
        catch (e) { console.error(e); }
    };

    $('pcdim_btn_cancelar').addEventListener('click', limpiarForm);

    $('pcdim_btn_guardar').addEventListener('click', async function () {
        if (guardando || idCuentaActual <= 0) return;
        guardando = true;
        this.disabled = true;
        try {
            const fd = new FormData();
            fd.append('id', idEditando || 0);
            fd.append('id_cuenta', idCuentaActual);
            fd.append('vigente_desde', $('pcdim_desde').value);
            fd.append('vigente_hasta', $('pcdim_hasta').value);
            fd.append('id_centro_costo', $('pcdim_centro')?.value || '');
            fd.append('id_proyecto', $('pcdim_proyecto')?.value || '');
            const resp = await fetch(`${URL_PC}/guardarDimensionAjax`, { method: 'POST', body: fd });
            const json = await resp.json();
            if (!json.ok) {
                Swal.fire('No se pudo guardar', json.error || 'Error al guardar el periodo.', 'warning');
                return;
            }
            limpiarForm();
            await recargar(false);
        } catch (e) {
            Swal.fire('Error', 'Error de conexión', 'error');
        } finally {
            guardando = false;
            this.disabled = false;
        }
    });

    async function eliminar(id) {
        const r = await Swal.fire({
            title: '¿Eliminar periodo?',
            text: 'Los asientos ya registrados conservan su centro de costo / proyecto.',
            icon: 'warning', showCancelButton: true,
            confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545'
        });
        if (!r.isConfirmed) return;
        try {
            const fd = new FormData();
            fd.append('id', id);
            const resp = await fetch(`${URL_PC}/eliminarDimensionAjax`, { method: 'POST', body: fd });
            const json = await resp.json();
            if (!json.ok) { Swal.fire('No se pudo eliminar', json.error, 'warning'); return; }
            if (idEditando === id) limpiarForm();
            await recargar(false);
        } catch (e) {
            Swal.fire('Error', 'Error de conexión', 'error');
        }
    }
})();
</script>
