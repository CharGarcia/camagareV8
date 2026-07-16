<?php
/** @var array $puntos */
/** @var array $formasPago */
/** @var array $vistaConfig */
?>
<div class="modal fade" id="modalTraspaso" data-bs-backdrop="static" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-arrow-left-right text-primary me-2"></i>
                    <span id="modalTraspasoTitulo">Nuevo Traspaso de Fondos</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0">
                <!-- Barra de Acciones Superior -->
                <div class="px-3 py-2 bg-light border-bottom d-flex gap-1 align-items-center flex-wrap">
                    <button type="button" class="btn btn-outline-danger btn-sm px-2 d-none" id="btnPdfTraspaso" onclick="window.TRP_abrirPdf()" title="Generar PDF del comprobante">
                        <i class="bi bi-file-earmark-pdf fs-6"></i>
                    </button>
                </div>

                <!-- Pestañas -->
                <div class="d-flex align-items-center bg-light px-3 pt-2">
                    <ul class="nav nav-tabs border-bottom-0 flex-grow-1" id="tabsModalTraspaso" role="tablist">
                        <li class="nav-item"><a class="nav-link active py-2 small fw-bold" id="tab-trp-gen-btn" data-bs-toggle="tab" href="#trp-tab-general" role="tab"><i class="bi bi-card-text me-1"></i> General</a></li>
                        <li class="nav-item"><a class="nav-link py-2 small fw-bold" id="tab-trp-cnt-btn" data-bs-toggle="tab" href="#trp-tab-contable" role="tab"><i class="bi bi-calculator me-1"></i> Asiento contable</a></li>
                    </ul>
                    <div class="ms-2">
                        <?php
                        $pestanasConfig = ['trp-tab-contable' => 'Asiento contable'];
                        echo \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasConfig, $vistaConfig ?? [], 'traspasos');
                        ?>
                    </div>
                </div>
                <div class="border-bottom bg-light mb-0"></div>

                <form id="formTraspasoModal">
                    <input type="hidden" name="id" id="trp-input-id" value="">

                    <div class="tab-content border-top">
                        <!-- PESTAÑA GENERAL -->
                        <div class="tab-pane fade show active p-3" id="trp-tab-general" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Fecha de Emisión</label>
                                    <input type="date" name="fecha_emision" id="trp-input-fecha" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Serie</label>
                                    <select name="id_punto_emision" id="trp-select-punto" class="form-select form-select-sm" onchange="window.TRP_syncSecuencial(this.value)" required>
                                        <?php foreach ($puntos as $p): ?>
                                            <option value="<?= $p['id'] ?>"
                                                    data-est="<?= $p['id_establecimiento'] ?>"
                                                    data-cod-est="<?= htmlspecialchars($p['cod_establecimiento']) ?>"
                                                    data-cod-punto="<?= htmlspecialchars($p['codigo_punto']) ?>">
                                                <?= $p['cod_establecimiento'] ?>-<?= $p['codigo_punto'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="id_establecimiento" id="trp-id-establecimiento">
                                    <input type="hidden" name="establecimiento" id="trp-txt-establecimiento">
                                    <input type="hidden" name="punto_emision" id="trp-txt-punto">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Secuencial</label>
                                    <input type="text" name="secuencial" id="trp-input-secuencial" class="form-control form-control-sm bg-light" readonly>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Estado</label>
                                    <div id="trp-badge-estado"><span class="badge bg-primary bg-opacity-10 text-primary border border-primary">Registrado</span></div>
                                </div>
                            </div>

                            <hr>

                            <div class="row g-3 align-items-end">
                                <div class="col-md-5">
                                    <label class="form-label small fw-bold text-danger"><i class="bi bi-dash-circle me-1"></i>Forma de Origen (Egreso)</label>
                                    <select name="id_forma_origen" id="trp-select-origen" class="form-select form-select-sm" onchange="window.TRP_onCambioFormas()" required>
                                        <option value="">Seleccione...</option>
                                        <?php foreach ($formasPago as $fp): ?>
                                            <option value="<?= $fp['id'] ?>" data-saldo="<?= (float) ($fp['saldo'] ?? 0) ?>"><?= htmlspecialchars($fp['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div id="trp-saldo-origen" class="trp-saldo-hint text-muted mt-1">&nbsp;</div>
                                </div>
                                <div class="col-md-2 text-center">
                                    <i class="bi bi-arrow-right fs-3 text-primary"></i>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small fw-bold text-success"><i class="bi bi-plus-circle me-1"></i>Forma de Destino (Ingreso)</label>
                                    <select name="id_forma_destino" id="trp-select-destino" class="form-select form-select-sm" onchange="window.TRP_onCambioFormas()" required>
                                        <option value="">Seleccione...</option>
                                        <?php foreach ($formasPago as $fp): ?>
                                            <option value="<?= $fp['id'] ?>" data-saldo="<?= (float) ($fp['saldo'] ?? 0) ?>"><?= htmlspecialchars($fp['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div id="trp-saldo-destino" class="trp-saldo-hint text-muted mt-1">&nbsp;</div>
                                </div>
                            </div>

                            <div class="row g-3 mt-1">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Monto a Traspasar</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" min="0.01" name="monto" id="trp-input-monto" class="form-control text-end fw-bold" placeholder="0.00" required>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label small fw-bold">Observaciones</label>
                                    <input type="text" name="observaciones" id="trp-input-obs" class="form-control form-control-sm" placeholder="Motivo del traspaso (opcional)">
                                </div>
                            </div>
                        </div>

                        <!-- PESTAÑA ASIENTO CONTABLE -->
                        <div class="tab-pane fade p-3" id="trp-tab-contable" role="tabpanel">
                            <div id="trp-asiento-contenido">
                                <p class="text-muted small mb-0">El asiento contable se genera automáticamente al guardar el traspaso.</p>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light py-2 d-flex justify-content-between">
                <button type="button" id="trp-btn-anular" class="btn btn-outline-danger btn-sm d-none" onclick="window.TRP_anular()">
                    <i class="bi bi-x-circle me-1"></i> Anular Traspaso
                </button>
                <div class="ms-auto d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" id="trp-btn-guardar" class="btn btn-primary btn-sm px-4" onclick="window.TRP_guardar()">
                        <i class="bi bi-check-lg me-1"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
