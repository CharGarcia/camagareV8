<?php

use App\Helpers\PreferenciasHelper;

/** @var string $titulo */
/** @var array  $perm */
/** @var string $rowsHtml */
/** @var int    $total */
/** @var int    $page */
/** @var int    $totalPages */
/** @var int    $perPage */
/** @var string $buscar */
/** @var string $ordenCol */
/** @var string $ordenDir */
/** @var array  $vistaConfig */
/** @var array  $anios */
/** @var string $rutaModulo */
/** @var bool   $sinTablas */
/** @var array  $catalogo */

$base       = rtrim(BASE_URL, '/');
$urlBaseAdi = $base . '/' . ltrim($rutaModulo, '/');

$total      = $total ?? 0;
$page       = $page ?? 1;
$totalPages = $totalPages ?? 1;
$perPage    = $perPage ?? 20;
$ordenCol   = $ordenCol ?? 'anio';
$ordenDir   = $ordenDir ?? 'desc';
$buscar     = $buscar ?? '';
$anios      = $anios ?? [];

$desde = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$hasta = $total > 0 ? min($page * $perPage, $total) : 0;

$columnasTabla = [
    'anio'                => 'Año',
    'id_informante'       => 'Identificación',
    'razon_social'        => 'Informante',
    'tipo_informante'     => 'Tipo informante',
    'total_beneficiarios' => 'Beneficiarios',
    'total_distribuido'   => 'Distribuido',
    'total_gravado'       => 'Ingreso gravado',
    'total_retencion'     => 'Retención',
    'estado'              => 'Estado',
];

$pestanas = [
    'adi-pane-informante' => 'Informante y origen',
    'adi-pane-utilidades' => 'Utilidades',
    'adi-pane-dividendos' => 'Dividendos',
    'adi-pane-validacion' => 'Validaciones',
];
?>
<style>
    .adi-header { flex-shrink: 0; }

    .adi-scroll { max-height: calc(100dvh - 240px); overflow-y: auto; }

    .adi-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }

    .adi-row { cursor: pointer; }
    .adi-row:hover { background-color: rgba(0, 0, 0, .04); }

    #modalAdi .table-sm td { vertical-align: middle; }

    /* El modal no tiene scroll vertical propio: crece con su contenido y quien
       se desplaza es la ventana del modal. La tabla de dividendos solo conserva
       el desplazamiento horizontal para cuando las columnas no caben.
       El nombre de la clase evita a propósito el sufijo "-scroll": el app-shell
       fuerza height:100% y overflow-y:auto sobre cualquier clase que lo lleve
       (app.css), y eso volvería a meter una barra vertical dentro del modal. */
    #modalAdi .adi-tabla-dividendos { overflow-x: auto; }
    #modalAdi .adi-tabla-dividendos thead th { background: #f8f9fa; }

    .adi-nota { font-size: .78rem; }
    .adi-tercero-lista { position: absolute; z-index: 5090; width: 100%; max-height: 220px; overflow-y: auto; }

    /* Resultados del buscador de cuentas: desplegable acotado, igual que el de
       terceros, para que no empuje el contenido del modal. */
    .adi-cuenta-lista { position: absolute; z-index: 5090; width: 100%; max-height: 240px; overflow-y: auto; }
</style>
<?= PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>
<?= PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>

<div class="adi-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-cash-coin text-success"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear'] && !$sinTablas): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="ADI_nuevo()">
            <i class="bi bi-plus-lg"></i> Nuevo
        </button>
    <?php endif; ?>
</div>

<?php if ($sinTablas): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-1"></i>
        Las tablas del módulo aún no existen en esta base. Ejecute
        <code>database/2026-09-08_anexo_dividendos_adi.sql</code> y vuelva a entrar.
    </div>
