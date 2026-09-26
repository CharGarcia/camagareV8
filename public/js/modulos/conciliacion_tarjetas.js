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

let CTAR_detalle      = null;   // detalle completo de la conciliación abierta
let CTAR_lineaSel     = null;   // id de la línea seleccionada para cruzar
let CTAR_cobrosCache  = [];
let CTAR_perfilesCache = [];

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

// ─── Listado de conciliaciones ──────────────────────────────────────────────
// Listado estándar (§9): el buscador FiltrosModal deja en #ctar-buscar el texto libre
// y los filtros serializados (`clave:valor`); el servidor los resuelve con FiltrosBusqueda.

/** Motor de orden (CMG_initSort) del listado. */
let CTAR_sorter = null;

const CTAR_ESTADOS = { borrador: 'Borrador', cerrada: 'Cerrada', anulada: 'Anulada' };
const CTAR_etiquetaEstado = (e) => CTAR_ESTADOS[e] || e || '';

const CTAR_buscar     = () => (document.getElementById('ctar-buscar')?.value || '').trim();
/** Criterios de orden en el formato de OrdenListado (`col:DIR,col:DIR`). */
const CTAR_ordenParam = () => (CTAR_sorter ? CTAR_sorter.getOrdenParam() : '');

/** Texto «desde-hasta/total» y botones anterior/siguiente. */
function CTAR_pintarPaginacion(datos) {
    const total   = parseInt(datos.total, 10) || 0;
    const page    = parseInt(datos.page, 10) || 1;
    const porPag  = parseInt(datos.per_page, 10) || 50;
    const paginas = Math.max(1, Math.ceil(total / porPag));
    const desde   = total ? (page - 1) * porPag + 1 : 0;
    const hasta   = Math.min(page * porPag, total);

    document.getElementById('ctar-info').textContent = `${desde}-${hasta}/${total}`;

    const cont = document.getElementById('ctar-pag');
    const prev = cont.querySelector('[data-pag="prev"]');
    const next = cont.querySelector('[data-pag="next"]');
    prev.disabled = page <= 1;
    next.disabled = page >= paginas;
    prev.onclick = () => CTAR_cargar(page - 1);
    next.onclick = () => CTAR_cargar(page + 1);
}

