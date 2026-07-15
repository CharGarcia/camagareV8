<?php
/** @var string $titulo */
/** @var array $perm */
/** @var string $rutaModulo */
/** @var array $formas */
/** @var int $idFormaPago */
/** @var array $aniosDisponibles */
/** @var string $fechaInicio */
/** @var string $fechaFin */
/** @var array $saldos */
/** @var array $vistaConfig */

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
    .cb-row:hover { background-color: rgba(0,0,0,.04); }
</style>

<div class="container-fluid pt-2 pb-3 px-0 px-md-3" id="modulo-control_bancario">

    <div class="cb-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h5 class="mb-0 fw-bold"><i class="bi bi-bank me-2 text-primary"></i><?= htmlspecialchars($titulo) ?></h5>
            <small class="text-muted">Detalle de transacciones por cuenta bancaria, conciliación y seguimiento de cheques posfechados</small>
        </div>
        <button type="button" class="btn btn-outline-warning btn-sm" onclick="CB_abrirModalPosfechados()">
            <i class="bi bi-calendar-event me-1"></i> Cheques Posfechados
        </button>
    </div>

    <!-- ── Selector de cuenta + filtros de fecha ── -->
    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-body p-3">
            <form id="cb-form-filtros" class="row g-2 align-items-end" onsubmit="event.preventDefault(); window.CB_fetchSearch(1);">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">Cuenta Bancaria</label>
                    <select id="cb-forma" class="form-select form-select-sm shadow-none" onchange="window.CB_cambiarCuenta(this.value)">
                        <option value="">— Seleccione —</option>
                        <?php foreach ($formas as $f): ?>
                            <option value="<?= (int) $f['id'] ?>" <?= (int) $f['id'] === $idFormaPago ? 'selected' : '' ?>>
                                <?= htmlspecialchars($f['nombre'] . ($f['nombre_banco'] ? ' — ' . $f['nombre_banco'] : '') . ($f['numero_cuenta'] ? ' (' . $f['numero_cuenta'] . ')' : '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Año</label>
                    <select class="form-select form-select-sm shadow-none" id="cb-anio" onchange="window.CB_actualizarFechas()">
                        <?php foreach ($aniosDisponibles as $anio): ?>
                            <option value="<?= $anio ?>" <?= $anio === (int) date('Y') ? 'selected' : '' ?>><?= $anio ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
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
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Fecha Inicio</label>
                    <input type="date" class="form-control form-control-sm shadow-none" id="cb-fecha-inicio" value="<?= htmlspecialchars($fechaInicio) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Fecha Fin</label>
                    <input type="date" class="form-control form-control-sm shadow-none" id="cb-fecha-fin" value="<?= htmlspecialchars($fechaFin) ?>">
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary btn-sm w-100 shadow-sm"><i class="bi bi-search"></i></button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── KPI Saldos ── -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="card border-0 rounded-4 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="text-muted small fw-bold text-uppercase" style="font-size:.62rem;">Saldo Inicial</div>
                    <div class="fw-bold fs-5" id="cb-stat-saldo-inicial">$<?= number_format($saldos['saldo_inicial'] ?? 0, 2) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 rounded-4 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="text-muted small fw-bold text-uppercase" style="font-size:.62rem;">Saldo Actual</div>
                    <div class="fw-bold fs-5 text-primary" id="cb-stat-saldo-actual">$<?= number_format($saldos['saldo_actual'] ?? 0, 2) ?></div>
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
                            <th data-col="comprobante">Comprobante</th>
                            <th class="sortable-header" role="button" data-sort="tipo_transaccion" data-col="tipo">Tipo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th data-col="cheque">Cheque</th>
                            <th data-col="documento">Documento Ref.</th>
                            <th class="sortable-header" role="button" data-sort="nombre_entidad" data-col="tercero">Tercero <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th data-col="glosa">Glosa</th>
                            <th class="text-end sortable-header" role="button" data-sort="debe" data-col="debe">Debe <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="text-end sortable-header" role="button" data-sort="haber" data-col="haber">Haber <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="text-end pe-3" data-col="saldo">Saldo</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="cb-tbody">
                        <tr><td colspan="12" class="text-center py-5 text-muted"><i class="bi bi-bank fs-3 d-block mb-2"></i>Seleccione una cuenta bancaria.</td></tr>
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
                <div class="p-2 border rounded-3 bg-light mb-3">
                    <div class="row g-1 small">
                        <div class="col-6"><span class="text-muted">Fecha asiento:</span> <span id="cbm-info-fecha" class="fw-bold"></span></div>
                        <div class="col-6"><span class="text-muted">Comprobante:</span> <span id="cbm-info-comprobante" class="fw-bold"></span></div>
                        <div class="col-12"><span class="text-muted">Glosa:</span> <span id="cbm-info-glosa"></span></div>
                        <div class="col-12"><span class="text-muted">Monto:</span> <span id="cbm-info-monto" class="fw-bold"></span></div>
                    </div>
                </div>
                <div class="row g-2">
                    <div class="col-6">
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
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Fecha Banco (conciliación)</label>
                        <input type="date" id="cbm-fecha-banco" class="form-control form-control-sm shadow-none">
                    </div>
                    <div class="col-12 d-none row g-2" id="cbm-div-cheque">
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-1">Dirección del Cheque <span class="text-danger">*</span></label>
                            <select id="cbm-direccion" class="form-select form-select-sm shadow-none">
                                <option value="RECIBIDO">Recibido (cobro cliente)</option>
                                <option value="EMITIDO">Emitido (pago proveedor)</option>
                            </select>
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
                    <div class="col-12">
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
                </div>
            </div>
            <div class="modal-footer py-2 px-3">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
    const RUTA_MODULO_CB = "<?= $rutaModulo ?>";
    const CB_URL_BASE = "<?= $urlBase ?>";
    window.BASE_URL = '<?= $base ?>';
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
