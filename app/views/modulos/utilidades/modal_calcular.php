<?php
/** @var array $perm */
$anioDefecto = (int) date('Y') - 1; // las utilidades se liquidan el año siguiente al ejercicio
?>
<div class="modal fade" id="modalCalcularUt" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" style="z-index:1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <form id="formCalcularUt" novalidate onsubmit="return false;">
                <div class="modal-header bg-light py-3">
                    <h5 class="modal-title fw-bold"><i class="bi bi-calculator me-2 text-primary"></i>Calcular Utilidades</h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label mb-1 small fw-bold text-muted">Ejercicio fiscal *</label>
                            <input type="number" class="form-control form-control-sm shadow-none" name="anio" id="ut_calc_anio" value="<?= $anioDefecto ?>" min="2000" max="2100">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-1 small fw-bold text-muted">Utilidad líquida</label>
                            <input type="number" step="0.01" min="0" class="form-control form-control-sm shadow-none text-end" name="utilidad_liquida" id="ut_calc_utilidad" value="0.00" oninput="window.utProponerMonto()">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-1 small fw-bold text-muted">Monto a repartir (15%) *</label>
                            <input type="number" step="0.01" min="0" class="form-control form-control-sm shadow-none text-end" name="monto_repartir" id="ut_calc_monto" value="0.00">
                        </div>
                        <div class="col-12">
                            <div class="alert alert-info small mb-0">
                                La <b>utilidad líquida</b> es la utilidad contable del ejercicio antes de la participación
                                de trabajadores y del impuesto a la renta; el sistema propone el 15% como monto a repartir
                                y usted puede ajustarlo. Se reparte entre todos los trabajadores y ex trabajadores del
                                año: 10% por días laborados y 5% por cargas familiares (de la ficha del empleado),
                                con tope de 24 SBU por persona. Si ya existe un cálculo de ese ejercicio sin pagos,
                                se vuelve a calcular sobre él.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top p-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4 shadow-sm" id="btnCalcularUt"><i class="bi bi-check2-circle me-1"></i> Calcular</button>
                </div>
            </form>
        </div>
    </div>
</div>
