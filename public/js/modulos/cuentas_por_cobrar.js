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
let CXC_plantillaEstado = null;  // plantilla 'estado_cuenta_cliente' (null = no creada/aprobada)
let CXC_seleccionados = new Set(); // ids de facturas seleccionadas
// Catálogos del modal cobro
let CXC_catalogos = { puntos: [], conceptos: [], formas: [] };
let CXC_catalogosCargados = false;
let CXC_cobroOrigen = 'FACTURA'; // origen del documento en el modal de cobro
let CXC_agrupado    = false;           // vista agrupada por cliente
let CXC_vista       = 'detalle';       // 'detalle' | 'agrupado' (por cliente) | 'producto'
let CXC_lineas      = null;            // líneas de producto de los documentos (solo vista 'producto'; null = no cargadas)
const CXC_gruposAbiertos = new Set();  // claves de grupos expandidos (vista por producto)
// Consolidado por RUC (fase 1, solo lectura): lo confirma el servidor en cada carga.
// Las filas de OTRO establecimiento (r.es_hermana) no se cobran ni se notifican desde aquí.
let CXC_consolidado = false;

// ¿Ya se consultó el listado al menos una vez? Al entrar al módulo NO se carga nada: el
// usuario elige sus filtros y presiona "Aplicar"; recién ahí se consulta al servidor.
let CXC_cargado = false;
/* ════════════════════════════════════════════════════
   ORDEN DE LA TABLA
   Columnas ordenables al hacer clic en la cabecera. La clave es la misma que el
   `data-sort` del <th> y la que manda al servidor en `orden_col`: tiene que existir
   igual en CuentasPorCobrarRepository::ordenColumnas(), que es quien ordena el Excel
   y el PDF. El orden por defecto (cliente A-Z) y los desempates también son los
   mismos que allá, para que reordenar aquí y recargar den la misma lista.
════════════════════════════════════════════════════ */
const CXC_ORDEN = {
    numero_factura:    { tipo: 'texto'  },
    origen:            { tipo: 'texto'  },
    cliente_nombre:    { tipo: 'texto'  },
    fecha_emision:     { tipo: 'fecha'  },
    fecha_vencimiento: { tipo: 'fecha'  },
    total:             { tipo: 'numero' },
    // "Cobrado" = abonos + retenciones + notas de crédito aplicadas (lo que muestra la columna)
    cobrado:           { tipo: 'numero', valor: r => CXC_totalCobrado(r) },
    saldo:             { tipo: 'numero' },
    dias_vencido:      { tipo: 'numero' },
};
const CXC_ORDEN_DEFECTO    = ['cliente_nombre', 'ASC'];
const CXC_ORDEN_DESEMPATES = { fecha_vencimiento: 'ASC', numero_factura: 'ASC' };

/* El orden vive en los hidden del formulario de filtros: así viaja tal cual en la
   consulta del listado y en las exportaciones. */
function CXC_getOrden() {
    return [
        document.getElementById('cxc-orden-col')?.value || '',
        (document.getElementById('cxc-orden-dir')?.value || 'ASC').toUpperCase()
    ];
}

function CXC_setOrden(col, dir) {
    const inpCol = document.getElementById('cxc-orden-col');
    const inpDir = document.getElementById('cxc-orden-dir');
    if (inpCol) inpCol.value = col;
    if (inpDir) inpDir.value = dir;
}

/* Reordena en el navegador las filas ya cargadas (el listado no es paginado y su
   consulta es cara: no vale la pena volver al servidor solo por reordenar). */
function CXC_aplicarOrden(filas) {
    if (!window.CMG_OrdenTabla) return filas;
    const [col, dir] = CXC_getOrden();
    return window.CMG_OrdenTabla.ordenar(filas, CXC_ORDEN, CXC_ORDEN_DEFECTO, col, dir, CXC_ORDEN_DESEMPATES);
}

/* Alcance elegido en el filtro (el select solo existe cuando la empresa activa es la matriz). */
function CXC_getAlcance() {
    return document.getElementById('cxc-alcance')?.value || 'ESTABLECIMIENTO';
}

// Fase 2 del consolidado: cobro de un documento de OTRO establecimiento desde la matriz.
// El ingreso se registra en los libros de esa empresa, con SUS series, conceptos y formas.
let CXC_cobroEmpresa = 0;            // empresa dueña del documento en el modal de cobro (0 = la activa)
const CXC_catalogosPorEmpresa = {};  // caché de catálogos por establecimiento hermano

