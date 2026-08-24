<?php
/** @var string $titulo */
/** @var array $perm */
/** @var string $rutaModulo */
/** @var array $formas */
/** @var int $idFormaPago */
/** @var array $aniosDisponibles */
/** @var string $fechaInicio */
/** @var string $fechaFin */
/** @var array $resumen */
/** @var array $vistaConfig */
/** @var array $gruposDeCuentas */
/** @var bool $consolidado */

$base = BASE_URL;
$urlBase = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');
?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<style>
    .cb-header { flex-shrink: 0; }
    .control-bancario-scroll {
        max-height: calc(100vh - 300px);
        min-height: 320px;
        overflow-y: auto;
        overflow-x: auto;
    }
    .control-bancario-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 10;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
        white-space: nowrap;
    }
    .cb-row { cursor: pointer; }
    .cb-row:hover { background-color: rgba(0,0,0,.04); }
</style>

<div class="container-fluid pt-2 pb-3 px-0 px-md-3" id="modulo-control_bancario">

    <div class="cb-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h5 class="mb-0 fw-bold"><i class="bi bi-bank me-2 text-primary"></i><?= htmlspecialchars($titulo) ?></h5>
            <small class="text-muted">Detalle de transacciones por cuenta bancaria, conciliación y seguimiento de cheques posfechados</small>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-warning btn-sm" onclick="CB_abrirModalPosfechados()">
                <i class="bi bi-calendar-event me-1"></i> Cheques Posfechados
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="CB_abrirModalHistorialConciliaciones()">
                <i class="bi bi-clock-history me-1"></i> Historial de Conciliaciones
            </button>
            <button type="button" class="btn btn-success btn-sm" id="cb-btn-conciliar" onclick="CB_abrirModalConciliar()">
                <i class="bi bi-check2-circle me-1"></i> Marcar Período como Conciliado
            </button>
        </div>
    </div>

    <div id="cb-badge-conciliacion" class="mb-2"></div>
    <!-- Aviso de origen de los datos cuando la cuenta no tiene cuenta contable (ver JS). -->
    <div id="cb-aviso-fuente" class="mb-2"></div>

    <!-- ── Selector de cuenta + filtros de fecha ── -->
    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-body p-3">
            <form id="cb-form-filtros" class="d-flex flex-nowrap align-items-end gap-2" onsubmit="event.preventDefault(); window.CB_fetchSearch(1);">
                <div style="flex:2.2 1 0;min-width:0">
                    <label class="form-label small fw-bold text-muted mb-1">Cuenta Bancaria</label>
                    <select id="cb-forma" class="form-select form-select-sm shadow-none" onchange="window.CB_cambiarCuenta(this.value)">
                        <option value="">— Seleccione —</option>
                        <?php foreach ($formas as $f): ?>
                            <option value="<?= (int) $f['id'] ?>" <?= (int) $f['id'] === $idFormaPago ? 'selected' : '' ?>>
                                <?= htmlspecialchars($f['nombre'] . ($f['nombre_banco'] ? ' — ' . $f['nombre_banco'] : '') . ($f['numero_cuenta'] ? ' (' . $f['numero_cuenta'] . ')' : '')) ?><?= empty($f['id_cuenta_contable']) ? ' — sin cuenta contable' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-check form-switch mt-1" id="cb-consolidado-wrap" style="display:none;">
                        <input class="form-check-input" type="checkbox" id="cb-consolidado" <?= $consolidado ? 'checked' : '' ?> onchange="window.CB_toggleConsolidado(this.checked)">
                        <label class="form-check-label small" for="cb-consolidado" title="Une los movimientos de todos los establecimientos del mismo RUC que comparten esta cuenta bancaria">Consolidar por RUC</label>
                    </div>
                </div>
                <div style="flex:1.1 1 0;min-width:0">
                    <label class="form-label small fw-bold text-muted mb-1">Flujo</label>
                    <select class="form-select form-select-sm shadow-none" id="cb-flujo" onchange="window.CB_fetchSearch(1)">
                        <option value="TODOS" selected>Todos</option>
                        <option value="INGRESO">Ingresos</option>
                        <option value="EGRESO">Egresos</option>
                    </select>
                </div>
                <div style="flex:1.2 1 0;min-width:0">
                    <label class="form-label small fw-bold text-muted mb-1">Tipo</label>
                    <select class="form-select form-select-sm shadow-none" id="cb-tipo" onchange="window.CB_fetchSearch(1)">
                        <option value="" selected>Todos</option>
                        <option value="TRANSFERENCIA">Transferencia</option>
                        <option value="DEPOSITO">Depósito</option>
                        <option value="DEBITO">Débito</option>
                        <option value="CHEQUE">Cheque</option>
                        <option value="TARJETA">Tarjeta</option>
                        <option value="PAYPHONE">Payphone</option>
                        <option value="OTRO">Otro</option>
                    </select>
                </div>
                <div style="flex:0.8 1 0;min-width:0">
                    <label class="form-label small fw-bold text-muted mb-1">Año</label>
                    <select class="form-select form-select-sm shadow-none" id="cb-anio" onchange="window.CB_actualizarFechas()">
                        <?php foreach ($aniosDisponibles as $anio): ?>
                            <option value="<?= $anio ?>" <?= $anio === (int) date('Y') ? 'selected' : '' ?>><?= $anio ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="flex:1.1 1 0;min-width:0">
                    <label class="form-label small fw-bold text-muted mb-1">Mes</label>
                    <select class="form-select form-select-sm shadow-none" id="cb-mes" onchange="window.CB_actualizarFechas()">
                        <option value="0" selected>Todos</option>
                        <?php
                        $meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
                        foreach ($meses as $i => $m): ?>
                            <option value="<?= $i + 1 ?>"><?= $m ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="flex:1.3 1 0;min-width:0">
                    <label class="form-label small fw-bold text-muted mb-1">Fecha Inicio</label>
                    <input type="date" class="form-control form-control-sm shadow-none" id="cb-fecha-inicio" value="<?= htmlspecialchars($fechaInicio) ?>">
                </div>
                <div style="flex:1.3 1 0;min-width:0">
                    <label class="form-label small fw-bold text-muted mb-1">Fecha Fin</label>
                    <input type="date" class="form-control form-control-sm shadow-none" id="cb-fecha-fin" value="<?= htmlspecialchars($fechaFin) ?>">
                </div>
                <div style="flex:0 0 auto">
                    <button type="submit" class="btn btn-primary btn-sm shadow-sm"><i class="bi bi-search"></i></button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── KPI del período seleccionado ── -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="card border-0 rounded-4 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="text-muted small fw-bold text-uppercase" style="font-size:.62rem;">Saldo Inicial</div>
                    <div class="fw-bold fs-5" id="cb-stat-saldo-inicial">$<?= number_format($resumen['saldo_inicial'] ?? 0, 2) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 rounded-4 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="text-muted small fw-bold text-uppercase" style="font-size:.62rem;">Créditos (entradas)</div>
                    <div class="fw-bold fs-5 text-success" id="cb-stat-creditos">$<?= number_format($resumen['creditos'] ?? 0, 2) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 rounded-4 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="text-muted small fw-bold text-uppercase" style="font-size:.62rem;">Débitos (salidas)</div>
                    <div class="fw-bold fs-5 text-danger" id="cb-stat-debitos">$<?= number_format($resumen['debitos'] ?? 0, 2) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 rounded-4 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="text-muted small fw-bold text-uppercase" style="font-size:.62rem;">Saldo Final</div>
                    <div class="fw-bold fs-5 text-primary" id="cb-stat-saldo-final">$<?= number_format($resumen['saldo_final'] ?? 0, 2) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Tabla Principal ── -->
    <div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-2 px-3 border-bottom">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <link rel="stylesheet" href="<?= rtrim($base, '/') ?>/css/components/filtros_busqueda.css?v=<?= time() ?>">
                    <script src="<?= rtrim($base, '/') ?>/js/components/filtros_busqueda.js?v=<?= time() ?>"></script>
                    <div id="fbBuscadorCB" style="width: 420px;"></div>
                    <input type="hidden" id="cb-buscar" value="">

                    <?php
                    $columnasTabla = [
                        'fecha_asiento' => 'Fecha',
                        'fecha_banco' => 'Fecha Banco',
                        'comprobante' => 'Comprobante',
                        'tipo' => 'Tipo',
                        'cheque' => 'Cheque',
                        'fecha_cheque' => 'Fecha Cheque',
                        'beneficiario_cheque' => 'Beneficiario',
                        'documento' => 'Documento Ref.',
                        'tercero' => 'Tercero',
                        'glosa' => 'Glosa',
                        'debe' => 'Debe',
                        'haber' => 'Haber',
                        'saldo' => 'Saldo',
                    ];
                    ?>
                    <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>

                    <div class="btn-group btn-group-sm">
                        <a id="cb-btn-pdf" href="#" class="btn btn-outline-danger" title="Descargar PDF"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                        <a id="cb-btn-excel" href="#" class="btn btn-outline-success" title="Descargar Excel"><i class="bi bi-file-earmark-spreadsheet"></i> Excel</a>
                    </div>
                    <div class="vr mx-1"></div>
                    <div class="btn-group btn-group-sm">
                        <a id="cb-btn-conciliacion-pdf" href="#" class="btn btn-outline-danger" title="Conciliación (PDF)"><i class="bi bi-file-earmark-pdf"></i> Conciliación</a>
                        <a id="cb-btn-conciliacion-excel" href="#" class="btn btn-outline-success" title="Conciliación (Excel)"><i class="bi bi-file-earmark-spreadsheet"></i> Conciliación</a>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span id="cb-pagination-info" class="text-muted small fw-medium"></span>
                    <div id="cb-pagination-container" class="btn-group btn-group-sm"></div>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="control-bancario-scroll w-100">
                <table class="table table-hover table-sm mb-0 align-middle" id="cb-tabla">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3 sortable-header" role="button" data-sort="fecha_asiento" data-col="fecha_asiento">Fecha <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="sortable-header" role="button" data-sort="fecha_banco" data-col="fecha_banco">Fecha Banco <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="sortable-header" role="button" data-sort="numero_comprobante" data-col="comprobante">Comprobante <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="sortable-header" role="button" data-sort="tipo_transaccion" data-col="tipo">Tipo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="sortable-header" role="button" data-sort="numero_cheque" data-col="cheque">Cheque <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="sortable-header" role="button" data-sort="fecha_cheque" data-col="fecha_cheque">Fecha Cheque <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="sortable-header" role="button" data-sort="beneficiario_cheque" data-col="beneficiario_cheque">Beneficiario <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="sortable-header" role="button" data-sort="documento_referencia" data-col="documento">Documento Ref. <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="sortable-header" role="button" data-sort="nombre_entidad" data-col="tercero">Tercero <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="sortable-header" role="button" data-sort="referencia_detalle" data-col="glosa">Glosa <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="text-end sortable-header" role="button" data-sort="debe" data-col="debe">Debe <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="text-end sortable-header" role="button" data-sort="haber" data-col="haber">Haber <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="text-end pe-3 sortable-header" role="button" data-sort="saldo_acumulado" data-col="saldo">Saldo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        </tr>
                    </thead>
                    <tbody id="cb-tbody">
                        <tr><td colspan="13" class="text-center py-5 text-muted"><i class="bi bi-bank fs-3 d-block mb-2"></i>Seleccione una cuenta bancaria.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════ MODAL: Clasificación de Movimiento ═══════════════════ -->
