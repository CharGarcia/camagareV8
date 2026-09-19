<?php
/**
 * Modal para registrar o editar una vacación del empleado abierto en su ficha
 * (los mismos campos del modal del módulo Vacaciones, con el empleado ya fijado).
 *
 * Va SEPARADO de _vacaciones_empleado.php —que pinta la sección dentro de la
 * pestaña— porque este modal debe declararse FUERA del modal de la ficha: si
 * queda dentro, hereda su contexto de apilamiento (app.css fuerza
 * .modal { z-index:5060 !important }) y su propio backdrop lo tapa, dejándolo
 * visible pero inservible. Misma convención que los submodales de Proformas.
 *
 * Recibe el mismo $vacacionesEmpleado que la sección (id, permisos y meses); el
 * id del modal es "{id}Modal", que es lo que la sección publica en data-modal.
 *
 * Ningún control lleva `name`: así no viaja en el FormData de ningún formulario
 * que lo contenga, y los botones son type="button" para no enviarlo.
 */
$ve = array_merge(
    ['id' => 'empVacRegistradas', 'puede_eliminar' => false, 'meses' => []],
    $vacacionesEmpleado ?? []
);
$idModalVac = $ve['id'] . 'Modal';
$anioActualVE = (int) date('Y');
$mesActualVE  = (int) date('n');
?>
<!-- Modal para registrar o editar una vacación del empleado abierto -->
<div class="modal fade" id="<?= htmlspecialchars($idModalVac, ENT_QUOTES, 'UTF-8') ?>" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-umbrella me-2 text-primary"></i><span data-rol="titulo">Registrar vacación</span>
                </h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-light border small py-2 mb-3 d-flex flex-wrap gap-3" data-rol="resumen">
                    <span><i class="bi bi-person me-1 text-muted"></i><b data-rol="res-empleado">—</b></span>
                    <span class="text-muted">Saldo: <b data-rol="res-saldo">—</b></span>
                    <span class="text-muted">Sueldo: <b data-rol="res-sueldo">—</b></span>
                </div>

                <input type="hidden" data-rol="f-id" value="">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label mb-1 small fw-bold text-muted">Fecha Desde *</label>
                        <input type="date" class="form-control form-control-sm shadow-none" data-rol="f-desde" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1 small fw-bold text-muted">Fecha Hasta *</label>
                        <input type="date" class="form-control form-control-sm shadow-none" data-rol="f-hasta" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1 small fw-bold text-muted">Días Gozados *</label>
                        <input type="number" step="0.5" min="0" class="form-control form-control-sm shadow-none fw-bold" data-rol="f-dias" value="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1 small fw-bold text-muted">Valor a Pagar</label>
                        <input type="text" class="form-control form-control-sm shadow-none fw-bold text-success bg-light" data-rol="f-valor" value="$0.00" readonly>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label mb-1 small fw-bold text-muted">Mes del Rol *</label>
                        <select class="form-select form-select-sm shadow-none" data-rol="f-mes">
                            <?php foreach ($ve['meses'] as $num => $nombre): ?>
                                <option value="<?= (int) $num ?>" <?= (int) $num === $mesActualVE ? 'selected' : '' ?>><?= htmlspecialchars($nombre) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1 small fw-bold text-muted">Año del Rol *</label>
                        <input type="number" class="form-control form-control-sm shadow-none" data-rol="f-anio" value="<?= $anioActualVE ?>" min="2000" max="2100">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1 small fw-bold text-muted">Estado</label>
                        <select class="form-select form-select-sm shadow-none" data-rol="f-estado">
                            <option value="registrado">Registrado</option>
                            <option value="pagado">Pagado</option>
                            <option value="anulado">Anulado</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" data-rol="f-afecta" checked>
                            <label class="form-check-label small fw-bold text-muted" data-rol="f-afecta-label">Incluir en el rol</label>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label mb-1 small fw-bold text-muted">Observación</label>
                        <textarea class="form-control form-control-sm shadow-none" data-rol="f-observacion" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between bg-light border-top p-2">
                <div>
                    <?php if (!empty($ve['puede_eliminar'])): ?>
                        <button type="button" class="btn btn-outline-danger btn-sm px-3 d-none" data-rol="btn-eliminar">
                            <i class="bi bi-trash3 me-1"></i> Eliminar
                        </button>
                    <?php endif; ?>
                </div>
                <div>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                        <i class="fa-solid fa-xmark me-1"></i>Cancelar
                    </button>
                    <button type="button" class="btn btn-primary btn-sm px-4 shadow-sm" data-rol="btn-guardar">
                        <i class="bi bi-check2-circle me-1"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
