<?php
/** @var string $titulo */
/** @var bool $sinEmpresaSuperAdmin */
$sinEmpresaSuperAdmin = $sinEmpresaSuperAdmin ?? false;
$base = rtrim(BASE_URL ?? '', '/');
?>

<style>
/* Dashboard Styles */
.dashboard-container {
    padding-bottom: 2rem;
    font-family: 'Inter', sans-serif;
}
.metric-card {
    background: #ffffff;
    border-radius: 12px;
    padding: 1.25rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
    border: 1px solid rgba(0,0,0,0.05);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.metric-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.04);
}
.metric-icon-bg {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}
.metric-value {
    font-size: 1.75rem;
    font-weight: 700;
    color: #111827;
    line-height: 1.2;
}
.metric-label {
    font-size: 0.875rem;
    color: #6b7280;
    font-weight: 500;
}
.metric-change {
    font-size: 0.75rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}
.change-up { color: #059669; }
.change-down { color: #dc2626; }
.change-neutral { color: #6b7280; }

.dashboard-panel {
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    border: 1px solid rgba(0,0,0,0.05);
    overflow: hidden;
}
.panel-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #f3f4f6;
    background: #fafafa;
}
.panel-title {
    font-size: 1rem;
    font-weight: 600;
    color: #374151;
    margin: 0;
}
.modern-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}
.modern-table th {
    background-color: #f9fafb;
    padding: 0.75rem 1.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 1px solid #e5e7eb;
}
.modern-table td {
    padding: 1rem 1.5rem;
    font-size: 0.875rem;
    color: #111827;
    border-bottom: 1px solid #f3f4f6;
    vertical-align: middle;
}
.modern-table tbody tr:last-child td {
    border-bottom: none;
}
.modern-table tbody tr:hover {
    background-color: #f9fafb;
}
.badge-soft {
    padding: 0.25em 0.6em;
    border-radius: 0.375rem;
    font-size: 0.75rem;
    font-weight: 500;
}
.badge-soft-success { background-color: #d1fae5; color: #065f46; }
.badge-soft-warning { background-color: #fef3c7; color: #92400e; }
.badge-soft-danger { background-color: #fee2e2; color: #991b1b; }
.badge-soft-info { background-color: #dbeafe; color: #1e40af; }

.skeleton-box {
    background: linear-gradient(90deg, #f3f4f6 25%, #e5e7eb 50%, #f3f4f6 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
    border-radius: 0.375rem;
}
@keyframes loading {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
</style>

<div class="dashboard-container">
    <?php if ($sinEmpresaSuperAdmin): ?>
    <div class="alert alert-info border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center" role="alert">
        <i class="bi bi-info-circle-fill fs-4 me-3"></i>
        <div>
            <strong class="d-block mb-1">Super administrador sin empresa activa.</strong>
            Cree la primera empresa en
            <a href="<?= htmlspecialchars($base) ?>/config/empresas-sistema" class="alert-link fw-semibold">Empresas del sistema</a>.
        </div>
    </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1">Dashboard</h3>
            <p class="text-muted small mb-0">Resumen general y métricas clave de la empresa</p>
        </div>
        <div>
            <button class="btn btn-primary shadow-sm" onclick="loadDashboardData()">
                <i class="bi bi-arrow-clockwise me-1"></i> Actualizar
            </button>
        </div>
    </div>

    <!-- Metrics Row -->
    <div class="row g-4 mb-4">
        <!-- Ingresos del Mes -->
        <div class="col-md-6 col-xl-3">
            <div class="metric-card">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <p class="metric-label mb-1">Ingresos del Mes</p>
                        <h4 class="metric-value mb-0 skeleton-text" id="valVentasMes">$0.00</h4>
                    </div>
                    <div class="metric-icon-bg bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-currency-dollar"></i>
                    </div>
                </div>
                <div class="metric-change mt-3 skeleton-text" id="changeVentas">
                    <span>-</span> vs mes anterior
                </div>
            </div>
        </div>

        <!-- Compras del Mes -->
        <div class="col-md-6 col-xl-3">
            <div class="metric-card">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <p class="metric-label mb-1">Compras del Mes</p>
                        <h4 class="metric-value mb-0 skeleton-text" id="valComprasMes">$0.00</h4>
                    </div>
                    <div class="metric-icon-bg bg-danger bg-opacity-10 text-danger">
                        <i class="bi bi-cart"></i>
                    </div>
                </div>
                <div class="metric-change mt-3 skeleton-text" id="changeCompras">
                    <span>-</span> vs mes anterior
                </div>
            </div>
        </div>

        <!-- Utilidad Bruta -->
        <div class="col-md-6 col-xl-3">
            <div class="metric-card">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <p class="metric-label mb-1">Utilidad Bruta (Mes)</p>
                        <h4 class="metric-value mb-0 skeleton-text" id="valUtilidad">$0.00</h4>
                    </div>
                    <div class="metric-icon-bg bg-success bg-opacity-10 text-success">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                </div>
                <div class="metric-change mt-3 skeleton-text" id="changeUtilidad">
                    <span>-</span> vs mes anterior
                </div>
            </div>
        </div>

        <!-- Margen de Utilidad -->
        <div class="col-md-6 col-xl-3">
            <div class="metric-card">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <p class="metric-label mb-1">Margen (Mes)</p>
                        <h4 class="metric-value mb-0 skeleton-text" id="valMargen">0.0%</h4>
                    </div>
                    <div class="metric-icon-bg bg-info bg-opacity-10 text-info">
                        <i class="bi bi-percent"></i>
                    </div>
                </div>
                <div class="metric-change mt-3 skeleton-text" id="changeMargen">
                    <span>-</span> vs mes anterior
                </div>
            </div>
        </div>
    </div>

    <!-- Chart Row -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="dashboard-panel">
                <div class="panel-header d-flex justify-content-between align-items-center">
                    <h5 class="panel-title"><i class="bi bi-bar-chart-line me-2 text-primary"></i>Tendencia Financiera (6 Meses)</h5>
                </div>
                <div class="p-4">
                    <div style="height: 300px; width: 100%;">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tables Row -->
    <div class="row g-4">
        <!-- Ventas Recientes -->
        <div class="col-lg-6">
            <div class="dashboard-panel h-100">
                <div class="panel-header">
                    <h5 class="panel-title"><i class="bi bi-receipt me-2 text-success"></i>Últimas Ventas</h5>
                </div>
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th class="text-end">Total</th>
                                <th class="text-center">Estado</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyVentas">
                            <!-- Skeleton Rows -->
                            <tr><td colspan="4"><div class="skeleton-box" style="height: 20px; width: 100%;"></div></td></tr>
                            <tr><td colspan="4"><div class="skeleton-box" style="height: 20px; width: 100%;"></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Compras Recientes -->
        <div class="col-lg-6">
            <div class="dashboard-panel h-100">
                <div class="panel-header">
                    <h5 class="panel-title"><i class="bi bi-bag-check me-2 text-danger"></i>Últimas Compras</h5>
                </div>
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Proveedor</th>
                                <th class="text-end">Total</th>
                                <th class="text-center">Estado</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyCompras">
                            <!-- Skeleton Rows -->
                            <tr><td colspan="4"><div class="skeleton-box" style="height: 20px; width: 100%;"></div></td></tr>
                            <tr><td colspan="4"><div class="skeleton-box" style="height: 20px; width: 100%;"></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Incluir Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
let trendChart = null;
const URL_DASHBOARD = '<?= $base ?>/home/dashboardDataAjax';

const formatCurrency = (val) => new Intl.NumberFormat('es-EC', { style: 'currency', currency: 'USD' }).format(val);
const formatNumber = (val) => new Intl.NumberFormat('es-EC', { maximumFractionDigits: 1 }).format(val);

function calculateChange(current, previous) {
    if (previous === 0) {
        if (current === 0) return { percent: 0, text: '0%', class: 'change-neutral', icon: 'bi-dash' };
        return { percent: 100, text: '+100%', class: 'change-up', icon: 'bi-arrow-up-right' };
    }
    const diff = current - previous;
    const pct = (diff / previous) * 100;
    const isUp = pct > 0;
    const isDown = pct < 0;
    
    return {
        percent: pct,
        text: (isUp ? '+' : '') + formatNumber(pct) + '%',
        class: isUp ? 'change-up' : (isDown ? 'change-down' : 'change-neutral'),
        icon: isUp ? 'bi-arrow-up-right' : (isDown ? 'bi-arrow-down-right' : 'bi-dash')
    };
}

function updateMetricCard(idValue, idChange, current, previous) {
    document.getElementById(idValue).textContent = formatCurrency(current);
    document.getElementById(idValue).classList.remove('skeleton-box', 'text-transparent');
    
    const change = calculateChange(current, previous);
    const changeEl = document.getElementById(idChange);
    changeEl.innerHTML = `<span class="${change.class}"><i class="bi ${change.icon}"></i> ${change.text}</span> <span class="ms-1 text-muted">vs mes anterior</span>`;
    changeEl.classList.remove('skeleton-box', 'text-transparent');
}

function updatePercentageCard(idValue, idChange, currentPct, previousPct) {
    document.getElementById(idValue).textContent = formatNumber(currentPct) + '%';
    document.getElementById(idValue).classList.remove('skeleton-box', 'text-transparent');
    
    const diff = currentPct - previousPct;
    const isUp = diff > 0;
    const isDown = diff < 0;
    
    const changeClass = isUp ? 'change-up' : (isDown ? 'change-down' : 'change-neutral');
    const changeIcon = isUp ? 'bi-arrow-up-right' : (isDown ? 'bi-arrow-down-right' : 'bi-dash');
    const changeText = (isUp ? '+' : '') + formatNumber(diff) + '% ptos.';
    
    const changeEl = document.getElementById(idChange);
    changeEl.innerHTML = `<span class="${changeClass}"><i class="bi ${changeIcon}"></i> ${changeText}</span> <span class="ms-1 text-muted">vs mes anterior</span>`;
    changeEl.classList.remove('skeleton-box', 'text-transparent');
}

function renderBadge(status) {
    status = String(status).toLowerCase();
    if (status === 'autorizado') return `<span class="badge-soft badge-soft-success">Autorizado</span>`;
    if (status === 'registrado') return `<span class="badge-soft badge-soft-info">Registrado</span>`;
    if (status === 'borrador') return `<span class="badge-soft badge-soft-warning">Borrador</span>`;
    if (status === 'anulado') return `<span class="badge-soft badge-soft-danger">Anulado</span>`;
    return `<span class="badge-soft badge-soft-secondary">${status}</span>`;
}

function renderTable(tbodyId, data) {
    const tbody = document.getElementById(tbodyId);
    if (!data || data.length === 0) {
        tbody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-muted"><i class="bi bi-inbox fs-4 d-block mb-1"></i>No hay registros recientes</td></tr>`;
        return;
    }
    
    let html = '';
    data.forEach(row => {
        const d = new Date(row.fecha + 'T12:00:00');
        const fechaFormat = String(d.getDate()).padStart(2,'0') + '/' + String(d.getMonth()+1).padStart(2,'0') + '/' + d.getFullYear();
        
        html += `
            <tr>
                <td class="text-muted">${fechaFormat}</td>
                <td class="fw-medium">${row.entidad.substring(0,35) + (row.entidad.length > 35 ? '...' : '')}<br><small class="text-muted">${row.comprobante}</small></td>
                <td class="text-end fw-bold">${formatCurrency(row.total)}</td>
                <td class="text-center">${renderBadge(row.estado)}</td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
}

function renderChart(data) {
    const ctx = document.getElementById('trendChart').getContext('2d');
    
    const labels = data.map(d => d.mes);
    const ventas = data.map(d => d.ventas);
    const compras = data.map(d => d.compras);
    
    if (trendChart) {
        trendChart.destroy();
    }
    
    trendChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Ventas',
                    data: ventas,
                    backgroundColor: 'rgba(59, 130, 246, 0.85)', // Blue primary
                    borderRadius: 4,
                    barPercentage: 0.6,
                    categoryPercentage: 0.8
                },
                {
                    label: 'Compras',
                    data: compras,
                    backgroundColor: 'rgba(239, 68, 68, 0.85)', // Red danger
                    borderRadius: 4,
                    barPercentage: 0.6,
                    categoryPercentage: 0.8
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'top',
                    align: 'end',
                    labels: { boxWidth: 12, usePointStyle: true, font: { family: 'Inter', size: 13 } }
                },
                tooltip: {
                    backgroundColor: 'rgba(17, 24, 39, 0.9)',
                    titleFont: { family: 'Inter', size: 13 },
                    bodyFont: { family: 'Inter', size: 13 },
                    padding: 12,
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                label += new Intl.NumberFormat('es-EC', { style: 'currency', currency: 'USD' }).format(context.parsed.y);
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f3f4f6', drawBorder: false },
                    ticks: {
                        font: { family: 'Inter' },
                        color: '#6b7280',
                        callback: function(value) {
                            if (value >= 1000) return '$' + (value / 1000) + 'k';
                            return '$' + value;
                        }
                    }
                },
                x: {
                    grid: { display: false, drawBorder: false },
                    ticks: { font: { family: 'Inter' }, color: '#6b7280' }
                }
            }
        }
    });
}

function resetSkeletons() {
    ['valVentasMes','changeVentas','valComprasMes','changeCompras','valUtilidad','changeUtilidad','valMargen','changeMargen'].forEach(id => {
        const el = document.getElementById(id);
        el.classList.add('skeleton-box', 'text-transparent');
    });
    
    document.getElementById('tbodyVentas').innerHTML = `<tr><td colspan="4"><div class="skeleton-box" style="height: 20px; width: 100%;"></div></td></tr><tr><td colspan="4"><div class="skeleton-box" style="height: 20px; width: 100%;"></div></td></tr>`;
    document.getElementById('tbodyCompras').innerHTML = `<tr><td colspan="4"><div class="skeleton-box" style="height: 20px; width: 100%;"></div></td></tr><tr><td colspan="4"><div class="skeleton-box" style="height: 20px; width: 100%;"></div></td></tr>`;
}

async function loadDashboardData() {
    resetSkeletons();
    
    try {
        const resp = await fetch(URL_DASHBOARD);
        const json = await resp.json();
        
        if (json.ok) {
            const data = json.data;
            
            updateMetricCard('valVentasMes', 'changeVentas', data.ventas_mes_actual, data.ventas_mes_anterior);
            updateMetricCard('valComprasMes', 'changeCompras', data.compras_mes_actual, data.compras_mes_anterior);
            
            const utilidadActual = data.ventas_mes_actual - data.compras_mes_actual;
            const utilidadAnterior = data.ventas_mes_anterior - data.compras_mes_anterior;
            updateMetricCard('valUtilidad', 'changeUtilidad', utilidadActual, utilidadAnterior);
            
            const margenActual = data.ventas_mes_actual > 0 ? (utilidadActual / data.ventas_mes_actual) * 100 : 0;
            const margenAnterior = data.ventas_mes_anterior > 0 ? (utilidadAnterior / data.ventas_mes_anterior) * 100 : 0;
            updatePercentageCard('valMargen', 'changeMargen', margenActual, margenAnterior);
            
            renderTable('tbodyVentas', data.facturas_recientes);
            renderTable('tbodyCompras', data.compras_recientes);
            
            renderChart(data.tendencia_6_meses);
            
        } else {
            console.error(json.error);
            alert("Error al cargar datos: " + json.error);
        }
    } catch (e) {
        console.error(e);
        alert("Error de conexión: " + e.message);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // Inject custom CSS utility
    const style = document.createElement('style');
    style.innerHTML = `.text-transparent { color: transparent !important; }`;
    document.head.appendChild(style);
    
    loadDashboardData();
});
</script>
