<?php
/** @var array $perm */
/** @var string $rutaModulo */
/** @var array $vistaConfig */

// Asegurar consistencia si el modal se incluye desde otro módulo
$idUsuarioAct = (int)($_SESSION['id_usuario'] ?? 0);
$idEmpresaAct = (int)($_SESSION['id_empresa'] ?? 0);
$nivelAct = (int)($_SESSION['nivel'] ?? 1);

// 1. Forzar vistaConfig de vendedores para ocultar pestañas correctamente
$vistaConfigVend = \App\Helpers\PreferenciasHelper::getPreferenciasVista('vendedores');
echo \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfigVend, 'estiloVistaPestanasVend');

// 2. Forzar permisos de vendedores
$permVend = $perm ?? [];
if (($rutaModulo ?? '') !== 'modulos/vendedores') {
    $modelPerm = new \App\models\PermisoSubmodulo();
    $idSubVend = $modelPerm->getIdSubmoduloPorRutaMvc('modulos/vendedores');
    if ($idSubVend) {
        $mapPerm = $modelPerm->getPermisosDeUsuario($idUsuarioAct, $idEmpresaAct);
        if (isset($mapPerm[$idSubVend])) {
            $p = $mapPerm[$idSubVend];
            $permVend = [
                'ver' => !empty($p['ver']),
                'crear' => !empty($p['crear']),
                'actualizar' => !empty($p['actualizar']),
                'eliminar' => !empty($p['eliminar']),
                'todo' => !empty($p['t']),
            ];
        } else if ($nivelAct >= 3) {
            $permVend = ['ver' => true, 'crear' => true, 'actualizar' => true, 'eliminar' => true, 'todo' => true];
        }
    }
}

