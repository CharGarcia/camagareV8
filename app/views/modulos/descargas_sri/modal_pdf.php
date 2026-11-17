<?php
/**
 * Descargas del SRI → «Desde PDF» e «Ingreso manual»: ventana de revisión de un
 * comprobante leído de su PDF o digitado a mano (solo emitidos). La llena y la
 * envía public/js/modulos/descargas_sri_pdf.js.
 */
use App\Helpers\ComprobantePdf;

$rpIva = [];
foreach (ComprobantePdf::IVA as $cod => [$etq, $pctIva]) {
    $rpIva[] = ['cod' => (string) $cod, 'nombre' => $etq, 'pct' => (float) $pctIva];
}
?>
<style>
    /* Tablas con el mismo diseño del detalle de Factura de Venta (factura_venta/index.php). */
    #modalRevisionPdf .table-detalle th { font-size: 0.7rem; text-transform: uppercase; padding: 4px 8px; white-space: nowrap; }
    #modalRevisionPdf .table-detalle td { padding: 0; vertical-align: middle; }
    #modalRevisionPdf .input-detalle { border: none; background: transparent; height: 30px; font-size: 0.82rem; padding: 2px 8px; border-radius: 0; box-shadow: none; }
    #modalRevisionPdf .input-detalle:focus { background: var(--bs-body-bg); box-shadow: inset 0 0 0 1px #0d6efd; outline: none; }
    #modalRevisionPdf select.input-detalle { padding-right: 22px; background-position: right 4px center; }
    #modalRevisionPdf .row-detalle:hover { background-color: rgba(13, 110, 253, 0.03); }
    #modalRevisionPdf .rp-totales { font-size: 0.75rem; }
    #modalRevisionPdf .rp-totales .rp-tot-input { width: 110px; height: 26px; background: var(--bs-tertiary-bg); border-radius: 4px; }
    #modalRevisionPdf .rp-campos .form-control,
    #modalRevisionPdf .rp-campos .form-select { height: 28px; font-size: 0.78rem; padding-top: 0; padding-bottom: 0; }
    #modalRevisionPdf .rp-campos label { font-size: 0.72rem; margin-bottom: 1px; }
    #modalRevisionPdf .rp-titulo { font-size: 0.8rem; font-weight: 700; border-bottom: 1px solid var(--bs-border-color); padding-bottom: 2px; margin: 12px 0 6px; }
    #modalRevisionPdf .rp-avisos ul { margin: 0; padding-left: 1.1rem; }
</style>

