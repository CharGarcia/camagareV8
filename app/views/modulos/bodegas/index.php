<?php

/** @var string $titulo */
/** @var array $perm */
/** @var string $rutaModulo */
/** @var string $rowsHtml */
/** @var string $pieHtml */
/** @var int $total */
/** @var int $page */
/** @var int $totalPages */
/** @var int $perPage */
/** @var string $buscar */

$base = BASE_URL;
$urlBaseBodegas = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');

$total      = $total ?? 0;
$page       = $page ?? 1;
$totalPages = $totalPages ?? 1;
$perPage    = $perPage ?? 25;
$buscar     = $buscar ?? '';
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

    .bodegas-scroll tfoot td {
        position: sticky;
        bottom: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 -1px 0 #dee2e6;
    }

    .bodega-row {
        cursor: pointer;
    }

    .bodega-row:hover {
        background-color: rgba(0, 0, 0, .04);
    }

    .bodega-saldo-stat {
        font-size: .7rem;
    }

    .bodega-saldo-stat .valor {
        font-size: .95rem;
        font-weight: 700;
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
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php
            // Buscador estándar (FiltrosModal), igual que Proveedores: texto libre sobre
            // nombre y establecimiento + embudo con todos los filtros + chips de los
            // activos. Las claves deben existir en los mapas de BodegaRepository::getListado().
            $opcFiltro = $opcionesFiltro ?? [];
            $tB = 'Bodega';
            $opcionesEstablecimiento = array_map(
                fn($e) => ['v' => (string) $e['id'], 'l' => trim(($e['codigo'] ?? '') . ' - ' . ($e['nombre'] ?? ''), ' -')],
                $establecimientos ?? []
            );
            $filtrosBodegas = [
                // ── Bodega ──
                ['tab' => $tB, 'key' => 'nombre',             'label' => 'Nombre',          'icon' => 'bi-box-seam', 'type' => 'text',   'grupo' => 'Bodega', 'col' => 4],
                ['tab' => $tB, 'key' => 'id_establecimiento', 'label' => 'Establecimiento', 'icon' => 'bi-shop',     'type' => 'select', 'grupo' => 'Bodega', 'col' => 4, 'options' => $opcionesEstablecimiento],
                ['tab' => $tB, 'key' => 'estado',             'label' => 'Estado',          'icon' => 'bi-flag',     'type' => 'select', 'grupo' => 'Bodega', 'col' => 4, 'options' => [
                    ['v' => 'activo',   'l' => 'Activo'],
                    ['v' => 'inactivo', 'l' => 'Inactivo'],
                ]],
                // ── Saldo ──
                ['tab' => $tB, 'key' => 'con_stock',   'label' => 'Existencias',           'icon' => 'bi-boxes',          'type' => 'select',       'grupo' => 'Saldo', 'col' => 6, 'options' => [
                    ['v' => 'si', 'l' => 'Con saldo'],
                    ['v' => 'no', 'l' => 'Sin saldo'],
                ]],
                ['tab' => $tB, 'key' => 'unidades',    'label' => 'Unidades',              'icon' => 'bi-123',            'type' => 'number_range', 'grupo' => 'Saldo', 'col' => 6],
                ['tab' => $tB, 'key' => 'valor_costo', 'label' => 'Saldo al costo',        'icon' => 'bi-cash-stack',     'type' => 'number_range', 'grupo' => 'Saldo', 'col' => 6],
                ['tab' => $tB, 'key' => 'valor_venta', 'label' => 'Saldo a precio de venta', 'icon' => 'bi-tag',          'type' => 'number_range', 'grupo' => 'Saldo', 'col' => 6],
                // ── Registro ──
                ['tab' => $tB, 'key' => 'registro', 'label' => 'Fecha de registro',    'icon' => 'bi-calendar-event', 'type' => 'date_range', 'grupo' => 'Registro', 'col' => 6, 'atajos' => true],
                ['tab' => $tB, 'key' => 'usuario',  'label' => 'Usuario que registró', 'icon' => 'bi-person-gear',    'type' => 'select',     'grupo' => 'Registro', 'col' => 6,
                    'options' => array_map(fn($u) => ['v' => (string) $u['id'], 'l' => (string) $u['nombre']], $opcFiltro['usuarios'] ?? [])],
            ];
            ?>
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
            <div id="fmBuscadorBOD"></div>
            <input type="hidden" id="buscarBodega" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosModal) return;
                    new FiltrosModal({
                        containerId: 'fmBuscadorBOD',
                        hiddenInputId: 'buscarBodega',
                        placeholder: 'Buscar en todas las columnas...',
                        titulo: 'Filtros de bodegas',
                        inputWidth: 380,
                        extraId: 'fmExtraBOD', // columnas + PDF + Excel, pegados al final del grupo
                        fields: <?= json_encode($filtrosBodegas, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                        loadingTarget: '#tbodyBodegas',
                        onApply: () => window.fetchSearch && window.fetchSearch(1),
                    }).init();
                });
            </script>

            <?php // FiltrosModal (extraId) mueve estos botones dentro del input-group del buscador; si el JS no corre, quedan aquí. ?>
            <div id="fmExtraBOD" class="btn-group btn-group-sm">
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla ?? [], $vistaConfig ?? [], $rutaModulo) ?>

                <a id="btnExportPdf" href="<?= $urlBaseBodegas ?>/export-pdf?b=<?= urlencode($buscar) ?>&orden=<?= urlencode($ordenParam ?? '') ?>"
                    class="btn btn-outline-danger" title="Descargar PDF">
                    <i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span>
                </a>
                <a id="btnExportExcel" href="<?= $urlBaseBodegas ?>/export-excel?b=<?= urlencode($buscar) ?>&orden=<?= urlencode($ordenParam ?? '') ?>"
                    class="btn btn-outline-success" title="Descargar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i><span class="d-none d-md-inline"> Excel</span>
                </a>
            </div>
        </div>

        <!-- Paginación -->
        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="paginationContainer" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" <?= $page <= 1 ? 'disabled' : '' ?> onclick="cambiarPaginaAjax(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" <?= $page >= $totalPages ? 'disabled' : '' ?> onclick="cambiarPaginaAjax(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card-body p-0">
        <div class="bodegas-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="nombre" data-col="nombre">Nombre <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="establecimiento" data-col="establecimiento">Establecimiento <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-end" role="button" data-sort="productos" data-col="productos" title="Productos con saldo en la bodega">Productos <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-end" role="button" data-sort="unidades" data-col="unidades" title="Suma de las cantidades en existencia">Unidades <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-end" role="button" data-sort="valor_costo" data-col="valor_costo" title="Existencias × último costo del kardex">Saldo al costo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-end" role="button" data-sort="valor_venta" data-col="valor_venta" title="Existencias × precio de venta (sin IVA)">Saldo a precio de venta <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center pe-3 sortable-header" role="button" data-sort="status" data-col="status" style="width: 110px">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyBodegas">
                    <?= $rowsHtml ?? '' ?>
                </tbody>
                <tfoot id="tfootBodegas">
                    <?= $pieHtml ?? '' ?>
                </tfoot>
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
                                    <!-- El valor real (1/0) viaja en el oculto: lo leen la estrella de
                                         favorito y el guardado. El interruptor solo lo refleja. -->
                                    <input type="hidden" name="status" id="bodega_status" value="1">
                                    <div class="form-check form-switch mb-0 d-flex align-items-center" style="min-height:31px">
                                        <input class="form-check-input" type="checkbox" id="bodega_status_switch" checked>
                                        <label class="form-check-label small fw-semibold ms-2" for="bodega_status_switch">
                                            <i class="bi bi-check-circle text-success me-1"></i>Activo
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold d-flex align-items-center">
                                        Establecimiento <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('bodegas', 'bodega_id_establecimiento', 'id_establecimiento') ?>
                                    </label>
                                    <select name="id_establecimiento" id="bodega_id_establecimiento" class="form-select form-select-sm">
                                        <option value="">Sin asignar</option>
                                        <?php foreach (($establecimientos ?? []) as $est): ?>
                                            <option value="<?= (int) $est['id'] ?>">
                                                <?= htmlspecialchars(($est['codigo'] ?? '') . ' - ' . ($est['nombre'] ?? '')) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text small">Local donde está físicamente la bodega. Define cuándo una transferencia de inventario cruza de un establecimiento a otro.</div>
                                </div>

                                <!-- Saldo de la bodega (solo al editar) -->
                                <div class="col-12 d-none" id="bodegaSaldoBox">
                                    <div class="border rounded-3 bg-light p-2">
                                        <div class="small fw-bold text-muted mb-2"><i class="bi bi-boxes me-1"></i>Saldo actual de la bodega</div>
                                        <div class="row g-2 text-center">
                                            <div class="col-6 col-md-3 bodega-saldo-stat">
                                                <div class="valor" id="saldo_productos">0</div>
                                                <div class="text-muted">Productos</div>
                                            </div>
                                            <div class="col-6 col-md-3 bodega-saldo-stat">
                                                <div class="valor" id="saldo_unidades">0.00</div>
                                                <div class="text-muted">Unidades</div>
                                            </div>
                                            <div class="col-6 col-md-3 bodega-saldo-stat">
                                                <div class="valor text-primary" id="saldo_costo">0.00</div>
                                                <div class="text-muted">Al costo</div>
                                            </div>
                                            <div class="col-6 col-md-3 bodega-saldo-stat">
                                                <div class="valor text-success" id="saldo_venta">0.00</div>
                                                <div class="text-muted">A precio de venta</div>
                                            </div>
                                        </div>
                                    </div>
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
        let sorter = null;
        window.currentSorts = <?= $ordenJson ?? '[]' ?>;
        window.currentPage = <?= (int) $page ?>;

        function getModal() {
            if (!modalInst) modalInst = new bootstrap.Modal(document.getElementById('modalBodega'));
            return modalInst;
        }

        const esc = (v) => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const fmt2 = (v) => Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        // ─── Mensajes (SweetAlert) ───────────────────────────────────────────
        function avisoError(texto, titulo = 'No se pudo completar') {
            if (typeof Swal === 'undefined') { alert(texto); return; }
            Swal.fire({ icon: 'error', title: titulo, text: texto, confirmButtonText: 'Aceptar' });
        }
        function avisoOk(texto) {
            if (typeof Swal === 'undefined') return;
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', showConfirmButton: false, timer: 2200, title: texto });
        }

        // ─── Listado ─────────────────────────────────────────────────────────
        window.cambiarPaginaAjax = (n) => window.fetchSearch(n);

        window.fetchSearch = async (page = 1) => {
            const term = inputBuscar ? inputBuscar.value.trim() : '';
            const orden = window.CMG_ordenParam ? window.CMG_ordenParam(window.currentSorts || []) : '';
            const uri = `${urlBase}/searchAjax?b=${encodeURIComponent(term)}&page=${page}&orden=${encodeURIComponent(orden)}`;
            const tbody = document.getElementById('tbodyBodegas');
            if (tbody) tbody.classList.add('fm-cargando-target');
            try {
                const resp = await fetch(uri);
                const data = await resp.json();
                if (data.ok) {
                    window.currentPage = page;
                    tbody.innerHTML = data.rows;
                    document.getElementById('tfootBodegas').innerHTML = data.pie || '';
                    document.getElementById('paginationContainer').innerHTML = data.pagination;
                    document.getElementById('paginationInfo').textContent = data.info;
                    document.getElementById('btnExportPdf').href = data.pdf_url;
                    document.getElementById('btnExportExcel').href = data.excel_url;
                    if (sorter) sorter.refreshIcons();
                } else {
                    avisoError(data.error || 'No se pudo cargar el listado de bodegas.');
                }
            } catch (e) {
                console.error('Error cargando bodegas:', e);
                avisoError('Error de conexión al cargar el listado de bodegas.');
            } finally {
                if (tbody) tbody.classList.remove('fm-cargando-target');
            }
        };

        // Orden de una o varias columnas (Shift+clic), igual que Proveedores.
        if (window.CMG_initSort) {
            sorter = window.CMG_initSort('bodegas', (col, dir, sorts) => {
                window.currentSorts = sorts;
                window.fetchSearch(1);
            }, { sorts: window.currentSorts, multi: true, container: '.bodegas-scroll', reload: false });
        }

        // ─── Interruptor de Estado ───────────────────────────────────────────
        // El valor real va en el oculto #bodega_status (1/0), que es el que leen la
        // estrella de favorito (favoritos.js trabaja con .value) y el guardado. El
        // interruptor #bodega_status_switch solo lo muestra y lo cambia.
        function sincronizarStatusSwitchBodega() {
            const oculto = document.getElementById('bodega_status');
            const sw     = document.getElementById('bodega_status_switch');
            if (oculto && sw) sw.checked = String(oculto.value) !== '0';
        }
        (function initStatusSwitchBodega() {
            const oculto = document.getElementById('bodega_status');
            const sw     = document.getElementById('bodega_status_switch');
            if (!oculto || !sw) return;
            sw.addEventListener('change', () => {
                oculto.value = sw.checked ? '1' : '0';
                oculto.dispatchEvent(new Event('change')); // actualiza la estrella de favorito
            });
            // aplicarFavoritosModal() fija el oculto y dispara 'change'.
            oculto.addEventListener('change', sincronizarStatusSwitchBodega);
        })();
        function fijarStatusBodega(activo) {
            const oculto = document.getElementById('bodega_status');
            if (oculto) oculto.value = activo ? '1' : '0';
            sincronizarStatusSwitchBodega();
        }

        function mostrarPestanaGeneral() {
            const btn = document.getElementById('tab-general-btn');
            if (btn && typeof bootstrap !== 'undefined') bootstrap.Tab.getOrCreateInstance(btn).show();
        }

        window.abrirModalBodegaCrear = function() {
            form.reset();
            // form.reset() no toca los campos ocultos: el estado vuelve a Activo a mano.
            fijarStatusBodega(true);
            document.getElementById('bodega_id').value = '';
            document.getElementById('tituloModal').textContent = 'Nueva Bodega';
            document.getElementById('btnEliminar')?.classList.add('d-none');
            document.getElementById('bodegaSaldoBox').classList.add('d-none');
            mostrarPestanaGeneral();

            if (typeof aplicarFavoritosModal === 'function') {
                aplicarFavoritosModal();
            }
            // Bodega nueva: todos los usuarios con acceso (modelo "lista de denegados").
            fetchAccesosUsuarios(0);
            getModal().show();
        };

        window.abrirModalBodegaEditar = function(row) {
            const data = JSON.parse(row.dataset.row);
            form.reset();
            document.getElementById('bodega_id').value = data.id;
            document.getElementById('bodega_nombre').value = data.nombre || '';
            const isInactive = data.status === false || data.status === 'false' || data.status === 0 || data.status === '0' || data.status === 'f';
            fijarStatusBodega(!isInactive);
            const selEst = document.getElementById('bodega_id_establecimiento');
            if (selEst) selEst.value = data.id_establecimiento ? String(data.id_establecimiento) : '';

            document.getElementById('tituloModal').textContent = 'Editar Bodega';
            document.getElementById('btnEliminar')?.classList.remove('d-none');
            mostrarPestanaGeneral();

            pintarSaldo({
                productos: data.productos,
                unidades: data.unidades,
                valor_costo: data.valor_costo,
                valor_venta: data.valor_venta
            });
            fetchAccesosUsuarios(data.id);
            getModal().show();
        };

        function pintarSaldo(s) {
            document.getElementById('saldo_productos').textContent = Number(s.productos || 0).toLocaleString('en-US');
            document.getElementById('saldo_unidades').textContent = fmt2(s.unidades);
            document.getElementById('saldo_costo').textContent = fmt2(s.valor_costo);
            document.getElementById('saldo_venta').textContent = fmt2(s.valor_venta);
            document.getElementById('bodegaSaldoBox').classList.remove('d-none');
        }

        async function refrescarSaldo(id) {
            try {
                const resp = await fetch(`${urlBase}/getDetalleAjax?id=${id}`);
                const json = await resp.json();
                if (json.ok && json.data && json.data.saldo) pintarSaldo(json.data.saldo);
            } catch (e) {
                console.error('Error cargando saldo de la bodega:', e);
            }
        }

        async function fetchAccesosUsuarios(id) {
            const container = document.getElementById('tbodyAccesosUsuarios');
            if (!container) return;

            if (!id) {
                container.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-muted small">Guarde la bodega para configurar los accesos. Por defecto, todos los usuarios tienen acceso.</td></tr>';
                return;
            }

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
                            const uid = parseInt(u.id, 10);
                            html += `
                                <tr class="align-middle">
                                    <td class="text-center">
                                        <div class="form-check d-inline-block">
                                            <input class="form-check-input check-acceso" type="checkbox" value="${uid}" ${u.tiene_acceso ? 'checked' : ''} onchange="validarDefaultInPermiso(this)">
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="form-check d-inline-block">
                                            <input class="form-check-input radio-default" type="radio" name="user_default_${uid}" data-user-id="${uid}" ${u.es_default ? 'checked' : ''} ${u.tiene_acceso ? '' : 'disabled'}>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold small">${esc(u.nombre)}</div>
                                        <div class="text-muted" style="font-size: 0.65rem;">${esc(u.mail)}</div>
                                    </td>
                                </tr>`;
                        });
                    }
                    container.innerHTML = html;
                } else {
                    container.innerHTML = `<tr><td colspan="3" class="text-center py-4 text-danger small">${esc(json.error || 'Error')}</td></tr>`;
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

        // Bandera "en curso" (§8): un Enter repetido o un doble clic no envían otro guardado.
        let guardandoBodega = false;
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (guardandoBodega) return;
            guardandoBodega = true;
            const id = document.getElementById('bodega_id').value;
            const action = id ? '/update' : '/store';
            const fd = new FormData(form);
            const btn = document.getElementById('btnGuardar');
            const restaurarBtn = () => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar';
            };

            if (!String(fd.get('nombre') || '').trim()) {
                guardandoBodega = false;
                avisoError('Escriba el nombre de la bodega.', 'Falta un dato');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';
            // Aviso mientras el servidor guarda (no se puede cerrar hasta que responda).
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: id ? 'Actualizando bodega...' : 'Guardando bodega...',
                    text: 'Por favor espere, no cierre esta ventana.',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => Swal.showLoading()
                });
            }

            try {
                const resp = await fetch(urlBase + action, { method: 'POST', body: fd });
                const json = await resp.json();

                if (json.ok) {
                    const bodegaId = id || json.id;
                    // El modal se queda abierto. Si era nueva, pasa a modo edición con su id:
                    // así el siguiente Guardar actualiza esta bodega y no crea otra.
                    if (!id && json.id) {
                        document.getElementById('bodega_id').value = json.id;
                        document.getElementById('tituloModal').textContent = 'Editar Bodega';
                        document.getElementById('btnEliminar')?.classList.remove('d-none');
                    }
                    let avisoAccesos = '';
                    if (bodegaId) {
                        // Bodega recién creada: la tabla de accesos aún no estaba cargada,
                        // no hay nada que guardar (todos con acceso por defecto).
                        if (id) {
                            const rAcc = await guardarAccesosUsuarios(bodegaId);
                            if (rAcc && rAcc.ok === false) avisoAccesos = rAcc.error || 'No se pudieron guardar los accesos de usuarios.';
                        }
                        refrescarSaldo(bodegaId);
                        fetchAccesosUsuarios(bodegaId);
                    }
                    window.fetchSearch(window.currentPage || 1);
                    restaurarBtn();
                    if (avisoAccesos) {
                        avisoError(avisoAccesos, 'Bodega guardada, accesos sin guardar');
                    } else {
                        avisoOk(json.msg || 'Guardado correctamente.');
                    }
                } else {
                    avisoError(json.msg || json.error || 'Error desconocido');
                    restaurarBtn();
                }
            } catch (err) {
                avisoError('Error de red al conectar con el servidor.');
                restaurarBtn();
            } finally {
                guardandoBodega = false;
            }
        });

        async function guardarAccesosUsuarios(idBodega) {
            const container = document.getElementById('tbodyAccesosUsuarios');
            if (!container) return null;

            const filas = container.querySelectorAll('tr.align-middle');
            if (filas.length === 0) return null; // la tabla no llegó a cargarse: no tocar accesos

            const accesos = [];
            filas.forEach(row => {
                const chk = row.querySelector('.check-acceso');
                const rad = row.querySelector('.radio-default');
                if (chk && chk.checked) {
                    accesos.push({ id_usuario: chk.value, es_default: rad ? rad.checked : false });
                }
            });

            const fd = new FormData();
            fd.append('id_bodega', idBodega);
            fd.append('accesos', JSON.stringify(accesos));

            try {
                const resp = await fetch(`${urlBase}/guardarAccesosAjax`, { method: 'POST', body: fd });
                return await resp.json();
            } catch (e) {
                console.error('Error guardando accesos:', e);
                return { ok: false, error: 'Error de conexión al guardar los accesos.' };
            }
        }

        window.eliminarBodega = async function() {
            const id = document.getElementById('bodega_id').value;
            if (!id) return;
            const nombre = document.getElementById('bodega_nombre').value || 'esta bodega';

            if (typeof Swal !== 'undefined') {
                const r = await Swal.fire({
                    icon: 'warning',
                    title: '¿Eliminar la bodega?',
                    html: `Se eliminará <b>${esc(nombre)}</b>. Solo se puede eliminar una bodega sin movimientos ni documentos asociados.`,
                    showCancelButton: true,
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#dc3545',
                    focusCancel: true
                });
                if (!r.isConfirmed) return;
                Swal.fire({ title: 'Eliminando bodega...', allowOutsideClick: false, allowEscapeKey: false, showConfirmButton: false, didOpen: () => Swal.showLoading() });
            } else if (!confirm('¿Está seguro de eliminar esta bodega?')) {
                return;
            }

            const fd = new FormData();
            fd.append('id_eliminar', id);

            try {
                const resp = await fetch(urlBase + '/delete', { method: 'POST', body: fd });
                const json = await resp.json();
                if (json.ok) {
                    getModal().hide();
                    window.fetchSearch(window.currentPage || 1);
                    avisoOk(json.msg || 'Bodega eliminada correctamente.');
                } else {
                    avisoError(json.error || 'No se pudo eliminar la bodega.', 'No se puede eliminar');
                }
            } catch (e) {
                avisoError('Error de conexión al eliminar la bodega.');
            }
        };
    })();
</script>
