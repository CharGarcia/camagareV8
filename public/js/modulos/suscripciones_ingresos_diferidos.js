/**
 * Modales «Ingresos diferidos» y «Apertura de ingresos diferidos» de Suscripciones
 * (app/views/modulos/suscripciones/modal_ingresos_diferidos.php).
 *
 * SuscIngresosDiferidos.iniciar({ urlReporte, urlExcel, urlApPreview, urlApAplicar, urlApRevertir });
 * Abrir: SuscIngresosDiferidos.abrirReporte() / SuscIngresosDiferidos.abrirApertura().
 * Controles por atributo data-idf (reporte) y data-apd (apertura).
 */
window.SuscIngresosDiferidos = (function () {
    'use strict';

    let cfg = null;
    const pet = { idf: 0, apd: 0 };

    const esc = s => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    const dinero = n => {
        const v = parseFloat(n) || 0;
        return (v < 0 ? '-$' : '$') + Math.abs(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };
    const fecha = iso => { const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso || ''); return m ? `${m[3]}-${m[2]}-${m[1]}` : (iso || ''); };
    const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    const mes = ym => { const m = /^(\d{4})-(\d{2})/.exec(ym || ''); return m ? `${MESES[+m[2] - 1]}-${m[1]}` : ''; };

    const sel = (pref, k) => document.querySelector(`[data-${pref}="${k}"]`);
    const ver = (pref, k, si) => sel(pref, k)?.classList.toggle('d-none', !si);

    async function pedirJson(url) {
        const r = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        return r.json();
    }

    // ── Reporte ─────────────────────────────────────────────────────────────

    async function cargarReporte() {
        const m = document.getElementById('idf_mes').value;
        const n = ++pet.idf;
        ver('idf', 'cargando', true); ver('idf', 'contenido', false); ver('idf', 'error', false);
        if (!m) { ver('idf', 'cargando', false); return; }
        try {
            const res = await pedirJson(`${cfg.urlReporte}?mes=${encodeURIComponent(m)}`);
            if (n !== pet.idf) return;
            ver('idf', 'cargando', false);
            if (!res.ok) { sel('idf', 'error').textContent = res.mensaje || 'No se pudo generar el reporte.'; ver('idf', 'error', true); return; }
            pintarReporte(res);
        } catch (e) {
            if (n !== pet.idf) return;
            ver('idf', 'cargando', false);
            sel('idf', 'error').textContent = 'No se pudo generar el reporte.';
            ver('idf', 'error', true);
        }
    }

    function pintarReporte(res) {
        sel('idf', 'corriente').textContent = dinero(res.totales.corriente);
        sel('idf', 'no-corriente').textContent = dinero(res.totales.no_corriente);
        sel('idf', 'por-facturar').textContent = dinero(res.totales.por_facturar);
        sel('idf', 'documentos').textContent = (res.filas || []).length;

        sel('idf', 'conciliacion').innerHTML = (res.conciliacion || []).map(c => {
            const dif = c.diferencia;
            const claseDif = dif === null ? 'text-muted' : (Math.abs(dif) >= 0.01 ? 'text-danger fw-bold' : 'text-success');
            return `<tr>
                <td class="ps-2">${esc(c.concepto)}</td>
                <td>${c.cuenta ? esc(c.cuenta) : '<span class="text-muted">sin cuenta configurada</span>'}</td>
                <td class="text-end">${dinero(c.cronograma)}</td>
                <td class="text-end">${c.mayor === null ? '—' : dinero(c.mayor)}</td>
                <td class="text-end pe-2 ${claseDif}">${dif === null ? '—' : dinero(dif)}</td>
            </tr>`;
        }).join('');

        const filas = res.filas || [];
        sel('idf', 'filas').innerHTML = filas.length ? filas.map(f => `
            <tr>
                <td class="ps-2 text-truncate" style="max-width: 240px;" title="${esc(f.cliente)}">${esc(f.cliente)}</td>
                <td>#${esc(f.id_suscripcion)}</td>
                <td class="text-nowrap">${esc(f.documento)}</td>
                <td class="text-nowrap">${esc(fecha(f.fecha))}</td>
                <td class="text-nowrap">${esc(mes(f.ultimo_mes))}</td>
                <td class="text-end">${parseFloat(f.corriente) ? dinero(f.corriente) : ''}</td>
                <td class="text-end">${parseFloat(f.no_corriente) ? dinero(f.no_corriente) : ''}</td>
                <td class="text-end pe-2">${parseFloat(f.por_facturar) ? dinero(f.por_facturar) : ''}</td>
            </tr>`).join('')
            : '<tr><td colspan="8" class="text-center text-muted py-3">No hay saldos de ingresos diferidos ni por facturar a esa fecha.</td></tr>';
        ver('idf', 'contenido', true);
    }

    // ── Apertura ────────────────────────────────────────────────────────────

    async function cargarApertura() {
        const m = document.getElementById('apd_mes').value;
        const n = ++pet.apd;
        ver('apd', 'cargando', true); ver('apd', 'contenido', false); ver('apd', 'error', false); ver('apd', 'btn-revertir', false);
        const btn = sel('apd', 'btn-aplicar');
        if (btn) btn.disabled = true;
        if (!m) { ver('apd', 'cargando', false); return; }
        try {
            const res = await pedirJson(`${cfg.urlApPreview}?mes=${encodeURIComponent(m)}`);
            if (n !== pet.apd) return;
            ver('apd', 'cargando', false);
            if (!res.ok) { sel('apd', 'error').textContent = res.mensaje || 'No se pudo calcular la apertura.'; ver('apd', 'error', true); return; }
            pintarApertura(res);
        } catch (e) {
            if (n !== pet.apd) return;
            ver('apd', 'cargando', false);
            sel('apd', 'error').textContent = 'No se pudo calcular la apertura.';
            ver('apd', 'error', true);
        }
    }

    function pintarApertura(res) {
        const docs = res.documentos || [];
        const hay = docs.length > 0;
        ver('apd', 'vacio', !hay);
        ver('apd', 'docs-wrap', hay && !res.error);
        sel('apd', 'aviso').innerHTML = res.error ? `<b>No se puede registrar:</b> ${esc(res.error)}` : '';
        ver('apd', 'aviso', !!res.error);
        sel('apd', 'total').textContent = dinero(res.total);
        sel('apd', 'fecha').textContent = fecha(res.fecha_asiento);
        sel('apd', 'docs').innerHTML = docs.map(d => `
            <tr>
                <td class="ps-2 text-nowrap">${d.tipo_documento === 'recibo' ? 'REC' : 'FAC'} ${esc(d.numero)}</td>
                <td class="text-nowrap">${esc(fecha(d.fecha_emision))}</td>
                <td class="text-truncate" style="max-width: 260px;" title="${esc(d.cliente)}">${esc(d.cliente)}</td>
                <td class="text-end pe-2">${dinero(d.monto)}</td>
            </tr>`).join('');
        sel('apd', 'asiento').innerHTML = (res.asiento || []).map(l => `
            <tr>
                <td class="ps-2 text-nowrap">${esc(l.cuenta_codigo)} ${esc(l.cuenta_nombre)}</td>
                <td>${esc(l.referencia_detalle)}</td>
                <td class="text-end">${parseFloat(l.debe) ? dinero(l.debe) : ''}</td>
                <td class="text-end pe-2">${parseFloat(l.haber) ? dinero(l.haber) : ''}</td>
            </tr>`).join('');
        const existentes = res.asientos_existentes || [];
        sel('apd', 'existentes').innerHTML = existentes.map(a => `
            <li class="list-group-item d-flex justify-content-between py-1">
                <span>${esc(a.numero_comprobante)} · ${esc(fecha(a.fecha_asiento))}</span>
                <span class="fw-bold">${dinero(a.total_debe)}</span>
            </li>`).join('');
        ver('apd', 'existentes-wrap', existentes.length > 0);
        ver('apd', 'btn-revertir', existentes.length > 0);
        ver('apd', 'contenido', true);
        const btn = sel('apd', 'btn-aplicar');
        if (btn) btn.disabled = !hay || !!res.error;
    }

    async function enviarApertura(url, titulo, texto, icono) {
        const modal = document.getElementById('modalAperturaDevengo');
        const conf = await Swal.fire({ icon: icono, title: titulo, text: texto, showCancelButton: true,
            confirmButtonText: 'Sí, continuar', cancelButtonText: 'Cancelar', target: modal });
        if (!conf.isConfirmed) return;
        const fd = new FormData();
        fd.append('mes', document.getElementById('apd_mes').value);
        try {
            const r = await fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const res = await r.json();
            await Swal.fire({ icon: res.ok ? 'success' : 'error', title: res.ok ? 'Listo' : 'No se pudo', text: res.mensaje || '', target: modal });
        } catch (e) {
            await Swal.fire({ icon: 'error', title: 'No se pudo', text: 'Error de comunicación con el servidor.', target: modal });
        }
        cargarApertura();
    }

    // ── Inicio ──────────────────────────────────────────────────────────────

    function iniciar(c) {
        cfg = c;
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => iniciar(c));
            return;
        }
        document.getElementById('idf_mes')?.addEventListener('change', cargarReporte);
        sel('idf', 'excel')?.addEventListener('click', () => {
            const m = document.getElementById('idf_mes').value;
            if (m) window.CMG_descargar(`${cfg.urlExcel}?mes=${encodeURIComponent(m)}`);
        });
        document.getElementById('apd_mes')?.addEventListener('change', cargarApertura);
        sel('apd', 'btn-aplicar')?.addEventListener('click', () => enviarApertura(cfg.urlApAplicar, 'Registrar apertura',
            'Se armará el cronograma de los documentos listados y se registrará el asiento de reclasificación.', 'question'));
        sel('apd', 'btn-revertir')?.addEventListener('click', () => enviarApertura(cfg.urlApRevertir, 'Revertir apertura',
            'Se anulará el asiento de apertura y se darán de baja sus filas del cronograma.', 'warning'));
    }

    function abrirReporte() {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalIngresosDiferidos')).show();
        cargarReporte();
    }

    function abrirApertura() {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAperturaDevengo')).show();
        cargarApertura();
    }

    return { iniciar, abrirReporte, abrirApertura };
})();
