<?php

/** @var array $perm */
/** @var string $rutaModulo */
/** @var array $rows */
/** @var int $total, $page, $totalPages, $perPage, $from, $to */
/** @var string $buscar, $ordenCol, $ordenDir */
/** @var array $formasPago, $tarifasIva, $sustentos, $puntos, $establecimientos */
/** @var array $empresa, $vistaConfig */

$base       = BASE_URL;
$urlBase    = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');
$rows       = $rows       ?? [];
$total      = $total      ?? 0;
$page       = $page       ?? 1;
$totalPages = $totalPages ?? 1;
$perPage    = $perPage    ?? 20;
$ordenCol   = $ordenCol   ?? 'fecha_emision';
$ordenDir   = $ordenDir   ?? 'DESC';
$buscar     = $buscar     ?? '';
$from       = $from       ?? 0;
$to         = $to         ?? 0;

$tiposComprobanteMap = [
    '01' => 'Factura',
    '03' => 'Liquidación de Compra',
    '04' => 'Nota de Venta',
    '05' => 'Nota de Crédito',
    '06' => 'Nota de Débito',
    '09' => 'Tique Máq. Registradora',
    '11' => 'Pasaje',
    '12' => 'Inst. Financiera',
    '15' => 'Comp. Reembolso',
    '16' => 'Comp. Socio Pasajero',
    '18' => 'Documento Import.',
    '19' => 'Comp. Combustible',
    '20' => 'Liquidación Gas',
    '21' => 'Notas de Crédito RISE',
    '41' => 'Comp. Reemb. Exterior',
    '42' => 'Comp. Servicio',
    '43' => 'Liquidación Imp.',
    '47' => 'Nota de Crédito Prestamista',
    '48' => 'Nota de Débito Prestamista',
];
?>
<style>
    .compras-scroll {
        max-height: calc(100dvh - 240px);
        overflow-y: auto;
    }

    .compras-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }

    .compra-row {
        cursor: pointer;
    }

    .compra-row:hover {
        background: rgba(0, 0, 0, .04);
    }

    .tab-pestaña .nav-link {
        font-size: .85rem;
        padding: .4rem .75rem;
        font-weight: 600;
    }

    .precio-input {
        font-family: monospace;
    }

    #tbodyDetalle tr td {
        padding: .3rem .4rem;
        vertical-align: middle;
    }

    .total-row td {
        font-weight: 600;
        background: #f8f9fa;
    }

    #asientoBody tr td {
        padding: .3rem .4rem;
        vertical-align: middle;
    }

    .cuadra-ok {
        color: #198754;
        font-weight: 700;
    }

    .cuadra-mal {
        color: #dc3545;
        font-weight: 700;
    }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-cart3"></i> <?= htmlspecialchars($titulo ?? 'Compras') ?></h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="abrirModalCompraCrear()">
            <i class="bi bi-plus-lg"></i> Nueva
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_busqueda.css?v=<?= time() ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_busqueda.js?v=<?= time() ?>"></script>
            <div id="fbBuscadorCompras" style="width: 480px;"></div>
            <input type="hidden" id="inputBuscarCompras" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosBusqueda) return;
                    new FiltrosBusqueda({
                        containerId: 'fbBuscadorCompras',
                        hiddenInputId: 'inputBuscarCompras',
                        placeholder: 'Buscar...',
                        fields: [
                            { key: 'proveedor',      label: 'Proveedor',         icon: 'bi-building',        type: 'text' },
                            { key: 'ruc',            label: 'RUC',               icon: 'bi-card-text',       type: 'text' },
                            { key: 'numero',         label: 'Nº comprobante',    icon: 'bi-hash',            type: 'text' },
                            { key: 'autorizacion',   label: 'Nº autorización',   icon: 'bi-shield-check',    type: 'text' },
                            { key: 'usuario',        label: 'Usuario',           icon: 'bi-person-circle',   type: 'text' },
                            { key: 'observacion',    label: 'Observaciones',     icon: 'bi-chat-text',       type: 'text' },
                            { key: 'sustento',       label: 'Sustento tributario', icon: 'bi-file-earmark-text', type: 'text' },
                            { key: 'fecha',          label: 'Fecha emisión',     icon: 'bi-calendar-event',  type: 'date_range' },
                            { key: 'fecha_registro', label: 'Fecha registro',    icon: 'bi-calendar-plus',   type: 'date_range' },
                            { key: 'monto',          label: 'Monto total',       icon: 'bi-currency-dollar', type: 'number_range' },
                            { key: 'subtotal',       label: 'Subtotal',          icon: 'bi-receipt',         type: 'number_range' },
                            { key: 'tipo',           label: 'Tipo comprobante',  icon: 'bi-file-earmark',    type: 'select', options: [
                                { v: '01', l: 'Factura' },
                                { v: '03', l: 'Liquidación de compra' },
                                { v: '04', l: 'Nota de crédito' },
                                { v: '05', l: 'Nota de débito' },
                                { v: '07', l: 'Comprobante de retención' },
                            ]},
                        ],
                        quickFilters: [
                            { id: 'qf_hoy',        label: 'Hoy',         mk: () => FiltrosBusqueda.helpers.hoyMismo('fecha') },
                            { id: 'qf_mes',        label: 'Este mes',    mk: () => FiltrosBusqueda.helpers.esteMes('fecha') },
                            { id: 'qf_mes_pasado', label: 'Mes pasado',  mk: () => FiltrosBusqueda.helpers.mesPasado('fecha') },
                            { id: 'qf_anio',       label: 'Este año',    mk: () => FiltrosBusqueda.helpers.esteAnio('fecha') },
                        ],
                        onApply: () => window.CMG_fetchSearch && window.CMG_fetchSearch(1),
                    }).init();
                });
            </script>
            <?php /* form de compatibilidad para no romper marcado existente */ ?>
            <form id="frmBuscarCompras" class="d-none" onsubmit="return false;">
            </form>
            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'secuencial_prov'  => 'N° Comprobante',
                    'fecha_emision'    => 'Fecha',
                    'proveedor_nombre' => 'Proveedor',
                    'proveedor_ruc'    => 'RUC',
                    'tipo_comprobante' => 'Tipo',
                    'sustento_nombre'  => 'Sustento',
                    'total_sin_impuestos' => 'Subtotal',
                    'monto_iva'        => 'IVA',
                    'importe_total'    => 'Total',
                    'estado_pago'      => 'Pago',
                    'estado'           => 'Estado',
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
                <button type="button" class="btn btn-outline-secondary" <?= $page <= 1 ? 'disabled' : '' ?> onclick="CMG_cambiarPagina(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" <?= $page >= $totalPages ? 'disabled' : '' ?> onclick="CMG_cambiarPagina(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="compras-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="secuencial_prov" data-col="secuencial_prov">N° Comprobante <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="fecha_emision" data-col="fecha_emision">Fecha <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="proveedor_nombre" data-col="proveedor_nombre">Proveedor <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="proveedor_ruc" data-col="proveedor_ruc">RUC / ID <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="tipo_comprobante" data-col="tipo_comprobante">Tipo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="sustento_nombre" data-col="sustento_nombre">Sustento <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header" role="button" data-sort="total_sin_impuestos" data-col="total_sin_impuestos">Subtotal <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header" role="button" data-sort="monto_iva" data-col="monto_iva">IVA <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header fw-bold" role="button" data-sort="importe_total" data-col="importe_total">Total <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" role="button" data-col="estado_pago">Pago</th>
                        <th class="text-center pe-3 sortable-header" role="button" data-sort="estado" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyCompras">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="11" class="text-center py-5 text-muted"><i class="bi bi-cart3 fs-3 d-block mb-2"></i>No se encontraron compras.</td>
                        </tr>
                        <?php else: foreach ($rows as $r): 
                            $importeTotal = (float)($r['importe_total'] ?? 0);
                            $pagado       = (float)($r['total_pagado'] ?? 0);
                            $nc           = (float)($r['total_nc'] ?? 0);
                            $retencion    = (float)($r['total_retencion'] ?? 0);
                            $saldo        = max(0, $importeTotal - $pagado - $nc - $retencion);

                            if ($saldo <= 0.01) {
                                $estadoPagoBadge = '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Pagada</span>';
                            } elseif (($pagado + $nc + $retencion) > 0) {
                                $estadoPagoBadge = '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">Abonada</span>';
                            } else {
                                $estadoPagoBadge = '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Pendiente</span>';
                            }
                            
                            $estado = $r['estado'] ?? 'borrador';
                            $estadoClass = match ($estado) {
                                'registrado'             => 'bg-success bg-opacity-10 text-success border-success',
                                'anulado'                => 'bg-danger bg-opacity-10 text-danger border-danger',
                                'borrador'               => 'bg-secondary bg-opacity-10 text-secondary border-secondary',
                                default                  => 'bg-primary bg-opacity-10 text-primary border-primary',
                            };
                        ?>
                            <tr class="compra-row" role="button" tabindex="0"
                                data-row='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>'
                                onclick="abrirModalCompra(this)">
                                <td class="ps-3" data-col="secuencial_prov"><code class="text-secondary"><?= htmlspecialchars(($r['establecimiento_prov'] ?? '') . '-' . ($r['punto_emision_prov'] ?? '') . '-' . ($r['secuencial_prov'] ?? '')) ?></code></td>
                                <td data-col="fecha_emision"><?= !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '-' ?></td>
                                <td class="fw-medium text-truncate" style="max-width:220px" data-col="proveedor_nombre"><?= htmlspecialchars($r['proveedor_nombre'] ?? '-') ?></td>
                                <td data-col="proveedor_ruc"><small class="text-muted"><?= htmlspecialchars($r['proveedor_ruc'] ?? '-') ?></small></td>
                                <td data-col="tipo_comprobante"><small><?= htmlspecialchars($tiposComprobanteMap[$r['tipo_comprobante'] ?? '01'] ?? $r['tipo_comprobante'] ?? '-') ?></small></td>
                                <td data-col="sustento_nombre" class="text-truncate" style="max-width:160px"><small class="text-muted"><?= htmlspecialchars($r['sustento_nombre'] ?? '-') ?></small></td>
                                <td class="text-end" data-col="total_sin_impuestos"><?= number_format((float)($r['total_sin_impuestos'] ?? 0), 2) ?></td>
                                <td class="text-end" data-col="monto_iva">$<?= number_format((float)($r['monto_iva'] ?? 0), 2) ?></td>
                                <td class="text-end fw-bold" data-col="importe_total">$<?= number_format((float)($r['importe_total'] ?? 0), 2) ?></td>
                                <td class="text-center" data-col="estado_pago"><?= $estadoPagoBadge ?></td>
                                <td class="text-center pe-3" data-col="estado"><span class="badge <?= $estadoClass ?> border border-opacity-25"><?= ucfirst($estado) ?></span></td>
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
    window.CMG_urlBase = '<?= $urlBase ?>';
    window.CMG_currentSort = '<?= $ordenCol ?>';
    window.CMG_currentDir = '<?= $ordenDir ?>';
    window.CMG_currentPage = <?= $page ?>;
    window.CMG_perm = {
        crear: <?= $perm['crear'] ? 'true' : 'false' ?>,
        actualizar: <?= $perm['actualizar'] ? 'true' : 'false' ?>,
        eliminar: <?= $perm['eliminar'] ? 'true' : 'false' ?>
    };
    window.CMG_formasPago = <?= json_encode(array_values($formasPago ?? [])) ?>;
    window.CMG_tarifasIva = <?= json_encode(array_values($tarifasIva ?? [])) ?>;
    window.CMG_sustentos = <?= json_encode(array_values($sustentos ?? [])) ?>;
    window.CMG_puntos = <?= json_encode(array_values($puntos ?? [])) ?>;
    window.CMG_unidadesMedida = <?= json_encode(array_values($unidadesMedida ?? [])) ?>;
    window.CMG_bodegas = <?= json_encode(array_values($bodegas ?? [])) ?>;
    window.CMG_tiposComp = <?= json_encode($tiposComprobanteMap) ?>;
    window.CMG_empresa = <?= json_encode($empresa ?? []) ?>;
    window.CMG_sucursal = <?= json_encode($sucursal_principal ?? []) ?>;
