<?php
/**
 * IA Soporte — página STANDALONE (se abre en ventana aparte desde el ícono
 * del navbar, igual que Videos de Ayuda). No usa el layout principal: no
 * muestra el navbar/menú del sistema, solo el asistente de IA.
 *
 * @var string $titulo
 * @var array  $perm
 * @var string $rutaModulo
 * @var string $base
 * @var string|null $nombreEmpresa
 */
$base = rtrim($base ?? BASE_URL ?? '', '/');
$urlBase = $base . '/' . ltrim($rutaModulo, '/');
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($titulo) ?> | CaMaGaRe</title>
    <link rel="shortcut icon" type="image/png" href="<?= $base ?>/image/logofinal.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        html, body { height: 100%; }
        body { background: #f4f6f9; overflow: hidden; }
        .ia-wrap { display: flex; flex-direction: column; height: 100vh; }
        .ia-header { flex: 0 0 auto; }
        .ia-body { flex: 1 1 auto; min-height: 0; padding: 1rem; overflow: hidden; display: flex; flex-direction: column; }
        .ia-soporte-tabs .nav-link { cursor: pointer; }
        .ia-soporte-tab-content { flex: 1 1 auto; min-height: 0; display: flex; flex-direction: column; }
        .ia-soporte-chat-layout { display: flex; gap: 1rem; flex: 1 1 auto; min-height: 0; }
        .ia-soporte-chat-layout > .card { min-height: 0; display: flex; flex-direction: column; }
        .ia-soporte-chat-layout > .card > .card-body { min-height: 0; }
        .ia-soporte-conversaciones { width: 300px; flex-shrink: 0; }
        .ia-soporte-conv-scroll { flex: 1 1 auto; min-height: 0; overflow-y: auto; }
        .ia-soporte-panel { flex: 1; min-width: 0; }
        .ia-soporte-chat-scroll { flex: 1; min-height: 0; overflow-y: auto; padding: 1rem; background: #f8f9fa; border-radius: .5rem; }
        .ia-soporte-msg { max-width: 75%; margin-bottom: .75rem; }
        .ia-soporte-msg.user { margin-left: auto; }
        .ia-soporte-msg .bubble { padding: .55rem .9rem; border-radius: .75rem; white-space: pre-wrap; word-break: break-word; }
        .ia-soporte-msg.user .bubble { background: #0d6efd; color: #fff; }
        .ia-soporte-msg.assistant .bubble { background: #fff; border: 1px solid #dee2e6; }
        .ia-soporte-fuentes { font-size: .72rem; color: #6c757d; margin-top: .25rem; }
        .ia-fuentes-lista { display: flex; flex-direction: column; gap: .15rem; }
        .ia-fuente-chip { background: none; border: none; padding: 0; font-size: .72rem; color: #0d6efd; text-align: left; cursor: pointer; }
        .ia-fuente-chip:hover { text-decoration: underline; }
        .ia-fuente-texto { font-size: .78rem; color: #495057; background: #fff; border: 1px solid #dee2e6; border-left: 3px solid #0d6efd; border-radius: .3rem; padding: .5rem .6rem; margin: .2rem 0 .4rem 1.1rem; white-space: pre-wrap; }
        .ia-soporte-conv-item { cursor: pointer; border-radius: .4rem; }
        .ia-soporte-conv-item:hover { background: rgba(0,0,0,.04); }
        .ia-soporte-conv-item.active { background: rgba(13,110,253,.12); }
        .ia-soporte-docs-scroll { flex: 1 1 auto; min-height: 0; overflow: auto; }
        .ia-soporte-tab-pane { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; }
    </style>
</head>
<body>
<div class="ia-wrap">
    <!-- Encabezado -->
    <div class="ia-header d-flex align-items-center justify-content-between gap-2 px-3 py-2 bg-primary text-white shadow-sm">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-robot fs-4"></i>
            <div>
                <div class="fw-semibold lh-1"><?= htmlspecialchars($titulo) ?></div>
                <small class="text-white-50">Asistente legal, tributario y contable con IA</small>
            </div>
        </div>
        <?php if (!empty($nombreEmpresa)): ?>
            <div class="d-flex align-items-center gap-2 bg-white bg-opacity-10 rounded-pill px-3 py-1" title="Empresa activa">
                <i class="bi bi-building"></i>
                <span class="small fw-semibold text-truncate" style="max-width: 320px;"><?= htmlspecialchars($nombreEmpresa) ?></span>
            </div>
        <?php endif; ?>
    </div>

    <div class="ia-body">
        <ul class="nav nav-tabs ia-soporte-tabs mb-3">
            <li class="nav-item"><button type="button" class="nav-link active" data-ia-tab="chat"><i class="bi bi-chat-dots"></i> Chat</button></li>
            <li class="nav-item"><button type="button" class="nav-link" data-ia-tab="documentos"><i class="bi bi-file-earmark-pdf"></i> Documentos</button></li>
            <?php if ($perm['actualizar']): ?>
                <li class="nav-item"><button type="button" class="nav-link" data-ia-tab="config"><i class="bi bi-gear"></i> Configuración</button></li>
            <?php endif; ?>
            <?php if (!empty($esSuperadmin)): ?>
                <li class="nav-item"><button type="button" class="nav-link" data-ia-tab="prompts"><i class="bi bi-sliders"></i> Prompts</button></li>
            <?php endif; ?>
        </ul>

        <!-- Tab: Chat -->
        <div class="ia-soporte-tab-content ia-soporte-tab-pane" data-ia-tab-content="chat">
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
                        <div class="ia-soporte-conv-scroll" id="iaListaConversaciones">
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
        <div class="ia-soporte-tab-content ia-soporte-tab-pane d-none" data-ia-tab-content="documentos">
            <div class="card border-0 shadow-sm rounded-3 d-flex flex-column flex-fill" style="min-height:0;">
                <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0">Documentos cargados</h6>
                    <?php if ($perm['crear']): ?>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#iaModalSubirDoc">
                            <i class="bi bi-upload"></i> Subir PDF
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0 d-flex flex-column" style="min-height:0;">
                    <div class="ia-soporte-docs-scroll">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th data-col="titulo">Título</th>
                                    <th data-col="categoria">Categoría</th>
                                    <th data-col="agentes">Agentes</th>
                                    <th data-col="paginas" class="text-center">Páginas</th>
                                    <th data-col="estado" class="text-center">Estado</th>
                                    <th data-col="created_at">Cargado</th>
                                    <th class="text-end pe-3">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="iaTablaDocumentos">
                                <tr><td colspan="7" class="text-center text-muted py-4">Cargando…</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($perm['actualizar']): ?>
        <!-- Tab: Configuración -->
        <div class="ia-soporte-tab-content ia-soporte-tab-pane d-none" data-ia-tab-content="config">
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

        <?php if (!empty($esSuperadmin)): ?>
        <!-- Tab: Prompts (catálogo global de agentes) -->
        <div class="ia-soporte-tab-content ia-soporte-tab-pane d-none" data-ia-tab-content="prompts">
            <div class="card border-0 shadow-sm rounded-3 d-flex flex-column flex-fill" style="min-height:0;">
                <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h6 class="mb-0">Prompts de los agentes</h6>
                        <small class="text-muted">Plantillas globales que usan todas las empresas. Solo el superadministrador las edita.</small>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" id="iaBtnNuevoPrompt">
                        <i class="bi bi-plus-lg"></i> Nuevo prompt
                    </button>
                </div>
                <div class="card-body p-0 d-flex flex-column" style="min-height:0;">
                    <div class="ia-soporte-docs-scroll">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:40px;"></th>
                                    <th>Nombre</th>
                                    <th>Descripción</th>
                                    <th class="text-center">Orden</th>
                                    <th class="text-center">Estado</th>
                                </tr>
                            </thead>
                            <tbody id="iaTablaPrompts">
                                <tr><td colspan="5" class="text-center text-muted py-4">Cargando…</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
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
                    <div class="mb-3">
                        <label class="form-label">Agentes que pueden usar este documento</label>
                        <div id="iaSubirDocAgentes" class="border rounded-3 p-2" style="max-height:160px; overflow-y:auto;">
                            <p class="text-muted small mb-0">Cargando agentes…</p>
                        </div>
                        <small class="text-muted">Si no marca ninguno, el documento estará disponible para <strong>todos</strong> los agentes.</small>
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

<!-- Modal: Agentes de un documento -->
<div class="modal fade" id="iaModalDocAgentes" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="iaFormDocAgentes">
                <input type="hidden" name="id" id="iaDocAgentesId" value="">
                <div class="modal-header">
                    <h5 class="modal-title">Agentes que pueden usar este documento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div id="iaDocAgentesLista" class="border rounded-3 p-2" style="max-height:220px; overflow-y:auto;">
                        <p class="text-muted small mb-0">Cargando agentes…</p>
                    </div>
                    <small class="text-muted">Si no marca ninguno, el documento estará disponible para <strong>todos</strong> los agentes.</small>
                    <div class="alert alert-danger d-none mt-2" id="iaDocAgentesError"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if (!empty($esSuperadmin)): ?>
<!-- Modal: Crear/editar prompt -->
<div class="modal fade" id="iaModalPrompt" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="iaFormPrompt">
                <input type="hidden" name="id" id="iaPromptId" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="iaModalPromptTitulo">Nuevo prompt</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nombre <span class="text-danger">*</span></label>
                            <input type="text" name="nombre" id="iaPromptNombre" class="form-control" required placeholder="Ej: Agente Tributario">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Ícono (Bootstrap Icons)</label>
                            <input type="text" name="icono" id="iaPromptIcono" class="form-control" placeholder="bi-calculator">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Orden</label>
                            <input type="number" name="orden" id="iaPromptOrden" class="form-control" value="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <input type="text" name="descripcion" id="iaPromptDescripcion" class="form-control" placeholder="Resumen corto de la especialidad">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Prompt del sistema <span class="text-danger">*</span></label>
                            <textarea name="prompt_sistema" id="iaPromptTexto" class="form-control" rows="10" required placeholder="Instrucciones que definen el rol y comportamiento del agente..."></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="activo" value="1" id="iaPromptActivo" checked>
                                <label class="form-check-label" for="iaPromptActivo">Activo (visible para las empresas)</label>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-danger d-none mt-3" id="iaPromptError"></div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-danger d-none" id="iaBtnEliminarPrompt"><i class="bi bi-trash"></i> Eliminar</button>
                    <div class="ms-auto">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Guardar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    window.IA_SOPORTE_URL = '<?= $urlBase ?>';
    window.IA_SOPORTE_PERM = <?= json_encode($perm) ?>;
</script>
<script src="<?= $base ?>/js/modulos/ia-soporte.js?v=<?= time() ?>"></script>
</body>
</html>
