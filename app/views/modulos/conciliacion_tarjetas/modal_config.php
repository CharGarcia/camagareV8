<?php
/**
 * Configuración contable del módulo: cuentas de comisión, IVA y retenciones por
 * procesadora, más los valores por defecto (días de liquidación, tolerancia).
 *
 * Los perfiles de lectura del estado de cuenta NO se configuran aquí: son un
 * catálogo global en /config/conciliacion-tarjetas-perfiles (solo nivel 3).
 *
 * La cuenta puente NO se configura aquí: es la cuenta contable de la propia
 * forma de cobro (Formas de Cobro/Pago), que es la que usa el asiento del cobro.
 */
?>
<div class="modal fade" id="modalConfigTarjetas" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-gear me-2"></i>Configuración de Conciliación de Tarjetas</h5>
                <button type="button" class="btn-close" aria-label="Cerrar" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-0">
                <div class="p-3">
                    <!-- ── Contabilidad ── -->
                    <div id="ctar-tab-contabilidad">
                        <div class="alert alert-info py-2 px-3 small">
                            <i class="bi bi-info-circle me-1"></i>
                            La contabilidad es opcional: si no configura cuentas, el módulo concilia igual
                            pero no genera el asiento del depósito.
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted mb-1">Procesadora</label>
                            <select id="ctar-cfg-procesadora" class="form-select form-select-sm shadow-none border"
                                    onchange="CTAR_cargarConfig()"></select>
                        </div>

                        <!-- Estado de la cuenta puente de esta procesadora -->
                        <div class="p-2 border rounded-3 mb-3" id="ctar-cfg-puente">
                            <div class="small fw-bold text-muted text-uppercase mb-1" style="font-size:.65rem;">Cuenta puente (viene de Formas de Cobro/Pago)</div>
                            <div id="ctar-cfg-puente-texto" class="small">—</div>
                        </div>

                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted mb-1">Cuenta de comisión (gasto)</label>
                                <div class="position-relative">
                                    <input type="text" class="form-control form-control-sm shadow-none border ctar-cuenta-input"
                                           id="ctar-cfg-comision-txt" data-target="ctar-cfg-comision" placeholder="Buscar cuenta..." autocomplete="off">
                                    <input type="hidden" id="ctar-cfg-comision">
                                    <div class="list-group shadow position-absolute d-none w-100 ctar-cuenta-drop"
                                         style="z-index:5090;max-height:180px;overflow-y:auto;"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted mb-1">Cuenta de IVA de la comisión</label>
                                <div class="position-relative">
                                    <input type="text" class="form-control form-control-sm shadow-none border ctar-cuenta-input"
                                           id="ctar-cfg-iva-txt" data-target="ctar-cfg-iva" placeholder="Buscar cuenta..." autocomplete="off">
                                    <input type="hidden" id="ctar-cfg-iva">
                                    <div class="list-group shadow position-absolute d-none w-100 ctar-cuenta-drop"
                                         style="z-index:5090;max-height:180px;overflow-y:auto;"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted mb-1">Cuenta de retención de renta</label>
                                <div class="position-relative">
                                    <input type="text" class="form-control form-control-sm shadow-none border ctar-cuenta-input"
                                           id="ctar-cfg-retir-txt" data-target="ctar-cfg-retir" placeholder="Buscar cuenta..." autocomplete="off">
                                    <input type="hidden" id="ctar-cfg-retir">
                                    <div class="list-group shadow position-absolute d-none w-100 ctar-cuenta-drop"
                                         style="z-index:5090;max-height:180px;overflow-y:auto;"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted mb-1">Cuenta de retención de IVA</label>
                                <div class="position-relative">
                                    <input type="text" class="form-control form-control-sm shadow-none border ctar-cuenta-input"
                                           id="ctar-cfg-retiva-txt" data-target="ctar-cfg-retiva" placeholder="Buscar cuenta..." autocomplete="off">
                                    <input type="hidden" id="ctar-cfg-retiva">
                                    <div class="list-group shadow position-absolute d-none w-100 ctar-cuenta-drop"
                                         style="z-index:5090;max-height:180px;overflow-y:auto;"></div>
                                </div>
                            </div>

                            <div class="col-6 col-md-3">
                                <label class="form-label small fw-bold text-muted mb-1">% comisión</label>
                                <input type="number" step="0.0001" id="ctar-cfg-pc" class="form-control form-control-sm shadow-none border text-end">
                                <div class="form-text" style="font-size:.68rem;">Solo para precalcular; siempre editable.</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small fw-bold text-muted mb-1">% IVA</label>
                                <input type="number" step="0.0001" id="ctar-cfg-pi" class="form-control form-control-sm shadow-none border text-end">
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small fw-bold text-muted mb-1">Días de liquidación</label>
                                <input type="number" id="ctar-cfg-dias" class="form-control form-control-sm shadow-none border text-end" value="2">
                                <div class="form-text" style="font-size:.68rem;">Pasados estos días, el cobro se marca atrasado.</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small fw-bold text-muted mb-1">Tolerancia</label>
                                <input type="number" step="0.01" id="ctar-cfg-tol" class="form-control form-control-sm shadow-none border text-end" value="0.05">
                                <div class="form-text" style="font-size:.68rem;">Descuadre aceptado al cerrar.</div>
                            </div>
                        </div>

                        <div class="text-end mt-3">
                            <button type="button" class="btn btn-primary btn-sm" onclick="CTAR_guardarConfig()">
                                <i class="bi bi-save me-1"></i>Guardar configuración
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-light border-top p-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
