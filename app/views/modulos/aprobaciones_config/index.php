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
/** @var array $disponibles   Checkpoints del catálogo aún no configurados */
/** @var array $usuarios      Usuarios asignados a la empresa */
/** @var array $vistaConfig */

use App\controllers\modulos\AprobacionesConfigController;

$base       = BASE_URL;
$urlBaseApr = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');

$rows        = $rows ?? [];
$total       = $total ?? 0;
$page        = $page ?? 1;
$totalPages  = $totalPages ?? 1;
$perPage     = $perPage ?? 20;
$ordenCol    = $ordenCol ?? 'modulo';
$ordenDir    = $ordenDir ?? 'asc';
$buscar      = $buscar ?? '';
$disponibles = $disponibles ?? [];
$usuarios    = $usuarios ?? [];

$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;

// El render de cada fila vive en el controlador (métodos estáticos) para que la
// carga inicial y el searchAjax pinten exactamente lo mismo.
$puedeAbrir = !empty($perm['actualizar']) || !empty($perm['eliminar']);
?>
<style>
    .apr-header {
        flex-shrink: 0;
    }

    .aprobaciones-scroll {
        max-height: calc(100dvh - 240px);
        overflow-y: auto;
    }

    .aprobaciones-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }

    .aprobacion-row {
        cursor: pointer;
    }

    .aprobacion-row:hover {
        background-color: rgba(0, 0, 0, .04);
    }

    /* Campo de aprobadores del modal: se ve como un input y contiene los chips. */
    .apr-chips-wrap { min-height: 34px; cursor: text; }
    .apr-dropdown { z-index: 5090; max-height: 220px; overflow: auto; }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<div class="apr-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-check2-square"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if (!empty($perm['crear'])): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" id="apr-btn-nueva"><i class="bi bi-plus-lg"></i> Nueva</button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <!-- Buscador y Exportación -->
        <div class="d-flex align-items-center gap-2">
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_busqueda.css?v=<?= time() ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_busqueda.js?v=<?= time() ?>"></script>
            <div id="fbBuscadorAPR" style="width: 420px;"></div>
            <input type="hidden" id="buscarAprobacion" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosBusqueda) return;
                    new FiltrosBusqueda({
                        containerId: 'fbBuscadorAPR',
                        hiddenInputId: 'buscarAprobacion',
                        fields: [
                            { key: 'proceso',   label: 'Proceso',    icon: 'bi-diagram-3', type: 'text' },
                            { key: 'modulo',    label: 'Módulo',     icon: 'bi-grid',      type: 'text' },
                            { key: 'aprobador', label: 'Aprobador',  icon: 'bi-person-check', type: 'text' },
                            { key: 'monto',     label: 'Monto mínimo', icon: 'bi-cash',    type: 'number_range' },
                            { key: 'estado',    label: 'Estado',     icon: 'bi-flag',      type: 'select', options: [
                                { v: 'activa',   l: 'Activa' },
                                { v: 'inactiva', l: 'Inactiva' },
                            ]},
                        ],
                        quickFilters: [
                            { id: 'qf_activa',    label: 'Activas',      mk: () => ({ key: 'estado', op: '=', value: 'activa',   display: 'Activa' }) },
                            { id: 'qf_inactiva',  label: 'Inactivas',    mk: () => ({ key: 'estado', op: '=', value: 'inactiva', display: 'Inactiva' }) },
                            { id: 'qf_con_monto', label: 'Con monto mínimo', mk: () => ({ key: 'monto', op: '>', value: '0', display: '> 0' }) },
                        ],
                        onApply: () => window.APR_fetchSearch && window.APR_fetchSearch(1),
                    }).init();
                });
            </script>

            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'modulo'      => 'Módulo',
                    'proceso'     => 'Proceso',
                    'aprobadores' => 'Aprobadores',
                    'umbral'      => 'Monto mínimo',
                    'estado'      => 'Estado',
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>

                <a id="btnExportPdf" href="<?= $urlBaseApr ?>/export-pdf?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                    class="btn btn-outline-danger" title="Descargar PDF">
                    <i class="bi bi-file-earmark-pdf"></i> PDF
                </a>
                <a id="btnExportExcel" href="<?= $urlBaseApr ?>/export-excel?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                    class="btn btn-outline-success" title="Descargar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                </a>
            </div>
        </div>

        <!-- Paginación -->
        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="paginationContainer" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" <?= $page <= 1 ? 'disabled' : '' ?> onclick="APR_cambiarPagina(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" <?= $page >= $totalPages ? 'disabled' : '' ?> onclick="APR_cambiarPagina(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card-body p-0">
        <div class="aprobaciones-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="modulo" data-col="modulo">Módulo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="proceso" data-col="proceso">Proceso <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="aprobadores" data-col="aprobadores">Aprobadores <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-end" role="button" data-sort="umbral" data-col="umbral">Monto mínimo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-center pe-3" role="button" data-sort="estado" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyAprobaciones">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="bi bi-check2-square fs-3 d-block mb-2"></i>
                                <?= $buscar !== '' ? 'No se encontraron aprobaciones.' : 'Todavía no hay aprobaciones configuradas en esta empresa.' ?>
                                <?php if ($buscar === '' && !empty($perm['crear'])): ?>
                                    <div class="small mt-1">Usa <strong>Nueva</strong> para elegir un proceso y sus aprobadores.</div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?= AprobacionesConfigController::filaHtml($r, $puedeAbrir) ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Nueva / Editar aprobación -->
<div class="modal fade" id="modalAprobacion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-white border-bottom py-2 px-3">
                <h6 class="modal-title fw-bold" id="apr-modal-titulo">Nueva aprobación</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body pt-3">
                <input type="hidden" id="apr-id-tipo" value="">

                <div class="mb-3">
                    <label class="form-label small fw-bold mb-1" for="apr-proceso">Proceso <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm" id="apr-proceso"></select>
                    <div class="text-muted mt-1" id="apr-proceso-desc" style="font-size:.72rem;"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold mb-1" for="apr-buscar">
                        Aprobadores <span class="text-danger">*</span>
                        <span class="text-muted fw-normal">(uno o varios)</span>
                    </label>
                    <div class="position-relative">
                        <div class="form-control form-control-sm d-flex flex-wrap align-items-center gap-1 apr-chips-wrap" id="apr-wrap">
                            <div class="d-flex flex-wrap gap-1" id="apr-chips"></div>
                            <input type="text" class="border-0 flex-grow-1 p-0" id="apr-buscar"
                                   placeholder="Buscar usuario…" autocomplete="off" style="outline:none; min-width:110px;">
                        </div>
                        <div class="list-group shadow-sm d-none position-absolute w-100 apr-dropdown" id="apr-dropdown"></div>
                    </div>
                    <div class="text-muted mt-1" style="font-size:.72rem;">
                        Cualquiera de ellos puede aprobar. Los superadministradores siempre pueden aprobar.
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-7">
                        <label class="form-label small fw-bold mb-1 d-block" for="apr-umbral">Monto mínimo</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">$</span>
                            <input type="number" step="0.01" min="0" class="form-control" id="apr-umbral" placeholder="Sin mínimo">
                        </div>
                        <div class="text-muted mt-1" style="font-size:.72rem;">Vacío = siempre pide aprobación.</div>
                    </div>
                    <div class="col-5">
                        <label class="form-label small fw-bold mb-1 d-block">Estado</label>
                        <div class="form-check form-switch mt-1">
                            <input class="form-check-input" type="checkbox" role="switch" id="apr-activa" checked>
                            <label class="form-check-label small" for="apr-activa">Activa</label>
                        </div>
                        <div class="text-muted mt-1" style="font-size:.72rem;">Inactiva = no pide aprobación, pero conserva la configuración.</div>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-white border-top py-2 px-3 d-flex justify-content-between">
                <button type="button" class="btn btn-sm btn-outline-danger" id="apr-btn-eliminar">
                    <i class="bi bi-trash me-1"></i>Eliminar
                </button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-sm btn-primary" id="apr-btn-guardar">
                        <i class="bi bi-check-lg me-1"></i>Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const APR_URL = '<?= $urlBaseApr ?>';
    const APR_PERM = {
        crear:      <?= !empty($perm['crear']) ? 'true' : 'false' ?>,
        actualizar: <?= !empty($perm['actualizar']) ? 'true' : 'false' ?>,
        eliminar:   <?= !empty($perm['eliminar']) ? 'true' : 'false' ?>
    };
    const APR_USUARIOS = <?= json_encode(array_map(static fn($u) => ['id' => (int) $u['id'], 'nombre' => $u['nombre']], $usuarios), JSON_UNESCAPED_UNICODE) ?>;
    // Checkpoints del catálogo que aún no se configuran en esta empresa.
    let APR_DISPONIBLES = <?= json_encode(array_map(static fn($t) => [
        'id' => (int) $t['id'], 'nombre' => $t['nombre'], 'descripcion' => $t['descripcion'] ?? '',
    ], $disponibles), JSON_UNESCAPED_UNICODE) ?>;
    // Aprobaciones de la página actual, indexadas por id_tipo (para abrir en edición).
    let APR_CONFIGURADAS = <?php
        $mapa = AprobacionesConfigController::mapaConfiguradas($rows);
        // Siempre objeto: con el arreglo vacío json_encode devolvería [] y el
        // acceso por id_tipo dejaría de leerse como mapa.
        echo empty($mapa) ? '{}' : json_encode($mapa, JSON_UNESCAPED_UNICODE);
    ?>;
</script>

<script src="<?= $base ?>/js/modulos/aprobaciones_config.js?v=<?= time() ?>"></script>

<script>
    (function () {
        'use strict';
        const inputBuscar = document.getElementById('buscarAprobacion');
        window.currentSort = '<?= $ordenCol ?>';
        window.currentDir  = '<?= $ordenDir ?>';
        window.currentPage = <?= $page ?>;

        let timerId;
        const debounce = (fn, delay = 400) => (...args) => {
            clearTimeout(timerId);
            timerId = setTimeout(() => fn.apply(this, args), delay);
        };

        window.APR_cambiarPagina = (n) => window.APR_fetchSearch(n);

        window.APR_fetchSearch = async (page = 1) => {
            const term = inputBuscar ? inputBuscar.value.trim() : '';
            const uri = `${APR_URL}/searchAjax?b=${encodeURIComponent(term)}&page=${page}&sort=${window.currentSort}&dir=${window.currentDir}`;
            try {
                const resp = await fetch(uri);
                const data = await resp.json();
                if (!data.ok) return;

                window.currentPage = page;
                document.getElementById('tbodyAprobaciones').innerHTML = data.rows;
                document.getElementById('paginationContainer').innerHTML = data.pagination;
                document.getElementById('paginationInfo').textContent = data.info;
                document.getElementById('btnExportPdf').href = data.pdf_url;
                document.getElementById('btnExportExcel').href = data.excel_url;
                // El modal de edición lee de aquí: sin esto, editar una fila de
                // otra página abriría con los datos de la página anterior.
                APR_CONFIGURADAS = data.configuradas || {};

                document.querySelectorAll('.sortable-header').forEach(th => {
                    const icon = th.querySelector('i');
                    const field = th.dataset.sort;
                    if (field === window.currentSort) {
                        icon.className = (window.currentDir.toLowerCase() === 'asc')
                            ? 'bi bi-sort-alpha-down text-primary ms-1'
                            : 'bi bi-sort-alpha-up text-primary ms-1';
                    } else {
                        icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
                    }
                });
            } catch (e) {
                console.error('Error en búsqueda de aprobaciones:', e);
            }
        };

        window.CMG_initSort('aprobaciones-config', (col, dir) => {
            window.currentSort = col;
            window.currentDir = dir;
            window.APR_fetchSearch(1);
        }, { col: window.currentSort, dir: window.currentDir });

        if (inputBuscar) inputBuscar.addEventListener('input', debounce(() => window.APR_fetchSearch(1)));
    })();
</script>
