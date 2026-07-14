(function () {
    'use strict';

    const BASE = window.IA_SOPORTE_URL;
    const PERM = window.IA_SOPORTE_PERM || {};

    let conversacionActualId = null;
    let pollDocumentosTimer = null;

    document.addEventListener('DOMContentLoaded', () => {
        initTabs();
        cargarAgentes();
        cargarConversaciones();

        const formMensaje = document.getElementById('iaFormMensaje');
        if (formMensaje) formMensaje.addEventListener('submit', enviarMensaje);

        const btnNuevaConv = document.getElementById('iaBtnNuevaConv');
        if (btnNuevaConv) btnNuevaConv.addEventListener('click', crearConversacion);

        const formSubirDoc = document.getElementById('iaFormSubirDoc');
        if (formSubirDoc) formSubirDoc.addEventListener('submit', subirDocumento);

        const formConfig = document.getElementById('iaFormConfig');
        if (formConfig) formConfig.addEventListener('submit', guardarConfig);
    });

    // ── Tabs ─────────────────────────────────────────────────────────────────

    function initTabs() {
        document.querySelectorAll('[data-ia-tab]').forEach((btn) => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('[data-ia-tab]').forEach((b) => b.classList.remove('active'));
                document.querySelectorAll('[data-ia-tab-content]').forEach((c) => c.classList.add('d-none'));
                btn.classList.add('active');
                const tab = btn.getAttribute('data-ia-tab');
                document.querySelector('[data-ia-tab-content="' + tab + '"]').classList.remove('d-none');

                if (tab === 'documentos') cargarDocumentos();
                if (tab === 'config') cargarConfig();
            });
        });
    }

    // ── Agentes ──────────────────────────────────────────────────────────────

    function cargarAgentes() {
        fetch(BASE + '/agentesListar')
            .then((r) => r.json())
            .then((res) => {
                const sel = document.getElementById('iaSelectAgente');
                if (!sel) return;
                sel.innerHTML = '';
                (res.data || []).forEach((a) => {
                    const opt = document.createElement('option');
                    opt.value = a.id;
                    opt.textContent = a.nombre;
                    sel.appendChild(opt);
                });
            });
    }

    // ── Conversaciones ───────────────────────────────────────────────────────

    function cargarConversaciones(seleccionarId) {
        fetch(BASE + '/conversacionesListar')
            .then((r) => r.json())
            .then((res) => {
                const cont = document.getElementById('iaListaConversaciones');
                if (!cont) return;
                const rows = res.data || [];
                if (rows.length === 0) {
                    cont.innerHTML = '<p class="text-muted small text-center mt-3">Sin conversaciones aún.</p>';
                    return;
                }
                cont.innerHTML = rows.map((c) => `
                    <div class="ia-soporte-conv-item p-2 mb-1 d-flex justify-content-between align-items-center ${c.id == conversacionActualId ? 'active' : ''}" data-id="${c.id}">
                        <div class="text-truncate" style="max-width: 200px;">
                            <i class="bi ${c.icono_agente || 'bi-robot'} me-1"></i>
                            <span class="small">${escapeHtml(c.titulo || 'Conversación')}</span>
                        </div>
                        ${PERM.eliminar ? `<button type="button" class="btn btn-sm btn-link text-danger p-0 ia-btn-eliminar-conv" data-id="${c.id}" title="Eliminar"><i class="bi bi-trash"></i></button>` : ''}
                    </div>
                `).join('');

                cont.querySelectorAll('.ia-soporte-conv-item').forEach((el) => {
                    el.addEventListener('click', (ev) => {
                        if (ev.target.closest('.ia-btn-eliminar-conv')) return;
                        seleccionarConversacion(parseInt(el.getAttribute('data-id'), 10));
                    });
                });
                cont.querySelectorAll('.ia-btn-eliminar-conv').forEach((btn) => {
                    btn.addEventListener('click', (ev) => {
                        ev.stopPropagation();
                        eliminarConversacion(parseInt(btn.getAttribute('data-id'), 10));
                    });
                });

                if (seleccionarId) {
                    seleccionarConversacion(seleccionarId);
                } else if (conversacionActualId) {
                    document.querySelectorAll('.ia-soporte-conv-item').forEach((el) => {
                        el.classList.toggle('active', el.getAttribute('data-id') == conversacionActualId);
                    });
                }
            });
    }

    function crearConversacion() {
        const idAgente = document.getElementById('iaSelectAgente').value;
        if (!idAgente) {
            alert('Seleccione un agente primero.');
            return;
        }
        const fd = new FormData();
        fd.append('id_agente', idAgente);
        fd.append('titulo', 'Nueva conversación');

        fetch(BASE + '/conversacionCrear', { method: 'POST', body: fd })
            .then((r) => r.json())
            .then((res) => {
                if (!res.ok) { alert(res.error || 'No se pudo crear la conversación.'); return; }
                cargarConversaciones(res.id);
            });
    }

    function eliminarConversacion(id) {
        if (!confirm('¿Eliminar esta conversación?')) return;
        const fd = new FormData();
        fd.append('id', id);
        fetch(BASE + '/conversacionEliminar', { method: 'POST', body: fd })
            .then((r) => r.json())
            .then((res) => {
                if (!res.ok) { alert(res.error || 'No se pudo eliminar.'); return; }
                if (conversacionActualId == id) {
                    conversacionActualId = null;
                    document.getElementById('iaChatMensajes').innerHTML =
                        '<p class="text-muted text-center mt-5">Seleccione o cree una conversación para empezar.</p>';
                    toggleComposer(false);
                }
                cargarConversaciones();
            });
    }

    function seleccionarConversacion(id) {
        conversacionActualId = id;
        document.querySelectorAll('.ia-soporte-conv-item').forEach((el) => {
            el.classList.toggle('active', el.getAttribute('data-id') == id);
        });
        toggleComposer(true);
        cargarMensajes();
    }

    function toggleComposer(activo) {
        document.getElementById('iaInputPregunta').disabled = !activo;
        document.getElementById('iaBtnEnviar').disabled = !activo;
    }

    // ── Mensajes / chat ──────────────────────────────────────────────────────

    function cargarMensajes() {
        const cont = document.getElementById('iaChatMensajes');
        cont.innerHTML = '<p class="text-muted text-center mt-3">Cargando…</p>';

        fetch(BASE + '/mensajesListar?id_conversacion=' + conversacionActualId)
            .then((r) => r.json())
            .then((res) => {
                if (!res.ok) { cont.innerHTML = '<p class="text-danger text-center mt-3">' + escapeHtml(res.error || 'Error') + '</p>'; return; }
                const mensajes = res.data || [];
                if (mensajes.length === 0) {
                    cont.innerHTML = '<p class="text-muted text-center mt-3">Escriba su primera pregunta.</p>';
                    return;
                }
                cont.innerHTML = mensajes.map(renderMensaje).join('');
                cont.scrollTop = cont.scrollHeight;
            });
    }

    function renderMensaje(m) {
        const fuentesHtml = (m.fuentes && m.fuentes.length)
            ? '<div class="ia-soporte-fuentes"><i class="bi bi-bookmark"></i> Fuentes: ' +
              m.fuentes.map((f) => escapeHtml(f.titulo) + (f.pagina ? ' (pág. ' + f.pagina + ')' : '')).join(' · ') +
              '</div>'
            : '';
        return `
            <div class="ia-soporte-msg ${m.rol}">
                <div class="bubble">${escapeHtml(m.contenido).replace(/\n/g, '<br>')}</div>
                ${m.rol === 'assistant' ? fuentesHtml : ''}
            </div>
        `;
    }

    function enviarMensaje(ev) {
        ev.preventDefault();
        if (!conversacionActualId) return;

        const input = document.getElementById('iaInputPregunta');
        const pregunta = input.value.trim();
        if (pregunta === '') return;

        const cont = document.getElementById('iaChatMensajes');
        cont.innerHTML += renderMensaje({ rol: 'user', contenido: pregunta });
        cont.innerHTML += '<div class="ia-soporte-msg assistant" id="iaMsgPendiente"><div class="bubble text-muted"><i class="bi bi-three-dots"></i> Pensando…</div></div>';
        cont.scrollTop = cont.scrollHeight;

        input.value = '';
        toggleComposer(false);

        const fd = new FormData();
        fd.append('id_conversacion', conversacionActualId);
        fd.append('pregunta', pregunta);

        fetch(BASE + '/mensajeEnviar', { method: 'POST', body: fd })
            .then((r) => r.json())
            .then((res) => {
                const pendiente = document.getElementById('iaMsgPendiente');
                if (pendiente) pendiente.remove();
                if (!res.ok) {
                    cont.innerHTML += `<div class="ia-soporte-msg assistant"><div class="bubble text-danger">${escapeHtml(res.error || 'Ocurrió un error.')}</div></div>`;
                } else {
                    cont.innerHTML += renderMensaje({ rol: 'assistant', contenido: res.data.contenido, fuentes: res.data.fuentes });
                    cargarConversaciones();
                }
                cont.scrollTop = cont.scrollHeight;
                toggleComposer(true);
                document.getElementById('iaInputPregunta').focus();
            })
            .catch(() => {
                const pendiente = document.getElementById('iaMsgPendiente');
                if (pendiente) pendiente.remove();
                cont.innerHTML += '<div class="ia-soporte-msg assistant"><div class="bubble text-danger">Error de conexión.</div></div>';
                toggleComposer(true);
            });
    }

    // ── Documentos ───────────────────────────────────────────────────────────

    function cargarDocumentos() {
        fetch(BASE + '/documentosListar')
            .then((r) => r.json())
            .then((res) => {
                const tbody = document.getElementById('iaTablaDocumentos');
                const rows = res.data || [];
                if (rows.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No hay documentos cargados.</td></tr>';
                } else {
                    tbody.innerHTML = rows.map(renderFilaDocumento).join('');
                    tbody.querySelectorAll('.ia-btn-eliminar-doc').forEach((btn) => {
                        btn.addEventListener('click', () => eliminarDocumento(btn.getAttribute('data-id')));
                    });
                    tbody.querySelectorAll('.ia-btn-reintentar-doc').forEach((btn) => {
                        btn.addEventListener('click', () => reintentarDocumento(btn.getAttribute('data-id')));
                    });
                }

                clearTimeout(pollDocumentosTimer);
                const enProceso = rows.some((r) => r.estado === 'pendiente' || r.estado === 'procesando');
                if (enProceso) {
                    pollDocumentosTimer = setTimeout(cargarDocumentos, 4000);
                }
            });
    }

    function renderFilaDocumento(d) {
        const badges = {
            pendiente: 'secondary', procesando: 'info', listo: 'success', error: 'danger',
        };
        const claseBadge = badges[d.estado] || 'secondary';
        let accionesHtml = '';
        if (d.estado === 'error' && PERM.actualizar) {
            accionesHtml += `<button type="button" class="btn btn-sm btn-outline-warning ia-btn-reintentar-doc" data-id="${d.id}" title="Reintentar"><i class="bi bi-arrow-clockwise"></i></button> `;
        }
        if (PERM.eliminar) {
            accionesHtml += `<button type="button" class="btn btn-sm btn-outline-danger ia-btn-eliminar-doc" data-id="${d.id}" title="Eliminar"><i class="bi bi-trash"></i></button>`;
        }
        return `
            <tr>
                <td data-col="titulo">${escapeHtml(d.titulo)}</td>
                <td data-col="categoria">${escapeHtml(d.categoria || '—')}</td>
                <td data-col="paginas" class="text-center">${d.paginas ?? '—'}</td>
                <td data-col="estado" class="text-center">
                    <span class="badge bg-${claseBadge} bg-opacity-10 text-${claseBadge} border border-${claseBadge} border-opacity-25">${d.estado}</span>
                    ${d.estado === 'error' && d.error_mensaje ? `<div class="small text-danger mt-1">${escapeHtml(d.error_mensaje)}</div>` : ''}
                </td>
                <td data-col="created_at">${escapeHtml(d.created_at || '—')}</td>
                <td class="text-end pe-3">${accionesHtml}</td>
            </tr>
        `;
    }

    function subirDocumento(ev) {
        ev.preventDefault();
        const form = ev.target;
        const fd = new FormData(form);
        const errBox = document.getElementById('iaSubirDocError');
        errBox.classList.add('d-none');

        const btn = document.getElementById('iaBtnSubirDoc');
        btn.disabled = true;

        fetch(BASE + '/documentoSubir', { method: 'POST', body: fd })
            .then((r) => r.json())
            .then((res) => {
                btn.disabled = false;
                if (!res.ok) {
                    errBox.textContent = res.error || 'No se pudo subir el documento.';
                    errBox.classList.remove('d-none');
                    return;
                }
                form.reset();
                const modalEl = document.getElementById('iaModalSubirDoc');
                const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                modal.hide();
                cargarDocumentos();
            })
            .catch(() => {
                btn.disabled = false;
                errBox.textContent = 'Error de conexión.';
                errBox.classList.remove('d-none');
            });
    }

    function reintentarDocumento(id) {
        const fd = new FormData();
        fd.append('id', id);
        fetch(BASE + '/documentoReintentar', { method: 'POST', body: fd })
            .then((r) => r.json())
            .then(() => cargarDocumentos());
    }

    function eliminarDocumento(id) {
        if (!confirm('¿Eliminar este documento? Ya no estará disponible para las consultas.')) return;
        const fd = new FormData();
        fd.append('id', id);
        fetch(BASE + '/documentoEliminar', { method: 'POST', body: fd })
            .then((r) => r.json())
            .then((res) => {
                if (!res.ok) { alert(res.error || 'No se pudo eliminar.'); return; }
                cargarDocumentos();
            });
    }

    // ── Configuración BYOK ───────────────────────────────────────────────────

    function cargarConfig() {
        fetch(BASE + '/configGet')
            .then((r) => r.json())
            .then((res) => {
                if (!res.ok) return;
                const d = res.data;
                document.getElementById('iaConfigProveedor').value = d.proveedor;
                document.getElementById('iaConfigModelo').value = d.modelo_chat;
                document.getElementById('iaConfigEstadoTexto').innerHTML = d.configurado
                    ? '<span class="text-success"><i class="bi bi-check-circle"></i> API key configurada. Déjelo en blanco para conservarla, o escriba una nueva para reemplazarla.</span>'
                    : '<span class="text-muted">Aún no hay una API key configurada.</span>';
            });
    }

    function guardarConfig(ev) {
        ev.preventDefault();
        const fd = new FormData(ev.target);
        fetch(BASE + '/configStore', { method: 'POST', body: fd })
            .then((r) => r.json())
            .then((res) => {
                alert(res.ok ? (res.msg || 'Guardado.') : (res.error || 'No se pudo guardar.'));
                if (res.ok) {
                    document.getElementById('iaConfigApiKey').value = '';
                    cargarConfig();
                }
            });
    }

    // ── Utils ────────────────────────────────────────────────────────────────

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML;
    }
})();
