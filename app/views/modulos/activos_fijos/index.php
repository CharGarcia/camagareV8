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
/** @var array $vistaConfig */

$base = BASE_URL;
$urlBase = $base . '/' . $rutaModulo;

$rows       = $rows ?? [];
$total      = $total ?? 0;
$page       = $page ?? 1;
$totalPages = $totalPages ?? 1;
$perPage    = $perPage ?? 20;
$ordenCol   = $ordenCol ?? 'fecha_adquisicion';
$ordenDir   = $ordenDir ?? 'DESC';
$buscar     = $buscar ?? '';
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>

<style>
    .af-header { flex-shrink: 0; }
    .activos-fijos-scroll { max-height: calc(100dvh - 240px); overflow-y: auto; }
    .activos-fijos-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
    .af-row { cursor: pointer; }
    .af-row:hover { background-color: rgba(0, 0, 0, .04); }
    .af-dropdown { position: absolute; z-index: 1080; max-height: 240px; overflow-y: auto; display: none; width: 100%; }
</style>

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<div class="af-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-building me-1 text-primary"></i> <?= htmlspecialchars($titulo) ?></h5>
    <div class="d-flex gap-2">
        <?php if ($perm['actualizar']): ?>
            <button type="button" class="btn btn-outline-primary btn-sm px-3" onclick="AF_abrirModalDepreciacion()">
                <i class="bi bi-graph-down-arrow me-1"></i> Generar Depreciación
            </button>
        <?php endif; ?>
        <?php if ($perm['crear']): ?>
            <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="AF_abrirModal()">
                <i class="bi bi-plus-lg me-1"></i> Nuevo
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <form id="frmBuscarAF" class="d-flex align-items-center m-0" onsubmit="event.preventDefault(); window.AF_buscar(1);">
                <div class="input-group input-group-sm" style="width: 320px;">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" id="txtBuscarAF" class="form-control border-start-0 ps-0 shadow-none border" placeholder="Buscar por nombre, código, categoría..." value="<?= htmlspecialchars($buscar) ?>" autocomplete="off">
                </div>
            </form>

            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'codigo'                 => 'Código',
                    'nombre'                 => 'Nombre',
                    'categoria_nombre'       => 'Categoría',
                    'origen'                 => 'Origen',
                    'fecha_adquisicion'      => 'Fecha Adquisición',
                    'valor_adquisicion'      => 'Valor Adquisición',
                    'valor_en_libros'        => 'Valor en Libros',
                    'estado'                 => 'Estado',
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>

                <a id="btnExportPdfAF" href="<?= $urlBase ?>/export-pdf?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                    class="btn btn-outline-danger" title="Descargar PDF">
                    <i class="bi bi-file-earmark-pdf"></i> PDF
                </a>
                <a id="btnExportExcelAF" href="<?= $urlBase ?>/export-excel?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                    class="btn btn-outline-success" title="Descargar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                </a>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="paginationContainer" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" <?= $page <= 1 ? 'disabled' : '' ?> onclick="window.AF_cambiarPaginaAjax(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" <?= $page >= $totalPages ? 'disabled' : '' ?> onclick="window.AF_cambiarPaginaAjax(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="activos-fijos-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <?php
                        $renderHeader = function ($key, $label, $align = 'left', $extraClass = '') use ($ordenCol, $ordenDir) {
                            $isCur = ($ordenCol === $key);
                            $icon = 'bi-arrow-down-up small text-muted';
                            if ($isCur) {
                                $icon = (strtoupper($ordenDir) === 'ASC') ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary';
                            }
                            $cls = match ($align) {
                                'center' => 'text-center',
                                'right'  => 'text-end',
                                default  => '',
                            };
                            echo '<th class="ps-3 py-2 sortable-header ' . $cls . ' ' . $extraClass . '" role="button" data-col="' . $key . '" onclick="window.AF_sort(\'' . $key . '\')">' . $label . ' <i class="bi ' . $icon . ' ms-1"></i></th>';
                        };
                        ?>
                        <?php $renderHeader('codigo', 'Código'); ?>
                        <?php $renderHeader('nombre', 'Nombre'); ?>
                        <th data-col="categoria_nombre">Categoría</th>
                        <th class="text-center" data-col="origen">Origen</th>
                        <?php $renderHeader('fecha_adquisicion', 'Fecha Adquisición'); ?>
                        <?php $renderHeader('valor_adquisicion', 'Valor Adquisición', 'right'); ?>
                        <?php $renderHeader('valor_en_libros', 'Valor en Libros', 'right'); ?>
                        <?php $renderHeader('estado', 'Estado', 'center', 'pe-3'); ?>
                    </tr>
                </thead>
                <tbody id="tbodyAF">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted"><i class="bi bi-building fs-3 d-block mb-2"></i>No se encontraron activos fijos.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $estado = $r['estado'] ?? 'activo';
                            $estCls = $estado === 'depreciado_total' ? 'bg-secondary bg-opacity-10 text-secondary border-secondary' : 'bg-success bg-opacity-10 text-success border-success';
                            $estTxt = $estado === 'depreciado_total' ? 'Depreciado total' : 'Activo';
                            $origenBadge = $r['origen'] === 'compra'
                                ? '<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25"><i class="bi bi-receipt"></i> Compra</span>'
                                : '<span class="badge bg-light text-dark border"><i class="bi bi-pencil"></i> Manual</span>';
                            ?>
                            <tr class="af-row" role="button" onclick="AF_abrirModal(<?= (int) $r['id'] ?>)">
                                <td class="ps-3" data-col="codigo"><code><?= htmlspecialchars((string) ($r['codigo'] ?? '')) ?></code></td>
                                <td data-col="nombre"><?= htmlspecialchars($r['nombre']) ?></td>
                                <td data-col="categoria_nombre"><?= htmlspecialchars($r['categoria_nombre'] ?? '') ?></td>
                                <td class="text-center" data-col="origen"><?= $origenBadge ?></td>
                                <td data-col="fecha_adquisicion"><?= !empty($r['fecha_adquisicion']) ? date('d-m-Y', strtotime($r['fecha_adquisicion'])) : '-' ?></td>
                                <td class="text-end" data-col="valor_adquisicion">$<?= number_format((float) $r['valor_adquisicion'], 2) ?></td>
                                <td class="text-end" data-col="valor_en_libros">$<?= number_format((float) $r['valor_en_libros'], 2) ?></td>
                                <td class="text-center pe-3" data-col="estado"><span class="badge <?= $estCls ?> border border-opacity-25"><?= $estTxt ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/modal_activo_fijo.php'; ?>
<?php include __DIR__ . '/modal_generar_depreciacion.php'; ?>

<script>
    window.AF_URL_BASE = '<?= $urlBase ?>';
    window.AF_CATEGORIAS_URL = '<?= $base ?>/modulos/activos-fijos-categorias';
    window.AF_CUENTAS_URL = '<?= $base ?>/modulos/plan-cuentas';
    window.AF_ORDEN_COL = '<?= $ordenCol ?>';
    window.AF_ORDEN_DIR = '<?= $ordenDir ?>';
    window.AF_PAGE = <?= $page ?>;
    window.AF_PERM = <?= json_encode($perm) ?>;
</script>
<script src="<?= rtrim(BASE_URL, '/') ?>/js/modulos/activos_fijos.js?v=<?= time() ?>"></script>
