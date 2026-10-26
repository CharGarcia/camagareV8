<?php
/**
 * Vista de Bandeja de Entrada (Chat Center)
 */
$ruta_assets = '/sistema/public';
?>
<script>document.body.classList.add('wa-chat-pantalla');</script>

<div class="wa-chat-shell">
    <?php if (!$configurado): ?>
    <div class="row h-100 m-0 align-items-center justify-content-center bg-light rounded shadow-sm border">
        <div class="col-md-6 text-center">
            <div class="card shadow-none border-0 bg-transparent">
                <div class="card-body p-5">
                    <i class="bi bi-whatsapp text-success mb-3" style="font-size: 4rem;"></i>
                    <h2 class="fw-bold mb-3">WhatsApp API no configurado</h2>
                    <p class="text-muted mb-4">Para usar el Chat Center, necesitas conectar tu cuenta de WhatsApp Business API.</p>
                    <a href="<?= $ruta_assets ?>/modulos/configuracion-whatsapp" class="btn btn-success px-4 py-2">
                        <i class="bi bi-gear me-2"></i> Ir a Configuración
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    
    <div class="row m-0 border-top overflow-hidden h-100">
        <!-- Sidebar Izquierdo: Lista de Chats -->
        <div class="col-12 col-md-4 col-lg-3 border-end bg-white p-0 d-flex flex-column" id="chatListContainer" style="height: 100%;">
            <div class="p-3 border-bottom bg-light d-flex align-items-center justify-content-between">
                <h5 class="m-0 fw-bold"><i class="bi bi-chat-left-text text-success me-2"></i> Chats</h5>
                <div class="d-flex gap-1">
                    <?php if (!empty($permisos['crear'])): ?>
                    <button class="btn btn-sm btn-success" onclick="WC_abrirPlantilla()" title="Iniciar un chat con una plantilla aprobada">
                        <i class="bi bi-plus-lg me-1"></i>Nuevo chat
                    </button>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-outline-secondary" onclick="WC_cargarChats()" title="Actualizar"><i class="bi bi-arrow-clockwise"></i></button>
                </div>
            </div>
            <!-- Buscador -->
            <div class="p-2 border-bottom bg-light">
                <input type="text" id="waSearchChat" class="form-control form-control-sm" placeholder="Buscar por número...">
            </div>
            <!-- Lista -->
            <div class="flex-grow-1 overflow-auto" id="waChatsList">
                <div class="text-center p-4 text-muted"><div class="spinner-border spinner-border-sm" role="status"></div> Cargando chats...</div>
            </div>
        </div>

        <!-- Panel Derecho: Conversación -->
        <div class="col-12 col-md-8 col-lg-9 p-0 d-flex flex-column bg-light" id="chatWindowContainer" style="height:100%;position:relative;">
            <!-- Header Chat -->
            <div class="p-3 border-bottom bg-white d-flex align-items-center d-none" id="waChatHeader">
                <!-- Botón retroceso — solo mobile -->
                <button class="btn btn-sm btn-light me-2 d-flex d-md-none align-items-center justify-content-center"
                        style="width:36px;height:36px;border-radius:50%;flex-shrink:0;"
                        onclick="WC_volverALista()" title="Volver a chats">
                    <i class="bi bi-arrow-left"></i>
                </button>
                <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width:45px;height:45px;flex-shrink:0;">
                    <i class="bi bi-person-fill fs-4"></i>
                </div>
                <div>
                    <h6 class="m-0 fw-bold" id="waChatName">Cargando...</h6>
                    <small class="text-muted" id="waChatPhone"></small>
                </div>
            </div>

            <!-- Empty State -->
            <div id="waChatEmptyState" class="flex-grow-1 d-flex flex-column align-items-center justify-content-center text-muted h-100">
                <i class="bi bi-whatsapp opacity-25" style="font-size:8rem;"></i>
                <h4 class="mt-3">WhatsApp Chat Center</h4>
                <p>Selecciona una conversación para comenzar a leer y escribir.</p>
            </div>

            <!-- Cuerpo de Mensajes -->
            <div class="flex-grow-1 overflow-auto p-4 d-none" id="waChatMessages"
                 style="background-image:url('<?= $ruta_assets ?>/img/wa-bg.png');background-color:#efeae2;">
            </div>

            <!-- Ventana de 24 h cerrada: solo se puede enviar una plantilla -->
            <div class="p-3 bg-white border-top d-none" id="waChatVentanaCerrada">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <i class="bi bi-clock-history text-warning fs-4"></i>
                    <div class="flex-grow-1 small">
                        <strong>Pasaron más de 24 horas desde el último mensaje del cliente</strong> (o aún no ha escrito).
                        WhatsApp solo permite enviarle una plantilla aprobada; cuando responda, podrá escribirle libremente.
                    </div>
                    <?php if (!empty($permisos['crear'])): ?>
                    <button type="button" class="btn btn-success btn-sm" onclick="WC_abrirPlantilla(true)">
                        <i class="bi bi-send me-1"></i>Enviar plantilla
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Input Area -->
            <div class="p-3 bg-white border-top d-none" id="waChatInputArea" style="position:relative;">

                <!-- Panel Respuestas Rápidas — flota sobre el chat, anclado al input -->
                <div id="waRespuestasPanel"
                     style="display:none;position:absolute;bottom:100%;left:0;right:0;z-index:200;
                            margin:0 0 4px 0;max-height:360px;
                            background:#fff;border:1px solid #dee2e6;border-radius:12px;
                            box-shadow:0 -4px 20px rgba(0,0,0,.12);
                            flex-direction:column;overflow:hidden;">
                    <!-- Header -->
                    <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom"
                         style="background:#f8f9fa;border-radius:12px 12px 0 0;flex-shrink:0;">
                        <span class="fw-semibold" style="font-size:.85rem;">
                            <i class="bi bi-lightning-charge-fill text-warning me-1"></i>Respuestas rápidas
                        </span>
                        <button type="button" class="btn-close" style="font-size:.7rem;"
                                onclick="WC_toggleRespuestasPanel(false)"></button>
                    </div>
                    <!-- Formulario inline crear/editar -->
                    <div id="waRRForm" style="display:none;flex-shrink:0;"
                         class="px-3 py-2 border-bottom bg-white">
                        <input type="hidden" id="waRRFormId"   value="">
                        <input type="hidden" id="waRRFormTipo" value="personal">
                        <input type="text" id="waRRFormTitulo"
                               class="form-control form-control-sm mb-1"
                               placeholder="Título (ej: Cuentas bancarias)" maxlength="100">
                        <textarea id="waRRFormContenido" class="form-control form-control-sm mb-1" rows="3"
                                  placeholder="Escribe aquí el texto completo..."
                                  style="resize:vertical;min-height:58px;font-size:.82rem;"></textarea>
                        <div class="d-flex gap-1 justify-content-end">
                            <button type="button" class="btn btn-sm btn-light border"
                                    onclick="WC_cancelarFormRR()">Cancelar</button>
                            <button type="button" class="btn btn-sm btn-success"
                                    onclick="WC_guardarRespuesta()">
                                <i class="bi bi-check-lg me-1"></i>Guardar
                            </button>
                        </div>
                    </div>
                    <!-- Lista scrolleable -->
                    <div id="waRRLista" style="overflow-y:auto;flex:1 1 auto;font-size:.82rem;">
                        <div class="text-center text-muted py-3">
                            <div class="spinner-border spinner-border-sm"></div>
                        </div>
                    </div>
                    <!-- Footer -->
                    <div class="d-flex border-top" style="background:#f8f9fa;border-radius:0 0 12px 12px;flex-shrink:0;font-size:.78rem;">
                        <button type="button"
                                class="btn btn-sm btn-light flex-fill rounded-0 py-1 border-end"
                                style="border-radius:0 0 0 12px !important;"
                                onclick="WC_nuevaRespuesta('empresa')"
                                title="Nueva respuesta para toda la empresa">
                            <i class="bi bi-building me-1 text-primary"></i>+ Empresa
                        </button>
                        <button type="button"
                                class="btn btn-sm btn-light flex-fill rounded-0 py-1"
                                style="border-radius:0 0 12px 0 !important;"
                                onclick="WC_nuevaRespuesta('personal')"
                                title="Nueva respuesta solo para ti">
                            <i class="bi bi-person me-1 text-secondary"></i>+ Personal
                        </button>
                    </div>
                </div>
                <!-- /Panel Respuestas Rápidas -->

                <form id="waChatForm" class="d-flex align-items-end"
                      onsubmit="event.preventDefault(); WC_enviarMensaje();">
                    <input type="file" id="waChatFileInput" class="d-none" accept="image/*,.pdf,.doc,.docx">
                    <button type="button" class="btn btn-light text-muted me-2 rounded-circle"
                            id="waChatBtnAttach" title="Adjuntar archivo"
                            onclick="document.getElementById('waChatFileInput').click();">
                        <i class="bi bi-paperclip"></i>
                    </button>
                    <button type="button" class="btn btn-light me-2 rounded-circle"
                            id="waChatBtnRespuestas" title="Respuestas rápidas"
                            onclick="WC_toggleRespuestasPanel()">
                        <i class="bi bi-lightning-charge-fill text-warning"></i>
                    </button>
                    <textarea class="form-control bg-light border-0 me-2" id="waChatInputText"
                              rows="1" placeholder="Escribe un mensaje..."
                              style="resize:none;"></textarea>
                    <button type="submit" class="btn btn-success rounded-circle"
                            id="waChatBtnSend" style="width:45px;height:45px;">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Nuevo chat / Enviar plantilla -->
    <div class="modal fade" id="waModalPlantilla" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="waPlTitulo"><i class="bi bi-whatsapp text-success me-2"></i>Nuevo chat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <!-- Destinatario nuevo -->
                            <div id="waPlDestinoNuevo">
                                <label class="form-label small fw-semibold mb-1">Buscar cliente</label>
                                <div class="position-relative mb-2">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                        <input type="text" class="form-control" id="waPlBuscarCliente" autocomplete="off"
                                               placeholder="Nombre, cédula/RUC o teléfono">
                                    </div>
                                    <div id="waPlClientesLista" class="list-group position-absolute w-100 shadow-sm d-none"
                                         style="z-index:1060;max-height:240px;overflow-y:auto;"></div>
                                </div>
                                <div class="row g-2">
                                    <div class="col-5">
                                        <label class="form-label small fw-semibold mb-1">Celular <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-sm" id="waPlCelular"
                                               maxlength="10" inputmode="numeric" placeholder="0958924835">
                                    </div>
                                    <div class="col-7">
                                        <label class="form-label small fw-semibold mb-1">Nombre del contacto</label>
                                        <input type="text" class="form-control form-control-sm" id="waPlNombre" maxlength="150">
                                    </div>
                                </div>
                            </div>
                            <!-- Destinatario del chat abierto -->
                            <div id="waPlDestinoFijo" class="d-none">
                                <label class="form-label small fw-semibold mb-1">Para</label>
                                <div class="form-control form-control-sm bg-light" id="waPlDestinoTexto"></div>
                            </div>

                            <label class="form-label small fw-semibold mb-1 mt-3">Plantilla aprobada <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="waPlPlantilla">
                                <option value="">Cargando…</option>
                            </select>
                            <div class="form-text" id="waPlPlantillaAyuda"></div>

                            <div id="waPlVariables" class="mt-2"></div>
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label small fw-semibold mb-1">Vista previa</label>
                            <div class="rounded p-3 d-flex flex-column" style="background-color:#efeae2;min-height:200px;">
                                <div id="waPlPreview" class="wa-bubble wa-bubble-out" style="max-width:100%;">
                                    <span class="text-muted">Elija una plantilla.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-sm btn-success" id="waPlBtnEnviar" onclick="WC_enviarPlantilla()">
                        <i class="bi bi-send me-1"></i>Enviar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Cargar JS Específico -->
    <script>
        const B_URL = '<?= rtrim(BASE_URL, '/') ?>';
    </script>
    <script src="<?= rtrim(BASE_URL, '/') ?>/js/modulos/whatsapp_chat.js?v=<?= asset_ver('/js/modulos/whatsapp_chat.js') ?>"></script>
    <?php endif; ?>