<?php endif; ?>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">

        <!-- Buscador y exportación -->
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php
            $opcionesUsuario     = array_map(fn($u) => ['v' => (string) $u['id'], 'l' => $u['nombre']], $usuariosFiltro ?? []);
            $opcionesTipoInf     = [];
            foreach (($catalogo['tipo_informante'] ?? []) as $cod => $nom) { $opcionesTipoInf[] = ['v' => (string) $cod, 'l' => $cod . ' - ' . $nom]; }
            $opcionesTipoBenef   = [];
            foreach (($catalogo['tipo_beneficiario'] ?? []) as $cod => $nom) { $opcionesTipoBenef[] = ['v' => (string) $cod, 'l' => $cod . ' - ' . $nom]; }
            $opcionesTipoDiv     = [];
            foreach (($catalogo['tipo_dividendo'] ?? []) as $cod => $def) { $opcionesTipoDiv[] = ['v' => (string) $cod, 'l' => $cod . ' - ' . (is_array($def) ? $def[0] : $def)]; }
            $tA = 'Anexo';
            // Filas de 12 columnas:
            //   Anexo:      [Fecha de registro contable 6][Año informado 6]
            //               [Estado 4][Tipo de informante 4][Con beneficiarios 4]
            //               [Usuario 6][Observaciones 6]
            //   Valores:    [Distribuido 4][Ingreso gravado 4][Retención 4]
            //               [Nº de beneficiarios 6][Utilidad del ejercicio 6]
            //   Informante: [Informante 6][Identificación 6]
            //   Distribución: [Tipo de beneficiario 6][Tipo de dividendo 6]
            // No hay fecha en la cabecera: la fecha principal es la de registro contable
            // de las líneas de la distribución (filtro EXISTS en el repository).
            $filtrosAdi = [
                ['tab' => $tA, 'key' => 'fecha_registro',  'label' => 'Fecha de registro contable del dividendo', 'icon' => 'bi-calendar-event', 'type' => 'date_range', 'grupo' => 'Anexo', 'col' => 6, 'atajos' => true],
                ['tab' => $tA, 'key' => 'anio',            'label' => 'Año informado',     'icon' => 'bi-calendar3',      'type' => 'number_range', 'grupo' => 'Anexo', 'col' => 6],
                ['tab' => $tA, 'key' => 'estado',          'label' => 'Estado',            'icon' => 'bi-flag',           'type' => 'select', 'grupo' => 'Anexo', 'col' => 4, 'options' => [
                    ['v' => 'borrador',   'l' => 'Borrador'],
                    ['v' => 'generado',   'l' => 'Generado'],
                    ['v' => 'presentado', 'l' => 'Presentado'],
                ]],
                ['tab' => $tA, 'key' => 'tipo_informante', 'label' => 'Tipo de informante', 'icon' => 'bi-people',        'type' => 'select', 'grupo' => 'Anexo', 'col' => 4, 'options' => $opcionesTipoInf],
                ['tab' => $tA, 'key' => 'con_beneficiarios', 'label' => 'Beneficiarios',   'icon' => 'bi-person-lines-fill', 'type' => 'select', 'grupo' => 'Anexo', 'col' => 4, 'options' => [
                    ['v' => 'si', 'l' => 'Con beneficiarios'],
                    ['v' => 'no', 'l' => 'Sin beneficiarios'],
                ]],
                ['tab' => $tA, 'key' => 'id_usuario',      'label' => 'Usuario que lo creó', 'icon' => 'bi-person-gear',  'type' => 'select', 'grupo' => 'Anexo', 'col' => 6, 'options' => $opcionesUsuario],
                ['tab' => $tA, 'key' => 'observaciones',   'label' => 'Observaciones',     'icon' => 'bi-chat-left-text', 'type' => 'text',   'grupo' => 'Anexo', 'col' => 6],
                // Valores
                ['tab' => $tA, 'key' => 'distribuido',     'label' => 'Dividendo distribuido', 'icon' => 'bi-currency-dollar', 'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                ['tab' => $tA, 'key' => 'gravado',         'label' => 'Ingreso gravado',   'icon' => 'bi-receipt',        'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                ['tab' => $tA, 'key' => 'retencion',       'label' => 'Retención',         'icon' => 'bi-percent',        'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                ['tab' => $tA, 'key' => 'beneficiarios',   'label' => 'Número de beneficiarios', 'icon' => 'bi-123',      'type' => 'number_range', 'grupo' => 'Valores', 'col' => 6],
                ['tab' => $tA, 'key' => 'utilidad',        'label' => 'Utilidad del ejercicio', 'icon' => 'bi-graph-up',  'type' => 'number_range', 'grupo' => 'Valores', 'col' => 6],
                // Informante
                ['tab' => $tA, 'key' => 'informante',      'label' => 'Informante',        'icon' => 'bi-building',       'type' => 'text',   'grupo' => 'Informante', 'col' => 6],
                ['tab' => $tA, 'key' => 'ruc',             'label' => 'Identificación',    'icon' => 'bi-card-text',      'type' => 'text',   'grupo' => 'Informante', 'col' => 6],
                // Distribución (líneas del anexo)
                ['tab' => $tA, 'key' => 'tipo_beneficiario', 'label' => 'Tipo de beneficiario', 'icon' => 'bi-person-badge', 'type' => 'select', 'grupo' => 'Distribución', 'col' => 6, 'options' => $opcionesTipoBenef],
                ['tab' => $tA, 'key' => 'tipo_dividendo',  'label' => 'Tipo de dividendo', 'icon' => 'bi-cash-stack',     'type' => 'select', 'grupo' => 'Distribución', 'col' => 6, 'options' => $opcionesTipoDiv],
            ];
            ?>
            <link rel="stylesheet" href="<?= $base ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
            <script src="<?= $base ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
            <div id="fmBuscadorADI"></div>
            <input type="hidden" id="buscarAdi" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosModal) return;
                    window.ADI_filtros = new FiltrosModal({
                        containerId: 'fmBuscadorADI',
                        hiddenInputId: 'buscarAdi',
                        placeholder: 'Buscar en todas las columnas...',
                        titulo: 'Filtros del anexo de dividendos',
                        inputWidth: 420,
                        extraId: 'fmExtraADI',   // columnas + PDF + Excel, pegados al final del grupo
                        // Pestaña Detalles: búsqueda libre dentro de los beneficiarios y la distribución.
                        busquedaDetalle: {
                            tab: 'Detalles',
                            url: `<?= $urlBaseAdi ?>/buscarDetallesAjax`,
                            label: 'Buscar libremente dentro de los anexos',
                            placeholder: 'Identificación o nombre del beneficiario, año de la utilidad, fecha, monto...',
                            columns: [
                                { key: 'origen',      label: 'Tipo' },
                                { key: 'tipo',        label: 'Identificación' },
                                { key: 'descripcion', label: 'Beneficiario' },
                                { key: 'clase',       label: 'Tipo de beneficiario / dividendo' },
                                { key: 'fecha_linea', label: 'Fecha contable' },
                                { key: 'monto',       label: 'Distribuido', align: 'end' },
                                { key: 'anio',        label: 'Año', class: 'fw-semibold' },
                                { key: 'estado',      label: 'Estado' },
                            ],
                            onSelect: (row, fm) => fm.aplicarFiltro({ key: 'anio', op: '=', value: String(row.anio) }),
                            onOpen: (row, fm) => {
                                fm.hide();
                                setTimeout(() => window.ADI_abrir(row.id), 350);
                            },
                        },
                        fields: <?= json_encode($filtrosAdi, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                        loadingTarget: '#tbodyAdi',   // se atenúa mientras se busca
                        onApply: () => window.fetchSearch && window.fetchSearch(1),
                    });
                    window.ADI_filtros.init();
                });
            </script>

            <?php // FiltrosModal (extraId) mueve estos botones dentro del grupo del buscador; si el JS no corre, quedan aquí. ?>
            <div id="fmExtraADI" class="btn-group btn-group-sm">
                <?= PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>

                <a id="btnExportPdfAdi" class="btn btn-outline-danger" title="Descargar PDF"
                   href="<?= $urlBaseAdi ?>/export-pdf?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>">
                    <i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span>
                </a>
                <a id="btnExportExcelAdi" class="btn btn-outline-success" title="Descargar Excel"
                   href="<?= $urlBaseAdi ?>/export-excel?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>">
                    <i class="bi bi-file-earmark-spreadsheet"></i><span class="d-none d-md-inline"> Excel</span>
                </a>
            </div>
        </div>

        <!-- Paginación -->
        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfoAdi" class="text-muted small fw-medium"><?= $desde ?>-<?= $hasta ?>/<?= $total ?></span>
            <div id="paginationContainerAdi" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary border-end-0 rounded-end-0" <?= $page <= 1 ? 'disabled' : '' ?>
                        onclick="cambiarPaginaAjax(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary rounded-start-0" <?= $page >= $totalPages ? 'disabled' : '' ?>
                        onclick="cambiarPaginaAjax(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card-body p-0">
        <div class="adi-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="anio" data-col="anio">Año <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="id_informante" data-col="id_informante">Identificación <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="razon_social" data-col="razon_social">Informante <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="tipo_informante" data-col="tipo_informante">Tipo informante <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-center" role="button" data-sort="total_beneficiarios" data-col="total_beneficiarios">Beneficiarios <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-end" role="button" data-sort="total_distribuido" data-col="total_distribuido">Distribuido <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-end" role="button" data-sort="total_gravado" data-col="total_gravado">Ingreso gravado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-end" role="button" data-sort="total_retencion" data-col="total_retencion">Retención <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-center pe-3" role="button" data-sort="estado" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyAdi"><?= $rowsHtml ?></tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/modal.php'; ?>
