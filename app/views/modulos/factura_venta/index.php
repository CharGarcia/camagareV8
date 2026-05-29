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
/** @var array $empresa */
/** @var array $formasPago */
/** @var array $tarifasIva */
/** @var array $unidades */
/** @var array $vistaConfig */

$base = BASE_URL;
$urlBase = $base . '/' . $rutaModulo;

$rows       = $rows ?? [];
$total      = $total ?? 0;
$page       = $page ?? 1;
$totalPages = $totalPages ?? 1;
$perPage    = $perPage ?? 20;
$ordenCol   = $ordenCol ?? 'fecha_emision';
$ordenDir   = $ordenDir ?? 'DESC';
$buscar     = $buscar ?? '';
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>

<style>
    .x-small { font-size: 0.75rem; }
    /* Estilos para el listado */
    .fv-scroll {
        max-height: calc(100vh - 240px);
        overflow-y: auto;
    }

    .fv-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }

    .factura-row {
        cursor: pointer;
    }

    .factura-row:hover {
        background-color: rgba(0, 0, 0, .04);
    }

    /* Fix para modales anidados (Cliente/Producto sobre Factura) */
    .modal-submodal {
        z-index: 1080 !important;
    }

    .modal-submodal-backdrop {
        z-index: 1075 !important;
    }

    /* Estilos para el Formulario en Modal */
    .modal-factura .modal-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
    }

    .table-detalle th {
        font-size: 0.75rem;
        text-transform: uppercase;
        background-color: #f8f9fa;
    }

    .input-detalle {
        border: none;
        background: transparent;
        font-size: 0.9rem;
        padding: 4px 8px;
    }

    .input-detalle:focus {
        background: #fff;
        box-shadow: inset 0 0 0 1px #0d6efd;
        outline: none;
    }

    .row-detalle:hover {
        background-color: rgba(13, 110, 253, 0.03);
    }

    .remove-row {
        color: #dc3545;
        opacity: 0;
        transition: opacity 0.2s;
    }

    .row-detalle:hover .remove-row {
        opacity: 1;
    }

    .total-label {
        font-size: 0.8rem;
        color: #6c757d;
        font-weight: 600;
    }

    .total-value {
        font-size: 1.1rem;
        font-weight: 700;
        color: #212529;
    }

    .dropdown-productos,
    .dropdown-predictivo {
        z-index: 2000 !important;
    }

    /* Eliminado el diseño "Slim" agresivo para estandarizar con Proveedores y el resto del sistema */
    /* .modal-factura .form-control-sm, ... lo que sigue era demasiado pequeño */

    .modal-factura .modal-header {
        padding: 0.75rem 1rem;
    }

    .modal-factura .modal-body {
        padding: 0 !important;
    }

    /* Estilos base para mantener la estructura pero con el tamaño estándar de Proveedores */
    .modal-factura .nav-tabs .nav-link {
        font-size: 0.875rem;
    }

    .modal-factura label {
        font-size: 0.85rem;
        font-weight: 600;
        color: #495057;
        margin-bottom: 3px !important;
    }

    /* Comentado para estandarizar con otros módulos */
    /* .modal-factura .nav-tabs-sm .nav-link {
        padding: 4px 10px;
        font-size: 0.75rem;
    } */
    .modal-factura .table-detalle th {
        font-size: 0.7rem !important;
        padding: 4px 8px !important;
    }

    .modal-factura .table-detalle td {
        padding: 0 !important;
        vertical-align: middle;
    }

    .modal-factura .input-detalle {
        height: 30px !important;
        font-size: 0.82rem !important;
        padding: 2px 8px !important;
    }

    .modal-factura .card-header {
        padding: 0.4rem 1rem !important;
    }

    .modal-factura .bg-light.p-3,
    .modal-factura .p-3 {
        padding: 0.75rem !important;
    }

    .modal-factura .p-2 {
        padding: 0.5rem !important;
    }

    .modal-factura hr {
        margin: 0.5rem 0;
    }

    .modal-factura .gap-2 {
        gap: 0.4rem !important;
    }

    .modal-factura .gap-3 {
        gap: 0.6rem !important;
    }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>

<div class="fv-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-receipt me-2 text-primary"></i><?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="abrirModalFactura()">
            <i class="bi bi-plus-lg"></i> Nueva
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <!-- Buscador con filtros (componente reusable) -->
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_busqueda.css?v=<?= time() ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_busqueda.js?v=<?= time() ?>"></script>
            <div id="fbBuscadorFV" style="width: 480px;"></div>
            <input type="hidden" id="buscarFactura" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosBusqueda) return;
                    new FiltrosBusqueda({
                        containerId:   'fbBuscadorFV',
                        hiddenInputId: 'buscarFactura',
                        placeholder:   'Buscar facturas...',
                        fields: [
                            { key: 'cliente',   label: 'Cliente',       icon: 'bi-person',          type: 'text' },
                            { key: 'ruc',       label: 'RUC / Cédula',  icon: 'bi-card-text',       type: 'text' },
                            { key: 'numero',    label: 'Nº Factura',    icon: 'bi-hash',            type: 'text' },
                            { key: 'vendedor',  label: 'Vendedor',      icon: 'bi-person-badge',    type: 'text' },
                            { key: 'usuario',   label: 'Usuario',       icon: 'bi-person-circle',   type: 'text' },
                            { key: 'fecha',     label: 'Fecha emisión', icon: 'bi-calendar-event',  type: 'date_range' },
                            { key: 'monto',     label: 'Total',         icon: 'bi-currency-dollar', type: 'number_range' },
                            { key: 'subtotal',  label: 'Subtotal',      icon: 'bi-receipt',         type: 'number_range' },
                            { key: 'estado',    label: 'Estado',        icon: 'bi-flag',            type: 'select', options: [
                                { v: 'borrador',   l: 'Borrador' },
                                { v: 'autorizado', l: 'Autorizado' },
                                { v: 'anulado',    l: 'Anulado' },
                            ]},
                        ],
                        quickFilters: [
                            { id: 'qf_borrador',   label: 'Borrador',    mk: () => ({ key: 'estado', op: '=', value: 'borrador',   display: 'Borrador' }) },
                            { id: 'qf_autorizado', label: 'Autorizadas', mk: () => ({ key: 'estado', op: '=', value: 'autorizado', display: 'Autorizado' }) },
                            { id: 'qf_anulado',    label: 'Anuladas',    mk: () => ({ key: 'estado', op: '=', value: 'anulado',    display: 'Anulado' }) },
                            { id: 'qf_hoy',        label: 'Hoy',         mk: () => FiltrosBusqueda.helpers.hoyMismo('fecha') },
                            { id: 'qf_mes',        label: 'Este mes',    mk: () => FiltrosBusqueda.helpers.esteMes('fecha') },
                            { id: 'qf_mes_pasado', label: 'Mes pasado',  mk: () => FiltrosBusqueda.helpers.mesPasado('fecha') },
                            { id: 'qf_anio',       label: 'Este año',    mk: () => FiltrosBusqueda.helpers.esteAnio('fecha') },
                        ],
                        onApply: () => window.FV_fetchSearch && window.FV_fetchSearch(1),
                    }).init();
                });
            </script>

            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'numero'              => 'Nº Factura',
                    'fecha_emision'       => 'Fecha',
                    'cliente_nombre'      => 'Cliente',
                    'cliente_ruc'         => 'Identificación',
                    'total_sin_impuestos' => 'Subtotal',
                    'total_descuento'     => 'Descuento',
                    'iva'                 => 'IVA',
                    'total_ice'           => 'ICE',
                    'propina'             => 'Propina',
                    'importe_total'       => 'Total',
                    'vendedor_nombre'     => 'Vendedor',
                    'observaciones'       => 'Observaciones',
                    'usuario_nombre'      => 'Usuario',
                    'estado_correo'       => 'Estado correo',
                    'estado'              => 'Estado',
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>

                <a id="btnExportPdf" href="<?= $urlBase ?>/export-pdf?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                    class="btn btn-outline-danger" title="Descargar PDF">
                    <i class="bi bi-file-earmark-pdf"></i> PDF
                </a>
                <a id="btnExportExcel" href="<?= $urlBase ?>/export-excel?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                    class="btn btn-outline-success" title="Descargar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                </a>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="paginationContainer" class="btn-group btn-group-sm">
                <?php if ($page <= 1): ?>
                    <button type="button" class="btn btn-outline-secondary" disabled><i class="bi bi-chevron-left"></i></button>
                <?php else: ?>
                    <button type="button" class="btn btn-outline-secondary" onclick="window.FV_cambiarPaginaAjax(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <?php endif; ?>
                <?php if ($page >= $totalPages): ?>
                    <button type="button" class="btn btn-outline-secondary" disabled><i class="bi bi-chevron-right"></i></button>
                <?php else: ?>
                    <button type="button" class="btn btn-outline-secondary" onclick="window.FV_cambiarPaginaAjax(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="fv-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="secuencial" data-col="numero" onclick="window.FV_ordenar(this.dataset.sort)">
                            Nº Factura <i class="bi <?= $ordenCol === 'secuencial' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="sortable-header" role="button" data-sort="fecha_emision" data-col="fecha_emision" onclick="window.FV_ordenar(this.dataset.sort)">
                            Fecha <i class="bi <?= $ordenCol === 'fecha_emision' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="sortable-header" role="button" data-sort="cliente_nombre" data-col="cliente_nombre" onclick="window.FV_ordenar(this.dataset.sort)">
                            Cliente <i class="bi <?= $ordenCol === 'cliente_nombre' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="sortable-header" role="button" data-sort="cliente_ruc" data-col="cliente_ruc" onclick="window.FV_ordenar(this.dataset.sort)">
                            Identificación <i class="bi <?= $ordenCol === 'cliente_ruc' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="sortable-header text-end" role="button" data-sort="total_sin_impuestos" data-col="total_sin_impuestos" onclick="window.FV_ordenar(this.dataset.sort)">
                            Subtotal <i class="bi <?= $ordenCol === 'total_sin_impuestos' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="sortable-header text-end" role="button" data-sort="total_descuento" data-col="total_descuento" onclick="window.FV_ordenar(this.dataset.sort)">
                            Descuento <i class="bi <?= $ordenCol === 'total_descuento' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="sortable-header text-end" role="button" data-sort="iva" data-col="iva" onclick="window.FV_ordenar(this.dataset.sort)">
                            IVA <i class="bi <?= $ordenCol === 'iva' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="sortable-header text-end" role="button" data-sort="total_ice" data-col="total_ice" onclick="window.FV_ordenar(this.dataset.sort)">
                            ICE <i class="bi <?= $ordenCol === 'total_ice' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="sortable-header text-end" role="button" data-sort="propina" data-col="propina" onclick="window.FV_ordenar(this.dataset.sort)">
                            Propina <i class="bi <?= $ordenCol === 'propina' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="sortable-header text-end" role="button" data-sort="importe_total" data-col="importe_total" onclick="window.FV_ordenar(this.dataset.sort)">
                            Total <i class="bi <?= $ordenCol === 'importe_total' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="sortable-header" role="button" data-sort="vendedor_nombre" data-col="vendedor_nombre" onclick="window.FV_ordenar(this.dataset.sort)">
                            Vendedor <i class="bi <?= $ordenCol === 'vendedor_nombre' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="sortable-header" role="button" data-sort="observaciones" data-col="observaciones" onclick="window.FV_ordenar(this.dataset.sort)">
                            Observaciones <i class="bi <?= $ordenCol === 'observaciones' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="sortable-header" role="button" data-sort="usuario_nombre" data-col="usuario_nombre" onclick="window.FV_ordenar(this.dataset.sort)">
                            Usuario <i class="bi <?= $ordenCol === 'usuario_nombre' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="text-center sortable-header" role="button" data-sort="estado_correo" data-col="estado_correo" onclick="window.FV_ordenar(this.dataset.sort)">
                            Correo <i class="bi <?= $ordenCol === 'estado_correo' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="text-center pe-3 sortable-header" role="button" data-sort="estado" data-col="estado" onclick="window.FV_ordenar(this.dataset.sort)">
                            Estado <i class="bi <?= $ordenCol === 'estado' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                    </tr>
                </thead>
                <tbody id="tbodyFacturas">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="15" class="text-center py-5 text-muted"><i class="bi bi-receipt fs-3 d-block mb-2"></i>No se encontraron facturas.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $estado = $r['estado'] ?? 'borrador';
                            $estadoClass = match ($estado) {
                                'aprobado', 'autorizado' => 'bg-success bg-opacity-10 text-success border-success',
                                'anulado'                => 'bg-danger bg-opacity-10 text-danger border-danger',
                                'borrador'               => 'bg-secondary bg-opacity-10 text-secondary border-secondary',
                                default                  => 'bg-primary bg-opacity-10 text-primary border-primary',
                            };
                            $estadoCorreo = $r['estado_correo'] ?? 'pendiente';
                            $correoClass  = $estadoCorreo === 'enviado'
                                ? 'bg-success bg-opacity-10 text-success border-success'
                                : 'bg-warning bg-opacity-10 text-warning border-warning';
                            $ivaCalc = max(0, (float)($r['importe_total'] ?? 0) - (float)($r['total_sin_impuestos'] ?? 0) + (float)($r['total_descuento'] ?? 0) - (float)($r['total_ice'] ?? 0) - (float)($r['propina'] ?? 0));
                            ?>
                            <tr class="factura-row" role="button" tabindex="0" data-row='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>' onclick="abrirModalFacturaVer(this)">
                                <td class="ps-3" data-col="numero"><code class="text-secondary"><?= htmlspecialchars(($r['establecimiento'] ?? '') . '-' . ($r['punto_emision'] ?? '') . '-' . ($r['secuencial'] ?? '')) ?></code></td>
                                <td data-col="fecha_emision"><?= !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '-' ?></td>
                                <td class="fw-medium text-truncate" data-col="cliente_nombre" style="max-width:200px"><?= htmlspecialchars($r['cliente_nombre'] ?? '-') ?></td>
                                <td data-col="cliente_ruc"><small class="text-muted"><?= htmlspecialchars($r['cliente_ruc'] ?? '-') ?></small></td>
                                <td class="text-end" data-col="total_sin_impuestos">$<?= number_format((float)($r['total_sin_impuestos'] ?? 0), 2) ?></td>
                                <td class="text-end text-danger" data-col="total_descuento">$<?= number_format((float)($r['total_descuento'] ?? 0), 2) ?></td>
                                <td class="text-end" data-col="iva">$<?= number_format($ivaCalc, 2) ?></td>
                                <td class="text-end" data-col="total_ice">$<?= number_format((float)($r['total_ice'] ?? 0), 2) ?></td>
                                <td class="text-end" data-col="propina">$<?= number_format((float)($r['propina'] ?? 0), 2) ?></td>
                                <td class="text-end fw-bold" data-col="importe_total">$<?= number_format((float)($r['importe_total'] ?? 0), 2) ?></td>
                                <td data-col="vendedor_nombre"><?= htmlspecialchars($r['vendedor_nombre'] ?? '-') ?></td>
                                <td data-col="observaciones" class="text-truncate" style="max-width:180px"><?= htmlspecialchars($r['observaciones'] ?? '') ?></td>
                                <td data-col="usuario_nombre"><?= htmlspecialchars($r['usuario_nombre'] ?? '-') ?></td>
                                <td class="text-center" data-col="estado_correo">
                                    <span class="badge <?= $correoClass ?> border border-opacity-25"><?= ucfirst($estadoCorreo) ?></span>
                                </td>
                                <td class="text-center pe-3" data-col="estado">
                                    <span class="badge <?= $estadoClass ?> border border-opacity-25"><?= ucfirst($estado) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal para nueva factura (XL) -->
