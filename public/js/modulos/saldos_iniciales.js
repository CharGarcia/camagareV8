/**
 * saldos_iniciales.js
 * Módulo de Saldos Iniciales — CXC, CXP y Bancos
 */
'use strict';

/* ════════════════════════════════
   ESTADO GLOBAL
════════════════════════════════ */
let SI_datosCxc = [];
let SI_datosCxp = [];
let SI_catalogos = { puntos: [], formas: [], conceptos_ing: [], conceptos_egr: [] };
let SI_catalogosCargados = false;

/* ════════════════════════════════
   INIT
════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
    // CXC es el tab activo por defecto — cargar de inmediato
    SI_cargarCxc();
    SI_cargarCatalogos();
    // Marcar el flag de la vista para que SI_onTabCxc no recargue al hacer click
    if (typeof SI_tabCxcCargado !== 'undefined') SI_tabCxcCargado = true;
});

/* ════════════════════════════════
   CATÁLOGOS
════════════════════════════════ */
async function SI_cargarCatalogos() {
    if (SI_catalogosCargados) return;
    try {
        const r = await fetch(`${BASE_URL}/${RUTA_SI}/getCatalogosAjax`, { headers: {'X-Requested-With':'XMLHttpRequest'} });
        const d = await r.json();
        if (d.ok) {
            SI_catalogos = d;
            SI_catalogosCargados = true;
        }
    } catch(e) { console.error('[SI] catálogos', e); }
}

