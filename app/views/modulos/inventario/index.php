<?php
/** @var array $empresa */
/** @var array $rows */
/** @var int $total */
/** @var int $page */
/** @var int $totalPages */
/** @var int $perPage */
/** @var array $bodegas */
/** @var array $productos */
/** @var array $usuarios */
/** @var array $tipos_ref */
/** @var array $medidas */
/** @var float $saldo */
/** @var array $filtros */
/** @var array $perm */
/** @var string $base */
/** @var string $rutaModulo */
/** @var array $vistaConfig */

$urlBase = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');
$buscar   = $filtros['buscar'] ?? '';
$ordenCol = $filtros['sort'] ?? 'fecha_movimiento';
$ordenDir = $filtros['dir'] ?? 'desc';

$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;

$columnasTabla = [
    'fecha_movimiento' => 'Fecha',
    'producto_nombre'  => 'Producto',
    'bodega_nombre'    => 'Bodega',
    'tipo_movimiento'  => 'Tipo',
    'cantidad'         => 'Cant.',
    'nombre_medida'    => 'Medida',
    'numero_lote'      => 'Lote',
    'fecha_caducidad'  => 'Caducidad',
    'nup'              => 'NUP/Serial',
    'usuario_nombre'   => 'Usuario',
    'observaciones'    => 'Observaciones'
];

