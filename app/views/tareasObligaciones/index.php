<?php

/** @var array  $obligacionesActivas */
/** @var array  $responsablesFiltro */
/** @var int    $idUsuarioActual */
/** @var int    $nivelUsuarioActual */
$base      = BASE_URL;
$tabActiva = in_array($tab, ['tareas', 'obligaciones'], true) ? $tab : 'tareas';
?>
<style>
    /* ── Pestañas ── */
    .nav-tabs-cmg .nav-link {
        color: var(--bs-secondary);
        font-size: .75rem;
        padding: 4px 10px;
        border-radius: 4px 4px 0 0;
    }

    .nav-tabs-cmg .nav-link.active {
        color: var(--bs-primary);
        font-weight: 600;
        background: #fff;
        border-bottom: 2px solid var(--bs-primary);
    }

    /* ── Tabla ── */
    .cmg-table-card {
        border-radius: 8px;
        box-shadow: 0 1px 4px rgba(0, 0, 0, .06);
    }

    .cmg-table-card .card-header {
        background: #fff;
        border-bottom: 1px solid #e8eaf0;
        padding: .35rem .7rem;
        border-radius: 8px 8px 0 0;
    }

    .cmg-table-card .table-scroll {
        overflow-y: auto;
        max-height: calc(100dvh - 290px);
    }

    .cmg-table-card .table thead th {
        background: #f7f8fc;
        font-size: .68rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .02em;
        padding: 4px 6px;
        position: sticky;
        top: 0;
        z-index: 1;
    }

    .cmg-table-card .table tbody td {
        font-size: .75rem;
        vertical-align: middle;
        padding: 4px 6px;
    }

    .sortable-header {
        cursor: pointer;
        user-select: none;
        white-space: nowrap;
    }

    .sortable-header i {
        font-size: .65rem;
        margin-left: 2px;
    }

    /* ── Marco del listado ── */
    .cmg-dashboard-frame {
        border: 1px solid #e0e4ec;
        border-radius: 12px;
        background: #fff;
        padding: 1.25rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, .03);
        margin-bottom: 2rem;
    }

    .cmg-dashboard-frame h6 {
        color: #2d3748;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    /* ── Autocomplete ── */
    .autocomplete-wrap {
        position: relative;
    }

    .autocomplete-list {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        z-index: 1060;
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        max-height: 160px;
        overflow-y: auto;
        box-shadow: 0 3px 10px rgba(0, 0, 0, .1);
        display: none;
    }

    .autocomplete-list .ac-item {
        padding: 4px 8px;
        cursor: pointer;
        font-size: .75rem;
        border-bottom: 1px solid #f0f0f0;
    }

    .autocomplete-list .ac-item:last-child {
        border-bottom: none;
    }

    /* ── Responsables/Tags ── */
    .tags-wrap {
        display: flex;
        flex-wrap: wrap;
        gap: 2px;
        padding: 2px 4px;
        border: 1px solid #ced4da;
        border-radius: 4px;
        min-height: 28px;
        background: #fff;
    }

    .tag-item {
        display: inline-flex;
        align-items: center;
        gap: 2px;
        background: #eef4ff;
        color: #1a56db;
        border-radius: 15px;
        padding: 0 6px;
        font-size: .7rem;
        line-height: 1.4;
        border: 1px solid rgba(13, 110, 253, .08);
    }

    .tag-item .tag-remove {
        cursor: pointer;
        color: #1a56db;
        opacity: .7;
        font-size: .75rem;
        margin-left: 2px;
    }

    .tags-input {
        border: none;
        outline: none;
        flex: 1;
        min-width: 80px;
        font-size: .75rem;
        background: transparent;
        padding: 0 2px;
    }

    /* ── Utilidades ── */
    .extra-small {
        font-size: .68rem !important;
    }

    .form-label-sm {
        font-size: .7rem;
        font-weight: 600;
        margin-bottom: 1px;
        color: #444;
    }

    .modal-body {
        padding: 0.65rem;
    }

    .modal-header,
    .modal-footer {
        padding: 0.4rem 0.65rem;
    }

    .row.g-1 {
        --bs-gutter-x: 0.25rem;
        --bs-gutter-y: 0.25rem;
    }

    /* ── Fix Modales Anidados ── */
    #modalOblig {
        z-index: 1061;
    }

    /* Asegurar que el fondo del segundo modal sea superior */
    .modal-backdrop.show:nth-of-type(even) {
        z-index: 1060;
    }
</style>

<!-- ══════════════════════════════════════════════════════ -->
<!-- Encabezado -->
<!-- ══════════════════════════════════════════════════════ -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-list-check"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">Gestión de obligaciones y asignación de tareas.</p>
    </div>
</div>

<!-- Pestañas -->
<ul class="nav nav-tabs nav-tabs-cmg mb-3" id="tabsTareas" role="tablist">
    <li class="nav-item">
        <a class="nav-link<?= $tabActiva === 'tareas' ? ' active' : '' ?>"
            href="<?= $base ?>/config/tareas-obligaciones"
            onclick="cambiarTab('tareas',event)"
            id="tab-tareas-link"><i class="bi bi-list-check me-1"></i>Tareas</a>
    </li>
    <li class="nav-item">
        <a class="nav-link<?= $tabActiva === 'obligaciones' ? ' active' : '' ?>"
            href="<?= $base ?>/config/tareas-obligaciones?tab=obligaciones"
            onclick="cambiarTab('obligaciones',event)"
            id="tab-oblig-link"><i class="bi bi-journal-text me-1"></i>Lista de Obligaciones</a>
    </li>
</ul>

