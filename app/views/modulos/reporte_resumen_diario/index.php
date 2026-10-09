<?php /** @var string $rutaModulo @var string $base */ ?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<style>
    .rrd-scroll { overflow-x: auto; }
    .rrd-scroll thead th { background: #f8f9fa; white-space: nowrap; }
    /* Altura idéntica y explícita para todos los controles de filtros */
    #form-filtros-rrd .form-select,
    #form-filtros-rrd .form-control,
    #form-filtros-rrd .btn { height:28px; font-size:.75rem; }
    /* El contenido se extiende libremente hacia abajo; hace scroll la página, no un contenedor interno */
    @media (max-width: 767.98px) {
        #modulo-reporte_resumen_diario .rrd-scroll { max-height:none !important; height:auto !important; overflow-y:visible !important; }
    }
</style>

<div class="container-fluid pt-0 pb-3 px-0 px-md-3" id="modulo-reporte_resumen_diario">

    <!-- ── Tarjeta de control fija (título + filtros + KPIs) ── -->
    <div class="card cmg-control-card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-header bg-white border-bottom py-2 px-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-calendar-day me-2 text-primary"></i>Resumen Diario</h5>
        </div>
        <div class="card-body p-3">
            <form id="form-filtros-rrd" class="d-flex flex-wrap align-items-start gap-2" onsubmit="return false;">
                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;" for="rrd-fecha">Día</label>
                    <div class="d-flex gap-1">
                        <button type="button" class="btn btn-outline-secondary btn-sm px-2" id="rrdBtnAnterior" title="Día anterior"><i class="bi bi-chevron-left"></i></button>
                        <input type="date" id="rrd-fecha" class="form-control form-control-sm shadow-none border" style="width:125px;" value="<?= date('Y-m-d') ?>">
                        <button type="button" class="btn btn-outline-secondary btn-sm px-2" id="rrdBtnSiguiente" title="Día siguiente"><i class="bi bi-chevron-right"></i></button>
                    </div>
                </div>
                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;" for="rrd-borradores">Borradores</label>
                    <select id="rrd-borradores" class="form-select form-select-sm shadow-none border" style="width:150px;"
                            title="Facturas, notas, liquidaciones y retenciones electrónicas aún no autorizadas">
                        <option value="">Sin borradores</option>
                        <option value="INCLUIR">Con borradores</option>
                    </select>
                </div>
                <div>
                    <label class="form-label small fw-bold mb-1 d-block" style="font-size:.65rem;">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="rrdBtnLimpiar" title="Volver a hoy"><i class="bi bi-eraser me-1"></i>Limpiar</button>
                        <button type="button" class="btn btn-primary btn-sm shadow-sm" id="rrdBtnMostrar"><i class="bi bi-search me-1"></i>Mostrar</button>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-footer bg-white border-top py-2 px-3">
            <div class="cmg-control-card__stats">
                <div class="cmg-control-card__stat">
                    <i class="bi bi-receipt bg-success bg-opacity-10 text-success"></i>
                    <div>
                        <div class="cmg-control-card__stat-value" id="rrd-kpi-ventas">$0.00</div>
                        <div class="cmg-control-card__stat-label">Ventas netas</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-cart bg-danger bg-opacity-10 text-danger"></i>
                    <div>
                        <div class="cmg-control-card__stat-value" id="rrd-kpi-compras">$0.00</div>
                        <div class="cmg-control-card__stat-label">Compras netas</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-arrow-down-circle bg-success bg-opacity-10 text-success"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-success" id="rrd-kpi-ingresos">$0.00</div>
                        <div class="cmg-control-card__stat-label">Ingresos (cobros)</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-arrow-up-circle bg-danger bg-opacity-10 text-danger"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-danger" id="rrd-kpi-egresos">$0.00</div>
                        <div class="cmg-control-card__stat-label">Egresos (pagos)</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-wallet2 bg-primary bg-opacity-10 text-primary"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-primary" id="rrd-kpi-neto">$0.00</div>
                        <div class="cmg-control-card__stat-label">Neto de caja</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Detalle del día ── -->
    <div class="card w-100 border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-2 px-3 border-bottom">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-danger" id="rrdBtnPdf" disabled><i class="bi bi-file-earmark-pdf"></i> PDF</button>
                    <button type="button" class="btn btn-outline-success" id="rrdBtnExcel" disabled><i class="bi bi-file-earmark-spreadsheet"></i> Excel</button>
                </div>
                <span class="text-muted small" id="rrd-titulo-dia"></span>
            </div>
        </div>
        <div class="card-body p-0 pb-2" id="rrd-contenido">
            <div class="text-center py-5 text-muted"><i class="bi bi-calendar-day fs-3 d-block mb-2"></i>Elija el día y presione <strong>Mostrar</strong>.</div>
        </div>
    </div>
</div>

<script>
    window.RRD_RUTA = <?= json_encode($rutaModulo) ?>;
    window.RRD_BASE = <?= json_encode($base) ?>;
    window.RRD_HOY  = <?= json_encode(date('Y-m-d')) ?>;
</script>
<script src="<?= $base ?>/js/modulos/reporte_resumen_diario.js?v=<?= asset_ver('/js/modulos/reporte_resumen_diario.js') ?>"></script>
