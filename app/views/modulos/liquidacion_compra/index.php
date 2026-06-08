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
/** @var array $sustentos */
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

$pestanasConfigLiqDropdown = [
    'tab-liq-asiento'     => 'Asiento contable',
    'tab-liq-pagos'       => 'Pagos',
    'tab-liq-retenciones' => 'Retenciones',
    'tab-liq-sri'         => 'SRI',
];

$pestanasConfigLiq = [
    ['id' => 'tab-liq-compra-btn',      'target' => 'tab-liq-compra',      'label' => 'Liquidación',     'icon' => 'bi-receipt'],
    ['id' => 'tab-liq-asiento-btn',     'target' => 'tab-liq-asiento',     'label' => 'Asiento contable','icon' => 'bi-calculator'],
    ['id' => 'tab-liq-pagos-btn',       'target' => 'tab-liq-pagos',       'label' => 'Pagos',           'icon' => 'bi-cash-coin'],
    ['id' => 'tab-liq-retenciones-btn', 'target' => 'tab-liq-retenciones', 'label' => 'Retenciones',     'icon' => 'bi-percent'],
    ['id' => 'tab-liq-sri-btn',         'target' => 'tab-liq-sri',         'label' => 'SRI',             'icon' => 'bi-cloud-check'],
];
?>

<style>
    /* Estilos para el listado */
    .liq-scroll {
        max-height: calc(100dvh - 240px);
        overflow-y: auto;
    }

    .liq-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }

    .liquidacion-row {
        cursor: pointer;
    }

    .liquidacion-row:hover {
        background-color: rgba(0, 0, 0, .04);
    }

    /* ── Estilos del modal de liquidación ── */
    .modal-liquidacion .modal-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
        padding: 0.75rem 1rem;
    }

    /* Tabla de detalle */
    .table-detalle th {
        font-size: 0.7rem !important;
        text-transform: uppercase;
        background-color: #f8f9fa;
        padding: 4px 8px !important;
    }

    .modal-liquidacion .table-detalle td {
        padding: 0 !important;
        vertical-align: middle;
    }

    .input-detalle {
        border: none;
        background: transparent;
        font-size: 0.82rem !important;
        padding: 2px 8px !important;
        width: 100%;
        height: 30px !important;
    }

    .input-detalle:focus {
        background: #fff;
        box-shadow: inset 0 0 0 1px #0d6efd;
        outline: none;
    }

    .row-detalle:hover {
        background-color: rgba(13, 110, 253, 0.03);
    }

    .dropdown-predictivo {
        z-index: 2050 !important;
    }

    .x-small {
        font-size: 0.75rem !important;
    }

    .modal-liquidacion hr {
        margin: 0.5rem 0;
    }

    /* Quitar barra lateral que genera Bootstrap en los tab-pane con border-top en el contenedor */
    .modal-liquidacion .tab-content {
        border-top: 1px solid #dee2e6;
        border-left: none !important;
        border-right: none !important;
        border-bottom: none !important;
    }
    .modal-liquidacion .tab-pane {
        border-left: none !important;
    }