<div class="modal fade" id="modalClasificacionCB" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:520px;">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white py-2 px-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Clasificar Movimiento</h6>
                <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <input type="hidden" id="cbm-id-asiento-detalle">
                <input type="hidden" id="cbm-id-asiento">
                <!-- Anclaje alternativo al cobro/pago, para cuentas sin cuenta contable (sin asiento). -->
                <input type="hidden" id="cbm-origen-tipo">
                <input type="hidden" id="cbm-origen-id">
                <input type="hidden" id="cbm-id-empresa">
                <input type="hidden" id="cbm-id-forma-pago">
                <!-- ── Encabezado: TODO lo que no se edita aquí ──────────────────────────
                     Los datos del movimiento y, cuando viene de un ingreso/egreso, también su
                     tipo, cheque y glosa: se corrigen en ese documento, no en la conciliación.
                     Abajo solo queda lo que este módulo sí decide. -->
                <div class="p-2 border rounded-3 bg-light mb-3">
                    <div class="row g-1 small">
                        <div class="col-6"><span class="text-muted">Fecha asiento:</span> <span id="cbm-info-fecha" class="fw-bold"></span></div>
                        <div class="col-6"><span class="text-muted">Comprobante:</span> <span id="cbm-info-comprobante" class="fw-bold"></span></div>
                        <div class="col-12" id="cbm-info-establecimiento-wrap" style="display:none;"><span class="text-muted">Establecimiento:</span> <span id="cbm-info-establecimiento" class="fw-bold text-info"></span></div>
                        <div class="col-12"><span class="text-muted">Glosa:</span> <span id="cbm-info-glosa"></span></div>
                        <div class="col-6"><span class="text-muted">Monto:</span> <span id="cbm-info-monto" class="fw-bold"></span></div>
                        <!-- Solo cuando el movimiento viene de un documento (ver JS). -->
                        <div class="col-6 cbm-info-doc" style="display:none;"><span class="text-muted">Tipo:</span> <span id="cbm-info-tipo" class="fw-bold"></span></div>
                        <div class="col-6 cbm-info-doc" id="cbm-info-cheque-wrap" style="display:none;"><span class="text-muted">Nº Cheque:</span> <span id="cbm-info-numero-cheque" class="fw-bold"></span> <span id="cbm-info-direccion" class="text-muted"></span></div>
                        <div class="col-6 cbm-info-doc" id="cbm-info-fecha-cheque-wrap" style="display:none;"><span class="text-muted">Fecha del cheque:</span> <span id="cbm-info-fecha-cheque" class="fw-bold"></span></div>
                        <div class="col-12 cbm-info-doc" id="cbm-info-observacion-wrap" style="display:none;"><span class="text-muted">Observación:</span> <span id="cbm-info-observacion"></span></div>
                        <!-- Estado de cobro del cheque (solo para movimientos tipo Cheque). -->
                        <div class="col-12 mt-1" id="cbm-info-estado-wrap" style="display:none;">
                            <span class="text-muted">Estado:</span> <span id="cbm-info-estado"></span>
                        </div>
                    </div>
                    <div class="border-top mt-2 pt-1 text-muted d-none" style="font-size:.72rem;" id="cbm-aviso-documento">
                        <i class="bi bi-lock-fill me-1"></i>
                        Datos del <strong>ingreso/egreso</strong> que originó el movimiento: se corrigen en ese documento.
                    </div>
                </div>
                <!-- Cómo marcarlo como cobrado (solo visible en cheques aún en circulación). -->
                <div class="alert alert-warning py-2 px-3 small mb-3 d-none" id="cbm-ayuda-cobro">
                    <i class="bi bi-info-circle me-1"></i>
                    Para marcar este cheque como <strong>cobrado</strong>, registre abajo la
                    <strong>Fecha Banco</strong> (el día en que el banco lo hizo efectivo) y guarde.
                    El egreso mostrará el cheque como cobrado y ya no permitirá cambiarle la fecha ni anularlo.
                </div>

                <!-- ── Editable ────────────────────────────────────────────────────────── -->
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Fecha Banco (conciliación)</label>
                        <input type="date" id="cbm-fecha-banco" class="form-control form-control-sm shadow-none">
                        <div class="form-text">Día en que el banco lo hizo efectivo. En un cheque, llenarla lo marca como <strong>cobrado</strong>.</div>
                    </div>
                    <!-- Solo para movimientos SIN documento detrás (asientos manuales): ahí este
                         módulo es el único lugar donde se pueden anotar estos datos. -->
                    <div class="col-6 cbm-editable">
                        <label class="form-label small fw-bold mb-1">Tipo de Transacción <span class="text-danger">*</span></label>
                        <select id="cbm-tipo" class="form-select form-select-sm shadow-none" onchange="window.CB_toggleCampoCheque(this.value)">
                            <option value="DEPOSITO">Depósito</option>
                            <option value="CHEQUE">Cheque</option>
                            <option value="TRANSFERENCIA">Transferencia</option>
                            <option value="NOTA_DEBITO">Nota Débito</option>
                            <option value="NOTA_CREDITO">Nota Crédito</option>
                            <option value="OTRO" selected>Otro</option>
                        </select>
                    </div>
                    <div class="col-12 cbm-editable d-none row g-2" id="cbm-div-cheque">
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-1">Dirección del Cheque</label>
                            <select id="cbm-direccion" class="form-select form-select-sm shadow-none bg-light" disabled>
                                <option value="RECIBIDO">Recibido (cobro cliente)</option>
                                <option value="EMITIDO">Emitido (pago proveedor)</option>
                            </select>
                            <div class="form-text">Automática: ingreso = recibido, egreso = emitido.</div>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-1">Nº Cheque <span class="text-danger">*</span></label>
                            <input type="text" id="cbm-numero-cheque" class="form-control form-control-sm shadow-none" maxlength="50">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-1">Fecha del Cheque</label>
                            <input type="date" id="cbm-fecha-cheque" class="form-control form-control-sm shadow-none">
                            <div class="form-text">Si es futura, aparecerá como "Posfechado".</div>
                        </div>
                    </div>
                    <div class="col-12 cbm-editable">
                        <label class="form-label small fw-bold mb-1">Observación</label>
                        <input type="text" id="cbm-observacion" class="form-control form-control-sm shadow-none" maxlength="255">
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2 px-3 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-danger btn-sm" id="cbm-btn-quitar" onclick="window.CB_quitarClasificacion()">
                    <i class="bi bi-trash me-1"></i> Quitar clasificación
                </button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-sm px-4" onclick="window.CB_guardarClasificacion()">
                        <i class="bi bi-check-lg me-1"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════ MODAL: Cheques Posfechados ═══════════════════ -->
