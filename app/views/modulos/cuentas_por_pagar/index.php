<?php $idModulo = basename($rutaModulo); ?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<style>
    .cxp-header { flex-shrink: 0; }
    /* Tabla principal */
    .cxp-scroll { max-height: calc(100vh - 390px); min-height: 220px; overflow-y: auto; overflow-x: auto; }
    .cxp-scroll thead th {
        position: sticky; top: 0; z-index: 10;
        background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6;
        white-space: nowrap;
    }
    #tabla-cxp td {
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    /* Badges de estado */
    .badge-vencida  { background: rgba(220,53,69,.12);  color: #dc3545; border: 1px solid rgba(220,53,69,.25); }
    .badge-vigente  { background: rgba(13,110,253,.12); color: #0d6efd; border: 1px solid rgba(13,110,253,.25); }
    .badge-proxima  { background: rgba(255,193,7,.15);  color: #856404; border: 1px solid rgba(255,193,7,.35); }
    .badge-pagada   { background: rgba(108,117,125,.12);color: #6c757d; border: 1px solid rgba(108,117,125,.25); }
    /* Badges de tipo documento */
    .badge-liquid   { background: rgba(102,16,242,.1);  color: #6610f2; border: 1px solid rgba(102,16,242,.2); font-size:.6rem; }
    .badge-compra   { background: rgba(13,110,253,.08); color: #0d6efd; border: 1px solid rgba(13,110,253,.2);  font-size:.6rem; }
    /* Columna saldo */
    .cxp-saldo-vencido { color: #dc3545 !important; }
    .cxp-saldo-vigente { color: #198754 !important; }
    .cxp-saldo-pagado  { color: #6c757d !important; }
</style>

<div class="container-fluid py-4 px-0 px-md-3" id="modulo-<?php echo $idModulo; ?>">

    <!-- ── Cabecera ── -->
    <div class="cxp-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h5 class="mb-0 fw-bold"><i class="bi bi-credit-card me-2 text-primary"></i>Cuentas por Pagar</h5>
            <small class="text-muted">Control de obligaciones con proveedores — facturas, notas de venta y liquidaciones de compras y servicios pendientes</small>
        </div>
    </div>

    <!-- ── Filtros ── -->
    <div class="accordion mb-3 shadow-sm border-0" id="accordionFiltrosCxP">
        <div class="accordion-item border-0 rounded-3">
            <h2 class="accordion-header">
                <button class="accordion-button bg-white text-dark py-2 shadow-none" type="button"
                        data-bs-toggle="collapse" data-bs-target="#collapseFiltrosCxP">
                    <i class="bi bi-funnel me-2 text-primary"></i>
                    <span class="fw-bold small">Filtros</span>
                </button>
            </h2>
            <div id="collapseFiltrosCxP" class="accordion-collapse collapse show">
                <div class="accordion-body bg-light bg-opacity-10 p-3 pt-2">
                    <form id="form-filtros-cxp" onsubmit="event.preventDefault(); CXP_cargar();" class="row g-3">

                        <!-- Estado -->
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Estado</label>
                            <select id="cxp-estado" name="estado" class="form-select form-select-sm shadow-none border"
                                    onchange="CXP_cargar()">
                                <option value="PENDIENTES" selected>Saldo Pendiente</option>
                                <option value="VENCIDAS">Vencidas</option>
                                <option value="AL_DIA">Al Día</option>
                                <option value="PAGADAS">Pagadas</option>
                                <option value="TODOS">Todos</option>
                            </select>
                        </div>

                        <!-- Tipo de documento -->
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Tipo</label>
                            <select id="cxp-tipo" name="tipo_fuente" class="form-select form-select-sm shadow-none border"
                                    onchange="CXP_cargar()">
                                <option value="">Todos</option>
                                <option value="COMPRA">Solo Facturas</option>
                                <option value="LIQUIDACION">Solo Liquidaciones</option>
                            </select>
                        </div>

                        <!-- Fecha Desde -->
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Fecha Desde</label>
                            <input type="date" id="cxp-fecha-desde" name="fecha_desde"
                                   class="form-control form-control-sm shadow-none border">
                        </div>

                        <!-- Fecha Hasta -->
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Fecha Hasta</label>
                            <input type="date" id="cxp-fecha-hasta" name="fecha_hasta"
                                   class="form-control form-control-sm shadow-none border"
                                   value="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <!-- Buscador proveedor -->
                        <div class="col-md-3 position-relative">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Proveedor</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                                <input type="text" id="cxp-search-proveedor" class="form-control border-start-0 px-1 shadow-none"
                                       placeholder="Buscar proveedor..." autocomplete="off">
                            </div>
                            <div id="cxp-chips-proveedor" class="d-flex flex-wrap gap-1 mt-1"></div>
                            <div id="cxp-dropdown-proveedores" class="list-group shadow position-absolute d-none"
                                 style="z-index:1050;width:calc(100% - 1.5rem);max-height:220px;overflow-y:auto;margin-top:2px;"></div>
                        </div>

                        <div class="col-12 d-flex justify-content-end border-top pt-3 mt-1">
                            <button type="submit" class="btn btn-primary btn-sm px-4 shadow-sm">
                                <i class="bi bi-search me-1"></i>Aplicar Filtros
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Tarjetas de Estadísticas ── -->
    <div class="row g-3 mb-3" id="cxp-stats-row">
        <div class="col-6 col-md-3">
            <div class="card border-0 rounded-4 shadow-sm h-100">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-3 me-3" style="width:46px;height:46px;display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-receipt fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-bold text-uppercase" style="font-size:.62rem;">Documentos</div>
                        <div class="fw-bold fs-4" id="cxp-stat-docs">0</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 rounded-4 shadow-sm h-100">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="bg-danger bg-opacity-10 text-danger rounded-3 p-3 me-3" style="width:46px;height:46px;display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-credit-card fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-bold text-uppercase" style="font-size:.62rem;">Por Pagar</div>
                        <div class="fw-bold fs-5 text-danger">$<span id="cxp-stat-saldo">0.00</span></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 rounded-4 shadow-sm h-100">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="bg-warning bg-opacity-10 text-warning rounded-3 p-3 me-3" style="width:46px;height:46px;display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-exclamation-triangle fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-bold text-uppercase" style="font-size:.62rem;">Vencido</div>
                        <div class="fw-bold fs-5 text-danger">$<span id="cxp-stat-vencido">0.00</span></div>
                        <div class="text-muted" style="font-size:.7rem;"><span id="cxp-stat-dvencidos">0</span> doc. vencidos</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 rounded-4 shadow-sm h-100">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="bg-success bg-opacity-10 text-success rounded-3 p-3 me-3" style="width:46px;height:46px;display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-check-circle fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-bold text-uppercase" style="font-size:.62rem;">Al Día</div>
                        <div class="fw-bold fs-5 text-success">$<span id="cxp-stat-aldia">0.00</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Tabla Principal ── -->
    <div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-2 px-3 border-bottom">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">

                <!-- Izquierda: exportación -->
                <div class="d-flex align-items-center gap-2">
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-danger" onclick="CXP_exportarPDF()" title="Exportar PDF">
                            <i class="bi bi-file-earmark-pdf"></i> PDF
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="CXP_exportarExcel()" title="Exportar Excel">
                            <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                        </button>
                    </div>
                </div>

                <!-- Derecha: contador + buscador -->
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-secondary bg-opacity-10 text-secondary border fw-normal" id="cxp-count-label">0 registros</span>
                    <input type="search" class="form-control form-control-sm shadow-none border" style="width:210px;"
                           id="cxp-buscador" placeholder="&#xF52A; Filtrar tabla…" oninput="CXP_filtrarTabla(this.value)">
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="cxp-scroll w-100">
                <table class="table table-hover table-sm mb-0 align-middle" id="tabla-cxp"
                       style="table-layout:fixed; min-width:860px;">
                    <colgroup>
                        <col style="width:165px;"><!-- Documento (badge+nro) -->
                        <col>                    <!-- Proveedor (flex) -->
                        <col style="width:92px;"><!-- F.Emisión -->
                        <col style="width:108px;"><!-- F.Vencimiento -->
                        <col style="width:98px;"><!-- Total -->
                        <col style="width:88px;"><!-- Pagado -->
                        <col style="width:82px;"><!-- NC/Ret. -->
                        <col style="width:102px;"><!-- Saldo -->
                        <col style="width:128px;"><!-- Estado -->
                        <col style="width:80px;"><!-- Acciones -->
                    </colgroup>
                    <thead class="table-light">
                        <tr>
                            <th class="ps-2">Documento</th>
                            <th>Proveedor</th>
                            <th class="text-center">F.Emisión</th>
                            <th class="text-center">F.Vencimiento</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Pagado</th>
                            <th class="text-end" title="Notas de Crédito / Retenciones">NC/Ret.</th>
                            <th class="text-end pe-2 fw-bold">Saldo</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="cxp-tbody">
                        <tr><td colspan="10" class="text-center py-5 text-muted">
                            <i class="bi bi-credit-card fs-3 d-block mb-2 text-primary opacity-50"></i>
                            Cargando cuentas por pagar…
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ── Gráfico de Antigüedad ── -->
    <div class="card border-0 shadow-sm mt-4" id="cxp-chart-card" style="display:none;">
        <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
            <h6 class="fw-bold mb-0"><i class="bi bi-bar-chart me-2 text-primary"></i>Análisis de Antigüedad de Saldo</h6>
            <small class="text-muted">Distribución de cuentas por pagar por tramos de vencimiento</small>
        </div>
        <div class="card-body">
            <canvas id="cxpAgingChart" style="max-height:260px;"></canvas>
        </div>
    </div>

    <!-- ── Saldos Iniciales CXP ── -->
    <div class="card border-0 shadow-sm mt-4" id="cxp-si-seccion" style="display:none;">
        <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
            <div>
                <h6 class="fw-bold mb-0 text-warning"><i class="bi bi-archive me-2"></i>Saldos Iniciales CXP</h6>
                <small class="text-muted">Saldos cargados manualmente desde sistemas anteriores — no corresponden a documentos registrados en este sistema</small>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <small class="text-muted" id="cxp-si-count"></small>
                <select id="cxp-si-estado" class="form-select form-select-sm shadow-none border" style="width:160px;" onchange="CXP_cargarSaldosIniciales()">
                    <option value="TODOS">Todos los estados</option>
                    <option value="PENDIENTE" selected>Pendiente</option>
                    <option value="PARCIAL">Parcial</option>
                    <option value="PAGADO">Pagado</option>
                </select>
                <button class="btn btn-outline-warning btn-sm" onclick="window.location.href='<?php echo BASE_URL; ?>/modulos/saldos_iniciales'">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Ir a Saldos Iniciales
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div style="max-height:300px;overflow-y:auto;">
                <table class="table table-hover table-sm mb-0 align-middle" style="table-layout:fixed;">
                    <colgroup>
                        <col style="width:90px;"><col style="width:130px;"><col><col style="width:110px;">
                        <col style="width:110px;"><col style="width:100px;"><col style="width:100px;"><col style="width:100px;"><col style="width:90px;">
                    </colgroup>
                    <thead class="table-light" style="position:sticky;top:0;z-index:5;">
                        <tr>
                            <th class="ps-3 small fw-semibold">Tipo</th>
                            <th class="small fw-semibold">Documento</th>
                            <th class="small fw-semibold">Proveedor / RUC</th>
                            <th class="text-center small fw-semibold">F.Emisión</th>
                            <th class="text-center small fw-semibold">F.Vencimiento</th>
                            <th class="text-end small fw-semibold">Saldo Inicial</th>
                            <th class="text-end small fw-semibold">Pagado</th>
                            <th class="text-end pe-3 small fw-semibold">Pendiente</th>
                            <th class="text-center small fw-semibold">Estado</th>
                        </tr>
                    </thead>
                    <tbody id="cxp-si-tbody">
                        <tr><td colspan="9" class="text-center py-4 text-muted">
                            <div class="spinner-border spinner-border-sm text-warning me-2"></div>Cargando saldos iniciales…
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL: Registrar Pago
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalPago" tabindex="-1" aria-labelledby="modalPagoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:500px;">
        <div class="modal-content border-0 shadow-lg">

            <div class="modal-header bg-primary text-white py-2 px-3">
                <h6 class="modal-title fw-bold" id="modalPagoLabel">
                    <i class="bi bi-cash-stack me-2"></i>Registrar Pago
                </h6>
                <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-3">
                <input type="hidden" id="pago-id-doc">
                <input type="hidden" id="pago-tipo-fuente">

                <!-- Info del documento -->
                <div class="p-2 border rounded-3 bg-light mb-3">
                    <div class="row g-1">
                        <div class="col-6">
                            <div class="text-muted" style="font-size:.7rem;">Documento</div>
                            <div class="fw-bold" id="pago-nro-doc"></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted" style="font-size:.7rem;">Proveedor</div>
                            <div class="fw-semibold text-truncate" id="pago-proveedor" style="font-size:.85rem;"></div>
                        </div>
                        <div class="col-3 mt-1">
                            <div class="text-muted" style="font-size:.7rem;">Total Doc.</div>
                            <div class="fw-semibold">$<span id="pago-total-doc">0.00</span></div>
                        </div>
                        <div class="col-3 mt-1">
                            <div class="text-muted" style="font-size:.7rem;">Ya Pagado</div>
                            <div class="fw-semibold text-success">$<span id="pago-ya-pagado">0.00</span></div>
                        </div>
                        <div class="col-3 mt-1">
                            <div class="text-muted" style="font-size:.7rem;">Retención</div>
                            <div class="fw-semibold text-warning">$<span id="pago-retenido">0.00</span></div>
                        </div>
                        <div class="col-3 mt-1">
                            <div class="text-muted" style="font-size:.7rem;" id="pago-nc-nd-label">NC/ND</div>
                            <div class="fw-semibold text-info">$<span id="pago-nc-nd">0.00</span></div>
                        </div>
                        <div class="col-12 mt-1 text-end border-top pt-1">
                            <span class="text-muted" style="font-size:.7rem;">Saldo Pendiente: </span>
                            <span class="fw-bold text-danger fs-5" id="pago-saldo-pend">0.00</span>
                        </div>
                    </div>
                </div>

                <!-- Alerta: documento completamente pagado -->
                <div class="alert alert-success text-center py-3 d-none mb-2" id="pago-alert-pagada">
                    <i class="bi bi-check-circle-fill fs-4 d-block mb-1 text-success"></i>
                    <span class="fw-bold text-success">¡Documento Completamente Pagado!</span>
                </div>

                <!-- Formulario -->
                <div id="pago-form-body">
                    <div class="row g-2">

                        <div class="col-7">
                            <label class="form-label small fw-bold mb-1">Serie <span class="text-danger">*</span></label>
                            <select id="pago-punto-emision" class="form-select form-select-sm shadow-none"
                                    onchange="CXP_cargarSecuencial(this.value)">
                                <option value="">— Seleccione —</option>
                            </select>
                        </div>
                        <div class="col-5">
                            <label class="form-label small fw-bold mb-1">Nº Egreso</label>
                            <input type="text" id="pago-secuencial" readonly
                                   class="form-control form-control-sm shadow-none bg-light text-center fw-bold font-monospace"
                                   placeholder="—" style="font-size:.8rem;">
                        </div>

                        <div class="col-6">
                            <label class="form-label small fw-bold mb-1">Fecha Emisión <span class="text-danger">*</span></label>
                            <input type="date" id="pago-fecha" class="form-control form-control-sm shadow-none"
                                   value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-1">Concepto de Egreso</label>
                            <select id="pago-concepto" class="form-select form-select-sm shadow-none">
                                <option value="">— Sin concepto —</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-bold mb-1 text-danger">Monto a Pagar ($) <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text fw-bold text-danger border-danger border-opacity-50">$</span>
                                <input type="number" id="pago-monto"
                                       class="form-control shadow-none fw-bold text-danger border-danger border-opacity-50"
                                       step="0.01" min="0.01" placeholder="0.00">
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-bold mb-1">Forma de Pago <span class="text-danger">*</span></label>
                            <select id="pago-forma" class="form-select form-select-sm shadow-none"
                                    onchange="CXP_toggleBancoDatos(this.value)">
                                <option value="">— Seleccione —</option>
                            </select>
                        </div>

                        <!-- Campos bancarios condicionales -->
                        <div class="col-12 d-none" id="pago-div-banco">
                            <div class="border border-warning border-opacity-25 rounded-2 p-2 bg-warning bg-opacity-10 row g-2">
                                <div class="col-6">
                                    <label class="form-label small fw-bold mb-1">Op. Bancaria</label>
                                    <select id="pago-tipo-op" class="form-select form-select-sm shadow-none"
                                            onchange="CXP_toggleTipoOp(this.value)">
                                        <option value="TRANSFERENCIA">Transferencia</option>
                                        <option value="DEBITO">Débito</option>
                                        <option value="CHEQUE">Cheque</option>
                                    </select>
                                </div>
                                <div class="col-6" id="pago-div-num-op">
                                    <label class="form-label small fw-bold mb-1" id="pago-lbl-num-op">Nº Referencia</label>
                                    <input type="text" id="pago-num-op" class="form-control form-control-sm shadow-none"
                                           placeholder="Nº transf / doc" maxlength="100">
                                </div>
                                <div class="col-6 d-none" id="pago-div-fecha-cobro">
                                    <label class="form-label small fw-bold mb-1">Fecha Cobro</label>
                                    <input type="date" id="pago-fecha-cobro" class="form-control form-control-sm shadow-none">
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-bold mb-1">Observaciones</label>
                            <input type="text" id="pago-observaciones" class="form-control form-control-sm shadow-none"
                                   placeholder="Opcional..." maxlength="255">
                        </div>

                        <div class="col-12">
                            <div id="pago-msg-error" class="alert alert-danger py-1 px-2 small d-none mb-0"></div>
                        </div>

                    </div>
                </div>

            </div><!-- /modal-body -->

            <div class="modal-footer py-2 px-3">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm px-4" id="btn-guardar-pago"
                        onclick="CXP_guardarPago()">
                    <i class="bi bi-check-lg me-1"></i>Registrar Pago y Generar Egreso
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL: Historial de Pagos
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalHistorialPagos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-secondary text-white py-2 px-3">
                <div>
                    <h6 class="modal-title fw-bold mb-0"><i class="bi bi-clock-history me-2"></i>Historial de Pagos</h6>
                    <small class="opacity-75" id="historial-pago-subtitulo"></small>
                </div>
                <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:.82rem;">
                        <thead class="table-light border-bottom" style="font-size:.75rem;">
                            <tr>
                                <th class="ps-3">Fecha</th>
                                <th>Nº Egreso</th>
                                <th>Forma de Pago</th>
                                <th>Registrado por</th>
                                <th class="text-end">Monto</th>
                                <th>Observaciones</th>
                            </tr>
                        </thead>
                        <tbody id="historial-pagos-tbody">
                            <tr><td colspan="6" class="text-center py-4 text-muted">
                                <div class="spinner-border spinner-border-sm me-2"></div>Cargando…
                            </td></tr>
                        </tbody>
                        <tfoot>
                            <tr class="table-light fw-bold border-top">
                                <td colspan="4" class="text-end pe-2 text-muted ps-3">Total abonado:</td>
                                <td class="text-end text-success fw-bold">$<span id="historial-pagos-total">0.00</span></td>
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

<!-- ═══════════════════════════════════════════════════════════
     MODAL: Detalle de ajustes (NC / ND / Retenciones)
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalAjustes" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-secondary text-white py-2 px-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-info-circle me-2"></i>Detalle de Ajustes</h6>
                <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3" id="ajustes-body"></div>
            <div class="modal-footer py-2 px-3">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
    const RUTA_MODULO_CXP = "<?php echo $rutaModulo; ?>";
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?php echo BASE_URL; ?>/js/modulos/cuentas_por_pagar.js?v=<?php echo time(); ?>"></script>
