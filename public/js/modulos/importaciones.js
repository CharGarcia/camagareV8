'use strict';

// ─── CONFIGURACIÓN INICIAL ───
const IMP_TIPOS_GASTO = {
    arancel_ad_valorem: 'Arancel Ad-Valorem',
    fodinfa: 'FODINFA',
    iva_importacion: 'IVA de importación',
    isd: 'ISD',
    flete_internacional: 'Flete internacional',
    seguro: 'Seguro',
    agente_afianzado: 'Agente afianzado',
    almacenaje: 'Almacenaje',
    transporte_interno: 'Transporte interno',
    otro: 'Otro',
};

// ¿La serie activa tiene secuenciales configurados? (se actualiza en IMP_syncSecuencial)
window.IMP_SECUENCIAL_CONFIGURADO = true;

function IMP_avisarSecuencialNoConfigurado(tipo) {
    const mensajes = {
        serie:       ['No hay una serie / punto de emisión disponible.<br>Configure los puntos de emisión y sus secuenciales en <strong>Empresa → Puntos de emisión</strong> antes de registrar la importación.', 'Secuenciales no configurados'],
        secuencial:  ['No están configurados los secuenciales para esta serie.<br>Configúrelos en <strong>Empresa → Puntos de emisión</strong> antes de registrar la importación.', 'Secuenciales no configurados'],
        inactivo:    ['El punto de emisión seleccionado (o su establecimiento) está <strong>inactivo</strong>.<br>Actívelo en <strong>Empresa → Puntos de emisión</strong> o elija otra serie antes de registrar la importación.', 'Punto de emisión inactivo'],
        error:       ['No se pudo consultar el secuencial de esta serie. Intente de nuevo o verifique la configuración en <strong>Empresa → Puntos de emisión</strong>.', 'Error al consultar el secuencial'],
    };
    const [html, title] = mensajes[tipo] || mensajes.secuencial;

    if (typeof Swal === 'undefined') { window.alert(title + ': ' + html.replace(/<[^>]+>/g, ' ')); return; }
    Swal.fire({
        icon: 'warning',
        title: title,
        html: html,
        confirmButtonText: 'Entendido',
        confirmButtonColor: '#f39c12',
        target: document.getElementById('modalImportacion'),
    });
}

// avisar=false: solo actualiza el campo/borde (uso interno al preseleccionar
// la primera serie al abrir "Nueva Importación"). El aviso emergente (Swal)
// solo debe aparecer cuando el usuario elige la serie explícitamente en el
// selector (onchange), no de forma automática al abrir el modal.
window.IMP_syncSecuencial = async function (idPunto, avisar = true) {
    const inputSec = document.getElementById('impSecuencial');

    if (!idPunto) {
        window.IMP_SECUENCIAL_CONFIGURADO = false;
        if (inputSec) { inputSec.value = ''; inputSec.classList.remove('border-danger', 'border-warning'); inputSec.placeholder = 'Sin serie'; }
        if (avisar) IMP_avisarSecuencialNoConfigurado('serie');
        return;
    }

    if (inputSec) inputSec.placeholder = 'Cargando...';
    try {
        const resp = await fetch(`${window.CMG_urlBaseImp}/getSecuencialAjax?id_punto_emision=${idPunto}`);
        if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
        const json = await resp.json();

        if (!json.ok) {
            window.IMP_SECUENCIAL_CONFIGURADO = false;
            if (inputSec) { inputSec.value = ''; inputSec.placeholder = '000000001'; inputSec.classList.add('border-danger'); }
            if (avisar) IMP_avisarSecuencialNoConfigurado('error');
            console.warn('getSecuencialAjax respondió ok=false:', json);
            return;
        }

        // Punto de emisión inexistente o inactivo (o su establecimiento lo está).
        if (json.activo === false) {
            window.IMP_SECUENCIAL_CONFIGURADO = false;
            if (inputSec) { inputSec.value = ''; inputSec.placeholder = '000000001'; inputSec.classList.add('border-danger'); }
            if (avisar) IMP_avisarSecuencialNoConfigurado('inactivo');
            return;
        }

        if (inputSec) {
            inputSec.value = json.formateado || '';
            inputSec.placeholder = '000000001';

            if (json.es_gap) {
                inputSec.classList.add('border-warning');
                inputSec.title = json.detalle || 'Número faltante recuperado';
            } else {
                inputSec.classList.remove('border-warning');
                inputSec.title = json.detalle || 'Siguiente consecutivo';
            }
        }

        window.IMP_SECUENCIAL_CONFIGURADO = (json.configurado !== false);
        if (json.configurado === false) {
            if (inputSec) inputSec.classList.add('border-danger');
            if (avisar) IMP_avisarSecuencialNoConfigurado('secuencial');
        } else if (inputSec) {
            inputSec.classList.remove('border-danger');
        }
    } catch (e) {
        window.IMP_SECUENCIAL_CONFIGURADO = false;
        if (inputSec) { inputSec.value = ''; inputSec.placeholder = '000000001'; inputSec.classList.add('border-danger'); }
        if (avisar) IMP_avisarSecuencialNoConfigurado('error');
        console.error('Error cargando secuencial de importación', e);
    }
};

// Los campos numéricos decimales (cantidad, precios, peso, volumen) usan
// type="text" + inputmode="decimal" (no type="number") porque un <input
// type="number"> descarta silenciosamente el valor si se escribe con coma
// decimal ("12,345"), muy natural en español — el campo queda vacío por
// dentro aunque se vea escrito, y esa línea se guarda en 0 / sin datos.
// Este parser tolera coma o punto como separador decimal.
function IMP_parseNum(v) {
    if (v === null || v === undefined || v === '') return 0;
    const n = parseFloat(String(v).trim().replace(',', '.'));
    return isNaN(n) ? 0 : n;
}

function IMP_esc(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function IMP_bodegaOptionsHtml(selectedId) {
    let html = '<option value="">-- Bodega destino --</option>';
    (window.CMG_bodegasImp || []).forEach(b => {
        html += `<option value="${b.id}" ${String(selectedId) === String(b.id) ? 'selected' : ''}>${IMP_esc(b.nombre)}</option>`;
    });
    return html;
}

// Buscador genérico con debounce reutilizado para todos los typeaheads del módulo
// z-index muy por encima del modal (1055 por defecto de Bootstrap, 6060 en el
// stacking de un segundo modal sobre #modalImportacion): así el listado
// predictivo siempre queda visible sobre el modal, sin importar en qué
// pestaña o contenedor con scroll/overflow esté el input que lo abre.
const IMP_ZINDEX_LISTA = 10600;

function IMP_posicionarListaFlotante(inputEl, listEl) {
    // "Teletransportar" el listado a <body>: así queda completamente fuera de
    // cualquier contenedor ancestro con overflow:hidden/auto (tarjeta de la
    // tabla, celdas con scroll propio, el tab-pane, etc.) que lo recorte o lo
    // deje detrás de otro contenido, sin importar en qué pestaña esté.
    if (listEl.parentElement !== document.body) {
        document.body.appendChild(listEl);
    }
    const rect = inputEl.getBoundingClientRect();
    listEl.style.position = 'fixed';
    listEl.style.top = Math.round(rect.bottom + 2) + 'px';
    listEl.style.left = Math.round(rect.left) + 'px';
    listEl.style.minWidth = Math.round(rect.width) + 'px';
    listEl.style.zIndex = String(IMP_ZINDEX_LISTA);
    listEl.style.margin = '0';
}

function IMP_wireBuscadorInline(inputEl, listEl, buscarFn, handlers) {
    let timer;

    // Reposicionar mientras el listado esté abierto y el usuario haga scroll
    // en CUALQUIER contenedor con scroll propio (el modal, una tabla con
    // overflow, etc.) o redimensione la ventana — position:fixed no sigue al
    // input automáticamente. 'scroll' no burbujea, así que se escucha en fase
    // de captura sobre document para detectar el scroll de cualquier ancestro.
    document.addEventListener('scroll', () => {
        if (!listEl.classList.contains('d-none')) IMP_posicionarListaFlotante(inputEl, listEl);
    }, { passive: true, capture: true });
    window.addEventListener('resize', () => {
        if (!listEl.classList.contains('d-none')) IMP_posicionarListaFlotante(inputEl, listEl);
    });

    inputEl.addEventListener('input', function () {
        clearTimeout(timer);
        const q = this.value.trim();
        if (q.length < 2) { listEl.classList.add('d-none'); return; }
        timer = setTimeout(async () => {
            try {
                const data = await buscarFn(q);
                listEl.innerHTML = '';
                if (!data.length) {
                    listEl.innerHTML = '<div class="list-group-item small text-muted py-1 px-2">Sin resultados</div>';
                } else {
                    data.forEach(item => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'list-group-item list-group-item-action py-1 px-2 small';
                        btn.innerHTML = handlers.label(item);
                        btn.onclick = () => {
                            handlers.pick(item);
                            listEl.classList.add('d-none');
                        };
                        listEl.appendChild(btn);
                    });
                }
                IMP_posicionarListaFlotante(inputEl, listEl);
                listEl.classList.remove('d-none');
            } catch (e) {
                console.error('Error buscador:', e);
                listEl.innerHTML = '<div class="list-group-item small text-danger py-1 px-2">Error al buscar. Intente de nuevo.</div>';
                IMP_posicionarListaFlotante(inputEl, listEl);
                listEl.classList.remove('d-none');
            }
        }, 300);
    });
    inputEl.addEventListener('keydown', function (e) {
        if (['Backspace', 'Delete'].includes(e.key) && inputEl.dataset.selectedId) {
            inputEl.value = '';
            delete inputEl.dataset.selectedId;
            if (handlers.clear) handlers.clear();
            listEl.classList.add('d-none');
        }
    });
    inputEl.addEventListener('blur', function () {
        setTimeout(() => listEl.classList.add('d-none'), 200);
    });
}

