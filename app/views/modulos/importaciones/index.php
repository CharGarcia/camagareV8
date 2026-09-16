<?php

/** @var array $perm */
/** @var string $rutaModulo */
/** @var array $rows */
/** @var int $total, $page, $totalPages, $perPage, $from, $to */
/** @var string $buscar, $ordenCol, $ordenDir */
/** @var array $bodegas */
/** @var array $vistaConfig */
/** @var array $puntos */
/** @var array $establecimientos */
/** @var array|null $sucursal_principal */

$base       = BASE_URL;
$urlBase    = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');
$rows       = $rows       ?? [];
$total      = $total      ?? 0;
$page       = $page       ?? 1;
$totalPages = $totalPages ?? 1;
$perPage    = $perPage    ?? 20;
$ordenCol   = $ordenCol   ?? 'fecha_nacionalizacion';
$ordenDir   = $ordenDir   ?? 'DESC';
$buscar     = $buscar     ?? '';
$from       = $from       ?? 0;
$to         = $to         ?? 0;
$bodegas    = $bodegas    ?? [];
$puntos     = $puntos     ?? [];

$estadoLabelMap = [
    'borrador'             => 'Borrador',
    'en_transito'          => 'En tránsito',
    'pendiente_aprobacion' => 'Pendiente aprobación',
    'nacionalizada'        => 'Nacionalizada',
    'cerrada'              => 'Cerrada',
    'anulada'              => 'Anulada',
];
?>
<style>
    .importaciones-scroll {
        max-height: calc(100dvh - 240px);
        overflow-y: auto;
    }

    .importaciones-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }

    .importacion-row {
        cursor: pointer;
    }

    .importacion-row:hover {
        background: rgba(0, 0, 0, .04);
    }

    .tab-pestaña .nav-link {
        font-size: .85rem;
        padding: .4rem .75rem;
        font-weight: 600;
    }

    #tbodyProductosFob tr td,
    #tbodyFacturasExterior tr td,
    #tbodyGastosImp tr td {
        padding: .3rem .4rem;
        vertical-align: middle;
    }

    #imp-asiento-body tr td {
        padding: .3rem .4rem;
        vertical-align: middle;
    }

    /* ── Apilado de modales sobre el modal de importación ── */
    .modal:not(#modalImportacion) {
        z-index: 6060 !important;
    }

    .modal-backdrop ~ .modal-backdrop {
        z-index: 6055 !important;
    }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-globe-americas"></i> <?= htmlspecialchars($titulo ?? 'Importaciones') ?></h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="abrirModalImportacionCrear()">
            <i class="bi bi-plus-lg"></i> Nueva
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php
            $vf = $valoresFiltro ?? [];
            $opcionesSerie    = array_map(fn($s) => ['v' => $s['establecimiento'] . '-' . $s['punto_emision'], 'l' => $s['establecimiento'] . '-' . $s['punto_emision']], $seriesFiltro ?? []);
            $opcionesIncoterm = array_map(fn($x) => ['v' => (string) $x, 'l' => (string) $x], $vf['incoterms'] ?? []);
            $opcionesBodega   = array_map(fn($b) => ['v' => (string) $b['id'], 'l' => $b['nombre']], $vf['bodegas'] ?? []);
            $opcionesAgente   = array_map(fn($a) => ['v' => (string) $a['id'], 'l' => $a['nombre']], $vf['agentes'] ?? []);
            $opcionesUsuario  = array_map(fn($u) => ['v' => (string) $u['id'], 'l' => $u['nombre']], $vf['usuarios'] ?? []);
            $tI = 'Importación';
            // Filas de 12 columnas:
            //   Documento: [Fecha nacionalización 6][Fecha embarque 6]
            //              [Fecha llegada 6][Estado 3][Serie 3]
            //              [Nº importación 4][Secuencial 2][Referencia DAI 3][Incoterm 3]
            //              [Bodega destino 4][Criterio de prorrateo 4][Asiento 4]
            //              [Agente afianzado 6][Usuario 6]
            //   Valores:   [Costo nacionalizado 4][Subtotal FOB 4][Gastos capitalizables 4]
            //              [IVA 4][ISD 4][Otros gastos 4]
            //   Proveedor: [Proveedor del exterior 6][Identificación 6]
            //              [Observaciones 12]
            $filtrosImportaciones = [
                ['tab' => $tI, 'key' => 'fecha',      'label' => 'Fecha de nacionalización', 'icon' => 'bi-calendar-event', 'type' => 'date_range', 'grupo' => 'Documento', 'col' => 6, 'atajos' => true],
                ['tab' => $tI, 'key' => 'embarque',   'label' => 'Fecha de embarque',   'icon' => 'bi-calendar-plus',  'type' => 'date_range', 'grupo' => 'Documento', 'col' => 6],
                ['tab' => $tI, 'key' => 'llegada',    'label' => 'Fecha de llegada',    'icon' => 'bi-calendar-check', 'type' => 'date_range', 'grupo' => 'Documento', 'col' => 6],
                ['tab' => $tI, 'key' => 'estado',     'label' => 'Estado',              'icon' => 'bi-flag',           'type' => 'select',     'grupo' => 'Documento', 'col' => 3, 'options' => [
                    ['v' => 'borrador',             'l' => 'Borrador'],
                    ['v' => 'en_transito',          'l' => 'En tránsito'],
                    ['v' => 'registrada',           'l' => 'Registrada'],
                    ['v' => 'pendiente_aprobacion', 'l' => 'Pendiente aprobación'],
                    ['v' => 'nacionalizada',        'l' => 'Nacionalizada'],
                    ['v' => 'cerrada',              'l' => 'Cerrada'],
                    ['v' => 'anulada',              'l' => 'Anulada'],
                ]],
                ['tab' => $tI, 'key' => 'serie',      'label' => 'Serie',               'icon' => 'bi-upc-scan',       'type' => 'select',     'grupo' => 'Documento', 'col' => 3, 'options' => $opcionesSerie],
                ['tab' => $tI, 'key' => 'numero',     'label' => 'Nº importación',      'icon' => 'bi-hash',           'type' => 'text',       'grupo' => 'Documento', 'col' => 4, 'placeholder' => '001-001-000000123'],
                ['tab' => $tI, 'key' => 'secuencial', 'label' => 'Secuencial',          'icon' => 'bi-123',            'type' => 'text',       'grupo' => 'Documento', 'col' => 2, 'placeholder' => 'Sin ceros'],
                ['tab' => $tI, 'key' => 'dai',        'label' => 'Referencia DAI',      'icon' => 'bi-file-earmark-text', 'type' => 'text',    'grupo' => 'Documento', 'col' => 3],
                ['tab' => $tI, 'key' => 'incoterm',   'label' => 'Incoterm',            'icon' => 'bi-truck',          'type' => 'select',     'grupo' => 'Documento', 'col' => 3, 'options' => $opcionesIncoterm],
                ['tab' => $tI, 'key' => 'id_bodega',  'label' => 'Bodega destino',      'icon' => 'bi-house-door',     'type' => 'select',     'grupo' => 'Documento', 'col' => 4, 'options' => $opcionesBodega],
                ['tab' => $tI, 'key' => 'criterio',   'label' => 'Criterio de prorrateo', 'icon' => 'bi-diagram-3',    'type' => 'select',     'grupo' => 'Documento', 'col' => 4, 'options' => [
                    ['v' => 'fob',      'l' => 'Por valor FOB'],
                    ['v' => 'peso',     'l' => 'Por peso (Kg)'],
                    ['v' => 'volumen',  'l' => 'Por volumen (m3)'],
                    ['v' => 'cantidad', 'l' => 'Por cantidad'],
                ]],
                ['tab' => $tI, 'key' => 'asiento',    'label' => 'Asiento contable',    'icon' => 'bi-journal-check',  'type' => 'select',     'grupo' => 'Documento', 'col' => 4, 'options' => [
                    ['v' => 'si', 'l' => 'Con asiento'],
                    ['v' => 'no', 'l' => 'Sin asiento'],
                ]],
                ['tab' => $tI, 'key' => 'id_agente',  'label' => 'Agente afianzado',    'icon' => 'bi-person-badge',   'type' => 'select',     'grupo' => 'Documento', 'col' => 6, 'options' => $opcionesAgente],
                ['tab' => $tI, 'key' => 'id_usuario', 'label' => 'Usuario que registró', 'icon' => 'bi-person-gear',   'type' => 'select',     'grupo' => 'Documento', 'col' => 6, 'options' => $opcionesUsuario],
                // Valores
                ['tab' => $tI, 'key' => 'total',      'label' => 'Costo nacionalizado', 'icon' => 'bi-currency-dollar','type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                ['tab' => $tI, 'key' => 'fob',        'label' => 'Subtotal FOB',        'icon' => 'bi-cash-stack',     'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                ['tab' => $tI, 'key' => 'gastos',     'label' => 'Gastos capitalizables', 'icon' => 'bi-plus-slash-minus', 'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                ['tab' => $tI, 'key' => 'iva',        'label' => 'IVA de importación',  'icon' => 'bi-percent',        'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                ['tab' => $tI, 'key' => 'isd',        'label' => 'ISD',                 'icon' => 'bi-bank',           'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                ['tab' => $tI, 'key' => 'otros',      'label' => 'Otros gastos',        'icon' => 'bi-receipt',        'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                // Proveedor
                ['tab' => $tI, 'key' => 'proveedor',  'label' => 'Proveedor del exterior', 'icon' => 'bi-building',    'type' => 'text',   'grupo' => 'Proveedor', 'col' => 6],
                ['tab' => $tI, 'key' => 'ruc',        'label' => 'Identificación',      'icon' => 'bi-card-text',      'type' => 'text',   'grupo' => 'Proveedor', 'col' => 6],
                ['tab' => $tI, 'key' => 'obs',        'label' => 'Observaciones',       'icon' => 'bi-chat-text',      'type' => 'text',   'grupo' => 'Proveedor', 'col' => 12],
            ];
            ?>
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
            <div id="fmBuscadorImportaciones"></div>
            <input type="hidden" id="inputBuscarImportaciones" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosModal) return;
                    window.IMP_filtros = new FiltrosModal({
                        containerId: 'fmBuscadorImportaciones',
                        hiddenInputId: 'inputBuscarImportaciones',
                        placeholder: 'Buscar en todas las columnas...',
                        titulo: 'Filtros de importaciones',
                        inputWidth: 420,
                        extraId: 'fmExtraImportaciones',   // columnas + PDF + Excel, pegados al final del grupo
                        // Pestaña Detalles: búsqueda libre dentro de las importaciones.
                        busquedaDetalle: {
                            tab: 'Detalles',
                            url: `<?= $urlBase ?>/buscarDetallesAjax`,
                            label: 'Buscar libremente dentro de las importaciones',
                            placeholder: 'Producto, código, lote, factura del exterior, gasto, compra o liquidación vinculada...',
                            columns: [
                                { key: 'origen',             label: 'Tipo' },
                                { key: 'tipo',               label: 'Código / Nº / Gasto' },
                                { key: 'descripcion',        label: 'Descripción' },
                                { key: 'cantidad',           label: 'Cant.', align: 'end' },
                                { key: 'monto',              label: 'Valor', align: 'end' },
                                { key: 'numero_importacion', label: 'Importación', class: 'font-monospace fw-semibold' },
                                { key: 'fecha',              label: 'F. nacionalización' },
                                { key: 'proveedor_nombre',   label: 'Proveedor' },
                                { key: 'estado',             label: 'Estado' },
                            ],
                            onSelect: (row, fm) => fm.aplicarFiltro({ key: 'numero', value: row.numero_importacion }),
                            onOpen: (row, fm) => {
                                fm.hide();
                                // abrirModalImportacion lee la importación del data-row de la fila.
                                setTimeout(() => window.abrirModalImportacion({ dataset: { row: JSON.stringify(row) } }), 350);
                            },
                        },
                        fields: <?= json_encode($filtrosImportaciones, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                        loadingTarget: '#tbodyImportaciones',   // se atenúa mientras se busca
                        onApply: () => window.CMG_fetchSearchImp && window.CMG_fetchSearchImp(1),
                    });
                    window.IMP_filtros.init();
                });
            </script>
            <?php // FiltrosModal (extraId) mueve estos botones dentro del grupo del buscador; si el JS no corre, quedan aquí. ?>
            <div id="fmExtraImportaciones" class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'numero_importacion'        => 'N° Importación',
                    'referencia_dai'            => 'Referencia DAI',
                    'proveedor_nombre'          => 'Proveedor exterior',
                    'incoterm'                  => 'Incoterm',
                    'bodega_nombre'             => 'Bodega destino',
                    'fecha_nacionalizacion'     => 'Fecha nacionalización',
                    'subtotal_fob'              => 'Subtotal FOB',
                    'costo_total_nacionalizado' => 'Costo total',
                    'estado'                    => 'Estado',
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>
                <a id="btnExportPdf" href="<?= $urlBase ?>/export-pdf?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                    class="btn btn-outline-danger" title="PDF"><i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span></a>
                <a id="btnExportExcel" href="<?= $urlBase ?>/export-excel?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                    class="btn btn-outline-success" title="Excel"><i class="bi bi-file-earmark-spreadsheet"></i><span class="d-none d-md-inline"> Excel</span></a>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="paginationContainer" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" <?= $page <= 1 ? 'disabled' : '' ?> onclick="CMG_cambiarPaginaImp(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" <?= $page >= $totalPages ? 'disabled' : '' ?> onclick="CMG_cambiarPaginaImp(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="importaciones-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="numero_importacion" data-col="numero_importacion">N° Importación <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="referencia_dai" data-col="referencia_dai">Referencia DAI <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="proveedor_nombre" data-col="proveedor_nombre">Proveedor exterior <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th data-col="incoterm">Incoterm</th>
                        <th data-col="bodega_nombre">Bodega destino</th>
                        <th class="sortable-header" role="button" data-sort="fecha_nacionalizacion" data-col="fecha_nacionalizacion">Fecha nacionalización <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header" role="button" data-sort="subtotal_fob" data-col="subtotal_fob">Subtotal FOB <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header fw-bold" role="button" data-sort="costo_total_nacionalizado" data-col="costo_total_nacionalizado">Costo total <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center pe-3 sortable-header" role="button" data-sort="estado" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyImportaciones">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted"><i class="bi bi-globe-americas fs-3 d-block mb-2"></i>No se encontraron importaciones.</td>
                        </tr>
                        <?php else: foreach ($rows as $r):
                            $estado = $r['estado'] ?? 'borrador';
                            $estadoClass = match ($estado) {
                                'nacionalizada'        => 'bg-success bg-opacity-10 text-success border-success',
                                'cerrada'              => 'bg-primary bg-opacity-10 text-primary border-primary',
                                'anulada'              => 'bg-danger bg-opacity-10 text-danger border-danger',
                                'en_transito'          => 'bg-warning bg-opacity-10 text-warning border-warning',
                                'pendiente_aprobacion' => 'bg-info bg-opacity-10 text-info border-info',
                                default                => 'bg-secondary bg-opacity-10 text-secondary border-secondary',
                            };
                            $estadoLabel = $estadoLabelMap[$estado] ?? ucfirst($estado);
                        ?>
                            <tr class="importacion-row" role="button" tabindex="0"
                                data-row='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>'
                                onclick="abrirModalImportacion(this)">
                                <td class="ps-3" data-col="numero_importacion"><code class="text-secondary"><?= htmlspecialchars($r['numero_importacion'] ?? '—') ?></code></td>
                                <td data-col="referencia_dai"><?= htmlspecialchars($r['referencia_dai'] ?? '—') ?></td>
                                <td class="fw-medium text-truncate" style="max-width:220px" data-col="proveedor_nombre"><?= htmlspecialchars($r['proveedor_nombre'] ?? '—') ?></td>
                                <td data-col="incoterm"><small class="text-muted"><?= htmlspecialchars($r['incoterm'] ?? '—') ?></small></td>
                                <td data-col="bodega_nombre"><small class="text-muted"><?= htmlspecialchars($r['bodega_nombre'] ?? '—') ?></small></td>
                                <td data-col="fecha_nacionalizacion"><?= !empty($r['fecha_nacionalizacion']) ? date('d-m-Y', strtotime($r['fecha_nacionalizacion'])) : '—' ?></td>
                                <td class="text-end" data-col="subtotal_fob">$<?= number_format((float) ($r['subtotal_fob'] ?? 0), 2) ?></td>
                                <td class="text-end fw-bold" data-col="costo_total_nacionalizado">$<?= number_format((float) ($r['costo_total_nacionalizado'] ?? 0), 2) ?></td>
                                <td class="text-center pe-3" data-col="estado"><span class="badge <?= $estadoClass ?> border border-opacity-25"><?= $estadoLabel ?></span></td>
                            </tr>
                    <?php endforeach;
                    endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Datos PHP para JS -->
<script>
    window.BASE_URL = '<?= $base ?>';
    window.CMG_urlBaseImp = '<?= $urlBase ?>';
    window.CMG_currentSortImp = '<?= $ordenCol ?>';
    window.CMG_currentDirImp = '<?= $ordenDir ?>';
    window.CMG_currentPageImp = <?= $page ?>;
    window.CMG_permImp = {
        crear: <?= $perm['crear'] ? 'true' : 'false' ?>,
        actualizar: <?= $perm['actualizar'] ? 'true' : 'false' ?>,
        eliminar: <?= $perm['eliminar'] ? 'true' : 'false' ?>
    };
    window.CMG_bodegasImp = <?= json_encode(array_values($bodegas ?? [])) ?>;
</script>

<?php include __DIR__ . '/modal_importacion.php'; ?>
<?php include MVC_APP . '/views/modulos/proveedores/modal_proveedor.php'; ?>
<?php include MVC_APP . '/views/modulos/productos/modal.php'; ?>
<script src="<?= $base ?>/js/modulos/proveedores_modal.js?v=<?= asset_ver('/js/modulos/proveedores_modal.js') ?>"></script>
<script src="<?= $base ?>/js/modulos/productos_modal.js?v=<?= asset_ver('/js/modulos/productos_modal.js') ?>"></script>
<script src="<?= $base ?>/js/modulos/asiento_contable_tab.js?v=<?= asset_ver('/js/modulos/asiento_contable_tab.js') ?>"></script>
<script src="<?= $base ?>/js/modulos/importaciones.js?v=<?= asset_ver('/js/modulos/importaciones.js') ?>"></script>

<script>
    (function() {
        'use strict';
        const input = document.getElementById('inputBuscarImportaciones');

        window.CMG_cambiarPaginaImp = (n) => CMG_fetchSearchImp(n);

        window.CMG_fetchSearchImp = async (page = 1) => {
            const term = input ? input.value.trim() : '';
            const uri = `${window.CMG_urlBaseImp}/searchAjax?b=${encodeURIComponent(term)}&page=${page}&sort=${window.CMG_currentSortImp}&dir=${window.CMG_currentDirImp}`;
            // Mismo indicador que el buscador (FiltrosModal): la tabla se atenúa mientras
            // carga, también al paginar y ordenar, que llaman a esta función directo.
            const tbody = document.getElementById('tbodyImportaciones');
            if (tbody) tbody.classList.add('fm-cargando-target');
            try {
                const resp = await fetch(uri);
                const data = await resp.json();
                if (data.ok) {
                    window.CMG_currentPageImp = page;
                    document.getElementById('tbodyImportaciones').innerHTML = data.rows;
                    document.getElementById('paginationContainer').innerHTML = `
                    <button type="button" class="btn btn-outline-secondary" ${page<=1?'disabled':''} onclick="CMG_cambiarPaginaImp(${page-1})"><i class="bi bi-chevron-left"></i></button>
                    <button type="button" class="btn btn-outline-secondary" ${page>=data.totalPages?'disabled':''} onclick="CMG_cambiarPaginaImp(${page+1})"><i class="bi bi-chevron-right"></i></button>`;
                    document.getElementById('paginationInfo').textContent = data.info;
                    document.getElementById('btnExportPdf').href = data.pdf_url;
                    document.getElementById('btnExportExcel').href = data.excel_url;
                    actualizarIconosOrdenImp();
                }
            } catch (e) {
                console.error('Error búsqueda importaciones:', e);
            } finally {
                if (tbody) tbody.classList.remove('fm-cargando-target');
            }
        };

        function actualizarIconosOrdenImp() {
            document.querySelectorAll('.sortable-header').forEach(th => {
                const icon = th.querySelector('i');
                const field = th.dataset.sort;
                if (!icon) return;
                if (field === window.CMG_currentSortImp) {
                    icon.className = window.CMG_currentDirImp.toLowerCase() === 'asc' ?
                        'bi bi-sort-alpha-down text-primary ms-1' :
                        'bi bi-sort-alpha-up text-primary ms-1';
                } else {
                    icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
                }
            });
        }

        document.querySelectorAll('.sortable-header').forEach(h => {
            h.addEventListener('click', () => {
                const f = h.dataset.sort;
                if (!f) return;
                if (window.CMG_currentSortImp === f) {
                    window.CMG_currentDirImp = window.CMG_currentDirImp.toLowerCase() === 'asc' ? 'DESC' : 'ASC';
                } else {
                    window.CMG_currentSortImp = f;
                    window.CMG_currentDirImp = 'ASC';
                }
                if (typeof window.guardarOrdenacionVista === 'function') {
                    window.guardarOrdenacionVista('importaciones', window.CMG_currentSortImp, window.CMG_currentDirImp);
                }
                CMG_fetchSearchImp(1);
            });
        });

        let timerId;
        if (input) {
            input.addEventListener('input', () => {
                clearTimeout(timerId);
                timerId = setTimeout(() => CMG_fetchSearchImp(1), 380);
            });
        }
        actualizarIconosOrdenImp();
    })();
</script>
