<?php $idModulo = basename($rutaModulo); ?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<style>
    .mc-header { flex-shrink: 0; }
    .mc-scroll { max-height: 500px; overflow-y: auto; }
    .mc-scroll thead th { position: sticky; top: 0; z-index: 10; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
</style>

<div class="container-fluid py-4 px-0 px-md-3" id="modulo-<?php echo $idModulo; ?>">
    <!-- ── Cabecera ── -->
    <div class="mc-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h5 class="mb-0 fw-bold"><i class="bi bi-trophy me-2 text-primary"></i>Mejor Cliente</h5>
            <small class="text-muted">Ranking de clientes por monto neto o cantidad de documentos</small>
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
                    <form id="form-filtros-reporte" onsubmit="event.preventDefault(); window.MC_generarReporte();" class="row g-3">

                        <?php if ($puedeFacturas || $puedeRecibos): ?>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase d-block" style="font-size: 0.65rem;">Fuente</label>
                            <div class="d-flex gap-3 pt-1">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="incluir_facturas" value="1" id="mc-incluir-facturas"
                                           <?= $puedeFacturas ? 'checked' : 'disabled' ?> onchange="window.MC_generarReporte()">
                                    <label class="form-check-label small" for="mc-incluir-facturas">Facturas</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="incluir_recibos" value="1" id="mc-incluir-recibos"
                                           <?= $puedeRecibos ? '' : 'disabled' ?> onchange="window.MC_generarReporte()">
                                    <label class="form-check-label small" for="mc-incluir-recibos">Recibos</label>
                                </div>
                            </div>
                            <small class="text-muted" style="font-size:.62rem;">Las Notas de Crédito de venta siempre se restan.</small>
                        </div>
                        <?php else: ?>
                            <div class="col-md-3">
                                <div class="alert alert-warning py-1 px-2 mb-0 small">No tiene acceso a Facturas ni Recibos de Venta.</div>
                            </div>
                        <?php endif; ?>

                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase d-flex align-items-center" style="font-size: 0.65rem;">
                                Asesor / Vendedor
                                <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito($rutaModulo, 'mc_id_vendedor', 'id_vendedor') ?>
                            </label>
                            <select name="id_vendedor" id="mc_id_vendedor" class="form-select form-select-sm shadow-none border" onchange="window.MC_generarReporte()">
                                <option value="">Todos</option>
                                <?php foreach (($vendedores ?? []) as $v): ?>
                                    <option value="<?= (int)$v['id'] ?>"><?= htmlspecialchars($v['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase d-flex align-items-center" style="font-size: 0.65rem;">
                                Ordenar Por
                                <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito($rutaModulo, 'mc_orden_por', 'orden_por') ?>
                            </label>
                            <select name="orden_por" id="mc_orden_por" class="form-select form-select-sm shadow-none border" onchange="window.MC_generarReporte()">
                                <option value="monto" selected>Monto Neto</option>
                                <option value="cantidad">Cantidad de Documentos</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase d-flex align-items-center" style="font-size: 0.65rem;">
                                Top
                                <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito($rutaModulo, 'mc_top_x', 'top_x') ?>
                            </label>
                            <input type="number" name="top_x" id="mc_top_x" class="form-control form-control-sm shadow-none border"
                                   min="0" step="1" value="10" list="mc-top-x-sugeridos"
                                   onchange="window.MC_generarReporte()" title="Cantidad de clientes a mostrar (0 = todos)">
                            <datalist id="mc-top-x-sugeridos">
                                <option value="10"></option>
                                <option value="20"></option>
                                <option value="50"></option>
                                <option value="100"></option>
                                <option value="0"></option>
                            </datalist>
                            <small class="text-muted" style="font-size:.62rem;">0 = todos los clientes.</small>
                        </div>

                        <div class="w-100 d-none d-md-block m-0"></div>

                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size: 0.65rem;">Mes</label>
                            <select id="mc-mes" class="form-select form-select-sm shadow-none border">
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
                            <select id="mc-anio" class="form-select form-select-sm shadow-none border" onchange="window.MC_cambiarMesAnio()">
                                <option value="TODOS">Todos</option>
                                <?php foreach (($anios ?? [date('Y')]) as $a): ?>
                                    <option value="<?= htmlspecialchars($a) ?>" <?= $a == date('Y') ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size: 0.65rem;">Fecha Desde</label>
                            <input type="date" name="fecha_desde" id="mc-fecha-desde" class="form-control form-control-sm shadow-none border" value="<?php echo date('Y-m-01'); ?>" onchange="window.MC_generarReporte()">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size: 0.65rem;">Fecha Hasta</label>
                            <input type="date" name="fecha_hasta" id="mc-fecha-hasta" class="form-control form-control-sm shadow-none border" value="<?php echo date('Y-m-t'); ?>" onchange="window.MC_generarReporte()">
                        </div>

                        <div class="col-md-2">
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
                        <i class="bi bi-people fs-4"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 text-muted small fw-bold text-uppercase" style="font-size: 0.65rem;">Clientes</h6>
                        <h4 class="mb-0 fw-bold" id="stat-clientes">0</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 rounded-4 shadow-sm h-100 bg-white">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="bg-info bg-opacity-10 text-info rounded-3 p-3 me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-receipt fs-4"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 text-muted small fw-bold text-uppercase" style="font-size: 0.65rem;">Documentos</h6>
                        <h4 class="mb-0 fw-bold" id="stat-documentos">0</h4>
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
                        <h6 class="mb-0 text-muted small fw-bold text-uppercase" style="font-size: 0.65rem;">Monto Neto Total</h6>
                        <h4 class="mb-0 fw-bold text-success" id="stat-monto">$0.00</h4>
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
                        <h6 class="mb-0 text-muted small fw-bold text-uppercase" style="font-size: 0.65rem;">Venta Promedio</h6>
                        <h4 class="mb-0 fw-bold" id="stat-venta-promedio">$0.00</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Gráfico ── -->
    <div class="card border-0 shadow-sm mb-4" id="chart-container" style="display: none;">
        <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
            <h6 class="mb-0 fw-bold text-dark" style="font-family: 'Outfit', sans-serif;"><i class="bi bi-bar-chart-line text-primary me-2"></i><span id="chart-titulo">Top Clientes por Monto Neto</span></h6>
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
                        <button type="button" class="btn btn-outline-danger" onclick="window.MC_exportarPDF()" title="Descargar PDF">
                            <i class="bi bi-file-earmark-pdf"></i> PDF
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="window.MC_exportarExcel()" title="Descargar Excel">
                            <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                        </button>
                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEnviarCorreo" title="Enviar por correo">
                            <i class="bi bi-envelope"></i> Correo
                        </button>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="text-muted small fw-medium">Resultados Generados</span>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="mc-scroll w-100">
                <table class="table table-hover table-sm mb-0 align-middle" id="tabla-mejor-cliente">
                    <thead class="table-light">
                        <tr class="text-secondary">
                            <th class="text-center ps-4">#</th>
                            <th>Cliente</th>
                            <th class="text-center">Nro Documentos</th>
                            <th class="text-end">Monto Neto</th>
                            <th class="text-end pe-4">Venta Promedio</th>
                        </tr>
                    </thead>
                    <tbody id="mc_tbody">
                        <tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-filter-circle fs-3 d-block mb-2"></i>Aplica los filtros y haz clic en Generar para ver los resultados.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal: Enviar por correo ── -->
<div class="modal fade" id="modalEnviarCorreo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="bi bi-envelope me-2"></i>Enviar Reporte por Correo</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Destinatario(s)</label>
                    <input type="email" class="form-control form-control-sm" id="mc-email-destino" placeholder="correo@ejemplo.com" multiple>
                    <small class="text-muted" style="font-size:.7rem;">Puede separar varios correos con comas.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Asunto</label>
                    <input type="text" class="form-control form-control-sm" id="mc-email-asunto" placeholder="Reporte de Mejor Cliente">
                </div>
                <div class="mb-0">
                    <label class="form-label small fw-bold">Mensaje</label>
                    <textarea class="form-control form-control-sm" id="mc-email-mensaje" rows="3" placeholder="Adjunto el reporte solicitado."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-enviar-correo-reporte" onclick="window.MC_enviarCorreo()">
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
<script src="<?php echo BASE_URL; ?>/js/modulos/mejor_cliente.js?v=<?php echo time(); ?>"></script>
