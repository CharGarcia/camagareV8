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
/** @var array $periodicidades */

$base       = BASE_URL;
$urlBase    = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');
$rows       = $rows       ?? [];
$total      = $total      ?? 0;
$page       = $page       ?? 1;
$totalPages = $totalPages ?? 1;
$perPage    = $perPage    ?? 20;
$ordenCol   = $ordenCol   ?? 'proximo_cobro';
$ordenDir   = $ordenDir   ?? 'asc';
$buscar     = $buscar     ?? '';
$from       = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to         = $total > 0 ? min($page * $perPage, $total) : 0;

$estadoClases = [
    'activo'     => 'success',
    'pausado'    => 'warning',
    'suspendido' => 'danger',
    'cancelado'  => 'secondary',
];
?>

<style>
    .susc-scroll { max-height: calc(100vh - 240px); overflow-y: auto; }
    .susc-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
    .susc-row { cursor: pointer; }
    .susc-row:hover { background-color: rgba(0,0,0,.04); }
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-arrow-repeat text-primary me-2"></i><?= htmlspecialchars($titulo) ?></h5>
    <div class="d-flex gap-2">
        <?php if ($perm['crear']): ?>
            <button type="button" class="btn btn-primary btn-sm px-3" onclick="abrirModalSuscCrear()">
                <i class="bi bi-plus-lg"></i> Nueva Suscripción
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_busqueda.css?v=<?= time() ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_busqueda.js?v=<?= time() ?>"></script>
            <div id="fbBuscadorSUSC" style="width: 480px;"></div>
            <input type="hidden" id="buscarSusc" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosBusqueda) return;
                    new FiltrosBusqueda({
                        containerId: 'fbBuscadorSUSC',
                        hiddenInputId: 'buscarSusc',
                        fields: [
                            { key: 'cliente',  label: 'Cliente',         icon: 'bi-person',          type: 'text' },
                            { key: 'ruc',      label: 'Identificación',  icon: 'bi-card-text',       type: 'text' },
                            { key: 'proximo_cobro', label: 'Próximo cobro', icon: 'bi-calendar-event', type: 'date_range' },
                            { key: 'monto',    label: 'Monto',           icon: 'bi-currency-dollar', type: 'number_range' },
                            { key: 'estado',   label: 'Estado',          icon: 'bi-flag',            type: 'select', options: [
                                { v: 'activa',     l: 'Activa' },
                                { v: 'pausada',    l: 'Pausada' },
                                { v: 'cancelada',  l: 'Cancelada' },
                            ]},
                        ],
                        quickFilters: [
                            { id: 'qf_activa',  label: 'Activas',  mk: () => ({ key: 'estado', op: '=', value: 'activa',  display: 'Activa' }) },
                            { id: 'qf_pausada', label: 'Pausadas', mk: () => ({ key: 'estado', op: '=', value: 'pausada', display: 'Pausada' }) },
                            { id: 'qf_mes',     label: 'Próximo cobro este mes', mk: () => FiltrosBusqueda.helpers.esteMes('proximo_cobro') },
                        ],
                        onApply: () => window.fetchSearch && window.fetchSearch(1),
                    }).init();
                });
            </script>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="paginationContainer" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(<?= $page - 1 ?>)" <?= $page <= 1 ? 'disabled' : '' ?>><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(<?= $page + 1 ?>)" <?= $page >= $totalPages ? 'disabled' : '' ?>><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="susc-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="nombre_cliente">Cliente <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th>RUC/Cédula</th>
                        <th class="sortable-header" role="button" data-sort="nombre_periodicidad">Periodicidad <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" role="button" data-sort="forma_cobro">Cobro <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" role="button" data-sort="proximo_cobro">Próx. Cobro <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" role="button" data-sort="fecha_inicio">Inicio <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center">Ítems</th>
                        <th class="text-center">Pagos</th>
                        <th class="text-center pe-3 sortable-header" role="button" data-sort="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodySusc">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="9" class="text-center py-5 text-muted">No se encontraron suscripciones.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $cls        = $estadoClases[$r['estado'] ?? 'activo'] ?? 'secondary';
                            $lbl        = ucfirst($r['estado'] ?? 'activo');
                            $proxCobro  = !empty($r['proximo_cobro']) ? date('d-m-Y', strtotime($r['proximo_cobro'])) : '-';
                            $inicio     = !empty($r['fecha_inicio'])  ? date('d-m-Y', strtotime($r['fecha_inicio']))  : '-';
                            $iconCobro  = ($r['forma_cobro'] ?? '') === 'tarjeta'
                                ? '<i class="bi bi-credit-card text-primary" title="Tarjeta"></i>'
                                : '<i class="bi bi-file-text text-muted" title="Crédito"></i>';
                            $totalItems = (int)($r['total_items'] ?? 0);
                            ?>
                            <tr class="susc-row" role="button"
                                data-susc='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>'
                                onclick="abrirModalSuscEditar(this)">
                                <td class="ps-3 fw-medium"><?= htmlspecialchars($r['nombre_cliente'] ?? '') ?></td>
                                <td><small class="text-muted"><?= htmlspecialchars($r['identificacion_cliente'] ?? '') ?></small></td>
                                <td><?= htmlspecialchars($r['nombre_periodicidad'] ?? '-') ?></td>
                                <td class="text-center"><?= $iconCobro ?> <?= ucfirst($r['forma_cobro'] ?? '') ?></td>
                                <td class="text-center fw-medium"><?= $proxCobro ?></td>
                                <td class="text-center"><?= $inicio ?></td>
                                <td class="text-center">
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border">
                                        <?= $totalItems ?> ítem<?= $totalItems !== 1 ? 's' : '' ?>
                                    </span>
                                </td>
                                <td class="text-center"><?= (int)($r['total_pagos'] ?? 0) ?></td>
                                <td class="text-center pe-3">
                                    <span class="badge bg-<?= $cls ?> bg-opacity-10 text-<?= $cls ?> border border-<?= $cls ?> border-opacity-25"><?= $lbl ?></span>
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
<?php include 'modal_suscripcion.php'; ?>
<?php include 'modal_pagos.php'; ?>

<script>
(function () {
    'use strict';
    const urlBase = '<?= $urlBase ?>';
    const inputB  = document.getElementById('buscarSusc');
    window.currentSort = '<?= $ordenCol ?>';
    window.currentDir  = '<?= $ordenDir ?>';
    window.currentPage = <?= $page ?>;
    let timer;

    window.cambiarPaginaAjax = n => window.fetchSearch(n);

    window.fetchSearch = async (page = 1) => {
        const b   = inputB ? inputB.value.trim() : '';
        const uri = `${urlBase}/searchAjax?b=${encodeURIComponent(b)}&page=${page}&sort=${window.currentSort}&dir=${window.currentDir}`;
        try {
            const resp = await fetch(uri);
            const data = await resp.json();
            if (data.ok) {
                window.currentPage = page;
                document.getElementById('tbodySusc').innerHTML           = data.rows;
                document.getElementById('paginationContainer').innerHTML = data.pagination;
                document.getElementById('paginationInfo').textContent    = data.info;
            }
        } catch (e) { console.error(e); }
    };

    document.querySelectorAll('.sortable-header').forEach(h => {
        h.addEventListener('click', () => {
            const f = h.dataset.sort;
            if (window.currentSort === f) window.currentDir = window.currentDir.toLowerCase() === 'asc' ? 'DESC' : 'ASC';
            else { window.currentSort = f; window.currentDir = 'ASC'; }
            fetchSearch(1);
        });
    });

    if (inputB) inputB.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => fetchSearch(1), 400); });
})();
</script>
