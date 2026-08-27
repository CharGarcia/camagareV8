<?php
/** @var string $titulo */
/** @var array  $perm */
$base = rtrim(BASE_URL ?? '', '/');
$anioActual = (int) date('Y');
$mesActual  = (int) date('n');
?>

<style>
.db-metric-card {
    background:#fff; border-radius:10px; padding:1.1rem 1.25rem;
    box-shadow:0 2px 8px rgba(0,0,0,.06); border:1px solid rgba(0,0,0,.05);
    transition:transform .15s,box-shadow .15s;
}
.db-metric-card:hover { transform:translateY(-2px); box-shadow:0 6px 18px rgba(0,0,0,.09); }
.db-metric-icon { width:44px;height:44px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:1.2rem; }
.db-metric-value { font-size:1.6rem;font-weight:700;color:#111827;line-height:1.2; }
.db-metric-label { font-size:.8rem;color:#6b7280;font-weight:500; }
.db-metric-change { font-size:.73rem;font-weight:600; }
.ch-up{color:#059669} .ch-dn{color:#dc2626} .ch-neu{color:#6b7280}
.db-panel { background:#fff;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.06);border:1px solid rgba(0,0,0,.05);overflow:hidden; }
.db-panel-header { padding:.9rem 1.25rem;border-bottom:1px solid #f3f4f6;background:#fafafa;display:flex;align-items:center;justify-content:space-between; }
.db-panel-title { font-size:.92rem;font-weight:600;color:#374151;margin:0; }
.db-tbl { width:100%;border-collapse:collapse; }
.db-tbl th { padding:.55rem 1rem;font-size:.72rem;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.04em;background:#f9fafb;border-bottom:1px solid #e5e7eb; }
.db-tbl td { padding:.65rem 1rem;font-size:.82rem;color:#111827;border-bottom:1px solid #f3f4f6;vertical-align:middle; }
.db-tbl tbody tr:last-child td { border-bottom:none; }
.db-tbl tbody tr:hover { background:#f9fafb; }
.bsoft { padding:.2em .55em;border-radius:.3rem;font-size:.72rem;font-weight:500; }
.bs-success{background:#d1fae5;color:#065f46} .bs-warning{background:#fef3c7;color:#92400e}
.bs-danger{background:#fee2e2;color:#991b1b} .bs-info{background:#dbeafe;color:#1e40af}
.bs-secondary{background:#f3f4f6;color:#374151}
.sk { background:linear-gradient(90deg,#f3f4f6 25%,#e5e7eb 50%,#f3f4f6 75%);background-size:200% 100%;animation:sk 1.4s infinite;border-radius:.3rem; }
@keyframes sk{0%{background-position:200% 0}100%{background-position:-200% 0}}
.text-tr { color:transparent!important; }
.db-filters { background:#fff;border-radius:10px;padding:.75rem 1.25rem;box-shadow:0 2px 8px rgba(0,0,0,.06);border:1px solid rgba(0,0,0,.05); }

/* ── Filtros fijos (pegados bajo el navbar al hacer scroll) ── */
.db-filters { position:sticky; top:var(--cmg-sticky-h,80px); z-index:30; }

/* ── Tarjetas reubicables y redimensionables (grilla de 12 columnas) ── */
.db-zone {
    display:grid;
    grid-template-columns:repeat(12, minmax(0,1fr));
    gap:1rem;
    align-items:stretch;
}
.db-item { position:relative; grid-column:span 4; min-width:0; }
.db-item > .db-metric-card, .db-item > .db-panel { height:100%; }

/* Agarre para mover */
.db-drag-handle {
    position:absolute; top:-7px; left:-7px; z-index:14;
    width:22px; height:22px; border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    background:#fff; color:#6b7280; border:1px solid #e5e7eb;
    box-shadow:0 1px 4px rgba(0,0,0,.12);
    font-size:.72rem; cursor:grab; touch-action:none;
    opacity:0; transition:opacity .15s; padding:0;
}
/* Agarre para cambiar el ancho */
.db-resize-handle {
    position:absolute; top:8px; bottom:8px; right:-9px; width:14px; z-index:14;
    cursor:col-resize; touch-action:none;
    display:flex; align-items:center; justify-content:center;
    opacity:0; transition:opacity .15s;
}
.db-resize-handle::before {
    content:''; width:4px; height:38px; border-radius:3px;
    background:#cbd5e1; box-shadow:0 1px 3px rgba(0,0,0,.15);
}
.db-resize-handle:hover::before { background:#3b82f6; }
.db-item:hover > .db-drag-handle,
.db-item:hover > .db-resize-handle,
.db-drag-handle:focus { opacity:1; }
.db-drag-handle:active { cursor:grabbing; }
@media (hover:none) { .db-drag-handle { opacity:.55; } }

/* Estados durante el arrastre / redimensionado */
.db-item.db-dragging { opacity:.5; }
.db-item.db-dragging > .db-metric-card,
.db-item.db-dragging > .db-panel,
.db-item.db-resizing > .db-metric-card,
.db-item.db-resizing > .db-panel { outline:2px dashed #3b82f6; outline-offset:2px; }
.db-zone.db-zone-busy .db-metric-card:hover { transform:none; box-shadow:0 2px 8px rgba(0,0,0,.06); }
.db-ancho-badge {
    position:absolute; top:6px; right:10px; z-index:15;
    background:#111827; color:#fff; border-radius:.3rem;
    padding:.1rem .4rem; font-size:.7rem; font-weight:600; pointer-events:none;
}
/* El valor de las tarjetas de indicador se adapta al ancho elegido */
.db-metric-value { font-size:clamp(1.05rem, 1.35vw, 1.6rem); }

/* En pantallas chicas la grilla se simplifica y el ancho manual no aplica */
@media (max-width:991.98px) {
    .db-zone { grid-template-columns:repeat(2, minmax(0,1fr)); }
    .db-zone > .db-item { grid-column:span 2 !important; }
    .db-zone > .db-item--metrica { grid-column:span 1 !important; }
    .db-resize-handle { display:none; }
}
</style>

<div class="pb-4">

<!-- Header + badge ambiente -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Dashboard
            <span id="badgeAmbiente" class="badge rounded-pill align-middle ms-2 d-none" style="font-size:.6em;"></span>
        </h4>
        <p id="lblPeriodo" class="text-muted small mb-0">Cargando...</p>
        <p class="text-muted mb-0" style="font-size:.72rem">
            <i class="bi bi-arrows-move me-1"></i>Las tarjetas se pueden reubicar: arrástralas desde el
            <i class="bi bi-grip-vertical"></i> de su esquina superior izquierda, y cambia su ancho arrastrando
            la barrita de su borde derecho. Todo se guarda para tu usuario.
            <a href="#" class="ms-1" onclick="restablecerOrden();return false;">Restablecer tablero</a>
        </p>
    </div>
    <button class="btn btn-sm btn-primary" onclick="applyFilters()">
        <i class="bi bi-arrow-clockwise me-1"></i> Actualizar
    </button>
</div>

<!-- ── Filtros ── -->
<div class="db-filters mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-auto">
            <label class="form-label small fw-semibold mb-1">Filtro</label>
            <select id="fModo" class="form-select form-select-sm" style="width:150px">
                <option value="periodo" selected>Por período</option>
                <option value="rango">Rango de fechas</option>
            </select>
        </div>
        <!-- Grupo: por período -->
        <div class="col-auto grp-periodo">
            <label class="form-label small fw-semibold mb-1">Año</label>
            <select id="fAnio" class="form-select form-select-sm" style="width:100px">
                <?php for ($y = $anioActual; $y >= $anioActual - 5; $y--): ?>
                <option value="<?= $y ?>" <?= $y === $anioActual ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-auto grp-periodo">
            <label class="form-label small fw-semibold mb-1">Mes</label>
            <select id="fMes" class="form-select form-select-sm" style="width:140px">
                <option value="-1">Todo el año</option>
                <?php
                $meses=['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
                        'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
                for ($m=1;$m<=12;$m++): ?>
                <option value="<?= $m ?>" <?= $m === $mesActual ? 'selected' : '' ?>><?= $meses[$m] ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <!-- Grupo: rango de fechas -->
        <div class="col-auto grp-rango d-none">
            <label class="form-label small fw-semibold mb-1">Desde</label>
            <input type="date" id="fDesde" class="form-control form-control-sm" style="width:150px">
        </div>
        <div class="col-auto grp-rango d-none">
            <label class="form-label small fw-semibold mb-1">Hasta</label>
            <input type="date" id="fHasta" class="form-control form-control-sm" style="width:150px">
        </div>
        <div class="col-auto grp-rango d-none">
            <label class="form-label small fw-semibold mb-1 d-block">Rápido</label>
            <div class="btn-group btn-group-sm" role="group">
                <button type="button" class="btn btn-outline-secondary" onclick="quickRange('mes')">Mes</button>
                <button type="button" class="btn btn-outline-secondary" onclick="quickRange('trim')">Trim.</button>
                <button type="button" class="btn btn-outline-secondary" onclick="quickRange('anio')">Año</button>
                <button type="button" class="btn btn-outline-secondary" onclick="quickRange('30d')">30 días</button>
            </div>
        </div>
        <div class="col-auto">
            <label class="form-label small fw-semibold mb-1">Tendencia</label>
            <select id="fMeses" class="form-select form-select-sm" style="width:110px">
                <option value="3">3 meses</option>
                <option value="6" selected>6 meses</option>
                <option value="12">12 meses</option>
                <option value="24">24 meses</option>
            </select>
        </div>
        <div class="col-auto">
            <label class="form-label small fw-semibold mb-1">Tipo gráfico</label>
            <select id="fTipoChart" class="form-select form-select-sm" style="width:150px">
                <option value="bar">Barras</option>
                <option value="stacked">Barras apiladas</option>
                <option value="line">Líneas</option>
                <option value="area">Área</option>
                <option value="radar">Radar</option>
            </select>
        </div>
        <div class="col-auto ms-auto d-flex gap-2 align-items-end">
            <button class="btn btn-sm btn-outline-secondary" onclick="resetFilters()">
                <i class="bi bi-x-circle me-1"></i>Limpiar
            </button>
            <button class="btn btn-sm btn-success" onclick="applyFilters()">
                <i class="bi bi-funnel me-1"></i>Aplicar
            </button>
        </div>
    </div>
</div>

<!-- ── Tarjetas y paneles: una sola grilla libre (12 columnas).
     El usuario los mueve y les cambia el ancho; ambas cosas se guardan
     en sus preferencias (clave `layout` de __vista__).                  ── -->
<div id="zonaDashboard" class="db-zone">

    <?php
    // Indicadores. `w` = ancho por defecto en columnas de 12.
    $metricas = [
        ['k'=>'ventas',   'w'=>2, 'lbl'=>'Ventas',         'val'=>'mValVentas',   'chg'=>'mChgVentas',   'ico'=>'bi-receipt',        'color'=>'primary',   'ini'=>'$0.00'],
        ['k'=>'compras',  'w'=>2, 'lbl'=>'Compras',        'val'=>'mValCompras',  'chg'=>'mChgCompras',  'ico'=>'bi-cart3',          'color'=>'danger',    'ini'=>'$0.00'],
        ['k'=>'nomina',   'w'=>2, 'lbl'=>'Nómina',         'val'=>'mValNomina',   'chg'=>'mChgNomina',   'ico'=>'bi-people',         'color'=>'secondary', 'ini'=>'$0.00'],
        ['k'=>'utilidad', 'w'=>2, 'lbl'=>'Utilidad Bruta', 'val'=>'mValUtilidad', 'chg'=>'mChgUtilidad', 'ico'=>'bi-graph-up-arrow', 'color'=>'success',   'ini'=>'$0.00'],
        ['k'=>'margen',   'w'=>2, 'lbl'=>'Margen',         'val'=>'mValMargen',   'chg'=>'mChgMargen',   'ico'=>'bi-percent',        'color'=>'info',      'ini'=>'0%'],
        ['k'=>'ingresos', 'w'=>2, 'lbl'=>'Ingresos (caja)','val'=>'mValIngresos', 'chg'=>'mChgIngresos', 'ico'=>'bi-cash-coin',      'color'=>'success',   'ini'=>'$0.00'],
        ['k'=>'egresos',  'w'=>4, 'lbl'=>'Egresos (caja)', 'val'=>'mValEgresos',  'chg'=>'mChgEgresos',  'ico'=>'bi-cash-stack',     'color'=>'warning',   'ini'=>'$0.00'],
        ['k'=>'cxc',      'w'=>4, 'lbl'=>'CxC Pendiente',  'val'=>'mValCxc',      'nota'=>'Pendiente de cobro del período', 'ico'=>'bi-person-check', 'color'=>'primary', 'ini'=>'$0.00'],
        ['k'=>'cxp',      'w'=>4, 'lbl'=>'CxP Pendiente',  'val'=>'mValCxp',      'nota'=>'Pendiente de pago del período',  'ico'=>'bi-building',     'color'=>'danger',  'ini'=>'$0.00'],
    ];
    foreach ($metricas as $m): ?>
    <div class="db-item db-item--metrica" data-db-key="<?= $m['k'] ?>" data-db-w="<?= $m['w'] ?>" style="grid-column:span <?= $m['w'] ?>">
        <button type="button" class="db-drag-handle" title="Arrastrar para mover"><i class="bi bi-grip-vertical"></i></button>
        <span class="db-resize-handle" title="Arrastrar para cambiar el ancho"></span>
        <div class="db-metric-card">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <p class="db-metric-label mb-1"><?= $m['lbl'] ?></p>
                    <div class="db-metric-value text-tr sk" id="<?= $m['val'] ?>"><?= $m['ini'] ?></div>
                </div>
                <div class="db-metric-icon bg-<?= $m['color'] ?> bg-opacity-10 text-<?= $m['color'] ?>"><i class="bi <?= $m['ico'] ?>"></i></div>
            </div>
            <?php if (isset($m['chg'])): ?>
            <div class="db-metric-change ch-neu text-tr sk mt-2" id="<?= $m['chg'] ?>">—</div>
            <?php else: ?>
            <div class="db-metric-change ch-neu mt-2 small text-muted"><?= $m['nota'] ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Saldos de Bancos/Efectivo -->
    <div class="db-item db-item--panel" data-db-key="saldos_caja" data-db-w="8" style="grid-column:span 8">
        <button type="button" class="db-drag-handle" title="Arrastrar para mover"><i class="bi bi-grip-vertical"></i></button>
        <span class="db-resize-handle" title="Arrastrar para cambiar el ancho"></span>
        <div class="db-panel">
            <div class="db-panel-header">
                <h6 class="db-panel-title"><i class="bi bi-bank me-2 text-success"></i>Saldos de Bancos, Efectivo, tarjetas y otros</h6>
                <span id="siTotalCaja" class="small fw-bold text-success"></span>
            </div>
            <div style="overflow-y:auto;max-height:240px">
                <table class="db-tbl">
                    <thead><tr><th>Forma de pago</th><th>Tipo</th><th class="text-end">Saldo</th></tr></thead>
                    <tbody id="tSaldosFormas"><tr><td colspan="3"><div class="sk" style="height:16px"></div></td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Anticipos -->
    <div class="db-item db-item--panel" data-db-key="anticipos" data-db-w="4" style="grid-column:span 4">
        <button type="button" class="db-drag-handle" title="Arrastrar para mover"><i class="bi bi-grip-vertical"></i></button>
        <span class="db-resize-handle" title="Arrastrar para cambiar el ancho"></span>
        <div class="db-panel">
            <div class="db-panel-header">
                <h6 class="db-panel-title"><i class="bi bi-wallet2 me-2 text-info"></i>Anticipos</h6>
            </div>
            <div class="p-3">
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span class="db-metric-label"><i class="bi bi-person-check text-primary me-2"></i>De clientes</span>
                    <span class="fw-bold text-tr sk" id="siAntClientes">$0.00</span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2">
                    <span class="db-metric-label"><i class="bi bi-building text-danger me-2"></i>A proveedores</span>
                    <span class="fw-bold text-tr sk" id="siAntProveedores">$0.00</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Comparativo mensual -->
    <div class="db-item db-item--panel" data-db-key="tendencia" data-db-w="8" style="grid-column:span 8">
        <button type="button" class="db-drag-handle" title="Arrastrar para mover"><i class="bi bi-grip-vertical"></i></button>
        <span class="db-resize-handle" title="Arrastrar para cambiar el ancho"></span>
        <div class="db-panel">
            <div class="db-panel-header flex-wrap gap-2">
                <h6 class="db-panel-title"><i class="bi bi-bar-chart-line me-2 text-primary"></i>Comparativo mensual</h6>
                <div id="cmpSeries" class="d-flex flex-wrap gap-2">
                    <?php
                    $series = [
                        ['k'=>'ventas',  'lbl'=>'Ventas',   'on'=>true],
                        ['k'=>'compras', 'lbl'=>'Compras',  'on'=>true],
                        ['k'=>'nomina',  'lbl'=>'Nómina',   'on'=>true],
                        ['k'=>'ingresos','lbl'=>'Ingresos', 'on'=>false],
                        ['k'=>'egresos', 'lbl'=>'Egresos',  'on'=>false],
                        ['k'=>'utilidad','lbl'=>'Utilidad', 'on'=>false],
                    ];
                    foreach ($series as $s): ?>
                    <div class="form-check form-check-inline m-0">
                        <input class="form-check-input cmp-serie" type="checkbox" id="cmp_<?= $s['k'] ?>"
                               value="<?= $s['k'] ?>" <?= $s['on'] ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="cmp_<?= $s['k'] ?>"><?= $s['lbl'] ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="p-3" style="height:280px"><canvas id="chartTendencia"></canvas></div>
        </div>
    </div>

    <!-- Top Productos -->
    <div class="db-item db-item--panel" data-db-key="top_productos" data-db-w="4" style="grid-column:span 4">
        <button type="button" class="db-drag-handle" title="Arrastrar para mover"><i class="bi bi-grip-vertical"></i></button>
        <span class="db-resize-handle" title="Arrastrar para cambiar el ancho"></span>
        <div class="db-panel">
            <div class="db-panel-header">
                <h6 class="db-panel-title"><i class="bi bi-box-seam me-2 text-warning"></i>Top Productos</h6>
            </div>
            <div class="p-3" style="height:280px"><canvas id="chartTopProductos"></canvas></div>
        </div>
    </div>

    <!-- Top Proveedores -->
    <div class="db-item db-item--panel" data-db-key="top_proveedores" data-db-w="8" style="grid-column:span 8">
        <button type="button" class="db-drag-handle" title="Arrastrar para mover"><i class="bi bi-grip-vertical"></i></button>
        <span class="db-resize-handle" title="Arrastrar para cambiar el ancho"></span>
        <div class="db-panel">
            <div class="db-panel-header">
                <h6 class="db-panel-title"><i class="bi bi-truck me-2 text-danger"></i>Top Proveedores (compras)</h6>
            </div>
            <div class="p-3" style="height:260px"><canvas id="chartTopProveedores"></canvas></div>
        </div>
    </div>

    <!-- Egresos por Concepto -->
    <div class="db-item db-item--panel" data-db-key="egresos_concepto" data-db-w="4" style="grid-column:span 4">
        <button type="button" class="db-drag-handle" title="Arrastrar para mover"><i class="bi bi-grip-vertical"></i></button>
        <span class="db-resize-handle" title="Arrastrar para cambiar el ancho"></span>
        <div class="db-panel">
            <div class="db-panel-header">
                <h6 class="db-panel-title"><i class="bi bi-pie-chart me-2 text-warning"></i>Egresos por Concepto</h6>
            </div>
            <div class="p-3" style="height:260px"><canvas id="chartEgresosConcepto"></canvas></div>
        </div>
    </div>

    <!-- Top Clientes -->
    <div class="db-item db-item--panel" data-db-key="top_clientes" data-db-w="4" style="grid-column:span 4">
        <button type="button" class="db-drag-handle" title="Arrastrar para mover"><i class="bi bi-grip-vertical"></i></button>
        <span class="db-resize-handle" title="Arrastrar para cambiar el ancho"></span>
        <div class="db-panel">
            <div class="db-panel-header">
                <h6 class="db-panel-title"><i class="bi bi-people me-2 text-info"></i>Top Clientes</h6>
            </div>
            <div class="p-3" style="height:240px"><canvas id="chartTopClientes"></canvas></div>
        </div>
    </div>

    <!-- CxC Vencidas -->
    <div class="db-item db-item--panel" data-db-key="cxc_vencidas" data-db-w="4" style="grid-column:span 4">
        <button type="button" class="db-drag-handle" title="Arrastrar para mover"><i class="bi bi-grip-vertical"></i></button>
        <span class="db-resize-handle" title="Arrastrar para cambiar el ancho"></span>
        <div class="db-panel">
            <div class="db-panel-header">
                <h6 class="db-panel-title"><i class="bi bi-exclamation-triangle me-2 text-danger"></i>CxC Vencidas</h6>
            </div>
            <div style="overflow-y:auto;max-height:240px">
                <table class="db-tbl">
                    <thead><tr><th>Cliente</th><th class="text-end">Saldo</th><th class="text-end">Días</th></tr></thead>
                    <tbody id="tCxcVencidas"><tr><td colspan="3"><div class="sk" style="height:16px"></div></td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- CxP Vencidas -->
    <div class="db-item db-item--panel" data-db-key="cxp_vencidas" data-db-w="4" style="grid-column:span 4">
        <button type="button" class="db-drag-handle" title="Arrastrar para mover"><i class="bi bi-grip-vertical"></i></button>
        <span class="db-resize-handle" title="Arrastrar para cambiar el ancho"></span>
        <div class="db-panel">
            <div class="db-panel-header">
                <h6 class="db-panel-title"><i class="bi bi-exclamation-triangle me-2 text-warning"></i>CxP Vencidas</h6>
            </div>
            <div style="overflow-y:auto;max-height:240px">
                <table class="db-tbl">
                    <thead><tr><th>Proveedor</th><th class="text-end">Saldo</th><th class="text-end">Días</th></tr></thead>
                    <tbody id="tCxpVencidas"><tr><td colspan="3"><div class="sk" style="height:16px"></div></td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Últimas Ventas -->
    <div class="db-item db-item--panel" data-db-key="ult_ventas" data-db-w="6" style="grid-column:span 6">
        <button type="button" class="db-drag-handle" title="Arrastrar para mover"><i class="bi bi-grip-vertical"></i></button>
        <span class="db-resize-handle" title="Arrastrar para cambiar el ancho"></span>
        <div class="db-panel">
            <div class="db-panel-header">
                <h6 class="db-panel-title"><i class="bi bi-receipt me-2 text-success"></i>Últimas Ventas</h6>
            </div>
            <div style="overflow-y:auto;max-height:260px">
                <table class="db-tbl">
                    <thead><tr><th>Fecha</th><th>Cliente</th><th class="text-end">Total</th><th class="text-center">Estado</th></tr></thead>
                    <tbody id="tVentas"><tr><td colspan="4"><div class="sk" style="height:16px"></div></td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Últimas Compras -->
    <div class="db-item db-item--panel" data-db-key="ult_compras" data-db-w="6" style="grid-column:span 6">
        <button type="button" class="db-drag-handle" title="Arrastrar para mover"><i class="bi bi-grip-vertical"></i></button>
        <span class="db-resize-handle" title="Arrastrar para cambiar el ancho"></span>
        <div class="db-panel">
            <div class="db-panel-header">
                <h6 class="db-panel-title"><i class="bi bi-bag-check me-2 text-danger"></i>Últimas Compras</h6>
            </div>
            <div style="overflow-y:auto;max-height:260px">
                <table class="db-tbl">
                    <thead><tr><th>Fecha</th><th>Proveedor</th><th class="text-end">Total</th><th class="text-center">Estado</th></tr></thead>
                    <tbody id="tCompras"><tr><td colspan="4"><div class="sk" style="height:16px"></div></td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Últimos Ingresos -->
    <div class="db-item db-item--panel" data-db-key="ult_ingresos" data-db-w="6" style="grid-column:span 6">
        <button type="button" class="db-drag-handle" title="Arrastrar para mover"><i class="bi bi-grip-vertical"></i></button>
        <span class="db-resize-handle" title="Arrastrar para cambiar el ancho"></span>
        <div class="db-panel">
            <div class="db-panel-header">
                <h6 class="db-panel-title"><i class="bi bi-cash-coin me-2 text-success"></i>Últimos Ingresos</h6>
            </div>
            <div style="overflow-y:auto;max-height:220px">
                <table class="db-tbl">
                    <thead><tr><th>Fecha</th><th>Descripción</th><th class="text-end">Total</th><th class="text-center">Estado</th></tr></thead>
                    <tbody id="tIngresos"><tr><td colspan="4"><div class="sk" style="height:16px"></div></td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Últimos Egresos -->
    <div class="db-item db-item--panel" data-db-key="ult_egresos" data-db-w="6" style="grid-column:span 6">
        <button type="button" class="db-drag-handle" title="Arrastrar para mover"><i class="bi bi-grip-vertical"></i></button>
        <span class="db-resize-handle" title="Arrastrar para cambiar el ancho"></span>
        <div class="db-panel">
            <div class="db-panel-header">
                <h6 class="db-panel-title"><i class="bi bi-cash-stack me-2 text-warning"></i>Últimos Egresos</h6>
            </div>
            <div style="overflow-y:auto;max-height:220px">
                <table class="db-tbl">
                    <thead><tr><th>Fecha</th><th>Descripción</th><th class="text-end">Total</th><th class="text-center">Estado</th></tr></thead>
                    <tbody id="tEgresos"><tr><td colspan="4"><div class="sk" style="height:16px"></div></td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

</div>


</div><!-- /.pb-4 -->

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const URL_DB  = '<?= $base ?>/modulos/dashboard/dataAjax';
const $ = id => document.getElementById(id);
const fmt  = v => new Intl.NumberFormat('es-EC',{style:'currency',currency:'USD'}).format(v);
const fmtN = v => new Intl.NumberFormat('es-EC',{maximumFractionDigits:1}).format(v);

let chartTend = null, chartProd = null, chartCli = null, chartProv = null, chartEgr = null;

// Definición de series del comparativo (color + cómo obtener el valor por mes)
const CMP_SERIES = {
    ventas:   {label:'Ventas',   color:'#3b82f6', val:d=>+d.ventas||0},
    compras:  {label:'Compras',  color:'#ef4444', val:d=>+d.compras||0},
    nomina:   {label:'Nómina',   color:'#6b7280', val:d=>+d.nomina||0},
    ingresos: {label:'Ingresos', color:'#10b981', val:d=>+d.ingresos||0},
    egresos:  {label:'Egresos',  color:'#f59e0b', val:d=>+d.egresos||0},
    utilidad: {label:'Utilidad', color:'#8b5cf6', val:d=>(+d.ventas||0)-(+d.compras||0)},
};
const PALETA = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#0ea5e9','#ec4899','#14b8a6'];

// Preferencias visuales del usuario: se persisten en BD (usuarios_preferencias)
// vía el servicio centralizado, no en localStorage, para que sigan al usuario
// entre navegadores y dispositivos.
const PREF_MESES      = <?= json_encode($prefMeses ?? '6') ?>;
const PREF_TIPO_CHART = <?= json_encode($prefTipoChart ?? 'bar') ?>;
// Tablero del usuario: orden y ancho de cada tarjeta, [{k, w}, ...].
const PREF_LAYOUT = <?= json_encode(array_values($prefLayout ?? []), JSON_UNESCAPED_UNICODE) ?>;

let _lastSavedPrefs = null;

function saveFilters(){
    // Año y mes NO se persisten: el dashboard siempre arranca en el mes/año actual.
    // Solo se recuerdan las preferencias visuales (rango de tendencia y tipo de gráfico).
    const meses = $('fMeses').value, tipoChart = $('fTipoChart').value;
    const firma = meses + '|' + tipoChart;
    if (firma === _lastSavedPrefs) return; // sin cambios: no reescribir en BD
    _lastSavedPrefs = firma;

    if (typeof window.CMG_guardarVista === 'function') {
        // reload:false — el dashboard ya re-renderiza sus gráficos por sí solo.
        window.CMG_guardarVista('dashboard', { meses, tipoChart }, { reload: false });
    }
}
function loadFilters(){
    if(PREF_MESES)      $('fMeses').value     = PREF_MESES;
    if(PREF_TIPO_CHART) $('fTipoChart').value = PREF_TIPO_CHART;
    // Evita que la carga inicial reescriba en BD lo que ya venía de ella
    _lastSavedPrefs = $('fMeses').value + '|' + $('fTipoChart').value;
    // El año y el mes quedan en su valor por defecto del HTML (año y mes actuales)
}
function resetFilters(){
    $('fModo').value      = 'periodo';
    $('fAnio').value      = '<?= $anioActual ?>';
    $('fMes').value       = '<?= $mesActual ?>';
    $('fDesde').value     = '';
    $('fHasta').value     = '';
    $('fMeses').value     = '6';
    $('fTipoChart').value = 'bar';
    toggleModo();
    saveFilters();
    applyFilters();
}

// ── Cambio de tipo de gráfico sin recargar datos ──
$('fTipoChart').addEventListener('change', () => {
    saveFilters();
    if(chartTend && window._lastTendencia) renderTendencia(window._lastTendencia, $('fTipoChart').value);
});

// ── Casillas del comparativo: re-render sin recargar datos ──
document.querySelectorAll('.cmp-serie').forEach(chk => {
    chk.addEventListener('change', () => {
        if(window._lastTendencia) renderTendencia(window._lastTendencia, $('fTipoChart').value);
    });
});

// ── Alternar Período / Rango de fechas ──
function toggleModo(){
    const rango = $('fModo').value === 'rango';
    document.querySelectorAll('.grp-periodo').forEach(el=>el.classList.toggle('d-none', rango));
    document.querySelectorAll('.grp-rango').forEach(el=>el.classList.toggle('d-none', !rango));
    if(rango && !$('fDesde').value){ quickRange('mes'); }
}
$('fModo').addEventListener('change', toggleModo);

// ── Rangos rápidos ──
function pad2(n){ return String(n).padStart(2,'0'); }
function isoDate(d){ return d.getFullYear()+'-'+pad2(d.getMonth()+1)+'-'+pad2(d.getDate()); }
function quickRange(tipo){
    const hoy = new Date();
    let d, h = hoy;
    if(tipo==='mes'){        d = new Date(hoy.getFullYear(), hoy.getMonth(), 1); h = new Date(hoy.getFullYear(), hoy.getMonth()+1, 0); }
    else if(tipo==='trim'){  const q = Math.floor(hoy.getMonth()/3)*3; d = new Date(hoy.getFullYear(), q, 1); h = new Date(hoy.getFullYear(), q+3, 0); }
    else if(tipo==='anio'){  d = new Date(hoy.getFullYear(), 0, 1); h = new Date(hoy.getFullYear(), 11, 31); }
    else if(tipo==='30d'){   d = new Date(hoy); d.setDate(d.getDate()-29); }
    $('fDesde').value = isoDate(d);
    $('fHasta').value = isoDate(h);
    applyFilters();
}

// ── Skeletons ──
function setSk(ids){
    ids.forEach(id=>{ const el=$(id); if(el){ el.classList.add('sk','text-tr'); } });
}
function clrSk(ids){
    ids.forEach(id=>{ const el=$(id); if(el){ el.classList.remove('sk','text-tr'); } });
}

// ── Badge estado ──
function badge(s){
    s=(s||'').toLowerCase();
    if(s==='autorizado') return `<span class="bsoft bs-success">Autorizado</span>`;
    if(s==='registrado') return `<span class="bsoft bs-info">Registrado</span>`;
    if(s==='borrador')   return `<span class="bsoft bs-warning">Borrador</span>`;
    if(s==='anulado')    return `<span class="bsoft bs-danger">Anulado</span>`;
    if(s==='aprobado')   return `<span class="bsoft bs-success">Aprobado</span>`;
    return `<span class="bsoft bs-secondary">${s}</span>`;
}

// ── Cambio comparativo ──
function chg(cur, prev, pct=false){
    const diff = cur - prev;
    const p    = prev===0 ? (cur===0?0:100) : (diff/prev)*100;
    const isUp = p>0, isDn = p<0;
    const cls  = isUp?'ch-up':(isDn?'ch-dn':'ch-neu');
    const ico  = isUp?'bi-arrow-up-right':(isDn?'bi-arrow-down-right':'bi-dash');
    const txt  = (isUp?'+':'')+fmtN(pct?diff:p)+(pct?' ptos.':'%');
    return `<span class="${cls}"><i class="bi ${ico}"></i> ${txt}</span> <span class="text-muted">vs período anterior</span>`;
}

// ── Tabla reciente genérica ──
function renderTbl(id, rows, cols4){
    const t = $(id);
    if(!rows||!rows.length){
        t.innerHTML=`<tr><td colspan="4" class="text-center py-3 text-muted"><i class="bi bi-inbox d-block fs-5 mb-1"></i>Sin registros</td></tr>`;
        return;
    }
    t.innerHTML = rows.map(r=>{
        const f = r.fecha ? r.fecha.substring(0,10).split('-').reverse().join('/') : '—';
        const ent = (r.entidad||'').substring(0,32);
        const cmp = r.comprobante||'';
        return `<tr>
            <td class="text-muted small">${f}</td>
            <td class="fw-medium">${ent}<br><small class="text-muted">${cmp}</small></td>
            <td class="text-end fw-bold">${fmt(r.total)}</td>
            <td class="text-center">${badge(r.estado)}</td>
        </tr>`;
    }).join('');
}

// ── Tabla vencidos ──
function renderVenc(id, rows, keyNombre){
    const t = $(id);
    if(!rows||!rows.length){
        t.innerHTML=`<tr><td colspan="3" class="text-center py-3 text-muted">Sin vencimientos</td></tr>`;
        return;
    }
    t.innerHTML = rows.map(r=>`<tr>
        <td class="fw-medium" style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${(r[keyNombre]||'').substring(0,28)}<br><small class="text-muted">${r.comprobante||''}</small></td>
        <td class="text-end text-danger fw-bold">${fmt(r.saldo)}</td>
        <td class="text-end"><span class="bsoft bs-danger">${r.dias_vencido}d</span></td>
    </tr>`).join('');
}

// ── Gráfico comparativo (tendencia) — series configurables por casillas ──
function seriesSeleccionadas(){
    const sel = Array.from(document.querySelectorAll('.cmp-serie:checked')).map(c=>c.value);
    return sel.length ? sel : ['ventas']; // al menos una
}
function hexToRgba(hex, a){
    const n = parseInt(hex.slice(1),16);
    return `rgba(${(n>>16)&255},${(n>>8)&255},${n&255},${a})`;
}
function renderTendencia(data, tipo){
    window._lastTendencia = data;
    const ctx = $('chartTendencia');
    if(chartTend) chartTend.destroy();
    const labels = data.map(d=>d.mes);

    // tipo → configuración de Chart.js
    const isLineFam = (tipo==='line' || tipo==='area');
    const isRadar   = (tipo==='radar');
    const isStacked = (tipo==='stacked');
    const fill      = (tipo==='area' || tipo==='radar');
    const chartType = isRadar ? 'radar' : (isLineFam ? 'line' : 'bar');

    const ds = seriesSeleccionadas().map(k=>{
        const s = CMP_SERIES[k];
        return {
            label: s.label,
            data: data.map(s.val),
            borderColor: s.color,
            backgroundColor: (isLineFam||isRadar) ? hexToRgba(s.color, fill?.14:.9) : hexToRgba(s.color,.8),
            pointBackgroundColor: s.color,
            fill: fill,
            tension: isRadar ? 0 : .3,
            borderRadius: 4,
            borderWidth: (isLineFam||isRadar) ? 2 : 0
        };
    });

    const opts = {
        responsive:true, maintainAspectRatio:false,
        interaction:{mode:'index',intersect:false},
        plugins:{
            legend:{position:'top',align:'end',labels:{boxWidth:11,usePointStyle:true,font:{size:12}}},
            tooltip:{callbacks:{label:c=>` ${c.dataset.label}: ${fmt(isRadar?c.parsed.r:c.parsed.y)}`}}
        }
    };
    if(isRadar){
        opts.scales = { r:{ beginAtZero:true, ticks:{ callback:v=>v>=1000?'$'+(v/1000)+'k':'$'+v, backdropColor:'transparent' } } };
    } else {
        opts.scales = {
            y:{beginAtZero:true, stacked:isStacked, grid:{color:'#f3f4f6'}, ticks:{callback:v=>v>=1000?'$'+(v/1000)+'k':'$'+v}},
            x:{stacked:isStacked, grid:{display:false}, ticks:{font:{size:11}}}
        };
    }

    chartTend = new Chart(ctx,{ type:chartType, data:{labels,datasets:ds}, options:opts });
}

// ── Gráfico Top Proveedores (compras) ──
function renderTopProveedores(data){
    const ctx = $('chartTopProveedores');
    if(chartProv) chartProv.destroy();
    if(!data||!data.length){ ctx.parentElement.innerHTML='<p class="text-muted text-center pt-5 small">Sin datos</p>'; return; }
    chartProv = new Chart(ctx,{
        type:'bar',
        data:{
            labels:data.map(d=>(d.nombre||'').substring(0,24)),
            datasets:[{label:'Compras',data:data.map(d=>d.total),backgroundColor:PALETA,borderRadius:4}]
        },
        options:{
            indexAxis:'y',responsive:true,maintainAspectRatio:false,
            plugins:{legend:{display:false},tooltip:{callbacks:{label:c=>` ${fmt(c.parsed.x)}`}}},
            scales:{x:{beginAtZero:true,ticks:{callback:v=>v>=1000?'$'+(v/1000)+'k':'$'+v}},y:{ticks:{font:{size:11}}}}
        }
    });
}

// ── Gráfico Egresos por Concepto (dona) ──
function renderEgresosConcepto(data){
    const ctx = $('chartEgresosConcepto');
    if(chartEgr) chartEgr.destroy();
    if(!data||!data.length){ ctx.parentElement.innerHTML='<p class="text-muted text-center pt-5 small">Sin datos</p>'; return; }
    chartEgr = new Chart(ctx,{
        type:'doughnut',
        data:{
            labels:data.map(d=>(d.nombre||'').substring(0,22)),
            datasets:[{data:data.map(d=>d.total),backgroundColor:PALETA,borderWidth:2}]
        },
        options:{
            responsive:true,maintainAspectRatio:false,
            plugins:{legend:{position:'bottom',labels:{boxWidth:11,font:{size:11}}},tooltip:{callbacks:{label:c=>` ${fmt(c.raw)}`}}}
        }
    });
}

// ── Gráfico top productos ──
function renderTopProductos(data){
    const ctx = $('chartTopProductos');
    if(chartProd) chartProd.destroy();
    if(!data||!data.length){ ctx.parentElement.innerHTML='<p class="text-muted text-center pt-5 small">Sin datos</p>'; return; }
    chartProd = new Chart(ctx,{
        type:'bar',
        data:{
            labels:data.map(d=>(d.nombre||'').substring(0,20)),
            datasets:[{
                label:'Ventas',
                data:data.map(d=>d.total),
                backgroundColor:['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6'],
                borderRadius:4
            }]
        },
        options:{
            indexAxis:'y',responsive:true,maintainAspectRatio:false,
            plugins:{legend:{display:false},tooltip:{callbacks:{label:c=>` ${fmt(c.parsed.x)}`}}},
            scales:{x:{beginAtZero:true,ticks:{callback:v=>v>=1000?'$'+(v/1000)+'k':'$'+v}},y:{ticks:{font:{size:11}}}}
        }
    });
}

// ── Gráfico top clientes ──
function renderTopClientes(data){
    const ctx = $('chartTopClientes');
    if(chartCli) chartCli.destroy();
    if(!data||!data.length){ ctx.parentElement.innerHTML='<p class="text-muted text-center pt-5 small">Sin datos</p>'; return; }
    chartCli = new Chart(ctx,{
        type:'doughnut',
        data:{
            labels:data.map(d=>(d.nombre||'').substring(0,22)),
            datasets:[{data:data.map(d=>d.total),backgroundColor:['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6'],borderWidth:2}]
        },
        options:{
            responsive:true,maintainAspectRatio:false,
            plugins:{
                legend:{position:'bottom',labels:{boxWidth:11,font:{size:11}}},
                tooltip:{callbacks:{label:c=>` ${fmt(c.raw)}`}}
            }
        }
    });
}

// ── Saldos de bancos/efectivo + anticipos ──
function tipoFormaLabel(t){
    const m = {EFECTIVO:'Efectivo', BANCO:'Banco', TARJETA:'Tarjeta', PAYPHONE:'Payphone', OTRO:'Otro'};
    return m[(t||'').toUpperCase()] || (t||'');
}
function renderSaldosCaja(sc){
    const tb = $('tSaldosFormas');
    const formas = (sc && sc.formas) || [];
    if(!formas.length){
        tb.innerHTML = `<tr><td colspan="3" class="text-center py-3 text-muted"><i class="bi bi-inbox d-block fs-5 mb-1"></i>Sin formas de pago</td></tr>`;
        $('siTotalCaja').textContent = '';
    } else {
        let total = 0;
        tb.innerHTML = formas.map(f=>{
            total += Number(f.saldo)||0;
            return `<tr>
                <td class="fw-medium">${(f.nombre||'').substring(0,40)}</td>
                <td><span class="bsoft bs-secondary">${tipoFormaLabel(f.tipo)}</span></td>
                <td class="text-end fw-bold ${Number(f.saldo)<0?'text-danger':''}">${fmt(f.saldo)}</td>
            </tr>`;
        }).join('');
        $('siTotalCaja').textContent = 'Total: ' + fmt(total);
    }

    const ac = $('siAntClientes'), ap = $('siAntProveedores');
    ac.classList.remove('sk','text-tr'); ac.textContent = fmt((sc && sc.anticipos_clientes) || 0);
    ap.classList.remove('sk','text-tr'); ap.textContent = fmt((sc && sc.anticipos_proveedores) || 0);
}

// ── Carga principal ──
async function applyFilters(){
    saveFilters();
    const modo  = $('fModo').value;
    const anio  = $('fAnio').value;
    const mes   = $('fMes').value;
    const meses = $('fMeses').value;
    const tipo  = $('fTipoChart').value;

    setSk(['mValVentas','mChgVentas','mValCompras','mChgCompras',
           'mValNomina','mChgNomina',
           'mValUtilidad','mChgUtilidad','mValMargen','mChgMargen',
           'mValIngresos','mChgIngresos','mValEgresos','mChgEgresos',
           'mValCxc','mValCxp','siAntClientes','siAntProveedores']);

    ['tVentas','tCompras','tIngresos','tEgresos','tCxcVencidas','tCxpVencidas','tSaldosFormas'].forEach(id=>{
        $(id).innerHTML=`<tr><td colspan="4"><div class="sk" style="height:16px"></div></td></tr>`;
    });

    try {
        const fd = new FormData();
        fd.append('anio', anio);
        fd.append('mes', mes);
        fd.append('cant_meses', meses);
        // Modo rango: enviar desde/hasta (el backend les da prioridad sobre año/mes)
        if(modo === 'rango' && $('fDesde').value && $('fHasta').value){
            fd.append('desde', $('fDesde').value);
            fd.append('hasta', $('fHasta').value);
        }

        const res = await fetch(URL_DB, {method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'}});
        const json = await res.json();

        if(!json.ok){ alert('Error: '+json.error); return; }
        const d = json.data;

        // Badge ambiente
        const ba = $('badgeAmbiente');
        if(ba && d.tipo_ambiente_label){
            ba.textContent = d.tipo_ambiente_label;
            ba.className = 'badge rounded-pill align-middle ms-2 '+(d.tipo_ambiente==='2'?'bg-success':'bg-warning text-dark');
            ba.classList.remove('d-none');
        }

        // Período label
        $('lblPeriodo').textContent = 'Período: ' + (d.label_periodo||'');

        // Tarjetas fila 1
        const util = d.ventas_mes_actual - d.compras_mes_actual;
        const utilAnt = d.ventas_mes_anterior - d.compras_mes_anterior;
        const mrgn = d.ventas_mes_actual>0?(util/d.ventas_mes_actual)*100:0;
        const mrgnAnt = d.ventas_mes_anterior>0?(utilAnt/d.ventas_mes_anterior)*100:0;

        clrSk(['mValVentas','mChgVentas','mValCompras','mChgCompras',
               'mValNomina','mChgNomina',
               'mValUtilidad','mChgUtilidad','mValMargen','mChgMargen']);

        $('mValVentas').textContent   = fmt(d.ventas_mes_actual);
        $('mChgVentas').innerHTML     = chg(d.ventas_mes_actual, d.ventas_mes_anterior);
        $('mValCompras').textContent  = fmt(d.compras_mes_actual);
        $('mChgCompras').innerHTML    = chg(d.compras_mes_actual, d.compras_mes_anterior);
        $('mValNomina').textContent   = fmt(d.nomina_mes_actual);
        $('mChgNomina').innerHTML     = chg(d.nomina_mes_actual, d.nomina_mes_anterior);
        $('mValUtilidad').textContent = fmt(util);
        $('mChgUtilidad').innerHTML   = chg(util, utilAnt);
        $('mValMargen').textContent   = fmtN(mrgn)+'%';
        $('mChgMargen').innerHTML     = chg(mrgn, mrgnAnt, true);

        // Tarjetas fila 2
        clrSk(['mValIngresos','mChgIngresos','mValEgresos','mChgEgresos','mValCxc','mValCxp']);
        $('mValIngresos').textContent = fmt(d.ingresos_mes_actual);
        $('mChgIngresos').innerHTML   = chg(d.ingresos_mes_actual, d.ingresos_mes_anterior);
        $('mValEgresos').textContent  = fmt(d.egresos_mes_actual);
        $('mChgEgresos').innerHTML    = chg(d.egresos_mes_actual, d.egresos_mes_anterior);
        $('mValCxc').textContent      = fmt(d.cxc_total);
        $('mValCxp').textContent      = fmt(d.cxp_total);

        // Gráficos
        renderTendencia(d.tendencia, tipo);
        renderTopProductos(d.top_productos);
        renderTopClientes(d.top_clientes);
        renderTopProveedores(d.top_proveedores);
        renderEgresosConcepto(d.egresos_por_concepto);

        // Tablas recientes
        renderTbl('tVentas',   d.facturas_recientes);
        renderTbl('tCompras',  d.compras_recientes);
        renderTbl('tIngresos', d.ingresos_recientes);
        renderTbl('tEgresos',  d.egresos_recientes);

        // Vencidos
        renderVenc('tCxcVencidas', d.cxc_vencidas, 'cliente');
        renderVenc('tCxpVencidas', d.cxp_vencidas, 'proveedor');

        // Saldos de bancos/efectivo + anticipos
        renderSaldosCaja(d.saldos_caja);

    } catch(e){
        console.error(e);
    }
}

// ─────────────────────────────────────────────────────────────
//  Tablero personalizable: mover y redimensionar tarjetas
//  ---------------------------------------------------------
//  Todas las tarjetas (indicadores y paneles) viven en una sola
//  grilla CSS de 12 columnas, así que cualquiera puede ir a
//  cualquier posición y ocupar de 2 a 12 columnas de ancho.
//  El resultado (orden + ancho) se persiste en
//  usuarios_preferencias (__vista__.layout), igual que el resto
//  de preferencias visuales del módulo.
//  Se usa Pointer Events para que funcione con mouse y con
//  pantalla táctil (HTML5 drag&drop no soporta touch).
// ─────────────────────────────────────────────────────────────
const DB_COLS     = 12;   // columnas de la grilla
const DB_MIN_SPAN = 2;    // ancho mínimo de una tarjeta

function dbZona(){ return $('zonaDashboard'); }
function dbItems(){
    const z = dbZona();
    return z ? Array.from(z.querySelectorAll(':scope > .db-item')) : [];
}

/** Layout actual: [{k:'ventas', w:2}, ...] en el orden en que están. */
function layoutActual(){
    return dbItems().map(el => ({ k: el.dataset.dbKey, w: parseInt(el.dataset.dbW, 10) || 4 }));
}

function setAncho(item, span){
    span = Math.max(DB_MIN_SPAN, Math.min(DB_COLS, span));
    item.dataset.dbW = span;
    item.style.gridColumn = 'span ' + span;
    return span;
}

function guardarLayout(){
    if (typeof window.CMG_guardarVista !== 'function') return;
    window.CMG_guardarVista('dashboard', { layout: layoutActual() }, { reload: false });
}

/** Guarda el layout de fábrica (el del HTML) para poder restablecerlo. */
let DB_LAYOUT_DEFECTO = [];
function marcarLayoutDefecto(){ DB_LAYOUT_DEFECTO = layoutActual(); }

/** Aplica un layout guardado (orden + anchos). */
function aplicarLayout(layout){
    const z = dbZona();
    if(!z || !Array.isArray(layout) || !layout.length) return;
    const vistos = [];
    layout.forEach(cfg => {
        const k = cfg && cfg.k;
        if(!k) return;
        const el = z.querySelector(':scope > .db-item[data-db-key="' + CSS.escape(k) + '"]');
        if(!el) return;                       // tarjeta que ya no existe: se ignora
        setAncho(el, parseInt(cfg.w, 10) || parseInt(el.dataset.dbW, 10));
        z.appendChild(el);                    // la reubica en el orden guardado
        vistos.push(k);
    });
    // Tarjetas que no existían cuando el usuario guardó su tablero: al final,
    // con su ancho de fábrica.
    dbItems().filter(el => vistos.indexOf(el.dataset.dbKey) === -1)
             .forEach(el => z.appendChild(el));
}

/**
 * Punto de inserción para la posición (x,y) del cursor. No exige que el
 * cursor esté encima de otra tarjeta: busca la más cercana y decide si va
 * antes o después, de modo que también se puede soltar en el hueco que
 * queda al final de una fila o debajo de la última tarjeta.
 * @returns {Element|null} elemento ANTES del cual insertar (null = al final)
 */
function puntoInsercion(x, y, arrastrado){
    let refe = null, antes = true, mejor = Infinity;
    dbItems().forEach(el => {
        if(el === arrastrado) return;
        const r  = el.getBoundingClientRect();
        const cx = r.left + r.width / 2;
        const cy = r.top  + r.height / 2;
        // Se pondera la distancia vertical para que el cursor "prefiera" la
        // fila en la que está, aunque horizontalmente quede lejos.
        const d = Math.hypot(x - cx, (y - cy) * 2);
        if(d < mejor){
            mejor = d;
            refe  = el;
            antes = (y < r.top) ? true : (y > r.bottom ? false : x < cx);
        }
    });
    if(!refe) return null;
    return antes ? refe : refe.nextElementSibling;
}

function initMoverTarjetas(){
    const zona = dbZona();
    if(!zona) return;

    zona.querySelectorAll(':scope > .db-item > .db-drag-handle').forEach(handle => {
        handle.addEventListener('pointerdown', ev => {
            if(ev.button !== undefined && ev.button !== 0) return;
            ev.preventDefault();

            const item = handle.closest('.db-item');
            const x0 = ev.clientX, y0 = ev.clientY;
            let activo = false;

            const mover = e => {
                if(!activo){
                    if(Math.abs(e.clientX-x0) < 5 && Math.abs(e.clientY-y0) < 5) return;
                    activo = true;
                    item.classList.add('db-dragging');
                    zona.classList.add('db-zone-busy');
                    document.body.style.userSelect = 'none';
                    try { handle.setPointerCapture(ev.pointerId); } catch(_){}
                }
                const ref = puntoInsercion(e.clientX, e.clientY, item);
                // Solo se toca el DOM si la posición cambia (evita parpadeo).
                if(ref !== item && ref !== item.nextElementSibling){
                    zona.insertBefore(item, ref);
                }
            };

            const soltar = () => {
                document.removeEventListener('pointermove', mover);
                document.removeEventListener('pointerup', soltar);
                document.removeEventListener('pointercancel', soltar);
                document.body.style.userSelect = '';
                zona.classList.remove('db-zone-busy');
                if(activo){
                    item.classList.remove('db-dragging');
                    guardarLayout();
                }
            };

            document.addEventListener('pointermove', mover);
            document.addEventListener('pointerup', soltar);
            document.addEventListener('pointercancel', soltar);
        });
    });
}

function initRedimensionar(){
    const zona = dbZona();
    if(!zona) return;

    zona.querySelectorAll(':scope > .db-item > .db-resize-handle').forEach(handle => {
        handle.addEventListener('pointerdown', ev => {
            if(ev.button !== undefined && ev.button !== 0) return;
            ev.preventDefault();
            ev.stopPropagation();

            const item  = handle.closest('.db-item');
            const zr    = zona.getBoundingClientRect();
            const gap   = parseFloat(getComputedStyle(zona).columnGap) || 0;
            const colW  = (zr.width - gap * (DB_COLS - 1)) / DB_COLS;
            if(colW <= 0) return;
            // El ancho se calcula por el DESPLAZAMIENTO del cursor, no por la
            // posición de la tarjeta: al ensancharla puede saltar de fila, y
            // entonces su borde izquierdo cambia y el cálculo se volvería errático.
            const x0     = ev.clientX;
            const wIni   = parseInt(item.dataset.dbW, 10) || 4;

            item.classList.add('db-resizing');
            zona.classList.add('db-zone-busy');
            document.body.style.userSelect = 'none';
            try { handle.setPointerCapture(ev.pointerId); } catch(_){}

            const badge = document.createElement('span');
            badge.className = 'db-ancho-badge';
            item.appendChild(badge);
            const pintarBadge = n => { badge.textContent = n + '/' + DB_COLS; };
            pintarBadge(wIni);

            const mover = e => {
                const span = wIni + Math.round((e.clientX - x0) / (colW + gap));
                pintarBadge(setAncho(item, span));
            };

            const soltar = () => {
                document.removeEventListener('pointermove', mover);
                document.removeEventListener('pointerup', soltar);
                document.removeEventListener('pointercancel', soltar);
                document.body.style.userSelect = '';
                item.classList.remove('db-resizing');
                zona.classList.remove('db-zone-busy');
                badge.remove();
                guardarLayout();
            };

            document.addEventListener('pointermove', mover);
            document.addEventListener('pointerup', soltar);
            document.addEventListener('pointercancel', soltar);
        });
    });
}

/** Devuelve el tablero al orden y a los anchos de fábrica. */
function restablecerOrden(){
    aplicarLayout(DB_LAYOUT_DEFECTO);
    guardarLayout();
}

document.addEventListener('DOMContentLoaded', () => {
    marcarLayoutDefecto();
    aplicarLayout(PREF_LAYOUT);
    initMoverTarjetas();
    initRedimensionar();
    loadFilters();
    applyFilters();
});
</script>
