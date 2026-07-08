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
/** @var array $tipos */
/** @var array $estados */
/** @var array $meses */

use App\models\CatalogoRol;
use App\models\CatalogoNovedades;

$base = BASE_URL;
$urlBaseRol = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
$colores = ['borrador' => 'secondary', 'generado' => 'info', 'pagado' => 'success', 'contabilizado' => 'primary', 'anulado' => 'danger'];
?>

<style>
    .rolp-scroll { max-height: calc(100dvh - 250px); overflow-y: auto; }
    .rolp-scroll thead th { position: sticky; top: 0; z-index: 10; background: #f8f9fa; }
    .rol-row { cursor: pointer; }
    .rol-row:hover { background-color: rgba(0, 0, 0, .04); }
</style>

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-cash-stack me-2 text-primary"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="abrirModalCrear()">
            <i class="bi bi-plus-lg me-1"></i> Nuevo
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_busqueda.css?v=<?= time() ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_busqueda.js?v=<?= time() ?>"></script>
            <div id="fbBuscadorROL" style="width: 420px;"></div>
            <input type="hidden" id="buscarRol" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosBusqueda) return;
                    new FiltrosBusqueda({
                        containerId: 'fbBuscadorROL',
                        hiddenInputId: 'buscarRol',
                        fields: [
                            { key: 'tipo', label: 'Tipo de rol', icon: 'bi-cash-stack', type: 'select', options: [
                                { v: 'MENSUAL', l: 'Rol Mensual' }, { v: 'QUINCENA', l: 'Quincena' }, { v: 'SEMANAL', l: 'Semanal' }
                            ]},
                            { key: 'estado', label: 'Estado', icon: 'bi-flag', type: 'select', options: [
                                { v: 'borrador', l: 'Borrador' }, { v: 'generado', l: 'Generado' }, { v: 'pagado', l: 'Pagado' },
                                { v: 'contabilizado', l: 'Contabilizado' }, { v: 'anulado', l: 'Anulado' }
                            ]},
                            { key: 'mes', label: 'Mes', icon: 'bi-calendar-month', type: 'select', options: [
                                <?php foreach ($meses as $n => $nom): ?>{ v: '<?= $n ?>', l: '<?= htmlspecialchars($nom) ?>' },<?php endforeach; ?>
                            ]},
                            { key: 'anio', label: 'Año', icon: 'bi-calendar', type: 'text' },
                            { key: 'neto', label: 'Neto', icon: 'bi-currency-dollar', type: 'number_range' },
                        ],
                        quickFilters: [
                            { id: 'qf_mensual',  label: 'Mensuales', mk: () => ({ key: 'tipo',   op: '=', value: 'MENSUAL',  display: 'Mensual' }) },
                            { id: 'qf_generado', label: 'Generados', mk: () => ({ key: 'estado', op: '=', value: 'generado', display: 'Generado' }) },
                        ],
                        onApply: () => window.cambiarPaginaAjax && window.cambiarPaginaAjax(1),
                    }).init();
                });
            </script>
            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'tipo' => 'Tipo', 'periodo' => 'Período', 'empleados' => 'Empleados', 'neto' => 'Neto', 'estado' => 'Estado',
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
        <div class="rolp-scroll">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light shadow-sm">
                    <tr>
                        <th class="ps-3 sortable-header" data-sort="tipo_rol" role="button" data-col="tipo">Tipo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th data-col="periodo">Período</th>
                        <th class="text-center" data-col="empleados">Empleados</th>
                        <th class="text-end sortable-header" data-sort="total_neto" role="button" data-col="neto">Neto <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" data-sort="estado" role="button" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center" style="width: 40px;"></th>
                    </tr>
                </thead>
                <tbody id="tbodyRoles">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">No hay corridas de rol registradas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row):
                            $mes = $meses[(int) $row['periodo_mes']] ?? $row['periodo_mes'];
                            $num = (int) $row['numero_periodo'] > 0 ? ' #' . (int) $row['numero_periodo'] : '';
                            $c = $colores[$row['estado']] ?? 'secondary';
                        ?>
                            <tr class="rol-row" onclick="abrirModalVer(this)" data-row='<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>'>
                                <td class="ps-3 fw-medium" data-col="tipo"><?= htmlspecialchars(CatalogoRol::nombreTipo((string) $row['tipo_rol'])) ?></td>
                                <td data-col="periodo"><?= htmlspecialchars($mes . ' ' . $row['periodo_anio'] . $num) ?></td>
                                <td class="text-center" data-col="empleados"><?= (int) ($row['num_empleados'] ?? 0) ?></td>
                                <td class="text-end fw-bold" data-col="neto">$<?= number_format((float) $row['total_neto'], 2) ?></td>
                                <td class="text-center" data-col="estado">
                                    <span class="badge bg-<?= $c ?> bg-opacity-10 text-<?= $c ?> border border-<?= $c ?> border-opacity-25"><?= htmlspecialchars(CatalogoRol::nombreEstado((string) $row['estado'])) ?></span>
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
<?php include 'modal_rol.php'; ?>
<?php include 'modal_rol_ver.php'; ?>
<?php include 'modal_rol_emp.php'; ?>
<script src="<?= $base ?>/js/modulos/roles_pago.js?v=<?= time() ?>"></script>

<script>
    (function () {
        'use strict';
        const urlBase = '<?= $urlBaseRol ?>';
        const inputB = document.getElementById('buscarRol');
        let currentSort = '<?= $ordenCol ?>', currentDir = '<?= $ordenDir ?>';

        window.cambiarPaginaAjax = (p) => cargarListado(p);

        async function cargarListado(page = 1) {
            const b = inputB ? inputB.value.trim() : '';
            try {
                const resp = await fetch(`${urlBase}/searchAjax?b=${encodeURIComponent(b)}&page=${page}&sort=${currentSort}&dir=${currentDir}`);
                const data = await resp.json();
                if (data.ok) {
                    window.currentPage = page;
                    document.getElementById('tbodyRoles').innerHTML = data.rows;
                    document.getElementById('wrapper-pagination').innerHTML = data.pagination;
                    document.getElementById('paginationInfo').textContent = data.info;
                }
            } catch (e) {}
        }

        if (window.CMG_initSort) {
            window.CMG_initSort('roles_pago', (col, dir) => { currentSort = col; currentDir = dir; cargarListado(1); }, { col: currentSort, dir: currentDir });
        }
        window.addEventListener('rolGuardado', () => cargarListado(window.currentPage || 1));
    })();
</script>
