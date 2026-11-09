/**
 * Configuración del CRM (modulos/crm_configuracion): canales de Instagram,
 * embudos y etapas, orígenes y motivos de pérdida.
 * Servidor: CrmConfiguracionController. Diseño: docs/diseno/crm-redes-fase1.md §9.3.
 */
(function () {
    'use strict';

    const cfg = window.CRMC_CONFIG || {};
    const $ = (id) => document.getElementById(id);
    const esc = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    function fecha(s) {
        const m = String(s || '').match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/);
        return m ? `${m[3]}-${m[2]}-${m[1]} ${m[4] || '00'}:${m[5] || '00'}:${m[6] || '00'}` : '-';
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

    async function api(accion, { params = null, post = null } = {}) {
        let url = `${cfg.urlBase}/${accion}`;
        const opts = { headers: { 'X-Requested-With': 'XMLHttpRequest' } };
        if (params) url += '?' + new URLSearchParams(params).toString();
        if (post) {
            const fd = new FormData();
            Object.entries(post).forEach(([k, v]) => {
                if (Array.isArray(v)) v.forEach((x) => fd.append(k + '[]', x));
                else fd.append(k, v ?? '');
            });
            opts.method = 'POST';
            opts.body = fd;
        }
        const resp = await fetch(url, opts);
        let json = null;
        try { json = await resp.json(); } catch (e) { /* sin JSON */ }
        if (!json || json.ok === false) throw new Error((json && (json.error || json.mensaje)) || 'Error de conexión. Intente de nuevo.');
        return json;
    }

    const st = { datos: null, embudoSel: null, enCurso: false, sortable: null };

    /** Envuelve una acción con bandera «en curso» (un clic = una petición). */
    async function unaVez(fn) {
        if (st.enCurso) return;
        st.enCurso = true;
        try { await fn(); } catch (e) { aviso('error', 'No se pudo completar', e.message); }
        finally { st.enCurso = false; }
    }

    // ── Canales ──────────────────────────────────────────────────────────────
    const ESTADO_CANAL = {
        activo:        ['Conectada', 'bg-success'],
        desconectado:  ['Desconectada', 'bg-secondary'],
        token_vencido: ['Acceso vencido', 'bg-danger'],
    };
    const REGLAS = { sin_asignar: 'Sin asignar (las ve todo el equipo)', rotacion: 'Repartir por turnos', fija: 'Siempre a un usuario' };

    async function cargarCanales() {
        const d = await api('canalesAjax');
        const usuarios = st.datos ? st.datos.usuarios : [];
        // Cada red en su sección del acordeón (Instagram / LinkedIn).
        const ig = d.canales.filter((c) => c.tipo === 'instagram');
        const li = d.canales.filter((c) => c.tipo !== 'instagram');
        const vacio = (t) => `<div class="text-muted small border rounded p-3 bg-white">${t}</div>`;
        $('crmc-lista-canales').innerHTML = ig.length ? ig.map((c) => canalHtml(c, usuarios)).join('') : vacio('No hay cuentas de Instagram conectadas.');
        const cajaLi = $('crmc-lista-linkedin');
        if (cajaLi) cajaLi.innerHTML = li.length ? li.map((c) => canalHtml(c, usuarios)).join('') : vacio('No hay cuentas de LinkedIn conectadas.');
        // Resumen en el encabezado de cada sección (se ve aunque esté cerrada).
        const resumen = (lista, id) => {
            const b = $(id);
            if (!b) return;
            const activas = lista.filter((c) => c.estado === 'activo').length;
            const vencidas = lista.filter((c) => c.estado === 'token_vencido').length;
            b.textContent = lista.length ? `${activas} conectada(s)${vencidas ? ` · ${vencidas} con acceso vencido` : ''}` : '';
            b.classList.toggle('d-none', !lista.length);
            b.classList.toggle('text-danger', vencidas > 0);
        };
        resumen(ig, 'crmc-badge-instagram');
        resumen(li, 'crmc-badge-linkedin');
    }

    /** Recuerda en este navegador qué secciones del acordeón de Canales dejó cerradas el usuario. */
    function iniciarAcordeon() {
        const CLAVE = 'crmc_canales_cerradas';
        let cerradas = [];
        try { cerradas = JSON.parse(localStorage.getItem(CLAVE) || '[]') || []; } catch (e) { cerradas = []; }
        document.querySelectorAll('#crmc-acordeon-canales .accordion-item').forEach((item) => {
            const sec = item.dataset.seccion;
            const panel = item.querySelector('.accordion-collapse');
            const boton = item.querySelector('.accordion-button');
            if (!panel || !boton) return;
            if (cerradas.includes(sec)) {
                panel.classList.remove('show');
                boton.classList.add('collapsed');
                boton.setAttribute('aria-expanded', 'false');
            }
            const guardar = (abierta) => {
                cerradas = cerradas.filter((s) => s !== sec);
                if (!abierta) cerradas.push(sec);
                try { localStorage.setItem(CLAVE, JSON.stringify(cerradas)); } catch (e) { /* sin almacenamiento */ }
            };
            panel.addEventListener('shown.bs.collapse', () => guardar(true));
            panel.addEventListener('hidden.bs.collapse', () => guardar(false));
        });
    }

    /** Cuenta de LinkedIn (perfil o página): solo para publicar; sin mensajes ni reparto. */
    function canalLinkedinHtml(c) {
        const [est, cls] = ESTADO_CANAL[c.estado] || [c.estado, 'bg-secondary'];
        const pagina = c.tipo === 'linkedin_pagina';
        const avisos = [];
        if (c.estado === 'activo' && c.dias_para_vencer !== null && c.dias_para_vencer < 10) {
            avisos.push(`El acceso de LinkedIn vence en ${c.dias_para_vencer} día(s) y LinkedIn no permite renovarlo solo: pulse «Reconectar» antes.`);
        }
        if (c.falta_publicar) avisos.push('LinkedIn no concedió el permiso de publicar. Revise los productos de la app y vuelva a conectar.');
        if (c.estado === 'token_vencido') avisos.push('El acceso venció. Pulse «Reconectar» para seguir publicando.');
        return `<div class="card border-0 shadow-sm"><div class="card-body py-2">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <i class="bi bi-linkedin text-primary me-1"></i><span class="fw-bold">${esc(c.nombre || c.usuario)}</span>
                    <span class="text-muted small ms-1">${pagina ? 'Página de empresa' : 'Perfil personal'}</span>
                    <span class="badge ${cls} ms-2">${est}</span>
                    <div class="small text-muted">Conectada por ${esc(c.conectado_por || '-')} · ${c.token_expira_at ? 'acceso hasta ' + fecha(c.token_expira_at) : 'sin acceso'} · solo para publicar</div>
                </div>
                <div class="d-flex gap-1">
                    ${cfg.perm.crear ? `<button type="button" class="btn btn-outline-primary btn-sm" onclick="CRMC.conectarLinkedin(${pagina ? 'true' : 'false'})"><i class="bi bi-arrow-repeat me-1"></i>Reconectar</button>` : ''}
                    ${cfg.perm.eliminar && c.estado !== 'desconectado' ? `<button type="button" class="btn btn-outline-danger btn-sm" onclick="CRMC.desconectar(${c.id}, '${esc(c.nombre || c.usuario)}')"><i class="bi bi-x-circle me-1"></i>Desconectar</button>` : ''}
                </div>
            </div>
            ${avisos.map((a) => `<div class="small text-warning-emphasis mt-1"><i class="bi bi-exclamation-triangle me-1"></i>${esc(a)}</div>`).join('')}
        </div></div>`;
    }

    function canalHtml(c, usuarios) {
        if (c.tipo !== 'instagram') return canalLinkedinHtml(c);
        const [est, cls] = ESTADO_CANAL[c.estado] || [c.estado, 'bg-secondary'];
        const asig = (c.config && c.config.asignacion) || { regla: 'sin_asignar', usuarios: [] };
        const elegidos = (asig.usuarios || []).map(String);
        const activo = c.estado === 'activo';
        const avisos = [];
        if (activo && c.dias_para_vencer !== null && c.dias_para_vencer < 10) avisos.push(`El acceso vence en ${c.dias_para_vencer} día(s); el sistema lo renueva solo cada día.`);
        if (c.unificada && c.permisos_faltantes && c.permisos_faltantes.length) {
            // Unified login: los permisos los define la configuración de la app en Meta, no la persona al conectar.
            avisos.push('La configuración de inicio de sesión de la app de Meta no incluye: ' + c.permisos_faltantes.join(', ')
                + (c.permisos_faltantes.includes('instagram_manage_insights') ? ' (sin él no hay métricas de las publicaciones)' : '')
                + '. Agréguelo(s) en Meta y pulse «Reconectar».');
        } else if (c.falta_publicar && activo) {
            avisos.push('Para publicar desde el sistema (Publicaciones), pulse «Reconectar» y acepte los permisos nuevos de publicar y de estadísticas. Los mensajes siguen funcionando mientras tanto.');
        } else if (c.permisos_faltantes && c.permisos_faltantes.length) {
            avisos.push('Faltan permisos: ' + c.permisos_faltantes.join(', ') + '. Vuelva a conectar y acéptelos todos.');
        }
        if (activo && c.config && c.config.webhooks_ok === false) avisos.push('Instagram no aceptó la suscripción a mensajes. Vuelva a conectar la cuenta.');
        if (c.estado === 'token_vencido') {
            avisos.push(c.unificada
                ? 'Meta ya no acepta el acceso de esta cuenta (se quitó la app del portafolio o la cuenta cambió de portafolio). Pulse «Reconectar».'
                : 'El acceso venció. Pulse «Reconectar» para seguir recibiendo mensajes.');
        }
        const acceso = c.unificada && activo ? 'acceso del portafolio (no vence)'
            : (c.token_expira_at ? 'acceso hasta ' + fecha(c.token_expira_at) : 'sin acceso');
        const editable = cfg.perm.actualizar && c.estado !== 'desconectado';
        return `<div class="card border-0 shadow-sm"><div class="card-body py-2">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <i class="bi bi-instagram text-danger me-1"></i><span class="fw-bold">@${esc(c.usuario)}</span>
                    <span class="text-muted small ms-1">${esc(c.nombre || '')}</span>
                    <span class="badge ${cls} ms-2">${est}</span>
                    <div class="small text-muted">Conectada por ${esc(c.conectado_por || '-')} · ${acceso}</div>
                </div>
                <div class="d-flex gap-1">
                    ${activo ? `<button type="button" class="btn btn-outline-secondary btn-sm" onclick="CRMC.probar(${c.id})"><i class="bi bi-plug me-1"></i>Probar</button>` : ''}
                    ${cfg.perm.crear ? `<button type="button" class="btn btn-outline-primary btn-sm" onclick="CRMC.conectarInstagram()"><i class="bi bi-arrow-repeat me-1"></i>Reconectar</button>` : ''}
                    ${cfg.perm.eliminar && c.estado !== 'desconectado' ? `<button type="button" class="btn btn-outline-danger btn-sm" onclick="CRMC.desconectar(${c.id}, '${esc(c.usuario)}')"><i class="bi bi-x-circle me-1"></i>Desconectar</button>` : ''}
                </div>
            </div>
            ${avisos.map((a) => `<div class="small text-warning-emphasis mt-1"><i class="bi bi-exclamation-triangle me-1"></i>${esc(a)}</div>`).join('')}
            <hr class="my-2">
            <div class="row g-2 align-items-start" data-canal="${c.id}">
                <div class="col-md-4">
                    <label class="form-label small fw-bold mb-1">Conversaciones nuevas</label>
                    <select class="form-select form-select-sm" data-campo="regla" ${editable ? '' : 'disabled'} onchange="CRMC.reglaCambiada(this)">
                        ${Object.entries(REGLAS).map(([v, t]) => `<option value="${v}" ${asig.regla === v ? 'selected' : ''}>${t}</option>`).join('')}
                    </select>
                </div>
                <div class="col-md-5 ${asig.regla === 'sin_asignar' ? 'd-none' : ''}" data-campo="usuarios-wrap">
                    <label class="form-label small fw-bold mb-1">Usuarios</label>
                    <div class="border rounded p-1 bg-white" style="max-height:120px;overflow:auto;">
                        ${usuarios.map((u) => `<div class="form-check small mb-0"><input class="form-check-input" type="checkbox" value="${u.id}" id="crmc-u-${c.id}-${u.id}" data-campo="usuario"
                            ${elegidos.includes(String(u.id)) ? 'checked' : ''} ${editable ? '' : 'disabled'}><label class="form-check-label" for="crmc-u-${c.id}-${u.id}">${esc(u.nombre)}</label></div>`).join('')}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-check form-switch mt-md-4 small" title="Activar solo si la app de Meta tiene aprobada la función Human Agent: permite responder a mano hasta 7 días después del último mensaje del cliente.">
                        <input class="form-check-input" type="checkbox" data-campo="ventana_humana" id="crmc-vh-${c.id}" ${c.ventana_humana ? 'checked' : ''} ${editable ? '' : 'disabled'}>
                        <label class="form-check-label" for="crmc-vh-${c.id}">Responder hasta 7 días (Human Agent)</label>
                    </div>
                    ${editable ? `<button type="button" class="btn btn-primary btn-sm mt-1" onclick="CRMC.guardarCanal(${c.id})"><i class="bi bi-check2 me-1"></i>Guardar</button>` : ''}
                </div>
            </div>
        </div></div>`;
    }

    function reglaCambiada(sel) {
        const fila = sel.closest('[data-canal]');
        fila.querySelector('[data-campo="usuarios-wrap"]').classList.toggle('d-none', sel.value === 'sin_asignar');
    }

    // ── App de Meta de la empresa ────────────────────────────────────────────
    function guardarApp() {
        const appId = $('crmc-app-id').value.trim();
        const secreto = $('crmc-app-secret').value.trim();
        const configId = $('crmc-config-id') ? $('crmc-config-id').value.trim() : '';
        unaVez(async () => {
            const r = await api('guardarAppMetaAjax', { post: { app_id: appId, app_secret: secreto, config_id: configId } });
            $('crmc-app-secret').value = '';
            toast(r.mensaje);
            // La tarjeta (URLs, token, botón Conectar) la pinta el servidor: se recarga.
            setTimeout(() => window.location.reload(), 700);
        });
    }

    async function quitarApp() {
        if (!(await confirmar('¿Quitar la app de Meta de la empresa? No se podrán conectar cuentas hasta registrar otra.', 'Sí, quitar'))) return;
        unaVez(async () => {
            const r = await api('quitarAppMetaAjax', { post: {} });
            toast(r.mensaje, 'info');
            setTimeout(() => window.location.reload(), 700);
        });
    }

    async function regenerarToken() {
        if (!(await confirmar('Se generará otro token de verificación. Hay que pegarlo también en la configuración del webhook de la app de Meta. ¿Continuar?', 'Sí, generar'))) return;
        unaVez(async () => {
            const r = await api('regenerarTokenMetaAjax', { post: {} });
            $('crmc-verify-token').value = r.app.verify_token;
            if ($('crmc-verify-token-guia')) $('crmc-verify-token-guia').value = r.app.verify_token;
            toast(r.mensaje);
        });
    }

    function copiar(boton) {
        const input = boton.closest('.input-group').querySelector('input');
        const ok = () => toast('Copiado.');
        if (navigator.clipboard) {
            navigator.clipboard.writeText(input.value).then(ok).catch(() => { input.select(); document.execCommand('copy'); ok(); });
        } else {
            input.select();
            document.execCommand('copy');
            ok();
        }
    }

    // ── Paso a paso de Meta (modal con una tarjeta por paso) ─────────────────
    const guia = { paso: 0 };
    function guiaModal() { return window.bootstrap ? bootstrap.Modal.getOrCreateInstance($('crmc-modal-guia')) : null; }
    function guiaAbrir(paso) {
        const m = guiaModal();
        if (!m) return;
        // Sin app registrada se empieza por el principio; con app, por los webhooks (lo que suele faltar).
        guiaIr(typeof paso === 'number' ? paso : (cfg.appRegistrada ? 3 : 0));
        m.show();
    }
    function guiaIr(n) {
        const pasos = document.querySelectorAll('#crmc-modal-guia .crmc-guia-paso');
        const total = pasos.length;
        guia.paso = Math.max(0, Math.min(total - 1, n));
        pasos.forEach((p, i) => p.classList.toggle('d-none', i !== guia.paso));
        document.querySelectorAll('#crmc-modal-guia .crmc-punto').forEach((b, i) => {
            b.classList.toggle('activo', i === guia.paso);
            b.classList.toggle('hecho', i < guia.paso);
        });
        $('crmc-guia-anterior').disabled = guia.paso === 0;
        const ultimo = guia.paso === total - 1;
        $('crmc-guia-siguiente').classList.toggle('d-none', ultimo);
        if ($('crmc-guia-conectar')) $('crmc-guia-conectar').classList.toggle('d-none', !ultimo);
        const cuerpo = document.querySelector('#crmc-modal-guia .modal-body');
        if (cuerpo) cuerpo.scrollTop = 0;
    }
    function guiaMover(d) { guiaIr(guia.paso + d); }
    function guiaConectar() {
        const m = guiaModal();
        if (m) m.hide();
        conectarInstagram();
    }

    // ── Conectar Instagram / LinkedIn en una ventana aparte ──────────────────
    // La autorización (Meta o LinkedIn) se abre en otra ventana: el sistema queda
    // abierto aquí. Al volver (instagramCallback / linkedinCallback → esta misma
    // página dentro de esa ventana, con el resultado en cfg.flash: ese aviso SOLO lo
    // dejan los callbacks), la página se lo pasa a esta ventana y se cierra.
    // Canal: BroadcastChannel (mismo sitio; no depende de window.opener ni de
    // window.name, que algunos navegadores cortan al pasar por facebook.com), con
    // postMessage al opener como respaldo. Sin respuesta de ninguna ventana del
    // sistema (p. ej. se abrió en la misma pestaña), la página sigue normal.
    const VENTANA = 'crmc_autorizacion';
    const CANAL = 'crmc-autorizacion';
    const canal = 'BroadcastChannel' in window ? new BroadcastChannel(CANAL) : null;
    const REDES = {
        instagram: {
            titulo: 'Conectar Instagram', nombre: 'Meta (Facebook / Instagram)',
            pasos: ['Inicie sesión con <strong>Instagram</strong> o con <strong>Facebook</strong>.',
                'Elija el <strong>portafolio comercial</strong> de la empresa y la <strong>cuenta de Instagram</strong>.',
                'Acepte los permisos.'],
            pedirUrl: () => api('conectarInstagramAjax', { post: {} }),
        },
        linkedin: {
            titulo: 'Conectar LinkedIn', nombre: 'LinkedIn',
            pasos: ['Inicie sesión con la cuenta de LinkedIn que publicará.', 'Acepte los permisos que pide la app de la empresa.'],
            pedirUrl: (paginas) => api('conectarLinkedinAjax', { post: { paginas: paginas ? '1' : '' } }),
        },
    };
    const con = { red: null, paginas: false, ventana: null, vigilante: null, recibido: false, esperando: false, abierta: 0, vistos: new Set() };

    function modalConectar() { return window.bootstrap ? bootstrap.Modal.getOrCreateInstance($('crmc-modal-conectar')) : null; }
    function estadoConectar(estado) {
        const caja = $('crmc-modal-conectar');
        caja.querySelector('[data-estado="antes"]').classList.toggle('d-none', estado !== 'antes');
        caja.querySelector('[data-estado="esperando"]').classList.toggle('d-none', estado !== 'esperando');
        caja.querySelector('[data-accion="abrir"]').classList.toggle('d-none', estado !== 'antes');
    }
    function prepararConectar(red, paginas) {
        const r = REDES[red];
        const m = modalConectar();
        if (!m) {   // sin Bootstrap: como antes, en la misma pestaña
            unaVez(async () => { window.location.href = (await r.pedirUrl(paginas)).url; });
            return;
        }
        con.red = red; con.paginas = !!paginas;
        const caja = $('crmc-modal-conectar');
        caja.querySelector('[data-red-titulo]').textContent = r.titulo;
        caja.querySelectorAll('[data-red-nombre]').forEach((e) => { e.textContent = r.nombre; });
        caja.querySelector('[data-red-pasos]').innerHTML = r.pasos.map((p) => `<li>${p}</li>`).join('');
        estadoConectar('antes');
        m.show();
    }
    function conectarInstagram() { prepararConectar('instagram'); }
    function conectarLinkedin(paginas) { prepararConectar('linkedin', paginas); }

    function conectarAbrir() {
        const r = REDES[con.red];
        if (!r) return;
        // La ventana se abre YA (en el clic) para que el navegador no la bloquee; la URL llega después.
        const ancho = 620, alto = 760;
        const izq = Math.max(0, (window.screenX || 0) + (window.outerWidth - ancho) / 2);
        const arr = Math.max(0, (window.screenY || 0) + (window.outerHeight - alto) / 2);
        let v = null;
        try { v = window.open('about:blank', VENTANA, `popup=yes,width=${ancho},height=${alto},left=${izq},top=${arr}`); } catch (e) { v = null; }
        unaVez(async () => {
            let url;
            try {
                url = (await r.pedirUrl(con.paginas)).url;
            } catch (e) {
                if (v && !v.closed) v.close();
                throw e;
            }
            if (!v || v.closed) {
                // Ventanas emergentes bloqueadas: se sigue en esta pestaña (al terminar vuelve al sistema).
                await aviso('info', 'Se abrirá en esta misma pestaña', 'El navegador bloqueó la ventana aparte. Al terminar, volverá automáticamente al sistema.');
                window.location.href = url;
                return;
            }
            v.location.href = url;
            try { v.focus(); } catch (e) { /* otro origen */ }
            con.ventana = v; con.recibido = false; con.esperando = true; con.abierta = Date.now();
            estadoConectar('esperando');
            clearInterval(con.vigilante);
            // Si la cierran sin terminar, no llega resultado: se recupera la pantalla.
            con.vigilante = setInterval(() => {
                if (con.recibido) { clearInterval(con.vigilante); return; }
                if (!con.ventana || con.ventana.closed) {
                    clearInterval(con.vigilante);
                    // «Cerrada» en los primeros segundos = el sitio cortó el vínculo entre ventanas
                    // (sigue abierta para la persona): se sigue esperando el resultado por el canal.
                    if (Date.now() - con.abierta < 4000) return;
                    setTimeout(() => {
                        if (con.recibido) return;
                        con.esperando = false;
                        const m = modalConectar();
                        if (m) m.hide();
                        toast('No se completó la conexión.', 'info');
                        cargarCanales().catch(() => {});
                    }, 2000);
                }
            }, 800);
        });
    }

    /** Resultado recibido desde la ventana de autorización (una sola vez por id). */
    function recibirResultado(d) {
        if (!d || d.tipo !== 'resultado' || !d.id || con.vistos.has(d.id)) return false;
        if (!con.esperando) return false;   // otra pestaña del sistema que no inició esta conexión
        con.vistos.add(d.id);
        con.recibido = true; con.esperando = false;
        clearInterval(con.vigilante);
        const m = modalConectar();
        if (m) m.hide();
        mostrarResultado(d.flash);
        cargarCanales().catch(() => {});
        cargarLinkedin();
        return true;
    }
    if (canal) {
        canal.onmessage = (e) => {
            if (recibirResultado(e.data)) canal.postMessage({ tipo: 'recibido', id: e.data.id });
        };
    }

    /** Muestra el resultado que trae la ventana de autorización (mismo formato que el aviso de la página). */
    function mostrarResultado(flash) {
        if (!flash) return;
        const icono = { success: 'success', warning: 'warning', error: 'error' }[flash.tipo] || 'info';
        const avisos = (flash.avisos || []).map((a) => `<div class="small text-start mt-1">⚠ ${esc(a)}</div>`).join('');
        if (window.Swal) Swal.fire({ icon: icono, title: esc(flash.texto), html: avisos || undefined });
        else alert(flash.texto + (flash.avisos ? '\n' + flash.avisos.join('\n') : ''));
    }

    // Respaldo sin BroadcastChannel: la ventana de autorización usa window.opener.
    window.addEventListener('message', (e) => {
        if (e.origin !== window.location.origin || !e.data) return;
        if (recibirResultado(e.data) && e.source) {
            try { e.source.postMessage({ tipo: 'recibido', id: e.data.id }, window.location.origin); } catch (x) { /* ya cerrada */ }
        }
    });

    /**
     * ¿Esta página es la ventana de autorización que volvió del callback? Le pasa el
     * resultado a la ventana del sistema que la abrió; si esa lo confirma, se cierra.
     * Si nadie responde (se abrió en la misma pestaña), devuelve false y la página
     * carga normal con su aviso.
     */
    function soyVentanaDeAutorizacion() {
        if (!cfg.flash) return Promise.resolve(false);
        const id = Date.now().toString(36) + Math.random().toString(36).slice(2);
        const msg = { tipo: 'resultado', id, flash: cfg.flash };
        return new Promise((resolver) => {
            let listo = false;
            const terminar = (ok) => {
                if (listo) return;
                listo = true;
                if (ok) {
                    document.body.innerHTML = '<div style="font-family:sans-serif;padding:24px;text-align:center">'
                        + 'Listo. Vuelva a la ventana del sistema CaMaGaRe (esta se cierra sola).</div>';
                    setTimeout(() => window.close(), 400);
                }
                resolver(ok);
            };
            const alConfirmar = (d) => { if (d && d.tipo === 'recibido' && d.id === id) terminar(true); };
            if (canal) {
                canal.addEventListener('message', (e) => alConfirmar(e.data));
                canal.postMessage(msg);
            }
            if (window.opener && !window.opener.closed) {
                window.addEventListener('message', (e) => { if (e.origin === window.location.origin) alConfirmar(e.data); });
                try { window.opener.postMessage(msg, window.location.origin); } catch (e) { /* sin acceso */ }
            }
            setTimeout(() => terminar(false), 1500);
        });
    }

    // ── LinkedIn (fase 3) ────────────────────────────────────────────────────
    async function cargarLinkedin() {
        const caja = $('crmc-li-app');
        if (!caja) return;
        let d;
        try { d = await api('linkedinAppAjax'); } catch (e) { caja.innerHTML = `<div class="small text-muted">${esc(e.message)}</div>`; return; }
        const a = d.app;
        const editar = a.configurada ? cfg.perm.actualizar : cfg.perm.crear;
        caja.innerHTML = `
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="fw-bold small"><i class="bi bi-linkedin text-primary me-1"></i>App de LinkedIn de la empresa</span>
                <span class="badge ${a.configurada ? 'bg-success' : 'bg-secondary'}">${a.configurada ? 'Registrada' : 'Sin registrar'}</span>
            </div>
            <div class="small text-muted mb-2">Solo para publicar. Se crea en linkedin.com/developers con los productos <b>Sign In with LinkedIn using OpenID Connect</b> y <b>Share on LinkedIn</b>.
                Para páginas de empresa, LinkedIn debe aprobar además <b>Community Management API</b>.</div>
            <div class="row g-2">
                <div class="col-md-5">
                    <label class="form-label small fw-bold mb-1">Client ID</label>
                    <input type="text" class="form-control form-control-sm font-monospace" id="crmc-li-id" maxlength="60" value="${esc(a.client_id)}" ${editar ? '' : 'disabled'}>
                </div>
                <div class="col-md-5">
                    <label class="form-label small fw-bold mb-1">Client Secret</label>
                    <input type="password" class="form-control form-control-sm font-monospace" id="crmc-li-secret" maxlength="120" autocomplete="new-password"
                           placeholder="${a.secret_guardado ? 'Guardado (déjelo vacío para conservarlo)' : ''}" ${editar ? '' : 'disabled'}>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    ${editar ? '<button type="button" class="btn btn-primary btn-sm w-100" onclick="CRMC.guardarLinkedin()"><i class="bi bi-check2 me-1"></i>Guardar</button>' : ''}
                </div>
                <div class="col-12">
                    <div class="form-check small">
                        <input class="form-check-input" type="checkbox" id="crmc-li-paginas" ${a.paginas_aprobadas ? 'checked' : ''} ${editar ? '' : 'disabled'}>
                        <label class="form-check-label" for="crmc-li-paginas">LinkedIn ya aprobó «Community Management API» en esta app (publicar en páginas de empresa)</label>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label small fw-bold mb-1">Dirección de retorno (Authorized redirect URL en la pestaña Auth de la app)</label>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control font-monospace" readonly value="${esc(d.redirect)}">
                        <button class="btn btn-outline-secondary" type="button" onclick="CRMC.copiar(this)" title="Copiar"><i class="bi bi-clipboard"></i></button>
                    </div>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-2">
                ${a.configurada && cfg.perm.crear ? '<button type="button" class="btn btn-outline-primary btn-sm" onclick="CRMC.conectarLinkedin(false)"><i class="bi bi-person-badge me-1"></i>Conectar mi perfil</button>' : ''}
                ${a.configurada && a.paginas_aprobadas && cfg.perm.crear ? '<button type="button" class="btn btn-outline-primary btn-sm" onclick="CRMC.conectarLinkedin(true)"><i class="bi bi-building me-1"></i>Conectar perfil y páginas</button>' : ''}
                ${a.configurada && cfg.perm.eliminar ? '<button type="button" class="btn btn-outline-danger btn-sm ms-auto" onclick="CRMC.quitarLinkedin()" title="Quitar la app"><i class="bi bi-trash"></i></button>' : ''}
            </div>`;
    }

    function guardarLinkedin() {
        unaVez(async () => {
            const r = await api('guardarLinkedinAppAjax', { post: {
                client_id: $('crmc-li-id').value.trim(), client_secret: $('crmc-li-secret').value.trim(),
                paginas_aprobadas: $('crmc-li-paginas').checked ? '1' : '',
            } });
            toast(r.mensaje);
            await cargarLinkedin();
        });
    }

    async function quitarLinkedin() {
        if (!(await confirmar('¿Quitar la app de LinkedIn de la empresa? No se podrá conectar LinkedIn hasta registrar otra.', 'Sí, quitar'))) return;
        unaVez(async () => { const r = await api('quitarLinkedinAppAjax', { post: {} }); toast(r.mensaje, 'info'); await cargarLinkedin(); });
    }


    function probar(id) {
        unaVez(async () => { const r = await api('probarCanalAjax', { post: { id } }); toast(r.mensaje); });
    }

    async function desconectar(id, usuario) {
        if (!(await confirmar(`¿Desconectar @${usuario}? Dejarán de llegar y de enviarse mensajes por esta cuenta. El historial se conserva.`, 'Sí, desconectar'))) return;
        unaVez(async () => { const r = await api('desconectarCanalAjax', { post: { id } }); toast(r.mensaje, 'info'); await cargarCanales(); });
    }

    function guardarCanal(id) {
        const fila = document.querySelector(`[data-canal="${id}"]`);
        const regla = fila.querySelector('[data-campo="regla"]').value;
        const usuarios = [...fila.querySelectorAll('[data-campo="usuario"]:checked')].map((i) => i.value);
        const vh = fila.querySelector('[data-campo="ventana_humana"]').checked ? '1' : '0';
        unaVez(async () => {
            const r = await api('guardarCanalAjax', { post: { id, regla, usuarios: regla === 'sin_asignar' ? [] : usuarios, ventana_humana: vh } });
            toast(r.mensaje);
            await cargarCanales();
        });
    }

    // ── Embudos y etapas ─────────────────────────────────────────────────────
    async function cargarDatos() {
        st.datos = await api('datosAjax');
        pintarEmbudos();
        pintarCatalogo('origenes');
        pintarCatalogo('motivos');
    }

    function pintarEmbudos() {
        const lista = st.datos.embudos;
        if (!lista.find((e) => String(e.id) === String(st.embudoSel))) st.embudoSel = (lista.find((e) => e.es_defecto) || lista[0] || {}).id || null;
        $('crmc-lista-embudos').innerHTML = lista.map((e) => `
            <button type="button" class="list-group-item list-group-item-action crmc-embudo ${String(e.id) === String(st.embudoSel) ? 'active' : ''}" onclick="CRMC.elegirEmbudo(${e.id})">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="${e.estado !== 'activo' ? 'text-muted text-decoration-line-through' : 'fw-medium'}">${esc(e.nombre)}</span>
                    <span>${e.es_defecto ? '<span class="badge bg-primary bg-opacity-10 text-primary">Predeterminado</span>' : ''}
                          <span class="badge bg-light text-secondary border" title="Oportunidades abiertas">${e.abiertas}</span></span>
                </div>
            </button>`).join('') || '<div class="list-group-item small text-muted">Sin embudos.</div>';
        pintarEditor();
    }

    function pintarEditor() {
        const e = st.datos.embudos.find((x) => String(x.id) === String(st.embudoSel));
        const cont = $('crmc-editor-embudo');
        if (!e) { cont.innerHTML = '<div class="card-body text-muted small">Elija o cree un embudo.</div>'; return; }
        const ed = cfg.perm.actualizar;
        const abiertas = e.etapas.filter((x) => x.tipo === 'abierta');
        const cierre = e.etapas.filter((x) => x.tipo !== 'abierta');
        cont.innerHTML = `<div class="card-body">
            <div class="row g-2 align-items-end mb-3">
                <div class="col-md-5"><label class="form-label small fw-bold mb-1">Nombre</label>
                    <input type="text" class="form-control form-control-sm" id="crmc-emb-nombre" maxlength="100" value="${esc(e.nombre)}" ${ed ? '' : 'disabled'}></div>
                <div class="col-md-3"><label class="form-label small fw-bold mb-1">Estado</label>
                    <select class="form-select form-select-sm" id="crmc-emb-estado" ${ed ? '' : 'disabled'}>
                        <option value="activo" ${e.estado === 'activo' ? 'selected' : ''}>Activo</option>
                        <option value="inactivo" ${e.estado !== 'activo' ? 'selected' : ''}>Inactivo</option></select></div>
                <div class="col-md-4">
                    <div class="form-check small mb-1"><input class="form-check-input" type="checkbox" id="crmc-emb-defecto" ${e.es_defecto ? 'checked disabled' : ''} ${ed ? '' : 'disabled'}>
                        <label class="form-check-label" for="crmc-emb-defecto">Predeterminado</label></div>
                    <div class="d-flex gap-1">
                        ${ed ? `<button type="button" class="btn btn-primary btn-sm" onclick="CRMC.guardarEmbudo(${e.id})"><i class="bi bi-check2 me-1"></i>Guardar</button>` : ''}
                        ${cfg.perm.eliminar && !e.es_defecto ? `<button type="button" class="btn btn-outline-danger btn-sm" onclick="CRMC.eliminarEmbudo(${e.id})"><i class="bi bi-trash"></i></button>` : ''}
                    </div>
                </div>
            </div>
            <div class="fw-bold small mb-1">Etapas intermedias <span class="text-muted fw-normal">(arrastre para ordenar)</span></div>
            <div class="d-flex flex-column gap-1 mb-2" id="crmc-etapas">${abiertas.map((x) => etapaHtml(x, ed)).join('')}</div>
            ${cfg.perm.crear ? `<div class="d-flex gap-1 mb-3" style="max-width:520px;">
                <input type="text" class="form-control form-control-sm" id="crmc-nueva-etapa" maxlength="100" placeholder="Nueva etapa…">
                <input type="number" class="form-control form-control-sm" id="crmc-nueva-prob" min="0" max="100" value="50" style="width:80px" title="Probabilidad %">
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="CRMC.agregarEtapa(${e.id})"><i class="bi bi-plus-lg"></i></button>
            </div>` : ''}
            <div class="fw-bold small mb-1">Cierre</div>
            <div class="d-flex flex-column gap-1">${cierre.map((x) => etapaHtml(x, ed)).join('')}</div>
        </div>`;
        if (st.sortable) { st.sortable.destroy(); st.sortable = null; }
        if (ed && window.Sortable) {
            st.sortable = new Sortable($('crmc-etapas'), { handle: '.crmc-asa', animation: 150, onEnd: () => reordenar(e.id) });
        }
    }

    function etapaHtml(x, ed) {
        const abierta = x.tipo === 'abierta';
        const icono = x.tipo === 'ganada' ? '<i class="bi bi-trophy text-success"></i>' : x.tipo === 'perdida' ? '<i class="bi bi-x-octagon text-danger"></i>' : '<i class="bi bi-grip-vertical crmc-asa"></i>';
        return `<div class="crmc-etapa" data-etapa="${x.id}">
            ${icono}
            <input type="color" class="form-control form-control-color" data-campo="color" value="${esc(x.color || '#6c757d')}" ${ed ? '' : 'disabled'} title="Color">
            <input type="text" class="form-control form-control-sm" data-campo="nombre" maxlength="100" value="${esc(x.nombre)}" ${ed ? '' : 'disabled'}>
            <div class="input-group input-group-sm" style="width:100px" title="Probabilidad de cierre">
                <input type="number" class="form-control" data-campo="probabilidad" min="0" max="100" value="${x.probabilidad}" ${ed && abierta ? '' : 'disabled'}><span class="input-group-text">%</span></div>
            ${ed ? `<button type="button" class="btn btn-outline-primary btn-sm" onclick="CRMC.guardarEtapa(${x.id})" title="Guardar"><i class="bi bi-check2"></i></button>` : ''}
            ${cfg.perm.eliminar && abierta ? `<button type="button" class="btn btn-outline-danger btn-sm" onclick="CRMC.eliminarEtapa(${x.id})" title="Eliminar"><i class="bi bi-trash"></i></button>` : ''}
        </div>`;
    }

    async function recargarDatos() {
        st.datos = await api('datosAjax');
        pintarEmbudos();
    }

    function elegirEmbudo(id) { st.embudoSel = id; pintarEmbudos(); }

    async function nuevoEmbudo() {
        let nombre = '';
        if (window.Swal) {
            const r = await Swal.fire({ title: 'Nuevo embudo', input: 'text', inputPlaceholder: 'Ej.: Postventa', showCancelButton: true, confirmButtonText: 'Crear', cancelButtonText: 'Cancelar' });
            if (!r.isConfirmed) return;
            nombre = (r.value || '').trim();
        } else {
            nombre = (prompt('Nombre del embudo') || '').trim();
        }
        if (!nombre) return;
        unaVez(async () => {
            const r = await api('guardarEmbudoAjax', { post: { nombre, estado: 'activo' } });
            st.embudoSel = r.id;
            toast('Embudo creado con sus etapas básicas.');
            await recargarDatos();
        });
    }

    function guardarEmbudo(id) {
        unaVez(async () => {
            await api('guardarEmbudoAjax', { post: {
                id, nombre: $('crmc-emb-nombre').value, estado: $('crmc-emb-estado').value,
                es_defecto: $('crmc-emb-defecto').checked && !$('crmc-emb-defecto').disabled ? '1' : '',
            } });
            toast('Embudo guardado.');
            await recargarDatos();
        });
    }

    async function eliminarEmbudo(id) {
        if (!(await confirmar('¿Eliminar este embudo y sus etapas?', 'Sí, eliminar'))) return;
        unaVez(async () => { await api('eliminarEmbudoAjax', { post: { id } }); st.embudoSel = null; toast('Embudo eliminado.'); await recargarDatos(); });
    }

    function agregarEtapa(idEmbudo) {
        const nombre = $('crmc-nueva-etapa').value.trim();
        if (!nombre) { aviso('warning', 'Escriba el nombre de la etapa'); return; }
        unaVez(async () => {
            await api('guardarEtapaAjax', { post: { id_embudo: idEmbudo, nombre, probabilidad: $('crmc-nueva-prob').value } });
            toast('Etapa agregada.');
            await recargarDatos();
        });
    }

    function guardarEtapa(id) {
        const fila = document.querySelector(`[data-etapa="${id}"]`);
        const v = (c) => fila.querySelector(`[data-campo="${c}"]`).value;
        unaVez(async () => {
            await api('guardarEtapaAjax', { post: { id, nombre: v('nombre'), probabilidad: v('probabilidad'), color: v('color') } });
            toast('Etapa guardada.');
            await recargarDatos();
        });
    }

    async function eliminarEtapa(id) {
        if (!(await confirmar('¿Eliminar esta etapa?', 'Sí, eliminar'))) return;
        unaVez(async () => { await api('eliminarEtapaAjax', { post: { id } }); toast('Etapa eliminada.'); await recargarDatos(); });
    }

    function reordenar(idEmbudo) {
        const ids = [...document.querySelectorAll('#crmc-etapas [data-etapa]')].map((el) => el.dataset.etapa);
        unaVez(async () => { await api('reordenarEtapasAjax', { post: { id_embudo: idEmbudo, ids } }); await recargarDatos(); });
    }

    // ── Orígenes y motivos ───────────────────────────────────────────────────
    const DESTINO = { origenes: 'crmc-lista-origenes', motivos: 'crmc-lista-motivos' };

    function pintarCatalogo(cat) {
        const items = st.datos[cat] || [];
        const ed = cfg.perm.actualizar;
        $(DESTINO[cat]).innerHTML = items.map((i) => `
            <div class="d-flex gap-1 align-items-center" data-cat="${cat}" data-id="${i.id}">
                <input type="text" class="form-control form-control-sm" data-campo="nombre" maxlength="150" value="${esc(i.nombre)}" ${ed ? '' : 'disabled'}>
                <select class="form-select form-select-sm" data-campo="estado" style="width:120px" ${ed ? '' : 'disabled'}>
                    <option value="activo" ${i.estado === 'activo' ? 'selected' : ''}>Activo</option>
                    <option value="inactivo" ${i.estado !== 'activo' ? 'selected' : ''}>Inactivo</option></select>
                ${ed ? `<button type="button" class="btn btn-outline-primary btn-sm" onclick="CRMC.guardarItem('${cat}', ${i.id})" title="Guardar"><i class="bi bi-check2"></i></button>` : ''}
                ${cfg.perm.eliminar ? `<button type="button" class="btn btn-outline-danger btn-sm" onclick="CRMC.eliminarItem('${cat}', ${i.id})" title="Eliminar"><i class="bi bi-trash"></i></button>` : ''}
            </div>`).join('')
            + (cfg.perm.crear ? `<div class="d-flex gap-1 mt-2"><input type="text" class="form-control form-control-sm" id="crmc-nuevo-${cat}" maxlength="150" placeholder="Agregar…">
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="CRMC.agregarItem('${cat}')"><i class="bi bi-plus-lg"></i></button></div>` : '');
    }

    async function recargarCatalogo(cat) {
        st.datos = await api('datosAjax');
        pintarCatalogo(cat);
    }

    function agregarItem(cat) {
        const nombre = $(`crmc-nuevo-${cat}`).value.trim();
        if (!nombre) return;
        unaVez(async () => { await api('guardarCatalogoAjax', { post: { catalogo: cat, nombre } }); toast('Agregado.'); await recargarCatalogo(cat); });
    }

    function guardarItem(cat, id) {
        const fila = document.querySelector(`[data-cat="${cat}"][data-id="${id}"]`);
        unaVez(async () => {
            await api('guardarCatalogoAjax', { post: { catalogo: cat, id, nombre: fila.querySelector('[data-campo="nombre"]').value, estado: fila.querySelector('[data-campo="estado"]').value } });
            toast('Guardado.');
            await recargarCatalogo(cat);
        });
    }

    async function eliminarItem(cat, id) {
        if (!(await confirmar('¿Eliminar? Las oportunidades que ya lo usan lo conservan.', 'Sí, eliminar'))) return;
        unaVez(async () => { await api('eliminarCatalogoAjax', { post: { catalogo: cat, id } }); toast('Eliminado.'); await recargarCatalogo(cat); });
    }

    // ── Arranque ─────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', async () => {
        if (!$('crmc-lista-canales')) return;
        if (await soyVentanaDeAutorizacion()) return;   // ventana de Meta/LinkedIn que volvió: avisa y se cierra
        iniciarAcordeon();
        try {
            await cargarDatos();
            await cargarCanales();
        } catch (e) {
            aviso('error', 'No se pudo cargar la configuración', e.message);
        }
        cargarLinkedin();
    });

    window.CRMC = {
        conectarInstagram, conectarAbrir, guiaAbrir, guiaIr, guiaMover, guiaConectar,
        probar, desconectar, guardarCanal, reglaCambiada,
        guardarApp, quitarApp, regenerarToken, copiar, guardarLinkedin, quitarLinkedin, conectarLinkedin,
        elegirEmbudo, nuevoEmbudo, guardarEmbudo, eliminarEmbudo, agregarEtapa, guardarEtapa, eliminarEtapa,
        agregarItem, guardarItem, eliminarItem,
    };
})();
