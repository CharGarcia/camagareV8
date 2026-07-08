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
/** @var array $meses */

$base = BASE_URL;
$urlBaseVac = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
$colores = ['registrado' => 'info', 'pagado' => 'success', 'anulado' => 'danger'];
?>

<style>
    .vac-scroll { max-height: calc(100dvh - 250px); overflow-y: auto; }
    .vac-scroll thead th { position: sticky; top: 0; z-index: 10; background: #f8f9fa; }
    .vac-row { cursor: pointer; }
    .vac-row:hover { background-color: rgba(0, 0, 0, .04); }
</style>

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-umbrella me-2 text-primary"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="abrirModalCrear()"><i class="bi bi-plus-lg me-1"></i> Nueva</button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_busqueda.css?v=<?= time() ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_busqueda.js?v=<?= time() ?>"></script>
            <div id="fbBuscadorVAC" style="width: 440px;"></div>
            <input type="hidden" id="buscarVac" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosBusqueda) return;
                    new FiltrosBusqueda({
                        containerId: 'fbBuscadorVAC',
                        hiddenInputId: 'buscarVac',
                        fields: [
                            { key: 'empleado', label: 'Empleado', icon: 'bi-person', type: 'text' },
                            { key: 'estado', label: 'Estado', icon: 'bi-flag', type: 'select', options: [
                                { v: 'registrado', l: 'Registrado' }, { v: 'pagado', l: 'Pagado' }, { v: 'anulado', l: 'Anulado' }
                            ]},
                            { key: 'mes', label: 'Mes del rol', icon: 'bi-calendar-month', type: 'select', options: [
                                <?php foreach ($meses as $n => $nom): ?>{ v: '<?= $n ?>', l: '<?= htmlspecialchars($nom) ?>' },<?php endforeach; ?>
                            ]},
                            { key: 'anio', label: 'Año', icon: 'bi-calendar', type: 'text' },
                            { key: 'dias', label: 'Días', icon: 'bi-123', type: 'number_range' },
                            { key: 'valor', label: 'Valor', icon: 'bi-currency-dollar', type: 'number_range' },
                            { key: 'desde', label: 'Desde', icon: 'bi-calendar-date', type: 'date_range' },
                            { key: 'hasta', label: 'Hasta', icon: 'bi-calendar-date', type: 'date_range' },
                        ],
                        quickFilters: [
                            { id: 'qf_reg', label: 'Registrados', mk: () => ({ key: 'estado', op: '=', value: 'registrado', display: 'Registrado' }) },
                            { id: 'qf_pag', label: 'Pagados',     mk: () => ({ key: 'estado', op: '=', value: 'pagado', display: 'Pagado' }) },
                        ],
                        onApply: () => window.cambiarPaginaAjax && window.cambiarPaginaAjax(1),
                    }).init();
                });
            </script>
            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'empleado' => 'Empleado', 'identificacion' => 'Identificación', 'desde' => 'Desde',
                    'hasta' => 'Hasta', 'dias' => 'Días', 'valor' => 'Valor', 'estado' => 'Estado',
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>
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
        <div class="vac-scroll">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light shadow-sm">
                    <tr>
                        <th class="ps-3 sortable-header" data-sort="empleado" role="button" data-col="empleado">Empleado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th data-col="identificacion">Identificación</th>
                        <th class="sortable-header" data-sort="fecha_desde" role="button" data-col="desde">Desde <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th data-col="hasta">Hasta</th>
                        <th class="text-center" data-col="dias">Días</th>
                        <th class="text-end sortable-header" data-sort="valor" role="button" data-col="valor">Valor <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" data-sort="estado" role="button" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center" style="width: 40px;"></th>
                    </tr>
                </thead>
                <tbody id="tbodyVacaciones">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">No hay vacaciones registradas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row):
                            $c = $colores[$row['estado']] ?? 'secondary';
                        ?>
                            <tr class="vac-row" onclick="abrirModalEditar(this)" data-row='<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>'>
                                <td class="ps-3 fw-medium" data-col="empleado"><?= htmlspecialchars((string) $row['empleado_nombre']) ?></td>
                                <td data-col="identificacion"><code class="text-secondary"><?= htmlspecialchars((string) $row['empleado_identificacion']) ?></code></td>
                                <td data-col="desde"><?= $row['fecha_desde'] ? date('d-m-Y', strtotime((string) $row['fecha_desde'])) : '—' ?></td>
                                <td data-col="hasta"><?= $row['fecha_hasta'] ? date('d-m-Y', strtotime((string) $row['fecha_hasta'])) : '—' ?></td>
                                <td class="text-center" data-col="dias"><?= (float) $row['dias_gozados'] ?></td>
                                <td class="text-end fw-bold" data-col="valor">$<?= number_format((float) $row['valor'], 2) ?></td>
                                <td class="text-center" data-col="estado">
                                    <span class="badge bg-<?= $c ?> bg-opacity-10 text-<?= $c ?> border border-<?= $c ?> border-opacity-25"><?= htmlspecialchars(ucfirst((string) $row['estado'])) ?></span>
                                </td>
                                <td class="text-center pe-3" onclick="event.stopPropagation()">
                                    <button class="btn btn-outline-danger btn-xs border-0 px-2" onclick="eliminarRegistro(<?= $row['id'] ?>)" title="Eliminar"><i class="bi bi-trash"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>window.BASE_URL = '<?= $base ?>';</script>
<?php include 'modal_vacacion.php'; ?>
<script src="<?= $base ?>/js/modulos/vacaciones.js?v=<?= time() ?>"></script>

<script>
    (function () {
        'use strict';
        const urlBase = '<?= $urlBaseVac ?>';
        const inputB = document.getElementById('buscarVac');
        let currentSort = '<?= $ordenCol ?>', currentDir = '<?= $ordenDir ?>';

        window.cambiarPaginaAjax = (p) => cargarListado(p);

        async function cargarListado(page = 1) {
            const b = inputB ? inputB.value.trim() : '';
            try {
                const resp = await fetch(`${urlBase}/searchAjax?b=${encodeURIComponent(b)}&page=${page}&sort=${currentSort}&dir=${currentDir}`);
                const data = await resp.json();
                if (data.ok) {
                    window.currentPage = page;
                    document.getElementById('tbodyVacaciones').innerHTML = data.rows;
                    document.getElementById('wrapper-pagination').innerHTML = data.pagination;
                    document.getElementById('paginationInfo').textContent = data.info;
                }
            } catch (e) {}
        }

        if (window.CMG_initSort) {
            window.CMG_initSort('vacaciones', (col, dir) => { currentSort = col; currentDir = dir; cargarListado(1); }, { col: currentSort, dir: currentDir });
        }
        window.addEventListener('vacacionGuardada', () => cargarListado(window.currentPage || 1));
    })();
</script>
