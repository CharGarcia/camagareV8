<?php

/** @var array $perm */
/** @var string $rutaModulo */
/** @var array $vistaConfig */
?>

<!-- Modal Retención en Ventas -->
<div class="modal fade modal-retv" id="modalRetencionVenta" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title fs-6 fw-bold" id="modalRetvTitulo">
                    <i class="fa-solid fa-file-invoice-dollar text-success me-2"></i>Nueva Retención Recibida
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <form id="formRetencionVenta">
                    <input type="hidden" name="id" id="retv_id">
                    <input type="hidden" name="id_venta" id="retv_id_venta">
                    <input type="hidden" name="origen" id="retv_origen" value="manual">
                    <input type="hidden" name="establecimiento" id="retv_establecimiento">
                    <input type="hidden" name="punto_emision" id="retv_punto_emision">
                    <input type="hidden" name="secuencial" id="retv_secuencial">

                    <!-- Barra de Acciones -->
                    <div class="px-3 py-2 bg-light border-bottom d-flex gap-1 align-items-center flex-wrap">
                        <?php if ($perm['crear'] ?? false): ?>
                            <button type="button" class="btn btn-outline-primary btn-sm px-2" onclick="window.abrirModalCrearCliente()" title="Registrar nuevo cliente">
                                <i class="fa-solid fa-user-plus"></i>
                            </button>
                        <?php endif; ?>
                        <button type="button" id="retv-btn-descargar-xml"
                                class="btn btn-outline-success btn-sm px-2 d-none"
                                onclick="window.RETV_descargarXml()"
                                title="Descargar XML">
                            <i class="fa-solid fa-file-code me-1"></i> XML
                        </button>
                        <div class="ms-auto" id="retv_estado_badge"></div>
                    </div>

                    <div class="border-top">

                        <!-- Datos del comprobante del cliente -->
                        <div class="px-3 py-2 bg-white border-bottom">
                            <div class="row g-2 align-items-end">
                                <!-- Fecha Emisión -->
                                <div class="col-md-2 col-6">
                                    <label class="x-small fw-bold text-muted mb-1">Fecha Emisión <span class="text-danger">*</span></label>
                                    <input type="date" name="fecha_emision" id="retv_fecha_emision"
                                        class="form-control form-control-sm border-primary border-opacity-10 py-0"
                                        style="height:31px;" required
                                        onchange="window.RETV_actualizarPeriodoFiscal(this.value)">
                                </div>

                                <!-- Número de Retención (estab-pto-secuencial con máscara) -->
                                <div class="col-md-2 col-4">
                                    <label class="x-small fw-bold text-muted mb-1">Número de Retención <span class="text-danger">*</span></label>
                                    <input type="text" id="retv_numero_retencion"
                                        class="form-control form-control-sm border-primary border-opacity-25 text-center font-monospace py-0"
                                        style="height:31px;" placeholder="000-000-000000000" maxlength="17"
                                        oninput="window.RETV_aplicarMascaraNumero(this)"
                                        onblur="window.RETV_normalizarNumero(this)">
                                </div>

                                <!-- Período Fiscal -->
                                <div class="col-md-2 col-5">
                                    <label class="x-small fw-bold text-muted mb-1">Período Fiscal <span class="text-danger">*</span></label>
                                    <input type="text" name="periodo_fiscal" id="retv_periodo_fiscal"
                                        class="form-control form-control-sm border-primary border-opacity-25 text-center py-0"
                                        style="height:31px;" placeholder="MM/YYYY" maxlength="7" readonly>
                                </div>

                                <!-- Cliente -->
                                <div class="col-md-6 position-relative">
                                    <label class="x-small fw-bold text-muted mb-1">Cliente <span class="text-danger">*</span></label>
                                    <div class="input-group input-group-sm rounded-pill overflow-hidden border border-primary border-opacity-25">
                                        <span class="input-group-text bg-white border-0 text-primary"><i class="fa-solid fa-magnifying-glass"></i></span>
                                        <input type="text" class="form-control border-0 px-1" id="retv_cliente_search"
                                            placeholder="Buscar por RUC o Razón Social..." autocomplete="off">
                                        <input type="hidden" name="id_cliente" id="retv_id_cliente">
                                    </div>
                                    <div id="retv_cliente_dropdown"
                                        class="list-group shadow dropdown-predictivo position-absolute d-none"
                                        style="z-index:1050;width:100%;max-height:250px;overflow-y:auto;top:55px;"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Líneas de Retención -->
                        <div class="px-3 py-2">
                            <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                                <div class="table-responsive" style="max-height:350px;">
                                    <table class="table table-sm table-detalle mb-0 text-nowrap">
                                        <thead>
                                            <tr class="table-light border-bottom">
                                                <th class="ps-2 py-2 small fw-bold text-muted" style="width:14%;">Tipo Comprobante</th>
                                                <th class="py-2 small fw-bold text-muted" style="width:14%;">Doc. Sustento</th>
                                                <th class="py-2 small fw-bold text-muted" style="width:10%;">Fecha Doc.</th>
                                                <th class="py-2 small fw-bold text-muted" style="width:8%;">Código</th>
                                                <th class="py-2 small fw-bold text-muted" style="width:20%;">Concepto</th>
                                                <th class="py-2 small fw-bold text-muted" style="width:8%;">Impuesto</th>
                                                <th class="py-2 small fw-bold text-muted text-end" style="width:13%;">Base Imponible</th>
                                                <th class="py-2 small fw-bold text-muted text-center" style="width:7%;">% Ret.</th>
                                                <th class="py-2 small fw-bold text-muted text-end pe-3" style="width:12%;">Valor Ret.</th>
                                                <th style="width:35px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="retv_lineas_body">
                                            <tr id="retv_lineas_empty">
                                                <td colspan="10" class="text-center py-5 text-muted">
                                                    <i class="fa-regular fa-file-lines fs-3 d-block mb-2"></i>Agregue al menos una línea de retención.
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="p-2 border-top bg-light d-flex justify-content-between align-items-center">
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" onclick="window.RETV_agregarLinea()">
                                        <i class="fa-solid fa-plus-circle me-1"></i> Agregar línea
                                    </button>
                                    <div class="small fw-bold text-muted pe-3">
                                        Líneas: <span id="retv_count_items">0</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Totales -->
                        <div class="px-3 py-1 border-top bg-light">
                            <div class="row g-2 align-items-center">
                                <div class="col-md-8">
                                    <div id="retv_access_key_container" class="d-none">
                                        <div class="small text-muted mb-1 fw-bold"><i class="fa-solid fa-key me-1"></i>CLAVE DE ACCESO:</div>
                                        <div id="retv_access_key" class="font-monospace small bg-white border rounded px-2 py-1 text-break"></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="bg-white border rounded p-2 shadow-sm" style="font-size:0.78rem;">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="text-muted">Total Retenido Renta</span>
                                            <span id="retv_lbl_renta" class="fw-bold">$0.00</span>
                                            <input type="hidden" name="total_renta" id="retv_total_renta">
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="text-muted">Total Retenido IVA</span>
                                            <span id="retv_lbl_iva" class="fw-bold">$0.00</span>
                                            <input type="hidden" name="total_iva" id="retv_total_iva">
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="text-muted">Total Retenido ISD</span>
                                            <span id="retv_lbl_isd" class="fw-bold">$0.00</span>
                                            <input type="hidden" name="total_isd" id="retv_total_isd">
                                        </div>
                                        <hr class="my-1 opacity-25">
                                        <div class="d-flex justify-content-between align-items-center bg-light border py-1 px-2 rounded">
                                            <span class="fw-bold" style="font-size:0.82rem;">TOTAL RETENIDO</span>
                                            <span class="fw-bold" style="font-size:1rem;" id="retv_lbl_total">$0.00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div><!-- /contenido -->
                </form>
            </div>

            <!-- Footer del Modal -->
            <div class="modal-footer justify-content-between bg-light border-top p-2">
                <div>
                    <?php if ($perm['eliminar'] ?? false): ?>
                        <button type="button" id="retv-btn-eliminar" class="btn btn-outline-danger btn-sm px-3 d-none" onclick="window.RETV_eliminar()">
                            <i class="fa-solid fa-trash me-1"></i> Eliminar
                        </button>
                    <?php endif; ?>
                </div>
                <div>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                        <i class="fa-solid fa-xmark me-1"></i>Cerrar
                    </button>
                    <?php if (($perm['crear'] ?? false) || ($perm['actualizar'] ?? false)): ?>
                        <button type="button" id="retv-btn-guardar" class="btn btn-primary px-4 btn-sm" onclick="window.RETV_guardar()">
                            <i class="bi bi-check2-circle me-1"></i> Guardar
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>