async function IMP_fetchBuscador(url) {
    const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    if (!res.ok) throw new Error(`HTTP ${res.status} en ${url}`);
    const data = await res.json();
    if (!data.ok) throw new Error(data.mensaje || data.error || 'Respuesta no OK del servidor');
    return data.data || [];
}

async function IMP_buscarProveedoresExterior(q) {
    return IMP_fetchBuscador(`${window.CMG_urlBaseImp}/getProveedoresExteriorAjax?q=${encodeURIComponent(q)}`);
}

async function IMP_buscarAgentesAfianzados(q) {
    return IMP_fetchBuscador(`${window.CMG_urlBaseImp}/getAgentesAfianzadosAjax?q=${encodeURIComponent(q)}`);
}

async function IMP_buscarProductosCatalogo(q) {
    return IMP_fetchBuscador(`${window.CMG_urlBaseImp}/getProductosAjax?q=${encodeURIComponent(q)}`);
}

async function IMP_buscarComprasVinc(q) {
    return IMP_fetchBuscador(`${window.CMG_urlBaseImp}/buscarComprasAjax?q=${encodeURIComponent(q)}`);
}

async function IMP_buscarLiquidacionesVinc(q) {
    return IMP_fetchBuscador(`${window.CMG_urlBaseImp}/buscarLiquidacionesAjax?q=${encodeURIComponent(q)}`);
}

// ─────────────────────────────────────────────────────────────────────────────
// MODAL — ABRIR NUEVO / EDITAR
// ─────────────────────────────────────────────────────────────────────────────
window.abrirModalImportacionCrear = function () {
    try {
        IMP_resetModal();
        document.getElementById('impTitulo').textContent = 'Nueva Importación';
        const modalEl = document.getElementById('modalImportacion');
        if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
    } catch (e) {
        console.error('Error al abrir modal de importación:', e);
    }
};

window.abrirModalImportacion = function (el) {
    try {
        const row = JSON.parse(el.dataset.row);
        IMP_resetModal(false);
        document.getElementById('impTitulo').textContent = (row.numero_importacion || '') + ' - ' + (row.proveedor_nombre || '');
        fetch(`${window.CMG_urlBaseImp}/getImportacionAjax?id=${row.id}`)
            .then(r => r.json())
            .then(res => {
                if (!res.ok) { Swal.fire('Error', res.mensaje, 'error'); return; }
                IMP_poblarModal(res.data);
            }).catch(e => console.error(e));

        const modalEl = document.getElementById('modalImportacion');
        if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
    } catch (e) {
        console.error('Error al abrir modal para editar:', e);
    }
};

// ─────────────────────────────────────────────────────────────────────────────
// RESET
// ─────────────────────────────────────────────────────────────────────────────
// esNueva=true (default, "Nueva Importación"): preselecciona la primera serie
// y muestra la vista previa de su siguiente secuencial.
// esNueva=false (editar una ya guardada): NO toca la serie/secuencial — se
// asignan un instante después con los datos reales vía IMP_poblarModal(). Si
// se dispara igual la vista previa aquí, esa consulta (para la serie por
// defecto, no la de la importación real) puede responder DESPUÉS de
// IMP_poblarModal() y sobrescribir el secuencial correcto con uno ajeno.
function IMP_resetModal(esNueva = true) {
    ['impId', 'impReferenciaDai', 'impIncoterm', 'impBuscarProveedor', 'impIdProveedor',
     'impBuscarAgente', 'impIdAgente', 'impFechaEmbarque', 'impFechaLlegada',
     'impFechaNacionalizacion', 'impObservaciones'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    document.getElementById('impSecuencial').value = '';
    const puntoSel = document.getElementById('impPuntoEmision');
    puntoSel.disabled = false;
    if (esNueva) {
        if (puntoSel.options.length > 1) {
            // Preselección automática de la primera serie: solo actualiza el
            // campo/borde, sin el aviso emergente (avisar=false). El aviso queda
            // reservado para cuando el usuario elija la serie explícitamente.
            puntoSel.selectedIndex = 1;
            window.IMP_syncSecuencial(puntoSel.value, false);
        } else {
            window.IMP_SECUENCIAL_CONFIGURADO = false;
        }
    } else {
        puntoSel.selectedIndex = 0;
    }
    document.getElementById('impIdBodegaDestino').value = '';
    document.getElementById('impCriterioProrrateo').value = 'fob';

    document.getElementById('tbodyProductosFob').innerHTML = '';
    document.getElementById('tbodyFacturasExterior').innerHTML = '';
    document.getElementById('tbodyGastosImp').innerHTML = '';

    document.querySelectorAll('.imp-col-nacionalizado').forEach(el => el.classList.add('d-none'));

    const badge = document.getElementById('impEstadoBadge');
    badge.classList.add('d-none');

    document.getElementById('btnEliminarImportacion').classList.add('d-none');
    document.getElementById('impBtnProcesarInventario').classList.add('d-none');
    document.getElementById('impBtnCalcularProrrateo').disabled = true;
    document.getElementById('impAlertPendienteAprobacion').classList.add('d-none');
    const grupoAprobacionReset = document.getElementById('impGrupoAprobacion');
    grupoAprobacionReset.classList.add('d-none');
    grupoAprobacionReset.classList.remove('d-flex');

    document.getElementById('impProrrateoBody').innerHTML =
        '<tr><td colspan="4" class="text-center py-4 text-muted">Guarda la importación y presiona "Calcular prorrateo" para ver la vista previa.</td></tr>';
    document.getElementById('impProrrateoStatus').innerHTML = '';
    ['impProrrateoTotalFactura', 'impProrrateoCapManual', 'impProrrateoCapVinc', 'impProrrateoIvaIsd', 'impProrrateoOtros', 'impProrrateoCostoTotal']
        .forEach(id => document.getElementById(id).textContent = '0.00');

    ['impLblSubtotalFob', 'impLblGastosCap', 'impLblIva', 'impLblIsd', 'impLblOtros', 'impLblCostoTotal']
        .forEach(id => document.getElementById(id).textContent = '0.00');

    if (_impAsientoTab) _impAsientoTab.limpiar();

    const modal = document.getElementById('modalImportacion');
    if (modal) modal.dataset.id = '';

    IMP_bloquearEdicion(false);

    const primerTab = document.getElementById('imp-tab-general');
    if (primerTab) bootstrap.Tab.getOrCreateInstance(primerTab).show();

    IMP_recalcularTotalesLineas();
}

// ─────────────────────────────────────────────────────────────────────────────
// BLOQUEO DE EDICIÓN (estados nacionalizada / cerrada / anulada)
// ─────────────────────────────────────────────────────────────────────────────
function IMP_bloquearEdicion(bloquear) {
    const selectoresCabecera = [
        '#impReferenciaDai', '#impIncoterm', '#impBuscarProveedor', '#impBuscarAgente',
        '#impIdBodegaDestino', '#impCriterioProrrateo', '#impFechaEmbarque', '#impFechaLlegada',
        '#impFechaNacionalizacion', '#impObservaciones'
    ];
    selectoresCabecera.forEach(s => {
        const el = document.querySelector(s);
        if (el) el.disabled = bloquear;
    });

    ['#tbodyProductosFob', '#tbodyFacturasExterior', '#tbodyGastosImp'].forEach(sel => {
        document.querySelectorAll(`${sel} input, ${sel} select, ${sel} button`).forEach(el => el.disabled = bloquear);
    });

    ['inputBuscarProductoImp', 'impFileExcelProductos'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.disabled = bloquear;
    });

    document.querySelectorAll('#impTabProductos .btn-link, #impTabFacturas > .border > .p-2 .btn-link, #impTabGastos > .border > .p-2 .btn-link')
        .forEach(btn => btn.disabled = bloquear);
}