</style>

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-receipt-cutoff me-2 text-primary"></i><?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="abrirModalLiquidacion()">
            <i class="bi bi-plus-lg"></i> Nueva
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_busqueda.css?v=<?= time() ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_busqueda.js?v=<?= time() ?>"></script>
            <div id="fbBuscadorLC" style="width: 480px;"></div>
            <input type="hidden" id="buscar" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosBusqueda) return;
                    new FiltrosBusqueda({
                        containerId: 'fbBuscadorLC',
                        hiddenInputId: 'buscar',
                        fields: [
                            { key: 'proveedor', label: 'Proveedor',    icon: 'bi-building',        type: 'text' },
                            { key: 'ruc',       label: 'RUC',          icon: 'bi-card-text',       type: 'text' },
                            { key: 'numero',    label: 'Nº liquidación', icon: 'bi-hash',          type: 'text' },
                            { key: 'fecha',     label: 'Fecha emisión', icon: 'bi-calendar-event', type: 'date_range' },
                            { key: 'monto',     label: 'Monto total',  icon: 'bi-currency-dollar', type: 'number_range' },
                            { key: 'estado',    label: 'Estado',       icon: 'bi-flag',            type: 'select', options: [
                                { v: 'borrador',   l: 'Borrador' },
                                { v: 'autorizado', l: 'Autorizado' },
                                { v: 'anulado',    l: 'Anulado' },
                            ]},
                        ],
                        quickFilters: [
                            { id: 'qf_borrador', label: 'Borrador',    mk: () => ({ key: 'estado', op: '=', value: 'borrador', display: 'Borrador' }) },
                            { id: 'qf_mes',      label: 'Este mes',    mk: () => FiltrosBusqueda.helpers.esteMes('fecha') },
                            { id: 'qf_anio',     label: 'Este año',    mk: () => FiltrosBusqueda.helpers.esteAnio('fecha') },
                        ],
                        onApply: () => window.LC_fetchSearch && window.LC_fetchSearch(1),
                    }).init();
                });
            </script>

            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'secuencial'          => 'Nº Liquidación',
                    'fecha_emision'       => 'Fecha',
                    'proveedor_nombre'    => 'Proveedor',
                    'proveedor_ruc'       => 'Identificación',
                    'total_sin_impuestos' => 'Subtotal',
                    'total_descuento'     => 'Descuento',
                    'importe_total'       => 'Total',
                    'usuario_nombre'      => 'Usuario',
                    'estado_correo'       => 'Estado correo',
                    'estado'              => 'Estado',
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>

                <button class="btn btn-outline-danger" onclick="exportarPdfListado()" title="Descargar PDF">
                    <i class="bi bi-file-earmark-pdf"></i> PDF
                </button>
                <button class="btn btn-outline-success" onclick="exportarExcelListado()" title="Descargar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                </button>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="paginationContainer" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" <?= ($page <= 1) ? 'disabled' : '' ?> onclick="window.LC_fetchSearch(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" <?= ($page >= $totalPages) ? 'disabled' : '' ?> onclick="window.LC_fetchSearch(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="liq-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="secuencial" data-col="secuencial">
                            Nº Liquidación <i class="bi <?= $ordenCol === 'secuencial' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="sortable-header" role="button" data-sort="fecha_emision" data-col="fecha_emision">
                            Fecha <i class="bi <?= $ordenCol === 'fecha_emision' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="sortable-header" role="button" data-sort="proveedor_nombre" data-col="proveedor_nombre">
                            Proveedor <i class="bi <?= $ordenCol === 'proveedor_nombre' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="sortable-header" role="button" data-sort="proveedor_ruc" data-col="proveedor_ruc">
                            Identificación <i class="bi <?= $ordenCol === 'proveedor_ruc' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="sortable-header text-end" role="button" data-sort="total_sin_impuestos" data-col="total_sin_impuestos">
                            Subtotal <i class="bi <?= $ordenCol === 'total_sin_impuestos' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="sortable-header text-end" role="button" data-sort="total_descuento" data-col="total_descuento">
                            Descuento <i class="bi <?= $ordenCol === 'total_descuento' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="sortable-header text-end" role="button" data-sort="importe_total" data-col="importe_total">
                            Total <i class="bi <?= $ordenCol === 'importe_total' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="sortable-header" role="button" data-sort="usuario_nombre" data-col="usuario_nombre">
                            Usuario <i class="bi <?= $ordenCol === 'usuario_nombre' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="text-center sortable-header" role="button" data-sort="estado_correo" data-col="estado_correo">
                            Correo <i class="bi <?= $ordenCol === 'estado_correo' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="text-center pe-3 sortable-header" role="button" data-sort="estado" data-col="estado">
                            Estado <i class="bi <?= $ordenCol === 'estado' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                    </tr>
                </thead>
                <tbody id="tbodyLiquidaciones">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted"><i class="bi bi-receipt fs-3 d-block mb-2"></i>No se encontraron liquidaciones.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $estado = $r['estado'] ?? 'borrador';
                            $estadoClass  = match ($estado) {
                                'aprobado', 'autorizado' => 'bg-success bg-opacity-10 text-success border-success',
                                'anulado'                => 'bg-danger bg-opacity-10 text-danger border-danger',
                                'borrador'               => 'bg-secondary bg-opacity-10 text-secondary border-secondary',
                                default                  => 'bg-primary bg-opacity-10 text-primary border-primary',
                            };
                            $estadoBadge  = '<span class="badge ' . $estadoClass . ' border border-opacity-25">' . ucfirst($estado) . '</span>';
                            ?>
                            <tr class="liquidacion-row" role="button" tabindex="0" data-row='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>' onclick="abrirModalLiquidacionVer(this)">
                                <td class="ps-3" data-col="secuencial"><code class="text-secondary"><?= htmlspecialchars(($r['establecimiento'] ?? '') . '-' . ($r['punto_emision'] ?? '') . '-' . ($r['secuencial'] ?? '')) ?></code></td>
                                <td data-col="fecha_emision"><?= !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '-' ?></td>
                                <td class="fw-medium text-truncate" style="max-width:250px" data-col="proveedor_nombre"><?= htmlspecialchars($r['proveedor_nombre'] ?? '-') ?></td>
                                <td data-col="proveedor_ruc"><small class="text-muted"><?= htmlspecialchars($r['proveedor_ruc'] ?? '-') ?></small></td>
                                <td class="text-end" data-col="total_sin_impuestos">$<?= number_format((float)($r['total_sin_impuestos'] ?? 0), 2) ?></td>
                                <td class="text-end text-danger" data-col="total_descuento">$<?= number_format((float)($r['total_descuento'] ?? 0), 2) ?></td>
                                <td class="text-end fw-bold" data-col="importe_total">$<?= number_format((float)($r['importe_total'] ?? 0), 2) ?></td>
                                <td data-col="usuario_nombre"><?= htmlspecialchars($r['usuario_nombre'] ?? '-') ?></td>
                                <td class="text-center" data-col="estado_correo">-</td>
                                <td class="text-center pe-3" data-col="estado"><?= $estadoBadge ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal para Nueva Liquidación -->
