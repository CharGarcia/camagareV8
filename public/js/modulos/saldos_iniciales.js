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
    // Máscara Nº documento (000-000-000000000) en CXC, CXP y Consignaciones
    SI_aplicarMascaraNroDoc(document.getElementById('si-cxc-nro'));
    SI_aplicarMascaraNroDoc(document.getElementById('si-cxp-nro'));
    SI_aplicarMascaraNroDoc(document.getElementById('si-consig-nro'));
});

/* Máscara de comprobante 000-000-000000000 (3-3-9) */
function SI_aplicarMascaraNroDoc(el) {
    if (!el) return;
    el.addEventListener('input', (e) => {
        let v = e.target.value.replace(/\D/g, '');
        if (v.length > 15) v = v.slice(0, 15);
        let res = '';
        if (v.length > 0) res += v.slice(0, 3);
        if (v.length > 3) res += '-' + v.slice(3, 6);
        if (v.length > 6) res += '-' + v.slice(6, 15);
        e.target.value = res;
    });
    el.addEventListener('blur', (e) => {
        const parts = e.target.value.split('-');
        if (parts.length === 1 && parts[0].length > 0) {
            const v = parts[0];
            if (v.length <= 9) e.target.value = `001-001-${v.padStart(9, '0')}`;
        } else if (parts.length === 3) {
            e.target.value = `${parts[0].padStart(3,'0')}-${parts[1].padStart(3,'0')}-${parts[2].padStart(9,'0')}`;
        }
    });
}

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
    if (row?.id_cliente) {
        SI_setTercero('cxc', row.id_cliente, row.ruc_cliente, row.nombre_cliente);
    } else {
        SI_limpiarTercero('cxc');
    }
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
    const idCliente = document.getElementById('si-cxc-id-cliente').value;
    if (!idCliente) { SI_toast('Debe seleccionar un cliente registrado.', 'warning'); return; }
    form.append('nro_documento',     document.getElementById('si-cxc-nro').value);
    form.append('fecha_emision',     document.getElementById('si-cxc-femision').value);
    form.append('fecha_vencimiento', document.getElementById('si-cxc-fvenc').value);
    form.append('id_cliente',        idCliente);
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
    if (row?.id_proveedor) {
        SI_setTercero('cxp', row.id_proveedor, row.ruc_proveedor, row.nombre_proveedor);
    } else {
        SI_limpiarTercero('cxp');
    }
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
    const idProveedor = document.getElementById('si-cxp-id-proveedor').value;
    if (!idProveedor) { SI_toast('Debe seleccionar un proveedor registrado.', 'warning'); return; }
    form.append('tipo_documento',    document.getElementById('si-cxp-tipo').value);
    form.append('nro_documento',     document.getElementById('si-cxp-nro').value);
    form.append('fecha_emision',     document.getElementById('si-cxp-femision').value);
    form.append('fecha_vencimiento', document.getElementById('si-cxp-fvenc').value);
    form.append('id_proveedor',      idProveedor);
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
   EFECTIVO — CARGAR Y GUARDAR
   (formas de cobro/pago de tipo EFECTIVO)
════════════════════════════════ */
async function SI_cargarEfectivo() {
    const container = document.getElementById('efectivo-container');
    container.innerHTML = `<div class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Cargando…</div>`;
    try {
        const r = await fetch(`${BASE_URL}/${RUTA_SI}/getEfectivoAjax`, { headers: {'X-Requested-With':'XMLHttpRequest'} });
        const d = await r.json();
        if (!d.ok) { container.innerHTML = `<div class="text-danger p-3">${siEsc(d.error)}</div>`; return; }
        SI_renderEfectivo(d.cuentas || []);
    } catch(e) { container.innerHTML = `<div class="text-danger p-3">Error de conexión</div>`; }
}

function SI_renderEfectivo(cuentas) {
    const container = document.getElementById('efectivo-container');
    if (!cuentas.length) {
        container.innerHTML = `<div class="text-center py-5 text-muted"><i class="bi bi-cash-stack fs-3 d-block mb-2 opacity-25"></i>No hay formas de cobro/pago de tipo Efectivo registradas.</div>`;
        return;
    }
    container.innerHTML = `<div class="table-responsive">
        <table class="table table-sm table-hover align-middle border rounded-3">
            <thead class="table-light"><tr>
                <th>Forma de Cobro/Pago</th>
                <th style="width:150px;" class="text-center">Fecha Saldo</th>
                <th style="width:150px;" class="text-end">Saldo Inicial ($)</th>
                <th style="width:200px;">Observaciones</th>
                ${SI_PERM_ELIMINAR ? '<th style="width:60px;"></th>' : ''}
            </tr></thead>
            <tbody>
                ${cuentas.map(c => `<tr>
                    <td><span class="fw-semibold">${siEsc(c.nombre)}</span></td>
                    <td><input type="date" class="form-control form-control-sm shadow-none border si-efectivo-fecha"
                        data-forma="${c.id}" value="${c.fecha_saldo||''}"></td>
                    <td><div class="input-group input-group-sm">
                        <span class="input-group-text text-success">$</span>
                        <input type="number" class="form-control form-control-sm shadow-none text-end fw-semibold si-efectivo-saldo"
                            data-forma="${c.id}" step="0.01" placeholder="0.00" value="${c.saldo_inicial||''}">
                    </div></td>
                    <td><input type="text" class="form-control form-control-sm shadow-none si-efectivo-obs"
                        data-forma="${c.id}" maxlength="255" placeholder="Opcional…" value="${siEsc(c.observaciones||'')}"></td>
                    ${SI_PERM_ELIMINAR ? `<td class="text-center">${c.id_saldo_inicial ? `<button class="btn btn-outline-danger btn-sm py-0 px-1" onclick="SI_eliminarEfectivo(${c.id_saldo_inicial})" title="Quitar saldo"><i class="bi bi-x-lg"></i></button>` : ''}</td>` : ''}
                </tr>`).join('')}
            </tbody>
        </table>
    </div>`;
}

