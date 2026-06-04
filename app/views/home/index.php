<?php
/** @var string $titulo */
/** @var bool $sinEmpresaSuperAdmin */
$sinEmpresaSuperAdmin = $sinEmpresaSuperAdmin ?? false;
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
</style>

<div class="pb-4">

<?php if ($sinEmpresaSuperAdmin): ?>
<div class="alert alert-info border-0 shadow-sm rounded-3 mb-3 d-flex align-items-center">
    <i class="bi bi-info-circle-fill fs-4 me-3"></i>
    <div>
        <strong class="d-block mb-1">Super administrador sin empresa activa.</strong>
        Cree la primera empresa en <a href="<?= $base ?>/config/empresas-sistema" class="alert-link fw-semibold">Empresas del sistema</a>.
    </div>
</div>
<?php endif; ?>

<!-- Header + badge ambiente -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Dashboard
            <span id="badgeAmbiente" class="badge rounded-pill align-middle ms-2 d-none" style="font-size:.6em;"></span>
        </h4>
        <p id="lblPeriodo" class="text-muted small mb-0">Cargando...</p>
    </div>
    <button class="btn btn-sm btn-primary" onclick="applyFilters()">
        <i class="bi bi-arrow-clockwise me-1"></i> Actualizar
    </button>
</div>

<!-- ── Filtros ── -->
<div class="db-filters mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-auto">
            <label class="form-label small fw-semibold mb-1">Año</label>
            <select id="fAnio" class="form-select form-select-sm" style="width:100px">
                <?php for ($y = $anioActual; $y >= $anioActual - 5; $y--): ?>
                <option value="<?= $y ?>" <?= $y === $anioActual ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-auto">
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
        <div class="col-auto">
            <label class="form-label small fw-semibold mb-1">Tendencia</label>
            <select id="fMeses" class="form-select form-select-sm" style="width:110px">
                <option value="3">3 meses</option>
                <option value="6" selected>6 meses</option>
                <option value="12">12 meses</option>
            </select>
        </div>
        <div class="col-auto">
            <label class="form-label small fw-semibold mb-1">Tipo gráfico</label>
            <select id="fTipoChart" class="form-select form-select-sm" style="width:120px">
                <option value="bar">Barras</option>
                <option value="line">Líneas</option>
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

<!-- ── Fila 1: Ventas / Compras / Utilidad / Margen ── -->
<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3">
        <div class="db-metric-card">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div><p class="db-metric-label mb-1">Ventas</p><div class="db-metric-value text-tr sk" id="mValVentas">$0.00</div></div>
                <div class="db-metric-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-receipt"></i></div>
            </div>
            <div class="db-metric-change ch-neu text-tr sk mt-2" id="mChgVentas">—</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="db-metric-card">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div><p class="db-metric-label mb-1">Compras</p><div class="db-metric-value text-tr sk" id="mValCompras">$0.00</div></div>
                <div class="db-metric-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-cart3"></i></div>
            </div>
            <div class="db-metric-change ch-neu text-tr sk mt-2" id="mChgCompras">—</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="db-metric-card">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div><p class="db-metric-label mb-1">Utilidad Bruta</p><div class="db-metric-value text-tr sk" id="mValUtilidad">$0.00</div></div>
                <div class="db-metric-icon bg-success bg-opacity-10 text-success"><i class="bi bi-graph-up-arrow"></i></div>
            </div>
            <div class="db-metric-change ch-neu text-tr sk mt-2" id="mChgUtilidad">—</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="db-metric-card">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div><p class="db-metric-label mb-1">Margen</p><div class="db-metric-value text-tr sk" id="mValMargen">0%</div></div>
                <div class="db-metric-icon bg-info bg-opacity-10 text-info"><i class="bi bi-percent"></i></div>
            </div>
            <div class="db-metric-change ch-neu text-tr sk mt-2" id="mChgMargen">—</div>
        </div>
    </div>
</div>

