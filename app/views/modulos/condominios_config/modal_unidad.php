<?php
/** Modal del inmueble (pestaña Condóminos de Configuración de condominios).
 *  @var array $perm @var array $vistaConfig @var string $rutaModulo @var array $tiposUnidad @var array $metodos */
$pestanasUni = [
    'pane-uni-general'     => 'General',
    'pane-uni-historial'   => 'Propietarios',
    'pane-uni-restriccion' => 'Áreas comunes',
    'pane-uni-suscripcion' => 'Expensa',
];
?>
<style>
    .modal-cond label { font-size: .83rem; font-weight: 600; color: #495057; margin-bottom: 3px !important; }
    .modal-cond .nav-tabs .nav-link { font-size: .875rem; }
    .modal-cond .form-text { font-size: .7rem; }
</style>

<div class="modal fade modal-cond" id="modalUnidad" tabindex="-1" data-bs-backdrop="static" style="z-index:1060">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <form id="formUnidad" novalidate>
                <div class="modal-header bg-light py-2">
                    <h5 class="modal-title fw-bold fs-6">
                        <i class="bi bi-door-open text-primary me-2"></i>
                        <span id="uni-titulo">Nuevo inmueble</span>
                        <span id="uni-badge-estado" class="badge ms-2 d-none"></span>
                        <span id="uni-badge-restr" class="badge ms-1 bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 d-none"><i class="bi bi-slash-circle me-1"></i>Áreas comunes restringidas</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body p-0">
                    <input type="hidden" id="uni_id" name="id" value="">

                    <!-- Pestañas -->
                    <div class="d-flex align-items-center bg-light px-3 pt-2">
                        <ul class="nav nav-tabs border-bottom-0 flex-grow-1 flex-nowrap" role="tablist">
                            <li class="nav-item"><a class="nav-link active py-2 small" id="uni-tab-general-btn" data-bs-toggle="tab" href="#pane-uni-general" role="tab"><i class="bi bi-card-text me-1"></i>General</a></li>
                            <li class="nav-item"><a class="nav-link py-2 small" id="uni-tab-historial-btn" data-bs-toggle="tab" href="#pane-uni-historial" role="tab"><i class="bi bi-people me-1"></i>Propietarios</a></li>
                            <li class="nav-item"><a class="nav-link py-2 small" id="uni-tab-restriccion-btn" data-bs-toggle="tab" href="#pane-uni-restriccion" role="tab"><i class="bi bi-slash-circle me-1"></i>Áreas comunes</a></li>
                            <li class="nav-item"><a class="nav-link py-2 small" id="uni-tab-suscripcion-btn" data-bs-toggle="tab" href="#pane-uni-suscripcion" role="tab"><i class="bi bi-arrow-repeat me-1"></i>Expensa</a></li>
                        </ul>
                        <div class="pb-1 flex-shrink-0">
                            <?= \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasUni, $vistaConfig ?? [], basename($rutaModulo)) ?>
                        </div>
                    </div>
                    <div class="border-bottom bg-light"></div>

                    <div class="tab-content border-top px-3 py-3">

                        <!-- ══ General ══ -->
                        <div class="tab-pane fade show active" id="pane-uni-general" role="tabpanel">
                            <!-- Fila 1: identificación del inmueble (2+4+2+2+2 = 12) -->
                            <div class="row g-2">
                                <div class="col-md-2">
                                    <label for="uni_codigo">Código *</label>
                                    <input type="text" class="form-control form-control-sm" id="uni_codigo" name="codigo" maxlength="30" required placeholder="DPTO-302">
                                </div>
                                <div class="col-md-4">
                                    <label for="uni_nombre">Nombre</label>
                                    <input type="text" class="form-control form-control-sm" id="uni_nombre" name="nombre" maxlength="120" placeholder="Dpto 302 (si se deja vacío, se usa el código)">
                                </div>
                                <div class="col-md-2">
                                    <label for="uni_tipo" class="d-flex align-items-center">Tipo * <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito($rutaModulo, 'uni_tipo', 'tipo') ?></label>
                                    <select class="form-select form-select-sm" id="uni_tipo" name="tipo">
                                        <?php foreach ($tiposUnidad as $k => $v): ?><option value="<?= $k ?>"><?= htmlspecialchars($v) ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="uni_torre_bloque" class="d-flex align-items-center">Torre / Bloque <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito($rutaModulo, 'uni_torre_bloque', 'torre_bloque') ?></label>
                                    <input type="text" class="form-control form-control-sm" id="uni_torre_bloque" name="torre_bloque" maxlength="60" placeholder="Torre B">
                                </div>
                                <div class="col-md-2">
                                    <label for="uni_piso">Piso</label>
                                    <input type="text" class="form-control form-control-sm" id="uni_piso" name="piso" maxlength="20" placeholder="3">
                                </div>
                            </div>

                            <!-- Fila 2: propietario / arrendatario / pagador (5+5+2 = 12) -->
                            <div class="row g-2 mt-0">
                                <div class="col-md-5 position-relative">
                                    <label for="uni_propietario_txt">Propietario * <span class="text-muted fw-normal">(cliente)</span></label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
                                        <input type="text" class="form-control" id="uni_propietario_txt" placeholder="Buscar por nombre o cédula/RUC…" autocomplete="off">
                                        <input type="hidden" id="uni_id_propietario" name="id_propietario">
                                        <?php if (\App\Helpers\Permisos::puedeCrear('modulos/clientes')): ?>
                                            <a class="btn btn-outline-primary" href="<?= rtrim(BASE_URL, '/') ?>/modulos/clientes" target="_blank" title="Registrar un cliente nuevo (se abre Clientes)"><i class="bi bi-person-plus"></i></a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="list-group position-absolute w-100 shadow cond-dropdown" id="uni_propietario_dd" style="display:none"></div>
                                    <div class="form-text">Siempre se guarda; es el responsable legal de las expensas.</div>
                                </div>
                                <div class="col-md-5 position-relative">
                                    <label for="uni_arrendatario_txt">Arrendatario <span class="text-muted fw-normal">(opcional)</span></label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white"><i class="bi bi-person-badge"></i></span>
                                        <input type="text" class="form-control" id="uni_arrendatario_txt" placeholder="Buscar por nombre o cédula/RUC…" autocomplete="off">
                                        <input type="hidden" id="uni_id_arrendatario" name="id_arrendatario">
                                    </div>
                                    <div class="list-group position-absolute w-100 shadow cond-dropdown" id="uni_arrendatario_dd" style="display:none"></div>
                                    <div class="form-text">Backspace o Supr limpia la selección.</div>
                                </div>
                                <div class="col-md-2">
                                    <label for="uni_pagador">Paga el *</label>
                                    <select class="form-select form-select-sm" id="uni_pagador" name="pagador">
                                        <option value="propietario">Propietario</option>
                                        <option value="arrendatario">Arrendatario</option>
                                    </select>
                                    <div class="form-text">A su nombre sale el recibo/factura.</div>
                                </div>
                            </div>
                            <div class="row g-2 mt-0 d-none" id="uni-wrap-cambio-personas">
                                <div class="col-md-3">
                                    <label for="uni_propietario_desde">Rige desde *</label>
                                    <input type="date" class="form-control form-control-sm" id="uni_propietario_desde" name="propietario_desde" value="<?= date('Y-m-d') ?>">
                                </div>
                                <div class="col-md-9">
                                    <label for="uni_propietario_observacion">Observación del cambio</label>
                                    <input type="text" class="form-control form-control-sm" id="uni_propietario_observacion" name="propietario_observacion" maxlength="300" placeholder="Compraventa, nuevo contrato de arriendo…">
                                </div>
                            </div>

                            <!-- Fila 3: cómo se calcula la cuota (2+2+2+2). Comprobante, serie y día de cobro viven en la suscripción del inmueble. -->
                            <div class="row g-2 mt-0">
                                <div class="col-md-2">
                                    <label for="uni_area_m2">Área m²</label>
                                    <input type="number" class="form-control form-control-sm text-end" id="uni_area_m2" name="area_m2" step="0.01" min="0" value="0">
                                </div>
                                <div class="col-md-2">
                                    <label for="uni_alicuota_pct">Alícuota %</label>
                                    <input type="number" class="form-control form-control-sm text-end" id="uni_alicuota_pct" name="alicuota_pct" step="0.000001" min="0" max="100" value="0">
                                </div>
                                <div class="col-md-2">
                                    <label for="uni_metodo_alicuota" class="d-flex align-items-center">Método <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito($rutaModulo, 'uni_metodo_alicuota', 'metodo_alicuota') ?></label>
                                    <select class="form-select form-select-sm" id="uni_metodo_alicuota" name="metodo_alicuota" onchange="COND.onMetodo()">
                                        <option value="">Del condominio</option>
                                        <?php foreach ($metodos as $k => $v): ?><option value="<?= $k ?>"><?= htmlspecialchars($v) ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="uni_monto_manual">Monto manual</label>
                                    <input type="number" class="form-control form-control-sm text-end" id="uni_monto_manual" name="monto_manual" step="0.01" min="0" placeholder="Solo si es manual">
                                </div>
                            </div>

                            <!-- Fila 4: fondo propio, estado, cuota estimada (2+2+8) -->
                            <div class="row g-2 mt-0">
                                <div class="col-md-2">
                                    <label for="uni_fondo_reserva_valor_propio">Fondo reserva propio</label>
                                    <input type="number" class="form-control form-control-sm text-end" id="uni_fondo_reserva_valor_propio" name="fondo_reserva_valor_propio" step="0.01" min="0" placeholder="Regla del condominio">
                                </div>
                                <div class="col-md-2">
                                    <label for="uni_estado">Estado</label>
                                    <select class="form-select form-select-sm" id="uni_estado" name="estado">
                                        <option value="activo">Activo (emite)</option>
                                        <option value="inactivo">Inactivo (no emite)</option>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <label>Cuota ordinaria estimada</label>
                                    <div class="form-control form-control-sm bg-light" id="uni-cuota-estimada">—</div>
                                </div>
                            </div>

                            <div class="row g-2 mt-0">
                                <div class="col-12">
                                    <label for="uni_observaciones">Observaciones</label>
                                    <textarea class="form-control form-control-sm" id="uni_observaciones" name="observaciones" rows="2"></textarea>
                                </div>
                                <div class="col-12 small text-muted" id="uni-registro"></div>
                            </div>
                        </div>

                        <!-- ══ Propietarios (historial) ══ -->
                        <div class="tab-pane fade" id="pane-uni-historial" role="tabpanel">
                            <p class="small text-muted mb-2"><i class="bi bi-info-circle me-1"></i>La deuda es del inmueble: cada cambio de propietario, arrendatario o pagador abre una fila nueva. Para registrar un cambio, edite las personas en <b>General</b> e indique desde cuándo rige.</p>
                            <div class="border rounded-3 bg-white">
                                <table class="table table-sm table-hover mb-0 small">
                                    <thead class="table-light"><tr><th class="ps-2">Desde</th><th>Hasta</th><th>Propietario</th><th>Arrendatario</th><th>Paga</th><th class="pe-2">Observación</th></tr></thead>
                                    <tbody id="uni-historial-body"><tr><td colspan="6" class="text-center text-muted py-3">—</td></tr></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- ══ Áreas comunes ══ -->
                        <div class="tab-pane fade" id="pane-uni-restriccion" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <div class="border rounded-3 p-3 bg-light" id="uni-restr-estado">
                                        <div class="fw-bold small mb-2"><i class="bi bi-slash-circle me-1"></i>Restricción de uso de áreas comunes</div>
                                        <div class="small text-muted mb-2" id="uni-restr-texto">Sin restricción.</div>
                                        <?php if (!empty($perm['actualizar'])): ?>
                                            <div id="uni-restr-form">
                                                <label for="restr_fecha">Fecha de notificación *</label>
                                                <input type="date" class="form-control form-control-sm mb-2" id="restr_fecha" value="<?= date('Y-m-d') ?>">
                                                <label for="restr_motivo">Motivo notificado *</label>
                                                <textarea class="form-control form-control-sm mb-2" id="restr_motivo" rows="2" placeholder="Expensas vencidas de julio a septiembre 2026, notificadas el…"></textarea>
                                                <button type="button" class="btn btn-outline-danger btn-sm" id="uni-btn-restringir" onclick="COND.restringir(true)"><i class="bi bi-slash-circle me-1"></i>Marcar restricción</button>
                                                <button type="button" class="btn btn-outline-success btn-sm d-none" id="uni-btn-levantar" onclick="COND.restringir(false)"><i class="bi bi-check-circle me-1"></i>Levantar restricción</button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-7">
                                    <div class="border rounded-3 bg-white">
                                        <table class="table table-sm table-hover mb-0 small">
                                            <thead class="table-light"><tr><th class="ps-2">Fecha</th><th>Acción</th><th>Motivo</th><th class="pe-2">Usuario</th></tr></thead>
                                            <tbody id="uni-restr-body"><tr><td colspan="4" class="text-center text-muted py-3">—</td></tr></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ══ Expensa (suscripción) ══ -->
                        <div class="tab-pane fade" id="pane-uni-suscripcion" role="tabpanel">
                            <p class="small text-muted mb-2"><i class="bi bi-info-circle me-1"></i>La cuota del inmueble se emite desde <b>Suscripciones</b>. Al crear la suscripción del pagador elija este inmueble, o enlace aquí una suscripción que ya tenga (por ejemplo, un programado migrado del sistema anterior). Sus recibos o facturas llevarán el detalle del inmueble en la información adicional.</p>
                            <div id="uni-susc-actual" class="border rounded-3 p-3 bg-light small mb-3">Guardel inmueble para ver su expensa.</div>
                            <div id="uni-susc-enlazables"></div>
                        </div>

                    </div>
                </div>

                <div class="modal-footer justify-content-between bg-light border-top p-2">
                    <div>
                        <?php if (!empty($perm['eliminar'])): ?>
                            <button type="button" class="btn btn-outline-danger btn-sm px-3 d-none" id="uni-btn-eliminar" onclick="COND.eliminar()">
                                <i class="bi bi-trash3 me-1"></i>Eliminar
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>Cerrar</button>
                        <?php if (!empty($perm['crear']) || !empty($perm['actualizar'])): ?>
                            <button type="submit" class="btn btn-primary btn-sm px-4" id="uni-btn-guardar">
                                <i class="bi bi-check2-circle me-1"></i>Guardar
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
