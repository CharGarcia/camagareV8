/**
 * Pestaña «Devengo» del modal de Suscripciones (app/views/modulos/suscripciones/modal_suscripcion.php):
 * cronograma mensual del ingreso diferido de los servicios facturados por adelantado
 * (tabla suscripciones_devengos). Solo lectura.
 *
 * Se carga al abrir la pestaña (no al abrir el modal) y se limpia al abrir y al cerrar el modal.
 *
 * SuscDevengo.iniciar({
 *   url:     '/…/modulos/suscripciones/devengoAjax',
 *   panel:   'pane-susc-devengo',     // id del tab-pane
 *   boton:   'susc-tab-devengo-btn',
 *   modalId: 'modalSusc',
 *   idSusc:  'susc_id',               // input con el id de la suscripción (vacío si es nueva)
 * });
 * Cada control del panel se ubica por su atributo data-sd.
 */
window.SuscDevengo = (function () {
    'use strict';

    const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    const ESTADOS = {
        pendiente: ['Por devengar', 'warning'],
        devengado: ['Devengado', 'success'],
        facturado: ['Facturado', 'primary'],
        anulado:   ['Anulado', 'secondary'],
    };

    function esc(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function fmtMes(iso) {
        const m = /^(\d{4})-(\d{2})/.exec(iso || '');
        return m ? `${MESES[parseInt(m[2], 10) - 1]}-${m[1]}` : (iso || '');
    }

    function fmtMoneda(n) {
        const v = parseFloat(n) || 0;
        return (v < 0 ? '-$' : '$') + Math.abs(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function iniciar(cfg) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => iniciar(cfg));
            return;
        }
        const panel = document.getElementById(cfg.panel);
        if (!panel) return;

        const el = clave => panel.querySelector(`[data-sd="${clave}"]`);
        const st = { cargado: null, peticion: 0 };

        function mostrarVacio(texto) {
            el('vacio-texto').textContent = texto;
            el('vacio').classList.remove('d-none');
            el('con-datos').classList.add('d-none');
        }

        function reset() {
            st.cargado = null;
            st.peticion++;
            el('tbody').innerHTML = '';
            mostrarVacio('Sin cronograma de devengo.');
        }

        function pintar(rows) {
            if (!rows.length) {
                mostrarVacio('Esta suscripción no tiene ingresos diferidos: todo lo facturado se reconoció al facturar.');
                return;
            }
            const suma = { total: 0, devengado: 0, pendiente: 0, anulado: 0 };
            el('tbody').innerHTML = rows.map(r => {
                const monto = parseFloat(r.monto) || 0;
                if (r.estado === 'anulado') {
                    suma.anulado += monto;
                } else {
                    suma.total += monto;
                    if (r.estado === 'pendiente') suma.pendiente += monto; else suma.devengado += monto;
                }
                const [etq, color] = ESTADOS[r.estado] || [r.estado, 'secondary'];
                const tachado = r.estado === 'anulado' ? ' text-decoration-line-through text-muted' : '';
                const doc = r.numero_documento
                    ? `${r.tipo_documento === 'recibo' ? 'REC' : 'FAC'} ${esc(r.numero_documento)}`
                    : '—';
                return `
                    <tr>
                        <td class="ps-2 text-nowrap">${esc(fmtMes(r.periodo))}</td>
                        <td class="text-nowrap">${doc}</td>
                        <td class="text-truncate" style="max-width: 260px;" title="${esc(r.descripcion)}">${esc(r.descripcion)}</td>
                        <td class="text-end text-nowrap${tachado}">${fmtMoneda(monto)}</td>
                        <td class="text-center"><span class="badge bg-${color} bg-opacity-10 text-${color} border border-${color} border-opacity-25">${esc(etq)}</span></td>
                        <td class="text-nowrap pe-2">${r.numero_asiento ? esc(r.numero_asiento) : '—'}</td>
                    </tr>`;
            }).join('');
            el('res-total').textContent     = fmtMoneda(suma.total);
            el('res-devengado').textContent = fmtMoneda(suma.devengado);
            el('res-pendiente').textContent = fmtMoneda(suma.pendiente);
            el('res-anulado').textContent   = fmtMoneda(suma.anulado);
            el('vacio').classList.add('d-none');
            el('con-datos').classList.remove('d-none');
        }

        async function asegurar() {
            const id = (document.getElementById(cfg.idSusc)?.value || '').trim();
            if (!id) {
                mostrarVacio('Guarde la suscripción para ver su devengo.');
                return;
            }
            if (st.cargado === id) return;

            const peticion = ++st.peticion;
            mostrarVacio('Cargando…');
            try {
                const r = await fetch(`${cfg.url}?id=${encodeURIComponent(id)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const res = await r.json();
                if (peticion !== st.peticion) return; // llegó tarde: otra suscripción
                if (!res.ok) {
                    mostrarVacio(res.mensaje || 'No se pudo cargar el devengo.');
                    return;
                }
                st.cargado = id;
                pintar(res.rows || []);
            } catch (e) {
                if (peticion === st.peticion) mostrarVacio('No se pudo cargar el devengo.');
            }
        }

        document.getElementById(cfg.boton)?.addEventListener('shown.bs.tab', asegurar);
        const modalEl = document.getElementById(cfg.modalId);
        modalEl?.addEventListener('show.bs.modal', (e) => { if (e.target === modalEl) reset(); });
        modalEl?.addEventListener('hidden.bs.modal', (e) => { if (e.target === modalEl) reset(); });
    }

    return { iniciar };
})();
