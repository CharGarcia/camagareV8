<?php
/** @var array $perm */
/** @var string $rutaModulo */
/** @var array $vistaConfig */

// 1. Forzar vistaConfig de vehículos para ocultar pestañas correctamente
$vistaConfigVeh = \App\Helpers\PreferenciasHelper::getPreferenciasVista('vehiculos');
echo \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfigVeh, 'estiloVistaPestanasVeh');

// 2. Forzar permisos de vehículos
// Usar el helper canónico (y no reimplementar la resolución a mano): antes esto
// llamaba a getIdSubmoduloPorRutaMvc() (SINGULAR), que solo mira la PRIMERA fila
// de submodulos_menu con esa ruta. "Vehículos" cuelga de dos menús (Mecánica y
// Car-Wash) y si el permiso del usuario quedó asignado en la fila que no es la
// primera, esto se ocultaba aunque el usuario sí tuviera el permiso marcado en
// /config/permisos-modulos. Permisos::porRuta() ya prueba TODAS las filas con
// esa ruta antes de darlo por sin permiso (ver PermisoSubmodulo::getIdsSubmoduloPorRutaMvc()).
$permVeh = $perm ?? [];
if (($rutaModulo ?? '') !== 'modulos/vehiculos') {
    $permVeh = \App\Helpers\Permisos::porRuta('modulos/vehiculos');
}

$urlBaseVehShared = BASE_URL . '/modulos/vehiculos';
?>
<script>
    // Enviar recordatorios requiere permiso de modificar en Vehículos.
    window.VEH_PERM_ACTUALIZAR = <?= (!empty($permVeh['actualizar']) || !empty($permVeh['todo'])) ? 'true' : 'false' ?>;
