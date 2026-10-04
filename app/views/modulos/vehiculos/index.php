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
$urlBaseVehiculos = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');

$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>

<style>
    .vehiculos-scroll {
        max-height: calc(100dvh - 250px);
        overflow-y: auto;
    }

    .vehiculos-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 10;
        background: #f8f9fa;
    }

    .vehiculo-row {
        cursor: pointer;
    }

    .vehiculo-row:hover {
        background-color: rgba(0, 0, 0, .04);
    }
</style>

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-car-front me-2 text-primary"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="abrirModalVehiculoCrear()">
            <i class="bi bi-plus-lg me-1"></i> Nuevo
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php
            // Buscador estándar (§9): texto libre sobre las columnas + botón "Filtros" (embudo)
            // con todos los criterios + chips de los activos. Las claves (key) deben existir en
            // los mapas de VehiculoRepository::getListado().
            $opcionesUsuarioVeh = array_map(fn($u) => ['v' => (string) $u['id'], 'l' => $u['nombre']], $usuariosFiltro ?? []);
            $tV = 'Vehículo';
            $filtrosVehiculos = [
                ['tab' => $tV, 'key' => 'placa',         'label' => 'Placa',          'icon' => 'bi-car-front',      'type' => 'text',         'grupo' => 'Vehículo', 'col' => 3],
                ['tab' => $tV, 'key' => 'marca',         'label' => 'Marca',          'icon' => 'bi-badge-tm',       'type' => 'text',         'grupo' => 'Vehículo', 'col' => 3],
                ['tab' => $tV, 'key' => 'modelo',        'label' => 'Modelo',         'icon' => 'bi-car-front-fill', 'type' => 'text',         'grupo' => 'Vehículo', 'col' => 3],
                ['tab' => $tV, 'key' => 'color',         'label' => 'Color',          'icon' => 'bi-palette',        'type' => 'text',         'grupo' => 'Vehículo', 'col' => 3],
                ['tab' => $tV, 'key' => 'chasis',        'label' => 'Chasis',         'icon' => 'bi-upc',            'type' => 'text',         'grupo' => 'Vehículo', 'col' => 4],
                ['tab' => $tV, 'key' => 'anio',          'label' => 'Año',            'icon' => 'bi-calendar3',      'type' => 'number_range', 'grupo' => 'Vehículo', 'col' => 4],
                ['tab' => $tV, 'key' => 'estado',        'label' => 'Estado',         'icon' => 'bi-flag',           'type' => 'select',       'grupo' => 'Vehículo', 'col' => 4, 'options' => [
                    ['v' => 'activo',   'l' => 'Activo'],
                    ['v' => 'inactivo', 'l' => 'Inactivo'],
                ]],
                ['tab' => $tV, 'key' => 'propietario',   'label' => 'Propietario',    'icon' => 'bi-person',         'type' => 'text',         'grupo' => 'Propietario y registro', 'col' => 4],
                ['tab' => $tV, 'key' => 'correo',        'label' => 'Correo',         'icon' => 'bi-envelope',       'type' => 'text',         'grupo' => 'Propietario y registro', 'col' => 4],
                ['tab' => $tV, 'key' => 'telefono',      'label' => 'Teléfono',       'icon' => 'bi-telephone',      'type' => 'text',         'grupo' => 'Propietario y registro', 'col' => 4],
                ['tab' => $tV, 'key' => 'fecha',         'label' => 'Fecha de registro', 'icon' => 'bi-calendar-event', 'type' => 'date_range', 'grupo' => 'Propietario y registro', 'col' => 6, 'atajos' => true],
                ['tab' => $tV, 'key' => 'usuario',       'label' => 'Registrado por', 'icon' => 'bi-person-gear',    'type' => 'select',       'grupo' => 'Propietario y registro', 'col' => 6, 'options' => $opcionesUsuarioVeh],
                ['tab' => $tV, 'key' => 'con_ordenes',   'label' => 'Órdenes de Car-Wash', 'icon' => 'bi-droplet-half', 'type' => 'select',    'grupo' => 'Car-Wash', 'col' => 4, 'options' => [
                    ['v' => 'si', 'l' => 'Con órdenes'],
                    ['v' => 'no', 'l' => 'Sin órdenes'],
                ]],
                ['tab' => $tV, 'key' => 'ultima_visita', 'label' => 'Última visita',  'icon' => 'bi-calendar-check', 'type' => 'date_range',   'grupo' => 'Car-Wash', 'col' => 4],
                ['tab' => $tV, 'key' => 'proxima_cita',  'label' => 'Próxima cita',   'icon' => 'bi-calendar-plus',  'type' => 'date_range',   'grupo' => 'Car-Wash', 'col' => 4],
            ];
            $columnasTabla = [
                'marca' => 'Marca',
                'placa' => 'Placa',
                'chasis' => 'Chasis',
                'anio' => 'Año',
                'propietario' => 'Propietario',
                'correo' => 'Correo',
                'telefono' => 'Teléfono',
                'estado' => 'Estado',
                'created_at' => 'Fecha Registro'
            ];
            ?>
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
            <div id="fmBuscadorVeh"></div>
            <input type="hidden" id="buscarVehiculo" name="b" value="<?= htmlspecialchars($buscar) ?>">

            <?php // FiltrosModal (extraId) mueve estos botones dentro del grupo del buscador; si el JS no corre, quedan aquí. ?>
            <div id="fmExtraVeh" class="btn-group btn-group-sm">
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>
                <a id="btnExportPdf" href="<?= $urlBaseVehiculos ?>/export-pdf?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>" class="btn btn-outline-danger" title="PDF"><i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span></a>
                <a id="btnExportExcel" href="<?= $urlBaseVehiculos ?>/export-excel?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>" class="btn btn-outline-success" title="Excel"><i class="bi bi-file-earmark-spreadsheet"></i><span class="d-none d-md-inline"> Excel</span></a>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?> / <?= $total ?></span>
            <div id="paginationContainer" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(<?= $page - 1 ?>)" <?= $page <= 1 ? 'disabled' : '' ?>><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(<?= $page + 1 ?>)" <?= $page >= $totalPages ? 'disabled' : '' ?>><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="vehiculos-scroll">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light shadow-sm">
                    <tr>
                        <th class="ps-3 sortable-header" data-sort="marca" role="button" data-col="marca">Marca <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="placa" role="button" data-col="placa">Placa <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="chasis" role="button" data-col="chasis">Chasis <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="anio" role="button" data-col="anio">Año <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="propietario" role="button" data-col="propietario">Propietario <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="correo" role="button" data-col="correo">Correo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="telefono" role="button" data-col="telefono">Teléfono <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" data-sort="estado" role="button" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" data-sort="created_at" role="button" data-col="created_at">Fecha Registro <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyVehiculos">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">No se encontraron vehículos registrados.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr class="vehiculo-row" onclick="abrirModalVehiculoEditar(this)" data-row='<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>'>
                                <td class="ps-3 fw-bold" data-col="marca"><?= htmlspecialchars((string)($row['marca'] ?? '')) ?></td>
                                <td data-col="placa" class="fw-medium text-primary"><?= htmlspecialchars((string)($row['placa'] ?? '')) ?></td>
                                <td data-col="chasis" class="small text-muted"><?= htmlspecialchars((string)($row['chasis'] ?? '-')) ?></td>
                                <td data-col="anio"><?= !empty($row['anio']) ? (int) $row['anio'] : '' ?></td>
                                <td data-col="propietario"><?= htmlspecialchars((string)($row['propietario'] ?? '-')) ?></td>
                                <td data-col="correo" class="small text-muted"><?= htmlspecialchars((string)($row['correo'] ?? '-')) ?></td>
                                <td data-col="telefono" class="small text-muted"><?= htmlspecialchars((string)($row['telefono'] ?? '-')) ?></td>
                                <td class="text-center" data-col="estado">
                                    <span class="badge bg-<?= ($row['estado'] ?? 'activo') === 'activo' ? 'success' : 'danger' ?> bg-opacity-10 text-<?= ($row['estado'] ?? 'activo') === 'activo' ? 'success' : 'danger' ?> border border-<?= ($row['estado'] ?? 'activo') === 'activo' ? 'success' : 'danger' ?> border-opacity-10 px-2">
                                        <?= ucfirst($row['estado'] ?? 'activo') ?>
                                    </span>
                                </td>
                                <td class="text-center small text-muted" data-col="created_at"><?= htmlspecialchars((string)($row['created_at'] ?? '-')) ?></td>
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
<?php include 'modal_vehiculo.php'; ?>
<script src="<?= $base ?>/js/modulos/vehiculos_modal.js?v=<?= asset_ver('/js/modulos/vehiculos_modal.js') ?>"></script>

