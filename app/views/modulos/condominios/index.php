<?php
/** @var string $titulo @var array $perm @var string $rutaModulo @var array $vistaConfig @var array $rows
 *  @var int $total @var int $page @var int $perPage @var int $totalPages @var string $buscar
 *  @var string $ordenJson @var string $ordenParam @var bool $instalado @var ?array $config @var array $pendientes
 *  @var ?array $resumen @var array $opcionesFiltro @var bool $puedeConfigurar
 *  @var array $tiposUnidad @var array $metodos @var string $base */
$idModulo = basename($rutaModulo);
$urlBase  = rtrim($base, '/') . '/' . $rutaModulo;
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
$columnasTabla = [
    'codigo'       => 'Código',
    'nombre'       => 'Nombre',
    'tipo'         => 'Tipo',
    'torre_bloque' => 'Torre / Bloque',
    'piso'         => 'Piso',
    'area_m2'      => 'Área m²',
    'alicuota_pct' => 'Alícuota %',
    'propietario'  => 'Propietario',
    'pagador'      => 'Pagador',
    'metodo'       => 'Método',
    'cuota'        => 'Cuota',
    'restringida'  => 'Restricción',
    'estado'       => 'Estado',
];
$opcIdNombre = fn(string $k) => array_map(fn($x) => ['v' => (string) $x['id'], 'l' => (string) $x['nombre']], $opcionesFiltro[$k] ?? []);
$opcSiNo = fn(string $si, string $no) => [['v' => 'si', 'l' => $si], ['v' => 'no', 'l' => $no]];
$tU = 'Inmueble';
// Filas de 12 columnas:
//   Inmueble:    [Código 3][Nombre 3][Tipo 3][Torre 3]
//   Personas:  [Propietario 6][Pagador 3][Con arrendatario 3]
//   Cobro:     [Método 4][Área 4][Alícuota 4]
//   Estado:    [Estado 4][Restringida 4][Usuario 4]
$filtrosUnidades = [
    ['tab' => $tU, 'key' => 'codigo', 'label' => 'Código',         'icon' => 'bi-hash',       'type' => 'text',   'grupo' => 'Inmueble', 'col' => 3],
    ['tab' => $tU, 'key' => 'nombre', 'label' => 'Nombre',         'icon' => 'bi-building',   'type' => 'text',   'grupo' => 'Inmueble', 'col' => 3],
    ['tab' => $tU, 'key' => 'tipo',   'label' => 'Tipo',           'icon' => 'bi-door-open',  'type' => 'select', 'grupo' => 'Inmueble', 'col' => 3,
        'options' => array_map(fn($k, $v) => ['v' => $k, 'l' => $v], array_keys($tiposUnidad), $tiposUnidad)],
    ['tab' => $tU, 'key' => 'torre',  'label' => 'Torre / Bloque', 'icon' => 'bi-buildings',  'type' => 'select', 'grupo' => 'Inmueble', 'col' => 3,
        'options' => array_map(fn($t) => ['v' => (string) $t['nombre'], 'l' => (string) $t['nombre']], $opcionesFiltro['torres'] ?? [])],
    ['tab' => $tU, 'key' => 'id_propietario',   'label' => 'Propietario',       'icon' => 'bi-person',       'type' => 'select', 'grupo' => 'Personas', 'col' => 6, 'options' => $opcIdNombre('propietarios')],
    ['tab' => $tU, 'key' => 'pagador',          'label' => 'Paga',              'icon' => 'bi-cash-coin',    'type' => 'select', 'grupo' => 'Personas', 'col' => 3,
        'options' => [['v' => 'propietario', 'l' => 'Propietario'], ['v' => 'arrendatario', 'l' => 'Arrendatario']]],
    ['tab' => $tU, 'key' => 'con_arrendatario', 'label' => 'Arrendatario',      'icon' => 'bi-person-badge', 'type' => 'select', 'grupo' => 'Personas', 'col' => 3, 'options' => $opcSiNo('Con arrendatario', 'Sin arrendatario')],
    ['tab' => $tU, 'key' => 'metodo',      'label' => 'Método de alícuota', 'icon' => 'bi-calculator', 'type' => 'select', 'grupo' => 'Cobro', 'col' => 4,
        'options' => array_map(fn($k, $v) => ['v' => $k, 'l' => $v], array_keys($metodos), $metodos)],
    ['tab' => $tU, 'key' => 'area',        'label' => 'Área m²',            'icon' => 'bi-rulers',     'type' => 'number_range', 'grupo' => 'Cobro', 'col' => 4],
    ['tab' => $tU, 'key' => 'alicuota',    'label' => 'Alícuota %',         'icon' => 'bi-percent',    'type' => 'number_range', 'grupo' => 'Cobro', 'col' => 4],
    ['tab' => $tU, 'key' => 'estado',      'label' => 'Estado',      'icon' => 'bi-flag',          'type' => 'select', 'grupo' => 'Estado', 'col' => 4,
        'options' => [['v' => 'activo', 'l' => 'Activo'], ['v' => 'inactivo', 'l' => 'Inactivo']]],
    ['tab' => $tU, 'key' => 'restringida', 'label' => 'Áreas comunes', 'icon' => 'bi-slash-circle', 'type' => 'select', 'grupo' => 'Estado', 'col' => 4, 'options' => $opcSiNo('Restringida', 'Sin restricción')],
    ['tab' => $tU, 'key' => 'usuario',     'label' => 'Usuario que registró', 'icon' => 'bi-person-gear', 'type' => 'select', 'grupo' => 'Estado', 'col' => 4, 'options' => $opcIdNombre('usuarios')],
];
?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>
<link rel="stylesheet" href="<?= rtrim($base, '/') ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
<script src="<?= rtrim($base, '/') ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>

