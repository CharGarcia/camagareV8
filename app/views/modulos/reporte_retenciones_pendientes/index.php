<?php /** @var string $rutaModulo @var array $anios @var int $anioActual @var array $perm @var array $vistaConfig @var string $base */
$idModulo = basename($rutaModulo);
$columnasTabla = [
    'factura'   => 'Factura',
    'fecha'     => 'Fecha',
    'dias'      => 'Días',
    'cliente'   => 'Cliente',
    'correo'    => 'Correo',
    'subtotal'  => 'Subtotal',
    'impuestos' => 'Impuestos',
    'total'     => 'Total',
    'avisos'    => 'Avisos',
    'acciones'  => 'Acciones',
];
?>
<script>document.body.classList.add('cmg-no-app-shell');</script>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<style>
    .rrp-scroll { overflow-x:auto; }
    .rrp-scroll thead th { background:#f8f9fa; box-shadow:0 1px 0 #dee2e6; white-space:nowrap; }
    .rrp-scroll tbody tr[data-doc-id] { cursor:pointer; }
    .rrp-scroll td { font-size:.8rem; }
    .rrp-grupo { background:#f1f3f5 !important; cursor:pointer; }
    .rrp-grupo td { font-size:.82rem; }
    .badge-aviso-si { background:rgba(25,135,84,.12);  color:#198754; border:1px solid rgba(25,135,84,.25); }
    .badge-aviso-no { background:rgba(108,117,125,.12); color:#6c757d; border:1px solid rgba(108,117,125,.25); }
    .badge-dias-30  { background:rgba(255,193,7,.15);  color:#856404; border:1px solid rgba(255,193,7,.35); }
    .badge-dias-60  { background:rgba(220,53,69,.12);  color:#dc3545; border:1px solid rgba(220,53,69,.25); }
    #rrp-chips-cliente:empty { margin-top:0; }
    /* Altura idéntica y explícita para todos los controles de filtros */
    #form-filtros-rrp .form-select,
    #form-filtros-rrp .form-control,
    #form-filtros-rrp .input-group-text,
    #form-filtros-rrp .btn { height:28px; font-size:.75rem; }
    /* La tabla se extiende libremente hacia abajo; hace scroll la página, no un contenedor interno */
    @media (max-width: 767.98px) {
        #modulo-<?= $idModulo ?> .rrp-scroll { max-height:none !important; height:auto !important; overflow-y:visible !important; }
    }
</style>

<div class="container-fluid pt-0 pb-3 px-0 px-md-3" id="modulo-<?= $idModulo ?>">

    <!-- ── Tarjeta de control fija (título + filtros + KPIs) ── -->
    <div class="card cmg-control-card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-header bg-white border-bottom py-2 px-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-receipt-cutoff me-2 text-warning"></i>Retenciones de Venta Pendientes</h5>
        </div>
        <div class="card-body p-3">
            <form id="form-filtros-rrp" onsubmit="event.preventDefault(); RRP_cargar();" class="d-flex flex-wrap align-items-start gap-2">

                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Año</label>
                    <select id="rrp-anio" name="anio" class="form-select form-select-sm shadow-none border" style="width:90px;">
                        <?php foreach ($anios as $a): ?>
                            <option value="<?= (int)$a ?>" <?= (int)$a === $anioActual ? 'selected' : '' ?>><?= (int)$a ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Mes</label>
                    <select id="rrp-mes" name="mes" class="form-select form-select-sm shadow-none border" style="width:115px;">
                        <option value="">Todos</option>
                        <?php $meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
                        foreach ($meses as $i => $m): ?>
                            <option value="<?= $i + 1 ?>"><?= $m ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Desde</label>
                    <input type="date" id="rrp-fecha-desde" name="fecha_desde" class="form-control form-control-sm shadow-none border" style="width:115px;">
                </div>
                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Hasta</label>
                    <input type="date" id="rrp-fecha-hasta" name="fecha_hasta" class="form-control form-control-sm shadow-none border" style="width:115px;">
                </div>

                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Aviso por correo</label>
                    <select id="rrp-aviso" name="aviso" class="form-select form-select-sm shadow-none border" style="width:130px;">
                        <option value="TODOS" selected>Todos</option>
                        <option value="SIN">Sin aviso</option>
                        <option value="CON">Con aviso</option>
                    </select>
                </div>

                <!-- Cliente + Botones: agrupados para que nunca se separen al hacer wrap -->
                <div class="d-flex flex-wrap align-items-start gap-2">
                    <div class="position-relative" style="width:440px;">
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Cliente</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" id="rrp-search-cliente" class="form-control border-start-0 px-1 shadow-none"
                                   placeholder="Nombre / RUC…" autocomplete="off">
                            <input type="hidden" id="rrp-id-cliente" name="id_cliente" value="">
                        </div>
                        <div id="rrp-chips-cliente" class="d-flex flex-wrap gap-1 mt-1"></div>
                        <div id="rrp-dropdown-clientes" class="list-group shadow position-absolute d-none"
                             style="z-index:1050;width:100%;max-height:220px;overflow-y:auto;margin-top:2px;"></div>
                    </div>
                    <div>
                        <label class="form-label small fw-bold mb-1 d-block" style="font-size:.65rem;">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm px-2" onclick="RRP_limpiarFiltros()">
                                <i class="bi bi-eraser me-1"></i>Limpiar
                            </button>
                            <button type="submit" class="btn btn-warning btn-sm px-3 shadow-sm">
                                <i class="bi bi-search me-1"></i>Buscar
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-footer bg-white border-top py-2 px-3">
            <div class="cmg-control-card__stats">
                <div class="cmg-control-card__stat">
                    <i class="bi bi-receipt bg-warning bg-opacity-10 text-warning"></i>
                    <div>
                        <div class="cmg-control-card__stat-value" id="rrp-kpi-facturas">0</div>
                        <div class="cmg-control-card__stat-label">Facturas sin retención</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-people bg-secondary bg-opacity-10 text-secondary"></i>
                    <div>
                        <div class="cmg-control-card__stat-value" id="rrp-kpi-clientes">0</div>
                        <div class="cmg-control-card__stat-label">Clientes</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-calculator bg-secondary bg-opacity-10 text-secondary"></i>
                    <div>
                        <div class="cmg-control-card__stat-value" id="rrp-kpi-subtotal">$0.00</div>
                        <div class="cmg-control-card__stat-label">Subtotal (base)</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-cash-stack bg-primary bg-opacity-10 text-primary"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-primary" id="rrp-kpi-total">$0.00</div>
                        <div class="cmg-control-card__stat-label">Total facturado</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-envelope-check bg-success bg-opacity-10 text-success"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-success" id="rrp-kpi-avisadas">0</div>
                        <div class="cmg-control-card__stat-label">Con aviso enviado</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-envelope-x bg-danger bg-opacity-10 text-danger"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-danger" id="rrp-kpi-sin-correo">0</div>
                        <div class="cmg-control-card__stat-label">Clientes sin correo</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Tabla ── -->
    <div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-2 px-3 border-bottom">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <div class="form-check mb-0 me-1">
                        <input class="form-check-input" type="checkbox" id="rrp-chk-all" onchange="RRP_seleccionarTodos(this.checked)">
                        <label class="form-check-label small text-muted" for="rrp-chk-all">Todos</label>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>
                        <button type="button" class="btn btn-outline-danger" id="rrpBtnPdf" disabled><i class="bi bi-file-earmark-pdf"></i> PDF</button>
                        <button type="button" class="btn btn-outline-success" id="rrpBtnExcel" disabled><i class="bi bi-file-earmark-spreadsheet"></i> Excel</button>
                        <button type="button" class="btn btn-outline-primary" onclick="RRP_abrirEnvioAgrupado()"
                                title="Un solo correo por cliente con todas sus facturas seleccionadas">
                            <i class="bi bi-envelope-paper"></i> Aviso agrupado
                        </button>
                    </div>
                    <div class="btn-group btn-group-sm ms-1" role="group" aria-label="Ver por">
                        <button type="button" id="rrp-btn-detalle" class="btn btn-warning" onclick="RRP_setVista('detalle')" title="Una fila por factura">
                            <i class="bi bi-list-ul"></i> Facturas
                        </button>
                        <button type="button" id="rrp-btn-cliente" class="btn btn-outline-warning" onclick="RRP_setVista('cliente')" title="Agrupar por cliente">
                            <i class="bi bi-people"></i> Por cliente
                        </button>
                        <button type="button" id="rrp-btn-mes" class="btn btn-outline-warning" onclick="RRP_setVista('mes')" title="Agrupar por mes de emisión">
                            <i class="bi bi-calendar3"></i> Por mes
                        </button>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <small class="text-muted fw-medium" id="rrp-count-label"></small>
                    <input type="search" class="form-control form-control-sm shadow-none border" style="width:200px;"
                           id="rrp-buscador" placeholder="Filtrar tabla..." oninput="RRP_filtrarTabla(this.value)">
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="rrp-scroll w-100">
                <table class="table table-hover table-sm mb-0 align-middle" id="tabla-rrp" style="min-width:1100px;">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center p-1" style="width:36px;"></th>
                            <th class="ps-2" data-col="factura">Factura</th>
                            <th data-col="fecha">Fecha</th>
                            <th class="text-center" data-col="dias">Días</th>
                            <th data-col="cliente">Cliente</th>
                            <th data-col="correo">Correo</th>
                            <th class="text-end" data-col="subtotal">Subtotal</th>
                            <th class="text-end" data-col="impuestos">Impuestos</th>
                            <th class="text-end" data-col="total">Total</th>
                            <th class="text-center" data-col="avisos">Avisos</th>
                            <th class="text-center pe-2" data-col="acciones">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="rrp-tbody">
                        <tr><td colspan="11" class="text-center py-5 text-muted">
                            <i class="bi bi-receipt-cutoff fs-3 d-block mb-2 text-warning opacity-50"></i>
                            Cargando facturas sin retención…
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL: Enviar aviso por correo (una factura)
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalEmailRRP" tabindex="-1" aria-labelledby="modalEmailRRPLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:520px;">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header py-2 px-3" style="background:#0d6efd;">
                <h6 class="modal-title fw-bold text-white" id="modalEmailRRPLabel"><i class="bi bi-envelope me-2"></i>Aviso de retención pendiente</h6>
                <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <input type="hidden" id="rrp-email-id-venta">
                <p class="text-muted small mb-2" id="rrp-email-subtitulo"></p>
                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Correo destinatario <span class="text-danger">*</span></label>
                        <input type="text" id="rrp-email-destino" class="form-control form-control-sm shadow-none"
                               placeholder="cliente@correo.com (varios separados por coma)">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Asunto</label>
                        <input type="text" id="rrp-email-asunto" class="form-control form-control-sm shadow-none"
                               placeholder="Se completará automáticamente si se deja vacío">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Mensaje adicional (opcional)</label>
                        <textarea id="rrp-email-mensaje" class="form-control form-control-sm shadow-none" rows="4"
                                  placeholder="Texto que se incluirá en el correo junto a los datos de la factura…"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2 px-3">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm px-4" id="rrp-btn-enviar-email" onclick="RRP_enviarEmail()">
                    <i class="bi bi-send me-1"></i>Enviar aviso
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL: Aviso agrupado (un correo por cliente, revisión de correos)
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalEmailAgrupadoRRP" tabindex="-1" aria-labelledby="modalEmailAgrupadoRRPLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header py-2 px-3" style="background:#0d6efd;">
                <h6 class="modal-title fw-bold text-white" id="modalEmailAgrupadoRRPLabel">
                    <i class="bi bi-envelope-paper me-2"></i>Aviso agrupado de retenciones pendientes
                </h6>
                <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <p class="small mb-2" id="rrp-agrupado-resumen"></p>
                <div class="table-responsive border rounded-2" style="max-height:45vh;overflow-y:auto;">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light" style="position:sticky;top:0;z-index:5;">
                            <tr>
                                <th class="ps-2">Cliente</th>
                                <th class="text-center" style="width:70px;">Facturas</th>
                                <th class="text-end" style="width:110px;">Total</th>
                                <th style="width:42%;">Correo destinatario</th>
                            </tr>
                        </thead>
                        <tbody id="rrp-agrupado-tbody"></tbody>
                    </table>
                </div>
                <div class="mt-2">
                    <label class="form-label small fw-bold mb-1">Mensaje adicional (opcional, se incluye en todos los correos)</label>
                    <textarea id="rrp-agrupado-mensaje" class="form-control form-control-sm shadow-none" rows="2"></textarea>
                </div>
                <div class="form-text mt-2">
                    <i class="bi bi-info-circle me-1"></i>Cada cliente recibe <strong>un solo correo</strong> con la tabla de sus facturas
                    sin retención. Revise y corrija los correos antes de enviar; puede poner varios destinatarios separados por coma.
                    Deje el correo vacío para omitir a ese cliente. Los cambios aplican solo a este envío (no modifican la ficha del cliente).
                </div>
            </div>
            <div class="modal-footer py-2 px-3">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm px-4" id="rrp-btn-enviar-agrupado" onclick="RRP_confirmarEnvioAgrupado()">
                    <i class="bi bi-send me-1"></i>Enviar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL: Historial de avisos enviados de una factura
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalAvisosRRP" tabindex="-1" aria-labelledby="modalAvisosRRPLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-secondary text-white py-2 px-3">
                <h6 class="modal-title fw-bold" id="modalAvisosRRPLabel"><i class="bi bi-clock-history me-2"></i>Avisos enviados</h6>
                <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <p class="text-muted small mb-2" id="rrp-avisos-subtitulo"></p>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha y hora</th>
                                <th>Tipo</th>
                                <th>Correo(s)</th>
                                <th>Asunto</th>
                                <th>Usuario</th>
                            </tr>
                        </thead>
                        <tbody id="rrp-avisos-tbody">
                            <tr><td colspan="5" class="text-center text-muted">Cargando…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2 px-3">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php // Panel lateral con el detalle de la factura (clic sobre una fila)
require_once MVC_APP . '/views/partials/offcanvas_doc_preview.php'; ?>

<?= \App\Helpers\PreferenciasHelper::getJavascriptVariables($rutaModulo) ?>
<script>
    const RUTA_MODULO_RRP = "<?= $rutaModulo ?>";
    const RRP_ANIO_ACTUAL = <?= (int) $anioActual ?>;
</script>
<script src="<?= $base ?>/js/modulos/reporte_retenciones_pendientes.js?v=<?= time() ?>"></script>
