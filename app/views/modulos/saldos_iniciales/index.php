<?php
/** @var array  $perm */
/** @var string $rutaModulo */
/** @var array  $vistaConfig */

$base    = BASE_URL;
$urlBase = $base . '/' . $rutaModulo;
?>

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<style>
    .si-header { flex-shrink: 0; }

    .si-scroll {
        max-height: calc(100dvh - 290px);
        overflow-y: auto;
    }
    .si-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }

    /* scroll bancos dentro de tab */
    .si-bancos-scroll {
        max-height: calc(100dvh - 320px);
        overflow-y: auto;
    }

    .si-row { cursor: default; }
    .si-row:hover { background-color: rgba(0,0,0,.03); }

    .badge-tipo {
        font-size: .6rem;
        padding: 2px 6px;
        background: rgba(13,110,253,.1);
        color: #0d6efd;
        border: 1px solid rgba(13,110,253,.2);
        border-radius: 4px;
    }
</style>

<!-- ── Cabecera ── -->
<div class="si-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0 fw-bold">
            <i class="bi bi-database-add me-2 text-warning"></i>Saldos Iniciales
        </h5>
        <small class="text-muted">Carga de saldos previos al inicio de operaciones en este sistema</small>
    </div>
</div>

<!-- ── Tabs Bootstrap ── -->
<ul class="nav nav-tabs mb-0 border-bottom-0" id="siTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active fw-semibold" id="tab-cxc-btn"
                data-bs-toggle="tab" data-bs-target="#tab-cxc" type="button" role="tab"
                onclick="SI_onTabCxc()">
            <i class="bi bi-wallet2 me-1 text-success"></i>Cuentas por Cobrar
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold" id="tab-cxp-btn"
                data-bs-toggle="tab" data-bs-target="#tab-cxp" type="button" role="tab"
                onclick="SI_onTabCxp()">
            <i class="bi bi-receipt me-1 text-primary"></i>Cuentas por Pagar
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold" id="tab-bancos-btn"
                data-bs-toggle="tab" data-bs-target="#tab-bancos" type="button" role="tab"
                onclick="SI_onTabBancos()">
            <i class="bi bi-bank me-1 text-warning"></i>Bancos y Tarjetas
        </button>
    </li>
</ul>

