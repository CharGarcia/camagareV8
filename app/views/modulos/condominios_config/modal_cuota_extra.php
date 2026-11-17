<?php
/** Pestaña Cuotas extraordinarias: ventana de una cuota extraordinaria a plazos (datos, plan por
 *  inmueble, cuotas y emisión aparte). JS: public/js/modulos/condominios_cuotas_extra.js (objeto CEX).
 *  Diseño: docs/diseno/condominios-cuotas-extraordinarias.md.
 *  @var array $perm @var string $rutaModulo @var string $base @var array $vistaConfig */
use App\Helpers\InformacionRegistro;

$pestanasCex = ['cex-pane-general' => 'General', 'cex-pane-inmuebles' => 'Inmuebles', 'cex-pane-cuotas' => 'Cuotas'];
$pestanasCex += InformacionRegistro::pestanaConfigurable('cex');
// Botón «nuevo producto o servicio» (crear al vuelo, CLAUDE.md §9): permiso de Productos, no de este módulo.
$permProductosCex = \App\Helpers\Permisos::porRuta('modulos/productos');
?>
<style>
    #modalCuotaExtra label { font-size: .8rem; font-weight: 600; color: #495057; margin-bottom: 2px; }
    #modalCuotaExtra .form-text { font-size: .7rem; }
    #modalCuotaExtra td.p-0 input, #modalCuotaExtra td.p-0 select { padding: 0 4px; height: 20px; font-size: .78rem; }
    .cex-scroll { max-height: 46vh; overflow: auto; }
    .cex-scroll thead th { position: sticky; top: 0; z-index: 2; background: #f8f9fa; }
</style>
<div class="modal fade modal-cond" id="modalCuotaExtra" tabindex="-1" data-bs-backdrop="static" style="z-index:1060">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" style="max-width:1300px">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-2">
                <h5 class="modal-title fw-bold fs-6"><i class="bi bi-cash-stack text-primary me-2"></i><span id="cex-titulo">Cuota extraordinaria</span>
                    <span class="badge bg-secondary bg-opacity-10 text-secondary border ms-2 d-none" id="cex-estado-badge"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <input type="hidden" id="cex_id">
                <!-- Barra de acciones del documento -->
                <div class="d-flex gap-1 align-items-center flex-wrap border-bottom pb-2 mb-2" id="cex-acciones">
                    <span class="d-flex gap-1 align-items-center" id="cex-acciones-doc">
                        <button type="button" class="btn btn-sm btn-outline-danger" title="PDF del estado por inmueble" onclick="CEX.exportar('pdf')"><i class="bi bi-file-earmark-pdf"></i></button>
                        <button type="button" class="btn btn-sm btn-outline-success" title="Excel del estado por inmueble" onclick="CEX.exportar('excel')"><i class="bi bi-file-earmark-excel"></i></button>
                        <?php if (!empty($perm['actualizar'])): ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="cex-btn-cerrar" title="Cerrar: no se emiten más cuotas" onclick="CEX.cerrar()"><i class="bi bi-lock me-1"></i>Cerrar</button>
                        <?php endif; ?>
                        <div class="vr mx-1"></div>
                    </span>
                    <?php if (!empty($permProductosCex['crear'])): ?>
                        <button type="button" class="btn btn-outline-primary btn-sm px-2" id="cex-btn-nuevo-producto" onclick="CEX.nuevoProducto()" title="Registrar nuevo producto o servicio"><i class="bi bi-box-seam fs-6"></i></button>
                    <?php endif; ?>
                    <div class="ms-auto d-flex flex-wrap gap-3 small" id="cex-kpis"></div>
                </div>

                <div class="d-flex align-items-center">
                    <ul class="nav nav-tabs flex-grow-1" role="tablist">
                        <li class="nav-item"><a class="nav-link active py-1 small" data-bs-toggle="tab" href="#cex-pane-general" role="tab"><i class="bi bi-card-text me-1"></i>General</a></li>
                        <li class="nav-item"><a class="nav-link py-1 small" data-bs-toggle="tab" href="#cex-pane-inmuebles" role="tab" id="cex-tab-inmuebles"><i class="bi bi-door-open me-1"></i>Inmuebles</a></li>
                        <li class="nav-item"><a class="nav-link py-1 small" data-bs-toggle="tab" href="#cex-pane-cuotas" role="tab" id="cex-tab-cuotas"><i class="bi bi-calendar3 me-1"></i>Cuotas</a></li>
                        <?= InformacionRegistro::boton('cex') ?>
                    </ul>
                    <div class="flex-shrink-0"><?= \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasCex, $vistaConfig ?? [], basename($rutaModulo)) ?></div>
                </div>

                <div class="tab-content pt-2">
                    <!-- ══ General ══ -->
                    <div class="tab-pane fade show active" id="cex-pane-general" role="tabpanel">
                        <div class="row g-2">
                            <div class="col-md-5">
                                <label for="cex_nombre">Nombre *</label>
                                <input type="text" class="form-control form-control-sm" id="cex_nombre" maxlength="150" placeholder="Pintura del edificio">
                            </div>
                            <div class="col-md-4 position-relative">
                                <label for="cex_producto_txt">Concepto (servicio de Productos) *</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white"><i class="bi bi-box-seam"></i></span>
                                    <input type="text" class="form-control" id="cex_producto_txt" placeholder="Buscar servicio…" autocomplete="off">
                                    <input type="hidden" id="cex_id_producto">
                                </div>
                                <div class="list-group position-absolute w-100 shadow" id="cex_producto_dd" style="display:none;z-index:1090;max-height:220px;overflow-y:auto"></div>
                                <div class="form-text">Su nombre, IVA y cuenta contable salen del producto.</div>
                            </div>
                            <div class="col-md-2">
                                <label for="cex_acta">Acta</label>
                                <input type="text" class="form-control form-control-sm" id="cex_acta" maxlength="100" placeholder="05-2026">
                            </div>
                            <div class="col-md-1">
                                <label for="cex_fecha_acta">Fecha</label>
                                <input type="date" class="form-control form-control-sm px-1" id="cex_fecha_acta">
                            </div>

                            <div class="col-md-2">
                                <label for="cex_meta_total">Monto aprobado *</label>
                                <input type="number" class="form-control form-control-sm text-end" id="cex_meta_total" step="0.01" min="0" placeholder="0.00">
                            </div>
                            <div class="col-md-2">
                                <label for="cex_calculo">Reparto *</label>
                                <select class="form-select form-select-sm" id="cex_calculo">
                                    <option value="iguales">Partes iguales</option>
                                    <option value="porcentaje">Por % de alícuota</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="cex_plazos">Cuotas permitidas *</label>
                                <input type="text" class="form-control form-control-sm" id="cex_plazos" placeholder="1, 6, 12, 24" value="1, 6, 12, 24">
                                <div class="form-text">Cada condómino elige una.</div>
                            </div>
                            <div class="col-md-2">
                                <label for="cex_plazo_defecto">Cuotas por defecto *</label>
                                <input type="number" class="form-control form-control-sm text-end" id="cex_plazo_defecto" min="1" max="120" step="1" value="24">
                                <div class="form-text">Se suma a las permitidas si no está.</div>
                            </div>
                            <div class="col-md-2">
                                <label for="cex_primer_periodo">Primera cuota *</label>
                                <input type="month" class="form-control form-control-sm" id="cex_primer_periodo">
                            </div>
                            <div class="col-md-2">
                                <label for="cex_pagador_defecto">Paga *</label>
                                <select class="form-select form-select-sm" id="cex_pagador_defecto">
                                    <option value="propietario">El propietario</option>
                                    <option value="arrendatario">El arrendatario (si tiene)</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="cex_donde_cobro">Dónde sale cada cuota *</label>
                                <select class="form-select form-select-sm" id="cex_donde_cobro">
                                    <option value="junto">En el recibo/factura de la alícuota (Suscripciones)</option>
                                    <option value="aparte">En un documento aparte</option>
                                </select>
                                <div class="form-text">Si el inmueble no tiene suscripción o la alícuota la paga otra persona, esa cuota sale aparte.</div>
                            </div>
                            <div class="col-md-2">
                                <label for="cex_tipo_comprobante">Documento propio *</label>
                                <select class="form-select form-select-sm" id="cex_tipo_comprobante">
                                    <option value="recibo">Recibo de venta</option>
                                    <option value="factura">Factura</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="cex_id_punto_emision">Serie *</label>
                                <select class="form-select form-select-sm" id="cex_id_punto_emision"></select>
                            </div>
                            <div class="col-md-4">
                                <label for="cex_id_forma_anticipo">Forma de cobro de anticipos</label>
                                <select class="form-select form-select-sm" id="cex_id_forma_anticipo"></select>
                                <div class="form-text">Con ella se aplica el anticipo de clientes al abono inicial.</div>
                            </div>
                            <div class="col-12">
                                <label for="cex_descripcion">Descripción</label>
                                <textarea class="form-control form-control-sm" id="cex_descripcion" rows="2" placeholder="Lo que aprobó la asamblea…"></textarea>
                            </div>
                        </div>
                        <div class="small text-muted mt-2 d-none" id="cex-bloqueo-aviso"><i class="bi bi-lock me-1"></i>Con planes ya activados, el monto, el reparto, el concepto y la primera cuota quedan fijos.</div>
                    </div>

                    <!-- ══ Inmuebles: planes activos + agregar ══ -->
                    <div class="tab-pane fade" id="cex-pane-inmuebles" role="tabpanel">
                        <div id="cex-sin-guardar" class="alert alert-info py-2 small">Guarde primero la cuota extraordinaria.</div>
                        <div id="cex-planes-wrap" class="d-none">
                            <h6 class="fw-bold small mb-1"><i class="bi bi-list-check me-1 text-primary"></i>Planes por inmueble</h6>
                            <div class="border rounded-3 bg-white cex-scroll mb-3">
                                <table class="table table-sm table-hover mb-0 small">
                                    <thead><tr><th class="ps-2">Inmueble</th><th>Paga</th><th class="text-end">Asignado</th><th class="text-end">Anticipo</th><th class="text-end">Cuota</th><th class="text-center">Emitidas</th><th class="text-end">Emitido</th><th class="text-end">Cobrado</th><th class="text-end">Saldo</th><th>Estado</th><th class="pe-2"></th></tr></thead>
                                    <tbody id="cex-planes-body"></tbody>
                                </table>
                            </div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <h6 class="fw-bold small mb-0"><i class="bi bi-plus-circle me-1 text-primary"></i>Inmuebles sin plan</h6>
                                <button type="button" class="btn btn-outline-primary btn-sm ms-auto" onclick="CEX.calcular()"><i class="bi bi-calculator me-1"></i>Calcular reparto</button>
                            </div>
                            <div id="cex-prev" class="d-none">
                                <div class="d-flex flex-wrap gap-3 small mb-1" id="cex-prev-tot"></div>
                                <div class="border rounded-3 bg-white cex-scroll">
                                    <table class="table table-sm mb-0 small">
                                        <thead><tr><th class="ps-2" style="width:26px"><input type="checkbox" class="form-check-input" id="cex-prev-todos" checked onchange="CEX.prevTodos(this.checked)"></th>
                                            <th>Inmueble</th><th class="text-end">% alíc.</th><th style="width:130px">Paga</th><th class="text-end" style="width:110px">Asignado</th>
                                            <th class="text-end" style="width:110px">Anticipo</th><th class="text-end">Disponible</th><th style="width:80px">Cuotas</th><th class="text-end">Cuota</th><th class="pe-2">Nota</th></tr></thead>
                                        <tbody id="cex-prev-body"></tbody>
                                    </table>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <span class="small text-muted">Asignado en blanco = se reparte. Escriba un monto para fijarlo a mano.</span>
                                    <?php if (!empty($perm['crear'])): ?>
                                        <button type="button" class="btn btn-primary btn-sm px-3" id="cex-btn-activar" onclick="CEX.activar()"><i class="bi bi-check2-circle me-1"></i>Activar planes</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ══ Cuotas: cronograma y emisión aparte ══ -->
                    <div class="tab-pane fade" id="cex-pane-cuotas" role="tabpanel">
                        <div class="border rounded-3 p-2 bg-light mb-2">
                            <div class="d-flex flex-wrap align-items-end gap-2">
                                <div style="width:150px">
                                    <label for="cex_emi_periodo" class="d-block">Emitir aparte hasta</label>
                                    <input type="month" class="form-control form-control-sm" id="cex_emi_periodo" value="<?= date('Y-m') ?>">
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="CEX.aparteVista()"><i class="bi bi-eye me-1"></i>Vista previa</button>
                                <span class="small text-muted">Cuotas «aparte» y las que no pueden ir con la alícuota. Las «junto» las agrega Suscripciones al emitir.</span>
                            </div>
                            <div id="cex-aparte" class="d-none mt-2">
                                <div class="border rounded-3 bg-white cex-scroll">
                                    <table class="table table-sm mb-0 small">
                                        <thead><tr><th class="ps-2" style="width:26px"><input type="checkbox" class="form-check-input" id="cex-aparte-todos" checked onchange="CEX.aparteTodos(this.checked)"></th><th>Cliente</th><th>Inmueble</th><th>Cuotas</th><th class="text-end">Valor</th><th>Correo</th><th class="pe-2">Nota</th></tr></thead>
                                        <tbody id="cex-aparte-body"></tbody>
                                    </table>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <div class="form-check form-switch small mb-0">
                                        <input class="form-check-input" type="checkbox" id="cex_emi_correo">
                                        <label class="form-check-label" for="cex_emi_correo">Enviar cada documento por correo</label>
                                    </div>
                                    <?php if (!empty($perm['crear'])): ?>
                                        <button type="button" class="btn btn-primary btn-sm px-3" id="cex-btn-emitir" onclick="CEX.aparteEmitir()"><i class="bi bi-send me-1"></i>Emitir</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="border rounded-3 bg-white cex-scroll">
                            <table class="table table-sm table-hover mb-0 small">
                                <thead><tr><th class="ps-2">Mes</th><th>Inmueble</th><th>Cuota</th><th class="text-end">Valor</th><th>Paga</th><th>Sale</th><th>Estado</th><th>Documento</th><th class="text-end">Cobrado</th><th class="pe-2"></th></tr></thead>
                                <tbody id="cex-cuotas-body"></tbody>
                            </table>
                        </div>
                    </div>

                    <?= InformacionRegistro::panel('cex', $rutaModulo, 'cex_id') ?>
                </div>
            </div>
            <div class="modal-footer justify-content-between bg-light border-top p-2">
                <div>
                    <?php if (!empty($perm['eliminar'])): ?>
                        <button type="button" class="btn btn-outline-danger btn-sm px-3 d-none" id="cex-btn-eliminar" onclick="CEX.eliminar()"><i class="bi bi-trash3 me-1"></i>Eliminar</button>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>Cerrar</button>
                    <?php if (!empty($perm['crear']) || !empty($perm['actualizar'])): ?>
                        <button type="button" class="btn btn-primary btn-sm px-4" id="cex-btn-guardar" onclick="CEX.guardar()"><i class="bi bi-check2-circle me-1"></i>Guardar</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
