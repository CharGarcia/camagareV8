<?php
/** @var array $perm */
/** @var string $rutaModulo */
/** @var array $vistaConfig */
/** @var array $puntos */

$vistaConfigRet = \App\Helpers\PreferenciasHelper::getPreferenciasVista($rutaModulo);
echo \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfigRet, 'estiloVistaPestanasRet');
?>

<!-- Modal Retención en Compras -->
<div class="modal fade modal-ret" id="modalRetencion" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title fs-6 fw-bold" id="modalRetTitulo">
                    <i class="fa-solid fa-file-invoice-dollar text-primary me-2"></i>Nueva Retención
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <form id="formRetencion">
                    <input type="hidden" name="id" id="ret_id">
                    <input type="hidden" name="id_compra" id="ret_id_compra">
                    <input type="hidden" name="id_liquidacion" id="ret_id_liquidacion">

                    <!-- Barra de Acciones -->
                    <div class="px-3 py-2 bg-light border-bottom d-flex gap-1 align-items-center flex-wrap">
                        <?php if ($perm['actualizar'] ?? false): ?>
                        <button id="ret-btn-sri" type="button" class="btn btn-outline-primary btn-sm" onclick="window.RET_enviarSRI()" disabled>
                            <i class="bi bi-cloud-arrow-up me-1"></i>Enviar al SRI
                        </button>
                        <button type="button" id="ret-btn-anular" class="btn btn-outline-warning btn-sm d-none" onclick="window.RET_anular()">
                            <i class="fa-solid fa-ban me-1"></i>Anular
                        </button>
                        <div class="vr mx-1"></div>
                        <?php endif; ?>
                        <button id="ret-btn-pdf" type="button" class="btn btn-outline-danger btn-sm px-2" onclick="window.RET_exportarPdf()" title="Exportar PDF" disabled>
                            <i class="bi bi-file-earmark-pdf"></i>
                        </button>
                        <button id="ret-btn-xml" type="button" class="btn btn-outline-success btn-sm px-2" onclick="window.RET_exportarXml()" title="Exportar XML" disabled>
                            <i class="bi bi-file-earmark-code"></i>
                        </button>
                        <button id="ret-btn-correo" type="button" class="btn btn-outline-info btn-sm px-2" onclick="window.RET_enviarPorCorreo()" title="Enviar por correo" disabled>
                            <i class="bi bi-envelope"></i>
                        </button>
                        <div class="vr mx-1"></div>
                        <button type="button" class="btn btn-outline-primary btn-sm px-2" onclick="window.abrirModalProveedorCrear()" title="Registrar nuevo proveedor">
                            <i class="fa-solid fa-user-plus"></i>
                        </button>

                        <div class="ms-auto" id="ret_estado_badge"></div>
                    </div>

                    <!-- Pestañas -->
                    <div class="d-flex align-items-center bg-light px-3 pt-2">
                        <ul class="nav nav-tabs border-bottom-0 flex-grow-1 tab-pestaña" id="retTabs" role="tablist">
                            <li class="nav-item"><a class="nav-link active py-2 small" data-bs-toggle="tab" href="#tab-ret-principal" role="tab" style="white-space: nowrap;"><i class="bi bi-receipt me-1"></i> Retención</a></li>
                            <li class="nav-item"><a class="nav-link py-2 small" id="tab-ret-asiento-btn" data-bs-toggle="tab" href="#tab-ret-asiento" role="tab" style="white-space: nowrap;"><i class="bi bi-calculator me-1"></i> Asiento contable</a></li>
                            <li class="nav-item"><a class="nav-link py-2 small" id="tab-ret-sri-btn" data-bs-toggle="tab" href="#tab-ret-sri" role="tab" style="white-space: nowrap;"><i class="bi bi-cloud-check me-1"></i> SRI</a></li>
                        </ul>
                        <div class="ms-auto pb-1">
                            <?php
                            $pestanasRet = [
                                'tab-ret-asiento' => 'Asiento contable',
                                'tab-ret-sri' => 'SRI',
                            ];
                            echo \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasRet, $vistaConfigRet ?? [], $rutaModulo);
                            ?>
                        </div>
                    </div>

                    <div class="tab-content border-top px-3 py-3">

                        <!-- ── PESTAÑA PRINCIPAL ──────────────────────────────────────── -->
                        <div class="tab-pane fade show active" id="tab-ret-principal" role="tabpanel">

                            <!-- Encabezado del comprobante y Proveedor -->
                            <div class="px-3 py-2 bg-white border-bottom">
                                <!-- Primera Línea: Datos de la Retención -->
                                <div class="row g-2 align-items-end mb-3">
                                    <!-- Fecha Emisión -->
                                    <div class="col-md-2 col-6">
                                        <label class="x-small fw-bold text-muted mb-1">Fecha Emisión <span class="text-danger">*</span></label>
                                        <input type="date" name="fecha_emision" id="ret_fecha_emision"
                                               class="form-control form-control-sm border-primary border-opacity-10 py-0"
                                               style="height:31px;" value="<?= date('Y-m-d') ?>" required
                                               onchange="window.RET_actualizarPeriodoFiscal(this.value)">
                                    </div>

                                    <!-- Fecha Documento Retenido -->
                                    <div class="col-md-2 col-6">
                                        <label class="x-small fw-bold text-muted mb-1">Fecha Doc. Retenido <span class="text-danger">*</span></label>
                                        <input type="date" name="fecha_emision_doc_sustento" id="ret_fecha_emision_doc_sustento"
                                               class="form-control form-control-sm border-primary border-opacity-25 py-0"
                                               style="height:31px;" value="<?= date('Y-m-d') ?>">
                                    </div>

                                    <!-- Serie (Punto de Emisión) -->
                                    <div class="col-md-2 col-6">
                                        <label class="x-small fw-bold text-muted mb-1">Serie <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito($rutaModulo, 'ret_id_punto_emision', 'id_punto_emision') ?></label>
                                        <select name="id_punto_emision" id="ret_id_punto_emision"
                                                class="form-select form-select-sm border-primary border-opacity-25"
                                                onchange="window.RET_cargarSecuencial()" style="height:31px;">
                                            <?php foreach ($puntos ?? [] as $p): ?>
                                                <option value="<?= $p['id'] ?>"
                                                        data-cod-est="<?= $p['cod_establecimiento'] ?>"
                                                        data-cod-punto="<?= $p['codigo_punto'] ?>">
                                                    <?= $p['cod_establecimiento'] ?>-<?= $p['codigo_punto'] ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <!-- Secuencial -->
                                    <div class="col-md-2 col-6">
                                        <label class="x-small fw-bold text-muted mb-1">Secuencial</label>
                                        <input type="text" name="secuencial" id="ret_secuencial"
                                               class="form-control form-control-sm bg-light text-center py-0"
                                               style="height:31px;" placeholder="000000001" readonly>
                                    </div>

                                    <!-- Período Fiscal -->
                                    <div class="col-md-2 col-6">
                                        <label class="x-small fw-bold text-muted mb-1">Período Fiscal <span class="text-danger">*</span></label>
                                        <input type="text" name="periodo_fiscal" id="ret_periodo_fiscal"
                                               class="form-control form-control-sm border-primary border-opacity-25 text-center py-0"
                                               style="height:31px;" placeholder="MM/YYYY" maxlength="7" readonly>
                                    </div>

                                    <!-- Tipo de Documento -->
                                    <div class="col-md-2 col-6">
                                        <label class="x-small fw-bold text-muted mb-1">Tipo Doc. <span class="text-danger">*</span> <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito($rutaModulo, 'ret_tipo_doc_sustento', 'tipo_doc_sustento') ?></label>
                                        <select name="tipo_doc_sustento" id="ret_tipo_doc_sustento"
                                                class="form-select form-select-sm border-primary border-opacity-25"
                                                style="height:31px;" onchange="window.RET_filtrarSustentos(this.value)">
                                            <option value="01">01 - Factura</option>
                                            <option value="03">03 - Liquidación de compra</option>
                                            <option value="05">05 - Nota de débito</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Segunda Línea: Proveedor y Datos del Documento -->
                                <div class="row g-2 align-items-end">
                                    <!-- Proveedor -->
                                    <div class="col-md-9 position-relative">
                                        <label class="x-small fw-bold text-muted mb-1">Proveedor (Sujeto Retenido) <span class="text-danger">*</span></label>
                                        <div class="input-group input-group-sm rounded-pill overflow-hidden border border-primary border-opacity-25">
                                            <span class="input-group-text bg-white border-0 text-primary"><i class="fa-solid fa-magnifying-glass"></i></span>
                                            <input type="text" class="form-control border-0 px-1" id="ret_proveedor_search"
                                                   placeholder="Buscar por RUC o Razón Social..." autocomplete="off">
                                            <input type="hidden" name="id_proveedor" id="ret_id_proveedor">
                                        </div>
                                        <div id="ret_proveedor_dropdown"
                                             class="list-group shadow dropdown-predictivo position-absolute d-none"
                                             style="z-index:1050;width:100%;max-height:250px;overflow-y:auto;top:55px;"></div>
                                    </div>

                                    <!-- Nº Doc Retenido -->
                                    <div class="col-md-3">
                                        <label class="x-small fw-bold text-muted mb-1">Nº Doc. Retenido <span class="text-danger">*</span></label>
                                        <input type="text" name="num_doc_sustento" id="ret_num_doc_sustento"
                                               class="form-control form-control-sm border-primary border-opacity-25 py-0"
                                               style="height:31px;" placeholder="001-001-000000001" maxlength="17">
                                    </div>
                                </div>

                                <!-- Información extra del proveedor (oculta hasta seleccionar) -->
                                <div id="ret_proveedor_info" class="mt-2 small text-muted d-none">
                                    <div class="d-flex flex-wrap gap-3 px-2">
                                        <span><i class="fa-solid fa-id-card me-1"></i><span id="ret_lbl_proveedor_ruc"></span></span>
                                        <span><i class="fa-solid fa-location-dot me-1"></i><span id="ret_lbl_proveedor_direccion"></span></span>
                                        <span><i class="fa-solid fa-envelope me-1"></i><span id="ret_lbl_proveedor_email"></span></span>
                                    </div>
                                </div>
                            </div>



                            <!-- Líneas de Retención -->
                            <div class="px-3 py-2">
                                <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                                    <div class="table-responsive" style="max-height: 350px;">
                                        <table class="table table-sm table-detalle mb-0 text-nowrap">
                                            <thead>
                                                <tr class="table-light border-bottom">
                                                    <th class="ps-3 py-2 small fw-bold text-muted" style="width: 12%;">Código</th>
                                                    <th class="py-2 small fw-bold text-muted">Concepto</th>
                                                    <th class="py-2 small fw-bold text-muted" style="width: 12%;">Impuesto</th>
                                                    <th class="py-2 small fw-bold text-muted text-end" style="width: 15%;">Base Imponible</th>
                                                    <th class="py-2 small fw-bold text-muted text-center" style="width: 10%;">% Ret.</th>
                                                    <th class="py-2 small fw-bold text-muted text-end pe-4" style="width: 15%;">Valor Ret.</th>
                                                    <th style="width: 40px;"></th>
                                                </tr>
                                            </thead>
                                            <tbody id="ret_lineas_body">
                                                <tr id="ret_lineas_empty">
                                                    <td colspan="7" class="text-center py-5 text-muted">
                                                        <i class="fa-regular fa-file-lines fs-3 d-block mb-2"></i>Agregue al menos una línea de retención.
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="p-2 border-top bg-light d-flex justify-content-between align-items-center">
                                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" onclick="window.RET_agregarLinea()">
                                            <i class="fa-solid fa-plus-circle me-1"></i> Agregar línea
                                        </button>
                                        <div class="small fw-bold text-muted pe-3">
                                            Líneas: <span id="ret_count_items">0</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Totales -->
                            <div class="px-3 py-1 border-top bg-light">
                                <div class="row g-2 justify-content-between align-items-start">
                                    <!-- Documento sustento (izquierda): se autocompleta si hay compra/liquidación;
                                         si el documento NO está registrado, se captura aquí. -->
                                    <div class="col-md-6">
                                        <div class="bg-white border rounded p-2 shadow-sm" style="font-size:0.78rem;">
                                            <div class="fw-bold text-muted mb-2">
                                                <i class="fa-solid fa-receipt me-1"></i>Documento sustento
                                                <span class="fw-normal" style="font-size:0.72rem;">(si no está registrado)</span>
                                            </div>
                                            <div class="row g-2">
                                                <div class="col-4">
                                                    <label class="d-block text-muted mb-1" style="font-size:0.72rem;">Subtotal (Base Imp.)</label>
                                                    <input type="number" step="0.01" min="0" name="doc_sustento_subtotal" id="ret_doc_subtotal"
                                                           class="form-control form-control-sm text-end py-0 w-100"
                                                           style="height:28px;font-size:0.78rem;" placeholder="0.00"
                                                           oninput="window.RET_calcTotalSustento()">
                                                </div>
                                                <div class="col-4">
                                                    <label class="d-block text-muted mb-1" style="font-size:0.72rem;">IVA</label>
                                                    <input type="number" step="0.01" min="0" name="doc_sustento_iva" id="ret_doc_iva"
                                                           class="form-control form-control-sm text-end py-0 w-100"
                                                           style="height:28px;font-size:0.78rem;" placeholder="0.00"
                                                           oninput="window.RET_calcTotalSustento()">
                                                </div>
                                                <div class="col-4">
                                                    <label class="d-block text-muted mb-1 fw-bold" style="font-size:0.72rem;">Total</label>
                                                    <input type="number" step="0.01" min="0" name="doc_sustento_total" id="ret_doc_total"
                                                           class="form-control form-control-sm text-end py-0 bg-light fw-bold w-100"
                                                           style="height:28px;font-size:0.78rem;" placeholder="0.00" readonly>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Sustento tributario (código de sustento, tabla 5 SRI).
                                             Se filtra según el Tipo de Documento (igual que en compras). -->
                                        <div class="mt-2">
                                            <label class="d-block text-muted mb-1" style="font-size:0.72rem;">Sustento tributario</label>
                                            <select name="id_sustento_tributario" id="ret_id_sustento_tributario"
                                                    class="form-select form-select-sm" style="height:28px;font-size:0.74rem;"></select>
                                        </div>
                                        <script>window.RET_SUSTENTOS = <?= json_encode($sustentos ?? [], JSON_UNESCAPED_UNICODE) ?>;</script>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="bg-white border rounded p-2 shadow-sm" style="font-size:0.78rem;">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="text-muted">Total Retenido Renta</span>
                                                <span id="ret_lbl_renta" class="fw-bold">$0.00</span>
                                                <input type="hidden" name="total_retenido_renta" id="ret_total_renta">
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="text-muted">Total Retenido IVA</span>
                                                <span id="ret_lbl_iva" class="fw-bold">$0.00</span>
                                                <input type="hidden" name="total_retenido_iva" id="ret_total_iva">
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="text-muted">Total Retenido ISD</span>
                                                <span id="ret_lbl_isd" class="fw-bold">$0.00</span>
                                                <input type="hidden" name="total_retenido_isd" id="ret_total_isd">
                                            </div>
                                            <hr class="my-1 opacity-25">
                                            <div class="d-flex justify-content-between align-items-center bg-light border py-1 px-2 rounded">
                                                <span class="fw-bold" style="font-size:0.82rem;">TOTAL RETENIDO</span>
                                                <span class="fw-bold" style="font-size:1rem;" id="ret_lbl_total">$0.00</span>
                                                <input type="hidden" name="total_retenido" id="ret_total_retenido">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ── PESTAÑA ASIENTO CONTABLE ──────────────────────────────────── -->
                        <div class="tab-pane fade" id="tab-ret-asiento" role="tabpanel">
                            <div class="px-3 py-3">
                                <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                                    <div class="table-responsive" style="max-height:350px;">
                                        <table class="table table-sm table-detalle mb-0 text-nowrap">
                                            <thead>
                                                <tr class="table-light border-bottom">
                                                    <th class="ps-3 py-2 small fw-bold text-muted" style="width:14%;">Código</th>
                                                    <th class="py-2 small fw-bold text-muted">Cuenta</th>
                                                    <th class="py-2 small fw-bold text-muted text-end" style="width:16%;">Debe</th>
                                                    <th class="py-2 small fw-bold text-muted text-end pe-3" style="width:16%;">Haber</th>
                                                </tr>
                                            </thead>
                                            <tbody id="ret_asiento_body">
                                                <tr><td colspan="4" class="text-center py-4 text-muted">Guarde la retención para generar el asiento contable.</td></tr>
                                            </tbody>
                                            <tfoot>
                                                <tr class="table-light border-top fw-bold">
                                                    <td colspan="2" class="text-end pe-2">Totales</td>
                                                    <td class="text-end" id="ret_asiento_total_debe">0.00</td>
                                                    <td class="text-end pe-3" id="ret_asiento_total_haber">0.00</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                    <div class="px-3 py-2 border-top bg-light small" id="ret_asiento_aviso"></div>
                                </div>
                            </div>
                        </div><!-- /tab-ret-asiento -->

                        <!-- ── PESTAÑA SRI ──────────────────────────────────────────────── -->
                        <div class="tab-pane fade px-3 py-2" id="tab-ret-sri" role="tabpanel">
                            <div class="row g-3">
                                <!-- Estado de autorización -->
                                <div class="col-12">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span class="fw-bold small text-muted">Estado de autorización:</span>
                                        <span id="ret-sri-badge-estado" class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2">Sin enviar</span>
                                    </div>
                                </div>
                                <!-- Reglas SRI para anulación -->
                                <div class="col-12">
                                    <div class="border rounded-2 bg-warning bg-opacity-10 border-warning border-opacity-25 p-2">
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <i class="bi bi-info-circle-fill text-warning"></i>
                                            <span class="fw-bold small text-warning-emphasis">Plazos permitidos para anular una retención</span>
                                        </div>
                                        <ul class="mb-0 ps-3 small text-muted" style="line-height:1.6;">
                                            <li><strong>Tiempo límite:</strong> Hasta el día <strong>7 del mes siguiente</strong> a la fecha de emisión del documento.</li>
                                            <li><strong>Excepciones:</strong> Si el día 7 cae en fin de semana o feriado, el plazo se extiende al siguiente día hábil.</li>
                                            <li><strong>Aceptación:</strong> El emisor debe solicitar la anulación y el receptor tiene <strong>5 días hábiles</strong> para confirmarla. Si no responde, la solicitud se cancela y el comprobante mantiene su validez.</li>
                                            <li><strong>Emisores:</strong> Deben informar al receptor sobre cualquier cambio o modificación realizada al estado del documento electrónico.</li>
                                        </ul>
                                    </div>
                                </div>
                                <!-- Fila 1: Clave de Acceso + Número de Autorización -->
                                <div class="col-md-6">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-key me-1"></i>Clave de Acceso</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" id="ret-sri-clave-acceso" class="form-control form-control-sm font-monospace bg-light" readonly placeholder="- sin clave de acceso -">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.RET_copiarCampoSri('ret-sri-clave-acceso')" title="Copiar clave de acceso">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-hash me-1"></i>Número de Autorización</label>
                                    <input type="text" id="ret-sri-autorizacion" class="form-control form-control-sm font-monospace bg-light" readonly placeholder="- pendiente -">
                                </div>
                                <!-- Fila 2: Tipo de Ambiente + Tipo de Emisión + Fecha de Autorización + Tipo de Documento -->
                                <div class="col-md-3">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-building me-1"></i>Tipo de Ambiente</label>
                                    <input type="text" id="ret-sri-ambiente" class="form-control form-control-sm bg-light" readonly placeholder="- pendiente -">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-broadcast me-1"></i>Tipo de Emisión</label>
                                    <input type="text" id="ret-sri-tipo-emision" class="form-control form-control-sm bg-light" readonly placeholder="- pendiente -">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-calendar-check me-1"></i>Fecha de Autorización</label>
                                    <input type="text" id="ret-sri-fecha-autorizacion" class="form-control form-control-sm bg-light" readonly placeholder="- pendiente -">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-file-earmark-text me-1"></i>Tipo de Documento</label>
                                    <input type="text" id="ret-sri-tipo-documento" class="form-control form-control-sm bg-light" readonly value="Retención">
                                </div>
                                <!-- Fila 3: Número de Documento + Número de Identificación + Correo del Proveedor -->
                                <div class="col-md-3">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-receipt me-1"></i>Número de Documento</label>
                                    <input type="text" id="ret-sri-numero-documento" class="form-control form-control-sm font-monospace bg-light" readonly placeholder="000-000-000000000">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-person-vcard me-1"></i>Número de Identificación</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" id="ret-sri-identificacion-proveedor" class="form-control form-control-sm bg-light" readonly placeholder="- sin identificación -">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.RET_copiarCampoSri('ret-sri-identificacion-proveedor')" title="Copiar identificación">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-envelope me-1"></i>Correo del Proveedor</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" id="ret-sri-correo-proveedor" class="form-control form-control-sm bg-light" readonly placeholder="- sin correo -">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.RET_copiarCampoSri('ret-sri-correo-proveedor')" title="Copiar correo">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                </div>
                                <!-- Historial de envíos SRI -->
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
                                            <tbody id="ret-sri-tbody-historial">
                                                <tr>
                                                    <td colspan="4" class="text-center py-3 text-muted">Sin historial de envíos.</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>



                        </div><!-- /tab-pane SRI -->
                    </div><!-- /tab-content -->
                </form>
            </div>

            <!-- Footer del Modal -->
            <div class="modal-footer justify-content-between bg-light border-top p-2">
                <div>
                    <button type="button" id="ret-btn-eliminar" class="btn btn-outline-danger btn-sm px-3 d-none" onclick="window.RET_eliminar()">
                        <i class="bi bi-trash3 me-1"></i> Eliminar
                    </button>
                </div>
                <div>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                        <i class="fa-solid fa-xmark me-1"></i>Cerrar
                    </button>
                    <?php if ($perm['crear'] || $perm['actualizar']): ?>
                    <button type="button" id="ret-btn-guardar" class="btn btn-primary px-4 btn-sm" onclick="window.RET_guardar()">
                        <i class="bi bi-check2-circle me-1"></i> Guardar
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
