/**
 * cuentas_por_pagar.js
 * Módulo de Cuentas por Pagar — lógica del cliente
 */

'use strict';

/* ════════════════════════════════════════════════════
   ESTADO GLOBAL
════════════════════════════════════════════════════ */
let CXP_datos         = [];   // filas completas recibidas del servidor
let CXP_filtradoLocal = [];   // filas tras filtro local de texto
let CXP_agingChart    = null;
let CXP_catalogos     = { puntos: [], conceptos: [], formas: [] };
let CXP_catalogosCargados = false;
let CXP_agrupado      = false;          // vista agrupada por proveedor
const CXP_gruposAbiertos = new Set();   // claves de grupos expandidos

/* ════════════════════════════════════════════════════
   INICIALIZACIÓN
════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
    CXP_cargar();
    CXP_cargarCatalogos();
    CXP_initBuscadorProveedores();
});

/* ════════════════════════════════════════════════════
   CARGAR DATOS PRINCIPALES
════════════════════════════════════════════════════ */
async function CXP_cargar() {
    const tbody = document.getElementById('cxp-tbody');
    tbody.innerHTML = `<tr><td colspan="11" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Cargando…</td></tr>`;

    const params = new URLSearchParams({
        accion:      'generarAjax',
        estado:      document.getElementById('cxp-estado')?.value       || 'PENDIENTES',
        tipo_fuente: document.getElementById('cxp-tipo')?.value         || '',
        fecha_desde: document.getElementById('cxp-fecha-desde')?.value  || '',
        fecha_hasta: document.getElementById('cxp-fecha-hasta')?.value  || '',
        id_proveedor:CXP_getProveedoresSeleccionados(),
    });

    try {
        const r = await fetch(`${BASE_URL}/${RUTA_MODULO_CXP}/generarAjax?${params}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await r.json();

        if (!data.ok) {
            tbody.innerHTML = `<tr><td colspan="11" class="text-center py-4 text-danger"><i class="bi bi-exclamation-triangle me-2"></i>${cxpEsc(data.error || 'Error al cargar')}</td></tr>`;
            return;
        }

        CXP_datos = data.filas || [];
        CXP_filtradoLocal = [...CXP_datos];

        CXP_actualizarStats(data.stats || {});
        CXP_dibujarAging(data.antiguedad || {});
        CXP_renderTabla(CXP_filtradoLocal);

    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="11" class="text-center py-4 text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Error de conexión</td></tr>`;
        console.error('[CXP]', e);
    }
}

/* ════════════════════════════════════════════════════
   ESTADÍSTICAS
════════════════════════════════════════════════════ */
function CXP_actualizarStats(s) {
    document.getElementById('cxp-stat-docs').textContent     = s.total_docs     || 0;
    document.getElementById('cxp-stat-saldo').textContent    = CXP_fmt(s.total_saldo   || 0);
    document.getElementById('cxp-stat-vencido').textContent  = CXP_fmt(s.total_vencido || 0);
    document.getElementById('cxp-stat-aldia').textContent    = CXP_fmt(s.total_al_dia  || 0);
    document.getElementById('cxp-stat-dvencidos').textContent= s.docs_vencidos  || 0;
}

/* ════════════════════════════════════════════════════
   GRÁFICO AGING
════════════════════════════════════════════════════ */
function CXP_dibujarAging(ag) {
    const card = document.getElementById('cxp-chart-card');
    const tiene = Object.values(ag).some(v => v > 0);
    card.style.display = tiene ? '' : 'none';
    if (!tiene) return;

    const ctx = document.getElementById('cxpAgingChart').getContext('2d');
    if (CXP_agingChart) CXP_agingChart.destroy();

    CXP_agingChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Vigente', '1–30 días', '31–60 días', '61–90 días', '+90 días'],
            datasets: [{
                label: 'Saldo',
                data: [
                    parseFloat(ag.vigente     || 0),
                    parseFloat(ag.tramo_1_30  || 0),
                    parseFloat(ag.tramo_31_60 || 0),
                    parseFloat(ag.tramo_61_90 || 0),
                    parseFloat(ag.mas_90      || 0),
                ],
                backgroundColor: [
                    'rgba(13,110,253,.55)',
                    'rgba(255,193,7,.65)',
                    'rgba(253,126,20,.65)',
                    'rgba(220,53,69,.65)',
                    'rgba(132,32,41,.75)',
                ],
                borderRadius: 4,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: ctx => ' $' + CXP_fmt(ctx.raw) } }
            },
            scales: { y: { ticks: { callback: v => '$' + CXP_fmt(v) } } }
        }
    });
}

/* ════════════════════════════════════════════════════
   RENDER TABLA
════════════════════════════════════════════════════ */
function CXP_renderTabla(filas) {
    const tbody = document.getElementById('cxp-tbody');
    const label = document.getElementById('cxp-count-label');

    if (!filas.length) {
        label.textContent = '0 registros';
        tbody.innerHTML = `<tr><td colspan="11" class="text-center py-5 text-muted">
            <i class="bi bi-credit-card fs-3 d-block mb-2 text-primary opacity-40"></i>
            No se encontraron cuentas por pagar con los filtros aplicados.
        </td></tr>`;
        return;
    }

    if (CXP_agrupado) { CXP_renderAgrupado(filas); return; }

    label.textContent = filas.length + ' registros';
    let html = '';
    for (const r of filas) html += CXP_filaHtml(r);
    tbody.innerHTML = html;
}

