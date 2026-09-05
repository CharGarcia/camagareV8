/**
 * cuentas_por_cobrar.js
 * Módulo de Cuentas por Cobrar – lógica del cliente
 */

'use strict';

/* ════════════════════════════════════════════════════
   ESTADO GLOBAL
════════════════════════════════════════════════════ */
let CXC_datos         = [];   // filas completas recibidas del servidor
let CXC_filtradoLocal = [];   // filas mostradas tras filtro de texto
let CXC_formasCobro   = [];
let CXC_plantillasWA  = [];
let CXC_seleccionados = new Set(); // ids de facturas seleccionadas
// Catálogos del modal cobro
let CXC_catalogos = { puntos: [], conceptos: [], formas: [] };
let CXC_catalogosCargados = false;
let CXC_cobroOrigen = 'FACTURA'; // origen del documento en el modal de cobro
let CXC_agrupado    = false;           // vista agrupada por cliente
const CXC_gruposAbiertos = new Set();  // claves de grupos expandidos

/* ════════════════════════════════════════════════════
   INICIALIZACIÓN
════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
    CXC_cargar();
    CXC_cargarCatalogos();
    if (CXC_TIENE_WA) CXC_cargarPlantillasWA();
    CXC_initBuscadorClientes();
});

/* ════════════════════════════════════════════════════
   CARGAR DATOS PRINCIPALES
════════════════════════════════════════════════════ */
async function CXC_cargar() {
    const tbody = document.getElementById('cxc-tbody');
    tbody.innerHTML = `<tr><td colspan="11" class="text-center py-4"><div class="spinner-border spinner-border-sm text-success me-2"></div>Cargando…</td></tr>`;
    CXC_seleccionados.clear();

    const params = new URLSearchParams({
        accion:      'generarAjax',
        estado:      document.getElementById('cxc-estado')?.value       || 'PENDIENTES',
        tipo_doc:    document.getElementById('cxc-tipo-doc')?.value     || 'TODOS',
        fecha_desde: document.getElementById('cxc-fecha-desde')?.value  || '',
        fecha_hasta: document.getElementById('cxc-fecha-hasta')?.value  || '',
        id_cliente:  CXC_getClientesSeleccionados(),
        id_vendedor: document.getElementById('cxc-vendedor')?.value    || '',
    });

    try {
        const r = await fetch(`${BASE_URL}/${RUTA_MODULO_CXC}/generarAjax?${params}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await r.json();

        if (!data.ok) {
            tbody.innerHTML = `<tr><td colspan="11" class="text-center py-4 text-danger"><i class="bi bi-exclamation-triangle me-2"></i>${data.error || 'Error al cargar'}</td></tr>`;
            return;
        }

        CXC_datos = data.filas || [];
        CXC_filtradoLocal = [...CXC_datos];

        CXC_actualizarStats(data.stats || {});
        CXC_renderTabla(CXC_filtradoLocal);

    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="11" class="text-center py-4 text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Error de conexión</td></tr>`;
        console.error('[CXC]', e);
    }
}

/* ════════════════════════════════════════════════════
   ESTADÍSTICAS
════════════════════════════════════════════════════ */
function CXC_actualizarStats(s) {
    document.getElementById('cxc-stat-facturas').textContent = s.total_facturas || 0;
    document.getElementById('cxc-stat-saldo').textContent    = CXC_fmt(s.total_saldo || 0);
    document.getElementById('cxc-stat-vencido').textContent  = CXC_fmt(s.total_vencido || 0);
    document.getElementById('cxc-stat-aldia').textContent    = CXC_fmt(s.total_al_dia || 0);
    document.getElementById('cxc-stat-fvencidas').textContent= s.facturas_vencidas || 0;
}

/* ════════════════════════════════════════════════════
   RENDER TABLA
════════════════════════════════════════════════════ */
function CXC_renderTabla(filas) {
    const tbody = document.getElementById('cxc-tbody');
    const label = document.getElementById('cxc-count-label');

    if (!filas.length) {
        label.textContent = '0 registros';
        tbody.innerHTML = `<tr><td colspan="11" class="text-center py-5 text-muted">
            <i class="bi bi-wallet2 fs-3 d-block mb-2 text-success opacity-40"></i>
            No se encontraron cuentas por cobrar con los filtros aplicados.
        </td></tr>`;
        return;
    }

    if (CXC_agrupado) { CXC_renderAgrupado(filas); return; }

    label.textContent = filas.length + ' registros';
    let html = '';
    for (const r of filas) html += CXC_filaHtml(r);
    tbody.innerHTML = html;
}

/* Clave única de una fila del listado unificado (los ids pueden repetirse
   entre facturas, recibos y saldos iniciales; el origen los distingue). */
function CXC_keyFila(r) {
    return `${r.origen}:${r.id}`;
}

/* Construye una fila <tr> de detalle (11 columnas). Reutilizada por la vista
   detallada y por la vista agrupada (para los documentos dentro de cada cliente). */
function CXC_filaHtml(r) {
    const dias     = parseInt(r.dias_vencido) || 0;
    const saldo    = parseFloat(r.saldo);
    const key      = CXC_keyFila(r);
    const selec    = CXC_seleccionados.has(key);

    let badgeHtml, rowClass = '';
    if (saldo <= 0) {
        badgeHtml = `<span class="badge badge-pagada rounded-pill small px-2">Pagada</span>`;
    } else if (dias > 90) {
        badgeHtml = `<span class="badge badge-vencida rounded-pill small px-2">+90d vencida</span>`;
        rowClass  = 'table-danger';
    } else if (dias > 30) {
        badgeHtml = `<span class="badge badge-vencida rounded-pill small px-2">Vencida ${dias}d</span>`;
        rowClass  = 'table-warning';
    } else if (dias > 0) {
        badgeHtml = `<span class="badge badge-proxima rounded-pill small px-2">Vencida ${dias}d</span>`;
    } else {
        const proximos = -dias; // días que restan para vencer
        badgeHtml = `<span class="badge badge-vigente rounded-pill small px-2">Vigente (${proximos}d)</span>`;
    }

    const fEmision   = CXC_fmtFecha(r.fecha_emision);
    const fVenc      = CXC_fmtFecha(r.fecha_vencimiento);
    const esSaldo    = r.origen === 'SALDO_INICIAL';
    const esRecibo   = r.origen === 'RECIBO';
    let origenBadge;
    if (esSaldo) {
        origenBadge = `<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 small px-2" title="Saldo inicial de apertura">Saldo inicial</span>`;
    } else if (esRecibo) {
        origenBadge = `<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 small px-2" title="Recibo de venta (comprobante interno)">Recibo</span>`;
    } else {
        origenBadge = `<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 small px-2">Factura</span>`;
    }

    return `
        <tr class="${rowClass}" style="cursor:pointer;" title="Clic para ver el detalle" data-id="${r.id}" data-origen="${r.origen}" data-cliente="${esc(r.cliente_nombre)}" data-factura="${esc(r.numero_factura)}">
            <td class="text-center p-1">
                <input class="form-check-input cxc-chk" type="checkbox" value="${key}"
                       ${esSaldo ? 'disabled' : (selec ? 'checked' : '')}
                       onchange="CXC_toggleSeleccion('${key}', this.checked)">
            </td>
            <td class="ps-2 fw-semibold text-truncate" title="${esc(r.numero_factura)}" style="font-size:.8rem;white-space:nowrap;">${esc(r.numero_factura)}</td>
            <td class="text-center" style="white-space:nowrap;">${origenBadge}</td>
            <td class="text-truncate" title="${esc(r.cliente_nombre)}" style="font-size:.8rem;">${esc(r.cliente_nombre)}</td>
            <td style="font-size:.78rem;white-space:nowrap;">${fEmision}</td>
            <td style="font-size:.78rem;white-space:nowrap;">${fVenc}</td>
            <td class="text-end" style="font-size:.78rem;white-space:nowrap;">$${CXC_fmt(r.total)}</td>
            <td class="text-end text-success" style="font-size:.78rem;white-space:nowrap;">$${CXC_fmt(CXC_totalCobrado(r))}</td>
            <td class="text-end fw-bold pe-3" style="font-size:.82rem;white-space:nowrap;color:${saldo > 0 ? '#dc3545' : '#198754'};">$${CXC_fmt(saldo)}</td>
            <td class="text-center" style="overflow:hidden;white-space:nowrap;">${badgeHtml}</td>
            <td class="text-center">
                <div class="d-flex justify-content-center gap-1">
                    ${saldo > 0 ? `
                    <button class="btn btn-success btn-sm py-0 px-2" style="font-size:.72rem;" title="Registrar cobro"
                            onclick="CXC_abrirModalCobro(${r.id}, '${r.origen}')">
                        <i class="bi bi-cash-coin"></i>
                    </button>` : ''}
                    <button class="btn btn-outline-primary btn-sm py-0 px-2" style="font-size:.72rem;" title="Ver historial de cobros"
                            onclick="CXC_abrirHistorial(${r.id}, '${esc(r.numero_factura)}', '${r.origen}')">
                        <i class="bi bi-clock-history"></i>
                    </button>
                    ${!esSaldo ? `
                    <button class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:.72rem;" title="Enviar recordatorio email"
                            onclick="CXC_abrirEmail(${r.id}, '${esc(r.numero_factura)}', '${esc(r.cliente_email || '')}', '${esc(r.cliente_nombre)}', '${r.origen}')">
                        <i class="bi bi-envelope"></i>
                    </button>` : ''}
                    ${(!esSaldo && !esRecibo) ? `
                    <button class="btn btn-sm py-0 px-2" style="font-size:.72rem;background:#25d366;color:#fff;" title="Enviar WhatsApp"
                            onclick="CXC_abrirWA(${r.id}, '${esc(r.numero_factura)}', '${esc(r.cliente_telefono || '')}', '${esc(r.cliente_nombre)}')">
                        <i class="bi bi-whatsapp"></i>
                    </button>` : ''}
                </div>
            </td>
        </tr>`;
}

/* ════════════════════════════════════════════════════
   VISTA AGRUPADA POR CLIENTE
════════════════════════════════════════════════════ */
function CXC_renderAgrupado(filas) {
    const tbody = document.getElementById('cxc-tbody');
    const label = document.getElementById('cxc-count-label');

    // Agrupar por cliente (RUC como clave; si falta, por nombre)
    const mapa = new Map();
    for (const r of filas) {
        const key = (r.cliente_ruc && String(r.cliente_ruc).trim()) || r.cliente_nombre || 'Sin cliente';
        let g = mapa.get(key);
        if (!g) {
            g = { key, nombre: r.cliente_nombre || 'Sin cliente', ruc: r.cliente_ruc || '', items: [], total: 0, cobrado: 0, saldo: 0 };
            mapa.set(key, g);
        }
        g.items.push(r);
        g.total   += parseFloat(r.total)         || 0;
        g.cobrado += CXC_totalCobrado(r);
        g.saldo   += parseFloat(r.saldo)         || 0;
    }
    const grupos = [...mapa.values()].sort((a, b) => b.saldo - a.saldo);

    label.textContent = `${filas.length} docs · ${grupos.length} cliente${grupos.length !== 1 ? 's' : ''}`;

    let html = '';
    for (const g of grupos) {
        const abierto = CXC_gruposAbiertos.has(g.key);
        const chev = abierto ? 'bi-chevron-down' : 'bi-chevron-right';
        html += `
        <tr class="cxc-grp-row" data-gkey="${esc(g.key)}" onclick="CXC_toggleGrupo(this)" style="cursor:pointer;background:#eafaf1;">
            <td class="text-center p-1"><i class="bi ${chev} text-success"></i></td>
            <td colspan="5" class="fw-bold" style="font-size:.82rem;">
                ${esc(g.nombre)}
                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 ms-2 fw-normal">${g.items.length} doc${g.items.length !== 1 ? 's' : ''}</span>
            </td>
            <td class="text-end fw-semibold" style="font-size:.8rem;">$${CXC_fmt(g.total)}</td>
            <td class="text-end fw-semibold text-success" style="font-size:.8rem;">$${CXC_fmt(g.cobrado)}</td>
            <td class="text-end fw-bold pe-3" style="font-size:.82rem;color:${g.saldo > 0 ? '#dc3545' : '#198754'};">$${CXC_fmt(g.saldo)}</td>
            <td colspan="2"></td>
        </tr>`;
        if (abierto) {
            for (const r of g.items) html += CXC_filaHtml(r);
        }
    }
    tbody.innerHTML = html;
}

function CXC_toggleGrupo(el) {
    const k = el.getAttribute('data-gkey');
    if (CXC_gruposAbiertos.has(k)) CXC_gruposAbiertos.delete(k);
    else CXC_gruposAbiertos.add(k);
    CXC_renderTabla(CXC_filtradoLocal);
}

/* Cambia entre vista detallada y agrupada (llamado desde los botones de la vista). */
function CXC_setVista(modo) {
    CXC_agrupado = (modo === 'agrupado');
    const bDet = document.getElementById('cxc-btn-detalle');
    const bGrp = document.getElementById('cxc-btn-agrupado');
    if (bDet && bGrp) {
        bDet.classList.toggle('btn-success',         !CXC_agrupado);
        bDet.classList.toggle('btn-outline-success',  CXC_agrupado);
        bGrp.classList.toggle('btn-success',          CXC_agrupado);
        bGrp.classList.toggle('btn-outline-success', !CXC_agrupado);
    }
    CXC_renderTabla(CXC_filtradoLocal);
}

/* ════════════════════════════════════════════════════
   FILTRO LOCAL
════════════════════════════════════════════════════ */
function CXC_filtrarTabla(q) {
    if (!q) {
        CXC_filtradoLocal = [...CXC_datos];
    } else {
        const l = q.toLowerCase();
        CXC_filtradoLocal = CXC_datos.filter(r =>
            (r.numero_factura  || '').toLowerCase().includes(l) ||
            (r.cliente_nombre  || '').toLowerCase().includes(l) ||
            (r.cliente_ruc     || '').toLowerCase().includes(l)
        );
    }
    CXC_renderTabla(CXC_filtradoLocal);
}

/* ════════════════════════════════════════════════════
   SELECCIÓN
════════════════════════════════════════════════════ */
function CXC_toggleSeleccion(key, sel) {
    sel ? CXC_seleccionados.add(key) : CXC_seleccionados.delete(key);
}

function CXC_seleccionarTodos(sel) {
    // Facturas y recibos: los saldos iniciales no tienen email/WhatsApp
    CXC_filtradoLocal.forEach(r => {
        if (r.origen === 'SALDO_INICIAL') return;
        const key = CXC_keyFila(r);
        sel ? CXC_seleccionados.add(key) : CXC_seleccionados.delete(key);
    });
    document.querySelectorAll('.cxc-chk:not([disabled])').forEach(c => c.checked = sel);
}

/* ════════════════════════════════════════════════════
   MODAL COBRO
════════════════════════════════════════════════════ */
async function CXC_abrirModalCobro(idVenta, origen = 'FACTURA') {
    CXC_cobroOrigen = origen;
    let f;
    if (origen === 'SALDO_INICIAL') {
        // Saldo inicial: tomar datos de la fila ya cargada (no hay endpoint de factura)
        const fila = CXC_datos.find(r => r.id == idVenta && r.origen === 'SALDO_INICIAL');
        if (!fila) return;
        f = { numero_factura: fila.numero_factura, cliente_nombre: fila.cliente_nombre,
              importe_total: fila.total, total_cobrado: fila.total_cobrado,
              total_retenido: fila.total_retenido || 0, total_nc: 0, total_nd: 0, saldo: fila.saldo };
    } else {
        // Factura o recibo: obtener datos en tiempo real del servidor
        const infoUrl = origen === 'RECIBO'
            ? `${BASE_URL}/${RUTA_MODULO_CXC}/getReciboParaCobroInfoAjax?id_recibo=${idVenta}`
            : `${BASE_URL}/${RUTA_MODULO_CXC}/getFacturaParaCobroInfoAjax?id_venta=${idVenta}`;
        try {
            const resp = await fetch(infoUrl);
            const data = await resp.json();
            if (!data.ok) { alert(data.error || 'Error al cargar el documento.'); return; }
            f = data.factura;
        } catch(e) {
            const fila = CXC_datos.find(r => r.id == idVenta && r.origen === origen);
            if (!fila) return;
            f = { numero_factura: fila.numero_factura, cliente_nombre: fila.cliente_nombre,
                  importe_total: fila.total, total_cobrado: fila.total_cobrado,
                  total_retenido: fila.total_retenido || 0, total_nc: fila.total_nc || 0,
                  total_nd: fila.total_nd || 0, saldo: fila.saldo };
        }
    }

    // Etiqueta del documento en la tarjeta informativa del modal
    const lblDoc = document.getElementById('cobro-doc-label');
    if (lblDoc) lblDoc.textContent = origen === 'RECIBO' ? 'Recibo' : (origen === 'SALDO_INICIAL' ? 'Saldo inicial' : 'Factura');

    const saldo = Math.max(0, parseFloat(f.saldo));

    // Info factura
    document.getElementById('cobro-id-venta').value         = idVenta;
    document.getElementById('cobro-nro-factura').textContent = f.numero_factura;
    document.getElementById('cobro-cliente').textContent     = f.cliente_nombre;
    document.getElementById('cobro-total-fact').textContent  = CXC_fmt(f.importe_total);
    document.getElementById('cobro-ya-cobrado').textContent  = CXC_fmt(f.total_cobrado);
    document.getElementById('cobro-retenido').textContent    = CXC_fmt(f.total_retenido || 0);
    document.getElementById('cobro-nc').textContent          = CXC_fmt(f.total_nc || 0);
    document.getElementById('cobro-nd').textContent          = CXC_fmt(f.total_nd || 0);
    document.getElementById('cobro-saldo-pend').textContent  = CXC_fmt(saldo);

    // Monto y fecha
    const elMonto = document.getElementById('cobro-monto');
    elMonto.value = saldo.toFixed(2);
    elMonto.max   = saldo.toFixed(2);
    document.getElementById('cobro-fecha').value         = CMG_fechaLocal();
    document.getElementById('cobro-observaciones').value = '';

    // ── Serie (puntos de emisión) ──────────────────────────────────────────
    const selPunto = document.getElementById('cobro-punto-emision');
    const pts = CXC_catalogos.puntos;
    selPunto.innerHTML = '<option value="">— Seleccione —</option>'
        + pts.map(p => `<option value="${p.id_punto}">${p.cod_establecimiento}-${p.codigo_punto}</option>`).join('');
    if (pts.length === 1) {
        selPunto.selectedIndex = 1;
        CXC_cargarSecuencial(pts[0].id_punto);
    } else {
        document.getElementById('cobro-secuencial').value = '';
    }

    // ── Concepto (solo lectura, auto-seleccionado) ─────────────────────────
    const selConc = document.getElementById('cobro-concepto');
    const cons = CXC_catalogos.conceptos;
    selConc.innerHTML = cons.length
        ? cons.map(c => `<option value="${c.id}">${c.nombre}</option>`).join('')
        : '<option value="">Sin conceptos configurados</option>';

    let cDef = cons.find(c => c.comportamiento === 'FACTURA_VENTA' || c.comportamiento === 'COBRO_FACTURA');
    if (!cDef) cDef = cons.find(c => {
        const n = (c.nombre || '').toLowerCase();
        return n.includes('cobro') || n.includes('factura') || n.includes('venta');
    });
    if (cDef) {
        selConc.value = cDef.id;
        selConc.style.pointerEvents = 'none';
        selConc.style.cursor        = 'default';
        selConc.tabIndex            = -1;
        selConc.classList.add('bg-light');
    } else {
        selConc.style.pointerEvents = '';
        selConc.style.cursor        = '';
        selConc.tabIndex            = 0;
        selConc.classList.remove('bg-light');
    }

    // ── Formas de cobro ────────────────────────────────────────────────────
    const selForma = document.getElementById('cobro-forma');
    const fps = CXC_catalogos.formas;
    selForma.innerHTML = fps.length
        ? fps.map(f => `<option value="${f.id}" data-tipo="${(f.tipo||'').toUpperCase()}">${f.nombre}</option>`).join('')
        : '<option value="">Sin formas de cobro configuradas</option>';
    if (fps.length === 1) selForma.selectedIndex = 0;

    // Resetear bloque banco
    CXC_toggleBancoDatos(selForma.value);
    const elTipoOp = document.getElementById('cobro-tipo-op');
    const elNumOp  = document.getElementById('cobro-num-op');
    if (elTipoOp) elTipoOp.value = 'TRANSFERENCIA';
    if (elNumOp)  elNumOp.value  = '';

    new bootstrap.Modal(document.getElementById('modalCobro')).show();
}

async function CXC_cargarSecuencial(idPunto) {
    const el = document.getElementById('cobro-secuencial');
    if (!el) return;
    if (!idPunto) { el.value = ''; return; }
    el.value = '…';
    try {
        const r = await fetch(
            `${BASE_URL}/${RUTA_MODULO_CXC}/getSecuencialAjax?id_punto_emision=${idPunto}`,
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
        );
        const data = await r.json();
        if (data.ok) {
            el.value = data.formateado || String(data.secuencial).padStart(9, '0');
            el.classList.toggle('border-warning', !!data.es_gap);
            el.classList.toggle('text-warning',   !!data.es_gap);
        } else {
            el.value = '—';
        }
    } catch {
        el.value = '—';
    }
}

/**
 * Muestra u oculta el bloque de datos bancarios según el tipo de forma de cobro seleccionada.
 */
function CXC_toggleBancoDatos(idForma) {
    const divBanco = document.getElementById('cobro-div-banco');
    if (!divBanco) return;
    const fp   = CXC_formasCobro.find(f => f.id == idForma);
    const tipo = fp ? (fp.tipo || '').toUpperCase() : '';
    if (tipo === 'BANCO') {
        divBanco.classList.remove('d-none');
    } else {
        divBanco.classList.add('d-none');
    }
}

async function CXC_guardarCobro() {
    const idVenta  = document.getElementById('cobro-id-venta').value;
    const idPunto  = document.getElementById('cobro-punto-emision').value;
    const concepto = document.getElementById('cobro-concepto').value;
    const monto    = parseFloat(document.getElementById('cobro-monto').value);
    const forma    = document.getElementById('cobro-forma').value;
    const fecha    = document.getElementById('cobro-fecha').value;
    const obs      = document.getElementById('cobro-observaciones').value;

    if (!idPunto)              { CXC_toast('Seleccione la serie (punto de emisión).', 'warning'); return; }
    if (!monto || monto <= 0)  { CXC_toast('Ingrese un monto válido.', 'warning'); return; }
    if (!forma)                { CXC_toast('Seleccione una forma de cobro.', 'warning'); return; }
    if (!fecha)                { CXC_toast('Seleccione la fecha de cobro.', 'warning'); return; }

    const btn = document.getElementById('btn-guardar-cobro');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Registrando…';

    try {
        const esSaldo  = CXC_cobroOrigen === 'SALDO_INICIAL';
        const esRecibo = CXC_cobroOrigen === 'RECIBO';
        const fd = new FormData();
        fd.append(esSaldo ? 'id_saldo' : (esRecibo ? 'id_recibo' : 'id_venta'), idVenta);
        fd.append('id_punto_emision',   idPunto);
        fd.append('id_ingreso_concepto',concepto);
        fd.append('monto',              monto);
        fd.append('id_forma_cobro',     forma);
        fd.append('fecha_cobro',        fecha);
        fd.append('observaciones',      obs);

        // Datos bancarios si el bloque está visible
        const divBanco = document.getElementById('cobro-div-banco');
        if (divBanco && !divBanco.classList.contains('d-none')) {
            fd.append('tipo_operacion_bancaria', document.getElementById('cobro-tipo-op')?.value || '');
            fd.append('numero_operacion',        document.getElementById('cobro-num-op')?.value  || '');
        }

        const endpoint = esSaldo ? 'registrarCobroSaldoInicialAjax'
                       : (esRecibo ? 'registrarCobroReciboAjax' : 'registrarCobroAjax');
        const r = await fetch(`${BASE_URL}/${RUTA_MODULO_CXC}/${endpoint}`, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        });
        const data = await r.json();

        if (data.ok) {
            bootstrap.Modal.getInstance(document.getElementById('modalCobro')).hide();
            CXC_toast(data.mensaje || 'Cobro registrado.', 'success');
            await CXC_cargar();
        } else {
            CXC_toast(data.error || 'Error al registrar.', 'danger');
        }
    } catch (e) {
        CXC_toast('Error de conexión.', 'danger');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Registrar Cobro';
    }
}

