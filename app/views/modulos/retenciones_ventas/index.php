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

$base    = BASE_URL;
$urlBase = $base . '/' . $rutaModulo;

$rows       = $rows       ?? [];
$total      = $total      ?? 0;
$page       = $page       ?? 1;
$totalPages = $totalPages ?? 1;
$perPage    = $perPage    ?? 20;
$ordenCol   = $ordenCol   ?? 'fecha_emision';
$ordenDir   = $ordenDir   ?? 'DESC';
$buscar     = $buscar     ?? '';
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>

<style>
    .retv-scroll { max-height: calc(100vh - 240px); overflow-y: auto; }
    .retv-scroll thead th { position: sticky; top: 0; z-index: 10; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
    .retv-row { cursor: pointer; }
    .retv-row:hover { background-color: rgba(0,0,0,.04); }
    .modal-retv .modal-header { background-color: #f8f9fa; border-bottom: 1px solid #dee2e6; padding: 0.75rem 1rem; }
    .modal-retv .modal-body { padding: 0 !important; }
    .modal-retv label { font-size: 0.85rem; font-weight: 600; color: #495057; margin-bottom: 3px !important; }
</style>

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold">
        <i class="fa-solid fa-file-invoice-dollar me-2 text-success"></i><?= htmlspecialchars($titulo) ?>
    </h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="window.RETV_abrirModalNuevo()">
            <i class="fa-solid fa-plus me-1"></i> Nueva
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_busqueda.css?v=<?= time() ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_busqueda.js?v=<?= time() ?>"></script>
            <div id="fbBuscadorRetV" style="width: 480px;"></div>
            <input type="hidden" id="buscarRetV" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosBusqueda) return;
                    new FiltrosBusqueda({
                        containerId: 'fbBuscadorRetV',
                        hiddenInputId: 'buscarRetV',
                        placeholder: 'Buscar...',
                        fields: [
                            { key: 'cliente',     label: 'Cliente',        icon: 'bi-person',          type: 'text' },
                            { key: 'ruc',         label: 'RUC / Cédula',   icon: 'bi-card-text',       type: 'text' },
                            { key: 'numero',      label: 'Secuencial',     icon: 'bi-hash',            type: 'text' },
                            { key: 'periodo',     label: 'Período fiscal', icon: 'bi-calendar3',       type: 'text' },
                            { key: 'clave_acceso', label: 'Clave acceso', icon: 'bi-key',             type: 'text' },
                            { key: 'usuario',     label: 'Usuario',        icon: 'bi-person-circle',   type: 'text' },
                            { key: 'fecha',       label: 'Fecha emisión',  icon: 'bi-calendar-event',  type: 'date_range' },
                            { key: 'origen',      label: 'Origen',         icon: 'bi-tag',             type: 'select', options: [
                                { v: 'manual',     l: 'Manual' },
                                { v: 'automatica', l: 'Automática' },
                            ]},
                            { key: 'total',       label: 'Total retenido', icon: 'bi-currency-dollar', type: 'number_range' },
                            { key: 'renta',       label: 'Total Renta',    icon: 'bi-percent',         type: 'number_range' },
                            { key: 'iva',         label: 'Total IVA',      icon: 'bi-receipt',         type: 'number_range' },
                            { key: 'isd',         label: 'Total ISD',      icon: 'bi-bank',            type: 'number_range' },
                        ],
                        quickFilters: [
                            { id: 'qf_manual',     label: 'Manuales',    mk: () => ({ key: 'origen', op: '=', value: 'manual',     display: 'Manual' }) },
                            { id: 'qf_automatica', label: 'Automáticas', mk: () => ({ key: 'origen', op: '=', value: 'automatica', display: 'Automática' }) },
                            { id: 'qf_hoy',        label: 'Hoy',         mk: () => FiltrosBusqueda.helpers.hoyMismo('fecha') },
                            { id: 'qf_mes',        label: 'Este mes',    mk: () => FiltrosBusqueda.helpers.esteMes('fecha') },
                            { id: 'qf_mes_pasado', label: 'Mes pasado',  mk: () => FiltrosBusqueda.helpers.mesPasado('fecha') },
                            { id: 'qf_anio',       label: 'Este año',    mk: () => FiltrosBusqueda.helpers.esteAnio('fecha') },
                        ],
                        onApply: () => window.RETV_fetchSearch && window.RETV_fetchSearch(1),
                    }).init();
                });
            </script>

            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'numero'         => 'Nº Retención',
                    'fecha_emision'  => 'Fecha',
                    'cliente_nombre' => 'Cliente',
                    'cliente_ruc'    => 'Identificación',
                    'periodo_fiscal' => 'Período',
                    'total_renta'    => 'Total Renta',
                    'total_iva'      => 'Total IVA',
                    'total_isd'      => 'Total ISD',
                    'total_retenido' => 'Total Ret.',
                    'origen'         => 'Origen',
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>

                <a id="btnExportPdf" href="<?= $urlBase ?>/export-pdf?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                   class="btn btn-outline-danger px-2" title="Exportar PDF">
                    <i class="fa-regular fa-file-pdf"></i> PDF
                </a>
                <a id="btnExportExcel" href="<?= $urlBase ?>/export-excel?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                   class="btn btn-outline-success px-2" title="Exportar Excel">
                    <i class="fa-regular fa-file-excel"></i> Excel
                </a>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <span id="retv-pagination-info" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="retv-pagination" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" <?= $page <= 1 ? 'disabled' : '' ?> onclick="window.RETV_cambiarPagina(<?= $page - 1 ?>)">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                <button type="button" class="btn btn-outline-secondary" <?= $page >= $totalPages ? 'disabled' : '' ?> onclick="window.RETV_cambiarPagina(<?= $page + 1 ?>)">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="retv-scroll w-100">
            <table class="table table-hover table-sm mb-0" style="table-layout:fixed;">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" onclick="window.RETV_ordenar('secuencial')" data-col="numero" style="width:130px;">
                            Nº Retención <i class="bi <?= $ordenCol === 'secuencial' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up text-muted' ?> ms-1 small"></i>
                        </th>
                        <th class="sortable-header" role="button" onclick="window.RETV_ordenar('fecha_emision')" data-col="fecha_emision" style="width:100px;">
                            Fecha <i class="bi <?= $ordenCol === 'fecha_emision' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up text-muted' ?> ms-1 small"></i>
                        </th>
                        <th class="sortable-header" role="button" onclick="window.RETV_ordenar('cliente_nombre')" data-col="cliente_nombre">
                            Cliente <i class="bi <?= $ordenCol === 'cliente_nombre' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up text-muted' ?> ms-1 small"></i>
                        </th>
                        <th class="sortable-header" role="button" onclick="window.RETV_ordenar('cliente_ruc')" data-col="cliente_ruc" style="width:120px;">
                            Identificación <i class="bi <?= $ordenCol === 'cliente_ruc' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up text-muted' ?> ms-1 small"></i>
                        </th>
                        <th class="sortable-header" role="button" onclick="window.RETV_ordenar('periodo_fiscal')" data-col="periodo_fiscal" style="width:90px;">
                            Período <i class="bi <?= $ordenCol === 'periodo_fiscal' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up text-muted' ?> ms-1 small"></i>
                        </th>
                        <th class="text-end sortable-header" role="button" onclick="window.RETV_ordenar('total_renta')" data-col="total_renta" style="width:100px;">
                            Total Renta <i class="bi <?= $ordenCol === 'total_renta' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up text-muted' ?> ms-1 small"></i>
                        </th>
                        <th class="text-end sortable-header" role="button" onclick="window.RETV_ordenar('total_iva')" data-col="total_iva" style="width:100px;">
                            Total IVA <i class="bi <?= $ordenCol === 'total_iva' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up text-muted' ?> ms-1 small"></i>
                        </th>
                        <th class="text-end sortable-header" role="button" onclick="window.RETV_ordenar('total_isd')" data-col="total_isd" style="width:100px;">
                            Total ISD <i class="bi <?= $ordenCol === 'total_isd' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up text-muted' ?> ms-1 small"></i>
                        </th>
                        <th class="text-end sortable-header" role="button" onclick="window.RETV_ordenar('total_retenido')" data-col="total_retenido" style="width:100px;">
                            Total Ret. <i class="bi <?= $ordenCol === 'total_retenido' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up text-muted' ?> ms-1 small"></i>
                        </th>
                        <th class="text-center pe-3 sortable-header" role="button" onclick="window.RETV_ordenar('origen')" data-col="origen" style="width:110px;">
                            Origen <i class="bi <?= $ordenCol === 'origen' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up text-muted' ?> ms-1 small"></i>
                        </th>
                    </tr>
                </thead>
                <tbody id="retv-table-body">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="fa-regular fa-file-lines fs-3 d-block mb-2"></i>No se encontraron retenciones.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $esElectronico = ($r['origen'] ?? 'manual') === 'electronico';
                            $origenBadge = $esElectronico
                                ? '<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">Electrónico</span>'
                                : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Manual</span>';
                            ?>
                            <tr class="retv-row" role="button" tabindex="0"
                                data-row='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>'
                                onclick="window.RETV_abrirModal(this)">
                                <td class="ps-3" data-col="numero">
                                    <code class="text-secondary"><?= htmlspecialchars(($r['establecimiento'] ?? '') . '-' . ($r['punto_emision'] ?? '') . '-' . ($r['secuencial'] ?? '')) ?></code>
                                </td>
                                <td data-col="fecha_emision"><?= !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '-' ?></td>
                                <td class="fw-medium text-truncate" data-col="cliente_nombre" style="max-width:200px;"><?= htmlspecialchars($r['cliente_nombre'] ?? '-') ?></td>
                                <td data-col="cliente_ruc"><small class="text-muted"><?= htmlspecialchars($r['cliente_ruc'] ?? '-') ?></small></td>
                                <td data-col="periodo_fiscal"><small><?= htmlspecialchars($r['periodo_fiscal'] ?? '-') ?></small></td>
                                <td class="text-end" data-col="total_renta">$<?= number_format((float)($r['total_renta'] ?? 0), 2) ?></td>
                                <td class="text-end" data-col="total_iva">$<?= number_format((float)($r['total_iva'] ?? 0), 2) ?></td>
                                <td class="text-end" data-col="total_isd">$<?= number_format((float)($r['total_isd'] ?? 0), 2) ?></td>
                                <td class="text-end fw-bold" data-col="total_retenido">$<?= number_format((float)($r['total_retenido'] ?? 0), 2) ?></td>
                                <td class="text-center pe-3" data-col="origen"><?= $origenBadge ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'modal_retencion.php'; ?>

<script>
    window.RETV_rutaBase = '<?= $urlBase ?>';
    window.RETV_perm = <?= json_encode($perm) ?>;
</script>
<script src="<?= rtrim($base, '/') ?>/js/modulos/clientes_modal.js?v=<?= time() ?>" defer></script>
<script src="<?= rtrim($base, '/') ?>/js/modulos/retenciones_ventas.js?v=<?= time() ?>" defer></script>
