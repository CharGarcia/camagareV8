<?php

/** @var array $perm */
/** @var array $provincias */
/** @var array $tiposFirma */
/** @var array $vistaConfig */

$idUsuarioAct = (int)($_SESSION['id_usuario'] ?? 0);
$idEmpresaAct = (int)($_SESSION['id_empresa'] ?? 0);
$nivelAct     = (int)($_SESSION['nivel'] ?? 1);
$urlBase      = BASE_URL . '/modulos/firmas_electronicas';

$vistaConfigF = \App\Helpers\PreferenciasHelper::getPreferenciasVista('firmas_electronicas');
echo \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfigF, 'estiloVistaPestanasFirma');
?>

<!-- Modal Ficha de Firma Electrónica -->
<div class="modal fade" id="modalFirma" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" style="z-index:1060;">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <form id="formFirmaModal" novalidate onsubmit="return false;">
                <div class="modal-header bg-light py-3">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-pen-fill me-2 text-primary"></i>
                        <span id="tituloModalFirma">Nueva Firma Electrónica</span>
                    </h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body pb-0">
                    <div id="modalAlertFirma" class="alert d-none mb-2 py-2 small shadow-sm border-0 mx-3"></div>
                    <div id="alertaBloqueoFirma" class="alert alert-warning d-none mb-3 py-2 small shadow-sm border-0 mx-3">
                        <i class="bi bi-lock-fill me-2"></i>
                        Esta firma tiene una <strong>factura activa</strong>. Para modificarla o eliminarla, primero debe
                        <strong>eliminar o anular la factura</strong> desde el módulo de Facturas de Venta.
                    </div>
                    <input type="hidden" name="id" id="firma_id_modal" value="">
                    <input type="hidden" name="nombre_producto" id="firma_nombre_producto_modal" value="">

                    <!-- Pestañas -->
                    <div class="d-flex align-items-center px-3 pt-2">
                        <ul class="nav nav-tabs border-bottom-0 flex-grow-1" id="tabsFirmaModal" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link active fw-medium py-2" id="tab-firma-general-btn"
                                    data-bs-toggle="tab" data-bs-target="#tab-firma-general" type="button">
                                    <i class="bi bi-person-lines-fill me-1"></i> Datos
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link fw-medium py-2" id="tab-firma-pago-btn"
                                    data-bs-toggle="tab" data-bs-target="#tab-firma-pago" type="button">
                                    <i class="bi bi-credit-card me-1"></i> Pago
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link fw-medium py-2" id="tab-firma-docs-btn"
                                    data-bs-toggle="tab" data-bs-target="#tab-firma-docs" type="button">
                                    <i class="bi bi-paperclip me-1"></i> Documentos
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link fw-medium py-2 disabled" id="tab-firma-fact-btn"
                                    data-bs-toggle="tab" data-bs-target="#tab-firma-fact" type="button">
                                    <i class="bi bi-receipt me-1"></i> Facturación
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link fw-medium py-2 disabled" id="tab-firma-info-btn"
                                    data-bs-toggle="tab" data-bs-target="#tab-firma-info" type="button">
                                    <i class="bi bi-info-circle me-1"></i> Información
                                </button>
                            </li>
                        </ul>
                        <?php
                        $pestanasConfig = [
                            'tab-firma-pago-btn' => 'Pago',
                            'tab-firma-docs-btn' => 'Documentos',
                            'tab-firma-fact-btn' => 'Facturación',
                            'tab-firma-info-btn' => 'Información',
                        ];
                        echo \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasConfig, $vistaConfigF, 'firmas_electronicas');
                        ?>
                    </div>
                    <div class="border-bottom mx-3 mb-3"></div>

                    <div class="tab-content px-3 pb-3">

                        <!-- ── Pestaña Datos ── -->
                        <div class="tab-pane fade show active" id="tab-firma-general" role="tabpanel">
                            <div class="row g-2">

                                <!-- Tipo de Persona -->
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold text-muted mb-1">Tipo de Persona *</label>
                                    <select class="form-select form-select-sm shadow-none" name="tipo_persona" id="firma_tipo_persona">
                                        <option value="natural">Persona Natural</option>
                                        <option value="juridica">Persona Jurídica</option>
                                    </select>
                                </div>

                                <!-- Validez de Firma -->
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted mb-1 d-flex align-items-center gap-2">
                                        Validez de Firma
                                        <span id="firma_pvp_badge" class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 d-none" style="font-size:.75rem;font-weight:600;"></span>
                                    </label>
                                    <select class="form-select form-select-sm shadow-none" name="id_producto" id="firma_id_producto">
                                        <option value="">- Seleccionar -</option>
                                        <?php foreach ($tiposFirma as $tf): ?>
                                            <option value="<?= $tf['id'] ?>"
                                                data-nombre="<?= htmlspecialchars($tf['nombre']) ?>"
                                                data-pvp="<?= number_format((float)($tf['pvp'] ?? 0), 2, '.', '') ?>">
                                                <?= htmlspecialchars($tf['nombre']) ?>
                                                <?php if (($tf['pvp'] ?? 0) > 0): ?>
                                                    - $<?= number_format((float)$tf['pvp'], 2) ?>
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (empty($tiposFirma)): ?>
                                        <div class="form-text text-warning"><i class="bi bi-exclamation-triangle me-1"></i>No hay productos con categoría "Firmas".</div>
                                    <?php endif; ?>
                                </div>

                                <!-- Tipo de identificación -->
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold text-muted mb-1">Tipo Identificación *</label>
                                    <select class="form-select form-select-sm shadow-none" name="tipo_identificacion" id="firma_tipo_id">
                                        <option value="cedula">Cédula</option>
                                        <option value="pasaporte">Pasaporte</option>
                                    </select>
                                </div>

                                <!-- Número de identificación -->
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold text-muted mb-1 d-flex justify-content-between align-items-center">
                                        <span>Identificación *</span>
                                        <span id="firma_sri_badge" class="badge d-none" style="font-size:.58rem;"></span>
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control form-control-sm shadow-none" name="numero_identificacion"
                                            id="firma_num_id" required maxlength="20" placeholder="1717..."
                                            pattern="\d{10}" inputmode="numeric">
                                        <span class="input-group-text bg-white px-2 d-none" id="firma_sri_spinner_wrap">
                                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                                <span class="visually-hidden">Consultando SRI…</span>
                                            </div>
                                        </span>
                                    </div>
                                    <div id="firma_num_id_hint" class="form-text">10 dígitos numéricos</div>
                                </div>

                                <!-- Fecha de Caducidad -->
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold text-muted mb-1 d-flex align-items-center gap-2">
                                        Caducidad firma
                                        <span id="firma_cad_badge" class="d-none" style="font-size:.7rem;"></span>
                                    </label>
                                    <input type="text" class="form-control form-control-sm shadow-none" name="fecha_caducidad"
                                        id="firma_fecha_cad" maxlength="10" placeholder="dd-mm-yyyy"
                                        pattern="\d{2}-\d{2}-\d{4}"
                                        title="Formato: dd-mm-yyyy">

                                </div>
                                <!-- ¿Con RUC? (solo Persona Natural) -->
                                <div class="col-12" id="bloqueConRuc">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="con_ruc" id="firma_con_ruc" value="1">
                                        <label class="form-check-label small" for="firma_con_ruc">
                                            <strong>¿Con RUC?</strong>
                                            <span class="text-muted">Válido para facturación electrónica</span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Bloque Persona Jurídica -->
                                <div id="bloqueJuridica" class="col-12" style="display:none;">
                                    <div class="border border-primary border-opacity-25 rounded-3 p-3 bg-primary bg-opacity-5">
                                        <div class="small fw-bold text-primary mb-2"><i class="bi bi-building me-1"></i>Datos Persona Jurídica</div>
                                        <div class="row g-2">
                                            <div class="col-md-3">
                                                <label class="form-label small fw-bold text-muted mb-1">RUC Empresa</label>
                                                <input type="text" class="form-control form-control-sm shadow-none" name="ruc_empresa"
                                                    id="firma_ruc_empresa" maxlength="13" placeholder="Ej. 1790000000001" inputmode="numeric">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small fw-bold text-muted mb-1">Nombre Empresa</label>
                                                <input type="text" class="form-control form-control-sm shadow-none" name="nombre_empresa"
                                                    id="firma_nombre_empresa" maxlength="200" placeholder="Razón social de la empresa">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label small fw-bold text-muted mb-1">Cargo / Representación</label>
                                                <input type="text" class="form-control form-control-sm shadow-none" name="cargo"
                                                    id="firma_cargo" maxlength="100" placeholder="Ej. Gerente General">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Código dactilar -->
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold text-muted mb-1">Código Dactilar</label>
                                    <input type="text" class="form-control form-control-sm shadow-none" name="codigo_dactilar"
                                        id="firma_cod_dactilar" maxlength="30" placeholder="Alfanumérico">
                                </div>

                                <!-- Nombres -->
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted mb-1">Nombres *</label>
                                    <input type="text" class="form-control form-control-sm shadow-none" name="nombres"
                                        id="firma_nombres" required maxlength="100" placeholder="Ej. Juan Carlos">
                                </div>

                                <!-- Apellidos -->
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted mb-1">Apellidos *</label>
                                    <input type="text" class="form-control form-control-sm shadow-none" name="apellidos"
                                        id="firma_apellidos" required maxlength="100" placeholder="Ej. Pérez García">
                                </div>

                                <!-- Sexo -->
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold text-muted mb-1">Sexo</label>
                                    <select class="form-select form-select-sm shadow-none" name="sexo" id="firma_sexo">
                                        <option value="">- -</option>
                                        <option value="hombre">Hombre</option>
                                        <option value="mujer">Mujer</option>
                                    </select>
                                </div>

                                <!-- Fecha de nacimiento -->
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold text-muted mb-1">Fecha de Nacimiento</label>
                                    <input type="text" class="form-control form-control-sm shadow-none" name="fecha_nacimiento"
                                        id="firma_fecha_nac" maxlength="10" placeholder="dd-mm-yyyy" pattern="\d{2}-\d{2}-\d{4}">
                                </div>


                                <!-- Nacionalidad -->
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold text-muted mb-1">Nacionalidad</label>
                                    <select class="form-select form-select-sm shadow-none" name="nacionalidad" id="firma_nacionalidad">
                                        <option value="Ecuatoriana" selected>Ecuatoriana</option>
                                        <option value="Colombiana">Colombiana</option>
                                        <option value="Peruana">Peruana</option>
                                        <option value="Boliviana">Boliviana</option>
                                        <option value="Argentina">Argentina</option>
                                        <option value="Brasileña">Brasileña</option>
                                        <option value="Paraguaya">Paraguaya</option>
                                        <option value="Uruguaya">Uruguaya</option>
                                        <option value="Estadounidense">Estadounidense</option>
                                        <option value="Canadiense">Canadiense</option>
                                        <option value="Mexicana">Mexicana</option>
                                        <option value="Española">Española</option>
                                        <option value="Italiana">Italiana</option>
                                    </select>
                                </div>

                                <!-- Teléfono -->
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold text-muted mb-1">Teléfono</label>
                                    <input type="text" class="form-control form-control-sm shadow-none" name="telefono"
                                        id="firma_telefono" maxlength="20" placeholder="Ej. 0999000000" inputmode="tel">
                                </div>

                                <!-- Correo -->
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted mb-1">Correo Electrónico</label>
                                    <input type="email" class="form-control form-control-sm shadow-none" name="correo"
                                        id="firma_correo" maxlength="150" placeholder="Ej. cliente@correo.com">
                                </div>

                                <!-- Provincia -->
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold text-muted mb-1">Provincia</label>
                                    <select class="form-select form-select-sm shadow-none" name="cod_prov" id="firma_cod_prov">
                                        <option value="">- Seleccionar provincia -</option>
                                        <?php foreach ($provincias as $prov): ?>
                                            <option value="<?= htmlspecialchars($prov['codigo']) ?>">
                                                <?= htmlspecialchars($prov['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Ciudad -->
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold text-muted mb-1">Ciudad</label>
                                    <select class="form-select form-select-sm shadow-none" name="cod_ciudad" id="firma_cod_ciudad">
                                        <option value="">- Seleccionar ciudad -</option>
                                    </select>
                                </div>

                                <!-- Dirección -->
                                <div class="col-8">
                                    <label class="form-label small fw-bold text-muted mb-1">Dirección</label>
                                    <input type="text" class="form-control form-control-sm shadow-none" name="direccion"
                                        id="firma_direccion" maxlength="255" placeholder="Calle, número, sector…">
                                </div>

                                <!-- Estado trámite, estado pago y caducidad -->
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold text-muted mb-1">Estado Trámite</label>
                                    <select class="form-select form-select-sm shadow-none" name="estado" id="firma_estado">
                                        <option value="pendiente">Pendiente</option>
                                        <option value="en_proceso">En Proceso</option>
                                        <option value="emitida">Emitida</option>
                                        <option value="cancelada">Cancelada</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold text-muted mb-1">Estado Pago</label>
                                    <select class="form-select form-select-sm shadow-none" name="estado_pago" id="firma_estado_pago">
                                        <option value="pendiente">Pendiente</option>
                                        <option value="confirmado">Confirmado</option>
                                        <option value="rechazado">Rechazado</option>
                                    </select>
                                </div>


                                <!-- Observaciones -->
                                <div class="col-8">
                                    <label class="form-label small fw-bold text-muted mb-1">Observaciones</label>
                                    <textarea class="form-control form-control-sm shadow-none" name="observaciones"
                                        id="firma_observaciones" rows="2" maxlength="1000"
                                        placeholder="Notas internas, indicaciones especiales…"></textarea>
                                </div>

                            </div>
                        </div><!-- /tab-firma-general -->

                        <!-- ── Pestaña Pago ── -->
                        <div class="tab-pane fade" id="tab-firma-pago" role="tabpanel">
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted mb-1">Forma de Pago</label>
                                    <select class="form-select form-select-sm shadow-none" name="tipo_pago" id="firma_tipo_pago">
                                        <option value="">- Seleccionar -</option>
                                        <option value="transferencia">Transferencia Bancaria</option>
                                        <option value="tarjeta">Tarjeta de Crédito/Débito</option>
                                    </select>
                                </div>

                                <!-- Bloque transferencia -->
                                <div class="col-12" id="bloquePagoTransferencia" style="display:none;">
                                    <div class="alert alert-info py-2 small mb-2">
                                        <strong><i class="bi bi-bank me-1"></i>Cuentas para Transferencia:</strong><br>
                                        <span class="text-muted">Configure las cuentas bancarias en los ajustes de la empresa.</span>
                                    </div>
                                    <label class="form-label small fw-bold text-muted mb-1">Comprobante de Transferencia</label>
                                    <div id="contenedorComprobante">
                                        <div id="listaComprobantes" class="mb-2"></div>
                                        <div id="uploadZonaComprobante" class="d-none">
                                            <div class="input-group input-group-sm">
                                                <input type="file" class="form-control form-control-sm" id="archivoComprobante"
                                                    accept=".jpg,.jpeg,.png,.pdf">
                                                <button type="button" class="btn btn-outline-primary btn-sm" id="btnSubirComprobante">
                                                    <i class="bi bi-upload me-1"></i>Subir
                                                </button>
                                            </div>
                                            <div class="form-text">Formatos: JPG, PNG, PDF. Máx. 5 MB.</div>
                                        </div>
                                        <div id="uploadZonaBtnComprobante" class="d-none">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnMostrarUploadComprobante">
                                                <i class="bi bi-plus me-1"></i>Adjuntar comprobante
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Bloque tarjeta -->
                                <div class="col-12" id="bloquePagoTarjeta" style="display:none;">
                                    <div class="alert alert-warning py-2 small">
                                        <i class="bi bi-credit-card me-1"></i>
                                        <strong>Pago con tarjeta:</strong> La integración con pasarela de pagos estará disponible próximamente.
                                    </div>
                                </div>
                            </div>
                        </div><!-- /tab-firma-pago -->

                        <!-- ── Pestaña Documentos ── -->
                        <div class="tab-pane fade" id="tab-firma-docs" role="tabpanel">
                            <div class="row g-3">

                                <!-- Cédula frontal -->
                                <div class="col-md-6">
                                    <div class="border rounded-3 p-3">
                                        <h6 class="small fw-bold text-muted mb-2"><i class="bi bi-card-image me-1 text-primary"></i>Foto Cédula - Frontal</h6>
                                        <div id="previewCedulaFrontal" class="mb-2"></div>
                                        <div class="input-group input-group-sm">
                                            <input type="file" class="form-control form-control-sm" id="archivoCedulaFrontal"
                                                accept=".jpg,.jpeg,.png,.pdf,.webp">
                                            <button type="button" class="btn btn-outline-primary btn-sm" id="btnSubirCedulaFrontal">
                                                <i class="bi bi-upload me-1"></i>Subir
                                            </button>
                                        </div>
                                        <div class="form-text">Formatos: JPG, PNG, WEBP, PDF. Máx. 5 MB.</div>
                                    </div>
                                </div>

                                <!-- Cédula posterior -->
                                <div class="col-md-6">
                                    <div class="border rounded-3 p-3">
                                        <h6 class="small fw-bold text-muted mb-2"><i class="bi bi-card-image me-1 text-secondary"></i>Foto Cédula - Posterior</h6>
                                        <div id="previewCedulaPosterior" class="mb-2"></div>
                                        <div class="input-group input-group-sm">
                                            <input type="file" class="form-control form-control-sm" id="archivoCedulaPosterior"
                                                accept=".jpg,.jpeg,.png,.pdf,.webp">
                                            <button type="button" class="btn btn-outline-primary btn-sm" id="btnSubirCedulaPosterior">
                                                <i class="bi bi-upload me-1"></i>Subir
                                            </button>
                                        </div>
                                        <div class="form-text">Formatos: JPG, PNG, WEBP, PDF. Máx. 5 MB.</div>
                                    </div>
                                </div>

                                <!-- Selfie -->
                                <div class="col-md-6">
                                    <div class="border rounded-3 p-3">
                                        <h6 class="small fw-bold text-muted mb-2"><i class="bi bi-person-bounding-box me-1 text-success"></i>Selfie</h6>
                                        <div id="previewSelfie" class="mb-2"></div>
                                        <div class="input-group input-group-sm">
                                            <input type="file" class="form-control form-control-sm" id="archivoSelfie"
                                                accept=".jpg,.jpeg,.png,.webp">
                                            <button type="button" class="btn btn-outline-primary btn-sm" id="btnSubirSelfie">
                                                <i class="bi bi-upload me-1"></i>Subir
                                            </button>
                                        </div>
                                        <div class="form-text">Formatos: JPG, PNG, WEBP. Máx. 5 MB.</div>
                                    </div>
                                </div>

                                <!-- Spinner de carga de adjuntos -->
                                <div class="col-12" id="spinnerAdjuntos" style="display:none;">
                                    <div class="text-center py-3 text-muted small">
                                        <span class="spinner-border spinner-border-sm me-2"></span>Cargando documentos…
                                    </div>
                                </div>

                                <!-- Documentos Persona Jurídica -->
                                <div id="bloqueDocsJuridica" class="col-12" style="display:none;">
                                    <div class="border-top pt-3 mt-1">
                                        <h6 class="small fw-bold text-primary mb-3"><i class="bi bi-building me-1"></i>Documentos Persona Jurídica</h6>
                                        <div class="row g-3">

                                            <!-- RUC empresa -->
                                            <div class="col-md-6">
                                                <div class="border rounded-3 p-3">
                                                    <h6 class="small fw-bold text-muted mb-2"><i class="bi bi-file-earmark-text me-1 text-info"></i>RUC</h6>
                                                    <div id="previewRucEmpresa" class="mb-2"></div>
                                                    <div class="input-group input-group-sm">
                                                        <input type="file" class="form-control form-control-sm" id="archivoRucEmpresa" accept=".pdf">
                                                        <button type="button" class="btn btn-outline-primary btn-sm" id="btnSubirRucEmpresa">
                                                            <i class="bi bi-upload me-1"></i>Subir
                                                        </button>
                                                    </div>
                                                    <div class="form-text">Formato: PDF. Máx. 5 MB.</div>
                                                </div>
                                            </div>

                                            <!-- Constitución compañía -->
                                            <div class="col-md-6">
                                                <div class="border rounded-3 p-3">
                                                    <h6 class="small fw-bold text-muted mb-2"><i class="bi bi-file-earmark-text me-1 text-secondary"></i>Constitución de Compañía</h6>
                                                    <div id="previewConstitucion" class="mb-2"></div>
                                                    <div class="input-group input-group-sm">
                                                        <input type="file" class="form-control form-control-sm" id="archivoConstitucion" accept=".pdf">
                                                        <button type="button" class="btn btn-outline-primary btn-sm" id="btnSubirConstitucion">
                                                            <i class="bi bi-upload me-1"></i>Subir
                                                        </button>
                                                    </div>
                                                    <div class="form-text">Formato: PDF. Máx. 5 MB.</div>
                                                </div>
                                            </div>

                                            <!-- Nombramiento -->
                                            <div class="col-md-6">
                                                <div class="border rounded-3 p-3">
                                                    <h6 class="small fw-bold text-muted mb-2"><i class="bi bi-file-earmark-person me-1 text-warning"></i>Nombramiento</h6>
                                                    <div id="previewNombramiento" class="mb-2"></div>
                                                    <div class="input-group input-group-sm">
                                                        <input type="file" class="form-control form-control-sm" id="archivoNombramiento" accept=".pdf">
                                                        <button type="button" class="btn btn-outline-primary btn-sm" id="btnSubirNombramiento">
                                                            <i class="bi bi-upload me-1"></i>Subir
                                                        </button>
                                                    </div>
                                                    <div class="form-text">Formato: PDF. Máx. 5 MB.</div>
                                                </div>
                                            </div>

                                            <!-- Aceptación de nombramiento -->
                                            <div class="col-md-6">
                                                <div class="border rounded-3 p-3">
                                                    <h6 class="small fw-bold text-muted mb-2"><i class="bi bi-file-earmark-check me-1 text-success"></i>Aceptación de Nombramiento</h6>
                                                    <div id="previewAceptacion" class="mb-2"></div>
                                                    <div class="input-group input-group-sm">
                                                        <input type="file" class="form-control form-control-sm" id="archivoAceptacion" accept=".pdf">
                                                        <button type="button" class="btn btn-outline-primary btn-sm" id="btnSubirAceptacion">
                                                            <i class="bi bi-upload me-1"></i>Subir
                                                        </button>
                                                    </div>
                                                    <div class="form-text">Formato: PDF. Máx. 5 MB.</div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <p class="text-muted small mb-0"><i class="bi bi-info-circle me-1"></i>Los documentos solo se pueden adjuntar después de guardar el registro por primera vez.</p>
                                </div>
                            </div>
                        </div><!-- /tab-firma-docs -->

                        <!-- ── Pestaña Facturación ── -->
                        <div class="tab-pane fade" id="tab-firma-fact" role="tabpanel">
                            <div class="row g-2">

                                <!-- Checkbox mismos datos -->
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="facturacion_mismos_datos"
                                            id="fact_mismos_datos" value="1" checked>
                                        <label class="form-check-label small fw-bold" for="fact_mismos_datos">
                                            ¿Usar los mismos datos del titular para facturar?
                                        </label>
                                    </div>
                                </div>

                                <!-- Campos personalizados (solo cuando mismos datos = false) -->
                                <div class="col-12" id="bloqueFactCampos" style="display:none;">
                                    <div class="bg-light border rounded-3 p-3">
                                        <div class="small fw-bold text-muted mb-2"><i class="bi bi-pencil-square me-1"></i>Datos para facturación</div>
                                        <div class="row g-2">
                                            <div class="col-md-2">
                                                <label class="form-label small fw-bold text-muted mb-1">Tipo ID</label>
                                                <select class="form-select form-select-sm shadow-none" name="facturacion_tipo_id" id="fact_tipo_id">
                                                    <option value="cedula">Cédula</option>
                                                    <option value="ruc">RUC</option>
                                                    <option value="pasaporte">Pasaporte</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label small fw-bold text-muted mb-1 d-flex justify-content-between align-items-center">
                                                    <span>Número ID *</span>
                                                    <span id="fact_sri_badge" class="badge d-none" style="font-size:.58rem;"></span>
                                                </label>
                                                <div class="input-group input-group-sm">
                                                    <input type="text" class="form-control form-control-sm shadow-none"
                                                        name="facturacion_num_id" id="fact_num_id" maxlength="13" inputmode="numeric">
                                                    <span class="input-group-text bg-white px-2 d-none" id="fact_sri_spinner_wrap">
                                                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                                                            <span class="visually-hidden">Consultando SRI…</span>
                                                        </div>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="col-md-5">
                                                <label class="form-label small fw-bold text-muted mb-1">Nombres / Razón Social *</label>
                                                <input type="text" class="form-control form-control-sm shadow-none"
                                                    name="facturacion_nombres" id="fact_nombres" maxlength="200">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small fw-bold text-muted mb-1">Teléfono</label>
                                                <input type="text" class="form-control form-control-sm shadow-none"
                                                    name="facturacion_telefono" id="fact_telefono" maxlength="20" inputmode="tel">
                                            </div>
                                            <div class="col-md-5">
                                                <label class="form-label small fw-bold text-muted mb-1">Correo</label>
                                                <input type="email" class="form-control form-control-sm shadow-none"
                                                    name="facturacion_correo" id="fact_correo" maxlength="150">
                                            </div>
                                            <div class="col-md-7">
                                                <label class="form-label small fw-bold text-muted mb-1">Dirección</label>
                                                <input type="text" class="form-control form-control-sm shadow-none"
                                                    name="facturacion_direccion" id="fact_direccion" maxlength="255">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Tarjeta única de factura -->
                                <div class="col-12">
                                    <div class="bg-light border rounded-3 overflow-hidden" id="bloqueResumenFactura">

                                        <!-- Encabezado (visible solo cuando existe factura) -->
                                        <div id="factHeaderRow" style="display:none;">
                                            <div class="d-flex justify-content-between align-items-center px-3 py-2 bg-white border-bottom">
                                                <div class="d-flex align-items-center gap-2">
                                                    <i class="bi bi-receipt text-secondary" style="font-size:.9rem;"></i>
                                                    <span class="small text-secondary fw-semibold">FACTURA</span>
                                                    <strong class="small text-dark" id="factNumero"></strong>
                                                </div>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="text-muted" id="factFecha" style="font-size:.78rem;"></span>
                                                    <span class="badge d-none" id="facturaEstadoBadge"></span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Datos de facturación -->
                                        <div class="px-3 pt-3 pb-2">
                                            <div class="row g-2" style="font-size:.82rem;">
                                                <div class="col-md-5">
                                                    <div class="text-muted" style="font-size:.65rem;text-transform:uppercase;letter-spacing:.04em;">Nombre / Razón Social</div>
                                                    <div class="fw-semibold" id="factNombre">-</div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="text-muted" style="font-size:.65rem;text-transform:uppercase;letter-spacing:.04em;">Identificación</div>
                                                    <div id="factCedula">-</div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="text-muted" style="font-size:.65rem;text-transform:uppercase;letter-spacing:.04em;">Correo</div>
                                                    <div id="factCorreo">-</div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="text-muted" style="font-size:.65rem;text-transform:uppercase;letter-spacing:.04em;">Teléfono</div>
                                                    <div id="factTelefono">-</div>
                                                </div>
                                                <div class="col-md-9">
                                                    <div class="text-muted" style="font-size:.65rem;text-transform:uppercase;letter-spacing:.04em;">Dirección</div>
                                                    <div id="factDireccion">-</div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Detalle de productos -->
                                        <div class="px-3 pb-3">
                                            <table class="table table-sm table-bordered mb-0" style="font-size:.8rem;">
                                                <thead class="table-secondary">
                                                    <tr>
                                                        <th class="fw-semibold">Concepto</th>
                                                        <th class="text-end fw-semibold" style="width:150px;">Total c/ IVA</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="factDetalleProductos">
                                                    <tr><td colspan="2" class="text-muted fst-italic text-center">- Seleccione una Validez de Firma -</td></tr>
                                                </tbody>
                                            </table>
                                        </div>

                                        <!-- Footer con enlace (solo con factura generada) -->
                                        <div class="px-3 py-2 border-top d-none" id="factLinkWrap">
                                            <a href="#" id="facturaLink" target="_blank" class="btn btn-outline-secondary btn-sm">
                                                <i class="bi bi-eye me-1"></i>Ver en Facturas de Venta
                                            </a>
                                        </div>

                                    </div>
                                </div>

                                <!-- Botón generar factura -->
                                <div class="col-12 d-flex align-items-center gap-3 flex-wrap">
                                    <button type="button" class="btn btn-success btn-sm px-4 shadow-sm"
                                        id="btnGenerarFactura" onclick="generarFacturaDesdeFirma()" disabled>
                                        <i class="bi bi-receipt me-1"></i>Generar Factura de Venta
                                    </button>
                                    <span id="factSpinner" class="d-none text-muted small">
                                        <span class="spinner-border spinner-border-sm me-1"></span>Generando factura…
                                    </span>
                                </div>

                            </div>
                        </div><!-- /tab-firma-fact -->

                        <!-- ── Pestaña Información ── -->
                        <div class="tab-pane fade" id="tab-firma-info" role="tabpanel">

                            <!-- Permisos -->
                            <div class="p-2 border rounded-3 bg-white shadow-sm mb-3 mx-0">
                                <div class="small fw-bold text-muted mb-2 d-flex align-items-center" style="font-size:.7rem;">
                                    <i class="bi bi-key-fill text-warning me-2"></i> PERMISOS
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php
                                    $bBadge = fn($ok, $lbl) => sprintf(
                                        '<span class="badge bg-%s bg-opacity-10 text-%s border border-%s border-opacity-25 px-2" style="font-size:.65rem;">%s</span>',
                                        $ok ? 'success' : 'secondary text-opacity-50',
                                        $ok ? 'success' : 'secondary',
                                        $ok ? 'success' : 'secondary',
                                        $lbl
                                    );
                                    echo $bBadge($perm['ver']        ?? true,  'VER');
                                    echo $bBadge($perm['crear']      ?? true,  'CREAR');
                                    echo $bBadge($perm['actualizar'] ?? true,  'MODIFICAR');
                                    echo $bBadge($perm['eliminar']   ?? false, 'ELIMINAR');
                                    ?>
                                </div>
                            </div>

                            <div class="bg-light rounded-3 p-3 border mb-3">
                                <h6 class="text-primary mb-3 small fw-bold"><i class="bi bi-clock-history me-2"></i>Historial</h6>
                                <div id="auditoriaTimelineFirma" class="position-relative mt-2" style="max-height:200px;overflow-y:auto;">
                                    <div class="text-center py-3 text-muted small">Cargando…</div>
                                </div>
                            </div>
                        </div><!-- /tab-firma-info -->

                    </div><!-- /tab-content -->
                </div><!-- /modal-body -->

                <div class="modal-footer justify-content-between bg-light py-3">
                    <div>
                        <?php if ($perm['eliminar'] ?? false): ?>
                            <button type="button" class="btn btn-outline-danger btn-sm d-none" id="btnEliminarFirmaModal"
                                onclick="eliminarFirmaModal()">
                                <i class="bi bi-trash"></i> Eliminar
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary btn-sm px-4 shadow-sm" id="btnGuardarFirmaModal"
                            onclick="guardarFirmaModal()">
                            <i class="bi bi-check-lg"></i> Guardar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>/js/modulos/firmas_electronicas_modal.js?v=<?= time() ?>"></script>