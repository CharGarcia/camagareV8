<?php

/** @var array  $obligacionesActivas */
/** @var array  $responsablesFiltro */
/** @var int    $idUsuarioActual */
/** @var int    $nivelUsuarioActual */
$base      = BASE_URL;
$tabActiva = in_array($tab, ['tareas', 'obligaciones', 'clientes'], true) ? $tab : 'tareas';
?>
<style>
    /* ── Pestañas (segmented control) ── */
    .nav-tabs-cmg {
        display: inline-flex;
        gap: 2px;
        background: #eef1f6;
        border: 1px solid #e3e7ef;
        border-radius: 10px;
        padding: 3px;
    }

    .nav-tabs-cmg .nav-item {
        display: flex;
    }

    .nav-tabs-cmg .nav-link {
        color: #6b7280;
        font-size: .78rem;
        font-weight: 500;
        padding: 6px 14px;
        border: none;
        border-radius: 7px;
        margin-bottom: 0;
        transition: background-color .15s ease, color .15s ease;
    }

    .nav-tabs-cmg .nav-link:hover:not(.active) {
        color: #374151;
        background: rgba(255, 255, 255, .65);
        border-color: transparent;
    }

    .nav-tabs-cmg .nav-link.active {
        color: var(--bs-primary);
        font-weight: 600;
        background: #fff;
        border-color: transparent;
        box-shadow: 0 1px 3px rgba(0, 0, 0, .1);
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

    /* ── App-shell: propagar el alto por pestañas + marco hasta la tarjeta, para que
       la tabla ocupe TODO el ancho/alto disponible y SOLO ella tenga scroll vertical.
       (Estos overrides solo aplican cuando el app-shell está activo.) ── */
    body.cmg-has-table .tab-panel {
        flex: 1 1 auto;
        min-height: 0;
        display: flex;
        flex-direction: column;
    }

    body.cmg-has-table .cmg-dashboard-frame {
        flex: 1 1 auto;
        min-height: 0;
        display: flex;
        flex-direction: column;
        /* borde a borde: el marco no debe insertar ni recuadrar la tabla */
        border: 0;
        border-radius: 0;
        box-shadow: none;
        background: transparent;
        margin: 0;
        padding: 0;
    }

    /* Los filtros quedan fijos (no se estiran ni scrollean) */
    body.cmg-has-table .tareas-filtros {
        flex-shrink: 0;
    }

    /* La tabla ocupa el alto restante de la tarjeta y es lo único que scrollea */
    body.cmg-has-table .cmg-table-card > .table-scroll {
        flex: 1 1 auto;
        min-height: 0;
    }

    /* Pequeña sangría a los títulos de cada pestaña (la tabla sigue borde a borde) */
    body.cmg-has-table .cmg-dashboard-frame > .d-flex,
    body.cmg-has-table .cmg-dashboard-frame > p {
        padding-left: 0.75rem;
        padding-right: 0.75rem;
    }

    /* Sangría del encabezado (título + botón) y de las pestañas — igual que proveedores.
       Con el app-shell el contenedor va sin padding lateral, por eso lo damos aquí. */
    body.cmg-has-table .tareas-page-header,
    body.cmg-has-table #tabsTareas {
        padding-left: 0.75rem;
        padding-right: 0.75rem;
    }

    .cmg-table-card .table thead th {
        background: #f8f9fa;
        font-weight: 600;
        padding: 6px 8px;
        position: sticky;
        top: 0;
        z-index: 1;
        box-shadow: 0 1px 0 #dee2e6;
        white-space: nowrap;
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

    #tarea-cliente-buscar,
    #tarea-obligacion-buscar {
        border-top-right-radius: 0;
        border-bottom-right-radius: 0;
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

    /* ── Responsables/Tags ──
       Usa las clases form-control + form-control-sm de Bootstrap para heredar el mismo
       alto/padding/borde que los demás campos del modal; aquí solo se agrega el layout flex. */
    .tags-wrap {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 2px;
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
    #modalOblig,
    #modalClienteTarea,
    #modalDuplicarCombo {
        z-index: 1061;
    }

    /* ── Panel flotante de responsable por fila (tabla Duplicar) ── */
    .dup-resp-panel {
        position: fixed;
        width: 320px;
        background: #fff;
        border: 1px solid #ced4da;
        border-radius: 6px;
        box-shadow: 0 4px 16px rgba(0, 0, 0, .15);
        z-index: 5075;
    }

    /* Asegurar que el fondo del segundo modal sea superior */
    .modal-backdrop.show:nth-of-type(even) {
        z-index: 1060;
    }
</style>

<!-- ══════════════════════════════════════════════════════ -->
<!-- Encabezado -->
<!-- ══════════════════════════════════════════════════════ -->
<div class="tareas-page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-list-check"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">Gestión de obligaciones y asignación de tareas.</p>
    </div>
    <div>
        <button class="btn btn-primary btn-sm px-3 enc-tab-btn<?= $tabActiva === 'tareas' ? '' : ' d-none' ?>" data-tab-btn="tareas" id="btn-nueva-tarea" onclick="abrirModalTareaNueva()">
            <i class="bi bi-plus-lg"></i> Nueva tarea
        </button>
        <button class="btn btn-primary btn-sm px-3 enc-tab-btn<?= $tabActiva === 'obligaciones' ? '' : ' d-none' ?>" data-tab-btn="obligaciones" onclick="abrirModalObligNueva()">
            <i class="bi bi-plus-lg"></i> Nueva obligación
        </button>
        <button class="btn btn-primary btn-sm px-3 enc-tab-btn<?= $tabActiva === 'clientes' ? '' : ' d-none' ?>" data-tab-btn="clientes" onclick="abrirModalClienteNuevo('', 'clientes-tab')">
            <i class="bi bi-plus-lg"></i> Nuevo cliente
        </button>
    </div>
</div>

<!-- Pestañas -->
<ul class="nav nav-tabs nav-tabs-cmg mb-0" id="tabsTareas" role="tablist">
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
    <li class="nav-item">
        <a class="nav-link<?= $tabActiva === 'clientes' ? ' active' : '' ?>"
            href="<?= $base ?>/config/tareas-obligaciones?tab=clientes"
            onclick="cambiarTab('clientes',event)"
            id="tab-clientes-link"><i class="bi bi-people me-1"></i>Detalle por cliente</a>
    </li>
</ul>

<!-- ══════════════════════════════════════════════════════ -->
<!--  TAB: TAREAS -->
<!-- ══════════════════════════════════════════════════════ -->
<div id="panel-tareas" class="tab-panel<?= $tabActiva === 'tareas' ? '' : ' d-none' ?>">
    <div class="cmg-dashboard-frame">
        <div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
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
            <div class="tareas-filtros py-2 px-3 bg-light bg-opacity-50 border-top border-bottom">
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
                <table class="table table-hover table-sm mb-0" id="tabla-tareas">
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
        <div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
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
                <table class="table table-hover table-sm mb-0">
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

<!-- ══════════════════════════════════════════════════════ -->
<!--  TAB: CLIENTES -->
<!-- ══════════════════════════════════════════════════════ -->
<div id="panel-clientes" class="tab-panel<?= $tabActiva === 'clientes' ? '' : ' d-none' ?>">
    <div class="cmg-dashboard-frame">
        <div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
            <div class="card-header px-3 py-2 border-bottom-0">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <div class="input-group input-group-sm" style="max-width:280px">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" id="buscar-clientes" class="form-control border-start-0" placeholder="Buscar cliente…" autocomplete="off" oninput="buscarClientesDebounce()">
                    </div>
                    <span class="text-muted small ms-auto me-2" id="info-clientes">-</span>
                    <div id="pagination-clientes"></div>
                </div>
            </div>
            <div class="px-3 py-1 small text-muted border-bottom">Clientes con obligaciones vigentes<?= $nivelUsuarioActual < 3 ? ' a tu cargo' : '' ?>. Haz clic en uno para ver el detalle y, si quieres, duplicar sus obligaciones hacia otro cliente.</div>
            <div class="table-scroll">
                <table class="table table-hover table-sm mb-0">
                    <thead>
                        <tr>
                            <th class="sortable-header ps-3" onclick="sortClientes('nombre')">Nombre <i class="bi bi-arrow-down-up text-muted" id="sort-icon-clientes-nombre"></i></th>
                            <th class="sortable-header" onclick="sortClientes('ruc')">RUC <i class="bi bi-arrow-down-up text-muted" id="sort-icon-clientes-ruc"></i></th>
                            <th>Correo</th>
                            <th>Teléfono</th>
                            <th class="sortable-header text-center" onclick="sortClientes('obligaciones_vigentes')">Obligaciones <i class="bi bi-arrow-down-up text-muted" id="sort-icon-clientes-obligaciones_vigentes"></i></th>
                            <th class="sortable-header text-center pe-3" onclick="sortClientes('created_at')">Creado <i class="bi bi-arrow-down-up text-muted" id="sort-icon-clientes-created_at"></i></th>
                        </tr>
                    </thead>
                    <tbody id="tbody-clientes">
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
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
                                <div class="input-group input-group-sm">
                                    <div class="autocomplete-wrap flex-grow-1">
                                        <input type="text" id="tarea-cliente-buscar" class="form-control form-control-sm"
                                            placeholder="Buscar o escribir nombre…"
                                            autocomplete="off" oninput="buscarClienteAC(this.value)">
                                        <div id="tarea-cliente-ac" class="autocomplete-list"></div>
                                    </div>
                                    <button class="btn btn-outline-secondary btn-sm" type="button" onclick="abrirModalClienteNuevo()" title="Crear nuevo cliente">
                                        <i class="bi bi-plus"></i></button>
                                </div>
                                <input type="hidden" id="tarea-id-cliente" value="">
                            </div>
                            <div class="col-md-6 border-start ps-3">
                                <label class="form-label-sm fw-bold">Correo(s) para notificación <span class="text-danger">*</span></label>
                                <div class="tags-wrap form-control form-control-sm" id="tags-correos-cliente" onclick="document.getElementById('correo-input').focus()">
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
                            <div class="autocomplete-wrap flex-grow-1">
                                <input type="text" id="tarea-obligacion-buscar" class="form-control form-control-sm"
                                    placeholder="Buscar obligación…" autocomplete="off"
                                    oninput="buscarObligacionAC(this.value)" onfocus="mostrarObligacionAC()">
                                <div id="tarea-obligacion-ac" class="autocomplete-list"></div>
                            </div>
                            <input type="hidden" id="tarea-id-obligacion" value="">
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
                        <label class="form-label-sm fw-bold">Fecha a realizar <span class="text-danger">*</span></label>
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
<!--  MODAL: NUEVO CLIENTE (rápido, desde el buscador de la tarea)   -->
<!-- ════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalClienteTarea" tabindex="-1" aria-labelledby="modalClienteTareaLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalClienteTareaLabel"><i class="bi bi-person-plus me-1"></i>Nuevo Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label-sm">Nombre <span class="text-danger">*</span></label>
                        <input type="text" id="cliente-nuevo-nombre" class="form-control form-control-sm" placeholder="Nombre o razón social">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-sm">RUC / Cédula</label>
                        <input type="text" id="cliente-nuevo-ruc" class="form-control form-control-sm" placeholder="Opcional">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-sm">Teléfono</label>
                        <input type="text" id="cliente-nuevo-telefono" class="form-control form-control-sm" placeholder="Opcional">
                    </div>
                    <div class="col-12">
                        <label class="form-label-sm">Correo <span class="text-danger">*</span></label>
                        <input type="email" id="cliente-nuevo-correo" class="form-control form-control-sm" placeholder="correo@ejemplo.com">
                    </div>
                </div>
                <div id="cliente-nuevo-error" class="alert alert-danger mt-2 d-none py-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-cliente-nuevo-guardar" onclick="guardarClienteNuevo()"><i class="bi bi-check-lg me-1"></i>Guardar</button>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════════ -->
<!--  MODAL: OBLIGACIONES VIGENTES DE UN CLIENTE (solo lectura)      -->
<!-- ════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalClienteCombo" tabindex="-1" aria-labelledby="modalClienteComboLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalClienteComboLabel"><i class="bi bi-person-lines-fill me-1"></i>Obligaciones de <span id="combo-cliente-nombre">-</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="combo-cliente-loading" class="text-center py-4 text-muted">
                    <div class="spinner-border spinner-border-sm me-2"></div>Cargando…
                </div>
                <div id="combo-cliente-vacio" class="alert alert-secondary d-none py-2 small">
                    <i class="bi bi-info-circle me-1"></i>Este cliente no tiene obligaciones vigentes.
                </div>
                <table class="table table-sm table-hover d-none" id="tabla-combo-cliente">
                    <thead>
                        <tr>
                            <th>Obligación</th>
                            <th>Periodicidad</th>
                            <th>Fecha</th>
                            <th>Responsables</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-combo-cliente"></tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-duplicar-combo" onclick="abrirModalDuplicarDesdeCombo()" disabled>
                    <i class="bi bi-copy me-1"></i>Duplicar a otro cliente
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════════ -->
<!--  MODAL: DUPLICAR COMBO HACIA OTRO CLIENTE                       -->
<!-- ════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalDuplicarCombo" tabindex="-1" aria-labelledby="modalDuplicarComboLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalDuplicarComboLabel"><i class="bi bi-copy me-1"></i>Duplicar obligaciones de <span id="dup-origen-nombre">-</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3 pb-2 border-bottom">
                    <div class="col-md-8">
                        <label class="form-label-sm fw-bold">Cliente destino <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <div class="autocomplete-wrap flex-grow-1">
                                <input type="text" id="duplicar-cliente-buscar" class="form-control form-control-sm"
                                    placeholder="Buscar o escribir nombre…" autocomplete="off"
                                    oninput="buscarDuplicarClienteAC(this.value)">
                                <div id="duplicar-cliente-ac" class="autocomplete-list"></div>
                            </div>
                            <button class="btn btn-outline-secondary btn-sm" type="button" onclick="abrirModalClienteNuevo('', 'duplicar-destino')" title="Crear nuevo cliente">
                                <i class="bi bi-plus"></i></button>
                        </div>
                        <input type="hidden" id="duplicar-id-cliente" value="">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-sm fw-bold">Correo destino <span class="text-danger">*</span></label>
                        <input type="email" id="duplicar-cliente-correo" class="form-control form-control-sm" placeholder="correo@ejemplo.com">
                    </div>
                </div>
                <div id="dup-error" class="alert alert-danger d-none py-2 mb-2 small"></div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle" id="tabla-duplicar">
                        <thead>
                            <tr>
                                <th style="width:34px" class="ps-3"><input type="checkbox" id="dup-check-all" checked onchange="toggleTodasFilasDuplicar(this.checked)"></th>
                                <th>Obligación</th>
                                <th style="width:160px">Periodicidad</th>
                                <th style="width:150px">Fecha</th>
                                <th>Responsables</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-duplicar"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-duplicar-guardar" onclick="guardarDuplicarCombo()">
                    <i class="bi bi-check-lg me-1"></i>Duplicar <span id="dup-count-badge">0</span> tarea(s)
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Panel flotante compacto para agregar un responsable a una fila de la tabla de duplicar -->
<div id="dup-resp-panel" class="dup-resp-panel d-none">
    <div class="p-2">
        <div class="autocomplete-wrap mb-1">
            <input type="text" id="dup-resp-input" class="form-control form-control-sm" placeholder="Buscar usuario o responsable…" autocomplete="off" oninput="buscarDupRespPanelAC(this.value)">
            <div id="dup-resp-panel-ac" class="autocomplete-list"></div>
        </div>
        <div class="d-flex gap-1 align-items-center">
            <input type="text" id="dup-resp-nombre-manual" class="form-control form-control-sm" placeholder="Nombre">
            <input type="email" id="dup-resp-mail-manual" class="form-control form-control-sm" placeholder="Correo">
            <button type="button" class="btn btn-primary btn-sm" onclick="agregarDupRespManual()" title="Agregar"><i class="bi bi-plus-lg"></i></button>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════════ -->
<!--  JAVASCRIPT                                                     -->
<!-- ════════════════════════════════════════════════════════════════ -->
<script>
    'use strict';
    var BASE = '<?= $base ?>';
    var obligacionesCatalogo = <?= json_encode($obligacionesActivas, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

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

    // Saca el dropdown del autocompletado del flujo del modal (que tiene overflow:hidden
    // por ser modal-dialog-scrollable) y lo posiciona fijo, por encima de todo, alineado al input.
    function flotarAC(inputEl, listEl) {
        if (listEl.parentNode !== document.body) {
            document.body.appendChild(listEl);
        }
        var rect = inputEl.getBoundingClientRect();
        listEl.style.position = 'fixed';
        listEl.style.left = rect.left + 'px';
        listEl.style.top = (rect.bottom + 2) + 'px';
        listEl.style.width = rect.width + 'px';
        listEl.style.right = 'auto';
        // Debe superar a .modal (z-index 5060 !important, ver public/css/app.css) para no
        // quedar oculto detrás del modal al flotarlo fuera de su árbol.
        listEl.style.zIndex = 5070;
    }

    // Cierra los autocompletados flotantes si el contenedor que los originó se desplaza
    // (evita que queden "flotando" desalineados del input al hacer scroll dentro del modal)
    document.addEventListener('scroll', function() {
        cerrarAC('tarea-cliente-ac');
        cerrarAC('tarea-obligacion-ac');
        cerrarAC('resp-ac');
        cerrarAC('duplicar-cliente-ac');
        cerrarPanelRespFila();
    }, true);

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
        modalObligBS = null,
        modalClienteBS = null,
        modalComboBS = null,
        modalDuplicarBS = null;
    var tabActual = '<?= $tabActiva ?>';
    var TAB_LINK_ID = { tareas: 'tab-tareas-link', obligaciones: 'tab-oblig-link', clientes: 'tab-clientes-link' };

    // ── Bootstrap modal refs ─────────────────────────────────────────
    window.addEventListener('DOMContentLoaded', function() {
        try {
            var modalT = document.getElementById('modalTarea');
            var modalO = document.getElementById('modalOblig');
            var modalC = document.getElementById('modalClienteTarea');
            var modalCombo = document.getElementById('modalClienteCombo');
            var modalDup = document.getElementById('modalDuplicarCombo');
            if (modalT) modalTareaBS = new bootstrap.Modal(modalT);
            if (modalO) modalObligBS = new bootstrap.Modal(modalO);
            if (modalC) modalClienteBS = new bootstrap.Modal(modalC);
            if (modalCombo) modalComboBS = new bootstrap.Modal(modalCombo);
            if (modalDup) modalDuplicarBS = new bootstrap.Modal(modalDup);

            // Los dropdowns de autocompletado se flotan fuera del modal (ver flotarAC);
            // hay que cerrarlos explícitamente si el modal se cierra, porque quedan
            // fuera de su árbol y el listener de "click afuera" no los alcanza.
            [modalT, modalO, modalC, modalCombo, modalDup].forEach(function(m) {
                if (!m) return;
                m.addEventListener('hidden.bs.modal', function() {
                    cerrarAC('tarea-cliente-ac');
                    cerrarAC('tarea-obligacion-ac');
                    cerrarAC('resp-ac');
                    cerrarAC('duplicar-cliente-ac');
                    cerrarPanelRespFila();
                });
            });

            if (tabActual === 'tareas') {
                buscarTareas(1);
            } else if (tabActual === 'clientes') {
                buscarClientes(1);
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
        document.getElementById(TAB_LINK_ID[tab] || TAB_LINK_ID.tareas).classList.add('active');
        // Botón "Nueva…" superior: mostrar solo el de la pestaña activa
        document.querySelectorAll('.enc-tab-btn').forEach(function(b) { b.classList.add('d-none'); });
        var encBtn = document.querySelector('.enc-tab-btn[data-tab-btn="' + tab + '"]');
        if (encBtn) encBtn.classList.remove('d-none');
        if (tab === 'tareas') {
            buscarTareas(1);
        } else if (tab === 'clientes') {
            buscarClientes(1);
        } else {
            buscarOblig(1);
        }
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

        var nombre = document.getElementById('oblig-nombre').value;
        var status = document.getElementById('oblig-status').value;

        var action = id ? 'obligaciones-update' : 'obligaciones-store';
        var fd = new FormData();
        if (id) fd.append('id', id);
        fd.append('nombre', nombre);
        fd.append('descripcion', document.getElementById('oblig-descripcion').value);
        fd.append('status', status);

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
                    var idFinal = id || d.id;
                    actualizarCatalogoObligacion(idFinal, nombre, status === '1');

                    modalObligBS.hide();
                    buscarOblig(obligPage);
                    if (typeof window.updateTareasBadge === 'function') window.updateTareasBadge();

                    // Si se creó/editó desde el modal de tarea, la seleccionamos automáticamente
                    if (window._obligDesdeModalTarea && status === '1') {
                        seleccionarObligacion({
                            id: idFinal,
                            nombre: nombre
                        });
                    }
                    window._obligDesdeModalTarea = false;
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
                    obligacionesCatalogo = obligacionesCatalogo.filter(function(o) {
                        return String(o.id) !== String(id);
                    });
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
        document.getElementById('oblig-nombre').value = document.getElementById('tarea-obligacion-buscar').value || '';
        document.getElementById('oblig-descripcion').value = '';
        document.getElementById('oblig-status').value = '1';
        document.getElementById('oblig-error').classList.add('d-none');
        document.getElementById('oblig-modal-titulo').textContent = 'Nueva Obligación';
        document.getElementById('btn-oblig-delete').classList.add('d-none');
        window._obligDesdeModalTarea = true;
        modalObligBS.show();
    };

    // Mantiene obligacionesCatalogo sincronizado tras crear/editar (sin recargar la página)
    function actualizarCatalogoObligacion(id, nombre, activa) {
        obligacionesCatalogo = obligacionesCatalogo.filter(function(o) {
            return String(o.id) !== String(id);
        });
        if (activa) {
            obligacionesCatalogo.push({
                id: id,
                nombre: nombre
            });
            obligacionesCatalogo.sort(function(a, b) {
                return a.nombre.localeCompare(b.nombre);
            });
        }
    }

    // ── Obligación: buscar y seleccionar (autocompletado sobre catálogo local) ──
    function seleccionarObligacion(data) {
        var o = (typeof data === 'string') ? JSON.parse(decodeURIComponent(data)) : data;
        cerrarAC('tarea-obligacion-ac');
        if (!o) return;
        document.getElementById('tarea-obligacion-buscar').value = o.nombre;
        document.getElementById('tarea-id-obligacion').value = o.id;
    }

    window.buscarObligacionAC = function(q) {
        document.getElementById('tarea-id-obligacion').value = '';
        renderObligacionAC(q);
    };

    // Usado en focus: muestra el listado filtrado por el texto actual sin borrar la selección vigente
    window.mostrarObligacionAC = function() {
        renderObligacionAC(document.getElementById('tarea-obligacion-buscar').value);
    };

    function renderObligacionAC(q) {
        q = (q || '').trim();
        var qLower = q.toLowerCase();
        var coincidencias = obligacionesCatalogo.filter(function(o) {
            return qLower === '' || o.nombre.toLowerCase().indexOf(qLower) !== -1;
        });

        var list = document.getElementById('tarea-obligacion-ac');
        var html = '';
        coincidencias.forEach(function(o) {
            html += '<div class="ac-item" data-json=\'' + encEscJson(o) + '\'>' +
                '<span class="fw-medium">' + escH(o.nombre) + '</span></div>';
        });
        if (q !== '') {
            html += '<div class="ac-item d-flex align-items-center text-primary" style="' + (coincidencias.length ? 'border-top:1px solid #f0f0f0;' : '') + '" data-crear-oblig="1">' +
                '<i class="bi bi-plus-circle me-2 fs-6"></i>' +
                '<span class="fw-medium">Crear obligación nueva: "' + escH(q) + '"</span></div>';
        }
        if (!html) {
            list.style.display = 'none';
            list.innerHTML = '';
            return;
        }

        list.innerHTML = html;
        flotarAC(document.getElementById('tarea-obligacion-buscar'), list);
        list.style.display = 'block';
        list.querySelectorAll('.ac-item[data-json]').forEach(function(el) {
            el.addEventListener('click', function() {
                seleccionarObligacion(el.getAttribute('data-json'));
            });
        });
        var itemCrear = list.querySelector('[data-crear-oblig]');
        if (itemCrear) {
            itemCrear.addEventListener('click', function() {
                cerrarAC('tarea-obligacion-ac');
                abrirModalObligNuevaDesdeModal();
            });
        }
    }

    // ══════════════════════════════════════════════════════════════════
    //  CLIENTES (listado)
    // ══════════════════════════════════════════════════════════════════
    var PERIODICIDAD_LABELS = {
        semanal: 'Semanal', quincenal: 'Quincenal', mensual: 'Mensual', trimestral: 'Trimestral',
        semestral: 'Semestral', anual: 'Anual', dos_anios: '2 Años', tres_anios: '3 Años',
        cuatro_anios: '4 Años', cinco_anios: '5 Años'
    };

    var clienteSort = { col: 'nombre', dir: 'ASC' };
    var clientesPage = 1;
    var debounceClientes;

    window.buscarClientesDebounce = function() {
        clearTimeout(debounceClientes);
        debounceClientes = setTimeout(function() {
            buscarClientes(1);
        }, 320);
    };
    window.buscarClientes = function(page) {
        clientesPage = page || 1;
        var b = document.getElementById('buscar-clientes').value;
        var params = new URLSearchParams({ b: b, page: clientesPage, sort: clienteSort.col, dir: clienteSort.dir });
        fetch(BASE + '/config/tareas-obligaciones?action=clientes-search-ajax&' + params.toString())
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d.ok) return;
                document.getElementById('tbody-clientes').innerHTML = d.rows;
                document.getElementById('info-clientes').textContent = d.info;
                document.getElementById('pagination-clientes').innerHTML = d.pagination;
            });
    };
    window.cambiarPaginaCliente = function(p) {
        if (p >= 1) buscarClientes(p);
    };
    window.sortClientes = function(col) {
        if (clienteSort.col === col) clienteSort.dir = clienteSort.dir === 'ASC' ? 'DESC' : 'ASC';
        else { clienteSort.col = col; clienteSort.dir = 'ASC'; }
        actualizarIconosSort('clientes', clienteSort);
        buscarClientes(1);
    };

    // ── Detalle: obligaciones vigentes de un cliente ──────────────────
    var comboOrigen = null; // {idCliente, nombre, correo, items: [...]}

    window.abrirModalClienteCombo = function(tr) {
        var r = JSON.parse(tr.getAttribute('data-row'));
        comboOrigen = { idCliente: r.id, nombre: r.nombre, correo: r.correo, items: [] };

        document.getElementById('combo-cliente-nombre').textContent = r.nombre;
        document.getElementById('combo-cliente-loading').classList.remove('d-none');
        document.getElementById('combo-cliente-vacio').classList.add('d-none');
        document.getElementById('tabla-combo-cliente').classList.add('d-none');
        document.getElementById('btn-duplicar-combo').disabled = true;
        modalComboBS.show();

        fetch(BASE + '/config/tareas-obligaciones?action=cliente-combo-ajax&id_cliente=' + r.id)
            .then(function(res) { return res.json(); })
            .then(function(d) {
                document.getElementById('combo-cliente-loading').classList.add('d-none');
                if (!d.ok || !d.data || d.data.length === 0) {
                    document.getElementById('combo-cliente-vacio').classList.remove('d-none');
                    return;
                }
                comboOrigen.items = d.data;

                var html = '';
                d.data.forEach(function(it) {
                    var periodo = PERIODICIDAD_LABELS[it.periodicidad] || it.periodicidad;
                    var fecha = it.fecha_tarea ? it.fecha_tarea.split('-').reverse().join('-') : '-';
                    var resp = (it.responsables || []).map(function(r) { return escH(r.nombre); }).join(', ') || '<span class="text-muted">—</span>';
                    html += '<tr><td>' + escH(it.obligacion_nombre) + '</td><td>' + periodo + '</td>' +
                        '<td>' + fecha + '</td><td class="small">' + resp + '</td></tr>';
                });
                document.getElementById('tbody-combo-cliente').innerHTML = html;
                document.getElementById('tabla-combo-cliente').classList.remove('d-none');
                document.getElementById('btn-duplicar-combo').disabled = false;
            });
    };

    // ── Duplicar combo hacia otro cliente ──────────────────────────────
    var duplicarFilas = []; // [{incluir, id_obligacion, obligacion_nombre, periodicidad, fecha_tarea, responsables}]

    window.abrirModalDuplicarDesdeCombo = function() {
        if (!comboOrigen || !comboOrigen.items.length) return;

        duplicarFilas = comboOrigen.items.map(function(it) {
            return {
                incluir: true,
                id_obligacion: it.id_obligacion,
                obligacion_nombre: it.obligacion_nombre,
                periodicidad: it.periodicidad,
                fecha_tarea: it.fecha_tarea,
                responsables: (it.responsables || []).map(function(r) {
                    return { id: r.id_usuario || r.id_resp_tarea || '', nombre: r.nombre, mail: r.mail || '', tipo: r.tipo || 'propio' };
                })
            };
        });

        document.getElementById('dup-origen-nombre').textContent = comboOrigen.nombre;
        document.getElementById('duplicar-cliente-buscar').value = '';
        document.getElementById('duplicar-id-cliente').value = '';
        document.getElementById('duplicar-cliente-correo').value = '';
        document.getElementById('dup-check-all').checked = true;
        document.getElementById('dup-error').classList.add('d-none');

        renderTablaDuplicar();
        modalDuplicarBS.show();
    };

    function renderTablaDuplicar() {
        var tbody = document.getElementById('tbody-duplicar');
        var html = '';
        duplicarFilas.forEach(function(f, idx) {
            var tags = (f.responsables || []).map(function(r, ri) {
                return '<span class="badge bg-light text-dark border me-1 mb-1" style="font-size:.68rem">' + escH(r.nombre) +
                    ' <span role="button" onclick="quitarDupResponsable(' + idx + ',' + ri + ')" class="text-danger">&times;</span></span>';
            }).join('');

            html += '<tr>' +
                '<td class="ps-3"><input type="checkbox" ' + (f.incluir ? 'checked' : '') + ' onchange="onIncluirFilaDuplicar(' + idx + ',this.checked)"></td>' +
                '<td>' + escH(f.obligacion_nombre) + '</td>' +
                '<td>' + renderSelectPeriodicidadFila(idx, f.periodicidad) + '</td>' +
                '<td><input type="date" class="form-control form-control-sm" value="' + escH(f.fecha_tarea || '') + '" onchange="onFechaFilaDuplicar(' + idx + ',this.value)"></td>' +
                '<td>' + tags + '<button type="button" class="btn btn-outline-secondary btn-sm py-0 px-1" style="font-size:.68rem" onclick="event.stopPropagation(); abrirPanelRespFila(' + idx + ', this)"><i class="bi bi-plus"></i></button></td>' +
                '</tr>';
        });
        tbody.innerHTML = html;
        actualizarContadorDuplicar();
    }

    function renderSelectPeriodicidadFila(idx, actual) {
        var html = '<select class="form-select form-select-sm" onchange="onPeriodicidadFilaDuplicar(' + idx + ',this.value)">';
        Object.keys(PERIODICIDAD_LABELS).forEach(function(key) {
            html += '<option value="' + key + '"' + (key === actual ? ' selected' : '') + '>' + PERIODICIDAD_LABELS[key] + '</option>';
        });
        return html + '</select>';
    }

    function actualizarContadorDuplicar() {
        var n = duplicarFilas.filter(function(f) { return f.incluir; }).length;
        document.getElementById('dup-count-badge').textContent = n;
    }

    window.toggleTodasFilasDuplicar = function(checked) {
        duplicarFilas.forEach(function(f) { f.incluir = checked; });
        renderTablaDuplicar();
    };
    window.onIncluirFilaDuplicar = function(idx, checked) {
        duplicarFilas[idx].incluir = checked;
        actualizarContadorDuplicar();
    };
    window.onPeriodicidadFilaDuplicar = function(idx, val) {
        duplicarFilas[idx].periodicidad = val;
    };
    window.onFechaFilaDuplicar = function(idx, val) {
        duplicarFilas[idx].fecha_tarea = val;
    };
    window.quitarDupResponsable = function(idx, ri) {
        duplicarFilas[idx].responsables.splice(ri, 1);
        renderTablaDuplicar();
    };

    // ── Cliente destino (buscador) ──────────────────────────────────
    var debounceDuplicarCliente;
    window.buscarDuplicarClienteAC = function(q) {
        clearTimeout(debounceDuplicarCliente);
        document.getElementById('duplicar-id-cliente').value = '';
        if (q.length < 2) {
            cerrarAC('duplicar-cliente-ac');
            return;
        }
        debounceDuplicarCliente = setTimeout(function() {
            fetch(BASE + '/config/tareas-obligaciones?action=buscar-clientes&q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    var list = document.getElementById('duplicar-cliente-ac');
                    if (!d.ok) { list.style.display = 'none'; return; }

                    var html = '';
                    var candidatos = (d.propios || []).concat(d.empresa || []);
                    candidatos.forEach(function(c) {
                        html += '<div class="ac-item d-flex align-items-center" data-json=\'' + encEscJson(c) + '\'>' +
                            '<i class="bi bi-person-badge text-warning me-2 fs-6"></i>' +
                            '<div class="d-flex flex-column" style="line-height:1.2">' +
                            '<span class="fw-medium">' + escH(c.nombre) + '</span>' +
                            '<small class="text-muted" style="font-size:0.75rem">' + escH(c.correo || 'Sin correo') + '</small></div></div>';
                    });
                    if (!html) { list.style.display = 'none'; list.innerHTML = ''; return; }

                    list.innerHTML = html;
                    flotarAC(document.getElementById('duplicar-cliente-buscar'), list);
                    list.style.display = 'block';
                    list.querySelectorAll('.ac-item[data-json]').forEach(function(el) {
                        el.addEventListener('click', function() {
                            seleccionarClienteDuplicar(JSON.parse(decodeURIComponent(el.getAttribute('data-json'))));
                        });
                    });
                });
        }, 280);
    };

    function seleccionarClienteDuplicar(c) {
        cerrarAC('duplicar-cliente-ac');
        if (!c) return;
        document.getElementById('duplicar-cliente-buscar').value = c.nombre;
        document.getElementById('duplicar-id-cliente').value = c.id || '';
        if (c.correo) document.getElementById('duplicar-cliente-correo').value = c.correo;
    }

    // ── Responsable por fila: panel flotante compacto ──────────────────
    var dupRespFilaActiva = null;

    window.abrirPanelRespFila = function(idx, btnEl) {
        dupRespFilaActiva = idx;
        var panel = document.getElementById('dup-resp-panel');
        var rect = btnEl.getBoundingClientRect();
        panel.style.left = Math.max(8, rect.right - 320) + 'px';
        panel.style.top = (rect.bottom + 4) + 'px';
        panel.classList.remove('d-none');
        document.getElementById('dup-resp-input').value = '';
        document.getElementById('dup-resp-nombre-manual').value = '';
        document.getElementById('dup-resp-mail-manual').value = '';
        cerrarAC('dup-resp-panel-ac');
        document.getElementById('dup-resp-input').focus();
    };

    function cerrarPanelRespFila() {
        var panel = document.getElementById('dup-resp-panel');
        if (panel) panel.classList.add('d-none');
        cerrarAC('dup-resp-panel-ac');
        dupRespFilaActiva = null;
    }

    var debounceDupRespPanel;
    window.buscarDupRespPanelAC = function(q) {
        clearTimeout(debounceDupRespPanel);
        if (q.length < 2) { cerrarAC('dup-resp-panel-ac'); return; }
        debounceDupRespPanel = setTimeout(function() {
            fetch(BASE + '/config/tareas-obligaciones?action=buscar-usuarios&q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    var list = document.getElementById('dup-resp-panel-ac');
                    if (!d.ok) { list.style.display = 'none'; return; }

                    var html = '';
                    (d.sistema || []).forEach(function(u) {
                        html += '<div class="ac-item" data-json=\'' + encEscJson(u) + '\'>' +
                            '<i class="bi bi-person-fill text-primary me-1 small"></i><span class="fw-medium">' + escH(u.nombre) + '</span>' +
                            (u.mail ? '<br><small class="text-muted">' + escH(u.mail) + '</small>' : '') + '</div>';
                    });
                    (d.propios || []).forEach(function(u) {
                        html += '<div class="ac-item" data-json=\'' + encEscJson({ id: u.id, nombre: u.nombre, mail: u.mail || '', tipo: 'propio' }) + '\'>' +
                            '<i class="bi bi-person-badge text-warning me-1 small"></i><span class="fw-medium">' + escH(u.nombre) + '</span></div>';
                    });
                    if (!html) { list.style.display = 'none'; list.innerHTML = ''; return; }

                    list.innerHTML = html;
                    list.style.display = 'block';
                    list.querySelectorAll('.ac-item[data-json]').forEach(function(el) {
                        el.addEventListener('click', function() {
                            agregarDupResponsable(JSON.parse(decodeURIComponent(el.getAttribute('data-json'))));
                        });
                    });
                });
        }, 280);
    };

    window.agregarDupRespManual = function() {
        var nombre = document.getElementById('dup-resp-nombre-manual').value.trim();
        var mail = document.getElementById('dup-resp-mail-manual').value.trim();
        if (!nombre || !mail) {
            if (typeof mostrarToast === 'function') mostrarToast('Nombre y correo son obligatorios.', 'warning');
            return;
        }
        agregarDupResponsable({ id: '', nombre: nombre, mail: mail, tipo: 'propio' });
    };

    function agregarDupResponsable(u) {
        if (dupRespFilaActiva === null || !u || !u.nombre) return;
        var fila = duplicarFilas[dupRespFilaActiva];
        var yaExiste = fila.responsables.some(function(s) {
            if (u.id && s.id && u.id == s.id && u.tipo == s.tipo) return true;
            if (u.mail && s.mail && u.mail.toLowerCase() === s.mail.toLowerCase()) return true;
            return false;
        });
        if (yaExiste) {
            if (typeof mostrarToast === 'function') mostrarToast('Ya está en la lista de esta fila.', 'info');
            return;
        }
        fila.responsables.push(u);
        cerrarPanelRespFila();
        renderTablaDuplicar();
    }

    // ── Guardar duplicado ────────────────────────────────────────────
    window.guardarDuplicarCombo = function() {
        var nombre = document.getElementById('duplicar-cliente-buscar').value.trim();
        var correo = document.getElementById('duplicar-cliente-correo').value.trim();
        var idCliente = document.getElementById('duplicar-id-cliente').value;
        var errEl = document.getElementById('dup-error');
        errEl.classList.add('d-none');

        if (!nombre) { errEl.textContent = 'Debe indicar el cliente destino.'; errEl.classList.remove('d-none'); return; }
        if (!correo) { errEl.textContent = 'El correo del cliente destino es obligatorio.'; errEl.classList.remove('d-none'); return; }

        var seleccionadas = duplicarFilas.filter(function(f) { return f.incluir; });
        if (!seleccionadas.length) { errEl.textContent = 'Selecciona al menos una obligación para duplicar.'; errEl.classList.remove('d-none'); return; }

        var sinResponsable = seleccionadas.find(function(f) { return !f.responsables || !f.responsables.length; });
        if (sinResponsable) {
            errEl.textContent = 'La obligación "' + sinResponsable.obligacion_nombre + '" no tiene responsable asignado.';
            errEl.classList.remove('d-none');
            return;
        }

        var btn = document.getElementById('btn-duplicar-guardar');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Duplicando…';

        var fd = new FormData();
        fd.append('id_cliente_destino', idCliente);
        fd.append('cliente_nombre_destino', nombre);
        fd.append('cliente_correo_destino', correo);
        fd.append('items', JSON.stringify(seleccionadas));

        fetch(BASE + '/config/tareas-obligaciones?action=tareas-copiar-combo', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Duplicar <span id="dup-count-badge">' + seleccionadas.length + '</span> tarea(s)';
                if (d.ok) {
                    modalDuplicarBS.hide();
                    modalComboBS.hide();
                    buscarClientes(clientesPage);
                    if (typeof mostrarToast === 'function') mostrarToast(d.msg || 'Copiado correctamente.', 'success');
                } else {
                    errEl.textContent = d.error || 'Error al duplicar.';
                    errEl.classList.remove('d-none');
                }
            })
            .catch(function() {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Duplicar <span id="dup-count-badge">' + seleccionadas.length + '</span> tarea(s)';
                errEl.textContent = 'Error de conexión al duplicar.';
                errEl.classList.remove('d-none');
            });
    };

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
        document.getElementById('tarea-obligacion-buscar').value = '';
        document.getElementById('tarea-id-obligacion').value = '';
        document.getElementById('tarea-periodicidad').value = '';
        document.getElementById('tarea-fecha').value = '';
        document.getElementById('tarea-estado').value = 'por_realizar';
        // Toda tarea nace "Por realizar"; el campo solo se habilita al editar una existente.
        document.getElementById('tarea-estado').disabled = true;
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
        respSeleccion = null;
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
        document.getElementById('tarea-obligacion-buscar').value = r.obligacion_nombre || '';
        document.getElementById('tarea-id-obligacion').value = r.id_obligacion || '';
        // Correos iniciales desde r (para carga inmediata)
        correosSeleccionados = (r.cliente_correo || '').split(',').map(function(e) {
            return e.trim();
        }).filter(Boolean);
        renderTagsCorreos();

        document.getElementById('tarea-periodicidad').value = r.periodicidad;
        document.getElementById('tarea-fecha').value = r.fecha_tarea;
        document.getElementById('tarea-estado').value = r.estado;
        document.getElementById('tarea-estado').disabled = false;
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
        fd.append('id_obligacion', document.getElementById('tarea-id-obligacion').value);
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

    // ── Crear cliente nuevo (desde el buscador de la tarea) ────────────
    // Contexto de a dónde debe ir el cliente recién creado/seleccionado: 'tarea' (modal de
    // tarea), 'clientes-tab' (solo refrescar el listado) o 'duplicar-destino' (modal de duplicar).
    var clienteNuevoContexto = 'tarea';
    window.abrirModalClienteNuevo = function(nombrePrefill, contexto) {
        clienteNuevoContexto = contexto || 'tarea';
        var prefillDefault = clienteNuevoContexto === 'tarea' ? (document.getElementById('tarea-cliente-buscar').value || '') : '';
        document.getElementById('cliente-nuevo-nombre').value = nombrePrefill || prefillDefault;
        document.getElementById('cliente-nuevo-ruc').value = '';
        document.getElementById('cliente-nuevo-telefono').value = '';
        document.getElementById('cliente-nuevo-correo').value = '';
        document.getElementById('cliente-nuevo-error').classList.add('d-none');
        modalClienteBS.show();
    };

    window.guardarClienteNuevo = function() {
        var nombre = document.getElementById('cliente-nuevo-nombre').value.trim();
        var correo = document.getElementById('cliente-nuevo-correo').value.trim();
        var ruc = document.getElementById('cliente-nuevo-ruc').value.trim();
        var telefono = document.getElementById('cliente-nuevo-telefono').value.trim();
        var errEl = document.getElementById('cliente-nuevo-error');
        errEl.classList.add('d-none');

        if (!nombre) {
            errEl.textContent = 'El nombre es obligatorio.';
            errEl.classList.remove('d-none');
            return;
        }
        if (!correo) {
            errEl.textContent = 'El correo es obligatorio.';
            errEl.classList.remove('d-none');
            return;
        }

        var btn = document.getElementById('btn-cliente-nuevo-guardar');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando…';

        var fd = new FormData();
        fd.append('nombre', nombre);
        fd.append('correo', correo);
        fd.append('ruc', ruc);
        fd.append('telefono', telefono);

        fetch(BASE + '/config/tareas-obligaciones?action=crear-cliente-tarea', {
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
                    modalClienteBS.hide();
                    if (clienteNuevoContexto === 'duplicar-destino') {
                        seleccionarClienteDuplicar({ id: d.id, nombre: d.nombre, correo: d.correo });
                    } else if (clienteNuevoContexto === 'clientes-tab') {
                        buscarClientes(clientesPage);
                    } else {
                        seleccionarCliente({
                            id: d.id,
                            nombre: d.nombre,
                            correo: d.correo,
                            origen: 'propio'
                        });
                    }
                    if (typeof mostrarToast === 'function') mostrarToast(d.msg || 'Cliente guardado.', 'success');
                } else {
                    errEl.textContent = d.error || 'Error al guardar el cliente.';
                    errEl.classList.remove('d-none');
                }
            })
            .catch(function() {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Guardar';
                errEl.textContent = 'Error de conexión al guardar el cliente.';
                errEl.classList.remove('d-none');
            });
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
                    var hayResultados = false;
                    // Clientes propios (clientes_tareas)
                    if (d.propios && d.propios.length > 0) {
                        hayResultados = true;
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
                        hayResultados = true;
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
                    // Opción siempre visible: crear cliente nuevo con lo escrito
                    html += '<div class="ac-item d-flex align-items-center text-primary" style="' + (hayResultados ? 'border-top:1px solid #f0f0f0;' : '') + '" data-crear-cliente="1">' +
                        '<i class="bi bi-plus-circle me-2 fs-6"></i>' +
                        '<span class="fw-medium">Crear cliente nuevo: "' + escH(q) + '"</span></div>';

                    list.innerHTML = html;
                    flotarAC(document.getElementById('tarea-cliente-buscar'), list);
                    list.style.display = 'block';
                    list.querySelectorAll('.ac-item[data-json]').forEach(function(el) {
                        el.addEventListener('click', function() {
                            seleccionarCliente(el.getAttribute('data-json'));
                        });
                    });
                    var itemCrear = list.querySelector('[data-crear-cliente]');
                    if (itemCrear) {
                        itemCrear.addEventListener('click', function() {
                            cerrarAC('tarea-cliente-ac');
                            abrirModalClienteNuevo(q);
                        });
                    }
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
                    flotarAC(document.getElementById('resp-input'), list);
                    list.style.display = 'block';
                    list.querySelectorAll('.ac-item[data-json]').forEach(function(el) {
                        el.addEventListener('click', function() {
                            seleccionarResponsableEnInputs(el.getAttribute('data-json'));
                        });
                    });
                });
        }, 280);
    };


    // Selección desde el buscador: solo llena Nombre y Correo; "Añadir" confirma
    var respSeleccion = null; // {id, tipo, nombre, mail} de lo elegido en el buscador, o null si es texto libre
    function seleccionarResponsableEnInputs(data) {
        var u = (typeof data === 'string') ? JSON.parse(decodeURIComponent(data)) : data;
        cerrarAC('resp-ac');
        if (!u || !u.nombre) return;

        document.getElementById('resp-input').value = u.nombre;
        document.getElementById('resp-mail-input').value = u.mail || '';
        respSeleccion = {
            id: u.id || '',
            tipo: u.tipo || 'propio',
            nombre: u.nombre,
            mail: u.mail || ''
        };

        if (!u.mail) {
            document.getElementById('resp-mail-input').focus();
        }
    }

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

        // Si el nombre/correo siguen coincidiendo con lo elegido en el buscador, conserva su
        // vínculo (id_usuario / id_resp_tarea); si el usuario los editó, se trata como texto libre.
        var usarSeleccion = respSeleccion && respSeleccion.nombre === name && respSeleccion.mail === mail;

        agregarResponsable({
            id: usarSeleccion ? respSeleccion.id : '',
            nombre: name,
            mail: mail,
            tipo: usarSeleccion ? respSeleccion.tipo : 'propio'
        });

        nInp.value = '';
        mInp.value = '';
        respSeleccion = null;
        nInp.focus();
    };
    window.onRespKeydown = function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            commitResponsableManual();
        }
    };

    function agregarResponsable(u) {
        // Comprobación de duplicados (por ID+Tipo o por Correo)
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
            cerrarAC('tarea-obligacion-ac');
            cerrarAC('resp-ac');
            cerrarAC('duplicar-cliente-ac');
        }
        if (!e.target.closest('#dup-resp-panel')) {
            cerrarPanelRespFila();
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