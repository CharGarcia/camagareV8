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
                            <li class="nav-item">
                                <button class="nav-link fw-medium py-2 disabled" id="tab-uni-info-btn"
                                        data-bs-toggle="tab" data-bs-target="#tab-uni-info"
                                        type="button" role="tab" style="white-space:nowrap;">
                                    <i class="bi bi-info-circle me-1"></i> Información
                                </button>
                            </li>
                        </ul>
                        <?php
                        $pestanasUni = ['tab-uni-info-btn' => 'Información'];
                        echo \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasUni, $vistaConfig ?? [], 'modulos/unidades-medida_unidad');
                        ?>
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

                        <!-- ── Pestaña Información ── -->
                        <div class="tab-pane fade" id="tab-uni-info" role="tabpanel">

                            <!-- Tarjeta de Permisos -->
                            <div class="col-12 px-3">
                                <div class="p-2 border rounded-3 bg-white shadow-sm mt-0 mb-3">
                                    <div class="small fw-bold text-muted mb-2 d-flex align-items-center" style="font-size:0.7rem;">
                                        <i class="bi bi-key-fill text-warning me-2"></i> MIS PERMISOS EN ESTE MÓDULO
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <span class="badge bg-<?= $perm['ver']       ? 'success' : 'secondary text-opacity-50' ?> bg-opacity-10 text-<?= $perm['ver']       ? 'success' : 'secondary' ?> border border-<?= $perm['ver']       ? 'success' : 'secondary' ?> border-opacity-25" style="font-size:0.65rem;">VER</span>
                                        <span class="badge bg-<?= $perm['crear']     ? 'success' : 'secondary text-opacity-50' ?> bg-opacity-10 text-<?= $perm['crear']     ? 'success' : 'secondary' ?> border border-<?= $perm['crear']     ? 'success' : 'secondary' ?> border-opacity-25" style="font-size:0.65rem;">CREAR</span>
                                        <span class="badge bg-<?= $perm['actualizar']? 'success' : 'secondary text-opacity-50' ?> bg-opacity-10 text-<?= $perm['actualizar']? 'success' : 'secondary' ?> border border-<?= $perm['actualizar']? 'success' : 'secondary' ?> border-opacity-25" style="font-size:0.65rem;">MODIFICAR</span>
                                        <span class="badge bg-<?= $perm['eliminar']  ? 'success' : 'secondary text-opacity-50' ?> bg-opacity-10 text-<?= $perm['eliminar']  ? 'success' : 'secondary' ?> border border-<?= $perm['eliminar']  ? 'success' : 'secondary' ?> border-opacity-25" style="font-size:0.65rem;">ELIMINAR</span>
                                        <?php if ($perm['todo']): ?>
                                        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25" style="font-size:0.65rem;">ACCESO TOTAL</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Datos de registro -->
                            <div class="bg-light rounded-3 p-3 border mb-3 mx-3">
                                <h6 class="text-primary mb-3 small fw-bold"><i class="bi bi-info-circle me-2"></i>Datos del Registro</h6>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <span class="small text-muted d-block">Tipo de medida:</span>
                                        <span class="small fw-medium" id="info_uni_tipo">-</span>
                                    </div>
                                    <div class="col-6">
                                        <span class="small text-muted d-block">Creado:</span>
                                        <span class="small fw-medium" id="info_uni_created_at">-</span>
                                    </div>
                                    <div class="col-6">
                                        <span class="small text-muted d-block">Creado por:</span>
                                        <span class="small fw-medium" id="info_uni_created_by">-</span>
                                    </div>
                                    <div class="col-6">
                                        <span class="small text-muted d-block">Última modificación:</span>
                                        <span class="small fw-medium" id="info_uni_updated_at">-</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Historial -->
                            <div class="bg-light rounded-3 p-3 border mx-3">
                                <h6 class="text-primary mb-2 small fw-bold"><i class="bi bi-list-ul me-2"></i>Historial de cambios</h6>
                                <div id="historialUnidadContainer" style="max-height:200px;overflow-y:auto;">
                                    <div class="text-center py-3 text-muted small">
                                        <div class="spinner-border spinner-border-sm mb-1" role="status"></div>
                                        <div>Cargando...</div>
                                    </div>
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

<script src="<?= BASE_URL ?>/js/modulos/unidades_medida_modal.js?v=<?= time() ?>"></script>