/* Construye una fila <tr> de detalle (11 columnas). Reutilizada por la vista
   detallada y por la vista agrupada (para los documentos dentro de cada proveedor). */
function CXP_filaHtml(r) {
        const dias    = parseInt(r.dias_vencido) || 0;
        const saldo   = parseFloat(r.saldo);
        const nc      = parseFloat(r.total_nc      || 0);
        const nd      = parseFloat(r.total_nd      || 0);
        const ret     = parseFloat(r.total_retenido|| 0);
        const ncRet   = nc + ret - nd;
        const esLiq   = r.tipo_fuente === 'LIQUIDACION';
        const esSaldo = r.tipo_fuente === 'SALDO_INICIAL';
        const pagada  = saldo <= 0.001;

        // ── Badge de estado y color de fila ──
        // Vencido desde el primer día: amarillo 1-30d, rojo 31d+
        let badgeHtml, rowClass = '';
        if (pagada) {
            badgeHtml = `<span class="badge badge-pagada rounded-pill px-2" style="font-size:.68rem;">Pagada</span>`;
            rowClass  = '';
        } else if (dias > 90) {
            badgeHtml = `<span class="badge badge-vencida rounded-pill px-2" style="font-size:.68rem;">+90d vencida</span>`;
            rowClass  = 'table-danger';
        } else if (dias > 30) {
            badgeHtml = `<span class="badge badge-vencida rounded-pill px-2" style="font-size:.68rem;">Vencida ${dias}d</span>`;
            rowClass  = 'table-danger';
        } else if (dias > 0) {
            badgeHtml = `<span class="badge badge-vencida rounded-pill px-2" style="font-size:.68rem;">Vencida ${dias}d</span>`;
            rowClass  = 'table-warning';
        } else {
            badgeHtml = `<span class="badge badge-vigente rounded-pill px-2" style="font-size:.68rem;">Vigente ${-dias}d</span>`;
            rowClass  = '';
        }

        // ── Badge de origen (columna Origen) ──
        const origenBadge = esSaldo
            ? `<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 small px-2" title="Saldo inicial de apertura">Saldo inicial</span>`
            : esLiq
                ? `<span class="badge badge-liquid rounded-pill px-2">Liquidación</span>`
                : `<span class="badge badge-compra rounded-pill px-2">Factura</span>`;

        // ── Fechas ──
        const fEmision     = CXP_fmtFecha(r.fecha_emision);
        const fVencimiento = CXP_fmtFecha(r.fecha_vencimiento);

        // ── Color saldo ──
        const clsSaldo = pagada ? 'cxp-saldo-pagado' : (dias > 0 ? 'cxp-saldo-vencido' : 'cxp-saldo-vigente');

        // ── NC/Ret clickable ──
        const ncRetHtml = ncRet > 0.001
            ? `<a href="#" class="text-decoration-none text-muted" title="Ver detalle NC/Retenciones"
                 onclick="event.preventDefault();CXP_verAjustes(${r.id},'${r.tipo_fuente}',${nc},${nd},${ret})">
                 <small>$${CXP_fmt(ncRet)}</small>
               </a>`
            : `<small class="text-muted">—</small>`;

        return `
        <tr class="${rowClass}" data-id="${r.id}" data-tipo="${cxpEsc(r.tipo_fuente)}"
            data-proveedor="${cxpEsc(r.proveedor_nombre)}" data-doc="${cxpEsc(r.numero_documento)}">

            <!-- Documento -->
            <td class="ps-2" title="${cxpEsc(r.numero_documento)}">
                <span class="fw-semibold" style="font-size:.79rem;">${cxpEsc(r.numero_documento)}</span>
            </td>

            <!-- Origen -->
            <td class="text-center" style="white-space:nowrap;">${origenBadge}</td>

            <!-- Proveedor -->
            <td title="${cxpEsc(r.proveedor_nombre)}" style="font-size:.8rem;">
                ${cxpEsc(r.proveedor_nombre)}
                ${r.proveedor_ruc ? `<br><small class="text-muted" style="font-size:.68rem;">${cxpEsc(r.proveedor_ruc)}</small>` : ''}
            </td>

            <!-- F.Emisión -->
            <td class="text-center text-muted" style="font-size:.77rem;">${fEmision}</td>

            <!-- F.Vencimiento -->
            <td class="text-center" style="font-size:.77rem;">${fVencimiento || '<span class=text-muted>—</span>'}</td>

            <!-- Total -->
            <td class="text-end" style="font-size:.78rem;">$${CXP_fmt(r.total)}</td>

            <!-- Pagado -->
            <td class="text-end text-success" style="font-size:.78rem;">
                ${parseFloat(r.total_pagado) > 0 ? '$' + CXP_fmt(r.total_pagado) : '<span class="text-muted">—</span>'}
            </td>

            <!-- NC/Ret. -->
            <td class="text-end" style="font-size:.78rem;">${ncRetHtml}</td>

            <!-- Saldo -->
            <td class="text-end pe-2 fw-bold ${clsSaldo}" style="font-size:.82rem;">$${CXP_fmt(saldo > 0 ? saldo : 0)}</td>

            <!-- Estado -->
            <td class="text-center">${badgeHtml}</td>

            <!-- Acciones -->
            <td class="text-center">
                <div class="d-flex justify-content-center gap-1">
                    ${!pagada ? `
                    <button class="btn btn-primary btn-sm py-0 px-2" style="font-size:.72rem;" title="Registrar pago"
                            onclick="CXP_abrirModalPago(${r.id}, '${r.tipo_fuente}')">
                        <i class="bi bi-cash-stack"></i>
                    </button>` : ''}
                    <button class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:.72rem;" title="Ver historial de pagos"
                            onclick="CXP_abrirHistorial(${r.id}, '${r.tipo_fuente}', '${cxpEsc(r.numero_documento)}')">
                        <i class="bi bi-clock-history"></i>
                    </button>
                </div>
            </td>
        </tr>`;
}

