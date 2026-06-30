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

$base = BASE_URL;
$urlBaseModulo = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');

$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>

<style>
    .asiento-scroll {
        max-height: calc(100dvh - 250px);
        overflow-y: auto;
    }

    .asiento-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 10;
        background: #f8f9fa;
    }

    .asiento-row {
        cursor: pointer;
    }

    .asiento-row:hover {
        background-color: rgba(0, 0, 0, .04);
    }
</style>

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<?php if (!empty($warnings)): ?>
    <div class="alert alert-warning alert-dismissible fade show shadow-sm mb-3" role="alert">
        <strong><i class="bi bi-exclamation-triangle-fill me-2"></i> Atención:</strong>
        <ul class="mb-0 mt-2">
            <?php foreach ($warnings as $w): ?>
                <li><?= htmlspecialchars($w) ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-journal-text me-2 text-primary"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="ASIENTO_abrirModal()">
            <i class="bi bi-plus-lg me-1"></i> Nuevo
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_busqueda.css?v=<?= time() ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_busqueda.js?v=<?= time() ?>"></script>
            <div id="fbBuscadorASIENTOS" style="width: 480px;"></div>
            <input type="hidden" id="buscarAsiento" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosBusqueda) return;
                    new FiltrosBusqueda({
                        containerId: 'fbBuscadorASIENTOS',
                        hiddenInputId: 'buscarAsiento',
                        placeholder: 'Buscar asiento...',
                        fields: [
                            { key: 'concepto', label: 'Concepto',        icon: 'bi-chat-left-text', type: 'text' },
                            { key: 'numero',   label: 'N° Comprobante',  icon: 'bi-hash',           type: 'text' },
                            { key: 'tipo',     label: 'Tipo',            icon: 'bi-journal',        type: 'select',
                              options: [
                                { v: 'adquisiciones',       l: 'Adquisiciones' },
                                { v: 'apertura',            l: 'Apertura' },
                                { v: 'cierre',              l: 'Cierre' },
                                { v: 'diario',              l: 'Diario' },
                                { v: 'egresos',             l: 'Egresos' },
                                { v: 'ingresos',            l: 'Ingresos' },
                                { v: 'nomina',              l: 'Nómina' },
                                { v: 'retenciones_compras', l: 'Retenciones Compras' },
                                { v: 'retenciones_ventas',  l: 'Retenciones Ventas' },
                                { v: 'ventas',              l: 'Ventas' },
                              ]
                            },
                            { key: 'estado', label: 'Estado', icon: 'bi-flag', type: 'select',
                              options: [
                                { v: 'contabilizado', l: 'Contabilizado' },
                                { v: 'anulado',       l: 'Anulado' },
                              ]
                            },
                            { key: 'fecha',  label: 'Fecha',  icon: 'bi-calendar-range', type: 'date_range' },
                            { key: 'origen', label: 'Origen', icon: 'bi-box-arrow-in-right', type: 'text' },
                            { key: 'total',  label: 'Total',  icon: 'bi-currency-dollar', type: 'number_range' },
                        ],
                        quickFilters: [
                            { id: 'qf_contabilizado', label: 'Contabilizados', mk: () => ({ key: 'estado', op: '=', value: 'contabilizado', display: 'Contabilizado' }) },
                            { id: 'qf_anulado',       label: 'Anulados',       mk: () => ({ key: 'estado', op: '=', value: 'anulado',       display: 'Anulado' }) },
                            { id: 'qf_este_mes',      label: 'Este mes',       mk: () => FiltrosBusqueda.helpers.esteMes('fecha') },
                        ],
                        onApply: () => window.fetchSearch && window.fetchSearch(1),
                    }).init();
                });
            </script>
            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'numero_comprobante' => 'Comprobante',
                    'fecha_asiento' => 'Fecha',
                    'tipo_comprobante' => 'Tipo',
                    'concepto' => 'Concepto',
                    'modulo_origen' => 'Origen',
                    'total_debe' => 'Total',
                    'estado' => 'Estado'
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
        <div class="asiento-scroll">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light shadow-sm">
                    <tr>
                        <th class="ps-3 sortable-header" data-sort="numero_comprobante" role="button" data-col="numero_comprobante">Comprobante <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="fecha_asiento" role="button" data-col="fecha_asiento">Fecha <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="tipo_comprobante" role="button" data-col="tipo_comprobante">Tipo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="concepto" role="button" data-col="concepto">Concepto <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="modulo_origen" role="button" data-col="modulo_origen">Origen <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header" data-sort="total_debe" role="button" data-col="total_debe">Total <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" data-sort="estado" role="button" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyAsientos">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">No se encontraron asientos contables.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $statusBadge = '';
                            if ($r['estado'] === 'contabilizado') $statusBadge = '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Contabilizado</span>';
                            elseif ($r['estado'] === 'anulado') $statusBadge = '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Anulado</span>';
                            else $statusBadge = '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">Borrador</span>';
                            ?>
                            <tr class="asiento-row" role="button" onclick="ASIENTO_abrirModal(<?= $r['id'] ?>)">
                                <td class="ps-3 fw-bold" data-col="numero_comprobante"><?= htmlspecialchars($r['numero_comprobante'] ?? '') ?></td>
                                <td data-col="fecha_asiento"><?= htmlspecialchars($r['fecha_asiento'] ?? '') ?></td>
                                <td data-col="tipo_comprobante" class="text-capitalize"><?= htmlspecialchars($r['tipo_comprobante'] ?? '') ?></td>
                                <td data-col="concepto" class="small text-truncate" style="max-width: 250px;"><?= htmlspecialchars($r['concepto'] ?? '') ?></td>
                                <td data-col="modulo_origen" class="text-capitalize small text-muted"><?= str_replace('_', ' ', htmlspecialchars($r['modulo_origen'] ?? '')) ?></td>
                                <td data-col="total_debe" class="text-end fw-bold">$<?= number_format((float)($r['total_debe'] ?? 0), 2) ?></td>
                                <td class="text-center" data-col="estado"><?= $statusBadge ?></td>
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
<?php include 'modal_asiento.php'; ?>
<script src="<?= $base ?>/js/modulos/asientos_contables_modal.js?v=<?= time() ?>"></script>

