<?php
/**
 * Vacaciones registradas de un empleado + el formulario para registrar una nueva
 * (los mismos campos del modal del módulo Vacaciones, pero con el empleado ya
 * fijado: aquí no hay buscador de empleado ni cuadro de períodos, que ya vive en
 * la pestaña).
 *
 * Lo usa la pestaña Vacaciones del modal de Empleados. La lógica está en
 * public/js/modulos/vacaciones_empleado.js (CuadroVacacionesEmpleado), que la
 * página debe cargar; los datos y las acciones son los endpoints de
 * modulos/vacaciones, con los permisos de ese módulo.
 *
 * Recibe:
 *   $vacacionesEmpleado = [
 *       'id'               => id del elemento raíz (único en la página),
 *       'puede_crear'      => permiso de crear,
 *       'puede_actualizar' => permiso de actualizar,
 *       'puede_eliminar'   => permiso de eliminar,
 *       'meses'            => CatalogoNovedades::MESES,
 *   ];
 *
 * El formulario del modal es propio (no anida <form>: el modal se saca del
 * formulario del empleado por JS al abrirse, ver el componente).
 */
$ve = array_merge(
    ['id' => 'empVacRegistradas', 'puede_crear' => false, 'puede_actualizar' => false, 'puede_eliminar' => false, 'meses' => []],
    $vacacionesEmpleado ?? []
);
$idModalVac = $ve['id'] . 'Modal';
$anioActualVE = (int) date('Y');
$mesActualVE  = (int) date('n');
?>
<?php if (!defined('CMG_CUADRO_VACACIONES_EMP_CSS')): define('CMG_CUADRO_VACACIONES_EMP_CSS', true); ?>
<style>
    /* Sin "-scroll" en el nombre: el app-shell de app.css pisa la altura de todo
       [class*="-scroll"] de la página, incluido dentro de un modal. */
    .vac-registradas .vac-reg-caja { max-height: 220px; overflow: auto; }
    .vac-registradas .vac-reg-caja thead th { position: sticky; top: 0; z-index: 2; background: #f8f9fa; white-space: nowrap; }
    .vac-registradas .vac-reg-caja td,
    .vac-registradas .vac-reg-caja th { font-size: .78rem; vertical-align: middle; }
    .vac-registradas .vac-reg-obs { max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .vac-registradas tr.vac-reg-fila { cursor: pointer; }
</style>
<?php endif; ?>
<div class="vac-registradas" id="<?= htmlspecialchars($ve['id'], ENT_QUOTES, 'UTF-8') ?>"
     data-url="<?= htmlspecialchars(rtrim(BASE_URL, '/') . '/modulos/vacaciones', ENT_QUOTES, 'UTF-8') ?>"
     data-modal="<?= htmlspecialchars($idModalVac, ENT_QUOTES, 'UTF-8') ?>"
     data-crear="<?= !empty($ve['puede_crear']) ? '1' : '0' ?>"
     data-actualizar="<?= !empty($ve['puede_actualizar']) ? '1' : '0' ?>"
     data-eliminar="<?= !empty($ve['puede_eliminar']) ? '1' : '0' ?>">

    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <span class="fw-bold small text-uppercase text-muted">
            <i class="bi bi-umbrella me-1"></i>Vacaciones registradas
        </span>
        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 d-none" data-rol="badge-total"></span>

        <div class="ms-auto d-flex flex-wrap gap-1">
            <?php if (!empty($ve['puede_crear'])): ?>
                <button type="button" class="btn btn-primary btn-sm" data-rol="btn-nueva" title="Registrar vacaciones de este empleado">
                    <i class="bi bi-plus-lg me-1"></i>Registrar vacación
                </button>
            <?php endif; ?>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-rol="btn-recargar" title="Actualizar">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
    </div>

    <div class="alert alert-warning py-1 px-2 small mb-2 d-none" data-rol="aviso"></div>

    <div class="vac-reg-caja border rounded-2" data-rol="caja">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>Desde</th>
                    <th>Hasta</th>
                    <th class="text-center">Días</th>
                    <th class="text-end">Valor</th>
                    <th>Mes del rol</th>
                    <th class="text-center">Estado</th>
                    <th>Observación</th>
                    <th class="text-end" style="width:70px;"></th>
                </tr>
            </thead>
            <tbody data-rol="tbody"></tbody>
        </table>
    </div>
</div>

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
