/**
 * Bandeja social (modulos/bandeja_social): conversaciones de Instagram.
 * Servidor: BandejaSocialController. Diseño: docs/diseno/crm-redes-fase1.md §9.2.
 *
 * - Sondeo cada 10 s (se pausa con la pestaña oculta): lista + mensajes nuevos
 *   de la conversación abierta (desde el último id ya pintado).
 * - Envío con bandera «en curso» antes del primer await (un clic = un mensaje).
 * - Fechas d-m-Y H:i:s. Todo texto que viene de Instagram se escapa.
 */
(function () {
    'use strict';

    const cfg = window.BS_CONFIG || {};
    const $ = (id) => document.getElementById(id);
    const esc = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const SONDEO_MS = 10000;

    function fecha(s, corta) {
        const m = String(s || '').match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/);
        if (!m) return '';
        if (corta) {
            const hoy = new Date();
            const esHoy = +m[1] === hoy.getFullYear() && +m[2] === hoy.getMonth() + 1 && +m[3] === hoy.getDate();
            return esHoy ? `${m[4]}:${m[5]}` : `${m[3]}-${m[2]}`;
        }
        return `${m[3]}-${m[2]}-${m[1]} ${m[4] || '00'}:${m[5] || '00'}:${m[6] || '00'}`;
    }
    function aviso(icon, title, text) {
        if (window.Swal) return Swal.fire({ icon, title, text });
        alert(title + (text ? '\n' + text : ''));
        return Promise.resolve();
    }
    function toast(text, icon = 'success') {
        if (window.Swal) Swal.fire({ toast: true, position: 'top-end', icon, title: text, showConfirmButton: false, timer: 2500 });
    }

    async function api(accion, { params = null, post = null } = {}) {
        let url = `${cfg.urlBase}/${accion}`;
        const opts = { headers: { 'X-Requested-With': 'XMLHttpRequest' } };
        if (params) url += '?' + new URLSearchParams(params).toString();
        if (post) {
            const fd = new FormData();
            Object.entries(post).forEach(([k, v]) => fd.append(k, v ?? ''));
            opts.method = 'POST';
            opts.body = fd;
        }
        const resp = await fetch(url, opts);
        let json = null;
        try { json = await resp.json(); } catch (e) { /* sin JSON */ }
        if (!json || json.ok === false) throw new Error((json && (json.error || json.mensaje)) || 'Error de conexión. Intente de nuevo.');
        return json;
    }

    const st = {
        alcance: 'todas',
        conv: null,          // conversación abierta (datos del servidor)
        ultimoId: 0,         // último mensaje pintado
        enviando: false,
        accion: false,
        timer: null,
        rows: [],
    };

    // ── Lista ────────────────────────────────────────────────────────────────
    function filtros() {
        return { alcance: st.alcance, tipo: $('bs-f-tipo').value, estado: $('bs-f-estado').value, b: $('bs-f-buscar').value.trim(),
                 bot: $('bs-f-bot') ? $('bs-f-bot').value : '' };
    }

    async function cargarLista() {
        const d = await api('listarAjax', { params: filtros() });
        st.rows = d.rows;
        const total = $('bs-total-sin-leer');
        total.textContent = d.sin_leer;
        total.classList.toggle('d-none', !d.sin_leer);
        $('bs-lista').innerHTML = d.rows.length ? d.rows.map(itemHtml).join('')
            : '<div class="bs-vacio p-4 small">No hay conversaciones.</div>';
    }

    function itemHtml(c) {
        const icono = c.tipo === 'dm' ? 'bi-instagram text-danger' : 'bi-chat-square-text text-primary';
        const activa = st.conv && +st.conv.id === +c.id;
        const vencida = c.tipo === 'dm' && c.ventana && !c.ventana.puede;
        return `<div class="bs-item ${activa ? 'activa' : ''} ${+c.sin_leer > 0 ? 'no-leida' : ''}" onclick="BS.abrir(${c.id})">
            <div class="d-flex justify-content-between gap-2">
                <span class="bs-nombre"><i class="bi ${icono} me-1"></i>${esc(c.contacto || 'Sin contacto')}</span>
                <span class="small text-muted text-nowrap">${fecha(c.ultimo_mensaje_at, true)}</span>
            </div>
            <div class="d-flex justify-content-between gap-2 align-items-center">
                <span class="bs-ultimo">${esc(c.ultimo_mensaje || '')}</span>
                ${+c.sin_leer > 0 ? `<span class="badge rounded-pill bg-danger">${c.sin_leer}</span>` : ''}
            </div>
            <div class="small text-muted text-truncate">${c.responsable ? '<i class="bi bi-person me-1"></i>' + esc(c.responsable) : '<span class="text-warning-emphasis">Sin asignar</span>'}
                ${vencida ? ' · <span title="Pasaron más de 24 h desde su último mensaje">ventana cerrada</span>' : ''}
                ${c.estado === 'cerrada' ? ' · cerrada' : ''}
                ${c.bot_estado === 'traspasada' ? ' · <span class="text-warning-emphasis" title="El asistente la pasó a una persona"><i class="bi bi-person-raised-hand"></i> del asistente</span>' : ''}</div>
        </div>`;
    }

    // ── Conversación ─────────────────────────────────────────────────────────
    async function abrir(id) {
        let d;
        try { d = await api('conversacionAjax', { params: { id } }); }
        catch (e) { aviso('error', 'No se pudo abrir', e.message); return; }
        const teniaSinLeer = st.rows.some((r) => +r.id === +id && +r.sin_leer > 0);
        st.conv = d.conversacion;
        st.ultimoId = 0;
        if (teniaSinLeer) document.dispatchEvent(new Event('cmg:contadores'));   // aviso del navbar
        $('bs-mensajes').innerHTML = '';
        pintarCabecera();
        agregarMensajes(d.mensajes);
        pintarComposer();
        pintarPanel(d.oportunidades || []);
        $('bandeja').classList.add('ver-chat');
        $('bandeja').classList.remove('ver-panel');
        document.querySelectorAll('.bs-item').forEach((el) => el.classList.remove('activa'));
        cargarLista().catch(() => {});
        setTimeout(() => $('bs-texto') && $('bs-texto').focus(), 100);
    }

    function pintarCabecera() {
        const c = st.conv;
        $('bs-chat-nombre').innerHTML = `${c.tipo === 'dm' ? '<i class="bi bi-instagram text-danger me-1"></i>' : '<i class="bi bi-chat-square-text text-primary me-1"></i>'}${esc(c.contacto || 'Sin contacto')}`;
        const partes = [];
        if (c.instagram_usuario) partes.push('@' + esc(c.instagram_usuario));
        partes.push(c.tipo === 'dm' ? 'Mensaje directo' : 'Comentarios de una publicación');
        partes.push('en @' + esc(c.canal_usuario));
        $('bs-chat-sub').innerHTML = partes.join(' · ');
    }

    const ETIQUETA_TIPO = {
        comment: '<i class="bi bi-chat-square-text me-1"></i>Comentario',
        comment_reply: '<i class="bi bi-reply me-1"></i>Respuesta pública',
        private_reply: '<i class="bi bi-envelope me-1"></i>Respuesta privada',
        story_mention: '<i class="bi bi-at me-1"></i>Te mencionó en su historia',
        story_reply: '<i class="bi bi-reply me-1"></i>Respondió a tu historia',
        share: '<i class="bi bi-share me-1"></i>Publicación compartida',
        ig_reel: '<i class="bi bi-film me-1"></i>Reel compartido',
    };

    function adjuntoHtml(m) {
        if (!m.adjunto_ruta) return '';
        const url = `${cfg.urlBase}/adjunto?id=${m.id}`;
        const ext = String(m.adjunto_ruta).split('.').pop().toLowerCase();
        if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) return `<a href="${url}" target="_blank" rel="noopener"><img src="${url}" loading="lazy" alt="Imagen"></a>`;
        if (['mp4', 'mov', 'webm'].includes(ext)) return `<video src="${url}" controls preload="metadata"></video>`;
        if (['mp3', 'm4a', 'aac', 'ogg', 'wav'].includes(ext)) return `<audio src="${url}" controls preload="none"></audio>`;
        return `<a href="${url}" target="_blank" rel="noopener"><i class="bi bi-paperclip me-1"></i>Archivo</a>`;
    }

    function estadoHtml(m) {
        if (m.direccion !== 'OUT') return '';
        switch (m.estado_envio) {
            case 'enviando': return ' <span class="spinner-border spinner-border-sm" style="width:.6rem;height:.6rem"></span>';
            case 'leido':    return ' <i class="bi bi-check2-all text-primary" title="Leído"></i>';
            case 'fallido':  return ` <span class="text-danger" title="${esc(m.error || '')}"><i class="bi bi-exclamation-circle"></i> No se envió</span>
                ${cfg.perm.crear ? `<button type="button" class="btn btn-link btn-sm p-0 ms-1" onclick="BS.reintentar(${m.id})">Reintentar</button>` : ''}`;
            default:         return ' <i class="bi bi-check2" title="Enviado"></i>';
        }
    }

    function mensajeHtml(m) {
        const lado = m.direccion === 'OUT' ? 'out' : 'in';
        if (m.eliminado === true || m.eliminado === 't') {
            return `<div class="bs-msg ${lado} fst-italic text-muted" data-id="${m.id}">Mensaje eliminado por el cliente
                <div class="bs-meta">${fecha(m.fecha)}</div></div>`;
        }
        const etiqueta = ETIQUETA_TIPO[m.tipo] ? `<div class="small text-muted mb-1">${ETIQUETA_TIPO[m.tipo]}</div>` : '';
        const quien = m.direccion !== 'OUT' ? ''
            : m.autor === 'bot' ? '<i class="bi bi-robot me-1"></i>Asistente'
            : (m.usuario ? esc(m.usuario) : 'Desde Instagram');
        return `<div class="bs-msg ${lado} ${m.estado_envio === 'fallido' ? 'fallido' : ''}" data-id="${m.id}">
            ${etiqueta}${adjuntoHtml(m)}${m.texto ? esc(m.texto) : (m.adjunto_ruta ? '' : '<span class="text-muted">[Contenido no disponible]</span>')}
            <div class="bs-meta">${quien ? quien + ' · ' : ''}${fecha(m.fecha)}${estadoHtml(m)}</div>
            ${m.estado_envio === 'fallido' && m.error ? `<div class="small text-danger">${esc(m.error)}</div>` : ''}
        </div>`;
    }

    function agregarMensajes(lista) {
        const cont = $('bs-mensajes');
        const alFondo = cont.scrollHeight - cont.scrollTop - cont.clientHeight < 80;
        lista.forEach((m) => {
            const previo = cont.querySelector(`.bs-msg[data-id="${m.id}"]`);
            if (previo) previo.outerHTML = mensajeHtml(m);
            else cont.insertAdjacentHTML('beforeend', mensajeHtml(m));
            st.ultimoId = Math.max(st.ultimoId, +m.id);
        });
        if (!cont.children.length) cont.innerHTML = '<div class="bs-vacio mt-5 small">Sin mensajes.</div>';
        if (alFondo || lista.length) cont.scrollTop = cont.scrollHeight;
    }

    function horas(seg) {
        const h = Math.floor(seg / 3600);
        const mi = Math.floor((seg % 3600) / 60);
        return h >= 24 ? `${Math.floor(h / 24)} día(s) ${h % 24} h` : `${h} h ${mi} min`;
    }

    function pintarComposer() {
        const c = st.conv;
        const comp = $('bs-composer');
        comp.classList.remove('d-none');
        const texto = $('bs-texto');
        const btn = $('bs-btn-enviar');
        let puede = cfg.perm.crear && c.canal_estado === 'activo';
        let aviso = '';
        $('bs-modo-comentario').classList.toggle('d-none', c.tipo !== 'comentario');
        if (c.canal_estado !== 'activo') {
            aviso = '<span class="text-danger"><i class="bi bi-plug me-1"></i>La cuenta de Instagram está desconectada o con el acceso vencido.</span>';
        } else if (c.tipo === 'dm' && c.ventana) {
            if (c.ventana.puede) {
                aviso = `<span class="text-success"><i class="bi bi-clock me-1"></i>Puede responder durante ${horas(c.ventana.segundos_restantes)} más${c.ventana.tag ? ' (ventana Human Agent: solo respuestas escritas por una persona)' : ''}.</span>`;
            } else {
                puede = false;
                aviso = `<span class="text-warning-emphasis"><i class="bi bi-lock me-1"></i>${esc(c.ventana.motivo)}</span>`;
            }
        } else if (c.tipo === 'comentario') {
            const priv = $('bs-modo-privada');
            priv.disabled = !c.respuesta_privada_disponible;
            if (priv.disabled && priv.checked) $('bs-modo-publica').checked = true;
            aviso = `<span class="text-muted"><i class="bi bi-info-circle me-1"></i>La respuesta pública se ve bajo el comentario.${c.respuesta_privada_disponible ? ' La privada llega por mensaje directo (una sola vez).' : ' La respuesta privada ya no está disponible.'}</span>`;
        }
        $('bs-ventana').innerHTML = aviso;
        texto.disabled = !puede;
        if (btn) btn.disabled = !puede;
        // Imágenes: solo por mensaje directo (Instagram no las admite en respuestas a comentarios).
        const bImg = $('bs-btn-imagen');
        if (bImg) {
            bImg.classList.toggle('d-none', c.tipo !== 'dm');
            bImg.disabled = !puede;
        }
    }

    async function enviarImagen(input) {
        const archivo = input.files && input.files[0];
        input.value = '';   // permite volver a elegir el mismo archivo
        if (!archivo || st.enviando || !st.conv || st.conv.tipo !== 'dm') return;
        if (!['image/jpeg', 'image/png', 'image/gif'].includes(archivo.type)) {
            aviso('warning', 'Formato no admitido', 'Solo se pueden enviar imágenes JPG, PNG o GIF.');
            return;
        }
        if (archivo.size > 8 * 1024 * 1024) {
            aviso('warning', 'Imagen muy grande', 'Instagram acepta imágenes de hasta 8 MB.');
            return;
        }
        st.enviando = true;
        const btns = [$('bs-btn-imagen'), $('bs-btn-enviar')].filter(Boolean);
        btns.forEach((b) => { b.disabled = true; });
        try {
            const r = await api('enviarImagenAjax', { post: { id: st.conv.id, imagen: archivo } });
            agregarMensajes([r.mensaje]);
            if (!r.enviado) aviso('error', 'Instagram no aceptó la imagen', r.error_envio || '');
            await refrescarConversacion();
        } catch (e) {
            aviso('error', 'No se pudo enviar la imagen', e.message);
        } finally {
            st.enviando = false;
            btns.forEach((b) => { b.disabled = $('bs-texto').disabled; });
        }
    }

    function pintarPanel(oportunidades) {
        const c = st.conv;
        const opciones = cfg.usuarios.map((u) => `<option value="${u.id}" ${+c.id_responsable === u.id ? 'selected' : ''}>${esc(u.nombre)}</option>`).join('');
        const ops = oportunidades.map((o) => `<a class="d-block small text-decoration-none" href="${cfg.urlCrm}" title="Abrir el CRM">#${o.numero} ${esc(o.titulo)} · ${esc(o.etapa)}${o.estado !== 'abierta' ? ' (' + esc(o.estado) + ')' : ''}</a>`).join('')
            || '<div class="small text-muted">Sin oportunidades.</div>';
        $('bs-panel').innerHTML = `
            <div class="fw-bold">${esc(c.contacto || 'Sin contacto')}</div>
            ${c.instagram_usuario ? `<div class="small"><a href="https://www.instagram.com/${encodeURIComponent(c.instagram_usuario)}/" target="_blank" rel="noopener">@${esc(c.instagram_usuario)}</a></div>` : ''}
            <div class="small text-muted mb-2">${c.id_cliente ? '<i class="bi bi-check-circle text-success me-1"></i>Ya es cliente' : 'Aún no es cliente'}</div>
            ${panelBot(c)}

            <label class="form-label small fw-bold mb-1">Responsable</label>
            <div class="d-flex gap-1 mb-2">
                <select class="form-select form-select-sm" id="bs-p-resp" ${cfg.perm.actualizar ? '' : 'disabled'}>
                    <option value="">Sin asignar</option>${opciones}</select>
                ${cfg.perm.actualizar ? '<button type="button" class="btn btn-outline-primary btn-sm" onclick="BS.asignar()" title="Guardar"><i class="bi bi-check2"></i></button>' : ''}
            </div>
            ${cfg.perm.actualizar && +c.id_responsable !== cfg.idUsuario ? '<button type="button" class="btn btn-outline-primary btn-sm w-100 mb-2" onclick="BS.asignar(\'yo\')"><i class="bi bi-person-check me-1"></i>Asignármela</button>' : ''}
            ${cfg.perm.actualizar ? `<button type="button" class="btn btn-outline-secondary btn-sm w-100 mb-3" onclick="BS.estado('${c.estado === 'cerrada' ? 'abierta' : 'cerrada'}')">
                <i class="bi ${c.estado === 'cerrada' ? 'bi-arrow-counterclockwise' : 'bi-check2-circle'} me-1"></i>${c.estado === 'cerrada' ? 'Reabrir conversación' : 'Cerrar conversación'}</button>` : ''}

            <div class="fw-bold small mb-1">Oportunidades</div>
            ${ops}
            ${cfg.puedeOportunidad && c.id_contacto ? '<button type="button" class="btn btn-primary btn-sm w-100 mt-2" onclick="BS.nuevaOportunidad()"><i class="bi bi-plus-lg me-1"></i>Crear oportunidad</button>' : ''}
            ${cfg.perm.actualizar ? '<button type="button" class="btn btn-link btn-sm w-100 mt-1" onclick="BS.abrirEnlazar()"><i class="bi bi-link-45deg me-1"></i>Enlazar a otro contacto</button>' : ''}`;
    }

    // ── Envío ────────────────────────────────────────────────────────────────
    function modoActual() {
        if (st.conv.tipo === 'dm') return 'dm';
        return document.querySelector('input[name="bs-modo"]:checked')?.value || 'publica';
    }

    async function enviar() {
        if (st.enviando || !st.conv) return;
        const texto = $('bs-texto').value.trim();
        if (!texto) return;
        st.enviando = true;
        const btn = $('bs-btn-enviar');
        if (btn) btn.disabled = true;
        try {
            const r = await api('enviarAjax', { post: { id: st.conv.id, modo: modoActual(), texto } });
            $('bs-texto').value = '';
            agregarMensajes([r.mensaje]);
            if (!r.enviado) aviso('error', 'Instagram no aceptó el mensaje', r.error_envio || '');
            await refrescarConversacion();
        } catch (e) {
            aviso('error', 'No se pudo enviar', e.message);
        } finally {
            st.enviando = false;
            if (btn) btn.disabled = $('bs-texto').disabled;
        }
    }

    async function reintentar(idMensaje) {
        if (st.enviando) return;
        st.enviando = true;
        try {
            const r = await api('reintentarAjax', { post: { id_mensaje: idMensaje } });
            agregarMensajes([r.mensaje]);
            if (!r.enviado) aviso('error', 'Instagram no aceptó el mensaje', r.error_envio || '');
        } catch (e) { aviso('error', 'No se pudo reintentar', e.message); }
        finally { st.enviando = false; }
    }

    async function cargarRespuestas() {
        const ul = $('bs-respuestas');
        if (ul.dataset.cargado) return;
        try {
            const d = await api('respuestasRapidasAjax');
            ul.innerHTML = d.data.length ? d.data.map((r, i) => `<li><a class="dropdown-item small" href="#" data-i="${i}">
                <div class="fw-semibold">${esc(r.titulo)}${r.personal ? ' <span class="badge bg-light text-secondary">mía</span>' : ''}</div>
                <div class="text-muted text-truncate" style="max-width:320px">${esc(r.contenido)}</div></a></li>`).join('')
                : '<li><span class="dropdown-item-text small text-muted">No hay respuestas rápidas. Se crean en el chat de WhatsApp.</span></li>';
            ul.dataset.cargado = '1';
            ul.addEventListener('click', (e) => {
                const a = e.target.closest('[data-i]');
                if (!a) return;
                e.preventDefault();
                const t = $('bs-texto');
                t.value = (t.value ? t.value + ' ' : '') + d.data[+a.dataset.i].contenido;
                t.focus();
            });
        } catch (e) { ul.innerHTML = '<li><span class="dropdown-item-text small text-danger">No se pudieron cargar.</span></li>'; }
    }

    // ── Gestión ──────────────────────────────────────────────────────────────
    async function accion(fn) {
        if (st.accion) return;
        st.accion = true;
        try { await fn(); } catch (e) { aviso('error', 'No se pudo completar', e.message); }
        finally { st.accion = false; }
    }

    function asignar(yo) {
        const valor = yo === 'yo' ? 'yo' : $('bs-p-resp').value;
        accion(async () => {
            const r = await api('asignarAjax', { post: { id: st.conv.id, id_responsable: valor } });
            toast(r.mensaje);
            await abrir(st.conv.id);
        });
    }

    function estado(nuevo) {
        accion(async () => {
            const r = await api('estadoAjax', { post: { id: st.conv.id, estado: nuevo } });
            toast(r.mensaje);
            await abrir(st.conv.id);
        });
    }

    /** Asistente virtual en esta conversación: pausar o reactivar. */
    function bot(nuevo) {
        accion(async () => {
            const r = await api('botEstadoAjax', { post: { id: st.conv.id, estado: nuevo } });
            toast(r.mensaje);
            await abrir(st.conv.id);
        });
    }

    /** Bloque del panel con el estado del asistente (solo si está instalado y es un DM). */
    function panelBot(c) {
        if (!c.bot_estado || c.tipo !== 'dm') return '';
        const ESTADOS = {
            activo: ['<i class="bi bi-robot me-1 text-success"></i>Responde el asistente', ''],
            pausado: ['<i class="bi bi-pause-circle me-1 text-secondary"></i>Asistente pausado', 'Responde el equipo.'],
            traspasada: ['<i class="bi bi-person-raised-hand me-1 text-warning"></i>El asistente la pasó a una persona', ''],
        };
        const cuentaActiva = c.bot_cuenta_activa === true || c.bot_cuenta_activa === 't';
        let [titulo, sub] = ESTADOS[c.bot_estado] || ['', ''];
        if (c.bot_estado === 'activo' && !cuentaActiva) {
            titulo = '<i class="bi bi-robot me-1 text-secondary"></i>Asistente apagado en esta cuenta';
            sub = 'Se activa en Configuración del CRM → Asistente virtual.';
        }
        if (c.bot_estado === 'traspasada' && c.bot_motivo_traspaso) sub = 'Motivo: ' + esc(c.bot_motivo_traspaso);
        let boton = '';
        if (cfg.perm.actualizar) {
            boton = c.bot_estado === 'activo'
                ? (cuentaActiva ? '<button type="button" class="btn btn-outline-secondary btn-sm w-100 mt-1" onclick="BS.bot(\'pausado\')"><i class="bi bi-pause-circle me-1"></i>Pausar asistente</button>' : '')
                : '<button type="button" class="btn btn-outline-success btn-sm w-100 mt-1" onclick="BS.bot(\'activo\')" title="Vuelve a responder y reinicia su contador"><i class="bi bi-robot me-1"></i>Reactivar asistente</button>';
        }
        return `<div class="border rounded-3 p-2 mb-3 small">
            <div class="fw-bold">${titulo}</div>
            ${sub ? `<div class="text-muted">${sub}</div>` : ''}
            ${+c.bot_respuestas > 0 ? `<div class="text-muted">${+c.bot_respuestas} respuesta(s) del asistente.</div>` : ''}
            ${boton}
        </div>`;
    }

    function nuevaOportunidad() {
        $('bs-op-token').value = window.CMG_nuevoTokenGuardado ? CMG_nuevoTokenGuardado() : '';
        $('bs-op-titulo').value = (st.conv.contacto ? st.conv.contacto + ' · ' : '') + 'Instagram';
        $('bs-op-valor').value = '0';
        bootstrap.Modal.getOrCreateInstance($('bsModalOportunidad')).show();
    }

    function crearOportunidad() {
        const btn = $('bs-op-btn');
        if (btn.disabled) return;
        btn.disabled = true;
        accion(async () => {
            try {
                const r = await api('crearOportunidadAjax', { post: {
                    id: st.conv.id, titulo: $('bs-op-titulo').value, valor_estimado: $('bs-op-valor').value,
                    id_embudo: $('bs-op-embudo').value, token_guardado: $('bs-op-token').value,
                } });
                bootstrap.Modal.getOrCreateInstance($('bsModalOportunidad')).hide();
                toast(r.mensaje);
                await abrir(st.conv.id);
            } finally { btn.disabled = false; }
        });
    }

    function abrirEnlazar() {
        $('bs-enl-id').value = '';
        $('bs-enl-buscar').value = '';
        bootstrap.Modal.getOrCreateInstance($('bsModalEnlazar')).show();
    }

    function enlazar() {
        const idContacto = $('bs-enl-id').value;
        if (!idContacto) { aviso('warning', 'Seleccione el contacto'); return; }
        accion(async () => {
            const r = await api('enlazarContactoAjax', { post: { id: st.conv.id, id_contacto: idContacto } });
            bootstrap.Modal.getOrCreateInstance($('bsModalEnlazar')).hide();
            toast(r.mensaje);
            await abrir(st.conv.id);
        });
    }

    /** Buscador del modal «Enlazar»: Backspace/Delete con selección la limpia de una vez. */
    function iniciarBuscadorContactos() {
        const input = $('bs-enl-buscar'), oculto = $('bs-enl-id'), dd = $('bs-enl-dd');
        let timer = null, items = [];
        input.addEventListener('keydown', (e) => {
            if ((e.key === 'Backspace' || e.key === 'Delete') && oculto.value) { e.preventDefault(); oculto.value = ''; input.value = ''; dd.classList.add('d-none'); }
        });
        input.addEventListener('input', () => {
            oculto.value = '';
            clearTimeout(timer);
            timer = setTimeout(async () => {
                try {
                    items = (await api('buscarContactosAjax', { params: { q: input.value.trim() } })).data;
                    dd.innerHTML = items.map((c, i) => `<button type="button" class="list-group-item list-group-item-action small py-1" data-i="${i}">${esc(c.nombre)}
                        <span class="text-muted">${esc([c.telefono, c.correo, c.instagram_usuario ? '@' + c.instagram_usuario : ''].filter(Boolean).join(' · '))}</span></button>`).join('')
                        || '<div class="list-group-item small text-muted">Sin resultados.</div>';
                    dd.classList.remove('d-none');
                } catch (e) { dd.classList.add('d-none'); }
            }, 300);
        });
        dd.addEventListener('click', (e) => {
            const b = e.target.closest('[data-i]');
            if (!b) return;
            const c = items[+b.dataset.i];
            oculto.value = c.id;
            input.value = c.nombre;
            dd.classList.add('d-none');
        });
    }

    // ── Sondeo ───────────────────────────────────────────────────────────────
    async function refrescarConversacion() {
        if (!st.conv) return;
        const d = await api('conversacionAjax', { params: { id: st.conv.id, desde_id: st.ultimoId } });
        const cambioVentana = JSON.stringify(d.conversacion.ventana) !== JSON.stringify(st.conv.ventana);
        st.conv = Object.assign(st.conv, d.conversacion);
        agregarMensajes(d.mensajes);
        if (cambioVentana || d.mensajes.length) pintarComposer();
    }

    async function sondear() {
        if (document.hidden) return;
        try {
            await cargarLista();
            await refrescarConversacion();
        } catch (e) { /* el siguiente intento lo vuelve a probar */ }
    }

    // ── Arranque ─────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        if (!$('bandeja')) return;
        document.querySelectorAll('#bs-alcance [data-alcance]').forEach((b) => b.addEventListener('click', () => {
            st.alcance = b.dataset.alcance;
            document.querySelectorAll('#bs-alcance [data-alcance]').forEach((x) => x.classList.toggle('active', x === b));
            cargarLista().catch((e) => aviso('error', 'No se pudo cargar', e.message));
        }));
        ['bs-f-tipo', 'bs-f-estado', 'bs-f-bot'].forEach((id) => $(id) && $(id).addEventListener('change', () => cargarLista().catch(() => {})));
        let tb = null;
        $('bs-f-buscar').addEventListener('input', () => { clearTimeout(tb); tb = setTimeout(() => cargarLista().catch(() => {}), 400); });
        $('bs-texto').addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); enviar(); }
        });
        iniciarBuscadorContactos();
        cargarLista().catch((e) => aviso('error', 'No se pudo cargar la bandeja', e.message));
        st.timer = setInterval(sondear, SONDEO_MS);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) sondear(); });
    });

    window.BS = {
        abrir, enviar, enviarImagen, reintentar, cargarRespuestas, asignar, estado, bot,
        nuevaOportunidad, crearOportunidad, abrirEnlazar, enlazar,
        volverALista: () => $('bandeja').classList.remove('ver-chat', 'ver-panel'),
        verPanel: () => $('bandeja').classList.add('ver-panel'),
        verChat: () => $('bandeja').classList.remove('ver-panel'),
    };
})();
