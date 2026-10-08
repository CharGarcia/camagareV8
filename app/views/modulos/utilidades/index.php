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

$base = BASE_URL;
$urlBaseUt = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');

$rows       = $rows ?? [];
$total      = $total ?? 0;
$page       = $page ?? 1;
$totalPages = $totalPages ?? 1;
$perPage    = $perPage ?? 25;
$ordenCol   = $ordenCol ?? 'anio';
$ordenDir   = $ordenDir ?? 'DESC';
$buscar     = $buscar ?? '';
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
$colores = ['borrador' => 'secondary', 'calculado' => 'info', 'contabilizado' => 'success'];
$fmt = fn($v) => '$' . number_format((float) $v, 2);
?>
<style>
    .ut-header { flex-shrink: 0; }
    .utilidades-scroll { max-height: calc(100dvh - 240px); overflow-y: auto; }
    .utilidades-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
    .ut-row { cursor: pointer; }
    .ut-row:hover { background-color: rgba(0, 0, 0, .04); }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>

<div class="ut-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-pie-chart"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="UT_abrirModalNuevo()"><i class="bi bi-plus-lg"></i> Nuevo</button>
    <?php endif; ?>
</div>

<?php if (isset($instalado) && !$instalado): ?>
    <div class="alert alert-warning shadow-sm">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <b>El módulo todavía no está instalado en esta base de datos.</b> Falta ejecutar
        <code>database/migrations/20261008_create_utilidades.sql</code> (crea las tablas, los conceptos
        contables y el menú). Hasta entonces no se puede calcular.
    </div>
<?php endif; ?>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <!-- Buscador y Exportación -->
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php
            // Buscador estándar (FiltrosModal): texto libre (ejercicio) + embudo con los filtros.
            // Las claves (key) deben existir en los mapas de UtilidadesRepository::getListado().
            $opcionesAnio = array_map(fn($a) => ['v' => (string) $a, 'l' => (string) $a], $aniosDisponibles ?? []);
            $tU = 'Utilidades';
            $filtrosUtilidades = [
                ['tab' => $tU, 'key' => 'anio',     'label' => 'Ejercicio fiscal',     'icon' => 'bi-calendar3',       'type' => 'select',       'grupo' => 'Período', 'col' => 4, 'options' => $opcionesAnio],
                ['tab' => $tU, 'key' => 'limite',   'label' => 'Fecha límite de pago', 'icon' => 'bi-calendar-event',  'type' => 'date_range',   'grupo' => 'Período', 'col' => 8, 'atajos' => true],
                ['tab' => $tU, 'key' => 'estado',   'label' => 'Estado',               'icon' => 'bi-flag',            'type' => 'select',       'grupo' => 'Estado',  'col' => 4, 'options' => [
                    ['v' => 'borrador',      'l' => 'Borrador'],
                    ['v' => 'calculado',     'l' => 'Calculado'],
                    ['v' => 'contabilizado', 'l' => 'Contabilizado'],
                ]],
                ['tab' => $tU, 'key' => 'repartir', 'label' => 'Monto a repartir',     'icon' => 'bi-cash-stack',      'type' => 'number_range', 'grupo' => 'Valores', 'col' => 6],
                ['tab' => $tU, 'key' => 'total',    'label' => 'Total a pagar',        'icon' => 'bi-cash-coin',       'type' => 'number_range', 'grupo' => 'Valores', 'col' => 6],
            ];
            ?>
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
            <div id="fmBuscadorUT"></div>
            <input type="hidden" id="buscarUtilidades" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosModal) return;
                    new FiltrosModal({
                        containerId: 'fmBuscadorUT',
                        hiddenInputId: 'buscarUtilidades',
                        placeholder: 'Buscar por ejercicio...',
                        titulo: 'Filtros de utilidades',
                        inputWidth: 320,
                        extraId: 'fmExtraUT',
                        fields: <?= json_encode($filtrosUtilidades, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                        loadingTarget: '#tbodyUtilidades',
                        onApply: () => window.fetchSearch && window.fetchSearch(1),
                    }).init();
                });
            </script>

            <?php // FiltrosModal (extraId) mueve estos botones dentro del input-group del buscador; si el JS no corre, quedan aquí. ?>
            <div id="fmExtraUT" class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'anio'      => 'Ejercicio',
                    'limite'    => 'Fecha límite',
                    'repartir'  => 'Monto a repartir',
                    'empleados' => 'Trabajadores',
                    'total'     => 'A pagar',
                    'excedente' => 'Excedente (IESS)',
                    'estado'    => 'Estado',
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>

                <a id="btnExportPdf" href="<?= $urlBaseUt ?>/export-pdf?b=<?= urlencode($buscar) ?>&orden=<?= urlencode($ordenParam ?? '') ?>"
                    class="btn btn-outline-danger" title="Descargar PDF">
                    <i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span>
                </a>
                <a id="btnExportExcel" href="<?= $urlBaseUt ?>/export-excel?b=<?= urlencode($buscar) ?>&orden=<?= urlencode($ordenParam ?? '') ?>"
                    class="btn btn-outline-success" title="Descargar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i><span class="d-none d-md-inline"> Excel</span>
                </a>
            </div>
        </div>

        <!-- Paginación -->
        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="paginationContainer" class="btn-group btn-group-sm">
                <?php if ($page <= 1): ?>
                    <button type="button" class="btn btn-outline-secondary" disabled><i class="bi bi-chevron-left"></i></button>
                <?php else: ?>
                    <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <?php endif; ?>

                <?php if ($page >= $totalPages): ?>
                    <button type="button" class="btn btn-outline-secondary" disabled><i class="bi bi-chevron-right"></i></button>
                <?php else: ?>
                    <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card-body p-0">
        <div class="utilidades-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="anio" data-col="anio">Ejercicio <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="limite" data-col="limite">Fecha límite de pago <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-end" role="button" data-sort="repartir" data-col="repartir">Monto a repartir <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-center" role="button" data-sort="empleados" data-col="empleados">Trabajadores <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-end" role="button" data-sort="total" data-col="total">A pagar <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end" data-col="excedente">Excedente (IESS)</th>
                        <th class="text-center pe-3 sortable-header" role="button" data-sort="estado" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyUtilidades">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-pie-chart fs-3 d-block mb-2"></i>No se encontraron utilidades.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r):
                            $c = $colores[$r['estado']] ?? 'secondary';
                        ?>
                            <tr class="ut-row" role="button" tabindex="0" data-row='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>' onclick="abrirModalVer(this)">
                                <td class="ps-3 fw-medium" data-col="anio"><?= (int) $r['anio'] ?></td>
                                <td data-col="limite"><?= $r['fecha_limite_pago'] ? date('d-m-Y', strtotime((string) $r['fecha_limite_pago'])) : '-' ?></td>
                                <td class="text-end" data-col="repartir"><?= $fmt($r['monto_repartir']) ?></td>
                                <td class="text-center" data-col="empleados"><?= (int) $r['total_empleados'] ?></td>
                                <td class="text-end fw-bold" data-col="total"><?= $fmt($r['total_valor']) ?></td>
                                <td class="text-end text-muted" data-col="excedente"><?= $fmt($r['total_excedente']) ?></td>
                                <td class="text-center pe-3" data-col="estado">
                                    <span class="badge bg-<?= $c ?> bg-opacity-10 text-<?= $c ?> border border-<?= $c ?> border-opacity-25"><?= htmlspecialchars(ucfirst((string) $r['estado'])) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    window.BASE_URL = '<?= $base ?>';
