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
/** @var array $establecimientos */
/** @var array $puntos */
/** @var array $formasCobro */
/** @var array $conceptos */
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

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>

<style>
    .ing-header {
        flex-shrink: 0;
    }

    .ingreso-scroll {
        max-height: calc(100vh - 240px);
        overflow-y: auto;
    }

    .ingreso-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }

    .ingreso-row {
        cursor: pointer;
    }

    .ingreso-row:hover {
        background-color: rgba(0, 0, 0, .04);
    }

    .table-detalle th {
        font-size: 0.75rem;
        text-transform: uppercase;
        background-color: #f8f9fa;
        padding: 6px 8px !important;
    }

    .table-detalle td {
        padding: 4px 8px !important;
        vertical-align: middle;
    }

    .input-numeric {
        text-align: right;
        font-family: monospace;
    }

    .total-big {
        font-size: 1.3rem;
        font-weight: 800;
        color: var(--bs-primary);
    }

    .dropdown-predictivo {
        z-index: 2000 !important;
    }
    .input-detalle {
        border: none;
        background: transparent;
        font-size: 0.85rem;
        padding: 4px 8px;
        box-shadow: none !important;
    }
    .input-detalle:focus {
        background: #fff;
        box-shadow: inset 0 0 0 1px #0d6efd !important;
        outline: none;
    }
    .row-detalle:hover {
        background-color: rgba(13, 110, 253, 0.03);
    }
    .remove-row {
        color: #dc3545;
        cursor: pointer;
        opacity: 0.6;
        transition: opacity 0.2s;
    }
    .row-detalle:hover .remove-row {
        opacity: 1;
    }
</style>

<div class="ing-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-wallet2 me-1 text-primary"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="abrirModalIngreso()">
            <i class="bi bi-plus-lg me-1"></i> Nuevo
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_busqueda.css?v=<?= time() ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_busqueda.js?v=<?= time() ?>"></script>
            <div id="fbBuscadorING" style="width: 480px;"></div>
            <input type="hidden" id="buscarIngreso" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosBusqueda) return;
                    new FiltrosBusqueda({
                        containerId: 'fbBuscadorING',
                        hiddenInputId: 'buscarIngreso',
                        fields: [
                            { key: 'recibo_de', label: 'Recibo de',   icon: 'bi-person-badge',    type: 'text' },
                            { key: 'cliente',   label: 'Cliente',     icon: 'bi-person',          type: 'text' },
                            { key: 'ruc',       label: 'RUC',         icon: 'bi-card-text',       type: 'text' },
                            { key: 'numero',    label: 'Nº ingreso',  icon: 'bi-hash',            type: 'text' },
                            { key: 'concepto',  label: 'Concepto',    icon: 'bi-chat-left-text',  type: 'text' },
                            { key: 'tipo',      label: 'Tipo ingreso',icon: 'bi-tag',             type: 'text' },
                            { key: 'fecha',     label: 'Fecha',       icon: 'bi-calendar-event',  type: 'date_range' },
                            { key: 'monto',     label: 'Monto',       icon: 'bi-currency-dollar', type: 'number_range' },
                            { key: 'estado',    label: 'Estado',      icon: 'bi-flag',            type: 'select', options: [
                                { v: 'aprobado', l: 'Aprobado' },
                                { v: 'borrador', l: 'Borrador' },
                                { v: 'anulado',  l: 'Anulado' },
                            ]},
                        ],
                        quickFilters: [
                            { id: 'qf_aprobado',   label: 'Aprobados',  mk: () => ({ key: 'estado', op: '=', value: 'aprobado', display: 'Aprobado' }) },
                            { id: 'qf_borrador',   label: 'Borradores', mk: () => ({ key: 'estado', op: '=', value: 'borrador', display: 'Borrador' }) },
                            { id: 'qf_anulado',    label: 'Anulados',   mk: () => ({ key: 'estado', op: '=', value: 'anulado',  display: 'Anulado' }) },
                            { id: 'qf_hoy',        label: 'Hoy',        mk: () => FiltrosBusqueda.helpers.hoyMismo('fecha') },
                            { id: 'qf_mes',        label: 'Este mes',   mk: () => FiltrosBusqueda.helpers.esteMes('fecha') },
                            { id: 'qf_mes_pasado', label: 'Mes pasado', mk: () => FiltrosBusqueda.helpers.mesPasado('fecha') },
                            { id: 'qf_anio',       label: 'Este año',   mk: () => FiltrosBusqueda.helpers.esteAnio('fecha') },
                        ],
                        onApply: () => window.ING_fetchSearch && window.ING_fetchSearch(1),
                    }).init();
                });
            </script>

            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'numero_ingreso' => 'Nº Ingreso',
                    'fecha_emision'  => 'Fecha',
                    'tipo_ingreso'   => 'Tipo',
                    'recibo_de'      => 'Recibo de',
                    'observaciones'  => 'Observaciones',
                    'monto_total'    => 'Monto',
                    'estado'         => 'Estado',
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>

                <a id="btnExportPdf" href="<?= $urlBase ?>/export-pdf?b=<?= urlencode($buscar) ?>" class="btn btn-outline-danger" title="Descargar PDF">
                    <i class="bi bi-file-earmark-pdf"></i> PDF
                </a>
                <a id="btnExportExcel" href="<?= $urlBase ?>/export-excel?b=<?= urlencode($buscar) ?>" class="btn btn-outline-success" title="Descargar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                </a>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="paginationContainer" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" <?= $page <= 1 ? 'disabled' : '' ?> onclick="window.ING_cambiarPaginaAjax(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" <?= $page >= $totalPages ? 'disabled' : '' ?> onclick="window.ING_cambiarPaginaAjax(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="ingreso-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="numero_ingreso" data-col="numero_ingreso">
                            Nº Ingreso <i class="bi <?= $ordenCol === 'numero_ingreso' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="sortable-header" role="button" data-sort="fecha_emision" data-col="fecha_emision">
                            Fecha <i class="bi <?= $ordenCol === 'fecha_emision' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="sortable-header" role="button" data-sort="tipo_ingreso" data-col="tipo_ingreso">
                            Tipo <i class="bi <?= $ordenCol === 'tipo_ingreso' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="sortable-header" role="button" data-sort="recibo_de" data-col="recibo_de">
                            Recibo de <i class="bi <?= $ordenCol === 'recibo_de' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="sortable-header" role="button" data-sort="observaciones" data-col="observaciones">
                            Observaciones <i class="bi <?= $ordenCol === 'observaciones' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="sortable-header text-end" role="button" data-sort="monto_total" data-col="monto_total">
                            Monto <i class="bi <?= $ordenCol === 'monto_total' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="text-center pe-3 sortable-header" role="button" data-sort="estado" data-col="estado">
                            Estado <i class="bi <?= $ordenCol === 'estado' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                    </tr>
                </thead>
                <tbody id="tbodyIngresos">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-wallet2 fs-3 d-block mb-2"></i>No se encontraron registros.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $tipoLabels = ['FACTURA_VENTA' => 'Facturas de Venta', 'RECIBO_VENTA' => 'Recibo de Venta', 'OTRO' => 'Otro Ingreso'];
                            $tipoLabel = $tipoLabels[$r['tipo_ingreso']] ?? $r['tipo_ingreso'];
                            $estado  = $r['estado'] ?? 'registrado';
                            $estadoClass = match ($estado) {
                                'anulado'  => 'bg-danger bg-opacity-10 text-danger border-danger',
                                'borrador' => 'bg-secondary bg-opacity-10 text-secondary border-secondary',
                                default    => 'bg-success bg-opacity-10 text-success border-success',
                            };
                            ?>
                            <tr class="ingreso-row" role="button" onclick="abrirModalIngresoVer(<?= $r['id'] ?>)">
                                <td class="ps-3" data-col="numero_ingreso"><code class="text-secondary"><?= htmlspecialchars($r['numero_ingreso'] ?? '') ?></code></td>
                                <td data-col="fecha_emision"><?= !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '-' ?></td>
                                <td data-col="tipo_ingreso"><span class="badge bg-light text-dark border"><?= htmlspecialchars($tipoLabel) ?></span></td>
                                <td class="fw-medium text-truncate" data-col="recibo_de" style="max-width:200px"><?= htmlspecialchars($r['recibo_de'] ?? $r['cliente_nombre'] ?? $r['concepto_nombre'] ?? '-') ?></td>
                                <td data-col="observaciones" class="text-truncate text-muted" style="max-width:200px"><?= htmlspecialchars($r['observaciones'] ?? '') ?></td>
                                <td class="text-end fw-bold" data-col="monto_total">$<?= number_format((float)$r['monto_total'], 2) ?></td>
                                <td class="text-center pe-3" data-col="estado"><span class="badge <?= $estadoClass ?> border border-opacity-25"><?= ucfirst($estado) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Principal Nuevo / Ver Ingreso -->
<div class="modal fade" id="modalNuevoIngreso" data-bs-backdrop="static" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg border-0 cmg-favoritos-card" data-modulo="ingresos">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold">
                    <i id="modalIngresoIcono" class="bi bi-wallet2 text-primary me-2"></i>
                    <span id="modalIngresoTitulo">Registrar Nuevo Ingreso</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0">
                <!-- Barra de Acciones Superior -->
                <div class="px-3 py-2 bg-light border-bottom d-flex gap-1 align-items-center flex-wrap">
                    <button type="button" class="btn btn-outline-primary btn-sm px-2" onclick="window.modalCrearFormaPago ? window.modalCrearFormaPago() : Swal.fire('Módulo en desarrollo','','info')" title="Crear Forma de Pago">
                        <i class="bi bi-credit-card fs-6"></i>
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sm px-2" onclick="window.modalCrearOpcionIngreso ? window.modalCrearOpcionIngreso() : Swal.fire('Módulo en desarrollo','','info')" title="Crear Opción de Ingreso">
                        <i class="bi bi-tags fs-6"></i>
                    </button>
                    <div class="vr mx-1"></div>
                    <button type="button" class="btn btn-outline-primary btn-sm px-2" onclick="window.abrirModalClienteCrear ? window.abrirModalClienteCrear() : Swal.fire('Módulo en desarrollo','','info')" title="Registrar nuevo cliente">
                        <i class="bi bi-person-plus fs-6"></i>
                    </button>
                    <!-- Botones de concepto de ingreso (derecha) -->
                    <div id="concepto-btns-group" class="ms-auto d-flex gap-1 align-items-center flex-wrap">
                        <div class="vr mx-1"></div>
