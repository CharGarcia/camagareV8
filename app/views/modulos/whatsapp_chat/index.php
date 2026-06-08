<?php
/**
 * Vista de Bandeja de Entrada (Chat Center)
 */
$ruta_assets = '/sistema/public';
?>

<div class="container h-100 p-0 py-lg-3">
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
    
    <div class="row m-0 border rounded shadow-sm overflow-hidden" style="height: calc(100dvh - 160px);">
        <!-- Sidebar Izquierdo: Lista de Chats -->
        <div class="col-md-4 col-lg-3 border-end bg-white p-0 d-flex flex-column" id="chatListContainer" style="height: 100%;">
            <div class="p-3 border-bottom bg-light d-flex align-items-center justify-content-between">
                <h5 class="m-0 fw-bold"><i class="bi bi-chat-left-text text-success me-2"></i> Chats</h5>
                <button class="btn btn-sm btn-outline-secondary" onclick="WC_cargarChats()"><i class="bi bi-arrow-clockwise"></i></button>
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
        <div class="col-md-8 col-lg-9 p-0 d-flex flex-column bg-light" id="chatWindowContainer" style="height:100%;position:relative;">
            <!-- Header Chat -->
            <div class="p-3 border-bottom bg-white d-flex align-items-center d-none" id="waChatHeader">
                <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width:45px;height:45px;">
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

    <!-- Cargar JS Específico -->
    <script>
        const B_URL = '<?= rtrim(BASE_URL, '/') ?>';
    </script>
    <script src="<?= rtrim(BASE_URL, '/') ?>/js/modulos/whatsapp_chat.js?v=<?= time() ?>"></script>
    <?php endif; ?>
</div>

<style>
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
</style>
