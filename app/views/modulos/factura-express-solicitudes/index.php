<?php
/** @var array  $perm */
/** @var array  $rows */
/** @var int    $total */
/** @var int    $page */
/** @var int    $totalPages */
/** @var int    $perPage */
/** @var int    $from */
/** @var int    $to */
/** @var string $buscar */
/** @var string $estadoFiltro */
/** @var string $ordenCol */
/** @var string $ordenDir */
/** @var array  $vistaConfig */
/** @var string $rutaModulo */

$base    = BASE_URL;
$urlBase = rtrim($base, '/') . '/modulos/factura-express-solicitudes';

$rows       = $rows       ?? [];
$total      = $total      ?? 0;
$page       = $page       ?? 1;
$totalPages = $totalPages ?? 1;
$perPage    = $perPage    ?? 20;
$ordenCol   = $ordenCol   ?? 'created_at';
$ordenDir   = $ordenDir   ?? 'DESC';
$buscar     = $buscar     ?? '';
$estadoFiltro = $estadoFiltro ?? 'pendiente';
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;

$estadosOpc = [
    'pendiente' => ['label' => 'Pendientes', 'color' => 'warning'],
    'aprobada'  => ['label' => 'Aprobadas',  'color' => 'info'],
    'rechazada' => ['label' => 'Rechazadas', 'color' => 'danger'],
    'facturada' => ['label' => 'Facturadas', 'color' => 'success'],
    ''          => ['label' => 'Todas',      'color' => 'secondary'],
];
$estadoClases = ['pendiente' => 'warning', 'aprobada' => 'info', 'rechazada' => 'danger', 'facturada' => 'success'];

$tarifasIva    = $tarifasIva    ?? [];
$decimalesPrec = $decimalesPrec ?? 2;
?>
<style>
    .fexsol-scroll { max-height: calc(100dvh - 240px); overflow-y: auto; overflow-x: auto; }
    .fexsol-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
    .fexsol-row { cursor: pointer; }
    .fexsol-row:hover { background: rgba(0,0,0,.04); }

    /* Responsive: en móvil los filtros, buscador y exportación se apilan y no se desbordan */
    @media (max-width: 575.98px) {
        .fexsol-toolbar { width: 100%; }
        .fexsol-toolbar > * { width: 100%; }
        .fexsol-toolbar .btn-group { flex-wrap: wrap; row-gap: .25rem; }
        .fexsol-toolbar .input-group { width: 100% !important; }
        .fexsol-toolbar .fexsol-estado-btn { flex: 1 1 auto; }
    }

    /* ── Tabla de ítems editable (mismo diseño que Facturas de Venta) ── */
    .table-detalle th {
        font-size: 0.7rem;
        text-transform: uppercase;
        background-color: #f8f9fa;
        padding: 4px 8px;
        font-weight: 700;
        color: #6c757d;
    }
    .table-detalle td { padding: 0 !important; vertical-align: middle; }
    .input-detalle {
        border: none;
        background: transparent;
        font-size: 0.82rem;
        height: 30px;
        padding: 2px 8px;
        width: 100%;
    }
    .input-detalle:focus {
        background: #fff;
        box-shadow: inset 0 0 0 1px #0d6efd;
        outline: none;
    }
    .row-detalle:hover { background-color: rgba(13, 110, 253, 0.03); }
    #fexsolDropProductos { z-index: 2000 !important; }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>

