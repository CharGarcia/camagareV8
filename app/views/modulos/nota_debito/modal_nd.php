<?php

/** @var array $perm */
/** @var string $rutaModulo */
/** @var array $vistaConfig */

$vistaConfigND = \App\Helpers\PreferenciasHelper::getPreferenciasVista('nota_debito');
?>
<!-- Modal para Nueva/Editar Nota de Débito -->
<div class="modal fade modal-nd" id="modalND" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title fs-6 fw-bold" id="modalNDTitulo">
                    <i class="bi bi-file-earmark-plus text-primary me-2"></i>Nueva Nota de Débito
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <form id="formND">
                    <input type="hidden" name="id" id="nd_id">

                    <!-- Barra de Acciones Superior -->
                    <div class="px-3 py-2 bg-light border-bottom d-flex gap-1 align-items-center flex-wrap">
                        <button id="nd-btn-sri" type="button" class="btn btn-outline-primary btn-sm" onclick="window.ND_enviarSRI()" disabled><i class="bi bi-cloud-arrow-up me-1"></i>Enviar al SRI</button>
                        <div class="vr mx-1"></div>
                        <button id="nd-btn-pdf" type="button" class="btn btn-outline-danger btn-sm px-2" onclick="window.ND_exportarPdf()" title="Exportar PDF" disabled><i class="bi bi-file-earmark-pdf"></i></button>
                        <button id="nd-btn-xml" type="button" class="btn btn-outline-success btn-sm px-2" onclick="window.ND_exportarXml()" title="Exportar XML" disabled><i class="bi bi-file-earmark-code"></i></button>
                        <button id="nd-btn-correo" type="button" class="btn btn-outline-info btn-sm px-2" onclick="window.ND_enviarPorCorreo()" title="Enviar por correo" disabled><i class="bi bi-envelope"></i></button>
                        <button id="btnAnularND" type="button" class="btn btn-outline-warning btn-sm px-2 d-none" onclick="window.ND_anular()" title="Anular"><i class="bi bi-slash-circle me-1"></i>Anular</button>
                        <div class="vr mx-1"></div>
                        <button type="button" class="btn btn-outline-primary btn-sm px-2 nd-edit-only" onclick="window.ND_abrirModalClienteCrear()" title="Registrar nuevo cliente"><i class="bi bi-person-plus fs-6"></i></button>
                    </div>

                    <!-- Pestañas -->
                    <div class="d-flex align-items-center bg-light px-3 pt-2">
                        <ul class="nav nav-tabs border-bottom-0 flex-grow-1 tab-pestaña" id="ndTabs" role="tablist">
                            <li class="nav-item"><a class="nav-link active py-2 small" id="tab-nd-principal-btn" data-bs-toggle="tab" href="#tab-nd-principal" role="tab" style="white-space: nowrap;"><i class="bi bi-file-earmark-plus me-1"></i> Nota de débito</a></li>
                            <li class="nav-item"><a class="nav-link py-2 small" id="tab-nd-factura-btn" data-bs-toggle="tab" href="#tab-nd-factura" role="tab" style="white-space: nowrap;"><i class="bi bi-receipt me-1"></i> Factura relacionada</a></li>
                            <li class="nav-item"><a class="nav-link py-2 small" id="tab-nd-contable-btn" data-bs-toggle="tab" href="#tab-nd-contable" role="tab" style="white-space: nowrap;"><i class="bi bi-calculator me-1"></i> Asiento contable</a></li>
                            <li class="nav-item"><a class="nav-link py-2 small" id="tab-nd-sri-btn" data-bs-toggle="tab" href="#tab-nd-sri" role="tab" style="white-space: nowrap;"><i class="bi bi-cloud-check me-1"></i> SRI</a></li>
                        </ul>
                        <div class="ms-auto pb-1">
                            <?php
                            $pestanasConfigND = [
                                'tab-nd-factura'  => 'Factura relacionada',
                                'tab-nd-contable' => 'Asiento contable',
                                'tab-nd-sri'      => 'SRI'
                            ];
                            echo \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasConfigND, $vistaConfigND ?? [], 'nota_debito');
                            ?>
                        </div>
                    </div>

                    <div class="tab-content border-top">
                        <!-- Pestaña Principal: Nota de Débito -->
                        <div class="tab-pane fade show active" id="tab-nd-principal" role="tabpanel">
                            <div class="p-3 bg-white border-bottom">
                                <div class="row g-2">
                                    <div class="col-12">
                                        <div class="row g-2 align-items-end">
                                            <!-- 1. Fecha -->
                                            <div class="col-md-3">
                                                <label class="x-small fw-bold text-muted mb-1">Fecha Emisión</label>
                                                <input type="date" name="fecha_emision" id="nd_fecha_emision" class="form-control form-control-sm border-primary border-opacity-10 py-0" style="height: 31px;" value="<?= date('Y-m-d') ?>">
                                            </div>
                                            <!-- 2. Serie -->
                                            <div class="col-md-3">
                                                <label class="x-small fw-bold text-muted mb-1 d-flex align-items-center">Serie <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('nota_debito', 'nd_id_punto_emision', 'id_punto_emision') ?></label>
                                                <select name="id_punto_emision" id="nd_id_punto_emision" class="form-select form-select-sm border-primary border-opacity-25" onchange="window.ND_cargarSecuencial()" style="height: 31px;">
                                                    <?php foreach ($puntos as $p): ?>
                                                        <option value="<?= $p['id'] ?>" data-cod-est="<?= $p['cod_establecimiento'] ?>" data-cod-punto="<?= $p['codigo_punto'] ?>">
                                                            <?= $p['cod_establecimiento'] ?>-<?= $p['codigo_punto'] ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <!-- 3. Secuencial -->
                                            <div class="col-md-3">
                                                <label class="x-small fw-bold text-muted mb-1">Secuencial</label>
                                                <input type="text" name="secuencial" id="nd_secuencial" class="form-control form-control-sm border-primary border-opacity-25 text-center py-0 bg-light" style="height: 31px;" placeholder="000000001" readonly>
                                            </div>
                                            <!-- 4. Tarifa IVA -->
                                            <div class="col-md-3">
                                                <label class="x-small fw-bold text-muted mb-1">Tarifa IVA</label>
                                                <select id="nd_tarifa_iva" class="form-select form-select-sm border-primary border-opacity-10" style="height: 31px;" onchange="window.ND_calcTotales()"></select>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Cliente (se selecciona primero) -->
                                    <div class="col-12 mt-2">
                                        <div class="p-2 border rounded-3 bg-light bg-opacity-10">
                                            <div class="row g-2 align-items-center">
                                                <div class="col-md-12 position-relative">
                                                    <label class="x-small fw-bold text-muted mb-1">1. Seleccione el cliente</label>
                                                    <div class="input-group input-group-sm flex-grow-1 elevation-1 rounded-pill overflow-hidden border">
                                                        <span class="input-group-text bg-white border-0 text-primary"><i class="bi bi-search"></i></span>
                                                        <input type="text" class="form-control border-0 px-1" id="nd_cliente_search" placeholder="Buscar cliente por RUC o Razón Social..." autocomplete="off">
                                                        <input type="hidden" name="id_cliente" id="nd_id_cliente">
                                                    </div>
                                                    <div id="nd_cliente_dropdown" class="list-group shadow dropdown-predictivo position-absolute d-none" style="z-index: 1050; width: 100%; max-height: 250px; overflow-y: auto; right: 0px; top: 55px;"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Documento Modificado (depende del cliente) -->
                                    <div class="col-12 mt-2">
                                        <div class="p-2 border rounded-3 bg-white shadow-sm border-primary border-opacity-10">
                                            <div class="row g-2 align-items-end">
                                                <div class="col-md-5 position-relative">
                                                    <label class="x-small fw-bold text-muted mb-1">2. Factura / Documento a modificar</label>
                                                    <div class="input-group input-group-sm rounded-pill overflow-hidden border border-primary border-opacity-25">
                                                        <span class="input-group-text bg-white border-0 text-primary"><i class="bi bi-file-earmark-text"></i></span>
                                                        <input type="text" class="form-control border-0 px-1" id="nd_factura_search" name="num_doc_modificado" placeholder="Seleccione un cliente primero..." autocomplete="off" maxlength="17" inputmode="numeric" disabled>
                                                    </div>
                                                    <div id="nd_factura_dropdown" class="list-group shadow dropdown-predictivo position-absolute d-none" style="z-index: 1050; width: 100%; max-height: 250px; overflow-y: auto; right: 0px; top: 55px;"></div>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="x-small fw-bold text-muted mb-1">Fecha del documento</label>
                                                    <input type="date" name="fecha_emision_docs_sustento" id="nd_fecha_emision_docs_sustento" class="form-control form-control-sm border-primary border-opacity-25 py-0" style="height: 31px;" disabled>
                                                </div>
                                                <div class="col-md-4">
                                                    <div id="nd_info_factura_modificada" class="small text-muted"></div>
                                                </div>
                                            </div>
                                            <div class="x-small text-muted mt-1 ps-1">
                                                <i class="bi bi-info-circle me-1"></i>Elija una factura del cliente o escriba el número manualmente si no figura en el listado.
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Info Detallada Cliente -->
                                    <div id="nd_info_cliente" class="col-12 mt-2 d-none">
                                        <div class="p-2 border rounded-3 bg-light bg-opacity-50 border-primary border-opacity-10">
                                            <div class="row g-2 align-items-center">
                                                <div class="col-md-4">
                                                    <div class="d-flex align-items-center gap-1">
                                                        <i class="bi bi-card-text text-muted"></i>
                                                        <span id="nd_lbl_cliente_ruc" class="fw-bold small text-dark"></span>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="d-flex align-items-center gap-1">
                                                        <i class="bi bi-geo-alt text-muted"></i>
                                                        <span id="nd_lbl_cliente_direccion" class="small text-muted text-truncate d-inline-block" style="max-width: 200px;"></span>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="d-flex align-items-center gap-1">
                                                        <i class="bi bi-envelope text-muted"></i>
                                                        <span id="nd_lbl_cliente_correo" class="small text-muted text-truncate d-inline-block" style="max-width: 200px;"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tabla de Motivos -->
                            <div class="p-3">
                                <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                                    <div class="table-responsive" style="max-height: 260px;">
                                        <table class="table table-sm table-detalle mb-0">
                                            <thead>
                                                <tr class="table-light border-bottom">
                                                    <th class="ps-3 py-2 small fw-bold text-muted" style="width: 75%;">Razón de la modificación</th>
                                                    <th class="py-2 small fw-bold text-muted text-end pe-4" style="width: 20%;">Valor</th>
                                                    <th style="width: 40px;"></th>
                                                </tr>
                                            </thead>
                                            <tbody id="nd_motivos_body">
                                                <tr>
                                                    <td colspan="3" class="text-center py-4 text-muted">Agregue al menos un motivo.</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="p-2 border-top bg-light nd-edit-only">
                                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold ms-2" onclick="window.ND_agregarMotivo()">
                                            <i class="bi bi-plus-circle me-1"></i> Agregar motivo
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Tabla de Pagos (opcional) -->
                            <div class="px-3 pb-3">
                                <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                                    <div class="px-2 py-1 bg-light border-bottom">
                                        <span class="x-small fw-bold text-muted"><i class="bi bi-credit-card me-1"></i>Formas de pago (opcional)</span>
                                    </div>
                                    <div class="table-responsive" style="max-height: 180px;">
                                        <table class="table table-sm mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="ps-2 py-1 small fw-bold text-muted" style="width: 40%;">Forma de pago</th>
                                                    <th class="py-1 small fw-bold text-muted text-end" style="width: 20%;">Total</th>
                                                    <th class="py-1 small fw-bold text-muted text-center" style="width: 15%;">Plazo</th>
                                                    <th class="py-1 small fw-bold text-muted" style="width: 15%;">Unidad</th>
                                                    <th style="width: 40px;"></th>
                                                </tr>
                                            </thead>
                                            <tbody id="nd_pagos_body"></tbody>
                                        </table>
                                    </div>
                                    <div class="p-1 border-top bg-light nd-edit-only">
                                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold ms-2" onclick="window.ND_agregarPago()">
                                            <i class="bi bi-plus-circle me-1"></i> Agregar pago
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Pie: Información Adicional (izquierda) + Totales (derecha) -->
                            <div class="p-3 border-top bg-light">
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                                            <div class="px-2 py-1 bg-light border-bottom">
                                                <span class="x-small fw-bold text-muted"><i class="bi bi-info-circle me-1"></i>Información Adicional</span>
                                            </div>
                                            <div class="table-responsive" style="max-height: 160px;">
                                                <table class="table table-sm mb-0">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th class="ps-2 py-0 small fw-bold text-muted" style="width: 40%;">Concepto</th>
                                                            <th class="py-0 small fw-bold text-muted" style="width: 50%;">Detalle</th>
                                                            <th class="py-0" style="width: 10%;"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="nd-tbody-info-adicional"></tbody>
                                                </table>
                                            </div>
                                            <div class="p-1 border-top bg-light nd-edit-only">
                                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold ms-2" onclick="window.ND_agregarInfoAdicional()">
                                                    <i class="bi bi-plus-circle me-1"></i> Agregar línea
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="bg-white border rounded p-2 shadow-sm" style="font-size:0.75rem;">
                                            <div class="d-flex justify-content-between align-items-center mb-1 fw-bold border-bottom pb-1">
                                                <span class="text-muted">Subtotal</span>
                                                <span id="nd_lbl_subtotal">0.00</span>
                                                <input type="hidden" name="total_sin_impuestos" id="nd_total_sin_impuestos">
                                            </div>

                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="text-muted" id="nd_lbl_iva_nombre">IVA</span>
                                                <span class="fw-bold text-dark" id="nd_lbl_iva">0.00</span>
                                            </div>

                                            <hr class="my-1 opacity-25">

                                            <div class="d-flex justify-content-between align-items-center bg-light border py-1 px-2 rounded">
                                                <span class="fw-bold text-dark" style="font-size:0.8rem;">TOTAL</span>
                                                <span class="fw-bold text-dark" style="font-size:1rem;" id="nd_lbl_total">0.00</span>
                                                <input type="hidden" name="importe_total" id="nd_importe_total">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pestaña: Factura relacionada -->
                        <div class="tab-pane fade p-3" id="tab-nd-factura" role="tabpanel">
                            <div id="nd-factura-relacionada-loading" class="text-center py-5 text-muted">
                                <i class="bi bi-info-circle me-1"></i> Guarda la nota de débito para ver el detalle de la factura relacionada.
                            </div>
                            <div id="nd-factura-relacionada-contenido" class="d-none">
                                <div class="row g-2 mb-3">
                                    <div class="col-md-3">
                                        <div class="text-muted x-small">Número</div>
                                        <div class="fw-bold" id="ndf-numero"></div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-muted x-small">Fecha emisión</div>
                                        <div class="fw-bold" id="ndf-fecha"></div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-muted x-small">Cliente</div>
                                        <div class="fw-bold" id="ndf-cliente"></div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-muted x-small">Estado</div>
                                        <div id="ndf-estado"></div>
                                    </div>
                                </div>
                                <div class="row g-2 mb-3">
                                    <div class="col-md-3">
                                        <div class="text-muted x-small">Subtotal</div>
                                        <div class="fw-semibold">$<span id="ndf-subtotal"></span></div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-muted x-small">Total factura</div>
                                        <div class="fw-semibold">$<span id="ndf-total"></span></div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-muted x-small">Cobrado</div>
                                        <div class="fw-semibold text-success">$<span id="ndf-cobrado"></span></div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-muted x-small">Saldo pendiente</div>
                                        <div class="fw-bold text-danger">$<span id="ndf-saldo"></span></div>
                                    </div>
                                </div>
                                <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                                    <div class="table-responsive" style="max-height: 260px;">
                                        <table class="table table-sm small mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="ps-3 py-2">Descripción</th>
                                                    <th class="text-end">Cant.</th>
                                                    <th class="text-end">P. Unitario</th>
                                                    <th class="text-end pe-3">Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody id="ndf-tbody-detalles"></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pestaña: Asiento Contable -->
                        <div class="tab-pane fade p-3" id="tab-nd-contable" role="tabpanel">
                            <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                                <div class="table-responsive" style="max-height: 350px;">
                                    <table class="table table-sm table-detalle mb-0 text-nowrap" id="nd-table-asiento">
                                        <thead>
                                            <tr class="table-light border-bottom">
                                                <th class="ps-3 py-2 small fw-bold text-muted" style="width:45%;">Cuenta Contable</th>
                                                <th class="py-2 small fw-bold text-muted text-end pe-3" style="width:20%;">D&eacute;bito / Debe</th>
                                                <th class="py-2 small fw-bold text-muted text-end pe-3" style="width:20%;">Cr&eacute;dito / Haber</th>
                                                <th class="py-2 small fw-bold text-muted" style="width:15%;">Referencia</th>
                                                <th style="width:40px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="nd-asiento-tbody">
                                            <tr><td colspan="5" class="text-center py-4 text-muted">Guarda la nota de d&eacute;bito para generar el asiento contable.</td></tr>
                                        </tbody>
                                        <tfoot class="bg-light fw-bold border-top sticky-bottom">
                                            <tr>
                                                <td class="text-end py-2">Totales:</td>
                                                <td class="text-end pe-3 py-2 text-primary" id="nd-asiento-debe">0.00</td>
                                                <td class="text-end pe-3 py-2 text-primary" id="nd-asiento-haber">0.00</td>
                                                <td colspan="2" class="py-2">
                                                    <div class="d-flex align-items-center gap-2 justify-content-end pe-3">
                                                        <span class="x-small text-muted">Diferencia: <span id="nd-asiento-dif">0.00</span></span>
                                                        <span id="nd-asiento-badge" class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2">Cuadrado</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <div class="p-2 border-top bg-light d-flex justify-content-between align-items-center">
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" id="nd-asiento-add">
                                        <i class="bi bi-plus-circle me-1"></i> Agregar l&iacute;nea
                                    </button>
                                    <div class="small fw-bold text-muted pe-3">L&iacute;neas: <span id="nd-asiento-count">0</span></div>
                                </div>
                            </div>
                            <div class="px-1 pt-2 small text-muted" id="nd-asiento-status"></div>
                        </div>

                        <!-- Pestaña: SRI -->
                        <div class="tab-pane fade p-3" id="tab-nd-sri" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span class="fw-bold small text-muted">Estado de autorización:</span>
                                        <span id="nd-sri-badge-estado" class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2">Sin enviar</span>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="border rounded-2 bg-warning bg-opacity-10 border-warning border-opacity-25 p-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi bi-info-circle-fill text-warning"></i>
                                            <span class="small text-warning-emphasis">Verifica los plazos y condiciones del SRI vigentes antes de anular un comprobante autorizado.</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-key me-1"></i>Clave de Acceso</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" id="nd-sri-clave-acceso" class="form-control form-control-sm font-monospace bg-light" readonly placeholder="- sin clave de acceso -" value="">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.ND_copiarCampoSri('nd-sri-clave-acceso')" title="Copiar clave de acceso">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-hash me-1"></i>Número de Autorización</label>
                                    <input type="text" id="nd-sri-autorizacion" class="form-control form-control-sm font-monospace bg-light" readonly placeholder="- pendiente -" value="">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-building me-1"></i>Tipo de Ambiente</label>
                                    <input type="text" id="nd-sri-ambiente" class="form-control form-control-sm bg-light" readonly placeholder="- pendiente -" value="">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-broadcast me-1"></i>Tipo de Emisión</label>
                                    <input type="text" id="nd-sri-tipo-emision" class="form-control form-control-sm bg-light" readonly placeholder="- pendiente -" value="">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-calendar-check me-1"></i>Fecha de Autorización</label>
                                    <input type="text" id="nd-sri-fecha-autorizacion" class="form-control form-control-sm bg-light" readonly placeholder="- pendiente -" value="">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-file-earmark-text me-1"></i>Tipo de Documento</label>
                                    <input type="text" id="nd-sri-tipo-documento" class="form-control form-control-sm bg-light" readonly value="Nota de Débito">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-receipt me-1"></i>Número de Documento</label>
                                    <input type="text" id="nd-sri-numero-documento" class="form-control form-control-sm font-monospace bg-light" readonly placeholder="000-000-000000000" value="">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-person-vcard me-1"></i>Número de Identificación</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" id="nd-sri-identificacion-cliente" class="form-control form-control-sm bg-light" readonly placeholder="- sin identificación -" value="">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.ND_copiarCampoSri('nd-sri-identificacion-cliente')" title="Copiar identificación">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-envelope me-1"></i>Correo del Cliente</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" id="nd-sri-correo-cliente" class="form-control form-control-sm bg-light" readonly placeholder="- sin correo -" value="">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.ND_copiarCampoSri('nd-sri-correo-cliente')" title="Copiar correo">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-clock-history me-1"></i>Historial de Envíos</label>
                                    <div class="border rounded-2 overflow-hidden">
                                        <table class="table table-sm small mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="ps-2 py-1 text-muted" style="width:140px">Fecha / Hora</th>
                                                    <th class="py-1 text-muted" style="width:80px">Ambiente</th>
                                                    <th class="py-1 text-muted" style="width:110px">Acción / Estado</th>
                                                    <th class="py-1 text-muted">Mensaje / Detalle</th>
                                                </tr>
                                            </thead>
                                            <tbody id="nd-sri-tbody-historial">
                                                <tr>
                                                    <td colspan="4" class="text-center py-3 text-muted">Sin historial de envíos.</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer justify-content-between bg-light border-top p-2">
                <div>
                    <button type="button" class="btn btn-sm btn-outline-danger px-3 d-none" id="btnEliminarND" onclick="window.ND_eliminar()">
                        <i class="bi bi-trash3 me-1"></i> Eliminar
                    </button>
                </div>
                <div>
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">
                        <i class="fa-solid fa-xmark me-1"></i>Cerrar
                    </button>
                    <button type="button" class="btn btn-sm btn-primary px-4" id="btnGuardarND" onclick="window.ND_guardar()">
                        <i class="bi bi-check2-circle me-1"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .modal-nd .x-small {
        font-size: 0.72rem;
    }

    .modal-nd .dropdown-predictivo {
        z-index: 2000 !important;
    }

    .modal-nd .modal-header {
        padding: 0.75rem 1rem;
    }

    .modal-nd .modal-body {
        padding: 0 !important;
    }

    .modal-nd label {
        font-size: 0.85rem;
        font-weight: 600;
        color: #495057;
        margin-bottom: 3px !important;
    }

    .modal-nd .input-detalle {
        border: 1px solid #dee2e6;
        background: #fff;
        width: 100%;
        padding: 4px 8px;
        font-size: 0.82rem;
        border-radius: 4px;
        height: 32px !important;
    }

    .modal-nd .input-detalle:focus {
        border-color: var(--bs-primary);
        outline: none;
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
    }

    .modal-nd .row-det:hover {
        background-color: rgba(13, 110, 253, 0.02) !important;
    }

    /* Modo solo lectura: ocultar controles de edición (agregar/eliminar/guardar) */
    #modalND.nd-lectura .nd-edit-only {
        display: none !important;
    }
</style>
