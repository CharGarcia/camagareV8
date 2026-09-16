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
/** @var array $empresa */
/** @var array $establecimientos */
/** @var array $puntos */
/** @var array $formasPago */
/** @var array $seriesFiltro */
/** @var array $vistaConfig */

$base = BASE_URL;
$urlBase = $base . '/' . $rutaModulo;

$rows       = $rows ?? [];
$total      = $total ?? 0;
$page       = $page ?? 1;
$totalPages = $totalPages ?? 1;
$perPage    = $perPage ?? 20;
$ordenCol   = $ordenCol ?? 'fecha_emision';
$ordenDir   = $ordenDir ?? 'DESC';
$buscar     = $buscar ?? '';
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>

<style>
    .trp-header { flex-shrink: 0; }
    .traspasos-scroll {
        max-height: calc(100dvh - 240px);
        overflow-y: auto;
    }
    .traspasos-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }
    .traspaso-row { cursor: pointer; }
    .traspaso-row:hover { background-color: rgba(0, 0, 0, .04); }
    .trp-saldo-hint { font-size: .78rem; }
</style>

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<div class="trp-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-arrow-left-right me-1 text-primary"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="abrirModalTraspaso()">
            <i class="bi bi-plus-lg me-1"></i> Nuevo
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php
            $vf = $valoresFiltro ?? [];
            $opcionesSerie   = array_map(fn($s) => ['v' => $s['establecimiento'] . '-' . $s['punto_emision'], 'l' => $s['establecimiento'] . '-' . $s['punto_emision']], $seriesFiltro ?? []);
            $opcionesOrigen  = array_map(fn($f) => ['v' => (string) $f['id'], 'l' => $f['nombre']], $vf['origenes'] ?? []);
            $opcionesDestino = array_map(fn($f) => ['v' => (string) $f['id'], 'l' => $f['nombre']], $vf['destinos'] ?? []);
            $opcionesUsuario = array_map(fn($u) => ['v' => (string) $u['id'], 'l' => $u['nombre']], $vf['usuarios'] ?? []);
            $tT = 'Traspaso';
            // Filas de 12 columnas:
            //   Documento: [Fecha 6][Estado 3][Asiento 3]
            //              [Serie 4][Secuencial 4][Nº traspaso 4]
            //              [Usuario 6][Monto 6]
            //   Cuentas:   [Origen 6][Destino 6]
            //              [Observación 12]
            // Sin pestaña Detalles: el traspaso no tiene tablas hijas.
            $filtrosTraspasos = [
                ['tab' => $tT, 'key' => 'fecha',      'label' => 'Fecha',           'icon' => 'bi-calendar-event',  'type' => 'date_range', 'grupo' => 'Documento', 'col' => 6, 'atajos' => true],
                ['tab' => $tT, 'key' => 'estado',     'label' => 'Estado',          'icon' => 'bi-flag',            'type' => 'select',     'grupo' => 'Documento', 'col' => 3, 'options' => [
                    ['v' => 'registrado', 'l' => 'Registrado'],
                    ['v' => 'anulado',    'l' => 'Anulado'],
                ]],
                ['tab' => $tT, 'key' => 'asiento',    'label' => 'Asiento contable', 'icon' => 'bi-journal-check',  'type' => 'select',     'grupo' => 'Documento', 'col' => 3, 'options' => [
                    ['v' => 'si', 'l' => 'Con asiento'],
                    ['v' => 'no', 'l' => 'Sin asiento'],
                ]],
                ['tab' => $tT, 'key' => 'serie',      'label' => 'Serie',           'icon' => 'bi-upc-scan',        'type' => 'select',     'grupo' => 'Documento', 'col' => 4, 'options' => $opcionesSerie],
                ['tab' => $tT, 'key' => 'secuencial', 'label' => 'Secuencial',      'icon' => 'bi-123',             'type' => 'text',       'grupo' => 'Documento', 'col' => 4, 'placeholder' => 'Sin ceros'],
                ['tab' => $tT, 'key' => 'numero',     'label' => 'Nº traspaso',     'icon' => 'bi-hash',            'type' => 'text',       'grupo' => 'Documento', 'col' => 4, 'placeholder' => '001-001-000000123'],
                ['tab' => $tT, 'key' => 'id_usuario', 'label' => 'Usuario que registró', 'icon' => 'bi-person-gear', 'type' => 'select',    'grupo' => 'Documento', 'col' => 6, 'options' => $opcionesUsuario],
                ['tab' => $tT, 'key' => 'monto',      'label' => 'Monto',           'icon' => 'bi-currency-dollar', 'type' => 'number_range', 'grupo' => 'Documento', 'col' => 6],
                // Cuentas
                ['tab' => $tT, 'key' => 'id_origen',  'label' => 'Origen',          'icon' => 'bi-box-arrow-up-right',     'type' => 'select', 'grupo' => 'Cuentas', 'col' => 6, 'options' => $opcionesOrigen],
                ['tab' => $tT, 'key' => 'id_destino', 'label' => 'Destino',         'icon' => 'bi-box-arrow-in-down-left', 'type' => 'select', 'grupo' => 'Cuentas', 'col' => 6, 'options' => $opcionesDestino],
                ['tab' => $tT, 'key' => 'obs',        'label' => 'Observación',     'icon' => 'bi-chat-left-text',  'type' => 'text',       'grupo' => 'Cuentas', 'col' => 12],
            ];
            ?>
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
            <div id="fmBuscadorTRP"></div>
            <input type="hidden" id="buscarTraspaso" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosModal) return;
                    window.TRP_filtros = new FiltrosModal({
                        containerId: 'fmBuscadorTRP',
                        hiddenInputId: 'buscarTraspaso',
                        placeholder: 'Buscar en todas las columnas...',
                        titulo: 'Filtros de traspasos',
                        inputWidth: 420,
                        extraId: 'fmExtraTRP',   // columnas, pegadas al final del grupo
                        fields: <?= json_encode($filtrosTraspasos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                        loadingTarget: '#tbodyTraspasos',   // se atenúa mientras se busca
                        onApply: () => window.TRP_fetchSearch && window.TRP_fetchSearch(1),
                    });
                    window.TRP_filtros.init();
                });
            </script>

            <?php // FiltrosModal (extraId) mueve estos botones dentro del grupo del buscador; si el JS no corre, quedan aquí. ?>
            <div id="fmExtraTRP" class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'numero_traspaso' => 'Nº Traspaso',
                    'fecha_emision'   => 'Fecha',
                    'origen_nombre'   => 'Origen',
                    'destino_nombre'  => 'Destino',
                    'monto'           => 'Monto',
                    'estado'          => 'Estado',
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="paginationContainer" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" <?= $page <= 1 ? 'disabled' : '' ?> onclick="window.TRP_cambiarPaginaAjax(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" <?= $page >= $totalPages ? 'disabled' : '' ?> onclick="window.TRP_cambiarPaginaAjax(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="traspasos-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <?php
                        $renderHeader = function ($key, $label, $align = 'left', $extraClass = '') use ($ordenCol, $ordenDir) {
                            $isCur = ($ordenCol === $key);
                            $icon = 'bi-arrow-down-up small text-muted';
                            if ($isCur) {
                                $icon = (strtoupper($ordenDir) === 'ASC') ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary';
                            }
                            $cls = match ($align) {
                                'center' => 'text-center',
                                'right'  => 'text-end',
                                default  => '',
                            };
                            echo '<th class="ps-3 py-2 sortable-header ' . $cls . ' ' . $extraClass . '" role="button" data-col="' . $key . '" onclick="window.TRP_sort(\'' . $key . '\')">' . $label . ' <i class="bi ' . $icon . ' ms-1"></i></th>';
                        };
                        ?>
                        <?php $renderHeader('numero_traspaso', 'Nº Traspaso'); ?>
                        <?php $renderHeader('fecha_emision', 'Fecha'); ?>
                        <th data-col="origen_nombre">Origen</th>
                        <th data-col="destino_nombre">Destino</th>
                        <?php $renderHeader('monto', 'Monto', 'right'); ?>
                        <?php $renderHeader('estado', 'Estado', 'center', 'pe-3'); ?>
                    </tr>
                </thead>
                <tbody id="tbodyTraspasos">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-arrow-left-right fs-3 d-block mb-2"></i>No se encontraron traspasos.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $estado = $r['estado'] ?? 'registrado';
                            $estadoClass = match ($estado) {
                                'anulado' => 'bg-danger bg-opacity-10 text-danger border-danger',
                                default   => 'bg-primary bg-opacity-10 text-primary border-primary',
                            };
                            ?>
                            <tr class="traspaso-row" role="button" onclick="abrirModalTraspasoVer(<?= $r['id'] ?>)">
                                <td class="ps-3" data-col="numero_traspaso"><code><?= htmlspecialchars($r['numero_traspaso'] ?? '') ?></code></td>
                                <td data-col="fecha_emision"><?= !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '-' ?></td>
                                <td data-col="origen_nombre"><span class="badge bg-light text-dark border"><?= htmlspecialchars($r['origen_nombre'] ?? '') ?></span></td>
                                <td data-col="destino_nombre"><span class="badge bg-light text-dark border"><?= htmlspecialchars($r['destino_nombre'] ?? '') ?></span> <i class="bi bi-arrow-right text-muted small"></i></td>
                                <td class="text-end fw-bold" data-col="monto">$<?= number_format((float) $r['monto'], 2) ?></td>
                                <td class="text-center pe-3" data-col="estado"><span class="badge <?= $estadoClass ?> border border-opacity-25"><?= ucfirst($estado) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/modal_traspaso.php'; ?>

<script>
    window.TRP_URL_BASE   = '<?= $urlBase ?>';
    window.TRP_FORMAS_PAGO = <?= json_encode($formasPago, JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    window.TRP_PUNTOS      = <?= json_encode($puntos, JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    window.TRP_ORDEN_COL   = '<?= $ordenCol ?>';
    window.TRP_ORDEN_DIR   = '<?= $ordenDir ?>';
    window.TRP_PAGE        = <?= $page ?>;
</script>
<script src="<?= rtrim(BASE_URL, '/') ?>/js/modulos/asiento_contable_tab.js?v=<?= asset_ver('/js/modulos/asiento_contable_tab.js') ?>"></script>
<script src="<?= rtrim(BASE_URL, '/') ?>/js/modulos/traspasos.js?v=<?= asset_ver('/js/modulos/traspasos.js') ?>"></script>
