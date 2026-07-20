<?php
/** @var array $perm */
?>
<div class="modal fade" id="modalDetalleDc" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" style="z-index:1060;">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-cash-coin me-2 text-primary"></i>Décimo Cuarto <span id="dc_det_titulo" class="text-muted fw-normal"></span></h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0">
                <input type="hidden" id="dc_det_id" value="">

                <ul class="nav nav-tabs px-3 pt-2" id="dcDetTabs">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#dcTabResumen" type="button">Resumen</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#dcTabEmpleados" type="button">Empleados</button></li>
                </ul>

                <div class="tab-content p-3">
                    <div class="tab-pane fade show active" id="dcTabResumen">
                        <div class="row g-2 small">
                            <div class="col-md-3"><span class="text-muted d-block">Año</span><b id="dc_r_anio">—</b></div>
                            <div class="col-md-3"><span class="text-muted d-block">Región</span><b id="dc_r_region">—</b></div>
                            <div class="col-md-3"><span class="text-muted d-block">Período</span><b id="dc_r_periodo">—</b></div>
                            <div class="col-md-3"><span class="text-muted d-block">Fecha límite de pago</span><b id="dc_r_limite">—</b></div>
                            <div class="col-md-3"><span class="text-muted d-block">SBU aplicado</span><b id="dc_r_sbu">—</b></div>
                            <div class="col-md-3"><span class="text-muted d-block">Empleados</span><b id="dc_r_empleados">—</b></div>
                            <div class="col-md-3"><span class="text-muted d-block">Total calculado</span><b id="dc_r_total" class="text-success">—</b></div>
                            <div class="col-md-3"><span class="text-muted d-block">Pagado</span><b id="dc_r_pagado" class="text-primary">—</b></div>
                        </div>
                        <div class="alert alert-info small mt-3 mb-0">
                            El pago se hace desde <b>Egresos → Nómina</b>, igual que un anticipo: ahí aparece cada
                            empleado con su valor de décimo cuarto pendiente (los que mensualizan no aparecen,
                            ya se les pagó dentro del rol). Ese egreso debita directamente la cuenta
                            "Décimo Cuarto por Pagar" que ya se viene provisionando mes a mes en el rol, así que
                            no se duplica el gasto.
                        </div>
                    </div>

                    <div class="tab-pane fade" id="dcTabEmpleados">
                        <div class="table-responsive" style="max-height: 50vh;">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light" style="position: sticky; top: 0;">
                                    <tr>
                                        <th>Identificación</th>
                                        <th>Nombres</th>
                                        <th>Apellidos</th>
                                        <th class="text-center">Días</th>
                                        <th class="text-end">Valor</th>
                                        <th class="text-end">Pagado</th>
                                        <th class="text-center">Mensualiza</th>
                                        <th>Tipo Pago</th>
                                        <th class="text-center">Discap.</th>
                                        <th>Jubilación</th>
                                        <th class="text-end">Retención</th>
                                    </tr>
                                </thead>
                                <tbody id="dc_tbody_empleados"></tbody>
                            </table>
                        </div>
                        <div class="small text-muted mt-2">Los cambios en esta tabla se guardan automáticamente al salir del campo.</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between bg-light border-top p-2">
                <div>
                    <?php if (!empty($perm['eliminar'])): ?>
                        <button type="button" class="btn btn-outline-danger btn-sm px-3" id="btnAnularDc" onclick="window.anularDecimoCuarto()"><i class="bi bi-trash3 me-1"></i> Anular</button>
                    <?php endif; ?>
                </div>
                <div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.exportarCsv()"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Exportar CSV</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>Cerrar</button>
                </div>
            </div>
        </div>
    </div>
</div>