// ─────────────────────────────────────────────────────────────────────────────
// POBLAR MODAL
// ─────────────────────────────────────────────────────────────────────────────
function IMP_poblarModal(d) {
    document.getElementById('impId').value = d.id || '';
    // Campo "N° Importación" = solo el secuencial puro (ej. "000000200"); la
    // serie (establecimiento-punto) va aparte, en el selector impPuntoEmision.
    // d.numero_importacion es el string ya concatenado "est-pto-secuencial";
    // no usarlo aquí para no duplicar la serie dentro de este campo.
    document.getElementById('impSecuencial').value = d.secuencial || '';
    // La serie/secuencial se asigna una sola vez al crear: no editable al abrir un registro existente.
    const puntoSel = document.getElementById('impPuntoEmision');
    puntoSel.value = d.id_punto_emision || '';
    puntoSel.disabled = true;
    document.getElementById('impReferenciaDai').value = d.referencia_dai || '';
    document.getElementById('impIncoterm').value = d.incoterm || '';

    document.getElementById('impIdProveedor').value = d.id_proveedor || '';
    document.getElementById('impBuscarProveedor').value = d.proveedor_nombre || '';
    if (d.id_proveedor) document.getElementById('impBuscarProveedor').dataset.selectedId = d.id_proveedor;

    document.getElementById('impIdAgente').value = d.id_agente_afianzado || '';
    document.getElementById('impBuscarAgente').value = d.agente_nombre || '';
    if (d.id_agente_afianzado) document.getElementById('impBuscarAgente').dataset.selectedId = d.id_agente_afianzado;

    document.getElementById('impIdBodegaDestino').value = d.id_bodega_destino || '';
    document.getElementById('impCriterioProrrateo').value = d.criterio_prorrateo || 'fob';
    document.getElementById('impFechaEmbarque').value = d.fecha_embarque ? d.fecha_embarque.slice(0, 10) : '';
    document.getElementById('impFechaLlegada').value = d.fecha_llegada ? d.fecha_llegada.slice(0, 10) : '';
    document.getElementById('impFechaNacionalizacion').value = d.fecha_nacionalizacion ? d.fecha_nacionalizacion.slice(0, 10) : '';
    document.getElementById('impObservaciones').value = d.observaciones || '';

    document.getElementById('impLblSubtotalFob').textContent = parseFloat(d.subtotal_fob || 0).toFixed(2);
    document.getElementById('impLblGastosCap').textContent = parseFloat(d.total_gastos_capitalizables || 0).toFixed(2);
    document.getElementById('impLblIva').textContent = parseFloat(d.total_iva || 0).toFixed(2);
    document.getElementById('impLblIsd').textContent = parseFloat(d.total_isd || 0).toFixed(2);
    document.getElementById('impLblOtros').textContent = parseFloat(d.total_otros_gastos || 0).toFixed(2);
    document.getElementById('impLblCostoTotal').textContent = parseFloat(d.costo_total_nacionalizado || 0).toFixed(2);

    const estado = d.estado || 'borrador';
    const badge = document.getElementById('impEstadoBadge');
    const estadoMap = {
        borrador: ['Borrador', 'secondary'],
        en_transito: ['En tránsito', 'warning'],
        pendiente_aprobacion: ['Pendiente aprobación', 'info'],
        nacionalizada: ['Nacionalizada', 'success'],
        cerrada: ['Cerrada', 'primary'],
        anulada: ['Anulada', 'danger'],
    };
    const [label, color] = estadoMap[estado] || ['Borrador', 'secondary'];
    badge.className = `badge bg-${color} bg-opacity-10 text-${color} border border-${color} border-opacity-25 ms-2`;
    badge.textContent = label;

    const bloqueada = ['nacionalizada', 'cerrada', 'anulada', 'pendiente_aprobacion'].includes(estado);
    document.querySelectorAll('.imp-col-nacionalizado').forEach(el => el.classList.toggle('d-none', estado !== 'nacionalizada' && !bloqueada));

    const pendiente = estado === 'pendiente_aprobacion';
    const alertaPendiente = document.getElementById('impAlertPendienteAprobacion');
    alertaPendiente.classList.toggle('d-none', !pendiente);
    if (pendiente) {
        const nombres = (d.aprobadores_nombres || []).join(', ');
        alertaPendiente.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Esta importación está <strong>pendiente de aprobación</strong>'
            + (nombres ? ` por: <strong>${IMP_esc(nombres)}</strong>` : '')
            + '. No se puede editar mientras tanto. Se notificó por correo a los aprobadores.';
    }
    const grupoAprobacion = document.getElementById('impGrupoAprobacion');
    const puedeAprobar = pendiente && !!d.puede_aprobar;
    grupoAprobacion.classList.toggle('d-none', !puedeAprobar);
    grupoAprobacion.classList.toggle('d-flex', puedeAprobar);

    document.getElementById('tbodyProductosFob').innerHTML = '';
    (d.detalles || []).forEach(det => IMP_agregarFilaProducto(det));

    document.getElementById('tbodyFacturasExterior').innerHTML = '';
    (d.facturas_exterior || []).forEach(f => IMP_agregarFilaFactura(f));

    document.getElementById('tbodyGastosImp').innerHTML = '';
    (d.gastos || []).forEach(g => IMP_agregarFilaGasto(g));

    IMP_recalcularTotalesLineas();
    IMP_bloquearEdicion(bloqueada);

    document.getElementById('btnEliminarImportacion').classList.toggle('d-none', bloqueada || !d.id);
    document.getElementById('impBtnProcesarInventario').classList.toggle('d-none', !d.id || bloqueada);
    document.getElementById('impBtnCalcularProrrateo').disabled = !d.id;

    const modal = document.getElementById('modalImportacion');
    if (modal) modal.dataset.id = d.id;
}

