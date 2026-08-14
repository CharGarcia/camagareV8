<?php $idModulo = basename($rutaModulo); ?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<style>
    .reporte-consolidado-scroll { overflow-x: auto; }
    .reporte-consolidado-scroll thead th { background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; white-space: nowrap; }
    #form-filtros-consolidado .form-select,
    #form-filtros-consolidado .form-control,
    #form-filtros-consolidado .input-group-text,
    #form-filtros-consolidado .btn { height: 28px; font-size: .75rem; }
    .rcon-chk-grupo { font-size: .72rem; }
    @media (max-width: 767.98px) {
        #modulo-reporte_consolidado .reporte-consolidado-scroll { max-height: none !important; height: auto !important; overflow-y: visible !important; }
    }
</style>

<div class="container-fluid pt-0 pb-3 px-0 px-md-3" id="modulo-<?php echo $idModulo; ?>">

    <!-- ── Tarjeta de control fija (título + filtros + KPIs) ── -->
    <div class="card cmg-control-card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-header bg-white border-bottom py-2 px-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-collection me-2 text-primary"></i>Reporte Consolidado de Transacciones</h5>
        </div>
        <div class="card-body p-3">
            <form id="form-filtros-consolidado" onsubmit="event.preventDefault(); window.RCON_generarReporte();">
                <div class="d-flex flex-wrap align-items-start gap-2 mb-2">
                    <div>
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Año</label>
                        <select id="rcon-anio" class="form-select form-select-sm shadow-none border" style="width:90px;"
                                onchange="window.RCON_cambiarMesAnio();">
                            <option value="TODOS">Todos</option>
                            <?php foreach (($anios ?? [date('Y')]) as $a): ?>
                                <option value="<?= htmlspecialchars((string) $a) ?>" <?= (string) $a === date('Y') ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) $a) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Mes</label>
                        <select id="rcon-mes" class="form-select form-select-sm shadow-none border" style="width:110px;"
                                onchange="window.RCON_cambiarMesAnio();">
                            <option value="TODOS">Todos</option>
                            <option value="01" <?= date('m') === '01' ? 'selected' : '' ?>>Enero</option>
                            <option value="02" <?= date('m') === '02' ? 'selected' : '' ?>>Febrero</option>
                            <option value="03" <?= date('m') === '03' ? 'selected' : '' ?>>Marzo</option>
                            <option value="04" <?= date('m') === '04' ? 'selected' : '' ?>>Abril</option>
                            <option value="05" <?= date('m') === '05' ? 'selected' : '' ?>>Mayo</option>
                            <option value="06" <?= date('m') === '06' ? 'selected' : '' ?>>Junio</option>
                            <option value="07" <?= date('m') === '07' ? 'selected' : '' ?>>Julio</option>
                            <option value="08" <?= date('m') === '08' ? 'selected' : '' ?>>Agosto</option>
                            <option value="09" <?= date('m') === '09' ? 'selected' : '' ?>>Septiembre</option>
                            <option value="10" <?= date('m') === '10' ? 'selected' : '' ?>>Octubre</option>
                            <option value="11" <?= date('m') === '11' ? 'selected' : '' ?>>Noviembre</option>
                            <option value="12" <?= date('m') === '12' ? 'selected' : '' ?>>Diciembre</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Fecha Desde</label>
                        <input type="date" name="fecha_desde" id="rcon-fecha-desde"
                               class="form-control form-control-sm shadow-none border" style="width:130px;"
                               value="<?php echo date('Y-m-01'); ?>">
                    </div>
                    <div>
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Fecha Hasta</label>
                        <input type="date" name="fecha_hasta" id="rcon-fecha-hasta"
                               class="form-control form-control-sm shadow-none border" style="width:130px;"
                               value="<?php echo date('Y-m-t'); ?>">
                    </div>

                    <div class="position-relative" style="flex:1 1 220px;min-width:0;">
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Buscar (tercero / número)</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" name="buscar" id="rcon-buscar" class="form-control border-start-0 px-1 shadow-none"
                                   placeholder="Nombre, identificación o número..." autocomplete="off">
                        </div>
                    </div>

                    <div>
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Anulados</label>
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" name="incluir_anulados" value="1" id="rcon-incluir-anulados" onchange="window.RCON_generarReporte();">
                            <label class="form-check-label small" for="rcon-incluir-anulados">Incluir anulados</label>
                        </div>
                    </div>

                    <div>
                        <label class="form-label small fw-bold mb-1 d-block" style="font-size:.65rem;">&nbsp;</label>
                        <button type="submit" class="btn btn-primary btn-sm shadow-sm" style="width:130px;">
                            <i class="bi bi-search me-1"></i> Buscar
                        </button>
                    </div>
                </div>

                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Documentos a incluir</label>
                    <div class="d-flex flex-wrap gap-3">
                        <?php foreach (($grupos ?? []) as $clave => $etiqueta): ?>
                            <div class="form-check rcon-chk-grupo">
                                <input class="form-check-input rcon-chk" type="checkbox" name="incluir[]" value="<?= htmlspecialchars($clave) ?>"
                                       id="rcon-chk-<?= htmlspecialchars($clave) ?>" checked onchange="window.RCON_generarReporte();">
                                <label class="form-check-label" for="rcon-chk-<?= htmlspecialchars($clave) ?>"><?= htmlspecialchars($etiqueta) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-footer bg-white border-top py-2 px-3">
            <div class="cmg-control-card__stats">
                <div class="cmg-control-card__stat">
                    <i class="bi bi-receipt bg-primary bg-opacity-10 text-primary"></i>
                    <div>
                        <div class="cmg-control-card__stat-value" id="rcon-stat-documentos">0</div>
                        <div class="cmg-control-card__stat-label">Documentos</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-graph-up-arrow bg-success bg-opacity-10 text-success"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-success">$<span id="rcon-stat-ventas">0.00</span></div>
                        <div class="cmg-control-card__stat-label">Total Ventas</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-cart-dash bg-danger bg-opacity-10 text-danger"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-danger">$<span id="rcon-stat-compras">0.00</span></div>
                        <div class="cmg-control-card__stat-label">Total Compras</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-cash-stack bg-info bg-opacity-10 text-info"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-info">$<span id="rcon-stat-neto">0.00</span></div>
                        <div class="cmg-control-card__stat-label">Neto (Ventas − Compras)</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Tabla Principal ── -->
    <div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-2 px-3 border-bottom">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-danger" onclick="window.RCON_exportarPDF()" title="Descargar PDF">
                        <i class="bi bi-file-earmark-pdf"></i> PDF
                    </button>
                    <button type="button" class="btn btn-outline-success" onclick="window.RCON_exportarExcel()" title="Descargar Excel (una hoja por documento)">
                        <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                    </button>
                </div>
                <small class="text-muted fw-medium">Vista resumida a nivel de cabecera. El Excel incluye el detalle línea por línea de cada documento.</small>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="reporte-consolidado-scroll w-100">
                <table class="table table-hover table-sm mb-0 align-middle" id="tabla-reporte-consolidado">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-2">Tipo</th>
                            <th class="text-center">Fecha</th>
                            <th>Número</th>
                            <th>Tercero</th>
                            <th class="text-end">Subtotal</th>
                            <th class="text-end">IVA</th>
                            <th class="text-end pe-3">Total</th>
                            <th class="text-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody id="rcon-tbody">
                        <tr><td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-filter-circle fs-3 d-block mb-2"></i>
                            Aplica los filtros y haz clic en Buscar para ver los resultados.
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    const RUTA_MODULO = "<?php echo $rutaModulo; ?>";
</script>
<script src="<?php echo BASE_URL; ?>/js/modulos/reporte_consolidado.js?v=<?php echo time(); ?>"></script>
