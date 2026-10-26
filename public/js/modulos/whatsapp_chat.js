let WC_currentChatId = 0;
let WC_currentPhone = '';
let WC_currentNombre = '';
let WC_pollingInterval = null;
let WC_lastMsgSignature = '';

/**
 * Convierte una ruta relativa de media (storage/whatsapp_media/...)
 * en la URL del endpoint PHP seguro que sirve el archivo.
 * @param {string} path  - Ruta relativa almacenada en BD
 * @param {boolean} dl   - true para forzar descarga (Content-Disposition: attachment)
 */
function WC_mediaUrl(path, dl = false) {
    if (!path) return '';
    if (path.startsWith('http')) return path; // URL externa (Meta CDN, etc.)
    const base = `${B_URL}/modulos/whatsapp-chat/serveMedia?file=${encodeURIComponent(path)}`;
    return dl ? base + '&dl=1' : base;
}

document.addEventListener('DOMContentLoaded', () => {
    WC_cargarChats();

    // Auto-resize textarea
    const tx = document.getElementById('waChatInputText');
    if (tx) {
        tx.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight < 120 ? this.scrollHeight : 120) + 'px';
            if (this.value.trim().length > 0) {
                document.getElementById('waChatBtnSend').disabled = false;
            } else {
                document.getElementById('waChatBtnSend').disabled = true;
            }
        });

        tx.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                WC_enviarMensaje();
            }
        });
    }

    const fileInput = document.getElementById('waChatFileInput');
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            if (this.files && this.files.length > 0) {
                WC_subirArchivo(this.files[0]);
                this.value = ''; // Reset
            }
        });
    }

    // Polling cada 5 segundos
    WC_pollingInterval = setInterval(() => {
        WC_cargarChats(true);
        if (WC_currentChatId > 0) {
            WC_cargarMensajes(WC_currentChatId, WC_currentPhone, true);
        }
    }, 5000);
});

function WC_cargarChats(silencioso = false) {
    if (!silencioso) {
        // Mostrar cargando si no es polling
    }
    fetch(`${B_URL}/modulos/whatsapp-chat/getChatsAjax`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.ok) {
            WC_renderChats(data.chats);
        }
    })
    .catch(err => console.error(err));
}

function WC_renderChats(chats) {
    const list = document.getElementById('waChatsList');
    if (!list) return;

    if (chats.length === 0) {
        list.innerHTML = `<div class="p-4 text-center text-muted">No hay conversaciones aún.</div>`;
        return;
    }

    let html = '';
    chats.forEach(c => {
        const isActive = c.id == WC_currentChatId ? 'wa-chat-active' : '';
        const unread = c.mensajes_sin_leer > 0 ? `<span class="badge bg-success rounded-pill ms-auto">${c.mensajes_sin_leer}</span>` : '';
        
        let dateObj = new Date(c.updated_at);
        let timeStr = dateObj.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});

        // Datos en data-* (escapados): un nombre con comilla rompía el onclick armado a mano.
        html += `
        <div class="wa-chat-item p-3 border-bottom d-flex align-items-center ${isActive}"
             data-id="${c.id}" data-tel="${WC_escHtml(c.telefono_cliente)}" data-nombre="${WC_escHtml(c.nombre_cliente || '')}"
             onclick="WC_seleccionarChat(+this.dataset.id, this.dataset.tel, this.dataset.nombre)">
            <div class="bg-secondary bg-opacity-25 text-secondary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 45px; height: 45px; min-width: 45px;">
                <i class="bi bi-person-fill fs-4"></i>
            </div>
            <div class="flex-grow-1 overflow-hidden">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <h6 class="m-0 text-truncate fw-bold">${WC_escHtml(c.nombre_cliente || c.telefono_cliente)}</h6>
                    <small class="text-muted" style="font-size: 11px;">${timeStr}</small>
                </div>
                <div class="d-flex align-items-center">
                    <small class="text-muted text-truncate flex-grow-1" style="max-width: 80%;">${WC_escHtml(c.ultimo_mensaje || '')}</small>
                    ${unread}
                </div>
            </div>
        </div>`;
    });

    list.innerHTML = html;
}

function WC_seleccionarChat(id, telefono, nombre) {
    WC_currentChatId = id;
    WC_currentPhone = telefono;
    WC_lastMsgSignature = ''; // resetear para forzar render en la primera carga

    // UI Updates
    document.getElementById('waChatEmptyState').classList.add('d-none');
    document.getElementById('waChatHeader').classList.remove('d-none');
    document.getElementById('waChatMessages').classList.remove('d-none');
    // El área de escritura o el aviso de 24 h se decide al llegar los mensajes (WC_aplicarVentana).
    document.getElementById('waChatInputArea').classList.add('d-none');
    document.getElementById('waChatVentanaCerrada').classList.add('d-none');
    WC_currentNombre = nombre || '';

    document.getElementById('waChatName').innerText = nombre || telefono;
    document.getElementById('waChatPhone').innerText = telefono;

    // Resaltar en la lista
    document.querySelectorAll('.wa-chat-item').forEach(el => el.classList.remove('wa-chat-active'));
    // (El polling de cargarChats re-aplicará la clase en el próximo ciclo)

    // Mobile: mostrar panel de chat, ocultar lista
    if (window.innerWidth < 768) {
        document.getElementById('chatListContainer').classList.add('wa-lista-oculta');
        document.getElementById('chatWindowContainer').classList.add('wa-chat-abierto');
    }

    WC_cargarMensajes(id, telefono, false);
}

