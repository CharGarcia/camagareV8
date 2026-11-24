/**
 * Aviso de asientos contables pendientes de generar.
 *
 * Se usa en los módulos que muestran contabilidad ya consolidada (Libro Diario / Asientos,
 * Estados Financieros, Balance de Comprobación y Mayores). Al abrir el módulo consulta cuántos
 * documentos operativos están SIN asiento generado y, si hay, pregunta al usuario si desea
 * generarlos ahora o continuar sin generar. NO genera nada por su cuenta: la generación solo
 * ocurre si el usuario la acepta explícitamente.
 *
 * La generación se hace PASO A PASO (un módulo/verificación por llamada, ver
 * SincronizadorAsientosService::ejecutarPaso()) en vez de una sola llamada bloqueante: así se
 * puede mostrar una barra de progreso real y el usuario puede cancelar entre pasos — cada
 * llamada HTTP es corta, así que "cancelar" simplemente deja de pedir el siguiente paso.
 *
 * Endpoints esperados en el controlador del módulo (mismo urlBase):
 *   GET {urlBase}/contarPendientesAjax        → { ok: true, pendientes: <int> }
 *   GET {urlBase}/sincronizarPasoAjax?paso=N  → { ok: true, paso, totalPasos, nombrePaso,
 *                                                 terminado, generados, warnings, detalle,
 *                                                 resumenPorModulo, acciones, puedeConfigurar, info }
 *
 * `acciones` es lo que se muestra: qué configurar, una línea por sección de Configuración Contable,
 * sin cifras (ver SincronizadorAsientosService::registrarAccion()). Cada una enlaza a
 * /modulos/configuracion-contable (URL limpia: tipo y sección viajan en sesión vía entrarAjax) si `puedeConfigurar`.
 *
 * `info` es un canal INFORMATIVO (azul): notas que no son error ni pendiente. Los documentos
 * traídos por la migración solo se cuentan si el sistema anterior nunca los contabilizó (ver
 * SincronizadorAsientosService::construirExclusionMigracion()).
 *
 * El resultado final se puede bajar con «Descargar PDF» (POST {urlBase}/avisoAsientosPdf, ver
 * AvisoAsientosPdfTrait): sirve para configurar con el aviso a la vista, sin volver a generar.
 *
 * Mientras se cuentan los pendientes se muestra un indicador abajo a la derecha, con el tiempo
 * transcurrido y un botón «Omitir» que corta la búsqueda y desbloquea el reporte.
 *   GET {urlBase}/sincronizarAjax             → (respaldo sin barra de progreso, solo si no hay SweetAlert2)
 *
 * Uso:
 *   CMG_verificarAsientosPendientes({ urlBase: '<...>', onGenerado: () => { ... } });
 */
