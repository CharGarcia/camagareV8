/**
 * Décimo Cuarto (Nómina): calcular declaración, editar empleados y exportar CSV.
 * El pago se hace desde Egresos → Nómina (no desde aquí).
 */
(function (window, document) {
    'use strict';

    const urlModulo = BASE_URL + '/modulos/decimo-cuarto';
    const $ = (id) => document.getElementById(id);
    const money = (v) => '$' + (parseFloat(v) || 0).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const fecha = (v) => v ? new Date(v + 'T00:00:00').toLocaleDateString('es-EC') : '—';

    let modalCalcularInst = null;
    let modalDetalleInst = null;
    const getModalCalcular = () => modalCalcularInst || (modalCalcularInst = new bootstrap.Modal($('modalCalcularDc')));
    const getModalDetalle = () => modalDetalleInst || (modalDetalleInst = new bootstrap.Modal($('modalDetalleDc')));

    // ─── Calcular ────────────────────────────────────────────────────────────
    window.abrirModalCalcular = function () {
        const form = $('formCalcularDc');
        if (form) form.reset();
        $('dc_calc_anio').value = window.DC_ANIO_ACTUAL || new Date().getFullYear();
        getModalCalcular().show();
    };

    const formCalcular = $('formCalcularDc');
    if (formCalcular) {
        formCalcular.addEventListener('submit', async () => {
            const btn = $('btnCalcularDc');
            const restaurar = () => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Calcular'; };
            btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';
            try {
                const resp = await fetch(`${urlModulo}/calcularAjax`, { method: 'POST', body: new FormData(formCalcular) });
                const json = await resp.json();
                if (json.ok) {
                    Swal.fire({ icon: 'success', title: 'Calculado', text: json.msg, timer: 1400, showConfirmButton: false });
                    setTimeout(() => {
                        restaurar();
                        getModalCalcular().hide();
                        window.dispatchEvent(new CustomEvent('decimoCuartoActualizado'));
                    }, 1400);
                } else {
                    Swal.fire({ icon: 'error', title: 'Atención', text: json.error || 'No se pudo calcular.' });
                    restaurar();
                }
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Error de Red', text: 'No se pudo conectar con el servidor.' });
                restaurar();
            }
        });
    }

    // ─── Ver / detalle ───────────────────────────────────────────────────────
    window.abrirModalVer = async function (tr) {
        const rowData = (tr instanceof HTMLElement) ? JSON.parse(tr.dataset.row) : tr;
        const id = rowData.id;
        if (!id) return;
        $('dc_det_id').value = id;
        getModalDetalle().show();

        try {
            const resp = await fetch(`${urlModulo}/getDetalleAjax?id=${id}`);
            const res = await resp.json();
            if (!res.ok) return;
            const totalPagado = (res.detalle || []).reduce((s, f) => s + (parseFloat(f.monto_pagado) || 0), 0);
            pintarResumen(res.cabecera, totalPagado);
            pintarEmpleados(res.detalle);
        } catch (e) {}
    };

    function pintarResumen(c, totalPagado) {
        const region = c.region_grupo === 'sierra_amazonia' ? 'Sierra / Amazonía' : 'Costa / Insular';
        $('dc_det_titulo').textContent = `${c.anio} — ${region}`;
        $('dc_r_anio').textContent = c.anio;
        $('dc_r_region').textContent = region;
        $('dc_r_periodo').textContent = `${fecha(c.fecha_desde)} — ${fecha(c.fecha_hasta)}`;
        $('dc_r_limite').textContent = fecha(c.fecha_limite_pago);
        $('dc_r_sbu').textContent = money(c.sbu_aplicado);
        $('dc_r_empleados').textContent = c.total_empleados;
        $('dc_r_total').textContent = money(c.total_valor);
        $('dc_r_pagado').textContent = money(totalPagado);

        const tienePagos = totalPagado > 0;
        const btnAnular = $('btnAnularDc');
        if (btnAnular) btnAnular.classList.toggle('d-none', tienePagos);
    }

    function tipoPagoSelect(valorActual, idDetalle) {
        const opciones = [['P', 'Pago Directo'], ['A', 'Acreditación'], ['RP', 'Retención Pago Directo'], ['RA', 'Retención Acreditación']];
        return `<select class="form-select form-select-sm shadow-none" style="padding:0 4px;height:24px;font-size:0.78rem;"
                        onchange="window.guardarCampoDc(${idDetalle}, 'tipo_pago', this.value)">
                    ${opciones.map(([v, l]) => `<option value="${v}" ${v === valorActual ? 'selected' : ''}>${l}</option>`).join('')}
                </select>`;
    }

    function pintarEmpleados(filas) {
        const tbody = $('dc_tbody_empleados');
        if (!tbody) return;
        if (!filas.length) {
            tbody.innerHTML = '<tr><td colspan="11" class="text-center text-muted py-3">Sin empleados.</td></tr>';
            return;
        }
        tbody.innerHTML = filas.map(f => {
            const mensualiza = (f.mensualiza === true || f.mensualiza === 't' || f.mensualiza === '1');
            const discapacidad = (f.discapacidad === true || f.discapacidad === 't' || f.discapacidad === '1');
            const pagado = parseFloat(f.monto_pagado) || 0;
            return `<tr>
                <td><code class="text-secondary">${f.identificacion || ''}</code></td>
                <td><input class="form-control form-control-sm shadow-none" style="padding:0 4px;height:24px;font-size:0.78rem;" value="${(f.nombres || '').replace(/"/g, '&quot;')}" onchange="window.guardarCampoDc(${f.id}, 'nombres', this.value)"></td>
                <td><input class="form-control form-control-sm shadow-none" style="padding:0 4px;height:24px;font-size:0.78rem;" value="${(f.apellidos || '').replace(/"/g, '&quot;')}" onchange="window.guardarCampoDc(${f.id}, 'apellidos', this.value)"></td>
                <td class="text-center">${f.dias_laborados}</td>
                <td class="text-end fw-bold">${money(f.valor)}</td>
                <td class="text-end ${pagado > 0 ? 'text-success fw-bold' : 'text-muted'}">${money(pagado)}</td>
                <td class="text-center">${mensualiza ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<span class="text-muted">—</span>'}</td>
                <td>${tipoPagoSelect(f.tipo_pago, f.id)}</td>
                <td class="text-center"><input type="checkbox" class="form-check-input" ${discapacidad ? 'checked' : ''} onchange="window.guardarCampoDc(${f.id}, 'discapacidad', this.checked ? 1 : 0)"></td>
                <td><input type="date" class="form-control form-control-sm shadow-none" style="padding:0 4px;height:24px;font-size:0.78rem;" value="${f.fecha_jubilacion || ''}" onchange="window.guardarCampoDc(${f.id}, 'fecha_jubilacion', this.value)"></td>
                <td><input type="number" step="0.01" min="0" class="form-control form-control-sm shadow-none text-end" style="padding:0 4px;height:24px;font-size:0.78rem;" value="${parseFloat(f.valor_retencion) || 0}" onchange="window.guardarCampoDc(${f.id}, 'valor_retencion', this.value)"></td>
            </tr>`;
        }).join('');
    }

    window.guardarCampoDc = async function (idDetalle, campo, valor) {
        try {
            const fd = new FormData();
            fd.append('id', idDetalle);
            fd.append(campo, valor);
            const resp = await fetch(`${urlModulo}/actualizarDetalleAjax`, { method: 'POST', body: fd });
            const json = await resp.json();
            if (!json.ok) Swal.fire({ icon: 'error', title: 'No se pudo guardar', text: json.error });
        } catch (e) {}
    };

    // ─── Anular ──────────────────────────────────────────────────────────────
    window.anularDecimoCuarto = async function () {
        const id = $('dc_det_id').value;
        if (!id) return;
        const r = await Swal.fire({ title: '¿Anular esta declaración?', text: 'Se eliminará lógicamente y podrá volver a calcularla.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Sí, anular', cancelButtonText: 'Cancelar' });
        if (!r.isConfirmed) return;
        try {
            const fd = new FormData(); fd.append('id', id);
            const resp = await fetch(`${urlModulo}/anularAjax`, { method: 'POST', body: fd });
            const json = await resp.json();
            if (json.ok) {
                Swal.fire({ icon: 'success', title: 'Anulada', timer: 1300, showConfirmButton: false });
                setTimeout(() => {
                    getModalDetalle().hide();
                    window.dispatchEvent(new CustomEvent('decimoCuartoActualizado'));
                }, 1300);
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: json.error });
            }
        } catch (e) {}
    };

    // ─── Exportar CSV ────────────────────────────────────────────────────────
    window.exportarCsv = function (id) {
        const idFinal = id || $('dc_det_id').value;
        if (!idFinal) return;
        window.location.href = `${urlModulo}/exportarCsv?id=${idFinal}`;
    };

})(window, document);