<style>
    .cond-row { cursor: pointer; }
    .cond-row:hover { background-color: rgba(0,0,0,.04); }
    .cond-aviso { font-size: .8rem; }
    .cond-dropdown { z-index: 1090; max-height: 240px; overflow-y: auto; }
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-buildings text-primary me-2"></i><?= htmlspecialchars($titulo) ?>
        <?php if ($config): ?><span class="text-muted fw-normal fs-6 ms-2"><?= htmlspecialchars((string) ($config['nombre_condominio'] ?: '')) ?></span><?php endif; ?>
    </h5>
    <?php if ($config && !empty($perm['crear'])): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="COND.nueva()"><i class="bi bi-plus-lg"></i> Nuevo inmueble</button>
    <?php endif; ?>
</div>

<?php if (!$instalado): ?>
    <div class="alert alert-warning cond-aviso py-2"><i class="bi bi-exclamation-triangle me-1"></i>
        El módulo Condominios aún no está instalado en la base de datos: falta aplicar <code>database/migrations/20261005_condominios.sql</code>.
    </div>
<?php elseif (!$config): ?>
    <div class="alert alert-info cond-aviso py-2"><i class="bi bi-info-circle me-1"></i>
        Este condominio aún no está configurado. Vaya a <a href="<?= rtrim($base, '/') ?>/modulos/condominios-config"><b>Configuración de condominios</b></a>, indique el administrador y el producto «Alícuotas» (créelo antes en Productos) para activar el módulo.
    </div>
<?php elseif ($pendientes): ?>
    <div class="alert alert-warning cond-aviso py-2 mb-2" id="cond-aviso-pendientes"><i class="bi bi-exclamation-triangle me-1"></i>
        <b>Falta para poder emitir:</b> <?= htmlspecialchars(implode(' ', $pendientes)) ?> <a href="<?= rtrim($base, '/') ?>/modulos/condominios-config">Ir a Configuración</a>.
    </div>