<!-- ── Fila 2: Ingresos / Egresos / CxC / CxP ── -->
<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3">
        <div class="db-metric-card">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div><p class="db-metric-label mb-1">Ingresos (caja)</p><div class="db-metric-value text-tr sk" id="mValIngresos">$0.00</div></div>
                <div class="db-metric-icon bg-success bg-opacity-10 text-success"><i class="bi bi-cash-coin"></i></div>
            </div>
            <div class="db-metric-change ch-neu text-tr sk mt-2" id="mChgIngresos">—</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="db-metric-card">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div><p class="db-metric-label mb-1">Egresos (caja)</p><div class="db-metric-value text-tr sk" id="mValEgresos">$0.00</div></div>
                <div class="db-metric-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-cash-stack"></i></div>
            </div>
            <div class="db-metric-change ch-neu text-tr sk mt-2" id="mChgEgresos">—</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="db-metric-card">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div><p class="db-metric-label mb-1">CxC Pendiente</p><div class="db-metric-value text-tr sk" id="mValCxc">$0.00</div></div>
                <div class="db-metric-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-person-check"></i></div>
            </div>
            <div class="db-metric-change ch-neu mt-2 small text-muted">Pendiente de cobro del período</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="db-metric-card">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div><p class="db-metric-label mb-1">CxP Pendiente</p><div class="db-metric-value text-tr sk" id="mValCxp">$0.00</div></div>
                <div class="db-metric-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-building"></i></div>
            </div>
            <div class="db-metric-change ch-neu mt-2 small text-muted">Pendiente de pago del período</div>
        </div>
    </div>
</div>

<!-- ── Gráfico tendencia + Top Productos ── -->
<div class="row g-3 mb-3">
    <div class="col-xl-8">
        <div class="db-panel h-100">
            <div class="db-panel-header">
                <h6 class="db-panel-title"><i class="bi bi-bar-chart-line me-2 text-primary"></i>Tendencia Financiera</h6>
            </div>
            <div class="p-3" style="height:280px"><canvas id="chartTendencia"></canvas></div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="db-panel h-100">
            <div class="db-panel-header">
                <h6 class="db-panel-title"><i class="bi bi-box-seam me-2 text-warning"></i>Top Productos</h6>
            </div>
            <div class="p-3" style="height:280px"><canvas id="chartTopProductos"></canvas></div>
        </div>
    </div>
</div>

<!-- ── Top Clientes + CxC Vencidas + CxP Vencidas ── -->
<div class="row g-3 mb-3">
    <div class="col-xl-4">
        <div class="db-panel h-100">
            <div class="db-panel-header">
                <h6 class="db-panel-title"><i class="bi bi-people me-2 text-info"></i>Top Clientes</h6>
            </div>
            <div class="p-3" style="height:240px"><canvas id="chartTopClientes"></canvas></div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="db-panel h-100">
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
    <div class="col-xl-4">
        <div class="db-panel h-100">
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
</div>

<!-- ── 4 Tablas recientes ── -->
<div class="row g-3">
    <div class="col-lg-6">
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
    <div class="col-lg-6">
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
    <div class="col-lg-6">
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
    <div class="col-lg-6">
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
const URL_DB  = '<?= $base ?>/home/dashboardDataAjax';
const $ = id => document.getElementById(id);
const fmt  = v => new Intl.NumberFormat('es-EC',{style:'currency',currency:'USD'}).format(v);
const fmtN = v => new Intl.NumberFormat('es-EC',{maximumFractionDigits:1}).format(v);

let chartTend = null, chartProd = null, chartCli = null;

const LS_KEY = 'db_filtros';
function saveFilters(){
    // Año y mes NO se persisten: el dashboard siempre arranca en el mes/año actual.
    // Solo se recuerdan las preferencias visuales (rango de tendencia y tipo de gráfico).
    localStorage.setItem(LS_KEY, JSON.stringify({
        meses:$('fMeses').value, tipoChart:$('fTipoChart').value
    }));
}
function loadFilters(){
    try {
        const f = JSON.parse(localStorage.getItem(LS_KEY)||'{}');
        if(f.meses) $('fMeses').value     = f.meses;
        if(f.tipoChart) $('fTipoChart').value = f.tipoChart;
    } catch(e){}
    // El año y el mes quedan en su valor por defecto del HTML (año y mes actuales)
}
function resetFilters(){
    $('fAnio').value      = '<?= $anioActual ?>';
    $('fMes').value       = '<?= $mesActual ?>';
    $('fMeses').value     = '6';
    $('fTipoChart').value = 'bar';
    saveFilters();
    applyFilters();
}

// ── Cambio de tipo de gráfico sin recargar datos ──
$('fTipoChart').addEventListener('change', () => {
    saveFilters();
    if(chartTend && window._lastTendencia) renderTendencia(window._lastTendencia, $('fTipoChart').value);
});

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

