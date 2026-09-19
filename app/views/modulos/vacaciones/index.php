<?php

/** @var array $perm */
/** @var string $rutaModulo */
/** @var array $rows */
/** @var int $total */
/** @var int $page */
/** @var int $totalPages */
/** @var int $perPage */
/** @var string $buscar */
/** @var string $ordenCol */
/** @var string $ordenDir */
/** @var array $meses */

$base = BASE_URL;
$urlBaseVac = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
$colores = ['registrado' => 'info', 'pagado' => 'success', 'anulado' => 'danger'];
?>

<style>
    .vac-scroll { max-height: calc(100dvh - 250px); overflow-y: auto; }
    .vac-scroll thead th { position: sticky; top: 0; z-index: 10; background: #f8f9fa; }
    .vac-row { cursor: pointer; }
    .vac-row:hover { background-color: rgba(0, 0, 0, .04); }
</style>

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-umbrella me-2 text-primary"></i> <?= htmlspecialchars($titulo) ?></h5>
    <div class="d-flex gap-2">
        <?php // Solicitudes que los empleados enviaron desde el enlace que recibieron por correo. ?>
        <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#modalSolicitudesVac" title="Revisar, aprobar o rechazar las solicitudes de los empleados">
            <i class="bi bi-envelope-paper me-1"></i> Solicitudes
            <?php if (!empty($solicitudesPendientes)): ?>
                <span class="badge bg-warning text-dark ms-1"><?= (int) $solicitudesPendientes ?></span>
            <?php endif; ?>
        </button>
        <?php // Períodos de un empleado: ver su cuadro y marcar los ya tomados o pagados antes del sistema. ?>
        <button type="button" class="btn btn-outline-primary btn-sm px-3" onclick="abrirModalPeriodos()" title="Ver los períodos de un empleado y marcar los ya tomados o pagados"><i class="bi bi-calendar-check me-1"></i> Períodos</button>
        <?php if ($perm['crear']): ?>
            <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="abrirModalCrear()"><i class="bi bi-plus-lg me-1"></i> Nueva</button>
        <?php endif; ?>
    </div>
</div>

<div class="card cmg-table-card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php
            // Buscador: texto libre sobre las columnas del listado (sin sugerencias) + botón
            // embudo que abre el modal con todos los filtros + chips de los activos.
            // Las claves (key) deben existir en los mapas de VacacionRepository::getListado().
            // Vacaciones no tiene tablas hijas: una sola pestaña, sin "Detalles".
            $opcionesMesVac = [];
            foreach ($meses as $n => $nom) {
                $opcionesMesVac[] = ['v' => (string) $n, 'l' => $nom];
            }
            $opcionesAnioVac    = array_map(fn($a) => ['v' => (string) $a, 'l' => (string) $a], $aniosFiltro ?? []);
            $opcionesUsuarioVac = array_map(fn($u) => ['v' => (string) $u['id'], 'l' => $u['nombre']], $usuariosFiltro ?? []);
            $tV = 'Vacación';
            // Orden pensado en filas de 12 columnas:
            //   Vacación: [Desde 6][Hasta 6]
            //             [Mes del rol 3][Año 3][Estado 3][Afecta al rol 3]
            //   Valores:  [Días gozados 4][Días de derecho 4][Valor 4]
            //   Empleado: [Empleado 3][Identificación 3][Observación 3][Usuario 3]
            $filtrosVacaciones = [
                // ── Vacación ──
                ['tab' => $tV, 'key' => 'desde',      'label' => 'Desde',          'icon' => 'bi-calendar-date',  'type' => 'date_range', 'grupo' => 'Vacación', 'col' => 6, 'atajos' => true],
                ['tab' => $tV, 'key' => 'hasta',      'label' => 'Hasta',          'icon' => 'bi-calendar-date',  'type' => 'date_range', 'grupo' => 'Vacación', 'col' => 6],
                ['tab' => $tV, 'key' => 'mes',        'label' => 'Mes del rol',    'icon' => 'bi-calendar-month', 'type' => 'select',     'grupo' => 'Vacación', 'col' => 3, 'options' => $opcionesMesVac],
                ['tab' => $tV, 'key' => 'anio',       'label' => 'Año del rol',    'icon' => 'bi-calendar',       'type' => 'select',     'grupo' => 'Vacación', 'col' => 3, 'options' => $opcionesAnioVac],
                ['tab' => $tV, 'key' => 'estado',     'label' => 'Estado',         'icon' => 'bi-flag',           'type' => 'select',     'grupo' => 'Vacación', 'col' => 3, 'options' => [
                    ['v' => 'registrado', 'l' => 'Registrado'],
                    ['v' => 'pagado',     'l' => 'Pagado'],
                    ['v' => 'anulado',    'l' => 'Anulado'],
                ]],
                ['tab' => $tV, 'key' => 'afecta_rol', 'label' => 'Afecta al rol',  'icon' => 'bi-cash-stack',     'type' => 'select',     'grupo' => 'Vacación', 'col' => 3, 'options' => [
                    ['v' => 'si', 'l' => 'Sí, se incluye en el rol'],
                    ['v' => 'no', 'l' => 'No'],
                ]],
                // ── Valores ──
                ['tab' => $tV, 'key' => 'dias',         'label' => 'Días gozados',    'icon' => 'bi-123',             'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                ['tab' => $tV, 'key' => 'dias_derecho', 'label' => 'Días de derecho', 'icon' => 'bi-calendar-check',  'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                ['tab' => $tV, 'key' => 'valor',        'label' => 'Valor',           'icon' => 'bi-currency-dollar', 'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                // ── Empleado ──
                ['tab' => $tV, 'key' => 'empleado',       'label' => 'Empleado',             'icon' => 'bi-person',         'type' => 'text',   'grupo' => 'Empleado', 'col' => 3],
                ['tab' => $tV, 'key' => 'identificacion', 'label' => 'Identificación',       'icon' => 'bi-card-text',      'type' => 'text',   'grupo' => 'Empleado', 'col' => 3],
                ['tab' => $tV, 'key' => 'observacion',    'label' => 'Observación',          'icon' => 'bi-chat-left-text', 'type' => 'text',   'grupo' => 'Empleado', 'col' => 3],
                ['tab' => $tV, 'key' => 'usuario',        'label' => 'Usuario que registró', 'icon' => 'bi-person-gear',    'type' => 'select', 'grupo' => 'Empleado', 'col' => 3, 'options' => $opcionesUsuarioVac],
            ];
            ?>
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
            <div id="fmBuscadorVAC"></div>
            <input type="hidden" id="buscarVac" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosModal) return;
                    new FiltrosModal({
                        containerId: 'fmBuscadorVAC',
                        hiddenInputId: 'buscarVac',
                        placeholder: 'Buscar en todas las columnas...',
                        titulo: 'Filtros de vacaciones',
                        inputWidth: 420,
                        extraId: 'fmExtraVAC',   // columnas, pegado al final del grupo
                        fields: <?= json_encode($filtrosVacaciones, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                        loadingTarget: '#tbodyVacaciones',   // se atenúa mientras se busca
                        onApply: () => window.cambiarPaginaAjax && window.cambiarPaginaAjax(1),
                    }).init();
                });
            </script>
            <?php // FiltrosModal (extraId) mueve estos botones dentro del input-group del buscador; si el JS no corre, quedan aquí. ?>
            <div id="fmExtraVAC" class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'empleado' => 'Empleado', 'identificacion' => 'Identificación', 'desde' => 'Desde',
                    'hasta' => 'Hasta', 'dias' => 'Días', 'valor' => 'Valor', 'estado' => 'Estado',
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?> / <?= $total ?></span>
            <div id="wrapper-pagination" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(<?= $page - 1 ?>)" <?= $page <= 1 ? 'disabled' : '' ?>><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(<?= $page + 1 ?>)" <?= $page >= $totalPages ? 'disabled' : '' ?>><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="vac-scroll">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light shadow-sm">
                    <tr>
                        <th class="ps-3 sortable-header" data-sort="empleado" role="button" data-col="empleado">Empleado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th data-col="identificacion">Identificación</th>
                        <th class="sortable-header" data-sort="fecha_desde" role="button" data-col="desde">Desde <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th data-col="hasta">Hasta</th>
                        <th class="text-center" data-col="dias">Días</th>
                        <th class="text-end sortable-header" data-sort="valor" role="button" data-col="valor">Valor <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" data-sort="estado" role="button" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center" style="width: 40px;"></th>
                    </tr>
                </thead>
                <tbody id="tbodyVacaciones">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">No hay vacaciones registradas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row):
                            $c = $colores[$row['estado']] ?? 'secondary';
                        ?>
                            <tr class="vac-row" onclick="abrirModalEditar(this)" data-row='<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>'>
                                <td class="ps-3 fw-medium" data-col="empleado"><?= htmlspecialchars((string) $row['empleado_nombre']) ?></td>
                                <td data-col="identificacion"><code class="text-secondary"><?= htmlspecialchars((string) $row['empleado_identificacion']) ?></code></td>
                                <td data-col="desde"><?= $row['fecha_desde'] ? date('d-m-Y', strtotime((string) $row['fecha_desde'])) : '—' ?></td>
                                <td data-col="hasta"><?= $row['fecha_hasta'] ? date('d-m-Y', strtotime((string) $row['fecha_hasta'])) : '—' ?></td>
                                <td class="text-center" data-col="dias"><?= (float) $row['dias_gozados'] ?></td>
                                <td class="text-end fw-bold" data-col="valor">$<?= number_format((float) $row['valor'], 2) ?></td>
                                <td class="text-center" data-col="estado">
                                    <span class="badge bg-<?= $c ?> bg-opacity-10 text-<?= $c ?> border border-<?= $c ?> border-opacity-25"><?= htmlspecialchars(ucfirst((string) $row['estado'])) ?></span>
                                </td>
                                <td class="text-center pe-3" onclick="event.stopPropagation()">
                                    <button class="btn btn-outline-danger btn-xs border-0 px-2" onclick="eliminarRegistro(<?= $row['id'] ?>)" title="Eliminar"><i class="bi bi-trash"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Bandeja de solicitudes de vacaciones de toda la empresa -->
<div class="modal fade" id="modalSolicitudesVac" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-envelope-paper me-2 text-primary"></i>Solicitudes de vacaciones
                </h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2">
                    <i class="bi bi-info-circle me-1"></i>Los empleados envían estas solicitudes desde el enlace que
                    reciben por correo. El enlace se manda desde la pestaña <b>Vacaciones</b> de su ficha (módulo
                    Empleados). Al aprobar una solicitud se registra la vacación del empleado.
                </p>
                <?php
                $solicitudesCuadro = [
                    'id'               => 'vacBandejaSolicitudes',
                    'modo'             => 'bandeja',
                    'puede_crear'      => !empty($perm['crear']),
                    'puede_actualizar' => !empty($perm['actualizar']),
                ];
                include MVC_APP . '/views/modulos/vacaciones/_solicitudes.php';
                ?>
            </div>
            <div class="modal-footer bg-light border-top p-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>window.BASE_URL = '<?= $base ?>';</script>
<?php include 'modal_vacacion.php'; ?>
<script src="<?= $base ?>/js/modulos/vacaciones_periodos.js?v=<?= asset_ver('/js/modulos/vacaciones_periodos.js') ?>"></script>
<script src="<?= $base ?>/js/modulos/vacaciones_solicitudes.js?v=<?= asset_ver('/js/modulos/vacaciones_solicitudes.js') ?>"></script>
<script src="<?= $base ?>/js/modulos/vacaciones.js?v=<?= asset_ver('/js/modulos/vacaciones.js') ?>"></script>

<script>
    (function () {
        'use strict';
        const urlBase = '<?= $urlBaseVac ?>';
        const inputB = document.getElementById('buscarVac');
        let currentSort = '<?= $ordenCol ?>', currentDir = '<?= $ordenDir ?>';

        window.cambiarPaginaAjax = (p) => cargarListado(p);

        async function cargarListado(page = 1) {
            const b = inputB ? inputB.value.trim() : '';
            // Mismo indicador que el buscador (FiltrosModal): la tabla se atenúa mientras
            // carga, también al paginar u ordenar (que llaman a esta función directo).
            const tbody = document.getElementById('tbodyVacaciones');
            if (tbody) tbody.classList.add('fm-cargando-target');
            try {
                const resp = await fetch(`${urlBase}/searchAjax?b=${encodeURIComponent(b)}&page=${page}&sort=${currentSort}&dir=${currentDir}`);
                const data = await resp.json();
                if (data.ok) {
                    window.currentPage = page;
                    document.getElementById('tbodyVacaciones').innerHTML = data.rows;
                    document.getElementById('wrapper-pagination').innerHTML = data.pagination;
                    document.getElementById('paginationInfo').textContent = data.info;
                }
            } catch (e) {
                console.error(e);
            } finally {
                if (tbody) tbody.classList.remove('fm-cargando-target');
            }
        }

        if (window.CMG_initSort) {
            window.CMG_initSort('vacaciones', (col, dir) => { currentSort = col; currentDir = dir; cargarListado(1); }, { col: currentSort, dir: currentDir });
        }
        window.addEventListener('vacacionGuardada', () => cargarListado(window.currentPage || 1));

        // Bandeja de solicitudes: se carga al abrir el modal. Si se aprueba una, se
        // registra la vacación → hay que refrescar el listado de atrás.
        const raizSol = document.getElementById('vacBandejaSolicitudes');
        if (raizSol && window.CuadroSolicitudesVacaciones) {
            const bandeja = new window.CuadroSolicitudesVacaciones(raizSol, {
                onCambio: () => cargarListado(window.currentPage || 1),
            });
            document.getElementById('modalSolicitudesVac')
                ?.addEventListener('shown.bs.modal', () => bandeja.cargarBandeja());
        }
    })();
</script>
