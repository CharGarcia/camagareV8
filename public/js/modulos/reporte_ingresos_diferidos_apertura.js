/**
 * Modal «Apertura de ingresos diferidos» del Reporte de Ingresos Diferidos
 * (app/views/modulos/reporte_ingresos_diferidos/modal_apertura.php).
 *
 * SuscAperturaDevengo.iniciar({ urlApPreview, urlApAplicar, urlApRevertir });
 * Abrir: SuscAperturaDevengo.abrir(). Controles por atributo data-apd.
 */
window.SuscAperturaDevengo = (function () {
    'use strict';

    let cfg = null;
    let peticion = 0;

    const esc = s => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    const dinero = n => {
        const v = parseFloat(n) || 0;
        return (v < 0 ? '-$' : '$') + Math.abs(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };
    const fecha = iso => { const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso || ''); return m ? `${m[3]}-${m[2]}-${m[1]}` : (iso || ''); };

    const sel = k => document.querySelector(`[data-apd="${k}"]`);
    const ver = (k, si) => sel(k)?.classList.toggle('d-none', !si);

    async function cargar() {
        const m = document.getElementById('apd_mes').value;
        const n = ++peticion;
        ver('cargando', true); ver('contenido', false); ver('error', false); ver('btn-revertir', false);
        const btn = sel('btn-aplicar');
        if (btn) btn.disabled = true;
        if (!m) { ver('cargando', false); return; }
        try {
            const r = await fetch(`${cfg.urlApPreview}?mes=${encodeURIComponent(m)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const res = await r.json();
            if (n !== peticion) return;
            ver('cargando', false);
            if (!res.ok) { sel('error').textContent = res.mensaje || 'No se pudo calcular la apertura.'; ver('error', true); return; }
            pintar(res);
        } catch (e) {
            if (n !== peticion) return;
            ver('cargando', false);
            sel('error').textContent = 'No se pudo calcular la apertura.';
            ver('error', true);
        }
    }

    function pintar(res) {
        const docs = res.documentos || [];
        const hay = docs.length > 0;
        ver('vacio', !hay);
        ver('docs-wrap', hay && !res.error);
        sel('aviso').innerHTML = res.error ? `<b>No se puede registrar:</b> ${esc(res.error)}` : '';
        ver('aviso', !!res.error);
        sel('total').textContent = dinero(res.total);
        sel('fecha').textContent = fecha(res.fecha_asiento);
        sel('docs').innerHTML = docs.map(d => `
            <tr>
                <td class="ps-2 text-nowrap">${d.tipo_documento === 'recibo' ? 'REC' : 'FAC'} ${esc(d.numero)}</td>
                <td class="text-nowrap">${esc(fecha(d.fecha_emision))}</td>
                <td class="text-truncate" style="max-width: 260px;" title="${esc(d.cliente)}">${esc(d.cliente)}</td>
                <td class="text-end pe-2">${dinero(d.monto)}</td>
            </tr>`).join('');
        sel('asiento').innerHTML = (res.asiento || []).map(l => `
            <tr>
                <td class="ps-2 text-nowrap">${esc(l.cuenta_codigo)} ${esc(l.cuenta_nombre)}</td>
                <td>${esc(l.referencia_detalle)}</td>
                <td class="text-end">${parseFloat(l.debe) ? dinero(l.debe) : ''}</td>
                <td class="text-end pe-2">${parseFloat(l.haber) ? dinero(l.haber) : ''}</td>
            </tr>`).join('');
        const existentes = res.asientos_existentes || [];
        sel('existentes').innerHTML = existentes.map(a => `
            <li class="list-group-item d-flex justify-content-between py-1">
                <span>${esc(a.numero_comprobante)} · ${esc(fecha(a.fecha_asiento))}</span>
                <span class="fw-bold">${dinero(a.total_debe)}</span>
            </li>`).join('');
        ver('existentes-wrap', existentes.length > 0);
        ver('btn-revertir', existentes.length > 0);
        ver('contenido', true);
        const btn = sel('btn-aplicar');
        if (btn) btn.disabled = !hay || !!res.error;
    }

    async function enviar(url, titulo, texto, icono) {
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
            if (res.ok && typeof cfg.alCambiar === 'function') cfg.alCambiar(); // el reporte se recarga
        } catch (e) {
            await Swal.fire({ icon: 'error', title: 'No se pudo', text: 'Error de comunicación con el servidor.', target: modal });
        }
        cargar();
    }

    function iniciar(c) {
        cfg = c;
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => iniciar(c));
            return;
        }
        document.getElementById('apd_mes')?.addEventListener('change', cargar);
        sel('btn-aplicar')?.addEventListener('click', () => enviar(cfg.urlApAplicar, 'Registrar apertura',
            'Se armará el cronograma de los documentos listados y se registrará el asiento de reclasificación.', 'question'));
        sel('btn-revertir')?.addEventListener('click', () => enviar(cfg.urlApRevertir, 'Revertir apertura',
            'Se anulará el asiento de apertura y se darán de baja sus filas del cronograma.', 'warning'));
    }

    function abrir() {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAperturaDevengo')).show();
        cargar();
    }
    // Opcional: callback del reporte para recargarse tras registrar o revertir.
    function alCambiar(fn) { if (cfg) cfg.alCambiar = fn; }

    return { iniciar, abrir, alCambiar };
})();
