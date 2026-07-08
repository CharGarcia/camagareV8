<?php
/** @var array $perm */
/** @var array $tipos */
/** @var array $meses */

$anioActual = (int) date('Y');
$mesActual  = (int) date('n');
?>
<div class="modal fade" id="modalRol" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" style="z-index:1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <form id="formRol" novalidate onsubmit="return false;">
                <div class="modal-header bg-light py-3">
                    <h5 class="modal-title fw-bold"><i class="bi bi-cash-stack me-2 text-primary"></i>Nuevo Rol</h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="rol_id" value="">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label mb-1 small fw-bold text-muted">Tipo de Rol *</label>
                            <select class="form-select form-select-sm shadow-none" name="tipo_rol" id="rol_tipo_rol" onchange="window.rolToggleNumero()">
                                <?php foreach ($tipos as $val => $lbl): ?>
                                    <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($lbl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-1 small fw-bold text-muted">Mes *</label>
                            <select class="form-select form-select-sm shadow-none" name="periodo_mes" id="rol_periodo_mes">
                                <?php foreach ($meses as $num => $nombre): ?>
                                    <option value="<?= $num ?>" <?= $num === $mesActual ? 'selected' : '' ?>><?= htmlspecialchars($nombre) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-1 small fw-bold text-muted">Año *</label>
                            <input type="number" class="form-control form-control-sm shadow-none" name="periodo_anio" id="rol_periodo_anio" value="<?= $anioActual ?>" min="2000" max="2100">
                        </div>
                        <div class="col-md-2 d-none" id="rol_container_numero">
                            <label class="form-label mb-1 small fw-bold text-muted" id="rol_label_numero">N°</label>
                            <select class="form-select form-select-sm shadow-none" name="numero_periodo" id="rol_numero_periodo">
                                <option value="0">-</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top p-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>Cerrar</button>
                    <button type="button" class="btn btn-primary btn-sm px-4 shadow-sm" id="btnGenerarRol" onclick="window.generarRol()"><i class="bi bi-gear-fill me-1"></i> Generar</button>
                </div>
            </form>
        </div>
    </div>
</div>
