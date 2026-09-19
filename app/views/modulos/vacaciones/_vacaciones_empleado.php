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
 * El modal de registro va en _vacaciones_empleado_modal.php y debe incluirse
 * FUERA del modal de la ficha (ahí explica por qué). Esta sección solo publica su
 * id en data-modal.
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