// ── Gráfico tendencia ──
function renderTendencia(data, tipo){
    window._lastTendencia = data;
    const ctx = $('chartTendencia');
    if(chartTend) chartTend.destroy();
    const labels  = data.map(d=>d.mes);
    const isFill  = tipo==='line';
    const ds = [
        {label:'Ventas',   data:data.map(d=>d.ventas),   borderColor:'#3b82f6',backgroundColor:isFill?'rgba(59,130,246,.15)':'rgba(59,130,246,.8)',  fill:isFill,tension:.3,borderRadius:4,borderWidth:isFill?2:0},
        {label:'Compras',  data:data.map(d=>d.compras),  borderColor:'#ef4444',backgroundColor:isFill?'rgba(239,68,68,.12)':'rgba(239,68,68,.8)',     fill:isFill,tension:.3,borderRadius:4,borderWidth:isFill?2:0},
        {label:'Ingresos', data:data.map(d=>d.ingresos), borderColor:'#10b981',backgroundColor:isFill?'rgba(16,185,129,.12)':'rgba(16,185,129,.8)',   fill:isFill,tension:.3,borderRadius:4,borderWidth:isFill?2:0},
        {label:'Egresos',  data:data.map(d=>d.egresos),  borderColor:'#f59e0b',backgroundColor:isFill?'rgba(245,158,11,.12)':'rgba(245,158,11,.8)',   fill:isFill,tension:.3,borderRadius:4,borderWidth:isFill?2:0},
    ];
    chartTend = new Chart(ctx,{
        type: tipo==='line' ? 'line' : 'bar',
        data:{labels,datasets:ds},
        options:{
            responsive:true,maintainAspectRatio:false,
            interaction:{mode:'index',intersect:false},
            plugins:{
                legend:{position:'top',align:'end',labels:{boxWidth:11,usePointStyle:true,font:{size:12}}},
                tooltip:{callbacks:{label:c=>` ${c.dataset.label}: ${fmt(c.parsed.y)}`}}
            },
            scales:{
                y:{beginAtZero:true,grid:{color:'#f3f4f6'},ticks:{callback:v=>v>=1000?'$'+(v/1000)+'k':'$'+v}},
                x:{grid:{display:false},ticks:{font:{size:11}}}
            }
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

// ── Carga principal ──
async function applyFilters(){
    saveFilters();
    const anio  = $('fAnio').value;
    const mes   = $('fMes').value;
    const meses = $('fMeses').value;
    const tipo  = $('fTipoChart').value;

    setSk(['mValVentas','mChgVentas','mValCompras','mChgCompras',
           'mValUtilidad','mChgUtilidad','mValMargen','mChgMargen',
           'mValIngresos','mChgIngresos','mValEgresos','mChgEgresos',
           'mValCxc','mValCxp']);

    ['tVentas','tCompras','tIngresos','tEgresos','tCxcVencidas','tCxpVencidas'].forEach(id=>{
        $(id).innerHTML=`<tr><td colspan="4"><div class="sk" style="height:16px"></div></td></tr>`;
    });

    try {
        const fd = new FormData();
        fd.append('anio', anio);
        fd.append('mes', mes);
        fd.append('cant_meses', meses);

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
               'mValUtilidad','mChgUtilidad','mValMargen','mChgMargen']);

        $('mValVentas').textContent   = fmt(d.ventas_mes_actual);
        $('mChgVentas').innerHTML     = chg(d.ventas_mes_actual, d.ventas_mes_anterior);
        $('mValCompras').textContent  = fmt(d.compras_mes_actual);
        $('mChgCompras').innerHTML    = chg(d.compras_mes_actual, d.compras_mes_anterior);
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

        // Tablas recientes
        renderTbl('tVentas',   d.facturas_recientes);
        renderTbl('tCompras',  d.compras_recientes);
        renderTbl('tIngresos', d.ingresos_recientes);
        renderTbl('tEgresos',  d.egresos_recientes);

        // Vencidos
        renderVenc('tCxcVencidas', d.cxc_vencidas, 'cliente');
        renderVenc('tCxpVencidas', d.cxp_vencidas, 'proveedor');

    } catch(e){
        console.error(e);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    loadFilters();
    applyFilters();
});
</script>
