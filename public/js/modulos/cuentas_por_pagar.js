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
let CXP_catalogos     = { puntos: [], conceptos: [], formas: [] };
let CXP_catalogosCargados = false;
let CXP_agrupado      = false;          // vista agrupada por proveedor
// Consolidado por RUC (fase 1, solo lectura): lo confirma el servidor en cada carga.
// Las filas de OTRO establecimiento (r.es_hermana) no se pagan desde aquí.
let CXP_consolidado   = false;

// ¿Ya se consultó el listado al menos una vez? Al entrar al módulo NO se carga nada: el
// usuario elige sus filtros y presiona "Aplicar"; recién ahí se consulta al servidor.
let CXP_cargado       = false;

/* Alcance elegido en el filtro (el select solo existe cuando la empresa activa es la matriz). */
function CXP_getAlcance() {
    return document.getElementById('cxp-alcance')?.value || 'ESTABLECIMIENTO';
}

// Fase 2 del consolidado: pago de un documento de OTRO establecimiento desde la matriz.
// El egreso se registra en los libros de esa empresa, con SUS series, conceptos y formas.
let CXP_pagoEmpresa = 0;             // empresa dueña del documento en el modal de pago (0 = la activa)
let CXP_catUso      = null;          // catálogo en uso en el modal (propio o de la hermana)
const CXP_catalogosPorEmpresa = {};  // caché de catálogos por establecimiento hermano

