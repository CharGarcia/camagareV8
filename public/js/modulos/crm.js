/**
 * CRM (modulos/crm): Embudo (Kanban), Lista, Contactos y Mis actividades, más los
 * modales de oportunidad, contacto, actividad, cliente y pérdida.
 * Servidor: CrmController. Diseño: docs/diseno/crm-redes-fase1.md §9.1.
 *
 * Reglas del sistema que se respetan aquí:
 *  - Un guardado = un registro (CLAUDE.md §8): bandera «en curso» antes del primer
 *    await, token_guardado por formulario nuevo y el id fijado apenas se crea.
 *  - Crear al vuelo (§9): contacto nuevo desde la oportunidad (crmContactoGuardado)
 *    y cliente nuevo desde Ganar / Crear proforma (clienteGuardado).
 *  - Selector tipo chip: Backspace/Delete limpia la selección de una vez.
 *  - Fechas en pantalla: d-m-Y H:i:s.
 */
(function () {
    'use strict';

    const cfg = window.CRM_CONFIG || {};
    const $ = (id) => document.getElementById(id);
    const esc = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const dinero = (n) => (Number(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const pad = (n) => String(n).padStart(2, '0');

    /** 'YYYY-MM-DD[ HH:MM:SS]' → 'd-m-Y[ H:i:s]' sin pasar por zonas horarias. */
    function fecha(s, conHora) {
        if (!s) return '-';
        const m = String(s).match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/);
        if (!m) return String(s);
        const f = `${m[3]}-${m[2]}-${m[1]}`;
        return conHora ? `${f} ${m[4] || '00'}:${m[5] || '00'}:${m[6] || '00'}` : f;
    }

    function iniciales(nombre) {
        return String(nombre || '?').trim().split(/\s+/).slice(0, 2).map((p) => p[0] || '').join('').toUpperCase() || '?';
    }

    const ICONO_ORIGEN = {
        instagram_dm: 'bi-instagram', instagram_comentario: 'bi-chat-square-text', linkedin: 'bi-linkedin',
        whatsapp: 'bi-whatsapp', referido: 'bi-people', web: 'bi-globe', otro: 'bi-dot',
    };
    const ESTADOS = {
        abierta: ['Abierta', 'bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25'],
        ganada:  ['Ganada', 'bg-success bg-opacity-10 text-success border border-success border-opacity-25'],
        perdida: ['Perdida', 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25'],
    };

    function aviso(icon, title, text) {
        if (window.Swal) return Swal.fire({ icon, title, text });
        alert(title + (text ? '\n' + text : ''));
        return Promise.resolve();
    }
    function toast(text, icon = 'success') {
        if (window.Swal) Swal.fire({ toast: true, position: 'top-end', icon, title: text, showConfirmButton: false, timer: 2500 });
    }
    async function confirmar(text, boton = 'Sí, continuar') {
        if (!window.Swal) return confirm(text);
        const r = await Swal.fire({ icon: 'question', text, showCancelButton: true, confirmButtonText: boton, cancelButtonText: 'Cancelar' });
        return r.isConfirmed;
    }

    /** GET/POST JSON. Un error de negocio llega como {ok:false,error}: se lanza con ese texto. */
    async function api(accion, { params = null, post = null } = {}) {
        let url = `${cfg.urlBase}/${accion}`;
        const opts = { headers: { 'X-Requested-With': 'XMLHttpRequest' } };
        if (params) url += '?' + new URLSearchParams(params).toString();
        if (post) {
            opts.method = 'POST';
            opts.body = post instanceof FormData ? post : (() => { const fd = new FormData(); Object.entries(post).forEach(([k, v]) => fd.append(k, v ?? '')); return fd; })();
        }
        const resp = await fetch(url, opts);
        let json = null;
        try { json = await resp.json(); } catch (e) { /* sin JSON */ }
        if (!json || json.ok === false) {
            throw new Error((json && (json.error || json.mensaje)) || 'Error de conexión. Intente de nuevo.');
        }
        return json;
    }

    function modal(id) { return bootstrap.Modal.getOrCreateInstance($(id)); }

    // ── Estado ───────────────────────────────────────────────────────────────
    const st = {
        datos: { embudos: [], origenes: [], motivos: [], usuarios: [], vendedores: [] },
        vista: 'kanban',
        sorts: Array.isArray(cfg.sorts) ? cfg.sorts : [],
        page: 1,
        pageContactos: 1,
        op: null,              // ficha abierta en el modal
        sorter: null,
        sortables: [],
        guardandoOp: false,
        guardandoCt: false,
        guardandoAct: false,
        accionEnCurso: false,
        modoCliente: null,     // 'ganar' | 'proforma'
    };

    // ── Selects ──────────────────────────────────────────────────────────────
    function opciones(sel, items, { vacio = null, valor = 'id', texto = 'nombre', seleccionado = '' } = {}) {
        if (!sel) return;
        let html = vacio !== null ? `<option value="">${esc(vacio)}</option>` : '';
        items.forEach((i) => { html += `<option value="${esc(i[valor])}">${esc(i[texto])}</option>`; });
        sel.innerHTML = html;
        if (seleccionado !== '' && seleccionado !== null) sel.value = String(seleccionado);
    }
    const embudo = (id) => st.datos.embudos.find((e) => String(e.id) === String(id));
    const etapasAbiertas = (idEmbudo) => (embudo(idEmbudo)?.etapas || []).filter((e) => e.tipo === 'abierta');

    async function cargarDatos() {
        const d = await api('datosAjax');
        st.datos = d;
        const fav = (typeof APP_FAVORITOS !== 'undefined' && APP_FAVORITOS) ? APP_FAVORITOS.id_embudo : '';
        const defecto = (d.embudos.find((e) => e.es_defecto) || d.embudos[0] || {}).id || '';
        opciones($('crm-f-embudo'), d.embudos, { seleccionado: embudo(fav) ? fav : defecto });
        opciones($('crm-f-responsable'), d.usuarios, { vacio: 'Todos' });
        opciones($('crm-f-origen'), d.origenes, { vacio: 'Todos' });
    }

    function filtros() {
        return {
            id_embudo:      $('crm-f-embudo').value,
            estado:         $('crm-f-estado').value,
            id_responsable: $('crm-f-responsable').value,
            id_origen:      $('crm-f-origen').value,
            cierre_desde:   $('crm-f-cierre-desde').value,
            cierre_hasta:   $('crm-f-cierre-hasta').value,
            b:              $('crm-f-buscar').value.trim(),
        };
    }

    function pintarResumen(r) {
        if (!r) return;
        $('crm-k-abiertas').textContent = r.abiertas;
        $('crm-k-valor').textContent = dinero(r.valor_abierto);
        $('crm-k-ponderado').textContent = dinero(r.valor_ponderado);
        $('crm-k-ganadas').textContent = r.ganadas_mes;
        $('crm-k-ganado').textContent = dinero(r.valor_ganado_mes);
        $('crm-k-perdidas').textContent = r.perdidas_mes;
    }

    // ── Vistas ───────────────────────────────────────────────────────────────
    function mostrarVista(v) {
        st.vista = v;
        document.querySelectorAll('[data-crm-vista]').forEach((b) => b.classList.toggle('active', b.dataset.crmVista === v));
        document.querySelectorAll('[data-crm-panel]').forEach((p) => p.classList.toggle('d-none', p.dataset.crmPanel !== v));
        try { localStorage.setItem('crm_vista', v); } catch (e) { /* almacenamiento no disponible */ }
        recargar();
    }

    async function recargar() {
        try {
            if (st.vista === 'kanban') await cargarKanban();
            else if (st.vista === 'lista') await cargarLista(st.page);
            else if (st.vista === 'contactos') await cargarContactos(st.pageContactos);
            else if (st.vista === 'reportes') await cargarReportes();
            else await cargarMisActividades();
        } catch (e) {
            aviso('error', 'No se pudo cargar', e.message);
        }
    }

    // ── Kanban ───────────────────────────────────────────────────────────────
    async function cargarKanban() {
        const f = filtros();
        const cont = $('crm-kanban');
        if (!f.id_embudo) { cont.innerHTML = '<div class="text-muted p-3">No hay embudos activos. Créelos en Configuración del CRM.</div>'; return; }
        const d = await api('kanbanAjax', { params: f });
        pintarResumen(d.resumen);
        st.sortables.forEach((s) => s.destroy());
        st.sortables = [];
        cont.innerHTML = d.columnas.map((col) => `
            <div class="crm-col" style="--crm-col:${esc(col.color || '#6c757d')}">
                <div class="crm-col-head">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold small text-truncate" title="${esc(col.nombre)}">${esc(col.nombre)}</span>
                        <span class="badge bg-white text-secondary border">${col.cantidad}</span>
                    </div>
                    <div class="small text-muted">$${dinero(col.valor)} · ${col.probabilidad}%</div>
                </div>
                <div class="crm-col-body" data-etapa="${col.id}">
                    ${col.tarjetas.map(tarjetaHtml).join('')}
                </div>
                ${col.truncada ? `<div class="crm-col-foot">Se muestran ${col.tarjetas.length} de ${col.cantidad}; vea el resto en Lista.</div>` : ''}
            </div>`).join('') || '<div class="text-muted p-3">El embudo no tiene etapas intermedias.</div>';

        if (window.Sortable && cfg.perm.actualizar) {
            cont.querySelectorAll('.crm-col-body').forEach((body) => {
                st.sortables.push(new Sortable(body, { group: 'crm', animation: 150, ghostClass: 'sortable-ghost', onEnd: alSoltarTarjeta }));
            });
        }
    }

    function tarjetaHtml(t) {
        const icono = ICONO_ORIGEN[t.origen_canal] || '';
        return `<div class="crm-card" data-id="${t.id}" data-orden="${t.orden_kanban}" onclick="CRM.opAbrir(${t.id})">
            <div class="d-flex justify-content-between gap-2">
                <div class="crm-card-titulo" title="${esc(t.titulo)}">${esc(t.titulo)}</div>
                ${t.actividad_vencida ? '<span class="crm-dot mt-1" title="Tiene actividades vencidas"></span>' : ''}
            </div>
            <div class="crm-card-sub">${esc(t.contacto)}${t.empresa_contacto ? ' · ' + esc(t.empresa_contacto) : ''}</div>
            <div class="d-flex justify-content-between align-items-center mt-1">
                <span class="fw-semibold small">$${dinero(t.valor_estimado)}</span>
                <span class="d-flex align-items-center gap-1 small text-muted">
                    ${icono ? `<i class="bi ${icono}" title="${esc(t.origen || '')}"></i>` : ''}
                    <span title="Días en esta etapa">${t.dias_en_etapa}d</span>
                    <span class="crm-avatar" title="${esc(t.responsable || 'Sin responsable')}">${esc(iniciales(t.responsable))}</span>
                </span>
            </div>
        </div>`;
    }

    /** Posición nueva = punto medio entre las tarjetas vecinas (sin renumerar la columna). */
    async function alSoltarTarjeta(ev) {
        const item = ev.item;
        if (ev.from === ev.to && ev.oldIndex === ev.newIndex) return;
        const prev = parseFloat(item.previousElementSibling?.dataset.orden);
        const next = parseFloat(item.nextElementSibling?.dataset.orden);
        let orden;
        if (!isNaN(prev) && !isNaN(next)) orden = (prev + next) / 2;
        else if (!isNaN(prev)) orden = prev + 1;
        else if (!isNaN(next)) orden = next - 1;
        else orden = 1;
        item.dataset.orden = orden;
        try {
            await api('moverEtapaAjax', { post: { id: item.dataset.id, id_etapa: ev.to.dataset.etapa, orden } });
            if (ev.from !== ev.to) cargarKanban();   // totales de las columnas
        } catch (e) {
            aviso('error', 'No se pudo mover', e.message);
            cargarKanban();
        }
    }

    // ── Lista ────────────────────────────────────────────────────────────────
    async function cargarLista(page = 1) {
        const tbody = $('crm-lista-tbody');
        tbody.classList.add('opacity-50');
        try {
            const d = await api('listadoAjax', { params: { ...filtros(), page, orden: window.CMG_ordenParam(st.sorts) } });
            st.page = d.page;
            pintarResumen(d.resumen);
            tbody.innerHTML = d.rows.length ? d.rows.map(filaLista).join('')
                : `<tr><td colspan="11" class="text-center text-muted py-4"><i class="bi bi-inbox fs-4 d-block mb-1"></i>No hay oportunidades con estos filtros.</td></tr>`;
            const desde = d.total ? (d.page - 1) * d.perPage + 1 : 0;
            $('crm-lista-info').textContent = `${desde}-${Math.min(d.page * d.perPage, d.total)}/${d.total}`;
            $('crm-lista-paginacion').innerHTML = paginador(d.page, d.totalPages, 'CRM.irPagina');
            if (st.sorter) st.sorter.refreshIcons();
        } finally {
            tbody.classList.remove('opacity-50');
        }
    }

    function filaLista(r) {
        const [est, cls] = ESTADOS[r.estado] || [r.estado, ''];
        return `<tr class="crm-row" onclick="CRM.opAbrir(${r.id})">
            <td class="ps-3" data-col="numero">${r.numero}</td>
            <td data-col="titulo" class="text-truncate" style="max-width:260px" title="${esc(r.titulo)}">${esc(r.titulo)}</td>
            <td data-col="contacto" class="text-truncate" style="max-width:200px">${esc(r.contacto)}</td>
            <td data-col="etapa"><span class="badge rounded-pill" style="background:${esc(r.etapa_color || '#6c757d')}">${esc(r.etapa)}</span></td>
            <td data-col="valor_estimado" class="text-end">$${dinero(r.valor_estimado)}</td>
            <td data-col="probabilidad" class="text-end">${r.probabilidad}%</td>
            <td data-col="fecha_cierre_estimada">${fecha(r.fecha_cierre_estimada)}</td>
            <td data-col="responsable">${esc(r.responsable || '-')}</td>
            <td data-col="origen">${esc(r.origen || '-')}</td>
            <td data-col="estado" class="text-center"><span class="badge ${cls}">${est}</span></td>
            <td data-col="created_at">${fecha(r.created_at, true)}</td>
        </tr>`;
    }

    function paginador(page, total, fn) {
        return `<button type="button" class="btn btn-outline-secondary" ${page <= 1 ? 'disabled' : ''} onclick="${fn}(${page - 1})"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" ${page >= total ? 'disabled' : ''} onclick="${fn}(${page + 1})"><i class="bi bi-chevron-right"></i></button>`;
    }

    // ── Contactos ────────────────────────────────────────────────────────────
    async function cargarContactos(page = 1) {
        const d = await api('contactosAjax', { params: { b: $('crm-f-buscar').value.trim(), page } });
        st.pageContactos = d.page;
        $('crm-contactos-tbody').innerHTML = d.rows.length ? d.rows.map((c) => `
            <tr class="crm-row" onclick="CRM.ctAbrir(${c.id})">
                <td class="ps-3 fw-medium">${esc(c.nombre)}</td>
                <td>${esc(c.empresa_contacto || '-')}</td>
                <td>${esc(c.telefono || '-')}</td>
                <td>${esc(c.correo || '-')}</td>
                <td>${c.instagram_usuario ? '@' + esc(c.instagram_usuario) : '-'}</td>
                <td>${esc(c.origen || '-')}</td>
                <td>${esc(c.responsable || '-')}</td>
                <td>${c.cliente ? `<i class="bi bi-check-circle text-success me-1"></i>${esc(c.cliente)}` : '-'}</td>
                <td class="text-center">${c.oportunidades_abiertas}</td>
            </tr>`).join('')
            : '<tr><td colspan="9" class="text-center text-muted py-4">No hay contactos.</td></tr>';
        const desde = d.total ? (d.page - 1) * d.perPage + 1 : 0;
        $('crm-contactos-info').textContent = `${desde}-${Math.min(d.page * d.perPage, d.total)}/${d.total}`;
        $('crm-contactos-paginacion').innerHTML = paginador(d.page, d.totalPages, 'CRM.irPaginaContactos');
    }

    // ── Mis actividades ──────────────────────────────────────────────────────
    async function cargarMisActividades() {
        const d = await api('misActividadesAjax');
        pintarBadgeVencidas(d.vencidas);
        $('crm-actividades-lista').innerHTML = d.data.length ? d.data.map((a) => actividadHtml(a, true)).join('')
            : '<div class="list-group-item text-muted text-center py-4">No tiene actividades pendientes.</div>';
    }

    function pintarBadgeVencidas(n) {
        const b = $('crm-badge-vencidas');
        if (!b) return;
        b.textContent = n;
        b.classList.toggle('d-none', !n);
    }

    const ICONO_ACT = { tarea: 'bi-check2-square', llamada: 'bi-telephone', reunion: 'bi-people', nota: 'bi-sticky', correo: 'bi-envelope' };

    function actividadHtml(a, conEnlace) {
        const venc = a.vencida ? 'text-danger fw-semibold' : 'text-muted';
        return `<div class="list-group-item d-flex gap-2 align-items-start">
            ${a.tipo !== 'nota' ? `<input class="form-check-input mt-1" type="checkbox" ${a.completada ? 'checked' : ''} ${cfg.perm.actualizar ? '' : 'disabled'}
                 onclick="event.stopPropagation(); CRM.actCompletar(${a.id}, this.checked)" title="Completada">` : '<i class="bi bi-sticky text-warning mt-1"></i>'}
            <div class="flex-grow-1" role="button" onclick='CRM.actAbrir(${esc(JSON.stringify(a))})'>
                <div class="${a.completada && a.tipo !== 'nota' ? 'text-decoration-line-through text-muted' : 'fw-medium'} small">
                    <i class="bi ${ICONO_ACT[a.tipo] || 'bi-dot'} me-1"></i>${esc(a.titulo)}</div>
                ${a.descripcion ? `<div class="small text-muted">${esc(a.descripcion)}</div>` : ''}
                <div class="small ${venc}">${a.vence_at ? fecha(a.vence_at, true) : fecha(a.created_at, true)} · ${esc(a.responsable || '')}</div>
            </div>
            ${conEnlace && a.id_oportunidad ? `<button type="button" class="btn btn-sm btn-link p-0" onclick="CRM.opAbrir(${a.id_oportunidad})" title="Abrir la oportunidad"><i class="bi bi-box-arrow-up-right"></i></button>` : ''}
        </div>`;
    }

    // ── Reportes ─────────────────────────────────────────────────────────────
    const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    /** Barra de magnitud (una serie, un tono) con su valor escrito y tooltip. */
    function barra(valor, maximo, titulo) {
        const pct = maximo > 0 ? Math.max(2, Math.round((valor / maximo) * 100)) : 0;
        return `<div class="crm-barra" title="${esc(titulo)}"><span style="width:${valor > 0 ? pct : 0}%"></span></div>`;
    }

    function tabla(cabeceras, filas, vacio) {
        if (!filas.length) return `<div class="text-muted small p-3">${vacio}</div>`;
        return `<div class="table-responsive"><table class="table table-sm mb-0 crm-r-tabla align-middle">
            <thead><tr>${cabeceras.map((c) => `<th class="${c[1] || ''}">${c[0]}</th>`).join('')}</tr></thead>
            <tbody>${filas.join('')}</tbody></table></div>`;
    }

    function kpi(icono, color, valor, etiqueta, ayuda) {
        return `<div class="col-6 col-md-4 col-xl"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2 d-flex gap-2 align-items-center" title="${esc(ayuda || '')}">
            <i class="bi ${icono} fs-4 text-${color}"></i>
            <div><div class="fw-bold">${valor}</div><div class="small text-muted">${etiqueta}</div></div></div></div></div>`;
    }

    async function cargarReportes() {
        const params = { ...filtros(), desde: $('crm-r-desde').value, hasta: $('crm-r-hasta').value };
        const d = await api('reportesAjax', { params });
        $('crm-r-desde').value = d.desde;
        $('crm-r-hasta').value = d.hasta;
        const r = d.resumen;
        const pct = (v) => (v === null ? '—' : v.toLocaleString('es-EC') + '%');
        $('crm-r-kpis').innerHTML = [
            kpi('bi-box-arrow-in-right', 'primary', r.creadas, 'Creadas', 'Oportunidades que entraron al embudo en el período'),
            kpi('bi-trophy', 'success', `${r.ganadas} · $${dinero(r.valor_ganado)}`, 'Ganadas', 'Cerradas como ganadas en el período'),
            kpi('bi-x-octagon', 'danger', `${r.perdidas} · $${dinero(r.valor_perdido)}`, 'Perdidas', 'Cerradas como perdidas en el período'),
            kpi('bi-bullseye', 'secondary', pct(r.tasa_cierre), 'Tasa de cierre', 'Ganadas ÷ (ganadas + perdidas) del período'),
            kpi('bi-receipt', 'secondary', r.ticket === null ? '—' : '$' + dinero(r.ticket), 'Ticket promedio', 'Valor ganado ÷ ganadas'),
            kpi('bi-hourglass-split', 'secondary', r.dias_cierre === null ? '—' : r.dias_cierre + ' días', 'Hasta ganar', 'Días promedio desde que se creó hasta que se ganó'),
        ].join('');

        const maxEtapa = Math.max(0, ...d.por_etapa.map((e) => +e.valor));
        $('crm-r-etapas').innerHTML = tabla([['Etapa'], ['Cant.', 'text-end'], ['Valor', 'text-end'], ['Ponderado', 'text-end'], ['', 'w-25']],
            d.por_etapa.map((e) => `<tr><td><span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background:${esc(e.color || '#6c757d')}"></span>${esc(e.nombre)} <span class="text-muted">${e.probabilidad}%</span></td>
                <td class="text-end">${e.cantidad}</td><td class="text-end">$${dinero(e.valor)}</td><td class="text-end">$${dinero(e.ponderado)}</td>
                <td>${barra(+e.valor, maxEtapa, `${e.nombre}: $${dinero(e.valor)}`)}</td></tr>`), 'Sin embudo.');

        const maxDias = Math.max(0, ...d.tiempo_etapa.map((e) => +(e.dias_promedio || 0)));
        $('crm-r-tiempo').innerHTML = tabla([['Etapa'], ['Pasos', 'text-end'], ['Días prom.', 'text-end'], ['', 'w-25']],
            d.tiempo_etapa.map((e) => `<tr><td>${esc(e.nombre)}</td><td class="text-end">${e.pasos}</td>
                <td class="text-end">${e.dias_promedio === null ? '—' : e.dias_promedio}</td>
                <td>${barra(+(e.dias_promedio || 0), maxDias, `${e.nombre}: ${e.dias_promedio ?? 0} días`)}</td></tr>`), 'Sin movimientos en el período.');

        const maxMes = Math.max(0, ...d.por_mes.map((m) => +m.valor_ganado));
        $('crm-r-meses').innerHTML = tabla([['Mes'], ['Creadas', 'text-end'], ['Ganadas', 'text-end'], ['Valor ganado', 'text-end'], ['Perdidas', 'text-end'], ['', 'w-25']],
            d.por_mes.map((m) => {
                const [a, mm] = m.mes.split('-');
                const etiqueta = `${MESES[+mm - 1]} ${a}`;
                return `<tr><td>${etiqueta}</td><td class="text-end">${m.creadas}</td><td class="text-end">${m.ganadas}</td>
                    <td class="text-end">$${dinero(m.valor_ganado)}</td><td class="text-end">${m.perdidas}</td>
                    <td>${barra(+m.valor_ganado, maxMes, `${etiqueta}: $${dinero(m.valor_ganado)} ganado`)}</td></tr>`;
            }), 'Sin datos.');

        const grupo = (filas) => {
            const max = Math.max(0, ...filas.map((g) => +g.valor_ganado));
            return tabla([['Nombre'], ['Creadas', 'text-end'], ['Ganadas', 'text-end'], ['Perdidas', 'text-end'], ['Cierre', 'text-end'], ['Valor ganado', 'text-end'], ['Abiertas', 'text-end'], ['', 'w-25']],
                filas.map((g) => `<tr><td>${esc(g.nombre)}</td><td class="text-end">${g.creadas}</td><td class="text-end">${g.ganadas}</td>
                    <td class="text-end">${g.perdidas}</td><td class="text-end">${pct(g.tasa_cierre)}</td><td class="text-end">$${dinero(g.valor_ganado)}</td>
                    <td class="text-end" title="Valor abierto: $${dinero(g.valor_abierto)}">${g.abiertas}</td>
                    <td>${barra(+g.valor_ganado, max, `${g.nombre}: $${dinero(g.valor_ganado)} ganado`)}</td></tr>`), 'Sin datos en el período.');
        };
        $('crm-r-origen').innerHTML = grupo(d.por_origen);
        $('crm-r-responsable').innerHTML = grupo(d.por_responsable);

        const totalMot = d.motivos.reduce((s, m) => s + +m.cantidad, 0);
        $('crm-r-motivos').innerHTML = tabla([['Motivo'], ['Cant.', 'text-end'], ['%', 'text-end'], ['Valor', 'text-end'], ['', 'w-25']],
            d.motivos.map((m) => `<tr><td>${esc(m.nombre)}</td><td class="text-end">${m.cantidad}</td>
                <td class="text-end">${totalMot ? Math.round(m.cantidad * 100 / totalMot) : 0}%</td><td class="text-end">$${dinero(m.valor)}</td>
                <td>${barra(+m.cantidad, totalMot, `${m.nombre}: ${m.cantidad}`)}</td></tr>`), 'No se perdieron oportunidades en el período.');
    }

    // ── Selector tipo chip (contacto / cliente) ──────────────────────────────
    /**
     * Autocompletar que fija un valor en un input oculto. Con algo seleccionado,
     * Backspace o Delete limpian la selección de una vez (regla de §9, ref.
     * setupTypeahead() de mayores/index.php).
     */
    function typeahead({ input, oculto, dd, buscar, etiqueta, alElegir }) {
        let timer = null;
        let items = [];
        const cerrar = () => dd.classList.add('d-none');
        const limpiar = () => { oculto.value = ''; input.value = ''; cerrar(); };
        input.addEventListener('keydown', (e) => {
            if ((e.key === 'Backspace' || e.key === 'Delete') && oculto.value) { e.preventDefault(); limpiar(); }
            if (e.key === 'Escape') cerrar();
        });
        input.addEventListener('input', () => {
            if (oculto.value) oculto.value = '';
            clearTimeout(timer);
            timer = setTimeout(async () => {
                try {
                    items = await buscar(input.value.trim());
                    dd.innerHTML = items.length ? items.map((it, i) => `<button type="button" class="list-group-item list-group-item-action py-1 small" data-i="${i}">${etiqueta(it, true)}</button>`).join('')
                        : '<div class="list-group-item small text-muted py-1">Sin resultados.</div>';
                    dd.classList.remove('d-none');
                } catch (e) { cerrar(); }
            }, 300);
        });
        dd.addEventListener('click', (e) => {
            const b = e.target.closest('[data-i]');
            if (!b) return;
            elegir(items[+b.dataset.i]);
        });
        document.addEventListener('click', (e) => { if (!dd.contains(e.target) && e.target !== input) cerrar(); });
        function elegir(it) {
            oculto.value = it.id;
            input.value = etiqueta(it, false);
            cerrar();
            if (alElegir) alElegir(it);
        }
        return { elegir, limpiar };
    }

    let taContacto = null;
    let taCliente = null;
    const etiquetaContacto = (c, html) => {
        const extra = [c.empresa_contacto, c.telefono, c.instagram_usuario ? '@' + c.instagram_usuario : ''].filter(Boolean).join(' · ');
        return html ? `<span class="fw-medium">${esc(c.nombre)}</span>${extra ? ` <span class="text-muted">· ${esc(extra)}</span>` : ''}` : c.nombre + (extra ? ' · ' + extra : '');
    };
    const etiquetaCliente = (c, html) => html
        ? `<span class="fw-medium">${esc(c.nombre)}</span> <span class="text-muted">${esc(c.identificacion || '')}</span>`
        : `${c.identificacion || ''} - ${c.nombre}`;

    // ── Oportunidad ──────────────────────────────────────────────────────────
    function opLlenarSelects(op) {
        const idEmbudo = op?.id_embudo || $('crm-f-embudo').value || (st.datos.embudos[0] || {}).id;
        opciones($('crm-op-embudo'), st.datos.embudos, { seleccionado: idEmbudo });
        opLlenarEtapas(op?.id_etapa);
        opciones($('crm-op-responsable'), st.datos.usuarios, { vacio: '— Sin responsable —', seleccionado: op ? (op.id_responsable || '') : cfg.idUsuario });
        opciones($('crm-op-vendedor'), st.datos.vendedores, { vacio: '— Ninguno —', seleccionado: op?.id_vendedor || '' });
        opciones($('crm-op-origen'), st.datos.origenes, { vacio: '— Sin origen —', seleccionado: op?.id_origen || '' });
        // Responsable que ya no está asignado a la empresa: se conserva visible.
        if (op && op.id_responsable && $('crm-op-responsable').value !== String(op.id_responsable)) {
            $('crm-op-responsable').insertAdjacentHTML('beforeend', `<option value="${op.id_responsable}">${esc(op.responsable || 'Usuario ' + op.id_responsable)}</option>`);
            $('crm-op-responsable').value = String(op.id_responsable);
        }
    }

    function opLlenarEtapas(seleccionada) {
        const sel = $('crm-op-etapa');
        const etapas = etapasAbiertas($('crm-op-embudo').value);
        opciones(sel, etapas, { seleccionado: seleccionada || (etapas[0] || {}).id || '' });
        // Oportunidad cerrada: su etapa (Ganado/Perdido) no está entre las abiertas.
        if (seleccionada && sel.value !== String(seleccionada) && st.op) {
            sel.insertAdjacentHTML('beforeend', `<option value="${seleccionada}">${esc(st.op.etapa)}</option>`);
            sel.value = String(seleccionada);
        }
    }

    function opEditable(si) {
        $('crm-op-form').querySelectorAll('input, select, textarea').forEach((el) => { if (el.type !== 'hidden') el.disabled = !si; });
        $('crm-op-btn-guardar').classList.toggle('d-none', !si);
    }

    function opNueva(prefill = {}) {
        st.op = null;
        $('crm-op-form').reset();
        $('crm-op-id').value = '';
        $('crm-op-token').value = window.CMG_nuevoTokenGuardado ? CMG_nuevoTokenGuardado() : '';
        $('crm-op-titulo-modal').textContent = 'Nueva oportunidad';
        $('crm-op-badge-estado').classList.add('d-none');
        $('crm-op-acciones').classList.add('d-none');
        $('crm-op-btn-eliminar')?.classList.add('d-none');
        $('crm-op-cierre-info').classList.add('d-none');
        $('crm-op-pie').classList.add('d-none');
        $('crm-op-valor').readOnly = false;
        $('crm-op-valor-ayuda').classList.add('d-none');
        $('crm-op-etapa').disabled = false;
        ['crm-op-tab-actividades', 'crm-op-tab-historial'].forEach((t) => $(t).classList.add('disabled'));
        bootstrap.Tab.getOrCreateInstance(document.querySelector('[data-bs-target="#crm-pane-datos"]')).show();
        opLlenarSelects(null);
        taContacto.limpiar();
        if (prefill.contacto) taContacto.elegir(prefill.contacto);
        opEditable(cfg.perm.crear);
        modal('crmModalOportunidad').show();
        setTimeout(() => $('crm-op-titulo').focus(), 300);
    }

    async function opAbrir(id) {
        let d;
        try { d = await api('oportunidadAjax', { params: { id } }); }
        catch (e) { aviso('error', 'No se pudo abrir', e.message); return; }
        const op = d.oportunidad;
        st.op = op;
        $('crm-op-form').reset();
        $('crm-op-id').value = op.id;
        $('crm-op-token').value = '';
        $('crm-op-titulo-modal').textContent = `Oportunidad #${op.numero}`;
        const [est, cls] = ESTADOS[op.estado] || [op.estado, ''];
        const badge = $('crm-op-badge-estado');
        badge.className = `badge ms-2 ${cls}`;
        badge.textContent = est;
        $('crm-op-titulo').value = op.titulo;
        $('crm-op-valor').value = op.valor_estimado;
        $('crm-op-cierre').value = op.fecha_cierre_estimada || '';
        $('crm-op-descripcion').value = op.descripcion || '';
        opLlenarSelects(op);
        taContacto.elegir({ id: op.id_contacto, nombre: op.contacto, empresa_contacto: op.empresa_contacto, telefono: op.contacto_telefono, instagram_usuario: op.contacto_instagram });

        const abierta = op.estado === 'abierta';
        const conProforma = !!op.id_proforma;
        $('crm-op-valor').readOnly = conProforma;
        $('crm-op-valor-ayuda').classList.toggle('d-none', !conProforma);

        $('crm-op-acciones').classList.remove('d-none');
        $('crm-op-btn-proforma').classList.toggle('d-none', !(abierta && !conProforma && cfg.puedeProforma && cfg.perm.actualizar));
        $('crm-op-proforma-info').classList.toggle('d-none', !conProforma);
        $('crm-op-btn-ganar').classList.toggle('d-none', !(abierta && cfg.perm.actualizar));
        $('crm-op-btn-perder').classList.toggle('d-none', !(abierta && cfg.perm.actualizar));
        $('crm-op-btn-reabrir').classList.toggle('d-none', !(!abierta && cfg.perm.actualizar));
        $('crm-op-btn-eliminar')?.classList.remove('d-none');

        const info = $('crm-op-cierre-info');
        if (op.estado === 'ganada') {
            info.classList.remove('d-none');
            $('crm-op-cierre-texto').className = 'alert alert-success py-2 px-3 small mb-0';
            $('crm-op-cierre-texto').innerHTML = `<i class="bi bi-trophy me-1"></i>Ganada el ${fecha(op.cerrada_at, true)} · Cliente: <b>${esc(op.cliente || '-')}</b>`;
        } else if (op.estado === 'perdida') {
            info.classList.remove('d-none');
            $('crm-op-cierre-texto').className = 'alert alert-danger py-2 px-3 small mb-0';
            $('crm-op-cierre-texto').innerHTML = `<i class="bi bi-x-octagon me-1"></i>Perdida el ${fecha(op.cerrada_at, true)} · Motivo: <b>${esc(op.motivo_perdida || '-')}</b>${op.detalle_perdida ? ' · ' + esc(op.detalle_perdida) : ''}`;
        } else {
            info.classList.add('d-none');
        }

        ['crm-op-tab-actividades', 'crm-op-tab-historial'].forEach((t) => $(t).classList.remove('disabled'));
        pintarActividadesOp(op.actividades || []);
        $('crm-op-historial').innerHTML = (op.historial_etapas || []).map((h) => `<tr>
            <td>${fecha(h.fecha, true)}</td><td>${esc(h.etapa_anterior || '—')}</td><td>${esc(h.etapa_nueva)}</td><td>${esc(h.usuario || '')}</td></tr>`).join('')
            || '<tr><td colspan="4" class="text-muted">Sin movimientos.</td></tr>';

        const pie = $('crm-op-pie');
        pie.textContent = `Creada el ${fecha(op.created_at, true)}${op.creado_por ? ' por ' + op.creado_por : ''} · ${op.dias_en_etapa} día(s) en «${op.etapa}»`;
        pie.classList.remove('d-none');

        opEditable(abierta && cfg.perm.actualizar);
        if (!$('crmModalOportunidad').classList.contains('show')) modal('crmModalOportunidad').show();
    }

    function pintarActividadesOp(lista) {
        $('crm-op-actividades').innerHTML = lista.length ? lista.map((a) => actividadHtml(a, false)).join('')
            : '<div class="list-group-item text-muted small">Sin actividades. Agende la próxima llamada o reunión.</div>';
    }

    async function opGuardar() {
        if (st.guardandoOp) return;
        st.guardandoOp = true;
        const btn = $('crm-op-btn-guardar');
        btn.disabled = true;
        let creada = false;
        try {
            if (!$('crm-op-titulo').value.trim()) throw new Error('Escriba el título de la oportunidad.');
            if (!$('crm-op-id-contacto').value) throw new Error('Seleccione el contacto (o créelo con el botón de al lado).');
            const id = $('crm-op-id').value;
            const etapaAntes = st.op ? String(st.op.id_etapa) : null;
            const embudoAntes = st.op ? String(st.op.id_embudo) : null;
            const fd = new FormData($('crm-op-form'));
            // Los campos deshabilitados no viajan en el FormData: se agregan a mano.
            ['crm-op-embudo', 'crm-op-etapa'].forEach((i) => fd.set($(i).name, $(i).value));
            const r = await api('guardarOportunidadAjax', { post: fd });
            if (!id) {
                // Fijar el id ya: el siguiente clic actualiza en vez de crear otra.
                $('crm-op-id').value = r.id;
                creada = true;
            } else if (etapaAntes && embudoAntes === $('crm-op-embudo').value && etapaAntes !== $('crm-op-etapa').value) {
                await api('moverEtapaAjax', { post: { id, id_etapa: $('crm-op-etapa').value } });
            }
            toast(r.mensaje || 'Guardado.');
            await opAbrir(r.id || id);
            recargar();
        } catch (e) {
            aviso('error', 'No se pudo guardar', e.message);
        } finally {
            st.guardandoOp = false;
            btn.disabled = false;
            if (creada && !$('crm-op-id').value) btn.classList.add('d-none');
        }
    }

    async function opEliminar() {
        if (!st.op || st.accionEnCurso) return;
        if (!(await confirmar(`¿Eliminar la oportunidad #${st.op.numero} «${st.op.titulo}»?`, 'Sí, eliminar'))) return;
        st.accionEnCurso = true;
        try {
            await api('eliminarOportunidadAjax', { post: { id: st.op.id } });
            modal('crmModalOportunidad').hide();
            toast('Oportunidad eliminada.');
            recargar();
        } catch (e) { aviso('error', 'No se pudo eliminar', e.message); }
        finally { st.accionEnCurso = false; }
    }

    async function opReabrir() {
        if (!st.op || st.accionEnCurso) return;
        if (!(await confirmar('La oportunidad volverá a la última etapa abierta del embudo. ¿Continuar?', 'Sí, reabrir'))) return;
        st.accionEnCurso = true;
        try {
            await api('reabrirAjax', { post: { id: st.op.id } });
            toast('Oportunidad reabierta.');
            await opAbrir(st.op.id);
            recargar();
        } catch (e) { aviso('error', 'No se pudo reabrir', e.message); }
        finally { st.accionEnCurso = false; }
    }

    /** Cliente ya conocido de la oportunidad: el suyo o el de su contacto. */
    function clienteConocido(op) {
        if (op.id_cliente) return { id: op.id_cliente, nombre: op.cliente };
        if (op.contacto_id_cliente) return { id: op.contacto_id_cliente, nombre: op.contacto_cliente, identificacion: op.contacto_cliente_identificacion };
        return null;
    }

    function abrirElegirCliente(modo) {
        st.modoCliente = modo;
        const ganar = modo === 'ganar';
        $('crm-cli-titulo').innerHTML = ganar ? '<i class="bi bi-trophy text-success me-2"></i>Ganar oportunidad' : '<i class="bi bi-file-earmark-text text-primary me-2"></i>Crear proforma';
        $('crm-cli-ayuda').textContent = ganar
            ? 'Indique el cliente que compró. Si es nuevo, regístrelo con el botón de al lado: quedará enlazado al contacto.'
            : 'Indique el cliente al que se le hará la proforma. Si es nuevo, regístrelo con el botón de al lado.';
        $('crm-cli-btn-texto').textContent = ganar ? 'Marcar ganada' : 'Ir a la proforma';
        taCliente.limpiar();
        const c = clienteConocido(st.op);
        if (c) taCliente.elegir(c);
        modal('crmModalCliente').show();
    }

    function opGanar() { if (st.op) abrirElegirCliente('ganar'); }

    async function opCrearProforma() {
        if (!st.op) return;
        if (clienteConocido(st.op)) {
            await irAProforma(clienteConocido(st.op).id);
        } else {
            abrirElegirCliente('proforma');
        }
    }

    async function irAProforma(idCliente) {
        if (st.accionEnCurso) return;
        st.accionEnCurso = true;
        try {
            const r = await api('prepararProformaAjax', { post: { id: st.op.id, id_cliente: idCliente } });
            window.location.href = r.url;
        } catch (e) {
            aviso('error', 'No se pudo preparar la proforma', e.message);
        } finally { st.accionEnCurso = false; }
    }

    async function cliConfirmar() {
        const idCliente = $('crm-cli-id').value;
        if (!idCliente) { aviso('warning', 'Seleccione el cliente'); return; }
        if (st.modoCliente === 'proforma') { await irAProforma(idCliente); return; }
        if (st.accionEnCurso) return;
        st.accionEnCurso = true;
        $('crm-cli-btn-ok').disabled = true;
        try {
            const r = await api('ganarAjax', { post: { id: st.op.id, id_cliente: idCliente } });
            modal('crmModalCliente').hide();
            toast(r.mensaje || 'Oportunidad ganada.');
            await opAbrir(st.op.id);
            recargar();
        } catch (e) { aviso('error', 'No se pudo marcar como ganada', e.message); }
        finally { st.accionEnCurso = false; $('crm-cli-btn-ok').disabled = false; }
    }

    function cliNuevo() {
        if (typeof window.abrirModalClienteCrear !== 'function') { aviso('error', 'No se pudo abrir el formulario de cliente.'); return; }
        window.abrirModalClienteCrear();
        // Datos que ya se conocen del contacto, para no escribirlos dos veces.
        setTimeout(() => {
            const nombre = $('cliente_nombre');
            if (nombre && !nombre.value && st.op) nombre.value = st.op.contacto || '';
            const tel = $('cliente_telefono');
            if (tel && !tel.value && st.op) tel.value = st.op.contacto_telefono || '';
        }, 400);
    }

    function opPerder() {
        if (!st.op) return;
        opciones($('crm-perder-motivo'), st.datos.motivos, { vacio: '— Seleccione —' });
        $('crm-perder-detalle').value = '';
        modal('crmModalPerder').show();
    }

    async function perderConfirmar() {
        if (st.accionEnCurso) return;
        const motivo = $('crm-perder-motivo').value;
        if (!motivo) { aviso('warning', 'Seleccione el motivo'); return; }
        st.accionEnCurso = true;
        $('crm-perder-btn').disabled = true;
        try {
            await api('perderAjax', { post: { id: st.op.id, id_motivo_perdida: motivo, detalle_perdida: $('crm-perder-detalle').value } });
            modal('crmModalPerder').hide();
            toast('Oportunidad marcada como perdida.', 'info');
            await opAbrir(st.op.id);
            recargar();
        } catch (e) { aviso('error', 'No se pudo marcar como perdida', e.message); }
        finally { st.accionEnCurso = false; $('crm-perder-btn').disabled = false; }
    }

    // ── Actividades ──────────────────────────────────────────────────────────
    function actTipoCambiado() {
        $('crm-act-vence-wrap').classList.toggle('d-none', $('crm-act-tipo').value === 'nota');
    }

    function manana9() {
        const d = new Date();
        d.setDate(d.getDate() + 1);
        return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T09:00`;
    }

    function actNueva() {
        if (!st.op) return;
        $('crm-act-form').reset();
        $('crm-act-id').value = '';
        $('crm-act-oportunidad').value = st.op.id;
        $('crm-act-titulo-modal').textContent = 'Nueva actividad';
        $('crm-act-vence').value = manana9();
        opciones($('crm-act-responsable'), st.datos.usuarios, { seleccionado: st.op.id_responsable || cfg.idUsuario });
        $('crm-act-btn-eliminar')?.classList.add('d-none');
        actTipoCambiado();
        modal('crmModalActividad').show();
    }

    function actAbrir(a) {
        $('crm-act-form').reset();
        $('crm-act-id').value = a.id;
        $('crm-act-oportunidad').value = a.id_oportunidad || '';
        $('crm-act-titulo-modal').textContent = 'Actividad';
        $('crm-act-tipo').value = a.tipo;
        $('crm-act-titulo').value = a.titulo;
        $('crm-act-descripcion').value = a.descripcion || '';
        $('crm-act-vence').value = a.vence_at ? String(a.vence_at).replace(' ', 'T').slice(0, 16) : '';
        opciones($('crm-act-responsable'), st.datos.usuarios, { seleccionado: a.id_responsable || '' });
        $('crm-act-btn-eliminar')?.classList.remove('d-none');
        actTipoCambiado();
        modal('crmModalActividad').show();
    }

    async function refrescarTrasActividad() {
        if (st.op && $('crmModalOportunidad').classList.contains('show')) {
            try { const d = await api('oportunidadAjax', { params: { id: st.op.id } }); st.op = d.oportunidad; pintarActividadesOp(st.op.actividades || []); }
            catch (e) { /* el modal sigue con lo que tenía */ }
        }
        try { const d = await api('misActividadesAjax'); pintarBadgeVencidas(d.vencidas); } catch (e) { /* sin aviso */ }
        if (st.vista === 'actividades' || st.vista === 'kanban') recargar();
    }

    async function actGuardar() {
        if (st.guardandoAct) return;
        st.guardandoAct = true;
        const btn = $('crm-act-btn-guardar');
        btn.disabled = true;
        try {
            const r = await api('guardarActividadAjax', { post: new FormData($('crm-act-form')) });
            $('crm-act-id').value = r.id;
            modal('crmModalActividad').hide();
            toast(r.mensaje || 'Guardado.');
            await refrescarTrasActividad();
        } catch (e) { aviso('error', 'No se pudo guardar', e.message); }
        finally { st.guardandoAct = false; btn.disabled = false; }
    }

    async function actCompletar(id, completada) {
        try {
            await api('completarActividadAjax', { post: { id, completada: completada ? '1' : '0' } });
            document.dispatchEvent(new Event('cmg:contadores'));   // aviso del navbar
            await refrescarTrasActividad();
        } catch (e) { aviso('error', 'No se pudo actualizar', e.message); }
    }

    async function actEliminar() {
        const id = $('crm-act-id').value;
        if (!id || !(await confirmar('¿Eliminar esta actividad?', 'Sí, eliminar'))) return;
        try {
            await api('eliminarActividadAjax', { post: { id } });
            modal('crmModalActividad').hide();
            await refrescarTrasActividad();
        } catch (e) { aviso('error', 'No se pudo eliminar', e.message); }
    }

    // ── Contacto ─────────────────────────────────────────────────────────────
    function ctLlenarSelects(c) {
        opciones($('crm-ct-origen'), st.datos.origenes, { vacio: '— Sin origen —', seleccionado: c?.id_origen || '' });
        opciones($('crm-ct-responsable'), st.datos.usuarios, { vacio: '— Sin responsable —', seleccionado: c ? (c.id_responsable || '') : cfg.idUsuario });
    }

    function nuevoContacto() {
        $('crm-ct-form').reset();
        $('crm-ct-id').value = '';
        $('crm-ct-token').value = window.CMG_nuevoTokenGuardado ? CMG_nuevoTokenGuardado() : '';
        $('crm-ct-titulo-modal').textContent = 'Nuevo contacto';
        $('crm-ct-extra').classList.add('d-none');
        $('crm-ct-btn-eliminar')?.classList.add('d-none');
        $('crm-ct-btn-guardar').classList.remove('d-none');
        ctLlenarSelects(null);
        modal('crmModalContacto').show();
        setTimeout(() => $('crm-ct-nombre').focus(), 300);
    }

    async function ctAbrir(id) {
        let d;
        try { d = await api('contactoAjax', { params: { id } }); }
        catch (e) { aviso('error', 'No se pudo abrir', e.message); return; }
        const c = d.contacto;
        $('crm-ct-form').reset();
        $('crm-ct-id').value = c.id;
        $('crm-ct-token').value = '';
        $('crm-ct-titulo-modal').textContent = c.nombre;
        $('crm-ct-nombre').value = c.nombre;
        $('crm-ct-empresa').value = c.empresa_contacto || '';
        $('crm-ct-cargo').value = c.cargo || '';
        $('crm-ct-telefono').value = c.telefono || '';
        $('crm-ct-correo').value = c.correo || '';
        $('crm-ct-instagram').value = c.instagram_usuario || '';
        $('crm-ct-linkedin').value = c.linkedin_url || '';
        $('crm-ct-notas').value = c.notas || '';
        ctLlenarSelects(c);
        $('crm-ct-extra').classList.remove('d-none');
        $('crm-ct-cliente').innerHTML = c.cliente ? `<i class="bi bi-check-circle text-success me-1"></i>Cliente: <b>${esc(c.cliente)}</b> ${esc(c.cliente_identificacion || '')}` : 'Aún no es cliente.';
        $('crm-ct-oportunidades').innerHTML = (c.oportunidades || []).map((o) => {
            const [est, cls] = ESTADOS[o.estado] || [o.estado, ''];
            return `<a href="#" class="d-block text-decoration-none" onclick="event.preventDefault(); CRM.opAbrir(${o.id})">#${o.numero} ${esc(o.titulo)} · $${dinero(o.valor_estimado)} <span class="badge ${cls}">${est}</span></a>`;
        }).join('') || '<span class="text-muted">Sin oportunidades.</span>';
        $('crm-ct-btn-eliminar')?.classList.remove('d-none');
        $('crm-ct-btn-guardar').classList.toggle('d-none', !cfg.perm.actualizar);
        modal('crmModalContacto').show();
    }

    async function ctGuardar() {
        if (st.guardandoCt) return;
        st.guardandoCt = true;
        const btn = $('crm-ct-btn-guardar');
        btn.disabled = true;
        try {
            if (!$('crm-ct-nombre').value.trim()) throw new Error('Escriba el nombre del contacto.');
            const r = await api('guardarContactoAjax', { post: new FormData($('crm-ct-form')) });
            $('crm-ct-id').value = r.id;   // el siguiente clic actualiza
            const el = $('crmModalContacto');
            const sobreOtro = typeof window.CMG_modalSobreOtro === 'function' && window.CMG_modalSobreOtro(el);
            document.dispatchEvent(new CustomEvent('crmContactoGuardado', { detail: { ...r, nombre: $('crm-ct-nombre').value.trim() } }));
            if (r.aviso) await aviso('warning', 'Posible duplicado', r.aviso);
            if (sobreOtro && r.nuevo) {
                modal('crmModalContacto').hide();
                toast('Contacto creado y seleccionado.');
            } else {
                toast(r.mensaje || 'Guardado.');
                await ctAbrir(r.id);
            }
            if (st.vista === 'contactos') recargar();
        } catch (e) { aviso('error', 'No se pudo guardar', e.message); }
        finally { st.guardandoCt = false; btn.disabled = false; }
    }

    async function ctEliminar() {
        const id = $('crm-ct-id').value;
        if (!id || !(await confirmar('¿Eliminar este contacto?', 'Sí, eliminar'))) return;
        try {
            await api('eliminarContactoAjax', { post: { id } });
            modal('crmModalContacto').hide();
            toast('Contacto eliminado.');
            recargar();
        } catch (e) { aviso('error', 'No se pudo eliminar', e.message); }
    }

    // ── Crear al vuelo ───────────────────────────────────────────────────────
    /** Contacto creado desde el modal de la oportunidad: queda puesto en ella. */
    document.addEventListener('crmContactoGuardado', async (e) => {
        const d = e.detail || {};
        const opEl = $('crmModalOportunidad');
        if (!d.nuevo || !opEl.classList.contains('show') || $('crm-op-contacto').disabled) return;
        try {
            const r = await api('buscarContactosAjax', { params: { id: d.id } });
            if (r.data && r.data[0]) taContacto.elegir(r.data[0]);
            else aviso('info', 'Contacto creado', 'No aparece en su lista de contactos; selecciónelo a mano.');
        } catch (err) { /* el usuario puede buscarlo a mano */ }
    });

    /** Cliente creado desde Ganar / Crear proforma: queda elegido en el diálogo. */
    document.addEventListener('clienteGuardado', async (e) => {
        const d = e.detail || {};
        if (d.nuevo !== true || !$('crmModalCliente').classList.contains('show')) return;
        const id = d.id || d.data?.id;
        if (!id) return;
        try {
            const r = await api('buscarClientesAjax', { params: { id } });
            if (r.data && r.data[0]) taCliente.elegir(r.data[0]);
            else aviso('info', 'Cliente creado', 'Búsquelo en la lista para seleccionarlo.');
        } catch (err) { /* buscar a mano */ }
    });

    // ── Modales anidados ─────────────────────────────────────────────────────
    // app.css fija .modal en 5060 !important: cada modal que se abre encima de
    // otro sube 20 puntos (inline important) y su fondo queda justo debajo.
    document.addEventListener('show.bs.modal', (e) => {
        const abiertos = document.querySelectorAll('.modal.show').length;
        if (!abiertos) return;
        const z = 5060 + abiertos * 20;
        e.target.style.setProperty('z-index', String(z), 'important');
        setTimeout(() => {
            const fondos = document.querySelectorAll('.modal-backdrop');
            if (fondos.length) fondos[fondos.length - 1].style.setProperty('z-index', String(z - 5), 'important');
        }, 0);
    });
    document.addEventListener('hidden.bs.modal', (e) => {
        e.target.style.removeProperty('z-index');
        // Bootstrap quita modal-open del body al cerrar el de arriba aunque quede otro abierto.
        if (document.querySelectorAll('.modal.show').length) document.body.classList.add('modal-open');
    });

    // ── Filtros, orden, exportación ──────────────────────────────────────────
    function limpiarFiltros() {
        $('crm-f-estado').value = 'abierta';
        $('crm-f-responsable').value = '';
        $('crm-f-origen').value = '';
        $('crm-f-cierre-desde').value = '';
        $('crm-f-cierre-hasta').value = '';
        $('crm-f-buscar').value = '';
        st.page = 1;
        st.pageContactos = 1;
        recargar();
    }

    function exportar(tipo) {
        const qs = new URLSearchParams({ ...filtros(), orden: window.CMG_ordenParam(st.sorts) }).toString();
        const url = `${cfg.urlBase}/export${tipo}?${qs}`;
        if (typeof window.CMG_descargar === 'function') {
            window.CMG_descargar(url, { nombre: tipo === 'Pdf' ? 'PDF' : 'Excel' });
        } else {
            window.location.href = url;
        }
    }

    async function cambiarPorPagina(n) {
        await window.CMG_guardarVista(cfg.rutaModulo, { __por_pagina__: parseInt(n, 10) }, { reload: false });
        st.page = 1;
        st.pageContactos = 1;
        recargar();
    }

    // ── Arranque ─────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', async () => {
        if (!$('crm-kanban')) return;   // sin SQL aplicado no hay pantalla
        taContacto = typeahead({
            input: $('crm-op-contacto'), oculto: $('crm-op-id-contacto'), dd: $('crm-op-dd-contacto'),
            buscar: async (q) => (await api('buscarContactosAjax', { params: { q } })).data,
            etiqueta: etiquetaContacto,
        });
        taCliente = typeahead({
            input: $('crm-cli-buscar'), oculto: $('crm-cli-id'), dd: $('crm-cli-dd'),
            buscar: async (q) => (await api('buscarClientesAjax', { params: { q } })).data,
            etiqueta: etiquetaCliente,
        });

        document.querySelectorAll('[data-crm-vista]').forEach((b) => b.addEventListener('click', () => mostrarVista(b.dataset.crmVista)));
        let tBuscar = null;
        $('crm-f-buscar').addEventListener('input', () => { clearTimeout(tBuscar); tBuscar = setTimeout(() => { st.page = 1; st.pageContactos = 1; recargar(); }, 450); });
        ['crm-f-cierre-desde', 'crm-f-cierre-hasta'].forEach((i) => $(i).addEventListener('change', () => { st.page = 1; recargar(); }));

        // Orden de la Lista: una o varias columnas (Shift+clic), sin recargar la página.
        if (typeof window.CMG_initSort === 'function') {
            st.sorter = window.CMG_initSort(cfg.rutaModulo, (col, dir, sorts) => {
                st.sorts = sorts;
                cargarLista(1);
            }, { sorts: st.sorts, multi: true, container: '[data-crm-panel="lista"]', reload: false });
        }

        try {
            await cargarDatos();
        } catch (e) {
            aviso('error', 'No se pudo cargar el CRM', e.message);
            return;
        }
        let vista = 'kanban';
        try { vista = localStorage.getItem('crm_vista') || 'kanban'; } catch (e) { /* sin almacenamiento */ }
        if (!document.querySelector(`[data-crm-vista="${vista}"]`)) vista = 'kanban';
        mostrarVista(vista);
        if (vista !== 'actividades') {
            try { const d = await api('misActividadesAjax'); pintarBadgeVencidas(d.vencidas); } catch (e) { /* sin aviso */ }
        }
    });

    window.CRM = {
        recargar: () => { st.page = 1; st.pageContactos = 1; recargar(); },
        cambiarEmbudo: () => { st.page = 1; recargar(); },
        limpiarFiltros, exportar, cambiarPorPagina,
        irPagina: (p) => cargarLista(p),
        irPaginaContactos: (p) => cargarContactos(p),
        nuevaOportunidad: () => opNueva(),
        nuevoContacto,
        opAbrir, opGuardar, opEliminar, opReabrir, opGanar, opPerder, opCrearProforma,
        opLlenarEtapas: () => opLlenarEtapas(),
        cliConfirmar, cliNuevo, perderConfirmar,
        actNueva, actAbrir, actGuardar, actCompletar, actEliminar, actTipoCambiado,
        ctAbrir, ctGuardar, ctEliminar,
    };
})();
