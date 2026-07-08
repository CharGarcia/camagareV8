<?php
/** @var array $perm */
/** @var array $tipos */
/** @var array $motivos */
/** @var array $meses */
/** @var array $aplicaEn */

$anioActual = (int) date('Y');
$mesActual  = (int) date('n');
?>
<div class="modal fade" id="modalNovedad" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" style="z-index:1060;">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">
            <form id="formNovedad" novalidate onsubmit="return false;">
                <div class="modal-header bg-light py-3">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-clipboard-plus me-2 text-primary"></i>
                        <span id="tituloModalNov">Nueva Novedad</span>
                    </h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="nov_id" value="">
                    <div class="row g-3">
                        <div class="col-md-8 position-relative">
                            <label class="form-label mb-1 small fw-bold text-muted">Empleado *</label>
                            <input type="hidden" name="id_empleado" id="nov_id_empleado" value="">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white text-muted"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control form-control-sm shadow-none" id="nov_empleado_buscar" placeholder="Buscar por nombre o identificación..." autocomplete="off">
                            </div>
                            <div id="nov_empleado_resultados" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index:1080; max-height:220px; overflow-y:auto;"></div>
                        </div>
                        <div class="col-md-4" id="nov_container_estado">
                            <label class="form-label mb-1 small fw-bold text-muted">Estado</label>
                            <select class="form-select form-select-sm shadow-none" name="estado" id="nov_estado">
                                <option value="activo">Activo</option>
                                <option value="anulado">Anulado</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label mb-1 small fw-bold text-muted">Tipo de Novedad * <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('novedades', 'nov_tipo_codigo', 'tipo_codigo') ?></label>
                            <select class="form-select form-select-sm shadow-none" name="tipo_codigo" id="nov_tipo_codigo" required onchange="window.novToggleCampos()">
                                <option value="">-- Seleccionar tipo --</option>
                                <?php foreach ($tipos as $t): ?>
                                    <option value="<?= htmlspecialchars($t['codigo']) ?>"><?= htmlspecialchars($t['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6" id="nov_container_valor">
                            <label class="form-label mb-1 small fw-bold text-muted" id="nov_label_valor">Monto ($)</label>
                            <input type="number" step="0.01" class="form-control form-control-sm shadow-none fw-bold" name="valor" id="nov_valor" value="0.00">
                        </div>

                        <div class="col-md-6 d-none" id="nov_container_motivo">
                            <label class="form-label mb-1 small fw-bold text-muted">Motivo de Salida *</label>
                            <select class="form-select form-select-sm shadow-none" name="motivo_codigo" id="nov_motivo_codigo">
                                <option value="">-- Seleccionar motivo --</option>
                                <?php foreach ($motivos as $m): ?>
                                    <option value="<?= htmlspecialchars($m['codigo']) ?>"><?= htmlspecialchars($m['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label mb-1 small fw-bold text-muted">Fecha *</label>
                            <input type="date" class="form-control form-control-sm shadow-none" name="fecha" id="nov_fecha" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-1 small fw-bold text-muted">Mes del Rol *</label>
                            <select class="form-select form-select-sm shadow-none" name="periodo_mes" id="nov_periodo_mes">
                                <?php foreach ($meses as $num => $nombre): ?>
                                    <option value="<?= $num ?>" <?= $num === $mesActual ? 'selected' : '' ?>><?= htmlspecialchars($nombre) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-1 small fw-bold text-muted">Año del Rol *</label>
                            <input type="number" class="form-control form-control-sm shadow-none" name="periodo_anio" id="nov_periodo_anio" value="<?= $anioActual ?>" min="2000" max="2100">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-1 small fw-bold text-muted">Afecta a * <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('novedades', 'nov_aplica_en', 'aplica_en') ?></label>
                            <select class="form-select form-select-sm shadow-none" name="aplica_en" id="nov_aplica_en">
                                <?php foreach ($aplicaEn as $val => $lbl): ?>
                                    <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($lbl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label mb-1 small fw-bold text-muted">Observación</label>
                            <textarea class="form-control form-control-sm shadow-none" name="observacion" id="nov_observacion" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between bg-light border-top p-2">
                    <div>
                        <?php if (!empty($perm['eliminar'])): ?>
                            <button type="button" class="btn btn-outline-danger btn-sm px-3 d-none" id="btnEliminarNov" onclick="window.eliminarNovedadModal()">
                                <i class="bi bi-trash3 me-1"></i> Eliminar
                            </button>
                        <?php endif; ?>
                    </div>
                    <div>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm px-4 shadow-sm" id="btnGuardarNov"><i class="bi bi-check2-circle me-1"></i> Guardar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
