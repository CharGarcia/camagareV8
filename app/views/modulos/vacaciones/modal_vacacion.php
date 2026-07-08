<?php
/** @var array $perm */
/** @var array $meses */

$anioActual = (int) date('Y');
$mesActual  = (int) date('n');
?>
<div class="modal fade" id="modalVacacion" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" style="z-index:1060;">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">
            <form id="formVacacion" novalidate onsubmit="return false;">
                <div class="modal-header bg-light py-3">
                    <h5 class="modal-title fw-bold"><i class="bi bi-umbrella me-2 text-primary"></i><span id="tituloModalVac">Nueva Vacación</span></h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="vac_id" value="">
                    <div class="row g-3">
                        <div class="col-md-8 position-relative">
                            <label class="form-label mb-1 small fw-bold text-muted">Empleado *</label>
                            <input type="hidden" name="id_empleado" id="vac_id_empleado" value="">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white text-muted"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control form-control-sm shadow-none" id="vac_empleado_buscar" placeholder="Buscar por nombre o identificación..." autocomplete="off">
                            </div>
                            <div id="vac_empleado_resultados" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index:1080; max-height:220px; overflow-y:auto;"></div>
                        </div>
                        <div class="col-md-4" id="vac_container_estado">
                            <label class="form-label mb-1 small fw-bold text-muted">Estado</label>
                            <select class="form-select form-select-sm shadow-none" name="estado" id="vac_estado">
                                <option value="registrado">Registrado</option>
                                <option value="pagado">Pagado</option>
                                <option value="anulado">Anulado</option>
                            </select>
                        </div>

                        <!-- Panel de saldo del empleado -->
                        <div class="col-12">
                            <div id="vac_info" class="d-none border rounded-3 bg-light p-2">
                                <div class="row text-center small g-2">
                                    <div class="col"><span class="text-muted d-block">Antigüedad</span><b id="vac_info_antig">—</b></div>
                                    <div class="col"><span class="text-muted d-block">Derecho año</span><b id="vac_info_derecho">—</b></div>
                                    <div class="col"><span class="text-muted d-block">Días gozados</span><b id="vac_info_gozados">—</b></div>
                                    <div class="col"><span class="text-muted d-block">Saldo pendiente</span><b id="vac_info_saldo" class="text-primary">—</b></div>
                                    <div class="col"><span class="text-muted d-block">Sueldo</span><b id="vac_info_sueldo">—</b></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label mb-1 small fw-bold text-muted">Fecha Desde *</label>
                            <input type="date" class="form-control form-control-sm shadow-none" name="fecha_desde" id="vac_fecha_desde" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-1 small fw-bold text-muted">Fecha Hasta *</label>
                            <input type="date" class="form-control form-control-sm shadow-none" name="fecha_hasta" id="vac_fecha_hasta" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-1 small fw-bold text-muted">Días Gozados *</label>
                            <input type="number" step="0.5" min="0" class="form-control form-control-sm shadow-none fw-bold" name="dias_gozados" id="vac_dias" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-1 small fw-bold text-muted">Valor a Pagar</label>
                            <input type="text" class="form-control form-control-sm shadow-none fw-bold text-success bg-light" id="vac_valor_preview" value="$0.00" readonly>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label mb-1 small fw-bold text-muted">Mes del Rol *</label>
                            <select class="form-select form-select-sm shadow-none" name="periodo_mes" id="vac_periodo_mes">
                                <?php foreach ($meses as $num => $nombre): ?>
                                    <option value="<?= $num ?>" <?= $num === $mesActual ? 'selected' : '' ?>><?= htmlspecialchars($nombre) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-1 small fw-bold text-muted">Año del Rol *</label>
                            <input type="number" class="form-control form-control-sm shadow-none" name="periodo_anio" id="vac_periodo_anio" value="<?= $anioActual ?>" min="2000" max="2100">
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="afecta_rol" id="vac_afecta_rol" value="1" checked>
                                <label class="form-check-label small fw-bold text-muted" for="vac_afecta_rol">Incluir el valor en el rol de pagos del período</label>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label mb-1 small fw-bold text-muted">Observación</label>
                            <textarea class="form-control form-control-sm shadow-none" name="observacion" id="vac_observacion" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between bg-light border-top p-2">
                    <div>
                        <?php if (!empty($perm['eliminar'])): ?>
                            <button type="button" class="btn btn-outline-danger btn-sm px-3 d-none" id="btnEliminarVac" onclick="window.eliminarVacacionModal()"><i class="bi bi-trash3 me-1"></i> Eliminar</button>
                        <?php endif; ?>
                    </div>
                    <div>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm px-4 shadow-sm" id="btnGuardarVac"><i class="bi bi-check2-circle me-1"></i> Guardar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
