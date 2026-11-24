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

    // ── Asignar cuentas desde el aviso ─────────────────────────────────────────────────────
    // `asignables` (SincronizadorAsientosService::registrarAsignables): una fila por cuenta que se
    // puede asignar aquí mismo. Cuatro clases:
    //   entidad  proveedor/cliente × concepto que le falta → en la General o solo para ese tercero.
    //   item     ítem de compra sin cuenta (reparto por línea) → en la General o solo para ese ítem.
    //   forma    forma de cobro/pago sin cuenta.
    //   opcion   concepto de Ingresos/Egresos sin cuenta.
    // POST configuracion-contable/asignarCuentaAvisoAjax solo COMPLETA lo que falta (no cambia una
    // cuenta ya puesta). Luego «Volver a generar».

    /** Máximo de filas por grupo en la ventana; el resto se configura con el enlace «Configurar». */
    const MAX_ASIGNABLES = 25;

    /** Grupo (título) de una fila y su orden en la ventana. */
    function grupoAsignable(a) {
        switch (a.clase) {
            case 'entidad': return { orden: 1, titulo: `${a.seccion} — por ${a.entidad === 'cliente' ? 'cliente' : 'proveedor'}` };
            case 'item':    return { orden: 2, titulo: `${a.seccion} — por ítem de compra` };
            case 'forma':   return { orden: 3, titulo: 'Formas de cobro y pago' };
            case 'opcion':  return { orden: 4, titulo: 'Conceptos de ingresos y egresos' };
            default:        return { orden: 9, titulo: 'Otras cuentas' };
        }
    }

    /** Destinos de una fila: [valor, etiqueta]. Las formas y conceptos tienen uno solo (el propio registro). */
    function destinosAsignable(a) {
        if (a.clase === 'entidad') {
            return [['general', 'En la General'], ['entidad', a.entidad === 'cliente' ? 'Solo este cliente' : 'Solo este proveedor']];
        }
        if (a.clase === 'item') return [['general', 'En la General'], ['entidad', 'Solo este ítem']];
        return [];
    }

    /** Aviso al elegir «Solo este proveedor/cliente» para quien aún no tiene cuentas propias. */
    function notaEntidad(a) {
        if (a.clase !== 'entidad' || a.con_cuentas_propias) return '';
        const esCliente = a.entidad === 'cliente';
        return `Este ${esCliente ? 'cliente' : 'proveedor'} pasará a tener cuentas propias: sus ${esCliente ? 'ventas' : 'compras'} dejarán de
            repartirse por ${esCliente ? 'producto' : 'ítem'}, categoría o marca, y lo que no configure en él se tomará de la General.`;
    }

    function cabeceraAsignable(a) {
        const docs = `<span class="text-muted">· ${a.documentos} doc.</span>`;
        const inactiva = a.inactiva ? ` <span class="badge bg-secondary bg-opacity-10 text-secondary">${a.clase === 'forma' ? 'inactiva' : 'inactivo'}</span>` : '';
        switch (a.clase) {
            case 'item':
                return `<div>Ítem <strong>${escapeHtml(a.nombre)}</strong> ${docs} — falta «${escapeHtml(a.concepto)}»</div>`;
            case 'forma':
            case 'opcion':
                return `<div>${a.clase === 'forma' ? (a.flujo === 'cobro' ? 'Forma de cobro' : 'Forma de pago') : (a.naturaleza === 'egreso' ? 'Concepto de egresos' : 'Concepto de ingresos')}`
                    + ` <strong>${escapeHtml(a.nombre)}</strong>${inactiva} ${docs} — sin cuenta contable</div>`;
            default:
                return `<div><strong>${escapeHtml(a.nombre)}</strong> ${docs} — falta «${escapeHtml(a.concepto)}»</div>`;
        }
    }

    function filaAsignable(a, i) {
        const cabecera = cabeceraAsignable(a);
        if (a.resuelto) {
            return `<div class="border-top pt-2 mt-2" data-asig-fila="${i}">${cabecera}`
                + `<div class="text-success mt-1"><i class="bi bi-check-circle-fill me-1"></i>${escapeHtml(a.resuelto)}</div></div>`;
        }
        const destinos = destinosAsignable(a);
        // Con cuentas propias, lo natural es completar las suyas; si no, la General (no le apaga el
        // reparto por ítem/producto, categoría o marca).
        const porDefecto = (a.clase === 'entidad' && a.con_cuentas_propias) ? 'entidad' : 'general';
        const selector = destinos.length
            ? `<select class="form-select form-select-sm" style="width:175px;" data-asig-destino title="Dónde guardar la cuenta">`
                + destinos.map(([v, t]) => `<option value="${v}"${v === porDefecto ? ' selected' : ''}>${t}</option>`).join('')
                + `</select>`
            : '';
        const nota = notaEntidad(a);
        return `<div class="border-top pt-2 mt-2" data-asig-fila="${i}">${cabecera}
            <div class="d-flex flex-wrap gap-1 align-items-start mt-1" data-asig-controles>
                <div class="flex-grow-1" style="min-width:240px;">
                    <input type="text" class="form-control form-control-sm" autocomplete="off"
                           placeholder="Buscar cuenta (código o nombre)…" data-asig-input
                           data-tipo-cuenta="${escapeHtml(a.tipo_cuenta || '')}">
                    <div class="list-group mt-1 shadow-sm" data-asig-sug style="display:none;max-height:170px;overflow:auto;"></div>
                </div>
                ${selector}
                <button type="button" class="btn btn-sm btn-success" data-asig-guardar disabled>
                    <i class="bi bi-check-lg me-1"></i>Asignar</button>
            </div>
            ${nota ? `<div class="text-warning-emphasis mt-1" style="font-size:.72rem;" data-asig-nota-prov hidden>
                <i class="bi bi-exclamation-triangle me-1"></i>${nota}</div>` : ''}
        </div>`;
    }

    /**
     * Concepto de una fila de proveedor/cliente/ítem: las filas con la misma clave se resuelven
     * juntas al asignar la cuenta «En la General».
     */
    const claveConcepto = (a) => `${a.clase}|${a.entidad || ''}|${a.tipo_asiento}|${a.id_asiento_tipo}`;
    const esDeConcepto = (a) => a.clase === 'entidad' || a.clase === 'item';

    /**
     * Fila resumen de un concepto que les falta a VARIOS proveedores/clientes/ítems: «subtotal…» falta
     * en 100 proveedores → Asignar en la General. Va arriba del grupo, así ningún concepto queda
     * escondido detrás del tope de filas y el caso común se resuelve con un solo clic.
     */
    function filaResumen(c) {
        const quienes = c.rep.clase === 'item' ? 'ítems' : (c.rep.entidad === 'cliente' ? 'clientes' : 'proveedores');
        const docs = c.rep.clase === 'item' ? '' : ` <span class="text-muted">· ${c.documentos} doc.</span>`;
        const cabecera = `<div><i class="bi bi-collection me-1 text-primary"></i>«${escapeHtml(c.rep.concepto)}» falta en `
            + `<strong>${c.filas} ${quienes}</strong>${docs}</div>`;
        const base = `class="border rounded-2 bg-white px-2 pb-2 pt-1 mt-2" data-asig-fila="${c.idx}" data-asig-resumen="${escapeHtml(c.clave)}"`;
        if (c.resuelto) {
            return `<div ${base}>${cabecera}<div class="text-success mt-1"><i class="bi bi-check-circle-fill me-1"></i>${escapeHtml(c.resuelto)}</div></div>`;
        }
        return `<div ${base}>${cabecera}
            <div class="d-flex flex-wrap gap-1 align-items-start mt-1" data-asig-controles>
                <div class="flex-grow-1" style="min-width:240px;">
                    <input type="text" class="form-control form-control-sm" autocomplete="off"
                           placeholder="Buscar cuenta (código o nombre)…" data-asig-input
                           data-tipo-cuenta="${escapeHtml(c.rep.tipo_cuenta || '')}">
                    <div class="list-group mt-1 shadow-sm" data-asig-sug style="display:none;max-height:170px;overflow:auto;"></div>
                </div>
                <button type="button" class="btn btn-sm btn-success" data-asig-guardar disabled>
                    <i class="bi bi-check-lg me-1"></i>Asignar en la General a los ${c.filas}</button>
            </div>
        </div>`;
    }

    /**
     * @param {Object[]} asignables Filas recibidas.
     * @param {Object[]} [omitidos] Lo que el servidor solo contó por el tope por concepto:
     *                              [{clave_concepto, filas, documentos}] (SincronizadorAsientosService::agregarAsignable).
     */
    function htmlAsignables(asignables, omitidos) {
        const extra = new Map((omitidos || []).map(o => [o.clave_concepto, o]));
        const grupos = new Map();
        asignables.forEach((a, i) => {
            const g = grupoAsignable(a);
            if (!grupos.has(g.titulo)) grupos.set(g.titulo, { ...g, filas: [] });
            grupos.get(g.titulo).filas.push([a, i]);
        });
        const html = [...grupos.values()].sort((x, y) => x.orden - y.orden).map(g => {
            // Resumen por concepto (solo si a más de uno le falta el mismo).
            const porConcepto = new Map();
            g.filas.forEach(([a, i]) => {
                if (!esDeConcepto(a)) return;
                const k = claveConcepto(a);
                if (!porConcepto.has(k)) porConcepto.set(k, { clave: k, idx: i, rep: a, filas: 0, documentos: 0, pendientes: 0 });
                const c = porConcepto.get(k);
                c.filas++;
                c.documentos += parseInt(a.documentos, 10) || 0;
                if (!a.resuelto) c.pendientes++;
                else c.resuelto = c.resuelto || a.resuelto;
            });
            // Lo que no viajó (tope por concepto del servidor) también cuenta en el resumen y en el «y N más».
            let omitidasGrupo = 0;
            porConcepto.forEach(c => {
                const o = extra.get(c.clave);
                if (!o) return;
                c.filas += parseInt(o.filas, 10) || 0;
                c.documentos += parseInt(o.documentos, 10) || 0;
                omitidasGrupo += parseInt(o.filas, 10) || 0;
            });
            const resumenes = [...porConcepto.values()].filter(c => c.filas > 1).map(c => {
                if (c.pendientes > 0) c.resuelto = null;
                return filaResumen(c);
            }).join('');
            const titDetalle = resumenes
                ? `<div class="text-muted mt-2" style="font-size:.72rem;">Uno por uno (solo si alguno necesita su propia cuenta):</div>`
                : '';
            const ocultas = Math.max(0, g.filas.length - MAX_ASIGNABLES) + omitidasGrupo;
            const resto = ocultas > 0
                ? `<div class="text-muted mt-2" style="font-size:.75rem;">Y ${ocultas} más: `
                    + (resumenes ? 'se resuelven con la fila de arriba («En la General») o con el enlace «Configurar».</div>' : 'configúrelos con el enlace «Configurar».</div>')
                : '';
            return `<div class="mt-2 pt-1"><div class="fw-semibold text-secondary" style="font-size:.78rem;">${escapeHtml(g.titulo)}</div>`
                + resumenes + titDetalle
                + g.filas.slice(0, MAX_ASIGNABLES).map(([a, i]) => filaAsignable(a, i)).join('') + resto + `</div>`;
        }).join('');
        return `<div class="text-start small mb-2 border rounded-3 p-2 bg-light" data-asig>
            <div class="fw-semibold"><i class="bi bi-lightning-charge-fill text-primary me-1"></i>Asigne aquí las cuentas que faltan</div>
            <div class="text-muted" style="font-size:.75rem;">«En la General» sirve para todos los que no tienen esa cuenta propia;
                «Solo este…» se la configura únicamente a ese proveedor, cliente o ítem.</div>
            ${html}
            <div class="d-flex justify-content-end align-items-center gap-2 mt-2 pt-2 border-top">
                <span class="text-muted" style="font-size:.75rem;" data-asig-estado>Asigne las cuentas y vuelva a generar.</span>
                <button type="button" class="btn btn-sm btn-primary" data-asig-regenerar disabled>
                    <i class="bi bi-arrow-repeat me-1"></i>Volver a generar</button>
            </div>
        </div>`;
    }

    /** Lo que se envía al servidor para una fila y un destino. */
    function cuerpoAsignar(a, destino, idCuenta) {
        const p = { clase: a.clase, id_cuenta: String(idCuenta) };
        if (a.clase === 'entidad' || a.clase === 'item') {
            p.destino = destino || 'general';
            p.id_asiento_tipo = String(a.id_asiento_tipo);
            if (a.clase === 'entidad') p.id_entidad = String(a.id_entidad);
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
     * Conecta los controles de la sección «Asigne aquí…» dentro de la ventana abierta.
     * `alRegenerar` cierra el aviso y vuelve a generar.
     */
    function activarAsignables(contenedor, opts, alRegenerar) {
        const sec = contenedor.querySelector('[data-asig]');
        if (!sec) return;
        const asignables = opts.asignables;
        const urlCuentas = urlApp(opts.urlBase) + '/modulos/plan-cuentas/searchAjaxCuentas';
        const urlAsignar = urlConfiguracion(opts.urlBase) + '/asignarCuentaAvisoAjax';
        const btnRegenerar = sec.querySelector('[data-asig-regenerar]');
        const estado = sec.querySelector('[data-asig-estado]');
        let temporizador = null;
        let consulta = 0;

        const filaDe = (el) => el.closest('[data-asig-fila]');
        const itemDe = (fila) => asignables[parseInt(fila.getAttribute('data-asig-fila'), 10)];

        function marcarHayCambios() {
            opts.huboAsignaciones = true;
            btnRegenerar.disabled = false;
            estado.textContent = 'Cuentas guardadas. Vuelva a generar para crear los asientos.';
        }
        if (opts.huboAsignaciones) marcarHayCambios();

        function marcarResuelta(fila, texto) {
            fila.querySelector('[data-asig-controles]')?.remove();
            fila.querySelector('[data-asig-nota-prov]')?.remove();
            const ok = document.createElement('div');
            ok.className = 'text-success mt-1';
            ok.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i>${escapeHtml(texto)}`;
            fila.appendChild(ok);
        }

        function limpiarSeleccion(input) {
            input.value = '';
            delete input.dataset.idCuenta;
            const fila = filaDe(input);
            fila.querySelector('[data-asig-guardar]').disabled = true;
            fila.querySelector('[data-asig-sug]').style.display = 'none';
        }

        function cerrarSugerencias(excepto) {
            sec.querySelectorAll('[data-asig-sug]').forEach(s => { if (s !== excepto) s.style.display = 'none'; });
        }

        function buscar(input) {
            const sug = filaDe(input).querySelector('[data-asig-sug]');
            const texto = input.value.trim();
            if (texto.length < 2) { sug.style.display = 'none'; return; }
            clearTimeout(temporizador);
            temporizador = setTimeout(async () => {
                const mia = ++consulta;
                try {
                    const url = `${urlCuentas}?q=${encodeURIComponent(texto)}&tipo=${encodeURIComponent(input.dataset.tipoCuenta || '')}`;
                    const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.json());
                    if (mia !== consulta) return; // llegó una respuesta más nueva
                    sug.innerHTML = '';
                    if (!res || !res.ok) {
                        sug.innerHTML = `<div class="list-group-item py-1 px-2 small text-danger">${escapeHtml((res && res.error) || 'No se pudieron buscar las cuentas.')}</div>`;
                    } else if (!Array.isArray(res.data) || !res.data.length) {
                        sug.innerHTML = '<div class="list-group-item py-1 px-2 small text-muted">Sin cuentas de movimiento que coincidan.</div>';
                    } else {
                        res.data.forEach(c => {
                            const b = document.createElement('button');
                            b.type = 'button';
                            b.className = 'list-group-item list-group-item-action py-1 px-2 small text-start';
                            b.textContent = `${c.codigo} - ${c.nombre}`;
                            b.addEventListener('click', () => {
                                input.value = `${c.codigo} - ${c.nombre}`;
                                input.dataset.idCuenta = String(c.id);
                                sug.style.display = 'none';
                                filaDe(input).querySelector('[data-asig-guardar]').disabled = false;
                            });
                            sug.appendChild(b);
                        });
                    }
                    cerrarSugerencias(sug);
                    sug.style.display = 'block';
                } catch (e) {
                    sug.style.display = 'none';
                }
            }, 300);
        }

        /**
         * Tras guardar: marca como resueltas EN LOS DATOS la fila y las que quedan resueltas con ella
         * (también las que no se ven por el tope de filas, para que el resumen y un nuevo dibujado
         * tras «Descargar PDF» lo reflejen) y luego repinta lo que está en pantalla.
         */
        function aplicarResultado(item, destino, cuenta) {
            if (esDeConcepto(item) && destino === 'general') {
                // La General cubre ese concepto para todos los de la lista (también para quien tiene
                // cuentas propias: lo que no configura se toma de la General).
                asignables.forEach(it => {
                    if (!it.resuelto && esDeConcepto(it)
                        && it.tipo_asiento === item.tipo_asiento && it.id_asiento_tipo === item.id_asiento_tipo) {
                        it.resuelto = `Asignada en la General: ${cuenta}`;
                    }
                });
            } else if (item.clase === 'entidad') {
                // Ya tiene cuentas propias: sus demás filas dejan de advertirlo.
                asignables.forEach(it => {
                    if (it.clase === 'entidad' && it.entidad === item.entidad && it.id_entidad === item.id_entidad
                        && it.tipo_asiento === item.tipo_asiento) {
                        it.con_cuentas_propias = true;
                    }
                });
                item.resuelto = `Asignada solo a este ${item.entidad === 'cliente' ? 'cliente' : 'proveedor'}: ${cuenta}`;
            } else if (item.clase === 'item') {
                item.resuelto = `Asignada solo a este ítem: ${cuenta}`;
            } else {
                item.resuelto = `Asignada: ${cuenta}`;
            }
            repintar();
        }

        /** Lleva la pantalla al estado de los datos: filas y resúmenes resueltos, notas que sobran. */
        function repintar() {
            sec.querySelectorAll('[data-asig-fila]').forEach(f => {
                const it = itemDe(f);
                const claveRes = f.getAttribute('data-asig-resumen');
                if (claveRes !== null) {
                    // El resumen queda resuelto cuando ya no le falta a ninguno de su concepto.
                    const todos = asignables.filter(x => esDeConcepto(x) && claveConcepto(x) === claveRes);
                    if (f.querySelector('[data-asig-controles]') && todos.every(x => x.resuelto)) {
                        marcarResuelta(f, todos[0].resuelto);
                    }
                    return;
                }
                if (it.con_cuentas_propias) f.querySelector('[data-asig-nota-prov]')?.remove();
                if (it.resuelto && f.querySelector('[data-asig-controles]')) marcarResuelta(f, it.resuelto);
            });
        }

        async function asignar(fila, boton) {
            if (boton.dataset.enCurso === '1') return; // doble clic
            const item = itemDe(fila);
            const input = fila.querySelector('[data-asig-input]');
            // La fila resumen siempre guarda en la General (no tiene selector).
            const destino = fila.hasAttribute('data-asig-resumen') ? 'general' : (fila.querySelector('[data-asig-destino]')?.value || '');
            const idCuenta = input.dataset.idCuenta;
            if (!idCuenta) return;
            boton.dataset.enCurso = '1';
            boton.disabled = true;
            const htmlBoton = boton.innerHTML;
            boton.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            try {
                const res = await fetch(urlAsignar, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: cuerpoAsignar(item, destino, idCuenta).toString(),
                }).then(r => r.json());
                if (!res || !res.ok) {
                    estado.innerHTML = `<span class="text-danger"><i class="bi bi-x-circle me-1"></i>${escapeHtml((res && res.error) || 'No se pudo asignar la cuenta.')}</span>`;
                    boton.innerHTML = htmlBoton;
                    boton.disabled = false;
                    return;
                }
                aplicarResultado(item, destino, res.cuenta || input.value);
                marcarHayCambios();
            } catch (e) {
                estado.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>Error de conexión: no se asignó la cuenta.</span>';
                boton.innerHTML = htmlBoton;
                boton.disabled = false;
            } finally {
                boton.dataset.enCurso = '';
            }
        }

        sec.addEventListener('input', (e) => {
            const input = e.target.closest('[data-asig-input]');
            if (!input) return;
            // Al escribir de nuevo, la cuenta elegida deja de valer.
            if (input.dataset.idCuenta) {
                delete input.dataset.idCuenta;
                filaDe(input).querySelector('[data-asig-guardar]').disabled = true;
            }
            buscar(input);
        });
        sec.addEventListener('keydown', (e) => {
            const input = e.target.closest('[data-asig-input]');
            if (!input) return;
            if (e.key === 'Enter') { e.preventDefault(); e.stopPropagation(); return; }
            if (e.key === 'Escape') {
                const sug = filaDe(input).querySelector('[data-asig-sug]');
                if (sug.style.display !== 'none') { e.stopPropagation(); sug.style.display = 'none'; }
                return;
            }
            // Cuenta ya elegida: Retroceso/Suprimir la limpia de una vez (no letra por letra).
            if ((e.key === 'Backspace' || e.key === 'Delete') && input.dataset.idCuenta) {
                e.preventDefault();
                limpiarSeleccion(input);
            }
        });
        sec.addEventListener('change', (e) => {
            const sel = e.target.closest('[data-asig-destino]');
            if (!sel) return;
            const fila = filaDe(sel);
            const nota = fila.querySelector('[data-asig-nota-prov]');
            if (nota) nota.hidden = !(sel.value === 'entidad' && !itemDe(fila).con_cuentas_propias);
        });
        sec.addEventListener('click', (e) => {
            const boton = e.target.closest('[data-asig-guardar]');
            if (boton) { asignar(filaDe(boton), boton); return; }
            if (e.target.closest('[data-asig-regenerar]')) { alRegenerar(); return; }
            if (!e.target.closest('[data-asig-input]') && !e.target.closest('[data-asig-sug]')) cerrarSugerencias(null);
        });
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
        // Lo que se puede resolver aquí mismo va primero: es lo más rápido para el usuario.
        const asignables = (opts.puedeAsignar && Array.isArray(opts.asignables)) ? opts.asignables : [];
        if (asignables.length && window.Swal) {
            html += htmlAsignables(asignables, opts.omitidos);
        }
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

        if (window.Swal) {
            // «Descargar PDF»: el aviso no se guarda en el servidor, así que el PDF es la forma de
            // tenerlo a mano mientras se configura, sin volver a generar para leerlo otra vez.
            const conPdf = (hayPendientes || info.length > 0) && !!opts.urlBase
                && typeof window.CMG_pdfDocumento === 'function';
            let regenerar = false;
            Swal.fire({
                icon: interrumpido ? 'info' : (hayPendientes ? 'warning' : 'success'),
                title: interrumpido ? 'Generación interrumpida' : (hayPendientes ? 'Generación completada con avisos' : 'Asientos generados'),
                html: html,
                width: asignables.length ? 780 : ((acciones.length || info.length) ? 640 : undefined),
                confirmButtonText: 'Aceptar',
                showDenyButton: conPdf,
                denyButtonText: '<i class="bi bi-file-earmark-pdf me-1"></i> Descargar PDF',
                denyButtonColor: '#dc3545',
                allowOutsideClick: !conPdf && !asignables.length,
                didOpen: (popup) => {
                    if (asignables.length) {
                        activarAsignables(popup, opts, () => { regenerar = true; Swal.close(); });
                    }
                },
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

            // Dentro de cada grupo (htmlAsignables): por concepto y luego por nombre, así las filas que
            // se resuelven juntas con «En la General» quedan seguidas.
            const asignables = [...acumAsignables.values()].sort((a, b) =>
                String(a.concepto).localeCompare(String(b.concepto), 'es')
                || String(a.nombre).localeCompare(String(b.nombre), 'es'));

            mostrarResultado({
                urlBase,
                asignables, puedeAsignar, omitidos: [...acumOmitidos.values()],
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
