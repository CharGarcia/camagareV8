<?php
/**
 * Conciliación de Tarjetas — pantalla principal.
 *
 * Listado estándar (§9, mismo diseño que Proveedores): título con el botón Nueva
 * arriba y una sola tarjeta de listado (`cmg-table-card`, app-shell) con el buscador
 * FiltrosModal (texto libre + embudo con todos los filtros + chips), columnas,
 * PDF, Excel y paginación. El cruce se hace en el modal (modal_conciliacion.php).
 *
 * Los filtros viajan serializados en el buscador (`clave:valor`) y los resuelve
 * ConciliacionTarjetasRepository::armarConsultaListado() con FiltrosBusqueda: las
 * claves (numero, estado, fecha, procesadora, destino, neto, diferencia) deben existir
 * en sus mapas.
 */
$puedeCrear      = !empty($perm['crear']);
$puedeActualizar = !empty($perm['actualizar']);
$puedeEliminar   = !empty($perm['eliminar']);
$vistaConfig     = $vistaConfig ?? [];

$columnas = [
    // data-col => [etiqueta, data-sort, clases]
    'c_numero'      => ['Número', 'numero', 'ps-3'],
    'c_fecha'       => ['Fecha', 'fecha', ''],
    'c_procesadora' => ['Procesadora', 'procesadora', ''],
    'c_destino'     => ['Depositado en', 'destino', ''],
    'c_cobros'      => ['Cobros', 'cobros', 'text-center'],
    'c_bruto'       => ['Bruto', 'bruto', 'text-end'],
    'c_comision'    => ['Comisión', 'comision', 'text-end'],
    'c_retenciones' => ['Retenciones', 'retenciones', 'text-end'],
    'c_neto'        => ['Neto', 'neto', 'text-end'],
    'c_estado'      => ['Estado', 'estado', 'text-center pe-3'],
];