/* Catálogos (series, conceptos, formas de pago) de otro establecimiento del grupo RUC. */
async function CXP_cargarCatalogosDe(idEmpresa) {
    if (CXP_catalogosPorEmpresa[idEmpresa]) return CXP_catalogosPorEmpresa[idEmpresa];
    const r = await fetch(`${BASE_URL}/${RUTA_MODULO_CXP}/getCatalogosPagoAjax?id_empresa=${idEmpresa}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    const data = await r.json();
    if (!data.ok) throw new Error(data.error || 'No se pudieron cargar los catálogos del establecimiento.');
    return (CXP_catalogosPorEmpresa[idEmpresa] = {
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
    // presiona "Aplicar" (ver CXP_cargado / CXP_recargar).
    CXP_estadoInicial();
    CXP_cargarCatalogos();
    CXP_initBuscadorProveedores();
});

/* ════════════════════════════════════════════════════
   CARGAR DATOS PRINCIPALES
════════════════════════════════════════════════════ */
/* Mensaje de la tabla mientras no se haya aplicado ningún filtro (al entrar al módulo). */
function CXP_estadoInicial() {
    const tbody = document.getElementById('cxp-tbody');
    if (tbody) {
        tbody.innerHTML = `<tr><td colspan="11" class="text-center py-5 text-muted">
            <i class="bi bi-funnel fs-3 d-block mb-2 text-primary opacity-50"></i>
            Elija los filtros y presione <span class="fw-semibold text-primary">Aplicar Filtros</span> para ver las cuentas por pagar.
        </td></tr>`;
    }
    const label = document.getElementById('cxp-count-label');
    if (label) label.textContent = '';
}

/* Recarga el listado SOLO si ya se aplicó una vez. Antes del primer "Aplicar" no se consulta
   nada al servidor: cambiar un filtro no dispara la carga. */
function CXP_recargar() {
    if (CXP_cargado) CXP_cargar();
}

async function CXP_cargar() {
    CXP_cargado = true;
    const tbody = document.getElementById('cxp-tbody');
    tbody.innerHTML = `<tr><td colspan="11" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Cargando…</td></tr>`;

    const params = new URLSearchParams({
        accion:      'generarAjax',
        estado:      document.getElementById('cxp-estado')?.value       || 'PENDIENTES',
        tipo_fuente: document.getElementById('cxp-tipo')?.value         || '',
        fecha_desde: document.getElementById('cxp-fecha-desde')?.value  || '',
        fecha_hasta: document.getElementById('cxp-fecha-hasta')?.value  || '',
        id_proveedor:CXP_getProveedoresSeleccionados(),
        alcance:     CXP_getAlcance(),
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

        // El servidor decide si el consolidado procede (solo desde la matriz)
        CXP_consolidado = !!data.consolidado;
        const estabWrap = document.getElementById('cxp-stat-estab-wrap');
        if (estabWrap) {
            estabWrap.hidden = !CXP_consolidado;
            document.getElementById('cxp-stat-estab').textContent = data.establecimientos || 1;
        }

        CXP_actualizarStats(data.stats || {});
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
   RENDER TABLA
════════════════════════════════════════════════════ */
function CXP_renderTabla(filas) {
    const tbody = document.getElementById('cxp-tbody');
    const label = document.getElementById('cxp-count-label');

    // Las columnas dependen de la vista: la de "Por proveedor" muestra otro detalle.
    CXP_renderCabecera();

    // Todavía sin aplicar: la tabla muestra la invitación a filtrar, no "sin resultados".
    if (!CXP_cargado) { CXP_estadoInicial(); return; }

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
        const esImp   = r.tipo_fuente === 'IMPORTACION';
        const esSaldo = r.tipo_fuente === 'SALDO_INICIAL';
        const pagada  = saldo <= 0.001;
        // Consolidado: documento de OTRO establecimiento del RUC → solo lectura
        const esHermana  = !!r.es_hermana;
        const estabTxt   = `${r.establecimiento || ''}${r.empresa_nombre ? ' - ' + r.empresa_nombre : ''}`;
        const estabBadge = (CXP_consolidado && r.establecimiento)
            ? `<span class="badge ${esHermana ? 'bg-info bg-opacity-10 text-info border-info' : 'bg-primary bg-opacity-10 text-primary border-primary'} border border-opacity-25 me-1 fw-normal" style="font-size:.65rem;" title="${cxpEsc(estabTxt)}">${cxpEsc(r.establecimiento)}</span>`
            : '';

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
                : esImp
                    ? `<span class="badge badge-importacion rounded-pill px-2">Importación</span>`
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
        <tr class="${rowClass}" style="cursor:pointer;" title="Clic para ver el detalle"
            data-id="${r.id}" data-tipo="${cxpEsc(r.tipo_fuente)}" data-hermana="${esHermana ? 1 : 0}"
            data-proveedor="${cxpEsc(r.proveedor_nombre)}" data-doc="${cxpEsc(r.numero_documento)}">

            <!-- Documento -->
            <td class="ps-2" title="${cxpEsc(r.numero_documento)}">
                ${estabBadge}<span class="fw-semibold" style="font-size:.79rem;">${cxpEsc(r.numero_documento)}</span>
            </td>

            <!-- Origen -->
            <td class="text-center" style="white-space:nowrap;">${origenBadge}</td>

            <!-- Proveedor -->
            <td title="${cxpEsc(r.proveedor_nombre)}" style="font-size:.8rem;">
                ${cxpEsc(r.proveedor_nombre)}
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
            <td class="text-center">${CXP_accionesHtml(r)}</td>
        </tr>`;
}

/* Botonera de un documento (pagar, historial). La comparten la fila detallada y la fila del
   detalle por proveedor, para no duplicar las reglas de permisos del consolidado. */
function CXP_accionesHtml(r) {
    const pagada    = (parseFloat(r.saldo) || 0) <= 0.001;
    const esHermana = !!r.es_hermana;
    const idEmpresa = parseInt(r.id_empresa) || 0;
    const estabTxt  = `${r.establecimiento || ''}${r.empresa_nombre ? ' - ' + r.empresa_nombre : ''}`;

    return `
                <div class="d-flex justify-content-center gap-1">
                    ${(!pagada && esHermana && r.puede_operar) ? `
                    <button class="btn btn-primary btn-sm py-0 px-2" style="font-size:.72rem;" title="Registrar pago en el establecimiento ${cxpEsc(estabTxt)}"
                            onclick="CXP_abrirModalPago(${r.id}, '${r.tipo_fuente}', ${idEmpresa})">
                        <i class="bi bi-cash-stack"></i>
                    </button>` : ''}
                    ${(!pagada && esHermana && !r.puede_operar) ? `
                    <button class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:.72rem;" disabled
                            title="Sin permiso para registrar pagos en el establecimiento ${cxpEsc(estabTxt)}">
                        <i class="bi bi-cash-stack"></i>
                    </button>` : ''}
                    ${(!pagada && !esHermana) ? `
                    <button class="btn btn-primary btn-sm py-0 px-2" style="font-size:.72rem;" title="Registrar pago"
                            onclick="CXP_abrirModalPago(${r.id}, '${r.tipo_fuente}')">
                        <i class="bi bi-cash-stack"></i>
                    </button>` : ''}
                    <button class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:.72rem;" title="Ver historial de pagos"
                            onclick="CXP_abrirHistorial(${r.id}, '${r.tipo_fuente}', '${cxpEsc(r.numero_documento)}', ${idEmpresa})">
                        <i class="bi bi-clock-history"></i>
                    </button>
                </div>`;
}

/* ════════════════════════════════════════════════════
   CABECERA DE LA TABLA SEGÚN LA VISTA
   Detallado usa las columnas de siempre. En "Por proveedor"
   el proveedor ya es la cabecera de la sección, así que su
   detalle muestra lo que hace falta dentro del proveedor:
   fecha, documento, total, NC, abonos, retenciones, saldo
   y días vencidos.
════════════════════════════════════════════════════ */
const CXP_COLS_ESTANDAR = `
    <col style="width:165px;"><col style="width:120px;"><col><col style="width:92px;">
    <col style="width:108px;"><col style="width:98px;"><col style="width:88px;">
    <col style="width:82px;"><col style="width:102px;"><col style="width:128px;"><col style="width:80px;">`;
const CXP_TH_ESTANDAR = `
    <tr>
        <th class="ps-2">Documento</th>
        <th class="text-center">Origen</th>
        <th>Proveedor</th>
        <th class="text-center">F.Emisión</th>
        <th class="text-center">F.Vencimiento</th>
        <th class="text-end">Total</th>
        <th class="text-end">Pagado</th>
        <th class="text-end" title="Notas de Crédito / Retenciones">NC/Ret.</th>
        <th class="text-end pe-2 fw-bold">Saldo</th>
        <th class="text-center">Estado</th>
        <th class="text-center">Acciones</th>
    </tr>`;
const CXP_COLS_MAYOR = `
    <col style="width:100px;"><col><col style="width:105px;"><col style="width:95px;">
    <col style="width:100px;"><col style="width:105px;"><col style="width:110px;">
    <col style="width:70px;"><col style="width:80px;">`;
const CXP_TH_MAYOR = `
    <tr>
        <th class="ps-2">Fecha</th>
        <th>N. Documento</th>
        <th class="text-end">Total</th>
        <th class="text-end" title="Notas de crédito del proveedor">NC</th>
        <th class="text-end" title="Pagos realizados">Abonos</th>
        <th class="text-end" title="Retenciones practicadas al proveedor">Retenciones</th>
        <th class="text-end pe-2 fw-bold">Saldo</th>
        <th class="text-center" title="Días vencidos">Días</th>
        <th class="text-center">Acciones</th>
    </tr>`;

function CXP_renderCabecera() {
    const cg = document.getElementById('cxp-colgroup');
    const th = document.getElementById('cxp-thead');
    if (!cg || !th) return;
    cg.innerHTML = CXP_agrupado ? CXP_COLS_MAYOR : CXP_COLS_ESTANDAR;
    th.innerHTML = CXP_agrupado ? CXP_TH_MAYOR   : CXP_TH_ESTANDAR;
}

/* Fila de un documento dentro de la sección de su proveedor (vista "Por proveedor"):
   fecha, documento, total, NC, abonos, retenciones, saldo y días vencidos. */
function CXP_filaMayorHtml(r) {
    const dias   = parseInt(r.dias_vencido) || 0;
    const saldo  = parseFloat(r.saldo) || 0;
    const total  = parseFloat(r.total) || 0;
    const nc     = parseFloat(r.total_nc) || 0;
    const nd     = parseFloat(r.total_nd) || 0;
    const abonos = parseFloat(r.total_pagado) || 0;
    const ret    = parseFloat(r.total_retenido) || 0;
    const esHermana = !!r.es_hermana;

    let rowClass = '';
    if (saldo > 0.001 && dias > 90)      rowClass = 'table-danger';
    else if (saldo > 0.001 && dias > 30) rowClass = 'table-warning';

    // El tipo de documento no tiene columna propia: una factura de compra no lleva marca y
    // los demás sí, que son los casos que conviene distinguir de un vistazo.
    const estabTxt   = `${r.establecimiento || ''}${r.empresa_nombre ? ' - ' + r.empresa_nombre : ''}`;
    const estabBadge = (CXP_consolidado && r.establecimiento)
        ? `<span class="badge ${esHermana ? 'bg-info bg-opacity-10 text-info border-info' : 'bg-primary bg-opacity-10 text-primary border-primary'} border border-opacity-25 me-1 fw-normal" style="font-size:.65rem;" title="${cxpEsc(estabTxt)}">${cxpEsc(r.establecimiento)}</span>`
        : '';
    const tipos = { LIQUIDACION: ['LIQ', 'badge-liquid', 'Liquidación de compra'],
                    IMPORTACION: ['IMP', 'badge-importacion', 'Importación'],
                    SALDO_INICIAL: ['SI', 'badge-proxima', 'Saldo inicial de apertura'] };
    const t = tipos[r.tipo_fuente];
    const tipoBadge = t ? `<span class="badge ${t[1]} me-1 fw-normal" style="font-size:.65rem;" title="${t[2]}">${t[0]}</span>` : '';

    // La nota de débito suma al documento: se avisa junto al total para que
    // total − NC − abonos − retenciones siga cuadrando con el saldo.
    const ndTxt = nd > 0 ? ` <span class="text-muted" style="font-size:.68rem;" title="Nota de débito sumada al documento">+${CXP_fmt(nd)}</span>` : '';

    const diasTxt = dias > 0
        ? `<span class="fw-bold" style="color:#dc3545;" title="Venció el ${CXP_fmtFecha(r.fecha_vencimiento)}">${dias}</span>`
        : `<span class="text-muted" title="Vence el ${CXP_fmtFecha(r.fecha_vencimiento)}">—</span>`;

    return `
        <tr class="${rowClass}" style="cursor:pointer;" title="Clic para ver el detalle" data-id="${r.id}" data-tipo="${r.tipo_fuente}">
            <td class="ps-2" style="font-size:.78rem;white-space:nowrap;">${CXP_fmtFecha(r.fecha_emision)}</td>
            <td class="fw-semibold text-truncate" title="${cxpEsc(r.numero_documento)}" style="font-size:.8rem;">${estabBadge}${tipoBadge}${cxpEsc(r.numero_documento)}</td>
            <td class="text-end" style="font-size:.78rem;white-space:nowrap;">$${CXP_fmt(total)}${ndTxt}</td>
            <td class="text-end" style="font-size:.78rem;white-space:nowrap;">${nc > 0.001 ? '$' + CXP_fmt(nc) : '<span class="text-muted">—</span>'}</td>
            <td class="text-end text-success" style="font-size:.78rem;white-space:nowrap;">${abonos > 0.001 ? '$' + CXP_fmt(abonos) : '<span class="text-muted">—</span>'}</td>
            <td class="text-end" style="font-size:.78rem;white-space:nowrap;">${ret > 0.001 ? '$' + CXP_fmt(ret) : '<span class="text-muted">—</span>'}</td>
            <td class="text-end fw-bold pe-2" style="font-size:.82rem;white-space:nowrap;color:${saldo > 0.001 ? '#dc3545' : '#198754'};">$${CXP_fmt(saldo > 0 ? saldo : 0)}</td>
            <td class="text-center" style="font-size:.78rem;white-space:nowrap;">${diasTxt}</td>
            <td class="text-center">${CXP_accionesHtml(r)}</td>
        </tr>`;
}

/* ════════════════════════════════════════════════════
   VISTA AGRUPADA POR PROVEEDOR — formato "mayor"
   El listado arranca PLEGADO: una línea por proveedor con sus
   totales. Al desplegar una, se lee como el mayor de una cuenta
   contable: los documentos del proveedor y, cerrando la sección,
   su fila de SUBTOTAL. Al final del listado, el TOTAL GENERAL.
   El PDF y el Excel salen siempre con el detalle desplegado.
════════════════════════════════════════════════════ */
/* Secciones DESPLEGADAS de la vista por proveedor: el listado arranca plegado —una línea
   por proveedor con sus totales— y el set recuerda las que el usuario abre. */
const CXP_proveedoresAbiertos = new Set();

/* Agrupa las filas por proveedor. La clave es la identificación BASE, no el texto del RUC:
   el proveedor registrado dos veces —con la cédula y con el RUC, que es esa cédula + '001'—
   cae en un solo grupo con su saldo sumado. Sin identificación se agrupa por nombre. */
function CXP_agruparPorProveedor(filas) {
    const mapa = new Map();
    for (const r of filas) {
        const key = IdentificacionTercero.claveGrupo(r.proveedor_ruc, r.proveedor_nombre || 'Sin proveedor');
        let g = mapa.get(key);
        if (!g) {
            g = { key, nombre: r.proveedor_nombre || 'Sin proveedor', ruc: r.proveedor_ruc || '', items: [],
                  total: 0, nc: 0, abonos: 0, retenciones: 0, saldo: 0 };
            mapa.set(key, g);
        }
        g.items.push(r);
        g.total       += parseFloat(r.total)          || 0;
        g.nc          += parseFloat(r.total_nc)       || 0;
        g.abonos      += parseFloat(r.total_pagado)   || 0;
        g.retenciones += parseFloat(r.total_retenido) || 0;
        g.saldo       += parseFloat(r.saldo)          || 0;
    }
    // Dentro de cada proveedor los documentos van en orden cronológico (como los movimientos
    // de un mayor); entre proveedores manda el saldo, al que más se le debe primero.
    for (const g of mapa.values()) {
        g.items.sort((a, b) =>
            String(a.fecha_emision || '').localeCompare(String(b.fecha_emision || '')) ||
            String(a.numero_documento || '').localeCompare(String(b.numero_documento || '')));
    }
    return [...mapa.values()].sort((a, b) => b.saldo - a.saldo);
}

function CXP_renderAgrupado(filas) {
    const tbody  = document.getElementById('cxp-tbody');
    const label  = document.getElementById('cxp-count-label');
    const grupos = CXP_agruparPorProveedor(filas);

    label.textContent = `${filas.length} docs · ${grupos.length} proveedor${grupos.length !== 1 ? 'es' : ''}`;

    // Columnas de esta vista: Fecha | N. Documento | Total | NC | Abonos | Retenciones |
    // Saldo | Días | Acciones (ver CXP_TH_MAYOR).
    let tTotal = 0, tNc = 0, tAbonos = 0, tRet = 0, tSaldo = 0;
    let html = '';
    for (const g of grupos) {
        tTotal  += g.total;
        tNc     += g.nc;
        tAbonos += g.abonos;
        tRet    += g.retenciones;
        tSaldo  += g.saldo;

        const abierto = CXP_proveedoresAbiertos.has(g.key);
        const chev    = abierto ? 'bi-chevron-down' : 'bi-chevron-right';
        const importes = () => `
            <td class="text-end" style="font-size:.8rem;">$${CXP_fmt(g.total)}</td>
            <td class="text-end" style="font-size:.8rem;">${g.nc > 0.001 ? '$' + CXP_fmt(g.nc) : '<span class="text-muted">—</span>'}</td>
            <td class="text-end text-success" style="font-size:.8rem;">${g.abonos > 0.001 ? '$' + CXP_fmt(g.abonos) : '<span class="text-muted">—</span>'}</td>
            <td class="text-end" style="font-size:.8rem;">${g.retenciones > 0.001 ? '$' + CXP_fmt(g.retenciones) : '<span class="text-muted">—</span>'}</td>
            <td class="text-end pe-2" style="font-size:.82rem;color:${g.saldo > 0.001 ? '#dc3545' : '#198754'};">$${CXP_fmt(g.saldo > 0 ? g.saldo : 0)}</td>`;

        // Cabecera del proveedor: siempre lleva sus totales, así plegada lo resume en una
        // línea. Al desplegarla salen sus documentos y se cierra con la fila de SUBTOTAL.
        html += `
        <tr class="cxp-mayor-grp" data-gkey="${cxpEsc(g.key)}" onclick="CXP_toggleProveedor(this)" style="cursor:pointer;" title="Clic para desplegar o plegar los documentos de este proveedor">
            <td colspan="2" class="ps-2 fw-bold text-truncate" title="${cxpEsc(g.nombre)}${g.ruc ? ' · ' + cxpEsc(g.ruc) : ''}" style="font-size:.82rem;">
                <i class="bi ${chev} text-primary me-1"></i>${cxpEsc(g.nombre)}
                ${g.ruc ? `<span class="text-muted fw-normal ms-1">${cxpEsc(g.ruc)}</span>` : ''}
            </td>
            ${importes()}
            <td class="text-center" style="font-size:.72rem;">
                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 fw-normal" title="${g.items.length} documento${g.items.length !== 1 ? 's' : ''}">${g.items.length}</span>
            </td>
            <td></td>
        </tr>`;

        if (abierto) {
            for (const r of g.items) html += CXP_filaMayorHtml(r);
            html += `
        <tr class="cxp-mayor-sub">
            <td colspan="2" class="text-end" style="font-size:.78rem;">SUBTOTAL ${cxpEsc(g.nombre)}</td>
            ${importes()}
            <td colspan="2"></td>
        </tr>
        <tr class="cxp-mayor-gap"><td colspan="9"></td></tr>`;
        }
    }

    html += `
        <tr class="cxp-mayor-total">
            <td colspan="2" class="text-end" style="font-size:.8rem;">TOTAL GENERAL (${grupos.length} proveedor${grupos.length !== 1 ? 'es' : ''})</td>
            <td class="text-end" style="font-size:.82rem;">$${CXP_fmt(tTotal)}</td>
            <td class="text-end" style="font-size:.82rem;">${tNc > 0.001 ? '$' + CXP_fmt(tNc) : '<span class="text-muted">—</span>'}</td>
            <td class="text-end text-success" style="font-size:.82rem;">${tAbonos > 0.001 ? '$' + CXP_fmt(tAbonos) : '<span class="text-muted">—</span>'}</td>
            <td class="text-end" style="font-size:.82rem;">${tRet > 0.001 ? '$' + CXP_fmt(tRet) : '<span class="text-muted">—</span>'}</td>
            <td class="text-end pe-2" style="font-size:.85rem;color:${tSaldo > 0.001 ? '#dc3545' : '#198754'};">$${CXP_fmt(tSaldo > 0 ? tSaldo : 0)}</td>
            <td colspan="2"></td>
        </tr>`;

    tbody.innerHTML = html;
}

/* Despliega/pliega la sección de un proveedor (el set guarda las abiertas). */
function CXP_toggleProveedor(el) {
    const k = el.getAttribute('data-gkey');
    if (CXP_proveedoresAbiertos.has(k)) CXP_proveedoresAbiertos.delete(k);
    else CXP_proveedoresAbiertos.add(k);
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
async function CXP_abrirModalPago(idDoc, tipoFuente, idEmpresa = 0) {
    // Consolidado (fase 2): si el documento es de OTRO establecimiento del RUC, el pago se
    // registra en ESA empresa, con sus series, conceptos, formas de pago y contabilidad.
    const filaDoc = CXP_datos.find(r => r.id == idDoc && r.tipo_fuente === tipoFuente);
    CXP_pagoEmpresa = (filaDoc && filaDoc.es_hermana) ? (parseInt(idEmpresa) || parseInt(filaDoc.id_empresa) || 0) : 0;
    const empQs = CXP_pagoEmpresa ? `&id_empresa=${CXP_pagoEmpresa}` : '';
    let cat = CXP_catalogos;
    if (CXP_pagoEmpresa) {
        try { cat = await CXP_cargarCatalogosDe(CXP_pagoEmpresa); }
        catch (e) { CXP_toast(e.message || 'No se pudieron cargar los catálogos del establecimiento.', 'danger'); return; }
    }
    CXP_catUso = cat;
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
            const resp = await fetch(`${BASE_URL}/${RUTA_MODULO_CXP}/getDocumentoParaPagoInfoAjax?id_doc=${idDoc}&tipo_fuente=${tipoFuente}${empQs}`);
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
    // NC/ND: solo aplica para COMPRA; en LIQUIDACION/IMPORTACION mostrar 0
    const ncNdVal = parseFloat(d.total_nc || 0) - parseFloat(d.total_nd || 0);
    const lblNcNd = document.getElementById('pago-nc-nd-label');
    if (lblNcNd) lblNcNd.textContent = (d.tipo_fuente === 'LIQUIDACION' || d.tipo_fuente === 'IMPORTACION') ? 'NC/ND' : 'NC - ND';
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
        document.getElementById('pago-fecha').value         = CMG_fechaLocal();
        document.getElementById('pago-observaciones').value = '';
        document.getElementById('pago-secuencial').value    = '';
        document.getElementById('pago-secuencial').classList.remove('border-warning','text-warning');

        // Serie / punto de emisión
        const selPunto = document.getElementById('pago-punto-emision');
        const pts = cat.puntos;
        // El servidor ya manda solo las series activas; si no queda ninguna se dice por qué,
        // en vez de dejar un "— Seleccione —" vacío.
        selPunto.innerHTML = pts.length
            ? '<option value="">— Seleccione —</option>'
              + pts.map(p => `<option value="${p.id_punto}">${p.cod_establecimiento}-${p.codigo_punto}</option>`).join('')
            : '<option value="">Sin series activas</option>';
        if (pts.length === 1) {
            selPunto.selectedIndex = 1;
            CXP_cargarSecuencial(pts[0].id_punto);
        }

        // Concepto de egreso — filtrado y bloqueo por tipo de documento
        const selConc = document.getElementById('pago-concepto');
        const cons = cat.conceptos;

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
        const fps = cat.formas;
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

    // Aviso: el pago va a los libros de otro establecimiento (consolidado, fase 2)
    const avisoEst = document.getElementById('pago-aviso-establecimiento');
    if (avisoEst) {
        avisoEst.hidden = !CXP_pagoEmpresa;
        if (CXP_pagoEmpresa && filaDoc) {
            avisoEst.innerHTML = `<i class="bi bi-diagram-3 me-1"></i>Este pago se registra en el establecimiento `
                + `<strong>${cxpEsc(filaDoc.establecimiento || '')} - ${cxpEsc(filaDoc.empresa_nombre || '')}</strong>: `
                + `el egreso, su secuencial y su contabilidad pertenecen a esa empresa.`;
        }
    }

    new bootstrap.Modal(document.getElementById('modalPago')).show();
}

/* Carga el siguiente secuencial de egreso para el punto seleccionado */
async function CXP_cargarSecuencial(idPunto) {
    const el = document.getElementById('pago-secuencial');
    if (!el) return;
    if (!idPunto) { el.value = ''; return; }
    el.value = '…';
    // La fecha viaja siempre: si Egresos está configurado para numerar por fecha de emisión
    // (Empresa → Secuenciales), el número depende del periodo al que pertenece esa fecha.
    const fecha = document.getElementById('pago-fecha')?.value || '';
    try {
        const r = await fetch(
            `${BASE_URL}/${RUTA_MODULO_CXP}/getSecuencialAjax?id_punto_emision=${idPunto}&fecha=${encodeURIComponent(fecha)}${CXP_pagoEmpresa ? '&id_empresa=' + CXP_pagoEmpresa : ''}`,
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
    const fp   = (CXP_catUso || CXP_catalogos).formas.find(f => f.id == idForma);
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
            elFC.value = CMG_fechaLocal();
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
    if (fecha > CMG_fechaLocal()) { mostrarError('La fecha de emisión no puede ser posterior a la fecha actual.'); return; }

    const divBancoChk = document.getElementById('pago-div-banco');
    if (divBancoChk && !divBancoChk.classList.contains('d-none') && document.getElementById('pago-tipo-op')?.value === 'CHEQUE') {
        const fechaCobroChk = document.getElementById('pago-fecha-cobro')?.value || '';
        const maxFechaCobro = new Date(); maxFechaCobro.setFullYear(maxFechaCobro.getFullYear() + 1);
        if (fechaCobroChk && new Date(fechaCobroChk + 'T00:00:00') > maxFechaCobro) {
            mostrarError('La fecha de cobro del cheque no puede ser mayor a 1 año desde hoy.');
            return;
        }
    }
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
        // Consolidado (fase 2): el servidor registra el egreso en la hermana dueña del documento
        if (CXP_pagoEmpresa) fd.append('id_empresa', CXP_pagoEmpresa);

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
async function CXP_abrirHistorial(idDoc, tipoFuente, nroDoc, idEmpresa = 0) {
    const esSaldo = tipoFuente === 'SALDO_INICIAL';
    document.getElementById('historial-pago-subtitulo').textContent = (esSaldo ? 'Saldo inicial: ' : 'Documento: ') + nroDoc;
    document.getElementById('historial-pagos-tbody').innerHTML =
        '<tr><td colspan="6" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Cargando…</td></tr>';
    document.getElementById('historial-pagos-total').textContent = '0.00';

    new bootstrap.Modal(document.getElementById('modalHistorialPagos')).show();

    try {
        // id_empresa: en el consolidado la fila puede ser de otro establecimiento (solo lectura);
        // el servidor solo lo acepta si es una hermana del grupo RUC consolidable.
        const emp = idEmpresa ? `&id_empresa=${parseInt(idEmpresa)}` : '';
        const url = esSaldo
            ? `${BASE_URL}/${RUTA_MODULO_CXP}/historialPagosSaldoInicialAjax?id_saldo=${idDoc}${emp}`
            : `${BASE_URL}/${RUTA_MODULO_CXP}/historialPagosAjax?id_doc=${idDoc}&tipo_fuente=${tipoFuente}${emp}`;
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
        const r = await fetch(`${BASE_URL}/${RUTA_MODULO_CXP}/getProveedoresAjax?q=${encodeURIComponent(q)}&alcance=${CXP_getAlcance()}`, {
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
    CXP_recargar();
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
    CXP_recargar();
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
    const selAlc = document.getElementById('cxp-alcance');
    if (selAlc) selAlc.value = 'ESTABLECIMIENTO';

    CXP_proveedoresSeleccionados = {};
    CXP_renderChipsProveedores();

    const buscador = document.getElementById('cxp-buscador');
    if (buscador) buscador.value = '';

    CXP_recargar();
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
        alcance:      CXP_getAlcance(),
        // La exportación sale con la misma estructura que la vista activa: por proveedor
        // en formato mayor (sección por proveedor, subtotal y total general).
        vista:        CXP_agrupado ? 'PROVEEDOR' : '',
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
        alcance:      CXP_getAlcance(),
        // La exportación sale con la misma estructura que la vista activa: por proveedor
        // en formato mayor (sección por proveedor, subtotal y total general).
        vista:        CXP_agrupado ? 'PROVEEDOR' : '',
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

/* ════════════════════════════════════════════════════
   PANEL LATERAL: detalle del documento al hacer clic
   en una fila. Delegado en document para sobrevivir a
   los re-render de la tabla (detallada y agrupada).
════════════════════════════════════════════════════ */
document.addEventListener('click', function (e) {
    const tr = e.target.closest('tr[data-tipo]');
    if (!tr) return;
    // Los controles de la fila (botones de pago/historial/etc.) conservan su acción.
    if (e.target.closest('button, a, input, select, label')) return;
    if (typeof window.CMG_abrirPreviewDoc !== 'function') return;

    const id   = tr.dataset.id;
    const tipo = tr.dataset.tipo;
    const r    = CXP_datos.find(x => String(x.id) === String(id) && x.tipo_fuente === tipo) || {};

    window.CMG_abrirPreviewDoc(id, tipo, {
        numero:      r.numero_documento || tr.dataset.doc || '',
        fecha:       r.fecha_emision    || '',
        sujetoLabel: 'Proveedor',
        sujeto:      r.proveedor_nombre || tr.dataset.proveedor || '',
        total:       r.total,
        // Consolidado: el detalle de un documento de otro establecimiento no se puede
        // consultar desde esta empresa; el panel muestra solo el resumen de la fila.
        soloResumen: !!r.es_hermana,
        aviso:       r.es_hermana
            ? `Documento del establecimiento ${r.establecimiento || ''} - ${r.empresa_nombre || ''}. Cambie a esa empresa para ver el detalle completo; el pago sí puede registrarse desde aquí con el botón de la fila.`
            : ''
    });
});

// Cambiar la fecha del documento puede cambiar su número: con numeración por fecha
// de emisión (Empresa → Secuenciales), cada periodo lleva su propio correlativo.
// Se vuelve a pedir la vista previa disparando el 'change' del selector de serie.
(function _cxpRecalcularSecuencialPorFecha() {
    const enganchar = () => {
        const inputFecha = document.getElementById('pago-fecha');
        const selSerie   = document.getElementById('pago-punto-emision');
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
