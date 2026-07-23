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
$urlBaseMesas = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');

$rows       = $rows ?? [];
$total      = $total ?? 0;
$page       = $page ?? 1;
$totalPages = $totalPages ?? 1;
$perPage    = $perPage ?? 20;
$ordenCol   = $ordenCol ?? 'id';
$ordenDir   = $ordenDir ?? 'desc';
$buscar     = $buscar ?? '';

$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>
<style>
    .mesas-header {
        flex-shrink: 0;
    }

    .mesas-scroll {
        max-height: calc(100dvh - 240px);
        overflow-y: auto;
    }

    .mesas-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }

    .mesa-row {
        cursor: pointer;
    }

    .mesa-row:hover {
        background-color: rgba(0, 0, 0, .04);
    }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<div class="mesas-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-grid-3x3"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="abrirModalMesaCrear()"><i class="bi bi-plus-lg"></i> Nueva</button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <form method="POST" action="<?= $urlBaseMesas ?>" class="d-flex align-items-center m-0" onsubmit="event.preventDefault(); fetchSearch(1);">
                <div class="input-group input-group-sm" style="width: 250px;">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="b" id="buscarMesa" class="form-control border-start-0 ps-0 shadow-none border" placeholder="Buscar mesa..." value="<?= htmlspecialchars($buscar) ?>" autocomplete="off">
                </div>
            </form>

            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'nombre' => 'Nombre',
                    'ubicacion' => 'Ubicación',
                    'estado' => 'Estado',
                    'created_at' => 'Registro'
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>
                
                <a id="btnExportPdf" href="<?= $urlBaseMesas ?>/export-pdf?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>" class="btn btn-outline-danger"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                <a id="btnExportExcel" href="<?= $urlBaseMesas ?>/export-excel?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>" class="btn btn-outline-success"><i class="bi bi-file-earmark-spreadsheet"></i> Excel</a>
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
        <div class="mesas-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="nombre" data-col="nombre">Nombre <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center" data-col="ubicacion">Ubicación</th>
                        <th class="text-center sortable-header" role="button" data-sort="estado" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" role="button" data-sort="created_at" data-col="created_at">Registro <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyMesas">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-5 text-muted"><i class="bi bi-grid-3x3 fs-3 d-block mb-2"></i>No se encontraron mesas.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <tr class="mesa-row" role="button" tabindex="0" data-row='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>' onclick="abrirModalMesaEditar(this)">
                                <td class="ps-3 fw-medium" data-col="nombre"><?= htmlspecialchars($r['nombre'] ?? '') ?></td>
                                <td class="text-center" data-col="ubicacion"><?= htmlspecialchars($r['ubicacion'] ?? '') ?></td>
                                <td class="text-center" data-col="estado">
                                    <?php if (($r['estado'] ?? 'disponible') === 'disponible'): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Disponible</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">Ocupada</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center" data-col="created_at"><?= htmlspecialchars($r['created_at'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Mesa -->
<div class="modal fade" id="modalMesa" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="<?= $urlBaseMesas ?>/store" id="formMesa" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-grid-3x3-gap-fill me-2 text-primary"></i> <span id="tituloModal">Nueva Mesa</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body pb-0">
                    <input type="hidden" name="id" id="mesa_id" value="">

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small fw-bold">Nombre de la Mesa *</label>
                            <input type="text" class="form-control form-control-sm" name="nombre" id="mesa_nombre" required maxlength="100" placeholder="Ej. Mesa 01">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Ubicación</label>
                            <input type="text" class="form-control form-control-sm" name="ubicacion" id="mesa_ubicacion" maxlength="60" placeholder="Ej. Piso 1, Piso 2, Terraza..." list="mesa_ubicaciones_sugeridas">
                            <datalist id="mesa_ubicaciones_sugeridas">
                                <option value="Piso 1">
                                <option value="Piso 2">
                                <option value="Terraza">
                                <option value="Salón principal">
                            </datalist>
                            <div class="form-text mt-0" style="font-size:0.65rem;">Agrupa las mesas por pestañas en el tablero (modulos/mesas/tablero).</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold d-flex align-items-center">Estado <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('mesas', 'mesa_estado', 'estado') ?></label>
                            <select class="form-select form-select-sm" name="estado" id="mesa_estado">
                                <option value="disponible">Disponible</option>
                                <option value="ocupada">Ocupada</option>
                                <option value="mantenimiento">Mantenimiento</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between bg-light">
                    <div>
                        <?php if ($perm['eliminar']): ?>
                            <button type="button" class="btn btn-outline-danger btn-sm d-none" id="btnEliminar" onclick="eliminarMesa()">
                                <i class="bi bi-trash"></i> Eliminar
                            </button>
                        <?php endif; ?>
                    </div>
                    <div>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm" id="btnGuardar"><i class="bi bi-check-lg"></i> Guardar</button>
                    </div>
                </div>
            </form>
            <form id="formEliminar" method="POST" action="<?= $urlBaseMesas ?>/delete" class="d-none">
                <input type="hidden" name="id_eliminar" id="id_eliminar_input">
            </form>
        </div>
    </div>
</div>

<script>
    (function() {
        'use strict';
        const urlBase = '<?= $urlBaseMesas ?>';
        const form = document.getElementById('formMesa');
        let modalInst = null;

        function getModal() {
            if (!modalInst && typeof bootstrap !== 'undefined') {
                modalInst = new bootstrap.Modal(document.getElementById('modalMesa'));
            }
            return modalInst;
        }

        function swalToast(icon, title) {
            Swal.fire({ toast: true, position: 'top-end', icon, title, showConfirmButton: false, timer: 2500, timerProgressBar: true });
        }
        function swalError(html) {
            Swal.fire({ icon: 'error', title: 'Error', html, confirmButtonColor: '#0d6efd', confirmButtonText: 'Aceptar' });
        }

        window.abrirModalMesaCrear = function() {
            form.reset();
            document.getElementById('mesa_id').value = '';
            document.getElementById('tituloModal').textContent = 'Nueva Mesa';
            document.getElementById('btnEliminar')?.classList.add('d-none');

            if (typeof aplicarFavoritosModal === 'function') {
                aplicarFavoritosModal();
            }

            getModal()?.show();
            setTimeout(() => document.getElementById('mesa_nombre')?.focus(), 500);
        };

        window.abrirModalMesaEditar = function(row) {
            const data = JSON.parse(row.dataset.row);
            form.reset();
            document.getElementById('mesa_id').value = data.id;
            document.getElementById('mesa_nombre').value = data.nombre || '';
            document.getElementById('mesa_ubicacion').value = data.ubicacion || '';
            document.getElementById('mesa_estado').value = data.estado || 'disponible';

            document.getElementById('tituloModal').textContent = 'Editar Mesa';
            document.getElementById('btnEliminar')?.classList.remove('d-none');

            getModal()?.show();
        };

        if (form) {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const btnSave = document.getElementById('btnGuardar');
                const actionUrl = document.getElementById('mesa_id').value ? `${urlBase}/update` : `${urlBase}/store`;

                btnSave.disabled = true;
                btnSave.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';

                try {
                    const fd = new FormData(form);
                    const resp = await fetch(actionUrl, {
                        method: 'POST',
                        body: fd
                    });
                    const json = await resp.json();
                    if (json.ok) {
                        swalToast('success', json.msg || 'Guardado correctamente.');
                        getModal()?.hide();
                        fetchSearch(window.currentPage || 1);
                    } else {
                        swalError(json.error || 'No se pudo guardar la mesa.');
                        btnSave.disabled = false;
                        btnSave.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';
                    }
                } catch (err) {
                    swalError('Error de conexión al guardar.');
                    btnSave.disabled = false;
                    btnSave.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';
                }
            });
        }

        window.eliminarMesa = async function() {
            const id = document.getElementById('mesa_id').value;
            if (!id) return;

            const { isConfirmed } = await Swal.fire({
                title: '¿Eliminar esta mesa?',
                text: 'Esta acción no se puede deshacer.',
                icon: 'warning', showCancelButton: true,
                confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545',
            });
            if (!isConfirmed) return;

            const btnDlt = document.getElementById('btnEliminar');
            btnDlt.disabled = true;
            try {
                const fd = new FormData();
                fd.append('id_eliminar', id);
                const resp = await fetch(`${urlBase}/delete`, {
                    method: 'POST',
                    body: fd
                });
                const json = await resp.json();
                if (json.ok) {
                    swalToast('success', json.msg || 'Mesa eliminada correctamente.');
                    getModal()?.hide();
                    fetchSearch(window.currentPage || 1);
                } else {
                    swalError(json.error || 'No se pudo eliminar la mesa.');
                    btnDlt.disabled = false;
                }
            } catch (err) {
                swalError('Error de conexión al eliminar.');
                btnDlt.disabled = false;
            }
        };

        const inputBuscar = document.getElementById('buscarMesa');
        window.currentSort = 'id';
        window.currentDir = 'desc';
        window.currentPage = 1;

        window.fetchSearch = async (page = 1) => {
            const term = inputBuscar ? inputBuscar.value.trim() : '';
            const url = `${urlBase}/searchAjax?b=${encodeURIComponent(term)}&page=${page}&sort=${window.currentSort}&dir=${window.currentDir}`;
            try {
                const resp = await fetch(url);
                const data = await resp.json();
                if (data.ok) {
                    window.currentPage = page;
                    document.getElementById('tbodyMesas').innerHTML = data.rows;
                    document.getElementById('paginationContainer').innerHTML = data.pagination;
                    document.getElementById('paginationInfo').textContent = data.info;
                    document.querySelectorAll('.sortable-header').forEach(th => {
                        const icon = th.querySelector('i');
                        if (th.dataset.sort === window.currentSort) {
                            icon.className = (window.currentDir.toLowerCase() === 'asc') ? 'bi bi-sort-alpha-down text-primary ms-1' : 'bi bi-sort-alpha-up text-primary ms-1';
                        } else {
                            icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
                        }
                    });
                }
            } catch (err) {}
        };

        window.cambiarPaginaAjax = function(n) {
            window.fetchSearch(n);
        };

        document.querySelectorAll('.sortable-header').forEach(header => {
            header.addEventListener('click', () => {
                const sortField = header.dataset.sort;
                window.currentDir = (window.currentSort === sortField && window.currentDir.toLowerCase() === 'asc') ? 'DESC' : 'ASC';
                window.currentSort = sortField;
                
                if (typeof window.guardarOrdenacionVista === 'function') {
                    window.guardarOrdenacionVista('mesas', window.currentSort, window.currentDir);
                }

                fetchSearch(1);
            });
        });

        let timerId;
        if (inputBuscar) {
            inputBuscar.addEventListener('input', () => {
                clearTimeout(timerId);
                timerId = setTimeout(() => fetchSearch(1), 400);
            });
        }
    })();
</script>