// Opciones para los selects del modal de filtros (FiltrosModal)
$optBodegas  = array_map(fn($b) => ['v' => (string)$b['id'], 'l' => $b['nombre']], $bodegas ?? []);
$optUsuarios = array_map(fn($u) => ['v' => (string)$u['id'], 'l' => $u['nombre']], $usuarios ?? []);
$optMedidas  = array_map(fn($m) => ['v' => (string)$m['id'], 'l' => $m['nombre'] . ' (' . ($m['abreviatura'] ?? '') . ')'], $medidas ?? []);
$optOrigen   = array_map(fn($t) => ['v' => $t, 'l' => ucwords(str_replace('_', ' ', $t))], $tipos_ref ?? []);
?>
<style>
    .inv-header { flex-shrink: 0; }
    .inv-scroll { max-height: calc(100dvh - 240px); overflow-y: auto; }
    .inv-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
    .inventario-row { cursor: pointer; }
    .inventario-row:hover { background-color: rgba(0, 0, 0, .04); }

    .badge-entrada { background: rgba(25,135,84,.1);  color:#198754; border:1px solid rgba(25,135,84,.2); }
    .badge-salida  { background: rgba(220,53,69,.1);  color:#dc3545; border:1px solid rgba(220,53,69,.2); }
    .badge-ajuste  { background: rgba(13,110,253,.1); color:#0d6efd; border:1px solid rgba(13,110,253,.2); }
</style>

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<!-- ── Cabecera ── -->
<div class="inv-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <!-- El <h5> debe ser hijo DIRECTO de esta fila: el app-shell aplica la sangría
         horizontal con `div:has(> h5)` (app.css). Un div intermedio la rompe. -->
    <h5 class="mb-0 fw-bold"><i class="bi bi-arrow-left-right me-2 text-primary"></i>Movimientos de Inventario</h5>
    <?php if ($perm['crear']): ?>
        <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>/modulos/cargas-inventario" class="btn btn-outline-success btn-sm px-3" title="La importación masiva ahora se gestiona (con aprobación) en el módulo Cargas de Inventario">
                <i class="bi bi-file-earmark-excel"></i> Importar (Cargas de Inventario)
            </a>
            <button type="button" class="btn btn-primary btn-sm px-3" onclick="abrirModalNuevoAjuste()">
                <i class="bi bi-plus-lg"></i> Nuevo
            </button>
        </div>
    <?php endif; ?>
</div>

<!-- ── Tarjeta Principal ── -->
<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <!-- Buscador y Exportación -->
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php
            // Buscador estándar (FiltrosModal): texto libre sobre las columnas del listado
            // (sin sugerencias) + botón embudo que abre un modal con todos los filtros +
            // chips de los activos. Las claves (key) deben existir en los mapas de
            // InventarioRepository::getKardex(). Sin pestaña Detalles: cada fila ya es una
            // línea del kardex (no tiene tablas hijas que buscar).
            $optCategorias = array_map(fn($c) => ['v' => (string) $c['id'], 'l' => (string) $c['nombre']], $categorias ?? []);
            $opcionesSiNo  = fn(string $si, string $no) => [['v' => 'si', 'l' => $si], ['v' => 'no', 'l' => $no]];
            $tM = 'Movimiento';
            // Filas de 12 columnas:
            //   Movimiento:   [Fecha 6][Tipo 3][Origen 3]
            //                 [Bodega 4][Usuario 4][Medida 4]
            //                 [Observaciones 12]
            //   Producto:     [Producto 4][Código 4][Categoría 4]
            //   Lote y serie: [Lote 3][NUP 3][Caducidad 6]
            //                 [Con lote 6][Con NUP 6]
            //   Valores:      [Cantidad 4][Costo unitario 4][Costo total 4]
            $filtrosInventario = [
                // ── Movimiento ──
                ['tab' => $tM, 'key' => 'fecha',      'label' => 'Fecha del movimiento', 'icon' => 'bi-calendar-event', 'type' => 'date_range', 'grupo' => 'Movimiento', 'col' => 6, 'atajos' => true],
                ['tab' => $tM, 'key' => 'tipo',       'label' => 'Tipo',                 'icon' => 'bi-arrow-down-up',  'type' => 'select',     'grupo' => 'Movimiento', 'col' => 3, 'options' => [
                    ['v' => 'entrada', 'l' => 'Entrada (+)'],
                    ['v' => 'salida',  'l' => 'Salida (-)'],
                ]],
                ['tab' => $tM, 'key' => 'origen',     'label' => 'Origen',               'icon' => 'bi-link-45deg',     'type' => 'select',     'grupo' => 'Movimiento', 'col' => 3, 'options' => $optOrigen],
                ['tab' => $tM, 'key' => 'id_bodega',  'label' => 'Bodega',               'icon' => 'bi-house-door',     'type' => 'select',     'grupo' => 'Movimiento', 'col' => 4, 'options' => $optBodegas],
                ['tab' => $tM, 'key' => 'id_usuario', 'label' => 'Usuario',              'icon' => 'bi-person',         'type' => 'select',     'grupo' => 'Movimiento', 'col' => 4, 'options' => $optUsuarios],
                ['tab' => $tM, 'key' => 'id_medida',  'label' => 'Unidad de medida',     'icon' => 'bi-rulers',         'type' => 'select',     'grupo' => 'Movimiento', 'col' => 4, 'options' => $optMedidas],
                ['tab' => $tM, 'key' => 'obs',        'label' => 'Observaciones',        'icon' => 'bi-chat-left-text', 'type' => 'text',       'grupo' => 'Movimiento', 'col' => 12],
                // ── Producto ──
                ['tab' => $tM, 'key' => 'producto',     'label' => 'Producto',  'icon' => 'bi-box-seam', 'type' => 'text',   'grupo' => 'Producto', 'col' => 4],
                ['tab' => $tM, 'key' => 'codigo',       'label' => 'Código',    'icon' => 'bi-upc',      'type' => 'text',   'grupo' => 'Producto', 'col' => 4],
                ['tab' => $tM, 'key' => 'id_categoria', 'label' => 'Categoría', 'icon' => 'bi-folder',   'type' => 'select', 'grupo' => 'Producto', 'col' => 4, 'options' => $optCategorias],
                // ── Lote y serie ──
                ['tab' => $tM, 'key' => 'lote',      'label' => 'Lote',            'icon' => 'bi-tag',             'type' => 'text',       'grupo' => 'Lote y serie', 'col' => 3],
                ['tab' => $tM, 'key' => 'nup',       'label' => 'NUP / Serial',    'icon' => 'bi-upc-scan',        'type' => 'text',       'grupo' => 'Lote y serie', 'col' => 3],
                ['tab' => $tM, 'key' => 'caducidad', 'label' => 'Fecha de caducidad', 'icon' => 'bi-calendar-x',   'type' => 'date_range', 'grupo' => 'Lote y serie', 'col' => 6],
                ['tab' => $tM, 'key' => 'con_lote',  'label' => 'Lote registrado', 'icon' => 'bi-tags',            'type' => 'select',     'grupo' => 'Lote y serie', 'col' => 6, 'options' => $opcionesSiNo('Con lote', 'Sin lote')],
                ['tab' => $tM, 'key' => 'con_nup',   'label' => 'NUP / Serial registrado', 'icon' => 'bi-upc',     'type' => 'select',     'grupo' => 'Lote y serie', 'col' => 6, 'options' => $opcionesSiNo('Con NUP / serial', 'Sin NUP / serial')],
                // ── Valores ──
                ['tab' => $tM, 'key' => 'cantidad',       'label' => 'Cantidad',       'icon' => 'bi-123',             'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                ['tab' => $tM, 'key' => 'costo_unitario', 'label' => 'Costo unitario', 'icon' => 'bi-currency-dollar', 'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                ['tab' => $tM, 'key' => 'costo_total',    'label' => 'Costo total',    'icon' => 'bi-cash-stack',      'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
            ];
            ?>
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
            <div id="fmBuscadorINV"></div>
            <input type="hidden" id="buscarInventario" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosModal) return;
                    new FiltrosModal({
                        containerId: 'fmBuscadorINV',
                        hiddenInputId: 'buscarInventario',
                        placeholder: 'Buscar en todas las columnas...',
                        titulo: 'Filtros de movimientos de inventario',
                        inputWidth: 420,
                        extraId: 'fmExtraINV',   // columnas + PDF + Excel, pegados al final del grupo
                        fields: <?= json_encode($filtrosInventario, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                        loadingTarget: '#tbodyInventario',   // se atenúa mientras se busca
                        onApply: () => window.fetchSearch && window.fetchSearch(1),
                    }).init();
                });
            </script>

            <?php // FiltrosModal (extraId) mueve estos botones dentro del input-group del buscador; si el JS no corre, quedan aquí. ?>
            <div id="fmExtraINV" class="btn-group btn-group-sm">
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>
                <?php
                // La búsqueda viaja en `b` (lo que leen exportPdf/exportExcel), no en `buscar`.
                $qFiltros = $filtros;
                unset($qFiltros['buscar']);
                $qStr = http_build_query(['b' => $buscar] + $qFiltros);
                ?>
                <a id="btnExportPdf" href="<?= $urlBase ?>/exportPdf?<?= htmlspecialchars($qStr) ?>"
                    class="btn btn-outline-danger" title="Descargar PDF">
                    <i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span>
                </a>
                <a id="btnExportExcel" href="<?= $urlBase ?>/exportExcel?<?= htmlspecialchars($qStr) ?>"
                    class="btn btn-outline-success" title="Descargar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i><span class="d-none d-md-inline"> Excel</span>
                </a>
            </div>

            <div class="form-check form-switch mb-0 ms-1" title="Mostrar solo los movimientos anulados">
                <input class="form-check-input" type="checkbox" role="switch" id="chkVerAnulados" onchange="window.fetchSearch(1)">
                <label class="form-check-label small text-muted" for="chkVerAnulados">Ver anulados</label>
            </div>
        </div>

        <!-- Paginación -->
        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="paginationContainer" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" <?= $page <= 1 ? 'disabled' : '' ?> onclick="cambiarPaginaAjax(<?= $page - 1 ?>)">
                    <i class="bi bi-chevron-left"></i>
                </button>
                <button type="button" class="btn btn-outline-secondary" <?= $page >= $totalPages ? 'disabled' : '' ?> onclick="cambiarPaginaAjax(<?= $page + 1 ?>)">
                    <i class="bi bi-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card-body p-0">
        <div class="inv-scroll w-100">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="fecha_movimiento" data-col="fecha_movimiento">Fecha <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="producto_nombre" data-col="producto_nombre">Producto <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="bodega_nombre" data-col="bodega_nombre">Bodega <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" role="button" data-sort="tipo_movimiento" data-col="tipo_movimiento">Tipo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header" role="button" data-sort="cantidad" data-col="cantidad">Cant. <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="nombre_medida" data-col="nombre_medida">Medida <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="numero_lote" data-col="numero_lote">Lote <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="fecha_caducidad" data-col="fecha_caducidad">Caducidad <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="nup" data-col="nup">NUP/Serial <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="usuario_nombre" data-col="usuario_nombre">Usuario <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="observaciones" data-col="observaciones">Obs. <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyInventario">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="11" class="text-center py-4 text-muted">
                                <i class="bi bi-info-circle me-1"></i> No se encontraron movimientos con los filtros actuales
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr class="inventario-row" onclick="editarMovimiento(<?= $row['id'] ?>)">
                                <td class="ps-3 small text-nowrap" data-col="fecha_movimiento"><?= date('d-m-Y H:i:s', strtotime($row['fecha_movimiento'])) ?></td>
                                <td data-col="producto_nombre">
                                    <div class="fw-bold text-dark mb-0"><?= htmlspecialchars($row['producto_nombre']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($row['producto_codigo']) ?></small>
                                </td>
                                <td class="small" data-col="bodega_nombre"><?= htmlspecialchars($row['bodega_nombre']) ?></td>
                                <td class="text-center" data-col="tipo_movimiento">
                                    <?php
                                        $badgeClass = ($row['tipo_movimiento'] === 'entrada') ? 'badge-entrada' : 'badge-salida';
                                        $label = ($row['tipo_movimiento'] === 'entrada') ? 'ENTRADA' : 'SALIDA';
                                    ?>
                                    <span class="badge <?= $badgeClass ?> rounded-pill px-2" style="font-size:0.7rem;"><?= $label ?></span>
                                </td>
                                <td class="text-end fw-bold" data-col="cantidad">
                                    <span class="<?= $row['tipo_movimiento'] === 'entrada' ? 'text-success' : 'text-danger' ?>">
                                        <?= $row['tipo_movimiento'] === 'entrada' ? '+' : '-' ?><?= number_format(abs((float)$row['cantidad']), 2) ?>
                                    </span>
                                </td>
                                <td class="small" data-col="nombre_medida">
                                    <?= htmlspecialchars($row['nombre_medida'] ?? '-') ?>
                                    <?php if (!empty($row['abreviatura_medida'])): ?>
                                        <small class="text-muted">(<?= htmlspecialchars($row['abreviatura_medida']) ?>)</small>
                                    <?php endif; ?>
                                </td>
                                <td class="small" data-col="numero_lote"><?= htmlspecialchars($row['numero_lote'] ?? '-') ?></td>
                                <td class="small" data-col="fecha_caducidad"><?= $row['fecha_caducidad'] ? date('d-m-Y', strtotime($row['fecha_caducidad'])) : '-' ?></td>
                                <td class="small" data-col="nup"><?= htmlspecialchars($row['nup'] ?? '-') ?></td>
                                <td class="small" data-col="usuario_nombre"><?= htmlspecialchars($row['usuario_nombre'] ?? '-') ?></td>
                                <td class="small text-truncate" style="max-width: 150px;" data-col="observaciones" title="<?= htmlspecialchars($row['observaciones'] ?? '') ?>">
                                    <?= htmlspecialchars($row['observaciones'] ?? '-') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── Modales ── -->
<?php include __DIR__ . '/modal.php'; ?>

<!-- ── Scripts ── -->
<script>
    (function() {
        'use strict';
        const urlBase = '<?= $urlBase ?>';
        const inputBuscar = document.getElementById('buscarInventario');
        window.currentSort = '<?= $ordenCol ?>';
        window.currentDir  = '<?= $ordenDir ?>';
        window.currentPage = <?= $page ?>;

        window.cambiarPaginaAjax = (n) => window.fetchSearch(n);

        window.fetchSearch = async (page = 1) => {
            const term = inputBuscar ? inputBuscar.value.trim() : '';
            const verAnulados = document.getElementById('chkVerAnulados')?.checked ? '1' : '';
            const uri = `${urlBase}/searchAjax?b=${encodeURIComponent(term)}&page=${page}&sort=${window.currentSort}&dir=${window.currentDir}&ver_anulados=${verAnulados}`;
            // Mismo indicador que el buscador (FiltrosModal): la tabla se atenúa mientras
            // carga, también al paginar, ordenar o cambiar "Ver anulados".
            const tbody = document.getElementById('tbodyInventario');
            if (tbody) tbody.classList.add('fm-cargando-target');
            try {
                const resp = await fetch(uri);
                const data = await resp.json();
                if (!data.ok) return;

                window.currentPage = page;
                document.getElementById('tbodyInventario').innerHTML = data.rows;
                document.getElementById('paginationContainer').innerHTML = data.pagination;
                document.getElementById('paginationInfo').textContent = data.info;

                if (data.pdf_url)   document.getElementById('btnExportPdf').href = data.pdf_url;
                if (data.excel_url) document.getElementById('btnExportExcel').href = data.excel_url;

                document.querySelectorAll('.sortable-header').forEach(th => {
                    const icon = th.querySelector('i');
                    if (!icon) return;
                    if (th.dataset.sort === window.currentSort) {
                        icon.className = (window.currentDir.toLowerCase() === 'asc')
                            ? 'bi bi-sort-alpha-down text-primary ms-1'
                            : 'bi bi-sort-alpha-up text-primary ms-1';
                    } else {
                        icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
                    }
                });
            } catch (e) {
                console.error('Error cargando listado de inventario:', e);
            } finally {
                if (tbody) tbody.classList.remove('fm-cargando-target');
            }
        };

        window.editarMovimiento = function(id) {
            if (typeof window.abrirModalAjuste === 'function') window.abrirModalAjuste(id);
        };

        window.eliminarMovimiento = async function(id) {
            const result = await Swal.fire({
                title: '¿Anular movimiento?',
                text: 'El stock será revertido automáticamente. Queda registrado como anulado y se puede habilitar de nuevo si fue un error.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, anular',
                cancelButtonText: 'Cancelar'
            });
            if (!result.isConfirmed) return false;
            try {
                const resp = await fetch(`${urlBase}/eliminarAjax`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${id}`
                });
                const json = await resp.json();
                if (json.ok) {
                    if (typeof window.INV_toast === 'function') window.INV_toast('success', 'Movimiento anulado');
                    else Swal.fire({ icon: 'success', title: 'Movimiento anulado', timer: 1400, showConfirmButton: false });
                    window.fetchSearch(window.currentPage);
                    return true;
                }
                Swal.fire({ icon: 'error', title: 'Error', text: json.mensaje || 'No se pudo anular.' });
                return false;
            } catch (e) {
                console.error('Error al anular:', e);
                Swal.fire({ icon: 'error', title: 'Error', text: 'Error de conexión con el servidor.' });
                return false;
            }
        };

        window.habilitarMovimiento = async function(id) {
            const result = await Swal.fire({
                title: '¿Habilitar movimiento?',
                text: 'El stock volverá a aplicarse tal como estaba antes de anularlo.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, habilitar',
                cancelButtonText: 'Cancelar'
            });
            if (!result.isConfirmed) return false;
            try {
                const resp = await fetch(`${urlBase}/restaurarAjax`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${id}`
                });
                const json = await resp.json();
                if (json.ok) {
                    if (typeof window.INV_toast === 'function') window.INV_toast('success', 'Movimiento habilitado');
                    else Swal.fire({ icon: 'success', title: 'Movimiento habilitado', timer: 1400, showConfirmButton: false });
                    window.fetchSearch(window.currentPage);
                    return true;
                }
                Swal.fire({ icon: 'error', title: 'Error', text: json.mensaje || 'No se pudo habilitar.' });
                return false;
            } catch (e) {
                console.error('Error al habilitar:', e);
                Swal.fire({ icon: 'error', title: 'Error', text: 'Error de conexión con el servidor.' });
                return false;
            }
        };

        // Ordenamiento centralizado (persiste preferencia por usuario)
        window.CMG_initSort('inventario', (col, dir) => {
            window.currentSort = col;
            window.currentDir  = dir;
            window.fetchSearch(1);
        }, { col: window.currentSort, dir: window.currentDir });

        // Auto-open si viene de Productos
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('autoOpen') === '1') {
            setTimeout(() => {
                if (typeof abrirModalNuevoAjuste === 'function') abrirModalNuevoAjuste();
            }, 600);
        }
    })();
</script>
