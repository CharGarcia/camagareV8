<?php
/** @var array $perm */
$anioActual = (int) date('Y');
?>
<div class="modal fade" id="modalCalcularDt" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" style="z-index:1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <form id="formCalcularDt" novalidate onsubmit="return false;">
                <div class="modal-header bg-light py-3">
                    <h5 class="modal-title fw-bold"><i class="bi bi-calculator me-2 text-primary"></i>Calcular Décimo Tercero</h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label mb-1 small fw-bold text-muted">Año de declaración *</label>
                            <input type="number" class="form-control form-control-sm shadow-none" name="anio" id="dt_calc_anio" value="<?= $anioActual ?>" min="2000" max="2100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label mb-1 small fw-bold text-muted">Base de cálculo *</label>
                            <select class="form-select form-select-sm shadow-none" name="base_calculo" id="dt_calc_base">
                                <option value="solo_iess">Solo lo que aporta IESS</option>
                                <option value="todos">Todos los ingresos</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="alert alert-info small mb-0">
                                Período: 1-diciembre del año anterior a 30-noviembre de <?= $anioActual ?>. Fecha límite
                                de pago: 24 de diciembre. Se suma lo percibido en ese período (según la base elegida)
                                de todos los empleados activos y se divide para 12. Si ya existe una declaración de
                                ese año en borrador o calculada, se vuelve a calcular sobre ella.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top p-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4 shadow-sm" id="btnCalcularDt"><i class="bi bi-check2-circle me-1"></i> Calcular</button>
                </div>
            </form>
        </div>
    </div>
</div>