<script>
    (function() {
        'use strict';
        const urlBase = '<?= $urlBaseModulo ?>';
        const inputB = document.getElementById('buscarAsiento');
        let currentSort = '<?= $ordenCol ?>';
        let currentDir = '<?= $ordenDir ?>';

        window.fetchSearch = async function(page = 1) {
            const b = inputB ? inputB.value.trim() : '';
            const uri = `${urlBase}/searchAjax?b=${encodeURIComponent(b)}&page=${page}&sort=${currentSort}&dir=${currentDir}`;
            try {
                const resp = await fetch(uri);
                const data = await resp.json();
                if (data.ok) {
                    window.currentPage = page;
                    document.getElementById('tbodyAsientos').innerHTML = data.rows;
                    document.getElementById('wrapper-pagination').innerHTML = data.pagination;
                    document.getElementById('paginationInfo').textContent = data.info;

                    document.querySelectorAll('.sortable-header').forEach(th => {
                        const icon = th.querySelector('i');
                        if (!icon) return;
                        if (th.dataset.sort === currentSort) {
                            icon.className = (currentDir.toLowerCase() === 'asc') ? 'bi bi-sort-alpha-down text-primary ms-1' : 'bi bi-sort-alpha-up text-primary ms-1';
                        } else icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
                    });
                }
            } catch (e) {}
        };

        // Alias para compatibilidad con llamadas existentes (guardar, anular, etc.)
        window.cambiarPaginaAjax = (p) => window.fetchSearch(p);

        document.querySelectorAll('.sortable-header').forEach(h => {
            h.addEventListener('click', () => {
                const f = h.dataset.sort;
                if (currentSort === f) currentDir = currentDir.toLowerCase() === 'asc' ? 'DESC' : 'ASC';
                else {
                    currentSort = f;
                    currentDir = 'ASC';
                }
                if (typeof window.guardarOrdenacionVista === 'function') {
                    window.guardarOrdenacionVista('asientos_contables', currentSort, currentDir);
                }
                window.fetchSearch(1);
            });
        });
    })();
</script>