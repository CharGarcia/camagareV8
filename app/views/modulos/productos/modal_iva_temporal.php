<?php
/**
 * Modal "Cambio temporal de IVA" (prestadores de servicios turísticos — IVA 8% en feriados).
 * Lo incluye productos/index.php solo si la empresa está marcada como prestador de servicios turísticos
 * y el usuario la administra. Lógica: public/js/modulos/productos_iva_temporal.js.
 */
?>
<div class="modal fade" id="modalIvaTemporal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-2">
                <h5 class="modal-title fw-bold"><i class="bi bi-percent text-primary me-2"></i>Cambio temporal de IVA</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning bg-warning bg-opacity-10 border-warning border-opacity-25 py-2 px-3 small mb-3">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    La tarifa del 8% solo aplica a <strong>servicios turísticos</strong> (art. 5 Ley de Turismo) de establecimientos con
                    Registro de Turismo y LUAF, en las fechas del decreto ejecutivo. <strong>La empresa es responsable</strong> de las
                    fechas, la tarifa y los productos que elige. Al terminar la fecha hasta, cada producto vuelve solo a la tarifa que tenía.
                </div>

                <form id="formIvaTemporal" class="row g-2" autocomplete="off" onsubmit="return false;">
                    <div class="col-md-4">
                        <label for="ivaTmpActual" class="form-label small fw-bold mb-1">Tarifa actual</label>
                        <select class="form-select form-select-sm" id="ivaTmpActual" name="id_tarifa_actual"></select>
                    </div>
                    <div class="col-md-4">
                        <label for="ivaTmpNueva" class="form-label small fw-bold mb-1">Tarifa nueva</label>
                        <select class="form-select form-select-sm" id="ivaTmpNueva" name="id_tarifa_nueva"></select>
                    </div>
                    <div class="col-md-4">
                        <label for="ivaTmpAplica" class="form-label small fw-bold mb-1">Aplicar a</label>
                        <select class="form-select form-select-sm" id="ivaTmpAplica" name="aplica_a">
                            <option value="todos">Todos (bienes y servicios)</option>
                            <option value="servicios">Solo servicios</option>
                            <option value="bienes">Solo bienes</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="ivaTmpDesde" class="form-label small fw-bold mb-1">Desde</label>
                        <input type="date" class="form-control form-control-sm" id="ivaTmpDesde" name="fecha_desde">
                    </div>
                    <div class="col-md-3">
                        <label for="ivaTmpHasta" class="form-label small fw-bold mb-1">Hasta (inclusive)</label>
                        <input type="date" class="form-control form-control-sm" id="ivaTmpHasta" name="fecha_hasta">
                    </div>
                    <div class="col-md-6">
                        <label for="ivaTmpObs" class="form-label small fw-bold mb-1">Observaciones <span class="text-muted fw-normal">(opcional)</span></label>
                        <input type="text" class="form-control form-control-sm" id="ivaTmpObs" name="observaciones" maxlength="500" placeholder="Ej.: Decreto Ejecutivo 526 — feriado de noviembre">
                    </div>
                    <div class="col-12 d-flex align-items-center justify-content-between flex-wrap gap-2 mt-2">
                        <span id="ivaTmpPreview" class="small text-muted"></span>
                        <button type="button" class="btn btn-primary btn-sm px-3" id="btnIvaTmpProgramar">
                            <i class="bi bi-calendar-check me-1"></i> Programar cambio
                        </button>
                    </div>
                </form>

                <hr class="my-3">
                <h6 class="fw-bold small text-uppercase text-muted mb-2"><i class="bi bi-clock-history me-1"></i>Cambios programados y anteriores</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Período</th>
                                <th>Cambio</th>
                                <th>Aplica a</th>
                                <th class="text-center">Estado</th>
                                <th class="text-end">Productos</th>
                                <th>Observaciones</th>
                                <th>Programado por</th>
                                <th class="text-end"></th>
                            </tr>
                        </thead>
                        <tbody id="ivaTmpHistorial">
                            <tr><td colspan="8" class="text-center text-muted py-3">Cargando…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light border-top p-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
