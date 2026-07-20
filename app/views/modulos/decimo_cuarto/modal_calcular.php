<?php
/** @var array $perm */
$anioActual = (int) date('Y');
?>
<div class="modal fade" id="modalCalcularDc" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" style="z-index:1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <form id="formCalcularDc" novalidate onsubmit="return false;">
                <div class="modal-header bg-light py-3">
                    <h5 class="modal-title fw-bold"><i class="bi bi-calculator me-2 text-primary"></i>Calcular Décimo Cuarto</h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label mb-1 small fw-bold text-muted">Año de declaración *</label>
                            <input type="number" class="form-control form-control-sm shadow-none" name="anio" id="dc_calc_anio" value="<?= $anioActual ?>" min="2000" max="2100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label mb-1 small fw-bold text-muted">Región *</label>
                            <select class="form-select form-select-sm shadow-none" name="region_grupo" id="dc_calc_region">
                                <option value="costa_insular">Costa / Insular (límite 15-marzo)</option>
                                <option value="sierra_amazonia">Sierra / Amazonía (límite 15-agosto)</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="alert alert-info small mb-0">
                                Se calculará el valor proporcional (SBU / 360 días) de todos los empleados activos
                                de la región seleccionada. Si ya existe una declaración de ese año/región en
                                borrador o calculada, se vuelve a calcular sobre ella.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top p-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4 shadow-sm" id="btnCalcularDc"><i class="bi bi-check2-circle me-1"></i> Calcular</button>
                </div>
            </form>
        </div>
    </div>
</div>
