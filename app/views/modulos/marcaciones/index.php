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
/** @var array $vistaConfig */

use App\Helpers\PreferenciasHelper;

$base = BASE_URL;
$urlBase = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');
$vistaConfig = $vistaConfig ?? [];
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;

$tipoColor = ['entrada' => 'success', 'salida' => 'danger', 'inicio_break' => 'warning', 'fin_break' => 'info'];
$estadoColor = ['valida' => 'success', 'sospechosa' => 'warning', 'anulada' => 'secondary'];
?>

<style>
    .marcaciones-scroll { max-height: calc(100dvh - 250px); overflow-y: auto; }
    .marcaciones-scroll thead th { position: sticky; top: 0; z-index: 10; background: #f8f9fa; }
</style>

<?= PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig) ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-list-check me-2 text-primary"></i> <?= htmlspecialchars($titulo) ?></h5>
</div>

<div class="card cmg-table-card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_busqueda.css?v=<?= time() ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_busqueda.js?v=<?= time() ?>"></script>
            <div id="fbBuscadorMARC" style="width: 460px;"></div>
            <input type="hidden" id="buscarMarc" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosBusqueda) return;
                    new FiltrosBusqueda({
                        containerId: 'fbBuscadorMARC',
                        hiddenInputId: 'buscarMarc',
                        fields: [
                            { key: 'empleado', label: 'Empleado', icon: 'bi-person', type: 'text' },
                            { key: 'punto', label: 'Punto', icon: 'bi-geo-alt', type: 'text' },
                            { key: 'tipo', label: 'Tipo', icon: 'bi-box-arrow-in-right', type: 'select', options: [
                                { v: 'entrada', l: 'Entrada' }, { v: 'salida', l: 'Salida' },
                                { v: 'inicio_break', l: 'Inicio break' }, { v: 'fin_break', l: 'Fin break' }
                            ]},
                            { key: 'estado', label: 'Estado', icon: 'bi-flag', type: 'select', options: [
                                { v: 'valida', l: 'Válida' }, { v: 'sospechosa', l: 'Sospechosa' }, { v: 'anulada', l: 'Anulada' }
                            ]},
                            { key: 'fecha', label: 'Fecha', icon: 'bi-calendar-date', type: 'date_range' },
                        ],
                        quickFilters: [
                            { id: 'qf_susp', label: 'Sospechosas', mk: () => ({ key: 'estado', op: '=', value: 'sospechosa', display: 'Sospechosa' }) },
                        ],
                        onApply: () => window.cambiarPaginaAjax && window.cambiarPaginaAjax(1),
                    }).init();
                });
            </script>
            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'empleado'  => 'Empleado',
                    'punto'     => 'Punto',
                    'fecha'     => 'Fecha/Hora',
                    'tipo'      => 'Tipo',
                    'distancia' => 'Distancia',
                    'estado'    => 'Estado',
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
        <div class="marcaciones-scroll">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light shadow-sm">
                    <tr>
                        <th class="ps-3 sortable-header" data-sort="empleado" role="button" data-col="empleado">Empleado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="punto" role="button" data-col="punto">Punto <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="fecha_hora" role="button" data-col="fecha">Fecha/Hora <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" data-sort="tipo" role="button" data-col="tipo">Tipo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center" data-col="distancia">Distancia</th>
                        <th class="text-center sortable-header" data-sort="estado" role="button" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center" style="width: 40px;"></th>
                    </tr>
                </thead>
                <tbody id="tbodyMarcaciones">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">No hay marcaciones registradas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row):
                            $fecha = $row['fecha_hora'] ? date('d-m-Y H:i:s', strtotime((string) $row['fecha_hora'])) : '—';
                            $tc = $tipoColor[$row['tipo']] ?? 'secondary';
                            $ec = $estadoColor[$row['estado'] ?? 'valida'] ?? 'secondary';
                            $dist = $row['distancia_m'] !== null ? (int) $row['distancia_m'] . ' m' : '—';
                        ?>
                            <tr>
                                <td class="ps-3 fw-medium" data-col="empleado"><?= htmlspecialchars((string) ($row['empleado_nombre'] ?? '')) ?></td>
                                <td data-col="punto" class="small text-muted"><?= htmlspecialchars((string) ($row['punto_nombre'] ?? '—')) ?></td>
                                <td data-col="fecha"><?= htmlspecialchars($fecha) ?></td>
                                <td class="text-center" data-col="tipo">
                                    <span class="badge bg-<?= $tc ?> bg-opacity-10 text-<?= $tc ?> border border-<?= $tc ?> border-opacity-25"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', (string) $row['tipo']))) ?></span>
                                </td>
                                <td class="text-center" data-col="distancia"><?= htmlspecialchars($dist) ?></td>
                                <td class="text-center" data-col="estado">
                                    <span class="badge bg-<?= $ec ?> bg-opacity-10 text-<?= $ec ?> border border-<?= $ec ?> border-opacity-25"><?= htmlspecialchars(ucfirst((string) ($row['estado'] ?? 'valida'))) ?></span>
                                </td>
                                <td class="text-center pe-3">
                                    <?php if ($perm['eliminar']): ?>
                                        <button class="btn btn-outline-danger btn-xs border-0 px-2" onclick="eliminarMarcacion(<?= (int) $row['id'] ?>)" title="Eliminar"><i class="bi bi-trash"></i></button>
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

<script>window.MARCACIONES_URL = '<?= $urlBase ?>';</script>
<script>
    (function () {
        'use strict';
        const urlBase = '<?= $urlBase ?>';
        const inputB = document.getElementById('buscarMarc');
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
                    document.getElementById('tbodyMarcaciones').innerHTML = data.rows;
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

        window.eliminarMarcacion = function (id) {
            const doDelete = () => {
                const fd = new FormData(); fd.append('id_eliminar', id);
                fetch(`${urlBase}/delete`, { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(j => {
                        if (j.ok) { cargarListado(window.currentPage || 1); if (window.Swal) Swal.fire({ icon: 'success', title: j.msg, timer: 1200, showConfirmButton: false }); }
                        else if (window.Swal) Swal.fire({ icon: 'error', title: 'Error', text: j.error });
                        else alert(j.error);
                    });
            };
            if (window.Swal) {
                Swal.fire({ icon: 'warning', title: '¿Eliminar marcación?', showCancelButton: true, confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545' })
                    .then(res => { if (res.isConfirmed) doDelete(); });
            } else if (confirm('¿Eliminar esta marcación?')) doDelete();
        };

        if (window.CMG_initSort) {
            window.CMG_initSort('marcaciones', (col, dir) => {
                currentSort = col; currentDir = dir; cargarListado(1);
            }, { col: currentSort, dir: currentDir });
        }
    })();
</script>
