<?php
/** @var array $perm */
/** @var array $puntos */
/** @var array $vendedores */
/** @var array $formasPago */
?>
<style>
    .modal-factura .modal-header { background-color: #f8f9fa; border-bottom: 1px solid #dee2e6; padding: 0.75rem 1rem; }
    .modal-factura .modal-body { padding: 0 !important; }
    .modal-factura .nav-tabs .nav-link { font-size: 0.875rem; }
    .modal-factura label { font-size: 0.85rem; font-weight: 600; color: #495057; margin-bottom: 3px !important; }
    .modal-factura .table-detalle th { font-size: 0.7rem !important; padding: 4px 8px !important; text-transform: uppercase; background-color: #f8f9fa; }
    .modal-factura .table-detalle td { padding: 4px 8px !important; vertical-align: middle; }
    .modal-factura .row-detalle:hover { background-color: rgba(13, 110, 253, 0.03); }
    .modal-factura .row-detalle:hover .remove-row { opacity: 1; }
    .modal-factura .remove-row { color: #dc3545; opacity: 0; transition: opacity 0.2s; }
    .modal-factura .input-detalle { border: 1px solid #dee2e6; background: #fff; height: 28px !important; font-size: 0.82rem !important; padding: 2px 6px !important; border-radius: 4px; }
    .modal-factura .input-detalle:focus { box-shadow: inset 0 0 0 1px #0d6efd; outline: none; }

    /* Sub-modal "Cargar consignación": fondo más oscuro para diferenciarlo del modal base */
    #modalFaccvCargar .modal-content { background-color: #e4e8ee; border: 1px solid #c4ccd6; }
    #modalFaccvCargar .modal-header { background-color: #d3dae3; border-bottom: 1px solid #c4ccd6; }
    #modalFaccvCargar .modal-footer { background-color: #d3dae3; border-top: 1px solid #c4ccd6; }
    #modalFaccvCargar .table { background-color: #fff; }
</style>

<div class="modal fade modal-factura" id="modalFacturacionCv" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-receipt-cutoff me-2"></i><span id="faccv_titulo">Nueva facturación de consignación</span></h5>
                <span id="faccv_badge" class="badge d-none ms-2" style="font-size:0.72rem;vertical-align:middle;"></span>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-0">
                <form id="formFaccv" autocomplete="off">
                    <input type="hidden" id="faccv_id">
                    <input type="hidden" id="faccv_id_factura">
                    <input type="hidden" id="faccv_estado">

                    <!-- Barra de Acciones Superior -->
                    <div class="px-3 py-2 bg-light border-bottom d-flex gap-1 align-items-center flex-wrap">
                        <button type="button" class="btn btn-outline-primary btn-sm px-2" onclick="faccvNuevoCliente()" title="Registrar nuevo cliente"><i class="bi bi-person-plus fs-6"></i></button>
                        <div class="vr mx-1"></div>
                        <button type="button" class="btn btn-outline-danger btn-sm px-2" onclick="faccvPdf()" title="Exportar PDF"><i class="bi bi-file-earmark-pdf"></i></button>
                        <button type="button" class="btn btn-outline-info btn-sm px-2" onclick="faccvEmail()" title="Enviar por correo"><i class="bi bi-envelope"></i></button>
                        <button type="button" class="btn btn-outline-success btn-sm px-2" onclick="faccvWhatsapp()" title="Enviar por WhatsApp"><i class="bi bi-whatsapp"></i></button>
                        <div class="vr mx-1"></div>
                        <button type="button" class="btn btn-outline-primary btn-sm px-2 d-none" id="faccv_btn_duplicar" onclick="faccvDuplicar()" title="Crear nueva facturación desde esta"><i class="bi bi-copy"></i></button>
                        <div class="ms-auto d-flex gap-2 align-items-center">
                            <span id="faccv_num_factura_wrap" class="badge bg-light text-dark border d-none" style="font-size:0.78rem;" title="Factura de venta generada">
                                <i class="bi bi-receipt me-1"></i>Factura <span id="faccv_num_factura" class="fw-bold"></span>
                            </span>
                            <button type="button" class="btn btn-primary btn-sm" id="faccv_btn_cargar" onclick="faccvAbrirCargar()" title="Cargar consignación"><i class="bi bi-box-arrow-in-down me-1"></i> Cargar consignación</button>
                        </div>
                    </div>

                    <!-- Pestañas -->
                    <div class="d-flex align-items-center bg-light px-3 pt-2">
                        <ul class="nav nav-tabs border-bottom-0 flex-grow-1" id="tabsFaccv" role="tablist">
                            <li class="nav-item"><a class="nav-link active py-2 small" id="faccv-tab-general-btn" data-bs-toggle="tab" href="#faccv-tab-general" role="tab" style="white-space:nowrap;"><i class="bi bi-receipt me-1"></i> Facturación de consignación</a></li>
                            <li class="nav-item"><a class="nav-link py-2 small" id="faccv-tab-asiento-btn" data-bs-toggle="tab" href="#faccv-tab-asiento" role="tab" style="white-space:nowrap;"><i class="bi bi-calculator me-1"></i> Asiento contable</a></li>
                        </ul>
                    </div>
                    <div class="border-bottom bg-light mb-0"></div>

                    <div class="tab-content border-top">
                        <!-- Pestaña principal -->
                        <div class="tab-pane fade show active" id="faccv-tab-general" role="tabpanel">
                            <!-- Cabecera -->
                            <div class="p-3 bg-white border-bottom">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-2">
                                        <label class="x-small fw-bold text-muted mb-1">Fecha</label>
                                        <input type="date" class="form-control form-control-sm border-primary border-opacity-10" style="height:31px;" id="faccv_fecha">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="x-small fw-bold text-muted mb-1">Serie</label>
                                        <select class="form-select form-select-sm border-primary border-opacity-25" id="faccv_select_serie" style="height:31px;" onchange="faccvSerieChange()">
                                            <?php if (empty($puntos)): ?>
                                                <option value="">— Sin secuencial —</option>
                                            <?php else: foreach ($puntos as $p): ?>
                                                <option value="<?= (int)$p['id'] ?>" data-cod-est="<?= htmlspecialchars($p['cod_establecimiento'] ?? '') ?>" data-cod-punto="<?= htmlspecialchars($p['codigo_punto'] ?? '') ?>">
                                                    <?= htmlspecialchars(($p['cod_establecimiento'] ?? '') . '-' . ($p['codigo_punto'] ?? '')) ?>
                                                </option>
                                            <?php endforeach; endif; ?>
                                        </select>
                                        <input type="hidden" id="faccv_serie">
                                        <input type="hidden" id="faccv_id_punto_emision">
                                        <input type="hidden" id="faccv_establecimiento">
                                        <input type="hidden" id="faccv_punto_emision">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="x-small fw-bold text-muted mb-1">Secuencial</label>
                                        <input type="text" class="form-control form-control-sm border-primary border-opacity-25 text-center bg-light" style="height:31px;" id="faccv_secuencial" placeholder="000000000" readonly>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="x-small fw-bold text-muted mb-1">Vendedor</label>
                                        <select class="form-select form-select-sm border-primary border-opacity-10" id="faccv_id_vendedor" style="height:31px;">
                                            <option value="">Seleccione...</option>
                                            <?php foreach (($vendedores ?? []) as $v): ?>
                                                <option value="<?= (int)$v['id'] ?>"><?= htmlspecialchars($v['nombre'] ?? '') ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="x-small fw-bold text-muted mb-1">Observaciones</label>
                                        <input type="text" class="form-control form-control-sm border-primary border-opacity-10" id="faccv_observaciones" style="height:31px;" placeholder="Notas internas (opcional)">
                                    </div>
                                </div>

                                <!-- Cliente a facturar (buscador + info) -->
                                <div class="col-12 mt-2">
                                    <div class="p-2 border rounded-3 bg-light bg-opacity-10">
                                        <div class="row g-2 align-items-center">
                                            <div class="col-md-12 position-relative">
                                                <div class="input-group input-group-sm flex-grow-1 rounded-pill overflow-hidden border">
                                                    <span class="input-group-text bg-white border-0 text-primary"><i class="bi bi-search"></i></span>
                                                    <input type="text" class="form-control border-0 px-1" id="faccv_cliente_busqueda" placeholder="Buscar cliente a facturar por identificación o nombre..." oninput="faccvBuscarClientes(this.value)" autocomplete="off">
                                                    <input type="hidden" id="faccv_id_cliente">
                                                    <input type="hidden" id="faccv_cliente_email">
                                                </div>
                                                <div id="faccv_clientes_dd" class="list-group shadow position-absolute d-none" style="z-index:1080; width:100%; max-height:240px; overflow-y:auto; top:38px;"></div>
                                            </div>

                                            <div class="col-12 px-3 mt-1 d-none" id="faccv_info_cliente">
                                                <div class="d-flex flex-wrap align-items-center gap-x-3 gap-y-1" style="font-size:0.72rem; color:#6c757d;">
                                                    <span class="border-end pe-2 me-1 fw-bold text-dark" id="faccv_lbl_ruc"></span>
                                                    <div class="d-flex align-items-center gap-1"><i class="bi bi-geo-alt"></i><span id="faccv_lbl_direccion"></span></div>
                                                    <div class="d-flex align-items-center gap-1 border-start ps-2"><i class="bi bi-envelope"></i><span id="faccv_lbl_correo"></span></div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Detalle -->
                            <div class="p-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <h6 class="mb-0 fw-bold text-secondary small"><i class="bi bi-box-seam me-1"></i> Productos a facturar</h6>
                                    <span id="faccv_lineas_info" class="small text-muted"></span>
                                </div>
                                <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                                    <div class="table-responsive" style="max-height:340px;">
                                        <table class="table table-sm table-detalle mb-0 text-nowrap" id="tablaFaccvLineas">
                                            <thead>
                                                <tr class="table-light border-bottom">
                                                    <th class="ps-3 py-2 small fw-bold text-muted">Consignación</th>
                                                    <th class="py-2 small fw-bold text-muted">Producto</th>
                                                    <th class="py-2 small fw-bold text-muted">Lote</th>
                                                    <th class="py-2 small fw-bold text-muted">Bodega</th>
                                                    <th class="py-2 small fw-bold text-muted text-end">Saldo</th>
                                                    <th class="py-2 small fw-bold text-muted text-end" style="width:100px;">Precio</th>
                                                    <th class="py-2 small fw-bold text-muted text-end" style="width:90px;">Cant.</th>
                                                    <th class="py-2 small fw-bold text-muted text-end" style="width:90px;">Desc.</th>
                                                    <th class="py-2 small fw-bold text-muted text-center" style="width:70px;">IVA</th>
                                                    <th class="py-2 small fw-bold text-muted text-end pe-3">Subtotal</th>
                                                    <th style="width:36px;"></th>
                                                </tr>
                                            </thead>
                                            <tbody id="faccv_lineas_body">
                                                <tr><td colspan="11" class="text-center text-muted py-4">Use <b>Cargar consignación</b> para agregar productos.</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="p-2 border-top bg-light d-flex justify-content-between align-items-center">
                                        <span class="small text-muted"><i class="bi bi-info-circle me-1"></i> Los productos provienen de consignaciones entregadas con saldo.</span>
                                        <div class="small fw-bold text-muted pe-2">Items: <span id="faccv_count_items">0</span></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Pie: totales -->
                            <div class="p-3 border-top bg-light">
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <ul class="nav nav-tabs nav-tabs-sm mb-2" role="tablist">
                                            <li class="nav-item"><button class="nav-link active py-1 small" data-bs-toggle="tab" data-bs-target="#faccv-sub-info" type="button"><i class="bi bi-info-circle me-1"></i>Info. Adicional</button></li>
                                            <li class="nav-item"><button class="nav-link py-1 small" data-bs-toggle="tab" data-bs-target="#faccv-sub-pago" type="button"><i class="bi bi-cash-coin me-1"></i>Forma de pago SRI</button></li>
                                            <li class="nav-item"><button class="nav-link py-1 small" data-bs-toggle="tab" data-bs-target="#faccv-sub-credito" type="button"><i class="bi bi-calendar-check me-1"></i>Crédito</button></li>
                                        </ul>
                                        <div class="tab-content bg-white border p-2 rounded-bottom" style="min-height:120px;">
                                            <!-- Info. Adicional -->
                                            <div class="tab-pane fade show active" id="faccv-sub-info" role="tabpanel">
                                                <div class="border rounded-2 overflow-hidden bg-white">
                                                    <div class="table-responsive" style="max-height:180px;">
                                                        <table class="table table-sm mb-0">
                                                            <thead class="table-light">
                                                                <tr>
                                                                    <th class="ps-2 py-0 small fw-bold text-muted" style="width:40%;">Concepto</th>
                                                                    <th class="py-0 small fw-bold text-muted" style="width:50%;">Detalle</th>
                                                                    <th class="py-0" style="width:10%;"></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody id="faccv_tbody_info"></tbody>
                                                        </table>
                                                    </div>
                                                    <div class="p-1 border-top bg-light">
                                                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold ms-2" id="faccv_btn_add_info" onclick="faccvAgregarInfo()"><i class="bi bi-plus-circle me-1"></i> Agregar línea</button>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Forma de pago SRI -->
                                            <div class="tab-pane fade" id="faccv-sub-pago" role="tabpanel">
                                                <div id="faccv_pagos_container">
                                                    <div class="row g-2 align-items-center mb-1 faccv-row-pago">
                                                        <div class="col-7">
                                                            <select class="form-select form-select-sm border-0 bg-light faccv-fpago">
                                                                <option value="" data-id="">-- Seleccione forma de pago --</option>
                                                                <?php foreach (($formasPago ?? []) as $fp): ?>
                                                                    <option value="<?= htmlspecialchars($fp['codigo'] ?? '') ?>" data-id="<?= (int)($fp['id'] ?? 0) ?>"><?= htmlspecialchars($fp['nombre'] ?? '') ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-4">
                                                            <input type="number" class="form-control form-control-sm text-end border-0 bg-light fw-bold faccv-vpago" step="0.01" value="0.00">
                                                        </div>
                                                        <div class="col-1 text-center"><span></span></div>
                                                    </div>
                                                </div>
                                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none small mt-1 ms-1" id="faccv_btn_add_pago" onclick="faccvAgregarPago()"><i class="bi bi-plus-circle me-1"></i>Añadir pago</button>
                                            </div>
                                            <!-- Crédito -->
                                            <div class="tab-pane fade" id="faccv-sub-credito" role="tabpanel">
                                                <div class="row g-2 p-1">
                                                    <div class="col-md-6">
                                                        <label class="x-small text-muted mb-1">Días de crédito</label>
                                                        <input type="number" class="form-control form-control-sm" id="faccv_dias_credito" value="0" min="0">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="x-small text-muted mb-1">Plazo</label>
                                                        <select class="form-select form-select-sm" id="faccv_plazo_unidad">
                                                            <option value="dias">Días</option>
                                                            <option value="meses">Meses</option>
                                                            <option value="anios">Años</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="bg-white border rounded p-2 shadow-sm" style="font-size:0.75rem;">
                                            <div class="d-flex justify-content-between align-items-center mb-1 fw-bold border-bottom pb-1">
                                                <span class="text-muted">Subtotal</span><span id="faccv_tot_subtotal">0.00</span>
                                            </div>
                                            <div id="faccv_tot_sub_grupos" class="mb-1"></div>
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="text-muted">(-) Descuento</span><span id="faccv_tot_desc">0.00</span>
                                            </div>
                                            <div id="faccv_tot_iva_grupos" class="mb-1"></div>
                                            <hr class="my-1 opacity-25">
                                            <div class="d-flex justify-content-between align-items-center bg-light border py-1 px-2 rounded">
                                                <span class="fw-bold text-dark" style="font-size:0.8rem;">TOTAL</span>
                                                <span class="fw-bold text-dark" style="font-size:1rem;" id="faccv_tot_total">0.00</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pestaña: Asiento contable (reversa de la consignación: Debe Inventario / Haber Mercadería en Consignación, a costo) -->
                        <div class="tab-pane fade p-3" id="faccv-tab-asiento" role="tabpanel">
                            <div class="alert alert-light border small d-flex align-items-center gap-2 mb-2 py-2">
                                <i class="bi bi-info-circle text-primary"></i>
                                <span>Reversa (a costo) de la consignación: <em>Inventario</em> contra <em>Mercadería en consignación</em>. La mercadería vuelve del poder de terceros para poder facturarla.</span>
                            </div>
                            <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                                <div class="table-responsive" style="max-height:320px;">
                                    <table class="table table-sm mb-0 text-nowrap">
                                        <thead>
                                            <tr class="table-light border-bottom">
                                                <th class="ps-3 py-2 small fw-bold text-muted" style="width:50%;">Cuenta Contable</th>
                                                <th class="py-2 small fw-bold text-muted text-end pe-3" style="width:20%;">Débito / Debe</th>
                                                <th class="py-2 small fw-bold text-muted text-end pe-3" style="width:20%;">Crédito / Haber</th>
                                                <th class="py-2 small fw-bold text-muted" style="width:10%;">Ref.</th>
                                            </tr>
                                        </thead>
                                        <tbody id="faccv-tbody-asiento">
                                            <tr><td colspan="4" class="text-center py-4 text-muted">Guarde el documento para ver el asiento (se calcula a costo).</td></tr>
                                        </tbody>
                                        <tfoot class="bg-light fw-bold border-top">
                                            <tr>
                                                <td class="text-end py-2">Totales:</td>
                                                <td class="text-end pe-3 py-2 text-primary" id="faccv-asiento-total-debe">0.00</td>
                                                <td class="text-end pe-3 py-2 text-primary" id="faccv-asiento-total-haber">0.00</td>
                                                <td class="py-2"><span id="faccv-asiento-badge" class="badge bg-secondary bg-opacity-10 text-secondary border px-2">—</span></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer justify-content-between bg-light border-top p-2">
                <div>
                    <button type="button" class="btn btn-outline-danger btn-sm px-3 d-none" id="faccv_btn_eliminar" onclick="faccvEliminar()"><i class="bi bi-trash3 me-1"></i> Eliminar borrador</button>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Cerrar</button>
                    <button type="button" class="btn btn-primary px-4 btn-sm" id="faccv_btn_guardar" onclick="faccvGuardar()"><i class="bi bi-check2-circle me-1"></i> Guardar borrador</button>
                    <button type="button" class="btn btn-success px-3 btn-sm d-none" id="faccv_btn_generar" onclick="faccvGenerar()"><i class="bi bi-receipt-cutoff me-1"></i> Generar factura</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sub-modal: Cargar consignación -->
<div class="modal fade" id="modalFaccvCargar" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header py-2">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-box-arrow-in-down me-2"></i> Cargar consignación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="position-relative mb-2">
                    <label class="x-small fw-bold text-muted mb-1">Buscar por cliente o N° de consignación</label>
                    <input type="text" class="form-control form-control-sm" id="faccv_cg_busqueda" placeholder="Escriba cliente o número (mín. 2 caracteres)..." oninput="faccvCgBuscar(this.value)" autocomplete="off">
                    <div id="faccv_cg_dd" class="list-group shadow position-absolute d-none" style="z-index:1090; width:100%; max-height:220px; overflow-y:auto;"></div>
                </div>
                <div id="faccv_cg_consinfo" class="small text-muted mb-2"></div>
                <div class="table-responsive border rounded-3" style="max-height:46vh; overflow:auto;">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr class="small">
                                <th class="text-center" style="width:34px;"><input type="checkbox" id="faccv_cg_all" onchange="faccvCgToggleAll(this)" title="Seleccionar todo"></th>
                                <th>Código</th>
                                <th>Descripción</th>
                                <th class="text-end">Saldo</th>
                                <th>Lote</th>
                                <th>NUP</th>
                                <th>Precios</th>
                                <th class="text-end">Precio</th>
                                <th class="text-end">Cant.</th>
                                <th class="text-end">Desc.</th>
                                <th class="text-end pe-2">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody id="faccv_cg_body">
                            <tr><td colspan="11" class="text-center text-muted py-4">Busque una consignación por cliente o número.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2 justify-content-between">
                <span class="small text-muted" id="faccv_cg_info"></span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="faccvCgAgregar()"><i class="bi bi-plus-lg me-1"></i> Agregar seleccionados</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const RUTA = window.RUTA_MODULO_FACCV;
    const DEC_P = (window.EMPRESA_CONFIG && window.EMPRESA_CONFIG.decimales_precio) || 2;
    let modal, tCli = null;
    const added = new Set();

    function getModal() { if (!modal) modal = new bootstrap.Modal(document.getElementById('modalFacturacionCv')); return modal; }
    function num(v) { const n = parseFloat(v); return isNaN(n) ? 0 : n; }
    function esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c])); }
    function $(id) { return document.getElementById(id); }
    function fmt(v, d) { return num(v).toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d }); }

    function resetForm() {
        $('formFaccv').reset();
        ['faccv_id','faccv_id_factura','faccv_estado','faccv_serie','faccv_id_punto_emision','faccv_establecimiento','faccv_punto_emision','faccv_id_cliente','faccv_cliente_email','faccv_secuencial'].forEach(id => $(id).value = '');
        $('faccv_lineas_body').innerHTML = '<tr><td colspan="11" class="text-center text-muted py-4">Use <b>Cargar consignación</b> para agregar productos.</td></tr>';
        $('faccv_lineas_info').textContent = '';
        $('faccv_info_cliente').classList.add('d-none');
        $('faccv_tbody_info').innerHTML = '';
        faccvCargarPagos([]);
        resetAsiento();
        $('faccv_num_factura_wrap').classList.add('d-none');
        cgClienteId = null;
        added.clear();
        recalc();
    }

    function setEditable(editable) {
        ['faccv_fecha','faccv_select_serie','faccv_cliente_busqueda','faccv_id_vendedor','faccv_observaciones','faccv_dias_credito','faccv_plazo_unidad'].forEach(id => { const el = $(id); if (el) el.disabled = !editable; });
        const bc = $('faccv_btn_cargar'); if (bc) bc.style.display = editable ? '' : 'none';
        const ba = $('faccv_btn_add_pago'); if (ba) ba.classList.toggle('d-none', !editable);
        document.querySelectorAll('#faccv_pagos_container .faccv-fpago, #faccv_pagos_container .faccv-vpago').forEach(el => { el.disabled = !editable; });
        $('faccv_btn_add_info').classList.toggle('d-none', !editable);
        $('faccv_btn_guardar').classList.toggle('d-none', !editable);
    }

    function resetTabs() {
        try { new bootstrap.Tab($('faccv-tab-general-btn')).show(); } catch (e) {}
    }

    window.abrirModalFacturacionNueva = async function () {
        resetForm(); resetTabs(); setEditable(true);
        $('faccv_titulo').textContent = 'Nueva facturación de consignación';
        $('faccv_badge').className = 'badge bg-secondary bg-opacity-10 text-secondary ms-2';
        $('faccv_badge').textContent = 'Nuevo';
        $('faccv_estado').value = '';
        $('faccv_btn_eliminar').classList.add('d-none');
        $('faccv_btn_generar').classList.add('d-none');
        $('faccv_btn_duplicar').classList.add('d-none');
        $('faccv_select_serie').disabled = false;
        $('faccv_fecha').value = new Date().toISOString().slice(0, 10);
        $('faccv_tbody_info').innerHTML = ''; faccvAgregarInfo();
        // Forma de pago por defecto de la empresa (hasta elegir cliente).
        if (window.EMPRESA_CONFIG && EMPRESA_CONFIG.id_forma_pago_sri_def) faccvAplicarFormaPago(EMPRESA_CONFIG.id_forma_pago_sri_def);
        const sel = $('faccv_select_serie');
        if (sel.value) { sel.selectedIndex = 0; await faccvSerieChange(); }
        getModal().show();
    };

    window.abrirModalFacturacionVer = async function (tr) {
        const row = JSON.parse(tr.dataset.row);
        const editable = (row.estado === 'borrador') && window.FACCV_PERM.actualizar;
        resetForm(); resetTabs(); setEditable(editable);
        $('faccv_id').value = row.id;
        $('faccv_estado').value = row.estado || '';
        $('faccv_id_factura').value = row.id_factura || '';
        $('faccv_titulo').textContent = 'Facturación ' + (row.serie || '') + '-' + (row.secuencial || '');
        pintarBadge(row.estado);
        $('faccv_btn_guardar').innerHTML = '<i class="bi bi-check2-circle me-1"></i> Actualizar borrador';
        $('faccv_btn_eliminar').classList.toggle('d-none', !(row.estado === 'borrador' && window.FACCV_PERM.eliminar));
        $('faccv_btn_generar').classList.toggle('d-none', !(row.estado === 'borrador' && window.FACCV_PERM.actualizar));
        // "Crear nueva desde esta": disponible en documentos ya emitidos (facturada/anulada).
        $('faccv_btn_duplicar').classList.toggle('d-none', !((row.estado === 'anulada' || row.estado === 'facturada') && window.FACCV_PERM.crear));
        getModal().show();
        await cargarDetalle(row.id, editable);
    };

    async function cargarDetalle(id, editable) {
        const body = $('faccv_lineas_body');
        body.innerHTML = '<tr><td colspan="11" class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>';
        const res = await fetch(`${RUTA}/getDetalleAjax?id=${id}`);
        const data = await res.json();
        if (!data.ok) { body.innerHTML = '<tr><td colspan="11" class="text-center text-danger py-4">No se pudo cargar.</td></tr>'; return; }
        const r = data.data;

        $('faccv_titulo').textContent = 'Facturación ' + (r.serie || '') + '-' + (r.secuencial || '');
        // Número de la factura de venta asociada (barra de acciones, derecha).
        if (r.numero_factura) { $('faccv_num_factura').textContent = r.numero_factura; $('faccv_num_factura_wrap').classList.remove('d-none'); }
        else $('faccv_num_factura_wrap').classList.add('d-none');
        $('faccv_serie').value = r.serie || '';
        const sel = $('faccv_select_serie');
        if (r.id_punto_emision && sel.querySelector(`option[value="${r.id_punto_emision}"]`)) sel.value = r.id_punto_emision;
        sel.disabled = true;
        $('faccv_id_punto_emision').value = r.id_punto_emision || '';
        $('faccv_establecimiento').value = r.establecimiento || '';
        $('faccv_punto_emision').value = r.punto_emision || '';
        // Mostrar el número completo (serie-secuencial), igual que consignaciones en modo ver.
        $('faccv_secuencial').value = (r.serie ? r.serie + '-' : '') + (r.secuencial || '');
        $('faccv_secuencial').dataset.sec = r.secuencial || '';
        if (r.fecha_emision) $('faccv_fecha').value = String(r.fecha_emision).slice(0, 10);
        $('faccv_id_cliente').value = r.id_cliente || '';
        $('faccv_cliente_email').value = r.cliente_email || '';
        $('faccv_cliente_busqueda').value = (r.cliente_identificacion || '') + ' — ' + (r.cliente_nombre || '');
        pintarInfoCliente({ identificacion: r.cliente_identificacion, direccion: r.cliente_direccion, email: r.cliente_email });
        if (r.id_vendedor) $('faccv_id_vendedor').value = r.id_vendedor;
        $('faccv_observaciones').value = r.observaciones || '';

        // Crédito y forma de pago.
        if ($('faccv_dias_credito')) $('faccv_dias_credito').value = parseInt(r.dias_credito, 10) || 0;
        if ($('faccv_plazo_unidad') && r.plazo_unidad) $('faccv_plazo_unidad').value = r.plazo_unidad;
        faccvCargarPagos(r.pagos_sri || []);
        if (!editable) document.querySelectorAll('#faccv_pagos_container .faccv-fpago, #faccv_pagos_container .faccv-vpago').forEach(el => el.disabled = true);

        // Info adicional (el "Correo del cliente" se recrea como fila fija).
        $('faccv_tbody_info').innerHTML = '';
        (r.info_adicional || []).forEach(ia => {
            const nombre = ia.nombre || ia.concepto || '';
            const valor = ia.valor || ia.detalle || '';
            if (nombre === 'Correo del cliente') faccvInfoCorreo(valor);
            else faccvAgregarInfo(nombre, valor, !editable);
        });

        const dets = r.detalles || [];
        body.innerHTML = ''; added.clear();
        dets.forEach(d => addLinea({
            idcd: d.id_consignacion_detalle,
            numero: (d.consignacion_serie || '') + '-' + (d.consignacion_secuencial || ''),
            producto_codigo: d.producto_codigo, producto_nombre: d.producto_nombre, lote: d.lote, nup: d.nup, bodega_nombre: d.bodega_nombre,
            precio: d.precio_unitario, porc: d.porcentaje_impuesto,
            saldo: editable ? num(d.saldo_facturable) : num(d.cantidad),
            cantidad: num(d.cantidad), descuento: num(d.descuento), readonly: !editable
        }));
        if (!dets.length) body.innerHTML = '<tr><td colspan="11" class="text-center text-muted py-4">Sin líneas.</td></tr>';
        recalc();
    }

    function pintarBadge(estado) {
        const b = $('faccv_badge');
        b.textContent = (estado || '').charAt(0).toUpperCase() + (estado || '').slice(1);
        let cls = 'bg-warning bg-opacity-10 text-warning';
        if (estado === 'facturada') cls = 'bg-success bg-opacity-10 text-success';
        else if (estado === 'anulada') cls = 'bg-danger bg-opacity-10 text-danger';
        b.className = 'badge ms-2 ' + cls;
    }
    function pintarInfoCliente(c) {
        $('faccv_lbl_ruc').textContent = c.identificacion || '';
        $('faccv_lbl_direccion').textContent = c.direccion || '—';
        $('faccv_lbl_correo').textContent = c.email || '—';
        $('faccv_info_cliente').classList.toggle('d-none', !(c.identificacion || c.direccion || c.email));
    }

    window.faccvSerieChange = async function () {
        const sel = $('faccv_select_serie'); const opt = sel.options[sel.selectedIndex]; const idPunto = sel.value;
        if (!idPunto || !opt) { $('faccv_serie').value=''; $('faccv_id_punto_emision').value=''; $('faccv_secuencial').value=''; return; }
        const est = opt.dataset.codEst || '', punto = opt.dataset.codPunto || '';
        $('faccv_serie').value = est + '-' + punto; $('faccv_establecimiento').value = est; $('faccv_punto_emision').value = punto; $('faccv_id_punto_emision').value = idPunto;
        const res = await fetch(`${RUTA}/getSecuencialAjax?id_punto_emision=${idPunto}`);
        const data = await res.json();
        if (!data.ok) { $('faccv_secuencial').value=''; Swal.fire('Atención', data.msg || 'No hay secuencial configurado.', 'warning'); return; }
        $('faccv_secuencial').value = data.formateado || String(data.secuencial || '').padStart(9, '0');
        $('faccv_secuencial').dataset.sec = data.secuencial || '';
    };

    window.faccvBuscarClientes = function (q) {
        clearTimeout(tCli); const dd = $('faccv_clientes_dd');
        if (!q || q.length < 2) { dd.classList.add('d-none'); return; }
        tCli = setTimeout(async () => {
            const res = await fetch(`${RUTA}/getClientesAjax?q=${encodeURIComponent(q)}`); const data = await res.json();
            dd.innerHTML = '';
            (data.data || []).forEach(c => {
                const a = document.createElement('a'); a.href = '#'; a.className = 'list-group-item list-group-item-action py-1 small';
                a.innerHTML = `<span class="fw-bold">${esc(c.nombre)}</span> <span class="text-muted">${c.identificacion ? '· ' + esc(c.identificacion) : ''}</span>`;
                a.onclick = (ev) => { ev.preventDefault(); faccvSelCliente(c); };
                dd.appendChild(a);
            });
            if (!data.data || !data.data.length) dd.innerHTML = '<span class="list-group-item small text-muted">Sin resultados.</span>';
            dd.classList.remove('d-none');
        }, 300);
    };
    // Fila fija "Correo del cliente" en info adicional (como en factura de venta).
    function faccvInfoCorreo(email) {
        const tb = $('faccv_tbody_info');
        let fila = tb.querySelector('tr[data-tipo="correo-cliente"]');
        if (!email) { if (fila) fila.remove(); return; }
        if (fila) { fila.querySelector('.input-info-detalle').value = email; return; }
        const tr = document.createElement('tr'); tr.className = 'row-faccv-info'; tr.dataset.tipo = 'correo-cliente';
        tr.innerHTML = `
            <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent input-info-concepto" style="padding:0 4px;height:22px;font-size:0.78rem;" value="Correo del cliente" readonly></td>
            <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent input-info-detalle" style="padding:0 4px;height:22px;font-size:0.78rem;" value="${esc(email)}"></td>
            <td class="p-0 text-center pe-1"><span class="text-muted small" title="Se actualiza al cambiar el cliente"><i class="bi bi-lock-fill"></i></span></td>`;
        tb.appendChild(tr);
    }
    // Preselecciona la forma de pago SRI (data-id) en la PRIMERA fila y, si es la única
    // y su valor está en 0, le asigna el total del documento.
    function faccvAplicarFormaPago(idFp) {
        const sel = document.querySelector('#faccv_pagos_container .faccv-row-pago .faccv-fpago');
        if (!sel) return;
        if (idFp) {
            const target = String(idFp);
            for (let i = 0; i < sel.options.length; i++) {
                if (sel.options[i].getAttribute('data-id') === target) { sel.selectedIndex = i; break; }
            }
        }
        const rows = document.querySelectorAll('#faccv_pagos_container .faccv-row-pago');
        if (rows.length === 1) {
            const vinp = rows[0].querySelector('.faccv-vpago');
            if (vinp && num(vinp.value) === 0) vinp.value = num($('faccv_tot_total').textContent).toFixed(2);
        }
    }

    // Añade una fila de forma de pago (clona las opciones de la primera).
    window.faccvAgregarPago = function () {
        const cont = $('faccv_pagos_container');
        const first = cont.querySelector('.faccv-fpago');
        const opts = first ? Array.from(first.options).map(o => `<option value="${o.value}" data-id="${o.dataset.id || ''}">${esc(o.text)}</option>`).join('') : '';
        const div = document.createElement('div');
        div.className = 'row g-2 align-items-center mb-1 faccv-row-pago';
        div.innerHTML = `
            <div class="col-7"><select class="form-select form-select-sm border-0 bg-light faccv-fpago">${opts}</select></div>
            <div class="col-4"><input type="number" class="form-control form-control-sm text-end border-0 bg-light fw-bold faccv-vpago" step="0.01" value="0.00"></div>
            <div class="col-1 text-center"><button type="button" class="btn btn-link btn-sm p-0 text-danger shadow-none" onclick="this.closest('.faccv-row-pago').remove();" title="Eliminar"><i class="bi bi-x-circle-fill"></i></button></div>`;
        cont.appendChild(div);
        div.querySelector('.faccv-vpago').focus();
    };

    function collectPagos() {
        const out = [];
        document.querySelectorAll('#faccv_pagos_container .faccv-row-pago').forEach(row => {
            const forma = row.querySelector('.faccv-fpago')?.value || '';
            const valor = num(row.querySelector('.faccv-vpago')?.value);
            if (forma) out.push({ forma_pago: forma, valor });
        });
        return out;
    }

    function faccvCargarPagos(pagos) {
        const cont = $('faccv_pagos_container');
        const rows = cont.querySelectorAll('.faccv-row-pago');
        for (let i = rows.length - 1; i >= 1; i--) rows[i].remove();
        const base = cont.querySelector('.faccv-row-pago');
        if (!pagos || !pagos.length) {
            if (base) { base.querySelector('.faccv-fpago').value = ''; base.querySelector('.faccv-vpago').value = '0.00'; }
            return;
        }
        pagos.forEach((p, i) => {
            if (i === 0 && base) {
                base.querySelector('.faccv-fpago').value = p.forma_pago || '';
                base.querySelector('.faccv-vpago').value = num(p.valor).toFixed(2);
            } else {
                faccvAgregarPago();
                const all = cont.querySelectorAll('.faccv-row-pago');
                const row = all[all.length - 1];
                row.querySelector('.faccv-fpago').value = p.forma_pago || '';
                row.querySelector('.faccv-vpago').value = num(p.valor).toFixed(2);
            }
        });
    }

    function faccvSelCliente(c) {
        $('faccv_id_cliente').value = c.id;
        $('faccv_cliente_email').value = c.email || '';
        $('faccv_cliente_busqueda').value = (c.identificacion || '') + ' — ' + (c.nombre || '');
        $('faccv_clientes_dd').classList.add('d-none');
        pintarInfoCliente(c);
        // Vendedor del cliente.
        const selV = $('faccv_id_vendedor');
        if (c.id_vendedor && selV && selV.querySelector(`option[value="${c.id_vendedor}"]`)) selV.value = c.id_vendedor;
        // Correo del cliente → info adicional.
        faccvInfoCorreo(c.email || '');
        // Días de crédito del cliente.
        if (c.plazo !== undefined && c.plazo !== null && $('faccv_dias_credito')) $('faccv_dias_credito').value = parseInt(c.plazo, 10) || 0;
        // Forma de pago: la del cliente o la configurada por defecto.
        const idFp = c.id_forma_pago_sri || (window.EMPRESA_CONFIG && EMPRESA_CONFIG.id_forma_pago_sri_def) || null;
        faccvAplicarFormaPago(idFp);
    }

    // ── Sub-modal "Cargar consignación" ─────────────────────────────────────
    let cgModal = null, tCg = null, cgLineas = {}, cgClienteId = null;
    function getCgModal() { if (!cgModal) cgModal = new bootstrap.Modal(document.getElementById('modalFaccvCargar')); return cgModal; }

    // Manejo de modal anidado: apilar por encima del modal principal y, al cerrar,
    // restaurar el body y quitar backdrops sobrantes (si no, un backdrop bloquea la edición).
    (function () {
        const cgEl = document.getElementById('modalFaccvCargar');
        if (!cgEl) return;
        cgEl.addEventListener('shown.bs.modal', () => {
            const abiertos = document.querySelectorAll('.modal.show').length;
            const z = 1055 + abiertos * 20;
            cgEl.style.zIndex = z;
            const bds = document.querySelectorAll('.modal-backdrop');
            if (bds.length) bds[bds.length - 1].style.zIndex = z - 5;
        });
        cgEl.addEventListener('hidden.bs.modal', () => {
            const abiertos = document.querySelectorAll('.modal.show').length;
            if (abiertos > 0) {
                document.body.classList.add('modal-open');
                const bds = document.querySelectorAll('.modal-backdrop');
                for (let i = bds.length - 1; i >= abiertos; i--) bds[i].remove();
            }
        });
    })();

    window.faccvAbrirCargar = function () {
        $('faccv_cg_busqueda').value = '';
        $('faccv_cg_dd').classList.add('d-none');
        $('faccv_cg_consinfo').textContent = '';
        $('faccv_cg_body').innerHTML = '<tr><td colspan="11" class="text-center text-muted py-4">Busque una consignación por cliente o número.</td></tr>';
        $('faccv_cg_info').textContent = '';
        cgLineas = {};
        getCgModal().show();
        setTimeout(() => $('faccv_cg_busqueda').focus(), 350);
    };

    window.faccvCgBuscar = function (q) {
        clearTimeout(tCg); const dd = $('faccv_cg_dd');
        if (!q || q.length < 2) { dd.classList.add('d-none'); return; }
        tCg = setTimeout(async () => {
            const res = await fetch(`${RUTA}/buscarConsignacionesAjax?q=${encodeURIComponent(q)}`); const data = await res.json();
            dd.innerHTML = '';
            (data.data || []).forEach(c => {
                const numero = (c.serie || '') + '-' + (c.secuencial || '');
                const a = document.createElement('a'); a.href = '#'; a.className = 'list-group-item list-group-item-action py-1 small';
                a.innerHTML = `<span class="fw-bold text-primary">${esc(numero)}</span> <span class="ms-1">${esc(c.cliente_nombre)}</span> <span class="text-muted">${c.cliente_identificacion ? '· ' + esc(c.cliente_identificacion) : ''}</span>`;
                a.onclick = (ev) => { ev.preventDefault(); faccvCgSelect(c.id_consignacion, numero, c.cliente_nombre, c.id_cliente); };
                dd.appendChild(a);
            });
            if (!data.data || !data.data.length) dd.innerHTML = '<span class="list-group-item small text-muted">Sin consignaciones con saldo.</span>';
            dd.classList.remove('d-none');
        }, 300);
    };

    async function faccvCgSelect(idCons, numero, cliente, idCliente) {
        cgClienteId = idCliente || null;
        $('faccv_cg_dd').classList.add('d-none');
        $('faccv_cg_busqueda').value = numero + ' · ' + cliente;
        $('faccv_cg_consinfo').innerHTML = '<i class="bi bi-box-seam me-1"></i> Consignación <b>' + esc(numero) + '</b> — ' + esc(cliente);
        const body = $('faccv_cg_body');
        body.innerHTML = '<tr><td colspan="11" class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>';
        const res = await fetch(`${RUTA}/getLineasFacturablesAjax?id=${idCons}`); const data = await res.json();
        const lineas = (data.ok && data.data) ? data.data : [];
        if (!lineas.length) { body.innerHTML = '<tr><td colspan="11" class="text-center text-muted py-4">Sin saldo facturable.</td></tr>'; $('faccv_cg_info').textContent = ''; return; }
        body.innerHTML = lineas.map(l => {
            const idcd = parseInt(l.id_consignacion_detalle, 10);
            cgLineas[idcd] = { idcd, numero, producto_codigo: l.producto_codigo, producto_nombre: l.producto_nombre, lote: l.lote, nup: l.nup, bodega_nombre: l.bodega_nombre, porc: num(l.porcentaje_impuesto), saldo: num(l.saldo_facturable) };
            const ya = added.has(idcd);
            const precioCons = num(l.precio_unitario), saldo = num(l.saldo_facturable);
            let opts = `<option value="${precioCons}">P. Consignación ($${precioCons.toFixed(2)})</option>`;
            (l.precios_lista || []).forEach(pl => { opts += `<option value="${num(pl.precio)}">${esc(pl.nombre_precio)} ($${num(pl.precio).toFixed(2)})</option>`; });
            return `<tr data-idcd="${idcd}" class="${ya ? 'table-warning' : ''}">
                <td class="text-center"><input type="checkbox" class="cg-chk" ${ya ? 'disabled title="Ya agregado"' : 'checked'}></td>
                <td class="small">${esc(l.producto_codigo || '—')}</td>
                <td class="small">${esc(l.producto_nombre || '')}</td>
                <td class="text-end small">${saldo.toFixed(2)}</td>
                <td class="small">${esc(l.lote && l.lote !== 'sin_lote' ? l.lote : '—')}</td>
                <td class="small">${esc(l.nup || '—')}</td>
                <td><select class="form-select form-select-sm cg-precios" style="min-width:130px;font-size:.78rem;" onchange="faccvCgPrecioSel(this)" ${ya ? 'disabled' : ''}>${opts}</select></td>
                <td><input type="number" class="form-control form-control-sm text-end cg-precio" style="width:80px;font-size:.78rem;" value="${precioCons.toFixed(DEC_P)}" min="0" step="any" oninput="faccvCgRowCalc(this)" ${ya ? 'disabled' : ''}></td>
                <td><input type="number" class="form-control form-control-sm text-end cg-cant" style="width:70px;font-size:.78rem;" value="${saldo}" min="0" max="${saldo}" step="any" oninput="faccvCgRowCalc(this)" ${ya ? 'disabled' : ''}></td>
                <td><input type="number" class="form-control form-control-sm text-end cg-desc" style="width:70px;font-size:.78rem;" value="0.00" min="0" step="any" oninput="faccvCgRowCalc(this)" ${ya ? 'disabled' : ''}></td>
                <td class="text-end small pe-2 cg-subtotal">0.00</td>
            </tr>`;
        }).join('');
        document.querySelectorAll('#faccv_cg_body .cg-cant').forEach(inp => faccvCgRowCalc(inp));
        $('faccv_cg_info').textContent = lineas.length + ' producto(s) con saldo';
    }

    window.faccvCgPrecioSel = function (sel) {
        const tr = sel.closest('tr'); tr.querySelector('.cg-precio').value = num(sel.value).toFixed(DEC_P); faccvCgRowCalc(tr.querySelector('.cg-precio'));
    };
    window.faccvCgRowCalc = function (inp) {
        const tr = inp.closest('tr'); const meta = cgLineas[parseInt(tr.dataset.idcd, 10)]; if (!meta) return;
        const saldo = num(meta.saldo);
        const cantEl = tr.querySelector('.cg-cant'), precioEl = tr.querySelector('.cg-precio'), descEl = tr.querySelector('.cg-desc');
        let cant = num(cantEl.value); if (cant < 0) cant = 0; if (cant > saldo) { cant = saldo; cantEl.value = saldo; }
        let precio = num(precioEl.value); if (precio < 0) { precio = 0; precioEl.value = 0; }
        const bruto = precio * cant;
        let desc = num(descEl.value); if (desc < 0) desc = 0; if (desc > bruto) { desc = bruto; descEl.value = bruto.toFixed(2); }
        tr.querySelector('.cg-subtotal').textContent = (bruto - desc).toFixed(2);
    };
    window.faccvCgToggleAll = function (chk) {
        document.querySelectorAll('#faccv_cg_body .cg-chk:not(:disabled)').forEach(c => c.checked = chk.checked);
    };
    window.faccvCgAgregar = function () {
        let agregadas = 0;
        document.querySelectorAll('#faccv_cg_body tr[data-idcd]').forEach(tr => {
            const chk = tr.querySelector('.cg-chk');
            if (!chk || !chk.checked || chk.disabled) return;
            const idcd = parseInt(tr.dataset.idcd, 10); const meta = cgLineas[idcd]; if (!meta) return;
            const cant = num(tr.querySelector('.cg-cant').value); if (cant <= 0) return;
            const precio = num(tr.querySelector('.cg-precio').value);
            const desc = num(tr.querySelector('.cg-desc').value);
            const ok = addLinea({ idcd, numero: meta.numero, producto_codigo: meta.producto_codigo, producto_nombre: meta.producto_nombre, lote: meta.lote, nup: meta.nup, bodega_nombre: meta.bodega_nombre, precio, saldo: meta.saldo, cantidad: cant, descuento: desc, porc: meta.porc, readonly: false });
            if (ok) agregadas++;
        });
        recalc();
        getCgModal().hide();
        // Si el documento aún no tiene cliente, tomar el de la consignación (con su vendedor,
        // días de crédito, forma de pago y correo en info adicional).
        if (agregadas > 0 && !$('faccv_id_cliente').value && cgClienteId) {
            fetch(`${RUTA}/getClienteAjax?id=${cgClienteId}`)
                .then(r => r.json())
                .then(d => { if (d.ok && d.data) faccvSelCliente(d.data); })
                .catch(() => {});
        }
        if (agregadas === 0) Swal.fire('Info', 'No se agregó ningún producto (marque al menos uno con cantidad > 0).', 'info');
    };

    // cfg: {idcd, numero, producto_codigo, producto_nombre, lote, nup, bodega_nombre, precio, saldo, cantidad, descuento, porc, readonly}
    // Los ítems agregados NO se editan aquí (precio/cantidad/descuento se definen en el
    // sub-modal "Cargar consignación"). Se muestran como valores fijos; para cambiarlos
    // se quita la línea y se vuelve a cargar. Los valores viven en data-* de la fila.
    function addLinea(cfg) {
        const idcd = parseInt(cfg.idcd, 10);
        if (added.has(idcd)) return false;
        added.add(idcd);
        const body = $('faccv_lineas_body');
        if (body.querySelector('td[colspan]')) body.innerHTML = '';
        const loteNup = [cfg.lote, cfg.nup].filter(x => x && x !== 'sin_lote').join(' / ') || '—';
        const precio = num(cfg.precio), porc = num(cfg.porc), saldo = num(cfg.saldo), cant = num(cfg.cantidad), desc = num(cfg.descuento);
        const neto = Math.max(0, precio * cant - desc);
        const tr = document.createElement('tr'); tr.className = 'row-detalle';
        tr.dataset.idcd = idcd; tr.dataset.porc = porc; tr.dataset.saldo = saldo;
        tr.dataset.precio = precio; tr.dataset.cant = cant; tr.dataset.desc = desc;
        tr.innerHTML = `
            <td class="ps-3 small">${esc(cfg.numero || '')}</td>
            <td class="small">${cfg.producto_codigo ? esc(cfg.producto_codigo) + ' · ' : ''}${esc(cfg.producto_nombre || '')}</td>
            <td class="small">${esc(loteNup)}</td>
            <td class="small">${esc(cfg.bodega_nombre || '—')}</td>
            <td class="text-end small">${saldo.toFixed(2)}</td>
            <td class="text-end small">${precio.toFixed(DEC_P)}</td>
            <td class="text-end small">${cant.toFixed(2)}</td>
            <td class="text-end small">${desc.toFixed(2)}</td>
            <td class="text-center small">${fmtPct(porc)}</td>
            <td class="text-end small pe-3 fw-semibold">${neto.toFixed(2)}</td>
            <td class="text-center">${cfg.readonly ? '' : `<i class="bi bi-x-circle remove-row" role="button" onclick="faccvQuitar(this)" title="Quitar"></i>`}</td>`;
        body.appendChild(tr);
        $('faccv_lineas_info').textContent = added.size + ' línea(s)';
        return true;
    }

    window.faccvQuitar = function (btn) {
        const tr = btn.closest('tr'); added.delete(parseInt(tr.dataset.idcd, 10)); tr.remove();
        if (!$('faccv_lineas_body').children.length) $('faccv_lineas_body').innerHTML = '<tr><td colspan="11" class="text-center text-muted py-4">Use <b>Cargar consignación</b> para agregar productos.</td></tr>';
        $('faccv_lineas_info').textContent = added.size ? added.size + ' línea(s)' : '';
        recalc();
    };

    function fmtPct(p) { p = num(p); return (p % 1 === 0 ? p.toFixed(0) : p.toFixed(2)) + '%'; }

    function recalc() {
        let bruto = 0, desc = 0;
        const grupos = {}; // pct -> { pct, base(net), iva }
        document.querySelectorAll('#faccv_lineas_body tr[data-idcd]').forEach(tr => {
            const cant = num(tr.dataset.cant), precio = num(tr.dataset.precio), d = num(tr.dataset.desc), pct = num(tr.dataset.porc);
            const b = precio * cant; const net = Math.max(0, b - d);
            bruto += b; desc += d;
            const key = pct.toFixed(2);
            if (!grupos[key]) grupos[key] = { pct, base: 0, iva: 0 };
            grupos[key].base += net;
            grupos[key].iva += net * pct / 100;
        });
        const lista = Object.values(grupos).sort((a, b) => a.pct - b.pct);
        let ivaTotal = 0; lista.forEach(g => ivaTotal += g.iva);

        $('faccv_tot_subtotal').textContent = bruto.toFixed(2);

        // Subtotales netos por tarifa de IVA (igual que factura).
        const cs = $('faccv_tot_sub_grupos'); cs.innerHTML = '';
        lista.forEach(g => {
            const div = document.createElement('div');
            div.className = 'd-flex justify-content-between align-items-center mb-1 text-muted';
            div.innerHTML = `<span>Subtotal ${fmtPct(g.pct)}</span><span>${g.base.toFixed(2)}</span>`;
            cs.appendChild(div);
        });

        $('faccv_tot_desc').textContent = desc.toFixed(2);

        // IVA por tarifa (solo tarifas > 0 con valor > 0).
        const ci = $('faccv_tot_iva_grupos'); ci.innerHTML = '';
        lista.forEach(g => {
            if (g.pct > 0 && g.iva > 0.0001) {
                const div = document.createElement('div');
                div.className = 'd-flex justify-content-between align-items-center mb-1';
                div.innerHTML = `<span class="text-muted">(+) IVA ${fmtPct(g.pct)}</span><span>${g.iva.toFixed(2)}</span>`;
                ci.appendChild(div);
            }
        });

        $('faccv_tot_total').textContent = (bruto - desc + ivaTotal).toFixed(2);
        $('faccv_count_items').textContent = added.size;
    }

    document.addEventListener('click', (e) => {
        if (!e.target.closest('#faccv_cliente_busqueda') && !e.target.closest('#faccv_clientes_dd')) $('faccv_clientes_dd').classList.add('d-none');
        if (!e.target.closest('#faccv_cg_busqueda') && !e.target.closest('#faccv_cg_dd')) { const dd = $('faccv_cg_dd'); if (dd) dd.classList.add('d-none'); }
    });

    // ── Info. Adicional ──────────────────────────────────────────────────────
    window.faccvAgregarInfo = function (concepto, detalle, readonly) {
        const tb = $('faccv_tbody_info');
        const tr = document.createElement('tr'); tr.className = 'row-faccv-info';
        const dis = readonly ? 'readonly' : '';
        tr.innerHTML = `
            <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent input-info-concepto" style="padding:0 4px;height:22px;font-size:0.78rem;" placeholder="Concepto..." value="${esc(concepto || '')}" ${dis}></td>
            <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent input-info-detalle" style="padding:0 4px;height:22px;font-size:0.78rem;" placeholder="Detalle..." value="${esc(detalle || '')}" ${dis}></td>
            <td class="p-0 text-center pe-1">${readonly ? '' : `<button type="button" class="btn btn-link btn-sm p-0 m-0 text-danger shadow-none" onclick="this.closest('tr').remove();"><i class="bi bi-x-circle-fill"></i></button>`}</td>`;
        tb.appendChild(tr);
        if (!readonly) tr.querySelector('.input-info-concepto').focus();
    };
    function collectInfo() {
        const out = [];
        document.querySelectorAll('#faccv_tbody_info .row-faccv-info').forEach(tr => {
            const nombre = (tr.querySelector('.input-info-concepto')?.value || '').trim();
            const valor  = (tr.querySelector('.input-info-detalle')?.value || '').trim();
            if (nombre && valor) out.push({ nombre, valor });
        });
        return out;
    }

    // ── Guardar / Generar / Eliminar / Anular ────────────────────────────────
    window.faccvGuardar = async function () {
        if (!$('faccv_id_cliente').value) { Swal.fire('Atención', 'Seleccione el cliente a facturar.', 'warning'); return; }
        if (!$('faccv_secuencial').value) { Swal.fire('Atención', 'Falta el secuencial. Configure el punto de emisión.', 'warning'); return; }
        const detalles = [];
        document.querySelectorAll('#faccv_lineas_body tr[data-idcd]').forEach(tr => {
            const c = num(tr.dataset.cant);
            if (c > 0) detalles.push({
                id_consignacion_detalle: parseInt(tr.dataset.idcd, 10),
                cantidad: c,
                precio_unitario: num(tr.dataset.precio),
                descuento: num(tr.dataset.desc)
            });
        });
        if (!detalles.length) { Swal.fire('Atención', 'Indique la cantidad a facturar en al menos una línea.', 'warning'); return; }
        const payload = {
            id: $('faccv_id').value || null, fecha_emision: $('faccv_fecha').value,
            serie: $('faccv_serie').value, secuencial: $('faccv_secuencial').dataset.sec || $('faccv_secuencial').value,
            id_punto_emision: $('faccv_id_punto_emision').value, establecimiento: $('faccv_establecimiento').value, punto_emision: $('faccv_punto_emision').value,
            id_cliente: parseInt($('faccv_id_cliente').value, 10), id_vendedor: $('faccv_id_vendedor').value || null,
            observaciones: $('faccv_observaciones').value, info_adicional: collectInfo(),
            dias_credito: parseInt($('faccv_dias_credito').value, 10) || 0,
            plazo_unidad: ($('faccv_plazo_unidad') && $('faccv_plazo_unidad').value) || 'dias',
            pagos_sri: collectPagos(),
            detalles
        };
        const btn = $('faccv_btn_guardar'); const orig = btn.innerHTML;
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';
        try {
            const res = await fetch(`${RUTA}/store`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'Error al guardar');
            // NO se cierra el modal: se recarga el documento guardado dentro del modal
            // (queda en modo edición con su id; aparece "Generar factura"). Igual que factura de venta.
            const idGuardado = parseInt(data.id, 10) || parseInt($('faccv_id').value, 10) || 0;
            if (typeof cargarGrid === 'function') cargarGrid();
            if (idGuardado > 0) {
                await abrirModalFacturacionVer({ dataset: { row: JSON.stringify({ id: idGuardado, estado: 'borrador' }) } });
            }
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.msg || 'Guardado', showConfirmButton: false, timer: 2500, timerProgressBar: true });
        } catch (err) { Swal.fire('Error', err.message, 'error'); }
        finally { btn.disabled = false; btn.innerHTML = orig; }
    };

    window.faccvGenerar = async function () {
        const id = $('faccv_id').value;
        if (!id) { Swal.fire('Atención', 'Guarde el documento primero.', 'warning'); return; }
        const c = await Swal.fire({ title: '¿Generar factura?', html: 'Se reingresará la mercadería al inventario y se generará una <b>Factura de Venta</b> al cliente del documento.', icon: 'question', showCancelButton: true, confirmButtonText: 'Sí, generar', cancelButtonText: 'Cancelar' });
        if (!c.isConfirmed) return;
        const btn = $('faccv_btn_generar'); const orig = btn.innerHTML;
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Generando...';
        try {
            const fd = new FormData(); fd.append('id', id);
            const res = await fetch(`${RUTA}/generarFacturaAjax`, { method: 'POST', body: fd }); const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'No se pudo generar la factura.');
            getModal().hide(); await Swal.fire({ icon: 'success', title: 'Factura generada', text: data.msg, timer: 2400, showConfirmButton: false });
            if (typeof cargarGrid === 'function') cargarGrid();
        } catch (err) { Swal.fire('Error', err.message, 'error'); }
        finally { btn.disabled = false; btn.innerHTML = orig; }
    };

    window.faccvDuplicar = async function () {
        const id = $('faccv_id').value; if (!id) return;
        const c = await Swal.fire({ title: '¿Crear nueva facturación?', html: 'Se creará un <b>nuevo borrador</b> copiando el cliente, las formas de pago y las líneas de este documento. Las cantidades se ajustan al saldo facturable disponible.', icon: 'question', showCancelButton: true, confirmButtonText: 'Sí, crear', cancelButtonText: 'Cancelar' });
        if (!c.isConfirmed) return;
        const btn = $('faccv_btn_duplicar'); const orig = btn.innerHTML;
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Creando...';
        try {
            const fd = new FormData(); fd.append('id', id);
            const res = await fetch(`${RUTA}/duplicarAjax`, { method: 'POST', body: fd }); const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'No se pudo crear la facturación.');
            if (typeof cargarGrid === 'function') cargarGrid();
            await Swal.fire({ icon: 'success', title: 'Borrador creado', text: data.msg, timer: 1800, showConfirmButton: false });
            await abrirModalFacturacionVer({ dataset: { row: JSON.stringify({ id: data.id, estado: 'borrador' }) } });
        } catch (err) { Swal.fire('Error', err.message, 'error'); }
        finally { btn.disabled = false; btn.innerHTML = orig; }
    };

    window.faccvEliminar = async function () {
        const id = $('faccv_id').value; if (!id) return;
        const c = await Swal.fire({ title: '¿Eliminar documento?', text: 'Se eliminará el borrador.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545' });
        if (!c.isConfirmed) return;
        try {
            const fd = new FormData(); fd.append('id', id);
            const res = await fetch(`${RUTA}/eliminar`, { method: 'POST', body: fd }); const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'Error');
            getModal().hide(); await Swal.fire('Eliminado', data.msg, 'success');
            if (typeof cargarGrid === 'function') cargarGrid();
        } catch (err) { Swal.fire('Error', err.message, 'error'); }
    };

    // ── Acciones documento ───────────────────────────────────────────────────
    window.faccvPdf = function () {
        const id = $('faccv_id').value; if (!id) return Swal.fire('Atención', 'Guarde el documento primero.', 'warning');
        const a = document.createElement('a'); a.href = `${RUTA}/pdf?id=${id}`; document.body.appendChild(a); a.click(); a.remove();
    };
    window.faccvEmail = async function () {
        const id = $('faccv_id').value; if (!id) return Swal.fire('Atención', 'Guarde el documento primero.', 'warning');
        const { value: correos, isConfirmed } = await Swal.fire({ title: 'Enviar por correo', input: 'text', inputLabel: 'Correo(s) destino, separados por coma.', inputValue: $('faccv_cliente_email').value || '', target: document.getElementById('modalFacturacionCv'), showCancelButton: true, confirmButtonText: 'Enviar', cancelButtonText: 'Cancelar' });
        if (!isConfirmed) return;
        Swal.fire({ title: 'Enviando...', allowOutsideClick: false, target: document.getElementById('modalFacturacionCv'), didOpen: () => Swal.showLoading() });
        try {
            const fd = new FormData(); fd.append('id', id); fd.append('correos', correos || '');
            const res = await fetch(`${RUTA}/enviarCorreoAjax`, { method: 'POST', body: fd }); const data = await res.json();
            Swal.fire(data.ok ? 'Enviado' : 'Error', data.mensaje || '', data.ok ? 'success' : 'error');
        } catch (e) { Swal.fire('Error', 'No se pudo enviar el correo.', 'error'); }
    };
    window.faccvWhatsapp = function () {
        if (!$('faccv_id').value) return Swal.fire('Atención', 'Guarde el documento primero.', 'warning');
        Swal.fire('Info', 'Enviando por WhatsApp...', 'info');
    };
    window.faccvNuevoCliente = function () {
        if (typeof window.abrirModalClienteCrear === 'function') window.abrirModalClienteCrear();
        else Swal.fire('Info', 'Abra el módulo Clientes para registrar uno nuevo.', 'info');
    };

    // ── Pestaña Asiento contable (reversa de la consignación, a costo) ────────
    function resetAsiento() {
        $('faccv-tbody-asiento').innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">Guarde el documento para ver el asiento (se calcula a costo).</td></tr>';
        $('faccv-asiento-total-debe').textContent = '0.00';
        $('faccv-asiento-total-haber').textContent = '0.00';
        const b = $('faccv-asiento-badge'); b.textContent = '—'; b.className = 'badge bg-secondary bg-opacity-10 text-secondary border px-2';
    }
    async function cargarAsiento() {
        const id = $('faccv_id').value; const tb = $('faccv-tbody-asiento');
        if (!id) { resetAsiento(); return; }
        tb.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">Cargando asiento...</td></tr>';
        try {
            const res = await fetch(`${RUTA}/getAsientoAjax?id=${id}`); const data = await res.json();
            if (data.ok && data.anulado) {
                tb.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted"><i class="bi bi-x-octagon me-1"></i> Documento anulado: el asiento de reingreso fue reversado.</td></tr>';
                $('faccv-asiento-total-debe').textContent = '0.00'; $('faccv-asiento-total-haber').textContent = '0.00';
                const bA = $('faccv-asiento-badge'); bA.textContent = '—'; bA.className = 'badge bg-secondary bg-opacity-10 text-secondary border px-2';
                return;
            }
            const dets = (data.ok && data.detalles) ? data.detalles : [];
            if (!dets.length) { tb.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">Sin asiento: el documento no tiene costo registrado o las cuentas de consignación no están configuradas.</td></tr>'; return; }
            let tD = 0, tH = 0;
            tb.innerHTML = dets.map(d => {
                tD += num(d.debe); tH += num(d.haber);
                const cuenta = (d.id_cuenta_contable > 0) ? `${d.cuenta_codigo ? esc(d.cuenta_codigo) + ' · ' : ''}${esc(d.cuenta_nombre || '')}` : '<span class="text-danger">— Cuenta sin configurar —</span>';
                return `<tr><td class="ps-3 small">${cuenta}</td><td class="text-end pe-3 small">${num(d.debe) ? num(d.debe).toFixed(2) : ''}</td><td class="text-end pe-3 small">${num(d.haber) ? num(d.haber).toFixed(2) : ''}</td><td class="small text-muted">${esc(d.referencia_detalle || '')}</td></tr>`;
            }).join('');
            $('faccv-asiento-total-debe').textContent = tD.toFixed(2);
            $('faccv-asiento-total-haber').textContent = tH.toFixed(2);
            const cuadrado = Math.abs(tD - tH) < 0.005;
            const b = $('faccv-asiento-badge');
            b.textContent = cuadrado ? 'Cuadrado' : 'Descuadrado';
            b.className = 'badge px-2 ' + (cuadrado ? 'bg-success bg-opacity-10 text-success border border-success border-opacity-25' : 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25');
        } catch (e) { tb.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-danger">Error al cargar el asiento.</td></tr>'; }
    }
    const _faccvAsientoBtn = document.getElementById('faccv-tab-asiento-btn');
    if (_faccvAsientoBtn) _faccvAsientoBtn.addEventListener('shown.bs.tab', cargarAsiento);

    document.addEventListener('clienteGuardado', async (ev) => {
        let ident = '';
        if (ev.detail && ev.detail.data && ev.detail.data.identificacion) ident = String(ev.detail.data.identificacion).trim();
        else { const f = $('cliente_identificacion'); ident = f ? f.value.trim() : ''; }
        if (!ident) return;
        try {
            const res = await fetch(`${RUTA}/getClientesAjax?q=${encodeURIComponent(ident)}`); const data = await res.json();
            const lista = data.data || []; const match = lista.find(c => String(c.identificacion || '') === ident) || lista[0];
            if (match) faccvSelCliente(match);
        } catch (e) {}
    });
})();
</script>
