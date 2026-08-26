<?php
/** @var array $perm */
/** @var array $puntos */
?>
<div class="modal fade" id="modalOrdenSE" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="seTitulo"><i class="bi bi-tools me-1 text-info"></i> Nueva orden de Servicio Externo</h5>
                <span id="se_estado_badge" class="badge bg-secondary bg-opacity-10 text-secondary ms-2 d-none">Nuevo</span>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <!-- Barra de acciones (estilo factura) -->
            <div class="px-3 pt-2 d-flex flex-wrap gap-1 border-bottom pb-2">
                <button type="button" class="btn btn-outline-primary btn-sm px-2" onclick="seCrearCliente()" title="Registrar nuevo cliente">
                    <i class="bi bi-person-plus"></i>
                </button>
                <button type="button" class="btn btn-outline-primary btn-sm px-2" onclick="seCrearProducto()" title="Registrar nuevo repuesto/servicio">
                    <i class="bi bi-box-seam"></i>
                </button>
                <div class="vr mx-1"></div>
                <button type="button" id="se_btn_factura" class="btn btn-outline-success btn-sm px-2" onclick="seGenerarDocumento('FACTURA')" title="Generar Factura electrónica" disabled>
                    <i class="bi bi-receipt"></i> <span class="d-none d-md-inline">Factura</span>
                </button>
                <button type="button" id="se_btn_recibo" class="btn btn-outline-success btn-sm px-2" onclick="seGenerarDocumento('RECIBO')" title="Generar Recibo de venta" disabled>
                    <i class="bi bi-receipt-cutoff"></i> <span class="d-none d-md-inline">Recibo</span>
                </button>
                <div class="vr mx-1"></div>
                <button type="button" id="se_btn_pdf" class="btn btn-outline-danger btn-sm px-2" onclick="sePdf()" title="PDF del documento" disabled><i class="bi bi-file-earmark-pdf"></i></button>
                <button type="button" id="se_btn_correo" class="btn btn-outline-info btn-sm px-2" onclick="seCorreo()" title="Enviar por correo" disabled><i class="bi bi-envelope"></i></button>
                <button type="button" id="se_btn_whatsapp" class="btn btn-outline-success btn-sm px-2" onclick="seWhatsapp()" title="Enviar por WhatsApp" disabled><i class="bi bi-whatsapp"></i></button>
            </div>

            <div class="modal-body position-relative">
                <!-- Loader mientras carga la información completa de la orden vía AJAX -->
                <div id="se-modal-loader" class="d-none position-absolute top-0 start-0 w-100 h-100 d-flex flex-column align-items-center justify-content-center bg-white bg-opacity-75" style="z-index: 1055;">
                    <div class="spinner-border text-primary mb-2" role="status"></div>
                    <div class="small text-muted">Cargando información de la orden...</div>
                </div>
                <form id="formOrdenSE" autocomplete="off">
                    <input type="hidden" id="se_id">
                    <input type="hidden" id="se_id_cliente">
                    <input type="hidden" id="se_serie">
                    <input type="hidden" id="se_id_punto_emision">
                    <input type="hidden" id="se_id_establecimiento">

                    <!-- Cabecera (diseño factura) -->
                    <div class="p-2 bg-white border rounded-3 mb-2">
                        <!-- Fila 1: fecha, serie, secuencial, cliente -->
                        <div class="row g-2 align-items-end">
                            <div class="col-6 col-md-2">
                                <label class="x-small fw-bold text-muted mb-1">Fecha servicio</label>
                                <input type="datetime-local" id="se_fecha_servicio" class="form-control form-control-sm border-primary border-opacity-10 py-0" style="height:31px;">
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="x-small fw-bold text-muted mb-1">Serie <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('modulos/servicio-externo', 'se_select_serie', 'id_punto_emision') ?></label>
                                <select id="se_select_serie" name="id_punto_emision" class="form-select form-select-sm border-primary border-opacity-25" onchange="seSerieChange()" style="height:31px;">
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
                                <input type="text" id="se_secuencial" class="form-control form-control-sm border-primary border-opacity-25 text-center text-dark py-0 bg-light" style="height:31px;" readonly placeholder="000000001" maxlength="9">
                            </div>
                            <div class="col-12 col-md-6 position-relative">
                                <label class="x-small fw-bold text-muted mb-1">Cliente <span class="text-danger">*</span></label>
                                <input type="text" id="se_cliente_busqueda" class="form-control form-control-sm border-primary border-opacity-10" placeholder="Seleccionar cliente..." oninput="seBuscarClientes(this.value)">
                                <div id="se_cli_dropdown" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index:1085; max-height:240px; overflow:auto;"></div>
                            </div>
                        </div>
                        <!-- Fila 2: equipo atendido -->
                        <div class="row g-2 align-items-end mt-1">
                            <div class="col-12 col-md-4">
                                <label class="x-small fw-bold text-muted mb-1">Equipo <span class="text-danger">*</span></label>
                                <input type="text" id="se_equipo_descripcion" class="form-control form-control-sm border-primary border-opacity-10" placeholder="Descripción del equipo atendido...">
                            </div>
                            <div class="col-4 col-md-2">
                                <label class="x-small fw-bold text-muted mb-1">Marca</label>
                                <input type="text" id="se_equipo_marca" class="form-control form-control-sm border-primary border-opacity-10" placeholder="Marca">
                            </div>
                            <div class="col-4 col-md-2">
                                <label class="x-small fw-bold text-muted mb-1">Modelo</label>
                                <input type="text" id="se_equipo_modelo" class="form-control form-control-sm border-primary border-opacity-10" placeholder="Modelo">
                            </div>
                            <div class="col-4 col-md-2">
                                <label class="x-small fw-bold text-muted mb-1">Serie</label>
                                <input type="text" id="se_equipo_serie" class="form-control form-control-sm border-primary border-opacity-10" placeholder="N° de serie">
                            </div>
                            <div class="col-12 col-md-2">
                                <label class="x-small fw-bold text-muted mb-1">Bodega <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('modulos/servicio-externo', 'se_id_bodega', 'id_bodega') ?></label>
                                <select id="se_id_bodega" name="id_bodega" class="form-select form-select-sm border-primary border-opacity-10" style="height:31px;" title="Bodega de donde se toman los repuestos al facturar" onchange="seBodegaCabeceraChange()">
                                    <option value="">Seleccione...</option>
                                    <?php if (isset($bodegas)): ?>
                                        <?php foreach ($bodegas as $b): ?>
                                            <option value="<?= $b['id'] ?>" <?= !empty($b['es_default']) ? 'selected' : '' ?>><?= htmlspecialchars($b['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                        <!-- Fila 3: dirección del servicio -->
                        <div class="row g-2 align-items-end mt-1">
                            <div class="col-12">
                                <label class="x-small fw-bold text-muted mb-1"><i class="bi bi-geo-alt"></i> Dirección del servicio</label>
                                <input type="text" id="se_direccion_servicio" class="form-control form-control-sm border-primary border-opacity-10" placeholder="Sitio donde se realizó el mantenimiento...">
                            </div>
                        </div>
                        <input type="hidden" id="se_numero_orden">
                        <div class="mt-1" id="se_info_cliente" style="font-size:.78rem"></div>
                    </div>

                    <!-- Trabajo realizado / Observaciones -->
                    <div class="row g-2 mb-2">
                        <div class="col-12 col-md-6">
                            <label class="x-small fw-bold text-muted mb-1">Trabajo realizado</label>
                            <textarea id="se_descripcion_trabajo" class="form-control form-control-sm border-primary border-opacity-10" rows="2" placeholder="Detalle del mantenimiento/reparación realizado..."></textarea>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="x-small fw-bold text-muted mb-1">Observaciones</label>
                            <textarea id="se_observaciones" class="form-control form-control-sm border-primary border-opacity-10" rows="2" placeholder="Observaciones adicionales..."></textarea>
                        </div>
                    </div>

                    <!-- Repuestos / Mano de obra (grilla igual que factura de venta) -->
                    <div class="mt-2 border rounded-3 overflow-hidden bg-white shadow-sm">
                        <div class="table-responsive" style="max-height: 300px;">
                            <table class="table table-sm table-detalle mb-0 text-nowrap">
                                <thead>
                                    <tr class="table-light border-bottom">
                                        <th class="ps-3 py-2 small fw-bold text-muted" style="width: 27%;">Descripción</th>
                                        <th class="py-2 small fw-bold text-muted" style="width: 8%;">Adicional</th>
                                        <th class="py-2 small fw-bold text-muted col-medida-header <?= (($empresa['mostrar_unidad_medida'] ?? true) === 'true' || ($empresa['mostrar_unidad_medida'] ?? true) === true) ? '' : 'd-none' ?>" style="width: 7%;">Medida</th>
                                        <th class="py-2 small fw-bold text-muted text-center" style="width: 6%;">Cant.</th>
                                        <th class="py-2 small fw-bold text-muted" style="width: 10%;">Precios</th>
                                        <th class="py-2 small fw-bold text-muted text-end" style="width: 8%;">P. Sin Imp.</th>
                                        <th class="py-2 small fw-bold text-muted text-end" style="width: 8%;">P. Con Imp.</th>
                                        <th class="py-2 small fw-bold text-muted text-end" style="width: 6%;">Desc.</th>
                                        <th class="py-2 small fw-bold text-muted text-center" style="width: 6%;">Iva</th>
                                        <?php if (!empty($empresa['obligatorio_lotes']) && ($empresa['obligatorio_lotes'] === 'true' || $empresa['obligatorio_lotes'] === true)): ?>
                                            <th class="py-2 small fw-bold text-muted text-center" style="width:8%;">Lote</th>
                                        <?php endif; ?>
                                        <?php if (!empty($empresa['obligatorio_caducidad']) && ($empresa['obligatorio_caducidad'] === 'true' || $empresa['obligatorio_caducidad'] === true)): ?>
                                            <th class="py-2 small fw-bold text-muted text-center" style="width:9%;">Caducidad</th>
                                        <?php endif; ?>
                                        <?php if (!empty($empresa['obligatorio_nup']) && ($empresa['obligatorio_nup'] === 'true' || $empresa['obligatorio_nup'] === true)): ?>
                                            <th class="py-2 small fw-bold text-muted text-center" style="width:9%;">NUP / Serial</th>
                                        <?php endif; ?>
                                        <th class="py-2 small fw-bold text-muted text-end pe-4" style="width: 10%;">Subtotal</th>
                                        <th style="width: 40px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="se_tbodyDetalle"></tbody>
                            </table>
                        </div>
                        <div class="p-2 border-top bg-light d-flex justify-content-between align-items-center">
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" onclick="seAgregarLinea()">
                                <i class="bi bi-plus-circle me-1"></i> Agregar línea
                            </button>
                            <div class="small fw-bold text-muted pe-3">Items: <span id="se-count-items">0</span></div>
                        </div>
                    </div>

                    <div class="row g-2 mt-2">
                        <!-- Información Adicional (pestaña, igual que factura de venta) -->
                        <div class="col-12 col-md-7">
                            <ul class="nav nav-tabs nav-tabs-sm mb-0" role="tablist">
                                <li class="nav-item"><button class="nav-link active py-1 small" data-bs-toggle="tab" data-bs-target="#se-subtab-info" type="button"><i class="bi bi-info-circle me-1"></i>Info. Adicional</button></li>
                            </ul>
                            <div class="tab-content bg-white border p-2 rounded-bottom" style="min-height:120px;">
                                <div class="tab-pane fade show active" id="se-subtab-info" role="tabpanel">
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
                                                <tbody id="se_info_body"></tbody>
                                            </table>
                                        </div>
                                        <div class="p-1 border-top bg-light">
                                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold ms-2" onclick="seAgregarInfo()">
                                                <i class="bi bi-plus-circle me-1"></i> Agregar línea
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Totales (estilo factura) -->
                        <div class="col-12 col-md-5">
                            <div class="border rounded-3 p-2 bg-light small">
                                <div class="d-flex justify-content-between align-items-center mb-1"><span class="text-muted">Subtotal</span><span id="se-lbl-subtotal">0.00</span></div>
                                <div id="se-lbl-subtotales-iva"></div>
                                <div class="d-flex justify-content-between align-items-center mb-1 text-danger"><span>Descuento</span><span id="se-lbl-descuento">0.00</span></div>
                                <div id="se-lbl-ivas-grupo"></div>
                                <div class="d-flex justify-content-between align-items-center mb-1 d-none" id="se-lbl-ice-row"><span class="text-muted">(+) ICE</span><span id="se-lbl-ice">0.00</span></div>
                                <hr class="my-1">
                                <div class="d-flex justify-content-between fw-bold fs-6"><span>TOTAL</span><span id="se-lbl-total">0.00</span></div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer py-2 d-flex justify-content-between">
                <div>
                    <button type="button" class="btn btn-outline-danger btn-sm d-none" id="se_btn_eliminar" onclick="seEliminar()"><i class="bi bi-trash"></i> Eliminar</button>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary btn-sm" id="se_btn_guardar" onclick="seGuardar()"><i class="bi bi-save me-1"></i> Guardar</button>
                </div>
            </div>
        </div>
    </div>
</div>
