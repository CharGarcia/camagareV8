/**
 * reporte_ingresos_diferidos.js
 * Reporte de Ingresos Diferidos (NIIF 15) – lógica del cliente.
 *
 * Saldos de las suscripciones que reconocen el ingreso durante el período, al cierre del mes
 * elegido, por documento, con la conciliación contra el mayor. El servidor devuelve todas las
 * filas del filtro; el filtro de texto, el orden por columna y la paginación son locales.
 */

'use strict';

let RID_datos   = [];        // filas recibidas del servidor
let RID_vista   = [];        // filas tras filtro de texto y orden
let RID_pagina  = 1;
let RID_orden   = { col: 'cliente', dir: 'asc' };
let RID_urls    = { pdf: '', excel: '' };
const RID_POR_PAGINA = 50;

const RID_$ = id => document.getElementById(id);

document.addEventListener('DOMContentLoaded', () => {
    RID_initBuscadorClientes();
    RID_$('rid-mes').addEventListener('change', RID_cargar);
    RID_$('rid-buscador').addEventListener('input', () => { RID_pagina = 1; RID_aplicarVista(); });
    document.querySelectorAll('#tabla-rid thead th[data-orden]').forEach(th => {
        th.addEventListener('click', () => {
            const col = th.dataset.orden;
            RID_orden = { col, dir: RID_orden.col === col && RID_orden.dir === 'asc' ? 'desc' : 'asc' };
            RID_aplicarVista();
        });
    });
    RID_$('rid-paginacion').addEventListener('click', e => {
        const b = e.target.closest('[data-pag]');
        if (!b || b.disabled) return;
        RID_pagina += parseInt(b.dataset.pag, 10);
        RID_pintarTabla();
    });
    RID_$('ridBtnPdf').onclick   = () => RID_urls.pdf && CMG_descargar(RID_urls.pdf);
    RID_$('ridBtnExcel').onclick = () => RID_urls.excel && CMG_descargar(RID_urls.excel);
    RID_cargar();
});

function RID_filtros() {
    return { mes: RID_$('rid-mes').value, id_cliente: RID_$('rid-id-cliente').value || '' };
}

async function RID_cargar() {
    const tbody = RID_$('rid-tbody');
    tbody.innerHTML = `<tr><td colspan="9" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Generando…</td></tr>`;
    RID_$('ridBtnPdf').disabled = true;
    RID_$('ridBtnExcel').disabled = true;
    if (!RID_$('rid-mes').value) RID_$('rid-mes').value = RID_MES_DEFECTO;

    try {
        const params = new URLSearchParams(RID_filtros());
        const r = await fetch(`${BASE_URL}/${RUTA_MODULO_RID}/generarAjax?${params.toString()}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const json = await r.json();
        if (!json.ok) {
            tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4">${RID_esc(json.mensaje || 'Error al generar el reporte.')}</td></tr>`;
            RID_pintarConciliacion([], null);
            return;
        }
        RID_datos = json.filas || [];
        RID_urls  = { pdf: json.pdf_url, excel: json.excel_url };
        RID_pintarKpis(json.totales || {}, RID_datos);
        RID_pintarConciliacion(json.conciliacion || [], json.conciliacion_nota || null);
        RID_pagina = 1;
        RID_aplicarVista();
        RID_$('ridBtnPdf').disabled   = false;
        RID_$('ridBtnExcel').disabled = false;
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4">Error de comunicación con el servidor.</td></tr>`;
    }
}

function RID_pintarKpis(t, filas) {
    RID_$('rid-kpi-corriente').textContent    = RID_money(t.corriente);
    RID_$('rid-kpi-no-corriente').textContent = RID_money(t.no_corriente);
    RID_$('rid-kpi-por-facturar').textContent = RID_money(t.por_facturar);
    RID_$('rid-kpi-documentos').textContent   = filas.length;
    RID_$('rid-kpi-clientes').textContent     = new Set(filas.map(f => f.id_cliente)).size;
}

