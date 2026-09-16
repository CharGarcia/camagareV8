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
    .nc-scroll {
        max-height: calc(100dvh - 240px);
        overflow-y: auto;
    }

    .nc-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 10;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }

    .nc-row {
        cursor: pointer;
    }

    .nc-row:hover {
        background-color: rgba(0, 0, 0, .04);
    }

    /* Estilos para el Formulario en Modal */
    .modal-nc .modal-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
        padding: 0.75rem 1rem;
    }

    .modal-nc .modal-body {
        padding: 0 !important;
    }

    .modal-nc label {
        font-size: 0.85rem;
        font-weight: 600;
        color: #495057;
        margin-bottom: 3px !important;
    }

    .table-detalle th {
        font-size: 0.7rem !important;
        text-transform: uppercase;
        background-color: #f8f9fa;
        padding: 4px 8px !important;
    }

    .input-detalle {
        border: none;
        background: transparent;
        font-size: 0.82rem !important;
        padding: 2px 8px !important;
        height: 30px !important;
        width: 100%;
    }

    .input-detalle:focus {
        background: #fff;
        box-shadow: inset 0 0 0 1px #0d6efd;
        outline: none;
    }

    .row-detalle:hover {
        background-color: rgba(13, 110, 253, 0.03);
    }
</style>

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>

