<?php

use App\Helpers\PreferenciasHelper;

/**
 * Modal principal del Anexo de Dividendos: informante y origen contable,
 * sección B de utilidades, dividendos distribuidos y validaciones previas a
 * generar el archivo.
 *
 * @var array  $perm
 * @var array  $catalogo
 * @var array  $vistaConfig
 * @var array  $pestanas
 * @var array  $anios       Años seleccionables al crear un período nuevo
 * @var string $rutaModulo
 */
?>
<div class="modal fade" id="modalAdi" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">

            <div class="modal-header bg-light py-2">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-cash-coin text-success me-2"></i>
                    Anexo de Dividendos <span id="adi-titulo-anio" class="text-muted"></span>
                </h5>
                <span id="adi-badge-estado" class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 ms-2">Borrador</span>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <!-- Barra de acciones del documento -->
            <div class="d-flex gap-1 align-items-center flex-wrap px-3 py-2 border-bottom bg-white">
                <?php if ($perm['actualizar']): ?>
                    <button type="button" class="btn btn-sm btn-outline-primary adi-requiere-anexo" id="adi-btn-importar"
                            onclick="ADI_importar()" title="Traer los dividendos desde los asientos contables del año">
                        <i class="bi bi-cloud-download"></i> Importar de contabilidad
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary adi-requiere-anexo" onclick="ADI_recalcular()"
                            title="Recalcular ingreso gravado, retención y la sección B">
                        <i class="bi bi-calculator"></i> Recalcular
                    </button>
                    <div class="vr mx-1"></div>
                <?php endif; ?>
                <button type="button" class="btn btn-sm btn-outline-success adi-requiere-anexo" onclick="ADI_generar()"
                        title="Validar y generar el archivo ADI-aaaa.xml">
                    <i class="bi bi-filetype-xml"></i> Generar anexo
                </button>
                <a class="btn btn-sm btn-outline-dark disabled" id="adi-link-xml" href="#" title="Descargar el XML">
                    <i class="bi bi-download"></i> XML
                </a>
                <a class="btn btn-sm btn-outline-dark disabled" id="adi-link-zip" href="#" title="Descargar el ZIP para el portal">
                    <i class="bi bi-file-zip"></i> ZIP
                </a>
                <span class="ms-auto small text-muted" id="adi-resumen-cabecera"></span>
            </div>

            <div class="modal-body p-0">
                <input type="hidden" id="adi-id" value="">

                <ul class="nav nav-tabs px-3 pt-2" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link active" id="adi-tab-informante" data-bs-toggle="tab"
                           href="#adi-pane-informante" data-bs-target="#adi-pane-informante" role="tab">
                            <i class="bi bi-building me-1"></i> Informante y origen
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" id="adi-tab-utilidades" data-bs-toggle="tab"
                           href="#adi-pane-utilidades" data-bs-target="#adi-pane-utilidades" role="tab">
                            <i class="bi bi-graph-up-arrow me-1"></i> Utilidades
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" id="adi-tab-dividendos" data-bs-toggle="tab"
                           href="#adi-pane-dividendos" data-bs-target="#adi-pane-dividendos" role="tab">
                            <i class="bi bi-people me-1"></i> Dividendos
                            <span class="badge bg-secondary ms-1" id="adi-badge-detalles">0</span>
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" id="adi-tab-validacion" data-bs-toggle="tab"
                           href="#adi-pane-validacion" data-bs-target="#adi-pane-validacion" role="tab">
                            <i class="bi bi-clipboard-check me-1"></i> Validaciones
                            <span class="badge bg-danger ms-1 d-none" id="adi-badge-errores">0</span>
                        </a>
                    </li>
                    <?= PreferenciasHelper::renderDropdownPestanas($pestanas, $vistaConfig ?? [], $rutaModulo) ?>
                </ul>

                <div class="tab-content p-3">

                    <!-- ── Informante y origen contable ───────────────────── -->
                    <div class="tab-pane fade show active" id="adi-pane-informante" role="tabpanel">
                        <div class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label small fw-bold d-block">Año informado</label>
                                <select id="adi-anio" class="form-select form-select-sm">
                                    <?php foreach ($anios as $a): ?>
                                        <option value="<?= (int) $a ?>"><?= (int) $a ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold d-block">Salario básico del año</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light">$</span>
                                    <input type="text" id="adi-sbu-texto" class="form-control bg-light" readonly value="0.00">
                                </div>
                                <!-- Solo se usa para avisar cuando el año no tiene salario básico registrado. -->
                                <div class="form-text adi-nota" id="adi-sbu-ayuda"></div>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold d-block">Identificación</label>
                                <input type="text" id="adi-info-identificacion" class="form-control form-control-sm bg-light" readonly>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold d-block">Tipo de identificación</label>
                                <input type="text" id="adi-info-tipo-id" class="form-control form-control-sm bg-light" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold d-block">Tipo de informante</label>
                                <input type="text" id="adi-info-tipo-informante" class="form-control form-control-sm bg-light" readonly>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small fw-bold d-block">Razón social o apellidos y nombres</label>
                                <input type="text" id="adi-info-razon-social" class="form-control form-control-sm bg-light" readonly>
                                <!-- Solo se usa para avisar si a la empresa le falta el RUC o el tipo de
                                     contribuyente, o si ese tipo no reporta las secciones que genera el módulo. -->
                                <div class="form-text adi-nota" id="adi-info-ayuda"></div>
                            </div>
                        </div>

                        <hr class="my-3">

                        <h6 class="fw-bold small mb-2"><i class="bi bi-diagram-3 me-1 text-primary"></i> Origen contable</h6>
                        <p class="text-muted adi-nota mb-3">
                            Marque las cuentas del plan donde se registra la distribución de dividendos. El módulo lee los
                            asientos <strong>contabilizados</strong> del año en esas cuentas y propone con ellos los
                            beneficiarios y los montos. El <em>lado</em> indica qué columna del asiento representa la
                            distribución: <strong>haber</strong> en cuentas de pasivo (dividendos por pagar) y
                            <strong>debe</strong> en cuentas de patrimonio (resultados acumulados), para que el pago
                            posterior no vuelva a contarse.
                            Las cuentas que el sistema reconoce aparecen ya listadas; si su plan las nombra de otro modo,
                            búsquelas con el buscador: se puede elegir <strong>cualquier cuenta</strong>, tenga o no el
                            mapeo de SuperCías.
                        </p>

                        <div class="row g-3">
                            <div class="col-md-7">
                                <label class="form-label small fw-bold d-block">Cuentas de dividendos distribuidos</label>
                                <div class="position-relative mb-2">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                                        <input type="text" id="adi-buscar-cuenta-div" class="form-control"
                                               placeholder="Buscar cualquier cuenta del plan por código o nombre..."
                                               autocomplete="off">
                                    </div>
                                    <div class="list-group adi-cuenta-lista d-none shadow" id="adi-lista-cuenta-div"></div>
                                </div>
                                <div class="border rounded p-2">
                                    <table class="table table-sm table-borderless mb-0" id="adi-tabla-cuentas-div">
                                        <tbody><tr><td class="text-muted small">Cargando cuentas...</td></tr></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small fw-bold d-block">Cuentas de resultados acumulados</label>
                                <div class="position-relative mb-2">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                                        <input type="text" id="adi-buscar-cuenta-res" class="form-control"
                                               placeholder="Buscar cuenta..." autocomplete="off">
                                    </div>
                                    <div class="list-group adi-cuenta-lista d-none shadow" id="adi-lista-cuenta-res"></div>
                                </div>
                                <div class="border rounded p-2">
                                    <table class="table table-sm table-borderless mb-0" id="adi-tabla-cuentas-res">
                                        <tbody><tr><td class="text-muted small">Cargando cuentas...</td></tr></tbody>
                                    </table>
                                </div>
                                <div class="form-text adi-nota">
                                    Su saldo al 31 de diciembre del año anterior alimenta el campo
                                    &laquo;utilidad de ejercicios anteriores pendiente de distribución&raquo;.
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-md-12">
                                <label class="form-label small fw-bold d-block">Observaciones internas</label>
                                <input type="text" id="adi-observaciones" class="form-control form-control-sm" maxlength="500">
                            </div>
                        </div>
                    </div>

                    <!-- ── Sección B: utilidades ──────────────────────────── -->
                    <div class="tab-pane fade" id="adi-pane-utilidades" role="tabpanel">
                        <p class="text-muted adi-nota">
                            La utilidad del ejercicio se toma del estado de resultados y los dos campos de utilidad
                            distribuida se cuadran con el detalle de dividendos al pulsar <strong>Recalcular</strong>.
                            La utilidad no distribuida es un campo derivado y no se edita.
                        </p>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold d-block">1. Utilidad del ejercicio informado</label>
                                <input type="number" step="0.01" id="adi-b1" class="form-control form-control-sm text-end">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold d-block">2. Utilidad distribuida del ejercicio (distinta de reinversión)</label>
                                <input type="number" step="0.01" id="adi-b2" class="form-control form-control-sm text-end">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold d-block">3. Utilidad reinvertida con derecho a reducción (art. 37 LRTI)</label>
                                <input type="number" step="0.01" id="adi-b3" class="form-control form-control-sm text-end">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold d-block">4. Utilidad reinvertida sin derecho a reducción</label>
                                <input type="number" step="0.01" id="adi-b4" class="form-control form-control-sm text-end">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold d-block">5. Utilidad del ejercicio distribuida por anticipado</label>
                                <input type="number" step="0.01" id="adi-b5" class="form-control form-control-sm text-end">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold d-block">6. Utilidad no distribuida del ejercicio (calculada)</label>
                                <input type="number" step="0.01" id="adi-b6" class="form-control form-control-sm text-end bg-light" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold d-block">7. Utilidad de ejercicios anteriores pendiente al inicio del período</label>
                                <input type="number" step="0.01" id="adi-b7" class="form-control form-control-sm text-end">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold d-block">8. Utilidad distribuida en el período de ejercicios anteriores</label>
                                <input type="number" step="0.01" id="adi-b8" class="form-control form-control-sm text-end">
                            </div>
                        </div>
                        <div class="alert alert-light border mt-3 mb-0 adi-nota" id="adi-cuadre-b"></div>
                    </div>

                    <!-- ── Sección C: dividendos ──────────────────────────── -->
                    <div class="tab-pane fade" id="adi-pane-dividendos" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                            <div class="btn-group btn-group-sm" role="group">
                                <?php if ($perm['crear']): ?>
                                    <button type="button" class="btn btn-outline-primary adi-requiere-anexo" onclick="ADI_modalBeneficiario()">
                                        <i class="bi bi-person-plus"></i> Nuevo beneficiario
                                    </button>
                                    <button type="button" class="btn btn-outline-success adi-requiere-anexo" onclick="ADI_modalDetalle()">
                                        <i class="bi bi-plus-circle"></i> Nuevo dividendo
                                    </button>
                                <?php endif; ?>
                            </div>
                            <span class="small text-muted" id="adi-resumen-dividendos"></span>
                        </div>

                        <div class="adi-tabla-dividendos border rounded">
                            <table class="table table-sm table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:34px;">#</th>
                                        <th>Beneficiario</th>
                                        <th style="width:52px;">Tipo</th>
                                        <th style="width:52px;">País</th>
                                        <th style="width:70px;">Año util.</th>
                                        <th style="width:60px;">Div.</th>
                                        <th style="width:92px;">Fecha</th>
                                        <th class="text-end" style="width:110px;">Distribuido</th>
                                        <th class="text-end" style="width:110px;">Ing. gravado</th>
                                        <th class="text-end" style="width:100px;">Retención</th>
                                        <th style="width:46px;">Pag.</th>
                                        <th style="width:80px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="adi-tbody-dividendos">
                                    <tr><td colspan="12" class="text-center text-muted py-4">Sin dividendos registrados.</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div id="adi-sin-tercero" class="alert alert-warning mt-3 mb-0 d-none adi-nota"></div>
                    </div>

                    <!-- ── Validaciones ───────────────────────────────────── -->
                    <div class="tab-pane fade" id="adi-pane-validacion" role="tabpanel">
                        <p class="text-muted adi-nota">
                            Los <strong>errores</strong> impiden generar el archivo porque el portal del SRI lo
                            rechazaría. Las <strong>advertencias</strong> se pueden presentar, pero conviene revisarlas.
                        </p>
                        <div id="adi-lista-errores"></div>
                        <div id="adi-lista-advertencias"></div>
                        <div class="alert alert-info adi-nota mb-0 mt-3">
                            <i class="bi bi-info-circle me-1"></i>
                            El ingreso gravado y la retención se calculan como sugerencia con la normativa vigente
                            (40 % del dividendo hasta agosto de 2025; monto completo menos la franja exenta desde
                            septiembre de 2025, con el impuesto único del 12 %, 10 % o 14 % según el beneficiario).
                            Dependen de datos que el sistema no conoce &mdash;composición societaria, convenios de doble
                            imposición, residencia efectiva&mdash;, así que revíselos antes de presentar.
                        </div>
                    </div>

                </div>
            </div>

            <div class="modal-footer bg-light py-2 d-flex justify-content-between">
                <?php if ($perm['eliminar']): ?>
                    <button type="button" class="btn btn-outline-danger btn-sm adi-requiere-anexo" onclick="ADI_eliminar()">
                        <i class="bi bi-trash me-1"></i> Eliminar anexo
                    </button>
                <?php else: ?><span></span><?php endif; ?>
                <div class="ms-auto d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    <?php if ($perm['actualizar']): ?>
                        <button type="button" class="btn btn-primary btn-sm px-4" onclick="ADI_guardarCabecera()">
                            <i class="bi bi-check-lg me-1"></i> Guardar
                        </button>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>