<div class="modal fade" id="modalRevisionPdf" tabindex="-1" aria-labelledby="modalRevisionPdfTitulo" aria-hidden="true" data-bs-backdrop="static">
    <!-- Sin modal-dialog-scrollable: la ventana no tiene scroll vertical propio; se desplaza completa. -->
    <div class="modal-dialog modal-xl" id="rp_dialogo">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold" id="modalRevisionPdfTitulo">
                    <i class="bi bi-file-earmark-pdf text-danger me-2"></i><span id="rp_titulo_texto">Revisar comprobante leído del PDF</span>
                    <span class="text-muted fw-normal small ms-2" id="rp_archivo"></span>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body pt-2">
                <!-- Barra de acciones superior -->
                <div class="d-flex gap-1 align-items-center flex-wrap border-bottom pb-2 mb-2">
                    <button type="button" class="btn btn-sm btn-outline-danger" id="rp_btn_ver_pdf" onclick="rpVerPdf()" title="Abrir el PDF para comparar"><i class="bi bi-file-earmark-pdf"></i></button>
                    <div class="vr mx-1" id="rp_sep_ver_pdf"></div>
                    <span class="small text-muted" id="rp_ayuda">Compare estos datos con el PDF, corrija lo que haga falta y luego registre.</span>
                </div>

                <div class="rp-avisos" id="rp_mensajes"></div>

                <form id="rp_form" autocomplete="off" onsubmit="return false;">
                    <?php $rpReq = '<span class="text-danger" title="Obligatorio para registrar">*</span>'; ?>
                    <!-- Qué hace falta para registrar: el JS lo ajusta al tipo de documento (rpAjustarSecciones). -->
                    <div class="border rounded-3 bg-light px-3 py-2 mb-3 small" id="rp_requisitos_caja">
                        <div class="fw-bold mb-1"><i class="bi bi-list-check me-1 text-primary"></i>Para registrar este comprobante se necesita</div>
                        <ul class="mb-1 ps-3" id="rp_requisitos"></ul>
                        <div class="text-muted">Los campos con <span class="text-danger">*</span> son obligatorios. Si las sumas no cuadran, el sistema lo avisa y puede registrar igual después de confirmarlo.</div>
                    </div>

                    <!-- Tarjeta: Comprobante -->
                    <div class="border rounded-3 bg-white shadow-sm mb-3">
                        <div class="px-3 py-2 border-bottom bg-light small fw-bold"><i class="bi bi-receipt me-2 text-primary"></i>Comprobante</div>
                        <div class="p-3">
                            <div class="row g-2 rp-campos">
                                <div class="col-md-3">
                                    <label class="form-label d-block" for="rp_cod_doc">Tipo de documento <?= $rpReq ?></label>
                                    <select id="rp_cod_doc" class="form-select form-select-sm" onchange="rpAjustarSecciones()">
                                        <option value="">— Elija —</option>
                                        <?php foreach (ComprobantePdf::TIPOS as $cod => $nom): ?>
                                            <option value="<?= $cod ?>"><?= htmlspecialchars($nom) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <!-- Un solo campo «001-001-000000009»; el JS lo reparte en los tres ocultos
                                         (estab, pto_emi, secuencial), que son los que viajan al servidor. -->
                                    <label class="form-label d-block" for="rp_numero">Número (estab.-pto. emi.-secuencial) <?= $rpReq ?></label>
                                    <input type="text" id="rp_numero" class="form-control form-control-sm font-monospace" maxlength="17" inputmode="numeric" placeholder="001-001-000000009">
                                    <input type="hidden" id="rp_estab">
                                    <input type="hidden" id="rp_pto_emi">
                                    <input type="hidden" id="rp_secuencial">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label d-block" for="rp_fecha_emision">Fecha emisión <?= $rpReq ?></label>
                                    <input type="text" id="rp_fecha_emision" class="form-control form-control-sm" maxlength="10" placeholder="dd/mm/aaaa">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label d-block" for="rp_fecha_autorizacion">Fecha autorización</label>
                                    <input type="text" id="rp_fecha_autorizacion" class="form-control form-control-sm" maxlength="19" placeholder="dd/mm/aaaa hh:mm:ss" title="Si se deja igual, se usa la fecha de emisión">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label d-block" for="rp_clave_acceso">Clave de acceso (49 dígitos) <?= $rpReq ?></label>
                                    <input type="text" id="rp_clave_acceso" class="form-control form-control-sm font-monospace" maxlength="49" inputmode="numeric">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label d-block" for="rp_numero_autorizacion">Número de autorización</label>
                                    <input type="text" id="rp_numero_autorizacion" class="form-control form-control-sm font-monospace" maxlength="49" inputmode="numeric" title="Por lo general es la misma clave de acceso">
                                </div>
                                <!-- Retenciones -->
                                <div class="col-md-3" data-rp-tipos="07">
                                    <label class="form-label d-block" for="rp_periodo_fiscal">Periodo fiscal <?= $rpReq ?></label>
                                    <input type="text" id="rp_periodo_fiscal" class="form-control form-control-sm" maxlength="7" placeholder="mm/aaaa">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <!-- Tarjeta: Emisor (quien emite el comprobante). En el ingreso manual se oculta:
                             el emisor es la empresa y sus datos se toman de Empresas (los campos siguen
                             llenos, solo no se muestran). En «Desde PDF» se ve: en un recibido es el proveedor. -->
                        <div class="col-lg-6" id="rp_col_emisor">
                            <div class="border rounded-3 bg-white shadow-sm h-100">
                                <div class="px-3 py-2 border-bottom bg-light small fw-bold">
                                    <i class="bi bi-building me-2 text-primary"></i>Emisor
                                    <span class="fw-normal text-muted ms-1">— quien emite el comprobante</span>
                                </div>
                                <div class="p-3">
                                    <div class="row g-2 rp-campos">
                                        <div class="col-md-4">
                                            <label class="form-label d-block" for="rp_em_ruc">RUC <?= $rpReq ?></label>
                                            <input type="text" id="rp_em_ruc" class="form-control form-control-sm" maxlength="13" inputmode="numeric">
                                        </div>
                                        <div class="col-md-8">
                                            <label class="form-label d-block" for="rp_em_razon_social">Razón social <?= $rpReq ?></label>
                                            <input type="text" id="rp_em_razon_social" class="form-control form-control-sm" maxlength="300">
                                        </div>
                                        <div class="col-md-8">
                                            <label class="form-label d-block" for="rp_em_dir_matriz">Dirección matriz</label>
                                            <input type="text" id="rp_em_dir_matriz" class="form-control form-control-sm" maxlength="300">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label d-block" for="rp_em_obligado">Obligado a contabilidad</label>
                                            <select id="rp_em_obligado" class="form-select form-select-sm">
                                                <option value="SI">Sí</option><option value="NO">No</option>
                                            </select>
                                        </div>
                                        <!-- Sin campo en pantalla: conservan lo leído del PDF y, si no hay, el JS
                                             usa la razón social como nombre comercial y la matriz como establecimiento. -->
                                        <input type="hidden" id="rp_em_nombre_comercial">
                                        <input type="hidden" id="rp_em_dir_establecimiento">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tarjeta: Comprador / Proveedor / Sujeto retenido (a quien se emite) -->
                        <div class="col-lg-6" id="rp_col_receptor">
                            <div class="border rounded-3 bg-white shadow-sm h-100">
                                <div class="px-3 py-2 border-bottom bg-light small fw-bold">
                                    <i class="bi bi-person me-2 text-primary"></i><span id="rp_titulo_receptor">Comprador</span>
                                    <span class="fw-normal text-muted ms-1">— a quien se emite el comprobante</span>
                                </div>
                                <div class="p-3">
                                    <div class="row g-2 rp-campos">
                                        <!-- Buscador de clientes: llena identificación, nombre y dirección.
                                             Solo para comprador (factura, notas); en liquidación y retención
                                             el receptor es un proveedor y no se muestra. -->
                                        <div class="col-12 position-relative" id="rp_buscar_cliente_wrap">
                                            <label class="form-label d-block" for="rp_buscar_cliente">Buscar cliente</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                                <input type="text" id="rp_buscar_cliente" class="form-control form-control-sm" autocomplete="off" placeholder="Nombre o identificación del cliente…">
                                            </div>
                                            <div id="rp_clientes_lista" class="list-group position-absolute shadow-sm d-none" style="z-index:1065; left:4px; right:4px; max-height:240px; overflow-y:auto;"></div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label d-block" for="rp_re_tipo_id">Tipo de identificación <?= $rpReq ?></label>
                                            <select id="rp_re_tipo_id" class="form-select form-select-sm">
                                                <?php foreach (ComprobantePdf::TIPOS_IDENTIFICACION as $codTipoId => $nomTipoId): ?>
                                                    <option value="<?= $codTipoId ?>"><?= htmlspecialchars($nomTipoId) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label d-block" for="rp_re_identificacion">Identificación <?= $rpReq ?></label>
                                            <input type="text" id="rp_re_identificacion" class="form-control form-control-sm" maxlength="20" placeholder="Cédula, RUC o pasaporte">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label d-block" for="rp_re_razon_social">Nombre / razón social <?= $rpReq ?></label>
                                            <input type="text" id="rp_re_razon_social" class="form-control form-control-sm" maxlength="300">
                                        </div>
                                        <div class="col-md-12">
                                            <label class="form-label d-block" for="rp_re_direccion">Dirección</label>
                                            <input type="text" id="rp_re_direccion" class="form-control form-control-sm" maxlength="300" placeholder="Opcional">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tarjeta: Documento que modifica (notas de crédito y débito) -->
                    <div class="border rounded-3 bg-white shadow-sm mb-3" data-rp-tipos="04,05">
                        <div class="px-3 py-2 border-bottom bg-light small fw-bold"><i class="bi bi-arrow-left-right me-2 text-primary"></i>Documento que modifica</div>
                        <div class="p-3">
                            <div class="row g-2 rp-campos">
                                <div class="col-md-3">
                                    <label class="form-label d-block" for="rp_mod_cod_doc">Tipo <?= $rpReq ?></label>
                                    <select id="rp_mod_cod_doc" class="form-select form-select-sm">
                                        <?php foreach (ComprobantePdf::DOCS_SUSTENTO as $cod => $nom): ?>
                                            <option value="<?= $cod ?>"><?= $cod ?> - <?= htmlspecialchars($nom) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label d-block" for="rp_mod_numero">Número <?= $rpReq ?></label>
                                    <input type="text" id="rp_mod_numero" class="form-control form-control-sm" maxlength="17" placeholder="001-001-000000001">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label d-block" for="rp_mod_fecha">Fecha <?= $rpReq ?></label>
                                    <input type="text" id="rp_mod_fecha" class="form-control form-control-sm" maxlength="10" placeholder="dd/mm/aaaa">
                                </div>
                                <div class="col-md-4" data-rp-tipos="04">
                                    <label class="form-label d-block" for="rp_mod_motivo">Motivo <?= $rpReq ?></label>
                                    <input type="text" id="rp_mod_motivo" class="form-control form-control-sm" maxlength="300">
                                </div>
                            </div>
                        </div>
                    </div>

