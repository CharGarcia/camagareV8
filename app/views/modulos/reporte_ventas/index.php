<?php $idModulo = basename($rutaModulo); ?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<style>
    .rv-scroll { overflow-x: auto; }
    .rv-scroll thead th { background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; white-space: nowrap; }
    /* Evita que el contenedor de chips (vacío hasta que se elige un cliente) desalinee la fila de filtros */
    #rv-chips-cliente:empty { margin-top:0; }
    /* Altura idéntica y explícita para todos los controles de filtros */
    #form-filtros-reporte .form-select,
    #form-filtros-reporte .form-control,
    #form-filtros-reporte .input-group-text,
    #form-filtros-reporte .btn { height:28px; font-size:.75rem; }
    /* La tabla se extiende libremente hacia abajo; hace scroll la página, no un contenedor interno */
    @media (max-width: 767.98px) {
        #modulo-reporte_ventas .rv-scroll { max-height:none !important; height:auto !important; overflow-y:visible !important; }
    }
</style>

<div class="container-fluid pt-0 pb-3 px-0 px-md-3" id="modulo-<?php echo $idModulo; ?>">

    <!-- ── Tarjeta de control fija (título + filtros + KPIs) ── -->
    <div class="card cmg-control-card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-header bg-white border-bottom py-2 px-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-file-earmark-bar-graph me-2 text-primary"></i>Reporte de Ventas</h5>
        </div>
        <div class="card-body p-3">
            <form id="form-filtros-reporte" onsubmit="event.preventDefault(); window.RV_generarReporte();">

                <div class="d-flex flex-wrap align-items-start gap-2 mb-2">
                    <div>
                        <label class="form-label small fw-bold mb-1 text-muted text-uppercase d-flex align-items-center" style="font-size:.65rem;">
                            Tipo de Documento
                            <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito($rutaModulo, 'rv_tipo_documento', 'tipo_documento') ?>
                        </label>
                        <select name="tipo_documento" id="rv_tipo_documento" class="form-select form-select-sm shadow-none border" style="width:200px;" onchange="window.RV_generarReporte()">
                            <option value="FACTURA" selected>Facturas de Venta</option>
                            <option value="RECIBO">Recibos de Venta</option>
                            <option value="NOTA_CREDITO">Notas de Crédito en Ventas</option>
                            <option value="FACTURA_MENOS_NC">Facturas de Ventas − NC Ventas</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label small fw-bold mb-1 text-muted text-uppercase d-flex align-items-center" style="font-size:.65rem;">
                            Agrupar Por
                            <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito($rutaModulo, 'rv_agrupar_por', 'agrupar_por') ?>
                        </label>
                        <select name="agrupar_por" id="rv_agrupar_por" class="form-select form-select-sm shadow-none border" style="width:160px;" onchange="window.RV_onAgruparChange()">
                            <option value="NINGUNO" selected>Detallado (Ninguno)</option>
                            <option value="CLIENTE">Por Cliente</option>
                            <option value="PRODUCTO">Por Producto</option>
                            <option value="VARIANTE">Por Variante</option>
                            <option value="FECHA">Por Fecha</option>
                            <option value="MES">Por Mes</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Año</label>
                        <select id="rv-anio" class="form-select form-select-sm shadow-none border" style="width:90px;" onchange="window.RV_cambiarMesAnio()">
                            <option value="TODOS">Todos</option>
                            <?php foreach (($anios ?? [date('Y')]) as $a): ?>
                                <option value="<?= htmlspecialchars($a) ?>" <?= $a == date('Y') ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Mes</label>
                        <select id="rv-mes" class="form-select form-select-sm shadow-none border" style="width:110px;">
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

                    <div>
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Fecha Desde</label>
                        <input type="date" name="fecha_desde" id="rv-fecha-desde" class="form-control form-control-sm shadow-none border" style="width:115px;" value="<?php echo date('Y-m-01'); ?>">
                    </div>
                    <div>
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Fecha Hasta</label>
                        <input type="date" name="fecha_hasta" id="rv-fecha-hasta" class="form-control form-control-sm shadow-none border" style="width:115px;" value="<?php echo date('Y-m-t'); ?>">
                    </div>

                    <div class="position-relative" style="flex:1 1 200px;">
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Cliente</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control border-start-0 px-1 shadow-none" id="rv-search-cliente" placeholder="Buscar clientes..." autocomplete="off">
                        </div>
                        <div id="rv-chips-cliente" class="d-flex flex-column gap-1 mt-2"></div>
                        <div id="rv-dropdown-clientes" class="list-group shadow dropdown-predictivo position-absolute d-none" style="z-index:1050;width:100%;max-height:250px;overflow-y:auto;margin-top:2px;"></div>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-start gap-2">
                    <div class="position-relative" style="flex:1 1 180px;min-width:0;">
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Producto</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" name="producto_texto" id="rv-producto-texto" class="form-control border-start-0 px-1 shadow-none"
                                   placeholder="Ej: pelota, servicio..." autocomplete="off">
                            <button type="button" class="btn btn-outline-secondary" title="Limpiar"
                                    onclick="document.getElementById('rv-producto-texto').value=''; window.RV_generarReporte();"><i class="bi bi-x-lg"></i></button>
                        </div>
                        <div id="rv-dropdown-items" class="list-group shadow dropdown-predictivo position-absolute d-none" style="z-index:1050;width:100%;max-height:250px;overflow-y:auto;margin-top:2px;"></div>
                    </div>

                    <div class="position-relative" style="flex:1 1 180px;min-width:0;">
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;"><i class="bi bi-palette me-1"></i>Variante</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" name="variante_texto" id="rv-variante-texto" class="form-control border-start-0 px-1 shadow-none"
                                   placeholder="Ej: Rojo, Talla M..." autocomplete="off" onchange="window.RV_generarReporte()">
                            <button type="button" class="btn btn-outline-secondary" title="Limpiar"
                                    onclick="document.getElementById('rv-variante-texto').value=''; window.RV_generarReporte();"><i class="bi bi-x-lg"></i></button>
                        </div>
                    </div>

                    <div class="position-relative" style="flex:1 1 180px;min-width:0;">
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;"><i class="bi bi-card-text me-1"></i>Buscar en info adicional</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-card-text"></i></span>
                            <input type="text" name="buscar_info" id="rv-buscar-info" class="form-control border-start-0 px-1 shadow-none"
                                   placeholder="Ej: placa, referencia..." autocomplete="off">
                            <button type="button" class="btn btn-outline-secondary" title="Limpiar"
                                    onclick="document.getElementById('rv-buscar-info').value=''; window.RV_generarReporte();"><i class="bi bi-x-lg"></i></button>
                        </div>
                        <div id="rv-dropdown-info" class="list-group shadow dropdown-predictivo position-absolute d-none" style="z-index:1050;width:100%;max-height:250px;overflow-y:auto;margin-top:2px;"></div>
                    </div>

                    <div style="flex:0 0 90px;min-width:0;">
                        <label class="form-label small fw-bold mb-1 d-block" style="font-size:.65rem;">&nbsp;</label>
                        <button type="submit" class="btn btn-primary btn-sm shadow-sm w-100" id="btn-generar-reporte">
                            <i class="bi bi-search me-1"></i> Buscar
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-footer bg-white border-top py-2 px-3">
            <div class="cmg-control-card__stats">
                <div class="cmg-control-card__stat">
                    <i class="bi bi-receipt bg-primary bg-opacity-10 text-primary"></i>
                    <div>
                        <div class="cmg-control-card__stat-value" id="stat-documentos">0</div>
                        <div class="cmg-control-card__stat-label">
                            Doc. Autorizados
                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary ms-1" style="font-size:.55rem;">Borr: <span id="stat-borradores">0</span></span>
                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger" style="font-size:.55rem;">Anul: <span id="stat-anulados">0</span></span>
                        </div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-percent bg-info bg-opacity-10 text-info"></i>
                    <div>
                        <div class="cmg-control-card__stat-value">$<span id="stat-base-0">0.00</span></div>
                        <div class="cmg-control-card__stat-label">Subtotal (0% / Exento)</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-graph-up bg-warning bg-opacity-10 text-warning"></i>
                    <div>
                        <div class="cmg-control-card__stat-value">$<span id="stat-base-iva">0.00</span></div>
                        <div class="cmg-control-card__stat-label">Base IVA + Imp. (IVA: $<span id="stat-iva">0.00</span>)</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-cash-stack bg-success bg-opacity-10 text-success"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-success">$<span id="stat-total">0.00</span></div>
                        <div class="cmg-control-card__stat-label">Gran Total</div>
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
                    <div class="btn-group btn-group-sm ms-1" role="group" aria-label="Vista de tabla">
                        <button type="button" id="rv-btn-detalle" class="btn btn-primary" onclick="window.RV_setVistaAgrupacion('NINGUNO')" title="Ver todas las ventas en lista">
                            <i class="bi bi-list-ul"></i> Detallado
                        </button>
                        <button type="button" id="rv-btn-agrupado" class="btn btn-outline-primary" onclick="window.RV_setVistaAgrupacion('CLIENTE')" title="Agrupar ventas por cliente">
                            <i class="bi bi-people"></i> Por cliente
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

<?php // Panel lateral con el detalle del documento (clic sobre una fila del detallado)
require_once MVC_APP . '/views/partials/offcanvas_doc_preview.php'; ?>

<script>
    const RUTA_MODULO = "<?php echo $rutaModulo; ?>";
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?php echo BASE_URL; ?>/js/modulos/reporte_ventas.js?v=<?php echo time(); ?>"></script>