/* ════════════════════════════════════════════════════
   MODAL HISTORIAL
════════════════════════════════════════════════════ */
async function CXC_abrirHistorial(idVenta, nroFactura, origen = 'FACTURA') {
    const esSaldo  = origen === 'SALDO_INICIAL';
    const esRecibo = origen === 'RECIBO';
    const prefijo  = esSaldo ? 'Saldo inicial: ' : (esRecibo ? 'Recibo: ' : 'Factura: ');
    document.getElementById('historial-subtitulo').textContent = prefijo + nroFactura;
    document.getElementById('historial-tbody').innerHTML = '<tr><td colspan="6" class="text-center text-muted">Cargando…</td></tr>';
    document.getElementById('historial-total').textContent = '0.00';

    new bootstrap.Modal(document.getElementById('modalHistorial')).show();

    try {
        const url = esSaldo
            ? `${BASE_URL}/${RUTA_MODULO_CXC}/historialCobrosSaldoInicialAjax?id_saldo=${idVenta}`
            : (esRecibo
                ? `${BASE_URL}/${RUTA_MODULO_CXC}/historialCobrosReciboAjax?id_recibo=${idVenta}`
                : `${BASE_URL}/${RUTA_MODULO_CXC}/historialCobrosAjax?id_venta=${idVenta}`);
        const r = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await r.json();

        if (!data.ok) {
            document.getElementById('historial-tbody').innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error al cargar</td></tr>';
            return;
        }

        const h = data.historial || [];
        if (!h.length) {
            document.getElementById('historial-tbody').innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No hay cobros registrados.</td></tr>';
            return;
        }

        let total = 0;
        let html  = '';
        for (const c of h) {
            const m = parseFloat(c.monto_cobrado);
            total += m;
            html += `<tr>
                <td style="font-size:.8rem;">${CXC_fmtFechaHora(c.fecha_emision)}</td>
                <td style="font-size:.8rem;">${esc(c.numero_ingreso || '')}</td>
                <td style="font-size:.8rem;">${esc(c.forma_cobro || '—')}</td>
                <td style="font-size:.8rem;">${esc(c.usuario_nombre || '—')}</td>
                <td class="text-end fw-semibold text-success" style="font-size:.8rem;">$${CXC_fmt(m)}</td>
                <td style="font-size:.78rem;">${esc(c.observaciones || '')}</td>
            </tr>`;
        }
        document.getElementById('historial-tbody').innerHTML = html;
        document.getElementById('historial-total').textContent = CXC_fmt(total);
    } catch (e) {
        document.getElementById('historial-tbody').innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error de conexión</td></tr>';
    }
}

