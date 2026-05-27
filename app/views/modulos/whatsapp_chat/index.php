<?php
/**
 * Vista de Bandeja de Entrada (Chat Center)
 */
$ruta_assets = '/sistema/public';
?>

<div class="container-fluid h-100 p-0">
    <?php if (!$configurado): ?>
    <div class="row h-100 m-0 align-items-center justify-content-center bg-light">
        <div class="col-md-6 text-center">
            <div class="card shadow-sm border-0">
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
    
    <div class="row h-100 m-0" style="min-height: calc(100vh - 120px);">
        <!-- Sidebar Izquierdo: Lista de Chats -->
        <div class="col-md-4 col-lg-3 border-end bg-white p-0 d-flex flex-column" id="chatListContainer">
            <div class="p-3 border-bottom bg-light d-flex align-items-center justify-content-between">
                <h5 class="m-0 fw-bold"><i class="bi bi-chat-left-text text-success me-2"></i> Chats</h5>
                <button class="btn btn-sm btn-outline-secondary" onclick="WC_cargarChats()"><i class="bi bi-arrow-clockwise"></i></button>
            </div>
            <!-- Buscador -->
            <div class="p-2 border-bottom bg-light">
                <input type="text" id="waSearchChat" class="form-control form-control-sm" placeholder="Buscar por número...">
            </div>
            <!-- Lista -->
            <div class="flex-grow-1 overflow-auto" id="waChatsList" style="max-height: 100%;">
                <div class="text-center p-4 text-muted"><div class="spinner-border spinner-border-sm" role="status"></div> Cargando chats...</div>
            </div>
        </div>

        <!-- Panel Derecho: Conversación -->
        <div class="col-md-8 col-lg-9 p-0 d-flex flex-column bg-light" id="chatWindowContainer">
            <!-- Header Chat -->
            <div class="p-3 border-bottom bg-white d-flex align-items-center d-none" id="waChatHeader">
                <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 45px; height: 45px;">
                    <i class="bi bi-person-fill fs-4"></i>
                </div>
                <div>
                    <h6 class="m-0 fw-bold" id="waChatName">Cargando...</h6>
                    <small class="text-muted" id="waChatPhone"></small>
                </div>
            </div>

            <!-- Empty State -->
            <div id="waChatEmptyState" class="flex-grow-1 d-flex flex-column align-items-center justify-content-center text-muted h-100">
                <i class="bi bi-whatsapp opacity-25" style="font-size: 8rem;"></i>
                <h4 class="mt-3">WhatsApp Chat Center</h4>
                <p>Selecciona una conversación para comenzar a leer y escribir.</p>
            </div>

            <!-- Cuerpo de Mensajes -->
            <div class="flex-grow-1 overflow-auto p-4 d-none" id="waChatMessages" style="background-image: url('<?= $ruta_assets ?>/img/wa-bg.png'); background-color: #efeae2; max-height: 100%;">
                <!-- Los mensajes se inyectan aquí -->
            </div>

            <!-- Input Area -->
            <div class="p-3 bg-white border-top d-none" id="waChatInputArea">
                <form id="waChatForm" class="d-flex align-items-end" onsubmit="event.preventDefault(); WC_enviarMensaje();">
                    <input type="file" id="waChatFileInput" class="d-none" accept="image/*,.pdf,.doc,.docx">
                    <button type="button" class="btn btn-light text-muted me-2 rounded-circle" id="waChatBtnAttach" onclick="document.getElementById('waChatFileInput').click();"><i class="bi bi-paperclip"></i></button>
                    <textarea class="form-control bg-light border-0 me-2" id="waChatInputText" rows="1" placeholder="Escribe un mensaje..." style="resize: none;"></textarea>
                    <button type="submit" class="btn btn-success rounded-circle" id="waChatBtnSend" style="width: 45px; height: 45px;"><i class="bi bi-send-fill"></i></button>
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
</style>
