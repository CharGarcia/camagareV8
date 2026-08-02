<?php $idModulo = basename($rutaModulo); ?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<style>
    .rvv-header { flex-shrink: 0; }
    .reporte-ventas-vendedor-scroll { max-height: 500px; overflow-y: auto; }
    .reporte-ventas-vendedor-scroll thead th { position: sticky; top: 0; z-index: 10; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
</style>

<div class="container-fluid py-4 px-0 px-md-3" id="modulo-<?php echo $idModulo; ?>">
    <!-- ── Cabecera ── -->
    <div class="rvv-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h5 class="mb-0 fw-bold"><i class="bi bi-person-badge me-2 text-primary"></i>Reporte de Ventas por Vendedor</h5>
            <small class="text-muted">Ventas netas (facturas − notas de crédito) por asesor, producto, marca y categoría</small>
        </div>
    </div>

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

    <!-- ── Filtros Avanzados (Accordion) ── -->
    <div class="accordion mb-3 shadow-sm border-0" id="accordionFiltrosRVV">
        <div class="accordion-item border-0 rounded-3">
            <h2 class="accordion-header" id="headingFiltrosRVV">
                <button class="accordion-button bg-white text-dark py-2 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFiltrosRVV" aria-expanded="true" aria-controls="collapseFiltrosRVV">
                    <i class="bi bi-funnel me-2 text-primary"></i> <span class="fw-bold small">Filtros Avanzados</span>
                </button>
            </h2>
            <div id="collapseFiltrosRVV" class="accordion-collapse collapse show" aria-labelledby="headingFiltrosRVV" data-bs-parent="#accordionFiltrosRVV">
                <div class="accordion-body bg-light bg-opacity-10 p-3 pt-2">
                    <form id="form-filtros-rvv" onsubmit="event.preventDefault(); window.RVV_generarReporte();" class="row g-3">

                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size: 0.65rem;">Tipo de Documento</label>
                            <select name="tipo_documento" id="rvv_tipo_documento" class="form-select form-select-sm shadow-none border" onchange="window.RVV_generarReporte()">
                                <option value="FACTURA_MENOS_NC" selected>Ventas Netas (Facturas − NC)</option>
                                <option value="FACTURA">Solo Facturas</option>
                                <option value="NOTA_CREDITO">Solo Notas de Crédito</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size: 0.65rem;">Agrupar Por</label>
                            <select name="agrupar_por" id="rvv_agrupar_por" class="form-select form-select-sm shadow-none border" onchange="window.RVV_generarReporte()">
                                <option value="VENDEDOR" selected>Por Vendedor</option>
                                <option value="PRODUCTO">Por Producto</option>
                                <option value="MARCA">Por Marca</option>
                                <option value="CATEGORIA">Por Categoría</option>
                                <option value="MES">Por Mes</option>
                                <option value="NINGUNO">Detallado (Ninguno)</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size: 0.65rem;">Mes</label>
                            <select id="rvv-mes" class="form-select form-select-sm shadow-none border">
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
                            <select id="rvv-anio" class="form-select form-select-sm shadow-none border">
                                <option value="TODOS">Todos</option>
                                <?php foreach (($anios ?? [date('Y')]) as $a): ?>
                                    <option value="<?= htmlspecialchars($a) ?>" <?= $a == date('Y') ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size: 0.65rem;">Fecha Desde</label>
                            <input type="date" name="fecha_desde" id="rvv-fecha-desde" class="form-control form-control-sm shadow-none border" value="<?php echo date('Y-m-01'); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size: 0.65rem;">Fecha Hasta</label>
                            <input type="date" name="fecha_hasta" id="rvv-fecha-hasta" class="form-control form-control-sm shadow-none border" value="<?php echo date('Y-m-t'); ?>">
                        </div>

                        <div class="w-100 d-none d-md-block m-0"></div>

                        <div class="col-md-3">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size: 0.65rem;"><i class="bi bi-person-badge me-1"></i>Vendedor</label>
                            <?php if (!empty($vendedorRestringido)): ?>
                                <input type="hidden" name="id_vendedor" value="<?= (int) $vendedorRestringido['id'] ?>">
                                <input type="text" class="form-control form-control-sm shadow-none border bg-light" disabled
                                       value="<?= (int) $vendedorRestringido['id'] > 0 ? htmlspecialchars($vendedorRestringido['nombre']) : 'Sin vendedor vinculado' ?>">
                            <?php else: ?>
                                <select name="id_vendedor" id="rvv-id-vendedor" class="form-select form-select-sm shadow-none border" onchange="window.RVV_generarReporte()">
                                    <option value="">Todos</option>
                                    <?php foreach (($vendedores ?? []) as $v): ?>
                                        <option value="<?= (int) $v['id'] ?>"><?= htmlspecialchars($v['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size: 0.65rem;"><i class="bi bi-tags me-1"></i>Marca</label>
                            <select name="id_marca" id="rvv-id-marca" class="form-select form-select-sm shadow-none border" onchange="window.RVV_generarReporte()">
                                <option value="">Todas</option>
                                <?php foreach (($marcas ?? []) as $m): ?>
                                    <option value="<?= (int) $m['id'] ?>"><?= htmlspecialchars($m['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size: 0.65rem;"><i class="bi bi-diagram-3 me-1"></i>Categoría</label>
                            <select name="id_categoria" id="rvv-id-categoria" class="form-select form-select-sm shadow-none border" onchange="window.RVV_generarReporte()">
                                <option value="">Todas</option>
                                <?php foreach (($categorias ?? []) as $c): ?>
                                    <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3 position-relative">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size: 0.65rem;"><i class="bi bi-box-seam me-1"></i>Producto</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                                <input type="text" id="rvv-search-producto" class="form-control border-start-0 px-1 shadow-none" placeholder="Buscar producto..." autocomplete="off">
                                <input type="hidden" name="id_producto" id="rvv-id-producto">
                                <button type="button" class="btn btn-outline-secondary" title="Limpiar"
                                        onclick="document.getElementById('rvv-search-producto').value=''; document.getElementById('rvv-id-producto').value=''; window.RVV_generarReporte();"><i class="bi bi-x-lg"></i></button>
                            </div>
                            <div id="rvv-dropdown-productos" class="list-group shadow dropdown-predictivo position-absolute d-none" style="z-index: 1050; width: calc(100% - 1.5rem); max-height: 250px; overflow-y: auto; margin-top: 2px;"></div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-bold mb-1 d-block" style="font-size: 0.65rem;">&nbsp;</label>
                            <button type="submit" class="btn btn-primary btn-sm shadow-sm w-100" id="btn-generar-reporte-rvv">
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
                        <h4 class="mb-0 fw-bold" style="font-family: 'Outfit', sans-serif;" id="rvv-stat-documentos">0</h4>
                        <div class="d-flex gap-2 mt-1">
                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary" style="font-size: 0.6rem;">Borradores: <span id="rvv-stat-borradores">0</span></span>
                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger" style="font-size: 0.6rem;">Anulados: <span id="rvv-stat-anulados">0</span></span>
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
                        <h4 class="mb-0 fw-bold" style="font-family: 'Outfit', sans-serif;">$<span id="rvv-stat-base-0">0.00</span></h4>
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
                        <h4 class="mb-0 fw-bold" style="font-family: 'Outfit', sans-serif;">$<span id="rvv-stat-base-iva">0.00</span></h4>
                        <small class="text-muted" style="font-size: 0.7rem;">IVA: $<span id="rvv-stat-iva">0.00</span></small>
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
                        <h6 class="mb-0 text-muted small fw-bold text-uppercase" style="font-size: 0.65rem;">Ventas Netas (Gran Total)</h6>
                        <h4 class="mb-0 fw-bold text-success" style="font-family: 'Outfit', sans-serif;">$<span id="rvv-stat-total">0.00</span></h4>
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
