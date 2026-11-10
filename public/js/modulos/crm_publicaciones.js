/**
 * Publicaciones en redes (modulos/crm_publicaciones), fase 3 del CRM:
 * Calendario, Lista, Biblioteca y el modal de la publicación.
 * Servidor: CrmPublicacionesController. Diseño: docs/diseno/crm-redes-fase3.md §4.
 *
 * Reglas del sistema que se respetan aquí:
 *  - Un guardado = un registro (CLAUDE.md §8): bandera «en curso» antes del primer
 *    await, token_guardado por publicación nueva y el id fijado apenas se crea.
 *  - Fechas en pantalla: d-m-Y H:i:s.
 */
(function () {
    'use strict';

    const cfg = window.PUB_CONFIG || {};
    const $ = (id) => document.getElementById(id);
    const esc = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const pad = (n) => String(n).padStart(2, '0');

    function fecha(s, conHora = true) {
        if (!s) return '-';
        const m = String(s).match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/);
        if (!m) return String(s);
        return `${m[3]}-${m[2]}-${m[1]}` + (conHora ? ` ${m[4] || '00'}:${m[5] || '00'}:${m[6] || '00'}` : '');
    }
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
        return (await Swal.fire({ icon: 'question', text, showCancelButton: true, confirmButtonText: boton, cancelButtonText: 'Cancelar' })).isConfirmed;
    }
    async function api(accion, { params = null, post = null, archivo = null } = {}) {
        let url = `${cfg.urlBase}/${accion}`;
        const opts = { headers: { 'X-Requested-With': 'XMLHttpRequest' } };
        if (params) url += '?' + new URLSearchParams(params).toString();
        if (post || archivo) {
            const fd = new FormData();
            Object.entries(post || {}).forEach(([k, v]) => {
                if (Array.isArray(v)) v.forEach((x) => fd.append(k + '[]', x));
                else fd.append(k, v ?? '');
            });
            if (archivo) fd.append('archivo', archivo);
            opts.method = 'POST';
            opts.body = fd;
        }
        const resp = await fetch(url, opts);
        let json = null;
        try { json = await resp.json(); } catch (e) { /* sin JSON */ }
        if (!json || json.ok === false) throw new Error((json && (json.error || json.mensaje)) || 'Error de conexión. Intente de nuevo.');
        return json;
    }
    const modal = (id) => bootstrap.Modal.getOrCreateInstance($(id));

    const ESTADO_CLASE = {
        borrador: 'bg-secondary', en_revision: 'bg-warning text-dark', aprobada: 'bg-info text-dark', programada: 'bg-primary',
        publicando: 'bg-primary', publicada: 'bg-success', parcial: 'bg-warning text-dark', con_error: 'bg-danger', cancelada: 'bg-light text-dark border',
    };
    const ESTADO_COLOR = {
        borrador: '#6c757d', en_revision: '#ffc107', aprobada: '#0dcaf0', programada: '#0d6efd', publicando: '#0d6efd',
        publicada: '#198754', parcial: '#fd7e14', con_error: '#dc3545', cancelada: '#adb5bd',
    };
    const DESTINO_ESTADO = {
        pendiente: ['Pendiente', 'bg-secondary'], preparando: ['Preparando', 'bg-primary'], publicando: ['Publicando', 'bg-primary'],
        publicada: ['Publicada', 'bg-success'], con_error: ['Con error', 'bg-danger'], cancelada: ['Cancelada', 'bg-light text-dark border'],
    };
    const FORMATOS = {
        instagram: [['imagen', 'Imagen'], ['carrusel', 'Carrusel'], ['reel', 'Reel']],
        linkedin_perfil: [['texto', 'Solo texto'], ['imagen', 'Con imágenes'], ['enlace', 'Enlace (tarjeta con link)']],
        linkedin_pagina: [['texto', 'Solo texto'], ['imagen', 'Con imágenes'], ['enlace', 'Enlace (tarjeta con link)']],
    };
    const EDITABLES = ['borrador', 'en_revision', 'aprobada'];
    const badge = (estado) => `<span class="badge ${ESTADO_CLASE[estado] || 'bg-secondary'}">${esc((cfg.estados || {})[estado] || estado)}</span>`;
    const iconoRed = (tipo) => tipo === 'instagram' ? '<i class="bi bi-instagram text-danger"></i>' : '<i class="bi bi-linkedin text-primary"></i>';
    const nombreCanal = (c) => (c.tipo === 'instagram' ? '@' : '') + (c.usuario || c.nombre || c.id);
    const urlMedio = (id) => `${cfg.urlBase}/medio?id=${id}`;

    const st = {
        datos: { canales: [], config: { requiere_aprobacion: false, max_publicaciones_dia: 25 } },
        vista: 'calendario', page: 1, sorts: cfg.sorts || [], sorter: null,
        mes: new Date(new Date().getFullYear(), new Date().getMonth(), 1),
        pub: null, medios: [], guardando: false, accionando: false,
        elegir: { seleccion: [], pagina: 1, items: [] }, refresco: null,
    };

    // ── Datos generales y resumen ────────────────────────────────────────────
    async function cargarDatos() {
        st.datos = await api('datosAjax');
    }
    async function cargarResumen() {
        try {
            const r = (await api('resumenAjax')).resumen;
            $('pub-k-programadas').textContent = r.programadas;
            $('pub-k-borradores').textContent = r.borradores;
            $('pub-k-revision').textContent = r.en_revision ? `(${r.en_revision} en revisión)` : '';
            $('pub-k-publicadas').textContent = r.publicadas_mes;
            $('pub-k-error').textContent = r.con_error;
        } catch (e) { /* sin indicadores */ }
    }
    function filtros() {
        return { estado: $('pub-f-estado').value, b: $('pub-f-buscar').value.trim() };
    }
    function recargar() {
        cargarResumen();
        if (st.vista === 'calendario') return cargarCalendario();
        if (st.vista === 'lista') return cargarLista(1);
        return cargarBiblioteca(1);
    }
    function limpiarFiltros() {
        $('pub-f-estado').value = '';
        $('pub-f-buscar').value = '';
        recargar();
    }
    function mostrarVista(v) {
        st.vista = v;
        document.querySelectorAll('[data-pub-vista]').forEach((b) => b.classList.toggle('active', b.dataset.pubVista === v));
        document.querySelectorAll('[data-pub-panel]').forEach((p) => p.classList.toggle('d-none', p.dataset.pubPanel !== v));
        try { localStorage.setItem('pub_vista', v); } catch (e) { /* sin almacenamiento */ }
        recargar();
    }

    // ── Calendario ───────────────────────────────────────────────────────────
    const iso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    async function cargarCalendario() {
        const primero = st.mes;
        const inicio = new Date(primero);
        inicio.setDate(1 - ((primero.getDay() + 6) % 7));   // lunes
        const fin = new Date(inicio);
        fin.setDate(inicio.getDate() + 42);
        $('pub-cal-titulo').textContent = primero.toLocaleDateString('es-EC', { month: 'long', year: 'numeric' });
        let rows = [];
        try { rows = (await api('calendarioAjax', { params: { desde: iso(inicio), hasta: iso(fin) } })).rows; }
        catch (e) { aviso('error', 'No se pudo cargar el calendario', e.message); }
        const f = filtros();
        if (f.estado) rows = rows.filter((r) => r.estado === f.estado);
        if (f.b) rows = rows.filter((r) => (r.titulo || '').toLowerCase().includes(f.b.toLowerCase()));
        const porDia = {};
        rows.forEach((r) => { const k = String(r.publicada_at || r.programada_at).slice(0, 10); (porDia[k] = porDia[k] || []).push(r); });
        const hoy = iso(new Date());
        let html = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'].map((d) => `<div class="pub-cal-cab">${d}</div>`).join('');
        for (let i = 0; i < 42; i++) {
            const d = new Date(inicio);
            d.setDate(inicio.getDate() + i);
            const k = iso(d);
            const items = (porDia[k] || []).map((r) => {
                const hora = String(r.publicada_at || r.programada_at).slice(11, 16);
                return `<a class="pub-cal-item" style="--pub-c:${ESTADO_COLOR[r.estado] || '#6c757d'}" title="${esc(r.titulo)} · ${esc((cfg.estados || {})[r.estado] || r.estado)}"
                           onclick="PUB.abrir(${r.id})">${hora} <span class="t">${esc(r.titulo)}</span></a>`;
            }).join('');
            html += `<div class="pub-cal-dia ${d.getMonth() !== primero.getMonth() ? 'fuera' : ''} ${k === hoy ? 'hoy' : ''}"
                          ${cfg.perm.crear && k >= hoy ? `ondblclick="PUB.nueva('${k}')" title="Doble clic: nueva publicación este día"` : ''}>
                        <span class="pub-cal-num">${d.getDate()}</span>${items}</div>`;
        }
        $('pub-cal').innerHTML = `<div class="pub-cal">${html}</div>`;
        $('pub-cal-leyenda').innerHTML = ['programada', 'publicada', 'parcial', 'con_error', 'borrador']
            .map((e) => `<span><span class="d-inline-block rounded-1 me-1" style="width:10px;height:10px;background:${ESTADO_COLOR[e]}"></span>${esc(cfg.estados[e])}</span>`).join('');
    }
    function mes(delta) {
        st.mes = delta === 0 ? new Date(new Date().getFullYear(), new Date().getMonth(), 1)
            : new Date(st.mes.getFullYear(), st.mes.getMonth() + delta, 1);
        cargarCalendario();
    }

    // ── Lista ────────────────────────────────────────────────────────────────
    async function cargarLista(page = 1) {
        const tbody = $('pub-lista-tbody');
        tbody.classList.add('opacity-50');
        try {
            const d = await api('listarAjax', { params: { ...filtros(), page } });
            st.page = d.page;
            tbody.innerHTML = d.rows.length ? d.rows.map((r) => `
                <tr class="pub-row" onclick="PUB.abrir(${r.id})">
                    <td class="ps-3 text-truncate" data-col="titulo" style="max-width:280px" title="${esc(r.titulo)}">
                        ${r.id_medio_portada ? `<img src="${urlMedio(r.id_medio_portada)}" alt="" style="width:28px;height:28px;object-fit:cover;border-radius:4px" class="me-2" loading="lazy">` : ''}${esc(r.titulo)}</td>
                    <td data-col="destinos" class="text-truncate" style="max-width:260px" title="${esc(r.destinos || '')}">${esc(r.destinos || '-')}</td>
                    <td data-col="estado" class="text-center">${badge(r.estado)}</td>
                    <td data-col="programada_at">${fecha(r.programada_at)}</td>
                    <td data-col="publicada_at">${fecha(r.publicada_at)}</td>
                    <td data-col="responsable">${esc(r.responsable || '-')}</td>
                    <td data-col="created_at">${fecha(r.created_at)}</td>
                </tr>`).join('')
                : '<tr><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-inbox fs-4 d-block mb-1"></i>No hay publicaciones con estos filtros.</td></tr>';
            const desde = d.total ? (d.page - 1) * d.perPage + 1 : 0;
            $('pub-lista-info').textContent = `${desde}-${Math.min(d.page * d.perPage, d.total)}/${d.total}`;
            $('pub-lista-paginacion').innerHTML = paginador(d.page, d.totalPages, 'PUB.irPagina');
            if (st.sorter) st.sorter.refreshIcons();
        } catch (e) {
            aviso('error', 'No se pudo cargar la lista', e.message);
        } finally {
            tbody.classList.remove('opacity-50');
        }
    }
    function paginador(page, total, fn) {
        return `<button type="button" class="btn btn-outline-secondary" ${page <= 1 ? 'disabled' : ''} onclick="${fn}(${page - 1})"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" ${page >= total ? 'disabled' : ''} onclick="${fn}(${page + 1})"><i class="bi bi-chevron-right"></i></button>`;
    }
    function exportar(tipo) {
        const url = `${cfg.urlBase}/export${tipo}?${new URLSearchParams(filtros()).toString()}`;
        if (typeof window.CMG_descargar === 'function') window.CMG_descargar(url, { nombre: tipo === 'Pdf' ? 'PDF' : 'Excel' });
        else window.location.href = url;
    }
    async function cambiarPorPagina(n) {
        await window.CMG_guardarVista(cfg.rutaModulo, { __por_pagina__: parseInt(n, 10) }, { reload: false });
        cargarLista(1);
    }

    // ── Biblioteca ───────────────────────────────────────────────────────────
    function tarjetaMedio(m, extra = '') {
        const vista = m.tipo === 'video'
            ? `<video src="${urlMedio(m.id)}" muted preload="metadata"></video>`
            : `<img src="${urlMedio(m.id)}" alt="${esc(m.texto_alternativo || '')}" loading="lazy">`;
        const dato = m.tipo === 'video' ? `${Number(m.duracion_seg || 0).toFixed(1)} s` : `${m.ancho || '?'}×${m.alto || '?'}`;
        return `${vista}
            <span class="badge bg-dark bg-opacity-75 pub-medio-tipo"><i class="bi ${m.tipo === 'video' ? 'bi-camera-reels' : 'bi-image'}"></i></span>${extra}
            <div class="pub-medio-pie" title="${esc(m.nombre_original || '')}">${dato} · ${esc(m.nombre_original || '')}</div>`;
    }
    async function cargarBiblioteca(page = 1) {
        let d;
        try { d = await api('mediosAjax', { params: { tipo: $('pub-b-tipo').value, page } }); }
        catch (e) { aviso('error', 'No se pudo cargar la biblioteca', e.message); return; }
        st.biblioteca = d.rows;
        $('pub-b-grilla').innerHTML = d.rows.length ? d.rows.map((m) => `<div class="pub-medio" onclick="PUB.abrirMedio(${m.id})">${tarjetaMedio(m)}</div>`).join('')
            : '<div class="text-muted small">Todavía no hay archivos. Use «Subir».</div>';
        $('pub-b-info').textContent = `${d.total} archivo(s)`;
        $('pub-b-paginacion').innerHTML = d.totalPages > 1 ? paginador(d.page, d.totalPages, 'PUB.cargarBiblioteca') : '';
    }

    /** Sube los archivos de uno en uno (la conversión a JPEG ocurre en el servidor). */
    async function subir(input, enElegir = false) {
        const archivos = [...(input.files || [])];
        input.value = '';
        if (!archivos.length || st.subiendo) return;
        st.subiendo = true;
        const caja = $(enElegir ? 'pub-e-subiendo' : 'pub-b-subiendo');
        const nuevos = [];
        try {
            for (let i = 0; i < archivos.length; i++) {
                const f = archivos[i];
                caja.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span>Subiendo ${i + 1} de ${archivos.length}: ${esc(f.name)}…`;
                try {
                    const r = await api('subirMedioAjax', { archivo: f });
                    nuevos.push(r.id);
                    (r.avisos || []).forEach((a) => toast(a, 'info'));
                } catch (e) {
                    await aviso('error', `No se subió «${f.name}»`, e.message);
                }
            }
        } finally {
            caja.innerHTML = '';
            st.subiendo = false;
        }
        if (nuevos.length) toast(`${nuevos.length} archivo(s) subido(s).`);
        if (enElegir) {
            st.elegir.seleccion.push(...nuevos.filter((id) => !st.elegir.seleccion.includes(id)));
            await cargarElegir(1);
        } else {
            cargarBiblioteca(1);
        }
    }

    function abrirMedio(id) {
        const m = (st.biblioteca || []).find((x) => +x.id === +id);
        if (!m) return;
        $('pub-md-id').value = id;
        $('pub-md-vista').innerHTML = m.tipo === 'video'
            ? `<video src="${urlMedio(id)}" controls style="max-width:100%;max-height:320px"></video>`
            : `<img src="${urlMedio(id)}" alt="" style="max-width:100%;max-height:320px">`;
        $('pub-md-datos').textContent = `${m.nombre_original || ''} · ${m.tipo === 'video' ? Number(m.duracion_seg || 0).toFixed(1) + ' s' : m.ancho + '×' + m.alto + ' px'}`
            + ` · ${(m.bytes / 1048576).toFixed(2)} MB · subido el ${fecha(m.created_at)}`;
        $('pub-md-alt').value = m.texto_alternativo || '';
        $('pub-md-ia').checked = m.es_ia === true || m.es_ia === 't';
        modal('pubModalMedio').show();
    }
    async function guardarMedio() {
        if (st.accionando) return;
        st.accionando = true;
        try {
            const r = await api('guardarMedioAjax', { post: { id: $('pub-md-id').value, texto_alternativo: $('pub-md-alt').value, es_ia: $('pub-md-ia').checked ? '1' : '' } });
            toast(r.mensaje);
            modal('pubModalMedio').hide();
            cargarBiblioteca(1);
        } catch (e) { aviso('error', 'No se pudo guardar', e.message); }
        finally { st.accionando = false; }
    }
    async function eliminarMedio() {
        if (!(await confirmar('¿Eliminar este archivo de la biblioteca?', 'Sí, eliminar'))) return;
        try {
            const r = await api('eliminarMedioAjax', { post: { id: $('pub-md-id').value } });
            toast(r.mensaje, 'info');
            modal('pubModalMedio').hide();
            cargarBiblioteca(1);
        } catch (e) { aviso('error', 'No se pudo eliminar', e.message); }
    }

    // ── Elegir medios para la publicación ────────────────────────────────────
    async function elegirMedios() {
        if (!editable()) return;
        st.elegir = { seleccion: st.medios.map((m) => +m.id), pagina: 1, items: [] };
        $('pub-e-grilla').innerHTML = '';
        modal('pubModalElegir').show();
        await cargarElegir(1);
    }
    async function cargarElegir(page) {
        const d = await api('mediosAjax', { params: { page } });
        st.elegir.items = page === 1 ? d.rows : st.elegir.items.concat(d.rows);
        st.elegir.pagina = d.page;
        $('pub-e-mas').classList.toggle('d-none', d.page >= d.totalPages);
        pintarElegir();
    }
    function pintarElegir() {
        $('pub-e-grilla').innerHTML = st.elegir.items.map((m) => {
            const n = st.elegir.seleccion.indexOf(+m.id);
            return `<div class="pub-medio ${n >= 0 ? 'elegido' : ''}" onclick="PUB.alternarElegido(${m.id})">
                ${tarjetaMedio(m, n >= 0 ? `<span class="badge bg-primary pub-medio-orden">${n + 1}</span>` : '')}</div>`;
        }).join('') || '<div class="text-muted small">La biblioteca está vacía. Use «Subir».</div>';
    }
    function alternarElegido(id) {
        const i = st.elegir.seleccion.indexOf(+id);
        if (i >= 0) st.elegir.seleccion.splice(i, 1);
        else if (st.elegir.seleccion.length >= 20) { aviso('info', 'Máximo 20 archivos', 'Una publicación lleva hasta 20 archivos.'); return; }
        else st.elegir.seleccion.push(+id);
        pintarElegir();
    }
    function confirmarElegidos() {
        const porId = {};
        st.elegir.items.concat(st.medios).forEach((m) => { porId[+m.id] = m; });
        st.medios = st.elegir.seleccion.map((id) => porId[id]).filter(Boolean);
        pintarMedios();
        modal('pubModalElegir').hide();
    }

    // ── Modal de la publicación ──────────────────────────────────────────────
    const editable = () => !st.pub || EDITABLES.includes(st.pub.estado);

    function nueva(dia) {
        st.pub = null;
        st.medios = [];
        $('pub-form').reset();
        $('pub-m-id').value = '';
        $('pub-m-token').value = window.CMG_nuevoTokenGuardado ? CMG_nuevoTokenGuardado() : '';
        $('pub-m-titulo').textContent = 'Nueva publicación';
        $('pub-m-fecha').value = dia ? `${dia}T09:00` : '';
        pintarTodo();
        bootstrap.Tab.getOrCreateInstance(document.querySelector('[data-bs-target="#pub-pane-contenido"]')).show();
        modal('pubModal').show();
        setTimeout(() => $('pub-m-nombre').focus(), 300);
    }

    async function abrir(id, mantenerPestana = false) {
        let d;
        try { d = await api('publicacionAjax', { params: { id } }); }
        catch (e) { aviso('error', 'No se pudo abrir', e.message); return; }
        const p = d.publicacion;
        st.pub = p;
        st.medios = p.medios || [];
        $('pub-m-id').value = p.id;
        $('pub-m-token').value = '';
        $('pub-m-titulo').textContent = p.titulo;
        $('pub-m-nombre').value = p.titulo;
        $('pub-m-texto').value = p.texto || '';
        $('pub-m-ia').checked = p.es_contenido_ia === true || p.es_contenido_ia === 't';
        $('pub-m-fecha').value = p.programada_at && p.estado === 'programada' ? String(p.programada_at).slice(0, 16).replace(' ', 'T') : '';
        pintarTodo();
        if (!mantenerPestana) bootstrap.Tab.getOrCreateInstance(document.querySelector('[data-bs-target="#pub-pane-contenido"]')).show();
        modal('pubModal').show();
        programarRefresco();
    }

    function pintarTodo() {
        const p = st.pub;
        const ed = editable();
        $('pub-m-estado').outerHTML = `<span class="badge ms-2 ${p ? ESTADO_CLASE[p.estado] : 'bg-secondary'}" id="pub-m-estado">${p ? esc(cfg.estados[p.estado]) : 'Nueva'}</span>`;
        ['pub-m-nombre', 'pub-m-texto', 'pub-m-ia'].forEach((i) => { $(i).disabled = !ed || !(p ? cfg.perm.actualizar : cfg.perm.crear); });
        pintarMedios();
        pintarDestinos();
        pintarBotones();
        pintarResultados();
        contar();
        if ($('pub-ia-btn-abrir')) $('pub-ia-btn-abrir').classList.toggle('d-none', !ed);
        if (!p && $('pub-ia-panel')) { st.propuesta = null; $('pub-ia-panel').classList.add('d-none'); $('pub-ia-resultado').innerHTML = ''; $('pub-ia-indicaciones').value = ''; }
        $('pub-m-pie').innerHTML = p ? `Creada por ${esc(p.creada_por || '-')} el ${fecha(p.created_at)}`
            + (p.aprobada_por_nombre ? ` · aprobada por ${esc(p.aprobada_por_nombre)} el ${fecha(p.aprobada_at)}` : '') : '';
    }

    function pintarMedios() {
        const ed = editable();
        $('pub-m-medios').innerHTML = st.medios.map((m, i) => `
            <div class="pub-sel-item">
                ${m.tipo === 'video' ? `<video src="${urlMedio(m.id)}" muted preload="metadata"></video>` : `<img src="${urlMedio(m.id)}" alt="" loading="lazy">`}
                <div class="d-flex justify-content-between align-items-center px-1">
                    <span>${i + 1}${m.tipo === 'video' ? ' <i class="bi bi-camera-reels"></i>' : ''}</span>
                    ${ed ? `<span>
                        <button type="button" class="btn btn-link" ${i === 0 ? 'disabled' : ''} onclick="PUB.moverMedio(${i},-1)" title="Antes"><i class="bi bi-chevron-left"></i></button>
                        <button type="button" class="btn btn-link" ${i === st.medios.length - 1 ? 'disabled' : ''} onclick="PUB.moverMedio(${i},1)" title="Después"><i class="bi bi-chevron-right"></i></button>
                        <button type="button" class="btn btn-link text-danger" onclick="PUB.quitarMedio(${i})" title="Quitar"><i class="bi bi-x-lg"></i></button>
                    </span>` : ''}
                </div>
            </div>`).join('') || '<div class="small text-muted">Sin imágenes ni videos (LinkedIn admite publicaciones de solo texto).</div>';
    }
    function moverMedio(i, d) {
        const j = i + d;
        if (j < 0 || j >= st.medios.length) return;
        [st.medios[i], st.medios[j]] = [st.medios[j], st.medios[i]];
        pintarMedios();
    }
    function quitarMedio(i) {
        st.medios.splice(i, 1);
        pintarMedios();
    }

    function formatoSugerido(c) {
        const vids = st.medios.filter((m) => m.tipo === 'video').length;
        if (c.tipo === 'instagram') return vids === 1 && st.medios.length === 1 ? 'reel' : (st.medios.length > 1 ? 'carrusel' : 'imagen');
        return st.medios.length ? 'imagen' : 'texto';
    }

    function pintarDestinos() {
        const ed = editable() && (st.pub ? cfg.perm.actualizar : cfg.perm.crear);
        const elegidos = {};
        (st.pub ? st.pub.destinos : []).forEach((d) => { elegidos[d.id_canal] = d; });
        const canales = (st.datos.canales || []).filter((c) => c.estado !== 'desconectado' || elegidos[c.id]);
        $('pub-m-destinos').innerHTML = canales.length ? canales.map((c) => {
            const d = elegidos[c.id];
            const fmt = d ? d.formato : formatoSugerido(c);
            const alerta = c.estado !== 'activo' ? 'La cuenta está desconectada o con el acceso vencido: reconéctela en Configuración del CRM.'
                : (!c.puede_publicar ? 'La cuenta no dio permiso para publicar: reconéctela en Configuración del CRM y acepte los permisos nuevos.' : '');
            return `<div class="border rounded-3 p-2 bg-white" data-destino="${c.id}" data-tipo="${esc(c.tipo)}">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="pub-d-${c.id}" ${d ? 'checked' : ''} ${ed ? '' : 'disabled'} onchange="PUB.contar()">
                        <label class="form-check-label fw-bold small" for="pub-d-${c.id}">${iconoRed(c.tipo)} ${esc(nombreCanal(c))}
                            <span class="text-muted fw-normal">${c.tipo === 'linkedin_pagina' ? '· página' : (c.tipo === 'linkedin_perfil' ? '· perfil' : '')}</span></label>
                    </div>
                    <select class="form-select form-select-sm" style="width:190px;" data-campo="formato" ${ed ? '' : 'disabled'} onchange="PUB.cambioFormato(this)">
                        ${formatosDe(c.tipo, fmt).map(([v, t]) => `<option value="${v}" ${fmt === v ? 'selected' : ''}>${t}</option>`).join('')}
                    </select>
                    <button type="button" class="btn btn-link btn-sm" onclick="this.closest('[data-destino]').querySelector('[data-campo=texto-wrap]').classList.toggle('d-none')">
                        <i class="bi bi-pencil me-1"></i>Texto propio${d && d.texto ? ' ✓' : ''}</button>
                    ${d && d.estado !== 'pendiente' ? `<span class="badge ${(DESTINO_ESTADO[d.estado] || [])[1] || 'bg-secondary'} ms-auto">${(DESTINO_ESTADO[d.estado] || [d.estado])[0]}</span>` : ''}
                </div>
                ${alerta ? `<div class="small text-warning-emphasis mt-1"><i class="bi bi-exclamation-triangle me-1"></i>${esc(alerta)}</div>` : ''}
                ${c.tipo === 'instagram' ? '' : `<div class="row g-2 mt-1 ${fmt === 'enlace' ? '' : 'd-none'}" data-campo="link-wrap">
                    <div class="col-md-7">
                        <label class="form-label small fw-bold mb-1 d-block">Dirección del enlace <span class="text-danger">*</span></label>
                        <input type="url" class="form-control form-control-sm" data-campo="link-url" maxlength="2000" ${ed ? '' : 'disabled'}
                               placeholder="https://www.miempresa.com/oferta" value="${esc(d ? d.link_url || '' : '')}" oninput="PUB.contar()">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-bold mb-1 d-block">Título de la tarjeta <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" data-campo="link-titulo" maxlength="200" ${ed ? '' : 'disabled'}
                               placeholder="Lo que se lee bajo la imagen" value="${esc(d ? d.link_titulo || '' : '')}">
                    </div>
                    <div class="col-12 small text-muted">Sale como una tarjeta: la primera imagen de «Contenido» y el título llevan a esa dirección. LinkedIn usa una sola imagen en este formato.</div>
                </div>`}
                <div class="mt-2 ${d && d.texto ? '' : 'd-none'}" data-campo="texto-wrap">
                    <textarea class="form-control form-control-sm" rows="3" data-campo="texto" maxlength="3000" ${ed ? '' : 'disabled'} oninput="PUB.contar()"
                              placeholder="Vacío = el texto de «Contenido»">${esc(d ? d.texto || '' : '')}</textarea>
                </div>
            </div>`;
        }).join('') : '<div class="small text-muted">No hay cuentas conectadas. Conecte Instagram o LinkedIn en Configuración del CRM → Canales.</div>';
    }

    /** Formatos de la red; «Enlace» solo si el servidor ya lo admite (o si el destino ya lo tiene). */
    function formatosDe(tipo, actual) {
        const admite = !!(st.datos.config && st.datos.config.admite_enlace);
        return (FORMATOS[tipo] || []).filter(([v]) => v !== 'enlace' || admite || actual === 'enlace');
    }
    function cambioFormato(sel) {
        const wrap = sel.closest('[data-destino]').querySelector('[data-campo="link-wrap"]');
        if (wrap) {
            wrap.classList.toggle('d-none', sel.value !== 'enlace');
            if (sel.value === 'enlace') wrap.querySelector('[data-campo="link-url"]').focus();
        }
    }

    function leerDestinos() {
        return [...document.querySelectorAll('#pub-m-destinos [data-destino]')].filter((el) => el.querySelector('.form-check-input').checked).map((el) => {
            const formato = el.querySelector('[data-campo="formato"]').value;
            const campo = (c) => { const x = el.querySelector(`[data-campo="${c}"]`); return x ? x.value.trim() : ''; };
            return {
                id_canal: +el.dataset.destino,
                formato,
                texto: el.querySelector('[data-campo="texto"]').value.trim(),
                link_url: formato === 'enlace' ? campo('link-url') : '',
                link_titulo: formato === 'enlace' ? campo('link-titulo') : '',
                tipo: el.dataset.tipo,
            };
        });
    }

    function contar() {
        const base = $('pub-m-texto').value;
        const partes = leerDestinos().map((d) => {
            const t = d.texto || base;
            if (d.tipo === 'instagram') {
                const tags = (t.match(/#[\p{L}\p{N}_]+/gu) || []).length;
                const mal = t.length > 2200 || tags > 30;
                return `<span class="${mal ? 'text-danger fw-bold' : ''}">${iconoRed('instagram')} ${t.length}/2200 · ${tags}/30 #</span>`;
            }
            return `<span class="${t.length > 3000 ? 'text-danger fw-bold' : ''}">${iconoRed('linkedin')} ${t.length}/3000</span>`;
        });
        $('pub-m-contador').innerHTML = partes.join('') || `<span>${base.length} caracteres</span>`;
    }

    function pintarBotones() {
        const p = st.pub;
        const estado = p ? p.estado : 'nueva';
        const exige = !!(st.datos.config && st.datos.config.requiere_aprobacion);
        const ver = (id, si) => $(id).classList.toggle('d-none', !si);
        const puedeProgramar = cfg.perm.crear && (estado === 'nueva' || (estado === 'borrador' && !exige) || estado === 'aprobada');
        ver('pub-m-programar-box', puedeProgramar);
        ver('pub-m-btn-revision', cfg.perm.crear && exige && ['nueva', 'borrador'].includes(estado));
        ver('pub-m-btn-aprobar', cfg.perm.actualizar && estado === 'en_revision');
        ver('pub-m-btn-devolver', cfg.perm.actualizar && ['en_revision', 'aprobada'].includes(estado));
        ver('pub-m-btn-desprogramar', cfg.perm.crear && estado === 'programada');
        ver('pub-m-btn-cancelar', cfg.perm.crear && ['programada', 'publicando', 'parcial', 'con_error'].includes(estado)
            && (p.destinos || []).some((d) => ['pendiente', 'preparando', 'con_error'].includes(d.estado)));
        if ($('pub-m-btn-eliminar')) ver('pub-m-btn-eliminar', !!p);
        ver('pub-m-btn-guardar', EDITABLES.concat(['nueva']).includes(estado) && (p ? cfg.perm.actualizar : cfg.perm.crear));
        $('pub-m-btn-programar').querySelector('span').textContent = $('pub-m-fecha').value ? 'Programar' : 'Publicar ahora';
        $('pub-m-ayuda').textContent = estado === 'programada' ? `Sale el ${fecha(p.programada_at)}.`
            : (exige && ['nueva', 'borrador'].includes(estado) ? 'La empresa exige aprobación antes de publicar.' : '');
    }

    function pintarResultados() {
        const p = st.pub;
        if (!p || !(p.destinos || []).length) {
            $('pub-m-resultados').innerHTML = '<div class="small text-muted">Aquí se verá el estado de cada red cuando la publicación salga.</div>';
            return;
        }
        $('pub-m-resultados').innerHTML = `<div class="table-responsive"><table class="table table-sm align-middle mb-0">
            <thead><tr><th>Cuenta</th><th>Formato</th><th>Estado</th><th>Publicada</th><th>Enlace</th><th></th></tr></thead><tbody>
            ${p.destinos.map((d) => {
                const [et, cls] = DESTINO_ESTADO[d.estado] || [d.estado, 'bg-secondary'];
                return `<tr>
                    <td class="small">${iconoRed(d.canal_tipo)} ${esc(d.canal_tipo === 'instagram' ? '@' + (d.canal_usuario || '') : (d.canal_nombre || d.canal_usuario || ''))}</td>
                    <td class="small">${esc(d.formato)}</td>
                    <td><span class="badge ${cls}">${et}</span>${d.error ? `<div class="small ${d.estado === 'con_error' ? 'text-danger' : 'text-muted'}" style="max-width:360px;white-space:normal">${esc(d.error)}</div>` : ''}</td>
                    <td class="small">${fecha(d.publicada_at)}</td>
                    <td class="small">${d.enlace ? `<a href="${esc(d.enlace)}" target="_blank" rel="noopener">Ver <i class="bi bi-box-arrow-up-right"></i></a>` : '-'}</td>
                    <td>${d.estado === 'con_error' && cfg.perm.crear ? `<button type="button" class="btn btn-outline-primary btn-sm" onclick="PUB.reintentar(${d.id})"><i class="bi bi-arrow-repeat me-1"></i>Reintentar</button>` : ''}</td>
                </tr>`;
            }).join('')}</tbody></table></div>${metricasHtml(p)}`;
    }

    const CORTE = { '24h': 'a las 24 h', '7d': 'a los 7 días', '30d': 'a los 30 días' };
    const num = (v) => (v === null || v === undefined || v === '') ? '-' : Number(v).toLocaleString('es-EC');
    const dinero = (n) => '$' + Number(n || 0).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    /** Métricas del último corte y lo que trajo al CRM, por cada red publicada. */
    function metricasHtml(p) {
        const res = p.resultados || {};
        const pubs = (p.destinos || []).filter((d) => d.estado === 'publicada');
        if (!pubs.length) return '';
        return pubs.map((d) => {
            const r = res[d.id] || {};
            const u = r.ultimo;
            const a = r.atribucion || {};
            const err = (r.cortes || []).filter((c) => c.con_error === true || c.con_error === 't').map((c) => c.error).filter(Boolean);
            const sinDatos = d.canal_tipo === 'linkedin_perfil';
            const celdas = [['Alcance', u && u.alcance], ['Vistas', u && u.vistas], ['Me gusta', u && u.me_gusta], ['Comentarios', u && u.comentarios],
                ['Guardados', u && u.guardados], ['Compartidos', u && u.compartidos], ['Clics', u && u.clics]]
                .filter(([, v]) => v !== null && v !== undefined);
            return `<div class="border rounded-3 p-2 mt-3">
                <div class="fw-bold small mb-2">${iconoRed(d.canal_tipo)} ${esc(d.canal_tipo === 'instagram' ? '@' + (d.canal_usuario || '') : (d.canal_nombre || ''))}
                    ${u ? `<span class="text-muted fw-normal">· estadísticas ${CORTE[u.corte] || ''} (${fecha(u.tomada_at)})</span>` : ''}</div>
                ${sinDatos ? '<div class="small text-muted">LinkedIn no entrega estadísticas de perfiles personales (solo de páginas de empresa).</div>'
                    : (u && celdas.length ? `<div class="d-flex flex-wrap gap-3 mb-2">${celdas.map(([t, v]) => `<div><div class="fw-bold">${num(v)}</div><div class="small text-muted">${t}</div></div>`).join('')}</div>`
                        : `<div class="small text-muted mb-2">Las estadísticas se toman a las 24 horas, a los 7 y a los 30 días de publicada.${err.length ? ' Último intento: ' + esc(err[err.length - 1]) : ''}</div>`)}
                <div class="small">${d.canal_tipo === 'instagram'
                    ? `<i class="bi bi-funnel me-1 text-primary"></i>Trajo al CRM: <b>${a.conversaciones || 0}</b> conversación(es) por comentarios ·
                       <b>${a.contactos_nuevos || 0}</b> contacto(s) nuevo(s) · <b>${a.oportunidades || 0}</b> oportunidad(es)
                       ${a.ganadas ? `· <b class="text-success">${a.ganadas} ganada(s) por ${dinero(a.valor_ganado)}</b>` : ''}`
                    : '<span class="text-muted">LinkedIn no envía al sistema los comentarios: no hay atribución automática.</span>'}</div>
            </div>`;
        }).join('');
    }

    /** Mientras algo se está publicando, el modal se actualiza solo cada 15 s. */
    function programarRefresco() {
        clearTimeout(st.refresco);
        if (!st.pub || !['programada', 'publicando'].includes(st.pub.estado)) return;
        if (st.pub.estado === 'programada' && new Date(String(st.pub.programada_at).replace(' ', 'T')) > new Date(Date.now() + 60000)) return;
        st.refresco = setTimeout(async () => {
            if (!$('pubModal').classList.contains('show') || !st.pub) return;
            await abrir(st.pub.id, true);
            recargar();
        }, 15000);
    }

    // ── Guardar y acciones ───────────────────────────────────────────────────
    async function guardar(silencioso = false) {
        if (st.guardando) return false;
        st.guardando = true;
        const btn = $('pub-m-btn-guardar');
        btn.disabled = true;
        try {
            if (!$('pub-m-nombre').value.trim()) throw new Error('Escriba el nombre interno de la publicación.');
            const r = await api('guardarAjax', { post: {
                id: $('pub-m-id').value, titulo: $('pub-m-nombre').value, texto: $('pub-m-texto').value,
                es_contenido_ia: $('pub-m-ia').checked ? '1' : '0', medios: st.medios.map((m) => m.id),
                destinos: JSON.stringify(leerDestinos().map(({ id_canal, formato, texto, link_url, link_titulo }) => ({ id_canal, formato, texto, link_url, link_titulo }))),
                token_guardado: $('pub-m-token').value,
            } });
            $('pub-m-id').value = r.id;   // el siguiente clic actualiza, no crea otra
            if (!silencioso) toast(r.mensaje);
            await abrir(r.id, true);
            recargar();
            return true;
        } catch (e) {
            aviso('error', 'No se pudo guardar', e.message);
            return false;
        } finally {
            st.guardando = false;
            btn.disabled = false;
        }
    }

    async function accion(a) {
        if (st.accionando) return;
        st.accionando = true;
        try {
            // Lo editable se guarda antes, para no perder cambios.
            if (editable() && (st.pub ? cfg.perm.actualizar : cfg.perm.crear)) {
                if (!(await guardar(true))) return;
            }
            if (!st.pub) return;
            if (a === 'programar' && !$('pub-m-fecha').value
                && !(await confirmar('Se publicará en el próximo minuto en las cuentas elegidas. ¿Continuar?', 'Sí, publicar'))) return;
            if (a === 'cancelar' && !(await confirmar('Se cancelará lo que aún no salió. Lo ya publicado sigue en la red. ¿Continuar?', 'Sí, cancelar'))) return;
            const fechaVal = $('pub-m-fecha').value;
            const r = await api('accionAjax', { post: { id: st.pub.id, accion: a, fecha: fechaVal } });
            toast(r.mensaje);
            await abrir(st.pub.id, true);
            recargar();
        } catch (e) {
            aviso('error', 'No se pudo completar', e.message);
        } finally {
            st.accionando = false;
        }
    }

    async function reintentar(idDestino) {
        if (st.accionando) return;
        st.accionando = true;
        try {
            const r = await api('accionAjax', { post: { id: st.pub.id, accion: 'reintentar', id_destino: idDestino } });
            toast(r.mensaje);
            await abrir(st.pub.id, true);
            recargar();
        } catch (e) { aviso('error', 'No se pudo reintentar', e.message); }
        finally { st.accionando = false; }
    }

    async function eliminar() {
        if (!st.pub || !(await confirmar(`¿Eliminar «${st.pub.titulo}»? Lo que ya salió en las redes no se borra de allí.`, 'Sí, eliminar'))) return;
        try {
            const r = await api('eliminarAjax', { post: { id: st.pub.id } });
            modal('pubModal').hide();
            await aviso('success', 'Eliminada', r.mensaje);
            recargar();
        } catch (e) { aviso('error', 'No se pudo eliminar', e.message); }
    }

    // ── Redacción con IA ─────────────────────────────────────────────────────
    function panelIa() {
        if (!editable()) return;
        $('pub-ia-panel').classList.toggle('d-none');
        if (!$('pub-ia-panel').classList.contains('d-none')) setTimeout(() => $('pub-ia-indicaciones').focus(), 50);
    }

    async function proponerIa() {
        if (st.proponiendo) return;
        const destinos = leerDestinos();
        const redes = [...new Set(destinos.map((d) => (d.tipo === 'instagram' ? 'instagram' : 'linkedin')))];
        st.proponiendo = true;
        const btn = $('pub-ia-btn');
        btn.disabled = true;
        $('pub-ia-resultado').innerHTML = '<div class="small text-muted"><span class="spinner-border spinner-border-sm me-1"></span>Redactando…</div>';
        try {
            const r = await api('proponerIaAjax', { post: {
                id: $('pub-m-id').value, indicaciones: $('pub-ia-indicaciones').value,
                texto_actual: $('pub-ia-mejorar').checked ? $('pub-m-texto').value : '',
                redes: redes.length ? redes : ['instagram', 'linkedin'],
                medios: st.medios.filter((m) => m.tipo === 'imagen').map((m) => m.id), alt: $('pub-ia-alt').checked ? '1' : '',
            } });
            st.propuesta = r.propuesta;
            pintarPropuesta();
        } catch (e) {
            $('pub-ia-resultado').innerHTML = '';
            aviso('error', 'No se pudo proponer', e.message);
        } finally {
            st.proponiendo = false;
            btn.disabled = false;
        }
    }

    function pintarPropuesta() {
        const p = st.propuesta;
        if (!p) return;
        // Cada propuesta es editable: lo que se corrige aquí es lo que lleva «Usar».
        const bloque = (clave, titulo, icono) => (p[clave] === undefined || p[clave] === null || (p[clave] === '' && !p['_editada_' + clave])) ? '' : `
            <div class="border rounded bg-white p-2 mb-2">
                <div class="d-flex align-items-center mb-1"><span class="small fw-bold">${icono} ${titulo}</span>
                    <span class="small text-muted ms-2" data-ia-cuenta="${clave}"></span>
                    <button type="button" class="btn btn-outline-primary btn-sm ms-auto py-0" onclick="PUB.usarIa('${clave}')">Usar</button></div>
                <textarea class="form-control form-control-sm pub-prev-texto" data-ia-texto="${clave}" maxlength="${clave === 'instagram' ? 2200 : 3000}"
                          rows="${Math.min(12, Math.max(3, Math.ceil(String(p[clave]).length / 90) + (String(p[clave]).match(/\n/g) || []).length))}"
                          oninput="PUB.editarIa('${clave}', this.value)" title="Puede corregir el texto antes de usarlo">${esc(p[clave])}</textarea>
            </div>`;
        const alts = (p.alt_textos || []).map((a) => `<li><b>#${st.medios.findIndex((m) => +m.id === +a.id_medio) + 1}</b> ${esc(a.texto)}</li>`).join('');
        $('pub-ia-resultado').innerHTML = (p.aviso ? `<div class="small text-warning-emphasis mb-1"><i class="bi bi-exclamation-triangle me-1"></i>${esc(p.aviso)}</div>` : '')
            + bloque('texto_base', 'Texto general', '<i class="bi bi-card-text"></i>')
            + bloque('instagram', 'Instagram', iconoRed('instagram'))
            + bloque('linkedin', 'LinkedIn', iconoRed('linkedin'))
            + (alts ? `<div class="border rounded bg-white p-2"><div class="d-flex align-items-center mb-1"><span class="small fw-bold"><i class="bi bi-universal-access"></i> Textos alternativos</span>
                ${cfg.perm.actualizar ? '<button type="button" class="btn btn-outline-primary btn-sm ms-auto py-0" onclick="PUB.usarIa(\'alt\')">Aplicar a las imágenes</button>' : ''}</div>
                <ol class="small mb-0 ps-3">${alts}</ol></div>` : '');
        ['texto_base', 'instagram', 'linkedin'].forEach(contarIa);
    }

    /** El texto propuesto se corrige en su propio cuadro; «Usar» lleva lo corregido. */
    function editarIa(clave, valor) {
        if (!st.propuesta) return;
        st.propuesta[clave] = valor;
        st.propuesta['_editada_' + clave] = true;
        contarIa(clave);
    }
    function contarIa(clave) {
        const el = document.querySelector(`[data-ia-cuenta="${clave}"]`);
        if (!el || !st.propuesta) return;
        const t = String(st.propuesta[clave] ?? '');
        if (clave === 'instagram') {
            const tags = (t.match(/#[\p{L}\p{N}_]+/gu) || []).length;
            el.textContent = `${t.length}/2200 · ${tags}/30 #`;
            el.classList.toggle('text-danger', tags > 30);
        } else {
            el.textContent = `${t.length}/3000`;
        }
    }

    /** Lleva la propuesta al formulario (no guarda: hay que pulsar Guardar). */
    async function usarIa(clave) {
        const p = st.propuesta;
        if (!p) return;
        if (['texto_base', 'instagram', 'linkedin'].includes(clave) && !String(p[clave] ?? '').trim()) {
            aviso('info', 'El texto está vacío', 'Escriba algo en el cuadro o pida otra propuesta.');
            return;
        }
        if (clave === 'texto_base') {
            $('pub-m-texto').value = p.texto_base;
        } else if (clave === 'instagram' || clave === 'linkedin') {
            const filas = [...document.querySelectorAll('#pub-m-destinos [data-destino]')].filter((el) =>
                el.querySelector('.form-check-input').checked && ((el.dataset.tipo === 'instagram') === (clave === 'instagram')));
            if (!filas.length) {
                $('pub-m-texto').value = p[clave];   // sin cuentas de esa red elegidas: va al texto general
            } else {
                filas.forEach((el) => {
                    el.querySelector('[data-campo="texto"]').value = p[clave];
                    el.querySelector('[data-campo="texto-wrap"]').classList.remove('d-none');
                });
                toast(`Puesto como texto propio de ${clave === 'instagram' ? 'Instagram' : 'LinkedIn'} (pestaña «Dónde»).`, 'info');
            }
        } else if (clave === 'alt') {
            let n = 0;
            for (const a of p.alt_textos || []) {
                const m = st.medios.find((x) => +x.id === +a.id_medio);
                try {
                    await api('guardarMedioAjax', { post: { id: a.id_medio, texto_alternativo: a.texto, es_ia: m && (m.es_ia === true || m.es_ia === 't') ? '1' : '' } });
                    if (m) m.texto_alternativo = a.texto;
                    n++;
                } catch (e) { aviso('error', 'No se aplicó un texto alternativo', e.message); break; }
            }
            if (n) toast(`${n} texto(s) alternativo(s) guardado(s) en la biblioteca.`);
            return;
        }
        contar();
        toast('Listo. Revíselo y pulse Guardar.', 'info');
    }

    // ── Vista previa ─────────────────────────────────────────────────────────
    function vistaPrevia() {
        const base = $('pub-m-texto').value;
        const destinos = leerDestinos();
        const canales = Object.fromEntries((st.datos.canales || []).map((c) => [c.id, c]));
        const m0 = st.medios[0];
        const medio = (m) => !m ? '' : (m.tipo === 'video' ? `<video src="${urlMedio(m.id)}" muted controls></video>` : `<img src="${urlMedio(m.id)}" alt="">`);
        $('pub-m-previa').innerHTML = destinos.map((d) => {
            const c = canales[d.id_canal] || {};
            const texto = d.texto || base;
            if (d.tipo === 'instagram') {
                const corto = texto.length > 125 ? texto.slice(0, 125) + '… más' : texto;
                return `<div class="pub-prev-ig">
                    <div class="p-2 small fw-bold"><i class="bi bi-person-circle me-1"></i>${esc(c.usuario || '')}</div>
                    <div class="marco ${d.formato === 'reel' ? 'reel' : ''}">${medio(m0) || '<div class="h-100 d-flex align-items-center justify-content-center text-muted small">Sin imagen</div>'}</div>
                    ${d.formato === 'carrusel' ? `<div class="text-center small text-muted">1 de ${st.medios.length} ●○○</div>` : ''}
                    <div class="p-2 pub-prev-texto"><b>${esc(c.usuario || '')}</b> ${esc(corto)}</div>
                </div>`;
            }
            const imgs = d.formato === 'imagen' ? st.medios.filter((m) => m.tipo === 'imagen').slice(0, 4) : [];
            const cab = `<div class="p-2 small fw-bold"><i class="bi bi-linkedin text-primary me-1"></i>${esc(c.nombre || c.usuario || '')}</div>
                <div class="px-2 pb-2 pub-prev-texto">${esc(texto.length > 210 ? texto.slice(0, 210) + '… ver más' : texto)}</div>`;
            if (d.formato === 'enlace') {
                const img = st.medios.find((m) => m.tipo === 'imagen');
                let host = '';
                try { host = new URL(d.link_url).hostname.replace(/^www\./, ''); } catch (e) { /* dirección incompleta */ }
                return `<div class="pub-prev-li">${cab}
                    <div class="border-top bg-light">
                        ${img ? `<img src="${urlMedio(img.id)}" alt="" style="width:100%;aspect-ratio:1.91/1;object-fit:cover">` : ''}
                        <div class="p-2"><div class="small fw-bold">${esc(d.link_titulo || '(falta el título de la tarjeta)')}</div>
                            <div class="small text-muted"><i class="bi bi-link-45deg"></i> ${esc(host || '(falta la dirección del enlace)')}</div></div>
                    </div>
                </div>`;
            }
            return `<div class="pub-prev-li">
                ${cab}
                ${imgs.length ? `<div class="rejilla" style="grid-template-columns:repeat(${Math.min(2, imgs.length)}, 1fr)">${imgs.map((m) => `<img src="${urlMedio(m.id)}" alt="">`).join('')}</div>` : ''}
            </div>`;
        }).join('') || '<div class="small text-muted">Elija dónde publicar en la pestaña «Dónde».</div>';
    }

    // ── Configuración ────────────────────────────────────────────────────────
    function abrirConfig() {
        const c = st.datos.config || {};
        $('pub-c-aprobacion').checked = !!c.requiere_aprobacion;
        $('pub-c-tope').value = c.max_publicaciones_dia || 25;
        modal('pubModalConfig').show();
    }
    async function guardarConfig() {
        if (st.accionando) return;
        st.accionando = true;
        try {
            const r = await api('guardarConfigAjax', { post: { requiere_aprobacion: $('pub-c-aprobacion').checked ? '1' : '', max_publicaciones_dia: $('pub-c-tope').value } });
            toast(r.mensaje);
            modal('pubModalConfig').hide();
            await cargarDatos();
        } catch (e) { aviso('error', 'No se pudo guardar', e.message); }
        finally { st.accionando = false; }
    }

    // ── Arranque ─────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', async () => {
        if (!$('pub-cal')) return;
        document.querySelectorAll('[data-pub-vista]').forEach((b) => b.addEventListener('click', () => mostrarVista(b.dataset.pubVista)));
        let t = null;
        $('pub-f-buscar').addEventListener('input', () => { clearTimeout(t); t = setTimeout(recargar, 450); });
        $('pub-m-fecha').addEventListener('input', () => pintarBotones());
        $('pubModal').addEventListener('hidden.bs.modal', () => clearTimeout(st.refresco));
        if (typeof window.CMG_initSort === 'function') {
            st.sorter = window.CMG_initSort(cfg.rutaModulo, (col, dir, sorts) => { st.sorts = sorts; cargarLista(1); },
                { sorts: st.sorts, multi: true, container: '[data-pub-panel="lista"]', reload: false });
        }
        try { await cargarDatos(); } catch (e) { aviso('error', 'No se pudo cargar Publicaciones', e.message); return; }
        let vista = 'calendario';
        try { vista = localStorage.getItem('pub_vista') || 'calendario'; } catch (e) { /* sin almacenamiento */ }
        mostrarVista(document.querySelector(`[data-pub-vista="${vista}"]`) ? vista : 'calendario');
    });

    window.PUB = {
        recargar, limpiarFiltros, mes, exportar, cambiarPorPagina,
        irPagina: (p) => cargarLista(p),
        cargarBiblioteca, subir, abrirMedio, guardarMedio, eliminarMedio,
        elegirMedios, alternarElegido, confirmarElegidos, masElegir: () => cargarElegir(st.elegir.pagina + 1),
        nueva, abrir, guardar: () => guardar(false), accion, reintentar, eliminar,
        moverMedio, quitarMedio, contar, vistaPrevia, abrirConfig, guardarConfig,
        panelIa, proponerIa, usarIa, editarIa, cambioFormato,
    };
})();