/* ════════════════════════════════
   CXC — CARGAR LISTADO
════════════════════════════════ */
async function SI_cargarCxc() {
    const tbody = document.getElementById('si-cxc-tbody');
    tbody.innerHTML = `<tr><td colspan="9" class="text-center py-4"><div class="spinner-border spinner-border-sm text-success me-2"></div>Cargando…</td></tr>`;
    const estado = document.getElementById('si-cxc-estado')?.value || 'TODOS';
    try {
        const r = await fetch(`${BASE_URL}/${RUTA_SI}/getCxcAjax?estado=${estado}`, { headers: {'X-Requested-With':'XMLHttpRequest'} });
        const d = await r.json();
        if (!d.ok) { tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4">${siEsc(d.error)}</td></tr>`; return; }
        SI_datosCxc = d.filas || [];
        SI_renderCxc(SI_datosCxc);
    } catch(e) { tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4">Error de conexión</td></tr>`; }
}

function SI_renderCxc(filas) {
    const tbody = document.getElementById('si-cxc-tbody');
    document.getElementById('si-cxc-count').textContent = filas.length + ' registros';
    if (!filas.length) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-3 d-block mb-2 opacity-25"></i>Sin registros</td></tr>`;
        return;
    }
    tbody.innerHTML = filas.map(f => {
        const dias = parseInt(f.dias_vencido) || 0;
        const color = dias > 0 ? 'color:#dc3545;' : '';
        const fEmis = siFmtFecha(f.fecha_emision);
        const fVenc = f.fecha_vencimiento ? siFmtFecha(f.fecha_vencimiento) : '—';
        const estadoBadge = siEstadoBadge(f.estado, dias);
        const puedeEditar = f.monto_cobrado <= 0;

        return `<tr style="${color}">
            <td class="ps-2 fw-semibold font-monospace small">${siEsc(f.nro_documento)}</td>
            <td class="text-truncate" title="${siEsc(f.nombre_cliente)}">
                ${siEsc(f.nombre_cliente)}
                ${f.ruc_cliente ? `<small class="text-muted d-block">${siEsc(f.ruc_cliente)}</small>` : ''}
            </td>
            <td class="text-center small">${fEmis}</td>
            <td class="text-center small">${fVenc}${dias > 0 ? `<br><small style="color:#dc3545;">${dias}d</small>` : ''}</td>
            <td class="text-end small">$${f.saldo_inicial}</td>
            <td class="text-end small text-success">$${f.monto_cobrado}</td>
            <td class="text-end fw-bold pe-2">$${f.saldo_pendiente}</td>
            <td class="text-center">${estadoBadge}</td>
            <td class="text-center">
                <div class="btn-group btn-group-sm">
                    ${parseFloat(f.saldo_pendiente) > 0 && SI_PERM_CREAR ? `<button class="btn btn-outline-success btn-sm py-0 px-1" onclick='SI_abrirMovimiento(${JSON.stringify(f)},"CXC")' title="Registrar cobro"><i class="bi bi-cash-coin"></i></button>` : ''}
                    <button class="btn btn-outline-secondary btn-sm py-0 px-1" onclick="SI_verHistorialCxc(${f.id})" title="Historial"><i class="bi bi-clock-history"></i></button>
                    ${puedeEditar && SI_PERM_MODIFICAR ? `<button class="btn btn-outline-warning btn-sm py-0 px-1" onclick='SI_abrirModalCxc(${JSON.stringify(f)})' title="Editar"><i class="bi bi-pencil"></i></button>` : ''}
                </div>
            </td>
        </tr>`;
    }).join('');
}

function SI_filtrarCxc(q) {
    const t = q.toLowerCase();
    SI_renderCxc(t ? SI_datosCxc.filter(f =>
        (f.nro_documento||'').toLowerCase().includes(t) ||
        (f.nombre_cliente||'').toLowerCase().includes(t) ||
        (f.ruc_cliente||'').toLowerCase().includes(t)
    ) : SI_datosCxc);
}

/* ════════════════════════════════
   CXC — MODAL GUARDAR
════════════════════════════════ */
function SI_abrirModalCxc(row = null) {
    document.getElementById('si-cxc-id').value = row?.id || '';
    document.getElementById('si-cxc-nro').value = row?.nro_documento || '';
    document.getElementById('si-cxc-femision').value = row?.fecha_emision || '';
    document.getElementById('si-cxc-fvenc').value = row?.fecha_vencimiento || '';
    document.getElementById('si-cxc-ruc').value = row?.ruc_cliente || '';
    document.getElementById('si-cxc-nombre').value = row?.nombre_cliente || '';
    document.getElementById('si-cxc-saldo').value = row?.saldo_inicial || '';
    document.getElementById('si-cxc-obs').value = row?.observaciones || '';
    const btnEl = document.getElementById('btn-eliminar-cxc');
    if (row?.id && SI_PERM_ELIMINAR && parseFloat(row.monto_cobrado||0) <= 0) {
        btnEl.style.removeProperty('display');
        btnEl.setAttribute('data-id', row.id);
    } else {
        btnEl.style.setProperty('display', 'none', 'important');
    }
    new bootstrap.Modal(document.getElementById('modalSICxc')).show();
}

async function SI_guardarCxc() {
    const id = document.getElementById('si-cxc-id').value;
    const form = new FormData();
    if (id) form.append('id', id);
    form.append('nro_documento',     document.getElementById('si-cxc-nro').value);
    form.append('fecha_emision',     document.getElementById('si-cxc-femision').value);
    form.append('fecha_vencimiento', document.getElementById('si-cxc-fvenc').value);
    form.append('ruc_cliente',       document.getElementById('si-cxc-ruc').value);
    form.append('nombre_cliente',    document.getElementById('si-cxc-nombre').value);
    form.append('saldo_inicial',     document.getElementById('si-cxc-saldo').value);
    form.append('observaciones',     document.getElementById('si-cxc-obs').value);

    try {
        const r = await fetch(`${BASE_URL}/${RUTA_SI}/guardarCxcAjax`, { method:'POST', body: form, headers: {'X-Requested-With':'XMLHttpRequest'} });
        const d = await r.json();
        if (d.ok) {
            bootstrap.Modal.getInstance(document.getElementById('modalSICxc'))?.hide();
            SI_toast(d.mensaje, 'success');
            SI_cargarCxc();
        } else {
            SI_toast(d.error, 'danger');
        }
    } catch(e) { SI_toast('Error de conexión', 'danger'); }
}

async function SI_eliminarCxc() {
    const id = document.getElementById('btn-eliminar-cxc').getAttribute('data-id');
    if (!id || !confirm('¿Eliminar este registro de saldo inicial?')) return;
    const fd = new FormData(); fd.append('id', id);
    try {
        const r = await fetch(`${BASE_URL}/${RUTA_SI}/eliminarCxcAjax`, { method:'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} });
        const d = await r.json();
        if (d.ok) {
            bootstrap.Modal.getInstance(document.getElementById('modalSICxc'))?.hide();
            SI_toast(d.mensaje, 'success');
            SI_cargarCxc();
        } else { SI_toast(d.error, 'danger'); }
    } catch(e) { SI_toast('Error de conexión', 'danger'); }
}

/* ════════════════════════════════
   CXC — IMPORTAR EXCEL
════════════════════════════════ */
async function SI_importarCxc(input) {
    if (!input.files[0]) return;
    const fd = new FormData(); fd.append('archivo', input.files[0]);
    SI_toast('Importando archivo…', 'info');
    try {
        const r = await fetch(`${BASE_URL}/${RUTA_SI}/importarCxcAjax`, { method:'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} });
        const d = await r.json();
        if (d.ok) {
            let msg = d.mensaje;
            if (d.errores?.length) msg += '\n' + d.errores.join('\n');
            alert(msg);
            SI_cargarCxc();
        } else { SI_toast(d.error, 'danger'); }
    } catch(e) { SI_toast('Error al importar', 'danger'); }
    input.value = '';
}

function SI_descargarTemplateCxc() {
    window.location.href = `${BASE_URL}/${RUTA_SI}/descargarTemplateCxc`;
}

/* ════════════════════════════════
   CXP — CARGAR LISTADO
════════════════════════════════ */
async function SI_cargarCxp() {
    const tbody = document.getElementById('si-cxp-tbody');
    tbody.innerHTML = `<tr><td colspan="10" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Cargando…</td></tr>`;
    const estado = document.getElementById('si-cxp-estado')?.value || 'TODOS';
    const tipo   = document.getElementById('si-cxp-tipo')?.value   || '';
    try {
        const r = await fetch(`${BASE_URL}/${RUTA_SI}/getCxpAjax?estado=${estado}&tipo_documento=${tipo}`, { headers: {'X-Requested-With':'XMLHttpRequest'} });
        const d = await r.json();
        if (!d.ok) { tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger py-4">${siEsc(d.error)}</td></tr>`; return; }
        SI_datosCxp = d.filas || [];
        SI_renderCxp(SI_datosCxp);
    } catch(e) { tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger py-4">Error de conexión</td></tr>`; }
}

const SI_TIPO_LABELS = { FACTURA_COMPRA:'Factura', LIQUIDACION:'Liquidación', NOTA_CREDITO:'NC', NOTA_DEBITO:'ND' };

function SI_renderCxp(filas) {
    const tbody = document.getElementById('si-cxp-tbody');
    document.getElementById('si-cxp-count').textContent = filas.length + ' registros';
    if (!filas.length) {
        tbody.innerHTML = `<tr><td colspan="10" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-3 d-block mb-2 opacity-25"></i>Sin registros</td></tr>`;
        return;
    }
    tbody.innerHTML = filas.map(f => {
        const dias = parseInt(f.dias_vencido) || 0;
        const color = dias > 0 ? 'color:#dc3545;' : '';
        const fEmis = siFmtFecha(f.fecha_emision);
        const fVenc = f.fecha_vencimiento ? siFmtFecha(f.fecha_vencimiento) : '—';
        const tipoLbl = SI_TIPO_LABELS[f.tipo_documento] || f.tipo_documento;
        const estadoBadge = siEstadoBadge(f.estado, dias);
        const puedeEditar = f.monto_pagado <= 0;

        return `<tr style="${color}">
            <td class="ps-2"><span class="badge badge-apertura">${siEsc(tipoLbl)}</span></td>
            <td class="font-monospace small fw-semibold">${siEsc(f.nro_documento)}</td>
            <td class="text-truncate" title="${siEsc(f.nombre_proveedor)}">
                ${siEsc(f.nombre_proveedor)}
                ${f.ruc_proveedor ? `<small class="text-muted d-block">${siEsc(f.ruc_proveedor)}</small>` : ''}
            </td>
            <td class="text-center small">${fEmis}</td>
            <td class="text-center small">${fVenc}${dias > 0 ? `<br><small style="color:#dc3545;">${dias}d</small>` : ''}</td>
            <td class="text-end small">$${f.saldo_inicial}</td>
            <td class="text-end small text-success">$${f.monto_pagado}</td>
            <td class="text-end fw-bold pe-2">$${f.saldo_pendiente}</td>
            <td class="text-center">${estadoBadge}</td>
            <td class="text-center">
                <div class="btn-group btn-group-sm">
                    ${parseFloat(f.saldo_pendiente) > 0 && SI_PERM_CREAR ? `<button class="btn btn-outline-primary btn-sm py-0 px-1" onclick='SI_abrirMovimiento(${JSON.stringify(f)},"CXP")' title="Registrar pago"><i class="bi bi-cash-stack"></i></button>` : ''}
                    <button class="btn btn-outline-secondary btn-sm py-0 px-1" onclick="SI_verHistorialCxp(${f.id})" title="Historial"><i class="bi bi-clock-history"></i></button>
                    ${puedeEditar && SI_PERM_MODIFICAR ? `<button class="btn btn-outline-warning btn-sm py-0 px-1" onclick='SI_abrirModalCxp(${JSON.stringify(f)})' title="Editar"><i class="bi bi-pencil"></i></button>` : ''}
                </div>
            </td>
        </tr>`;
    }).join('');
}

function SI_filtrarCxp(q) {
    const t = q.toLowerCase();
    SI_renderCxp(t ? SI_datosCxp.filter(f =>
        (f.nro_documento||'').toLowerCase().includes(t) ||
        (f.nombre_proveedor||'').toLowerCase().includes(t) ||
        (f.ruc_proveedor||'').toLowerCase().includes(t)
    ) : SI_datosCxp);
}

/* ════════════════════════════════
   CXP — MODAL GUARDAR
════════════════════════════════ */
function SI_abrirModalCxp(row = null) {
    document.getElementById('si-cxp-id').value = row?.id || '';
    document.getElementById('si-cxp-tipo').value = row?.tipo_documento || 'FACTURA_COMPRA';
    document.getElementById('si-cxp-nro').value = row?.nro_documento || '';
    document.getElementById('si-cxp-femision').value = row?.fecha_emision || '';
    document.getElementById('si-cxp-fvenc').value = row?.fecha_vencimiento || '';
    document.getElementById('si-cxp-ruc').value = row?.ruc_proveedor || '';
    document.getElementById('si-cxp-nombre').value = row?.nombre_proveedor || '';
    document.getElementById('si-cxp-saldo').value = row?.saldo_inicial || '';
    document.getElementById('si-cxp-obs').value = row?.observaciones || '';
    const btnEl = document.getElementById('btn-eliminar-cxp');
    if (row?.id && SI_PERM_ELIMINAR && parseFloat(row.monto_pagado||0) <= 0) {
        btnEl.style.removeProperty('display');
        btnEl.setAttribute('data-id', row.id);
    } else {
        btnEl.style.setProperty('display', 'none', 'important');
    }
    new bootstrap.Modal(document.getElementById('modalSICxp')).show();
}

async function SI_guardarCxp() {
    const id = document.getElementById('si-cxp-id').value;
    const form = new FormData();
    if (id) form.append('id', id);
    form.append('tipo_documento',    document.getElementById('si-cxp-tipo').value);
    form.append('nro_documento',     document.getElementById('si-cxp-nro').value);
    form.append('fecha_emision',     document.getElementById('si-cxp-femision').value);
    form.append('fecha_vencimiento', document.getElementById('si-cxp-fvenc').value);
    form.append('ruc_proveedor',     document.getElementById('si-cxp-ruc').value);
    form.append('nombre_proveedor',  document.getElementById('si-cxp-nombre').value);
    form.append('saldo_inicial',     document.getElementById('si-cxp-saldo').value);
    form.append('observaciones',     document.getElementById('si-cxp-obs').value);

    try {
        const r = await fetch(`${BASE_URL}/${RUTA_SI}/guardarCxpAjax`, { method:'POST', body: form, headers: {'X-Requested-With':'XMLHttpRequest'} });
        const d = await r.json();
        if (d.ok) {
            bootstrap.Modal.getInstance(document.getElementById('modalSICxp'))?.hide();
            SI_toast(d.mensaje, 'success');
            SI_cargarCxp();
        } else { SI_toast(d.error, 'danger'); }
    } catch(e) { SI_toast('Error de conexión', 'danger'); }
}

async function SI_eliminarCxp() {
    const id = document.getElementById('btn-eliminar-cxp').getAttribute('data-id');
    if (!id || !confirm('¿Eliminar este registro de saldo inicial?')) return;
    const fd = new FormData(); fd.append('id', id);
    try {
        const r = await fetch(`${BASE_URL}/${RUTA_SI}/eliminarCxpAjax`, { method:'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} });
        const d = await r.json();
        if (d.ok) {
            bootstrap.Modal.getInstance(document.getElementById('modalSICxp'))?.hide();
            SI_toast(d.mensaje, 'success');
            SI_cargarCxp();
        } else { SI_toast(d.error, 'danger'); }
    } catch(e) { SI_toast('Error de conexión', 'danger'); }
}

/* ════════════════════════════════
   CXP — IMPORTAR EXCEL
════════════════════════════════ */
async function SI_importarCxp(input) {
    if (!input.files[0]) return;
    const fd = new FormData(); fd.append('archivo', input.files[0]);
    SI_toast('Importando archivo…', 'info');
    try {
        const r = await fetch(`${BASE_URL}/${RUTA_SI}/importarCxpAjax`, { method:'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} });
        const d = await r.json();
        if (d.ok) {
            let msg = d.mensaje;
            if (d.errores?.length) msg += '\n' + d.errores.join('\n');
            alert(msg);
            SI_cargarCxp();
        } else { SI_toast(d.error, 'danger'); }
    } catch(e) { SI_toast('Error al importar', 'danger'); }
    input.value = '';
}

function SI_descargarTemplateCxp() {
    window.location.href = `${BASE_URL}/${RUTA_SI}/descargarTemplateCxp`;
}

/* ════════════════════════════════
   BANCOS — CARGAR Y GUARDAR
════════════════════════════════ */
async function SI_cargarBancos() {
    const container = document.getElementById('bancos-container');
    container.innerHTML = `<div class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Cargando…</div>`;
    try {
        const r = await fetch(`${BASE_URL}/${RUTA_SI}/getBancosAjax`, { headers: {'X-Requested-With':'XMLHttpRequest'} });
        const d = await r.json();
        if (!d.ok) { container.innerHTML = `<div class="text-danger p-3">${siEsc(d.error)}</div>`; return; }
        SI_renderBancos(d.cuentas || []);
    } catch(e) { container.innerHTML = `<div class="text-danger p-3">Error de conexión</div>`; }
}

function SI_renderBancos(cuentas) {
    const container = document.getElementById('bancos-container');
    if (!cuentas.length) {
        container.innerHTML = `<div class="text-center py-5 text-muted"><i class="bi bi-bank fs-3 d-block mb-2 opacity-25"></i>No hay cuentas bancarias o tarjetas registradas.</div>`;
        return;
    }

    const grupos = { BANCO: [], TARJETA: [] };
    cuentas.forEach(c => { (grupos[c.tipo] || grupos.BANCO).push(c); });

    let html = '';
    const renderGrupo = (titulo, icono, color, items) => {
        if (!items.length) return '';
        return `<div class="mb-4">
            <h6 class="fw-bold mb-2 text-${color}"><i class="bi ${icono} me-2"></i>${titulo}</h6>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle border rounded-3">
                    <thead class="table-light"><tr>
                        <th>Cuenta / Nombre</th>
                        <th style="width:150px;" class="text-center">Fecha Saldo</th>
                        <th style="width:150px;" class="text-end">Saldo Inicial ($)</th>
                        <th style="width:200px;">Observaciones</th>
                        ${SI_PERM_ELIMINAR ? '<th style="width:60px;"></th>' : ''}
                    </tr></thead>
                    <tbody>
                        ${items.map(c => `<tr>
                            <td>
                                <span class="fw-semibold">${siEsc(c.nombre)}</span>
                                ${c.numero_cuenta ? `<small class="text-muted d-block">${siEsc(c.numero_cuenta)}${c.tipo_cuenta ? ' · ' + siEsc(c.tipo_cuenta) : ''}</small>` : ''}
                            </td>
                            <td><input type="date" class="form-control form-control-sm shadow-none border si-banco-fecha"
                                data-id="${c.id}" data-forma="${c.id}" value="${c.fecha_saldo||''}"></td>
                            <td><div class="input-group input-group-sm">
                                <span class="input-group-text text-${color}">$</span>
                                <input type="number" class="form-control form-control-sm shadow-none text-end fw-semibold si-banco-saldo"
                                    data-forma="${c.id}" step="0.01" placeholder="0.00" value="${c.saldo_inicial||''}">
                            </div></td>
                            <td><input type="text" class="form-control form-control-sm shadow-none si-banco-obs"
                                data-forma="${c.id}" maxlength="255" placeholder="Opcional…" value="${siEsc(c.observaciones||'')}"></td>
                            ${SI_PERM_ELIMINAR ? `<td class="text-center">${c.id_saldo_inicial ? `<button class="btn btn-outline-danger btn-sm py-0 px-1" onclick="SI_eliminarBanco(${c.id_saldo_inicial})" title="Quitar saldo"><i class="bi bi-x-lg"></i></button>` : ''}</td>` : ''}
                        </tr>`).join('')}
                    </tbody>
                </table>
            </div>
        </div>`;
    };

    html += renderGrupo('Cuentas Bancarias', 'bi-bank', 'warning', grupos.BANCO);
    html += renderGrupo('Tarjetas', 'bi-credit-card-2-front', 'info', grupos.TARJETA);
    container.innerHTML = html;
}

async function SI_guardarBancos() {
    const cuentas = [];
    document.querySelectorAll('.si-banco-fecha').forEach(el => {
        const forma = el.getAttribute('data-forma');
        const saldo = document.querySelector(`.si-banco-saldo[data-forma="${forma}"]`)?.value;
        const fecha = el.value;
        const obs   = document.querySelector(`.si-banco-obs[data-forma="${forma}"]`)?.value || '';
        if (fecha && saldo !== '' && saldo !== undefined) {
            cuentas.push({ id_forma_pago: forma, fecha_saldo: fecha, saldo_inicial: saldo, observaciones: obs });
        }
    });

    if (!cuentas.length) { SI_toast('No hay saldos para guardar.', 'warning'); return; }

    const fd = new FormData();
    fd.append('cuentas', JSON.stringify(cuentas));
    try {
        const r = await fetch(`${BASE_URL}/${RUTA_SI}/guardarBancosAjax`, { method:'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} });
        const d = await r.json();
        if (d.ok) { SI_toast(d.mensaje, 'success'); SI_cargarBancos(); }
        else { SI_toast(d.error, 'danger'); }
    } catch(e) { SI_toast('Error de conexión', 'danger'); }
}

async function SI_eliminarBanco(id) {
    if (!confirm('¿Eliminar el saldo inicial de esta cuenta?')) return;
    const fd = new FormData(); fd.append('id', id);
    try {
        const r = await fetch(`${BASE_URL}/${RUTA_SI}/eliminarBancoAjax`, { method:'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} });
        const d = await r.json();
        if (d.ok) { SI_toast(d.mensaje, 'success'); SI_cargarBancos(); }
        else { SI_toast(d.error, 'danger'); }
    } catch(e) { SI_toast('Error de conexión', 'danger'); }
}

/* ════════════════════════════════
   MODAL MOVIMIENTO (cobro/pago)
════════════════════════════════ */
function SI_abrirMovimiento(row, tipo) {
    document.getElementById('si-mov-id').value   = row.id;
    document.getElementById('si-mov-tipo').value = tipo;
    document.getElementById('si-mov-doc').textContent      = row.nro_documento;
    document.getElementById('si-mov-tercero').textContent  = tipo === 'CXC' ? row.nombre_cliente : row.nombre_proveedor;
    document.getElementById('si-mov-saldo-inicial').textContent = row.saldo_inicial;
    document.getElementById('si-mov-saldo-pend').textContent    = '$' + row.saldo_pendiente;

    const header = document.getElementById('si-mov-header');
    const titulo = document.getElementById('si-mov-titulo');
    const btnGuardar = document.getElementById('btn-guardar-movimiento');

    if (tipo === 'CXC') {
        header.className = 'modal-header bg-success text-white py-2 px-3';
        titulo.innerHTML = '<i class="bi bi-cash-coin me-2"></i>Registrar Cobro — Saldo Inicial';
        document.getElementById('si-mov-saldo-pend').style.color = '#dc3545';
        btnGuardar.className = 'btn btn-success btn-sm px-4 fw-semibold';
        btnGuardar.innerHTML = '<i class="bi bi-check-lg me-1"></i>Registrar Cobro';
    } else {
        header.className = 'modal-header bg-primary text-white py-2 px-3';
        titulo.innerHTML = '<i class="bi bi-cash-stack me-2"></i>Registrar Pago — Saldo Inicial';
        document.getElementById('si-mov-saldo-pend').style.color = '#dc3545';
        btnGuardar.className = 'btn btn-primary btn-sm px-4 fw-semibold';
        btnGuardar.innerHTML = '<i class="bi bi-check-lg me-1"></i>Registrar Pago';
    }

    document.getElementById('si-mov-monto').value = '';
    document.getElementById('si-mov-obs').value   = '';
    document.getElementById('si-mov-div-banco').classList.add('d-none');

    SI_cargarCatalogos().then(() => {
        const selectPunto = document.getElementById('si-mov-punto');
        selectPunto.innerHTML = '<option value="">— Seleccione —</option>' +
            (SI_catalogos.puntos||[]).map(p =>
                `<option value="${p.id_punto}">${p.cod_establecimiento}-${p.codigo_punto}</option>`
            ).join('');

        const selectForma = document.getElementById('si-mov-forma');
        const formasFiltradas = (SI_catalogos.formas||[]).filter(f =>
            tipo === 'CXC'
                ? ['INGRESO','AMBAS','COBRO'].includes(f.aplica_en) || !f.aplica_en
                : ['EGRESO','AMBAS','PAGO'].includes(f.aplica_en)   || !f.aplica_en
        );
        selectForma.innerHTML = '<option value="">— Seleccione —</option>' +
            formasFiltradas.map(f => `<option value="${f.id}" data-tipo="${f.tipo}">${siEsc(f.nombre)}</option>`).join('');

        const conceptos = tipo === 'CXC' ? SI_catalogos.conceptos_ing : SI_catalogos.conceptos_egr;
        document.getElementById('si-mov-concepto').innerHTML = '<option value="">— Sin concepto —</option>' +
            (conceptos||[]).map(c => `<option value="${c.id}">${siEsc(c.nombre)}</option>`).join('');

        document.getElementById('si-mov-secuencial').value = '';
    });

    new bootstrap.Modal(document.getElementById('modalSIMovimiento')).show();
}

async function SI_cargarSecuencialMov(idPunto) {
    if (!idPunto) { document.getElementById('si-mov-secuencial').value = ''; return; }
    const tipo = document.getElementById('si-mov-tipo').value;
    const modulo = tipo === 'CXC' ? 'Ingresos' : 'Egresos';
    try {
        const r = await fetch(`${BASE_URL}/modulos/cuentas_por_cobrar/getSecuencialAjax?id_punto_emision=${idPunto}&modulo=${modulo}`, { headers:{'X-Requested-With':'XMLHttpRequest'} });
        const d = await r.json();
        if (d.ok) document.getElementById('si-mov-secuencial').value = d.formateado || '';
    } catch(e) {}
}

function SI_toggleBancoMov(idForma) {
    const opt = document.querySelector(`#si-mov-forma option[value="${idForma}"]`);
    const tipo = opt?.getAttribute('data-tipo') || '';
    document.getElementById('si-mov-div-banco').classList.toggle('d-none', tipo !== 'BANCO');
}

async function SI_guardarMovimiento() {
    const tipo    = document.getElementById('si-mov-tipo').value;
    const idSaldo = document.getElementById('si-mov-id').value;
    const fd = new FormData();
    fd.append('id_saldo',    idSaldo);
    fd.append('id_punto_emision', document.getElementById('si-mov-punto').value);
    fd.append('monto',       document.getElementById('si-mov-monto').value);
    fd.append('observaciones', document.getElementById('si-mov-obs').value);
    fd.append('tipo_operacion_bancaria', document.getElementById('si-mov-tipo-op').value);
    fd.append('numero_operacion', document.getElementById('si-mov-num-op')?.value || '');

    if (tipo === 'CXC') {
        fd.append('id_forma_cobro',      document.getElementById('si-mov-forma').value);
        fd.append('id_ingreso_concepto', document.getElementById('si-mov-concepto').value);
        fd.append('fecha_cobro',         document.getElementById('si-mov-fecha').value);
    } else {
        fd.append('id_forma_pago',       document.getElementById('si-mov-forma').value);
        fd.append('id_egreso_concepto',  document.getElementById('si-mov-concepto').value);
        fd.append('fecha_pago',          document.getElementById('si-mov-fecha').value);
    }

    const endpoint = tipo === 'CXC' ? 'registrarCobroAjax' : 'registrarPagoAjax';
    try {
        const r = await fetch(`${BASE_URL}/${RUTA_SI}/${endpoint}`, { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} });
        const d = await r.json();
        if (d.ok) {
            bootstrap.Modal.getInstance(document.getElementById('modalSIMovimiento'))?.hide();
            SI_toast(d.mensaje, 'success');
            if (tipo === 'CXC') SI_cargarCxc(); else SI_cargarCxp();
        } else { SI_toast(d.error, 'danger'); }
    } catch(e) { SI_toast('Error de conexión', 'danger'); }
}

/* ════════════════════════════════
   HISTORIAL
════════════════════════════════ */
async function SI_verHistorialCxc(id) {
    document.getElementById('si-hist-tbody').innerHTML = `<tr><td colspan="6" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>`;
    new bootstrap.Modal(document.getElementById('modalSIHistorial')).show();
    try {
        const r = await fetch(`${BASE_URL}/${RUTA_SI}/historialCobrosCxcAjax?id=${id}`, { headers:{'X-Requested-With':'XMLHttpRequest'} });
        const d = await r.json();
        if (!d.ok) return;
        SI_renderHistorial(d.historial, 'monto_cobrado');
    } catch(e) {}
}

async function SI_verHistorialCxp(id) {
    document.getElementById('si-hist-tbody').innerHTML = `<tr><td colspan="6" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>`;
    new bootstrap.Modal(document.getElementById('modalSIHistorial')).show();
    try {
        const r = await fetch(`${BASE_URL}/${RUTA_SI}/historialPagosCxpAjax?id=${id}`, { headers:{'X-Requested-With':'XMLHttpRequest'} });
        const d = await r.json();
        if (!d.ok) return;
        SI_renderHistorial(d.historial, 'monto_pagado');
    } catch(e) {}
}

function SI_renderHistorial(historial, campoMonto) {
    const tbody = document.getElementById('si-hist-tbody');
    if (!historial.length) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-muted">Sin movimientos</td></tr>`;
        document.getElementById('si-hist-total').textContent = '0.00';
        return;
    }
    let total = 0;
    tbody.innerHTML = historial.map(h => {
        const monto = parseFloat(h[campoMonto] || 0);
        total += monto;
        const nro = h.numero_ingreso || h.numero_egreso || '—';
        const forma = h.forma_cobro || h.forma_pago || '—';
        return `<tr>
            <td class="ps-3 small">${siFmtFecha(h.fecha_emision)}</td>
            <td class="font-monospace small">${siEsc(nro)}</td>
            <td class="small">${siEsc(forma)}</td>
            <td class="small">${siEsc(h.usuario_nombre||'—')}</td>
            <td class="text-end pe-3 fw-semibold text-success small">$${siFmt(monto)}</td>
            <td class="small text-muted">${siEsc(h.observaciones||'')}</td>
        </tr>`;
    }).join('');
    document.getElementById('si-hist-total').textContent = siFmt(total);
}

/* ════════════════════════════════
   HELPERS
════════════════════════════════ */
function siEsc(str) {
    return String(str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function siFmt(n) { return parseFloat(n||0).toFixed(2); }
function siFmtFecha(f) {
    if (!f) return '—';
    const d = new Date(f + 'T00:00:00');
    return d.toLocaleDateString('es-EC', { day:'2-digit', month:'2-digit', year:'numeric' });
}
function siEstadoBadge(estado, dias) {
    if (estado === 'PAGADO') return `<span class="badge bg-secondary bg-opacity-10 text-secondary border" style="font-size:.65rem;">Pagado</span>`;
    if (dias > 0) return `<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25" style="font-size:.65rem;">Vencida</span>`;
    if (estado === 'PARCIAL') return `<span class="badge bg-warning bg-opacity-10 text-warning border" style="font-size:.65rem;">Parcial</span>`;
    return `<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size:.65rem;">Pendiente</span>`;
}
function SI_toast(msg, tipo = 'info') {
    const c = document.createElement('div');
    c.className = `alert alert-${tipo} alert-dismissible shadow position-fixed bottom-0 end-0 m-3 py-2 px-3`;
    c.style.zIndex = '9999';
    c.innerHTML = msg + `<button type="button" class="btn-close btn-sm" onclick="this.parentElement.remove()"></button>`;
    document.body.appendChild(c);
    setTimeout(() => c.remove(), 4000);
}