/* ════════════════════════════════════════════════════
   MODAL EMAIL
════════════════════════════════════════════════════ */
let CXC_emailOrigen = 'FACTURA'; // origen del documento en el modal de email

function CXC_abrirEmail(idVenta, nroFactura, email, clienteNombre, origen = 'FACTURA') {
    CXC_emailOrigen = origen;
    const prefijo = origen === 'RECIBO' ? 'Recibo' : 'Factura';
    document.getElementById('email-id-venta').value        = idVenta;
    document.getElementById('email-subtitulo').textContent = `${prefijo}: ${nroFactura} — ${clienteNombre}`;
    document.getElementById('email-destino').value         = email || '';
    document.getElementById('email-asunto').value          = '';
    document.getElementById('email-mensaje').value         = '';
    new bootstrap.Modal(document.getElementById('modalEmail')).show();
}

async function CXC_enviarEmail() {
    const idVenta = document.getElementById('email-id-venta').value;
    const email   = document.getElementById('email-destino').value.trim();
    const asunto  = document.getElementById('email-asunto').value.trim();
    const msg     = document.getElementById('email-mensaje').value.trim();

    if (!email) { CXC_toast('Ingrese el correo destinatario.', 'warning'); return; }

    const fd = new FormData();
    fd.append('id_venta', idVenta);
    fd.append('origen',   CXC_emailOrigen);
    fd.append('email',    email);
    fd.append('asunto',   asunto);
    fd.append('mensaje',  msg);

    try {
        const r = await fetch(`${BASE_URL}/${RUTA_MODULO_CXC}/enviarEmailAjax`, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        });
        const data = await r.json();
        if (data.ok) {
            bootstrap.Modal.getInstance(document.getElementById('modalEmail')).hide();
            CXC_toast(data.mensaje || 'Correo enviado.', 'success');
        } else {
            CXC_toast(data.error || 'Error al enviar.', 'danger');
        }
    } catch (e) {
        CXC_toast('Error de conexión.', 'danger');
    }
}

