<?php $idModulo = basename($rutaModulo); ?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<style>
    .mc-scroll { overflow-x: auto; }
    .mc-scroll thead th { background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; white-space: nowrap; }
    /* Altura idéntica y explícita para todos los controles de filtros */
    #form-filtros-reporte .form-select,
    #form-filtros-reporte .form-control,
    #form-filtros-reporte .btn { height:28px; font-size:.75rem; }
    /* La tabla se extiende libremente hacia abajo; hace scroll la página, no un contenedor interno */
    @media (max-width: 767.98px) {
        #modulo-mejor_cliente .mc-scroll { max-height:none !important; height:auto !important; overflow-y:visible !important; }
    }
</style>

<div class="container-fluid pt-0 pb-3 px-0 px-md-3" id="modulo-<?php echo $idModulo; ?>">

    <!-- ── Tarjeta de control fija (título + filtros + KPIs) ── -->
    <div class="card cmg-control-card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-header bg-white border-bottom py-2 px-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-trophy me-2 text-primary"></i>Mejor Cliente</h5>
        </div>
        <div class="card-body p-3">
            <form id="form-filtros-reporte" onsubmit="event.preventDefault(); window.MC_generarReporte();">

                <div class="d-flex flex-wrap align-items-start gap-2 mb-2">
                    <?php if ($puedeFacturas || $puedeRecibos): ?>
                    <div>
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Fuente</label>
                        <div class="d-flex gap-3" style="height:28px;align-items:center;">
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" name="incluir_facturas" value="1" id="mc-incluir-facturas"
                                       <?= $puedeFacturas ? 'checked' : 'disabled' ?> onchange="window.MC_generarReporte()">
                                <label class="form-check-label small" for="mc-incluir-facturas">Facturas</label>
                            </div>
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" name="incluir_recibos" value="1" id="mc-incluir-recibos"
                                       <?= $puedeRecibos ? '' : 'disabled' ?> onchange="window.MC_generarReporte()">
                                <label class="form-check-label small" for="mc-incluir-recibos">Recibos</label>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                        <div class="alert alert-warning py-1 px-2 mb-0 small">No tiene acceso a Facturas ni Recibos de Venta.</div>
                    <?php endif; ?>

                    <div>
                        <label class="form-label small fw-bold mb-1 text-muted text-uppercase d-flex align-items-center" style="font-size:.65rem;">
                            Asesor / Vendedor
                            <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito($rutaModulo, 'mc_id_vendedor', 'id_vendedor') ?>
                        </label>
                        <select name="id_vendedor" id="mc_id_vendedor" class="form-select form-select-sm shadow-none border" style="width:170px;" onchange="window.MC_generarReporte()">
                            <option value="">Todos</option>
                            <?php foreach (($vendedores ?? []) as $v): ?>
                                <option value="<?= (int)$v['id'] ?>"><?= htmlspecialchars($v['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Año</label>
                        <select id="mc-anio" class="form-select form-select-sm shadow-none border" style="width:90px;" onchange="window.MC_cambiarMesAnio()">
                            <option value="TODOS">Todos</option>
                            <?php foreach (($anios ?? [date('Y')]) as $a): ?>
                                <option value="<?= htmlspecialchars($a) ?>" <?= $a == date('Y') ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Mes</label>
                        <select id="mc-mes" class="form-select form-select-sm shadow-none border" style="width:110px;">
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
                        <input type="date" name="fecha_desde" id="mc-fecha-desde" class="form-control form-control-sm shadow-none border" style="width:115px;" value="<?php echo date('Y-m-01'); ?>" onchange="window.MC_generarReporte()">
                    </div>
                    <div>
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Fecha Hasta</label>
                        <input type="date" name="fecha_hasta" id="mc-fecha-hasta" class="form-control form-control-sm shadow-none border" style="width:115px;" value="<?php echo date('Y-m-t'); ?>" onchange="window.MC_generarReporte()">
                    </div>

                    <div>
                        <label class="form-label small fw-bold mb-1 text-muted text-uppercase d-flex align-items-center" style="font-size:.65rem;">
                            Ordenar Por
                            <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito($rutaModulo, 'mc_orden_por', 'orden_por') ?>
                        </label>
                        <select name="orden_por" id="mc_orden_por" class="form-select form-select-sm shadow-none border" style="width:170px;" onchange="window.MC_generarReporte()">
                            <option value="monto" selected>Monto Neto</option>
                            <option value="cantidad">Cantidad de Documentos</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label small fw-bold mb-1 text-muted text-uppercase d-flex align-items-center" style="font-size:.65rem;">
                            Top
                            <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito($rutaModulo, 'mc_top_x', 'top_x') ?>
                        </label>
                        <input type="number" name="top_x" id="mc_top_x" class="form-control form-control-sm shadow-none border" style="width:90px;"
                               min="0" step="1" value="10" list="mc-top-x-sugeridos"
                               onchange="window.MC_generarReporte()" title="Cantidad de clientes a mostrar (0 = todos)">
                        <datalist id="mc-top-x-sugeridos">
                            <option value="10"></option>
                            <option value="20"></option>
                            <option value="50"></option>
                            <option value="100"></option>
                            <option value="0"></option>
                        </datalist>
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
                    <i class="bi bi-people bg-primary bg-opacity-10 text-primary"></i>
                    <div>
                        <div class="cmg-control-card__stat-value" id="stat-clientes">0</div>
                        <div class="cmg-control-card__stat-label">Clientes</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-receipt bg-info bg-opacity-10 text-info"></i>
                    <div>
                        <div class="cmg-control-card__stat-value" id="stat-documentos">0</div>
                        <div class="cmg-control-card__stat-label">Documentos</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-cash-stack bg-success bg-opacity-10 text-success"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-success" id="stat-monto">$0.00</div>
                        <div class="cmg-control-card__stat-label">Monto Neto Total</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-graph-up bg-warning bg-opacity-10 text-warning"></i>
                    <div>
                        <div class="cmg-control-card__stat-value" id="stat-venta-promedio">$0.00</div>
                        <div class="cmg-control-card__stat-label">Venta Promedio</div>
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
