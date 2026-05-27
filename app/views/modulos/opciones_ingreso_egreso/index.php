<?php

/** @var array $perm */
/** @var array $rows */
/** @var int $total */
/** @var array $vistaConfig */
/** @var string $rutaModulo */

$base = BASE_URL;
$urlBase = $base . '/' . $rutaModulo;
$rows = $rows ?? [];
$from = $from ?? 0;
$to = $to ?? 0;
$total = $total ?? 0;

echo \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []);
?>

<style>
    .mod-header {
        flex-shrink: 0;
    }

    .table-container {
        max-height: calc(100vh - 240px);
        overflow-y: auto;
    }

    .table-container thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }

    .clickable-row {
        cursor: pointer;
    }

    .clickable-row:hover {
        background-color: rgba(0, 0, 0, 0.03);
    }

    .dropdown-predictivo {
        z-index: 2050 !important;
    }
</style>

<div class="mod-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold text-dark">
        <i class="bi bi-tags text-primary me-2"></i> <?= htmlspecialchars($titulo) ?>
    </h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="abrirModalOpcion()">
            <i class="bi bi-plus-lg me-1"></i> Nueva
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card border-0 shadow-sm rounded-3 w-100">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <form id="frmBuscar" class="d-flex align-items-center m-0" onsubmit="event.preventDefault(); OIE_buscar(1);">
                <div class="input-group input-group-sm" style="width: 300px;">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" id="txtBuscar" class="form-control border-start-0 ps-0 shadow-none border" placeholder="Buscar por nombre..." value="<?= htmlspecialchars($buscar ?? '') ?>" autocomplete="off">
                    <?php if (!empty($buscar)): ?>
                        <a href="<?= $urlBase ?>/index" class="btn border border-start-0 text-muted" title="Limpiar"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </div>
            </form>

            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'nombre' => 'Nombre',
                    'aplica_ingresos' => 'Aplica Ingresos',
                    'aplica_egresos' => 'Aplica Egresos',
                    'cuenta_contable' => 'Cuenta Contable',
                    'estado' => 'Estado'
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>

                <a href="#" class="btn btn-outline-danger disabled" title="Próximamente PDF"><i class="bi bi-file-earmark-pdf"></i></a>
                <a href="#" class="btn btn-outline-success disabled" title="Próximamente Excel"><i class="bi bi-file-earmark-spreadsheet"></i></a>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            <span id="oieInfoPag" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?> / <?= $total ?></span>
            <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-secondary" <?= ($page ?? 1) <= 1 ? 'disabled' : '' ?> onclick="OIE_buscar(<?= ($page ?? 1) - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <button class="btn btn-outline-secondary" <?= ($page ?? 1) >= ($totalPages ?? 1) ? 'disabled' : '' ?> onclick="OIE_buscar(<?= ($page ?? 1) + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-container">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <?php
                        $renderHeader = function ($key, $label, $center = false) use ($ordenCol, $ordenDir) {
                            $isCur = ($ordenCol === $key);
                            $icon = 'bi-arrow-down-up small text-muted';
                            if ($isCur) {
                                $icon = (strtoupper($ordenDir) === 'ASC') ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary';
                            }
                            $cls = $center ? 'text-center' : '';
                            echo '<th class="ps-3 py-2 sortable-header ' . $cls . '" style="cursor:pointer;" data-col="' . $key . '" onclick="OIE_sort(\'' . $key . '\')">' . $label . ' <i class="bi ' . $icon . ' ms-1"></i></th>';
                        };
                        ?>
                        <?php $renderHeader('nombre', 'Nombre'); ?>
                        <?php $renderHeader('aplica_ingresos', 'Aplica Ingresos', true); ?>
                        <?php $renderHeader('aplica_egresos', 'Aplica Egresos', true); ?>
                        <th data-col="cuenta_contable">Cuenta Contable</th>
                        <?php $renderHeader('estado', 'Estado', true); ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-tags fs-2 d-block mb-2"></i> No se encontraron opciones registradas.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <tr class="clickable-row" onclick="abrirModalOpcion(<?= $r['id'] ?>)">
                                <td class="ps-3 fw-bold" data-col="nombre"><?= htmlspecialchars($r['nombre']) ?></td>
                                <td class="text-center" data-col="aplica_ingresos">
                                    <?php if (!empty($r['aplica_ingresos'])): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success"><i class="bi bi-check-circle me-1"></i> SÍ</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border border-secondary border-opacity-25">NO</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center" data-col="aplica_egresos">
                                    <?php if (!empty($r['aplica_egresos'])): ?>
                                        <span class="badge bg-info bg-opacity-10 text-info border border-info"><i class="bi bi-check-circle me-1"></i> SÍ</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border border-secondary border-opacity-25">NO</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small" data-col="cuenta_contable">
                                    <?php if (!empty($r['cuenta_codigo'])): ?>
                                        <code class="fw-bold"><?= $r['cuenta_codigo'] ?></code> - <span class="text-secondary"><?= htmlspecialchars($r['cuenta_nombre']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted italic">No asignada</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center" data-col="estado">
                                    <?php if (strtoupper($r['estado'] ?? '') === 'ACTIVO'): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary">Inactivo</span>
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

<?php include __DIR__ . '/modal_opcion.php'; ?>

<script>
    let currentSort = '<?= $ordenCol ?>';
    let currentDir = '<?= $ordenDir ?>';

    function OIE_buscar(p = 1) {
        const b = document.getElementById('txtBuscar').value;
        window.location.href = `<?= $urlBase ?>/index?b=${encodeURIComponent(b)}&page=${p}&sort=${currentSort}&dir=${currentDir}`;
    }

    function OIE_sort(col) {
        if (currentSort === col) {
            currentDir = (currentDir.toUpperCase() === 'ASC') ? 'DESC' : 'ASC';
        } else {
            currentSort = col;
            currentDir = 'ASC';
        }
        OIE_buscar(1);
    }
</script>