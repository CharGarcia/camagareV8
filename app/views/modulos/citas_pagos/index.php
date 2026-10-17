<?php
/** @var array       $perm */
/** @var array       $vistaConfig */
/** @var string      $rutaModulo */
/** @var array       $resumen */

$urlBase     = BASE_URL . '/' . $rutaModulo;
$vistaConfig = $vistaConfig ?? [];
$resumen     = $resumen     ?? [];

// Columnas configurables
$cols = [
    'fecha_reg'   => 'Fecha registro',
    'fecha_cita'  => 'Fecha cita',
    'cliente'     => 'Cliente',
    'tipo_cita'   => 'Tipo cita',
    'tipo_pago'   => 'Tipo pago',
    'gateway'     => 'Método',
    'referencia'  => 'Referencia',
    'monto'       => 'Monto',
    'estado'      => 'Estado',
];
?>

<?php
// Buscador: texto libre sobre las columnas del listado (sin sugerencias) + botón
// embudo que abre el modal con todos los filtros + chips de los activos. Las claves
// (key) deben existir en los mapas de CitaPagoRepository::getListado().
$opcionesFiltro  = $opcionesFiltro ?? [];
$opcionesTipo    = array_map(fn($t) => ['v' => (string) $t['id'], 'l' => $t['nombre']], $opcionesFiltro['tipos'] ?? []);
$opcionesRecurso = array_map(fn($r) => ['v' => (string) $r['id'], 'l' => $r['nombre']], $opcionesFiltro['recursos'] ?? []);
$opcionesUsuario = array_map(fn($u) => ['v' => (string) $u['id'], 'l' => $u['nombre']], $opcionesFiltro['usuarios'] ?? []);
// Una sola pestaña, sin "Detalles" (el pago no tiene tablas hijas).
// Filas de 12 columnas:
//   Pago: [Fecha de registro 6][Estado 3][Tipo de pago 3]
//         [Método 4][Monto 4][Usuario que registró 4]
//         [Referencia 12]
//   Cita: [Fecha de la cita 6][Tipo de cita 3][Recurso 3]
//         [Cliente 4][Identificación 4][Título de la cita 4]
$filtrosPagos = [
    ['key' => 'fecha',          'label' => 'Fecha de registro',    'icon' => 'bi-calendar-event',  'type' => 'date_range',   'grupo' => 'Pago', 'col' => 6, 'atajos' => true],
    ['key' => 'estado',         'label' => 'Estado',               'icon' => 'bi-flag',            'type' => 'select',       'grupo' => 'Pago', 'col' => 3, 'options' => [
        ['v' => 'pendiente',   'l' => 'Pendiente'],
        ['v' => 'completado',  'l' => 'Completado'],
        ['v' => 'fallido',     'l' => 'Fallido'],
        ['v' => 'reembolsado', 'l' => 'Reembolsado'],
    ]],
    ['key' => 'tipo_pago',      'label' => 'Tipo de pago',         'icon' => 'bi-cash-coin',       'type' => 'select',       'grupo' => 'Pago', 'col' => 3, 'options' => [
        ['v' => 'total',    'l' => 'Total'],
        ['v' => 'anticipo', 'l' => 'Anticipo'],
    ]],
    ['key' => 'gateway',        'label' => 'Método',               'icon' => 'bi-credit-card',     'type' => 'select',       'grupo' => 'Pago', 'col' => 4, 'options' => [
        ['v' => 'sitio',         'l' => 'En sitio'],
        ['v' => 'efectivo',      'l' => 'Efectivo'],
        ['v' => 'tarjeta',       'l' => 'Tarjeta'],
        ['v' => 'transferencia', 'l' => 'Transferencia'],
        ['v' => 'stripe',        'l' => 'Stripe'],
        ['v' => 'paypal',        'l' => 'PayPal'],
    ]],
    ['key' => 'monto',          'label' => 'Monto',                'icon' => 'bi-currency-dollar', 'type' => 'number_range', 'grupo' => 'Pago', 'col' => 4],
    ['key' => 'usuario',        'label' => 'Usuario que registró', 'icon' => 'bi-person-badge',    'type' => 'select',       'grupo' => 'Pago', 'col' => 4, 'options' => $opcionesUsuario],
    ['key' => 'referencia',     'label' => 'Referencia',           'icon' => 'bi-hash',            'type' => 'text',         'grupo' => 'Pago', 'col' => 12],
    ['key' => 'fecha_cita',     'label' => 'Fecha de la cita',     'icon' => 'bi-calendar3',       'type' => 'date_range',   'grupo' => 'Cita', 'col' => 6],
    ['key' => 'id_tipo_cita',   'label' => 'Tipo de cita',         'icon' => 'bi-tags',            'type' => 'select',       'grupo' => 'Cita', 'col' => 3, 'options' => $opcionesTipo],
    ['key' => 'id_recurso',     'label' => 'Recurso',              'icon' => 'bi-person-gear',     'type' => 'select',       'grupo' => 'Cita', 'col' => 3, 'options' => $opcionesRecurso],
    ['key' => 'cliente',        'label' => 'Cliente',              'icon' => 'bi-person',          'type' => 'text',         'grupo' => 'Cita', 'col' => 4],
    ['key' => 'identificacion', 'label' => 'Identificación',       'icon' => 'bi-card-text',       'type' => 'text',         'grupo' => 'Cita', 'col' => 4],
    ['key' => 'titulo',         'label' => 'Título de la cita',    'icon' => 'bi-tag',             'type' => 'text',         'grupo' => 'Cita', 'col' => 4],
];
?>
<!-- FiltrosModal -->
<link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
<script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig) ?>

