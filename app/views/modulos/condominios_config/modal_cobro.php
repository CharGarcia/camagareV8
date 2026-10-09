<?php
/** Pestaña Condóminos: «Generar cobro» (emitir en bloque / agregar a suscripciones) e historial de emisiones.
 *  @var array $perm @var string $rutaModulo */
?>
<div class="modal fade modal-cond" id="modalCondCobro" tabindex="-1" data-bs-backdrop="static" style="z-index:1060">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-2">
                <h5 class="modal-title fw-bold fs-6"><i class="bi bi-receipt text-primary me-2"></i>Generar cobro a condóminos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3" id="cob-cuerpo">
                <div class="row g-2">
                    <div class="col-md-5">
                        <label for="cob_modo">Qué desea hacer *</label>
                        <select class="form-select form-select-sm" id="cob_modo" onchange="COND.cobroModo()">
                            <option value="emitir">Emitir ahora: recibos o facturas de una sola vez (multas, extraordinarias…)</option>
                            <option value="suscripcion">Agregar a suscripciones: cobro recurrente (alícuota…)</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="cob_destino">A quiénes *</label>
                        <select class="form-select form-select-sm" id="cob_destino" onchange="COND.cobroCambio()">
                            <option value="marcados">Los marcados en el listado</option>
                            <option value="filtro">Todos los del filtro actual</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="cob_agrupar">Un documento por *</label>
                        <select class="form-select form-select-sm" id="cob_agrupar" onchange="COND.cobroAgrupar()">
                            <option value="cliente">Condómino</option>
                            <option value="inmueble">Inmueble que paga</option>
                        </select>
                    </div>
                </div>
                <div class="row g-2 mt-0">
                    <div class="col-md-3">
                        <label for="cob_multa">Multa del reglamento <span class="text-muted fw-normal">(atajo)</span></label>
                        <select class="form-select form-select-sm" id="cob_multa" onchange="COND.cobroMulta()"><option value="">—</option></select>
                    </div>
                    <div class="col-md-4 position-relative">
                        <label for="cob_producto_txt">Concepto (servicio de Productos) *</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-box-seam"></i></span>
                            <input type="text" class="form-control" id="cob_producto_txt" placeholder="Buscar servicio…" autocomplete="off">
                            <input type="hidden" id="cob_id_producto">
                        </div>
                        <div class="list-group position-absolute w-100 shadow cond-dropdown" id="cob_producto_dd" style="display:none"></div>
                    </div>
                    <div class="col-md-3">
                        <label for="cob_forma_valor">Valor</label>
                        <select class="form-select form-select-sm" id="cob_forma_valor" onchange="COND.cobroAgrupar()">
                            <option value="fijo">El mismo para todos</option>
                            <option value="inmueble">Cuota del inmueble (valor que rige)</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="cob_valor">Valor sin IVA *</label>
                        <input type="number" class="form-control form-control-sm text-end" id="cob_valor" step="0.01" min="0" placeholder="0.00">
                    </div>
                </div>
                <div class="row g-2 mt-0">
                    <div class="col-md-2">
                        <label for="cob_tipo_comprobante">Comprobante *</label>
                        <select class="form-select form-select-sm" id="cob_tipo_comprobante" onchange="COND.cobroCambio()">
                            <option value="recibo">Recibo de venta</option>
                            <option value="factura">Factura</option>
                        </select>
                    </div>
                    <!-- Solo «emitir ahora» -->
                    <div class="col-md-2 cob-emitir">
                        <label for="cob_id_punto_emision">Serie *</label>
                        <select class="form-select form-select-sm" id="cob_id_punto_emision" onchange="COND.cobroCambio()"></select>
                    </div>
                    <div class="col-md-4 cob-emitir">
                        <label for="cob_descripcion">Descripción *</label>
                        <input type="text" class="form-control form-control-sm" id="cob_descripcion" maxlength="200" placeholder="Multa por ruido, octubre 2026">
                        <div class="form-text">Sale en la información adicional como «Concepto».</div>
                    </div>
                    <div class="col-md-4 cob-emitir">
                        <label for="cob_texto_item">Texto de la línea <span class="text-muted fw-normal">(opcional)</span></label>
                        <input type="text" class="form-control form-control-sm" id="cob_texto_item" maxlength="300" placeholder="Acta N.º 5 del {fecha}">
                    </div>
                    <!-- Solo «agregar a suscripciones» -->
                    <div class="col-md-3 cob-susc d-none">
                        <label for="cob_id_periodicidad">Periodicidad *</label>
                        <select class="form-select form-select-sm" id="cob_id_periodicidad" onchange="COND.cobroCambio()"></select>
                    </div>
                    <div class="col-md-3 cob-susc d-none">
                        <label for="cob_fecha_inicio">Primer cobro *</label>
                        <input type="date" class="form-control form-control-sm" id="cob_fecha_inicio" onchange="COND.cobroCambio()">
                        <div class="form-text">Solo para las suscripciones nuevas.</div>
                    </div>
                </div>
                <div class="row g-2 mt-1 align-items-center">
                    <div class="col-md-8 cob-emitir">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="cob_enviar_correo" onchange="COND.cobroCambio()">
                            <label class="form-check-label" for="cob_enviar_correo">Enviar cada documento por correo al condómino</label>
                        </div>
                        <div class="form-text">Las facturas se envían al SRI y el correo sale cuando quedan autorizadas.</div>
                    </div>
                    <div class="col-md-8 cob-susc d-none small text-muted">
                        <i class="bi bi-info-circle me-1"></i>No se duplica nada: si ya tiene el concepto con el mismo valor se omite; si cambió el valor, se actualiza; si tiene suscripción sin el concepto, se le agrega la línea; si no tiene, se crea.
                    </div>
                    <div class="col-md-4 text-end">
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="COND.cobroPreview()"><i class="bi bi-eye me-1"></i>Vista previa</button>
                    </div>
                </div>

                <div id="cob-preview" class="d-none mt-3">
                    <div class="row g-2 mb-2" id="cob-kpis"></div>
                    <div class="border rounded-3 bg-white" style="max-height: 42vh; overflow: auto;">
                        <table class="table table-sm table-hover mb-0 small">
                            <thead class="table-light"><tr>
                                <th class="ps-2" style="width:28px"><input type="checkbox" class="form-check-input" id="cob-todas" onchange="COND.cobroTodas(this.checked)"></th>
                                <th>Condómino</th><th>Inmueble</th><th class="text-end" id="cob-th-valor">Valor</th><th>Acción</th><th>Correo</th><th class="pe-2">Nota</th>
                            </tr></thead>
                            <tbody id="cob-body"></tbody>
                        </table>
                    </div>
                </div>

                <div id="cob-progreso" class="d-none mt-3 border rounded-3 p-3 bg-light">
                    <div class="d-flex justify-content-between small mb-1"><b id="cob-prog-titulo">Generando…</b><span id="cob-prog-texto"></span></div>
                    <div class="progress" style="height:10px"><div class="progress-bar" id="cob-prog-barra" style="width:0%"></div></div>
                    <div class="small text-muted mt-2">Puede cerrar esta ventana: el proceso sigue en segundo plano. El avance y los errores quedan en <b>Emisiones</b>.</div>
                </div>
            </div>
            <div class="modal-footer bg-light border-top p-2 d-flex justify-content-between">
                <span class="small text-muted" id="cob-pie"></span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>Cerrar</button>
                    <?php if (!empty($perm['crear'])): ?>
                        <button type="button" class="btn btn-primary btn-sm px-3 d-none" id="cob-btn-aplicar" onclick="COND.cobroAplicar()"><i class="bi bi-check2-circle me-1"></i><span>Emitir</span></button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade modal-cond" id="modalCondEmisiones" tabindex="-1" style="z-index:1060">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-2">
                <h5 class="modal-title fw-bold fs-6"><i class="bi bi-clock-history text-primary me-2"></i>Emisiones en bloque <span class="text-muted fw-normal" id="emi-sub"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div id="emi-lista">
                    <div class="border rounded-3 bg-white">
                        <table class="table table-sm table-hover mb-0 small">
                            <thead class="table-light"><tr><th class="ps-2">Fecha</th><th>Descripción</th><th>Concepto</th><th>Comprob.</th><th class="text-end">Docs.</th><th class="text-end">Generados</th><th class="text-end">Con error</th><th class="text-end">Correos</th><th class="text-end">Total</th><th>Estado</th><th class="pe-2"></th></tr></thead>
                            <tbody id="emi-body"><tr><td colspan="11" class="text-center text-muted py-3">—</td></tr></tbody>
                        </table>
                    </div>
                </div>
                <div id="emi-detalle" class="d-none">
                    <button type="button" class="btn btn-link btn-sm px-0 mb-2" onclick="COND.emisionesVolver()"><i class="bi bi-arrow-left me-1"></i>Volver a las emisiones</button>
                    <div class="border rounded-3 bg-white" style="max-height: 55vh; overflow: auto;">
                        <table class="table table-sm table-hover mb-0 small">
                            <thead class="table-light"><tr><th class="ps-2">Condómino</th><th>Inmueble</th><th class="text-end">Valor</th><th>Documento</th><th>Estado</th><th>Correo</th><th class="pe-2">Mensaje</th></tr></thead>
                            <tbody id="emi-items"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
