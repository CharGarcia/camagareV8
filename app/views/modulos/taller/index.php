<?php
/**
 * Taller Mecánico — listado de órdenes de trabajo.
 *
 * Tabla estándar del sistema (borde a borde vía cmg-table-card) con buscador de
 * filtros, columnas configurables por usuario y exportación PDF/Excel.
 *
 * @var string $titulo
 * @var array  $perm
 * @var string $rutaModulo
 * @var array  $empresa
 * @var array  $rows
 * @var int    $total
 * @var int    $page
 * @var int    $totalPages
 * @var int    $perPage
 * @var string $buscar
 * @var string $ordenCol
 * @var string $ordenDir
 * @var array  $vistaConfig
 * @var array  $puntos
 * @var array  $formasPago
 * @var array  $bodegas
 * @var array  $tarifasIva
 * @var array  $unidades
 * @var array  $departamentos
 * @var array  $empleados
 * @var array  $checklistBase
 */

use App\controllers\modulos\TallerController;

$base    = BASE_URL;
$urlBase = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');

$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>

<style>
    .tll-header { flex-shrink: 0; }
    .taller-scroll { max-height: calc(100dvh - 240px); overflow-y: auto; }
    .taller-scroll thead th {
        position: sticky; top: 0; z-index: 1;
        background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6;
    }
    .tll-row { cursor: pointer; }
    .tll-row:hover { background-color: rgba(0, 0, 0, .04); }

    /* Sin barra de desplazamiento visible en el modal: el contenido se sigue
       moviendo con la rueda, el teclado y el gesto táctil. */
    #modalOrdenTaller .modal-body { scrollbar-width: none; -ms-overflow-style: none; }
    #modalOrdenTaller .modal-body::-webkit-scrollbar { width: 0; height: 0; display: none; }

    /* Grilla de repuestos / mano de obra dentro del modal */
    #modalOrdenTaller .table-detalle th { font-size: .7rem; text-transform: uppercase; padding: 4px 8px !important; background-color: #f8f9fa; }
    #modalOrdenTaller .table-detalle td { padding: 0 !important; vertical-align: middle; }
    #modalOrdenTaller .input-detalle { border: none; background: transparent; height: 30px !important; font-size: .82rem !important; padding: 2px 8px !important; }
    #modalOrdenTaller .input-detalle:focus { background: #fff; box-shadow: inset 0 0 0 1px #0d6efd; outline: none; }
    #modalOrdenTaller .row-detalle:hover { background-color: rgba(13, 110, 253, .03); }
    #modalOrdenTaller .x-small { font-size: .72rem; }

    /* Línea de tiempo de la bitácora */
    .tll-timeline { position: relative; padding-left: 22px; }
    .tll-timeline::before { content: ''; position: absolute; left: 7px; top: 4px; bottom: 4px; width: 2px; background: #e9ecef; }
    .tll-timeline .tll-ev { position: relative; padding-bottom: 12px; }
    .tll-timeline .tll-ev::before {
        content: ''; position: absolute; left: -19px; top: 4px;
        width: 10px; height: 10px; border-radius: 50%; background: #adb5bd; border: 2px solid #fff;
    }
    .tll-timeline .tll-ev.ev-aprobacion::before { background: #198754; }
    .tll-timeline .tll-ev.ev-rechazo::before    { background: #dc3545; }
    .tll-timeline .tll-ev.ev-ingreso::before    { background: #0d6efd; }
    .tll-timeline .tll-ev.ev-entrega::before    { background: #198754; }
    .tll-foto { width: 88px; height: 66px; object-fit: cover; border-radius: 6px; border: 1px solid #dee2e6; cursor: pointer; }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>

<!-- Encabezado -->
<div class="tll-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-wrench-adjustable-circle text-primary"></i> <?= htmlspecialchars($titulo) ?></h5>
    <div class="d-flex gap-2">
        <?php if (!empty($perm['crear']) || !empty($perm['todo'])): ?>
            <button type="button" class="btn btn-primary btn-sm px-3" onclick="tllAbrirNuevo()">
                <i class="bi bi-plus-lg"></i> Recibir vehículo
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_busqueda.css?v=<?= time() ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_busqueda.js?v=<?= time() ?>"></script>
            <div id="fbBuscadorTaller" style="width: 460px;"></div>
            <input type="hidden" id="b" name="b" value="<?= htmlspecialchars($buscar) ?>">

            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosBusqueda) return;
                    new FiltrosBusqueda({
                        containerId: 'fbBuscadorTaller',
                        hiddenInputId: 'b',
                        fields: [
                            { key: 'orden',       label: 'N° Orden',     icon: 'bi-hash',       type: 'text' },
                            { key: 'placa',       label: 'Placa',        icon: 'bi-car-front',  type: 'text' },
                            { key: 'cliente',     label: 'Cliente',      icon: 'bi-person',     type: 'text' },
                            { key: 'marca',       label: 'Marca',        icon: 'bi-tag',        type: 'text' },
                            { key: 'aseguradora', label: 'Aseguradora',  icon: 'bi-shield',     type: 'text' },
                            { key: 'fecha',       label: 'Fecha',        icon: 'bi-calendar',   type: 'date' },
                            { key: 'estado',      label: 'Estado',       icon: 'bi-flag',       type: 'select', options: [
                                { v: 'recepcion',       l: 'Recepción' },
                                { v: 'diagnostico',     l: 'Diagnóstico' },
                                { v: 'presupuesto',     l: 'Presupuesto' },
                                { v: 'aprobada',        l: 'Aprobada' },
                                { v: 'en_proceso',      l: 'En proceso' },
                                { v: 'control_calidad', l: 'Control de calidad' },
                                { v: 'terminada',       l: 'Terminada' },
                                { v: 'entregada',       l: 'Entregada' },
                                { v: 'facturada',       l: 'Facturada' },
                                { v: 'anulada',         l: 'Anulada' },
                            ]},
                            { key: 'tipo', label: 'Tipo servicio', icon: 'bi-wrench', type: 'select', options: [
                                { v: 'mantenimiento', l: 'Mantenimiento' },
                                { v: 'correctivo',    l: 'Correctivo' },
                                { v: 'colision',      l: 'Colisión' },
                                { v: 'garantia',      l: 'Garantía' },
                                { v: 'revision',      l: 'Revisión' },
                            ]},
                        ],
                        quickFilters: [
                            { id: 'qf_proceso',   label: 'En taller',      mk: () => ({ key: 'estado', op: '=', value: 'en_proceso', display: 'En proceso' }) },
                            { id: 'qf_presup',    label: 'Por aprobar',    mk: () => ({ key: 'estado', op: '=', value: 'presupuesto', display: 'Presupuesto' }) },
                            { id: 'qf_terminada', label: 'Para entregar',  mk: () => ({ key: 'estado', op: '=', value: 'terminada', display: 'Terminadas' }) },
                        ],
                        onApply: () => { g_paginaActual = 1; cargarGrid(); },
                    }).init();
                });
            </script>

            <div class="btn-group btn-group-sm">
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas([
                    'fecha_ingreso' => 'Fecha',
                    'numero_orden'  => 'N° Orden',
                    'placa'         => 'Placa',
                    'vehiculo'      => 'Vehículo',
                    'cliente'       => 'Cliente',
                    'departamento'  => 'Departamento',
                    'aprobado'      => 'Aprob.',
                    'total'         => 'Total',
                    'estado'        => 'Estado',
                ], $vistaConfig ?? [], 'taller'); ?>

                <a class="btn btn-outline-danger pdf-export-btn" href="<?= $urlBase ?>/export-pdf?b=<?= urlencode($buscar) ?>&sort=<?= $ordenCol ?>&dir=<?= $ordenDir ?>" target="_blank" title="Exportar a PDF"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                <a class="btn btn-outline-success excel-export-btn" href="<?= $urlBase ?>/export-excel?b=<?= urlencode($buscar) ?>&sort=<?= $ordenCol ?>&dir=<?= $ordenDir ?>" title="Exportar a Excel"><i class="bi bi-file-earmark-excel"></i> Excel</a>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <span id="pagination-info" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="pagination-controls" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" <?= $page <= 1 ? 'disabled' : '' ?> onclick="cambiarPaginaAjax(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" <?= $page >= $totalPages ? 'disabled' : '' ?> onclick="cambiarPaginaAjax(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="taller-scroll w-100">
            <table class="table table-hover table-sm mb-0" id="tablaTaller">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-col="fecha_ingreso">Fecha <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-col="numero_orden">N° Orden <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-col="placa">Placa <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-col="vehiculo">Vehículo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-col="cliente">Cliente <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-col="departamento">Departamento <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center" data-col="aprobado" title="Presupuesto aprobado por el cliente">Aprob.</th>
                        <th class="text-end sortable-header" role="button" data-col="total">Total <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center pe-3 sortable-header" role="button" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="grid-body">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="bi bi-wrench-adjustable-circle fs-3 d-block mb-2"></i>
                                No se encontraron órdenes de trabajo.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r):
                            $fecha    = !empty($r['fecha_ingreso']) ? date('d-m-Y H:i', strtotime((string) $r['fecha_ingreso'])) : '';
                            $dataJson = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                            $vehiculo = trim((string) ($r['marca'] ?? '') . ' ' . (string) ($r['modelo'] ?? ''));
                            $depColor = (string) ($r['departamento_color'] ?? '#6c757d');
                        ?>
                            <tr class="tll-row" role="button" tabindex="0" data-row="<?= $dataJson ?>" onclick="tllAbrirVer(this)">
                                <td class="ps-3" data-col="fecha_ingreso"><?= htmlspecialchars($fecha) ?></td>
                                <td data-col="numero_orden" class="fw-bold text-primary"><?= htmlspecialchars((string) ($r['numero_orden'] ?? '')) ?></td>
                                <td data-col="placa" class="fw-semibold"><?= htmlspecialchars((string) ($r['placa'] ?? '')) ?></td>
                                <td data-col="vehiculo" class="text-truncate" style="max-width:180px" title="<?= htmlspecialchars($vehiculo) ?>"><?= htmlspecialchars($vehiculo) ?></td>
                                <td data-col="cliente" class="text-truncate" style="max-width:200px" title="<?= htmlspecialchars((string) ($r['cliente_nombre'] ?? '')) ?>"><?= htmlspecialchars((string) ($r['cliente_nombre'] ?? '')) ?></td>
                                <td data-col="departamento">
                                    <?php if (!empty($r['departamento_nombre'])): ?>
                                        <span class="badge rounded-pill" style="background:<?= htmlspecialchars($depColor) ?>1a;color:<?= htmlspecialchars($depColor) ?>;border:1px solid <?= htmlspecialchars($depColor) ?>40;">
                                            <?= htmlspecialchars((string) $r['departamento_nombre']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center" data-col="aprobado">
                                    <?php if (\App\Helpers\Booleano::es($r['aprobado'] ?? false)): ?>
                                        <i class="bi bi-check-circle-fill text-success" title="Presupuesto aprobado"></i>
                                    <?php else: ?>
                                        <i class="bi bi-clock-history text-warning" title="Sin aprobación del cliente"></i>
                                    <?php endif; ?>
                                </td>
                                <td data-col="total" class="text-end"><?= number_format((float) ($r['total'] ?? 0), 2) ?></td>
                                <td class="text-center pe-3" data-col="estado"><?= TallerController::badgeEstado((string) ($r['estado'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Datos que consume el JS del módulo -->
<script>
    window.RUTA_MODULO_TALLER = '<?= $urlBase ?>';
    // Catálogos que se pueden alimentar al vuelo desde la barra del modal.
    window.RUTA_TALLER_DEPARTAMENTOS = '<?= rtrim($base, '/') ?>/modulos/taller-departamentos';
    window.RUTA_TALLER_CHECKLIST     = '<?= rtrim($base, '/') ?>/modulos/taller-checklist';
    window.TLL_PERM = {
        crear:      <?= (!empty($perm['crear']) || !empty($perm['todo'])) ? 'true' : 'false' ?>,
        actualizar: <?= (!empty($perm['actualizar']) || !empty($perm['todo'])) ? 'true' : 'false' ?>,
        eliminar:   <?= (!empty($perm['eliminar']) || !empty($perm['todo'])) ? 'true' : 'false' ?>
    };
    <?php $bt = fn($k, $def) => ((($empresa[$k] ?? $def) === 'true' || ($empresa[$k] ?? $def) === true) ? 'true' : 'false'); ?>
    window.EMPRESA_CONFIG = {
        facturacion_libre: <?= $bt('facturacion_libre', false) ?>,
        facturacion_inventario: <?= $bt('facturacion_inventario', true) ?>,
        editar_precio_factura: <?= $bt('editar_precio_factura', true) ?>,
        editar_iva_factura: <?= $bt('editar_iva_factura', true) ?>,
        editar_descuento_factura: <?= $bt('editar_descuento_factura', true) ?>,
        decimales_precio: <?= (int) ($empresa['decimales_precio'] ?? 2) ?>,
        decimales_cantidad: <?= (int) ($empresa['decimales_cantidad'] ?? 2) ?>
    };
    window.TLL_TARIFAS_IVA   = <?= json_encode($tarifasIva ?? []) ?>;
    window.TLL_DEPARTAMENTOS = <?= json_encode(array_map(fn($d) => [
        'id'     => (int) $d['id'],
        'nombre' => $d['nombre'] ?? '',
        'color'  => $d['color'] ?? '#0d6efd',
        'icono'  => $d['icono'] ?? 'bi-tools',
    ], $departamentos ?? [])) ?>;
    window.TLL_EMPLEADOS = <?= json_encode(array_map(fn($e) => [
        'id'     => (int) $e['id'],
        'nombre' => $e['nombres_apellidos'] ?? '',
    ], $empleados ?? [])) ?>;
    window.TLL_CHECKLIST_BASE = <?= json_encode(array_map(fn($c) => [
        'grupo' => $c['grupo'] ?? 'accesorios',
        'item'  => $c['item'] ?? '',
        'orden' => (int) ($c['orden'] ?? 0),
    ], $checklistBase ?? [])) ?>;
    window.TLL_PUNTOS = <?= json_encode(array_map(fn($p) => [
        'id'                  => (int) $p['id'],
        'id_establecimiento'  => (int) ($p['id_establecimiento'] ?? 0),
        'cod_establecimiento' => $p['cod_establecimiento'] ?? '',
        'codigo_punto'        => $p['codigo_punto'] ?? '',
    ], $puntos ?? [])) ?>;
    window.TLL_FORMAS_PAGO = <?= json_encode($formasPago ?? []) ?>;
    window.TLL_BODEGAS = <?= json_encode(array_map(fn($b) => ['id' => (int) $b['id'], 'nombre' => $b['nombre'] ?? ''], $bodegas ?? [])) ?>;
    window.USUARIO_NOMBRE = '<?= htmlspecialchars($_SESSION['nombre'] ?? '', ENT_QUOTES) ?>';
</script>

<?php echo \App\Helpers\PreferenciasHelper::getJavascriptVariables($rutaModulo); ?>

<?php include __DIR__ . '/modal_orden.php'; ?>

<!-- Modales reutilizados para crear entidades al vuelo (mismo patrón que Factura) -->
<?php
    include dirname(__DIR__) . '/vehiculos/modal_vehiculo.php';
    include dirname(__DIR__) . '/clientes/modal_cliente.php';
    include dirname(__DIR__) . '/productos/modal.php';
?>
<script src="<?= BASE_URL ?>/js/modulos/vehiculos_modal.js?v=<?= time() ?>"></script>
<script src="<?= BASE_URL ?>/js/modulos/clientes_modal.js?v=<?= time() ?>"></script>
<script src="<?= BASE_URL ?>/js/modulos/productos_modal.js?v=<?= time() ?>"></script>

<!-- Dropdown global de búsqueda de repuestos de la grilla (igual que factura) -->
<div id="tll-dropdown-productos-global" class="list-group shadow position-fixed d-none" style="z-index: 9999; min-width: 400px; max-height: 250px; overflow-y: auto; background-color: white;"></div>

<script src="<?= BASE_URL ?>/js/modulos/taller.js?v=<?= time() ?>"></script>

<script>
    let g_ordenCol = '<?= addslashes($ordenCol) ?>';
    let g_ordenDir = '<?= addslashes($ordenDir) ?>';
    let g_paginaActual = <?= (int) $page ?>;
    let g_buscar = '<?= addslashes($buscar) ?>';

    // Orden a desplegar al entrar desde el tablero. Llega desde la sesión, no
    // por la URL, para que la barra de direcciones quede limpia.
    const TLL_ABRIR_ORDEN = <?= (int) ($abrirOrden ?? 0) ?>;

    document.addEventListener("DOMContentLoaded", function () {
        if (TLL_ABRIR_ORDEN > 0 && typeof tllAbrirPorId === 'function') {
            tllAbrirPorId(TLL_ABRIR_ORDEN);
        }

        document.querySelectorAll('#tablaTaller th.sortable-header').forEach(th => {
            th.addEventListener('click', () => {
                const col = th.dataset.col;
                if (g_ordenCol === col) g_ordenDir = g_ordenDir === 'ASC' ? 'DESC' : 'ASC';
                else { g_ordenCol = col; g_ordenDir = 'ASC'; }
                actualizarIconosOrden(col, g_ordenDir);
                cargarGrid();
            });
        });
    });

    function actualizarIconosOrden(col, dir) {
        document.querySelectorAll('#tablaTaller th.sortable-header').forEach(th => {
            const icon = th.querySelector('i');
            if (icon) {
                icon.className = 'bi bi-arrow-down-up text-muted ms-1';
                if (th.dataset.col === col) icon.className = dir === 'ASC' ? 'bi bi-sort-down text-primary ms-1' : 'bi bi-sort-up text-primary ms-1';
            }
        });
    }

    function cambiarPaginaAjax(p) { g_paginaActual = p; cargarGrid(); }

    async function cargarGrid() {
        try {
            const tbody = document.getElementById('grid-body');
            tbody.innerHTML = '<tr><td colspan="9" class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></td></tr>';
            const bInput = document.getElementById('b');
            g_buscar = bInput ? bInput.value : '';
            const params = new URLSearchParams({ b: g_buscar, page: g_paginaActual, sort: g_ordenCol, dir: g_ordenDir });
            const res = await fetch(`${window.RUTA_MODULO_TALLER}/searchAjax?${params.toString()}`);
            const data = await res.json();
            if (data.ok) {
                tbody.innerHTML = data.rows;
                document.getElementById('pagination-info').textContent = data.info;
                document.getElementById('pagination-controls').innerHTML = data.pagination;
                const pdf = document.querySelector('.pdf-export-btn'); if (pdf) pdf.href = data.pdf_url;
                const xls = document.querySelector('.excel-export-btn'); if (xls) xls.href = data.excel_url;
            }
        } catch (e) {
            console.error(e);
            Swal.fire('Error', 'No se pudo cargar la lista', 'error');
        }
    }
</script>
