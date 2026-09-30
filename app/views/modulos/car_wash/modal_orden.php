<?php
/** @var array $perm */
/** @var array $puntos */
?>
<div class="modal fade" id="modalOrdenCW" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="cwTitulo"><i class="bi bi-droplet-half me-1 text-info"></i> Nueva orden de Car-Wash</h5>
                <span id="cw_estado_badge" class="badge bg-secondary bg-opacity-10 text-secondary ms-2 d-none">Nuevo</span>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <!-- Barra de acciones (estilo factura) -->
            <div class="px-3 pt-2 d-flex flex-wrap gap-1 border-bottom pb-2">
                <button type="button" class="btn btn-outline-primary btn-sm px-2" onclick="cwCrearVehiculo()" title="Registrar nuevo vehículo">
                    <i class="bi bi-car-front"></i>
                </button>
                <button type="button" class="btn btn-outline-primary btn-sm px-2" onclick="cwCrearCliente()" title="Registrar nuevo cliente">
                    <i class="bi bi-person-plus"></i>
                </button>
                <button type="button" class="btn btn-outline-primary btn-sm px-2" onclick="cwCrearProducto()" title="Registrar nuevo servicio/producto">
                    <i class="bi bi-box-seam"></i>
                </button>
                <div class="vr mx-1"></div>
                <button type="button" id="cw_btn_factura" class="btn btn-outline-success btn-sm px-2" onclick="cwGenerarDocumento('FACTURA')" title="Generar Factura electrónica" disabled>
                    <i class="bi bi-receipt"></i> <span class="d-none d-md-inline">Factura</span>
                </button>
                <button type="button" id="cw_btn_recibo" class="btn btn-outline-success btn-sm px-2" onclick="cwGenerarDocumento('RECIBO')" title="Generar Recibo de venta" disabled>
                    <i class="bi bi-receipt-cutoff"></i> <span class="d-none d-md-inline">Recibo</span>
                </button>
                <div class="vr mx-1"></div>
                <button type="button" id="cw_btn_pdf" class="btn btn-outline-danger btn-sm px-2" onclick="cwPdf()" title="PDF de la orden" disabled><i class="bi bi-file-earmark-pdf"></i></button>
                <button type="button" id="cw_btn_acta" class="btn btn-outline-danger btn-sm px-2" onclick="cwPdfIngreso()" title="Acta de ingreso del vehículo (PDF)" disabled><i class="bi bi-clipboard2-check"></i></button>
                <button type="button" id="cw_btn_correo" class="btn btn-outline-info btn-sm px-2" onclick="cwCorreo()" title="Enviar por correo" disabled><i class="bi bi-envelope"></i></button>
                <button type="button" id="cw_btn_whatsapp" class="btn btn-outline-success btn-sm px-2" onclick="cwWhatsapp()" title="Enviar por WhatsApp" disabled><i class="bi bi-whatsapp"></i></button>
            </div>

            <div class="modal-body position-relative">
                <div id="cw-modal-loader" class="d-none position-absolute top-0 start-0 w-100 h-100 d-flex flex-column align-items-center justify-content-center bg-white bg-opacity-75" style="z-index: 1055;">
                    <div class="spinner-border text-primary mb-2" role="status"></div>
                    <div class="small text-muted">Cargando información de la orden...</div>
                </div>

                <!-- Pestañas: General (la orden) / Historial (por vehículo o cliente) / Facturación -->
                <?php
                $pestanasCW = [
                    'cw-pane-general'     => 'General',
                    'cw-pane-historial'   => 'Historial',
                    'cw-pane-facturacion' => 'Facturación',
                ];
                ?>
                <ul class="nav nav-tabs nav-tabs-sm mb-2 align-items-center" role="tablist" id="cwTabs">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link active py-1 small" id="cw-tab-general" data-bs-toggle="tab" href="#cw-pane-general" data-bs-target="#cw-pane-general" role="tab">
                            <i class="bi bi-card-list me-1"></i>General
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link py-1 small" id="cw-tab-historial" data-bs-toggle="tab" href="#cw-pane-historial" data-bs-target="#cw-pane-historial" role="tab">
                            <i class="bi bi-clock-history me-1"></i>Historial
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link py-1 small" id="cw-tab-facturacion" data-bs-toggle="tab" href="#cw-pane-facturacion" data-bs-target="#cw-pane-facturacion" role="tab">
                            <i class="bi bi-receipt me-1"></i>Facturación <span class="badge bg-secondary bg-opacity-10 text-secondary ms-1 d-none" id="cw-badge-docs">0</span>
                        </a>
                    </li>
                    <?= \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasCW, $vistaConfig ?? [], 'modulos/car-wash') ?>
                </ul>

                <div class="tab-content">
                <div class="tab-pane fade show active" id="cw-pane-general" role="tabpanel">
                <form id="formOrdenCW" autocomplete="off">
                    <input type="hidden" id="cw_id">
                    <input type="hidden" id="cw_id_vehiculo">
                    <input type="hidden" id="cw_id_cliente">
                    <input type="hidden" id="cw_serie">
                    <input type="hidden" id="cw_id_punto_emision">
                    <input type="hidden" id="cw_id_establecimiento">

                    <!-- Cabecera (diseño factura) -->
                    <div class="p-2 bg-white border rounded-3 mb-2">
                        <!-- Fila 1: fecha, serie, secuencial, cliente -->
                        <div class="row g-2 align-items-end">
                            <div class="col-6 col-md-2">
                                <label class="x-small fw-bold text-muted mb-1">Fecha ingreso</label>
                                <input type="datetime-local" id="cw_fecha_ingreso" class="form-control form-control-sm border-primary border-opacity-10 py-0" style="height:31px;">
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="x-small fw-bold text-muted mb-1">Serie <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('modulos/car-wash', 'cw_select_serie', 'id_punto_emision') ?></label>
                                <select id="cw_select_serie" name="id_punto_emision" class="form-select form-select-sm border-primary border-opacity-25" onchange="cwSerieChange()" style="height:31px;">
                                    <?php if (empty($puntos)): ?>
                                        <option value="">— Sin puntos —</option>
                                    <?php else: foreach ($puntos as $p): ?>
                                        <option value="<?= (int)$p['id'] ?>"
                                            data-id-est="<?= (int)($p['id_establecimiento'] ?? 0) ?>"
                                            data-cod-est="<?= htmlspecialchars($p['cod_establecimiento'] ?? '') ?>"
                                            data-cod-punto="<?= htmlspecialchars($p['codigo_punto'] ?? '') ?>">
                                            <?= htmlspecialchars(($p['cod_establecimiento'] ?? '') . '-' . ($p['codigo_punto'] ?? '')) ?>
                                        </option>
                                    <?php endforeach; endif; ?>
                                </select>
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="x-small fw-bold text-muted mb-1">Secuencial</label>
                                <input type="text" id="cw_secuencial" class="form-control form-control-sm border-primary border-opacity-25 text-center text-dark py-0 bg-light" style="height:31px;" readonly placeholder="000000001" maxlength="9">
                            </div>
                            <div class="col-12 col-md-6 position-relative">
                                <label class="x-small fw-bold text-muted mb-1">Cliente <span class="text-danger">*</span></label>
                                <input type="text" id="cw_cliente_busqueda" class="form-control form-control-sm border-primary border-opacity-10" placeholder="Seleccionar cliente..." oninput="cwBuscarClientes(this.value)">
                                <div id="cw_cli_dropdown" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index:1085; max-height:240px; overflow:auto;"></div>
                            </div>
                        </div>
                        <!-- Fila 2: vehículo, kilometraje, combustible, próxima cita, bodega -->
                        <div class="row g-2 align-items-end mt-1">
                            <div class="col-12 col-md-4 position-relative">
                                <label class="x-small fw-bold text-muted mb-1">Vehículo <span class="text-danger">*</span></label>
                                <input type="text" id="cw_vehiculo_busqueda" class="form-control form-control-sm border-primary border-opacity-10" placeholder="Placa, marca o propietario..." oninput="cwBuscarVehiculos(this.value)">
                                <div id="cw_veh_dropdown" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index:1085; max-height:240px; overflow:auto;"></div>
                            </div>
                            <div class="col-4 col-md-2">
                                <label class="x-small fw-bold text-muted mb-1">Kilometraje</label>
                                <input type="number" id="cw_kilometraje" class="form-control form-control-sm border-primary border-opacity-10 py-0" style="height:31px;" min="0" placeholder="Km">
                            </div>
                            <div class="col-4 col-md-2">
                                <label class="x-small fw-bold text-muted mb-1">Combustible</label>
                                <select id="cw_nivel_combustible" class="form-select form-select-sm border-primary border-opacity-10" style="height:31px;">
                                    <option value="">—</option>
                                    <option value="E">E - Vacío</option>
                                    <option value="1/4">1/4</option>
                                    <option value="1/2">1/2</option>
                                    <option value="3/4">3/4</option>
                                    <option value="F">F - Lleno</option>
                                </select>
                            </div>
                            <div class="col-4 col-md-2">
                                <label class="x-small fw-bold text-muted mb-1"><i class="bi bi-calendar-event"></i> Próx. cita</label>
                                <input type="date" id="cw_proxima_cita" class="form-control form-control-sm border-primary border-opacity-10 py-0" style="height:31px;">
                            </div>
                            <div class="col-12 col-md-2">
                                <label class="x-small fw-bold text-muted mb-1">Bodega <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('modulos/car-wash', 'cw_id_bodega', 'id_bodega') ?></label>
                                <select id="cw_id_bodega" name="id_bodega" class="form-select form-select-sm border-primary border-opacity-10" style="height:31px;" title="Bodega de donde se toma el inventario al facturar">
                                    <option value="">Seleccione...</option>
                                    <?php if (isset($bodegas)): ?>
                                        <?php foreach ($bodegas as $b): ?>
                                            <option value="<?= $b['id'] ?>" <?= !empty($b['es_default']) ? 'selected' : '' ?>><?= htmlspecialchars($b['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                        <input type="hidden" id="cw_numero_orden">
                        <div class="mt-1" id="cw_info_cliente" style="font-size:.78rem"></div>
                    </div>

                    <!-- Orden facturada / anulada: solo lectura (lo valida también el servidor). -->
                    <div id="cw_aviso_bloqueo" class="alert alert-warning py-1 px-2 small mt-2 mb-0 d-none">
                        <i class="bi bi-lock-fill me-1"></i><span></span>
                    </div>

                    <!-- Servicios / Productos (grilla igual que factura de venta) -->
                    <div class="mt-2 border rounded-3 overflow-hidden bg-white shadow-sm">
                        <div class="table-responsive" style="max-height: 350px;">
                            <table class="table table-sm table-detalle mb-0 text-nowrap" id="cw_tabla_detalle">
                                <thead>
                                    <tr class="table-light border-bottom">
                                        <th class="ps-3 py-2 small fw-bold text-muted" data-det-col="codigo" style="width: 9%;">Código</th>
                                        <th class="py-2 small fw-bold text-muted" data-det-col="descripcion" style="width: 26%;">Descripción</th>
                                        <th class="py-2 small fw-bold text-muted" style="width: 7%;">Adicional</th>
                                        <th class="py-2 small fw-bold text-muted col-medida-header col-medida d-none" style="width: 8%;">Medida</th>
                                        <th class="py-2 small fw-bold text-muted text-center" style="width: 6%;">Cant.</th>
                                        <th class="py-2 small fw-bold text-muted col-lista-precios d-none" style="width: 12%;">Precios</th>
                                        <th class="py-2 small fw-bold text-muted text-end" style="width: 8%;">P. Sin Imp.</th>
                                        <th class="py-2 small fw-bold text-muted text-end" style="width: 8%;">P. Con Imp.</th>
                                        <th class="py-2 small fw-bold text-muted text-end" style="width: 10%;">Desc.</th>
                                        <th class="py-2 small fw-bold text-muted text-center" style="width: 7%;">Iva</th>
                                        <?php if (!empty($empresa['obligatorio_lotes']) && ($empresa['obligatorio_lotes'] === 'true' || $empresa['obligatorio_lotes'] === true)): ?>
                                            <th class="py-2 small fw-bold text-muted text-center" style="width:8%;">Lote</th>
                                        <?php endif; ?>
                                        <?php if (!empty($empresa['obligatorio_caducidad']) && ($empresa['obligatorio_caducidad'] === 'true' || $empresa['obligatorio_caducidad'] === true)): ?>
                                            <th class="py-2 small fw-bold text-muted text-center" style="width:9%;">Caducidad</th>
                                        <?php endif; ?>
                                        <?php if (!empty($empresa['obligatorio_nup']) && ($empresa['obligatorio_nup'] === 'true' || $empresa['obligatorio_nup'] === true)): ?>
                                            <th class="py-2 small fw-bold text-muted text-center" style="width:9%;">NUP / Serial</th>
                                        <?php endif; ?>
                                        <th class="py-2 small fw-bold text-muted text-end pe-4" style="width: 78px; min-width: 78px;">Subtotal</th>
                                        <th style="width: 40px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="cw_tbodyDetalle"></tbody>
                            </table>
                        </div>
                        <div class="p-2 border-top bg-light d-flex justify-content-between align-items-center">
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold cw-solo-edicion" onclick="cwAgregarLinea()">
                                <i class="bi bi-plus-circle me-1"></i> Agregar línea
                            </button>
                            <div class="small fw-bold text-muted pe-3">Items: <span id="cw-count-items">0</span></div>
                        </div>
                    </div>

                    <div class="row g-2 mt-2">
                        <!-- Información Adicional (pestaña, igual que factura de venta) -->
                        <div class="col-12 col-md-7">
                            <ul class="nav nav-tabs nav-tabs-sm mb-0" role="tablist">
                                <li class="nav-item"><button class="nav-link active py-1 small" data-bs-toggle="tab" data-bs-target="#cw-subtab-info" type="button"><i class="bi bi-info-circle me-1"></i>Info. Adicional</button></li>
                                <li class="nav-item"><button class="nav-link py-1 small" data-bs-toggle="tab" data-bs-target="#cw-subtab-condiciones" type="button"><i class="bi bi-clipboard2-check me-1"></i>Condiciones de ingreso</button></li>
                            </ul>
                            <div class="tab-content bg-white border p-2 rounded-bottom" style="min-height:120px;">
                                <div class="tab-pane fade show active" id="cw-subtab-info" role="tabpanel">
                                    <div class="border rounded-2 overflow-hidden bg-white">
                                        <div class="table-responsive" style="max-height: 200px;">
                                            <table class="table table-sm mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th class="ps-2 py-0 small fw-bold text-muted" style="width: 40%;">Concepto</th>
                                                        <th class="py-0 small fw-bold text-muted" style="width: 50%;">Detalle</th>
                                                        <th class="py-0" style="width: 10%;"></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="cw_info_body"></tbody>
                                            </table>
                                        </div>
                                        <div class="p-1 border-top bg-light">
                                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold ms-2 cw-solo-edicion" onclick="cwAgregarInfo()">
                                                <i class="bi bi-plus-circle me-1"></i> Agregar línea
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <!-- Condiciones de ingreso: texto con formato (como Condiciones de la Proforma).
                                     Se imprime en el Acta de ingreso del vehículo. -->
                                <div class="tab-pane fade" id="cw-subtab-condiciones" role="tabpanel">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span class="x-small text-muted">Estado en que ingresa el vehículo (golpes, rayones, objetos, accesorios…). Sale en el Acta de ingreso, que se puede imprimir y enviar por correo.</span>
                                        <button type="button" class="btn btn-outline-danger btn-sm px-2 ms-auto flex-shrink-0" onclick="cwPdfIngreso()" title="Acta de ingreso del vehículo">
                                            <i class="bi bi-file-earmark-pdf me-1"></i>Acta de ingreso
                                        </button>
                                    </div>
                                    <div id="cw_condicionesEditor" class="cw-quill bg-white"></div>
                                </div>
                            </div>
                        </div>
                        <!-- Totales (estilo factura) -->
                        <div class="col-12 col-md-5">
                            <div class="border rounded-3 p-2 bg-light small">
                                <div class="d-flex justify-content-between align-items-center mb-1"><span class="text-muted">Subtotal</span><span id="cw-lbl-subtotal">0.00</span></div>
                                <div id="cw-lbl-subtotales-iva"></div>
                                <div class="d-flex justify-content-between align-items-center mb-1 text-danger"><span>Descuento</span><span id="cw-lbl-descuento">0.00</span></div>
                                <div id="cw-lbl-ivas-grupo"></div>
                                <div class="d-flex justify-content-between align-items-center mb-1 d-none" id="cw-lbl-ice-row"><span class="text-muted">(+) ICE</span><span id="cw-lbl-ice">0.00</span></div>
                                <hr class="my-1">
                                <div class="d-flex justify-content-between fw-bold fs-6"><span>TOTAL</span><span id="cw-lbl-total">0.00</span></div>
                            </div>
                        </div>
                    </div>
                </form>
                </div><!-- /cw-pane-general -->

                <!-- Historial: todas las órdenes de un vehículo o de un cliente -->
                <div class="tab-pane fade" id="cw-pane-historial" role="tabpanel">
                    <div class="d-flex flex-wrap align-items-start gap-2 mb-2">
                        <div style="width:150px">
                            <label class="x-small fw-bold text-muted mb-1 d-block">Buscar por</label>
                            <select id="cw_hist_modo" class="form-select form-select-sm" onchange="cwHistorialModoChange()">
                                <option value="vehiculo">Vehículo</option>
                                <option value="cliente">Cliente</option>
                            </select>
                        </div>
                        <div style="width:340px">
                            <label class="x-small fw-bold text-muted mb-1 d-block" id="cw_hist_lbl">Placa, marca o propietario</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                                <input type="text" id="cw_hist_q" class="form-control" placeholder="Escriba al menos 2 letras..." oninput="cwHistorialBuscarTexto()">
                            </div>
                        </div>
                        <div>
                            <label class="x-small fw-bold text-muted mb-1 d-block">&nbsp;</label>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="cwHistorialDeEstaOrden()" title="Historial del vehículo / cliente de esta orden">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>De esta orden
                            </button>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-3 small mb-2" id="cw_hist_resumen"></div>
                    <div class="border rounded-3 overflow-auto bg-white" style="max-height:420px;">
                        <table class="table table-sm table-hover mb-0 text-nowrap" style="font-size:.8rem;">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-2">Fecha</th>
                                    <th>N° Orden</th>
                                    <th>Placa</th>
                                    <th>Cliente</th>
                                    <th>Servicios / productos</th>
                                    <th class="text-end">Total</th>
                                    <th>Documento</th>
                                    <th class="text-center pe-2">Estado</th>
                                </tr>
                            </thead>
                            <tbody id="cw_hist_body">
                                <tr><td colspan="8" class="text-center text-muted py-4">Busque un vehículo o un cliente.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Facturación: en qué factura(s) o recibo(s) se emitió esta orden -->
                <div class="tab-pane fade" id="cw-pane-facturacion" role="tabpanel">
                    <div class="small text-muted mb-2">
                        Documentos de venta emitidos desde esta orden, con su estado actual. Si la factura o el recibo se
                        anula o elimina en su módulo, la orden queda libre para corregirse y volver a facturarse; el documento
                        anterior se conserva aquí.
                    </div>
                    <div class="border rounded-3 overflow-auto bg-white">
                        <table class="table table-sm table-hover mb-0 text-nowrap" style="font-size:.8rem;">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-2">Fecha</th>
                                    <th>Documento</th>
                                    <th>Número</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-center">Estado</th>
                                    <th>Origen</th>
                                    <th>Usuario</th>
                                    <th class="text-center pe-2" style="width:50px;"></th>
                                </tr>
                            </thead>
                            <tbody id="cw_docs_body">
                                <tr><td colspan="8" class="text-center text-muted py-4">La orden aún no se ha facturado.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                </div><!-- /tab-content -->
            </div>

            <div class="modal-footer py-2 d-flex justify-content-between">
                <div>
                    <button type="button" class="btn btn-outline-danger btn-sm d-none" id="cw_btn_eliminar" onclick="cwEliminar()"><i class="bi bi-trash"></i> Eliminar</button>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary btn-sm" id="cw_btn_guardar" onclick="cwGuardar()"><i class="bi bi-save me-1"></i> Guardar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script src="<?= BASE_URL ?>/js/components/detalle_columnas.js?v=<?= asset_ver('/js/components/detalle_columnas.js') ?>"></script>
<style>
    .cw-quill .ql-toolbar.ql-snow { padding: 3px 4px; border-radius: 4px 4px 0 0; }
    .cw-quill .ql-toolbar.ql-snow .ql-formats { margin-right: 6px; }
    .cw-quill .ql-container.ql-snow { border-radius: 0 0 4px 4px; font-size: 0.8rem; }
    .cw-quill .ql-editor { min-height: 110px; max-height: 220px; overflow-y: auto; }
    .cw-quill .ql-editor.ql-blank::before { font-style: italic; color: #adb5bd; }
    /* Orden facturada o anulada: sin agregar, quitar ni descontar líneas. */
    #modalOrdenCW.cw-solo-lectura .cw-solo-edicion,
    #modalOrdenCW.cw-solo-lectura #cw_tbodyDetalle .btn,
    #modalOrdenCW.cw-solo-lectura #cw_info_body .btn { display: none !important; }
    #modalOrdenCW.cw-solo-lectura #cw_tbodyDetalle .input-detalle:disabled,
    #modalOrdenCW.cw-solo-lectura #cw_info_body :disabled { background: transparent; color: inherit; opacity: 1; }
</style>
<script>
(function () {
    const RUTA = window.RUTA_MODULO_CW;

    // Código y Descripción del detalle, igual que en Factura de Venta: la descripción crece
    // con su texto y las dos columnas se ensanchan arrastrando el borde del encabezado
    // (doble clic: ajustar al texto; el ancho se guarda por usuario).
    // Ver public/js/components/detalle_columnas.js. Sin el componente, la orden sigue igual.
    const CW_DET = typeof CMG_detalleColumnas !== 'function'
        ? { engancharDescripcion() {}, ajustarDescripciones() {}, pausarAjuste() {} }
        : CMG_detalleColumnas({
            tabla:   '#cw_tabla_detalle',
            tbody:   '#cw_tbodyDetalle',
            modal:   '#modalOrdenCW',
            anchos:  <?= json_encode((object) \App\Helpers\PreferenciasHelper::getAnchosDetalle($vistaConfig ?? [])) ?>,
            modulo:  'modulos/car-wash',
            urlBase: '<?= rtrim(BASE_URL, '/') ?>',
        });

    // ─── Editor de condiciones de ingreso (Quill, mismo que Proforma) ─────────
    // Sin imágenes a propósito (irían en base64 dentro de la columna de texto).
    let cwQuillInst = null;
    function cwQuill() {
        if (!cwQuillInst && window.Quill && document.getElementById('cw_condicionesEditor')) {
            cwQuillInst = new Quill('#cw_condicionesEditor', {
                theme: 'snow',
                placeholder: 'Ej.: rayón en la puerta trasera izquierda, tapicería manchada, deja gata y llanta de repuesto…',
                modules: { toolbar: [['bold', 'italic', 'underline'], [{ color: [] }], [{ list: 'ordered' }, { list: 'bullet' }], ['clean']] },
            });
            cwQuillInst.on('text-change', (d, o, source) => { if (source === 'user') cwBorradorCambio(); });
        }
        return cwQuillInst;
    }
    function cwCondicionesHtml() { const q = cwQuill(); return (q && q.getText().trim()) ? q.root.innerHTML : ''; }
    function cwSetCondiciones(html) { const q = cwQuill(); if (q) q.setContents(html ? q.clipboard.convert(html) : [], 'silent'); }
    const DEC_P = (window.EMPRESA_CONFIG && window.EMPRESA_CONFIG.decimales_precio) || 2;
    let modal, vehTimer = null, cliTimer = null, prodTimers = {};
    let CW_CUR = { id: 0, id_documento: 0, tipo_documento: '', estado: '', id_vehiculo: 0, id_cliente: 0 };

    function getModal() { if (!modal) modal = new bootstrap.Modal(document.getElementById('modalOrdenCW')); return modal; }
    function num(v) { const n = parseFloat(v); return isNaN(n) ? 0 : n; }
    function fmt(v, d) { return num(v).toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d }); }
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
    function cwFocus(id) { const el = document.getElementById(id); if (el) { try { el.focus(); if (el.select) el.select(); } catch (e) {} } }

    // ─── Borrador local (orden sin guardar) ───────────────────────────────────
    // Mientras se llena una orden, lo escrito se guarda en el navegador (localStorage), por
    // empresa + usuario + orden ('nueva' o el id). Si se cierra el modal, se recarga la página
    // o se cae la conexión antes de Guardar, al volver a abrir se ofrece recuperarlo. Se borra
    // al guardar o eliminar la orden, o si el usuario lo descarta. Es solo una comodidad del
    // navegador: nunca reemplaza lo guardado en el servidor.
    const CW_BORR_BASE = 'cw_borrador:' + (window.CW_EMP_USR || '0:0');
    let CW_BORR_ON = false, borrTimer = null;
    function cwBorrKey() { return CW_BORR_BASE + ':' + (document.getElementById('cw_id').value || 'nueva'); }
    function cwBorrLeer(key) { try { const t = localStorage.getItem(key); return t ? JSON.parse(t) : null; } catch (e) { return null; } }
    function cwBorrBorrar(key) { try { localStorage.removeItem(key || cwBorrKey()); } catch (e) {} }
    function cwBorrSnapshot() {
        const val = id => (document.getElementById(id) || {}).value || '';
        const veh = document.getElementById('cw_vehiculo_busqueda');
        const lineas = [];
        document.querySelectorAll('#cw_tbodyDetalle .row-detalle').forEach(tr => {
            const q = c => (tr.querySelector(c) || {}).value || '';
            const selIva = tr.querySelector('.input-iva');
            const opt = selIva ? selIva.options[selIva.selectedIndex] : null;
            if (!q('.input-descripcion').trim() && !q('.input-id-producto')) return;
            lineas.push({
                id_producto: q('.input-id-producto'), producto_codigo: q('.input-codigo'), descripcion: q('.input-descripcion'),
                adicional: q('.input-adicional'), cantidad: q('.input-cantidad'), precio_unitario: q('.input-precio'),
                descuento: q('.input-desc'), id_tarifa_iva: opt ? (opt.dataset.id || '') : '', porcentaje_iva: opt ? opt.value : 0,
                es_libre: q('.input-es-libre') === '1', tipo_linea: tr.dataset.tipoProduccion === '02' ? 'servicio' : 'producto',
                lote: q('.input-lote') || tr.dataset.originalLote || '', fecha_caducidad: q('.input-caducidad') || tr.dataset.originalCad || '', nup: q('.input-nup'),
                id_unidad_medida: q('.input-medida'), producto_inventariable: tr.dataset.inventariable === '1',
                producto_tipo_produccion: tr.dataset.tipoProduccion || '',
                producto_id_tipo_medida: tr.dataset.idTipoMedida || '', producto_id_medida: tr.dataset.idMedidaBase || '',
            });
        });
        const info = [];
        document.querySelectorAll('#cw_info_body .row-info-adicional').forEach(r => {
            const n = (r.querySelector('.input-info-concepto') || {}).value || '', v = (r.querySelector('.input-info-detalle') || {}).value || '';
            if (n.trim() || v.trim()) info.push({ nombre: n, valor: v, tipo: r.dataset.tipo || '' });
        });
        return {
            ts: Date.now(), id: val('cw_id'), id_punto: val('cw_select_serie'), fecha_ingreso: val('cw_fecha_ingreso'),
            id_vehiculo: val('cw_id_vehiculo'), vehiculo_texto: veh.value, placa: veh.dataset.placa || '', marca: veh.dataset.marca || '', modelo: veh.dataset.modelo || '',
            id_cliente: val('cw_id_cliente'), cliente_texto: val('cw_cliente_busqueda'),
            kilometraje: val('cw_kilometraje'), combustible: val('cw_nivel_combustible'), proxima_cita: val('cw_proxima_cita'), id_bodega: val('cw_id_bodega'),
            lineas, info, condiciones_html: cwCondicionesHtml(),
        };
    }
    function cwBorrEscribir() {
        clearTimeout(borrTimer); borrTimer = null;
        if (!CW_BORR_ON) return;
        const snap = cwBorrSnapshot();
        // Sin nada que valga la pena recuperar: no se deja basura guardada.
        if (!snap.id_vehiculo && !snap.id_cliente && !snap.lineas.length && !snap.condiciones_html) { cwBorrBorrar(); return; }
        try { localStorage.setItem(cwBorrKey(), JSON.stringify(snap)); } catch (e) {}
    }
    window.cwBorradorCambio = function () {
        if (!CW_BORR_ON) return;
        clearTimeout(borrTimer);
        borrTimer = setTimeout(cwBorrEscribir, 500);
    };
    function cwBorrAplicar(b) {
        const set = (id, v) => { const el = document.getElementById(id); if (el) el.value = v || ''; };
        if (!document.getElementById('cw_id').value) {
            const sel = document.getElementById('cw_select_serie');
            if (b.id_punto && sel.querySelector(`option[value="${b.id_punto}"]`)) { sel.value = b.id_punto; cwSerieChange(); }
            if (b.fecha_ingreso) set('cw_fecha_ingreso', b.fecha_ingreso);
        }
        set('cw_id_vehiculo', b.id_vehiculo);
        const veh = document.getElementById('cw_vehiculo_busqueda');
        veh.value = b.vehiculo_texto || ''; veh.dataset.placa = b.placa || ''; veh.dataset.marca = b.marca || ''; veh.dataset.modelo = b.modelo || '';
        set('cw_id_cliente', b.id_cliente); set('cw_cliente_busqueda', b.cliente_texto);
        set('cw_kilometraje', b.kilometraje); set('cw_nivel_combustible', b.combustible);
        set('cw_proxima_cita', b.proxima_cita); set('cw_id_bodega', b.id_bodega);
        document.getElementById('cw_tbodyDetalle').innerHTML = '';
        (b.lineas || []).forEach(l => {
            cwCargarLineaGuardada(l);
            const tr = document.querySelector('#cw_tbodyDetalle .row-detalle:last-child');
            if (tr && l.adicional) tr.querySelector('.input-adicional').value = l.adicional;
        });
        if (!(b.lineas || []).length) cwAgregarLinea();
        document.getElementById('cw_info_body').innerHTML = '';
        (b.info || []).forEach(ia => ia.tipo === 'correo-cliente' ? cwActualizarInfoCorreoCliente(ia.valor) : cwAgregarInfo(ia));
        if (b.condiciones_html) cwSetCondiciones(b.condiciones_html);
        cwCalcTotales();
    }
    // Si hay un borrador para la orden que se abre, pregunta si recuperarlo.
    async function cwBorrOfrecer() {
        const key = cwBorrKey();
        const b = cwBorrLeer(key);
        if (!b) return;
        const f = new Date(b.ts || Date.now());
        const p2 = n => String(n).padStart(2, '0');
        const cuando = `${p2(f.getDate())}-${p2(f.getMonth() + 1)}-${f.getFullYear()} ${p2(f.getHours())}:${p2(f.getMinutes())}:${p2(f.getSeconds())}`;
        const detalle = [b.placa || (b.vehiculo_texto || '').split(' — ')[0], (b.lineas || []).length + ' ítem(s)'].filter(Boolean).join(' · ');
        const r = await Swal.fire({
            icon: 'question', target: document.getElementById('modalOrdenCW'),
            title: 'Orden sin guardar',
            html: `Hay cambios de esta orden que no se guardaron (${esc(cuando)}).<br><span class="text-muted small">${esc(detalle)}</span><br>¿Desea recuperarlos?`,
            showCancelButton: true, confirmButtonText: '<i class="bi bi-arrow-counterclockwise me-1"></i> Recuperar', cancelButtonText: 'Descartar',
        });
        if (r.isConfirmed) cwBorrAplicar(b);
        else if (r.dismiss === Swal.DismissReason.cancel) cwBorrBorrar(key);
    }
    // Cualquier cambio del formulario (escritura o selects) actualiza el borrador.
    ['input', 'change'].forEach(ev => document.getElementById('formOrdenCW')?.addEventListener(ev, () => cwBorradorCambio()));
    // Al cerrar el modal se escribe de inmediato lo pendiente y se deja de registrar.
    document.getElementById('modalOrdenCW')?.addEventListener('hide.bs.modal', () => { if (borrTimer) cwBorrEscribir(); CW_BORR_ON = false; });
    window.addEventListener('beforeunload', () => { if (borrTimer) cwBorrEscribir(); });

    // ─── Reset / apertura ─────────────────────────────────────────────────────
    function resetForm() {
        CW_BORR_ON = false;
        document.getElementById('formOrdenCW').reset();
        ['cw_id','cw_id_vehiculo','cw_id_cliente','cw_serie','cw_id_punto_emision','cw_id_establecimiento','cw_numero_orden'].forEach(id => document.getElementById(id).value = '');
        document.getElementById('cw_secuencial').value = '';
        document.getElementById('cw_secuencial').dataset.sec = '';
        const selSerie = document.getElementById('cw_select_serie');
        if (selSerie) { selSerie.selectedIndex = 0; selSerie.disabled = false; }
        document.getElementById('cw_tbodyDetalle').innerHTML = '';
        document.getElementById('cw_info_body').innerHTML = '';
        document.getElementById('cw_info_cliente').innerHTML = '';
        cwSetCondiciones('');
        CW_CUR = { id: 0, id_documento: 0, tipo_documento: '', estado: '', id_vehiculo: 0, id_cliente: 0 };
        cwToggleDocBtns(false);
        cwRecalcular();
        // Pestañas: siempre se abre en General, con Historial y Facturación limpios.
        const tabGen = document.getElementById('cw-tab-general');
        if (tabGen) bootstrap.Tab.getOrCreateInstance(tabGen).show();
        document.getElementById('cw_hist_q').value = '';
        document.getElementById('cw_hist_resumen').innerHTML = '';
        document.getElementById('cw_hist_body').innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Busque un vehículo o un cliente.</td></tr>';
        cwRenderDocumentos([]);
    }

    function setEditable(editable, aviso) {
        ['cw_fecha_ingreso','cw_select_serie','cw_vehiculo_busqueda','cw_cliente_busqueda','cw_kilometraje',
         'cw_nivel_combustible','cw_proxima_cita','cw_id_bodega'].forEach(id => {
            const el = document.getElementById(id); if (el) el.disabled = !editable;
        });
        // La numeración de una orden ya guardada no cambia (el servidor la ignora al editar).
        if (document.getElementById('cw_id').value) document.getElementById('cw_select_serie').disabled = true;
        document.getElementById('cw_btn_guardar').classList.toggle('d-none', !editable);
        const q = cwQuill(); if (q) q.enable(!!editable);
        // Grilla e info adicional: una orden facturada (documento vigente) o anulada es solo de
        // consulta. Solo se deshabilita: al abrir otra orden las filas se vuelven a crear, así
        // no se re-habilitan campos que la configuración deja fijos (p. ej. el IVA).
        const modal = document.getElementById('modalOrdenCW');
        modal.classList.toggle('cw-solo-lectura', !editable);
        if (!editable) {
            modal.querySelectorAll('#cw_tbodyDetalle input, #cw_tbodyDetalle select, #cw_tbodyDetalle textarea, #cw_info_body input, #cw_info_body select, #cw_info_body textarea')
                .forEach(el => { el.disabled = true; });
        }
        const av = document.getElementById('cw_aviso_bloqueo');
        if (av) { av.querySelector('span').textContent = aviso || ''; av.classList.toggle('d-none', !!editable || !aviso); }
    }

    window.cwAbrirNuevo = function () {
        resetForm();
        setEditable(true);
        document.getElementById('cwTitulo').innerHTML = '<i class="bi bi-droplet-half me-1 text-info"></i> Nueva orden de Car-Wash';
        pintarBadge('borrador', 'Borrador');
        document.getElementById('cw_btn_eliminar').classList.add('d-none');
        // fecha/hora local
        const d = new Date(); const off = d.getTimezoneOffset();
        document.getElementById('cw_fecha_ingreso').value = new Date(d.getTime() - off * 60000).toISOString().slice(0, 16);
        // Serie: arranca marcada (primer punto o favorito) y carga el secuencial (como factura).
        if (typeof window.aplicarFavoritosModal === 'function') { try { window.aplicarFavoritosModal('#modalOrdenCW'); } catch (e) {} }
        const selSerie = document.getElementById('cw_select_serie');
        if (selSerie.value) cwSerieChange();
        cwAgregarLinea();
        cwAgregarInfo();   // una línea de info general lista por defecto
        getModal().show();
        // El cursor empieza en Vehículo (tras la animación del modal).
        setTimeout(() => cwFocus('cw_vehiculo_busqueda'), 250);
        setTimeout(async () => { await cwBorrOfrecer(); CW_BORR_ON = true; }, 300);
    };

    window.cwAbrirVer = function (rowEl) {
        const row = JSON.parse(rowEl.getAttribute('data-row'));
        cwAbrirVerId(row.id, false);
    };

    window.cwAbrirVerId = async function (id, irAFacturar) {
        resetForm();
        getModal().show();
        document.getElementById('cw-modal-loader')?.classList.remove('d-none');
        try {
            const res = await fetch(`${RUTA}/getDetalleAjax?id=${id}`);
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'No se pudo cargar la orden.');
            const o = data.data;
            // editable / puede_facturar los calcula el servidor: una orden facturada se libera si su
            // factura o recibo fue anulado o eliminado.
            const editable = window.CW_PERM.actualizar && !!o.editable;

            document.getElementById('cw_id').value = o.id;
            document.getElementById('cw_numero_orden').value = o.numero_orden || '';
            document.getElementById('cwTitulo').innerHTML = '<i class="bi bi-droplet-half me-1 text-info"></i> Orden ' + (o.numero_orden || '');
            pintarBadge(o.estado, o.estado, o.documento_vigente);
            CW_CUR = { id: o.id, id_documento: o.id_documento || 0, tipo_documento: o.tipo_documento || '', estado: o.estado || '',
                       id_vehiculo: o.id_vehiculo || 0, id_cliente: o.id_cliente || 0, documento_vigente: o.documento_vigente,
                       placa: o.placa || '', cliente: o.cliente_nombre || '', forma_pago_sri: o.forma_pago_sri || null };
            cwRenderDocumentos(o.documentos || []);

            // Serie / secuencial (se conserva la numeración de la orden; el selector queda bloqueado).
            const selSerie = document.getElementById('cw_select_serie');
            if (o.id_punto_emision && selSerie.querySelector(`option[value="${o.id_punto_emision}"]`)) selSerie.value = o.id_punto_emision;
            selSerie.disabled = true;
            document.getElementById('cw_serie').value = (o.establecimiento || '') + '-' + (o.punto_emision || '');
            document.getElementById('cw_id_punto_emision').value = o.id_punto_emision || '';
            document.getElementById('cw_id_establecimiento').value = o.id_establecimiento || '';
            document.getElementById('cw_secuencial').value = o.secuencial || '';
            document.getElementById('cw_secuencial').dataset.sec = o.secuencial || '';

            if (o.fecha_ingreso) document.getElementById('cw_fecha_ingreso').value = String(o.fecha_ingreso).replace(' ', 'T').slice(0, 16);
            document.getElementById('cw_id_vehiculo').value = o.id_vehiculo || '';
            document.getElementById('cw_vehiculo_busqueda').value = (o.placa || '') + (o.marca ? ' — ' + o.marca : '');
            document.getElementById('cw_id_cliente').value = o.id_cliente || '';
            document.getElementById('cw_cliente_busqueda').value = o.id_cliente ? ((o.cliente_identificacion || '') + ' — ' + (o.cliente_nombre || '')) : '';
            cwPintarInfoCliente(o);
            document.getElementById('cw_kilometraje').value = o.kilometraje || '';
            document.getElementById('cw_nivel_combustible').value = o.nivel_combustible || '';
            document.getElementById('cw_id_bodega').value = o.id_bodega || '';
            document.getElementById('cw_proxima_cita').value = o.proxima_cita ? String(o.proxima_cita).slice(0, 10) : '';

            CW_DET.pausarAjuste(true);   // cientos de líneas: medir las descripciones una sola vez al final
            (o.detalles || []).forEach(d => cwCargarLineaGuardada(d));
            (o.info_adicional || []).forEach(ia => cwAgregarInfo(ia));
            cwSetCondiciones(o.condiciones_html || '');
            // Novedades antiguas (órdenes previas) se muestran como líneas de Info. Adicional.
            (o.novedades || []).forEach(n => cwAgregarInfo({ nombre: 'Novedad', valor: n.descripcion }));
            if (!(o.detalles || []).length) cwAgregarLinea();
            cwCalcTotales();

            let aviso = '';
            if (!editable) {
                if (o.estado === 'anulado') aviso = 'Orden anulada: solo se puede consultar.';
                else if (o.id_documento) aviso = `Orden facturada en ${o.tipo_documento === 'RECIBO' ? 'el recibo de venta' : 'la factura'} ${o.numero_documento || ''}: no se puede modificar. Para corregirla, anule o elimine primero ese documento en su módulo.`;
                else aviso = 'Orden facturada: no se puede modificar.';
            }
            setEditable(editable, aviso);
            document.getElementById('cw_btn_eliminar').classList.toggle('d-none', !(window.CW_PERM.eliminar && !!o.editable));

            // Botones de documento: generar si es borrador sin documento; PDF/correo/wa si ya hay documento.
            cwToggleDocBtns(true, o);
            if (editable) { await cwBorrOfrecer(); CW_BORR_ON = true; }
            else cwBorrBorrar(); // ya no se puede editar: un borrador viejo no sirve
            if (irAFacturar && o.puede_facturar) {
                document.getElementById('cw_btn_factura').classList.add('shadow');
            }
        } catch (e) {
            Swal.fire('Error', e.message, 'error');
        } finally {
            CW_DET.pausarAjuste(false);
            document.getElementById('cw-modal-loader')?.classList.add('d-none');
        }
    };

    function pintarBadge(estado, label, docVigente) {
        estado = estado || 'borrador';
        const nombres = { borrador: 'Borrador', facturado: 'Facturado', anulado: 'Anulado' };
        const b = document.getElementById('cw_estado_badge');
        b.classList.remove('d-none');
        b.textContent = nombres[estado] || label || estado;
        let cls = 'bg-warning bg-opacity-10 text-warning'; // borrador
        if (estado === 'facturado' && docVigente === false) b.textContent = 'Documento anulado · por re-facturar';
        else if (estado === 'facturado') cls = 'bg-success bg-opacity-10 text-success';
        else if (estado === 'anulado') cls = 'bg-danger bg-opacity-10 text-danger';
        b.className = 'badge ms-2 ' + cls;
    }

    function cwPintarInfoCliente(o) {
        const parts = [];
        if (o.cliente_direccion) parts.push('<i class="bi bi-geo-alt"></i> ' + esc(o.cliente_direccion));
        if (o.cliente_email) parts.push('<i class="bi bi-envelope"></i> ' + esc(o.cliente_email));
        if (o.cliente_telefono) parts.push('<i class="bi bi-telephone"></i> ' + esc(o.cliente_telefono));
        const aviso = (o.id_cliente && o.cliente_activo === false)
            ? '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 me-2"><i class="bi bi-person-x me-1"></i>Cliente inactivo: no se puede facturar. Actívelo en Clientes o elija otro.</span>'
            : '';
        document.getElementById('cw_info_cliente').innerHTML = aviso + (parts.length
            ? '<span class="text-muted">' + parts.join(' &nbsp; ') + '</span>' : '');
    }

    // Habilita/deshabilita los botones de documento según el estado de la orden.
    function cwToggleDocBtns(mostrar, o) {
        const hayDoc = mostrar && o && !!o.id_documento && o.documento_vigente !== false;
        // Solo se factura a clientes activos (el servidor lo vuelve a validar al emitir).
        const puedeFacturar = mostrar && o && !!o.puede_facturar && window.CW_PERM.crear && !(o.id_cliente && o.cliente_activo === false);
        const esFactura = hayDoc && (o.tipo_documento === 'FACTURA');
        const ordenGuardada = !!(document.getElementById('cw_id').value);
        ['cw_btn_factura','cw_btn_recibo'].forEach(id => { const b = document.getElementById(id); if (b) b.disabled = !puedeFacturar; });
        const bp = document.getElementById('cw_btn_pdf'); if (bp) bp.disabled = !ordenGuardada; // PDF de la orden
        const ba = document.getElementById('cw_btn_acta'); if (ba) ba.disabled = !ordenGuardada; // Acta de ingreso
        const bc = document.getElementById('cw_btn_correo'); if (bc) bc.disabled = !ordenGuardada; // Correo de la orden
        const bw = document.getElementById('cw_btn_whatsapp'); if (bw) bw.disabled = !esFactura;
    }

    // ─── Serie / secuencial (mismas reglas que recibo de venta) ───────────────
    window.cwSerieChange = async function () {
        const sel = document.getElementById('cw_select_serie');
        const opt = sel.options[sel.selectedIndex];
        const idPunto = sel.value;
        if (!idPunto || !opt) {
            document.getElementById('cw_serie').value = '';
            document.getElementById('cw_id_punto_emision').value = '';
            document.getElementById('cw_id_establecimiento').value = '';
            document.getElementById('cw_secuencial').value = '';
            return;
        }
        const est = opt.dataset.codEst || '';
        const punto = opt.dataset.codPunto || '';
        document.getElementById('cw_serie').value = est + '-' + punto;
        document.getElementById('cw_id_punto_emision').value = idPunto;
        document.getElementById('cw_id_establecimiento').value = opt.dataset.idEst || '';
        await cwCargarSecuencial(idPunto);
        cwCalcTotales(); // el modo de IVA depende del establecimiento de la serie
    };
    async function cwCargarSecuencial(idPunto) {
        try {
            const res = await fetch(`${RUTA}/getSecuencialAjax?id_punto_emision=${idPunto}`);
            const data = await res.json();
            if (!data.ok) { document.getElementById('cw_secuencial').value = ''; Swal.fire('Atención', data.msg || 'No hay secuencial disponible.', 'warning'); return; }
            const sec = data.formateado || String(data.secuencial || '').padStart(9, '0');
            document.getElementById('cw_secuencial').value = sec;
            document.getElementById('cw_secuencial').dataset.sec = data.secuencial || '';
        } catch (e) { document.getElementById('cw_secuencial').value = ''; }
    }

    // ─── Vehículo ─────────────────────────────────────────────────────────────
    window.cwBuscarVehiculos = function (q) {
        clearTimeout(vehTimer);
        const dd = document.getElementById('cw_veh_dropdown');
        if (!q || q.length < 2) { dd.classList.add('d-none'); return; }
        vehTimer = setTimeout(async () => {
            const res = await fetch(`${RUTA}/buscarVehiculosAjax?q=${encodeURIComponent(q)}`);
            const data = await res.json();
            dd.innerHTML = '';
            (data.data || []).forEach(v => {
                const a = document.createElement('a');
                a.href = '#'; a.className = 'list-group-item list-group-item-action py-1';
                a.innerHTML = `<span class="fw-bold text-primary small">${esc(v.placa || '')}</span>
                               <span class="small ms-2">${esc(v.marca || '')}</span>
                               <span class="small text-muted ms-1">${v.propietario ? '· ' + esc(v.propietario) : ''}</span>`;
                a.onclick = (ev) => { ev.preventDefault(); cwSeleccionarVehiculo(v); };
                dd.appendChild(a);
            });
            if (!data.data || !data.data.length) dd.innerHTML = '<span class="list-group-item small text-muted">Sin resultados. Use "Vehículo" para crear uno nuevo.</span>';
            dd.classList.remove('d-none');
        }, 300);
    };
    function cwSeleccionarVehiculo(v) {
        cwBorradorCambio();
        document.getElementById('cw_id_vehiculo').value = v.id;
        document.getElementById('cw_vehiculo_busqueda').value = (v.placa || '') + (v.marca ? ' — ' + v.marca : '');
        document.getElementById('cw_veh_dropdown').classList.add('d-none');
        // snapshot en dataset para el guardado
        const inp = document.getElementById('cw_vehiculo_busqueda');
        inp.dataset.placa = v.placa || '';
        inp.dataset.marca = v.marca || '';
        inp.dataset.modelo = v.modelo || '';
    }

    // ─── Cliente ──────────────────────────────────────────────────────────────
    window.cwBuscarClientes = function (q) {
        clearTimeout(cliTimer);
        const dd = document.getElementById('cw_cli_dropdown');
        if (!q || q.length < 2) { dd.classList.add('d-none'); return; }
        cliTimer = setTimeout(async () => {
            const res = await fetch(`${RUTA}/buscarClientesAjax?q=${encodeURIComponent(q)}`);
            const data = await res.json();
            dd.innerHTML = '';
            (data.data || []).forEach(c => {
                const a = document.createElement('a');
                a.href = '#'; a.className = 'list-group-item list-group-item-action py-1';
                a.innerHTML = `<span class="small fw-semibold">${esc(c.nombre || '')}</span>
                               <span class="small text-muted ms-1">${c.identificacion ? '· ' + esc(c.identificacion) : ''}</span>`;
                a.onclick = (ev) => { ev.preventDefault(); cwSeleccionarCliente(c); };
                dd.appendChild(a);
            });
            if (!data.data || !data.data.length) dd.innerHTML = '<span class="list-group-item small text-muted">Sin resultados. Use "Cliente" para crear uno nuevo.</span>';
            dd.classList.remove('d-none');
        }, 300);
    };
    function cwSeleccionarCliente(c) {
        cwBorradorCambio();
        document.getElementById('cw_id_cliente').value = c.id;
        document.getElementById('cw_cliente_busqueda').value = (c.identificacion || '') + ' — ' + (c.nombre || '');
        document.getElementById('cw_cli_dropdown').classList.add('d-none');
        cwPintarInfoCliente({ cliente_direccion: c.direccion, cliente_email: c.correo, cliente_telefono: c.telefono });
        // Agrega/actualiza el correo del cliente en Info. Adicional (igual que factura).
        cwActualizarInfoCorreoCliente(c.correo || '');
    }

    // Fila fija con el correo del cliente en Info. Adicional (se actualiza al cambiar de cliente).
    window.cwActualizarInfoCorreoCliente = function (email) {
        const tbody = document.getElementById('cw_info_body');
        let fila = tbody.querySelector('tr[data-tipo="correo-cliente"]');
        if (!fila) {
            // Reutiliza una línea de correo ya guardada (evita duplicados al reseleccionar cliente).
            fila = Array.from(tbody.querySelectorAll('tr.row-info-adicional')).find(r => (r.querySelector('.input-info-concepto')?.value || '').trim().toLowerCase() === 'correo del cliente');
            if (fila) fila.dataset.tipo = 'correo-cliente';
        }
        email = (email || '').trim();
        if (!email) { if (fila) fila.remove(); return; }
        if (fila) { fila.querySelector('.input-info-detalle').value = email; return; }
        const tr = document.createElement('tr');
        tr.className = 'row-info-adicional';
        tr.dataset.tipo = 'correo-cliente';
        tr.innerHTML = `
            <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent input-info-concepto" style="padding:0 4px;height:20px;font-size:0.78rem;" value="Correo del cliente" readonly></td>
            <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent input-info-detalle" style="padding:0 4px;height:20px;font-size:0.78rem;" value="${esc(email)}"></td>
            <td class="p-0 text-center pe-1"><span class="text-muted small" title="Se actualiza al cambiar el cliente"><i class="bi bi-lock-fill"></i></span></td>`;
        tbody.appendChild(tr);
    };

    // Cerrar dropdowns al hacer clic fuera
    document.addEventListener('click', (e) => {
        if (!e.target.closest('#cw_vehiculo_busqueda') && !e.target.closest('#cw_veh_dropdown'))
            document.getElementById('cw_veh_dropdown')?.classList.add('d-none');
        if (!e.target.closest('#cw_cliente_busqueda') && !e.target.closest('#cw_cli_dropdown'))
            document.getElementById('cw_cli_dropdown')?.classList.add('d-none');
    });

    // ─── Grilla de ítems (portada de Factura de Venta) ────────────────────────
    const EMPRESA_CONFIG = window.EMPRESA_CONFIG || {};
    const TARIFAS_IVA = window.TARIFAS_IVA || [];
    const UNIDADES = window.UNIDADES || [];
    const DEC_PRECIO = EMPRESA_CONFIG.decimales_precio ?? 2;
    // Redondeo a centavos igual que PHP round(): Math.round(1.005 * 100) da 100 (1.00) por la
    // representación binaria, PHP da 1.01. toPrecision(15) corrige ese ruido antes de redondear.
    const r2 = v => { const s = v < 0 ? -1 : 1; return s * Math.round(parseFloat((Math.abs(v) * 100).toPrecision(15))) / 100; };
    const r6 = v => parseFloat((parseFloat(v) || 0).toFixed(6));
    // Modo de IVA del establecimiento de la serie elegida (el mismo que usa el servidor).
    function cwModoIva() {
        const idPunto = document.getElementById('cw_id_punto_emision')?.value || document.getElementById('cw_select_serie')?.value;
        const p = (window.CW_PUNTOS || []).find(x => String(x.id) === String(idPunto));
        return (p && p.calculo_iva) || EMPRESA_CONFIG.calculo_iva || 'linea_linea';
    }
    function cwDebounce(fn, wait) { let t; return function (...a) { clearTimeout(t); t = setTimeout(() => fn.apply(this, a), wait); }; }


    // Crea una fila vacía de la grilla y cablea su búsqueda de producto.
    window.cwAgregarLinea = function () {
        const tbody = document.getElementById('cw_tbodyDetalle');
        const tr = document.createElement('tr');
        tr.className = 'row-detalle';
        tr.innerHTML = `
            <td class="ps-3">
                <input type="text" class="form-control form-control-sm input-detalle input-codigo" placeholder="Código" title="Buscar por código">
            </td>
            <td class="position-relative">
                <textarea rows="2" class="form-control form-control-sm input-detalle input-descripcion" style="resize:none; overflow:auto; line-height:1.15;" placeholder="${EMPRESA_CONFIG.facturacion_libre ? 'Escribe o busca un servicio/producto...' : 'Buscar servicio o producto...'}"></textarea>
                <input type="hidden" class="input-id-producto">
                <input type="hidden" class="input-es-libre" value="0">
                <input type="hidden" class="input-ice-pct" value="0">
                <input type="hidden" class="input-ice-val" value="0">
                <input type="hidden" class="input-precio-base-original" value="0">
                <input type="hidden" class="input-factor-original" value="1">
                <div class="mt-1 container-variante d-none">
                    <select class="form-select form-select-sm input-detalle input-variante" style="font-size:0.7rem; height:24px; padding:0 5px;"><option value="">Variantes...</option></select>
                </div>
            </td>
            <td><input type="text" class="form-control form-control-sm input-detalle input-adicional text-muted fst-italic" placeholder="Info adicional"></td>
            <td class="col-medida d-none">
                <select class="form-select form-select-sm input-detalle input-medida d-none"><option value="">Medida</option></select>
            </td>
            <td><input type="number" class="form-control form-control-sm input-detalle text-center input-cantidad" value="1" step="any" oninput="cwCalcFila(this)"></td>
            <td class="col-lista-precios d-none"><select class="form-select form-select-sm input-detalle input-lista-precios d-none"><option value="">P. Base</option></select></td>
            <td><input type="number" class="form-control form-control-sm input-detalle text-end input-precio" value="${(0).toFixed(DEC_PRECIO)}" step="any" oninput="cwCalcSinImp(this)" onblur="this.value=parseFloat(this.value||0).toFixed(${DEC_PRECIO})" ${EMPRESA_CONFIG.editar_precio_factura ? '' : 'readonly'}></td>
            <td><input type="number" class="form-control form-control-sm input-detalle text-end input-precio-iva" value="${(0).toFixed(DEC_PRECIO)}" step="any" oninput="cwCalcConImp(this)" onblur="this.value=parseFloat(this.value||0).toFixed(${DEC_PRECIO})" ${EMPRESA_CONFIG.editar_precio_factura ? '' : 'readonly'}></td>
            <td>
                <div class="d-flex align-items-center">
                    <input type="number" class="form-control form-control-sm input-detalle text-end text-danger input-desc" value="0.00" step="any" min="0" oninput="cwCalcFila(this)" ${EMPRESA_CONFIG.editar_descuento_factura ? '' : 'readonly'}>
                    <button type="button" class="btn btn-link btn-sm p-1 text-primary shadow-none border-0 cw-btn-desc ${EMPRESA_CONFIG.editar_descuento_factura ? '' : 'd-none'}" onclick="cwAbrirDescuento(this)" title="Aplicar descuento rápido">
                        <i class="bi bi-plus-circle"></i>
                    </button>
                </div>
            </td>
            <td>
                <select class="form-select form-select-sm input-detalle text-center input-iva" onchange="cwSyncPrecioIva(this)" ${EMPRESA_CONFIG.editar_iva_factura ? '' : 'disabled'}>
                    ${TARIFAS_IVA.map(t => `<option value="${t.porcentaje_iva}" data-codigo="${t.codigo}" data-id="${t.id}">${t.tarifa}</option>`).join('')}
                </select>
            </td>
            ${EMPRESA_CONFIG.obligatorio_lotes ? `<td class="align-middle" style="min-width:120px;"><select class="form-select form-select-sm input-detalle input-lote d-none" style="font-size:0.75rem;"><option value="">Seleccionar Lote</option></select></td>` : ''}
            ${EMPRESA_CONFIG.obligatorio_caducidad ? `<td class="align-middle" style="min-width:120px;"><select class="form-select form-select-sm input-detalle input-caducidad d-none" style="font-size:0.75rem;"><option value="">Seleccionar Vencimiento</option></select></td>` : ''}
            ${EMPRESA_CONFIG.obligatorio_nup ? `<td class="align-middle" style="min-width:100px;"><input type="text" class="form-control form-control-sm input-detalle input-nup d-none" placeholder="NUP/Serial" style="font-size:0.75rem;"></td>` : ''}
            <td class="text-end pe-4 align-middle"><span class="subtotal-line">0.00</span></td>
            <td class="text-center p-0 align-middle" style="width:40px;">
                <button type="button" class="btn btn-link btn-sm text-danger p-0 shadow-none border-0" onclick="this.closest('tr').remove(); cwCalcTotales();" title="Eliminar ítem"><i class="bi bi-trash3 fs-6"></i></button>
            </td>`;
        tbody.appendChild(tr);

        const inputDesc = tr.querySelector('.input-descripcion');
        CW_DET.engancharDescripcion(inputDesc);
        const dropdownGlobal = document.getElementById('cw-dropdown-productos-global');

        const buscarProducto = async (q, sourceInput) => {
            q = (q || '').trim();
            if (q.length < 2) { dropdownGlobal.classList.add('d-none'); return; }
            const rect = sourceInput.getBoundingClientRect();
            dropdownGlobal.style.top = `${rect.bottom + 2}px`;
            dropdownGlobal.style.left = `${rect.left}px`;
            dropdownGlobal.style.width = `${Math.max(rect.width, 350)}px`;
            dropdownGlobal.classList.remove('d-none');
            dropdownGlobal.innerHTML = '<div class="list-group-item small text-muted">Buscando...</div>';
            try {
                // Stock según la bodega de la cabecera (aplica a toda la orden).
                const idBod = document.getElementById('cw_id_bodega').value || 0;
                const idOrd = document.getElementById('cw_id').value || 0;
                const resp = await fetch(`${RUTA}/getProductosAjax?q=${encodeURIComponent(q)}&id_bodega=${idBod}&id_orden=${idOrd}`);
                const json = await resp.json();
                dropdownGlobal.innerHTML = '';
                if (json.data && json.data.length > 0) {
                    json.data.forEach(p => {
                        const b = document.createElement('button');
                        b.type = 'button';
                        b.className = 'list-group-item list-group-item-action small py-1 border-bottom';
                        // Stock disponible (solo productos que controlan inventario)
                        let stockBadge = '';
                        if (p.controla_stock) {
                            const st = parseFloat(p.stock_actual || 0);
                            const cls = st > 0 ? 'success' : 'danger';
                            stockBadge = `<span class="badge bg-${cls} bg-opacity-10 text-${cls} border border-${cls} border-opacity-25 me-1">Stock: ${st.toFixed(2)}</span>`;
                        }
                        b.innerHTML = `<div class="d-flex justify-content-between align-items-center text-start">
                                <div class="pe-3"><div class="fw-bold text-dark">${esc(p.nombre)}</div>
                                <div class="x-small text-muted">${esc(p.codigo || '')} ${p.codigo_barras ? '| ' + esc(p.codigo_barras) : ''}</div></div>
                                <div class="text-nowrap">${stockBadge}<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10">$${parseFloat(p.precio_base || 0).toFixed(2)}</span></div></div>`;
                        b.onmousedown = (evt) => {
                            evt.preventDefault();
                            const sinStock = p.controla_stock && parseFloat(p.stock_actual || 0) <= 0;
                            cwSeleccionarProductoEnFila(p, tr);
                            dropdownGlobal.classList.add('d-none');
                            // Sin saldo: se ofrecen productos similares que sí tienen saldo.
                            if (sinStock) cwMostrarSimilares(p, tr);
                        };
                        dropdownGlobal.appendChild(b);
                    });
                    if (EMPRESA_CONFIG.facturacion_libre) cwAgregarOpcionServicioLibre(q, tr, dropdownGlobal);
                } else {
                    if (EMPRESA_CONFIG.facturacion_libre) cwAgregarOpcionServicioLibre(q, tr, dropdownGlobal);
                    else dropdownGlobal.innerHTML = '<div class="list-group-item small text-muted">Sin coincidencias en el catálogo</div>';
                }
            } catch (err) { console.error('Error productos', err); }
        };

        inputDesc.addEventListener('input', cwDebounce((e) => buscarProducto(e.target.value, inputDesc), 400));
        inputDesc.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault(); // la descripción es una sola línea (como factura)
                const firstBtn = dropdownGlobal.querySelector('button');
                if (firstBtn && !dropdownGlobal.classList.contains('d-none')) firstBtn.onmousedown(new MouseEvent('mousedown'));
            }
        });

        // Código (igual que factura): busca en el catálogo; Enter toma el primero.
        const inputCodigo = tr.querySelector('.input-codigo');
        inputCodigo.addEventListener('input', cwDebounce((e) => buscarProducto(e.target.value, inputCodigo), 400));
        inputCodigo.addEventListener('keydown', (e) => {
            if ((e.key === 'Delete' || e.key === 'Backspace') && !EMPRESA_CONFIG.facturacion_libre && tr.querySelector('.input-id-producto').value) {
                e.preventDefault();
                inputCodigo.value = ''; inputDesc.value = '';
                tr.querySelector('.input-id-producto').value = '';
                dropdownGlobal.classList.add('d-none');
            }
            if (e.key === 'Enter') {
                e.preventDefault();
                const firstBtn = dropdownGlobal.querySelector('button');
                if (firstBtn && !dropdownGlobal.classList.contains('d-none')) firstBtn.onmousedown(new MouseEvent('mousedown'));
                else inputDesc.focus();
            }
        });
        inputCodigo.addEventListener('blur', () => setTimeout(() => dropdownGlobal.classList.add('d-none'), 200));
        inputDesc.addEventListener('blur', () => setTimeout(() => dropdownGlobal.classList.add('d-none'), 200));
        inputDesc.addEventListener('blur', () => { inputDesc.value = inputDesc.value.replace(/\s+/g, ' ').trim(); });
        inputDesc.addEventListener('blur', () => {
            if (!EMPRESA_CONFIG.facturacion_libre) return;
            const idProd = tr.querySelector('.input-id-producto').value;
            const desc = inputDesc.value.trim();
            if (!idProd && desc.length > 0) cwSeleccionarItemLibre(desc, tr);
        });

        cwCalcTotales();
        return tr;
    };

    window.cwSeleccionarProductoEnFila = function (p, row) {
        row.querySelector('.input-codigo').value = p.codigo || '';
        row.querySelector('.input-descripcion').value = p.nombre || '';
        row.querySelector('.input-precio').value = parseFloat(p.precio_base || 0).toFixed(DEC_PRECIO);
        row.querySelector('.input-id-producto').value = p.id;
        row.dataset.idProducto = p.id;
        row.dataset.tipoProduccion = p.tipo_produccion || '01';
        row.dataset.inventariable = p.inventariable;
        row.dataset.controlaStock = p.controla_stock ? '1' : '0';
        row.querySelector('.input-es-libre').value = '0';
        row.querySelector('.input-precio-base-original').value = p.precio_base || 0;
        row.querySelector('.input-ice-pct').value = p.valor_ice || 0;
        row.querySelector('.input-ice-val').value = 0;
        row.classList.remove('table-warning');

        // Variantes
        const selVar = row.querySelector('.input-variante');
        const contVar = row.querySelector('.container-variante');
        if (p.variantes && p.variantes.length > 0) {
            if (contVar) contVar.classList.remove('d-none');
            selVar.innerHTML = '<option value="">Variantes...</option>';
            p.variantes.forEach(v => {
                const opt = document.createElement('option');
                opt.value = v.precio_adicional || 0;
                opt.textContent = `${v.nombre}: ${v.valor} (+${parseFloat(v.precio_adicional || 0).toFixed(2)})`;
                opt.dataset.nombre = v.nombre; opt.dataset.valor = v.valor;
                selVar.appendChild(opt);
            });
            selVar.onchange = () => {
                const base = parseFloat(row.querySelector('.input-precio-base-original').value) || 0;
                const add = parseFloat(selVar.value) || 0;
                row.querySelector('.input-precio').value = (base + add).toFixed(DEC_PRECIO);
                const opt = selVar.options[selVar.selectedIndex];
                row.querySelector('.input-adicional').value = opt.value ? `${opt.dataset.nombre}: ${opt.dataset.valor}` : '';
                cwSyncPrecioIva(row.querySelector('.input-precio'));
            };
        } else {
            if (contVar) contVar.classList.add('d-none');
            selVar.innerHTML = '<option value="">Variantes...</option>';
        }

        // IVA: por id de tarifa o por porcentaje
        let pctFinal = null;
        if (p.porcentaje_iva !== undefined && p.porcentaje_iva !== null) pctFinal = parseFloat(p.porcentaje_iva);
        else if (p.tarifa_iva) { const tf = TARIFAS_IVA.find(t => t.id == p.tarifa_iva); if (tf) pctFinal = parseFloat(tf.porcentaje_iva); }
        const selIva = row.querySelector('.input-iva');
        if (selIva) {
            let opt = p.tarifa_iva ? Array.from(selIva.options).find(o => o.dataset.id == p.tarifa_iva) : null;
            if (!opt && pctFinal !== null) opt = Array.from(selIva.options).find(o => Math.abs(parseFloat(o.value) - pctFinal) < 0.001);
            if (opt) selIva.selectedIndex = opt.index;
            else if (pctFinal === 0) selIva.value = '0';
        }

        // Medidas
        cwLlenarMedidas(row, p.id_tipo_medida, p.id_medida, null);

        // Lote / Caducidad / NUP según la configuración de facturación (como factura).
        row.dataset.originalLote = ''; row.dataset.originalCad = '';
        const nupIn = row.querySelector('.input-nup'); if (nupIn) nupIn.value = '';
        const esInventariable = (p.inventariable == true || p.inventariable == 'true' || p.inventariable == 1) && (p.tipo_produccion !== '02');
        cwMostrarCamposInventario(row, esInventariable);

        // Lista de precios
        const selPrecios = row.querySelector('.input-lista-precios');
        selPrecios.innerHTML = '';
        const optBase = document.createElement('option');
        optBase.value = p.precio_base; optBase.textContent = `P. Base ($${parseFloat(p.precio_base || 0).toFixed(DEC_PRECIO)})`;
        selPrecios.appendChild(optBase);
        if (p.precios_lista && p.precios_lista.length > 0) {
            p.precios_lista.forEach(pl => {
                const opt = document.createElement('option');
                opt.value = pl.precio; opt.textContent = `${pl.nombre_precio} ($${parseFloat(pl.precio || 0).toFixed(DEC_PRECIO)})`;
                selPrecios.appendChild(opt);
            });
        }
        selPrecios.classList.toggle('d-none', !(p.precios_lista && p.precios_lista.length > 0));
        selPrecios.onchange = () => { row.querySelector('.input-precio').value = parseFloat(selPrecios.value).toFixed(DEC_PRECIO); cwSyncPrecioIva(row.querySelector('.input-precio')); };

        cwSyncPrecioIva(row.querySelector('.input-precio'));
        cwCalcFila(row.querySelector('.input-cantidad'));
        if (esInventariable && EMPRESA_CONFIG.facturacion_inventario) cwCargarLotesFila(row);
        const inCant = row.querySelector('.input-cantidad'); inCant.focus(); inCant.select();
    };

    // Producto sin saldo en la bodega de la orden: muestra productos similares (misma
    // categoría, marca o nombre) que sí tienen saldo, para reemplazarlo con un clic.
    window.cwMostrarSimilares = async function (p, tr) {
        const modalEl = document.getElementById('modalOrdenCW');
        const idBod = document.getElementById('cw_id_bodega').value || 0;
        const idOrd = document.getElementById('cw_id').value || 0;
        let similares = [];
        try {
            const res = await fetch(`${RUTA}/productosSimilaresAjax?id_producto=${p.id}&id_bodega=${idBod}&id_orden=${idOrd}`);
            const data = await res.json();
            similares = data.ok ? (data.data || []) : [];
        } catch (e) { similares = []; }

        const bodega = document.getElementById('cw_id_bodega');
        const nomBodega = bodega && bodega.selectedIndex >= 0 ? bodega.options[bodega.selectedIndex].text : '';
        if (!similares.length) {
            Swal.fire({ icon: 'warning', title: 'Sin saldo', target: modalEl,
                html: `<b>${esc(p.nombre)}</b> no tiene saldo en la bodega <b>${esc(nomBodega)}</b> y no hay productos similares con saldo.` });
            return;
        }
        const filas = similares.map((s, i) => `
            <tr>
                <td class="text-start"><div class="fw-semibold">${esc(s.nombre)}</div><div class="text-muted" style="font-size:.72rem">${esc(s.codigo || '')}${s.coincide ? ' · coincide en ' + esc(s.coincide) : ''}</div></td>
                <td class="text-end"><span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">${fmt(s.stock_actual, 2)}</span></td>
                <td class="text-end">$${fmt(s.precio_base, 2)}</td>
                <td class="text-center"><button type="button" class="btn btn-outline-primary btn-sm py-0 px-2 cw-usar-similar" data-i="${i}">Usar este</button></td>
            </tr>`).join('');
        await Swal.fire({
            icon: 'info', title: 'Sin saldo — productos similares', width: 720, target: modalEl,
            showConfirmButton: false, showCancelButton: true, cancelButtonText: 'Mantener el producto',
            html: `<div class="text-start small mb-2"><b>${esc(p.nombre)}</b> no tiene saldo en la bodega <b>${esc(nomBodega)}</b>. Estos productos similares sí tienen:</div>
                   <div style="max-height:320px;overflow:auto"><table class="table table-sm table-hover align-middle mb-0" style="font-size:.8rem">
                   <thead class="table-light"><tr><th class="text-start">Producto</th><th class="text-end">Saldo</th><th class="text-end">Precio</th><th></th></tr></thead>
                   <tbody>${filas}</tbody></table></div>`,
            didOpen: (popup) => {
                popup.querySelectorAll('.cw-usar-similar').forEach(btn => btn.addEventListener('click', () => {
                    cwSeleccionarProductoEnFila(similares[parseInt(btn.dataset.i, 10)], tr);
                    Swal.close();
                }));
            }
        });
    };

    // Unidades compatibles del producto; idSel = unidad guardada en la línea (si hay).
    function cwLlenarMedidas(row, idTipo, idMedidaBase, idSel) {
        row.dataset.idTipoMedida = idTipo || ''; row.dataset.idMedidaBase = idMedidaBase || '';
        const selMedida = row.querySelector('.input-medida');
        if (!selMedida) return;
        if (!idTipo && !idMedidaBase) { selMedida.classList.add('d-none'); selMedida.innerHTML = '<option value="">...</option>'; return; }
        let compatibles = [];
        if (idTipo) compatibles = UNIDADES.filter(u => u.id_tipo == idTipo);
        if (compatibles.length === 0 && idMedidaBase) { const ub = UNIDADES.find(u => u.id == idMedidaBase); if (ub) compatibles = [ub]; }
        if (!compatibles.length) { selMedida.classList.add('d-none'); return; }
        selMedida.classList.remove('d-none');
        selMedida.innerHTML = '';
        compatibles.forEach(u => {
            const opt = document.createElement('option');
            opt.value = u.id; opt.textContent = u.nombre; opt.dataset.factor = u.factor_base || 1;
            if (u.id == idMedidaBase) row.querySelector('.input-factor-original').value = u.factor_base || 1;
            opt.selected = idSel ? (u.id == idSel) : (u.id == idMedidaBase);
            selMedida.appendChild(opt);
        });
        // Otra unidad → otro factor: el stock de cada lote se recalcula en esa unidad.
        selMedida.onchange = () => { if (row.dataset.inventariable === '1' && EMPRESA_CONFIG.facturacion_inventario) cwCargarLotesFila(row); cwBorradorCambio(); };
    }

    // Muestra/oculta Lote, Caducidad y NUP según la configuración (solo productos inventariables).
    function cwMostrarCamposInventario(row, esInventariable) {
        row.dataset.inventariable = esInventariable ? '1' : '0';
        [['obligatorio_lotes', '.input-lote'], ['obligatorio_caducidad', '.input-caducidad'], ['obligatorio_nup', '.input-nup']].forEach(([cfg, sel]) => {
            if (!EMPRESA_CONFIG[cfg]) return;
            const el = row.querySelector(sel);
            if (!el) return;
            el.classList.toggle('d-none', !esInventariable);
            el.required = !!esInventariable;
            if (!esInventariable) el.value = '';
        });
    }

    function cwFechaCadTexto(iso) {
        if (!iso) return 'Sin Fecha';
        const p = String(iso).substring(0, 10).split('-');
        return (p.length === 3) ? `${p[2]}-${p[1]}-${p[0]}` : iso;
    }

    // Lotes y vencimientos disponibles del producto en la bodega de la orden (igual que
    // factura): lote y caducidad van 1:1; elegir uno acota/elige el otro. Lo guardado en la
    // línea se muestra siempre, aunque ya no tenga stock (orden histórica o migrada).
    window.cwCargarLotesFila = async function (row) {
        const idProd = row.querySelector('.input-id-producto').value;
        const idBod  = document.getElementById('cw_id_bodega').value;
        const selLote = row.querySelector('.input-lote');
        const selCad  = row.querySelector('.input-caducidad');
        if (!idProd || !idBod || (!selLote && !selCad)) return;
        const currentLote = row.dataset.originalLote || '';
        const currentCad  = row.dataset.originalCad || '';
        [selLote, selCad].forEach(s => { if (s) { s.innerHTML = '<option value="">Cargando...</option>'; s.disabled = true; } });
        try {
            const idOrd = document.getElementById('cw_id').value || 0;
            const json = await (await fetch(`${RUTA}/getLotesAjax?id_producto=${idProd}&id_bodega=${idBod}&id_orden=${idOrd}`)).json();
            if (selLote) selLote.innerHTML = '<option value="">Lote...</option>';
            if (selCad) selCad.innerHTML = '<option value="">Vencimiento...</option>';
            const selMedida = row.querySelector('.input-medida');
            const factorLinea = parseFloat(selMedida?.options[selMedida.selectedIndex]?.dataset.factor || 1) || 1;
            const factorProd  = parseFloat(row.querySelector('.input-factor-original')?.value || 1) || 1;
            const factor = factorLinea / factorProd;
            const lotes = (json.ok && json.data) ? json.data : [];
            lotes.forEach(l => {
                const stock = parseFloat(l.stock_lote || 0) / factor;
                if (selLote) { const o = new Option(l.numero_lote || 'Sin Lote', l.numero_lote || ''); o.dataset.stock = stock; selLote.appendChild(o); }
                if (selCad)  { const o = new Option(cwFechaCadTexto(l.fecha_caducidad || ''), l.fecha_caducidad || ''); o.dataset.stock = stock; selCad.appendChild(o); }
            });
            if (!lotes.length) {
                if (selLote) selLote.options[0].textContent = 'Sin Stock';
                if (selCad) selCad.options[0].textContent = 'Sin Stock';
            }
            const cadCompletas = selCad ? Array.from(selCad.options).map(o => o.cloneNode(true)) : [];
            const acotarCadAlLote = (idx, isoGuardado) => {
                if (!selCad) return;
                selCad.innerHTML = '';
                if (idx <= 0) { cadCompletas.forEach(o => selCad.appendChild(o.cloneNode(true))); selCad.selectedIndex = 0; return; }
                const base = cadCompletas[idx];
                if (isoGuardado && (!base || base.value !== isoGuardado)) selCad.appendChild(new Option(cwFechaCadTexto(isoGuardado), isoGuardado));
                else if (base) selCad.appendChild(base.cloneNode(true));
                selCad.selectedIndex = 0;
            };
            if (selLote) selLote.onchange = () => { acotarCadAlLote(selLote.selectedIndex); cwBorradorCambio(); };
            if (selCad) selCad.onchange = () => {
                const idx = selCad.selectedIndex;
                if (idx > 0 && selLote && cadCompletas.length === selCad.options.length) { selLote.selectedIndex = idx; acotarCadAlLote(idx); }
                cwBorradorCambio();
            };
            // Lote / caducidad guardados: se muestran aunque ya no estén en stock.
            if (selLote && currentLote && currentLote !== 'sin_lote') {
                if (!Array.from(selLote.options).some(o => o.value === currentLote)) selLote.appendChild(new Option(currentLote, currentLote));
                selLote.value = currentLote;
                acotarCadAlLote(selLote.selectedIndex, currentCad || '');
                if (selCad && currentCad) {
                    if (!Array.from(selCad.options).some(o => o.value === currentCad)) selCad.appendChild(new Option(cwFechaCadTexto(currentCad), currentCad));
                    selCad.value = currentCad;
                }
            } else if (selCad && currentCad) {
                if (!Array.from(selCad.options).some(o => o.value === currentCad)) selCad.appendChild(new Option(cwFechaCadTexto(currentCad), currentCad));
                selCad.value = currentCad;
            }
        } catch (e) {
            console.error('Error cargando lotes', e);
        } finally {
            const editable = !document.getElementById('cw_btn_guardar').classList.contains('d-none');
            [selLote, selCad].forEach(s => { if (s) s.disabled = !editable; });
        }
    };

    // Al cambiar la bodega de la orden, los lotes de cada línea se vuelven a cargar.
    document.getElementById('cw_id_bodega')?.addEventListener('change', () => {
        if (!EMPRESA_CONFIG.facturacion_inventario) return;
        document.querySelectorAll('#cw_tbodyDetalle .row-detalle').forEach(tr => {
            if (tr.dataset.inventariable === '1' && tr.querySelector('.input-id-producto').value) cwCargarLotesFila(tr);
        });
    });

    // ─── Descuento rápido (igual que Factura de Venta) ────────────────────────
    // Porcentaje o valor, para la línea o para todos los ítems. El descuento se deja con
    // 2 decimales: es como se guarda en la orden y en la factura/recibo, así lo que se ve
    // es exactamente lo que se emite.
    window.cwAbrirDescuento = async function (btn) {
        const trBase = btn.closest('tr');
        const modalEl = document.getElementById('modalOrdenCW');
        const subtotalFila = tr => r2(r6(tr.querySelector('.input-precio').value) * r6(tr.querySelector('.input-cantidad').value || 1));
        const actual = parseFloat(trBase.querySelector('.input-desc').value) || 0;
        const { value: form } = await Swal.fire({
            title: '<span style="font-size:1rem"><i class="bi bi-percent me-1 text-primary"></i>Aplicar descuento</span>',
            width: 340, target: modalEl, heightAuto: false,
            html: `<div class="text-start" style="font-size:.8rem">
                    <label class="text-muted mb-1 d-block">Modo</label>
                    <div class="btn-group w-100 btn-group-sm mb-2" role="group">
                        <input type="radio" class="btn-check" name="cwTipoDesc" id="cwDescP" value="P" ${actual > 0 ? '' : 'checked'}>
                        <label class="btn btn-outline-primary py-1" for="cwDescP">Porcentaje (%)</label>
                        <input type="radio" class="btn-check" name="cwTipoDesc" id="cwDescV" value="V" ${actual > 0 ? 'checked' : ''}>
                        <label class="btn btn-outline-primary py-1" for="cwDescV">Valor ($)</label>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><label class="text-muted mb-1 d-block">Ingreso</label>
                            <input type="number" id="cwDescIn" class="form-control form-control-sm text-center" step="any" min="0" value="${actual > 0 ? actual : 0}"></div>
                        <div class="col-6"><label class="text-muted mb-1 d-block">Calculado ($)</label>
                            <input type="text" id="cwDescCalc" class="form-control form-control-sm text-center bg-light border-0 text-primary" readonly></div>
                    </div>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="cwDescTodos">
                        <label class="form-check-label text-muted" for="cwDescTodos">Aplicar a todos los ítems</label>
                    </div>
                   </div>`,
            showCancelButton: true, confirmButtonText: 'Confirmar', cancelButtonText: 'Cancelar',
            didOpen: (popup) => {
                const calc = () => {
                    const tipo = popup.querySelector('input[name="cwTipoDesc"]:checked').value;
                    const v = parseFloat(popup.querySelector('#cwDescIn').value) || 0;
                    const res = tipo === 'P' ? r2(subtotalFila(trBase) * v / 100) : r2(v);
                    popup.querySelector('#cwDescCalc').value = res.toFixed(2);
                };
                popup.querySelectorAll('input[name="cwTipoDesc"], #cwDescIn').forEach(el => el.addEventListener('input', calc));
                popup.querySelectorAll('input[name="cwTipoDesc"]').forEach(el => el.addEventListener('change', calc));
                calc();
                setTimeout(() => { const i = popup.querySelector('#cwDescIn'); i.focus(); i.select(); }, 150);
            },
            preConfirm: () => {
                const popup = Swal.getPopup();
                const tipo = popup.querySelector('input[name="cwTipoDesc"]:checked').value;
                const v = parseFloat(popup.querySelector('#cwDescIn').value) || 0;
                const todos = popup.querySelector('#cwDescTodos').checked;
                if (v < 0) { Swal.showValidationMessage('El valor no puede ser negativo.'); return false; }
                if (tipo === 'P' && v > 100) { Swal.showValidationMessage('El porcentaje no puede pasar de 100.'); return false; }
                const filas = todos ? Array.from(document.querySelectorAll('#cw_tbodyDetalle tr.row-detalle')) : [trBase];
                if (tipo === 'V') {
                    const excede = filas.find(tr => (tr.querySelector('.input-descripcion').value || '').trim() && v > subtotalFila(tr) + 0.0001);
                    if (excede) { Swal.showValidationMessage('El descuento no puede ser mayor que el subtotal de la línea "' + excede.querySelector('.input-descripcion').value.trim() + '".'); return false; }
                }
                return { tipo, v, filas };
            }
        });
        if (!form) return;
        form.filas.forEach(tr => {
            if (!(tr.querySelector('.input-descripcion').value || '').trim()) return; // filas vacías, nada que descontar
            const desc = form.tipo === 'P' ? r2(subtotalFila(tr) * form.v / 100) : r2(form.v);
            const inp = tr.querySelector('.input-desc');
            inp.value = desc.toFixed(2);
            inp.dispatchEvent(new Event('input', { bubbles: true }));
        });
    };

    window.cwAgregarOpcionServicioLibre = function (texto, tr, dropdown) {
        const sep = document.createElement('div');
        sep.className = 'list-group-item py-1 text-muted x-small border-top bg-light';
        sep.textContent = 'ó facturar como servicio libre:';
        dropdown.appendChild(sep);
        const bLibre = document.createElement('button');
        bLibre.type = 'button';
        bLibre.className = 'list-group-item list-group-item-action py-2 border-0 bg-warning bg-opacity-10';
        bLibre.innerHTML = `<div class="d-flex align-items-center gap-2 text-start"><i class="bi bi-lightning-charge-fill text-warning fs-6"></i>
            <div><div class="fw-bold small text-dark">"${esc(texto)}"</div><div class="x-small text-muted">Registrar como servicio libre (se creará al guardar)</div></div></div>`;
        bLibre.onmousedown = (evt) => { evt.preventDefault(); cwSeleccionarItemLibre(texto, tr); dropdown.classList.add('d-none'); };
        dropdown.appendChild(bLibre);
    };

    window.cwSeleccionarItemLibre = function (descripcion, row) {
        row.querySelector('.input-descripcion').value = descripcion;
        row.querySelector('.input-id-producto').value = '';
        row.querySelector('.input-codigo').value = '';
        row.querySelector('.input-es-libre').value = '1';
        row.dataset.tipoProduccion = '02';
        row.dataset.inventariable = 'false';
        cwMostrarCamposInventario(row, false); // ítem libre: sin lote / caducidad / NUP
        const selIva = row.querySelector('.input-iva');
        if (selIva && selIva.options.length > 0) selIva.selectedIndex = 0;
        const selMedida = row.querySelector('.input-medida');
        if (selMedida) selMedida.classList.add('d-none');
        row.classList.add('table-warning');
        row.title = 'Servicio libre - se creará en el catálogo al guardar';
        const inputPrecio = row.querySelector('.input-precio');
        if (inputPrecio) setTimeout(() => { inputPrecio.focus(); inputPrecio.select(); }, 50);
        cwCalcFila(row.querySelector('.input-cantidad'));
    };

    window.cwSyncPrecioIva = function (el) {
        const tr = el.closest('tr');
        const pSin = parseFloat(tr.querySelector('.input-precio').value) || 0;
        const ivaPct = parseFloat(tr.querySelector('.input-iva').value) || 0;
        tr.querySelector('.input-precio-iva').value = (pSin * (1 + ivaPct / 100)).toFixed(DEC_PRECIO);
        cwCalcFila(tr.querySelector('.input-cantidad'));
    };
    window.cwCalcSinImp = function (el) {
        const tr = el.closest('tr');
        const pSin = parseFloat(el.value) || 0;
        const ivaPct = parseFloat(tr.querySelector('.input-iva').value) || 0;
        tr.querySelector('.input-precio-iva').value = (pSin * (1 + ivaPct / 100)).toFixed(DEC_PRECIO);
        cwCalcFila(el);
    };
    window.cwCalcConImp = function (el) {
        const tr = el.closest('tr');
        const pCon = parseFloat(el.value) || 0;
        const ivaPct = parseFloat(tr.querySelector('.input-iva').value) || 0;
        tr.querySelector('.input-precio').value = (pCon / (1 + ivaPct / 100)).toFixed(DEC_PRECIO);
        cwCalcFila(el);
    };
    window.cwCalcFila = function (el) {
        const tr = el.closest('tr');
        const cant = parseFloat(tr.querySelector('.input-cantidad').value) || 0;
        const prec = parseFloat(tr.querySelector('.input-precio').value) || 0;
        const desc = parseFloat(tr.querySelector('.input-desc').value) || 0;
        tr.querySelector('.subtotal-line').textContent = cwBaseLinea(cant, prec, desc).toFixed(2);
        cwCalcTotales();
    };
    // Base de la línea EXACTAMENTE como el servidor (OrdenCarWashService::calcularLineas):
    // cantidad y precio a 6 decimales, descuento a 2, y un solo redondeo al final.
    function cwBaseLinea(cant, prec, desc) {
        return Math.max(0, r2(r6(prec) * r6(cant) - r2(desc)));
    }
    // Columnas dinámicas (como factura): "Precios" y "Medida" solo se muestran si algún ítem
    // las usa; Medida además respeta la configuración de la empresa.
    function cwActualizarColumnasDinamicas() {
        const hayPrecios = !!document.querySelector('#cw_tbodyDetalle .input-lista-precios:not(.d-none)');
        document.querySelectorAll('#modalOrdenCW .col-lista-precios').forEach(el => el.classList.toggle('d-none', !hayPrecios));
        const hayMedida = !!EMPRESA_CONFIG.mostrar_unidad_medida && !!document.querySelector('#cw_tbodyDetalle .input-medida:not(.d-none)');
        document.querySelectorAll('#modalOrdenCW .col-medida').forEach(el => el.classList.toggle('d-none', !hayMedida));
    }
    window.cwCalcTotales = function () {
        cwBorradorCambio();
        cwActualizarColumnasDinamicas();
        const modoIva = cwModoIva();
        let sumaBases = 0, descuentoTotal = 0;
        const grupos = {};
        document.querySelectorAll('#cw_tbodyDetalle .row-detalle').forEach(tr => {
            const cant = parseFloat(tr.querySelector('.input-cantidad').value) || 0;
            const prec = parseFloat(tr.querySelector('.input-precio').value) || 0;
            const desc = parseFloat(tr.querySelector('.input-desc').value) || 0;
            const selIva = tr.querySelector('.input-iva');
            const optIva = selIva.options[selIva.selectedIndex];
            const ivaPct = parseFloat(optIva ? optIva.value : 0) || 0;
            const key = optIva ? (optIva.dataset.id || ivaPct) : ivaPct;
            const label = optIva ? optIva.text : '0%';
            if (cant <= 0) return; // igual que el servidor: las líneas sin cantidad no suman
            const neto = cwBaseLinea(cant, prec, desc);
            sumaBases += neto;
            descuentoTotal += r2(desc);
            if (!grupos[key]) grupos[key] = { pct: ivaPct, label: label, base: 0, iva: 0 };
            grupos[key].base += neto;
            if (modoIva === 'linea_linea') grupos[key].iva += r2(neto * ivaPct / 100);
        });
        Object.values(grupos).forEach(g => {
            g.base = r2(g.base);
            g.iva = modoIva === 'subtotal' ? r2(g.base * g.pct / 100) : r2(g.iva);
        });
        sumaBases = r2(sumaBases); descuentoTotal = r2(descuentoTotal);
        let ivaTotal = 0; Object.values(grupos).forEach(g => { ivaTotal += g.iva; });
        ivaTotal = r2(ivaTotal);
        // Subtotal (antes de descuento) = bases + descuentos, así Subtotal − Descuento cuadra
        // al centavo con la suma de las líneas; Total = bases + IVA, igual que la factura.
        const subtotalGeneral = r2(sumaBases + descuentoTotal);
        const total = r2(sumaBases + ivaTotal);

        const set = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };
        set('cw-lbl-subtotal', subtotalGeneral.toFixed(2));
        const contSub = document.getElementById('cw-lbl-subtotales-iva');
        if (contSub) { contSub.innerHTML = ''; Object.values(grupos).forEach(g => { const d = document.createElement('div'); d.className = 'd-flex justify-content-between align-items-center mb-1 text-muted'; d.innerHTML = `<span>Subtotal ${g.label}</span><span>${g.base.toFixed(2)}</span>`; contSub.appendChild(d); }); }
        set('cw-lbl-descuento', descuentoTotal.toFixed(2));
        const contIva = document.getElementById('cw-lbl-ivas-grupo');
        if (contIva) { contIva.innerHTML = ''; Object.values(grupos).forEach(g => { if (g.pct > 0 && g.iva > 0) { const d = document.createElement('div'); d.className = 'd-flex justify-content-between align-items-center mb-1'; d.innerHTML = `<span class="text-muted">(+) IVA ${g.pct}%</span><span>${g.iva.toFixed(2)}</span>`; contIva.appendChild(d); } }); }
        set('cw-lbl-total', total.toFixed(2));
        const cnt = document.getElementById('cw-count-items'); if (cnt) cnt.textContent = document.querySelectorAll('#cw_tbodyDetalle .row-detalle').length;
    };
    // Alias para llamadas previas.
    window.cwRecalcularGrilla = window.cwCalcTotales;

    // Carga un detalle guardado en una fila de la grilla.
    window.cwCargarLineaGuardada = function (d) {
        const tr = cwAgregarLinea();
        tr.dataset.idProducto = d.id_producto || '';
        tr.dataset.tipoProduccion = (d.tipo_linea === 'servicio') ? '02' : '01';
        tr.dataset.controlaStock = (d.id_producto && d.tipo_linea === 'producto') ? '1' : '0';
        tr.querySelector('.input-descripcion').value = d.descripcion || '';
        tr.querySelector('.input-codigo').value = d.producto_codigo || '';
        tr.querySelector('.input-id-producto').value = d.id_producto || '';
        tr.querySelector('.input-es-libre').value = (d.es_libre === true || d.es_libre === 't' || d.es_libre === 'true' || d.es_libre === 1) ? '1' : '0';
        tr.querySelector('.input-cantidad').value = d.cantidad != null ? parseFloat(d.cantidad) : 1;
        tr.querySelector('.input-precio').value = parseFloat(d.precio_unitario || 0).toFixed(DEC_PRECIO);
        tr.querySelector('.input-desc').value = parseFloat(d.descuento || 0).toFixed(2);
        const selIva = tr.querySelector('.input-iva');
        if (selIva) {
            let opt = d.id_tarifa_iva ? Array.from(selIva.options).find(o => o.dataset.id == d.id_tarifa_iva) : null;
            if (!opt) opt = Array.from(selIva.options).find(o => Math.abs(parseFloat(o.value) - parseFloat(d.porcentaje_iva || 0)) < 0.001);
            if (!opt && d.porcentaje_iva != null && d.porcentaje_iva !== '' && !isNaN(parseFloat(d.porcentaje_iva))) {
                // Tarifa histórica inactiva: se agrega la opción solo para este documento.
                const pctH = parseFloat(d.porcentaje_iva);
                opt = document.createElement('option');
                opt.value = pctH;
                opt.textContent = pctH + '%';
                selIva.appendChild(opt);
            }
            if (opt) selIva.selectedIndex = opt.index;
        }
        // Unidad de medida, inventariable y Lote / Caducidad / NUP guardados (como factura).
        if (d.producto_tipo_produccion) tr.dataset.tipoProduccion = d.producto_tipo_produccion;
        if (d.id_producto) cwLlenarMedidas(tr, d.producto_id_tipo_medida, d.producto_id_medida, d.id_unidad_medida);
        const esInv = !!d.id_producto && (d.producto_inventariable === true || d.producto_inventariable === 't' || d.producto_inventariable === 'true' || d.producto_inventariable == 1)
                      && (d.producto_tipo_produccion || tr.dataset.tipoProduccion) !== '02';
        cwMostrarCamposInventario(tr, esInv);
        tr.dataset.originalLote = d.lote || '';
        tr.dataset.originalCad  = d.fecha_caducidad ? String(d.fecha_caducidad).slice(0, 10) : '';
        const nupIn = tr.querySelector('.input-nup'); if (nupIn) nupIn.value = d.nup || '';
        cwSyncPrecioIva(tr.querySelector('.input-precio'));
        cwCalcFila(tr.querySelector('.input-cantidad'));
        if (esInv && EMPRESA_CONFIG.facturacion_inventario) cwCargarLotesFila(tr);
    };


    // ─── Info. Adicional (igual que factura de venta) ─────────────────────────
    window.cwAgregarInfo = function (ia) {
        ia = ia || {};
        const tbody = document.getElementById('cw_info_body');
        const tr = document.createElement('tr');
        tr.className = 'row-info-adicional';
        tr.innerHTML = `
            <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent input-info-concepto" style="padding:0 4px;height:20px;font-size:0.78rem;" placeholder="Concepto..." value="${esc(ia.nombre || '')}"></td>
            <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent input-info-detalle" style="padding:0 4px;height:20px;font-size:0.78rem;" placeholder="Detalle..." value="${esc(ia.valor || '')}"></td>
            <td class="p-0 text-center pe-1"><button type="button" class="btn btn-link btn-sm p-0 m-0 text-danger shadow-none" onclick="this.closest('tr').remove(); cwBorradorCambio();"><i class="bi bi-x-circle-fill"></i></button></td>`;
        const primeraFija = tbody.querySelector('tr[data-tipo]');
        if (primeraFija) tbody.insertBefore(tr, primeraFija);
        else tbody.appendChild(tr);
        if (!ia.nombre) tr.querySelector('.input-info-concepto').focus();
    };

    // ─── Totales (usa la grilla portada de factura) ───────────────────────────
    window.cwRecalcular = function () { cwCalcTotales(); };

    // ─── Guardar ──────────────────────────────────────────────────────────────
    window.cwGuardar = async function () {
        const idVeh = document.getElementById('cw_id_vehiculo').value;
        const idCli = document.getElementById('cw_id_cliente').value;
        // 1º Vehículo (obligatorio)
        if (!idVeh) { await Swal.fire('Atención', 'Seleccione un vehículo.', 'warning'); cwFocus('cw_vehiculo_busqueda'); return; }
        // El cliente es opcional al registrar la orden (obligatorio al facturar).
        // 2º Serie / secuencial (obligatorio)
        if (!document.getElementById('cw_id_punto_emision').value || !document.getElementById('cw_secuencial').value) {
            await Swal.fire('Atención', 'Seleccione la serie (punto de emisión).', 'warning'); cwFocus('cw_select_serie'); return;
        }

        const detalles = [];
        document.querySelectorAll('#cw_tbodyDetalle .row-detalle').forEach(tr => {
            const desc = (tr.querySelector('.input-descripcion')?.value || '').trim();
            const cant = num(tr.querySelector('.input-cantidad')?.value);
            if (!desc || cant <= 0) return;
            const selIva = tr.querySelector('.input-iva');
            const optIva = selIva ? selIva.options[selIva.selectedIndex] : null;
            const idProd = tr.querySelector('.input-id-producto')?.value || '';
            const esLibre = (tr.querySelector('.input-es-libre')?.value === '1') || !idProd;
            const tipoProd = tr.dataset.tipoProduccion || '';
            detalles.push({
                id_producto: idProd || null,
                tipo_linea: (tipoProd === '02' || esLibre) ? 'servicio' : (idProd ? 'producto' : 'servicio'),
                es_libre: esLibre,
                descripcion: desc,
                id_bodega: null, // la bodega de la cabecera aplica a toda la orden
                id_tarifa_iva: optIva ? (optIva.dataset.id || null) : null,
                cantidad: cant,
                precio_unitario: num(tr.querySelector('.input-precio')?.value),
                descuento: num(tr.querySelector('.input-desc')?.value),
                porcentaje_iva: optIva ? (parseFloat(optIva.value) || 0) : 0,
                lote: tr.querySelector('.input-lote')?.value.trim() || '',
                caducidad: tr.querySelector('.input-caducidad')?.value.trim() || '',
                nup: tr.querySelector('.input-nup')?.value.trim() || '',
                id_unidad_medida: (() => { const m = tr.querySelector('.input-medida'); return (m && !m.classList.contains('d-none') && m.value) ? m.value : null; })(),
            });
        });
        // 3º Al menos un servicio/producto
        if (!detalles.length) {
            await Swal.fire('Atención', 'Agregue al menos un servicio o producto.', 'warning');
            let fila = document.querySelector('#cw_tbodyDetalle .row-detalle .input-descripcion');
            if (!fila) { cwAgregarLinea(); fila = document.querySelector('#cw_tbodyDetalle .row-detalle .input-descripcion'); }
            if (fila) fila.focus();
            return;
        }

        const info_adicional = [];
        document.querySelectorAll('#cw_info_body .row-info-adicional').forEach(row => {
            const nom = (row.querySelector('.input-info-concepto')?.value || '').trim();
            const val = (row.querySelector('.input-info-detalle')?.value || '').trim();
            if (nom && val) info_adicional.push({ nombre: nom, valor: val });
        });

        const vehInp = document.getElementById('cw_vehiculo_busqueda');
        const serie = document.getElementById('cw_serie').value;
        const payload = {
            id: document.getElementById('cw_id').value || null,
            id_establecimiento: document.getElementById('cw_id_establecimiento').value || null,
            id_punto_emision: document.getElementById('cw_id_punto_emision').value || null,
            establecimiento: (serie.split('-')[0] || ''),
            punto_emision: (serie.split('-')[1] || ''),
            secuencial: document.getElementById('cw_secuencial').dataset.sec || document.getElementById('cw_secuencial').value || '',
            id_vehiculo: parseInt(idVeh, 10),
            id_cliente: idCli ? parseInt(idCli, 10) : null,
            placa: vehInp.dataset.placa || (vehInp.value.split(' — ')[0] || ''),
            marca: vehInp.dataset.marca || '',
            modelo: vehInp.dataset.modelo || '',
            kilometraje: document.getElementById('cw_kilometraje').value,
            nivel_combustible: document.getElementById('cw_nivel_combustible').value,
            id_bodega: document.getElementById('cw_id_bodega').value || null,
            fecha_ingreso: (document.getElementById('cw_fecha_ingreso').value || '').replace('T', ' '),
            proxima_cita: document.getElementById('cw_proxima_cita').value,
            detalles, novedades: [], info_adicional,
            condiciones_html: cwCondicionesHtml(),
        };

        const keyBorrador = cwBorrKey();
        const btn = document.getElementById('cw_btn_guardar');
        const orig = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';
        try {
            const res = await fetch(`${RUTA}/store`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'Error al guardar');
            CW_BORR_ON = false; clearTimeout(borrTimer); borrTimer = null;
            cwBorrBorrar(keyBorrador);
            getModal().hide();
            await Swal.fire({ icon: 'success', title: 'Listo', text: data.msg, timer: 1400, showConfirmButton: false });
            if (typeof cwRecargarTablero === 'function') cwRecargarTablero();
            if (typeof cargarGrid === 'function') cargarGrid();
        } catch (e) {
            Swal.fire('Error', e.message, 'error');
        } finally {
            btn.disabled = false; btn.innerHTML = orig;
        }
    };

    // ─── Eliminar ─────────────────────────────────────────────────────────────
    window.cwEliminar = async function () {
        const id = document.getElementById('cw_id').value;
        if (!id) return;
        const c = await Swal.fire({ title: '¿Eliminar orden?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545' });
        if (!c.isConfirmed) return;
        try {
            const fd = new FormData(); fd.append('id', id);
            const res = await fetch(`${RUTA}/eliminar`, { method: 'POST', body: fd });
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'Error');
            CW_BORR_ON = false; clearTimeout(borrTimer); borrTimer = null;
            cwBorrBorrar(CW_BORR_BASE + ':' + id);
            getModal().hide();
            await Swal.fire({ icon: 'success', title: 'Eliminada', timer: 1200, showConfirmButton: false });
            if (typeof cwRecargarTablero === 'function') cwRecargarTablero();
            if (typeof cargarGrid === 'function') cargarGrid();
        } catch (e) { Swal.fire('Error', e.message, 'error'); }
    };

    // ─── Documento de venta (Factura / Recibo) ────────────────────────────────
    const CW_BASE = RUTA.replace(/\/modulos\/car-wash\/?$/, '');

    window.cwGenerarDocumento = async function (tipo) {
        const idOrden = document.getElementById('cw_id').value;
        if (!idOrden) { Swal.fire('Atención', 'Primero guarde la orden.', 'warning'); return; }
        if (CW_CUR.id_documento && CW_CUR.documento_vigente !== false) { Swal.fire('Atención', 'Esta orden ya generó un documento vigente. Para volver a facturarla, anule primero ese documento.', 'warning'); return; }

        // La forma de pago SRI la resuelve el servidor (cliente → configuración de facturación → 01).
        const etq = tipo === 'FACTURA' ? 'Factura electrónica' : 'Recibo de venta';
        const { isConfirmed: form } = await Swal.fire({
            title: 'Generar ' + etq,
            target: document.getElementById('modalOrdenCW'),
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-receipt me-1"></i> Generar',
            cancelButtonText: 'Cancelar',
        });
        if (!form) return;

        Swal.fire({ title: 'Generando ' + etq + '...', allowOutsideClick: false, target: document.getElementById('modalOrdenCW'), didOpen: () => Swal.showLoading() });
        try {
            const fd = new FormData();
            fd.append('id_orden', idOrden);
            fd.append('tipo', tipo);
            fd.append('id_bodega', 0);
            const res = await fetch(`${RUTA}/generarDocumentoAjax`, { method: 'POST', body: fd });
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'No se pudo generar el documento.');

            // Recarga la orden: queda bloqueada y la pestaña Facturación muestra el documento nuevo.
            await cwAbrirVerId(idOrden, false);
            CW_CUR.id_documento = data.id_documento;
            CW_CUR.tipo_documento = data.tipo_documento;
            if (typeof cwRecargarTablero === 'function') cwRecargarTablero();
            if (typeof cargarGrid === 'function') cargarGrid();

            // Sin botón "Ver PDF": el PDF del documento está en la pestaña Facturación.
            Swal.fire({ icon: 'success', title: '¡Listo!', text: data.msg, target: document.getElementById('modalOrdenCW'), timer: 2200, showConfirmButton: false });
        } catch (e) {
            Swal.fire('Error', e.message, 'error');
        }
    };

    // PDF de la orden (orden de servicio car-wash): pregunta Imprimir / Descargar / Ver.
    window.cwPdf = function () {
        const id = document.getElementById('cw_id').value || CW_CUR.id;
        if (!id) { Swal.fire('Atención', 'Primero guarde la orden.', 'warning'); return; }
        CMG_pdfDocumento(`${RUTA}/exportarPdfAjax?id=${id}`);
    };
    // Acta de ingreso del vehículo (se genera desde lo GUARDADO en la orden).
    window.cwPdfIngreso = function () {
        const id = document.getElementById('cw_id').value || CW_CUR.id;
        if (!id) { Swal.fire({ icon: 'warning', title: 'Atención', text: 'Guarde la orden antes de generar el acta de ingreso.', target: document.getElementById('modalOrdenCW') }); return; }
        CMG_pdfDocumento(`${RUTA}/exportarIngresoPdfAjax?id=${id}`);
    };
    // PDF del documento generado (factura/recibo).
    window.cwPdfDocumento = function () {
        if (!CW_CUR.id_documento) return;
        const ruta = CW_CUR.tipo_documento === 'FACTURA' ? 'factura-venta' : 'recibo-venta';
        CMG_pdfDocumento(`${CW_BASE}/modulos/${ruta}/exportarPdfAjax?id=${CW_CUR.id_documento}`);
    };

    // Enviar el PDF de la orden por correo (mismo patrón que consignaciones).
    window.cwCorreo = async function () {
        const id = document.getElementById('cw_id').value || CW_CUR.id;
        if (!id) { Swal.fire('Atención', 'Primero guarde la orden.', 'warning'); return; }
        const correoActual = (document.getElementById('cw_info_cliente').textContent.match(/[\w.+-]+@[\w-]+\.[\w.-]+/) || [''])[0];
        const { value: form, isConfirmed } = await Swal.fire({
            title: 'Enviar por correo',
            html: `<div class="text-start">
                    <label class="form-label small fw-semibold mb-1">Documento</label>
                    <select id="cwMailTipo" class="form-select form-select-sm mb-2">
                        <option value="orden">Orden de servicio (PDF)</option>
                        <option value="ingreso">Acta de ingreso del vehículo (condiciones y servicios)</option>
                    </select>
                    <label class="form-label small fw-semibold mb-1">Correo(s) destino, separados por coma</label>
                    <input id="cwMailPara" class="form-control form-control-sm" placeholder="cliente@correo.com" value="${esc(correoActual)}">
                   </div>`,
            target: document.getElementById('modalOrdenCW'),
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-envelope me-1"></i> Enviar',
            cancelButtonText: 'Cancelar',
            preConfirm: () => ({ tipo: document.getElementById('cwMailTipo').value, correos: document.getElementById('cwMailPara').value.trim() })
        });
        if (!isConfirmed || !form) return;
        const correos = form.correos;
        Swal.fire({ title: 'Enviando correo...', allowOutsideClick: false, target: document.getElementById('modalOrdenCW'), didOpen: () => Swal.showLoading() });
        try {
            const fd = new FormData(); fd.append('id', id); fd.append('correos', correos || ''); fd.append('tipo', form.tipo);
            const res = await fetch(`${RUTA}/enviarCorreoAjax`, { method: 'POST', body: fd });
            const data = await res.json();
            if (data.ok) Swal.fire('Enviado', data.mensaje || 'Correo enviado correctamente.', 'success');
            else Swal.fire('Error', data.mensaje || 'No se pudo enviar el correo.', 'error');
        } catch (e) { Swal.fire('Error', 'No se pudo enviar el correo.', 'error'); }
    };

    window.cwWhatsapp = async function () {
        if (!CW_CUR.id_documento || CW_CUR.tipo_documento !== 'FACTURA') return;
        try {
            const res = await fetch(`${CW_BASE}/modulos/factura-venta/getPlantillasWhatsappAjax?id_factura=${CW_CUR.id_documento}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'No se pudieron cargar las plantillas.');
            if (data.configurado === false) { Swal.fire('WhatsApp', 'Aún no tiene configurada la API de WhatsApp. Actívela en su módulo de WhatsApp.', 'info'); return; }
            const opts = (data.plantillas || []).map(p => `<option value="${p.id}">${esc(p.nombre)} (${esc(p.idioma)})</option>`).join('');
            const { value: form, isConfirmed } = await Swal.fire({
                title: 'Enviar por WhatsApp', target: document.getElementById('modalOrdenCW'),
                html: `<div class="text-start">
                        <label class="form-label small fw-semibold mb-1">Plantilla</label>
                        <select id="cwWaTpl" class="form-select form-select-sm mb-2">${opts || '<option value="">Sin plantillas</option>'}</select>
                        <label class="form-label small fw-semibold mb-1">Teléfono</label>
                        <input id="cwWaTel" class="form-control form-control-sm" value="${esc(data.telefono_cliente || '593')}">
                       </div>`,
                showCancelButton: true, confirmButtonText: 'Enviar',
                preConfirm: () => ({ id_plantilla: document.getElementById('cwWaTpl').value, telefono: document.getElementById('cwWaTel').value })
            });
            if (!isConfirmed || !form) return;
            Swal.fire({ title: 'Enviando...', allowOutsideClick: false, target: document.getElementById('modalOrdenCW'), didOpen: () => Swal.showLoading() });
            const fd = new FormData();
            fd.append('id_factura', CW_CUR.id_documento);
            fd.append('id_plantilla', form.id_plantilla);
            fd.append('telefono', form.telefono);
            const r2 = await fetch(`${CW_BASE}/modulos/factura-venta/enviarWhatsappAjax`, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const d2 = await r2.json();
            if (!d2.ok) throw new Error(d2.error || 'No se pudo enviar.');
            Swal.fire({ icon: 'success', title: '¡Enviado!', text: d2.mensaje || 'Mensaje enviado.', timer: 2200, showConfirmButton: false });
        } catch (e) { Swal.fire('Error', e.message, 'error'); }
    };

    // ─── Pestaña Facturación: documentos emitidos desde la orden ─────────────
    const CW_ESTADOS_DOC = {
        autorizado: ['success', 'Autorizado'], autorizada: ['success', 'Autorizado'],
        borrador: ['warning', 'Borrador'], pendiente: ['warning', 'Pendiente'], emitido: ['success', 'Emitido'],
        anulado: ['danger', 'Anulado'], anulada: ['danger', 'Anulado'], eliminado: ['secondary', 'Eliminado'],
    };
    function cwFechaHora(f) {
        if (!f) return '';
        const m = String(f).match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/);
        if (!m) return esc(f);
        return `${m[3]}-${m[2]}-${m[1]} ${m[4] || '00'}:${m[5] || '00'}:${m[6] || '00'}`;
    }
    window.cwRenderDocumentos = function (docs) {
        const tbody = document.getElementById('cw_docs_body');
        const badge = document.getElementById('cw-badge-docs');
        if (badge) { badge.textContent = docs.length; badge.classList.toggle('d-none', !docs.length); }
        if (!docs.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">La orden aún no se ha facturado.</td></tr>';
            return;
        }
        tbody.innerHTML = docs.map(d => {
            const est = CW_ESTADOS_DOC[String(d.estado_documento || '').toLowerCase()]
                || (d.id_documento ? ['secondary', d.estado_documento || '—'] : ['secondary', 'Sistema anterior']);
            const tipo = d.tipo_documento === 'FACTURA' ? 'Factura' : 'Recibo';
            const pdf = d.id_documento && d.estado_documento !== 'eliminado'
                ? `<button type="button" class="btn btn-link btn-sm p-0 text-danger" title="PDF del documento" onclick="cwPdfDocumentoId('${esc(d.tipo_documento)}', ${parseInt(d.id_documento, 10)})"><i class="bi bi-file-earmark-pdf"></i></button>`
                : '';
            return `<tr>
                <td class="ps-2">${cwFechaHora(d.fecha_emision)}</td>
                <td><i class="bi ${d.tipo_documento === 'FACTURA' ? 'bi-receipt' : 'bi-receipt-cutoff'} me-1 text-muted"></i>${tipo}</td>
                <td class="fw-semibold">${esc(d.numero_documento || '')}</td>
                <td class="text-end">${fmt(d.total, 2)}</td>
                <td class="text-center"><span class="badge bg-${est[0]} bg-opacity-10 text-${est[0]} border border-${est[0]} border-opacity-25">${esc(est[1])}</span></td>
                <td>${d.origen === 'migracion' ? '<span class="text-muted">Sistema anterior</span>' : 'Car-Wash'}</td>
                <td class="text-muted">${esc(d.usuario || '')}</td>
                <td class="text-center pe-2">${pdf}</td>
            </tr>`;
        }).join('');
    };
    window.cwPdfDocumentoId = function (tipo, id) {
        if (!id) return;
        const ruta = tipo === 'FACTURA' ? 'factura-venta' : 'recibo-venta';
        CMG_pdfDocumento(`${CW_BASE}/modulos/${ruta}/exportarPdfAjax?id=${id}`);
    };

    // ─── Pestaña Historial: órdenes por vehículo o por cliente ────────────────
    let histTimer = null;
    window.cwHistorialModoChange = function () {
        const modo = document.getElementById('cw_hist_modo').value;
        document.getElementById('cw_hist_lbl').textContent = modo === 'cliente' ? 'Nombre o identificación del cliente' : 'Placa, marca o propietario';
        document.getElementById('cw_hist_q').value = '';
        cwHistorialDeEstaOrden();
    };
    window.cwHistorialBuscarTexto = function () {
        clearTimeout(histTimer);
        const q = document.getElementById('cw_hist_q').value.trim();
        if (q.length < 2) return;
        histTimer = setTimeout(() => cwCargarHistorial({ q }), 350);
    };
    // Carga el historial del vehículo (o cliente) de la orden abierta.
    window.cwHistorialDeEstaOrden = function () {
        const modo = document.getElementById('cw_hist_modo').value;
        const idVeh = document.getElementById('cw_id_vehiculo').value || CW_CUR.id_vehiculo;
        const idCli = document.getElementById('cw_id_cliente').value || CW_CUR.id_cliente;
        if (modo === 'cliente' && idCli) { cwCargarHistorial({ id_cliente: idCli }); return; }
        if (modo === 'vehiculo' && idVeh) { cwCargarHistorial({ id_vehiculo: idVeh }); return; }
        document.getElementById('cw_hist_resumen').innerHTML = '';
        document.getElementById('cw_hist_body').innerHTML = `<tr><td colspan="8" class="text-center text-muted py-4">La orden no tiene ${modo === 'cliente' ? 'cliente' : 'vehículo'} seleccionado. Escriba para buscar.</td></tr>`;
    };
    async function cwCargarHistorial(filtro) {
        const modo = document.getElementById('cw_hist_modo').value;
        const tbody = document.getElementById('cw_hist_body');
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-1"></span>Buscando...</td></tr>';
        try {
            const params = new URLSearchParams({ modo, ...filtro });
            const res = await fetch(`${RUTA}/historialAjax?${params.toString()}`);
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'No se pudo cargar el historial.');
            const rows = data.data || [];
            const idActual = parseInt(document.getElementById('cw_id').value || 0, 10);
            if (!rows.length) {
                document.getElementById('cw_hist_resumen').innerHTML = '';
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Sin órdenes registradas.</td></tr>';
                return;
            }
            const total = rows.reduce((a, r) => a + (r.estado === 'anulado' ? 0 : num(r.total)), 0);
            const placas = new Set(rows.map(r => (r.placa || '').toUpperCase()).filter(Boolean));
            const clientes = new Set(rows.map(r => r.cliente_nombre).filter(Boolean));
            document.getElementById('cw_hist_resumen').innerHTML = `
                <span><i class="bi bi-list-check text-primary me-1"></i><b>${rows.length}</b> ${rows.length === 1 ? 'orden' : 'órdenes'}${rows.length >= 200 ? ' (últimas 200)' : ''}</span>
                <span><i class="bi bi-cash-stack text-success me-1"></i>Total: <b>${fmt(total, 2)}</b></span>
                <span><i class="bi bi-calendar-check text-info me-1"></i>Última visita: <b>${esc(rows[0].fecha || '')}</b></span>
                ${modo === 'cliente' ? `<span><i class="bi bi-car-front text-secondary me-1"></i>${placas.size} vehículo(s)</span>` : `<span><i class="bi bi-person text-secondary me-1"></i>${clientes.size} cliente(s)</span>`}`;
            const nombres = { borrador: ['warning', 'Borrador'], facturado: ['success', 'Facturado'], anulado: ['danger', 'Anulado'] };
            tbody.innerHTML = rows.map(r => {
                const e = nombres[r.estado] || ['secondary', r.estado || ''];
                const doc = r.numero_documento ? `${r.tipo_documento === 'FACTURA' ? 'Fact.' : 'Rec.'} ${esc(r.numero_documento)}` : '<span class="text-muted">—</span>';
                const actual = parseInt(r.id, 10) === idActual;
                return `<tr role="button" class="${actual ? 'table-primary' : ''}" title="${actual ? 'Orden abierta' : 'Abrir esta orden'}" onclick="${actual ? '' : `cwAbrirVerId(${parseInt(r.id, 10)}, false)`}">
                    <td class="ps-2">${esc(r.fecha || '')}</td>
                    <td class="fw-semibold text-primary">${esc(r.numero_orden || '')}</td>
                    <td>${esc(r.placa || '')}</td>
                    <td class="text-truncate" style="max-width:200px">${esc(r.cliente_nombre || '')}</td>
                    <td class="text-truncate" style="max-width:320px" title="${esc(r.servicios || '')}">${esc(r.servicios || '')}</td>
                    <td class="text-end">${fmt(r.total, 2)}</td>
                    <td>${doc}</td>
                    <td class="text-center pe-2"><span class="badge bg-${e[0]} bg-opacity-10 text-${e[0]}">${esc(e[1])}</span></td>
                </tr>`;
            }).join('');
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-4">${esc(e.message)}</td></tr>`;
        }
    }
    // Al entrar a la pestaña Historial sin búsqueda previa, carga el de esta orden.
    document.getElementById('cw-tab-historial')?.addEventListener('shown.bs.tab', () => {
        if (!document.getElementById('cw_hist_q').value.trim()) cwHistorialDeEstaOrden();
    });

    // ─── Crear entidades al vuelo (reutiliza modales existentes) ───────────────
    window.cwCrearVehiculo = function () {
        if (typeof window.abrirModalVehiculoCrear === 'function') window.abrirModalVehiculoCrear();
        else Swal.fire('Atención', 'No se pudo abrir el formulario de vehículo.', 'warning');
    };
    window.cwCrearCliente = function () {
        if (typeof window.abrirModalClienteCrear === 'function') window.abrirModalClienteCrear();
        else Swal.fire('Atención', 'No se pudo abrir el formulario de cliente.', 'warning');
    };
    window.cwCrearProducto = function () {
        if (typeof window.abrirModalProductoCrear === 'function') window.abrirModalProductoCrear();
        else Swal.fire('Atención', 'No se pudo abrir el formulario de producto.', 'warning');
    };

    // Autoseleccionar la entidad recién creada (best-effort según el payload del evento).
    window.addEventListener('vehiculoGuardado', (e) => {
        const j = e.detail || {}; const v = j.data || j;
        if (v && v.id) cwSeleccionarVehiculo({ id: v.id, placa: v.placa, marca: v.marca, modelo: v.modelo });
        else if (v && v.placa) { document.getElementById('cw_vehiculo_busqueda').value = v.placa; cwBuscarVehiculos(v.placa); }
    });
    document.addEventListener('clienteGuardado', (e) => {
        const j = e.detail || {}; const c = j.data || j;
        if (c && c.id) cwSeleccionarCliente({ id: c.id, nombre: c.nombre || j.nombre, identificacion: c.identificacion, direccion: c.direccion, correo: c.correo || c.email, telefono: c.telefono });
    });
    document.addEventListener('productoGuardado', () => {
        // El nuevo producto queda disponible en el buscador de líneas; nada que autoseleccionar aquí.
    });
})();
</script>