<!-- ══════════════════════════════════════════════════════ -->
<!--  TAB: TAREAS -->
<!-- ══════════════════════════════════════════════════════ -->
<div id="panel-tareas" class="tab-panel<?= $tabActiva === 'tareas' ? '' : ' d-none' ?>">
    <div class="cmg-dashboard-frame">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold mb-0"><i class="bi bi-list-task text-primary"></i> Listado de Tareas</h6>
            <button class="btn btn-primary btn-sm" id="btn-nueva-tarea" onclick="abrirModalTareaNueva()">
                <i class="bi bi-plus-lg"></i> Nueva tarea
            </button>
        </div>
        <div class="card cmg-table-card">
            <div class="card-header px-3 py-2 border-bottom-0">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <!-- Exportar -->
                    <div class="btn-group btn-group-sm me-1">
                        <button class="btn btn-outline-danger" title="PDF" onclick="alert('Próximamente')" id="btn-pdf-tareas"><i class="bi bi-file-earmark-pdf"></i></button>
                        <button class="btn btn-outline-success" title="Excel" onclick="alert('Próximamente')" id="btn-excel-tareas"><i class="bi bi-file-earmark-excel"></i></button>
                    </div>
                    <!-- Buscador -->
                    <div class="input-group input-group-sm" style="max-width:280px">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" id="buscar-tareas" class="form-control border-start-0" placeholder="Buscar por cliente u obligación…" autocomplete="off" oninput="buscarTareasDebounce()">
                    </div>
                    <!-- Paginación derecha -->
                    <span class="text-muted small ms-auto me-2" id="info-tareas">-</span>
                    <div id="pagination-tareas"></div>
                </div>
            </div>

            <!-- Fila de Filtros Avanzados -->
            <div class="card-body py-2 px-3 bg-light bg-opacity-50 border-top border-bottom">
                <div class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label class="extra-small text-muted mb-0 fw-bold">Estado</label>
                        <select id="filtro-estado" class="form-select form-select-sm" onchange="buscarTareas(1)">
                            <option value="">- Todos -</option>
                            <option value="por_realizar">Por realizar</option>
                            <option value="realizada_continua">Realizada y continua</option>
                            <option value="realizada_finalizada">Realizada y finalizada</option>
                            <option value="vencida">Vencida</option>
                            <option value="cancelada">Cancelada</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="extra-small text-muted mb-0 fw-bold">Obligación</label>
                        <select id="filtro-obligacion" class="form-select form-select-sm" onchange="buscarTareas(1)">
                            <option value="">- Todas -</option>
                            <?php foreach ($obligacionesActivas as $ob): ?>
                                <option value="<?= $ob['id'] ?>"><?= htmlspecialchars($ob['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="extra-small text-muted mb-0 fw-bold">Responsable</label>
                        <select id="filtro-responsable" class="form-select form-select-sm" onchange="buscarTareas(1)">
                            <option value="">- Todos -</option>
                            <optgroup label="Usuarios">
                                <?php foreach ($responsablesFiltro['usuarios'] as $u): ?>
                                    <option value="u_<?= $u['id'] ?>">
                                        <?= htmlspecialchars($u['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Externos">
                                <?php foreach ($responsablesFiltro['propios'] as $p): ?>
                                    <option value="r_<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="extra-small text-muted mb-0 fw-bold">Desde</label>
                        <input type="date" id="filtro-desde" class="form-control form-control-sm" onchange="buscarTareas(1)">
                    </div>
                    <div class="col-md-2">
                        <label class="extra-small text-muted mb-0 fw-bold">Hasta</label>
                        <input type="date" id="filtro-hasta" class="form-control form-control-sm" onchange="buscarTareas(1)">
                    </div>
                    <div class="col-md-2">
                        <div class="d-flex gap-1">
                            <div class="form-check form-switch mb-0 flex-grow-1 d-flex align-items-center">
                                <input class="form-check-input mt-0" type="checkbox" id="toggle-archivadas" onchange="buscarTareas(1)">
                                <label class="form-check-label extra-small text-muted ms-1" for="toggle-archivadas">Solo Archivadas</label>
                            </div>
                            <button class="btn btn-outline-secondary btn-sm" onclick="limpiarFiltros()" title="Limpiar filtros">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="table-scroll">
                <table class="table table-hover mb-0" id="tabla-tareas">
                    <thead>
                        <tr>
                            <th class="sortable-header ps-3" onclick="sortTareas('cliente_nombre')">Cliente <i class="bi bi-arrow-down-up text-muted" id="sort-icon-tareas-cliente_nombre"></i></th>
                            <th class="sortable-header" onclick="sortTareas('obligacion')">Obligación <i class="bi bi-arrow-down-up text-muted" id="sort-icon-tareas-obligacion"></i></th>
                            <th>Creado por</th>
                            <th>Responsables</th>
                            <th class="sortable-header text-center" onclick="sortTareas('periodicidad')">Periodicidad <i class="bi bi-arrow-down-up text-muted" id="sort-icon-tareas-periodicidad"></i></th>
                            <th class="sortable-header text-center" onclick="sortTareas('fecha_tarea')">Fecha Tarea <i class="bi bi-arrow-down-up text-muted" id="sort-icon-tareas-fecha_tarea"></i></th>
                            <th class="sortable-header text-center" onclick="sortTareas('estado')">Estado <i class="bi bi-arrow-down-up text-muted" id="sort-icon-tareas-estado"></i></th>
                            <th class="pe-3">Notas</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-tareas">
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <div class="spinner-border spinner-border-sm me-2"></div>Cargando…
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════ -->
<!--  TAB: OBLIGACIONES -->
<!-- ══════════════════════════════════════════════════════ -->
<div id="panel-obligaciones" class="tab-panel<?= $tabActiva === 'obligaciones' ? '' : ' d-none' ?>">
    <div class="cmg-dashboard-frame">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold"><i class="bi bi-journal-check text-success"></i> Listado de Obligaciones</h6>
            <button class="btn btn-primary btn-sm ms-auto" onclick="abrirModalObligNueva()">
                <i class="bi bi-plus-lg"></i> Nueva obligación
            </button>
        </div>
        <div class="card cmg-table-card">
            <div class="card-header">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <div class="btn-group btn-group-sm me-1">
                        <button class="btn btn-outline-danger" title="PDF" onclick="alert('Próximamente')"><i class="bi bi-file-earmark-pdf"></i></button>
                        <button class="btn btn-outline-success" title="Excel" onclick="alert('Próximamente')"><i class="bi bi-file-earmark-excel"></i></button>
                    </div>
                    <div class="input-group input-group-sm" style="max-width:280px">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" id="buscar-oblig" class="form-control border-start-0" placeholder="Buscar obligación…" autocomplete="off" oninput="buscarObligDebounce()">
                    </div>

                    <span class="text-muted small ms-2" id="info-oblig">-</span>
                    <div id="pagination-oblig"></div>
                </div>
            </div>
            <div class="table-scroll">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="sortable-header ps-3" onclick="sortOblig('nombre')">Nombre <i class="bi bi-arrow-down-up text-muted" id="sort-icon-oblig-nombre"></i></th>
                            <th>Descripción</th>
                            <th class="sortable-header text-center" onclick="sortOblig('status')">Estado <i class="bi bi-arrow-down-up text-muted" id="sort-icon-oblig-status"></i></th>
                            <th class="sortable-header text-center pe-3" onclick="sortOblig('created_at')">Creado <i class="bi bi-arrow-down-up text-muted" id="sort-icon-oblig-created_at"></i></th>
                        </tr>
                    </thead>
                    <tbody id="tbody-oblig">
                        <tr>
                            <td colspan="4" class="text-center py-5 text-muted">
                                <div class="spinner-border spinner-border-sm me-2"></div>Cargando…
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>


<!-- ════════════════════════════════════════════════════════════════ -->
<!--  MODAL: TAREA (Crear / Editar)                                 -->
<!-- ════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalTarea" tabindex="-1" aria-labelledby="modalTareaLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTareaLabel"><i class="bi bi-list-check me-1"></i><span id="tarea-modal-titulo">Nueva Tarea</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="tarea-id" value="">
                <input type="hidden" id="tarea-id-origen" value="">

                <div class="row g-2">
                    <!-- ─── Cliente (Nombre y Correo horizontal) ─── -->
                    <div class="col-12 mb-2 p-2 border-bottom">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label-sm fw-bold">Cliente <span class="text-danger">*</span></label>
                                <div class="autocomplete-wrap">
                                    <input type="text" id="tarea-cliente-buscar" class="form-control form-control-sm"
                                        placeholder="Buscar o escribir nombre…"
                                        autocomplete="off" oninput="buscarClienteAC(this.value)">
                                    <div id="tarea-cliente-ac" class="autocomplete-list"></div>
                                </div>
                                <input type="hidden" id="tarea-id-cliente" value="">
                            </div>
                            <div class="col-md-6 border-start ps-3">
                                <label class="form-label-sm fw-bold">Correo(s) para notificación <span class="text-danger">*</span></label>
                                <div class="tags-wrap" id="tags-correos-cliente" onclick="document.getElementById('correo-input').focus()">
                                    <input type="text" id="correo-input" class="tags-input"
                                        placeholder="Enter o coma..."
                                        onkeydown="onCorreoKeydown(event)"
                                        onblur="commitCorreo()"
                                        autocomplete="off">
                                </div>
                                <div id="tarea-correos-wrap" class="mt-1 d-none">
                                    <div id="tarea-correos-list" class="d-flex flex-wrap gap-1"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ─── Obligación + Periodicidad + Fecha ─── -->
                    <div class="col-md-5 mb-2">
                        <label class="form-label-sm fw-bold">Obligación <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <select id="tarea-obligacion" class="form-select form-select-sm">
                                <option value="">- Seleccionar -</option>
                                <?php foreach ($obligacionesActivas as $ob): ?>
                                    <option value="<?= $ob['id'] ?>"><?= htmlspecialchars($ob['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-outline-secondary btn-sm" type="button" onclick="abrirModalObligNuevaDesdeModal()" title="Crear nueva obligación">
                                <i class="bi bi-plus"></i></button>
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label-sm fw-bold">Periodicidad <span class="text-danger">*</span></label>
                        <select id="tarea-periodicidad" class="form-select form-select-sm">
                            <option value="">- Seleccionar -</option>
                            <option value="semanal">Semanal</option>
                            <option value="quincenal">Quincenal</option>
                            <option value="mensual">Mensual</option>
                            <option value="trimestral">Trimestral</option>
                            <option value="semestral">Semestral</option>
                            <option value="anual">Anual</option>
                            <option value="dos_anios">2 Años</option>
                            <option value="tres_anios">3 Años</option>
                            <option value="cuatro_anios">4 Años</option>
                            <option value="cinco_anios">5 Años</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="form-label-sm fw-bold">Fecha <span class="text-danger">*</span></label>
                        <input type="date" id="tarea-fecha" class="form-control form-control-sm" onchange="onTareaFechaCambio()">
                    </div>

                    <!-- ─── Responsables (Nombre y Correo horizontal) ─── -->
                    <div class="col-12 mb-2">
                        <div class="p-2 border rounded bg-light bg-opacity-75 shadow-sm">
                            <label class="form-label-sm fw-bold mb-1">Cargar Responsable <span class="text-danger">*</span></label>
                            <div class="row g-2 align-items-end">
                                <div class="col-md-5">
                                    <label class="extra-small text-muted mb-0">Nombre o búsqueda</label>
                                    <div class="autocomplete-wrap">
                                        <input type="text" id="resp-input" class="form-control form-control-sm"
                                            placeholder="Buscar o escribir nombre…"
                                            autocomplete="off" oninput="buscarRespAC(this.value)"
                                            onkeydown="onRespKeydown(event)">
                                        <div id="resp-ac" class="autocomplete-list"></div>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <label class="extra-small text-muted mb-0">Correo</label>
                                    <input type="email" id="resp-mail-input" class="form-control form-control-sm"
                                        placeholder="ejemplo@correo.com" onkeydown="onRespKeydown(event)">
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-primary btn-sm w-100" onclick="commitResponsableManual()">
                                        <i class="bi bi-plus-lg"></i> Añadir
                                    </button>
                                </div>
                            </div>
                            <div class="mt-2 d-flex flex-wrap gap-1" id="tags-responsables"></div>
                            <div id="resp-hidden-inputs"></div>
                        </div>
                    </div>

                    <!-- ─── Estado + Notas ─── -->
                    <div class="col-md-4 mb-2">
                        <label class="form-label-sm fw-bold">Estado <span class="text-danger">*</span></label>
                        <select id="tarea-estado" class="form-select form-select-sm" onchange="onEstadoCambio()">
                            <option value="por_realizar">Por realizar</option>
                            <option value="realizada_continua">Realizada y continua</option>
                            <option value="realizada_finalizada">Realizada y finalizada</option>
                            <option value="vencida">Vencida</option>
                            <option value="cancelada">Cancelada</option>
                        </select>
                    </div>
                    <div class="col-md-8 mb-2">
                        <label class="form-label-sm">Notas adicionales</label>
                        <textarea id="tarea-notas" class="form-control form-control-sm" rows="1" placeholder="Comentarios opcionales…"></textarea>
                    </div>

                    <!-- ─── Bloques Condicionales (Se muestran vía JS) ─── -->
                    <div id="bloque-realizada" class="col-12 d-none">
                        <hr class="my-1">
                        <label class="form-label-sm fw-bold">Resumen de lo realizado <span class="text-danger">*</span></label>
                        <textarea id="tarea-resumen" class="form-control form-control-sm" rows="2" placeholder="Describe lo que hiciste…"></textarea>
                        <div class="mt-2">
                            <label class="form-label-sm">Documentos adjuntos</label>
                            <div id="adjuntos-lista" class="d-flex flex-column gap-1 mb-2"></div>
                            <div id="upload-section">
                                <label class="btn btn-sm btn-outline-secondary" for="input-adjunto">
                                    <i class="bi bi-paperclip me-1"></i> Adjuntar archivo
                                </label>
                                <input type="file" id="input-adjunto" class="d-none" accept=".pdf,.doc,.docx,.xls,.xlsx,.ods,.jpg,.jpeg,.png,.gif,.webp,.xml" onchange="subirAdjunto(this)">
                                <small class="text-muted ms-2">Máx. 200 KB &bull; PDF, Word, Excel, imágenes, XML</small>
                            </div>
                        </div>
                    </div>

                    <div id="bloque-cancelada" class="col-12 d-none">
                        <hr class="my-1">
                        <label class="form-label small fw-semibold text-danger"><i class="bi bi-x-circle me-1"></i>Motivo de cancelación <span class="text-danger">*</span></label>
                        <textarea id="tarea-motivo" class="form-control form-control-sm" rows="2" placeholder="Explica por qué se canceló…"></textarea>
                        <div class="alert alert-warning mt-2 py-2 small"><i class="bi bi-archive me-1"></i>La tarea será <strong>archivada</strong> al cancelarse.</div>
                    </div>

                    <!-- Badge de origen recurrente -->
                    <div id="tarea-origen-badge" class="col-12 d-none">
                        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 small">
                            <i class="bi bi-arrow-repeat me-1"></i>Tarea generada automáticamente (recurrente)
                        </span>
                    </div>
                </div>
                <div id="tarea-error" class="alert alert-danger mt-2 d-none py-2 px-3 small"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger btn-sm me-auto d-none" id="btn-tarea-delete" onclick="eliminarTarea()"><i class="bi bi-trash me-1"></i>Eliminar</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-tarea-guardar" onclick="guardarTarea()"><i class="bi bi-check-lg me-1"></i>Guardar</button>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════════ -->
<!--  MODAL: OBLIGACIÓN (Crear / Editar)                            -->
<!-- ════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalOblig" tabindex="-1" aria-labelledby="modalObligLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalObligLabel"><i class="bi bi-journal-text me-1"></i><span id="oblig-modal-titulo">Nueva Obligación</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="oblig-id" value="">
                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label-sm">Nombre <span class="text-danger">*</span></label>
                        <input type="text" id="oblig-nombre" class="form-control form-control-sm" placeholder="Ej: Declaración de IVA Mensual">
                    </div>
                    <div class="col-12">
                        <label class="form-label-sm">Descripción</label>
                        <input type="text" id="oblig-descripcion" class="form-control form-control-sm" placeholder="Detalle opcional">
                    </div>
                    <div class="col-6">
                        <label class="form-label-sm">Estado</label>
                        <select id="oblig-status" class="form-select form-select-sm">
                            <option value="1">Activa</option>
                            <option value="0">Inactiva</option>
                        </select>
                    </div>
                </div>
                <div id="oblig-error" class="alert alert-danger mt-2 d-none py-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger btn-sm me-auto d-none" id="btn-oblig-delete" onclick="eliminarObligacion()"><i class="bi bi-trash me-1"></i>Eliminar</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-oblig-guardar" onclick="guardarObligacion()"><i class="bi bi-check-lg me-1"></i>Guardar</button>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════════ -->
<!--  JAVASCRIPT                                                     -->
<!-- ════════════════════════════════════════════════════════════════ -->
<script>
    'use strict';
    var BASE = '<?= $base ?>';

    // Captura de errores global para debugging
    window.onerror = function(msg, url, line, col, error) {
        var errorMsg = "Error: " + msg + " en " + url + ":" + line;
        console.error(errorMsg);
        if (typeof mostrarToast === 'function') {
            mostrarToast('Error detectado: ' + msg, 'danger');
        }
        return false;
    };

    // ── Helpers ──
    function escH(s) {
        if (!s) return '';
        return s.toString()
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function cerrarAC(id) {
        var el = document.getElementById(id);
        if (el) {
            el.style.display = 'none';
            el.innerHTML = '';
        }
    }

    function encEscJson(obj) {
        return encodeURIComponent(JSON.stringify(obj));
    }

    // ── Estado global ──
    var tareaSort = {
        col: 'fecha_tarea',
        dir: 'ASC'
    };
    var obligSort = {
        col: 'nombre',
        dir: 'ASC'
    };
    var tareasPage = 1,
        obligPage = 1;
    var responsablesSeleccionados = []; // [{id, nombre, mail, tipo}]
    var correosSeleccionados = []; // [string]
    var adjuntosIdTarea = 0;
    var modalTareaBS = null,
        modalObligBS = null;
    var tabActual = '<?= $tabActiva ?>';

    // ── Bootstrap modal refs ─────────────────────────────────────────
    window.addEventListener('DOMContentLoaded', function() {
        try {
            var modalT = document.getElementById('modalTarea');
            var modalO = document.getElementById('modalOblig');
            if (modalT) modalTareaBS = new bootstrap.Modal(modalT);
            if (modalO) modalObligBS = new bootstrap.Modal(modalO);

            if (tabActual === 'tareas') {
                buscarTareas(1);
            } else {
                buscarOblig(1);
            }
        } catch (e) {
            console.error('Error init modals:', e);
        }
    });

    // ── Cambiar pestaña ─────────────────────────────────────────────
    window.cambiarTab = function(tab, e) {
        if (e) e.preventDefault();
        tabActual = tab;
        document.querySelectorAll('.tab-panel').forEach(function(p) {
            p.classList.add('d-none');
        });
        document.getElementById('panel-' + tab).classList.remove('d-none');
        document.querySelectorAll('.nav-tabs-cmg .nav-link').forEach(function(l) {
            l.classList.remove('active');
        });
        document.getElementById('tab-' + (tab === 'tareas' ? 'tareas' : 'oblig') + '-link').classList.add('active');
        if (tab === 'tareas') buscarTareas(1);
        else buscarOblig(1);
        history.replaceState(null, '', BASE + '/config/tareas-obligaciones' + (tab !== 'tareas' ? '?tab=' + tab : ''));
    };

    // ══════════════════════════════════════════════════════════════════
    //  OBLIGACIONES
    // ══════════════════════════════════════════════════════════════════
    var debounceOblig;
    window.buscarObligDebounce = function() {
        clearTimeout(debounceOblig);
        debounceOblig = setTimeout(function() {
            buscarOblig(1);
        }, 320);
    };
    window.buscarOblig = function(page) {
        obligPage = page || 1;
        var b = document.getElementById('buscar-oblig').value;
        var params = new URLSearchParams({
            b: b,
            page: obligPage,
            sort: obligSort.col,
            dir: obligSort.dir
        });
        fetch(BASE + '/config/tareas-obligaciones?action=obligaciones-search-ajax&' + params.toString())
            .then(function(r) {
                return r.json();
            })
            .then(function(d) {
                if (!d.ok) return;
                document.getElementById('tbody-oblig').innerHTML = d.rows;
                document.getElementById('info-oblig').textContent = d.info;
                document.getElementById('pagination-oblig').innerHTML = d.pagination;
            });
    };
    window.cambiarPaginaOblig = function(p) {
        if (p >= 1) buscarOblig(p);
    };
    window.sortOblig = function(col) {
        if (obligSort.col === col) obligSort.dir = obligSort.dir === 'ASC' ? 'DESC' : 'ASC';
        else {
            obligSort.col = col;
            obligSort.dir = 'ASC';
        }
        actualizarIconosSort('oblig', obligSort);
        buscarOblig(1);
    };

    function actualizarIconosSort(prefijo, sortObj) {
        // Resetear todos los iconos del prefijo (ej: sort-icon-tareas-...)
        document.querySelectorAll('[id^="sort-icon-' + prefijo + '-"]').forEach(function(i) {
            i.className = 'bi bi-arrow-down-up text-muted';
        });

        var icon = document.getElementById('sort-icon-' + prefijo + '-' + sortObj.col);
        if (icon) {
            if (sortObj.dir === 'ASC') {
                icon.className = 'bi bi-sort-alpha-down text-primary';
            } else {
                icon.className = 'bi bi-sort-alpha-up text-primary';
            }
        }
    }

    window.abrirModalObligNueva = function() {
        document.getElementById('oblig-id').value = '';
        document.getElementById('oblig-nombre').value = '';
        document.getElementById('oblig-descripcion').value = '';
        document.getElementById('oblig-status').value = '1';
        document.getElementById('oblig-error').classList.add('d-none');
        document.getElementById('oblig-modal-titulo').textContent = 'Nueva Obligación';
        document.getElementById('btn-oblig-delete').classList.add('d-none');
        modalObligBS.show();
    };

    window.abrirModalObligEdit = function(tr) {
        var r = JSON.parse(tr.getAttribute('data-row'));
        document.getElementById('oblig-id').value = r.id;
        document.getElementById('oblig-nombre').value = r.nombre;
        document.getElementById('oblig-descripcion').value = r.descripcion || '';
        document.getElementById('oblig-status').value = String(r.status);
        document.getElementById('oblig-error').classList.add('d-none');
        document.getElementById('oblig-modal-titulo').textContent = 'Editar Obligación';
        document.getElementById('btn-oblig-delete').classList.remove('d-none');
        modalObligBS.show();
    };

    window.guardarObligacion = function() {
        var id = document.getElementById('oblig-id').value;
        var btn = document.getElementById('btn-oblig-guardar');
        var errEl = document.getElementById('oblig-error');
        errEl.classList.add('d-none');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando…';

        var action = id ? 'obligaciones-update' : 'obligaciones-store';
        var fd = new FormData();
        if (id) fd.append('id', id);
        fd.append('nombre', document.getElementById('oblig-nombre').value);
        fd.append('descripcion', document.getElementById('oblig-descripcion').value);
        fd.append('status', document.getElementById('oblig-status').value);

        fetch(BASE + '/config/tareas-obligaciones?action=' + action, {
                method: 'POST',
                body: fd
            })
            .then(function(r) {
                return r.json();
            })
            .then(function(d) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Guardar';
                if (d.ok) {
                    modalObligBS.hide();
                    buscarOblig(obligPage);
                    actualizarSelectorObligaciones();
                    if (typeof window.updateTareasBadge === 'function') window.updateTareasBadge();
                } else {
                    errEl.textContent = d.error || 'Error al guardar.';
                    errEl.classList.remove('d-none');
                }
            });
    };

    window.eliminarObligacion = function() {
        if (!confirm('¿Eliminar esta obligación?')) return;
        var id = document.getElementById('oblig-id').value;
        var fd = new FormData();
        fd.append('id', id);
        fetch(BASE + '/config/tareas-obligaciones?action=obligaciones-delete', {
                method: 'POST',
                body: fd
            })
            .then(function(r) {
                return r.json();
            })
            .then(function(d) {
                if (d.ok) {
                    modalObligBS.hide();
                    buscarOblig(1);
                    if (typeof window.updateTareasBadge === 'function') window.updateTareasBadge();
                } else {
                    document.getElementById('oblig-error').textContent = d.error;
                    document.getElementById('oblig-error').classList.remove('d-none');
                }
            });
    };

    // Abrir modal obligación desde dentro del modal tarea
    window.abrirModalObligNuevaDesdeModal = function() {
        document.getElementById('oblig-id').value = '';
        document.getElementById('oblig-nombre').value = '';
        document.getElementById('oblig-descripcion').value = '';
        document.getElementById('oblig-status').value = '1';
        document.getElementById('oblig-error').classList.add('d-none');
        document.getElementById('oblig-modal-titulo').textContent = 'Nueva Obligación';
        document.getElementById('btn-oblig-delete').classList.add('d-none');
        // Guardar callback para actualizar select
        window._obligDesdeModalTarea = true;
        modalObligBS.show();
    };

    function actualizarSelectorObligaciones() {
        fetch(BASE + '/config/tareas-obligaciones?action=obligaciones-search-ajax&b=&page=1&sort=nombre&dir=ASC&per_page=999')
            .then(function(r) {
                return r.json();
            });
        // Recargar la página silenciosamente no es ideal; hacemos fetch directo al catálogo
        // En su lugar, solo actualizamos si el modal tarea está abierto
        // Hacemos un reload del select desde el endpoint de todas activas
        fetch(BASE + '/config/tareas-obligaciones?action=obligaciones-search-ajax&b=&page=1&sort=nombre&dir=ASC')
            .then(function(r) {
                return r.json();
            })
            .then(function(d) {
                // Para refrescar el select de obligaciones en el modal tarea,
                // recargamos desde la vista (sencillo: reload de ajax)
            });
    }

    // ══════════════════════════════════════════════════════════════════
    //  TAREAS
    // ══════════════════════════════════════════════════════════════════
    var debounceT;
    window.buscarTareasDebounce = function() {
        clearTimeout(debounceT);
        debounceT = setTimeout(function() {
            buscarTareas(1);
        }, 320);
    };
    window.buscarTareas = function(page) {
        tareasPage = page || 1;
        var b = document.getElementById('buscar-tareas').value;
        var arch = document.getElementById('toggle-archivadas').checked ? 1 : 0;

        // Filtros adicionales
        var estado = document.getElementById('filtro-estado').value;
        var oblig = document.getElementById('filtro-obligacion').value;
        var resp = document.getElementById('filtro-responsable').value;
        var desde = document.getElementById('filtro-desde').value;
        var hasta = document.getElementById('filtro-hasta').value;

        var params = new URLSearchParams({
            b: b,
            page: tareasPage,
            sort: tareaSort.col,
            dir: tareaSort.dir,
            archivadas: arch,
            estado: estado,
            obligacion: oblig,
            responsable: resp,
            desde: desde,
            hasta: hasta
        });

        document.getElementById('tbody-tareas').innerHTML = '<tr><td colspan="7" class="text-center py-4"><div class="spinner-border spinner-border-sm me-2"></div>Cargando…</td></tr>';
        fetch(BASE + '/config/tareas-obligaciones?action=tareas-search-ajax&' + params.toString())
            .then(function(r) {
                return r.json();
            })
            .then(function(d) {
                if (!d.ok) return;
                document.getElementById('tbody-tareas').innerHTML = d.rows;
                document.getElementById('info-tareas').textContent = d.info;
                document.getElementById('pagination-tareas').innerHTML = d.pagination;
            });
    };

    window.limpiarFiltros = function() {
        document.getElementById('buscar-tareas').value = '';
        document.getElementById('filtro-estado').value = '';
        document.getElementById('filtro-obligacion').value = '';
        document.getElementById('filtro-responsable').value = '';
        document.getElementById('filtro-desde').value = '';
        document.getElementById('filtro-hasta').value = '';
        document.getElementById('toggle-archivadas').checked = false;
        buscarTareas(1);
    };
    window.cambiarPaginaTarea = function(p) {
        if (p >= 1) buscarTareas(p);
    };
    window.sortTareas = function(col) {
        if (tareaSort.col === col) tareaSort.dir = tareaSort.dir === 'ASC' ? 'DESC' : 'ASC';
        else {
            tareaSort.col = col;
            tareaSort.dir = 'ASC';
        }
        actualizarIconosSort('tareas', tareaSort);
        buscarTareas(1);
    };

    // ── Modal Tarea ─────────────────────────────────────────────────
    function resetModalTarea() {
        document.getElementById('tarea-id').value = '';
        document.getElementById('tarea-id-origen').value = '';
        document.getElementById('tarea-cliente-buscar').value = '';
        document.getElementById('tarea-id-cliente').value = '';
        document.getElementById('tarea-correos-wrap').classList.add('d-none');
        document.getElementById('tarea-correos-list').innerHTML = '';
        document.getElementById('tarea-obligacion').value = '';
        document.getElementById('tarea-periodicidad').value = '';
        document.getElementById('tarea-fecha').value = '';
        document.getElementById('tarea-estado').value = 'por_realizar';
        document.getElementById('tarea-notas').value = '';
        document.getElementById('tarea-resumen').value = '';
        document.getElementById('tarea-motivo').value = '';
        document.getElementById('tarea-error').classList.add('d-none');
        document.getElementById('bloque-realizada').classList.add('d-none');
        document.getElementById('bloque-cancelada').classList.add('d-none');
        document.getElementById('tarea-origen-badge').classList.add('d-none');
        document.getElementById('adjuntos-lista').innerHTML = '';
        // Cliente
        // Responsables extras
        // Reset correos
        correosSeleccionados = [];
        renderTagsCorreos();
        document.getElementById('correo-input').value = '';
        document.getElementById('tarea-correos-wrap').classList.add('d-none');
        document.getElementById('tarea-correos-list').innerHTML = '';
        responsablesSeleccionados = [];
        renderTagsResponsables();
        adjuntosIdTarea = 0;
        if (document.getElementById('btn-tarea-delete')) document.getElementById('btn-tarea-delete').classList.add('d-none');
        if (document.getElementById('upload-section')) document.getElementById('upload-section').classList.remove('d-none');
    }

    window.abrirModalTareaNueva = function() {
        resetModalTarea();
        document.getElementById('tarea-modal-titulo').textContent = 'Nueva Tarea';
        modalTareaBS.show();
    };

    window.abrirModalTareaEditar = function(tr) {
        resetModalTarea();
        var r = JSON.parse(tr.getAttribute('data-row'));
        document.getElementById('tarea-modal-titulo').textContent = 'Editar Tarea';
        document.getElementById('tarea-id').value = r.id;
        document.getElementById('tarea-id-origen').value = r.id_tarea_origen || '';
        document.getElementById('tarea-cliente-buscar').value = r.cliente_nombre;
        document.getElementById('tarea-id-cliente').value = r.id_cliente || '';
        document.getElementById('tarea-obligacion').value = r.id_obligacion || '';
        // Correos iniciales desde r (para carga inmediata)
        correosSeleccionados = (r.cliente_correo || '').split(',').map(function(e) {
            return e.trim();
        }).filter(Boolean);
        renderTagsCorreos();

        document.getElementById('tarea-periodicidad').value = r.periodicidad;
        document.getElementById('tarea-fecha').value = r.fecha_tarea;
        document.getElementById('tarea-estado').value = r.estado;
        document.getElementById('tarea-notas').value = r.notas || '';
        document.getElementById('btn-tarea-delete').classList.remove('d-none');

        if (r.id_tarea_origen) {
            document.getElementById('tarea-origen-badge').classList.remove('d-none');
        }

        onEstadoCambio();

        // Cargar detalle completo (responsables + adjuntos + resumen)
        fetch(BASE + '/config/tareas-obligaciones?action=tareas-get-detalle&id=' + r.id)
            .then(function(res) {
                return res.json();
            })
            .then(function(d) {
                if (!d.ok) return;
                var t = d.data;
                document.getElementById('tarea-resumen').value = t.resumen || '';
                document.getElementById('tarea-motivo').value = t.motivo_cancelacion || '';

                // Correos (guardados como string separado por comas)
                correosSeleccionados = (t.cliente_correo || '').split(',').map(function(e) {
                    return e.trim();
                }).filter(Boolean);
                renderTagsCorreos();

                // Responsables
                responsablesSeleccionados = (t.responsables || []).map(function(u) {
                    return {
                        id: u.id_usuario || u.id_resp_tarea,
                        nombre: u.nombre,
                        mail: u.mail || u.correo_cache || '',
                        tipo: u.id_usuario ? 'usuario' : 'propio'
                    };
                });
                renderTagsResponsables();

                // Adjuntos
                adjuntosIdTarea = t.id;
                renderAdjuntos(t.adjuntos || []);
            });

        modalTareaBS.show();
    };

    window.onEstadoCambio = function() {
        var estado = document.getElementById('tarea-estado').value;
        var blReal = document.getElementById('bloque-realizada');
        var blCan = document.getElementById('bloque-cancelada');
        blReal.classList.toggle('d-none', !(estado === 'realizada_continua' || estado === 'realizada_finalizada'));
        blCan.classList.toggle('d-none', estado !== 'cancelada');
    };

    window.onTareaFechaCambio = function() {
        var fechaVal = document.getElementById('tarea-fecha').value;
        if (!fechaVal) return;

        var estadoEl = document.getElementById('tarea-estado');
        var estadoActual = estadoEl.value;

        // Solo actualizamos automáticamente si está en un estado "pendiente"
        if (estadoActual === 'por_realizar' || estadoActual === 'vencida') {
            var now = new Date();
            var hoy = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');

            if (fechaVal < hoy) {
                estadoEl.value = 'vencida';
            } else {
                estadoEl.value = 'por_realizar';
            }
            onEstadoCambio(); // Por si acaso afecta a bloques visibles
        }
    };

    window.guardarTarea = function() {
        var id = document.getElementById('tarea-id').value;
        var btn = document.getElementById('btn-tarea-guardar');
        var errEl = document.getElementById('tarea-error');
        errEl.classList.add('d-none');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando…';

        var action = id ? 'tareas-update' : 'tareas-store';
        var fd = new FormData();
        if (id) fd.append('id', id);
        fd.append('id_obligacion', document.getElementById('tarea-obligacion').value);
        fd.append('id_cliente', document.getElementById('tarea-id-cliente').value);
        fd.append('cliente_nombre', document.getElementById('tarea-cliente-buscar').value);
        fd.append('cliente_correo', correosSeleccionados.join(', '));
        fd.append('periodicidad', document.getElementById('tarea-periodicidad').value);
        fd.append('fecha_tarea', document.getElementById('tarea-fecha').value);
        fd.append('estado', document.getElementById('tarea-estado').value);
        fd.append('notas', document.getElementById('tarea-notas').value);
        fd.append('resumen', document.getElementById('tarea-resumen').value);
        fd.append('motivo_cancelacion', document.getElementById('tarea-motivo').value);
        fd.append('id_tarea_origen', document.getElementById('tarea-id-origen').value);
        fd.append('responsables', JSON.stringify(responsablesSeleccionados.map(function(u) {
            return {
                id: u.id,
                nombre: u.nombre,
                mail: u.mail || '',
                tipo: u.tipo || 'usuario'
            };
        })));

        fetch(BASE + '/config/tareas-obligaciones?action=' + action, {
                method: 'POST',
                body: fd
            })
            .then(function(r) {
                return r.json();
            })
            .then(function(d) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Guardar';
                if (d.ok) {
                    modalTareaBS.hide();
                    buscarTareas(tareasPage);
                    if (d.id) {
                        adjuntosIdTarea = d.id;
                    }
                    if (document.getElementById('tarea-estado').value === 'realizada_continua') {
                        mostrarToast('Se creó automáticamente la próxima tarea recurrente.', 'info');
                    }
                    if (typeof window.updateTareasBadge === 'function') window.updateTareasBadge();
                } else {
                    errEl.textContent = d.error || 'Error al guardar.';
                    errEl.classList.remove('d-none');
                }
            });
    };

    window.eliminarTarea = function() {
        if (!confirm('¿Eliminar esta tarea? Esta acción no se puede deshacer.')) return;
        var id = document.getElementById('tarea-id').value;
        var fd = new FormData();
        fd.append('id', id);
        fetch(BASE + '/config/tareas-obligaciones?action=tareas-delete', {
                method: 'POST',
                body: fd
            })
            .then(function(r) {
                return r.json();
            })
            .then(function(d) {
                if (d.ok) {
                    modalTareaBS.hide();
                    buscarTareas(tareasPage);
                    if (typeof window.updateTareasBadge === 'function') window.updateTareasBadge();
                } else {
                    document.getElementById('tarea-error').textContent = d.error;
                    document.getElementById('tarea-error').classList.remove('d-none');
                }
            });
    };

    // ── Seleccionar cliente ───────────────────────────────────────────
    function seleccionarCliente(data) {
        var c = (typeof data === 'string') ? JSON.parse(decodeURIComponent(data)) : data;
        cerrarAC('tarea-cliente-ac');
        if (!c) return;
        document.getElementById('tarea-cliente-buscar').value = c.nombre;
        document.getElementById('tarea-id-cliente').value = c.id || '';
        if (c.correo) {
            agregarCorreo(c.correo);
        }
        document.getElementById('correo-input').placeholder = 'Agregar otro correo...';
        if (c.origen === 'propio' || !c.origen) {
            cargarCorreosClienteTarea(c.nombre);
        }
    }

    window.limpiarCliente = function() {
        document.getElementById('tarea-cliente-buscar').value = '';
        document.getElementById('tarea-id-cliente').value = '';
        correosSeleccionados = [];
        renderTagsCorreos();
        document.getElementById('tarea-correos-wrap').classList.add('d-none');
        document.getElementById('correo-input').placeholder = 'correo@ejemplo.com';
    };

    // ── Autocomplete: busca en clientes_tareas + clientes empresa ────
    var debCliente;
    window.buscarClienteAC = function(q) {
        clearTimeout(debCliente);
        // Si borra o cambia, limpiamos el ID para usar el nombre manual
        document.getElementById('tarea-id-cliente').value = '';

        if (q.length < 2) {
            cerrarAC('tarea-cliente-ac');
            return;
        }
        debCliente = setTimeout(function() {
            fetch(BASE + '/config/tareas-obligaciones?action=buscar-clientes&q=' + encodeURIComponent(q))
                .then(function(r) {
                    return r.json();
                })
                .then(function(d) {
                    var list = document.getElementById('tarea-cliente-ac');
                    if (!d.ok) {
                        list.style.display = 'none';
                        return;
                    }
                    var html = '';
                    // Clientes propios (clientes_tareas)
                    if (d.propios && d.propios.length > 0) {
                        html += '<div class="ac-item fw-bold text-muted small" style="cursor:default;pointer-events:none">Mis clientes</div>';
                        d.propios.forEach(function(c) {
                            html += '<div class="ac-item d-flex align-items-center" data-json=\'' + encEscJson(c) + '\'>' +
                                '<i class="bi bi-person-badge text-warning me-2 fs-6"></i>' +
                                '<div class="d-flex flex-column" style="line-height:1.2">' +
                                '<span class="fw-medium">' + escH(c.nombre) + '</span>' +
                                '<small class="text-muted" style="font-size:0.75rem">' + (c.ruc ? escH(c.ruc) + ' &bull; ' : '') + escH(c.correo || 'Sin correo') + '</small>' +
                                '</div></div>';
                        });
                    }
                    // De la tabla de empresas
                    if (d.empresa && d.empresa.length > 0) {
                        html += '<div class="ac-item fw-bold text-muted small" style="cursor:default;pointer-events:none;border-top:1px solid #f0f0f0">Clientes del sistema</div>';
                        d.empresa.forEach(function(c) {
                            html += '<div class="ac-item d-flex align-items-center" data-json=\'' + encEscJson(c) + '\'>' +
                                '<i class="bi bi-building text-primary me-2 fs-6"></i>' +
                                '<div class="d-flex flex-column" style="line-height:1.2">' +
                                '<span class="fw-medium">' + escH(c.nombre) + '</span>' +
                                '<small class="text-muted" style="font-size:0.75rem">' + (c.ruc ? escH(c.ruc) + ' &bull; ' : '') + escH(c.correo || 'Sin correo') + '</small>' +
                                '</div></div>';
                        });
                    }
                    if (!html) {
                        list.style.display = 'none';
                        list.innerHTML = '';
                        return;
                    }
                    list.innerHTML = html;
                    list.style.display = 'block';
                    list.querySelectorAll('.ac-item[data-json]').forEach(function(el) {
                        el.addEventListener('click', function() {
                            seleccionarCliente(el.getAttribute('data-json'));
                        });
                    });
                });
        }, 280);
    };

    function cargarCorreosClienteTarea(nombre) {
        fetch(BASE + '/config/tareas-obligaciones?action=correos-cliente&nombre=' + encodeURIComponent(nombre))
            .then(function(r) {
                return r.json();
            })
            .then(function(d) {
                var wrap = document.getElementById('tarea-correos-wrap');
                var list = document.getElementById('tarea-correos-list');
                if (!d.ok || !d.data || d.data.length === 0) {
                    wrap.classList.add('d-none');
                    return;
                }
                var html = '';
                d.data.forEach(function(c) {
                    var activo = correosSeleccionados.includes((c.correo || '').toLowerCase());
                    html += '<button type="button" class="btn btn-sm py-1 px-2 ' + (activo ? 'btn-secondary' : 'btn-outline-secondary') + '" onclick="selCorreo(\'' + escH(c.correo) + '\')">' +
                        '<i class="bi bi-envelope me-1 small"></i>' + escH(c.correo) + '</button>';
                });
                list.innerHTML = html;
                wrap.classList.remove('d-none');
            });
    }

    window.selCorreo = function(correo) {
        agregarCorreo(correo);
    };

    // ── Multi-tag correos ────────────────────────────────────
    function agregarCorreo(email) {
        email = email.trim().toLowerCase();
        if (!email) return;
        // Validación básica
        if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) {
            mostrarToast('Correo no válido: ' + email, 'warning');
            return;
        }
        if (correosSeleccionados.includes(email)) return;
        correosSeleccionados.push(email);
        renderTagsCorreos();
    }

    function renderTagsCorreos() {
        var wrap = document.getElementById('tags-correos-cliente');
        var inp = document.getElementById('correo-input');
        wrap.querySelectorAll('.tag-item').forEach(function(t) {
            t.remove();
        });
        correosSeleccionados.forEach(function(email) {
            var tag = document.createElement('span');
            tag.className = 'tag-item';
            tag.innerHTML = '<i class="bi bi-envelope me-1 small"></i>' + escH(email) +
                ' <span class="tag-remove" data-email="' + escH(email) + '">✕</span>';
            tag.querySelector('.tag-remove').addEventListener('click', function() {
                var e = this.getAttribute('data-email');
                correosSeleccionados = correosSeleccionados.filter(function(x) {
                    return x !== e;
                });
                renderTagsCorreos();
            });
            wrap.insertBefore(tag, inp);
        });
    }

    window.onCorreoKeydown = function(e) {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            commitCorreo();
        } else if (e.key === 'Backspace' && e.target.value === '' && correosSeleccionados.length > 0) {
            correosSeleccionados.pop();
            renderTagsCorreos();
        }
    };

    window.commitCorreo = function() {
        var val = document.getElementById('correo-input').value.trim().replace(/,$/, '');
        if (val) {
            agregarCorreo(val);
        }
        document.getElementById('correo-input').value = '';
    };


    // ── Autocomplete Responsables ────────────────────────────────────
    var debResp;
    window.buscarRespAC = function(q) {
        clearTimeout(debResp);
        if (q.length < 2) {
            cerrarAC('resp-ac');
            return;
        }
        debResp = setTimeout(function() {
            fetch(BASE + '/config/tareas-obligaciones?action=buscar-usuarios&q=' + encodeURIComponent(q))
                .then(function(r) {
                    return r.json();
                })
                .then(function(d) {
                    var list = document.getElementById('resp-ac');
                    if (!d.ok) {
                        list.style.display = 'none';
                        return;
                    }
                    var html = '';
                    if (d.sistema && d.sistema.length > 0) {
                        html += '<div class="ac-item fw-bold text-muted small" style="cursor:default;pointer-events:none">Usuarios del sistema</div>';
                        d.sistema.forEach(function(u) {
                            var key = 'usuario_' + u.id;
                            if (!responsablesSeleccionados.some(function(s) {
                                    return (s.tipo || 'usuario') + '_' + s.id === key;
                                })) {
                                html += '<div class="ac-item" data-json=\'' + encEscJson(u) + '\'>' +
                                    '<i class="bi bi-person-fill text-primary me-1 small"></i><span class="fw-medium">' + escH(u.nombre) + '</span>' +
                                    (u.mail ? '<br><small class="text-muted">' + escH(u.mail) + '</small>' : '') + '</div>';
                            }
                        });
                    }
                    if (d.propios && d.propios.length > 0) {
                        html += '<div class="ac-item fw-bold text-muted small" style="cursor:default;pointer-events:none;border-top:1px solid #eee">Responsables externos</div>';
                        d.propios.forEach(function(u) {
                            var key = 'propio_' + u.id;
                            if (!responsablesSeleccionados.some(function(s) {
                                    return (s.tipo || 'usuario') + '_' + s.id === key;
                                })) {
                                html += '<div class="ac-item" data-json=\'' + encEscJson({
                                        id: u.id,
                                        nombre: u.nombre,
                                        mail: u.mail || '',
                                        tipo: 'propio',
                                        cedula: u.cedula || ''
                                    }) + '\'>' +
                                    '<i class="bi bi-person-badge text-warning me-1 small"></i><span class="fw-medium">' + escH(u.nombre) + '</span>' +
                                    (u.cedula ? '<small class="text-muted ms-2">' + escH(u.cedula) + '</small>' : '') + '</div>';
                            }
                        });
                    }
                    if (!html) {
                        list.style.display = 'none';
                        list.innerHTML = '';
                        return;
                    }
                    list.innerHTML = html;
                    list.style.display = 'block';
                    list.querySelectorAll('.ac-item[data-json]').forEach(function(el) {
                        el.addEventListener('click', function() {
                            agregarResponsable(el.getAttribute('data-json'));
                            document.getElementById('resp-input').value = '';
                        });
                    });
                });
        }, 280);
    };


    window.commitResponsableManual = function() {
        var nInp = document.getElementById('resp-input');
        var mInp = document.getElementById('resp-mail-input');
        var name = nInp.value.trim();
        var mail = mInp.value.trim();

        if (!name) {
            nInp.focus();
            return;
        }
        if (!mail) {
            mostrarToast('El correo del responsable es obligatorio.', 'warning');
            mInp.focus();
            return;
        }
        // Validación básica de correo
        if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(mail)) {
            mostrarToast('El correo ingresado no es válido.', 'warning');
            mInp.focus();
            return;
        }

        agregarResponsable({
            id: '',
            nombre: name,
            mail: mail,
            tipo: 'propio'
        });

        nInp.value = '';
        mInp.value = '';
        nInp.focus();
    };
    window.onRespKeydown = function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            commitResponsableManual();
        }
    };

    function agregarResponsable(data) {
        var u = (typeof data === 'string') ? JSON.parse(decodeURIComponent(data)) : data;
        cerrarAC('resp-ac');
        if (!u || !u.nombre) return;

        if (!u.mail) {
            mostrarToast('El responsable seleccionado no tiene correo. Por favor ingréselo manualmente.', 'warning');
            document.getElementById('resp-input').value = u.nombre;
            document.getElementById('resp-mail-input').focus();
            return;
        }

        // Comprobación de duplicados mejorada (por ID+Tipo o por Correo)
        var yaExiste = responsablesSeleccionados.some(function(s) {
            if (u.id && s.id && u.id == s.id && u.tipo == s.tipo) return true;
            if (u.mail && s.mail && u.mail.toLowerCase() === s.mail.toLowerCase()) return true;
            return false;
        });
        if (yaExiste) {
            mostrarToast('Este responsable ya ha sido agregado.', 'info');
            return;
        }

        responsablesSeleccionados.push(u);
        renderTagsResponsables();
    }

    function renderTagsResponsables() {
        var wrap = document.getElementById('tags-responsables');
        wrap.innerHTML = '';
        responsablesSeleccionados.forEach(function(u, idx) {
            var tag = document.createElement('span');
            tag.className = 'tag-item d-flex align-items-center shadow-sm border border-secondary border-opacity-10';
            var icono = (u.tipo === 'propio' || !u.id) ? '<i class="bi bi-person-badge text-warning me-1 small"></i>' : '<i class="bi bi-person-fill text-primary me-1 small"></i>';

            tag.innerHTML = icono + '<div class="d-flex flex-column" style="line-height:1">' +
                '<span class="small fw-medium">' + escH(u.nombre) + '</span>' +
                '<small class="extra-small text-muted" style="font-size:0.65rem">' + escH(u.mail || u.correo_cache || 'Sin correo') + '</small>' +
                '</div>' +
                ' <span class="tag-remove ms-2 text-danger" style="cursor:pointer" data-idx="' + idx + '">✕</span>';

            tag.querySelector('.tag-remove').addEventListener('click', function() {
                var i = parseInt(this.getAttribute('data-idx'));
                responsablesSeleccionados.splice(i, 1);
                renderTagsResponsables();
            });
            wrap.appendChild(tag);
        });
    }

    window.updateRespMail = function(el) {
        var idx = parseInt(el.getAttribute('data-idx'));
        if (responsablesSeleccionados[idx]) {
            responsablesSeleccionados[idx].mail = el.value.trim();
            responsablesSeleccionados[idx].correo_cache = el.value.trim();
        }
    };

    // ── Adjuntos ────────────────────────────────────────────────────
    function renderAdjuntos(adjuntos) {
        var lista = document.getElementById('adjuntos-lista');
        lista.innerHTML = '';
        adjuntos.forEach(function(a) {
            if (a.eliminado) return;
            var item = document.createElement('div');
            item.className = 'adjunto-item';
            item.id = 'adjunto-' + a.id;
            var ext = (a.nombre_archivo || '').split('.').pop().toLowerCase();
            var icono = ext === 'pdf' ? 'bi-file-earmark-pdf text-danger' : ['doc', 'docx'].includes(ext) ? 'bi-file-earmark-word text-primary' : ['xls', 'xlsx'].includes(ext) ? 'bi-file-earmark-excel text-success' : ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext) ? 'bi-file-earmark-image text-info' :
                'bi-file-earmark';
            item.innerHTML = '<i class="bi ' + icono + ' fs-5"></i>' +
                '<span class="adj-name" title="' + escH(a.nombre_archivo) + '">' + escH(a.nombre_archivo) + '</span>' +
                '<button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="borrarAdjunto(' + a.id + ')">' +
                '<i class="bi bi-trash"></i></button>';
            lista.appendChild(item);
        });
    }

    window.subirAdjunto = function(input) {
        var id = adjuntosIdTarea || document.getElementById('tarea-id').value;
        if (!id) {
            mostrarToast('Primero guarda la tarea, luego sube los adjuntos.', 'warning');
            input.value = '';
            return;
        }
        var file = input.files[0];
        if (!file) return;
        var fd = new FormData();
        fd.append('id_tarea', id);
        fd.append('adjunto', file);
        fetch(BASE + '/config/tareas-obligaciones?action=tareas-upload-adjunto', {
                method: 'POST',
                body: fd
            })
            .then(function(r) {
                return r.json();
            })
            .then(function(d) {
                if (d.ok) {
                    var lista = document.getElementById('adjuntos-lista');
                    var item = document.createElement('div');
                    item.className = 'adjunto-item';
                    item.id = 'adjunto-' + d.id;
                    item.innerHTML = '<i class="bi bi-file-earmark fs-5"></i>' +
                        '<span class="adj-name">' + escH(d.nombre) + '</span>' +
                        '<button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="borrarAdjunto(' + d.id + ')">' +
                        '<i class="bi bi-trash"></i></button>';
                    lista.appendChild(item);
                    mostrarToast('Archivo adjuntado correctamente.', 'success');
                } else {
                    mostrarToast(d.error || 'Error al subir.', 'danger');
                }
                input.value = '';
            });
    };

    window.borrarAdjunto = function(idAdj) {
        if (!confirm('¿Eliminar este adjunto?')) return;
        var fd = new FormData();
        fd.append('id_adjunto', idAdj);
        fetch(BASE + '/config/tareas-obligaciones?action=tareas-delete-adjunto', {
                method: 'POST',
                body: fd
            })
            .then(function(r) {
                return r.json();
            })
            .then(function(d) {
                if (d.ok) {
                    var el = document.getElementById('adjunto-' + idAdj);
                    if (el) el.remove();
                } else {
                    mostrarToast(d.error, 'danger');
                }
            });
    };

    // ── Utilidades ──────────────────────────────────────────────────
    // (helpers at top)

    // Cerrar autocomplete al clickear fuera
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.autocomplete-wrap')) {
            cerrarAC('tarea-cliente-ac');
            cerrarAC('resp-ac');
        }
    });

    function actualizarIconosSort(prefijo, sort) {
        document.querySelectorAll('[id^="sort-icon-' + prefijo + '-"]').forEach(function(el) {
            el.className = 'bi bi-arrow-down-up text-muted';
        });
        var icon = document.getElementById('sort-icon-' + prefijo + '-' + sort.col);
        if (icon) {
            icon.className = sort.dir === 'ASC' ? 'bi bi-sort-alpha-down text-primary' : 'bi bi-sort-alpha-up text-primary';
        }
    }

    function mostrarToast(msg, tipo) {
        var container = document.getElementById('toast-container-global');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container-global';
            container.className = 'position-fixed bottom-0 end-0 p-3';
            container.style.zIndex = '9999';
            document.body.appendChild(container);
        }
        var t = document.createElement('div');
        t.className = 'toast align-items-center text-white bg-' + (tipo || 'primary') + ' border-0 show';
        t.setAttribute('role', 'alert');
        t.innerHTML = '<div class="d-flex"><div class="toast-body">' + escH(msg) + '</div>' +
            '<button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="this.closest(\'.toast\').remove()"></button></div>';
        container.appendChild(t);
        setTimeout(function() {
            t.remove();
        }, 4000);
    }
</script>