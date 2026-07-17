<?php /** @var string $rutaModulo @var array $puntos @var array $cajeros */ ?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<div class="container-fluid py-2">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
        <h5 class="fw-bold mb-0"><i class="bi bi-shop text-primary me-2"></i>Reportes POS</h5>
        <div class="d-flex gap-1">
            <button type="button" class="btn btn-outline-danger btn-sm" id="rposBtnPdf" disabled><i class="bi bi-file-earmark-pdf me-1"></i>PDF</button>
            <button type="button" class="btn btn-outline-success btn-sm" id="rposBtnExcel" disabled><i class="bi bi-file-earmark-excel me-1"></i>Excel</button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm mb-2">
        <div class="card-body p-2">
            <div class="row g-2">
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold mb-1">Ver por</label>
                    <select id="rpos-ver-por" class="form-select form-select-sm">
                        <option value="TURNOS">Resumen de turnos</option>
                        <option value="FORMA_PAGO">Ventas por forma de pago</option>
                        <option value="PRODUCTOS">Productos más vendidos</option>
                        <option value="CAJERO">Ventas por cajero</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold mb-1">Desde</label>
                    <input type="date" id="rpos-fecha-desde" class="form-control form-control-sm" value="<?= date('Y-m-01') ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold mb-1">Hasta</label>
                    <input type="date" id="rpos-fecha-hasta" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold mb-1">Punto de emisión</label>
                    <select id="rpos-punto" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($puntos as $p): ?>
                            <option value="<?= (int) $p['id'] ?>"><?= htmlspecialchars($p['cod_establecimiento'] . '-' . $p['codigo_punto']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold mb-1">Cajero</label>
                    <select id="rpos-cajero" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($cajeros as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-2 d-flex align-items-end gap-1">
                    <button type="button" class="btn btn-primary btn-sm flex-grow-1" id="rposBtnGenerar"><i class="bi bi-funnel me-1"></i>Generar</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="rposBtnLimpiar" title="Limpiar filtros"><i class="bi bi-eraser"></i></button>
                </div>
            </div>
        </div>
    </div>

    <!-- KPIs -->
    <div class="row g-2 mb-2">
        <div class="col-4"><div class="card shadow-sm border-primary border-opacity-25"><div class="card-body p-2 text-center">
            <div class="small text-muted">Ventas</div>
            <div class="fs-5 fw-bold text-primary" id="rpos-kpi-ventas">0</div>
        </div></div></div>
        <div class="col-4"><div class="card shadow-sm border-success border-opacity-25"><div class="card-body p-2 text-center">
            <div class="small text-muted">Total vendido</div>
            <div class="fs-5 fw-bold text-success" id="rpos-kpi-total">$0.00</div>
        </div></div></div>
        <div class="col-4"><div class="card shadow-sm border-secondary border-opacity-25"><div class="card-body p-2 text-center">
            <div class="small text-muted">Turnos</div>
            <div class="fs-5 fw-bold text-secondary" id="rpos-kpi-turnos">0</div>
        </div></div></div>
    </div>

    <!-- Tabla -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="rpos-scroll" style="max-height:calc(100vh - 320px);overflow:auto;">
                <table class="table table-sm table-hover table-striped mb-0" style="font-size:0.82rem;">
                    <thead class="table-light sticky-top" style="top:0;z-index:1;">
                        <tr id="rpos-head-turnos">
                            <th class="ps-3">#</th><th>Punto</th><th>Cajero</th><th class="text-center">Apertura</th>
                            <th class="text-center">Cierre</th><th class="text-center">Estado</th><th class="text-center">Docs</th>
                            <th class="text-end">Fondo Inicial</th><th class="text-end">Total Vendido</th>
                            <th class="text-end">Monto Contado</th><th class="text-end pe-3">Diferencia</th>
                        </tr>
                        <tr id="rpos-head-forma-pago" class="d-none">
                            <th class="ps-3">Forma de pago</th><th>Tipo</th><th class="text-center">Cant. Ventas</th><th class="text-end pe-3">Total</th>
                        </tr>
                        <tr id="rpos-head-productos" class="d-none">
                            <th class="ps-3">Producto</th><th class="text-center">Cant. Vendida</th><th class="text-end pe-3">Total</th>
                        </tr>
                        <tr id="rpos-head-cajero" class="d-none">
                            <th class="ps-3">Cajero</th><th class="text-center">Cant. Ventas</th><th class="text-end pe-3">Total</th>
                        </tr>
                    </thead>
                    <tbody id="rpos-tbody">
                        <tr><td colspan="11" class="text-center py-5 text-muted"><i class="bi bi-funnel fs-3 d-block mb-2"></i>Ajuste los filtros y presione <strong>Generar</strong>.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const RUTA = '<?= $rutaModulo ?>';
    const BASE = '<?= rtrim(BASE_URL ?? "", "/") ?>';
    const $ = id => document.getElementById(id);
    const money = n => '$' + (parseFloat(n) || 0).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function filtros() {
        return {
            ver_por: $('rpos-ver-por').value,
            fecha_desde: $('rpos-fecha-desde').value,
            fecha_hasta: $('rpos-fecha-hasta').value,
            id_punto_emision: $('rpos-punto').value,
            id_usuario: $('rpos-cajero').value,
        };
    }

    async function generar() {
        const f = filtros();
        const tbody = $('rpos-tbody');
        const vp = f.ver_por;
        $('rpos-head-turnos').classList.toggle('d-none', vp !== 'TURNOS');
        $('rpos-head-forma-pago').classList.toggle('d-none', vp !== 'FORMA_PAGO');
        $('rpos-head-productos').classList.toggle('d-none', vp !== 'PRODUCTOS');
        $('rpos-head-cajero').classList.toggle('d-none', vp !== 'CAJERO');

        const colspan = vp === 'TURNOS' ? 11 : (vp === 'FORMA_PAGO' ? 4 : 3);
        tbody.innerHTML = `<tr><td colspan="${colspan}" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Generando…</td></tr>`;
        $('rposBtnPdf').disabled = true; $('rposBtnExcel').disabled = true;

        try {
            const params = new URLSearchParams(f);
            const res = await fetch(`${BASE}/${RUTA}/generarAjax?${params.toString()}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json();
            if (!json.ok) { tbody.innerHTML = `<tr><td colspan="${colspan}" class="text-center text-danger py-4">${json.error || 'Error'}</td></tr>`; return; }

            tbody.innerHTML = json.rows;
            $('rpos-kpi-ventas').textContent = json.stats.cantidad_ventas ?? 0;
            $('rpos-kpi-total').textContent   = money(json.stats.total_vendido);
            $('rpos-kpi-turnos').textContent  = json.stats.cantidad_turnos ?? 0;

            $('rposBtnPdf').disabled = json.total === 0;
            $('rposBtnExcel').disabled = json.total === 0;
            $('rposBtnPdf').onclick   = () => window.open(json.pdf_url, '_blank');
            $('rposBtnExcel').onclick = () => window.open(json.excel_url, '_blank');
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="${colspan}" class="text-center text-danger py-4">Error de comunicación.</td></tr>`;
        }
    }

    $('rposBtnGenerar').addEventListener('click', generar);
    $('rpos-ver-por').addEventListener('change', generar);
    $('rposBtnLimpiar').addEventListener('click', () => {
        $('rpos-fecha-desde').value = '<?= date('Y-m-01') ?>';
        $('rpos-fecha-hasta').value = '<?= date('Y-m-d') ?>';
        $('rpos-ver-por').value = 'TURNOS';
        $('rpos-punto').value = '';
        $('rpos-cajero').value = '';
        generar();
    });

    generar(); // primera carga
})();
</script>
