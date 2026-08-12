<script>document.body.classList.add('cmg-no-app-shell');</script>

<style>
    .tzp-scroll { overflow-x: auto; }
    .tzp-scroll thead th { background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; white-space: nowrap; }
    .tzp-linea.catalogo td:first-child { border-left: 3px solid #6f42c1; }
    .tzp-linea.entrada td:first-child { border-left: 3px solid #198754; }
    .tzp-linea.salida td:first-child { border-left: 3px solid #dc3545; }
    .tzp-linea.ajuste td:first-child { border-left: 3px solid #fd7e14; }
    .tzp-linea.documento td:first-child { border-left: 3px dashed #6c757d; }
    /* Altura idéntica y explícita para todos los controles de filtros */
    #form-filtros-tzp .form-control,
    #form-filtros-tzp .input-group-text,
    #form-filtros-tzp .btn { height:28px; font-size:.75rem; }
    /* La tabla se extiende libremente hacia abajo; hace scroll la página, no un contenedor interno */
    @media (max-width: 767.98px) {
        #modulo-reporte_trazabilidad_productos .tzp-scroll { max-height:none !important; height:auto !important; overflow-y:visible !important; }
    }
</style>

<div class="container-fluid pt-0 pb-3 px-0 px-md-3" id="modulo-<?php echo basename($rutaModulo); ?>">

    <!-- ── Tarjeta de control fija (título + filtros + KPIs) ── -->
    <div class="card cmg-control-card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-header bg-white border-bottom py-2 px-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-diagram-3 me-2 text-primary"></i>Trazabilidad de Productos</h5>
        </div>
        <div class="card-body p-3">
            <form id="form-filtros-tzp" onsubmit="return false;">
                <div class="d-flex flex-wrap align-items-start gap-2">
                    <div class="position-relative" style="flex:1 1 250px;">
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Producto (solo inventariables)</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control border-start-0 px-1 shadow-none" id="tzp-search-producto" placeholder="Buscar por nombre o código..." autocomplete="off">
                        </div>
                        <div id="tzp-dropdown-producto" class="list-group shadow dropdown-predictivo position-absolute d-none" style="z-index:1050;width:100%;max-height:250px;overflow-y:auto;margin-top:2px;"></div>
                    </div>
                    <div>
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Fecha Desde</label>
                        <input type="date" class="form-control form-control-sm shadow-none border" style="width:115px;" id="tzp-fecha-desde">
                    </div>
                    <div>
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Fecha Hasta</label>
                        <input type="date" class="form-control form-control-sm shadow-none border" style="width:115px;" id="tzp-fecha-hasta">
                    </div>
                    <div>
                        <label class="form-label small fw-bold mb-1 d-block" style="font-size:.65rem;">&nbsp;</label>
                        <button type="button" class="btn btn-primary btn-sm shadow-sm" style="width:110px;" id="tzp-btn-buscar" disabled title="Actualizar">
                            <i class="bi bi-search me-1"></i>Mostrar
                        </button>
                    </div>
                </div>
                <div id="tzp-producto-seleccionado" class="mt-2 small text-muted fst-italic">Seleccione un producto para ver su trazabilidad.</div>
            </form>
        </div>
        <div class="card-footer bg-white border-top py-2 px-3" id="tzp-kpis" style="display:none;">
            <div class="cmg-control-card__stats">
                <div class="cmg-control-card__stat">
                    <i class="bi bi-boxes bg-primary bg-opacity-10 text-primary"></i>
                    <div>
                        <div class="cmg-control-card__stat-value" id="tzp-kpi-stock">-</div>
                        <div class="cmg-control-card__stat-label">Stock actual</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-box-arrow-in-down bg-success bg-opacity-10 text-success"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-success" id="tzp-kpi-entradas">-</div>
                        <div class="cmg-control-card__stat-label">Entradas</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-box-arrow-up bg-danger bg-opacity-10 text-danger"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-danger" id="tzp-kpi-salidas">-</div>
                        <div class="cmg-control-card__stat-label">Salidas</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-cash-stack bg-warning bg-opacity-10 text-warning"></i>
                    <div>
                        <div class="cmg-control-card__stat-value" id="tzp-kpi-costo">-</div>
                        <div class="cmg-control-card__stat-label">Costo promedio</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-list-check bg-info bg-opacity-10 text-info"></i>
                    <div>
                        <div class="cmg-control-card__stat-value" id="tzp-kpi-total">-</div>
                        <div class="cmg-control-card__stat-label">Movimientos</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-clock-history bg-secondary bg-opacity-10 text-secondary"></i>
                    <div>
                        <div class="cmg-control-card__stat-value" id="tzp-kpi-ultimo">-</div>
                        <div class="cmg-control-card__stat-label">Último movimiento</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="tzp-aviso-truncado" class="alert alert-warning py-2 small mb-2" style="display:none;">
        Se muestran los movimientos más recientes dentro del límite del reporte. Acote el rango de fechas para ver el historial completo.
    </div>

    <!-- ── Tarjeta Principal (Línea de tiempo) ── -->
    <div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-danger" id="tzp-btn-pdf" disabled title="Descargar PDF">
                    <i class="bi bi-file-earmark-pdf"></i> PDF
                </button>
                <button type="button" class="btn btn-outline-success" id="tzp-btn-excel" disabled title="Descargar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                </button>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted small fw-medium" id="tzp-info-total">&nbsp;</span>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="tzp-scroll w-100" id="trazabilidad-scroll">
                <table class="table table-hover table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Fecha</th>
                            <th>Evento</th>
                            <th>Documento</th>
                            <th>Contraparte</th>
                            <th class="text-center">Cantidad</th>
                            <th class="text-center">Saldo</th>
                            <th>Lote / NUP / Caducidad</th>
                            <th>Bodega</th>
                            <th class="pe-3">Usuario</th>
                        </tr>
                    </thead>
                    <tbody id="tzp-tbody">
                        <tr><td colspan="9" class="text-center py-5 text-muted"><i class="bi bi-filter-circle fs-3 d-block mb-2"></i>Seleccione un producto para ver su línea de tiempo.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    const RUTA_MODULO = "<?php echo $rutaModulo; ?>";
</script>
<script src="<?php echo BASE_URL; ?>/js/modulos/reporte_trazabilidad_productos.js?v=<?php echo time(); ?>"></script>
