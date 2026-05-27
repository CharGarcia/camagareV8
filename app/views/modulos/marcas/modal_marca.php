<?php
/** @var array $perm */
/** @var string $rutaModulo */
/** @var array $vistaConfig */

// Asegurar consistencia si el modal se incluye desde otro módulo
$idUsuarioAct = (int)($_SESSION['id_usuario'] ?? 0);
$idEmpresaAct = (int)($_SESSION['id_empresa'] ?? 0);
$nivelAct = (int)($_SESSION['nivel'] ?? 1);

// 1. Forzar vistaConfig de marcas para ocultar pestañas correctamente
$vistaConfigMar = \App\Helpers\PreferenciasHelper::getPreferenciasVista('marcas');
echo \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfigMar, 'estiloVistaPestanasMar');

// 2. Forzar permisos de marcas
$permMar = $perm ?? [];
if (($rutaModulo ?? '') !== 'modulos/marcas') {
    $modelPerm = new \App\models\PermisoSubmodulo();
    $idSubMar = $modelPerm->getIdSubmoduloPorRutaMvc('modulos/marcas');
    if ($idSubMar) {
        $mapPerm = $modelPerm->getPermisosDeUsuario($idUsuarioAct, $idEmpresaAct);
        if (isset($mapPerm[$idSubMar])) {
            $p = $mapPerm[$idSubMar];
            $permMar = [
                'ver' => !empty($p['ver']),
                'crear' => !empty($p['crear']),
                'actualizar' => !empty($p['actualizar']),
                'eliminar' => !empty($p['eliminar']),
                'todo' => !empty($p['t']),
            ];
        } else if ($nivelAct >= 3) {
            $permMar = ['ver' => true, 'crear' => true, 'actualizar' => true, 'eliminar' => true, 'todo' => true];
        }
    }
}

