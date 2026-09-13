<?php
/**
 * Listado de Responsables de Traslado.
 *
 * @var string $titulo
 * @var array  $perm
 * @var string $rutaModulo
 * @var array  $rows
 * @var int    $total, $page, $totalPages, $perPage, $from, $to
 * @var string $buscar, $ordenCol, $ordenDir
 * @var array  $vistaConfig
 */
$base    = BASE_URL;
$urlBase = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');

$rows        = $rows        ?? [];
$total       = $total       ?? 0;
$page        = $page        ?? 1;
$totalPages  = $totalPages  ?? 1;
$buscar      = $buscar      ?? '';
$ordenCol    = $ordenCol    ?? 'nombre';
$ordenDir    = $ordenDir    ?? 'ASC';
$vistaConfig = $vistaConfig ?? [];

$qsExport = '?b=' . urlencode($buscar) . '&sort=' . urlencode($ordenCol) . '&dir=' . urlencode($ordenDir);

$columnasTabla = [
    'nombre'              => 'Nombre',
    'identificacion'      => 'Identificación',
    'telefono'            => 'Teléfono',
    'email'               => 'Correo',
    'created_at'          => 'Creado',
    'usuarios_vinculados' => 'Usuarios',
    'estado'              => 'Estado',
];
?>
<style>
/* El alto lo gobierna el app-shell (cmg-table-card): aquí solo el detalle visual. */
.resp-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
.resp-row { cursor: pointer; }
.resp-row:hover { background-color: rgba(0,0,0,.04); }
/* Tabla de usuarios vinculados dentro del modal (no la gobierna el app-shell). */
.rt-usuarios-scroll { max-height: 240px; overflow-y: auto; }
.rt-usuarios-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig) ?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig) ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-person-badge me-1 text-primary"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if (!empty($perm['crear'])): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="RT_abrirCrear()">
            <i class="bi bi-plus-lg"></i> Nuevo
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <form class="d-flex align-items-center m-0" onsubmit="event.preventDefault(); RT_fetchSearch(1);">
                <div class="input-group input-group-sm" style="width:320px">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" id="buscarResponsable" class="form-control border-start-0 ps-0 shadow-none border"
                           placeholder="Buscar nombre, identificación, teléfono…"
                           value="<?= htmlspecialchars($buscar) ?>" autocomplete="off">
                    <?php if ($buscar !== ''): ?>
                        <a href="<?= $urlBase ?>" class="btn border border-start-0 text-muted" title="Limpiar"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </div>
                <button type="submit" class="d-none">Buscar</button>
            </form>

            <div class="btn-group btn-group-sm">
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig, $rutaModulo) ?>
                <a id="btnExportPdf" href="<?= $urlBase ?>/export-pdf<?= $qsExport ?>"
                   class="btn btn-outline-danger" title="Descargar PDF">
                    <i class="bi bi-file-earmark-pdf"></i> PDF
                </a>
                <a id="btnExportExcel" href="<?= $urlBase ?>/export-excel<?= $qsExport ?>"
                   class="btn btn-outline-success" title="Descargar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                </a>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <span id="rtPaginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="rtPaginationContainer" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" <?= $page <= 1 ? 'disabled' : '' ?>
                        onclick="RT_fetchSearch(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" <?= $page >= $totalPages ? 'disabled' : '' ?>
                        onclick="RT_fetchSearch(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="resp-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="nombre" data-col="nombre">Nombre <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="identificacion" data-col="identificacion">Identificación <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="telefono" data-col="telefono">Teléfono <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="email" data-col="email">Correo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="created_at" data-col="created_at">Creado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center" data-col="usuarios_vinculados"
                            title="Usuarios que ven las entregas de este responsable en la app móvil">Usuarios</th>
                        <th class="text-center pe-3 sortable-header" role="button" data-sort="estado" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyResponsables">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-person-badge fs-3 d-block mb-2"></i>No se encontraron responsables de traslado.
                            </td>
                        </tr>
                    <?php else: foreach ($rows as $r): ?>
                        <?php include __DIR__ . '/_fila.php'; ?>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/modal_responsable.php'; ?>

<script>
    window.RT_URL_BASE = '<?= $urlBase ?>';
    window.RT_currentSort = '<?= htmlspecialchars($ordenCol) ?>';
    window.RT_currentDir  = '<?= htmlspecialchars($ordenDir) ?>';
    window.RT_currentPage = <?= (int) $page ?>;
</script>
<script src="<?= $base ?>/js/modulos/responsables_traslados.js?v=<?= asset_ver('/js/modulos/responsables_traslados.js') ?>"></script>
