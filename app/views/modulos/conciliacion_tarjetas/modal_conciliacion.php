<?php
/**
 * Modal de trabajo: el cruce propiamente dicho.
 *
 * Pestaña «Conciliación»:
 *   Izquierda  = líneas del estado de cuenta de la procesadora.
 *   Derecha    = cobros con tarjeta registrados en el sistema.
 *   El usuario empareja (o acepta las sugerencias automáticas) y al cerrar elige
 *   a qué forma de cobro (banco) entró el dinero.
 * Pestaña «Asiento contable» (solo con acceso a Contabilidad → Asientos Contables):
 *   el asiento del depósito, con el componente compartido asiento_tab.php.
 *
 * Estructura estándar de modal de documento (§9): barra de acciones al inicio del
 * cuerpo, pestañas configurables por usuario y Eliminar a la izquierda del pie.
 */
$ctarVerAsiento = \App\Helpers\AsientoPestana::puedeVer();
?>
<div class="modal fade" id="modalConciliacion" tabindex="-1" aria-labelledby="modalConciliacionLabel" aria-hidden="true"
     data-bs-backdrop="static">
    <div class="modal-dialog modal-fullscreen-lg-down modal-xl modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title fs-6 fw-bold" id="modalConciliacionLabel">
                    <i class="bi bi-credit-card-2-front me-2"></i><span id="ctar-m-titulo">Nueva conciliación</span>
                </h5>
                <span class="badge d-none ms-2" id="ctar-m-estado" style="font-size:.72rem;"></span>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body p-0">

                <!-- ── Barra de acciones de documento (§9: al inicio del cuerpo) ── -->
                <div class="px-3 py-2 bg-light border-bottom d-flex gap-1 align-items-center flex-wrap" id="ctar-m-acciones">
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="CTAR_abrirCargaArchivo()"
                            id="ctar-btn-cargar" title="Cargar el estado de cuenta de la procesadora">
                        <i class="bi bi-upload me-1"></i>Cargar estado de cuenta
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="CTAR_agregarLineaManual()"
                            id="ctar-btn-linea" title="Agregar una línea a mano">
                        <i class="bi bi-plus-lg me-1"></i>Línea manual
                    </button>
                    <button type="button" class="btn btn-outline-info btn-sm" onclick="CTAR_sugerir()"
                            id="ctar-btn-sugerir" title="Proponer emparejamientos automáticos">
                        <i class="bi bi-magic me-1"></i>Cruzar automáticamente
                    </button>
                    <div class="vr mx-1"></div>
                    <button type="button" class="btn btn-outline-danger btn-sm px-2" onclick="CTAR_pdfConciliacion()"
                            title="Comprobante en PDF"><i class="bi bi-file-earmark-pdf"></i></button>
                    <button type="button" class="btn btn-outline-success btn-sm px-2" onclick="CTAR_excelConciliacion()"
                            title="Detalle en Excel"><i class="bi bi-file-earmark-excel"></i></button>
                    <button type="button" class="btn btn-outline-warning btn-sm d-none" id="ctar-btn-anular" onclick="CTAR_anular()"
                            title="Anular la conciliación: revierte el asiento y los cobros vuelven a pendientes">
                        <i class="bi bi-slash-circle me-1"></i>Anular
                    </button>
                </div>

                <!-- ── Pestañas ── -->
                <div class="d-flex align-items-center bg-light px-3 pt-2">
                    <ul class="nav nav-tabs border-bottom-0 flex-grow-1" id="ctar-m-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button type="button" class="nav-link active py-2 small" id="ctar-tab-cruce-btn"
                                    data-bs-toggle="tab" data-bs-target="#ctar-pane-cruce" role="tab">
                                <i class="bi bi-arrow-left-right me-1"></i>Conciliación
                            </button>
                        </li>
                        <?php if ($ctarVerAsiento): // solo con acceso a Contabilidad → Asientos Contables ?>
                        <li class="nav-item" role="presentation">
                            <button type="button" class="nav-link py-2 small" id="ctar-tab-asiento-btn"
                                    data-bs-toggle="tab" data-bs-target="#ctar-pane-asiento" role="tab">
                                <i class="bi bi-calculator me-1"></i>Asiento contable
                            </button>
                        </li>
                        <?php endif; ?>
                    </ul>
                    <?php if ($ctarVerAsiento): ?>
                    <div class="ms-auto pb-1">
                        <?= \App\Helpers\PreferenciasHelper::renderDropdownPestanas(
                            ['ctar-pane-asiento' => 'Asiento contable'],
                            $vistaConfig ?? [],
                            $rutaModulo
                        ) ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="tab-content border-top">
                <!-- ═══ Pestaña: Conciliación ═══ -->
                <div class="tab-pane fade show active" id="ctar-pane-cruce" role="tabpanel">

                <!-- ── Datos de la conciliación ── -->
                <div class="px-3 py-2 border-bottom">
                    <input type="hidden" id="ctar-m-id">
                    <div class="row g-2">
                        <div class="col-6 col-md-2">
                            <label class="form-label small fw-bold text-muted mb-1">Procesadora</label>
                            <select id="ctar-m-procesadora" class="form-select form-select-sm shadow-none border"></select>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small fw-bold text-muted mb-1">Fecha depósito</label>
                            <input type="date" id="ctar-m-fecha" class="form-control form-control-sm shadow-none border">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small fw-bold text-muted mb-1">Período desde</label>
                            <input type="date" id="ctar-m-desde" class="form-control form-control-sm shadow-none border">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small fw-bold text-muted mb-1">Período hasta</label>
                            <input type="date" id="ctar-m-hasta" class="form-control form-control-sm shadow-none border">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small fw-bold text-muted mb-1">Depositado en</label>
                            <select id="ctar-m-destino" class="form-select form-select-sm shadow-none border"></select>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small fw-bold text-muted mb-1">Neto depositado</label>
                            <input type="number" step="0.01" id="ctar-m-neto" class="form-control form-control-sm shadow-none border text-end"
                                   placeholder="0.00" oninput="CTAR_recalcularDiferencia()">
                        </div>
                    </div>

                    <!-- Aviso del estado contable: por qué se generará (o no) el asiento -->
                    <div class="alert alert-warning py-1 px-2 mt-2 mb-0 small d-none" id="ctar-m-aviso-conta">
                        <i class="bi bi-info-circle me-1"></i><span id="ctar-m-aviso-conta-texto"></span>
                    </div>
                </div>

                <!-- ── El cruce: estado de cuenta ↔ cobros del sistema ── -->
                <div class="row g-0">
                    <!-- Izquierda: estado de cuenta -->
                    <div class="col-lg-7 border-end">
                        <div class="px-3 py-2 bg-light border-bottom d-flex justify-content-between align-items-center">
                            <span class="fw-bold small"><i class="bi bi-filetype-csv me-1 text-primary"></i>Estado de cuenta de la procesadora</span>
                            <span class="small text-muted" id="ctar-m-resumen-lineas">0 líneas</span>
                        </div>
                        <div style="max-height:46vh; overflow:auto;">
                            <table class="table table-sm table-hover mb-0 align-middle" style="min-width:720px;">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Fecha</th>
                                        <th>Autorización</th>
                                        <th class="text-end">Bruto</th>
                                        <th class="text-end">Comisión</th>
                                        <th class="text-end">Retenc.</th>
                                        <th class="text-end">Neto</th>
                                        <th class="text-center">Estado</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="ctar-m-tbody-lineas">
                                    <tr><td colspan="8" class="text-center py-4 text-muted small">
                                        Cargue el estado de cuenta o agregue líneas a mano.
                                    </td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Derecha: cobros del sistema -->
                    <div class="col-lg-5">
                        <div class="px-3 py-2 bg-light border-bottom d-flex justify-content-between align-items-center">
                            <span class="fw-bold small"><i class="bi bi-receipt me-1 text-success"></i>Cobros del sistema</span>
                            <span class="small text-muted" id="ctar-m-resumen-cobros">0 disponibles</span>
                        </div>
                        <div class="px-3 py-2 border-bottom">
                            <input type="search" class="form-control form-control-sm shadow-none border" id="ctar-m-buscar-cobro"
                                   placeholder="Filtrar por cliente, documento o valor..." oninput="CTAR_filtrarCobros(this.value)">
                        </div>
                        <div style="max-height:40vh; overflow:auto;">
                            <table class="table table-sm table-hover mb-0 align-middle" style="min-width:420px;">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Documento</th>
                                        <th>Cliente</th>
                                        <th class="text-end">Monto</th>
                                        <th class="text-center">Días</th>
                                    </tr>
                                </thead>
                                <tbody id="ctar-m-tbody-cobros">
                                    <tr><td colspan="4" class="text-center py-4 text-muted small">Sin cobros pendientes.</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="px-3 py-2 border-top bg-light small text-muted">
                            <i class="bi bi-lightbulb me-1"></i>
                            Seleccione una línea de la izquierda y luego el cobro que le corresponde.
                        </div>
                    </div>
                </div>

                <!-- ── Totales ── -->
                <div class="border-top px-3 py-2 bg-white">
                    <div class="row g-2 text-center small">
                        <div class="col-6 col-md">
                            <div class="text-muted small fw-bold">Bruto conciliado</div>
                            <div class="fw-bold">$<span id="ctar-m-t-bruto">0.00</span></div>
                        </div>
                        <div class="col-6 col-md">
                            <div class="text-muted small fw-bold">Comisión</div>
                            <div class="fw-bold text-secondary">$<span id="ctar-m-t-comision">0.00</span></div>
                        </div>
                        <div class="col-6 col-md">
                            <div class="text-muted small fw-bold">IVA comisión</div>
                            <div class="fw-bold text-secondary">$<span id="ctar-m-t-iva">0.00</span></div>
                        </div>
                        <div class="col-6 col-md">
                            <div class="text-muted small fw-bold">Retenciones</div>
                            <div class="fw-bold text-secondary">$<span id="ctar-m-t-retenciones">0.00</span></div>
                        </div>
                        <div class="col-6 col-md">
                            <div class="text-muted small fw-bold">Neto calculado</div>
                            <div class="fw-bold text-primary">$<span id="ctar-m-t-neto">0.00</span></div>
                        </div>
                        <div class="col-6 col-md">
                            <div class="text-muted small fw-bold">Diferencia</div>
                            <div class="fw-bold" id="ctar-m-t-diferencia-wrap">$<span id="ctar-m-t-diferencia">0.00</span></div>
                        </div>
                    </div>
                </div>
                </div><!-- /ctar-pane-cruce -->

                <?php if ($ctarVerAsiento): ?>
                <!-- ═══ Pestaña: Asiento contable ═══ -->
                <div class="tab-pane fade p-3" id="ctar-pane-asiento" role="tabpanel">
                    <div class="alert alert-light border small d-flex align-items-center gap-2 mb-2 py-2">
                        <i class="bi bi-info-circle text-primary"></i>
                        <span>Asiento del depósito: <em>Banco</em> (neto) + comisión, IVA y retenciones en el Debe, contra la
                            <em>cuenta puente</em> de la forma de cobro (bruto) en el Haber. Se genera al conciliar y cerrar.</span>
                    </div>
                    <?php $prefijo = 'ctar'; require MVC_APP . '/views/partials/asiento_tab.php'; ?>
                </div><!-- /ctar-pane-asiento -->
                <?php endif; ?>
                </div><!-- /tab-content -->
            </div>

            <div class="modal-footer justify-content-between bg-light border-top p-2">
                <div>
                    <?php if (!empty($perm['eliminar'])): ?>
                        <button type="button" class="btn btn-outline-danger btn-sm px-3 d-none" id="ctar-btn-eliminar" onclick="CTAR_eliminar()">
                            <i class="bi bi-trash3 me-1"></i>Eliminar
                        </button>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i>Cerrar
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="ctar-btn-guardar" onclick="CTAR_guardar()">
                        <i class="bi bi-save me-1"></i>Guardar
                    </button>
                    <button type="button" class="btn btn-success btn-sm px-3 shadow-sm" id="ctar-btn-conciliar" onclick="CTAR_cerrarConciliacion()">
                        <i class="bi bi-check2-circle me-1"></i>Conciliar y cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- ═══ Sub-modal: cargar el estado de cuenta ═══ -->
