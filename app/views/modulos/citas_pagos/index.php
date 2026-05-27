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

<!-- FiltrosBusqueda -->
<link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_busqueda.css?v=<?= time() ?>">
<script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_busqueda.js?v=<?= time() ?>"></script>

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig) ?>

<style>
    .pagos-table-wrap { max-height: calc(100vh - 310px); overflow-y: auto; }
    .pagos-table-wrap thead th {
        position: sticky; top: 0; z-index: 1;
        background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6;
    }
    .pago-row { cursor: pointer; }
    .pago-row:hover { background-color: rgba(0,0,0,.03); }
    .cmg-resizer {
        position: absolute; right: 0; top: 0; height: 100%;
        width: 5px; cursor: col-resize; user-select: none;
    }
    .cmg-resizer:hover, .cmg-resizer.resizing { background: #0d6efd44; }
    thead th { position: relative; }
    .stat-card { border-radius: .5rem; padding: .75rem 1.25rem; }
</style>

<script>
const URL_PAGOS = '<?= $urlBase ?>';
</script>

<!-- ─── ENCABEZADO ──────────────────────────────────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="mb-0 fw-bold"><i class="bi bi-cash-coin text-primary me-2"></i>Pagos de Citas</h4>
        <small class="text-muted">Registro y seguimiento de pagos vinculados a citas</small>
    </div>
    <div class="d-flex align-items-center gap-2">
        <?php if ($perm['crear']): ?>
            <button class="btn btn-success btn-sm" onclick="abrirModalPago()">
                <i class="bi bi-plus-circle me-1"></i>Nuevo pago
            </button>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/modulos/citas-agenda" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-calendar3 me-1"></i>Agenda
        </a>
    </div>
</div>

<!-- ─── TARJETAS RESUMEN ─────────────────────────────────────────────────────── -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="stat-card bg-success bg-opacity-10 border border-success border-opacity-25">
            <div class="small text-muted mb-1"><i class="bi bi-check-circle me-1 text-success"></i>Total cobrado</div>
            <div class="fs-5 fw-bold text-success">$<?= number_format((float)($resumen['total_cobrado'] ?? 0), 2) ?></div>
            <div class="small text-muted"><?= (int)($resumen['completados'] ?? 0) ?> pagos</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card bg-warning bg-opacity-10 border border-warning border-opacity-25">
            <div class="small text-muted mb-1"><i class="bi bi-hourglass-split me-1 text-warning"></i>Pendiente</div>
            <div class="fs-5 fw-bold text-warning">$<?= number_format((float)($resumen['total_pendiente'] ?? 0), 2) ?></div>
            <div class="small text-muted"><?= (int)($resumen['pendientes'] ?? 0) ?> pagos</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card bg-danger bg-opacity-10 border border-danger border-opacity-25">
            <div class="small text-muted mb-1"><i class="bi bi-arrow-counterclockwise me-1 text-danger"></i>Reembolsado</div>
            <div class="fs-5 fw-bold text-danger">$<?= number_format((float)($resumen['total_reembolsado'] ?? 0), 2) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card bg-secondary bg-opacity-10 border border-secondary border-opacity-25">
            <div class="small text-muted mb-1"><i class="bi bi-x-octagon me-1 text-secondary"></i>Fallidos</div>
            <div class="fs-5 fw-bold text-secondary"><?= (int)($resumen['fallidos'] ?? 0) ?></div>
            <div class="small text-muted">registros</div>
        </div>
    </div>
</div>

<!-- ─── BARRA DE HERRAMIENTAS ────────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-2">
    <div class="card-body py-2 px-3">
        <div class="d-flex align-items-center gap-2 flex-wrap">

            <!-- FiltrosBusqueda -->
            <div id="fbBuscadorPagos" style="min-width:320px;flex:1;max-width:520px;"></div>
            <input type="hidden" id="buscarPagosHidden" value="">

            <!-- Columnas -->
            <div class="btn-group btn-group-sm">
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($cols, $vistaConfig, $rutaModulo) ?>
            </div>

            <!-- Paginación por página -->
            <select id="perPageSel" class="form-select form-select-sm" style="width:auto;" onchange="cargarPagos(1)">
                <option value="15">15</option>
                <option value="25" selected>25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>

            <!-- Export -->
            <div class="btn-group btn-group-sm ms-auto">
                <a id="btnExportPdf" href="#" target="_blank" class="btn btn-outline-danger btn-sm" title="Exportar PDF">
                    <i class="bi bi-file-pdf me-1"></i>PDF
                </a>
                <a id="btnExportExcel" href="#" target="_blank" class="btn btn-outline-success btn-sm" title="Exportar Excel">
                    <i class="bi bi-file-earmark-excel me-1"></i>Excel
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ─── TABLA ────────────────────────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="pagos-table-wrap">
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

        <!-- Paginación -->
        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-top flex-wrap gap-2">
            <small class="text-muted" id="infoPagos">—</small>
            <nav id="navPagos"></nav>
        </div>
    </div>
</div>

<script>
// ─── Estado de la tabla ────────────────────────────────────────────────────────
let _pagSort = { col: 'created_at', dir: 'DESC' };
let _pagPage = 1;

// ─── Cargar datos ─────────────────────────────────────────────────────────────
function cargarPagos(page) {
    _pagPage = page ?? _pagPage;
    const perPage = parseInt(document.getElementById('perPageSel').value) || 25;
    const q       = document.getElementById('buscarPagosHidden')?.value || '';

    const params = new URLSearchParams({
        q,
        page:     _pagPage,
        per_page: perPage,
        sort:     _pagSort.col,
        dir:      _pagSort.dir,
    });

    document.getElementById('tbodyPagos').innerHTML =
        `<tr><td colspan="9" class="text-center py-5 text-muted">
            <span class="spinner-border spinner-border-sm me-2"></span> Cargando...
         </td></tr>`;

    fetch(`${URL_PAGOS}/search-ajax?${params}`)
        .then(r => r.json())
        .then(res => {
            if (!res.ok) { console.error(res); return; }
            renderPagos(res.data);
            document.getElementById('infoPagos').textContent = `${res.info} registros`;
            renderPaginacion(res.total, perPage, _pagPage);
            if (res.pdf_url)   document.getElementById('btnExportPdf').href   = res.pdf_url;
            if (res.excel_url) document.getElementById('btnExportExcel').href = res.excel_url;
        })
        .catch(e => {
            document.getElementById('tbodyPagos').innerHTML =
                `<tr><td colspan="9" class="text-center py-4 text-danger">Error al cargar datos.</td></tr>`;
        });
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
    const totalPages = Math.ceil(total / perPage) || 1;
    const nav = document.getElementById('navPagos');
    if (totalPages <= 1) { nav.innerHTML = ''; return; }

    let html = '<ul class="pagination pagination-sm mb-0">';
    html += `<li class="page-item ${page <= 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="cargarPagos(${page - 1});return false;">&laquo;</a></li>`;

    const delta = 2;
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= page - delta && i <= page + delta)) {
            html += `<li class="page-item ${i === page ? 'active' : ''}">
                <a class="page-link" href="#" onclick="cargarPagos(${i});return false;">${i}</a></li>`;
        } else if (i === page - delta - 1 || i === page + delta + 1) {
            html += '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
    }

    html += `<li class="page-item ${page >= totalPages ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="cargarPagos(${page + 1});return false;">&raquo;</a></li>`;
    html += '</ul>';
    nav.innerHTML = html;
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

    // FiltrosBusqueda
    if (window.FiltrosBusqueda && document.getElementById('fbBuscadorPagos')) {
        new FiltrosBusqueda({
            containerId:   'fbBuscadorPagos',
            hiddenInputId: 'buscarPagosHidden',
            placeholder:   'Buscar por cliente, tipo de cita, referencia...',
            fields: [
                { key: 'cliente',    label: 'Cliente',    icon: 'bi-person',       type: 'text'   },
                { key: 'referencia', label: 'Referencia', icon: 'bi-hash',         type: 'text'   },
                { key: 'tipo_cita',  label: 'Tipo cita',  icon: 'bi-tags',         type: 'text'   },
                { key: 'estado',     label: 'Estado',     icon: 'bi-flag',         type: 'select', options: [
                    { v: 'pendiente',   l: 'Pendiente'   },
                    { v: 'completado',  l: 'Completado'  },
                    { v: 'fallido',     l: 'Fallido'     },
                    { v: 'reembolsado', l: 'Reembolsado' },
                ]},
                { key: 'gateway',    label: 'Método',     icon: 'bi-credit-card',  type: 'select', options: [
                    { v: 'sitio',         l: 'En sitio'      },
                    { v: 'efectivo',      l: 'Efectivo'      },
                    { v: 'tarjeta',       l: 'Tarjeta'       },
                    { v: 'transferencia', l: 'Transferencia' },
                    { v: 'stripe',        l: 'Stripe'        },
                    { v: 'paypal',        l: 'PayPal'        },
                ]},
                { key: 'tipo_pago',  label: 'Tipo pago',  icon: 'bi-cash-coin',    type: 'select', options: [
                    { v: 'total',    l: 'Total'    },
                    { v: 'anticipo', l: 'Anticipo' },
                ]},
                { key: 'monto',      label: 'Monto',      icon: 'bi-currency-dollar', type: 'numeric' },
                { key: 'fecha',      label: 'Fecha reg.',  icon: 'bi-calendar',    type: 'date'   },
                { key: 'fecha_cita', label: 'Fecha cita', icon: 'bi-calendar3',    type: 'date'   },
            ],
            quickFilters: [
                { id: 'qf_pendiente',  label: 'Pendientes',   mk: () => ({ key: 'estado', op: '=', value: 'pendiente',   display: 'Pendiente'   }) },
                { id: 'qf_completado', label: 'Completados',  mk: () => ({ key: 'estado', op: '=', value: 'completado',  display: 'Completado'  }) },
                { id: 'qf_hoy',        label: 'Hoy',          mk: () => FiltrosBusqueda.helpers.hoyMismo('fecha') },
                { id: 'qf_mes',        label: 'Este mes',     mk: () => FiltrosBusqueda.helpers.esteMes('fecha')  },
                { id: 'qf_anticipo',   label: 'Anticipos',    mk: () => ({ key: 'tipo_pago', op: '=', value: 'anticipo', display: 'Anticipo' }) },
            ],
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
