<?php
/**
 * Modal de Bodega (crear / editar) — pieza reutilizable.
 *
 * Lo usa el listado de Bodegas y cualquier pantalla que necesite crear una bodega al vuelo
 * (p. ej. Configuración de Producción). Se incluye con include_once, fuera de otros modales,
 * y carga su propio JS (public/js/modulos/bodegas_modal.js, protegido contra doble carga).
 *
 * Al guardar emite `bodegaGuardado` (detail: id, nombre, nuevo, id_establecimiento, status) y al
 * eliminar `bodegaEliminada` (detail: id). Ver CLAUDE.md §9, "Crear al vuelo".
 *
 * Variables que espera (todas opcionales):
 * @var array $establecimientos       Establecimientos de la empresa (id, codigo, nombre).
 * @var array $permBodegas            Permisos del módulo Bodegas (crear, actualizar, eliminar).
 *                                    Si no llega, se resuelven con Permisos::porRuta('modulos/bodegas').
 * @var bool  $bodegaModalFavoritos   true solo en el listado de Bodegas: muestra las estrellas de
 *                                    favorito (guardan en el módulo 'bodegas').
 */
$permBodegas          = $permBodegas ?? \App\Helpers\Permisos::porRuta('modulos/bodegas');
$bodegaModalFavoritos = $bodegaModalFavoritos ?? false;
$establecimientosBod  = $establecimientos ?? [];
$urlBodegasModal      = rtrim(BASE_URL, '/') . '/modulos/bodegas';
$nivelBodegaModal     = (int) ($_SESSION['nivel'] ?? 1);
?>
<style>
    #modalBodega .bodega-saldo-stat { font-size: .7rem; }
    #modalBodega .bodega-saldo-stat .valor { font-size: .95rem; font-weight: 700; }
</style>