$urlBaseMarShared = BASE_URL . '/modulos/marcas';
?>
<!-- Modal Ficha de Marca -->
<div class="modal fade" id="modalMarca" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <form id="formMarcaModal" novalidate onsubmit="return false;">
                <div class="modal-header bg-light py-3">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-award-fill me-2 text-primary"></i>
                        <span id="tituloModalMar">Nueva Marca</span>
                    </h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body pb-0">
                    <div id="modalAlertMar" class="alert d-none mb-3 py-2 small shadow-sm border-0"></div>
                    <input type="hidden" name="id" id="marca_id_modal" value="">
                    
                    <!-- Pestañas -->
                    <div class="d-flex align-items-center bg-light px-3 pt-2">
                        <ul class="nav nav-tabs border-bottom-0 flex-grow-1 tab-pestaña" id="tabsMarcaModal" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active py-2 small" id="tab-mar-general-btn" data-bs-toggle="tab" href="#tab-mar-general" role="tab"><i class="bi bi-tag me-1"></i> General</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link py-2 small" id="tab-mar-info-btn" data-bs-toggle="tab" href="#tab-mar-info" role="tab"><i class="bi bi-info-circle me-1"></i> Información</a>
                            </li>
                        </ul>
                        <div class="ms-auto pb-1">
                            <?php
                            $pestanasConfigMar = [
                                'tab-mar-info-btn' => 'Información'
                            ];
                            echo \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasConfigMar, $vistaConfigMar ?? [], 'marcas');
                            ?>
                        </div>
                    </div>
                    <div class="border-bottom bg-light mb-0"></div>

                    <div class="tab-content border-top px-4 py-3">
                        <!-- Pestaña General -->
                        <div class="tab-pane fade show active" id="tab-mar-general" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label small fw-bold text-muted mb-1">Nombre *</label>
                                    <input type="text" class="form-control form-control-sm shadow-none" name="nombre" id="marca_nombre_modal" required maxlength="100" placeholder="Ej. Sony">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold text-muted mb-1 d-flex align-items-center">Estado <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('marcas', 'marca_status_modal', 'status') ?></label>
                                    <select class="form-select form-select-sm shadow-none" name="status" id="marca_status_modal">
                                        <option value="1">Activo</option>
                                        <option value="0">Inactivo</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Pestaña Información -->
                        <div class="tab-pane fade" id="tab-mar-info" role="tabpanel">
                            <div class="p-2 border rounded-3 bg-white shadow-sm mb-3">
                                <div class="small fw-bold text-muted mb-2 d-flex align-items-center" style="font-size: 0.7rem;"><i class="bi bi-key-fill text-warning me-2"></i> PERMISOS</div>
                                <div class="d-flex flex-wrap gap-2">
                                    <span class="badge bg-<?= ($permMar['ver'] ?? true) ? 'success' : 'secondary text-opacity-50' ?> bg-opacity-10 text-<?= ($permMar['ver'] ?? true) ? 'success' : 'secondary' ?> border border-<?= ($permMar['ver'] ?? true) ? 'success' : 'secondary' ?> border-opacity-25 px-2" style="font-size: 0.65rem;">VER</span>
                                    <span class="badge bg-<?= ($permMar['crear'] ?? true) ? 'success' : 'secondary text-opacity-50' ?> bg-opacity-10 text-<?= ($permMar['crear'] ?? true) ? 'success' : 'secondary' ?> border border-<?= ($permMar['crear'] ?? true) ? 'success' : 'secondary' ?> border-opacity-25 px-2" style="font-size: 0.65rem;">CREAR</span>
                                    <span class="badge bg-<?= ($permMar['actualizar'] ?? true) ? 'success' : 'secondary text-opacity-50' ?> bg-opacity-10 text-<?= ($permMar['actualizar'] ?? true) ? 'success' : 'secondary' ?> border border-<?= ($permMar['actualizar'] ?? true) ? 'success' : 'secondary' ?> border-opacity-25 px-2" style="font-size: 0.65rem;">MODIFICAR</span>
                                    <span class="badge bg-<?= ($permMar['eliminar'] ?? true) ? 'success' : 'secondary text-opacity-50' ?> bg-opacity-10 text-<?= ($permMar['eliminar'] ?? true) ? 'success' : 'secondary' ?> border border-<?= ($permMar['eliminar'] ?? true) ? 'success' : 'secondary' ?> border-opacity-25 px-2" style="font-size: 0.65rem;">ELIMINAR</span>
                                </div>
                            </div>

                            <div class="bg-light rounded-3 p-3 border mb-3">
                                <h6 class="text-primary mb-3 small fw-bold"><i class="bi bi-box-seam me-2"></i>Vinculaciones</h6>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="small text-muted">Productos asociados:</span>
                                    <span class="fw-bold text-dark" id="info_mar_productos_count">0</span>
                                </div>
                            </div>
                            <div class="bg-light rounded-3 p-3 border">
                                <h6 class="text-primary mb-3 small fw-bold"><i class="bi bi-clock-history me-2"></i>Historial</h6>
                                <div id="auditoriaTimelineMar" class="position-relative mt-2" style="max-height: 200px; overflow-y: auto;">
                                    <div class="text-center py-3 text-muted small">Cargando...</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer justify-content-between bg-light border-top p-2">
                    <div>
                        <button type="button" class="btn btn-outline-danger btn-sm px-3 d-none" id="btnEliminarMarModal" onclick="eliminarMarcaModal()">
                            <i class="bi bi-trash3 me-1"></i> Eliminar
                        </button>
                    </div>
                    <div>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                            <i class="fa-solid fa-xmark me-1"></i>Cerrar
                        </button>
                        <button type="button" class="btn btn-primary btn-sm px-4" id="btnGuardarMarModal" onclick="guardarMarcaModal()">
                            <i class="bi bi-check2-circle me-1"></i> Guardar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>/js/modulos/marcas_modal.js?v=<?= time() ?>"></script>