/** Recarga el listado (por defecto desde la página 1). */
async function CTAR_cargar(page = 1) {
    const tbody = document.getElementById('ctar-tbody');
    // Mismo indicador que el buscador (FiltrosModal): la tabla se atenúa mientras carga.
    tbody.classList.add('fm-cargando-target');

    try {
        const params = new URLSearchParams({ buscar: CTAR_buscar(), page, orden: CTAR_ordenParam() });
        const datos = await CTAR_api(`listarAjax?${params}`);
        const filas = datos.data || [];

        if (!filas.length) {
            tbody.innerHTML = `<tr><td colspan="10" class="text-center py-5 text-muted">
                <i class="bi bi-list-check fs-3 d-block mb-2 text-primary opacity-50"></i>
                No hay conciliaciones registradas con esos filtros.
            </td></tr>`;
        } else {
            tbody.innerHTML = filas.map((c) => {
                const retenciones = (parseFloat(c.total_retencion_ir) || 0) + (parseFloat(c.total_retencion_iva) || 0);
                const comision    = (parseFloat(c.total_comision) || 0) + (parseFloat(c.total_iva_comision) || 0);

                return `<tr class="ctar-fila" onclick="CTAR_abrir(${c.id})">
                    <td class="ps-3 fw-bold" data-col="c_numero">${CTAR_esc(c.numero)}</td>
                    <td data-col="c_fecha">${CTAR_fecha(c.fecha_conciliacion)}</td>
                    <td data-col="c_procesadora">${CTAR_esc(c.procesadora_nombre || '')}</td>
                    <td data-col="c_destino">${CTAR_esc(c.destino_nombre || '—')}</td>
                    <td class="text-center" data-col="c_cobros">${c.cobros_cruzados || 0}</td>
                    <td class="text-end" data-col="c_bruto">$${CTAR_num(c.total_bruto_cruzado)}</td>
                    <td class="text-end text-secondary" data-col="c_comision">$${CTAR_num(comision)}</td>
                    <td class="text-end text-secondary" data-col="c_retenciones">$${CTAR_num(retenciones)}</td>
                    <td class="text-end fw-bold text-primary" data-col="c_neto">$${CTAR_num(c.total_neto)}</td>
                    <td class="text-center pe-3" data-col="c_estado"><span class="badge ctar-estado-${CTAR_esc(c.estado)}">${CTAR_esc(CTAR_etiquetaEstado(c.estado))}</span></td>
                </tr>`;
            }).join('');
        }

        CTAR_pintarPaginacion(datos);
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="10" class="text-center py-4 text-danger">${CTAR_esc(e.message)}</td></tr>`;
    } finally {
        tbody.classList.remove('fm-cargando-target');
    }
    if (CTAR_sorter) CTAR_sorter.refreshIcons();
}
window.CTAR_cargar = CTAR_cargar;

/** Orden por columnas (§9): clic ordena, Shift+clic encadena hasta 3 columnas. */
function CTAR_initOrden() {
    if (typeof window.CMG_initSort !== 'function') return;
    CTAR_sorter = window.CMG_initSort(CTAR_ORDEN.modulo, () => CTAR_cargar(1), {
        sorts: CTAR_ORDEN.sorts,
        multi: true,
        container: '#tabla-ctar',
    });
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
    CTAR_limpiarFiltrosListas();

    document.getElementById('ctar-m-id').value = '';
    document.getElementById('ctar-m-titulo').textContent = 'Nueva conciliación';
    document.getElementById('ctar-m-procesadora').innerHTML = CTAR_opcionesProcesadoras('');
    document.getElementById('ctar-m-procesadora').disabled = false;
    document.getElementById('ctar-m-destino').innerHTML = CTAR_opcionesDestinos('');
    document.getElementById('ctar-m-fecha').value = new Date().toISOString().substring(0, 10);
    document.getElementById('ctar-m-desde').value = '';
    document.getElementById('ctar-m-hasta').value = '';
    document.getElementById('ctar-m-neto').value  = '';
    document.getElementById('ctar-m-estado').classList.add('d-none');
    document.getElementById('ctar-m-aviso-conta').classList.add('d-none');

    document.getElementById('ctar-m-archivo').value = '';
    CTAR_cargarPerfilesModal();
    CTAR_pintarInfoArchivo();

    document.getElementById('ctar-m-tbody-lineas').innerHTML =
        '<tr><td colspan="8" class="text-center py-4 text-muted small">Llene el encabezado, elija el perfil y el archivo del estado de cuenta, y pulse Cargar.</td></tr>';
    document.getElementById('ctar-m-tbody-cobros').innerHTML =
        '<tr><td colspan="5" class="text-center py-4 text-muted small">—</td></tr>';

    CTAR_habilitarAcciones(false, true);
    CTAR_mostrarModal();
}

async function CTAR_abrir(id) {
    try {
        CTAR_detalle = await CTAR_api(`detalleAjax?id=${id}`);
        CTAR_lineaSel = null;
        CTAR_limpiarFiltrosListas();
        document.getElementById('ctar-m-archivo').value = '';
        CTAR_pintarModal();
        CTAR_mostrarModal();
    } catch (e) {
        CTAR_aviso('error', 'No se pudo abrir', e.message);
    }
}

/** Abre el modal siempre en la pestaña «Conciliación» (una sola instancia de Bootstrap). */
function CTAR_mostrarModal() {
    const btnCruce = document.getElementById('ctar-tab-cruce-btn');
    if (btnCruce) bootstrap.Tab.getOrCreateInstance(btnCruce).show();
    if (CTAR_asientoTab) CTAR_asientoTab.limpiar();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalConciliacion')).show();
}

// ─── Pestaña «Asiento contable» ─────────────────────────────────────────────
// Componente compartido (public/js/modulos/asiento_contable_tab.js). Solo muestra el
// asiento YA generado al cerrar la conciliación; si no hay, estadoAsientoAjax explica
// por qué. La pestaña existe solo con acceso a Contabilidad → Asientos Contables.
let CTAR_asientoTab = null;

function CTAR_getAsientoTab() {
    if (!document.getElementById('ctar-asiento-tbody')) return null;
    if (!CTAR_asientoTab && typeof window.crearAsientoTab === 'function') {
        CTAR_asientoTab = window.crearAsientoTab({
            prefijo: 'ctar',
            moduloOrigen: 'conciliacion_tarjetas',
            soloRegistrado: true,
            previewUrl: `${CTAR_URL}/estadoAsientoAjax`,
            cuentasUrl: `${window.BASE_URL}/modulos/plan-cuentas/searchAjaxCuentas`,
            asientosUrl: `${window.BASE_URL}/modulos/asientos-contables`,
        });
    }
    return CTAR_asientoTab;
}

function CTAR_cargarAsiento() {
    const tab = CTAR_getAsientoTab();
    if (tab) tab.cargar(document.getElementById('ctar-m-id').value || 0);
}

/** ¿Está visible la pestaña del asiento? (para refrescarla tras cerrar o anular). */
const CTAR_asientoVisible = () => !!document.querySelector('#ctar-pane-asiento.active');

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
    badge.className = `badge ms-2 ctar-estado-${cab.estado}`;
    badge.textContent = CTAR_etiquetaEstado(cab.estado);

    // El selector ofrece solo las formas activas; si la de esta conciliación se desactivó
    // después en Formas de Cobros y Pagos, se muestra igual (el selector está bloqueado).
    const selProc = document.getElementById('ctar-m-procesadora');
    selProc.innerHTML = CTAR_opcionesProcesadoras(cab.id_forma_cobro);
    if (!CTAR_PROCESADORAS.some((p) => String(p.id) === String(cab.id_forma_cobro))) {
        selProc.insertAdjacentHTML('afterbegin',
            `<option value="${cab.id_forma_cobro}" selected>${CTAR_esc(cab.procesadora_nombre || '')} (inactiva)</option>`);
    }
    selProc.disabled = true;   // no se cambia una vez creada
    // «Depositado en» ofrece solo formas activas; si el destino guardado se desactivó
    // después, se conserva como opción (sin ella, guardar lo dejaría vacío sin avisar).
    const selDest = document.getElementById('ctar-m-destino');
    selDest.innerHTML = CTAR_opcionesDestinos(cab.id_forma_cobro_destino);
    if (cab.id_forma_cobro_destino
        && !CTAR_DESTINOS.some((d) => String(d.id) === String(cab.id_forma_cobro_destino))) {
        selDest.insertAdjacentHTML('beforeend',
            `<option value="${cab.id_forma_cobro_destino}" selected>${CTAR_esc(cab.destino_nombre || '')} (inactiva)</option>`);
    }
    document.getElementById('ctar-m-fecha').value = (cab.fecha_conciliacion || '').substring(0, 10);
    document.getElementById('ctar-m-desde').value = (cab.fecha_desde || '').substring(0, 10);
    document.getElementById('ctar-m-hasta').value = (cab.fecha_hasta || '').substring(0, 10);
    document.getElementById('ctar-m-neto').value  = parseFloat(cab.neto_depositado) > 0 ? CTAR_num(cab.neto_depositado) : '';

    ['ctar-m-destino', 'ctar-m-fecha', 'ctar-m-desde', 'ctar-m-hasta', 'ctar-m-neto']
        .forEach((id) => { document.getElementById(id).disabled = !editable; });

    // Perfil: el último usado en esta conciliación. El archivo elegido NO se limpia aquí:
    // si su lectura falló, el usuario corrige el perfil y vuelve a pulsar Guardar sin
    // tener que elegirlo otra vez (se limpia al leerse bien o al abrir otra conciliación).
    CTAR_cargarPerfilesModal(cab.id_perfil || '');
    CTAR_pintarInfoArchivo();

    CTAR_pintarLineas();
    CTAR_pintarCobros();
    CTAR_pintarTotales();
    CTAR_pintarAvisoContable();
    CTAR_habilitarAcciones(editable, false);

    // Tras cerrar o anular con la pestaña del asiento a la vista, mostrar el asiento nuevo.
    if (CTAR_asientoVisible()) CTAR_cargarAsiento();
}

function CTAR_habilitarAcciones(editable, esNueva) {
    // Eliminar solo existe en el DOM con permiso de eliminar (lo decide la vista).
    const mostrar = (id, visible) => document.getElementById(id)?.classList.toggle('d-none', !visible);

    ['ctar-btn-linea', 'ctar-btn-sugerir'].forEach((id) => {
        document.getElementById(id).disabled = esNueva || !editable;
    });
    // El estado de cuenta se elige desde el encabezado, también al crear la conciliación.
    ['ctar-m-perfil', 'ctar-m-archivo', 'ctar-btn-cargar'].forEach((id) => {
        document.getElementById(id).disabled = !esNueva && !editable;
    });

    // Una conciliación nueva se crea con «Cargar» (encabezado + archivo); «Guardar» aparece
    // después, para guardar los cambios del encabezado mientras se hace el cruce.
    document.getElementById('ctar-btn-guardar').classList.toggle('d-none', esNueva || !editable);
    document.getElementById('ctar-btn-conciliar').classList.toggle('d-none', esNueva || !editable);
    mostrar('ctar-btn-eliminar', CTAR_PERM.eliminar && !esNueva && editable);
    mostrar('ctar-btn-anular', CTAR_PERM.actualizar && !esNueva && !editable
        && CTAR_detalle && CTAR_detalle.cabecera.estado === 'cerrada');
    mostrar('ctar-m-acciones', !esNueva);

    // Paso 2 (estado de cuenta | cobros | totales): solo con la conciliación guardada.
    // Mientras es nueva, el modal se ajusta al alto del encabezado.
    mostrar('ctar-m-paso2', !esNueva);
    document.getElementById('modalConciliacion').classList.toggle('ctar-m-sin-cruce', esNueva);
    CTAR_ajustarAltoModal();
}

/**
 * Alto del modal según lo que muestra (como el modal de Clientes): solo la pestaña
 * «Conciliación» con el cruce a la vista usa casi toda la pantalla para las dos listas;
 * la conciliación nueva y las pestañas «Asiento contable» y «Configuración» se ajustan
 * a su contenido. Se llama al cambiar de pestaña y al pintar el modal.
 */
function CTAR_ajustarAltoModal() {
    const modal = document.getElementById('modalConciliacion');
    if (!modal) return;
    const enCruce = !!document.querySelector('#ctar-pane-cruce.active');
    modal.classList.toggle('ctar-m-lleno', enCruce && !modal.classList.contains('ctar-m-sin-cruce'));
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
    const todas = CTAR_detalle.lineas || [];
    const editable = CTAR_detalle.cabecera.estado === 'borrador';

    // Filtro Desde/Hasta del encabezado de la tarjeta (fecha del movimiento). Solo
    // acota lo que se ve: los totales y el cierre siguen usando todas las líneas.
    const lineas = CTAR_lineasVisibles();

    document.getElementById('ctar-m-resumen-lineas').textContent =
        (lineas.length !== todas.length ? `${lineas.length} de ` : '')
        + `${todas.length} líneas · ${CTAR_detalle.totales.lineas_cruzadas} cruzadas · ${CTAR_detalle.totales.lineas_sin_cobro} sin documento`;

    if (!lineas.length) {
        tbody.innerHTML = todas.length
            ? '<tr><td colspan="8" class="text-center py-4 text-muted small">Ninguna línea en esas fechas.</td></tr>'
            : '<tr><td colspan="8" class="text-center py-4 text-muted small">Cargue el estado de cuenta o agregue líneas a mano.</td></tr>';
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

function CTAR_pintarCobros(filtro = null) {
    // Sin argumento se respeta lo escrito en el buscador (antes, al seleccionar una
    // línea, la lista se repintaba sin el filtro de texto).
    if (filtro === null) filtro = document.getElementById('ctar-m-buscar-cobro')?.value || '';
    const tbody = document.getElementById('ctar-m-tbody-cobros');
    const editable = CTAR_detalle.cabecera.estado === 'borrador';
    const limite = CTAR_diasEsperados(CTAR_detalle.cabecera.id_forma_cobro);

    // Solo los que aún no están cruzados en esta conciliación.
    CTAR_cobrosCache = (CTAR_detalle.cobros || []).filter((c) => !c.id_cruce);

    // Filtro Desde/Hasta del encabezado de la tarjeta: fecha de la FACTURA (la columna
    // «Fecha fact.»). Si el cobro cubrió varias, basta con que una caiga en el rango.
    // Un cobro sin documentos con fecha (p. ej. un anticipo) se filtra por su propia fecha.
    const rango = CTAR_rangoFechas('cobros');
    const enFechas = CTAR_cobrosCache.filter((c) => CTAR_cobroEnRango(c, rango));

    const q = filtro.trim().toLowerCase();
    const visibles = q ? enFechas.filter((c) => CTAR_cobroCoincideTexto(c, q)) : enFechas;

    document.getElementById('ctar-m-resumen-cobros').textContent =
        (visibles.length !== CTAR_cobrosCache.length ? `${visibles.length} de ` : '') + `${CTAR_cobrosCache.length} disponibles`;

    if (!visibles.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted small">Sin cobros pendientes.</td></tr>';
        return;
    }

    const puedeCruzar = editable && CTAR_lineaSel;

    tbody.innerHTML = visibles.map((c) => {
        const docs = CTAR_documentosCobro(c);
        const celdaDocs = docs.length
            ? docs.map((d) => `<div class="text-nowrap">${CTAR_esc(d.numero || '—')}${d.tipo === 'SALDO_INICIAL'
                ? ' <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25" title="Factura de saldos iniciales">S.I.</span>' : ''}</div>`).join('')
            : CTAR_esc(c.documentos || c.numero_ingreso || '—');
        const celdaFechas = docs.length
            ? docs.map((d) => `<div class="text-nowrap">${d.fecha ? CTAR_fecha(d.fecha) : '—'}</div>`).join('')
            : '—';
        return `
        <tr style="cursor:${puedeCruzar ? 'pointer' : 'default'};"
            ${puedeCruzar ? `onclick="CTAR_cruzarCon(${c.id_ingreso_pago})"` : ''}
            title="${puedeCruzar ? 'Cruzar con la línea seleccionada' : 'Seleccione primero una línea del estado de cuenta'}">
            <td class="ps-3 fw-medium">${celdaDocs}</td>
            <td class="small" title="Cobro ${CTAR_esc(c.numero_ingreso || '')} del ${CTAR_fecha(c.fecha_emision)}">${celdaFechas}</td>
            <td class="small">${CTAR_esc(c.cliente_nombre || '—')}</td>
            <td class="text-end fw-bold">$${CTAR_num(c.monto)}</td>
            <td class="text-center">${CTAR_badgeDias(c.dias_transcurridos, limite)}</td>
        </tr>`;
    }).join('');
}

/** Documentos (factura, recibo o saldo inicial) que cubrió un cobro, con su fecha de emisión. */
function CTAR_documentosCobro(c) {
    let docs = c.documentos_detalle;
    if (typeof docs === 'string') {
        try { docs = JSON.parse(docs); } catch (e) { docs = null; }
    }
    return Array.isArray(docs) ? docs : [];
}

// ─── Filtros Desde/Hasta de cada lista ──────────────────────────────────────
// Viven en el encabezado de cada tarjeta y solo filtran en pantalla (no se guardan
// ni cambian qué se cruza o se cierra).

function CTAR_rangoFechas(lista) {
    return {
        desde: document.getElementById(`ctar-f-${lista}-desde`)?.value || '',
        hasta: document.getElementById(`ctar-f-${lista}-hasta`)?.value || '',
    };
}

function CTAR_enRango(fecha, rango) {
    if (!rango.desde && !rango.hasta) return true;
    const f = String(fecha || '').substring(0, 10);
    if (!f) return false;
    return (!rango.desde || f >= rango.desde) && (!rango.hasta || f <= rango.hasta);
}

/**
 * ¿El cobro pasa el filtro Desde/Hasta de la lista de cobros? Por fecha de la FACTURA:
 * basta con que una de las que cubrió caiga en el rango. Sin documentos con fecha (un
 * anticipo, por ejemplo), por la fecha del propio cobro. Lo usan la lista y los totales.
 */
function CTAR_cobroEnRango(c, rango) {
    const fechas = CTAR_documentosCobro(c).map((d) => d.fecha).filter(Boolean);
    return fechas.length
        ? fechas.some((fe) => CTAR_enRango(fe, rango))
        : CTAR_enRango(c.fecha_emision, rango);
}

function CTAR_filtrarFechas(lista) {
    if (!CTAR_detalle) return;
    if (lista === 'lineas') CTAR_pintarLineas(); else CTAR_pintarCobros();
    CTAR_pintarTotales();   // ambos filtros afectan los totales
}

function CTAR_limpiarFiltrosListas() {
    ['ctar-f-lineas-desde', 'ctar-f-lineas-hasta', 'ctar-f-cobros-desde', 'ctar-f-cobros-hasta', 'ctar-m-buscar-cobro']
        .forEach((id) => { const el = document.getElementById(id); if (el) el.value = ''; });
}

function CTAR_filtrarCobros(q) {
    if (!CTAR_detalle) return;
    CTAR_pintarCobros(q);
    CTAR_pintarTotales();   // el buscador de cobros también afecta los totales
}

/** Texto del buscador de cobros (en minúsculas, sin espacios sobrantes). */
function CTAR_textoBuscarCobro() {
    return (document.getElementById('ctar-m-buscar-cobro')?.value || '').trim().toLowerCase();
}

/** ¿El cobro coincide con el buscador? Mismos campos que se ven en la lista. */
function CTAR_cobroCoincideTexto(c, q) {
    if (!q) return true;
    return (
        `${c.documentos || ''} ${c.cliente_nombre || ''} ${c.numero_ingreso || ''} ${c.monto} `
        + CTAR_documentosCobro(c).map((d) => CTAR_fecha(d.fecha)).join(' ')
    ).toLowerCase().includes(q);
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

// ─── Cruzar automáticamente: sugerir → revisar → aplicar ────────────────────
// El servidor solo PROPONE (ConciliacionTarjetasMatchService); el usuario ve cada
// emparejamiento con su criterio y decide cuáles cruzar. Nada se guarda antes.

const CTAR_CRITERIOS = {
    autorizacion: { texto: 'Autorización',        clase: 'bg-success',           marcado: true },
    referencia:   { texto: 'Referencia',          clase: 'bg-primary',           marcado: true },
    monto_fecha:  { texto: 'Monto y fecha',       clase: 'bg-info text-dark',    marcado: true },
    monto:        { texto: 'Solo monto — revisar', clase: 'bg-warning text-dark', marcado: false },
    // Depósito consolidado: varios cobros cuya suma da el bruto de la línea.
    deposito_suma: { texto: 'Suma del depósito',  clase: 'bg-info text-dark',    marcado: true },
};

let CTAR_sugerencias = [];

async function CTAR_sugerir() {
    const id = document.getElementById('ctar-m-id').value;
    if (!id) return;

    try {
        CTAR_sugerencias = await CTAR_api(`sugerirAjax?id=${id}`);
    } catch (e) {
        CTAR_aviso('error', 'Error al sugerir', e.message);
        return;
    }
    if (!CTAR_sugerencias.length) {
        CTAR_aviso('info', 'Sin sugerencias',
            'No se encontraron emparejamientos evidentes. Cruce las líneas a mano seleccionando cada una y su cobro.');
        return;
    }

    const lineas = new Map((CTAR_detalle.lineas || []).map((l) => [String(l.id), l]));
    const cobros = new Map((CTAR_detalle.cobros || []).map((c) => [String(c.id_ingreso_pago), c]));

    document.getElementById('ctar-sug-tbody').innerHTML = CTAR_sugerencias.map((s, i) => {
        const l = lineas.get(String(s.id_linea)) || {};
        const crit = CTAR_CRITERIOS[s.criterio] || { texto: s.criterio, clase: 'bg-secondary', marcado: false };
        const sumaCobros = s.cobros.reduce((t, c) => t + (parseFloat(c.monto) || 0), 0);
        const difiere = Math.abs(sumaCobros - (parseFloat(l.monto_bruto) || 0)) >= 0.01;

        const detalleCobros = s.cobros.map((c) => {
            const info = cobros.get(String(c.id_ingreso_pago)) || {};
            return `<div class="text-truncate" style="max-width:360px;">
                        <span class="fw-medium">${CTAR_esc(info.documentos || info.numero_ingreso || '—')}</span>
                        <span class="text-muted">· ${CTAR_esc(info.cliente_nombre || '')}</span>
                    </div>`;
        }).join('');

        return `
            <tr>
                <td class="ps-3">
                    <input type="checkbox" class="form-check-input ctar-sug-check" data-i="${i}" ${crit.marcado ? 'checked' : ''}>
                </td>
                <td class="small">
                    <div>${CTAR_fecha(l.fecha_movimiento)}${l.tipo_linea === 'deposito' ? ' <span class="badge bg-light text-muted border">depósito</span>' : ''}</div>
                    <div class="text-muted">${CTAR_esc(l.autorizacion || l.referencia || '')}</div>
                </td>
                <td class="text-end fw-bold small">$${CTAR_num(l.monto_bruto)}</td>
                <td class="small">${detalleCobros}</td>
                <td class="text-end small ${difiere ? 'text-danger fw-bold' : 'fw-bold'}"
                    title="${difiere ? 'La suma de los cobros no coincide con el bruto de la línea' : ''}">
                    $${CTAR_num(sumaCobros)}${s.cobros.length > 1 ? `<div class="text-muted fw-normal">${s.cobros.length} cobros</div>` : ''}
                </td>
                <td><span class="badge ${crit.clase}">${CTAR_esc(crit.texto)}</span></td>
            </tr>`;
    }).join('');

    CTAR_actualizarResumenSugerencias();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalSugerenciasTarjeta')).show();
}

function CTAR_actualizarResumenSugerencias() {
    const checks = [...document.querySelectorAll('.ctar-sug-check')];
    const marcadas = checks.filter((c) => c.checked).length;
    document.getElementById('ctar-sug-resumen').textContent =
        `${marcadas} de ${checks.length} sugerencias seleccionadas`;
    document.getElementById('ctar-sug-aplicar').disabled = marcadas === 0;
    const todas = document.getElementById('ctar-sug-todas');
    todas.checked = marcadas === checks.length;
    todas.indeterminate = marcadas > 0 && marcadas < checks.length;
}

async function CTAR_aplicarSugerencias() {
    const id = document.getElementById('ctar-m-id').value;
    const pares = [];
    document.querySelectorAll('.ctar-sug-check:checked').forEach((chk) => {
        const s = CTAR_sugerencias[parseInt(chk.dataset.i, 10)];
        s.cobros.forEach((c) => pares.push({
            id_linea: s.id_linea,
            id_ingreso_pago: c.id_ingreso_pago,
            origen: 'auto',
            score: s.score,
            criterio: s.criterio,
        }));
    });
    if (!pares.length) return;

    const btn = document.getElementById('ctar-sug-aplicar');
    btn.disabled = true;
    try {
        const r = await CTAR_post('cruzarAjax', { id_cabecera: id, pares });
        bootstrap.Modal.getInstance(document.getElementById('modalSugerenciasTarjeta'))?.hide();
        await CTAR_refrescarDetalle();
        CTAR_aviso('success', 'Cruce automático',
            `Se emparejaron ${r.creados} cobros.` + (r.omitidos.length ? ` ${r.omitidos.length} no se pudieron cruzar.` : ''));
    } catch (e) {
        CTAR_aviso('error', 'No se pudo cruzar', e.message);
    } finally {
        btn.disabled = false;
    }
}

document.addEventListener('change', (ev) => {
    if (ev.target.id === 'ctar-sug-todas') {
        document.querySelectorAll('.ctar-sug-check').forEach((c) => { c.checked = ev.target.checked; });
        CTAR_actualizarResumenSugerencias();
    } else if (ev.target.classList && ev.target.classList.contains('ctar-sug-check')) {
        CTAR_actualizarResumenSugerencias();
    }
});

// ─── Totales ────────────────────────────────────────────────────────────────

/** Líneas del estado de cuenta que pasan el filtro Desde/Hasta (todas si no hay filtro). */
function CTAR_lineasVisibles() {
    const rango = CTAR_rangoFechas('lineas');
    return (CTAR_detalle.lineas || []).filter((l) => CTAR_enRango(l.fecha_movimiento, rango));
}

/**
 * Totales de lo que se ve en pantalla, con el mismo criterio que el servidor
 * (ConciliacionTarjetasService::calcularTotales): solo cuentan las líneas cruzadas, y
 * el bruto es lo cruzado. El cierre y el asiento usan SIEMPRE los del servidor (todo
 * lo cruzado), por eso con filtro activo se avisa cuál será el total contabilizado.
 */
function CTAR_totalesVisibles() {
    const r2 = (n) => Math.round(n * 100) / 100;
    // Filtro de cobros: un cruce cuenta si su cobro pasa el filtro de fechas (fecha de la
    // factura) y el buscador de texto.
    // Los cobros cruzados en esta conciliación vienen en CTAR_detalle.cobros con id_cruce.
    const rangoCobros = CTAR_rangoFechas('cobros');
    const qCobros = CTAR_textoBuscarCobro();
    const hayFiltroCobros = !!(rangoCobros.desde || rangoCobros.hasta || qCobros);
    const cobroPorPago = new Map((CTAR_detalle.cobros || []).map((c) => [String(c.id_ingreso_pago), c]));
    const cruceCuenta = (cr) => {
        if (!hayFiltroCobros) return true;
        const c = cobroPorPago.get(String(cr.id_ingreso_pago));
        return c ? (CTAR_cobroEnRango(c, rangoCobros) && CTAR_cobroCoincideTexto(c, qCobros)) : false;
    };

    // Por línea visible: su bruto es lo cruzado que pasa el filtro de cobros, y sus
    // descuentos (comisión, IVA, retenciones, otros) se prorratean en esa misma
    // proporción — una línea de depósito cruzada con varios cobros puede pasar a medias.
    const t = { total_bruto_cruzado: 0, total_comision: 0, total_iva_comision: 0,
                total_retencion_ir: 0, total_retencion_iva: 0, total_otros: 0 };
    CTAR_lineasVisibles().forEach((l) => {
        const cruces = l.cruces_detalle || [];
        if (!cruces.length) return;
        const totalLinea = cruces.reduce((s, cr) => s + (parseFloat(cr.monto_cruzado) || 0), 0);
        const incluido   = cruces.filter(cruceCuenta).reduce((s, cr) => s + (parseFloat(cr.monto_cruzado) || 0), 0);
        if (incluido <= 0) return;
        const prop = totalLinea > 0 ? incluido / totalLinea : 0;
        t.total_bruto_cruzado += incluido;
        t.total_comision      += (parseFloat(l.comision) || 0) * prop;
        t.total_iva_comision  += (parseFloat(l.iva_comision) || 0) * prop;
        t.total_retencion_ir  += (parseFloat(l.retencion_ir) || 0) * prop;
        t.total_retencion_iva += (parseFloat(l.retencion_iva) || 0) * prop;
        t.total_otros         += (parseFloat(l.otros_descuentos) || 0) * prop;
    });
    Object.keys(t).forEach((k) => { t[k] = r2(t[k]); });
    t.total_neto = r2(t.total_bruto_cruzado - t.total_comision - t.total_iva_comision
        - t.total_retencion_ir - t.total_retencion_iva - t.total_otros);
    return t;
}

/** ¿Hay algún filtro activo (fechas del estado de cuenta, fechas o buscador de cobros)? Todos afectan los totales. */
function CTAR_hayFiltroLineas() {
    const l = CTAR_rangoFechas('lineas');
    const c = CTAR_rangoFechas('cobros');
    return !!(l.desde || l.hasta || c.desde || c.hasta || CTAR_textoBuscarCobro());
}

function CTAR_pintarTotales() {
    const filtrado = CTAR_hayFiltroLineas();
    const t = filtrado ? CTAR_totalesVisibles() : CTAR_detalle.totales;
    const retenciones = (parseFloat(t.total_retencion_ir) || 0) + (parseFloat(t.total_retencion_iva) || 0);

    document.getElementById('ctar-m-t-bruto').textContent       = CTAR_num(t.total_bruto_cruzado);
    document.getElementById('ctar-m-t-comision').textContent    = CTAR_num(t.total_comision);
    document.getElementById('ctar-m-t-iva').textContent         = CTAR_num(t.total_iva_comision);
    document.getElementById('ctar-m-t-retenciones').textContent = CTAR_num(retenciones);
    document.getElementById('ctar-m-t-neto').textContent        = CTAR_num(t.total_neto);

    const aviso = document.getElementById('ctar-m-t-aviso-filtro');
    if (aviso) {
        aviso.classList.toggle('d-none', !filtrado);
        document.getElementById('ctar-m-t-aviso-total').textContent =
            CTAR_num(CTAR_detalle.totales.total_neto);
    }

    CTAR_recalcularDiferencia();
}

/** La diferencia se recalcula en vivo mientras el usuario digita el depósito. */
function CTAR_recalcularDiferencia() {
    if (!CTAR_detalle) return;

    const neto = parseFloat((CTAR_hayFiltroLineas() ? CTAR_totalesVisibles() : CTAR_detalle.totales).total_neto) || 0;
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

/**
 * Botón «Cargar» (junto al archivo): guarda el encabezado —crea la conciliación si es
 * nueva— y lee el estado de cuenta. Son dos llamadas (guardar → importar): si la
 * lectura falla, la conciliación ya queda creada en borrador y el usuario solo
 * corrige el perfil o el archivo y vuelve a pulsar Cargar.
 */
async function CTAR_cargarArchivo() {
    const archivo = document.getElementById('ctar-m-archivo').files[0];
    const tieneLineas = !!(CTAR_detalle && (CTAR_detalle.lineas || []).length);

    if (!document.getElementById('ctar-m-perfil').value) {
        CTAR_aviso('warning', 'Falta el perfil',
            'No hay un perfil de lectura para esta procesadora. Pida al superadministrador que lo cree en Configuración → Perfiles de lectura de tarjetas.');
        return;
    }
    if (!archivo) {
        CTAR_aviso('warning', 'Falta el archivo', 'Seleccione el estado de cuenta a cargar.');
        return;
    }
    if (tieneLineas && !await CTAR_confirmar('¿Reemplazar el estado de cuenta?',
        'Cargar un archivo reemplaza las líneas y los cruces que ya tiene esta conciliación.', 'Sí, reemplazar')) return;

    const btn = document.getElementById('ctar-btn-cargar');
    btn.disabled = true;
    try {
        let r;
        try {
            r = await CTAR_post('guardarAjax', CTAR_datosCabecera());
            document.getElementById('ctar-m-id').value = r.id;
        } catch (e) {
            CTAR_aviso('error', 'No se pudo guardar', e.message);
            return;
        }

        let importado = null;
        let errorLectura = null;
        try {
            importado = await CTAR_importarArchivo(r.id);
            document.getElementById('ctar-m-archivo').value = '';
        } catch (e) {
            errorLectura = e.message;
        }

        await CTAR_refrescarDetalle();
        CTAR_cargar();

        if (errorLectura) {
            CTAR_aviso('error', 'No se pudo leer el archivo',
                `${errorLectura} La conciliación quedó guardada: revise el perfil o el archivo y vuelva a pulsar Cargar.`);
        } else {
            CTAR_aviso('success', 'Estado de cuenta cargado',
                `Se leyeron ${importado.insertadas} líneas de ${importado.total_leidas}.` +
                (importado.descartadas ? ` ${importado.descartadas} filas se descartaron por no tener fecha o valor.` : ''));
        }
    } finally {
        // Si quedó en borrador, CTAR_habilitarAcciones ya lo dejó habilitado al repintar.
        btn.disabled = !!(CTAR_detalle && CTAR_detalle.cabecera.estado !== 'borrador');
    }
}

/** Botón «Guardar» del pie: solo el encabezado (una vez cargado el estado de cuenta). */
async function CTAR_guardar() {
    try {
        await CTAR_post('guardarAjax', CTAR_datosCabecera());
        await CTAR_refrescarDetalle();
        CTAR_cargar();
        CTAR_aviso('success', 'Guardado', 'La conciliación se guardó.');
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

// El perfil y el archivo se eligen en el mismo encabezado del modal; el archivo se
// lee al pulsar Guardar (ver CTAR_guardar). Una conciliación nueva se crea y carga
// el estado de cuenta en ese mismo paso.

/** Llena el selector de perfiles con los que sirven para la procesadora del modal. */
async function CTAR_cargarPerfilesModal(idSeleccionado = '') {
    const idForma = document.getElementById('ctar-m-procesadora').value;
    const sel = document.getElementById('ctar-m-perfil');
    try {
        CTAR_perfilesCache = idForma ? await CTAR_api(`listarPerfilesAjax?id_forma_cobro=${idForma}`) : [];
    } catch (e) {
        CTAR_perfilesCache = [];
    }

    sel.innerHTML = CTAR_perfilesCache.length
        ? CTAR_perfilesCache.map((p) =>
            `<option value="${p.id}" ${String(p.id) === String(idSeleccionado) ? 'selected' : ''}>`
            + `${CTAR_esc(p.nombre_perfil)} (${CTAR_esc(p.tipo_archivo)}, ${p.nivel === 'deposito' ? 'depósitos' : 'transacciones'})</option>`
        ).join('')
        : '<option value="">— No hay perfiles para esta procesadora —</option>';
}

/** Junto al botón Cargar: el archivo que ya se leyó en esta conciliación (vacío si ninguno). */
function CTAR_pintarInfoArchivo() {
    const info = document.getElementById('ctar-m-archivo-info');
    const cab = CTAR_detalle ? CTAR_detalle.cabecera : null;

    if (cab && cab.nombre_archivo) {
        info.innerHTML = `<i class="bi bi-check-circle text-success me-1"></i>Cargado: `
            + `<strong>${CTAR_esc(cab.nombre_archivo)}</strong>`
            + (cab.nombre_perfil ? ` <span class="text-muted">(${CTAR_esc(cab.nombre_perfil)})</span>` : '');
        info.title = cab.nombre_archivo + (cab.nombre_perfil ? ` (${cab.nombre_perfil})` : '');
    } else {
        info.innerHTML = '';
        info.title = '';
    }
}

/** Lee el archivo elegido en la conciliación ya guardada. Devuelve el resultado o lanza el error. */
async function CTAR_importarArchivo(id) {
    const idPerfil = document.getElementById('ctar-m-perfil').value;
    const archivo  = document.getElementById('ctar-m-archivo').files[0];
    if (!idPerfil) {
        throw new Error('No hay un perfil de lectura para esta procesadora. Pida al superadministrador que lo cree en Configuración → Perfiles de lectura de tarjetas.');
    }

    const fd = new FormData();
    fd.append('id', id);
    fd.append('id_perfil', idPerfil);
    fd.append('archivo', archivo);
    return CTAR_api('importarAjax', { method: 'POST', body: fd });
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
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalLineaTarjeta')).show();
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

    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalLineaTarjeta')).show();
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

// Pestaña «Configuración» del modal de la conciliación: se edita la configuración de
// la procesadora elegida en el propio modal. Se guarda por procesadora
// (conciliacion_tarjetas_config), así que vale para esta y las siguientes conciliaciones.

/** Recarga la pestaña solo si está a la vista (al abrirla o al cambiar la procesadora). */
function CTAR_refrescarConfigSiVisible() {
    if (document.querySelector('#ctar-pane-config.active')) CTAR_cargarConfig();
}

async function CTAR_cargarConfig() {
    if (!document.getElementById('ctar-pane-config')) return;   // sin permiso de actualizar
    const idForma = document.getElementById('ctar-m-procesadora').value;
    if (!idForma) return;

    // Estado de la cuenta puente, que vive en la forma de cobro.
    const p = CTAR_PROCESADORAS.find((x) => String(x.id) === String(idForma));
    document.getElementById('ctar-cfg-procesadora-nombre').textContent = p ? p.nombre : '—';
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
    const idForma = document.getElementById('ctar-m-procesadora').value;
    try {
        await CTAR_post('guardarConfigAjax', {
            id_forma_cobro: idForma,
            id_cuenta_comision: document.getElementById('ctar-cfg-comision').value,
            id_cuenta_iva_comision: document.getElementById('ctar-cfg-iva').value,
            id_cuenta_retencion_ir: document.getElementById('ctar-cfg-retir').value,
            id_cuenta_retencion_iva: document.getElementById('ctar-cfg-retiva').value,
            porcentaje_comision: document.getElementById('ctar-cfg-pc').value || 0,
            porcentaje_iva: document.getElementById('ctar-cfg-pi').value || 0,
            dias_liquidacion: document.getElementById('ctar-cfg-dias').value || 2,
            tolerancia_diferencia: document.getElementById('ctar-cfg-tol').value || 0.05,
        });

        // Los días de liquidación alimentan el semáforo del listado sin recargar la página.
        const p = CTAR_PROCESADORAS.find((x) => String(x.id) === String(idForma));
        if (p) p.dias_liquidacion = document.getElementById('ctar-cfg-dias').value || 2;
        // Si la conciliación ya existe, su aviso contable depende de estas cuentas.
        if (document.getElementById('ctar-m-id').value) await CTAR_refrescarDetalle();

        CTAR_aviso('success', 'Configuración guardada',
            'Se aplica a esta y a las siguientes conciliaciones de la procesadora.');
    } catch (e) {
        CTAR_aviso('error', 'No se pudo guardar', e.message);
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

/** Mismos filtros (buscador) y mismo orden que el listado en pantalla. */
function CTAR_paramsExportacion() {
    return new URLSearchParams({ buscar: CTAR_buscar(), orden: CTAR_ordenParam() });
}

function CTAR_exportarPDF() {
    CMG_descargar(`${CTAR_URL}/exportarPdf?${CTAR_paramsExportacion()}`);
}

function CTAR_exportarExcel() {
    CMG_descargar(`${CTAR_URL}/exportarExcel?${CTAR_paramsExportacion()}`);
}

function CTAR_pdfConciliacion() {
    const id = document.getElementById('ctar-m-id').value;
    if (!id) { CTAR_aviso('info', 'Guarde primero', 'La conciliación debe estar guardada.'); return; }
    CMG_pdfDocumento(`${CTAR_URL}/comprobantePdf?id=${id}`);
}

function CTAR_excelConciliacion() {
    const id = document.getElementById('ctar-m-id').value;
    if (!id) { CTAR_aviso('info', 'Guarde primero', 'La conciliación debe estar guardada.'); return; }
    CMG_descargar(`${CTAR_URL}/comprobanteExcel?id=${id}`);
}

// ─── Arranque ───────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    CTAR_initOrden();
    // El alto se decide al empezar el cambio de pestaña (show), no al terminar (shown):
    // así el modal ya tiene su tamaño nuevo cuando aparece el contenido.
    document.querySelectorAll('#ctar-m-tabs [data-bs-toggle="tab"]').forEach((b) => {
        b.addEventListener('show.bs.tab', () => setTimeout(CTAR_ajustarAltoModal, 0));
    });
    document.getElementById('ctar-tab-asiento-btn')?.addEventListener('shown.bs.tab', CTAR_cargarAsiento);
    document.getElementById('ctar-tab-config-btn')?.addEventListener('shown.bs.tab', CTAR_cargarConfig);
    document.getElementById('ctar-m-procesadora').addEventListener('change', () => {
        CTAR_refrescarConfigSiVisible();
        CTAR_cargarPerfilesModal();   // los perfiles dependen del tipo y banco de la procesadora
    });
    document.getElementById('ctar-m-archivo').addEventListener('change', CTAR_pintarInfoArchivo);
    CTAR_cargar();
});
