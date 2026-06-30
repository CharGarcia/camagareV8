<?php

/** @var array  $perm */
/** @var string $rutaModulo */
/** @var array  $vistaConfig */
/** @var array  $puntos */
/** @var string $base */
?>

<!-- ═══════════════════════ MODAL GUÍA DE REMISIÓN ═══════════════════════ -->
<div class="modal fade modal-gr" id="modalGuiaRemision" tabindex="-1" aria-labelledby="modalGRLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-truck text-primary fs-5"></i>
                    <h6 class="modal-title fw-bold mb-0" id="modalGRLabel">
                        <span id="gr-modal-titulo">Nueva Guía de Remisión</span>
                        <span id="gr-numero-badge" class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 ms-2" style="display:none;font-size:0.75rem"></span>
                    </h6>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-0">
                <!-- BARRA DE ACCIONES -->
                <div id="gr-acciones-existente" class="px-3 py-2 bg-light border-0 d-none d-flex gap-1 align-items-center flex-wrap">
                    <?php if ($perm['actualizar']): ?>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="btn-gr-enviar-sri" onclick="GR_enviarSri()">
                            <i class="bi bi-cloud-arrow-up me-1"></i>Enviar al SRI
                        </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline-danger btn-sm px-2" onclick="GR_exportarPdf()" title="Exportar PDF">
                        <i class="bi bi-file-earmark-pdf"></i>
                    </button>
                    <button type="button" class="btn btn-outline-success btn-sm px-2" onclick="GR_exportarXml()" title="Exportar XML">
                        <i class="bi bi-file-earmark-code"></i>
                    </button>
                    <?php if ($perm['actualizar']): ?>
                        <button type="button" class="btn btn-outline-warning btn-sm" id="btn-gr-anular" onclick="GR_anular()">
                            <i class="bi bi-slash-circle me-1"></i>Anular
                        </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline-primary btn-sm px-2"
                        onclick="TR_abrirCrear()" title="Nuevo transportista">
                        <i class="bi bi-person-plus fs-6"></i>
                    </button>
                </div>

                <!-- PESTAÑAS -->
                <div class="d-flex align-items-center bg-light px-3 pt-2">
                    <ul class="nav nav-tabs border-bottom-0 flex-grow-1 tab-pestaña" id="gr-tabs" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active fw-medium py-2 border-0" id="tab-gr-guia-btn"
                                data-bs-toggle="tab" data-bs-target="#gr-tab-guia" type="button" role="tab" style="white-space:nowrap">
                                <i class="bi bi-truck me-1"></i> Guía de Remisión
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link fw-medium py-2 border-0" id="tab-gr-sri-btn"
                                data-bs-toggle="tab" data-bs-target="#gr-tab-sri" type="button" role="tab" style="white-space:nowrap">
                                <i class="bi bi-cloud-check me-1"></i> SRI
                            </button>
                        </li>
                    </ul>
                    <div class="ms-auto pb-1">
                        <?php
                        $pestanasGR = [
                            'tab-gr-sri-btn' => 'SRI',
                        ];
                        echo \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasGR, $vistaConfig ?? [], $rutaModulo);
                        ?>
                    </div>
                </div>
                <input type="hidden" id="gr-id">
                <div class="tab-content px-3 py-3">
                    <!-- ═══ TAB: GUÍA DE REMISIÓN ═══ -->
                    <div class="tab-pane fade show active" id="gr-tab-guia" role="tabpanel">
                        <div class="mb-0">

                            <!-- Fila 1: Fecha emisión | Serie | Secuencial -->
                            <div class="row g-2 mb-2 align-items-end">
                                <div class="col-md-2">
                                    <label class="form-label">Fecha emisión <span class="text-danger">*</span></label>
                                    <input type="date" id="gr-fecha-emision" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Serie <span class="text-danger">*</span></label>
                                    <select id="gr-serie" class="form-select form-select-sm" onchange="GR_actualizarSecuencial()">
                                        <?php foreach ($puntos as $p): ?>
                                            <option value="<?= $p['id'] ?>"
                                                data-id-est="<?= $p['id_establecimiento'] ?>"
                                                data-cod-est="<?= htmlspecialchars($p['cod_establecimiento'] ?? '001') ?>"
                                                data-cod-punto="<?= htmlspecialchars($p['codigo_punto'] ?? '001') ?>">
                                                <?= htmlspecialchars(($p['cod_establecimiento'] ?? '') . '-' . ($p['codigo_punto'] ?? '')) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Secuencial</label>
                                    <input type="text" id="gr-secuencial" class="form-control form-control-sm bg-light" readonly placeholder="000000001">
                                </div>
                                <!-- </div> -->

                                <!-- Fila 2: Tipo documento | Número documento -->
                                <!-- <div class="row g-2 mb-2 align-items-end"> -->
                                <div class="col-md-3">
                                    <label class="form-label">Tipo documento sustento</label>
                                    <select id="gr-cod-doc-sustento" class="form-select form-select-sm">
                                        <option value="">- Sin documento sustento -</option>
                                        <option value="01" selected>01 - Factura</option>
                                        <option value="03">03 - Liquidación de compra de bienes y prestación de servicios</option>
                                        <option value="04">04 - Nota de crédito</option>
                                        <option value="05">05 - Nota de débito</option>
                                        <option value="06">06 - Guía de remisión</option>
                                        <option value="07">07 - Comprobante de retención</option>
                                    </select>
                                </div>
                                <div class="col-md-3 position-relative">
                                    <label class="form-label">Número documento</label>
                                    <input type="text" id="gr-num-doc-sustento" class="form-control form-control-sm"
                                        maxlength="17" placeholder="000-000-000000000" autocomplete="off"
                                        oninput="GR_buscarFacturaSustento(this.value)">
                                    <div id="gr-dropdown-factura" class="dropdown-gr position-absolute bg-white border rounded shadow-sm"
                                        style="display:none;max-height:200px;overflow-y:auto;top:100%;left:0;right:0;z-index:2000"></div>
                                </div>
                            </div>

                            <!-- Fila 3: Destinatario | Fecha salida | Fecha llegada -->
                            <div class="row g-2 mb-4 align-items-start">
                                <div class="col-md-8 position-relative">
                                    <label class="form-label">Cliente / Destinatario <span class="text-danger">*</span></label>
                                    <div class="input-group input-group-sm rounded-pill overflow-hidden border border-secondary border-opacity-10">
                                        <span class="input-group-text bg-white border-0 text-primary px-2"><i class="bi bi-search"></i></span>
                                        <input type="text" class="form-control border-0 px-1" id="gr-search-cliente"
                                            placeholder="Buscar por nombre o identificación..." autocomplete="off"
                                            oninput="GR_buscarCliente(this.value)">
                                        <input type="hidden" id="gr-id-cliente">
                                    </div>
                                    <div id="gr-dropdown-cliente" class="dropdown-gr position-absolute bg-white border rounded shadow-sm"
                                        style="display:none;max-height:200px;overflow-y:auto;top:100%;left:0;right:0;z-index:2000"></div>
                                    <div id="gr-info-cliente" class="d-none position-absolute mt-1 w-100" style="font-size:0.72rem;color:#6c757d;z-index:10;">
                                        <span class="fw-bold text-dark me-2" id="gr-lbl-cliente-ruc"></span>
                                        <i class="bi bi-geo-alt"></i> <span id="gr-lbl-cliente-direccion" style="text-transform:lowercase"></span>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Fecha salida <span class="text-danger">*</span></label>
                                    <input type="date" id="gr-fecha-inicio" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Fecha llegada <span class="text-danger">*</span></label>
                                    <input type="date" id="gr-fecha-fin" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                                </div>
                            </div>

                            <!-- Fila 4: Transportista | Placa | Motivo -->
                            <div class="row g-2 mb-4 align-items-start">
                                <div class="col-md-4 position-relative">
                                    <label class="form-label">Transportista <span class="text-danger">*</span></label>
                                    <div class="input-group input-group-sm rounded-pill overflow-hidden border border-secondary border-opacity-10">
                                        <span class="input-group-text bg-white border-0 text-primary px-2"><i class="bi bi-search"></i></span>
                                        <input type="text" class="form-control border-0 px-1" id="gr-search-transportista"
                                            placeholder="Buscar transportista..." autocomplete="off"
                                            oninput="GR_buscarTransportista(this.value)">
                                        <input type="hidden" id="gr-id-transportista">
                                    </div>
                                    <div id="gr-dropdown-transportista" class="dropdown-gr position-absolute bg-white border rounded shadow-sm"
                                        style="display:none;max-height:200px;overflow-y:auto;top:100%;left:0;right:0;z-index:2000"></div>
                                    <div id="gr-info-transportista" class="d-none position-absolute mt-1 w-100" style="font-size:0.7rem;color:#6c757d;z-index:10; line-height: 1.1;">
                                        <span class="fw-bold text-dark me-2" id="gr-lbl-transp-id"></span>
                                        <i class="bi bi-truck"></i> <span id="gr-lbl-transp-placa"></span>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Placa <span class="text-danger">*</span></label>
                                    <input type="text" id="gr-placa" class="form-control form-control-sm" maxlength="8"
                                        placeholder="ABC-1234" style="text-transform:uppercase">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Motivo traslado <span class="text-danger">*</span></label>
                                    <input type="text" id="gr-motivo" class="form-control form-control-sm" maxlength="300"
                                        placeholder="Ej: Venta, Traslado, Devolución...">
                                </div>
                            </div>

                            <!-- Fila 5: Origen | Destino -->
                            <div class="row g-2 mb-2 align-items-end">
                                <div class="col-md-6">
                                    <label class="form-label">Origen <span class="text-danger">*</span></label>
                                    <input type="text" id="gr-partida" class="form-control form-control-sm" maxlength="300"
                                        placeholder="Dirección de partida del traslado">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Destino <span class="text-danger">*</span></label>
                                    <input type="text" id="gr-destino" class="form-control form-control-sm" maxlength="300"
                                        placeholder="Dirección de llegada del traslado">
                                </div>
                            </div>

                            <!-- Fila 6: Ruta | Doc. aduanero | Cód. establecimiento destino -->
                            <div class="row g-2 mb-3 align-items-end">
                                <div class="col-md-8">
                                    <label class="form-label">Ruta <span class="text-danger">*</span></label>
                                    <input type="text" id="gr-ruta" class="form-control form-control-sm" maxlength="300"
                                        placeholder="Ruta durante el traslado">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Doc. aduanero único</label>
                                    <input type="text" id="gr-doc-aduanero" class="form-control form-control-sm" maxlength="20"
                                        placeholder="DAE / DAU">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Cód. est. destino <span class="text-danger">*</span></label>
                                    <input type="text" id="gr-cod-est-destino" class="form-control form-control-sm" maxlength="3"
                                        placeholder="001">
                                </div>
                            </div>



                            <!-- Productos -->
                            <h6 class="fw-semibold small text-muted mb-2"><i class="bi bi-box-seam me-1"></i>Productos</h6>
                            <div class="border rounded-3 overflow-hidden bg-white mb-3">
                                <div class="table-responsive" style="max-height:300px;">
                                    <table class="table table-sm table-detalle mb-0 text-nowrap">
                                        <thead>
                                            <tr class="table-light border-bottom">
                                                <th class="ps-2 py-2 small fw-bold text-muted" style="width:36px">#</th>
                                                <th class="py-2 small fw-bold text-muted" style="width:110px">Código</th>
                                                <th class="py-2 small fw-bold text-muted">Descripción</th>
                                                <th class="py-2 small fw-bold text-muted text-end" style="width:110px">Cantidad</th>
                                                <th style="width:36px"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="gr-tbody-detalle"></tbody>
                                    </table>
                                </div>
                                <div class="p-2 border-top bg-light d-flex justify-content-between align-items-center">
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" id="btn-gr-agregar-linea" onclick="GR_agregarLinea()">
                                        <i class="bi bi-plus-circle me-1"></i> Agregar línea
                                    </button>
                                    <div class="small fw-bold text-muted pe-2">Items: <span id="gr-count-items">0</span></div>
                                </div>
                            </div>

                            <!-- Información adicional -->
                            <div style="max-width:50%">
                                <h6 class="fw-semibold small text-muted mb-2"><i class="bi bi-info-circle me-1"></i>Información Adicional</h6>
                                <div class="border rounded-3 overflow-hidden bg-white">
                                    <div class="table-responsive" style="max-height:200px;">
                                        <table class="table table-sm mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="ps-2 py-0 small fw-bold text-muted" style="width:40%">Nombre</th>
                                                    <th class="py-0 small fw-bold text-muted">Valor</th>
                                                    <th class="py-0" style="width:36px"></th>
                                                </tr>
                                            </thead>
                                            <tbody id="gr-tbody-adicional"></tbody>
                                        </table>
                                    </div>
                                    <div class="p-1 border-top bg-light">
                                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold ms-2" id="btn-gr-agregar-adicional" onclick="GR_agregarAdicionalLinea()">
                                            <i class="bi bi-plus-circle me-1"></i> Agregar línea
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div><!-- /gr-tab-guia -->

                    <!-- ═══ TAB: SRI ═══ -->
                    <div class="tab-pane fade p-3" id="gr-tab-sri" role="tabpanel">
                        <div class="row g-3">
                            <!-- Estado de autorización -->
                            <div class="col-12">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="fw-bold small text-muted">Estado de autorización:</span>
                                    <span id="gr-sri-badge-estado" class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2">Sin enviar</span>
                                </div>
                            </div>
                            <!-- Reglas SRI para anulación -->
                            <div class="col-12">
                                <div class="border rounded-2 bg-warning bg-opacity-10 border-warning border-opacity-25 p-2">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <i class="bi bi-info-circle-fill text-warning"></i>
                                        <span class="fw-bold small text-warning-emphasis">Plazos permitidos para anular una guía de remisión</span>
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
                                    <input type="text" id="gr-sri-clave-acceso" class="form-control form-control-sm font-monospace bg-light" readonly placeholder="- sin clave de acceso -">
                                    <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.GR_copiarCampoSri('gr-sri-clave-acceso')" title="Copiar clave de acceso">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold text-muted mb-1"><i class="bi bi-hash me-1"></i>Número de Autorización</label>
                                <input type="text" id="gr-sri-num-autorizacion" class="form-control form-control-sm font-monospace bg-light" readonly placeholder="- pendiente -">
                            </div>
                            <!-- Fila 2: Tipo de Ambiente + Tipo de Emisión + Fecha de Autorización + Tipo de Documento -->
                            <div class="col-md-3">
                                <label class="small fw-bold text-muted mb-1"><i class="bi bi-building me-1"></i>Tipo de Ambiente</label>
                                <input type="text" id="gr-sri-ambiente" class="form-control form-control-sm bg-light" readonly placeholder="- pendiente -">
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold text-muted mb-1"><i class="bi bi-broadcast me-1"></i>Tipo de Emisión</label>
                                <input type="text" id="gr-sri-tipo-emision" class="form-control form-control-sm bg-light" readonly placeholder="- pendiente -">
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold text-muted mb-1"><i class="bi bi-calendar-check me-1"></i>Fecha de Autorización</label>
                                <input type="text" id="gr-sri-fecha-autorizacion" class="form-control form-control-sm bg-light" readonly placeholder="- pendiente -">
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold text-muted mb-1"><i class="bi bi-file-earmark-text me-1"></i>Tipo de Documento</label>
                                <input type="text" id="gr-sri-tipo-documento" class="form-control form-control-sm bg-light" readonly value="Guía de Remisión">
                            </div>
                            <!-- Fila 3: Número de Documento + Número de Identificación + Correo del Destinatario -->
                            <div class="col-md-3">
                                <label class="small fw-bold text-muted mb-1"><i class="bi bi-receipt me-1"></i>Número de Documento</label>
                                <input type="text" id="gr-sri-numero-documento" class="form-control form-control-sm font-monospace bg-light" readonly placeholder="000-000-000000000">
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold text-muted mb-1"><i class="bi bi-person-vcard me-1"></i>Número de Identificación</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" id="gr-sri-identificacion-cliente" class="form-control form-control-sm bg-light" readonly placeholder="- sin identificación -">
                                    <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.GR_copiarCampoSri('gr-sri-identificacion-cliente')" title="Copiar identificación">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold text-muted mb-1"><i class="bi bi-envelope me-1"></i>Correo del Destinatario</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" id="gr-sri-correo-cliente" class="form-control form-control-sm bg-light" readonly placeholder="- sin correo -">
                                    <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.GR_copiarCampoSri('gr-sri-correo-cliente')" title="Copiar correo">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                            </div>
                            <!-- Historial de Envíos -->
                            <div class="col-12">
                                <label class="small fw-bold text-muted mb-1"><i class="bi bi-clock-history me-1"></i>Historial de Envíos</label>
                                <div class="border rounded-2 overflow-hidden">
                                    <table class="table table-sm small mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-2 py-1 text-muted" style="width:140px">Fecha / Hora</th>
                                                <th class="py-1 text-muted" style="width:80px">Ambiente</th>
                                                <th class="py-1 text-muted" style="width:110px">Acción / Estado</th>
                                                <th class="py-1 text-muted">Mensaje</th>
                                            </tr>
                                        </thead>
                                        <tbody id="gr-sri-tbody-historial">
                                            <tr>
                                                <td colspan="4" class="text-center py-3 text-muted">Sin historial de envíos.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div><!-- /gr-tab-sri -->

                </div><!-- /tab-content -->
            </div><!-- /modal-body -->

            <!-- FOOTER -->
            <div class="modal-footer justify-content-between bg-light border-0 p-2">
                <div>
                    <?php if ($perm['eliminar']): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger px-3 d-none" id="btn-gr-eliminar" onclick="GR_eliminar()">
                            <i class="bi bi-trash3 me-1"></i> Eliminar
                        </button>
                    <?php endif; ?>
                </div>
                <div>
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i>Cerrar
                    </button>
                    <?php if ($perm['crear'] || $perm['actualizar']): ?>
                        <button type="button" class="btn btn-sm btn-primary" id="btn-gr-guardar" onclick="GR_guardar()">
                            <i class="bi bi-check2-circle me-1"></i> Guardar
                        </button>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>