</script>
<!-- Modal Vehículo -->
<div class="modal fade" id="modalVehiculo" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content shadow-lg border-0">
            <form id="formVehiculo" novalidate onsubmit="return false;">
                <div class="modal-header bg-light py-3">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-car-front-fill me-2 text-primary"></i>
                        <span id="tituloModal">Nuevo Vehículo</span>
                    </h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="modalAlert" class="alert d-none mx-3 mt-3 mb-0 py-2 small shadow-sm border-0"></div>
                    <input type="hidden" name="id" id="vehiculo_id" value="">

                    <!-- Pestañas: General (datos) / Transacciones (órdenes Car-Wash) / Recordatorios (próximas citas) -->
                    <?php
                    $pestanasVeh = [
                        'veh-pane-general'       => 'General',
                        'veh-pane-transacciones' => 'Transacciones',
                        'veh-pane-recordatorios' => 'Recordatorios',
                    ];
                    ?>
                    <ul class="nav nav-tabs nav-tabs-sm px-3 pt-2 align-items-center" role="tablist" id="vehTabs">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link active py-1 small" id="veh-tab-general" data-bs-toggle="tab" href="#veh-pane-general" data-bs-target="#veh-pane-general" role="tab"><i class="bi bi-card-text me-1"></i>General</a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link py-1 small veh-tab-requiere-id" id="veh-tab-transacciones" data-bs-toggle="tab" href="#veh-pane-transacciones" data-bs-target="#veh-pane-transacciones" role="tab"><i class="bi bi-clock-history me-1"></i>Transacciones <span class="badge bg-secondary bg-opacity-10 text-secondary ms-1 d-none" id="veh-badge-trx">0</span></a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link py-1 small veh-tab-requiere-id" id="veh-tab-recordatorios" data-bs-toggle="tab" href="#veh-pane-recordatorios" data-bs-target="#veh-pane-recordatorios" role="tab"><i class="bi bi-bell me-1"></i>Recordatorios</a>
                        </li>
                        <?= \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasVeh, $vistaConfigVeh ?? [], 'modulos/vehiculos', '__pestanas_ocultas__', 'estiloVistaPestanasVeh') ?>
                    </ul>

                    <div class="tab-content">
                    <div class="tab-pane fade show active" id="veh-pane-general" role="tabpanel">
                    <div class="px-4 py-3">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label mb-1 small fw-bold text-muted">Marca *</label>
                                <input type="text" class="form-control form-control-sm shadow-none" name="marca" id="vehiculo_marca" required maxlength="100" placeholder="Ej. TOYOTA">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label mb-1 small fw-bold text-muted">Placa *</label>
                                <input type="text" class="form-control form-control-sm shadow-none fw-bold" name="placa" id="vehiculo_placa" required maxlength="20" placeholder="Ej. ABC-1234" style="text-transform: uppercase;">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label mb-1 small fw-bold text-muted">Chasis</label>
                                <input type="text" class="form-control form-control-sm shadow-none" name="chasis" id="vehiculo_chasis" maxlength="100">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-bold text-muted">Año</label>
                                <input type="number" class="form-control form-control-sm shadow-none" name="anio" id="vehiculo_anio" min="1900" max="2100">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-1 small fw-bold text-muted d-flex align-items-center">Estado <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('vehiculos', 'vehiculo_estado', 'estado') ?></label>
                                <select class="form-select form-select-sm shadow-none" name="estado" id="vehiculo_estado">
                                    <option value="activo">Activo</option>
                                    <option value="inactivo">Inactivo</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label mb-1 small fw-bold text-muted">Propietario</label>
                                <input type="text" class="form-control form-control-sm shadow-none" name="propietario" id="vehiculo_propietario" maxlength="200">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label mb-1 small fw-bold text-muted">Correo Electrónico</label>
                                <input type="email" class="form-control form-control-sm shadow-none" name="correo" id="vehiculo_correo" placeholder="Ej. correo@ejemplo.com" maxlength="150">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label mb-1 small fw-bold text-muted">Teléfono</label>
                                <input type="text" class="form-control form-control-sm shadow-none" name="telefono" id="vehiculo_telefono" placeholder="Ej. 0987654321" maxlength="10" pattern="[0-9]{10}">
                            </div>
                        </div>
                    </div>
                    </div><!-- /veh-pane-general -->

                    <!-- Transacciones: todo lo que se le ha hecho al vehículo (órdenes de Car-Wash) -->
                    <div class="tab-pane fade" id="veh-pane-transacciones" role="tabpanel">
                        <div class="px-3 py-2">
                            <div class="d-flex flex-wrap gap-3 small mb-2" id="veh_trx_resumen"></div>
                            <div class="border rounded-3 bg-white veh-trx-lista" style="max-height:55vh; overflow:auto;">
                                <table class="table table-sm table-hover mb-0 text-nowrap" style="font-size:.8rem;">
                                    <thead class="table-light" style="position:sticky; top:0; z-index:1;">
                                        <tr>
                                            <th style="width:28px;"></th>
                                            <th>Fecha</th>
                                            <th>N° Orden</th>
                                            <th>Cliente</th>
                                            <th class="text-end">Km</th>
                                            <th>Servicios / productos</th>
                                            <th class="text-end">Total</th>
                                            <th>Documento</th>
                                            <th class="text-center">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody id="veh_trx_body"><tr><td colspan="9" class="text-center text-muted py-4">Sin transacciones.</td></tr></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Recordatorios: próximas citas (de Car-Wash) y envío por correo / WhatsApp -->
                    <div class="tab-pane fade" id="veh-pane-recordatorios" role="tabpanel">
                        <div class="px-3 py-2">
                            <div id="veh_rec_aviso"></div>
                            <div class="fw-semibold small text-muted mb-1"><i class="bi bi-calendar-event me-1"></i>Citas del vehículo (fijadas en las órdenes de Car-Wash)</div>
                            <div class="border rounded-3 bg-white mb-3 veh-rec-lista" style="max-height:30vh; overflow:auto;">
                                <table class="table table-sm table-hover mb-0 text-nowrap" style="font-size:.8rem;">
                                    <thead class="table-light" style="position:sticky; top:0; z-index:1;">
                                        <tr><th>Próxima cita</th><th>N° Orden</th><th>Cliente</th><th>Correo</th><th>Teléfono</th><th>Último aviso</th><th class="text-center">Enviar</th></tr>
                                    </thead>
                                    <tbody id="veh_rec_citas"><tr><td colspan="7" class="text-center text-muted py-3">Sin citas.</td></tr></tbody>
                                </table>
                            </div>
                            <div class="fw-semibold small text-muted mb-1"><i class="bi bi-send-check me-1"></i>Recordatorios enviados</div>
                            <div class="border rounded-3 bg-white veh-rec-lista" style="max-height:25vh; overflow:auto;">
                                <table class="table table-sm mb-0 text-nowrap" style="font-size:.8rem;">
                                    <thead class="table-light" style="position:sticky; top:0; z-index:1;">
                                        <tr><th>Enviado</th><th>Cita</th><th>N° Orden</th><th>Canal</th><th>Destinatario</th><th class="text-center">Estado</th><th>Origen</th><th>Usuario</th></tr>
                                    </thead>
                                    <tbody id="veh_rec_historial"><tr><td colspan="8" class="text-center text-muted py-3">Aún no se han enviado recordatorios.</td></tr></tbody>
                                </table>
                            </div>
                            <div class="form-text mt-2"><i class="bi bi-robot me-1"></i>Para enviarlos solos cada día, cree una automatización <b>Car-Wash → Recordatorio de próxima cita</b> en el módulo Automatizaciones (por correo o por WhatsApp). Cada cita se avisa una sola vez.</div>
                        </div>
                    </div>
                    </div><!-- /tab-content -->
                </div>

                <div class="modal-footer justify-content-between bg-light border-top p-2">
                    <div>
                        <button type="button" class="btn btn-outline-danger btn-sm px-3 d-none" id="btnEliminar" onclick="eliminarVehiculo()">
                            <i class="bi bi-trash3 me-1"></i> Eliminar
                        </button>
                    </div>
                    <div>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                            <i class="fa-solid fa-xmark me-1"></i>Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary btn-sm px-4 shadow-sm" id="btnGuardar">
                            <i class="bi bi-check2-circle me-1"></i> Guardar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