function WC_volverALista() {
    document.getElementById('chatListContainer').classList.remove('wa-lista-oculta');
    document.getElementById('chatWindowContainer').classList.remove('wa-chat-abierto');
}

function WC_cargarMensajes(idChat, telefono, silencioso = false) {
    fetch(`${B_URL}/modulos/whatsapp-chat/getMensajesAjax?id_chat=${idChat}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        // Una respuesta de un chat que ya no está abierto no debe pintarse encima del actual.
        if (data.ok && idChat === WC_currentChatId) {
            WC_renderMensajes(data.mensajes, !silencioso);
            WC_aplicarVentana(data.ventana);
        }
    })
    .catch(err => console.error(err));
}

/**
 * Ventana de 24 h de Meta: abierta → se escribe libremente; cerrada → solo plantilla.
 * Sin dato (respuesta antigua) se deja escribir, como antes.
 */
function WC_aplicarVentana(ventana) {
    const abierta = !ventana || !!ventana.abierta;
    document.getElementById('waChatInputArea').classList.toggle('d-none', !abierta);
    document.getElementById('waChatVentanaCerrada').classList.toggle('d-none', abierta);
    if (!abierta) WC_toggleRespuestasPanel(false);
}

function WC_renderMensajes(mensajes, esInicial = false) {
    const container = document.getElementById('waChatMessages');
    if (!container) return;

    // Firma del estado actual: id + estado_meta de cada mensaje
    const firma = mensajes.map(m => m.id + ':' + (m.estado_meta || '')).join('|');
    if (!esInicial && firma === WC_lastMsgSignature) {
        return; // nada cambió, evitar reconstruir el DOM
    }
    WC_lastMsgSignature = firma;

    // Posición del scroll ANTES de actualizar (para no forzar scroll si el usuario está revisando)
    const margen = 120;
    const estabaAbajo = container.scrollHeight <= container.clientHeight
        || (container.scrollHeight - container.clientHeight - container.scrollTop) <= margen;

    let html = '';
    mensajes.forEach(m => {
        const isOut = m.direccion === 'OUT';
        const bubbleClass = isOut ? 'wa-bubble-out' : 'wa-bubble-in';

        // Garantizar que contenido sea siempre un objeto
        if (m.contenido === null || m.contenido === undefined) {
            m.contenido = {};
        }
        if (typeof m.contenido === 'string') {
            try { m.contenido = JSON.parse(m.contenido); } catch(e) { m.contenido = {}; }
        }

        let contentHtml = '';
        if (m.tipo_mensaje === 'text') {
            let text = '';
            if (isOut) {
                text = m.contenido.text?.body || m.contenido.body || '';
            } else {
                text = m.contenido.text?.body || '';
            }
            contentHtml = WC_escHtml(text).replace(/\n/g, '<br>');
        } else if (m.tipo_mensaje === 'template') {
            const c = m.contenido || {};
            const parts = [];
            const headerType = (c.header_type || 'none').toLowerCase();

            // Cabecera de la plantilla
            if (headerType === 'image' && c.header_local_path) {
                const hUrl = WC_mediaUrl(c.header_local_path);
                parts.push(`<a href="${hUrl}" target="_blank">
                    <img src="${hUrl}" style="max-width:200px;border-radius:8px;display:block;" alt="Imagen"
                         onerror="this.onerror=null;this.style.display='none';">
                </a>`);
            } else if (headerType === 'document' && c.header_local_path) {
                const hUrl = WC_mediaUrl(c.header_local_path);
                parts.push(`<a href="${hUrl}" target="_blank"
                    class="btn btn-sm btn-light text-primary border mb-1">
                    <i class="bi bi-file-earmark-text"></i> Ver documento
                </a>`);
            } else if (headerType === 'text' && c.header_text) {
                parts.push(`<div class="fw-bold mb-1">${WC_escHtml(c.header_text)}</div>`);
            }

            // Cuerpo de la plantilla
            if (c.template_text) {
                parts.push(`<div>${WC_escHtml(c.template_text).replace(/\n/g, '<br>')}</div>`);
            } else {
                parts.push(`<i class="text-muted">[Plantilla: ${WC_escHtml(c.template || '')}]</i>`);
            }

            // Pie de plantilla
            if (c.footer_text) {
                parts.push(`<div class="text-muted mt-1" style="font-size:0.78rem;">${WC_escHtml(c.footer_text)}</div>`);
            }

            // Etiqueta de plantilla
            parts.push(`<div class="text-muted mt-1 border-top pt-1" style="font-size:0.71rem;">
                <i class="bi bi-megaphone"></i> Plantilla: ${WC_escHtml(c.template || '')}
            </div>`);

            // Botones (solo visual)
            if (c.buttons && c.buttons.length > 0) {
                let btnHtml = '<div class="mt-2 d-flex flex-wrap gap-1 border-top pt-1">';
                c.buttons.forEach(btn => {
                    const label = typeof btn === 'string' ? btn : (btn.text || '');
                    if (label) btnHtml += `<span class="badge bg-white text-primary border" style="font-size:0.75rem;">${WC_escHtml(label)}</span>`;
                });
                btnHtml += '</div>';
                parts.push(btnHtml);
            }

            contentHtml = parts.join('');

        } else if (m.tipo_mensaje === 'image') {
            const c       = m.contenido || {};
            const path    = c.local_path || c.image?.link || '';
            const urlView = WC_mediaUrl(path);          // abre inline en nueva pestaña
            const urlDl   = WC_mediaUrl(path, true);    // fuerza descarga
            const fileName = path ? path.split('/').pop() : 'imagen.jpg';
            if (urlView) {
                contentHtml = `
                    <div class="wa-img-wrap" style="display:inline-block;max-width:220px;">
                        <a href="${urlView}" target="_blank" style="display:block;">
                            <img src="${urlView}"
                                 style="width:100%;max-height:300px;object-fit:cover;border-radius:8px 8px 0 0;display:block;cursor:pointer;"
                                 alt="Imagen"
                                 onerror="this.onerror=null;this.style.display='none';this.closest('.wa-img-wrap').querySelector('.wa-img-fallback').style.display='flex';">
                        </a>
                        <div class="wa-img-fallback align-items-center gap-1 p-2 text-primary border-bottom"
                             style="display:none;font-size:0.82rem;">
                            <i class="bi bi-image"></i> Imagen no disponible
                        </div>
                        <div class="d-flex border-top bg-white" style="border-radius:0 0 8px 8px;overflow:hidden;">
                            <a href="${urlView}" target="_blank"
                               class="btn btn-sm btn-light flex-fill py-1 rounded-0"
                               style="font-size:0.75rem;border-right:1px solid #dee2e6;">
                                <i class="bi bi-eye me-1"></i>Ver
                            </a>
                            <a href="${urlDl}"
                               class="btn btn-sm btn-light flex-fill py-1 rounded-0"
                               style="font-size:0.75rem;">
                                <i class="bi bi-download me-1"></i>Descargar
                            </a>
                        </div>
                    </div>`;
            } else {
                contentHtml = `<span class="text-muted"><i class="bi bi-image me-1"></i>Imagen no disponible</span>`;
            }

        } else if (m.tipo_mensaje === 'document') {
            const c        = m.contenido || {};
            const path     = c.local_path || c.document?.link || '';
            const url      = WC_mediaUrl(path);
            const filename = c.document?.filename || c.filename || 'Documento';
            contentHtml = url
                ? `<a href="${url}" target="_blank"
                       class="btn btn-sm btn-light text-primary border">
                       <i class="bi bi-file-earmark-text me-1"></i>${WC_escHtml(filename)}
                   </a>`
                : `<span class="text-muted"><i class="bi bi-file-earmark me-1"></i>Documento no disponible</span>`;

        } else if (m.tipo_mensaje === 'audio') {
            const c    = m.contenido || {};
            const path = c.local_path || c.audio?.link || '';
            const url  = WC_mediaUrl(path);
            if (url) {
                contentHtml = `
                    <div class="d-flex align-items-center gap-2 py-1">
                        <i class="bi bi-mic-fill text-success fs-5"></i>
                        <audio controls preload="none"
                            style="max-width:220px; height:36px; outline:none; border-radius:20px;">
                            <source src="${url}">
                        </audio>
                        <a href="${url}" target="_blank" download
                           class="btn btn-sm btn-light border" title="Descargar audio">
                            <i class="bi bi-download"></i>
                        </a>
                    </div>`;
            } else {
                contentHtml = `<span class="text-muted"><i class="bi bi-mic-fill me-1"></i>Audio no disponible</span>`;
            }

        } else if (m.tipo_mensaje === 'video') {
            const c    = m.contenido || {};
            const path = c.local_path || c.video?.link || '';
            const url  = WC_mediaUrl(path);
            contentHtml = url
                ? `<div>
                       <video controls preload="none"
                           style="max-width:240px;border-radius:8px;display:block;">
                           <source src="${url}">
                       </video>
                       <a href="${url}" target="_blank" download
                          class="btn btn-sm btn-light text-primary border mt-1"
                          style="font-size:0.75rem;">
                          <i class="bi bi-download me-1"></i>Descargar video
                       </a>
                   </div>`
                : `<span class="text-muted"><i class="bi bi-camera-video me-1"></i>Video no disponible</span>`;

        } else if (m.tipo_mensaje === 'sticker') {
            const c    = m.contenido || {};
            const path = c.local_path || '';
            const url  = WC_mediaUrl(path);
            contentHtml = url
                ? `<img src="${url}" style="max-width:110px;" alt="Sticker">`
                : `<span class="text-muted">🖼️ Sticker</span>`;

        } else {
            contentHtml = `<span class="text-muted"><i class="bi bi-paperclip me-1"></i>[${WC_escHtml(m.tipo_mensaje || '')}]</span>`;
        }

        let dateObj = new Date(m.fecha_hora);
        let timeStr = dateObj.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});

        let statusIcon = '';
        if (isOut) {
            if (m.estado_meta === 'read') {
                statusIcon = '<i class="bi bi-check2-all wa-status-icon wa-status-read"></i>';
            } else if (m.estado_meta === 'delivered') {
                statusIcon = '<i class="bi bi-check2-all wa-status-icon wa-status-delivered"></i>';
            } else if (m.estado_meta === 'sent') {
                statusIcon = '<i class="bi bi-check2 wa-status-icon wa-status-sent"></i>';
            } else if (m.estado_meta === 'failed') {
                statusIcon = '<i class="bi bi-exclamation-circle text-danger wa-status-icon" title="Falló"></i>';
            } else {
                statusIcon = '<i class="bi bi-clock wa-status-icon text-muted"></i>';
            }
        }

        html += `
        <div class="wa-bubble ${bubbleClass}">
            <div>${contentHtml}</div>
            <div class="wa-time">${timeStr} ${statusIcon}</div>
        </div>`;
    });

    container.innerHTML = html;

    // Scroll inteligente: solo bajar al fondo si es carga inicial o el usuario ya estaba abajo
    if (esInicial || estabaAbajo) {
        container.scrollTop = container.scrollHeight;
    }
}

function WC_enviarMensaje() {
    if (WC_currentChatId <= 0 || !WC_currentPhone) return;

    const input = document.getElementById('waChatInputText');
    const texto = input.value.trim();
    if (!texto) return;

    // Deshabilitar input temporalmente
    input.disabled = true;
    const btn = document.getElementById('waChatBtnSend');
    btn.disabled = true;

    // Pre-agregar mensaje optimista a la UI
    const container = document.getElementById('waChatMessages');
    let timeStr = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    container.innerHTML += `
        <div class="wa-bubble wa-bubble-out opacity-50" id="msg-temp">
            <div>${texto.replace(/\n/g, '<br>')}</div>
            <div class="wa-time">${timeStr} <i class="bi bi-clock wa-status-icon text-muted"></i></div>
        </div>`;
    container.scrollTop = container.scrollHeight;

    input.value = '';
    input.style.height = 'auto';

    fetch(`${B_URL}/modulos/whatsapp-chat/enviarMensajeAjax`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({
            telefono: WC_currentPhone,
            texto: texto
        })
    })
    .then(res => res.json())
    .then(data => {
        input.disabled = false;
        input.focus();
        
        if (data.ok) {
            WC_cargarMensajes(WC_currentChatId, WC_currentPhone, true);
            WC_cargarChats(true);
        } else {
            const temp = document.getElementById('msg-temp');
            if (temp) temp.remove();
            if (data.ventana_cerrada) {
                input.value = texto;
                WC_aplicarVentana({ abierta: false });
                Swal.fire('Ventana de 24 horas cerrada', data.error, 'warning');
            } else {
                Swal.fire('Error', data.error, 'error');
            }
        }
    })
    .catch(err => {
        console.error(err);
        input.disabled = false;
        btn.disabled = false;
        Swal.fire('Error', 'Problema de red al enviar.', 'error');
    });
}

// ═══════════════════════════════════════════════════════
//  PLANTILLAS
// ═══════════════════════════════════════════════════════

/** Caché de plantillas cargadas */
let WC_plantillasCache = [];

/** Escapa HTML para evitar XSS al renderizar contenido dinámico */
function WC_escHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function WC_subirArchivo(file) {
    if (!WC_currentChatId) return;

    // Mostrar indicador temporal
    const container = document.getElementById('waChatMessages');
    if (container) {
        container.innerHTML += `<div class='mb-3 text-end' id='msg-uploading'>
            <div class='d-inline-block p-2 rounded-3 wa-bubble-out text-muted'>
                <i class='bi bi-hourglass-split'></i> Subiendo archivo...
            </div>
        </div>`;
        container.scrollTop = container.scrollHeight;
    }

    const formData = new FormData();
    formData.append('id_chat', WC_currentChatId);
    formData.append('telefono', WC_currentPhone);
    formData.append('file', file);

    fetch(`${B_URL}/modulos/whatsapp-chat/uploadMediaAjax`, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        const upMsg = document.getElementById('msg-uploading');
        if (upMsg) upMsg.remove();

        if (data.ok) {
            WC_cargarMensajes(WC_currentChatId, WC_currentPhone, true);
            WC_cargarChats(true);
        } else {
            Swal.fire('Error', data.error || 'Error subiendo archivo', 'error');
        }
    })
    .catch(err => {
        console.error(err);
        const upMsg = document.getElementById('msg-uploading');
        if (upMsg) upMsg.remove();
        Swal.fire('Error', 'Problema de red.', 'error');
    });
}

// ═══════════════════════════════════════════════════════════════════════════
//  RESPUESTAS RÁPIDAS
// ═══════════════════════════════════════════════════════════════════════════

let WC_rrVisible  = false;  // panel visible
let WC_rrCache    = null;   // { empresa: [], personales: [] }
let WC_rrEditId   = 0;      // id en edición (0 = nuevo)

/** Abre o cierra el panel. Sin arg = alterna; false = cierra. */
function WC_toggleRespuestasPanel(forzarEstado) {
    const panel = document.getElementById('waRespuestasPanel');
    const btn   = document.getElementById('waChatBtnRespuestas');
    if (!panel) return;

    const abrir = (forzarEstado !== undefined) ? !!forzarEstado : !WC_rrVisible;

    if (abrir) {
        panel.style.display = 'flex';
        WC_rrVisible = true;
        if (btn) btn.classList.add('active');
        WC_cargarRespuestasRapidas();
    } else {
        panel.style.display = 'none';
        WC_rrVisible = false;
        if (btn) btn.classList.remove('active');
        WC_cancelarFormRR();
    }
}

/** Carga desde servidor (o usa caché) y renderiza */
function WC_cargarRespuestasRapidas(forzar = false) {
    if (WC_rrCache && !forzar) {
        WC_renderRespuestasRapidas(WC_rrCache);
        return;
    }
    fetch(`${B_URL}/modulos/whatsapp-chat/getRespuestasRapidas`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            WC_rrCache = { empresa: data.empresa, personales: data.personales };
            WC_renderRespuestasRapidas(WC_rrCache);
        }
    })
    .catch(console.error);
}

/** Renderiza empresa + personales en el listado */
function WC_renderRespuestasRapidas(data) {
    const lista = document.getElementById('waRRLista');
    if (!lista) return;

    const empresa    = data.empresa    || [];
    const personales = data.personales || [];

    if (empresa.length === 0 && personales.length === 0) {
        lista.innerHTML = `<div class="text-center text-muted py-4" style="font-size:.82rem;">
            <i class="bi bi-lightning-charge fs-3 d-block mb-1 opacity-50"></i>
            Aún no hay respuestas guardadas.<br>Usa los botones de abajo para crear una.
        </div>`;
        return;
    }

    let html = '';

    if (empresa.length > 0) {
        html += `<div class="px-3 pt-2 pb-1 text-uppercase fw-semibold text-primary"
                      style="font-size:.7rem;letter-spacing:.06em;">
                     <i class="bi bi-building me-1"></i>Empresa
                 </div>`;
        empresa.forEach(r => { html += WC_rrItem(r, 'empresa'); });
    }

    if (personales.length > 0) {
        html += `<div class="px-3 pt-2 pb-1 text-uppercase fw-semibold text-secondary"
                      style="font-size:.7rem;letter-spacing:.06em;${empresa.length ? 'border-top:1px solid #f0f0f0;margin-top:4px;' : ''}">
                     <i class="bi bi-person me-1"></i>Personales
                 </div>`;
        personales.forEach(r => { html += WC_rrItem(r, 'personal'); });
    }

    lista.innerHTML = html;
}

/** HTML de un ítem de la lista */
function WC_rrItem(r, tipo) {
    const preview = WC_escHtml(r.contenido.length > 80
        ? r.contenido.substring(0, 80) + '…'
        : r.contenido);
    return `
    <div class="d-flex align-items-start gap-2 px-3 py-2 wa-rr-item border-bottom"
         style="cursor:default;">
        <div class="flex-grow-1 min-w-0">
            <div class="fw-semibold text-truncate" style="font-size:.8rem;">${WC_escHtml(r.titulo)}</div>
            <div class="text-muted text-truncate" style="font-size:.75rem;">${preview}</div>
        </div>
        <div class="d-flex gap-1 flex-shrink-0">
            <button type="button" class="btn btn-sm btn-success py-0 px-2"
                    style="font-size:.73rem;"
                    onclick="WC_insertarRespuesta(${r.id})"
                    title="Insertar en el campo de texto">
                <i class="bi bi-arrow-up-left"></i> Usar
            </button>
            <button type="button" class="btn btn-sm btn-light border py-0 px-1"
                    style="font-size:.73rem;"
                    onclick="WC_editarRespuesta(${r.id}, '${tipo}')"
                    title="Editar">
                <i class="bi bi-pencil"></i>
            </button>
            <button type="button" class="btn btn-sm btn-light border py-0 px-1 text-danger"
                    style="font-size:.73rem;"
                    onclick="WC_eliminarRespuesta(${r.id})"
                    title="Eliminar">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    </div>`;
}

/** Inserta el contenido de la respuesta en el textarea */
function WC_insertarRespuesta(id) {
    const item = [...(WC_rrCache?.empresa || []), ...(WC_rrCache?.personales || [])]
        .find(r => r.id === id);
    if (!item) return;

    const textarea = document.getElementById('waChatInputText');
    if (!textarea) return;

    // Insertar en posición del cursor o al final
    const start = textarea.selectionStart ?? textarea.value.length;
    const end   = textarea.selectionEnd   ?? textarea.value.length;
    const antes  = textarea.value.substring(0, start);
    const despues = textarea.value.substring(end);
    const sep = (antes && !antes.endsWith('\n')) ? '\n' : '';
    textarea.value = antes + sep + item.contenido + despues;

    // Ajustar alto del textarea
    textarea.style.height = 'auto';
    textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';

    // Activar botón enviar
    const btn = document.getElementById('waChatBtnSend');
    if (btn) btn.disabled = false;

    // Cerrar panel y enfocar textarea
    WC_toggleRespuestasPanel(false);
    textarea.focus();
}

/** Muestra el formulario para crear una nueva respuesta */
function WC_nuevaRespuesta(tipo) {
    WC_rrEditId = 0;
    document.getElementById('waRRFormId').value        = '';
    document.getElementById('waRRFormTipo').value      = tipo;
    document.getElementById('waRRFormTitulo').value    = '';
    document.getElementById('waRRFormContenido').value = '';

    document.getElementById('waRRFormTitulo').placeholder =
        tipo === 'empresa' ? 'Título (ej: Cuentas bancarias)' : 'Título (ej: Mi saludo)';

    const form = document.getElementById('waRRForm');
    form.style.display = 'block';
    document.getElementById('waRRFormTitulo').focus();
}

/** Carga el formulario con los datos de un ítem para editar */
function WC_editarRespuesta(id, tipo) {
    const item = [...(WC_rrCache?.empresa || []), ...(WC_rrCache?.personales || [])]
        .find(r => r.id === id);
    if (!item) return;

    WC_rrEditId = id;
    document.getElementById('waRRFormId').value        = id;
    document.getElementById('waRRFormTipo').value      = tipo;
    document.getElementById('waRRFormTitulo').value    = item.titulo;
    document.getElementById('waRRFormContenido').value = item.contenido;

    const form = document.getElementById('waRRForm');
    form.style.display = 'block';
    document.getElementById('waRRFormTitulo').focus();
}

/** Cancela el formulario inline */
function WC_cancelarFormRR() {
    WC_rrEditId = 0;
    const form = document.getElementById('waRRForm');
    if (form) form.style.display = 'none';
}

/** Envía al servidor crear o actualizar */
function WC_guardarRespuesta() {
    const titulo    = document.getElementById('waRRFormTitulo').value.trim();
    const contenido = document.getElementById('waRRFormContenido').value.trim();
    const tipo      = document.getElementById('waRRFormTipo').value;
    const id        = parseInt(document.getElementById('waRRFormId').value || '0');

    if (!titulo) {
        document.getElementById('waRRFormTitulo').focus();
        return;
    }
    if (!contenido) {
        document.getElementById('waRRFormContenido').focus();
        return;
    }

    const payload = { id, titulo, contenido, tipo };

    fetch(`${B_URL}/modulos/whatsapp-chat/saveRespuestaRapida`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            WC_cancelarFormRR();
            WC_rrCache = null;                      // invalidar caché
            WC_cargarRespuestasRapidas(true);       // recargar lista
        } else {
            Swal.fire('Error', data.error || 'No se pudo guardar.', 'error');
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire('Error', 'Problema de red.', 'error');
    });
}

/** Elimina con confirmación */
function WC_eliminarRespuesta(id) {
    Swal.fire({
        title: '¿Eliminar respuesta?',
        text: 'Esta acción no se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonText: 'Cancelar',
        confirmButtonText: 'Sí, eliminar'
    }).then(result => {
        if (!result.isConfirmed) return;

        fetch(`${B_URL}/modulos/whatsapp-chat/deleteRespuestaRapida`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ id })
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                WC_rrCache = null;
                WC_cargarRespuestasRapidas(true);
            } else {
                Swal.fire('Error', data.error || 'No se pudo eliminar.', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            Swal.fire('Error', 'Problema de red.', 'error');
        });
    });
}

// ═══════════════════════════════════════════════════════════════════════════
//  NUEVO CHAT / ENVIAR PLANTILLA
//  Meta solo acepta una plantilla aprobada para iniciar una conversación o para
//  escribir cuando pasaron 24 h desde el último mensaje del cliente.
// ═══════════════════════════════════════════════════════════════════════════

let WC_plPlantillas = null;   // caché de plantillas aprobadas
let WC_plChatFijo   = false;  // true = se envía al chat abierto (ventana cerrada)
let WC_plEnviando   = false;
let WC_plTimerBusca = null;

/** Abre el modal. chatActual = true → al chat abierto; si no, a un número nuevo. */
async function WC_abrirPlantilla(chatActual = false) {
    WC_plChatFijo = !!chatActual && WC_currentChatId > 0;
    const $ = id => document.getElementById(id);

    $('waPlTitulo').innerHTML = WC_plChatFijo
        ? '<i class="bi bi-send text-success me-2"></i>Enviar plantilla'
        : '<i class="bi bi-whatsapp text-success me-2"></i>Nuevo chat';
    $('waPlDestinoNuevo').classList.toggle('d-none', WC_plChatFijo);
    $('waPlDestinoFijo').classList.toggle('d-none', !WC_plChatFijo);
    $('waPlDestinoTexto').textContent = WC_plChatFijo
        ? `${WC_currentNombre || 'Sin nombre'} (${WC_currentPhone})` : '';
    $('waPlBuscarCliente').value = '';
    $('waPlCelular').value = '';
    $('waPlNombre').value = '';
    $('waPlClientesLista').classList.add('d-none');

    bootstrap.Modal.getOrCreateInstance($('waModalPlantilla')).show();
    await WC_plCargarPlantillas();
}

async function WC_plCargarPlantillas() {
    const sel = document.getElementById('waPlPlantilla');
    if (!WC_plPlantillas) {
        sel.innerHTML = '<option value="">Cargando…</option>';
        try {
            const r = await fetch(`${B_URL}/modulos/whatsapp-chat/getPlantillasAjax`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await r.json();
            if (!data.ok) throw new Error(data.error || 'No se pudieron cargar las plantillas.');
            WC_plPlantillas = data.plantillas || [];
        } catch (e) {
            sel.innerHTML = '<option value="">No se pudieron cargar</option>';
            Swal.fire('Error', e.message || 'No se pudieron cargar las plantillas.', 'error');
            return;
        }
    }
    const previa = sel.value;
    const ayuda = document.getElementById('waPlPlantillaAyuda');
    if (!WC_plPlantillas.length) {
        sel.innerHTML = '<option value="">No hay plantillas aprobadas por Meta</option>';
        ayuda.textContent = 'Créelas en Plantillas de WhatsApp y espere la aprobación de Meta.';
    } else {
        sel.innerHTML = '<option value="">— Elija una plantilla —</option>' + WC_plPlantillas.map(p =>
            `<option value="${WC_escHtml(p.nombre)}" ${p.soportada ? '' : 'disabled'} title="${WC_escHtml(p.motivo)}">`
            + `${WC_escHtml(p.nombre)}${p.soportada ? '' : ' (no disponible aquí)'}</option>`
        ).join('');
        ayuda.textContent = WC_plPlantillas.some(p => !p.soportada)
            ? 'Las marcadas «no disponible aquí» llevan un archivo en el encabezado o variables con nombre.' : '';
        if (previa && WC_plPlantillas.some(p => p.nombre === previa && p.soportada)) sel.value = previa;
    }
    WC_plRenderVariables();
}

function WC_plSeleccionada() {
    const nombre = document.getElementById('waPlPlantilla').value;
    return (WC_plPlantillas || []).find(p => p.nombre === nombre) || null;
}

/** Inputs de las variables de la plantilla elegida (encabezado, cuerpo y botón). */
function WC_plRenderVariables() {
    const cont = document.getElementById('waPlVariables');
    const p = WC_plSeleccionada();
    if (!p) { cont.innerHTML = ''; WC_plPreview(); return; }

    const campo = (grupo, i, ejemplo, etiqueta) => `
        <div class="mb-2">
            <label class="form-label small mb-1">${etiqueta}</label>
            <input type="text" class="form-control form-control-sm wa-pl-var" data-grupo="${grupo}" data-i="${i}"
                   maxlength="1000" placeholder="${ejemplo ? 'Ej.: ' + WC_escHtml(ejemplo) : ''}">
        </div>`;
    let html = '';
    for (let i = 0; i < p.vars_header; i++) html += campo('header', i, p.ejemplos_header[i], `Encabezado {{${i + 1}}} <span class="text-danger">*</span>`);
    for (let i = 0; i < p.vars_body; i++)   html += campo('body', i, p.ejemplos_body[i], `Variable {{${i + 1}}} <span class="text-danger">*</span>`);
    if (p.boton_url) {
        html += `
        <div class="mb-2">
            <label class="form-label small mb-1">Enlace del botón «${WC_escHtml(p.boton_url.texto)}» <span class="text-danger">*</span></label>
            <div class="input-group input-group-sm">
                <span class="input-group-text text-truncate" style="max-width:55%;" title="${WC_escHtml(p.boton_url.base)}">${WC_escHtml(p.boton_url.base)}</span>
                <input type="text" class="form-control wa-pl-var" data-grupo="boton" data-i="0"
                       placeholder="${p.boton_url.ejemplo ? 'Ej.: ' + WC_escHtml(p.boton_url.ejemplo) : ''}">
            </div>
        </div>`;
    }
    cont.innerHTML = html || '<div class="small text-muted">Esta plantilla no tiene variables.</div>';

    // Si el cuerpo saluda («Hola {{1}}», «Estimado {{1}}»), {{1}} se precarga con el nombre del contacto.
    const nombre = WC_plChatFijo ? WC_currentNombre : document.getElementById('waPlNombre').value.trim();
    const primera = cont.querySelector('.wa-pl-var[data-grupo="body"][data-i="0"]');
    if (primera && nombre && /(hola|estimad[oa]s?|querid[oa]s?)[\s,¡!]*\{\{1\}\}/i.test(p.cuerpo)) primera.value = nombre;
    WC_plPreview();
}

function WC_plValores(grupo) {
    return [...document.querySelectorAll(`#waPlVariables .wa-pl-var[data-grupo="${grupo}"]`)]
        .sort((a, b) => a.dataset.i - b.dataset.i).map(i => i.value.trim());
}

/** Texto con las variables puestas; las vacías se resaltan para que se vea qué falta. */
function WC_plRellenar(texto, valores) {
    return WC_escHtml(texto || '').replace(/\{\{(\d+)\}\}/g, (m, n) => {
        const v = valores[n - 1];
        return v ? WC_escHtml(v) : `<span class="badge bg-warning text-dark">{{${n}}}</span>`;
    }).replace(/\n/g, '<br>');
}

function WC_plPreview() {
    const prev = document.getElementById('waPlPreview');
    const p = WC_plSeleccionada();
    if (!p) { prev.innerHTML = '<span class="text-muted">Elija una plantilla.</span>'; return; }
    let html = '';
    if (p.encabezado) html += `<div class="fw-bold mb-1">${WC_plRellenar(p.encabezado, WC_plValores('header'))}</div>`;
    html += `<div>${WC_plRellenar(p.cuerpo, WC_plValores('body'))}</div>`;
    if (p.pie) html += `<div class="text-muted mt-1" style="font-size:.78rem;">${WC_escHtml(p.pie)}</div>`;
    if (p.botones.length) {
        html += '<div class="mt-2 d-flex flex-wrap gap-1 border-top pt-1">' + p.botones.map(b =>
            `<span class="badge bg-white text-primary border" style="font-size:.75rem;">${WC_escHtml(b)}</span>`).join('') + '</div>';
    }
    prev.innerHTML = html;
}

// ── Buscador de clientes ────────────────────────────────────────────────────
function WC_plBuscarClientes() {
    const q = document.getElementById('waPlBuscarCliente').value.trim();
    const lista = document.getElementById('waPlClientesLista');
    clearTimeout(WC_plTimerBusca);
    if (q.length < 2) { lista.classList.add('d-none'); return; }
    WC_plTimerBusca = setTimeout(async () => {
        try {
            const r = await fetch(`${B_URL}/modulos/whatsapp-chat/buscarClientesAjax?q=${encodeURIComponent(q)}`,
                { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await r.json();
            const clientes = data.clientes || [];
            lista.innerHTML = clientes.length ? clientes.map(c => `
                <button type="button" class="list-group-item list-group-item-action py-1 small"
                        data-cel="${WC_escHtml(c.celular)}" data-nombre="${WC_escHtml(c.nombre)}" data-tel="${WC_escHtml(c.telefono)}">
                    <div class="fw-semibold text-truncate">${WC_escHtml(c.nombre)}</div>
                    <div class="text-muted">${WC_escHtml(c.identificacion || '')} · ${WC_escHtml(c.telefono)}
                        ${c.celular ? '' : '<span class="text-danger ms-1">(sin celular 09…)</span>'}</div>
                </button>`).join('')
                : '<div class="list-group-item small text-muted">Sin clientes con teléfono que coincidan.</div>';
            lista.classList.remove('d-none');
        } catch (e) {
            console.error(e);
        }
    }, 300);
}

function WC_plElegirCliente(btn) {
    document.getElementById('waPlCelular').value = btn.dataset.cel || '';
    document.getElementById('waPlNombre').value = btn.dataset.nombre || '';
    document.getElementById('waPlBuscarCliente').value = btn.dataset.nombre || '';
    document.getElementById('waPlClientesLista').classList.add('d-none');
    if (!btn.dataset.cel) {
        Swal.fire('Sin celular', `El teléfono registrado del cliente (${btn.dataset.tel}) no es un celular 09…. Escriba el celular a mano.`, 'info');
        document.getElementById('waPlCelular').focus();
    }
    WC_plRenderVariables();
}

// ── Envío ───────────────────────────────────────────────────────────────────
async function WC_enviarPlantilla() {
    if (WC_plEnviando) return;
    WC_plEnviando = true;
    const btn = document.getElementById('waPlBtnEnviar');
    const htmlBtn = btn.innerHTML;
    try {
        const p = WC_plSeleccionada();
        const telefono = WC_plChatFijo ? WC_currentPhone : document.getElementById('waPlCelular').value.trim();
        const nombre   = WC_plChatFijo ? WC_currentNombre : document.getElementById('waPlNombre').value.trim();

        if (!WC_plChatFijo && !/^09\d{8}$/.test(telefono)) {
            Swal.fire('Celular no válido', 'Escriba los 10 dígitos del celular, por ejemplo 0958924835.', 'warning');
            return;
        }
        if (!p) {
            Swal.fire('Falta la plantilla', 'Elija la plantilla aprobada que desea enviar.', 'warning');
            return;
        }
        const vacia = [...document.querySelectorAll('#waPlVariables .wa-pl-var')].find(i => !i.value.trim());
        if (vacia) {
            vacia.focus();
            Swal.fire('Faltan datos', 'Complete todas las variables de la plantilla.', 'warning');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Enviando…';
        const r = await fetch(`${B_URL}/modulos/whatsapp-chat/enviarPlantillaAjax`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({
                telefono, nombre, plantilla: p.nombre,
                vars_header: WC_plValores('header'), vars_body: WC_plValores('body'),
                var_boton: WC_plValores('boton')[0] || '',
            }),
        });
        const data = await r.json();
        if (!data.ok) throw new Error(data.error || 'No se pudo enviar.');

        bootstrap.Modal.getOrCreateInstance(document.getElementById('waModalPlantilla')).hide();
        WC_cargarChats(true);
        WC_seleccionarChat(data.id_chat, data.telefono, data.nombre || nombre);
        Swal.fire({ icon: 'success', title: 'Plantilla enviada', text: 'Cuando el cliente responda podrá escribirle libremente.', timer: 2500, showConfirmButton: false });
    } catch (e) {
        Swal.fire('Error', e.message || 'Problema de red al enviar.', 'error');
    } finally {
        WC_plEnviando = false;
        btn.disabled = false;
        btn.innerHTML = htmlBtn;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('waModalPlantilla');
    if (!modal) return;
    document.getElementById('waPlPlantilla').addEventListener('change', WC_plRenderVariables);
    document.getElementById('waPlVariables').addEventListener('input', WC_plPreview);
    document.getElementById('waPlBuscarCliente').addEventListener('input', WC_plBuscarClientes);
    document.getElementById('waPlClientesLista').addEventListener('click', e => {
        const btn = e.target.closest('[data-cel]');
        if (btn) WC_plElegirCliente(btn);
    });
    document.getElementById('waPlCelular').addEventListener('input', e => {
        e.target.value = e.target.value.replace(/\D/g, '').slice(0, 10);
    });
    // Cerrar la lista de clientes al hacer clic fuera de ella.
    modal.addEventListener('click', e => {
        if (!e.target.closest('#waPlClientesLista') && e.target.id !== 'waPlBuscarCliente') {
            document.getElementById('waPlClientesLista').classList.add('d-none');
        }
    });
});