/* ════════════════════════════════════════════════════
   VISTA AGRUPADA POR PROVEEDOR
════════════════════════════════════════════════════ */
function CXP_renderAgrupado(filas) {
    const tbody = document.getElementById('cxp-tbody');
    const label = document.getElementById('cxp-count-label');

    // Agrupar por proveedor (RUC como clave; si falta, por nombre)
    const mapa = new Map();
    for (const r of filas) {
        const key = (r.proveedor_ruc && String(r.proveedor_ruc).trim()) || r.proveedor_nombre || 'Sin proveedor';
        let g = mapa.get(key);
        if (!g) {
            g = { key, nombre: r.proveedor_nombre || 'Sin proveedor', ruc: r.proveedor_ruc || '', items: [], total: 0, pagado: 0, ncret: 0, saldo: 0 };
            mapa.set(key, g);
        }
        const nc  = parseFloat(r.total_nc       || 0);
        const nd  = parseFloat(r.total_nd       || 0);
        const ret = parseFloat(r.total_retenido || 0);
        g.items.push(r);
        g.total  += parseFloat(r.total)        || 0;
        g.pagado += parseFloat(r.total_pagado) || 0;
        g.ncret  += (nc + ret - nd);
        g.saldo  += parseFloat(r.saldo)        || 0;
    }
    const grupos = [...mapa.values()].sort((a, b) => b.saldo - a.saldo);

    label.textContent = `${filas.length} docs · ${grupos.length} proveedor${grupos.length !== 1 ? 'es' : ''}`;

    let html = '';
    for (const g of grupos) {
        const abierto = CXP_gruposAbiertos.has(g.key);
        const chev = abierto ? 'bi-chevron-down' : 'bi-chevron-right';
        html += `
        <tr class="cxp-grp-row" data-gkey="${cxpEsc(g.key)}" onclick="CXP_toggleGrupo(this)" style="cursor:pointer;background:#eaf1fb;">
            <td colspan="5" class="ps-2 fw-bold" style="font-size:.82rem;">
                <i class="bi ${chev} text-primary me-1"></i>
                ${cxpEsc(g.nombre)}
                ${g.ruc ? `<small class="text-muted fw-normal ms-1">${cxpEsc(g.ruc)}</small>` : ''}
                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 ms-2 fw-normal">${g.items.length} doc${g.items.length !== 1 ? 's' : ''}</span>
            </td>
            <td class="text-end fw-semibold" style="font-size:.8rem;">$${CXP_fmt(g.total)}</td>
            <td class="text-end fw-semibold text-success" style="font-size:.8rem;">${g.pagado > 0 ? '$' + CXP_fmt(g.pagado) : '<span class="text-muted">—</span>'}</td>
            <td class="text-end" style="font-size:.78rem;">${g.ncret > 0.001 ? '$' + CXP_fmt(g.ncret) : '<span class="text-muted">—</span>'}</td>
            <td class="text-end pe-2 fw-bold" style="font-size:.82rem;color:${g.saldo > 0.001 ? '#dc3545' : '#198754'};">$${CXP_fmt(g.saldo > 0 ? g.saldo : 0)}</td>
            <td colspan="2"></td>
        </tr>`;
        if (abierto) {
            for (const r of g.items) html += CXP_filaHtml(r);
        }
    }
    tbody.innerHTML = html;
}

function CXP_toggleGrupo(el) {
    const k = el.getAttribute('data-gkey');
    if (CXP_gruposAbiertos.has(k)) CXP_gruposAbiertos.delete(k);
    else CXP_gruposAbiertos.add(k);
    CXP_renderTabla(CXP_filtradoLocal);
}

