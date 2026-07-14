<?php
/** @var string $titulo */
/** @var array $perm */
/** @var string $rutaModulo */
/** @var string $base */

$base = $base ?? BASE_URL;
$urlBase = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');
?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<style>
    .ia-soporte-wrap { display: flex; flex-direction: column; }
    .ia-soporte-tabs .nav-link { cursor: pointer; }
    .ia-soporte-chat-layout { display: flex; gap: 1rem; height: calc(100dvh - 260px); min-height: 420px; }
    .ia-soporte-conversaciones { width: 300px; flex-shrink: 0; }
    .ia-soporte-conv-scroll { max-height: calc(100dvh - 380px); overflow-y: auto; }
    .ia-soporte-panel { flex: 1; min-width: 0; }
    .ia-soporte-chat-scroll { flex: 1; overflow-y: auto; padding: 1rem; background: #f8f9fa; border-radius: .5rem; }
    .ia-soporte-msg { max-width: 75%; margin-bottom: .75rem; }
    .ia-soporte-msg.user { margin-left: auto; }
    .ia-soporte-msg .bubble { padding: .55rem .9rem; border-radius: .75rem; white-space: pre-wrap; word-break: break-word; }
    .ia-soporte-msg.user .bubble { background: #0d6efd; color: #fff; }
    .ia-soporte-msg.assistant .bubble { background: #fff; border: 1px solid #dee2e6; }
    .ia-soporte-fuentes { font-size: .72rem; color: #6c757d; margin-top: .25rem; }
    .ia-soporte-conv-item { cursor: pointer; border-radius: .4rem; }
    .ia-soporte-conv-item:hover { background: rgba(0,0,0,.04); }
    .ia-soporte-conv-item.active { background: rgba(13,110,253,.12); }
    .ia-soporte-docs-scroll { max-height: calc(100dvh - 340px); overflow: auto; }
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-robot"></i> <?= htmlspecialchars($titulo) ?></h5>
</div>

<ul class="nav nav-tabs ia-soporte-tabs mb-3">
    <li class="nav-item"><button type="button" class="nav-link active" data-ia-tab="chat"><i class="bi bi-chat-dots"></i> Chat</button></li>
    <li class="nav-item"><button type="button" class="nav-link" data-ia-tab="documentos"><i class="bi bi-file-earmark-pdf"></i> Documentos</button></li>
    <?php if ($perm['actualizar']): ?>
        <li class="nav-item"><button type="button" class="nav-link" data-ia-tab="config"><i class="bi bi-gear"></i> Configuración</button></li>
    <?php endif; ?>
</ul>

<div class="ia-soporte-wrap">

    <!-- Tab: Chat -->
    <div class="ia-soporte-tab-content" data-ia-tab-content="chat">
        <div class="ia-soporte-chat-layout">
            <div class="ia-soporte-conversaciones card border-0 shadow-sm">
                <div class="card-body p-2 d-flex flex-column h-100">
                    <label class="form-label small text-muted mb-1">Agente</label>
                    <select id="iaSelectAgente" class="form-select form-select-sm mb-2"></select>
                    <?php if ($perm['crear']): ?>
                        <button type="button" class="btn btn-primary btn-sm mb-2" id="iaBtnNuevaConv">
                            <i class="bi bi-plus-lg"></i> Nueva conversación
                        </button>
                    <?php endif; ?>
                    <div class="ia-soporte-conv-scroll flex-grow-1" id="iaListaConversaciones">
                        <p class="text-muted small text-center mt-3">Cargando…</p>
                    </div>
                </div>
            </div>
            <div class="ia-soporte-panel card border-0 shadow-sm">
                <div class="card-body d-flex flex-column p-3">
                    <div class="ia-soporte-chat-scroll" id="iaChatMensajes">
                        <p class="text-muted text-center mt-5">Seleccione o cree una conversación para empezar.</p>
                    </div>
                    <form id="iaFormMensaje" class="d-flex gap-2 mt-3">
                        <input type="text" id="iaInputPregunta" class="form-control" placeholder="Escriba su pregunta…" autocomplete="off" disabled>
                        <button type="submit" class="btn btn-primary" id="iaBtnEnviar" disabled><i class="bi bi-send"></i></button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab: Documentos -->
    <div class="ia-soporte-tab-content d-none" data-ia-tab-content="documentos">
        <div class="card cmg-table-card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="mb-0">Documentos cargados</h6>
                <?php if ($perm['crear']): ?>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#iaModalSubirDoc">
                        <i class="bi bi-upload"></i> Subir PDF
                    </button>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <div class="ia-soporte-docs-scroll">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th data-col="titulo">Título</th>
                                <th data-col="categoria">Categoría</th>
                                <th data-col="paginas" class="text-center">Páginas</th>
                                <th data-col="estado" class="text-center">Estado</th>
                                <th data-col="created_at">Cargado</th>
                                <th class="text-end pe-3">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="iaTablaDocumentos">
                            <tr><td colspan="6" class="text-center text-muted py-4">Cargando…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php if ($perm['actualizar']): ?>
    <!-- Tab: Configuración -->
    <div class="ia-soporte-tab-content d-none" data-ia-tab-content="config">
        <div class="card border-0 shadow-sm rounded-3" style="max-width: 560px;">
            <div class="card-body">
                <h6 class="mb-2">Configuración del proveedor de IA (BYOK)</h6>
                <p class="text-muted small">
                    Cada empresa configura su propio proveedor y API key. El sistema no factura por el consumo de IA:
                    el gasto lo administra directamente la empresa con su proveedor.
                </p>
                <form id="iaFormConfig">
                    <div class="mb-3">
                        <label class="form-label">Proveedor</label>
                        <select class="form-select" id="iaConfigProveedor" name="proveedor">
                            <option value="openai">OpenAI (ChatGPT)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Modelo</label>
                        <input type="text" class="form-control" id="iaConfigModelo" name="modelo_chat" value="gpt-4o-mini" placeholder="ej. gpt-4o-mini">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">API key</label>
                        <input type="password" class="form-control" id="iaConfigApiKey" name="api_key" placeholder="sk-...">
                        <small class="text-muted d-block mt-1" id="iaConfigEstadoTexto"></small>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Guardar</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- Modal: Subir documento -->
<div class="modal fade" id="iaModalSubirDoc" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="iaFormSubirDoc" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Subir documento PDF</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Título</label>
                        <input type="text" class="form-control" name="titulo" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Categoría</label>
                        <input type="text" class="form-control" name="categoria" placeholder="tributario, laboral, contable, societario…">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Archivo PDF (máx. 20 MB)</label>
                        <input type="file" class="form-control" name="archivo" accept="application/pdf" required>
                    </div>
                    <div class="alert alert-danger d-none" id="iaSubirDocError"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="iaBtnSubirDoc">Subir</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    window.IA_SOPORTE_URL = '<?= $urlBase ?>';
    window.IA_SOPORTE_PERM = <?= json_encode($perm) ?>;
</script>
<script src="<?= $base ?>/js/modulos/ia-soporte.js?v=<?= time() ?>"></script>
