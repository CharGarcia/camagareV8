<?php $idModulo = basename($rutaModulo); ?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<style>
    .cxc-header { flex-shrink:0; }
    .cxc-scroll  { max-height:520px; overflow-y:auto; }
    .cxc-scroll thead th { position:sticky; top:0; z-index:10; background:#f8f9fa; box-shadow:0 1px 0 #dee2e6; }
    .badge-vencida  { background:rgba(220,53,69,.12);  color:#dc3545; border:1px solid rgba(220,53,69,.25); }
    .badge-vigente  { background:rgba(25,135,84,.12);  color:#198754; border:1px solid rgba(25,135,84,.25); }
    .badge-proxima  { background:rgba(255,193,7,.15);  color:#856404; border:1px solid rgba(255,193,7,.35); }
    .badge-pagada   { background:rgba(108,117,125,.12);color:#6c757d; border:1px solid rgba(108,117,125,.25); }
</style>

<div class="container-fluid py-4 px-0 px-md-3" id="modulo-<?php echo $idModulo; ?>">

    <!-- ── Cabecera ── -->
    <div class="cxc-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h5 class="mb-0 fw-bold"><i class="bi bi-wallet2 me-2 text-success"></i>Cuentas por Cobrar</h5>
            <small class="text-muted">Seguimiento de saldos pendientes, cobros y recordatorios</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?php if (!empty($tieneWA)): ?>
            <button class="btn btn-success btn-sm" onclick="CXC_envioMasivoWA()" title="Enviar recordatorio WhatsApp a todos los seleccionados">
                <i class="bi bi-whatsapp me-1"></i>Envío Masivo WA
            </button>
            <?php endif; ?>
            <button class="btn btn-outline-primary btn-sm" onclick="CXC_envioMasivoEmail()" title="Enviar correo a todos los seleccionados">
                <i class="bi bi-envelope me-1"></i>Envío Masivo Email
            </button>
        </div>
    </div>

    <!-- ── Filtros ── -->
    <div class="accordion mb-3 shadow-sm border-0" id="accordionFiltrosCxC">
        <div class="accordion-item border-0 rounded-3">
            <h2 class="accordion-header">
                <button class="accordion-button bg-white text-dark py-2 shadow-none" type="button"
                        data-bs-toggle="collapse" data-bs-target="#collapseFiltrosCxC">
                    <i class="bi bi-funnel me-2 text-success"></i>
                    <span class="fw-bold small">Filtros</span>
                </button>
            </h2>
            <div id="collapseFiltrosCxC" class="accordion-collapse collapse show">
                <div class="accordion-body bg-light bg-opacity-10 p-3 pt-2">
                    <form id="form-filtros-cxc" onsubmit="event.preventDefault(); CXC_cargar();" class="row g-3">

                        <!-- Estado CxC -->
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Estado</label>
                            <select id="cxc-estado" name="estado" class="form-select form-select-sm shadow-none border"
                                    onchange="CXC_cargar()">
                                <option value="PENDIENTES" selected>Saldo Pendiente</option>
                                <option value="VENCIDAS">Vencidas</option>
                                <option value="AL_DIA">Al Día</option>
                                <option value="PAGADAS">Pagadas</option>
                                <option value="TODOS">Todos</option>
                            </select>
                        </div>

                        <!-- Fecha Desde -->
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Fecha Desde</label>
                            <input type="date" id="cxc-fecha-desde" name="fecha_desde"
                                   class="form-control form-control-sm shadow-none border">
                        </div>

                        <!-- Fecha Hasta -->
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Fecha Hasta</label>
                            <input type="date" id="cxc-fecha-hasta" name="fecha_hasta"
                                   class="form-control form-control-sm shadow-none border"
                                   value="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <!-- Buscador cliente -->
                        <div class="col-md-3 position-relative">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Cliente</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                                <input type="text" id="cxc-search-cliente" class="form-control border-start-0 px-1 shadow-none"
                                       placeholder="Buscar cliente..." autocomplete="off">
                            </div>
                            <div id="cxc-chips-cliente" class="d-flex flex-wrap gap-1 mt-1"></div>
                            <div id="cxc-dropdown-clientes" class="list-group shadow position-absolute d-none"
                                 style="z-index:1050;width:calc(100% - 1.5rem);max-height:220px;overflow-y:auto;margin-top:2px;"></div>
                        </div>

                        <div class="col-12 d-flex justify-content-end border-top pt-3 mt-1">
                            <button type="submit" class="btn btn-success btn-sm px-4 shadow-sm">
                                <i class="bi bi-search me-1"></i>Aplicar Filtros
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Tarjetas de Estadísticas ── -->
    <div class="row g-3 mb-3" id="cxc-stats-row">
        <div class="col-6 col-md-3">
            <div class="card border-0 rounded-4 shadow-sm h-100">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="bg-success bg-opacity-10 text-success rounded-3 p-3 me-3" style="width:46px;height:46px;display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-receipt fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-bold text-uppercase" style="font-size:.62rem;">Facturas</div>
                        <div class="fw-bold fs-4" id="cxc-stat-facturas">0</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 rounded-4 shadow-sm h-100">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-3 me-3" style="width:46px;height:46px;display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-wallet2 fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-bold text-uppercase" style="font-size:.62rem;">Saldo Total</div>
                        <div class="fw-bold fs-5 text-primary">$<span id="cxc-stat-saldo">0.00</span></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 rounded-4 shadow-sm h-100">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="bg-danger bg-opacity-10 text-danger rounded-3 p-3 me-3" style="width:46px;height:46px;display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-exclamation-triangle fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-bold text-uppercase" style="font-size:.62rem;">Vencido</div>
                        <div class="fw-bold fs-5 text-danger">$<span id="cxc-stat-vencido">0.00</span></div>
                        <div class="text-muted" style="font-size:.7rem;"><span id="cxc-stat-fvencidas">0</span> facturas vencidas</div>
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
                        <div class="fw-bold fs-5 text-success">$<span id="cxc-stat-aldia">0.00</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Gráfico de Antigüedad ── -->
    <div class="card border-0 shadow-sm mb-4" id="cxc-chart-card" style="display:none;">
        <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
            <h6 class="fw-bold mb-0"><i class="bi bi-bar-chart me-2 text-success"></i>Análisis de Antigüedad de Saldo</h6>
            <small class="text-muted">Distribución de cuentas por cobrar por tramos de vencimiento</small>
        </div>
        <div class="card-body">
            <canvas id="cxcAgingChart" style="max-height:260px;"></canvas>
        </div>
    </div>

    <!-- ── Tabla Principal ── -->
    <div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-2 px-3 border-bottom">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="form-check mb-0 me-1">
                        <input class="form-check-input" type="checkbox" id="cxc-chk-all" onchange="CXC_seleccionarTodos(this.checked)">
                        <label class="form-check-label small text-muted" for="cxc-chk-all">Todos</label>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-danger" onclick="CXC_exportarPDF()">
                            <i class="bi bi-file-earmark-pdf"></i> PDF
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="CXC_exportarExcel()">
                            <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                        </button>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <small class="text-muted fw-medium" id="cxc-count-label"></small>
                    <input type="search" class="form-control form-control-sm shadow-none border" style="width:200px;"
                           id="cxc-buscador" placeholder="Filtrar tabla..." oninput="CXC_filtrarTabla(this.value)">
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="cxc-scroll w-100">
                <table class="table table-hover table-sm mb-0 align-middle" id="tabla-cxc" style="table-layout:fixed;">
                    <colgroup>
                        <col style="width:36px;">
                        <col style="width:130px;">
                        <col>
                        <col style="width:110px;">
                        <col style="width:105px;">
                        <col style="width:105px;">
                        <col style="width:65px;">
                        <col style="width:100px;">
                        <col style="width:100px;">
                        <col style="width:100px;">
                        <col style="width:115px;">
                    </colgroup>
                    <thead class="table-light">
                        <tr>
                            <th class="text-center p-1"></th>
                            <th class="ps-2">Factura</th>
                            <th>Cliente</th>
                            <th>F.Emisión</th>
                            <th>F.Vencimiento</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Cobrado</th>
                            <th class="text-end pe-3">Saldo</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="cxc-tbody">
                        <tr><td colspan="10" class="text-center py-5 text-muted">
                            <i class="bi bi-wallet2 fs-3 d-block mb-2 text-success opacity-50"></i>
                            Cargando cuentas por cobrar…
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL: Registrar Cobro
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalCobro" tabindex="-1" aria-labelledby="modalCobroLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:480px;">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white py-2 px-3">
                <h6 class="modal-title fw-bold" id="modalCobroLabel"><i class="bi bi-cash-coin me-2"></i>Registrar Cobro</h6>
                <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <input type="hidden" id="cobro-id-venta">
                <div class="p-2 border rounded-3 bg-light mb-3" id="cobro-info-factura">
                    <div class="row g-1">
                        <div class="col-6">
                            <div class="text-muted" style="font-size:.7rem;">Factura</div>
                            <div class="fw-bold" id="cobro-nro-factura"></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted" style="font-size:.7rem;">Cliente</div>
                            <div class="fw-bold" id="cobro-cliente"></div>
                        </div>
                        <div class="col-4 mt-1">
                            <div class="text-muted" style="font-size:.7rem;">Total Factura</div>
                            <div class="fw-semibold">$<span id="cobro-total-fact"></span></div>
                        </div>
                        <div class="col-4 mt-1">
                            <div class="text-muted" style="font-size:.7rem;">Ya Cobrado</div>
                            <div class="fw-semibold text-success">$<span id="cobro-ya-cobrado"></span></div>
                        </div>
                        <div class="col-4 mt-1">
                            <div class="text-muted" style="font-size:.7rem;">Saldo Pendiente</div>
                            <div class="fw-bold text-danger fs-5">$<span id="cobro-saldo-pend"></span></div>
                        </div>
                    </div>
                </div>
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold mb-1">Monto a Cobrar <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">$</span>
                            <input type="number" id="cobro-monto" class="form-control shadow-none"
                                   step="0.01" min="0.01" placeholder="0.00">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold mb-1">Fecha de Cobro <span class="text-danger">*</span></label>
                        <input type="date" id="cobro-fecha" class="form-control form-control-sm shadow-none"
                               value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Forma de Cobro <span class="text-danger">*</span></label>
                        <select id="cobro-forma" class="form-select form-select-sm shadow-none">
                            <option value="">Cargando…</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Observaciones</label>
                        <input type="text" id="cobro-observaciones" class="form-control form-control-sm shadow-none"
                               placeholder="Opcional..." maxlength="255">
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2 px-3">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success btn-sm px-4" id="btn-guardar-cobro" onclick="CXC_guardarCobro()">
                    <i class="bi bi-check-lg me-1"></i>Registrar Cobro
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL: Historial de Cobros
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalHistorial" tabindex="-1" aria-labelledby="modalHistorialLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white py-2 px-3">
                <h6 class="modal-title fw-bold" id="modalHistorialLabel"><i class="bi bi-clock-history me-2"></i>Historial de Cobros</h6>
                <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <p class="text-muted small mb-2" id="historial-subtitulo"></p>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Nro Ingreso</th>
                                <th>Forma de Cobro</th>
                                <th>Usuario</th>
                                <th class="text-end">Monto</th>
                                <th>Observaciones</th>
                            </tr>
                        </thead>
                        <tbody id="historial-tbody">
                            <tr><td colspan="6" class="text-center text-muted">Cargando…</td></tr>
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold">
                                <td colspan="4" class="text-end small">Total cobrado:</td>
                                <td class="text-end text-success">$<span id="historial-total"></span></td>
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
     MODAL: Enviar Email
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalEmail" tabindex="-1" aria-labelledby="modalEmailLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:520px;">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header py-2 px-3" style="background:#0d6efd;">
                <h6 class="modal-title fw-bold text-white" id="modalEmailLabel"><i class="bi bi-envelope me-2"></i>Enviar Recordatorio por Email</h6>
                <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <input type="hidden" id="email-id-venta">
                <p class="text-muted small mb-2" id="email-subtitulo"></p>
                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Correo Destinatario <span class="text-danger">*</span></label>
                        <input type="email" id="email-destino" class="form-control form-control-sm shadow-none"
                               placeholder="cliente@email.com">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Asunto</label>
                        <input type="text" id="email-asunto" class="form-control form-control-sm shadow-none"
                               placeholder="Se completará automáticamente si se deja vacío">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Mensaje adicional (opcional)</label>
                        <textarea id="email-mensaje" class="form-control form-control-sm shadow-none" rows="4"
                                  placeholder="Puede agregar un mensaje personalizado que se incluirá en el correo..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2 px-3">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm px-4" onclick="CXC_enviarEmail()">
                    <i class="bi bi-send me-1"></i>Enviar Correo
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL: Enviar WhatsApp
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalWA" tabindex="-1" aria-labelledby="modalWALabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:520px;">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header py-2 px-3" style="background:#25d366;">
                <h6 class="modal-title fw-bold text-white" id="modalWALabel"><i class="bi bi-whatsapp me-2"></i>Enviar por WhatsApp</h6>
                <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <input type="hidden" id="wa-id-venta">
                <p class="text-muted small mb-2" id="wa-subtitulo"></p>
                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Número de WhatsApp <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">+</span>
                            <input type="text" id="wa-telefono" class="form-control shadow-none"
                                   placeholder="593987654321 (sin + ni espacios)">
                        </div>
                        <div class="form-text">Incluya el código de país. Ej: 593 para Ecuador.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Plantilla <span class="text-danger">*</span></label>
                        <select id="wa-plantilla" class="form-select form-select-sm shadow-none" onchange="CXC_mostrarVarsPlantilla()">
                            <option value="">Seleccione una plantilla aprobada…</option>
                        </select>
                    </div>
                    <!-- Variables de la plantilla -->
                    <div class="col-12" id="wa-vars-container" style="display:none;">
                        <label class="form-label small fw-bold mb-1">Variables de la plantilla</label>
                        <div id="wa-vars-lista" class="row g-2"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2 px-3">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm px-4 fw-bold" style="background:#25d366;color:#fff;" onclick="CXC_enviarWA()">
                    <i class="bi bi-whatsapp me-1"></i>Enviar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const RUTA_MODULO_CXC = "<?php echo $rutaModulo; ?>";
    const CXC_TIENE_WA    = <?php echo $tieneWA ? 'true' : 'false'; ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?php echo BASE_URL; ?>/js/modulos/cuentas_por_cobrar.js?v=<?php echo time(); ?>"></script>
