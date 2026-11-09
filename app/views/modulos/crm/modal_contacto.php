<?php
/**
 * Modal de contacto del CRM (lead). Al crear uno «al vuelo» desde el modal de la
 * oportunidad, queda puesto en ella (evento crmContactoGuardado, ver crm.js).
 */
?>
<div class="modal fade" id="crmModalContacto" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light py-2">
                <h5 class="modal-title fw-bold fs-6"><i class="bi bi-person-vcard text-primary me-2"></i><span id="crm-ct-titulo-modal">Nuevo contacto</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <form id="crm-ct-form" autocomplete="off" onsubmit="event.preventDefault(); CRM.ctGuardar();">
                    <input type="hidden" name="id" id="crm-ct-id">
                    <input type="hidden" name="token_guardado" id="crm-ct-token">
                    <div class="row g-2">
                        <div class="col-12">
                            <label class="form-label small fw-bold mb-1">Nombre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" name="nombre" id="crm-ct-nombre" maxlength="200" required>
                        </div>
                        <div class="col-7">
                            <label class="form-label small fw-bold mb-1">Empresa donde trabaja</label>
                            <input type="text" class="form-control form-control-sm" name="empresa_contacto" id="crm-ct-empresa" maxlength="200">
                        </div>
                        <div class="col-5">
                            <label class="form-label small fw-bold mb-1">Cargo</label>
                            <input type="text" class="form-control form-control-sm" name="cargo" id="crm-ct-cargo" maxlength="100">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-1">Teléfono</label>
                            <input type="tel" class="form-control form-control-sm" name="telefono" id="crm-ct-telefono" maxlength="30">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-1">Correo</label>
                            <input type="email" class="form-control form-control-sm" name="correo" id="crm-ct-correo" maxlength="150">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-1">Instagram</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">@</span>
                                <input type="text" class="form-control" name="instagram_usuario" id="crm-ct-instagram" maxlength="100">
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-1">Perfil de LinkedIn</label>
                            <input type="url" class="form-control form-control-sm" name="linkedin_url" id="crm-ct-linkedin" maxlength="300" placeholder="https://www.linkedin.com/in/…">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-1">Origen</label>
                            <select class="form-select form-select-sm" name="id_origen" id="crm-ct-origen"></select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-1">Responsable</label>
                            <select class="form-select form-select-sm" name="id_responsable" id="crm-ct-responsable"></select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold mb-1">Notas</label>
                            <textarea class="form-control form-control-sm" rows="2" name="notas" id="crm-ct-notas"></textarea>
                        </div>
                        <div class="col-12 d-none" id="crm-ct-extra">
                            <div class="small text-muted" id="crm-ct-cliente"></div>
                            <div class="small mt-1" id="crm-ct-oportunidades"></div>
                            <div class="small text-muted mt-1 d-none" id="crm-ct-alias"></div>
                        </div>
                    </div>
                </form>
                <?php if (!empty($perm['actualizar']) && !empty($perm['eliminar'])): ?>
                <!-- Unir duplicados: la misma persona llegó por mensaje directo y por comentario
                     (Instagram le da ids distintos) o se registró dos veces. -->
                <div class="border rounded-3 p-2 mt-3 bg-light d-none" id="crm-ct-unir">
                    <div class="small fw-bold mb-1"><i class="bi bi-intersect me-1 text-primary"></i>Unir con otro contacto</div>
                    <div class="small text-muted mb-2">
                        El contacto elegido se une a este: le pasa sus oportunidades, conversaciones y actividades, completa los datos que aquí estén vacíos y luego se elimina.
                    </div>
                    <div class="position-relative">
                        <input type="hidden" id="crm-ct-unir-id">
                        <input type="text" class="form-control form-control-sm" id="crm-ct-unir-buscar" placeholder="Buscar el contacto duplicado…" autocomplete="off">
                        <div class="list-group position-absolute w-100 shadow-sm d-none" id="crm-ct-unir-dd" style="z-index:10;max-height:220px;overflow:auto;"></div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="CRM.ctUnirCancelar()">Cancelar</button>
                        <button type="button" class="btn btn-primary btn-sm" id="crm-ct-unir-btn" onclick="CRM.ctUnirConfirmar()">
                            <i class="bi bi-intersect me-1"></i>Unir
                        </button>
                    </div>
                </div>
                <?php endif; ?>

            </div>
            <div class="modal-footer bg-light py-2 d-flex justify-content-between">
                <div>
                    <?php if (!empty($perm['eliminar'])): ?>
                    <button type="button" class="btn btn-outline-danger btn-sm d-none" id="crm-ct-btn-eliminar" onclick="CRM.ctEliminar()">
                        <i class="bi bi-trash"></i> Eliminar
                    </button>
                    <?php endif; ?>
                    <?php if (!empty($perm['actualizar']) && !empty($perm['eliminar'])): ?>
                    <button type="button" class="btn btn-outline-primary btn-sm d-none" id="crm-ct-btn-unir" onclick="CRM.ctUnirMostrar()" title="Unir con un contacto duplicado">
                        <i class="bi bi-intersect"></i> Unir duplicado
                    </button>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary btn-sm px-3" id="crm-ct-btn-guardar" onclick="CRM.ctGuardar()">
                        <i class="bi bi-check2-circle me-1"></i>Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
