<!-- Modal Asiento Contable -->
<div class="modal fade" id="modalAsientoContable" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width: 1400px; width: 95%;">
        <div class="modal-content border-0 shadow-lg">
            <form id="formAsientoContable" onsubmit="event.preventDefault(); window.ASIENTO_guardar();">
                <div class="modal-header bg-light border-bottom px-4 py-3">
                    <h5 class="modal-title fw-bold mb-0">
                        <i class="bi bi-journal-check me-2 text-primary"></i> <span id="asientoModalTitle">Asiento Contable</span>
                    </h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4 py-3">
                    <input type="hidden" id="asiento_id" name="id">
                    <input type="hidden" id="asiento_modulo_origen" name="modulo_origen" value="manual">
                    <input type="hidden" id="asiento_id_referencia_origen" name="id_referencia_origen" value="">

                    <!-- Cabecera -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label class="form-label small fw-medium mb-1">Fecha <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-sm" id="asiento_fecha" name="fecha_asiento" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-medium mb-1">Tipo Comprobante</label>
                            <input type="text" class="form-control form-control-sm bg-light text-capitalize" id="asiento_tipo_label" readonly tabindex="-1">
                            <input type="hidden" id="asiento_tipo" name="tipo_comprobante">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-medium mb-1">Nro. Comprobante</label>
                            <input type="text" class="form-control form-control-sm bg-light text-primary fw-bold" id="asiento_numero" name="numero_comprobante" placeholder="(Automático)" readonly tabindex="-1">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-medium mb-1">Estado</label>
                            <input type="text" class="form-control form-control-sm bg-light text-capitalize" id="asiento_estado_label" readonly tabindex="-1">
                            <input type="hidden" id="asiento_estado" name="estado">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-medium mb-1">Concepto / Glosa <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="asiento_concepto" name="concepto" required placeholder="Concepto general del asiento...">
                        </div>
                    </div>

                    <!-- Detalles -->
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold mb-0">Detalles del Asiento</h6>
                    </div>
                    <div class="border rounded-3 bg-white shadow-sm">
                        <div class="table-responsive" style="overflow: visible !important;">
                            <table class="table table-sm table-bordered mb-0 align-middle" id="tablaAsientoDetalles">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th style="min-width: 250px;">Cuenta Contable</th>
                                    <th style="min-width: 150px;">Centro Costo</th>
                                    <th style="min-width: 150px;">Proyecto</th>
                                    <th style="min-width: 150px;">Documento/Ref</th>
                                    <th style="width: 130px;" class="text-end">Debe</th>
                                    <th style="width: 130px;" class="text-end">Haber</th>
                                    <th style="width: 40px;" class="text-center"></th>
                                </tr>
                            </thead>
                            <tbody id="tbodyAsientoDetalles">
                                <!-- Filas dinámicas -->
                            </tbody>
                            <tfoot class="table-light sticky-bottom">
                                <tr>
                                    <td colspan="4" class="text-end fw-bold">TOTALES</td>
                                    <td class="text-end fw-bold text-success fs-6" id="asientoTotalDebe">$0.00</td>
                                    <td class="text-end fw-bold text-danger fs-6" id="asientoTotalHaber">$0.00</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="text-end fw-bold text-muted small">DIFERENCIA</td>
                                    <td colspan="2" class="text-center fw-bold fs-6" id="asientoDiferencia">$0.00</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                        </div>
                        <div class="p-2 border-top bg-light d-flex justify-content-between align-items-center">
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold ms-2" onclick="window.ASIENTO_agregarFila()">
                                <i class="bi bi-plus-circle me-1"></i> Agregar línea
                            </button>
                            <div class="small fw-bold text-muted pe-3">
                                Items: <span id="m-count-items-asiento">0</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top px-4 py-3 d-flex justify-content-between">
                    <div>
                        <button type="button" class="btn btn-outline-danger btn-sm d-none" id="btnAnularAsiento" onclick="window.ASIENTO_anular()"><i class="bi bi-x-circle me-1"></i> Anular</button>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm px-4 shadow-sm" id="btnGuardarAsiento" disabled>
                            <i class="bi bi-check2-circle me-1"></i> Guardar Asiento
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Autocomplete Dropdown Template -->
<template id="asientoCuentaAutocompleteTemplate">
    <div class="position-relative">
        <input type="text" class="form-control form-control-sm cuenta-search" placeholder="Buscar por código o nombre..." autocomplete="off">
        <input type="hidden" class="cuenta-id">
        <div class="list-group position-absolute shadow cuenta-results" style="z-index: 1050; max-height: 250px; min-width: 450px; max-width: 600px; width: max-content; overflow-y: auto; display: none;"></div>
    </div>
</template>