<div class="tab-content">

    <!-- ══════════ TAB CXC ══════════ -->
    <div class="tab-pane fade show active" id="tab-cxc" role="tabpanel">
        <div class="card cmg-table-card w-100 border-0 shadow-sm rounded-top-0 rounded-bottom-3">

            <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">

                <!-- Izquierda: acciones -->
                <div class="d-flex align-items-center gap-2">
                    <?php if ($perm['crear'] ?? false): ?>
                    <button class="btn btn-success btn-sm px-3 shadow-sm" onclick="SI_abrirModalCxc()">
                        <i class="bi bi-plus-lg me-1"></i>Nuevo
                    </button>
                    <label class="btn btn-outline-success btn-sm mb-0" title="Importar Excel">
                        <i class="bi bi-file-earmark-excel me-1"></i>Importar
                        <input type="file" id="input-import-cxc" accept=".xlsx,.xls" class="d-none" onchange="SI_importarCxc(this)">
                    </label>
                    <?php endif; ?>
                    <button class="btn btn-outline-secondary btn-sm" onclick="SI_descargarTemplateCxc()" title="Descargar plantilla Excel">
                        <i class="bi bi-download me-1"></i>Plantilla
                    </button>

                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-danger" onclick="SI_exportarCxcPdf()" title="PDF">
                            <i class="bi bi-file-earmark-pdf"></i>
                        </button>
                        <button class="btn btn-outline-success" onclick="SI_exportarCxcExcel()" title="Excel">
                            <i class="bi bi-file-earmark-spreadsheet"></i>
                        </button>
                    </div>
                </div>

                <!-- Derecha: filtros + buscador + contador -->
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <select id="si-cxc-estado" class="form-select form-select-sm shadow-none border" style="width:160px;" onchange="SI_cargarCxc()">
                        <option value="TODOS">Todos los estados</option>
                        <option value="PENDIENTE" selected>Pendiente</option>
                        <option value="PARCIAL">Parcial</option>
                        <option value="PAGADO">Pagado</option>
                    </select>
                    <span class="text-muted small fw-medium" id="si-cxc-count">0 registros</span>
                    <div class="input-group input-group-sm" style="width:200px;">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="search" class="form-control border-start-0 shadow-none ps-0"
                               id="si-cxc-buscar" placeholder="Filtrar…" oninput="SI_filtrarCxc(this.value)">
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="si-scroll">
                    <table class="table table-hover table-sm mb-0 align-middle" style="table-layout:fixed;min-width:820px;">
                        <colgroup>
                            <col style="width:150px;">
                            <col>
                            <col style="width:105px;">
                            <col style="width:115px;">
                            <col style="width:105px;">
                            <col style="width:105px;">
                            <col style="width:105px;">
                            <col style="width:90px;">
                            <col style="width:100px;">
                        </colgroup>
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Documento</th>
                                <th>Cliente / RUC</th>
                                <th class="text-center">F.Emisión</th>
                                <th class="text-center">F.Vencimiento</th>
                                <th class="text-end">Saldo Inicial</th>
                                <th class="text-end">Cobrado</th>
                                <th class="text-end pe-2">Pendiente</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center pe-3">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="si-cxc-tbody">
                            <tr><td colspan="9" class="text-center py-5 text-muted">
                                <i class="bi bi-wallet2 fs-3 d-block mb-2 opacity-25 text-success"></i>Cargando…
                            </td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════ TAB CXP ══════════ -->
    <div class="tab-pane fade" id="tab-cxp" role="tabpanel">
        <div class="card cmg-table-card w-100 border-0 shadow-sm rounded-top-0 rounded-bottom-3">

            <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">

                <div class="d-flex align-items-center gap-2">
                    <?php if ($perm['crear'] ?? false): ?>
                    <button class="btn btn-primary btn-sm px-3 shadow-sm" onclick="SI_abrirModalCxp()">
                        <i class="bi bi-plus-lg me-1"></i>Nuevo
                    </button>
                    <label class="btn btn-outline-primary btn-sm mb-0" title="Importar Excel">
                        <i class="bi bi-file-earmark-excel me-1"></i>Importar
                        <input type="file" id="input-import-cxp" accept=".xlsx,.xls" class="d-none" onchange="SI_importarCxp(this)">
                    </label>
                    <?php endif; ?>
                    <button class="btn btn-outline-secondary btn-sm" onclick="SI_descargarTemplateCxp()" title="Descargar plantilla Excel">
                        <i class="bi bi-download me-1"></i>Plantilla
                    </button>

                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-danger" onclick="SI_exportarCxpPdf()" title="PDF">
                            <i class="bi bi-file-earmark-pdf"></i>
                        </button>
                        <button class="btn btn-outline-success" onclick="SI_exportarCxpExcel()" title="Excel">
                            <i class="bi bi-file-earmark-spreadsheet"></i>
                        </button>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <select id="si-cxp-tipo" class="form-select form-select-sm shadow-none border" style="width:160px;" onchange="SI_cargarCxp()">
                        <option value="">Todos los tipos</option>
                        <option value="FACTURA_COMPRA">Facturas</option>
                        <option value="LIQUIDACION">Liquidaciones</option>
                        <option value="NOTA_CREDITO">Notas de Crédito</option>
                        <option value="NOTA_DEBITO">Notas de Débito</option>
                    </select>
                    <select id="si-cxp-estado" class="form-select form-select-sm shadow-none border" style="width:155px;" onchange="SI_cargarCxp()">
                        <option value="TODOS">Todos los estados</option>
                        <option value="PENDIENTE" selected>Pendiente</option>
                        <option value="PARCIAL">Parcial</option>
                        <option value="PAGADO">Pagado</option>
                    </select>
                    <span class="text-muted small fw-medium" id="si-cxp-count">0 registros</span>
                    <div class="input-group input-group-sm" style="width:200px;">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="search" class="form-control border-start-0 shadow-none ps-0"
                               id="si-cxp-buscar" placeholder="Filtrar…" oninput="SI_filtrarCxp(this.value)">
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="si-scroll">
                    <table class="table table-hover table-sm mb-0 align-middle" style="table-layout:fixed;min-width:960px;">
                        <colgroup>
                            <col style="width:100px;">
                            <col style="width:140px;">
                            <col>
                            <col style="width:105px;">
                            <col style="width:115px;">
                            <col style="width:105px;">
                            <col style="width:105px;">
                            <col style="width:105px;">
                            <col style="width:90px;">
                            <col style="width:100px;">
                        </colgroup>
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Tipo</th>
                                <th>Documento</th>
                                <th>Proveedor / RUC</th>
                                <th class="text-center">F.Emisión</th>
                                <th class="text-center">F.Vencimiento</th>
                                <th class="text-end">Saldo Inicial</th>
                                <th class="text-end">Pagado</th>
                                <th class="text-end pe-2">Pendiente</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center pe-3">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="si-cxp-tbody">
                            <tr><td colspan="10" class="text-center py-5 text-muted">
                                <i class="bi bi-receipt fs-3 d-block mb-2 opacity-25 text-primary"></i>Cargando…
                            </td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════ TAB BANCOS ══════════ -->
    <div class="tab-pane fade" id="tab-bancos" role="tabpanel">
        <div class="card cmg-table-card w-100 border-0 shadow-sm rounded-top-0 rounded-bottom-3">

            <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                <div>
                    <span class="fw-semibold"><i class="bi bi-bank me-2 text-warning"></i>Saldos de Cuentas Bancarias y Tarjetas</span>
                    <small class="text-muted d-block" style="font-size:.72rem;">
                        Ingrese el saldo y fecha de corte para cada cuenta activa de tipo <strong>Banco</strong> o <strong>Tarjeta</strong>.
                    </small>
                </div>
                <?php if ($perm['crear'] ?? false): ?>
                <button class="btn btn-warning btn-sm px-3 text-dark shadow-sm" onclick="SI_guardarBancos()">
                    <i class="bi bi-floppy me-1"></i>Guardar Saldos
                </button>
                <?php endif; ?>
            </div>

            <div class="card-body p-0">
                <div class="si-bancos-scroll">
                    <div id="bancos-container" class="p-3">
                        <div class="text-center py-5 text-muted">
                            <div class="spinner-border spinner-border-sm me-2 text-warning"></div>Cargando cuentas…
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div><!-- /tab-content -->