<script>
    (function() {
        'use strict';
        const urlBase = '<?= $urlBaseVehiculos ?>';
        const inputB = document.getElementById('buscarVehiculo');
        let currentSort = '<?= $ordenCol ?>';
        let currentDir = '<?= $ordenDir ?>';

        window.cambiarPaginaAjax = (p) => cargarListado(p);

        async function cargarListado(page = 1) {
            const b = inputB ? inputB.value.trim() : '';
            const uri = `${urlBase}/searchAjax?b=${encodeURIComponent(b)}&page=${page}&sort=${currentSort}&dir=${currentDir}`;
            try {
                const resp = await fetch(uri);
                const data = await resp.json();
                if (data.ok) {
                    window.currentPage = page;
                    document.getElementById('tbodyVehiculos').innerHTML = data.rows;
                    document.getElementById('paginationContainer').innerHTML = data.pagination;
                    document.getElementById('paginationInfo').textContent = data.info;
                    document.getElementById('btnExportPdf').href = data.pdf_url;
                    document.getElementById('btnExportExcel').href = data.excel_url;

                    document.querySelectorAll('.sortable-header').forEach(th => {
                        const icon = th.querySelector('i');
                        if (!icon) return;
                        if (th.dataset.sort === currentSort) {
                            icon.className = (currentDir.toLowerCase() === 'asc') ? 'bi bi-sort-alpha-down text-primary ms-1' : 'bi bi-sort-alpha-up text-primary ms-1';
                        } else icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
                    });
                }
            } catch (e) {}
        }
        window.fetchSearch = cargarListado;

        window.CMG_initSort('vehiculos', (col, dir) => {
            currentSort = col;
            currentDir = dir;
            cargarListado(1);
        }, { col: currentSort, dir: currentDir });

        // Buscador con filtros (mismo componente que el resto de módulos).
        document.addEventListener('DOMContentLoaded', () => {
            if (!window.FiltrosModal) return;
            window.VEH_filtros = new FiltrosModal({
                containerId: 'fmBuscadorVeh',
                hiddenInputId: 'buscarVehiculo',
                placeholder: 'Buscar en todas las columnas...',
                titulo: 'Filtros de vehículos',
                inputWidth: 380,
                extraId: 'fmExtraVeh',
                fields: <?= json_encode($filtrosVehiculos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                loadingTarget: '#tbodyVehiculos',
                onApply: () => cargarListado(1),
            });
            window.VEH_filtros.init();
        });
    })();
</script>