<div class="modal fade" id="modalPosfechadosCB" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning py-2 px-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-calendar-event me-2"></i>Cheques Posfechados</h6>
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <ul class="nav nav-tabs mb-2" id="cb-tabs-posfechados">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#cb-tab-recibidos" type="button">Recibidos (de clientes)</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cb-tab-emitidos" type="button">Emitidos (a proveedores)</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cb-tab-emitidos-emp" type="button">Emitidos a Empleados</button></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="cb-tab-recibidos">
                        <div class="table-responsive" style="max-height:400px;overflow-y:auto;">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light"><tr><th>Fecha Cheque</th><th>Nº Cheque</th><th>Cuenta</th><th>Cliente</th><th class="text-end">Monto</th></tr></thead>
                                <tbody id="cb-tbody-posf-recibidos"><tr><td colspan="5" class="text-center text-muted py-4">Cargando…</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="cb-tab-emitidos">
                        <div class="table-responsive" style="max-height:400px;overflow-y:auto;">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light"><tr><th>Fecha Cheque</th><th>Nº Cheque</th><th>Cuenta</th><th>Proveedor</th><th class="text-end">Monto</th></tr></thead>
                                <tbody id="cb-tbody-posf-emitidos"><tr><td colspan="5" class="text-center text-muted py-4">Cargando…</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="cb-tab-emitidos-emp">
                        <div class="table-responsive" style="max-height:400px;overflow-y:auto;">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light"><tr><th>Fecha Cheque</th><th>Nº Cheque</th><th>Cuenta</th><th>Empleado</th><th class="text-end">Monto</th></tr></thead>
                                <tbody id="cb-tbody-posf-emitidos-emp"><tr><td colspan="5" class="text-center text-muted py-4">Cargando…</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2 px-3">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════ MODAL: Conciliar Período ═══════════════════ -->
