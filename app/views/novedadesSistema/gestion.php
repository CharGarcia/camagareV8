<?php
/**
 * Gestión de novedades del sistema (SOLO superadmin, nivel 3).
 * Listado estándar (cmg-table-card): buscador, orden por columna, paginación,
 * columnas visibles/anchos por usuario (PreferenciasHelper) y filas clicables
 * que abren el modal. Modal con barra de acciones + pestañas "Novedad"
 * (formulario con editor Quill) y "Leída por" (detalle de lecturas).
 */
$base = rtrim(BASE_URL ?? '', '/');
$rutaModulo   = $rutaModulo ?? 'config/novedades-sistema';
$rowsHtml     = $rowsHtml ?? '';
$paginacionHtml = $paginacionHtml ?? '';
$infoPaginacion = $infoPaginacion ?? '';
$ordenCol     = $ordenCol ?? 'publicado_at';
$ordenDir     = $ordenDir ?? 'DESC';
$buscar       = $buscar ?? '';
$tipos        = $tipos ?? [];
$estados      = $estados ?? [];
$vistaConfig  = $vistaConfig ?? [];
$columnasTabla = $columnasTabla ?? [];
$pestanasModal = $pestanasModal ?? [];
$submodulos   = $submodulos ?? [];
$totalUsuarios = (int) ($totalUsuarios ?? 0);
$e = static fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');

// Submódulos agrupados por módulo padre para el <select> con <optgroup>.
$gruposSub = [];
foreach ($submodulos as $s) {
    $ruta = trim((string) ($s['ruta'] ?? ''), '/');
    if ($ruta === '') {
        continue;
    }
    $gruposSub[(string) ($s['nombre_modulo'] ?? 'Otros')][] = ['ruta' => $ruta, 'nombre' => (string) ($s['nombre_submodulo'] ?? $ruta)];
}
?>
<script>window.CMG_NOVEDADES_NO_AUTO = true;</script>
<?= \App\Helpers\PreferenciasHelper::getJavascriptVariables($rutaModulo) ?>
<style>
    .novedades-header { flex-shrink: 0; }
    .novedades-scroll { max-height: calc(100dvh - 240px); overflow-y: auto; }
    .novedades-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; white-space: nowrap; }
    .nv-row { cursor: pointer; }
    .nv-row:hover { background-color: rgba(0, 0, 0, .04); }
    /* Quill trae .ql-container { height:100% }: dentro de una columna flex de
       Bootstrap ese 100% se resuelve contra el alto ya estirado de la columna
       (que incluye etiqueta, barra y texto de ayuda) y el editor se desborda
       sobre los campos de abajo. Altura FIJA en el contenedor = caja estable;
       el texto largo hace scroll dentro del editor. */
    #nvEditor { height: 240px !important; }
    #nvEditor .ql-editor { font-size: .9rem; }
    #nvModal .nav-tabs .nav-link { padding: .35rem .75rem; font-size: .85rem; }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig) ?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig) ?>

<div class="novedades-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-megaphone-fill me-1 text-primary"></i> <?= $e($titulo ?? 'Novedades del sistema') ?></h5>
    <div class="d-flex gap-2">
        <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm px-3"><i class="bi bi-arrow-left"></i> Volver</a>
        <button type="button" class="btn btn-primary btn-sm px-3" id="nvNueva"><i class="bi bi-plus-lg"></i> Nueva</button>
    </div>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <!-- Buscador y columnas -->
        <div class="d-flex align-items-center gap-2">
            <div class="input-group input-group-sm" style="width: 320px; max-width: 100%;">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control border-start-0" id="nvBuscar" placeholder="Buscar por título, resumen o módulo" value="<?= $e($buscar) ?>">
            </div>
            <div class="btn-group btn-group-sm">
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig, $rutaModulo) ?>
            </div>
            <span class="small text-muted d-none d-xl-inline">Usuarios activos: <b><?= $totalUsuarios ?></b></span>
        </div>

        <!-- Paginación -->
        <div class="d-flex align-items-center gap-3">
            <span id="nvPaginationInfo" class="text-muted small fw-medium"><?= $e($infoPaginacion) ?></span>
            <div id="nvPaginationContainer" class="btn-group btn-group-sm"><?= $paginacionHtml ?></div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card-body p-0">
        <div class="novedades-scroll w-100">
            <table class="table table-hover table-sm mb-0" id="nvTabla">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="publicado_at" data-col="publicado_at">Publicación <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="tipo" data-col="tipo">Tipo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="titulo" data-col="titulo">Título <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="modulo" data-col="modulo">Módulo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="vigente_hasta" data-col="vigente_hasta">Vigente hasta <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-center" role="button" data-sort="estado" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-center pe-3" role="button" data-sort="leidas" data-col="leidas">Leída por <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="nvTbody"><?= $rowsHtml ?></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: redactar / editar + lecturas -->
