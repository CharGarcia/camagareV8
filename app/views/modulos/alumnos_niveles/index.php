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
$urlBaseNivel = $base . '/modulos/alumnos-niveles';

$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>

<style>
    .alumnos-niveles-scroll { max-height: calc(100dvh - 240px); overflow-y: auto; }
    .alumnos-niveles-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
    .nivel-row { cursor: pointer; }
    .nivel-row:hover { background-color: rgba(0, 0, 0, .04); }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-mortarboard-fill me-2 text-primary"></i>Niveles / Cursos</h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="abrirModalNivelCrear()">
            <i class="bi bi-plus-lg me-1"></i> Nuevo
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php
            // Buscador estándar (FiltrosModal): texto libre sobre el nombre + embudo
            // con los filtros. Las claves deben existir en AlumnoNivelRepository::getListado().
            $opcFiltro = $opcionesFiltro ?? [];
            $usuariosFiltro = array_map(fn($x) => ['v' => (string) $x['id'], 'l' => (string) $x['nombre']], $opcFiltro['usuarios'] ?? []);
            $tNivel = 'Nivel / Curso';
            $filtrosNivel = [
                ['tab' => $tNivel, 'key' => 'nombre',  'label' => 'Nombre',  'icon' => 'bi-mortarboard',       'type' => 'text',         'grupo' => 'Datos', 'col' => 6],
                ['tab' => $tNivel, 'key' => 'orden',   'label' => 'Orden',   'icon' => 'bi-sort-numeric-down', 'type' => 'number_range', 'grupo' => 'Datos', 'col' => 3],
                ['tab' => $tNivel, 'key' => 'alumnos', 'label' => 'Alumnos vigentes', 'icon' => 'bi-people', 'type' => 'number_range', 'grupo' => 'Datos', 'col' => 3],
                ['tab' => $tNivel, 'key' => 'estado',    'label' => 'Estado',    'icon' => 'bi-flag',     'type' => 'select', 'grupo' => 'Estado', 'col' => 12, 'options' => [
                    ['v' => 'activo',   'l' => 'Activo'],
                    ['v' => 'inactivo', 'l' => 'Inactivo'],
                ]],
                ['tab' => $tNivel, 'key' => 'registro', 'label' => 'Fecha de registro',    'icon' => 'bi-calendar-event', 'type' => 'date_range', 'grupo' => 'Registro', 'col' => 6, 'atajos' => true],
                ['tab' => $tNivel, 'key' => 'usuario',  'label' => 'Usuario que registró', 'icon' => 'bi-person-gear',    'type' => 'select',     'grupo' => 'Registro', 'col' => 6, 'options' => $usuariosFiltro],
            ];
            ?>
            <link rel="stylesheet" href="<?= $base ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
            <script src="<?= $base ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
            <div id="fmBuscadorNivel"></div>
            <input type="hidden" id="buscarNivel" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosModal) return;
                    new FiltrosModal({
                        containerId: 'fmBuscadorNivel',
                        hiddenInputId: 'buscarNivel',
                        placeholder: 'Buscar nivel/curso...',
                        titulo: 'Filtros de niveles/cursos',
                        inputWidth: 360,
                        extraId: 'fmExtraNivel',
                        fields: <?= json_encode($filtrosNivel, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                        loadingTarget: '#tbodyNivel',
                        onApply: () => window.fetchSearchNivel && window.fetchSearchNivel(1),
                    }).init();
                });
            </script>
            <?php // FiltrosModal (extraId) mueve estos botones dentro del input-group del buscador. ?>
            <div id="fmExtraNivel" class="btn-group btn-group-sm">
                <?php
                $columnasTabla = ['orden' => 'Orden', 'nombre' => 'Nombre', 'alumnos' => 'Alumnos', 'estado' => 'Estado'];
                echo \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo);
                ?>
                <a id="btnExportPdfNivel" href="<?= $urlBaseNivel ?>/export-pdf?b=<?= urlencode($buscar) ?>&orden=<?= urlencode($ordenParam) ?>" class="btn btn-outline-danger" title="Descargar PDF">
                    <i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span>
                </a>
                <a id="btnExportExcelNivel" href="<?= $urlBaseNivel ?>/export-excel?b=<?= urlencode($buscar) ?>&orden=<?= urlencode($ordenParam) ?>" class="btn btn-outline-success" title="Descargar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i><span class="d-none d-md-inline"> Excel</span>
                </a>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfoNivel" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="paginationContainerNivel" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaNivelAjax(<?= $page - 1 ?>)" <?= $page <= 1 ? 'disabled' : '' ?>><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaNivelAjax(<?= $page + 1 ?>)" <?= $page >= $totalPages ? 'disabled' : '' ?>><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="alumnos-niveles-scroll w-100">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 text-end sortable-header" data-sort="orden" data-col="orden" role="button">Orden <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="nombre" data-col="nombre" role="button">Nombre <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header" data-sort="alumnos" data-col="alumnos" role="button" title="Alumnos con matrícula vigente">Alumnos <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center pe-3 sortable-header" data-sort="estado" data-col="estado" role="button">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyNivel">
                    <?php require __DIR__ . '/_filas.php'; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>window.BASE_URL = '<?= $base ?>';</script>
<?php include 'modal_nivel.php'; ?>

<script>
    (function () {
        'use strict';
        const urlBase = '<?= $urlBaseNivel ?>';
        let currentPage = <?= (int) $page ?>;
        // Orden múltiple (Shift+clic), en el formato que lee OrdenListado en PHP.
        window.currentSorts = <?= $ordenJson ?: '[]' ?>;
        let sorter = null;

        async function fetchSearch(page = currentPage) {
            currentPage = page;
            const b = document.getElementById('buscarNivel').value.trim();
            const orden = window.CMG_ordenParam(window.currentSorts || []);
            const tbody = document.getElementById('tbodyNivel');
            tbody.classList.add('fm-cargando-target');
            try {
                const resp = await fetch(`${urlBase}/searchAjax?b=${encodeURIComponent(b)}&page=${page}&orden=${encodeURIComponent(orden)}`);
                const data = await resp.json();
                if (data.ok) {
                    tbody.innerHTML = data.rows;
                    document.getElementById('paginationContainerNivel').innerHTML = data.pagination;
                    document.getElementById('paginationInfoNivel').textContent = data.info;
                    document.getElementById('btnExportPdfNivel').href = data.pdf_url;
                    document.getElementById('btnExportExcelNivel').href = data.excel_url;
                    if (sorter) sorter.refreshIcons();
                }
            } catch (e) { console.error(e); }
            finally { tbody.classList.remove('fm-cargando-target'); }
        }
        // El modal de nivel refresca el listado con esta función al guardar/eliminar.
        window.fetchSearchNivel = fetchSearch;
        window.cambiarPaginaNivelAjax = (p) => fetchSearch(p);

        // multi: Shift+clic encadena hasta 3 columnas; reload:false porque fetchSearch
        // repinta todo lo que depende del orden.
        sorter = window.CMG_initSort('alumnos-niveles', (col, dir, sorts) => {
            window.currentSorts = sorts;
            fetchSearch(1);
        }, { sorts: window.currentSorts, multi: true, container: '.alumnos-niveles-scroll', reload: false });
    })();
</script>
