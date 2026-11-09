/**
 * Configuración del CRM → pestaña «Asistente virtual» (fase 2).
 * Servidor: CrmConfiguracionController::botAjax / guardarBotAjax / probarBotAjax.
 * Diseño: docs/diseno/crm-redes-fase2.md §8.
 */
(function () {
    'use strict';

    const cfg = window.CRMC_CONFIG || {};
    const $ = (id) => document.getElementById(id);
    const esc = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const DIAS = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

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
    const dinero = (n) => '$' + Number(n || 0).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
    const entero = (n) => Number(n || 0).toLocaleString('es-EC');

    const st = { cargado: false, guardando: false, probando: false, chat: [] };

    // ── Carga ────────────────────────────────────────────────────────────────
    async function cargarCanales() {
        const d = await api('canalesAjax');
        const sel = $('bot-canal');
        // El asistente solo atiende Instagram (LinkedIn no tiene mensajes en el sistema).
        const canales = (d.canales || []).filter((c) => c.estado !== 'liberado' && c.tipo === 'instagram');
        sel.innerHTML = canales.map((c) => `<option value="${c.id}">@${esc(c.usuario || c.nombre || c.id)}${c.estado !== 'activo' ? ' (desconectada)' : ''}</option>`).join('');
        return canales.length;
    }

    async function cargar() {
        let hay;
        try { hay = await cargarCanales(); } catch (e) { $('bot-aviso').innerHTML = alerta('danger', e.message); return; }
        if (!hay) {
            $('bot-form').classList.add('d-none');
            $('bot-aviso').innerHTML = alerta('info', 'Conecte primero una cuenta de Instagram en la pestaña Canales.');
            return;
        }
        $('bot-form').classList.remove('d-none');
        await cargarConfig();
        st.cargado = true;
    }

    async function cargarConfig() {
        let d;
        try { d = await api('botAjax', { params: { id_canal: $('bot-canal').value } }); }
        catch (e) { $('bot-aviso').innerHTML = alerta('danger', e.message); return; }
        const c = d.config;
        $('bot-activo').checked = !!c.activo;
        $('bot-nombre').value = c.nombre_asistente || '';
        $('bot-tono').value = c.tono;
        $('bot-presentacion').value = c.presentacion || '';
        $('bot-instrucciones').value = c.instrucciones || '';
        $('bot-prohibidos').value = c.temas_prohibidos || '';
        $('bot-horario').value = c.horario;
        $('bot-stock').checked = !!c.mostrar_stock;
        $('bot-oportunidades').checked = !!c.crear_oportunidades;
        $('bot-max-conv').value = c.max_respuestas_conv;
        $('bot-max-dia').value = c.max_respuestas_dia;
        pintarDias(c.horario_atencion || {});
        verHorario();
        pintarIa(d.ia, d.consumo);
        if (!cfg.perm.actualizar) {
            $('bot-form').querySelectorAll('input, select, textarea').forEach((el) => { el.disabled = true; });
        }
    }

    function alerta(tipo, texto) {
        return `<div class="alert alert-${tipo} py-2 small mb-3">${texto}</div>`;
    }

    function pintarIa(ia, consumo) {
        let estado;
        if (!ia.configurada) {
            estado = alerta('warning', '<i class="bi bi-exclamation-triangle me-1"></i>La empresa no tiene configurada la IA. Vaya a <b>IA Soporte → Configuración</b> y elija <b>Anthropic (Claude)</b> con su API key.');
        } else if (!ia.admite_asistente) {
            estado = alerta('warning', `<i class="bi bi-exclamation-triangle me-1"></i>La IA de la empresa es <b>${esc(ia.proveedor_nombre || ia.proveedor)}</b>; el asistente necesita <b>Anthropic (Claude)</b>. Cámbielo en IA Soporte → Configuración.`);
        } else {
            estado = `<div class="mb-2"><i class="bi bi-check-circle text-success me-1"></i>${esc(ia.proveedor_nombre)} · <code>${esc(ia.modelo)}</code></div>`;
        }
        $('bot-aviso').innerHTML = ia.admite_asistente ? '' : estado;
        const c = consumo || {};
        $('bot-consumo').innerHTML = `${estado}
            <div class="row g-2 text-center">
                <div class="col-4"><div class="fw-bold fs-5">${entero(c.respuestas)}</div><div class="text-muted">respuestas</div></div>
                <div class="col-4"><div class="fw-bold fs-5">${entero(c.traspasos)}</div><div class="text-muted">pasadas a persona</div></div>
                <div class="col-4"><div class="fw-bold fs-5">${entero(c.errores)}</div><div class="text-muted">con error</div></div>
            </div>
            <div class="mt-2 text-muted">Tokens: ${entero(c.tokens_entrada)} de entrada · ${entero(c.tokens_salida)} de salida · ${entero(c.pruebas)} prueba(s).
            Costo estimado: <b>${dinero(c.costo)}</b> (referencial; lo cobra su proveedor de IA).</div>`;
    }

    // ── Horario ──────────────────────────────────────────────────────────────
    function pintarDias(h) {
        $('bot-dias').innerHTML = DIAS.map((nom, i) => {
            const t = h[String(i + 1)] || ['', ''];
            return `<div class="d-flex align-items-center gap-2" data-dia="${i + 1}">
                <span class="small" style="width:80px;">${nom}</span>
                <input type="time" class="form-control form-control-sm" style="width:115px;" value="${esc(t[0])}" data-ini>
                <span class="small text-muted">a</span>
                <input type="time" class="form-control form-control-sm" style="width:115px;" value="${esc(t[1])}" data-fin>
            </div>`;
        }).join('') + '<div class="small text-muted">Deje vacío el día que no atiende (ese día responde el asistente todo el día).</div>';
    }

    function verHorario() {
        $('bot-horario-dias').classList.toggle('d-none', $('bot-horario').value !== 'fuera_horario');
    }

    function leerDias() {
        const h = {};
        document.querySelectorAll('#bot-dias [data-dia]').forEach((row) => {
            const ini = row.querySelector('[data-ini]').value;
            const fin = row.querySelector('[data-fin]').value;
            if (ini || fin) h[row.dataset.dia] = [ini, fin];
        });
        return h;
    }

    // ── Guardar ──────────────────────────────────────────────────────────────
    async function guardar() {
        if (st.guardando || !cfg.perm.actualizar) return;
        st.guardando = true;
        const btn = $('bot-btn-guardar');
        if (btn) btn.disabled = true;
        try {
            const r = await api('guardarBotAjax', {
                post: {
                    id_canal: $('bot-canal').value,
                    activo: $('bot-activo').checked ? '1' : '0',
                    nombre_asistente: $('bot-nombre').value,
                    tono: $('bot-tono').value,
                    presentacion: $('bot-presentacion').value,
                    instrucciones: $('bot-instrucciones').value,
                    temas_prohibidos: $('bot-prohibidos').value,
                    horario: $('bot-horario').value,
                    horario_atencion: JSON.stringify(leerDias()),
                    mostrar_stock: $('bot-stock').checked ? '1' : '0',
                    crear_oportunidades: $('bot-oportunidades').checked ? '1' : '0',
                    max_respuestas_conv: $('bot-max-conv').value,
                    max_respuestas_dia: $('bot-max-dia').value,
                },
            });
            toast(r.mensaje || 'Guardado.');
            await cargarConfig();
        } catch (e) {
            aviso('error', 'No se pudo guardar', e.message);
        } finally {
            st.guardando = false;
            if (btn) btn.disabled = false;
        }
    }

    // ── Probar ───────────────────────────────────────────────────────────────
    function pintarChat(extra = '') {
        $('bot-chat').innerHTML = st.chat.map((m) => {
            const out = m.rol === 'assistant';
            return `<div class="d-flex ${out ? 'justify-content-start' : 'justify-content-end'} mb-1">
                <div class="px-2 py-1 rounded-3 small ${out ? 'bg-white border' : 'bg-primary text-white'}" style="max-width:85%; white-space:pre-wrap;">${esc(m.contenido)}</div>
            </div>${(m.acciones || []).map((a) => `<div class="small text-muted fst-italic mb-1"><i class="bi bi-gear me-1"></i>${esc(a)}</div>`).join('')}`;
        }).join('') + extra || '<div class="small text-muted text-center mt-5">Sin mensajes.</div>';
        $('bot-chat').scrollTop = $('bot-chat').scrollHeight;
    }

    async function probar() {
        const txt = $('bot-prueba-texto').value.trim();
        if (!txt || st.probando) return;
        st.probando = true;
        const btn = $('bot-btn-probar');
        btn.disabled = true;
        st.chat.push({ rol: 'user', contenido: txt });
        $('bot-prueba-texto').value = '';
        pintarChat('<div class="small text-muted"><span class="spinner-border spinner-border-sm me-1"></span>Pensando…</div>');
        try {
            const r = await api('probarBotAjax', {
                post: { id_canal: $('bot-canal').value, mensajes: JSON.stringify(st.chat.map(({ rol, contenido }) => ({ rol, contenido }))) },
            });
            st.chat.push({ rol: 'assistant', contenido: r.respuesta || '(sin respuesta)', acciones: r.acciones || [] });
            pintarChat();
        } catch (e) {
            st.chat.pop();
            pintarChat();
            $('bot-prueba-texto').value = txt;
            aviso('error', 'No se pudo probar', e.message);
        } finally {
            st.probando = false;
            btn.disabled = false;
        }
    }

    function reiniciarPrueba() {
        st.chat = [];
        pintarChat();
    }

    // ── Arranque ─────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        const tab = $('crmc-tab-asistente');
        if (!tab) return;
        tab.addEventListener('shown.bs.tab', () => { if (!st.cargado) cargar(); });
        $('bot-canal').addEventListener('change', () => { reiniciarPrueba(); cargarConfig(); });
        if ($('bot-chat')) pintarChat();
    });

    window.CRMBOT = { guardar, probar, reiniciarPrueba, verHorario };
})();