</script>

<?php include __DIR__ . '/modal_compra.php'; ?>
<?php include MVC_APP . '/views/modulos/retenciones_compras/modal_retencion.php'; ?>
<script src="<?= $base ?>/js/modulos/proveedores_modal.js?v=<?= time() ?>"></script>
<script src="<?= $base ?>/js/modulos/productos_modal.js?v=<?= time() ?>"></script>
<script src="<?= $base ?>/js/modulos/asiento_contable_tab.js?v=<?= time() ?>"></script>
<script src="<?= $base ?>/js/modulos/compras.js?v=<?= time() ?>"></script>
<script src="<?= $base ?>/js/modulos/retenciones_compras.js?v=<?= time() ?>"></script>

<script>
    (function() {
        'use strict';
        const input = document.getElementById('inputBuscarCompras');

        window.CMG_cambiarPagina = (n) => CMG_fetchSearch(n);

        window.CMG_fetchSearch = async (page = 1) => {
            const term = input ? input.value.trim() : '';
            const uri = `${window.CMG_urlBase}/searchAjax?b=${encodeURIComponent(term)}&page=${page}&sort=${window.CMG_currentSort}&dir=${window.CMG_currentDir}`;
            try {
                const resp = await fetch(uri);
                const data = await resp.json();
                if (data.ok) {
                    window.CMG_currentPage = page;
                    document.getElementById('tbodyCompras').innerHTML = data.rows;
                    document.getElementById('paginationContainer').innerHTML = `
                    <button type="button" class="btn btn-outline-secondary" ${page<=1?'disabled':''} onclick="CMG_cambiarPagina(${page-1})"><i class="bi bi-chevron-left"></i></button>
                    <button type="button" class="btn btn-outline-secondary" ${page>=data.totalPages?'disabled':''} onclick="CMG_cambiarPagina(${page+1})"><i class="bi bi-chevron-right"></i></button>`;
                    document.getElementById('paginationInfo').textContent = data.info;
                    document.getElementById('btnExportPdf').href = data.pdf_url;
                    document.getElementById('btnExportExcel').href = data.excel_url;
                    actualizarIconosOrden();
                }
            } catch (e) {
                console.error('Error búsqueda compras:', e);
            }
        };

        function actualizarIconosOrden() {
            document.querySelectorAll('.sortable-header').forEach(th => {
                const icon = th.querySelector('i');
                const field = th.dataset.sort;
                if (field === window.CMG_currentSort) {
                    icon.className = window.CMG_currentDir.toLowerCase() === 'asc' ?
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
                if (window.CMG_currentSort === f) {
                    window.CMG_currentDir = window.CMG_currentDir.toLowerCase() === 'asc' ? 'DESC' : 'ASC';
                } else {
                    window.CMG_currentSort = f;
                    window.CMG_currentDir = 'ASC';
                }
                if (typeof window.guardarOrdenacionVista === 'function') {
                    window.guardarOrdenacionVista('compras', window.CMG_currentSort, window.CMG_currentDir);
                }
                CMG_fetchSearch(1);
            });
        });

        let timerId;
        if (input) {
            input.addEventListener('input', () => {
                clearTimeout(timerId);
                timerId = setTimeout(() => CMG_fetchSearch(1), 380);
            });
        }
        actualizarIconosOrden();
    })();
</script>