function RID_pintarConciliacion(conc, nota) {
    const tbody = RID_$('rid-conciliacion');
    tbody.innerHTML = conc.length ? conc.map(c => {
        const dif = c.diferencia;
        const cls = dif === null ? 'text-muted' : (Math.abs(dif) >= 0.01 ? 'text-danger fw-bold' : 'text-success');
        return `<tr>
            <td class="ps-3">${RID_esc(c.concepto)}</td>
            <td>${c.cuenta ? RID_esc(c.cuenta) : '<span class="text-muted">sin cuenta configurada</span>'}</td>
            <td class="text-end">${RID_money(c.cronograma)}</td>
            <td class="text-end">${c.mayor === null ? '—' : RID_money(c.mayor)}</td>
            <td class="text-end pe-3 ${cls}">${dif === null ? '—' : RID_money(dif)}</td>
        </tr>`;
    }).join('') : '<tr><td colspan="5" class="text-center text-muted py-3">—</td></tr>';
    const n = RID_$('rid-conciliacion-nota');
    n.textContent = nota || '';
    n.classList.toggle('d-none', !nota);
}

/* Filtro de texto + orden, luego pinta la página. */
function RID_aplicarVista() {
    const q = RID_$('rid-buscador').value.trim().toLowerCase();
    RID_vista = !q ? [...RID_datos] : RID_datos.filter(f =>
        [f.cliente, f.identificacion, f.documento, '#' + f.id_suscripcion, f.ultimo_mes]
            .some(v => String(v ?? '').toLowerCase().includes(q)));

    const { col, dir } = RID_orden;
    const num = ['corriente', 'no_corriente', 'por_facturar', 'id_suscripcion'].includes(col);
    RID_vista.sort((a, b) => {
        const va = num ? (parseFloat(a[col]) || 0) : String(a[col] ?? '').toLowerCase();
        const vb = num ? (parseFloat(b[col]) || 0) : String(b[col] ?? '').toLowerCase();
        return (va < vb ? -1 : va > vb ? 1 : 0) * (dir === 'asc' ? 1 : -1);
    });

    document.querySelectorAll('#tabla-rid thead th[data-orden]').forEach(th => {
        th.querySelector('.rid-orden')?.remove();
        if (th.dataset.orden === col) {
            th.insertAdjacentHTML('beforeend', ` <i class="bi bi-sort-${dir === 'asc' ? 'up' : 'down'} small rid-orden"></i>`);
        }
    });
    RID_pintarTabla();
}