<div class="modal fade modal-factura" id="modalNuevaFactura" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-receipt-cutoff me-2"></i>Nueva factura de venta</h5>
                <span id="fv-badge-estado-modal" class="badge d-none ms-2" style="font-size:0.72rem;vertical-align:middle;"></span>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-0">
                <form id="formFacturaModal">
                    <!-- Barra de Acciones Superior -->
                    <div class="px-3 py-2 bg-light border-bottom d-flex gap-1 align-items-center flex-wrap">
                        <button id="m-btn-sri" type="button" class="btn btn-outline-primary btn-sm" onclick="enviarAlSri()"><i class="bi bi-cloud-arrow-up me-1"></i>Enviar al SRI</button>
                        <button id="m-btn-duplicar" type="button" class="btn btn-outline-secondary btn-sm" onclick="duplicarFactura()"><i class="bi bi-copy me-1"></i>Duplicar</button>
                        <div class="vr mx-1"></div>
                        <button id="m-btn-pdf" type="button" class="btn btn-outline-danger btn-sm px-2" onclick="exportarPdf()" title="Exportar PDF"><i class="bi bi-file-earmark-pdf"></i></button>
                        <button id="m-btn-xml" type="button" class="btn btn-outline-success btn-sm px-2" onclick="exportarXml()" title="Exportar XML"><i class="bi bi-file-earmark-code"></i></button>
                        <button id="m-btn-correo" type="button" class="btn btn-outline-info btn-sm px-2" onclick="enviarPorCorreo()" title="Enviar por correo"><i class="bi bi-envelope"></i></button>
                        <button id="m-btn-whatsapp" type="button" class="btn btn-outline-success btn-sm px-2" onclick="FV_abrirModalWhatsapp()" title="Enviar por WhatsApp"><i class="bi bi-whatsapp"></i></button>
                        <button id="m-btn-ticket" type="button" class="btn btn-outline-secondary btn-sm px-2" onclick="imprimirTicket()" title="Imprimir ticket / tirilla"><i class="bi bi-receipt"></i></button>
                        <button id="btnAnularFacturaModal" type="button" class="btn btn-outline-warning btn-sm d-none" title="Anular Factura"><i class="bi bi-slash-circle me-1"></i>Anular</button>
                        <button id="m-btn-pagar-tarjeta" type="button" class="btn btn-success btn-sm px-2 d-none" onclick="fvAbrirPagoTarjeta()" title="Pagar con tarjeta"><i class="bi bi-credit-card"></i></button>
                        <div class="vr mx-1"></div>
                        <button type="button" class="btn btn-outline-primary btn-sm px-2" onclick="abrirModalClienteCrear()" title="Registrar nuevo cliente"><i class="bi bi-person-plus fs-6"></i></button>
                        <button type="button" class="btn btn-outline-primary btn-sm px-2" onclick="abrirModalProductoCrear()" title="Registrar nuevo producto"><i class="bi bi-box-seam fs-6"></i></button>
                    </div>

                    <!-- Pestañas -->
                    <div class="d-flex align-items-center bg-light px-3 pt-2">
                        <ul class="nav nav-tabs border-bottom-0 flex-grow-1 tab-pestaña" id="tabsFacturaVenta" role="tablist">
                            <li class="nav-item"><a class="nav-link active py-2 small" id="tab-fv-venta-btn" data-bs-toggle="tab" href="#m-tab-detalle" role="tab" style="white-space: nowrap;"><i class="bi bi-receipt me-1"></i> Factura de venta</a></li>
                            <li class="nav-item"><a class="nav-link py-2 small" id="tab-fv-asiento-btn" data-bs-toggle="tab" href="#m-tab-contable" role="tab" style="white-space: nowrap;"><i class="bi bi-calculator me-1"></i> Asiento contable</a></li>
                            <li class="nav-item"><a class="nav-link py-2 small" id="tab-fv-pagos-btn" data-bs-toggle="tab" href="#m-tab-pagos-historial" role="tab" style="white-space: nowrap;"><i class="bi bi-cash-coin me-1"></i> Pagos</a></li>
                            <li class="nav-item"><a class="nav-link py-2 small" id="tab-fv-retenciones-btn" data-bs-toggle="tab" href="#m-tab-retenciones" role="tab" style="white-space: nowrap;"><i class="bi bi-percent me-1"></i> Retenciones</a></li>
                            <li class="nav-item"><a class="nav-link py-2 small" id="tab-fv-notas-btn" data-bs-toggle="tab" href="#m-tab-notas" role="tab" style="white-space: nowrap;"><i class="bi bi-file-earmark-minus me-1"></i> Notas de crédito</a></li>
                            <li class="nav-item"><a class="nav-link py-2 small" id="tab-fv-guias-btn" data-bs-toggle="tab" href="#m-tab-guias" role="tab" style="white-space: nowrap;"><i class="bi bi-truck me-1"></i> Guías de remisión</a></li>
                            <li class="nav-item"><a class="nav-link py-2 small" id="tab-fv-sri-btn" data-bs-toggle="tab" href="#m-tab-respuestas-sri" role="tab" style="white-space: nowrap;"><i class="bi bi-cloud-check me-1"></i> SRI</a></li>
                        </ul>
                        <div class="ms-auto pb-1">
                            <?php
                            $pestanasConfig = [
                                'tab-fv-asiento-btn' => 'Asiento contable',
                                'tab-fv-pagos-btn' => 'Pagos',
                                'tab-fv-retenciones-btn' => 'Retenciones',
                                'tab-fv-notas-btn' => 'Notas de crédito',
                                'tab-fv-guias-btn' => 'Guías de remisión',
                                'tab-fv-sri-btn' => 'SRI'
                            ];
                            echo \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasConfig, $vistaConfig ?? [], 'modulos/factura-venta');
                            ?>
                        </div>
                    </div>
                    <div class="border-bottom bg-light mb-0"></div>

                    <div class="tab-content border-top">
                        <div class="tab-pane fade show active" id="m-tab-detalle" role="tabpanel">
                            <!-- Cabecera de Facturación Optimizada -->
                            <div class="p-3 bg-white border-bottom">
                                <div class="row g-2">
                                    <div class="col-12">
                                        <div class="row g-2 align-items-end">
                                            <!-- 1. Fecha -->
                                            <div class="col-md-2">
                                                <label class="x-small fw-bold text-muted mb-1">Fecha</label>
                                                <input type="date" class="form-control form-control-sm border-primary border-opacity-10 py-0" style="height: 31px;" name="fecha_emision" value="<?= date('Y-m-d') ?>">
                                            </div>
                                            <!-- 2. Serie Unificada -->
                                            <div class="col-md-2">
                                                <label class="x-small fw-bold text-muted mb-1">
                                                    Serie <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito($rutaModulo, 'm-select-puntos', 'id_punto_emision') ?>
                                                </label>
                                                <select class="form-select form-select-sm border-primary border-opacity-25" name="id_punto_emision" id="m-select-puntos" onchange="syncSerie(this.value)" style="height: 31px;">
                                                    <?php foreach ($puntos as $p): ?>
                                                        <option value="<?= $p['id'] ?>"
                                                            data-est="<?= $p['id_establecimiento'] ?>"
                                                            data-cod-est="<?= htmlspecialchars($p['cod_establecimiento']) ?>"
                                                            data-cod-punto="<?= htmlspecialchars($p['codigo_punto']) ?>">
                                                            <?= $p['cod_establecimiento'] ?>-<?= $p['codigo_punto'] ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <input type="hidden" name="id_establecimiento" id="m-id-establecimiento">
                                            </div>
                                            <!-- 3. Secuencial -->
                                            <div class="col-md-2">
                                                <label class="x-small fw-bold text-muted mb-1">Secuencial</label>
                                                <input type="text" class="form-control form-control-sm border-primary border-opacity-25 text-center text-dark py-0 bg-light" style="height: 31px;" name="secuencial" id="m-input-secuencial" placeholder="000000001" maxlength="9" readonly>
                                            </div>
                                            <!-- 4. Bodega -->
                                            <?php if (($empresa['facturacion_inventario'] ?? 'true') === 'true' || ($empresa['facturacion_inventario'] ?? 'true') === true): ?>
                                                <div class="col-md-3">
                                                    <label class="x-small fw-bold text-muted mb-1">
                                                        Bodega <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito($rutaModulo, 'm-select-bodega', 'id_bodega') ?>
                                                    </label>
                                                    <select class="form-select form-select-sm border-primary border-opacity-10" name="id_bodega" id="m-select-bodega" style="height: 31px;">
                                                        <?php foreach ($bodegas as $b): ?>
                                                            <option value="<?= $b['id'] ?>" <?= !empty($b['es_default']) ? 'selected' : '' ?>><?= htmlspecialchars($b['nombre']) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            <?php endif; ?>
                                            <!-- 5. Vendedor -->
                                            <div class="col-md-<?= (($empresa['facturacion_inventario'] ?? 'true') === 'true' || ($empresa['facturacion_inventario'] ?? 'true') === true) ? '3' : '6' ?>">
                                                <label class="x-small fw-bold text-muted mb-1">
                                                    Vendedor <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito($rutaModulo, 'm-select-vendedor', 'id_vendedor') ?>
                                                </label>
                                                <select class="form-select form-select-sm border-primary border-opacity-10" name="id_vendedor" id="m-select-vendedor" style="height: 31px;" onchange="actualizarInfoVendedor(this)">
                                                    <option value="">Seleccione...</option>
                                                    <?php foreach ($vendedores as $v): ?>
                                                        <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['nombre']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Fila 2: Cliente con Diseño Compacto -->
                                    <div class="col-12 mt-2">
                                        <div class="p-2 border rounded-3 bg-light bg-opacity-10">
                                            <div class="row g-2 align-items-center">
                                                <div class="col-md-12 position-relative">
                                                    <div class="input-group input-group-sm flex-grow-1 elevation-1 rounded-pill overflow-hidden border">
                                                        <span class="input-group-text bg-white border-0 text-primary"><i class="bi bi-search"></i></span>
                                                        <input type="text" class="form-control border-0 px-1" id="m-search-cliente" placeholder="Buscar cliente por RUC o Razón Social..." autocomplete="off">
                                                        <input type="hidden" name="id_cliente" id="m-id-cliente">
                                                        <input type="hidden" id="m-tipo-id-cliente">
                                                        <input type="hidden" id="m-nombre-tipo-id-cliente">
                                                    </div>
                                                    <div id="m-dropdown-clientes" class="list-group shadow dropdown-predictivo position-absolute d-none" style="z-index: 1050; width: 100%; max-height: 250px; overflow-y: auto; right: 0px; top: 35px;"></div>
                                                </div>

                                                <div class="col-12 px-3 mt-1 d-none" id="m-info-cliente">
                                                    <div class="d-flex flex-wrap align-items-center gap-x-3 gap-y-1" style="font-size:0.72rem; text-transform:lowercase; color:#6c757d;">
                                                        <span class="border-end pe-2 me-1 fw-bold text-dark" id="m-lbl-cliente-ruc"></span>
                                                        <div class="d-flex align-items-center gap-1">
                                                            <i class="bi bi-geo-alt"></i><span id="m-lbl-cliente-direccion"></span>
                                                        </div>
                                                        <div class="d-flex align-items-center gap-1 border-start ps-2">
                                                            <i class="bi bi-envelope"></i><span id="m-lbl-cliente-correo"></span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Observaciones Internas (Movido desde pestaña información) -->
                                                <div class="col-12 mt-2">
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text bg-white border-primary border-opacity-10 text-muted" style="font-size: 0.7rem;"><i class="bi bi-sticky me-1"></i>Observaciones</span>
                                                        <textarea class="form-control border-primary border-opacity-10" name="observaciones" id="m-input-observaciones" rows="1" placeholder="Ej: Notas internas para el equipo..." style="font-size: 0.75rem; min-height: 31px;"></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Contenedor del Detalle de Productos -->
                            <div class="p-3">
                                <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                                    <div class="table-responsive" style="max-height: 350px;">
                                        <table class="table table-sm table-detalle mb-0 text-nowrap">
                                            <thead>
                                                <tr class="table-light border-bottom">
                                                    <th class="ps-3 py-2 small fw-bold text-muted" style="width: 20%;">Descripción</th>
                                                    <th class="py-2 small fw-bold text-muted" style="width: 10%;">Adicional</th>
                                                    <th class="py-2 small fw-bold text-muted col-medida-header <?= (($empresa['mostrar_unidad_medida'] ?? true) === 'true' || ($empresa['mostrar_unidad_medida'] ?? true) === true) ? '' : 'd-none' ?>" style="width: 8%;">Medida</th>
                                                    <th class="py-2 small fw-bold text-muted text-center" style="width: 6%;">Cant.</th>
                                                    <th class="py-2 small fw-bold text-muted" style="width: 12%;">Precios</th>
                                                    <th class="py-2 small fw-bold text-muted text-end" style="width: 8%;">P. Sin Imp.</th>
                                                    <th class="py-2 small fw-bold text-muted text-end" style="width: 8%;">P. Con Imp.</th>
                                                    <th class="py-2 small fw-bold text-muted text-end" style="width: 7%;">Desc.</th>
                                                    <th class="py-2 small fw-bold text-muted text-center" style="width: 7%;">Iva</th>
                                                    <?php if (!empty($empresa['obligatorio_lotes']) && ($empresa['obligatorio_lotes'] === 'true' || $empresa['obligatorio_lotes'] === true)): ?>
                                                        <th class="py-2 small fw-bold text-muted text-center" style="width:8%;">Lote</th>
                                                    <?php endif; ?>
                                                    <?php if (!empty($empresa['obligatorio_caducidad']) && ($empresa['obligatorio_caducidad'] === 'true' || $empresa['obligatorio_caducidad'] === true)): ?>
                                                        <th class="py-2 small fw-bold text-muted text-center" style="width:9%;">Caducidad</th>
                                                    <?php endif; ?>
                                                    <?php if (!empty($empresa['obligatorio_nup']) && ($empresa['obligatorio_nup'] === 'true' || $empresa['obligatorio_nup'] === true)): ?>
                                                        <th class="py-2 small fw-bold text-muted text-center" style="width:9%;">NUP / Serial</th>
                                                    <?php endif; ?>
                                                    <th class="py-2 small fw-bold text-muted text-end pe-4" style="width: 11%;">Subtotal</th>
                                                    <th style="width: 40px;"></th>
                                                </tr>
                                            </thead>
                                            <tbody id="m-tbodyDetalle"></tbody>
                                        </table>
                                    </div>
                                    <div class="p-2 border-top bg-light d-flex justify-content-between align-items-center">
                                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" onclick="agregarFila()">
                                            <i class="bi bi-plus-circle me-1"></i> Agregar línea
                                        </button>
                                        <div class="small fw-bold text-muted pe-3">
                                            Items: <span id="m-count-items">0</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Pie de Factura: Pestañas secundarias y Totales -->
                            <div class="p-3 border-top bg-light">
                                <div class="row g-3">
                                    <!-- Izquierda: Pestañas secundarias -->
                                    <div class="col-md-8">
                                        <ul class="nav nav-tabs nav-tabs-sm mb-2" id="m-subtabs-factura" role="tablist">
                                            <li class="nav-item">
                                                <button class="nav-link active py-1 small" data-bs-toggle="tab" data-bs-target="#m-subtab-info" type="button">Info. Adicional</button>
                                            </li>
                                            <li class="nav-item">
                                                <button class="nav-link py-1 small" data-bs-toggle="tab" data-bs-target="#m-subtab-pagos" type="button">Formas de pago SRI</button>
                                            </li>
                                            <li class="nav-item">
                                                <button class="nav-link py-1 small" data-bs-toggle="tab" data-bs-target="#m-subtab-sri" type="button">Crédito</button>
                                            </li>
                                        </ul>
                                        <div class="tab-content bg-white border p-2 rounded-bottom" style="min-height: 120px;">
                                            <!-- Info Adicional -->
                                            <div class="tab-pane fade show active" id="m-subtab-info" role="tabpanel">
                                                <div class="border rounded-2 overflow-hidden bg-white mt-1">
                                                    <div class="table-responsive" style="max-height: 200px;">
                                                        <table class="table table-sm mb-0">
                                                            <thead class="table-light">
                                                                <tr>
                                                                    <th class="ps-2 py-0 small fw-bold text-muted" style="width: 40%;">Concepto</th>
                                                                    <th class="py-0 small fw-bold text-muted" style="width: 50%;">Detalle</th>
                                                                    <th class="py-0" style="width: 10%;"></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody id="m-tbody-info-adicional"></tbody>
                                                        </table>
                                                    </div>
                                                    <div class="p-1 border-top bg-light">
                                                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold ms-2" onclick="agregarInfoAdicional()">
                                                            <i class="bi bi-plus-circle me-1"></i> Agregar línea
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Formas de Pago -->
                                            <div class="tab-pane fade" id="m-subtab-pagos" role="tabpanel">
                                                <div id="m-container-pagos">
                                                    <div class="row g-2 align-items-center mb-1 row-pago">
                                                        <div class="col-7">
                                                            <div class="d-flex align-items-center gap-1">
                                                                <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito($rutaModulo, 'm-select-pago-sri', 'id_forma_pago_sri') ?>
                                                                <select class="form-select form-select-sm border-0 bg-light" name="f_pago_id[]" id="m-select-pago-sri">
                                                                    <?php foreach ($formasPago as $fp): ?>
                                                                        <option value="<?= $fp['codigo'] ?>" data-id="<?= $fp['id'] ?>"><?= htmlspecialchars($fp['nombre']) ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-4">
                                                            <input type="number" class="form-control form-control-sm text-end border-0 bg-light fw-bold" name="f_pago_valor[]" step="0.01" value="0.00">
                                                        </div>
                                                        <div class="col-1 text-center">
                                                            <span></span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <button type="button" class="btn btn-link btn-xs p-0 text-decoration-none small mt-1" onclick="agregarFormaPago()"><i class="bi bi-plus-circle me-1"></i>Añadir pago</button>
                                            </div>
                                            <!-- Crédito SRI -->
                                            <div class="tab-pane fade" id="m-subtab-sri" role="tabpanel">
                                                <div class="p-2">
                                                    <div class="row g-2">
                                                        <div class="col-md-6">
                                                            <label class="x-small text-muted mb-1">Días de crédito</label>
                                                            <input type="number" class="form-control form-control-sm" name="sri_dias_credito" id="m-input-dias-credito" value="0">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="x-small text-muted mb-1">Plazo</label>
                                                            <select class="form-select form-select-sm" name="sri_plazo">
                                                                <option value="dias">Días</option>
                                                                <option value="meses">Meses</option>
                                                                <option value="anios">Años</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Derecha: Totales Verticales -->
                                    <div class="col-md-4">
                                        <div class="bg-white border rounded p-2 shadow-sm" style="font-size:0.75rem;">

                                            <!-- Subtotal General -->
                                            <div class="d-flex justify-content-between align-items-center mb-1 fw-bold border-bottom pb-1">
                                                <span class="text-muted">Subtotal</span>
                                                <span id="m-lbl-subtotal">0.00</span>
                                            </div>

                                            <!-- Subtotales agrupados por tarifa IVA -->
                                            <div id="m-lbl-subtotales-iva" class="mb-1"></div>

                                            <!-- Total Descuento -->
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="text-muted">(-) Descuento</span>
                                                <span class="fw-bold text-dark" id="m-lbl-descuento">0.00</span>
                                            </div>

                                            <!-- IVA agrupado por tarifa (solo los > 0) -->
                                            <div id="m-lbl-ivas-grupo" class="mb-1"></div>

                                            <!-- ICE (solo si hay) -->
                                            <div id="m-lbl-ice-row" class="d-flex justify-content-between align-items-center mb-1 d-none">
                                                <span class="text-muted">(+) ICE</span>
                                                <span class="fw-bold text-dark" id="m-lbl-ice">0.00</span>
                                            </div>

                                            <!-- Propina -->
                                            <div class="d-flex justify-content-between align-items-center mb-1 <?= (isset($empresa['mostrar_propina_factura']) && ($empresa['mostrar_propina_factura'] === 'true' || $empresa['mostrar_propina_factura'] === true)) ? '' : 'd-none' ?>">
                                                <span class="text-muted">(+) Propina</span>
                                                <div style="width:90px;">
                                                    <input type="number" id="m-input-propina" class="form-control form-control-sm text-end border-0 bg-light fw-bold p-1"
                                                        value="0.00" min="0" step="0.01" oninput="calcTotales()">
                                                </div>
                                            </div>

                                            <hr class="my-1 opacity-25">

                                            <!-- Total Factura -->
                                            <div class="d-flex justify-content-between align-items-center bg-light border py-1 px-2 rounded">
                                                <span class="fw-bold text-dark" style="font-size:0.8rem;">TOTAL</span>
                                                <span class="fw-bold text-dark" style="font-size:1rem;" id="m-lbl-total">0.00</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pestaña: Asiento Contable -->
                        <div class="tab-pane fade p-3" id="m-tab-contable" role="tabpanel">
                            <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                                <div class="table-responsive" style="max-height: 350px;">
                                    <table class="table table-sm table-detalle mb-0 text-nowrap" id="table-asiento-contable">
                                        <thead>
                                            <tr class="table-light border-bottom">
                                                <th class="ps-3 py-2 small fw-bold text-muted" style="width: 45%;">Cuenta Contable</th>
                                                <th class="py-2 small fw-bold text-muted text-end pe-3" style="width: 20%;">D&eacute;bito / Debe</th>
                                                <th class="py-2 small fw-bold text-muted text-end pe-3" style="width: 20%;">Cr&eacute;dito / Haber</th>
                                                <th class="py-2 small fw-bold text-muted" style="width: 15%;">Referencia</th>
                                                <th style="width: 40px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbody-asiento-contable">
                                            <tr>
                                                <td colspan="5" class="text-center py-4 text-muted">Cargando asiento contable...</td>
                                            </tr>
                                        </tbody>
                                        <tfoot class="bg-light fw-bold border-top sticky-bottom">
                                            <tr>
                                                <td class="text-end py-2">Totales:</td>
                                                <td class="text-end pe-3 py-2 text-primary" id="lbl-asiento-total-debe">0.00</td>
                                                <td class="text-end pe-3 py-2 text-primary" id="lbl-asiento-total-haber">0.00</td>
                                                <td colspan="2" class="py-2">
                                                    <div class="d-flex align-items-center gap-2 justify-content-end pe-3">
                                                        <span class="x-small text-muted">Diferencia: <span id="lbl-asiento-diferencia">0.00</span></span>
                                                        <span id="badge-asiento-cuadre" class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2">Cuadrado</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <div class="p-2 border-top bg-light d-flex justify-content-between align-items-center">
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" onclick="fvAgregarLineaAsiento()" id="btn-agregar-asiento-linea">
                                        <i class="bi bi-plus-circle me-1"></i> Agregar línea
                                    </button>
                                    <div class="small fw-bold text-muted pe-3">
                                        Líneas: <span id="fv-count-asiento-lineas">0</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pestaña: Pagos / Cobros -->
                        <div class="tab-pane fade" id="m-tab-pagos-historial" role="tabpanel">
                            <div class="p-3">
                                <!-- Resumen -->
                                <div class="row g-2 mb-3">
                                    <div class="col">
                                        <div class="border rounded-3 p-2 bg-white text-center shadow-sm border-secondary-subtle">
                                            <div class="text-muted mb-0 fw-semibold text-nowrap" style="font-size: 0.72rem;">Total Factura</div>
                                            <div class="fw-bold text-dark mb-0" style="font-size: 1.1rem;">$ <span id="fvPagoTotalFactura">0.00</span></div>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div class="border rounded-3 p-2 bg-success bg-opacity-10 border-success border-opacity-25 text-center shadow-sm">
                                            <div class="text-success mb-0 fw-semibold text-nowrap" style="font-size: 0.72rem;">Total Cobrado</div>
                                            <div class="fw-bold text-success mb-0" style="font-size: 1.1rem;">$ <span id="fvPagoTotalCobrado">0.00</span></div>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div class="border rounded-3 p-2 bg-warning bg-opacity-10 border-warning border-opacity-25 text-center shadow-sm">
                                            <div class="text-warning mb-0 fw-semibold text-nowrap" style="font-size: 0.72rem;">Retenciones</div>
                                            <div class="fw-bold text-warning mb-0" style="font-size: 1.1rem;">$ <span id="fvPagoTotalRetenciones">0.00</span></div>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div class="border rounded-3 p-2 bg-info bg-opacity-10 border-info border-opacity-25 text-center shadow-sm">
                                            <div class="text-info mb-0 fw-semibold text-nowrap" style="font-size: 0.72rem;">Notas de Crédito</div>
                                            <div class="fw-bold text-info mb-0" style="font-size: 1.1rem;">$ <span id="fvPagoTotalNC">0.00</span></div>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div class="border rounded-3 p-2 bg-danger bg-opacity-10 border-danger border-opacity-25 text-center shadow-sm">
                                            <div class="text-danger mb-0 fw-semibold text-nowrap" style="font-size: 0.72rem;">Saldo Pendiente</div>
                                            <div class="fw-bold text-danger mb-0" style="font-size: 1.1rem;">$ <span id="fvPagoSaldoPendiente">0.00</span></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <!-- Izquierda: Historial -->
                                    <div class="col-md-7">
                                        <div class="card border border-secondary-subtle shadow-sm rounded-3 overflow-hidden">
                                            <div class="card-header bg-light py-2 d-flex align-items-center border-bottom border-secondary-subtle">
                                                <h6 class="card-title mb-0 fw-bold text-secondary" style="font-size: 0.85rem;"><i class="bi bi-list-ul me-2"></i>Historial de Cobros (Ingresos)</h6>
                                            </div>
                                            <div class="card-body p-0">
                                                <div class="table-responsive" style="max-height: 320px; min-height: 150px;">
                                                    <table class="table table-hover align-middle mb-0">
                                                        <thead class="table-light text-muted sticky-top border-bottom" style="font-size: 0.75rem;">
                                                            <tr>
                                                                <th class="ps-3">Fecha</th>
                                                                <th>Nº Ingreso</th>
                                                                <th>Concepto / Forma</th>
                                                                <th class="text-end pe-3">Monto</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="fvPagoTbodyHistorial" class="small" style="font-size: 0.8rem;">
                                                            <tr>
                                                                <td colspan="4" class="text-center py-4 text-muted"><i class="spinner-border spinner-border-sm me-2"></i>Cargando historial...</td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Derecha: Formulario -->
                                    <div class="col-md-5">
                                        <!-- Card de registro -->
                                        <div class="card border border-primary border-opacity-25 bg-primary bg-opacity-10 shadow-sm rounded-3 overflow-hidden d-none" id="fvPagoCardRegistro">
                                            <div class="card-header bg-primary bg-opacity-25 border-0 py-2 d-flex align-items-center">
                                                <h6 class="card-title mb-0 fw-bold text-primary" style="font-size: 0.85rem;"><i class="bi bi-plus-circle me-2"></i>Registrar Cobro</h6>
                                            </div>
                                            <div class="card-body py-3 px-3 bg-white">
                                                <div id="fvPagoFormNuevo">
                                                    <div class="row g-2">
                                                        <div class="col-7">
                                                            <label class="form-label fw-bold mb-0 text-muted" style="font-size:0.7rem;">Serie <span class="text-danger">*</span></label>
                                                            <select class="form-select form-select-sm shadow-none border-secondary-subtle" id="fvPagoPuntoEmision" onchange="fvCargarSecuencialCobro(this.value)" required>
                                                                <option value="">— Seleccione —</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-5">
                                                            <label class="form-label fw-bold mb-0 text-muted" style="font-size:0.7rem;">Nº Secuencial</label>
                                                            <input type="text" class="form-control form-control-sm shadow-none border-secondary-subtle bg-light text-center fw-bold font-monospace" id="fvPagoSecuencial" readonly placeholder="—" style="font-size:0.75rem;">
                                                        </div>
                                                        <div class="col-6">
                                                            <label class="form-label fw-bold mb-0 text-muted" style="font-size:0.7rem;">Fecha <span class="text-danger">*</span></label>
                                                            <input type="date" class="form-control form-control-sm shadow-none border-secondary-subtle" id="fvPagoFecha" value="<?= date('Y-m-d') ?>" required>
                                                        </div>
                                                        <div class="col-6">
                                                            <label class="form-label fw-bold mb-0 text-muted" style="font-size:0.7rem;">Concepto</label>
                                                            <select class="form-select form-select-sm shadow-none border-secondary-subtle" id="fvPagoConcepto">
                                                                <option value="">— Opcional —</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label fw-bold mb-0 text-danger" style="font-size:0.7rem;">Monto a Cobrar ($) <span class="text-danger">*</span></label>
                                                            <input type="number" step="0.01" min="0.01" class="form-control form-control-sm shadow-none fw-bold text-danger border-danger border-opacity-50" id="fvPagoMonto" required>
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label fw-bold mb-0 text-muted" style="font-size:0.7rem;">Forma de Cobro <span class="text-danger">*</span></label>
                                                            <select class="form-select form-select-sm shadow-none border-secondary-subtle" id="fvPagoFormaCobro" onchange="fvToggleCobrosFormaForm(this.value)" required>
                                                                <option value="">— Seleccione —</option>
                                                            </select>
                                                        </div>
                                                        <!-- Campos banco condicionales -->
                                                        <div class="col-12 d-none" id="fvPagoDivBanco">
                                                            <div class="border border-warning border-opacity-25 rounded-2 p-2 bg-warning bg-opacity-10 mb-1 row g-2">
                                                                <div class="col-6">
                                                                    <label class="form-label fw-bold mb-0 text-dark" style="font-size:0.7rem;">Op. Bancaria</label>
                                                                    <select class="form-select form-select-sm" id="fvPagoTipoOp">
                                                                        <option value="TRANSFERENCIA">Transferencia</option>
                                                                        <option value="DEBITO">Débito</option>
                                                                        <option value="CHEQUE">Cheque</option>
                                                                    </select>
                                                                </div>
                                                                <div class="col-6">
                                                                    <label class="form-label fw-bold mb-0 text-dark" style="font-size:0.7rem;">Nº Referencia</label>
                                                                    <input type="text" class="form-control form-control-sm" id="fvPagoNumOp" placeholder="Nº doc / Transf">
                                                                </div>
                                                                <div class="col-12">
                                                                    <label class="form-label fw-bold mb-0 text-dark" style="font-size:0.7rem;">Banco</label>
                                                                    <select class="form-select form-select-sm" id="fvPagoBanco">
                                                                        <option value="">— Opcional —</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label fw-bold mb-0 text-muted" style="font-size:0.7rem;">Observaciones</label>
                                                            <input type="text" class="form-control form-control-sm shadow-none border-secondary-subtle" id="fvPagoObs" placeholder="Nota del cobro">
                                                        </div>
                                                        <div class="col-12 mt-2">
                                                            <button type="button" class="btn btn-success btn-sm w-100 py-2 fw-bold shadow-sm border-0" id="fvPagoBtnRegistrar" onclick="fvRegistrarCobro()">
                                                                <i class="bi bi-check-circle me-2"></i>Registrar Cobro
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Factura completamente pagada -->
                                        <div class="alert alert-success border-success border-opacity-25 text-center py-4 shadow-sm mb-0 d-none" id="fvPagoAlertaPagada">
                                            <i class="bi bi-check-circle-fill fs-2 mb-2 text-success d-block"></i>
                                            <h6 class="fw-bold mb-1 text-success">¡Factura Completamente Cobrada!</h6>
                                            <p class="text-muted mb-0" style="font-size: 0.75rem;">El saldo pendiente de esta factura es de $0.00.</p>
                                        </div>

                                        <!-- Factura nueva (sin ID aún) -->
                                        <div class="alert alert-secondary bg-light border-secondary-subtle text-center py-4 shadow-sm mb-0" id="fvPagoAlertaNueva">
                                            <i class="bi bi-cash-coin fs-2 mb-2 text-muted d-block opacity-50"></i>
                                            <h6 class="fw-bold mb-1 text-secondary">Nuevo Registro</h6>
                                            <p class="text-muted mb-0" style="font-size: 0.75rem;">Guarda la factura primero para poder registrar cobros.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pestaña: Retenciones -->
                        <div class="tab-pane fade p-3" id="m-tab-retenciones" role="tabpanel">
                            <p class="text-muted small text-center py-4">No hay retenciones asociadas a esta factura.</p>
                        </div>

                        <!-- Pestaña: Notas de Crédito -->
                        <div class="tab-pane fade p-3" id="m-tab-notas" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold mb-0 small text-primary"><i class="bi bi-file-earmark-minus me-1"></i> Notas de crédito asociadas</h6>
                                <?php if ($permNC['crear']): ?>
                                    <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 shadow-none border-0" style="font-size: 0.75rem;" id="btnNuevaNCDesdeFactura" onclick="abrirModalNuevaNCDesdeFV()"><i class="bi bi-plus-lg me-1"></i> Crear nota de crédito</button>
                                <?php endif; ?>
                            </div>
                            <div class="table-responsive border rounded">
                                <table class="table table-sm small mb-0 table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-3 py-2">Fecha</th>
                                            <th>Nº Nota de Crédito</th>
                                            <th>Motivo</th>
                                            <th>Estado</th>
                                            <th class="text-end pe-3">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody id="m-tbody-notas-credito">
                                        <tr>
                                            <td colspan="5" class="text-center py-5 text-muted">
                                                <i class="bi bi-info-circle me-1"></i> No se han encontrado notas de crédito para esta factura.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Pestaña: Guías de Remisión -->
                        <div class="tab-pane fade p-3" id="m-tab-guias" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold mb-0 small text-primary"><i class="bi bi-truck me-1"></i> Guías de remisión asociadas</h6>
                                <?php if ($permGR['crear']): ?>
                                    <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 shadow-none border-0" style="font-size: 0.75rem;" id="btnNuevaGRDesdeFactura" onclick="abrirModalNuevaGRDesdeFV()"><i class="bi bi-plus-lg me-1"></i> Crear guía de remisión</button>
                                <?php endif; ?>
                            </div>
                            <div class="table-responsive border rounded">
                                <table class="table table-sm small mb-0 table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-3 py-2">Fecha</th>
                                            <th>Nº Guía de Remisión</th>
                                            <th>Transportista</th>
                                            <th>Estado</th>
                                            <th class="pe-3">Placa</th>
                                        </tr>
                                    </thead>
                                    <tbody id="m-tbody-guias-remision">
                                        <tr>
                                            <td colspan="5" class="text-center py-5 text-muted">
                                                <i class="bi bi-info-circle me-1"></i> No se han encontrado guías de remisión para esta factura.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Pestaña: Respuestas SRI -->
                        <div class="tab-pane fade p-3" id="m-tab-respuestas-sri" role="tabpanel">
                            <div class="row g-3">
                                <!-- Estado de autorización -->
                                <div class="col-12">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span class="fw-bold small text-muted">Estado de autorización:</span>
                                        <span id="sri-badge-estado" class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2">Sin enviar</span>
                                    </div>
                                </div>
                                <!-- Reglas SRI para anulación -->
                                <div class="col-12">
                                    <div class="border rounded-2 bg-warning bg-opacity-10 border-warning border-opacity-25 p-2">
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <i class="bi bi-info-circle-fill text-warning"></i>
                                            <span class="fw-bold small text-warning-emphasis">Plazos permitidos para anular una factura</span>
                                        </div>
                                        <ul class="mb-0 ps-3 small text-muted" style="line-height:1.6;">
                                            <li><strong>Tiempo límite:</strong> Hasta el día <strong>7 del mes siguiente</strong> a la fecha de emisión del documento.</li>
                                            <li><strong>Excepciones:</strong> Si el día 7 cae en fin de semana o feriado, el plazo se extiende al siguiente día hábil.</li>
                                            <li class="text-danger-emphasis"><strong>Facturas a Consumidor Final:</strong> Las facturas emitidas a Consumidor Final <strong>no se pueden anular</strong> ni tampoco se les puede emitir una nota de crédito una vez transmitidas.</li>
                                        </ul>
                                    </div>
                                </div>
                                <!-- Fila 1: Clave de Acceso + Número de Autorización -->
                                <div class="col-md-6">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-key me-1"></i>Clave de Acceso</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" id="sri-clave-acceso" class="form-control form-control-sm font-monospace bg-light" readonly placeholder="- sin clave de acceso -" value="">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="copiarClaveAcceso()" title="Copiar clave de acceso">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-hash me-1"></i>Número de Autorización</label>
                                    <input type="text" id="sri-numero-autorizacion" class="form-control form-control-sm font-monospace bg-light" readonly placeholder="- pendiente -" value="">
                                </div>
                                <!-- Fila 2: Tipo de Ambiente + Tipo de Emisión + Fecha de Autorización + Tipo de Documento -->
                                <div class="col-md-3">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-building me-1"></i>Tipo de Ambiente</label>
                                    <input type="text" id="sri-ambiente" class="form-control form-control-sm bg-light" readonly placeholder="- pendiente -" value="">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-broadcast me-1"></i>Tipo de Emisión</label>
                                    <input type="text" id="sri-tipo-emision" class="form-control form-control-sm bg-light" readonly placeholder="- pendiente -" value="">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-calendar-check me-1"></i>Fecha de Autorización</label>
                                    <input type="text" id="sri-fecha-autorizacion" class="form-control form-control-sm bg-light" readonly placeholder="- pendiente -" value="">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-file-earmark-text me-1"></i>Tipo de Documento</label>
                                    <input type="text" id="sri-tipo-documento" class="form-control form-control-sm bg-light" readonly value="Factura">
                                </div>
                                <!-- Fila 3: Número de Documento + Número de Identificación + Correo del Cliente -->
                                <div class="col-md-3">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-receipt me-1"></i>Número de Documento</label>
                                    <input type="text" id="sri-numero-documento" class="form-control form-control-sm font-monospace bg-light" readonly placeholder="000-000-000000000" value="">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-person-vcard me-1"></i>Número de Identificación</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" id="sri-identificacion-cliente" class="form-control form-control-sm bg-light" readonly placeholder="- sin identificación -" value="">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="copiarCampoSri('sri-identificacion-cliente')" title="Copiar identificación">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-envelope me-1"></i>Correo del Cliente</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" id="sri-correo-cliente" class="form-control form-control-sm bg-light" readonly placeholder="- sin correo -" value="">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="copiarCampoSri('sri-correo-cliente')" title="Copiar correo">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                </div>
                                <!-- Mensajes / Observaciones SRI -->
                                <div class="col-12">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-chat-left-text me-1"></i>Mensajes del SRI</label>
                                    <div id="sri-mensajes-container" class="border rounded-2 bg-light p-2" style="min-height: 80px; max-height: 200px; overflow-y: auto; font-size: 0.8rem;">
                                        <p class="text-muted text-center mb-0 py-3 small">Sin respuesta del SRI registrada.</p>
                                    </div>
                                </div>
                                <!-- Historial de envíos SRI -->
                                <div class="col-12">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-clock-history me-1"></i>Historial de Envíos</label>
                                    <div class="border rounded-2 overflow-hidden">
                                        <table class="table table-sm small mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="ps-2 py-1 text-muted" style="width:140px">Fecha / Hora</th>
                                                    <th class="py-1 text-muted" style="width:80px">Ambiente</th>
                                                    <th class="py-1 text-muted" style="width:110px">Acción / Estado</th>
                                                    <th class="py-1 text-muted">Mensaje / Detalle</th>
                                                </tr>
                                            </thead>
                                            <tbody id="sri-tbody-historial">
                                                <tr>
                                                    <td colspan="5" class="text-center py-3 text-muted">Sin historial de envíos.</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>




                    </div>



                </form>
            </div>
            <div class="modal-footer justify-content-between bg-light border-top p-2">
                <div>
                    <?php if ($perm['eliminar']): ?>
                        <button type="button" class="btn btn-outline-danger btn-sm px-3 d-none" id="btnEliminarFacturaModal"><i class="bi bi-trash3 me-1"></i> Eliminar borrador</button>
                    <?php endif; ?>
                </div>
                <div>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                        <i class="fa-solid fa-xmark me-1"></i>Cerrar
                    </button>
                    <button type="button" class="btn btn-primary px-4 btn-sm" id="btnGuardarFacturaModal">
                        <i class="bi bi-check2-circle me-1"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Elementos sombra para evitar errores en los scripts originales extraídos -->
<div id="shadow-elements" class="d-none">
    <input id="buscarCliente">
    <input id="buscarProducto">
    <div id="tbodyClientes"></div>
    <div id="tbodyProductos"></div>
</div>
<?php
// Copia de seguridad de la ruta original del módulo de facturación
$rutaModuloOriginal = $rutaModulo;
$permOriginal = $perm;

// Variables requeridas por los modales originales para evitar errores de PHP durante la inclusión
$urlBaseClientes = BASE_URL . '/modulos/clientes';
$perm = ['crear' => true, 'editar' => true, 'eliminar' => true];
$rutaModulo = 'modulos/productos';
$canCreateVend = true;
$ordenCol = 'nombre';
$ordenDir = 'ASC';
$page = 1;
$totalPages = 1;

// Incluir modales originales
include dirname(__DIR__) . '/clientes/modal_cliente.php';
include dirname(__DIR__) . '/productos/modal.php';

// RESTAURAR variables originales para la lógica de Facturación
$rutaModulo = $rutaModuloOriginal;
$perm = $permOriginal;
?>

