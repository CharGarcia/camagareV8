<?php
/**
 * Descargas del SRI → «Desde PDF»: ventana de revisión de un comprobante leído
 * de su PDF. La llena y la envía public/js/modulos/descargas_sri_pdf.js.
 */
use App\Helpers\ComprobantePdf;

$rpIva = [];
foreach (ComprobantePdf::IVA as $cod => [$etq]) {
    $rpIva[] = ['cod' => (string) $cod, 'nombre' => $etq];
}
?>
<style>
    #modalRevisionPdf .rp-tabla td { padding: 0; }
    #modalRevisionPdf .rp-tabla .form-control,
    #modalRevisionPdf .rp-tabla .form-select { padding: 0 4px; height: 20px; font-size: 0.78rem; border-radius: 0; }
    #modalRevisionPdf .rp-tabla .form-select { padding-right: 18px; background-position: right 2px center; }
    #modalRevisionPdf .rp-tabla th { font-size: 0.72rem; padding: 2px 4px; white-space: nowrap; }
    #modalRevisionPdf .rp-campos .form-control,
    #modalRevisionPdf .rp-campos .form-select { height: 28px; font-size: 0.78rem; padding-top: 0; padding-bottom: 0; }
    #modalRevisionPdf .rp-campos label { font-size: 0.72rem; margin-bottom: 1px; }
    #modalRevisionPdf .rp-titulo { font-size: 0.8rem; font-weight: 700; border-bottom: 1px solid var(--bs-border-color); padding-bottom: 2px; margin: 12px 0 6px; }
    #modalRevisionPdf .rp-avisos ul { margin: 0; padding-left: 1.1rem; }
</style>

