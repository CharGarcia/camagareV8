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

use App\Helpers\PreferenciasHelper;

$base      = BASE_URL;
$urlBaseCE = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');

$rows       = $rows ?? [];
$total      = $total ?? 0;
$page       = $page ?? 1;
$totalPages = $totalPages ?? 1;
$perPage    = $perPage ?? 25;
$buscar     = $buscar ?? '';
$vista      = $vistaConfig ?? [];
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>
<style>
    .ce-header {
        flex-shrink: 0;
    }

    .cierre-ejercicio-scroll {
        max-height: calc(100dvh - 240px);
        overflow-y: auto;
    }

    .cierre-ejercicio-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }

    .cierre-row {
        cursor: pointer;
    }

    .cierre-row:hover {
        background-color: rgba(0, 0, 0, .04);
    }

    #tabsCierre .nav-link {
        padding: 6px 9px;
        font-size: 0.8rem;
        white-space: nowrap;
    }

    #tabsCierre .nav-link i {
        font-size: 0.85rem;
    }

    .ce-lineas-scroll {
        max-height: 55vh;
        overflow: auto;
    }

    .ce-lineas-scroll thead th {
        position: sticky;
        top: 0;
        background: #f8f9fa;
        z-index: 1;
    }

    .ce-lineas td,
    .ce-lineas th {
        font-size: .78rem;
        padding: 2px 6px;
        white-space: nowrap;
    }

    .ce-kpi .v {
        font-weight: 600;
        font-size: .9rem;
        line-height: 1.2;
    }

    .ce-kpi .l {
        font-size: .72rem;
        color: #6c757d;
    }
</style>
<?= PreferenciasHelper::renderEstilosColumnasOcultas($vista) ?>
<?= PreferenciasHelper::renderEstilosPestanasOcultas($vista) ?>

<div class="ce-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-journal-check"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear'] && $tablaDisponible): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="CE_nuevo()"><i class="bi bi-plus-lg"></i> Nuevo</button>
    <?php endif; ?>
</div>

<?php if (!$tablaDisponible): ?>
    <div class="alert alert-warning small py-2">
        <i class="bi bi-exclamation-triangle me-1"></i>
        Falta crear la tabla del módulo. Ejecute <code>database/2026-10-09_cierre_ejercicio.sql</code> en la base de datos.
    </div>