<div class="modal fade modal-liquidacion" id="modalLiquidacion" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-receipt-cutoff text-primary me-2"></i>
                    <span id="tituloModalLiq">Liquidación de Compras y Servicios</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <form id="formLiquidacion">
                    <input type="hidden" name="id" id="liq-id">

                    <!-- Acciones -->
                    <div class="px-3 py-2 bg-light border-bottom d-flex gap-2 align-items-center flex-wrap">
                        <button type="button" class="btn btn-outline-warning btn-sm d-none" id="btnAnularLiq" onclick="window.LC_anularLiquidacion()"><i class="bi bi-slash-circle me-1"></i>Anular</button>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="btnEnviarSri" onclick="enviarSri()"><i class="bi bi-cloud-arrow-up me-1"></i>Enviar al SRI</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnDuplicar" onclick="duplicar()"><i class="bi bi-copy me-1"></i>Duplicar</button>
                        
                        <button type="button" class="btn btn-outline-danger btn-sm px-2" title="PDF"><i class="bi bi-file-earmark-pdf"></i></button>
                        <button type="button" class="btn btn-outline-success btn-sm px-2" title="XML"><i class="bi bi-file-earmark-code"></i></button>
                        <button type="button" class="btn btn-outline-info btn-sm px-2" title="Correo"><i class="bi bi-envelope"></i></button>
                        
                        <button type="button" class="btn btn-outline-primary btn-sm px-2" onclick="abrirModalProveedorCrear()" title="Nuevo Proveedor"><i class="bi bi-person-plus"></i></button>
                    </div>

                    <!-- Pestañas estilo Proveedores -->
                    <div class="d-flex align-items-center bg-light px-3 pt-2">
                        <ul class="nav nav-tabs border-bottom-0 flex-grow-1 tab-pestaña" id="tabsLiquidacion" role="tablist">
                            <?php foreach ($pestanasConfigLiq as $p): 
                                $visible = !isset($vistaConfig['__pestanas_ocultas__']) || !in_array($p['target'], $vistaConfig['__pestanas_ocultas__']);
                            ?>
                                <li class="nav-item <?= $visible ? '' : 'd-none' ?>" role="presentation" id="li-<?= $p['id'] ?>">
                                    <a class="nav-link <?= $p['id'] === 'tab-liq-compra-btn' ? 'active' : '' ?> py-2 small" id="<?= $p['id'] ?>" data-bs-toggle="tab" href="#<?= $p['target'] ?>" role="tab">
                                        <i class="bi <?= $p['icon'] ?> me-1"></i><?= $p['label'] ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="ms-auto pb-1">
                            <?= \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasConfigLiqDropdown, $vistaConfig, 'liquidacion-compra') ?>
                        </div>
                    </div>
                    <div class="border-bottom bg-light mb-0"></div>

                    <div class="tab-content border-top px-3 py-3">
                        <!-- Pestaña Liquidación -->
                        <div class="tab-pane fade show active" id="tab-liq-compra">
                            <div class="row g-3">
                                <!-- Cabecera Info -->
                                <div class="col-md-12">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-2">
                                            <label class="x-small fw-bold d-flex align-items-center">Fecha Emisión</label>
                                            <input type="date" class="form-control form-control-sm" name="fecha_emision" id="liq-fecha" value="<?= date('Y-m-d') ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="x-small fw-bold d-flex align-items-center">Serie <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('liquidacion-compra', 'liq-punto', 'liq_punto') ?></label>
                                            <select class="form-select form-select-sm" name="id_punto_emision" id="liq-punto" onchange="window.LC_syncSecuencial(this.value)">
                                                <?php foreach ($puntos as $p): ?>
                                                    <option value="<?= $p['id'] ?>" 
                                                        data-id-est="<?= $p['id_establecimiento'] ?? $sucursal_principal['id'] ?? '' ?>" 
                                                        data-est="<?= $p['cod_establecimiento'] ?>" 
                                                        data-punto="<?= $p['codigo_punto'] ?>">
                                                        <?= $p['cod_establecimiento'] ?>-<?= $p['codigo_punto'] ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="x-small fw-bold d-flex align-items-center">Secuencial</label>
                                            <input type="text" class="form-control form-control-sm bg-light" name="secuencial" id="liq-secuencial" readonly placeholder="000000001">
                                        </div>
                                        <!-- Proveedor -->
                                        <div class="col-md-6">
                                            <label class="x-small fw-bold d-flex align-items-center">Proveedor (Cédula/Pasaporte)</label>
                                            <div class="position-relative">
                                                <div class="input-group input-group-sm rounded-pill overflow-hidden border">
                                                    <span class="input-group-text bg-white border-0 text-primary"><i class="bi bi-search"></i></span>
                                                    <input type="text" class="form-control border-0" id="search-proveedor" placeholder="Buscar por nombre o identificación..." autocomplete="off">
                                                    <input type="hidden" name="id_proveedor" id="liq-id-proveedor">
                                                </div>
                                                <div id="dropdown-proveedores" class="list-group shadow dropdown-predictive position-absolute d-none" style="width: 100%; max-height: 200px; overflow-y: auto;"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Detalle Items -->
                                <div class="col-md-12 mt-3">
                                    <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                                        <div class="table-responsive" style="max-height: 400px;">
                                            <table class="table table-sm table-detalle mb-0 text-nowrap">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th class="ps-3 py-2 small fw-bold text-muted" style="width: 12%;">Código</th>
                                                        <th class="py-2 small fw-bold text-muted" style="width: 25%;">Descripción</th>
                                                        <th class="py-2 small fw-bold text-muted" style="width: 15%;">Adicional</th>
                                                        <th class="py-2 small fw-bold text-muted text-center" style="width: 8%;">Cant.</th>
                                                        <th class="py-2 small fw-bold text-muted text-end" style="width: 10%;">P. Unitario</th>
                                                        <th class="py-2 small fw-bold text-muted text-end" style="width: 8%;">Desc.</th>
                                                        <th class="py-2 small fw-bold text-muted text-center" style="width: 10%;">IVA</th>
                                                        <th class="py-2 small fw-bold text-muted text-end pe-4" style="width: 10%;">Subtotal</th>
                                                        <th style="width: 2%;"></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="tbodyDetalles"></tbody>
                                            </table>
                                        </div>
                                        <div class="p-2 border-top bg-light d-flex justify-content-between align-items-center">
                                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" onclick="window.LC_agregarFila()">
                                                <i class="bi bi-plus-circle me-1"></i> Agregar línea
                                            </button>
                                            <div class="small fw-bold text-muted pe-3">
                                                Items: <span id="liq-count-items">0</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Sustento y Totales -->
                                <div class="col-md-12 mt-2">
                                    <div class="row g-3">
                                        <!-- Tabs Secundarias (Sustento, Info Adicional, Pagos) -->
                                        <div class="col-md-8">
                                            <ul class="nav nav-tabs nav-tabs-sm mb-2" role="tablist">
                                                <li class="nav-item"><button class="nav-link active py-1 px-3 small" data-bs-toggle="tab" data-bs-target="#subtab-info-extra" type="button">Info. Adicional</button></li>
                                                <li class="nav-item"><button class="nav-link py-1 px-3 small" data-bs-toggle="tab" data-bs-target="#subtab-pagos-sri" type="button">Pagos SRI</button></li>
                                                <li class="nav-item"><button class="nav-link py-1 px-3 small" data-bs-toggle="tab" data-bs-target="#subtab-sustento" type="button">Sustento Tributario</button></li>
                                            </ul>
                                            <div class="tab-content border rounded p-2 bg-light" style="min-height: 140px;">
                                                <!-- Info Extra -->
                                                <div class="tab-pane fade show active" id="subtab-info-extra">
                                                    <table class="table table-sm table-borderless mb-0">
                                                        <thead>
                                                            <tr class="border-bottom">
                                                                <th class="x-small text-muted py-1" style="width: 40%;">Concepto</th>
                                                                <th class="x-small text-muted py-1" style="width: 50%;">Detalle</th>
                                                                <th style="width: 10%;"></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="container-info-adicional"></tbody>
                                                    </table>
                                                    <button type="button" class="btn btn-link btn-xs p-0 small mt-1 text-decoration-none fw-bold" onclick="window.LC_agregarInfoAdicional()"><i class="bi bi-plus-circle me-1"></i>Agregar línea</button>
                                                </div>
                                                <!-- Pagos SRI -->
                                                <div class="tab-pane fade" id="subtab-pagos-sri">
                                                    <div id="container-pagos"></div>
                                                    <button type="button" class="btn btn-link btn-xs p-0 small mt-1 text-decoration-none fw-bold" onclick="window.LC_agregarPago()"><i class="bi bi-plus-circle me-1"></i>Añadir pago</button>
                                                </div>
                                                <!-- Sustento -->
                                                <div class="tab-pane fade" id="subtab-sustento">
                                                    <label class="x-small text-muted mb-1 d-flex align-items-center">Código de Sustento Tributario <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('liquidacion-compra', 'liq-sustento', 'liq_sustento') ?></label>
                                                    <select class="form-select form-select-sm" name="id_sustento_tributario" id="liq-sustento">
                                                        <option value="">Seleccione el sustento...</option>
                                                        <?php foreach ($sustentos as $s):
                                                            // Filtrar por tipo_comprobante 03 (Liquidación de Compra)
                                                            if (isset($s['tipo_comprobante']) && !empty($s['tipo_comprobante'])) {
                                                                $tipos = explode(',', (string)$s['tipo_comprobante']);
                                                                if (!in_array('03', array_map('trim', $tipos))) continue;
                                                            }
                                                        ?>
                                                            <option value="<?= $s['id'] ?>"><?= $s['codigo'] ?> - <?= htmlspecialchars($s['nombre']) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Totales (Matching Factura Venta) -->
                                        <div class="col-md-4">
                                            <div class="bg-white border rounded p-2 shadow-sm" style="font-size:0.78rem;">
                                                <div class="d-flex justify-content-between align-items-center mb-1 fw-bold border-bottom pb-1">
                                                    <span class="text-muted">Subtotal</span>
                                                    <span id="liq-lbl-subtotal">0.00</span>
                                                </div>

                                                <!-- Subtotales agrupados por tarifa IVA -->
                                                <div id="liq-lbl-subtotales-iva" class="mb-1">
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <span class="text-muted">Subtotal 0%</span>
                                                        <span class="fw-bold">0.00</span>
                                                    </div>
                                                </div>

                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="text-muted">(-) Descuento</span>
                                                    <span class="fw-bold text-dark" id="liq-lbl-descuento">0.00</span>
                                                </div>

                                                <!-- IVA agrupado por tarifa -->
                                                <div id="liq-lbl-ivas-grupo" class="mb-1"></div>

                                                <hr class="my-1 opacity-25">

                                                <div class="d-flex justify-content-between align-items-center bg-light border py-1 px-2 rounded">
                                                    <span class="fw-bold text-dark" style="font-size:0.8rem;">TOTAL</span>
                                                    <span class="fw-bold text-dark" style="font-size:1rem;" id="liq-lbl-total">0.00</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Otras pestañas -->
                        <div class="tab-pane fade p-4 text-center text-muted" id="tab-liq-asiento">El asiento se generará al guardar.</div>
                        <!-- TAB: PAGOS -->
                        <div class="tab-pane fade" id="tab-liq-pagos" role="tabpanel">
                            <div class="p-3">
                                <!-- Resumen de Deuda -->
                                <div class="row g-3 mb-3">
                                    <div class="col-md-3">
                                        <div class="border rounded-3 p-2 bg-white text-center shadow-sm border-secondary-subtle">
                                            <div class="text-muted mb-0 fw-semibold" style="font-size: 0.75rem;">Total Documento</div>
                                            <h4 class="fw-bold text-dark mb-0">$ <span id="pagoTotalCompra">0.00</span></h4>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="border rounded-3 p-2 bg-warning bg-opacity-10 border-warning border-opacity-25 text-center shadow-sm">
                                            <div class="text-warning mb-0 fw-semibold" style="font-size: 0.75rem;">Retenciones</div>
                                            <h4 class="fw-bold text-warning mb-0">$ <span id="pagoTotalRetencion">0.00</span></h4>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="border rounded-3 p-2 bg-success bg-opacity-10 border-success border-opacity-25 text-center shadow-sm">
                                            <div class="text-success mb-0 fw-semibold" style="font-size: 0.75rem;">Total Abonado</div>
                                            <h4 class="fw-bold text-success mb-0">$ <span id="pagoTotalAbonado">0.00</span></h4>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="border rounded-3 p-2 bg-danger bg-opacity-10 border-danger border-opacity-25 text-center shadow-sm">
                                            <div class="text-danger mb-0 fw-semibold" style="font-size: 0.75rem;">Saldo Pendiente</div>
                                            <h4 class="fw-bold text-danger mb-0">$ <span id="pagoSaldoPendiente">0.00</span></h4>
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <!-- Izquierda: Historial de Pagos -->
                                    <div class="col-md-7">
                                        <div class="card border border-secondary-subtle shadow-sm rounded-3 overflow-hidden">
                                            <div class="card-header bg-light py-2 d-flex align-items-center border-bottom border-secondary-subtle">
                                                <h6 class="card-title mb-0 fw-bold text-secondary" style="font-size: 0.85rem;"><i class="bi bi-list-ul me-2"></i>Historial de Pagos (Egresos)</h6>
                                            </div>
                                            <div class="card-body p-0">
                                                <div class="table-responsive" style="max-height: 320px; min-height: 150px;">
                                                    <table class="table table-hover align-middle mb-0">
                                                        <thead class="table-light text-muted sticky-top border-bottom" style="font-size: 0.75rem;">
                                                            <tr>
                                                                <th class="ps-3">Fecha</th>
                                                                <th>Nº Egreso</th>
                                                                <th>Concepto / Forma</th>
                                                                <th class="text-end pe-3">Monto</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="pagoTbodyHistorial" class="small" style="font-size: 0.8rem;">
                                                            <tr>
                                                                <td colspan="4" class="text-center py-4 text-muted"><i class="spinner-border spinner-border-sm me-2"></i>Cargando historial...</td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Derecha: Formulario de Nuevo Pago -->
                                    <div class="col-md-5">
                                        <div class="card border border-primary border-opacity-25 bg-primary bg-opacity-10 shadow-sm rounded-3 overflow-hidden d-none" id="pagoCardRegistro">
                                            <div class="card-header bg-primary bg-opacity-25 border-0 py-2 d-flex align-items-center">
                                                <h6 class="card-title mb-0 fw-bold text-primary" style="font-size: 0.85rem;"><i class="bi bi-plus-circle me-2"></i>Registrar Nuevo Pago</h6>
                                            </div>
                                            <div class="card-body py-3 px-3 bg-white">
                                                <form id="pagoFormNuevo" onsubmit="window.LC_registrarPagoEgre(event)">
                                                    <div class="row g-2">
                                                        <div class="col-6">
                                                            <label class="form-label fw-bold mb-0 text-muted" style="font-size:0.7rem;">Serie <span class="text-danger">*</span></label>
                                                            <select class="form-select form-select-sm shadow-none border-secondary-subtle" id="pagoPuntoEmision" required>
                                                                <option value="">- Seleccione -</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-6">
                                                            <label class="form-label fw-bold mb-0 text-muted" style="font-size:0.7rem;">Fecha Emisión <span class="text-danger">*</span></label>
                                                            <input type="date" class="form-control form-control-sm shadow-none border-secondary-subtle" id="pagoFechaEmision" value="<?= date('Y-m-d') ?>" required>
                                                        </div>
                                                        
                                                        <div class="col-6">
                                                            <label class="form-label fw-bold mb-0 text-muted" style="font-size:0.7rem;">Concepto de Egreso <span class="text-danger">*</span></label>
                                                            <select class="form-select form-select-sm shadow-none border-secondary-subtle" id="pagoConcepto" required>
                                                                <option value="">- Seleccione -</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-6">
                                                            <label class="form-label fw-bold mb-0 text-danger" style="font-size:0.7rem;">Monto a Pagar ($) <span class="text-danger">*</span></label>
                                                            <input type="number" step="0.01" min="0.01" class="form-control form-control-sm shadow-none fw-bold text-danger border-danger border-opacity-50" id="pagoMontoPagar" required>
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label fw-bold mb-0 text-muted" style="font-size:0.7rem;">Forma de Pago <span class="text-danger">*</span></label>
                                                            <select class="form-select form-select-sm shadow-none border-secondary-subtle" id="pagoFormaPago" onchange="window.LC_toggleEgresoBancoForm(this.value)" required>
                                                                <option value="">- Seleccione Forma -</option>
                                                            </select>
                                                        </div>
                                                        
                                                        <!-- Campos Condicionales de Banco -->
                                                        <div class="col-12 d-none" id="pagoDivDetalleBanco">
                                                            <div class="border border-warning border-opacity-25 rounded-2 p-2 bg-warning bg-opacity-10 mb-1 row g-2">
                                                                <div class="col-6">
                                                                    <label class="form-label fw-bold mb-0 text-dark" style="font-size:0.7rem;">Op. Bancaria</label>
                                                                    <select class="form-select form-select-sm" id="pagoTipoOp">
                                                                        <option value="TRANSFERENCIA">Transferencia</option>
                                                                        <option value="DEBITO">Débito</option>
                                                                        <option value="CHEQUE">Cheque</option>
                                                                    </select>
                                                                </div>
                                                                <div class="col-6">
                                                                    <label class="form-label fw-bold mb-0 text-dark" style="font-size:0.7rem;">Nº Referencia</label>
                                                                    <input type="text" class="form-control form-control-sm" id="pagoNumOp" placeholder="Nº doc / Transf">
                                                                </div>
                                                                <div class="col-12">
                                                                    <label class="form-label fw-bold mb-0 text-dark" style="font-size:0.7rem;">Banco</label>
                                                                    <select class="form-select form-select-sm" id="pagoBancoId">
                                                                        <option value="">- Opcional -</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-12">
                                                            <label class="form-label fw-bold mb-0 text-muted" style="font-size:0.7rem;">Observaciones / Notas</label>
                                                            <input type="text" class="form-control form-control-sm shadow-none border-secondary-subtle" id="pagoObservaciones" placeholder="Comentario del pago">
                                                        </div>

                                                        <div class="col-12 mt-2">
                                                            <button type="submit" class="btn btn-success btn-sm w-100 py-2 fw-bold shadow-sm border-0" style="background: #198754;" id="pagoBtnRegistrar">
                                                                <i class="bi bi-check-circle me-2"></i>Registrar Pago y Generar Egreso
                                                            </button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                        
                                        <!-- Alerta de Documento Completamente Pagado -->
                                        <div class="alert alert-success border-success border-opacity-25 text-center py-4 shadow-sm mb-0 d-none" id="pagoAlertaPagada">
                                            <i class="bi bi-check-circle-fill fs-2 mb-2 text-success d-block"></i>
                                            <h6 class="fw-bold mb-1 text-success">¡Documento Completamente Pagado!</h6>
                                            <p class="text-muted mb-0" style="font-size: 0.75rem;">El saldo pendiente de esta liquidación es de $0.00.</p>
                                        </div>
                                        
                                        <!-- Alerta para Nuevas Liquidaciones -->
                                        <div class="alert alert-secondary bg-light border-secondary-subtle text-center py-4 shadow-sm mb-0" id="pagoAlertaNueva">
                                            <i class="bi bi-credit-card-2-front fs-2 mb-2 text-muted d-block opacity-50"></i>
                                            <h6 class="fw-bold mb-1 text-secondary">Nuevo Registro</h6>
                                            <p class="text-muted mb-0" style="font-size: 0.75rem;">Guarda la liquidación primero para poder habilitar el registro de pagos internos.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB: RETENCIONES -->
                        <div class="tab-pane fade" id="tab-liq-retenciones" role="tabpanel">
                            <div class="card cmg-table-card border-0 shadow-none">
                                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-primary btn-sm px-3" id="btnNuevaRetencionLiq" onclick="window.LC_nuevaRetencionDesdeLiq()" disabled>
                                            <i class="bi bi-plus-circle me-1"></i> Emitir Retención
                                        </button>
                                    </div>
                                    <div class="ms-auto" id="lc-retenciones-info"></div>
                                </div>

                                <div class="card-body p-0">
                                    <div class="table-responsive" style="max-height: 400px;">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="table-light sticky-top">
                                                <tr>
                                                    <th class="ps-3" style="width: 180px;">Nº Retención</th>
                                                    <th style="width: 120px;">Fecha</th>
                                                    <th class="text-end" style="width: 120px;">Monto</th>
                                                    <th class="text-center" style="width: 120px;">Estado</th>
                                                    <th style="width: 40px;"></th>
                                                </tr>
                                            </thead>
                                            <tbody id="lc-tbody-retenciones">
                                                <tr>
                                                    <td colspan="5" class="text-center py-4 text-muted">
                                                        <i class="bi bi-file-earmark-text d-block fs-3 mb-2"></i>
                                                        No hay retenciones registradas para esta liquidación
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade py-3" id="tab-liq-sri" role="tabpanel">
                            <div class="row g-3">
                                <!-- Estado de autorización -->
                                <div class="col-12">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span class="fw-bold small text-muted">Estado de autorización:</span>
                                        <span id="liq-sri-badge-estado" class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2">Sin enviar</span>
                                    </div>
                                </div>
                                <!-- Reglas SRI para anulación -->
                                <div class="col-12">
                                    <div class="border rounded-2 bg-warning bg-opacity-10 border-warning border-opacity-25 p-2">
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <i class="bi bi-info-circle-fill text-warning"></i>
                                            <span class="fw-bold small text-warning-emphasis">Plazos permitidos para anular una liquidación de compra</span>
                                        </div>
                                        <ul class="mb-0 ps-3 small text-muted" style="line-height:1.6;">
                                            <li><strong>Tiempo límite:</strong> Hasta el día <strong>7 del mes siguiente</strong> a la fecha de emisión del documento.</li>
                                            <li><strong>Excepciones:</strong> Si el día 7 cae en fin de semana o feriado, el plazo se extiende al siguiente día hábil.</li>
                                        </ul>
                                    </div>
                                </div>
                                <!-- Fila 1: Clave de Acceso + Número de Autorización -->
                                <div class="col-md-6">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-key me-1"></i>Clave de Acceso</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" id="liq-sri-clave-acceso" class="form-control form-control-sm font-monospace bg-light" readonly placeholder="- sin clave de acceso -" value="">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="liqCopiarCampoSri('liq-sri-clave-acceso')" title="Copiar clave de acceso">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-hash me-1"></i>Número de Autorización</label>
                                    <input type="text" id="liq-sri-numero-autorizacion" class="form-control form-control-sm font-monospace bg-light" readonly placeholder="- pendiente -" value="">
                                </div>
                                <!-- Fila 2: Tipo de Ambiente + Tipo de Emisión + Fecha de Autorización + Tipo de Documento -->
                                <div class="col-md-3">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-building me-1"></i>Tipo de Ambiente</label>
                                    <input type="text" id="liq-sri-ambiente" class="form-control form-control-sm bg-light" readonly placeholder="- pendiente -" value="">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-broadcast me-1"></i>Tipo de Emisión</label>
                                    <input type="text" id="liq-sri-tipo-emision" class="form-control form-control-sm bg-light" readonly placeholder="- pendiente -" value="">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-calendar-check me-1"></i>Fecha de Autorización</label>
                                    <input type="text" id="liq-sri-fecha-autorizacion" class="form-control form-control-sm bg-light" readonly placeholder="- pendiente -" value="">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-file-earmark-text me-1"></i>Tipo de Documento</label>
                                    <input type="text" id="liq-sri-tipo-documento" class="form-control form-control-sm bg-light" readonly value="Liquidación de Compra">
                                </div>
                                <!-- Fila 3: Número de Documento + Número de Identificación + Correo del Proveedor -->
                                <div class="col-md-3">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-receipt me-1"></i>Número de Documento</label>
                                    <input type="text" id="liq-sri-numero-documento" class="form-control form-control-sm font-monospace bg-light" readonly placeholder="000-000-000000000" value="">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-person-vcard me-1"></i>Número de Identificación</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" id="liq-sri-identificacion-proveedor" class="form-control form-control-sm bg-light" readonly placeholder="- sin identificación -" value="">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="liqCopiarCampoSri('liq-sri-identificacion-proveedor')" title="Copiar identificación">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-envelope me-1"></i>Correo del Proveedor</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" id="liq-sri-correo-proveedor" class="form-control form-control-sm bg-light" readonly placeholder="- sin correo -" value="">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="liqCopiarCampoSri('liq-sri-correo-proveedor')" title="Copiar correo">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                </div>
                                <!-- Mensajes / Observaciones SRI -->
                                <div class="col-12">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-chat-left-text me-1"></i>Mensajes del SRI</label>
                                    <div id="liq-sri-mensajes-container" class="border rounded-2 bg-light p-2" style="min-height: 80px; font-size: 0.8rem;">
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
                                            <tbody id="liq-sri-tbody-historial">
                                                <tr>
                                                    <td colspan="4" class="text-center py-3 text-muted">Sin historial de envíos.</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="modal-footer justify-content-between bg-light border-top p-2 px-3">
                        <div></div>
                        <div>
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="bi bi-x me-1"></i>Cerrar</button>
                            <button type="button" class="btn btn-primary px-4 btn-sm shadow-sm" id="btnGuardarLiq" onclick="window.LC_guardar()">
                                <i class="bi bi-check2-circle me-1"></i> Guardar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Incluir el Modal de Proveedor Reutilizable -->
