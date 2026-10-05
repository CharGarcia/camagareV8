/**
 * Modal «Devengar mes» del Reporte de Ingresos Diferidos (app/views/modulos/reporte_ingresos_diferidos/modal_devengo_mes.php).
 * Al abrir o cambiar el mes pide la vista previa; «Generar asiento» registra el devengo y
 * «Revertir mes» anula los asientos de devengo del mes. Controles por atributo data-dm.
 *
 * SuscDevengoMes.iniciar({ modalId, urlPreview, urlGenerar, urlRevertir });
 * Abrir: SuscDevengoMes.abrir().
 */
window.SuscDevengoMes = (function () {
    'use strict';

    let cfg = null;
    let peticion = 0;

    const esc = s => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    const dinero = n => '$' + (parseFloat(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const fecha = iso => { const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso || ''); return m ? `${m[3]}-${m[2]}-${m[1]}` : (iso || ''); };

    function modal() { return document.getElementById(cfg.modalId); }
    function el(k) { return modal().querySelector(`[data-dm="${k}"]`); }
    function mostrar(k, si) { el(k)?.classList.toggle('d-none', !si); }

    async function cargar() {
        const mes = document.getElementById('dm_mes').value;
        const n = ++peticion;
        mostrar('cargando', true); mostrar('contenido', false); mostrar('error', false); mostrar('btn-revertir', false);
        const btnGen = el('btn-generar');
        if (btnGen) btnGen.disabled = true;
        if (!mes) { mostrar('cargando', false); return; }
        try {
            const r = await fetch(`${cfg.urlPreview}?mes=${encodeURIComponent(mes)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const res = await r.json();
            if (n !== peticion) return;
            mostrar('cargando', false);
            if (!res.ok) { el('error').textContent = res.mensaje || 'No se pudo calcular.'; mostrar('error', true); return; }
            pintar(res);
        } catch (e) {
            if (n !== peticion) return;
            mostrar('cargando', false);
            el('error').textContent = 'No se pudo calcular el devengo del mes.';
            mostrar('error', true);
        }
    }

    function pintar(res) {
        el('diferido').textContent = dinero(res.diferidos.monto);
        el('diferido-filas').textContent = `${res.diferidos.filas} fila(s)`;
        el('anteriores').textContent = dinero(res.meses_anteriores.monto);
        el('provision').textContent = dinero(res.provisiones.monto);
        el('provision-susc').textContent = `${res.provisiones.suscripciones} suscripción(es)`;
        el('esperando').textContent = dinero(res.esperando_asiento.monto);

        const avisos = [];
        if (res.error) avisos.push(`<b>No se puede generar:</b> ${esc(res.error)}`);
        if (res.esperando_asiento.filas > 0) {
            avisos.push(`${res.esperando_asiento.filas} fila(s) por ${dinero(res.esperando_asiento.monto)} esperan que su factura o recibo tenga asiento (borrador sin autorizar o asiento pendiente); se devengarán en una próxima corrida.`);
        }
        if (res.meses_anteriores.filas > 0) {
            avisos.push(`Incluye ${dinero(res.meses_anteriores.monto)} de meses anteriores que quedaron sin devengar.`);
        }
        el('aviso').innerHTML = avisos.join('<br>');
        mostrar('aviso', avisos.length > 0);

        const hayAlgo = res.diferidos.filas > 0 || res.provisiones.filas > 0;
        mostrar('vacio', !hayAlgo);
        el('fecha').textContent = fecha(res.fecha_asiento);
        el('asiento').innerHTML = (res.asiento || []).map(l => `
            <tr>
                <td class="ps-2 text-nowrap">${esc(l.cuenta_codigo)} ${esc(l.cuenta_nombre)}</td>
                <td>${esc(l.referencia_detalle)}</td>
                <td class="text-end">${parseFloat(l.debe) ? dinero(l.debe) : ''}</td>
                <td class="text-end pe-2">${parseFloat(l.haber) ? dinero(l.haber) : ''}</td>
            </tr>`).join('');
        mostrar('asiento-wrap', hayAlgo && !res.error && (res.asiento || []).length > 0);

        const existentes = res.asientos_existentes || [];
        el('existentes').innerHTML = existentes.map(a => `
            <li class="list-group-item d-flex justify-content-between py-1">
                <span>${esc(a.numero_comprobante)} · ${esc(fecha(a.fecha_asiento))}</span>
                <span class="fw-bold">${dinero(a.total_debe)}</span>
            </li>`).join('');
        mostrar('existentes-wrap', existentes.length > 0);
        mostrar('btn-revertir', existentes.length > 0);

        mostrar('contenido', true);
        const btnGen = el('btn-generar');
        if (btnGen) btnGen.disabled = !hayAlgo || !!res.error;
    }

    async function enviar(url, titulo, texto, icono) {
        const mes = document.getElementById('dm_mes').value;
        const conf = await Swal.fire({
            icon: icono, title: titulo, text: texto, showCancelButton: true,
            confirmButtonText: 'Sí, continuar', cancelButtonText: 'Cancelar', target: modal(),
        });
        if (!conf.isConfirmed) return;
        const fd = new FormData();
        fd.append('mes', mes);
        try {
            const r = await fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const res = await r.json();
            await Swal.fire({ icon: res.ok ? 'success' : 'error', title: res.ok ? 'Listo' : 'No se pudo', text: res.mensaje || '', target: modal() });
            if (res.ok && typeof cfg.alCambiar === 'function') cfg.alCambiar(); // el reporte se recarga
        } catch (e) {
            await Swal.fire({ icon: 'error', title: 'No se pudo', text: 'Error de comunicación con el servidor.', target: modal() });
        }
        cargar();
    }

    function iniciar(c) {
        cfg = c;
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => iniciar(c));
            return;
        }
        if (!modal()) return;
        document.getElementById('dm_mes')?.addEventListener('change', cargar);
        el('btn-generar')?.addEventListener('click', () => enviar(cfg.urlGenerar, 'Generar asiento de devengo',
            'Se registrará el asiento del mes elegido con los montos de la vista previa.', 'question'));
        el('btn-revertir')?.addEventListener('click', () => enviar(cfg.urlRevertir, 'Revertir devengo del mes',
            'Se anularán los asientos de devengo del mes: lo diferido vuelve a «por devengar» y las provisiones de mes caído se dan de baja.', 'warning'));
    }

    /** @param {string} [mes] YYYY-MM con que abrir (el elegido en el reporte). */
    function abrir(mes) {
        const inp = document.getElementById('dm_mes');
        if (mes && inp && (!inp.max || mes <= inp.max)) inp.value = mes;
        bootstrap.Modal.getOrCreateInstance(modal()).show();
        cargar();
    }

    return { iniciar, abrir, alCambiar: fn => { if (cfg) cfg.alCambiar = fn; } };
})();