/* ════════════════════════════════════════════════════
   ENVÍO MASIVO EMAIL
════════════════════════════════════════════════════ */
let CXC_masivoGrupos = []; // grupos por cliente del modal de envío masivo

/* Abre el modal de revisión: un grupo por cliente con su correo editable.
   El envío real ocurre en CXC_confirmarEnvioMasivo(). */
function CXC_envioMasivoEmail() {
    const keys = [...CXC_seleccionados];
    if (!keys.length) {
        CXC_toast('Seleccione al menos un documento.', 'warning');
        return;
    }
    const filas = CXC_datos.filter(r => keys.includes(CXC_keyFila(r)));
    if (!filas.length) {
        CXC_toast('Seleccione al menos un documento.', 'warning');
        return;
    }

    // Agrupar por cliente: se envía UN correo por cliente con el resumen
    // de todos sus documentos seleccionados (facturas y recibos).
    const mapa = new Map();
    for (const r of filas) {
        const idCli = parseInt(r.id_cliente) || 0;
        const k = idCli || ('r:' + (r.cliente_ruc || r.cliente_nombre || '?'));
        let g = mapa.get(k);
        if (!g) {
            g = { idCliente: idCli, nombre: r.cliente_nombre || 'Sin nombre', ruc: r.cliente_ruc || '',
                  email: r.cliente_email || '', numDocs: 0, saldo: 0, documentos: [] };
            mapa.set(k, g);
        }
        if (!g.email && r.cliente_email) g.email = r.cliente_email;
        g.numDocs++;
        g.saldo += parseFloat(r.saldo) || 0;
        g.documentos.push({ origen: r.origen || 'FACTURA', id: r.id });
    }
    CXC_masivoGrupos = [...mapa.values()].sort((a, b) => b.saldo - a.saldo);

    const tbody = document.getElementById('em-masivo-tbody');
    tbody.innerHTML = CXC_masivoGrupos.map((g, i) => `
        <tr>
            <td class="ps-2" style="font-size:.82rem;">
                <div class="fw-semibold text-truncate" style="max-width:240px;" title="${esc(g.nombre)}">${esc(g.nombre)}</div>
                <div class="text-muted" style="font-size:.7rem;">${esc(g.ruc)}</div>
            </td>
            <td class="text-center" style="font-size:.8rem;">${g.numDocs}</td>
            <td class="text-end fw-semibold" style="font-size:.8rem;color:#dc3545;">$${CXC_fmt(g.saldo)}</td>
            <td class="pe-2 py-1">
                <input type="text" class="form-control form-control-sm shadow-none em-masivo-correo ${g.email ? '' : 'border-warning'}"
                       data-idx="${i}" value="${esc(g.email)}" placeholder="Sin correo — se omite"
                       title="Varios destinatarios separados por coma"
                       oninput="this.classList.remove('is-invalid')">
            </td>
        </tr>`).join('');

    document.getElementById('em-masivo-resumen').innerHTML =
        `<strong>${filas.length}</strong> documento(s) de <strong>${CXC_masivoGrupos.length}</strong> cliente(s). ` +
        `Se enviará <strong>un correo por cliente</strong> con la tabla resumen de sus documentos y el total pendiente.`;

    new bootstrap.Modal(document.getElementById('modalEmailMasivo')).show();
}

