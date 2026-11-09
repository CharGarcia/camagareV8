<?php
/**
 * Diálogos de acciones de la oportunidad: elegir cliente (Ganar / Crear proforma),
 * perder (motivo) y actividad. Van sobre el modal de la oportunidad.
 */
?>
<!-- Elegir cliente: Ganar y Crear proforma -->
<div class="modal fade" id="crmModalCliente" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light py-2">
                <h5 class="modal-title fw-bold fs-6" id="crm-cli-titulo"><i class="bi bi-person-check text-success me-2"></i>Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2" id="crm-cli-ayuda"></p>
                <div class="position-relative">
                    <label class="form-label small fw-bold mb-1">Cliente <span class="text-danger">*</span></label>
                    <div class="input-group input-group-sm">
                        <input type="hidden" id="crm-cli-id">
                        <input type="text" class="form-control" id="crm-cli-buscar" autocomplete="off" placeholder="Buscar por nombre o identificación…">
                        <?php if (!empty($puedeCliente)): ?>
                        <button type="button" class="btn btn-outline-primary px-2" onclick="CRM.cliNuevo()" title="Registrar nuevo cliente"><i class="bi bi-person-plus"></i></button>
                        <?php endif; ?>
                    </div>
                    <div class="list-group shadow position-absolute d-none crm-dd" id="crm-cli-dd" style="left:0;right:0;"></div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success btn-sm px-3" id="crm-cli-btn-ok" onclick="CRM.cliConfirmar()">
                    <i class="bi bi-check2 me-1"></i><span id="crm-cli-btn-texto">Confirmar</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Perder -->
<div class="modal fade" id="crmModalPerder" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light py-2">
                <h5 class="modal-title fw-bold fs-6"><i class="bi bi-x-octagon text-danger me-2"></i>Marcar como perdida</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <label class="form-label small fw-bold mb-1">Motivo <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm mb-2" id="crm-perder-motivo"></select>
                <label class="form-label small fw-bold mb-1">Detalle</label>
                <textarea class="form-control form-control-sm" rows="3" id="crm-perder-detalle" maxlength="2000"
                          placeholder="Qué pasó, con quién se fue, cuándo volver a intentar…"></textarea>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger btn-sm px-3" id="crm-perder-btn" onclick="CRM.perderConfirmar()">
                    <i class="bi bi-x-octagon me-1"></i>Marcar perdida
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Actividad -->
<div class="modal fade" id="crmModalActividad" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light py-2">
                <h5 class="modal-title fw-bold fs-6"><i class="bi bi-check2-square text-primary me-2"></i><span id="crm-act-titulo-modal">Nueva actividad</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <form id="crm-act-form" autocomplete="off" onsubmit="event.preventDefault(); CRM.actGuardar();">
                    <input type="hidden" name="id" id="crm-act-id">
                    <input type="hidden" name="id_oportunidad" id="crm-act-oportunidad">
                    <div class="row g-2">
                        <div class="col-5">
                            <label class="form-label small fw-bold mb-1">Tipo</label>
                            <select class="form-select form-select-sm" name="tipo" id="crm-act-tipo" onchange="CRM.actTipoCambiado()">
                                <option value="tarea">Tarea</option>
                                <option value="llamada">Llamada</option>
                                <option value="reunion">Reunión</option>
                                <option value="correo">Correo</option>
                                <option value="nota">Nota</option>
                            </select>
                        </div>
                        <div class="col-7" id="crm-act-vence-wrap">
                            <label class="form-label small fw-bold mb-1">Para cuándo <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control form-control-sm" name="vence_at" id="crm-act-vence">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold mb-1">Título <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" name="titulo" id="crm-act-titulo" maxlength="200" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold mb-1">Responsable</label>
                            <select class="form-select form-select-sm" name="id_responsable" id="crm-act-responsable"></select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold mb-1">Descripción</label>
                            <textarea class="form-control form-control-sm" rows="2" name="descripcion" id="crm-act-descripcion"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light py-2 d-flex justify-content-between">
                <div>
                    <?php if (!empty($perm['eliminar'])): ?>
                    <button type="button" class="btn btn-outline-danger btn-sm d-none" id="crm-act-btn-eliminar" onclick="CRM.actEliminar()"><i class="bi bi-trash"></i> Eliminar</button>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-sm px-3" id="crm-act-btn-guardar" onclick="CRM.actGuardar()"><i class="bi bi-check2-circle me-1"></i>Guardar</button>
                </div>
            </div>
        </div>
    </div>
</div>