<script>
    // Ajustar z-index de los backdrops dinámicamente para modales anidados
    document.addEventListener('show.bs.modal', function(event) {
        if (event.target.classList.contains('modal-submodal')) {
            setTimeout(() => {
                const backdrop = document.querySelector('.modal-backdrop:not(.modal-submodal-backdrop)');
                if (backdrop) {
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    if (backdrops.length > 1) {
                        backdrops[backdrops.length - 1].classList.add('modal-submodal-backdrop');
                    }
                }
            }, 0);
        }
    });

    const B_URL = '<?= $base ?>';
    const RUTA_MODULO = '<?= $rutaModulo ?>';
    // ID de la factura actualmente abierta en el modal (0 = nueva)
    let FV_ID_ACTIVO = 0;
    let FV_FECHA_EMISION = null; // 'YYYY-MM-DD' de la factura activa; null si es nueva
    let FV_CLIENTE_RUC  = '';   // RUC/cédula del cliente activo (9999999999999 = Consumidor Final)
    // Cuando es true, cargarSecuencial no sobreescribe el campo (modo edición de factura existente)
    let FV_BLOQUEAR_SECUENCIAL = false;
    const TARIFAS_IVA = <?= json_encode($tarifasIva) ?>;
    const UNIDADES = <?= json_encode($unidades) ?>;
    const EMPRESA_CONFIG = {
        facturacion_libre: <?= (($empresa['facturacion_libre'] ?? false) === 'true' || ($empresa['facturacion_libre'] ?? false) === true) ? 'true' : 'false' ?>,
        facturacion_inventario: <?= (($empresa['facturacion_inventario'] ?? true) === 'true'  || ($empresa['facturacion_inventario'] ?? true)  === true)  ? 'true' : 'false' ?>,
        obligatorio_lotes: <?= (($empresa['obligatorio_lotes'] ?? false) === 'true'    || ($empresa['obligatorio_lotes'] ?? false)    === true)    ? 'true' : 'false' ?>,
        obligatorio_caducidad: <?= (($empresa['obligatorio_caducidad'] ?? false) === 'true' || ($empresa['obligatorio_caducidad'] ?? false) === true) ? 'true' : 'false' ?>,
        obligatorio_nup: <?= (($empresa['obligatorio_nup'] ?? false) === 'true'       || ($empresa['obligatorio_nup'] ?? false)       === true)       ? 'true' : 'false' ?>,
        mostrar_cajero_factura: <?= (($empresa['mostrar_cajero_factura'] ?? false) === 'true' || ($empresa['mostrar_cajero_factura'] ?? false) === true) ? 'true' : 'false' ?>,
        mostrar_vendedor_factura: <?= (($empresa['mostrar_vendedor_factura'] ?? false) === 'true' || ($empresa['mostrar_vendedor_factura'] ?? false) === true) ? 'true' : 'false' ?>,
        metodo_costeo: '<?= $empresa['metodo_costeo'] ?? 'promedio' ?>',
        decimales_precio: <?= (int) ($empresa['decimales_precio'] ?? 2) ?>,
        decimales_cantidad: <?= (int) ($empresa['decimales_cantidad'] ?? 2) ?>,
        mostrar_unidad_medida: <?= (($empresa['mostrar_unidad_medida'] ?? true) === 'true' || ($empresa['mostrar_unidad_medida'] ?? true) === true) ? 'true' : 'false' ?>,
        calculo_iva: '<?= $empresa['calculo_iva_facturacion'] ?? 'linea_linea' ?>',
        valor_limite_consumidor_final: <?= isset($empresa['valor_limite_consumidor_final']) && $empresa['valor_limite_consumidor_final'] !== null && $empresa['valor_limite_consumidor_final'] !== '' ? (float)$empresa['valor_limite_consumidor_final'] : 'null' ?>,
        id_forma_pago_sri_def: <?= isset($empresa['id_forma_pago_sri_def']) && $empresa['id_forma_pago_sri_def'] !== null ? (int)$empresa['id_forma_pago_sri_def'] : 'null' ?>,
        editar_precio_factura: <?= (($empresa['editar_precio_factura'] ?? true) === 'true' || ($empresa['editar_precio_factura'] ?? true) === true) ? 'true' : 'false' ?>,
        editar_iva_factura: <?= (($empresa['editar_iva_factura'] ?? true) === 'true' || ($empresa['editar_iva_factura'] ?? true) === true) ? 'true' : 'false' ?>,
        editar_descuento_factura: <?= (($empresa['editar_descuento_factura'] ?? true) === 'true' || ($empresa['editar_descuento_factura'] ?? true) === true) ? 'true' : 'false' ?>,
        mostrar_propina_factura: <?= (($empresa['mostrar_propina_factura'] ?? false) === 'true' || ($empresa['mostrar_propina_factura'] ?? false) === true) ? 'true' : 'false' ?>
    };
    const EMPRESA_INFO = {
        nombre: '<?= htmlspecialchars($empresa['nombre'] ?? '', ENT_QUOTES) ?>',
        nombre_comercial: '<?= htmlspecialchars($empresa['nombre_comercial'] ?? '', ENT_QUOTES) ?>',
        ruc: '<?= htmlspecialchars($empresa['ruc'] ?? '', ENT_QUOTES) ?>',
        direccion: '<?= htmlspecialchars($empresa['direccion'] ?? '', ENT_QUOTES) ?>',
        telefono: '<?= htmlspecialchars($empresa['telefono'] ?? '', ENT_QUOTES) ?>',
        correo: '<?= htmlspecialchars($empresa['mail'] ?? $empresa['correo'] ?? '', ENT_QUOTES) ?>',
        logo: '<?= htmlspecialchars($empresa['logo'] ?? '', ENT_QUOTES) ?>',
    };
    const USUARIO_NOMBRE = '<?= htmlspecialchars($_SESSION['nombre'] ?? '', ENT_QUOTES) ?>';
    // Alias cortos para uso frecuente
    const PERM_ACTUALIZAR = <?= !empty($perm['actualizar']) ? 'true' : 'false' ?>;
    const DEC_PRECIO = EMPRESA_CONFIG.decimales_precio;
    const DEC_CANT = EMPRESA_CONFIG.decimales_cantidad;

    let modalMain = null;

    // Estado del listado AJAX (con fallbacks para asegurar ordenamiento)
    window.FV_currentSort = '<?= $ordenCol ?>' || 'fecha_emision';
    window.FV_currentDir = '<?= $ordenDir ?>' || 'DESC';
    window.FV_currentPage = <?= (int)($page ?? 1) ?>;

    // ── AJAX: buscar / paginar / ordenar ─────────────────────────────────────

    window.FV_fetchSearch = async function(page = 1) {
        window.FV_currentPage = page;
        const buscar  = document.getElementById('buscarFactura')?.value || '';
        const sort    = window.FV_currentSort || 'fecha_emision';
        const dir     = window.FV_currentDir  || 'DESC';
        const url     = `${B_URL}/${RUTA_MODULO}/searchAjax?b=${encodeURIComponent(buscar)}&page=${page}&sort=${encodeURIComponent(sort)}&dir=${encodeURIComponent(dir)}`;
        try {
            const resp = await fetch(url);
            if (!resp.ok) return;
            const data = await resp.json();
            if (!data.ok) return;
            const tbody = document.getElementById('tbodyFacturas');
            if (tbody) tbody.innerHTML = data.rows ?? '';
            const pgCont = document.getElementById('paginationContainer');
            if (pgCont) pgCont.innerHTML = data.pagination ?? '';
            const pgInfo = document.getElementById('paginationInfo');
            if (pgInfo) pgInfo.textContent = data.info ?? '';
            const btnPdf   = document.getElementById('btnExportPdf');
            if (btnPdf   && data.pdf_url)   btnPdf.href   = data.pdf_url;
            const btnXlsx  = document.getElementById('btnExportExcel');
            if (btnXlsx  && data.excel_url) btnXlsx.href  = data.excel_url;
            // Actualizar íconos de ordenamiento en encabezados
            document.querySelectorAll('th.sortable-header[data-sort]').forEach(th => {
                const icon = th.querySelector('i.bi');
                if (!icon) return;
                if (th.dataset.sort === sort) {
                    icon.className = `bi ${dir === 'ASC' ? 'bi-sort-alpha-down' : 'bi-sort-alpha-up'} ms-1 text-primary`;
                } else {
                    icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
                }
            });
        } catch (e) {
            console.error('FV_fetchSearch error:', e);
        }
    };

    window.FV_cambiarPaginaAjax = function(page) {
        window.FV_fetchSearch(page);
    };

    window.FV_ordenar = function(col) {
        if (!col) return;
        const dir = (window.FV_currentSort === col && window.FV_currentDir === 'ASC') ? 'DESC' : 'ASC';
        window.FV_currentSort = col;
        window.FV_currentDir  = dir;
        // Guardar preferencia de ordenamiento (persiste entre sesiones)
        if (typeof window.guardarOrdenacionVista === 'function') {
            window.guardarOrdenacionVista('factura-venta', col, dir);
        }
        window.FV_fetchSearch(1);
    };

    // ─────────────────────────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', function() {
        modalMain = new bootstrap.Modal(document.getElementById('modalNuevaFactura'));

        // Al mostrar el modal: cargar secuencial solo para facturas nuevas, y enfocar buscador
        document.getElementById('modalNuevaFactura').addEventListener('shown.bs.modal', function() {
            // Solo cargar el siguiente secuencial cuando es una factura NUEVA (FV_ID_ACTIVO === 0)
            // Para facturas existentes el secuencial ya fue seteado y no debe sobreescribirse
            if (FV_ID_ACTIVO === 0) {
                const selectPto = document.getElementById('m-select-puntos');
                if (selectPto && selectPto.value) {
                    cargarSecuencial(selectPto.value);
                }
            }

            const searchCliente = document.getElementById('m-search-cliente');
            if (searchCliente) {
                searchCliente.focus();
                searchCliente.select();
            }
        });

        // Listener para guardar
        const btnSave = document.getElementById('btnGuardarFacturaModal');
        if (btnSave) btnSave.addEventListener('click', guardarFactura);

        // Listener para eliminar borrador
        const btnElim = document.getElementById('btnEliminarFacturaModal');
        if (btnElim) btnElim.addEventListener('click', () => {
            if (typeof window.eliminarFacturaBorrador === 'function') {
                window.eliminarFacturaBorrador();
            }
        });

        // Listener para anular factura
        const btnAnular = document.getElementById('btnAnularFacturaModal');
        if (btnAnular) btnAnular.addEventListener('click', () => {
            if (typeof window.anularFactura === 'function') {
                window.anularFactura();
            }
        });

        // Registrar auto-guardado en localStorage
        fvRegistrarAutoGuardado();
    });

    // --- Enlaces a modales originales ---
    window.abrirModalCrearCliente = () => {
        if (typeof abrirModalClienteCrear === 'function') {
            abrirModalClienteCrear();
        } else {
            console.error('La función abrirModalClienteCrear no se cargó correctamente.');
        }
    };

    window.abrirModalCrearProducto = () => {
        if (typeof abrirModalProductoCrear === 'function') {
            abrirModalProductoCrear();
        } else {
            // En productos el nombre es diferente en el script original (abrirModalProductoCrear no existe, es getModal)
            // Revisando productos/index.php: onclick="abrirModalProductoCrear()"
            if (typeof abrirModalProductoCrear === 'function') {
                abrirModalProductoCrear();
            }
        }
    };

    // fetchSearch está definido más abajo para el listado de facturas (AJAX).

    async function cargarPuntosEmision(idEst) {
        if (!idEst) return;
        const resp = await fetch(`${B_URL}/${RUTA_MODULO}/getPuntosEmisionAjax?id_establecimiento=${idEst}`);
        const json = await resp.json();
        const select = document.getElementById('m-select-puntos');
        select.innerHTML = '';
        json.data.forEach(p => {
            select.innerHTML += `<option value="${p.id}">${p.codigo} - ${p.nombre}</option>`;
        });
    }

    function copiarCampoSri(inputId) {
        const input = document.getElementById(inputId);
        const val = input ? input.value.trim() : '';
        if (!val) return;
        navigator.clipboard.writeText(val).then(() => {
            const btn = input.nextElementSibling;
            if (btn) {
                const icon = btn.querySelector('i');
                icon.classList.replace('bi-clipboard', 'bi-clipboard-check');
                btn.classList.replace('btn-outline-secondary', 'btn-outline-success');
                setTimeout(() => {
                    icon.classList.replace('bi-clipboard-check', 'bi-clipboard');
                    btn.classList.replace('btn-outline-success', 'btn-outline-secondary');
                }, 2000);
            }
        }).catch(() => {
            input.select();
            document.execCommand('copy');
        });
    }

    function copiarClaveAcceso() { copiarCampoSri('sri-clave-acceso'); }

    async function guardarFactura() {
        const btn = document.getElementById('btnGuardarFacturaModal');
        const form = document.getElementById('formFacturaModal');

        // ”€”€ Leer valores de cabecera ”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€
        const fecha = form.querySelector('[name="fecha_emision"]')?.value || '';
        const idCliente = document.getElementById('m-id-cliente').value;
        const diasCred = parseInt(document.getElementById('m-input-dias-credito')?.value || '0');
        const totalFactura = r2(parseFloat(document.getElementById('m-lbl-total').textContent) || 0);

        // ”€”€ Validaciones de cabecera ”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€
        if (!fecha) return Swal.fire({
            icon: 'warning',
            title: 'Atención',
            text: 'La fecha de emisión es obligatoria.'
        });
        if (!idCliente) return Swal.fire({
            icon: 'warning',
            title: 'Atención',
            text: 'Debe seleccionar un cliente.'
        });
        if (isNaN(diasCred) || diasCred < 0) return Swal.fire({
            icon: 'warning',
            title: 'Atención',
            text: 'Los días de crédito no pueden ser menores a cero.'
        });
        if (totalFactura < 0) return Swal.fire({
            icon: 'warning',
            title: 'Atención',
            text: 'El total de la factura no puede ser negativo.'
        });

        // ”€”€ Recolectar ítems ”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€
        let totalSinImpuestos = 0;
        let totalDescuento = 0;
        let hayError = false;
        const detalles = [];
        const idBodega = document.getElementById('m-select-bodega')?.value || '';

        document.querySelectorAll('.row-detalle').forEach(tr => {
            if (hayError) return;
            const idProd = tr.querySelector('.input-id-producto').value;
            const esLibre = tr.querySelector('.input-es-libre')?.value === '1';
            const desc = tr.querySelector('.input-descripcion').value.trim();

            if (!idProd && !(esLibre && desc)) return; // fila vacía

            // Campos obligatorios por configuración (solo para productos inventariables, NO servicios Tipo 02)
            if (!esLibre && idProd) {
                const tipoProd = (tr.dataset.tipoProduccion || '01').trim();
                const esInv = (tr.dataset.inventariable == 'true' || tr.dataset.inventariable == '1' || tr.dataset.inventariable === true);

                if (tipoProd !== '02' && esInv) {
                    if (EMPRESA_CONFIG.obligatorio_lotes && !tr.querySelector('.input-lote')?.value.trim()) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Atención',
                            text: `"${desc}": debe ingresar el Número de Lote.`
                        });
                        hayError = true;
                        return;
                    }
                    if (EMPRESA_CONFIG.obligatorio_caducidad && !tr.querySelector('.input-caducidad')?.value.trim()) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Atención',
                            text: `"${desc}": debe ingresar la Fecha de Caducidad.`
                        });
                        hayError = true;
                        return;
                    }
                    if (EMPRESA_CONFIG.obligatorio_nup && !tr.querySelector('.input-nup')?.value.trim()) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Atención',
                            text: `"${desc}": debe ingresar el NUP / Serial.`
                        });
                        hayError = true;
                        return;
                    }
                }
            }

            const subtotalNeto = r2(parseFloat(tr.querySelector('.subtotal-line').textContent) || 0);
            const iceVal = r2(parseFloat(tr.querySelector('.input-ice-val').value) || 0);
            const descVal = r2(parseFloat(tr.querySelector('.input-desc').value) || 0);
            const ivaPct = parseFloat(tr.querySelector('.input-iva').value) || 0;
            const baseIvaFila = r2(subtotalNeto + iceVal);
            const ivaValFila = r2(baseIvaFila * ivaPct / 100);
            const selIva = tr.querySelector('.input-iva');
            const codPorcentaje = selIva?.selectedOptions[0]?.dataset.codigo || '0';

            totalSinImpuestos = r2(totalSinImpuestos + subtotalNeto);
            totalDescuento = r2(totalDescuento + descVal);

            const impuestos = [{
                codigo_impuesto: '2',
                codigo_porcentaje: codPorcentaje,
                tarifa: ivaPct,
                base_imponible: baseIvaFila.toFixed(2),
                valor: ivaValFila.toFixed(2)
            }];
            if (iceVal > 0) {
                impuestos.push({
                    codigo_impuesto: '3',
                    codigo_porcentaje: tr.querySelector('.input-ice-cod').value,
                    tarifa: tr.querySelector('.input-ice-pct').value,
                    base_imponible: subtotalNeto.toFixed(2),
                    valor: iceVal.toFixed(2)
                });
            }

            detalles.push({
                id_producto: idProd || '',
                es_libre: esLibre ? '1' : '0',
                codigo_principal: tr.querySelector('.input-codigo').value,
                descripcion: desc,
                info_adicional: tr.querySelector('.input-adicional').value,
                id_unidad_medida: tr.querySelector('.input-medida').value || '',
                id_bodega: idBodega,
                cantidad: tr.querySelector('.input-cantidad').value,
                precio_unitario: tr.querySelector('.input-precio').value,
                descuento: descVal.toFixed(2),
                porcentaje_iva: ivaPct,
                lista_precios: tr.querySelector('.input-lista-precios').value,
                precio_total_sin_impuesto: subtotalNeto.toFixed(2),
                casillero: tr.querySelector('.input-casillero').value,
                lote: tr.querySelector('.input-lote')?.value.trim() || '',
                caducidad: tr.querySelector('.input-caducidad')?.value.trim() || '',
                nup: tr.querySelector('.input-nup')?.value.trim() || '',
                ice_valor: iceVal.toFixed(2),
                ice_porcentaje: tr.querySelector('.input-ice-pct').value,
                ice_codigo: tr.querySelector('.input-ice-cod').value,
                id_medida: tr.querySelector('.input-medida')?.value || null,
                impuestos
            });
        });

        if (hayError) return;
        if (detalles.length === 0) return Swal.fire({
            icon: 'warning',
            title: 'Atención',
            text: 'Debe agregar al menos un producto o servicio.'
        });

        // ”€”€ Recolectar pagos ”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€
        let sumPagos = 0;
        const pagos = [];
        document.querySelectorAll('#m-container-pagos .row-pago').forEach(row => {
            const sel = row.querySelector('select[name="f_pago_id[]"]');
            const inp = row.querySelector('input[name="f_pago_valor[]"]');
            const val = r2(parseFloat(inp?.value) || 0);
            if (sel && val > 0) {
                pagos.push({
                    forma_pago: sel.value,
                    total: val.toFixed(2),
                    plazo: diasCred,
                    unidad_tiempo: 'dias'
                });
                sumPagos = r2(sumPagos + val);
            }
        });

        if (pagos.length === 0) return Swal.fire({
            icon: 'warning',
            title: 'Atención',
            text: 'Debe especificar al menos una forma de pago.'
        });
        if (Math.abs(sumPagos - totalFactura) > 0.001) {
            return Swal.fire({
                icon: 'warning',
                title: 'Descuadre en Pagos',
                text: `La suma de formas de pago ($${sumPagos.toFixed(2)}) no coincide con el total de la factura ($${totalFactura.toFixed(2)}).`
            });
        }

        // ”€”€ Validar límite consumidor final ”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€
        const nombreTipoCliente = (document.getElementById('m-nombre-tipo-id-cliente')?.value || '').toUpperCase();
        const esConsumidorFinal = nombreTipoCliente.includes('CONSUMIDOR');
        const limiteConsumidor = EMPRESA_CONFIG.valor_limite_consumidor_final;
        if (esConsumidorFinal && limiteConsumidor !== null && totalFactura > limiteConsumidor) {
            return Swal.fire({
                icon: 'warning',
                title: 'Límite Consumidor Final',
                text: `El total de la factura ($${totalFactura.toFixed(2)}) supera el límite permitido para Consumidor Final ($${limiteConsumidor.toFixed(2)}).\n\nPara montos mayores debe identificar al cliente con RUC o cédula.`
            });
        }

        // ”€”€ Recolectar info adicional ”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€
        const infoAdicional = [];
        document.querySelectorAll('.row-info-adicional').forEach(tr => {
            const nombre = tr.querySelector('.input-info-concepto').value.trim();
            const valor = tr.querySelector('.input-info-detalle').value.trim();
            if (nombre && valor) infoAdicional.push({
                nombre,
                valor
            });
        });

        // ”€”€ Leer totales y códigos ”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€
        const iceTotal = document.getElementById('m-lbl-ice-row')?.classList.contains('d-none') ?
            0 : r2(parseFloat(document.getElementById('m-lbl-ice')?.textContent) || 0);
        const propina = r2(parseFloat(document.getElementById('m-input-propina')?.value) || 0);
        const selPto = document.getElementById('m-select-puntos');
        const selOpt = selPto?.options[selPto.selectedIndex];
        const codEst = selOpt?.dataset.codEst || '';
        const codPunto = selOpt?.dataset.codPunto || '';

        // ”€”€ Construir payload ”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€
        const payload = {
            ...(FV_ID_ACTIVO > 0 ? {
                id: FV_ID_ACTIVO
            } : {}),
            id_cliente: idCliente,
            id_establecimiento: document.getElementById('m-id-establecimiento').value,
            id_punto_emision: selPto.value,
            establecimiento: codEst,
            punto_emision: codPunto,
            secuencial: document.getElementById('m-input-secuencial').value,
            fecha_emision: fecha,
            id_vendedor: document.getElementById('m-select-vendedor')?.value || '',
            dias_credito: diasCred,
            plazo: diasCred,
            total_sin_impuestos: totalSinImpuestos.toFixed(2),
            total_descuento: totalDescuento.toFixed(2),
            total_ice: iceTotal.toFixed(2),
            importe_total: totalFactura.toFixed(2),
            propina: propina.toFixed(2),
            observaciones: document.getElementById('m-input-observaciones')?.value || '',
            detalles,
            pagos,
            info_adicional: infoAdicional,
            asiento_detalles: typeof window.fvCapturarDetallesAsiento === 'function' ? window.fvCapturarDetallesAsiento() : []
        };

        // Advertir si el asiento fue editado manualmente y está descuadrado
        if (window.ASIENTO_MANUAL) {
            const badgeCuadre = document.getElementById('badge-asiento-cuadre');
            const estaDescuadrado = badgeCuadre && badgeCuadre.textContent === 'Descuadrado';
            if (estaDescuadrado) {
                const confirmar = await Swal.fire({
                    icon: 'warning',
                    title: 'Asiento contable descuadrado',
                    text: 'El asiento contable está descuadrado (Debe ≠ Haber). Si guarda, el asiento no se registrará. ¿Desea continuar de todos modos?',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    confirmButtonText: 'Sí, guardar sin asiento',
                    cancelButtonText: 'Cancelar y corregir',
                });
                if (!confirmar.isConfirmed) return;
            }
        }

        // “€”€ Enviar “€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€
        btn.disabled = true;
        let _guardadoOk = false;

        try {
            const formData = new FormData();
            const appendRecursive = (data, root = '') => {
                for (const key in data) {
                    const name = root ? `${root}[${key}]` : key;
                    if (Array.isArray(data[key])) {
                        data[key].forEach((item, i) => appendRecursive(item, `${name}[${i}]`));
                    } else if (typeof data[key] === 'object' && data[key] !== null) {
                        appendRecursive(data[key], name);
                    } else {
                        formData.append(name, data[key] ?? '');
                    }
                }
            };
            appendRecursive(payload);

            const resp = await fetch(`${B_URL}/${RUTA_MODULO}/guardarAjax`, {
                method: 'POST',
                body: formData
            });
            const json = await resp.json();

            if (json.ok) {
                _guardadoOk = true;
                fvLimpiarBorrador();
                modalMain.hide();

                // Mostrar advertencia del asiento ANTES de refrescar la lista,
                // con await para que el usuario la cierre antes de que cambie el DOM.
                if (json.asiento_warning) {
                    await Swal.fire({
                        icon: 'warning',
                        title: 'Factura guardada — asiento pendiente',
                        html: `La factura se guardó correctamente, pero el asiento contable no pudo generarse:<br><br><small class="text-muted">${json.asiento_warning}</small>`,
                        confirmButtonText: 'Entendido'
                    });
                }

                if (FV_ID_ACTIVO > 0 && json.rowHtml) {
                    let rowReplaced = false;
                    document.querySelectorAll('tr.factura-row').forEach(tr => {
                        try {
                            const dataRow = JSON.parse(tr.dataset.row);
                            if (dataRow.id == FV_ID_ACTIVO) {
                                tr.outerHTML = json.rowHtml;
                                rowReplaced = true;
                            }
                        } catch (e) {}
                    });
                    if (!rowReplaced && typeof window.FV_fetchSearch === 'function') {
                        window.FV_fetchSearch(window.FV_currentPage || 1);
                    }
                } else {
                    if (typeof window.FV_fetchSearch === 'function') {
                        window.FV_fetchSearch(1);
                    } else {
                        location.reload();
                    }
                }
            } else {
                const titulo = (json.mensaje || '').includes('Stock insuficiente') ? 'Stock Insuficiente' : 'Oops...';
                Swal.fire({
                    icon: 'error',
                    title: titulo,
                    text: json.mensaje,
                    confirmButtonColor: '#d33'
                });
            }
        } catch (e) {
            Swal.fire({
                icon: 'error',
                title: 'Error de Conexión',
                text: 'No se pudo conectar con el servidor. Intente nuevamente.'
            });
        } finally {
            // Rehabilitar siempre excepto cuando el guardado fue exitoso (modal ya cerrado)
            if (!_guardadoOk) btn.disabled = false;
        }
    }

    // =====================================================================
    // LOCAL STORAGE - Auto-guardado de borrador de factura
    // =====================================================================
    const FV_STORAGE_KEY = 'fv_borrador_<?= (int)($_SESSION['id_empresa'] ?? 0) ?>_<?= (int)($_SESSION['id_usuario'] ?? 0) ?>';

    /** Serializa el estado actual del modal a un objeto plano (sin secuencial). */
    function fvCapturarEstado() {
        const estado = {};

        // Cliente
        estado.id_cliente = document.getElementById('m-id-cliente')?.value || '';
        estado.search_cliente = document.getElementById('m-search-cliente')?.value || '';
        const rucLbl = document.getElementById('m-lbl-cliente-ruc');
        const dirLbl = document.getElementById('m-lbl-cliente-direccion');
        const mailLbl = document.getElementById('m-lbl-cliente-correo');
        estado.cliente_ruc = rucLbl?.textContent || '';
        estado.cliente_direccion = dirLbl?.textContent || '';
        estado.cliente_correo = mailLbl?.textContent || '';

        // Cabecera
        estado.id_establecimiento = document.getElementById('m-id-establecimiento')?.value || '';
        estado.id_punto_emision = document.getElementById('m-select-puntos')?.value || '';
        estado.id_bodega = document.getElementById('m-select-bodega')?.value || '';
        estado.fecha = document.querySelector('#formFacturaModal [name="fecha_emision"]')?.value || '';
        estado.id_vendedor = document.getElementById('m-select-vendedor')?.value || '';
        estado.dias_credito = document.getElementById('m-input-dias-credito')?.value || '';
        estado.propina = document.getElementById('m-input-propina')?.value || '0.00';
        estado.observacion = document.getElementById('m-input-observaciones')?.value || '';

        // Detalle de ítems
        estado.detalles = [];
        document.querySelectorAll('#m-tbodyDetalle tr').forEach(tr => {
            const fila = {
                id_producto: tr.querySelector('.input-id-producto')?.value || '',
                codigo: tr.querySelector('.input-codigo')?.value || '',
                nombre: tr.querySelector('.input-descripcion')?.value || '',
                cantidad: tr.querySelector('.input-cantidad')?.value || '',
                precio: tr.querySelector('.input-precio')?.value || '',
                descuento: tr.querySelector('.input-desc')?.value || '',
                iva: tr.querySelector('.input-iva')?.value || '',
                lista_precios: tr.querySelector('.input-lista-precios')?.value || '',
                casillero: tr.querySelector('.input-casillero')?.value || '',
                lote: tr.querySelector('.input-lote')?.value || '',
                nup: tr.querySelector('.input-nup')?.value || '',
                ice_pct: tr.querySelector('.input-ice-pct')?.value || '',
                ice_cod: tr.querySelector('.input-ice-cod')?.value || '',
            };
            if (fila.id_producto || fila.nombre.trim()) estado.detalles.push(fila);
        });

        // Formas de pago
        estado.pagos = [];
        document.querySelectorAll('#m-container-pagos .row-pago').forEach(row => {
            const sel = row.querySelector('select[name="f_pago_id[]"]');
            const val = row.querySelector('input[name="f_pago_valor[]"]')?.value || '';
            if (sel) estado.pagos.push({
                pago_id: sel.value,
                valor: val
            });
        });

        // Info adicional (solo filas libres, no las fijas - esas se reconstruyen)
        estado.info_adicional = [];
        document.querySelectorAll('#m-tbody-info-adicional tr:not([data-tipo])').forEach(tr => {
            const concepto = tr.querySelector('.input-info-concepto')?.value || '';
            const detalle = tr.querySelector('.input-info-detalle')?.value || '';
            if (concepto || detalle) estado.info_adicional.push({
                concepto,
                detalle
            });
        });

        return estado;
    }

    /** Guarda el estado actual en localStorage. */
    function fvAutoGuardar() {
        try {
            const estado = fvCapturarEstado();
            // Solo guardar si hay algo significativo (cliente o al menos un ítem con producto)
            if (!estado.id_cliente && !estado.detalles.length) {
                localStorage.removeItem(FV_STORAGE_KEY);
                return;
            }
            localStorage.setItem(FV_STORAGE_KEY, JSON.stringify(estado));
        } catch (e) {
            /* localStorage puede estar lleno o deshabilitado */
        }
    }

    /** Elimina el borrador guardado. */
    function fvLimpiarBorrador() {
        try {
            localStorage.removeItem(FV_STORAGE_KEY);
        } catch (e) {}
    }

    /** Restaura el estado guardado en el modal. */
    function fvRestaurar(estado) {
        // Cliente
        if (estado.id_cliente) {
            document.getElementById('m-id-cliente').value = estado.id_cliente;
            document.getElementById('m-search-cliente').value = estado.search_cliente || '';
            const rucLbl = document.getElementById('m-lbl-cliente-ruc');
            const dirLbl = document.getElementById('m-lbl-cliente-direccion');
            const mailLbl = document.getElementById('m-lbl-cliente-correo');
            if (rucLbl) rucLbl.textContent = estado.cliente_ruc || '';
            if (dirLbl) dirLbl.textContent = estado.cliente_direccion || '';
            if (mailLbl) mailLbl.textContent = estado.cliente_correo || '';
            document.getElementById('m-info-cliente')?.classList.remove('d-none');
            // Restaurar correo en info adicional
            actualizarInfoCorreoCliente(estado.cliente_correo && estado.cliente_correo !== 'Sin correo registrado' ? estado.cliente_correo : '');
        }

        // Cabecera
        if (estado.fecha) {
            const el = document.querySelector('#formFacturaModal [name="fecha_emision"]');
            if (el) el.value = estado.fecha;
        }
        if (estado.id_vendedor) {
            const sel = document.getElementById('m-select-vendedor');
            if (sel) {
                sel.value = estado.id_vendedor;
                actualizarInfoVendedor(sel);
            }
        }
        if (estado.id_bodega) {
            const sel = document.getElementById('m-select-bodega');
            if (sel) sel.value = estado.id_bodega;
        }
        if (estado.dias_credito) {
            const el = document.getElementById('m-input-dias-credito');
            if (el) el.value = estado.dias_credito;
        }
        if (estado.propina) {
            const el = document.getElementById('m-input-propina');
            if (el) el.value = estado.propina;
        }
        if (estado.observacion) {
            const el = document.getElementById('m-input-observaciones');
            if (el) el.value = estado.observacion;
        }

        // Detalle
        const tbody = document.getElementById('m-tbodyDetalle');
        tbody.innerHTML = '';
        if (estado.detalles && estado.detalles.length > 0) {
            estado.detalles.forEach(fila => {
                agregarFila();
                const filas = tbody.querySelectorAll('tr.row-detalle');
                const tr = filas[filas.length - 1];
                if (!tr) return;
                const set = (sel, val) => {
                    const el = tr.querySelector(sel);
                    if (el && val !== undefined) el.value = val;
                };
                set('.input-id-producto', fila.id_producto);
                set('.input-codigo', fila.codigo);
                set('.input-descripcion', fila.nombre);
                set('.input-cantidad', fila.cantidad);
                set('.input-precio', fila.precio);
                set('.input-desc', fila.descuento);
                set('.input-iva', fila.iva);
                set('.input-lista-precios', fila.lista_precios);
                set('.input-casillero', fila.casillero);
                const selLote = tr.querySelector('.input-lote');
                const selCad = tr.querySelector('.input-caducidad');
                if (selLote) {
                    selLote.dataset.originalLote = fila.lote || '';
                    selLote.value = fila.lote || '';
                }
                if (selCad) {
                    selCad.dataset.originalCad = fila.caducidad || '';
                    selCad.value = fila.caducidad || '';
                }
                if (fila.id_producto && (selLote || selCad)) {
                    cargarLotesFila(tr);
                }

                set('.input-nup', fila.nup);
                set('.input-ice-pct', fila.ice_pct);
                set('.input-ice-cod', fila.ice_cod);

                // Recalcular subtotal de la fila
                const inputCant = tr.querySelector('.input-cantidad');
                if (inputCant) calcFila(inputCant);
            });
        } else {
            agregarFila();
        }

        // Info adicional libre
        if (estado.info_adicional && estado.info_adicional.length > 0) {
            // Limpiar filas libres existentes antes de restaurar
            document.querySelectorAll('#m-tbody-info-adicional tr:not([data-tipo])').forEach(tr => tr.remove());
            estado.info_adicional.forEach(item => {
                agregarInfoAdicional();
                const filas = document.querySelectorAll('#m-tbody-info-adicional tr:not([data-tipo])');
                const ultima = filas[filas.length - 1];
                if (ultima) {
                    ultima.querySelector('.input-info-concepto').value = item.concepto;
                    ultima.querySelector('.input-info-detalle').value = item.detalle;
                }
            });
        }

        calcTotales();
    }

    /** Registra listeners de auto-guardado sobre los elementos del modal. */
    function fvRegistrarAutoGuardado() {
        const modal = document.getElementById('formFacturaModal');
        if (!modal) return;
        const debouncedGuardar = debounce(fvAutoGuardar, 800);
        modal.addEventListener('input', debouncedGuardar);
        modal.addEventListener('change', debouncedGuardar);
    }

    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    async function cargarSecuencial(idPunto) {
        if (!idPunto || FV_BLOQUEAR_SECUENCIAL) return;
        const inputSec = document.getElementById('m-input-secuencial');
        if (inputSec) inputSec.placeholder = 'Cargando...';
        try {
            const resp = await fetch(`${B_URL}/${RUTA_MODULO}/getSecuencialAjax?id_punto_emision=${idPunto}&tipo=factura`);
            const json = await resp.json();
            if (json.ok) {
                inputSec.value = json.formateado || String(json.secuencial).padStart(9, '0');
                inputSec.placeholder = '000000001';

                // Indicador visual si es un gap (número faltante recuperado)
                if (json.es_gap) {
                    inputSec.classList.add('border-warning');
                    inputSec.title = json.detalle || 'Número faltante recuperado';
                } else {
                    inputSec.classList.remove('border-warning');
                    inputSec.title = json.detalle || 'Siguiente consecutivo';
                }
            } else {
                inputSec.value = '000000001';
                inputSec.placeholder = '000000001';
                console.warn('getSecuencialAjax respondió ok=false:', json);
            }
        } catch (e) {
            if (inputSec) {
                inputSec.value = '000000001';
                inputSec.placeholder = '000000001';
            }
            console.error('Error cargando secuencial', e);
        }
    }

    async function cargarPuntosEmision(idEst) {
        if (!idEst) return;
        const resp = await fetch(`${B_URL}/${RUTA_MODULO}/getPuntosEmisionAjax?id_establecimiento=${idEst}`);
        const json = await resp.json();
        const select = document.getElementById('m-select-puntos');
        select.innerHTML = '';
        json.data.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = p.codigo_punto;
            select.appendChild(opt);
        });
        if (json.data.length > 0) cargarSecuencial(json.data[0].id);
    }

    /**
     * Activa o desactiva los botones de acción según el estado de la factura.
     */
    function fvActualizarEstadoBotones(estado = 'nuevo') {
        const idActivo = parseInt(FV_ID_ACTIVO) || 0;

        // ── Badge de estado en el header del modal ─────────────────────────────
        const badgeModal = document.getElementById('fv-badge-estado-modal');
        if (badgeModal) {
            const st = (estado || '').toLowerCase().trim();
            const estadoMapModal = {
                'autorizado':       ['bg-success bg-opacity-10 text-success border border-success border-opacity-25',   'Autorizado'],
                'no_autorizado':    ['bg-danger  bg-opacity-10 text-danger  border border-danger  border-opacity-25',   'No autorizado'],
                'devuelta':         ['bg-danger  bg-opacity-10 text-danger  border border-danger  border-opacity-25',   'Devuelta'],
                'en_procesamiento': ['bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25',  'En procesamiento'],
                'recibida':         ['bg-info    bg-opacity-10 text-info    border border-info    border-opacity-25',   'Recibida'],
                'borrador':         ['bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25', 'Borrador'],
                'anulado':          ['bg-danger  bg-opacity-10 text-danger  border border-danger  border-opacity-25',   'Anulado'],
            };
            if (st === 'nuevo' || idActivo === 0) {
                badgeModal.className = 'badge d-none ms-2';
                badgeModal.textContent = '';
            } else {
                const [cls, lbl] = estadoMapModal[st] ?? ['bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25', st];
                badgeModal.className = `badge ${cls} ms-2`;
                badgeModal.style.cssText = 'font-size:0.72rem;vertical-align:middle;';
                badgeModal.textContent = lbl;
            }
        }

        const btnSri = document.getElementById('m-btn-sri');
        const btnDuplicar  = document.getElementById('m-btn-duplicar');
        const btnPdf       = document.getElementById('m-btn-pdf');
        const btnXml       = document.getElementById('m-btn-xml');
        const btnCorreo    = document.getElementById('m-btn-correo');
        const btnTicket    = document.getElementById('m-btn-ticket');
        const btnAnular    = document.getElementById('btnAnularFacturaModal');
        const btnTarjeta   = document.getElementById('m-btn-pagar-tarjeta');
        const vrs = document.querySelectorAll('.modal-body .vr'); // Separadores visuales

        // Si es nueva factura (ID 0), ocultar todo
        if (idActivo === 0) {
            if (btnSri) btnSri.classList.add('d-none');
            if (btnDuplicar) btnDuplicar.classList.add('d-none');
            if (btnPdf) btnPdf.classList.add('d-none');
            if (btnXml) btnXml.classList.add('d-none');
            if (btnCorreo) btnCorreo.classList.add('d-none');
            if (btnTicket) btnTicket.classList.add('d-none');
            if (btnAnular) btnAnular.classList.add('d-none');
            if (btnTarjeta) btnTarjeta.classList.add('d-none');
            vrs.forEach(v => v.classList.add('d-none'));
            return;
        }

        // Mostrar botones base (ID existente)
        if (btnSri) btnSri.classList.remove('d-none');
        if (btnDuplicar) btnDuplicar.classList.remove('d-none');
        if (btnPdf) btnPdf.classList.remove('d-none');
        if (btnXml) btnXml.classList.remove('d-none');
        if (btnCorreo) btnCorreo.classList.remove('d-none');
        if (btnTicket) btnTicket.classList.remove('d-none');
        vrs.forEach(v => v.classList.remove('d-none'));

        // Lógica de habilitación y visibilidad específica por Estado
        const st = (estado || '').toLowerCase().trim();
        const esAutorizado = st.includes('autorizado') || st.includes('autorizada');

        if (btnDuplicar) btnDuplicar.disabled = false;
        if (btnPdf) btnPdf.disabled = false;
        if (btnCorreo) btnCorreo.disabled = !esAutorizado;

        // Botón SRI: activo siempre que el documento esté en borrador
        if (btnSri) {
            btnSri.disabled = st !== 'borrador';
            btnSri.title    = st !== 'borrador' ? 'Solo se pueden enviar documentos en estado borrador.' : '';
        }

        // Botón Anular: SOLO en estado autorizado y con permiso de actualizar (Centralizado)
        if (btnAnular) {
            // Permitimos 'autorizado' o 'autorizada' para cubrir variaciones locales
            if (esAutorizado && PERM_ACTUALIZAR) {
                btnAnular.classList.remove('d-none');
            } else {
                btnAnular.classList.add('d-none');
            }
        }

        // PDF: habilitado si hay cliente e ítems en la factura
        if (btnPdf) {
            const tieneCliente = !!(document.getElementById('m-id-cliente')?.value);
            const tieneItems = document.querySelectorAll('#m-tbodyDetalle tr.row-detalle').length > 0;
            btnPdf.disabled = !(tieneCliente && tieneItems);
            btnPdf.title = btnPdf.disabled ? 'Se requiere cliente e ítems para generar el PDF' : 'Exportar PDF';
        }
        if (btnXml) {
            const tieneCliente = !!(document.getElementById('m-id-cliente')?.value);
            const tieneItems = document.querySelectorAll('#m-tbodyDetalle tr.row-detalle').length > 0;
            btnXml.disabled = !(tieneCliente && tieneItems);
            btnXml.title = btnXml.disabled ? 'Se requiere cliente e ítems para exportar XML' : 'Exportar XML';
        }

        // ”€”€ Lógica de Solo Lectura para la pestaña Factura de Venta ”€”€
        const esBorrador = st === 'borrador';
        const tabDetalle = document.getElementById('m-tab-detalle');
        if (tabDetalle) {
            // Deshabilitar inputs, selects y textareas
            const controles = tabDetalle.querySelectorAll('input:not([type="hidden"]), select, textarea');
            controles.forEach(el => {
                if (el.id === 'm-select-vendedor') {
                    el.disabled = false;
                } else {
                    el.disabled = !esBorrador;
                }
            });

            // Ocultar botones de agregar y eliminar (Productos, Info Adicional, Pagos)
            const btnAddProd = tabDetalle.querySelector('button[onclick="agregarFila()"]');
            if (btnAddProd) btnAddProd.classList.toggle('d-none', !esBorrador);

            const btnAddInfo = tabDetalle.querySelector('button[onclick="agregarInfoAdicional()"]');
            if (btnAddInfo) btnAddInfo.classList.toggle('d-none', !esBorrador);

            const btnAddPago = tabDetalle.querySelector('button[onclick="agregarFormaPago()"]');
            if (btnAddPago) btnAddPago.classList.toggle('d-none', !esBorrador);

            // Ocultar cualquier botón con clase text-danger (basureros de eliminación de filas)
            const trashBtns = tabDetalle.querySelectorAll('button.text-danger, button.btn-outline-danger');
            trashBtns.forEach(btn => {
                if (btn.id !== 'm-btn-pdf') {
                    btn.classList.toggle('d-none', !esBorrador);
                }
            });

            // Deshabilitar botones de selección de métodos de pago (ej. botón de "Transferencia")
            const paymentBtns = tabDetalle.querySelectorAll('#m-container-pagos button:not(.text-danger)');
            paymentBtns.forEach(btn => {
                btn.disabled = !esBorrador;
            });
        }
    }

    /**
     * Limpia las pestañas de trazabilidad (Cobros, SRI, Auditoría).
     */
    function fvLimpiarTrazabilidad() {
        // Historial de Cobros
        const tbodyPagos = document.getElementById('fvPagoTbodyHistorial');
        if (tbodyPagos) tbodyPagos.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted"><i class="bi bi-info-circle me-1"></i> No se han registrado cobros para esta factura.</td></tr>';

        const elTotalFact = document.getElementById('fvPagoTotalFactura');
        if (elTotalFact) elTotalFact.textContent = '0.00';
        const elTotalCob = document.getElementById('fvPagoTotalCobrado');
        if (elTotalCob) elTotalCob.textContent = '0.00';
        const elTotalRet = document.getElementById('fvPagoTotalRetenciones');
        if (elTotalRet) elTotalRet.textContent = '0.00';
        const elTotalNC = document.getElementById('fvPagoTotalNC');
        if (elTotalNC) elTotalNC.textContent = '0.00';
        const elSaldo = document.getElementById('fvPagoSaldoPendiente');
        if (elSaldo) elSaldo.textContent = '0.00';

        const cardReg = document.getElementById('fvPagoCardRegistro');
        if (cardReg) cardReg.classList.add('d-none');
        const alertPag = document.getElementById('fvPagoAlertaPagada');
        if (alertPag) alertPag.classList.add('d-none');
        const alertNueva = document.getElementById('fvPagoAlertaNueva');
        if (alertNueva) alertNueva.classList.remove('d-none');
        const elSec = document.getElementById('fvPagoSecuencial');
        if (elSec) elSec.value = '';
        _fvSaldoPendiente    = 0;
        _fvIngresoDepsCargas = false; // Forzar recarga de catálogos al cambiar factura

        // Mensajes y Historial SRI
        const msgSri = document.getElementById('sri-mensajes-container');
        if (msgSri) msgSri.innerHTML = '<p class="text-muted text-center mb-0 py-3 small">Sin respuesta del SRI registrada.</p>';

        const histSri = document.getElementById('sri-tbody-historial');
        if (histSri) histSri.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-muted">Sin historial de envíos.</td></tr>';

        // Campos de anulación SRI
        const _nroDoc = document.getElementById('sri-numero-documento');
        if (_nroDoc) _nroDoc.value = '';
        const _identif = document.getElementById('sri-identificacion-cliente');
        if (_identif) _identif.value = '';
        const _correo = document.getElementById('sri-correo-cliente');
        if (_correo) _correo.value = '';

        // Timeline de Auditoría
        const containerAuditoria = document.getElementById('auditoriaTimelineVenta');
        if (containerAuditoria) containerAuditoria.innerHTML = '<div class="text-center py-4 text-muted small">No hay historial de cambios.</div>';

        // Etiquetas de Auditoría
        if (document.getElementById('m-lbl-creado-por')) document.getElementById('m-lbl-creado-por').textContent = '...';
        if (document.getElementById('m-lbl-actualizado-por')) document.getElementById('m-lbl-actualizado-por').textContent = '...';
    }

    function fvResetearModal() {
        window.ASIENTO_MANUAL = false;
        document.getElementById('formFacturaModal').reset();
        document.getElementById('m-tbodyDetalle').innerHTML = '';
        document.getElementById('m-id-cliente').value = '';
        const _tipoCl = document.getElementById('m-tipo-id-cliente');
        const _nomTipoCl = document.getElementById('m-nombre-tipo-id-cliente');
        if (_tipoCl) _tipoCl.value = '';
        if (_nomTipoCl) _nomTipoCl.value = '';
        document.getElementById('m-info-cliente').classList.add('d-none');
        document.getElementById('m-dropdown-clientes').classList.add('d-none');
        document.getElementById('m-search-cliente').value = '';

        document.getElementById('m-tbody-info-adicional').innerHTML = '';
        agregarInfoAdicional();
        insertarInfoCajero();
        actualizarInfoVendedor(null);

        const triggerEl = document.querySelector('#tab-fv-venta-btn');
        if (triggerEl) {
            bootstrap.Tab.getInstance(triggerEl)?.show() || new bootstrap.Tab(triggerEl).show();
        }

        agregarFila();
        calcTotales();

        // Solo mostrar la pestaña de Venta en nueva factura
        document.querySelectorAll('#tabsFacturaVenta .nav-item').forEach((li, idx) => {
            if (idx === 0) li.classList.remove('d-none'); // Venta
            else li.classList.add('d-none'); // Otros
        });

        const selectPto = document.getElementById('m-select-puntos');
        if (selectPto && selectPto.value) {
            const opt = selectPto.options[selectPto.selectedIndex];
            if (opt) document.getElementById('m-id-establecimiento').value = opt.dataset.est || '';
        }
        const inputSec = document.getElementById('m-input-secuencial');
        if (inputSec) {
            inputSec.value = '';
            inputSec.placeholder = 'Cargando...';
        }

        // Limpiar badge de estado del header del modal
        const _badgeEstadoModal = document.getElementById('fv-badge-estado-modal');
        if (_badgeEstadoModal) { _badgeEstadoModal.className = 'badge d-none ms-2'; _badgeEstadoModal.textContent = ''; }

        // Limpiar estado de factura activa y controles del footer
        FV_ID_ACTIVO = 0;
        const _btnElim = document.getElementById('btnEliminarFacturaModal');
        const _btnGuardar = document.getElementById('btnGuardarFacturaModal');
        if (_btnElim) {
            _btnElim.classList.add('d-none');
            _btnElim.disabled = false;
            _btnElim.innerHTML = '<i class="bi bi-trash3 me-1"></i> Eliminar borrador';
        }
        if (_btnGuardar) {
            _btnGuardar.classList.remove('d-none');
            _btnGuardar.disabled = false;
        }

        // Resetear campos SRI
        const badgeSri = document.getElementById('sri-badge-estado');
        if (badgeSri) {
            badgeSri.className = 'badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2';
            badgeSri.textContent = 'Sin enviar';
        }
        const fieldsSri = ['sri-clave-acceso', 'sri-ambiente', 'sri-tipo-emision', 'sri-numero-autorizacion', 'sri-fecha-autorizacion'];
        fieldsSri.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });

        // Limpiar trazabilidad (Cobros, Historial SRI, Auditoría)
        fvLimpiarTrazabilidad();

        // Restablecer título por defecto
        const tituloEl = document.querySelector('#modalNuevaFactura .modal-title');
        if (tituloEl) tituloEl.innerHTML = '<i class="bi bi-receipt-cutoff me-2"></i>Nueva factura de venta';

        // Actualizar estado de botones superiores
        fvActualizarEstadoBotones('nuevo');
    }

    function abrirModalFactura() {
        // Nueva factura: asegurar ID en cero y permitir carga normal del consecutivo
        FV_ID_ACTIVO = 0;
        FV_FECHA_EMISION = null;
        FV_CLIENTE_RUC   = '';
        FV_BLOQUEAR_SECUENCIAL = false;

        // Verificar si hay un borrador guardado
        let borrador = null;
        try {
            const raw = localStorage.getItem(FV_STORAGE_KEY);
            if (raw) borrador = JSON.parse(raw);
        } catch (e) {}

        if (borrador && (borrador.id_cliente || borrador.detalles.length)) {
            const divAviso = document.createElement('div');
            divAviso.id = 'fv-borrador-aviso';
            divAviso.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.45);z-index:9999;display:flex;align-items:center;justify-content:center;';
            const clienteName = borrador.search_cliente || 'desconocido';
            divAviso.innerHTML = `
                <div class="bg-white rounded-3 shadow-lg p-4" style="max-width:420px;width:90%;">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <i class="bi bi-exclamation-triangle-fill text-warning fs-4"></i>
                        <h6 class="fw-bold mb-0">Factura sin guardar</h6>
                    </div>
                    <p class="small text-muted mb-4">Hay una factura en borrador del cliente <strong>${clienteName}</strong> que no fue guardada. ¿Qué desea hacer?</p>
                    <div class="d-flex gap-2 justify-content-end">
                        <button class="btn btn-sm btn-outline-secondary" id="fv-aviso-nueva">
                            <i class="bi bi-file-earmark-plus me-1"></i> Nueva factura
                        </button>
                        <button class="btn btn-sm btn-primary" id="fv-aviso-restaurar">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Cargar borrador
                        </button>
                    </div>
                </div>`;
            document.body.appendChild(divAviso);
            document.getElementById('fv-aviso-restaurar').onclick = () => {
                divAviso.remove();
                fvResetearModal();
                modalMain.show();
                document.getElementById('modalNuevaFactura').addEventListener('shown.bs.modal', function onShown() {
                    fvRestaurar(borrador);
                    this.removeEventListener('shown.bs.modal', onShown);
                });
            };
            document.getElementById('fv-aviso-nueva').onclick = () => {
                fvLimpiarBorrador();
                divAviso.remove();
                fvResetearModal();
                modalMain.show();
            };
            return;
        }

        // Sin borrador: apertura normal
        fvResetearModal();
        modalMain.show();

        // Aplicar favoritos para nueva factura
        if (typeof aplicarFavoritosModal === 'function') {
            setTimeout(() => {
                aplicarFavoritosModal('#modalNuevaFactura');
            }, 150);
        }
        // cargarSecuencial se ejecuta en shown.bs.modal cuando el modal está visible
    }

    function syncSerie(idPunto) {
        if (!idPunto) return;
        const select = document.getElementById('m-select-puntos');
        const opt = select.options[select.selectedIndex];
        if (opt) {
            document.getElementById('m-id-establecimiento').value = opt.dataset.est || '';
        }
        cargarSecuencial(idPunto);
    }



    // --- ACCIONES BARRA ---
    async function enviarAlSri() {
        const id = parseInt(FV_ID_ACTIVO) || 0;
        if (!id) return;

        // Validar fecha de emisión antes de continuar
        const dateLocal = new Date();
        const hoy = new Date(dateLocal.getTime() - dateLocal.getTimezoneOffset() * 60000)
                        .toISOString().split('T')[0];
        if (FV_FECHA_EMISION && FV_FECHA_EMISION !== hoy) {
            const fechaFmt = FV_FECHA_EMISION.split('-').reverse().join('-');
            const hoyFmt   = hoy.split('-').reverse().join('-');
            await Swal.fire({
                icon: 'warning',
                title: 'Fecha de emisión incorrecta',
                html: `<p>El SRI solo acepta comprobantes cuya fecha de emisión sea <strong>la fecha actual</strong>.</p>
                       <p class="mb-1">Fecha del documento: <code>${fechaFmt}</code></p>
                       <p class="mb-0">Fecha actual: <code>${hoyFmt}</code></p>
                       <hr class="my-2">
                       <small class="text-muted">Edita el comprobante, actualiza la fecha de emisión a hoy y vuelve a intentarlo.</small>`,
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#f39c12',
            });
            return;
        }

        const confirmar = await Swal.fire({
            icon: 'question',
            title: 'Enviar al SRI',
            html: 'Se firmará el comprobante con el certificado de la empresa y se enviará al SRI para su autorización.<br><small class="text-muted">Este proceso puede tardar unos segundos.</small>',
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-cloud-arrow-up me-1"></i> Enviar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#0d6efd',
        });
        if (!confirmar.isConfirmed) return;

        // Mostrar progreso
        Swal.fire({
            title: 'Enviando al SRI...',
            html: '<div class="spinner-border text-primary" role="status"></div><br><small class="text-muted mt-2 d-block">Firmando y enviando comprobante...</small>',
            allowOutsideClick: false,
            showConfirmButton: false,
        });

        try {
            const fd = new FormData();
            fd.append('id', id);

            const resp = await fetch(`${B_URL}/${RUTA_MODULO}/enviar-sri-ajax`, {
                method: 'POST',
                body: fd,
            });
            const json = await resp.json();

            // ── Actualizar estado inmediatamente ───────────────────────────────
            const nuevoEstado = (json.estado || (json.ok ? 'autorizado' : 'error')).toLowerCase();

            // 1. Pestaña SRI y badge del header del modal
            fvActualizarPestanhaSri({
                estado_sri:          nuevoEstado,
                numero_autorizacion: json.numero_autorizacion || '',
                fecha_autorizacion:  json.fecha_autorizacion  || '',
                mensajes_sri:        json.errores?.length ? JSON.stringify(json.errores) : null,
            });
            fvActualizarEstadoBotones(nuevoEstado);
            fvCargarHistorialSri(id);

            // 2. Actualizar la fila de la tabla de fondo de forma síncrona
            //    para que data-row tenga el estado correcto ANTES de que el usuario
            //    pueda volver a abrirla (evita que el SRI button se reactive).
            (function fvActualizarFilaTabla(idVenta, estado) {
                const clsMap = {
                    'autorizado':       'bg-success bg-opacity-10 text-success border-success',
                    'no_autorizado':    'bg-danger  bg-opacity-10 text-danger  border-danger',
                    'devuelta':         'bg-danger  bg-opacity-10 text-danger  border-danger',
                    'en_procesamiento': 'bg-warning bg-opacity-10 text-warning border-warning',
                    'anulado':          'bg-danger  bg-opacity-10 text-danger  border-danger',
                    'borrador':         'bg-secondary bg-opacity-10 text-secondary border-secondary',
                };
                const cls   = clsMap[estado] || 'bg-primary bg-opacity-10 text-primary border-primary';
                const label = estado.charAt(0).toUpperCase() + estado.slice(1).replace('_', ' ');

                document.querySelectorAll('tr.factura-row').forEach(row => {
                    try {
                        const d = JSON.parse(row.dataset.row || '{}');
                        if (parseInt(d.id) !== idVenta) return;

                        // Actualizar data-row para que abrirModalFacturaVer reciba el estado correcto
                        d.estado = estado;
                        row.dataset.row = JSON.stringify(d);

                        // Actualizar badge visible en la celda
                        const tdEstado = row.querySelector('td[data-col="estado"]');
                        if (tdEstado) {
                            tdEstado.innerHTML =
                                `<span class="badge ${cls} border border-opacity-25">${label}</span>`;
                        }
                    } catch (e) { /* fila sin data-row válido */ }
                });
            })(id, nuevoEstado);

            if (json.ok) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Autorizado!',
                    html: `<p>${json.mensaje}</p><code class="small">${json.numero_autorizacion || ''}</code>`,
                    confirmButtonColor: '#0d6efd',
                }).then(() => {
                    bootstrap.Modal.getInstance(document.getElementById('modalNuevaFactura')).hide();
                    // Refresco completo de la tabla al cerrar el modal (datos frescos del servidor)
                    if (typeof window.FV_fetchSearch === 'function') {
                        window.FV_fetchSearch(window.FV_currentPage || 1);
                    }
                });
            } else {
                let errHtml = `<p class="text-danger">${json.mensaje || 'Error al enviar.'}</p>`;
                if (json.errores?.length) {
                    errHtml += '<ul class="text-start small mt-2">';
                    json.errores.forEach(e => {
                        errHtml += `<li><strong>[${e.tipo || 'ERROR'}]</strong> ${e.mensaje || ''}`;
                        if (e.info) errHtml += `<br><small class="text-muted">${e.info}</small>`;
                        errHtml += '</li>';
                    });
                    errHtml += '</ul>';
                }
                Swal.fire({
                    icon: 'error',
                    title: 'No autorizado',
                    html: errHtml,
                    confirmButtonColor: '#dc3545',
                });
            }
        } catch (err) {
            fvCargarHistorialSri(id);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudo conectar con el servidor.'
            });
        }
    }

    /**
     * Actualiza los campos de la pestaña SRI con los datos del comprobante.
     * Llama también desde abrirModalFacturaVer para rellenar al abrir.
     */
    function fvActualizarPestanhaSri(data = {}) {
        // Badge de estado
        const badge = document.getElementById('sri-badge-estado');
        if (badge) {
            const estado = (data.estado_sri || data.estado || 'pendiente').toLowerCase();
            const map = {
                'autorizado': ['bg-success bg-opacity-10 text-success border-success', 'Autorizado'],
                'no_autorizado': ['bg-danger bg-opacity-10 text-danger border-danger', 'No autorizado'],
                'devuelta': ['bg-danger bg-opacity-10 text-danger border-danger', 'Devuelta'],
                'en_procesamiento': ['bg-warning bg-opacity-10 text-warning border-warning', 'En procesamiento'],
                'recibida': ['bg-info bg-opacity-10 text-info border-info', 'Recibida'],
                'enviando': ['bg-primary bg-opacity-10 text-primary border-primary', 'Enviando€'],
                'error': ['bg-danger bg-opacity-10 text-danger border-danger', 'Error'],
                'pendiente': ['bg-secondary bg-opacity-10 text-secondary border-secondary', 'Sin enviar'],
            };
            const [cls, lbl] = map[estado] ?? map['pendiente'];
            badge.className = `badge ${cls} border border-opacity-25 px-2`;
            badge.textContent = lbl;
        }

        // Número de autorización
        const elAut = document.getElementById('sri-numero-autorizacion');
        if (elAut && data.numero_autorizacion != null) elAut.value = data.numero_autorizacion;

        // Fecha de autorización
        const elFecha = document.getElementById('sri-fecha-autorizacion');
        if (elFecha && data.fecha_autorizacion) {
            try {
                const d = new Date(data.fecha_autorizacion);
                elFecha.value = isNaN(d) ? data.fecha_autorizacion :
                    d.toLocaleDateString('es-EC', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
            } catch {
                elFecha.value = data.fecha_autorizacion;
            }
        }

        // Mensajes del SRI
        const msgBox = document.getElementById('sri-mensajes-container');
        if (msgBox) {
            let mensajes = [];
            if (data.mensajes_sri) {
                try {
                    mensajes = JSON.parse(data.mensajes_sri);
                } catch {
                    mensajes = [];
                }
            }
            if (mensajes.length > 0) {
                msgBox.innerHTML = mensajes.map(m => {
                    const tipo = (m.tipo || 'INFO').toUpperCase();
                    const cls = tipo === 'ERROR' ? 'text-danger' : tipo === 'ADVERTENCIA' ? 'text-warning' : 'text-info';
                    return `<div class="mb-1 ${cls}"><strong>[${tipo}]</strong> ${m.mensaje || ''}${m.info ? `<br><small class="text-muted">${m.info}</small>` : ''}</div>`;
                }).join('');
            } else {
                msgBox.innerHTML = '<p class="text-muted text-center mb-0 py-3 small">Sin respuesta del SRI registrada.</p>';
            }
        }
    }

    // ”€”€ Historial SRI ”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€

    async function fvCargarHistorialSri(id) {
        const tbody = document.getElementById('sri-tbody-historial');
        if (!tbody || !id) return;

        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-2 text-muted small"><span class="spinner-border spinner-border-sm me-1"></span>Cargando...</td></tr>';

        try {
            const resp = await fetch(`${B_URL}/${RUTA_MODULO}/getHistorialSriAjax?id=${id}&tipo=factura_venta`);
            const json = await resp.json();

            if (!json.ok || !json.data.length) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-muted small">Sin historial de envíos.</td></tr>';
                return;
            }

            const accionMap = {
                'enviando': ['bg-primary', 'bi-cloud-arrow-up', 'Enviando'],
                'recibida': ['bg-info', 'bi-check-circle', 'Recibida'],
                'devuelta': ['bg-danger', 'bi-x-circle', 'Devuelta'],
                'autorizado': ['bg-success', 'bi-patch-check-fill', 'Autorizado'],
                'no_autorizado': ['bg-danger', 'bi-patch-minus', 'No autorizado'],
                'en_procesamiento': ['bg-warning', 'bi-hourglass-split', 'En proceso'],
                'error': ['bg-danger', 'bi-exclamation-triangle', 'Error'],
            };

            tbody.innerHTML = json.data.map(row => {
                const [bgCls, icon, lbl] = accionMap[row.accion] ?? ['bg-secondary', 'bi-question', row.accion];
                const esPruebas = row.tipo_ambiente === '1';
                const ambienteLbl = esPruebas ? '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25" style="font-size:0.65rem;">PRUEBAS</span>' :
                    '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25" style="font-size:0.65rem;">PRODUCCI“N</span>';

                // Detalle: mensaje + errores del json
                let detalle = row.mensaje || '';
                if (row.detalle_json) {
                    try {
                        const errs = JSON.parse(row.detalle_json);
                        if (Array.isArray(errs) && errs.length) {
                            detalle += '<ul class="mb-0 ps-3 mt-1" style="font-size:0.7rem;">';
                            errs.forEach(e => {
                                detalle += `<li><strong>[${e.tipo||e.id||''}]</strong> ${e.mensaje||''} ${e.info ? '<br><em class="text-muted">'+e.info+'</em>' : ''}</li>`;
                            });
                            detalle += '</ul>';
                        }
                    } catch (e) {}
                }
                if (row.numero_autorizacion && row.accion === 'autorizado') {
                    detalle += `<div class="font-monospace mt-1" style="font-size:0.65rem;word-break:break-all;">${row.numero_autorizacion}</div>`;
                }

                return `<tr>
                    <td class="ps-2 py-1 text-nowrap" style="font-size:0.72rem;">${row.created_at}</td>
                    <td class="py-1">${ambienteLbl}</td>
                    <td class="py-1"><span class="badge ${bgCls} bg-opacity-10 text-${bgCls.replace('bg-','')} border border-${bgCls.replace('bg-','')} border-opacity-25" style="font-size:0.65rem;"><i class="bi ${icon} me-1"></i>${lbl}</span></td>
                    <td class="py-1" style="font-size:0.72rem;">${detalle}</td>
                </tr>`;
            }).join('');

        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-2 text-danger small">Error al cargar historial.</td></tr>';
        }
    }

    async function fvEliminarLogSri(idLog) {
        const confirm = await Swal.fire({
            title: '¿Eliminar registro?',
            text: 'Se eliminará este registro del historial de envíos (solo disponible en ambiente de pruebas).',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
        });
        if (!confirm.isConfirmed) return;

        try {
            const fd = new FormData();
            fd.append('id', idLog);
            const resp = await fetch(`${B_URL}/${RUTA_MODULO}/eliminarLogSriAjax`, {
                method: 'POST',
                body: fd
            });
            const json = await resp.json();
            if (json.ok) {
                fvCargarHistorialSri(FV_ID_ACTIVO);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: json.mensaje || 'No se pudo eliminar.'
                });
            }
        } catch (e) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error de conexión.'
            });
        }
    }

    // ”€”€ fin Historial SRI ”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€

    function duplicarFactura() {
        if (!FV_ID_ACTIVO) return;

        // 1. Resetear ID para que sea una nueva factura
        FV_ID_ACTIVO = 0;

        // 2. Permitir carga de secuencial para la nueva factura
        FV_BLOQUEAR_SECUENCIAL = false;

        // 3. Cambiar fecha a hoy
        const fechaInput = document.querySelector('#formFacturaModal [name="fecha_emision"]');
        if (fechaInput) {
            const hoy = new Date().toISOString().split('T')[0];
            fechaInput.value = hoy;
        }

        // 3. Cambiar título del modal para reflejar duplicación
        const tituloEl = document.querySelector('#modalNuevaFactura .modal-title');
        if (tituloEl) {
            const actual = tituloEl.textContent.split(':').pop().trim();
            tituloEl.innerHTML = `<i class="bi bi-copy me-2 text-warning"></i>Nueva factura <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 ms-2">Duplicada de ${actual}</span>`;
        }

        // 4. Cargar nuevo secuencial para el punto de emisión seleccionado
        const selPunto = document.getElementById('m-select-puntos');
        if (selPunto && selPunto.value) {
            cargarSecuencial(selPunto.value);
        }

        // 5. Ocultar botón eliminar y mostrar botón guardar
        const _btnElim = document.getElementById('btnEliminarFacturaModal');
        const _btnGuardar = document.getElementById('btnGuardarFacturaModal');
        if (_btnElim) _btnElim.classList.add('d-none');
        if (_btnGuardar) _btnGuardar.classList.remove('d-none');

        // 6. Resetear campos SRI y Trazabilidad Completa
        const fieldsSri = ['sri-clave-acceso', 'sri-numero-autorizacion', 'sri-fecha-autorizacion'];
        fieldsSri.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        const badgeSri = document.getElementById('sri-badge-estado');
        if (badgeSri) {
            badgeSri.className = 'badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2';
            badgeSri.textContent = 'Sin enviar';
        }

        fvLimpiarTrazabilidad();

        // 7. Forzar cambio a pestaña de detalle
        const triggerEl = document.querySelector('#tab-fv-venta-btn');
        if (triggerEl) {
            bootstrap.Tab.getInstance(triggerEl)?.show() || new bootstrap.Tab(triggerEl).show();
        }

        // 8. Actualizar estado de botones (como es "Nueva", se desactivan hasta guardar)
        fvActualizarEstadoBotones('nuevo');

        // Notificación suave opcional (puedes quitar el alert si prefieres)
        // alert('Factura lista para ser guardada como una nueva duplicación.');
    }

    function exportarPdf() {
        const id = parseInt(FV_ID_ACTIVO) || 0;
        if (!id) return;
        const url = `${B_URL}/${RUTA_MODULO}/exportar-pdf-ajax?id=${id}`;
        window.open(url, '_blank');
    }

    function exportarXml() {
        const id = parseInt(FV_ID_ACTIVO) || 0;
        if (!id) return;
        const url = `${B_URL}/${RUTA_MODULO}/exportar-xml-ajax?id=${id}`;
        window.open(url, '_blank');
    }

    async function imprimirTicket() {
        const id = parseInt(FV_ID_ACTIVO) || 0;
        if (!id) return;

        try {
            const resp = await fetch(`${B_URL}/${RUTA_MODULO}/getFacturaAjax?id=${id}`);
            const json = await resp.json();
            if (!json.ok) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: json.error || 'No se pudo cargar la factura.'
                });
                return;
            }

            const cab = json.cabecera;
            const detalles = json.detalles || [];
            const pagos = json.pagos || [];

            const num = `${cab.establecimiento || '000'}-${cab.punto_emision || '000'}-${String(cab.secuencial || '').padStart(9, '0')}`;
            const fecha = cab.fecha_emision ? (() => {
                const d = new Date(cab.fecha_emision);
                return isNaN(d) ? cab.fecha_emision : d.toLocaleDateString('es-EC', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric'
                });
            })() : '';

            const fmt = (n) => parseFloat(n || 0).toFixed(2);
            const esc = (s) => String(s || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');

            // Calcular totales
            let subtotal = 0,
                totalIva = 0,
                totalIce = 0,
                totalDescuento = 0;
            const impMap = {};
            detalles.forEach(d => {
                subtotal += parseFloat(d.precio_total_sin_impuesto || 0);
                totalDescuento += parseFloat(d.descuento || 0);
                (d.impuestos || []).forEach(imp => {
                    const lbl = `IVA ${parseFloat(imp.tarifa||0).toFixed(0)}%`;
                    impMap[lbl] = (impMap[lbl] || 0) + parseFloat(imp.valor || 0);
                    if (String(imp.codigo_impuesto) === '3') totalIce += parseFloat(imp.valor || 0);
                });
            });
            Object.values(impMap).forEach(v => totalIva += v);
            const total = subtotal + totalIva + totalIce + parseFloat(cab.propina || 0);

            const logoHtml = EMPRESA_INFO.logo ?
                `<img src="${B_URL}/${EMPRESA_INFO.logo}" style="max-width:120px;max-height:60px;margin-bottom:4px;">` :
                '';

            const lineas = detalles.map(d => {
                const cant = parseFloat(d.cantidad || 1);
                const pu = parseFloat(d.precio_unitario || 0);
                const desc = parseFloat(d.descuento || 0);
                const tot = parseFloat(d.precio_total_sin_impuesto || 0);
                const ivaPct = (d.impuestos && d.impuestos[0]) ? parseFloat(d.impuestos[0].tarifa || 0).toFixed(0) : '0';
                return `<tr>
                    <td colspan="2" style="padding:1px 0;">${esc(d.descripcion)}</td>
                </tr>
                <tr>
                    <td style="padding:1px 0;color:#555;">${fmt(cant)} x $${fmt(pu)}${desc > 0 ? ` desc.$${fmt(desc)}` : ''} (IVA ${ivaPct}%)</td>
                    <td style="padding:1px 0;text-align:right;font-weight:bold;">$${fmt(tot)}</td>
                </tr>`;
            }).join('<tr><td colspan="2"><hr style="margin:2px 0;border-color:#ccc;"></td></tr>');

            const ivaLineas = Object.entries(impMap).map(([lbl, val]) =>
                `<tr><td>${lbl}</td><td style="text-align:right;">$${fmt(val)}</td></tr>`
            ).join('');

            const pagoLineas = pagos.map(p =>
                `<tr><td>${esc(p.forma_pago || 'Efectivo')}</td><td style="text-align:right;">$${fmt(p.total)}</td></tr>`
            ).join('');

            const html = `<!DOCTYPE html><html lang="es"><head>
                <meta charset="UTF-8">
                <title>Ticket - ${esc(num)}</title>
                <style>
                    @page { size: 80mm auto; margin: 3mm; }
                    * { box-sizing: border-box; }
                    body { font-family: 'Courier New', Courier, monospace; font-size: 9px; width: 74mm; margin: 0; padding: 0; color: #000; }
                    .center { text-align: center; }
                    .bold { font-weight: bold; }
                    .sep { border: none; border-top: 1px dashed #000; margin: 3px 0; }
                    table { width: 100%; border-collapse: collapse; }
                    td { vertical-align: top; font-size: 9px; }
                    .totales td { padding: 1px 0; }
                    .totales tr:last-child td { font-weight: bold; font-size: 10px; }
                    h2 { font-size: 11px; margin: 2px 0; }
                    h3 { font-size: 9px; margin: 1px 0; font-weight: normal; }
                    @media print { body { width: 74mm; } button { display: none; } }
                </style>
            </head><body>
                <div class="center">
                    ${logoHtml}
                    <h2>${esc(EMPRESA_INFO.nombre_comercial || EMPRESA_INFO.nombre)}</h2>
                    <h3>RUC: ${esc(EMPRESA_INFO.ruc)}</h3>
                    ${EMPRESA_INFO.direccion ? `<h3>${esc(EMPRESA_INFO.direccion)}</h3>` : ''}
                    ${EMPRESA_INFO.telefono  ? `<h3>Tel: ${esc(EMPRESA_INFO.telefono)}</h3>` : ''}
                </div>
                <hr class="sep">
                <div class="center bold" style="font-size:10px;">FACTURA DE VENTA</div>
                <div class="center">No. ${esc(num)}</div>
                <div class="center">Fecha: ${esc(fecha)}</div>
                <hr class="sep">
                <table>
                    <tr><td class="bold">Cliente:</td><td>${esc(cab.cliente_nombre)}</td></tr>
                    <tr><td class="bold">RUC/CI:</td><td>${esc(cab.cliente_ruc)}</td></tr>
                    ${cab.cliente_direccion ? `<tr><td class="bold">Dir:</td><td>${esc(cab.cliente_direccion)}</td></tr>` : ''}
                </table>
                <hr class="sep">
                <table><tbody>${lineas}</tbody></table>
                <hr class="sep">
                <table class="totales">
                    <tr><td>Subtotal sin imp.</td><td style="text-align:right;">$${fmt(subtotal)}</td></tr>
                    ${totalDescuento > 0 ? `<tr><td>Descuento</td><td style="text-align:right;">-$${fmt(totalDescuento)}</td></tr>` : ''}
                    ${ivaLineas}
                    ${totalIce > 0 ? `<tr><td>ICE</td><td style="text-align:right;">$${fmt(totalIce)}</td></tr>` : ''}
                    ${parseFloat(cab.propina||0) > 0 ? `<tr><td>Propina</td><td style="text-align:right;">$${fmt(cab.propina)}</td></tr>` : ''}
                    <tr><td>TOTAL</td><td style="text-align:right;">$${fmt(total)}</td></tr>
                </table>
                ${pagos.length ? `<hr class="sep"><div class="bold" style="font-size:9px;">FORMA DE PAGO</div><table class="totales">${pagoLineas}</table>` : ''}
                ${cab.observaciones ? `<hr class="sep"><div style="font-size:8px;">${esc(cab.observaciones)}</div>` : ''}
                <hr class="sep">
                <div class="center" style="font-size:8px;">¡Gracias por su compra!</div>
                <br><br>
                <script>window.onload=function(){window.print();window.onafterprint=function(){window.close();};};<\/script>
            </body></html>`;

            const win = window.open('', '_blank', 'width=320,height=600,scrollbars=yes');
            if (!win) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Bloqueado',
                    text: 'Permite ventanas emergentes para imprimir el ticket.'
                });
                return;
            }
            win.document.write(html);
            win.document.close();
        } catch (e) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudo generar el ticket.'
            });
        }
    }

    async function enviarPorCorreo() {
        const id = parseInt(FV_ID_ACTIVO) || 0;
        if (!id) return;

        // Obtener el correo actual del cliente de la interfaz
        const mailLbl = document.getElementById('m-lbl-cliente-correo');
        const correoActual = mailLbl && mailLbl.textContent !== 'Sin correo registrado' ? mailLbl.textContent : '';

        const { value: correos, isConfirmed } = await Swal.fire({
            title: 'Enviar por correo',
            input: 'text',
            inputLabel: 'Correos electrónicos (separados por coma)',
            inputValue: correoActual,
            target: document.getElementById('modalNuevaFactura'),
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-send me-1"></i> Enviar',
            cancelButtonText: 'Cancelar',
            inputValidator: (value) => {
                if (!value.trim()) {
                    return 'Debes ingresar al menos un correo válido!';
                }
            }
        });

        if (!isConfirmed) return;

        // Mostrar Swal de carga
        Swal.fire({
            title: 'Enviando correo...',
            text: 'Por favor espera',
            allowOutsideClick: false,
            target: document.getElementById('modalNuevaFactura'),
            didOpen: () => {
                Swal.showLoading();
            }
        });

        try {
            const fd = new FormData();
            fd.append('id', id);
            fd.append('correos', correos);

            const resp = await fetch(`${B_URL}/${RUTA_MODULO}/reenviarCorreoAjax`, {
                method: 'POST',
                body: fd
            });
            const textResponse = await resp.text();
            
            let json;
            try {
                json = JSON.parse(textResponse);
            } catch(err) {
                console.error("RAW RESPONSE:", textResponse);
                Swal.fire({
                    icon: 'error',
                    title: 'Respuesta inválida',
                    html: '<pre style="text-align:left; max-height:200px; overflow:auto;">' + textResponse.substring(0, 500) + '</pre>',
                    target: document.getElementById('modalNuevaFactura')
                });
                return;
            }

            if (json.ok) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Enviado!',
                    text: json.mensaje,
                    timer: 2500,
                    showConfirmButton: false
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: json.mensaje || 'No se pudo enviar el correo.'
                });
            }
        } catch (e) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error de conexión al enviar el correo.'
            });
        }
    }

    // --- CLIENTE SEARCH INLINE ---
    const inputSearchCliente = document.getElementById('m-search-cliente');
    const dropdownClientes = document.getElementById('m-dropdown-clientes');

    if (inputSearchCliente) {
        // Backspace / Delete limpia el cliente seleccionado
        inputSearchCliente.addEventListener('keydown', (e) => {
            const idInput = document.getElementById('m-id-cliente');
            if ((e.key === 'Backspace' || e.key === 'Delete') && idInput && idInput.value) {
                idInput.value = '';
                inputSearchCliente.value = '';
                document.getElementById('m-info-cliente').classList.add('d-none');


                dropdownClientes.classList.add('d-none');
                actualizarInfoCorreoCliente('');
                e.preventDefault();
            }
        });

        inputSearchCliente.addEventListener('input', debounce(async (e) => {
            const q = e.target.value.trim();
            if (q.length < 2) {
                dropdownClientes.classList.add('d-none');
                return;
            }

            try {
                const resp = await fetch(`${B_URL}/${RUTA_MODULO}/getClientesAjax?q=${encodeURIComponent(q)}`);
                const json = await resp.json();

                dropdownClientes.innerHTML = '';
                if (json.data && json.data.length > 0) {
                    json.data.forEach(c => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'list-group-item list-group-item-action py-2 border-start-0 border-end-0';
                        btn.innerHTML = `
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-bold small text-primary">${c.nombre}</span>
                                <span class="badge bg-light text-dark border x-small">${c.identificacion}</span>
                            </div>
                            <div class="x-small text-muted text-truncate"><i class="bi bi-geo-alt me-1"></i>${c.direccion || 'Sin dirección'}</div>
                        `;
                        // Usamos mousedown para que se ejecute antes del click global que cierra el dropdown
                        btn.onmousedown = (evt) => {
                            evt.preventDefault();
                            seleccionarCliente(c);
                        };
                        dropdownClientes.appendChild(btn);
                    });
                    dropdownClientes.classList.remove('d-none');
                } else {
                    dropdownClientes.innerHTML = '<div class="list-group-item small text-muted">No se encontraron resultados</div>';
                    dropdownClientes.classList.remove('d-none');
                }
            } catch (err) {
                console.error('Error al buscar clientes', err);
            }
        }, 300));
    }

    function seleccionarCliente(c) {
        const idInput = document.getElementById('m-id-cliente');
        const searchInput = document.getElementById('m-search-cliente');
        const infoBar = document.getElementById('m-info-cliente');
        const rucLbl = document.getElementById('m-lbl-cliente-ruc');
        const dirLbl = document.getElementById('m-lbl-cliente-direccion');
        const mailLbl = document.getElementById('m-lbl-cliente-correo');

        if (idInput) idInput.value = c.id || '';
        if (searchInput) searchInput.value = c.nombre || '';

        // Guardar tipo_id para validar límite consumidor final al facturar
        const tipoIdInput = document.getElementById('m-tipo-id-cliente');
        const nombreTipoIdInput = document.getElementById('m-nombre-tipo-id-cliente');
        if (tipoIdInput) tipoIdInput.value = c.tipo_id || '';
        if (nombreTipoIdInput) nombreTipoIdInput.value = c.nombre_tipo_id || '';

        if (rucLbl) rucLbl.textContent = c.identificacion || '';
        if (dirLbl) dirLbl.textContent = c.direccion || 'No especificada';
        if (mailLbl) mailLbl.textContent = c.email || 'Sin correo registrado';
        if (infoBar) infoBar.classList.remove('d-none');

        // Insertar o actualizar fila de correo en info adicional
        actualizarInfoCorreoCliente(c.email || '');

        // Autocompletar datos del cliente
        const selVendedor = document.getElementById('m-select-vendedor');
        const inDiasCredito = document.getElementById('m-input-dias-credito');

        if (selVendedor && c.id_vendedor) {
            selVendedor.value = c.id_vendedor;
            actualizarInfoVendedor(selVendedor);
        }
        if (inDiasCredito) inDiasCredito.value = c.plazo || 0;


        if (dropdownClientes) dropdownClientes.classList.add('d-none');

        // Seleccionar la forma de pago SRI predeterminada desde el cliente o empresa
        const idFpSri = c.id_forma_pago_sri || EMPRESA_CONFIG.id_forma_pago_sri_def;
        if (idFpSri) {
            const selectPagoSri = document.querySelector('select[name="f_pago_id[]"]');
            if (selectPagoSri) {
                const targetId = String(idFpSri);
                for (let i = 0; i < selectPagoSri.options.length; i++) {
                    if (selectPagoSri.options[i].getAttribute('data-id') === targetId || selectPagoSri.options[i].value === targetId) {
                        selectPagoSri.selectedIndex = i;
                        selectPagoSri.dispatchEvent(new Event('change'));
                        break;
                    }
                }
            }
        }

        // Pasar el cursor al buscador de productos en la primera fila
        setTimeout(() => {
            const firstProductInput = document.querySelector('#m-tbodyDetalle tr:first-child .input-descripcion');
            if (firstProductInput) {
                firstProductInput.focus();
            }
        }, 100);
    }

    function editarClienteActual() {
        const id = document.getElementById('m-id-cliente').value;
        if (!id) return;
        Swal.fire({
            icon: 'info',
            title: 'Editar Cliente',
            text: 'Redirigiendo a edición de cliente ID: ' + id,
            confirmButtonColor: '#0d6efd'
        });
    }

    document.addEventListener('click', (e) => {
        if (inputSearchCliente && !inputSearchCliente.contains(e.target) && !dropdownClientes.contains(e.target)) {
            dropdownClientes.classList.add('d-none');
        }
    });

    function abrirModalCrearCliente() {
        Swal.fire({
            icon: 'info',
            title: 'Nuevo Cliente',
            text: 'Funcionalidad para crear cliente rápido estará disponible pronto.',
            confirmButtonColor: '#0d6efd'
        });
    }

    // --- ÍTEMS ---
    function agregarFila() {
        const tbody = document.getElementById('m-tbodyDetalle');
        const tr = document.createElement('tr');
        tr.className = 'row-detalle';
        tr.innerHTML = `
            <td class="ps-3 position-relative">
                <input type="text" class="form-control form-control-sm input-detalle input-descripcion" placeholder="${EMPRESA_CONFIG.facturacion_libre ? 'Escribe o busca un producto/servicio...' : 'Buscar producto o escanee c\u00f3digo...'}"
                    title="${EMPRESA_CONFIG.facturacion_libre ? 'Modo libre: puedes escribir directamente o seleccionar del cat\u00e1logo' : ''}">
                <input type="hidden" class="input-id-producto">
                <input type="hidden" class="input-codigo">
                <input type="hidden" class="input-casillero">
                <input type="hidden" class="input-es-libre" value="0">
                <input type="hidden" class="input-ice-pct" value="0">
                <input type="hidden" class="input-ice-cod" value="">
                <input type="hidden" class="input-ice-val" value="0">
                <input type="hidden" class="input-precio-base-original" value="0">
                <input type="hidden" class="input-factor-original" value="1">
                <div class="mt-1 container-variante d-none">
                    <select class="form-select form-select-sm input-detalle input-variante" style="font-size:0.7rem; height: 24px; padding: 0 5px;">
                        <option value="">Variantes...</option>
                    </select>
                </div>
                <div class="mt-1 small fw-bold text-muted span-saldo-info d-none" style="font-size:0.68rem;">
                    <i class="bi bi-box-seam me-1 text-primary"></i>Saldo: <span class="lbl-saldo-valor">0.00</span>
                </div>
            </td>
            <td><input type="text" class="form-control form-control-sm input-detalle input-adicional text-muted fst-italic" placeholder="Info adicional"></td>
            <td class="${EMPRESA_CONFIG.mostrar_unidad_medida ? '' : 'd-none'}">
                <select class="form-select form-select-sm input-detalle input-medida d-none">
                    <option value="">Medida</option>
                </select>
            </td>
            <td><input type="number" class="form-control form-control-sm input-detalle text-center input-cantidad" value="1" step="any" oninput="calcFila(this)"></td>
            <td>
                <select class="form-select form-select-sm input-detalle input-lista-precios">
                    <option value="1">Precio 1</option>
                    <option value="2">Precio 2</option>
                    <option value="3">Precio 3</option>
                </select>
            </td>
            <td><input type="number" class="form-control form-control-sm input-detalle text-end input-precio" value="${(0).toFixed(DEC_PRECIO)}" step="any" oninput="calcSinImp(this)" onblur="this.value=parseFloat(this.value||0).toFixed(DEC_PRECIO)" ${EMPRESA_CONFIG.editar_precio_factura ? '' : 'readonly'}></td>
            <td><input type="number" class="form-control form-control-sm input-detalle text-end input-precio-iva" value="${(0).toFixed(DEC_PRECIO)}" step="any" oninput="calcConImp(this)" onblur="this.value=parseFloat(this.value||0).toFixed(DEC_PRECIO)" ${EMPRESA_CONFIG.editar_precio_factura ? '' : 'readonly'}></td>
            <td>
                <div class="d-flex align-items-center">
                    <input type="number" class="form-control form-control-sm input-detalle text-end text-danger input-desc" value="0.00" step="any" oninput="calcFila(this)" ${EMPRESA_CONFIG.editar_descuento_factura ? '' : 'readonly'}>
                    <button type="button" class="btn btn-link btn-sm p-1 text-primary shadow-none border-0 ${EMPRESA_CONFIG.editar_descuento_factura ? '' : 'd-none'}" onclick="abrirModalDescuento(this)" title="Aplicar descuento rÃ¡pido">
                        <i class="bi bi-plus-circle"></i>
                    </button>
                </div>
            </td>
            <td>
                <select class="form-select form-select-sm input-detalle text-center input-iva" onchange="syncPrecioIva(this)" ${EMPRESA_CONFIG.editar_iva_factura ? '' : 'disabled'}>
                    ${TARIFAS_IVA.map(t => `<option value="${t.porcentaje_iva}" data-codigo="${t.codigo}" data-id="${t.id}">${t.tarifa}</option>`).join('')}
                </select>
            </td>
            ${EMPRESA_CONFIG.obligatorio_lotes ? `
            <td class="align-middle" style="min-width:120px;">
                    <select class="form-select form-select-sm input-detalle input-lote d-none" style="font-size:0.75rem;">
                        <option value="">Seleccionar Lote</option>
                    </select>
                </td>` : ''}
                ${EMPRESA_CONFIG.obligatorio_caducidad ? `
                <td class="align-middle" style="min-width:120px;">
                    <select class="form-select form-select-sm input-detalle input-caducidad d-none" style="font-size:0.75rem;">
                        <option value="">Seleccionar Vencimiento</option>
                    </select>
                </td>` : ''}
                ${EMPRESA_CONFIG.obligatorio_nup ? `
                <td class="align-middle" style="min-width:100px;">
                    <input type="text" class="form-control form-control-sm input-detalle input-nup d-none" placeholder="NUP/Serial" style="font-size:0.75rem;">
                </td>` : ''}
            <td class="text-end pe-4 align-middle">
                <span class="subtotal-line">0.00</span>
            </td>
            <td class="text-center p-0 align-middle" style="width: 40px;">
                <button type="button" class="btn btn-link btn-sm text-danger p-0 shadow-none border-0" onclick="this.closest('tr').remove(); calcTotales();" title="Eliminar Ã­tem">
                    <i class="bi bi-trash3 fs-6"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);

        const inputDesc = tr.querySelector('.input-descripcion');
        setTimeout(() => {
            inputDesc.focus();
        }, 50);
        const dropdownGlobal = document.getElementById('m-dropdown-productos-global');

        const seleccionarProductoEnFila = (p, row) => {
            row.querySelector('.input-codigo').value = p.codigo;
            row.querySelector('.input-descripcion').value = p.nombre;
            row.querySelector('.input-precio').value = parseFloat(p.precio_base).toFixed(DEC_PRECIO);
            row.querySelector('.input-id-producto').value = p.id;
            row.dataset.idProducto = p.id;
            row.dataset.tipoProduccion = p.tipo_produccion || '01';
            row.dataset.inventariable = p.inventariable;
            row.querySelector('.input-precio-base-original').value = p.precio_base;
            row.querySelector('.input-casillero').value = p.id_casillero_venta || '';

            // Datos de ICE
            row.querySelector('.input-ice-pct').value = p.valor_ice || 0;
            row.querySelector('.input-ice-cod').value = p.codigo_ice || '';
            row.querySelector('.input-ice-val').value = 0;

            // Manejo de Variantes
            const selVar = row.querySelector('.input-variante');
            const contVar = row.querySelector('.container-variante');
            if (p.variantes && p.variantes.length > 0) {
                if (contVar) contVar.classList.remove('d-none');
                selVar.innerHTML = '<option value="">Variantes...</option>';
                p.variantes.forEach(v => {
                    const opt = document.createElement('option');
                    opt.value = v.precio_adicional || 0;
                    opt.textContent = `${v.nombre}: ${v.valor} (+${parseFloat(v.precio_adicional || 0).toFixed(2)})`;
                    opt.dataset.nombre = v.nombre;
                    opt.dataset.valor = v.valor;
                    selVar.appendChild(opt);
                });
                selVar.onchange = () => {
                    const base = parseFloat(row.querySelector('.input-precio-base-original').value) || 0;
                    const add = parseFloat(selVar.value) || 0;
                    const total = base + add;
                    row.querySelector('.input-precio').value = total.toFixed(DEC_PRECIO);

                    const opt = selVar.options[selVar.selectedIndex];
                    const inputAdi = row.querySelector('.input-adicional');
                    if (opt.value) {
                        inputAdi.value = `${opt.dataset.nombre}: ${opt.dataset.valor}`;
                    } else {
                        inputAdi.value = '';
                    }

                    syncPrecioIva(row.querySelector('.input-precio'));
                };
            } else {
                if (contVar) contVar.classList.add('d-none');
                selVar.innerHTML = '<option value="">Variantes...</option>';
            }

            // Mostrar campos obligatorios si aplica (solo para productos de catÃ¡logo, no libres)
            const esInventariable = (p.inventariable == true || p.inventariable == 'true' || p.inventariable == 1) && (p.tipo_produccion !== '02');

            if (EMPRESA_CONFIG.obligatorio_lotes) {
                const fLote = row.querySelector('.input-lote');
                if (fLote) {
                    if (esInventariable) {
                        fLote.classList.remove('d-none');
                        fLote.required = true;
                    } else {
                        fLote.classList.add('d-none');
                        fLote.required = false;
                        fLote.value = '';
                    }
                }
            }
            if (EMPRESA_CONFIG.obligatorio_caducidad) {
                const fCad = row.querySelector('.input-caducidad');
                if (fCad) {
                    if (esInventariable) {
                        fCad.classList.remove('d-none');
                        fCad.required = true;
                    } else {
                        fCad.classList.add('d-none');
                        fCad.required = false;
                        fCad.value = '';
                    }
                }
            }
            if (EMPRESA_CONFIG.obligatorio_nup) {
                const fNup = row.querySelector('.input-nup');
                if (fNup) {
                    if (esInventariable) {
                        fNup.classList.remove('d-none');
                        fNup.required = true;
                    } else {
                        fNup.classList.add('d-none');
                        fNup.required = false;
                        fNup.value = '';
                    }
                }
            }

            if (esInventariable && EMPRESA_CONFIG.facturacion_inventario) {
                cargarLotesFila(row);
            }

            // Asignar IVA (priorizar porcentaje_iva_final de join unificado)
            let pctFinal = null;
            if (p.porcentaje_iva_final !== undefined && p.porcentaje_iva_final !== null) {
                pctFinal = parseFloat(p.porcentaje_iva_final);
            } else if (p.porcentaje_iva !== undefined && p.porcentaje_iva !== null) {
                pctFinal = parseFloat(p.porcentaje_iva);
            } else if (p.porcentaje !== undefined && p.porcentaje !== null) {
                pctFinal = parseFloat(p.porcentaje);
            } else if (p.tarifa_iva) {
                const tFound = TARIFAS_IVA.find(t => t.id == p.tarifa_iva);
                if (tFound) pctFinal = parseFloat(tFound.porcentaje_iva_final || tFound.porcentaje_iva || tFound.porcentaje);
            }

            if (pctFinal !== null || p.tarifa_iva) {
                const selIva = row.querySelector('.input-iva');
                if (selIva) {
                    // Prioridad 1: Buscar por ID directo de tarifa_iva
                    let opt = p.tarifa_iva ? Array.from(selIva.options).find(o => o.dataset.id == p.tarifa_iva) : null;

                    // Prioridad 2: Buscar por porcentaje (fallback)
                    if (!opt && pctFinal !== null) {
                        opt = Array.from(selIva.options).find(o => Math.abs(parseFloat(o.value) - pctFinal) < 0.001);
                    }

                    if (opt) {
                        selIva.selectedIndex = opt.index;
                    } else if (pctFinal === 0) {
                        selIva.value = "0";
                    }
                }
            }

            // Manejo de Medidas
            const selMedida = row.querySelector('.input-medida');
            if (p.inventariable == true || p.inventariable == 'true' || p.id_tipo_medida || p.id_medida) {
                selMedida.classList.remove('d-none');
                selMedida.innerHTML = '';

                let compatibles = [];
                if (p.id_tipo_medida) {
                    compatibles = UNIDADES.filter(u => u.id_tipo == p.id_tipo_medida); // Usar id_tipo (no id_tipo_medida segÃºn el repo)
                }

                if (compatibles.length === 0 && p.id_medida) {
                    const unitBase = UNIDADES.find(u => u.id == p.id_medida);
                    if (unitBase) compatibles = [unitBase];
                }

                if (compatibles.length > 0) {
                    compatibles.forEach(u => {
                        const opt = document.createElement('option');
                        opt.value = u.id;
                        opt.textContent = u.nombre;
                        opt.dataset.factor = u.factor_base || 1;
                        if (u.id == p.id_medida) {
                            opt.selected = true;
                            row.querySelector('.input-factor-original').value = u.factor_base || 1;
                        }
                        selMedida.appendChild(opt);
                    });

                    // Asegurar que si el producto no tiene id_medida asignado pero hay compatibles, el factor original sea del primero
                    if (!p.id_medida && selMedida.options.length > 0) {
                        row.querySelector('.input-factor-original').value = selMedida.options[0].dataset.factor || 1;
                    }

                    // Listener para conversiÃ³n de precio
                    selMedida.onchange = () => {
                        const basePrice = parseFloat(row.querySelector('.input-precio-base-original').value) || 0;
                        const factorOri = parseFloat(row.querySelector('.input-factor-original').value) || 1;
                        const optSel = selMedida.options[selMedida.selectedIndex];
                        const factorNew = parseFloat(optSel ? optSel.dataset.factor : 1) || 1;

                        // New Price = BasePrice * (NewFactor / OriginalFactor)
                        const convertedPrice = basePrice * (factorNew / factorOri);
                        row.querySelector('.input-precio').value = convertedPrice.toFixed(DEC_PRECIO);

                        syncPrecioIva(row.querySelector('.input-precio'));
                        calcFila(row.querySelector('.input-cantidad'));

                        // Actualizar saldos segÃºn la nueva unidad
                        if (typeof cargarLotesFila === 'function') {
                            cargarLotesFila(row);
                        }
                    };
                } else {
                    selMedida.classList.add('d-none');
                }
            } else {
                selMedida.classList.add('d-none');
                selMedida.innerHTML = '<option value="">...</option>';
            }

            // Manejo de Precios (Lista de Precios)
            const selPrecios = row.querySelector('.input-lista-precios');
            selPrecios.innerHTML = '';

            // Siempre agregamos el precio base
            const optBase = document.createElement('option');
            optBase.value = p.precio_base;
            optBase.textContent = `P. Base ($${parseFloat(p.precio_base).toFixed(DEC_PRECIO)})`;
            selPrecios.appendChild(optBase);

            // Agregar precios adicionales si existen
            if (p.precios_lista && p.precios_lista.length > 0) {
                p.precios_lista.forEach(pl => {
                    const opt = document.createElement('option');
                    opt.value = pl.precio;
                    opt.textContent = `${pl.nombre_precio} ($${parseFloat(pl.precio).toFixed(DEC_PRECIO)})`;
                    selPrecios.appendChild(opt);
                });
            }

            // Cambiar precio al seleccionar de la lista
            selPrecios.onchange = () => {
                row.querySelector('.input-precio').value = parseFloat(selPrecios.value).toFixed(DEC_PRECIO);
                syncPrecioIva(row.querySelector('.input-precio'));
            };

            // Sincronizar Precio con IVA
            syncPrecioIva(row.querySelector('.input-precio'));

            calcFila(row.querySelector('.input-cantidad'));
            const inCant = row.querySelector('.input-cantidad');
            inCant.focus();
            inCant.select();
        };

        const buscarProducto = async (q, sourceInput) => {
            // ... (keep search logic similar but adjust for no inputCod)
            q = q.trim();
            if (q.length < 2) {
                dropdownGlobal.classList.add('d-none');
                return;
            }

            const rect = sourceInput.getBoundingClientRect();
            dropdownGlobal.style.top = `${rect.bottom + 2}px`;
            dropdownGlobal.style.left = `${rect.left}px`;
            dropdownGlobal.style.width = `${Math.max(rect.width, 350)}px`;
            dropdownGlobal.classList.remove('d-none');
            dropdownGlobal.innerHTML = '<div class="list-group-item small text-muted">Buscando...</div>';

            try {
                const resp = await fetch(`${B_URL}/${RUTA_MODULO}/getProductosAjax?q=${encodeURIComponent(q)}`);
                const json = await resp.json();
                dropdownGlobal.innerHTML = '';

                if (json.data && json.data.length > 0) {
                    if (json.data.length === 1) {
                        const p = json.data[0];
                        if (p.codigo === q || p.codigo_barras === q || p.codigo_auxiliar === q) {
                            seleccionarProductoEnFila(p, tr);
                            dropdownGlobal.classList.add('d-none');
                            return;
                        }
                    }

                    json.data.forEach(p => {
                        const b = document.createElement('button');
                        b.type = 'button';
                        b.className = 'list-group-item list-group-item-action small py-1 border-bottom';
                        b.innerHTML = `
                            <div class="d-flex justify-content-between align-items-center text-start">
                                <div class="pe-3">
                                    <div class="fw-bold text-dark">${p.nombre}</div>
                                    <div class="x-small text-muted">${p.codigo} ${p.codigo_barras ? '| ' + p.codigo_barras : ''}</div>
                                </div>
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10">$${parseFloat(p.precio_base).toFixed(2)}</span>
                            </div>
                        `;
                        b.onmousedown = (evt) => {
                            evt.preventDefault();
                            seleccionarProductoEnFila(p, tr);
                            dropdownGlobal.classList.add('d-none');
                        };
                        dropdownGlobal.appendChild(b);
                    });

                    // Si facturaciÃ³n libre, mostrar opciÃ³n adicional al final
                    if (EMPRESA_CONFIG.facturacion_libre) {
                        agregarOpcionServicioLibre(q, tr, dropdownGlobal);
                    }
                } else {
                    if (EMPRESA_CONFIG.facturacion_libre) {
                        agregarOpcionServicioLibre(q, tr, dropdownGlobal);
                    } else {
                        dropdownGlobal.innerHTML = '<div class="list-group-item small text-muted">Sin coincidencias en el catÃ¡logo</div>';
                    }
                }
            } catch (err) {
                console.error('Error productos', err);
            }
        };

        inputDesc.addEventListener('input', debounce((e) => buscarProducto(e.target.value, inputDesc), 400));

        inputDesc.addEventListener('keydown', (e) => {
            if (e.key === 'Delete' || e.key === 'Backspace') {
                e.target.value = '';
                dropdownGlobal.classList.add('d-none');
                tr.querySelector('.input-id-producto').value = '';
            }
            if (e.key === 'Enter') {
                const firstBtn = dropdownGlobal.querySelector('button');
                if (firstBtn && !dropdownGlobal.classList.contains('d-none')) {
                    e.preventDefault();
                    if (firstBtn.onmousedown) firstBtn.onmousedown(new MouseEvent('mousedown'));
                }
            }
        });
        inputDesc.addEventListener('blur', () => setTimeout(() => dropdownGlobal.classList.add('d-none'), 200));

        inputDesc.addEventListener('blur', () => {
            if (!EMPRESA_CONFIG.facturacion_libre) return;
            const idProd = tr.querySelector('.input-id-producto').value;
            const desc = inputDesc.value.trim();
            if (!idProd && desc.length > 0) {
                seleccionarItemLibre(desc, tr);
            }
        });
    }

    async function cargarLotesFila(row) {
        const idProd = row.dataset.idProducto;
        const idBod = row.querySelector('.select-bodega')?.value || document.getElementById('m-select-bodega')?.value;
        const selLote = row.querySelector('.input-lote');
        const selCad = row.querySelector('.input-caducidad');
        const lblSaldo = row.querySelector('.lbl-saldo-valor');
        const contSaldo = row.querySelector('.span-saldo-info');
        const idVenta = FV_ID_ACTIVO || 0;

        if (!idProd || !idBod) return;

        try {
            if (contSaldo) contSaldo.classList.remove('d-none');
            if (lblSaldo) lblSaldo.textContent = '...';

            if (selLote) {
                selLote.innerHTML = '<option value="">Cargando...</option>';
                selLote.disabled = true;
            }
            if (selCad) {
                selCad.innerHTML = '<option value="">Cargando...</option>';
                selCad.disabled = true;
            }

            const currentLote = selLote ? selLote.dataset.originalLote || '' : '';
            const currentCad = selCad ? selCad.dataset.originalCad || '' : '';

            const resp = await fetch(`${B_URL}/${RUTA_MODULO}/getLotesAjax?id_producto=${idProd}&id_bodega=${idBod}&id_venta=${idVenta}`);
            const json = await resp.json();

            if (selLote) {
                selLote.innerHTML = '<option value="">Lote...</option>';
                selLote.disabled = false;
            }
            if (selCad) {
                selCad.innerHTML = '<option value="">Vencimiento...</option>';
                selCad.disabled = false;
            }

            if (json.ok) {
                const selMedida = row.querySelector('.input-medida');
                const factor = parseFloat(selMedida?.options[selMedida.selectedIndex]?.dataset.factor || 1) || 1;

                row.dataset.stockTotalBase = json.stock_total || 0;
                const totalConvertido = parseFloat(json.stock_total || 0) / factor;
                row.dataset.stockTotal = totalConvertido.toFixed(2);

                // Mostrar saldo general inicialmente
                if (lblSaldo) lblSaldo.textContent = row.dataset.stockTotal;

                if (json.data && json.data.length > 0) {
                    const lotes = json.data;
                    lotes.forEach(l => {
                        const loteVal = l.numero_lote || '';
                        const cadVal = l.fecha_caducidad || '';
                        const stockConvertido = parseFloat(l.stock_lote || 0) / factor;

                        if (selLote) {
                            const optL = document.createElement('option');
                            optL.value = loteVal;
                            optL.textContent = loteVal || 'Sin Lote';
                            optL.dataset.stock = stockConvertido;
                            optL.dataset.caducidad = cadVal;
                            selLote.appendChild(optL);
                        }

                        if (selCad) {
                            const optC = document.createElement('option');
                            optC.value = cadVal;
                            const labelCad = cadVal ? (typeof formatDate === 'function' ? formatDate(cadVal) : cadVal) : 'Sin Fecha';
                            optC.textContent = labelCad;
                            optC.dataset.stock = stockConvertido;
                            optC.dataset.lote = loteVal;
                            selCad.appendChild(optC);
                        }
                    });

                    // Sincronización Lote <-> Caducidad y Actualización de Saldo Dinámico
                    const actualizarSaldoLote = (sel) => {
                        const opt = sel.options[sel.selectedIndex];
                        if (opt && opt.value !== '') {
                            if (lblSaldo) lblSaldo.textContent = parseFloat(opt.dataset.stock || 0).toFixed(2);
                        } else {
                            if (lblSaldo) lblSaldo.textContent = parseFloat(row.dataset.stockTotal || 0).toFixed(2);
                        }
                    };

                    if (selLote) {
                        selLote.onchange = () => {
                            const opt = selLote.options[selLote.selectedIndex];
                            if (opt && opt.dataset.caducidad && selCad) {
                                selCad.value = opt.dataset.caducidad;
                            }
                            actualizarSaldoLote(selLote);
                        };
                    }
                    if (selCad) {
                        selCad.onchange = () => {
                            const opt = selCad.options[selCad.selectedIndex];
                            if (opt && opt.dataset.lote && selLote) {
                                selLote.value = opt.dataset.lote;
                            }
                            actualizarSaldoLote(selCad);
                        };
                    }

                    // Restauración de lote solo si ya existe un valor guardado (edición)
                    if (selLote && currentLote && Array.from(selLote.options).some(o => o.value === currentLote)) {
                        selLote.value = currentLote;
                        selLote.dispatchEvent(new Event('change'));
                    } else if (selCad && currentCad && Array.from(selCad.options).some(o => o.value === currentCad)) {
                        selCad.value = currentCad;
                        selCad.dispatchEvent(new Event('change'));
                    }
                }
            } else {
                if (lblSaldo) lblSaldo.textContent = '0.00';
                if (selLote) selLote.innerHTML = '<option value="">Sin Stock</option>';
                if (selCad) selCad.innerHTML = '<option value="">Sin Stock</option>';
            }
        } catch (e) {
            console.error('Error cargando lotes', e);
        } finally {
            if (selLote) selLote.disabled = false;
        }
    }

    // Listener para cambio de bodega global
    const selectBodegaGlobal = document.getElementById('m-select-bodega');
    if (selectBodegaGlobal) {
        selectBodegaGlobal.addEventListener('change', () => {
            document.querySelectorAll('#m-tbodyDetalle tr').forEach(row => {
                const idProd = row.querySelector('.input-id-producto').value;
                if (idProd) cargarLotesFila(row);
            });
        });
    }

    /**
     * Agrega al dropdown un botón para "usar como servicio libre"
     */
    function agregarOpcionServicioLibre(texto, tr, dropdown) {
        const sep = document.createElement('div');
        sep.className = 'list-group-item py-1 text-muted x-small border-top bg-light';
        sep.textContent = 'ó facturar como servicio libre:';
        dropdown.appendChild(sep);

        const bLibre = document.createElement('button');
        bLibre.type = 'button';
        bLibre.className = 'list-group-item list-group-item-action py-2 border-0 bg-warning bg-opacity-10';
        bLibre.innerHTML = `
            <div class="d-flex align-items-center gap-2 text-start">
                <i class="bi bi-lightning-charge-fill text-warning fs-6"></i>
                <div>
                    <div class="fw-bold small text-dark">"${texto}"</div>
                    <div class="x-small text-muted">Registrar como servicio libre (se creará automáticamente al guardar)</div>
                </div>
            </div>
        `;
        bLibre.onmousedown = (evt) => {
            evt.preventDefault();
            seleccionarItemLibre(texto, tr);
            dropdown.classList.add('d-none');
        };
        dropdown.appendChild(bLibre);
    }

    /**
     * Configura una fila como ítem libre (servicio ad-hoc)
     */
    function seleccionarItemLibre(descripcion, row) {
        row.querySelector('.input-descripcion').value = descripcion;
        row.querySelector('.input-id-producto').value = ''; // Sin ID de producto
        row.querySelector('.input-codigo').value = '__LIBRE__'; // Bandera para el backend
        row.querySelector('.input-es-libre').value = '1';
        row.dataset.tipoProduccion = '02'; // Los ítems libres se tratan como servicios ad-hoc
        row.dataset.inventariable = 'false';
        row.querySelector('.input-casillero').value = '';

        // Precio en 0 para que el usuario lo ingrese
        row.querySelector('.input-precio').value = '0.00';
        row.querySelector('.input-precio-iva').value = '0.00';
        row.querySelector('.input-cantidad').value = '1';

        // Seleccionar por defecto la primera tarifa IVA disponible
        const selIva = row.querySelector('.input-iva');
        if (selIva && selIva.options.length > 0) {
            selIva.selectedIndex = 0;
        }

        // Ocultar selector de medidas (servicios libres no tienen unidad fija)
        const selMedida = row.querySelector('.input-medida');
        if (selMedida) selMedida.classList.add('d-none');

        // Resaltar la fila visualmente como "libre"
        row.classList.add('table-warning');
        row.title = 'Servicio libre - se creará en el catálogo al guardar la factura';

        // Mover foco al precio
        const inputPrecio = row.querySelector('.input-precio');
        if (inputPrecio) {
            setTimeout(() => {
                inputPrecio.focus();
                inputPrecio.select();
            }, 50);
        }

        calcFila(row.querySelector('.input-cantidad'));
    }

    window.syncPrecioIva = function(el) {
        const tr = el.closest('tr');
        const pSin = parseFloat(tr.querySelector('.input-precio').value) || 0;
        const ivaPct = parseFloat(tr.querySelector('.input-iva').value) || 0;
        const pCon = pSin * (1 + (ivaPct / 100));
        tr.querySelector('.input-precio-iva').value = pCon.toFixed(DEC_PRECIO);
        calcFila(tr.querySelector('.input-cantidad'));
    }

    window.calcSinImp = function(el) {
        const tr = el.closest('tr');
        const pSin = parseFloat(el.value) || 0;
        const ivaPct = parseFloat(tr.querySelector('.input-iva').value) || 0;
        tr.querySelector('.input-precio-iva').value = (pSin * (1 + (ivaPct / 100))).toFixed(DEC_PRECIO);
        calcFila(el);
    }

    window.calcConImp = function(el) {
        const tr = el.closest('tr');
        const pCon = parseFloat(el.value) || 0;
        const ivaPct = parseFloat(tr.querySelector('.input-iva').value) || 0;
        const pSin = pCon / (1 + (ivaPct / 100));
        tr.querySelector('.input-precio').value = pSin.toFixed(DEC_PRECIO);
        calcFila(el);
    }

    /** Redondea a 2 decimales evitando errores de punto flotante. */
    const r2 = v => Math.round(v * 100) / 100;

    function calcFila(el) {
        const tr = el.closest('tr');
        const cant = parseFloat(tr.querySelector('.input-cantidad').value) || 0;
        const prec = parseFloat(tr.querySelector('.input-precio').value) || 0;
        const desc = parseFloat(tr.querySelector('.input-desc').value) || 0;

        const subtotalBruto = r2(cant * prec);
        const subtotalNeto = r2(subtotalBruto - desc);

        // ICE: tarifa < 1 †’ específico ($ por unidad); tarifa >= 1 †’ ad-valorem (% sobre neto)
        const iceTarifa = parseFloat(tr.querySelector('.input-ice-pct').value) || 0;
        let iceVal = 0;
        if (iceTarifa > 0) {
            iceVal = iceTarifa < 1 ? r2(cant * iceTarifa) : r2(subtotalNeto * (iceTarifa / 100));
        }
        tr.querySelector('.input-ice-val').value = iceVal.toFixed(2);

        // El subtotal de la línea muestra el neto (después de descuento)
        tr.querySelector('.subtotal-line').textContent = subtotalNeto.toFixed(2);
        calcTotales();
    }

    function calcTotales() {
        const modoIva = EMPRESA_CONFIG.calculo_iva ?? 'linea_linea';

        let subtotalGeneral = 0; // suma de (cant - prec) antes de descuento - para mostrar bruto
        let descuentoTotal = 0;
        let iceTotal = 0;

        /**
         * Por tarifa IVA:
         *   base    †’ suma de subtotales netos (cant-prec ˆ’ desc) para display "Subtotal X%"
         *   baseIva †’ base + ICE, base imponible real del IVA
         *   iva     †’ IVA acumulado (en modo linea_linea se va sumando línea a línea redondeada)
         */
        const grupos = {};

        document.querySelectorAll('.row-detalle').forEach(tr => {
            const cant = parseFloat(tr.querySelector('.input-cantidad').value) || 0;
            const prec = parseFloat(tr.querySelector('.input-precio').value) || 0;
            const desc = parseFloat(tr.querySelector('.input-desc').value) || 0;

            const selectIva = tr.querySelector('.input-iva');
            const optIva = selectIva.options[selectIva.selectedIndex];
            const ivaPct = parseFloat(optIva.value) || 0;
            const idTarifa = optIva.dataset.id; // Agrupar por ID de tarifa, no por porcentaje
            const labelTarifa = optIva.text;

            const iceVal = parseFloat(tr.querySelector('.input-ice-val').value) || 0;
            const key = idTarifa;

            const subtotalBruto = r2(cant * prec);
            const subtotalNeto = r2(subtotalBruto - desc);
            const baseIvaFila = r2(subtotalNeto + iceVal); // base imponible IVA (incluye ICE)

            subtotalGeneral = r2(subtotalGeneral + subtotalBruto);
            descuentoTotal = r2(descuentoTotal + desc);
            iceTotal = r2(iceTotal + iceVal);

            if (!grupos[key]) {
                grupos[key] = {
                    pct: ivaPct,
                    label: labelTarifa,
                    base: 0,
                    baseIva: 0,
                    iva: 0
                };
            }
            grupos[key].base = r2(grupos[key].base + subtotalNeto);
            grupos[key].baseIva = r2(grupos[key].baseIva + baseIvaFila);

            // Modo línea a línea: acumular IVA redondeado por línea
            if (modoIva === 'linea_linea') {
                grupos[key].iva = r2(grupos[key].iva + r2(baseIvaFila * ivaPct / 100));
            }
        });

        // Modo subtotal: calcular IVA sobre la base acumulada de cada tarifa
        if (modoIva === 'subtotal') {
            Object.values(grupos).forEach(g => {
                g.iva = r2(g.baseIva * g.pct / 100);
            });
        }

        let ivaTotal = 0;
        Object.values(grupos).forEach(g => {
            ivaTotal = r2(ivaTotal + g.iva);
        });

        const propina = r2(parseFloat(document.getElementById('m-input-propina')?.value) || 0);
        const totalFactura = r2((subtotalGeneral - descuentoTotal) + ivaTotal + iceTotal + propina);

        // ”€”€ Actualizar DOM ”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€”€

        // Subtotal general (bruto, antes de descuento)
        const lblSubTotal = document.getElementById('m-lbl-subtotal');
        if (lblSubTotal) lblSubTotal.textContent = subtotalGeneral.toFixed(2);

        // Subtotales netos por tarifa IVA
        const contSubIva = document.getElementById('m-lbl-subtotales-iva');
        if (contSubIva) {
            contSubIva.innerHTML = '';
            Object.values(grupos).forEach(g => {
                const div = document.createElement('div');
                div.className = 'd-flex justify-content-between align-items-center mb-1 text-muted';
                div.innerHTML = `<span>Subtotal ${g.label}</span><span>${g.base.toFixed(2)}</span>`;
                contSubIva.appendChild(div);
            });
        }

        // Descuento total
        const lblDesc = document.getElementById('m-lbl-descuento');
        if (lblDesc) lblDesc.textContent = descuentoTotal.toFixed(2);

        // IVA por tarifa (solo tarifas > 0 con valor > 0)
        const contIvas = document.getElementById('m-lbl-ivas-grupo');
        if (contIvas) {
            contIvas.innerHTML = '';
            Object.values(grupos).forEach(g => {
                if (g.pct > 0 && g.iva > 0) {
                    const div = document.createElement('div');
                    div.className = 'd-flex justify-content-between align-items-center mb-1';
                    div.innerHTML = `<span class="text-muted">(+) IVA ${g.pct}%</span><span>${g.iva.toFixed(2)}</span>`;
                    contIvas.appendChild(div);
                }
            });
        }

        // ICE (solo si hay)
        const iceRow = document.getElementById('m-lbl-ice-row');
        const lblIce = document.getElementById('m-lbl-ice');
        if (iceTotal > 0) {
            iceRow?.classList.remove('d-none');
            if (lblIce) lblIce.textContent = iceTotal.toFixed(2);
        } else {
            iceRow?.classList.add('d-none');
        }

        // Total
        const lblTotal = document.getElementById('m-lbl-total');
        if (lblTotal) lblTotal.textContent = totalFactura.toFixed(2);

        // Contador de ítems
        const countItems = document.getElementById('m-count-items');
        if (countItems) countItems.textContent = document.querySelectorAll('.row-detalle').length;

        // Sincronizar monto si hay un solo pago registrado
        const pagosValores = document.querySelectorAll('input[name="f_pago_valor[]"]');
        if (pagosValores.length === 1) pagosValores[0].value = totalFactura.toFixed(2);

        // Regenerar asiento contable con los valores actuales de la factura (siempre, con debounce)
        if (typeof window.fvDebouncedRecalcularAsiento === 'function') {
            window.fvDebouncedRecalcularAsiento();
        }
    }

    // --- FORMAS DE PAGO SRI ---
    window.agregarFormaPago = function() {
        const container = document.getElementById('m-container-pagos');
        const opcionesHTML = Array.from(
            container.querySelector('select[name="f_pago_id[]"]').options
        ).map(o => `<option value="${o.value}" data-id="${o.dataset.id || ''}">${o.text}</option>`).join('');

        const div = document.createElement('div');
        div.className = 'row g-2 align-items-center mb-1 row-pago';
        div.innerHTML = `
            <div class="col-7">
                <select class="form-select form-select-sm border-0 bg-light" name="f_pago_id[]">
                    ${opcionesHTML}
                </select>
            </div>
            <div class="col-4">
                <input type="number" class="form-control form-control-sm text-end border-0 bg-light fw-bold" name="f_pago_valor[]" step="0.01" value="0.00">
            </div>
            <div class="col-1 text-center">
                <button type="button" class="btn btn-link btn-sm p-0 text-danger shadow-none" onclick="this.closest('.row-pago').remove(); calcTotales();" title="Eliminar">
                    <i class="bi bi-x-circle-fill"></i>
                </button>
            </div>
        `;
        container.appendChild(div);
        div.querySelector('input[name="f_pago_valor[]"]').focus();
    };

    // --- INFO ADICIONAL ---

    /**
     * Inserta la fila fija del cajero en info adicional si la config de empresa lo indica.
     * Se identifica con data-tipo="cajero". No tiene botón eliminar.
     */
    function insertarInfoCajero() {
        if (!EMPRESA_CONFIG.mostrar_cajero_factura || !USUARIO_NOMBRE) return;
        const tbody = document.getElementById('m-tbody-info-adicional');
        if (tbody.querySelector('tr[data-tipo="cajero"]')) return; // ya existe
        const tr = document.createElement('tr');
        tr.className = 'row-info-adicional';
        tr.dataset.tipo = 'cajero';
                tr.innerHTML = `
            <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent input-info-concepto" style="padding:0 4px;height:20px;font-size:0.78rem;" value="Cajero" readonly></td>
            <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent input-info-detalle" style="padding:0 4px;height:20px;font-size:0.78rem;" value="${USUARIO_NOMBRE}" readonly></td>
            <td class="p-0 text-center pe-1">
                <span class="text-muted small" title="Generado automÃ¡ticamente"><i class="bi bi-lock-fill"></i></span>
            </td>
        `;
        tbody.appendChild(tr);
    }

    /**
     * Inserta o actualiza la fila del vendedor en info adicional.
     * Se identifica con data-tipo="vendedor". No tiene botón eliminar.
     * Se llama al abrir el modal y al cambiar el select de vendedor.
     */
    window.actualizarInfoVendedor = function(selectEl) {
        if (!EMPRESA_CONFIG.mostrar_vendedor_factura) return;
        const select = selectEl || document.getElementById('m-select-vendedor');
        const nombre = select?.options[select.selectedIndex]?.text || '';
        const tbody = document.getElementById('m-tbody-info-adicional');
        let fila = tbody.querySelector('tr[data-tipo="vendedor"]');

        if (!select?.value || !nombre || nombre === 'Seleccione...') {
            if (fila) fila.remove();
            return;
        }

        if (fila) {
            fila.querySelector('.input-info-detalle').value = nombre;
        } else {
            const tr = document.createElement('tr');
            tr.className = 'row-info-adicional';
            tr.dataset.tipo = 'vendedor';
                    tr.innerHTML = `
                <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent input-info-concepto" style="padding:0 4px;height:20px;font-size:0.78rem;" value="Vendedor" readonly></td>
                <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent input-info-detalle" style="padding:0 4px;height:20px;font-size:0.78rem;" value="${nombre}" readonly></td>
                <td class="p-0 text-center pe-1">
                    <span class="text-muted small" title="Se actualiza al cambiar el vendedor"><i class="bi bi-lock-fill"></i></span>
                </td>
            `;
            tbody.appendChild(tr);
        }
    };

    /**
     * Inserta o actualiza la fila fija de correo del cliente en info adicional.
     * Esta fila no tiene botón de eliminar y se identifica con data-tipo="correo-cliente".
     * Si el cliente no tiene correo registrado, elimina la fila (si existía).
     */
    function actualizarInfoCorreoCliente(email) {
        const tbody = document.getElementById('m-tbody-info-adicional');
        let filaCorreo = tbody.querySelector('tr[data-tipo="correo-cliente"]');

        if (!email) {
            if (filaCorreo) filaCorreo.remove();
            return;
        }

        if (filaCorreo) {
            // Solo actualiza el detalle si ya existe
            filaCorreo.querySelector('.input-info-detalle').value = email;
        } else {
            // Crea la fila fija (sin botÃ³n eliminar) y la agrega al final
            const tr = document.createElement('tr');
            tr.className = 'row-info-adicional';
            tr.dataset.tipo = 'correo-cliente';
            tr.innerHTML = `
                <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent input-info-concepto" style="padding:0 4px;height:20px;font-size:0.78rem;" value="Correo del cliente" readonly></td>
                <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent input-info-detalle" style="padding:0 4px;height:20px;font-size:0.78rem;" value="${email}"></td>
                <td class="p-0 text-center pe-1">
                    <span class="text-muted small" title="Se actualiza al cambiar el cliente"><i class="bi bi-lock-fill"></i></span>
                </td>
            `;
            tbody.appendChild(tr);
        }
    }

    window.agregarInfoAdicional = function() {
        const tbody = document.getElementById('m-tbody-info-adicional');
        const tr = document.createElement('tr');
        tr.className = 'row-info-adicional';
        tr.innerHTML = `
            <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent input-info-concepto" style="padding:0 4px;height:20px;font-size:0.78rem;" placeholder="Concepto..."></td>
            <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent input-info-detalle" style="padding:0 4px;height:20px;font-size:0.78rem;" placeholder="Detalle..."></td>
            <td class="p-0 text-center pe-1">
                <button type="button" class="btn btn-link btn-sm p-0 m-0 text-danger shadow-none" onclick="this.closest('tr').remove();">
                    <i class="bi bi-x-circle-fill"></i>
                </button>
            </td>
        `;
        // Siempre insertar antes de la primera fila fija (data-tipo)
        const primeraFija = tbody.querySelector('tr[data-tipo]');
        if (primeraFija) {
            tbody.insertBefore(tr, primeraFija);
        } else {
            tbody.appendChild(tr);
        }
        tr.querySelector('.input-info-concepto').focus();
    };

    window.abrirModalFacturaVer = async function(row) {
        const data = JSON.parse(row.dataset.row);
        const id = parseInt(data.id) || 0;

        // Bloquear cargarSecuencial ANTES del reset para que ninguna llamada asÃ­ncrona lo sobreescriba
        FV_BLOQUEAR_SECUENCIAL = true;
        fvResetearModal();
        FV_ID_ACTIVO = id;
        FV_FECHA_EMISION = (data.fecha_emision || '').split(' ')[0].split('T')[0] || null;
        FV_CLIENTE_RUC   = (data.cliente_ruc || '').trim();

        // TÃ­tulo con nÃºmero de factura
        const tituloEl = document.querySelector('#modalNuevaFactura .modal-title');
        
        // Si data tiene los campos, los usamos para el tÃ­tulo inicial, sino mantenemos lo que hay o usamos vacÃ­os
        const padEst = (data.establecimiento || '').toString().padStart(3, '0');
        const padPunto = (data.punto_emision || '').toString().padStart(3, '0');
        const padSec = (data.secuencial || '').toString().padStart(9, '0');
        
        // Solo actualizar el tÃ­tulo si tenemos datos reales o si no hay tÃ­tulo puesto
        if (data.establecimiento || data.secuencial || (tituloEl && tituloEl.textContent.includes('Nueva factura'))) {
            const num = padEst + '-' + padPunto + '-' + padSec;
            const nombreCliente = data.cliente_nombre ? ' - ' + data.cliente_nombre : '';
            if (tituloEl) tituloEl.innerHTML = '<i class="bi bi-receipt-cutoff me-2"></i>Factura: ' + num + nombreCliente;
        }



        // Fecha de emisiÃ³n (sÃ³lo parte de fecha YYYY-MM-DD)
        const form = document.getElementById('formFacturaModal');
        const fechaInput = form?.querySelector('[name="fecha_emision"]');
        if (fechaInput && data.fecha_emision) {
            fechaInput.value = (data.fecha_emision || '').split(' ')[0].split('T')[0];
        }

        // Punto de emisiÃ³n: solo setear valor, NO llamar syncSerie para no pisar el secuencial
        const selPunto = document.getElementById('m-select-puntos');
        if (selPunto && data.id_punto_emision) {
            selPunto.value = data.id_punto_emision;
            // Sincronizar solo el id_establecimiento, sin recargar el secuencial
            const opt = selPunto.options[selPunto.selectedIndex];
            if (opt) {
                const estInput = document.getElementById('m-id-establecimiento');
                if (estInput) estInput.value = opt.dataset.est || '';
            }
        }

        // Secuencial: mostrar el de la factura cargada, no el siguiente consecutivo
        const inputSec = document.getElementById('m-input-secuencial');
        if (inputSec) {
            inputSec.value = data.secuencial || '';
            inputSec.classList.remove('border-warning');
            inputSec.title = 'Secuencial original de la factura';
        }

        // Cliente bÃ¡sico (datos del listado para mostrar mientras carga el AJAX)
        const idClienteInput = document.getElementById('m-id-cliente');
        const searchClienteInput = document.getElementById('m-search-cliente');
        const infoBarCliente = document.getElementById('m-info-cliente');
        if (idClienteInput) idClienteInput.value = data.id_cliente || '';
        if (searchClienteInput) searchClienteInput.value = data.cliente_nombre || '';
        const rucLbl = document.getElementById('m-lbl-cliente-ruc');
        if (rucLbl) rucLbl.textContent = data.cliente_ruc || '';
        if (infoBarCliente) infoBarCliente.classList.remove('d-none');

        // BotÃ³n eliminar: solo visible para borradores con permiso
        const btnElim = document.getElementById('btnEliminarFacturaModal');
        const btnAnular = document.getElementById('btnAnularFacturaModal');
        const esBorrador = (data.estado || '') === 'borrador';
        const esAnulado = (data.estado || '') === 'anulado';

        if (btnElim) {
            if (esBorrador && <?= !empty($perm['eliminar']) ? 'true' : 'false' ?>) {
                btnElim.classList.remove('d-none');
            } else {
                btnElim.classList.add('d-none');
            }
        }

        if (btnAnular) {
            // Se puede anular solo si el estado es autorizado (insensible a mayÃºsculas y flexible)
            const stTrim = (data.estado || '').toLowerCase().trim();
            const esAutorizado = stTrim.includes('autorizado') || stTrim.includes('autorizada');

            if (esAutorizado && PERM_ACTUALIZAR) {
                btnAnular.classList.remove('d-none');
            } else {
                btnAnular.classList.add('d-none');
            }
        }
        // BotÃ³n guardar: en borrador dice "Guardar", en autorizado dice "Actualizar Vendedor"
        const btnGuardar = document.getElementById('btnGuardarFacturaModal');
        if (btnGuardar) {
            if (esBorrador) {
                btnGuardar.classList.remove('d-none');
                btnGuardar.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar';
            } else if (!esAnulado && PERM_ACTUALIZAR) {
                btnGuardar.classList.remove('d-none');
                btnGuardar.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Actualizar';
                btnGuardar.title = 'Actualizar datos de la factura y generar asiento si no lo tiene';
            } else {
                btnGuardar.classList.add('d-none');
            }
        }

        // Limpiar campos de trazabilidad inicial
        if (document.getElementById('m-lbl-creado-por')) document.getElementById('m-lbl-creado-por').textContent = '...';
        if (document.getElementById('m-lbl-responsable-por')) document.getElementById('m-lbl-responsable-por').textContent = data.usuario_nombre || '...';
        if (document.getElementById('m-lbl-actualizado-por')) document.getElementById('m-lbl-actualizado-por').textContent = '...';

        // Mostrar todas las pestaÃ±as configuradas en ediciÃ³n/vista
        document.querySelectorAll('#tabsFacturaVenta .nav-item').forEach(li => {
            li.classList.remove('d-none');
        });

        // Asegurar que fvActualizarEstadoBotones se llame para los botones superiores
        fvActualizarEstadoBotones(data.estado || 'borrador');

        modalMain.show();

        // Cargar datos completos vÃ­a AJAX
        try {
            const resp = await fetch(`${B_URL}/${RUTA_MODULO}/getFacturaAjax?id=${id}`);
            const json = await resp.json();
            if (!json.ok) return;

            const cab = json.cabecera;

            // Actualizar tÃ­tulo con datos reales completos (aplicando padding)
            const rEst = (cab.establecimiento || '').toString().padStart(3, '0');
            const rPunto = (cab.punto_emision || '').toString().padStart(3, '0');
            const rSec = (cab.secuencial || '').toString().padStart(9, '0');
            const numReal = rEst + '-' + rPunto + '-' + rSec;
            if (tituloEl) tituloEl.innerHTML = '<i class="bi bi-receipt-cutoff me-2"></i>Factura: ' + numReal + ' - ' + (cab.cliente_nombre || '');


            // â”€â”€ Datos completos del cliente â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            if (idClienteInput) idClienteInput.value = cab.id_cliente || '';
            if (searchClienteInput) searchClienteInput.value = cab.cliente_nombre || '';
            const tipoIdInput = document.getElementById('m-tipo-id-cliente');
            const nomTipoIdInput = document.getElementById('m-nombre-tipo-id-cliente');
            if (tipoIdInput) tipoIdInput.value = cab.cliente_tipo_id || '';
            if (nomTipoIdInput) nomTipoIdInput.value = cab.cliente_nombre_tipo_id || '';
            if (rucLbl) rucLbl.textContent = cab.cliente_ruc || '';
            const dirLbl = document.getElementById('m-lbl-cliente-direccion');
            const mailLbl = document.getElementById('m-lbl-cliente-correo');
            if (dirLbl) dirLbl.textContent = cab.cliente_direccion || 'No especificada';
            if (mailLbl) mailLbl.textContent = cab.cliente_email || 'Sin correo registrado';
            if (infoBarCliente) infoBarCliente.classList.remove('d-none');

            // â”€â”€ Secuencial: confirmar el valor guardado desde la cabecera completa â”€â”€
            if (inputSec && cab.secuencial) {
                inputSec.value = cab.secuencial;
                inputSec.classList.remove('border-warning');
                inputSec.title = 'Secuencial original de la factura';
            }

            // â”€â”€ Vendedor â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            const selVendedor = document.getElementById('m-select-vendedor');
            if (selVendedor) selVendedor.value = cab.id_vendedor || '';

            // â”€â”€ SRI: Clave de acceso, Ambiente y EmisiÃ³n â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            const elClaveAcceso = document.getElementById('sri-clave-acceso');
            const elAmbiente = document.getElementById('sri-ambiente');
            const elEmision = document.getElementById('sri-tipo-emision');

            if (elClaveAcceso) elClaveAcceso.value = cab.clave_acceso || '';

            const elNumAut = document.getElementById('sri-numero-autorizacion');
            if (elNumAut) elNumAut.value = cab.numero_autorizacion || cab.clave_acceso || '';

            if (elAmbiente) {
                const amb = parseInt(cab.tipo_ambiente);
                elAmbiente.value = (amb === 1) ? '1 - PRUEBAS' : (amb === 2 ? '2 - PRODUCCIÃ“N' : (cab.tipo_ambiente || 'â€”'));
            }
            if (elEmision) {
                const emi = parseInt(cab.tipo_emision);
                elEmision.value = (emi === 1) ? '1 - NORMAL' : (cab.tipo_emision || 'â€”');
            }

            // Actualizar badge estado SRI, fecha autorizaciÃ³n y mensajes
            fvActualizarPestanhaSri({
                estado_sri: cab.estado_sri || cab.estado || 'pendiente',
                numero_autorizacion: cab.numero_autorizacion || cab.clave_acceso || '',
                fecha_autorizacion: cab.fecha_autorizacion || '',
                mensajes_sri: cab.mensajes_sri || null,
            });

            // Campos de anulación SRI
            const elNroDoc = document.getElementById('sri-numero-documento');
            if (elNroDoc) {
                const est  = (cab.establecimiento  || '').toString().padStart(3, '0');
                const pto  = (cab.punto_emision    || '').toString().padStart(3, '0');
                const sec  = (cab.secuencial       || '').toString().padStart(9, '0');
                elNroDoc.value = `${est}-${pto}-${sec}`;
            }
            const elIdentif = document.getElementById('sri-identificacion-cliente');
            if (elIdentif) elIdentif.value = cab.cliente_ruc || '';
            const elCorreo = document.getElementById('sri-correo-cliente');
            if (elCorreo) elCorreo.value = cab.cliente_email || '';

            // Cargar historiales
            fetchHistorialVenta(id);
            fvCargarHistorialSri(id);

            // â”€â”€ CrÃ©dito / observaciones â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            const inputObs = document.getElementById('m-input-observaciones');
            const inputDias = document.getElementById('m-input-dias-credito');
            if (inputObs) inputObs.value = cab.observaciones || '';
            if (inputDias) inputDias.value = cab.dias_credito || 0;
            // Plazo SRI: tomar del primer pago guardado
            const selPlazoCred = form?.querySelector('[name="sri_plazo"]');
            if (selPlazoCred && json.pagos && json.pagos.length > 0 && json.pagos[0].unidad_tiempo) {
                selPlazoCred.value = json.pagos[0].unidad_tiempo;
            }

            // â”€â”€ Detalles (limpiar fila vacÃ­a inicial y cargar) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            document.querySelectorAll('#m-tbodyDetalle tr.row-detalle').forEach(tr => tr.remove());

            // Si hay detalles, establecer la bodega del primer Ã­tem en el selector cabecera
            if (json.detalles.length > 0) {
                const selBodegaHeader = document.getElementById('m-select-bodega');
                if (selBodegaHeader) {
                    selBodegaHeader.value = json.detalles[0].id_bodega || '';
                }
            }

            json.detalles.forEach(d => {
                agregarFila();
                const filas = document.querySelectorAll('#m-tbodyDetalle tr.row-detalle');
                const tr = filas[filas.length - 1];
                if (!tr) return;

                tr.querySelector('.input-id-producto').value = d.id_producto || '';
                tr.dataset.idProducto = d.id_producto || '';
                tr.dataset.tipoProduccion = d.tipo_produccion || '01';
                tr.dataset.inventariable = d.inventariable;
                tr.querySelector('.input-descripcion').value = d.descripcion || '';
                tr.querySelector('.input-codigo').value = d.codigo_principal || '';
                tr.querySelector('.input-casillero').value = d.casillero || '';
                tr.querySelector('.input-cantidad').value = parseFloat(d.cantidad || 1).toFixed(DEC_CANT);
                tr.querySelector('.input-precio').value = parseFloat(d.precio_unitario || 0).toFixed(DEC_PRECIO);
                tr.querySelector('.input-desc').value = parseFloat(d.descuento || 0).toFixed(2);
                const inputAdi = tr.querySelector('.input-adicional');
                if (inputAdi) inputAdi.value = d.info_adicional || '';

                // Control de visibilidad de Lotes, Caducidad y NUP segÃºn configuraciÃ³n y tipo de producto
                const esInventariable = (d.inventariable == true || d.inventariable == 'true' || d.inventariable == 1) && (d.tipo_produccion !== '02');

                if (EMPRESA_CONFIG.obligatorio_lotes) {
                    const fLote = tr.querySelector('.input-lote');
                    if (fLote) fLote.classList.toggle('d-none', !esInventariable);
                }
                if (EMPRESA_CONFIG.obligatorio_caducidad) {
                    const fCad = tr.querySelector('.input-caducidad');
                    if (fCad) fCad.classList.toggle('d-none', !esInventariable);
                }
                if (EMPRESA_CONFIG.obligatorio_nup) {
                    const fNup = tr.querySelector('.input-nup');
                    if (fNup) fNup.classList.toggle('d-none', !esInventariable);
                }

                // Carga de Lotes y Caducidad
                const selLote = tr.querySelector('.input-lote');
                const selCad = tr.querySelector('.input-caducidad');
                if (selLote) {
                    selLote.dataset.originalLote = d.numero_lote || '';
                }
                if (selCad) {
                    selCad.dataset.originalCad = d.fecha_caducidad || '';
                }

                if (esInventariable && (selLote || selCad)) {
                    cargarLotesFila(tr);
                }

                // Carga de Medidas y Factores
                const selMedida = tr.querySelector('.input-medida');
                if (selMedida && d.id_tipo_medida) {
                    const compatibles = (typeof UNIDADES !== 'undefined') ? UNIDADES.filter(u => u.id_tipo == d.id_tipo_medida) : [];
                    if (compatibles.length > 0) {
                        selMedida.innerHTML = '';
                        compatibles.forEach(u => {
                            const opt = document.createElement('option');
                            opt.value = u.id;
                            opt.textContent = u.nombre;
                            opt.dataset.factor = u.factor_base || 1;
                            if (u.id == d.id_unidad_medida) opt.selected = true;
                            selMedida.appendChild(opt);
                        });

                        // Mostrar solo si la configuraciÃ³n lo permite
                        if (EMPRESA_CONFIG.mostrar_unidad_medida) {
                            selMedida.classList.remove('d-none');
                        }

                        // Establecer factor original para futuras conversiones
                        const baseUnit = compatibles.find(u => u.id == (d.id_medida_base || d.id_unidad_medida));
                        if (baseUnit) {
                            tr.querySelector('.input-factor-original').value = baseUnit.factor_base || 1;
                        }
                    }
                }

                // Tarifa IVA desde impuestos del detalle
                if (d.impuestos && d.impuestos.length > 0) {
                    const ivaTarifa = d.impuestos.find(i => i.codigo_impuesto == '2');
                    if (ivaTarifa) {
                        const selIva = tr.querySelector('.input-iva');
                        if (selIva) {
                            // Comparar numÃ©ricamente: "15.00" (DB) debe coincidir con value="15" (select)
                            const pct = parseFloat(ivaTarifa.tarifa);
                            const opt = Array.from(selIva.options).find(o => Math.abs(parseFloat(o.value) - pct) < 0.001);
                            if (opt) selIva.value = opt.value;
                        }
                    }
                }

                // Carga de Precios y Variantes
                const selPrecios = tr.querySelector('.input-lista-precios');
                const selVar = tr.querySelector('.input-variante');

                // Establecer precio base original (importante para conversiones y variantes)
                // Si no viene en d, usamos el precio unitario actual como base
                const pBase = parseFloat(d.precio_base || d.precio_unitario || 0);
                tr.querySelector('.input-precio-base-original').value = pBase;

                if (selPrecios) {
                    selPrecios.innerHTML = '';
                    // OpciÃ³n base
                    const optBase = document.createElement('option');
                    optBase.value = pBase;
                    optBase.textContent = `P. Base ($${pBase.toFixed(DEC_PRECIO)})`;
                    selPrecios.appendChild(optBase);

                    // Otras opciones de la lista
                    if (d.precios_lista && d.precios_lista.length > 0) {
                        d.precios_lista.forEach(pl => {
                            const opt = document.createElement('option');
                            opt.value = pl.precio;
                            opt.textContent = `${pl.nombre_precio} ($${parseFloat(pl.precio).toFixed(DEC_PRECIO)})`;
                            selPrecios.appendChild(opt);
                        });
                    }

                    // Intentar seleccionar el precio que coincida con el guardado
                    const precioActual = parseFloat(d.precio_unitario || 0);
                    const optMatch = Array.from(selPrecios.options).find(o => Math.abs(parseFloat(o.value) - precioActual) < 0.001);
                    if (optMatch) selPrecios.value = optMatch.value;

                    selPrecios.onchange = () => {
                        tr.querySelector('.input-precio').value = parseFloat(selPrecios.value).toFixed(DEC_PRECIO);
                        syncPrecioIva(tr.querySelector('.input-precio'));
                    };
                }

                if (selVar && d.variantes && d.variantes.length > 0) {
                    const contVarRow = tr.querySelector('.container-variante');
                    if (contVarRow) contVarRow.classList.remove('d-none');
                    selVar.innerHTML = '<option value="">Variantes...</option>';
                    d.variantes.forEach(v => {
                        const opt = document.createElement('option');
                        opt.value = v.precio_adicional || 0;
                        opt.textContent = `${v.nombre}: ${v.valor} (+${parseFloat(v.precio_adicional || 0).toFixed(2)})`;
                        opt.dataset.nombre = v.nombre;
                        opt.dataset.valor = v.valor;
                        selVar.appendChild(opt);
                    });

                    // Identificar si hay una variante aplicada (viendo la info adicional del Ã­tem)
                    if (d.info_adicional) {
                        const optMatch = Array.from(selVar.options).find(o => d.info_adicional.includes(`${o.dataset.nombre}: ${o.dataset.valor}`));
                        if (optMatch) selVar.value = optMatch.value;
                    }

                    selVar.onchange = () => {
                        const base = parseFloat(tr.querySelector('.input-precio-base-original').value) || 0;
                        const add = parseFloat(selVar.value) || 0;
                        tr.querySelector('.input-precio').value = (base + add).toFixed(DEC_PRECIO);

                        const opt = selVar.options[selVar.selectedIndex];
                        const inputAdi = tr.querySelector('.input-adicional');
                        if (opt.value && opt.dataset.nombre) {
                            inputAdi.value = `${opt.dataset.nombre}: ${opt.dataset.valor}`;
                        }
                        syncPrecioIva(tr.querySelector('.input-precio'));
                    };
                }

                calcFila(tr.querySelector('.input-cantidad'));
            });

            // â”€â”€ Formas de pago SRI â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            const contPagos = document.getElementById('m-container-pagos');
            if (contPagos && json.pagos && json.pagos.length > 0) {
                // Eliminar filas extra pero CONSERVAR la primera (tiene las opciones del select generadas por PHP)
                contPagos.querySelectorAll('.row-pago:not(:first-child)').forEach(r => r.remove());
                json.pagos.forEach((p, idx) => {
                    let lastRow;
                    if (idx === 0) {
                        lastRow = contPagos.querySelector('.row-pago');
                    } else {
                        agregarFormaPago();
                        const rows = contPagos.querySelectorAll('.row-pago');
                        lastRow = rows[rows.length - 1];
                    }
                    if (!lastRow) return;
                    const sel = lastRow.querySelector('select[name="f_pago_id[]"]');
                    if (sel) sel.value = p.forma_pago;
                    const val = lastRow.querySelector('input[name="f_pago_valor[]"]');
                    if (val) val.value = parseFloat(p.total || 0).toFixed(2);
                });
            }

            // â”€â”€ Info adicional (limpiar libres, mantener fijas; actualizar fijas desde DB) â”€â”€
            const tbodyInfo = document.getElementById('m-tbody-info-adicional');
            if (tbodyInfo) {
                // Nombres de las filas fijas gestionadas automÃ¡ticamente (no se insertan como libres)
                const NOMBRES_FIJOS = ['cajero', 'vendedor', 'correo del cliente'];
                tbodyInfo.querySelectorAll('tr:not([data-tipo])').forEach(tr => tr.remove());
                json.info_adicional.forEach(ia => {
                    // Saltar entradas que corresponden a filas fijas para evitar duplicados
                    if (NOMBRES_FIJOS.includes((ia.nombre || '').toLowerCase().trim())) return;
                    const tr = document.createElement('tr');
                    tr.className = 'row-info-adicional';
                    const nombre = (ia.nombre || '').replace(/"/g, '&quot;');
                    const valor = (ia.valor || '').replace(/"/g, '&quot;');
                    tr.innerHTML = `
                        <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent input-info-concepto" style="padding:0 4px;height:20px;font-size:0.78rem;" value="${nombre}"></td>
                        <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent input-info-detalle"  style="padding:0 4px;height:20px;font-size:0.78rem;" value="${valor}"></td>
                        <td class="p-0 text-center pe-1">
                            <button type="button" class="btn btn-link btn-sm p-0 m-0 text-danger shadow-none" onclick="this.closest('tr').remove();">
                                <i class="bi bi-x-circle-fill"></i>
                            </button>
                        </td>`;
                    const primeraFija = tbodyInfo.querySelector('tr[data-tipo]');
                    if (primeraFija) tbodyInfo.insertBefore(tr, primeraFija);
                    else tbodyInfo.appendChild(tr);
                });
                // Actualizar el valor de la fila fija de correo si viene en la info adicional
                const correoGuardado = json.info_adicional.find(ia => (ia.nombre || '').toLowerCase().trim() === 'correo del cliente');
                if (correoGuardado) actualizarInfoCorreoCliente(correoGuardado.valor || '');
            }

            // â”€â”€ Propina â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            const inputPropina = document.getElementById('m-input-propina');
            if (inputPropina) inputPropina.value = parseFloat(cab.propina || 0).toFixed(2);

            // â”€â”€ Recalcular totales con todos los datos cargados â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            calcTotales();

            // â”€â”€ Cargar Asiento Contable â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            if (typeof window.fvCargarAsiento === 'function') {
                window.fvCargarAsiento(id);
            }

            // â”€â”€ Cargar Notas de CrÃ©dito relacionadas (con datos reales de la cabecera y padding) â”€â”€
            const nEst = (cab.establecimiento || '').toString().padStart(3, '0');
            const nPunto = (cab.punto_emision || '').toString().padStart(3, '0');
            const nSec = (cab.secuencial || '').toString().padStart(9, '0');
            const numCompleto = nEst + '-' + nPunto + '-' + nSec;
            fvCargarNotasCredito(numCompleto);
            fvCargarGuiasRemision(numCompleto);

            // â”€â”€ Actualizar estado de botones segÃºn el estado de la factura â”€â”€
            fvActualizarEstadoBotones(data.estado);

        } catch (err) {
            console.error('Error cargando factura:', err);
        } finally {
            // Liberar el bloqueo: a partir de aquÃ­ cualquier cambio manual de punto de emisiÃ³n
            // podrÃ¡ volver a cargar el siguiente consecutivo normalmente
            FV_BLOQUEAR_SECUENCIAL = false;
        }
    };

    async function fetchHistorialVenta(id) {
        const container = document.getElementById('auditoriaTimelineVenta');
        if (!container || !id) return;

        try {
            const resp = await fetch(`${B_URL}/${RUTA_MODULO}/getHistorialAjax?id=${id}&tabla=ventas_cabecera`);
            const json = await resp.json();

            if (json.ok && json.data.length > 0) {
                let html = '<div class="timeline-border position-absolute h-100 border-start border-2 border-primary border-opacity-10" style="left: 10px; top: 0;"></div>';

                json.data.forEach(log => {
                    const icon = log.accion.includes('Crear') ? 'bi-plus-circle-fill text-success' :
                        log.accion.includes('Actualizar') ? 'bi-pencil-fill text-primary' :
                        log.accion.includes('Anular') ? 'bi-slash-circle-fill text-warning' :
                        log.accion.includes('Eliminar') ? 'bi-trash-fill text-danger' :
                        'bi-clock-history text-secondary';

                    html += `
                        <div class="timeline-item position-relative mb-3 ps-4">
                            <div class="timeline-icon position-absolute rounded-circle bg-white d-flex align-items-center justify-content-center shadow-sm border" 
                                 style="left: 0; top: 0; width: 22px; height: 22px; z-index: 2;">
                                 <i class="bi ${icon}" style="font-size: 0.7rem;"></i>
                            </div>
                            <div class="timeline-content">
                                <div class="d-flex justify-content-between align-items-center mb-0">
                                    <span class="fw-bold" style="font-size: 0.75rem;">${log.accion}</span>
                                    <span class="text-muted" style="font-size: 0.65rem;">${log.created_at}</span>
                                </div>
                                <div class="text-muted mb-1" style="font-size: 0.7rem;">
                                    <i class="bi bi-person me-1"></i> ${log.usuario_nombre || 'SISTEMA'}
                                </div>
                                <div class="bg-light rounded p-1 border border-light-subtle shadow-sm" style="font-size: 0.65rem;">
                                    ${renderDetalleHistorialVenta(log.detalles)}
                                </div>
                            </div>
                        </div>
                    `;
                });
                container.innerHTML = html;
            } else {
                container.innerHTML = `<div class="text-center py-4 text-muted small">No hay historial de cambios.</div>`;
            }
        } catch (e) {
            container.innerHTML = `<div class="text-center py-3 text-danger small">Error de carga.</div>`;
        }
    }

    async function fvCargarNotasCredito(numeroFactura) {
        const tbody = document.getElementById('m-tbody-notas-credito');
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4"><span class="spinner-border spinner-border-sm me-2"></span> Cargando notas de crÃ©dito...</td></tr>';

        try {
            const resp = await fetch(`${B_URL}/${RUTA_MODULO}/getNotasCreditoAjax?numero=${numeroFactura}`);
            const json = await resp.json();

            if (json.ok && json.data && json.data.length > 0) {
                let html = '';
                json.data.forEach(nc => {
                    const estado = (nc.estado || 'borrador').toLowerCase();
                    const estadoClass = (estado === 'autorizado' || estado === 'aprobado') ? 'bg-success' :
                        (estado === 'anulado') ? 'bg-danger' : 'bg-secondary';

                    html += `
                        <tr class="nc-row-fv" role="button" 
                            data-nc='${JSON.stringify(nc).replace(/'/g, "&apos;")}' 
                            onclick='abrirModalEditarNCDesdeFV(JSON.parse(this.dataset.nc))'>
                            <td class="ps-3">${nc.fecha_emision ? nc.fecha_emision.split(' ')[0] : 'â€”'}</td>
                            <td><code class="text-primary">${nc.establecimiento}-${nc.punto_emision}-${nc.secuencial}</code></td>
                            <td>${nc.motivo || 'â€”'}</td>
                            <td><span class="badge ${estadoClass} bg-opacity-10 text-${estadoClass.replace('bg-', '')} border border-${estadoClass.replace('bg-', '')} border-opacity-25">${estado.toUpperCase()}</span></td>
                            <td class="text-end pe-3 fw-bold">$${parseFloat(nc.importe_total || 0).toFixed(2)}</td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
            } else {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-info-circle me-1"></i> No se han encontrado notas de crÃ©dito para esta factura.</td></tr>';
            }
        } catch (e) {
            console.error('Error al cargar notas de crÃ©dito:', e);
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-danger">Error al cargar datos.</td></tr>';
        }
    }

    function renderDetalleHistorialVenta(detalle) {
        if (!detalle || detalle.length === 0) return '<span class="text-muted">Sin detalles especÃ­ficos</span>';
        if (typeof detalle === 'string') return detalle;
        if (Array.isArray(detalle)) {
            return `<ul class="list-unstyled mb-0">
                ${detalle.map(d => {
                    if (typeof d === 'object') {
                        const antes = d.antes !== null ? `<span class="text-decoration-line-through text-muted">${d.antes}</span> ` : '';
                        return `<li><i class="bi bi-dot"></i> <span class="fw-bold">${d.campo}:</span> ${antes}<i class="bi bi-arrow-right mx-1"></i> ${d.despues}</li>`;
                    }
                    return ` < li > < i class = "bi bi-dot" > < /i> ${d}</li > `;
                }).join('')}
            </ul>`;
        }
        return '<span class="text-muted">AcciÃ³n registrada</span>';
    }

    window.eliminarFacturaBorrador = async function() {
        if (!FV_ID_ACTIVO) return;

        const result = await Swal.fire({
            title: 'Â¿EstÃ¡s seguro?',
            text: "Esta acciÃ³n eliminarÃ¡ el borrador permanentemente y no se puede deshacer.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="bi bi-trash me-2"></i>SÃ­, eliminar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            focusCancel: true
        });

        if (!result.isConfirmed) return;

        const btn = document.getElementById('btnEliminarFacturaModal');
        const btnOriginalHtml = btn?.innerHTML;
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Eliminando...';
        }

        try {
            const fd = new FormData();
            fd.append('id', FV_ID_ACTIVO);
            const resp = await fetch(`${B_URL}/${RUTA_MODULO}/eliminarAjax`, {
                method: 'POST',
                body: fd
            });
            const json = await resp.json();

            if (json.ok) {
                FV_ID_ACTIVO = 0; // Limpiar ID activo
                modalMain.hide();

                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
                Toast.fire({
                    icon: 'success',
                    title: json.mensaje || 'Factura eliminada correctamente.'
                });

                if (typeof window.FV_fetchSearch === 'function') {
                    window.FV_fetchSearch(window.FV_currentPage || 1);
                } else {
                    location.reload();
                }
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al eliminar',
                    text: json.mensaje || 'No se pudo completar la operaciÃ³n.',
                    confirmButtonColor: '#d33'
                });
            }
        } catch (err) {
            console.error(err);
            Swal.fire({
                icon: 'error',
                title: 'Error de conexiÃ³n',
                text: 'No se pudo conectar con el servidor. Intente nuevamente.',
                confirmButtonColor: '#d33'
            });
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = btnOriginalHtml;
            }
        }
    };

    /**
     * Calcula el último día hábil permitido para anular según las reglas del SRI:
     * Día 7 del mes siguiente a la emisión. Si cae en sábado/domingo, se extiende
     * al siguiente lunes (no se consideran feriados nacionales aquí).
     */
    function fvFechaLimiteAnulacion(fechaEmision) {
        const [y, m] = fechaEmision.split('-').map(Number);
        // Mes siguiente (0-indexed)
        const mesLimite = m === 12 ? 0 : m;         // 0 = enero si emisión fue en diciembre
        const anioLimite = m === 12 ? y + 1 : y;
        let limite = new Date(anioLimite, mesLimite, 7); // día 7 del mes siguiente
        // Si cae en sábado (6) → lunes; si cae en domingo (0) → lunes
        if (limite.getDay() === 6) limite.setDate(9);   // sábado → lunes
        if (limite.getDay() === 0) limite.setDate(8);   // domingo → lunes
        return limite;
    }

    window.anularFactura = async function() {
        if (!FV_ID_ACTIVO) return;

        // ── Regla 1: Consumidor Final ────────────────────────────────────────
        const esConsumidorFinal = FV_CLIENTE_RUC === '9999999999999'
            || document.getElementById('sri-identificacion-cliente')?.value?.trim() === '9999999999999';

        if (esConsumidorFinal) {
            await Swal.fire({
                icon: 'error',
                title: 'No se puede anular',
                html: `Las facturas emitidas a <strong>Consumidor Final</strong> no se pueden anular<br>
                       ni tampoco se les puede emitir una nota de crédito una vez transmitidas.<br>
                       <small class="text-muted">(Normativa SRI)</small>`,
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#dc3545'
            });
            return;
        }

        // ── Regla 2: Plazo del día 7 del mes siguiente ───────────────────────
        if (FV_FECHA_EMISION) {
            const hoy = new Date();
            hoy.setHours(0, 0, 0, 0);
            const limiteAnulacion = fvFechaLimiteAnulacion(FV_FECHA_EMISION);
            limiteAnulacion.setHours(23, 59, 59, 999);

            if (hoy > limiteAnulacion) {
                const fmtLimite = limiteAnulacion.toLocaleDateString('es-EC', { day: '2-digit', month: '2-digit', year: 'numeric' });
                await Swal.fire({
                    icon: 'error',
                    title: 'Fuera de plazo',
                    html: `El plazo para anular esta factura venció el <strong>${fmtLimite}</strong>.<br>
                           <small class="text-muted">El SRI permite anular hasta el día 7 del mes siguiente a la emisión<br>
                           (o el siguiente día hábil si cae en fin de semana).</small>`,
                    confirmButtonText: 'Entendido',
                    confirmButtonColor: '#dc3545'
                });
                return;
            }
        }

        const result = await Swal.fire({
            title: '¿Anular Factura?',
            text: "Esta acción marcará la factura como ANULADA y reintegrará todo el stock al inventario. No se puede revertir.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f59e0b',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="bi bi-slash-circle me-2"></i>Sí, anular factura',
            cancelButtonText: 'Cancelar',
            reverseButtons: true
        });

        if (!result.isConfirmed) return;

        const btn = document.getElementById('btnAnularFacturaModal');
        const btnOriginalHtml = btn?.innerHTML;
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Anulando...';
        }

        try {
            const fd = new FormData();
            fd.append('id', FV_ID_ACTIVO);
            const resp = await fetch(`${B_URL}/${RUTA_MODULO}/anularAjax`, {
                method: 'POST',
                body: fd
            });
            const json = await resp.json();

            if (json.ok) {
                modalMain.hide();

                Swal.fire({
                    icon: 'success',
                    title: 'Anulada',
                    text: json.mensaje || 'Factura anulada e inventario actualizado.',
                    timer: 3000
                });

                if (typeof window.FV_fetchSearch === 'function') {
                    window.FV_fetchSearch(window.FV_currentPage || 1);
                } else {
                    location.reload();
                }
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al anular',
                    text: json.mensaje || 'No se pudo completar la operaciÃ³n.',
                    confirmButtonColor: '#d33'
                });
            }
        } catch (err) {
            console.error(err);
            Swal.fire({
                icon: 'error',
                title: 'Error de conexiÃ³n',
                text: 'Intente nuevamente.',
                confirmButtonColor: '#d33'
            });
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = btnOriginalHtml;
            }
        }
    };