<?php include __DIR__ . '/../proveedores/modal_proveedor.php'; ?>

<script>
    // Definir constantes del sistema ANTES de cargar los archivos JS
    window.BASE_URL  = '<?= $base ?>';
    window.B_URL     = window.BASE_URL;
    window.R_MODULO  = '<?= $rutaModulo ?>';
    window.ID_EMPRESA = <?= (int)($_SESSION['id_empresa'] ?? 0) ?>;
    window.ID_USUARIO = <?= (int)($_SESSION['id_usuario'] ?? 0) ?>;


    // Pasar catálogos a JS
    window.TARIFAS_IVA = <?= json_encode($tarifasIva ?? []) ?>;
    window.FORMAS_PAGO_SRI = <?= json_encode($formasPago ?? []) ?>;
    window.STAR_PAGO_HTML = `<?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('liquidacion-compra', 'liq-pago-fav', 'liq_pago_fav') ?>`;

    console.log('Liquidación Compra - Configuración:', {
        B_URL,
        R_MODULO
    });
    console.log('Catálogos cargados:', {
        TARIFAS_IVA: window.TARIFAS_IVA.length,
        FORMAS_PAGO: window.FORMAS_PAGO_SRI.length
    });
</script>

<!-- Scripts -->
<script src="<?= $base ?>/js/modulos/proveedores_modal.js?v=<?= time() ?>"></script>
<script src="<?= $base ?>/js/modulos/liquidacion_compra.js?v=<?= time() ?>"></script>