function CXC_emailValido(v) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
}

/* Valida los correos del modal y dispara el envío agrupado. */
async function CXC_confirmarEnvioMasivo() {
    const inputs = document.querySelectorAll('#em-masivo-tbody .em-masivo-correo');
    const correos = {};      // id_cliente -> correos editados (para este envío)
    let documentos = [];
    let conCorreo = 0, invalidos = 0, omitidos = 0;

    inputs.forEach(inp => {
        const g = CXC_masivoGrupos[parseInt(inp.dataset.idx)];
        if (!g) return;
        const val = inp.value.trim();
        if (!val) { omitidos++; return; }

        const partes = val.split(/[\s,;]+/).filter(Boolean);
        if (!partes.every(CXC_emailValido)) {
            inp.classList.add('is-invalid');
            invalidos++;
            return;
        }
        conCorreo++;
        if (g.idCliente > 0) correos[g.idCliente] = partes.join(',');
        documentos = documentos.concat(g.documentos);
    });

    if (invalidos) {
        CXC_toast(`Hay ${invalidos} correo(s) inválido(s). Corríjalos o déjelos vacíos para omitir al cliente.`, 'warning');
        return;
    }
    if (!conCorreo) {
        CXC_toast('Ningún cliente tiene correo. Complete al menos uno para enviar.', 'warning');
        return;
    }

    const btn = document.getElementById('btn-enviar-masivo');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Enviando…';

    try {
        const fd = new FormData();
        fd.append('documentos', JSON.stringify(documentos));
        fd.append('correos',    JSON.stringify(correos));

        const r = await fetch(`${BASE_URL}/${RUTA_MODULO_CXC}/enviarEmailMasivoAjax`, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        });
        const d = await r.json();

        if (d.ok) {
            bootstrap.Modal.getInstance(document.getElementById('modalEmailMasivo'))?.hide();
            let mensaje = d.mensaje || 'Correos enviados.';
            if (omitidos) mensaje += ` ${omitidos} cliente(s) omitido(s) por no tener correo.`;
            const hayAvisos = omitidos + (d.sin_email || 0) + (d.con_error || 0) + (d.no_encontrados || 0) > 0;
            CXC_toast(mensaje, hayAvisos ? 'warning' : 'success');
        } else {
            CXC_toast(d.error || 'Error al enviar.', 'danger');
        }
    } catch (e) {
        CXC_toast('Error de conexión.', 'danger');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send me-1"></i>Enviar';
    }
}

