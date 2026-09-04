/**
 * reporte_retenciones_pendientes.js
 * Reporte de Retenciones de Venta Pendientes – lógica del cliente.
 *
 * Facturas de venta autorizadas sin comprobante de retención; permite enviar
 * un aviso por correo por factura (individual) o un solo correo por cliente
 * con todas sus facturas seleccionadas (agrupado).
 */

'use strict';

/* ════════════════════════════════════════════════════
   ESTADO GLOBAL
════════════════════════════════════════════════════ */
let RRP_datos         = [];           // filas recibidas del servidor
let RRP_filtradoLocal = [];           // filas tras el filtro de texto de la tabla
let RRP_seleccionados = new Set();    // ids de factura seleccionados
let RRP_vista         = 'detalle';    // detalle | cliente | mes
const RRP_gruposAbiertos = new Set(); // claves de grupos expandidos
let RRP_gruposAgrupado = [];          // grupos por cliente del modal de envío agrupado
let RRP_urls = { pdf: '', excel: '' };

const RRP_$ = id => document.getElementById(id);

/* ════════════════════════════════════════════════════
   INICIALIZACIÓN
════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
    if (typeof aplicarFavoritosModal === 'function') aplicarFavoritosModal();
    RRP_initBuscadorClientes();

    // Año y Mes recalculan Desde/Hasta.
    RRP_$('rrp-anio').addEventListener('change', () => { RRP_actualizarFechas(); RRP_cargar(); });
    RRP_$('rrp-mes').addEventListener('change', () => { RRP_actualizarFechas(); RRP_cargar(); });
    RRP_$('rrp-aviso').addEventListener('change', RRP_cargar);

    RRP_actualizarFechas();
    RRP_cargar();
});

/* Desde/Hasta a partir de Año + Mes (con tope en hoy). */
function RRP_actualizarFechas() {
    const anio = parseInt(RRP_$('rrp-anio').value, 10);
    if (!anio) return;
    const mes = RRP_$('rrp-mes').value ? parseInt(RRP_$('rrp-mes').value, 10) : null;

    let mIni = 1, mFin = 12;
    if (mes) { mIni = mes; mFin = mes; }

    const hoy = new Date(); hoy.setHours(0, 0, 0, 0);
    let desde = new Date(anio, mIni - 1, 1);
    let hasta = new Date(anio, mFin, 0);
    if (hasta > hoy) hasta = hoy;
    if (desde > hasta) desde = hasta;

    const fmt = d => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    RRP_$('rrp-fecha-desde').value = fmt(desde);
    RRP_$('rrp-fecha-hasta').value = fmt(hasta);
}

function RRP_filtros() {
    return {
        anio:         RRP_$('rrp-anio').value,
        mes:          RRP_$('rrp-mes').value,
        fecha_desde:  RRP_$('rrp-fecha-desde').value,
        fecha_hasta:  RRP_$('rrp-fecha-hasta').value,
        id_cliente:   RRP_$('rrp-id-cliente').value || 0,
        aviso:        RRP_$('rrp-aviso').value,
        buscar:       '',
    };
}