<?php foreach ($conceptos as $c): ?>
                        <button type="button"
                                class="btn btn-sm btn-outline-secondary concepto-ingreso-btn"
                                data-id="<?= $c['id'] ?>"
                                data-comportamiento="<?= htmlspecialchars($c['comportamiento'] ?? 'GENERAL') ?>">
                            <?= htmlspecialchars($c['nombre']) ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Pestañas Principales del Modal -->
                <div class="d-flex align-items-center bg-light px-3 pt-2">
                    <ul class="nav nav-tabs border-bottom-0 flex-grow-1 tab-pestaña" id="tabsModalIngreso" role="tablist">
                        <li class="nav-item"><a class="nav-link active py-2 small fw-bold" id="tab-ingreso-gen-btn" data-bs-toggle="tab" href="#m-tab-general" data-bs-target="#m-tab-general" role="tab"><i class="bi bi-card-text me-1"></i> General</a></li>
                        <li class="nav-item"><a class="nav-link py-2 small fw-bold" id="tab-ingreso-cnt-btn" data-bs-toggle="tab" href="#m-tab-contable" data-bs-target="#m-tab-contable" role="tab"><i class="bi bi-calculator me-1"></i> Asiento contable</a></li>
                    </ul>
                    <div class="ms-auto pb-1">
                        <?php
                        $pestanasConfig = [
                            'm-tab-contable' => 'Asiento contable'
                        ];
                        echo \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasConfig, $vistaConfig ?? [], 'ingresos');
                        ?>
                    </div>
                </div>
                <div class="border-bottom bg-light mb-0"></div>

                <form id="formIngresoModal">
                    <input type="hidden" name="id" id="m-input-id" value="">

                    <div class="tab-content border-top">
                        <!-- PESTAÑA 1: GENERAL -->
                        <div class="tab-pane fade show active" id="m-tab-general" role="tabpanel">

                            <!-- Cabecera Principal -->
                            <div class="p-3 bg-white border-bottom">
                                <div class="row g-3">

                                    <!-- Fila: Fecha, Serie, Secuencial y Recibo de -->
                                    <div class="col-md-2">
                                        <label class="form-label small fw-bold">Fecha de Emisión</label>
                                        <input type="date" name="fecha_emision" id="m-input-fecha" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small fw-bold d-flex align-items-center">Serie <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('ingresos', 'm-select-punto', 'id_punto_emision') ?></label>
                                        <select name="id_punto_emision" id="m-select-punto" class="form-select form-select-sm" onchange="syncIngresoSecuencial(this.value)" required>
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
                                        <input type="hidden" name="establecimiento" id="m-txt-establecimiento">
                                        <input type="hidden" name="punto_emision" id="m-txt-punto">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small fw-bold">Secuencial</label>
                                        <input type="text" name="secuencial" id="m-input-secuencial" class="form-control form-control-sm bg-light" readonly>
                                    </div>
                                    <!-- Recibo de -->
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">
                                            <i class="bi bi-person-badge text-primary me-1"></i> Recibo de <span class="text-danger">*</span>
                                        </label>
                                        <div class="position-relative">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
                                                <input type="text" id="m-recibo-de-input" class="form-control fw-medium"
                                                       placeholder="Nombre del cliente o de quien se ha recibido el ingreso..." autocomplete="off" required>
                                                <input type="hidden" name="recibo_de" id="m-input-recibo-de">
                                                <input type="hidden" name="id_recibo_cliente" id="m-input-id-recibo-cliente">
                                            </div>
                                            <div id="m-dropdown-recibo-de" class="list-group shadow dropdown-predictivo position-absolute d-none w-100" style="max-height:200px; overflow-y:auto; z-index:2100;"></div>
                                        </div>
                                    </div>
                                    <!-- Select oculto: fuente de verdad del concepto seleccionado -->
                                    <select name="id_ingreso_concepto" id="m-select-concepto" class="d-none" onchange="manejarCambioConceptoIngreso(this)">
                                        <option value="" data-comportamiento="GENERAL"></option>
                                        <?php foreach ($conceptos as $c): ?>
                                            <option value="<?= $c['id'] ?>" data-comportamiento="<?= htmlspecialchars($c['comportamiento'] ?? 'GENERAL') ?>">
                                                <?= htmlspecialchars($c['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="tipo_ingreso" id="m-input-tipo-ingreso" value="GENERAL">

                                    <input type="hidden" name="id_cliente" id="m-input-id-cliente">
                                </div>
                            </div>

                            <!-- Sub-Tabs Internos (Mantener Estructura de Detalle vs Cobros) -->
                            <div class="d-flex align-items-center bg-light px-3 pt-2 border-bottom">
                                <ul class="nav nav-tabs nav-tabs-sm border-bottom-0 flex-grow-1" role="tablist" style="font-size: 0.8rem;">
                                    <li class="nav-item">
                                        <a class="nav-link active py-1 px-3 fw-bold" data-bs-toggle="tab" href="#subtab-detalles" role="tab"><i class="bi bi-list-ul me-1"></i> Detalle / Documentos</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link py-1 px-3 fw-bold" data-bs-toggle="tab" href="#subtab-cobros" role="tab"><i class="bi bi-cash-coin me-1"></i> Formas de Cobro</a>
                                    </li>
                                </ul>
                            </div>

                            <div class="tab-content bg-white">
                                <!-- SUB-TAB DETALLES -->
                                <div class="tab-pane fade show active p-3" id="subtab-detalles" role="tabpanel">

                                    <!-- Tabla para FACTURAS PENDIENTES -->
                                    <div id="m-block-facturas">
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <h6 class="small fw-bold mb-0 text-secondary">Documentos seleccionados</h6>
                                            <span id="m-lbl-status-pend" class="badge bg-light text-muted border d-none">Cargando...</span>
                                        </div>
                                        <div class="table-responsive border rounded mb-3" style="max-height: 220px;">
                                            <table class="table table-sm table-detalle mb-0">
                                                <thead class="table-light sticky-top">
                                                    <tr>
                                                        <th style="width: 40px;"></th>
                                                        <th>Factura</th>
                                                        <th style="min-width:120px;">Cliente</th>
                                                        <th>Fecha</th>
                                                        <th class="text-end">Total</th>
                                                        <th class="text-end text-nowrap" style="width: 130px;">Monto cobrado</th>
                                                        <th style="width: 36px;"></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="m-tbody-docs-pendientes">
                                                    <tr>
                                                        <td colspan="6" class="text-center text-muted py-3 small">Use el buscador para añadir documentos pendientes de uno o más clientes.</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <!-- Tabla para OTROS CONCEPTOS (Manual) -->
                                    <div id="m-block-otros" class="d-none">
                                        <div class="border rounded-3 overflow-hidden bg-white shadow-sm mt-2">
                                            <div class="table-responsive" style="max-height: 220px;">
                                                <table class="table table-sm table-detalle mb-0 text-nowrap align-middle">
                                                    <thead class="table-light border-bottom">
                                                        <tr>
                                                            <th class="ps-3 py-2 small fw-bold text-muted" style="width: 75%;">Descripción / Concepto</th>
                                                            <th class="py-2 small fw-bold text-muted text-end pe-4" style="width: 20%;">Monto</th>
                                                            <th style="width: 40px;"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="m-tbody-otros-manual">
                                                        <!-- Filas editables dinámicas -->
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="p-2 border-top bg-light d-flex justify-content-between align-items-center">
                                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" onclick="agregarFilaManualIngreso()">
                                                    <i class="bi bi-plus-circle me-1"></i> Agregar línea
                                                </button>
                                                <div class="small fw-bold text-muted pe-3">
                                                    Items: <span id="m-man-count-items">0</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Sección Observaciones Generales -->
                                    <div class="mt-3">
                                        <label class="form-label small fw-bold">Observaciones Generales</label>
                                        <textarea name="observaciones" id="m-input-observaciones" rows="2" class="form-control form-control-sm" placeholder="Escriba anotaciones adicionales aquí..."></textarea>
                                    </div>
                                </div>

                                <!-- SUB-TAB COBROS -->
                                <div class="tab-pane fade p-3" id="subtab-cobros" role="tabpanel">
                                    <div class="alert alert-light border p-2 small mb-3 d-flex align-items-center justify-content-between">
                                        <span>Total a liquidar:</span>
                                        <span class="fw-bold fs-5 text-dark" id="m-sumary-total-doc">$0.00</span>
                                    </div>

                                    <div class="row g-2 mb-2 bg-light p-2 rounded border">
                                        <div class="col-md-5">
                                            <label class="form-label small fw-bold d-flex align-items-center">Forma de Cobro <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('ingresos', 'm-add-cobro-forma', 'id_forma_cobro_default') ?></label>
                                            <select id="m-add-cobro-forma" class="form-select form-select-sm">
                                                <option value="">-- Seleccione --</option>
                                                <?php foreach ($formasCobro as $fc): ?>
                                                    <option value="<?= $fc['id'] ?>" data-tipo="<?= htmlspecialchars($fc['tipo'] ?? '') ?>"><?= htmlspecialchars($fc['nombre']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold">Monto</label>
                                            <input type="number" id="m-add-cobro-monto" class="form-control form-control-sm input-numeric fw-bold" step="0.01" value="0.00">
                                        </div>
                                        <div class="col-md-3 d-flex align-items-end">
                                            <button type="button" class="btn btn-primary btn-sm w-100" onclick="agregarLineaCobro()">
                                                <i class="bi bi-plus-lg me-1"></i> Agregar
                                            </button>
                                        </div>
                                        <!-- Campos condicionales para BANCO -->
                                        <div id="wrapper-banco-extra" class="col-12 d-none">
                                            <div class="p-2 bg-white border rounded shadow-sm mt-1 mb-1">
                                                <div class="row g-2">
                                                    <div class="col-md-4">
                                                        <label class="form-label small fw-bold">Operación Bancaria</label>
                                                        <select id="m-add-cobro-tipo-banco" class="form-select form-select-sm bg-warning bg-opacity-10">
                                                            <option value="DEPOSITO">Depósito</option>
                                                            <option value="TRANSFERENCIA" selected>Transferencia</option>
                                                            <option value="CHEQUE">Cheque</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4 div-cheque-fields d-none">
                                                        <label class="form-label small fw-bold text-primary"><i class="bi bi-card-checklist me-1"></i>N° Cheque</label>
                                                        <input type="text" id="m-add-cobro-num-cheque" class="form-control form-control-sm border-primary" placeholder="000123">
                                                    </div>
                                                    <div class="col-md-4 div-cheque-fields d-none">
                                                        <label class="form-label small fw-bold text-primary"><i class="bi bi-calendar-date me-1"></i>Fecha Cobro</label>
                                                        <input type="date" id="m-add-cobro-fecha-cheque" class="form-control form-control-sm border-primary">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label small fw-bold">Referencia / Glosa General</label>
                                            <input type="text" id="m-add-cobro-ref" class="form-control form-control-sm" placeholder="Ej: Comprobante #5522 o detalle extra...">
                                        </div>
                                    </div>

                                    <div class="table-responsive border rounded" style="min-height: 120px;">
                                        <table class="table table-sm table-detalle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Método</th>
                                                    <th>Referencia</th>
                                                    <th class="text-end" style="width: 120px;">Monto</th>
                                                    <th style="width: 40px;"></th>
                                                </tr>
                                            </thead>
                                            <tbody id="m-tbody-pagos">
                                                <tr>
                                                    <td colspan="4" class="text-center text-muted py-3 small">Agregue las formas en que se recibió el dinero.</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- FOOTER DENTRO DE MODAL BODY PARA MANTENER TOTALES VISIBLES -->
                            <div class="p-3 bg-light border-top d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="small text-muted mb-1">Suma Formas de Cobro:</div>
                                    <div id="m-footer-cobro-tot" class="fw-bold text-muted">$0.00</div>
                                </div>
                                <div class="text-end">
                                    <div class="small fw-bold text-muted mb-0">MONTO TOTAL INGRESO</div>
                                    <div class="total-big" id="m-final-total">$ 0.00</div>
                                </div>
                            </div>
                        </div>

                        <!-- PESTAÑA 2: ASIENTO CONTABLE -->
                        <div class="tab-pane fade p-3" id="m-tab-contable" role="tabpanel">
                            <div class="alert alert-info py-2 small mb-3">
                                <i class="bi bi-info-circle me-1"></i> Aquí se generará automáticamente el asiento contable del ingreso una vez guardado.
                            </div>
                            <div class="table-responsive border rounded" style="max-height: 380px;">
                                <table class="table table-sm small mb-0 table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-3 py-2" style="width: 45%;">Cuenta Contable</th>
                                            <th class="text-end pe-3" style="width: 25%;">Débito / Debe</th>
                                            <th class="text-end pe-3" style="width: 25%;">Crédito / Haber</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody-asiento-contable">
                                        <tr>
                                            <td colspan="3" class="text-center py-5 text-muted">
                                                <i class="bi bi-calculator fs-4 d-block mb-2"></i>
                                                El asiento contable se visualiza al consultar un registro guardado.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer justify-content-between bg-light border-top p-2">
                <div id="m-container-footer-ver" class="d-none">
                    <button type="button" class="btn btn-outline-danger btn-sm" id="btnAnularIngreso" onclick="anularIngreso()">
                        <i class="bi bi-slash-circle me-1"></i> Anular
                    </button>
                </div>
                <div class="ms-auto">
                    <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">
                        <i class="fa-solid fa-xmark me-1"></i> Cerrar
                    </button>
                    <button type="button" class="btn btn-primary btn-sm px-4 shadow-sm" id="btnGuardarIngreso" onclick="guardarIngreso()">
                        <i class="bi bi-check2-circle me-1"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Variables y Estados locales
    let docPendientes = [];
    let detalleManual = [];
    let formasPagoData = [];
    let esIngresoModoLectura = false;
    let clientesCargados = {}; // { id_cliente: nombre } – tracking de clientes ya cargados

    const RUTA_MODULO_JS = '<?= $rutaModulo ?>';

    document.addEventListener('DOMContentLoaded', () => {
        const puntoSel = document.getElementById('m-select-punto');
        if (puntoSel) {
            syncIngresoSecuencial(puntoSel.value);
        }

        // Autocomplete para "Recibo de"
        const inputReciboDe = document.getElementById('m-recibo-de-input');
        const dropdownReciboDe = document.getElementById('m-dropdown-recibo-de');
        let timerReciboDe = null;
        if (inputReciboDe) {
            inputReciboDe.addEventListener('input', (e) => {
                // Sincronizar texto al hidden
                document.getElementById('m-input-recibo-de').value = e.target.value;
                document.getElementById('m-input-id-recibo-cliente').value = '';

                clearTimeout(timerReciboDe);
                const val = e.target.value.trim();
                if (val.length < 2) {
                    dropdownReciboDe.classList.add('d-none');
                    return;
                }
                timerReciboDe = setTimeout(() => fetchClientesReciboDe(val), 300);
            });
        }

        // Clic fuera cierra dropdowns
        document.addEventListener('click', (e) => {
            if (dropdownReciboDe && !dropdownReciboDe.contains(e.target) && e.target !== inputReciboDe) {
                dropdownReciboDe.classList.add('d-none');
            }
        });
    });

    function syncIngresoSecuencial(idPunto) {
        const sel = document.getElementById('m-select-punto');
        if (!sel) return;
        const opt = sel.options[sel.selectedIndex];
        if (!opt) return;

        document.getElementById('m-id-establecimiento').value = opt.dataset.est || '';
        document.getElementById('m-txt-establecimiento').value = opt.dataset.codEst || '';
        document.getElementById('m-txt-punto').value = opt.dataset.codPunto || '';

        if (!idPunto) {
            document.getElementById('m-input-secuencial').value = '';
            return;
        }

        fetch(`<?= BASE_URL ?>/<?= $rutaModulo ?>/getSecuencialAjax?id_punto_emision=${idPunto}`)
            .then(r => r.json())
            .then(res => {
                // En modo edición (ya existe un ID) NO se recalcula el secuencial:
                // debe conservarse el secuencial original del documento que se está editando.
                if (document.getElementById('m-input-id')?.value) return;
                if (res.ok) {
                    document.getElementById('m-input-secuencial').value = String(res.secuencial).padStart(9, '0');
                }
            })
            .catch(e => console.error(e));
    }

    function sincronizarBotonesConcepto(id) {
        document.querySelectorAll('.concepto-ingreso-btn').forEach(btn => {
            const activo = btn.dataset.id == id;
            btn.classList.toggle('btn-secondary', activo);
            btn.classList.toggle('btn-outline-secondary', !activo);
            btn.classList.toggle('active', activo);
        });
    }

    function setConceptoBotonesDisabled(disabled) {
        document.querySelectorAll('.concepto-ingreso-btn').forEach(btn => {
            btn.disabled = disabled;
        });
    }

    function manejarCambioConceptoIngreso(sel) {
        const opt = sel.options[sel.selectedIndex];
        const comp = opt ? (opt.dataset.comportamiento || 'GENERAL') : 'GENERAL';

        sincronizarBotonesConcepto(sel.value);
        document.getElementById('m-input-tipo-ingreso').value = comp;

        // Limpiar
        docPendientes = [];
        detalleManual = [];
        clientesCargados = {};
        actualizarInfoClientesCargados();
        renderDetalles();
        recalcularTotales();

        if (['FACTURA_VENTA', 'RECIBO_VENTA'].includes(comp)) {
            document.getElementById('m-block-facturas').classList.remove('d-none');
            document.getElementById('m-block-otros').classList.add('d-none');
            abrirModalDocsPendientes();
        } else {
            document.getElementById('m-block-facturas').classList.add('d-none');
            document.getElementById('m-block-otros').classList.remove('d-none');
        }
    }

    // Click en botones de concepto
    document.querySelectorAll('.concepto-ingreso-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const sel = document.getElementById('m-select-concepto');
            // Si ya está seleccionado este mismo concepto, no hacer nada
            if (sel.value == this.dataset.id) return;

            const hayDatos = docPendientes.length > 0
                        || detalleManual.some(d => d.descripcion.trim() !== '' || parseFloat(d.monto) > 0);

            const aplicarCambio = () => {
                sel.value = this.dataset.id;
                manejarCambioConceptoIngreso(sel);
            };

            if (hayDatos) {
                Swal.fire({
                    title: '¿Cambiar concepto?',
                    text: 'Al cambiar el concepto se eliminará toda la información de detalle que has agregado. ¿Deseas continuar?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: '<i class="bi bi-trash3 me-1"></i> Sí, cambiar',
                    cancelButtonText: 'Cancelar',
                }).then(result => {
                    if (result.isConfirmed) aplicarCambio();
                });
            } else {
                aplicarCambio();
            }
        });
    });

    // ── Autocomplete "Recibo de" ────────────────────────────────────────────
    function fetchClientesReciboDe(q) {
        const dropdown = document.getElementById('m-dropdown-recibo-de');
        fetch(`<?= BASE_URL ?>/<?= $rutaModulo ?>/getClientesAjax?q=${encodeURIComponent(q)}`)
            .then(r => r.json())
            .then(res => {
                dropdown.innerHTML = '';
                if (!res.data || res.data.length === 0) {
                    dropdown.innerHTML = '<div class="list-group-item small text-muted">Sin resultados</div>';
                } else {
                    res.data.forEach(cli => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'list-group-item list-group-item-action py-2 border-bottom';
                        btn.innerHTML = `<div class="d-flex justify-content-between">
                            <strong>${cli.nombre}</strong>
                            <small class="text-muted">${cli.identificacion}</small>
                        </div>`;
                        btn.onclick = () => seleccionarClienteReciboDe(cli);
                        dropdown.appendChild(btn);
                    });
                }
                dropdown.classList.remove('d-none');
            });
    }

    function seleccionarClienteReciboDe(cli) {
        document.getElementById('m-recibo-de-input').value    = cli.nombre;
        document.getElementById('m-input-recibo-de').value    = cli.nombre;
        document.getElementById('m-input-id-recibo-cliente').value = cli.id;
        document.getElementById('m-dropdown-recibo-de').classList.add('d-none');
    }

    // ── Modal secundario: selección de documentos pendientes ─────────────────
    let _docsModal = [];
    let _selModal  = {};

    document.addEventListener('DOMContentLoaded', () => {
        const modalDocsPend = document.getElementById('modalSelDocPendientes');
        if (modalDocsPend) {
            modalDocsPend.addEventListener('show.bs.modal', () => {
                modalDocsPend.style.zIndex = '1080';
                requestAnimationFrame(() => {
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    if (backdrops.length) {
                        backdrops[backdrops.length - 1].style.zIndex = '1072';
                    }
                });
            });
        }
    });

    function abrirModalDocsPendientes() {
        _selModal = {};
        const modalEl = document.getElementById('modalSelDocPendientes');
        document.body.appendChild(modalEl);
        modalEl.style.zIndex = '1080';
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
        document.getElementById('inp-docs-buscar').value = '';
        buscarEnModalDocsPendientes('');
    }

    function buscarEnModalDocsPendientes(q) {
        const tbody    = document.getElementById('sdp-tbody');
        const excluirId = document.getElementById('m-input-id')?.value || '';
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Buscando...</td></tr>';

        let uri = `<?= BASE_URL ?>/<?= $rutaModulo ?>/buscarDocumentosPendientesAjax?q=${encodeURIComponent(q)}`;
        if (excluirId) uri += `&excluir_ingreso_id=${excluirId}`;

        fetch(uri)
            .then(r => r.json())
            .then(res => {
                _docsModal = res.data || [];
                renderTablaDocsPendientesModal(_docsModal, res.has_more);
            })
            .catch(() => {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-danger">Error al buscar documentos.</td></tr>';
            });
    }

    function renderTablaDocsPendientesModal(docs, hasMore) {
        const tbody = document.getElementById('sdp-tbody');
        tbody.innerHTML = '';

        if (docs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted"><i class="bi bi-inbox fs-4 d-block mb-1"></i>No hay documentos pendientes.</td></tr>';
            actualizarResumenModal();
            return;
        }

        docs.forEach(doc => {
            const yaAgregado = docPendientes.some(d => d.id === doc.id);
            const montoPrev  = _selModal[doc.id] !== undefined
                ? _selModal[doc.id]
                : parseFloat(doc.saldo_pendiente);
            const checked    = _selModal[doc.id] !== undefined;
            const saldo      = parseFloat(doc.saldo_pendiente);

            const tr = document.createElement('tr');
            tr.className = yaAgregado ? 'table-success' : '';
            if (!yaAgregado) {
                tr.style.cursor = 'pointer';
                tr.addEventListener('click', function (e) {
                    if (e.target.closest('input[type="checkbox"]') || e.target.closest('input[type="number"]')) return;
                    const chk = this.querySelector('.sdp-chk');
                    if (!chk) return;
                    chk.checked = !chk.checked;
                    toggleDocModal(doc.id, chk.checked, saldo);
                });
            }
            tr.innerHTML = `
                <td class="text-center ps-2">
                    ${yaAgregado
                        ? '<i class="bi bi-check-circle-fill text-success" title="Ya agregado"></i>'
                        : `<input type="checkbox" class="form-check-input sdp-chk" data-id="${doc.id}" data-saldo="${saldo}"
                               ${checked ? 'checked' : ''}
                               onchange="toggleDocModal(${doc.id}, this.checked, ${saldo})">`
                    }
                </td>
                <td><code class="text-primary small">${doc.numero_documento}</code></td>
                <td class="small">${doc.cliente_nombre}</td>
                <td class="small">${doc.fecha_emision ? doc.fecha_emision.split('-').reverse().join('/') : ''}</td>
                <td class="small">${(() => {
                    if (!doc.fecha_emision) return '<span class="text-muted">-</span>';
                    const hoy = new Date(); hoy.setHours(0,0,0,0);
                    const fEmision = new Date(doc.fecha_emision + 'T00:00:00');
                    const diasPasados = Math.floor((hoy - fEmision) / 86400000);
                    const diasCredito = parseInt(doc.dias_credito) || 0;
                    const dentroRango = diasCredito > 0 && diasPasados <= diasCredito;
                    if (dentroRango) {
                        return `<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">${diasPasados} días</span>`;
                    } else {
                        const diasVencido = diasCredito > 0 ? diasPasados - diasCredito : diasPasados;
                        return `<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Vencido ${diasVencido} días</span>`;
                    }
                })()}</td>
                <td class="text-end small">$${parseFloat(doc.importe_total).toFixed(2)}</td>
                <td class="text-end">
                    ${yaAgregado
                        ? `<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 small">Agregado</span>`
                        : `<input type="number" class="form-control form-control-sm text-end sdp-monto px-1" data-id="${doc.id}" data-saldo="${saldo}"
                               style="height:26px;font-size:0.8rem;width:90px;"
                               step="0.01" min="0.01" max="${saldo}"
                               value="${checked ? montoPrev.toFixed(2) : saldo.toFixed(2)}"
                               ${checked ? '' : 'disabled'}
                               oninput="actualizarMontoModal(${doc.id}, this)">`
                    }
                </td>`;
            tbody.appendChild(tr);
        });

        if (hasMore) {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td colspan="7" class="text-center small text-muted py-2 bg-light border-top">
                <i class="bi bi-info-circle me-1"></i>Hay más de 300 documentos. Refine la búsqueda para ver más.
            </td>`;
            tbody.appendChild(tr);
        }

        actualizarResumenModal();
    }

    function toggleDocModal(id, checked, saldo) {
        const inputMonto = document.querySelector(`.sdp-monto[data-id="${id}"]`);
        if (checked) {
            const monto = inputMonto ? parseFloat(inputMonto.value) || saldo : saldo;
            _selModal[id] = monto;
            if (inputMonto) inputMonto.disabled = false;
        } else {
            delete _selModal[id];
            if (inputMonto) { inputMonto.disabled = true; }
        }
        actualizarResumenModal();
    }

    function actualizarMontoModal(id, input) {
        const saldo = parseFloat(input.dataset.saldo);
        let val = parseFloat(input.value);
        if (isNaN(val) || val <= 0) val = 0;
        if (val > saldo) { val = saldo; input.value = saldo.toFixed(2); }
        _selModal[id] = val;
        actualizarResumenModal();
    }

    function actualizarResumenModal() {
        const ids    = Object.keys(_selModal);
        const total  = ids.reduce((s, id) => s + (_selModal[id] || 0), 0);
        document.getElementById('sdp-lbl-sel').textContent  = ids.length;
        document.getElementById('sdp-lbl-total').textContent = `$${total.toFixed(2)}`;
    }

    function confirmarSeleccionDocsPendientes() {
        const ids = Object.keys(_selModal);
        if (ids.length === 0) {
            if (typeof showToast === 'function') showToast('Seleccione al menos un documento.', 'warning');
            return;
        }

        let agregados = 0;
        ids.forEach(id => {
            const doc = _docsModal.find(d => d.id == id);
            if (!doc) return;
            if (docPendientes.some(d => d.id == id)) return; // ya existe

            const monto = _selModal[id] || parseFloat(doc.saldo_pendiente);
            docPendientes.push({
                id:             doc.id,
                numero:         doc.numero_documento,
                fecha:          doc.fecha_emision,
                cliente_nombre: doc.cliente_nombre,
                monto_doc:      parseFloat(doc.importe_total),
                saldo_ant:      parseFloat(doc.saldo_pendiente),
                cobrado:        monto,
                seleccionado:   true
            });
            clientesCargados[doc.id_cliente] = doc.cliente_nombre;

            // Auto-rellenar "Recibo de" si está vacío
            const inputReciboDe = document.getElementById('m-recibo-de-input');
            if (!inputReciboDe.value.trim()) {
                inputReciboDe.value = doc.cliente_nombre;
                document.getElementById('m-input-recibo-de').value         = doc.cliente_nombre;
                document.getElementById('m-input-id-recibo-cliente').value = doc.id_cliente;
            }
            agregados++;
        });

        actualizarInfoClientesCargados();
        renderDetalles();
        recalcularTotales();

        const modal = bootstrap.Modal.getInstance(document.getElementById('modalSelDocPendientes'));
        if (modal) modal.hide();

        if (typeof showToast === 'function') {
            showToast(`Se agregaron ${agregados} documento(s) al ingreso.`, 'success');
        }
    }

    function agregarDocumentoPendiente(doc) {
        // Si "Recibo de" está vacío, auto-rellenar con el cliente del documento
        const inputReciboDe = document.getElementById('m-recibo-de-input');
        if (!inputReciboDe.value.trim()) {
            inputReciboDe.value = doc.cliente_nombre;
            document.getElementById('m-input-recibo-de').value       = doc.cliente_nombre;
            document.getElementById('m-input-id-recibo-cliente').value = doc.id_cliente;
        }

        // Evitar duplicados
        if (docPendientes.some(d => d.id === doc.id)) {
            if (typeof showToast === 'function') showToast(`La factura ${doc.numero_documento} ya está en la lista.`, 'info');
            return;
        }

        docPendientes.push({
            id:             doc.id,
            numero:         doc.numero_documento,
            fecha:          doc.fecha_emision,
            cliente_nombre: doc.cliente_nombre,
            monto_doc:      parseFloat(doc.importe_total),
            saldo_ant:      parseFloat(doc.saldo_pendiente),
            cobrado:        parseFloat(doc.saldo_pendiente),
            seleccionado:   true
        });

        clientesCargados[doc.id_cliente] = doc.cliente_nombre;
        actualizarInfoClientesCargados();
        renderDetalles();
        recalcularTotales();
    }

    function limpiarDocumentosPendientes() {
        docPendientes = [];
        clientesCargados = {};
        renderDetalles();
        recalcularTotales();
        actualizarInfoClientesCargados();
    }

    function actualizarInfoClientesCargados() { /* eliminado - ya no se muestra */ }

    function eliminarDocPendiente(idx) {
        docPendientes.splice(idx, 1);
        renderDetalles();
        recalcularTotales();
    }

    function renderDetalles() {
        const tipo = document.getElementById('m-input-tipo-ingreso').value;
        const esHistorico = !!document.getElementById('m-input-id').value;
        const isObsReadOnly = document.getElementById('m-input-observaciones').disabled;

        if (['FACTURA_VENTA', 'RECIBO_VENTA'].includes(tipo)) {
            const tbody = document.getElementById('m-tbody-docs-pendientes');
            tbody.innerHTML = '';

            if (docPendientes.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3 small">Use el buscador para añadir documentos pendientes de uno o más clientes.</td></tr>';
                return;
            }

            docPendientes.forEach((f, idx) => {
                const tr = document.createElement('tr');
                const disInput = (!f.seleccionado || esHistorico) ? 'disabled' : '';
                const disChk   = esHistorico ? 'disabled' : '';
                const cliLabel = f.cliente_nombre ? `<span class="badge bg-light text-dark border" style="font-size:0.7rem;">${f.cliente_nombre}</span>` : '';

                tr.innerHTML = `
                    <td class="text-center">
                        <input type="checkbox" class="form-check-input chk-sel-doc" ${f.seleccionado ? 'checked' : ''} ${disChk} onchange="toggleDoc(${idx}, this.checked)">
                    </td>
                    <td><code class="text-primary fw-bold pointer text-decoration-underline" onclick="abrirPrevisualizadorDoc(${f.id}, 'FACTURA_VENTA')">${f.numero}</code></td>
                    <td class="small">${cliLabel}</td>
                    <td class="small">${f.fecha ? f.fecha.split('-').reverse().join('/') : ''}</td>
                    <td class="text-end">$${f.monto_doc.toFixed(2)}</td>
                    <td class="text-end">
                        <input type="number" class="form-control form-control-sm input-numeric text-end px-1 input-monto-cobrar"
                               style="height:26px;" step="0.01"
                               value="${f.cobrado > 0 ? f.cobrado.toFixed(2) : ''}"
                               ${disInput}
                               oninput="actualizarMontoDoc(${idx}, this)">
                    </td>
                    <td class="text-center">
                        ${!esHistorico ? `<button type="button" class="btn btn-link p-0 text-danger" title="Eliminar" onclick="eliminarDocPendiente(${idx})"><i class="bi bi-trash3" style="font-size:0.85rem;"></i></button>` : ''}
                    </td>
                `;
                tbody.appendChild(tr);
            });
        } else {
            const tbody = document.getElementById('m-tbody-otros-manual');
            tbody.innerHTML = '';
            
            // Si está vacío y no está en lectura, inicializar línea inteligente
            if (detalleManual.length === 0 && !isObsReadOnly) {
                detalleManual.push({ descripcion: '', monto: 0 });
            }

            if (detalleManual.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center py-3 small text-muted">Sin registros asignados.</td></tr>';
            } else {
                detalleManual.forEach((d, idx) => {
                    const tr = document.createElement('tr');
                    tr.className = 'row-detalle';
                    
                    tr.innerHTML = `
                        <td class="ps-3">
                            <input type="text" class="form-control form-control-sm input-detalle" 
                                value="${d.descripcion}" 
                                placeholder="Escriba la descripción o referencia del ingreso..." 
                                ${isObsReadOnly ? 'disabled' : ''}
                                oninput="actualizarManualIngresoDesc(${idx}, this.value)">
                        </td>
                        <td class="pe-4">
                            <input type="number" class="form-control form-control-sm input-detalle text-end fw-bold" 
                                value="${d.monto > 0 ? d.monto.toFixed(2) : ''}" 
                                placeholder="0.00" 
                                step="0.01" 
                                ${isObsReadOnly ? 'disabled' : ''}
                                oninput="actualizarManualIngresoMonto(${idx}, this.value)">
                        </td>
                        <td class="text-center align-middle">
                            ${!isObsReadOnly ? `<i class="bi bi-trash text-danger pointer remove-row" title="Eliminar fila" onclick="eliminarFilaManualIngreso(${idx})"></i>` : ''}
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            }
            
            const btnAddLine = document.querySelector('#m-block-otros button.btn-link');
            if (btnAddLine) btnAddLine.style.display = isObsReadOnly ? 'none' : '';
            
            const itemsCounter = document.getElementById('m-man-count-items');
            if (itemsCounter) itemsCounter.innerText = detalleManual.length;
        }
    }

    function toggleDoc(idx, state) {
        docPendientes[idx].seleccionado = state;
        if (state) {
            // Autocomplete con saldo pendiente si está vacío
            if (!docPendientes[idx].cobrado) docPendientes[idx].cobrado = docPendientes[idx].saldo_ant;
        } else {
            docPendientes[idx].cobrado = 0;
        }
        renderDetalles();
        recalcularTotales();
    }

    function actualizarMontoDoc(idx, el) {
        let v = parseFloat(el.value) || 0;
        const max = docPendientes[idx].saldo_ant;

        if (v > max) {
            v = max;
            el.value = max.toFixed(2);
            // Pequeño toast de aviso para mejor UX
            if (typeof showToast === 'function') {
                showToast(`El monto no puede exceder el saldo ($${max.toFixed(2)})`, 'warning');
            }
        }

        docPendientes[idx].cobrado = v;
        recalcularTotales();
    }

    function agregarFilaManualIngreso() {
        detalleManual.push({ descripcion: '', monto: 0 });
        renderDetalles();
        recalcularTotales();
    }

    function eliminarFilaManualIngreso(idx) {
        detalleManual.splice(idx, 1);
        renderDetalles();
        recalcularTotales();
    }

    function actualizarManualIngresoDesc(idx, val) {
        if (detalleManual[idx]) detalleManual[idx].descripcion = val;
    }

    function actualizarManualIngresoMonto(idx, val) {
        if (detalleManual[idx]) {
            detalleManual[idx].monto = parseFloat(val) || 0;
            recalcularTotales();
        }
    }

    // Formas de cobro
    function renderPagos() {
        const tbody = document.getElementById('m-tbody-pagos');
        tbody.innerHTML = '';

        if (formasPagoData.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3 small">Agregue las formas en que se recibió el dinero.</td></tr>';
        } else {
            formasPagoData.forEach((p, idx) => {
                // Formatear referencia textual
                let textRef = p.referencia || '';
                if (p.tipo_operacion_bancaria) {
                    let prefix = p.tipo_operacion_bancaria;
                    if (p.tipo_operacion_bancaria === 'CHEQUE') {
                        prefix = `CHEQUE #${p.numero_cheque || '?'} [Fec: ${p.fecha_cobro || '?'}]`;
                    }
                    textRef = `[${prefix}] ` + textRef;
                }
                if (!textRef.trim()) textRef = '-';

                const tr = document.createElement('tr');
                const deleteBtn = esIngresoModoLectura 
                    ? '<i class="bi bi-lock text-muted"></i>' 
                    : `<button type="button" class="btn btn-link btn-sm text-danger p-0" onclick="eliminarFormaPago(${idx})" title="Quitar"><i class="bi bi-trash"></i></button>`;

                tr.innerHTML = `
                    <td><span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 me-1">${p.formaNombre}</span></td>
                    <td class="small">${textRef}</td>
                    <td class="text-end fw-bold text-success">$${p.monto.toFixed(2)}</td>
                    <td class="text-center">${deleteBtn}</td>
                `;
                tbody.appendChild(tr);
            });
        }
        recalcularSumariaCobros();
    }

    function agregarLineaCobro() {
        const combo = document.getElementById('m-add-cobro-forma');
        const formaId = combo.value;
        const selectedOpt = combo.options[combo.selectedIndex];
        const formaNombre = selectedOpt.text;
        const tipoPadre = selectedOpt.dataset.tipo || '';

        const monto = parseFloat(document.getElementById('m-add-cobro-monto').value) || 0;
        const ref = document.getElementById('m-add-cobro-ref').value;

        if (!formaId) {
            Swal.fire('Forma de Cobro', 'Seleccione una forma de cobro.', 'warning');
            return;
        }
        if (monto <= 0) {
            Swal.fire('Monto Inválido', 'El monto debe ser mayor a 0.00.', 'warning');
            return;
        }

        let tipoOpBanco = null;
        let numChq = null;
        let fecChq = null;

        if (tipoPadre === 'BANCO') {
            tipoOpBanco = document.getElementById('m-add-cobro-tipo-banco').value;
            if (tipoOpBanco === 'CHEQUE') {
                numChq = document.getElementById('m-add-cobro-num-cheque').value.trim();
                fecChq = document.getElementById('m-add-cobro-fecha-cheque').value;
                if (!numChq) {
                    Swal.fire('Cheque', 'Por favor ingrese el número de cheque.', 'warning');
                    return;
                }
                if (!fecChq) {
                    Swal.fire('Cheque', 'Por favor seleccione la fecha de cobro del cheque.', 'warning');
                    return;
                }
            }
        }

        formasPagoData.push({
            id_forma_cobro: parseInt(formaId),
            formaNombre: formaNombre,
            monto: monto,
            referencia: ref,
            tipo_operacion_bancaria: tipoOpBanco,
            numero_cheque: numChq,
            fecha_cobro: fecChq
        });

        // Reset inputs standard
        document.getElementById('m-add-cobro-monto').value = '0.00';
        document.getElementById('m-add-cobro-ref').value = '';
        // Reset bank fields
        document.getElementById('m-add-cobro-num-cheque').value = '';
        document.getElementById('m-add-cobro-fecha-cheque').value = '';

        renderPagos();
    }

    function eliminarFormaPago(idx) {
        formasPagoData.splice(idx, 1);
        renderPagos();
    }

    function recalcularTotales() {
        const tipo = document.getElementById('m-input-tipo-ingreso').value;
        let total = 0;

        if (['FACTURA_VENTA', 'RECIBO_VENTA'].includes(tipo)) {
            docPendientes.filter(f => f.seleccionado).forEach(f => {
                total += f.cobrado;
            });
        } else {
            detalleManual.forEach(d => total += d.monto);
        }

        document.getElementById('m-final-total').innerText = '$ ' + total.toFixed(2);
        document.getElementById('m-sumary-total-doc').innerText = '$ ' + total.toFixed(2);

        // Propagar al sumario para actualizar el saldo restante en el input de cobro
        recalcularSumariaCobros();
    }

    function recalcularSumariaCobros() {
        let sum = 0;
        formasPagoData.forEach(p => sum += p.monto);
        const lbl = document.getElementById('m-footer-cobro-tot');
        lbl.innerText = '$ ' + sum.toFixed(2);

        // Cambiar color si iguala o difiere
        const totalDocStr = document.getElementById('m-final-total').innerText.replace('$ ', '');
        const totalDoc = parseFloat(totalDocStr) || 0;

        if (Math.abs(sum - totalDoc) < 0.009) {
            lbl.classList.remove('text-muted', 'text-danger');
            lbl.classList.add('text-success');
        } else {
            lbl.classList.remove('text-muted', 'text-success');
            lbl.classList.add('text-danger');
        }
        
        // Sugerir siempre el saldo residual que falta por liquidar
        const diff = totalDoc - sum;
        document.getElementById('m-add-cobro-monto').value = (diff > 0.009) ? diff.toFixed(2) : '0.00';
    }

    function abrirModalIngreso() {
        // Reset form UI
        document.getElementById('modalIngresoTitulo').textContent = 'Registrar Nuevo Ingreso';
        const iconEl = document.getElementById('modalIngresoIcono');
        if (iconEl) iconEl.className = 'bi bi-wallet2 text-primary me-2';
        
        const btnGuardar = document.getElementById('btnGuardarIngreso');
        if (btnGuardar) {
            btnGuardar.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar';
            btnGuardar.classList.remove('d-none');
        }
        document.getElementById('formIngresoModal').reset();
        document.getElementById('m-input-id').value = '';
        document.getElementById('m-input-id-cliente').value = '';
        document.getElementById('m-recibo-de-input').value = '';
        document.getElementById('m-input-recibo-de').value = '';
        document.getElementById('m-input-id-recibo-cliente').value = '';

        // DESBLOQUEAR TODOS LOS CONTROLES (Limpia estado previo de anulado)
        const fieldsToUnlock = [
            'm-input-fecha', 'm-select-concepto',
            'm-recibo-de-input',
            'm-input-observaciones',
            'm-manual-desc', 'm-manual-monto',
            'm-add-cobro-forma', 'm-add-cobro-monto', 'm-add-cobro-ref',
            'm-add-cobro-tipo-banco', 'm-add-cobro-num-cheque', 'm-add-cobro-fecha-cheque'
        ];
        fieldsToUnlock.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.disabled = false;
        });

        // Habilitar todos los botones de agregar/eliminar que se ocultaron
        document.querySelectorAll('#modalNuevoIngreso button').forEach(b => b.classList.remove('d-none'));

        document.getElementById('btnGuardarIngreso').classList.remove('d-none');
        document.getElementById('m-container-footer-ver').classList.add('d-none');

        document.getElementById('m-input-fecha').value = new Date().toISOString().slice(0, 10);

        docPendientes = [];
        detalleManual = [];
        formasPagoData = [];
        clientesCargados = {};
        esIngresoModoLectura = false;
        actualizarInfoClientesCargados();

        // Aplicar preferencias/favoritos del usuario
        if (typeof window.aplicarFavoritosModal === 'function') {
            window.aplicarFavoritosModal('#modalNuevoIngreso');
        }

        const comboPunto = document.getElementById('m-select-punto');
        if (comboPunto) {
            syncIngresoSecuencial(comboPunto.value);
        }

        document.getElementById('m-select-concepto').value = '';
        sincronizarBotonesConcepto('');
        setConceptoBotonesDisabled(false);
        manejarCambioConceptoIngreso(document.getElementById('m-select-concepto'));
        renderPagos();

        // Reset Tab activa a 'General'
        const tabTrigger = document.querySelector('#tabsModalIngreso a[href="#m-tab-general"]');
        if (tabTrigger) {
            const tab = new bootstrap.Tab(tabTrigger);
            tab.show();
        }
        // Reset sub-tab activa a 'Detalles'
        const subTabTrigger = document.querySelector('a[href="#subtab-detalles"]');
        if (subTabTrigger) {
            const stab = new bootstrap.Tab(subTabTrigger);
            stab.show();
        }

        const modal = new bootstrap.Modal(document.getElementById('modalNuevoIngreso'));
        modal.show();
    }

    async function guardarIngreso() {
        const tipo   = document.getElementById('m-input-tipo-ingreso').value;
        const idConc = document.getElementById('m-select-concepto').value;
        const reciboDe = document.getElementById('m-recibo-de-input').value.trim();

        const data = {
            id:                 document.getElementById('m-input-id').value,
            fecha_emision:      document.getElementById('m-input-fecha').value,
            id_establecimiento: document.getElementById('m-id-establecimiento').value,
            establecimiento:    document.getElementById('m-txt-establecimiento').value,
            punto_emision:      document.getElementById('m-txt-punto').value,
            id_punto_emision:   document.getElementById('m-select-punto').value,
            secuencial:         document.getElementById('m-input-secuencial').value,
            tipo_ingreso:       tipo,
            observaciones:      document.getElementById('m-input-observaciones').value,
            id_cliente:         null, // se llena abajo solo cuando hay un único cliente de referencia
            id_ingreso_concepto: idConc,
            recibo_de:          reciboDe,
            id_recibo_cliente:  document.getElementById('m-input-id-recibo-cliente').value || null,
            detalles:           [],
            pagos:              formasPagoData
        };

        if (!reciboDe) {
            Swal.fire('Campo Obligatorio', 'El campo "Recibo de" es obligatorio.', 'warning');
            return;
        }

        if (!data.secuencial) {
            Swal.fire('Campo Obligatorio', 'Falta secuencial.', 'warning');
            return;
        }

        if (!data.id_ingreso_concepto) {
            Swal.fire('Concepto Requerido', 'Seleccione un concepto.', 'warning');
            return;
        }

        // Armar Detalles
        if (['FACTURA_VENTA', 'RECIBO_VENTA'].includes(tipo)) {
            const sel = docPendientes.filter(f => f.seleccionado && f.cobrado > 0);
            if (sel.length === 0) {
                Swal.fire('Sin Selección', 'Debe seleccionar al menos una factura y definir monto.', 'warning');
                return;
            }
            sel.forEach(s => {
                data.detalles.push({
                    tipo_documento:          'FACTURA',
                    id_referencia_documento: s.id,
                    numero_documento:        s.numero,
                    fecha_documento:         s.fecha || '',
                    monto_documento:         s.monto_doc,
                    saldo_anterior:          s.saldo_ant,
                    monto_cobrado:           s.cobrado,
                    saldo_actual:            s.saldo_ant - s.cobrado
                });
            });
        } else {
            // Filtrar conceptos en blanco
            const finalDets = detalleManual.filter(d => d.descripcion.trim() !== '' || d.monto > 0);
            if (finalDets.length === 0) {
                Swal.fire('Detalles Vacíos', 'Debe registrar al menos un concepto y monto válido.', 'warning');
                return;
            }

            // Validar montos positivos
            if (finalDets.some(d => d.monto <= 0)) {
                Swal.fire('Atención', 'Todos los montos en la cuadrícula deben ser superiores a cero.', 'warning');
                return;
            }

            finalDets.forEach(d => {
                data.detalles.push({
                    tipo_documento: 'OTRO',
                    descripcion: d.descripcion,
                    monto_documento: d.monto,
                    saldo_anterior: d.monto,
                    monto_cobrado: d.monto,
                    saldo_actual: 0
                });
            });
        }

        // Sum total
        data.monto_total = data.detalles.reduce((a, b) => a + b.monto_cobrado, 0);

        // Valida pagos
        const sumPagos = formasPagoData.reduce((a, b) => a + b.monto, 0);
        if (Math.abs(sumPagos - data.monto_total) > 0.01) {
            Swal.fire('Descuadre', `La suma de cobros ($${sumPagos.toFixed(2)}) no coincide con el total ($${data.monto_total.toFixed(2)}).`, 'error');
            return;
        }

        // ── Validar fecha de emisión vs fechas de documentos ──────────────────
        if (['FACTURA_VENTA', 'RECIBO_VENTA'].includes(tipo)) {
            for (const d of data.detalles) {
                if (d.fecha_documento && data.fecha_emision < d.fecha_documento) {
                    const fEmision  = data.fecha_emision.split('-').reverse().join('/');
                    const fDoc      = d.fecha_documento.split('-').reverse().join('/');
                    Swal.fire({
                        icon: 'warning',
                        title: 'Fecha inválida',
                        html: `La fecha de emisión <strong>${fEmision}</strong> no puede ser anterior a la fecha del documento <strong>${d.numero_documento}</strong> (${fDoc}).`
                    });
                    return;
                }
            }
        }

        // ── Validar periodo contable ───────────────────────────────────────────
        try {
            const periodoRes = await fetch(`<?= BASE_URL ?>/<?= $rutaModulo ?>/verificarPeriodoAjax?fecha=${encodeURIComponent(data.fecha_emision)}`);
            const periodoJson = await periodoRes.json();
            if (!periodoJson.ok) {
                Swal.fire({ icon: 'error', title: 'Periodo Contable Cerrado', text: periodoJson.mensaje });
                return;
            }
        } catch (e) {
            Swal.fire('Error', 'No se pudo verificar el periodo contable. Intente nuevamente.', 'error');
            return;
        }

        const btn = document.getElementById('btnGuardarIngreso');
        const oldHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Procesando...';

        fetch(`<?= BASE_URL ?>/<?= $rutaModulo ?>/guardarAjax`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'data=' + encodeURIComponent(JSON.stringify(data))
            })
            .then(r => r.json())
            .then(res => {
                btn.disabled = false;
                btn.innerHTML = oldHtml;
                if (res.ok) {
                    // Bootstrap Modal hide workaround
                    const modalEl = document.getElementById('modalNuevoIngreso');
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();

                    // Refrescar tabla
                    window.ING_fetchSearch(1);

                    // Alerta visual atractiva
                    Swal.fire('Éxito', res.mensaje, 'success');
                } else {
                    Swal.fire('Error', res.mensaje, 'error');
                }
            })
            .catch(e => {
                btn.disabled = false;
                btn.innerHTML = oldHtml;
                Swal.fire('Error de Red', e.message, 'error');
            });
    }

    function abrirModalIngresoVer(id) {
        fetch(`<?= BASE_URL ?>/<?= $rutaModulo ?>/getIngresoAjax?id=${id}`)
            .then(r => r.json())
            .then(res => {
                if (!res.ok) {
                    Swal.fire('Error', res.mensaje, 'error');
                    return;
                }
                const ing = res.data;
                const esAnulado = (ing.estado === 'anulado');
                esIngresoModoLectura = esAnulado; // Propagar estado globalmente

                abrirModalIngreso(); // Resets all, variables and resets fields to enabled
                
                // Cambiar estética según estado
                document.getElementById('modalIngresoTitulo').textContent = esAnulado ? `Ver Ingreso #${ing.numero_ingreso}` : `Editar Ingreso #${ing.numero_ingreso}`;
                const iconEl = document.getElementById('modalIngresoIcono');
                if (iconEl) iconEl.className = esAnulado ? 'bi bi-eye text-primary me-2' : 'bi bi-pencil-square text-primary me-2';
                
                document.getElementById('m-input-id').value = ing.id;
                document.getElementById('m-input-fecha').value = ing.fecha_emision;
                // Mostrar la serie (punto) original del documento, sin disparar el cálculo del siguiente secuencial
                if (ing.id_punto_emision) {
                    document.getElementById('m-select-punto').value = ing.id_punto_emision;
                }
                document.getElementById('m-input-secuencial').value = String(ing.secuencial ?? '').padStart(9, '0');

                // Poblar "Recibo de"
                const reciboDe = ing.recibo_de || ing.cliente_nombre || '';
                document.getElementById('m-recibo-de-input').value       = reciboDe;
                document.getElementById('m-input-recibo-de').value       = reciboDe;
                document.getElementById('m-input-id-recibo-cliente').value = ing.id_recibo_cliente || '';

                const comp = ing.tipo_ingreso || 'GENERAL';
                document.getElementById('m-input-tipo-ingreso').value = comp;
                document.getElementById('m-select-concepto').value = ing.id_ingreso_concepto;
                sincronizarBotonesConcepto(ing.id_ingreso_concepto);
                document.getElementById('m-input-observaciones').value = ing.observaciones || '';

                const isModuloLinked = ['FACTURA_VENTA', 'RECIBO_VENTA'].includes(comp);

                if (esAnulado) {
                    // Modo estrictamente solo lectura si ya fue anulado
                    document.getElementById('m-select-concepto').disabled = true;
                    setConceptoBotonesDisabled(true);
                    document.getElementById('m-recibo-de-input').disabled = true;
                    document.getElementById('m-input-observaciones').disabled = true;
                    document.getElementById('m-input-fecha').disabled = true;
                    document.getElementById('m-select-punto').disabled = true;

                    document.getElementById('btnGuardarIngreso').classList.add('d-none');
                    document.getElementById('m-container-footer-ver').classList.add('d-none');
                    document.getElementById('modalIngresoTitulo').innerHTML += ' <span class="badge bg-danger ms-2">ANULADO</span>';
                } else {
                    // NO ESTÁ ANULADO: Habilitar botón "Actualizar"
                    const btnGuardar = document.getElementById('btnGuardarIngreso');
                    btnGuardar.classList.remove('d-none');
                    btnGuardar.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Actualizar';
                    document.getElementById('m-container-footer-ver').classList.remove('d-none');
                    
                    // Siempre bloqueados en modo edición histórica
                    document.getElementById('m-select-punto').disabled = true;
                    document.getElementById('m-select-concepto').disabled = true;
                    setConceptoBotonesDisabled(true);
                    document.getElementById('m-input-secuencial').disabled = true;
                    
                    // Siempre permitidos en modo edición no anulado
                    document.getElementById('m-input-fecha').disabled = false;

                    if (isModuloLinked) {
                        // REGLA DE NEGOCIO: Si está relacionado a un módulo, solo se puede editar fecha, recibo_de y pagos.
                        document.getElementById('m-input-observaciones').disabled = true;
                        document.getElementById('m-recibo-de-input').disabled = false;
                    } else {
                        // REGLA DE NEGOCIO: Si es general (sin módulo), SÍ permite corregir todo.
                        document.getElementById('m-input-observaciones').disabled = false;
                        document.getElementById('m-recibo-de-input').disabled = false;
                    }
                }

                // Hidratar Pagos para render dinámico
                formasPagoData = (ing.pagos || []).map(p => ({
                    id_forma_cobro: p.id_forma_cobro,
                    formaNombre: p.forma_cobro_nombre,
                    monto: parseFloat(p.monto),
                    referencia: p.referencia,
                    tipo_operacion_bancaria: p.tipo_operacion_bancaria,
                    numero_cheque: p.numero_cheque,
                    fecha_cobro: p.fecha_cobro
                }));

                if (['FACTURA_VENTA', 'RECIBO_VENTA'].includes(comp)) {
                    document.getElementById('m-input-id-cliente').value = ing.id_cliente || '';
                    document.getElementById('m-block-facturas').classList.remove('d-none');
                    document.getElementById('m-block-otros').classList.add('d-none');

                    // Reconstruir docPendientes directamente desde los detalles guardados (multi-cliente)
                    docPendientes = [];
                    (ing.detalles || []).forEach(d => {
                        docPendientes.push({
                            id:             d.id_referencia_documento,
                            numero:         d.numero_documento,
                            fecha:          d.fecha_documento || '',
                            cliente_nombre: d.cliente_nombre || '',
                            monto_doc:      parseFloat(d.monto_documento || 0),
                            saldo_ant:      parseFloat(d.saldo_anterior || 0),
                            cobrado:        parseFloat(d.monto_cobrado || 0),
                            seleccionado:   true
                        });
                        if (d.id_cliente) clientesCargados[d.id_cliente] = d.cliente_nombre || '';
                    });
                    actualizarInfoClientesCargados();
                    renderDetalles();
                    renderPagos();
                    recalcularTotales();

                    // Si está anulado, forzar desactivación de inputs tras renderizado dinámico
                    if (esAnulado) {
                        document.querySelectorAll('.chk-sel-doc, .input-monto-cobrar').forEach(el => el.disabled = true);
                    }

                } else {
                    // Modo GENERAL Conceptos (Ya el select de concepto se hidrató al inicio del fetch)
                    document.getElementById('m-block-facturas').classList.add('d-none');
                    document.getElementById('m-block-otros').classList.remove('d-none');
                    
                    detalleManual = (ing.detalles || []).map(d => ({
                        descripcion: d.descripcion || 'Detalle',
                        monto: parseFloat(d.monto_cobrado)
                    }));
                    
                    renderDetalles();
                    renderPagos();
                    recalcularTotales();
                }
                
                // Bloquear adición de cobros si está anulado
                if (esAnulado) {
                    document.getElementById('m-add-cobro-forma').disabled = true;
                    document.getElementById('m-add-cobro-monto').disabled = true;
                    document.getElementById('m-add-cobro-ref').disabled = true;
                    document.querySelectorAll('#subtab-cobros button').forEach(b => b.classList.add('d-none'));
                }
            });
    }

    function anularIngreso() {
        const id = document.getElementById('m-input-id').value;
        if (!id) return;

        Swal.fire({
            title: '¿Está seguro?',
            text: '¿Confirma que desea ANULAR este ingreso? Esta acción revertirá los cobros aplicados.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, anular',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(`<?= BASE_URL ?>/<?= $rutaModulo ?>/anularAjax`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'id=' + id
                    })
                    .then(r => r.json())
                    .then(res => {
                        if (res.ok) {
                            Swal.fire('Anulado', res.mensaje, 'success').then(() => location.reload());
                        } else {
                            Swal.fire('Error', res.mensaje, 'error');
                        }
                    })
                    .catch(err => Swal.fire('Error', err.message, 'error'));
            }
        });
    }

    // Global para uso en el header/pagination
    // Global para uso en el header/pagination
    window.currentSort = '<?= $ordenCol ?>';
    window.currentDir = '<?= $ordenDir ?>';

    window.ING_cambiarPaginaAjax = function(pag) {
        window.ING_fetchSearch(pag);
    }

    window.ING_fetchSearch = async function(page = 1) {
        const b = document.getElementById('buscarIngreso').value.trim();
        const loader = '<tr><td colspan="7" class="text-center py-5"><span class="spinner-border text-primary"></span></td></tr>';
        document.getElementById('tbodyIngresos').innerHTML = loader;

        const uri = `<?= BASE_URL ?>/<?= $rutaModulo ?>/searchAjax?b=${encodeURIComponent(b)}&page=${page}&sort=${window.currentSort}&dir=${window.currentDir}`;
        try {
            const resp = await fetch(uri);
            const data = await resp.json();
            document.getElementById('tbodyIngresos').innerHTML = data.rows;
            document.getElementById('paginationContainer').innerHTML = data.pagination;
            document.getElementById('paginationInfo').innerText = data.info;

            // Actualizar enlaces de exportación
            const btnPdf = document.getElementById('btnExportPdf');
            const btnXls = document.getElementById('btnExportExcel');
            if (btnPdf) btnPdf.href = `<?= BASE_URL ?>/<?= $rutaModulo ?>/export-pdf?b=${encodeURIComponent(b)}&sort=${window.currentSort}&dir=${window.currentDir}`;
            if (btnXls) btnXls.href = `<?= BASE_URL ?>/<?= $rutaModulo ?>/export-excel?b=${encodeURIComponent(b)}&sort=${window.currentSort}&dir=${window.currentDir}`;

            // Actualizar iconos en headers
            document.querySelectorAll('.sortable-header').forEach(th => {
                const icon = th.querySelector('i');
                const field = th.dataset.sort;
                if (icon) {
                    if (field === window.currentSort) {
                        icon.className = (window.currentDir.toLowerCase() === 'asc') ?
                            'bi bi-sort-alpha-down text-primary ms-1' :
                            'bi bi-sort-alpha-up text-primary ms-1';
                    } else {
                        icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
                    }
                }
            });
        } catch (e) {
            console.error(e);
        }
    }

    // Listener para Ordenamiento y Buscador con Debounce
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.sortable-header').forEach(h => {
            h.addEventListener('click', () => {
                const f = h.dataset.sort;
                if (window.currentSort === f) {
                    window.currentDir = (window.currentDir.toLowerCase() === 'asc') ? 'DESC' : 'ASC';
                } else {
                    window.currentSort = f;
                    window.currentDir = 'ASC';
                }
                // Opcional: Guardar preferencias si helper existe
                if (typeof window.guardarOrdenacionVista === 'function') {
                    window.guardarOrdenacionVista('ingresos', window.currentSort, window.currentDir);
                }
                window.ING_fetchSearch(1);
            });
        });

        const inputB = document.getElementById('buscarIngreso');
        let tSearch;
        if (inputB) {
            inputB.addEventListener('input', () => {
                clearTimeout(tSearch);
                tSearch = setTimeout(() => window.ING_fetchSearch(1), 400);
            });
        }

        // Comportamiento dinámico Formas de Cobro
        const comboFormaCobro = document.getElementById('m-add-cobro-forma');
        const divBancoExtra = document.getElementById('wrapper-banco-extra');
        const comboTipoBanco = document.getElementById('m-add-cobro-tipo-banco');
        const divChequeFields = document.querySelectorAll('.div-cheque-fields');

        if (comboFormaCobro) {
            comboFormaCobro.addEventListener('change', function() {
                const opt = this.options[this.selectedIndex];
                const tipo = opt ? opt.dataset.tipo : '';
                if (tipo === 'BANCO') {
                    divBancoExtra.classList.remove('d-none');
                } else {
                    divBancoExtra.classList.add('d-none');
                }
            });
        }

        if (comboTipoBanco) {
            comboTipoBanco.addEventListener('change', function() {
                if (this.value === 'CHEQUE') {
                    divChequeFields.forEach(el => el.classList.remove('d-none'));
                } else {
                    divChequeFields.forEach(el => el.classList.add('d-none'));
                }
            });
        }
    });

    function abrirPrevisualizadorDoc(id, tipo) {
        const offcanvasEl = document.getElementById('offcanvasDocPreview');
        
        // Mover al Body dinámicamente si no está en la raíz, para saltar el apilamiento de modales
        if (offcanvasEl && offcanvasEl.parentNode !== document.body) {
            document.body.appendChild(offcanvasEl);
        }

        const bsOffcanvas = bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl);
        
        const loading = document.getElementById('preview-doc-loading');
        const content = document.getElementById('preview-doc-content');
        const itemsContainer = document.getElementById('p-container-items');
        
        loading.classList.remove('d-none');
        content.classList.add('d-none');
        
        bsOffcanvas.show();
        
        let url = '';
        if (tipo === 'FACTURA_VENTA' || tipo === 'VENTA' || tipo === 'RECIBO_VENTA') {
            url = `<?= BASE_URL ?>/modulos/factura-venta/getFacturaAjax?id=${id}`;
        } else if (tipo === 'COMPRA') {
            url = `<?= BASE_URL ?>/modulos/compras/getCompraAjax?id=${id}`;
        } else if (tipo === 'LIQUIDACION') {
            url = `<?= BASE_URL ?>/modulos/liquidacion-compra/getLiquidacionAjax?id=${id}`;
        }
        
        fetch(url)
            .then(r => r.json())
            .then(res => {
                if (!res.ok) {
                    alert(res.mensaje || 'Error al obtener detalle');
                    bsOffcanvas.hide();
                    return;
                }
                
                loading.classList.add('d-none');
                content.classList.remove('d-none');
                
                let cab = null;
                let dets = [];
                let lblSujeto = '';
                let valSujeto = '';
                let badgeTipo = '';
                
                if (tipo === 'FACTURA_VENTA' || tipo === 'VENTA' || tipo === 'RECIBO_VENTA') {
                    cab = res.cabecera;
                    dets = res.detalles || [];
                    lblSujeto = 'Cliente';
                    valSujeto = cab.cliente_nombre || 'CONSUMIDOR FINAL';
                    badgeTipo = 'FACTURA DE VENTA';
                } else if (tipo === 'COMPRA') {
                    cab = res.data;
                    dets = cab.detalles || [];
                    lblSujeto = 'Proveedor';
                    valSujeto = cab.proveedor_nombre || '';
                    badgeTipo = 'FACTURA DE COMPRA';
                } else if (tipo === 'LIQUIDACION') {
                    cab = res.cabecera;
                    dets = res.detalles || [];
                    lblSujeto = 'Proveedor';
                    valSujeto = cab.proveedor_nombre || '';
                    badgeTipo = 'LIQUIDACIÓN';
                }
                
                // Asignar Encabezado
                const num = (cab.establecimiento || cab.establecimiento_prov || '') + '-' +
                            (cab.punto_emision || cab.punto_emision_prov || '') + '-' +
                            (cab.secuencial || cab.secuencial_prov || '');
                
                document.getElementById('p-txt-numero').innerText = num;
                document.getElementById('p-txt-fecha').innerText = cab.fecha_emision ? cab.fecha_emision.split('-').reverse().join('/') : '';
                document.getElementById('p-lbl-sujeto').innerText = lblSujeto;
                document.getElementById('p-txt-sujeto').innerText = valSujeto;
                document.getElementById('p-badge-tipo').innerText = badgeTipo;
                
                // Asignar Totales
                const subtotal = parseFloat(cab.total_sin_impuestos || 0);
                const total = parseFloat(cab.importe_total || 0);
                const iva = parseFloat(cab.monto_iva || 0) || (total - subtotal); // Fallback simple
                
                document.getElementById('p-txt-subtotal').innerText = `$${subtotal.toFixed(2)}`;
                document.getElementById('p-txt-iva').innerText = `$${iva.toFixed(2)}`;
                document.getElementById('p-txt-total').innerText = `$${total.toFixed(2)}`;
                
                // Renderizar Ítems
                itemsContainer.innerHTML = '';
                if (dets.length === 0) {
                    itemsContainer.innerHTML = '<div class="text-center py-3 text-muted small">Sin ítems registrados.</div>';
                } else {
                    dets.forEach(d => {
                        const cant = parseFloat(d.cantidad || 0);
                        const pUni = parseFloat(d.precio_unitario || d.costo_unitario || 0);
                        const dTotal = parseFloat(d.precio_total_sin_impuesto || d.subtotal || (cant * pUni));
                        const desc = d.descripcion || d.producto_nombre || 'Ítem';
                        const cod = d.codigo_principal || d.codigo || '';
                        
                        const card = document.createElement('div');
                        card.className = 'bg-white border rounded-3 p-2 shadow-sm';
                        card.innerHTML = `
                            <div class="d-flex justify-content-between align-items-start">
                                <div style="max-width:70%">
                                    <small class="d-block text-muted" style="font-size: 0.7rem;">${cod ? `Cod: ${cod}` : ''}</small>
                                    <strong class="d-block text-dark small text-truncate" title="${desc}">${desc}</strong>
                                </div>
                                <div class="text-end">
                                    <span class="fw-bold text-secondary small">$${dTotal.toFixed(2)}</span>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-1 pt-1 border-top border-light" style="font-size: 0.75rem;">
                                <span class="text-muted">Cant: <strong>${cant}</strong></span>
                                <span class="text-muted">P.U: <strong>$${pUni.toFixed(4)}</strong></span>
                            </div>
                        `;
                        itemsContainer.appendChild(card);
                    });
                }
            })
            .catch(e => {
                console.error(e);
                alert('No se pudo conectar con el servidor');
                bsOffcanvas.hide();
            });
    }
