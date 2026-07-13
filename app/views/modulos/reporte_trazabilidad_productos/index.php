<script>document.body.classList.add('cmg-no-app-shell');</script>

<style>
    .tzp-header { flex-shrink: 0; }
    .tzp-scroll { max-height: calc(100dvh - 240px); overflow-y: auto; }
    .tzp-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
    .tzp-linea.catalogo td:first-child { border-left: 3px solid #6f42c1; }
    .tzp-linea.entrada td:first-child { border-left: 3px solid #198754; }
    .tzp-linea.salida td:first-child { border-left: 3px solid #dc3545; }
    .tzp-linea.ajuste td:first-child { border-left: 3px solid #fd7e14; }
    .tzp-linea.documento td:first-child { border-left: 3px dashed #6c757d; }
</style>

<div class="container-fluid py-4 px-0 px-md-3" id="modulo-<?php echo basename($rutaModulo); ?>">
    <!-- ── Cabecera ── -->
    <div class="tzp-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h5 class="mb-0 fw-bold"><i class="bi bi-diagram-3 me-2 text-primary"></i>Trazabilidad de Productos</h5>
            <small class="text-muted">Historial completo de un producto: catálogo, inventario, compras, ventas, consignaciones, pedidos, proformas, órdenes de compra y guías de remisión</small>
        </div>
    </div>

    <!-- ── Filtros (Accordion) ── -->
    <div class="accordion mb-3 shadow-sm border-0" id="accordionFiltrosTzp">
        <div class="accordion-item border-0 rounded-3">
            <h2 class="accordion-header" id="headingFiltrosTzp">
                <button class="accordion-button bg-white text-dark py-2 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFiltrosTzp" aria-expanded="true" aria-controls="collapseFiltrosTzp">
                    <i class="bi bi-funnel me-2 text-primary"></i> <span class="fw-bold small">Filtros</span>
                </button>
            </h2>
            <div id="collapseFiltrosTzp" class="accordion-collapse collapse show" aria-labelledby="headingFiltrosTzp" data-bs-parent="#accordionFiltrosTzp">
                <div class="accordion-body bg-light bg-opacity-10 p-3 pt-2">
                    <div class="row g-3">
                        <div class="col-md-5 position-relative">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size: 0.65rem;">Producto (solo inventariables)</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control border-start-0 px-1 shadow-none" id="tzp-search-producto" placeholder="Buscar por nombre o código..." autocomplete="off">
                            </div>
                            <div id="tzp-dropdown-producto" class="list-group shadow dropdown-predictivo position-absolute d-none" style="z-index: 1050; width: calc(100% - 1.5rem); max-height: 250px; overflow-y: auto; margin-top: 2px;"></div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size: 0.65rem;">Fecha Desde</label>
                            <input type="date" class="form-control form-control-sm shadow-none border" id="tzp-fecha-desde">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size: 0.65rem;">Fecha Hasta</label>
                            <input type="date" class="form-control form-control-sm shadow-none border" id="tzp-fecha-hasta">
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="button" class="btn btn-primary btn-sm w-100" id="tzp-btn-buscar" disabled title="Actualizar">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                        </div>
                    </div>
                    <div id="tzp-producto-seleccionado" class="mt-2 small text-muted fst-italic">Seleccione un producto para ver su trazabilidad.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Tarjetas de Estadísticas ── -->
    <div class="row g-3 mb-3" id="tzp-kpis" style="display:none;">
        <div class="col-6 col-md-2">
            <div class="card border-0 rounded-4 shadow-sm h-100 bg-white">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-3 me-3 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                        <i class="bi bi-boxes fs-5"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 text-muted small fw-bold text-uppercase" style="font-size: 0.62rem;">Stock actual</h6>
                        <h5 class="mb-0 fw-bold" id="tzp-kpi-stock">-</h5>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card border-0 rounded-4 shadow-sm h-100 bg-white">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="bg-success bg-opacity-10 text-success rounded-3 p-3 me-3 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                        <i class="bi bi-box-arrow-in-down fs-5"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 text-muted small fw-bold text-uppercase" style="font-size: 0.62rem;">Entradas</h6>
                        <h5 class="mb-0 fw-bold text-success" id="tzp-kpi-entradas">-</h5>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card border-0 rounded-4 shadow-sm h-100 bg-white">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="bg-danger bg-opacity-10 text-danger rounded-3 p-3 me-3 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                        <i class="bi bi-box-arrow-up fs-5"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 text-muted small fw-bold text-uppercase" style="font-size: 0.62rem;">Salidas</h6>
                        <h5 class="mb-0 fw-bold text-danger" id="tzp-kpi-salidas">-</h5>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card border-0 rounded-4 shadow-sm h-100 bg-white">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="bg-warning bg-opacity-10 text-warning rounded-3 p-3 me-3 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                        <i class="bi bi-cash-stack fs-5"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 text-muted small fw-bold text-uppercase" style="font-size: 0.62rem;">Costo promedio</h6>
                        <h5 class="mb-0 fw-bold" id="tzp-kpi-costo">-</h5>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card border-0 rounded-4 shadow-sm h-100 bg-white">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="bg-info bg-opacity-10 text-info rounded-3 p-3 me-3 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                        <i class="bi bi-list-check fs-5"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 text-muted small fw-bold text-uppercase" style="font-size: 0.62rem;">Movimientos</h6>
                        <h5 class="mb-0 fw-bold" id="tzp-kpi-total">-</h5>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card border-0 rounded-4 shadow-sm h-100 bg-white">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="bg-secondary bg-opacity-10 text-secondary rounded-3 p-3 me-3 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                        <i class="bi bi-clock-history fs-5"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 text-muted small fw-bold text-uppercase" style="font-size: 0.62rem;">Último movimiento</h6>
                        <div class="fw-bold" style="font-size: 0.8rem;" id="tzp-kpi-ultimo">-</div>
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