<div class="modal fade" id="nvModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" id="nvForm" autocomplete="off">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-semibold" id="nvModalTitulo">Nueva novedad</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="nvId" value="">
                <input type="hidden" name="contenido" id="nvContenido" value="">

                <!-- Barra de acciones (solo al editar): publicar / archivar + estado actual -->
                <div class="d-flex gap-1 align-items-center flex-wrap border-bottom pb-2 mb-2 d-none" id="nvBarraAcciones">
                    <button type="button" class="btn btn-sm btn-outline-success" id="nvBtnPublicar" title="Publicar: los usuarios la verán al ingresar">
                        <i class="bi bi-megaphone me-1"></i>Publicar
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-warning" id="nvBtnArchivar" title="Archivar: deja de mostrarse a los usuarios">
                        <i class="bi bi-archive me-1"></i>Archivar
                    </button>
                    <div class="vr mx-1"></div>
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25" title="Usuarios que la marcaron como leída / usuarios activos"><i class="bi bi-eye me-1"></i><span id="nvLeidasBadge">0 / 0</span></span>
                    <span class="ms-auto small text-muted" id="nvEstadoActual"></span>
                </div>

                <!-- Pestañas -->
                <ul class="nav nav-tabs mb-3 d-flex align-items-center" id="nvTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="nvTabForm" data-bs-toggle="tab" data-bs-target="#nvPaneForm" type="button" role="tab"><i class="bi bi-pencil-square me-1"></i>Novedad</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="nvTabAdjuntos" data-bs-toggle="tab" data-bs-target="#nvPaneAdjuntos" type="button" role="tab"><i class="bi bi-paperclip me-1"></i>Adjuntos <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 ms-1" id="nvAdjuntosBadge">0</span></button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="nvTabLeidos" data-bs-toggle="tab" data-bs-target="#nvPaneLeidos" type="button" role="tab"><i class="bi bi-eye me-1"></i>Leída por</button>
                    </li>
                    <?= \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasModal, $vistaConfig, $rutaModulo) ?>
                </ul>

                <div class="tab-content">
                    <!-- Pestaña: formulario -->
                    <div class="tab-pane fade show active" id="nvPaneForm" role="tabpanel">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small mb-1 d-block" for="nvTitulo">Título <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" name="titulo" id="nvTitulo" maxlength="200" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1 d-block" for="nvTipo">Tipo</label>
                                <select class="form-select form-select-sm" name="tipo" id="nvTipo">
                                    <?php foreach ($tipos as $k => $lbl): ?>
                                        <option value="<?= $e($k) ?>"><?= $e($lbl) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1 d-block" for="nvEstado">Estado</label>
                                <select class="form-select form-select-sm" name="estado" id="nvEstado">
                                    <?php foreach ($estados as $k => $lbl): ?>
                                        <option value="<?= $e($k) ?>"><?= $e($lbl) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label small mb-1 d-block" for="nvResumen">Resumen <span class="text-muted">(una línea, opcional)</span></label>
                                <input type="text" class="form-control form-control-sm" name="resumen" id="nvResumen" maxlength="300">
                            </div>
                            <div class="col-12">
                                <label class="form-label small mb-1 d-block">Contenido <span class="text-danger">*</span></label>
                                <div id="nvEditor" class="bg-white"></div>
                                <div class="form-text">Solo texto con formato (negrita, listas, títulos, enlaces). No admite imágenes.</div>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small mb-1 d-block" for="nvRutaModulo">Módulo relacionado</label>
                                <select class="form-select form-select-sm" name="ruta_modulo" id="nvRutaModulo">
                                    <option value="">— Ninguno —</option>
                                    <?php foreach ($gruposSub as $grupo => $subs): ?>
                                        <optgroup label="<?= $e($grupo) ?>">
                                            <?php foreach ($subs as $s): ?>
                                                <option value="<?= $e($s['ruta']) ?>"><?= $e($s['nombre']) ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">La ventana mostrará "Abrir módulo" y el enlace al manual de ese módulo.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-1 d-block" for="nvEnlace">Enlace <span class="text-muted">(opcional)</span></label>
                                <input type="text" class="form-control form-control-sm" name="enlace" id="nvEnlace" maxlength="500" placeholder="https://… o /modulos/…">
                                <div class="form-text">El usuario verá el botón "Abrir enlace".</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1 d-block" for="nvVigente">Vigente hasta <span class="text-danger">*</span></label>
                                <input type="date" class="form-control form-control-sm" name="vigente_hasta" id="nvVigente" required>
                                <div class="form-text">Desde esa fecha deja de mostrarse.</div>
                            </div>
                        </div>
                        <div class="small text-muted mt-3 d-none" id="nvPublicadoInfo"></div>
                    </div>

                    <!-- Pestaña: adjuntos descargables -->
                    <div class="tab-pane fade" id="nvPaneAdjuntos" role="tabpanel">
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                            <label class="btn btn-sm btn-outline-primary mb-0" for="nvAdjuntoInput" id="nvAdjuntoLabel">
                                <i class="bi bi-upload me-1"></i>Subir archivos
                            </label>
                            <input type="file" id="nvAdjuntoInput" class="d-none" multiple
                                   accept=".pdf,.xls,.xlsx,.csv,.doc,.docx,.ppt,.pptx,.txt,.png,.jpg,.jpeg,.gif,.webp,.zip">
                            <span class="small text-muted">PDF, Excel, Word, PowerPoint, imágenes, texto o ZIP. Máximo <?= (int) (\App\Services\NovedadSistemaService::MAX_ADJUNTO_BYTES / 1048576) ?> MB por archivo.</span>
                        </div>
                        <div class="progress mb-2 d-none" id="nvAdjuntoProgresoWrap" style="height: 14px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" id="nvAdjuntoProgreso" role="progressbar" style="width:0%; font-size:.65rem;">0%</div>
                        </div>
                        <div class="list-group list-group-flush border rounded" id="nvAdjuntosLista"></div>
                    </div>

                    <!-- Pestaña: quién la leyó -->
                    <div class="tab-pane fade" id="nvPaneLeidos" role="tabpanel">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="small text-muted" id="nvLeidosResumen">&nbsp;</span>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="nvLeidosRefrescar" title="Actualizar"><i class="bi bi-arrow-clockwise"></i></button>
                        </div>
                        <div>
                            <table class="table table-sm table-striped mb-0">
                                <thead class="table-light"><tr><th>Usuario</th><th>Empresa</th><th class="text-nowrap">Fecha</th></tr></thead>
                                <tbody id="nvLecturasTbody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2 d-flex">
                <button type="button" class="btn btn-outline-danger btn-sm me-auto d-none" id="nvEliminarModal"><i class="bi bi-trash me-1"></i>Eliminar</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm" id="nvGuardar"><i class="bi bi-save me-1"></i>Guardar</button>
            </div>
        </form>
    </div>
</div>

<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script>
    window.NV_CFG = {
        base: '<?= $base ?>',
        modulo: 'novedades_sistema',
        ordenCol: '<?= $e($ordenCol) ?>',
        ordenDir: '<?= $e($ordenDir) ?>'
    };
</script>
<script src="<?= $base ?>/js/novedades-sistema.js?v=<?= time() ?>"></script>
