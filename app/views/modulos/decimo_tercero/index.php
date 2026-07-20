<?php

/** @var array $perm */
/** @var string $rutaModulo */
/** @var array $rows */
/** @var int $total */
/** @var int $page */
/** @var int $totalPages */
/** @var int $perPage */
/** @var string $buscar */

$base = BASE_URL;
$urlBase = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
$colores = ['borrador' => 'secondary', 'calculado' => 'info'];
$anioActual = (int) date('Y');
?>

<style>
    .dt-scroll { max-height: calc(100dvh - 250px); overflow-y: auto; }
    .dt-scroll thead th { position: sticky; top: 0; z-index: 10; background: #f8f9fa; }
    .dt-row { cursor: pointer; }
    .dt-row:hover { background-color: rgba(0, 0, 0, .04); }
</style>

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-cash-coin me-2 text-primary"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="abrirModalCalcular()"><i class="bi bi-calculator me-1"></i> Calcular</button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <input type="text" id="buscarDt" class="form-control form-control-sm" style="width: 260px;"
                   placeholder="Buscar por año o estado..." value="<?= htmlspecialchars($buscar) ?>"
                   onkeyup="if(event.key==='Enter') cambiarPaginaAjax(1)">
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
        <div class="dt-scroll">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light shadow-sm">
                    <tr>
                        <th class="ps-3" data-col="anio">Año</th>
                        <th data-col="base">Base de cálculo</th>
                        <th data-col="limite">Fecha límite</th>
                        <th class="text-center" data-col="empleados">Empleados</th>
                        <th class="text-end" data-col="total">Total</th>
                        <th class="text-center" data-col="estado">Estado</th>
                        <th class="text-center" style="width: 40px;"></th>
                    </tr>
                </thead>
                <tbody id="tbodyDecimoTercero">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">No hay declaraciones calculadas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row):
                            $c = $colores[$row['estado']] ?? 'secondary';
                            $baseTxt = $row['base_calculo'] === 'todos' ? 'Todos los ingresos' : 'Solo IESS';
                        ?>
                            <tr class="dt-row" onclick="abrirModalVer(this)" data-row='<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>'>
                                <td class="ps-3 fw-medium" data-col="anio"><?= (int) $row['anio'] ?></td>
                                <td data-col="base"><?= htmlspecialchars($baseTxt) ?></td>
                                <td data-col="limite"><?= $row['fecha_limite_pago'] ? date('d-m-Y', strtotime((string) $row['fecha_limite_pago'])) : '—' ?></td>
                                <td class="text-center" data-col="empleados"><?= (int) $row['total_empleados'] ?></td>
                                <td class="text-end fw-bold" data-col="total">$<?= number_format((float) $row['total_valor'], 2) ?></td>
                                <td class="text-center" data-col="estado">
                                    <span class="badge bg-<?= $c ?> bg-opacity-10 text-<?= $c ?> border border-<?= $c ?> border-opacity-25"><?= htmlspecialchars(ucfirst((string) $row['estado'])) ?></span>
                                </td>
                                <td class="text-center pe-3" onclick="event.stopPropagation()">
                                    <button class="btn btn-outline-secondary btn-xs border-0 px-2" onclick="exportarCsv(<?= (int) $row['id'] ?>)" title="Exportar CSV"><i class="bi bi-file-earmark-spreadsheet"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>window.BASE_URL = '<?= $base ?>'; window.DT_ANIO_ACTUAL = <?= $anioActual ?>;</script>
<?php include 'modal_calcular.php'; ?>
<?php include 'modal_detalle.php'; ?>
<script src="<?= $base ?>/js/modulos/decimo_tercero.js?v=<?= time() ?>"></script>

<script>
    (function () {
        'use strict';
        const urlBase = '<?= $urlBase ?>';
        const inputB = document.getElementById('buscarDt');
        let currentSort = '<?= $ordenCol ?>', currentDir = '<?= $ordenDir ?>';

        window.cambiarPaginaAjax = (p) => cargarListado(p);

        async function cargarListado(page = 1) {
            const b = inputB ? inputB.value.trim() : '';
            try {
                const resp = await fetch(`${urlBase}/searchAjax?b=${encodeURIComponent(b)}&page=${page}&sort=${currentSort}&dir=${currentDir}`);
                const data = await resp.json();
                if (data.ok) {
                    window.currentPage = page;
                    document.getElementById('tbodyDecimoTercero').innerHTML = data.rows;
                    document.getElementById('wrapper-pagination').innerHTML = data.pagination;
                    document.getElementById('paginationInfo').textContent = data.info;
                }
            } catch (e) {}
        }

        if (window.CMG_initSort) {
            window.CMG_initSort('decimo_tercero', (col, dir) => { currentSort = col; currentDir = dir; cargarListado(1); }, { col: currentSort, dir: currentDir });
        }
        window.addEventListener('decimoTerceroActualizado', () => cargarListado(window.currentPage || 1));
    })();
</script>
