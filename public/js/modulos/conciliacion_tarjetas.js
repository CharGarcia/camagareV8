/**
 * Conciliación de Tarjetas.
 *
 * Flujo de la pantalla de cruce: se elige una línea del estado de cuenta
 * (izquierda) y luego el cobro del sistema que le corresponde (derecha). El
 * botón "Cruzar automáticamente" propone los emparejamientos evidentes y el
 * usuario corrige lo que haga falta.
 *
 * Las peticiones usan fetch: public/js/csrf.js ya le adjunta el token.
 */

/* global CTAR_URL, CTAR_PERM, CTAR_PROCESADORAS, CTAR_DESTINOS, bootstrap, Swal */

let CTAR_vista        = 'pendientes';
let CTAR_detalle      = null;   // detalle completo de la conciliación abierta
let CTAR_lineaSel     = null;   // id de la línea seleccionada para cruzar
let CTAR_cobrosCache  = [];
let CTAR_perfilesCache = [];

const CTAR_CAMPOS_MAPEO = [
    ['fecha', 'Fecha', true],
    ['autorizacion', 'Autorización', false],
    ['referencia', 'Referencia', false],
    ['descripcion', 'Descripción', false],
    ['monto_bruto', 'Bruto', true],
    ['comision', 'Comisión', false],
    ['iva_comision', 'IVA comisión', false],
    ['retencion_ir', 'Retención renta', false],
    ['retencion_iva', 'Retención IVA', false],
    ['otros_descuentos', 'Otros descuentos', false],
    ['monto_neto', 'Neto', false],
];

// ─── Utilidades ─────────────────────────────────────────────────────────────

const CTAR_num = (v) => (parseFloat(v) || 0).toFixed(2);
const CTAR_esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
));

/** Fecha a d-m-Y (§9: siempre ese formato en pantalla). */
function CTAR_fecha(iso) {
    if (!iso) return '';
    const p = String(iso).substring(0, 10).split('-');
    return p.length === 3 ? `${p[2]}-${p[1]}-${p[0]}` : iso;
}

function CTAR_aviso(icon, title, text) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({ icon, title, text, confirmButtonColor: '#0d6efd' });
    } else {
        alert(`${title}\n\n${text || ''}`);
    }
}

async function CTAR_confirmar(title, text, confirmText = 'Sí, continuar') {
    if (typeof Swal === 'undefined') return confirm(`${title}\n\n${text || ''}`);
    const r = await Swal.fire({
        icon: 'warning', title, text, showCancelButton: true,
        confirmButtonText: confirmText, cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545', cancelButtonColor: '#6c757d',
    });
    return r.isConfirmed;
}

/** Envoltura de fetch: desempaqueta {ok,data} y convierte el error en excepción. */
async function CTAR_api(ruta, opciones = {}) {
    const resp = await fetch(`${CTAR_URL}/${ruta}`, opciones);
    let json;
    try {
        json = await resp.json();
    } catch (e) {
        throw new Error('El servidor devolvió una respuesta inesperada.');
    }
    if (!json.ok) throw new Error(json.error || 'No se pudo completar la operación.');
    return json.data;
}

const CTAR_post = (ruta, datos) => CTAR_api(ruta, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(datos),
});

/** Badge de días según los días de liquidación configurados para la procesadora. */
function CTAR_badgeDias(dias, diasEsperados) {
    const d = parseInt(dias, 10) || 0;
    const limite = parseInt(diasEsperados, 10) || 2;
    let clase = 'ctar-dias-ok';
    if (d > limite * 3) clase = 'ctar-dias-tarde';
    else if (d > limite) clase = 'ctar-dias-alerta';
    return `<span class="badge ${clase}">${d} d</span>`;
}

function CTAR_diasEsperados(idFormaCobro) {
    const p = CTAR_PROCESADORAS.find((x) => String(x.id) === String(idFormaCobro));
    return p ? (parseInt(p.dias_liquidacion, 10) || 2) : 2;
}

// ─── Listado ────────────────────────────────────────────────────────────────

function CTAR_setVista(vista) {
    CTAR_vista = vista;
    document.getElementById('ctar-vista-pendientes').classList.toggle('d-none', vista !== 'pendientes');
    document.getElementById('ctar-vista-conciliaciones').classList.toggle('d-none', vista !== 'conciliaciones');
    document.getElementById('ctar-tab-pendientes').classList.toggle('active', vista === 'pendientes');
    document.getElementById('ctar-tab-conciliaciones').classList.toggle('active', vista === 'conciliaciones');
    CTAR_cargar();
}

function CTAR_limpiarFiltros() {
    ['ctar-procesadora', 'ctar-estado', 'ctar-fecha-desde', 'ctar-fecha-hasta', 'ctar-buscar']
        .forEach((id) => { document.getElementById(id).value = ''; });
    CTAR_cargar();
}

function CTAR_filtrosActuales() {
    return {
        id_forma_cobro: document.getElementById('ctar-procesadora').value,
        estado:         document.getElementById('ctar-estado').value,
        fecha_desde:    document.getElementById('ctar-fecha-desde').value,
        fecha_hasta:    document.getElementById('ctar-fecha-hasta').value,
        buscar:         document.getElementById('ctar-buscar').value,
    };
}

async function CTAR_cargar() {
    return CTAR_vista === 'pendientes' ? CTAR_cargarPendientes() : CTAR_cargarConciliaciones();
}

async function CTAR_cargarPendientes() {
    const f = CTAR_filtrosActuales();
    const tbody = document.getElementById('ctar-tbody-pendientes');

    if (!f.id_forma_cobro) {
        // Sin procesadora elegida solo se muestran los indicadores globales.
        tbody.innerHTML = `<tr><td colspan="7" class="text-center py-5 text-muted">
            <i class="bi bi-credit-card-2-front fs-3 d-block mb-2 text-primary opacity-50"></i>
            Elija una procesadora en los filtros para ver sus cobros pendientes de depósito.
        </td></tr>`;
        document.getElementById('ctar-count-label').textContent = '';
        CTAR_actualizarKpisGlobales();
        return;
    }

    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Cargando…</td></tr>';

    try {
        const datos = await CTAR_api(`pendientesAjax?${new URLSearchParams(f)}`);
        const limite = CTAR_diasEsperados(f.id_forma_cobro);

        if (!datos.length) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-5 text-muted">
                <i class="bi bi-check2-circle fs-3 d-block mb-2 text-success opacity-50"></i>
                No hay cobros pendientes de depósito para esta procesadora.
            </td></tr>`;
        } else {
            tbody.innerHTML = datos.map((c) => `
                <tr>
                    <td class="ps-3">${CTAR_fecha(c.fecha_emision)}</td>
                    <td class="fw-medium">${CTAR_esc(c.documentos || '—')}</td>
                    <td>${CTAR_esc(c.cliente_nombre || '—')}</td>
                    <td class="text-muted small">${CTAR_esc(c.numero_ingreso || '')}</td>
                    <td class="small">${CTAR_esc(c.autorizacion || c.referencia || '—')}</td>
                    <td class="text-end fw-bold">$${CTAR_num(c.monto)}</td>
                    <td class="text-center">${CTAR_badgeDias(c.dias_transcurridos, limite)}</td>
                </tr>`).join('');
        }

        const total = datos.reduce((s, c) => s + (parseFloat(c.monto) || 0), 0);
        document.getElementById('ctar-count-label').textContent =
            `${datos.length} cobros · $${CTAR_num(total)}`;
        document.getElementById('ctar-badge-pendientes').textContent = datos.length;

        CTAR_actualizarKpisGlobales();
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-danger">${CTAR_esc(e.message)}</td></tr>`;
    }
}