/* Cambia entre vista detallada y agrupada (llamado desde los botones de la vista). */
function CXP_setVista(modo) {
    CXP_agrupado = (modo === 'agrupado');
    const bDet = document.getElementById('cxp-btn-detalle');
    const bGrp = document.getElementById('cxp-btn-agrupado');
    if (bDet && bGrp) {
        bDet.classList.toggle('btn-primary',         !CXP_agrupado);
        bDet.classList.toggle('btn-outline-primary',  CXP_agrupado);
        bGrp.classList.toggle('btn-primary',          CXP_agrupado);
        bGrp.classList.toggle('btn-outline-primary', !CXP_agrupado);
    }
    CXP_renderTabla(CXP_filtradoLocal);
}

/* ════════════════════════════════════════════════════
   FILTRO LOCAL
════════════════════════════════════════════════════ */
function CXP_filtrarTabla(q) {
    if (!q) {
        CXP_filtradoLocal = [...CXP_datos];
    } else {
        const l = q.toLowerCase();
        CXP_filtradoLocal = CXP_datos.filter(r =>
            (r.numero_documento  || '').toLowerCase().includes(l) ||
            (r.proveedor_nombre  || '').toLowerCase().includes(l) ||
            (r.proveedor_ruc     || '').toLowerCase().includes(l)
        );
    }
    CXP_renderTabla(CXP_filtradoLocal);
}

