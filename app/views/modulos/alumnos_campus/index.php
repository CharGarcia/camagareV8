<?php

/** @var array $perm */
/** @var string $rutaModulo */
/** @var array $rows */
/** @var int $total */
/** @var int $page */
/** @var int $totalPages */
/** @var int $perPage */
/** @var string $buscar */
/** @var string $ordenJson */
/** @var string $ordenParam */

$base = BASE_URL;
$urlBaseCampus = $base . '/modulos/alumnos-campus';

$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>

<style>
    .alumnos-campus-scroll { max-height: calc(100dvh - 240px); overflow-y: auto; }
    .alumnos-campus-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
    .campus-row { cursor: pointer; }
    .campus-row:hover { background-color: rgba(0, 0, 0, .04); }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-geo-alt-fill me-2 text-primary"></i>Campus</h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="abrirModalCampusCrear()">
            <i class="bi bi-plus-lg me-1"></i> Nuevo
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php
            // Buscador estándar (FiltrosModal): texto libre sobre nombre/dirección + embudo
            // con los filtros. Las claves deben existir en AlumnoCampusRepository::getListado().
            $opcFiltro = $opcionesFiltro ?? [];
            $usuariosFiltro = array_map(fn($x) => ['v' => (string) $x['id'], 'l' => (string) $x['nombre']], $opcFiltro['usuarios'] ?? []);
            $tCampus = 'Campus';
            $filtrosCampus = [
                ['tab' => $tCampus, 'key' => 'nombre',    'label' => 'Nombre',    'icon' => 'bi-geo-alt',  'type' => 'text', 'grupo' => 'Datos', 'col' => 4],
                ['tab' => $tCampus, 'key' => 'direccion', 'label' => 'Dirección', 'icon' => 'bi-signpost', 'type' => 'text', 'grupo' => 'Datos', 'col' => 5],
                ['tab' => $tCampus, 'key' => 'alumnos',   'label' => 'Alumnos vigentes', 'icon' => 'bi-people', 'type' => 'number_range', 'grupo' => 'Datos', 'col' => 3],
                ['tab' => $tCampus, 'key' => 'estado',    'label' => 'Estado',    'icon' => 'bi-flag',     'type' => 'select', 'grupo' => 'Estado', 'col' => 12, 'options' => [
                    ['v' => 'activo',   'l' => 'Activo'],
                    ['v' => 'inactivo', 'l' => 'Inactivo'],
                ]],
                ['tab' => $tCampus, 'key' => 'registro', 'label' => 'Fecha de registro',    'icon' => 'bi-calendar-event', 'type' => 'date_range', 'grupo' => 'Registro', 'col' => 6, 'atajos' => true],
                ['tab' => $tCampus, 'key' => 'usuario',  'label' => 'Usuario que registró', 'icon' => 'bi-person-gear',    'type' => 'select',     'grupo' => 'Registro', 'col' => 6, 'options' => $usuariosFiltro],
            ];
            ?>
            <link rel="stylesheet" href="<?= $base ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
            <script src="<?= $base ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
            <div id="fmBuscadorCampus"></div>
            <input type="hidden" id="buscarCampus" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosModal) return;
                    new FiltrosModal({
                        containerId: 'fmBuscadorCampus',
                        hiddenInputId: 'buscarCampus',
                        placeholder: 'Buscar campus...',
                        titulo: 'Filtros de campus',
                        inputWidth: 360,
                        extraId: 'fmExtraCampus',
                        fields: <?= json_encode($filtrosCampus, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                        loadingTarget: '#tbodyCampus',
                        onApply: () => window.fetchSearchCampus && window.fetchSearchCampus(1),
                    }).init();
                });
            </script>
            <?php // FiltrosModal (extraId) mueve estos botones dentro del input-group del buscador. ?>
            <div id="fmExtraCampus" class="btn-group btn-group-sm">
                <?php
                $columnasTabla = ['nombre' => 'Nombre', 'direccion' => 'Dirección', 'alumnos' => 'Alumnos', 'estado' => 'Estado'];
                echo \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo);
                ?>
                <a id="btnExportPdfCampus" href="<?= $urlBaseCampus ?>/export-pdf?b=<?= urlencode($buscar) ?>&orden=<?= urlencode($ordenParam) ?>" class="btn btn-outline-danger" title="Descargar PDF">
                    <i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span>
                </a>
                <a id="btnExportExcelCampus" href="<?= $urlBaseCampus ?>/export-excel?b=<?= urlencode($buscar) ?>&orden=<?= urlencode($ordenParam) ?>" class="btn btn-outline-success" title="Descargar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i><span class="d-none d-md-inline"> Excel</span>
                </a>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfoCampus" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="paginationContainerCampus" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaCampusAjax(<?= $page - 1 ?>)" <?= $page <= 1 ? 'disabled' : '' ?>><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaCampusAjax(<?= $page + 1 ?>)" <?= $page >= $totalPages ? 'disabled' : '' ?>><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="alumnos-campus-scroll w-100">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" data-sort="nombre" data-col="nombre" role="button">Nombre <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="direccion" data-col="direccion" role="button">Dirección <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header" data-sort="alumnos" data-col="alumnos" role="button" title="Alumnos con matrícula vigente">Alumnos <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center pe-3 sortable-header" data-sort="estado" data-col="estado" role="button">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyCampus">
                    <?php require __DIR__ . '/_filas.php'; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>window.BASE_URL = '<?= $base ?>';</script>
<?php include 'modal_campus.php'; ?>

<script>
    (function () {
        'use strict';
        const urlBase = '<?= $urlBaseCampus ?>';
        let currentPage = <?= (int) $page ?>;
        // Orden múltiple (Shift+clic), en el formato que lee OrdenListado en PHP.
        window.currentSorts = <?= $ordenJson ?: '[]' ?>;
        let sorter = null;

        async function fetchSearch(page = currentPage) {
            currentPage = page;
            const b = document.getElementById('buscarCampus').value.trim();
            const orden = window.CMG_ordenParam(window.currentSorts || []);
            const tbody = document.getElementById('tbodyCampus');
            tbody.classList.add('fm-cargando-target');
            try {
                const resp = await fetch(`${urlBase}/searchAjax?b=${encodeURIComponent(b)}&page=${page}&orden=${encodeURIComponent(orden)}`);
                const data = await resp.json();
                if (data.ok) {
                    tbody.innerHTML = data.rows;
                    document.getElementById('paginationContainerCampus').innerHTML = data.pagination;
                    document.getElementById('paginationInfoCampus').textContent = data.info;
                    document.getElementById('btnExportPdfCampus').href = data.pdf_url;
                    document.getElementById('btnExportExcelCampus').href = data.excel_url;
                    if (sorter) sorter.refreshIcons();
                }
            } catch (e) { console.error(e); }
            finally { tbody.classList.remove('fm-cargando-target'); }
        }
        // El modal de campus refresca el listado con esta función al guardar/eliminar.
        window.fetchSearchCampus = fetchSearch;
        window.cambiarPaginaCampusAjax = (p) => fetchSearch(p);

        // multi: Shift+clic encadena hasta 3 columnas; reload:false porque fetchSearch
        // repinta todo lo que depende del orden.
        sorter = window.CMG_initSort('alumnos-campus', (col, dir, sorts) => {
            window.currentSorts = sorts;
            fetchSearch(1);
        }, { sorts: window.currentSorts, multi: true, container: '.alumnos-campus-scroll', reload: false });
    })();
</script>