</script>

<style>
    #offcanvasDocPreview {
        z-index: 6000 !important;
    }
    .offcanvas-backdrop {
        z-index: 5990 !important;
    }
</style>

<!-- ══════════════════════════════════════════════════════════════════
     Modal Secundario: Seleccionar Documentos Pendientes
     ══════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalSelDocPendientes" tabindex="-1" aria-labelledby="modalSelDocPendientesLabel">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold" id="modalSelDocPendientesLabel">
                    <i class="bi bi-receipt-cutoff text-primary me-2"></i> Seleccionar Documentos Pendientes de Cobro
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body p-0 d-flex flex-column" style="min-height: 400px;">
                <!-- Barra de búsqueda -->
                <div class="px-3 pt-3 pb-2 border-bottom bg-light bg-opacity-50">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" id="inp-docs-buscar" class="form-control"
                               placeholder="Buscar por Nº factura, nombre de cliente o RUC..."
                               oninput="clearTimeout(window._timerDocsBuscar); window._timerDocsBuscar = setTimeout(() => buscarEnModalDocsPendientes(this.value.trim()), 350)">
                        <button type="button" class="btn btn-outline-secondary" onclick="buscarEnModalDocsPendientes(document.getElementById('inp-docs-buscar').value.trim())">
                            <i class="bi bi-search"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('inp-docs-buscar').value=''; buscarEnModalDocsPendientes('')" title="Limpiar">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                <!-- Tabla -->
                <div class="flex-grow-1 overflow-auto px-0">
                    <table class="table table-sm table-hover mb-0" style="font-size:0.82rem;">
                        <thead class="table-light sticky-top" style="top:0; z-index:1;">
                            <tr>
                                <th class="text-center ps-2" style="width:42px;"></th>
                                <th style="min-width:130px;">Nº Documento</th>
                                <th style="min-width:150px;">Cliente</th>
                                <th style="min-width:90px;">Fecha</th>
                                <th style="min-width:90px;">Crédito</th>
                                <th class="text-end" style="min-width:90px;">Total Doc.</th>
                                <th class="text-end" style="min-width:100px;">Monto a cobrar</th>
                            </tr>
                        </thead>
                        <tbody id="sdp-tbody">
                            <tr><td colspan="7" class="text-center py-4 text-muted">
                                <span class="spinner-border spinner-border-sm me-2"></span>Cargando documentos...
                            </td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="modal-footer justify-content-between bg-light border-top p-2 align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3 small text-muted">
                    <span><i class="bi bi-check2-square me-1 text-primary"></i>
                        Seleccionados: <strong id="sdp-lbl-sel" class="text-dark">0</strong>
                    </span>
                    <span><i class="bi bi-cash-coin me-1 text-success"></i>
                        Total: <strong id="sdp-lbl-total" class="text-dark">$0.00</strong>
                    </span>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="confirmarSeleccionDocsPendientes()">
                        <i class="bi bi-plus-circle me-1"></i> Agregar seleccionados
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Offcanvas para Previsualizar Documento -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasDocPreview" aria-labelledby="offcanvasDocPreviewLabel" style="width: 420px;">
    <div class="offcanvas-header bg-light border-bottom py-2 px-3">
        <h6 class="offcanvas-title fw-bold text-primary mb-0 d-flex align-items-center" id="offcanvasDocPreviewLabel">
            <i class="bi bi-file-earmark-text me-2 fs-5"></i> Detalle del Documento
        </h6>
        <button type="button" class="btn-close btn-close-sm text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0 d-flex flex-column bg-light bg-opacity-25" style="overflow:hidden;">
        <div id="preview-doc-loading" class="text-center py-5 d-none flex-grow-1 d-flex flex-column justify-content-center">
            <div class="spinner-border spinner-border-sm text-primary mx-auto mb-2" role="status"></div>
            <div class="small text-muted">Cargando desglose...</div>
        </div>
        <div id="preview-doc-content" class="d-flex flex-column h-100 w-100">
            <!-- Encabezado Rápido -->
            <div class="bg-white p-3 border-bottom shadow-sm">
                <div class="d-flex justify-content-between align-items-start mb-1">
                    <div>
                        <span id="p-badge-tipo" class="badge bg-primary bg-opacity-10 text-primary border mb-1" style="font-size:0.65rem;">FACTURA</span>
                        <h6 id="p-txt-numero" class="fw-bold mb-0 text-dark" style="font-family:monospace; font-size: 0.95rem;">000-000-000000000</h6>
                    </div>
                    <div class="text-end">
                        <span class="small text-muted d-block" style="font-size:0.7rem;">Fecha</span>
                        <strong id="p-txt-fecha" class="small text-dark">--/--/----</strong>
                    </div>
                </div>
                <div class="mt-2 pt-2 border-top">
                    <span class="small text-muted d-block mb-0" id="p-lbl-sujeto" style="font-size:0.7rem;">Cliente / Proveedor</span>
                    <span id="p-txt-sujeto" class="small fw-medium text-dark">CONSUMIDOR FINAL</span>
                </div>
            </div>

            <!-- Listado de Ítems -->
            <div class="flex-grow-1 overflow-auto p-3" style="max-height: calc(100vh - 260px);">
                <h6 class="small fw-bold text-muted mb-2" style="font-size:0.75rem; letter-spacing: 0.5px;">ÍTEMS DETALLADOS</h6>
                <div id="p-container-items" class="d-flex flex-column gap-2">
                    <!-- Tarjetas de ítems inyectadas aquí -->
                </div>
            </div>

            <!-- Totales Finales -->
            <div class="bg-white p-3 border-top shadow-sm mt-auto">
                <div class="d-flex justify-content-between mb-1">
                    <span class="small text-muted" style="font-size:0.75rem;">Subtotal</span>
                    <span id="p-txt-subtotal" class="small fw-medium text-dark">$0.00</span>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="small text-muted" style="font-size:0.75rem;">IVA</span>
                    <span id="p-txt-iva" class="small fw-medium text-dark">$0.00</span>
                </div>
                <div class="d-flex justify-content-between pt-2 border-top border-2">
                    <span class="fw-bold text-dark" style="font-size:0.85rem;">Total Documento</span>
                    <span id="p-txt-total" class="fw-bold text-primary fs-6">$0.00</span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// ── Modales compartidos incluidos desde Ingresos ─────────────────────────────

