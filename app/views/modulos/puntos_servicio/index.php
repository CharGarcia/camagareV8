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

use App\Helpers\PreferenciasHelper;

$base = BASE_URL;
$urlBase = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');
$vistaConfig = PreferenciasHelper::getPreferenciasVista($rutaModulo);
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>

<style>
    .casis-scroll { max-height: calc(100dvh - 250px); overflow-y: auto; }
    .casis-scroll thead th { position: sticky; top: 0; z-index: 10; background: #f8f9fa; }
    .punto-row { cursor: pointer; }
    .punto-row:hover { background-color: rgba(0, 0, 0, .04); }
</style>

<?= PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig) ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-geo-alt-fill me-2 text-primary"></i> <?= htmlspecialchars($titulo) ?></h5>
    <div class="d-flex align-items-center gap-2">
        <?php if ($perm['crear']): ?>
            <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="abrirModalCrearPunto()">
                <i class="bi bi-plus-lg me-1"></i> Nuevo
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="card cmg-table-card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_busqueda.css?v=<?= time() ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_busqueda.js?v=<?= time() ?>"></script>
            <div id="fbBuscadorCASIS" style="width: 420px;"></div>
            <input type="hidden" id="buscarCasis" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosBusqueda) return;
                    new FiltrosBusqueda({
                        containerId: 'fbBuscadorCASIS',
                        hiddenInputId: 'buscarCasis',
                        fields: [
                            { key: 'nombre', label: 'Nombre', icon: 'bi-geo-alt', type: 'text' },
                            { key: 'direccion', label: 'Dirección', icon: 'bi-signpost', type: 'text' },
                            { key: 'estado', label: 'Estado', icon: 'bi-flag', type: 'select', options: [
                                { v: 'activo', l: 'Activo' }, { v: 'inactivo', l: 'Inactivo' }
                            ]},
                        ],
                        quickFilters: [
                            { id: 'qf_activo', label: 'Activos', mk: () => ({ key: 'estado', op: '=', value: 'activo', display: 'Activo' }) },
                        ],
                        onApply: () => window.cambiarPaginaAjax && window.cambiarPaginaAjax(1),
                    }).init();
                });
            </script>
            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'nombre'    => 'Nombre',
                    'direccion' => 'Dirección',
                    'radio'     => 'Radio',
                    'gps'       => 'GPS',
                    'estado'    => 'Estado',
                    'qr'        => 'QR',
                ];
                ?>
                <?= PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig, $rutaModulo) ?>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?> / <?= $total ?></span>
            <div id="wrapper-pagination" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(<?= $page - 1 ?>)" <?= $page <= 1 ? 'disabled' : '' ?>><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(<?= $page + 1 ?>)" <?= $page >= $totalPages ? 'disabled' : '' ?>><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="casis-scroll">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light shadow-sm">
                    <tr>
                        <th class="ps-3 sortable-header" data-sort="nombre" role="button" data-col="nombre">Nombre <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th data-col="direccion">Dirección</th>
                        <th class="text-center sortable-header" data-sort="radio_m" role="button" data-col="radio">Radio <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center" data-col="gps">GPS</th>
                        <th class="text-center sortable-header" data-sort="estado" role="button" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center" data-col="qr">QR</th>
                        <th class="text-center" style="width: 40px;"></th>
                    </tr>
                </thead>
                <tbody id="tbodyPuntos">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">No hay puntos de servicio registrados.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row):
                            $activo = ($row['estado'] ?? 'activo') === 'activo';
                        ?>
                            <tr class="punto-row" onclick="abrirModalEditarPunto(this)" data-row='<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>'>
                                <td class="ps-3 fw-medium" data-col="nombre"><?= htmlspecialchars((string) $row['nombre']) ?></td>
                                <td data-col="direccion" class="small text-muted"><?= htmlspecialchars((string) ($row['direccion'] ?? '—')) ?></td>
                                <td class="text-center" data-col="radio"><?= (int) $row['radio_m'] ?> m</td>
                                <td class="text-center" data-col="gps">
                                    <?php if (!empty($row['exige_gps'])): ?>
                                        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25"><i class="bi bi-geo-alt me-1"></i>Sí</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">No</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center" data-col="estado">
                                    <span class="badge bg-<?= $activo ? 'success' : 'secondary' ?> bg-opacity-10 text-<?= $activo ? 'success' : 'secondary' ?> border border-<?= $activo ? 'success' : 'secondary' ?> border-opacity-25"><?= $activo ? 'Activo' : 'Inactivo' ?></span>
                                </td>
                                <td class="text-center" data-col="qr" onclick="event.stopPropagation()">
                                    <button class="btn btn-outline-primary btn-xs px-2" onclick="verQrPunto(<?= (int) $row['id'] ?>)" title="Ver / imprimir QR"><i class="bi bi-qr-code"></i> QR</button>
                                </td>
                                <td class="text-center pe-3" onclick="event.stopPropagation()">
                                    <?php if ($perm['eliminar']): ?>
                                        <button class="btn btn-outline-danger btn-xs border-0 px-2" onclick="eliminarPunto(<?= (int) $row['id'] ?>)" title="Eliminar"><i class="bi bi-trash"></i></button>
                                    <?php endif; ?>
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
    window.CASIS_URL_BASE = '<?= $urlBase ?>';
    window.CASIS_PERM = <?= json_encode($perm) ?>;
</script>

<?php include 'modal_punto.php'; ?>
<?php include 'modal_qr.php'; ?>

<script src="<?= $base ?>/js/modulos/puntos_servicio.js?v=<?= time() ?>"></script>

<script>
    (function () {
        'use strict';
        const urlBase = '<?= $urlBase ?>';
        const inputB = document.getElementById('buscarCasis');
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
                    document.getElementById('tbodyPuntos').innerHTML = data.rows;
                    document.getElementById('wrapper-pagination').innerHTML = data.pagination;
                    document.getElementById('paginationInfo').textContent = data.info;
                    document.querySelectorAll('.sortable-header').forEach(th => {
                        const icon = th.querySelector('i');
                        if (!icon) return;
                        if (th.dataset.sort === currentSort) {
                            icon.className = (currentDir.toLowerCase() === 'asc') ? 'bi bi-sort-down-alt text-primary ms-1' : 'bi bi-sort-up text-primary ms-1';
                        } else icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
                    });
                }
            } catch (e) {}
        }

        if (window.CMG_initSort) {
            window.CMG_initSort('puntos_servicio', (col, dir) => {
                currentSort = col;
                currentDir = dir;
                cargarListado(1);
            }, { col: currentSort, dir: currentDir });
        }

        window.addEventListener('puntoGuardado', () => cargarListado(window.currentPage || 1));
    })();
</script>
