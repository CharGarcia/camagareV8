<?php

/** @var string $titulo */
/** @var array $perm */
/** @var string $rutaModulo */
/** @var string $rowsHtml */
/** @var string $pieHtml */
/** @var int $total */
/** @var int $page */
/** @var int $totalPages */
/** @var int $perPage */
/** @var string $buscar */

$base = BASE_URL;
$urlBaseBodegas = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');

$total      = $total ?? 0;
$page       = $page ?? 1;
$totalPages = $totalPages ?? 1;
$perPage    = $perPage ?? 25;
$buscar     = $buscar ?? '';
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>
<style>
    .bodegas-header {
        flex-shrink: 0;
    }

    .bodegas-scroll {
        max-height: calc(100dvh - 240px);
        overflow-y: auto;
    }

    .bodegas-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }

    .bodegas-scroll tfoot td {
        position: sticky;
        bottom: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 -1px 0 #dee2e6;
    }

    .bodega-row {
        cursor: pointer;
    }

    .bodega-row:hover {
        background-color: rgba(0, 0, 0, .04);
    }


</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<div class="bodegas-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-box-seam"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="abrirModalBodegaCrear()"><i class="bi bi-plus-lg"></i> Nueva</button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <!-- Buscador y Exportación -->
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php
            // Buscador estándar (FiltrosModal), igual que Proveedores: texto libre sobre
            // nombre y establecimiento + embudo con todos los filtros + chips de los
            // activos. Las claves deben existir en los mapas de BodegaRepository::getListado().
            $opcFiltro = $opcionesFiltro ?? [];
            $tB = 'Bodega';
            $opcionesEstablecimiento = array_map(
                fn($e) => ['v' => (string) $e['id'], 'l' => trim(($e['codigo'] ?? '') . ' - ' . ($e['nombre'] ?? ''), ' -')],
                $establecimientos ?? []
            );
            $filtrosBodegas = [
                // ── Bodega ──
                ['tab' => $tB, 'key' => 'nombre',             'label' => 'Nombre',          'icon' => 'bi-box-seam', 'type' => 'text',   'grupo' => 'Bodega', 'col' => 4],
                ['tab' => $tB, 'key' => 'id_establecimiento', 'label' => 'Establecimiento', 'icon' => 'bi-shop',     'type' => 'select', 'grupo' => 'Bodega', 'col' => 4, 'options' => $opcionesEstablecimiento],
                ['tab' => $tB, 'key' => 'estado',             'label' => 'Estado',          'icon' => 'bi-flag',     'type' => 'select', 'grupo' => 'Bodega', 'col' => 4, 'options' => [
                    ['v' => 'activo',   'l' => 'Activo'],
                    ['v' => 'inactivo', 'l' => 'Inactivo'],
                ]],
                // ── Saldo ──
                ['tab' => $tB, 'key' => 'con_stock',   'label' => 'Existencias',           'icon' => 'bi-boxes',          'type' => 'select',       'grupo' => 'Saldo', 'col' => 6, 'options' => [
                    ['v' => 'si', 'l' => 'Con saldo'],
                    ['v' => 'no', 'l' => 'Sin saldo'],
                ]],
                ['tab' => $tB, 'key' => 'unidades',    'label' => 'Unidades',              'icon' => 'bi-123',            'type' => 'number_range', 'grupo' => 'Saldo', 'col' => 6],
                ['tab' => $tB, 'key' => 'valor_costo', 'label' => 'Saldo al costo',        'icon' => 'bi-cash-stack',     'type' => 'number_range', 'grupo' => 'Saldo', 'col' => 6],
                ['tab' => $tB, 'key' => 'valor_venta', 'label' => 'Saldo a precio de venta', 'icon' => 'bi-tag',          'type' => 'number_range', 'grupo' => 'Saldo', 'col' => 6],
                // ── Registro ──
                ['tab' => $tB, 'key' => 'registro', 'label' => 'Fecha de registro',    'icon' => 'bi-calendar-event', 'type' => 'date_range', 'grupo' => 'Registro', 'col' => 6, 'atajos' => true],
                ['tab' => $tB, 'key' => 'usuario',  'label' => 'Usuario que registró', 'icon' => 'bi-person-gear',    'type' => 'select',     'grupo' => 'Registro', 'col' => 6,
                    'options' => array_map(fn($u) => ['v' => (string) $u['id'], 'l' => (string) $u['nombre']], $opcFiltro['usuarios'] ?? [])],
            ];
            ?>
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
            <div id="fmBuscadorBOD"></div>
            <input type="hidden" id="buscarBodega" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosModal) return;
                    new FiltrosModal({
                        containerId: 'fmBuscadorBOD',
                        hiddenInputId: 'buscarBodega',
                        placeholder: 'Buscar en todas las columnas...',
                        titulo: 'Filtros de bodegas',
                        inputWidth: 380,
                        extraId: 'fmExtraBOD', // columnas + PDF + Excel, pegados al final del grupo
                        fields: <?= json_encode($filtrosBodegas, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                        loadingTarget: '#tbodyBodegas',
                        onApply: () => window.fetchSearch && window.fetchSearch(1),
                    }).init();
                });
            </script>

            <?php // FiltrosModal (extraId) mueve estos botones dentro del input-group del buscador; si el JS no corre, quedan aquí. ?>
            <div id="fmExtraBOD" class="btn-group btn-group-sm">
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla ?? [], $vistaConfig ?? [], $rutaModulo) ?>

                <a id="btnExportPdf" href="<?= $urlBaseBodegas ?>/export-pdf?b=<?= urlencode($buscar) ?>&orden=<?= urlencode($ordenParam ?? '') ?>"
                    class="btn btn-outline-danger" title="Descargar PDF">
                    <i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span>
                </a>
                <a id="btnExportExcel" href="<?= $urlBaseBodegas ?>/export-excel?b=<?= urlencode($buscar) ?>&orden=<?= urlencode($ordenParam ?? '') ?>"
                    class="btn btn-outline-success" title="Descargar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i><span class="d-none d-md-inline"> Excel</span>
                </a>
            </div>
        </div>

        <!-- Paginación -->
        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="paginationContainer" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" <?= $page <= 1 ? 'disabled' : '' ?> onclick="cambiarPaginaAjax(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" <?= $page >= $totalPages ? 'disabled' : '' ?> onclick="cambiarPaginaAjax(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card-body p-0">
        <div class="bodegas-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="nombre" data-col="nombre">Nombre <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="establecimiento" data-col="establecimiento">Establecimiento <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-end" role="button" data-sort="productos" data-col="productos" title="Productos con saldo en la bodega">Productos <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-end" role="button" data-sort="unidades" data-col="unidades" title="Suma de las cantidades en existencia">Unidades <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-end" role="button" data-sort="valor_costo" data-col="valor_costo" title="Existencias × último costo del kardex">Saldo al costo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-end" role="button" data-sort="valor_venta" data-col="valor_venta" title="Existencias × precio de venta (sin IVA)">Saldo a precio de venta <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center pe-3 sortable-header" role="button" data-sort="status" data-col="status" style="width: 110px">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyBodegas">
                    <?= $rowsHtml ?? '' ?>
                </tbody>
                <tfoot id="tfootBodegas">
                    <?= $pieHtml ?? '' ?>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php
// Modal de Bodega: pieza reutilizable (también la usa Configuración de Producción).
$permBodegas          = $perm;
$bodegaModalFavoritos = true;
include_once __DIR__ . '/modal.php';
?>

<script>
    (function() {
        'use strict';

        const urlBase = '<?= $urlBaseBodegas ?>';
        const inputBuscar = document.getElementById('buscarBodega');
        let sorter = null;
        window.currentSorts = <?= $ordenJson ?? '[]' ?>;
        window.currentPage = <?= (int) $page ?>;

        function avisoError(texto, titulo = 'No se pudo completar') {
            if (typeof Swal === 'undefined') { alert(texto); return; }
            Swal.fire({ icon: 'error', title: titulo, text: texto, confirmButtonText: 'Aceptar' });
        }

        // ─── Listado ─────────────────────────────────────────────────────────
        window.cambiarPaginaAjax = (n) => window.fetchSearch(n);

        window.fetchSearch = async (page = 1) => {
            const term = inputBuscar ? inputBuscar.value.trim() : '';
            const orden = window.CMG_ordenParam ? window.CMG_ordenParam(window.currentSorts || []) : '';
            const uri = `${urlBase}/searchAjax?b=${encodeURIComponent(term)}&page=${page}&orden=${encodeURIComponent(orden)}`;
            const tbody = document.getElementById('tbodyBodegas');
            if (tbody) tbody.classList.add('fm-cargando-target');
            try {
                const resp = await fetch(uri);
                const data = await resp.json();
                if (data.ok) {
                    window.currentPage = page;
                    tbody.innerHTML = data.rows;
                    document.getElementById('tfootBodegas').innerHTML = data.pie || '';
                    document.getElementById('paginationContainer').innerHTML = data.pagination;
                    document.getElementById('paginationInfo').textContent = data.info;
                    document.getElementById('btnExportPdf').href = data.pdf_url;
                    document.getElementById('btnExportExcel').href = data.excel_url;
                    if (sorter) sorter.refreshIcons();
                } else {
                    avisoError(data.error || 'No se pudo cargar el listado de bodegas.');
                }
            } catch (e) {
                console.error('Error cargando bodegas:', e);
                avisoError('Error de conexión al cargar el listado de bodegas.');
            } finally {
                if (tbody) tbody.classList.remove('fm-cargando-target');
            }
        };

        // Orden de una o varias columnas (Shift+clic), igual que Proveedores.
        if (window.CMG_initSort) {
            sorter = window.CMG_initSort('bodegas', (col, dir, sorts) => {
                window.currentSorts = sorts;
                window.fetchSearch(1);
            }, { sorts: window.currentSorts, multi: true, container: '.bodegas-scroll', reload: false });
        }

        // El modal (modal.php + bodegas_modal.js) avisa al guardar o eliminar: se recarga la página actual.
        document.addEventListener('bodegaGuardado', () => window.fetchSearch(window.currentPage || 1));
        document.addEventListener('bodegaEliminada', () => window.fetchSearch(window.currentPage || 1));
    })();
</script>
