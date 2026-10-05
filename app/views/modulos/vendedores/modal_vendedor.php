<?php
/** @var array $perm */
/** @var string $rutaModulo */
/** @var array $vistaConfig */

// 1. Forzar vistaConfig de vendedores para ocultar pestañas correctamente
$vistaConfigVend = \App\Helpers\PreferenciasHelper::getPreferenciasVista('vendedores');
echo \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfigVend, 'estiloVistaPestanasVend');

// 2. Forzar permisos de vendedores
// Usar el helper canónico (y no reimplementar la resolución a mano): antes esto
// llamaba a getIdSubmoduloPorRutaMvc() (SINGULAR), que solo mira la PRIMERA fila
// de submodulos_menu con esa ruta. Si "Vendedores" cuelga de más de un menú y el
// permiso del usuario quedó asignado en la fila que no es la primera, esto se
// ocultaba aunque el usuario sí tuviera el permiso marcado en
// /config/permisos-modulos. Permisos::porRuta() ya prueba TODAS las filas con
// esa ruta antes de darlo por sin permiso (ver PermisoSubmodulo::getIdsSubmoduloPorRutaMvc()).
$permVend = $perm ?? [];
if (($rutaModulo ?? '') !== 'modulos/vendedores') {
    $permVend = \App\Helpers\Permisos::porRuta('modulos/vendedores');
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
                        </ul>
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
                                <div class="col-md-12 d-none" id="vendedor_usuario_wrap">
                                    <label class="form-label mb-1 small fw-bold text-muted">Usuario del sistema</label>
                                    <select name="id_usuario_vinculado" id="vendedor_id_usuario_vinculado" class="form-select form-select-sm shadow-none">
                                        <option value="">— Sin vincular —</option>
                                    </select>
                                    <div class="form-text text-muted mt-1" style="font-size:.68rem;">
                                        Cuenta con la que este asesor entra al sistema. Sirve para que en el
                                        <strong>Reporte de Ventas por Vendedor</strong> vea solo sus ventas. Si se deja
                                        sin vincular, se resuelve por la cédula.
                                    </div>
                                </div>
                                <?php // Dato de registro al pie del contenido (sin pestaña Información). ?>
                                <div class="col-md-12 text-muted" style="font-size:.72rem;">
                                    <i class="bi bi-people-fill me-1"></i>Clientes asignados: <span class="fw-bold text-dark" id="info_clientes_count_v">0 clientes</span>
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

<script src="<?= BASE_URL ?>/js/modulos/vendedores_modal.js?v=<?= asset_ver('/js/modulos/vendedores_modal.js') ?>"></script>