<?php
                    /**
                     * Tarjeta de tabla con el diseño del detalle de Factura de Venta: borde
                     * redondeado, encabezado gris en mayúsculas, celdas sin borde y pie con
                     * «Agregar línea» y el contador de filas.
                     * $cols: [[título, ancho css, clase extra], ...]; la columna de quitar se agrega sola.
                     */
                    $rpTarjeta = static function (string $tabla, array $cols, string $agregar, string $maxAlto = '300px'): void {
                        ?>
                        <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                            <div class="table-responsive" style="max-height: <?= $maxAlto ?>;">
                                <table class="table table-sm table-detalle mb-0 text-nowrap">
                                    <thead>
                                        <tr class="table-light border-bottom">
                                            <?php foreach ($cols as $i => [$tit, $ancho, $cls]): ?>
                                                <th class="py-2 small fw-bold text-muted <?= $i === 0 ? 'ps-3 ' : '' ?><?= $cls ?>"<?= $ancho !== '' ? ' style="width: ' . $ancho . ';"' : '' ?>><?= $tit ?></th>
                                            <?php endforeach; ?>
                                            <th style="width: 40px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="rp_tb_<?= $tabla ?>"></tbody>
                                </table>
                            </div>
                            <div class="p-2 border-top bg-light d-flex justify-content-between align-items-center">
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" onclick="rpAgregarFila('<?= $tabla ?>')">
                                    <i class="bi bi-plus-circle me-1"></i> <?= $agregar ?>
                                </button>
                                <div class="small fw-bold text-muted pe-3">Ítems: <span id="rp_count_<?= $tabla ?>">0</span></div>
                            </div>
                        </div>
                        <?php
                    };
                    ?>

                    <!-- Detalle (factura, liquidación, nota de crédito) -->
                    <div data-rp-tipos="01,03,04">
                        <div class="rp-titulo">Detalle</div>
                        <?php $rpTarjeta('detalles', [
                            ['Código', '11%', ''], ['Descripción', '', ''], ['Adicional', '16%', ''], ['Cant.', '7%', 'text-center'],
                            ['P. Unitario', '10%', 'text-end'], ['Desc.', '9%', 'text-end'], ['Subtotal', '10%', 'text-end'], ['Iva', '14%', 'text-center'],
                        ], 'Agregar línea', '350px'); ?>
                    </div>

                    <!-- Motivos (nota de débito) -->
                    <div data-rp-tipos="05">
                        <div class="rp-titulo">Motivos</div>
                        <?php $rpTarjeta('motivos', [['Razón', '', ''], ['Valor', '18%', 'text-end']], 'Agregar motivo'); ?>
                    </div>

                    <!-- Líneas de retención -->
                    <div data-rp-tipos="07">
                        <div class="rp-titulo">Retenciones</div>
                        <?php $rpTarjeta('retenciones', [
                            ['Impuesto', '90px', ''], ['Código de retención', '', ''], ['Base', '95px', 'text-end'], ['%', '65px', 'text-end'],
                            ['Retenido', '95px', 'text-end'], ['Doc. sustento', '170px', ''], ['Número sustento', '140px', ''], ['Fecha sust.', '95px', ''],
                        ], 'Agregar línea'); ?>
                        <div class="form-text">El PDF casi nunca imprime el <strong>código de retención</strong>: se proponen los del catálogo con ese impuesto y porcentaje. Elija el que corresponde.</div>
                    </div>

                    <!-- Pie del documento, como en Factura de Venta: a la izquierda IVA por tarifa
                         (si se ve) y, ocupando el resto del ancho hasta Totales, Formas de pago SRI
                         con Información adicional debajo, del mismo ancho; a la derecha, Totales. -->
                    <div class="row g-3 mt-0">
                        <div class="col-lg-8">
                            <div class="row g-3">
                                <!-- En el ingreso manual (factura, liquidación, nota de crédito) se oculta: el
                                     IVA por tarifa se calcula con el detalle (la tabla sigue armándose por dentro
                                     y viaja al servidor) y su desglose se muestra en Totales. En «Desde PDF» y en
                                     la nota de débito se ve. La visibilidad la decide rpAjustarSecciones(). -->
                                <div class="col-md-5" id="rp_col_impuestos">
                                    <div class="rp-titulo mt-0">IVA por tarifa</div>
                                    <?php $rpTarjeta('impuestos', [['Tarifa', '', ''], ['Base', '30%', 'text-end'], ['IVA', '26%', 'text-end']], 'Agregar tarifa', '200px'); ?>
                                </div>
                                <div class="col">
                                    <div data-rp-tipos="01,03,05" class="mb-3">
                                        <div class="rp-titulo mt-0">Formas de pago SRI</div>
                                        <?php $rpTarjeta('pagos', [['Forma', '', ''], ['Total', '22%', 'text-end'], ['Plazo', '14%', 'text-end']], 'Añadir pago', '200px'); ?>
                                    </div>
                                    <div class="rp-titulo mt-0">Información adicional</div>
                                    <?php $rpTarjeta('info_adicional', [['Concepto', '40%', ''], ['Detalle', '', '']], 'Agregar línea', '200px'); ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4" data-rp-tipos="01,03,04,05">
                            <div class="rp-titulo mt-0">Totales</div>
                            <div class="bg-white border rounded p-2 shadow-sm rp-totales">
                                <div class="d-flex justify-content-between align-items-center mb-1 fw-bold border-bottom pb-1">
                                    <label class="text-muted mb-0" for="rp_tot_subtotal">Subtotal sin impuestos</label>
                                    <input type="text" id="rp_tot_subtotal" class="form-control form-control-sm input-detalle rp-tot-input text-end fw-bold" inputmode="decimal">
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="text-muted mb-0" for="rp_tot_descuento">(-) Descuento</label>
                                    <input type="text" id="rp_tot_descuento" class="form-control form-control-sm input-detalle rp-tot-input text-end" inputmode="decimal">
                                </div>
                                <!-- Ingreso manual: desglose del IVA por tarifa calculado con el detalle (solo lectura). -->
                                <div id="rp_tot_ivas"></div>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="text-muted mb-0" for="rp_tot_ice">(+) ICE / IRBPNR</label>
                                    <input type="text" id="rp_tot_ice" class="form-control form-control-sm input-detalle rp-tot-input text-end" inputmode="decimal">
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="text-muted mb-0" for="rp_tot_propina">(+) Propina</label>
                                    <input type="text" id="rp_tot_propina" class="form-control form-control-sm input-detalle rp-tot-input text-end" inputmode="decimal">
                                </div>
                                <hr class="my-1 opacity-25">
                                <div class="d-flex justify-content-between align-items-center bg-light border py-1 px-2 rounded">
                                    <label class="fw-bold text-dark mb-0" for="rp_tot_total" style="font-size:0.8rem;">TOTAL</label>
                                    <input type="text" id="rp_tot_total" class="form-control form-control-sm input-detalle rp-tot-input text-end fw-bold" style="font-size:1rem;" inputmode="decimal">
                                </div>
                            </div>
                        </div>
                    </div>
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
    // Ingreso manual: la empresa es el emisor (ver DescargaPdfService::emisorEmpresa).
    'emisorManual' => $emisorManual ?? new \stdClass(),
    // Modo de cálculo del IVA por serie (001-001 => subtotal | linea_linea), ver DescargasSriController::modosIvaSerie.
    'modosIva' => (object) ($modosIvaSerie ?? []),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>