<?php endif; ?>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <div id="fmBuscadorCOND"></div>
            <input type="hidden" id="cond-buscar" value="<?= htmlspecialchars($buscar) ?>">
            <?php // FiltrosModal (extraId) mueve estos botones dentro del input-group del buscador. ?>
            <div id="fmExtraCOND" class="btn-group btn-group-sm">
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>
                <a id="cond-btn-pdf" href="<?= $urlBase ?>/exportPdf?b=<?= urlencode($buscar) ?>&orden=<?= urlencode($ordenParam) ?>" class="btn btn-outline-danger" title="PDF del listado">
                    <i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span>
                </a>
                <a id="cond-btn-excel" href="<?= $urlBase ?>/exportExcel?b=<?= urlencode($buscar) ?>&orden=<?= urlencode($ordenParam) ?>" class="btn btn-outline-success" title="Excel del listado">
                    <i class="bi bi-file-earmark-spreadsheet"></i><span class="d-none d-md-inline"> Excel</span>
                </a>
                <?php if ($config): ?>
                    <a href="<?= $urlBase ?>/pdfRestringidas" class="btn btn-outline-secondary" title="Inmuebles con restricción de áreas comunes (PDF)" data-pdf-documento>
                        <i class="bi bi-slash-circle"></i><span class="d-none d-md-inline"> Restringidas</span>
                    </a>
                <?php endif; ?>
                <?php if ($config && !empty($perm['crear'])): ?>
                    <button type="button" class="btn btn-outline-primary" onclick="COND.abrirExcel()" title="Cargar inmuebles desde Excel">
                        <i class="bi bi-file-earmark-arrow-up"></i><span class="d-none d-md-inline"> Cargar Excel</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="text-muted small" id="cond-resumen" title="Inmuebles activos · suma de alícuotas · suma de m²">
                <?php if ($resumen): ?>
                    <?= (int) $resumen['activas'] ?> activos · Σ <?= number_format((float) $resumen['suma_pct'], 4) ?> % · <?= number_format((float) $resumen['suma_m2'], 2) ?> m²<?= (int) $resumen['restringidas'] > 0 ? ' · ' . (int) $resumen['restringidas'] . ' restringida(s)' : '' ?>
                <?php endif; ?>
            </span>
            <span id="cond-pag-info" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="cond-paginacion" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" data-pag="-1" <?= $page <= 1 ? 'disabled' : '' ?>><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" data-pag="1" <?= $page >= $totalPages ? 'disabled' : '' ?>><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="condominios-scroll">
            <table class="table table-hover table-sm mb-0 align-middle" id="tabla-cond">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="codigo" data-col="codigo">Código <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="nombre" data-col="nombre">Nombre <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="tipo" data-col="tipo">Tipo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="torre_bloque" data-col="torre_bloque">Torre / Bloque <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="piso" data-col="piso">Piso <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header" role="button" data-sort="area_m2" data-col="area_m2">Área m² <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header" role="button" data-sort="alicuota_pct" data-col="alicuota_pct">Alícuota % <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="propietario" data-col="propietario">Propietario <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="pagador" data-col="pagador">Pagador <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="metodo" data-col="metodo">Método <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end" data-col="cuota" title="Cuota ordinaria mensual estimada con el valor vigente">Cuota</th>
                        <th class="text-center sortable-header" role="button" data-sort="restringida" data-col="restringida">Restricción <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center pe-3 sortable-header" role="button" data-sort="estado" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="cond-tbody">
                    <tr><td colspan="13" class="text-center py-5 text-muted">Cargando…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/modal_unidad.php'; ?>
<?php include __DIR__ . '/modal_excel.php'; ?>

<?= \App\Helpers\PreferenciasHelper::getJavascriptVariables($rutaModulo) ?>
<script>
    window.COND_CFG = {
        url: <?= json_encode($urlBase) ?>,
        urlClientes: <?= json_encode(rtrim($base, '/') . '/modulos/clientes') ?>,
        urlProductos: <?= json_encode(rtrim($base, '/') . '/modulos/productos') ?>,
        modulo: <?= json_encode($idModulo) ?>,
        perm: <?= json_encode(['crear' => !empty($perm['crear']), 'actualizar' => !empty($perm['actualizar']), 'eliminar' => !empty($perm['eliminar'])]) ?>,
        sorts: <?= $ordenJson ?>,
        page: <?= (int) $page ?>,
        instalado: <?= $instalado ? 'true' : 'false' ?>,
        config: <?= json_encode($config, JSON_UNESCAPED_UNICODE) ?>,
        tipos: <?= json_encode($tiposUnidad, JSON_UNESCAPED_UNICODE) ?>,
        metodos: <?= json_encode($metodos, JSON_UNESCAPED_UNICODE) ?>,
        filtros: <?= json_encode($filtrosUnidades, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
    };
</script>
<script src="<?= rtrim($base, '/') ?>/js/modulos/condominios.js?v=<?= asset_ver('/js/modulos/condominios.js') ?>"></script>
