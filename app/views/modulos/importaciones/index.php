<?php

/** @var array $perm */
/** @var string $rutaModulo */
/** @var array $rows */
/** @var int $total, $page, $totalPages, $perPage, $from, $to */
/** @var string $buscar, $ordenCol, $ordenDir */
/** @var array $bodegas */
/** @var array $vistaConfig */
/** @var array $puntos */
/** @var array $establecimientos */
/** @var array|null $sucursal_principal */

$base       = BASE_URL;
$urlBase    = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');
$rows       = $rows       ?? [];
$total      = $total      ?? 0;
$page       = $page       ?? 1;
$totalPages = $totalPages ?? 1;
$perPage    = $perPage    ?? 20;
$ordenCol   = $ordenCol   ?? 'fecha_nacionalizacion';
$ordenDir   = $ordenDir   ?? 'DESC';
$buscar     = $buscar     ?? '';
$from       = $from       ?? 0;
$to         = $to         ?? 0;
$bodegas    = $bodegas    ?? [];
$puntos     = $puntos     ?? [];

$estadoLabelMap = [
    'borrador'             => 'Borrador',
    'en_transito'          => 'En tránsito',
    'pendiente_aprobacion' => 'Pendiente aprobación',
    'nacionalizada'        => 'Nacionalizada',
    'cerrada'              => 'Cerrada',
    'anulada'              => 'Anulada',
];
?>
<style>
    .importaciones-scroll {
        max-height: calc(100dvh - 240px);
        overflow-y: auto;
    }

    .importaciones-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }

    .importacion-row {
        cursor: pointer;
    }

    .importacion-row:hover {
        background: rgba(0, 0, 0, .04);
    }

    .tab-pestaña .nav-link {
        font-size: .85rem;
        padding: .4rem .75rem;
        font-weight: 600;
    }

    #tbodyProductosFob tr td,
    #tbodyFacturasExterior tr td,
    #tbodyGastosImp tr td {
        padding: .3rem .4rem;
        vertical-align: middle;
    }

    #imp-asiento-body tr td {
        padding: .3rem .4rem;
        vertical-align: middle;
    }

    /* ── Apilado de modales sobre el modal de importación ── */
    .modal:not(#modalImportacion) {
        z-index: 6060 !important;
    }

    .modal-backdrop ~ .modal-backdrop {
        z-index: 6055 !important;
    }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-globe-americas"></i> <?= htmlspecialchars($titulo ?? 'Importaciones') ?></h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="abrirModalImportacionCrear()">
            <i class="bi bi-plus-lg"></i> Nueva
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_busqueda.css?v=<?= time() ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_busqueda.js?v=<?= time() ?>"></script>
            <div id="fbBuscadorImportaciones" style="width: 480px;"></div>
            <input type="hidden" id="inputBuscarImportaciones" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosBusqueda) return;
                    new FiltrosBusqueda({
                        containerId: 'fbBuscadorImportaciones',
                        hiddenInputId: 'inputBuscarImportaciones',
                        placeholder: 'Buscar...',
                        fields: [
                            { key: 'proveedor',       label: 'Proveedor del exterior', icon: 'bi-building',        type: 'text' },
                            { key: 'dai',             label: 'Referencia DAI',         icon: 'bi-file-earmark-text', type: 'text' },
                            { key: 'incoterm',        label: 'Incoterm',               icon: 'bi-truck',           type: 'text' },
                            { key: 'obs',             label: 'Observaciones',          icon: 'bi-chat-text',       type: 'text' },
                            { key: 'fecha',           label: 'Fecha nacionalización',  icon: 'bi-calendar-event',  type: 'date_range' },
                            { key: 'embarque',        label: 'Fecha embarque',         icon: 'bi-calendar-plus',   type: 'date_range' },
                            { key: 'llegada',         label: 'Fecha llegada',          icon: 'bi-calendar-check',  type: 'date_range' },
                            { key: 'total',           label: 'Costo nacionalizado',    icon: 'bi-currency-dollar', type: 'number_range' },
                            { key: 'fob',             label: 'Subtotal FOB',           icon: 'bi-cash-stack',      type: 'number_range' },
                            { key: 'estado',          label: 'Estado',                 icon: 'bi-flag',            type: 'select', options: [
                                { v: 'borrador',             l: 'Borrador' },
                                { v: 'en_transito',          l: 'En tránsito' },
                                { v: 'pendiente_aprobacion', l: 'Pendiente aprobación' },
                                { v: 'nacionalizada',        l: 'Nacionalizada' },
                                { v: 'cerrada',              l: 'Cerrada' },
                                { v: 'anulada',              l: 'Anulada' },
                            ]},
                        ],
                        quickFilters: [
                            { id: 'qf_mes',        label: 'Este mes',    mk: () => FiltrosBusqueda.helpers.esteMes('fecha') },
                            { id: 'qf_anio',       label: 'Este año',    mk: () => FiltrosBusqueda.helpers.esteAnio('fecha') },
                            { id: 'qf_borrador',   label: 'Borradores',  mk: () => ({ key: 'estado', op: '=', value: 'borrador', display: 'Borrador' }) },
                        ],
                        onApply: () => window.CMG_fetchSearchImp && window.CMG_fetchSearchImp(1),
                    }).init();
                });
            </script>
            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'numero_importacion'        => 'N° Importación',
                    'referencia_dai'            => 'Referencia DAI',
                    'proveedor_nombre'          => 'Proveedor exterior',
                    'incoterm'                  => 'Incoterm',
                    'bodega_nombre'             => 'Bodega destino',
                    'fecha_nacionalizacion'     => 'Fecha nacionalización',
                    'subtotal_fob'              => 'Subtotal FOB',
                    'costo_total_nacionalizado' => 'Costo total',
                    'estado'                    => 'Estado',
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>
                <a id="btnExportPdf" href="<?= $urlBase ?>/export-pdf?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                    class="btn btn-outline-danger" title="PDF"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                <a id="btnExportExcel" href="<?= $urlBase ?>/export-excel?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                    class="btn btn-outline-success" title="Excel"><i class="bi bi-file-earmark-spreadsheet"></i> Excel</a>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="paginationContainer" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" <?= $page <= 1 ? 'disabled' : '' ?> onclick="CMG_cambiarPaginaImp(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" <?= $page >= $totalPages ? 'disabled' : '' ?> onclick="CMG_cambiarPaginaImp(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="importaciones-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="numero_importacion" data-col="numero_importacion">N° Importación <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="referencia_dai" data-col="referencia_dai">Referencia DAI <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="proveedor_nombre" data-col="proveedor_nombre">Proveedor exterior <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th data-col="incoterm">Incoterm</th>
                        <th data-col="bodega_nombre">Bodega destino</th>
                        <th class="sortable-header" role="button" data-sort="fecha_nacionalizacion" data-col="fecha_nacionalizacion">Fecha nacionalización <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header" role="button" data-sort="subtotal_fob" data-col="subtotal_fob">Subtotal FOB <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header fw-bold" role="button" data-sort="costo_total_nacionalizado" data-col="costo_total_nacionalizado">Costo total <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center pe-3 sortable-header" role="button" data-sort="estado" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyImportaciones">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted"><i class="bi bi-globe-americas fs-3 d-block mb-2"></i>No se encontraron importaciones.</td>
                        </tr>
                        <?php else: foreach ($rows as $r):
                            $estado = $r['estado'] ?? 'borrador';
                            $estadoClass = match ($estado) {
                                'nacionalizada'        => 'bg-success bg-opacity-10 text-success border-success',
                                'cerrada'              => 'bg-primary bg-opacity-10 text-primary border-primary',
                                'anulada'              => 'bg-danger bg-opacity-10 text-danger border-danger',
                                'en_transito'          => 'bg-warning bg-opacity-10 text-warning border-warning',
                                'pendiente_aprobacion' => 'bg-info bg-opacity-10 text-info border-info',
                                default                => 'bg-secondary bg-opacity-10 text-secondary border-secondary',
                            };
                            $estadoLabel = $estadoLabelMap[$estado] ?? ucfirst($estado);
                        ?>
                            <tr class="importacion-row" role="button" tabindex="0"
                                data-row='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>'
                                onclick="abrirModalImportacion(this)">
                                <td class="ps-3" data-col="numero_importacion"><code class="text-secondary"><?= htmlspecialchars($r['numero_importacion'] ?? '—') ?></code></td>
                                <td data-col="referencia_dai"><?= htmlspecialchars($r['referencia_dai'] ?? '—') ?></td>
                                <td class="fw-medium text-truncate" style="max-width:220px" data-col="proveedor_nombre"><?= htmlspecialchars($r['proveedor_nombre'] ?? '—') ?></td>
                                <td data-col="incoterm"><small class="text-muted"><?= htmlspecialchars($r['incoterm'] ?? '—') ?></small></td>
                                <td data-col="bodega_nombre"><small class="text-muted"><?= htmlspecialchars($r['bodega_nombre'] ?? '—') ?></small></td>
                                <td data-col="fecha_nacionalizacion"><?= !empty($r['fecha_nacionalizacion']) ? date('d-m-Y', strtotime($r['fecha_nacionalizacion'])) : '—' ?></td>
                                <td class="text-end" data-col="subtotal_fob">$<?= number_format((float) ($r['subtotal_fob'] ?? 0), 2) ?></td>
                                <td class="text-end fw-bold" data-col="costo_total_nacionalizado">$<?= number_format((float) ($r['costo_total_nacionalizado'] ?? 0), 2) ?></td>
                                <td class="text-center pe-3" data-col="estado"><span class="badge <?= $estadoClass ?> border border-opacity-25"><?= $estadoLabel ?></span></td>
                            </tr>
                    <?php endforeach;
                    endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Datos PHP para JS -->
<script>
    window.BASE_URL = '<?= $base ?>';
    window.CMG_urlBaseImp = '<?= $urlBase ?>';
    window.CMG_currentSortImp = '<?= $ordenCol ?>';
    window.CMG_currentDirImp = '<?= $ordenDir ?>';
    window.CMG_currentPageImp = <?= $page ?>;
    window.CMG_permImp = {
        crear: <?= $perm['crear'] ? 'true' : 'false' ?>,
        actualizar: <?= $perm['actualizar'] ? 'true' : 'false' ?>,
        eliminar: <?= $perm['eliminar'] ? 'true' : 'false' ?>
    };
    window.CMG_bodegasImp = <?= json_encode(array_values($bodegas ?? [])) ?>;
</script>

<?php include __DIR__ . '/modal_importacion.php'; ?>
<script src="<?= $base ?>/js/modulos/asiento_contable_tab.js?v=<?= time() ?>"></script>
<script src="<?= $base ?>/js/modulos/importaciones.js?v=<?= time() ?>"></script>

<script>
    (function() {
        'use strict';
        const input = document.getElementById('inputBuscarImportaciones');

        window.CMG_cambiarPaginaImp = (n) => CMG_fetchSearchImp(n);

        window.CMG_fetchSearchImp = async (page = 1) => {
            const term = input ? input.value.trim() : '';
            const uri = `${window.CMG_urlBaseImp}/searchAjax?b=${encodeURIComponent(term)}&page=${page}&sort=${window.CMG_currentSortImp}&dir=${window.CMG_currentDirImp}`;
            try {
                const resp = await fetch(uri);
                const data = await resp.json();
                if (data.ok) {
                    window.CMG_currentPageImp = page;
                    document.getElementById('tbodyImportaciones').innerHTML = data.rows;
                    document.getElementById('paginationContainer').innerHTML = `
                    <button type="button" class="btn btn-outline-secondary" ${page<=1?'disabled':''} onclick="CMG_cambiarPaginaImp(${page-1})"><i class="bi bi-chevron-left"></i></button>
                    <button type="button" class="btn btn-outline-secondary" ${page>=data.totalPages?'disabled':''} onclick="CMG_cambiarPaginaImp(${page+1})"><i class="bi bi-chevron-right"></i></button>`;
                    document.getElementById('paginationInfo').textContent = data.info;
                    document.getElementById('btnExportPdf').href = data.pdf_url;
                    document.getElementById('btnExportExcel').href = data.excel_url;
                    actualizarIconosOrdenImp();
                }
            } catch (e) {
                console.error('Error búsqueda importaciones:', e);
            }
        };

        function actualizarIconosOrdenImp() {
            document.querySelectorAll('.sortable-header').forEach(th => {
                const icon = th.querySelector('i');
                const field = th.dataset.sort;
                if (!icon) return;
                if (field === window.CMG_currentSortImp) {
                    icon.className = window.CMG_currentDirImp.toLowerCase() === 'asc' ?
                        'bi bi-sort-alpha-down text-primary ms-1' :
                        'bi bi-sort-alpha-up text-primary ms-1';
                } else {
                    icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
                }
            });
        }

        document.querySelectorAll('.sortable-header').forEach(h => {
            h.addEventListener('click', () => {
                const f = h.dataset.sort;
                if (!f) return;
                if (window.CMG_currentSortImp === f) {
                    window.CMG_currentDirImp = window.CMG_currentDirImp.toLowerCase() === 'asc' ? 'DESC' : 'ASC';
                } else {
                    window.CMG_currentSortImp = f;
                    window.CMG_currentDirImp = 'ASC';
                }
                if (typeof window.guardarOrdenacionVista === 'function') {
                    window.guardarOrdenacionVista('importaciones', window.CMG_currentSortImp, window.CMG_currentDirImp);
                }
                CMG_fetchSearchImp(1);
            });
        });

        let timerId;
        if (input) {
            input.addEventListener('input', () => {
                clearTimeout(timerId);
                timerId = setTimeout(() => CMG_fetchSearchImp(1), 380);
            });
        }
        actualizarIconosOrdenImp();
    })();
</script>
