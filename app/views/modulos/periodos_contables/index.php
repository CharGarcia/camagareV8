<?php
$base = BASE_URL;
$urlBasePeriodos = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');

$rows       = $rows       ?? [];
$total      = $total      ?? 0;
$page       = $page       ?? 1;
$totalPages = $totalPages ?? 1;
$perPage    = $perPage    ?? 20;
$ordenCol   = $ordenCol   ?? 'fecha_inicial';
$ordenDir   = $ordenDir   ?? 'desc';
$buscar     = $buscar     ?? '';
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>
<style>
    .periodos-header {
        flex-shrink: 0;
    }

    .periodos-scroll {
        max-height: calc(100vh - 240px);
        overflow-y: auto;
    }

    .periodos-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }

    .periodo-row {
        cursor: pointer;
    }

    .periodo-row:hover {
        background-color: rgba(0, 0, 0, .04);
    }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<div class="periodos-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-calendar-range"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="abrirModalPeriodoCrear()"><i class="bi bi-plus-lg"></i> Nuevo</button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <!-- Buscador y Exportación -->
        <div class="d-flex align-items-center gap-2">
            <form method="POST" action="<?= $urlBasePeriodos ?>" class="d-flex align-items-center m-0" onsubmit="return false;">
                <input type="hidden" name="page" value="1">
                <input type="hidden" name="sort" value="<?= htmlspecialchars($ordenCol) ?>">
                <input type="hidden" name="dir" value="<?= htmlspecialchars($ordenDir) ?>">
                <div class="input-group input-group-sm" style="width: 300px;">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="b" id="buscarPeriodo" class="form-control border-start-0 ps-0 shadow-none border" placeholder="Buscar periodo..." value="<?= htmlspecialchars($buscar) ?>" autocomplete="off">
                    <?php if ($buscar !== ''): ?>
                        <a href="<?= $urlBasePeriodos ?>" class="btn border border-start-0 text-muted" title="Limpiar"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </div>
                <button type="submit" class="d-none">Buscar</button>
            </form>

            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'nombre' => 'Nombre',
                    'fecha_inicial' => 'Fecha Inicial',
                    'fecha_final' => 'Fecha Final',
                    'status' => 'Estado'
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>

                <a id="btnExportPdf" href="<?= $urlBasePeriodos ?>/export-pdf?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                    class="btn btn-outline-danger" title="Descargar PDF">
                    <i class="bi bi-file-earmark-pdf"></i> PDF
                </a>
                <a id="btnExportExcel" href="<?= $urlBasePeriodos ?>/export-excel?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                    class="btn btn-outline-success" title="Descargar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                </a>
            </div>
        </div>

        <!-- Paginación -->
        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="paginationContainer" class="btn-group btn-group-sm">
                <?php if ($page <= 1): ?>
                    <button type="button" class="btn btn-outline-secondary" disabled><i class="bi bi-chevron-left"></i></button>
                <?php else: ?>
                    <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <?php endif; ?>

                <?php if ($page >= $totalPages): ?>
                    <button type="button" class="btn btn-outline-secondary" disabled><i class="bi bi-chevron-right"></i></button>
                <?php else: ?>
                    <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card-body p-0">
        <div class="periodos-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="nombre" data-col="nombre">
                            Nombre <i class="bi bi-arrow-down-up small text-muted ms-1"></i>
                        </th>
                        <th class="sortable-header" role="button" data-sort="fecha_inicial" data-col="fecha_inicial">
                            Fecha Inicial <i class="bi bi-arrow-down-up small text-muted ms-1"></i>
                        </th>
                        <th class="sortable-header" role="button" data-sort="fecha_final" data-col="fecha_final">
                            Fecha Final <i class="bi bi-arrow-down-up small text-muted ms-1"></i>
                        </th>
                        <th class="text-center pe-3 sortable-header" role="button" data-sort="status" data-col="status" style="width: 120px">
                            Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i>
                        </th>
                    </tr>
                </thead>
                <tbody id="tbodyPeriodos">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-5 text-muted">
                                <i class="bi bi-calendar-x fs-3 d-block mb-2"></i> No se encontraron periodos contables.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <tr class="periodo-row" role="button" tabindex="0" data-row='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>' onclick="abrirModalPeriodoEditar(this)">
                                <td class="fw-medium ps-3" data-col="nombre"><?= htmlspecialchars($r['nombre'] ?? '') ?></td>
                                <td data-col="fecha_inicial"><?= !empty($r['fecha_inicial']) ? date('d-m-Y', strtotime($r['fecha_inicial'])) : '' ?></td>
                                <td data-col="fecha_final"><?= !empty($r['fecha_final']) ? date('d-m-Y', strtotime($r['fecha_final'])) : '' ?></td>
                                <td class="text-center pe-3" data-col="status">
                                    <?php if (($r['status'] ?? 1) == 1): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Abierto</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Cerrado</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Periodo -->