function RID_pintarTabla() {
    const tbody = RID_$('rid-tbody');
    const total = RID_vista.length;
    const paginas = Math.max(1, Math.ceil(total / RID_POR_PAGINA));
    RID_pagina = Math.min(Math.max(1, RID_pagina), paginas);
    const desde = (RID_pagina - 1) * RID_POR_PAGINA;
    const filas = RID_vista.slice(desde, desde + RID_POR_PAGINA);

    if (!total) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center py-5 text-muted">
            <i class="bi bi-check2-circle fs-3 d-block mb-2 text-success opacity-50"></i>
            No hay saldos de ingresos diferidos ni por facturar a esa fecha.</td></tr>`;
    } else {
        const suma = k => RID_vista.reduce((s, f) => s + (parseFloat(f[k]) || 0), 0);
        tbody.innerHTML = filas.map(f => {
            const tipo = f.tipo_documento === 'recibo' ? 'RECIBO' : 'FACTURA';
            const attrs = f.id_documento
                ? ` data-doc-id="${f.id_documento}" data-doc-tipo="${tipo}" data-doc-numero="${RID_esc(f.documento)}" data-doc-sujeto="${RID_esc(f.cliente)}" title="Clic para ver el documento"`
                : '';
            return `<tr${attrs}>
                <td class="ps-3" data-col="cliente" title="${RID_esc(f.cliente)}">${RID_esc(f.cliente)}</td>
                <td data-col="ruc">${RID_esc(f.identificacion)}</td>
                <td class="text-center" data-col="suscripcion">#${RID_esc(f.id_suscripcion)}</td>
                <td data-col="documento">${RID_esc(f.documento)}</td>
                <td data-col="fecha">${RID_fecha(f.fecha)}</td>
                <td data-col="ultimo_mes">${RID_mes(f.ultimo_mes)}</td>
                <td class="text-end" data-col="corriente">${parseFloat(f.corriente) ? RID_money(f.corriente) : ''}</td>
                <td class="text-end" data-col="no_corriente">${parseFloat(f.no_corriente) ? RID_money(f.no_corriente) : ''}</td>
                <td class="text-end pe-3" data-col="por_facturar">${parseFloat(f.por_facturar) ? RID_money(f.por_facturar) : ''}</td>
            </tr>`;
        }).join('') + `<tr class="rid-total">
                <td class="ps-3" data-col="cliente">TOTAL (${total})</td><td data-col="ruc"></td><td data-col="suscripcion"></td>
                <td data-col="documento"></td><td data-col="fecha"></td><td data-col="ultimo_mes"></td>
                <td class="text-end" data-col="corriente">${RID_money(suma('corriente'))}</td>
                <td class="text-end" data-col="no_corriente">${RID_money(suma('no_corriente'))}</td>
                <td class="text-end pe-3" data-col="por_facturar">${RID_money(suma('por_facturar'))}</td>
            </tr>`;
    }

    RID_$('rid-count-label').textContent = total ? `${desde + 1}-${Math.min(desde + RID_POR_PAGINA, total)}/${total}` : '0/0';
    const [prev, next] = RID_$('rid-paginacion').querySelectorAll('[data-pag]');
    prev.disabled = RID_pagina <= 1;
    next.disabled = RID_pagina >= paginas;
}

/* ── Buscador de cliente (selección tipo chip: Backspace/Delete limpia todo) ── */
function RID_initBuscadorClientes() {
    const input = RID_$('rid-search-cliente');
    const drop  = RID_$('rid-dropdown-clientes');
    let timer = null;

    input.addEventListener('input', () => {
        const q = input.value.trim();
        clearTimeout(timer);
        if (q.length < 2) { drop.classList.add('d-none'); return; }
        timer = setTimeout(async () => {
            try {
                const r = await fetch(`${BASE_URL}/${RUTA_MODULO_RID}/buscarClientesAjax?q=${encodeURIComponent(q)}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const d = await r.json();
                drop.innerHTML = '';
                if (!d.data || !d.data.length) {
                    drop.innerHTML = '<div class="list-group-item small text-muted">Sin resultados.</div>';
                } else {
                    d.data.forEach(c => {
                        const b = document.createElement('button');
                        b.type = 'button';
                        b.className = 'list-group-item list-group-item-action small py-1';
                        b.innerHTML = `<strong>${RID_esc(c.nombre)}</strong> <span class="text-muted">${RID_esc(c.identificacion || '')}</span>`;
                        b.onclick = () => { RID_setCliente(c.id, c.nombre, c.identificacion); drop.classList.add('d-none'); };
                        drop.appendChild(b);
                    });
                }
                drop.classList.remove('d-none');
            } catch (e) { drop.classList.add('d-none'); }
        }, 300);
    });

    input.addEventListener('keydown', e => {
        if ((e.key === 'Backspace' || e.key === 'Delete') && RID_$('rid-id-cliente').value) {
            e.preventDefault();
            RID_quitarCliente();
        }
    });
    document.addEventListener('click', e => {
        if (!e.target.closest('#rid-search-cliente') && !e.target.closest('#rid-dropdown-clientes')) drop.classList.add('d-none');
    });
}

function RID_setCliente(id, nombre, ident) {
    RID_$('rid-id-cliente').value = id;
    RID_$('rid-search-cliente').value = `${ident ? ident + ' - ' : ''}${nombre}`;
    RID_cargar();
}

function RID_quitarCliente() {
    RID_$('rid-id-cliente').value = '';
    RID_$('rid-search-cliente').value = '';
    RID_$('rid-dropdown-clientes').classList.add('d-none');
    RID_cargar();
}

function RID_limpiarFiltros() {
    RID_$('rid-mes').value = RID_MES_DEFECTO;
    RID_$('rid-buscador').value = '';
    RID_quitarCliente();
}

/* ── Clic en la fila: vista previa del documento ── */
document.addEventListener('click', function (e) {
    const tr = e.target.closest('#tabla-rid tr[data-doc-id]');
    if (!tr || e.target.closest('button, a, input, select, label')) return;
    if (typeof window.CMG_abrirPreviewDoc !== 'function') return;
    window.CMG_abrirPreviewDoc(tr.dataset.docId, tr.dataset.docTipo, {
        numero:      tr.dataset.docNumero || '',
        sujetoLabel: 'Cliente',
        sujeto:      tr.dataset.docSujeto || ''
    });
});

/* ── Utilidades ── */
function RID_esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}
function RID_money(n) {
    const v = parseFloat(n) || 0;
    return (v < 0 ? '-$' : '$') + Math.abs(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function RID_fecha(iso) {
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso || '');
    return m ? `${m[3]}-${m[2]}-${m[1]}` : '';
}
function RID_mes(ym) {
    const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    const m = /^(\d{4})-(\d{2})/.exec(ym || '');
    return m ? `${MESES[parseInt(m[2], 10) - 1]}-${m[1]}` : '';
}
