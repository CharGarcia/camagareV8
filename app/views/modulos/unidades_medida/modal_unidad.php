<?php
/** @var array  $perm */
/** @var array  $vistaConfig */
/** @var array  $tiposSelect */
$urlBaseModalUni = BASE_URL . '/modulos/unidades-medida';
$tiposSelect     = $tiposSelect ?? [];
?>
<!-- ══════════════════════════════════════════════════════════════════════
     MODAL UNIDAD DE MEDIDA
══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalUnidadMedida" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" style="z-index:1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <form id="formUnidadModal" novalidate onsubmit="return false;">
                <div class="modal-header bg-light border-bottom-0 py-3">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-rulers me-2 text-primary"></i>
                        <span id="tituloModalUnidad">Nueva Unidad de Medida</span>
                    </h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body pb-0">
                    <div id="alertModalUnidad" class="alert d-none mb-3 py-2 small shadow-sm border-0"></div>
                    <input type="hidden" name="id" id="unidad_id_modal" value="">

                    <!-- Pestañas -->
                    <div class="d-flex align-items-center mb-1 px-3">
                        <ul class="nav nav-tabs border-bottom-0 flex-grow-1" id="tabsUnidadModal" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link active fw-medium py-2" id="tab-uni-general-btn"
                                        data-bs-toggle="tab" data-bs-target="#tab-uni-general"
                                        type="button" role="tab" style="white-space:nowrap;">
                                    <i class="bi bi-card-list me-1"></i> General
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="border-bottom mb-3 mx-3"></div>

                    <div class="tab-content pb-3">

                        <!-- ── Pestaña General ── -->
                        <div class="tab-pane fade show active" id="tab-uni-general" role="tabpanel">
                            <div class="row g-3 px-1">

                                <!-- Tipo de Medida -->
                                <div class="col-12">
                                    <label class="form-label small fw-bold text-muted mb-1">Tipo de Medida <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm shadow-none" name="id_tipo" id="unidad_tipo_modal" required>
                                        <option value="">- Seleccione un tipo -</option>
                                        <?php foreach ($tiposSelect as $t): ?>
                                        <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['nombre']) ?><?= !empty($t['codigo']) ? ' (' . htmlspecialchars($t['codigo']) . ')' : '' ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Código y Nombre -->
                                <div class="col-4">
                                    <label class="form-label small fw-bold text-muted mb-1">Código</label>
                                    <input type="text" class="form-control form-control-sm shadow-none"
                                           name="codigo" id="unidad_codigo_modal"
                                           maxlength="50" placeholder="Ej: KG">
                                    <div class="form-text">Opcional</div>
                                </div>
                                <div class="col-8">
                                    <label class="form-label small fw-bold text-muted mb-1">Nombre <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm shadow-none"
                                           name="nombre" id="unidad_nombre_modal"
                                           required maxlength="100" placeholder="Ej: Kilogramo">
                                </div>

                                <!-- Abreviatura y Es Base -->
                                <div class="col-4">
                                    <label class="form-label small fw-bold text-muted mb-1">Abreviatura <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm shadow-none"
                                           name="abreviatura" id="unidad_abreviatura_modal"
                                           required maxlength="20" placeholder="Ej: kg">
                                </div>
                                <div class="col-8">
                                    <label class="form-label small fw-bold text-muted mb-1 d-block">&nbsp;</label>
                                    <div class="form-check form-switch mt-1">
                                        <input class="form-check-input" type="checkbox" role="switch"
                                               name="es_base" id="unidad_esbase_modal" value="1"
                                               onchange="toggleFactorBase(this)">
                                        <label class="form-check-label small fw-bold" for="unidad_esbase_modal">
                                            <i class="bi bi-star-fill text-warning me-1" style="font-size:0.75rem;"></i>
                                            Es unidad base del tipo
                                        </label>
                                    </div>
                                    <div class="form-text">Solo puede haber una base por tipo. El factor base será 1.</div>
                                </div>

                                <!-- Factor Base -->
                                <div class="col-12" id="wrapperFactorBase">
                                    <label class="form-label small fw-bold text-muted mb-1">
                                        Factor Base
                                        <span class="text-muted fw-normal" style="font-size:0.7rem;">
                                            <i class="bi bi-question-circle ms-1"
                                               title="Cuántas unidades BASE equivale 1 de esta unidad.&#10;Ej: 1 lb = 0.453592 kg → factor = 0.453592&#10;La unidad base siempre tiene factor = 1."></i>
                                        </span>
                                    </label>
                                    <input type="number" class="form-control form-control-sm shadow-none"
                                           name="factor_base" id="unidad_factor_modal"
                                           step="0.000001" min="0" value="1" placeholder="1">
                                    <div class="form-text">Cuántas unidades base equivale 1 de esta unidad. Ej: 1 lb = 0.453592 kg.</div>
                                </div>

                                <!-- Estado -->
                                <div class="col-12">
                                    <label class="form-label small fw-bold text-muted mb-1">Estado</label>
                                    <select class="form-select form-select-sm shadow-none" name="status" id="unidad_status_modal">
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
                        <button type="button" class="btn btn-outline-danger btn-sm d-none" id="btnEliminarUnidadModal" onclick="eliminarUnidadModal()">
                            <i class="bi bi-trash"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-link text-decoration-none text-muted btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <?php if ($perm['crear'] || $perm['actualizar']): ?>
                        <button type="button" class="btn btn-primary btn-sm px-4 shadow-sm" id="btnGuardarUnidadModal" onclick="guardarUnidadModal()">
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