/* ════════════════════════════════════════════════════
   MODAL WHATSAPP
════════════════════════════════════════════════════ */
function CXC_abrirWA(idVenta, nroFactura, telefono, clienteNombre) {
    if (!CXC_TIENE_WA) {
        CXC_toast('WhatsApp no está configurado para esta empresa. Active el módulo de WhatsApp para usar esta función.', 'warning');
        return;
    }

    document.getElementById('wa-id-venta').value         = idVenta;
    document.getElementById('wa-subtitulo').textContent  = `Factura: ${nroFactura} — ${clienteNombre}`;
    let cleanTel = (telefono || '').replace(/[^0-9]/g, '');
    if (cleanTel.startsWith('0')) cleanTel = cleanTel.substring(1);
    if (!cleanTel.startsWith('593')) cleanTel = '593' + cleanTel;
    document.getElementById('wa-telefono').value = cleanTel;

    // Llenar plantillas
    const sel = document.getElementById('wa-plantilla');
    sel.innerHTML = '<option value="">Seleccione una plantilla aprobada…</option>';
    CXC_plantillasWA.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.nombre; // Solo enviamos el nombre
        opt.textContent = `${p.nombre} (${p.idioma})`;
        sel.appendChild(opt);
    });

    if (typeof window.aplicarFavoritosModal === 'function') {
        window.aplicarFavoritosModal('#modalWA');
    }

    // Preseleccionar favorito
    if (typeof APP_FAVORITOS !== 'undefined' && APP_FAVORITOS['wa_plantilla_default']) {
        sel.value = APP_FAVORITOS['wa_plantilla_default'];
    }

    new bootstrap.Modal(document.getElementById('modalWA')).show();
}

