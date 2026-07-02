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
/** @var array $empresa */
/** @var array $vistaConfig */

$base    = BASE_URL;
$urlBase = $base . '/' . $rutaModulo;

$rows       = $rows ?? [];
$total      = $total ?? 0;
$page       = $page ?? 1;
$totalPages = $totalPages ?? 1;
$perPage    = $perPage ?? 20;
$ordenCol   = $ordenCol ?? 'fecha_emision';
$ordenDir   = $ordenDir ?? 'DESC';
$buscar     = $buscar ?? '';
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>

<style>
    .ret-scroll {
        max-height: calc(100dvh - 240px);
        overflow-y: auto;
    }

    .ret-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 10;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }

    .ret-row {
        cursor: pointer;
    }

    .ret-row:hover {
        background-color: rgba(0, 0, 0, .04);
    }

    .modal-ret .modal-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
        padding: 0.75rem 1rem;
    }

    .modal-ret .nav-tabs .nav-link {
        font-size: 0.875rem;
    }

    .modal-ret .modal-body {
        padding: 0 !important;
    }

    .modal-ret label {
        font-size: 0.85rem;
        font-weight: 600;
        color: #495057;
        margin-bottom: 3px !important;
    }

    .table-lineas th {
        font-size: 0.7rem !important;
        text-transform: uppercase;
        background-color: #f8f9fa;
        padding: 4px 8px !important;
    }