// 1. Forma de Pago
include_once MVC_APP . '/views/modulos/formas_cobros_pagos/modal_forma_pago.php';

// 2. Opción de Ingreso / Egreso
$urlBase    = BASE_URL . '/modulos/opciones_ingreso_egreso';
$permOIE    = $perm; // guardar permisos actuales
$perm       = ['ver' => true, 'crear' => true, 'actualizar' => true, 'eliminar' => true, 'todo' => true];
include_once MVC_APP . '/views/modulos/opciones_ingreso_egreso/modal_opcion.php';
$perm = $permOIE;

// 3. Clientes
$urlBaseClientes  = BASE_URL . '/modulos/clientes';
include_once MVC_APP . '/views/modulos/clientes/modal_cliente.php';
?>

<script>
// ── Conectar botones de la barra superior del modal de Ingresos ──────────────

// Botón: Crear Forma de Pago
window.modalCrearFormaPago = function () {
    abrirModalFP();
};

// Callback tras guardar forma de pago: agrega la nueva al select de formas del modal
window.onFormaPagoCreada = function (id, nombre) {
    Swal.fire({
        icon: 'success',
        title: 'Forma de pago creada',
        text: `"${nombre}" fue registrada. Ya puedes seleccionarla en la lista de cobros.`,
        timer: 2500,
        showConfirmButton: false
    });
    if (typeof renderPagos === 'function') renderPagos();
};

