<?php
$base = BASE_URL;
$urlBaseBodegas = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');

$rows       = $rows       ?? [];
$total      = $total      ?? 0;
$page       = $page       ?? 1;
$totalPages = $totalPages ?? 1;
$perPage    = $perPage    ?? 20;
$ordenCol   = $ordenCol   ?? 'nombre';
$ordenDir   = $ordenDir   ?? 'asc';
$buscar     = $buscar     ?? '';
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>
<style>
    .bodegas-header {
        flex-shrink: 0;
    }

    .bodegas-scroll {
        max-height: calc(100dvh - 240px);
        overflow-y: auto;
    }

    .bodegas-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }

    .bodega-row {
        cursor: pointer;
    }

    .bodega-row:hover {
        background-color: rgba(0, 0, 0, .04);
    }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<div class="bodegas-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-box-seam"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="abrirModalBodegaCrear()"><i class="bi bi-plus-lg"></i> Nueva</button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <!-- Buscador y Exportación -->
        <div class="d-flex align-items-center gap-2">
            <form method="POST" action="<?= $urlBaseBodegas ?>" class="d-flex align-items-center m-0" onsubmit="return false;">
                <input type="hidden" name="page" value="1">
                <input type="hidden" name="sort" value="<?= htmlspecialchars($ordenCol) ?>">
                <input type="hidden" name="dir" value="<?= htmlspecialchars($ordenDir) ?>">
                <div class="input-group input-group-sm" style="width: 300px;">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="b" id="buscarBodega" class="form-control border-start-0 ps-0 shadow-none border" placeholder="Buscar bodega..." value="<?= htmlspecialchars($buscar) ?>" autocomplete="off">
                    <?php if ($buscar !== ''): ?>
                        <a href="<?= $urlBaseBodegas ?>" class="btn border border-start-0 text-muted" title="Limpiar"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </div>
                <button type="submit" class="d-none">Buscar</button>
            </form>

            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'nombre' => 'Nombre',
                    'status' => 'Estado'
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>

                <a id="btnExportPdf" href="<?= $urlBaseBodegas ?>/export-pdf?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                    class="btn btn-outline-danger" title="Descargar PDF">
                    <i class="bi bi-file-earmark-pdf"></i> PDF
                </a>
                <a id="btnExportExcel" href="<?= $urlBaseBodegas ?>/export-excel?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
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
        <div class="bodegas-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="nombre" data-col="nombre">
                            Nombre <i class="bi bi-arrow-down-up small text-muted ms-1"></i>
                        </th>
                        <th class="text-center pe-3 sortable-header" role="button" data-sort="status" data-col="status" style="width: 120px">
                            Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i>
                        </th>
                    </tr>
                </thead>
                <tbody id="tbodyBodegas">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="2" class="text-center py-5 text-muted">
                                <i class="bi bi-box-seam fs-3 d-block mb-2"></i> No se encontraron bodegas.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <tr class="bodega-row" role="button" tabindex="0" data-row='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>' onclick="abrirModalBodegaEditar(this)">
                                <td class="fw-medium ps-3" data-col="nombre"><?= htmlspecialchars($r['nombre'] ?? '') ?></td>
                                <td class="text-center pe-3" data-col="status">
                                    <?php if (($r['status'] ?? 1) == 1): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Inactivo</span>
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