(function () {
    'use strict';

    /** Máximo que se espera a un paso (el servidor le da 120 s de ejecución). */
    const TOPE_PASO_MS = 180000;

    const escapeHtml = (s) => String(s).replace(/[&<>"']/g,
        c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    /**
     * @param {number} actual     Pasos terminados.
     * @param {number} total      Total de pasos.
     * @param {string} nombrePaso Módulo que se está procesando.
     * @param {Object} [extra]    { fraccion (0-1 del paso en curso), docs ("350 de 2.000"), generados }
     */
    function buildProgressHtml(actual, total, nombrePaso, extra) {
        extra = extra || {};
        const avance = Math.min(actual + (extra.fraccion || 0), total);
        const pct = total > 0 ? Math.round((avance / total) * 100) : 0;
        const docs = extra.docs ? ` <span class="text-muted">(${escapeHtml(extra.docs)} documentos)</span>` : '';
        const gen = extra.generados > 0 ? ` · ${extra.generados} asiento(s) generado(s)` : '';
        return `
            <div class="text-start small mb-2 text-truncate">${nombrePaso ? escapeHtml(nombrePaso) : 'Preparando…'}${docs}</div>
            <div class="progress" style="height: 20px;">
                <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar"
                     style="width: ${pct}%;" aria-valuenow="${pct}" aria-valuemin="0" aria-valuemax="100">${pct}%</div>
            </div>
            <div class="text-muted small mt-1">Paso ${Math.min(actual + 1, total)} de ${total}${gen} · <span data-reloj-gen>0 s</span></div>
        `;
    }

    const fmtNum = (n) => Number(n).toLocaleString('es-EC');

    /** "…/modulos/asientos_contables" → "…" (base de la app). */
    function urlApp(urlBase) {
        return String(urlBase).replace(/\/modulos\/[^/]+\/?$/, '');
    }

    /** "…/modulos/asientos_contables" → "…/modulos/configuracion-contable" (misma base de la app). */
    function urlConfiguracion(urlBase) {
        return urlApp(urlBase) + '/modulos/configuracion-contable';
    }

    // ── Asignar cuentas desde el aviso: pestañas y tabla rápida ─────────────────────────────
    // `asignables` (SincronizadorAsientosService::registrarAsignables): una fila por cuenta que falta
    // y se puede asignar aquí. Clases: 'entidad' (proveedor/cliente), 'item' (ítem de compra por
    // nombre), 'dimension' (producto/categoría/marca), 'forma' (forma de cobro/pago) y 'opcion'
    // (concepto de Ingresos/Egresos).
    //
    // El aviso se arma en pestañas: «General» (lo de siempre: generados, qué configurar, notas) y una
    // por tipo de asiento con pendientes (Ventas con Factura, Adquisiciones de Compras…). Cada una
    // muestra una tabla delgada: Nombre · Doc. · una columna por cuenta que falta (normalmente solo
    // el subtotal). La primera fila, «Todos — en la General», resuelve la cuenta para todos; debajo,
    // según la vista elegida («Ver por»), los proveedores/clientes, ítems, productos, categorías o
    // marcas. La vista por defecto es la dimensión que la empresa más usa en sus reglas
    // (`usoDimensiones`). La cuenta se GUARDA AL ELEGIRLA (POST configuracion-contable/asignarCuentaAvisoAjax,
    // que solo completa lo que falta). Luego «Volver a generar».

    /** Filas por tabla en la ventana; lo demás se cuenta en el «y N más». */
    const MAX_FILAS_TABLA = 25;

    /** Orden de las pestañas de tipo de asiento. */
    const ORDEN_PESTANAS = ['Ventas con Factura', 'Recibos de Venta', 'Adquisiciones de Compras', 'Cobros y Pagos', 'Ingresos y Egresos'];

    /** Orden y nombre de las vistas («Ver por») de una pestaña de conceptos. */
    const VISTAS = ['general', 'entidad', 'item_compra', 'producto', 'categoria', 'marca'];

    const esDeConcepto = (a) => a.clase === 'entidad' || a.clase === 'item' || a.clase === 'dimension';

    /** Clave del concepto de una fila (mismo formato que SincronizadorAsientosService::claveConceptoAsignable). */
    const claveConcepto = (a) => `${a.clase}|${a.entidad || ''}|${a.tipo_asiento}|${a.id_asiento_tipo}`;

    /** Pestaña de una fila. */
    function pestanaDe(a) {
        if (a.clase === 'forma') return 'Cobros y Pagos';
        if (a.clase === 'opcion') return 'Ingresos y Egresos';
        return a.seccion || 'Otras cuentas';
    }

    /** Vista («Ver por») a la que pertenece una fila de concepto. */
    function vistaDe(a) {
        if (a.clase === 'entidad') return 'entidad';
        if (a.clase === 'item') return 'item_compra';
        return a.entidad; // producto | categoria | marca
    }

    function etiquetaVista(v, esCliente) {
        return {
            general: 'Solo la General', entidad: esCliente ? 'Por cliente' : 'Por proveedor', item_compra: 'Por ítem',
            producto: 'Por producto', categoria: 'Por categoría', marca: 'Por marca',
        }[v] || v;
    }

    /** Clave de la fila de la tabla (un proveedor, un ítem, una categoría…) dentro de una vista. */
    const claveFila = (a) => `${a.clase}|${a.entidad || ''}|${a.id_entidad || a.referencia_texto || a.id_forma || a.id_opcion || ''}|${a.flujo || a.naturaleza || ''}`;

    /**
     * Arma las pestañas a partir de las filas: por pestaña, sus conceptos (columnas), las vistas que
     * tienen filas y la vista por defecto (la dimensión con más reglas en la empresa; si la empresa no
     * tiene reglas por ninguna dimensión, la General).
     */
    function construirPestanas(asignables, uso) {
        const mapa = new Map();
        asignables.forEach((a, i) => {
            const t = pestanaDe(a);
            if (!mapa.has(t)) {
                mapa.set(t, { id: 'asig-tab-' + mapa.size, titulo: t, deConceptos: esDeConcepto(a), indices: [], conceptos: new Map(), vistas: new Set(), tipo: a.tipo_asiento || '', esCliente: a.entidad === 'cliente' });
            }
            const p = mapa.get(t);
            p.indices.push(i);
            if (esDeConcepto(a)) {
                if (a.clase === 'entidad' && a.entidad === 'cliente') p.esCliente = true;
                p.vistas.add(vistaDe(a));
                if (!p.conceptos.has(a.id_asiento_tipo)) {
                    p.conceptos.set(a.id_asiento_tipo, { id: a.id_asiento_tipo, nombre: a.concepto, tipo_cuenta: a.tipo_cuenta || '', rep: i });
                }
            }
        });
        const pestanas = [...mapa.values()].sort((x, y) => {
            const ox = ORDEN_PESTANAS.indexOf(x.titulo), oy = ORDEN_PESTANAS.indexOf(y.titulo);
            return (ox < 0 ? 99 : ox) - (oy < 0 ? 99 : oy);
        });
        pestanas.forEach(p => {
            p.conceptos = [...p.conceptos.values()];
            p.vistas = VISTAS.filter(v => v === 'general' || p.vistas.has(v));
            const usoTipo = (uso && uso[p.tipo]) || {};
            let mejor = 'general', max = 0;
            p.vistas.forEach(v => { if (v !== 'general' && (usoTipo[v] || 0) > max) { max = usoTipo[v]; mejor = v; } });
            p.vistaPorDefecto = mejor;
        });
        return pestanas;
    }

    /** Cuentas que siguen faltando en una pestaña (para el contador de la pestaña). */
    function pendientesPestana(p, asignables) {
        if (p.deConceptos) {
            return p.conceptos.filter(c => p.indices.some(i => asignables[i].id_asiento_tipo === c.id && !asignables[i].resuelto)).length;
        }
        return p.indices.filter(i => !asignables[i].resuelto).length;
    }

    /** Celda de cuenta: input que guarda al elegir, o la cuenta ya asignada. */
    function celdaCuenta(idx, destino, tipoCuenta, resuelto) {
        if (resuelto) {
            return `<td class="px-1 text-success text-truncate" style="max-width:190px;font-size:.76rem;" title="${escapeHtml(resuelto)}">`
                + `<i class="bi bi-check-circle-fill me-1"></i>${escapeHtml(String(resuelto).replace(/^.*?:\s*/, ''))}</td>`;
        }
        return `<td class="p-0" style="min-width:170px;"><input type="text" class="form-control form-control-sm border-0 rounded-0" autocomplete="off"
            style="padding:0 4px;height:20px;font-size:0.78rem;" placeholder="Buscar cuenta…" data-asig-celda="${idx}"
            data-destino="${destino}" data-tipo-cuenta="${escapeHtml(tipoCuenta || '')}"></td>`;
    }

    /** Contenido (tabla) de una pestaña de conceptos en la vista elegida. */
    function htmlTablaConceptos(p, asignables, omitidos, vista) {
        const ths = p.conceptos.map(c => `<th class="px-1 text-truncate" style="max-width:190px;" title="${escapeHtml(c.nombre)}">${escapeHtml(c.nombre)}</th>`).join('');
        // Fila «Todos — en la General»: una celda por concepto; resuelta si la General ya lo cubre.
        // Documentos detenidos: los de la vista por tercero (cada documento tiene un solo tercero).
        let docsTodos = 0;
        const docsPorConcepto = new Map();
        p.indices.forEach(i => {
            const a = asignables[i];
            if (a.clase !== 'entidad') return;
            docsPorConcepto.set(a.id_asiento_tipo, (docsPorConcepto.get(a.id_asiento_tipo) || 0) + (parseInt(a.documentos, 10) || 0));
        });
        (omitidos || []).forEach(o => {
            const [clase, , tipo, idAt] = String(o.clave_concepto).split('|');
            if (clase === 'entidad' && tipo === p.tipo) {
                docsPorConcepto.set(parseInt(idAt, 10), (docsPorConcepto.get(parseInt(idAt, 10)) || 0) + (parseInt(o.documentos, 10) || 0));
            }
        });
        docsPorConcepto.forEach(n => { docsTodos = Math.max(docsTodos, n); });
        const celdasTodos = p.conceptos.map(c => {
            const delConcepto = p.indices.filter(i => asignables[i].id_asiento_tipo === c.id);
            const enGeneral = delConcepto.map(i => asignables[i].resuelto).find(r => r && r.startsWith('Asignada en la General'));
            const todos = delConcepto.every(i => asignables[i].resuelto);
            return celdaCuenta(c.rep, 'general', c.tipo_cuenta, enGeneral || (todos ? asignables[delConcepto[0]].resuelto : null));
        }).join('');
        let filasHtml = `<tr class="table-light"><td class="px-1 fw-semibold text-nowrap"><i class="bi bi-globe2 me-1 text-primary"></i>Todos — en la General</td>`
            + `<td class="px-1 text-end text-muted">${docsTodos || ''}</td>${celdasTodos}</tr>`;

        let resto = 0;
        if (vista !== 'general') {
            const filas = new Map();
            p.indices.forEach(i => {
                const a = asignables[i];
                if (!esDeConcepto(a) || vistaDe(a) !== vista) return;
                const k = claveFila(a);
                if (!filas.has(k)) filas.set(k, { nombre: a.nombre, docs: 0, celdas: new Map() });
                const f = filas.get(k);
                f.docs = Math.max(f.docs, parseInt(a.documentos, 10) || 0);
                f.celdas.set(a.id_asiento_tipo, i);
            });
            const lista = [...filas.values()].sort((x, y) => String(x.nombre).localeCompare(String(y.nombre), 'es'));
            filasHtml += lista.slice(0, MAX_FILAS_TABLA).map(f => {
                const celdas = p.conceptos.map(c => {
                    const i = f.celdas.get(c.id);
                    if (i === undefined) return `<td class="px-1 text-muted text-center">—</td>`;
                    return celdaCuenta(i, 'entidad', c.tipo_cuenta, asignables[i].resuelto);
                }).join('');
                return `<tr><td class="px-1 text-truncate" style="max-width:260px;" title="${escapeHtml(f.nombre)}">${escapeHtml(f.nombre)}</td>`
                    + `<td class="px-1 text-end text-muted">${f.docs}</td>${celdas}</tr>`;
            }).join('');
            // Lo que no se dibuja: filas sobre el tope + las que el servidor solo contó (máximo por concepto).
            let omit = 0;
            (omitidos || []).forEach(o => {
                const [clase, entidad, tipo] = String(o.clave_concepto).split('|');
                const v = clase === 'entidad' ? 'entidad' : (clase === 'item' ? 'item_compra' : entidad);
                if (tipo === p.tipo && v === vista) omit = Math.max(omit, parseInt(o.filas, 10) || 0);
            });
            resto = Math.max(0, lista.length - MAX_FILAS_TABLA) + omit;
        }
        const pie = resto > 0
            ? `<div class="text-muted mt-1" style="font-size:.72rem;">Y ${resto} más: se resuelven con «Todos — en la General» o con el enlace «Configurar» de la pestaña General.</div>`
            : '';
        return `<div style="overflow-x:auto;"><table class="table table-sm table-bordered mb-0 align-middle" style="font-size:.78rem;">
            <thead class="table-light"><tr><th class="px-1">Nombre</th><th class="px-1 text-end" style="width:48px;">Doc.</th>${ths}</tr></thead>
            <tbody>${filasHtml}</tbody></table></div>${pie}`;
    }

    /** Contenido de una pestaña de formas de cobro/pago o conceptos de ingresos/egresos. */
    function htmlTablaRegistros(p, asignables) {
        const filas = p.indices.slice().sort((x, y) => String(asignables[x].nombre).localeCompare(String(asignables[y].nombre), 'es'));
        const html = filas.slice(0, MAX_FILAS_TABLA).map(i => {
            const a = asignables[i];
            const que = a.clase === 'forma' ? (a.flujo === 'cobro' ? 'Cobro' : 'Pago') : (a.naturaleza === 'egreso' ? 'Egreso' : 'Ingreso');
            const inactiva = a.inactiva ? ` <span class="badge bg-secondary bg-opacity-10 text-secondary">${a.clase === 'forma' ? 'inactiva' : 'inactivo'}</span>` : '';
            return `<tr><td class="px-1 text-truncate" style="max-width:300px;" title="${escapeHtml(a.nombre)}">${escapeHtml(a.nombre)}${inactiva}</td>`
                + `<td class="px-1 text-muted">${que}</td><td class="px-1 text-end text-muted">${a.documentos}</td>`
                + celdaCuenta(i, '', a.tipo_cuenta, a.resuelto) + `</tr>`;
        }).join('');
        const resto = filas.length > MAX_FILAS_TABLA
            ? `<div class="text-muted mt-1" style="font-size:.72rem;">Y ${filas.length - MAX_FILAS_TABLA} más: configúrelos con el enlace «Configurar».</div>` : '';
        return `<table class="table table-sm table-bordered mb-0 align-middle" style="font-size:.78rem;">
            <thead class="table-light"><tr><th class="px-1">Nombre</th><th class="px-1" style="width:70px;">Flujo</th><th class="px-1 text-end" style="width:48px;">Doc.</th><th class="px-1">Cuenta</th></tr></thead>
            <tbody>${html}</tbody></table>${resto}`;
    }

    /** Cuerpo de una pestaña (barra «Ver por» + tabla). */
    function htmlCuerpoPestana(p, opts) {
        if (!p.deConceptos) return htmlTablaRegistros(p, opts.asignables);
        const vista = (opts.vistaPorPestana || {})[p.titulo] || p.vistaPorDefecto;
        const selector = p.vistas.length > 1
            ? `<div class="d-flex align-items-center gap-2 mb-1"><label class="small text-muted mb-0">Ver por</label>
                <select class="form-select form-select-sm" style="width:170px;height:24px;padding-top:0;padding-bottom:0;font-size:.76rem;" data-asig-vista="${escapeHtml(p.titulo)}">`
                + p.vistas.map(v => `<option value="${v}"${v === vista ? ' selected' : ''}>${etiquetaVista(v, p.esCliente)}</option>`).join('')
                + `</select><span class="text-muted" style="font-size:.72rem;">«Todos — en la General» sirve para todos los que no tienen su propia cuenta.</span></div>`
            : '';
        return selector + htmlTablaConceptos(p, opts.asignables, opts.omitidos, vista);
    }

    /**
     * Ventana completa con pestañas: «General» ($htmlGeneral, lo de siempre) + una por tipo de asiento.
     * La pestaña activa y la vista de cada una se guardan en opts (sobreviven a «Descargar PDF»).
     */
    function htmlConPestanas(htmlGeneral, opts) {
        const pestanas = construirPestanas(opts.asignables, opts.usoDimensiones);
        opts.__pestanas = pestanas;
        const activa = opts.pestanaActiva || 'general';
        const nav = [`<li class="nav-item"><button type="button" class="nav-link py-1 px-2${activa === 'general' ? ' active' : ''}" data-asig-tab="general">General</button></li>`]
            .concat(pestanas.map(p => {
                const n = pendientesPestana(p, opts.asignables);
                const badge = n > 0
                    ? ` <span class="badge rounded-pill bg-warning text-dark" data-asig-badge="${p.id}">${n}</span>`
                    : ` <i class="bi bi-check-circle-fill text-success" data-asig-badge="${p.id}"></i>`;
                return `<li class="nav-item"><button type="button" class="nav-link py-1 px-2${activa === p.titulo ? ' active' : ''}" data-asig-tab="${escapeHtml(p.titulo)}">${escapeHtml(p.titulo)}${badge}</button></li>`;
            })).join('');
        const cuerpos = [`<div class="text-start${activa === 'general' ? '' : ' d-none'}" data-asig-panel="general">${htmlGeneral}</div>`]
            .concat(pestanas.map(p => `<div class="text-start${activa === p.titulo ? '' : ' d-none'}" data-asig-panel="${escapeHtml(p.titulo)}">${htmlCuerpoPestana(p, opts)}</div>`))
            .join('');
        return `<div data-asig>
            <ul class="nav nav-tabs small mb-2 flex-nowrap overflow-auto" style="font-size:.8rem;">${nav}</ul>
            ${cuerpos}
            <div class="d-flex justify-content-end align-items-center gap-2 mt-2 pt-2 border-top">
                <span class="text-muted small text-start me-auto" style="font-size:.75rem;" data-asig-estado>Elija las cuentas en las pestañas: se guardan al elegirlas.</span>
                <button type="button" class="btn btn-sm btn-primary text-nowrap" data-asig-regenerar disabled>
                    <i class="bi bi-arrow-repeat me-1"></i>Volver a generar</button>
            </div>
        </div>`;
    }

    /** Lo que se envía al servidor para una fila y un destino. */
    function cuerpoAsignar(a, destino, idCuenta) {
        const p = { clase: a.clase, id_cuenta: String(idCuenta) };
        if (esDeConcepto(a)) {
            p.destino = destino || 'general';
            p.id_asiento_tipo = String(a.id_asiento_tipo);
            if (a.clase === 'entidad') p.id_entidad = String(a.id_entidad);
            else if (a.clase === 'dimension') { p.dimension = a.entidad; p.id_entidad = String(a.id_entidad); }
            else p.referencia_texto = a.referencia_texto;
        } else if (a.clase === 'forma') {
            p.id_forma = String(a.id_forma);
            p.flujo = a.flujo;
        } else if (a.clase === 'opcion') {
            p.id_opcion = String(a.id_opcion);
            p.naturaleza = a.naturaleza;
        }
        return new URLSearchParams(p);
    }

    /**
     * Tras guardar: marca como resueltas EN LOS DATOS la fila y las que quedan resueltas con ella
     * (también las que no se dibujan, para que los contadores y un nuevo dibujado lo reflejen).
     */
    function aplicarResultado(asignables, item, destino, cuenta) {
        if (esDeConcepto(item) && destino === 'general') {
            // La General cubre ese concepto para todos (también para quien tiene cuentas propias:
            // lo que no configura se toma de la General).
            asignables.forEach(it => {
                if (!it.resuelto && esDeConcepto(it) && it.tipo_asiento === item.tipo_asiento && it.id_asiento_tipo === item.id_asiento_tipo) {
                    it.resuelto = `Asignada en la General: ${cuenta}`;
                }
            });
            return;
        }
        if (item.clase === 'entidad') {
            asignables.forEach(it => {
                if (it.clase === 'entidad' && it.entidad === item.entidad && it.id_entidad === item.id_entidad && it.tipo_asiento === item.tipo_asiento) {
                    it.con_cuentas_propias = true;
                }
            });
        }
        item.resuelto = `Asignada: ${cuenta}`;
    }

    /**
     * Conecta la ventana: cambio de pestaña y de vista, buscador de cuentas (lista flotante sobre la
     * ventana, para que la tabla no la recorte) y guardado al elegir. `alRegenerar` cierra y regenera.
     */
    function activarAsignables(contenedor, opts, alRegenerar) {
        const sec = contenedor.querySelector('[data-asig]');
        if (!sec) return () => {};
        const asignables = opts.asignables;
        const urlCuentas = urlApp(opts.urlBase) + '/modulos/plan-cuentas/searchAjaxCuentas';
        const urlAsignar = urlConfiguracion(opts.urlBase) + '/asignarCuentaAvisoAjax';
        const btnRegenerar = sec.querySelector('[data-asig-regenerar]');
        const estado = sec.querySelector('[data-asig-estado]');
        let temporizador = null;
        let consulta = 0;
        let inputActivo = null;
        let marcada = -1;

        // Lista flotante única (position:fixed en el body, sobre la ventana de SweetAlert).
        const lista = document.createElement('div');
        lista.className = 'list-group shadow';
        lista.style.cssText = 'position:fixed;z-index:2100;display:none;max-height:220px;overflow:auto;min-width:320px;font-size:.78rem;';
        document.body.appendChild(lista);

        function cerrarLista() { lista.style.display = 'none'; lista.innerHTML = ''; marcada = -1; }
        function ubicarLista(input) {
            const r = input.getBoundingClientRect();
            lista.style.left = `${Math.max(8, Math.min(r.left, window.innerWidth - 340))}px`;
            lista.style.top = `${r.bottom + 2}px`;
            lista.style.width = `${Math.max(320, r.width)}px`;
        }

        function marcarHayCambios() {
            opts.huboAsignaciones = true;
            btnRegenerar.disabled = false;
            estado.textContent = 'Cuentas guardadas. Pulse «Volver a generar» para crear los asientos.';
        }
        if (opts.huboAsignaciones) marcarHayCambios();

        /** Repinta el cuerpo de una pestaña y los contadores de todas. */
        function repintar() {
            (opts.__pestanas || []).forEach(p => {
                const panel = sec.querySelector(`[data-asig-panel="${CSS.escape(p.titulo)}"]`);
                if (panel) panel.innerHTML = htmlCuerpoPestana(p, opts);
                const badge = sec.querySelector(`[data-asig-badge="${p.id}"]`);
                if (badge) {
                    const n = pendientesPestana(p, asignables);
                    badge.outerHTML = n > 0
                        ? `<span class="badge rounded-pill bg-warning text-dark" data-asig-badge="${p.id}">${n}</span>`
                        : `<i class="bi bi-check-circle-fill text-success" data-asig-badge="${p.id}"></i>`;
                }
            });
        }

        async function guardar(input, cuenta) {
            if (input.dataset.enCurso === '1') return; // doble clic
            const item = asignables[parseInt(input.getAttribute('data-asig-celda'), 10)];
            const destino = input.getAttribute('data-destino');
            input.dataset.enCurso = '1';
            input.disabled = true;
            input.value = `Guardando ${cuenta.codigo}…`;
            try {
                const res = await fetch(urlAsignar, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: cuerpoAsignar(item, destino, cuenta.id).toString(),
                }).then(r => r.json());
                if (!res || !res.ok) {
                    estado.innerHTML = `<span class="text-danger"><i class="bi bi-x-circle me-1"></i>${escapeHtml((res && res.error) || 'No se pudo asignar la cuenta.')}</span>`;
                    input.disabled = false;
                    input.value = '';
                    input.dataset.enCurso = '';
                    return;
                }
                aplicarResultado(asignables, item, destino, res.cuenta || `${cuenta.codigo} - ${cuenta.nombre}`);
                marcarHayCambios();
                repintar();
            } catch (e) {
                estado.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>Error de conexión: no se asignó la cuenta.</span>';
                input.disabled = false;
                input.value = '';
                input.dataset.enCurso = '';
            }
        }

        function buscar(input) {
            const texto = input.value.trim();
            if (texto.length < 2) { cerrarLista(); return; }
            clearTimeout(temporizador);
            temporizador = setTimeout(async () => {
                const mia = ++consulta;
                try {
                    const url = `${urlCuentas}?q=${encodeURIComponent(texto)}&tipo=${encodeURIComponent(input.dataset.tipoCuenta || '')}`;
                    const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.json());
                    if (mia !== consulta || inputActivo !== input) return; // llegó otra respuesta o cambió de celda
                    lista.innerHTML = '';
                    marcada = -1;
                    if (!res || !res.ok) {
                        lista.innerHTML = `<div class="list-group-item py-1 px-2 text-danger">${escapeHtml((res && res.error) || 'No se pudieron buscar las cuentas.')}</div>`;
                    } else if (!Array.isArray(res.data) || !res.data.length) {
                        lista.innerHTML = '<div class="list-group-item py-1 px-2 text-muted">Sin cuentas de movimiento que coincidan.</div>';
                    } else {
                        res.data.forEach(c => {
                            const b = document.createElement('button');
                            b.type = 'button';
                            b.className = 'list-group-item list-group-item-action py-1 px-2 text-start';
                            b.textContent = `${c.codigo} - ${c.nombre}`;
                            // mousedown: se elige antes de que el input pierda el foco.
                            b.addEventListener('mousedown', (ev) => { ev.preventDefault(); cerrarLista(); guardar(input, c); });
                            b._cuenta = c;
                            lista.appendChild(b);
                        });
                    }
                    ubicarLista(input);
                    lista.style.display = 'block';
                } catch (e) {
                    cerrarLista();
                }
            }, 300);
        }

        sec.addEventListener('input', (e) => {
            const input = e.target.closest('[data-asig-celda]');
            if (!input) return;
            inputActivo = input;
            buscar(input);
        });
        sec.addEventListener('keydown', (e) => {
            const input = e.target.closest('[data-asig-celda]');
            if (!input) return;
            const botones = [...lista.querySelectorAll('button')];
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                if (!botones.length || lista.style.display === 'none') return;
                e.preventDefault();
                marcada = (marcada + (e.key === 'ArrowDown' ? 1 : -1) + botones.length) % botones.length;
                botones.forEach((b, k) => b.classList.toggle('active', k === marcada));
                botones[marcada].scrollIntoView({ block: 'nearest' });
                return;
            }
            if (e.key === 'Enter') {
                // Enter no confirma la ventana: elige la cuenta marcada (o la única que hay).
                e.preventDefault();
                e.stopPropagation();
                const b = botones[marcada] || (botones.length === 1 ? botones[0] : null);
                if (b && b._cuenta) { cerrarLista(); guardar(input, b._cuenta); }
                return;
            }
            if (e.key === 'Escape' && lista.style.display !== 'none') { e.stopPropagation(); cerrarLista(); }
        });
        sec.addEventListener('focusout', (e) => {
            if (e.target.closest('[data-asig-celda]')) setTimeout(() => { if (document.activeElement !== inputActivo) cerrarLista(); }, 150);
        });
        sec.addEventListener('change', (e) => {
            const sel = e.target.closest('[data-asig-vista]');
            if (!sel) return;
            opts.vistaPorPestana = opts.vistaPorPestana || {};
            opts.vistaPorPestana[sel.getAttribute('data-asig-vista')] = sel.value;
            cerrarLista();
            repintar();
        });
        sec.addEventListener('click', (e) => {
            const tab = e.target.closest('[data-asig-tab]');
            if (tab) {
                const t = tab.getAttribute('data-asig-tab');
                opts.pestanaActiva = t;
                sec.querySelectorAll('[data-asig-tab]').forEach(b => b.classList.toggle('active', b === tab));
                sec.querySelectorAll('[data-asig-panel]').forEach(pn => pn.classList.toggle('d-none', pn.getAttribute('data-asig-panel') !== t));
                cerrarLista();
                return;
            }
            if (e.target.closest('[data-asig-regenerar]')) { cerrarLista(); alRegenerar(); }
        });
        // La lista flotante no sigue al input si la ventana se desplaza: se cierra.
        contenedor.querySelector('.swal2-html-container')?.addEventListener('scroll', cerrarLista, true);

        return () => { clearTimeout(temporizador); lista.remove(); };
    }

    /**
     * Enlace «Configurar»: las URLs de navegación van limpias, sin parámetros. Se deja en sesión qué
     * tipo de asiento y sección abrir (ConfiguracionContableController::entrarAjax()) y se navega a
     * `modulos/configuracion-contable`. La pestaña se abre ANTES del fetch, dentro del clic, para que
     * el navegador no la bloquee como ventana emergente; si igual la bloquea, se navega aquí mismo.
     */
    document.addEventListener('click', function (e) {
        const a = e.target.closest ? e.target.closest('a[data-asientos-cfg-tipo]') : null;
        if (!a) return;
        e.preventDefault();

        const url = a.getAttribute('href');
        const destino = window.open('', '_blank');
        if (destino) destino.opener = null;
        const cuerpo = new URLSearchParams({
            tipo: a.getAttribute('data-asientos-cfg-tipo'),
            seccion: a.getAttribute('data-asientos-cfg-seccion') || 'general',
        });
        fetch(`${url}/entrarAjax`, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: cuerpo.toString(),
        })
            .catch(() => null) // sin la sección igual se abre la pantalla
            .then(() => {
                if (destino) destino.location.href = url;
                else window.location.href = url;
            });
    });

    /**
     * Arma y muestra el resultado final (generados + acciones + notas), acumulados de
     * todos los pasos. Las acciones dicen QUÉ configurar (una línea por sección, sin cifras) y,
     * si el usuario puede entrar a Configuración Contable, enlazan directo a esa sección.
     */
    function mostrarResultado(opts) {
        // Los `warnings` del backend (conceptos/formas de pago sin cuenta, etc.) ya NO se muestran
        // como "Otros avisos": solo se informa lo generado y lo que queda por configurar.
        // El detalle por documento ya no se muestra: queda en el log del servidor para soporte.
        const { generados, interrumpido, onGenerado } = opts;
        const acciones = Array.isArray(opts.acciones) ? opts.acciones : [];
        const info = Array.isArray(opts.info) ? opts.info : [];
        const hayPendientes = acciones.length > 0 || (parseInt(opts.pendientes, 10) || 0) > 0;

        let html = '';
        if (interrumpido) {
            html += `<div class="mb-2"><i class="bi bi-slash-circle text-secondary me-1"></i> Proceso interrumpido por el usuario: se muestra lo generado hasta el momento.</div>`;
        }
        if (generados > 0) {
            html += `<div class="mb-2"><i class="bi bi-check-circle-fill text-success me-1"></i> Se generaron <strong>${generados}</strong> asiento(s) contable(s).</div>`;
        }
        // Cuentas que se pueden asignar aquí mismo: van en pestañas por tipo de asiento (ver htmlConPestanas).
        const asignables = (opts.puedeAsignar && Array.isArray(opts.asignables) && window.Swal) ? opts.asignables : [];
        if (acciones.length) {
            const items = acciones.map(a => {
                let enlace = '';
                if (opts.urlConfig && a.tipo) {
                    // URL limpia: el tipo y la sección viajan en sesión (ver abrirConfiguracion()).
                    enlace = ` <a href="${escapeHtml(opts.urlConfig)}" target="_blank" rel="noopener" class="text-nowrap"`
                        + ` data-asientos-cfg-tipo="${escapeHtml(a.tipo)}" data-asientos-cfg-seccion="${escapeHtml(a.seccion || 'general')}">`
                        + `<i class="bi bi-box-arrow-up-right me-1"></i>Configurar</a>`;
                }
                // Qué documentos, de quién y qué cuentas faltan (ver SincronizadorAsientosService::detalleDeLote).
                const det = Array.isArray(a.detalles) ? a.detalles : [];
                const maxDet = 8;
                const sub = det.length
                    ? `<ul class="mb-1 mt-1 text-muted" style="font-size:.78rem;">`
                        + det.slice(0, maxDet).map(d => `<li>${escapeHtml(d)}</li>`).join('')
                        + (det.length > maxDet ? `<li>y ${det.length - maxDet} grupo(s) más</li>` : '')
                        + `</ul>`
                    : '';
                return `<li class="mb-1">${escapeHtml(a.texto)}.${enlace}${sub}</li>`;
            }).join('');
            html += `<div class="text-start small mb-2"><i class="bi bi-exclamation-triangle text-warning me-1"></i> `
                + `<strong>Para generar los asientos que faltan:</strong>`
                + `<ul class="mb-0 mt-1">${items}</ul></div>`;
        }
        const pendientes = parseInt(opts.pendientes, 10) || 0;
        if (!html && pendientes === 0) html = 'No quedaron asientos por generar.';
        if (info.length) {
            // Informativo: no cambia el ícono ni el título (no es un pendiente ni un error).
            html += `<div class="text-start small alert alert-info py-2 px-3 mt-3 mb-0"><strong><i class="bi bi-info-circle-fill me-1"></i>Información:</strong>`
                + `<ul class="mb-0 mt-1 small">${info.map(i => `<li class="mb-1">${escapeHtml(i)}</li>`).join('')}</ul></div>`;
        }
        // Cierre del aviso: cuántos documentos siguen sin asiento (el único número que se muestra).
        // Si se interrumpió, solo cuenta los módulos que alcanzaron a revisarse.
        if (pendientes > 0) {
            html += `<div class="text-start small fw-semibold mt-3 pt-2 border-top">`
                + `<i class="bi bi-hourglass-split text-warning me-1"></i>`
                + `Quedan pendientes <strong>${pendientes}</strong> asiento(s) por generar`
                + (interrumpido ? ' en los módulos revisados' : '') + `.</div>`;
        }

        // Con cuentas para asignar, lo anterior pasa a la pestaña «General» y cada tipo de asiento tiene la suya.
        if (asignables.length) html = htmlConPestanas(html, opts);

        if (window.Swal) {
            // «Descargar PDF»: el aviso no se guarda en el servidor, así que el PDF es la forma de
            // tenerlo a mano mientras se configura, sin volver a generar para leerlo otra vez.
            const conPdf = (hayPendientes || info.length > 0) && !!opts.urlBase
                && typeof window.CMG_pdfDocumento === 'function';
            let regenerar = false;
            let limpiar = null; // quita la lista flotante de cuentas al cerrar
            Swal.fire({
                icon: interrumpido ? 'info' : (hayPendientes ? 'warning' : 'success'),
                title: interrumpido ? 'Generación interrumpida' : (hayPendientes ? 'Generación completada con avisos' : 'Asientos generados'),
                html: html,
                width: asignables.length ? 920 : ((acciones.length || info.length) ? 640 : undefined),
                confirmButtonText: 'Aceptar',
                showDenyButton: conPdf,
                denyButtonText: '<i class="bi bi-file-earmark-pdf me-1"></i> Descargar PDF',
                denyButtonColor: '#dc3545',
                allowOutsideClick: !conPdf && !asignables.length,
                didOpen: (popup) => {
                    if (asignables.length) {
                        limpiar = activarAsignables(popup, opts, () => { regenerar = true; Swal.close(); });
                    }
                },
                willClose: () => { if (limpiar) limpiar(); },
            }).then((res) => {
                if (res.isDenied) { descargarPdf(opts); return; }
                if (regenerar) {
                    // Sigue bloqueado (no se llama terminar()): la nueva generación lo libera al final.
                    generar(opts.urlBase, onGenerado);
                    return;
                }
                terminar();
                if (typeof onGenerado === 'function') onGenerado();
            });
        } else {
            terminar();
            if (typeof onGenerado === 'function') onGenerado();
        }
    }

    /**
     * PDF del aviso (POST {urlBase}/avisoAsientosPdf con lo acumulado, ver AvisoAsientosPdfTrait).
     * Lleva TODOS los grupos de detalle, no solo los primeros que caben en la ventana. Al cerrar
     * la ventana del PDF el aviso vuelve a mostrarse: se puede descargar de nuevo o aceptar.
     */
    function descargarPdf(opts) {
        const cuerpo = {
            generados: opts.generados || 0,
            pendientes: parseInt(opts.pendientes, 10) || 0,
            interrumpido: !!opts.interrumpido,
            acciones: (Array.isArray(opts.acciones) ? opts.acciones : []).map(a => ({
                texto: a.texto,
                detalles: Array.isArray(a.detalles) ? a.detalles : [],
            })),
            info: Array.isArray(opts.info) ? opts.info : [],
        };
        Promise.resolve(window.CMG_pdfDocumento(`${opts.urlBase}/avisoAsientosPdf`, {
            nombre: 'PDF', archivo: 'Asientos_pendientes.pdf', cuerpo,
        })).catch(() => null).then(() => {
            const espera = setInterval(() => {
                if (Swal.isVisible()) return; // sigue abierta «PDF listo» (o el error)
                clearInterval(espera);
                mostrarResultado(opts);
            }, 300);
        });
    }

    // ── Bloqueo de la generación de balances mientras el aviso no esté resuelto ────────────
    // Desde que se empieza a consultar si hay asientos pendientes hasta que el usuario responde
    // el aviso (o termina la generación), los botones indicados en `opts.bloquear` quedan
    // deshabilitados y CMG_asientosPendientesBloqueado() devuelve true, para que los reportes
    // (Balance de Comprobación, Estados Financieros, Mayores) no se generen con asientos faltantes.
    let verificando = false;
    let elementosBloqueados = [];

    function bloquear(selectores) {
        verificando = true;
        elementosBloqueados = [];
        (selectores || []).forEach(sel => {
            document.querySelectorAll(sel).forEach(el => {
                elementosBloqueados.push({ el, disabled: el.disabled, title: el.getAttribute('title') });
                el.disabled = true;
                el.setAttribute('title', 'Verificando asientos contables pendientes…');
            });
        });
    }

    function terminar() {
        if (!verificando) return;
        verificando = false;
        elementosBloqueados.forEach(({ el, disabled, title }) => {
            el.disabled = disabled;
            if (title === null) el.removeAttribute('title'); else el.setAttribute('title', title);
        });
        elementosBloqueados = [];
    }

    // ── Indicador "Buscando asientos pendientes…" ──────────────────────────────────────────
    // El conteo puede tardar en empresas con muchos documentos; mientras tanto los botones del
    // reporte están bloqueados. El indicador dice qué pasa, cuánto lleva y permite omitir la
    // búsqueda (equivale a "Continuar sin generar").
    let indicador = null;
    let relojIndicador = null;

    function mostrarIndicador(onOmitir) {
        ocultarIndicador();
        indicador = document.createElement('div');
        indicador.className = 'card shadow border-primary border-opacity-25 position-fixed';
        indicador.setAttribute('role', 'status');
        indicador.style.cssText = 'right:16px;bottom:16px;z-index:1050;max-width:calc(100vw - 32px);width:340px;';
        indicador.innerHTML = `
            <div class="card-body py-2 px-3 d-flex align-items-center gap-2 small">
                <span class="spinner-border spinner-border-sm text-primary flex-shrink-0" aria-hidden="true"></span>
                <div class="flex-grow-1">
                    <div class="fw-semibold">Buscando asientos contables pendientes…</div>
                    <div class="text-muted" data-reloj>Revisando documentos sin asiento.</div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary flex-shrink-0" data-omitir
                        title="Continuar sin esperar la búsqueda">Omitir</button>
            </div>`;
        indicador.querySelector('[data-omitir]').addEventListener('click', onOmitir);
        document.body.appendChild(indicador);

        const inicio = Date.now();
        const reloj = indicador.querySelector('[data-reloj]');
        relojIndicador = setInterval(() => {
            const s = Math.floor((Date.now() - inicio) / 1000);
            if (s >= 3) reloj.textContent = `Revisando documentos sin asiento (${s} s). Con muchos documentos puede tardar.`;
        }, 1000);
    }

    function ocultarIndicador() {
        if (relojIndicador) { clearInterval(relojIndicador); relojIndicador = null; }
        if (indicador) { indicador.remove(); indicador = null; }
    }

    /** true (y avisa) si todavía no se resolvió el aviso de asientos pendientes. */
    window.CMG_asientosPendientesBloqueado = function () {
        if (!verificando) return false;
        const msg = 'Espere a que termine la verificación de asientos contables pendientes antes de generar el reporte.';
        if (window.Swal && !Swal.isVisible()) {
            Swal.fire({ icon: 'info', title: 'Verificando asientos', text: msg, timer: 2500, showConfirmButton: false });
        } else if (!window.Swal) {
            window.alert(msg);
        }
        return true;
    };

    /** Respaldo sin barra de progreso (SweetAlert2 no disponible): una sola llamada bloqueante, como antes. */
    function generarSinProgreso(urlBase, onGenerado) {
        return fetch(`${urlBase}/sincronizarAjax`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(json => {
                terminar();
                if (!json || json.success === false) return;
                if (typeof onGenerado === 'function') onGenerado();
            })
            .catch(() => { terminar(); /* silencioso: no hay Swal para avisar el error */ });
    }

    function generar(urlBase, onGenerado) {
        if (!window.Swal) {
            return generarSinProgreso(urlBase, onGenerado);
        }

        let cancelado = false;
        Swal.fire({
            title: 'Generando asientos…',
            html: buildProgressHtml(0, 1, 'Preparando…'),
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            showCancelButton: true,
            cancelButtonText: '<i class="bi bi-x-circle me-1"></i> Cancelar',
            cancelButtonColor: '#6c757d',
        }).then((result) => {
            if (result.dismiss === Swal.DismissReason.cancel) cancelado = true;
        });

        const acumInfo = [];
        const acumResumen = {};
        const acumAcciones = new Map();
        // Proveedor + concepto que se puede asignar desde el aviso; los documentos se suman entre lotes.
        const acumAsignables = new Map();
        // Lo que el servidor solo contó por su tope por concepto: se suma para el total del resumen.
        const acumOmitidos = new Map();
        // Reglas por dimensión de cada tipo de asiento: decide la vista por defecto de cada pestaña.
        const usoDimensiones = {};
        let puedeAsignar = false;
        let puedeConfigurar = false;
        // Solo lo que de verdad falta generar: módulos encendidos, sin los documentos que no llevan
        // asiento (sin valor, o que dependen de un módulo apagado). Ver getPendientes() en el servidor.
        let pendientesReales = 0;
        let generados = 0;

        // Reloj de la ventana: aunque un lote tarde, se ve que el proceso sigue vivo.
        const inicioGen = Date.now();
        const relojGen = setInterval(() => {
            const el = document.querySelector('[data-reloj-gen]');
            if (!el) return;
            const s = Math.floor((Date.now() - inicioGen) / 1000);
            el.textContent = s < 60 ? `${s} s` : `${Math.floor(s / 60)} min ${s % 60} s`;
        }, 1000);

        (async () => {
            let n = 0;
            let totalPasos = 1;
            let interrumpido = false;
            let errorMsg = null;
            // Lotes dentro de un módulo grande: el servidor vuelve cada ~15 s con 'continuar' y
            // se repite el mismo paso desde 'desdeId'. totalModulo = pendientes al empezar el paso.
            let desde = 0;
            let totalModulo = 0;
            let hechosModulo = 0;

            while (n < totalPasos) {
                if (cancelado) { interrumpido = true; break; }

                let json;
                // Tope por paso: si el servidor queda esperando (p. ej. un bloqueo en la base), la
                // ventana no se queda para siempre en "Preparando…" sin decir nada.
                const ctrl = new AbortController();
                const tope = setTimeout(() => ctrl.abort(), TOPE_PASO_MS);
                try {
                    const res = await fetch(`${urlBase}/sincronizarPasoAjax?paso=${n}&desde=${desde}`, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        signal: ctrl.signal,
                    });
                    json = await res.json();
                } catch (e) {
                    errorMsg = e.name === 'AbortError'
                        ? 'La generación de asientos no respondió a tiempo. Los asientos generados hasta ahora se conservan; vuelva a intentarlo en unos minutos.'
                        : 'No se pudo completar la generación de asientos (error de red).';
                    break;
                } finally {
                    clearTimeout(tope);
                }
                if (!json || json.ok === false) {
                    errorMsg = (json && json.error) ? json.error : 'No se pudieron generar los asientos.';
                    break;
                }

                totalPasos = json.totalPasos || totalPasos;
                generados += json.generados || 0;
                if (Array.isArray(json.info)) json.info.forEach(i => { if (!acumInfo.includes(i)) acumInfo.push(i); });
                if (json.resumenPorModulo && typeof json.resumenPorModulo === 'object') {
                    Object.entries(json.resumenPorModulo).forEach(([mod, cnt]) => {
                        acumResumen[mod] = (acumResumen[mod] || 0) + (parseInt(cnt, 10) || 0);
                    });
                }
                if (Array.isArray(json.acciones)) {
                    json.acciones.forEach(a => {
                        if (!a || !a.clave) return;
                        const previa = acumAcciones.get(a.clave);
                        if (!previa) { acumAcciones.set(a.clave, { ...a, detalles: Array.isArray(a.detalles) ? [...a.detalles] : [] }); return; }
                        // Los detalles de cada paso se suman (sin repetir): no se pierden al generalizar el texto.
                        (Array.isArray(a.detalles) ? a.detalles : []).forEach(d => {
                            if (!previa.detalles.includes(d)) previa.detalles.push(d);
                        });
                        // Una sola línea por sección: faltas distintas en la misma sección → texto genérico.
                        if (previa.texto !== a.texto) previa.texto = previa.textoGenerico || a.textoGenerico || previa.texto;
                        if (!a.dependeDe) previa.dependeDe = null; // también falta de forma directa
                    });
                }
                if (json.puedeConfigurar) puedeConfigurar = true;
                if (json.puedeAsignar) puedeAsignar = true;
                if (json.usoDimensiones && typeof json.usoDimensiones === 'object') {
                    Object.entries(json.usoDimensiones).forEach(([tipo, usos]) => { usoDimensiones[tipo] = { ...(usoDimensiones[tipo] || {}), ...(usos || {}) }; });
                }
                if (Array.isArray(json.asignablesOmitidos)) {
                    json.asignablesOmitidos.forEach(o => {
                        if (!o || !o.clave_concepto) return;
                        const p = acumOmitidos.get(o.clave_concepto) || { clave_concepto: o.clave_concepto, filas: 0, documentos: 0 };
                        p.filas += parseInt(o.filas, 10) || 0;
                        p.documentos += parseInt(o.documentos, 10) || 0;
                        acumOmitidos.set(o.clave_concepto, p);
                    });
                }
                if (Array.isArray(json.asignables)) {
                    json.asignables.forEach(a => {
                        if (!a || !a.clave) return;
                        const previa = acumAsignables.get(a.clave);
                        if (previa) previa.documentos += parseInt(a.documentos, 10) || 0;
                        else acumAsignables.set(a.clave, { ...a, documentos: parseInt(a.documentos, 10) || 0 });
                    });
                }
                pendientesReales += parseInt(json.pendientes, 10) || 0;

                let extra = { generados };
                let nombreMostrar = json.nombreSiguiente || '';
                if (json.continuar) {
                    // Mismo módulo, siguiente lote.
                    if (desde === 0) totalModulo = parseInt(json.loteTotal, 10) || 0;
                    hechosModulo += parseInt(json.loteProcesados, 10) || 0;
                    desde = parseInt(json.desdeId, 10) || 0;
                    extra.fraccion = totalModulo > 0 ? hechosModulo / totalModulo : 0;
                    extra.docs = `${fmtNum(hechosModulo)} de ${fmtNum(totalModulo)}`;
                    nombreMostrar = json.nombrePaso || nombreMostrar;
                } else {
                    n++;
                    desde = 0;
                    totalModulo = 0;
                    hechosModulo = 0;
                }
                if (!cancelado) {
                    Swal.update({ html: buildProgressHtml(n, totalPasos, nombreMostrar, extra) });
                }
            }

            clearInterval(relojGen);
            Swal.close();

            if (errorMsg) {
                terminar();
                Swal.fire({ icon: 'error', title: 'Error', text: errorMsg });
                return;
            }

            // Mismo criterio que SincronizadorAsientosService::getResumenMensaje(): una acción que
            // depende de otro módulo que también quedó pendiente (ej. egresos que pagan compras sin
            // asiento) no se pide aparte — se resuelve al corregir ese módulo, y se dice como nota.
            const acciones = [];
            acumAcciones.forEach(a => {
                if (a.dependeDe && acumResumen[a.dependeDe]) {
                    const nota = `Los ingresos/egresos que cobran o pagan ${a.dependeDe} se contabilizarán solos cuando esos documentos tengan su asiento.`;
                    if (!acumInfo.includes(nota)) acumInfo.push(nota);
                    return;
                }
                acciones.push(a);
            });

            // Por concepto y luego por nombre (cada tabla vuelve a ordenar sus filas por nombre); así las filas que
            // se resuelven juntas con «En la General» quedan seguidas.
            const asignables = [...acumAsignables.values()].sort((a, b) =>
                String(a.concepto).localeCompare(String(b.concepto), 'es')
                || String(a.nombre).localeCompare(String(b.nombre), 'es'));

            mostrarResultado({
                urlBase,
                asignables, puedeAsignar, omitidos: [...acumOmitidos.values()], usoDimensiones,
                acciones, urlConfig: puedeConfigurar ? urlConfiguracion(urlBase) : null,
                pendientes: pendientesReales,
                info: acumInfo, generados, interrumpido, onGenerado,
            });
        })();
    }

    /**
     * @param {Object} opts
     * @param {string} opts.urlBase      Base del módulo (p. ej. ".../modulos/asientos_contables").
     * @param {Function} [opts.onGenerado] Callback tras generar con éxito (p. ej. refrescar la tabla/reporte).
     * @param {string[]} [opts.bloquear]  Selectores de botones a deshabilitar hasta que el aviso se resuelva
     *                                    (p. ej. ['#btnGenerar']). Ver CMG_asientosPendientesBloqueado().
     */
    window.CMG_verificarAsientosPendientes = function (opts) {
        opts = opts || {};
        const urlBase = opts.urlBase;
        const onGenerado = opts.onGenerado;
        if (!urlBase) return;

        bloquear(opts.bloquear);

        const ctrl = new AbortController();
        mostrarIndicador(() => { ctrl.abort(); ocultarIndicador(); terminar(); });

        fetch(`${urlBase}/contarPendientesAjax`, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: ctrl.signal })
            .then(r => r.json())
            .then(json => {
                ocultarIndicador();
                if (!json || !json.ok) { terminar(); return; }
                const n = parseInt(json.pendientes, 10) || 0;
                // `otros` (opcional): pendientes de los demás establecimientos del RUC, que el
                // servidor manda solo cuando la empresa activa es la matriz (Estados Financieros).
                // Solo se avisan: cada establecimiento genera los suyos entrando a él.
                const otros = (Array.isArray(json.otros) ? json.otros : [])
                    .filter(o => (parseInt(o.pendientes, 10) || 0) > 0);
                if (n < 1 && !otros.length) { terminar(); return; }

                const htmlOtros = otros.length
                    ? `<div class="text-start small mt-3 pt-2 border-top">`
                        + `<i class="bi bi-shop text-warning me-1"></i><strong>Otros establecimientos del RUC con asientos pendientes:</strong>`
                        + `<ul class="mb-1 mt-1">${otros.map(o => `<li>${escapeHtml(o.etiqueta)}: <strong>${parseInt(o.pendientes, 10)}</strong> documento(s)</li>`).join('')}</ul>`
                        + `<span class="text-muted">Para generarlos, cambie a ese establecimiento y abra este módulo.</span></div>`
                    : '';

                if (!window.Swal) {
                    // Sin SweetAlert: confirm nativo como respaldo.
                    if (n < 1) {
                        window.alert('Otros establecimientos del RUC tienen asientos contables pendientes de generar.');
                        terminar();
                    } else if (window.confirm(`Hay ${n} documento(s) sin asiento contable generado. ¿Desea generarlos ahora?`)) {
                        generar(urlBase, onGenerado);
                    } else {
                        terminar();
                    }
                    return;
                }

                if (n < 1) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Asientos pendientes en otros establecimientos',
                        html: 'Este establecimiento no tiene asientos pendientes.' + htmlOtros,
                        confirmButtonText: 'Continuar',
                        allowOutsideClick: false,
                    }).then(() => terminar());
                    return;
                }

                Swal.fire({
                    icon: 'question',
                    title: 'Asientos pendientes',
                    html: `Hay <strong>${n}</strong> documento(s) sin asiento contable generado${otros.length ? ' en este establecimiento' : ''}.<br>¿Desea generarlos ahora?` + htmlOtros,
                    width: otros.length ? 600 : undefined,
                    showCancelButton: true,
                    confirmButtonText: '<i class="bi bi-gear-fill me-1"></i> Generar ahora',
                    cancelButtonText: 'Continuar sin generar',
                    confirmButtonColor: '#0d6efd',
                    cancelButtonColor: '#6c757d',
                    allowOutsideClick: false,
                    reverseButtons: true,
                }).then(res => {
                    if (res.isConfirmed) generar(urlBase, onGenerado);
                    else terminar();
                });
            })
            .catch(() => { ocultarIndicador(); terminar(); /* Silencioso: no bloquear el módulo por el aviso. */ });
    };
})();
