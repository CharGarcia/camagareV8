<?php
/**
 * Modal de alta/edición de Responsable de Traslado.
 * Sin pestaña "Información": los permisos se administran en /config/permisos-modulos
 * y el historial del registro se consulta en log_sistema.
 *
 * @var array $perm
 */
?>
<div class="modal fade" id="modalResponsable" tabindex="-1" aria-labelledby="modalResponsableLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header bg-light border-bottom py-2 px-3">
                <h6 class="modal-title fw-bold mb-0" id="modalResponsableLabel">
                    <i class="bi bi-person-badge me-2 text-primary"></i>
                    <span id="rt-modal-titulo">Nuevo Responsable de Traslado</span>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <!-- Pestañas (cada usuario puede ocultarlas desde el dropdown de la vista) -->
            <div class="d-flex align-items-center bg-light px-3 pt-2">
                <ul class="nav nav-tabs border-bottom-0 flex-grow-1 tab-pestaña" id="rt-tabs-nav">
                    <li class="nav-item">
                        <button class="nav-link active small fw-semibold" id="rt-tab-general-btn"
                                data-bs-toggle="tab" data-bs-target="#rt-pane-general" type="button">
                            <i class="bi bi-person-vcard me-1"></i>General
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link small fw-semibold" id="rt-tab-usuarios-btn"
                                data-bs-toggle="tab" data-bs-target="#rt-pane-usuarios" type="button">
                            <i class="bi bi-people me-1"></i>Usuarios vinculados
                            <span class="badge bg-secondary bg-opacity-25 text-secondary ms-1 d-none" id="rt-usuarios-contador">0</span>
                        </button>
                    </li>
                </ul>
                <?php
                // Las claves son el id del PANEL (no el del botón): es lo que oculta
                // renderEstilosPestanasOcultas(), que casa con .nav-link[data-bs-target="#id"].
                echo \App\Helpers\PreferenciasHelper::renderDropdownPestanas(
                    ['rt-pane-usuarios' => 'Usuarios vinculados'],
                    $vistaConfig ?? [],
                    'modulos/responsables-traslados'
                );
                ?>
            </div>

            <div class="modal-body p-0">
                <input type="hidden" id="rt-id">

              <div class="tab-content border-top">
                <div class="tab-pane fade show active px-3 py-3" id="rt-pane-general">
                <div class="row g-3">
                    <div class="col-12">
                        <label for="rt-nombre" class="form-label fw-semibold small">
                            Nombre completo <span class="text-danger">*</span>
                        </label>
                        <input type="text" id="rt-nombre" class="form-control form-control-sm"
                               maxlength="100" placeholder="Nombres y apellidos del responsable"
                               style="text-transform:uppercase">
                    </div>

                    <div class="col-md-5">
                        <label for="rt-identificacion" class="form-label fw-semibold small">
                            Identificación <small class="text-muted fw-normal">(opcional)</small>
                        </label>
                        <input type="text" id="rt-identificacion" class="form-control form-control-sm"
                               maxlength="20" placeholder="Cédula, RUC o pasaporte" inputmode="text">
                    </div>

                    <div class="col-md-4">
                        <label for="rt-telefono" class="form-label fw-semibold small">
                            Teléfono <small class="text-muted fw-normal">(opcional)</small>
                        </label>
                        <input type="text" id="rt-telefono" class="form-control form-control-sm"
                               maxlength="20" placeholder="0999999999">
                    </div>

                    <div class="col-md-3">
                        <label for="rt-estado" class="form-label fw-semibold small">Estado</label>
                        <select id="rt-estado" class="form-select form-select-sm">
                            <option value="activo">Activo</option>
                            <option value="inactivo">Inactivo</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label for="rt-email" class="form-label fw-semibold small">
                            Correo electrónico <small class="text-muted fw-normal">(opcional)</small>
                        </label>
                        <input type="email" id="rt-email" class="form-control form-control-sm"
                               maxlength="150" placeholder="responsable@correo.com">
                        <div id="rt-email-error" class="text-danger small mt-1 d-none"></div>
                    </div>

                    <div class="col-12">
                        <div class="alert alert-light border small mb-0 py-2 px-3">
                            <i class="bi bi-info-circle me-1 text-primary"></i>
                            Un responsable <strong>inactivo</strong> deja de aparecer en los formularios de
                            Pedidos y Consignaciones, pero se conserva en los documentos ya emitidos.
                        </div>
                    </div>
                </div>

                <div id="rt-auditoria" class="text-muted small mt-3 d-none"></div>
                </div><!-- /rt-pane-general -->

                <!-- ── Usuarios vinculados ────────────────────────────────────
                     Vista inversa del vínculo que se administra en la ficha del
                     usuario (Configuración → Usuarios del sistema → pestaña
                     "Responsables de traslado"). Decide qué entregas ve cada
                     repartidor en la app móvil. -->
                <div class="tab-pane fade px-3 py-3" id="rt-pane-usuarios">
                    <p class="text-muted small mb-3">
                        Los usuarios vinculados aquí ven, en el módulo <strong>Entregas de Consignaciones</strong>,
                        las consignaciones asignadas a este responsable. Solo aplica a quienes
                        <strong>no</strong> tienen "acceso total" en ese módulo: con acceso total ven todas.
                    </p>

                    <div id="rt-usuarios-editor" class="row g-2 mb-3 d-none">
                        <div class="col-md-9">
                            <label class="form-label small fw-semibold d-block">Agregar usuario</label>
                            <select id="rt-select-usuario" class="form-select form-select-sm">
                                <option value="">Cargando…</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="button" class="btn btn-sm btn-primary w-100" id="rt-btn-vincular">
                                <i class="bi bi-plus-lg"></i> Agregar
                            </button>
                        </div>
                    </div>

                    <div id="rt-usuarios-solo-lectura" class="alert alert-light border small py-2 px-3 mb-3 d-none">
                        <i class="bi bi-lock me-1 text-secondary"></i>
                        Solo lectura: vincular usuarios requiere perfil de administrador.
                        Se administra en <strong>Configuración → Usuarios del sistema</strong>.
                    </div>

                    <div class="rt-usuarios-scroll">
                        <table class="table table-sm table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Usuario</th>
                                    <th>Correo</th>
                                    <th class="text-center">App móvil</th>
                                    <th class="text-end">Quitar</th>
                                </tr>
                            </thead>
                            <tbody id="rt-tbody-usuarios">
                                <tr><td colspan="4" class="text-muted small">Guarde el responsable para poder vincular usuarios.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div><!-- /rt-pane-usuarios -->
              </div><!-- /tab-content -->
            </div>

            <div class="modal-footer bg-light border-top py-2 justify-content-between">
                <div>
                    <?php if (!empty($perm['eliminar'])): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger d-none"
                                id="btn-rt-eliminar" onclick="RT_eliminar()">
                            <i class="bi bi-trash3 me-1"></i> Eliminar
                        </button>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i> Cancelar
                    </button>
                    <?php if (!empty($perm['crear']) || !empty($perm['actualizar'])): ?>
                        <button type="button" class="btn btn-sm btn-primary" id="btn-rt-guardar" onclick="RT_guardar()">
                            <i class="bi bi-check2-circle me-1"></i> Guardar
                        </button>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>
