<?php
/** @var array $perm */
/** @var string $rutaModulo */
/** @var array $vistaConfig */

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
                <div class="modal-body">
                    <div id="modalAlertMar" class="alert d-none mb-3 py-2 small shadow-sm border-0"></div>
                    <input type="hidden" name="id" id="marca_id_modal" value="">

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