</div>

<style>
/* Pantalla completa: el chat llena todo el espacio bajo el header y la página no hace
   scroll; solo se desplazan la lista de chats y los mensajes. */
body.wa-chat-pantalla {
    overflow: hidden !important;
    height: 100dvh !important;
}
body.wa-chat-pantalla .cmg-main-content {
    height: calc(100dvh - var(--cmg-sticky-h));
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
body.wa-chat-pantalla .cmg-main-content > .container,
body.wa-chat-pantalla .cmg-main-content > .container-fluid {
    flex: 1 1 auto;
    min-height: 0;
    display: flex;
    flex-direction: column;
    max-width: 100% !important;
    width: 100% !important;
    padding: 0 !important;
}
.wa-chat-shell {
    flex: 1 1 auto;
    min-height: 0;
}
/* Con el header fijo (≥992px) el contenido arranca debajo con margen, no con padding,
   para no restarle esa altura al chat (mismo criterio que el app-shell de app.css). */
@media (min-width: 992px) {
    body.wa-chat-pantalla .cmg-main-content {
        padding-top: 0 !important;
        margin-top: var(--cmg-sticky-h) !important;
    }
}

/* Estilos extra para las burbujas de WhatsApp */
.wa-bubble {
    max-width: 75%;
    padding: 8px 12px;
    border-radius: 8px;
    position: relative;
    margin-bottom: 8px;
    box-shadow: 0 1px 1px rgba(0,0,0,0.1);
    font-size: 14.5px;
}
.wa-bubble-out {
    background-color: #d9fdd3;
    align-self: flex-end;
    border-top-right-radius: 0;
}
.wa-bubble-in {
    background-color: #ffffff;
    align-self: flex-start;
    border-top-left-radius: 0;
}
.wa-time {
    font-size: 11px;
    color: rgba(0,0,0,0.45);
    margin-top: 4px;
    text-align: right;
    display: flex;
    justify-content: flex-end;
    align-items: center;
}
.wa-status-icon {
    font-size: 14px;
    margin-left: 4px;
}
.wa-status-read {
    color: #53bdeb; /* Doble check azul */
}
.wa-status-delivered, .wa-status-sent {
    color: #8696a0; /* Gris */
}
.wa-chat-item {
    cursor: pointer;
    transition: background-color 0.2s;
}
.wa-chat-item:hover {
    background-color: #f5f6f6;
}
.wa-chat-active {
    background-color: #f0f2f5 !important;
}
#waChatMessages {
    display: flex;
    flex-direction: column;
}

/* Respuestas rápidas */
.wa-rr-item:hover {
    background-color: #f8f9fa;
}
#waChatBtnRespuestas.active {
    background-color: #fff8e1 !important;
    box-shadow: 0 0 0 2px #ffc10740;
}

/* ── Mobile: un panel a la vez ───────────────────────────── */
@media (max-width: 767.98px) {
    #chatListContainer,
    #chatWindowContainer {
        width: 100% !important;
        flex: 0 0 100% !important;
        max-width: 100% !important;
        height: 100% !important;
    }
    /* Panel de chat oculto por defecto en mobile */
    #chatWindowContainer {
        display: none !important;
    }
    /* Cuando hay un chat abierto en mobile: muestra ventana, oculta lista */
    #chatWindowContainer.wa-chat-abierto {
        display: flex !important;
    }
    #chatListContainer.wa-lista-oculta {
        display: none !important;
    }
}
</style>