async function CXC_enviarWA() {
    const idVenta  = document.getElementById('wa-id-venta').value;
    const telefono = document.getElementById('wa-telefono').value.replace(/[^0-9]/g,'');
    const templateName = document.getElementById('wa-plantilla').value;

    if (!telefono || telefono.length < 7) { CXC_toast('Ingrese un número válido.', 'warning'); return; }
    if (!templateName)                    { CXC_toast('Seleccione una plantilla.', 'warning'); return; }

    const fd = new FormData();
    fd.append('id_venta',       idVenta);
    fd.append('telefono',       telefono);
    fd.append('template_name',  templateName);

    // Aviso "Enviando…" mientras la API de WhatsApp responde (puede tardar unos
    // segundos); el usuario ve que el envío está en curso.
    Swal.fire({
        title: 'Enviando mensaje…',
        html: 'Contactando a WhatsApp. Por favor espera.',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => Swal.showLoading()
    });

    try {
        const r = await fetch(`${BASE_URL}/${RUTA_MODULO_CXC}/enviarWhatsappAjax`, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        });
        const data = await r.json();
        Swal.close();
        if (data.ok) {
            bootstrap.Modal.getInstance(document.getElementById('modalWA')).hide();
            CXC_toast(data.mensaje || 'WhatsApp enviado.', 'success');
        } else {
            CXC_toast(data.error || 'Error al enviar.', 'danger');
        }
    } catch (e) {
        Swal.close();
        CXC_toast('Error de conexión.', 'danger');
    }
}