<div class="modal fade" id="modalPeriodo" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <form id="formPeriodo" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-calendar-range me-2 text-primary"></i> <span id="tituloModal">Nuevo Periodo</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body pb-0">
                    <div id="modalAlert" class="alert d-none mb-3 py-2 small shadow-sm border-0"></div>
                    <input type="hidden" name="id" id="periodo_id">

                    <ul class="nav nav-tabs mb-3" id="modalPeriodoTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active fw-medium" id="tab-general-btn" data-bs-toggle="tab" data-bs-target="#pane-general" type="button" role="tab">
                                <i class="bi bi-card-text me-1"></i> General
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-medium" id="tab-info-btn" data-bs-toggle="tab" data-bs-target="#pane-info" type="button" role="tab">
                                <i class="bi bi-info-circle me-1"></i> Información
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content pb-3">
                        <div class="tab-pane fade show active" id="pane-general" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label small fw-bold">Nombre del Periodo *</label>
                                    <input type="text" name="nombre" id="periodo_nombre" class="form-control form-control-sm" required maxlength="100" autocomplete="off" placeholder="Ej: Ejercicio 2026">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Fecha Inicial *</label>
                                    <input type="date" name="fecha_inicial" id="periodo_fecha_inicial" class="form-control form-control-sm" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Fecha Final *</label>
                                    <input type="date" name="fecha_final" id="periodo_fecha_final" class="form-control form-control-sm" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold d-flex align-items-center">Estado</label>
                                    <select name="status" id="periodo_status" class="form-select form-select-sm">
                                        <option value="1">Abierto</option>
                                        <option value="0">Cerrado</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="pane-info" role="tabpanel">
                            <!-- Tarjeta de Permisos -->
                            <div class="col-12 px-3">
                                <div class="p-2 border rounded-3 bg-white shadow-sm mt-0 mb-3 mx-3">
                                    <div class="small fw-bold text-muted mb-2 d-flex align-items-center" style="font-size: 0.7rem;">
                                        <i class="bi bi-key-fill text-warning me-2"></i> MIS PERMISOS EN ESTE MÓDULO
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <span class="badge bg-<?= $perm['ver'] ? 'success' : 'secondary text-opacity-50' ?> bg-opacity-10 text-<?= $perm['ver'] ? 'success' : 'secondary' ?> border border-<?= $perm['ver'] ? 'success' : 'secondary' ?> border-opacity-25" style="font-size: 0.65rem;">VER</span>
                                        <span class="badge bg-<?= $perm['crear'] ? 'success' : 'secondary text-opacity-50' ?> bg-opacity-10 text-<?= $perm['crear'] ? 'success' : 'secondary' ?> border border-<?= $perm['crear'] ? 'success' : 'secondary' ?> border-opacity-25" style="font-size: 0.65rem;">CREAR</span>
                                        <span class="badge bg-<?= $perm['actualizar'] ? 'success' : 'secondary text-opacity-50' ?> bg-opacity-10 text-<?= $perm['actualizar'] ? 'success' : 'secondary' ?> border border-<?= $perm['actualizar'] ? 'success' : 'secondary' ?> border-opacity-25" style="font-size: 0.65rem;">MODIFICAR</span>
                                        <span class="badge bg-<?= $perm['eliminar'] ? 'success' : 'secondary text-opacity-50' ?> bg-opacity-10 text-<?= $perm['eliminar'] ? 'success' : 'secondary' ?> border border-<?= $perm['eliminar'] ? 'success' : 'secondary' ?> border-opacity-25" style="font-size: 0.65rem;">ELIMINAR</span>
                                        <?php if ($perm['todo']): ?>
                                            <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25" style="font-size: 0.65rem;">ACCESO TOTAL</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-light rounded-3 p-3 border mb-3 mx-3">
                                <h6 class="text-primary mb-3 small fw-bold"><i class="bi bi-clock-history me-2"></i>Historial de Cambios</h6>
                                <div id="auditoriaTimelinePeriodo" class="position-relative mt-2" style="max-height: 250px; overflow-y: auto; padding-right: 5px;">
                                    <div class="text-center py-4 text-muted small">
                                        <div class="spinner-border spinner-border-sm mb-2" role="status"></div>
                                        <div class="d-block">Cargando historial...</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between bg-light">
                    <div>
                        <?php if ($perm['eliminar']): ?>
                            <button type="button" id="btnEliminar" class="btn btn-outline-danger btn-sm px-3 d-none" onclick="eliminarPeriodo()">
                                <i class="bi bi-trash"></i> Eliminar
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm px-4" id="btnGuardar">
                            <i class="bi bi-check-lg"></i> Guardar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function() {
        'use strict';

        const urlBase = '<?= $urlBasePeriodos ?>';
        const form = document.getElementById('formPeriodo');
        const inputBuscar = document.getElementById('buscarPeriodo');
        let modalInst = null;
        let page = <?= $page ?>;
        let totalPages = <?= $totalPages ?>;
        let buscarTimer = null;
        let ordenCol = '<?= $ordenCol ?>';
        let ordenDir = '<?= $ordenDir ?>';

        function getModal() {
            if (!modalInst) modalInst = new bootstrap.Modal(document.getElementById('modalPeriodo'));
            return modalInst;
        }

        window.cambiarPaginaAjax = function(p) {
            if (p < 1 || p > totalPages) return;
            page = p;
            cargarListado();
        };

        window.limpiarBuscar = function() {
            if (inputBuscar) inputBuscar.value = '';
            page = 1;
            cargarListado();
        };

        if (inputBuscar) {
            inputBuscar.addEventListener('input', () => {
                clearTimeout(buscarTimer);
                buscarTimer = setTimeout(() => {
                    page = 1;
                    cargarListado();
                }, 500);
            });
        }

        document.querySelectorAll('.sortable-header').forEach(th => {
            th.addEventListener('click', () => {
                const newSort = th.dataset.sort;
                if (!newSort) return;
                if (ordenCol === newSort) {
                    ordenDir = (ordenDir.toLowerCase() === 'asc') ? 'DESC' : 'ASC';
                } else {
                    ordenCol = newSort;
                    ordenDir = 'ASC';
                }

                if (typeof window.guardarOrdenacionVista === 'function') {
                    window.guardarOrdenacionVista('periodos_contables', ordenCol, ordenDir);
                }

                page = 1;
                cargarListado();
            });
        });

        async function cargarListado() {
            const b = inputBuscar ? inputBuscar.value.trim() : '';
            const url = `${urlBase}/searchAjax?page=${page}&b=${encodeURIComponent(b)}&sort=${ordenCol}&dir=${ordenDir}`;

            try {
                const resp = await fetch(url);
                const json = await resp.json();

                if (json.ok) {
                    document.getElementById('tbodyPeriodos').innerHTML = json.rows;
                    document.getElementById('paginationInfo').textContent = json.info;
                    document.getElementById('paginationContainer').innerHTML = json.pagination;

                    if (json.pdf_url) document.getElementById('btnExportPdf').href = json.pdf_url;
                    if (json.excel_url) document.getElementById('btnExportExcel').href = json.excel_url;

                    if (json.total !== undefined) {
                        const perPage = 20;
                        totalPages = Math.ceil(json.total / perPage) || 1;
                    }

                    // Actualizar iconos de ordenamiento
                    document.querySelectorAll('.sortable-header').forEach(th => {
                        const icon = th.querySelector('i');
                        if (!icon) return;
                        const field = th.dataset.sort;
                        if (field === ordenCol) {
                            icon.className = (ordenDir.toLowerCase() === 'asc') ?
                                'bi bi-sort-alpha-down text-primary ms-1' :
                                'bi bi-sort-alpha-up text-primary ms-1';
                        } else {
                            icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
                        }
                    });
                }
            } catch (e) {
                console.error('Error cargando periodos:', e);
            }
        }

        window.abrirModalPeriodoCrear = function() {
            form.reset();
            document.getElementById('periodo_id').value = '';
            document.getElementById('tituloModal').textContent = 'Nuevo Periodo Contable';
            document.getElementById('btnEliminar')?.classList.add('d-none');

            if (typeof bootstrap !== 'undefined') {
                bootstrap.Tab.getInstance(document.getElementById('tab-general-btn'))?.show() || new bootstrap.Tab(document.getElementById('tab-general-btn')).show();
            }
            resetearInfoExtra();
            document.getElementById('tab-info-btn').classList.add('disabled');

            const mo = document.getElementById('modalAlert');
            if (mo) mo.classList.add('d-none');

            getModal().show();
        };

        window.abrirModalPeriodoEditar = function(row) {
            const data = JSON.parse(row.dataset.row);
            form.reset();
            document.getElementById('periodo_id').value = data.id;
            document.getElementById('periodo_nombre').value = data.nombre || '';

            // Format dates to YYYY-MM-DD for input[type="date"]
            if (data.fecha_inicial) {
                document.getElementById('periodo_fecha_inicial').value = data.fecha_inicial.split(' ')[0];
            }
            if (data.fecha_final) {
                document.getElementById('periodo_fecha_final').value = data.fecha_final.split(' ')[0];
            }

            const isInactive = data.status === false || data.status === 'false' || data.status === 0 || data.status === '0' || data.status === 'f';
            document.getElementById('periodo_status').value = isInactive ? '0' : '1';

            document.getElementById('tituloModal').textContent = 'Editar Periodo Contable';
            document.getElementById('btnEliminar')?.classList.remove('d-none');

            if (typeof bootstrap !== 'undefined') {
                bootstrap.Tab.getInstance(document.getElementById('tab-general-btn'))?.show() || new bootstrap.Tab(document.getElementById('tab-general-btn')).show();
            }
            document.getElementById('tab-info-btn').classList.remove('disabled');

            const mo = document.getElementById('modalAlert');
            if (mo) mo.classList.add('d-none');

            fetchInformacionExtra(data.id);
            getModal().show();
        };

        async function fetchInformacionExtra(id) {
            resetearInfoExtra('Cargando...');
            try {
                const resp = await fetch(`${urlBase}/getDetalleAjax?id=${id}`);
                const json = await resp.json();
                if (json.ok) {
                    fetchHistorialPeriodo(id);
                } else {
                    resetearInfoExtra('Error al cargar');
                }
            } catch (e) {
                resetearInfoExtra('Error de red');
            }
        }

        function resetearInfoExtra(msg = '-') {
            const timeline = document.getElementById('auditoriaTimelinePeriodo');
            if (timeline) timeline.innerHTML = '<div class="text-center py-4 text-muted small">No hay historial de cambios.</div>';
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('periodo_id').value;
            const action = id ? '/update' : '/store';
            const fd = new FormData(form);
            const btn = document.getElementById('btnGuardar');
            const alertEl = document.getElementById('modalAlert');

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';
            if (alertEl) alertEl.classList.add('d-none');

            try {
                const resp = await fetch(urlBase + action, {
                    method: 'POST',
                    body: fd
                });
                const json = await resp.json();

                if (alertEl) {
                    alertEl.textContent = json.msg || json.error || 'Error desconocido';
                    alertEl.className = 'alert mb-3 py-2 small shadow-sm border-0 ' + (json.ok ? 'alert-success' : 'alert-danger');
                    alertEl.classList.remove('d-none');
                }

                if (json.ok) {
                    setTimeout(() => {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';
                        getModal().hide();
                        cargarListado();
                    }, 800);
                } else {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';
                }
            } catch (err) {
                if (alertEl) {
                    alertEl.textContent = 'Error de red al conectar con el servidor';
                    alertEl.className = 'alert alert-danger mb-3 py-2 small shadow-sm border-0';
                    alertEl.classList.remove('d-none');
                }
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';
            }
        });

        window.eliminarPeriodo = async function() {
            const id = document.getElementById('periodo_id').value;
            if (!id || !confirm('¿Está seguro de eliminar este periodo contable?')) return;

            const fd = new FormData();
            fd.append('id_eliminar', id);

            try {
                const resp = await fetch(urlBase + '/delete', {
                    method: 'POST',
                    body: fd
                });
                const json = await resp.json();
                if (json.ok) {
                    getModal().hide();
                    cargarListado();
                } else {
                    alert(json.error);
                }
            } catch (e) {
                alert('Error al eliminar');
            }
        };

        async function fetchHistorialPeriodo(id) {
            const container = document.getElementById('auditoriaTimelinePeriodo');
            if (!container || !id) return;

            try {
                const resp = await fetch(`${urlBase}/getHistorialAjax?id=${id}&tabla=periodos_contables`);
                const json = await resp.json();

                if (json.ok && json.data.length > 0) {
                    let html = '<div class="timeline-border position-absolute h-100 border-start border-2 border-primary border-opacity-10" style="left: 10px; top: 0;"></div>';

                    json.data.forEach(log => {
                        const icon = log.accion.includes('Crear') ? 'bi-plus-circle-fill text-success' :
                            log.accion.includes('Actualizar') ? 'bi-pencil-fill text-primary' :
                            log.accion.includes('Eliminar') ? 'bi-trash-fill text-danger' :
                            'bi-clock-history text-secondary';

                        html += `
                            <div class="timeline-item position-relative mb-3 ps-4">
                                <div class="timeline-icon position-absolute rounded-circle bg-white d-flex align-items-center justify-content-center shadow-sm border" 
                                     style="left: 0; top: 0; width: 22px; height: 22px; z-index: 2;">
                                    <i class="bi ${icon}" style="font-size: 0.7rem;"></i>
                                </div>
                                <div class="timeline-content">
                                    <div class="d-flex justify-content-between align-items-center mb-0">
                                        <span class="fw-bold" style="font-size: 0.75rem;">${log.accion}</span>
                                        <span class="text-muted" style="font-size: 0.65rem;">${log.created_at}</span>
                                    </div>
                                    <div class="text-muted mb-1" style="font-size: 0.7rem;">
                                        <i class="bi bi-person me-1"></i> ${log.usuario_nombre || 'SISTEMA'}
                                    </div>
                                    <div class="bg-light rounded p-1 border border-light-subtle shadow-sm" style="font-size: 0.65rem;">
                                        ${renderDetalleHistorialPeriodo(log.detalles)}
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    container.innerHTML = html;
                } else {
                    container.innerHTML = `<div class="text-center py-4 text-muted small">No hay historial de cambios.</div>`;
                }
            } catch (e) {
                container.innerHTML = `<div class="text-center py-3 text-danger small">Error de carga.</div>`;
            }
        }

        function renderDetalleHistorialPeriodo(detalle) {
            if (!detalle || detalle.length === 0) return '<span class="text-muted">Sin detalles específicos</span>';
            if (typeof detalle === 'string') return detalle;
            if (Array.isArray(detalle)) {
                return `<ul class="list-unstyled mb-0">
                    ${detalle.map(d => {
                        if (typeof d === 'object') {
                            const antes = d.antes !== null ? `<span class="text-decoration-line-through text-muted">${d.antes}</span> ` : '';
                            return `<li><i class="bi bi-dot"></i> <span class="fw-bold">${d.campo}:</span> ${antes}<i class="bi bi-arrow-right mx-1"></i> ${d.despues}</li>`;
                        }
                        return ` < li > < i class = "bi bi-dot" > < /i> ${d}</li > `;
                    }).join('')}
                </ul>`;
            }
            return '<span class="text-muted">Acción registrada</span>';
        }

    })();
</script>