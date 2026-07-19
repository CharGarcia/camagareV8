<?php /** @var string $rutaModulo @var string $base */ ?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<div class="container-fluid py-2">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
        <div>
            <h5 class="fw-bold mb-0"><i class="bi bi-graph-up-arrow text-primary me-2"></i>Flujo de Caja</h5>
            <small class="text-muted">Histórico real (Control Bancario) + proyección con CXC/CXP por vencer, roles de pago y cheques posfechados</small>
        </div>
        <div class="d-flex gap-1">
            <button type="button" class="btn btn-outline-danger btn-sm" id="fcBtnPdf" disabled><i class="bi bi-file-earmark-pdf me-1"></i>PDF</button>
            <button type="button" class="btn btn-outline-success btn-sm" id="fcBtnExcel" disabled><i class="bi bi-file-earmark-excel me-1"></i>Excel</button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm mb-2">
        <div class="card-body p-2">
            <div class="row g-2 align-items-end">
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold mb-1">Desde</label>
                    <input type="date" id="fc-desde" class="form-control form-control-sm" value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold mb-1">Hasta</label>
                    <input type="date" id="fc-hasta" class="form-control form-control-sm" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold mb-1">Agrupar por</label>
                    <select id="fc-agrupacion" class="form-select form-select-sm">
                        <option value="dia" selected>Día</option>
                        <option value="semana">Semana</option>
                        <option value="mes">Mes</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <button type="button" class="btn btn-primary btn-sm w-100" id="fcBtnGenerar"><i class="bi bi-funnel me-1"></i>Generar</button>
                </div>
            </div>
        </div>
    </div>

    <div id="fc-alerta-sin-cuentas" class="alert alert-warning py-2 px-3 small d-none">
        <i class="bi bi-exclamation-triangle me-1"></i>
        No hay cuentas de efectivo/banco configuradas con cuenta contable en <strong>Configuración Contable → Formas de Cobro/Pago</strong>.
        El flujo de caja no puede calcularse sin esa configuración.
    </div>

    <!-- KPIs -->
    <div class="row g-2 mb-2">
        <div class="col-6 col-md-3"><div class="card shadow-sm"><div class="card-body p-2 text-center">
            <div class="small text-muted">Saldo inicial del período</div>
            <div class="fs-6 fw-bold" id="fc-kpi-saldo-inicial">$0.00</div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card shadow-sm border-primary border-opacity-25"><div class="card-body p-2 text-center">
            <div class="small text-muted">Saldo actual (hoy)</div>
            <div class="fs-6 fw-bold text-primary" id="fc-kpi-saldo-actual">$0.00</div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card shadow-sm border-success border-opacity-25"><div class="card-body p-2 text-center">
            <div class="small text-muted">Entradas del rango</div>
            <div class="fs-6 fw-bold text-success" id="fc-kpi-entradas">$0.00</div>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card shadow-sm border-danger border-opacity-25"><div class="card-body p-2 text-center">
            <div class="small text-muted">Salidas del rango</div>
            <div class="fs-6 fw-bold text-danger" id="fc-kpi-salidas">$0.00</div>
        </div></div></div>
    </div>

    <!-- Gráfico -->
    <div class="card shadow-sm mb-2">
        <div class="card-body">
            <canvas id="fcChart" style="max-height: 260px;"></canvas>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="flujocaja-scroll" style="max-height:calc(100vh - 620px);min-height:220px;overflow:auto;">
                <table class="table table-sm table-hover table-striped mb-0" style="font-size:0.82rem;">
                    <thead class="table-light sticky-top" style="top:0;z-index:1;">
                        <tr>
                            <th class="ps-3">Período</th>
                            <th class="text-center">Tipo</th>
                            <th class="text-end">Entradas</th>
                            <th class="text-end">Salidas</th>
                            <th class="text-end pe-3">Saldo</th>
                        </tr>
                    </thead>
                    <tbody id="fc-tbody">
                        <tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-funnel fs-3 d-block mb-2"></i>Ajuste el rango y presione <strong>Generar</strong>.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function () {
    const RUTA = '<?= $rutaModulo ?>';
    const BASE = '<?= $base ?>';
    const $ = id => document.getElementById(id);
    const money = n => '$' + (parseFloat(n) || 0).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    let chart = null;

    function etiquetaPeriodo(periodo, agrupacion) {
        if (agrupacion === 'mes') {
            const [a, m] = periodo.split('-');
            const meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
            return meses[parseInt(m, 10) - 1] + ' ' + a;
        }
        const [a, m, d] = periodo.split('-');
        return `${d}-${m}-${a}`;
    }

    function pintarGrafico(periodos, agrupacion) {
        const labels = periodos.map(p => etiquetaPeriodo(p.periodo, agrupacion));
        const saldos = periodos.map(p => p.saldo);
        const cortePos = periodos.findIndex(p => !p.real);
        const saldosReal = saldos.map((v, i) => (cortePos === -1 || i <= cortePos - 1) ? v : null);
        const saldosProy = saldos.map((v, i) => (cortePos !== -1 && i >= cortePos - 1) ? v : null);

        if (chart) chart.destroy();
        chart = new Chart($('fcChart').getContext('2d'), {
            type: 'line',
            data: {
                labels,
                datasets: [
                    { label: 'Saldo real', data: saldosReal, borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,.1)', fill: true, tension: 0.25, spanGaps: false },
                    { label: 'Saldo proyectado', data: saldosProy, borderColor: '#fd7e14', borderDash: [6, 4], backgroundColor: 'rgba(253,126,20,.08)', fill: true, tension: 0.25, spanGaps: false },
                ],
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'bottom' } },
                scales: { y: { ticks: { callback: v => '$' + v.toLocaleString('es-EC') } } },
            },
        });
    }

    function pintarTabla(periodos, agrupacion) {
        const tbody = $('fc-tbody');
        if (!periodos.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-3 d-block mb-2"></i>Sin movimientos en el rango seleccionado.</td></tr>';
            return;
        }
        let h = '';
        periodos.forEach(p => {
            const badge = p.real
                ? '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">Real</span>'
                : '<span class="badge bg-warning bg-opacity-25 text-warning-emphasis border border-warning border-opacity-50">Proyectado</span>';
            const saldoClass = p.saldo < 0 ? 'text-danger' : 'text-dark';
            h += `<tr>
                <td class="ps-3">${etiquetaPeriodo(p.periodo, agrupacion)}</td>
                <td class="text-center">${badge}</td>
                <td class="text-end text-success">${money(p.entradas)}</td>
                <td class="text-end text-danger">${money(p.salidas)}</td>
                <td class="text-end pe-3 fw-bold ${saldoClass}">${money(p.saldo)}</td>
            </tr>`;
        });
        tbody.innerHTML = h;
    }

    async function generar() {
        const desde = $('fc-desde').value;
        const hasta = $('fc-hasta').value;
        const agrupacion = $('fc-agrupacion').value;
        const tbody = $('fc-tbody');
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Generando…</td></tr>';
        $('fcBtnPdf').disabled = true; $('fcBtnExcel').disabled = true;
        $('fc-alerta-sin-cuentas').classList.add('d-none');

        try {
            const params = new URLSearchParams({ desde, hasta, agrupacion });
            const res = await fetch(`${BASE}/${RUTA}/getLineaTiempoAjax?${params.toString()}`);
            const json = await res.json();
            if (!json.ok) { tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-4">${json.mensaje || 'Error'}</td></tr>`; return; }

            if (!json.cuentas_configuradas) {
                $('fc-alerta-sin-cuentas').classList.remove('d-none');
            }

            const periodos = json.periodos || [];
            let totalEntradas = 0, totalSalidas = 0;
            periodos.forEach(p => { totalEntradas += p.entradas; totalSalidas += p.salidas; });

            $('fc-kpi-saldo-inicial').textContent = money(json.saldo_inicial);
            $('fc-kpi-saldo-actual').textContent = money(json.saldo_actual);
            $('fc-kpi-entradas').textContent = money(totalEntradas);
            $('fc-kpi-salidas').textContent = money(totalSalidas);

            pintarGrafico(periodos, agrupacion);
            pintarTabla(periodos, agrupacion);

            $('fcBtnPdf').disabled = periodos.length === 0;
            $('fcBtnExcel').disabled = periodos.length === 0;
            $('fcBtnPdf').onclick = () => window.open(`${BASE}/${RUTA}/exportPdf?${params.toString()}`, '_blank');
            $('fcBtnExcel').onclick = () => window.open(`${BASE}/${RUTA}/exportExcel?${params.toString()}`, '_blank');
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">Error de comunicación.</td></tr>';
        }
    }

    $('fcBtnGenerar').addEventListener('click', generar);
    generar();
})();
</script>