<div class="modal fade" id="modalConciliarCB" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:480px;">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white py-2 px-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-check2-circle me-2"></i>Marcar Período como Conciliado</h6>
                <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="p-2 border rounded-3 bg-light mb-3 small">
                    <div><span class="text-muted">Cuenta:</span> <span id="cbc-info-cuenta" class="fw-bold"></span></div>
                    <div><span class="text-muted">Período:</span> <span id="cbc-info-periodo" class="fw-bold"></span></div>
                    <div><span class="text-muted">Saldo final según el sistema:</span> <span id="cbc-info-saldo-sistema" class="fw-bold"></span></div>
                </div>
                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Saldo según estado de cuenta del banco (opcional)</label>
                        <input type="number" step="0.01" id="cbc-saldo-banco" class="form-control form-control-sm shadow-none" placeholder="0.00">
                        <div class="form-text">Si lo indicas y no coincide con el saldo del sistema, se te avisará antes de confirmar.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Observaciones</label>
                        <textarea id="cbc-observaciones" class="form-control form-control-sm shadow-none" rows="2" maxlength="500"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2 px-3">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success btn-sm px-4" onclick="window.CB_confirmarConciliar()">
                    <i class="bi bi-lock-fill me-1"></i> Conciliar y Bloquear Período
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════ MODAL: Historial de Conciliaciones ═══════════════════ -->
<div class="modal fade" id="modalHistorialConciliacionesCB" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-secondary text-white py-2 px-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-clock-history me-2"></i>Historial de Conciliaciones</h6>
                <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="table-responsive" style="max-height:420px;overflow-y:auto;">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Período</th><th class="text-end">Saldo Final</th><th class="text-end">Saldo Banco</th>
                                <th>Usuario</th><th>Estado</th><th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="cb-tbody-conciliaciones"><tr><td colspan="6" class="text-center text-muted py-4">Cargando…</td></tr></tbody>
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
    const CB_PERM_ELIMINAR = <?= !empty($perm['eliminar']) ? 'true' : 'false' ?>;
    const RUTA_MODULO_CB = "<?= $rutaModulo ?>";
    const CB_URL_BASE = "<?= $urlBase ?>";
    window.BASE_URL = '<?= $base ?>';
    // Mapa id_forma_pago -> cantidad de establecimientos que comparten esa cuenta real (mismo
    // banco + número de cuenta) dentro del RUC accesible. Solo tiene entradas para cuentas que
    // SÍ tienen con qué consolidar; controla cuándo se muestra el switch "Consolidar por RUC".
    window.CB_GRUPOS_CUENTAS = <?= json_encode(array_map('count', $gruposDeCuentas ?? []), JSON_UNESCAPED_UNICODE) ?>;
    window.CB_CONSOLIDADO_INICIAL = <?= $consolidado ? 'true' : 'false' ?>;
    // Cuentas sin cuenta contable: su detalle se arma desde los cobros y pagos registrados con
    // esa cuenta (empresas que no llevan contabilidad), no desde el mayor.
    window.CB_CUENTAS_SIN_CONTABILIDAD = <?= json_encode(array_values(array_map(
        static fn ($f) => (int) $f['id'],
        array_filter($formas, static fn ($f) => empty($f['id_cuenta_contable']))
    ))) ?>;
