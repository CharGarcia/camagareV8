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
$urlBasePeriodos = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');

$rows       = $rows       ?? [];
$total      = $total      ?? 0;
$page       = $page       ?? 1;
$totalPages = $totalPages ?? 1;
$perPage    = $perPage    ?? 25;
$ordenCol   = $ordenCol   ?? 'fecha_inicial';
$ordenDir   = $ordenDir   ?? 'DESC';
$buscar     = $buscar     ?? '';
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>
<style>
    .periodos-header {
        flex-shrink: 0;
    }

    .periodos-scroll {
        max-height: calc(100dvh - 240px);
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
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php
            // Buscador estándar (FiltrosModal): texto libre sobre el nombre y las fechas del
            // listado + botón embudo con todos los filtros + chips de los activos. Las claves
            // (key) deben existir en los mapas de PeriodosContablesRepository::getListado().
            $opcFiltro = $opcionesFiltro ?? [];
            $opcionesUsuario = array_map(fn($u) => ['v' => (string) $u['id'], 'l' => (string) $u['nombre']], $opcFiltro['usuarios'] ?? []);
            $tP = 'Período';
            $filtrosPeriodos = [
                // ── Período ──
                ['tab' => $tP, 'key' => 'nombre', 'label' => 'Nombre',  'icon' => 'bi-tag',      'type' => 'text',         'grupo' => 'Período', 'col' => 4],
                ['tab' => $tP, 'key' => 'anio',   'label' => 'Año',     'icon' => 'bi-calendar', 'type' => 'number_range', 'grupo' => 'Período', 'col' => 4],
                ['tab' => $tP, 'key' => 'estado', 'label' => 'Estado',  'icon' => 'bi-flag',     'type' => 'select',       'grupo' => 'Período', 'col' => 4, 'options' => [
                    ['v' => 'abierto', 'l' => 'Abierto'],
                    ['v' => 'cerrado', 'l' => 'Cerrado'],
                ]],
                // ── Fechas ──
                ['tab' => $tP, 'key' => 'fecha_inicial', 'label' => 'Fecha inicial', 'icon' => 'bi-calendar-event', 'type' => 'date_range', 'grupo' => 'Fechas', 'col' => 6, 'atajos' => true],
                ['tab' => $tP, 'key' => 'fecha_final',   'label' => 'Fecha final',   'icon' => 'bi-calendar-check', 'type' => 'date_range', 'grupo' => 'Fechas', 'col' => 6, 'atajos' => true],
                // ── Registro ──
                ['tab' => $tP, 'key' => 'registro', 'label' => 'Fecha de registro',    'icon' => 'bi-calendar-plus', 'type' => 'date_range', 'grupo' => 'Registro', 'col' => 6, 'atajos' => true],
                ['tab' => $tP, 'key' => 'usuario',  'label' => 'Usuario que registró', 'icon' => 'bi-person-gear',   'type' => 'select',     'grupo' => 'Registro', 'col' => 6, 'options' => $opcionesUsuario],
            ];
            ?>
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
            <div id="fmBuscadorPER"></div>
            <input type="hidden" id="buscarPeriodo" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosModal) return;
                    new FiltrosModal({
                        containerId: 'fmBuscadorPER',
                        hiddenInputId: 'buscarPeriodo',
                        placeholder: 'Buscar en todas las columnas...',
                        titulo: 'Filtros de períodos contables',
                        inputWidth: 420,
                        extraId: 'fmExtraPER',   // columnas + PDF + Excel, pegados al final del grupo
                        fields: <?= json_encode($filtrosPeriodos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                        loadingTarget: '#tbodyPeriodos',   // se atenúa mientras se busca
                        onApply: () => window.fetchSearch && window.fetchSearch(1),
                    }).init();
                });
            </script>

            <?php // FiltrosModal (extraId) mueve estos botones dentro del input-group del buscador; si el JS no corre, quedan aquí. ?>
            <div id="fmExtraPER" class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'nombre'        => 'Nombre',
                    'fecha_inicial' => 'Fecha Inicial',
                    'fecha_final'   => 'Fecha Final',
                    'status'        => 'Estado'
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>

                <a id="btnExportPdf" href="<?= $urlBasePeriodos ?>/export-pdf?b=<?= urlencode($buscar) ?>&orden=<?= urlencode($ordenParam ?? '') ?>"
                    class="btn btn-outline-danger" title="Descargar PDF">
                    <i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span>
                </a>
                <a id="btnExportExcel" href="<?= $urlBasePeriodos ?>/export-excel?b=<?= urlencode($buscar) ?>&orden=<?= urlencode($ordenParam ?? '') ?>"
                    class="btn btn-outline-success" title="Descargar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i><span class="d-none d-md-inline"> Excel</span>
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
                        <th class="ps-3 sortable-header" role="button" data-sort="nombre" data-col="nombre">Nombre <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="fecha_inicial" data-col="fecha_inicial">Fecha Inicial <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="fecha_final" data-col="fecha_final">Fecha Final <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center pe-3 sortable-header" role="button" data-sort="status" data-col="status" style="width: 120px">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyPeriodos">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-5 text-muted"><i class="bi bi-calendar-x fs-3 d-block mb-2"></i>No se encontraron periodos contables.</td>
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
        <div class="modal-content shadow-lg border-0">
            <form id="formPeriodo" novalidate>
                <div class="modal-header bg-light py-3">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-calendar-range text-primary me-2"></i>
                        <span id="tituloModal">Nuevo Periodo Contable</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-0">
                    <input type="hidden" name="id" id="periodo_id">

                    <!-- Pestañas -->
                    <div class="d-flex align-items-center bg-light px-3 pt-2">
                        <ul class="nav nav-tabs border-bottom-0 flex-grow-1 flex-nowrap" id="modalPeriodoTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="tab-general-btn" data-bs-toggle="tab" data-bs-target="#pane-general" type="button" role="tab">
                                    <i class="bi bi-card-text me-1"></i> General
                                </button>
                            </li>
                        </ul>
                    </div>

                    <div class="tab-content p-3 border-top">
                        <div class="tab-pane fade show active" id="pane-general" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label for="periodo_nombre" class="form-label small fw-bold">Nombre del Periodo *</label>
                                    <input type="text" name="nombre" id="periodo_nombre" class="form-control form-control-sm" required maxlength="100" autocomplete="off" placeholder="Ej: Ejercicio 2026">
                                </div>
                                <div class="col-md-6">
                                    <label for="periodo_fecha_inicial" class="form-label small fw-bold">Fecha Inicial *</label>
                                    <input type="date" name="fecha_inicial" id="periodo_fecha_inicial" class="form-control form-control-sm" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="periodo_fecha_final" class="form-label small fw-bold">Fecha Final *</label>
                                    <input type="date" name="fecha_final" id="periodo_fecha_final" class="form-control form-control-sm" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="periodo_status" class="form-label small fw-bold">Estado</label>
                                    <select name="status" id="periodo_status" class="form-select form-select-sm">
                                        <option value="1">Abierto</option>
                                        <option value="0">Cerrado</option>
                                    </select>
                                </div>
                                <div class="col-md-12 d-none" id="grupoMotivoReapertura">
                                    <label for="periodo_motivo_reapertura" class="form-label small fw-bold">Motivo de la reapertura *</label>
                                    <input type="text" name="motivo_reapertura" id="periodo_motivo_reapertura" class="form-control form-control-sm" maxlength="500" autocomplete="off" placeholder="Por qué se reabre este período">
                                    <div class="form-text small">Reabrir un período ya cerrado permite volver a modificar documentos de esas fechas. Queda registrado en el log del sistema con este motivo. Solo puede hacerlo un usuario con acceso total.</div>
                                </div>
                                <div class="col-md-12 d-none" id="notaPeriodoCerrado">
                                    <div class="small text-muted"><i class="bi bi-lock me-1"></i>Período cerrado: sus fechas no se pueden cambiar ni se puede eliminar. Para hacerlo, primero reábralo.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between bg-light border-top p-2">
                    <div>
                        <?php if ($perm['eliminar']): ?>
                            <button type="button" id="btnEliminar" class="btn btn-outline-danger btn-sm px-3 d-none" onclick="eliminarPeriodo()">
                                <i class="bi bi-trash3 me-1"></i> Eliminar
                            </button>
                        <?php endif; ?>
                    </div>
                    <div>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                            <i class="fa-solid fa-xmark me-1"></i>Cerrar
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

        const urlBase = '<?= $urlBasePeriodos ?>';
        const form = document.getElementById('formPeriodo');
        const inputBuscar = document.getElementById('buscarPeriodo');
        const HTML_GUARDAR = '<i class="bi bi-check2-circle me-1"></i> Guardar';
        const HTML_ELIMINAR = '<i class="bi bi-trash3 me-1"></i> Eliminar';
        window.currentSort = '<?= htmlspecialchars($ordenCol, ENT_QUOTES) ?>';
        window.currentDir = '<?= htmlspecialchars($ordenDir, ENT_QUOTES) ?>';
        // Orden múltiple (Shift+clic): lista completa de criterios, en el formato que lee
        // OrdenListado en PHP. currentSort/currentDir quedan como el principal.
        window.currentSorts = <?= $ordenJson ?? '[]' ?>;
        window.currentPage = <?= (int) $page ?>;
        let sorter = null;
        let modalInst = null;
        let guardando = false;

        function getModal() {
            if (!modalInst) modalInst = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalPeriodo'));
            return modalInst;
        }

        // ── Mensajes (SweetAlert) ──
        function avisoOk(texto) {
            if (window.Swal) {
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: texto, timer: 2200, showConfirmButton: false });
            }
        }

        function avisoError(texto, titulo = 'Atención') {
            if (window.Swal) Swal.fire({ icon: 'error', title: titulo, text: texto || 'Ocurrió un error.' });
            else alert(texto);
        }

        // ── Listado ──
        let timerId;

        function debounce(func, delay = 350) {
            return (...args) => {
                clearTimeout(timerId);
                timerId = setTimeout(() => func.apply(this, args), delay);
            };
        }

        window.cambiarPaginaAjax = (n) => window.fetchSearch(n);

        window.fetchSearch = async (page = 1) => {
            const term = inputBuscar ? inputBuscar.value.trim() : '';
            const orden = window.CMG_ordenParam(window.currentSorts || []);
            const uri = `${urlBase}/searchAjax?b=${encodeURIComponent(term)}&page=${page}&orden=${encodeURIComponent(orden)}`;
            // Mismo indicador que el buscador (FiltrosModal): la tabla se atenúa mientras carga.
            const tbody = document.getElementById('tbodyPeriodos');
            if (tbody) tbody.classList.add('fm-cargando-target');
            try {
                const resp = await fetch(uri);
                const data = await resp.json();
                if (data.ok) {
                    window.currentPage = page;
                    tbody.innerHTML = data.rows;
                    document.getElementById('paginationContainer').innerHTML = data.pagination;
                    document.getElementById('paginationInfo').textContent = data.info;
                    document.getElementById('btnExportPdf').href = data.pdf_url;
                    document.getElementById('btnExportExcel').href = data.excel_url;
                    if (sorter) sorter.refreshIcons();
                }
            } catch (e) {
                console.error('Error cargando periodos:', e);
            } finally {
                if (tbody) tbody.classList.remove('fm-cargando-target');
            }
        };

        // multi: clic normal ordena por una columna; Shift+clic encadena hasta 3.
        // reload:false porque fetchSearch repinta todo lo que depende del orden.
        sorter = window.CMG_initSort('periodos_contables', (col, dir, sorts) => {
            window.currentSort = col;
            window.currentDir = dir;
            window.currentSorts = sorts;
            fetchSearch(1);
        }, { sorts: window.currentSorts, multi: true, container: '.periodos-scroll', reload: false });

        if (inputBuscar) inputBuscar.addEventListener('input', debounce(() => fetchSearch(1), 400));

        // ── Modal ──
        // Estado con que se abrió el período: un período cerrado no mueve sus fechas ni se
        // elimina, y para reabrirlo hay que dar el motivo (el servidor valida lo mismo).
        let estabaCerrado = false;
        const selStatus = document.getElementById('periodo_status');

        function actualizarControlesCierre() {
            const reabriendo = estabaCerrado && selStatus.value === '1';
            const sigueCerrado = estabaCerrado && selStatus.value === '0';
            document.getElementById('grupoMotivoReapertura').classList.toggle('d-none', !reabriendo);
            document.getElementById('periodo_motivo_reapertura').required = reabriendo;
            document.getElementById('notaPeriodoCerrado').classList.toggle('d-none', !sigueCerrado);
            document.getElementById('periodo_fecha_inicial').readOnly = sigueCerrado;
            document.getElementById('periodo_fecha_final').readOnly = sigueCerrado;
            document.getElementById('btnEliminar')?.classList.toggle('d-none', !document.getElementById('periodo_id').value || estabaCerrado);
        }
        selStatus.addEventListener('change', actualizarControlesCierre);

        function prepararModal(titulo) {
            document.getElementById('tituloModal').textContent = titulo;
            actualizarControlesCierre();
            bootstrap.Tab.getOrCreateInstance(document.getElementById('tab-general-btn')).show();
            const btn = document.getElementById('btnGuardar');
            btn.disabled = false;
            btn.innerHTML = HTML_GUARDAR;
            getModal().show();
        }

        window.abrirModalPeriodoCrear = function() {
            form.reset();
            document.getElementById('periodo_id').value = '';
            estabaCerrado = false;
            prepararModal('Nuevo Periodo Contable');
        };

        window.abrirModalPeriodoEditar = function(row) {
            const data = JSON.parse(row.dataset.row);
            form.reset();
            document.getElementById('periodo_id').value = data.id;
            document.getElementById('periodo_nombre').value = data.nombre || '';
            if (data.fecha_inicial) document.getElementById('periodo_fecha_inicial').value = data.fecha_inicial.split(' ')[0];
            if (data.fecha_final) document.getElementById('periodo_fecha_final').value = data.fecha_final.split(' ')[0];

            const cerrado = data.status === false || data.status === 'false' || data.status === 0 || data.status === '0' || data.status === 'f';
            selStatus.value = cerrado ? '0' : '1';
            estabaCerrado = cerrado;
            prepararModal('Editar Periodo Contable');
        };

        function validarFormulario() {
            const nombre = document.getElementById('periodo_nombre').value.trim();
            const fi = document.getElementById('periodo_fecha_inicial').value;
            const ff = document.getElementById('periodo_fecha_final').value;
            if (!nombre) return 'Ingrese el nombre del período.';
            if (!fi || !ff) return 'Ingrese la fecha inicial y la fecha final.';
            if (fi > ff) return 'La fecha inicial no puede ser posterior a la fecha final.';
            const motivo = document.getElementById('periodo_motivo_reapertura');
            if (motivo.required && !motivo.value.trim()) return 'Indique el motivo de la reapertura.';
            return '';
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (guardando) return;

            const error = validarFormulario();
            if (error) {
                avisoError(error);
                return;
            }

            guardando = true;
            const id = document.getElementById('periodo_id').value;
            const btn = document.getElementById('btnGuardar');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';

            let cerrarModal = false;
            try {
                const resp = await fetch(urlBase + (id ? '/update' : '/store'), { method: 'POST', body: new FormData(form) });
                const json = await resp.json();
                if (json.ok) {
                    // Tras crear, el botón no se reactiva: el modal se cierra y un nuevo
                    // período se abre con «Nuevo» (un guardado = un registro).
                    cerrarModal = true;
                    getModal().hide();
                    fetchSearch(window.currentPage || 1);
                    avisoOk(json.msg || 'Guardado correctamente');
                } else {
                    avisoError(json.msg || json.error || 'Error al guardar');
                }
            } catch (err) {
                avisoError('No se pudo comunicar con el servidor', 'Error de red');
            } finally {
                guardando = false;
                if (!cerrarModal) {
                    btn.disabled = false;
                    btn.innerHTML = HTML_GUARDAR;
                }
            }
        });

        window.eliminarPeriodo = async function() {
            const id = document.getElementById('periodo_id').value;
            if (!id) return;

            const nombre = document.getElementById('periodo_nombre').value.trim();
            const result = await Swal.fire({
                title: '¿Está seguro?',
                text: `Se realizará una eliminación lógica del período${nombre ? ' «' + nombre + '»' : ''}.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            });
            if (!result.isConfirmed) return;

            const btnDlt = document.getElementById('btnEliminar');
            btnDlt.disabled = true;
            btnDlt.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Eliminando...';

            try {
                const fd = new FormData();
                fd.append('id_eliminar', id);
                const resp = await fetch(urlBase + '/delete', { method: 'POST', body: fd });
                const json = await resp.json();
                if (json.ok) {
                    getModal().hide();
                    fetchSearch(window.currentPage || 1);
                    Swal.fire({ icon: 'success', title: 'Eliminado', text: json.msg || 'Periodo contable eliminado correctamente.', timer: 1500, showConfirmButton: false });
                } else {
                    avisoError(json.msg || json.error || 'No se pudo eliminar el período.');
                }
            } catch (e) {
                avisoError('No se pudo comunicar con el servidor', 'Error de red');
            } finally {
                btnDlt.disabled = false;
                btnDlt.innerHTML = HTML_ELIMINAR;
            }
        };
    })();
</script>
