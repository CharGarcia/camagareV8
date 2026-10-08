<?php
/** @var array $perm */
/** @var array $vistaConfig */
$vistaConfigUt = \App\Helpers\PreferenciasHelper::getPreferenciasVista('utilidades');
echo \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfigUt, 'estiloVistaPestanasUt');
// Pestaña «Asiento contable»: solo con acceso a Contabilidad → Asientos Contables (regla general
// de todos los modales con asiento; ver app/helpers/AsientoPestana.php).
$utVerAsiento = \App\Helpers\AsientoPestana::puedeVer();
?>
<!-- Modal Utilidades (detalle de un ejercicio) -->
<div class="modal fade" id="modalDetalleUt" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" style="z-index: 1060;">
    <?php // Sin modal-dialog-scrollable a propósito: igual que Proveedores, si el contenido
          // no cabe scrollea el modal completo, no el cuerpo. ?>
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-pie-chart text-primary me-2"></i>
                    <span id="ut_det_titulo">Utilidades</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0 position-relative">
                <div id="ut-modal-loader" class="d-none position-absolute top-0 start-0 w-100 h-100 d-flex flex-column align-items-center justify-content-center bg-white bg-opacity-75" style="z-index: 1055;">
                    <div class="spinner-border text-primary mb-2" role="status"></div>
                    <div class="small text-muted">Cargando...</div>
                </div>
                <input type="hidden" id="ut_det_id" value="">

                <!-- Pestañas -->
                <style>
                    #tabsUtilidades .nav-link { padding: 6px 9px; font-size: 0.8rem; white-space: nowrap; }
                    #tabsUtilidades .nav-link i { font-size: 0.85rem; }
                </style>
                <div class="d-flex align-items-center bg-light px-3 pt-2">
                    <ul class="nav nav-tabs border-bottom-0 flex-grow-1 flex-nowrap tab-pestaña" id="tabsUtilidades" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link active" id="ut-tab-resumen-btn" data-bs-toggle="tab" data-bs-target="#ut-tab-resumen" href="#ut-tab-resumen" role="tab" title="Resumen"><i class="bi bi-card-list me-1"></i> Resumen</a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="ut-tab-trabajadores-btn" data-bs-toggle="tab" data-bs-target="#ut-tab-trabajadores" href="#ut-tab-trabajadores" role="tab" title="Trabajadores"><i class="bi bi-people me-1"></i> Trabajadores</a>
                        </li>
                        <?php if ($utVerAsiento): // solo con acceso a Contabilidad → Asientos Contables ?>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="ut-tab-asiento-btn" data-bs-toggle="tab" data-bs-target="#ut-tab-asiento" href="#ut-tab-asiento" role="tab" title="Asiento contable del 31 de diciembre"><i class="bi bi-calculator me-1"></i> Asiento contable</a>
                        </li>
                        <?php endif; ?>
                    </ul>
                    <div class="pb-1 flex-shrink-0">
                        <?php
                        $pestanasUt = ['ut-tab-trabajadores' => 'Trabajadores'];
                        if ($utVerAsiento) $pestanasUt['ut-tab-asiento'] = 'Asiento contable';
                        echo \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasUt, $vistaConfigUt, 'utilidades', '__pestanas_ocultas__', 'estiloVistaPestanasUt');
                        ?>
                    </div>
                </div>
                <div class="border-bottom bg-light mb-0"></div>

                <div class="tab-content border-top px-3 py-3" id="tabsUtilidadesContent">
                    <!-- Pestaña Resumen -->
                    <div class="tab-pane fade show active" id="ut-tab-resumen" role="tabpanel">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label small fw-bold d-block mb-1">Ejercicio</label>
                                <div class="form-control form-control-sm bg-light" id="ut_r_anio">-</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold d-block mb-1">Fecha límite de pago</label>
                                <div class="form-control form-control-sm bg-light" id="ut_r_limite">-</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold d-block mb-1">Utilidad líquida</label>
                                <div class="form-control form-control-sm bg-light text-end" id="ut_r_utilidad">-</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold d-block mb-1">Monto a repartir</label>
                                <div class="form-control form-control-sm bg-light text-end fw-bold text-primary" id="ut_r_repartir">-</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold d-block mb-1">10% por tiempo</label>
                                <div class="form-control form-control-sm bg-light text-end" id="ut_r_m10">-</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold d-block mb-1">5% por cargas</label>
                                <div class="form-control form-control-sm bg-light text-end" id="ut_r_m5">-</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold d-block mb-1">SBU / tope por trabajador</label>
                                <div class="form-control form-control-sm bg-light text-end" id="ut_r_tope">-</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold d-block mb-1">Estado</label>
                                <div class="form-control form-control-sm bg-light" id="ut_r_estado">-</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold d-block mb-1">Trabajadores</label>
                                <div class="form-control form-control-sm bg-light text-end" id="ut_r_empleados">-</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold d-block mb-1">Total días</label>
                                <div class="form-control form-control-sm bg-light text-end" id="ut_r_dias">-</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold d-block mb-1">Total cargas</label>
                                <div class="form-control form-control-sm bg-light text-end" id="ut_r_cargas">-</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold d-block mb-1">Pagado</label>
                                <div class="form-control form-control-sm bg-light text-end text-primary" id="ut_r_pagado">-</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold d-block mb-1">A pagar a trabajadores</label>
                                <div class="form-control form-control-sm bg-light text-end fw-bold text-success" id="ut_r_total">-</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold d-block mb-1">Excedente sobre 24 SBU (va al IESS)</label>
                                <div class="form-control form-control-sm bg-light text-end fw-bold text-warning" id="ut_r_excedente">-</div>
                            </div>
                            <div class="col-12">
                                <div class="alert alert-warning py-2 px-3 small mb-0 d-none" id="ut_aviso_sin_cargas">
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    Ningún trabajador tiene cargas familiares registradas: el 5% se repartió por días laborados, igual que el 10%.
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="alert alert-primary bg-primary bg-opacity-10 py-2 px-3 small border-primary border-opacity-25 mb-0">
                                    <i class="bi bi-info-circle-fill me-1"></i>
                                    El pago se hace desde <b>Egresos → Nómina</b>: ahí aparece cada trabajador con su valor de
                                    utilidades pendiente. Ese egreso debita la cuenta "Participación Trabajadores por Pagar",
                                    que el botón <b>Contabilizar</b> acredita en el asiento del 31 de diciembre del ejercicio
                                    (gasto Participación Trabajadores contra ese pasivo, por el monto repartido completo).
                                    El excedente sobre 24 SBU no se paga al trabajador: se deposita al IESS con un egreso aparte.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pestaña Trabajadores -->
                    <div class="tab-pane fade" id="ut-tab-trabajadores" role="tabpanel">
                        <div class="table-responsive" style="max-height: 55vh;">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
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

                    <!-- Pestaña Asiento contable (el del 31 de diciembre que genera Contabilizar) -->
                    <?php if ($utVerAsiento): ?>
                    <div class="tab-pane fade" id="ut-tab-asiento" role="tabpanel">
                        <div class="alert alert-light border small d-flex align-items-center gap-2 mb-2 py-2">
                            <i class="bi bi-info-circle text-primary"></i>
                            <span>Asiento del <strong>31 de diciembre</strong> del ejercicio: <em>Gasto Participación Trabajadores</em> contra <em>Participación Trabajadores por Pagar</em>, por el monto repartido completo. Se genera con el botón <strong>Contabilizar</strong>.</span>
                        </div>
                        <?php $prefijo = 'ut'; require MVC_APP . '/views/partials/asiento_tab.php'; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer justify-content-between bg-light border-top p-2">
                <div>
                    <?php if (!empty($perm['eliminar'])): ?>
                        <button type="button" class="btn btn-outline-danger btn-sm px-3" id="btnAnularUt" onclick="window.anularUtilidades()">
                            <i class="bi bi-trash3 me-1"></i> Eliminar
                        </button>
                    <?php endif; ?>
                </div>
                <div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.exportarCsv()" title="Archivo para el Ministerio del Trabajo"><i class="bi bi-file-earmark-spreadsheet me-1"></i> CSV Ministerio</button>
                    <?php if (!empty($perm['actualizar'])): ?>
                        <button type="button" class="btn btn-outline-success btn-sm" id="btnContabilizarUt" onclick="window.contabilizarUtilidades()"><i class="bi bi-journal-check me-1"></i> Contabilizar</button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>Cerrar</button>
                </div>
            </div>
        </div>
    </div>
</div>