<?php endif; ?>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <!-- Buscador y Exportación -->
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php
            // Buscador estándar (FiltrosModal): texto libre + embudo con los filtros. Las claves
            // deben existir en los mapas de CierreEjercicioRepository::getListado().
            $tC = 'Cierre';
            $filtrosCierre = [
                ['tab' => $tC, 'key' => 'anio',     'label' => 'Año',               'icon' => 'bi-calendar3',      'type' => 'number_range', 'grupo' => 'Ejercicio', 'col' => 6],
                ['tab' => $tC, 'key' => 'estado',   'label' => 'Estado',            'icon' => 'bi-flag',           'type' => 'select',       'grupo' => 'Ejercicio', 'col' => 6, 'options' => [
                    ['v' => 'vigente',   'l' => 'Vigente'],
                    ['v' => 'revertido', 'l' => 'Revertido'],
                ]],
                ['tab' => $tC, 'key' => 'resultado', 'label' => 'Resultado del año', 'icon' => 'bi-graph-up',      'type' => 'number_range', 'grupo' => 'Importes', 'col' => 6],
                ['tab' => $tC, 'key' => 'cierre',   'label' => 'Fecha de cierre',   'icon' => 'bi-calendar-check', 'type' => 'date_range',   'grupo' => 'Importes', 'col' => 6],
                ['tab' => $tC, 'key' => 'registro', 'label' => 'Fecha de registro', 'icon' => 'bi-calendar-event', 'type' => 'date_range',   'grupo' => 'Registro', 'col' => 6, 'atajos' => true],
                ['tab' => $tC, 'key' => 'usuario',  'label' => 'Usuario que registró', 'icon' => 'bi-person-gear', 'type' => 'select',       'grupo' => 'Registro', 'col' => 6,
                    'options' => array_map(fn($u) => ['v' => (string) $u['id'], 'l' => (string) $u['nombre']], $usuariosFiltro ?? [])],
            ];
            ?>
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
            <div id="fmBuscadorCE"></div>
            <input type="hidden" id="buscarCierre" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosModal) return;
                    new FiltrosModal({
                        containerId: 'fmBuscadorCE',
                        hiddenInputId: 'buscarCierre',
                        placeholder: 'Buscar por año, estado o número de asiento...',
                        titulo: 'Filtros de cierres del ejercicio',
                        inputWidth: 420,
                        extraId: 'fmExtraCE',
                        fields: <?= json_encode($filtrosCierre, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                        loadingTarget: '#tbodyCierres',
                        onApply: () => window.fetchSearch && window.fetchSearch(1),
                    }).init();
                });
            </script>

            <?php // FiltrosModal (extraId) mueve estos botones dentro del input-group del buscador; si el JS no corre, quedan aquí. ?>
            <div id="fmExtraCE" class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'anio'             => 'Año',
                    'fecha_cierre'     => 'Fecha cierre',
                    'saldos_desde'     => 'Saldos desde',
                    'asiento_cierre'   => 'Asiento cierre',
                    'asiento_apertura' => 'Asiento apertura',
                    'resultado'        => 'Resultado del año',
                    'activos'          => 'Activos',
                    'patrimonio'       => 'Patrimonio',
                    'estado'           => 'Estado',
                    'registrado'       => 'Registrado',
                ];
                ?>
                <?= PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vista, $rutaModulo) ?>
                <a id="btnExportPdf" href="<?= $urlBaseCE ?>/export-pdf?b=<?= urlencode($buscar) ?>&orden=<?= urlencode($ordenParam ?? '') ?>"
                    class="btn btn-outline-danger" title="Descargar PDF">
                    <i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span>
                </a>
                <a id="btnExportExcel" href="<?= $urlBaseCE ?>/export-excel?b=<?= urlencode($buscar) ?>&orden=<?= urlencode($ordenParam ?? '') ?>"
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
        <div class="cierre-ejercicio-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="anio" data-col="anio">Año <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="fecha_cierre" data-col="fecha_cierre">Fecha cierre <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="saldos_desde" data-col="saldos_desde">Saldos desde <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="asiento_cierre" data-col="asiento_cierre">Asiento cierre <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="asiento_apertura" data-col="asiento_apertura">Asiento apertura <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-end" role="button" data-sort="resultado" data-col="resultado">Resultado del año <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-end" role="button" data-sort="activos" data-col="activos">Activos <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-end" role="button" data-sort="patrimonio" data-col="patrimonio">Patrimonio <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-center" role="button" data-sort="estado" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="pe-3 sortable-header" role="button" data-sort="registrado" data-col="registrado">Registrado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyCierres">
                    <?php require __DIR__ . '/_filas.php'; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: cierre del ejercicio (nuevo y detalle) -->
