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
/** @var array $puntos */
/** @var array $establecimientos */
/** @var array $tarifasIva */
/** @var array $formasPago */
/** @var array $tiposIdentificacion */

$base = BASE_URL;
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
    .factura-reembolso-row { cursor: pointer; }
    .factura-reembolso-row:hover { background-color: rgba(0, 0, 0, .04); }

    .modal-fr .modal-header { background-color: #f8f9fa; border-bottom: 1px solid #dee2e6; padding: 0.75rem 1rem; }
    .modal-fr .modal-body { padding: 0 !important; }
    .modal-fr label { font-size: 0.85rem; font-weight: 600; color: #495057; margin-bottom: 3px !important; }

    .fr-table-detalle th { font-size: 0.7rem !important; text-transform: uppercase; background-color: #f8f9fa; padding: 4px 8px !important; }
    .row-fr-detalle:hover, .row-fr-tercero:hover { background-color: rgba(13, 110, 253, 0.03); }
</style>

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-arrow-repeat me-2 text-primary"></i><?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear']): ?>
        <?php if (empty($puntos)): ?>
            <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" disabled
                title="No hay ningún punto de emisión con el secuencial de &quot;Facturas de reembolso&quot; configurado.">
                <i class="bi bi-plus-lg me-1"></i> Nueva
            </button>
        <?php else: ?>
            <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="window.FR_abrirModalNuevo()">
                <i class="bi bi-plus-lg me-1"></i> Nueva
            </button>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if (empty($puntos)): ?>
    <div class="alert alert-warning d-flex align-items-center gap-2 py-2 px-3 mb-3">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div class="small">
            Ningún punto de emisión tiene configurado el secuencial para <strong>Facturas de reembolso</strong>.
            Configúralo en <strong>Empresa → Secuenciales</strong> para poder emitir.
        </div>
    </div>
<?php endif; ?>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_busqueda.css?v=<?= time() ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_busqueda.js?v=<?= time() ?>"></script>
            <div id="fbBuscadorFR" style="width: 480px;"></div>
            <input type="hidden" id="buscarFR" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosBusqueda) return;
                    new FiltrosBusqueda({
                        containerId: 'fbBuscadorFR',
                        hiddenInputId: 'buscarFR',
                        placeholder: 'Buscar...',
                        fields: [
                            { key: 'cliente',        label: 'Cliente',       icon: 'bi-person',          type: 'text' },
                            { key: 'ruc',            label: 'RUC / Cédula',  icon: 'bi-card-text',       type: 'text' },
                            { key: 'numero',         label: 'Número',        icon: 'bi-hash',            type: 'text' },
                            { key: 'usuario',        label: 'Usuario',       icon: 'bi-person-circle',   type: 'text' },
                            { key: 'fecha',          label: 'Fecha emisión', icon: 'bi-calendar-event',  type: 'date_range' },
                            { key: 'monto',          label: 'Monto total',   icon: 'bi-currency-dollar', type: 'number_range' },
                            { key: 'reembolso',      label: 'Base reembolso',icon: 'bi-arrow-repeat',    type: 'number_range' },
                            { key: 'estado',         label: 'Estado',        icon: 'bi-flag',            type: 'select', options: [
                                { v: 'borrador',   l: 'Borrador' },
                                { v: 'autorizado', l: 'Autorizado' },
                                { v: 'anulado',    l: 'Anulado' },
                            ]},
                        ],
                        quickFilters: [
                            { id: 'qf_borrador',   label: 'Borrador',    mk: () => ({ key: 'estado', op: '=', value: 'borrador',   display: 'Borrador' }) },
                            { id: 'qf_autorizado', label: 'Autorizadas', mk: () => ({ key: 'estado', op: '=', value: 'autorizado', display: 'Autorizado' }) },
                            { id: 'qf_anulado',    label: 'Anuladas',    mk: () => ({ key: 'estado', op: '=', value: 'anulado',    display: 'Anulado' }) },
                            { id: 'qf_hoy',        label: 'Hoy',         mk: () => FiltrosBusqueda.helpers.hoyMismo('fecha') },
                            { id: 'qf_mes',        label: 'Este mes',    mk: () => FiltrosBusqueda.helpers.esteMes('fecha') },
                        ],
                        onApply: () => window.FR_fetchSearch && window.FR_fetchSearch(1),
                    }).init();
                });
            </script>

            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'numero'                        => 'Número',
                    'fecha_emision'                 => 'Fecha',
                    'cliente_nombre'                => 'Cliente',
                    'cliente_ruc'                    => 'Identificación',
                    'cantidad_terceros'              => 'Terceros',
                    'total_reembolsado'              => 'Reembolsado',
                    'importe_total'                  => 'Total',
                    'usuario_nombre'                 => 'Usuario',
                    'estado'                          => 'Estado',
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>

                <a id="btnExportPdf" href="<?= $urlBase ?>/export-pdf?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                    class="btn btn-outline-danger px-2" title="Descargar PDF">
                    <i class="bi bi-file-earmark-pdf"></i> PDF
                </a>
                <a id="btnExportExcel" href="<?= $urlBase ?>/export-excel?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                    class="btn btn-outline-success px-2" title="Descargar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                </a>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <span id="fr-pagination-info" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="fr-pagination" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" <?= $page <= 1 ? 'disabled' : '' ?> onclick="window.FR_cambiarPaginaAjax(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" <?= $page >= $totalPages ? 'disabled' : '' ?> onclick="window.FR_cambiarPaginaAjax(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="factura-reembolso-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" onclick="window.FR_ordenar('secuencial')" data-col="numero">Número <i class="bi <?= $ordenCol === 'secuencial' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i></th>
                        <th class="sortable-header" role="button" onclick="window.FR_ordenar('fecha_emision')" data-col="fecha_emision">Fecha <i class="bi <?= $ordenCol === 'fecha_emision' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i></th>
                        <th class="sortable-header" role="button" onclick="window.FR_ordenar('cliente_nombre')" data-col="cliente_nombre">Cliente <i class="bi <?= $ordenCol === 'cliente_nombre' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i></th>
                        <th class="sortable-header" role="button" onclick="window.FR_ordenar('cliente_ruc')" data-col="cliente_ruc">Identificación <i class="bi <?= $ordenCol === 'cliente_ruc' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i></th>
                        <th class="text-center" data-col="cantidad_terceros">Terceros</th>
                        <th class="sortable-header text-end" role="button" onclick="window.FR_ordenar('total_base_imponible_reembolso')" data-col="total_reembolsado">Reembolsado <i class="bi <?= $ordenCol === 'total_base_imponible_reembolso' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i></th>
                        <th class="sortable-header text-end" role="button" onclick="window.FR_ordenar('importe_total')" data-col="importe_total">Total <i class="bi <?= $ordenCol === 'importe_total' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i></th>
                        <th class="sortable-header" role="button" onclick="window.FR_ordenar('usuario_nombre')" data-col="usuario_nombre">Usuario <i class="bi <?= $ordenCol === 'usuario_nombre' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i></th>
                        <th class="text-center pe-3 sortable-header" role="button" onclick="window.FR_ordenar('estado')" data-col="estado">Estado <i class="bi <?= $ordenCol === 'estado' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="fr-table-body">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted"><i class="bi bi-receipt fs-3 d-block mb-2"></i>No se encontraron facturas de reembolso.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $estado = $r['estado'] ?? 'borrador';
                            $estadoClass = match ($estado) {
                                'autorizado' => 'bg-success bg-opacity-10 text-success border-success',
                                'anulado'    => 'bg-danger bg-opacity-10 text-danger border-danger',
                                'borrador'   => 'bg-secondary bg-opacity-10 text-secondary border-secondary',
                                default      => 'bg-primary bg-opacity-10 text-primary border-primary',
                            };
                            $totalReembolsado = (float) ($r['total_base_imponible_reembolso'] ?? 0) + (float) ($r['total_impuesto_reembolso'] ?? 0);
                            ?>
                            <tr class="factura-reembolso-row" role="button" tabindex="0" data-row='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>' onclick="window.FR_abrirModalFR(this)">
                                <td class="ps-3" data-col="numero"><code class="text-secondary"><?= htmlspecialchars(($r['establecimiento'] ?? '') . '-' . ($r['punto_emision'] ?? '') . '-' . ($r['secuencial'] ?? '')) ?></code></td>
                                <td data-col="fecha_emision"><?= !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '-' ?></td>
                                <td class="fw-medium text-truncate" data-col="cliente_nombre" style="max-width:200px"><?= htmlspecialchars($r['cliente_nombre'] ?? '-') ?></td>
                                <td data-col="cliente_ruc"><small class="text-muted"><?= htmlspecialchars($r['cliente_ruc'] ?? '-') ?></small></td>
                                <td class="text-center" data-col="cantidad_terceros"><span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25"><?= (int) ($r['cantidad_terceros'] ?? 0) ?></span></td>
                                <td class="text-end" data-col="total_reembolsado">$<?= number_format($totalReembolsado, 2) ?></td>
                                <td class="text-end fw-bold" data-col="importe_total">$<?= number_format((float) ($r['importe_total'] ?? 0), 2) ?></td>
                                <td data-col="usuario_nombre"><?= htmlspecialchars($r['usuario_nombre'] ?? '-') ?></td>
                                <td class="text-center pe-3" data-col="estado">
                                    <span class="badge <?= $estadoClass ?> border border-opacity-25"><?= ucfirst($estado) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'modal_fr.php'; ?>

<script>
    window.fr_dec_p = <?= (int) ($empresa['decimales_precio'] ?? 2) ?>;
    window.FR_ID_FORMA_PAGO_SRI_DEF = <?= isset($empresa['id_forma_pago_sri_def']) && $empresa['id_forma_pago_sri_def'] !== null ? (int) $empresa['id_forma_pago_sri_def'] : 'null' ?>;
    window.FR_STORAGE_KEY = 'fr_borrador_' + <?= (int) ($_SESSION['id_empresa'] ?? 0) ?> + '_' + <?= (int) ($_SESSION['id_usuario'] ?? 0) ?>;
    window.FR_ORDEN_COL = <?= json_encode($ordenCol ?? 'fecha_emision') ?>;
    window.FR_ORDEN_DIR = <?= json_encode($ordenDir ?? 'DESC') ?>;
    // Catálogo de tarifas IVA para el selector por línea (honorarios) del detalle.
    window.FR_TARIFAS_IVA = <?= json_encode(array_map(fn($t) => [
        'porcentaje' => (string) $t['porcentaje_iva'],
        'codigo'     => (string) $t['codigo'],
        'label'      => (string) $t['tarifa'],
    ], $tarifasIva ?? [])) ?>;
</script>
<script src="<?= rtrim($base, '/') ?>/js/modulos/asiento_contable_tab.js?v=<?= time() ?>" defer></script>
<script src="<?= rtrim($base, '/') ?>/js/modulos/factura_reembolso.js?v=<?= time() ?>" defer></script>
