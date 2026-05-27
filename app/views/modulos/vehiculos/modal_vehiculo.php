<?php
/** @var array $perm */
/** @var string $rutaModulo */
/** @var array $vistaConfig */

// Asegurar consistencia si el modal se incluye desde otro módulo
$idUsuarioAct = (int)($_SESSION['id_usuario'] ?? 0);
$idEmpresaAct = (int)($_SESSION['id_empresa'] ?? 0);
$nivelAct = (int)($_SESSION['nivel'] ?? 1);

// 1. Forzar vistaConfig de vehículos para ocultar pestañas correctamente
$vistaConfigVeh = \App\Helpers\PreferenciasHelper::getPreferenciasVista('vehiculos');
echo \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfigVeh, 'estiloVistaPestanasVeh');

// 2. Forzar permisos de vehículos
$permVeh = $perm ?? [];
if (($rutaModulo ?? '') !== 'modulos/vehiculos') {
    $modelPerm = new \App\models\PermisoSubmodulo();
    $idSubVeh = $modelPerm->getIdSubmoduloPorRutaMvc('modulos/vehiculos');
    if ($idSubVeh) {
        $mapPerm = $modelPerm->getPermisosDeUsuario($idUsuarioAct, $idEmpresaAct);
        if (isset($mapPerm[$idSubVeh])) {
            $p = $mapPerm[$idSubVeh];
            $permVeh = [
                'ver' => !empty($p['ver']),
                'crear' => !empty($p['crear']),
                'actualizar' => !empty($p['actualizar']),
                'eliminar' => !empty($p['eliminar']),
                'todo' => !empty($p['t']),
            ];
        } else if ($nivelAct >= 3) {
            $permVeh = ['ver' => true, 'crear' => true, 'actualizar' => true, 'eliminar' => true, 'todo' => true];
        }
    }
}

$urlBaseVehShared = BASE_URL . '/modulos/vehiculos';
?>
<!-- Modal Vehículo -->
<div class="modal fade" id="modalVehiculo" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered modal-lg">
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
                                <label class="form-label mb-1 small fw-bold text-muted">Propietario *</label>
                                <input type="text" class="form-control form-control-sm shadow-none" name="propietario" id="vehiculo_propietario" required maxlength="200">
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