<?php require __DIR__ . '/modal_beneficiario.php'; ?>
<?php require __DIR__ . '/modal_detalle.php'; ?>

<script>
    window.ADI_URL_BASE  = '<?= $urlBaseAdi ?>';
    window.ADI_PERM      = <?= json_encode($perm) ?>;
    // Los 257 países ya viajan como <option> del modal, así que se excluyen del
    // catálogo que consume el JavaScript para no duplicarlos en la página.
    window.ADI_CAT       = <?= json_encode(array_diff_key($catalogo, ['paises' => null]), JSON_UNESCAPED_UNICODE) ?>;
    window.ADI_MODULO    = '<?= $rutaModulo ?>';
    window.ADI_SORT_INI  = { col: '<?= htmlspecialchars($ordenCol) ?>', dir: '<?= htmlspecialchars($ordenDir) ?>' };
    window.ADI_DEFAULTS  = <?= json_encode($defaults ?? [], JSON_UNESCAPED_UNICODE) ?>;
    window.ADI_SBUS      = <?= json_encode((object) ($sbus ?? []), JSON_UNESCAPED_UNICODE) ?>;
</script>
<?= PreferenciasHelper::getJavascriptVariables($rutaModulo) ?>
<script src="<?= $base ?>/js/modulos/anexo_dividendos.js?v=<?= asset_ver('/js/modulos/anexo_dividendos.js') ?>"></script>