// ─────────────────────────────────────────────────────────────────────────────
// CABECERA — PROVEEDOR EXTERIOR / AGENTE AFIANZADO
// ─────────────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    const inpProv = document.getElementById('impBuscarProveedor');
    const listProv = document.getElementById('impListaProveedores');
    if (inpProv && listProv) {
        IMP_wireBuscadorInline(inpProv, listProv, IMP_buscarProveedoresExterior, {
            label: (p) => `<strong>${IMP_esc(p.identificacion)}</strong> — ${IMP_esc(p.nombre)}`,
            pick: (p) => {
                document.getElementById('impIdProveedor').value = p.id;
                inpProv.value = p.nombre;
                inpProv.dataset.selectedId = p.id;
            },
            clear: () => { document.getElementById('impIdProveedor').value = ''; },
        });
    }

    const inpAg = document.getElementById('impBuscarAgente');
    const listAg = document.getElementById('impListaAgentes');
    if (inpAg && listAg) {
        IMP_wireBuscadorInline(inpAg, listAg, IMP_buscarAgentesAfianzados, {
            label: (p) => `<strong>${IMP_esc(p.identificacion)}</strong> — ${IMP_esc(p.nombre)}`,
            pick: (p) => {
                document.getElementById('impIdAgente').value = p.id;
                inpAg.value = p.nombre;
                inpAg.dataset.selectedId = p.id;
            },
            clear: () => { document.getElementById('impIdAgente').value = ''; },
        });
    }

    const inpProd = document.getElementById('inputBuscarProductoImp');
    const listProd = document.getElementById('listaProductosImp');
    if (inpProd && listProd) {
        IMP_wireBuscadorInline(inpProd, listProd, IMP_buscarProductosCatalogo, {
            label: (p) => `<div class="d-flex justify-content-between"><span>${IMP_esc(p.nombre)}</span><small class="text-muted">${IMP_esc(p.codigo_principal || p.codigo || '')}</small></div>`,
            pick: (p) => {
                IMP_agregarFilaProducto({
                    id_producto: p.id,
                    codigo_producto_raw: p.codigo_principal || p.codigo || '',
                    descripcion: p.nombre,
                    cantidad: 1,
                    id_medida: p.id_medida || '',
                    precio_unitario_fob: 0,
                });
                inpProd.value = '';
                IMP_recalcularTotalesLineas();
            },
        });
    }

    const tabAsiento = document.getElementById('imp-tab-asiento');
    if (tabAsiento) {
        tabAsiento.addEventListener('shown.bs.tab', function () {
            const tab = IMP_asientoTab();
            if (tab) tab.cargar(document.getElementById('impId').value || 0);
        });
    }
});

window.IMP_descargarPlantillaExcel = function () {
    window.location.href = `${window.CMG_urlBaseImp}/descargarPlantillaProductosAjax`;
};