<!-- ===== Vista de ESCRITORIO (tabla) — oculta en móvil ===== -->
<div class="d-none d-md-block">
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-bell text-warning me-2"></i><?= htmlspecialchars($titulo) ?></h5>
    <a href="<?= rtrim($base, '/') ?>/modulos/factura-express-config" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-qr-code me-1"></i>Configuración QR
    </a>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">

        <!-- Filtros de estado + buscador + exportación -->
        <div class="d-flex align-items-center gap-2 flex-wrap fexsol-toolbar">
            <!-- Filtros de estado -->
            <div class="btn-group btn-group-sm flex-wrap">
                <?php foreach ($estadosOpc as $key => $opc): ?>
                    <button type="button"
                        class="btn fexsol-estado-btn <?= $estadoFiltro === $key ? 'btn-' . $opc['color'] : 'btn-outline-' . $opc['color'] ?>"
                        data-estado="<?= $key ?>" data-color="<?= $opc['color'] ?>"
                        onclick="fexsolFiltrarEstado('<?= $key ?>')">
                        <?= $opc['label'] ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Buscador -->
            <div class="input-group input-group-sm" style="width:280px">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" id="fexsolBuscar" class="form-control border-start-0 ps-0 shadow-none border"
                       placeholder="Buscar solicitud..." value="<?= htmlspecialchars($buscar) ?>" autocomplete="off">
                <?php if ($buscar !== ''): ?>
                    <a href="<?= $urlBase ?>" class="btn border border-start-0 text-muted" title="Limpiar"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </div>

            <!-- Columnas + exportación -->
            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'created_at'     => 'Fecha',
                    'nombre_cliente' => 'Cliente',
                    'identificacion' => 'Identificación',
                    'correo_cliente' => 'Correo',
                    'nombre_plantilla' => 'Plantilla',
                    'monto_total'    => 'Monto',
                    'estado'         => 'Estado',
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>

                <a id="fexsolBtnPdf"
                   href="<?= $urlBase ?>/export-pdf?b=<?= urlencode($buscar) ?>&estado=<?= urlencode($estadoFiltro) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                   class="btn btn-outline-danger" title="Descargar PDF">
                    <i class="bi bi-file-earmark-pdf"></i> PDF
                </a>
                <a id="fexsolBtnExcel"
                   href="<?= $urlBase ?>/export-excel?b=<?= urlencode($buscar) ?>&estado=<?= urlencode($estadoFiltro) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                   class="btn btn-outline-success" title="Descargar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                </a>
            </div>
        </div>

        <!-- Paginación -->
        <div class="d-flex align-items-center gap-3">
            <span id="fexsolPaginInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="fexsolPaginContainer" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" onclick="fexsolCambiarPagina(<?= $page - 1 ?>)" <?= $page <= 1 ? 'disabled' : '' ?>><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" onclick="fexsolCambiarPagina(<?= $page + 1 ?>)" <?= $page >= $totalPages ? 'disabled' : '' ?>><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="fexsol-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="created_at" data-col="created_at">Fecha <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="nombre_cliente" data-col="nombre_cliente">Cliente <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="identificacion" data-col="identificacion">Identificación <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="correo_cliente" data-col="correo_cliente">Correo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="nombre_plantilla" data-col="nombre_plantilla">Plantilla <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-end sortable-header" role="button" data-sort="monto_total" data-col="monto_total">Monto <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center pe-3 sortable-header" role="button" data-sort="estado" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="fexsolTbody">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-bell fs-3 d-block mb-2 opacity-25"></i>
                            No hay solicitudes<?= $estadoFiltro ? ' con estado "' . htmlspecialchars($estadoFiltro) . '"' : '' ?>.
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r):
                            $cls   = $estadoClases[$r['estado'] ?? 'pendiente'] ?? 'secondary';
                            $lbl   = ucfirst($r['estado'] ?? '');
                            $fecha = !empty($r['created_at']) ? date('d-m-Y H:i', strtotime($r['created_at'])) : '-';
                            $monto = number_format((float)($r['monto_total'] ?? 0), 2);
                        ?>
                            <tr class="fexsol-row" role="button" tabindex="0"
                                data-row="<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>"
                                onclick="fexsolAbrirSolicitud(this)">
                                <td class="ps-3 text-nowrap" data-col="created_at"><small><?= $fecha ?></small></td>
                                <td class="fw-medium" data-col="nombre_cliente"><?= htmlspecialchars($r['nombre_cliente'] ?? '') ?></td>
                                <td data-col="identificacion"><small class="text-muted"><?= htmlspecialchars($r['identificacion'] ?? '') ?></small></td>
                                <td data-col="correo_cliente"><small><?= htmlspecialchars($r['correo_cliente'] ?? '-') ?></small></td>
                                <td data-col="nombre_plantilla"><small class="text-muted"><?= htmlspecialchars($r['nombre_plantilla'] ?? '-') ?></small></td>
                                <td class="text-end fw-bold" data-col="monto_total">$<?= $monto ?></td>
                                <td class="text-center pe-3" data-col="estado">
                                    <span class="badge bg-<?= $cls ?> bg-opacity-10 text-<?= $cls ?> border border-<?= $cls ?> border-opacity-25"><?= $lbl ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div><!-- /vista escritorio -->