/* ════════════════════════════════════════════════════
   CARGA DE CATÁLOGOS
════════════════════════════════════════════════════ */
async function CXC_cargarCatalogos() {
    try {
        const r = await fetch(`${BASE_URL}/${RUTA_MODULO_CXC}/getCatalogosCobroAjax`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await r.json();
        if (data.ok) {
            CXC_catalogos.puntos    = data.puntos    || [];
            CXC_catalogos.conceptos = data.conceptos || [];
            CXC_catalogos.formas    = data.formas    || [];
            CXC_formasCobro         = CXC_catalogos.formas; // alias para toggleBancoDatos
            CXC_catalogosCargados   = true;
        }
    } catch (e) {
        console.warn('[CxC] Error cargando catálogos:', e);
    }
}

async function CXC_cargarPlantillasWA() {
    try {
        const r = await fetch(`${BASE_URL}/${RUTA_MODULO_CXC}/getPlantillasWAAjax`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await r.json();
        if (data.ok) CXC_plantillasWA = data.plantillas || [];
    } catch {}
}

/* ════════════════════════════════════════════════════
   BUSCADOR DE CLIENTES (PREDICTIVO)
════════════════════════════════════════════════════ */
let CXC_clientesSeleccionados = [];

function CXC_initBuscadorClientes() {
    const input = document.getElementById('cxc-search-cliente');
    const drop  = document.getElementById('cxc-dropdown-clientes');
    if (!input) return;

    let timer;
    input.addEventListener('input', () => {
        clearTimeout(timer);
        const q = input.value.trim();
        if (q.length < 2) { drop.classList.add('d-none'); return; }
        timer = setTimeout(() => CXC_buscarClientes(q), 280);
    });

    document.addEventListener('click', e => {
        if (!e.target.closest('#cxc-search-cliente') && !e.target.closest('#cxc-dropdown-clientes')) {
            drop.classList.add('d-none');
        }
    });
}

async function CXC_buscarClientes(q) {
    const drop = document.getElementById('cxc-dropdown-clientes');
    try {
        const r = await fetch(`${BASE_URL}/${RUTA_MODULO_CXC}/getClientesAjax?q=${encodeURIComponent(q)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await r.json();
        const list = data.clientes || [];
        if (!list.length) { drop.innerHTML = '<div class="list-group-item text-muted small py-1">Sin resultados</div>'; }
        else {
            drop.innerHTML = list.map(c => `
                <button type="button" class="list-group-item list-group-item-action py-1 small"
                        onclick="CXC_agregarCliente(${c.id}, '${esc(c.nombre)}', '${esc(c.identificacion)}')">
                    <strong>${esc(c.nombre)}</strong> <span class="text-muted">${esc(c.identificacion)}</span>
                </button>`).join('');
        }
        drop.classList.remove('d-none');
    } catch {}
}

function CXC_agregarCliente(id, nombre, ruc) {
    if (CXC_clientesSeleccionados.find(c => c.id === id)) {
        document.getElementById('cxc-dropdown-clientes').classList.add('d-none');
        return;
    }
    CXC_clientesSeleccionados.push({ id, nombre, ruc });
    document.getElementById('cxc-search-cliente').value = '';
    document.getElementById('cxc-dropdown-clientes').classList.add('d-none');
    CXC_renderChipsClientes();
}

function CXC_renderChipsClientes() {
    const cont = document.getElementById('cxc-chips-cliente');
    cont.innerHTML = CXC_clientesSeleccionados.map(c => `
        <span style="display:inline-flex;align-items:center;gap:4px;background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;border-radius:20px;padding:2px 10px;font-size:.78rem;font-weight:500;">
            ${esc(c.nombre)}
            <button type="button" class="btn-close btn-close-sm ms-1" style="font-size:.55rem;"
                    onclick="CXC_quitarCliente(${c.id})"></button>
        </span>`).join('');
}

function CXC_quitarCliente(id) {
    CXC_clientesSeleccionados = CXC_clientesSeleccionados.filter(c => c.id !== id);
    CXC_renderChipsClientes();
}

function CXC_getClientesSeleccionados() {
    return CXC_clientesSeleccionados.map(c => c.id).join(',');
}

/* ════════════════════════════════════════════════════
   LIMPIAR FILTROS
════════════════════════════════════════════════════ */
function CXC_limpiarFiltros() {
    const hoy = new Date();
    const hoyStr = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}-${String(hoy.getDate()).padStart(2, '0')}`;

    document.getElementById('cxc-estado').value      = 'PENDIENTES';
    const selTipoDoc = document.getElementById('cxc-tipo-doc');
    if (selTipoDoc) selTipoDoc.value = 'TODOS';
    document.getElementById('cxc-fecha-desde').value = '';
    document.getElementById('cxc-fecha-hasta').value = hoyStr;
    document.getElementById('cxc-search-cliente').value = '';
    const selVend = document.getElementById('cxc-vendedor');
    if (selVend) selVend.value = '';

    CXC_clientesSeleccionados = [];
    CXC_renderChipsClientes();

    const buscador = document.getElementById('cxc-buscador');
    if (buscador) buscador.value = '';

    CXC_cargar();
}

/* ════════════════════════════════════════════════════
   EXPORTACIONES
════════════════════════════════════════════════════ */
function CXC_exportarExcel() {
    const params = new URLSearchParams({
        estado:      document.getElementById('cxc-estado')?.value      || 'PENDIENTES',
        tipo_doc:    document.getElementById('cxc-tipo-doc')?.value    || 'TODOS',
        fecha_desde: document.getElementById('cxc-fecha-desde')?.value || '',
        fecha_hasta: document.getElementById('cxc-fecha-hasta')?.value || '',
        id_cliente:  CXC_getClientesSeleccionados(),
        id_vendedor: document.getElementById('cxc-vendedor')?.value    || '',
    });
    window.open(`${BASE_URL}/${RUTA_MODULO_CXC}/exportExcel?${params}`, '_blank');
}

function CXC_exportarPDF() {
    const params = new URLSearchParams({
        estado:      document.getElementById('cxc-estado')?.value      || 'PENDIENTES',
        tipo_doc:    document.getElementById('cxc-tipo-doc')?.value    || 'TODOS',
        fecha_desde: document.getElementById('cxc-fecha-desde')?.value || '',
        fecha_hasta: document.getElementById('cxc-fecha-hasta')?.value || '',
        id_cliente:  CXC_getClientesSeleccionados(),
        id_vendedor: document.getElementById('cxc-vendedor')?.value    || '',
    });
    window.open(`${BASE_URL}/${RUTA_MODULO_CXC}/exportPdf?${params}`, '_blank');
}

/* ════════════════════════════════════════════════════
   UTILIDADES
════════════════════════════════════════════════════ */
function CXC_fmt(v) {
    return parseFloat(v || 0).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/* "Cobrado" = abonos en efectivo/banco + retenciones + notas de crédito aplicadas */
function CXC_totalCobrado(r) {
    return (parseFloat(r.total_cobrado) || 0) + (parseFloat(r.total_retenido) || 0) + (parseFloat(r.total_nc) || 0);
}

function CXC_fmtFecha(s) {
    if (!s) return '—';
    const d = new Date(s.replace(' ', 'T').replace(/T.*/, 'T12:00:00'));
    return isNaN(d) ? s : d.toLocaleDateString('es-EC', { day:'2-digit', month:'2-digit', year:'numeric' });
}

function CXC_fmtFechaHora(s) {
    if (!s) return '—';
    try {
        const d = new Date(s.replace(' ', 'T'));
        return d.toLocaleDateString('es-EC', { day:'2-digit', month:'2-digit', year:'numeric' }) +
               ' ' + d.toLocaleTimeString('es-EC', { hour:'2-digit', minute:'2-digit' });
    } catch { return s; }
}

function esc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

function CXC_toast(msg, type = 'info') {
    const map = {
        success : { icon: 'success', title: 'Éxito',       timer: 2500, showConfirmButton: false },
        danger  : { icon: 'error',   title: 'Error',        timer: undefined, showConfirmButton: true },
        warning : { icon: 'warning', title: 'Atención',     timer: undefined, showConfirmButton: true },
        info    : { icon: 'info',    title: 'Información',  timer: 3000, showConfirmButton: false },
    };
    const cfg = map[type] || map.info;
    const opts = { icon: cfg.icon, title: cfg.title, text: msg };
    if (cfg.timer)               opts.timer             = cfg.timer;
    if (!cfg.showConfirmButton)  opts.showConfirmButton = false;
    Swal.fire(opts);
}

/* ════════════════════════════════════════════════════
   PANEL LATERAL: detalle del documento al hacer clic
   en una fila. Delegado en document para sobrevivir a
   los re-render de la tabla (detallada y agrupada).
════════════════════════════════════════════════════ */
document.addEventListener('click', function (e) {
    const tr = e.target.closest('tr[data-origen]');
    if (!tr) return;
    // Los controles de la fila (checkbox, botones de cobro/historial/email/WA)
    // conservan su propia acción.
    if (e.target.closest('button, a, input, select, label')) return;
    if (typeof window.CMG_abrirPreviewDoc !== 'function') return;

    const id     = tr.dataset.id;
    const origen = tr.dataset.origen;
    const r      = CXC_datos.find(x => String(x.id) === String(id) && x.origen === origen) || {};

    window.CMG_abrirPreviewDoc(id, origen, {
        numero:      r.numero_factura || tr.dataset.factura || '',
        fecha:       r.fecha_emision  || '',
        sujetoLabel: 'Cliente',
        sujeto:      r.cliente_nombre || tr.dataset.cliente || '',
        total:       r.total
    });
});
