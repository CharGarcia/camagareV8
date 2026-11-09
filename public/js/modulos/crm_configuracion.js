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
        $('crmc-lista-canales').innerHTML = d.canales.length ? d.canales.map((c) => canalHtml(c, usuarios)).join('')
            : '<div class="text-muted small border rounded p-3 bg-white">No hay cuentas conectadas.</div>';
    }

    function canalHtml(c, usuarios) {
        const [est, cls] = ESTADO_CANAL[c.estado] || [c.estado, 'bg-secondary'];
        const asig = (c.config && c.config.asignacion) || { regla: 'sin_asignar', usuarios: [] };
        const elegidos = (asig.usuarios || []).map(String);
        const activo = c.estado === 'activo';
        const avisos = [];
        if (activo && c.dias_para_vencer !== null && c.dias_para_vencer < 10) avisos.push(`El acceso vence en ${c.dias_para_vencer} día(s); el sistema lo renueva solo cada día.`);
        if (c.permisos_faltantes && c.permisos_faltantes.length) avisos.push('Faltan permisos: ' + c.permisos_faltantes.join(', ') + '. Vuelva a conectar y acéptelos todos.');
        if (activo && c.config && c.config.webhooks_ok === false) avisos.push('Instagram no aceptó la suscripción a mensajes. Vuelva a conectar la cuenta.');
        if (c.estado === 'token_vencido') avisos.push('El acceso venció. Pulse «Reconectar» para seguir recibiendo mensajes.');
        const editable = cfg.perm.actualizar && c.estado !== 'desconectado';
        return `<div class="card border-0 shadow-sm"><div class="card-body py-2">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <i class="bi bi-instagram text-danger me-1"></i><span class="fw-bold">@${esc(c.usuario)}</span>
                    <span class="text-muted small ms-1">${esc(c.nombre || '')}</span>
                    <span class="badge ${cls} ms-2">${est}</span>
                    <div class="small text-muted">Conectada por ${esc(c.conectado_por || '-')} · ${c.token_expira_at ? 'acceso hasta ' + fecha(c.token_expira_at) : 'sin acceso'}</div>
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
        unaVez(async () => {
            const r = await api('guardarAppMetaAjax', { post: { app_id: appId, app_secret: secreto } });
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

    function conectarInstagram() {
        unaVez(async () => {
            const r = await api('conectarInstagramAjax', { post: {} });
            // Instagram pide iniciar sesión y autorizar; vuelve a instagramCallback.
            window.location.href = r.url;
        });
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
        try {
            await cargarDatos();
            await cargarCanales();
        } catch (e) {
            aviso('error', 'No se pudo cargar la configuración', e.message);
        }
    });

    window.CRMC = {
        conectarInstagram, probar, desconectar, guardarCanal, reglaCambiada,
        guardarApp, quitarApp, regenerarToken, copiar,
        elegirEmbudo, nuevoEmbudo, guardarEmbudo, eliminarEmbudo, agregarEtapa, guardarEtapa, eliminarEtapa,
        agregarItem, guardarItem, eliminarItem,
    };
})();