async function CTAR_cargarConciliaciones() {
    const f = CTAR_filtrosActuales();
    const tbody = document.getElementById('ctar-tbody');
    tbody.innerHTML = '<tr><td colspan="12" class="text-center py-4 text-muted">Cargando…</td></tr>';

    try {
        const datos = await CTAR_api(`listarAjax?${new URLSearchParams({ ...f, per_page: 100 })}`);
        const filas = datos.data || [];

        if (!filas.length) {
            tbody.innerHTML = `<tr><td colspan="12" class="text-center py-5 text-muted">
                <i class="bi bi-list-check fs-3 d-block mb-2 text-primary opacity-50"></i>
                No hay conciliaciones registradas con esos filtros.
            </td></tr>`;
        } else {
            tbody.innerHTML = filas.map((c) => {
                const retenciones = (parseFloat(c.total_retencion_ir) || 0) + (parseFloat(c.total_retencion_iva) || 0);
                const comision    = (parseFloat(c.total_comision) || 0) + (parseFloat(c.total_iva_comision) || 0);
                const asiento = c.id_asiento_contable
                    ? '<i class="bi bi-check-circle-fill text-success" title="Asiento generado"></i>'
                    : `<i class="bi bi-dash-circle text-muted" title="${CTAR_esc(c.asiento_omitido_motivo || 'Sin asiento')}"></i>`;

                return `<tr>
                    <td class="ps-3 fw-bold">${CTAR_esc(c.numero)}</td>
                    <td>${CTAR_fecha(c.fecha_conciliacion)}</td>
                    <td>${CTAR_esc(c.procesadora_nombre || '')}</td>
                    <td>${CTAR_esc(c.destino_nombre || '—')}</td>
                    <td class="text-center">${c.cobros_cruzados || 0}</td>
                    <td class="text-end">$${CTAR_num(c.total_bruto_cruzado)}</td>
                    <td class="text-end text-secondary">$${CTAR_num(comision)}</td>
                    <td class="text-end text-secondary">$${CTAR_num(retenciones)}</td>
                    <td class="text-end fw-bold text-primary">$${CTAR_num(c.total_neto)}</td>
                    <td class="text-center"><span class="badge ctar-estado-${CTAR_esc(c.estado)}">${CTAR_esc(c.estado)}</span></td>
                    <td class="text-center">${asiento}</td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-primary py-0 px-1" onclick="CTAR_abrir(${c.id})" title="Abrir">
                            <i class="bi bi-box-arrow-in-right"></i>
                        </button>
                    </td>
                </tr>`;
            }).join('');
        }

        document.getElementById('ctar-count-label').textContent = `${datos.total} conciliaciones`;
        CTAR_pintarKpis(datos.resumen || [], filas);
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="12" class="text-center py-4 text-danger">${CTAR_esc(e.message)}</td></tr>`;
    }
}

async function CTAR_actualizarKpisGlobales() {
    try {
        const datos = await CTAR_api(`listarAjax?${new URLSearchParams({ ...CTAR_filtrosActuales(), per_page: 100 })}`);
        CTAR_pintarKpis(datos.resumen || [], datos.data || []);
    } catch (e) { /* los KPI son informativos: si fallan, la tabla ya mostró el error */ }
}

function CTAR_pintarKpis(resumen, conciliaciones) {
    const idForma = document.getElementById('ctar-procesadora').value;
    const filtrado = idForma
        ? resumen.filter((r) => String(r.id_forma_cobro) === String(idForma))
        : resumen;

    const monto  = filtrado.reduce((s, r) => s + (parseFloat(r.monto) || 0), 0);
    const cobros = filtrado.reduce((s, r) => s + (parseInt(r.cobros, 10) || 0), 0);
    const dias   = filtrado.reduce((m, r) => Math.max(m, parseInt(r.dias_max, 10) || 0), 0);

    const conciliado = conciliaciones
        .filter((c) => c.estado === 'cerrada')
        .reduce((s, c) => s + (parseFloat(c.total_neto) || 0), 0);
    const comisiones = conciliaciones
        .filter((c) => c.estado === 'cerrada')
        .reduce((s, c) => s + (parseFloat(c.total_comision) || 0) + (parseFloat(c.total_iva_comision) || 0), 0);

    document.getElementById('ctar-stat-pendiente').textContent  = CTAR_num(monto);
    document.getElementById('ctar-stat-cobros').textContent     = cobros;
    document.getElementById('ctar-stat-dias').textContent       = dias;
    document.getElementById('ctar-stat-conciliado').textContent = CTAR_num(conciliado);
    document.getElementById('ctar-stat-comision').textContent   = CTAR_num(comisiones);
    document.getElementById('ctar-badge-pendientes').textContent = cobros;
}

// ─── Modal de conciliación ──────────────────────────────────────────────────

function CTAR_opcionesProcesadoras(sel) {
    return CTAR_PROCESADORAS.map((p) =>
        `<option value="${p.id}" ${String(p.id) === String(sel) ? 'selected' : ''}>${CTAR_esc(p.nombre)}</option>`
    ).join('');
}

function CTAR_opcionesDestinos(sel) {
    return `<option value="">— Seleccione —</option>` + CTAR_DESTINOS.map((d) =>
        `<option value="${d.id}" ${String(d.id) === String(sel) ? 'selected' : ''}>${CTAR_esc(d.nombre)}</option>`
    ).join('');
}

function CTAR_nueva() {
    if (!CTAR_PROCESADORAS.length) {
        CTAR_aviso('warning', 'Sin formas de cobro con tarjeta',
            'Esta empresa no tiene formas de cobro de tipo Payphone, Nuvei o Tarjeta.');
        return;
    }

    CTAR_detalle = null;
    CTAR_lineaSel = null;

    document.getElementById('ctar-m-id').value = '';
    document.getElementById('ctar-m-titulo').textContent = 'Nueva conciliación';
    document.getElementById('ctar-m-procesadora').innerHTML = CTAR_opcionesProcesadoras(
        document.getElementById('ctar-procesadora').value
    );
    document.getElementById('ctar-m-procesadora').disabled = false;
    document.getElementById('ctar-m-destino').innerHTML = CTAR_opcionesDestinos('');
    document.getElementById('ctar-m-fecha').value = new Date().toISOString().substring(0, 10);
    document.getElementById('ctar-m-desde').value = '';
    document.getElementById('ctar-m-hasta').value = '';
    document.getElementById('ctar-m-neto').value  = '';
    document.getElementById('ctar-m-estado').classList.add('d-none');
    document.getElementById('ctar-m-aviso-conta').classList.add('d-none');

    document.getElementById('ctar-m-tbody-lineas').innerHTML =
        '<tr><td colspan="8" class="text-center py-4 text-muted small">Guarde la conciliación y luego cargue el estado de cuenta.</td></tr>';
    document.getElementById('ctar-m-tbody-cobros').innerHTML =
        '<tr><td colspan="4" class="text-center py-4 text-muted small">—</td></tr>';

    CTAR_habilitarAcciones(false, true);
    new bootstrap.Modal(document.getElementById('modalConciliacion')).show();
}

async function CTAR_abrir(id) {
    try {
        CTAR_detalle = await CTAR_api(`detalleAjax?id=${id}`);
        CTAR_lineaSel = null;
        CTAR_pintarModal();
        new bootstrap.Modal(document.getElementById('modalConciliacion')).show();
    } catch (e) {
        CTAR_aviso('error', 'No se pudo abrir', e.message);
    }
}

async function CTAR_refrescarDetalle() {
    const id = document.getElementById('ctar-m-id').value;
    if (!id) return;
    CTAR_detalle = await CTAR_api(`detalleAjax?id=${id}`);
    CTAR_pintarModal();
}

function CTAR_pintarModal() {
    const d   = CTAR_detalle;
    const cab = d.cabecera;
    const editable = cab.estado === 'borrador';

    document.getElementById('ctar-m-id').value = cab.id;
    document.getElementById('ctar-m-titulo').textContent = `Conciliación ${cab.numero}`;

    const badge = document.getElementById('ctar-m-estado');
    badge.className = `badge ctar-estado-${cab.estado}`;
    badge.textContent = cab.estado;
    badge.classList.remove('d-none');

    document.getElementById('ctar-m-procesadora').innerHTML = CTAR_opcionesProcesadoras(cab.id_forma_cobro);
    document.getElementById('ctar-m-procesadora').disabled = true;   // no se cambia una vez creada
    document.getElementById('ctar-m-destino').innerHTML = CTAR_opcionesDestinos(cab.id_forma_cobro_destino);
    document.getElementById('ctar-m-fecha').value = (cab.fecha_conciliacion || '').substring(0, 10);
    document.getElementById('ctar-m-desde').value = (cab.fecha_desde || '').substring(0, 10);
    document.getElementById('ctar-m-hasta').value = (cab.fecha_hasta || '').substring(0, 10);
    document.getElementById('ctar-m-neto').value  = parseFloat(cab.neto_depositado) > 0 ? CTAR_num(cab.neto_depositado) : '';

    ['ctar-m-destino', 'ctar-m-fecha', 'ctar-m-desde', 'ctar-m-hasta', 'ctar-m-neto']
        .forEach((id) => { document.getElementById(id).disabled = !editable; });

    CTAR_pintarLineas();
    CTAR_pintarCobros();
    CTAR_pintarTotales();
    CTAR_pintarAvisoContable();
    CTAR_habilitarAcciones(editable, false);
}

function CTAR_habilitarAcciones(editable, esNueva) {
    const mostrar = (id, visible) => document.getElementById(id).classList.toggle('d-none', !visible);

    ['ctar-btn-cargar', 'ctar-btn-linea', 'ctar-btn-sugerir'].forEach((id) => {
        document.getElementById(id).disabled = esNueva || !editable;
    });

    document.getElementById('ctar-btn-guardar').classList.toggle('d-none', !editable && !esNueva);
    document.getElementById('ctar-btn-conciliar').classList.toggle('d-none', esNueva || !editable);
    mostrar('ctar-btn-eliminar', CTAR_PERM.eliminar && !esNueva && editable);
    mostrar('ctar-btn-anular', CTAR_PERM.actualizar && !esNueva && !editable
        && CTAR_detalle && CTAR_detalle.cabecera.estado === 'cerrada');
    mostrar('ctar-m-acciones', !esNueva);
}

function CTAR_pintarAvisoContable() {
    const aviso = document.getElementById('ctar-m-aviso-conta');
    const texto = document.getElementById('ctar-m-aviso-conta-texto');
    const c = CTAR_detalle.contabilidad;
    const motivoGuardado = CTAR_detalle.cabecera.asiento_omitido_motivo;

    if (CTAR_detalle.cabecera.id_asiento_contable) {
        aviso.className = 'alert alert-success py-1 px-2 mt-2 mb-0 small';
        texto.textContent = 'Asiento contable generado para este depósito.';
        aviso.classList.remove('d-none');
        return;
    }

    const mensaje = c && !c.puede ? c.motivo : motivoGuardado;
    if (!mensaje) { aviso.classList.add('d-none'); return; }

    aviso.className = 'alert alert-warning py-1 px-2 mt-2 mb-0 small';
    texto.textContent = mensaje;
    aviso.classList.remove('d-none');
}

// ─── Líneas del estado de cuenta ────────────────────────────────────────────

function CTAR_pintarLineas() {
    const tbody = document.getElementById('ctar-m-tbody-lineas');
    const lineas = CTAR_detalle.lineas || [];
    const editable = CTAR_detalle.cabecera.estado === 'borrador';

    document.getElementById('ctar-m-resumen-lineas').textContent =
        `${lineas.length} líneas · ${CTAR_detalle.totales.lineas_cruzadas} cruzadas · ${CTAR_detalle.totales.lineas_sin_cobro} sin documento`;

    if (!lineas.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted small">Cargue el estado de cuenta o agregue líneas a mano.</td></tr>';
        return;
    }

    tbody.innerHTML = lineas.map((l) => {
        const retenciones = (parseFloat(l.retencion_ir) || 0) + (parseFloat(l.retencion_iva) || 0);
        const seleccionada = String(l.id) === String(CTAR_lineaSel);

        let estado = '<span class="badge bg-secondary bg-opacity-25 text-secondary">pendiente</span>';
        if (l.estado === 'cruzada') estado = '<span class="badge ctar-estado-cerrada">cruzada</span>';
        if (l.estado === 'sin_cobro') estado = '<span class="badge ctar-estado-anulada">sin documento</span>';

        const cruzados = (l.cruces_detalle || []).map((cr) => `
            <div class="small text-muted ps-3">
                <i class="bi bi-arrow-return-right"></i>
                ${CTAR_esc(cr.documentos || cr.numero_ingreso)} — ${CTAR_esc(cr.cliente_nombre || '')} · $${CTAR_num(cr.monto_cruzado)}
                ${editable ? `<button class="btn btn-link btn-sm text-danger py-0 px-1"
                        onclick="CTAR_descruzar(${cr.id})" title="Deshacer cruce"><i class="bi bi-x-lg"></i></button>` : ''}
            </div>`).join('');

        return `<tr class="${seleccionada ? 'table-primary' : ''}" style="cursor:${editable ? 'pointer' : 'default'};"
                    onclick="${editable ? `CTAR_seleccionarLinea(${l.id})` : ''}">
                <td class="ps-3">${CTAR_fecha(l.fecha_movimiento)}
                    ${l.tipo_linea === 'deposito' ? '<span class="badge bg-info bg-opacity-25 text-info ms-1">depósito</span>' : ''}
                </td>
                <td class="small">${CTAR_esc(l.autorizacion || l.referencia || '—')}</td>
                <td class="text-end fw-bold">$${CTAR_num(l.monto_bruto)}</td>
                <td class="text-end text-secondary">$${CTAR_num(l.comision)}</td>
                <td class="text-end text-secondary">$${CTAR_num(retenciones)}</td>
                <td class="text-end">$${CTAR_num(l.monto_neto)}</td>
                <td class="text-center">${estado}</td>
                <td class="text-center" onclick="event.stopPropagation();">
                    ${editable ? `
                        <button class="btn btn-sm btn-outline-secondary py-0 px-1" onclick="CTAR_editarLinea(${l.id})" title="Editar">
                            <i class="bi bi-pencil"></i></button>
                        <button class="btn btn-sm btn-outline-warning py-0 px-1"
                                onclick="CTAR_marcarSinCobro(${l.id}, ${l.estado !== 'sin_cobro'})"
                                title="${l.estado === 'sin_cobro' ? 'Devolver a pendiente' : 'Entró sin documento'}">
                            <i class="bi bi-exclamation-triangle"></i></button>
                        <button class="btn btn-sm btn-outline-danger py-0 px-1" onclick="CTAR_eliminarLinea(${l.id})" title="Eliminar">
                            <i class="bi bi-trash"></i></button>` : ''}
                </td>
            </tr>
            ${cruzados ? `<tr class="${seleccionada ? 'table-primary' : ''}"><td colspan="8" class="py-0 pb-1">${cruzados}</td></tr>` : ''}`;
    }).join('');
}

function CTAR_seleccionarLinea(id) {
    CTAR_lineaSel = String(CTAR_lineaSel) === String(id) ? null : id;
    CTAR_pintarLineas();
    CTAR_pintarCobros();
}

// ─── Cobros del sistema ─────────────────────────────────────────────────────

function CTAR_pintarCobros(filtro = '') {
    const tbody = document.getElementById('ctar-m-tbody-cobros');
    const editable = CTAR_detalle.cabecera.estado === 'borrador';
    const limite = CTAR_diasEsperados(CTAR_detalle.cabecera.id_forma_cobro);

    // Solo los que aún no están cruzados en esta conciliación.
    CTAR_cobrosCache = (CTAR_detalle.cobros || []).filter((c) => !c.id_cruce);

    const q = filtro.trim().toLowerCase();
    const visibles = q
        ? CTAR_cobrosCache.filter((c) => (
            `${c.documentos || ''} ${c.cliente_nombre || ''} ${c.numero_ingreso || ''} ${c.monto}`
        ).toLowerCase().includes(q))
        : CTAR_cobrosCache;

    document.getElementById('ctar-m-resumen-cobros').textContent = `${CTAR_cobrosCache.length} disponibles`;

    if (!visibles.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted small">Sin cobros pendientes.</td></tr>';
        return;
    }

    const puedeCruzar = editable && CTAR_lineaSel;

    tbody.innerHTML = visibles.map((c) => `
        <tr style="cursor:${puedeCruzar ? 'pointer' : 'default'};"
            ${puedeCruzar ? `onclick="CTAR_cruzarCon(${c.id_ingreso_pago})"` : ''}
            title="${puedeCruzar ? 'Cruzar con la línea seleccionada' : 'Seleccione primero una línea del estado de cuenta'}">
            <td class="ps-3 fw-medium">${CTAR_esc(c.documentos || c.numero_ingreso || '—')}</td>
            <td class="small">${CTAR_esc(c.cliente_nombre || '—')}</td>
            <td class="text-end fw-bold">$${CTAR_num(c.monto)}</td>
            <td class="text-center">${CTAR_badgeDias(c.dias_transcurridos, limite)}</td>
        </tr>`).join('');
}

function CTAR_filtrarCobros(q) {
    if (CTAR_detalle) CTAR_pintarCobros(q);
}

async function CTAR_cruzarCon(idIngresoPago) {
    if (!CTAR_lineaSel) {
        CTAR_aviso('info', 'Seleccione una línea', 'Primero elija la línea del estado de cuenta que quiere cruzar.');
        return;
    }
    try {
        const r = await CTAR_post('cruzarAjax', {
            id_cabecera: document.getElementById('ctar-m-id').value,
            pares: [{ id_linea: CTAR_lineaSel, id_ingreso_pago: idIngresoPago, origen: 'manual' }],
        });
        if (r.omitidos && r.omitidos.length) {
            CTAR_aviso('warning', 'No se pudo cruzar', r.omitidos[0].motivo);
        }
        CTAR_lineaSel = null;
        await CTAR_refrescarDetalle();
    } catch (e) {
        CTAR_aviso('error', 'Error al cruzar', e.message);
    }
}

async function CTAR_descruzar(idCruce) {
    try {
        await CTAR_post('descruzarAjax', { id: idCruce });
        await CTAR_refrescarDetalle();
    } catch (e) {
        CTAR_aviso('error', 'No se pudo deshacer', e.message);
    }
}

async function CTAR_sugerir() {
    const id = document.getElementById('ctar-m-id').value;
    if (!id) return;

    try {
        const sugerencias = await CTAR_api(`sugerirAjax?id=${id}`);
        if (!sugerencias.length) {
            CTAR_aviso('info', 'Sin sugerencias',
                'No se encontraron emparejamientos evidentes. Cruce las líneas a mano seleccionando cada una y su cobro.');
            return;
        }

        const pares = [];
        sugerencias.forEach((s) => s.cobros.forEach((c) => pares.push({
            id_linea: s.id_linea,
            id_ingreso_pago: c.id_ingreso_pago,
            origen: 'auto',
            score: s.score,
            criterio: s.criterio,
        })));

        const r = await CTAR_post('cruzarAjax', { id_cabecera: id, pares });
        await CTAR_refrescarDetalle();

        CTAR_aviso('success', 'Cruce automático',
            `Se emparejaron ${r.creados} cobros.` + (r.omitidos.length ? ` ${r.omitidos.length} no se pudieron cruzar.` : ''));
    } catch (e) {
        CTAR_aviso('error', 'Error al sugerir', e.message);
    }
}

// ─── Totales ────────────────────────────────────────────────────────────────

function CTAR_pintarTotales() {
    const t = CTAR_detalle.totales;
    const retenciones = (parseFloat(t.total_retencion_ir) || 0) + (parseFloat(t.total_retencion_iva) || 0);

    document.getElementById('ctar-m-t-bruto').textContent       = CTAR_num(t.total_bruto_cruzado);
    document.getElementById('ctar-m-t-comision').textContent    = CTAR_num(t.total_comision);
    document.getElementById('ctar-m-t-iva').textContent         = CTAR_num(t.total_iva_comision);
    document.getElementById('ctar-m-t-retenciones').textContent = CTAR_num(retenciones);
    document.getElementById('ctar-m-t-neto').textContent        = CTAR_num(t.total_neto);

    CTAR_recalcularDiferencia();
}

/** La diferencia se recalcula en vivo mientras el usuario digita el depósito. */
function CTAR_recalcularDiferencia() {
    if (!CTAR_detalle) return;

    const neto = parseFloat(CTAR_detalle.totales.total_neto) || 0;
    const declarado = parseFloat(document.getElementById('ctar-m-neto').value);
    const diferencia = (isNaN(declarado) || declarado === 0) ? 0 : declarado - neto;

    document.getElementById('ctar-m-t-diferencia').textContent = CTAR_num(diferencia);
    document.getElementById('ctar-m-t-diferencia-wrap').className =
        Math.abs(diferencia) < 0.005 ? 'fw-bold text-success' : 'fw-bold text-danger';
}

// ─── Guardar / cerrar / anular / eliminar ───────────────────────────────────

function CTAR_datosCabecera() {
    return {
        id: document.getElementById('ctar-m-id').value || 0,
        id_forma_cobro: document.getElementById('ctar-m-procesadora').value,
        id_forma_cobro_destino: document.getElementById('ctar-m-destino').value,
        fecha_conciliacion: document.getElementById('ctar-m-fecha').value,
        fecha_desde: document.getElementById('ctar-m-desde').value,
        fecha_hasta: document.getElementById('ctar-m-hasta').value,
        neto_depositado: document.getElementById('ctar-m-neto').value || 0,
    };
}

async function CTAR_guardar() {
    try {
        const r = await CTAR_post('guardarAjax', CTAR_datosCabecera());
        document.getElementById('ctar-m-id').value = r.id;
        await CTAR_refrescarDetalle();
        CTAR_cargar();
        CTAR_aviso('success', 'Guardado', 'La conciliación se guardó. Ahora puede cargar el estado de cuenta.');
    } catch (e) {
        CTAR_aviso('error', 'No se pudo guardar', e.message);
    }
}

async function CTAR_cerrarConciliacion() {
    const id = document.getElementById('ctar-m-id').value;
    if (!id) return;

    if (!await CTAR_confirmar('¿Cerrar la conciliación?',
        'Los cobros cruzados quedarán conciliados y, si hay cuentas configuradas, se generará el asiento contable.',
        'Sí, conciliar')) return;

    try {
        // Se guardan primero banco destino y neto depositado, que son parte del cierre.
        await CTAR_post('guardarAjax', CTAR_datosCabecera());
        const r = await CTAR_post('cerrarAjax', { id });

        await CTAR_refrescarDetalle();
        CTAR_cargar();

        if (r.id_asiento) {
            CTAR_aviso('success', 'Conciliación cerrada', 'Se generó el asiento contable del depósito.');
        } else {
            CTAR_aviso('info', 'Conciliación cerrada',
                r.motivo || 'La conciliación quedó registrada sin asiento contable.');
        }
    } catch (e) {
        CTAR_aviso('error', 'No se pudo cerrar', e.message);
    }
}

async function CTAR_anular() {
    const id = document.getElementById('ctar-m-id').value;
    if (!await CTAR_confirmar('¿Anular la conciliación?',
        'Se revertirá el asiento contable y los cobros volverán a quedar pendientes de depósito.',
        'Sí, anular')) return;

    try {
        await CTAR_post('anularAjax', { id });
        await CTAR_refrescarDetalle();
        CTAR_cargar();
        CTAR_aviso('success', 'Anulada', 'La conciliación fue anulada.');
    } catch (e) {
        CTAR_aviso('error', 'No se pudo anular', e.message);
    }
}

async function CTAR_eliminar() {
    const id = document.getElementById('ctar-m-id').value;
    if (!id) return;

    if (!await CTAR_confirmar('¿Eliminar la conciliación?',
        'Se eliminará junto con sus líneas y cruces. Los cobros volverán a quedar pendientes.')) return;

    try {
        await CTAR_post('eliminarAjax', { id });
        bootstrap.Modal.getInstance(document.getElementById('modalConciliacion')).hide();
        CTAR_cargar();
        CTAR_aviso('success', 'Eliminada', 'La conciliación fue eliminada.');
    } catch (e) {
        CTAR_aviso('error', 'No se pudo eliminar', e.message);
    }
}

// ─── Carga del estado de cuenta ─────────────────────────────────────────────

async function CTAR_abrirCargaArchivo() {
    const idForma = CTAR_detalle ? CTAR_detalle.cabecera.id_forma_cobro : '';
    try {
        CTAR_perfilesCache = await CTAR_api(`listarPerfilesAjax?id_forma_cobro=${idForma}`);
    } catch (e) {
        CTAR_perfilesCache = [];
    }

    const sel = document.getElementById('ctar-carga-perfil');
    sel.innerHTML = CTAR_perfilesCache.length
        ? CTAR_perfilesCache.map((p) =>
            `<option value="${p.id}">${CTAR_esc(p.nombre_perfil)} (${CTAR_esc(p.tipo_archivo)}, ${p.nivel === 'deposito' ? 'depósitos' : 'transacciones'})</option>`
        ).join('')
        : '<option value="">— No hay perfiles configurados —</option>';

    document.getElementById('ctar-carga-archivo').value = '';
    new bootstrap.Modal(document.getElementById('modalCargaEstado')).show();
}

async function CTAR_importar() {
    const idPerfil = document.getElementById('ctar-carga-perfil').value;
    const archivo  = document.getElementById('ctar-carga-archivo').files[0];

    if (!idPerfil) {
        CTAR_aviso('warning', 'Falta el perfil', 'Cree un perfil de lectura en Configuración → Perfiles.');
        return;
    }
    if (!archivo) {
        CTAR_aviso('warning', 'Falta el archivo', 'Seleccione el estado de cuenta a cargar.');
        return;
    }

    const fd = new FormData();
    fd.append('id', document.getElementById('ctar-m-id').value);
    fd.append('id_perfil', idPerfil);
    fd.append('archivo', archivo);

    try {
        const r = await CTAR_api('importarAjax', { method: 'POST', body: fd });
        bootstrap.Modal.getInstance(document.getElementById('modalCargaEstado')).hide();
        await CTAR_refrescarDetalle();
        CTAR_aviso('success', 'Estado de cuenta cargado',
            `Se leyeron ${r.insertadas} líneas de ${r.total_leidas}.` +
            (r.descartadas ? ` ${r.descartadas} filas se descartaron por no tener fecha o valor.` : ''));
    } catch (e) {
        CTAR_aviso('error', 'No se pudo cargar', e.message);
    }
}

// ─── Línea manual ───────────────────────────────────────────────────────────

function CTAR_limpiarFormLinea() {
    document.getElementById('ctar-linea-id').value = '';
    document.getElementById('ctar-linea-fecha').value = new Date().toISOString().substring(0, 10);
    document.getElementById('ctar-linea-tipo').value = 'transaccion';
    ['autorizacion', 'referencia', 'descripcion'].forEach((c) => {
        document.getElementById(`ctar-linea-${c}`).value = '';
    });
    ['bruto', 'comision', 'iva', 'retir', 'retiva', 'otros'].forEach((c) => {
        document.getElementById(`ctar-linea-${c}`).value = '';
    });
    document.getElementById('ctar-linea-neto').textContent = '0.00';
}

function CTAR_agregarLineaManual() {
    CTAR_limpiarFormLinea();
    document.getElementById('ctar-linea-titulo').textContent = 'Nueva línea del estado de cuenta';
    new bootstrap.Modal(document.getElementById('modalLineaTarjeta')).show();
}

function CTAR_editarLinea(id) {
    const l = (CTAR_detalle.lineas || []).find((x) => String(x.id) === String(id));
    if (!l) return;

    CTAR_limpiarFormLinea();
    document.getElementById('ctar-linea-titulo').textContent = 'Editar línea del estado de cuenta';
    document.getElementById('ctar-linea-id').value = l.id;
    document.getElementById('ctar-linea-fecha').value = (l.fecha_movimiento || '').substring(0, 10);
    document.getElementById('ctar-linea-tipo').value = l.tipo_linea || 'transaccion';
    document.getElementById('ctar-linea-autorizacion').value = l.autorizacion || '';
    document.getElementById('ctar-linea-referencia').value = l.referencia || '';
    document.getElementById('ctar-linea-descripcion').value = l.descripcion || '';
    document.getElementById('ctar-linea-bruto').value = l.monto_bruto;
    document.getElementById('ctar-linea-comision').value = l.comision;
    document.getElementById('ctar-linea-iva').value = l.iva_comision;
    document.getElementById('ctar-linea-retir').value = l.retencion_ir;
    document.getElementById('ctar-linea-retiva').value = l.retencion_iva;
    document.getElementById('ctar-linea-otros').value = l.otros_descuentos;
    CTAR_recalcularNetoLinea();

    new bootstrap.Modal(document.getElementById('modalLineaTarjeta')).show();
}

function CTAR_recalcularNetoLinea() {
    const v = (id) => parseFloat(document.getElementById(`ctar-linea-${id}`).value) || 0;
    const neto = v('bruto') - v('comision') - v('iva') - v('retir') - v('retiva') - v('otros');
    document.getElementById('ctar-linea-neto').textContent = CTAR_num(neto);
}

function CTAR_datosLinea() {
    const v = (id) => parseFloat(document.getElementById(`ctar-linea-${id}`).value) || 0;
    const neto = v('bruto') - v('comision') - v('iva') - v('retir') - v('retiva') - v('otros');

    return {
        id: document.getElementById('ctar-linea-id').value || 0,
        id_cabecera: document.getElementById('ctar-m-id').value,
        fecha_movimiento: document.getElementById('ctar-linea-fecha').value,
        tipo_linea: document.getElementById('ctar-linea-tipo').value,
        autorizacion: document.getElementById('ctar-linea-autorizacion').value,
        referencia: document.getElementById('ctar-linea-referencia').value,
        descripcion: document.getElementById('ctar-linea-descripcion').value,
        monto_bruto: v('bruto'),
        comision: v('comision'),
        iva_comision: v('iva'),
        retencion_ir: v('retir'),
        retencion_iva: v('retiva'),
        otros_descuentos: v('otros'),
        monto_neto: neto,
    };
}

async function CTAR_guardarLinea() {
    const datos = CTAR_datosLinea();
    try {
        await CTAR_post(datos.id > 0 ? 'guardarLineaAjax' : 'agregarLineaAjax', datos);
        bootstrap.Modal.getInstance(document.getElementById('modalLineaTarjeta')).hide();
        await CTAR_refrescarDetalle();
    } catch (e) {
        CTAR_aviso('error', 'No se pudo guardar la línea', e.message);
    }
}

async function CTAR_eliminarLinea(id) {
    if (!await CTAR_confirmar('¿Eliminar la línea?', 'También se deshará su cruce, si lo tiene.')) return;
    try {
        await CTAR_post('eliminarLineaAjax', { id });
        await CTAR_refrescarDetalle();
    } catch (e) {
        CTAR_aviso('error', 'No se pudo eliminar', e.message);
    }
}

async function CTAR_marcarSinCobro(id, sinCobro) {
    try {
        await CTAR_post('marcarSinCobroAjax', { id, sin_cobro: sinCobro });
        await CTAR_refrescarDetalle();
    } catch (e) {
        CTAR_aviso('error', 'No se pudo marcar', e.message);
    }
}

// ─── Configuración contable ─────────────────────────────────────────────────

function CTAR_abrirConfig() {
    const sel = document.getElementById('ctar-cfg-procesadora');
    sel.innerHTML = CTAR_opcionesProcesadoras('');
    document.getElementById('ctar-perfil-forma').innerHTML =
        '<option value="">Cualquiera</option>' + CTAR_opcionesProcesadoras('');

    CTAR_pintarCamposMapeo();
    CTAR_cargarConfig();
    CTAR_cargarPerfiles();
    CTAR_cancelarPerfil();

    new bootstrap.Modal(document.getElementById('modalConfigTarjetas')).show();
}

async function CTAR_cargarConfig() {
    const idForma = document.getElementById('ctar-cfg-procesadora').value;
    if (!idForma) return;

    // Estado de la cuenta puente, que vive en la forma de cobro.
    const p = CTAR_PROCESADORAS.find((x) => String(x.id) === String(idForma));
    const puente = document.getElementById('ctar-cfg-puente-texto');
    if (p && p.cuenta_codigo) {
        puente.innerHTML = `<i class="bi bi-check-circle-fill text-success me-1"></i>
            <strong>${CTAR_esc(p.cuenta_codigo)}</strong> — ${CTAR_esc(p.cuenta_nombre || '')}`;
    } else {
        puente.innerHTML = `<i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>
            Sin cuenta asignada. Se configura en <strong>Formas de Cobro/Pago</strong>.
            Sin ella la conciliación funciona, pero no genera asiento.`;
    }

    try {
        const cfg = await CTAR_api(`configAjax?id_forma_cobro=${idForma}`);
        const set = (idTxt, idHid, codigo, nombre, valor) => {
            document.getElementById(idHid).value = valor || '';
            document.getElementById(idTxt).value = codigo ? `${codigo} — ${nombre || ''}` : '';
        };

        if (!cfg) {
            ['comision', 'iva', 'retir', 'retiva'].forEach((c) => {
                document.getElementById(`ctar-cfg-${c}`).value = '';
                document.getElementById(`ctar-cfg-${c}-txt`).value = '';
            });
            document.getElementById('ctar-cfg-pc').value = '';
            document.getElementById('ctar-cfg-pi').value = '';
            document.getElementById('ctar-cfg-dias').value = 2;
            document.getElementById('ctar-cfg-tol').value = 0.05;
            return;
        }

        set('ctar-cfg-comision-txt', 'ctar-cfg-comision', cfg.comision_codigo, cfg.comision_nombre, cfg.id_cuenta_comision);
        set('ctar-cfg-iva-txt', 'ctar-cfg-iva', cfg.iva_codigo, cfg.iva_nombre, cfg.id_cuenta_iva_comision);
        set('ctar-cfg-retir-txt', 'ctar-cfg-retir', cfg.ret_ir_codigo, cfg.ret_ir_nombre, cfg.id_cuenta_retencion_ir);
        set('ctar-cfg-retiva-txt', 'ctar-cfg-retiva', cfg.ret_iva_codigo, cfg.ret_iva_nombre, cfg.id_cuenta_retencion_iva);
        document.getElementById('ctar-cfg-pc').value   = cfg.porcentaje_comision;
        document.getElementById('ctar-cfg-pi').value   = cfg.porcentaje_iva;
        document.getElementById('ctar-cfg-dias').value = cfg.dias_liquidacion;
        document.getElementById('ctar-cfg-tol').value  = cfg.tolerancia_diferencia;
    } catch (e) {
        CTAR_aviso('error', 'No se pudo leer la configuración', e.message);
    }
}

async function CTAR_guardarConfig() {
    try {
        await CTAR_post('guardarConfigAjax', {
            id_forma_cobro: document.getElementById('ctar-cfg-procesadora').value,
            id_cuenta_comision: document.getElementById('ctar-cfg-comision').value,
            id_cuenta_iva_comision: document.getElementById('ctar-cfg-iva').value,
            id_cuenta_retencion_ir: document.getElementById('ctar-cfg-retir').value,
            id_cuenta_retencion_iva: document.getElementById('ctar-cfg-retiva').value,
            porcentaje_comision: document.getElementById('ctar-cfg-pc').value || 0,
            porcentaje_iva: document.getElementById('ctar-cfg-pi').value || 0,
            dias_liquidacion: document.getElementById('ctar-cfg-dias').value || 2,
            tolerancia_diferencia: document.getElementById('ctar-cfg-tol').value || 0.05,
        });
        CTAR_aviso('success', 'Configuración guardada', '');
    } catch (e) {
        CTAR_aviso('error', 'No se pudo guardar', e.message);
    }
}

// ─── Perfiles de lectura ────────────────────────────────────────────────────

function CTAR_pintarCamposMapeo() {
    document.getElementById('ctar-perfil-campos').innerHTML = CTAR_CAMPOS_MAPEO.map(([campo, etiqueta, obligatorio]) => `
        <div class="col-6 col-md-3">
            <label class="form-label small mb-1">
                ${CTAR_esc(etiqueta)}${obligatorio ? ' <span class="text-danger">*</span>' : ''}
            </label>
            <input type="number" min="0" class="form-control form-control-sm shadow-none border ctar-mapeo-campo"
                   data-campo="${campo}" placeholder="col.">
        </div>`).join('');
}

async function CTAR_cargarPerfiles() {
    try {
        CTAR_perfilesCache = await CTAR_api('listarPerfilesAjax');
    } catch (e) {
        CTAR_perfilesCache = [];
    }

    const tbody = document.getElementById('ctar-tbody-perfiles');
    if (!CTAR_perfilesCache.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-muted small">Sin perfiles todavía.</td></tr>';
        return;
    }

    tbody.innerHTML = CTAR_perfilesCache.map((p) => `
        <tr>
            <td class="fw-medium">${CTAR_esc(p.nombre_perfil)}</td>
            <td>${CTAR_esc(p.forma_nombre || 'Cualquiera')}</td>
            <td class="text-center"><span class="badge bg-secondary bg-opacity-25 text-secondary">${CTAR_esc(p.tipo_archivo)}</span></td>
            <td class="text-center small">${p.nivel === 'deposito' ? 'Depósitos' : 'Transacciones'}</td>
            <td class="text-center">
                <button class="btn btn-sm btn-outline-secondary py-0 px-1" onclick="CTAR_editarPerfil(${p.id})" title="Editar">
                    <i class="bi bi-pencil"></i></button>
                <button class="btn btn-sm btn-outline-danger py-0 px-1" onclick="CTAR_eliminarPerfil(${p.id})" title="Eliminar">
                    <i class="bi bi-trash"></i></button>
            </td>
        </tr>`).join('');
}

function CTAR_nuevoPerfil() {
    document.getElementById('ctar-perfil-editor').classList.remove('d-none');
    document.getElementById('ctar-perfil-id').value = '';
    document.getElementById('ctar-perfil-nombre').value = '';
    document.getElementById('ctar-perfil-forma').value = '';
    document.getElementById('ctar-perfil-tipo').value = 'EXCEL';
    document.getElementById('ctar-perfil-nivel').value = 'transaccion';
    document.getElementById('ctar-perfil-fila').value = 1;
    document.getElementById('ctar-perfil-fecha').value = 'd/m/Y';
    document.getElementById('ctar-perfil-separador').value = '.';
    document.getElementById('ctar-perfil-regex').value = '';
    document.querySelectorAll('.ctar-mapeo-campo').forEach((i) => { i.value = ''; });
    document.getElementById('ctar-perfil-preview-wrap').classList.add('d-none');
    CTAR_perfilTipoCambio();
}

function CTAR_editarPerfil(id) {
    const p = CTAR_perfilesCache.find((x) => String(x.id) === String(id));
    if (!p) return;

    CTAR_nuevoPerfil();
    document.getElementById('ctar-perfil-id').value = p.id;
    document.getElementById('ctar-perfil-nombre').value = p.nombre_perfil;
    document.getElementById('ctar-perfil-forma').value = p.id_forma_cobro || '';
    document.getElementById('ctar-perfil-tipo').value = p.tipo_archivo;
    document.getElementById('ctar-perfil-nivel').value = p.nivel;
    document.getElementById('ctar-perfil-fila').value = p.fila_inicio;
    document.getElementById('ctar-perfil-fecha').value = p.formato_fecha;
    document.getElementById('ctar-perfil-separador').value = p.separador_decimal;

    const mapeo = typeof p.mapeo_columnas === 'string' ? JSON.parse(p.mapeo_columnas || '{}') : (p.mapeo_columnas || {});
    document.getElementById('ctar-perfil-regex').value = mapeo.regex_linea || '';
    document.querySelectorAll('.ctar-mapeo-campo').forEach((i) => {
        const def = mapeo[i.dataset.campo];
        i.value = (def && def.col !== undefined) ? def.col : '';
    });

    CTAR_perfilTipoCambio();
}

function CTAR_perfilTipoCambio() {
    const esPdf = document.getElementById('ctar-perfil-tipo').value === 'PDF';
    document.getElementById('ctar-perfil-mapeo-excel').classList.toggle('d-none', esPdf);
    document.getElementById('ctar-perfil-mapeo-pdf').classList.toggle('d-none', !esPdf);
}

function CTAR_mapeoActual() {
    if (document.getElementById('ctar-perfil-tipo').value === 'PDF') {
        return { regex_linea: document.getElementById('ctar-perfil-regex').value };
    }

    const mapeo = {};
    document.querySelectorAll('.ctar-mapeo-campo').forEach((i) => {
        if (i.value !== '') mapeo[i.dataset.campo] = { col: parseInt(i.value, 10) };
    });
    return mapeo;
}

function CTAR_cancelarPerfil() {
    document.getElementById('ctar-perfil-editor').classList.add('d-none');
}

async function CTAR_guardarPerfil() {
    try {
        await CTAR_post('guardarPerfilAjax', {
            id: document.getElementById('ctar-perfil-id').value || 0,
            id_forma_cobro: document.getElementById('ctar-perfil-forma').value || null,
            nombre_perfil: document.getElementById('ctar-perfil-nombre').value,
            tipo_archivo: document.getElementById('ctar-perfil-tipo').value,
            nivel: document.getElementById('ctar-perfil-nivel').value,
            fila_inicio: document.getElementById('ctar-perfil-fila').value || 0,
            formato_fecha: document.getElementById('ctar-perfil-fecha').value,
            separador_decimal: document.getElementById('ctar-perfil-separador').value,
            mapeo_columnas: CTAR_mapeoActual(),
            activo: true,
        });
        CTAR_cancelarPerfil();
        CTAR_cargarPerfiles();
        CTAR_aviso('success', 'Perfil guardado', '');
    } catch (e) {
        CTAR_aviso('error', 'No se pudo guardar el perfil', e.message);
    }
}

async function CTAR_eliminarPerfil(id) {
    if (!await CTAR_confirmar('¿Eliminar el perfil?', 'Las conciliaciones ya cargadas no se ven afectadas.')) return;
    try {
        await CTAR_post('eliminarPerfilAjax', { id });
        CTAR_cargarPerfiles();
    } catch (e) {
        CTAR_aviso('error', 'No se pudo eliminar', e.message);
    }
}

/** Muestra el archivo de muestra tal como lo lee el sistema, y prueba el mapeo. */
async function CTAR_previsualizarMuestra() {
    const archivo = document.getElementById('ctar-perfil-muestra').files[0];
    if (!archivo) return;

    const fd = new FormData();
    fd.append('archivo', archivo);
    fd.append('tipo_archivo', document.getElementById('ctar-perfil-tipo').value);
    fd.append('fila_inicio', document.getElementById('ctar-perfil-fila').value || 0);
    fd.append('formato_fecha', document.getElementById('ctar-perfil-fecha').value);
    fd.append('separador_decimal', document.getElementById('ctar-perfil-separador').value);
    fd.append('mapeo_prueba', JSON.stringify(CTAR_mapeoActual()));

    try {
        const r = await CTAR_api('previsualizarArchivoAjax', { method: 'POST', body: fd });
        const esPdf = document.getElementById('ctar-perfil-tipo').value === 'PDF';

        document.getElementById('ctar-perfil-preview').innerHTML = (r.lineas || []).map((fila, i) => {
            if (esPdf) return `<tr><td class="text-muted">${i}</td><td class="font-monospace">${CTAR_esc(fila)}</td></tr>`;
            const celdas = (fila || []).map((c, idx) =>
                `<td><span class="badge bg-light text-muted me-1">${idx}</span>${CTAR_esc(c)}</td>`).join('');
            return `<tr>${celdas}</tr>`;
        }).join('');
        document.getElementById('ctar-perfil-preview-wrap').classList.remove('d-none');

        const probadas = r.filas_probadas;
        const wrap = document.getElementById('ctar-perfil-probado-wrap');
        if (Array.isArray(probadas) && probadas.length) {
            document.getElementById('ctar-perfil-probado').innerHTML = probadas.slice(0, 20).map((l) => `
                <tr>
                    <td>${CTAR_fecha(l.fecha)}</td>
                    <td>${CTAR_esc(l.autorizacion || '')}</td>
                    <td class="text-end">$${CTAR_num(l.monto_bruto)}</td>
                    <td class="text-end">$${CTAR_num(l.comision)}</td>
                    <td class="text-end">$${CTAR_num(l.monto_neto)}</td>
                </tr>`).join('');
            wrap.classList.remove('d-none');
        } else {
            wrap.classList.add('d-none');
        }
    } catch (e) {
        CTAR_aviso('error', 'No se pudo leer el archivo', e.message);
    }
}

// ─── Buscador de cuentas (typeahead de la configuración) ────────────────────

document.addEventListener('input', async (ev) => {
    const input = ev.target.closest('.ctar-cuenta-input');
    if (!input) return;

    const drop = input.parentElement.querySelector('.ctar-cuenta-drop');
    const q = input.value.trim();

    // Al borrar el texto se limpia también la selección oculta.
    if (q.length < 2) {
        drop.classList.add('d-none');
        if (q === '') document.getElementById(input.dataset.target).value = '';
        return;
    }

    try {
        const cuentas = await CTAR_api(`buscarCuentasAjax?q=${encodeURIComponent(q)}`);
        if (!cuentas.length) { drop.classList.add('d-none'); return; }

        drop.innerHTML = cuentas.map((c) => `
            <button type="button" class="list-group-item list-group-item-action py-1 small"
                    data-id="${c.id}" data-texto="${CTAR_esc(c.codigo)} — ${CTAR_esc(c.nombre)}">
                <strong>${CTAR_esc(c.codigo)}</strong> ${CTAR_esc(c.nombre)}
            </button>`).join('');
        drop.classList.remove('d-none');
    } catch (e) {
        drop.classList.add('d-none');
    }
});

document.addEventListener('click', (ev) => {
    const opcion = ev.target.closest('.ctar-cuenta-drop .list-group-item');
    if (opcion) {
        const drop  = opcion.parentElement;
        const input = drop.parentElement.querySelector('.ctar-cuenta-input');
        document.getElementById(input.dataset.target).value = opcion.dataset.id;
        input.value = opcion.dataset.texto;
        drop.classList.add('d-none');
        return;
    }
    // Clic fuera: cerrar los desplegables abiertos.
    if (!ev.target.closest('.ctar-cuenta-input')) {
        document.querySelectorAll('.ctar-cuenta-drop').forEach((d) => d.classList.add('d-none'));
    }
});

// Backspace/Delete con una cuenta ya elegida limpia toda la selección (§9).
document.addEventListener('keydown', (ev) => {
    const input = ev.target.closest('.ctar-cuenta-input');
    if (!input || (ev.key !== 'Backspace' && ev.key !== 'Delete')) return;

    const hidden = document.getElementById(input.dataset.target);
    if (hidden.value) {
        ev.preventDefault();
        hidden.value = '';
        input.value = '';
        input.parentElement.querySelector('.ctar-cuenta-drop').classList.add('d-none');
    }
});

// ─── Exportación ────────────────────────────────────────────────────────────

function CTAR_exportarPDF() {
    const params = new URLSearchParams({ ...CTAR_filtrosActuales(), vista: CTAR_vista });
    window.open(`${CTAR_URL}/exportarPdf?${params}`, '_blank');
}

function CTAR_exportarExcel() {
    const params = new URLSearchParams({ ...CTAR_filtrosActuales(), vista: CTAR_vista });
    window.open(`${CTAR_URL}/exportarExcel?${params}`, '_blank');
}

function CTAR_pdfConciliacion() {
    const id = document.getElementById('ctar-m-id').value;
    if (!id) { CTAR_aviso('info', 'Guarde primero', 'La conciliación debe estar guardada.'); return; }
    window.open(`${CTAR_URL}/comprobantePdf?id=${id}`, '_blank');
}

function CTAR_excelConciliacion() {
    const id = document.getElementById('ctar-m-id').value;
    if (!id) { CTAR_aviso('info', 'Guarde primero', 'La conciliación debe estar guardada.'); return; }
    window.open(`${CTAR_URL}/comprobanteExcel?id=${id}`, '_blank');
}

// ─── Arranque ───────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    CTAR_cargar();
});