// Botón: Crear Opción de Ingreso (preselecciona "Ingreso" antes de abrir)
window.modalCrearOpcionIngreso = function () {
    const rdoIng = document.getElementById('oie-rdo-ingreso');
    if (rdoIng) rdoIng.checked = true;
    abrirModalOpcion();
};

// Callback tras guardar opción: agrega el nuevo botón de concepto dinámicamente
window.onOpcionCreada = function (id, nombre, comportamiento) {
    const grupo = document.getElementById('concepto-btns-group');
    const sel   = document.getElementById('m-select-concepto');

    if (grupo && sel) {
        // Agregar al select oculto
        const opt = document.createElement('option');
        opt.value = id;
        opt.dataset.comportamiento = comportamiento || 'GENERAL';
        opt.textContent = nombre;
        sel.appendChild(opt);

        // Agregar botón visible
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm btn-outline-secondary concepto-ingreso-btn';
        btn.dataset.id = id;
        btn.dataset.comportamiento = comportamiento || 'GENERAL';
        btn.textContent = nombre;
        btn.addEventListener('click', function () {
            if (sel.value == this.dataset.id) return;
            const hayDatos = docPendientes.length > 0
                          || detalleManual.some(d => d.descripcion.trim() !== '' || parseFloat(d.monto) > 0);
            const aplicar = () => { sel.value = this.dataset.id; manejarCambioConceptoIngreso(sel); };
            if (hayDatos) {
                Swal.fire({
                    title: '¿Cambiar concepto?',
                    text: 'Al cambiar el concepto se eliminará toda la información de detalle que has agregado. ¿Deseas continuar?',
                    icon: 'warning', showCancelButton: true,
                    confirmButtonColor: '#d33', cancelButtonColor: '#6c757d',
                    confirmButtonText: '<i class="bi bi-trash3 me-1"></i> Sí, cambiar',
                    cancelButtonText: 'Cancelar'
                }).then(r => { if (r.isConfirmed) aplicar(); });
            } else {
                aplicar();
            }
        });
        grupo.appendChild(btn);
    }

    Swal.fire({
        icon: 'success',
        title: 'Opción creada',
        text: `"${nombre}" fue registrada y ya aparece como opción de concepto.`,
        timer: 2500,
        showConfirmButton: false
    });
};

// window.abrirModalClienteCrear ya lo expone clientes/modal.php automáticamente
</script>