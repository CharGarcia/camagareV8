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
let CXC_agingChart    = null;
let CXC_formasCobro   = [];
let CXC_plantillasWA  = [];
let CXC_seleccionados = new Set(); // ids de facturas seleccionadas
// Catálogos del modal cobro
let CXC_catalogos = { puntos: [], conceptos: [], formas: [] };
let CXC_catalogosCargados = false;

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
    tbody.innerHTML = `<tr><td colspan="10" class="text-center py-4"><div class="spinner-border spinner-border-sm text-success me-2"></div>Cargando…</td></tr>`;
    CXC_seleccionados.clear();

    const params = new URLSearchParams({
        accion:      'generarAjax',
        estado:      document.getElementById('cxc-estado')?.value       || 'PENDIENTES',
        fecha_desde: document.getElementById('cxc-fecha-desde')?.value  || '',
        fecha_hasta: document.getElementById('cxc-fecha-hasta')?.value  || '',
        id_cliente:  CXC_getClientesSeleccionados(),
    });

    try {
        const r = await fetch(`${BASE_URL}/${RUTA_MODULO_CXC}/generarAjax?${params}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await r.json();

        if (!data.ok) {
            tbody.innerHTML = `<tr><td colspan="10" class="text-center py-4 text-danger"><i class="bi bi-exclamation-triangle me-2"></i>${data.error || 'Error al cargar'}</td></tr>`;
            return;
        }

        CXC_datos = data.filas || [];
        CXC_filtradoLocal = [...CXC_datos];

        CXC_actualizarStats(data.stats || {});
        CXC_dibujarAging(data.antiguedad || {});
        CXC_renderTabla(CXC_filtradoLocal);

    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="10" class="text-center py-4 text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Error de conexión</td></tr>`;
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
   GRÁFICO AGING
════════════════════════════════════════════════════ */
function CXC_dibujarAging(ag) {
    const card = document.getElementById('cxc-chart-card');
    const tiene = Object.values(ag).some(v => v > 0);
    card.style.display = tiene ? '' : 'none';
    if (!tiene) return;

    const ctx = document.getElementById('cxcAgingChart').getContext('2d');
    if (CXC_agingChart) CXC_agingChart.destroy();

    CXC_agingChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Vigente', '1–30 días', '31–60 días', '61–90 días', '+90 días'],
            datasets: [{
                label: 'Saldo',
                data: [
                    parseFloat(ag.vigente    || 0),
                    parseFloat(ag.tramo_1_30 || 0),
                    parseFloat(ag.tramo_31_60|| 0),
                    parseFloat(ag.tramo_61_90|| 0),
                    parseFloat(ag.mas_90     || 0),
                ],
                backgroundColor: [
                    'rgba(25,135,84,.55)',
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
                tooltip: {
                    callbacks: {
                        label: ctx => ' $' + CXC_fmt(ctx.raw)
                    }
                }
            },
            scales: {
                y: { ticks: { callback: v => '$' + CXC_fmt(v) } }
            }
        }
    });
}

/* ════════════════════════════════════════════════════
   RENDER TABLA
════════════════════════════════════════════════════ */
function CXC_renderTabla(filas) {
    const tbody = document.getElementById('cxc-tbody');
    const label = document.getElementById('cxc-count-label');
    label.textContent = filas.length + ' registros';

    if (!filas.length) {
        tbody.innerHTML = `<tr><td colspan="10" class="text-center py-5 text-muted">
            <i class="bi bi-wallet2 fs-3 d-block mb-2 text-success opacity-40"></i>
            No se encontraron cuentas por cobrar con los filtros aplicados.
        </td></tr>`;
        return;
    }

    let html = '';
    for (const r of filas) {
        const dias     = parseInt(r.dias_vencido) || 0;
        const saldo    = parseFloat(r.saldo);
        const selec    = CXC_seleccionados.has(r.id);

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

        html += `
        <tr class="${rowClass}" data-id="${r.id}" data-cliente="${esc(r.cliente_nombre)}" data-factura="${esc(r.numero_factura)}">
            <td class="text-center p-1">
                <input class="form-check-input cxc-chk" type="checkbox" value="${r.id}"
                       ${selec ? 'checked' : ''}
                       onchange="CXC_toggleSeleccion(${r.id}, this.checked)">
            </td>
            <td class="ps-2 fw-semibold text-truncate" title="${esc(r.numero_factura)}" style="font-size:.8rem;">${esc(r.numero_factura)}</td>
            <td class="text-truncate" title="${esc(r.cliente_nombre)}" style="font-size:.8rem;">${esc(r.cliente_nombre)}</td>
            <td style="font-size:.78rem;">${fEmision}</td>
            <td style="font-size:.78rem;">${fVenc}</td>
            <td class="text-end" style="font-size:.78rem;">$${CXC_fmt(r.total)}</td>
            <td class="text-end text-success" style="font-size:.78rem;">$${CXC_fmt(r.total_cobrado)}</td>
            <td class="text-end fw-bold pe-3" style="font-size:.82rem;color:${saldo > 0 ? '#dc3545' : '#198754'};">$${CXC_fmt(saldo)}</td>
            <td class="text-center">${badgeHtml}</td>
            <td class="text-center">
                <div class="d-flex justify-content-center gap-1 flex-wrap">
                    ${saldo > 0 ? `
                    <button class="btn btn-success btn-sm py-0 px-2" style="font-size:.72rem;" title="Registrar cobro"
                            onclick="CXC_abrirModalCobro(${r.id})">
                        <i class="bi bi-cash-coin"></i>
                    </button>` : ''}
                    <button class="btn btn-outline-primary btn-sm py-0 px-2" style="font-size:.72rem;" title="Ver historial de cobros"
                            onclick="CXC_abrirHistorial(${r.id}, '${esc(r.numero_factura)}')">
                        <i class="bi bi-clock-history"></i>
                    </button>
                    <button class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:.72rem;" title="Enviar recordatorio email"
                            onclick="CXC_abrirEmail(${r.id}, '${esc(r.numero_factura)}', '${esc(r.cliente_email || '')}', '${esc(r.cliente_nombre)}')">
                        <i class="bi bi-envelope"></i>
                    </button>
                    <button class="btn btn-sm py-0 px-2" style="font-size:.72rem;background:#25d366;color:#fff;" title="Enviar WhatsApp"
                            onclick="CXC_abrirWA(${r.id}, '${esc(r.numero_factura)}', '${esc(r.cliente_telefono || '')}', '${esc(r.cliente_nombre)}')">
                        <i class="bi bi-whatsapp"></i>
                    </button>
                </div>
            </td>
        </tr>`;
    }
    tbody.innerHTML = html;
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
function CXC_toggleSeleccion(id, sel) {
    sel ? CXC_seleccionados.add(id) : CXC_seleccionados.delete(id);
}

function CXC_seleccionarTodos(sel) {
    CXC_filtradoLocal.forEach(r => sel ? CXC_seleccionados.add(r.id) : CXC_seleccionados.delete(r.id));
    document.querySelectorAll('.cxc-chk').forEach(c => c.checked = sel);
}

/* ════════════════════════════════════════════════════
   MODAL COBRO
════════════════════════════════════════════════════ */
function CXC_abrirModalCobro(idVenta) {
    const fila = CXC_datos.find(r => r.id == idVenta);
    if (!fila) return;

    // Info factura
    document.getElementById('cobro-id-venta').value         = idVenta;
    document.getElementById('cobro-nro-factura').textContent = fila.numero_factura;
    document.getElementById('cobro-cliente').textContent     = fila.cliente_nombre;
    document.getElementById('cobro-total-fact').textContent  = CXC_fmt(fila.total);
    document.getElementById('cobro-ya-cobrado').textContent  = CXC_fmt(fila.total_cobrado);
    document.getElementById('cobro-saldo-pend').textContent  = CXC_fmt(fila.saldo);

    // Monto y fecha
    const elMonto = document.getElementById('cobro-monto');
    elMonto.value = parseFloat(fila.saldo).toFixed(2);
    elMonto.max   = parseFloat(fila.saldo).toFixed(2);
    document.getElementById('cobro-fecha').value         = new Date().toISOString().slice(0,10);
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
        const fd = new FormData();
        fd.append('id_venta',           idVenta);
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

        const r = await fetch(`${BASE_URL}/${RUTA_MODULO_CXC}/registrarCobroAjax`, {
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
async function CXC_abrirHistorial(idVenta, nroFactura) {
    document.getElementById('historial-subtitulo').textContent = 'Factura: ' + nroFactura;
    document.getElementById('historial-tbody').innerHTML = '<tr><td colspan="6" class="text-center text-muted">Cargando…</td></tr>';
    document.getElementById('historial-total').textContent = '0.00';

    new bootstrap.Modal(document.getElementById('modalHistorial')).show();

    try {
        const r = await fetch(`${BASE_URL}/${RUTA_MODULO_CXC}/historialCobrosAjax?id_venta=${idVenta}`, {
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
function CXC_abrirEmail(idVenta, nroFactura, email, clienteNombre) {
    document.getElementById('email-id-venta').value        = idVenta;
    document.getElementById('email-subtitulo').textContent = `Factura: ${nroFactura} — ${clienteNombre}`;
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
function CXC_envioMasivoEmail() {
    const ids = [...CXC_seleccionados];
    if (!ids.length) {
        CXC_toast('Seleccione al menos una factura.', 'warning');
        return;
    }
    const filas = CXC_datos.filter(r => ids.includes(r.id));
    const sinEmail = filas.filter(r => !r.cliente_email);
    if (sinEmail.length) {
        CXC_toast(`${sinEmail.length} cliente(s) sin email registrado.`, 'warning');
    }
    const conEmail = filas.filter(r => r.cliente_email);
    if (!conEmail.length) {
        CXC_toast('Ningún cliente seleccionado tiene email.', 'danger');
        return;
    }
    if (!confirm(`¿Enviar recordatorio por email a ${conEmail.length} cliente(s)?`)) return;

    let enviados = 0;
    const envios = conEmail.map(async r => {
        const fd = new FormData();
        fd.append('id_venta', r.id);
        fd.append('email',    r.cliente_email);
        try {
            const res = await fetch(`${BASE_URL}/${RUTA_MODULO_CXC}/enviarEmailAjax`, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            });
            const d = await res.json();
            if (d.ok) enviados++;
        } catch {}
    });
    Promise.all(envios).then(() => {
        CXC_toast(`${enviados}/${conEmail.length} correos enviados.`, 'success');
    });
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
        opt.value = JSON.stringify({ nombre: p.nombre, idioma: p.idioma, componentes: p.componentes });
        opt.textContent = `${p.nombre} (${p.idioma})`;
        sel.appendChild(opt);
    });

    if (typeof window.aplicarFavoritosModal === 'function') {
        window.aplicarFavoritosModal('#modalWA');
    }

    new bootstrap.Modal(document.getElementById('modalWA')).show();
}

async function CXC_enviarWA() {
    const idVenta  = document.getElementById('wa-id-venta').value;
    const telefono = document.getElementById('wa-telefono').value.replace(/[^0-9]/g,'');
    const val      = document.getElementById('wa-plantilla').value;

    if (!telefono || telefono.length < 7) { CXC_toast('Ingrese un número válido.', 'warning'); return; }
    if (!val)                              { CXC_toast('Seleccione una plantilla.', 'warning'); return; }

    const p = JSON.parse(val);

    const fd = new FormData();
    fd.append('id_venta',       idVenta);
    fd.append('telefono',       telefono);
    fd.append('template_name',  p.nombre);
    fd.append('idioma',         p.idioma);
    fd.append('components',     '[]');

    try {
        const r = await fetch(`${BASE_URL}/${RUTA_MODULO_CXC}/enviarWhatsappAjax`, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        });
        const data = await r.json();
        if (data.ok) {
            bootstrap.Modal.getInstance(document.getElementById('modalWA')).hide();
            CXC_toast(data.mensaje || 'WhatsApp enviado.', 'success');
        } else {
            CXC_toast(data.error || 'Error al enviar.', 'danger');
        }
    } catch (e) {
        CXC_toast('Error de conexión.', 'danger');
    }
}

/* ════════════════════════════════════════════════════
   ENVÍO MASIVO WHATSAPP
════════════════════════════════════════════════════ */
function CXC_envioMasivoWA() {
    if (!CXC_TIENE_WA) {
        CXC_toast('WhatsApp no está configurado para esta empresa. Active el módulo de WhatsApp para usar esta función.', 'warning');
        return;
    }
    const ids = [...CXC_seleccionados];
    if (!ids.length) {
        CXC_toast('Seleccione al menos una factura para el envío masivo.', 'warning');
        return;
    }
    CXC_toast('Para envíos masivos de WhatsApp, use el botón individual por cliente.', 'info');
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
   EXPORTACIONES
════════════════════════════════════════════════════ */
function CXC_exportarExcel() {
    const params = new URLSearchParams({
        estado:      document.getElementById('cxc-estado')?.value      || 'PENDIENTES',
        fecha_desde: document.getElementById('cxc-fecha-desde')?.value || '',
        fecha_hasta: document.getElementById('cxc-fecha-hasta')?.value || '',
        id_cliente:  CXC_getClientesSeleccionados(),
    });
    window.open(`${BASE_URL}/${RUTA_MODULO_CXC}/exportExcel?${params}`, '_blank');
}

function CXC_exportarPDF() {
    const params = new URLSearchParams({
        estado:      document.getElementById('cxc-estado')?.value      || 'PENDIENTES',
        fecha_desde: document.getElementById('cxc-fecha-desde')?.value || '',
        fecha_hasta: document.getElementById('cxc-fecha-hasta')?.value || '',
        id_cliente:  CXC_getClientesSeleccionados(),
    });
    window.open(`${BASE_URL}/${RUTA_MODULO_CXC}/exportPdf?${params}`, '_blank');
}

/* ════════════════════════════════════════════════════
   UTILIDADES
════════════════════════════════════════════════════ */
function CXC_fmt(v) {
    return parseFloat(v || 0).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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