</script>

<!-- Dropdown Global para Productos (evita recortes por overflow) -->
<div id="m-dropdown-productos-global" class="list-group shadow position-fixed d-none" style="z-index: 9999; min-width: 400px; max-height: 250px; overflow-y: auto; background-color: white;"></div>

<!-- Modal Descuento RÃ¡pido -->
<div class="modal fade" id="modalDescuentoRapido" tabindex="-1" aria-hidden="true" style="z-index: 2050;">
    <div class="modal-dialog modal-sm modal-dialog-centered" style="max-width: 320px;">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light py-1 px-3 border-bottom-0">
                <h6 class="modal-title small text-primary" style="font-size: 0.85rem;"><i class="bi bi-percent me-1"></i> Aplicar Descuento</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="font-size: 0.5rem; padding: 0.8rem;"></button>
            </div>
            <div class="modal-body py-2 px-3">
                <div class="mb-2 text-center">
                    <label class="small text-muted mb-1 d-block text-start" style="font-size: 0.75rem;">Modo</label>
                    <div class="btn-group w-100 btn-group-sm" role="group">
                        <input type="radio" class="btn-check" name="tipoDesc" id="descPorc" value="P" checked onchange="validarCalculoModal()">
                        <label class="btn btn-outline-primary py-1" for="descPorc" style="font-size: 0.75rem;">Porcentaje (%)</label>
                        <input type="radio" class="btn-check" name="tipoDesc" id="descVal" value="V" onchange="validarCalculoModal()">
                        <label class="btn btn-outline-primary py-1" for="descVal" style="font-size: 0.75rem;">Valor ($)</label>
                    </div>
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="small text-muted mb-1 d-block" style="font-size: 0.75rem;">Ingreso</label>
                        <input type="number" id="inputValDescModal" class="form-control form-control-sm text-center shadow-none border-secondary-subtle" value="0" step="any" oninput="validarCalculoModal()" style="font-size: 0.9rem;">
                    </div>
                    <div class="col-6">
                        <label class="small text-muted mb-1 d-block" style="font-size: 0.75rem;">Calculado ($)</label>
                        <input type="number" id="inputCalcDescModal" class="form-control form-control-sm text-center shadow-none border-0 bg-light text-primary" value="0" readonly style="font-size: 0.9rem;">
                    </div>
                </div>

                <div class="form-check form-switch mb-1" style="min-height: auto;">
                    <input class="form-check-input" type="checkbox" id="checkAplicarTodo" style="height: 1rem; width: 1.8rem; margin-top: 0.2rem;">
                    <label class="form-check-label text-muted ms-1" for="checkAplicarTodo" style="font-size: 0.7rem; vertical-align: middle;">Aplicar a todos los Ã­tems</label>
                </div>
            </div>
            <div class="modal-footer bg-light p-2 border-top-0 justify-content-center">
                <button type="button" class="btn btn-primary btn-sm w-100 py-1" onclick="confirmarDescuento()" style="font-size: 0.8rem;">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<script>
    let trDescuentoTarget = null;
    let modalDescInst = null;

    function abrirModalDescuento(btn) {
        trDescuentoTarget = btn.closest('tr');
        if (!modalDescInst) {
            modalDescInst = new bootstrap.Modal(document.getElementById('modalDescuentoRapido'));
        }

        const currentVal = parseFloat(trDescuentoTarget.querySelector('.input-desc').value) || 0;
        const inputModal = document.getElementById('inputValDescModal');
        inputModal.value = currentVal;

        if (currentVal > 0) {
            document.getElementById('descVal').checked = true;
            document.getElementById('descPorc').checked = false;
        } else {
            document.getElementById('descPorc').checked = true;
            document.getElementById('descVal').checked = false;
        }

        document.getElementById('checkAplicarTodo').checked = false;
        validarCalculoModal();
        modalDescInst.show();

        setTimeout(() => {
            inputModal.focus();
            inputModal.select();
        }, 400);
    }

    function validarCalculoModal() {
        if (!trDescuentoTarget) return;

        const tipo = document.querySelector('input[name="tipoDesc"]:checked').value;
        const valorIngresado = parseFloat(document.getElementById('inputValDescModal').value) || 0;
        const inputCalculado = document.getElementById('inputCalcDescModal');

        const inputCant = trDescuentoTarget.querySelector('.input-cantidad');
        const inputPrecio = trDescuentoTarget.querySelector('.input-precio');
        const cant = parseFloat(inputCant.value) || 1;
        const precio = parseFloat(inputPrecio.value) || 0;
        const subtotal = precio * cant;

        let res = 0;
        if (tipo === 'P') {
            res = subtotal * (valorIngresado / 100);
        } else {
            res = valorIngresado;
        }

        inputCalculado.value = res.toFixed(4);
    }

    function confirmarDescuento() {
        const tipo = document.querySelector('input[name="tipoDesc"]:checked').value;
        const valorIngresado = parseFloat(document.getElementById('inputValDescModal').value) || 0;
        const aplicarTodo = document.getElementById('checkAplicarTodo').checked;

        if (valorIngresado < 0) {
            return Swal.fire({
                icon: 'warning',
                title: 'Valor invÃ¡lido',
                text: 'El valor no puede ser negativo.',
                confirmButtonColor: '#ffc107'
            });
        }

        const filas = aplicarTodo ?
            document.querySelectorAll('#m-tbodyDetalle tr.row-detalle') : [trDescuentoTarget];

        filas.forEach(tr => {
            const inputDesc = tr.querySelector('.input-desc');
            let finalRowDesc = 0;
            if (tipo === 'P') {
                const p = parseFloat(tr.querySelector('.input-precio').value) || 0;
                const c = parseFloat(tr.querySelector('.input-cantidad').value) || 1;
                finalRowDesc = (p * c) * (valorIngresado / 100);
            } else {
                finalRowDesc = valorIngresado;
            }

            inputDesc.value = finalRowDesc.toFixed(4);
            inputDesc.dispatchEvent(new Event('input', {
                bubbles: true
            }));
        });
    }


    // Actualizar saldos de todos los Ã­tems al cambiar la bodega de la cabecera
    document.getElementById('m-select-bodega')?.addEventListener('change', function() {
        document.querySelectorAll('.row-detalle').forEach(row => {
            if (row.dataset.idProducto) {
                cargarLotesFila(row);
            }
        });
    });

    /**
     * Puente entre Factura de Venta y Notas de CrÃ©dito
     */
    async function abrirModalNuevaNCDesdeFV() {
        if (!FV_ID_ACTIVO) return;
        
        // Obtener datos de la factura actual desde el formulario
        const idCliente = document.getElementById('m-id-cliente').value;
        const nombreCliente = document.getElementById('m-search-cliente').value;
        const rucCliente = document.getElementById('m-lbl-cliente-ruc').textContent;
        const establecimiento = (document.getElementById('m-select-puntos').options[document.getElementById('m-select-puntos').selectedIndex].dataset.codEst || '').toString().padStart(3, '0');
        const puntoEmision = (document.getElementById('m-select-puntos').options[document.getElementById('m-select-puntos').selectedIndex].dataset.codPunto || '').toString().padStart(3, '0');
        const secuencial = (document.getElementById('m-input-secuencial').value || '').toString().padStart(9, '0');
        const fechaEmision = document.getElementsByName('fecha_emision')[0].value;
        const importeTotal = parseFloat(document.getElementById('m-lbl-total').textContent.replace(/[^0-9.]/g, '')) || 0;
        const estadoFactura = document.getElementById('sri-badge-estado').textContent.toLowerCase();

        // Solo permitir si la factura estÃ¡ autorizada
        if (estadoFactura !== 'autorizado') {
            return Swal.fire('AtenciÃ³n', 'Solo se pueden generar notas de crÃ©dito para facturas en estado "autorizado".', 'warning');
        }

        // Validar suma de NC existentes
        const tbody = document.getElementById('m-tbody-notas-credito');
        let sumaNC = 0;
        tbody.querySelectorAll('.nc-row-fv').forEach(row => {
            const rowDataStr = row.getAttribute('onclick').match(/abrirModalEditarNCDesdeFV\((.*)\)/)[1];
            try {
                const rowData = JSON.parse(rowDataStr.replace(/&quot;/g, '"'));
                if (rowData.estado !== 'anulado') {
                    sumaNC += parseFloat(rowData.importe_total || 0);
                }
            } catch (e) {}
        });

        if (sumaNC >= importeTotal) {
            return Swal.fire('AtenciÃ³n', 'La suma de las notas de crÃ©dito ya cubre el total de la factura.', 'warning');
        }

        // Abrir modal de NC
        if (typeof window.NC_abrirModalNuevo === 'function') {
            window.NC_abrirModalNuevo();
            
            // Pre-seleccionar factura y cliente en el modal de NC
            setTimeout(() => {
                window.NC_seleccionarCliente({ id: idCliente, nombre: nombreCliente, identificacion: rucCliente });
                window.NC_seleccionarFactura({
                    id: FV_ID_ACTIVO,
                    establecimiento: establecimiento,
                    punto_emision: puntoEmision,
                    secuencial: secuencial,
                    fecha_emision: fechaEmision,
                    importe_total: importeTotal,
                    cliente_nombre: nombreCliente,
                    cliente_ruc: rucCliente,
                    estado: estadoFactura
                });
            }, 500);
        }
    }

    function abrirModalEditarNCDesdeFV(nc) {
        if (typeof window.NC_abrirModalNC === 'function') {
            // Mock de un objeto row para NC_abrirModalNC
            const rowMock = { dataset: { row: JSON.stringify(nc) } };
            window.NC_abrirModalNC(rowMock);
        }
    }

    // Sobrescribir el cierre del modal de NC para refrescar la pestaÃ±a de NC en la factura
    const modalNCEl = document.getElementById('modalNC');
    window.fvRefrescarDatosModal = function() {
        if (FV_ID_ACTIVO) {
            // Refrescar los datos de la factura en el modal (totales, saldos, notas de crÃ©dito, etc.)
            // Enviamos un objeto que mantenga el ID actual
            const mockRow = { dataset: { row: JSON.stringify({ id: FV_ID_ACTIVO }) } };
            window.abrirModalFacturaVer(mockRow);

            // Refrescar tambiÃ©n el listado de facturas en el fondo
            if (typeof window.FV_fetchSearch === 'function') {
                window.FV_fetchSearch(window.FV_currentPage || 1);
            }
        }
    }

    // Sobrescribir el cierre del modal de NC para refrescar la factura
    if (modalNCEl) {
        modalNCEl.addEventListener('hidden.bs.modal', function () {
            window.fvRefrescarDatosModal();
        });
    }

    async function fvCargarGuiasRemision(numeroFactura) {
        const tbody = document.getElementById('m-tbody-guias-remision');
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4"><span class="spinner-border spinner-border-sm me-2"></span> Cargando guÃ­as...</td></tr>';

        try {
            const resp = await fetch(`${B_URL}/${RUTA_MODULO}/getGuiasAjax?numero=${numeroFactura}`);
            const json = await resp.json();

            if (json.ok && json.data && json.data.length > 0) {
                let html = '';
                json.data.forEach(gr => {
                    const estado = (gr.estado || 'borrador').toLowerCase();
                    const estadoClass = (estado === 'autorizado' || estado === 'aprobado') ? 'bg-success' :
                        (estado === 'anulado') ? 'bg-danger' : 'bg-secondary';

                    html += `
                        <tr class="gr-row-fv" role="button" 
                            data-gr='${JSON.stringify(gr).replace(/'/g, "&apos;")}' 
                            onclick='abrirModalEditarGRDesdeFV(JSON.parse(this.dataset.gr))'>
                            <td class="ps-3">${gr.fecha_emision ? gr.fecha_emision.split(' ')[0] : 'â€”'}</td>
                            <td><code class="text-primary">${gr.establecimiento}-${gr.punto_emision}-${gr.secuencial}</code></td>
                            <td>${gr.transportista_nombre || 'â€”'}</td>
                            <td><span class="badge ${estadoClass} bg-opacity-10 text-${estadoClass.replace('bg-', '')} border border-${estadoClass.replace('bg-', '')} border-opacity-25">${estado.toUpperCase()}</span></td>
                            <td class="pe-3">${gr.placa || 'â€”'}</td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
            } else {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-info-circle me-1"></i> No se han encontrado guÃ­as de remisiÃ³n para esta factura.</td></tr>';
            }
        } catch (e) {
            console.error('Error al cargar guÃ­as:', e);
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-danger">Error al cargar datos.</td></tr>';
        }
    }

    async function abrirModalNuevaGRDesdeFV() {
        if (!FV_ID_ACTIVO) return;
        
        const idCliente = document.getElementById('m-id-cliente').value;
        const nombreCliente = document.getElementById('m-search-cliente').value;
        const rucCliente = document.getElementById('m-lbl-cliente-ruc').textContent;
        const establecimiento = (document.getElementById('m-select-puntos').options[document.getElementById('m-select-puntos').selectedIndex].dataset.codEst || '').toString().padStart(3, '0');
        const puntoEmision = (document.getElementById('m-select-puntos').options[document.getElementById('m-select-puntos').selectedIndex].dataset.codPunto || '').toString().padStart(3, '0');
        const secuencial = (document.getElementById('m-input-secuencial').value || '').toString().padStart(9, '0');
        const fechaEmision = document.getElementsByName('fecha_emision')[0].value;
        const numAutorizacion = document.getElementById('sri-numero-autorizacion').value;
        const estadoFactura = document.getElementById('sri-badge-estado').textContent.toLowerCase();

        // Solo permitir si la factura estÃ¡ autorizada (aunque las guÃ­as a veces se pueden emitir antes, seguimos el concepto de NC si el usuario lo pidiÃ³ asÃ­)
        if (estadoFactura !== 'autorizado') {
            return Swal.fire('AtenciÃ³n', 'Solo se pueden generar guÃ­as de remisiÃ³n para facturas en estado "autorizado".', 'warning');
        }

        if (typeof window.GR_abrirCrear === 'function') {
            window.GR_abrirCrear();
            
            setTimeout(() => {
                // Pre-seleccionar factura y cliente en el modal de GR
                if (typeof window.GR_seleccionarCliente === 'function') {
                    window.GR_seleccionarCliente(idCliente, nombreCliente, rucCliente);
                }
                
                // Llenar datos de sustento
                const inpNumDoc = document.getElementById('gr-num-doc-sustento');
                if (inpNumDoc) inpNumDoc.value = `${establecimiento}-${puntoEmision}-${secuencial}`;
                
                const inpAutDoc = document.getElementById('gr-num-aut-doc-sustento');
                if (inpAutDoc) inpAutDoc.value = numAutorizacion;

                const inpFechaDoc = document.getElementById('gr-fecha-emision-doc-sustento');
                if (inpFechaDoc) inpFechaDoc.value = fechaEmision;

                // Seleccionar tipo 01 (Factura)
                const selTipo = document.getElementById('gr-cod-doc-sustento');
                if (selTipo) selTipo.value = '01';

                // Cargar detalles si es posible
                if (typeof window.GR_cargarDetallesDesdeFactura === 'function') {
                    window.GR_cargarDetallesDesdeFactura(FV_ID_ACTIVO);
                }
            }, 500);
        }
    }

    function abrirModalEditarGRDesdeFV(gr) {
        if (typeof window.GR_abrirEditar === 'function') {
            window.GR_abrirEditar(gr);
        }
    }

    // Sobrescribir el cierre del modal de GR para refrescar la factura
    const modalGREl = document.getElementById('modalGuiaRemision');
    if (modalGREl) {
        modalGREl.addEventListener('hidden.bs.modal', function () {
            window.fvRefrescarDatosModal();
        });
    }

    // â”€â”€ GESTIÃ”N DE ASIENTO CONTABLE INTERACTIVO Y EMULADOR EN CALIENTE â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    window.ASIENTO_MANUAL = false;

    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    // Regenera el asiento con los valores actuales de la factura (debounced para no spamear).
    // Se llama desde calcTotales() cada vez que cambian productos, precios o cantidades.
    window.fvDebouncedRecalcularAsiento = debounce(function() {
        window.ASIENTO_MANUAL = false;
        window.fvCargarAsiento(window.FV_ID_ACTIVO || 0, false, true);
    }, 600);

    window.fvAgregarLineaAsiento = function(idCuenta = '', codigo = '', nombre = '', debe = 0, haber = 0, referencia = '') {
        const tbody = document.getElementById('tbody-asiento-contable');
        if (!tbody) return;

        // Remover fila de "Cargando" o "Sin datos"
        if (tbody.querySelector('td[colspan="4"]')) {
            tbody.innerHTML = '';
        }

        const tr = document.createElement('tr');
        tr.className = 'asiento-linea-row';
        tr.innerHTML = `
            <td class="ps-3 position-relative align-middle p-0">
                <input type="text" class="form-control border-0 bg-transparent input-cuenta-nombre" placeholder="Escriba código o cuenta contable..." value="${nombre ? (codigo ? codigo + ' - ' + nombre : nombre) : ''}" style="padding:0 4px;height:20px;font-size:0.78rem;">
                <input type="hidden" class="input-id-cuenta-contable" value="${idCuenta}">
                <div class="list-group position-absolute w-100 shadow rounded-3 d-none select-cuenta-dropdown" style="z-index: 1050; max-height: 200px; overflow-y: auto;"></div>
            </td>
            <td class="align-middle p-0"><input type="number" step="0.01" class="form-control text-end border-0 bg-transparent fw-medium input-debe text-primary" placeholder="0.00" value="${parseFloat(debe || 0).toFixed(2) === '0.00' ? '' : parseFloat(debe || 0).toFixed(2)}" style="padding:0 4px;height:20px;font-size:0.78rem;"></td>
            <td class="align-middle p-0"><input type="number" step="0.01" class="form-control text-end border-0 bg-transparent fw-medium input-haber text-primary" placeholder="0.00" value="${parseFloat(haber || 0).toFixed(2) === '0.00' ? '' : parseFloat(haber || 0).toFixed(2)}" style="padding:0 4px;height:20px;font-size:0.78rem;"></td>
            <td class="align-middle p-0"><input type="text" class="form-control border-0 bg-transparent input-referencia text-muted fst-italic" placeholder="Ej: Factura # 001-001-000000001" value="${referencia}" style="padding:0 4px;height:20px;font-size:0.78rem;"></td>
            <td class="text-center p-0 align-middle" style="width: 40px;">
                <button type="button" class="btn btn-link btn-sm text-danger p-0 shadow-none border-0" onclick="this.closest('tr').remove(); window.ASIENTO_MANUAL = true; window.fvRecalcularTotalesAsiento();" title="Eliminar línea">
                    <i class="bi bi-trash3 fs-6"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);

        const inpCuenta = tr.querySelector('.input-cuenta-nombre');
        const hiddenCuenta = tr.querySelector('.input-id-cuenta-contable');
        const dropdown = tr.querySelector('.select-cuenta-dropdown');
        const inpDebe = tr.querySelector('.input-debe');
        const inpHaber = tr.querySelector('.input-haber');
        const inpRef = tr.querySelector('.input-referencia');

        // Autocompletado predictivo para cuentas de movimiento (nivel 5)
        inpCuenta.addEventListener('input', debounce(async (e) => {
            const q = e.target.value.trim();
            if (q.length < 2) {
                dropdown.classList.add('d-none');
                return;
            }

            try {
                const resp = await fetch(`${B_URL}/modulos/plan-cuentas/searchAjaxCuentas?q=${encodeURIComponent(q)}`);
                const json = await resp.json();

                dropdown.innerHTML = '';
                if (json.data && json.data.length > 0) {
                    json.data.forEach(cuenta => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'list-group-item list-group-item-action py-1 px-2 border-0 small text-start';
                        btn.innerHTML = `<code class="text-secondary me-2">${cuenta.codigo}</code> <span class="fw-medium">${cuenta.nombre}</span>`;
                        btn.onmousedown = (evt) => {
                            evt.preventDefault();
                            hiddenCuenta.value = cuenta.id;
                            inpCuenta.value = `${cuenta.codigo} - ${cuenta.nombre}`;
                            dropdown.classList.add('d-none');
                            window.ASIENTO_MANUAL = true;
                        };
                        dropdown.appendChild(btn);
                    });
                    dropdown.classList.remove('d-none');
                } else {
                    dropdown.innerHTML = '<div class="list-group-item small text-muted text-center py-2">No se encontraron cuentas contables</div>';
                    dropdown.classList.remove('d-none');
                }
            } catch (err) {
                console.error('Error al autocompletar cuentas:', err);
            }
        }, 250));

        // Cerrar dropdown al perder el foco
        inpCuenta.addEventListener('blur', () => {
            setTimeout(() => dropdown.classList.add('d-none'), 200);
        });

        // Event listeners para cambios manuales en montos y referencias
        inpDebe.addEventListener('input', () => {
            if (parseFloat(inpDebe.value) > 0) {
                inpHaber.value = '';
            }
            window.ASIENTO_MANUAL = true;
            fvRecalcularTotalesAsiento();
        });

        inpHaber.addEventListener('input', () => {
            if (parseFloat(inpHaber.value) > 0) {
                inpDebe.value = '';
            }
            window.ASIENTO_MANUAL = true;
            fvRecalcularTotalesAsiento();
        });

        inpRef.addEventListener('input', () => {
            window.ASIENTO_MANUAL = true;
        });

        fvRecalcularTotalesAsiento();
    };

    window.fvRecalcularTotalesAsiento = function() {
        let totalDebe = 0;
        let totalHaber = 0;

        document.querySelectorAll('.asiento-linea-row').forEach(tr => {
            const debe = parseFloat(tr.querySelector('.input-debe').value) || 0;
            const haber = parseFloat(tr.querySelector('.input-haber').value) || 0;
            totalDebe += debe;
            totalHaber += haber;
        });

        const lblDebe = document.getElementById('lbl-asiento-total-debe');
        const lblHaber = document.getElementById('lbl-asiento-total-haber');
        const lblDiferencia = document.getElementById('lbl-asiento-diferencia');
        const badgeCuadre = document.getElementById('badge-asiento-cuadre');

        if (lblDebe) lblDebe.textContent = totalDebe.toFixed(2);
        if (lblHaber) lblHaber.textContent = totalHaber.toFixed(2);

        const diff = Math.abs(totalDebe - totalHaber);
        if (lblDiferencia) {
            lblDiferencia.textContent = diff.toFixed(2);
            if (diff >= 0.005) {
                lblDiferencia.classList.add('text-danger');
                lblDiferencia.classList.remove('text-success');
            } else {
                lblDiferencia.classList.remove('text-danger');
                lblDiferencia.classList.add('text-success');
            }
        }

        if (badgeCuadre) {
            if (diff < 0.005 && (totalDebe > 0 || totalHaber > 0)) {
                badgeCuadre.className = 'badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2';
                badgeCuadre.textContent = 'Cuadrado';
            } else {
                badgeCuadre.className = 'badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2';
                badgeCuadre.textContent = 'Descuadrado';
            }
        }

        const countLineas = document.getElementById('fv-count-asiento-lineas');
        if (countLineas) countLineas.textContent = document.querySelectorAll('.asiento-linea-row').length;
    };


    window.fvCargarAsiento = async function(idVenta = 0, sugerir = false, forceRecalculate = false) {
        const tbody = document.getElementById('tbody-asiento-contable');
        if (!tbody) return;

        // Si ya está marcado como manual por edición del usuario, respetar y no sobreescribir.
        // forceRecalculate=true se usa cuando cambian valores de la factura: siempre regenerar.
        if (window.ASIENTO_MANUAL && !forceRecalculate) return;

        try {
            // Hot values de la factura para propuesta en caliente
            const subtotal = parseFloat(document.getElementById('m-lbl-subtotal')?.textContent) || 0;
            const desc = parseFloat(document.getElementById('m-lbl-descuento')?.textContent) || 0;
            const total = parseFloat(document.getElementById('m-lbl-total')?.textContent) || 0;
            const propina = parseFloat(document.getElementById('m-input-propina')?.value) || 0;

            // Extraer porcentaje e importes de IVA en caliente
            const ivas = [];
            document.querySelectorAll('#m-lbl-ivas-grupo div').forEach(div => {
                const text = div.querySelector('span:first-child')?.textContent || '';
                const val = parseFloat(div.querySelector('span:last-child')?.textContent) || 0;
                const m = text.match(/(\d+)%/);
                if (m) {
                    ivas.push({ porcentaje: parseInt(m[1]), valor: val });
                }
            });

            const idCliente = document.getElementById('m-id-cliente')?.value || '';

            // Al forzar recálculo, usar id_venta=0 para obtener una sugerencia fresca
            // basada en los valores actuales del formulario, ignorando el asiento guardado.
            const idParaQuery = forceRecalculate ? 0 : idVenta;

            const query = new URLSearchParams({
                id_venta: idParaQuery,
                id_cliente: idCliente,
                subtotal: subtotal,
                descuento: desc,
                total: total,
                propina: propina,
                ivas: JSON.stringify(ivas),
                sugerir: sugerir ? 'true' : 'false'
            });

            const resp = await fetch(`${B_URL}/${RUTA_MODULO}/getAsientoSugeridoAjax?${query.toString()}`);
            const json = await resp.json();

            if (json.ok && json.detalles && json.detalles.length > 0) {
                tbody.innerHTML = '';
                json.detalles.forEach(det => {
                    window.fvAgregarLineaAsiento(
                        det.id_cuenta_contable,
                        det.cuenta_codigo || det.codigo_cuenta || '',
                        det.cuenta_nombre || det.nombre_cuenta || '',
                        parseFloat(det.debe || 0),
                        parseFloat(det.haber || 0),
                        det.documento_referencia || det.referencia_detalle || det.referencia || ''
                    );
                });
                window.ASIENTO_MANUAL = false;
                fvRecalcularTotalesAsiento();

            } else {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted"><i class="bi bi-info-circle me-1"></i> Sin asiento contable, guarda o actualiza este documento para generar el asiento.</td></tr>';
            }
        } catch (err) {
            console.error('Error al cargar asiento contable:', err);
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i> Error al cargar datos del asiento contable.</td></tr>';
        }
    };

    
    window.fvCapturarDetallesAsiento = function() {
        const detalles = [];
        document.querySelectorAll('.asiento-linea-row').forEach(tr => {
            const idCuenta = tr.querySelector('.input-id-cuenta-contable').value;
            const debe = parseFloat(tr.querySelector('.input-debe').value) || 0;
            const haber = parseFloat(tr.querySelector('.input-haber').value) || 0;
            const referencia = tr.querySelector('.input-referencia').value;

            if (idCuenta) {
                detalles.push({
                    id_cuenta_contable: parseInt(idCuenta),
                    debe: debe,
                    haber: haber,
                    referencia_detalle: referencia
                });
            }
        });
        return detalles;
    };

    // ─── Pestaña Pagos / Cobros ────────────────────────────────────────────────
    let _fvIngresoDeps        = null;
    let _fvIngresoDepsCargas  = false;
    let _fvSaldoPendiente     = 0;          // saldo disponible de la factura activa
    const _fvAjaxHeaders = { 'X-Requested-With': 'XMLHttpRequest' };

    function _fvSetTarjetas(totalFact, totalCob, totalRet, totalNC, saldo) {
        document.getElementById('fvPagoTotalFactura').textContent      = totalFact.toFixed(2);
        document.getElementById('fvPagoTotalCobrado').textContent      = totalCob.toFixed(2);
        document.getElementById('fvPagoTotalRetenciones').textContent  = totalRet.toFixed(2);
        document.getElementById('fvPagoTotalNC').textContent           = totalNC.toFixed(2);
        document.getElementById('fvPagoSaldoPendiente').textContent    = saldo.toFixed(2);

        const btnTarjeta = document.getElementById('m-btn-pagar-tarjeta');
        if (btnTarjeta) {
            if (saldo > 0.001) {
                btnTarjeta.classList.remove('d-none');
            } else {
                btnTarjeta.classList.add('d-none');
            }
        }
    }

    async function fvCargarCobrosTab() {
        const idFact = parseInt(FV_ID_ACTIVO) || 0;

        const alertaNueva  = document.getElementById('fvPagoAlertaNueva');
        const alertaPagada = document.getElementById('fvPagoAlertaPagada');
        const cardReg      = document.getElementById('fvPagoCardRegistro');
        const tbody        = document.getElementById('fvPagoTbodyHistorial');
        if (!alertaNueva || !alertaPagada || !cardReg || !tbody) return;

        // Estado visual inicial — ocultar todo excepto alertaNueva
        alertaNueva.classList.remove('d-none');
        alertaPagada.classList.add('d-none');
        cardReg.classList.add('d-none');
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">'
            + '<span class="spinner-border spinner-border-sm me-2"></span>Cargando...</td></tr>';

        if (!idFact) {
            _fvSetTarjetas(0, 0, 0, 0, 0);
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">'
                + 'Guarda la factura primero para registrar cobros.</td></tr>';
            return;
        }

        // Factura existente → ocultar alerta "nueva"
        alertaNueva.classList.add('d-none');

        // ── 1. Catálogos (independiente — no aborta la carga de cobros si falla) ─
        if (!_fvIngresoDepsCargas) {
            try {
                const rDeps = await fetch(
                    `${B_URL}/${RUTA_MODULO}/getIngresosCatalogosAjax`,
                    { headers: _fvAjaxHeaders }
                );
                const jDeps = await rDeps.json();
                if (!jDeps.ok) {
                    console.warn('[Pagos] getIngresosCatalogosAjax devolvió ok=false:', jDeps.mensaje || jDeps);
                } else {
                    _fvIngresoDeps       = jDeps.data;
                    _fvIngresoDepsCargas = true;

                    const pts = _fvIngresoDeps.puntos       || [];
                    const con = _fvIngresoDeps.conceptos    || [];
                    const fps = _fvIngresoDeps.formas_cobro || [];
                    const ban = _fvIngresoDeps.bancos       || [];

                    // Serie (punto de emisión)
                    const comboPto = document.getElementById('fvPagoPuntoEmision');
                    if (comboPto) {
                        comboPto.innerHTML = '<option value="">— Seleccione —</option>'
                            + pts.map(p =>
                                `<option value="${p.id_punto}" data-est="${p.id_establecimiento}" data-cod-est="${p.cod_establecimiento}" data-cod-punto="${p.codigo_punto}">${p.cod_establecimiento}-${p.codigo_punto}</option>`
                            ).join('');
                        if (pts.length > 0) {
                            comboPto.selectedIndex = 1;
                            fvCargarSecuencialCobro(comboPto.value);
                        }
                    }

                    // Concepto — auto-seleccionar el relacionado a facturas de venta
                    const comboConc = document.getElementById('fvPagoConcepto');
                    if (comboConc) {
                        comboConc.innerHTML = '<option value="">— Opcional —</option>'
                            + con.map(c => `<option value="${c.id}" data-comp="${c.comportamiento || 'GENERAL'}">${c.nombre}</option>`).join('');

                        // 1) Buscar por comportamiento exacto
                        let cDef = con.find(c =>
                            c.comportamiento === 'FACTURA_VENTA' || c.comportamiento === 'COBRO_FACTURA'
                        );
                        // 2) Fallback: por palabras clave en el nombre
                        if (!cDef) cDef = con.find(c => {
                            const n = (c.nombre || '').toLowerCase();
                            return n.includes('cobro') || n.includes('factura') || n.includes('venta');
                        });
                        if (cDef) comboConc.value = cDef.id;
                    }

                    // Forma de cobro
                    const comboFP = document.getElementById('fvPagoFormaCobro');
                    if (comboFP) {
                        comboFP.innerHTML = '<option value="">— Seleccione —</option>'
                            + fps.map(fp => `<option value="${fp.id}">${fp.nombre}</option>`).join('');
                        if (fps.length === 1) comboFP.selectedIndex = 1;
                    }

                    // Bancos (opcional)
                    const comboBanco = document.getElementById('fvPagoBanco');
                    if (comboBanco) {
                        comboBanco.innerHTML = '<option value="">— Opcional —</option>'
                            + ban.map(b => `<option value="${b.id}">${b.nombre_banco}</option>`).join('');
                    }
                }
            } catch (errDeps) { console.warn('[Pagos] Error cargando catálogos:', errDeps); }
        }

        // ── 2. Cobros vinculados ──────────────────────────────────────────────────
        try {
            const rCob = await fetch(
                `${B_URL}/${RUTA_MODULO}/getCobrosVinculadosAjax?id=${idFact}`,
                { headers: _fvAjaxHeaders }
            );
            const jCob = await rCob.json();
            if (!jCob.ok) throw new Error(jCob.mensaje || 'Error al cargar cobros.');

            const totalFactura     = parseFloat(jCob.factura?.importe_total || 0);
            const totalRetenciones = parseFloat(jCob.total_retenciones      || 0);
            const totalNC          = parseFloat(jCob.total_nc               || 0);

            // Poblar historial
            let totalCobrado = 0;
            tbody.innerHTML = '';

            if (jCob.cobros && jCob.cobros.length > 0) {
                jCob.cobros.forEach(cob => {
                    const esAnulado = (cob.estado || '').toLowerCase() === 'anulado';
                    const monto     = parseFloat(cob.monto_cobrado || cob.monto_total || 0);
                    if (!esAnulado) totalCobrado += monto;

                    const tr = document.createElement('tr');
                    if (esAnulado) tr.classList.add('table-danger', 'text-decoration-line-through', 'opacity-50');

                    const fEmis = cob.fecha_emision
                        ? cob.fecha_emision.slice(0, 10).split('-').reverse().join('/')
                        : '—';
                    tr.innerHTML = `
                        <td class="ps-3">${fEmis}</td>
                        <td><code class="text-secondary fw-bold">${cob.numero_ingreso || ''}</code></td>
                        <td><div class="fw-medium">${cob.usuario_nombre || '—'}</div>
                            <small class="text-muted" style="font-size:0.65rem;">${cob.formas_cobro || '—'}</small></td>
                        <td class="text-end fw-bold pe-3">$ ${monto.toFixed(2)}</td>`;
                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">'
                    + 'No hay cobros registrados aún.</td></tr>';
            }

            // Saldo = Factura − Cobrado − Retenciones − Notas de Crédito
            const saldo = Math.max(0, totalFactura - totalCobrado - totalRetenciones - totalNC);
            _fvSaldoPendiente = saldo;                       // guardar para validación
            _fvSetTarjetas(totalFactura, totalCobrado, totalRetenciones, totalNC, saldo);

            // Panel derecho
            if (saldo < 0.01) {
                alertaPagada.classList.remove('d-none');
                cardReg.classList.add('d-none');
            } else {
                alertaPagada.classList.add('d-none');
                cardReg.classList.remove('d-none');
                const elMonto = document.getElementById('fvPagoMonto');
                if (elMonto) {
                    elMonto.value = saldo.toFixed(2);
                    elMonto.max   = saldo.toFixed(2);        // límite máximo en el input
                }
                const elObs = document.getElementById('fvPagoObs');
                if (elObs && jCob.factura) {
                    const n = (jCob.factura.establecimiento || '') + '-'
                            + (jCob.factura.punto_emision   || '') + '-'
                            + (jCob.factura.secuencial      || '');
                    elObs.value = `Cobro de factura ${n}`;
                }
            }
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-danger">`
                + `<i class="bi bi-exclamation-triangle me-1"></i>${err.message}</td></tr>`;
            // Mostrar alerta de "nuevo" con mensaje de error para que el panel derecho no quede vacío
            alertaNueva.classList.remove('d-none');
        }
    }

    function fvToggleCobrosFormaForm(formaCobradId) {
        const divBanco = document.getElementById('fvPagoDivBanco');
        if (!divBanco) return;
        const fp = (_fvIngresoDeps?.formas_cobro || []).find(x => x.id == formaCobradId);
        if (fp && fp.tipo === 'BANCO') divBanco.classList.remove('d-none');
        else divBanco.classList.add('d-none');
    }

    async function fvCargarSecuencialCobro(idPunto) {
        const elSec = document.getElementById('fvPagoSecuencial');
        if (!elSec) return;
        if (!idPunto) { elSec.value = ''; return; }
        elSec.value = '…';
        try {
            const resp = await fetch(
                `${B_URL}/${RUTA_MODULO}/getSecuencialAjax?id_punto_emision=${idPunto}&tipo=ingresos`,
                { headers: _fvAjaxHeaders }
            );
            const json = await resp.json();
            if (json.ok) {
                elSec.value = json.formateado || String(json.secuencial).padStart(9, '0');
                if (json.es_gap) {
                    elSec.classList.add('border-warning', 'text-warning');
                    elSec.title = json.detalle || 'Número faltante recuperado';
                } else {
                    elSec.classList.remove('border-warning', 'text-warning');
                    elSec.title = json.detalle || 'Siguiente consecutivo';
                }
            } else {
                elSec.value = '—';
            }
        } catch (_) {
            elSec.value = '—';
        }
    }

    async function fvRegistrarCobro() {
        const idFact = parseInt(FV_ID_ACTIVO) || 0;
        if (!idFact) return;

        const btn = document.getElementById('fvPagoBtnRegistrar');
        const payload = {
            id_factura             : idFact,
            id_punto_emision    : document.getElementById('fvPagoPuntoEmision').value,
            fecha_emision       : document.getElementById('fvPagoFecha').value,
            id_ingreso_concepto : document.getElementById('fvPagoConcepto').value,
            monto_cobrar        : parseFloat(document.getElementById('fvPagoMonto').value),
            id_forma_cobro      : document.getElementById('fvPagoFormaCobro').value,
            observaciones       : document.getElementById('fvPagoObs').value,
        };

        // Solo agregar datos bancarios si el div está visible (forma de cobro tipo BANCO)
        const divBanco = document.getElementById('fvPagoDivBanco');
        if (divBanco && !divBanco.classList.contains('d-none')) {
            payload.tipo_operacion_bancaria = document.getElementById('fvPagoTipoOp').value || null;
            payload.numero_operacion        = document.getElementById('fvPagoNumOp').value  || null;
            payload.referencia              = document.getElementById('fvPagoNumOp').value  || null;
        }

        if (!payload.id_punto_emision) {
            return Swal.fire('Campos requeridos', 'Seleccione la serie (punto de emisión).', 'warning');
        }
        if (!payload.id_forma_cobro) {
            return Swal.fire('Campos requeridos', 'Seleccione la forma de cobro.', 'warning');
        }
        if (!payload.monto_cobrar || payload.monto_cobrar <= 0) {
            return Swal.fire('Campos requeridos', 'El monto a cobrar debe ser mayor a cero.', 'warning');
        }
        if (_fvSaldoPendiente > 0 && payload.monto_cobrar > _fvSaldoPendiente + 0.001) {
            return Swal.fire(
                'Monto excedido',
                `El monto a cobrar ($${payload.monto_cobrar.toFixed(2)}) no puede superar el saldo pendiente ($${_fvSaldoPendiente.toFixed(2)}).`,
                'warning'
            );
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Registrando...';

        try {
            const resp = await fetch(`${B_URL}/${RUTA_MODULO}/registrarCobroRapidoAjax`, {
                method : 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body   : JSON.stringify(payload)
            });
            const res = await resp.json();
            if (res.ok) {
                Swal.fire({ icon: 'success', title: 'Éxito', text: res.msg, timer: 2000, showConfirmButton: false });
                fvCargarCobrosTab();
                if (typeof fetchSearchFn === 'function') fetchSearchFn(window.FV_currentPage || 1);
            } else {
                throw new Error(res.mensaje || res.error || 'Error desconocido.');
            }
        } catch (err) {
            Swal.fire('Error', err.message, 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Registrar Cobro';
        }
    }

    document.getElementById('tab-fv-pagos-btn')?.addEventListener('shown.bs.tab', function() {
        fvCargarCobrosTab();
    });

    document.getElementById('tab-fv-asiento-btn')?.addEventListener('shown.bs.tab', function() {
        fvCargarAsiento(FV_ID_ACTIVO);
    });

</script>
<?php 
$permNCRespaldo = $perm;
$perm = $permNC;
include_once MVC_APP . '/views/modulos/notas_credito/modal_nc.php'; 

$perm = $permGR;
include_once MVC_APP . '/views/modulos/guias_remision/modal_gr.php';
// Re-incluir modal de transportistas ya que GR lo necesita
include_once MVC_APP . '/views/modulos/transportistas/modal_transportista.php';

$perm = $permNCRespaldo;
?>
<script src="<?= BASE_URL ?>/js/modulos/transportistas_modal.js?v=<?= time() ?>"></script>
<script src="<?= BASE_URL ?>/js/modulos/guias_remision_modal.js?v=<?= time() ?>"></script>
<script src="<?= BASE_URL ?>/js/modulos/notas_credito.js?v=<?= time() ?>"></script>
<script>
// ── Descargar XML Original de Factura de Venta (detalle_xml de ventas_cabecera) ──
</script>

<!-- Modal Enviar WhatsApp -->
<div class="modal fade" id="modalEnviarWhatsappFactura" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-whatsapp text-success me-2"></i>Enviar Factura por WhatsApp</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formEnviarWhatsappFactura">
                <div class="modal-body pt-3">
                    <input type="hidden" name="id_factura" id="waIdFactura">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Plantilla de WhatsApp <span class="text-danger">*</span></label>
                        <select name="id_plantilla" id="waSelectPlantilla" class="form-select form-select-sm" required>
                            <option value="">Cargando plantillas...</option>
                        </select>
                        <div class="form-text">Solo se muestran plantillas aprobadas por Meta.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Teléfono del Cliente <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                            <input type="text" name="telefono" id="waTelefonoCliente" class="form-control" placeholder="Ej: 59398000000" required>
                        </div>
                        <div class="form-text">Código de país + número (sin +). Si está vacío, ingresa el número.</div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0 rounded-bottom-3">
                    <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm px-4" id="btnEnviarWhatsappFactura">
                        <i class="bi bi-send me-1"></i> Enviar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Manejador del formulario
    const formWa = document.getElementById('formEnviarWhatsappFactura');
    if (formWa) {
        formWa.addEventListener('submit', (e) => {
            e.preventDefault();
            const btn = document.getElementById('btnEnviarWhatsappFactura');
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enviando...';
            btn.disabled = true;

            fetch(`${B_URL}/${RUTA_MODULO}/enviarWhatsappAjax`, {
                method: 'POST',
                body: new FormData(formWa),
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.ok) {
                    Swal.fire('¡Enviado!', data.mensaje, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('modalEnviarWhatsappFactura')).hide();
                } else {
                    Swal.fire('Error', data.error || 'Ocurrió un error al enviar por WhatsApp.', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                Swal.fire('Error', 'Error de red al enviar el mensaje.', 'error');
            })
            .finally(() => {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            });
        });
    }
});

function FV_abrirModalWhatsapp() {
    const idFactura = parseInt(FV_ID_ACTIVO) || 0;
    if (!idFactura) {
        Swal.fire('Atención', 'No hay una factura seleccionada.', 'warning');
        return;
    }

    const select = document.getElementById('waSelectPlantilla');
    select.innerHTML = '<option value="">Cargando plantillas...</option>';

    fetch(`${B_URL}/${RUTA_MODULO}/getPlantillasWhatsappAjax?id_factura=${idFactura}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.ok) {
            if (data.configurado === false) {
                Swal.fire({
                    title: '<i class="bi bi-whatsapp text-success fs-1"></i><br>Activa WhatsApp API',
                    html: `Parece que aún no tienes configurado el servicio oficial de <b>WhatsApp Business API</b> en tu cuenta.<br><br>
                           Al activarlo podrás enviar facturas en PDF y notificaciones automáticas a tus clientes con un solo clic.<br><br>
                           <b>¿Te gustaría activar este servicio?</b>`,
                    showCancelButton: true,
                    confirmButtonText: 'Sí, quiero activarlo',
                    cancelButtonText: 'Más tarde',
                    confirmButtonColor: '#25D366'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire('¡Excelente decisión!', 'Por favor comunícate con nuestro equipo de soporte técnico para iniciar el proceso de activación de WhatsApp Oficial para tu empresa.', 'info');
                    }
                });
                return;
            }

            select.innerHTML = '<option value="">-- Seleccione una plantilla --</option>';
            data.plantillas.forEach(p => {
                const isSelected = (p.id == data.id_plantilla_default) ? 'selected' : '';
                select.innerHTML += `<option value="${p.id}" ${isSelected}>${p.nombre} (${p.idioma})</option>`;
            });
            document.getElementById('waTelefonoCliente').value = data.telefono_cliente || '593';

            document.getElementById('waIdFactura').value = idFactura;
            const modal = new bootstrap.Modal(document.getElementById('modalEnviarWhatsappFactura'));
            modal.show();

        } else {
            select.innerHTML = `<option value="">Error: ${data.error}</option>`;
            Swal.fire('Error', data.error, 'error');
        }
    })
    .catch(err => {
        console.error(err);
        select.innerHTML = `<option value="">Error de red</option>`;
        Swal.fire('Error de Red', 'Hubo un problema de conexión al cargar las plantillas.', 'error');
    });
}

// ─── CAJITA DE PAGOS PAYPHONE ────────────────────────────────────────────────

var _ppCargado = false;

function _fvCargarPayphoneSDK(callback) {
    if (_ppCargado && typeof window.PPaymentButtonBox !== 'undefined') {
        callback();
        return;
    }
    if (!document.querySelector('link[href*="payphone-payment-box.css"]')) {
        var lnk  = document.createElement('link');
        lnk.rel  = 'stylesheet';
        lnk.href = 'https://cdn.payphonetodoesposible.com/box/v2.0/payphone-payment-box.css';
        document.head.appendChild(lnk);
    }
    if (!document.querySelector('script[src*="payphone-payment-box.js"]')) {
        var sc    = document.createElement('script');
        sc.src    = 'https://cdn.payphonetodoesposible.com/box/v2.0/payphone-payment-box.js';
        sc.onload = function() { _ppCargado = true; callback(); };
        sc.onerror= function() { callback(new Error('No se pudo cargar el SDK de Payphone.')); };
        document.head.appendChild(sc);
    } else {
        var t = 0;
        var iv = setInterval(function() {
            if (typeof window.PPaymentButtonBox !== 'undefined') {
                clearInterval(iv);
                _ppCargado = true;
                callback();
            } else if (++t > 50) {
                clearInterval(iv);
                callback(new Error('Tiempo de espera agotado cargando Payphone.'));
            }
        }, 100);
    }
}

function _fvGetOCrearModal() {
    var el = document.getElementById('modalFvPagoTarjeta');
    if (el) return el;

    var wrap = document.createElement('div');
    var hdr  = document.createElement('div'); hdr.className = 'modal-header py-2 px-3';
    var ttl  = document.createElement('h6');  ttl.className = 'modal-title fw-bold';
    ttl.innerHTML = '<i class="bi bi-credit-card-2-front text-primary me-2"></i>Pagar con tarjeta';
    var cls  = document.createElement('button'); cls.type = 'button'; cls.className = 'btn-close';
    cls.setAttribute('data-bs-dismiss', 'modal'); cls.setAttribute('aria-label', 'Cerrar');
    hdr.appendChild(ttl); hdr.appendChild(cls);

    var bdy  = document.createElement('div'); bdy.className = 'modal-body p-3';
    var ctn  = document.createElement('div'); ctn.id = 'fvPagoTarjetaContenido';
    var sec  = document.createElement('div'); sec.className = 'd-flex align-items-center gap-1 mt-3 small text-muted';
    sec.innerHTML = '<i class="bi bi-shield-lock text-success"></i>&nbsp;Pago seguro procesado por <strong class="ms-1">Payphone</strong>';
    bdy.appendChild(ctn); bdy.appendChild(sec);

    var ftr  = document.createElement('div'); ftr.className = 'modal-footer py-2 px-3 justify-content-end';
    var can  = document.createElement('button'); can.type = 'button'; can.className = 'btn btn-secondary btn-sm';
    can.setAttribute('data-bs-dismiss', 'modal'); can.textContent = 'Cancelar';
    ftr.appendChild(can);

    var cnt  = document.createElement('div'); cnt.className = 'modal-content rounded-3 shadow';
    cnt.appendChild(hdr); cnt.appendChild(bdy); cnt.appendChild(ftr);
    var dlg  = document.createElement('div'); dlg.className = 'modal-dialog modal-dialog-centered';
    dlg.style.maxWidth = '480px'; dlg.appendChild(cnt);
    var mod  = document.createElement('div'); mod.className = 'modal fade';
    mod.id = 'modalFvPagoTarjeta'; mod.setAttribute('tabindex', '-1');
    mod.setAttribute('data-bs-backdrop', 'static');
    mod.appendChild(dlg);

    document.body.appendChild(mod);
    return mod;
}

window.fvAbrirPagoTarjeta = function() {
    var idFactura = parseInt(FV_ID_ACTIVO) || 0;
    if (idFactura <= 0) return;

    var btn      = document.getElementById('m-btn-pagar-tarjeta');
    var origHtml = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Cargando...'; }

    var fd = new FormData();
    fd.append('id_factura', idFactura);

    fetch(B_URL + '/' + RUTA_MODULO + '/prepararPagoTarjetaAjax', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.ok) {
                Swal.fire('No se puede procesar el pago', data.mensaje, 'warning');
                return;
            }

            var modalEl     = _fvGetOCrearModal();
            var contenidoEl = modalEl.querySelector('#fvPagoTarjetaContenido');
            contenidoEl.innerHTML = '<div class="text-center py-4 text-muted small"><span class="spinner-border spinner-border-sm me-2"></span>Cargando formulario de pago...</div>';

            bootstrap.Modal.getOrCreateInstance(modalEl).show();

            _fvCargarPayphoneSDK(function(err) {
                if (err) {
                    contenidoEl.innerHTML = '<div class="alert alert-danger small py-2">' + err.message + '</div>';
                    return;
                }
                contenidoEl.innerHTML = '';
                try {
                    new window.PPaymentButtonBox(data.widget).render('fvPagoTarjetaContenido');
                } catch(e) {
                    contenidoEl.innerHTML = '<div class="alert alert-danger small py-2">Error al inicializar el formulario de pago.</div>';
                }
            });
        })
        .catch(function() {
            Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
        })
        .finally(function() {
            if (btn) { btn.disabled = false; btn.innerHTML = origHtml; }
        });
};
</script>

<?php // Fin de index.php ?>