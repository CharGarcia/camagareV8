let WC_currentChatId = 0;
let WC_currentPhone = '';
let WC_pollingInterval = null;

/**
 * Convierte una ruta relativa de media (storage/whatsapp_media/...)
 * en la URL del endpoint PHP seguro que sirve el archivo.
 * Usa el endpoint serveMedia para validar sesión, empresa y prevenir 404 estáticos.
 */
function WC_mediaUrl(path) {
    if (!path) return '';
    if (path.startsWith('http')) return path; // URL externa (Meta CDN, etc.)
    return `${B_URL}/modulos/whatsapp-chat/serveMedia?file=${encodeURIComponent(path)}`;
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

        html += `
        <div class="wa-chat-item p-3 border-bottom d-flex align-items-center ${isActive}" onclick="WC_seleccionarChat(${c.id}, '${c.telefono_cliente}', '${c.nombre_cliente}')">
            <div class="bg-secondary bg-opacity-25 text-secondary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 45px; height: 45px; min-width: 45px;">
                <i class="bi bi-person-fill fs-4"></i>
            </div>
            <div class="flex-grow-1 overflow-hidden">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <h6 class="m-0 text-truncate fw-bold">${c.nombre_cliente || c.telefono_cliente}</h6>
                    <small class="text-muted" style="font-size: 11px;">${timeStr}</small>
                </div>
                <div class="d-flex align-items-center">
                    <small class="text-muted text-truncate flex-grow-1" style="max-width: 80%;">${c.ultimo_mensaje || ''}</small>
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

    // UI Updates
    document.getElementById('waChatEmptyState').classList.add('d-none');
    document.getElementById('waChatHeader').classList.remove('d-none');
    document.getElementById('waChatMessages').classList.remove('d-none');
    document.getElementById('waChatInputArea').classList.remove('d-none');

    document.getElementById('waChatName').innerText = nombre || telefono;
    document.getElementById('waChatPhone').innerText = telefono;

    // Resaltar en la lista
    document.querySelectorAll('.wa-chat-item').forEach(el => el.classList.remove('wa-chat-active'));
    // (El polling de cargarChats re-aplicará la clase en el próximo ciclo)

    WC_cargarMensajes(id, telefono, false);
}

function WC_cargarMensajes(idChat, telefono, silencioso = false) {
    fetch(`${B_URL}/modulos/whatsapp-chat/getMensajesAjax?id_chat=${idChat}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.ok) {
            WC_renderMensajes(data.mensajes);
        }
    })
    .catch(err => console.error(err));
}

function WC_renderMensajes(mensajes) {
    const container = document.getElementById('waChatMessages');
    if (!container) return;

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
            const c    = m.contenido || {};
            const path = c.local_path || c.image?.link || '';
            const url  = WC_mediaUrl(path);
            if (url) {
                contentHtml = `
                    <a href="${url}" target="_blank" style="display:inline-block;">
                        <img src="${url}"
                             style="max-width:220px;max-height:300px;border-radius:8px;display:block;cursor:pointer;"
                             alt="Imagen"
                             onerror="this.onerror=null;this.style.display='none';this.nextElementSibling.style.display='inline-flex';">
                        <span class="btn btn-sm btn-light text-primary border" style="display:none;">
                            <i class="bi bi-image me-1"></i>Ver imagen
                        </span>
                    </a>`;
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

    // Solo auto-scroll si el html cambió (muy básico)
    const isScrolledToBottom = container.scrollHeight - container.clientHeight <= container.scrollTop + 50;
    
    container.innerHTML = html;

    // Bajar scroll
    container.scrollTop = container.scrollHeight;
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
            Swal.fire('Error', data.error, 'error');
            const temp = document.getElementById('msg-temp');
            if (temp) temp.remove();
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