<?php // Título + buscador + KPIs en la tarjeta de control fija: sin app-shell, la página hace scroll normal (§9). ?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<style>
    /* Con tarjeta de control fija la tabla no lleva scroll vertical interno: scrollea la página. */
    .pagos-scroll { overflow-x: auto; }
    .pagos-scroll thead th { background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
    @media (max-width: 767.98px) {
        #modulo-citas-pagos .pagos-scroll { max-height: none !important; height: auto !important; overflow-y: visible !important; }
    }
    .pago-row { cursor: pointer; }
    .pago-row:hover { background-color: rgba(0,0,0,.03); }
    .cmg-resizer {
        position: absolute; right: 0; top: 0; height: 100%;
        width: 5px; cursor: col-resize; user-select: none;
    }
    .cmg-resizer:hover, .cmg-resizer.resizing { background: #0d6efd44; }
    thead th { position: relative; }
</style>

<script>
const URL_PAGOS = '<?= $urlBase ?>';
</script>

<div class="container-fluid pt-3 pb-3 px-3" id="modulo-citas-pagos">

<!-- ─── TARJETA DE CONTROL FIJA (título + buscador + resumen) ────────────────── -->
<div class="card cmg-control-card border-0 shadow-sm rounded-3 mb-3">
    <div class="card-header bg-white border-bottom py-2 px-3">
        <h5 class="mb-0 fw-bold"><i class="bi bi-cash-coin me-2 text-primary"></i>Pagos de Citas</h5>
    </div>

    <div class="card-body p-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">

            <div class="d-flex align-items-center gap-2 flex-wrap">
                <!-- Buscador (FiltrosModal) -->
                <div id="fmBuscadorPAGOS"></div>
                <input type="hidden" id="buscarPagosHidden" value="">

                <?php // FiltrosModal (extraId) mueve estos botones dentro del input-group del buscador; si el JS no corre, quedan aquí. ?>
                <div id="fmExtraPAGOS" class="btn-group btn-group-sm">
                    <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($cols, $vistaConfig, $rutaModulo) ?>
                    <a id="btnExportPdf" href="#" target="_blank" class="btn btn-outline-danger" title="Exportar PDF">
                        <i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span>
                    </a>
                    <a id="btnExportExcel" href="#" target="_blank" class="btn btn-outline-success" title="Exportar Excel">
                        <i class="bi bi-file-earmark-spreadsheet"></i><span class="d-none d-md-inline"> Excel</span>
                    </a>
                </div>

                <?php if ($perm['crear']): ?>
                    <button class="btn btn-success btn-sm" onclick="abrirModalPago()">
                        <i class="bi bi-plus-circle me-1"></i>Nuevo pago
                    </button>
                <?php endif; ?>
            </div>

            <!-- Paginación (el selector de filas por página se agrega solo junto a las flechas) -->
            <div class="d-flex align-items-center gap-3">
                <span id="infoPagos" class="text-muted small fw-medium"></span>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" id="btnPrevPagos" disabled
                            onclick="cargarPagos(_pagPage - 1)">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="btnNextPagos" disabled
                            onclick="cargarPagos(_pagPage + 1)">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card-footer bg-white border-top py-2 px-3">
        <div class="cmg-control-card__stats">
            <div class="cmg-control-card__stat">
                <i class="bi bi-check-circle bg-success bg-opacity-10 text-success"></i>
                <div>
                    <div class="cmg-control-card__stat-value text-success">$<?= number_format((float)($resumen['total_cobrado'] ?? 0), 2) ?></div>
                    <div class="cmg-control-card__stat-label">Cobrado · <?= (int)($resumen['completados'] ?? 0) ?> pagos</div>
                </div>
            </div>
            <div class="cmg-control-card__stat">
                <i class="bi bi-hourglass-split bg-warning bg-opacity-10 text-warning"></i>
                <div>
                    <div class="cmg-control-card__stat-value text-warning">$<?= number_format((float)($resumen['total_pendiente'] ?? 0), 2) ?></div>
                    <div class="cmg-control-card__stat-label">Pendiente · <?= (int)($resumen['pendientes'] ?? 0) ?> pagos</div>
                </div>
            </div>
            <div class="cmg-control-card__stat">
                <i class="bi bi-arrow-counterclockwise bg-danger bg-opacity-10 text-danger"></i>
                <div>
                    <div class="cmg-control-card__stat-value text-danger">$<?= number_format((float)($resumen['total_reembolsado'] ?? 0), 2) ?></div>
                    <div class="cmg-control-card__stat-label">Reembolsado</div>
                </div>
            </div>
            <div class="cmg-control-card__stat">
                <i class="bi bi-x-octagon bg-secondary bg-opacity-10 text-secondary"></i>
                <div>
                    <div class="cmg-control-card__stat-value text-secondary"><?= (int)($resumen['fallidos'] ?? 0) ?></div>
                    <div class="cmg-control-card__stat-label">Fallidos</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ─── TABLA ────────────────────────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="pagos-scroll">
            <table class="table table-hover table-sm mb-0" id="tablaPagos"
                   style="table-layout:fixed;min-width:900px;">
                <thead class="table-light">
                    <tr id="trCabecera">
                        <th class="py-2 ps-3" data-col="fecha_reg" style="width:145px;"
                            onclick="sortPagos('created_at')">
                            Fecha reg. <i class="bi bi-arrow-down-up small text-muted ms-1" id="si-created_at"></i>
                            <span class="cmg-resizer" data-col="fecha_reg"></span>
                        </th>
                        <th class="py-2" data-col="fecha_cita" style="width:140px;"
                            onclick="sortPagos('fecha_cita')">
                            Fecha cita <i class="bi bi-arrow-down-up small text-muted ms-1" id="si-fecha_cita"></i>
                            <span class="cmg-resizer" data-col="fecha_cita"></span>
                        </th>
                        <th class="py-2" data-col="cliente"
                            onclick="sortPagos('nombre_cliente')">
                            Cliente <i class="bi bi-arrow-down-up small text-muted ms-1" id="si-nombre_cliente"></i>
                            <span class="cmg-resizer" data-col="cliente"></span>
                        </th>
                        <th class="py-2" data-col="tipo_cita" style="width:130px;"
                            onclick="sortPagos('nombre_tipo')">
                            Tipo cita <i class="bi bi-arrow-down-up small text-muted ms-1" id="si-nombre_tipo"></i>
                            <span class="cmg-resizer" data-col="tipo_cita"></span>
                        </th>
                        <th class="py-2 text-center" data-col="tipo_pago" style="width:110px;"
                            onclick="sortPagos('tipo_pago')">
                            Tipo pago <i class="bi bi-arrow-down-up small text-muted ms-1" id="si-tipo_pago"></i>
                            <span class="cmg-resizer" data-col="tipo_pago"></span>
                        </th>
                        <th class="py-2 text-center" data-col="gateway" style="width:120px;"
                            onclick="sortPagos('gateway')">
                            Método <i class="bi bi-arrow-down-up small text-muted ms-1" id="si-gateway"></i>
                            <span class="cmg-resizer" data-col="gateway"></span>
                        </th>
                        <th class="py-2" data-col="referencia" style="width:150px;">
                            Referencia
                            <span class="cmg-resizer" data-col="referencia"></span>
                        </th>
                        <th class="py-2 text-end" data-col="monto" style="width:95px;"
                            onclick="sortPagos('monto')">
                            Monto <i class="bi bi-arrow-down-up small text-muted ms-1" id="si-monto"></i>
                            <span class="cmg-resizer" data-col="monto"></span>
                        </th>
                        <th class="py-2 text-center pe-3" data-col="estado" style="width:110px;"
                            onclick="sortPagos('estado')">
                            Estado <i class="bi bi-arrow-down-up small text-muted ms-1" id="si-estado"></i>
                            <span class="cmg-resizer" data-col="estado"></span>
                        </th>
                    </tr>
                </thead>
                <tbody id="tbodyPagos">
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <span class="spinner-border spinner-border-sm me-2"></span> Cargando...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div><!-- /#modulo-citas-pagos -->

<script>
// ─── Estado de la tabla ────────────────────────────────────────────────────────
let _pagSort = { col: 'created_at', dir: 'DESC' };
let _pagPage = 1;
let _pagTotalPags = 1;
const PAG_POR_PAGINA = <?= (int) ($perPage ?? 25) ?>;   // preferencia del usuario (BaseModuloController::porPagina)

// ─── Cargar datos ─────────────────────────────────────────────────────────────
// Devuelve la promesa: FiltrosModal apaga su indicador de carga cuando termina.
function cargarPagos(page) {
    if (page !== undefined && (page < 1 || page > _pagTotalPags)) return;
    _pagPage = page ?? _pagPage;
    const perPage = PAG_POR_PAGINA;
    const q       = document.getElementById('buscarPagosHidden')?.value || '';

    const params = new URLSearchParams({
        q,
        page:     _pagPage,
        per_page: perPage,
        sort:     _pagSort.col,
        dir:      _pagSort.dir,
    });

    // Mismo indicador que el buscador: la tabla se atenúa en vez de vaciarse. Se
    // aplica aquí también para paginar y ordenar, que llaman a esta función directo.
    const tbody = document.getElementById('tbodyPagos');
    if (tbody) tbody.classList.add('fm-cargando-target');

    return fetch(`${URL_PAGOS}/search-ajax?${params}`)
        .then(r => r.json())
        .then(res => {
            if (!res.ok) { console.error(res); return; }
            renderPagos(res.data);
            document.getElementById('infoPagos').textContent = res.info ?? '';
            renderPaginacion(res.total, res.per_page ?? perPage, _pagPage);
            if (res.pdf_url)   document.getElementById('btnExportPdf').href   = res.pdf_url;
            if (res.excel_url) document.getElementById('btnExportExcel').href = res.excel_url;
        })
        .catch(e => {
            document.getElementById('tbodyPagos').innerHTML =
                `<tr><td colspan="9" class="text-center py-4 text-danger">Error al cargar datos.</td></tr>`;
        })
        .finally(() => { if (tbody) tbody.classList.remove('fm-cargando-target'); });
}

// ─── Renderizar filas ─────────────────────────────────────────────────────────
function renderPagos(rows) {
    const tbody = document.getElementById('tbodyPagos');
    if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center py-5 text-muted">
            <i class="bi bi-cash-coin fs-2 d-block mb-2"></i>No hay pagos registrados.</td></tr>`;
        return;
    }

    const badgeEstado = {
        pendiente:   'bg-warning  bg-opacity-10 text-warning  border-warning',
        completado:  'bg-success  bg-opacity-10 text-success  border-success',
        fallido:     'bg-danger   bg-opacity-10 text-danger   border-danger',
        reembolsado: 'bg-info     bg-opacity-10 text-info     border-info',
    };
    const labelEstado  = { pendiente:'Pendiente', completado:'Completado', fallido:'Fallido', reembolsado:'Reembolsado' };
    const labelGateway = { stripe:'Stripe', paypal:'PayPal', transferencia:'Transferencia', sitio:'En sitio', efectivo:'Efectivo', tarjeta:'Tarjeta' };
    const labelTipo    = { total:'Total', anticipo:'Anticipo' };
    const iconGateway  = { stripe:'bi-lightning-charge', paypal:'bi-paypal', transferencia:'bi-bank', sitio:'bi-building', efectivo:'bi-cash', tarjeta:'bi-credit-card' };

    tbody.innerHTML = rows.map(r => {
        const fechaReg  = r.created_at ? fmt(r.created_at) : '—';
        const fechaCita = r.fecha_cita  ? fmt(r.fecha_cita) : '—';
        const cliente   = escHtml(r.nombre_cliente || r.cita_titulo || '—');
        const tipoCita  = r.nombre_tipo ? `<span class="badge bg-light text-dark border small" style="max-width:120px;overflow:hidden;text-overflow:ellipsis;">${escHtml(r.nombre_tipo)}</span>` : '—';
        const tipoPago  = labelTipo[r.tipo_pago]    ?? r.tipo_pago;
        const gtw       = r.gateway;
        const gtwLabel  = labelGateway[gtw] ?? gtw;
        const gtwIcon   = iconGateway[gtw]  ?? 'bi-cash';
        const ref       = escHtml(r.referencia_externa || '—');
        const monto     = '$' + parseFloat(r.monto).toFixed(2);
        const est       = r.estado;
        const estClass  = badgeEstado[est] ?? 'bg-secondary bg-opacity-10 text-secondary border-secondary';
        const estLabel  = labelEstado[est] ?? est;

        const dataJson  = JSON.stringify(r).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        return `<tr class="pago-row" onclick='abrirDesdeRow(${dataJson})'>
            <td class="ps-3 small" data-col="fecha_reg" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${fechaReg}</td>
            <td class="small" data-col="fecha_cita" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${fechaCita}</td>
            <td data-col="cliente" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${cliente}</td>
            <td data-col="tipo_cita" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${tipoCita}</td>
            <td class="text-center small" data-col="tipo_pago">${tipoPago}</td>
            <td class="text-center small" data-col="gateway">
                <i class="bi ${gtwIcon} me-1"></i>${gtwLabel}
            </td>
            <td class="small text-muted" data-col="referencia" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${ref}</td>
            <td class="text-end font-monospace fw-medium" data-col="monto">${monto}</td>
            <td class="text-center pe-3" data-col="estado">
                <span class="badge border ${estClass}">${estLabel}</span>
            </td>
        </tr>`;
    }).join('');
}

function abrirDesdeRow(data) {
    <?php if ($perm['actualizar'] || $perm['eliminar']): ?>
    abrirModalPago(data);
    <?php else: ?>
    // Solo lectura
    <?php endif; ?>
}

// ─── Paginación ──────────────────────────────────────────────────────────────
function renderPaginacion(total, perPage, page) {
    _pagTotalPags = Math.max(1, Math.ceil((total || 0) / perPage));
    document.getElementById('btnPrevPagos').disabled = page <= 1;
    document.getElementById('btnNextPagos').disabled = page >= _pagTotalPags;
}

// ─── Ordenamiento ─────────────────────────────────────────────────────────────
function sortPagos(col) {
    if (_pagSort.col === col) {
        _pagSort.dir = _pagSort.dir === 'ASC' ? 'DESC' : 'ASC';
    } else {
        _pagSort.col = col;
        _pagSort.dir = 'ASC';
    }
    // Actualizar íconos
    document.querySelectorAll('[id^="si-"]').forEach(ic => {
        ic.className = 'bi bi-arrow-down-up small text-muted ms-1';
    });
    const icon = document.getElementById('si-' + col);
    if (icon) {
        icon.className = _pagSort.dir === 'ASC'
            ? 'bi bi-sort-alpha-down text-primary ms-1'
            : 'bi bi-sort-alpha-up text-primary ms-1';
    }
    // Persistir ordenamiento
    if (typeof window.guardarOrdenacionVista === 'function') {
        window.guardarOrdenacionVista('citas-pagos', col, _pagSort.dir);
    }
    cargarPagos(1);
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function fmt(dt) {
    if (!dt) return '—';
    const d = new Date(dt);
    if (isNaN(d)) return dt;
    const pad = n => String(n).padStart(2, '0');
    return `${pad(d.getDate())}-${pad(d.getMonth()+1)}-${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function escHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ─── INIT ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {

    // Buscador (FiltrosModal)
    if (window.FiltrosModal && document.getElementById('fmBuscadorPAGOS')) {
        new FiltrosModal({
            containerId:   'fmBuscadorPAGOS',
            hiddenInputId: 'buscarPagosHidden',
            placeholder:   'Buscar en todas las columnas...',
            titulo:        'Filtros de pagos de citas',
            inputWidth:    420,
            extraId:       'fmExtraPAGOS',   // columnas + PDF + Excel, pegados al final del grupo
            fields: <?= json_encode($filtrosPagos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
            loadingTarget: '#tbodyPagos',    // se atenúa mientras se busca
            onApply: () => cargarPagos(1),
        }).init();
    }

    // Columnas resizables
    if (typeof initResizableColumns === 'function') {
        initResizableColumns('#tablaPagos', '<?= $rutaModulo ?>');
    }

    // Restaurar ordenamiento guardado
    <?php
    $savedSort = $vistaConfig['__ordenCol__'] ?? 'created_at';
    $savedDir  = strtoupper($vistaConfig['__ordenDir__'] ?? 'DESC');
    ?>
    const savedCol = '<?= htmlspecialchars($savedSort) ?>';
    const savedDir = '<?= $savedDir ?>';
    if (savedCol) {
        _pagSort = { col: savedCol, dir: savedDir };
        const icon = document.getElementById('si-' + savedCol);
        if (icon) {
            icon.className = savedDir === 'ASC'
                ? 'bi bi-sort-alpha-down text-primary ms-1'
                : 'bi bi-sort-alpha-up text-primary ms-1';
        }
    }

    // Carga inicial
    cargarPagos(1);
});
</script>

<?php include __DIR__ . '/_modal_pago.php'; ?>