/* ════════════════════════════════════════════════════
   CARGA PRINCIPAL
════════════════════════════════════════════════════ */
async function RRP_cargar() {
    const tbody = RRP_$('rrp-tbody');
    tbody.innerHTML = `<tr><td colspan="11" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Generando…</td></tr>`;
    RRP_$('rrpBtnPdf').disabled = true; RRP_$('rrpBtnExcel').disabled = true;

    try {
        const params = new URLSearchParams(RRP_filtros());
        const r = await fetch(`${BASE_URL}/${RUTA_MODULO_RRP}/generarAjax?${params.toString()}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const json = await r.json();
        if (!json.ok) {
            tbody.innerHTML = `<tr><td colspan="11" class="text-center text-danger py-4">${RRP_esc(json.mensaje || 'Error al generar el reporte.')}</td></tr>`;
            return;
        }
        RRP_datos = json.rows || [];
        RRP_urls  = { pdf: json.pdf_url, excel: json.excel_url };

        // Mantener solo las selecciones que siguen existiendo
        const ids = new Set(RRP_datos.map(r => String(r.id)));
        RRP_seleccionados = new Set([...RRP_seleccionados].filter(k => ids.has(k)));

        RRP_actualizarStats(json.stats || {});
        RRP_filtrarTabla(RRP_$('rrp-buscador').value);

        RRP_$('rrpBtnPdf').disabled   = RRP_datos.length === 0;
        RRP_$('rrpBtnExcel').disabled = RRP_datos.length === 0;
        RRP_$('rrpBtnPdf').onclick    = () => window.open(RRP_urls.pdf, '_blank');
        RRP_$('rrpBtnExcel').onclick  = () => window.open(RRP_urls.excel, '_blank');
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="11" class="text-center text-danger py-4">Error de comunicación con el servidor.</td></tr>`;
    }
}

function RRP_actualizarStats(s) {
    RRP_$('rrp-kpi-facturas').textContent   = s.n_facturas ?? 0;
    RRP_$('rrp-kpi-clientes').textContent   = s.n_clientes ?? 0;
    RRP_$('rrp-kpi-subtotal').textContent   = '$' + RRP_fmt(s.total_subtotal);
    RRP_$('rrp-kpi-total').textContent      = '$' + RRP_fmt(s.total_general);
    RRP_$('rrp-kpi-avisadas').textContent   = s.n_avisadas ?? 0;
    RRP_$('rrp-kpi-sin-correo').textContent = s.n_sin_correo ?? 0;
}

/* Filtro de texto local sobre las filas ya cargadas. */
function RRP_filtrarTabla(q) {
    q = (q || '').trim().toLowerCase();
    RRP_filtradoLocal = !q ? RRP_datos : RRP_datos.filter(r =>
        [r.numero_factura, r.cliente_nombre, r.cliente_ruc, r.cliente_email].some(v => String(v || '').toLowerCase().includes(q))
    );
    RRP_render();
}

function RRP_setVista(modo) {
    RRP_vista = modo;
    ['detalle', 'cliente', 'mes'].forEach(m => {
        const b = RRP_$('rrp-btn-' + m);
        b.classList.toggle('btn-warning', m === modo);
        b.classList.toggle('btn-outline-warning', m !== modo);
    });
    RRP_render();
}

function RRP_render() {
    const label = RRP_$('rrp-count-label');
    label.textContent = `${RRP_filtradoLocal.length} factura(s)`;
    if (RRP_vista === 'cliente')  return RRP_renderAgrupado('cliente');
    if (RRP_vista === 'mes')      return RRP_renderAgrupado('mes');
    RRP_renderDetalle();
}

/* ════════════════════════════════════════════════════
   VISTA DETALLE
════════════════════════════════════════════════════ */
function RRP_renderDetalle() {
    const tbody = RRP_$('rrp-tbody');
    if (!RRP_filtradoLocal.length) {
        tbody.innerHTML = `<tr><td colspan="11" class="text-center py-5 text-muted">
            <i class="bi bi-check2-circle fs-3 d-block mb-2 text-success opacity-75"></i>
            No hay facturas sin retención para los filtros seleccionados.</td></tr>`;
        RRP_$('rrp-chk-all').checked = false;
        return;
    }
    tbody.innerHTML = RRP_filtradoLocal.map(RRP_filaHtml).join('');
    RRP_$('rrp-chk-all').checked = RRP_filtradoLocal.every(r => RRP_seleccionados.has(String(r.id)));
}

function RRP_badgeDias(dias) {
    dias = parseInt(dias) || 0;
    if (dias > 60) return `<span class="badge badge-dias-60 rounded-pill px-2">${dias} d</span>`;
    if (dias > 30) return `<span class="badge badge-dias-30 rounded-pill px-2">${dias} d</span>`;
    return `<span class="badge bg-light text-dark border rounded-pill px-2">${dias} d</span>`;
}

function RRP_badgeAviso(r) {
    const n = parseInt(r.n_avisos) || 0;
    if (!n) return `<span class="badge badge-aviso-no rounded-pill px-2">Sin aviso</span>`;
    return `<span class="badge badge-aviso-si rounded-pill px-2" title="Último: ${RRP_fmtFechaHora(r.ultimo_aviso)}">${n} aviso${n === 1 ? '' : 's'}</span>
            <div class="text-muted" style="font-size:.68rem;">${RRP_fmtFecha(r.ultimo_aviso)}</div>`;
}

function RRP_filaHtml(r) {
    const id    = String(r.id);
    const selec = RRP_seleccionados.has(id);
    const email = r.cliente_email || '';
    const correoHtml = email
        ? `<span class="text-truncate d-inline-block" style="max-width:200px;" title="${RRP_esc(email)}">${RRP_esc(email)}</span>`
        : `<span class="text-danger small"><i class="bi bi-exclamation-circle me-1"></i>Sin correo</span>`;

    return `
        <tr data-doc-id="${id}" data-doc-tipo="FACTURA" data-doc-numero="${RRP_esc(r.numero_factura)}" data-doc-sujeto="${RRP_esc(r.cliente_nombre)}" title="Clic para ver el detalle de la factura">
            <td class="text-center p-1">
                <input class="form-check-input rrp-chk" type="checkbox" value="${id}" ${selec ? 'checked' : ''}
                       onchange="RRP_toggleSeleccion('${id}', this.checked)">
            </td>
            <td class="ps-2 fw-semibold" data-col="factura" style="white-space:nowrap;">${RRP_esc(r.numero_factura)}</td>
            <td data-col="fecha" style="white-space:nowrap;">${RRP_fmtFecha(r.fecha_emision)}</td>
            <td class="text-center" data-col="dias">${RRP_badgeDias(r.dias)}</td>
            <td data-col="cliente" class="text-truncate" style="max-width:260px;" title="${RRP_esc(r.cliente_nombre)}">
                ${RRP_esc(r.cliente_nombre)}<div class="text-muted" style="font-size:.7rem;">${RRP_esc(r.cliente_ruc || '')}</div>
            </td>
            <td data-col="correo">${correoHtml}</td>
            <td class="text-end" data-col="subtotal" style="white-space:nowrap;">$${RRP_fmt(r.total_sin_impuestos)}</td>
            <td class="text-end" data-col="impuestos" style="white-space:nowrap;">$${RRP_fmt(r.impuestos)}</td>
            <td class="text-end fw-bold" data-col="total" style="white-space:nowrap;">$${RRP_fmt(r.importe_total)}</td>
            <td class="text-center" data-col="avisos" style="white-space:nowrap;">${RRP_badgeAviso(r)}</td>
            <td class="text-center pe-2" data-col="acciones">
                <div class="d-flex justify-content-center gap-1">
                    <button class="btn btn-outline-primary btn-sm py-0 px-2" style="font-size:.72rem;" title="Enviar aviso por correo"
                            onclick="RRP_abrirEmail(${r.id})">
                        <i class="bi bi-envelope"></i>
                    </button>
                    <button class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:.72rem;" title="Ver avisos enviados"
                            onclick="RRP_abrirAvisos(${r.id})" ${parseInt(r.n_avisos) ? '' : 'disabled'}>
                        <i class="bi bi-clock-history"></i>
                    </button>
                </div>
            </td>
        </tr>`;
}

/* ════════════════════════════════════════════════════
   VISTAS AGRUPADAS (por cliente / por mes)
════════════════════════════════════════════════════ */
function RRP_renderAgrupado(modo) {
    const tbody = RRP_$('rrp-tbody');
    if (!RRP_filtradoLocal.length) {
        tbody.innerHTML = `<tr><td colspan="11" class="text-center py-5 text-muted">
            <i class="bi bi-check2-circle fs-3 d-block mb-2 text-success opacity-75"></i>
            No hay facturas sin retención para los filtros seleccionados.</td></tr>`;
        return;
    }

    const meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    const mapa = new Map();
    for (const r of RRP_filtradoLocal) {
        let key, titulo, sub = '';
        if (modo === 'cliente') {
            key    = 'c:' + (r.id_cliente || r.cliente_ruc || r.cliente_nombre);
            titulo = r.cliente_nombre || 'Sin nombre';
            sub    = r.cliente_ruc || '';
        } else {
            const ym = String(r.fecha_emision || '').slice(0, 7); // YYYY-MM
            key    = 'm:' + ym;
            const [y, m] = ym.split('-');
            titulo = `${meses[parseInt(m, 10) - 1] || ym} ${y}`;
        }
        let g = mapa.get(key);
        if (!g) { g = { key, titulo, sub, filas: [], subtotal: 0, impuestos: 0, total: 0, avisadas: 0 }; mapa.set(key, g); }
        g.filas.push(r);
        g.subtotal  += parseFloat(r.total_sin_impuestos) || 0;
        g.impuestos += parseFloat(r.impuestos) || 0;
        g.total     += parseFloat(r.importe_total) || 0;
        if (parseInt(r.n_avisos)) g.avisadas++;
    }
    const grupos = [...mapa.values()];
    if (modo === 'cliente') grupos.sort((a, b) => b.total - a.total);
    else grupos.sort((a, b) => b.key.localeCompare(a.key));

    let html = '';
    for (const g of grupos) {
        const abierto = RRP_gruposAbiertos.has(g.key);
        const todasSel = g.filas.every(r => RRP_seleccionados.has(String(r.id)));
        html += `
            <tr class="rrp-grupo" onclick="RRP_toggleGrupo('${RRP_esc(g.key)}')">
                <td class="text-center p-1" onclick="event.stopPropagation()">
                    <input class="form-check-input" type="checkbox" ${todasSel ? 'checked' : ''} title="Seleccionar todas las facturas del grupo"
                           onchange="RRP_seleccionarGrupo('${RRP_esc(g.key)}', this.checked)">
                </td>
                <td colspan="4" class="fw-semibold">
                    <i class="bi ${abierto ? 'bi-chevron-down' : 'bi-chevron-right'} me-2 text-muted"></i>
                    ${RRP_esc(g.titulo)} ${g.sub ? `<span class="text-muted fw-normal ms-1" style="font-size:.75rem;">${RRP_esc(g.sub)}</span>` : ''}
                    <span class="badge bg-warning bg-opacity-25 text-dark border ms-2">${g.filas.length} factura${g.filas.length === 1 ? '' : 's'}</span>
                </td>
                <td></td>
                <td class="text-end fw-semibold">$${RRP_fmt(g.subtotal)}</td>
                <td class="text-end">$${RRP_fmt(g.impuestos)}</td>
                <td class="text-end fw-bold">$${RRP_fmt(g.total)}</td>
                <td class="text-center"><span class="badge ${g.avisadas ? 'badge-aviso-si' : 'badge-aviso-no'} rounded-pill px-2">${g.avisadas}/${g.filas.length} con aviso</span></td>
                <td class="text-center pe-2" onclick="event.stopPropagation()">
                    ${modo === 'cliente' ? `
                    <button class="btn btn-outline-primary btn-sm py-0 px-2" style="font-size:.72rem;" title="Aviso agrupado: un solo correo con todas las facturas de este cliente"
                            onclick="RRP_abrirEnvioAgrupado('${RRP_esc(g.key)}')">
                        <i class="bi bi-envelope-paper"></i>
                    </button>` : ''}
                </td>
            </tr>`;
        if (abierto) html += g.filas.map(RRP_filaHtml).join('');
    }
    tbody.innerHTML = html;
    RRP_$('rrp-chk-all').checked = RRP_filtradoLocal.every(r => RRP_seleccionados.has(String(r.id)));
    RRP_gruposCache = grupos;
}
let RRP_gruposCache = [];

function RRP_toggleGrupo(key) {
    if (RRP_gruposAbiertos.has(key)) RRP_gruposAbiertos.delete(key); else RRP_gruposAbiertos.add(key);
    RRP_render();
}

function RRP_seleccionarGrupo(key, sel) {
    const g = RRP_gruposCache.find(x => x.key === key);
    if (!g) return;
    g.filas.forEach(r => sel ? RRP_seleccionados.add(String(r.id)) : RRP_seleccionados.delete(String(r.id)));
    RRP_render();
}

/* ════════════════════════════════════════════════════
   SELECCIÓN
════════════════════════════════════════════════════ */
function RRP_toggleSeleccion(id, sel) {
    if (sel) RRP_seleccionados.add(String(id)); else RRP_seleccionados.delete(String(id));
    if (RRP_vista !== 'detalle') RRP_render();
    else RRP_$('rrp-chk-all').checked = RRP_filtradoLocal.every(r => RRP_seleccionados.has(String(r.id)));
}

function RRP_seleccionarTodos(sel) {
    RRP_filtradoLocal.forEach(r => sel ? RRP_seleccionados.add(String(r.id)) : RRP_seleccionados.delete(String(r.id)));
    RRP_render();
}

/* ════════════════════════════════════════════════════
   AVISO INDIVIDUAL (una factura)
════════════════════════════════════════════════════ */
function RRP_abrirEmail(idVenta) {
    const r = RRP_datos.find(x => String(x.id) === String(idVenta));
    if (!r) return;
    RRP_$('rrp-email-id-venta').value        = r.id;
    RRP_$('rrp-email-subtitulo').textContent = `Factura ${r.numero_factura} — ${r.cliente_nombre} — $${RRP_fmt(r.importe_total)}`;
    RRP_$('rrp-email-destino').value         = r.cliente_email || '';
    RRP_$('rrp-email-asunto').value          = '';
    RRP_$('rrp-email-mensaje').value         = '';
    new bootstrap.Modal(RRP_$('modalEmailRRP')).show();
}

async function RRP_enviarEmail() {
    const idVenta = RRP_$('rrp-email-id-venta').value;
    const email   = RRP_$('rrp-email-destino').value.trim();
    if (!email) { RRP_toast('Ingrese el correo destinatario.', 'warning'); return; }
    const partes = email.split(/[\s,;]+/).filter(Boolean);
    if (!partes.every(RRP_emailValido)) { RRP_toast('Hay un correo con formato inválido.', 'warning'); return; }

    const btn = RRP_$('rrp-btn-enviar-email');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Enviando…';

    const fd = new FormData();
    fd.append('id_venta', idVenta);
    fd.append('email',    partes.join(','));
    fd.append('asunto',   RRP_$('rrp-email-asunto').value.trim());
    fd.append('mensaje',  RRP_$('rrp-email-mensaje').value.trim());

    try {
        const r = await fetch(`${BASE_URL}/${RUTA_MODULO_RRP}/enviarEmailAjax`, {
            method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd
        });
        const d = await r.json();
        if (d.ok) {
            bootstrap.Modal.getInstance(RRP_$('modalEmailRRP'))?.hide();
            RRP_toast(d.mensaje || 'Aviso enviado.', 'success');
            RRP_cargar(); // refresca la columna de avisos
        } else {
            RRP_toast(d.mensaje || 'Error al enviar.', 'danger');
        }
    } catch (e) {
        RRP_toast('Error de conexión.', 'danger');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send me-1"></i>Enviar aviso';
    }
}

/* ════════════════════════════════════════════════════
   AVISO AGRUPADO (un correo por cliente)
════════════════════════════════════════════════════ */
/**
 * Abre el modal de revisión. Sin argumento usa las facturas seleccionadas;
 * con `keyGrupo` (vista Por cliente) toma todas las facturas de ese cliente.
 */
function RRP_abrirEnvioAgrupado(keyGrupo = null) {
    let filas;
    if (keyGrupo) {
        const g = RRP_gruposCache.find(x => x.key === keyGrupo);
        filas = g ? g.filas : [];
    } else {
        filas = RRP_datos.filter(r => RRP_seleccionados.has(String(r.id)));
    }
    if (!filas.length) { RRP_toast('Seleccione al menos una factura.', 'warning'); return; }

    const mapa = new Map();
    for (const r of filas) {
        const idCli = parseInt(r.id_cliente) || 0;
        const k = idCli || ('r:' + (r.cliente_ruc || r.cliente_nombre || '?'));
        let g = mapa.get(k);
        if (!g) {
            g = { idCliente: idCli, nombre: r.cliente_nombre || 'Sin nombre', ruc: r.cliente_ruc || '',
                  email: r.cliente_email || '', ids: [], total: 0 };
            mapa.set(k, g);
        }
        if (!g.email && r.cliente_email) g.email = r.cliente_email;
        g.ids.push(r.id);
        g.total += parseFloat(r.importe_total) || 0;
    }
    RRP_gruposAgrupado = [...mapa.values()].sort((a, b) => b.total - a.total);

    RRP_$('rrp-agrupado-tbody').innerHTML = RRP_gruposAgrupado.map((g, i) => `
        <tr>
            <td class="ps-2" style="font-size:.82rem;">
                <div class="fw-semibold text-truncate" style="max-width:240px;" title="${RRP_esc(g.nombre)}">${RRP_esc(g.nombre)}</div>
                <div class="text-muted" style="font-size:.7rem;">${RRP_esc(g.ruc)}</div>
            </td>
            <td class="text-center" style="font-size:.8rem;">${g.ids.length}</td>
            <td class="text-end fw-semibold" style="font-size:.8rem;">$${RRP_fmt(g.total)}</td>
            <td class="pe-2 py-1">
                <input type="text" class="form-control form-control-sm shadow-none rrp-agrupado-correo ${g.email ? '' : 'border-warning'}"
                       data-idx="${i}" value="${RRP_esc(g.email)}" placeholder="Sin correo — se omite"
                       title="Varios destinatarios separados por coma" oninput="this.classList.remove('is-invalid')">
            </td>
        </tr>`).join('');

    RRP_$('rrp-agrupado-resumen').innerHTML =
        `<strong>${filas.length}</strong> factura(s) de <strong>${RRP_gruposAgrupado.length}</strong> cliente(s). ` +
        `Se enviará <strong>un correo por cliente</strong> con la tabla de sus facturas sin retención.`;
    RRP_$('rrp-agrupado-mensaje').value = '';
    new bootstrap.Modal(RRP_$('modalEmailAgrupadoRRP')).show();
}

async function RRP_confirmarEnvioAgrupado() {
    const inputs = document.querySelectorAll('#rrp-agrupado-tbody .rrp-agrupado-correo');
    const correos = {};
    let ids = [];
    let conCorreo = 0, invalidos = 0, omitidos = 0;

    inputs.forEach(inp => {
        const g = RRP_gruposAgrupado[parseInt(inp.dataset.idx)];
        if (!g) return;
        const val = inp.value.trim();
        if (!val) { omitidos++; return; }
        const partes = val.split(/[\s,;]+/).filter(Boolean);
        if (!partes.every(RRP_emailValido)) { inp.classList.add('is-invalid'); invalidos++; return; }
        conCorreo++;
        if (g.idCliente > 0) correos[g.idCliente] = partes.join(',');
        ids = ids.concat(g.ids);
    });

    if (invalidos)  { RRP_toast(`Hay ${invalidos} correo(s) inválido(s). Corríjalos o déjelos vacíos para omitir al cliente.`, 'warning'); return; }
    if (!conCorreo) { RRP_toast('Ningún cliente tiene correo. Complete al menos uno para enviar.', 'warning'); return; }

    const btn = RRP_$('rrp-btn-enviar-agrupado');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Enviando…';

    try {
        const fd = new FormData();
        fd.append('ids',     JSON.stringify(ids));
        fd.append('correos', JSON.stringify(correos));
        fd.append('mensaje', RRP_$('rrp-agrupado-mensaje').value.trim());

        const r = await fetch(`${BASE_URL}/${RUTA_MODULO_RRP}/enviarEmailAgrupadoAjax`, {
            method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd
        });
        const d = await r.json();
        if (d.ok) {
            bootstrap.Modal.getInstance(RRP_$('modalEmailAgrupadoRRP'))?.hide();
            let mensaje = d.mensaje || 'Correos enviados.';
            if (omitidos) mensaje += ` ${omitidos} cliente(s) omitido(s) por no tener correo.`;
            const hayAvisos = omitidos + (d.sin_email || 0) + (d.con_error || 0) + (d.no_disponibles || 0) > 0;
            RRP_toast(mensaje, hayAvisos ? 'warning' : 'success');
            RRP_seleccionados.clear();
            RRP_cargar();
        } else {
            RRP_toast(d.mensaje || 'Error al enviar.', 'danger');
        }
    } catch (e) {
        RRP_toast('Error de conexión.', 'danger');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send me-1"></i>Enviar';
    }
}

/* ════════════════════════════════════════════════════
   HISTORIAL DE AVISOS DE UNA FACTURA
════════════════════════════════════════════════════ */
async function RRP_abrirAvisos(idVenta) {
    const r = RRP_datos.find(x => String(x.id) === String(idVenta));
    RRP_$('rrp-avisos-subtitulo').textContent = r ? `Factura ${r.numero_factura} — ${r.cliente_nombre}` : '';
    const tbody = RRP_$('rrp-avisos-tbody');
    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Cargando…</td></tr>';
    new bootstrap.Modal(RRP_$('modalAvisosRRP')).show();

    try {
        const res = await fetch(`${BASE_URL}/${RUTA_MODULO_RRP}/avisosAjax?id_venta=${encodeURIComponent(idVenta)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const d = await res.json();
        if (!d.ok) { tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">${RRP_esc(d.mensaje || 'Error')}</td></tr>`; return; }
        if (!d.data.length) { tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Esta factura no tiene avisos enviados.</td></tr>'; return; }
        tbody.innerHTML = d.data.map(a => `
            <tr>
                <td style="white-space:nowrap;">${RRP_fmtFechaHora(a.fecha_envio)}</td>
                <td>${a.tipo_envio === 'AGRUPADO'
                    ? '<span class="badge bg-primary bg-opacity-10 text-primary border">Agrupado</span>'
                    : '<span class="badge bg-secondary bg-opacity-10 text-secondary border">Individual</span>'}</td>
                <td style="font-size:.8rem;word-break:break-all;">${RRP_esc(a.correo_destino)}</td>
                <td style="font-size:.8rem;">${RRP_esc(a.asunto || '')}</td>
                <td style="font-size:.8rem;">${RRP_esc(a.usuario || '')}</td>
            </tr>`).join('');
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error de conexión.</td></tr>';
    }
}

/* ════════════════════════════════════════════════════
   BUSCADOR DE CLIENTE (autocomplete con chip)
════════════════════════════════════════════════════ */
function RRP_initBuscadorClientes() {
    const input = RRP_$('rrp-search-cliente');
    const drop  = RRP_$('rrp-dropdown-clientes');
    let timer = null;

    input.addEventListener('input', () => {
        const q = input.value.trim();
        clearTimeout(timer);
        if (q.length < 2) { drop.classList.add('d-none'); return; }
        timer = setTimeout(async () => {
            try {
                const r = await fetch(`${BASE_URL}/${RUTA_MODULO_RRP}/buscarClientesAjax?q=${encodeURIComponent(q)}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const d = await r.json();
                drop.innerHTML = '';
                if (!d.data || !d.data.length) {
                    drop.innerHTML = '<div class="list-group-item small text-muted">Sin resultados.</div>';
                } else {
                    d.data.forEach(c => {
                        const b = document.createElement('button');
                        b.type = 'button'; b.className = 'list-group-item list-group-item-action small py-1';
                        b.innerHTML = `<strong>${RRP_esc(c.nombre)}</strong> <span class="text-muted">${RRP_esc(c.ident || '')}</span>`;
                        b.onclick = () => { RRP_setCliente(c.id, c.nombre, c.ident); drop.classList.add('d-none'); };
                        drop.appendChild(b);
                    });
                }
                drop.classList.remove('d-none');
            } catch (e) { drop.classList.add('d-none'); }
        }, 300);
    });

    // Backspace/Delete con selección activa: limpia toda la selección de una vez
    input.addEventListener('keydown', e => {
        if ((e.key === 'Backspace' || e.key === 'Delete') && RRP_$('rrp-id-cliente').value) {
            e.preventDefault();
            RRP_quitarCliente();
        }
    });
    document.addEventListener('click', e => {
        if (!e.target.closest('#rrp-search-cliente') && !e.target.closest('#rrp-dropdown-clientes')) drop.classList.add('d-none');
    });
}

function RRP_setCliente(id, nombre, ident) {
    RRP_$('rrp-id-cliente').value = id;
    RRP_$('rrp-search-cliente').value = `${ident ? ident + ' - ' : ''}${nombre}`;
    RRP_$('rrp-chips-cliente').innerHTML = '';
    RRP_cargar();
}

function RRP_quitarCliente() {
    RRP_$('rrp-id-cliente').value = '';
    RRP_$('rrp-search-cliente').value = '';
    RRP_$('rrp-chips-cliente').innerHTML = '';
    RRP_$('rrp-dropdown-clientes').classList.add('d-none');
    RRP_cargar();
}

function RRP_limpiarFiltros() {
    RRP_$('rrp-anio').value = String(RRP_ANIO_ACTUAL);
    RRP_$('rrp-mes').value = '';
    RRP_$('rrp-aviso').value = 'TODOS';
    RRP_$('rrp-id-cliente').value = '';
    RRP_$('rrp-search-cliente').value = '';
    RRP_$('rrp-chips-cliente').innerHTML = '';
    RRP_$('rrp-buscador').value = '';
    if (typeof aplicarFavoritosModal === 'function') aplicarFavoritosModal();
    RRP_actualizarFechas();
    RRP_cargar();
}

/* ════════════════════════════════════════════════════
   PANEL LATERAL: detalle de la factura al hacer clic en la fila
════════════════════════════════════════════════════ */
document.addEventListener('click', function (e) {
    const tr = e.target.closest('#tabla-rrp tr[data-doc-id]');
    if (!tr) return;
    if (e.target.closest('button, a, input, select, label')) return;
    if (typeof window.CMG_abrirPreviewDoc !== 'function') return;
    window.CMG_abrirPreviewDoc(tr.dataset.docId, tr.dataset.docTipo, {
        numero:      tr.dataset.docNumero || '',
        sujetoLabel: 'Cliente',
        sujeto:      tr.dataset.docSujeto || ''
    });
});

/* ════════════════════════════════════════════════════
   UTILIDADES
════════════════════════════════════════════════════ */
function RRP_esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}
function RRP_fmt(v) {
    return (parseFloat(v) || 0).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function RRP_fmtFecha(s) {
    if (!s) return '—';
    const [y, m, d] = String(s).slice(0, 10).split('-');
    return (y && m && d) ? `${d}-${m}-${y}` : s;
}
function RRP_fmtFechaHora(s) {
    if (!s) return '—';
    const str = String(s).replace('T', ' ');
    const [f, h] = str.split(' ');
    return `${RRP_fmtFecha(f)} ${(h || '').slice(0, 8)}`.trim();
}
function RRP_emailValido(v) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
}
function RRP_toast(msg, type = 'info') {
    const map = {
        success : { icon: 'success', title: 'Éxito',       timer: 2500, showConfirmButton: false },
        danger  : { icon: 'error',   title: 'Error',        timer: undefined, showConfirmButton: true },
        warning : { icon: 'warning', title: 'Atención',     timer: undefined, showConfirmButton: true },
        info    : { icon: 'info',    title: 'Información',  timer: 3000, showConfirmButton: false },
    };
    const cfg = map[type] || map.info;
    const opts = { icon: cfg.icon, title: cfg.title, text: msg };
    if (cfg.timer) opts.timer = cfg.timer;
    if (!cfg.showConfirmButton) opts.showConfirmButton = false;
    if (typeof Swal !== 'undefined') Swal.fire(opts); else alert(msg);
}