$urlBaseVendShared = BASE_URL . '/modulos/vendedores';
?>
<!-- Modal Vendedor -->
<div class="modal fade" id="modalVendedor" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" style="z-index: 1060;">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <form id="formVendedor" novalidate onsubmit="return false;">
                <div class="modal-header bg-light py-3">
                    <h5 class="modal-title fw-bold" id="tituloModalVendedorLabel">
                        <i class="bi bi-person-badge text-primary me-2"></i> Nuevo Vendedor
                    </h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="modalAlertVendedor" class="alert d-none mx-3 mt-3 mb-0 py-2 small shadow-sm border-0"></div>
                    <input type="hidden" name="id" id="vendedor_id">

                    <!-- Pestañas -->
                    <div class="d-flex align-items-center bg-light px-3 pt-2">
                        <ul class="nav nav-tabs border-bottom-0 flex-grow-1 tab-pestaña" id="modalVendedorTabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active py-2 small" id="tab-general-vendedor-btn" data-bs-toggle="tab" href="#pane-general-vendedor" role="tab"><i class="bi bi-card-text me-1"></i>General</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link py-2 small" id="tab-info-vendedor-btn" data-bs-toggle="tab" href="#pane-info-vendedor" role="tab"><i class="bi bi-info-circle me-1"></i>Información</a>
                            </li>
                        </ul>
                        <div class="ms-auto pb-1">
                            <?php
                            $pestanasConfigVend = [
                                'tab-info-vendedor-btn' => 'Información'
                            ];
                            echo \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasConfigVend, $vistaConfigVend ?? [], 'vendedores');
                            ?>
                        </div>
                    </div>
                    <div class="border-bottom bg-light mb-0"></div>

                    <div class="tab-content border-top px-4 py-3">
                        <div class="tab-pane fade show active" id="pane-general-vendedor" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label mb-1 small fw-bold text-muted">Nombre Completo *</label>
                                    <input type="text" name="nombre" id="vendedor_nombre" class="form-control form-control-sm shadow-none" required maxlength="100">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label mb-1 small fw-bold text-muted">Identificación / Cédula *</label>
                                    <input type="text" name="identificacion" id="vendedor_identificacion" class="form-control form-control-sm shadow-none" required maxlength="20">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label mb-1 small fw-bold text-muted d-flex align-items-center">Estado <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('vendedores', 'vendedor_status', 'status') ?></label>
                                    <select name="status" id="vendedor_status" class="form-select form-select-sm shadow-none">
                                        <option value="1">Activo</option>
                                        <option value="0">Inactivo</option>
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label mb-1 small fw-bold text-muted">Correo Electrónico</label>
                                    <input type="email" name="correo" id="vendedor_correo" class="form-control form-control-sm shadow-none" maxlength="100">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label mb-1 small fw-bold text-muted">Teléfono</label>
                                    <input type="text" name="telefono" id="vendedor_telefono" class="form-control form-control-sm shadow-none" maxlength="50">
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="pane-info-vendedor" role="tabpanel">
                            <div class="p-2 border rounded-3 bg-white shadow-sm mb-3">
                                <div class="small fw-bold text-muted mb-2 d-flex align-items-center" style="font-size: 0.7rem;"><i class="bi bi-key-fill text-warning me-2"></i> PERMISOS</div>
                                <div class="d-flex flex-wrap gap-2">
                                    <span class="badge bg-<?= ($permVend['ver'] ?? true) ? 'success' : 'secondary text-opacity-50' ?> bg-opacity-10 text-<?= ($permVend['ver'] ?? true) ? 'success' : 'secondary' ?> border border-<?= ($permVend['ver'] ?? true) ? 'success' : 'secondary' ?> border-opacity-25 px-2" style="font-size: 0.65rem;">VER</span>
                                    <span class="badge bg-<?= ($permVend['crear'] ?? true) ? 'success' : 'secondary text-opacity-50' ?> bg-opacity-10 text-<?= ($permVend['crear'] ?? true) ? 'success' : 'secondary' ?> border border-<?= ($permVend['crear'] ?? true) ? 'success' : 'secondary' ?> border-opacity-25 px-2" style="font-size: 0.65rem;">CREAR</span>
                                    <span class="badge bg-<?= ($permVend['actualizar'] ?? true) ? 'success' : 'secondary text-opacity-50' ?> bg-opacity-10 text-<?= ($permVend['actualizar'] ?? true) ? 'success' : 'secondary' ?> border border-<?= ($permVend['actualizar'] ?? true) ? 'success' : 'secondary' ?> border-opacity-25 px-2" style="font-size: 0.65rem;">MODIFICAR</span>
                                    <span class="badge bg-<?= ($permVend['eliminar'] ?? true) ? 'success' : 'secondary text-opacity-50' ?> bg-opacity-10 text-<?= ($permVend['eliminar'] ?? true) ? 'success' : 'secondary' ?> border border-<?= ($permVend['eliminar'] ?? true) ? 'success' : 'secondary' ?> border-opacity-25 px-2" style="font-size: 0.65rem;">ELIMINAR</span>
                                </div>
                            </div>

                            <div class="bg-light rounded-3 p-3 border mb-3">
                                <h6 class="text-primary mb-3 small fw-bold"><i class="bi bi-people-fill me-2"></i>Resumen</h6>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="small text-muted">Clientes asignados:</span>
                                    <span class="fw-bold text-dark" id="info_clientes_count_v">0</span>
                                </div>
                            </div>
                            <div class="bg-light rounded-3 p-3 border">
                                <h6 class="text-primary mb-3 small fw-bold"><i class="bi bi-clock-history me-2"></i>Historial</h6>
                                <div id="auditoriaTimelineV" class="position-relative mt-2" style="max-height: 200px; overflow-y: auto;">
                                    <div class="text-center py-3 text-muted small">Cargando...</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer justify-content-between bg-light border-top p-2">
                    <div>
                        <button type="button" id="btnEliminarVendedorActual" class="btn btn-outline-danger btn-sm px-3 d-none" onclick="eliminarVendedor()">
                            <i class="bi bi-trash3 me-1"></i> Eliminar
                        </button>
                    </div>
                    <div>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                            <i class="fa-solid fa-xmark me-1"></i>Cerrar
                        </button>
                        <button type="submit" class="btn btn-primary btn-sm px-4 shadow-sm" id="btnGuardarVendedorActual">
                            <i class="bi bi-check2-circle me-1"></i> Guardar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>/js/modulos/vendedores_modal.js?v=<?= time() ?>"></script>
