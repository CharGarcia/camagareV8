<?php

/** @var array $perm */
/** @var string $rutaModulo */
/** @var array $puntos */
/** @var array $tarifasIva */
/** @var array $formasPago */
/** @var array $tiposIdentificacion */

$vistaConfigFR = \App\Helpers\PreferenciasHelper::getPreferenciasVista('modulos/factura-reembolso');
?>
<!-- Modal para Nueva/Editar Factura de Reembolso -->
<div class="modal fade modal-fr" id="modalFR" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title fs-6 fw-bold" id="modalFRTitulo">
                    <i class="bi bi-arrow-repeat text-primary me-2"></i>Nueva Factura de Reembolso
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <form id="formFR">
                    <input type="hidden" name="id" id="fr_id">

                    <!-- Barra de Acciones Superior -->
                    <div class="px-3 py-2 bg-light border-bottom d-flex gap-1 align-items-center flex-wrap">
                        <button id="fr-btn-sri" type="button" class="btn btn-outline-primary btn-sm" onclick="window.FR_enviarSRI()" disabled><i class="bi bi-cloud-arrow-up me-1"></i>Enviar al SRI</button>
                        <div class="vr mx-1"></div>
                        <button id="fr-btn-pdf" type="button" class="btn btn-outline-danger btn-sm px-2" onclick="window.FR_exportarPdf()" title="Exportar PDF" disabled><i class="bi bi-file-earmark-pdf"></i></button>
                        <button id="fr-btn-xml" type="button" class="btn btn-outline-success btn-sm px-2" onclick="window.FR_exportarXml()" title="Exportar XML" disabled><i class="bi bi-file-earmark-code"></i></button>
                        <button id="fr-btn-correo" type="button" class="btn btn-outline-info btn-sm px-2" onclick="window.FR_enviarPorCorreo()" title="Enviar por correo" disabled><i class="bi bi-envelope"></i></button>
                        <button id="fr-btn-whatsapp" type="button" class="btn btn-outline-success btn-sm px-2" onclick="window.FR_enviarWhatsapp()" title="Enviar por WhatsApp" disabled><i class="bi bi-whatsapp"></i></button>
                        <button id="btnAnularFR" type="button" class="btn btn-outline-warning btn-sm px-2 d-none" onclick="window.FR_anular()" title="Anular"><i class="bi bi-slash-circle me-1"></i>Anular</button>
                    </div>

                    <!-- Pestañas -->
                    <div class="d-flex align-items-center bg-light px-3 pt-2">
                        <ul class="nav nav-tabs border-bottom-0 flex-grow-1 tab-pestaña" id="frTabs" role="tablist">
                            <li class="nav-item"><a class="nav-link active py-2 small" id="tab-fr-principal-btn" data-bs-toggle="tab" href="#tab-fr-principal" role="tab" style="white-space: nowrap;"><i class="bi bi-receipt me-1"></i> Factura de reembolso</a></li>
                            <li class="nav-item"><a class="nav-link py-2 small" id="tab-fr-terceros-btn" data-bs-toggle="tab" href="#tab-fr-terceros" role="tab" style="white-space: nowrap;"><i class="bi bi-people me-1"></i> Terceros reembolsados<span id="fr-badge-terceros" class="badge bg-secondary ms-1 x-small d-none">0</span></a></li>
                            <li class="nav-item"><a class="nav-link py-2 small" id="tab-fr-contable-btn" data-bs-toggle="tab" href="#tab-fr-contable" role="tab" style="white-space: nowrap;"><i class="bi bi-calculator me-1"></i> Asiento contable</a></li>
                            <li class="nav-item"><a class="nav-link py-2 small" id="tab-fr-sri-btn" data-bs-toggle="tab" href="#tab-fr-sri" role="tab" style="white-space: nowrap;"><i class="bi bi-cloud-check me-1"></i> SRI</a></li>
                        </ul>
                        <div class="ms-auto pb-1">
                            <?php
                            $pestanasConfigFR = [
                                'tab-fr-terceros' => 'Terceros reembolsados',
                                'tab-fr-contable' => 'Asiento contable',
                                'tab-fr-sri'      => 'SRI',
                            ];
                            echo \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasConfigFR, $vistaConfigFR ?? [], 'modulos/factura-reembolso');
                            ?>
                        </div>
                    </div>

                    <div class="tab-content border-top">
                        <!-- Pestaña Principal: Factura de reembolso -->
                        <div class="tab-pane fade show active" id="tab-fr-principal" role="tabpanel">
                            <div class="p-3 bg-white border-bottom">
                                <div class="row g-2">
                                    <div class="col-12">
                                        <div class="row g-2 align-items-end">
                                            <div class="col-md-3">
                                                <label class="x-small fw-bold text-muted mb-1">Fecha Emisión</label>
                                                <input type="date" name="fecha_emision" id="fr_fecha_emision" class="form-control form-control-sm border-primary border-opacity-10 py-0" style="height: 31px;" value="<?= date('Y-m-d') ?>">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="x-small fw-bold text-muted mb-1 d-flex align-items-center">Serie <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('modulos/factura-reembolso', 'fr_id_punto_emision', 'id_punto_emision') ?></label>
                                                <select name="id_punto_emision" id="fr_id_punto_emision" class="form-select form-select-sm border-primary border-opacity-25" onchange="window.FR_cargarSecuencial()" style="height: 31px;">
                                                    <?php foreach ($puntos as $p): ?>
                                                        <option value="<?= $p['id'] ?>" data-cod-est="<?= $p['cod_establecimiento'] ?>" data-cod-punto="<?= $p['codigo_punto'] ?>">
                                                            <?= $p['cod_establecimiento'] ?>-<?= $p['codigo_punto'] ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="x-small fw-bold text-muted mb-1">Secuencial</label>
                                                <input type="text" name="secuencial" id="fr_secuencial" class="form-control form-control-sm border-primary border-opacity-25 text-center py-0 bg-light" style="height: 31px;" placeholder="000000001" readonly>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="x-small fw-bold text-muted mb-1">Tarifa IVA (honorarios)</label>
                                                <select id="fr_tarifa_iva" class="form-select form-select-sm border-primary border-opacity-10" style="height: 31px;">
                                                    <?php foreach ($tarifasIva as $t): ?>
                                                        <option value="<?= htmlspecialchars((string) $t['porcentaje_iva']) ?>" data-codigo="<?= htmlspecialchars((string) $t['codigo']) ?>"><?= htmlspecialchars((string) $t['tarifa']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12 mt-2">
                                        <div class="p-2 border rounded-3 bg-light bg-opacity-10">
                                            <div class="row g-2 align-items-center">
                                                <div class="col-md-12 position-relative">
                                                    <label class="x-small fw-bold text-muted mb-1">Cliente</label>
                                                    <div class="input-group input-group-sm flex-grow-1 elevation-1 rounded-pill overflow-hidden border">
                                                        <span class="input-group-text bg-white border-0 text-primary"><i class="bi bi-search"></i></span>
                                                        <input type="text" class="form-control border-0 px-1" id="fr_cliente_search" placeholder="Buscar cliente por RUC o Razón Social..." autocomplete="off">
                                                        <input type="hidden" name="id_cliente" id="fr_id_cliente">
                                                    </div>
                                                    <div id="fr_cliente_dropdown" class="list-group shadow dropdown-predictivo position-absolute d-none" style="z-index: 1050; width: 100%; max-height: 250px; overflow-y: auto; right: 0px; top: 55px;"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div id="fr_info_cliente" class="col-12 mt-2 d-none">
                                        <div class="p-2 border rounded-3 bg-light bg-opacity-50 border-primary border-opacity-10">
                                            <div class="row g-2 align-items-center">
                                                <div class="col-md-4">
                                                    <div class="d-flex align-items-center gap-1">
                                                        <i class="bi bi-card-text text-muted"></i>
                                                        <span id="fr_lbl_cliente_ruc" class="fw-bold small text-dark"></span>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="d-flex align-items-center gap-1">
                                                        <i class="bi bi-geo-alt text-muted"></i>
                                                        <span id="fr_lbl_cliente_direccion" class="small text-muted text-truncate d-inline-block" style="max-width: 200px;"></span>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="d-flex align-items-center gap-1">
                                                        <i class="bi bi-envelope text-muted"></i>
                                                        <span id="fr_lbl_cliente_correo" class="small text-muted text-truncate d-inline-block" style="max-width: 200px;"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-light border mx-3 mt-3 mb-0 py-2 px-3" style="font-size:0.75rem;">
                                <i class="bi bi-info-circle me-1"></i> Agregue una línea por cada gasto reembolsado (marcada "Reembolso", sin IVA propio — código 6 "No objeto de impuesto")
                                y, si cobra honorarios propios por la gestión, una línea adicional sin marcar (con IVA normal). Los datos del comprobante del proveedor van en la pestaña
                                <strong>Terceros reembolsados</strong>.
                            </div>

                            <!-- Tabla de Detalle -->
                            <div class="p-3">
                                <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                                    <div class="table-responsive" style="max-height: 260px;">
                                        <table class="table table-sm fr-table-detalle mb-0">
                                            <thead>
                                                <tr class="table-light border-bottom">
                                                    <th class="ps-3 py-2 small fw-bold text-muted" style="width: 40%;">Descripción</th>
                                                    <th class="py-2 small fw-bold text-muted text-center" style="width: 10%;">Reembolso</th>
                                                    <th class="py-2 small fw-bold text-muted text-end" style="width: 12%;">Cantidad</th>
                                                    <th class="py-2 small fw-bold text-muted text-end" style="width: 14%;">P. Unitario</th>
                                                    <th class="py-2 small fw-bold text-muted text-end pe-3" style="width: 15%;">Subtotal</th>
                                                    <th style="width: 40px;"></th>
                                                </tr>
                                            </thead>
                                            <tbody id="fr_detalle_body">
                                                <tr id="fr-tr-detalle-vacio">
                                                    <td colspan="6" class="text-center py-4 text-muted">Agregue al menos un ítem.</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="p-2 border-top bg-light">
                                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold ms-2" onclick="window.FR_agregarDetalle()">
                                            <i class="bi bi-plus-circle me-1"></i> Agregar ítem
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Tabla de Pagos -->
                            <div class="px-3 pb-3">
                                <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                                    <div class="px-2 py-1 bg-light border-bottom">
                                        <span class="x-small fw-bold text-muted"><i class="bi bi-credit-card me-1"></i>Formas de pago</span>
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
                                            <tbody id="fr_pagos_body">
                                                <tr class="row-fr-pago">
                                                    <td class="ps-2">
                                                        <select class="form-select form-select-sm border-0 bg-light" name="fp_forma_pago[]">
                                                            <option value="">-- Seleccione --</option>
                                                            <?php foreach ($formasPago as $fp): ?>
                                                                <option value="<?= htmlspecialchars($fp['codigo']) ?>"><?= htmlspecialchars($fp['nombre']) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </td>
                                                    <td><input type="number" step="0.01" class="form-control form-control-sm border-0 bg-light text-end" name="fp_total[]" value="0.00"></td>
                                                    <td><input type="number" class="form-control form-control-sm border-0 bg-light text-center" name="fp_plazo[]" value="0"></td>
                                                    <td>
                                                        <select class="form-select form-select-sm border-0 bg-light" name="fp_unidad[]">
                                                            <option value="dias">Días</option>
                                                            <option value="meses">Meses</option>
                                                        </select>
                                                    </td>
                                                    <td></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="p-1 border-top bg-light">
                                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold ms-2" onclick="window.FR_agregarPago()">
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
                                                    <tbody id="fr-tbody-info-adicional"></tbody>
                                                </table>
                                            </div>
                                            <div class="p-1 border-top bg-light">
                                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold ms-2" onclick="window.FR_agregarInfoAdicional()">
                                                    <i class="bi bi-plus-circle me-1"></i> Agregar línea
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="bg-white border rounded p-2 shadow-sm" style="font-size:0.75rem;">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="text-muted">Subtotal (detalle)</span>
                                                <span id="fr_lbl_subtotal">0.00</span>
                                                <input type="hidden" name="total_sin_impuestos" id="fr_total_sin_impuestos">
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="text-muted" id="fr_lbl_iva_nombre">IVA</span>
                                                <span class="fw-bold text-dark" id="fr_lbl_iva">0.00</span>
                                            </div>
                                            <hr class="my-1 opacity-25">
                                            <div class="d-flex justify-content-between align-items-center bg-light border py-1 px-2 rounded mb-1">
                                                <span class="fw-bold text-dark" style="font-size:0.8rem;">TOTAL</span>
                                                <span class="fw-bold text-dark" style="font-size:1rem;" id="fr_lbl_total">0.00</span>
                                                <input type="hidden" name="importe_total" id="fr_importe_total">
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mb-0 x-small text-info">
                                                <span>Total reembolsado (terceros)</span>
                                                <span id="fr_lbl_total_reembolsado">0.00</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pestaña: Terceros reembolsados (bloque SRI <reembolsos>) -->
                        <div class="tab-pane fade p-3" id="tab-fr-terceros" role="tabpanel">
                            <div class="alert alert-light border py-1 px-2 mb-2" style="font-size:0.7rem;">
                                <i class="bi bi-info-circle me-1"></i> Cada línea es un comprobante de un proveedor tercero que la empresa pagó a nombre del cliente
                                (obligatorio para el Anexo ATS código 41). Vincule una compra ya registrada o agréguela manualmente.
                            </div>
                            <div class="position-relative mb-2">
                                <input type="text" id="fr_search_tercero_compra" class="form-control form-control-sm" autocomplete="off" placeholder="Buscar compra registrada por proveedor, RUC o número...">
                                <div id="fr_dropdown_tercero_compras" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index:1060; max-height:220px; overflow-y:auto;"></div>
                            </div>
                            <div class="border rounded-2 overflow-hidden bg-white">
                                <div class="table-responsive" style="max-height: 240px;">
                                    <table class="table table-sm mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-2 py-0 small fw-bold text-muted">Proveedor</th>
                                                <th class="py-0 small fw-bold text-muted">Documento</th>
                                                <th class="py-0 small fw-bold text-muted text-end">Base</th>
                                                <th class="py-0 small fw-bold text-muted text-end">Impuesto</th>
                                                <th class="py-0" style="width: 10%;"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="fr_terceros_body">
                                            <tr id="fr-tr-terceros-vacio">
                                                <td colspan="5" class="text-center text-muted small py-3">Sin terceros agregados.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="p-1 border-top bg-light">
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold ms-2" onclick="window.FR_toggleTerceroManual()">
                                        <i class="bi bi-pencil-square me-1"></i> Agregar manual (proveedor no registrado en Compras)
                                    </button>
                                </div>
                            </div>
                            <div id="fr_form_tercero_manual" class="border rounded-2 p-2 mt-2 bg-light d-none">
                                <div class="row g-1">
                                    <div class="col-md-3">
                                        <label class="x-small text-muted mb-0">Tipo ID proveedor</label>
                                        <select id="fr_tercero_tipo_id" class="form-select form-select-sm">
                                            <?php foreach ($tiposIdentificacion as $ti): ?>
                                                <option value="<?= htmlspecialchars($ti['codigo']) ?>"><?= htmlspecialchars($ti['codigo'] . ' - ' . $ti['nombre']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="x-small text-muted mb-0">Identificación</label>
                                        <input type="text" id="fr_tercero_identificacion" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-5">
                                        <label class="x-small text-muted mb-0">Razón social (referencia)</label>
                                        <input type="text" id="fr_tercero_razon_social" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="x-small text-muted mb-0">Tipo proveedor</label>
                                        <select id="fr_tercero_tipo_proveedor" class="form-select form-select-sm">
                                            <option value="02">02 - Gasto</option>
                                            <option value="01">01 - Servicios profesionales</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="x-small text-muted mb-0">Tipo comprobante</label>
                                        <select id="fr_tercero_cod_doc" class="form-select form-select-sm">
                                            <option value="01">01 - Factura</option>
                                            <option value="03">03 - Liquidación de compra</option>
                                            <option value="04">04 - Nota de crédito</option>
                                            <option value="05">05 - Nota de débito</option>
                                            <option value="06">06 - Guía de remisión</option>
                                            <option value="07">07 - Comprobante de retención</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="x-small text-muted mb-0">Estab.</label>
                                        <input type="text" id="fr_tercero_estab" maxlength="3" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="x-small text-muted mb-0">Pto. emi.</label>
                                        <input type="text" id="fr_tercero_ptoemi" maxlength="3" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="x-small text-muted mb-0">Secuencial</label>
                                        <input type="text" id="fr_tercero_secuencial" maxlength="9" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="x-small text-muted mb-0">Fecha emisión</label>
                                        <input type="date" id="fr_tercero_fecha" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="x-small text-muted mb-0">Núm. autorización</label>
                                        <input type="text" id="fr_tercero_autorizacion" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="x-small text-muted mb-0">Base imponible</label>
                                        <input type="number" step="0.01" id="fr_tercero_base" class="form-control form-control-sm" value="0.00" oninput="window.FR_calcularIvaTerceroManual()">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="x-small text-muted mb-0">Tarifa IVA</label>
                                        <select id="fr_tercero_tarifa_iva" class="form-select form-select-sm" onchange="window.FR_calcularIvaTerceroManual()">
                                            <?php foreach ($tarifasIva as $t): ?>
                                                <option value="<?= htmlspecialchars((string) $t['porcentaje_iva']) ?>" data-codigo="<?= htmlspecialchars((string) $t['codigo']) ?>"><?= htmlspecialchars((string) $t['tarifa']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="x-small text-muted mb-0">Valor IVA (calculado)</label>
                                        <input type="number" step="0.01" id="fr_tercero_impuesto" class="form-control form-control-sm" value="0.00" readonly>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <button type="button" class="btn btn-primary btn-sm" onclick="window.FR_confirmarTerceroManual()">
                                        <i class="bi bi-plus-circle me-1"></i>Agregar
                                    </button>
                                    <button type="button" class="btn btn-link btn-sm text-muted" onclick="window.FR_toggleTerceroManual()">Cancelar</button>
                                </div>
                            </div>
                        </div>

                        <!-- Pestaña: Asiento Contable -->
                        <div class="tab-pane fade p-3" id="tab-fr-contable" role="tabpanel">
                            <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                                <div class="table-responsive" style="max-height: 350px;">
                                    <table class="table table-sm table-detalle mb-0 text-nowrap" id="fr-table-asiento">
                                        <thead>
                                            <tr class="table-light border-bottom">
                                                <th class="ps-3 py-2 small fw-bold text-muted" style="width:45%;">Cuenta Contable</th>
                                                <th class="py-2 small fw-bold text-muted text-end pe-3" style="width:20%;">D&eacute;bito / Debe</th>
                                                <th class="py-2 small fw-bold text-muted text-end pe-3" style="width:20%;">Cr&eacute;dito / Haber</th>
                                                <th class="py-2 small fw-bold text-muted" style="width:15%;">Referencia</th>
                                                <th style="width:40px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="fr-asiento-tbody">
                                            <tr><td colspan="5" class="text-center py-4 text-muted">El asiento se genera al enviar la factura al SRI (autorización).</td></tr>
                                        </tbody>
                                        <tfoot class="bg-light fw-bold border-top sticky-bottom">
                                            <tr>
                                                <td class="text-end py-2">Totales:</td>
                                                <td class="text-end pe-3 py-2 text-primary" id="fr-asiento-debe">0.00</td>
                                                <td class="text-end pe-3 py-2 text-primary" id="fr-asiento-haber">0.00</td>
                                                <td colspan="2" class="py-2">
                                                    <div class="d-flex align-items-center gap-2 justify-content-end pe-3">
                                                        <span class="x-small text-muted">Diferencia: <span id="fr-asiento-dif">0.00</span></span>
                                                        <span id="fr-asiento-badge" class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2">Cuadrado</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <div class="p-2 border-top bg-light d-flex justify-content-between align-items-center">
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" id="fr-asiento-add">
                                        <i class="bi bi-plus-circle me-1"></i> Agregar l&iacute;nea
                                    </button>
                                    <div class="small fw-bold text-muted pe-3">L&iacute;neas: <span id="fr-asiento-count">0</span></div>
                                </div>
                            </div>
                            <div class="px-1 pt-2 small text-muted" id="fr-asiento-status"></div>
                        </div>

                        <!-- Pestaña: SRI -->
                        <div class="tab-pane fade p-3" id="tab-fr-sri" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span class="fw-bold small text-muted">Estado de autorización:</span>
                                        <span id="fr-sri-badge-estado" class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2">Sin enviar</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-key me-1"></i>Clave de Acceso</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" id="fr-sri-clave-acceso" class="form-control form-control-sm font-monospace bg-light" readonly placeholder="- sin clave de acceso -" value="">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.FR_copiarCampoSri('fr-sri-clave-acceso')" title="Copiar clave de acceso">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-hash me-1"></i>Número de Autorización</label>
                                    <input type="text" id="fr-sri-autorizacion" class="form-control form-control-sm font-monospace bg-light" readonly placeholder="- pendiente -" value="">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-building me-1"></i>Tipo de Ambiente</label>
                                    <input type="text" id="fr-sri-ambiente" class="form-control form-control-sm bg-light" readonly placeholder="- pendiente -" value="">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-file-earmark-text me-1"></i>Tipo de Documento</label>
                                    <input type="text" id="fr-sri-tipo-documento" class="form-control form-control-sm bg-light" readonly value="Factura (ATS 41 - Reembolso)">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-calendar-check me-1"></i>Fecha de Autorización</label>
                                    <input type="text" id="fr-sri-fecha-autorizacion" class="form-control form-control-sm bg-light" readonly placeholder="- pendiente -" value="">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-receipt me-1"></i>Número de Documento</label>
                                    <input type="text" id="fr-sri-numero-documento" class="form-control form-control-sm font-monospace bg-light" readonly placeholder="000-000-000000000" value="">
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
                                            <tbody id="fr-sri-tbody-historial">
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
                    <button type="button" class="btn btn-sm btn-outline-danger px-3 d-none" id="btnEliminarFR" onclick="window.FR_eliminar()">
                        <i class="bi bi-trash3 me-1"></i> Eliminar
                    </button>
                </div>
                <div>
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">
                        <i class="fa-solid fa-xmark me-1"></i>Cerrar
                    </button>
                    <button type="button" class="btn btn-sm btn-primary px-4" id="btnGuardarFR" onclick="window.FR_guardar()">
                        <i class="bi bi-check2-circle me-1"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .modal-fr .x-small { font-size: 0.72rem; }
    .modal-fr .dropdown-predictivo { z-index: 2000 !important; }
    .modal-fr .modal-header { padding: 0.75rem 1rem; }
    .modal-fr .modal-body { padding: 0 !important; }
    .modal-fr label { font-size: 0.85rem; font-weight: 600; color: #495057; margin-bottom: 3px !important; }
    #modalFR.fr-lectura .fr-edit-only { display: none !important; }
</style>
