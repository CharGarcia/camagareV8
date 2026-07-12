<?php $idModulo = basename($rutaModulo); ?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<style>
    .rv-header { flex-shrink: 0; }
    .rv-scroll { max-height: 500px; overflow-y: auto; }
    .rv-scroll thead th { position: sticky; top: 0; z-index: 10; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
</style>

<div class="container-fluid py-4 px-0 px-md-3" id="modulo-<?php echo $idModulo; ?>">
    <!-- ── Cabecera ── -->
    <div class="rv-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h5 class="mb-0 fw-bold"><i class="bi bi-file-earmark-bar-graph me-2 text-primary"></i>Reporte de Ventas</h5>
            <small class="text-muted">Análisis detallado de ventas por cliente, producto y fecha</small>
        </div>
    </div>

    <!-- ── Filtros Avanzados (Accordion) ── -->
    <div class="accordion mb-3 shadow-sm border-0" id="accordionFiltros">
        <div class="accordion-item border-0 rounded-3">
            <h2 class="accordion-header" id="headingFiltros">
                <button class="accordion-button bg-white text-dark py-2 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFiltros" aria-expanded="true" aria-controls="collapseFiltros">
                    <i class="bi bi-funnel me-2 text-primary"></i> <span class="fw-bold small">Filtros Avanzados</span>
                </button>
            </h2>
            <div id="collapseFiltros" class="accordion-collapse collapse show" aria-labelledby="headingFiltros" data-bs-parent="#accordionFiltros">
                <div class="accordion-body bg-light bg-opacity-10 p-3 pt-2">
                    <form id="form-filtros-reporte" onsubmit="event.preventDefault(); window.RV_generarReporte();" class="row g-3">
                        
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase d-flex align-items-center" style="font-size: 0.65rem;">
                                Tipo de Documento
                                <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito($rutaModulo, 'rv_tipo_documento', 'tipo_documento') ?>
                            </label>
                            <select name="tipo_documento" id="rv_tipo_documento" class="form-select form-select-sm shadow-none border">
                                <option value="FACTURA" selected>Facturas de Venta</option>
                                <option value="RECIBO" disabled>Recibos (Próximamente)</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase d-flex align-items-center" style="font-size: 0.65rem;">
                                Agrupar Por
                                <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito($rutaModulo, 'rv_agrupar_por', 'agrupar_por') ?>
                            </label>
                            <select name="agrupar_por" id="rv_agrupar_por" class="form-select form-select-sm shadow-none border" onchange="window.RV_onAgruparChange()">
                                <option value="NINGUNO" selected>Detallado (Ninguno)</option>
                                <option value="CLIENTE">Por Cliente</option>
                                <option value="PRODUCTO">Por Producto</option>
                                <option value="FECHA">Por Fecha</option>
                                <option value="MES">Por Mes</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size: 0.65rem;">Mes</label>
                            <select id="rv-mes" class="form-select form-select-sm shadow-none border">
                                <option value="TODOS">Todos</option>
                                <option value="01">Enero</option>
                                <option value="02">Febrero</option>
                                <option value="03">Marzo</option>
                                <option value="04">Abril</option>
                                <option value="05">Mayo</option>
                                <option value="06">Junio</option>
                                <option value="07">Julio</option>
                                <option value="08">Agosto</option>
                                <option value="09">Septiembre</option>
                                <option value="10">Octubre</option>
                                <option value="11">Noviembre</option>
                                <option value="12">Diciembre</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size: 0.65rem;">Año</label>
                            <select id="rv-anio" class="form-select form-select-sm shadow-none border" onchange="window.RV_cambiarMesAnio()">
                                <option value="TODOS">Todos</option>
                                <?php foreach (($anios ?? [date('Y')]) as $a): ?>
                                    <option value="<?= htmlspecialchars($a) ?>" <?= $a == date('Y') ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size: 0.65rem;">Fecha Desde</label>
                            <input type="date" name="fecha_desde" id="rv-fecha-desde" class="form-control form-control-sm shadow-none border" value="<?php echo date('Y-m-01'); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size: 0.65rem;">Fecha Hasta</label>
                            <input type="date" name="fecha_hasta" id="rv-fecha-hasta" class="form-control form-control-sm shadow-none border" value="<?php echo date('Y-m-t'); ?>">
                        </div>

                        <div class="w-100 d-none d-md-block m-0"></div>

                        <div class="col-md-3 position-relative">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size: 0.65rem;">Cliente</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control border-start-0 px-1 shadow-none" id="rv-search-cliente" placeholder="Buscar clientes..." autocomplete="off">
                            </div>
                            <div id="rv-chips-cliente" class="d-flex flex-column gap-1 mt-2"></div>
                            <div id="rv-dropdown-clientes" class="list-group shadow dropdown-predictivo position-absolute d-none" style="z-index: 1050; width: calc(100% - 1.5rem); max-height: 250px; overflow-y: auto; margin-top: 2px;"></div>
                        </div>

                        <div class="col-md-3 position-relative">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size: 0.65rem;">Producto</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                                <input type="text" name="producto_texto" id="rv-producto-texto" class="form-control border-start-0 px-1 shadow-none"
                                       placeholder="Ej: pelota, servicio..." autocomplete="off">
                                <button type="button" class="btn btn-outline-secondary" title="Limpiar"
                                        onclick="document.getElementById('rv-producto-texto').value=''; window.RV_generarReporte();"><i class="bi bi-x-lg"></i></button>
                            </div>
                            <div id="rv-dropdown-items" class="list-group shadow dropdown-predictivo position-absolute d-none" style="z-index: 1050; width: calc(100% - 1.5rem); max-height: 250px; overflow-y: auto; margin-top: 2px;"></div>
                            <small class="text-muted" style="font-size:.62rem;">Busca por el ítem/descripción de las líneas de venta.</small>
                        </div>

                        <div class="col-md-3 position-relative">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size: 0.65rem;"><i class="bi bi-card-text me-1"></i>Buscar en info adicional</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-card-text"></i></span>
                                <input type="text" name="buscar_info" id="rv-buscar-info" class="form-control border-start-0 px-1 shadow-none"
                                       placeholder="Ej: placa, referencia..." autocomplete="off">
                                <button type="button" class="btn btn-outline-secondary" title="Limpiar"
                                        onclick="document.getElementById('rv-buscar-info').value=''; window.RV_generarReporte();"><i class="bi bi-x-lg"></i></button>
                            </div>
                            <div id="rv-dropdown-info" class="list-group shadow dropdown-predictivo position-absolute d-none" style="z-index: 1050; width: calc(100% - 1.5rem); max-height: 250px; overflow-y: auto; margin-top: 2px;"></div>
                            <small class="text-muted" style="font-size:.62rem;">Campos adicionales del documento (nombre o valor).</small>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-bold mb-1 d-block" style="font-size: 0.65rem;">&nbsp;</label>
                            <button type="submit" class="btn btn-primary btn-sm shadow-sm w-100" id="btn-generar-reporte">
                                <i class="bi bi-search me-1"></i> Aplicar y Generar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Tarjetas de Estadísticas ── -->
    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card border-0 rounded-4 shadow-sm h-100 bg-white">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-3 me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-receipt fs-4"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-0 text-muted small fw-bold text-uppercase" style="font-size: 0.65rem;">Doc. Autorizados</h6>
                        <h4 class="mb-0 fw-bold" style="font-family: 'Outfit', sans-serif;" id="stat-documentos">0</h4>
                        <div class="d-flex gap-2 mt-1">
                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary" style="font-size: 0.6rem;">Borradores: <span id="stat-borradores">0</span></span>
                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger" style="font-size: 0.6rem;">Anulados: <span id="stat-anulados">0</span></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 rounded-4 shadow-sm h-100 bg-white">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="bg-info bg-opacity-10 text-info rounded-3 p-3 me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-percent fs-4"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 text-muted small fw-bold text-uppercase" style="font-size: 0.65rem;">Subtotal (0% / Exento)</h6>
                        <h4 class="mb-0 fw-bold" style="font-family: 'Outfit', sans-serif;">$<span id="stat-base-0">0.00</span></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 rounded-4 shadow-sm h-100 bg-white">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="bg-warning bg-opacity-10 text-warning rounded-3 p-3 me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-graph-up fs-4"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 text-muted small fw-bold text-uppercase" style="font-size: 0.65rem;">Base IVA + Impuesto</h6>
                        <h4 class="mb-0 fw-bold" style="font-family: 'Outfit', sans-serif;">$<span id="stat-base-iva">0.00</span></h4>
                        <small class="text-muted" style="font-size: 0.7rem;">IVA: $<span id="stat-iva">0.00</span></small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 rounded-4 shadow-sm h-100 bg-white">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="bg-success bg-opacity-10 text-success rounded-3 p-3 me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-cash-stack fs-4"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 text-muted small fw-bold text-uppercase" style="font-size: 0.65rem;">Gran Total</h6>
                        <h4 class="mb-0 fw-bold text-success" style="font-family: 'Outfit', sans-serif;">$<span id="stat-total">0.00</span></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Gráficos ── -->
    <div class="card border-0 shadow-sm mb-4" id="chart-container" style="display: none;">
        <div class="card-header bg-white border-bottom-0 pt-4 pb-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h6 class="mb-0 fw-bold text-dark" style="font-family: 'Outfit', sans-serif;"><i class="bi bi-graph-up text-primary me-2"></i>Gráfico de Ventas</h6>
            <div class="d-flex align-items-center gap-2">
                <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito($rutaModulo, 'rv-tipo-grafico', 'tipo_grafico') ?>
                <select id="rv-tipo-grafico" class="form-select form-select-sm shadow-none border" style="width: 140px;" onchange="window.RV_cambiarTipoGrafico()">
                    <option value="auto">Automático</option>
                    <option value="bar">Barras</option>
                    <option value="line">Líneas</option>
                    <option value="pie">Pastel</option>
                    <option value="doughnut">Dona</option>
                </select>
            </div>
        </div>
        <div class="card-body">
            <canvas id="reporteChart" style="max-height: 300px;"></canvas>
        </div>
    </div>

    <!-- ── Tarjeta Principal (Tabla) ── -->
    <div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-2 px-3 border-bottom">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <!-- Buscador rápido en tabla o Exportación -->
                <div class="d-flex align-items-center gap-2">
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-danger" onclick="window.RV_exportarPDF()" title="Descargar PDF">
                            <i class="bi bi-file-earmark-pdf"></i> PDF
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="window.RV_exportarExcel()" title="Descargar Excel">
                            <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                        </button>
                    </div>
                </div>
                <!-- Información Paginación (Si aplica) -->
                <div class="d-flex align-items-center gap-3">
                    <span class="text-muted small fw-medium">Resultados Generados</span>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="rv-scroll w-100">
                <table class="table table-hover table-sm mb-0 align-middle" id="tabla-reporte-ventas">
                    <thead class="table-light" id="rv_thead">
                        <!-- Contenido dinámico desde JS -->
                    </thead>
                    <tbody id="rv_tbody">
                        <tr><td colspan="12" class="text-center py-5 text-muted"><i class="bi bi-filter-circle fs-3 d-block mb-2"></i>Aplica los filtros y haz clic en Generar para ver los resultados.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    const RUTA_MODULO = "<?php echo $rutaModulo; ?>";
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?php echo BASE_URL; ?>/js/modulos/reporte_ventas.js?v=<?php echo time(); ?>"></script>