<div class="modal fade" id="modalCargaEstado" tabindex="-1" aria-hidden="true" style="z-index:5080;">
    <div class="modal-dialog modal-dialog-centered" style="max-width:520px;">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-upload me-2"></i>Cargar estado de cuenta</h5>
                <button type="button" class="btn-close" aria-label="Cerrar" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="mb-2">
                    <label class="form-label small fw-bold text-muted mb-1">Perfil de lectura</label>
                    <select id="ctar-carga-perfil" class="form-select form-select-sm shadow-none border"></select>
                    <div class="form-text" style="font-size:.7rem;">
                        El formato del archivo cambia según la procesadora y el banco. Si el suyo no está,
                        cree un perfil en <strong>Configuración → Perfiles</strong>.
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold text-muted mb-1">Archivo (Excel, CSV o PDF)</label>
                    <input type="file" id="ctar-carga-archivo" class="form-control form-control-sm shadow-none border"
                           accept=".xls,.xlsx,.csv,.pdf">
                </div>
                <div class="alert alert-warning py-1 px-2 small mb-0">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Cargar un archivo reemplaza las líneas y los cruces que ya tenga esta conciliación.
                </div>
            </div>
            <div class="modal-footer bg-light border-top p-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="CTAR_importar()">
                    <i class="bi bi-upload me-1"></i>Cargar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══ Sub-modal: línea manual / edición de línea ═══ -->
