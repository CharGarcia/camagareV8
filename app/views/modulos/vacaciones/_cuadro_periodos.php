<?php
/**
 * Cuadro de períodos de vacaciones de un empleado (reutilizable): sus años de trabajo,
 * con los ya tomados o pagados antes de usar el sistema y la opción de marcarlos.
 *
 * Lo usan el modal de Vacaciones y la pestaña Vacaciones del modal de Empleados. La
 * lógica vive en public/js/modulos/vacaciones_periodos.js (CuadroPeriodosVacaciones),
 * que la página debe cargar; los datos y las acciones son siempre los endpoints de
 * modulos/vacaciones, con los permisos de ese módulo.
 *
 * Recibe:
 *   $cuadroPeriodos = [
 *       'id'           => id del elemento raíz (único en la página),
 *       'puede_marcar' => permiso de crear en modulos/vacaciones (muestra los botones),
 *       'con_resumen'  => muestra arriba el resumen de saldo del empleado,
 *   ];
 *
 * Ningún control lleva `name`: el cuadro vive dentro de formularios ajenos (vacación,
 * empleado) y no debe viajar en el FormData de esos guardados.
 */
$cp = array_merge(
    ['id' => 'cuadroPeriodosVac', 'puede_marcar' => false, 'con_resumen' => false],
    $cuadroPeriodos ?? []
);
?>
<?php if (!defined('CMG_CUADRO_PERIODOS_VAC_CSS')): define('CMG_CUADRO_PERIODOS_VAC_CSS', true); ?>
<style>
    /* OJO: sin "-scroll" en el nombre de la clase: el app-shell de app.css pisa la altura
       de todo [class*="-scroll"] de la página, incluido lo que va dentro de un modal. */
    .vac-cuadro-periodos .vac-periodos-caja { max-height: 260px; overflow: auto; }
    .vac-cuadro-periodos .vac-periodos-caja thead th { position: sticky; top: 0; z-index: 2; background: #f8f9fa; white-space: nowrap; }
    .vac-cuadro-periodos .vac-periodos-caja td,
    .vac-cuadro-periodos .vac-periodos-caja th { font-size: .78rem; vertical-align: middle; }
    .vac-cuadro-periodos tr.vac-per-cerrado td { color: #6c757d; }
    .vac-cuadro-periodos tr.vac-per-sel td { background-color: rgba(13, 110, 253, .06); }
</style>
<?php endif; ?>
<div class="vac-cuadro-periodos" id="<?= htmlspecialchars($cp['id'], ENT_QUOTES, 'UTF-8') ?>"
     data-url="<?= htmlspecialchars(rtrim(BASE_URL, '/') . '/modulos/vacaciones', ENT_QUOTES, 'UTF-8') ?>"
     data-puede-marcar="<?= !empty($cp['puede_marcar']) ? '1' : '0' ?>">
    <?php if (!empty($cp['con_resumen'])): ?>
        <div class="border rounded-3 bg-light p-2 mb-2">
            <div class="row row-cols-2 row-cols-md-6 text-center small g-2">
                <div class="col"><span class="text-muted d-block">Ingreso</span><b data-rol="res-ingreso">—</b></div>
                <div class="col"><span class="text-muted d-block">Antigüedad</span><b data-rol="res-antig">—</b><span class="d-block text-muted" data-rol="res-derecho-anio"></span></div>
                <div class="col"><span class="text-muted d-block">Derecho acumulado</span><b data-rol="res-acumulado">—</b></div>
                <div class="col"><span class="text-muted d-block" title="Días de los períodos marcados como tomados o pagados antes de usar el sistema">Tomados/pagados antes</span><b data-rol="res-marcados">—</b></div>
                <div class="col"><span class="text-muted d-block">Gozados en el sistema</span><b data-rol="res-gozados">—</b></div>
                <div class="col"><span class="text-muted d-block">Saldo pendiente</span><b data-rol="res-saldo" class="text-primary">—</b></div>
            </div>
        </div>
    <?php endif; ?>
    <div class="small text-muted mb-2">
        Marque los períodos que el empleado ya tomó o cobró antes de usar el sistema: sus días dejan de contar en el saldo.
        Las vacaciones registradas en el sistema se descuentan del período pendiente más antiguo.
    </div>
    <div class="alert alert-warning py-1 px-2 small mb-2 d-none" data-rol="aviso"></div>
    <div class="vac-periodos-caja border rounded-2" data-rol="caja">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th class="text-center" style="width:28px;"><input type="checkbox" class="form-check-input m-0" data-rol="chk-todos" title="Seleccionar todos los que se pueden marcar"></th>
                    <th class="text-center">#</th>
                    <th>Desde</th>
                    <th>Hasta</th>
                    <th class="text-center">Derecho</th>
                    <th class="text-center" title="Días que tomó o cobró antes de usar el sistema">Antes del sistema</th>
                    <th class="text-center" title="Días de las vacaciones registradas en el sistema">En el sistema</th>
                    <th class="text-center">Pendiente</th>
                    <th>Situación</th>
                    <th style="width:28px;"></th>
                </tr>
            </thead>
            <tbody data-rol="tbody"></tbody>
        </table>
    </div>
    <div class="small text-danger mt-1 d-none" data-rol="adelantados"></div>
    <?php if (!empty($cp['puede_marcar'])): ?>
        <div class="d-flex flex-wrap align-items-center gap-2 mt-2 d-none" data-rol="acciones">
            <input type="text" class="form-control form-control-sm shadow-none" data-rol="obs" maxlength="255" style="max-width:300px;" placeholder="Observación (opcional)">
            <span class="small text-muted ms-auto" data-rol="sel">Ningún período seleccionado</span>
            <button type="button" class="btn btn-outline-success btn-sm" data-rol="btn-tomado" disabled><i class="bi bi-check2-square me-1"></i> Marcar como tomados</button>
            <button type="button" class="btn btn-outline-primary btn-sm" data-rol="btn-pagado" disabled><i class="bi bi-cash-coin me-1"></i> Marcar como pagados</button>
        </div>
    <?php endif; ?>
</div>