async function SI_guardarEfectivo() {
    const cuentas = [];
    document.querySelectorAll('.si-efectivo-fecha').forEach(el => {
        const forma = el.getAttribute('data-forma');
        const saldo = document.querySelector(`.si-efectivo-saldo[data-forma="${forma}"]`)?.value;
        const fecha = el.value;
        const obs   = document.querySelector(`.si-efectivo-obs[data-forma="${forma}"]`)?.value || '';
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
        if (d.ok) { SI_toast(d.mensaje, 'success'); SI_cargarEfectivo(); }
        else { SI_toast(d.error, 'danger'); }
    } catch(e) { SI_toast('Error de conexión', 'danger'); }
}

async function SI_eliminarEfectivo(id) {
    if (!confirm('¿Eliminar el saldo inicial de esta forma de efectivo?')) return;
    const fd = new FormData(); fd.append('id', id);
    try {
        const r = await fetch(`${BASE_URL}/${RUTA_SI}/eliminarBancoAjax`, { method:'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} });
        const d = await r.json();
        if (d.ok) { SI_toast(d.mensaje, 'success'); SI_cargarEfectivo(); }
        else { SI_toast(d.error, 'danger'); }
    } catch(e) { SI_toast('Error de conexión', 'danger'); }
}

/* ════════════════════════════════
   ANTICIPOS — listado + modal (atado a cliente/proveedor)
════════════════════════════════ */
let SI_datosAnti = [];
let SI_formasAnti = null;

async function SI_cargarFormasAnticipo() {
    if (SI_formasAnti === null) {
        try {
            const r = await fetch(`${BASE_URL}/${RUTA_SI}/getFormasAnticipoAjax`, { headers:{'X-Requested-With':'XMLHttpRequest'} });
            const d = await r.json();
            SI_formasAnti = d.ok ? (d.formas || []) : [];
        } catch(e) { SI_formasAnti = []; }
    }
    return SI_formasAnti;
}

async function SI_cargarAnticipos() {
    const tbody = document.getElementById('si-anti-tbody');
    tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4"><div class="spinner-border spinner-border-sm me-2" style="color:#6f42c1;"></div>Cargando…</td></tr>`;
    try {
        const r = await fetch(`${BASE_URL}/${RUTA_SI}/getAnticiposAjax`, { headers:{'X-Requested-With':'XMLHttpRequest'} });
        const d = await r.json();
        if (!d.ok) { tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">${siEsc(d.error)}</td></tr>`; return; }
        SI_datosAnti = d.filas || [];
        SI_renderAnticipos(SI_datosAnti);
    } catch(e) { tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">Error de conexión</td></tr>`; }
}

function SI_renderAnticipos(filas) {
    const tbody = document.getElementById('si-anti-tbody');
    document.getElementById('si-anti-count').textContent = filas.length + ' registros';
    if (!filas.length) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-3 d-block mb-2 opacity-25"></i>Sin registros</td></tr>`;
        return;
    }
    tbody.innerHTML = filas.map(f => {
        const esCli = f.tipo === 'CLIENTE';
        const badge = esCli
            ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size:.65rem;">Cliente</span>'
            : '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25" style="font-size:.65rem;">Proveedor</span>';
        return `<tr>
            <td class="ps-3">${badge}</td>
            <td class="text-truncate" title="${siEsc(f.nombre_tercero)}">${siEsc(f.nombre_tercero)}${f.ruc_tercero ? `<small class="text-muted d-block">${siEsc(f.ruc_tercero)}</small>` : ''}</td>
            <td class="small">${siEsc(f.forma_nombre||'—')}</td>
            <td class="text-center small">${siFmtFecha(f.fecha_saldo)}</td>
            <td class="text-end fw-bold">$${f.saldo_inicial}</td>
            <td class="small text-muted text-truncate" style="max-width:200px;" title="${siEsc(f.observaciones||'')}">${siEsc(f.observaciones||'')}</td>
            <td class="text-center">
                ${SI_PERM_MODIFICAR ? `<button class="btn btn-outline-warning btn-sm py-0 px-1" onclick='SI_abrirModalAnticipo(${JSON.stringify(f)})' title="Editar"><i class="bi bi-pencil"></i></button>` : ''}
            </td>
        </tr>`;
    }).join('');
}

function SI_filtrarAnticipos(q) {
    const t = q.toLowerCase();
    SI_renderAnticipos(t ? SI_datosAnti.filter(f =>
        (f.nombre_tercero||'').toLowerCase().includes(t) ||
        (f.ruc_tercero||'').toLowerCase().includes(t) ||
        (f.forma_nombre||'').toLowerCase().includes(t)
    ) : SI_datosAnti);
}

/* Muestra el selector de cliente o proveedor según la dirección de la forma */
function SI_onFormaAnticipoChange() {
    const sel = document.getElementById('si-anti-forma');
    const aplica = sel.options[sel.selectedIndex]?.getAttribute('data-aplica') || '';
    const esProv = aplica === 'EGRESO';
    document.getElementById('si-anti-cli-block').classList.toggle('d-none', esProv || aplica === '');
    document.getElementById('si-anti-prov-block').classList.toggle('d-none', !esProv);
}

async function SI_abrirModalAnticipo(row = null) {
    const formas = await SI_cargarFormasAnticipo();
    const sel = document.getElementById('si-anti-forma');
    sel.innerHTML = '<option value="">— Seleccione —</option>' +
        formas.map(f => {
            const dir = f.aplica_en === 'EGRESO' ? 'Proveedores' : 'Clientes';
            return `<option value="${f.id}" data-aplica="${siEsc(f.aplica_en||'')}">${siEsc(f.nombre)} · ${dir}</option>`;
        }).join('');

    document.getElementById('si-anti-id').value = row?.id || '';
    sel.value = row?.id_forma_pago || '';
    document.getElementById('si-anti-fecha').value = row?.fecha_saldo || new Date().toISOString().slice(0,10);
    document.getElementById('si-anti-saldo').value = row?.saldo_inicial || '';
    document.getElementById('si-anti-obs').value = row?.observaciones || '';

    SI_limpiarTercero('anticli');
    SI_limpiarTercero('antiprov');
    SI_onFormaAnticipoChange();
    if (row) {
        if (row.tipo === 'CLIENTE' && row.id_cliente) SI_setTercero('anticli', row.id_cliente, row.ruc_tercero, row.nombre_tercero);
        if (row.tipo === 'PROVEEDOR' && row.id_proveedor) SI_setTercero('antiprov', row.id_proveedor, row.ruc_tercero, row.nombre_tercero);
    }

    const btnEl = document.getElementById('btn-eliminar-anti');
    if (row?.id && SI_PERM_ELIMINAR) { btnEl.style.removeProperty('display'); btnEl.setAttribute('data-id', row.id); }
    else { btnEl.style.setProperty('display','none','important'); }

    new bootstrap.Modal(document.getElementById('modalSIAnticipo')).show();
}

async function SI_guardarAnticipo() {
    const idForma = document.getElementById('si-anti-forma').value;
    if (!idForma) { SI_toast('Debe seleccionar una forma de anticipo.', 'warning'); return; }
    const esProv = !document.getElementById('si-anti-prov-block').classList.contains('d-none');
    const idTercero = esProv
        ? document.getElementById('si-anti-id-proveedor').value
        : document.getElementById('si-anti-id-cliente').value;
    if (!idTercero) { SI_toast(esProv ? 'Debe seleccionar un proveedor.' : 'Debe seleccionar un cliente.', 'warning'); return; }

    const id = document.getElementById('si-anti-id').value;
    const fd = new FormData();
    if (id) fd.append('id', id);
    fd.append('id_forma_pago', idForma);
    fd.append('id_tercero',    idTercero);
    fd.append('fecha_saldo',   document.getElementById('si-anti-fecha').value);
    fd.append('saldo_inicial', document.getElementById('si-anti-saldo').value);
    fd.append('observaciones', document.getElementById('si-anti-obs').value);
    try {
        const r = await fetch(`${BASE_URL}/${RUTA_SI}/guardarAnticipoAjax`, { method:'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} });
        const d = await r.json();
        if (d.ok) { bootstrap.Modal.getInstance(document.getElementById('modalSIAnticipo'))?.hide(); SI_toast(d.mensaje, 'success'); SI_cargarAnticipos(); }
        else { SI_toast(d.error, 'danger'); }
    } catch(e) { SI_toast('Error de conexión', 'danger'); }
}

async function SI_eliminarAnticipo() {
    const id = document.getElementById('btn-eliminar-anti').getAttribute('data-id');
    if (!id || !confirm('¿Eliminar este saldo inicial de anticipo?')) return;
    const fd = new FormData(); fd.append('id', id);
    try {
        const r = await fetch(`${BASE_URL}/${RUTA_SI}/eliminarAnticipoAjax`, { method:'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} });
        const d = await r.json();
        if (d.ok) { bootstrap.Modal.getInstance(document.getElementById('modalSIAnticipo'))?.hide(); SI_toast(d.mensaje, 'success'); SI_cargarAnticipos(); }
        else { SI_toast(d.error, 'danger'); }
    } catch(e) { SI_toast('Error de conexión', 'danger'); }
}

/* ════════════════════════════════
   CATÁLOGOS (bodegas / vendedores) — cacheados
════════════════════════════════ */
let SI_bodegas = null;
let SI_vendedores = null;

async function SI_cargarBodegasSelect() {
    if (SI_bodegas === null) {
        try {
            const r = await fetch(`${BASE_URL}/${RUTA_SI}/getBodegasAjax`, { headers:{'X-Requested-With':'XMLHttpRequest'} });
            const d = await r.json();
            SI_bodegas = d.ok ? (d.data || []) : [];
        } catch(e) { SI_bodegas = []; }
    }
    return SI_bodegas;
}
async function SI_cargarVendedoresSelect() {
    if (SI_vendedores === null) {
        try {
            const r = await fetch(`${BASE_URL}/${RUTA_SI}/getVendedoresAjax`, { headers:{'X-Requested-With':'XMLHttpRequest'} });
            const d = await r.json();
            SI_vendedores = d.ok ? (d.data || []) : [];
        } catch(e) { SI_vendedores = []; }
    }
    return SI_vendedores;
}
function SI_fillSelect(selId, items, valKey, txtKey, placeholder) {
    const sel = document.getElementById(selId);
    if (!sel) return;
    sel.innerHTML = `<option value="">${placeholder}</option>` +
        items.map(it => `<option value="${it[valKey]}">${siEsc(it[txtKey])}</option>`).join('');
}

/* ════════════════════════════════
   INVENTARIO — saldos de apertura
════════════════════════════════ */
let SI_datosInv = [];

async function SI_cargarInventario() {
    const tbody = document.getElementById('si-inv-tbody');
    tbody.innerHTML = `<tr><td colspan="9" class="text-center py-4"><div class="spinner-border spinner-border-sm text-info me-2"></div>Cargando…</td></tr>`;
    try {
        const r = await fetch(`${BASE_URL}/${RUTA_SI}/getInventarioAjax`, { headers:{'X-Requested-With':'XMLHttpRequest'} });
        const d = await r.json();
        if (!d.ok) { tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4">${siEsc(d.error)}</td></tr>`; return; }
        SI_datosInv = d.filas || [];
        SI_renderInventario(SI_datosInv);
    } catch(e) { tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4">Error de conexión</td></tr>`; }
}

function SI_renderInventario(filas) {
    const tbody = document.getElementById('si-inv-tbody');
    document.getElementById('si-inv-count').textContent = filas.length + ' registros';
    if (!filas.length) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-3 d-block mb-2 opacity-25"></i>Sin registros</td></tr>`;
        return;
    }
    tbody.innerHTML = filas.map(f => `<tr>
        <td class="ps-3"><span class="fw-semibold">${siEsc(f.producto_nombre)}</span>${f.producto_codigo ? `<small class="text-muted d-block font-monospace">${siEsc(f.producto_codigo)}</small>` : ''}</td>
        <td>${siEsc(f.bodega_nombre)}</td>
        <td class="text-end fw-semibold">${siFmt(f.cantidad)}</td>
        <td class="text-end small">$${siFmt(f.costo_unitario)}</td>
        <td class="text-end small">$${siFmt(f.costo_total)}</td>
        <td class="small">${siEsc(f.numero_lote||'—')}</td>
        <td class="text-center small">${f.fecha_caducidad ? siFmtFecha(f.fecha_caducidad) : '—'}</td>
        <td class="small">${siEsc(f.nup||'—')}</td>
        <td class="text-center">${SI_PERM_ELIMINAR ? `<button class="btn btn-outline-danger btn-sm py-0 px-1" onclick="SI_eliminarInventario(${f.id})" title="Eliminar"><i class="bi bi-trash"></i></button>` : ''}</td>
    </tr>`).join('');
}

function SI_filtrarInventario(q) {
    const t = q.toLowerCase();
    SI_renderInventario(t ? SI_datosInv.filter(f =>
        (f.producto_nombre||'').toLowerCase().includes(t) ||
        (f.producto_codigo||'').toLowerCase().includes(t) ||
        (f.bodega_nombre||'').toLowerCase().includes(t) ||
        (f.numero_lote||'').toLowerCase().includes(t)
    ) : SI_datosInv);
}

async function SI_abrirModalInventario() {
    SI_limpiarProducto('inv');
    document.getElementById('si-inv-cantidad').value = '';
    document.getElementById('si-inv-costo').value = '';
    document.getElementById('si-inv-lote').value = '';
    document.getElementById('si-inv-caducidad').value = '';
    document.getElementById('si-inv-nup').value = '';
    document.getElementById('si-inv-obs').value = '';
    const bodegas = await SI_cargarBodegasSelect();
    SI_fillSelect('si-inv-bodega', bodegas, 'id', 'nombre', '— Seleccione —');
    new bootstrap.Modal(document.getElementById('modalSIInventario')).show();
}

async function SI_guardarInventario() {
    const idProd = document.getElementById('si-inv-id-producto').value;
    if (!idProd) { SI_toast('Debe seleccionar un producto.', 'warning'); return; }
    if (!document.getElementById('si-inv-bodega').value) { SI_toast('Debe seleccionar una bodega.', 'warning'); return; }
    const fd = new FormData();
    fd.append('id_producto',     idProd);
    fd.append('id_bodega',       document.getElementById('si-inv-bodega').value);
    fd.append('cantidad',        document.getElementById('si-inv-cantidad').value);
    fd.append('costo_unitario',  document.getElementById('si-inv-costo').value);
    fd.append('lote',            document.getElementById('si-inv-lote').value);
    fd.append('fecha_caducidad', document.getElementById('si-inv-caducidad').value);
    fd.append('nup',             document.getElementById('si-inv-nup').value);
    fd.append('observaciones',   document.getElementById('si-inv-obs').value);
    try {
        const r = await fetch(`${BASE_URL}/${RUTA_SI}/guardarInventarioAjax`, { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} });
        const d = await r.json();
        if (d.ok) { bootstrap.Modal.getInstance(document.getElementById('modalSIInventario'))?.hide(); SI_toast(d.mensaje, 'success'); SI_cargarInventario(); }
        else { SI_toast(d.error, 'danger'); }
    } catch(e) { SI_toast('Error de conexión', 'danger'); }
}

async function SI_eliminarInventario(id) {
    if (!confirm('¿Eliminar este saldo de inventario? Se revertirá el stock.')) return;
    const fd = new FormData(); fd.append('id', id);
    try {
        const r = await fetch(`${BASE_URL}/${RUTA_SI}/eliminarInventarioAjax`, { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} });
        const d = await r.json();
        if (d.ok) { SI_toast(d.mensaje, 'success'); SI_cargarInventario(); }
        else { SI_toast(d.error, 'danger'); }
    } catch(e) { SI_toast('Error de conexión', 'danger'); }
}

async function SI_importarInventario(input) {
    if (!input.files[0]) return;
    const fd = new FormData(); fd.append('archivo', input.files[0]);
    SI_toast('Importando archivo…', 'info');
    try {
        const r = await fetch(`${BASE_URL}/${RUTA_SI}/importarInventarioAjax`, { method:'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} });
        const d = await r.json();
        if (d.ok) {
            let msg = d.mensaje;
            if (d.errores?.length) msg += '\n' + d.errores.join('\n');
            alert(msg);
            SI_cargarInventario();
        } else { SI_toast(d.error, 'danger'); }
    } catch(e) { SI_toast('Error al importar', 'danger'); }
    input.value = '';
}

function SI_descargarTemplateInventario() {
    window.location.href = `${BASE_URL}/${RUTA_SI}/descargarTemplateInventario`;
}

/* ════════════════════════════════
   CONSIGNACIONES — registro de saldo pendiente
════════════════════════════════ */
let SI_datosConsig = [];

async function SI_cargarConsig() {
    const tbody = document.getElementById('si-consig-tbody');
    tbody.innerHTML = `<tr><td colspan="10" class="text-center py-4"><div class="spinner-border spinner-border-sm text-secondary me-2"></div>Cargando…</td></tr>`;
    try {
        const r = await fetch(`${BASE_URL}/${RUTA_SI}/getConsignacionesAjax`, { headers:{'X-Requested-With':'XMLHttpRequest'} });
        const d = await r.json();
        if (!d.ok) { tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger py-4">${siEsc(d.error)}</td></tr>`; return; }
        SI_datosConsig = d.filas || [];
        SI_renderConsig(SI_datosConsig);
    } catch(e) { tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger py-4">Error de conexión</td></tr>`; }
}

function SI_renderConsig(filas) {
    const tbody = document.getElementById('si-consig-tbody');
    document.getElementById('si-consig-count').textContent = filas.length + ' registros';
    if (!filas.length) {
        tbody.innerHTML = `<tr><td colspan="10" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-3 d-block mb-2 opacity-25"></i>Sin registros</td></tr>`;
        return;
    }
    tbody.innerHTML = filas.map(f => `<tr>
        <td class="ps-3 font-monospace small">${siEsc(f.nro_documento||'—')}</td>
        <td class="text-center small">${siFmtFecha(f.fecha_emision)}</td>
        <td class="text-truncate" title="${siEsc(f.nombre_cliente)}">${siEsc(f.nombre_cliente)}${f.ruc_cliente ? `<small class="text-muted d-block">${siEsc(f.ruc_cliente)}</small>` : ''}</td>
        <td class="text-truncate" title="${siEsc(f.producto_nombre)}">${siEsc(f.producto_nombre)}${f.producto_codigo ? `<small class="text-muted d-block font-monospace">${siEsc(f.producto_codigo)}</small>` : ''}</td>
        <td class="text-end fw-semibold">${f.cantidad}</td>
        <td class="text-end small">$${f.precio_unitario}</td>
        <td class="text-end fw-bold">$${f.total}</td>
        <td class="small">${siEsc(f.nombre_vendedor||'—')}</td>
        <td class="small">${siEsc(f.nombre_bodega||'—')}</td>
        <td class="text-center">
            ${SI_PERM_MODIFICAR ? `<button class="btn btn-outline-warning btn-sm py-0 px-1" onclick='SI_abrirModalConsig(${JSON.stringify(f)})' title="Editar"><i class="bi bi-pencil"></i></button>` : ''}
        </td>
    </tr>`).join('');
}

function SI_filtrarConsig(q) {
    const t = q.toLowerCase();
    SI_renderConsig(t ? SI_datosConsig.filter(f =>
        (f.nombre_cliente||'').toLowerCase().includes(t) ||
        (f.producto_nombre||'').toLowerCase().includes(t) ||
        (f.nro_documento||'').toLowerCase().includes(t)
    ) : SI_datosConsig);
}

async function SI_abrirModalConsig(row = null) {
    document.getElementById('si-consig-id').value = row?.id || '';
    document.getElementById('si-consig-fecha').value = row?.fecha_emision || new Date().toISOString().slice(0,10);
    document.getElementById('si-consig-nro').value = row?.nro_documento || '';
    if (row?.id_cliente) SI_setTercero('consig', row.id_cliente, row.ruc_cliente, row.nombre_cliente); else SI_limpiarTercero('consig');
    if (row?.id_producto) SI_setProducto('consig', row.id_producto, row.producto_codigo, row.producto_nombre); else SI_limpiarProducto('consig');
    document.getElementById('si-consig-cantidad').value = row?.cantidad || '';
    document.getElementById('si-consig-precio').value = row?.precio_unitario || '';
    document.getElementById('si-consig-lote').value = row?.lote || '';
    document.getElementById('si-consig-caducidad').value = row?.fecha_caducidad || '';
    document.getElementById('si-consig-nup').value = row?.nup || '';
    document.getElementById('si-consig-obs').value = row?.observaciones || '';

    const [bodegas, vendedores] = await Promise.all([SI_cargarBodegasSelect(), SI_cargarVendedoresSelect()]);
    SI_fillSelect('si-consig-bodega', bodegas, 'id', 'nombre', '— Ninguna —');
    SI_fillSelect('si-consig-vendedor', vendedores, 'id', 'nombre', '— Ninguno —');
    document.getElementById('si-consig-bodega').value = row?.id_bodega || '';
    document.getElementById('si-consig-vendedor').value = row?.id_vendedor || '';

    const btnEl = document.getElementById('btn-eliminar-consig');
    if (row?.id && SI_PERM_ELIMINAR) { btnEl.style.removeProperty('display'); btnEl.setAttribute('data-id', row.id); }
    else { btnEl.style.setProperty('display','none','important'); }

    new bootstrap.Modal(document.getElementById('modalSIConsig')).show();
}

async function SI_guardarConsig() {
    const idCliente = document.getElementById('si-consig-id-cliente').value;
    const idProd    = document.getElementById('si-consig-id-producto').value;
    if (!idCliente) { SI_toast('Debe seleccionar un cliente registrado.', 'warning'); return; }
    if (!idProd)    { SI_toast('Debe seleccionar un producto.', 'warning'); return; }
    const id = document.getElementById('si-consig-id').value;
    const fd = new FormData();
    if (id) fd.append('id', id);
    fd.append('fecha_emision',   document.getElementById('si-consig-fecha').value);
    fd.append('nro_documento',   document.getElementById('si-consig-nro').value);
    fd.append('id_cliente',      idCliente);
    fd.append('id_producto',     idProd);
    fd.append('cantidad',        document.getElementById('si-consig-cantidad').value);
    fd.append('precio_unitario', document.getElementById('si-consig-precio').value);
    fd.append('id_bodega',       document.getElementById('si-consig-bodega').value);
    fd.append('id_vendedor',     document.getElementById('si-consig-vendedor').value);
    fd.append('lote',            document.getElementById('si-consig-lote').value);
    fd.append('fecha_caducidad', document.getElementById('si-consig-caducidad').value);
    fd.append('nup',             document.getElementById('si-consig-nup').value);
    fd.append('observaciones',   document.getElementById('si-consig-obs').value);
    try {
        const r = await fetch(`${BASE_URL}/${RUTA_SI}/guardarConsignacionAjax`, { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} });
        const d = await r.json();
        if (d.ok) { bootstrap.Modal.getInstance(document.getElementById('modalSIConsig'))?.hide(); SI_toast(d.mensaje, 'success'); SI_cargarConsig(); }
        else { SI_toast(d.error, 'danger'); }
    } catch(e) { SI_toast('Error de conexión', 'danger'); }
}

async function SI_eliminarConsig() {
    const id = document.getElementById('btn-eliminar-consig').getAttribute('data-id');
    if (!id || !confirm('¿Eliminar este registro de consignación?')) return;
    const fd = new FormData(); fd.append('id', id);
    try {
        const r = await fetch(`${BASE_URL}/${RUTA_SI}/eliminarConsignacionAjax`, { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} });
        const d = await r.json();
        if (d.ok) { bootstrap.Modal.getInstance(document.getElementById('modalSIConsig'))?.hide(); SI_toast(d.mensaje, 'success'); SI_cargarConsig(); }
        else { SI_toast(d.error, 'danger'); }
    } catch(e) { SI_toast('Error de conexión', 'danger'); }
}

async function SI_importarConsig(input) {
    if (!input.files[0]) return;
    const fd = new FormData(); fd.append('archivo', input.files[0]);
    SI_toast('Importando archivo…', 'info');
    try {
        const r = await fetch(`${BASE_URL}/${RUTA_SI}/importarConsignacionAjax`, { method:'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} });
        const d = await r.json();
        if (d.ok) {
            let msg = d.mensaje;
            if (d.errores?.length) msg += '\n' + d.errores.join('\n');
            alert(msg);
            SI_cargarConsig();
        } else { SI_toast(d.error, 'danger'); }
    } catch(e) { SI_toast('Error al importar', 'danger'); }
    input.value = '';
}

function SI_descargarTemplateConsig() {
    window.location.href = `${BASE_URL}/${RUTA_SI}/descargarTemplateConsignacion`;
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
   SELECTOR CLIENTE / PROVEEDOR
   (deben existir registrados)
════════════════════════════════ */
const SI_TERCERO_CFG = {
    cxc: {
        id:'si-cxc-id-cliente', buscar:'si-cxc-cliente-buscar', dropdown:'si-cxc-cliente-dropdown',
        sel:'si-cxc-cliente-sel', selTxt:'si-cxc-cliente-sel-txt', endpoint:'buscarClienteAjax',
        vacio:'No se encontraron clientes registrados.'
    },
    cxp: {
        id:'si-cxp-id-proveedor', buscar:'si-cxp-proveedor-buscar', dropdown:'si-cxp-proveedor-dropdown',
        sel:'si-cxp-proveedor-sel', selTxt:'si-cxp-proveedor-sel-txt', endpoint:'buscarProveedorAjax',
        vacio:'No se encontraron proveedores registrados.'
    },
    consig: {
        id:'si-consig-id-cliente', buscar:'si-consig-cli-buscar', dropdown:'si-consig-cli-dropdown',
        sel:'si-consig-cli-sel', selTxt:'si-consig-cli-sel-txt', endpoint:'buscarClienteAjax',
        vacio:'No se encontraron clientes registrados.'
    },
    anticli: {
        id:'si-anti-id-cliente', buscar:'si-anti-cli-buscar', dropdown:'si-anti-cli-dropdown',
        sel:'si-anti-cli-sel', selTxt:'si-anti-cli-sel-txt', endpoint:'buscarClienteAjax',
        vacio:'No se encontraron clientes registrados.'
    },
    antiprov: {
        id:'si-anti-id-proveedor', buscar:'si-anti-prov-buscar', dropdown:'si-anti-prov-dropdown',
        sel:'si-anti-prov-sel', selTxt:'si-anti-prov-sel-txt', endpoint:'buscarProveedorAjax',
        vacio:'No se encontraron proveedores registrados.'
    }
};
let SI_terceroTimer = null;

function SI_buscarTercero(modo, q) {
    const cfg = SI_TERCERO_CFG[modo];
    const dropdown = document.getElementById(cfg.dropdown);
    clearTimeout(SI_terceroTimer);
    SI_terceroTimer = setTimeout(async () => {
        try {
            const r = await fetch(`${BASE_URL}/${RUTA_SI}/${cfg.endpoint}?q=${encodeURIComponent(q || '')}`,
                { headers: {'X-Requested-With':'XMLHttpRequest'} });
            const d = await r.json();
            const items = d.ok ? (d.data || []) : [];
            if (!items.length) {
                dropdown.innerHTML = `<div class="list-group-item small text-muted py-2">${cfg.vacio}</div>`;
            } else {
                dropdown.innerHTML = items.map(it => {
                    const tipo = it.tipo_nombre ? `<small class="text-muted ms-1">· ${siEsc(it.tipo_nombre)}</small>` : '';
                    const payload = siEsc(JSON.stringify({ id: it.id, ident: it.identificacion, nombre: it.nombre })).replace(/'/g, '&#39;');
                    return `<button type="button" class="list-group-item list-group-item-action py-1 px-2 small"
                                onmousedown='SI_pickTercero("${modo}", ${payload})'>
                                <span class="fw-semibold font-monospace">${siEsc(it.identificacion || '—')}</span>${tipo}
                                <span class="d-block text-truncate">${siEsc(it.nombre || '')}</span>
                            </button>`;
                }).join('');
            }
            dropdown.classList.remove('d-none');
        } catch (e) { dropdown.classList.add('d-none'); }
    }, 250);
}

function SI_pickTercero(modo, obj) {
    SI_setTercero(modo, obj.id, obj.ident, obj.nombre);
}

function SI_setTercero(modo, id, ident, nombre) {
    const cfg = SI_TERCERO_CFG[modo];
    document.getElementById(cfg.id).value = id || '';
    document.getElementById(cfg.selTxt).textContent = `${ident || '—'} — ${nombre || ''}`;
    document.getElementById(cfg.sel).classList.remove('d-none');
    const buscar = document.getElementById(cfg.buscar);
    buscar.value = '';
    buscar.classList.add('d-none');
    document.getElementById(cfg.dropdown).classList.add('d-none');
}

function SI_limpiarTercero(modo) {
    const cfg = SI_TERCERO_CFG[modo];
    document.getElementById(cfg.id).value = '';
    document.getElementById(cfg.selTxt).textContent = '';
    document.getElementById(cfg.sel).classList.add('d-none');
    const buscar = document.getElementById(cfg.buscar);
    buscar.value = '';
    buscar.classList.remove('d-none');
    document.getElementById(cfg.dropdown).classList.add('d-none');
    buscar.focus();
}

/* Cerrar dropdowns al hacer clic fuera */
document.addEventListener('click', (e) => {
    Object.values(SI_TERCERO_CFG).forEach(cfg => {
        const dd = document.getElementById(cfg.dropdown);
        const inp = document.getElementById(cfg.buscar);
        if (dd && !dd.classList.contains('d-none') && e.target !== inp && !dd.contains(e.target)) {
            dd.classList.add('d-none');
        }
    });
    Object.values(SI_PROD_CFG).forEach(cfg => {
        const dd = document.getElementById(cfg.dropdown);
        const inp = document.getElementById(cfg.buscar);
        if (dd && !dd.classList.contains('d-none') && e.target !== inp && !dd.contains(e.target)) {
            dd.classList.add('d-none');
        }
    });
});

/* ════════════════════════════════
   SELECTOR DE PRODUCTO (inventario / consignaciones)
════════════════════════════════ */
const SI_PROD_CFG = {
    inv: {
        id:'si-inv-id-producto', buscar:'si-inv-prod-buscar', dropdown:'si-inv-prod-dropdown',
        sel:'si-inv-prod-sel', selTxt:'si-inv-prod-sel-txt'
    },
    consig: {
        id:'si-consig-id-producto', buscar:'si-consig-prod-buscar', dropdown:'si-consig-prod-dropdown',
        sel:'si-consig-prod-sel', selTxt:'si-consig-prod-sel-txt'
    }
};
let SI_prodTimer = null;

function SI_buscarProducto(modo, q) {
    const cfg = SI_PROD_CFG[modo];
    const dropdown = document.getElementById(cfg.dropdown);
    clearTimeout(SI_prodTimer);
    SI_prodTimer = setTimeout(async () => {
        try {
            const r = await fetch(`${BASE_URL}/${RUTA_SI}/buscarProductoAjax?q=${encodeURIComponent(q || '')}`,
                { headers: {'X-Requested-With':'XMLHttpRequest'} });
            const d = await r.json();
            const items = d.ok ? (d.data || []) : [];
            if (!items.length) {
                dropdown.innerHTML = `<div class="list-group-item small text-muted py-2">No se encontraron productos.</div>`;
            } else {
                dropdown.innerHTML = items.map(it => {
                    const payload = siEsc(JSON.stringify({ id: it.id, codigo: it.codigo, nombre: it.nombre })).replace(/'/g, '&#39;');
                    return `<button type="button" class="list-group-item list-group-item-action py-1 px-2 small"
                                onmousedown='SI_pickProducto("${modo}", ${payload})'>
                                <span class="fw-semibold font-monospace">${siEsc(it.codigo || '—')}</span>
                                <span class="d-block text-truncate">${siEsc(it.nombre || '')}</span>
                            </button>`;
                }).join('');
            }
            dropdown.classList.remove('d-none');
        } catch (e) { dropdown.classList.add('d-none'); }
    }, 250);
}

function SI_pickProducto(modo, obj) {
    SI_setProducto(modo, obj.id, obj.codigo, obj.nombre);
}

function SI_setProducto(modo, id, codigo, nombre) {
    const cfg = SI_PROD_CFG[modo];
    document.getElementById(cfg.id).value = id || '';
    document.getElementById(cfg.selTxt).textContent = `${codigo || '—'} — ${nombre || ''}`;
    document.getElementById(cfg.sel).classList.remove('d-none');
    const buscar = document.getElementById(cfg.buscar);
    buscar.value = '';
    buscar.classList.add('d-none');
    document.getElementById(cfg.dropdown).classList.add('d-none');
}

function SI_limpiarProducto(modo) {
    const cfg = SI_PROD_CFG[modo];
    document.getElementById(cfg.id).value = '';
    document.getElementById(cfg.selTxt).textContent = '';
    document.getElementById(cfg.sel).classList.add('d-none');
    const buscar = document.getElementById(cfg.buscar);
    buscar.value = '';
    buscar.classList.remove('d-none');
    document.getElementById(cfg.dropdown).classList.add('d-none');
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
