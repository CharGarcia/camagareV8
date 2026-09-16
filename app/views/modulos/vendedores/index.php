<?php

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
$urlBaseVendedores = $base . '/modulos/vendedores';

$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>

<style>
    .vendedores-scroll {
        max-height: calc(100dvh - 250px);
        overflow-y: auto;
    }

    .vendedores-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 10;
        background: #f8f9fa;
    }

    .vendedor-row {
        cursor: pointer;
    }

    .vendedor-row:hover {
        background-color: rgba(0, 0, 0, .04);
    }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-person-badge me-2 text-primary"></i>Vendedores / Asesores de ventas</h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="abrirModalVendedorCrear()">
            <i class="bi bi-plus-lg me-1"></i> Nuevo
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php
            // Buscador estándar (FiltrosModal): texto libre sobre las columnas del listado
            // (sin sugerencias) + botón embudo que abre un modal con todos los filtros +
            // chips de los activos. Las claves (key) deben existir en los mapas de
            // VendedorRepository::getListado(). Sin pestaña Detalles: el vendedor no tiene
            // tablas hijas que buscar.
            $opcFiltro = $opcionesFiltro ?? [];
            $opcIdNombre = fn(string $k) => array_map(fn($x) => ['v' => (string) $x['id'], 'l' => (string) $x['nombre']], $opcFiltro[$k] ?? []);
            $opcionesSiNo = fn(string $si, string $no) => [['v' => 'si', 'l' => $si], ['v' => 'no', 'l' => $no]];
            $tV = 'Vendedor';
            // Filas de 12 columnas:
            //   Vendedor:        [Nombre 3][Identificación 3][Estado 3][Clientes asignados 3]
            //   Contacto:        [Correo 3][Con correo 3][Teléfono 3][Dirección 3]
            //   Usuario sistema: [Usuario del sistema 6][Vinculado 6]  (solo si existe la columna id_usuario_vinculado)
            //   Registro:        [Fecha de registro 6][Usuario que registró 6]
            $filtrosVendedores = [
                // ── Vendedor ──
                ['tab' => $tV, 'key' => 'nombre',         'label' => 'Nombre',         'icon' => 'bi-person-badge', 'type' => 'text',   'grupo' => 'Vendedor', 'col' => 3],
                ['tab' => $tV, 'key' => 'identificacion', 'label' => 'Identificación', 'icon' => 'bi-card-text',    'type' => 'text',   'grupo' => 'Vendedor', 'col' => 3],
                ['tab' => $tV, 'key' => 'estado',         'label' => 'Estado',         'icon' => 'bi-flag',         'type' => 'select', 'grupo' => 'Vendedor', 'col' => 3, 'options' => [
                    ['v' => 'activo',   'l' => 'Activo'],
                    ['v' => 'inactivo', 'l' => 'Inactivo'],
                ]],
                ['tab' => $tV, 'key' => 'con_clientes',   'label' => 'Clientes asignados', 'icon' => 'bi-people',   'type' => 'select', 'grupo' => 'Vendedor', 'col' => 3, 'options' => $opcionesSiNo('Con clientes', 'Sin clientes')],
                // ── Contacto ──
                ['tab' => $tV, 'key' => 'email',     'label' => 'Correo',            'icon' => 'bi-envelope',       'type' => 'text',   'grupo' => 'Contacto', 'col' => 3],
                ['tab' => $tV, 'key' => 'con_email', 'label' => 'Correo registrado', 'icon' => 'bi-envelope-check', 'type' => 'select', 'grupo' => 'Contacto', 'col' => 3, 'options' => $opcionesSiNo('Con correo', 'Sin correo')],
                ['tab' => $tV, 'key' => 'telefono',  'label' => 'Teléfono',          'icon' => 'bi-telephone',      'type' => 'text',   'grupo' => 'Contacto', 'col' => 3],
                ['tab' => $tV, 'key' => 'direccion', 'label' => 'Dirección',         'icon' => 'bi-geo',            'type' => 'text',   'grupo' => 'Contacto', 'col' => 3],
            ];
            if (!empty($opcFiltro['con_vinculo'])) {
                // ── Usuario del sistema (la columna id_usuario_vinculado existe) ──
                $filtrosVendedores[] = ['tab' => $tV, 'key' => 'usuario_vinculado', 'label' => 'Usuario del sistema',    'icon' => 'bi-person-check', 'type' => 'select', 'grupo' => 'Usuario del sistema', 'col' => 6, 'options' => $opcIdNombre('vinculados')];
                $filtrosVendedores[] = ['tab' => $tV, 'key' => 'con_usuario',       'label' => 'Vinculado a un usuario', 'icon' => 'bi-link-45deg',   'type' => 'select', 'grupo' => 'Usuario del sistema', 'col' => 6, 'options' => $opcionesSiNo('Con usuario del sistema', 'Sin usuario del sistema')];
            }
            // ── Registro ──
            $filtrosVendedores[] = ['tab' => $tV, 'key' => 'registro', 'label' => 'Fecha de registro',    'icon' => 'bi-calendar-event', 'type' => 'date_range', 'grupo' => 'Registro', 'col' => 6, 'atajos' => true];
            $filtrosVendedores[] = ['tab' => $tV, 'key' => 'usuario',  'label' => 'Usuario que registró', 'icon' => 'bi-person-gear',    'type' => 'select',     'grupo' => 'Registro', 'col' => 6, 'options' => $opcIdNombre('usuarios')];
            ?>
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
            <div id="fmBuscadorVEN"></div>
            <input type="hidden" id="buscarVendedor" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosModal) return;
                    new FiltrosModal({
                        containerId: 'fmBuscadorVEN',
                        hiddenInputId: 'buscarVendedor',
                        placeholder: 'Buscar en todas las columnas...',
                        titulo: 'Filtros de vendedores',
                        inputWidth: 420,
                        extraId: 'fmExtraVEN',   // columnas + PDF + Excel, pegados al final del grupo
                        fields: <?= json_encode($filtrosVendedores, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                        loadingTarget: '#tbodyVendedores',   // se atenúa mientras se busca
                        // La función de búsqueda de este listado es cargarListado (antes se
                        // llamaba a un window.fetchSearch que no existe y los filtros no
                        // refrescaban la tabla).
                        onApply: () => window.cargarListado && window.cargarListado(1),
                    }).init();
                });
            </script>
            <?php // FiltrosModal (extraId) mueve estos botones dentro del input-group del buscador; si el JS no corre, quedan aquí. ?>
            <div id="fmExtraVEN" class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'nombre'          => 'Nombre',
                    'identificacion'  => 'Identificación',
                    'correo'          => 'Correo',
                    'telefono'        => 'Teléfono',
                    'status'          => 'Estado',
                ];
                echo \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo);
                ?>
                <a id="btnExportPdf" href="<?= $urlBaseVendedores ?>/export-pdf?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>" class="btn btn-outline-danger" title="Descargar PDF"><i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span></a>
                <a id="btnExportExcel" href="<?= $urlBaseVendedores ?>/export-excel?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>" class="btn btn-outline-success" title="Descargar Excel"><i class="bi bi-file-earmark-spreadsheet"></i><span class="d-none d-md-inline"> Excel</span></a>
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
        <div class="vendedores-scroll">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light shadow-sm">
                    <tr>
                        <th class="ps-3 sortable-header" data-sort="nombre" data-col="nombre" role="button">Nombre Vendedor <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="identificacion" data-col="identificacion" role="button">Identificación <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="correo" data-col="correo" role="button">Correo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="telefono" data-col="telefono" role="button">Teléfono <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" data-sort="status" data-col="status" role="button">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyVendedores">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">No se encontraron vendedores registrados.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr class="vendedor-row" onclick="abrirModalVendedorEditar(this)" data-row='<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>'>
                                <td class="ps-3 fw-bold" data-col="nombre"><?= htmlspecialchars((string)($row['nombre'] ?? '')) ?></td>
                                <td data-col="identificacion"><?= htmlspecialchars((string)($row['identificacion'] ?? '-')) ?></td>
                                <td class="small text-muted" data-col="correo"><?= htmlspecialchars((string)($row['correo'] ?? '-')) ?></td>
                                <td data-col="telefono"><?= htmlspecialchars((string)($row['telefono'] ?? '-')) ?></td>
                                <td class="text-center" data-col="status">
                                    <span class="badge bg-<?= ($row['status'] ?? 1) == 1 ? 'success' : 'danger' ?> bg-opacity-10 text-<?= ($row['status'] ?? 1) == 1 ? 'success' : 'danger' ?> border border-<?= ($row['status'] ?? 1) == 1 ? 'success' : 'danger' ?> border-opacity-10">
                                        <?= ($row['status'] ?? 1) == 1 ? 'Activo' : 'Inactivo' ?>
                                    </span>
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
<?php include 'modal_vendedor.php'; ?>