// Filtros del embudo (FiltrosModal), en filas de 12 columnas:
//   Conciliación:     [Número 3][Estado 3][Fecha 6]
//   Formas de cobro:  [Procesadora 6][Depositado en 6]
//   Valores:          [Neto depositado 6][Diferencia 6]
// Procesadora y destino filtran por nombre (mapa 'texto' del repository).
$tC = 'Conciliación';
$opcNombres = static fn(array $filas) => array_values(array_map(
    static fn($n) => ['v' => $n, 'l' => $n],
    array_unique(array_map(static fn($f) => (string) $f['nombre'], $filas))
));
$filtrosCtar = [
    ['tab' => $tC, 'key' => 'numero', 'label' => 'Número', 'icon' => 'bi-hash', 'type' => 'text', 'grupo' => 'Conciliación', 'col' => 3],
    ['tab' => $tC, 'key' => 'estado', 'label' => 'Estado', 'icon' => 'bi-flag', 'type' => 'select', 'grupo' => 'Conciliación', 'col' => 3, 'options' => [
        ['v' => 'borrador', 'l' => 'En borrador'],
        ['v' => 'cerrada',  'l' => 'Cerrada'],
        ['v' => 'anulada',  'l' => 'Anulada'],
    ]],
    ['tab' => $tC, 'key' => 'fecha', 'label' => 'Fecha de depósito', 'icon' => 'bi-calendar-event', 'type' => 'date_range', 'grupo' => 'Conciliación', 'col' => 6, 'atajos' => true],
    ['tab' => $tC, 'key' => 'procesadora', 'label' => 'Procesadora', 'icon' => 'bi-credit-card', 'type' => 'select', 'grupo' => 'Formas de cobro', 'col' => 6, 'options' => $opcNombres($procesadoras)],
    ['tab' => $tC, 'key' => 'destino', 'label' => 'Depositado en', 'icon' => 'bi-bank', 'type' => 'select', 'grupo' => 'Formas de cobro', 'col' => 6, 'options' => $opcNombres($destinos)],
    ['tab' => $tC, 'key' => 'neto', 'label' => 'Neto depositado', 'icon' => 'bi-cash-stack', 'type' => 'number_range', 'grupo' => 'Valores', 'col' => 6],
    ['tab' => $tC, 'key' => 'diferencia', 'label' => 'Diferencia', 'icon' => 'bi-plus-slash-minus', 'type' => 'number_range', 'grupo' => 'Valores', 'col' => 6],
];
?>
<style>
    .ctar-header { flex-shrink: 0; }
    .ctar-scroll { max-height: calc(100dvh - 240px); overflow-y: auto; }
    .ctar-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; white-space: nowrap; }
    .ctar-scroll tbody td { white-space: nowrap; }
    .ctar-scroll thead th.sortable-header { cursor: pointer; user-select: none; }
    .ctar-scroll thead th.sortable-header:hover { background: #eef2f5; }
    .ctar-fila { cursor: pointer; }

    /* Semáforo de atraso de los cobros (lista «Cobros del sistema» del modal) */
    .ctar-dias-ok      { background:rgba(25,135,84,.12);  color:#198754; border:1px solid rgba(25,135,84,.25); }
    .ctar-dias-alerta  { background:rgba(255,193,7,.15);  color:#856404; border:1px solid rgba(255,193,7,.35); }
    .ctar-dias-tarde   { background:rgba(220,53,69,.12);  color:#dc3545; border:1px solid rgba(220,53,69,.25); }

    .ctar-estado-borrador { background:rgba(108,117,125,.12); color:#6c757d; border:1px solid rgba(108,117,125,.25); }
    .ctar-estado-cerrada  { background:rgba(25,135,84,.12);   color:#198754; border:1px solid rgba(25,135,84,.25); }
    .ctar-estado-anulada  { background:rgba(220,53,69,.12);   color:#dc3545; border:1px solid rgba(220,53,69,.25); }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig) ?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig) ?>

<div class="ctar-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-credit-card-2-front"></i> Conciliación de Tarjetas</h5>
    <?php if ($puedeCrear): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="CTAR_nueva()"><i class="bi bi-plus-lg"></i> Nueva</button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <!-- Buscador y exportación -->
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
            <div id="fmBuscadorCTAR"></div>
            <input type="hidden" id="ctar-buscar" value="">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosModal) return;
                    new FiltrosModal({
                        containerId: 'fmBuscadorCTAR',
                        hiddenInputId: 'ctar-buscar',
                        placeholder: 'Buscar en todas las columnas...',
                        titulo: 'Filtros de conciliaciones de tarjetas',
                        inputWidth: 420,
                        extraId: 'fmExtraCTAR',   // columnas + PDF + Excel, pegados al final del grupo
                        fields: <?= json_encode($filtrosCtar, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                        loadingTarget: '#ctar-tbody',   // se atenúa mientras se busca
                        onApply: () => window.CTAR_cargar && window.CTAR_cargar(1),
                    }).init();
                });
            </script>

            <?php // FiltrosModal (extraId) mueve estos botones dentro del input-group del buscador; si el JS no corre, quedan aquí. ?>
            <div id="fmExtraCTAR" class="btn-group btn-group-sm">
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas(
                    array_map(static fn($c) => $c[0], $columnas),
                    $vistaConfig,
                    $rutaModulo
                ) ?>
                <button type="button" class="btn btn-outline-danger" onclick="CTAR_exportarPDF()" title="Descargar PDF">
                    <i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span>
                </button>
                <button type="button" class="btn btn-outline-success" onclick="CTAR_exportarExcel()" title="Descargar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i><span class="d-none d-md-inline"> Excel</span>
                </button>
            </div>
        </div>

        <!-- Paginación -->
        <div class="d-flex align-items-center gap-3">
            <span id="ctar-info" class="text-muted small fw-medium"></span>
            <div id="ctar-pag" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" disabled data-pag="prev" title="Página anterior"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" disabled data-pag="next" title="Página siguiente"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card-body p-0">
        <div class="ctar-scroll w-100">
            <table class="table table-hover table-sm mb-0 align-middle" id="tabla-ctar">
                <thead class="table-light">
                    <tr>
                        <?php foreach ($columnas as $col => [$etiqueta, $sort, $clases]): ?>
                            <?php if ($sort !== null): ?>
                                <th class="<?php echo $clases; ?> sortable-header" data-col="<?php echo $col; ?>" data-sort="<?php echo $sort; ?>" role="button">
                                    <?php echo htmlspecialchars($etiqueta); ?> <i class="bi bi-arrow-down-up small text-muted ms-1"></i>
                                </th>
                            <?php else: ?>
                                <th class="<?php echo $clases; ?>" data-col="<?php echo $col; ?>"><?php echo htmlspecialchars($etiqueta); ?></th>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody id="ctar-tbody">
                    <tr><td colspan="<?php echo count($columnas); ?>" class="text-center py-5 text-muted">
                        <i class="bi bi-list-check fs-3 d-block mb-2 text-primary opacity-50"></i>Cargando conciliaciones…
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/modal_conciliacion.php'; ?>

<script>
    const CTAR_URL   = '<?php echo BASE_URL; ?>/modulos/conciliacion-tarjetas';
    const CTAR_PERM  = {
        crear:      <?php echo $puedeCrear ? 'true' : 'false'; ?>,
        actualizar: <?php echo $puedeActualizar ? 'true' : 'false'; ?>,
        eliminar:   <?php echo $puedeEliminar ? 'true' : 'false'; ?>
    };
    const CTAR_PROCESADORAS = <?php echo json_encode($procesadoras, JSON_UNESCAPED_UNICODE); ?>;
    const CTAR_DESTINOS     = <?php echo json_encode($destinos, JSON_UNESCAPED_UNICODE); ?>;
    // Estado inicial del orden (OrdenListado::aJson), para CMG_initSort.
    const CTAR_ORDEN = { modulo: '<?php echo $rutaModulo; ?>', sorts: <?php echo $ordenJson; ?> };
</script>
<script src="<?php echo BASE_URL; ?>/js/modulos/asiento_contable_tab.js?v=<?= asset_ver('/js/modulos/asiento_contable_tab.js') ?>"></script>
<script src="<?php echo BASE_URL; ?>/js/modulos/conciliacion_tarjetas.js?v=<?= asset_ver('/js/modulos/conciliacion_tarjetas.js') ?>"></script>
