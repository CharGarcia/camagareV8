<?php

/** @var string $titulo */
/** @var array $perm */
/** @var string $rutaModulo */
/** @var array $rows */
/** @var int $total */
/** @var int $page */
/** @var int $totalPages */
/** @var int $perPage */
/** @var string $buscar */
/** @var string $ordenCol */
/** @var string $ordenDir */

$base = BASE_URL;
$urlBaseCC = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');

$rows       = $rows ?? [];
$total      = $total ?? 0;
$page       = $page ?? 1;
$totalPages = $totalPages ?? 1;
$perPage    = $perPage ?? 20;
$ordenCol   = $ordenCol ?? 'nombre';
$ordenDir   = $ordenDir ?? 'asc';
$buscar     = $buscar ?? '';
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>
<style>
    .cc-header { flex-shrink: 0; }
    .cc-scroll { max-height: calc(100vh - 240px); overflow-y: auto; }
    .cc-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
    .cc-row { cursor: pointer; }
    .cc-row:hover { background-color: rgba(0, 0, 0, .04); }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<div class="cc-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-diagram-3"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="abrirModalCrear()"><i class="bi bi-plus-lg"></i> Nuevo</button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <form method="POST" action="<?= $urlBaseCC ?>" class="d-flex align-items-center m-0" onsubmit="event.preventDefault(); fetchSearch(1);">
                <div class="input-group input-group-sm" style="width: 300px;">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="b" id="buscarCC" class="form-control border-start-0 ps-0 shadow-none border" placeholder="Buscar nombre o código..." value="<?= htmlspecialchars($buscar) ?>" autocomplete="off" onkeyup="if(event.key === 'Enter') fetchSearch(1);">
                    <?php if ($buscar !== ''): ?>
                        <a href="<?= $urlBaseCC ?>" class="btn border border-start-0 text-muted" title="Limpiar"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </div>
            </form>

            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'codigo' => 'Código',
                    'nombre' => 'Nombre',
                    'descripcion' => 'Descripción',
                    'estado' => 'Estado'
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>
                
                <a id="btnExportPdf" href="<?= $urlBaseCC ?>/export-pdf?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>" class="btn btn-outline-danger" title="Descargar PDF">
                    <i class="bi bi-file-earmark-pdf"></i> PDF
                </a>
                <a id="btnExportExcel" href="<?= $urlBaseCC ?>/export-excel?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>" class="btn btn-outline-success" title="Descargar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                </a>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="paginationContainer" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" <?= ($page <= 1) ? 'disabled' : '' ?> onclick="cambiarPaginaAjax(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" <?= ($page >= $totalPages) ? 'disabled' : '' ?> onclick="cambiarPaginaAjax(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="cc-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="codigo" onclick="ordenar('codigo')" data-col="codigo">
                            Código <i class="bi <?= $ordenCol === 'codigo' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up text-muted' ?> small ms-1"></i>
                        </th>
                        <th class="sortable-header" role="button" data-sort="nombre" onclick="ordenar('nombre')" data-col="nombre">
                            Nombre <i class="bi <?= $ordenCol === 'nombre' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up text-muted' ?> small ms-1"></i>
                        </th>
                        <th data-col="descripcion">Descripción</th>
                        <th class="text-center pe-3 sortable-header" role="button" data-sort="estado" onclick="ordenar('estado')" data-col="estado">
                            Estado <i class="bi <?= $ordenCol === 'estado' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up text-muted' ?> small ms-1"></i>
                        </th>
                    </tr>
                </thead>
                <tbody id="tbodyCC">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted"><i class="bi bi-diagram-3 fs-3 d-block mb-2"></i>No se encontraron centros de costo.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <tr class="cc-row" role="button" tabindex="0" data-row='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>' onclick="abrirModalEditar(this)">
                                <td class="ps-3" data-col="codigo"><code class="text-secondary"><?= htmlspecialchars($r['codigo'] ?? '-') ?></code></td>
                                <td class="fw-medium" data-col="nombre"><?= htmlspecialchars($r['nombre'] ?? '') ?></td>
                                <td class="text-truncate" style="max-width:300px" data-col="descripcion"><?= htmlspecialchars($r['descripcion'] ?? '-') ?></td>
                                <td class="text-center pe-3" data-col="estado">
                                    <span class="badge <?= ($r['estado'] === 'activo') ? 'bg-success bg-opacity-10 text-success border border-success border-opacity-25' : 'bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25' ?>">
                                        <?= ucfirst($r['estado'] ?? '-') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Centro de Costos -->