<script>
    (function() {
        'use strict';
        const urlBase = '<?= $urlBaseVendedores ?>';
        const inputB = document.getElementById('buscarVendedor');
        let currentSort = '<?= $ordenCol ?>';
        let currentDir = '<?= $ordenDir ?>';
        let timer;

        window.cambiarPaginaAjax = (p) => cargarListado(p);

        async function cargarListado(page = 1) {
            const b = inputB ? inputB.value.trim() : '';
            const uri = `${urlBase}/searchAjax?b=${encodeURIComponent(b)}&page=${page}&sort=${currentSort}&dir=${currentDir}`;
            // Mismo indicador que el buscador (FiltrosModal): la tabla se atenúa mientras
            // carga, también al paginar u ordenar (que llaman a esta función directo).
            const tbody = document.getElementById('tbodyVendedores');
            if (tbody) tbody.classList.add('fm-cargando-target');
            try {
                const resp = await fetch(uri);
                const data = await resp.json();
                if (data.ok) {
                    document.getElementById('tbodyVendedores').innerHTML = data.rows;
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
            } catch (e) {
                console.error(e);
            } finally {
                if (tbody) tbody.classList.remove('fm-cargando-target');
            }
        }
        window.cargarListado = cargarListado;

        window.CMG_initSort('vendedores', (col, dir) => {
            currentSort = col;
            currentDir = dir;
            cargarListado(1);
        }, { col: currentSort, dir: currentDir });

        if (inputB) inputB.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => cargarListado(1), 400);
        });
    })();
</script>