<div class="modal fade" id="modalRevisionPdf" tabindex="-1" aria-labelledby="modalRevisionPdfTitulo" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold" id="modalRevisionPdfTitulo">
                    <i class="bi bi-file-earmark-pdf text-danger me-2"></i>Revisar comprobante leído del PDF
                    <span class="text-muted fw-normal small ms-2" id="rp_archivo"></span>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body pt-2">
                <!-- Barra de acciones superior -->
                <div class="d-flex gap-1 align-items-center flex-wrap border-bottom pb-2 mb-2">
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="rpVerPdf()" title="Abrir el PDF para comparar"><i class="bi bi-file-earmark-pdf"></i></button>
                    <div class="vr mx-1"></div>
                    <span class="small text-muted">Compare estos datos con el PDF, corrija lo que haga falta y luego registre.</span>
                </div>

                <div class="rp-avisos" id="rp_mensajes"></div>

                <form id="rp_form" autocomplete="off" onsubmit="return false;">
                    <div class="rp-titulo">Comprobante</div>
                    <div class="row g-2 rp-campos">
                        <div class="col-md-3">
                            <label class="form-label d-block" for="rp_cod_doc">Tipo de documento</label>
                            <select id="rp_cod_doc" class="form-select form-select-sm" onchange="rpAjustarSecciones()">
                                <option value="">— Elija —</option>
                                <?php foreach (ComprobantePdf::TIPOS as $cod => $nom): ?>
                                    <option value="<?= $cod ?>"><?= htmlspecialchars($nom) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-4 col-md-1">
                            <label class="form-label d-block" for="rp_estab">Estab.</label>
                            <input type="text" id="rp_estab" class="form-control form-control-sm" maxlength="3" inputmode="numeric">
                        </div>
                        <div class="col-4 col-md-1">
                            <label class="form-label d-block" for="rp_pto_emi">Pto. emi.</label>
                            <input type="text" id="rp_pto_emi" class="form-control form-control-sm" maxlength="3" inputmode="numeric">
                        </div>
                        <div class="col-4 col-md-2">
                            <label class="form-label d-block" for="rp_secuencial">Secuencial</label>
                            <input type="text" id="rp_secuencial" class="form-control form-control-sm" maxlength="9" inputmode="numeric">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label d-block" for="rp_fecha_emision">Fecha emisión</label>
                            <input type="text" id="rp_fecha_emision" class="form-control form-control-sm" maxlength="10" placeholder="dd/mm/aaaa">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label d-block" for="rp_fecha_autorizacion">Fecha autorización</label>
                            <input type="text" id="rp_fecha_autorizacion" class="form-control form-control-sm" maxlength="19" placeholder="dd/mm/aaaa hh:mm:ss">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label d-block" for="rp_clave_acceso">Clave de acceso (49 dígitos)</label>
                            <input type="text" id="rp_clave_acceso" class="form-control form-control-sm font-monospace" maxlength="49" inputmode="numeric">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label d-block" for="rp_numero_autorizacion">Número de autorización</label>
                            <input type="text" id="rp_numero_autorizacion" class="form-control form-control-sm font-monospace" maxlength="49" inputmode="numeric">
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-lg-6">
                            <div class="rp-titulo">Emisor</div>
                            <div class="row g-2 rp-campos">
                                <div class="col-md-4">
                                    <label class="form-label d-block" for="rp_em_ruc">RUC</label>
                                    <input type="text" id="rp_em_ruc" class="form-control form-control-sm" maxlength="13" inputmode="numeric">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label d-block" for="rp_em_razon_social">Razón social</label>
                                    <input type="text" id="rp_em_razon_social" class="form-control form-control-sm" maxlength="300">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label d-block" for="rp_em_nombre_comercial">Nombre comercial</label>
                                    <input type="text" id="rp_em_nombre_comercial" class="form-control form-control-sm" maxlength="300">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label d-block" for="rp_em_obligado">Obligado a contabilidad</label>
                                    <select id="rp_em_obligado" class="form-select form-select-sm">
                                        <option value=""></option><option value="SI">SI</option><option value="NO">NO</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label d-block" for="rp_em_dir_matriz">Dirección matriz</label>
                                    <input type="text" id="rp_em_dir_matriz" class="form-control form-control-sm" maxlength="300">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label d-block" for="rp_em_dir_establecimiento">Dirección establecimiento</label>
                                    <input type="text" id="rp_em_dir_establecimiento" class="form-control form-control-sm" maxlength="300">
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="rp-titulo" id="rp_titulo_receptor">Comprador</div>
                            <div class="row g-2 rp-campos">
                                <div class="col-md-4">
                                    <label class="form-label d-block" for="rp_re_identificacion">Identificación</label>
                                    <input type="text" id="rp_re_identificacion" class="form-control form-control-sm" maxlength="20">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label d-block" for="rp_re_razon_social">Nombre / razón social</label>
                                    <input type="text" id="rp_re_razon_social" class="form-control form-control-sm" maxlength="300">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label d-block" for="rp_re_direccion">Dirección</label>
                                    <input type="text" id="rp_re_direccion" class="form-control form-control-sm" maxlength="300">
                                </div>
                            </div>

                            <!-- Notas de crédito y débito -->
                            <div data-rp-tipos="04,05">
                                <div class="rp-titulo">Documento que modifica</div>
                                <div class="row g-2 rp-campos">
                                    <div class="col-md-4">
                                        <label class="form-label d-block" for="rp_mod_cod_doc">Tipo</label>
                                        <select id="rp_mod_cod_doc" class="form-select form-select-sm">
                                            <?php foreach (ComprobantePdf::DOCS_SUSTENTO as $cod => $nom): ?>
                                                <option value="<?= $cod ?>"><?= $cod ?> - <?= htmlspecialchars($nom) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label d-block" for="rp_mod_numero">Número</label>
                                        <input type="text" id="rp_mod_numero" class="form-control form-control-sm" maxlength="17" placeholder="001-001-000000001">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label d-block" for="rp_mod_fecha">Fecha</label>
                                        <input type="text" id="rp_mod_fecha" class="form-control form-control-sm" maxlength="10" placeholder="dd/mm/aaaa">
                                    </div>
                                    <div class="col-md-12" data-rp-tipos="04">
                                        <label class="form-label d-block" for="rp_mod_motivo">Motivo</label>
                                        <input type="text" id="rp_mod_motivo" class="form-control form-control-sm" maxlength="300">
                                    </div>
                                </div>
                            </div>

                            <!-- Retenciones -->
                            <div data-rp-tipos="07">
                                <div class="row g-2 rp-campos mt-0">
                                    <div class="col-md-4">
                                        <label class="form-label d-block" for="rp_periodo_fiscal">Periodo fiscal</label>
                                        <input type="text" id="rp_periodo_fiscal" class="form-control form-control-sm" maxlength="7" placeholder="mm/aaaa">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Detalle (factura, liquidación, nota de crédito) -->
                    <div data-rp-tipos="01,03,04">
                        <div class="rp-titulo d-flex justify-content-between align-items-center">
                            <span>Detalle</span>
                            <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2" style="font-size:.72rem" onclick="rpAgregarFila('detalles')"><i class="bi bi-plus-lg"></i> Línea</button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm mb-0 rp-tabla">
                                <thead class="bg-light">
                                    <tr>
                                        <th style="width:110px">Código</th><th>Descripción</th>
                                        <th style="width:90px" class="text-end">Cantidad</th><th style="width:100px" class="text-end">Precio unit.</th>
                                        <th style="width:85px" class="text-end">Descuento</th><th style="width:100px" class="text-end">Subtotal</th>
                                        <th style="width:135px">IVA</th><th style="width:26px"></th>
                                    </tr>
                                </thead>
                                <tbody id="rp_tb_detalles"></tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Motivos (nota de débito) -->
                    <div data-rp-tipos="05">
                        <div class="rp-titulo d-flex justify-content-between align-items-center">
                            <span>Motivos</span>
                            <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2" style="font-size:.72rem" onclick="rpAgregarFila('motivos')"><i class="bi bi-plus-lg"></i> Motivo</button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm mb-0 rp-tabla">
                                <thead class="bg-light"><tr><th>Razón</th><th style="width:120px" class="text-end">Valor</th><th style="width:26px"></th></tr></thead>
                                <tbody id="rp_tb_motivos"></tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Líneas de retención -->
                    <div data-rp-tipos="07">
                        <div class="rp-titulo d-flex justify-content-between align-items-center">
                            <span>Retenciones</span>
                            <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2" style="font-size:.72rem" onclick="rpAgregarFila('retenciones')"><i class="bi bi-plus-lg"></i> Línea</button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm mb-0 rp-tabla">
                                <thead class="bg-light">
                                    <tr>
                                        <th style="width:85px">Impuesto</th><th style="min-width:220px">Código de retención</th>
                                        <th style="width:95px" class="text-end">Base</th><th style="width:65px" class="text-end">%</th>
                                        <th style="width:95px" class="text-end">Retenido</th><th style="width:170px">Doc. sustento</th>
                                        <th style="width:135px">Número sustento</th><th style="width:90px">Fecha sust.</th><th style="width:26px"></th>
                                    </tr>
                                </thead>
                                <tbody id="rp_tb_retenciones"></tbody>
                            </table>
                        </div>
                        <div class="form-text">El PDF casi nunca imprime el <strong>código de retención</strong>: se proponen los del catálogo con ese impuesto y porcentaje. Elija el que corresponde.</div>
                    </div>

                    <!-- Totales, IVA y pagos -->
                    <div class="row g-3" data-rp-tipos="01,03,04,05">
                        <div class="col-lg-4">
                            <div class="rp-titulo d-flex justify-content-between align-items-center">
                                <span>IVA por tarifa</span>
                                <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2" style="font-size:.72rem" onclick="rpAgregarFila('impuestos')"><i class="bi bi-plus-lg"></i> Tarifa</button>
                            </div>
                            <table class="table table-bordered table-sm mb-0 rp-tabla">
                                <thead class="bg-light"><tr><th>Tarifa</th><th class="text-end">Base</th><th class="text-end">IVA</th><th style="width:26px"></th></tr></thead>
                                <tbody id="rp_tb_impuestos"></tbody>
                            </table>
                        </div>
                        <div class="col-lg-4" data-rp-tipos="01,03,05">
                            <div class="rp-titulo d-flex justify-content-between align-items-center">
                                <span>Formas de pago</span>
                                <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2" style="font-size:.72rem" onclick="rpAgregarFila('pagos')"><i class="bi bi-plus-lg"></i> Pago</button>
                            </div>
                            <table class="table table-bordered table-sm mb-0 rp-tabla">
                                <thead class="bg-light"><tr><th>Forma</th><th style="width:85px" class="text-end">Total</th><th style="width:55px" class="text-end">Plazo</th><th style="width:26px"></th></tr></thead>
                                <tbody id="rp_tb_pagos"></tbody>
                            </table>
                        </div>
                        <div class="col-lg-4 ms-auto">
                            <div class="rp-titulo">Totales</div>
                            <div class="row g-2 rp-campos">
                                <div class="col-6">
                                    <label class="form-label d-block" for="rp_tot_subtotal">Subtotal sin impuestos</label>
                                    <input type="text" id="rp_tot_subtotal" class="form-control form-control-sm text-end" inputmode="decimal">
                                </div>
                                <div class="col-6">
                                    <label class="form-label d-block" for="rp_tot_descuento">Descuento</label>
                                    <input type="text" id="rp_tot_descuento" class="form-control form-control-sm text-end" inputmode="decimal">
                                </div>
                                <div class="col-6">
                                    <label class="form-label d-block" for="rp_tot_propina">Propina</label>
                                    <input type="text" id="rp_tot_propina" class="form-control form-control-sm text-end" inputmode="decimal">
                                </div>
                                <div class="col-6">
                                    <label class="form-label d-block" for="rp_tot_ice">ICE / IRBPNR</label>
                                    <input type="text" id="rp_tot_ice" class="form-control form-control-sm text-end" inputmode="decimal">
                                </div>
                                <div class="col-12">
                                    <label class="form-label d-block fw-bold" for="rp_tot_total">Valor total</label>
                                    <input type="text" id="rp_tot_total" class="form-control form-control-sm text-end fw-bold" inputmode="decimal">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rp-titulo d-flex justify-content-between align-items-center">
                        <span>Información adicional</span>
                        <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2" style="font-size:.72rem" onclick="rpAgregarFila('info_adicional')"><i class="bi bi-plus-lg"></i> Campo</button>
                    </div>
                    <table class="table table-bordered table-sm mb-0 rp-tabla">
                        <thead class="bg-light"><tr><th style="width:30%">Nombre</th><th>Valor</th><th style="width:26px"></th></tr></thead>
                        <tbody id="rp_tb_info_adicional"></tbody>
                    </table>
                </form>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-sm btn-outline-primary" id="rp_btn_verificar" onclick="rpVerificar()"><i class="bi bi-check2-circle me-1"></i>Verificar datos</button>
                <button type="button" class="btn btn-sm btn-success" id="rp_btn_registrar" onclick="rpRegistrar()"><i class="bi bi-save me-1"></i>Aprobar y registrar</button>
            </div>
        </div>
    </div>
</div>

<script>
window.CMG_PDF_CAT = <?= json_encode([
    'tipos'     => ComprobantePdf::TIPOS,
    'iva'       => $rpIva,
    'pagos'     => ComprobantePdf::FORMAS_PAGO,
    'sustentos' => ComprobantePdf::DOCS_SUSTENTO,
    'maxBytes'  => \App\Services\modulos\DescargaPdfService::MAX_BYTES,
    // Solo llega con datos al superadministrador (ver DescargasSriController::index).
    'empresasIa' => array_values($empresasIa ?? []),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>
