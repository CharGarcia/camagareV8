<?php
/** @var array $perm */
/** @var array $rows */
/** @var int $total */
/** @var array $bancos */
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
    .fp-header { flex-shrink: 0; }
    .table-container { max-height: calc(100dvh - 240px); overflow-y: auto; }
    .table-container thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
    .clickable-row { cursor: pointer; }
    .clickable-row:hover { background-color: rgba(0,0,0,0.03); }
    .dropdown-predictivo { z-index: 2000 !important; }
</style>

<div class="fp-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold text-dark">
        <i class="bi bi-credit-card-2-front text-primary me-2"></i> <?= htmlspecialchars($titulo) ?>
    </h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="abrirModalFP()">
            <i class="bi bi-plus-lg me-1"></i> Nueva
        </button>
    <?php endif; ?>
</div>

<!-- CONTENEDOR DE TABLA PRINCIPAL -->
<div class="card cmg-table-card border-0 shadow-sm rounded-3 w-100">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <form id="frmBuscarFP" class="d-flex align-items-center m-0" onsubmit="event.preventDefault(); FP_buscar(1);">
                <div class="input-group input-group-sm" style="width: 300px;">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" id="txtBuscarFP" class="form-control border-start-0 ps-0 shadow-none border" placeholder="Buscar por nombre o banco..." value="<?= htmlspecialchars($buscar ?? '') ?>" autocomplete="off">
                    <?php if (!empty($buscar)): ?>
                        <a href="<?= $urlBase ?>/index" class="btn border border-start-0 text-muted" title="Limpiar"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </div>
            </form>

            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'nombre' => 'Nombre',
                    'tipo' => 'Tipo',
                    'aplica_en' => 'Aplica en',
                    'banco_cuenta' => 'Banco / Cuenta',
                    'cuenta_contable' => 'Cta. Contable',
                    'activo' => 'Estado'
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>
                
                <a id="btnExportPdf" href="<?= $urlBase ?>/export-pdf?b=<?= urlencode($buscar??'') ?>" class="btn btn-outline-danger" title="Descargar PDF">
                    <i class="bi bi-file-earmark-pdf"></i> PDF
                </a>
                <a id="btnExportExcel" href="<?= $urlBase ?>/export-excel?b=<?= urlencode($buscar??'') ?>" class="btn btn-outline-success" title="Descargar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                </a>
            </div>
        </div>
        
        <div class="d-flex align-items-center gap-2">
            <span id="fpInfoPag" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?> / <?= $total ?></span>
            <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-secondary" <?= ($page??1)<=1?'disabled':'' ?> onclick="FP_buscar(<?=($page??1)-1?>)"><i class="bi bi-chevron-left"></i></button>
                <button class="btn btn-outline-secondary" <?= ($page??1)>=($totalPages??1)?'disabled':'' ?> onclick="FP_buscar(<?=($page??1)+1?>)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-container">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <?php
                        $renderHeader = function($key, $label, $center = false) use ($ordenCol, $ordenDir) {
                            $isCur = ($ordenCol === $key);
                            $icon = 'bi-arrow-down-up small text-muted';
                            if ($isCur) {
                                $icon = (strtoupper($ordenDir) === 'ASC') ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary';
                            }
                            $cls = $center ? 'text-center' : '';
                            echo '<th class="ps-3 py-2 sortable-header ' . $cls . '" style="cursor:pointer;" data-col="' . $key . '" onclick="FP_sort(\'' . $key . '\')">' . $label . ' <i class="bi ' . $icon . ' ms-1"></i></th>';
                        };
                        ?>
                        <?php $renderHeader('nombre', 'Nombre'); ?>
                        <?php $renderHeader('tipo', 'Tipo'); ?>
                        <?php $renderHeader('aplica_en', 'Aplica en'); ?>
                        <th data-col="banco_cuenta">Banco / Cuenta</th>
                        <th data-col="cuenta_contable">Cta. Contable</th>
                        <?php $renderHeader('activo', 'Estado', true); ?>
                    </tr>
                </thead>
                <tbody id="tbodyFP">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-credit-card-2-front fs-2 d-block mb-2"></i> No se encontraron formas de pago.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): 
                            $tipoCls = match($r['tipo']) {
                                'BANCO'    => 'bg-primary',
                                'TARJETA'  => 'bg-warning text-dark',
                                'PAYPHONE' => 'bg-danger',
                                'EFECTIVO' => 'bg-success',
                                'ANTICIPO' => 'bg-info',
                                'OTRO'     => 'bg-dark',
                                default    => 'bg-secondary'
                            };
                            $aplicaCls = match($r['aplica_en']) {
                                'INGRESO' => 'badge-outline-success border-success text-success',
                                'EGRESO'  => 'badge-outline-danger border-danger text-danger',
                                default   => 'badge-outline-info border-info text-info'
                            };
                        ?>
                            <tr class="clickable-row" onclick="abrirModalFP(<?= $r['id'] ?>)">
                                <td class="ps-3 fw-medium" data-col="nombre"><?= htmlspecialchars($r['nombre']) ?></td>
                                <td data-col="tipo"><span class="badge <?= $tipoCls ?> bg-opacity-10 <?= $r['tipo']==='TARJETA'?'text-dark':'text-'.$tipoCls ?> border"><?= $r['tipo'] ?></span></td>
                                <td data-col="aplica_en"><span class="badge bg-light text-dark border"><?= $r['aplica_en'] ?></span></td>
                                <td class="small" data-col="banco_cuenta">
                                    <?php if ($r['tipo'] === 'BANCO'): ?>
                                        <strong><?= htmlspecialchars($r['banco_nombre'] ?? '-') ?></strong><br>
                                        <span class="text-muted"><?= $r['tipo_cuenta'] ?> - <?= $r['numero_cuenta'] ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small" data-col="cuenta_contable">
                                    <?php if (!empty($r['cuenta_contable_codigo'])): ?>
                                        <code><?= $r['cuenta_contable_codigo'] ?></code> <?= htmlspecialchars($r['cuenta_contable_nombre']) ?>
                                    <?php else: ?>
                                        <span class="text-muted italic small">Sin asignar</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center" data-col="activo">
                                    <?php if (!empty($r['activo']) && $r['activo']): ?>
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

<?php include __DIR__ . '/modal_forma_pago.php'; ?>

<script>
let currentSort = '<?= $ordenCol ?>';
let currentDir = '<?= $ordenDir ?>';

function FP_buscar(p = 1){
    const b = document.getElementById('txtBuscarFP').value;
    window.location.href = `<?= $urlBase ?>/index?b=${encodeURIComponent(b)}&page=${p}&sort=${currentSort}&dir=${currentDir}`;
}

function FP_sort(col){
    if(currentSort === col){
        currentDir = (currentDir.toUpperCase() === 'ASC') ? 'DESC' : 'ASC';
    } else {
        currentSort = col;
        currentDir = 'ASC';
    }
    // Persistir el orden antes de navegar (sendBeacon sobrevive a la recarga)
    if (navigator.sendBeacon && typeof APP_VISTAS_URL !== 'undefined') {
        const fd = new FormData();
        fd.append('modulo', 'formas_cobros_pagos');
        fd.append('vistaPayload', JSON.stringify({ __ordenCol__: currentSort, __ordenDir__: currentDir }));
        navigator.sendBeacon(APP_VISTAS_URL, fd);
    }
    FP_buscar(1);
}
</script>