<div class="modal fade" id="modalCC" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <form id="formCC" novalidate>
                <div class="modal-header bg-light">
                    <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-diagram-3 text-primary me-2"></i><span id="tituloModal">Nuevo Centro de Costo</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="modalAlert" class="alert d-none mx-3 mt-3 mb-0 py-2 small shadow-sm border-0"></div>
                    <input type="hidden" name="id" id="cc_id" value="">

                    <div class="d-flex align-items-center bg-light px-3 pt-2">
                        <ul class="nav nav-tabs border-bottom-0 flex-grow-1 tab-pestaña" id="tabsCC" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active py-2 small" id="tab-general-btn" data-bs-toggle="tab" href="#tab-general" role="tab">General</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link py-2 small disabled" id="tab-info-btn" data-bs-toggle="tab" href="#tab-info" role="tab">Información</a>
                            </li>
                        </ul>
                    </div>
                    <div class="border-bottom mx-3 mb-3"></div>

                    <div class="tab-content px-3 pb-3">
                        <div class="tab-pane fade show active" id="tab-general" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Código</label>
                                    <input type="text" class="form-control form-control-sm" name="codigo" id="cc_codigo" maxlength="20" placeholder="Opcional">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label small fw-bold">Nombre *</label>
                                    <input type="text" class="form-control form-control-sm" name="nombre" id="cc_nombre" required maxlength="100">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold">Descripción</label>
                                    <textarea class="form-control form-control-sm" name="descripcion" id="cc_descripcion" rows="3" maxlength="500"></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Estado</label>
                                    <div class="form-check form-switch mt-1">
                                        <input class="form-check-input" type="checkbox" role="switch" name="estado" id="cc_estado" value="1" checked>
                                        <label class="form-check-label small" for="cc_estado">Activo</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="tab-info" role="tabpanel">
                            <div class="audit-timeline-container" style="max-height: 300px; overflow-y: auto; padding: 10px;">
                                <div id="auditoriaTimelineCC" class="position-relative">
                                    <div class="text-center py-4 text-muted">
                                        <div class="spinner-border spinner-border-sm mb-2" role="status"></div>
                                        <div class="small">Cargando historial...</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between bg-light border-top p-2">
                    <div>
                        <?php if ($perm['eliminar']): ?>
                            <button type="button" class="btn btn-outline-danger btn-sm px-3 d-none" id="btnEliminar" onclick="eliminarRegistro()">
                                <i class="bi bi-trash3 me-1"></i> Eliminar
                            </button>
                        <?php endif; ?>
                    </div>
                    <div>
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
        const urlBase = '<?= $urlBaseCC ?>';
        const form = document.getElementById('formCC');
        let modalInst = null;
        let currentPage = <?= $page ?>;
        let currentSort = '<?= $ordenCol ?>';
        let currentDir = '<?= $ordenDir ?>';

        function getModal() {
            if (!modalInst) modalInst = new bootstrap.Modal(document.getElementById('modalCC'));
            return modalInst;
        }

        window.abrirModalCrear = function() {
            form.reset();
            document.getElementById('cc_id').value = '';
            document.getElementById('tituloModal').textContent = 'Nuevo Centro de Costo';
            document.getElementById('modalAlert').classList.add('d-none');
            document.getElementById('btnEliminar')?.classList.add('d-none');
            document.getElementById('cc_estado').checked = true;
            document.getElementById('tab-info-btn').classList.add('disabled');
            const tabGen = document.getElementById('tab-general-btn');
            if (tabGen) (bootstrap.Tab.getInstance(tabGen) || new bootstrap.Tab(tabGen)).show();
            getModal().show();
            setTimeout(() => document.getElementById('cc_nombre').focus(), 400);
        };

        window.abrirModalEditar = function(row) {
            const data = JSON.parse(row.dataset.row);
            form.reset();
            document.getElementById('cc_id').value = data.id;
            document.getElementById('cc_codigo').value = data.codigo || '';
            document.getElementById('cc_nombre').value = data.nombre || '';
            document.getElementById('cc_descripcion').value = data.descripcion || '';
            document.getElementById('cc_estado').checked = data.estado === 'activo';
            document.getElementById('tituloModal').textContent = 'Editar Centro de Costo';
            document.getElementById('modalAlert').classList.add('d-none');
            document.getElementById('btnEliminar')?.classList.remove('d-none');
            document.getElementById('tab-info-btn').classList.remove('disabled');
            const tabGen = document.getElementById('tab-general-btn');
            if (tabGen) (bootstrap.Tab.getInstance(tabGen) || new bootstrap.Tab(tabGen)).show();
            
            fetchHistorialCC(data.id);

            getModal().show();
        };

        async function fetchHistorialCC(id) {
            const container = document.getElementById('auditoriaTimelineCC');
            if (!container || !id) return;

            try {
                const resp = await fetch(`${urlBase}/getHistorialAjax?id=${id}&tabla=centro_costos`);
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
                                        ${renderDetalleHistorialCC(log.detalles)}
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    container.innerHTML = html;
                } else {
                    container.innerHTML = '<div class="text-center py-4 text-muted small">No hay historial de cambios.</div>';
                }
            } catch (e) {
                container.innerHTML = '<div class="text-center py-3 text-danger small">Error de carga.</div>';
            }
        }

        function renderDetalleHistorialCC(detalle) {
            if (!detalle || detalle.length === 0) return '<span class="text-muted small">Sin detalles específicos.</span>';
            if (typeof detalle === 'string') return detalle;
            if (Array.isArray(detalle)) {
                return `<ul class="list-unstyled mb-0">
                    ${detalle.map(d => {
                        if (typeof d === 'object') {
                            const antes = d.antes !== null ? `<span class="text-decoration-line-through text-muted">${d.antes}</span> ` : '';
                            return `<li><i class="bi bi-dot"></i> <span class="fw-bold">${d.campo}:</span> ${antes}<i class="bi bi-arrow-right mx-1"></i> ${d.despues}</li>`;
                        }
                        return `<li><i class="bi bi-dot"></i> ${d}</li>`;
                    }).join('')}
                </ul>`;
            }
            return '<span class="text-muted">Acción registrada</span>';
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('btnGuardar');
            const alertEl = document.getElementById('modalAlert');
            const id = document.getElementById('cc_id').value;
            const url = id ? `${urlBase}/update` : `${urlBase}/store`;

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>...';

            try {
                const fd = new FormData(form);
                const resp = await fetch(url, { method: 'POST', body: fd });
                const json = await resp.json();
                if (json.ok) {
                    alertEl.textContent = json.msg;
                    alertEl.className = 'alert alert-success mb-3 py-2 small shadow-sm border-0';
                    alertEl.classList.remove('d-none');
                    setTimeout(() => {
                        getModal().hide();
                        fetchSearch(currentPage);
                    }, 800);
                } else {
                    alertEl.textContent = json.error;
                    alertEl.className = 'alert alert-danger mb-3 py-2 small shadow-sm border-0';
                    alertEl.classList.remove('d-none');
                }
            } catch (err) {
                alertEl.textContent = 'Error de conexión';
                alertEl.classList.remove('d-none');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';
            }
        });

        window.fetchSearch = async function(page = 1) {
            currentPage = page;
            const buscar = document.getElementById('buscarCC').value;
            const url = `${urlBase}/searchAjax?b=${encodeURIComponent(buscar)}&page=${page}&sort=${currentSort}&dir=${currentDir}`;
            try {
                const resp = await fetch(url);
                const json = await resp.json();
                if (json.ok) {
                    document.getElementById('tbodyCC').innerHTML = json.rows;
                    document.getElementById('paginationContainer').innerHTML = json.pagination;
                    document.getElementById('paginationInfo').textContent = json.info;
                    document.getElementById('btnExportPdf').href = json.pdf_url;
                    document.getElementById('btnExportExcel').href = json.excel_url;
                }
            } catch (e) { console.error(e); }
        };

        window.cambiarPaginaAjax = (p) => fetchSearch(p);

        window.ordenar = function(col) {
            if (currentSort === col) {
                currentDir = (currentDir === 'ASC') ? 'DESC' : 'ASC';
            } else {
                currentSort = col;
                currentDir = 'ASC';
            }
            // Update UI sort icons
            document.querySelectorAll('.sortable-header i').forEach(i => {
                i.className = 'bi bi-arrow-down-up text-muted small ms-1';
            });
            const th = document.querySelector(`th[data-sort="${col}"] i`);
            if (th) {
                th.className = (currentDir === 'ASC') ? 'bi bi-sort-alpha-down text-primary small ms-1' : 'bi bi-sort-alpha-up text-primary small ms-1';
            }
            
            if (typeof window.guardarOrdenacionVista === 'function') {
                window.guardarOrdenacionVista('centro-costos', currentSort, currentDir);
            }

            fetchSearch(1);
        };

        window.eliminarRegistro = async function() {
            if (!confirm('¿Seguro que desea eliminar este centro de costo?')) return;
            const id = document.getElementById('cc_id').value;
            try {
                const fd = new FormData();
                fd.append('id_eliminar', id);
                const resp = await fetch(`${urlBase}/delete`, { method: 'POST', body: fd });
                const json = await resp.json();
                if (json.ok) {
                    getModal().hide();
                    fetchSearch(currentPage);
                } else {
                    alert(json.error);
                }
            } catch (e) { alert('Error de conexión'); }
        };
    })();
</script>
