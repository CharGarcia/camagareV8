<?php
/** @var string $titulo @var array $perm @var string $rutaModulo @var array $vistaConfig @var array $rows
 *  @var int $total @var int $page @var int $perPage @var int $totalPages @var string $buscar
 *  @var string $ordenJson @var string $ordenParam @var array $centrosCosto @var array $proyectos @var bool $puedeAprobar @var string $base */
$idModulo = basename($rutaModulo);
$urlBase  = rtrim($base, '/') . '/' . $rutaModulo;
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
$columnasTabla = [
    'nombre'    => 'Nombre',
    'periodo'   => 'Período',
    'alcance'   => 'Alcance',
    'version'   => 'Versión',
    'ingresos'  => 'Ingresos',
    'gastos'    => 'Gastos',
    'ejecutado' => 'Ejecutado',
    'pct'       => '% Ejec.',
    'estado'    => 'Estado',
];
?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<style>
    .pre-row { cursor: pointer; }
    .pre-row:hover { background-color: rgba(0,0,0,.04); }
    .pre-sem { display:inline-block; width:10px; height:10px; border-radius:50%; vertical-align:middle; margin-right:4px; }
    .pre-sem-verde { background:#198754; } .pre-sem-amarillo { background:#ffc107; } .pre-sem-rojo { background:#dc3545; } .pre-sem-gris, .pre-sem-futuro { background:#ced4da; }
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-calculator text-primary me-2"></i><?= htmlspecialchars($titulo) ?></h5>
    <div class="d-flex gap-2">
        <?php if ($perm['crear']): ?>
            <button type="button" class="btn btn-primary btn-sm px-3" onclick="PRE.nuevo()">
                <i class="bi bi-plus-lg"></i> Nuevo
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <div class="input-group input-group-sm" style="width: 420px; max-width: 100%;">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input type="search" class="form-control" id="pre-buscar" value="<?= htmlspecialchars($buscar) ?>"
                       placeholder="Buscar… (estado:aprobado, alcance:proyecto, anio:2027)" autocomplete="off">
            </div>
            <div class="btn-group btn-group-sm">
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>
                <a id="pre-btn-pdf" href="<?= $urlBase ?>/exportPdf?b=<?= urlencode($buscar) ?>&orden=<?= urlencode($ordenParam) ?>" class="btn btn-outline-danger" title="PDF del listado">
                    <i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span>
                </a>
                <a id="pre-btn-excel" href="<?= $urlBase ?>/exportExcel?b=<?= urlencode($buscar) ?>&orden=<?= urlencode($ordenParam) ?>" class="btn btn-outline-success" title="Excel del listado">
                    <i class="bi bi-file-earmark-spreadsheet"></i><span class="d-none d-md-inline"> Excel</span>
                </a>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span id="pre-pag-info" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="pre-paginacion" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" data-pag="-1" <?= $page <= 1 ? 'disabled' : '' ?>><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" data-pag="1" <?= $page >= $totalPages ? 'disabled' : '' ?>><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="presupuestos-scroll">
            <table class="table table-hover table-sm mb-0 align-middle" id="tabla-pre">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="nombre" data-col="nombre">Nombre <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="periodo" data-col="periodo">Período <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="alcance" data-col="alcance">Alcance <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" role="button" data-sort="version" data-col="version">Versión <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header" role="button" data-sort="ingresos" data-col="ingresos">Ingresos <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header" role="button" data-sort="gastos" data-col="gastos">Gastos <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end" data-col="ejecutado" title="Costos y gastos ejecutados de la versión vigente">Ejecutado</th>
                        <th class="text-center" data-col="pct">% Ejec.</th>
                        <th class="text-center pe-3 sortable-header" role="button" data-sort="estado" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="pre-tbody">
                    <tr><td colspan="9" class="text-center py-5 text-muted">Cargando…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/modal.php'; ?>

<?= \App\Helpers\PreferenciasHelper::getJavascriptVariables($rutaModulo) ?>
<script>
    window.PRE_CFG = {
        url: <?= json_encode($urlBase) ?>,
        modulo: <?= json_encode($idModulo) ?>,
        perm: <?= json_encode(['crear' => !empty($perm['crear']), 'actualizar' => !empty($perm['actualizar']), 'eliminar' => !empty($perm['eliminar']), 'aprobar' => (bool) $puedeAprobar]) ?>,
        sorts: <?= $ordenJson ?>,
        page: <?= (int) $page ?>,
        centrosCosto: <?= json_encode($centrosCosto, JSON_UNESCAPED_UNICODE) ?>,
        proyectos: <?= json_encode($proyectos, JSON_UNESCAPED_UNICODE) ?>,
    };
</script>
<script src="<?= rtrim($base, '/') ?>/js/modulos/presupuestos.js?v=<?= asset_ver('/js/modulos/presupuestos.js') ?>"></script>
