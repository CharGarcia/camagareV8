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

$base = BASE_URL;
$urlBasePedidos = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');

$rows       = $rows ?? [];
$total      = $total ?? 0;
$page       = $page ?? 1;
$totalPages = $totalPages ?? 1;
$perPage    = $perPage ?? 20;
// Orden por defecto: lo más reciente primero. fecha_pedido es timestamp, pero el
// formulario solo manda la fecha (input type="date"), así que los pedidos del mismo
// día quedan empatados y los desempata el p.id DESC que agrega el repository.
$ordenCol   = $ordenCol ?? 'fecha_pedido';
$ordenDir   = $ordenDir ?? 'DESC';
$buscar     = $buscar ?? '';
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>
<style>
    .ped-header {
        flex-shrink: 0;
    }

    .ped-scroll {
        max-height: calc(100dvh - 240px);
        overflow: auto;
    }

    .ped-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }

    .pedido-row {
        cursor: pointer;
    }

    .pedido-row:hover {
        background-color: rgba(0, 0, 0, .04);
    }

    /* Estilos Premium para la tabla de detalles del modal */
    .table-detalle th {
        font-size: 0.7rem !important;
        padding: 4px 8px !important;
        text-transform: uppercase;
        background-color: #f8f9fa;
    }

    .table-detalle td {
        padding: 0 !important;
        vertical-align: middle;
    }

    .input-detalle {
        border: none;
        background: transparent;
        height: 30px !important;
        font-size: 0.82rem !important;
        padding: 2px 8px !important;
        border-radius: 0;
    }

    .input-detalle:focus {
        background: #fff;
        box-shadow: inset 0 0 0 1px #0d6efd !important;
        outline: none;
        border-radius: 4px;
    }

    .row-detalle:hover {
        background-color: rgba(13, 110, 253, 0.03);
    }

    /* Encabezados del detalle fijos al scrollear la tabla dentro del modal. */
    #modalPedido .table-detalle thead th {
        position: sticky;
        top: 0;
        z-index: 2;
    }

    /* Detalle del modal en móvil: la columna Código se comprimía hasta no dejar
       ver el código completo (no se sabía qué producto ya estaba cargado). Se le
       fija un ancho mínimo y, si la fila ya no cabe, la tabla scrollea en
       horizontal. El !important gana sobre el overflow en línea del contenedor,
       sea cual sea el valor con el que quede. */
    @media (max-width: 767.98px) {
        #modalPedido .table-responsive {
            overflow-x: auto !important;
            overflow-y: auto !important;
        }

        #modalPedido .table-detalle th:first-child,
        #modalPedido .table-detalle td:first-child {
            width: 150px;
            min-width: 150px;
        }

        #modalPedido .input-codigo {
            min-width: 140px;
        }

        /* La descripción cede el espacio: ya no arrastra a la fila entera. */
        #modalPedido .table-detalle th:nth-child(2) {
            width: auto;
            min-width: 170px;
        }
    }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>

