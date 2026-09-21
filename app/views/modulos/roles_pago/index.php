<?php

/** @var string $titulo */
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
/** @var string $ordenJson */
/** @var array $tipos */
/** @var array $estados */
/** @var array $meses */

use App\models\CatalogoRol;
use App\models\CatalogoNovedades;

$base = BASE_URL;
$urlBaseRol = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
$colores = ['borrador' => 'secondary', 'generado' => 'info', 'pagado' => 'success', 'contabilizado' => 'primary', 'anulado' => 'danger'];
?>

<style>
    .rolp-scroll { max-height: calc(100dvh - 250px); overflow-y: auto; }
    .rolp-scroll thead th { position: sticky; top: 0; z-index: 10; background: #f8f9fa; }
    .rol-row { cursor: pointer; }
    .rol-row:hover { background-color: rgba(0, 0, 0, .04); }
</style>

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-cash-stack me-2 text-primary"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="abrirModalCrear()">
            <i class="bi bi-plus-lg me-1"></i> Nuevo
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php
            // Buscador: texto libre sobre las columnas del listado (sin sugerencias) + botón
            // embudo que abre el modal con todos los filtros + chips de los activos.
            // Las claves (key) deben existir en los mapas de RolPagoRepository::getListado().
            $opcionesTipoRol = [];
            foreach (CatalogoRol::tipos() as $v => $l) {
                $opcionesTipoRol[] = ['v' => (string) $v, 'l' => $l];
            }
            $opcionesEstadoRol = [];
            foreach (CatalogoRol::estados() as $v => $l) {
                $opcionesEstadoRol[] = ['v' => (string) $v, 'l' => $l];
            }
            $opcionesMesRol = [];
            foreach ($meses as $n => $nom) {
                $opcionesMesRol[] = ['v' => (string) $n, 'l' => $nom];
            }
            $opcionesAnioRol    = array_map(fn($a) => ['v' => (string) $a, 'l' => (string) $a], $aniosFiltro ?? []);
            $opcionesUsuarioRol = array_map(fn($u) => ['v' => (string) $u['id'], 'l' => $u['nombre']], $usuariosFiltro ?? []);
            // Dos pestañas: "Rol" (filtros por campo de la corrida) y "Detalles" (solo la
            // búsqueda libre dentro de las líneas de empleado y sus rubros; sin filtros por campo).
            $tR = 'Rol';
            // Orden pensado en filas de 12 columnas:
            //   Corrida:  [Fecha de pago 6][Mes 3][Año 3]
            //             [Tipo de rol 4][Estado 4][Asiento 4]
            //             [Corrida 4][Descripción 4][Usuario 4]
            //   Valores:  [Neto 6][Nº de empleados 6]
            //             [Total ingresos 4][Total egresos 4][Aporte patronal 4]
            //   Empleado: [Empleado 6][Identificación 6]
            $filtrosRoles = [
                // ── Corrida ──
                ['tab' => $tR, 'key' => 'fecha',       'label' => 'Fecha de pago',        'icon' => 'bi-calendar-event', 'type' => 'date_range', 'grupo' => 'Corrida', 'col' => 6, 'atajos' => true],
                ['tab' => $tR, 'key' => 'mes',         'label' => 'Mes del período',      'icon' => 'bi-calendar-month', 'type' => 'select',     'grupo' => 'Corrida', 'col' => 3, 'options' => $opcionesMesRol],
                ['tab' => $tR, 'key' => 'anio',        'label' => 'Año del período',      'icon' => 'bi-calendar',       'type' => 'select',     'grupo' => 'Corrida', 'col' => 3, 'options' => $opcionesAnioRol],
                ['tab' => $tR, 'key' => 'tipo',        'label' => 'Tipo de rol',          'icon' => 'bi-cash-stack',     'type' => 'select',     'grupo' => 'Corrida', 'col' => 4, 'options' => $opcionesTipoRol],
                ['tab' => $tR, 'key' => 'estado',      'label' => 'Estado',               'icon' => 'bi-flag',           'type' => 'select',     'grupo' => 'Corrida', 'col' => 4, 'options' => $opcionesEstadoRol],
                ['tab' => $tR, 'key' => 'asiento',     'label' => 'Asiento contable',     'icon' => 'bi-journal-check',  'type' => 'select',     'grupo' => 'Corrida', 'col' => 4, 'options' => [
                    ['v' => 'si', 'l' => 'Con asiento'],
                    ['v' => 'no', 'l' => 'Sin asiento'],
                ]],
                ['tab' => $tR, 'key' => 'corrida',     'label' => 'Corrida (tipo y período)', 'icon' => 'bi-hash',       'type' => 'text',       'grupo' => 'Corrida', 'col' => 4, 'placeholder' => 'Rol Mensual Julio 2026'],
                ['tab' => $tR, 'key' => 'descripcion', 'label' => 'Descripción',          'icon' => 'bi-chat-left-text', 'type' => 'text',       'grupo' => 'Corrida', 'col' => 4],
                ['tab' => $tR, 'key' => 'usuario',     'label' => 'Usuario que registró', 'icon' => 'bi-person-gear',    'type' => 'select',     'grupo' => 'Corrida', 'col' => 4, 'options' => $opcionesUsuarioRol],
                // ── Valores ──
                ['tab' => $tR, 'key' => 'neto',            'label' => 'Neto',            'icon' => 'bi-currency-dollar', 'type' => 'number_range', 'grupo' => 'Valores', 'col' => 6],
                ['tab' => $tR, 'key' => 'empleados',       'label' => 'Nº de empleados', 'icon' => 'bi-people',          'type' => 'number_range', 'grupo' => 'Valores', 'col' => 6],
                ['tab' => $tR, 'key' => 'ingresos',        'label' => 'Total ingresos',  'icon' => 'bi-plus-circle',     'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                ['tab' => $tR, 'key' => 'egresos',         'label' => 'Total egresos',   'icon' => 'bi-dash-circle',     'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                ['tab' => $tR, 'key' => 'aporte_patronal', 'label' => 'Aporte patronal', 'icon' => 'bi-building',        'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                // ── Empleado ──
                ['tab' => $tR, 'key' => 'empleado',       'label' => 'Empleado incluido',  'icon' => 'bi-person',    'type' => 'text', 'grupo' => 'Empleado', 'col' => 6],
                ['tab' => $tR, 'key' => 'identificacion', 'label' => 'Identificación',     'icon' => 'bi-card-text', 'type' => 'text', 'grupo' => 'Empleado', 'col' => 6],
            ];
            ?>
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
            <div id="fmBuscadorROL"></div>
            <input type="hidden" id="buscarRol" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosModal) return;
                    new FiltrosModal({
                        containerId: 'fmBuscadorROL',
                        hiddenInputId: 'buscarRol',
                        placeholder: 'Buscar en todas las columnas...',
                        titulo: 'Filtros de roles de pago',
                        inputWidth: 420,
                        extraId: 'fmExtraROL',   // columnas, pegado al final del grupo
                        // Pestaña Detalles: búsqueda libre dentro de las corridas (líneas de
                        // empleado y rubros). Cada coincidencia dice a qué corrida pertenece.
                        busquedaDetalle: {
                            tab: 'Detalles',
                            url: `<?= $urlBaseRol ?>/buscarDetallesAjax`,
                            label: 'Buscar libremente dentro de los roles de pago',
                            placeholder: 'Empleado, identificación, cargo, rubro (sueldo, horas extra, IESS…), valor...',
                            columns: [
                                { key: 'origen',         label: 'Tipo' },
                                { key: 'empleado',       label: 'Empleado' },
                                { key: 'identificacion', label: 'Identificación', class: 'font-monospace' },
                                { key: 'concepto',       label: 'Cargo / Rubro' },
                                { key: 'monto',          label: 'Valor', align: 'end' },
                                { key: 'corrida',        label: 'Corrida', class: 'fw-semibold' },
                                { key: 'fecha',          label: 'Fecha de pago' },
                                { key: 'estado',         label: 'Estado' },
                            ],
                            onSelect: (row, fm) => fm.aplicarFiltro({ key: 'corrida', value: row.corrida }),
                            onOpen: (row, fm) => { fm.hide(); setTimeout(() => window.abrirModalVer && window.abrirModalVer({ id: row.id_rol }), 350); },
                        },
                        fields: <?= json_encode($filtrosRoles, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                        loadingTarget: '#tbodyRoles',   // se atenúa mientras se busca
                        onApply: () => window.cambiarPaginaAjax && window.cambiarPaginaAjax(1),
                    }).init();
                });
            </script>
            <?php // FiltrosModal (extraId) mueve estos botones dentro del input-group del buscador; si el JS no corre, quedan aquí. ?>
            <div id="fmExtraROL" class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'tipo' => 'Tipo', 'periodo' => 'Período', 'empleados' => 'Empleados', 'neto' => 'Neto', 'estado' => 'Estado',
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
        <div class="rolp-scroll">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light shadow-sm">
                    <tr>
                        <th class="ps-3 sortable-header" data-sort="tipo_rol" role="button" data-col="tipo">Tipo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" data-sort="periodo" role="button" data-col="periodo">Período <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" data-sort="empleados" role="button" data-col="empleados">Empleados <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header" data-sort="total_neto" role="button" data-col="neto">Neto <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" data-sort="estado" role="button" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center" style="width: 40px;"></th>
                    </tr>
                </thead>
                <tbody id="tbodyRoles">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">No hay corridas de rol registradas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row):
                            $mes = $meses[(int) $row['periodo_mes']] ?? $row['periodo_mes'];
                            $num = (int) $row['numero_periodo'] > 0 ? ' #' . (int) $row['numero_periodo'] : '';
                            $c = $colores[$row['estado']] ?? 'secondary';
                        ?>
                            <tr class="rol-row" onclick="abrirModalVer(this)" data-row='<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>'>
                                <td class="ps-3 fw-medium" data-col="tipo"><?= htmlspecialchars(CatalogoRol::nombreTipo((string) $row['tipo_rol'])) ?></td>
                                <td data-col="periodo"><?= htmlspecialchars($mes . ' ' . $row['periodo_anio'] . $num) ?></td>
                                <td class="text-center" data-col="empleados"><?= (int) ($row['num_empleados'] ?? 0) ?></td>
                                <td class="text-end fw-bold" data-col="neto">$<?= number_format((float) $row['total_neto'], 2) ?></td>
                                <td class="text-center" data-col="estado">
                                    <span class="badge bg-<?= $c ?> bg-opacity-10 text-<?= $c ?> border border-<?= $c ?> border-opacity-25"><?= htmlspecialchars(CatalogoRol::nombreEstado((string) $row['estado'])) ?></span>
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

<script>window.BASE_URL = '<?= $base ?>';</script>
<?php include 'modal_rol.php'; ?>
<?php include 'modal_rol_ver.php'; ?>
<?php include 'modal_rol_emp.php'; ?>
<script src="<?= $base ?>/js/modulos/roles_pago.js?v=<?= asset_ver('/js/modulos/roles_pago.js') ?>"></script>

<script>
    (function () {
        'use strict';
        const urlBase = '<?= $urlBaseRol ?>';
        const inputB = document.getElementById('buscarRol');
        // Orden múltiple (Shift+clic): lista completa de criterios, en el mismo formato
        // que lee OrdenListado en PHP. Viaja al backend como `orden=col:DIR,col:DIR`.
        window.currentSorts = <?= $ordenJson ?? '[]' ?>;
        let sorter = null;

        window.cambiarPaginaAjax = (p) => cargarListado(p);

        async function cargarListado(page = 1) {
            const b = inputB ? inputB.value.trim() : '';
            const orden = window.CMG_ordenParam ? window.CMG_ordenParam(window.currentSorts) : '';
            // Mismo indicador que el buscador (FiltrosModal): la tabla se atenúa mientras
            // carga, también al paginar u ordenar (que llaman a esta función directo).
            const tbody = document.getElementById('tbodyRoles');
            if (tbody) tbody.classList.add('fm-cargando-target');
            try {
                const resp = await fetch(`${urlBase}/searchAjax?b=${encodeURIComponent(b)}&page=${page}&orden=${encodeURIComponent(orden)}`);
                const data = await resp.json();
                if (data.ok) {
                    window.currentPage = page;
                    document.getElementById('tbodyRoles').innerHTML = data.rows;
                    document.getElementById('wrapper-pagination').innerHTML = data.pagination;
                    document.getElementById('paginationInfo').textContent = data.info;
                    // Los íconos (incluida la prioridad 1/2/3) los repinta el motor global.
                    if (sorter) sorter.refreshIcons();
                }
            } catch (e) {
                console.error(e);
            } finally {
                if (tbody) tbody.classList.remove('fm-cargando-target');
            }
        }

        // Ordenamiento (motor global: persiste la preferencia y pinta los íconos).
        // multi: clic normal ordena por una columna; Shift+clic encadena hasta 3
        // (ASC → DESC → fuera). No recarga la página: cargarListado repinta filas,
        // paginación y contador.
        if (window.CMG_initSort) {
            sorter = window.CMG_initSort('roles_pago', (col, dir, sorts) => {
                window.currentSorts = sorts;
                cargarListado(1);
            }, { sorts: window.currentSorts, multi: true });
        }
        window.addEventListener('rolGuardado', () => cargarListado(window.currentPage || 1));
    })();
</script>