<div class="nc-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-file-earmark-minus me-2 text-primary"></i><?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="window.NC_abrirModalNuevo()">
            <i class="bi bi-plus-lg me-1"></i> Nueva
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php
            // Buscador: texto libre sobre las columnas del listado (sin sugerencias) + botón
            // "Filtros" que abre un modal con todos los filtros + chips de los activos.
            // Las claves (key) deben existir en los mapas de NotaCreditoRepository::getListado().
            $opcionesSerie   = array_map(fn($s) => ['v' => $s['establecimiento'] . '-' . $s['punto_emision'], 'l' => $s['establecimiento'] . '-' . $s['punto_emision']], $seriesFiltro ?? []);
            $opcionesUsuario = array_map(fn($u) => ['v' => (string) $u['id'], 'l' => $u['nombre']], $usuariosFiltro ?? []);
            // Dos pestañas: "Nota de crédito" (filtros por campo de la cabecera) y "Detalles"
            // (solo la búsqueda libre dentro de las notas, ver `busquedaDetalle` abajo).
            $tN = 'Nota de crédito';
            // Orden pensado en filas de 12 columnas:
            //   Documento:          [Fecha de emisión 6][Estado 3][Correo 3]
            //                       [Serie 3][Nº nota 3][Secuencial 2][Asiento 4]
            //                       [Fecha de autorización 6][Usuario que registró 6]
            //   Documento modificado: [Nº documento 4][Fecha del sustento 5][Motivo 3]
            //   Valores:            [Total 4][Subtotal 4][Descuento 4]
            //   Cliente:            [Cliente 4][RUC 4][Observaciones 4]
            //                       [Nº autorización 6][Clave de acceso 6]
            $filtrosNotasCredito = [
                // ── Documento ──
                ['tab' => $tN, 'key' => 'fecha',      'label' => 'Fecha de emisión', 'icon' => 'bi-calendar-event', 'type' => 'date_range', 'grupo' => 'Documento', 'col' => 6, 'atajos' => true],
                ['tab' => $tN, 'key' => 'estado',     'label' => 'Estado',           'icon' => 'bi-flag',           'type' => 'select',     'grupo' => 'Documento', 'col' => 3, 'options' => [
                    ['v' => 'borrador',   'l' => 'Borrador'],
                    ['v' => 'autorizado', 'l' => 'Autorizado'],
                    ['v' => 'anulado',    'l' => 'Anulado'],
                ]],
                ['tab' => $tN, 'key' => 'correo',     'label' => 'Correo',           'icon' => 'bi-envelope',       'type' => 'select',     'grupo' => 'Documento', 'col' => 3, 'options' => [
                    ['v' => 'enviado',   'l' => 'Enviado'],
                    ['v' => 'pendiente', 'l' => 'Pendiente'],
                ]],
                ['tab' => $tN, 'key' => 'serie',      'label' => 'Serie',            'icon' => 'bi-upc-scan',       'type' => 'select',     'grupo' => 'Documento', 'col' => 3, 'options' => $opcionesSerie],
                ['tab' => $tN, 'key' => 'numero',     'label' => 'Nº nota',          'icon' => 'bi-hash',           'type' => 'text',       'grupo' => 'Documento', 'col' => 3, 'placeholder' => '001-001-000000123'],
                ['tab' => $tN, 'key' => 'secuencial', 'label' => 'Secuencial',       'icon' => 'bi-123',            'type' => 'text',       'grupo' => 'Documento', 'col' => 2, 'placeholder' => 'Sin ceros'],
                ['tab' => $tN, 'key' => 'asiento',    'label' => 'Asiento contable', 'icon' => 'bi-journal-check',  'type' => 'select',     'grupo' => 'Documento', 'col' => 4, 'options' => [
                    ['v' => 'si', 'l' => 'Con asiento'],
                    ['v' => 'no', 'l' => 'Sin asiento'],
                ]],
                ['tab' => $tN, 'key' => 'fecha_autorizacion', 'label' => 'Fecha de autorización', 'icon' => 'bi-patch-check', 'type' => 'date_range', 'grupo' => 'Documento', 'col' => 6],
                ['tab' => $tN, 'key' => 'id_usuario', 'label' => 'Usuario que registró', 'icon' => 'bi-person-gear', 'type' => 'select',   'grupo' => 'Documento', 'col' => 6, 'options' => $opcionesUsuario],
                // ── Documento modificado ──
                ['tab' => $tN, 'key' => 'doc_modificado', 'label' => 'Nº documento modificado', 'icon' => 'bi-receipt', 'type' => 'text',   'grupo' => 'Documento modificado', 'col' => 4, 'placeholder' => '001-001-000000123'],
                ['tab' => $tN, 'key' => 'fecha_sustento', 'label' => 'Fecha del documento',     'icon' => 'bi-calendar', 'type' => 'date_range', 'grupo' => 'Documento modificado', 'col' => 5],
                ['tab' => $tN, 'key' => 'motivo',         'label' => 'Motivo',                  'icon' => 'bi-chat-left-text', 'type' => 'text', 'grupo' => 'Documento modificado', 'col' => 3],
                // ── Valores ──
                ['tab' => $tN, 'key' => 'monto',     'label' => 'Total',     'icon' => 'bi-currency-dollar', 'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                ['tab' => $tN, 'key' => 'subtotal',  'label' => 'Subtotal',  'icon' => 'bi-receipt',         'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                ['tab' => $tN, 'key' => 'descuento', 'label' => 'Descuento', 'icon' => 'bi-tag',             'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                // ── Cliente ──
                ['tab' => $tN, 'key' => 'cliente',      'label' => 'Cliente',         'icon' => 'bi-person',         'type' => 'text', 'grupo' => 'Cliente', 'col' => 4],
                ['tab' => $tN, 'key' => 'ruc',          'label' => 'RUC / Cédula',    'icon' => 'bi-card-text',      'type' => 'text', 'grupo' => 'Cliente', 'col' => 4],
                ['tab' => $tN, 'key' => 'obs',          'label' => 'Observaciones',   'icon' => 'bi-chat-left-text', 'type' => 'text', 'grupo' => 'Cliente', 'col' => 4],
                ['tab' => $tN, 'key' => 'autorizacion', 'label' => 'Nº autorización', 'icon' => 'bi-shield-check',   'type' => 'text', 'grupo' => 'Cliente', 'col' => 6],
                ['tab' => $tN, 'key' => 'clave',        'label' => 'Clave de acceso', 'icon' => 'bi-key',            'type' => 'text', 'grupo' => 'Cliente', 'col' => 6],
            ];
            ?>
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
            <div id="fmBuscadorNC"></div>
            <input type="hidden" id="buscarNC" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosModal) return;
                    new FiltrosModal({
                        containerId: 'fmBuscadorNC',
                        hiddenInputId: 'buscarNC',
                        placeholder: 'Buscar en todas las columnas...',
                        titulo: 'Filtros de notas de crédito',
                        inputWidth: 420,
                        extraId: 'fmExtraNC',   // columnas + PDF + Excel, pegados al final del grupo
                        // Pestaña Detalles: búsqueda libre dentro de las notas (productos/servicios
                        // e información adicional). Cada coincidencia dice a qué nota pertenece.
                        busquedaDetalle: {
                            tab: 'Detalles',
                            url: `<?= BASE_URL ?>/<?= $rutaModulo ?>/buscarDetallesAjax`,
                            label: 'Buscar libremente dentro de las notas de crédito',
                            placeholder: 'Producto, código, descripción, cantidad, valor, información adicional...',
                            columns: [
                                { key: 'origen',         label: 'Tipo' },
                                { key: 'tipo',           label: 'Código / Campo' },
                                { key: 'descripcion',    label: 'Descripción' },
                                { key: 'cantidad',       label: 'Cant.', align: 'end' },
                                { key: 'monto',          label: 'Valor', align: 'end' },
                                { key: 'numero',         label: 'Nota', class: 'font-monospace fw-semibold' },
                                { key: 'fecha',          label: 'Fecha' },
                                { key: 'doc_modificado', label: 'Doc. modificado', class: 'font-monospace' },
                                { key: 'cliente',        label: 'Cliente' },
                                { key: 'estado',         label: 'Estado' },
                            ],
                            onSelect: (row, fm) => fm.aplicarFiltro({ key: 'numero', value: row.numero }),
                            onOpen: (row, fm) => {
                                fm.hide();
                                setTimeout(() => window.NC_abrirModalNC({ dataset: { row: JSON.stringify({ id: row.id_nota, estado: row.estado_valor }) } }), 350);
                            },
                        },
                        fields: <?= json_encode($filtrosNotasCredito, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                        loadingTarget: '#nc-table-body',   // se atenúa mientras se busca
                        onApply: () => window.NC_fetchSearch && window.NC_fetchSearch(1),
                    }).init();
                });
            </script>

            <?php // FiltrosModal (extraId) mueve estos botones dentro del grupo del buscador; si el JS no corre, quedan aquí. ?>
            <div id="fmExtraNC" class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'numero'              => 'Nº Nota',
                    'fecha_emision'       => 'Fecha',
                    'cliente_nombre'      => 'Cliente',
                    'cliente_ruc'         => 'Identificación',
                    'num_doc_modificado'  => 'Doc. Modificado',
                    'total_sin_impuestos' => 'Subtotal',
                    'total_descuento'     => 'Descuento',
                    'importe_total'       => 'Total',
                    'motivo'              => 'Motivo',
                    'usuario_nombre'      => 'Usuario',
                    'estado_correo'       => 'Correo',
                    'estado'              => 'Estado',
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>

                <a id="btnExportPdf" href="<?= $urlBase ?>/export-pdf?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                    class="btn btn-outline-danger px-2" title="Descargar PDF">
                    <i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span>
                </a>
                <a id="btnExportExcel" href="<?= $urlBase ?>/export-excel?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                    class="btn btn-outline-success px-2" title="Descargar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i><span class="d-none d-md-inline"> Excel</span>
                </a>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <span id="nc-pagination-info" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="nc-pagination" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" <?= $page <= 1 ? 'disabled' : '' ?> onclick="window.NC_cambiarPaginaAjax(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" <?= $page >= $totalPages ? 'disabled' : '' ?> onclick="window.NC_cambiarPaginaAjax(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="nc-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" onclick="window.NC_ordenar('secuencial')" data-col="numero">
                            Nº Nota <i class="bi <?= $ordenCol === 'secuencial' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="sortable-header" role="button" onclick="window.NC_ordenar('fecha_emision')" data-col="fecha_emision">
                            Fecha <i class="bi <?= $ordenCol === 'fecha_emision' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="sortable-header" role="button" onclick="window.NC_ordenar('cliente_nombre')" data-col="cliente_nombre">
                            Cliente <i class="bi <?= $ordenCol === 'cliente_nombre' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="sortable-header" role="button" onclick="window.NC_ordenar('cliente_ruc')" data-col="cliente_ruc">
                            Identificación <i class="bi <?= $ordenCol === 'cliente_ruc' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th data-col="num_doc_modificado">Doc. Modificado</th>
                        <th class="sortable-header text-end" role="button" onclick="window.NC_ordenar('total_sin_impuestos')" data-col="total_sin_impuestos">
                            Subtotal <i class="bi <?= $ordenCol === 'total_sin_impuestos' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="sortable-header text-end" role="button" onclick="window.NC_ordenar('total_descuento')" data-col="total_descuento">
                            Descuento <i class="bi <?= $ordenCol === 'total_descuento' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="sortable-header text-end" role="button" onclick="window.NC_ordenar('importe_total')" data-col="importe_total">
                            Total <i class="bi <?= $ordenCol === 'importe_total' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th data-col="motivo">Motivo</th>
                        <th class="sortable-header" role="button" onclick="window.NC_ordenar('usuario_nombre')" data-col="usuario_nombre">
                            Usuario <i class="bi <?= $ordenCol === 'usuario_nombre' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="text-center sortable-header" role="button" onclick="window.NC_ordenar('estado_correo')" data-col="estado_correo">
                            Correo <i class="bi <?= $ordenCol === 'estado_correo' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                        <th class="text-center pe-3 sortable-header" role="button" onclick="window.NC_ordenar('estado')" data-col="estado">
                            Estado <i class="bi <?= $ordenCol === 'estado' ? ($ordenDir === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary') : 'bi-arrow-down-up small text-muted' ?> ms-1"></i>
                        </th>
                    </tr>
                </thead>
                <tbody id="nc-table-body">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="12" class="text-center py-5 text-muted"><i class="bi bi-file-earmark-minus fs-3 d-block mb-2"></i>No se encontraron notas de crédito.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $estado = $r['estado'] ?? 'borrador';
                            $estadoClass = match ($estado) {
                                'autorizado' => 'bg-success bg-opacity-10 text-success border-success',
                                'anulado'    => 'bg-danger bg-opacity-10 text-danger border-danger',
                                'borrador'   => 'bg-secondary bg-opacity-10 text-secondary border-secondary',
                                default      => 'bg-primary bg-opacity-10 text-primary border-primary',
                            };
                            $estadoCorreo = $r['estado_correo'] ?? 'pendiente';
                            $correoClass  = $estadoCorreo === 'enviado'
                                ? 'bg-success bg-opacity-10 text-success border-success'
                                : 'bg-warning bg-opacity-10 text-warning border-warning';
                            ?>
                            <tr class="nc-row" role="button" tabindex="0" data-row='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>' onclick="window.NC_abrirModalNC(this)">
                                <td class="ps-3" data-col="numero"><code class="text-secondary"><?= htmlspecialchars(($r['establecimiento'] ?? '') . '-' . ($r['punto_emision'] ?? '') . '-' . ($r['secuencial'] ?? '')) ?></code></td>
                                <td data-col="fecha_emision"><?= !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '-' ?></td>
                                <td class="fw-medium text-truncate" data-col="cliente_nombre" style="max-width:200px"><?= htmlspecialchars($r['cliente_nombre'] ?? '-') ?></td>
                                <td data-col="cliente_ruc"><small class="text-muted"><?= htmlspecialchars($r['cliente_ruc'] ?? '-') ?></small></td>
                                <td data-col="num_doc_modificado"><small class="text-muted"><?= htmlspecialchars($r['num_doc_modificado'] ?? '-') ?></small></td>
                                <td class="text-end" data-col="total_sin_impuestos">$<?= number_format((float)($r['total_sin_impuestos'] ?? 0), 2) ?></td>
                                <td class="text-end text-danger" data-col="total_descuento">$<?= number_format((float)($r['total_descuento'] ?? 0), 2) ?></td>
                                <td class="text-end fw-bold" data-col="importe_total">$<?= number_format((float)($r['importe_total'] ?? 0), 2) ?></td>
                                <td data-col="motivo" class="text-truncate" style="max-width:180px"><?= htmlspecialchars($r['motivo'] ?? '') ?></td>
                                <td data-col="usuario_nombre"><?= htmlspecialchars($r['usuario_nombre'] ?? '-') ?></td>
                                <td class="text-center" data-col="estado_correo">
                                    <span class="badge <?= $correoClass ?> border border-opacity-25"><?= ucfirst($estadoCorreo) ?></span>
                                </td>
                                <td class="text-center pe-3" data-col="estado">
                                    <span class="badge <?= $estadoClass ?> border border-opacity-25"><?= ucfirst($estado) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'modal_nc.php'; ?>

<script>
    window.nc_dec_p = <?= (int)($empresa['decimales_precio'] ?? 2) ?>;
    window.nc_dec_c = <?= (int)($empresa['decimales_cantidad'] ?? 2) ?>;
    window.NC_STORAGE_KEY = 'nc_borrador_' + <?= (int)($_SESSION['id_empresa'] ?? 0) ?> + '_' + <?= (int)($_SESSION['id_usuario'] ?? 0) ?>;
    // Nivel 3 (superadmin) puede eliminar una NC fuera de borrador — ver
    // NotaCreditoService::eliminar(), que hace la verificación real.
    window.NC_ES_SUPERADMIN = <?= ((int) ($_SESSION['nivel'] ?? 1) === 3) ? 'true' : 'false' ?>;
    // Ordenamiento aplicado por el servidor (preferencia del usuario).
    window.NC_ORDEN_COL = <?= json_encode($ordenCol ?? 'fecha_emision') ?>;
    window.NC_ORDEN_DIR = <?= json_encode($ordenDir ?? 'DESC') ?>;
    window.currentSort  = window.NC_ORDEN_COL;
    window.currentDir   = window.NC_ORDEN_DIR;
</script>
<script src="<?= rtrim($base, '/') ?>/js/modulos/asiento_contable_tab.js?v=<?= asset_ver('/js/modulos/asiento_contable_tab.js') ?>" defer></script>
<script src="<?= rtrim($base, '/') ?>/js/modulos/notas_credito.js?v=<?= asset_ver('/js/modulos/notas_credito.js') ?>" defer></script>
