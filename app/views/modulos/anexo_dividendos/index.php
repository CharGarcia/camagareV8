<?php

use App\Helpers\PreferenciasHelper;

/** @var string $titulo */
/** @var array  $perm */
/** @var string $rowsHtml */
/** @var int    $total */
/** @var int    $page */
/** @var int    $totalPages */
/** @var int    $perPage */
/** @var string $buscar */
/** @var string $ordenCol */
/** @var string $ordenDir */
/** @var array  $vistaConfig */
/** @var array  $anios */
/** @var string $rutaModulo */
/** @var bool   $sinTablas */
/** @var array  $catalogo */

$base       = rtrim(BASE_URL, '/');
$urlBaseAdi = $base . '/' . ltrim($rutaModulo, '/');

$total      = $total ?? 0;
$page       = $page ?? 1;
$totalPages = $totalPages ?? 1;
$perPage    = $perPage ?? 20;
$ordenCol   = $ordenCol ?? 'anio';
$ordenDir   = $ordenDir ?? 'desc';
$buscar     = $buscar ?? '';
$anios      = $anios ?? [];

$desde = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$hasta = $total > 0 ? min($page * $perPage, $total) : 0;

$columnasTabla = [
    'anio'                => 'Año',
    'id_informante'       => 'Identificación',
    'razon_social'        => 'Informante',
    'tipo_informante'     => 'Tipo informante',
    'total_beneficiarios' => 'Beneficiarios',
    'total_distribuido'   => 'Distribuido',
    'total_gravado'       => 'Ingreso gravado',
    'total_retencion'     => 'Retención',
    'estado'              => 'Estado',
];

$pestanas = [
    'adi-pane-informante' => 'Informante y origen',
    'adi-pane-utilidades' => 'Utilidades',
    'adi-pane-dividendos' => 'Dividendos',
    'adi-pane-validacion' => 'Validaciones',
];
?>
<style>
    .adi-header { flex-shrink: 0; }

    .adi-scroll { max-height: calc(100dvh - 240px); overflow-y: auto; }

    .adi-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }

    .adi-row { cursor: pointer; }
    .adi-row:hover { background-color: rgba(0, 0, 0, .04); }

    #modalAdi .table-sm td { vertical-align: middle; }
    #modalAdi .adi-sub-scroll { max-height: 46vh; overflow: auto; }
    #modalAdi .adi-sub-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; }

    .adi-nota { font-size: .78rem; }
    .adi-tercero-lista { position: absolute; z-index: 5090; width: 100%; max-height: 220px; overflow-y: auto; }
</style>
<?= PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>
<?= PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>

<div class="adi-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-cash-coin text-success"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear'] && !$sinTablas): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="ADI_nuevo()">
            <i class="bi bi-plus-lg"></i> Nuevo
        </button>
    <?php endif; ?>
</div>

<?php if ($sinTablas): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-1"></i>
        Las tablas del módulo aún no existen en esta base. Ejecute
        <code>database/2026-09-08_anexo_dividendos_adi.sql</code> y vuelva a entrar.
    </div>
