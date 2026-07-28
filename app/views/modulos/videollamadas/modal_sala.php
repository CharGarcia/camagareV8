<?php

/**
 * Modal de creación/edición de una sala de videollamada.
 *
 * @var array $perm
 * @var array $usuarios
 * @var array $vistaConfig
 * @var int   $maxMesh
 */
?>
<div class="modal fade" id="modalSalaVC" data-bs-backdrop="static" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">

            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-camera-video-fill text-primary me-2"></i>
                    <span id="vcModalTitulo">Nueva reunión</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body p-0">

                <!-- Barra de Acciones Superior -->
                <div class="px-3 py-2 bg-light border-bottom d-flex gap-1 align-items-center flex-wrap">
                    <button type="button" class="btn btn-outline-success btn-sm px-2 d-none" id="vcBtnEntrar"
                            onclick="VC_entrarSala()" title="Entrar a la sala">
                        <i class="bi bi-box-arrow-in-right fs-6"></i>
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sm px-2 d-none" id="vcBtnCopiar"
                            onclick="VC_copiarEnlace()" title="Copiar el enlace de la reunión">
                        <i class="bi bi-link-45deg fs-6"></i>
                    </button>
                    <div class="vr mx-1 d-none" id="vcSepAcciones"></div>
                    <button type="button" class="btn btn-outline-danger btn-sm px-2 d-none" id="vcBtnFinalizar"
                            onclick="VC_finalizarSala()" title="Finalizar la reunión">
                        <i class="bi bi-stop-circle fs-6"></i>
                    </button>
                    <span class="ms-auto small text-muted d-none" id="vcCodigoSala"></span>
                </div>

                <!-- Pestañas -->
                <div class="d-flex align-items-center bg-light px-3 pt-2">
                    <ul class="nav nav-tabs border-bottom-0 flex-grow-1" id="vcTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active py-2 small fw-bold" data-bs-toggle="tab" href="#vc-tab-general" role="tab">
                                <i class="bi bi-card-text me-1"></i> General
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link py-2 small fw-bold" data-bs-toggle="tab" href="#vc-tab-participantes" role="tab">
                                <i class="bi bi-people me-1"></i> Participantes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link py-2 small fw-bold" data-bs-toggle="tab" href="#vc-tab-info" role="tab">
                                <i class="bi bi-info-circle me-1"></i> Información
                            </a>
                        </li>
                    </ul>
                    <div class="ms-2">
                        <?php
                        $pestanasConfig = [
                            'vc-tab-participantes' => 'Participantes',
                            'vc-tab-info'          => 'Información',
                        ];
                        echo \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasConfig, $vistaConfig ?? [], 'videollamadas');
                        ?>
                    </div>
                </div>
                <div class="border-bottom bg-light mb-0"></div>

                <form id="vcForm">
                    <input type="hidden" name="id" id="vc-id" value="">

                    <div class="tab-content border-top">

                        <!-- ─── GENERAL ─────────────────────────────────────── -->
                        <div class="tab-pane fade show active p-3" id="vc-tab-general" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label small fw-bold">Título de la reunión</label>
                                    <input type="text" name="titulo" id="vc-titulo" class="form-control form-control-sm"
                                           maxlength="200" placeholder="Ej. Revisión de cierre mensual" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Tipo</label>
                                    <select name="tipo" id="vc-tipo" class="form-select form-select-sm" onchange="VC_onCambioTipo()">
                                        <option value="instantanea">Instantánea</option>
                                        <option value="programada">Programada</option>
                                        <option value="permanente">Permanente</option>
                                    </select>
                                </div>

                                <div class="col-md-4 d-none" id="vc-wrap-fecha">
                                    <label class="form-label small fw-bold">Fecha y hora de inicio</label>
                                    <input type="datetime-local" name="fecha_inicio" id="vc-fecha-inicio" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Duración estimada (minutos)</label>
                                    <input type="number" name="duracion_minutos" id="vc-duracion" class="form-control form-control-sm"
                                           min="0" max="1440" value="60">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Anfitrión</label>
                                    <select name="id_anfitrion" id="vc-anfitrion" class="form-select form-select-sm">
                                        <?php foreach ($usuarios as $u): ?>
                                            <option value="<?= (int) $u['id'] ?>"><?= htmlspecialchars((string) $u['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Máximo de participantes</label>
                                    <input type="number" name="max_participantes" id="vc-max" class="form-control form-control-sm"
                                           min="2" max="<?= (int) $maxMesh ?>" value="6" onchange="VC_avisoCapacidad()">
                                    <div class="form-text small" id="vc-aviso-capacidad">
                                        El motor interno admite hasta <?= (int) $maxMesh ?> participantes.
                                    </div>
                                </div>

                                <div class="col-md-8">
                                    <label class="form-label small fw-bold">Descripción</label>
                                    <textarea name="descripcion" id="vc-descripcion" class="form-control form-control-sm" rows="2"
                                              placeholder="Agenda o notas de la reunión (opcional)"></textarea>
                                </div>
                            </div>

                            <hr class="my-3">

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="sala_espera" id="vc-sala-espera" checked>
                                        <label class="form-check-label small" for="vc-sala-espera">
                                            Sala de espera
                                            <i class="bi bi-question-circle text-muted"
                                               title="El anfitrión admite a cada persona antes de que entre."></i>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="permite_invitados" id="vc-invitados">
                                        <label class="form-check-label small" for="vc-invitados">
                                            Permitir invitados externos
                                            <i class="bi bi-question-circle text-muted"
                                               title="Personas sin cuenta en el sistema, que entran por un enlace."></i>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="grabar" id="vc-grabar">
                                        <label class="form-check-label small" for="vc-grabar">Grabar la reunión</label>
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-warning py-2 px-3 mt-3 mb-0 small d-none" id="vc-aviso-grabacion">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                La grabación exige avisar a todos los participantes al inicio de la reunión y queda
                                registrada en la bitácora. Estará disponible cuando se habilite la grabación en la
                                configuración del módulo.
                            </div>
                        </div>

                        <!-- ─── PARTICIPANTES ───────────────────────────────── -->
                        <div class="tab-pane fade p-3" id="vc-tab-participantes" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="small text-muted">
                                    El anfitrión se agrega automáticamente.
                                </div>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="VC_agregarParticipante('usuario')">
                                        <i class="bi bi-person-plus me-1"></i> Usuario
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="vcBtnAgregarInvitado"
                                            onclick="VC_agregarParticipante('invitado')">
                                        <i class="bi bi-person-badge me-1"></i> Invitado
                                    </button>
                                </div>
                            </div>

                            <div class="table-responsive border rounded">
                                <table class="table table-sm mb-0 align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width:45%;" class="small">Participante</th>
                                            <th style="width:30%;" class="small">Correo</th>
                                            <th style="width:20%;" class="small">Rol</th>
                                            <th style="width:5%;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="vcTbodyParticipantes"></tbody>
                                </table>
                            </div>
                            <div class="small text-muted mt-2" id="vc-contador-participantes"></div>
                        </div>

                        <!-- ─── INFORMACIÓN ─────────────────────────────────── -->
                        <div class="tab-pane fade" id="vc-tab-info" role="tabpanel">
                            <div class="bg-light rounded-3 p-3 border mt-3 mb-3 mx-3">
                                <div class="row g-3 small">
                                    <div class="col-md-6">
                                        <div class="text-muted" style="font-size:.7rem;">CÓDIGO DE SALA</div>
                                        <div class="fw-bold"><code id="vc-info-codigo">—</code></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="text-muted" style="font-size:.7rem;">ESTADO</div>
                                        <div id="vc-info-estado">—</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="text-muted" style="font-size:.7rem;">CREADA POR</div>
                                        <div id="vc-info-creador">—</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="text-muted" style="font-size:.7rem;">FECHA DE CREACIÓN</div>
                                        <div id="vc-info-creada">—</div>
                                    </div>
                                    <div class="col-12">
                                        <div class="text-muted" style="font-size:.7rem;">ENLACE DE LA REUNIÓN</div>
                                        <div class="input-group input-group-sm mt-1">
                                            <input type="text" class="form-control form-control-sm bg-white" id="vc-info-enlace" readonly>
                                            <button class="btn btn-outline-secondary" type="button" onclick="VC_copiarEnlace()">
                                                <i class="bi bi-clipboard"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </form>
            </div>

            <div class="modal-footer bg-light py-2 d-flex justify-content-between">
                <div>
                    <?php if ($perm['eliminar']): ?>
                        <button type="button" class="btn btn-outline-danger btn-sm d-none" id="vcBtnEliminar" onclick="VC_eliminar()">
                            <i class="bi bi-trash me-1"></i> Eliminar
                        </button>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary btn-sm px-3" id="vcBtnGuardar" onclick="VC_guardar()">
                        <i class="bi bi-save me-1"></i> Guardar
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>