</script>
<?= \App\Helpers\PreferenciasHelper::getJavascriptVariables($rutaModulo) ?>
<?php include __DIR__ . '/../asientos_contables/modal_asiento.php'; ?>
<script src="<?= $base ?>/js/modulos/asientos_contables_modal.js?v=<?= time() ?>"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (!window.FiltrosBusqueda) return;
        new FiltrosBusqueda({
            containerId: 'fbBuscadorCB',
            hiddenInputId: 'cb-buscar',
            fields: [
                { key: 'numero_cheque', label: 'Nº Cheque', icon: 'bi-hash', type: 'text' },
                { key: 'tercero', label: 'Tercero', icon: 'bi-person', type: 'text' },
                { key: 'concepto', label: 'Concepto', icon: 'bi-chat-left-text', type: 'text' },
                { key: 'documento', label: 'Documento Ref.', icon: 'bi-file-text', type: 'text' },
                { key: 'tipo', label: 'Tipo', icon: 'bi-tag', type: 'select', options: [
                    { v: 'deposito', l: 'Depósito' }, { v: 'cheque', l: 'Cheque' }, { v: 'transferencia', l: 'Transferencia' },
                    { v: 'nota_debito', l: 'Nota Débito' }, { v: 'nota_credito', l: 'Nota Crédito' }, { v: 'otro', l: 'Otro' },
                ]},
                { key: 'direccion', label: 'Dirección (cheque)', icon: 'bi-arrow-left-right', type: 'select', options: [
                    { v: 'recibido', l: 'Recibido' }, { v: 'emitido', l: 'Emitido' },
                ]},
                { key: 'fecha_banco', label: 'Fecha Banco', icon: 'bi-calendar-check', type: 'date_range' },
                { key: 'debe', label: 'Monto Debe', icon: 'bi-currency-dollar', type: 'number_range' },
                { key: 'haber', label: 'Monto Haber', icon: 'bi-currency-dollar', type: 'number_range' },
            ],
            onApply: () => window.CB_fetchSearch && window.CB_fetchSearch(1),
        }).init();
    });
</script>
<script src="<?= $base ?>/js/modulos/control_bancario.js?v=<?= time() ?>"></script>
