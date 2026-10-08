/**
 * Declaración de Impuesto a la Renta — pantalla.
 * Calcula por AJAX con los filtros/ajustes de la tarjeta de control y pinta las pestañas.
 * Los ajustes manuales (anticipos, créditos, gastos no deducibles…) se escriben en la misma
 * tabla de liquidación (inputs amarillos) y viajan en cada recálculo y en las exportaciones.
 */
(function () {
    'use strict';

    const CFG = window.RENTA_CFG || {};
    const url = (accion) => `${CFG.base}/${CFG.ruta}/${accion}`;
    const money = (v) => (Number(v) || 0).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const $ = (id) => document.getElementById(id);

    // Ajustes escritos en la tabla (clave → valor), se conservan entre recálculos.
    const ajustes = {};
    const CLAVE_AJUSTE = {
        otros_ingresos: 'otros_ingresos',
        otras_deducciones: 'otras_deducciones',
        gastos_no_deducibles: 'gastos_no_deducibles',
        rentas_exentas: 'rentas_exentas',
        deducciones_adicionales: 'deducciones_adicionales',
        amortizacion_perdidas: 'amortizacion_perdidas',
        anticipo_pagado: 'anticipo_pagado',
        credito_anterior: 'credito_anios_anteriores',
        otros_creditos: 'otros_creditos',
        participacion: 'participacion_trabajadores_pct',
        impuesto_causado: 'tarifa_pct'
    };
    let ultimoCalculo = null;
    let calculando = false;

    function params() {
        const p = new URLSearchParams();
        const form = $('form-filtros-renta');
        new FormData(form).forEach((v, k) => { if (v !== '') p.set(k, v); });
        Object.keys(ajustes).forEach((k) => {
            if (ajustes[k] !== '' && ajustes[k] !== null && ajustes[k] !== undefined) p.set(k, ajustes[k]);
        });
        return p;
    }

    function leerAjustesTabla() {
        document.querySelectorAll('#renta-liquidacion input.renta-edit').forEach((inp) => {
            const k = inp.dataset.ajuste;
            if (!k) return;
            ajustes[k] = inp.value === '' ? '' : inp.value;
            // Participación / tarifa viven también en la tarjeta de control: se sincronizan.
            if (k === 'participacion_trabajadores_pct' && $('renta-participacion')) $('renta-participacion').value = inp.value;
            if (k === 'tarifa_pct' && $('renta-tarifa')) $('renta-tarifa').value = inp.value;
        });
    }

    window.RENTA_calcular = async function () {
        if (calculando) return;
        calculando = true;
        const btn = $('renta-btn-mostrar');
        btn.disabled = true;
        leerAjustesTabla();
        try {
            const r = await fetch(url('calcularAjax') + '?' + params().toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const j = await r.json();
            if (!j.success) throw new Error(j.message || 'No se pudo calcular la declaración.');
            ultimoCalculo = j.data;
            pintar(j.data);
        } catch (e) {
            $('renta-avisos').innerHTML = `<div class="alert alert-danger py-1 px-2 small mb-0">${esc(e.message)}</div>`;
        } finally {
            calculando = false;
            btn.disabled = false;
        }
    };

    function pintar(d) {
        // Avisos
        $('renta-avisos').innerHTML = (d.avisos || []).map((a) =>
            `<div class="alert alert-${a.tipo === 'warning' ? 'warning' : 'info'} py-1 px-2 small mb-1"><i class="bi bi-${a.tipo === 'warning' ? 'exclamation-triangle' : 'info-circle'} me-1"></i>${esc(a.texto)}</div>`
        ).join('');

        // KPI
        const r = d.liquidacion.resumen;
        $('kpi-ingresos').textContent = money(r.total_ingresos);
        $('kpi-gastos').textContent = money(r.total_gastos);
        $('kpi-base').textContent = money(r.base_imponible);
        $('kpi-causado').textContent = money(r.impuesto_causado);
        $('kpi-retenciones').textContent = money(r.retenciones);
        if (Number(r.saldo_favor) > 0) {
            $('kpi-pagar').textContent = money(r.saldo_favor);
            $('kpi-pagar-label').textContent = 'Saldo a favor';
        } else {
            $('kpi-pagar').textContent = money(r.impuesto_pagar);
            $('kpi-pagar-label').textContent = 'Impuesto a pagar';
        }

        // Liquidación
        $('renta-liquidacion').innerHTML = d.liquidacion.lineas.map((l) => {
            if (l.seccion) return `<tr class="renta-sec"><td colspan="4">${esc(l.concepto)}</td></tr>`;
            const nota = l.nota ? ` <span class="nota">(${esc(l.nota)})</span>` : '';
            let valor;
            if (l.editable) {
                // Participación y tarifa se editan como porcentaje; el resto como monto.
                const esPct = l.clave === 'participacion' || l.clave === 'impuesto_causado';
                const clave = CLAVE_AJUSTE[l.clave];
                const actual = esPct ? (d.ajustes[clave] ?? '') : (d.ajustes[clave] ?? 0);
                valor = `<span class="me-1">${money(l.valor)}</span>` +
                    `<input type="number" step="0.01" min="0" class="form-control form-control-sm renta-edit bg-warning bg-opacity-25" data-ajuste="${clave}" value="${esc(actual)}" title="${esPct ? 'Porcentaje' : 'Valor'} — presione Mostrar para recalcular" ${esPct ? 'max="100" style="width:80px"' : ''}>` +
                    (esPct ? '<span class="nota ms-1">%</span>' : '');
            } else {
                valor = money(l.valor);
            }
            const cls = l.nivel === 0 ? 'renta-tot' : (l.nivel === 2 ? 'renta-sub' : '');
            return `<tr class="${cls}"><td>${esc(l.concepto)}${nota}</td><td class="cas">${esc(l.casillero)}</td><td class="signo">${esc(l.signo)}</td><td class="val">${valor}</td></tr>`;
        }).join('');
        document.querySelectorAll('#renta-liquidacion input.renta-edit').forEach((inp) => {
            inp.addEventListener('keydown', (ev) => { if (ev.key === 'Enter') { ev.preventDefault(); RENTA_calcular(); } });
        });

        // Resumen de documentos
        $('renta-resumen').innerHTML = (d.resumen_documentos || []).map((f) =>
            `<tr class="${f.total ? 'renta-tot' : (f.rubro ? 'renta-sub' : '')}"><td>${esc(f.concepto)}</td><td class="text-center">${f.cantidad === null ? '' : f.cantidad}</td><td class="val">${money(f.base)}</td><td class="val">${money(f.monto)}</td></tr>`
        ).join('');

        // Casilleros contables
        const tc = $('renta-casilleros');
        if (tc) {
            const c = d.contabilidad || { casilleros: [], sin_casillero: [] };
            const etqEst = (q) => q.establecimiento ? `<span class="badge bg-light text-muted border me-1">${esc(q.establecimiento)}</span>` : '';
            let html = (c.casilleros || []).map((k) =>
                `<tr><td class="cas">${esc(k.casillero)}</td><td>${esc(k.seccion)}</td><td><div class="renta-cuentas">${k.cuentas.map((q) => `${etqEst(q)}${esc(q.codigo)} ${esc(q.nombre)} <span class="text-dark">${money(q.valor)}</span>`).join('<br>')}</div></td><td class="val fw-bold">${money(k.valor)}</td></tr>`
            ).join('');
            if ((c.sin_casillero || []).length) {
                html += `<tr class="renta-sec"><td colspan="4">Cuentas con saldo sin casillero SRI (no entran al formulario)</td></tr>` +
                    c.sin_casillero.map((q) => `<tr><td class="cas text-danger">—</td><td>${esc(q.seccion)}</td><td>${esc(q.codigo)} ${esc(q.nombre)}</td><td class="val">${money(q.valor)}</td></tr>`).join('');
            }
            tc.innerHTML = html || '<tr><td colspan="4" class="text-center text-muted py-3">Sin asientos contabilizados en el ejercicio.</td></tr>';
        }

        // Fuentes del detalle
        const sel = $('renta-detalle-fuente');
        if (!sel.options.length) {
            Object.keys(d.fuentes_detalle || CFG.fuentes).forEach((k) => {
                const o = document.createElement('option');
                o.value = k;
                o.textContent = (d.fuentes_detalle || CFG.fuentes)[k];
                sel.appendChild(o);
            });
        }
        $('renta-detalle').innerHTML = '';
        $('renta-detalle-resumen').textContent = '';
        if (document.querySelector('#tab-detalle.active')) RENTA_detalle();
    }

    window.RENTA_detalle = async function () {
        if (!ultimoCalculo) return;
        const fuente = $('renta-detalle-fuente').value;
        const tb = $('renta-detalle');
        tb.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">Cargando…</td></tr>';
        try {
            const r = await fetch(`${url('detalleAjax')}?anio=${encodeURIComponent(ultimoCalculo.anio)}&fuente=${encodeURIComponent(fuente)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const j = await r.json();
            if (!j.success) throw new Error(j.message || 'No se pudo cargar el detalle.');
            let base = 0, total = 0;
            const colEst = CFG.consolidado ? (x) => `<td class="small text-muted">${esc(x.establecimiento)}</td>` : () => '';
            tb.innerHTML = j.data.map((x) => {
                base += Number(x.base) || 0; total += Number(x.total) || 0;
                return `<tr>${colEst(x)}<td>${esc(x.fecha_emision)}</td><td>${esc(x.tipo_nombre)}</td><td>${esc(x.numero)}</td><td>${esc(x.tercero)}</td><td>${esc(x.identificacion)}</td><td class="val">${money(x.base)}</td><td class="val">${money(x.total)}</td></tr>`;
            }).join('') || '<tr><td colspan="8" class="text-center text-muted py-3">No hay documentos en esta fuente para el ejercicio.</td></tr>';
            $('renta-detalle-resumen').textContent = `${j.data.length} documento(s) · base ${money(base)} · total ${money(total)}`;
        } catch (e) {
            tb.innerHTML = `<tr><td colspan="7" class="text-danger py-3">${esc(e.message)}</td></tr>`;
        }
    };

    window.RENTA_exportar = function (tipo) {
        leerAjustesTabla();
        const u = `${url(tipo)}?${params().toString()}`;
        if (typeof window.CMG_descargar === 'function') {
            window.CMG_descargar(u);
        } else {
            window.location.href = u;
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        const tabDetalle = document.querySelector('[data-bs-target="#tab-detalle"]');
        if (tabDetalle) tabDetalle.addEventListener('shown.bs.tab', () => { if (!$('renta-detalle').innerHTML) RENTA_detalle(); });
        RENTA_calcular();
    });
})();
