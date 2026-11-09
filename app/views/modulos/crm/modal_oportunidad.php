<?php
/**
 * Modal de oportunidad del CRM. Barra de acciones arriba (Crear proforma, Ganar,
 * Perder, Reabrir), pestañas Datos / Actividades / Historial (ocultables por
 * usuario) y Eliminar a la izquierda del pie. Lo maneja CRM.op* en crm.js.
 */
use App\Helpers\PreferenciasHelper;

$pestanasOp = ['crm-pane-datos' => 'Datos', 'crm-pane-actividades' => 'Actividades', 'crm-pane-historial' => 'Historial'];
?>
<div class="modal fade" id="crmModalOportunidad" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light py-2">
                <h5 class="modal-title fw-bold fs-6">
                    <i class="bi bi-briefcase text-primary me-2"></i><span id="crm-op-titulo-modal">Nueva oportunidad</span>
                    <span class="badge ms-2 d-none" id="crm-op-badge-estado"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body pt-2">
                <!-- Barra de acciones del documento (solo con la oportunidad guardada) -->
                <div class="d-flex gap-1 align-items-center flex-wrap border-bottom pb-2 mb-2 d-none" id="crm-op-acciones">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="crm-op-btn-proforma" onclick="CRM.opCrearProforma()" title="Crear la proforma de esta oportunidad">
                        <i class="bi bi-file-earmark-text"></i> Crear proforma
                    </button>
                    <span class="small text-muted d-none" id="crm-op-proforma-info"><i class="bi bi-link-45deg"></i> Proforma enlazada</span>
                    <div class="vr mx-1"></div>
                    <button type="button" class="btn btn-sm btn-outline-success" id="crm-op-btn-ganar" onclick="CRM.opGanar()" title="Marcar como ganada">
                        <i class="bi bi-trophy"></i> Ganar
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger" id="crm-op-btn-perder" onclick="CRM.opPerder()" title="Marcar como perdida">
                        <i class="bi bi-x-octagon"></i> Perder
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="crm-op-btn-reabrir" onclick="CRM.opReabrir()" title="Volver a abrir la oportunidad">
                        <i class="bi bi-arrow-counterclockwise"></i> Reabrir
                    </button>
                </div>

                <div class="d-flex align-items-center mb-2">
                    <ul class="nav nav-tabs flex-grow-1" role="tablist">
                        <li class="nav-item"><button class="nav-link active py-1" data-bs-toggle="tab" data-bs-target="#crm-pane-datos" type="button">Datos</button></li>
                        <li class="nav-item"><button class="nav-link py-1" data-bs-toggle="tab" data-bs-target="#crm-pane-actividades" type="button" id="crm-op-tab-actividades">Actividades</button></li>
                        <li class="nav-item"><button class="nav-link py-1" data-bs-toggle="tab" data-bs-target="#crm-pane-historial" type="button" id="crm-op-tab-historial">Historial</button></li>
                    </ul>
                    <?= PreferenciasHelper::renderDropdownPestanas($pestanasOp, $vistaConfig ?? [], $rutaModulo) ?>
                </div>

                <div class="tab-content">
                    <!-- Datos -->
                    <div class="tab-pane fade show active" id="crm-pane-datos">
                        <form id="crm-op-form" autocomplete="off" onsubmit="event.preventDefault(); CRM.opGuardar();">
                            <input type="hidden" name="id" id="crm-op-id">
                            <input type="hidden" name="token_guardado" id="crm-op-token">
                            <div class="row g-2">
                                <div class="col-12">
                                    <label class="form-label small fw-bold mb-1">Oportunidad <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm" name="titulo" id="crm-op-titulo" maxlength="200" required
                                           placeholder="Ej.: Plan anual de mantenimiento">
                                </div>
                                <div class="col-12 position-relative">
                                    <label class="form-label small fw-bold mb-1">Contacto <span class="text-danger">*</span></label>
                                    <div class="input-group input-group-sm">
                                        <input type="hidden" name="id_contacto" id="crm-op-id-contacto">
                                        <input type="text" class="form-control" id="crm-op-contacto" placeholder="Buscar contacto por nombre, teléfono, correo o @instagram…">
                                        <?php if (!empty($perm['crear'])): ?>
                                        <button type="button" class="btn btn-outline-primary px-2" onclick="CRM.nuevoContacto(true)" title="Registrar nuevo contacto"><i class="bi bi-person-plus"></i></button>
                                        <?php endif; ?>
                                    </div>
                                    <div class="list-group shadow position-absolute d-none crm-dd" id="crm-op-dd-contacto" style="left:0;right:0;"></div>
                                </div>
                                <div class="col-6 col-md-4">
                                    <label class="form-label small fw-bold mb-1">Embudo</label>
                                    <select class="form-select form-select-sm" name="id_embudo" id="crm-op-embudo" onchange="CRM.opLlenarEtapas()"></select>
                                </div>
                                <div class="col-6 col-md-4">
                                    <label class="form-label small fw-bold mb-1">Etapa</label>
                                    <select class="form-select form-select-sm" name="id_etapa" id="crm-op-etapa"></select>
                                </div>
                                <div class="col-6 col-md-4">
                                    <label class="form-label small fw-bold mb-1">Valor estimado</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" min="0" class="form-control text-end" name="valor_estimado" id="crm-op-valor" value="0">
                                    </div>
                                    <div class="form-text small d-none" id="crm-op-valor-ayuda">Lo fija la proforma enlazada.</div>
                                </div>
                                <div class="col-6 col-md-4">
                                    <label class="form-label small fw-bold mb-1">Cierre estimado</label>
                                    <input type="date" class="form-control form-control-sm" name="fecha_cierre_estimada" id="crm-op-cierre">
                                </div>
                                <div class="col-6 col-md-4">
                                    <label class="form-label small fw-bold mb-1">Responsable</label>
                                    <select class="form-select form-select-sm" name="id_responsable" id="crm-op-responsable"></select>
                                </div>
                                <div class="col-6 col-md-4">
                                    <label class="form-label small fw-bold mb-1">Vendedor</label>
                                    <select class="form-select form-select-sm" name="id_vendedor" id="crm-op-vendedor"></select>
                                </div>
                                <div class="col-6 col-md-4">
                                    <label class="form-label small fw-bold mb-1">Origen</label>
                                    <select class="form-select form-select-sm" name="id_origen" id="crm-op-origen"></select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold mb-1">Descripción</label>
                                    <textarea class="form-control form-control-sm" rows="3" name="descripcion" id="crm-op-descripcion" maxlength="4000"></textarea>
                                </div>
                                <div class="col-12 d-none" id="crm-op-cierre-info">
                                    <div class="alert py-2 px-3 small mb-0" id="crm-op-cierre-texto"></div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Actividades -->
                    <div class="tab-pane fade" id="crm-pane-actividades">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted">Tareas, llamadas, reuniones y notas de esta oportunidad.</small>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="CRM.actNueva()"><i class="bi bi-plus-lg"></i> Actividad</button>
                        </div>
                        <div class="list-group" id="crm-op-actividades"></div>
                    </div>

                    <!-- Historial de etapas -->
                    <div class="tab-pane fade" id="crm-pane-historial">
                        <table class="table table-sm small mb-0">
                            <thead><tr><th>Fecha</th><th>De</th><th>A</th><th>Usuario</th></tr></thead>
                            <tbody id="crm-op-historial"></tbody>
                        </table>
                    </div>
                </div>
                <div class="small text-muted mt-2 d-none" id="crm-op-pie"></div>
            </div>
            <div class="modal-footer bg-light py-2 d-flex justify-content-between">
                <div>
                    <?php if (!empty($perm['eliminar'])): ?>
                    <button type="button" class="btn btn-outline-danger btn-sm d-none" id="crm-op-btn-eliminar" onclick="CRM.opEliminar()">
                        <i class="bi bi-trash"></i> Eliminar
                    </button>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary btn-sm px-3" id="crm-op-btn-guardar" onclick="CRM.opGuardar()">
                        <i class="bi bi-check2-circle me-1"></i>Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
