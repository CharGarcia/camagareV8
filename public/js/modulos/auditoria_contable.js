/**
 * Módulo Auditoría Contable — listado, ejecución, correcciones y regeneración.
 * Depende de window.AUD_CONFIG (inyectado por la vista) y SweetAlert (Swal).
 */
(function () {
    'use strict';

    const cfg = window.AUD_CONFIG || {};
    const urlBase = cfg.urlBase;

    const state = {
        page: cfg.page || 1,
        sort: cfg.ordenCol || 'detectado_at',
        dir: cfg.ordenDir || 'DESC',
        buscar: '',
        vista: cfg.vista || 'pendientes', // pendientes | corregidas | todas
    };

    /**
     * Una fecha solo se envía cuando está completa: al teclearla a mano el input emite
     * valores intermedios ('0002-...', años de 5 dígitos) que no deben llegar al servidor.
     */
    function fechaCompleta(v) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(v)) return false;
        const anio = Number(v.slice(0, 4));
        return anio >= 1900 && anio <= 2999;
    }

    /** Rango de fechas activo en la barra superior (aplica al listado y a las 3 acciones). */
    function rangoFechas() {
        const d = document.getElementById('audFechaDesde')?.value || '';
        const h = document.getElementById('audFechaHasta')?.value || '';
        return {
            fecha_desde: fechaCompleta(d) ? d : '',
            fecha_hasta: fechaCompleta(h) ? h : '',
        };
    }

    /**
     * Recalcula el rango de fechas desde los selects de año/mes. Los inputs de fecha quedan
     * como override libre: si el usuario los edita a mano, manda lo que escribió.
     * Mes 0 = todo el año. Mismo criterio que el módulo Mayores.
     */
    function actualizarFechasPorPeriodo() {
        const anio = parseInt(document.getElementById('audAnio')?.value || '0', 10);
        const mes = parseInt(document.getElementById('audMes')?.value || '0', 10);
        if (!anio) return;

        let ini, fin;
        if (!mes) {
            ini = `${anio}-01-01`;
            fin = `${anio}-12-31`;
        } else {
            const mm = String(mes).padStart(2, '0');
            ini = `${anio}-${mm}-01`;
            fin = `${anio}-${mm}-${String(new Date(anio, mes, 0).getDate()).padStart(2, '0')}`;
        }
        const d = document.getElementById('audFechaDesde');
        const h = document.getElementById('audFechaHasta');
        if (d) d.value = ini;
        if (h) h.value = fin;
    }

    /** Orígenes marcados en el dropdown. Array vacío = sin filtro (todos). */
    function origenesSeleccionados() {
        return Array.from(document.querySelectorAll('.js-aud-origen:checked')).map((c) => c.value);
    }

    /** Refresca el texto del botón del dropdown de orígenes. */
    function refrescarEtiquetaOrigenes() {
        const sel = origenesSeleccionados();
        const btn = document.getElementById('audOrigenBtn');
        const resumen = document.getElementById('audOrigenResumen');
        const total = document.querySelectorAll('.js-aud-origen').length;
        let txt;
        if (sel.length === 0 || sel.length === total) txt = 'Todos';
        else if (sel.length === 1) {
            const lbl = document.querySelector('.js-aud-origen[value="' + sel[0] + '"]')?.closest('label')?.textContent?.trim();
            txt = lbl || sel[0];
        } else txt = sel.length + ' orígenes';
        if (btn) btn.textContent = txt;
        if (resumen) resumen.textContent = (sel.length === 0 || sel.length === total) ? '(todos)' : '(' + sel.length + ')';
    }

    /**
     * Búsqueda efectiva del listado: lo que el usuario tecleó + el filtro de orígenes del
     * dropdown, expresado con el token `origen:a,b,c` que el backend ya entiende
     * (FiltrosBusqueda). Si el usuario ya escribió un `origen:` a mano, se respeta el suyo.
     */
    function buscarEfectivo() {
        const sel = origenesSeleccionados();
        const total = document.querySelectorAll('.js-aud-origen').length;
        const base = state.buscar || '';
        if (sel.length === 0 || sel.length === total) return base;
        if (/(^|\s)(origen|modulo):/i.test(base)) return base;
        return (base + ' origen:' + sel.join(',')).trim();
    }

    /**
     * Origen a mandar a las acciones masivas: una sola selección se manda tal cual; varias o
     * ninguna se mandan como «todos» (los endpoints procesan origen por origen).
     */
    function origenParaAccion() {
        const sel = origenesSeleccionados();
        return sel.length === 1 ? sel[0] : '__todos__';
    }

    // ---- Helpers HTTP ----
    // El backend (PermisoModuloTrait::esAjaxRequest) reconoce una petición AJAX por estas
    // cabeceras. Sin ellas, un fallo de permiso/sesión responde con un redirect HTML en vez
    // de JSON 403 y el fetch revienta con "Unexpected token '<'".
    const AJAX_HEADERS = {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
    };

    /** Convierte la respuesta a JSON; si llega HTML (login/redirect/error), da un mensaje legible. */
    function parseJson(r) {
        const ct = r.headers.get('content-type') || '';
        if (ct.includes('application/json')) {
            return r.json().then((data) => {
                // Sesión expirada (401) o sin permiso (403): el núcleo responde {ok:false, mensaje|error}
                if (!r.ok && data && data.ok === false) {
                    throw new Error(data.error || data.mensaje || 'No autorizado.');
                }
                return data;
            });
        }
        return r.text().then(() => {
            throw new Error('La sesión expiró o el servidor devolvió una respuesta inesperada. '
                + 'Recargue la página e inicie sesión nuevamente.');
        });
    }

    function postForm(accion, data) {
        const body = new URLSearchParams(data || {});
        return fetch(`${urlBase}/${accion}`, {
            method: 'POST',
            headers: Object.assign({ 'Content-Type': 'application/x-www-form-urlencoded' }, AJAX_HEADERS),
            body,
        }).then(parseJson);
    }

    function getJSON(accion, params) {
        const qs = new URLSearchParams(params || {}).toString();
        return fetch(`${urlBase}/${accion}${qs ? '?' + qs : ''}`, { headers: AJAX_HEADERS }).then(parseJson);
    }

    function toast(icon, title) {
        if (window.Swal) {
            Swal.fire({ toast: true, position: 'top-end', timer: 2500, showConfirmButton: false, icon, title });
        }
    }

    function err(msg) {
        if (window.Swal) Swal.fire('Error', msg || 'Ocurrió un error', 'error');
        else alert(msg);
    }

    // ---- Listado ----
    function fetchSearch(page) {
        if (page) state.page = page;
        const tbody = document.getElementById('audTbody');
        getJSON('searchAjax', Object.assign(
            { b: buscarEfectivo(), page: state.page, sort: state.sort, dir: state.dir, vista: state.vista },
            rangoFechas()
        ))
            .then((res) => {
                if (!res.ok) { err(res.error); return; }
                tbody.innerHTML = res.html;
                document.getElementById('audPaginationInfo').textContent = `${res.from}-${res.to}/${res.total}`;
                document.getElementById('audPrev').disabled = res.page <= 1;
                document.getElementById('audNext').disabled = res.page >= res.totalPages;
                state.page = res.page;
                state.totalPages = res.totalPages;
                actualizarResumen(res.resumen);
                actualizarContadores(res.contadores);
            })
            .catch((e) => err(e.message));
    }

    /** Refresca los badges de las pestañas: al corregir, baja «Por corregir» y sube «Corregidas». */
    function actualizarContadores(c) {
        if (!c) return;
        const p = document.getElementById('audCntPendientes');
        const r = document.getElementById('audCntCorregidas');
        if (p) p.textContent = c.pendientes ?? 0;
        if (r) r.textContent = c.corregidas ?? 0;
    }

    function actualizarResumen(resumen) {
        if (!resumen) return;
        document.querySelectorAll('#audResumen .aud-card-resumen').forEach((card) => {
            const t = card.dataset.tipo;
            const num = card.querySelector('.fs-4');
            if (num) num.textContent = resumen[t] || 0;
        });
    }

    // ---- Ejecutar auditoría ----
    function ejecutarAuditoria() {
        // Una sola selección acota la auditoría a ese origen; varias/ninguna auditan todo.
        const sel = origenesSeleccionados();
        const origen = sel.length === 1 ? sel[0] : '';
        const btn = document.getElementById('btnEjecutarAuditoria');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Ejecutando…';
        postForm('ejecutarAuditoriaAjax', Object.assign({ origen }, rangoFechas()))
            .then((res) => {
                if (!res.ok) { err(res.error); return; }
                const d = res.data || {};
                toast('success', `Auditoría lista: ${d.detectadas || 0} incidencia(s).`);
                fetchSearch(1);
            })
            .catch((e) => err(e.message))
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-search me-1"></i> Ejecutar auditoría';
            });
    }

    // ---- Acciones por fila ----
    function getFila(el) { return el.closest('tr'); }

    function generarFaltante(tr) {
        Swal.fire({
            title: 'Generar asiento', text: 'Se generará el asiento del documento.',
            icon: 'question', showCancelButton: true, confirmButtonText: 'Generar',
        }).then((r) => {
            if (!r.isConfirmed) return;
            postForm('generarFaltanteAjax', { id: tr.dataset.id })
                .then((res) => { if (res.ok) { toast('success', res.mensaje); fetchSearch(); } else err(res.error); })
                .catch((e) => err(e.message));
        });
    }

    function corregirAmbiente(tr) {
        postForm('corregirAmbienteAjax', { id: tr.dataset.id })
            .then((res) => { if (res.ok) { toast('success', res.mensaje); fetchSearch(); } else err(res.error); })
            .catch((e) => err(e.message));
    }

    /** Elimina (anula) un asiento huérfano: su documento de origen ya no existe. */
    function eliminarHuerfano(tr) {
        Swal.fire({
            title: '¿Eliminar este asiento huérfano?',
            html: 'El documento que originó este asiento <strong>ya no existe</strong> (fue eliminado), '
                + 'así que el asiento no debería seguir sumando en Balance, Estado de Resultados ni Mayores.'
                + '<br><br><span class="small text-muted">Se marca como anulado (no se borra), así que es reversible.</span>',
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Sí, eliminar',
        }).then((r) => {
            if (!r.isConfirmed) return;
            postForm('eliminarHuerfanoAjax', { id: tr.dataset.id })
                .then((res) => { if (res.ok) { toast('success', res.msg); fetchSearch(); } else err(res.error); })
                .catch((e) => err(e.message));
        });
    }

    /** Elimina TODOS los asientos huérfanos del filtro actual. */
    function eliminarHuerfanosMasivo() {
        const f = rangoFechas();
        Swal.fire({
            title: '¿Eliminar todos los asientos huérfanos?',
            html: 'Se anularán <strong>todos</strong> los asientos cuyo documento de origen ya no existe, '
                + 'dentro del período y orígenes seleccionados en el filtro.'
                + '<br><br><span class="small text-muted">Reversible (quedan anulados, no se borran). '
                + 'No se tocan los de tipo Diario ni los de períodos cerrados.</span>',
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Sí, eliminar',
        }).then((r) => {
            if (!r.isConfirmed) return;
            const btn = document.getElementById('btnEliminarHuerfanos');
            if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Eliminando…'; }
            postForm('eliminarHuerfanosMasivoAjax', Object.assign({ origen: origenParaAccion() }, f))
                .then((res) => {
                    if (!res.ok) { err(res.error); return; }
                    const d = res.data || {};
                    Swal.fire({
                        title: 'Huérfanos eliminados',
                        html: '<div class="text-start small">'
                            + '<div>Asientos eliminados: <strong>' + (d.eliminados || 0) + '</strong></div>'
                            + (d.errores ? '<div class="text-danger">Con error: <strong>' + d.errores + '</strong></div>' : '')
                            + ((d.mensajes || []).length ? '<div class="mt-2 text-muted">' + d.mensajes.join('<br>') + '</div>' : '')
                            + '</div>',
                        icon: d.errores ? 'warning' : 'success',
                    });
                    fetchSearch(1);
                })
                .catch((e) => err(e.message))
                .finally(() => {
                    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-trash me-1"></i> Eliminar huérfanos'; }
                });
        });
    }

    function regenerarDocumento(tr) {
        Swal.fire({
            title: 'Regenerar este asiento',
            html: 'Se <strong>anulará</strong> el asiento actual del documento y se <strong>volverá a generar</strong> con la configuración vigente.',
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Regenerar',
        }).then((r) => {
            if (!r.isConfirmed) return;
            postForm('regenerarDocumentoAjax', { id: tr.dataset.id })
                .then((res) => { if (res.ok) { toast('success', res.mensaje); fetchSearch(); } else err(res.error); })
                .catch((e) => err(e.message));
        });
    }

    function abrirRevision(tr) {
        document.getElementById('revIncidenciaId').value = tr.dataset.id;
        document.getElementById('revEstado').value = 'revisada';
        document.getElementById('revNota').value = '';
        new bootstrap.Modal(document.getElementById('modalRevision')).show();
    }

    function guardarRevision() {
        const id = document.getElementById('revIncidenciaId').value;
        postForm('marcarRevisionAjax', {
            id,
            estado_revision: document.getElementById('revEstado').value,
            nota: document.getElementById('revNota').value,
        }).then((res) => {
            if (res.ok) {
                bootstrap.Modal.getInstance(document.getElementById('modalRevision')).hide();
                toast('success', res.mensaje); fetchSearch();
            } else err(res.error);
        }).catch((e) => err(e.message));
    }

    // ---- Duplicados ----
    function abrirDuplicados(tr) {
        const cont = document.getElementById('dupListado');
        cont.innerHTML = '<div class="text-center py-3"><span class="spinner-border spinner-border-sm"></span></div>';
        new bootstrap.Modal(document.getElementById('modalDuplicados')).show();
        getJSON('asientosDocumentoAjax', { origen: tr.dataset.origen, documento: tr.dataset.doc })
            .then((res) => {
                const rows = (res.data || []);
                if (!rows.length) { cont.innerHTML = '<p class="text-muted small">Sin asientos.</p>'; return; }
                let html = '<table class="table table-sm table-bordered small mb-0"><thead class="table-light"><tr>'
                    + '<th>Asiento</th><th>Nº</th><th>Fecha</th><th class="text-end">Debe</th><th class="text-end">Haber</th><th>Estado</th><th></th>'
                    + '</tr></thead><tbody>';
                rows.forEach((a) => {
                    const fecha = a.fecha_asiento ? a.fecha_asiento.substring(0, 10).split('-').reverse().join('-') : '';
                    html += `<tr>
                        <td>#${a.id}</td>
                        <td>${a.numero_comprobante || ''}</td>
                        <td>${fecha}</td>
                        <td class="text-end">${parseFloat(a.total_debe).toFixed(2)}</td>
                        <td class="text-end">${parseFloat(a.total_haber).toFixed(2)}</td>
                        <td>${a.estado || ''}</td>
                        <td class="text-end">${cfg.perm && cfg.perm.eliminar
                            ? `<button class="btn btn-sm btn-outline-danger js-dup-anular" data-asiento="${a.id}"><i class="bi bi-trash3"></i> Anular</button>`
                            : ''}</td>
                    </tr>`;
                });
                html += '</tbody></table>';
                cont.innerHTML = html;
            })
            .catch((e) => { cont.innerHTML = `<p class="text-danger small">${e.message}</p>`; });
    }

    function anularDuplicado(idAsiento) {
        Swal.fire({
            title: '¿Anular este asiento?', text: 'Se eliminará lógicamente (anulado).',
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Anular',
        }).then((r) => {
            if (!r.isConfirmed) return;
            postForm('anularDuplicadoAjax', { id_asiento: idAsiento })
                .then((res) => {
                    if (res.ok) {
                        bootstrap.Modal.getInstance(document.getElementById('modalDuplicados')).hide();
                        toast('success', res.mensaje); fetchSearch();
                    } else err(res.error);
                })
                .catch((e) => err(e.message));
        });
    }

    // ---- Regeneración masiva (por lotes) ----
    //  Una corrida grande no cabe en una sola petición, así que el navegador encadena
    //  lotes contra el servidor y va mostrando el avance. El servidor lleva el estado
    //  de la corrida en sesión; aquí solo se pide «el siguiente lote de este origen».

    /** Pinta el progreso: paso actual, porcentaje y bitácora. */
    const regProgreso = {
        mostrar() { document.getElementById('regProgresoBox')?.classList.remove('d-none'); },
        paso(txt) {
            const el = document.getElementById('regProgresoPaso');
            if (el) el.textContent = txt;
        },
        porcentaje(hechos, total) {
            const pct = total > 0 ? Math.min(100, Math.round((hechos / total) * 100)) : 100;
            const barra = document.getElementById('regProgresoBarra');
            const txt = document.getElementById('regProgresoPct');
            if (barra) barra.style.width = pct + '%';
            if (txt) txt.textContent = pct + '%';
        },
        log(linea, clase) {
            const caja = document.getElementById('regProgresoLog');
            if (!caja) return;
            const div = document.createElement('div');
            if (clase) div.className = clase;
            div.textContent = linea;
            caja.appendChild(div);
            caja.scrollTop = caja.scrollHeight;
        },
        limpiar() {
            const caja = document.getElementById('regProgresoLog');
            if (caja) caja.innerHTML = '';
            this.porcentaje(0, 1);
        },
    };

    /** Encadena los lotes de un origen hasta que no queden asientos por rehacer. */
    async function regenerarOrigen(item, avance) {
        let restantes = item.pendientes;
        let anulados = 0;
        let fallidos = 0;
        // Tope de vueltas: si un lote dejara de avanzar, se corta en vez de girar sin fin.
        const maxVueltas = Math.ceil(item.pendientes / (avance.lote || 25)) + 5;

        for (let i = 0; i < maxVueltas && restantes > 0; i++) {
            const res = await postForm('regenerarLoteAjax', { origen: item.origen });
            if (!res.ok) {
                regProgreso.log('✗ ' + item.label + ': ' + res.error, 'text-danger');
                return;
            }
            const d = res.data;
            anulados += d.anulados;
            fallidos += d.errores;
            avance.hechos += d.anulados;
            regProgreso.porcentaje(avance.hechos, avance.total);
            (d.mensajes || []).forEach((m) => regProgreso.log('  ⚠ ' + m, 'text-warning'));

            if (d.procesados === 0 && d.anulados === 0) break;
            restantes = d.restantes;
        }
        regProgreso.log('✓ ' + item.label + ': ' + anulados + ' asiento(s) rehecho(s)'
            + (fallidos ? ' — ' + fallidos + ' con error' : '')
            + (item.cerrados ? ' — ' + item.cerrados + ' omitido(s) por período cerrado' : ''));
    }

    async function correrRegeneracion() {
        const origen = document.getElementById('regOrigen').value;
        const desde = document.getElementById('regDesde').value;
        const hasta = document.getElementById('regHasta').value;
        const conFaltantes = document.getElementById('regFaltantes')?.checked;
        const btn = document.getElementById('btnConfirmarRegenerar');
        const btnCerrar = document.getElementById('btnCerrarRegenerar');

        btn.disabled = true;
        btnCerrar.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Procesando…';
        regProgreso.mostrar();
        regProgreso.limpiar();
        regProgreso.paso('Calculando el alcance…');

        try {
            const plan = await postForm('planRegeneracionAjax', {
                origen,
                fecha_desde: fechaCompleta(desde) ? desde : '',
                fecha_hasta: fechaCompleta(hasta) ? hasta : '',
            });
            if (!plan.ok) { err(plan.error); return; }

            const d = plan.data;
            const avance = { hechos: 0, total: Math.max(1, d.total), lote: d.lote };
            regProgreso.log('Alcance: ' + d.total + ' asiento(s) a rehacer'
                + (d.cerrados ? ', ' + d.cerrados + ' omitido(s) por período cerrado' : ''));

            const pendientes = d.origenes.filter((o) => o.pendientes > 0);
            for (let i = 0; i < pendientes.length; i++) {
                const item = pendientes[i];
                regProgreso.paso(`(${i + 1}/${pendientes.length}) ${item.label} — ${item.pendientes} asiento(s)`);
                await regenerarOrigen(item, avance);
            }

            if (conFaltantes) {
                regProgreso.paso('Generando los asientos faltantes…');
                const f = await postForm('generarFaltantesAjax', {});
                if (f.ok) {
                    regProgreso.log('✓ Faltantes generados: ' + f.data.generados);
                    (f.data.avisos || []).forEach((a) => regProgreso.log('  ⚠ ' + a, 'text-warning'));
                } else {
                    regProgreso.log('✗ Faltantes: ' + f.error, 'text-danger');
                }
            }

            regProgreso.paso('Verificando con la auditoría…');
            const fin = await postForm('finalizarRegeneracionAjax', {});
            if (!fin.ok) { err(fin.error); return; }

            const t = fin.data;
            regProgreso.porcentaje(1, 1);
            regProgreso.paso('Terminado');
            actualizarResumen(t.resumen);
            actualizarContadores(t.contadores);
            fetchSearch(1);

            Swal.fire({
                icon: t.errores > 0 ? 'warning' : 'success',
                title: t.errores > 0 ? 'Regeneración con avisos' : 'Regeneración terminada',
                html: `Asientos anulados: <strong>${t.anulados}</strong><br>`
                    + `Asientos regenerados: <strong>${t.regenerados}</strong><br>`
                    + `Faltantes generados: <strong>${t.faltantes}</strong><br>`
                    + `Omitidos por período cerrado: <strong>${t.omitidos}</strong><br>`
                    + (t.errores > 0 ? `Con error: <strong class="text-danger">${t.errores}</strong><br>` : '')
                    + `<hr class="my-2">Incidencias detectadas ahora: <strong>${t.detectadas}</strong>`,
            });
        } catch (e) {
            err(e.message);
        } finally {
            btn.disabled = false;
            btnCerrar.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Generar asientos';
        }
    }

    // ---- Anulación masiva (solo anula, NO regenera) ----
    /** Barra de progreso propia del modal de anulación (misma mecánica que regProgreso). */
    const anuProgreso = {
        mostrar() { document.getElementById('anuProgresoBox')?.classList.remove('d-none'); },
        limpiar() {
            const l = document.getElementById('anuProgresoLog');
            if (l) l.innerHTML = '';
            this.porcentaje(0, 1);
        },
        paso(txt) {
            const p = document.getElementById('anuProgresoPaso');
            if (p) p.textContent = txt;
        },
        log(txt, clase) {
            const l = document.getElementById('anuProgresoLog');
            if (!l) return;
            const d = document.createElement('div');
            if (clase) d.className = clase;
            d.textContent = txt;
            l.appendChild(d);
            l.scrollTop = l.scrollHeight;
        },
        porcentaje(hechos, total) {
            const pct = total > 0 ? Math.min(100, Math.round((hechos / total) * 100)) : 0;
            const b = document.getElementById('anuProgresoBarra');
            const t = document.getElementById('anuProgresoPct');
            if (b) b.style.width = pct + '%';
            if (t) t.textContent = pct + '%';
        },
    };

    /** Encadena los lotes de anulación de un origen hasta agotarlo. */
    async function anularOrigen(item, avance) {
        let restantes = item.pendientes;
        let anulados = 0;
        const maxVueltas = Math.ceil(item.pendientes / (avance.lote || 25)) + 5;

        for (let i = 0; i < maxVueltas && restantes > 0; i++) {
            const res = await postForm('anularLoteAjax', { origen: item.origen });
            if (!res.ok) {
                anuProgreso.log('✗ ' + item.label + ': ' + res.error, 'text-danger');
                return;
            }
            const d = res.data;
            anulados += d.anulados;
            avance.hechos += d.anulados;
            anuProgreso.porcentaje(avance.hechos, avance.total);
            if (d.anulados === 0) break;
            restantes = d.restantes;
        }
        anuProgreso.log('✓ ' + item.label + ': ' + anulados + ' asiento(s) anulado(s)'
            + (item.cerrados ? ' — ' + item.cerrados + ' omitido(s) por período cerrado' : ''));
    }

    async function correrAnulacion() {
        const origen = document.getElementById('anuOrigen').value;
        const desde = document.getElementById('anuDesde').value;
        const hasta = document.getElementById('anuHasta').value;
        const btn = document.getElementById('btnConfirmarAnular');
        const btnCerrar = document.getElementById('btnCerrarAnular');

        btn.disabled = true;
        btnCerrar.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Anulando…';
        anuProgreso.mostrar();
        anuProgreso.limpiar();
        anuProgreso.paso('Calculando el alcance…');

        try {
            const plan = await postForm('planAnulacionAjax', {
                origen,
                fecha_desde: fechaCompleta(desde) ? desde : '',
                fecha_hasta: fechaCompleta(hasta) ? hasta : '',
            });
            if (!plan.ok) { err(plan.error); return; }

            const d = plan.data || {};
            const conPendientes = (d.origenes || []).filter((o) => o.pendientes > 0);
            if (!conPendientes.length) {
                anuProgreso.paso('Nada por anular');
                anuProgreso.log('No hay asientos que anular con esos filtros.');
                toast('info', 'No hay asientos que anular con esos filtros.');
                return;
            }

            anuProgreso.log('Se anularán ' + d.total + ' asiento(s) en ' + conPendientes.length + ' módulo(s).');
            if (d.cerrados) anuProgreso.log('⚠ ' + d.cerrados + ' quedan fuera por período contable cerrado.', 'text-warning');

            const avance = { hechos: 0, total: d.total, lote: d.lote };
            for (const item of conPendientes) {
                anuProgreso.paso('Anulando ' + item.label + '…');
                await anularOrigen(item, avance);
            }

            anuProgreso.paso('Cerrando…');
            const fin = await postForm('finalizarAnulacionAjax', {});
            if (!fin.ok) { err(fin.error); return; }

            anuProgreso.porcentaje(1, 1);
            anuProgreso.paso('Listo');
            const f = fin.data || {};
            Swal.fire({
                title: 'Asientos eliminados',
                html: '<div class="text-start small">'
                    + '<div>Asientos anulados: <strong>' + (f.anulados || 0) + '</strong></div>'
                    + '<div>Documentos que quedaron sin contabilidad: <strong>' + (f.documentos || 0) + '</strong></div>'
                    + '<div class="mt-2 text-muted">Use «Generar asientos» para volver a crearlos.</div>'
                    + '</div>',
                icon: 'success',
            });
            fetchSearch(1);
        } catch (e) {
            err(e.message);
        } finally {
            btn.disabled = false;
            btnCerrar.disabled = false;
            btn.innerHTML = '<i class="bi bi-slash-circle me-1"></i>Eliminar asientos';
        }
    }

    function confirmarAnular() {
        const sel = document.getElementById('anuOrigen');
        const todos = sel.value === '__todos__';
        Swal.fire({
            title: '¿Eliminar los asientos del filtro?',
            html: (todos
                    ? 'Se <strong>anularán</strong> los asientos de <strong>todos los módulos</strong> en el rango indicado.'
                    : 'Se <strong>anularán</strong> los asientos de «' + sel.options[sel.selectedIndex].text + '».')
                + '<br><br><span class="small text-danger">Los documentos quedarán <strong>SIN contabilidad</strong>: '
                + 'desaparecen del Balance, Estado de Resultados y Mayores hasta que se regeneren.</span>'
                + '<br><span class="small text-muted">Es reversible (los asientos quedan anulados, no se borran). '
                + 'No se tocan los de tipo Diario, los de migración ni los de períodos cerrados.</span>',
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Sí, eliminar',
        }).then((r) => {
            if (r.isConfirmed) correrAnulacion();
        });
    }

    function confirmarRegenerar() {
        const sel = document.getElementById('regOrigen');
        const todos = sel.value === '__todos__';
        Swal.fire({
            title: todos ? '¿Generar los asientos de toda la contabilidad?' : '¿Generar asientos?',
            html: (todos
                    ? 'Se <strong>anularán y volverán a generar</strong> los asientos de <strong>todos los módulos</strong> con la configuración contable actual.'
                    : 'Se <strong>anularán</strong> los asientos de «' + sel.options[sel.selectedIndex].text + '» y se <strong>volverán a generar</strong>.')
                + '<br><br><span class="small text-muted">No se tocan los asientos de <strong>tipo Diario</strong> '
                + '(manuales y de activos fijos), los de la migración ni los de períodos cerrados. '
                + 'Los comprobantes se renumeran.</span>',
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Sí, generar',
        }).then((r) => {
            if (r.isConfirmed) correrRegeneracion();
        });
    }

    // ---- Eventos ----
    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('btnEjecutarAuditoria')?.addEventListener('click', ejecutarAuditoria);
        document.getElementById('btnGuardarRevision')?.addEventListener('click', guardarRevision);
        document.getElementById('btnConfirmarRegenerar')?.addEventListener('click', confirmarRegenerar);
        document.getElementById('btnConfirmarAnular')?.addEventListener('click', confirmarAnular);
        document.getElementById('btnEliminarHuerfanos')?.addEventListener('click', eliminarHuerfanosMasivo);

        // Al reabrir el modal, el progreso de la corrida anterior no debe seguir a la vista.
        // Además se precargan origen y fechas desde la barra de filtros, para que las acciones
        // masivas operen sobre lo mismo que el usuario está viendo en el listado.
        document.getElementById('modalRegenerar')?.addEventListener('show.bs.modal', () => {
            document.getElementById('regProgresoBox')?.classList.add('d-none');
            regProgreso.limpiar();
            regProgreso.paso('Preparando…');
            const f = rangoFechas();
            const d = document.getElementById('regDesde');
            const h = document.getElementById('regHasta');
            const o = document.getElementById('regOrigen');
            if (d) d.value = f.fecha_desde;
            if (h) h.value = f.fecha_hasta;
            // Solo se precarga si ese origen existe en el selector (regenerables ⊂ orígenes).
            if (o) {
                const val = origenParaAccion();
                o.value = Array.from(o.options).some((op) => op.value === val) ? val : '__todos__';
            }
        });

        document.getElementById('modalAnular')?.addEventListener('show.bs.modal', () => {
            document.getElementById('anuProgresoBox')?.classList.add('d-none');
            anuProgreso.limpiar();
            anuProgreso.paso('Preparando…');
            const f = rangoFechas();
            const d = document.getElementById('anuDesde');
            const h = document.getElementById('anuHasta');
            const o = document.getElementById('anuOrigen');
            if (d) d.value = f.fecha_desde;
            if (h) h.value = f.fecha_hasta;
            if (o) o.value = origenParaAccion();
        });

        // Período: los selects de año/mes recalculan el rango de fechas y refrescan.
        document.getElementById('audAnio')?.addEventListener('change', () => {
            actualizarFechasPorPeriodo();
            fetchSearch(1);
        });
        document.getElementById('audMes')?.addEventListener('change', () => {
            actualizarFechasPorPeriodo();
            fetchSearch(1);
        });

        // Orígenes (multi-selección): acotan el listado y precargan las acciones masivas.
        document.querySelectorAll('.js-aud-origen').forEach((chk) => {
            chk.addEventListener('change', () => {
                refrescarEtiquetaOrigenes();
                fetchSearch(1);
            });
        });
        document.getElementById('audOrigenTodos')?.addEventListener('click', () => {
            document.querySelectorAll('.js-aud-origen').forEach((c) => { c.checked = true; });
            refrescarEtiquetaOrigenes();
            fetchSearch(1);
        });
        document.getElementById('audOrigenNinguno')?.addEventListener('click', () => {
            document.querySelectorAll('.js-aud-origen').forEach((c) => { c.checked = false; });
            refrescarEtiquetaOrigenes();
            fetchSearch(1);
        });
        refrescarEtiquetaOrigenes();

        document.getElementById('audPrev')?.addEventListener('click', () => fetchSearch(state.page - 1));
        document.getElementById('audNext')?.addEventListener('click', () => fetchSearch(state.page + 1));

        // Buscador (debounce)
        let t = null;
        document.getElementById('audBuscar')?.addEventListener('input', (e) => {
            clearTimeout(t);
            state.buscar = e.target.value.trim();
            t = setTimeout(() => fetchSearch(1), 350);
        });

        // Pestañas: por corregir / corregidas / todas
        document.querySelectorAll('#audTabs [data-vista]').forEach((btn) => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('#audTabs [data-vista]').forEach((b) => b.classList.remove('active'));
                btn.classList.add('active');
                state.vista = btn.dataset.vista;
                fetchSearch(1);
            });
        });

        // Filtro por fechas (aplica al listado; también acota la auditoría al ejecutarla)
        document.getElementById('audFechaDesde')?.addEventListener('change', () => fetchSearch(1));
        document.getElementById('audFechaHasta')?.addEventListener('change', () => fetchSearch(1));
        // Limpia TODO el filtro: fechas, período y orígenes.
        document.getElementById('audLimpiarFechas')?.addEventListener('click', () => {
            const d = document.getElementById('audFechaDesde');
            const h = document.getElementById('audFechaHasta');
            if (d) d.value = '';
            if (h) h.value = '';
            const mes = document.getElementById('audMes');
            if (mes) mes.value = '0';
            document.querySelectorAll('.js-aud-origen').forEach((c) => { c.checked = false; });
            refrescarEtiquetaOrigenes();
            fetchSearch(1);
        });

        // Ordenamiento por cabecera
        document.querySelectorAll('.sortable-header').forEach((th) => {
            th.addEventListener('click', () => {
                const col = th.dataset.sort;
                if (state.sort === col) state.dir = state.dir === 'ASC' ? 'DESC' : 'ASC';
                else { state.sort = col; state.dir = 'DESC'; }
                if (typeof window.guardarOrdenacionVista === 'function') {
                    window.guardarOrdenacionVista('auditoria_contable', state.sort, state.dir);
                }
                fetchSearch(1);
            });
        });

        // Tarjetas-resumen → filtran por tipo
        document.querySelectorAll('#audResumen .aud-card-resumen').forEach((card) => {
            card.addEventListener('click', () => {
                const tipo = card.dataset.tipo;
                const input = document.getElementById('audBuscar');
                const ya = card.classList.contains('activa');
                document.querySelectorAll('#audResumen .aud-card-resumen').forEach((c) => c.classList.remove('activa'));
                if (ya) { input.value = ''; state.buscar = ''; }
                else { card.classList.add('activa'); input.value = 'tipo:' + tipo; state.buscar = 'tipo:' + tipo; }
                fetchSearch(1);
            });
        });

        // Delegación de acciones en el tbody
        document.getElementById('audTbody')?.addEventListener('click', (e) => {
            const btn = e.target.closest('button');
            if (!btn) return;
            const tr = getFila(btn);
            if (btn.classList.contains('js-aud-generar')) generarFaltante(tr);
            else if (btn.classList.contains('js-aud-ambiente')) corregirAmbiente(tr);
            else if (btn.classList.contains('js-aud-huerfano')) eliminarHuerfano(tr);
            else if (btn.classList.contains('js-aud-regen-doc')) regenerarDocumento(tr);
            else if (btn.classList.contains('js-aud-revisar')) abrirRevision(tr);
            else if (btn.classList.contains('js-aud-duplicado')) abrirDuplicados(tr);
        });

        // Delegación dentro del modal de duplicados
        document.getElementById('dupListado')?.addEventListener('click', (e) => {
            const btn = e.target.closest('.js-dup-anular');
            if (btn) anularDuplicado(btn.dataset.asiento);
        });
    });
})();