<div class="modal fade" id="modalCierre" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <?php // Sin modal-dialog-scrollable, como Proveedores: si no cabe, scrollea el modal completo. ?>
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-journal-check text-primary me-2"></i>
                    <span id="ceTitulo">Nuevo cierre del ejercicio</span>
                    <span id="ceEstadoBadge" class="ms-2"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0">
                <!-- Pestañas -->
                <div class="d-flex align-items-center bg-light px-3 pt-2">
                    <ul class="nav nav-tabs border-bottom-0 flex-grow-1 flex-nowrap tab-pestaña" id="tabsCierre" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link active" id="ce-tab-general-btn" data-bs-toggle="tab" data-bs-target="#ce-tab-general" href="#ce-tab-general" role="tab" title="General"><i class="bi bi-card-text me-1"></i> General</a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="ce-tab-cierre-btn" data-bs-toggle="tab" data-bs-target="#ce-tab-cierre" href="#ce-tab-cierre" role="tab" title="Asiento de cierre al 31-12"><i class="bi bi-box-arrow-in-right me-1"></i> Asiento de cierre <span class="badge bg-secondary bg-opacity-10 text-secondary" id="ceCntCierre"></span></a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="ce-tab-apertura-btn" data-bs-toggle="tab" data-bs-target="#ce-tab-apertura" href="#ce-tab-apertura" role="tab" title="Asiento de apertura al 01-01 del año siguiente"><i class="bi bi-box-arrow-right me-1"></i> Asiento de apertura <span class="badge bg-secondary bg-opacity-10 text-secondary" id="ceCntApertura"></span></a>
                        </li>
                    </ul>
                    <div class="ms-2">
                        <?= PreferenciasHelper::renderDropdownPestanas(['ce-tab-cierre' => 'Asiento de cierre', 'ce-tab-apertura' => 'Asiento de apertura'], $vista, $rutaModulo) ?>
                    </div>
                </div>
                <div class="border-bottom bg-light mb-0"></div>

                <div class="tab-content border-top px-3 py-3" id="tabsCierreContent">
                    <!-- Pestaña General -->
                    <div class="tab-pane fade show active" id="ce-tab-general" role="tabpanel">
                        <div class="row g-3">
                            <div class="col-md-2">
                                <label for="ceAnio" class="form-label small fw-bold">Año a cerrar *</label>
                                <select id="ceAnio" class="form-select form-select-sm"></select>
                            </div>
                            <div class="col-md-4">
                                <label for="ceDesde" class="form-label small fw-bold">Saldos de balance desde
                                    <i class="bi bi-info-circle text-muted" title="Desde qué fecha se suman los movimientos de activo, pasivo y patrimonio para la apertura. Por defecto, la primera del último grupo de asientos de apertura registrados (aperturas con hasta 31 días de diferencia cuentan como una sola); si nunca hubo una, todo el histórico."></i>
                                </label>
                                <div class="d-flex align-items-center gap-2">
                                    <input type="date" id="ceDesde" class="form-control form-control-sm" style="max-width:160px">
                                    <div class="form-check small mb-0">
                                        <input class="form-check-input" type="checkbox" id="ceDesdeTodo">
                                        <label class="form-check-label" for="ceDesdeTodo">Todo el histórico</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="ceObs" class="form-label small fw-bold">Observaciones</label>
                                <input type="text" id="ceObs" class="form-control form-control-sm" maxlength="2000" placeholder="Opcional">
                            </div>
                        </div>

                        <div id="ceCargando" class="text-center text-muted py-4 d-none"><span class="spinner-border spinner-border-sm me-2"></span>Calculando…</div>
                        <div id="ceRevertido" class="alert alert-secondary small py-2 mt-3 mb-0 d-none"></div>
                        <div id="ceErrores" class="alert alert-danger small py-2 mt-3 mb-0 d-none"></div>
                        <div id="ceAvisos" class="alert alert-warning small py-2 mt-3 mb-0 d-none"></div>

                        <div id="ceResumen" class="d-none">
                            <div class="d-flex flex-wrap gap-2 mt-3" id="ceKpis"></div>
                            <p class="small text-muted mt-3 mb-0" id="ceExplica"></p>
                        </div>
                        <div class="small text-muted mt-3 d-none" id="cePie"></div>
                    </div>

                    <!-- Pestaña Asiento de cierre -->
                    <div class="tab-pane fade" id="ce-tab-cierre" role="tabpanel">
                        <div class="ce-lineas-scroll" id="ceTablaCierre"></div>
                    </div>

                    <!-- Pestaña Asiento de apertura -->
                    <div class="tab-pane fade" id="ce-tab-apertura" role="tabpanel">
                        <div class="ce-lineas-scroll" id="ceTablaApertura"></div>
                    </div>
                </div>
            </div>

            <div class="modal-footer justify-content-between bg-light border-top p-2">
                <div>
                    <?php if ($perm['eliminar']): ?>
                        <button type="button" class="btn btn-outline-danger btn-sm px-3 d-none" id="ceBtnRevertir" onclick="CE_revertir()">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Revertir cierre
                        </button>
                    <?php endif; ?>
                </div>
                <div>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                        <i class="fa-solid fa-xmark me-1"></i>Cerrar
                    </button>
                    <?php if ($perm['crear']): ?>
                        <button type="button" class="btn btn-primary btn-sm px-4 d-none" id="ceBtnGenerar" disabled onclick="CE_generar()">
                            <i class="bi bi-lock me-1"></i> Generar cierre
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    window.CE_CONFIG = {
        urlBase: '<?= $urlBaseCE ?>',
        modulo: '<?= htmlspecialchars($rutaModulo, ENT_QUOTES) ?>',
        sorts: <?= $ordenJson ?? '[]' ?>,
        page: <?= (int) $page ?>
    };
</script>
<script src="<?= $base ?>/js/modulos/cierre_ejercicio.js?v=<?= asset_ver('/js/modulos/cierre_ejercicio.js') ?>"></script>
