/**
 * Utilidades (Nómina): calcular el reparto del 15%, editar trabajadores,
 * contabilizar el asiento del 31-dic y exportar el CSV del Ministerio.
 * El pago se hace desde Egresos → Nómina (no desde aquí).
 */
(function (window, document) {
    'use strict';

    const urlModulo = BASE_URL + '/modulos/utilidades';
    const $ = (id) => document.getElementById(id);
    const money = (v) => '$' + (parseFloat(v) || 0).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const fecha = (v) => v ? new Date(v + 'T00:00:00').toLocaleDateString('es-EC') : '—';
    const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');

    let modalCalcularInst = null;
    let modalDetalleInst = null;
    let calculando = false;
    const getModalCalcular = () => modalCalcularInst || (modalCalcularInst = new bootstrap.Modal($('modalCalcularUt')));
    const getModalDetalle = () => modalDetalleInst || (modalDetalleInst = new bootstrap.Modal($('modalDetalleUt')));

    // ─── Nuevo (cálculo de un ejercicio) ─────────────────────────────────────
    window.UT_abrirModalNuevo = function () {
        const form = $('formCalcularUt');
        if (form) form.reset();
        $('ut_calc_anio').value = new Date().getFullYear() - 1;
        $('ut_calc_utilidad').value = '0.00';
        $('ut_calc_monto').value = '0.00';
        getModalCalcular().show();
    };
    window.abrirModalCalcular = window.UT_abrirModalNuevo;

    // El 15% se propone solo al escribir la utilidad líquida; el usuario puede corregirlo.
    window.utProponerMonto = function () {
        const u = parseFloat($('ut_calc_utilidad').value) || 0;
        $('ut_calc_monto').value = (Math.round(u * 15) / 100).toFixed(2);
    };

    const formCalcular = $('formCalcularUt');
    if (formCalcular) {
        formCalcular.addEventListener('submit', async () => {
            if (calculando) return;
            calculando = true;
            const btn = $('btnCalcularUt');
            const restaurar = () => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Calcular'; calculando = false; };
            btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';
            try {
                const resp = await fetch(`${urlModulo}/calcularAjax`, { method: 'POST', body: new FormData(formCalcular) });
                const json = await resp.json();
                if (json.ok) {
                    Swal.fire({ icon: 'success', title: 'Calculado', text: json.msg, timer: 1400, showConfirmButton: false });
                    setTimeout(() => {
                        restaurar();
                        getModalCalcular().hide();
                        window.dispatchEvent(new CustomEvent('utilidadesActualizado'));
                        window.abrirModalVer({ id: json.id });
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
        $('ut_det_id').value = id;
        $('ut_det_titulo').textContent = 'Utilidades';
        getModalDetalle().show();
        await cargarDetalle(id);
    };

    async function cargarDetalle(id) {
        $('ut-modal-loader')?.classList.remove('d-none');
        try {
            const resp = await fetch(`${urlModulo}/getDetalleAjax?id=${id}`);
            const res = await resp.json();
            if (!res.ok) return;
            const totalPagado = (res.detalle || []).reduce((s, f) => s + (parseFloat(f.monto_pagado) || 0), 0);
            pintarResumen(res.cabecera, res.detalle || [], totalPagado, !!res.tiene_pagos);
            pintarEmpleados(res.detalle || [], !!res.tiene_pagos);
        } catch (e) {
        } finally {
            $('ut-modal-loader')?.classList.add('d-none');
        }
    }

    function pintarResumen(c, filas, totalPagado, tienePagos) {
        $('ut_det_titulo').textContent = `Utilidades ${c.anio}`;
        $('ut_r_anio').textContent = c.anio;
        $('ut_r_limite').textContent = fecha(c.fecha_limite_pago);
        $('ut_r_utilidad').textContent = money(c.utilidad_liquida);
        $('ut_r_repartir').textContent = money(c.monto_repartir);
        $('ut_r_m10').textContent = money(c.monto_10);
        $('ut_r_m5').textContent = money(c.monto_5);
        $('ut_r_tope').textContent = `${money(c.sbu_aplicado)} / ${money(c.tope_trabajador)}`;
        $('ut_r_empleados').textContent = c.total_empleados;
        $('ut_r_dias').textContent = c.total_dias;
        $('ut_r_cargas').textContent = c.total_cargas;
        $('ut_r_total').textContent = money(c.total_valor);
        $('ut_r_excedente').textContent = money(c.total_excedente);
        $('ut_r_pagado').textContent = money(totalPagado);
        $('ut_r_estado').textContent = (c.estado || '').charAt(0).toUpperCase() + (c.estado || '').slice(1);

        const sinCargas = filas.length > 0 && filas.every(f => (parseInt(f.cargas_familiares, 10) || 0) === 0);
        $('ut_aviso_sin_cargas')?.classList.toggle('d-none', !sinCargas);

        const btnAnular = $('btnAnularUt');
        if (btnAnular) btnAnular.classList.toggle('d-none', tienePagos);
        const btnCont = $('btnContabilizarUt');
        if (btnCont) {
            btnCont.innerHTML = c.estado === 'contabilizado'
                ? '<i class="bi bi-arrow-repeat me-1"></i> Regenerar asiento'
                : '<i class="bi bi-journal-check me-1"></i> Contabilizar';
        }
    }

    function tipoPagoSelect(valorActual, idDetalle) {
        const opciones = [['P', 'Pago Directo'], ['A', 'Acreditación'], ['RP', 'Retención Pago Directo'], ['RA', 'Retención Acreditación']];
        return `<select class="form-select form-select-sm shadow-none" style="padding:0 4px;height:24px;font-size:0.78rem;"
                        onchange="window.guardarCampoUt(${idDetalle}, 'tipo_pago', this.value)">
                    ${opciones.map(([v, l]) => `<option value="${v}" ${v === valorActual ? 'selected' : ''}>${l}</option>`).join('')}
                </select>`;
    }

    function pintarEmpleados(filas, tienePagos) {
        const tbody = $('ut_tbody_empleados');
        if (!tbody) return;
        if (!filas.length) {
            tbody.innerHTML = '<tr><td colspan="13" class="text-center text-muted py-3">Sin trabajadores con días laborados en el ejercicio.</td></tr>';
            return;
        }
        const st = 'style="padding:0 4px;height:24px;font-size:0.78rem;"';
        tbody.innerHTML = filas.map(f => {
            const activo = (f.activo === true || f.activo === 't' || f.activo === '1');
            const discapacidad = (f.discapacidad === true || f.discapacidad === 't' || f.discapacidad === '1');
            const pagado = parseFloat(f.monto_pagado) || 0;
            const exced = parseFloat(f.excedente) || 0;
            return `<tr class="${activo ? '' : 'text-muted'}" title="${activo ? '' : 'Ex trabajador (salió durante el ejercicio)'}">
                <td><code class="text-secondary">${esc(f.identificacion)}</code></td>
                <td><input class="form-control form-control-sm shadow-none" ${st} value="${esc(f.nombres)}" onchange="window.guardarCampoUt(${f.id}, 'nombres', this.value)"></td>
                <td><input class="form-control form-control-sm shadow-none" ${st} value="${esc(f.apellidos)}" onchange="window.guardarCampoUt(${f.id}, 'apellidos', this.value)"></td>
                <td class="text-center">${f.dias_laborados}</td>
                <td class="text-center" style="width:70px;"><input type="number" min="0" max="20" step="1" class="form-control form-control-sm shadow-none text-center" ${st} value="${parseInt(f.cargas_familiares, 10) || 0}" ${tienePagos ? 'disabled' : ''} onchange="window.guardarCampoUt(${f.id}, 'cargas_familiares', this.value)"></td>
                <td class="text-end">${money(f.valor_10)}</td>
                <td class="text-end">${money(f.valor_5)}</td>
                <td class="text-end ${exced > 0 ? 'text-warning fw-bold' : 'text-muted'}">${money(exced)}</td>
                <td class="text-end fw-bold">${money(f.valor)}</td>
                <td class="text-end ${pagado > 0 ? 'text-success fw-bold' : 'text-muted'}">${money(pagado)}</td>
                <td>${tipoPagoSelect(f.tipo_pago, f.id)}</td>
                <td class="text-center"><input type="checkbox" class="form-check-input" ${discapacidad ? 'checked' : ''} onchange="window.guardarCampoUt(${f.id}, 'discapacidad', this.checked ? 1 : 0)"></td>
                <td><input type="number" step="0.01" min="0" class="form-control form-control-sm shadow-none text-end" ${st} value="${parseFloat(f.valor_retencion) || 0}" onchange="window.guardarCampoUt(${f.id}, 'valor_retencion', this.value)"></td>
            </tr>`;
        }).join('');
    }

    window.guardarCampoUt = async function (idDetalle, campo, valor) {
        try {
            const fd = new FormData();
            fd.append('id', idDetalle);
            fd.append(campo, valor);
            const resp = await fetch(`${urlModulo}/actualizarDetalleAjax`, { method: 'POST', body: fd });
            const json = await resp.json();
            if (!json.ok) {
                Swal.fire({ icon: 'error', title: 'No se pudo guardar', text: json.error });
                await cargarDetalle($('ut_det_id').value);
                return;
            }
            if (json.redistribuido) {
                // Las cargas cambian el 5% de todos: se repinta la grilla y el resumen.
                await cargarDetalle($('ut_det_id').value);
                window.dispatchEvent(new CustomEvent('utilidadesActualizado'));
            }
        } catch (e) {}
    };

    // ─── Contabilizar ────────────────────────────────────────────────────────
    window.contabilizarUtilidades = async function () {
        const id = $('ut_det_id').value;
        if (!id) return;
        const r = await Swal.fire({
            title: '¿Contabilizar las utilidades?',
            text: 'Se genera (o regenera) el asiento del 31 de diciembre del ejercicio: Gasto Participación Trabajadores contra Participación Trabajadores por Pagar.',
            icon: 'question', showCancelButton: true, confirmButtonText: 'Sí, contabilizar', cancelButtonText: 'Cancelar'
        });
        if (!r.isConfirmed) return;
        const btn = $('btnContabilizarUt');
        if (btn) btn.disabled = true;
        try {
            const fd = new FormData(); fd.append('id', id);
            const resp = await fetch(`${urlModulo}/contabilizarAjax`, { method: 'POST', body: fd });
            const json = await resp.json();
            if (json.ok) {
                Swal.fire({ icon: 'success', title: 'Contabilizado', text: json.msg, timer: 1500, showConfirmButton: false });
                await cargarDetalle(id);
                window.dispatchEvent(new CustomEvent('utilidadesActualizado'));
            } else {
                Swal.fire({ icon: 'error', title: 'No se pudo contabilizar', text: json.error });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error de Red', text: 'No se pudo conectar con el servidor.' });
        } finally {
            if (btn) btn.disabled = false;
        }
    };

    // ─── Anular ──────────────────────────────────────────────────────────────
    window.anularUtilidades = async function () {
        const id = $('ut_det_id').value;
        if (!id) return;
        const r = await Swal.fire({ title: '¿Anular estas utilidades?', text: 'Se eliminan lógicamente (y su asiento, si lo tiene) y podrá volver a calcularlas.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Sí, anular', cancelButtonText: 'Cancelar' });
        if (!r.isConfirmed) return;
        try {
            const fd = new FormData(); fd.append('id', id);
            const resp = await fetch(`${urlModulo}/anularAjax`, { method: 'POST', body: fd });
            const json = await resp.json();
            if (json.ok) {
                Swal.fire({ icon: 'success', title: 'Anuladas', timer: 1300, showConfirmButton: false });
                setTimeout(() => {
                    getModalDetalle().hide();
                    window.dispatchEvent(new CustomEvent('utilidadesActualizado'));
                }, 1300);
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: json.error });
            }
        } catch (e) {}
    };

    // ─── Exportar CSV ────────────────────────────────────────────────────────
    window.exportarCsv = function (id) {
        const idFinal = id || $('ut_det_id').value;
        if (!idFinal) return;
        CMG_descargar(`${urlModulo}/exportarCsv?id=${idFinal}`);
    };

})(window, document);
