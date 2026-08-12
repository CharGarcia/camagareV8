<?php $idModulo = basename($rutaModulo); ?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<style>
    .pmv-scroll { overflow-x: auto; }
    .pmv-scroll thead th { background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; white-space: nowrap; }
    /* Altura idéntica y explícita para todos los controles de filtros */
    #form-filtros-reporte .form-select,
    #form-filtros-reporte .form-control,
    #form-filtros-reporte .input-group-text,
    #form-filtros-reporte .btn { height:28px; font-size:.75rem; }
    /* Evita que el contenedor de chips (vacío hasta elegir) desalinee la fila de filtros */
    #pmv-chips-cliente:empty, #pmv-chips-producto:empty { margin-top:0; }
    /* La tabla se extiende libremente hacia abajo; hace scroll la página, no un contenedor interno */
    @media (max-width: 767.98px) {
        #modulo-producto_mas_vendido .pmv-scroll { max-height:none !important; height:auto !important; overflow-y:visible !important; }
    }
</style>

<div class="container-fluid pt-0 pb-3 px-0 px-md-3" id="modulo-<?php echo $idModulo; ?>">

    <!-- ── Tarjeta de control fija (título + filtros + KPIs) ── -->
    <div class="card cmg-control-card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-header bg-white border-bottom py-2 px-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-trophy me-2 text-primary"></i>Productos Más Vendidos</h5>
        </div>
        <div class="card-body p-3">
            <form id="form-filtros-reporte" onsubmit="event.preventDefault(); window.PMV_generarReporte();">
                <div class="d-flex flex-wrap align-items-start gap-2">
                    <div>
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Tipo de Documento</label>
                        <select name="tipo_documento" id="pmv_tipo_documento" class="form-select form-select-sm shadow-none border" style="width:170px;" onchange="window.PMV_generarReporte()">
                            <option value="FACTURA" selected>Facturas de Venta</option>
                            <option value="RECIBO">Recibos de Venta</option>
                            <option value="AMBOS">Facturas + Recibos</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Top</label>
                        <input type="hidden" name="top_n" id="pmv_top_n_hidden" value="20">
                        <input type="number" id="pmv_top_n" class="form-control form-control-sm shadow-none border" style="width:90px;" min="1" step="1"
                               value="20" placeholder="Cantidad" list="pmv-top-n-sugeridos"
                               onchange="window.PMV_syncTopN(); window.PMV_generarReporte();">
                        <datalist id="pmv-top-n-sugeridos">
                            <option value="10"><option value="20"><option value="50"><option value="100">
                        </datalist>
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" id="pmv_top_todos" onchange="window.PMV_toggleTopTodos()">
                            <label class="form-check-label text-muted" for="pmv_top_todos" style="font-size:.65rem;">Todos</label>
                        </div>
                    </div>

                    <div>
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Año</label>
                        <select id="pmv-anio" class="form-select form-select-sm shadow-none border" style="width:90px;" onchange="window.PMV_cambiarMesAnio()">
                            <option value="TODOS">Todos</option>
                            <?php foreach (($anios ?? [date('Y')]) as $a): ?>
                                <option value="<?= htmlspecialchars($a) ?>" <?= $a == date('Y') ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Mes</label>
                        <select id="pmv-mes" class="form-select form-select-sm shadow-none border" style="width:110px;">
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
                        <input type="date" name="fecha_desde" id="pmv-fecha-desde" class="form-control form-control-sm shadow-none border" style="width:115px;" value="<?php echo date('Y-m-01'); ?>">
                    </div>
                    <div>
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Fecha Hasta</label>
                        <input type="date" name="fecha_hasta" id="pmv-fecha-hasta" class="form-control form-control-sm shadow-none border" style="width:115px;" value="<?php echo date('Y-m-t'); ?>">
                    </div>

                    <div class="position-relative" style="flex:1 1 200px;">
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Cliente</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control border-start-0 px-1 shadow-none" id="pmv-search-cliente" placeholder="Buscar clientes..." autocomplete="off">
                        </div>
                        <div id="pmv-chips-cliente" class="d-flex flex-column gap-1 mt-2"></div>
                        <div id="pmv-dropdown-clientes" class="list-group shadow dropdown-predictivo position-absolute d-none" style="z-index:1050;width:100%;max-height:250px;overflow-y:auto;margin-top:2px;"></div>
                    </div>

                    <div class="position-relative" style="flex:1 1 200px;">
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Producto</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control border-start-0 px-1 shadow-none" id="pmv-search-producto" placeholder="Buscar productos..." autocomplete="off">
                        </div>
                        <div id="pmv-chips-producto" class="d-flex flex-column gap-1 mt-2"></div>
                        <div id="pmv-dropdown-productos" class="list-group shadow dropdown-predictivo position-absolute d-none" style="z-index:1050;width:100%;max-height:250px;overflow-y:auto;margin-top:2px;"></div>
                    </div>

                    <div>
                        <label class="form-label small fw-bold mb-1 d-block" style="font-size:.65rem;">&nbsp;</label>
                        <button type="submit" class="btn btn-primary btn-sm shadow-sm" style="width:90px;" id="btn-generar-reporte">
                            <i class="bi bi-search me-1"></i> Buscar
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-footer bg-white border-top py-2 px-3">
            <div class="cmg-control-card__stats">
                <div class="cmg-control-card__stat">
                    <i class="bi bi-box-seam bg-primary bg-opacity-10 text-primary"></i>
                    <div>
                        <div class="cmg-control-card__stat-value" id="stat-productos">0</div>
                        <div class="cmg-control-card__stat-label">Productos Distintos</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-boxes bg-info bg-opacity-10 text-info"></i>
                    <div>
                        <div class="cmg-control-card__stat-value" id="stat-unidades">0</div>
                        <div class="cmg-control-card__stat-label">Unidades Vendidas</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-cash-stack bg-success bg-opacity-10 text-success"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-success">$<span id="stat-total">0.00</span></div>
                        <div class="cmg-control-card__stat-label">Total Vendido</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-trophy bg-warning bg-opacity-10 text-warning"></i>
                    <div style="min-width:0;">
                        <div class="cmg-control-card__stat-value text-truncate" id="stat-top1-nombre" style="max-width:180px;">—</div>
                        <div class="cmg-control-card__stat-label">Producto #1 (<span id="stat-top1-cantidad">0 unidades</span>)</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Gráfico ── -->
    <div class="card border-0 shadow-sm mb-4" id="chart-container" style="display: none;">
        <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
            <h6 class="mb-0 fw-bold text-dark" style="font-family: 'Outfit', sans-serif;"><i class="bi bi-bar-chart-line text-primary me-2"></i>Cantidad Vendida por Producto</h6>
        </div>
        <div class="card-body">
            <canvas id="reporteChart" style="max-height: 300px;"></canvas>
        </div>
    </div>

    <!-- ── Tarjeta Principal (Tabla) ── -->
    <div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-2 px-3 border-bottom">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-danger" onclick="window.PMV_exportarPDF()" title="Descargar PDF">
                            <i class="bi bi-file-earmark-pdf"></i> PDF
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="window.PMV_exportarExcel()" title="Descargar Excel">
                            <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                        </button>
                        <button type="button" class="btn btn-outline-primary" onclick="window.PMV_abrirModalCorreo()" title="Enviar por correo">
                            <i class="bi bi-envelope"></i> Correo
                        </button>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="text-muted small fw-medium">Ranking Generado</span>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="pmv-scroll w-100">
                <table class="table table-hover table-sm mb-0 align-middle" id="tabla-reporte">
                    <thead class="table-light">
                        <tr class="text-secondary" style="font-family: 'Outfit', sans-serif;">
                            <th class="ps-4 text-center">#</th>
                            <th>Producto</th>
                            <th class="text-center">Cantidad Vendida</th>
                            <th class="text-center">N° Documentos</th>
                            <th class="text-end pe-4">Total Vendido</th>
                        </tr>
                    </thead>
                    <tbody id="pmv_tbody">
                        <tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-filter-circle fs-3 d-block mb-2"></i>Aplica los filtros y haz clic en Generar para ver los resultados.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal: Enviar por correo ── -->
<div class="modal fade" id="modalCorreoReporte" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="bi bi-envelope me-2"></i>Enviar reporte por correo</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Destinatarios</label>
                    <input type="text" id="pmv-correo-destinatarios" class="form-control form-control-sm" placeholder="correo1@ejemplo.com, correo2@ejemplo.com">
                    <small class="text-muted">Separe varios correos con coma.</small>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold">Adjuntar</label>
                    <select id="pmv-correo-adjuntar" class="form-select form-select-sm">
                        <option value="pdf" selected>Solo PDF</option>
                        <option value="excel">Solo Excel</option>
                        <option value="ambos">PDF y Excel</option>
                    </select>
                </div>
                <small class="text-muted">Se enviará el reporte con los filtros actualmente aplicados.</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-enviar-correo-reporte" onclick="window.PMV_enviarCorreo()">
                    <i class="bi bi-send me-1"></i> Enviar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const RUTA_MODULO = "<?php echo $rutaModulo; ?>";
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?php echo BASE_URL; ?>/js/modulos/producto_mas_vendido.js?v=<?php echo time(); ?>"></script>
