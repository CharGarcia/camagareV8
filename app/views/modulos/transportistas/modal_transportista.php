<?php
/** @var array $perm */
$vistaConfigTr = \App\Helpers\PreferenciasHelper::getPreferenciasVista('transportistas');
echo \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfigTr, 'estiloVistaPestanasTr');
?>

<!-- ═══════════════════════ MODAL TRANSPORTISTA ═══════════════════════ -->
<div class="modal fade" id="modalTransportista" tabindex="-1" aria-labelledby="modalTransportistaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header bg-light border-bottom py-2 px-3">
                <h6 class="modal-title fw-bold mb-0" id="modalTransportistaLabel">
                    <i class="bi bi-truck me-2 text-primary"></i>
                    <span id="tr-modal-titulo">Nuevo Transportista</span>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <!-- Pestañas -->
            <div class="d-flex align-items-center bg-light px-3 pt-2">
                <ul class="nav nav-tabs border-bottom-0 flex-grow-1 tab-pestaña" id="tr-tabs-nav">
                    <li class="nav-item">
                        <button class="nav-link active small fw-semibold" id="tr-tab-general-btn"
                                data-bs-toggle="tab" data-bs-target="#tr-pane-general" type="button">
                            <i class="bi bi-person-vcard me-1"></i>General
                        </button>
                    </li>
                </ul>
            </div>

            <div class="modal-body p-0">
                <input type="hidden" id="tr-id">

                <div class="tab-content border-top">
                    <!-- Pestaña General -->
                    <div class="tab-pane fade show active px-3 py-3" id="tr-pane-general">
                        <div class="row g-3">

                            <!-- Tipo ID -->
                            <div class="col-md-4">
                                <label class="form-label fw-semibold small">
                                    Tipo identificación <span class="text-danger">*</span>
                                </label>
                                <select id="tr-tipo-id" class="form-select form-select-sm"
                                        onchange="TR_cambiarTipoId()">
                                    <option value="04">RUC</option>
                                    <option value="05" selected>Cédula</option>
                                    <option value="06">Pasaporte</option>
                                </select>
                            </div>

                            <!-- Identificación + SRI -->
                            <div class="col-md-5">
                                <label for="tr-identificacion" class="form-label small fw-bold d-flex justify-content-between align-items-center" style="margin-bottom: 0.5rem;">
                                    <span>Identificación *</span>
                                    <span id="tr-sri-badge" class="badge d-none"></span>
                                </label>
                                <div class="input-group input-group-sm">
                                    <input type="text" id="tr-identificacion"
                                           class="form-control form-control-sm"
                                           maxlength="13" placeholder="Número de identificación"
                                           oninput="TR_onIdentificacionInput()">
                                    <span class="input-group-text bg-white px-2 d-none" id="tr-sri-spinner-wrap">
                                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                                            <span class="visually-hidden">Consultando...</span>
                                        </div>
                                    </span>
                                </div>
                            </div>

                            <!-- Estado -->
                            <div class="col-md-3">
                                <label class="form-label fw-semibold small">Estado</label>
                                <select id="tr-estado" class="form-select form-select-sm">
                                    <option value="activo">Activo</option>
                                    <option value="inactivo">Inactivo</option>
                                </select>
                            </div>

                            <!-- Nombre -->
                            <div class="col-12">
                                <label class="form-label fw-semibold small">
                                    Nombres y apellidos / Razón social <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="tr-nombre" class="form-control form-control-sm"
                                       maxlength="300" placeholder="Nombre completo o razón social"
                                       style="text-transform:uppercase">
                            </div>

                            <!-- Email múltiple -->
                            <div class="col-12">
                                <label class="form-label fw-semibold small">
                                    Correo electrónico <span class="text-danger">*</span>
                                    <small class="text-muted fw-normal ms-1">- varios separados por coma</small>
                                </label>
                                <input type="text" id="tr-email" class="form-control form-control-sm"
                                       maxlength="500" placeholder="correo@ejemplo.com, otro@ejemplo.com"
                                       onblur="TR_validarEmails()">
                                <div id="tr-email-error" class="text-danger small mt-1 d-none"></div>
                            </div>

                            <!-- Placa -->
                            <div class="col-md-4">
                                <label class="form-label fw-semibold small">Placa del vehículo</label>
                                <input type="text" id="tr-placa" class="form-control form-control-sm"
                                       maxlength="8" placeholder="ABC-1234"
                                       style="text-transform:uppercase">
                            </div>

                            <!-- Teléfono -->
                            <div class="col-md-4">
                                <label class="form-label fw-semibold small">
                                    Teléfono <small class="text-muted fw-normal">(opcional)</small>
                                </label>
                                <input type="text" id="tr-telefono" class="form-control form-control-sm"
                                       maxlength="20" placeholder="0999999999">
                            </div>

                            <!-- Dirección -->
                            <div class="col-12">
                                <label class="form-label fw-semibold small">
                                    Dirección / Ubicación <small class="text-muted fw-normal">(opcional)</small>
                                </label>
                                <input type="text" id="tr-direccion" class="form-control form-control-sm"
                                       maxlength="300" placeholder="Calle, ciudad, referencia…">
                            </div>

                        </div>
                    </div><!-- /tab-pane general -->
                </div><!-- /tab-content -->
            </div><!-- /modal-body -->

            <div class="modal-footer bg-light border-top py-2 justify-content-between">
                <div>
                    <?php if ($perm['eliminar']): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger d-none"
                                id="btn-tr-eliminar" onclick="TR_eliminar()">
                            <i class="bi bi-trash3 me-1"></i> Eliminar
                        </button>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i> Cancelar
                    </button>
                    <?php if ($perm['crear'] || $perm['actualizar']): ?>
                        <button type="button" class="btn btn-sm btn-primary"
                                id="btn-tr-guardar" onclick="TR_guardar()">
                            <i class="bi bi-check2-circle me-1"></i> Guardar
                        </button>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>