</style>

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold">
        <i class="fa-solid fa-file-invoice-dollar me-2 text-primary"></i><?= htmlspecialchars($titulo) ?>
    </h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="window.RET_abrirModalNuevo()">
            <i class="fa-solid fa-plus me-1"></i> Nueva
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_busqueda.css?v=<?= time() ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_busqueda.js?v=<?= time() ?>"></script>
            <div id="fbBuscadorRet" style="width: 480px;"></div>
            <input type="hidden" id="buscarRet" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosBusqueda) return;
                    new FiltrosBusqueda({
                        containerId: 'fbBuscadorRet',
                        hiddenInputId: 'buscarRet',
                        fields: [
                            { key: 'proveedor',    label: 'Proveedor',      icon: 'bi-building',        type: 'text' },
                            { key: 'ruc',          label: 'RUC',            icon: 'bi-card-text',       type: 'text' },
                            { key: 'numero',       label: 'Secuencial',     icon: 'bi-hash',            type: 'text' },
                            { key: 'doc_sustento', label: 'Doc. sustento',  icon: 'bi-receipt',         type: 'text' },
                            { key: 'clave_acceso', label: 'Clave de acceso', icon: 'bi-key',            type: 'text' },
                            { key: 'periodo',      label: 'Período',        icon: 'bi-calendar3',       type: 'text' },
                            { key: 'usuario',      label: 'Usuario',        icon: 'bi-person-circle',   type: 'text' },
                            { key: 'fecha',        label: 'Fecha emisión',  icon: 'bi-calendar-event',  type: 'date_range' },
                            { key: 'total',        label: 'Total retenido', icon: 'bi-currency-dollar', type: 'number_range' },
                            { key: 'renta',        label: 'Total Renta',    icon: 'bi-percent',         type: 'number_range' },
                            { key: 'iva',          label: 'Total IVA',      icon: 'bi-receipt',         type: 'number_range' },
                            { key: 'isd',          label: 'Total ISD',      icon: 'bi-bank',            type: 'number_range' },
                            { key: 'estado',       label: 'Estado',         icon: 'bi-flag',            type: 'select', options: [
                                { v: 'autorizado', l: 'Autorizado' },
                                { v: 'pendiente',  l: 'Pendiente' },
                                { v: 'anulado',    l: 'Anulado' },
                            ]},
                        ],
                        quickFilters: [
                            { id: 'qf_hoy',        label: 'Hoy',         mk: () => FiltrosBusqueda.helpers.hoyMismo('fecha') },
                            { id: 'qf_mes',        label: 'Este mes',    mk: () => FiltrosBusqueda.helpers.esteMes('fecha') },
                            { id: 'qf_mes_pasado', label: 'Mes pasado',  mk: () => FiltrosBusqueda.helpers.mesPasado('fecha') },
                            { id: 'qf_anio',       label: 'Este año',    mk: () => FiltrosBusqueda.helpers.esteAnio('fecha') },
                        ],
                        onApply: () => window.RET_fetchSearch && window.RET_fetchSearch(1),
                    }).init();
                });
            </script>

            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'numero'               => 'Nº Retención',
                    'fecha_emision'        => 'Fecha',
                    'proveedor_nombre'     => 'Proveedor',
                    'proveedor_ruc'        => 'Identificación',
                    'tipo_doc_sustento'    => 'Tipo Doc.',
                    'num_doc_sustento'     => 'Doc. Sustento',
                    'periodo_fiscal'       => 'Período',
                    'total_retenido'       => 'Total Ret.',
                    'estado'               => 'Estado',
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
            <span id="ret-pagination-info" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="ret-pagination" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" <?= $page <= 1 ? 'disabled' : '' ?> onclick="window.RET_cambiarPagina(<?= $page - 1 ?>)">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                <button type="button" class="btn btn-outline-secondary" <?= $page >= $totalPages ? 'disabled' : '' ?> onclick="window.RET_cambiarPagina(<?= $page + 1 ?>)">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="ret-scroll w-100">
            <table class="table table-hover table-sm mb-0" style="table-layout:fixed;">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" onclick="window.RET_ordenar('secuencial')" data-col="numero" style="width:130px;">
                            Nº Retención <i class="bi <?= $ordenCol === 'secuencial' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up text-muted' ?> ms-1 small"></i>
                        </th>
                        <th class="sortable-header" role="button" onclick="window.RET_ordenar('fecha_emision')" data-col="fecha_emision" style="width:100px;">
                            Fecha <i class="bi <?= $ordenCol === 'fecha_emision' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up text-muted' ?> ms-1 small"></i>
                        </th>
                        <th class="sortable-header" role="button" onclick="window.RET_ordenar('proveedor_nombre')" data-col="proveedor_nombre">
                            Proveedor <i class="bi <?= $ordenCol === 'proveedor_nombre' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up text-muted' ?> ms-1 small"></i>
                        </th>
                        <th class="sortable-header" role="button" onclick="window.RET_ordenar('proveedor_ruc')" data-col="proveedor_ruc" style="width:120px;">
                            Identificación <i class="bi <?= $ordenCol === 'proveedor_ruc' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up text-muted' ?> ms-1 small"></i>
                        </th>
                        <th class="sortable-header" role="button" onclick="window.RET_ordenar('tipo_doc_sustento')" data-col="tipo_doc_sustento" style="width:110px;">
                            Tipo Doc. <i class="bi <?= $ordenCol === 'tipo_doc_sustento' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up text-muted' ?> ms-1 small"></i>
                        </th>
                        <th class="sortable-header" role="button" onclick="window.RET_ordenar('num_doc_sustento')" data-col="num_doc_sustento" style="width:150px;">
                            Doc. Sustento <i class="bi <?= $ordenCol === 'num_doc_sustento' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up text-muted' ?> ms-1 small"></i>
                        </th>
                        <th class="sortable-header" role="button" onclick="window.RET_ordenar('periodo_fiscal')" data-col="periodo_fiscal" style="width:90px;">
                            Período <i class="bi <?= $ordenCol === 'periodo_fiscal' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up text-muted' ?> ms-1 small"></i>
                        </th>
                        <th class="text-end sortable-header" role="button" onclick="window.RET_ordenar('total_retenido')" data-col="total_retenido" style="width:100px;">
                            Total Ret. <i class="bi <?= $ordenCol === 'total_retenido' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up text-muted' ?> ms-1 small"></i>
                        </th>
                        <th class="text-center sortable-header" role="button" onclick="window.RET_ordenar('estado_correo')" data-col="estado_correo" style="width:100px;">
                            Correo <i class="bi <?= $ordenCol === 'estado_correo' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up text-muted' ?> ms-1 small"></i>
                        </th>
                        <th class="text-center pe-3 sortable-header" role="button" onclick="window.RET_ordenar('estado')" data-col="estado" style="width:110px;">
                            Estado <i class="bi <?= $ordenCol === 'estado' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up text-muted' ?> ms-1 small"></i>
                        </th>
                    </tr>
                </thead>
                <tbody id="ret-table-body">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="fa-regular fa-file-lines fs-3 d-block mb-2"></i>No se encontraron retenciones.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $estado = $r['estado'] ?? 'borrador';
                            $estadoClass = match ($estado) {
                                'autorizada'    => 'bg-success bg-opacity-10 text-success border-success',
                                'anulada'       => 'bg-danger bg-opacity-10 text-danger border-danger',
                                'no_autorizada' => 'bg-warning bg-opacity-10 text-warning border-warning',
                                'borrador'      => 'bg-secondary bg-opacity-10 text-secondary border-secondary',
                                default         => 'bg-primary bg-opacity-10 text-primary border-primary',
                            };
                            $estadoCorreo = $r['estado_correo'] ?? 'pendiente';
                            $correoClass  = $estadoCorreo === 'enviado'
                                ? 'bg-success bg-opacity-10 text-success border-success'
                                : 'bg-secondary bg-opacity-10 text-secondary border-secondary';
                            ?>
                            <tr class="ret-row" role="button" tabindex="0"
                                data-row='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>'
                                onclick="window.RET_abrirModal(this)">
                                <td class="ps-3" data-col="numero">
                                    <code class="text-secondary"><?= htmlspecialchars(($r['establecimiento'] ?? '') . '-' . ($r['punto_emision'] ?? '') . '-' . ($r['secuencial'] ?? '')) ?></code>
                                </td>
                                <td data-col="fecha_emision"><?= !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '-' ?></td>
                                <td class="fw-medium text-truncate" data-col="proveedor_nombre" style="max-width:200px;"><?= htmlspecialchars($r['proveedor_nombre'] ?? '-') ?></td>
                                <td data-col="proveedor_ruc"><small class="text-muted"><?= htmlspecialchars($r['proveedor_ruc'] ?? '-') ?></small></td>
                                <td data-col="tipo_doc_sustento"><small class="text-muted"><?= match((string)($r['tipo_doc_sustento'] ?? '01')) {
                                    '01' => 'Factura', '03' => 'Liquidación', '05' => 'Nota débito',
                                    default => htmlspecialchars((string)($r['tipo_doc_sustento'] ?? '-')),
                                } ?></small></td>
                                <td data-col="num_doc_sustento"><small class="text-muted"><?= htmlspecialchars($r['num_doc_sustento'] ?? '-') ?></small></td>
                                <td data-col="periodo_fiscal"><small><?= htmlspecialchars($r['periodo_fiscal'] ?? '-') ?></small></td>
                                <td class="text-end fw-bold" data-col="total_retenido">$<?= number_format((float)($r['total_retenido'] ?? 0), 2) ?></td>
                                <td class="text-center" data-col="estado_correo">
                                    <span class="badge <?= $correoClass ?> border border-opacity-25"><?= ucfirst($estadoCorreo) ?></span>
                                </td>
                                <td class="text-center pe-3" data-col="estado">
                                    <span class="badge <?= $estadoClass ?> border border-opacity-25"><?= ucfirst(str_replace('_', ' ', $estado)) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'modal_retencion.php'; ?>

<?php include MVC_APP . '/views/modulos/proveedores/modal_proveedor.php'; ?>

<script>
    window.RET_rutaBase = '<?= $urlBase ?>';
    window.RET_perm = <?= json_encode($perm) ?>;
    // Estado inicial de ordenamiento (desde la preferencia persistida en el servidor)
    window.RET_ordenCol = '<?= $ordenCol ?>';
    window.RET_ordenDir = '<?= $ordenDir ?>';
</script>
<script src="<?= rtrim($base, '/') ?>/js/modulos/proveedores_modal.js?v=<?= time() ?>"></script>
<script src="<?= rtrim($base, '/') ?>/js/modulos/retenciones_compras.js?v=<?= time() ?>" defer></script>