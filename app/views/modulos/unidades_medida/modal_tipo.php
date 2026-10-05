<?php
/** @var array  $perm */
/** @var array  $vistaConfig */
$urlBaseModalTipo = BASE_URL . '/modulos/unidades-medida';
?>
<!-- ══════════════════════════════════════════════════════════════════════
     MODAL TIPO DE MEDIDA
══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalTipoMedida" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" style="z-index:1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <form id="formTipoModal" novalidate onsubmit="return false;">
                <div class="modal-header bg-light border-bottom-0 py-3">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-tag-fill me-2 text-primary"></i>
                        <span id="tituloModalTipo">Nuevo Tipo de Medida</span>
                    </h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body pb-0">
                    <div id="alertModalTipo" class="alert d-none mb-3 py-2 small shadow-sm border-0"></div>
                    <input type="hidden" name="id" id="tipo_id_modal" value="">

                    <!-- Pestañas -->
                    <div class="d-flex align-items-center mb-1 px-3">
                        <ul class="nav nav-tabs border-bottom-0 flex-grow-1" id="tabsTipoModal" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link active fw-medium py-2" id="tab-tipo-general-btn"
                                        data-bs-toggle="tab" data-bs-target="#tab-tipo-general"
                                        type="button" role="tab" style="white-space:nowrap;">
                                    <i class="bi bi-card-list me-1"></i> General
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="border-bottom mb-3 mx-3"></div>

                    <div class="tab-content pb-3">

                        <!-- ── Pestaña General ── -->
                        <div class="tab-pane fade show active" id="tab-tipo-general" role="tabpanel">
                            <div class="row g-3 px-1">
                                <div class="col-4">
                                    <label class="form-label small fw-bold text-muted mb-1">Código</label>
                                    <input type="text" class="form-control form-control-sm shadow-none"
                                           name="codigo" id="tipo_codigo_modal"
                                           maxlength="50" placeholder="Ej: PESO">
                                    <div class="form-text">Opcional</div>
                                </div>
                                <div class="col-8">
                                    <label class="form-label small fw-bold text-muted mb-1">Nombre <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm shadow-none"
                                           name="nombre" id="tipo_nombre_modal"
                                           required maxlength="100" placeholder="Ej: Peso">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold text-muted mb-1">Estado</label>
                                    <select class="form-select form-select-sm shadow-none" name="status" id="tipo_status_modal">
                                        <option value="1">Activo</option>
                                        <option value="0">Inactivo</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                    </div><!-- /tab-content -->
                </div><!-- /modal-body -->

                <div class="modal-footer justify-content-between bg-light border-top-0 py-3">
                    <div>
                        <?php if ($perm['eliminar']): ?>
                        <button type="button" class="btn btn-outline-danger btn-sm d-none" id="btnEliminarTipoModal" onclick="eliminarTipoModal()">
                            <i class="bi bi-trash"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-link text-decoration-none text-muted btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <?php if ($perm['crear'] || $perm['actualizar']): ?>
                        <button type="button" class="btn btn-primary btn-sm px-4 shadow-sm" id="btnGuardarTipoModal" onclick="guardarTipoModal()">
                            <i class="bi bi-check-lg"></i> Guardar
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>/js/modulos/unidades_medida_modal.js?v=<?= asset_ver('/js/modulos/unidades_medida_modal.js') ?>"></script>