<!-- ===== Vista MÓVIL (panel de tarjetas) — oculta en escritorio ===== -->
<div class="d-md-none">
    <?php
    $fexpEmbedded    = true;   // se incluye dentro del index; oculta el botón "volver"
    $idPlantilla     = 0;      // URL limpia: sin filtro de plantilla (muestra todas)
    $nombrePlantilla = null;
    include __DIR__ . '/panel.php';
    ?>
</div>

<!-- Modal detalle solicitud -->
<div class="modal fade" id="modalFexsolSolicitud" tabindex="-1" style="z-index:1060">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-file-earmark-text text-primary me-2"></i>Detalle de Solicitud</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="p-2 border rounded-3 bg-light mb-3">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <div class="small text-muted">Cliente</div>
                            <div class="fw-medium" id="fexsolDetNombre"></div>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted">Identificación</div>
                            <div id="fexsolDetIdentificacion"></div>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted">Tipo</div>
                            <div id="fexsolDetTipo"></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Correo</div>
                            <div id="fexsolDetCorreo"></div>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted">Teléfono</div>
                            <div id="fexsolDetTelefono"></div>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted">Plantilla</div>
                            <div id="fexsolDetPlantilla"></div>
                        </div>
                    </div>
                </div>

                <!-- Ítems en modo SOLO LECTURA (solicitudes ya procesadas) -->
                <div class="mb-3" id="fexsolItemsReadonly">
                    <div class="fw-medium small mb-1">Ítems solicitados</div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0" style="font-size:0.82rem">
                            <thead class="table-light">
                                <tr>
                                    <th>Descripción</th>
                                    <th class="text-center" style="width:80px">Cant.</th>
                                    <th class="text-end" style="width:100px">P. Unit.</th>
                                    <th class="text-end" style="width:60px">IVA%</th>
                                    <th class="text-end" style="width:100px">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody id="fexsolDetItems"></tbody>
                            <tfoot>
                                <tr class="table-light">
                                    <td colspan="4" class="text-end fw-bold">Total:</td>
                                    <td class="text-end fw-bold" id="fexsolDetTotal"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Ítems en modo EDITABLE (solicitudes pendientes con permiso de modificar) -->
                <div class="mb-3 d-none" id="fexsolItemsEditable">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="fw-medium small">Ítems a facturar <span class="text-muted">(editable)</span></span>
                    </div>
                    <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                        <div class="table-responsive" style="max-height: 320px;">
                            <table class="table table-sm table-detalle mb-0 text-nowrap">
                                <thead>
                                    <tr class="table-light border-bottom">
                                        <th class="ps-3" style="width: 42%;">Descripción</th>
                                        <th class="text-center" style="width: 12%;">Cant.</th>
                                        <th class="text-end" style="width: 16%;">P. Unit.</th>
                                        <th class="text-center" style="width: 14%;">IVA</th>
                                        <th class="text-end pe-3" style="width: 14%;">Subtotal</th>
                                        <th style="width: 36px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="fexsolEditTbody"></tbody>
                            </table>
                        </div>
                        <div class="p-2 border-top bg-light d-flex justify-content-between align-items-center">
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" onclick="fexsolAgregarFila()">
                                <i class="bi bi-plus-circle me-1"></i> Agregar línea
                            </button>
                            <div class="fw-bold">Total: <span id="fexsolEditTotal" class="text-primary">$0.00</span></div>
                        </div>
                    </div>
                </div>

                <div id="fexsolDetEstadoInfo" class="d-none p-2 border rounded-3 mb-3">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <div class="small text-muted">Estado</div>
                            <div id="fexsolDetEstado"></div>
                        </div>
                        <div class="col-md-8">
                            <div class="small text-muted">Nota</div>
                            <div id="fexsolDetNota" class="text-muted fst-italic"></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Procesado por</div>
                            <div id="fexsolDetAprobadoPor"></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Fecha</div>
                            <div id="fexsolDetAprobadoAt"></div>
                        </div>
                    </div>
                </div>

                <div id="fexsolDetNotaRechazar" class="d-none">
                    <label class="form-label small fw-medium">Motivo del rechazo <span class="text-danger">*</span></label>
                    <textarea id="fexsolInputNota" class="form-control form-control-sm" rows="2" placeholder="Ingrese el motivo..."></textarea>
                </div>

                <input type="hidden" id="fexsolDetId">
            </div>
            <div class="modal-footer bg-light border-top p-2 justify-content-end gap-2">
                <div id="fexsolBotonesAccion"></div>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                    <i class="fa-solid fa-xmark me-1"></i>Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Dropdown global para el buscador de productos (ítems editables) -->
