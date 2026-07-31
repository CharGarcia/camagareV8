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
                        <button id="btnAnularFR" type="button" class="btn btn-outline-warning btn-sm px-2 d-none" onclick="window.FR_anular()" title="Anular"><i class="bi bi-slash-circle me-1"></i>Anular</button>
                    </div>

                    <!-- Pestañas -->
                    <div class="d-flex align-items-center bg-light px-3 pt-2">
                        <ul class="nav nav-tabs border-bottom-0 flex-grow-1 tab-pestaña" id="frTabs" role="tablist">
                            <li class="nav-item"><a class="nav-link active py-2 small" id="tab-fr-principal-btn" data-bs-toggle="tab" href="#tab-fr-principal" role="tab" style="white-space: nowrap;"><i class="bi bi-receipt me-1"></i> Factura de reembolso</a></li>
                            <li class="nav-item"><a class="nav-link py-2 small" id="tab-fr-contable-btn" data-bs-toggle="tab" href="#tab-fr-contable" role="tab" style="white-space: nowrap;"><i class="bi bi-calculator me-1"></i> Asiento contable</a></li>
                            <li class="nav-item"><a class="nav-link py-2 small" id="tab-fr-sri-btn" data-bs-toggle="tab" href="#tab-fr-sri" role="tab" style="white-space: nowrap;"><i class="bi bi-cloud-check me-1"></i> SRI</a></li>
                        </ul>
                        <div class="ms-auto pb-1">
                            <?php
                            $pestanasConfigFR = [
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
                                            <div class="col-md-2">
                                                <label class="x-small fw-bold text-muted mb-1">Fecha Emisión</label>
                                                <input type="date" name="fecha_emision" id="fr_fecha_emision" class="form-control form-control-sm border-primary border-opacity-10 py-0" style="height: 31px;" value="<?= date('Y-m-d') ?>">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="x-small fw-bold text-muted mb-1 d-flex align-items-center">Serie <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('modulos/factura-reembolso', 'fr_id_punto_emision', 'id_punto_emision') ?></label>
                                                <select name="id_punto_emision" id="fr_id_punto_emision" class="form-select form-select-sm border-primary border-opacity-25" onchange="window.FR_cargarSecuencial()" style="height: 31px;">
                                                    <?php foreach ($puntos as $p): ?>
                                                        <option value="<?= $p['id'] ?>" data-cod-est="<?= $p['cod_establecimiento'] ?>" data-cod-punto="<?= $p['codigo_punto'] ?>">
                                                            <?= $p['cod_establecimiento'] ?>-<?= $p['codigo_punto'] ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="x-small fw-bold text-muted mb-1">Secuencial</label>
                                                <input type="text" name="secuencial" id="fr_secuencial" class="form-control form-control-sm border-primary border-opacity-25 text-center py-0 bg-light" style="height: 31px;" placeholder="000000001" readonly>
                                            </div>
                                            <div class="col-md-6 position-relative">
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

                                    <div class="col-12 px-1 mt-1 d-none" id="fr_info_cliente">
                                        <div class="d-flex flex-wrap align-items-center gap-x-3 gap-y-1" style="font-size:0.72rem; text-transform:lowercase; color:#6c757d;">
                                            <span class="border-end pe-2 me-1 fw-bold text-dark" id="fr_lbl_cliente_ruc"></span>
                                            <div class="d-flex align-items-center gap-1">
                                                <i class="bi bi-geo-alt"></i><span id="fr_lbl_cliente_direccion"></span>
                                            </div>
                                            <div class="d-flex align-items-center gap-1 border-start ps-2">
                                                <i class="bi bi-envelope"></i><span id="fr_lbl_cliente_correo"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tabla de Detalle -->
                            <div class="p-3">
                                <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                                    <div class="table-responsive" style="max-height: 260px;">
                                        <table class="table table-sm fr-table-detalle mb-0">
                                            <thead>
                                                <tr class="table-light border-bottom">
                                                    <th class="ps-3 py-2 small fw-bold text-muted" style="width: 26%;">Descripción</th>
                                                    <th class="py-2 small fw-bold text-muted text-center" style="width: 13%;">Tipo</th>
                                                    <th class="py-2 small fw-bold text-muted text-center" style="width: 12%;">Tarifa IVA</th>
                                                    <th class="py-2 small fw-bold text-muted text-end" style="width: 10%;">Cantidad</th>
                                                    <th class="py-2 small fw-bold text-muted text-end" style="width: 12%;">P. Unitario</th>
                                                    <th class="py-2 small fw-bold text-muted text-end pe-3" style="width: 13%;">Subtotal</th>
                                                    <th class="py-2 small fw-bold text-muted text-center" style="width: 70px;" title="Terceros reembolsados">Det. gastos</th>
                                                    <th style="width: 40px;"></th>
                                                </tr>
                                            </thead>
                                            <tbody id="fr_detalle_body">
                                                <tr id="fr-tr-detalle-vacio">
                                                    <td colspan="8" class="text-center py-4 text-muted">Agregue al menos un ítem.</td>
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

                            <!-- Pie de Factura: Pestañas secundarias (Info. Adicional / Formas de pago) y Totales -->
                            <div class="p-3 border-top bg-light">
                                <div class="row g-3">
                                    <!-- Izquierda: Pestañas secundarias -->
                                    <div class="col-md-8">
                                        <ul class="nav nav-tabs nav-tabs-sm mb-2" id="fr-subtabs-factura" role="tablist">
                                            <li class="nav-item">
                                                <button class="nav-link active py-1 small" data-bs-toggle="tab" data-bs-target="#fr-subtab-info" type="button">Info. Adicional</button>
                                            </li>
                                            <li class="nav-item">
                                                <button class="nav-link py-1 small" id="fr-subtab-pagos-btn" data-bs-toggle="tab" data-bs-target="#fr-subtab-pagos" type="button">Formas de pago</button>
                                            </li>
                                            <li class="nav-item">
                                                <button class="nav-link py-1 small" id="fr-subtab-credito-btn" data-bs-toggle="tab" data-bs-target="#fr-subtab-credito" type="button">Crédito</button>
                                            </li>
                                        </ul>
                                        <div class="tab-content bg-white border p-2 rounded-bottom" style="min-height: 120px;">
                                            <!-- Info Adicional -->
                                            <div class="tab-pane fade show active" id="fr-subtab-info" role="tabpanel">
                                                <div class="border rounded-2 overflow-hidden bg-white mt-1">
                                                    <div class="table-responsive" style="max-height: 200px;">
                                                        <table class="table table-sm mb-0">
                                                            <thead class="table-light">
                                                                <tr>
                                                                    <th class="ps-2 py-0 small fw-bold text-muted" style="width: 40%;">Concepto</th>
                                                                    <th class="py-0 small fw-bold text-muted" style="width: 50%;">Detalle</th>
                                                                    <th class="py-0" style="width: 10%;"></th>
                                                                </tr>
                                                            </thead>
                                                            <?php $rucProveedorSri = \App\Helpers\SriProveedorHelper::rucProveedor(); ?>
                                                            <?php if ($rucProveedorSri !== ''): ?>
                                                            <!-- Campo normativo fijo (Res. NAC-DGERCGC26-00000027 / Ficha v2.34 Anexo 26):
                                                                 lo agrega el sistema al CREAR/ACTUALIZAR el documento (FacturaReembolsoService);
                                                                 esta fila es solo la vista previa. -->
                                                            <tbody id="fr-tbody-ruc-proveedor-preview">
                                                                <tr class="table-light">
                                                                    <td class="ps-2 p-0 align-middle">
                                                                        <span class="small text-muted fst-italic"><i class="bi bi-lock-fill me-1" style="font-size:0.65rem;"></i><?= htmlspecialchars(\App\Helpers\SriProveedorHelper::CAMPO_NOMBRE) ?></span>
                                                                    </td>
                                                                    <td class="p-0 align-middle">
                                                                        <span class="small text-muted fst-italic"><?= htmlspecialchars($rucProveedorSri) ?></span>
                                                                    </td>
                                                                    <td class="p-0 align-middle text-center">
                                                                        <i class="bi bi-shield-check text-success" style="font-size:0.75rem;" title="Campo obligatorio del SRI: lo agrega el sistema automáticamente en el XML y el PDF"></i>
                                                                    </td>
                                                                </tr>
                                                            </tbody>
                                                            <?php endif; ?>
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
                                            <!-- Formas de Pago -->
                                            <div class="tab-pane fade" id="fr-subtab-pagos" role="tabpanel">
                                                <div id="fr_pagos_body">
                                                    <div class="row g-2 align-items-center mb-1 row-fr-pago">
                                                        <div class="col-7">
                                                            <div class="d-flex align-items-center gap-1">
                                                                <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito($rutaModulo, 'fr-select-pago-sri', 'id_forma_pago_sri') ?>
                                                                <select class="form-select form-select-sm border-0 bg-light" name="fp_forma_pago[]" id="fr-select-pago-sri">
                                                                    <option value="" data-id="">-- Seleccione forma de pago --</option>
                                                                    <?php foreach ($formasPago as $fp): ?>
                                                                        <option value="<?= htmlspecialchars($fp['codigo']) ?>" data-id="<?= htmlspecialchars((string) $fp['id']) ?>"><?= htmlspecialchars($fp['nombre']) ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-4">
                                                            <input type="number" class="form-control form-control-sm text-end border-0 bg-light fw-bold" name="fp_total[]" step="0.01" value="0.00">
                                                        </div>
                                                        <div class="col-1 text-center">
                                                            <span></span>
                                                        </div>
                                                        <input type="hidden" name="fp_plazo[]" value="0">
                                                        <input type="hidden" name="fp_unidad[]" value="dias">
                                                    </div>
                                                </div>
                                                <button type="button" class="btn btn-link btn-xs p-0 text-decoration-none small mt-1" onclick="window.FR_agregarPago()"><i class="bi bi-plus-circle me-1"></i>Añadir pago</button>
                                            </div>
                                            <!-- Crédito SRI -->
                                            <div class="tab-pane fade" id="fr-subtab-credito" role="tabpanel">
                                                <div class="p-2">
                                                    <div class="row g-2">
                                                        <div class="col-md-6">
                                                            <label class="x-small text-muted mb-1">Días de crédito</label>
                                                            <input type="number" class="form-control form-control-sm" id="fr-input-dias-credito" value="0" min="0">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="x-small text-muted mb-1">Plazo</label>
                                                            <select class="form-select form-select-sm" id="fr-select-plazo-credito">
                                                                <option value="dias">Días</option>
                                                                <option value="meses">Meses</option>
                                                                <option value="anios">Años</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Derecha: Totales -->
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

<!-- ════════════════════════════════════════
     MODAL SECUNDARIO: Terceros reembolsados (bloque SRI <reembolsos>)
     Se abre desde el botón "Terceros" de una línea "Gasto" en el detalle.
════════════════════════════════════════ -->
<div class="modal fade" id="modalFRTerceros" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header py-2">
                <h6 class="modal-title fs-6 fw-bold mb-0"><i class="bi bi-people-fill text-primary me-2"></i>Terceros reembolsados</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
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
                        <!-- Fila 1: Fecha emisión, Tipo ID, Identificación, Tipo proveedor -->
                        <div class="col-md-3">
                            <label class="x-small text-muted mb-0">Fecha emisión</label>
                            <input type="date" id="fr_tercero_fecha" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3">
                            <label class="x-small text-muted mb-0">Tipo ID proveedor</label>
                            <select id="fr_tercero_tipo_id" class="form-select form-select-sm">
                                <?php foreach ($tiposIdentificacion as $ti): ?>
                                    <option value="<?= htmlspecialchars($ti['codigo']) ?>"><?= htmlspecialchars($ti['codigo'] . ' - ' . $ti['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="x-small text-muted mb-0">Identificación</label>
                            <div class="input-group input-group-sm">
                                <input type="text" id="fr_tercero_identificacion" class="form-control form-control-sm">
                                <span class="input-group-text bg-white p-1 border-start-0" id="fr_tercero_sriSpinnerWrap" style="display:none;">
                                    <span class="spinner-border spinner-border-sm text-secondary" style="width:0.8rem;height:0.8rem;"></span>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="x-small text-muted mb-0">Tipo proveedor</label>
                            <select id="fr_tercero_tipo_proveedor" class="form-select form-select-sm">
                                <option value="02">02 - Gasto</option>
                                <option value="01">01 - Servicios profesionales</option>
                            </select>
                        </div>

                        <!-- Razón social (referencia, no viene del XSD) -->
                        <div class="col-12">
                            <label class="x-small text-muted mb-0">Razón social (referencia) <span id="fr_tercero_sriBadge" class="badge d-none"></span></label>
                            <input type="text" id="fr_tercero_razon_social" class="form-control form-control-sm">
                        </div>

                        <!-- Fila 2: Tipo comprobante, No. documento, Núm. autorización -->
                        <div class="col-md-3">
                            <label class="x-small text-muted mb-0">Tipo comprobante</label>
                            <select id="fr_tercero_cod_doc" class="form-select form-select-sm">
                                <option value="01">01 - Factura</option>
                                <option value="02">02 - Nota de venta</option>
                                <option value="03">03 - Liquidación de compra</option>
                                <option value="04">04 - Nota de crédito</option>
                                <option value="05">05 - Nota de débito</option>
                                <option value="06">06 - Guía de remisión</option>
                                <option value="07">07 - Comprobante de retención</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="x-small text-muted mb-0">No. documento</label>
                            <input type="text" id="fr_tercero_numero_doc" maxlength="17" placeholder="000-000-000000000" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-6">
                            <label class="x-small text-muted mb-0">Núm. autorización</label>
                            <input type="text" id="fr_tercero_autorizacion" class="form-control form-control-sm">
                        </div>

                        <!-- Fila 3: Tarifa IVA, Base imponible, Valor IVA -->
                        <div class="col-md-4">
                            <label class="x-small text-muted mb-0">Tarifa IVA</label>
                            <select id="fr_tercero_tarifa_iva" class="form-select form-select-sm" onchange="window.FR_calcularIvaTerceroManual()">
                                <?php foreach ($tarifasIva as $t): ?>
                                    <option value="<?= htmlspecialchars((string) $t['porcentaje_iva']) ?>" data-codigo="<?= htmlspecialchars((string) $t['codigo']) ?>"><?= htmlspecialchars((string) $t['tarifa']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="x-small text-muted mb-0">Base imponible</label>
                            <input type="number" step="0.01" id="fr_tercero_base" class="form-control form-control-sm" value="0.00" oninput="window.FR_calcularIvaTerceroManual()">
                        </div>
                        <div class="col-md-4">
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
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-primary px-4" data-bs-dismiss="modal">
                    <i class="bi bi-check2-circle me-1"></i> Listo
                </button>
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

<script>
(function () {
    // app.css fuerza globalmente `.modal { z-index:5060 !important }` y
    // `.modal-backdrop { z-index:5055 !important }`. Para que "Terceros reembolsados"
    // se abra ENCIMA de la Factura de Reembolso hay que subirlo por encima de 5060
    // (mismo patrón que app/views/modulos/proformas/modal_proforma.php).
    var Z_SUBMODAL = 5080;
    var Z_BACKDROP = 5075;
    var SUBMODALES = ['modalFRTerceros'];

    document.addEventListener('show.bs.modal', function (ev) {
        if (SUBMODALES.indexOf(ev.target.id) === -1) return;
        ev.target.style.setProperty('z-index', String(Z_SUBMODAL), 'important');
        setTimeout(function () {
            var bds = document.querySelectorAll('.modal-backdrop');
            if (bds.length) bds[bds.length - 1].style.setProperty('z-index', String(Z_BACKDROP), 'important');
        }, 0);
    });

    document.addEventListener('hidden.bs.modal', function (ev) {
        if (SUBMODALES.indexOf(ev.target.id) !== -1 && document.querySelectorAll('.modal.show').length > 0) {
            document.body.classList.add('modal-open');
        }
    });
})();
</script>