<div class="ped-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-cart-check text-primary me-2"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="nuevoPedido()">
            <i class="bi bi-plus-lg"></i> Nuevo
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <!-- Buscador y Exportación -->
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php
            // Buscador estándar: texto libre sobre las columnas del listado (sin sugerencias),
            // botón embudo que abre el modal de filtros y chips dentro de la caja.
            // Las claves (key) deben existir en los mapas de PedidoRepository::getListado().
            $opcionesSerie       = array_map(fn($s) => ['v' => $s['establecimiento'] . '-' . $s['punto_emision'], 'l' => $s['establecimiento'] . '-' . $s['punto_emision']], $seriesFiltro ?? []);
            $opcionesResponsable = array_map(fn($x) => ['v' => (string) $x['id'], 'l' => $x['nombre']], $responsablesFiltro ?? []);
            $opcionesUsuario     = array_map(fn($x) => ['v' => (string) $x['id'], 'l' => $x['nombre']], $usuariosFiltro ?? []);
            $tP = 'Pedido';
            // Orden pensado en filas de 12 columnas:
            //   Documento: [Fecha de emisión 6][Estado 3][Serie 3]
            //              [Nº pedido 4][Secuencial 2][Fecha de entrega 6]
            //              [Usuario que registró 4][Documentos generados 4][Total 4]
            //   Cliente:   [Cliente 4][RUC / Cédula 4][Responsable de entrega 4]
            //              [Observaciones 6][Observaciones internas 6]
            $filtrosPedidos = [
                // ── Documento ──
                ['tab' => $tP, 'key' => 'fecha_pedido', 'label' => 'Fecha de emisión', 'icon' => 'bi-calendar-event', 'type' => 'date_range', 'grupo' => 'Documento', 'col' => 6, 'atajos' => true],
                // Estados reales: Pendiente, Procesado (consumido por completo) y Anulado.
                ['tab' => $tP, 'key' => 'estado', 'label' => 'Estado', 'icon' => 'bi-flag', 'type' => 'select', 'grupo' => 'Documento', 'col' => 3, 'options' => [
                    ['v' => 'Pendiente', 'l' => 'Pendiente'],
                    ['v' => 'Procesado', 'l' => 'Procesado'],
                    ['v' => 'Anulado',   'l' => 'Anulado'],
                ]],
                ['tab' => $tP, 'key' => 'serie',         'label' => 'Serie',            'icon' => 'bi-upc-scan',       'type' => 'select',     'grupo' => 'Documento', 'col' => 3, 'options' => $opcionesSerie],
                ['tab' => $tP, 'key' => 'numero',        'label' => 'Nº pedido',        'icon' => 'bi-hash',           'type' => 'text',       'grupo' => 'Documento', 'col' => 4, 'placeholder' => '001-001-000000123'],
                ['tab' => $tP, 'key' => 'secuencial',    'label' => 'Secuencial',       'icon' => 'bi-123',            'type' => 'text',       'grupo' => 'Documento', 'col' => 2, 'placeholder' => 'Sin ceros'],
                ['tab' => $tP, 'key' => 'fecha_entrega', 'label' => 'Fecha de entrega', 'icon' => 'bi-calendar-check', 'type' => 'date_range', 'grupo' => 'Documento', 'col' => 6],
                ['tab' => $tP, 'key' => 'id_usuario',    'label' => 'Usuario que registró', 'icon' => 'bi-person-gear', 'type' => 'select',    'grupo' => 'Documento', 'col' => 4, 'options' => $opcionesUsuario],
                ['tab' => $tP, 'key' => 'documentos',    'label' => 'Documentos generados', 'icon' => 'bi-files',       'type' => 'select',    'grupo' => 'Documento', 'col' => 4, 'options' => [
                    ['v' => 'si', 'l' => 'Con consignación o factura'],
                    ['v' => 'no', 'l' => 'Sin documentos'],
                ]],
                ['tab' => $tP, 'key' => 'total',         'label' => 'Total',            'icon' => 'bi-currency-dollar', 'type' => 'number_range', 'grupo' => 'Documento', 'col' => 4],
                // ── Cliente ──
                ['tab' => $tP, 'key' => 'cliente',        'label' => 'Cliente',                'icon' => 'bi-person',         'type' => 'text',   'grupo' => 'Cliente', 'col' => 4],
                ['tab' => $tP, 'key' => 'ruc',            'label' => 'RUC / Cédula',           'icon' => 'bi-card-text',      'type' => 'text',   'grupo' => 'Cliente', 'col' => 4],
                ['tab' => $tP, 'key' => 'id_responsable', 'label' => 'Responsable de entrega', 'icon' => 'bi-person-badge',   'type' => 'select', 'grupo' => 'Cliente', 'col' => 4, 'options' => $opcionesResponsable],
                ['tab' => $tP, 'key' => 'observaciones',  'label' => 'Observaciones',          'icon' => 'bi-chat-left-text', 'type' => 'text',   'grupo' => 'Cliente', 'col' => 6],
                ['tab' => $tP, 'key' => 'obs_internas',   'label' => 'Observaciones internas', 'icon' => 'bi-lock',           'type' => 'text',   'grupo' => 'Cliente', 'col' => 6],
            ];
            ?>
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
            <div id="fmBuscadorPED"></div>
            <input type="hidden" id="buscarPedido" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosModal) return;
                    new FiltrosModal({
                        containerId: 'fmBuscadorPED',
                        hiddenInputId: 'buscarPedido',
                        placeholder: 'Buscar en todas las columnas...',
                        titulo: 'Filtros de pedidos',
                        inputWidth: 420,
                        extraId: 'fmExtraPED',   // columnas + PDF + Excel, pegados al final del grupo
                        // Pestaña Detalles: búsqueda libre dentro de los pedidos (productos
                        // pedidos y consignaciones/facturas que los consumieron). Cada
                        // coincidencia dice a qué pedido pertenece.
                        busquedaDetalle: {
                            tab: 'Detalles',
                            url: `<?= BASE_URL ?>/<?= $rutaModulo ?>/buscarDetallesAjax`,
                            label: 'Buscar libremente dentro de los pedidos',
                            placeholder: 'Producto, código, cantidad, Nº de consignación o factura...',
                            columns: [
                                { key: 'origen',      label: 'Tipo' },
                                { key: 'tipo',        label: 'Código / Nº documento', class: 'font-monospace' },
                                { key: 'descripcion', label: 'Producto' },
                                { key: 'cantidad',    label: 'Cant.', align: 'end' },
                                { key: 'documento',   label: 'Fecha / estado doc.' },
                                { key: 'numero',      label: 'Pedido', class: 'font-monospace fw-semibold' },
                                { key: 'fecha',       label: 'Fecha' },
                                { key: 'cliente',     label: 'Cliente' },
                                { key: 'estado',      label: 'Estado' },
                            ],
                            onSelect: (row, fm) => fm.aplicarFiltro({ key: 'numero', value: row.numero }),
                            onOpen: (row, fm) => { fm.hide(); setTimeout(() => editarPedido(row.id_pedido), 350); },
                        },
                        fields: <?= json_encode($filtrosPedidos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                        loadingTarget: '#lista-pedidos',   // se atenúa mientras se busca
                        onApply: () => window.PED_fetchSearch && window.PED_fetchSearch(1),
                    }).init();
                });
            </script>

            <?php // FiltrosModal (extraId) mueve estos botones dentro del grupo del buscador; si el JS no corre, quedan aquí. ?>
            <div id="fmExtraPED" class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'numero_pedido'  => 'Nro. Pedido',
                    'fecha_pedido'   => 'Fecha Emisión',
                    'fecha_entrega'  => 'Fecha Entrega',
                    'rango_horario'  => 'Rango Horario',
                    'cliente_nombre' => 'Cliente',
                    'responsable_entrega' => 'Resp. Entrega',
                    'observaciones'  => 'Observaciones',
                    'observaciones_internas' => 'Obs. Internas',
                    'estado'         => 'Estado',
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>

                <a id="btnExportPdf" href="<?= $urlBasePedidos ?>/export-pdf?b=<?= urlencode($buscar) ?>&orden=<?= urlencode($ordenParam ?? '') ?>"
                    class="btn btn-outline-danger" title="Exportar PDF">
                    <i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span>
                </a>
                <a id="btnExportExcel" href="<?= $urlBasePedidos ?>/export-excel?b=<?= urlencode($buscar) ?>&orden=<?= urlencode($ordenParam ?? '') ?>"
                    class="btn btn-outline-success" title="Exportar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i><span class="d-none d-md-inline"> Excel</span>
                </a>
            </div>
        </div>

        <!-- Paginación -->
        <div class="d-flex align-items-center gap-3">
            <span id="info-paginacion" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="paginacion-pedidos" class="btn-group btn-group-sm">
                <!-- Mismo markup que devuelve searchAjax (PedidosController::searchAjax):
                     la primera carga la pinta PHP y las siguientes páginas la reemplazan
                     por AJAX, así que ambas versiones deben verse idénticas. -->
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary border-end-0 rounded-end-0" <?= $page <= 1 ? 'disabled' : '' ?> onclick="PED_cambiarPaginaAjax(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                    <button type="button" class="btn btn-outline-secondary rounded-start-0" <?= $page >= $totalPages ? 'disabled' : '' ?> onclick="PED_cambiarPaginaAjax(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card-body p-0">
        <div class="ped-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="numero_pedido" data-col="numero_pedido">Nro. Pedido <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="fecha_pedido" data-col="fecha_pedido">Fecha Emisión <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="fecha_entrega" data-col="fecha_entrega">Fecha Entrega <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="rango_horario" data-col="rango_horario">Rango Horario <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="cliente_nombre" data-col="cliente_nombre">Cliente <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="responsable_entrega" data-col="responsable_entrega">Resp. Entrega <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="observaciones" data-col="observaciones">Observaciones <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="observaciones_internas" data-col="observaciones_internas">Obs. Internas <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" role="button" data-sort="estado" data-col="estado"
                            title="Ordena por el flujo del pedido, no alfabéticamente: Pendiente → Procesado → Facturado → Anulado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="lista-pedidos">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted"><i class="bi bi-cart-x fs-3 d-block mb-2"></i>No se encontraron pedidos.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $estadoVal  = $r['estado'] ?? 'Pendiente';
                            $badgeColor = match(strtoupper($estadoVal)) {
                                'PENDIENTE' => 'warning',
                                'FACTURADO', 'PROCESADO' => 'success',
                                'ANULADO'   => 'danger',
                                default     => 'secondary',
                            };

                            $fechaEntrega = !empty($r['fecha_entrega']) ? date('d-m-Y', strtotime($r['fecha_entrega'])) : '';
                            $rangoHorario = '';
                            if (!empty($r['hora_inicial_entrega']) || !empty($r['hora_maxima_entrega'])) {
                                $ini = !empty($r['hora_inicial_entrega']) ? date('H:i', strtotime($r['hora_inicial_entrega'])) : '--:--';
                                $max = !empty($r['hora_maxima_entrega']) ? date('H:i', strtotime($r['hora_maxima_entrega'])) : '--:--';
                                $rangoHorario = "$ini - $max";
                            }
                            ?>
                            <tr class="pedido-row" role="button" tabindex="0" onclick="editarPedido(<?= $r['id'] ?>)">
                                <td class="ps-3" data-col="numero_pedido"><code class="text-secondary"><?= htmlspecialchars($r['numero_pedido'] ?? '') ?></code></td>
                                <td data-col="fecha_pedido"><?= !empty($r['fecha_pedido']) ? date('d-m-Y', strtotime($r['fecha_pedido'])) : '' ?></td>
                                <td data-col="fecha_entrega"><?= htmlspecialchars($fechaEntrega) ?></td>
                                <td data-col="rango_horario"><?= htmlspecialchars($rangoHorario) ?></td>
                                <td class="fw-medium text-truncate" style="max-width:250px" data-col="cliente_nombre"><?= htmlspecialchars($r['cliente_nombre'] ?? '') ?></td>
                                <td class="text-truncate" style="max-width:200px" data-col="responsable_entrega"><?= htmlspecialchars($r['responsable_entrega'] ?? '') ?></td>
                                <td class="text-truncate" style="max-width:200px" data-col="observaciones"><?= htmlspecialchars($r['observaciones'] ?? '') ?></td>
                                <td class="text-truncate" style="max-width:200px" data-col="observaciones_internas"><?= htmlspecialchars($r['observaciones_internas'] ?? '') ?></td>
                                <td class="text-center" data-col="estado">
                                    <span class="badge bg-<?= $badgeColor ?> bg-opacity-10 text-<?= $badgeColor ?> border border-<?= $badgeColor ?> border-opacity-25">
                                        <?= htmlspecialchars($estadoVal) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    window.BASE_URL    = '<?= $base ?>';
    window.CMG_urlBase = '<?= $base ?>/modulos/pedidos';
    window.APP_FAVORITOS = window.APP_FAVORITOS || {};
    window.currentSort = '<?= $ordenCol ?>';
    window.currentDir  = '<?= $ordenDir ?>';
    // Orden múltiple (Shift+clic): lista completa de criterios, en el formato que
    // lee OrdenListado en PHP. currentSort/currentDir quedan como el principal.
    window.currentSorts = <?= $ordenJson ?? '[]' ?>;
    window.currentPage = <?= $page ?>;
    // Tope de filas por descarga (PDF / Excel del listado) y total actual del
    // listado: si se pasa del tope, el JS pide acotar la búsqueda antes de bajar
    // el archivo. El mismo tope se revalida en el controlador.
    window.PED_EXPORT_MAX = <?= (int)($exportMaxFilas ?? 500) ?>;
    window.PED_TOTAL      = <?= (int)($total ?? 0) ?>;
    const TARIFAS_IVA    = <?= json_encode($tarifasIva ?? []) ?>;
    const UNIDADES       = <?= json_encode($unidades ?? []) ?>;
    const EMPRESA_CONFIG = <?= json_encode($empresa ?? []) ?>;
    const DEC_PRECIO     = <?= (int)($empresa['decimales_precio'] ?? 2) ?>;
</script>

<?php include __DIR__ . '/modal_pedido.php'; ?>

<?php
// Copia de seguridad del estado del módulo de pedidos. Se respalda TODO lo que el
// modal de clientes pisa más abajo (no solo ruta y permisos): si algo que se agregue
// al final de la vista leyera $ordenCol/$page, vería los valores del modal.
$rutaModuloOriginal = $rutaModulo ?? 'modulos/pedidos';
$permOriginal       = $perm;
$ordenColOriginal   = $ordenCol;
$ordenDirOriginal   = $ordenDir;
$pageOriginal       = $page;
$totalPagesOriginal = $totalPages;

// Variables requeridas por el modal de clientes para evitar errores de PHP durante la inclusión
$urlBaseClientes = BASE_URL . '/modulos/clientes';
$permForModal = [
    'ver'        => $perm['ver'] ?? true,
    'crear'      => $perm['crear'] ?? true,
    'actualizar' => $perm['actualizar'] ?? true,
    'eliminar'   => $perm['eliminar'] ?? true,
    'todo'       => $perm['todo'] ?? true
];
$perm = $permForModal;
$canCreateVend = true;
$ordenCol   = 'nombre';
$ordenDir   = 'ASC';
$page       = 1;
$totalPages = 1;

// Incluir modal original de clientes
include dirname(__DIR__) . '/clientes/modal_cliente.php';

// RESTAURAR variables originales para pedidos
$rutaModulo = $rutaModuloOriginal;
$perm       = $permOriginal;
$ordenCol   = $ordenColOriginal;
$ordenDir   = $ordenDirOriginal;
$page       = $pageOriginal;
$totalPages = $totalPagesOriginal;
?>

<script src="<?= $base ?>/js/modulos/clientes_modal.js?v=<?= asset_ver('/js/modulos/clientes_modal.js') ?>"></script>
<script src="<?= $base ?>/js/components/dropdown_flotante.js?v=<?= asset_ver('/js/components/dropdown_flotante.js') ?>"></script>
<script src="<?= $base ?>/js/modulos/pedidos.js?v=<?= asset_ver('/js/modulos/pedidos.js') ?>"></script>