<div id="fexsolDropProductos" class="list-group shadow position-fixed d-none" style="z-index: 2000; min-width: 360px; max-height: 250px; overflow-y: auto; background:#fff;"></div>

<script>
(function () {
    'use strict';
    const urlBase         = '<?= $urlBase ?>';
    const puedeActualizar = <?= !empty($perm['actualizar']) ? 'true' : 'false' ?>;
    const TARIFAS_IVA     = <?= json_encode($tarifasIva, JSON_UNESCAPED_UNICODE) ?>;
    const DEC_PRECIO      = <?= (int) $decimalesPrec ?>;
    window.currentSort  = '<?= $ordenCol ?>';
    window.currentDir   = '<?= $ordenDir ?>';
    let currentEstado = '<?= htmlspecialchars($estadoFiltro) ?>';
    let timer;

    window.fexsolCambiarPagina = p => fexsolBuscar(p);

    window.fexsolFiltrarEstado = function(estado) {
        currentEstado = estado;
        // Resaltar el botón de filtro activo
        document.querySelectorAll('.fexsol-estado-btn').forEach(b => {
            const color  = b.dataset.color;
            const activo = b.dataset.estado === estado;
            b.classList.toggle('btn-' + color, activo);
            b.classList.toggle('btn-outline-' + color, !activo);
        });
        fexsolBuscar(1);
    };

    async function fexsolBuscar(page = 1) {
        const b    = document.getElementById('fexsolBuscar').value.trim();
        const tbody = document.getElementById('fexsolTbody');
        const uri  = `${urlBase}/searchAjax?b=${encodeURIComponent(b)}&estado=${encodeURIComponent(currentEstado)}&page=${page}&sort=${window.currentSort}&dir=${window.currentDir}`;
        try {
            const r = await fetch(uri, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!r.ok) {
                tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Tu sesión pudo haber expirado. Recarga la página (F5) e inicia sesión nuevamente.</td></tr>`;
                return;
            }
            const d = await r.json();
            if (d.ok) {
                tbody.innerHTML                                          = d.rows || '<tr><td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-bell fs-3 d-block mb-2 opacity-25"></i>No hay solicitudes con este filtro.</td></tr>';
                document.getElementById('fexsolPaginContainer').innerHTML = d.pagination;
                document.getElementById('fexsolPaginInfo').textContent    = d.info;
                if (d.pdf_url)   document.getElementById('fexsolBtnPdf').href   = d.pdf_url;
                if (d.excel_url) document.getElementById('fexsolBtnExcel').href = d.excel_url;

                document.querySelectorAll('.sortable-header').forEach(th => {
                    const icon  = th.querySelector('i');
                    const field = th.dataset.sort;
                    if (field === window.currentSort) {
                        icon.className = window.currentDir.toLowerCase() === 'asc'
                            ? 'bi bi-sort-alpha-down text-primary ms-1'
                            : 'bi bi-sort-alpha-up text-primary ms-1';
                    } else {
                        icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
                    }
                });
            } else {
                tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-danger"><i class="bi bi-exclamation-triangle me-1"></i>${escHtml(d.error || d.mensaje || 'No se pudo cargar el listado.')}</td></tr>`;
            }
        } catch(e) {
            console.error(e);
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Error de conexión al cargar el listado.</td></tr>`;
        }
    }

    document.getElementById('fexsolBuscar').addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => fexsolBuscar(1), 400);
    });

    window.CMG_initSort('factura_express_solicitudes', (col, dir) => {
        window.currentSort = col;
        window.currentDir  = dir;
        fexsolBuscar(1);
    }, { col: window.currentSort, dir: window.currentDir });

    window.fexsolAbrirSolicitud = function(el) {
        const r = JSON.parse(el.dataset.row);
        document.getElementById('fexsolDetId').value                   = r.id;
        document.getElementById('fexsolDetNombre').textContent         = r.nombre_cliente ?? '';
        document.getElementById('fexsolDetIdentificacion').textContent = r.identificacion ?? '';
        document.getElementById('fexsolDetTipo').textContent           = r.tipo_identificacion ?? '';
        document.getElementById('fexsolDetCorreo').textContent         = r.correo_cliente || '-';
        document.getElementById('fexsolDetTelefono').textContent       = r.telefono_cliente || '-';
        document.getElementById('fexsolDetPlantilla').textContent      = r.nombre_plantilla ?? '-';
        document.getElementById('fexsolDetNotaRechazar').classList.add('d-none');
        document.getElementById('fexsolInputNota').value = '';

        const items     = JSON.parse(r.items_json ?? '[]');
        const editable  = (r.estado === 'pendiente' && puedeActualizar);
        const boxRead   = document.getElementById('fexsolItemsReadonly');
        const boxEdit   = document.getElementById('fexsolItemsEditable');

        if (editable) {
            // ── Modo edición: tabla estilo Facturas de Venta ──
            boxRead.classList.add('d-none');
            boxEdit.classList.remove('d-none');
            document.getElementById('fexsolEditTbody').innerHTML = '';
            if (items.length) {
                items.forEach(it => fexsolAgregarFila(it));
            } else {
                fexsolAgregarFila();
            }
            fexsolCalcTotalEdit();
        } else {
            // ── Modo solo lectura ──
            boxEdit.classList.add('d-none');
            boxRead.classList.remove('d-none');
            let tbodyHtml = '';
            items.forEach(it => {
                const sub = parseFloat(it.cantidad || 0) * parseFloat(it.precio_unitario || 0);
                tbodyHtml += `<tr>
                    <td>${escHtml(it.descripcion ?? '')}</td>
                    <td class="text-center">${parseFloat(it.cantidad || 0).toFixed(2)}</td>
                    <td class="text-end">$${parseFloat(it.precio_unitario || 0).toFixed(2)}</td>
                    <td class="text-end">${parseFloat(it.porcentaje_iva || 0).toFixed(0)}%</td>
                    <td class="text-end">$${sub.toFixed(2)}</td>
                </tr>`;
            });
            document.getElementById('fexsolDetItems').innerHTML = tbodyHtml || '<tr><td colspan="5" class="text-center text-muted">Sin ítems</td></tr>';
            document.getElementById('fexsolDetTotal').textContent = '$' + parseFloat(r.monto_total || 0).toFixed(2);
        }

        const estadoDiv = document.getElementById('fexsolDetEstadoInfo');
        if (r.estado && r.estado !== 'pendiente') {
            estadoDiv.classList.remove('d-none');
            document.getElementById('fexsolDetEstado').textContent      = r.estado.charAt(0).toUpperCase() + r.estado.slice(1);
            document.getElementById('fexsolDetNota').textContent        = r.nota_aprobacion || '-';
            document.getElementById('fexsolDetAprobadoPor').textContent = r.aprobado_por_nombre || '-';
            document.getElementById('fexsolDetAprobadoAt').textContent  = r.aprobado_at ? formatFecha(r.aprobado_at) : '-';
        } else {
            estadoDiv.classList.add('d-none');
        }

        const btnsDiv = document.getElementById('fexsolBotonesAccion');
        btnsDiv.innerHTML = '';
        if (r.estado === 'pendiente' && puedeActualizar) {
            btnsDiv.innerHTML = `
                <button type="button" class="btn btn-outline-danger btn-sm" id="btnFexsolRechazar" onclick="fexsolMostrarRechazar()">
                    <i class="bi bi-x-circle me-1"></i>Rechazar
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="btnFexsolCancelarRech" onclick="fexsolCancelarRechazar()">
                    <i class="bi bi-arrow-left me-1"></i>Cancelar
                </button>
                <button type="button" class="btn btn-danger btn-sm d-none" id="btnFexsolGuardarRech" onclick="fexsolRechazar()">
                    <i class="bi bi-save me-1"></i>Guardar rechazo
                </button>
                <button type="button" class="btn btn-success btn-sm" id="btnFexsolAprobar" onclick="fexsolAprobar()">
                    <i class="bi bi-check-circle me-1"></i>Aprobar y Facturar
                </button>`;
        }

        new bootstrap.Modal(document.getElementById('modalFexsolSolicitud')).show();
    };

    // Entrar en "modo rechazo": muestra el motivo, bloquea Aprobar y cambia el botón a Guardar
    window.fexsolMostrarRechazar = function() {
        document.getElementById('fexsolDetNotaRechazar').classList.remove('d-none');
        document.getElementById('btnFexsolRechazar').classList.add('d-none');
        document.getElementById('btnFexsolCancelarRech').classList.remove('d-none');
        document.getElementById('btnFexsolGuardarRech').classList.remove('d-none');
        const btnAprobar = document.getElementById('btnFexsolAprobar');
        if (btnAprobar) btnAprobar.disabled = true;
        setTimeout(() => document.getElementById('fexsolInputNota').focus(), 50);
    };

    // Salir de "modo rechazo": vuelve al estado inicial
    window.fexsolCancelarRechazar = function() {
        document.getElementById('fexsolDetNotaRechazar').classList.add('d-none');
        document.getElementById('fexsolInputNota').value = '';
        document.getElementById('btnFexsolRechazar').classList.remove('d-none');
        document.getElementById('btnFexsolCancelarRech').classList.add('d-none');
        document.getElementById('btnFexsolGuardarRech').classList.add('d-none');
        const btnAprobar = document.getElementById('btnFexsolAprobar');
        if (btnAprobar) btnAprobar.disabled = false;
    };

    window.fexsolAprobar = async function() {
        const id  = document.getElementById('fexsolDetId').value;
        const btn = document.getElementById('btnFexsolAprobar');

        const itemsEditados = fexsolRecolectarItems();
        if (!itemsEditados.length) {
            Swal.fire({ icon:'warning', title:'Sin ítems', text:'Debe haber al menos un ítem con descripción y cantidad mayor a cero.' });
            return;
        }

        const confirmado = await Swal.fire({
            icon: 'question',
            title: '¿Aprobar y facturar?',
            text: 'Se generará la factura con los ítems mostrados.',
            showCancelButton: true,
            confirmButtonText: 'Sí, aprobar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#198754',
        });
        if (!confirmado.isConfirmed) return;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Procesando...';
        try {
            const fd = new FormData();
            fd.append('id', id);
            fd.append('items_json', JSON.stringify(itemsEditados));
            const res = await fetch(`${urlBase}/aprobar`, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const d   = await res.json();
            if (d.ok) {
                await Swal.fire({ icon:'success', title:'Aprobada', text: d.mensaje, timer:1500, showConfirmButton:false });
                bootstrap.Modal.getInstance(document.getElementById('modalFexsolSolicitud'))?.hide();
                fexsolBuscar(1);
            } else {
                Swal.fire({ icon:'error', title:'Error', text: d.mensaje });
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Aprobar y Facturar';
            }
        } catch(e) {
            Swal.fire({ icon:'error', title:'Error', text:'Error de conexión.' });
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Aprobar y Facturar';
        }
    };

    window.fexsolRechazar = async function() {
        const id   = document.getElementById('fexsolDetId').value;
        const nota = document.getElementById('fexsolInputNota').value.trim();
        const btn  = document.getElementById('btnFexsolGuardarRech');

        // El motivo es obligatorio
        if (!nota) {
            Swal.fire({ icon:'warning', title:'Motivo requerido', text:'Debe ingresar el motivo del rechazo.' });
            document.getElementById('fexsolInputNota').focus();
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';
        try {
            const fd = new FormData();
            fd.append('id', id);
            fd.append('nota', nota);
            const res = await fetch(`${urlBase}/rechazar`, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const d   = await res.json();
            if (d.ok) {
                await Swal.fire({ icon:'success', title:'Rechazada', text: d.mensaje, timer:1500, showConfirmButton:false });
                bootstrap.Modal.getInstance(document.getElementById('modalFexsolSolicitud'))?.hide();
                fexsolBuscar(1);
            } else {
                Swal.fire({ icon:'error', title:'Error', text: d.mensaje });
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-save me-1"></i>Guardar rechazo';
            }
        } catch(e) {
            Swal.fire({ icon:'error', title:'Error', text:'Error de conexión.' });
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-save me-1"></i>Guardar rechazo';
        }
    };

    // ═══════════════════════════════════════════════════════
    // ÍTEMS EDITABLES (mismo diseño que Facturas de Venta)
    // ═══════════════════════════════════════════════════════
    const dropProd = document.getElementById('fexsolDropProductos');

    function fexsolIvaOptions(pct) {
        return TARIFAS_IVA.map(t => {
            const val = parseFloat(t.porcentaje_iva);
            const sel = (pct !== null && Math.abs(val - pct) < 0.001) ? 'selected' : '';
            const lbl = t.tarifa ?? (val + '%');
            return `<option value="${val}" ${sel}>${escHtml(lbl)}</option>`;
        }).join('');
    }

    window.fexsolAgregarFila = function(it) {
        it = it || {};
        const tbody = document.getElementById('fexsolEditTbody');
        const tr    = document.createElement('tr');
        tr.className = 'row-detalle';
        const pct = (it.porcentaje_iva !== undefined && it.porcentaje_iva !== null) ? parseFloat(it.porcentaje_iva) : null;
        tr.innerHTML = `
            <td class="ps-3 position-relative">
                <input type="text" class="input-detalle fexsol-desc" placeholder="Buscar producto o escribir descripción..." value="${escAttr(it.descripcion ?? '')}">
                <input type="hidden" class="fexsol-idprod" value="${parseInt(it.id_producto) || ''}">
            </td>
            <td><input type="number" class="input-detalle text-center fexsol-cant" value="${parseFloat(it.cantidad ?? 1)}" step="any" min="0" oninput="fexsolCalcFila(this)"></td>
            <td><input type="number" class="input-detalle text-end fexsol-precio" value="${parseFloat(it.precio_unitario ?? 0).toFixed(DEC_PRECIO)}" step="any" min="0" oninput="fexsolCalcFila(this)" onblur="this.value=parseFloat(this.value||0).toFixed(DEC_PRECIO)"></td>
            <td><select class="input-detalle text-center fexsol-iva" onchange="fexsolCalcFila(this)">${fexsolIvaOptions(pct)}</select></td>
            <td class="text-end pe-3 fw-bold"><span class="fexsol-subtotal">0.00</span></td>
            <td class="text-center">
                <button type="button" class="btn btn-link btn-sm text-danger p-0 shadow-none border-0" onclick="this.closest('tr').remove(); fexsolCalcTotalEdit();" title="Eliminar ítem">
                    <i class="bi bi-trash3"></i>
                </button>
            </td>`;
        tbody.appendChild(tr);

        const inputDesc = tr.querySelector('.fexsol-desc');
        inputDesc.addEventListener('input', debounce(() => fexsolBuscarProducto(inputDesc, tr), 350));
        inputDesc.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                const b = dropProd.querySelector('button');
                if (b && !dropProd.classList.contains('d-none')) { e.preventDefault(); b.onmousedown(new MouseEvent('mousedown')); }
            }
        });
        inputDesc.addEventListener('blur', () => setTimeout(() => dropProd.classList.add('d-none'), 200));

        fexsolCalcFila(tr.querySelector('.fexsol-cant'));
        return tr;
    };

    async function fexsolBuscarProducto(inputDesc, tr) {
        const q = inputDesc.value.trim();
        if (q.length < 2) { dropProd.classList.add('d-none'); return; }
        const rect = inputDesc.getBoundingClientRect();
        dropProd.style.top   = `${rect.bottom + 2}px`;
        dropProd.style.left  = `${rect.left}px`;
        dropProd.style.width = `${Math.max(rect.width, 360)}px`;
        dropProd.classList.remove('d-none');
        dropProd.innerHTML = '<div class="list-group-item small text-muted">Buscando...</div>';
        try {
            const resp = await fetch(`${urlBase}/getProductosAjax?q=${encodeURIComponent(q)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await resp.json();
            dropProd.innerHTML = '';
            if (json.ok && json.data && json.data.length) {
                json.data.forEach(p => {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'list-group-item list-group-item-action small py-1 border-bottom';
                    b.innerHTML = `<div class="d-flex justify-content-between align-items-center text-start">
                        <div class="pe-3"><div class="fw-bold text-dark">${escHtml(p.nombre || '')}</div>
                        <div class="x-small text-muted">${escHtml(p.codigo || '')}</div></div>
                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10">$${parseFloat(p.precio_base || 0).toFixed(2)}</span></div>`;
                    b.onmousedown = (evt) => { evt.preventDefault(); fexsolSeleccionarProducto(p, tr); dropProd.classList.add('d-none'); };
                    dropProd.appendChild(b);
                });
            } else {
                dropProd.innerHTML = '<div class="list-group-item small text-muted">Sin coincidencias (puede escribir libre)</div>';
            }
        } catch (e) { dropProd.classList.add('d-none'); }
    }

    function fexsolSeleccionarProducto(p, tr) {
        tr.querySelector('.fexsol-desc').value   = p.nombre || '';
        tr.querySelector('.fexsol-idprod').value = p.id || '';
        tr.querySelector('.fexsol-precio').value = parseFloat(p.precio_base || 0).toFixed(DEC_PRECIO);
        const pct = (p.porcentaje_iva_final !== undefined && p.porcentaje_iva_final !== null) ? parseFloat(p.porcentaje_iva_final) : null;
        if (pct !== null) {
            const sel = tr.querySelector('.fexsol-iva');
            const opt = Array.from(sel.options).find(o => Math.abs(parseFloat(o.value) - pct) < 0.001);
            if (opt) sel.value = opt.value;
        }
        fexsolCalcFila(tr.querySelector('.fexsol-cant'));
        const inCant = tr.querySelector('.fexsol-cant');
        inCant.focus(); inCant.select();
    }

    window.fexsolCalcFila = function(el) {
        const tr   = el.closest('tr');
        const cant = parseFloat(tr.querySelector('.fexsol-cant').value) || 0;
        const prec = parseFloat(tr.querySelector('.fexsol-precio').value) || 0;
        const sub  = Math.round(cant * prec * 100) / 100;
        tr.querySelector('.fexsol-subtotal').textContent = sub.toFixed(2);
        fexsolCalcTotalEdit();
    };

    window.fexsolCalcTotalEdit = function() {
        let total = 0;
        document.querySelectorAll('#fexsolEditTbody tr').forEach(tr => {
            const cant = parseFloat(tr.querySelector('.fexsol-cant').value) || 0;
            const prec = parseFloat(tr.querySelector('.fexsol-precio').value) || 0;
            const iva  = parseFloat(tr.querySelector('.fexsol-iva').value) || 0;
            const base = Math.round(cant * prec * 100) / 100;
            total += base + Math.round(base * (iva / 100) * 100) / 100;
        });
        const lbl = document.getElementById('fexsolEditTotal');
        if (lbl) lbl.textContent = '$' + total.toFixed(2);
    };

    function fexsolRecolectarItems() {
        const items = [];
        document.querySelectorAll('#fexsolEditTbody tr').forEach(tr => {
            const desc = tr.querySelector('.fexsol-desc').value.trim();
            const cant = parseFloat(tr.querySelector('.fexsol-cant').value) || 0;
            if (!desc || cant <= 0) return;
            items.push({
                id_item:         0,
                id_producto:     parseInt(tr.querySelector('.fexsol-idprod').value) || null,
                descripcion:     desc,
                cantidad:        cant,
                precio_unitario: parseFloat(tr.querySelector('.fexsol-precio').value) || 0,
                porcentaje_iva:  parseFloat(tr.querySelector('.fexsol-iva').value) || 0,
            });
        });
        return items;
    }

    function debounce(fn, ms) {
        let t;
        return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); };
    }

    function escAttr(s) {
        return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function escHtml(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function formatFecha(str) {
        const d = new Date(str);
        return d.toLocaleDateString('es-EC', {day:'2-digit',month:'2-digit',year:'numeric'})
             + ' ' + d.toLocaleTimeString('es-EC', {hour:'2-digit',minute:'2-digit'});
    }
})();
</script>