<!-- ═══════════════════════════════════════════════════════════
     MODAL: Saldo Inicial CXC
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalSICxc" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:560px;">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white py-2 px-3">
                <h6 class="modal-title fw-bold">
                    <i class="bi bi-wallet2 me-2"></i>Saldo Inicial — Cuenta por Cobrar
                </h6>
                <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <input type="hidden" id="si-cxc-id">
                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Nº Documento <span class="text-danger">*</span></label>
                        <input type="text" id="si-cxc-nro" class="form-control form-control-sm shadow-none"
                               placeholder="001-001-000001" maxlength="50">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold mb-1">Fecha Emisión <span class="text-danger">*</span></label>
                        <input type="date" id="si-cxc-femision" class="form-control form-control-sm shadow-none">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold mb-1">Fecha Vencimiento</label>
                        <input type="date" id="si-cxc-fvenc" class="form-control form-control-sm shadow-none">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold mb-1">RUC / Cédula</label>
                        <input type="text" id="si-cxc-ruc" class="form-control form-control-sm shadow-none" maxlength="20">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small fw-bold mb-1">Nombre del Cliente <span class="text-danger">*</span></label>
                        <input type="text" id="si-cxc-nombre" class="form-control form-control-sm shadow-none" maxlength="255">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1 text-success">Saldo Pendiente ($) <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text fw-bold text-success border-success border-opacity-50">$</span>
                            <input type="number" id="si-cxc-saldo"
                                   class="form-control shadow-none fw-bold text-success border-success border-opacity-50"
                                   step="0.01" min="0.01" placeholder="0.00">
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Observaciones</label>
                        <input type="text" id="si-cxc-obs" class="form-control form-control-sm shadow-none"
                               maxlength="255" placeholder="Opcional…">
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2 px-3 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-danger btn-sm" id="btn-eliminar-cxc"
                        onclick="SI_eliminarCxc()" style="display:none!important;">
                    <i class="bi bi-trash me-1"></i>Eliminar
                </button>
                <div class="ms-auto d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success btn-sm px-4" onclick="SI_guardarCxc()">
                        <i class="bi bi-check-lg me-1"></i>Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL: Saldo Inicial CXP
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalSICxp" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:580px;">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white py-2 px-3">
                <h6 class="modal-title fw-bold">
                    <i class="bi bi-receipt me-2"></i>Saldo Inicial — Cuenta por Pagar
                </h6>
                <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <input type="hidden" id="si-cxp-id">
                <div class="row g-2">
                    <div class="col-md-5">
                        <label class="form-label small fw-bold mb-1">Tipo Documento <span class="text-danger">*</span></label>
                        <select id="si-cxp-tipo" class="form-select form-select-sm shadow-none">
                            <option value="FACTURA_COMPRA">Factura de Compra</option>
                            <option value="LIQUIDACION">Liquidación</option>
                            <option value="NOTA_CREDITO">Nota de Crédito</option>
                            <option value="NOTA_DEBITO">Nota de Débito</option>
                        </select>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label small fw-bold mb-1">Nº Documento <span class="text-danger">*</span></label>
                        <input type="text" id="si-cxp-nro" class="form-control form-control-sm shadow-none"
                               placeholder="001-001-000001" maxlength="50">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold mb-1">Fecha Emisión <span class="text-danger">*</span></label>
                        <input type="date" id="si-cxp-femision" class="form-control form-control-sm shadow-none">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold mb-1">Fecha Vencimiento</label>
                        <input type="date" id="si-cxp-fvenc" class="form-control form-control-sm shadow-none">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold mb-1">RUC / Cédula</label>
                        <input type="text" id="si-cxp-ruc" class="form-control form-control-sm shadow-none" maxlength="20">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small fw-bold mb-1">Nombre del Proveedor <span class="text-danger">*</span></label>
                        <input type="text" id="si-cxp-nombre" class="form-control form-control-sm shadow-none" maxlength="255">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1 text-primary">Saldo Pendiente ($) <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text fw-bold text-primary border-primary border-opacity-50">$</span>
                            <input type="number" id="si-cxp-saldo"
                                   class="form-control shadow-none fw-bold text-primary border-primary border-opacity-50"
                                   step="0.01" min="0.01" placeholder="0.00">
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Observaciones</label>
                        <input type="text" id="si-cxp-obs" class="form-control form-control-sm shadow-none"
                               maxlength="255" placeholder="Opcional…">
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2 px-3 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-danger btn-sm" id="btn-eliminar-cxp"
                        onclick="SI_eliminarCxp()" style="display:none!important;">
                    <i class="bi bi-trash me-1"></i>Eliminar
                </button>
                <div class="ms-auto d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-sm px-4" onclick="SI_guardarCxp()">
                        <i class="bi bi-check-lg me-1"></i>Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL: Registrar Cobro / Pago
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalSIMovimiento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:480px;">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header py-2 px-3" id="si-mov-header">
                <h6 class="modal-title fw-bold" id="si-mov-titulo">
                    <i class="bi bi-cash-coin me-2"></i>Registrar Movimiento
                </h6>
                <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <input type="hidden" id="si-mov-id">
                <input type="hidden" id="si-mov-tipo">

                <!-- Resumen del documento -->
                <div class="p-2 border rounded-3 bg-light mb-3">
                    <div class="row g-1">
                        <div class="col-6">
                            <div class="text-muted" style="font-size:.7rem;">Documento</div>
                            <div class="fw-bold small font-monospace" id="si-mov-doc"></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted" style="font-size:.7rem;">Tercero</div>
                            <div class="fw-semibold small text-truncate" id="si-mov-tercero"></div>
                        </div>
                        <div class="col-6 mt-1">
                            <div class="text-muted" style="font-size:.7rem;">Saldo Inicial</div>
                            <div class="fw-semibold small">$<span id="si-mov-saldo-inicial"></span></div>
                        </div>
                        <div class="col-6 mt-1">
                            <div class="text-muted" style="font-size:.7rem;">Saldo Pendiente</div>
                            <div class="fw-bold" id="si-mov-saldo-pend" style="color:#dc3545;font-size:1.1rem;"></div>
                        </div>
                    </div>
                </div>

                <div class="row g-2">
                    <div class="col-7">
                        <label class="form-label small fw-bold mb-1">Serie <span class="text-danger">*</span></label>
                        <select id="si-mov-punto" class="form-select form-select-sm shadow-none"
                                onchange="SI_cargarSecuencialMov(this.value)">
                            <option value="">— Seleccione —</option>
                        </select>
                    </div>
                    <div class="col-5">
                        <label class="form-label small fw-bold mb-1">Nº Secuencial</label>
                        <input type="text" id="si-mov-secuencial" readonly
                               class="form-control form-control-sm shadow-none bg-light text-center fw-bold font-monospace"
                               placeholder="—" style="font-size:.8rem;">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Fecha <span class="text-danger">*</span></label>
                        <input type="date" id="si-mov-fecha" class="form-control form-control-sm shadow-none"
                               value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Concepto</label>
                        <select id="si-mov-concepto" class="form-select form-select-sm shadow-none">
                            <option value="">— Sin concepto —</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Monto ($) <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text fw-bold" id="si-mov-icon-monto">$</span>
                            <input type="number" id="si-mov-monto" class="form-control shadow-none fw-bold"
                                   step="0.01" min="0.01" placeholder="0.00">
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Forma de Cobro/Pago <span class="text-danger">*</span></label>
                        <select id="si-mov-forma" class="form-select form-select-sm shadow-none"
                                onchange="SI_toggleBancoMov(this.value)">
                            <option value="">— Seleccione —</option>
                        </select>
                    </div>
                    <!-- Datos bancarios (condicional) -->
                    <div class="col-12 d-none" id="si-mov-div-banco">
                        <div class="border border-warning border-opacity-25 rounded-2 p-2 bg-warning bg-opacity-10 row g-2">
                            <div class="col-6">
                                <label class="form-label small fw-bold mb-1">Op. Bancaria</label>
                                <select id="si-mov-tipo-op" class="form-select form-select-sm shadow-none">
                                    <option value="TRANSFERENCIA">Transferencia</option>
                                    <option value="DEBITO">Débito</option>
                                    <option value="CHEQUE">Cheque</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold mb-1">Nº Referencia</label>
                                <input type="text" id="si-mov-num-op" class="form-control form-control-sm shadow-none"
                                       placeholder="Nº transf / cheque" maxlength="100">
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Observaciones</label>
                        <input type="text" id="si-mov-obs" class="form-control form-control-sm shadow-none"
                               placeholder="Opcional…" maxlength="255">
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2 px-3 d-flex gap-2 justify-content-end">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm px-4 fw-semibold" id="btn-guardar-movimiento"
                        onclick="SI_guardarMovimiento()">
                    <i class="bi bi-check-lg me-1"></i>Registrar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL: Historial de Movimientos
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalSIHistorial" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-secondary text-white py-2 px-3">
                <h6 class="modal-title fw-bold">
                    <i class="bi bi-clock-history me-2"></i>Historial de Movimientos
                </h6>
                <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Fecha</th>
                                <th>Nº Comprobante</th>
                                <th>Forma</th>
                                <th>Usuario</th>
                                <th class="text-end pe-3">Monto</th>
                                <th>Observaciones</th>
                            </tr>
                        </thead>
                        <tbody id="si-hist-tbody">
                            <tr><td colspan="6" class="text-center py-4 text-muted">Cargando…</td></tr>
                        </tbody>
                        <tfoot class="table-light">
                            <tr class="fw-bold">
                                <td colspan="4" class="text-end small ps-3 py-2">Total:</td>
                                <td class="text-end pe-3 text-success">$<span id="si-hist-total">0.00</span></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2 px-3">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
    const RUTA_SI           = "<?= $rutaModulo ?>";
    const SI_PERM_CREAR     = <?= ($perm['crear']     ?? false) ? 'true' : 'false' ?>;
    const SI_PERM_MODIFICAR = <?= ($perm['modificar'] ?? false) ? 'true' : 'false' ?>;
    const SI_PERM_ELIMINAR  = <?= ($perm['eliminar']  ?? false) ? 'true' : 'false' ?>;

    /* Lazily cargar cada tab la primera vez */
    let SI_tabCxcCargado    = false;
    let SI_tabCxpCargado    = false;
    let SI_tabBancosCargado = false;

    function SI_onTabCxc() {
        if (!SI_tabCxcCargado) { SI_cargarCxc(); SI_tabCxcCargado = true; }
    }
    function SI_onTabCxp() {
        if (!SI_tabCxpCargado) { SI_cargarCxp(); SI_tabCxpCargado = true; }
    }
    function SI_onTabBancos() {
        if (!SI_tabBancosCargado) { SI_cargarBancos(); SI_tabBancosCargado = true; }
    }

    /* stub compatibilidad — el JS real llama SI_cambiarTab */
    function SI_cambiarTab(tab) { /* no-op, Bootstrap maneja el tab */ }
</script>
<script src="<?= BASE_URL ?>/js/modulos/saldos_iniciales.js?v=<?= time() ?>"></script>
