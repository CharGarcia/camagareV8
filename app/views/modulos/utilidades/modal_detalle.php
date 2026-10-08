<?php
/** @var array $perm */
?>
<div class="modal fade" id="modalDetalleUt" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" style="z-index:1060;">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-pie-chart me-2 text-primary"></i>Utilidades <span id="ut_det_titulo" class="text-muted fw-normal"></span></h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0 position-relative">
                <div id="ut-modal-loader" class="d-none position-absolute top-0 start-0 w-100 h-100 d-flex flex-column align-items-center justify-content-center bg-white bg-opacity-75" style="z-index: 1055;">
                    <div class="spinner-border text-primary mb-2" role="status"></div>
                    <div class="small text-muted">Cargando...</div>
                </div>
                <input type="hidden" id="ut_det_id" value="">

                <ul class="nav nav-tabs px-3 pt-2" id="utDetTabs">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#utTabResumen" type="button">Resumen</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#utTabEmpleados" type="button">Trabajadores</button></li>
                </ul>

                <div class="tab-content p-3">
                    <div class="tab-pane fade show active" id="utTabResumen">
                        <div class="row g-2 small">
                            <div class="col-md-3"><span class="text-muted d-block">Ejercicio</span><b id="ut_r_anio">—</b></div>
                            <div class="col-md-3"><span class="text-muted d-block">Fecha límite de pago</span><b id="ut_r_limite">—</b></div>
                            <div class="col-md-3"><span class="text-muted d-block">Utilidad líquida</span><b id="ut_r_utilidad">—</b></div>
                            <div class="col-md-3"><span class="text-muted d-block">Monto a repartir</span><b id="ut_r_repartir" class="text-primary">—</b></div>
                            <div class="col-md-3"><span class="text-muted d-block">10% por tiempo</span><b id="ut_r_m10">—</b></div>
                            <div class="col-md-3"><span class="text-muted d-block">5% por cargas</span><b id="ut_r_m5">—</b></div>
                            <div class="col-md-3"><span class="text-muted d-block">SBU / tope por trabajador</span><b id="ut_r_tope">—</b></div>
                            <div class="col-md-3"><span class="text-muted d-block">Trabajadores</span><b id="ut_r_empleados">—</b></div>
                            <div class="col-md-3"><span class="text-muted d-block">Total días</span><b id="ut_r_dias">—</b></div>
                            <div class="col-md-3"><span class="text-muted d-block">Total cargas</span><b id="ut_r_cargas">—</b></div>
                            <div class="col-md-3"><span class="text-muted d-block">A pagar a trabajadores</span><b id="ut_r_total" class="text-success">—</b></div>
                            <div class="col-md-3"><span class="text-muted d-block">Excedente (va al IESS)</span><b id="ut_r_excedente" class="text-warning">—</b></div>
                            <div class="col-md-3"><span class="text-muted d-block">Pagado</span><b id="ut_r_pagado" class="text-primary">—</b></div>
                            <div class="col-md-3"><span class="text-muted d-block">Estado</span><b id="ut_r_estado">—</b></div>
                        </div>
                        <div class="alert alert-warning small mt-3 mb-0 d-none" id="ut_aviso_sin_cargas">
                            Ningún trabajador tiene cargas familiares registradas: el 5% se repartió por días laborados, igual que el 10%.
                        </div>
                        <div class="alert alert-info small mt-3 mb-0">
                            El pago se hace desde <b>Egresos → Nómina</b>: ahí aparece cada trabajador con su valor de
                            utilidades pendiente. Ese egreso debita la cuenta "Participación Trabajadores por Pagar",
                            que el botón <b>Contabilizar</b> acredita en el asiento del 31 de diciembre del ejercicio
                            (gasto Participación Trabajadores contra ese pasivo, por el monto repartido completo).
                            El excedente sobre 24 SBU no se paga al trabajador: se deposita al IESS con un egreso aparte.
                        </div>
                    </div>

                    <div class="tab-pane fade" id="utTabEmpleados">
                        <div class="table-responsive" style="max-height: 50vh;">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light" style="position: sticky; top: 0;">
                                    <tr>
                                        <th>Identificación</th>
                                        <th>Nombres</th>
                                        <th>Apellidos</th>
                                        <th class="text-center">Días</th>
                                        <th class="text-center">Cargas</th>
                                        <th class="text-end">10%</th>
                                        <th class="text-end">5%</th>
                                        <th class="text-end">Excedente</th>
                                        <th class="text-end">A pagar</th>
                                        <th class="text-end">Pagado</th>
                                        <th>Tipo Pago</th>
                                        <th class="text-center">Discap.</th>
                                        <th class="text-end">Ret. judicial</th>
                                    </tr>
                                </thead>
                                <tbody id="ut_tbody_empleados"></tbody>
                            </table>
                        </div>
                        <div class="small text-muted mt-2">
                            Los cambios se guardan automáticamente al salir del campo. Cambiar las cargas de un trabajador
                            vuelve a repartir el 5% entre todos. Los ex trabajadores del año aparecen en gris.
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between bg-light border-top p-2">
                <div>
                    <?php if (!empty($perm['eliminar'])): ?>
                        <button type="button" class="btn btn-outline-danger btn-sm px-3" id="btnAnularUt" onclick="window.anularUtilidades()"><i class="bi bi-trash3 me-1"></i> Anular</button>
                    <?php endif; ?>
                </div>
                <div>
                    <?php if (!empty($perm['actualizar'])): ?>
                        <button type="button" class="btn btn-outline-success btn-sm" id="btnContabilizarUt" onclick="window.contabilizarUtilidades()"><i class="bi bi-journal-check me-1"></i> Contabilizar</button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.exportarCsv()"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Exportar CSV</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>Cerrar</button>
                </div>
            </div>
        </div>
    </div>
</div>
