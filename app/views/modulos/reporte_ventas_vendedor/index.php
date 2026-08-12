<?php $idModulo = basename($rutaModulo); ?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<style>
    .reporte-ventas-vendedor-scroll { overflow-x: auto; }
    .reporte-ventas-vendedor-scroll thead th { background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; white-space: nowrap; }
    /* Altura idéntica y explícita para todos los controles de filtros */
    #form-filtros-rvv .form-select,
    #form-filtros-rvv .form-control,
    #form-filtros-rvv .input-group-text,
    #form-filtros-rvv .btn { height:28px; font-size:.75rem; }
    /* La tabla se extiende libremente hacia abajo; hace scroll la página, no un contenedor interno */
    @media (max-width: 767.98px) {
        #modulo-reporte_ventas_vendedor .reporte-ventas-vendedor-scroll { max-height:none !important; height:auto !important; overflow-y:visible !important; }
    }
</style>

<div class="container-fluid pt-0 pb-3 px-0 px-md-3" id="modulo-<?php echo $idModulo; ?>">

    <?php if (!empty($vendedorRestringido)): ?>
        <?php if ((int) $vendedorRestringido['id'] > 0): ?>
            <div class="alert alert-info py-2 px-3 small mb-3 d-flex align-items-center gap-2">
                <i class="bi bi-info-circle"></i>
                No tienes acceso total a este reporte: estás viendo únicamente las ventas asignadas a ti como vendedor
                (<strong><?= htmlspecialchars($vendedorRestringido['nombre']) ?></strong>).
            </div>
        <?php else: ?>
            <div class="alert alert-warning py-2 px-3 small mb-3 d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle"></i>
                No tienes acceso total a este reporte y tu usuario no está vinculado a ningún vendedor
                (catálogo Vendedores), así que no se muestran resultados. Pide a un administrador que te asigne
                acceso total o vincule tu usuario a un vendedor.
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- ── Tarjeta de control fija (título + filtros + KPIs) ── -->
    <div class="card cmg-control-card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-header bg-white border-bottom py-2 px-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-person-badge me-2 text-primary"></i>Reporte de Ventas por Vendedor</h5>
        </div>
        <div class="card-body p-3">
            <form id="form-filtros-rvv" onsubmit="event.preventDefault(); window.RVV_generarReporte();">

                <div class="d-flex flex-wrap align-items-start gap-2 mb-2">
                    <div>
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Tipo de Documento</label>
                        <select name="tipo_documento" id="rvv_tipo_documento" class="form-select form-select-sm shadow-none border" style="width:210px;" onchange="window.RVV_generarReporte()">
                            <option value="FACTURA_MENOS_NC" selected>Ventas Netas (Facturas − NC)</option>
                            <option value="FACTURA">Solo Facturas</option>
                            <option value="NOTA_CREDITO">Solo Notas de Crédito</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Agrupar Por</label>
                        <select name="agrupar_por" id="rvv_agrupar_por" class="form-select form-select-sm shadow-none border" style="width:170px;" onchange="window.RVV_generarReporte()">
                            <option value="VENDEDOR" selected>Por Vendedor</option>
                            <option value="PRODUCTO">Por Producto</option>
                            <option value="MARCA">Por Marca</option>
                            <option value="CATEGORIA">Por Categoría</option>
                            <option value="MES">Por Mes</option>
                            <option value="NINGUNO">Detallado (Ninguno)</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Año</label>
                        <select id="rvv-anio" class="form-select form-select-sm shadow-none border" style="width:90px;">
                            <option value="TODOS" selected>Todos</option>
                            <?php foreach (($anios ?? [date('Y')]) as $a): ?>
                                <option value="<?= htmlspecialchars($a) ?>"><?= htmlspecialchars($a) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Mes</label>
                        <select id="rvv-mes" class="form-select form-select-sm shadow-none border" style="width:110px;">
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
                        <input type="date" name="fecha_desde" id="rvv-fecha-desde" class="form-control form-control-sm shadow-none border" style="width:115px;">
                    </div>
                    <div>
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Fecha Hasta</label>
                        <input type="date" name="fecha_hasta" id="rvv-fecha-hasta" class="form-control form-control-sm shadow-none border" style="width:115px;">
                    </div>

                    <div style="flex:1 1 170px;">
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;"><i class="bi bi-person-badge me-1"></i>Vendedor</label>
                        <?php if (!empty($vendedorRestringido)): ?>
                            <input type="hidden" name="id_vendedor" value="<?= (int) $vendedorRestringido['id'] ?>">
                            <input type="text" class="form-control form-control-sm shadow-none border bg-light w-100" disabled
                                   value="<?= (int) $vendedorRestringido['id'] > 0 ? htmlspecialchars($vendedorRestringido['nombre']) : 'Sin vendedor vinculado' ?>">
                        <?php else: ?>
                            <select name="id_vendedor" id="rvv-id-vendedor" class="form-select form-select-sm shadow-none border w-100" onchange="window.RVV_generarReporte()">
                                <option value="">Todos</option>
                                <?php foreach (($vendedores ?? []) as $v): ?>
                                    <option value="<?= (int) $v['id'] ?>"><?= htmlspecialchars($v['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-start gap-2">
                    <div>
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;"><i class="bi bi-tags me-1"></i>Marca</label>
                        <select name="id_marca" id="rvv-id-marca" class="form-select form-select-sm shadow-none border" style="width:150px;" onchange="window.RVV_generarReporte()">
                            <option value="">Todas</option>
                            <?php foreach (($marcas ?? []) as $m): ?>
                                <option value="<?= (int) $m['id'] ?>"><?= htmlspecialchars($m['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;"><i class="bi bi-diagram-3 me-1"></i>Categoría</label>
                        <select name="id_categoria" id="rvv-id-categoria" class="form-select form-select-sm shadow-none border" style="width:150px;" onchange="window.RVV_generarReporte()">
                            <option value="">Todas</option>
                            <?php foreach (($categorias ?? []) as $c): ?>
                                <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="position-relative" style="flex:1 1 200px;">
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;"><i class="bi bi-box-seam me-1"></i>Producto</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" id="rvv-search-producto" class="form-control border-start-0 px-1 shadow-none" placeholder="Buscar producto..." autocomplete="off">
                            <input type="hidden" name="id_producto" id="rvv-id-producto">
                            <button type="button" class="btn btn-outline-secondary" title="Limpiar"
                                    onclick="document.getElementById('rvv-search-producto').value=''; document.getElementById('rvv-id-producto').value=''; window.RVV_generarReporte();"><i class="bi bi-x-lg"></i></button>
                        </div>
                        <div id="rvv-dropdown-productos" class="list-group shadow dropdown-predictivo position-absolute d-none" style="z-index:1050;width:100%;max-height:250px;overflow-y:auto;margin-top:2px;"></div>
                    </div>

                    <div>
                        <label class="form-label small fw-bold mb-1 d-block" style="font-size:.65rem;">&nbsp;</label>
                        <button type="submit" class="btn btn-primary btn-sm shadow-sm" style="width:110px;" id="btn-generar-reporte-rvv">
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
                        <div class="cmg-control-card__stat-value" id="rvv-stat-documentos">0</div>
                        <div class="cmg-control-card__stat-label">
                            Doc. Autorizados
                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary ms-1" style="font-size:.55rem;">Borr: <span id="rvv-stat-borradores">0</span></span>
                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger" style="font-size:.55rem;">Anul: <span id="rvv-stat-anulados">0</span></span>
                        </div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-percent bg-info bg-opacity-10 text-info"></i>
                    <div>
                        <div class="cmg-control-card__stat-value">$<span id="rvv-stat-base-0">0.00</span></div>
                        <div class="cmg-control-card__stat-label">Subtotal (0% / Exento)</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-graph-up bg-warning bg-opacity-10 text-warning"></i>
                    <div>
                        <div class="cmg-control-card__stat-value">$<span id="rvv-stat-base-iva">0.00</span></div>
                        <div class="cmg-control-card__stat-label">Base IVA + Imp. (IVA: $<span id="rvv-stat-iva">0.00</span>)</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-cash-stack bg-success bg-opacity-10 text-success"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-success">$<span id="rvv-stat-total">0.00</span></div>
                        <div class="cmg-control-card__stat-label">Ventas Netas (Gran Total)</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Tarjeta Principal (Tabla) ── -->
    <div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-2 px-3 border-bottom">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-danger" onclick="window.RVV_exportarPDF()" title="Descargar PDF">
                            <i class="bi bi-file-earmark-pdf"></i> PDF
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="window.RVV_exportarExcel()" title="Descargar Excel">
                            <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                        </button>
                        <button type="button" class="btn btn-outline-primary" onclick="window.RVV_abrirModalCorreo()" title="Enviar por correo">
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
            <div class="reporte-ventas-vendedor-scroll w-100">
                <table class="table table-hover table-sm mb-0 align-middle" id="tabla-rvv">
                    <thead class="table-light" id="rvv_thead"></thead>
                    <tbody id="rvv_tbody">
                        <tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-filter-circle fs-3 d-block mb-2"></i>Aplica los filtros y haz clic en Generar para ver los resultados.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     OFFCANVAS: Detalle de documentos por vendedor (drill-down)
═══════════════════════════════════════════════════════════ -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasDetalleVendedor" aria-labelledby="offcanvasDetalleVendedorLabel" style="width: 480px;">
    <div class="offcanvas-header bg-light border-bottom py-2 px-3">
        <div>
            <h6 class="offcanvas-title fw-bold text-primary mb-0" id="offcanvasDetalleVendedorLabel">
                <i class="bi bi-receipt-cutoff me-2"></i>Documentos del Vendedor
            </h6>
            <small class="text-muted" id="rvv-dv-subtitulo"></small>
        </div>
        <button type="button" class="btn-close btn-close-sm text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0 d-flex flex-column">
        <div id="rvv-dv-loading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <div class="small text-muted mt-2">Cargando documentos...</div>
        </div>
        <div id="rvv-dv-content" class="d-none flex-grow-1 d-flex flex-column" style="overflow: hidden;">
            <div class="table-responsive flex-grow-1" style="overflow-y: auto;">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                        <tr>
                            <th class="ps-3">Fecha</th>
                            <th>Factura</th>
                            <th>Cliente</th>
                            <th class="text-end">Subtotal</th>
                            <th class="text-end">NC</th>
                            <th class="text-end pe-3">Total</th>
                        </tr>
                    </thead>
                    <tbody id="rvv-dv-tbody"></tbody>
                </table>
            </div>
            <div class="border-top bg-light py-2 px-3 d-flex justify-content-between align-items-center">
                <span class="small fw-bold text-muted text-uppercase" style="font-size: 0.65rem;">Total Neto</span>
                <span class="fw-bold text-success fs-6">$<span id="rvv-dv-total">0.00</span></span>
            </div>
        </div>
    </div>
</div>

<style>
    #offcanvasDetalleVendedor { z-index: 6000 !important; }
</style>

<!-- ═══════════════════════════════════════════════════════════
     MODAL: Enviar reporte por correo
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalCorreoRVV" tabindex="-1" aria-labelledby="modalCorreoRVVLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:520px;">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header py-2 px-3" style="background:#0d6efd;">
                <h6 class="modal-title fw-bold text-white" id="modalCorreoRVVLabel"><i class="bi bi-envelope me-2"></i>Enviar Reporte por Correo</h6>
                <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <p class="text-muted small mb-2">Se enviará el PDF del reporte con los filtros actualmente aplicados.</p>
                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Correo Destinatario <span class="text-danger">*</span></label>
                        <input type="email" id="rvv-email-destino" class="form-control form-control-sm shadow-none" placeholder="correo@empresa.com">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Asunto</label>
                        <input type="text" id="rvv-email-asunto" class="form-control form-control-sm shadow-none" placeholder="Reporte de Ventas por Vendedor">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Mensaje adicional (opcional)</label>
                        <textarea id="rvv-email-mensaje" class="form-control form-control-sm shadow-none" rows="4" placeholder="Puede agregar un mensaje personalizado..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2 px-3">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm px-4" id="rvv-btn-enviar-correo" onclick="window.RVV_enviarCorreo()">
                    <i class="bi bi-send me-1"></i>Enviar Correo
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once MVC_APP . '/views/partials/offcanvas_doc_preview.php'; ?>

<script>
    const RUTA_MODULO = "<?php echo $rutaModulo; ?>";
</script>
<script src="<?php echo BASE_URL; ?>/js/modulos/reporte_ventas_vendedor.js?v=<?php echo time(); ?>"></script>
