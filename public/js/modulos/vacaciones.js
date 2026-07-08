/**
 * Modal de Vacaciones (Nómina): empleado con buscador, saldo y valor.
 */
(function (window, document) {
    'use strict';

    const urlModulo = BASE_URL + '/modulos/vacaciones';
    let modalInst = null;
    const form = document.getElementById('formVacacion');
    let sueldoEmp = 0;

    const $ = (id) => document.getElementById(id);
    const money = (v) => '$' + (parseFloat(v) || 0).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function getModal() {
        if (!modalInst && typeof bootstrap !== 'undefined') modalInst = new bootstrap.Modal($('modalVacacion'));
        return modalInst;
    }

    // ─── Buscador de empleado ────────────────────────────────────────────────
    const empBuscar = $('vac_empleado_buscar');
    const empHidden = $('vac_id_empleado');
    const empResultados = $('vac_empleado_resultados');
    let empTimer = null;

    const ocultarResultados = () => empResultados && empResultados.classList.add('d-none');

    function setEmpleado(id, texto) {
        if (empHidden) empHidden.value = id || '';
        if (empBuscar) empBuscar.value = texto || '';
        ocultarResultados();
        if (id) cargarInfoEmpleado(id);
        else { $('vac_info').classList.add('d-none'); sueldoEmp = 0; recalcularValor(); }
    }

    async function buscarEmpleados(q) {
        try {
            const resp = await fetch(`${urlModulo}/buscarEmpleadosAjax?q=${encodeURIComponent(q)}`);
            const json = await resp.json();
            if (!json.ok || !empResultados) return;
            if (!json.data.length) {
                empResultados.innerHTML = '<div class="list-group-item small text-muted">Sin resultados</div>';
                empResultados.classList.remove('d-none');
                return;
            }
            empResultados.innerHTML = json.data.map(e => {
                const texto = `${e.nombres_apellidos} (${e.identificacion})`.replace(/"/g, '&quot;');
                return `<button type="button" class="list-group-item list-group-item-action py-1 small" data-id="${e.id}" data-texto="${texto}">
                            <span class="fw-medium">${e.nombres_apellidos}</span> <span class="text-muted">${e.identificacion}</span>
                        </button>`;
            }).join('');
            empResultados.classList.remove('d-none');
        } catch (e) { ocultarResultados(); }
    }

    if (empBuscar) {
        empBuscar.addEventListener('input', () => {
            if (empHidden) empHidden.value = '';
            const q = empBuscar.value.trim();
            clearTimeout(empTimer);
            if (q.length < 2) { ocultarResultados(); return; }
            empTimer = setTimeout(() => buscarEmpleados(q), 300);
        });
        empBuscar.addEventListener('blur', () => setTimeout(ocultarResultados, 200));
    }
    if (empResultados) {
        empResultados.addEventListener('mousedown', (ev) => {
            const btn = ev.target.closest('[data-id]');
            if (!btn) return;
            ev.preventDefault();
            setEmpleado(btn.dataset.id, btn.dataset.texto);
        });
    }

    async function cargarInfoEmpleado(idEmpleado) {
        const excl = $('vac_id').value || 0;
        try {
            const resp = await fetch(`${urlModulo}/getInfoEmpleadoAjax?id_empleado=${idEmpleado}&exclude=${excl}`);
            const json = await resp.json();
            if (!json.ok) return;
            const d = json.data;
            sueldoEmp = parseFloat(d.sueldo_base) || 0;
            $('vac_info_antig').textContent = d.antiguedad || '—';
            $('vac_info_derecho').textContent = d.derecho_anio_actual + ' días';
            $('vac_info_gozados').textContent = d.dias_gozados_total + ' días';
            $('vac_info_saldo').textContent = d.saldo + ' días';
            $('vac_info_sueldo').textContent = money(d.sueldo_base);
            $('vac_info').classList.remove('d-none');
            recalcularValor();
        } catch (e) {}
    }

    // ─── Valor y días ────────────────────────────────────────────────────────
    function recalcularValor() {
        const dias = parseFloat($('vac_dias').value) || 0;
        $('vac_valor_preview').value = money((sueldoEmp / 30) * dias);
    }

    function diasEntreFechas() {
        const d = $('vac_fecha_desde').value, h = $('vac_fecha_hasta').value;
        if (!d || !h) return 0;
        const ms = new Date(h) - new Date(d);
        return ms >= 0 ? Math.floor(ms / 86400000) + 1 : 0;
    }

    $('vac_dias').addEventListener('input', recalcularValor);
    ['vac_fecha_desde', 'vac_fecha_hasta'].forEach(id => $(id).addEventListener('change', () => {
        // Sugerir días por rango si aún no se han fijado.
        const actual = parseFloat($('vac_dias').value) || 0;
        const sug = diasEntreFechas();
        if (sug > 0 && actual === 0) { $('vac_dias').value = sug; recalcularValor(); }
        // Período del rol desde la fecha desde.
        const fd = $('vac_fecha_desde').value;
        if (fd) {
            $('vac_periodo_mes').value = String(parseInt(fd.substring(5, 7), 10));
            $('vac_periodo_anio').value = fd.substring(0, 4);
        }
    }));

    // ─── Abrir / guardar ─────────────────────────────────────────────────────
    window.abrirModalCrear = function () {
        if (!form) return;
        form.reset();
        $('vac_id').value = '';
        $('tituloModalVac').textContent = 'Nueva Vacación';
        $('btnEliminarVac')?.classList.add('d-none');
        setEmpleado('', '');
        $('vac_afecta_rol').checked = true;
        // Nueva: estado registrado en solo lectura.
        const est = $('vac_estado'); est.value = 'registrado'; est.disabled = true;
        recalcularValor();
        getModal()?.show();
    };

    window.abrirModalEditar = async function (tr) {
        const rowData = (tr instanceof HTMLElement) ? JSON.parse(tr.dataset.row) : tr;
        const id = rowData.id;
        if (!form || !id) return;
        form.reset();
        $('vac_id').value = id;
        $('tituloModalVac').textContent = 'Editar Vacación';
        $('btnEliminarVac')?.classList.remove('d-none');
        $('vac_estado').disabled = false;
        getModal()?.show();

        try {
            const resp = await fetch(`${urlModulo}/getDetalleAjax?id=${id}`);
            const res = await resp.json();
            if (!res.ok) return;
            const d = res.data;
            setEmpleado(d.id_empleado || '', d.empleado_nombre ? `${d.empleado_nombre} (${d.empleado_identificacion || ''})` : '');
            $('vac_estado').value = d.estado || 'registrado';
            $('vac_fecha_desde').value = d.fecha_desde || '';
            $('vac_fecha_hasta').value = d.fecha_hasta || '';
            $('vac_dias').value = d.dias_gozados != null ? parseFloat(d.dias_gozados) : 0;
            $('vac_periodo_mes').value = String(parseInt(d.periodo_mes, 10) || '');
            $('vac_periodo_anio').value = d.periodo_anio || '';
            $('vac_afecta_rol').checked = (d.afecta_rol === true || d.afecta_rol === 't' || d.afecta_rol === '1' || d.afecta_rol === 1);
            $('vac_observacion').value = d.observacion || '';
            recalcularValor();
        } catch (e) {}
    };

    if (form) {
        form.addEventListener('submit', async () => {
            if (!empHidden.value) { Swal.fire({ icon: 'info', title: 'Seleccione un empleado' }); return; }
            const id = $('vac_id').value;
            const btn = $('btnGuardarVac');
            const url = id ? `${urlModulo}/update` : `${urlModulo}/store`;
            const restaurar = () => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar'; };
            btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';
            try {
                const resp = await fetch(url, { method: 'POST', body: new FormData(form) });
                const json = await resp.json();
                if (json.ok) {
                    Swal.fire({ icon: 'success', title: id ? 'Actualizada' : 'Guardada', text: json.msg, timer: 1400, showConfirmButton: false });
                    setTimeout(() => { restaurar(); getModal()?.hide(); window.dispatchEvent(new CustomEvent('vacacionGuardada')); }, 1400);
                } else {
                    Swal.fire({ icon: 'error', title: 'Atención', text: json.error || 'No se pudo guardar.' });
                    restaurar();
                }
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Error de Red', text: 'No se pudo conectar con el servidor.' });
                restaurar();
            }
        });
    }

    async function eliminarConSwal(id, cerrar) {
        if (!id) return;
        const r = await Swal.fire({ title: '¿Está seguro?', text: 'No podrá revertir esta acción.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar' });
        if (!r.isConfirmed) return;
        try {
            const fd = new FormData(); fd.append('id_eliminar', id);
            const resp = await fetch(`${urlModulo}/delete`, { method: 'POST', body: fd });
            const json = await resp.json();
            if (json.ok) {
                Swal.fire({ icon: 'success', title: 'Eliminada', timer: 1300, showConfirmButton: false });
                if (cerrar) getModal()?.hide();
                window.dispatchEvent(new CustomEvent('vacacionGuardada'));
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: json.error });
            }
        } catch (e) {}
    }

    window.eliminarRegistro = (id) => eliminarConSwal(id, false);
    window.eliminarVacacionModal = () => eliminarConSwal($('vac_id').value, true);

})(window, document);