<?php endif; ?>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">

        <!-- Buscador y exportación -->
        <div class="d-flex align-items-center gap-2">
            <link rel="stylesheet" href="<?= $base ?>/css/components/filtros_busqueda.css?v=<?= time() ?>">
            <script src="<?= $base ?>/js/components/filtros_busqueda.js?v=<?= time() ?>"></script>
            <div id="fbBuscadorADI" style="width: 480px;"></div>
            <input type="hidden" id="buscarAdi" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosBusqueda) return;
                    new FiltrosBusqueda({
                        containerId: 'fbBuscadorADI',
                        hiddenInputId: 'buscarAdi',
                        fields: [
                            { key: 'anio',           label: 'Año informado',   icon: 'bi-calendar3',  type: 'number_range' },
                            { key: 'informante',     label: 'Informante',      icon: 'bi-building',   type: 'text' },
                            { key: 'ruc',            label: 'Identificación',  icon: 'bi-card-text',  type: 'text' },
                            { key: 'observaciones',  label: 'Observaciones',   icon: 'bi-chat-left-text', type: 'text' },
                            { key: 'estado',         label: 'Estado',          icon: 'bi-flag',       type: 'select', options: [
                                { v: 'borrador',   l: 'Borrador' },
                                { v: 'generado',   l: 'Generado' },
                                { v: 'presentado', l: 'Presentado' },
                            ]},
                            { key: 'tipo_informante', label: 'Tipo de informante', icon: 'bi-people', type: 'select', options: [
                                <?php foreach ($catalogo['tipo_informante'] as $cod => $nom): ?>
                                { v: '<?= $cod ?>', l: '<?= htmlspecialchars($nom, ENT_QUOTES) ?>' },
                                <?php endforeach; ?>
                            ]},
                        ],
                        quickFilters: [
                            { id: 'qf_borrador',  label: 'Borradores', mk: () => ({ key: 'estado', op: '=', value: 'borrador', display: 'Borrador' }) },
                            { id: 'qf_generado',  label: 'Generados',  mk: () => ({ key: 'estado', op: '=', value: 'generado', display: 'Generado' }) },
                            { id: 'qf_anio_ant',  label: 'Año anterior', mk: () => ({ key: 'anio', op: '=', value: String(new Date().getFullYear() - 1), display: String(new Date().getFullYear() - 1) }) },
                        ],
                        onApply: () => window.fetchSearch && window.fetchSearch(1),
                    }).init();
                });
            </script>

            <div class="btn-group btn-group-sm">
                <?= PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>

                <a id="btnExportPdfAdi" class="btn btn-outline-danger" title="Descargar PDF"
                   href="<?= $urlBaseAdi ?>/export-pdf?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>">
                    <i class="bi bi-file-earmark-pdf"></i> PDF
                </a>
                <a id="btnExportExcelAdi" class="btn btn-outline-success" title="Descargar Excel"
                   href="<?= $urlBaseAdi ?>/export-excel?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                </a>
            </div>
        </div>

        <!-- Paginación -->
        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfoAdi" class="text-muted small fw-medium"><?= $desde ?>-<?= $hasta ?>/<?= $total ?></span>
            <div id="paginationContainerAdi" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary border-end-0 rounded-end-0" <?= $page <= 1 ? 'disabled' : '' ?>
                        onclick="cambiarPaginaAjax(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary rounded-start-0" <?= $page >= $totalPages ? 'disabled' : '' ?>
                        onclick="cambiarPaginaAjax(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card-body p-0">
        <div class="adi-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="anio" data-col="anio">Año <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="id_informante" data-col="id_informante">Identificación <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="razon_social" data-col="razon_social">Informante <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="tipo_informante" data-col="tipo_informante">Tipo informante <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-center" role="button" data-sort="total_beneficiarios" data-col="total_beneficiarios">Beneficiarios <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-end" role="button" data-sort="total_distribuido" data-col="total_distribuido">Distribuido <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-end" role="button" data-sort="total_gravado" data-col="total_gravado">Ingreso gravado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-end" role="button" data-sort="total_retencion" data-col="total_retencion">Retención <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-center pe-3" role="button" data-sort="estado" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyAdi"><?= $rowsHtml ?></tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/modal.php'; ?>
<?php require __DIR__ . '/modal_beneficiario.php'; ?>
<?php require __DIR__ . '/modal_detalle.php'; ?>

<script>
    window.ADI_URL_BASE  = '<?= $urlBaseAdi ?>';
    window.ADI_PERM      = <?= json_encode($perm) ?>;
    // Los 257 países ya viajan como <option> del modal, así que se excluyen del
    // catálogo que consume el JavaScript para no duplicarlos en la página.
    window.ADI_CAT       = <?= json_encode(array_diff_key($catalogo, ['paises' => null]), JSON_UNESCAPED_UNICODE) ?>;
    window.ADI_MODULO    = '<?= $rutaModulo ?>';
    window.ADI_SORT_INI  = { col: '<?= htmlspecialchars($ordenCol) ?>', dir: '<?= htmlspecialchars($ordenDir) ?>' };
    window.ADI_DEFAULTS  = <?= json_encode($defaults ?? [], JSON_UNESCAPED_UNICODE) ?>;
    window.ADI_SBUS      = <?= json_encode((object) ($sbus ?? []), JSON_UNESCAPED_UNICODE) ?>;
</script>
<?= PreferenciasHelper::getJavascriptVariables($rutaModulo) ?>
<script src="<?= $base ?>/js/modulos/anexo_dividendos.js?v=<?= time() ?>"></script>
