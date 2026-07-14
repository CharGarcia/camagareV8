(function () {
    'use strict';

    const BASE = window.IA_SOPORTE_URL;
    const PERM = window.IA_SOPORTE_PERM || {};

    let conversacionActualId = null;
    let pollDocumentosTimer = null;
    let agentesCache = [];

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

        const formDocAgentes = document.getElementById('iaFormDocAgentes');
        if (formDocAgentes) formDocAgentes.addEventListener('submit', guardarDocAgentes);

        const formConfig = document.getElementById('iaFormConfig');
        if (formConfig) formConfig.addEventListener('submit', guardarConfig);

        const btnNuevoPrompt = document.getElementById('iaBtnNuevoPrompt');
        if (btnNuevoPrompt) btnNuevoPrompt.addEventListener('click', () => abrirModalPrompt(null));

        const formPrompt = document.getElementById('iaFormPrompt');
        if (formPrompt) formPrompt.addEventListener('submit', guardarPrompt);

        const btnEliminarPrompt = document.getElementById('iaBtnEliminarPrompt');
        if (btnEliminarPrompt) btnEliminarPrompt.addEventListener('click', eliminarPrompt);
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
                if (tab === 'prompts') cargarPrompts();
            });
        });
    }

    // ── Agentes ──────────────────────────────────────────────────────────────

    function cargarAgentes() {
        fetch(BASE + '/agentesListar')
            .then((r) => r.json())
            .then((res) => {
                agentesCache = res.data || [];

                const sel = document.getElementById('iaSelectAgente');
                if (sel) {
                    sel.innerHTML = '';
                    agentesCache.forEach((a) => {
                        const opt = document.createElement('option');
                        opt.value = a.id;
                        opt.textContent = a.nombre;
                        sel.appendChild(opt);
                    });
                }

                renderChecksAgentes('iaSubirDocAgentes', []);
            });
    }

    /** Dibuja checkboxes de agentes en `contenedorId`, marcando los ids en `idsSeleccionados`. */
    function renderChecksAgentes(contenedorId, idsSeleccionados) {
        const cont = document.getElementById(contenedorId);
        if (!cont) return;
        if (agentesCache.length === 0) {
            cont.innerHTML = '<p class="text-muted small mb-0">No hay agentes disponibles.</p>';
            return;
        }
        const seleccionados = (idsSeleccionados || []).map(String);
        cont.innerHTML = agentesCache.map((a) => `
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="id_agentes[]" value="${a.id}"
                       id="${contenedorId}-a-${a.id}" ${seleccionados.includes(String(a.id)) ? 'checked' : ''}>
                <label class="form-check-label small" for="${contenedorId}-a-${a.id}">
                    <i class="bi ${escapeHtml(a.icono || 'bi-robot')}"></i> ${escapeHtml(a.nombre)}
                </label>
            </div>
        `).join('');
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
                        <div class="text-truncate" style="max-width: 160px;">
                            <i class="bi ${c.icono_agente || 'bi-robot'} me-1"></i>
                            <span class="small">${escapeHtml(c.titulo || 'Conversación')}</span>
                        </div>
                        <div class="d-flex align-items-center flex-shrink-0">
                            ${PERM.actualizar ? `<button type="button" class="btn btn-sm btn-link text-secondary p-0 me-2 ia-btn-renombrar-conv" data-id="${c.id}" data-titulo="${escapeHtml(c.titulo || '')}" title="Renombrar"><i class="bi bi-pencil"></i></button>` : ''}
                            ${PERM.eliminar ? `<button type="button" class="btn btn-sm btn-link text-danger p-0 ia-btn-eliminar-conv" data-id="${c.id}" title="Eliminar"><i class="bi bi-trash"></i></button>` : ''}
                        </div>
                    </div>
                `).join('');

                cont.querySelectorAll('.ia-soporte-conv-item').forEach((el) => {
                    el.addEventListener('click', (ev) => {
                        if (ev.target.closest('.ia-btn-eliminar-conv') || ev.target.closest('.ia-btn-renombrar-conv')) return;
                        seleccionarConversacion(parseInt(el.getAttribute('data-id'), 10));
                    });
                });
                cont.querySelectorAll('.ia-btn-eliminar-conv').forEach((btn) => {
                    btn.addEventListener('click', (ev) => {
                        ev.stopPropagation();
                        eliminarConversacion(parseInt(btn.getAttribute('data-id'), 10));
                    });
                });
                cont.querySelectorAll('.ia-btn-renombrar-conv').forEach((btn) => {
                    btn.addEventListener('click', (ev) => {
                        ev.stopPropagation();
                        renombrarConversacion(parseInt(btn.getAttribute('data-id'), 10), btn.getAttribute('data-titulo') || '');
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

    function renombrarConversacion(id, tituloActual) {
        const nuevoTitulo = prompt('Nuevo nombre de la conversación:', tituloActual || '');
        if (nuevoTitulo === null) return;
        if (nuevoTitulo.trim() === '') { alert('El nombre no puede estar vacío.'); return; }

        const fd = new FormData();
        fd.append('id', id);
        fd.append('titulo', nuevoTitulo.trim());
        fetch(BASE + '/conversacionRenombrar', { method: 'POST', body: fd })
            .then((r) => r.json())
            .then((res) => {
                if (!res.ok) { alert(res.error || 'No se pudo renombrar.'); return; }
                cargarConversaciones();
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
        const tieneFuentes = Array.isArray(m.fuentes) && m.fuentes.length > 0;
        const sinContexto = m.rol === 'assistant' && Array.isArray(m.fuentes) && m.fuentes.length === 0;

        const fuentesHtml = tieneFuentes
            ? '<div class="ia-soporte-fuentes"><i class="bi bi-bookmark"></i> Fuentes: ' +
              m.fuentes.map((f) => escapeHtml(f.titulo) + (f.pagina ? ' (pág. ' + f.pagina + ')' : '')).join(' · ') +
              '</div>'
            : '';
        const avisoHtml = sinContexto
            ? '<div class="ia-soporte-fuentes text-warning"><i class="bi bi-exclamation-triangle"></i> '
              + 'No se encontró contenido relevante en los documentos cargados para esta pregunta: '
              + 'esta respuesta es general, no está basada en sus PDFs.</div>'
            : '';

        return `
            <div class="ia-soporte-msg ${m.rol}">
                <div class="bubble">${escapeHtml(m.contenido).replace(/\n/g, '<br>')}</div>
                ${m.rol === 'assistant' ? (fuentesHtml || avisoHtml) : ''}
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
                    tbody.querySelectorAll('.ia-btn-doc-agentes').forEach((btn) => {
                        btn.addEventListener('click', () => {
                            const doc = rows.find((r) => r.id == btn.getAttribute('data-id'));
                            if (doc) abrirModalDocAgentes(doc);
                        });
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
        if (PERM.actualizar) {
            accionesHtml += `<button type="button" class="btn btn-sm btn-outline-secondary ia-btn-doc-agentes" data-id="${d.id}" title="Agentes que pueden usar este documento"><i class="bi bi-people"></i></button> `;
        }
        if (d.estado === 'error' && PERM.actualizar) {
            accionesHtml += `<button type="button" class="btn btn-sm btn-outline-warning ia-btn-reintentar-doc" data-id="${d.id}" title="Reintentar"><i class="bi bi-arrow-clockwise"></i></button> `;
        }
        if (PERM.eliminar) {
            accionesHtml += `<button type="button" class="btn btn-sm btn-outline-danger ia-btn-eliminar-doc" data-id="${d.id}" title="Eliminar"><i class="bi bi-trash"></i></button>`;
        }
        const agentesHtml = (d.agentes && d.agentes.length)
            ? d.agentes.map((a) => `<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 me-1">${escapeHtml(a.nombre)}</span>`).join('')
            : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Todos</span>';
        return `
            <tr>
                <td data-col="titulo">${escapeHtml(d.titulo)}</td>
                <td data-col="categoria">${escapeHtml(d.categoria || '—')}</td>
                <td data-col="agentes">${agentesHtml}</td>
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

    function abrirModalDocAgentes(doc) {
        document.getElementById('iaDocAgentesId').value = doc.id;
        document.getElementById('iaDocAgentesError').classList.add('d-none');
        renderChecksAgentes('iaDocAgentesLista', (doc.agentes || []).map((a) => a.id));

        const modalEl = document.getElementById('iaModalDocAgentes');
        (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
    }

    function guardarDocAgentes(ev) {
        ev.preventDefault();
        const fd = new FormData(ev.target);
        const errBox = document.getElementById('iaDocAgentesError');
        errBox.classList.add('d-none');

        fetch(BASE + '/documentoAgentesActualizar', { method: 'POST', body: fd })
            .then((r) => r.json())
            .then((res) => {
                if (!res.ok) {
                    errBox.textContent = res.error || 'No se pudo guardar.';
                    errBox.classList.remove('d-none');
                    return;
                }
                const modalEl = document.getElementById('iaModalDocAgentes');
                (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).hide();
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

    // ── Prompts (catálogo global de agentes, solo superadmin) ──────────────────

    let prompts = [];

    function cargarPrompts() {
        fetch(BASE + '/promptsListar')
            .then((r) => r.json())
            .then((res) => {
                const tbody = document.getElementById('iaTablaPrompts');
                if (!res.ok) {
                    tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-4">${escapeHtml(res.error || 'Error')}</td></tr>`;
                    return;
                }
                prompts = res.data || [];
                if (prompts.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No hay prompts registrados.</td></tr>';
                    return;
                }
                tbody.innerHTML = prompts.map((p) => `
                    <tr class="ia-prompt-row" role="button" data-id="${p.id}">
                        <td class="text-center"><i class="bi ${escapeHtml(p.icono || 'bi-robot')}"></i></td>
                        <td class="fw-medium">${escapeHtml(p.nombre)}</td>
                        <td class="text-truncate" style="max-width:360px;">${escapeHtml(p.descripcion || '')}</td>
                        <td class="text-center">${p.orden ?? 0}</td>
                        <td class="text-center">
                            ${p.activo
                                ? '<span class="badge bg-success">Activo</span>'
                                : '<span class="badge bg-secondary">Inactivo</span>'}
                        </td>
                    </tr>
                `).join('');
                tbody.querySelectorAll('.ia-prompt-row').forEach((row) => {
                    row.addEventListener('click', () => {
                        const p = prompts.find((x) => x.id == row.getAttribute('data-id'));
                        if (p) abrirModalPrompt(p);
                    });
                });
            });
    }

    function abrirModalPrompt(prompt) {
        const form = document.getElementById('iaFormPrompt');
        form.reset();
        document.getElementById('iaPromptId').value = prompt ? prompt.id : '';
        document.getElementById('iaPromptNombre').value = prompt ? prompt.nombre : '';
        document.getElementById('iaPromptDescripcion').value = prompt ? (prompt.descripcion || '') : '';
        document.getElementById('iaPromptIcono').value = prompt ? (prompt.icono || 'bi-robot') : 'bi-robot';
        document.getElementById('iaPromptOrden').value = prompt ? (prompt.orden || 0) : 0;
        document.getElementById('iaPromptTexto').value = prompt ? (prompt.prompt_sistema || '') : '';
        document.getElementById('iaPromptActivo').checked = prompt ? !!prompt.activo : true;
        document.getElementById('iaModalPromptTitulo').textContent = prompt ? 'Editar prompt' : 'Nuevo prompt';
        document.getElementById('iaBtnEliminarPrompt').classList.toggle('d-none', !prompt);
        document.getElementById('iaPromptError').classList.add('d-none');

        const modalEl = document.getElementById('iaModalPrompt');
        (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
    }

    function guardarPrompt(ev) {
        ev.preventDefault();
        const id = document.getElementById('iaPromptId').value;
        const fd = new FormData(ev.target);
        const errBox = document.getElementById('iaPromptError');
        errBox.classList.add('d-none');

        fetch(BASE + (id ? '/promptUpdate' : '/promptStore'), { method: 'POST', body: fd })
            .then((r) => r.json())
            .then((res) => {
                if (!res.ok) {
                    errBox.textContent = res.error || 'No se pudo guardar el prompt.';
                    errBox.classList.remove('d-none');
                    return;
                }
                const modalEl = document.getElementById('iaModalPrompt');
                (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).hide();
                cargarPrompts();
                cargarAgentes();
            });
    }

    function eliminarPrompt() {
        const id = document.getElementById('iaPromptId').value;
        if (!id) return;
        if (!confirm('¿Eliminar este prompt? Ya no estará disponible para nuevas conversaciones.')) return;

        const fd = new FormData();
        fd.append('id', id);
        fetch(BASE + '/promptEliminar', { method: 'POST', body: fd })
            .then((r) => r.json())
            .then((res) => {
                if (!res.ok) { alert(res.error || 'No se pudo eliminar.'); return; }
                const modalEl = document.getElementById('iaModalPrompt');
                (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).hide();
                cargarPrompts();
                cargarAgentes();
            });
    }

    // ── Utils ────────────────────────────────────────────────────────────────

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML;
    }
})();