<!-- Modal Bodega -->
<div class="modal fade" id="modalBodega" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <form id="formBodega" novalidate>
                <div class="modal-header bg-light">
                    <h5 class="modal-title fs-6 fw-bold">
                        <i class="bi bi-box-seam me-2 text-primary"></i> <span id="tituloModal">Nueva Bodega</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="modalAlert" class="alert d-none mx-3 mt-3 mb-0 py-2 small shadow-sm border-0"></div>
                    <input type="hidden" name="id" id="bodega_id">

                    <div class="d-flex align-items-center bg-light px-3 pt-2">
                        <ul class="nav nav-tabs border-bottom-0 flex-grow-1 tab-pestaña" id="modalBodegaTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <a class="nav-link active py-2 small" id="tab-general-btn" data-bs-toggle="tab" href="#pane-general" role="tab">
                                    <i class="bi bi-card-text me-1"></i> General
                                </a>
                            </li>
                            <?php if (($_SESSION['nivel'] ?? 1) >= 2): ?>
                                <li class="nav-item" role="presentation">
                                    <a class="nav-link py-2 small" id="tab-accesos-btn" data-bs-toggle="tab" href="#pane-accesos" role="tab">
                                        <i class="bi bi-person-lock me-1"></i> Accesos
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div class="border-bottom mx-3 mb-3"></div>

                    <div class="tab-content px-3 pb-3">
                        <div class="tab-pane fade show active" id="pane-general" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label small fw-bold">Nombre de la bodega *</label>
                                    <input type="text" name="nombre" id="bodega_nombre" class="form-control form-control-sm" required maxlength="100" autocomplete="off">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold d-flex align-items-center">Estado <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('bodegas', 'bodega_status', 'status') ?></label>
                                    <select name="status" id="bodega_status" class="form-select form-select-sm">
                                        <option value="1">Activo</option>
                                        <option value="0">Inactivo</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <?php if (($_SESSION['nivel'] ?? 1) >= 2): ?>
                            <div class="tab-pane fade" id="pane-accesos" role="tabpanel">
                                <div class="alert alert-info py-2 small border-0 shadow-sm mb-3">
                                    <i class="bi bi-info-circle-fill me-2"></i> Configure qué usuarios tienen acceso a esta bodega y establezca cuál es su bodega predeterminada.
                                </div>
                                <div class="table-responsive" style="max-height: 350px;">
                                    <table class="table table-sm table-hover border">
                                        <thead class="table-light sticky-top">
                                            <tr style="font-size: 0.75rem;">
                                                <th class="text-center" style="width: 80px;">Acceso</th>
                                                <th class="text-center" style="width: 80px;">Default</th>
                                                <th>Usuario</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbodyAccesosUsuarios">
                                            <tr>
                                                <td colspan="3" class="text-center py-4 text-muted">
                                                    <div class="spinner-border spinner-border-sm mb-2"></div>
                                                    <div class="small">Cargando usuarios...</div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer justify-content-between bg-light border-top p-2">
                    <div>
                        <?php if ($perm['eliminar']): ?>
                            <button type="button" id="btnEliminar" class="btn btn-outline-danger btn-sm px-3 d-none" onclick="eliminarBodega()">
                                <i class="bi bi-trash3 me-1"></i> Eliminar
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                            <i class="fa-solid fa-xmark me-1"></i>Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary btn-sm px-4" id="btnGuardar">
                            <i class="bi bi-check2-circle me-1"></i> Guardar
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

        const urlBase = '<?= $urlBaseBodegas ?>';
        const form = document.getElementById('formBodega');
        const inputBuscar = document.getElementById('buscarBodega');
        let modalInst = null;
        let page = <?= $page ?>;
        let totalPages = <?= $totalPages ?>;
        let buscarTimer = null;
        let ordenCol = '<?= $ordenCol ?>';
        let ordenDir = '<?= $ordenDir ?>';

        function getModal() {
            if (!modalInst) modalInst = new bootstrap.Modal(document.getElementById('modalBodega'));
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
                    window.guardarOrdenacionVista('bodegas', ordenCol, ordenDir);
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
                    document.getElementById('tbodyBodegas').innerHTML = json.rows;
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
                console.error('Error cargando bodegas:', e);
            }
        }

        window.abrirModalBodegaCrear = function() {
            form.reset();
            document.getElementById('bodega_id').value = '';
            document.getElementById('tituloModal').textContent = 'Nueva Bodega';
            document.getElementById('btnEliminar')?.classList.add('d-none');

            if (typeof bootstrap !== 'undefined') {
                bootstrap.Tab.getInstance(document.getElementById('tab-general-btn'))?.show() || new bootstrap.Tab(document.getElementById('tab-general-btn')).show();
            }
            resetearInfoExtra();

            const mo = document.getElementById('modalAlert');
            if (mo) mo.classList.add('d-none');

            if (typeof aplicarFavoritosModal === 'function') {
                aplicarFavoritosModal();
            }

            getModal().show();
        };

        window.abrirModalBodegaEditar = function(row) {
            const data = JSON.parse(row.dataset.row);
            form.reset();
            document.getElementById('bodega_id').value = data.id;
            document.getElementById('bodega_nombre').value = data.nombre || '';
            const isInactive = data.status === false || data.status === 'false' || data.status === 0 || data.status === '0' || data.status === 'f';
            document.getElementById('bodega_status').value = isInactive ? '0' : '1';

            document.getElementById('tituloModal').textContent = 'Editar Bodega';
            document.getElementById('btnEliminar')?.classList.remove('d-none');

            if (typeof bootstrap !== 'undefined') {
                bootstrap.Tab.getInstance(document.getElementById('tab-general-btn'))?.show() || new bootstrap.Tab(document.getElementById('tab-general-btn')).show();
            }
            const mo = document.getElementById('modalAlert');
            if (mo) mo.classList.add('d-none');

            fetchInformacionExtra(data.id);
            fetchAccesosUsuarios(data.id);
            getModal().show();
        };

        async function fetchAccesosUsuarios(id) {
            const container = document.getElementById('tbodyAccesosUsuarios');
            if (!container) return;

            container.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm mb-2"></div><div class="small">Cargando usuarios...</div></td></tr>';

            try {
                const resp = await fetch(`${urlBase}/getUsuariosAccesoAjax?id=${id}`);
                const json = await resp.json();

                if (json.ok) {
                    let html = '';
                    if (json.data.length === 0) {
                        html = '<tr><td colspan="3" class="text-center py-4">No hay usuarios disponibles.</td></tr>';
                    } else {
                        json.data.forEach(u => {
                            const checkedAcceso = u.tiene_acceso ? 'checked' : '';
                            const checkedDefault = u.es_default ? 'checked' : '';
                            html += `
                                <tr class="align-middle">
                                    <td class="text-center">
                                        <div class="form-check d-inline-block">
                                            <input class="form-check-input check-acceso" type="checkbox" value="${u.id}" ${checkedAcceso} onchange="validarDefaultInPermiso(this)">
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="form-check d-inline-block">
                                            <input class="form-check-input radio-default" type="radio" name="user_default_${u.id}" data-user-id="${u.id}" ${checkedDefault} ${u.tiene_acceso ? '' : 'disabled'}>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold small">${u.nombre}</div>
                                        <div class="text-muted" style="font-size: 0.65rem;">${u.mail}</div>
                                    </td>
                                </tr>
                            `;
                        });
                    }
                    container.innerHTML = html;
                } else {
                    container.innerHTML = `<tr><td colspan="3" class="text-center py-4 text-danger small">${json.error || 'Error'}</td></tr>`;
                }
            } catch (e) {
                container.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-danger small">Error de conexión</td></tr>';
            }
        }

        window.validarDefaultInPermiso = function(chk) {
            const row = chk.closest('tr');
            if (!row) return;
            const radio = row.querySelector('.radio-default');
            if (!radio) return;
            if (!chk.checked) {
                radio.checked = false;
                radio.disabled = true;
            } else {
                radio.disabled = false;
            }
        };

        async function fetchInformacionExtra(id) {
            resetearInfoExtra('Cargando...');
            try {
                const resp = await fetch(`${urlBase}/getDetalleAjax?id=${id}`);
                const json = await resp.json();
                if (json.ok) {
                    const d = json.data;
                    const countEl = document.getElementById('info_productos_count');
                    if (countEl) countEl.textContent = d.productos_count + (d.productos_count === 1 ? ' producto' : ' productos');

                    fetchHistorialBodega(id);
                } else {
                    resetearInfoExtra('Error al cargar');
                }
            } catch (e) {
                resetearInfoExtra('Error de red');
            }
        }

        function resetearInfoExtra(msg = '-') {
            const countEl = document.getElementById('info_productos_count');
            if (countEl) countEl.textContent = msg === '-' ? 'Sin datos' : msg;

            const timeline = document.getElementById('auditoriaTimelineBodega');
            if (timeline) timeline.innerHTML = '<div class="text-center py-4 text-muted small">No hay historial de cambios.</div>';
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('bodega_id').value;
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
                    const bodegaId = id || json.id;
                    if (bodegaId) {
                        await guardarAccesosUsuarios(bodegaId);
                    }

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

        async function guardarAccesosUsuarios(idBodega) {
            const container = document.getElementById('tbodyAccesosUsuarios');
            if (!container) return;

            const accesos = [];
            container.querySelectorAll('tr.align-middle').forEach(row => {
                const chk = row.querySelector('.check-acceso');
                const rad = row.querySelector('.radio-default');
                if (chk && chk.checked) {
                    accesos.push({
                        id_usuario: chk.value,
                        es_default: rad ? rad.checked : false
                    });
                }
            });

            const fd = new FormData();
            fd.append('id_bodega', idBodega);
            fd.append('accesos', JSON.stringify(accesos));

            try {
                const resp = await fetch(`${urlBase}/guardarAccesosAjax`, {
                    method: 'POST',
                    body: fd
                });
                return await resp.json();
            } catch (e) {
                console.error('Error guardando accesos:', e);
                return {
                    ok: false
                };
            }
        }

        window.eliminarBodega = async function() {
            const id = document.getElementById('bodega_id').value;
            if (!id || !confirm('¿Está seguro de eliminar esta bodega?')) return;

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
                alert('Error al eliminar la bodega');
            }
        };

        async function fetchHistorialBodega(id) {
            const container = document.getElementById('auditoriaTimelineBodega');
            if (!container || !id) return;

            try {
                const resp = await fetch(`${urlBase}/getHistorialAjax?id=${id}&tabla=bodegas`);
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
                                        ${renderDetalleHistorialBodega(log.detalles)}
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

        function renderDetalleHistorialBodega(detalle) {
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