</script>
<?php include __DIR__ . '/modal_calcular.php'; ?>
<?php include __DIR__ . '/modal_detalle.php'; ?>
<?php if (\App\Helpers\AsientoPestana::puedeVer()): // componente compartido de la pestaña «Asiento contable» ?>
<script src="<?= rtrim($base, '/') ?>/js/modulos/asiento_contable_tab.js?v=<?= asset_ver('/js/modulos/asiento_contable_tab.js') ?>"></script>
<?php endif; ?>
<script src="<?= $base ?>/js/modulos/utilidades.js?v=<?= asset_ver('/js/modulos/utilidades.js') ?>"></script>

<script>
    (function () {
        'use strict';
        const urlBase = '<?= $urlBaseUt ?>';
        const inputBuscar = document.getElementById('buscarUtilidades');
        window.currentSort = '<?= $ordenCol ?>';
        window.currentDir = '<?= $ordenDir ?>';
        // Orden múltiple (Shift+clic): lista completa de criterios, en el formato que lee
        // OrdenListado en PHP. currentSort/currentDir quedan como el principal.
        window.currentSorts = <?= $ordenJson ?? '[]' ?>;
        window.currentPage = <?= $page ?>;
        let sorter = null;
        let timerId;

        function debounce(func, delay = 350) {
            return (...args) => {
                clearTimeout(timerId);
                timerId = setTimeout(() => func.apply(this, args), delay);
            };
        }

        window.cambiarPaginaAjax = (n) => window.fetchSearch(n);

        window.fetchSearch = async (page = 1) => {
            const term = inputBuscar ? inputBuscar.value.trim() : '';
            const orden = window.CMG_ordenParam(window.currentSorts || []);
            const uri = `${urlBase}/searchAjax?b=${encodeURIComponent(term)}&page=${page}&orden=${encodeURIComponent(orden)}`;
            const tbody = document.getElementById('tbodyUtilidades');
            if (tbody) tbody.classList.add('fm-cargando-target');
            try {
                const resp = await fetch(uri);
                const data = await resp.json();
                if (data.ok) {
                    window.currentPage = page;
                    document.getElementById('tbodyUtilidades').innerHTML = data.rows;
                    document.getElementById('paginationContainer').innerHTML = data.pagination;
                    document.getElementById('paginationInfo').textContent = data.info;
                    document.getElementById('btnExportPdf').href = data.pdf_url;
                    document.getElementById('btnExportExcel').href = data.excel_url;
                    if (sorter) sorter.refreshIcons();
                }
            } catch (e) {
                console.error('Error en búsqueda de utilidades:', e);
            } finally {
                if (tbody) tbody.classList.remove('fm-cargando-target');
            }
        };

        sorter = window.CMG_initSort('utilidades', (col, dir, sorts) => {
            window.currentSort  = col;
            window.currentDir   = dir;
            window.currentSorts = sorts;
            fetchSearch(1);
        }, { sorts: window.currentSorts, multi: true, container: '.utilidades-scroll', reload: false });

        if (inputBuscar) inputBuscar.addEventListener('input', debounce(() => fetchSearch(1), 400));
        window.addEventListener('utilidadesActualizado', () => fetchSearch(window.currentPage || 1));
    })();
</script>
