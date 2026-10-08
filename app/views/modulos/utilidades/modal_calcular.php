<?php
/** @var array $perm */
$anioDefecto = (int) date('Y') - 1; // las utilidades se liquidan el año siguiente al ejercicio
?>
<!-- Modal Nuevo (cálculo de un ejercicio) -->
<div class="modal fade" id="modalCalcularUt" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" style="z-index:1060;">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <form id="formCalcularUt" novalidate onsubmit="return false;">
                <div class="modal-header bg-light py-3">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pie-chart text-primary me-2"></i>Nuevo cálculo de utilidades</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body px-3 py-3">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold d-block">Ejercicio *</label>
                            <input type="number" class="form-control form-control-sm" name="anio" id="ut_calc_anio" value="<?= $anioDefecto ?>" min="2000" max="2100">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold d-block">Utilidad líquida</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" min="0" class="form-control text-end" name="utilidad_liquida" id="ut_calc_utilidad" value="0.00" oninput="window.utProponerMonto()">
                            </div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-bold d-block">Monto a repartir (15%) *</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" min="0" class="form-control text-end" name="monto_repartir" id="ut_calc_monto" value="0.00">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="alert alert-primary bg-primary bg-opacity-10 py-2 px-3 small border-primary border-opacity-25 mb-0">
                                <i class="bi bi-info-circle-fill me-1"></i>
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
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>Cerrar</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4" id="btnCalcularUt"><i class="bi bi-check2-circle me-1"></i> Calcular</button>
                </div>
            </form>
        </div>
    </div>
</div>