/* Catálogos (series, conceptos, formas de cobro) de otro establecimiento del grupo RUC. */
async function CXC_cargarCatalogosDe(idEmpresa) {
    if (CXC_catalogosPorEmpresa[idEmpresa]) return CXC_catalogosPorEmpresa[idEmpresa];
    const r = await fetch(`${BASE_URL}/${RUTA_MODULO_CXC}/getCatalogosCobroAjax?id_empresa=${idEmpresa}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    const data = await r.json();
    if (!data.ok) throw new Error(data.error || 'No se pudieron cargar los catálogos del establecimiento.');
    return (CXC_catalogosPorEmpresa[idEmpresa] = {
        puntos:    data.puntos    || [],
        conceptos: data.conceptos || [],
        formas:    data.formas    || [],
    });
}

/* ════════════════════════════════════════════════════
   INICIALIZACIÓN
════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
    // Al entrar NO se consulta nada: el listado se carga solo cuando el usuario
    // presiona "Aplicar" (ver CXC_cargado / CXC_recargar).
    CXC_initOrden();
    CXC_estadoInicial();
    // Series, conceptos y formas de cobro solo hacen falta si puede cobrar en esta empresa
    // (los de una hermana del consolidado se piden al abrir su cobro).
    if (CXC_PUEDE_COBRAR) CXC_cargarCatalogos();
    if (CXC_TIENE_WA) CXC_cargarPlantillasWA();
    CXC_initBuscadorClientes();
    CXC_initBuscadorProductos();
});

/* Cabeceras clicables: alternan la dirección, guardan la preferencia del usuario
   (sin recargar la página, que perdería los filtros) y repintan la tabla. */
function CXC_initOrden() {
    if (!window.CMG_OrdenTabla) return;
    window.CMG_OrdenTabla.engancharCabeceras({
        modulo:      RUTA_MODULO_CXC,
        contenedor:  '#cxc-thead',
        getOrden:    CXC_getOrden,
        setOrden:    CXC_setOrden,
        onSort:      () => {
            CXC_datos         = CXC_aplicarOrden(CXC_datos);
            CXC_filtradoLocal = CXC_aplicarOrden(CXC_filtradoLocal);
            CXC_renderTabla(CXC_filtradoLocal);
        }
    });
}

/* ════════════════════════════════════════════════════
   CARGAR DATOS PRINCIPALES
════════════════════════════════════════════════════ */
/* Columnas que tiene la tabla en la vista activa: la cabecera estándar (vistas Detallado y
   Por producto) lleva 11; la de "Por cliente" pierde la de Asesor cuando el listado ya está
   acotado a un asesor (CXC_sinAsesor), y entonces son 10. Los mensajes que ocupan la fila
   entera —estado inicial, cargando, error, sin resultados— deben usar ESTE número: con un
   colspan mayor que las columnas reales el navegador agrega una columna fantasma y la fila
   del mensaje se sale del ancho del <thead>. */
function CXC_nCols() {
    return (CXC_vista === 'agrupado' && CXC_sinAsesor()) ? 10 : 11;
}

/* Mensaje de la tabla mientras no se haya aplicado ningún filtro (al entrar al módulo). */
function CXC_estadoInicial() {
    const tbody = document.getElementById('cxc-tbody');
    if (tbody) {
        tbody.innerHTML = `<tr><td colspan="${CXC_nCols()}" class="text-center py-5 text-muted">
            <i class="bi bi-funnel fs-3 d-block mb-2 text-success opacity-50"></i>
            Elija los filtros y presione <span class="fw-semibold text-success">Aplicar</span> para ver las cuentas por cobrar.
        </td></tr>`;
    }
    const label = document.getElementById('cxc-count-label');
    if (label) label.textContent = '';
}

/* Recarga el listado SOLO si ya se aplicó una vez. Antes del primer "Aplicar" no se consulta
   nada al servidor: cambiar un filtro no dispara la carga. */
function CXC_recargar() {
    if (CXC_cargado) CXC_cargar();
}

async function CXC_cargar() {
    CXC_cargado = true;
    const tbody = document.getElementById('cxc-tbody');
    tbody.innerHTML = `<tr><td colspan="${CXC_nCols()}" class="text-center py-4"><div class="spinner-border spinner-border-sm text-success me-2"></div>Cargando…</td></tr>`;
    CXC_seleccionados.clear();

    const params = new URLSearchParams({
        accion:      'generarAjax',
        estado:      document.getElementById('cxc-estado')?.value       || 'PENDIENTES',
        tipo_doc:    document.getElementById('cxc-tipo-doc')?.value     || 'TODOS',
        fecha_desde: document.getElementById('cxc-fecha-desde')?.value  || '',
        fecha_hasta: document.getElementById('cxc-fecha-hasta')?.value  || '',
        id_cliente:  CXC_getClientesSeleccionados(),
        id_vendedor: document.getElementById('cxc-vendedor')?.value    || '',
        id_producto: CXC_getProductosSeleccionados(),
        producto:    (document.getElementById('cxc-search-producto')?.value || '').trim(),
        alcance:     CXC_getAlcance(),
        // El servidor devuelve las filas ya ordenadas (mismas reglas que aquí)
        orden_col:   CXC_getOrden()[0],
        orden_dir:   CXC_getOrden()[1],
        // Vista "Por producto": pide además las líneas de producto de cada documento
        incluir_lineas: CXC_vista === 'producto' ? '1' : '',
    });

    try {
        const r = await fetch(`${BASE_URL}/${RUTA_MODULO_CXC}/generarAjax?${params}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await r.json();

        if (!data.ok) {
            tbody.innerHTML = `<tr><td colspan="${CXC_nCols()}" class="text-center py-4 text-danger"><i class="bi bi-exclamation-triangle me-2"></i>${data.error || 'Error al cargar'}</td></tr>`;
            return;
        }

        CXC_datos = data.filas || [];
        CXC_filtradoLocal = [...CXC_datos];
        // Líneas de producto: vienen solo cuando se pidieron (vista 'producto'); si no, quedan
        // en null para que al cambiar a esa vista se vuelva a consultar.
        CXC_lineas = Array.isArray(data.lineas) ? data.lineas : null;

        // El servidor decide si el consolidado procede (solo desde la matriz)
        CXC_consolidado = !!data.consolidado;
        const estabWrap = document.getElementById('cxc-stat-estab-wrap');
        if (estabWrap) {
            estabWrap.hidden = !CXC_consolidado;
            document.getElementById('cxc-stat-estab').textContent = data.establecimientos || 1;
        }

        CXC_actualizarStats(data.stats || {});
        CXC_renderTabla(CXC_filtradoLocal);

    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="${CXC_nCols()}" class="text-center py-4 text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Error de conexión</td></tr>`;
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

    // Las columnas dependen de la vista: la de "Por cliente" muestra otro detalle.
    CXC_renderCabecera();

    // Todavía sin aplicar: la tabla muestra la invitación a filtrar, no "sin resultados".
    if (!CXC_cargado) { CXC_estadoInicial(); return; }

    if (!filas.length) {
        label.textContent = '0 registros';
        tbody.innerHTML = `<tr><td colspan="${CXC_nCols()}" class="text-center py-5 text-muted">
            <i class="bi bi-wallet2 fs-3 d-block mb-2 text-success opacity-40"></i>
            No se encontraron cuentas por cobrar con los filtros aplicados.
        </td></tr>`;
        return;
    }

    if (CXC_vista === 'producto') { CXC_renderAgrupadoProducto(filas); return; }
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
    // Consolidado: documento de OTRO establecimiento del RUC → solo lectura
    const esHermana  = !!r.es_hermana;
    const estabTxt   = `${r.establecimiento || ''}${r.empresa_nombre ? ' - ' + r.empresa_nombre : ''}`;
    const estabBadge = (CXC_consolidado && r.establecimiento)
        ? `<span class="badge ${esHermana ? 'bg-info bg-opacity-10 text-info border-info' : 'bg-success bg-opacity-10 text-success border-success'} border border-opacity-25 me-1 fw-normal" style="font-size:.65rem;" title="${esc(estabTxt)}">${esc(r.establecimiento)}</span>`
        : '';
    let origenBadge;
    if (esSaldo) {
        origenBadge = `<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 small px-2" title="Saldo inicial de apertura">Saldo inicial</span>`;
    } else if (esRecibo) {
        origenBadge = `<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 small px-2" title="Recibo de venta (comprobante interno)">Recibo</span>`;
    } else {
        origenBadge = `<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 small px-2">Factura</span>`;
    }

    return `
        <tr class="${rowClass}" style="cursor:pointer;" title="Clic para ver el detalle" data-id="${r.id}" data-origen="${r.origen}" data-hermana="${esHermana ? 1 : 0}" data-cliente="${esc(r.cliente_nombre)}" data-factura="${esc(r.numero_factura)}">
            <td class="text-center p-1">
                <input class="form-check-input cxc-chk" type="checkbox" value="${key}"
                       ${(esSaldo || esHermana) ? 'disabled' : (selec ? 'checked' : '')}
                       ${esHermana ? 'title="Documento de otro establecimiento: no se puede notificar desde aquí"' : ''}
                       onchange="CXC_toggleSeleccion('${key}', this.checked)">
            </td>
            <td class="ps-2 fw-semibold text-truncate" title="${esc(r.numero_factura)}" style="font-size:.8rem;white-space:nowrap;">${estabBadge}${esc(r.numero_factura)}</td>
            <td class="text-center" style="white-space:nowrap;">${origenBadge}</td>
            <td class="text-truncate" title="${esc(r.cliente_nombre)}" style="font-size:.8rem;">${esc(r.cliente_nombre)}</td>
            <td style="font-size:.78rem;white-space:nowrap;">${fEmision}</td>
            <td style="font-size:.78rem;white-space:nowrap;">${fVenc}</td>
            <td class="text-end" style="font-size:.78rem;white-space:nowrap;">$${CXC_fmt(r.total)}</td>
            <td class="text-end text-success" style="font-size:.78rem;white-space:nowrap;">$${CXC_fmt(CXC_totalCobrado(r))}</td>
            <td class="text-end fw-bold pe-3" style="font-size:.82rem;white-space:nowrap;color:${saldo > 0 ? '#dc3545' : '#198754'};">$${CXC_fmt(saldo)}</td>
            <td class="text-center" style="overflow:hidden;white-space:nowrap;">${badgeHtml}</td>
            <td class="text-center">${CXC_accionesHtml(r)}</td>
        </tr>`;
}

/* Botonera de un documento (PDF, cobrar, historial, correo, WhatsApp). La comparten la fila
   detallada y la fila del detalle por cliente, para no duplicar reglas de permisos.
   Cada botón aparece solo si se puede usar, con la misma regla que valida el servidor:
   - PDF: facturas y recibos (un saldo inicial no es un documento que se imprima).
   - Cobro: `puede_operar` = crear en Cuentas por Cobrar y en Ingresos en la empresa del
     documento (el cobro emite un ingreso; en el consolidado, en los libros de la hermana).
   - Historial: acceso al Reporte de cartera (CXC_PUEDE_HISTORIAL).
   - WhatsApp: la empresa tiene la integración configurada (CXC_TIENE_WA). */
function CXC_accionesHtml(r) {
    const saldo     = parseFloat(r.saldo) || 0;
    const esSaldo   = r.origen === 'SALDO_INICIAL';
    const esRecibo  = r.origen === 'RECIBO';
    const esHermana = !!r.es_hermana;
    const idEmpresa = parseInt(r.id_empresa) || 0;
    const estabTxt  = `${r.establecimiento || ''}${r.empresa_nombre ? ' - ' + r.empresa_nombre : ''}`;
    // El PDF lo sirve este módulo (su permiso y el alcance del usuario), no Facturas/Recibos:
    // así lo descarga también quien no tiene acceso a esos módulos. En el consolidado viaja
    // la empresa dueña del documento, igual que en el historial.
    const urlPdf = `${BASE_URL}/${RUTA_MODULO_CXC}/pdfDocumento?origen=${encodeURIComponent(r.origen)}&id=${parseInt(r.id)}`
                 + (esHermana && idEmpresa ? `&id_empresa=${idEmpresa}` : '');

    return `
                <div class="d-flex justify-content-center gap-1">
                    ${!esSaldo ? `
                    <a class="btn btn-outline-danger btn-sm py-0 px-2" style="font-size:.72rem;" href="${urlPdf}" data-pdf-documento
                       title="Descargar PDF del documento">
                        <i class="bi bi-file-earmark-pdf"></i>
                    </a>` : ''}
                    ${(saldo > 0 && r.puede_operar) ? `
                    <button class="btn btn-success btn-sm py-0 px-2" style="font-size:.72rem;"
                            title="${esHermana ? `Registrar cobro en el establecimiento ${esc(estabTxt)}` : 'Registrar cobro'}"
                            onclick="CXC_abrirModalCobro(${r.id}, '${r.origen}', ${idEmpresa})">
                        <i class="bi bi-cash-coin"></i>
                    </button>` : ''}
                    ${CXC_PUEDE_HISTORIAL ? `
                    <button class="btn btn-outline-primary btn-sm py-0 px-2" style="font-size:.72rem;" title="Ver historial de cobros"
                            onclick="CXC_abrirHistorial(${r.id}, '${esc(r.numero_factura)}', '${r.origen}', ${idEmpresa})">
                        <i class="bi bi-clock-history"></i>
                    </button>` : ''}
                    ${(!esSaldo && !esHermana) ? `
                    <button class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:.72rem;" title="Enviar recordatorio email"
                            onclick="CXC_abrirEmail(${r.id}, '${esc(r.numero_factura)}', '${esc(r.cliente_email || '')}', '${esc(r.cliente_nombre)}', '${r.origen}')">
                        <i class="bi bi-envelope"></i>
                    </button>` : ''}
                    ${(CXC_TIENE_WA && !esSaldo && !esRecibo && !esHermana) ? `
                    <button class="btn btn-sm py-0 px-2" style="font-size:.72rem;background:#25d366;color:#fff;" title="Enviar WhatsApp"
                            onclick="CXC_abrirWA(${r.id}, '${esc(r.numero_factura)}', '${esc(r.cliente_telefono || '')}', '${esc(r.cliente_nombre)}')">
                        <i class="bi bi-whatsapp"></i>
                    </button>` : ''}
                </div>`;
}

/* ════════════════════════════════════════════════════
   CABECERA DE LA TABLA SEGÚN LA VISTA
   Detallado y Por producto usan las columnas de siempre.
   En "Por cliente" el cliente ya es la cabecera de la sección,
   así que su detalle muestra lo que hace falta dentro del
   cliente: fecha, documento, total, NC, abonos, retenciones,
   saldo, días vencidos y —salvo que se esté filtrando por un
   asesor (CXC_sinAsesor)— el asesor.
════════════════════════════════════════════════════ */
/* Copia exacta del colgroup/thead de la vista (index.php): anchos y cabeceras ordenables
   (`data-sort` = clave de CXC_ORDEN). Si se cambia allá, cambiar aquí. */
const CXC_COLS_ESTANDAR = `
    <col style="width:36px;"><col style="width:170px;"><col style="width:120px;"><col>
    <col style="width:110px;"><col style="width:126px;"><col style="width:95px;">
    <col style="width:100px;"><col style="width:95px;"><col style="width:125px;"><col style="width:190px;">`;
const CXC_TH_ESTANDAR = `
    <tr>
        <th class="text-center p-1"></th>
        <th class="ps-2 sortable-header" data-sort="numero_factura" role="button" title="Ordenar por documento">Documento <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
        <th class="text-center sortable-header" data-sort="origen" role="button" title="Ordenar por origen">Origen <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
        <th class="sortable-header" data-sort="cliente_nombre" role="button" title="Ordenar por cliente">Cliente <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
        <th class="sortable-header" data-sort="fecha_emision" role="button" title="Ordenar por fecha de emisión">F.Emisión <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
        <th class="sortable-header" data-sort="fecha_vencimiento" role="button" title="Ordenar por fecha de vencimiento">F.Vencimiento <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
        <th class="text-end sortable-header" data-sort="total" role="button" title="Ordenar por total">Total <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
        <th class="text-end sortable-header" data-sort="cobrado" role="button" title="Ordenar por cobrado">Cobrado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
        <th class="text-end pe-3 sortable-header" data-sort="saldo" role="button" title="Ordenar por saldo">Saldo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
        <th class="text-center sortable-header" data-sort="dias_vencido" role="button" title="Ordenar por días vencidos">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
        <th class="text-center">Acciones</th>
    </tr>`;
/* ¿El listado está acotado a UN solo asesor? Pasa al elegir uno en el filtro Vendedor y con
   el usuario restringido a su propio vendedor (CXC_VENDEDOR_UNICO). En ese caso la columna
   Asesor repetiría el mismo nombre en todas las filas —el que ya dice el resumen de filtros—
   y se omite, igual que en el PDF y el Excel (CuentasPorCobrarController::filtraPorVendedor).
   Es la única columna opcional de esta vista: cuando falta, la tabla tiene 10 y no 11. */
function CXC_sinAsesor() {
    return CXC_VENDEDOR_UNICO || !!(document.getElementById('cxc-vendedor')?.value || '');
}

/* Columnas de la vista "Por cliente". Sin la columna Asesor su ancho pasa a N. Documento,
   que es la que queda flexible (<col> sin width). */
function CXC_colsMayor() {
    const sinAse = CXC_sinAsesor();
    return `
    <col style="width:36px;"><col style="width:92px;">${sinAse ? '<col>' : '<col style="width:170px;">'}<col style="width:100px;">
    <col style="width:90px;"><col style="width:95px;"><col style="width:100px;">
    <col style="width:105px;"><col style="width:62px;">${sinAse ? '' : '<col>'}<col style="width:190px;">`;
}
function CXC_thMayor() {
    return `
    <tr>
        <th class="text-center p-1"></th>
        <th class="ps-2">Fecha</th>
        <th>N. Documento</th>
        <th class="text-end">Total</th>
        <th class="text-end" title="Notas de crédito aplicadas">NC</th>
        <th class="text-end" title="Cobros recibidos (efectivo, banco, tarjeta…)">Abonos</th>
        <th class="text-end">Retenciones</th>
        <th class="text-end pe-2">Saldo</th>
        <th class="text-center" title="Días vencidos">Días</th>
        ${CXC_sinAsesor() ? '' : '<th>Asesor</th>'}
        <th class="text-center">Acciones</th>
    </tr>`;
}

function CXC_renderCabecera() {
    const cg = document.getElementById('cxc-colgroup');
    const th = document.getElementById('cxc-thead');
    if (!cg || !th) return;
    const mayor = (CXC_vista === 'agrupado');
    cg.innerHTML = mayor ? CXC_colsMayor() : CXC_COLS_ESTANDAR;
    th.innerHTML = mayor ? CXC_thMayor()   : CXC_TH_ESTANDAR;
    // Los <th> recién creados no tienen el clic de ordenar: se vuelven a enganchar
    // (engancharCabeceras no duplica en los que ya lo tienen y repinta las flechas).
    CXC_initOrden();
}

/* Fila de un documento dentro de la sección de su cliente (vista "Por cliente"):
   fecha, documento, total, NC, abonos, retenciones, saldo, días y asesor (este
   último solo cuando el listado NO está acotado a un asesor; ver CXC_sinAsesor). */
function CXC_filaMayorHtml(r) {
    const dias    = parseInt(r.dias_vencido) || 0;
    const saldo   = parseFloat(r.saldo) || 0;
    const total   = parseFloat(r.total) || 0;
    const nc      = parseFloat(r.total_nc) || 0;
    const nd      = parseFloat(r.total_nd) || 0;
    const abonos  = parseFloat(r.total_cobrado) || 0;
    const ret     = parseFloat(r.total_retenido) || 0;
    const key     = CXC_keyFila(r);
    const selec   = CXC_seleccionados.has(key);
    const esSaldo = r.origen === 'SALDO_INICIAL';
    const esRecibo= r.origen === 'RECIBO';
    const esHermana = !!r.es_hermana;

    let rowClass = '';
    if (saldo > 0 && dias > 90)      rowClass = 'table-danger';
    else if (saldo > 0 && dias > 30) rowClass = 'table-warning';

    // El origen no tiene columna propia: una factura no lleva marca y los demás sí,
    // que son los casos que conviene distinguir de un vistazo.
    const estabTxt   = `${r.establecimiento || ''}${r.empresa_nombre ? ' - ' + r.empresa_nombre : ''}`;
    const estabBadge = (CXC_consolidado && r.establecimiento)
        ? `<span class="badge ${esHermana ? 'bg-info bg-opacity-10 text-info border-info' : 'bg-success bg-opacity-10 text-success border-success'} border border-opacity-25 me-1 fw-normal" style="font-size:.65rem;" title="${esc(estabTxt)}">${esc(r.establecimiento)}</span>`
        : '';
    let origenBadge = '';
    if (esSaldo) {
        origenBadge = `<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 me-1 fw-normal" style="font-size:.65rem;" title="Saldo inicial de apertura">SI</span>`;
    } else if (esRecibo) {
        origenBadge = `<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 me-1 fw-normal" style="font-size:.65rem;" title="Recibo de venta">REC</span>`;
    }

    // Total con nota de débito: el saldo la incluye, así que se avisa en la misma celda
    // para que total − NC − abonos − retenciones siga cuadrando con el saldo.
    const ndTxt = nd > 0 ? ` <span class="text-muted" style="font-size:.68rem;" title="Nota de débito sumada al documento">+${CXC_fmt(nd)}</span>` : '';

    // Columna "Días": antigüedad del documento —días desde su emisión hasta la Fecha Hasta
    // del filtro (o hasta hoy si no hay corte)—, no su mora. El rojo lo sigue marcando la
    // mora (`dias_vencido`), así se ve de un vistazo cuál está vencido sin perder la edad.
    const edad    = Math.max(0, parseInt(r.dias_transcurridos) || 0);
    const diasTxt = dias > 0
        ? `<span class="fw-bold" style="color:#dc3545;" title="${edad} días desde la emisión (${CXC_fmtFecha(r.fecha_emision)}) · venció el ${CXC_fmtFecha(r.fecha_vencimiento)}">${edad}</span>`
        : `<span title="${edad} días desde la emisión (${CXC_fmtFecha(r.fecha_emision)}) · vence el ${CXC_fmtFecha(r.fecha_vencimiento)}">${edad}</span>`;

    return `
        <tr class="${rowClass}" style="cursor:pointer;" title="Clic para ver el detalle" data-id="${r.id}" data-origen="${r.origen}" data-hermana="${esHermana ? 1 : 0}" data-cliente="${esc(r.cliente_nombre)}" data-factura="${esc(r.numero_factura)}">
            <td class="text-center p-1">
                <input class="form-check-input cxc-chk" type="checkbox" value="${key}"
                       ${(esSaldo || esHermana) ? 'disabled' : (selec ? 'checked' : '')}
                       onchange="CXC_toggleSeleccion('${key}', this.checked)">
            </td>
            <td class="ps-2" style="font-size:.78rem;white-space:nowrap;">${CXC_fmtFecha(r.fecha_emision)}</td>
            <td class="fw-semibold text-truncate" title="${esc(r.numero_factura)}" style="font-size:.8rem;">${estabBadge}${origenBadge}${esc(r.numero_factura)}</td>
            <td class="text-end" style="font-size:.78rem;white-space:nowrap;">$${CXC_fmt(total)}${ndTxt}</td>
            <td class="text-end" style="font-size:.78rem;white-space:nowrap;">${nc > 0 ? '$' + CXC_fmt(nc) : '<span class="text-muted">—</span>'}</td>
            <td class="text-end text-success" style="font-size:.78rem;white-space:nowrap;">${abonos > 0 ? '$' + CXC_fmt(abonos) : '<span class="text-muted">—</span>'}</td>
            <td class="text-end" style="font-size:.78rem;white-space:nowrap;">${ret > 0 ? '$' + CXC_fmt(ret) : '<span class="text-muted">—</span>'}</td>
            <td class="text-end fw-bold pe-2" style="font-size:.82rem;white-space:nowrap;color:${saldo > 0 ? '#dc3545' : '#198754'};">$${CXC_fmt(saldo)}</td>
            <td class="text-center" style="font-size:.78rem;white-space:nowrap;">${diasTxt}</td>
            ${CXC_sinAsesor() ? '' : `<td class="text-truncate" title="${esc(r.vendedor_nombre || '')}" style="font-size:.78rem;">${r.vendedor_nombre ? esc(r.vendedor_nombre) : '<span class="text-muted">—</span>'}</td>`}
            <td class="text-center">${CXC_accionesHtml(r)}</td>
        </tr>`;
}

/* ════════════════════════════════════════════════════
   VISTA AGRUPADA POR CLIENTE — formato "mayor"
   El listado arranca PLEGADO: una línea por cliente con sus
   totales. Al desplegar una, se lee como el mayor de una cuenta
   contable: los documentos del cliente y, cerrando la sección,
   su fila de SUBTOTAL. Al final del listado, el TOTAL GENERAL.
   El PDF y el Excel salen siempre con el detalle desplegado.
════════════════════════════════════════════════════ */
/* Secciones DESPLEGADAS de la vista por cliente: el listado arranca plegado —una línea
   por cliente con sus totales— y el set recuerda las que el usuario abre. */
const CXC_clientesAbiertos = new Set();
/* Grupos de la última vista por cliente (clave → grupo), para el envío del estado de cuenta. */
let CXC_gruposCliente = new Map();

/* Agrupa las filas por cliente. La clave es la identificación BASE, no el texto del RUC: así
   el cliente registrado dos veces —con la cédula y con el RUC, que es esa cédula + '001'—
   cae en un solo grupo con su saldo sumado. Sin identificación se agrupa por nombre. */
function CXC_agruparPorCliente(filas) {
    const mapa = new Map();
    for (const r of filas) {
        const key = IdentificacionTercero.claveGrupo(r.cliente_ruc, r.cliente_nombre || 'Sin cliente');
        let g = mapa.get(key);
        if (!g) {
            g = { key, nombre: r.cliente_nombre || 'Sin cliente', ruc: r.cliente_ruc || '', items: [],
                  total: 0, nc: 0, abonos: 0, retenciones: 0, cobrado: 0, saldo: 0 };
            mapa.set(key, g);
        }
        g.items.push(r);
        g.total       += parseFloat(r.total)          || 0;
        g.nc          += parseFloat(r.total_nc)       || 0;
        g.abonos      += parseFloat(r.total_cobrado)  || 0;
        g.retenciones += parseFloat(r.total_retenido) || 0;
        g.cobrado     += CXC_totalCobrado(r);
        g.saldo       += parseFloat(r.saldo)          || 0;
    }
    // Dentro de cada cliente, los documentos van en orden cronológico (como los movimientos
    // de un mayor); los clientes salen en orden alfabético (A-Z), igual que el listado
    // detallado y que las exportaciones (CuentasPorCobrarController::agruparPorCliente).
    for (const g of mapa.values()) {
        g.items.sort((a, b) =>
            String(a.fecha_emision || '').localeCompare(String(b.fecha_emision || '')) ||
            String(a.numero_factura || '').localeCompare(String(b.numero_factura || '')));
    }
    const nom = g => window.CMG_OrdenTabla ? window.CMG_OrdenTabla.normalizar(g.nombre) : String(g.nombre || '').toUpperCase();
    return [...mapa.values()].sort((a, b) => (nom(a) < nom(b) ? -1 : (nom(a) > nom(b) ? 1 : 0)));
}

function CXC_renderAgrupado(filas) {
    const tbody  = document.getElementById('cxc-tbody');
    const label  = document.getElementById('cxc-count-label');
    const grupos = CXC_agruparPorCliente(filas);
    CXC_gruposCliente = new Map(grupos.map(g => [g.key, g]));

    label.textContent = `${filas.length} docs · ${grupos.length} cliente${grupos.length !== 1 ? 's' : ''}`;

    // Columnas de esta vista: [chevron] Fecha | N. Documento | Total | NC | Abonos |
    // Retenciones | Saldo | Días | Asesor | Acciones (ver CXC_thMayor). Asesor se cae si el
    // listado ya está acotado a uno: las filas de sección ajustan su colspan con `colAse`.
    const colAse  = CXC_sinAsesor() ? 0 : 1;   // 1 si la columna Asesor está presente
    const nCols   = 10 + colAse;
    let tTotal = 0, tNc = 0, tAbonos = 0, tRet = 0, tSaldo = 0;
    let html = '';
    for (const g of grupos) {
        tTotal  += g.total;
        tNc     += g.nc;
        tAbonos += g.abonos;
        tRet    += g.retenciones;
        tSaldo  += g.saldo;

        const abierto = CXC_clientesAbiertos.has(g.key);
        const chev    = abierto ? 'bi-chevron-down' : 'bi-chevron-right';
        const importes = () => `
            <td class="text-end" style="font-size:.8rem;">$${CXC_fmt(g.total)}</td>
            <td class="text-end" style="font-size:.8rem;">${g.nc > 0.001 ? '$' + CXC_fmt(g.nc) : '<span class="text-muted">—</span>'}</td>
            <td class="text-end text-success" style="font-size:.8rem;">${g.abonos > 0.001 ? '$' + CXC_fmt(g.abonos) : '<span class="text-muted">—</span>'}</td>
            <td class="text-end" style="font-size:.8rem;">${g.retenciones > 0.001 ? '$' + CXC_fmt(g.retenciones) : '<span class="text-muted">—</span>'}</td>
            <td class="text-end pe-2" style="font-size:.82rem;color:${g.saldo > 0 ? '#dc3545' : '#198754'};">$${CXC_fmt(g.saldo)}</td>`;

        // Cabecera del cliente: siempre lleva sus totales, así plegada resume al cliente en
        // una línea. Al desplegarla salen sus documentos y se cierra con la fila de SUBTOTAL.
        html += `
        <tr class="cxc-mayor-grp" data-gkey="${esc(g.key)}" onclick="CXC_toggleCliente(this)" style="cursor:pointer;" title="Clic para desplegar o plegar los documentos de este cliente">
            <td class="text-center p-1"><i class="bi ${chev} text-success"></i></td>
            <!-- Saldo junto al nombre: con la sección plegada se lee de inmediato lo que debe
                 el cliente, sin recorrer la fila hasta la columna Saldo. El nombre se recorta
                 si no cabe, el saldo nunca (flex:0 0 auto), y el RUC pasa a la línea de abajo
                 para no competir por el ancho de la celda. -->
            <td colspan="2" class="fw-bold" title="${esc(g.nombre)}${g.ruc ? ' · ' + esc(g.ruc) : ''} · Saldo $${CXC_fmt(g.saldo)}" style="font-size:.82rem;overflow:hidden;">
                <div class="d-flex align-items-center gap-1" style="min-width:0;">
                    <span class="text-truncate">${esc(g.nombre)}</span>
                    <span class="badge rounded-pill fw-semibold ${g.saldo > 0.001 ? 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25' : 'bg-success bg-opacity-10 text-success border border-success border-opacity-25'}"
                          style="font-size:.7rem;flex:0 0 auto;" title="Saldo pendiente del cliente">$${CXC_fmt(g.saldo)}</span>
                </div>
                ${g.ruc ? `<div class="text-muted fw-normal text-truncate" style="font-size:.68rem;line-height:1.1;">${esc(g.ruc)}</div>` : ''}
            </td>
            ${importes()}
            <td class="text-center" style="font-size:.72rem;">
                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fw-normal" title="${g.items.length} documento${g.items.length !== 1 ? 's' : ''}">${g.items.length}</span>
            </td>
            ${colAse ? '<td></td>' : ''}
            <td class="text-center">
                <div class="d-flex justify-content-center gap-1">
                ${g.saldo > 0.001 ? `
                <button class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:.72rem;"
                        title="Enviar estado de cuenta por correo (resumen de sus documentos pendientes)"
                        onclick="event.stopPropagation(); CXC_abrirEmailCliente(this.closest('tr').dataset.gkey)">
                    <i class="bi bi-envelope"></i>
                </button>` : ''}
                ${(CXC_TIENE_WA && g.saldo > 0.001) ? `
                <button class="btn btn-sm py-0 px-2" style="font-size:.72rem;background:#25d366;color:#fff;"
                        title="Enviar estado de cuenta por WhatsApp (resumen vencido o total)"
                        onclick="event.stopPropagation(); CXC_abrirWAEstado(this.closest('tr').dataset.gkey)">
                    <i class="bi bi-whatsapp"></i>
                </button>` : ''}
                </div>
            </td>
        </tr>`;

        if (abierto) {
            // Sin fila de SUBTOTAL: la cabecera del cliente ya trae sus totales y el saldo,
            // así que repetirlos al cerrar la sección solo alarga la lista. El separador
            // mantiene la sección visualmente cerrada.
            for (const r of g.items) html += CXC_filaMayorHtml(r);
            html += `<tr class="cxc-mayor-gap"><td colspan="${nCols}"></td></tr>`;
        }
    }

    html += `
        <tr class="cxc-mayor-total">
            <td colspan="3" class="text-end" style="font-size:.8rem;">TOTAL GENERAL (${grupos.length} cliente${grupos.length !== 1 ? 's' : ''})</td>
            <td class="text-end" style="font-size:.82rem;">$${CXC_fmt(tTotal)}</td>
            <td class="text-end" style="font-size:.82rem;">${tNc > 0.001 ? '$' + CXC_fmt(tNc) : '<span class="text-muted">—</span>'}</td>
            <td class="text-end text-success" style="font-size:.82rem;">${tAbonos > 0.001 ? '$' + CXC_fmt(tAbonos) : '<span class="text-muted">—</span>'}</td>
            <td class="text-end" style="font-size:.82rem;">${tRet > 0.001 ? '$' + CXC_fmt(tRet) : '<span class="text-muted">—</span>'}</td>
            <td class="text-end pe-2" style="font-size:.85rem;color:${tSaldo > 0 ? '#dc3545' : '#198754'};">$${CXC_fmt(tSaldo)}</td>
            <td colspan="${2 + colAse}"></td>
        </tr>`;

    tbody.innerHTML = html;
}

/* Despliega/pliega la sección de un cliente (el set guarda las abiertas). */
function CXC_toggleCliente(el) {
    const k = el.getAttribute('data-gkey');
    if (CXC_clientesAbiertos.has(k)) CXC_clientesAbiertos.delete(k);
    else CXC_clientesAbiertos.add(k);
    CXC_renderTabla(CXC_filtradoLocal);
}

/* Pliega/despliega un grupo de la vista por producto (aquí el set guarda los abiertos). */
function CXC_toggleGrupo(el) {
    const k = el.getAttribute('data-gkey');
    if (CXC_gruposAbiertos.has(k)) CXC_gruposAbiertos.delete(k);
    else CXC_gruposAbiertos.add(k);
    CXC_renderTabla(CXC_filtradoLocal);
}

/* Cambia entre vista detallada y agrupada (llamado desde los botones de la vista). */
function CXC_setVista(modo) {
    CXC_vista    = ['detalle', 'agrupado', 'producto'].includes(modo) ? modo : 'detalle';
    CXC_agrupado = (CXC_vista === 'agrupado');
    const botones = { detalle: 'cxc-btn-detalle', agrupado: 'cxc-btn-agrupado', producto: 'cxc-btn-producto' };
    for (const [m, id] of Object.entries(botones)) {
        const b = document.getElementById(id);
        if (!b) continue;
        b.classList.toggle('btn-success',          m === CXC_vista);
        b.classList.toggle('btn-outline-success',  m !== CXC_vista);
    }
    // La vista por producto necesita las líneas de cada documento: si aún no se cargaron
    // (la carga normal no las trae), se vuelve a consultar el listado pidiéndolas.
    if (CXC_vista === 'producto' && CXC_lineas === null && CXC_cargado) {
        CXC_cargar();
        return;
    }
    CXC_renderTabla(CXC_filtradoLocal);
}

/* ════════════════════════════════════════════════════
   VISTA AGRUPADA POR PRODUCTO
   Un grupo por producto (código; si no hay, nombre) con
   los documentos pendientes que lo contienen. Un documento
   con varios productos aparece en cada uno; los saldos
   iniciales no tienen líneas y no entran en esta vista.
════════════════════════════════════════════════════ */
function CXC_renderAgrupadoProducto(filas) {
    const tbody = document.getElementById('cxc-tbody');
    const label = document.getElementById('cxc-count-label');

    const visibles = new Map(filas.map(r => [CXC_keyFila(r), r]));
    const mapa = new Map();
    for (const l of (CXC_lineas || [])) {
        const keyDoc = `${l.origen}:${l.id_doc}`;
        const r = visibles.get(keyDoc);
        if (!r) continue;
        const pk = l.codigo ? 'c:' + l.codigo : 'n:' + l.nombre;
        let g = mapa.get(pk);
        if (!g) {
            g = { key: pk, codigo: l.codigo || '', nombre: l.nombre || '(sin descripción)', cantidad: 0, valor: 0,
                  total: 0, cobrado: 0, saldo: 0, docs: new Map() };
            mapa.set(pk, g);
        }
        g.cantidad += parseFloat(l.cantidad) || 0;
        g.valor    += parseFloat(l.valor)    || 0;
        if (!g.docs.has(keyDoc)) {
            g.docs.set(keyDoc, r);
            g.total   += parseFloat(r.total) || 0;
            g.cobrado += CXC_totalCobrado(r);
            g.saldo   += parseFloat(r.saldo) || 0;
        }
    }
    // Productos en orden alfabético, igual que las exportaciones (agruparPorProducto).
    const nomProd = g => window.CMG_OrdenTabla ? window.CMG_OrdenTabla.normalizar(g.nombre) : String(g.nombre || '').toUpperCase();
    const grupos = [...mapa.values()].sort((a, b) => (nomProd(a) < nomProd(b) ? -1 : (nomProd(a) > nomProd(b) ? 1 : 0)));
    const sinLineas = filas.filter(r => r.origen === 'SALDO_INICIAL').length;

    label.textContent = `${filas.length} docs · ${grupos.length} producto${grupos.length !== 1 ? 's' : ''}`;

    if (!grupos.length) {
        tbody.innerHTML = `<tr><td colspan="${CXC_nCols()}" class="text-center py-5 text-muted">
            <i class="bi bi-box-seam fs-3 d-block mb-2 text-success opacity-40"></i>
            No hay documentos con líneas de producto para los filtros aplicados.
        </td></tr>`;
        return;
    }

    let html = '';
    for (const g of grupos) {
        const abierto = CXC_gruposAbiertos.has(g.key);
        const chev = abierto ? 'bi-chevron-down' : 'bi-chevron-right';
        html += `
        <tr class="cxc-grp-row" data-gkey="${esc(g.key)}" onclick="CXC_toggleGrupo(this)" style="cursor:pointer;background:#eef7ff;">
            <td class="text-center p-1"><i class="bi ${chev} text-primary"></i></td>
            <td colspan="5" class="fw-bold" style="font-size:.82rem;">
                ${esc(g.nombre)}${g.codigo ? ` <small class="text-muted fw-normal">${esc(g.codigo)}</small>` : ''}
                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 ms-2 fw-normal">${g.docs.size} doc${g.docs.size !== 1 ? 's' : ''}</span>
                <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 ms-1 fw-normal" title="Cantidad del producto en los documentos">cant. ${CXC_fmt(g.cantidad)}</span>
            </td>
            <td class="text-end fw-semibold" style="font-size:.8rem;" title="Valor del producto en los documentos (Total: $${CXC_fmt(g.total)})">$${CXC_fmt(g.valor)}</td>
            <td class="text-end fw-semibold text-success" style="font-size:.8rem;" title="Cobrado de los documentos">$${CXC_fmt(g.cobrado)}</td>
            <td class="text-end fw-bold pe-3" style="font-size:.82rem;color:${g.saldo > 0 ? '#dc3545' : '#198754'};" title="Saldo de los documentos que contienen el producto">$${CXC_fmt(g.saldo)}</td>
            <td colspan="2"></td>
        </tr>`;
        if (abierto) {
            for (const r of g.docs.values()) html += CXC_filaHtml(r);
        }
    }
    if (sinLineas > 0) {
        html += `<tr><td colspan="${CXC_nCols()}" class="text-muted small py-2 text-center">
            ${sinLineas} saldo${sinLineas !== 1 ? 's' : ''} inicial${sinLineas !== 1 ? 'es' : ''} sin líneas de producto no se muestra${sinLineas !== 1 ? 'n' : ''} en esta vista.
        </td></tr>`;
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
function CXC_toggleSeleccion(key, sel) {
    sel ? CXC_seleccionados.add(key) : CXC_seleccionados.delete(key);
}

function CXC_seleccionarTodos(sel) {
    // Facturas y recibos: los saldos iniciales no tienen email/WhatsApp, y los
    // documentos de otro establecimiento (consolidado) son solo lectura.
    CXC_filtradoLocal.forEach(r => {
        if (r.origen === 'SALDO_INICIAL' || r.es_hermana) return;
        const key = CXC_keyFila(r);
        sel ? CXC_seleccionados.add(key) : CXC_seleccionados.delete(key);
    });
    document.querySelectorAll('.cxc-chk:not([disabled])').forEach(c => c.checked = sel);
}

/* ════════════════════════════════════════════════════
   MODAL COBRO
════════════════════════════════════════════════════ */
async function CXC_abrirModalCobro(idVenta, origen = 'FACTURA', idEmpresa = 0) {
    CXC_cobroOrigen = origen;
    // Consolidado (fase 2): si el documento es de OTRO establecimiento del RUC, el cobro se
    // registra en ESA empresa, con sus series, conceptos, formas de cobro y contabilidad.
    const filaDoc = CXC_datos.find(r => r.id == idVenta && r.origen === origen);
    CXC_cobroEmpresa = (filaDoc && filaDoc.es_hermana) ? (parseInt(idEmpresa) || parseInt(filaDoc.id_empresa) || 0) : 0;
    const empQs = CXC_cobroEmpresa ? `&id_empresa=${CXC_cobroEmpresa}` : '';
    let cat = CXC_catalogos;
    if (CXC_cobroEmpresa) {
        try { cat = await CXC_cargarCatalogosDe(CXC_cobroEmpresa); }
        catch (e) { CXC_toast(e.message || 'No se pudieron cargar los catálogos del establecimiento.', 'danger'); return; }
    }
    let f;
    if (origen === 'SALDO_INICIAL') {
        // Saldo inicial: tomar datos de la fila ya cargada (no hay endpoint de factura).
        // `total_nc` viene del servidor igual que `total_retenido` (el listado lo calcula
        // con lateralNcSaldoInicial y ya está descontado del saldo); antes se forzaba a 0
        // y el modal mostraba Nota Crédito 0.00 aunque la NC sí estuviera aplicada.
        const fila = CXC_datos.find(r => r.id == idVenta && r.origen === 'SALDO_INICIAL');
        if (!fila) return;
        f = { numero_factura: fila.numero_factura, cliente_nombre: fila.cliente_nombre,
              importe_total: fila.total, total_cobrado: fila.total_cobrado,
              total_retenido: fila.total_retenido || 0, total_nc: fila.total_nc || 0,
              total_nd: 0, saldo: fila.saldo };
    } else {
        // Factura o recibo: obtener datos en tiempo real del servidor
        const infoUrl = origen === 'RECIBO'
            ? `${BASE_URL}/${RUTA_MODULO_CXC}/getReciboParaCobroInfoAjax?id_recibo=${idVenta}${empQs}`
            : `${BASE_URL}/${RUTA_MODULO_CXC}/getFacturaParaCobroInfoAjax?id_venta=${idVenta}${empQs}`;
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
    const pts = cat.puntos;
    // El servidor ya manda solo las series activas; si no queda ninguna se dice por qué,
    // en vez de dejar un "— Seleccione —" vacío.
    selPunto.innerHTML = pts.length
        ? '<option value="">— Seleccione —</option>'
          + pts.map(p => `<option value="${p.id_punto}">${p.cod_establecimiento}-${p.codigo_punto}</option>`).join('')
        : '<option value="">Sin series activas</option>';
    if (pts.length === 1) {
        selPunto.selectedIndex = 1;
        CXC_cargarSecuencial(pts[0].id_punto);
    } else {
        document.getElementById('cobro-secuencial').value = '';
    }

    // ── Concepto (solo lectura, auto-seleccionado) ─────────────────────────
    const selConc = document.getElementById('cobro-concepto');
    const cons = cat.conceptos;
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
    const fps = cat.formas;
    CXC_formasCobro = cat.formas; // alias para toggleBancoDatos: el catálogo EN USO (propio o de la hermana)
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

    // Aviso: el cobro va a los libros de otro establecimiento (consolidado, fase 2)
    const avisoEst = document.getElementById('cobro-aviso-establecimiento');
    if (avisoEst) {
        avisoEst.hidden = !CXC_cobroEmpresa;
        if (CXC_cobroEmpresa && filaDoc) {
            avisoEst.innerHTML = `<i class="bi bi-diagram-3 me-1"></i>Este cobro se registra en el establecimiento `
                + `<strong>${esc(filaDoc.establecimiento || '')} - ${esc(filaDoc.empresa_nombre || '')}</strong>: `
                + `el ingreso, su secuencial y su contabilidad pertenecen a esa empresa.`;
        }
    }

    new bootstrap.Modal(document.getElementById('modalCobro')).show();
}

async function CXC_cargarSecuencial(idPunto) {
    const el = document.getElementById('cobro-secuencial');
    if (!el) return;
    if (!idPunto) { el.value = ''; return; }
    el.value = '…';
    // La fecha viaja siempre: si Ingresos está configurado para numerar por fecha de emisión
    // (Empresa → Secuenciales), el número depende del periodo al que pertenece esa fecha.
    const fecha = document.getElementById('cobro-fecha')?.value || '';
    try {
        const r = await fetch(
            `${BASE_URL}/${RUTA_MODULO_CXC}/getSecuencialAjax?id_punto_emision=${idPunto}&fecha=${encodeURIComponent(fecha)}${CXC_cobroEmpresa ? '&id_empresa=' + CXC_cobroEmpresa : ''}`,
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
        // Consolidado (fase 2): el servidor registra el ingreso en la hermana dueña del documento
        if (CXC_cobroEmpresa) fd.append('id_empresa', CXC_cobroEmpresa);

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
async function CXC_abrirHistorial(idVenta, nroFactura, origen = 'FACTURA', idEmpresa = 0) {
    const esSaldo  = origen === 'SALDO_INICIAL';
    const esRecibo = origen === 'RECIBO';
    const prefijo  = esSaldo ? 'Saldo inicial: ' : (esRecibo ? 'Recibo: ' : 'Factura: ');
    document.getElementById('historial-subtitulo').textContent = prefijo + nroFactura;
    document.getElementById('historial-tbody').innerHTML = '<tr><td colspan="7" class="text-center text-muted">Cargando…</td></tr>';
    document.getElementById('historial-total').textContent = '0.00';
    document.getElementById('historial-cargos').textContent = '0.00';
    document.getElementById('historial-fila-cargos').hidden = true;

    new bootstrap.Modal(document.getElementById('modalHistorial')).show();

    try {
        // id_empresa: en el consolidado la fila puede ser de otro establecimiento (solo lectura);
        // el servidor solo lo acepta si es una hermana del grupo RUC consolidable.
        const emp = idEmpresa ? `&id_empresa=${parseInt(idEmpresa)}` : '';
        const url = esSaldo
            ? `${BASE_URL}/${RUTA_MODULO_CXC}/historialCobrosSaldoInicialAjax?id_saldo=${idVenta}${emp}`
            : (esRecibo
                ? `${BASE_URL}/${RUTA_MODULO_CXC}/historialCobrosReciboAjax?id_recibo=${idVenta}${emp}`
                : `${BASE_URL}/${RUTA_MODULO_CXC}/historialCobrosAjax?id_venta=${idVenta}${emp}`);
        const r = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await r.json();

        if (!data.ok) {
            document.getElementById('historial-tbody').innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error al cargar</td></tr>';
            return;
        }

        const h = data.historial || [];
        if (!h.length) {
            document.getElementById('historial-tbody').innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">No hay movimientos registrados.</td></tr>';
            return;
        }

        let abonos = 0;
        let cargos = 0;
        let html   = '';
        for (const c of h) {
            // Compatibilidad: saldos iniciales devuelven el formato antiguo
            // (numero_ingreso / monto_cobrado, sin tipo ni signo).
            const tipo  = c.tipo || 'COBRO';
            const signo = parseInt(c.signo != null ? c.signo : 1) || 1;
            const m     = parseFloat(c.monto != null ? c.monto : c.monto_cobrado) || 0;
            const meta  = CXC_HIST_TIPOS[tipo] || CXC_HIST_TIPOS.COBRO;

            if (signo < 0) cargos += m; else abonos += m;

            html += `<tr>
                <td style="font-size:.8rem;"><span class="badge bg-${meta.color} bg-opacity-10 text-${meta.color} border border-${meta.color} border-opacity-25 fw-normal"><i class="bi ${meta.icono} me-1"></i>${meta.etiqueta}</span></td>
                <td style="font-size:.8rem;">${CXC_fmtFechaHora(c.fecha_emision)}</td>
                <td style="font-size:.8rem;">${esc(c.numero || c.numero_ingreso || '')}</td>
                <td style="font-size:.8rem;">${esc(c.forma_cobro || '—')}</td>
                <td style="font-size:.8rem;">${esc(c.usuario_nombre || '—')}</td>
                <td class="text-end fw-semibold text-${signo < 0 ? 'dark' : 'success'}" style="font-size:.8rem;">${signo < 0 ? '+' : ''}$${CXC_fmt(m)}</td>
                <td style="font-size:.78rem;">${esc(c.observaciones || '')}</td>
            </tr>`;
        }
        document.getElementById('historial-tbody').innerHTML = html;
        document.getElementById('historial-total').textContent = CXC_fmt(abonos);
        document.getElementById('historial-cargos').textContent = CXC_fmt(cargos);
        document.getElementById('historial-fila-cargos').hidden = cargos <= 0;
    } catch (e) {
        document.getElementById('historial-tbody').innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error de conexión</td></tr>';
    }
}

/* Presentación de cada tipo de movimiento del historial. La nota de débito es un
   CARGO (suma al saldo), por eso su monto se muestra con "+" y va en su propia
   línea del pie, fuera del total abonado. */
const CXC_HIST_TIPOS = {
    COBRO:        { etiqueta: 'Cobro',           color: 'success', icono: 'bi-cash-coin' },
    RETENCION:    { etiqueta: 'Retención',       color: 'warning', icono: 'bi-receipt' },
    NOTA_CREDITO: { etiqueta: 'Nota de crédito', color: 'info',    icono: 'bi-file-earmark-minus' },
    NOTA_DEBITO:  { etiqueta: 'Nota de débito',  color: 'dark',    icono: 'bi-file-earmark-plus' },
};

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
    CXC_abrirModalEmailMasivo(filas);
}

/* Correo del estado de cuenta de UN cliente (botón de su línea en la vista por cliente):
   el mismo modal y el mismo envío que el masivo, con los documentos con saldo de ese
   cliente. En el consolidado incluye los de otros establecimientos (viajan con su
   id_empresa y el servidor valida que la hermana sea del grupo). Los saldos iniciales
   no entran: el correo resume facturas y recibos. */
function CXC_abrirEmailCliente(gkey) {
    const g = CXC_gruposCliente.get(gkey);
    if (!g) return;
    const filas = g.items.filter(r => (parseFloat(r.saldo) || 0) > 0.001 && r.origen !== 'SALDO_INICIAL');
    if (!filas.length) {
        CXC_toast('El cliente no tiene facturas ni recibos con saldo pendiente.', 'warning');
        return;
    }
    CXC_abrirModalEmailMasivo(filas);
}

function CXC_abrirModalEmailMasivo(filas) {

    // Agrupar por cliente: se envía UN correo por cliente con el resumen de todos sus
    // documentos seleccionados (facturas y recibos). La clave es la identificación base y
    // no el id, para que el cliente registrado dos veces —cédula y RUC— reciba un solo
    // correo con TODOS sus documentos y no dos correos parciales.
    const mapa = new Map();
    for (const r of filas) {
        const idCli = parseInt(r.id_cliente) || 0;
        const k = IdentificacionTercero.claveGrupo(r.cliente_ruc, String(r.cliente_nombre || idCli || '?'));
        let g = mapa.get(k);
        if (!g) {
            g = { idCliente: idCli, nombre: r.cliente_nombre || 'Sin nombre', ruc: r.cliente_ruc || '',
                  email: r.cliente_email || '', numDocs: 0, saldo: 0, documentos: [] };
            mapa.set(k, g);
        }
        if (!g.email && r.cliente_email) g.email = r.cliente_email;
        g.numDocs++;
        g.saldo += parseFloat(r.saldo) || 0;
        g.documentos.push({ origen: r.origen || 'FACTURA', id: r.id, id_empresa: parseInt(r.id_empresa) || 0 });
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
   ESTADO DE CUENTA POR WHATSAPP (vista por cliente)
   Un mensaje por cliente con el resumen vencido o el total de
   su deuda (plantilla 'estado_cuenta_cliente'). En el consolidado
   incluye los documentos de TODOS los establecimientos visibles:
   cada documento viaja con su id_empresa y el servidor valida
   que la hermana sea del grupo. Montos y conteos de la vista
   previa son referenciales: el servidor los recalcula desde BD.
════════════════════════════════════════════════════ */
function CXC_resumenWAEstado(g) {
    const res = { vencido: { n: 0, valor: 0, docs: [] }, total: { n: 0, valor: 0, docs: [] } };
    let otrosEstab = 0;
    for (const r of g.items) {
        const saldo = parseFloat(r.saldo) || 0;
        if (saldo <= 0.001) continue;
        const doc = { origen: r.origen || 'FACTURA', id: parseInt(r.id), id_empresa: parseInt(r.id_empresa) || 0 };
        res.total.n++; res.total.valor += saldo; res.total.docs.push(doc);
        if ((parseInt(r.dias_vencido) || 0) > 0) {
            res.vencido.n++; res.vencido.valor += saldo; res.vencido.docs.push(doc);
        }
        if (r.es_hermana) otrosEstab++;
    }
    res.otrosEstab = otrosEstab;
    return res;
}

function CXC_abrirWAEstado(gkey) {
    if (!CXC_TIENE_WA) {
        CXC_toast('WhatsApp no está configurado para esta empresa. Active el módulo de WhatsApp para usar esta función.', 'warning');
        return;
    }
    const g = CXC_gruposCliente.get(gkey);
    if (!g) return;
    const res = CXC_resumenWAEstado(g);
    if (!res.total.n) { CXC_toast('El cliente no tiene documentos con saldo pendiente.', 'warning'); return; }

    document.getElementById('wae-gkey').value = gkey;
    document.getElementById('wae-subtitulo').textContent = `${g.nombre}${g.ruc ? ' · ' + g.ruc : ''}`;
    const lbl = (x) => `${x.n} documento(s) — $${CXC_fmt(x.valor)}`;
    document.getElementById('wae-lbl-vencido').textContent = lbl(res.vencido);
    document.getElementById('wae-lbl-total').textContent   = lbl(res.total);
    const rVenc = document.getElementById('wae-tipo-vencido');
    rVenc.disabled = res.vencido.n === 0;
    (res.vencido.n ? rVenc : document.getElementById('wae-tipo-total')).checked = true;

    // Teléfono: el primero registrado en las fichas del cliente
    const telRaw = (g.items.find(r => r.cliente_telefono) || {}).cliente_telefono || '';
    let tel = telRaw.replace(/[^0-9]/g, '');
    if (tel) {
        if (tel.startsWith('0')) tel = tel.substring(1);
        if (!tel.startsWith('593')) tel = '593' + tel;
    }
    document.getElementById('wae-telefono').value = tel;

    document.getElementById('wae-aviso-plantilla').classList.toggle('d-none', !!CXC_plantillaEstado);
    document.getElementById('wae-btn-enviar').disabled = !CXC_plantillaEstado;
    document.getElementById('wae-nota').textContent = res.otrosEstab
        ? `Incluye ${res.otrosEstab} documento(s) de otros establecimientos del mismo RUC.`
        : '';

    CXC_previewWAEstado();
    new bootstrap.Modal(document.getElementById('modalWAEstado')).show();
}

function CXC_previewWAEstado() {
    const g = CXC_gruposCliente.get(document.getElementById('wae-gkey').value);
    if (!g) return;
    const res  = CXC_resumenWAEstado(g);
    const tipo = document.getElementById('wae-tipo-vencido').checked ? 'vencido' : 'total';
    const vals = { 1: g.nombre, 2: String(res[tipo].n), 3: '$' + CXC_fmt(res[tipo].valor), 4: CXC_EMPRESA_NOMBRE || 'la empresa' };

    let texto = 'Estimado(a) {{1}}, le recordamos que estamos pendientes del pago de {{2}} factura(s) por un valor de {{3}}. Atentamente, {{4}}. Agradecemos su puntual pago.';
    if (CXC_plantillaEstado) {
        try {
            const comps = JSON.parse(CXC_plantillaEstado.componentes || '[]');
            const body  = comps.find(c => c.type === 'BODY');
            if (body && body.text) texto = body.text;
        } catch {}
    }
    document.getElementById('wae-preview').textContent = texto.replace(/{{(\d+)}}/g, (m, n) => vals[n] ?? m);
}

async function CXC_enviarWAEstado() {
    const g = CXC_gruposCliente.get(document.getElementById('wae-gkey').value);
    if (!g) return;
    const tipo     = document.getElementById('wae-tipo-vencido').checked ? 'vencido' : 'total';
    const telefono = document.getElementById('wae-telefono').value.replace(/[^0-9]/g, '');
    const docs     = CXC_resumenWAEstado(g)[tipo].docs;

    if (!telefono || telefono.length < 7) { CXC_toast('Ingrese un número válido.', 'warning'); return; }
    if (!docs.length) { CXC_toast('No hay documentos para ese resumen.', 'warning'); return; }

    const fd = new FormData();
    fd.append('telefono',   telefono);
    fd.append('tipo',       tipo);
    fd.append('documentos', JSON.stringify(docs));

    Swal.fire({
        title: 'Enviando mensaje…',
        html: 'Contactando a WhatsApp. Por favor espera.',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => Swal.showLoading()
    });

    try {
        const r = await fetch(`${BASE_URL}/${RUTA_MODULO_CXC}/enviarWhatsappEstadoCuentaAjax`, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        });
        const data = await r.json();
        Swal.close();
        if (data.ok) {
            bootstrap.Modal.getInstance(document.getElementById('modalWAEstado')).hide();
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
        if (data.ok) {
            CXC_plantillasWA  = data.plantillas || [];
            CXC_plantillaEstado = data.estado_cuenta || null;
        }
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
        const r = await fetch(`${BASE_URL}/${RUTA_MODULO_CXC}/getClientesAjax?q=${encodeURIComponent(q)}&alcance=${CXC_getAlcance()}`, {
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
   BUSCADOR DE PRODUCTOS (filtro por producto: lista con
   sugerencias y chips, igual que el de clientes)
════════════════════════════════════════════════════ */
let CXC_productosSeleccionados = []; // [{id, codigo, nombre}]

function CXC_initBuscadorProductos() {
    const input = document.getElementById('cxc-search-producto');
    const drop  = document.getElementById('cxc-dropdown-productos');
    if (!input || !drop) return;

    let timer;
    input.addEventListener('input', () => {
        clearTimeout(timer);
        const q = input.value.trim();
        if (q.length < 2) { drop.classList.add('d-none'); return; }
        timer = setTimeout(() => CXC_buscarProductos(q), 280);
    });
    // Enter sin elegir de la lista: filtra por el texto escrito (nombre o código de la línea)
    input.addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); drop.classList.add('d-none'); CXC_recargar(); }
    });
    document.addEventListener('click', e => {
        if (!e.target.closest('#cxc-search-producto') && !e.target.closest('#cxc-dropdown-productos')) {
            drop.classList.add('d-none');
        }
    });
}

async function CXC_buscarProductos(q) {
    const drop = document.getElementById('cxc-dropdown-productos');
    try {
        const r = await fetch(`${BASE_URL}/${RUTA_MODULO_CXC}/getProductosAjax?q=${encodeURIComponent(q)}&alcance=${CXC_getAlcance()}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await r.json();
        const list = data.productos || [];
        if (!list.length) {
            drop.innerHTML = '<div class="list-group-item text-muted small py-1">Sin resultados (Enter filtra por el texto escrito)</div>';
        } else {
            drop.innerHTML = list.map(p => `
                <button type="button" class="list-group-item list-group-item-action py-1 small"
                        onclick="CXC_agregarProducto(${p.id}, '${esc(p.codigo)}', '${esc(p.nombre)}')">
                    <strong>${esc(p.nombre)}</strong> <span class="text-muted">${esc(p.codigo)}</span>
                </button>`).join('');
        }
        drop.classList.remove('d-none');
    } catch {}
}

function CXC_agregarProducto(id, codigo, nombre) {
    const drop = document.getElementById('cxc-dropdown-productos');
    if (!CXC_productosSeleccionados.find(p => p.id === id)) {
        CXC_productosSeleccionados.push({ id, codigo, nombre });
    }
    // Al elegir de la lista, el texto libre se limpia: manda el producto elegido
    document.getElementById('cxc-search-producto').value = '';
    drop.classList.add('d-none');
    CXC_renderChipsProductos();
    CXC_recargar();
}

function CXC_renderChipsProductos() {
    const cont = document.getElementById('cxc-chips-producto');
    if (!cont) return;
    cont.innerHTML = CXC_productosSeleccionados.map(p => `
        <span style="display:inline-flex;align-items:center;gap:4px;background:#e3f2fd;color:#0d47a1;border:1px solid #90caf9;border-radius:20px;padding:2px 10px;font-size:.78rem;font-weight:500;" title="${esc(p.codigo)}">
            ${esc(p.nombre)}
            <button type="button" class="btn-close btn-close-sm ms-1" style="font-size:.55rem;"
                    onclick="CXC_quitarProducto(${p.id})"></button>
        </span>`).join('');
}

function CXC_quitarProducto(id) {
    CXC_productosSeleccionados = CXC_productosSeleccionados.filter(p => p.id !== id);
    CXC_renderChipsProductos();
    CXC_recargar();
}

function CXC_getProductosSeleccionados() {
    return CXC_productosSeleccionados.map(p => p.id).join(',');
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
    const selAlc = document.getElementById('cxc-alcance');
    if (selAlc) selAlc.value = 'ESTABLECIMIENTO';
    const inpProd = document.getElementById('cxc-search-producto');
    if (inpProd) inpProd.value = '';
    CXC_productosSeleccionados = [];
    CXC_renderChipsProductos();

    CXC_clientesSeleccionados = [];
    CXC_renderChipsClientes();

    const buscador = document.getElementById('cxc-buscador');
    if (buscador) buscador.value = '';

    CXC_recargar();
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
        id_producto: CXC_getProductosSeleccionados(),
        producto:    (document.getElementById('cxc-search-producto')?.value || '').trim(),
        alcance:     CXC_getAlcance(),
        // Mismo orden que la pantalla: el Excel y el PDF salen como se ve la tabla
        orden_col:   CXC_getOrden()[0],
        orden_dir:   CXC_getOrden()[1],
        // La exportación sale con la misma estructura que la vista activa: por producto, o por
        // cliente en formato mayor (sección por cliente, subtotal y total general).
        vista:       CXC_vista === 'producto' ? 'PRODUCTO' : (CXC_vista === 'agrupado' ? 'CLIENTE' : ''),
    });
    CMG_descargar(`${BASE_URL}/${RUTA_MODULO_CXC}/exportExcel?${params}`);
}

function CXC_exportarPDF() {
    const params = new URLSearchParams({
        estado:      document.getElementById('cxc-estado')?.value      || 'PENDIENTES',
        tipo_doc:    document.getElementById('cxc-tipo-doc')?.value    || 'TODOS',
        fecha_desde: document.getElementById('cxc-fecha-desde')?.value || '',
        fecha_hasta: document.getElementById('cxc-fecha-hasta')?.value || '',
        id_cliente:  CXC_getClientesSeleccionados(),
        id_vendedor: document.getElementById('cxc-vendedor')?.value    || '',
        id_producto: CXC_getProductosSeleccionados(),
        producto:    (document.getElementById('cxc-search-producto')?.value || '').trim(),
        alcance:     CXC_getAlcance(),
        // Mismo orden que la pantalla: el Excel y el PDF salen como se ve la tabla
        orden_col:   CXC_getOrden()[0],
        orden_dir:   CXC_getOrden()[1],
        // La exportación sale con la misma estructura que la vista activa: por producto, o por
        // cliente en formato mayor (sección por cliente, subtotal y total general).
        vista:       CXC_vista === 'producto' ? 'PRODUCTO' : (CXC_vista === 'agrupado' ? 'CLIENTE' : ''),
    });
    CMG_descargar(`${BASE_URL}/${RUTA_MODULO_CXC}/exportPdf?${params}`);
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
    // Solo YYYY-MM-DD y medianoche LOCAL: 'new Date("2026-01-05")' se interpreta como
    // medianoche UTC y en Ecuador (UTC-5) mostraba el día anterior. Las fechas del listado
    // vienen de columnas DATE, así que siempre caían en ese caso.
    const d = new Date(String(s).substring(0, 10) + 'T00:00:00');
    return isNaN(d.getTime()) ? s : d.toLocaleDateString('es-EC', { day:'2-digit', month:'2-digit', year:'numeric' });
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
    // El detalle lo sirve este módulo (su permiso y el alcance del usuario), no Facturas ni
    // Recibos: sin acceso a esos módulos el panel respondía "HTTP 403". En el consolidado
    // viaja la empresa dueña del documento, así también se ve el de otro establecimiento.
    const emp = (r.es_hermana && parseInt(r.id_empresa)) ? `&id_empresa=${parseInt(r.id_empresa)}` : '';

    window.CMG_abrirPreviewDoc(id, origen, {
        url:         `${BASE_URL}/${RUTA_MODULO_CXC}/detalleDocumentoAjax?origen=${encodeURIComponent(origen)}${emp}`,
        numero:      r.numero_factura || tr.dataset.factura || '',
        fecha:       r.fecha_emision  || '',
        sujetoLabel: 'Cliente',
        sujeto:      r.cliente_nombre || tr.dataset.cliente || '',
        total:       r.total
    });
});

// Cambiar la fecha del documento puede cambiar su número: con numeración por fecha
// de emisión (Empresa → Secuenciales), cada periodo lleva su propio correlativo.
// Se vuelve a pedir la vista previa disparando el 'change' del selector de serie.
(function _cxcRecalcularSecuencialPorFecha() {
    const enganchar = () => {
        const inputFecha = document.getElementById('cobro-fecha');
        const selSerie   = document.getElementById('cobro-punto-emision');
        if (!inputFecha || !selSerie) return;
        inputFecha.addEventListener('change', () => {
            if (selSerie.value) selSerie.dispatchEvent(new Event('change'));
        });
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', enganchar);
    } else {
        enganchar();
    }
})();
