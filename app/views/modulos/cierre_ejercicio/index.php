<?php
use App\Helpers\PreferenciasHelper;

$base     = BASE_URL;
$urlBase  = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');
$vista    = $vistaConfig ?? [];
$columnas = [
    'anio'          => 'Año',
    'fecha_cierre'  => 'Fecha cierre',
    'saldos_desde'  => 'Saldos desde',
    'asiento_cierre'   => 'Asiento cierre',
    'asiento_apertura' => 'Asiento apertura',
    'resultado'     => 'Resultado del año',
    'activos'       => 'Activos',
    'patrimonio'    => 'Patrimonio',
    'estado'        => 'Estado',
    'registrado'    => 'Registrado',
];
$pestanasNuevo   = ['ce-pane-cierre' => 'Asiento de cierre', 'ce-pane-apertura' => 'Asiento de apertura'];
$pestanasDetalle = ['ce-det-cierre' => 'Asiento de cierre', 'ce-det-apertura' => 'Asiento de apertura'];
?>
<style>
    .cierre-ejercicio-scroll { max-height: calc(100dvh - 240px); overflow-y: auto; }
    .cierre-ejercicio-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
    .ce-row { cursor: pointer; }
    .ce-lineas-scroll { max-height: 42vh; overflow: auto; }
    .ce-lineas-scroll thead th { position: sticky; top: 0; background: #f8f9fa; z-index: 1; }
    .ce-lineas td, .ce-lineas th { font-size: .78rem; padding: 2px 6px; white-space: nowrap; }
    .ce-kpi { min-width: 150px; }
    .ce-kpi .v { font-weight: 600; font-size: .95rem; }
    .ce-kpi .l { font-size: .72rem; color: #6c757d; }
</style>
<?= PreferenciasHelper::renderEstilosColumnasOcultas($vista) ?>
<?= PreferenciasHelper::renderEstilosPestanasOcultas($vista) ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-journal-check me-1"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear'] && $tablaDisponible): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="CE_nuevo()"><i class="bi bi-plus-lg"></i> Cerrar un ejercicio</button>
    <?php endif; ?>
</div>

<?php if (!$tablaDisponible): ?>
    <div class="alert alert-warning small">
        <i class="bi bi-exclamation-triangle me-1"></i>
        Falta crear la tabla del módulo. Ejecute <code>database/2026-10-09_cierre_ejercicio.sql</code> en la base de datos.
    </div>
<?php endif; ?>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <div class="input-group input-group-sm" style="width: 300px;">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" id="buscarCierre" class="form-control border-start-0 ps-0 shadow-none border"
                       placeholder="Año, estado, número de asiento… (anio:2025)" autocomplete="off">
            </div>
            <div class="btn-group btn-group-sm">
                <?= PreferenciasHelper::renderDropdownColumnas($columnas, $vista, $rutaModulo) ?>
                <a id="btnExportPdf" href="<?= $urlBase ?>/exportPdf" class="btn btn-outline-danger" title="Descargar PDF"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                <a id="btnExportExcel" href="<?= $urlBase ?>/exportExcel" class="btn btn-outline-success" title="Descargar Excel"><i class="bi bi-file-earmark-spreadsheet"></i> Excel</a>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfo" class="text-muted small fw-medium">0-0/0</span>
            <div id="paginationContainer" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" id="cePrev" disabled><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" id="ceNext" disabled><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="cierre-ejercicio-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" data-sort="anio" data-col="anio">Año <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th data-col="fecha_cierre">Fecha cierre</th>
                        <th data-col="saldos_desde">Saldos desde</th>
                        <th data-col="asiento_cierre">Asiento cierre</th>
                        <th data-col="asiento_apertura">Asiento apertura</th>
                        <th class="text-end sortable-header" data-sort="resultado" data-col="resultado">Resultado del año <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end" data-col="activos">Activos</th>
                        <th class="text-end" data-col="patrimonio">Patrimonio</th>
                        <th class="text-center sortable-header" data-sort="estado" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="pe-3 sortable-header" data-sort="created_at" data-col="registrado">Registrado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyCierres">
                    <tr><td colspan="10" class="text-center py-5 text-muted">Cargando…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: cerrar un ejercicio -->
<div class="modal fade" id="modalCierreNuevo" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-journal-check me-2 text-primary"></i> Cerrar un ejercicio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap align-items-start gap-3 mb-3">
                    <div style="width:130px">
                        <label class="form-label small fw-bold d-block mb-1">Año a cerrar</label>
                        <select id="ceAnio" class="form-select form-select-sm"></select>
                    </div>
                    <div style="width:270px">
                        <label class="form-label small fw-bold d-block mb-1">Saldos de balance desde
                            <i class="bi bi-info-circle text-muted" title="Desde qué fecha se suman los movimientos de activo, pasivo y patrimonio para la apertura. Por defecto, la primera del último grupo de asientos de apertura registrados (aperturas con hasta 31 días de diferencia cuentan como una sola); si nunca hubo una, todo el histórico."></i>
                        </label>
                        <div class="d-flex align-items-center gap-2">
                            <input type="date" id="ceDesde" class="form-control form-control-sm" style="width:140px">
                            <div class="form-check small mb-0">
                                <input class="form-check-input" type="checkbox" id="ceDesdeTodo">
                                <label class="form-check-label" for="ceDesdeTodo">Todo el histórico</label>
                            </div>
                        </div>
                    </div>
                    <div class="flex-grow-1" style="min-width:240px">
                        <label class="form-label small fw-bold d-block mb-1">Observaciones</label>
                        <input type="text" id="ceObs" class="form-control form-control-sm" maxlength="2000" placeholder="Opcional">
                    </div>
                </div>

                <div id="ceCargando" class="text-center text-muted py-4 d-none"><span class="spinner-border spinner-border-sm me-2"></span>Calculando…</div>
                <div id="ceErrores" class="alert alert-danger small py-2 d-none"></div>
                <div id="ceAvisos" class="alert alert-warning small py-2 d-none"></div>

                <div id="cePrevia" class="d-none">
                    <div class="d-flex flex-wrap gap-2 mb-3" id="ceKpis"></div>
                    <p class="small text-muted mb-2" id="ceExplica"></p>
                    <div class="d-flex align-items-center border-bottom mb-2">
                        <ul class="nav nav-tabs border-0" role="tablist">
                            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#ce-pane-cierre" type="button"><i class="bi bi-box-arrow-in-right me-1"></i>Asiento de cierre <span class="badge bg-secondary bg-opacity-10 text-secondary" id="ceCntCierre"></span></button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#ce-pane-apertura" type="button"><i class="bi bi-box-arrow-right me-1"></i>Asiento de apertura <span class="badge bg-secondary bg-opacity-10 text-secondary" id="ceCntApertura"></span></button></li>
                        </ul>
                        <?= PreferenciasHelper::renderDropdownPestanas($pestanasNuevo, $vista, $rutaModulo) ?>
                    </div>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="ce-pane-cierre"><div class="ce-lineas-scroll" id="ceTablaCierre"></div></div>
                        <div class="tab-pane fade" id="ce-pane-apertura"><div class="ce-lineas-scroll" id="ceTablaApertura"></div></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between bg-light">
                <div></div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-sm px-4" id="ceBtnGenerar" disabled onclick="CE_generar()">
                        <i class="bi bi-lock"></i> Generar cierre
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: detalle de un cierre -->
<div class="modal fade" id="modalCierreDetalle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-journal-check me-2 text-primary"></i> <span id="ceDetTitulo">Cierre</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div id="ceDetAviso" class="alert alert-warning small py-2 d-none"></div>
                <div id="ceDetRevertido" class="alert alert-secondary small py-2 d-none"></div>
                <div class="d-flex flex-wrap gap-2 mb-3" id="ceDetKpis"></div>
                <div class="d-flex align-items-center border-bottom mb-2">
                    <ul class="nav nav-tabs border-0" role="tablist">
                        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#ce-det-cierre" type="button"><i class="bi bi-box-arrow-in-right me-1"></i>Asiento de cierre <span class="text-muted small" id="ceDetNumCierre"></span></button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#ce-det-apertura" type="button"><i class="bi bi-box-arrow-right me-1"></i>Asiento de apertura <span class="text-muted small" id="ceDetNumApertura"></span></button></li>
                    </ul>
                    <?= PreferenciasHelper::renderDropdownPestanas($pestanasDetalle, $vista, $rutaModulo) ?>
                </div>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="ce-det-cierre"><div class="ce-lineas-scroll" id="ceDetTablaCierre"></div></div>
                    <div class="tab-pane fade" id="ce-det-apertura"><div class="ce-lineas-scroll" id="ceDetTablaApertura"></div></div>
                </div>
                <div class="small text-muted mt-3" id="ceDetPie"></div>
            </div>
            <div class="modal-footer justify-content-between bg-light">
                <div>
                    <?php if ($perm['eliminar']): ?>
                        <button type="button" class="btn btn-outline-danger btn-sm px-3 d-none" id="ceBtnRevertir" onclick="CE_revertir()">
                            <i class="bi bi-arrow-counterclockwise"></i> Revertir cierre
                        </button>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
    window.CE_CONFIG = {
        urlBase: '<?= $urlBase ?>',
        modulo: '<?= htmlspecialchars($rutaModulo, ENT_QUOTES) ?>',
        ordenCol: '<?= htmlspecialchars($ordenCol, ENT_QUOTES) ?>',
        ordenDir: '<?= htmlspecialchars($ordenDir, ENT_QUOTES) ?>',
        tabla: <?= $tablaDisponible ? 'true' : 'false' ?>
    };
</script>
<script src="<?= $base ?>/js/modulos/cierre_ejercicio.js?v=<?= asset_ver('/js/modulos/cierre_ejercicio.js') ?>"></script>