<div class="modal fade" id="modalLineaTarjeta" tabindex="-1" aria-hidden="true" style="z-index:5080;">
    <div class="modal-dialog modal-dialog-centered" style="max-width:560px;">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-pencil-square me-2"></i><span id="ctar-linea-titulo">Línea del estado de cuenta</span></h5>
                <button type="button" class="btn-close" aria-label="Cerrar" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <input type="hidden" id="ctar-linea-id">
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small fw-bold text-muted mb-1">Fecha</label>
                        <input type="date" id="ctar-linea-fecha" class="form-control form-control-sm shadow-none border">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold text-muted mb-1">Tipo</label>
                        <select id="ctar-linea-tipo" class="form-select form-select-sm shadow-none border">
                            <option value="transaccion">Transacción individual</option>
                            <option value="deposito">Depósito consolidado</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold text-muted mb-1">Autorización</label>
                        <input type="text" id="ctar-linea-autorizacion" class="form-control form-control-sm shadow-none border" maxlength="60">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold text-muted mb-1">Referencia</label>
                        <input type="text" id="ctar-linea-referencia" class="form-control form-control-sm shadow-none border" maxlength="120">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold text-muted mb-1">Descripción</label>
                        <input type="text" id="ctar-linea-descripcion" class="form-control form-control-sm shadow-none border" maxlength="500">
                    </div>
                    <div class="col-4">
                        <label class="form-label small fw-bold text-muted mb-1">Bruto</label>
                        <input type="number" step="0.01" id="ctar-linea-bruto" class="form-control form-control-sm shadow-none border text-end"
                               oninput="CTAR_recalcularNetoLinea()">
                    </div>
                    <div class="col-4">
                        <label class="form-label small fw-bold text-muted mb-1">Comisión</label>
                        <input type="number" step="0.01" id="ctar-linea-comision" class="form-control form-control-sm shadow-none border text-end"
                               oninput="CTAR_recalcularNetoLinea()">
                    </div>
                    <div class="col-4">
                        <label class="form-label small fw-bold text-muted mb-1">IVA comisión</label>
                        <input type="number" step="0.01" id="ctar-linea-iva" class="form-control form-control-sm shadow-none border text-end"
                               oninput="CTAR_recalcularNetoLinea()">
                    </div>
                    <div class="col-4">
                        <label class="form-label small fw-bold text-muted mb-1">Retención renta</label>
                        <input type="number" step="0.01" id="ctar-linea-retir" class="form-control form-control-sm shadow-none border text-end"
                               oninput="CTAR_recalcularNetoLinea()">
                    </div>
                    <div class="col-4">
                        <label class="form-label small fw-bold text-muted mb-1">Retención IVA</label>
                        <input type="number" step="0.01" id="ctar-linea-retiva" class="form-control form-control-sm shadow-none border text-end"
                               oninput="CTAR_recalcularNetoLinea()">
                    </div>
                    <div class="col-4">
                        <label class="form-label small fw-bold text-muted mb-1">Otros descuentos</label>
                        <input type="number" step="0.01" id="ctar-linea-otros" class="form-control form-control-sm shadow-none border text-end"
                               oninput="CTAR_recalcularNetoLinea()">
                    </div>
                    <div class="col-12">
                        <div class="p-2 border rounded-3 bg-light d-flex justify-content-between">
                            <span class="small fw-bold text-muted">Neto de la línea</span>
                            <span class="fw-bold text-primary">$<span id="ctar-linea-neto">0.00</span></span>
                        </div>
                    </div>
                </div>
                <div class="form-text mt-2" style="font-size:.7rem;">
                    Las retenciones se digitan tal como vienen en el comprobante de la procesadora; el sistema no las calcula.
                </div>
            </div>
            <div class="modal-footer bg-light border-top p-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="CTAR_guardarLinea()">
                    <i class="bi bi-save me-1"></i>Guardar línea
                </button>
            </div>
        </div>
    </div>
</div>
