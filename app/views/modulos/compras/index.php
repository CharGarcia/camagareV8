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

    /* ── Apilado de modales sobre el modal de compra ──
       app.css fuerza `.modal { z-index: 5060 !important }` a TODOS los modales,
       lo que deja los submodales (nuevo producto / proveedor / retención) por
       detrás del modal de compra. `#modalCompra` es el modal base; cualquier
       otro modal se abre ENCIMA de él. El selector `.modal:not(#modalCompra)`
       incluye un ID, así que gana en especificidad al `.modal` global y eleva
       todos los modales apilados. El backdrop del 2.º modal (hermano `~`) se
       coloca por encima del modal de compra (5060) para oscurecerlo. */
    .modal:not(#modalCompra) {
        z-index: 6060 !important;
    }

    /* El 2.º backdrop (y siguientes) va por encima del modal de compra (5060) */
    .modal-backdrop ~ .modal-backdrop {
        z-index: 6055 !important;
    }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold">
        <i class="bi bi-cart3"></i> <?= htmlspecialchars($titulo ?? 'Compras') ?>
        <?php if (!empty($pendientesAprob)): ?>
            <?php // Atajo al filtro: quien tiene que aprobar llega en un clic. ?>
            <a href="#" class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 text-decoration-none ms-2"
               style="font-size:.7rem;" onclick="CMG_filtrarPendientesAprobacion(); return false;"
               title="Ver las compras pendientes de aprobación">
                <i class="bi bi-hourglass-split me-1"></i><?= (int) $pendientesAprob ?> pendiente<?= $pendientesAprob > 1 ? 's' : '' ?> de aprobación
            </a>
        <?php endif; ?>
    </h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="abrirModalCompraCrear()">
            <i class="bi bi-plus-lg"></i> Nueva
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php
            // Buscador estándar: texto libre (sin sugerencias), embudo que abre el modal de
            // filtros y chips dentro de la caja. Las claves (key) deben existir en los mapas
            // de ComprasRepository::getListado().
            $opcionesSerie    = array_map(fn($s) => ['v' => $s['establecimiento'] . '-' . $s['punto_emision'], 'l' => $s['establecimiento'] . '-' . $s['punto_emision']], $seriesFiltro ?? []);
            // Solo los tipos y sustentos que esta empresa realmente usó en Compras: no se
            // ofrecen filtros que nunca van a traer resultados.
            $opcionesTipo     = array_map(fn($tc) => ['v' => $tc['codigo_comprobante'], 'l' => $tc['codigo_comprobante'] . ' - ' . trim((string) $tc['comprobante'])], $tiposComprobanteUsados ?? []);
            $opcionesSustento = array_map(fn($s) => ['v' => (string) $s['id'], 'l' => $s['codigo'] . ' - ' . $s['nombre']], $sustentosFiltro ?? []);
            $opcionesUsuario  = array_map(fn($u) => ['v' => (string) $u['id'], 'l' => $u['nombre']], $usuariosFiltro ?? []);
            $siNo = fn(string $si, string $no) => [['v' => 'si', 'l' => $si], ['v' => 'no', 'l' => $no]];
            $tC = 'Compra';
            // Orden pensado en filas de 12 columnas:
            //   Documento: [Fecha de emisión 6][Fecha de registro 6]
            //              [Estado 3][Estado de pago 3][Tipo de comprobante 3][Tipo de registro 3]
            //              [Serie 3][Nº comprobante 3][Secuencial 2][Nº autorización 4]
            //              [Sustento 6][Deducible 3][Documento modificado 3]
            //              [Asiento 3][Orden de compra 3][Retención 3][Parte relacionada 3]
            //   Valores:   [Total 4][Subtotal 4][IVA 4]
            //              [Descuento 4][Saldo pendiente 4][Valor retenido 4]
            //   Proveedor: [Proveedor 4][RUC 4][Usuario 4]
            //              [Observaciones 12]
            $filtrosCompras = [
                ['tab' => $tC, 'key' => 'fecha',          'label' => 'Fecha de emisión',   'icon' => 'bi-calendar-event',  'type' => 'date_range',   'grupo' => 'Documento', 'col' => 6, 'atajos' => true],
                ['tab' => $tC, 'key' => 'fecha_registro', 'label' => 'Fecha de registro',  'icon' => 'bi-calendar-plus',   'type' => 'date_range',   'grupo' => 'Documento', 'col' => 6],
                ['tab' => $tC, 'key' => 'estado',         'label' => 'Estado',             'icon' => 'bi-flag',            'type' => 'select',       'grupo' => 'Documento', 'col' => 3, 'options' => [
                    ['v' => 'registrado',           'l' => 'Registrado'],
                    ['v' => 'pendiente_aprobacion', 'l' => 'Pendiente de aprobación'],
                    ['v' => 'rechazada',            'l' => 'Rechazada'],
                    ['v' => 'anulado',              'l' => 'Anulado'],
                    ['v' => 'borrador',             'l' => 'Borrador'],
                ]],
                ['tab' => $tC, 'key' => 'pago',           'label' => 'Estado de pago',     'icon' => 'bi-wallet2',         'type' => 'select',       'grupo' => 'Documento', 'col' => 3, 'options' => [
                    ['v' => 'pendiente', 'l' => 'Pendiente'],
                    ['v' => 'abonada',   'l' => 'Abonada'],
                    ['v' => 'pagada',    'l' => 'Pagada'],
                ]],
                ['tab' => $tC, 'key' => 'tipo',           'label' => 'Tipo de comprobante','icon' => 'bi-file-earmark',    'type' => 'select',       'grupo' => 'Documento', 'col' => 3, 'options' => $opcionesTipo],
                ['tab' => $tC, 'key' => 'tipo_registro',  'label' => 'Tipo de registro',   'icon' => 'bi-cloud-download',  'type' => 'select',       'grupo' => 'Documento', 'col' => 3, 'options' => [
                    ['v' => 'electronico', 'l' => 'Electrónico (XML del SRI)'],
                    ['v' => 'fisica',      'l' => 'Física (registro manual)'],
                    ['v' => 'migrado',     'l' => 'Migrado'],
                ]],
                ['tab' => $tC, 'key' => 'serie',          'label' => 'Serie del proveedor','icon' => 'bi-upc-scan',        'type' => 'select',       'grupo' => 'Documento', 'col' => 3, 'options' => $opcionesSerie],
                ['tab' => $tC, 'key' => 'numero',         'label' => 'Nº comprobante',     'icon' => 'bi-hash',            'type' => 'text',         'grupo' => 'Documento', 'col' => 3, 'placeholder' => '001-001-000000123'],
                ['tab' => $tC, 'key' => 'secuencial',     'label' => 'Secuencial',         'icon' => 'bi-123',             'type' => 'text',         'grupo' => 'Documento', 'col' => 2, 'placeholder' => 'Sin ceros'],
                ['tab' => $tC, 'key' => 'autorizacion',   'label' => 'Nº autorización',    'icon' => 'bi-shield-check',    'type' => 'text',         'grupo' => 'Documento', 'col' => 4],
                ['tab' => $tC, 'key' => 'id_sustento',    'label' => 'Sustento tributario','icon' => 'bi-file-earmark-text','type' => 'select',      'grupo' => 'Documento', 'col' => 6, 'options' => $opcionesSustento],
                ['tab' => $tC, 'key' => 'deducible',      'label' => 'Deducible',          'icon' => 'bi-receipt-cutoff',  'type' => 'select',       'grupo' => 'Documento', 'col' => 3, 'options' => [
                    ['v' => 'declaracion_iva', 'l' => 'Declaración de IVA'],
                    ['v' => 'gasto_personal',  'l' => 'Gasto personal'],
                ]],
                ['tab' => $tC, 'key' => 'documento_modificado', 'label' => 'Documento modificado', 'icon' => 'bi-arrow-return-left', 'type' => 'text', 'grupo' => 'Documento', 'col' => 3, 'placeholder' => 'Nº de la factura (NC)'],
                ['tab' => $tC, 'key' => 'asiento',        'label' => 'Asiento contable',   'icon' => 'bi-journal-check',   'type' => 'select',       'grupo' => 'Documento', 'col' => 3, 'options' => $siNo('Con asiento', 'Sin asiento')],
                ['tab' => $tC, 'key' => 'orden_compra',   'label' => 'Orden de compra',    'icon' => 'bi-clipboard-check', 'type' => 'select',       'grupo' => 'Documento', 'col' => 3, 'options' => $siNo('Con orden de compra', 'Sin orden de compra')],
                ['tab' => $tC, 'key' => 'retencion',      'label' => 'Retención',          'icon' => 'bi-percent',         'type' => 'select',       'grupo' => 'Documento', 'col' => 3, 'options' => $siNo('Con retención', 'Sin retención')],
                ['tab' => $tC, 'key' => 'parte_relacionada', 'label' => 'Parte relacionada', 'icon' => 'bi-diagram-2',    'type' => 'select',       'grupo' => 'Documento', 'col' => 3, 'options' => $siNo('Sí', 'No')],
                // Valores
                ['tab' => $tC, 'key' => 'monto',     'label' => 'Total',           'icon' => 'bi-currency-dollar', 'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                ['tab' => $tC, 'key' => 'subtotal',  'label' => 'Subtotal',        'icon' => 'bi-receipt',         'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                ['tab' => $tC, 'key' => 'iva',       'label' => 'IVA',             'icon' => 'bi-percent',         'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                ['tab' => $tC, 'key' => 'descuento', 'label' => 'Descuento',       'icon' => 'bi-tag',             'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                ['tab' => $tC, 'key' => 'saldo',     'label' => 'Saldo pendiente', 'icon' => 'bi-wallet',          'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                ['tab' => $tC, 'key' => 'retenido',  'label' => 'Valor retenido',  'icon' => 'bi-scissors',        'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                // Proveedor
                ['tab' => $tC, 'key' => 'proveedor',   'label' => 'Proveedor',            'icon' => 'bi-building',       'type' => 'text',   'grupo' => 'Proveedor', 'col' => 4],
                ['tab' => $tC, 'key' => 'ruc',         'label' => 'RUC / Cédula',         'icon' => 'bi-card-text',      'type' => 'text',   'grupo' => 'Proveedor', 'col' => 4],
                ['tab' => $tC, 'key' => 'id_usuario',  'label' => 'Usuario que registró', 'icon' => 'bi-person-gear',    'type' => 'select', 'grupo' => 'Proveedor', 'col' => 4, 'options' => $opcionesUsuario],
                ['tab' => $tC, 'key' => 'obs',         'label' => 'Observaciones',        'icon' => 'bi-chat-left-text', 'type' => 'text',   'grupo' => 'Proveedor', 'col' => 12],
            ];
            ?>
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
            <div id="fmBuscadorCompras"></div>
            <input type="hidden" id="inputBuscarCompras" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosModal) return;
                    // Se guarda la instancia: el acceso "N pendientes de aprobación" del
                    // título aplica su filtro a través de ella (CMG_filtrarPendientesAprobacion).
                    window.CMG_filtros = new FiltrosModal({
                        containerId: 'fmBuscadorCompras',
                        hiddenInputId: 'inputBuscarCompras',
                        placeholder: 'Buscar en todas las columnas...',
                        titulo: 'Filtros de compras',
                        inputWidth: 420,
                        extraId: 'fmExtraCompras',   // columnas + PDF + Excel, pegados al final del grupo
                        // Pestaña Detalles: búsqueda libre dentro de las compras (productos,
                        // formas de pago, información adicional y reembolsos de terceros).
                        busquedaDetalle: {
                            tab: 'Detalles',
                            url: `<?= BASE_URL ?>/<?= $rutaModulo ?>/buscarDetallesAjax`,
                            label: 'Buscar libremente dentro de las compras',
                            placeholder: 'Producto, código, forma de pago, plazo, información adicional, reembolso...',
                            columns: [
                                { key: 'origen',           label: 'Tipo' },
                                { key: 'tipo',             label: 'Código / Forma / Nº' },
                                { key: 'descripcion',      label: 'Descripción' },
                                { key: 'cantidad',         label: 'Cant.', align: 'end' },
                                { key: 'monto',            label: 'Valor', align: 'end' },
                                { key: 'numero',           label: 'Compra', class: 'font-monospace fw-semibold' },
                                { key: 'fecha',            label: 'Fecha' },
                                { key: 'proveedor_nombre', label: 'Proveedor' },
                                { key: 'estado',           label: 'Estado' },
                            ],
                            onSelect: (row, fm) => fm.aplicarFiltro({ key: 'numero', value: row.numero }),
                            onOpen: (row, fm) => {
                                fm.hide();
                                // abrirModalCompra lee la compra del data-row de la fila.
                                setTimeout(() => window.abrirModalCompra({ dataset: { row: JSON.stringify(row) } }), 350);
                            },
                        },
                        fields: <?= json_encode($filtrosCompras, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                        loadingTarget: '#tbodyCompras',   // se atenúa mientras se busca
                        onApply: () => window.CMG_fetchSearch && window.CMG_fetchSearch(1),
                    });
                    window.CMG_filtros.init();
                });
            </script>
            <?php /* form de compatibilidad para no romper marcado existente */ ?>
            <form id="frmBuscarCompras" class="d-none" onsubmit="return false;">
            </form>
            <?php // FiltrosModal (extraId) mueve estos botones dentro del grupo del buscador; si el JS no corre, quedan aquí. ?>
            <div id="fmExtraCompras" class="btn-group btn-group-sm">
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
                    'saldo_documento'  => 'Saldo',
                    'estado_pago'      => 'Pago',
                    'estado'           => 'Estado',
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>
                <a id="btnExportPdf" href="<?= $urlBase ?>/export-pdf?b=<?= urlencode($buscar) ?>&orden=<?= urlencode($ordenParam ?? '') ?>"
                    class="btn btn-outline-danger" title="Descargar PDF"><i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span></a>
                <a id="btnExportExcel" href="<?= $urlBase ?>/export-excel?b=<?= urlencode($buscar) ?>&orden=<?= urlencode($ordenParam ?? '') ?>"
                    class="btn btn-outline-success" title="Descargar Excel"><i class="bi bi-file-earmark-spreadsheet"></i><span class="d-none d-md-inline"> Excel</span></a>
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
                        <th class="text-end fw-bold" data-col="saldo_documento" title="Saldo = Total − Retención − Notas de crédito − Pagos">Saldo</th>
                        <?php // Pago no es ordenable (se calcula por fila, no hay columna que ordenar):
                              // va sin sortable-header ni role="button" para que tampoco lo parezca. ?>
                        <th class="text-center" data-col="estado_pago">Pago</th>
                        <th class="text-center pe-3 sortable-header" role="button" data-sort="estado" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyCompras">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="12" class="text-center py-5 text-muted"><i class="bi bi-cart3 fs-3 d-block mb-2"></i>No se encontraron compras.</td>
                        </tr>
                        <?php else: foreach ($rows as $r): 
                            $importeTotal = (float)($r['importe_total'] ?? 0);
                            $pagado       = (float)($r['total_pagado'] ?? 0);
                            $nc           = (float)($r['total_nc'] ?? 0);
                            $retencion    = (float)($r['total_retencion'] ?? 0);
                            $saldo        = max(0, $importeTotal - $pagado - $nc - $retencion);

                            if (($r['tipo_comprobante'] ?? '') === '04') {
                                // Las notas de crédito de compra son un crédito a favor: no se pagan → Pagada.
                                $estadoPagoBadge = '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Pagada</span>';
                            } elseif ($saldo <= 0.01) {
                                $estadoPagoBadge = '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Pagada</span>';
                            } elseif (($pagado + $nc + $retencion) > 0) {
                                $estadoPagoBadge = '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">Abonada</span>';
                            } else {
                                $estadoPagoBadge = '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Pendiente</span>';
                            }
                            
                            $estadoBadge = \App\controllers\modulos\ComprasController::badgeEstado($r['estado'] ?? null);
                        ?>
                            <tr class="compra-row" role="button" tabindex="0"
                                data-row='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>'
                                onclick="abrirModalCompra(this)">
                                <td class="ps-3" data-col="secuencial_prov"><code class="text-secondary"><?= htmlspecialchars(($r['establecimiento_prov'] ?? '') . '-' . ($r['punto_emision_prov'] ?? '') . '-' . ($r['secuencial_prov'] ?? '')) ?></code></td>
                                <td data-col="fecha_emision"><?= !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '-' ?></td>
                                <td class="fw-medium text-truncate" style="max-width:220px" data-col="proveedor_nombre"><?= htmlspecialchars($r['proveedor_nombre'] ?? '-') ?></td>
                                <td data-col="proveedor_ruc"><small class="text-muted"><?= htmlspecialchars($r['proveedor_ruc'] ?? '-') ?></small></td>
                                <td data-col="tipo_comprobante"><small><?= htmlspecialchars(trim((string)($r['tipo_comprobante_nombre'] ?? $r['tipo_comprobante'] ?? '-'))) ?></small></td>
                                <td data-col="sustento_nombre" class="text-truncate" style="max-width:160px"><small class="text-muted"><?= htmlspecialchars($r['sustento_nombre'] ?? '-') ?></small></td>
                                <td class="text-end" data-col="total_sin_impuestos"><?= number_format((float)($r['total_sin_impuestos'] ?? 0), 2) ?></td>
                                <td class="text-end" data-col="monto_iva">$<?= number_format((float)($r['monto_iva'] ?? 0), 2) ?></td>
                                <td class="text-end fw-bold" data-col="importe_total">$<?= number_format((float)($r['importe_total'] ?? 0), 2) ?></td>
                                <td class="text-end fw-bold" data-col="saldo_documento">
                                    <?php if (($r['tipo_comprobante'] ?? '') === '04'): ?>
                                        <span class="text-muted" title="Las notas de crédito no tienen saldo por pagar">N/A</span>
                                    <?php else: ?>
                                        <span class="<?= $saldo > 0.01 ? 'text-danger' : 'text-success' ?>">$<?= number_format($saldo, 2) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center" data-col="estado_pago"><?= $estadoPagoBadge ?></td>
                                <td class="text-center pe-3" data-col="estado"><?= $estadoBadge ?></td>
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
    // Orden múltiple (Shift+clic): lista completa de criterios, en el formato que lee
    // OrdenListado en PHP. CMG_currentSort/CMG_currentDir quedan como el principal.
    window.CMG_currentSorts = <?= $ordenJson ?? '[]' ?>;
    window.CMG_currentPage = <?= $page ?>;
    window.CMG_perm = {
        crear: <?= $perm['crear'] ? 'true' : 'false' ?>,
        actualizar: <?= $perm['actualizar'] ? 'true' : 'false' ?>,
        eliminar: <?= $perm['eliminar'] ? 'true' : 'false' ?>
    };
    // Aprobación de compras (checkpoint 'aprobacion_compras'): el modal decide
    // con esto si muestra los botones de Aprobar/Rechazar.
    window.COMPRAS_ES_APROBADOR = <?= !empty($esAprobador) ? 'true' : 'false' ?>;
    window.COMPRAS_APROBADORES  = <?= json_encode($aprobadoresNombres ?? [], JSON_UNESCAPED_UNICODE) ?>;
    window.COMPRAS_ID_USUARIO   = <?= (int) ($_SESSION['id_usuario'] ?? 0) ?>;
    window.COMPRAS_NIVEL        = <?= (int) ($nivelUsuario ?? 1) ?>;

    /** Atajo del badge del título: deja el listado solo con las pendientes. */
    window.CMG_filtrarPendientesAprobacion = function () {
        // Con el buscador estándar el filtro se aplica por el componente: así aparece
        // como chip en la caja (y se puede quitar) y conserva el texto ya escrito.
        if (window.CMG_filtros) {
            window.CMG_filtros.aplicarFiltro({ key: 'estado', op: '=', value: 'pendiente_aprobacion' }, false);
            return;
        }
        const input = document.getElementById('inputBuscarCompras');
        if (!input) return;
        input.value = 'estado:pendiente_aprobacion';
        if (window.CMG_fetchSearch) window.CMG_fetchSearch(1);
    };
    window.CMG_formasPago = <?= json_encode(array_values($formasPago ?? [])) ?>;
    window.CMG_tarifasIva = <?= json_encode(array_values($tarifasIva ?? [])) ?>;
    window.CMG_sustentos = <?= json_encode(array_values($sustentos ?? [])) ?>;
    window.CMG_puntos = <?= json_encode(array_values($puntos ?? [])) ?>;
    window.CMG_unidadesMedida = <?= json_encode(array_values($unidadesMedida ?? [])) ?>;
    window.CMG_bodegas = <?= json_encode(array_values($bodegas ?? [])) ?>;
    window.CMG_empresa = <?= json_encode($empresa ?? []) ?>;
    // Catálogo completo de comprobantes (código => nombre). El selector del modal
    // solo ofrece los tipos capturables a mano; este mapa permite reconstruir la
    // etiqueta de una compra ya registrada con otro código (p. ej. factura 01
    // cargada desde el XML del SRI) para que no pierda su tipo al abrirla.
    window.CMG_tiposComprobanteTodos = <?= json_encode(array_column($tiposComprobanteTodos ?? [], 'comprobante', 'codigo_comprobante')) ?>;
    window.CMG_sucursal = <?= json_encode($sucursal_principal ?? []) ?>;
</script>

<?php include __DIR__ . '/modal_compra.php'; ?>
<?php include MVC_APP . '/views/modulos/retenciones_compras/modal_retencion.php'; ?>
<?php include MVC_APP . '/views/modulos/proveedores/modal_proveedor.php'; ?>
<?php include MVC_APP . '/views/modulos/productos/modal.php'; ?>
<script src="<?= $base ?>/js/modulos/proveedores_modal.js?v=<?= asset_ver('/js/modulos/proveedores_modal.js') ?>"></script>
<script src="<?= $base ?>/js/modulos/productos_modal.js?v=<?= asset_ver('/js/modulos/productos_modal.js') ?>"></script>
<script src="<?= $base ?>/js/modulos/categorias_modal.js?v=<?= asset_ver('/js/modulos/categorias_modal.js') ?>"></script>
<script src="<?= $base ?>/js/modulos/marcas_modal.js?v=<?= asset_ver('/js/modulos/marcas_modal.js') ?>"></script>
<script src="<?= $base ?>/js/modulos/unidades_medida_modal.js?v=<?= asset_ver('/js/modulos/unidades_medida_modal.js') ?>"></script>
<script src="<?= $base ?>/js/modulos/asiento_contable_tab.js?v=<?= asset_ver('/js/modulos/asiento_contable_tab.js') ?>"></script>
<script src="<?= $base ?>/js/modulos/compras.js?v=<?= asset_ver('/js/modulos/compras.js') ?>"></script>
<script src="<?= $base ?>/js/modulos/retenciones_compras.js?v=<?= asset_ver('/js/modulos/retenciones_compras.js') ?>"></script>

<script>
    (function() {
        'use strict';
        const input = document.getElementById('inputBuscarCompras');

        window.CMG_cambiarPagina = (n) => CMG_fetchSearch(n);

        window.CMG_fetchSearch = async (page = 1) => {
            const term = input ? input.value.trim() : '';
            const orden = window.CMG_ordenParam(window.CMG_currentSorts || []);
            const uri = `${window.CMG_urlBase}/searchAjax?b=${encodeURIComponent(term)}&page=${page}&orden=${encodeURIComponent(orden)}`;
            // Mismo indicador que el buscador (FiltrosModal): la tabla se atenúa mientras
            // carga, también al paginar y ordenar, que llaman a esta función directo.
            const tbody = document.getElementById('tbodyCompras');
            if (tbody) tbody.classList.add('fm-cargando-target');
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
                    // Los íconos (incluida la prioridad 1/2/3 del orden múltiple) los
                    // repinta el motor global; aquí solo se le pide que se refresque.
                    if (sorter) sorter.refreshIcons();
                }
            } catch (e) {
                console.error('Error búsqueda compras:', e);
            } finally {
                if (tbody) tbody.classList.remove('fm-cargando-target');
            }
        };

        // Ordenamiento: motor global (window.CMG_initSort, en public/js/favoritos.js).
        // Antes esta vista tenía su propio binding y su propio repintado de íconos; se
        // centralizó para heredar el orden múltiple sin duplicar la lógica. De paso, el
        // motor solo engancha los encabezados con `data-sort`: el anterior enganchaba
        // todos los `.sortable-header`, incluida la columna Pago —que no es ordenable y
        // no tiene ícono—, con lo que un clic ahí ordenaba por `undefined` y el
        // repintado reventaba al buscarle el `<i>`.
        // multi: clic normal ordena por una columna; Shift+clic encadena hasta 3
        // (ASC → DESC → fuera del orden), con la prioridad numerada en cada encabezado.
        // reload:false porque CMG_fetchSearch repinta todo lo que depende del orden.
        const sorter = window.CMG_initSort('compras', (col, dir, sorts) => {
            window.CMG_currentSort  = col;
            window.CMG_currentDir   = dir;
            window.CMG_currentSorts = sorts;
            CMG_fetchSearch(1);
        }, { sorts: window.CMG_currentSorts, multi: true, container: '.compras-scroll', reload: false });

        let timerId;
        if (input) {
            input.addEventListener('input', () => {
                clearTimeout(timerId);
                timerId = setTimeout(() => CMG_fetchSearch(1), 380);
            });
        }
    })();
</script>