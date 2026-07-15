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

    /** Rango de fechas activo en la barra superior (aplica al listado y a la auditoría). */
    function rangoFechas() {
        const d = document.getElementById('audFechaDesde')?.value || '';
        const h = document.getElementById('audFechaHasta')?.value || '';
        return {
            fecha_desde: fechaCompleta(d) ? d : '',
            fecha_hasta: fechaCompleta(h) ? h : '',
        };
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
            { b: state.buscar, page: state.page, sort: state.sort, dir: state.dir, vista: state.vista },
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
        const origen = document.getElementById('audOrigenAuditar').value;
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
                btn.innerHTML = '<i class="bi bi-play-circle me-1"></i> Ejecutar auditoría';
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

    // ---- Regeneración masiva ----
    function confirmarRegenerar() {
        const origen = document.getElementById('regOrigen').value;
        const desde = document.getElementById('regDesde').value;
        const hasta = document.getElementById('regHasta').value;
        const btn = document.getElementById('btnConfirmarRegenerar');
        Swal.fire({
            title: '¿Regenerar asientos?',
            html: 'Se <strong>anularán</strong> los asientos del origen y se <strong>volverán a generar</strong>.<br>Los de períodos cerrados se omiten.',
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Sí, regenerar',
        }).then((r) => {
            if (!r.isConfirmed) return;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Procesando…';
            postForm('regenerarMasivoAjax', { origen, fecha_desde: desde, fecha_hasta: hasta })
                .then((res) => {
                    if (res.ok) {
                        bootstrap.Modal.getInstance(document.getElementById('modalRegenerar')).hide();
                        Swal.fire('Listo', res.mensaje, 'success');
                        fetchSearch(1);
                    } else err(res.error);
                })
                .catch((e) => err(e.message))
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Regenerar';
                });
        });
    }

    // ---- Eventos ----
    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('btnEjecutarAuditoria')?.addEventListener('click', ejecutarAuditoria);
        document.getElementById('btnGuardarRevision')?.addEventListener('click', guardarRevision);
        document.getElementById('btnConfirmarRegenerar')?.addEventListener('click', confirmarRegenerar);

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
        document.getElementById('audLimpiarFechas')?.addEventListener('click', () => {
            const d = document.getElementById('audFechaDesde');
            const h = document.getElementById('audFechaHasta');
            if (d) d.value = '';
            if (h) h.value = '';
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
