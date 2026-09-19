<?php
/**
 * Cuadro de solicitudes de vacaciones (reutilizable): el empleado las envía desde
 * el enlace que recibe por correo y aquí se revisan, se aprueban o se rechazan.
 *
 * Lo usan la pestaña Vacaciones del modal de Empleados (modo 'empleado', con los
 * botones de enviar el enlace y de imprimir el detalle) y la bandeja del módulo
 * Vacaciones (modo 'bandeja', con todas las de la empresa). La lógica vive en
 * public/js/modulos/vacaciones_solicitudes.js (CuadroSolicitudesVacaciones), que
 * la página debe cargar; los datos y las acciones son los endpoints de
 * modulos/vacaciones, con los permisos de ese módulo.
 *
 * Recibe:
 *   $solicitudesCuadro = [
 *       'id'               => id del elemento raíz (único en la página),
 *       'modo'             => 'empleado' | 'bandeja',
 *       'puede_crear'      => permiso de crear  (enviar el enlace y aprobar),
 *       'puede_actualizar' => permiso de actualizar (rechazar y anular el enlace),
 *   ];
 *
 * Ningún control lleva `name`: el cuadro vive dentro de formularios ajenos.
 */
$sc = array_merge(
    ['id' => 'cuadroSolicitudesVac', 'modo' => 'empleado', 'puede_crear' => false, 'puede_actualizar' => false],
    $solicitudesCuadro ?? []
);
?>
<?php if (!defined('CMG_CUADRO_SOLICITUDES_VAC_CSS')): define('CMG_CUADRO_SOLICITUDES_VAC_CSS', true); ?>
<style>
    /* OJO: sin "-scroll" en el nombre de la clase: el app-shell de app.css pisa la
       altura de todo [class*="-scroll"] de la página, incluido dentro de un modal. */
    .vac-solicitudes .vac-sol-caja { max-height: 240px; overflow: auto; }
    .vac-solicitudes .vac-sol-caja thead th { position: sticky; top: 0; z-index: 2; background: #f8f9fa; white-space: nowrap; }
    .vac-solicitudes .vac-sol-caja td,
    .vac-solicitudes .vac-sol-caja th { font-size: .78rem; vertical-align: middle; }
    .vac-solicitudes .vac-sol-motivo { max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
</style>
<?php endif; ?>
<div class="vac-solicitudes" id="<?= htmlspecialchars($sc['id'], ENT_QUOTES, 'UTF-8') ?>"
     data-url="<?= htmlspecialchars(rtrim(BASE_URL, '/') . '/modulos/vacaciones', ENT_QUOTES, 'UTF-8') ?>"
     data-modo="<?= htmlspecialchars($sc['modo'], ENT_QUOTES, 'UTF-8') ?>"
     data-crear="<?= !empty($sc['puede_crear']) ? '1' : '0' ?>"
     data-actualizar="<?= !empty($sc['puede_actualizar']) ? '1' : '0' ?>">

    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <span class="fw-bold small text-uppercase text-muted">
            <i class="bi bi-envelope-paper me-1"></i>Solicitudes
        </span>

        <?php if ($sc['modo'] === 'bandeja'): ?>
            <select class="form-select form-select-sm shadow-none w-auto" data-rol="filtro-estado" style="font-size:.78rem;">
                <option value="">Abiertas (sin resolver)</option>
                <option value="pendiente">Esperando aprobación</option>
                <option value="enviada">Enviadas al empleado</option>
                <option value="aprobada">Aprobadas</option>
                <option value="rechazada">Rechazadas</option>
                <option value="cancelada">Anuladas</option>
            </select>
        <?php endif; ?>

        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 d-none" data-rol="badge-pendientes"></span>

        <div class="ms-auto d-flex flex-wrap gap-1">
            <?php if ($sc['modo'] === 'empleado'): ?>
                <?php if (!empty($sc['puede_crear'])): ?>
                    <button type="button" class="btn btn-outline-primary btn-sm" data-rol="btn-enviar" title="Enviarle al empleado un enlace para que solicite sus vacaciones">
                        <i class="bi bi-send me-1"></i>Enviar solicitud
                    </button>
                <?php endif; ?>
                <button type="button" class="btn btn-outline-danger btn-sm" data-rol="btn-pdf-detalle" title="Imprimir el detalle de vacaciones del empleado (PDF)">
                    <i class="bi bi-file-earmark-pdf me-1"></i>Detalle PDF
                </button>
            <?php endif; ?>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-rol="btn-recargar" title="Actualizar">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
    </div>

    <div class="alert alert-warning py-1 px-2 small mb-2 d-none" data-rol="aviso"></div>

    <div class="vac-sol-caja border rounded-2" data-rol="caja">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <?php if ($sc['modo'] === 'bandeja'): ?><th>Empleado</th><?php endif; ?>
                    <th>Estado</th>
                    <th>Desde</th>
                    <th>Hasta</th>
                    <th class="text-center">Días</th>
                    <th>Motivo</th>
                    <th>Enviada / solicitada</th>
                    <th class="text-end" style="width:130px;">Acciones</th>
                </tr>
            </thead>
            <tbody data-rol="tbody"></tbody>
        </table>
    </div>
</div>
