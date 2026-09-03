<?php $idModulo = basename($rutaModulo); ?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<style>
    .rc-scroll { overflow-x: auto; }
    .rc-scroll thead th { background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; white-space: nowrap; }
    /* Evita que el contenedor de chips (vacío hasta que se elige un proveedor) desalinee la fila de filtros */
    #rc-chips-proveedor:empty { margin-top:0; }
    /* Altura idéntica y explícita para todos los controles de filtros */
    #form-filtros-reporte .form-select,
    #form-filtros-reporte .form-control,
    #form-filtros-reporte .input-group-text,
    #form-filtros-reporte .btn { height:28px; font-size:.75rem; }
    /* La tabla se extiende libremente hacia abajo; hace scroll la página, no un contenedor interno */
    @media (max-width: 767.98px) {
        #modulo-reporte_compras .rc-scroll { max-height:none !important; height:auto !important; overflow-y:visible !important; }
    }
</style>

<div class="container-fluid pt-0 pb-3 px-0 px-md-3" id="modulo-<?php echo $idModulo; ?>">

    <!-- ── Tarjeta de control fija (título + filtros + KPIs) ── -->
    <div class="card cmg-control-card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-header bg-white border-bottom py-2 px-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-file-earmark-bar-graph me-2 text-danger"></i>Reporte de Compras</h5>
        </div>
        <div class="card-body p-3">
            <form id="form-filtros-reporte" onsubmit="event.preventDefault(); window.RC_generarReporte();">

                <div class="d-flex flex-wrap align-items-start gap-2 mb-2">
                    <div>
                        <label class="form-label small fw-bold mb-1 text-muted text-uppercase d-flex align-items-center" style="font-size:.65rem;">
                            Tipo de Documento
                            <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito($rutaModulo, 'rc_tipo_comprobante', 'tipo_comprobante') ?>
                        </label>
                        <select name="tipo_comprobante" id="rc_tipo_comprobante" class="form-select form-select-sm shadow-none border" style="width:230px;">
                            <option value="">Todas las compras (NC restan)</option>
                            <?php foreach (($tiposComprobante ?? []) as $tc): ?>
                                <option value="<?= htmlspecialchars($tc['tipo_comprobante']) ?>">
                                    <?= htmlspecialchars($tc['tipo_comprobante']) ?> - <?= htmlspecialchars(trim((string)$tc['nombre'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="form-label small fw-bold mb-1 text-muted text-uppercase d-flex align-items-center" style="font-size:.65rem;">
                            Agrupar Por
                            <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito($rutaModulo, 'rc_agrupar_por', 'agrupar_por') ?>
                        </label>
                        <select name="agrupar_por" id="rc_agrupar_por" class="form-select form-select-sm shadow-none border" style="width:160px;"
                                onchange="window.RC_onAgruparChange()">
                            <option value="NINGUNO" selected>Detallado (Ninguno)</option>
                            <option value="PROVEEDOR">Por Proveedor</option>
                            <option value="PRODUCTO">Por Producto</option>
                            <option value="FECHA">Por Fecha</option>
                            <option value="MES">Por Mes</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Año</label>
                        <select id="rc-anio" class="form-select form-select-sm shadow-none border" style="width:90px;"
                                onchange="window.RC_cambiarMesAnio()">
                            <option value="TODOS">Todos</option>
                            <?php foreach (($anios ?? [date('Y')]) as $a): ?>
                                <option value="<?= htmlspecialchars($a) ?>" <?= $a == date('Y') ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($a) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Mes</label>
                        <select id="rc-mes" class="form-select form-select-sm shadow-none border" style="width:110px;">
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
                        <input type="date" name="fecha_desde" id="rc-fecha-desde"
                               class="form-control form-control-sm shadow-none border" style="width:115px;"
                               value="<?php echo date('Y-m-01'); ?>">
                    </div>
                    <div>
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Fecha Hasta</label>
                        <input type="date" name="fecha_hasta" id="rc-fecha-hasta"
                               class="form-control form-control-sm shadow-none border" style="width:115px;"
                               value="<?php echo date('Y-m-t'); ?>">
                    </div>

                    <!-- Buscador Proveedor -->
                    <div class="position-relative" style="flex:1 1 200px;">
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Proveedor</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control border-start-0 px-1 shadow-none"
                                   id="rc-search-proveedor" placeholder="Buscar proveedor..." autocomplete="off">
                        </div>
                        <div id="rc-chips-proveedor" class="d-flex flex-column gap-1 mt-2"></div>
                        <div id="rc-dropdown-proveedores" class="list-group shadow dropdown-predictivo position-absolute d-none"
                             style="z-index:1050;width:100%;max-height:250px;overflow-y:auto;margin-top:2px;"></div>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-start gap-2">

                    <!-- Producto: busca en los ítems de las compras -->
                    <div class="position-relative" style="flex:1 1 180px;min-width:0;">
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Producto</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" name="producto_texto" id="rc-producto-texto" class="form-control border-start-0 px-1 shadow-none"
                                   placeholder="Ej: cemento, cable..." autocomplete="off">
                            <button type="button" class="btn btn-outline-secondary" title="Limpiar"
                                    onclick="document.getElementById('rc-producto-texto').value=''; window.RC_generarReporte();"><i class="bi bi-x-lg"></i></button>
                        </div>
                        <div id="rc-dropdown-items" class="list-group shadow dropdown-predictivo position-absolute d-none"
                             style="z-index:1050;width:100%;max-height:250px;overflow-y:auto;margin-top:2px;"></div>
                    </div>

                    <div class="position-relative" style="flex:1 1 180px;min-width:0;">
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;"><i class="bi bi-card-text me-1"></i>Buscar en info adicional</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-card-text"></i></span>
                            <input type="text" name="buscar_info" id="rc-buscar-info" class="form-control border-start-0 px-1 shadow-none"
                                   placeholder="Ej: placa, referencia..." autocomplete="off">
                            <button type="button" class="btn btn-outline-secondary" title="Limpiar"
                                    onclick="document.getElementById('rc-buscar-info').value=''; window.RC_generarReporte();"><i class="bi bi-x-lg"></i></button>
                        </div>
                        <div id="rc-dropdown-info" class="list-group shadow dropdown-predictivo position-absolute d-none"
                             style="z-index:1050;width:100%;max-height:250px;overflow-y:auto;margin-top:2px;"></div>
                    </div>

                    <div>
                        <label class="form-label small fw-bold mb-1 d-block" style="font-size:.65rem;">&nbsp;</label>
                        <button type="submit" class="btn btn-primary btn-sm shadow-sm" style="width:170px;" id="btn-generar-reporte">
                            <i class="bi bi-search me-1"></i> Buscar
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-footer bg-white border-top py-2 px-3">
            <div class="cmg-control-card__stats">
                <div class="cmg-control-card__stat">
                    <i class="bi bi-receipt bg-danger bg-opacity-10 text-danger"></i>
                    <div>
                        <div class="cmg-control-card__stat-value" id="stat-documentos">0</div>
                        <div class="cmg-control-card__stat-label">Comprobantes</div>
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
                    <i class="bi bi-graph-up-arrow bg-warning bg-opacity-10 text-warning"></i>
                    <div>
                        <div class="cmg-control-card__stat-value">$<span id="stat-base-iva">0.00</span></div>
                        <div class="cmg-control-card__stat-label">Base IVA + Imp. (IVA: $<span id="stat-iva">0.00</span>)</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-cash-stack bg-danger bg-opacity-10 text-danger"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-danger">$<span id="stat-total">0.00</span></div>
                        <div class="cmg-control-card__stat-label">Gran Total</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Gráfico ── -->
    <div class="card border-0 shadow-sm mb-4" id="chart-container" style="display:none;">
        <div class="card-header bg-white border-bottom-0 pt-4 pb-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h6 class="mb-0 fw-bold text-dark" style="font-family:'Outfit',sans-serif;">
                <i class="bi bi-graph-up text-danger me-2"></i>Gráfico de Compras
            </h6>
            <div class="d-flex align-items-center gap-2">
                <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito($rutaModulo, 'rc-tipo-grafico', 'tipo_grafico') ?>
                <select id="rc-tipo-grafico" class="form-select form-select-sm shadow-none border" style="width:140px;"
                        onchange="window.RC_cambiarTipoGrafico()">
                    <option value="auto">Automático</option>
                    <option value="bar">Barras</option>
                    <option value="line">Líneas</option>
                    <option value="pie">Pastel</option>
                    <option value="doughnut">Dona</option>
                </select>
            </div>
        </div>
        <div class="card-body">
            <canvas id="reporteChart" style="max-height:300px;"></canvas>
        </div>
    </div>

    <!-- ── Tabla Principal ── -->
    <div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-2 px-3 border-bottom">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-danger" onclick="window.RC_exportarPDF()" title="Descargar PDF">
                            <i class="bi bi-file-earmark-pdf"></i> PDF
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="window.RC_exportarExcel()" title="Descargar Excel">
                            <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                        </button>
                    </div>
                    <div class="btn-group btn-group-sm ms-1" role="group" aria-label="Vista de tabla">
                        <button type="button" id="rc-btn-detalle" class="btn btn-primary" onclick="window.RC_setVistaAgrupacion('NINGUNO')" title="Ver todas las compras en lista">
                            <i class="bi bi-list-ul"></i> Detallado
                        </button>
                        <button type="button" id="rc-btn-agrupado" class="btn btn-outline-primary" onclick="window.RC_setVistaAgrupacion('PROVEEDOR')" title="Agrupar compras por proveedor">
                            <i class="bi bi-people"></i> Por proveedor
                        </button>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="text-muted small fw-medium">Resultados Generados</span>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="rc-scroll w-100">
                <table class="table table-hover table-sm mb-0 align-middle" id="tabla-reporte-compras">
                    <thead class="table-light" id="rc_thead">
                        <!-- Dinámico desde JS -->
                    </thead>
                    <tbody id="rc_tbody">
                        <tr><td colspan="12" class="text-center py-5 text-muted">
                            <i class="bi bi-filter-circle fs-3 d-block mb-2"></i>
                            Aplica los filtros y haz clic en Generar para ver los resultados.
                        </td></tr>
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
<script src="<?php echo BASE_URL; ?>/js/modulos/reporte_compras.js?v=<?php echo time(); ?>"></script>