<!-- Modal Bodega -->
<div class="modal fade" id="modalBodega" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-url-base="<?= htmlspecialchars($urlBodegasModal) ?>">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <form id="formBodega" novalidate>
                <div class="modal-header bg-light">
                    <h5 class="modal-title fs-6 fw-bold">
                        <i class="bi bi-box-seam me-2 text-primary"></i> <span id="tituloModal">Nueva Bodega</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-0">
                    <input type="hidden" name="id" id="bodega_id">

                    <div class="d-flex align-items-center bg-light px-3 pt-2">
                        <ul class="nav nav-tabs border-bottom-0 flex-grow-1 tab-pestaña" id="modalBodegaTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <a class="nav-link active py-2 small" id="tab-general-btn" data-bs-toggle="tab" href="#pane-general" role="tab">
                                    <i class="bi bi-card-text me-1"></i> General
                                </a>
                            </li>
                            <?php if ($nivelBodegaModal >= 2): ?>
                                <li class="nav-item" role="presentation">
                                    <a class="nav-link py-2 small" id="tab-accesos-btn" data-bs-toggle="tab" href="#pane-accesos" role="tab">
                                        <i class="bi bi-person-lock me-1"></i> Accesos
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div class="border-bottom mx-3 mb-3"></div>

                    <div class="tab-content px-3 pb-3">
                        <div class="tab-pane fade show active" id="pane-general" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label small fw-bold">Nombre de la bodega *</label>
                                    <input type="text" name="nombre" id="bodega_nombre" class="form-control form-control-sm" required maxlength="100" autocomplete="off">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold d-flex align-items-center">Estado <?= $bodegaModalFavoritos ? \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('bodegas', 'bodega_status', 'status') : '' ?></label>
                                    <!-- El valor real (1/0) viaja en el oculto: lo leen la estrella de
                                         favorito y el guardado. El interruptor solo lo refleja. -->
                                    <input type="hidden" name="status" id="bodega_status" value="1">
                                    <div class="form-check form-switch mb-0 d-flex align-items-center" style="min-height:31px">
                                        <input class="form-check-input" type="checkbox" id="bodega_status_switch" checked>
                                        <label class="form-check-label small fw-semibold ms-2" for="bodega_status_switch">
                                            <i class="bi bi-check-circle text-success me-1"></i>Activo
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold d-flex align-items-center">
                                        Establecimiento <?= $bodegaModalFavoritos ? \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('bodegas', 'bodega_id_establecimiento', 'id_establecimiento') : '' ?>
                                    </label>
                                    <select name="id_establecimiento" id="bodega_id_establecimiento" class="form-select form-select-sm">
                                        <option value="">Sin asignar</option>
                                        <?php foreach ($establecimientosBod as $est): ?>
                                            <option value="<?= (int) $est['id'] ?>">
                                                <?= htmlspecialchars(($est['codigo'] ?? '') . ' - ' . ($est['nombre'] ?? '')) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text small">Local donde está físicamente la bodega. Define cuándo una transferencia de inventario cruza de un establecimiento a otro.</div>
                                </div>

                                <!-- Saldo de la bodega (solo al editar) -->
                                <div class="col-12 d-none" id="bodegaSaldoBox">
                                    <div class="border rounded-3 bg-light p-2">
                                        <div class="small fw-bold text-muted mb-2"><i class="bi bi-boxes me-1"></i>Saldo actual de la bodega</div>
                                        <div class="row g-2 text-center">
                                            <div class="col-6 col-md-3 bodega-saldo-stat">
                                                <div class="valor" id="saldo_productos">0</div>
                                                <div class="text-muted">Productos</div>
                                            </div>
                                            <div class="col-6 col-md-3 bodega-saldo-stat">
                                                <div class="valor" id="saldo_unidades">0.00</div>
                                                <div class="text-muted">Unidades</div>
                                            </div>
                                            <div class="col-6 col-md-3 bodega-saldo-stat">
                                                <div class="valor text-primary" id="saldo_costo">0.00</div>
                                                <div class="text-muted">Al costo</div>
                                            </div>
                                            <div class="col-6 col-md-3 bodega-saldo-stat">
                                                <div class="valor text-success" id="saldo_venta">0.00</div>
                                                <div class="text-muted">A precio de venta</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if ($nivelBodegaModal >= 2): ?>
                            <div class="tab-pane fade" id="pane-accesos" role="tabpanel">
                                <div class="alert alert-info py-2 small border-0 shadow-sm mb-3">
                                    <i class="bi bi-info-circle-fill me-2"></i> Configure qué usuarios tienen acceso a esta bodega y establezca cuál es su bodega predeterminada.
                                </div>
                                <div class="table-responsive" style="max-height: 350px;">
                                    <table class="table table-sm table-hover border">
                                        <thead class="table-light sticky-top">
                                            <tr style="font-size: 0.75rem;">
                                                <th class="text-center" style="width: 80px;">Acceso</th>
                                                <th class="text-center" style="width: 80px;">Default</th>
                                                <th>Usuario</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbodyAccesosUsuarios">
                                            <tr>
                                                <td colspan="3" class="text-center py-4 text-muted">
                                                    <div class="spinner-border spinner-border-sm mb-2"></div>
                                                    <div class="small">Cargando usuarios...</div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer justify-content-between bg-light border-top p-2">
                    <div>
                        <?php if (!empty($permBodegas['eliminar'])): ?>
                            <button type="button" id="btnEliminar" class="btn btn-outline-danger btn-sm px-3 d-none" onclick="eliminarBodega()">
                                <i class="bi bi-trash3 me-1"></i> Eliminar
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                            <i class="fa-solid fa-xmark me-1"></i>Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary btn-sm px-4" id="btnGuardar">
                            <i class="bi bi-check2-circle me-1"></i> Guardar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="<?= rtrim(BASE_URL, '/') ?>/js/modulos/bodegas_modal.js?v=<?= asset_ver('/js/modulos/bodegas_modal.js') ?>"></script>
