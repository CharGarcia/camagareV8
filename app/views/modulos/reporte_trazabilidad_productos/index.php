<script>document.body.classList.add('cmg-no-app-shell');</script>

<style>
    .tzp-header { flex-shrink: 0; }
    .tzp-scroll { max-height: 60vh; overflow-y: auto; }
    .tzp-scroll thead th { position: sticky; top: 0; z-index: 10; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
    .tzp-kpi { border-radius: .5rem; }
    .tzp-linea { border-left: 3px solid #dee2e6; }
    .tzp-linea.catalogo { border-left-color: #6f42c1; }
    .tzp-linea.entrada { border-left-color: #198754; }
    .tzp-linea.salida { border-left-color: #dc3545; }
    .tzp-linea.ajuste { border-left-color: #fd7e14; }
</style>

<div class="container-fluid py-4 px-0 px-md-3" id="modulo-<?php echo basename($rutaModulo); ?>">
    <!-- ── Cabecera ── -->
    <div class="tzp-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h5 class="mb-0 fw-bold"><i class="bi bi-diagram-3 me-2 text-primary"></i>Trazabilidad de Productos</h5>
            <small class="text-muted">Historial completo de un producto: catálogo, compras, ventas, consignaciones e inventario</small>
        </div>
        <div class="d-flex gap-1">
            <button type="button" class="btn btn-sm btn-outline-danger" id="tzp-btn-pdf" disabled title="Exportar PDF"><i class="bi bi-file-earmark-pdf"></i></button>
            <button type="button" class="btn btn-sm btn-outline-success" id="tzp-btn-excel" disabled title="Exportar Excel"><i class="bi bi-file-earmark-excel"></i></button>
        </div>
    </div>

    <!-- ── Selector de producto + filtros ── -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <div class="row g-3">
                <div class="col-md-5 position-relative">
                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Producto (solo inventariables)</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control border-start-0 px-1 shadow-none" id="tzp-search-producto" placeholder="Buscar por nombre o código..." autocomplete="off">
                    </div>
                    <div id="tzp-dropdown-producto" class="list-group shadow dropdown-predictivo position-absolute d-none" style="z-index:1050; width:calc(100% - 1.5rem); max-height:250px; overflow-y:auto; margin-top:2px;"></div>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Desde</label>
                    <input type="date" class="form-control form-control-sm shadow-none border" id="tzp-fecha-desde">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size:.65rem;">Hasta</label>
                    <input type="date" class="form-control form-control-sm shadow-none border" id="tzp-fecha-hasta">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="button" class="btn btn-primary btn-sm w-100" id="tzp-btn-buscar" disabled title="Actualizar"><i class="bi bi-arrow-clockwise"></i></button>
                </div>
            </div>
            <div id="tzp-producto-seleccionado" class="mt-2 small text-muted fst-italic">Seleccione un producto para ver su trazabilidad.</div>
        </div>
    </div>

    <!-- ── KPIs ── -->
    <div class="row g-2 mb-3" id="tzp-kpis" style="display:none;">
        <div class="col-6 col-md-2">
            <div class="tzp-kpi bg-white border shadow-sm p-2 text-center h-100">
                <div class="text-muted text-uppercase" style="font-size:.62rem;">Stock actual</div>
                <div class="fw-bold fs-6" id="tzp-kpi-stock">-</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="tzp-kpi bg-white border shadow-sm p-2 text-center h-100">
                <div class="text-muted text-uppercase" style="font-size:.62rem;">Entradas</div>
                <div class="fw-bold fs-6 text-success" id="tzp-kpi-entradas">-</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="tzp-kpi bg-white border shadow-sm p-2 text-center h-100">
                <div class="text-muted text-uppercase" style="font-size:.62rem;">Salidas</div>
                <div class="fw-bold fs-6 text-danger" id="tzp-kpi-salidas">-</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="tzp-kpi bg-white border shadow-sm p-2 text-center h-100">
                <div class="text-muted text-uppercase" style="font-size:.62rem;">Costo promedio</div>
                <div class="fw-bold fs-6" id="tzp-kpi-costo">-</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="tzp-kpi bg-white border shadow-sm p-2 text-center h-100">
                <div class="text-muted text-uppercase" style="font-size:.62rem;">Movimientos</div>
                <div class="fw-bold fs-6" id="tzp-kpi-total">-</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="tzp-kpi bg-white border shadow-sm p-2 text-center h-100">
                <div class="text-muted text-uppercase" style="font-size:.62rem;">Último movimiento</div>
                <div class="fw-bold" style="font-size:.78rem;" id="tzp-kpi-ultimo">-</div>
            </div>
        </div>
    </div>

    <div id="tzp-aviso-truncado" class="alert alert-warning py-2 small mb-2" style="display:none;">
        Se muestran los movimientos más recientes dentro del límite del reporte. Acote el rango de fechas para ver el historial completo.
    </div>

    <!-- ── Línea de tiempo ── -->
    <div class="card border-0 shadow-sm cmg-table-card">
        <div class="card-body p-0">
            <div class="table-responsive tzp-scroll" id="trazabilidad-scroll">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-3">Fecha</th>
                            <th>Evento</th>
                            <th>Documento</th>
                            <th>Contraparte</th>
                            <th class="text-center">Cantidad</th>
                            <th class="text-center">Saldo</th>
                            <th>Lote / NUP / Caducidad</th>
                            <th>Bodega</th>
                            <th>Usuario</th>
                        </tr>
                    </thead>
                    <tbody id="tzp-tbody">
                        <tr><td colspan="9" class="text-center py-5 text-muted">Seleccione un producto para ver su línea de tiempo.</td></tr>
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
