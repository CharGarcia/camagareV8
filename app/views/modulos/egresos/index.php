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
/** @var array $formasPago */
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

<style>
    .egr-header {
        flex-shrink: 0;
    }
    .egreso-scroll {
        max-height: calc(100dvh - 240px);
        overflow-y: auto;
    }
    .egreso-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }
    .egreso-row {
        cursor: pointer;
    }
    .egreso-row:hover {
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

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>

<div class="egr-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-cash-stack me-1 text-primary"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="abrirModalEgreso()">
            <i class="bi bi-plus-lg me-1"></i> Nuevo
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_busqueda.css?v=<?= time() ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_busqueda.js?v=<?= time() ?>"></script>
            <div id="fbBuscadorEGR" style="width: 480px;"></div>
            <input type="hidden" id="buscarEgreso" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosBusqueda) return;
                    new FiltrosBusqueda({
                        containerId: 'fbBuscadorEGR',
                        hiddenInputId: 'buscarEgreso',
                        fields: [
                            { key: 'proveedor', label: 'Proveedor',  icon: 'bi-building',        type: 'text' },
                            { key: 'empleado',  label: 'Empleado',   icon: 'bi-person-badge',    type: 'text' },
                            { key: 'numero',    label: 'Nº egreso',  icon: 'bi-hash',            type: 'text' },
                            { key: 'concepto',  label: 'Concepto',   icon: 'bi-chat-left-text',  type: 'text' },
                            { key: 'tipo',      label: 'Tipo egreso', icon: 'bi-tag',            type: 'text' },
                            { key: 'fecha',     label: 'Fecha',      icon: 'bi-calendar-event',  type: 'date_range' },
                            { key: 'monto',     label: 'Monto',      icon: 'bi-currency-dollar', type: 'number_range' },
                            { key: 'estado',    label: 'Estado',     icon: 'bi-flag',            type: 'select', options: [
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
                        onApply: () => window.EGR_fetchSearch && window.EGR_fetchSearch(1),
                    }).init();
                });
            </script>

            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'numero_egreso' => 'Nº Egreso',
                    'fecha_emision'  => 'Fecha',
                    'tipo_egreso'   => 'Tipo',
                    'sujeto_nombre' => 'Beneficiario',
                    'observaciones'  => 'Observaciones',
                    'monto_total'    => 'Monto',
                    'estado'         => 'Estado',
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>

                <a id="btnExportPdf" href="<?= $urlBase ?>/export-pdf?b=<?= urlencode($buscar) ?>" class="btn btn-outline-danger" title="Descargar PDF">
                    <i class="bi bi-file-earmark-pdf"></i>
                </a>
                <a id="btnExportExcel" href="<?= $urlBase ?>/export-excel?b=<?= urlencode($buscar) ?>" class="btn btn-outline-success" title="Descargar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i>
                </a>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="paginationContainer" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" <?= $page <= 1 ? 'disabled' : '' ?> onclick="window.EGR_cambiarPaginaAjax(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" <?= $page >= $totalPages ? 'disabled' : '' ?> onclick="window.EGR_cambiarPaginaAjax(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="egreso-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="numero_egreso" data-col="numero_egreso">
                            Nº Egreso <i class="bi <?= $ordenCol === 'numero_egreso' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="sortable-header" role="button" data-sort="fecha_emision" data-col="fecha_emision">
                            Fecha <i class="bi <?= $ordenCol === 'fecha_emision' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="sortable-header" role="button" data-sort="tipo_egreso" data-col="tipo_egreso">
                            Tipo <i class="bi <?= $ordenCol === 'tipo_egreso' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="sortable-header" role="button" data-sort="sujeto_nombre" data-col="sujeto_nombre">
                            Beneficiario <i class="bi <?= $ordenCol === 'sujeto_nombre' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
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
                <tbody id="tbodyEgresos">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-cash-stack fs-3 d-block mb-2"></i>No se encontraron egresos.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $tipoLabels = [
                                'COMPRA' => 'Registro de Compra',
                                'LIQUIDACION' => 'Liquidación',
                                'ROL' => 'Rol de Pago',
                                'QUINCENA' => 'Quincena',
                                'PRESTAMO' => 'Préstamo',
                                'OTRO' => 'Otro Concepto'
                            ];
                            $tipoLabel = $tipoLabels[$r['tipo_egreso']] ?? $r['tipo_egreso'];
                            $estado  = $r['estado'] ?? 'registrado';
                            $estadoClass = match ($estado) {
                                'anulado' => 'bg-danger bg-opacity-10 text-danger border-danger',
                                default   => 'bg-primary bg-opacity-10 text-primary border-primary',
                            };
                            ?>
                            <tr class="egreso-row" role="button" onclick="abrirModalEgresoVer(<?= $r['id'] ?>)">
                                <td class="ps-3" data-col="numero_egreso"><code><?= htmlspecialchars($r['numero_egreso'] ?? '') ?></code></td>
                                <td data-col="fecha_emision"><?= !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '-' ?></td>
                                <td data-col="tipo_egreso"><span class="badge bg-light text-dark border"><?= htmlspecialchars($tipoLabel) ?></span></td>
                                <td class="fw-medium text-truncate" data-col="sujeto_nombre" style="max-width:200px"><?= htmlspecialchars($r['sujeto_nombre'] ?? '') ?></td>
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

<!-- Modal Principal Nuevo / Ver Egreso -->
<div class="modal fade" id="modalNuevoEgreso" data-bs-backdrop="static" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold">
                    <i id="modalEgresoIcono" class="bi bi-cash-stack text-primary me-2"></i>
                    <span id="modalEgresoTitulo">Registrar Nuevo Egreso</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0">
                <!-- Barra de Acciones Superior -->
                <div class="px-3 py-2 bg-light border-bottom d-flex gap-1 align-items-center flex-wrap">
                    <button type="button" class="btn btn-outline-primary btn-sm px-2" onclick="window.modalCrearFormaPago ? window.modalCrearFormaPago() : Swal.fire('Módulo en desarrollo','','info')" title="Crear Forma de Pago">
                        <i class="bi bi-credit-card fs-6"></i>
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sm px-2" onclick="window.modalCrearOpcionEgreso ? window.modalCrearOpcionEgreso() : Swal.fire('Módulo en desarrollo','','info')" title="Crear Opción de Egreso / Concepto">
                        <i class="bi bi-tags fs-6"></i>
                    </button>
                    <div class="vr mx-1"></div>
                    <button type="button" class="btn btn-outline-primary btn-sm px-2" onclick="window.modalCrearProveedor ? window.modalCrearProveedor() : Swal.fire('Módulo en desarrollo','','info')" title="Registrar nuevo Proveedor">
                        <i class="bi bi-person-plus fs-6"></i>
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sm px-2" onclick="window.modalCrearEmpleado ? window.modalCrearEmpleado() : Swal.fire('Módulo en desarrollo','','info')" title="Registrar nuevo Empleado">
                        <i class="bi bi-person-lines-fill fs-6"></i>
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm px-2 d-none" id="btnPdfEgreso" onclick="abrirPdfEgreso()" title="Generar PDF del comprobante">
                        <i class="bi bi-file-earmark-pdf fs-6"></i>
                    </button>
                    <button type="button" class="btn btn-outline-info btn-sm px-2 d-none" id="btnCorreoEgreso" onclick="enviarCorreoEgreso()" title="Enviar comprobante por correo">
                        <i class="bi bi-envelope fs-6"></i>
                    </button>
                    <!-- Conceptos de egreso (derecha): los relacionados con un módulo van como botón; el resto en un selector -->
                    <?php
                        $conceptosBoton  = array_values(array_filter($conceptos, fn($c) => ($c['comportamiento'] ?? 'GENERAL') !== 'GENERAL'));
                        $conceptosSelect = array_values(array_filter($conceptos, fn($c) => ($c['comportamiento'] ?? 'GENERAL') === 'GENERAL'));
                    ?>
                    <div id="concepto-egreso-btns-group" class="ms-auto d-flex gap-1 align-items-center flex-wrap">
                        <div class="vr mx-1"></div>
                        <?php foreach ($conceptosBoton as $c): ?>
                        <button type="button"
                                class="btn btn-sm btn-outline-secondary concepto-egreso-btn"
                                data-id="<?= $c['id'] ?>"
                                data-comportamiento="<?= htmlspecialchars($c['comportamiento'] ?? 'GENERAL') ?>">
                            <?= htmlspecialchars($c['nombre']) ?>
                        </button>
                        <?php endforeach; ?>
                        <select id="eg-select-concepto-general" class="form-select form-select-sm" style="width:auto;max-width:220px;"
                                onchange="seleccionarConceptoGeneralEgreso(this.value)"
                                title="Otros conceptos (sin relación con módulos)">
                            <option value="">Otro concepto…</option>
                            <?php foreach ($conceptosSelect as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Pestañas Principales -->
                <div class="d-flex align-items-center bg-light px-3 pt-2">
                    <ul class="nav nav-tabs border-bottom-0 flex-grow-1 tab-pestaña" id="tabsModalEgreso" role="tablist">
                        <li class="nav-item"><a class="nav-link active py-2 small fw-bold" id="tab-egreso-gen-btn" data-bs-toggle="tab" href="#eg-tab-general" role="tab"><i class="bi bi-card-text me-1"></i> General</a></li>
                        <li class="nav-item"><a class="nav-link py-2 small fw-bold" id="tab-egreso-cnt-btn" data-bs-toggle="tab" href="#eg-tab-contable" role="tab"><i class="bi bi-calculator me-1"></i> Asiento contable</a></li>
                    </ul>
                    <div class="ms-2">
                        <?php
                        $pestanasConfig = [
                            'eg-tab-contable' => 'Asiento contable'
                        ];
                        echo \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasConfig, $vistaConfig ?? [], 'egresos');
                        ?>
                    </div>
                </div>
                <div class="border-bottom bg-light mb-0"></div>

                <form id="formEgresoModal">
                    <input type="hidden" name="id" id="eg-input-id" value="">

                    <div class="tab-content border-top">
                        <!-- PESTAÑA 1: GENERAL -->
                        <div class="tab-pane fade show active" id="eg-tab-general" role="tabpanel">
                            
                            <!-- Cabecera Principal -->
                            <div class="p-3 bg-white border-bottom">
                                <div class="row g-3">
                                    <!-- Fila única: Fecha, Serie, Secuencial, Tipo Sujeto, Beneficiario -->
                                    <div class="col-md-2">
                                        <label class="form-label small fw-bold">Fecha de Emisión</label>
                                        <input type="date" name="fecha_emision" id="eg-input-fecha" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small fw-bold d-flex align-items-center">Serie <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('egresos', 'eg-select-punto', 'id_punto_emision_default') ?></label>
                                        <select name="id_punto_emision" id="eg-select-punto" class="form-select form-select-sm" onchange="syncEgresoSecuencial(this.value)" required>
                                            <?php foreach ($puntos as $p): ?>
                                                <option value="<?= $p['id'] ?>"
                                                        data-est="<?= $p['id_establecimiento'] ?>"
                                                        data-cod-est="<?= htmlspecialchars($p['cod_establecimiento']) ?>"
                                                        data-cod-punto="<?= htmlspecialchars($p['codigo_punto']) ?>">
                                                    <?= $p['cod_establecimiento'] ?>-<?= $p['codigo_punto'] ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="hidden" name="id_establecimiento" id="eg-id-establecimiento">
                                        <input type="hidden" name="establecimiento" id="eg-txt-establecimiento">
                                        <input type="hidden" name="punto_emision" id="eg-txt-punto">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small fw-bold">Secuencial</label>
                                        <input type="text" name="secuencial" id="eg-input-secuencial" class="form-control form-control-sm bg-light" readonly>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small fw-bold d-flex align-items-center">Tipo Sujeto <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('egresos', 'eg-select-tipo-sujeto', 'tipo_sujeto_default') ?></label>
                                        <select name="tipo_sujeto" id="eg-select-tipo-sujeto" class="form-select form-select-sm" onchange="toggleBuscadorSujeto(this.value)">
                                            <option value="PROVEEDOR">Proveedor</option>
                                            <option value="EMPLEADO">Empleado</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4" id="eg-box-search-sujeto">
                                        <label class="form-label small fw-bold" id="eg-lbl-search">Buscar Proveedor</label>
                                        <div class="position-relative">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-white"><i class="bi bi-person-badge" id="eg-icon-search"></i></span>
                                                <input type="text" id="eg-search-input" class="form-control" placeholder="Ingrese nombre o identificación..." autocomplete="off">
                                                <input type="hidden" name="id_sujeto" id="eg-input-id-sujeto">
                                            </div>
                                            <div id="eg-dropdown-sujetos" class="list-group shadow dropdown-predictivo position-absolute d-none w-100" style="max-height:200px; overflow-y:auto;"></div>
                                        </div>
                                    </div>

                                    <!-- Select oculto: fuente de verdad del concepto seleccionado -->
                                    <select name="id_egreso_concepto" id="eg-select-concepto" class="d-none" onchange="manejarCambioConceptoEgreso(this)">
                                        <option value="" data-comportamiento="GENERAL"></option>
                                        <?php foreach ($conceptos as $c): ?>
                                            <option value="<?= $c['id'] ?>" data-comportamiento="<?= htmlspecialchars($c['comportamiento'] ?? 'GENERAL') ?>"
                                                    data-cuenta-id="<?= (int)($c['id_cuenta_contable'] ?? 0) ?>"
                                                    data-cuenta-codigo="<?= htmlspecialchars($c['cuenta_codigo'] ?? '') ?>"
                                                    data-cuenta-nombre="<?= htmlspecialchars($c['cuenta_nombre'] ?? '') ?>">
                                                <?= htmlspecialchars($c['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="tipo_egreso" id="eg-input-tipo-egreso" value="GENERAL">
                                </div>
                            </div>

                            <!-- Tabs de Detalle vs Pago -->
                            <div class="d-flex align-items-center bg-light px-3 pt-2 border-bottom">
                                <ul class="nav nav-tabs nav-tabs-sm border-bottom-0 flex-grow-1" role="tablist" style="font-size: 0.8rem;">
                                    <li class="nav-item">
                                        <a class="nav-link active py-1 px-3 fw-bold" data-bs-toggle="tab" href="#eg-subtab-det" role="tab"><i class="bi bi-file-earmark-text me-1"></i> Obligaciones / Documentos</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link py-1 px-3 fw-bold" data-bs-toggle="tab" href="#eg-subtab-pag" role="tab"><i class="bi bi-credit-card-2-front me-1"></i> Formas de Pago</a>
                                    </li>
                                </ul>
                            </div>

                            <div class="tab-content bg-white">
                                <!-- TAB DOCUMENTOS PENDIENTES -->
                                <div class="tab-pane fade show active p-3" id="eg-subtab-det" role="tabpanel">
                                    
                                    <div id="eg-block-docs">
                                        <div class="d-flex justify-content-between mb-2 align-items-center">
                                            <h6 class="small fw-bold mb-0 text-secondary">Documentos seleccionados para pago</h6>
                                            <span id="eg-status-docs" class="badge bg-light text-muted border d-none">Cargando...</span>
                                        </div>
                                        <div class="table-responsive border rounded mb-3" style="max-height: 240px;">
                                            <table class="table table-sm table-detalle mb-0">
                                                <thead class="table-light sticky-top">
                                                    <tr>
                                                        <th style="width:80px;">Tipo</th>
                                                        <th>Documento</th>
                                                        <th>Fecha</th>
                                                        <th class="text-end">Total Doc.</th>
                                                        <th class="text-end" style="width:120px;">Monto a Pagar</th>
                                                        <th style="width:36px;"></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="eg-tbody-docs-pendientes">
                                                    <tr><td colspan="6" class="text-center text-muted py-3 small">Use los botones de concepto (arriba) para agregar documentos pendientes de pago.</td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <div id="eg-block-otros" class="d-none">
                                        <div class="border rounded-3 overflow-hidden bg-white shadow-sm mt-2">
                                            <div class="table-responsive" style="max-height: 220px;">
                                                <table class="table table-sm table-detalle mb-0 text-nowrap align-middle">
                                                    <thead class="table-light border-bottom">
                                                        <tr>
                                                            <th class="ps-3 py-2 small fw-bold text-muted" style="width: 45%;">Descripción / Concepto</th>
                                                            <th class="py-2 small fw-bold text-muted" style="width: 35%;">Cuenta contable</th>
                                                            <th class="py-2 small fw-bold text-muted text-end pe-4" style="width: 15%;">Monto</th>
                                                            <th style="width: 40px;"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="eg-tbody-otros">
                                                        <!-- Filas editables dinámicas -->
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="p-2 border-top bg-light d-flex justify-content-between align-items-center">
                                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" onclick="agregarFilaManualEgreso()">
                                                    <i class="bi bi-plus-circle me-1"></i> Agregar línea
                                                </button>
                                                <div class="small fw-bold text-muted pe-3">
                                                    Items: <span id="eg-man-count-items">0</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Dropdown compartido del buscador de cuentas por línea -->
                                    <div id="eg-cuenta-drop" class="list-group shadow position-fixed d-none" style="z-index:2200; max-height:200px; overflow-y:auto; width:320px;"></div>

                                    <div class="mt-3">
                                        <label class="form-label small fw-bold">Observaciones del Egreso</label>
                                        <textarea name="observaciones" id="eg-input-obs" rows="2" class="form-control form-control-sm"></textarea>
                                    </div>
                                </div>

                                <!-- TAB PAGOS -->
                                <div class="tab-pane fade p-3" id="eg-subtab-pag" role="tabpanel">
                                    <div class="alert alert-light border p-2 small mb-3 d-flex align-items-center justify-content-between">
                                        <span>Saldo a Pagar:</span>
                                        <span class="fw-bold fs-5 text-dark" id="eg-sumary-total">$0.00</span>
                                    </div>

                                    <div class="row g-2 mb-2 bg-light p-2 rounded border">
                                        <div class="col-md-7">
                                            <label class="form-label small fw-bold d-flex align-items-center">Forma de pago <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('egresos', 'eg-add-pago-forma', 'id_forma_pago_default') ?></label>
                                            <select id="eg-add-pago-forma" class="form-select form-select-sm" onchange="manejarCambioFormaPagoEgreso(this)">
                                                <option value="">-- Seleccione --</option>
                                                <?php foreach ($formasPago as $fp):
                                                    $esAnt = !empty($fp['es_anticipo']);
                                                    $lblSaldo = $esAnt ? '' : ' — $' . number_format((float)($fp['saldo'] ?? 0), 2);
                                                ?>
                                                    <option value="<?= $fp['id'] ?>"
                                                            data-tipo="<?= htmlspecialchars($fp['tipo'] ?? '') ?>"
                                                            data-anticipo="<?= $esAnt ? '1' : '0' ?>"
                                                            data-saldo="<?= $esAnt ? '' : number_format((float)($fp['saldo'] ?? 0), 2, '.', '') ?>"><?= htmlspecialchars($fp['nombre']) . $lblSaldo ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div id="eg-saldo-forma" class="small mt-1 d-none"></div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small fw-bold">Monto</label>
                                            <input type="number" id="eg-add-pago-monto" class="form-control form-control-sm input-numeric fw-bold" step="0.01" value="0.00">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small fw-bold d-block">&nbsp;</label>
                                            <button type="button" class="btn btn-primary btn-sm w-100 px-1" onclick="addEgresoPago()" title="Agregar forma de pago">Agregar</button>
                                        </div>

                                        <!-- Campos Condicionales para BANCO -->
                                        <div id="eg-wrapper-banco-extra" class="col-12 d-none">
                                            <div class="p-2 bg-white border rounded shadow-sm mt-1 mb-1">
                                                <div class="row g-2">
                                                    <div class="col-md-4">
                                                        <label class="form-label small fw-bold">Operación Bancaria</label>
                                                        <select id="eg-add-pago-tipo-banco" class="form-select form-select-sm bg-warning bg-opacity-10" onchange="manejarCambioTipoOperacionEgreso(this.value)">
                                                            <option value="TRANSFERENCIA" selected>Transferencia</option>
                                                            <option value="DEPOSITO">Depósito</option>
                                                            <option value="DEBITO">Débito</option>
                                                            <option value="CHEQUE">Cheque</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4 eg-div-cheque-fields d-none">
                                                        <label class="form-label small fw-bold text-primary"><i class="bi bi-card-checklist me-1"></i>N° Cheque</label>
                                                        <div class="input-group input-group-sm">
                                                            <input type="text" id="eg-add-pago-num-cheque" class="form-control border-primary" placeholder="Autogenerado...">
                                                            <button type="button" class="btn btn-outline-primary btn-sm" title="Recargar secuencia" onclick="recargarSecuenciaCheque()">
                                                                <i class="bi bi-arrow-clockwise"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4 eg-div-cheque-fields d-none">
                                                        <label class="form-label small fw-bold text-primary"><i class="bi bi-calendar-date me-1"></i>Fecha Cobro</label>
                                                        <input type="date" id="eg-add-pago-fecha-cheque" class="form-control form-control-sm border-primary">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label small fw-bold">Nº Referencia / Comprobante</label>
                                            <input type="text" id="eg-add-pago-ref" class="form-control form-control-sm" placeholder="Nota, referencia, etc...">
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
                                            <tbody id="eg-tbody-pagos">
                                                <tr><td colspan="4" class="text-center text-muted py-3 small">No hay formas de pago cargadas.</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer Total -->
                            <div class="p-3 bg-light border-top d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="small text-muted mb-1">Total Formas de Pago:</div>
                                    <div id="eg-footer-pago-tot" class="fw-bold text-muted">$0.00</div>
                                </div>
                                <div class="text-end">
                                    <div class="small fw-bold text-muted mb-0">MONTO TOTAL EGRESO</div>
                                    <div class="total-big" id="eg-final-total">$ 0.00</div>
                                </div>
                            </div>
                        </div>

                        <!-- ASIENTO -->
                        <div class="tab-pane fade p-3" id="eg-tab-contable" role="tabpanel">
                            <div class="alert alert-info py-2 small mb-3">
                                <i class="bi bi-calculator me-1"></i> El asiento será visible aquí una vez se guarde el egreso de caja.
                            </div>
                            <div class="table-responsive border rounded" style="max-height: 380px;">
                                <table class="table table-sm small mb-0 table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-3 py-2" style="width: 45%;">Cuenta Contable</th>
                                            <th class="text-end pe-3" style="width: 25%;">Débito</th>
                                            <th class="text-end pe-3" style="width: 25%;">Crédito</th>
                                        </tr>
                                    </thead>
                                    <tbody id="eg-tbody-asiento">
                                        <tr><td colspan="3" class="text-center py-5 text-muted">Visualización activa solo para registros guardados.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer justify-content-between bg-light border-top p-2">
                <div id="eg-footer-ver-extra" class="d-none">
                    <button type="button" class="btn btn-outline-danger btn-sm" id="btnAnularEgreso" onclick="anularEgreso()">
                        <i class="bi bi-slash-circle me-1"></i> Anular
                    </button>
                </div>
                <div class="ms-auto">
                    <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary btn-sm px-4 shadow-sm" id="btnGuardarEgreso" onclick="guardarEgreso()">
                        <i class="bi bi-check2-circle me-1"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let docsEgreso = [];
    let manualEgreso = [];
    let pagosEgreso = [];
    let esEgresoAnulado = false;
    const EGR_URL = '<?= BASE_URL ?>/<?= $rutaModulo ?>';

    // ── Modal secundario: selección de documentos pendientes de pago ──────────
    let _egDocsModal    = [];
    let _egSelModal     = {};
    let _egTipoDocActual = '';
    let _egModoItems    = false; // switch "Pagar por ítems"
    let _egItemsModal   = [];    // ítems aplanados
    let _egSelItems     = {};    // { iuid: monto }
    let _egDocsHasMore  = false;

    document.addEventListener('DOMContentLoaded', () => {
        const p = document.getElementById('eg-select-punto');
        if(p) syncEgresoSecuencial(p.value);

        // Predictivo
        const inSrc = document.getElementById('eg-search-input');
        let timerSrc = null;
        if (inSrc) {
            inSrc.addEventListener('input', (e) => {
                clearTimeout(timerSrc);
                const q = e.target.value.trim();
                if (q.length < 2) { document.getElementById('eg-dropdown-sujetos').classList.add('d-none'); return; }
                timerSrc = setTimeout(() => fetchSujetosPredictivo(q), 300);
            });
            inSrc.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' || e.key === 'Delete') {
                    inSrc.value = '';
                    document.getElementById('eg-input-id-sujeto').value = '';
                    docsEgreso = [];
                    renderDocsEgreso();
                    recalcEgresoTot();
                    document.getElementById('eg-dropdown-sujetos').classList.add('d-none');
                    e.preventDefault();
                }
            });
        }

        document.addEventListener('click', (e) => {
            const d = document.getElementById('eg-dropdown-sujetos');
            if (d && !d.contains(e.target) && e.target !== inSrc) d.classList.add('d-none');
        });

        // ── Modal secundario docs pendientes: z-index y búsqueda predictiva ──
        const modalEgDocs = document.getElementById('modalEgDocsPendientes');
        if (modalEgDocs) {
            modalEgDocs.addEventListener('show.bs.modal', () => {
                modalEgDocs.style.zIndex = '1080';
                requestAnimationFrame(() => {
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    if (backdrops.length) backdrops[backdrops.length - 1].style.zIndex = '1072';
                });
            });
        }

        // Click handlers para botones de concepto de egreso
        document.querySelectorAll('.concepto-egreso-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const sel = document.getElementById('eg-select-concepto');
                const comp = this.dataset.comportamiento || 'GENERAL';

                if (sel.value == this.dataset.id) {
                    // Mismo concepto ya seleccionado → re-abrir el modal de docs pendientes.
                    if (['COMPRA', 'LIQUIDACION'].includes(comp)) {
                        abrirModalEgDocsPendientes(comp);
                    } else if (['ROL', 'QUINCENA', 'PRESTAMO'].includes(comp)) {
                        abrirModalEgDocsPendientes('ROL');
                    }
                    return;
                }

                const hayDatos = docsEgreso.length > 0 || manualEgreso.some(m => m.desc.trim() !== '' || m.monto > 0) || pagosEgreso.length > 0;
                const aplicarCambio = () => {
                    sel.value = this.dataset.id;
                    manejarCambioConceptoEgreso(sel);
                    if (['COMPRA', 'LIQUIDACION'].includes(comp)) {
                        setTimeout(() => abrirModalEgDocsPendientes(comp), 150);
                    } else if (['ROL', 'QUINCENA', 'PRESTAMO'].includes(comp)) {
                        setTimeout(() => abrirModalEgDocsPendientes('ROL'), 150);
                    }
                };
                if (hayDatos) {
                    Swal.fire({
                        title: '¿Cambiar concepto?',
                        html: 'Si cambia el concepto, se eliminarán los documentos o detalles ya cargados en este egreso.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, cambiar',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#d33'
                    }).then(r => { if (r.isConfirmed) aplicarCambio(); });
                } else { aplicarCambio(); }
            });
        });
    });

    function syncEgresoSecuencial(id) {
        const s = document.getElementById('eg-select-punto');
        if(!s) return;
        const o = s.options[s.selectedIndex];
        if(!o) return;
        document.getElementById('eg-id-establecimiento').value = o.dataset.est||'';
        document.getElementById('eg-txt-establecimiento').value = o.dataset.codEst||'';
        document.getElementById('eg-txt-punto').value = o.dataset.codPunto||'';
        if (!id) return;
        fetch(`${EGR_URL}/getSecuencialAjax?id_punto_emision=${id}`).then(r => r.json()).then(res => {
            // En modo edición (ya existe un ID) NO se recalcula el secuencial:
            // debe conservarse el secuencial original del documento que se está editando.
            if (document.getElementById('eg-input-id')?.value) return;
            if (res.ok) document.getElementById('eg-input-secuencial').value = String(res.secuencial).padStart(9,'0');
        }).catch(console.error);
    }

    function sincronizarBotonesConceptoEgreso(id) {
        document.querySelectorAll('.concepto-egreso-btn').forEach(btn => {
            const activo = btn.dataset.id == id;
            btn.classList.toggle('btn-secondary', activo);
            btn.classList.toggle('btn-outline-secondary', !activo);
            btn.classList.toggle('active', activo);
        });
        // Sincronizar el selector de conceptos generales (sin relación con módulos)
        const selGen = document.getElementById('eg-select-concepto-general');
        if (selGen) {
            const existe = [...selGen.options].some(o => o.value !== '' && o.value == id);
            selGen.value = existe ? id : '';
        }
    }

    function setConceptoEgresoBotonesDisabled(disabled) {
        document.querySelectorAll('.concepto-egreso-btn').forEach(btn => { btn.disabled = disabled; });
        const selGen = document.getElementById('eg-select-concepto-general');
        if (selGen) selGen.disabled = disabled;
    }

    function seleccionarConceptoGeneralEgreso(id) {
        const sel = document.getElementById('eg-select-concepto');
        if (!sel || sel.value == id) return;
        const hayDatos = docsEgreso.length > 0 || manualEgreso.some(m => m.desc.trim() !== '' || m.monto > 0) || pagosEgreso.length > 0;
        const aplicarCambio = () => { sel.value = id; manejarCambioConceptoEgreso(sel); };
        if (hayDatos) {
            Swal.fire({
                title: '¿Cambiar concepto?',
                html: 'Si cambia el concepto, se eliminarán los documentos o detalles ya cargados en este egreso.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, cambiar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#d33'
            }).then(r => { if (r.isConfirmed) aplicarCambio(); else sincronizarBotonesConceptoEgreso(sel.value); });
        } else {
            aplicarCambio();
        }
    }

    function manejarCambioConceptoEgreso(sel) {
        sincronizarBotonesConceptoEgreso(sel.value);
        const opt = sel.options[sel.selectedIndex];
        const comp = opt.dataset.comportamiento || 'GENERAL';
        
        // Guardar el comportamiento en el campo oculto para el backend
        document.getElementById('eg-input-tipo-egreso').value = comp;

        const selSuj = document.getElementById('eg-select-tipo-sujeto');
        const prevVal = selSuj.value;

        // 1. Inteligencia de mapeo dinámica basada en Comportamiento
        if (['COMPRA', 'LIQUIDACION', 'ANTICIPO_PROVEEDOR'].includes(comp)) {
            selSuj.value = 'PROVEEDOR';
        } else if (['ROL', 'QUINCENA', 'PRESTAMO'].includes(comp)) {
            selSuj.value = 'EMPLEADO';
        } else if (comp === 'GENERAL') {
            if (!prevVal || prevVal === 'OTRO') {
                selSuj.value = 'PROVEEDOR';
            }
        }

        // 2. Alternar cuadrícula visual: entrada manual para GENERAL y ANTICIPO_PROVEEDOR,
        //    grilla de documentos para los que sí se enlazan a un módulo (COMPRA/LIQUIDACION/etc.)
        const usaManual = (comp === 'GENERAL' || comp === 'ANTICIPO_PROVEEDOR');
        document.getElementById('eg-block-docs').classList.toggle('d-none', usaManual);
        document.getElementById('eg-block-otros').classList.toggle('d-none', !usaManual);

        // 3. Limpiar SIEMPRE el detalle y los pagos al cambiar de concepto
        docsEgreso = [];
        manualEgreso = [];
        pagosEgreso = [];
        if (prevVal !== selSuj.value) {
            toggleBuscadorSujeto(selSuj.value); // limpia sujeto + arrays + re-render de docs
        } else {
            renderDocsEgreso();
        }
        renderPagosEgreso();
        recalcEgresoTot();
    }

    function toggleBuscadorSujeto(val) {
        document.getElementById('eg-input-id-sujeto').value = '';
        document.getElementById('eg-search-input').value = '';
        docsEgreso = [];
        manualEgreso = [];
        renderDocsEgreso();
        recalcEgresoTot();

        const lbl = document.getElementById('eg-lbl-search');
        const icon = document.getElementById('eg-icon-search');
        
        // Aseguramos que el buscador siempre esté visible ahora que "OTRO" sujeto no existe
        document.getElementById('eg-box-search-sujeto').classList.remove('d-none');

        if (val === 'PROVEEDOR') {
            lbl.innerText = 'Buscar Proveedor';
            icon.className = 'bi bi-truck';
        } else if (val === 'EMPLEADO') {
            lbl.innerText = 'Buscar Empleado';
            icon.className = 'bi bi-person-badge';
        }
    }

    function fetchSujetosPredictivo(q) {
        const type = document.getElementById('eg-select-tipo-sujeto').value;
        const endpoint = type === 'PROVEEDOR' ? 'getProveedoresAjax' : 'getEmpleadosAjax';
        const dropdown = document.getElementById('eg-dropdown-sujetos');

        fetch(`${EGR_URL}/${endpoint}?q=${encodeURIComponent(q)}`).then(r=>r.json()).then(res => {
            dropdown.innerHTML = '';
            if(!res.data || res.data.length === 0) {
                dropdown.innerHTML = '<div class="list-group-item small text-muted">Sin coincidencias.</div>';
            } else {
                res.data.forEach(item => {
                    const name = item.razon_social || item.nombre;
                    const ruc = item.ruc || item.identificacion || '';
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'list-group-item list-group-item-action py-2';
                    btn.innerHTML = `<div class="d-flex justify-content-between"><strong>${name}</strong> <small>${ruc}</small></div>`;
                    btn.onclick = () => selectSujeto(item, type);
                    dropdown.appendChild(btn);
                });
            }
            dropdown.classList.remove('d-none');
        });
    }

    function selectSujeto(item, type) {
        document.getElementById('eg-input-id-sujeto').value = item.id;
        document.getElementById('eg-search-input').value = item.razon_social || item.nombre;
        document.getElementById('eg-dropdown-sujetos').classList.add('d-none');
        if (typeof EGR_renderSaldoForma === 'function') EGR_renderSaldoForma();

        // COMPRA/LIQUIDACION/NÓMINA: los documentos a pagar se agregan mediante el
        // modal de documentos pendientes (botón de concepto). No se cargan aquí.
    }

    function quitarDocEgreso(i) {
        docsEgreso.splice(i, 1);
        renderDocsEgreso();
        recalcEgresoTot();
    }

    function renderDocsEgreso() {
        const tEgreso = document.getElementById('eg-input-tipo-egreso').value;

        // Entrada manual (sin documentos): conceptos GENERAL y Anticipo a proveedor.
        const egUsaManual = ['GENERAL', 'ANTICIPO_PROVEEDOR'].includes(tEgreso);
        if (!egUsaManual) {
            const tb = document.getElementById('eg-tbody-docs-pendientes');
            tb.innerHTML = '';

            const isReadOnly = document.getElementById('eg-input-obs').disabled;
            const docsConfirmados = docsEgreso.filter(d => d.seleccionado);

            if (docsConfirmados.length === 0) {
                const tipoLabel = tEgreso === 'COMPRA' ? 'facturas de compra' : tEgreso === 'LIQUIDACION' ? 'liquidaciones de compras' : (['ROL','QUINCENA','PRESTAMO'].includes(tEgreso) ? 'roles pendientes' : 'documentos');
                const ayuda = ['ROL','QUINCENA','PRESTAMO'].includes(tEgreso) ? 'Seleccione el empleado para cargar sus roles pendientes.' : 'Haga clic en el botón de concepto para agregar documentos.';
                tb.innerHTML = `<tr><td colspan="6" class="text-center py-3 text-muted small">No hay ${tipoLabel} seleccionados. ${ayuda}</td></tr>`;
                return;
            }

            docsConfirmados.forEach((d) => {
                const i = docsEgreso.indexOf(d);
                const badgesDoc = {
                    COMPRA:      '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25" style="font-size:0.65rem">COMPRA</span>',
                    LIQUIDACION: '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25" style="font-size:0.65rem">LIQUID.</span>',
                    ROL:         '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size:0.65rem">NÓMINA</span>',
                    ANTICIPO:    '<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25" style="font-size:0.65rem">ANTICIPO</span>'
                };
                const esPrestamo = String(d.tipo_bd || '').startsWith('PRESTAMO');
                const esNominaTipo = d.tipo_bd === 'ROL' || d.tipo_bd === 'ANTICIPO' || esPrestamo;
                const tipoBadge = esPrestamo
                    ? '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25" style="font-size:0.65rem">PRÉSTAMO</span>'
                    : (badgesDoc[d.tipo_bd] || `<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25" style="font-size:0.65rem">${d.tipo_bd}</span>`);
                const numeroCell = esNominaTipo
                    ? `<span class="small fw-bold">${d.numero}</span>`
                    : `<code class="small text-primary fw-bold pointer text-decoration-underline" onclick="abrirPrevisualizadorDoc(${d.id}, '${d.tipo_bd}')">${d.numero}</code>`;
                const tieneItems = Array.isArray(d.items) && d.items.length > 0;
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${tipoBadge}</td>
                    <td>${numeroCell}</td>
                    <td class="small">${d.fecha ? d.fecha.split('-').reverse().join('/') : ''}</td>
                    <td class="text-end small">$${d.total.toFixed(2)}</td>
                    <td class="text-end"><input type="number" class="form-control form-control-sm input-numeric px-1" style="height:26px;" step="0.01" value="${d.pagado > 0 ? d.pagado.toFixed(2) : ''}" ${(isReadOnly || tieneItems) ? 'disabled' : ''} oninput="updateDocAmt(${i}, this.value, this)"></td>
                    <td class="text-center">${!isReadOnly ? `<button type="button" class="btn btn-link btn-sm p-0 text-danger" onclick="quitarDocEgreso(${i})" title="Quitar"><i class="bi bi-x-lg"></i></button>` : ''}</td>
                `;
                tb.appendChild(tr);

                if (tieneItems) {
                    const filas = d.items.map(it => `
                        <div class="d-flex justify-content-between align-items-center px-2 py-1 border-top border-light" style="font-size:0.74rem;">
                            <span class="text-truncate text-secondary" style="max-width:75%"><i class="bi bi-dot"></i>${String(it.desc).replace(/</g, '&lt;')}</span>
                            <span class="fw-bold text-secondary">$${(it.pagado || 0).toFixed(2)}</span>
                        </div>`).join('');
                    const trItems = document.createElement('tr');
                    trItems.className = 'eg-items-detalle';
                    trItems.innerHTML = `<td class="border-0"></td>
                        <td colspan="5" class="p-0 border-0">
                            <div class="bg-light bg-opacity-50 rounded-bottom mb-1">
                                <div class="small text-muted px-2 pt-1 fw-bold"><i class="bi bi-list-check me-1"></i>Ítems pagados</div>
                                ${filas}
                            </div>
                        </td>`;
                    tb.appendChild(trItems);
                }
            });
        } else {
            const tb = document.getElementById('eg-tbody-otros');
            tb.innerHTML = '';
            
            const isReadOnly = document.getElementById('eg-input-obs').disabled;
            
            // Inicializar primera fila vacía automáticamente si está vacío en modo creación
            if (manualEgreso.length === 0 && !isReadOnly) {
                manualEgreso.push({ desc: '', monto: 0, ...egConceptoCuentaActual() });
            }

            if (manualEgreso.length === 0) {
                tb.innerHTML = '<tr><td colspan="4" class="text-center py-3 small text-muted">Sin registros asignados.</td></tr>';
            } else {
                manualEgreso.forEach((m, i) => {
                    const tr = document.createElement('tr');
                    tr.className = 'row-detalle';
                    const ctaTxt = m.cuenta_codigo ? `${m.cuenta_codigo} - ${m.cuenta_nombre}` : '';
                    tr.innerHTML = `
                        <td class="ps-3">
                            <input type="text" class="form-control form-control-sm input-detalle"
                                value="${m.desc}"
                                placeholder="Escriba el detalle o concepto del egreso..."
                                ${isReadOnly ? 'disabled' : ''}
                                oninput="actualizarManualEgresoDesc(${i}, this.value)">
                        </td>
                        <td class="px-1">
                            <input type="text" class="form-control form-control-sm input-detalle eg-cuenta-input"
                                value="${ctaTxt.replace(/"/g, '&quot;')}"
                                placeholder="Buscar cuenta contable..."
                                autocomplete="off"
                                data-idx="${i}"
                                ${isReadOnly ? 'disabled' : ''}
                                oninput="egCuentaInput(this)"
                                onfocus="this.select()"
                                onblur="egCuentaBlur(this)">
                        </td>
                        <td class="pe-4">
                            <input type="number" class="form-control form-control-sm input-detalle text-end fw-bold"
                                value="${m.monto > 0 ? m.monto.toFixed(2) : ''}"
                                placeholder="0.00"
                                step="0.01"
                                ${isReadOnly ? 'disabled' : ''}
                                oninput="actualizarManualEgresoMonto(${i}, this.value)">
                        </td>
                        <td class="text-center align-middle">
                            ${!isReadOnly ? `<i class="bi bi-trash text-danger pointer remove-row" title="Eliminar fila" onclick="eliminarFilaManualEgreso(${i})"></i>` : ''}
                        </td>
                    `;
                    tb.appendChild(tr);
                });
            }

            const btnAddLine = document.querySelector('#eg-block-otros button.btn-link');
            if (btnAddLine) btnAddLine.style.display = isReadOnly ? 'none' : '';
            
            const itemsCounter = document.getElementById('eg-man-count-items');
            if (itemsCounter) itemsCounter.innerText = manualEgreso.length;
        }
    }

    function toggleEgDoc(i, s) {
        docsEgreso[i].seleccionado = s;
        docsEgreso[i].pagado = s ? docsEgreso[i].pendiente : 0;
        renderDocsEgreso();
        recalcEgresoTot();
    }
    function updateDocAmt(i, v, el) {
        let val = parseFloat(v) || 0;
        const limit = docsEgreso[i].pendiente;
        if (val > limit) {
            val = limit;
            if (el) el.value = limit.toFixed(2); // Forzar valor de vuelta al límite visualmente
        }
        docsEgreso[i].pagado = val;
        recalcEgresoTot();
    }

    function agregarFilaManualEgreso() {
        manualEgreso.push({ desc: '', monto: 0, ...egConceptoCuentaActual() });
        renderDocsEgreso();
        recalcEgresoTot();
    }

    // ── Cuenta contable por línea ─────────────────────────────────────────────
    // Cuenta por defecto = la del concepto seleccionado (puede cambiarse por línea).
    function egConceptoCuentaActual() {
        const sel = document.getElementById('eg-select-concepto');
        const opt = sel ? sel.options[sel.selectedIndex] : null;
        if (!opt) return {};
        const id = parseInt(opt.dataset.cuentaId || '0') || 0;
        if (!id) return {};
        return { id_cuenta: id, cuenta_codigo: opt.dataset.cuentaCodigo || '', cuenta_nombre: opt.dataset.cuentaNombre || '' };
    }

    let _egCuentaTimer = null;
    function egCuentaInput(input) {
        const drop = document.getElementById('eg-cuenta-drop');
        const idx  = parseInt(input.dataset.idx);
        const q    = input.value.trim();
        clearTimeout(_egCuentaTimer);
        if (q.length < 2) { drop.classList.add('d-none'); return; }
        _egCuentaTimer = setTimeout(() => {
            fetch(`${EGR_URL}/searchCuentasAjax?q=${encodeURIComponent(q)}`)
                .then(r => r.json())
                .then(res => {
                    drop.innerHTML = '';
                    if (!res.ok || !res.data || res.data.length === 0) {
                        drop.innerHTML = '<div class="list-group-item small text-muted">Sin resultados.</div>';
                    } else {
                        res.data.forEach(item => {
                            const b = document.createElement('button');
                            b.type = 'button';
                            b.className = 'list-group-item list-group-item-action py-1 small';
                            b.innerHTML = `<strong>${item.codigo}</strong> - ${item.nombre}`;
                            // mousedown + preventDefault: selecciona antes de que el input pierda foco.
                            b.onmousedown = (e) => { e.preventDefault(); egCuentaSelect(idx, item); };
                            drop.appendChild(b);
                        });
                    }
                    const r = input.getBoundingClientRect();
                    drop.style.left  = r.left + 'px';
                    drop.style.top   = r.bottom + 'px';
                    drop.style.width = Math.max(r.width, 280) + 'px';
                    drop.classList.remove('d-none');
                })
                .catch(() => drop.classList.add('d-none'));
        }, 250);
    }

    function egCuentaSelect(idx, item) {
        if (manualEgreso[idx]) {
            manualEgreso[idx].id_cuenta     = item.id;
            manualEgreso[idx].cuenta_codigo = item.codigo;
            manualEgreso[idx].cuenta_nombre = item.nombre;
        }
        document.getElementById('eg-cuenta-drop').classList.add('d-none');
        renderDocsEgreso();
    }

    function egCuentaBlur(input) {
        setTimeout(() => {
            document.getElementById('eg-cuenta-drop').classList.add('d-none');
            const idx = parseInt(input.dataset.idx);
            const m = manualEgreso[idx];
            if (m) input.value = m.cuenta_codigo ? `${m.cuenta_codigo} - ${m.cuenta_nombre}` : '';
        }, 150);
    }

    function eliminarFilaManualEgreso(i) {
        manualEgreso.splice(i, 1);
        renderDocsEgreso();
        recalcEgresoTot();
    }

    function actualizarManualEgresoDesc(i, val) {
        if (manualEgreso[i]) manualEgreso[i].desc = val;
    }

    function actualizarManualEgresoMonto(i, val) {
        if (manualEgreso[i]) {
            manualEgreso[i].monto = parseFloat(val) || 0;
            recalcEgresoTot();
        }
    }

    function recalcEgresoTot() {
        const comp = document.getElementById('eg-input-tipo-egreso').value;
        let total = 0;
        if (comp !== 'GENERAL') {
            docsEgreso.filter(d => d.seleccionado).forEach(d => total += d.pagado);
        } else {
            manualEgreso.forEach(m => total += m.monto);
        }
        document.getElementById('eg-final-total').innerText = '$ ' + total.toFixed(2);
        document.getElementById('eg-sumary-total').innerText = '$ ' + total.toFixed(2);

        // El monto sugerido siempre refleja lo que falta por pagar (= saldo a pagar).
        document.getElementById('eg-add-pago-monto').value = egPendientePago().toFixed(2);
    }

    // Monto que falta por pagar = total del egreso − suma de pagos ya cargados.
    function egPendientePago() {
        const total = parseFloat(document.getElementById('eg-final-total').innerText.replace('$', '').trim()) || 0;
        const sum = pagosEgreso.reduce((a, b) => a + b.monto, 0);
        return Math.max(0, +(total - sum).toFixed(2));
    }

    function manejarCambioFormaPagoEgreso(el) {
        const wrapper = document.getElementById('eg-wrapper-banco-extra');
        if (!wrapper) return;
        
        const sel = el.options[el.selectedIndex];
        const tipo = sel ? (sel.dataset.tipo || '') : '';
        
        if (tipo.toUpperCase() === 'BANCO') {
            wrapper.classList.remove('d-none');
            document.getElementById('eg-add-pago-tipo-banco').value = 'TRANSFERENCIA';
            manejarCambioTipoOperacionEgreso('TRANSFERENCIA');
        } else {
            wrapper.classList.add('d-none');
        }
        EGR_renderSaldoForma();

        // Al elegir forma de pago, sugerir el monto pendiente por pagar (= saldo a pagar).
        const inpMonto = document.getElementById('eg-add-pago-monto');
        if (sel && sel.value) {
            inpMonto.value = egPendientePago().toFixed(2);
        }
    }

    function EGR_fmtMoney(n) {
        return '$' + (parseFloat(n) || 0).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function EGR_renderSaldoForma() {
        const combo = document.getElementById('eg-add-pago-forma');
        const box   = document.getElementById('eg-saldo-forma');
        if (!combo || !box) return;
        const opt = combo.options[combo.selectedIndex];
        if (!opt || !opt.value) { box.classList.add('d-none'); box.innerHTML = ''; return; }

        const esAnt = opt.dataset.anticipo === '1';
        if (!esAnt) {
            const saldo = parseFloat(opt.dataset.saldo || '0');
            box.className = 'small mt-1 fw-bold ' + (saldo < 0 ? 'text-danger' : 'text-success');
            box.innerHTML = '<i class="bi bi-wallet2 me-1"></i>Saldo disponible: ' + EGR_fmtMoney(saldo);
            box.classList.remove('d-none');
            return;
        }

        // Anticipo: el saldo depende del proveedor seleccionado
        const tipoSujeto = (document.getElementById('eg-select-tipo-sujeto') || {}).value || '';
        const idProv = document.getElementById('eg-input-id-sujeto').value || '';
        if (tipoSujeto !== 'PROVEEDOR' || !idProv) {
            box.className = 'small mt-1 fw-bold text-warning';
            box.innerHTML = '<i class="bi bi-info-circle me-1"></i>Selecciona el proveedor para ver su saldo de anticipo.';
            box.classList.remove('d-none');
            return;
        }
        box.className = 'small mt-1 text-muted';
        box.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Consultando saldo...';
        box.classList.remove('d-none');
        fetch(`${EGR_URL}/getSaldoAnticipoAjax?id_forma=${opt.value}&id_tercero=${idProv}`)
            .then(r => r.json())
            .then(res => {
                if (!res.ok) { box.className = 'small mt-1 text-danger'; box.innerHTML = res.mensaje || 'No se pudo obtener el saldo.'; return; }
                const saldo = parseFloat(res.saldo) || 0;
                box.className = 'small mt-1 fw-bold ' + (saldo < 0 ? 'text-danger' : 'text-success');
                box.innerHTML = '<i class="bi bi-person-badge me-1"></i>Saldo de anticipo del proveedor: ' + EGR_fmtMoney(saldo);
            })
            .catch(() => { box.className = 'small mt-1 text-danger'; box.innerHTML = 'Error al consultar el saldo.'; });
    }

    function manejarCambioTipoOperacionEgreso(val) {
        const divs = document.querySelectorAll('.eg-div-cheque-fields');
        if (val === 'CHEQUE') {
            divs.forEach(d => d.classList.remove('d-none'));
            recargarSecuenciaCheque();
        } else {
            divs.forEach(d => d.classList.add('d-none'));
        }
    }

    function recargarSecuenciaCheque() {
        const fp = document.getElementById('eg-add-pago-forma').value;
        if (!fp) return;
        const input = document.getElementById('eg-add-pago-num-cheque');
        input.placeholder = 'Buscando...';
        
        fetch(`${EGR_URL}/getUltimoChequeAjax?id_forma_pago=${fp}`)
            .then(r => r.json())
            .then(res => {
                if (res.ok && res.siguiente) {
                    input.value = res.siguiente;
                } else {
                    input.value = '';
                    input.placeholder = 'Manual Nº';
                }
            })
            .catch(() => { input.placeholder = 'Manual Nº'; });
    }

    function renderPagosEgreso() {
        const tb = document.getElementById('eg-tbody-pagos');
        tb.innerHTML = '';
        if (pagosEgreso.length === 0) {
            tb.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted small">No hay formas cargadas.</td></tr>';
        } else {
            pagosEgreso.forEach((p, i) => {
                let txtRef = p.ref || '';
                if (p.tipo_operacion_bancaria) {
                    let pref = p.tipo_operacion_bancaria;
                    if (pref === 'CHEQUE') pref = `CHQ#${p.numero_cheque||'?'} (${p.fecha_cobro||'?'})`;
                    txtRef = `[${pref}] ` + txtRef;
                }
                if (!txtRef.trim()) txtRef = '-';

                const btnTrash = esEgresoAnulado ? '' : `<button type="button" class="btn btn-link btn-sm text-danger p-0" onclick="pagosEgreso.splice(${i},1);renderPagosEgreso();"><i class="bi bi-trash"></i></button>`;
                
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><span class="badge bg-secondary bg-opacity-10 text-secondary border border-opacity-25">${p.nombre}</span></td>
                    <td class="small">${txtRef}</td>
                    <td class="text-end fw-bold text-primary">$${p.monto.toFixed(2)}</td>
                    <td class="text-center">${btnTrash}</td>
                `;
                tb.appendChild(tr);
            });
        }
        // Recalc pago footer
        let sum = 0; pagosEgreso.forEach(p=>sum+=p.monto);
        const f = document.getElementById('eg-footer-pago-tot');
        f.innerText = '$ ' + sum.toFixed(2);
        const final = parseFloat(document.getElementById('eg-final-total').innerText.replace('$ ','')) || 0;
        f.className = 'fw-bold ' + (Math.abs(sum - final) < 0.01 ? 'text-success' : 'text-danger');
    }

    function addEgresoPago() {
        const c = document.getElementById('eg-add-pago-forma');
        if (!c.value) { Swal.fire('Requerido', 'Seleccione forma de pago', 'warning'); return; }
        
        const selOpt = c.options[c.selectedIndex];
        const tipoPadre = (selOpt.dataset.tipo || '').toUpperCase();
        const m = parseFloat(document.getElementById('eg-add-pago-monto').value) || 0;
        const r = document.getElementById('eg-add-pago-ref').value;

        if (m <= 0) { Swal.fire('Inválido', 'Monto debe ser mayor a 0', 'warning'); return; }

        let tipoOp = null;
        let numChq = null;
        let fecChq = null;

        if (tipoPadre === 'BANCO') {
            tipoOp = document.getElementById('eg-add-pago-tipo-banco').value;
            if (tipoOp === 'CHEQUE') {
                numChq = document.getElementById('eg-add-pago-num-cheque').value.trim();
                fecChq = document.getElementById('eg-add-pago-fecha-cheque').value;
                if (!numChq) { Swal.fire('Campo requerido', 'Ingrese número de cheque', 'warning'); return; }
                if (!fecChq) { Swal.fire('Campo requerido', 'Ingrese fecha de cobro del cheque', 'warning'); return; }
            }
        }

        pagosEgreso.push({ 
            id_forma: c.value, 
            nombre: selOpt.text, 
            monto: m, 
            ref: r,
            tipo_operacion_bancaria: tipoOp,
            numero_cheque: numChq,
            fecha_cobro: fecChq
        });

        // Reset inputs
        document.getElementById('eg-add-pago-ref').value = '';
        document.getElementById('eg-add-pago-num-cheque').value = '';
        document.getElementById('eg-add-pago-fecha-cheque').value = '';

        renderPagosEgreso();
        // Pre-cargar el monto con lo que aún falta por pagar.
        document.getElementById('eg-add-pago-monto').value = egPendientePago().toFixed(2);
    }

    function abrirModalEgreso(esNuevo = true) {
        document.getElementById('modalEgresoTitulo').textContent = 'Registrar Nuevo Egreso';
        document.getElementById('modalEgresoIcono').className = 'bi bi-cash-stack text-primary me-2';
        document.getElementById('formEgresoModal').reset();
        document.getElementById('eg-input-id').value = '';
        document.getElementById('eg-input-id-sujeto').value = '';

        const boxSaldoEgr = document.getElementById('eg-saldo-forma');
        if (boxSaldoEgr) { boxSaldoEgr.classList.add('d-none'); boxSaldoEgr.innerHTML = ''; }

        // Reset pestaña Asiento contable (placeholder para registros nuevos)
        const egTbAsientoReset = document.getElementById('eg-tbody-asiento');
        if (egTbAsientoReset) egTbAsientoReset.innerHTML = '<tr><td colspan="3" class="text-center py-5 text-muted">Visualización activa solo para registros guardados.</td></tr>';
        
        esEgresoAnulado = false;
        const btnG = document.getElementById('btnGuardarEgreso');
        btnG.classList.remove('d-none');
        btnG.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar';
        btnG.onclick = () => guardarEgreso(); // Restablecer a callback normal

        setControlesGeneralesHabilitados(true);
        setPagosControlesHabilitados(true);

        document.getElementById('eg-footer-ver-extra').classList.add('d-none');
        document.getElementById('btnPdfEgreso').classList.add('d-none');
        document.getElementById('btnCorreoEgreso').classList.add('d-none');
        document.getElementById('eg-input-fecha').value = new Date().toISOString().slice(0,10);
        
        // Resetear controles dinámicos de pagos
        document.getElementById('eg-add-pago-forma').value = '';
        document.getElementById('eg-wrapper-banco-extra').classList.add('d-none');
        
        docsEgreso = []; manualEgreso = []; pagosEgreso = [];
        const p = document.getElementById('eg-select-punto');
        if(p && esNuevo) syncEgresoSecuencial(p.value);
        
        document.getElementById('eg-select-concepto').value = '';
        manejarCambioConceptoEgreso(document.getElementById('eg-select-concepto'));
        setConceptoEgresoBotonesDisabled(false);

        // Aplicar preferencias/favoritos del usuario
        if (typeof window.aplicarFavoritosModal === 'function') {
            window.aplicarFavoritosModal('#modalNuevoEgreso');
        }

        renderPagosEgreso();
        recalcEgresoTot();

        const t = new bootstrap.Tab(document.querySelector('#tabsModalEgreso a[href="#eg-tab-general"]'));
        t.show();
        
        const m = new bootstrap.Modal(document.getElementById('modalNuevoEgreso'));
        m.show();
    }

    async function guardarEgreso() {
        const tS = document.getElementById('eg-select-tipo-sujeto').value;
        const inputFec = document.getElementById('eg-input-fecha').value;
        const hoy = new Date().toISOString().split('T')[0];
        if (inputFec > hoy) {
            Swal.fire('Fecha Inválida', 'La fecha de emisión no puede ser posterior a la fecha actual.', 'warning');
            return;
        }

        const data = {
            fecha_emision: document.getElementById('eg-input-fecha').value,
            establecimiento: document.getElementById('eg-txt-establecimiento').value,
            punto_emision: document.getElementById('eg-txt-punto').value,
            id_punto_emision: document.getElementById('eg-select-punto').value,
            secuencial: document.getElementById('eg-input-secuencial').value,
            tipo_egreso: document.getElementById('eg-input-tipo-egreso').value,
            id_egreso_concepto: document.getElementById('eg-select-concepto').value,
            tipo_sujeto: tS,
            observaciones: document.getElementById('eg-input-obs').value,
            detalles: [],
            pagos: pagosEgreso.map(p=>({ 
                id_forma_pago: p.id_forma, 
                monto: p.monto, 
                referencia: p.ref,
                tipo_operacion_bancaria: p.tipo_operacion_bancaria,
                numero_cheque: p.numero_cheque,
                fecha_cobro: p.fecha_cobro
            }))
        };

        const valSujeto = document.getElementById('eg-input-id-sujeto').value;
        if (!valSujeto) { Swal.fire('Requerido', 'Seleccione un Beneficiario (Proveedor/Empleado) válido.', 'warning'); return; }

        if (tS === 'PROVEEDOR') data.id_proveedor = valSujeto;
        else if (tS === 'EMPLEADO') data.id_empleado = valSujeto;

        if (!data.id_egreso_concepto) { Swal.fire('Requerido', 'Debe seleccionar el Concepto del Egreso.', 'warning'); return; }

        // Det
        if (['GENERAL', 'ANTICIPO_PROVEEDOR'].includes(data.tipo_egreso)) {
            // Lógica Manual
            if(manualEgreso.length===0){ Swal.fire('Atención', 'Agregue al menos un concepto o ítem manual.', 'warning'); return;}
            manualEgreso.forEach(m => {
                data.detalles.push({ tipo_documento: 'MANUAL', descripcion: m.desc, monto_documento: m.monto, saldo_anterior: m.monto, monto_pagado: m.monto, saldo_actual: 0, id_cuenta_contable: m.id_cuenta || null });
            });
        } else {
            // Lógica Documentos Pendientes (Compras, Liquidaciones, etc)
            const sel = docsEgreso.filter(d=>d.seleccionado && d.pagado > 0);
            if(sel.length===0){ Swal.fire('Atención', 'Seleccione al menos un documento pendiente y asigne un monto.', 'warning'); return;}

            // Validar que ningún abono supere el límite permitido
            const invalido = sel.find(s => s.pagado > s.pendiente + 0.01);
            if (invalido) {
                Swal.fire('Monto Inválido', `El monto a pagar ($${invalido.pagado.toFixed(2)}) en el documento ${invalido.numero} no puede superar su saldo pendiente de $${invalido.pendiente.toFixed(2)}.`, 'warning');
                return;
            }

            sel.forEach(s => {
                if (Array.isArray(s.items) && s.items.length > 0) {
                    // Un renglón por ítem pagado (mismo documento; el saldo se calcula por SUMA)
                    s.items.forEach(it => {
                        if ((it.pagado || 0) <= 0) return;
                        const tItem = it.total || it.pagado;
                        data.detalles.push({
                            tipo_documento: s.tipo_bd,
                            id_referencia_documento: s.id,
                            numero_documento: s.numero,
                            descripcion: `${s.numero} · ${it.desc}`,
                            monto_documento: tItem,
                            saldo_anterior: tItem,
                            monto_pagado: it.pagado,
                            saldo_actual: Math.max(0, tItem - it.pagado)
                        });
                    });
                } else {
                    data.detalles.push({
                        tipo_documento: s.tipo_bd,
                        id_referencia_documento: s.id,
                        numero_documento: s.numero,
                        monto_documento: s.total,
                        saldo_anterior: s.pendiente,
                        monto_pagado: s.pagado,
                        saldo_actual: s.pendiente - s.pagado
                    });
                }
            });
        }

        data.monto_total = data.detalles.reduce((a,b)=>a+b.monto_pagado,0);
        const sumPag = pagosEgreso.reduce((a,b)=>a+b.monto,0);
        if(Math.abs(sumPag - data.monto_total) > 0.01) { Swal.fire('Inconsistencia', 'La suma de pagos registrados no coincide con el total liquidado.', 'error'); return; }

        // Validación 1 (cliente): fecha de emisión no puede ser anterior a la fecha de los documentos
        if (data.tipo_egreso !== 'GENERAL') {
            for (const d of docsEgreso.filter(d => d.seleccionado)) {
                if (d.fecha && data.fecha_emision < d.fecha) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Fecha inválida',
                        html: `La fecha de emisión del egreso no puede ser anterior a la fecha del documento <strong>${d.numero}</strong> (${d.fecha.split('-').reverse().join('/')}).`
                    });
                    return;
                }
            }
        }

        // Validación 2 (servidor): verificar que la fecha esté dentro de un periodo contable abierto
        try {
            const periodoRes = await fetch(`${EGR_URL}/verificarPeriodoAjax?fecha=${encodeURIComponent(data.fecha_emision)}`);
            const periodoJson = await periodoRes.json();
            if (!periodoJson.ok) {
                Swal.fire({ icon: 'error', title: 'Periodo Contable Cerrado', text: periodoJson.mensaje });
                return;
            }
        } catch (e) {
            Swal.fire('Error', 'No se pudo verificar el periodo contable. Intente de nuevo.', 'error');
            return;
        }

        const b = document.getElementById('btnGuardarEgreso'); b.disabled = true;
        fetch(`${EGR_URL}/guardarAjax`, {
            method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body: 'data=' + encodeURIComponent(JSON.stringify(data))
        }).then(r=>r.json()).then(res => {
            b.disabled = false;
            if(res.ok) {
                bootstrap.Modal.getInstance(document.getElementById('modalNuevoEgreso')).hide();
                window.EGR_fetchSearch(1);
                Swal.fire('Éxito', res.mensaje, 'success');
            } else {
                Swal.fire('Error al guardar', res.mensaje, 'error');
            }
        }).catch(e=> { 
            b.disabled = false; 
            Swal.fire('Error de Red', 'No se pudo completar la operación en este momento.', 'error'); 
        });
    }

    function abrirPdfEgreso() {
        const id = document.getElementById('eg-input-id').value;
        if (!id) return;
        const a = document.createElement('a');
        a.href = `${EGR_URL}/pdf?id=${id}&_=${Date.now()}`;
        a.download = '';
        document.body.appendChild(a);
        a.click();
        a.remove();
    }

    async function enviarCorreoEgreso() {
        const id = document.getElementById('eg-input-id').value;
        if (!id) return;
        const emailPrecargado = document.getElementById('btnCorreoEgreso').dataset.email || '';
        const { value: correo } = await Swal.fire({
            title: 'Enviar comprobante',
            input: 'email',
            inputLabel: 'Correo del destinatario',
            inputPlaceholder: 'proveedor@correo.com',
            inputValue: emailPrecargado,
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-envelope me-1"></i> Enviar',
            cancelButtonText: 'Cancelar',
            target: document.getElementById('modalNuevoEgreso'),
            inputValidator: (v) => {
                if (!v || !v.trim()) return 'Ingrese un correo.';
                if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v.trim())) return 'Correo no válido.';
            }
        });
        if (correo === undefined) return;

        Swal.fire({ title: 'Enviando…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        try {
            const fd = new FormData();
            fd.append('id', id);
            fd.append('correo', correo || '');
            const res = await fetch(`${EGR_URL}/enviarCorreoAjax`, { method: 'POST', body: fd });
            const json = await res.json();
            Swal.fire({ icon: json.ok ? 'success' : 'error', title: json.ok ? 'Enviado' : 'Atención', text: json.mensaje });
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo enviar el correo.' });
        }
    }

    function abrirModalEgresoVer(id) {
        fetch(`${EGR_URL}/getEgresoAjax?id=${id}`).then(r=>r.json()).then(res => {
            if(!res.ok) return alert(res.mensaje);
            const e = res.data;
            abrirModalEgreso(false);
            document.getElementById('modalEgresoTitulo').textContent = `Ver Egreso #${e.numero_egreso}`;
            document.getElementById('eg-input-id').value = e.id;
            document.getElementById('btnPdfEgreso').classList.remove('d-none');
            const _btnCorreoEg = document.getElementById('btnCorreoEgreso');
            _btnCorreoEg.classList.remove('d-none');
            _btnCorreoEg.dataset.email = e.sujeto_email || '';
            document.getElementById('eg-input-fecha').value = e.fecha_emision;
            // Mostrar la serie (punto) original del documento, sin disparar el cálculo del siguiente secuencial
            if (e.id_punto_emision) {
                document.getElementById('eg-select-punto').value = e.id_punto_emision;
            }
            document.getElementById('eg-input-secuencial').value = String(e.secuencial ?? '').padStart(9,'0');
            document.getElementById('eg-input-obs').value = e.observaciones || '';
            const comp = e.tipo_egreso || 'GENERAL';
            document.getElementById('eg-input-tipo-egreso').value = comp;
            document.getElementById('eg-select-concepto').value = e.id_egreso_concepto;
            sincronizarBotonesConceptoEgreso(e.id_egreso_concepto);
            setConceptoEgresoBotonesDisabled(true); // En modo ver/editar el concepto es de solo lectura

            const ts = e.tipo_sujeto || 'PROVEEDOR';
            document.getElementById('eg-select-tipo-sujeto').value = ts;
            toggleBuscadorSujeto(ts);
            document.getElementById('eg-input-id-sujeto').value = e.id_proveedor || e.id_empleado || '';

            // 2. Alternar cuadrícula visual en modo Ver
            const blkDoc  = document.getElementById('eg-block-docs');
            const blkOtr  = document.getElementById('eg-block-otros');
            
            if (['GENERAL', 'ANTICIPO_PROVEEDOR'].includes(comp)) {
                blkDoc.classList.add('d-none');
                blkOtr.classList.remove('d-none');

                document.getElementById('eg-search-input').value = e.sujeto_nombre;
                manualEgreso = (e.detalles||[]).map(d=>({
                    desc: d.descripcion||'Detalle',
                    monto: parseFloat(d.monto_pagado),
                    id_cuenta: d.id_cuenta_contable ? parseInt(d.id_cuenta_contable) : 0,
                    cuenta_codigo: d.cuenta_codigo || '',
                    cuenta_nombre: d.cuenta_nombre || ''
                }));
                // Renderizado diferido hasta que se definan los permisos visuales más abajo.
            } else {
                blkDoc.classList.remove('d-none');
                blkOtr.classList.add('d-none');

                document.getElementById('eg-search-input').value = e.sujeto_nombre;
                
                // Hidratar docsEgreso agrupando por documento (varias filas = pago por ítems)
                docsEgreso = [];
                const _egGrupos = {};
                (e.detalles || []).forEach(d => {
                    const tipoBd = d.tipo_documento || comp;
                    const key = tipoBd + ':' + d.id_referencia_documento;
                    const prefItem = (d.numero_documento || '') + ' · ';
                    const esItem = (d.descripcion || '').startsWith(prefItem);
                    if (!_egGrupos[key]) {
                        _egGrupos[key] = {
                            id:          d.id_referencia_documento,
                            tipo_bd:     tipoBd,
                            numero:      d.numero_documento || '-',
                            fecha:       d.fecha_documento || null,
                            total:       0,
                            pendiente:   0,
                            seleccionado: true,
                            pagado:      0,
                            items:       [],
                            _hasItems:   false
                        };
                        docsEgreso.push(_egGrupos[key]);
                    }
                    const g = _egGrupos[key];
                    g.total     += parseFloat(d.monto_documento || 0);
                    g.pendiente += parseFloat(d.saldo_anterior || d.monto_documento || 0);
                    g.pagado    += parseFloat(d.monto_pagado || 0);
                    if (esItem) {
                        g._hasItems = true;
                        g.items.push({ desc: (d.descripcion || '').slice(prefItem.length) || d.numero_documento, pagado: parseFloat(d.monto_pagado || 0), total: parseFloat(d.monto_documento || 0) });
                    }
                });
                docsEgreso.forEach(g => { if (!g._hasItems) g.items = null; delete g._hasItems; });
                // Renderizar usando la función unificada (modo solo-lectura: eg-input-obs está disabled)
                renderDocsEgreso();
            }

            pagosEgreso = (e.pagos||[]).map(p=>({ 
                id_forma: p.id_forma_pago, 
                nombre: p.forma_pago_nombre, 
                monto: parseFloat(p.monto), 
                ref: p.referencia,
                tipo_operacion_bancaria: p.tipo_operacion_bancaria,
                numero_cheque: p.numero_cheque,
                fecha_cobro: p.fecha_cobro
            }));

            esEgresoAnulado = (e.estado === 'anulado');
            
            const btnGuardar = document.getElementById('btnGuardarEgreso');
            
            if (esEgresoAnulado) {
                setControlesGeneralesHabilitados(false);
                btnGuardar.classList.add('d-none');
                document.getElementById('modalEgresoTitulo').innerHTML += ' <span class="badge bg-danger ms-2">ANULADO</span>';
                setPagosControlesHabilitados(false);
                document.getElementById('eg-input-fecha').disabled = true;
            } else {
                // NO ESTÁ ANULADO: Habilitar botón "Actualizar"
                btnGuardar.classList.remove('d-none');
                btnGuardar.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Actualizar';
                btnGuardar.onclick = () => actualizarPagosEgreso();
                setPagosControlesHabilitados(true);
                
                if (['GENERAL', 'ANTICIPO_PROVEEDOR'].includes(comp)) {
                    // Regla del Usuario: Para egresos generales, permitir modificar todo EXCEPTO concepto, serie y secuencial
                    document.getElementById('eg-select-punto').disabled = true;
                    document.getElementById('eg-select-concepto').disabled = true;
                    document.getElementById('eg-input-secuencial').disabled = true;

                    document.getElementById('eg-select-tipo-sujeto').disabled = false; // SÍ modificar tipo de sujeto
                    document.getElementById('eg-search-input').disabled = false;      // SÍ buscar/cambiar sujeto
                    document.getElementById('eg-input-obs').disabled = false;          // SÍ cambiar observaciones
                    document.getElementById('eg-input-fecha').disabled = false;        // SÍ cambiar fecha
                    
                    // Forzar render para habilitar los inputs individuales de la grilla de conceptos manuales
                    renderDocsEgreso();
                } else {
                    // Para egresos ligados a módulos (COMPRA/LIQUIDACION), mantener bloqueo clásico estricto
                    setControlesGeneralesHabilitados(false);
                    document.getElementById('eg-input-fecha').disabled = false; // ¡Fecha sí es modificable!
                }
            }

            renderPagosEgreso();
            recalcEgresoTot();

            if(e.estado !== 'anulado') document.getElementById('eg-footer-ver-extra').classList.remove('d-none');

            // Cargar el asiento contable generado para este egreso
            cargarAsientoContableEgreso(e.id);
        });
    }

    function cargarAsientoContableEgreso(id) {
        const tbody = document.getElementById('eg-tbody-asiento');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-1"></span> Cargando asiento…</td></tr>';
        fetch(`${EGR_URL}/getAsientoContableAjax?id=${id}`)
            .then(r => r.json())
            .then(res => {
                if (!res.ok || !res.asiento || !(res.asiento.detalles || []).length) {
                    tbody.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-muted"><i class="bi bi-exclamation-circle me-1"></i> Aún no se ha generado el asiento contable. Verifique que el concepto y las formas de pago tengan cuenta contable configurada.</td></tr>';
                    return;
                }
                const a = res.asiento;
                let html = '';
                (a.detalles || []).forEach(d => {
                    const debe = parseFloat(d.debe || 0), haber = parseFloat(d.haber || 0);
                    html += `<tr>
                        <td class="ps-3"><code class="text-primary">${d.codigo_cuenta || ''}</code> ${d.nombre_cuenta || ''}</td>
                        <td class="text-end pe-3">${debe > 0 ? debe.toFixed(2) : ''}</td>
                        <td class="text-end pe-3">${haber > 0 ? haber.toFixed(2) : ''}</td>
                    </tr>`;
                });
                html += `<tr class="table-light fw-bold">
                    <td class="ps-3 text-end">TOTALES</td>
                    <td class="text-end pe-3">${parseFloat(a.total_debe || 0).toFixed(2)}</td>
                    <td class="text-end pe-3">${parseFloat(a.total_haber || 0).toFixed(2)}</td>
                </tr>`;
                tbody.innerHTML = html;
            })
            .catch(() => {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center text-danger py-4">Error al cargar el asiento contable.</td></tr>';
            });
    }

    function setControlesGeneralesHabilitados(hab) {
        const els = [
            'eg-select-punto', 'eg-select-concepto',
            'eg-select-tipo-sujeto', 'eg-search-input', 'eg-input-obs'
        ];
        els.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.disabled = !hab;
        });

        // Forzar re-renderizado de la grilla manual para sincronizar botones y inputs bloqueados
        const comp = document.getElementById('eg-input-tipo-egreso').value;
        if (comp === 'GENERAL') {
            renderDocsEgreso();
        }
    }

    function setPagosControlesHabilitados(hab) {
        const els = [
            'eg-add-pago-forma', 'eg-add-pago-monto', 'eg-add-pago-ref',
            'eg-add-pago-tipo-banco', 'eg-add-pago-num-cheque', 'eg-add-pago-fecha-cheque'
        ];
        els.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.disabled = !hab;
        });
        const btnAddFP = document.querySelector('#eg-subtab-pag button.btn-primary');
        if (btnAddFP) {
            if (hab) btnAddFP.classList.remove('d-none');
            else btnAddFP.classList.add('d-none');
        }
    }

    function actualizarPagosEgreso() {
        const id = document.getElementById('eg-input-id').value;
        if (!id) return;

        const sumPag = pagosEgreso.reduce((a, b) => a + b.monto, 0);
        const totalEg = parseFloat(document.getElementById('eg-final-total').innerText.replace('$ ', '')) || 0;
        
        if (Math.abs(sumPag - totalEg) > 0.01) {
            Swal.fire('Inconsistencia', 'La suma de las formas de pago ($' + sumPag.toFixed(2) + ') no coincide con el total del egreso ($' + totalEg.toFixed(2) + ').', 'error');
            return;
        }

        const btn = document.getElementById('btnGuardarEgreso');
        const oldHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Actualizando...';

        const comp = document.getElementById('eg-input-tipo-egreso').value;
        const payload = {
            id: id,
            fecha_emision: document.getElementById('eg-input-fecha').value,
            pagos: pagosEgreso.map(p => ({
                id_forma_pago: p.id_forma,
                monto: p.monto,
                referencia: p.ref,
                tipo_operacion_bancaria: p.tipo_operacion_bancaria,
                numero_cheque: p.numero_cheque,
                fecha_cobro: p.fecha_cobro
            }))
        };

        // Si es egreso general o anticipo a proveedor (entrada manual), integramos y validamos datos extendidos
        if (['GENERAL', 'ANTICIPO_PROVEEDOR'].includes(comp)) {
            const valSujeto = document.getElementById('eg-input-id-sujeto').value;
            if (!valSujeto) {
                Swal.fire('Requerido', 'Debe seleccionar un Beneficiario (Proveedor/Empleado) válido.', 'warning');
                btn.disabled = false;
                btn.innerHTML = oldHtml;
                return;
            }

            // Filtrar conceptos en blanco
            const finalDets = manualEgreso.filter(d => d.desc.trim() !== '' || d.monto > 0);
            if (finalDets.length === 0) {
                Swal.fire('Requerido', 'Debe registrar al menos un concepto y monto válido.', 'warning');
                btn.disabled = false;
                btn.innerHTML = oldHtml;
                return;
            }

            // Validar montos positivos
            if (finalDets.some(d => d.monto <= 0)) {
                Swal.fire('Atención', 'Todos los montos en la cuadrícula deben ser superiores a cero.', 'warning');
                btn.disabled = false;
                btn.innerHTML = oldHtml;
                return;
            }

            const ts = document.getElementById('eg-select-tipo-sujeto').value;
            payload.es_general = true;
            payload.tipo_sujeto = ts;
            payload.id_proveedor = ts === 'PROVEEDOR' ? valSujeto : null;
            payload.id_empleado = ts === 'EMPLEADO' ? valSujeto : null;
            payload.observaciones = document.getElementById('eg-input-obs').value;
            
            payload.detalles = finalDets.map(d => ({
                tipo_documento: 'MANUAL',
                descripcion: d.desc,
                monto_documento: d.monto,
                saldo_anterior: d.monto,
                monto_pagado: d.monto,
                saldo_actual: 0,
                id_cuenta_contable: d.id_cuenta || null
            }));

            // Recalcular el monto_total de los conceptos para enviarlo a la API
            payload.monto_total = payload.detalles.reduce((a, b) => a + b.monto_pagado, 0);
        }

        fetch(`${EGR_URL}/actualizarPagosAjax`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'data=' + encodeURIComponent(JSON.stringify(payload))
        })
        .then(async r => {
            const text = await r.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error("El servidor no devolvió un JSON válido. Respuesta: " + text.substring(0, 250));
            }
        })
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
            if (res.ok) {
                bootstrap.Modal.getInstance(document.getElementById('modalNuevoEgreso')).hide();
                window.EGR_fetchSearch(1);
                Swal.fire('Éxito', res.mensaje, 'success');
            } else {
                Swal.fire('Error al actualizar', res.mensaje, 'error');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
            Swal.fire('Error del Servidor / Red', err.message, 'error');
        });
    }

    function anularEgreso() {
        const id = document.getElementById('eg-input-id').value;
        if (!id) return;
        Swal.fire({
            title: '¿Anular Egreso?',
            text: 'Esta acción anulará el egreso y no podrá deshacerse.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, anular',
            cancelButtonText: 'Cancelar'
        }).then(result => {
            if (result.isConfirmed) {
                fetch(`${EGR_URL}/anularAjax`, {
                    method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: 'id=' + id
                }).then(r => r.json()).then(res => {
                    if (res.ok) {
                        Swal.fire('Anulado', res.mensaje, 'success').then(() => {
                            bootstrap.Modal.getInstance(document.getElementById('modalNuevoEgreso'))?.hide();
                            window.EGR_fetchSearch(1);
                        });
                    } else {
                        Swal.fire('Error', res.mensaje, 'error');
                    }
                });
            }
        });
    }

    // Global para orden
    window.currentSort = '<?= $ordenCol ?>';
    window.currentDir = '<?= $ordenDir ?>';
    window.EGR_cambiarPaginaAjax = (p) => window.EGR_fetchSearch(p);

    window.EGR_fetchSearch = async function(p = 1) {
        const b = document.getElementById('buscarEgreso').value.trim();
        document.getElementById('tbodyEgresos').innerHTML = '<tr><td colspan="7" class="text-center py-5"><span class="spinner-border text-primary"></span></td></tr>';
        try {
            const res = await (await fetch(`${EGR_URL}/searchAjax?b=${encodeURIComponent(b)}&page=${p}&sort=${window.currentSort}&dir=${window.currentDir}`)).json();
            document.getElementById('tbodyEgresos').innerHTML = res.rows;
            document.getElementById('paginationContainer').innerHTML = res.pagination;
            document.getElementById('paginationInfo').innerText = res.info;
            
            // Actualizar iconos visuales sort headers
            document.querySelectorAll('.sortable-header').forEach(th => {
                const icon = th.querySelector('i');
                if(icon && th.dataset.sort === window.currentSort) icon.className = (window.currentDir.toLowerCase()==='asc') ? 'bi bi-sort-alpha-down text-primary ms-1' : 'bi bi-sort-alpha-up text-primary ms-1';
                else if(icon) icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
            });
        } catch(e){ console.error(e); }
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.sortable-header').forEach(h => {
            h.addEventListener('click', () => {
                const f = h.dataset.sort;
                window.currentDir = (window.currentSort === f && window.currentDir.toLowerCase() === 'asc') ? 'DESC' : 'ASC';
                window.currentSort = f;
                
                if (typeof window.guardarOrdenacionVista === 'function') {
                    window.guardarOrdenacionVista('egresos', f, window.currentDir);
                }
                
                window.EGR_fetchSearch(1);
            });
        });
        const iB = document.getElementById('buscarEgreso');
        let tSrc; if(iB) iB.addEventListener('input', () => { clearTimeout(tSrc); tSrc = setTimeout(()=>window.EGR_fetchSearch(1), 400); });
    });

    // ── Funciones del modal secundario: docs pendientes de pago ──────────────

    function abrirModalEgDocsPendientes(tipoBehav) {
        _egSelModal      = {};
        _egDocsModal     = [];
        _egTipoDocActual = tipoBehav;

        const titulos = {
            COMPRA:      'Facturas de Compra - Pendientes de Pago',
            LIQUIDACION: 'Liquidaciones de Compra - Pendientes de Pago',
            ROL:         'Nómina - Roles y Anticipos Pendientes de Pago'
        };
        const el = document.getElementById('eg-sdp-titulo');
        if (el) el.textContent = titulos[tipoBehav] || 'Documentos Pendientes de Pago';
        // La columna "sujeto" muestra Empleado para nómina, Proveedor para compras.
        const thSuj = document.getElementById('eg-sdp-th-suj');
        if (thSuj) thSuj.textContent = tipoBehav === 'ROL' ? 'Empleado' : 'Proveedor';

        const inpBuscar = document.getElementById('eg-sdp-buscar');
        if (inpBuscar) inpBuscar.value = '';

        // Reset del modo "Pagar por ítems"; solo aplica a COMPRA/LIQUIDACION (documentos con ítems).
        _egModoItems = false;
        _egSelItems  = {};
        _egItemsModal = [];
        const swWrap = document.getElementById('eg-sdp-modo-items-wrap');
        const sw     = document.getElementById('eg-sdp-modo-items');
        if (sw) sw.checked = false;
        if (swWrap) swWrap.classList.toggle('d-none', !['COMPRA', 'LIQUIDACION'].includes(tipoBehav));

        actualizarResumenModalEg();

        const modalEl = document.getElementById('modalEgDocsPendientes');
        document.body.appendChild(modalEl);
        modalEl.style.zIndex = '1080';
        bootstrap.Modal.getOrCreateInstance(modalEl).show();

        // Cargar todos los documentos pendientes al abrir
        buscarEnModalEgDocsPendientes('');
    }

    function buscarEnModalEgDocsPendientes(q) {
        const tbody     = document.getElementById('eg-sdp-tbody');
        const excluirId = document.getElementById('eg-input-id')?.value || '';
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Buscando...</td></tr>';

        let uri = `${EGR_URL}/buscarDocumentosPendientesEgresoAjax?q=${encodeURIComponent(q)}&tipo=${encodeURIComponent(_egTipoDocActual)}`;
        if (excluirId) uri += `&excluir_egreso_id=${encodeURIComponent(excluirId)}`;

        fetch(uri)
            .then(r => r.json())
            .then(res => {
                if (!res.ok) {
                    tbody.innerHTML = `<tr><td colspan="7" class="text-center py-3 text-danger small"><i class="bi bi-exclamation-triangle me-1"></i>${res.mensaje || 'Error al buscar documentos.'}</td></tr>`;
                    return;
                }
                _egDocsModal = res.data || [];
                _egDocsHasMore = !!res.has_more;
                if (_egModoItems) cargarEgItemsYRender();
                else renderTablaEgDocsPendientes(_egDocsModal, _egDocsHasMore);
            })
            .catch(err => {
                console.error('buscarDocumentosPendientesEgresoAjax:', err);
                tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-danger small"><i class="bi bi-exclamation-triangle me-1"></i>Error de comunicación con el servidor. Revise la consola.</td></tr>';
            });
    }

    function renderTablaEgDocsPendientes(docs, hasMore) {
        const tbody = document.getElementById('eg-sdp-tbody');
        setHeadersModoEg('doc');
        tbody.innerHTML = '';

        if (docs.length === 0) {
            const tipo = _egTipoDocActual === 'COMPRA' ? 'facturas de compra' : _egTipoDocActual === 'LIQUIDACION' ? 'liquidaciones de compras' : _egTipoDocActual === 'ROL' ? 'roles de pago' : 'documentos';
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-muted"><i class="bi bi-inbox fs-4 d-block mb-1"></i>No hay ${tipo} pendientes de pago.</td></tr>`;
            actualizarResumenModalEg();
            return;
        }

        docs.forEach(doc => {
            // Clave compuesta: rol_detalle.id y novedades.id pueden colisionar en el modal mixto.
            const uid        = doc.tipo_doc_bd + '_' + doc.id;
            const yaAgregado = docsEgreso.some(d => d.id == doc.id && d.tipo_bd == doc.tipo_doc_bd && d.seleccionado);
            const saldo      = parseFloat(doc.saldo_pendiente);
            const checked    = _egSelModal[uid] !== undefined;
            const montoPrev  = checked ? _egSelModal[uid] : saldo;

            const creditoBadge = (() => {
                if (!doc.fecha_emision) return '<span class="text-muted">-</span>';
                const hoy = new Date(); hoy.setHours(0, 0, 0, 0);
                const fEm = new Date(doc.fecha_emision + 'T00:00:00');
                const dias = Math.floor((hoy - fEm) / 86400000);
                const diasCred = parseInt(doc.dias_credito) || 0;
                if (diasCred > 0 && dias <= diasCred) {
                    return `<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">${dias} días</span>`;
                }
                const vencido = diasCred > 0 ? dias - diasCred : dias;
                return `<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Vencido ${vencido} días</span>`;
            })();

            const tr = document.createElement('tr');
            tr.className = yaAgregado ? 'table-success' : '';
            if (!yaAgregado) {
                tr.style.cursor = 'pointer';
                tr.addEventListener('click', function (e) {
                    if (e.target.closest('input[type="checkbox"]') || e.target.closest('input[type="number"]')) return;
                    const chk = this.querySelector('.eg-sdp-chk');
                    if (!chk) return;
                    chk.checked = !chk.checked;
                    toggleEgDocModal(uid, chk.checked, saldo);
                });
            }

            tr.innerHTML = `
                <td class="text-center ps-2">
                    ${yaAgregado
                        ? '<i class="bi bi-check-circle-fill text-success" title="Ya agregado"></i>'
                        : `<input type="checkbox" class="form-check-input eg-sdp-chk" data-id="${uid}"
                               ${checked ? 'checked' : ''}
                               onchange="toggleEgDocModal('${uid}', this.checked, ${saldo})">`
                    }
                </td>
                <td><code class="text-primary small">${doc.numero_documento}</code></td>
                <td class="small text-truncate" style="max-width:160px;" title="${(doc.proveedor_nombre || '').replace(/"/g,'&quot;')}">${doc.proveedor_nombre || '-'}</td>
                <td class="small">${doc.fecha_emision ? doc.fecha_emision.split('-').reverse().join('/') : ''}</td>
                <td class="small">${creditoBadge}</td>
                <td class="text-end small">$${parseFloat(doc.monto_total).toFixed(2)}</td>
                <td class="text-end">
                    ${yaAgregado
                        ? `<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 small">Agregado</span>`
                        : `<input type="number" class="form-control form-control-sm text-end eg-sdp-monto px-1"
                               data-id="${uid}" data-saldo="${saldo}"
                               style="height:26px;font-size:0.8rem;width:90px;"
                               step="0.01" min="0.01" max="${saldo}"
                               value="${montoPrev.toFixed(2)}"
                               ${checked ? '' : 'disabled'}
                               oninput="actualizarMontoEgModal('${uid}', this)">`
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

        actualizarResumenModalEg();
    }

    // Autocompleta el sujeto del egreso desde un documento del modal, si aún no hay uno.
    function egAutocompletarSujeto(doc) {
        if (!doc || !doc.proveedor_id) return;
        const campoId = document.getElementById('eg-input-id-sujeto');
        if (campoId.value) return; // ya hay datos ingresados → no sobreescribir
        const esNomina = _egTipoDocActual === 'ROL';
        document.getElementById('eg-select-tipo-sujeto').value = esNomina ? 'EMPLEADO' : 'PROVEEDOR';
        campoId.value = doc.proveedor_id;
        document.getElementById('eg-search-input').value = doc.proveedor_nombre || '';
        const lblSuj = document.getElementById('eg-lbl-search');
        if (lblSuj) lblSuj.innerText = esNomina ? 'Buscar Empleado' : 'Buscar Proveedor';
        if (typeof EGR_renderSaldoForma === 'function') EGR_renderSaldoForma();
    }

    function toggleEgDocModal(uid, checked, saldo) {
        const inputMonto = document.querySelector(`.eg-sdp-monto[data-id="${uid}"]`);
        const doc = _egDocsModal.find(d => (d.tipo_doc_bd + '_' + d.id) === uid);
        if (checked) {
            // Nómina: un egreso es por empleado. Evitar mezclar líneas de empleados distintos.
            if (_egTipoDocActual === 'ROL') {
                const campoId = document.getElementById('eg-input-id-sujeto');
                if (campoId.value && doc && String(campoId.value) !== String(doc.proveedor_id)) {
                    const chk = document.querySelector(`.eg-sdp-chk[data-id="${uid}"]`);
                    if (chk) chk.checked = false;
                    Swal.fire('Un empleado por egreso', 'Este egreso ya corresponde a otro empleado. Registre un egreso independiente para cada empleado.', 'warning');
                    return;
                }
            }
            const monto = inputMonto ? parseFloat(inputMonto.value) || saldo : saldo;
            _egSelModal[uid] = monto;
            if (inputMonto) inputMonto.disabled = false;
            // Nómina: al seleccionar la línea, autocompletar el empleado en el egreso.
            if (_egTipoDocActual === 'ROL') egAutocompletarSujeto(doc);
        } else {
            delete _egSelModal[uid];
            if (inputMonto) inputMonto.disabled = true;
        }
        actualizarResumenModalEg();
    }

    function actualizarMontoEgModal(uid, input) {
        const saldo = parseFloat(input.dataset.saldo);
        let val = parseFloat(input.value);
        if (isNaN(val) || val <= 0) val = 0;
        if (val > saldo) { val = saldo; input.value = saldo.toFixed(2); }
        _egSelModal[uid] = val;
        actualizarResumenModalEg();
    }

    // ── Pago por ítems (egresos): switch global ───────────────────────────────
    function toggleEgModoItems(on) {
        _egModoItems = !!on;
        if (_egModoItems) { _egSelModal = {}; cargarEgItemsYRender(); }
        else { _egSelItems = {}; renderTablaEgDocsPendientes(_egDocsModal, _egDocsHasMore); }
    }

    function setHeadersModoEg(modo) {
        const hDoc = document.getElementById('eg-sdp-th-doc');
        const hCre = document.getElementById('eg-sdp-th-credito');
        const hTot = document.getElementById('eg-sdp-th-total');
        if (!hDoc) return;
        if (modo === 'items') { hDoc.textContent = 'Ítem'; hCre.textContent = 'Documento'; hTot.textContent = 'Total ítem'; }
        else { hDoc.textContent = 'Nº Documento'; hCre.textContent = 'Crédito'; hTot.textContent = 'Total Doc.'; }
    }

    async function cargarEgItemsYRender() {
        const tbody = document.getElementById('eg-sdp-tbody');
        setHeadersModoEg('items');
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Cargando ítems…</td></tr>';
        _egItemsModal = [];

        await Promise.all((_egDocsModal || []).map(async doc => {
            const uid   = doc.tipo_doc_bd + '_' + doc.id;
            const saldo = parseFloat(doc.saldo_pendiente);
            const ep    = (doc.tipo_doc_bd === 'LIQUIDACION') ? 'liquidacion-compra/getLiquidacionAjax' : 'compras/getCompraAjax';
            try {
                const res  = await (await fetch(`<?= BASE_URL ?>/modulos/${ep}?id=${doc.id}`)).json();
                const dets = res.detalles || (res.data && res.data.detalles) || [];
                dets.forEach((d, idx) => {
                    const cant  = parseFloat(d.cantidad || 0);
                    const base  = parseFloat(d.precio_total_sin_impuesto || d.subtotal || (cant * parseFloat(d.precio_unitario || 0)) || 0);
                    const imp   = (d.impuestos || []).reduce((a, x) => a + parseFloat(x.valor || 0), 0);
                    const tItem = Math.round((base + imp) * 100) / 100;
                    _egItemsModal.push({ iuid: uid + '#' + idx, docUid: uid, docId: doc.id, tipo_bd: doc.tipo_doc_bd,
                        numero_documento: doc.numero_documento, proveedor_nombre: doc.proveedor_nombre, proveedor_id: doc.proveedor_id,
                        fecha: doc.fecha_emision, saldo_doc: saldo, desc: (d.descripcion || d.producto_nombre || 'Ítem'), cant, total_item: tItem });
                });
            } catch (e) { /* documento sin ítems accesibles */ }
        }));

        renderEgItemsModal();
    }

    function renderEgItemsModal() {
        const tbody = document.getElementById('eg-sdp-tbody');
        tbody.innerHTML = '';
        if (_egItemsModal.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted"><i class="bi bi-inbox fs-4 d-block mb-1"></i>No hay ítems pendientes.</td></tr>';
            actualizarResumenModalEg();
            return;
        }
        _egItemsModal.forEach(it => {
            const iuid    = it.iuid;
            const yaDoc   = docsEgreso.some(d => d.id == it.docId && d.tipo_bd == it.tipo_bd && d.seleccionado);
            const checked = _egSelItems[iuid] !== undefined;
            const montoPrev = _egSelItems[iuid] !== undefined ? _egSelItems[iuid] : it.total_item;
            const desc = String(it.desc).replace(/</g, '&lt;');

            const tr = document.createElement('tr');
            tr.className = yaDoc ? 'table-success' : '';
            if (!yaDoc) {
                tr.style.cursor = 'pointer';
                tr.addEventListener('click', function (e) {
                    if (e.target.closest('input')) return;
                    const chk = this.querySelector('.eg-item-chk');
                    if (!chk) return;
                    chk.checked = !chk.checked;
                    toggleEgItemModal(iuid, chk.checked);
                });
            }
            tr.innerHTML = `
                <td class="text-center ps-2">
                    ${yaDoc
                        ? '<i class="bi bi-check-circle-fill text-success" title="Documento ya agregado"></i>'
                        : `<input type="checkbox" class="form-check-input eg-item-chk" data-iuid="${iuid}" ${checked ? 'checked' : ''} onchange="toggleEgItemModal('${iuid}', this.checked)">`
                    }
                </td>
                <td class="small"><strong>${desc}</strong>${it.cant ? ` <span class="text-muted">(x${it.cant})</span>` : ''}</td>
                <td class="small text-truncate" style="max-width:150px;">${it.proveedor_nombre || ''}</td>
                <td class="small">${it.fecha ? it.fecha.split('-').reverse().join('/') : ''}</td>
                <td class="small"><code class="text-primary">${it.numero_documento}</code></td>
                <td class="text-end small">$${it.total_item.toFixed(2)}</td>
                <td class="text-end">
                    ${yaDoc
                        ? `<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 small">Agregado</span>`
                        : `<input type="number" class="form-control form-control-sm text-end eg-item-monto px-1" data-iuid="${iuid}"
                               style="height:26px;font-size:0.8rem;width:90px;" step="0.01" min="0.01" max="${it.total_item.toFixed(2)}"
                               value="${checked ? montoPrev.toFixed(2) : it.total_item.toFixed(2)}"
                               ${checked ? '' : 'disabled'} oninput="actualizarEgItemMonto('${iuid}', this)">`
                    }
                </td>`;
            tbody.appendChild(tr);
        });
        if (_egDocsHasMore) {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td colspan="7" class="text-center small text-muted py-2 bg-light border-top"><i class="bi bi-info-circle me-1"></i>Hay más de 300 documentos. Refine la búsqueda para ver más.</td>`;
            tbody.appendChild(tr);
        }
        actualizarResumenModalEg();
    }

    function egDocSaldoRestante(docUid_, exceptIuid) {
        const it0 = _egItemsModal.find(x => x.docUid === docUid_);
        const saldo = it0 ? it0.saldo_doc : Infinity;
        let usado = 0;
        Object.keys(_egSelItems).forEach(k => {
            if (k === exceptIuid) return;
            const it = _egItemsModal.find(x => x.iuid === k);
            if (it && it.docUid === docUid_) usado += _egSelItems[k];
        });
        return Math.max(0, saldo - usado);
    }

    function toggleEgItemModal(iuid, checked) {
        const it = _egItemsModal.find(x => x.iuid === iuid);
        if (!it) return;
        const input = document.querySelector(`.eg-item-monto[data-iuid="${iuid}"]`);
        if (checked) {
            const monto = Math.min(it.total_item, egDocSaldoRestante(it.docUid, iuid));
            _egSelItems[iuid] = monto;
            if (input) { input.disabled = false; input.value = monto.toFixed(2); }
        } else {
            delete _egSelItems[iuid];
            if (input) input.disabled = true;
        }
        actualizarResumenModalEg();
    }

    function actualizarEgItemMonto(iuid, input) {
        const it = _egItemsModal.find(x => x.iuid === iuid);
        if (!it) return;
        let v = parseFloat(input.value);
        if (isNaN(v) || v < 0) v = 0;
        if (v > it.total_item) v = it.total_item;
        const tope = egDocSaldoRestante(it.docUid, iuid);
        if (v > tope) v = tope;
        input.value = v > 0 ? v.toFixed(2) : '';
        _egSelItems[iuid] = v;
        actualizarResumenModalEg();
    }

    function actualizarResumenModalEg() {
        let n, total;
        if (_egModoItems) {
            const ids = Object.keys(_egSelItems);
            n = ids.length; total = ids.reduce((s, k) => s + (_egSelItems[k] || 0), 0);
        } else {
            const ids = Object.keys(_egSelModal);
            n = ids.length; total = ids.reduce((s, id) => s + (_egSelModal[id] || 0), 0);
        }
        const lblSel = document.getElementById('eg-sdp-lbl-sel');
        const lblTot = document.getElementById('eg-sdp-lbl-total');
        if (lblSel) lblSel.textContent = n;
        if (lblTot) lblTot.textContent = `$${total.toFixed(2)}`;
    }

    function confirmarSeleccionEgDocsPendientes() {
        // Mapa docUid → monto y desglose de ítems (según modo).
        const montosPorDoc = {};
        const itemsPorDoc  = {};
        if (_egModoItems) {
            Object.keys(_egSelItems).forEach(iuid => {
                const it = _egItemsModal.find(x => x.iuid === iuid);
                if (!it || (_egSelItems[iuid] || 0) <= 0.0001) return;
                montosPorDoc[it.docUid] = (montosPorDoc[it.docUid] || 0) + (_egSelItems[iuid] || 0);
                (itemsPorDoc[it.docUid] = itemsPorDoc[it.docUid] || []).push({ desc: it.desc, pagado: _egSelItems[iuid] || 0, total: it.total_item });
            });
        } else {
            Object.keys(_egSelModal).forEach(uid => { montosPorDoc[uid] = _egSelModal[uid]; });
        }

        const uids = Object.keys(montosPorDoc).filter(u => (montosPorDoc[u] || 0) > 0.0001);
        if (uids.length === 0) {
            Swal.fire('Atención', 'Seleccione al menos un documento o ítem para continuar.', 'warning');
            return;
        }

        let agregados     = 0;
        let proveedorId   = '';
        let proveedorNom  = '';

        uids.forEach(uid => {
            const doc = _egDocsModal.find(d => (d.tipo_doc_bd + '_' + d.id) === uid);
            if (!doc) return;
            if (docsEgreso.some(d => d.id == doc.id && d.tipo_bd == doc.tipo_doc_bd)) return; // evitar duplicados

            const saldo = parseFloat(doc.saldo_pendiente);
            const monto = Math.min(montosPorDoc[uid] || saldo, saldo);
            docsEgreso.push({
                id:           doc.id,
                tipo_bd:      doc.tipo_doc_bd,
                numero:       doc.numero_documento,
                fecha:        doc.fecha_emision,
                total:        parseFloat(doc.monto_total),
                pendiente:    saldo,
                seleccionado: true,
                pagado:       monto,
                items:        itemsPorDoc[uid] || null
            });

            // Capturar el sujeto (proveedor o empleado) del primer documento seleccionado
            if (!proveedorId && doc.proveedor_id) {
                proveedorId  = doc.proveedor_id;
                proveedorNom = doc.proveedor_nombre || '';
            }
            agregados++;
        });

        // Auto-rellenar el sujeto en el formulario principal si no había uno seleccionado.
        const esNomina = _egTipoDocActual === 'ROL';
        const campoIdSujeto = document.getElementById('eg-input-id-sujeto');
        if (proveedorId && !campoIdSujeto.value) {
            document.getElementById('eg-select-tipo-sujeto').value = esNomina ? 'EMPLEADO' : 'PROVEEDOR';
            campoIdSujeto.value = proveedorId;
            document.getElementById('eg-search-input').value = proveedorNom;
            const lblSuj = document.getElementById('eg-lbl-search');
            if (lblSuj) lblSuj.innerText = esNomina ? 'Buscar Empleado' : 'Buscar Proveedor';
        }

        renderDocsEgreso();
        recalcEgresoTot();

        bootstrap.Modal.getInstance(document.getElementById('modalEgDocsPendientes'))?.hide();

        if (agregados > 0 && typeof showToast === 'function') {
            showToast(`Se agregaron ${agregados} documento(s) al egreso.`, 'success');
        }
    }

    // ─── Callbacks globales para modales compartidos ──────────────────────────
    window.modalCrearFormaPago = function () { abrirModalFP(); };
    window.onFormaPagoCreada = function (id, nombre) {
        Swal.fire({ icon: 'success', title: '¡Creada!', text: `Forma de pago "${nombre}" creada correctamente.`, timer: 2000, showConfirmButton: false });
    };

    window.modalCrearOpcionEgreso = function () {
        const rdoEgr = document.getElementById('oie-rdo-egreso');
        if (rdoEgr) rdoEgr.checked = true;
        abrirModalOpcion();
    };
    window.onOpcionCreada = function (id, nombre, comportamiento) {
        comportamiento = comportamiento || 'GENERAL';
        // Agregar al select oculto (fuente de verdad del concepto seleccionado)
        const sel = document.getElementById('eg-select-concepto');
        if (sel) {
            const opt = document.createElement('option');
            opt.value = id;
            opt.dataset.comportamiento = comportamiento;
            opt.textContent = nombre;
            sel.appendChild(opt);
        }

        if (comportamiento === 'GENERAL') {
            // Concepto sin relación con módulos → al selector
            const selGen = document.getElementById('eg-select-concepto-general');
            if (selGen) {
                const optG = document.createElement('option');
                optG.value = id;
                optG.textContent = nombre;
                selGen.appendChild(optG);
            }
        } else {
            // Concepto relacionado con un módulo → botón dinámico
            const grp = document.getElementById('concepto-egreso-btns-group');
            if (grp) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-sm btn-outline-secondary concepto-egreso-btn';
                btn.dataset.id = id;
                btn.dataset.comportamiento = comportamiento;
                btn.textContent = nombre;
                btn.addEventListener('click', function () {
                    const selEl = document.getElementById('eg-select-concepto');
                    if (selEl.value == this.dataset.id) return;
                    const hayDatos = docsEgreso.length > 0 || manualEgreso.some(m => m.desc.trim() !== '' || m.monto > 0) || pagosEgreso.length > 0;
                    const aplicarCambio = () => { selEl.value = this.dataset.id; manejarCambioConceptoEgreso(selEl); };
                    if (hayDatos) {
                        Swal.fire({
                            title: '¿Cambiar concepto?',
                            html: 'Si cambia el concepto, se eliminarán los documentos o detalles ya cargados en este egreso.',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Sí, cambiar',
                            cancelButtonText: 'Cancelar',
                            confirmButtonColor: '#d33'
                        }).then(r => { if (r.isConfirmed) aplicarCambio(); });
                    } else { aplicarCambio(); }
                });
                // Insertar antes del selector general para conservar el orden
                const selGen = document.getElementById('eg-select-concepto-general');
                if (selGen) grp.insertBefore(btn, selGen); else grp.appendChild(btn);
            }
        }
        Swal.fire({ icon: 'success', title: '¡Creada!', text: `Opción de egreso "${nombre}" creada correctamente.`, timer: 2000, showConfirmButton: false });
    };

    window.modalCrearProveedor = function () {
        if (typeof window.abrirModalProveedorCrear === 'function') {
            window.abrirModalProveedorCrear();
        } else {
            const modalEl = document.getElementById('modalProveedor');
            if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    };

    window.modalCrearEmpleado = function () {
        if (typeof window.abrirModalCrear === 'function') {
            window.abrirModalCrear();
        } else {
            const modalEl = document.getElementById('modalEmpleado');
            if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    };

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
            <div class="flex-grow-1 overflow-auto p-3" style="max-height: calc(100dvh - 260px);">
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

<!-- ── Modal Secundario: Selección de Documentos Pendientes de Pago ─────────── -->
<div class="modal fade" id="modalEgDocsPendientes" tabindex="-1" aria-labelledby="modalEgDocsPendientesLabel">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-md-down">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold" id="modalEgDocsPendientesLabel">
                    <i class="bi bi-receipt text-primary me-2"></i>
                    <span id="eg-sdp-titulo">Documentos Pendientes de Pago</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body p-0 d-flex flex-column" style="min-height: 420px;">
                <!-- Barra de búsqueda única -->
                <div class="px-3 pt-3 pb-2 border-bottom bg-light bg-opacity-50 d-flex align-items-center gap-2 flex-wrap">
                    <div class="input-group input-group-sm flex-grow-1" style="min-width:240px;">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" id="eg-sdp-buscar" class="form-control"
                               placeholder="Buscar por Nº documento, proveedor o RUC..."
                               oninput="clearTimeout(window._timerEgDocsBuscar); window._timerEgDocsBuscar = setTimeout(() => buscarEnModalEgDocsPendientes(this.value.trim()), 350)">
                        <button type="button" class="btn btn-outline-secondary"
                                onclick="buscarEnModalEgDocsPendientes(document.getElementById('eg-sdp-buscar').value.trim())"
                                title="Buscar">
                            <i class="bi bi-search"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary"
                                onclick="document.getElementById('eg-sdp-buscar').value=''; buscarEnModalEgDocsPendientes('')"
                                title="Limpiar">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="form-check form-switch mb-0 text-nowrap d-none" id="eg-sdp-modo-items-wrap" title="Desglosa los ítems de cada documento para pagar por ítem">
                        <input class="form-check-input" type="checkbox" role="switch" id="eg-sdp-modo-items" onchange="toggleEgModoItems(this.checked)">
                        <label class="form-check-label small fw-bold text-secondary" for="eg-sdp-modo-items"><i class="bi bi-list-check me-1"></i>Pagar por ítems</label>
                    </div>
                </div>

                <!-- Tabla de documentos -->
                <div class="flex-grow-1 overflow-auto px-0">
                    <table class="table table-sm table-hover mb-0" style="font-size:0.82rem;">
                        <thead class="table-light sticky-top" style="top:0; z-index:1;">
                            <tr>
                                <th class="text-center ps-2" style="width:42px;"></th>
                                <th style="min-width:140px;" id="eg-sdp-th-doc">Nº Documento</th>
                                <th style="min-width:160px;" id="eg-sdp-th-suj">Proveedor</th>
                                <th style="min-width:90px;">Fecha</th>
                                <th style="min-width:100px;" id="eg-sdp-th-credito">Crédito</th>
                                <th class="text-end" style="min-width:90px;" id="eg-sdp-th-total">Total Doc.</th>
                                <th class="text-end" style="min-width:110px;">Monto a Pagar</th>
                            </tr>
                        </thead>
                        <tbody id="eg-sdp-tbody">
                            <tr><td colspan="7" class="text-center py-4 text-muted">
                                <span class="spinner-border spinner-border-sm me-2"></span>Cargando...
                            </td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="modal-footer justify-content-between bg-light border-top p-2 align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3 small text-muted">
                    <span>
                        <i class="bi bi-check2-square me-1 text-primary"></i>
                        Seleccionados: <strong id="eg-sdp-lbl-sel" class="text-dark">0</strong>
                    </span>
                    <span>
                        <i class="bi bi-cash-coin me-1 text-success"></i>
                        Total: <strong id="eg-sdp-lbl-total" class="text-dark">$0.00</strong>
                    </span>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-primary btn-sm px-4" onclick="confirmarSeleccionEgDocsPendientes()">
                        <i class="bi bi-plus-circle me-1"></i> Agregar seleccionados
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// ─── Modales compartidos ─────────────────────────────────────────────────────

// 1. Forma de Pago
include_once MVC_APP . '/views/modulos/formas_cobros_pagos/modal_forma_pago.php';

// 2. Opción de Egreso (reutiliza modal de opciones_ingreso_egreso)
$urlBase = BASE_URL . '/modulos/opciones_ingreso_egreso';
$permOIE = $perm;
$perm    = ['ver' => true, 'crear' => true, 'actualizar' => true, 'eliminar' => true, 'todo' => true];
include_once MVC_APP . '/views/modulos/opciones_ingreso_egreso/modal_opcion.php';
$perm = $permOIE;

// 3. Proveedor
include_once MVC_APP . '/views/modulos/proveedores/modal_proveedor.php';

// 4. Empleado
include_once MVC_APP . '/views/modulos/empleados/modal_empleado.php';
?>

<!-- Variable global BASE_URL requerida por los JS externos de modales compartidos -->
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

<!-- JS para modales de proveedores y empleados (expone abrirModalProveedorCrear / abrirModalCrear) -->
<script src="<?= BASE_URL ?>/js/modulos/proveedores_modal.js?v=<?= time() ?>"></script>
<script src="<?= BASE_URL ?>/js/modulos/empleados_modal.js?v=<?= time() ?>"></script>