/* ════════════════════════════════════════════════════
   CARGAR CATÁLOGOS (puntos, conceptos, formas)
════════════════════════════════════════════════════ */
async function CXP_cargarCatalogos() {
    if (CXP_catalogosCargados) return;
    try {
        const r = await fetch(`${BASE_URL}/${RUTA_MODULO_CXP}/getCatalogosPagoAjax`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await r.json();
        if (data.ok) {
            CXP_catalogos = {
                puntos:    data.puntos    || [],
                conceptos: data.conceptos || [],
                formas:    data.formas    || [],
            };
            CXP_catalogosCargados = true;
        }
    } catch (e) {
        console.error('[CXP catalogos]', e);
    }
}

/* ════════════════════════════════════════════════════
   MODAL PAGO — abrir
════════════════════════════════════════════════════ */
async function CXP_abrirModalPago(idDoc, tipoFuente) {
    let d;
    if (tipoFuente === 'SALDO_INICIAL') {
        // Saldo inicial: tomar datos de la fila ya cargada (no hay endpoint de documento)
        const fila = CXP_datos.find(r => r.id == idDoc && r.tipo_fuente === 'SALDO_INICIAL');
        if (!fila) return;
        d = { numero_documento: fila.numero_documento, proveedor_nombre: fila.proveedor_nombre,
              importe_total: fila.total, total_pagado: fila.total_pagado, tipo_fuente: tipoFuente,
              total_retenido: 0, total_nc: 0, total_nd: 0, saldo: fila.saldo };
    } else {
        // Compra / liquidación: obtener datos en tiempo real del servidor
        try {
            const resp = await fetch(`${BASE_URL}/${RUTA_MODULO_CXP}/getDocumentoParaPagoInfoAjax?id_doc=${idDoc}&tipo_fuente=${tipoFuente}`);
            const data = await resp.json();
            if (!data.ok) { alert(data.error || 'Error al cargar el documento.'); return; }
            d = data.doc;
        } catch(e) {
            const fila = CXP_datos.find(r => r.id == idDoc && r.tipo_fuente == tipoFuente);
            if (!fila) return;
            d = { numero_documento: fila.numero_documento, proveedor_nombre: fila.proveedor_nombre,
                  importe_total: fila.total, total_pagado: fila.total_pagado, tipo_fuente: tipoFuente,
                  total_retenido: fila.total_retenido || 0, total_nc: 0, total_nd: 0, saldo: fila.saldo };
        }
    }

    const saldo  = Math.max(0, parseFloat(d.saldo));
    const pagado = saldo <= 0.001;

    // Campos ocultos
    document.getElementById('pago-id-doc').value      = idDoc;
    document.getElementById('pago-tipo-fuente').value = tipoFuente;

    // Panel de info del documento
    document.getElementById('pago-nro-doc').textContent   = d.numero_documento;
    document.getElementById('pago-proveedor').textContent = d.proveedor_nombre || '';
    document.getElementById('pago-total-doc').textContent = CXP_fmt(d.importe_total);
    document.getElementById('pago-ya-pagado').textContent = CXP_fmt(d.total_pagado);
    document.getElementById('pago-retenido').textContent  = CXP_fmt(d.total_retenido || 0);
    // NC/ND: solo aplica para COMPRA; en LIQUIDACION mostrar 0
    const ncNdVal = parseFloat(d.total_nc || 0) - parseFloat(d.total_nd || 0);
    const lblNcNd = document.getElementById('pago-nc-nd-label');
    if (lblNcNd) lblNcNd.textContent = (d.tipo_fuente === 'LIQUIDACION') ? 'NC/ND' : 'NC - ND';
    document.getElementById('pago-nc-nd').textContent    = CXP_fmt(ncNdVal);
    document.getElementById('pago-saldo-pend').textContent = CXP_fmt(saldo);

    // Mostrar formulario o alerta de pagado
    document.getElementById('pago-form-body').classList.toggle('d-none', pagado);
    document.getElementById('pago-alert-pagada').classList.toggle('d-none', !pagado);
    document.getElementById('btn-guardar-pago').classList.toggle('d-none', pagado);

    if (!pagado) {
        // Resetear campos
        const elMonto = document.getElementById('pago-monto');
        elMonto.value = saldo.toFixed(2);
        elMonto.max   = saldo.toFixed(2);
        document.getElementById('pago-fecha').value         = new Date().toISOString().slice(0, 10);
        document.getElementById('pago-observaciones').value = '';
        document.getElementById('pago-secuencial').value    = '';
        document.getElementById('pago-secuencial').classList.remove('border-warning','text-warning');

        // Serie / punto de emisión
        const selPunto = document.getElementById('pago-punto-emision');
        const pts = CXP_catalogos.puntos;
        selPunto.innerHTML = '<option value="">— Seleccione —</option>'
            + pts.map(p => `<option value="${p.id_punto}">${p.cod_establecimiento}-${p.codigo_punto}</option>`).join('');
        if (pts.length === 1) {
            selPunto.selectedIndex = 1;
            CXP_cargarSecuencial(pts[0].id_punto);
        }

        // Concepto de egreso — filtrado y bloqueo por tipo de documento
        const selConc = document.getElementById('pago-concepto');
        const cons = CXP_catalogos.conceptos;

        // tipo_fuente viene como 'COMPRA' o 'LIQUIDACION' desde el servidor
        const compTipoFuente = (tipoFuente || '').toUpperCase();

        // Filtrar: primero los que coinciden exactamente, luego los GENERAL como opciones adicionales
        const consExactos  = cons.filter(c => (c.comportamiento || '').toUpperCase() === compTipoFuente);
        const consGenerales = cons.filter(c => (c.comportamiento || 'GENERAL').toUpperCase() === 'GENERAL');
        const consVisibles  = consExactos.length > 0 ? consExactos : (consGenerales.length > 0 ? consGenerales : cons);

        selConc.innerHTML = consVisibles.map(c => `<option value="${c.id}">${cxpEsc(c.nombre)}</option>`).join('');

        // Si hay exactamente un concepto que corresponde al tipo → bloquearlo
        if (consVisibles.length === 1) {
            selConc.value    = consVisibles[0].id;
            selConc.disabled = true;
            selConc.title    = `Concepto asignado automáticamente para ${compTipoFuente}`;
        } else {
            selConc.disabled = false;
            selConc.title    = '';
            // Preseleccionar el primero con comportamiento exacto si lo hay
            const cDef = consExactos[0] || consGenerales[0] || null;
            if (cDef) selConc.value = cDef.id;
        }

        // Forma de pago
        const selForma = document.getElementById('pago-forma');
        const fps = CXP_catalogos.formas;
        selForma.innerHTML = fps.length
            ? fps.map(f => `<option value="${f.id}" data-tipo="${(f.tipo||'').toUpperCase()}">${cxpEsc(f.nombre)}</option>`).join('')
            : '<option value="">Sin formas de pago configuradas</option>';

        // Reset campos bancarios
        const elTipoOp = document.getElementById('pago-tipo-op');
        const elNumOp  = document.getElementById('pago-num-op');
        const elFC     = document.getElementById('pago-fecha-cobro');
        const divFC    = document.getElementById('pago-div-fecha-cobro');
        if (elTipoOp) elTipoOp.value = 'TRANSFERENCIA';
        if (elNumOp)  elNumOp.value  = '';
        if (elFC)     elFC.value     = '';
        if (divFC)    divFC.classList.add('d-none');
        CXP_toggleBancoDatos(selForma.value);

        const msgErr = document.getElementById('pago-msg-error');
        if (msgErr) msgErr.classList.add('d-none');
    }

    new bootstrap.Modal(document.getElementById('modalPago')).show();
}

/* Carga el siguiente secuencial de egreso para el punto seleccionado */
async function CXP_cargarSecuencial(idPunto) {
    const el = document.getElementById('pago-secuencial');
    if (!el) return;
    if (!idPunto) { el.value = ''; return; }
    el.value = '…';
    try {
        const r = await fetch(
            `${BASE_URL}/${RUTA_MODULO_CXP}/getSecuencialAjax?id_punto_emision=${idPunto}`,
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

function CXP_toggleBancoDatos(idForma) {
    const divBanco = document.getElementById('pago-div-banco');
    if (!divBanco) return;
    const fp   = CXP_catalogos.formas.find(f => f.id == idForma);
    const tipo = fp ? (fp.tipo || '').toUpperCase() : '';
    const esBanco = tipo === 'BANCO';
    divBanco.classList.toggle('d-none', !esBanco);
    if (esBanco) {
        const sel = document.getElementById('pago-tipo-op');
        if (sel) CXP_toggleTipoOp(sel.value);
    } else {
        // Limpiar campos bancarios al ocultar
        const elNum = document.getElementById('pago-num-op');
        const elFC  = document.getElementById('pago-fecha-cobro');
        if (elNum) elNum.value = '';
        if (elFC)  elFC.value  = '';
        const divFC = document.getElementById('pago-div-fecha-cobro');
        if (divFC) divFC.classList.add('d-none');
    }
}

/* Muestra/oculta campos según tipo de operación bancaria */
function CXP_toggleTipoOp(tipo) {
    const lblNum = document.getElementById('pago-lbl-num-op');
    const elNum  = document.getElementById('pago-num-op');
    const divFC  = document.getElementById('pago-div-fecha-cobro');
    const elFC   = document.getElementById('pago-fecha-cobro');

    if (tipo === 'CHEQUE') {
        if (lblNum) lblNum.textContent = 'Nº Cheque';
        if (elNum)  elNum.placeholder  = 'Nº cheque';
        if (divFC)  divFC.classList.remove('d-none');
        // Pre-llenar fecha cobro si está vacía
        if (elFC && !elFC.value) {
            elFC.value = new Date().toISOString().slice(0, 10);
        }
    } else {
        if (lblNum) lblNum.textContent = 'Nº Referencia';
        if (elNum)  elNum.placeholder  = 'Nº transf / doc';
        if (divFC)  divFC.classList.add('d-none');
        if (elFC)   elFC.value = '';
    }
}

/* ════════════════════════════════════════════════════
   MODAL PAGO — guardar
════════════════════════════════════════════════════ */
async function CXP_guardarPago() {
    const idDoc      = document.getElementById('pago-id-doc').value;
    const tipoFuente = document.getElementById('pago-tipo-fuente').value;
    const idPunto    = document.getElementById('pago-punto-emision').value;
    const concepto   = document.getElementById('pago-concepto').value;
    const monto      = parseFloat(document.getElementById('pago-monto').value);
    const forma      = document.getElementById('pago-forma').value;
    const fecha      = document.getElementById('pago-fecha').value;
    const obs        = document.getElementById('pago-observaciones').value;

    const msgErr = document.getElementById('pago-msg-error');
    const mostrarError = (txt) => {
        if (msgErr) { msgErr.textContent = txt; msgErr.classList.remove('d-none'); }
        else        { CXP_toast(txt, 'warning'); }
    };

    if (!idPunto)             { mostrarError('Seleccione la serie (punto de emisión).'); return; }
    if (!monto || monto <= 0) { mostrarError('Ingrese un monto válido mayor a $0.'); return; }
    if (!forma)               { mostrarError('Seleccione una forma de pago.'); return; }
    if (!fecha)               { mostrarError('Seleccione la fecha de emisión.'); return; }
    if (msgErr) msgErr.classList.add('d-none');

    const btn = document.getElementById('btn-guardar-pago');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Registrando…';

    try {
        const esSaldo = tipoFuente === 'SALDO_INICIAL';
        const fd = new FormData();
        fd.append(esSaldo ? 'id_saldo' : 'id_doc', idDoc);
        fd.append('tipo_fuente',        tipoFuente);
        fd.append('id_punto_emision',   idPunto);
        fd.append('id_egreso_concepto', concepto);
        fd.append('monto',              monto);
        fd.append('id_forma_pago',      forma);
        fd.append('fecha_pago',         fecha);
        fd.append('observaciones',      obs);

        const divBanco = document.getElementById('pago-div-banco');
        if (divBanco && !divBanco.classList.contains('d-none')) {
            const tipoOp = document.getElementById('pago-tipo-op')?.value || '';
            fd.append('tipo_operacion_bancaria', tipoOp);
            fd.append('numero_operacion',        document.getElementById('pago-num-op')?.value  || '');
            // Fecha de cobro solo aplica para cheques
            if (tipoOp === 'CHEQUE') {
                fd.append('fecha_cobro', document.getElementById('pago-fecha-cobro')?.value || '');
            }
        }

        const endpoint = esSaldo ? 'registrarPagoSaldoInicialAjax' : 'registrarPagoAjax';
        const r = await fetch(`${BASE_URL}/${RUTA_MODULO_CXP}/${endpoint}`, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        });
        const data = await r.json();

        if (data.ok) {
            bootstrap.Modal.getInstance(document.getElementById('modalPago')).hide();
            CXP_toast(data.mensaje || 'Pago registrado correctamente.', 'success');
            await CXP_cargar();
        } else {
            mostrarError(data.error || 'Error al registrar el pago.');
        }
    } catch (e) {
        mostrarError('Error de conexión. Intente nuevamente.');
        console.error('[CXP guardarPago]', e);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Registrar Pago y Generar Egreso';
    }
}

/* ════════════════════════════════════════════════════
   MODAL HISTORIAL DE PAGOS
════════════════════════════════════════════════════ */
async function CXP_abrirHistorial(idDoc, tipoFuente, nroDoc) {
    const esSaldo = tipoFuente === 'SALDO_INICIAL';
    document.getElementById('historial-pago-subtitulo').textContent = (esSaldo ? 'Saldo inicial: ' : 'Documento: ') + nroDoc;
    document.getElementById('historial-pagos-tbody').innerHTML =
        '<tr><td colspan="6" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Cargando…</td></tr>';
    document.getElementById('historial-pagos-total').textContent = '0.00';

    new bootstrap.Modal(document.getElementById('modalHistorialPagos')).show();

    try {
        const url = esSaldo
            ? `${BASE_URL}/${RUTA_MODULO_CXP}/historialPagosSaldoInicialAjax?id_saldo=${idDoc}`
            : `${BASE_URL}/${RUTA_MODULO_CXP}/historialPagosAjax?id_doc=${idDoc}&tipo_fuente=${tipoFuente}`;
        const r = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await r.json();

        if (!data.ok) {
            document.getElementById('historial-pagos-tbody').innerHTML =
                '<tr><td colspan="6" class="text-center text-danger py-3">Error al cargar el historial.</td></tr>';
            return;
        }

        const h = data.historial || [];
        if (!h.length) {
            document.getElementById('historial-pagos-tbody').innerHTML =
                '<tr><td colspan="6" class="text-center text-muted py-4">No hay pagos registrados para este documento.</td></tr>';
            return;
        }

        let total = 0, html = '';
        for (const p of h) {
            const m = parseFloat(p.monto_pagado);
            total += m;
            html += `<tr>
                <td class="ps-3">${CXP_fmtFecha(p.fecha_emision)}</td>
                <td class="fw-semibold text-primary">${cxpEsc(p.numero_egreso || '—')}</td>
                <td>${cxpEsc(p.forma_pago || '—')}</td>
                <td>${cxpEsc(p.usuario_nombre || '—')}</td>
                <td class="text-end fw-semibold text-success">$${CXP_fmt(m)}</td>
                <td class="text-muted fst-italic" style="font-size:.78rem;">${cxpEsc(p.observaciones || '')}</td>
            </tr>`;
        }
        document.getElementById('historial-pagos-tbody').innerHTML = html;
        document.getElementById('historial-pagos-total').textContent = CXP_fmt(total);

    } catch (e) {
        document.getElementById('historial-pagos-tbody').innerHTML =
            '<tr><td colspan="6" class="text-center text-danger py-3">Error de conexión.</td></tr>';
    }
}

/* ════════════════════════════════════════════════════
   MODAL AJUSTES (NC / ND / Retenciones)
════════════════════════════════════════════════════ */
function CXP_verAjustes(idDoc, tipoFuente, nc, nd, ret) {
    const body = document.getElementById('ajustes-body');
    let html = '<table class="table table-sm mb-0"><tbody>';
    if (nc > 0)  html += `<tr><td>Nota de Crédito recibida</td><td class="text-end text-success fw-bold">− $${CXP_fmt(nc)}</td></tr>`;
    if (nd > 0)  html += `<tr><td>Nota de Débito recibida</td><td class="text-end text-danger fw-bold">+ $${CXP_fmt(nd)}</td></tr>`;
    if (ret > 0) html += `<tr><td>Retenciones emitidas</td><td class="text-end text-success fw-bold">− $${CXP_fmt(ret)}</td></tr>`;
    const total = nc + ret - nd;
    html += `</tbody><tfoot><tr class="fw-bold"><td>Total ajustes</td><td class="text-end">− $${CXP_fmt(total)}</td></tr></tfoot></table>`;
    html += '<p class="text-muted small mt-2 mb-0">Estos montos ya están descontados del saldo pendiente.</p>';
    body.innerHTML = html;
    new bootstrap.Modal(document.getElementById('modalAjustes')).show();
}

/* ════════════════════════════════════════════════════
   BUSCADOR DE PROVEEDORES (chips)
════════════════════════════════════════════════════ */
let CXP_proveedoresSeleccionados = {}; // { id: nombre }

function CXP_initBuscadorProveedores() {
    const inp = document.getElementById('cxp-search-proveedor');
    const dd  = document.getElementById('cxp-dropdown-proveedores');
    if (!inp || !dd) return;

    let timer;
    inp.addEventListener('input', () => {
        clearTimeout(timer);
        const q = inp.value.trim();
        if (q.length < 2) { dd.classList.add('d-none'); return; }
        timer = setTimeout(() => CXP_buscarProveedores(q), 300);
    });
    document.addEventListener('click', e => {
        if (!inp.contains(e.target) && !dd.contains(e.target)) dd.classList.add('d-none');
    });
}

async function CXP_buscarProveedores(q) {
    const dd = document.getElementById('cxp-dropdown-proveedores');
    try {
        const r = await fetch(`${BASE_URL}/${RUTA_MODULO_CXP}/getProveedoresAjax?q=${encodeURIComponent(q)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await r.json();
        if (!data.ok || !data.proveedores.length) { dd.classList.add('d-none'); return; }

        dd.innerHTML = data.proveedores.map(p =>
            `<button class="list-group-item list-group-item-action py-1 px-2" style="font-size:.82rem;"
                     onclick="CXP_seleccionarProveedor(${p.id}, '${cxpEsc(p.nombre)}')">
                 <strong>${cxpEsc(p.nombre)}</strong><br>
                 <small class="text-muted">${cxpEsc(p.identificacion)}</small>
             </button>`
        ).join('');
        dd.classList.remove('d-none');
    } catch {}
}

function CXP_seleccionarProveedor(id, nombre) {
    CXP_proveedoresSeleccionados[id] = nombre;
    CXP_renderChipsProveedores();
    document.getElementById('cxp-search-proveedor').value = '';
    document.getElementById('cxp-dropdown-proveedores').classList.add('d-none');
    CXP_cargar();
}

function CXP_renderChipsProveedores() {
    const cont = document.getElementById('cxp-chips-proveedor');
    cont.innerHTML = Object.entries(CXP_proveedoresSeleccionados).map(([id, nombre]) =>
        `<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 py-1 px-2 rounded-pill" style="font-size:.75rem;">
            ${cxpEsc(nombre)}
            <i class="bi bi-x ms-1" style="cursor:pointer;" onclick="CXP_quitarProveedor(${id})"></i>
         </span>`
    ).join('');
}

function CXP_quitarProveedor(id) {
    delete CXP_proveedoresSeleccionados[id];
    CXP_renderChipsProveedores();
    CXP_cargar();
}

function CXP_getProveedoresSeleccionados() {
    return Object.keys(CXP_proveedoresSeleccionados).join(',');
}

/* ════════════════════════════════════════════════════
   LIMPIAR FILTROS
════════════════════════════════════════════════════ */
function CXP_limpiarFiltros() {
    const hoy = new Date();
    const hoyStr = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}-${String(hoy.getDate()).padStart(2, '0')}`;

    document.getElementById('cxp-estado').value      = 'PENDIENTES';
    document.getElementById('cxp-tipo').value        = '';
    document.getElementById('cxp-fecha-desde').value = '';
    document.getElementById('cxp-fecha-hasta').value = hoyStr;
    document.getElementById('cxp-search-proveedor').value = '';

    CXP_proveedoresSeleccionados = {};
    CXP_renderChipsProveedores();

    const buscador = document.getElementById('cxp-buscador');
    if (buscador) buscador.value = '';

    CXP_cargar();
}

/* ════════════════════════════════════════════════════
   EXPORTACIÓN
════════════════════════════════════════════════════ */
function CXP_exportarExcel() {
    const params = new URLSearchParams({
        estado:       document.getElementById('cxp-estado')?.value       || 'PENDIENTES',
        tipo_fuente:  document.getElementById('cxp-tipo')?.value         || '',
        fecha_desde:  document.getElementById('cxp-fecha-desde')?.value  || '',
        fecha_hasta:  document.getElementById('cxp-fecha-hasta')?.value  || '',
        id_proveedor: CXP_getProveedoresSeleccionados(),
    });
    window.location.href = `${BASE_URL}/${RUTA_MODULO_CXP}/exportExcel?${params}`;
}

function CXP_exportarPDF() {
    const params = new URLSearchParams({
        estado:       document.getElementById('cxp-estado')?.value       || 'PENDIENTES',
        tipo_fuente:  document.getElementById('cxp-tipo')?.value         || '',
        fecha_desde:  document.getElementById('cxp-fecha-desde')?.value  || '',
        fecha_hasta:  document.getElementById('cxp-fecha-hasta')?.value  || '',
        id_proveedor: CXP_getProveedoresSeleccionados(),
    });
    window.location.href = `${BASE_URL}/${RUTA_MODULO_CXP}/exportPdf?${params}`;
}

/* ════════════════════════════════════════════════════
   UTILIDADES
════════════════════════════════════════════════════ */
function CXP_fmt(v) {
    return parseFloat(v || 0).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function CXP_fmtFecha(str) {
    if (!str) return '—';
    try {
        // Toma solo YYYY-MM-DD para evitar problemas con timezone y formato PostgreSQL "2024-03-15 00:00:00"
        const d = new Date(String(str).substring(0, 10) + 'T00:00:00');
        if (isNaN(d.getTime())) return str;
        return d.toLocaleDateString('es-EC', { day: '2-digit', month: '2-digit', year: 'numeric' });
    } catch { return str; }
}

function CXP_fmtFechaHora(str) {
    if (!str) return '—';
    try {
        // Normaliza "2024-03-15 14:30:00" → "2024-03-15T14:30:00"
        const normalized = String(str).replace(' ', 'T').substring(0, 19);
        const d = new Date(normalized);
        if (isNaN(d.getTime())) return str;
        return d.toLocaleString('es-EC', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    } catch { return str; }
}

function cxpEsc(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function CXP_toast(mensaje, tipo = 'info') {
    if (window.Toast) {
        Toast.fire({ icon: tipo === 'danger' ? 'error' : tipo, title: mensaje });
    } else if (window.Swal) {
        Swal.fire({ toast: true, position: 'top-end', icon: tipo === 'danger' ? 'error' : tipo,
                    title: mensaje, showConfirmButton: false, timer: 3000 });
    }
}