// Carga masiva de líneas FOB desde Excel/CSV: sube el archivo, el backend lo
// parsea y resuelve cada código contra el catálogo (sin guardar nada todavía),
// y cada línea resuelta se agrega a la tabla igual que al elegir un producto
// en el buscador — el usuario revisa/corrige y recién queda guardado al
// guardar toda la importación.
document.addEventListener('DOMContentLoaded', () => {
    const fileInput = document.getElementById('impFileExcelProductos');
    if (!fileInput) return;
    fileInput.addEventListener('change', async function () {
        const file = this.files[0];
        this.value = ''; // permite volver a elegir el mismo archivo si hace falta reintentar
        if (!file) return;

        const fd = new FormData();
        fd.append('archivo', file);

        if (typeof Swal !== 'undefined') {
            Swal.fire({ title: 'Procesando archivo...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        }
        try {
            const res = await fetch(`${window.CMG_urlBaseImp}/importarProductosAjax`, {
                method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            if (typeof Swal !== 'undefined') Swal.close();

            if (!data.ok) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', data.mensaje || 'No se pudo procesar el archivo.', 'error');
                else alert(data.mensaje || 'No se pudo procesar el archivo.');
                return;
            }

            let agregadas = 0, conError = 0;
            (data.data || []).forEach(linea => {
                IMP_agregarFilaProducto(linea);
                if (linea.valido) agregadas++; else conError++;
            });
            IMP_recalcularTotalesLineas();

            const resumen = conError > 0
                ? `Se agregaron ${agregadas + conError} línea(s): ${agregadas} correcta(s) y ${conError} con error (marcadas en rojo en la tabla; corríjalas antes de guardar).`
                : `Se agregaron ${agregadas} línea(s) correctamente.`;
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: conError > 0 ? 'warning' : 'success', title: 'Archivo procesado', text: resumen });
            } else {
                alert(resumen);
            }
        } catch (e) {
            if (typeof Swal !== 'undefined') Swal.fire('Error', 'No se pudo subir el archivo: ' + e.message, 'error');
            console.error('Error al importar Excel de productos:', e);
        }
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// TAB PRODUCTOS (FOB)
// ─────────────────────────────────────────────────────────────────────────────
let _impProdIdx = 0;

window.IMP_agregarLineaProductoVacia = function () {
    IMP_agregarFilaProducto({ descripcion: '', cantidad: 1, precio_unitario_fob: 0 });
};

function IMP_agregarFilaProducto(det) {
    const tbody = document.getElementById('tbodyProductosFob');
    const idx = _impProdIdx++;
    const tr = document.createElement('tr');
    tr.className = 'row-detalle';
    tr.dataset.idx = idx;
    const costoUnit = parseFloat(det.costo_unitario_nacionalizado || 0);
    const costoTotal = parseFloat(det.costo_total_nacionalizado || 0);
    // Línea proveniente de una carga por Excel con error (código no encontrado,
    // cantidad inválida, etc.): se resalta para que el usuario la corrija antes
    // de guardar (ver ImportacionesService::resolverLineasExcelProductos).
    const filaConError = det.valido === false;
    tr.innerHTML = `
        <td class="ps-3">
            <input type="text" class="form-control form-control-sm input-imp-descripcion ${filaConError ? 'border-danger' : ''}" value="${IMP_esc(det.descripcion || '')}" placeholder="Escriba para buscar en el catálogo..." autocomplete="off" ${filaConError ? `title="${IMP_esc(det.error || '')}"` : ''}>
            <input type="hidden" class="input-imp-id-detalle" value="${det.id || ''}">
            <input type="hidden" class="input-imp-id-producto" value="${det.id_producto || ''}">
            <input type="hidden" class="input-imp-codigo-raw" value="${IMP_esc(det.codigo_producto_raw || '')}">
            <input type="hidden" class="input-imp-id-medida" value="${det.id_medida || ''}">
        </td>
        <td><input type="text" inputmode="decimal" class="form-control form-control-sm text-center input-imp-cantidad" value="${parseFloat(det.cantidad || 1)}" oninput="IMP_recalcularFilaProducto(this)"></td>
        <td><input type="text" inputmode="decimal" class="form-control form-control-sm text-end input-imp-precio-fob" value="${parseFloat(det.precio_unitario_fob || 0).toFixed(4)}" oninput="IMP_recalcularFilaProducto(this)"></td>
        <td><input type="text" class="form-control form-control-sm text-end bg-light input-imp-total-fob" value="${parseFloat(det.precio_total_fob || 0).toFixed(2)}" readonly tabindex="-1" title="Se calcula automáticamente: Cantidad × P. Unit. FOB"></td>
        <td><input type="text" inputmode="decimal" class="form-control form-control-sm text-center input-imp-peso" value="${parseFloat(det.peso_kg || 0)}"></td>
        <td><input type="text" inputmode="decimal" class="form-control form-control-sm text-center input-imp-volumen" value="${parseFloat(det.volumen_m3 || 0)}"></td>
        <td><input type="text" class="form-control form-control-sm text-center input-imp-lote" value="${IMP_esc(det.numero_lote || '')}"></td>
        <td><input type="date" class="form-control form-control-sm input-imp-caducidad" value="${det.fecha_caducidad ? det.fecha_caducidad.slice(0,10) : ''}"></td>
        <td><input type="text" class="form-control form-control-sm text-center input-imp-nup" value="${IMP_esc(det.nup || '')}"></td>
        <td><select class="form-select form-select-sm input-imp-bodega">${IMP_bodegaOptionsHtml(det.id_bodega || '')}</select></td>
        <td class="text-end imp-col-nacionalizado d-none"><span class="input-imp-costo-unit">${costoUnit ? costoUnit.toFixed(4) : '-'}</span></td>
        <td class="text-end imp-col-nacionalizado d-none"><span class="input-imp-costo-total">${costoTotal ? costoTotal.toFixed(2) : '-'}</span></td>
        <td class="text-center p-0 align-middle">
            <button type="button" class="btn btn-sm btn-link text-danger p-0 shadow-none border-0" onclick="this.closest('tr').remove();IMP_recalcularTotalesLineas()">
                <i class="bi bi-trash3 fs-6"></i>
            </button>
        </td>`;
    tbody.appendChild(tr);
    IMP_recalcularFilaProducto(tr.querySelector('.input-imp-cantidad'));

    // El campo Descripción de la fila también busca en el catálogo (además del
    // buscador de la barra de herramientas), reutilizando el mismo listado
    // flotante: así no hace falta pasar por la barra para vincular un producto.
    const descInput = tr.querySelector('.input-imp-descripcion');
    const listaProd = document.getElementById('listaProductosImp');
    if (descInput && listaProd) {
        IMP_wireBuscadorInline(descInput, listaProd, IMP_buscarProductosCatalogo, {
            label: (p) => `<div class="d-flex justify-content-between"><span>${IMP_esc(p.nombre)}</span><small class="text-muted">${IMP_esc(p.codigo_principal || p.codigo || '')}</small></div>`,
            pick: (p) => {
                tr.querySelector('.input-imp-id-producto').value = p.id;
                tr.querySelector('.input-imp-codigo-raw').value = p.codigo_principal || p.codigo || '';
                tr.querySelector('.input-imp-id-medida').value = p.id_medida || '';
                descInput.value = p.nombre;
                descInput.dataset.selectedId = p.id;
            },
            clear: () => { tr.querySelector('.input-imp-id-producto').value = ''; },
        });
    }
}

function IMP_recalcularFilaProducto(input) {
    const tr = input.closest('tr');
    const cant = IMP_parseNum(tr.querySelector('.input-imp-cantidad').value);
    const precio = IMP_parseNum(tr.querySelector('.input-imp-precio-fob').value);
    tr.querySelector('.input-imp-total-fob').value = (cant * precio).toFixed(2);
    IMP_recalcularTotalesLineas();
}

// ─────────────────────────────────────────────────────────────────────────────
// TAB FACTURAS DEL EXTERIOR
// ─────────────────────────────────────────────────────────────────────────────
let _impFacIdx = 0;

window.IMP_agregarFilaFactura = function (f) {
    f = f || {};
    const tbody = document.getElementById('tbodyFacturasExterior');
    const idx = _impFacIdx++;
    const tr = document.createElement('tr');
    tr.dataset.idx = idx;

    const idProvDefault = f.id_proveedor || document.getElementById('impIdProveedor').value || '';
    const nombreProvDefault = f.proveedor_nombre || document.getElementById('impBuscarProveedor').value || '';

    tr.innerHTML = `
        <td class="ps-3 position-relative">
            <input type="text" class="form-control form-control-sm input-imp-fac-proveedor" value="${IMP_esc(nombreProvDefault)}" placeholder="Buscar proveedor exterior..." autocomplete="off">
            <input type="hidden" class="input-imp-fac-id-proveedor" value="${idProvDefault}">
            <input type="hidden" class="input-imp-fac-id" value="${f.id || ''}">
            <div class="list-group shadow-sm d-none imp-fac-lista" style="max-height:180px;overflow-y:auto;"></div>
        </td>
        <td><input type="text" class="form-control form-control-sm input-imp-fac-numero" value="${IMP_esc(f.numero_factura || '')}" placeholder="N° factura"></td>
        <td><input type="date" class="form-control form-control-sm input-imp-fac-fecha" value="${f.fecha_factura ? f.fecha_factura.slice(0,10) : ''}"></td>
        <td><input type="text" inputmode="decimal" class="form-control form-control-sm text-end input-imp-fac-monto" value="${parseFloat(f.monto_usd || 0).toFixed(2)}" oninput="IMP_recalcularTotalesLineas()"></td>
        <td><input type="text" class="form-control form-control-sm input-imp-fac-forma-pago" value="${IMP_esc(f.forma_pago || '')}" placeholder="Forma de pago"></td>
        <td><input type="number" class="form-control form-control-sm text-center input-imp-fac-plazo" value="${parseInt(f.plazo_dias || 0)}" min="0" step="1"></td>
        <td class="text-center p-0 align-middle">
            <button type="button" class="btn btn-sm btn-link text-danger p-0 shadow-none border-0 btn-eliminar-fila">
                <i class="bi bi-trash3 fs-6"></i>
            </button>
        </td>`;
    tbody.appendChild(tr);

    const inp = tr.querySelector('.input-imp-fac-proveedor');
    const list = tr.querySelector('.imp-fac-lista');
    const hidden = tr.querySelector('.input-imp-fac-id-proveedor');
    if (idProvDefault) inp.dataset.selectedId = idProvDefault;

    IMP_wireBuscadorInline(inp, list, IMP_buscarProveedoresExterior, {
        label: (p) => `<strong>${IMP_esc(p.identificacion)}</strong> — ${IMP_esc(p.nombre)}`,
        pick: (p) => {
            hidden.value = p.id;
            inp.value = p.nombre;
            inp.dataset.selectedId = p.id;
        },
        clear: () => { hidden.value = ''; },
    });

    // El listado pudo haberse movido a <body> (ver IMP_posicionarListaFlotante):
    // al borrar la fila hay que quitarlo explícitamente de donde esté, si no
    // queda huérfano flotando sobre la página.
    tr.querySelector('.btn-eliminar-fila').addEventListener('click', () => {
        list.remove();
        tr.remove();
        IMP_recalcularTotalesLineas();
    });
};

function IMP_recalcularTotalFacturas() {
    let total = 0;
    document.querySelectorAll('#tbodyFacturasExterior tr').forEach(tr => {
        total += IMP_parseNum(tr.querySelector('.input-imp-fac-monto')?.value);
    });
    document.getElementById('impTotalFacturasExterior').textContent = total.toFixed(2);
    return total;
}

// ─────────────────────────────────────────────────────────────────────────────
// TAB GASTOS DE NACIONALIZACIÓN
// ─────────────────────────────────────────────────────────────────────────────
let _impGastoIdx = 0;

function IMP_tipoGastoOptionsHtml(origen, selected) {
    let html = '';
    Object.entries(IMP_TIPOS_GASTO).forEach(([val, label]) => {
        const deshabilitado = origen !== 'dai_manual' && (val === 'iva_importacion' || val === 'isd');
        html += `<option value="${val}" ${val === selected ? 'selected' : ''} ${deshabilitado ? 'disabled' : ''}>${label}</option>`;
    });
    return html;
}

window.IMP_agregarFilaGasto = function (g) {
    g = g || {};
    const tbody = document.getElementById('tbodyGastosImp');
    const idx = _impGastoIdx++;
    const origen = g.origen || 'dai_manual';
    const prorrateableDefault = g.id ? !!(g.prorrateable === true || g.prorrateable === 't') : (origen === 'dai_manual' ? !['iva_importacion', 'isd'].includes(g.tipo_gasto) : true);

    const tr = document.createElement('tr');
    tr.dataset.idx = idx;

    let docLabel = '';
    if (origen === 'compra_vinculada' && g.compra_numero) docLabel = 'Compra #' + g.compra_numero;
    if (origen === 'liquidacion_vinculada' && g.liquidacion_numero) docLabel = 'Liquidación #' + g.liquidacion_numero;

    tr.innerHTML = `
        <td class="ps-3">
            <select class="form-select form-select-sm select-imp-gasto-origen" onchange="IMP_onCambioOrigenGasto(this)">
                <option value="dai_manual" ${origen === 'dai_manual' ? 'selected' : ''}>Manual DAI</option>
                <option value="compra_vinculada" ${origen === 'compra_vinculada' ? 'selected' : ''}>Vincular Compra</option>
                <option value="liquidacion_vinculada" ${origen === 'liquidacion_vinculada' ? 'selected' : ''}>Vincular Liquidación</option>
            </select>
            <input type="hidden" class="input-imp-gasto-id" value="${g.id || ''}">
        </td>
        <td><select class="form-select form-select-sm select-imp-gasto-tipo">${IMP_tipoGastoOptionsHtml(origen, g.tipo_gasto || (origen === 'dai_manual' ? 'otro' : 'agente_afianzado'))}</select></td>
        <td class="position-relative">
            <input type="text" class="form-control form-control-sm input-imp-gasto-descripcion" value="${IMP_esc(g.descripcion || docLabel)}" placeholder="Descripción">
            <input type="hidden" class="input-imp-gasto-id-compra" value="${g.id_compra || ''}">
            <input type="hidden" class="input-imp-gasto-id-liquidacion" value="${g.id_liquidacion_compra || ''}">
            <div class="mt-1 imp-gasto-vinc-wrap ${origen === 'dai_manual' ? 'd-none' : ''}">
                <input type="text" class="form-control form-control-sm imp-gasto-buscar-doc" placeholder="Buscar documento a vincular..." autocomplete="off">
                <div class="list-group shadow-sm d-none imp-gasto-lista-doc" style="max-height:180px;overflow-y:auto;"></div>
            </div>
        </td>
        <td><input type="text" inputmode="decimal" class="form-control form-control-sm text-end input-imp-gasto-monto" value="${parseFloat(g.monto || 0).toFixed(2)}" oninput="IMP_recalcularTotalesLineas()"></td>
        <td class="text-center"><input type="checkbox" class="form-check-input input-imp-gasto-prorrateable" ${prorrateableDefault ? 'checked' : ''} ${origen !== 'dai_manual' ? 'disabled' : ''}></td>
        <td class="text-center p-0 align-middle">
            <button type="button" class="btn btn-sm btn-link text-danger p-0 shadow-none border-0 btn-eliminar-fila">
                <i class="bi bi-trash3 fs-6"></i>
            </button>
        </td>`;
    tbody.appendChild(tr);

    const listaDoc = tr.querySelector('.imp-gasto-lista-doc');
    const selTipo = tr.querySelector('.select-imp-gasto-tipo');
    const chkProrr = tr.querySelector('.input-imp-gasto-prorrateable');
    selTipo.addEventListener('change', function () {
        if (tr.querySelector('.select-imp-gasto-origen').value !== 'dai_manual') return;
        chkProrr.checked = !['iva_importacion', 'isd'].includes(this.value);
    });

    IMP_wireBuscadorDocGasto(tr);

    // El listado de "vincular documento" pudo haberse movido a <body> (ver
    // IMP_posicionarListaFlotante): al borrar la fila hay que quitarlo de
    // donde esté, si no queda huérfano flotando sobre la página.
    tr.querySelector('.btn-eliminar-fila').addEventListener('click', () => {
        listaDoc.remove();
        tr.remove();
        IMP_recalcularTotalesLineas();
    });
};

window.IMP_onCambioOrigenGasto = function (sel) {
    const tr = sel.closest('tr');
    const origen = sel.value;
    const wrap = tr.querySelector('.imp-gasto-vinc-wrap');
    const selTipo = tr.querySelector('.select-imp-gasto-tipo');
    const chkProrr = tr.querySelector('.input-imp-gasto-prorrateable');
    const idCompra = tr.querySelector('.input-imp-gasto-id-compra');
    const idLiq = tr.querySelector('.input-imp-gasto-id-liquidacion');

    wrap.classList.toggle('d-none', origen === 'dai_manual');
    if (origen === 'dai_manual') {
        idCompra.value = '';
        idLiq.value = '';
        chkProrr.disabled = false;
    } else {
        chkProrr.checked = true;
        chkProrr.disabled = true;
        if (['iva_importacion', 'isd'].includes(selTipo.value)) selTipo.value = 'agente_afianzado';
    }

    const selectedTipo = selTipo.value;
    selTipo.innerHTML = IMP_tipoGastoOptionsHtml(origen, selectedTipo);
    IMP_wireBuscadorDocGasto(tr);
};

function IMP_wireBuscadorDocGasto(tr) {
    const inp = tr.querySelector('.imp-gasto-buscar-doc');
    const list = tr.querySelector('.imp-gasto-lista-doc');
    if (!inp || !list || inp.dataset.wired) return;
    inp.dataset.wired = '1';

    const origenSel = tr.querySelector('.select-imp-gasto-origen');
    const descInput = tr.querySelector('.input-imp-gasto-descripcion');
    const montoInput = tr.querySelector('.input-imp-gasto-monto');
    const idCompra = tr.querySelector('.input-imp-gasto-id-compra');
    const idLiq = tr.querySelector('.input-imp-gasto-id-liquidacion');

    IMP_wireBuscadorInline(inp, list, (q) => origenSel.value === 'compra_vinculada' ? IMP_buscarComprasVinc(q) : IMP_buscarLiquidacionesVinc(q), {
        label: (item) => `<strong>#${IMP_esc(item.numero)}</strong> — ${IMP_esc(item.proveedor_nombre)} — $${parseFloat(item.importe_total).toFixed(2)}`,
        pick: (item) => {
            if (origenSel.value === 'compra_vinculada') {
                idCompra.value = item.id;
                idLiq.value = '';
                descInput.value = `Compra #${item.numero} - ${item.proveedor_nombre}`;
            } else {
                idLiq.value = item.id;
                idCompra.value = '';
                descInput.value = `Liquidación #${item.numero} - ${item.proveedor_nombre}`;
            }
            montoInput.value = parseFloat(item.importe_total).toFixed(2);
            inp.value = '';
            IMP_recalcularTotalesLineas();
        },
    });
}

function IMP_recalcularTotalGastos() {
    let total = 0;
    document.querySelectorAll('#tbodyGastosImp tr').forEach(tr => {
        total += IMP_parseNum(tr.querySelector('.input-imp-gasto-monto')?.value);
    });
    document.getElementById('impTotalGastos').textContent = total.toFixed(2);
    return total;
}

// ─────────────────────────────────────────────────────────────────────────────
// TOTALES (vista previa local, se recalculan de forma autoritativa en el backend)
// ─────────────────────────────────────────────────────────────────────────────
function IMP_recalcularTotalesLineas() {
    let subtotalFob = 0;
    document.querySelectorAll('#tbodyProductosFob tr').forEach(tr => {
        subtotalFob += IMP_parseNum(tr.querySelector('.input-imp-total-fob')?.value);
    });
    document.getElementById('impLblSubtotalFob').textContent = subtotalFob.toFixed(2);

    let capManual = 0, capVinc = 0, iva = 0, isd = 0, otros = 0;
    document.querySelectorAll('#tbodyGastosImp tr').forEach(tr => {
        const origen = tr.querySelector('.select-imp-gasto-origen')?.value || 'dai_manual';
        const tipo = tr.querySelector('.select-imp-gasto-tipo')?.value || 'otro';
        const monto = IMP_parseNum(tr.querySelector('.input-imp-gasto-monto')?.value);
        const prorrateable = tr.querySelector('.input-imp-gasto-prorrateable')?.checked;

        if (origen !== 'dai_manual') {
            capVinc += monto;
        } else if (prorrateable) {
            capManual += monto;
        } else if (tipo === 'iva_importacion') {
            iva += monto;
        } else if (tipo === 'isd') {
            isd += monto;
        } else {
            otros += monto;
        }
    });

    const capTotal = capManual + capVinc;
    const totalFacturas = IMP_recalcularTotalFacturas();
    IMP_recalcularTotalGastos();

    document.getElementById('impLblGastosCap').textContent = capTotal.toFixed(2);
    document.getElementById('impLblIva').textContent = iva.toFixed(2);
    document.getElementById('impLblIsd').textContent = isd.toFixed(2);
    document.getElementById('impLblOtros').textContent = otros.toFixed(2);
    document.getElementById('impLblCostoTotal').textContent = (totalFacturas + capTotal).toFixed(2);

    const countEl = document.getElementById('impCountProductos');
    if (countEl) countEl.textContent = document.querySelectorAll('#tbodyProductosFob tr').length;
}

// ─────────────────────────────────────────────────────────────────────────────
// TAB PRORRATEO / RESUMEN
// ─────────────────────────────────────────────────────────────────────────────
window.IMP_calcularProrrateo = async function () {
    const id = document.getElementById('impId').value;
    if (!id) {
        Swal.fire('Atención', 'Guarda la importación antes de calcular el prorrateo.', 'warning');
        return;
    }
    const btn = document.getElementById('impBtnCalcularProrrateo');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Calculando...';

    try {
        const res = await fetch(`${window.CMG_urlBaseImp}/previsualizarProrrateoAjax?id=${id}`);
        const data = await res.json();
        if (!data.ok) {
            Swal.fire('Error', data.mensaje || 'No se pudo calcular el prorrateo.', 'error');
            return;
        }

        const tbody = document.getElementById('impProrrateoBody');
        tbody.innerHTML = '';
        if (!data.detalles.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">Sin líneas de producto.</td></tr>';
        } else {
            data.detalles.forEach(d => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="ps-3">${IMP_esc(d.producto_nombre || d.descripcion || '-')}</td>
                    <td class="text-center">${parseFloat(d.cantidad || 0)}</td>
                    <td class="text-end">${parseFloat(d.costo_unitario_nacionalizado || 0).toFixed(4)}</td>
                    <td class="text-end pe-3">${parseFloat(d.costo_total_nacionalizado || 0).toFixed(2)}</td>`;
                tbody.appendChild(tr);
            });
        }

        const t = data.totales;
        document.getElementById('impProrrateoTotalFactura').textContent = parseFloat(t.total_factura_exterior || 0).toFixed(2);
        document.getElementById('impProrrateoCapManual').textContent = parseFloat(t.capitalizable_manual || 0).toFixed(2);
        document.getElementById('impProrrateoCapVinc').textContent = parseFloat(t.capitalizable_vinculado || 0).toFixed(2);
        document.getElementById('impProrrateoIvaIsd').textContent = (parseFloat(t.iva || 0) + parseFloat(t.isd || 0)).toFixed(2);
        document.getElementById('impProrrateoOtros').textContent = parseFloat(t.otros || 0).toFixed(2);
        document.getElementById('impProrrateoCostoTotal').textContent = parseFloat(t.costo_total_nacionalizado || 0).toFixed(2);

        document.getElementById('impLblGastosCap').textContent = parseFloat(t.capitalizable_total || 0).toFixed(2);
        document.getElementById('impLblIva').textContent = parseFloat(t.iva || 0).toFixed(2);
        document.getElementById('impLblIsd').textContent = parseFloat(t.isd || 0).toFixed(2);
        document.getElementById('impLblOtros').textContent = parseFloat(t.otros || 0).toFixed(2);
        document.getElementById('impLblCostoTotal').textContent = parseFloat(t.costo_total_nacionalizado || 0).toFixed(2);

        document.getElementById('impProrrateoStatus').innerHTML = '<i class="bi bi-info-circle me-1"></i>Vista previa: estos valores se aplicarán definitivamente al procesar el inventario.';
    } catch (e) {
        console.error(e);
        Swal.fire('Error', 'No se pudo calcular el prorrateo.', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-calculator me-1"></i> Calcular prorrateo';
    }
};

window.IMP_procesarInventario = async function () {
    const id = document.getElementById('impId').value;
    if (!id) return;

    const confirm = await Swal.fire({
        title: '¿Procesar inventario / nacionalizar?',
        text: 'Se prorrateará el costo entre las líneas, se postearán las entradas al kardex y se generará el asiento contable. Esta acción no se puede deshacer desde este módulo.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, procesar',
        cancelButtonText: 'Cancelar',
    });
    if (!confirm.isConfirmed) return;

    const btn = document.getElementById('impBtnProcesarInventario');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Procesando...';

    try {
        const fd = new FormData();
        fd.append('id', id);
        const res = await fetch(`${window.CMG_urlBaseImp}/procesarInventarioAjax`, {
            method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if (data.ok) {
            Swal.fire({ icon: 'success', title: 'Nacionalizada', text: data.mensaje, timer: 2500, showConfirmButton: false });
            const res2 = await fetch(`${window.CMG_urlBaseImp}/getImportacionAjax?id=${id}`);
            const data2 = await res2.json();
            if (data2.ok) IMP_poblarModal(data2.data);
            if (typeof window.CMG_fetchSearchImp === 'function') window.CMG_fetchSearchImp(window.CMG_currentPageImp);
        } else {
            Swal.fire('Error', data.mensaje, 'error');
        }
    } catch (e) {
        Swal.fire('Error de conexión', e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-box-arrow-in-right me-1"></i> Procesar Inventario / Nacionalizar';
    }
};

async function IMP_recargarModalTrasAprobacion(id) {
    const res2 = await fetch(`${window.CMG_urlBaseImp}/getImportacionAjax?id=${id}`);
    const data2 = await res2.json();
    if (data2.ok) IMP_poblarModal(data2.data);
    if (typeof window.CMG_fetchSearchImp === 'function') window.CMG_fetchSearchImp(window.CMG_currentPageImp);
}

window.IMP_aprobarNacionalizacion = async function () {
    const id = document.getElementById('impId').value;
    if (!id) return;

    const confirm = await Swal.fire({
        title: '¿Aprobar y nacionalizar?',
        text: 'Se postearán las entradas al kardex con el costo ya calculado y se generará el asiento contable. Esta acción no se puede deshacer desde este módulo.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, aprobar',
        cancelButtonText: 'Cancelar',
    });
    if (!confirm.isConfirmed) return;

    const btn = document.getElementById('impBtnAprobar');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Aprobando...';

    try {
        const fd = new FormData();
        fd.append('id', id);
        const res = await fetch(`${window.CMG_urlBaseImp}/aprobarNacionalizacionAjax`, {
            method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if (data.ok) {
            Swal.fire({ icon: 'success', title: 'Aprobada', text: data.mensaje, timer: 2500, showConfirmButton: false });
            await IMP_recargarModalTrasAprobacion(id);
        } else {
            Swal.fire('Error', data.mensaje, 'error');
        }
    } catch (e) {
        Swal.fire('Error de conexión', e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Aprobar nacionalización';
    }
};

window.IMP_rechazarNacionalizacion = async function () {
    const id = document.getElementById('impId').value;
    if (!id) return;

    const { value: motivo, isConfirmed } = await Swal.fire({
        title: 'Rechazar nacionalización',
        input: 'textarea',
        inputLabel: 'Motivo del rechazo',
        inputPlaceholder: 'Indique por qué se rechaza esta importación...',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Rechazar',
        cancelButtonText: 'Cancelar',
        inputValidator: (v) => !v || !v.trim() ? 'Debe indicar el motivo del rechazo.' : undefined,
    });
    if (!isConfirmed) return;

    const btn = document.getElementById('impBtnRechazar');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Rechazando...';

    try {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('motivo', motivo.trim());
        const res = await fetch(`${window.CMG_urlBaseImp}/rechazarNacionalizacionAjax`, {
            method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if (data.ok) {
            Swal.fire({ icon: 'success', title: 'Rechazada', text: data.mensaje, timer: 2500, showConfirmButton: false });
            await IMP_recargarModalTrasAprobacion(id);
        } else {
            Swal.fire('Error', data.mensaje, 'error');
        }
    } catch (e) {
        Swal.fire('Error de conexión', e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-x-circle me-1"></i> Rechazar';
    }
};

// ─────────────────────────────────────────────────────────────────────────────
// TAB ASIENTO CONTABLE (vista previa reutilizable)
// ─────────────────────────────────────────────────────────────────────────────
let _impAsientoTab = null;
function IMP_asientoTab() {
    if (!_impAsientoTab && typeof window.crearAsientoTab === 'function') {
        _impAsientoTab = window.crearAsientoTab({
            tbodyId: 'imp-asiento-tbody',
            debeId: 'imp-asiento-debe',
            haberId: 'imp-asiento-haber',
            difId: 'imp-asiento-dif',
            badgeId: 'imp-asiento-badge',
            countId: 'imp-asiento-count',
            statusId: 'imp-asiento-status',
            previewUrl: `${window.CMG_urlBaseImp}/getAsientoSugeridoAjax`,
            cuentasUrl: `${window.BASE_URL}/modulos/plan-cuentas/searchAjaxCuentas`,
        });
    }
    return _impAsientoTab;
}

// ─────────────────────────────────────────────────────────────────────────────
// GUARDAR
// ─────────────────────────────────────────────────────────────────────────────
window.guardarImportacion = async function () {
    const modal = document.getElementById('modalImportacion');
    const id = document.getElementById('impId').value || modal.dataset.id || '';

    const idProveedor = document.getElementById('impIdProveedor').value;
    const idBodega = document.getElementById('impIdBodegaDestino').value;
    const puntoSelect = document.getElementById('impPuntoEmision');
    const puntoOpt = puntoSelect.options[puntoSelect.selectedIndex];

    if (!idProveedor) {
        Swal.fire('Atención', 'Debe seleccionar el proveedor del exterior.', 'warning');
        document.getElementById('impBuscarProveedor').focus();
        return;
    }
    if (!idBodega) {
        Swal.fire('Atención', 'Debe seleccionar la bodega destino.', 'warning');
        document.getElementById('impIdBodegaDestino').focus();
        return;
    }
    if (!id && !puntoSelect.value) {
        Swal.fire('Atención', 'Debe seleccionar la serie (establecimiento y punto de emisión).', 'warning');
        puntoSelect.focus();
        return;
    }
    // Bloqueo: secuenciales no configurados (solo al CREAR una nueva, igual que Factura de Venta).
    if (!id && window.IMP_SECUENCIAL_CONFIGURADO === false) {
        IMP_avisarSecuencialNoConfigurado('secuencial');
        return;
    }

    const detalles = [];
    document.querySelectorAll('#tbodyProductosFob tr').forEach(tr => {
        const descripcion = tr.querySelector('.input-imp-descripcion')?.value.trim() || '';
        if (!descripcion) return;
        detalles.push({
            id: tr.querySelector('.input-imp-id-detalle')?.value || null,
            id_producto: tr.querySelector('.input-imp-id-producto')?.value || null,
            codigo_producto_raw: tr.querySelector('.input-imp-codigo-raw')?.value || '',
            descripcion,
            cantidad: IMP_parseNum(tr.querySelector('.input-imp-cantidad')?.value),
            id_medida: tr.querySelector('.input-imp-id-medida')?.value || null,
            precio_unitario_fob: IMP_parseNum(tr.querySelector('.input-imp-precio-fob')?.value),
            precio_total_fob: IMP_parseNum(tr.querySelector('.input-imp-total-fob')?.value),
            peso_kg: IMP_parseNum(tr.querySelector('.input-imp-peso')?.value),
            volumen_m3: IMP_parseNum(tr.querySelector('.input-imp-volumen')?.value),
            numero_lote: tr.querySelector('.input-imp-lote')?.value || null,
            fecha_caducidad: tr.querySelector('.input-imp-caducidad')?.value || null,
            nup: tr.querySelector('.input-imp-nup')?.value || null,
            id_bodega: tr.querySelector('.input-imp-bodega')?.value || null,
        });
    });

    if (detalles.length === 0) {
        Swal.fire('Atención', 'Debe agregar al menos una línea de producto.', 'warning');
        const tabProd = document.getElementById('imp-tab-productos');
        if (tabProd) bootstrap.Tab.getOrCreateInstance(tabProd).show();
        return;
    }

    const facturas = [];
    document.querySelectorAll('#tbodyFacturasExterior tr').forEach(tr => {
        const monto = IMP_parseNum(tr.querySelector('.input-imp-fac-monto')?.value);
        const idProv = tr.querySelector('.input-imp-fac-id-proveedor')?.value || '';
        if (!idProv && monto <= 0) return;
        facturas.push({
            id: tr.querySelector('.input-imp-fac-id')?.value || null,
            id_proveedor: idProv || idProveedor,
            numero_factura: tr.querySelector('.input-imp-fac-numero')?.value || null,
            fecha_factura: tr.querySelector('.input-imp-fac-fecha')?.value || null,
            monto_usd: monto,
            forma_pago: tr.querySelector('.input-imp-fac-forma-pago')?.value || null,
            plazo_dias: parseInt(tr.querySelector('.input-imp-fac-plazo')?.value || 0),
        });
    });

    if (facturas.length === 0) {
        Swal.fire('Atención', 'Debe registrar al menos una factura del proveedor del exterior.', 'warning');
        const tabFac = document.getElementById('imp-tab-facturas');
        if (tabFac) bootstrap.Tab.getOrCreateInstance(tabFac).show();
        return;
    }

    const gastos = [];
    document.querySelectorAll('#tbodyGastosImp tr').forEach(tr => {
        const monto = IMP_parseNum(tr.querySelector('.input-imp-gasto-monto')?.value);
        if (monto <= 0) return;
        gastos.push({
            id: tr.querySelector('.input-imp-gasto-id')?.value || null,
            origen: tr.querySelector('.select-imp-gasto-origen')?.value || 'dai_manual',
            tipo_gasto: tr.querySelector('.select-imp-gasto-tipo')?.value || 'otro',
            id_compra: tr.querySelector('.input-imp-gasto-id-compra')?.value || null,
            id_liquidacion_compra: tr.querySelector('.input-imp-gasto-id-liquidacion')?.value || null,
            descripcion: tr.querySelector('.input-imp-gasto-descripcion')?.value || null,
            monto,
            prorrateable: !!tr.querySelector('.input-imp-gasto-prorrateable')?.checked,
        });
    });

    const payload = {
        id: id || undefined,
        id_establecimiento: puntoOpt ? puntoOpt.dataset.idEst : null,
        id_punto_emision: puntoSelect.value || null,
        referencia_dai: document.getElementById('impReferenciaDai').value || null,
        id_proveedor: idProveedor,
        id_agente_afianzado: document.getElementById('impIdAgente').value || null,
        id_bodega_destino: idBodega,
        incoterm: document.getElementById('impIncoterm').value || null,
        fecha_embarque: document.getElementById('impFechaEmbarque').value || null,
        fecha_llegada: document.getElementById('impFechaLlegada').value || null,
        fecha_nacionalizacion: document.getElementById('impFechaNacionalizacion').value || null,
        criterio_prorrateo: document.getElementById('impCriterioProrrateo').value || 'fob',
        observaciones: document.getElementById('impObservaciones').value || null,
        detalles, facturas_exterior: facturas, gastos,
    };

    const btn = document.getElementById('btnGuardarImportacion');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';

    try {
        const fd = new FormData();
        fd.append('data', JSON.stringify(payload));
        const res = await fetch(`${window.CMG_urlBaseImp}/guardarAjax`, {
            method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if (data.ok) {
            Swal.fire({ icon: 'success', title: 'Éxito', text: data.mensaje, timer: 1500, showConfirmButton: false });
            const res2 = await fetch(`${window.CMG_urlBaseImp}/getImportacionAjax?id=${data.id}`);
            const data2 = await res2.json();
            if (data2.ok) IMP_poblarModal(data2.data);
            if (typeof window.CMG_fetchSearchImp === 'function') window.CMG_fetchSearchImp(window.CMG_currentPageImp);
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: data.mensaje });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Error de conexión', text: e.message });
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar';
    }
};

// ─────────────────────────────────────────────────────────────────────────────
// ELIMINAR
// ─────────────────────────────────────────────────────────────────────────────
window.eliminarImportacion = async function () {
    const confirm = await Swal.fire({
        title: '¿Eliminar esta importación?',
        text: 'Esta acción no se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
    });
    if (!confirm.isConfirmed) return;

    const id = document.getElementById('modalImportacion').dataset.id;
    const fd = new FormData();
    fd.append('id', id);
    const res = await fetch(`${window.CMG_urlBaseImp}/eliminarAjax`, {
        method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    const data = await res.json();
    if (data.ok) {
        Swal.fire('Eliminado', data.mensaje, 'success');
        bootstrap.Modal.getInstance(document.getElementById('modalImportacion')).hide();
        if (typeof window.CMG_fetchSearchImp === 'function') window.CMG_fetchSearchImp(1);
    } else {
        Swal.fire('Error', data.mensaje, 'error');
    }
};
