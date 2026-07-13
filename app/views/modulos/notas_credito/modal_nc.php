<?php

/** @var array $perm */
/** @var string $rutaModulo */
/** @var array $vistaConfig */

$vistaConfigNC = \App\Helpers\PreferenciasHelper::getPreferenciasVista('notas_credito');
?>
<!-- Modal para Nueva/Editar Nota de Crédito -->
<div class="modal fade modal-nc" id="modalNC" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title fs-6 fw-bold" id="modalNCTitulo">
                    <i class="bi bi-file-earmark-minus text-primary me-2"></i>Nueva Nota de Crédito
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <form id="formNC">
                    <input type="hidden" name="id" id="nc_id">

                    <!-- Barra de Acciones Superior -->
                    <div class="px-3 py-2 bg-light border-bottom d-flex gap-1 align-items-center flex-wrap">
                        <button id="nc-btn-sri" type="button" class="btn btn-outline-primary btn-sm" onclick="window.NC_enviarSRI()" disabled><i class="bi bi-cloud-arrow-up me-1"></i>Enviar al SRI</button>
                        <div class="vr mx-1"></div>
                        <button id="nc-btn-pdf" type="button" class="btn btn-outline-danger btn-sm px-2" onclick="window.NC_exportarPdf()" title="Exportar PDF" disabled><i class="bi bi-file-earmark-pdf"></i></button>
                        <button id="nc-btn-xml" type="button" class="btn btn-outline-success btn-sm px-2" onclick="window.NC_exportarXml()" title="Exportar XML" disabled><i class="bi bi-file-earmark-code"></i></button>
                        <button id="nc-btn-correo" type="button" class="btn btn-outline-info btn-sm px-2" onclick="window.NC_enviarPorCorreo()" title="Enviar por correo" disabled><i class="bi bi-envelope"></i></button>
                        <button id="btnAnularNC" type="button" class="btn btn-outline-warning btn-sm px-2 d-none" onclick="window.NC_anular()" title="Anular"><i class="bi bi-slash-circle me-1"></i>Anular</button>
                        <div class="vr mx-1"></div>
                        <button type="button" class="btn btn-outline-primary btn-sm px-2 nc-edit-only" onclick="window.NC_abrirModalClienteCrear()" title="Registrar nuevo cliente"><i class="bi bi-person-plus fs-6"></i></button>


                    </div>

                    <!-- Pestañas -->
                    <div class="d-flex align-items-center bg-light px-3 pt-2">
                        <ul class="nav nav-tabs border-bottom-0 flex-grow-1 tab-pestaña" id="ncTabs" role="tablist">
                            <li class="nav-item"><a class="nav-link active py-2 small" id="tab-nc-principal-btn" data-bs-toggle="tab" href="#tab-nc-principal" role="tab" style="white-space: nowrap;"><i class="bi bi-file-earmark-minus me-1"></i> Nota de crédito</a></li>
                            <li class="nav-item"><a class="nav-link py-2 small" id="tab-nc-contable-btn" data-bs-toggle="tab" href="#tab-nc-contable" role="tab" style="white-space: nowrap;"><i class="bi bi-calculator me-1"></i> Asiento contable</a></li>
                            <li class="nav-item"><a class="nav-link py-2 small" id="tab-nc-sri-btn" data-bs-toggle="tab" href="#tab-nc-sri" role="tab" style="white-space: nowrap;"><i class="bi bi-cloud-check me-1"></i> SRI</a></li>
                        </ul>
                        <div class="ms-auto pb-1">
                            <?php
                            $pestanasConfigNC = [
                                'tab-nc-contable' => 'Asiento contable',
                                'tab-nc-sri'      => 'SRI'
                            ];
                            echo \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasConfigNC, $vistaConfigNC ?? [], 'notas_credito');
                            ?>
                        </div>
                    </div>

                    <div class="tab-content border-top">
                        <!-- Pestaña Principal: Nota de Crédito -->
                        <div class="tab-pane fade show active" id="tab-nc-principal" role="tabpanel">
                            <div class="p-3 bg-white border-bottom">
                                <div class="row g-2">
                                    <div class="col-12">
                                        <div class="row g-2 align-items-end">
                                            <!-- 1. Fecha -->
                                            <div class="col-md-2">
                                                <label class="x-small fw-bold text-muted mb-1">Fecha Emisión</label>
                                                <input type="date" name="fecha_emision" id="nc_fecha_emision" class="form-control form-control-sm border-primary border-opacity-10 py-0" style="height: 31px;" value="<?= date('Y-m-d') ?>">
                                            </div>
                                            <!-- 2. Serie -->
                                            <div class="col-md-2">
                                                <label class="x-small fw-bold text-muted mb-1 d-flex align-items-center">Serie <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('notas_credito', 'nc_id_punto_emision', 'id_punto_emision') ?></label>
                                                <select name="id_punto_emision" id="nc_id_punto_emision" class="form-select form-select-sm border-primary border-opacity-25" onchange="window.NC_cargarSecuencial()" style="height: 31px;">
                                                    <?php foreach ($puntos as $p): ?>
                                                        <option value="<?= $p['id'] ?>" data-cod-est="<?= $p['cod_establecimiento'] ?>" data-cod-punto="<?= $p['codigo_punto'] ?>">
                                                            <?= $p['cod_establecimiento'] ?>-<?= $p['codigo_punto'] ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <!-- 3. Secuencial -->
                                            <div class="col-md-2">
                                                <label class="x-small fw-bold text-muted mb-1">Secuencial</label>
                                                <input type="text" name="secuencial" id="nc_secuencial" class="form-control form-control-sm border-primary border-opacity-25 text-center py-0 bg-light" style="height: 31px;" placeholder="000000001" readonly>
                                            </div>
                                            <!-- 4. Bodega (para reintegrar stock) -->
                                            <div class="col-md-3">
                                                <label class="x-small fw-bold text-muted mb-1 d-flex align-items-center">Bodega Reintegro <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('notas_credito', 'nc_id_bodega', 'id_bodega') ?></label>
                                                <select name="id_bodega" id="nc_id_bodega" class="form-select form-select-sm border-primary border-opacity-10" style="height: 31px;">
                                                    <?php foreach ($bodegas as $b): ?>
                                                        <option value="<?= $b['id'] ?>" <?= !empty($b['es_default']) ? 'selected' : '' ?>><?= htmlspecialchars($b['nombre']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <!-- 5. Motivo -->
                                            <div class="col-md-3">
                                                <label class="x-small fw-bold text-muted mb-1">Motivo SRI</label>
                                                <input type="text" name="motivo" id="nc_motivo" class="form-control form-control-sm" placeholder="Motivo de la NC" style="height: 31px;">
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
                                                        <input type="text" class="form-control border-0 px-1" id="nc_cliente_search" placeholder="Buscar cliente por RUC o Razón Social..." autocomplete="off">
                                                        <input type="hidden" name="id_cliente" id="nc_id_cliente">
                                                    </div>
                                                    <div id="nc_cliente_dropdown" class="list-group shadow dropdown-predictivo position-absolute d-none" style="z-index: 1050; width: 100%; max-height: 250px; overflow-y: auto; right: 0px; top: 55px;"></div>
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
                                                        <input type="text" class="form-control border-0 px-1" id="nc_factura_search" name="num_doc_modificado" placeholder="Seleccione un cliente primero..." autocomplete="off" maxlength="17" inputmode="numeric" disabled>
                                                    </div>
                                                    <div id="nc_factura_dropdown" class="list-group shadow dropdown-predictivo position-absolute d-none" style="z-index: 1050; width: 100%; max-height: 250px; overflow-y: auto; right: 0px; top: 55px;"></div>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="x-small fw-bold text-muted mb-1">Fecha del documento</label>
                                                    <input type="date" name="fecha_emision_docs_sustento" id="nc_fecha_emision_docs_sustento" class="form-control form-control-sm border-primary border-opacity-25 py-0" style="height: 31px;" disabled>
                                                </div>
                                                <div class="col-md-4">
                                                    <div id="nc_info_factura_modificada" class="small text-muted">
                                                        <!-- Se llena vía JS -->
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="x-small text-muted mt-1 ps-1">
                                                <i class="bi bi-info-circle me-1"></i>Elija una factura del cliente o escriba el número manualmente si no figura en el listado.
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Info Detallada Cliente (Se muestra al seleccionar) -->
                                    <div id="nc_info_cliente" class="col-12 mt-2 d-none">
                                        <div class="p-2 border rounded-3 bg-light bg-opacity-50 border-primary border-opacity-10">
                                            <div class="row g-2 align-items-center">
                                                <div class="col-md-4">
                                                    <div class="d-flex align-items-center gap-1">
                                                        <i class="bi bi-card-text text-muted"></i>
                                                        <span id="nc_lbl_cliente_ruc" class="fw-bold small text-dark"></span>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="d-flex align-items-center gap-1">
                                                        <i class="bi bi-geo-alt text-muted"></i>
                                                        <span id="nc_lbl_cliente_direccion" class="small text-muted text-truncate d-inline-block" style="max-width: 200px;"></span>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="d-flex align-items-center gap-1">
                                                        <i class="bi bi-envelope text-muted"></i>
                                                        <span id="nc_lbl_cliente_correo" class="small text-muted text-truncate d-inline-block" style="max-width: 200px;"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>

                            <!-- Tabla de Detalles -->
                            <div class="p-3">
                                <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                                    <div class="table-responsive" style="max-height: 300px;">
                                        <table class="table table-sm table-detalle mb-0">
                                            <thead>
                                                <tr class="table-light border-bottom">
                                                    <th class="ps-3 py-2 small fw-bold text-muted" style="width: 40%;">Descripción</th>
                                                    <th class="py-2 small fw-bold text-muted text-center" style="width: 10%;">Cant.</th>
                                                    <th class="py-2 small fw-bold text-muted text-end" style="width: 12%;">P. Unitario</th>
                                                    <th class="py-2 small fw-bold text-muted text-end" style="width: 10%;">Desc.</th>
                                                    <th class="py-2 small fw-bold text-muted text-center" style="width: 10%;">IVA</th>
                                                    <th class="py-2 small fw-bold text-muted text-end pe-4" style="width: 15%;">Subtotal</th>
                                                    <th style="width: 40px;"></th>
                                                </tr>
                                            </thead>
                                            <tbody id="nc_detalles_body">
                                                <tr>
                                                    <td colspan="7" class="text-center py-4 text-muted">Seleccione una factura para cargar los detalles.</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="p-2 border-top bg-light nc-edit-only">
                                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold ms-2" onclick="window.NC_agregarFila()">
                                            <i class="bi bi-plus-circle me-1"></i> Agregar línea manual
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Pie: Información Adicional (izquierda) + Totales (derecha) -->
                            <div class="p-3 border-top bg-light">
                                <div class="row g-3">
                                    <!-- Izquierda: Información Adicional -->
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
                                                    <tbody id="nc-tbody-info-adicional"></tbody>
                                                </table>
                                            </div>
                                            <div class="p-1 border-top bg-light nc-edit-only">
                                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold ms-2" onclick="window.NC_agregarInfoAdicional()">
                                                    <i class="bi bi-plus-circle me-1"></i> Agregar línea
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Derecha: Totales Verticales -->
                                    <div class="col-md-4">
                                        <div class="bg-white border rounded p-2 shadow-sm" style="font-size:0.75rem;">

                                            <!-- Subtotal General -->
                                            <div class="d-flex justify-content-between align-items-center mb-1 fw-bold border-bottom pb-1">
                                                <span class="text-muted">Subtotal</span>
                                                <span id="nc_lbl_subtotal">0.00</span>
                                                <input type="hidden" name="total_sin_impuestos" id="nc_total_sin_impuestos">
                                            </div>

                                            <!-- Subtotales agrupados por tarifa IVA -->
                                            <div id="nc_lbl_subtotales_iva" class="mb-1"></div>

                                            <!-- Total Descuento -->
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="text-muted">(-) Descuento</span>
                                                <span class="fw-bold text-dark" id="nc_lbl_descuento">0.00</span>
                                                <input type="hidden" name="total_descuento" id="nc_total_descuento">
                                            </div>

                                            <!-- IVA agrupado por tarifa -->
                                            <div id="nc_lbl_ivas_grupo" class="mb-1"></div>

                                            <hr class="my-1 opacity-25">

                                            <!-- Total Nota de Crédito -->
                                            <div class="d-flex justify-content-between align-items-center bg-light border py-1 px-2 rounded">
                                                <span class="fw-bold text-dark" style="font-size:0.8rem;">TOTAL</span>
                                                <span class="fw-bold text-dark" style="font-size:1rem;" id="nc_lbl_total">0.00</span>
                                                <input type="hidden" name="importe_total" id="nc_importe_total">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pestaña: Asiento Contable -->
                        <div class="tab-pane fade p-3" id="tab-nc-contable" role="tabpanel">
                            <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                                <div class="table-responsive" style="max-height: 350px;">
                                    <table class="table table-sm table-detalle mb-0 text-nowrap" id="nc-table-asiento">
                                        <thead>
                                            <tr class="table-light border-bottom">
                                                <th class="ps-3 py-2 small fw-bold text-muted" style="width:45%;">Cuenta Contable</th>
                                                <th class="py-2 small fw-bold text-muted text-end pe-3" style="width:20%;">D&eacute;bito / Debe</th>
                                                <th class="py-2 small fw-bold text-muted text-end pe-3" style="width:20%;">Cr&eacute;dito / Haber</th>
                                                <th class="py-2 small fw-bold text-muted" style="width:15%;">Referencia</th>
                                                <th style="width:40px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="nc-asiento-tbody">
                                            <tr><td colspan="5" class="text-center py-4 text-muted">Guarda la nota de cr&eacute;dito para generar el asiento contable.</td></tr>
                                        </tbody>
                                        <tfoot class="bg-light fw-bold border-top sticky-bottom">
                                            <tr>
                                                <td class="text-end py-2">Totales:</td>
                                                <td class="text-end pe-3 py-2 text-primary" id="nc-asiento-debe">0.00</td>
                                                <td class="text-end pe-3 py-2 text-primary" id="nc-asiento-haber">0.00</td>
                                                <td colspan="2" class="py-2">
                                                    <div class="d-flex align-items-center gap-2 justify-content-end pe-3">
                                                        <span class="x-small text-muted">Diferencia: <span id="nc-asiento-dif">0.00</span></span>
                                                        <span id="nc-asiento-badge" class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2">Cuadrado</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <div class="p-2 border-top bg-light d-flex justify-content-between align-items-center">
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" id="nc-asiento-add">
                                        <i class="bi bi-plus-circle me-1"></i> Agregar l&iacute;nea
                                    </button>
                                    <div class="small fw-bold text-muted pe-3">L&iacute;neas: <span id="nc-asiento-count">0</span></div>
                                </div>
                            </div>
                            <div class="px-1 pt-2 small text-muted" id="nc-asiento-status"></div>
                        </div>

                        <!-- Pestaña: SRI -->
                        <div class="tab-pane fade p-3" id="tab-nc-sri" role="tabpanel">
                            <div class="row g-3">
                                <!-- Estado de autorización -->
                                <div class="col-12">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span class="fw-bold small text-muted">Estado de autorización:</span>
                                        <span id="nc-sri-badge-estado" class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2">Sin enviar</span>
                                    </div>
                                </div>
                                <!-- Reglas SRI para anulación -->
                                <div class="col-12">
                                    <div class="border rounded-2 bg-warning bg-opacity-10 border-warning border-opacity-25 p-2">
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <i class="bi bi-info-circle-fill text-warning"></i>
                                            <span class="fw-bold small text-warning-emphasis">Plazos permitidos para anular una nota de crédito</span>
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
                                        <input type="text" id="nc-sri-clave-acceso" class="form-control form-control-sm font-monospace bg-light" readonly placeholder="- sin clave de acceso -" value="">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.NC_copiarCampoSri('nc-sri-clave-acceso')" title="Copiar clave de acceso">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-hash me-1"></i>Número de Autorización</label>
                                    <input type="text" id="nc-sri-autorizacion" class="form-control form-control-sm font-monospace bg-light" readonly placeholder="- pendiente -" value="">
                                </div>
                                <!-- Fila 2: Tipo de Ambiente + Tipo de Emisión + Fecha de Autorización + Tipo de Documento -->
                                <div class="col-md-3">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-building me-1"></i>Tipo de Ambiente</label>
                                    <input type="text" id="nc-sri-ambiente" class="form-control form-control-sm bg-light" readonly placeholder="- pendiente -" value="">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-broadcast me-1"></i>Tipo de Emisión</label>
                                    <input type="text" id="nc-sri-tipo-emision" class="form-control form-control-sm bg-light" readonly placeholder="- pendiente -" value="">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-calendar-check me-1"></i>Fecha de Autorización</label>
                                    <input type="text" id="nc-sri-fecha-autorizacion" class="form-control form-control-sm bg-light" readonly placeholder="- pendiente -" value="">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-file-earmark-text me-1"></i>Tipo de Documento</label>
                                    <input type="text" id="nc-sri-tipo-documento" class="form-control form-control-sm bg-light" readonly value="Nota de Crédito">
                                </div>
                                <!-- Fila 3: Número de Documento + Número de Identificación + Correo del Cliente -->
                                <div class="col-md-3">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-receipt me-1"></i>Número de Documento</label>
                                    <input type="text" id="nc-sri-numero-documento" class="form-control form-control-sm font-monospace bg-light" readonly placeholder="000-000-000000000" value="">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-person-vcard me-1"></i>Número de Identificación</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" id="nc-sri-identificacion-cliente" class="form-control form-control-sm bg-light" readonly placeholder="- sin identificación -" value="">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.NC_copiarCampoSri('nc-sri-identificacion-cliente')" title="Copiar identificación">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-envelope me-1"></i>Correo del Cliente</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" id="nc-sri-correo-cliente" class="form-control form-control-sm bg-light" readonly placeholder="- sin correo -" value="">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.NC_copiarCampoSri('nc-sri-correo-cliente')" title="Copiar correo">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                </div>
                                <!-- Historial de Envíos SRI -->
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
                                            <tbody id="nc-sri-tbody-historial">
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
                    <button type="button" class="btn btn-sm btn-outline-danger px-3 d-none" id="btnEliminarNC" onclick="window.NC_eliminar()">
                        <i class="bi bi-trash3 me-1"></i> Eliminar
                    </button>
                </div>
                <div>
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">
                        <i class="fa-solid fa-xmark me-1"></i>Cerrar
                    </button>
                    <button type="button" class="btn btn-sm btn-primary px-4" id="btnGuardarNC" onclick="window.NC_guardar()">
                        <i class="bi bi-check2-circle me-1"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .modal-nc .x-small {
        font-size: 0.72rem;
    }

    .modal-nc .dropdown-predictivo {
        z-index: 2000 !important;
    }

    .modal-nc .modal-header {
        padding: 0.75rem 1rem;
    }

    .modal-nc .modal-body {
        padding: 0 !important;
    }

    .modal-nc label {
        font-size: 0.85rem;
        font-weight: 600;
        color: #495057;
        margin-bottom: 3px !important;
    }

    .modal-nc .input-detalle {
        border: 1px solid #dee2e6;
        background: #fff;
        width: 100%;
        padding: 4px 8px;
        font-size: 0.82rem;
        border-radius: 4px;
        height: 32px !important;
    }

    .modal-nc .input-detalle:focus {
        border-color: var(--bs-primary);
        outline: none;
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
    }

    .modal-nc .row-det:hover {
        background-color: rgba(13, 110, 253, 0.02) !important;
    }

    /* Modo solo lectura: ocultar controles de edición (agregar/eliminar/guardar) */
    #modalNC.nc-lectura .nc-edit-only {
        display: none !important;
    }
</style>