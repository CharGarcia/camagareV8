<?php /** @var string $rutaModulo @var array $mesas @var array $meseros @var array $menuItems @var array $categorias */ ?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<div class="container-fluid py-2">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
        <h5 class="fw-bold mb-0"><i class="bi bi-shop-window text-primary me-2"></i>Reportes Restaurante</h5>
        <div class="d-flex gap-1">
            <button type="button" class="btn btn-outline-danger btn-sm" id="rresBtnPdf" disabled><i class="bi bi-file-earmark-pdf me-1"></i>PDF</button>
            <button type="button" class="btn btn-outline-success btn-sm" id="rresBtnExcel" disabled><i class="bi bi-file-earmark-excel me-1"></i>Excel</button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm mb-2">
        <div class="card-body p-2">
            <div class="row g-2">
                <div class="col-6 col-md-3">
                    <label class="form-label small fw-bold mb-1">Ver por</label>
                    <select id="rres-ver-por" class="form-select form-select-sm">
                        <option value="MESAS">Ventas por mesa</option>
                        <option value="MESERO">Ventas por mesero</option>
                        <option value="MENU">Ítems del menú más vendidos</option>
                        <option value="CATEGORIA">Ventas por categoría</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small fw-bold mb-1">Desde</label>
                    <input type="date" id="rres-fecha-desde" class="form-control form-control-sm" value="<?= date('Y-m-01') ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small fw-bold mb-1">Hasta</label>
                    <input type="date" id="rres-fecha-hasta" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small fw-bold mb-1">Mesa</label>
                    <select id="rres-mesa" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <?php foreach ($mesas as $m): ?>
                            <option value="<?= (int) $m['id'] ?>"><?= htmlspecialchars($m['nombre']) ?><?= $m['ubicacion'] ? ' — ' . htmlspecialchars($m['ubicacion']) : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small fw-bold mb-1">Mesero</label>
                    <select id="rres-mesero" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($meseros as $u): ?>
                            <option value="<?= (int) $u['id'] ?>"><?= htmlspecialchars($u['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small fw-bold mb-1">Ítem del menú</label>
                    <select id="rres-menu-item" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($menuItems as $mi): ?>
                            <option value="<?= (int) $mi['id'] ?>"><?= htmlspecialchars($mi['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small fw-bold mb-1">Categoría</label>
                    <select id="rres-categoria" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <?php foreach ($categorias as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-3 d-flex align-items-end gap-1">
                    <button type="button" class="btn btn-primary btn-sm flex-grow-1" id="rresBtnGenerar"><i class="bi bi-funnel me-1"></i>Generar</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="rresBtnLimpiar" title="Limpiar filtros"><i class="bi bi-eraser"></i></button>
                </div>
            </div>
        </div>
    </div>

    <!-- KPIs -->
    <div class="row g-2 mb-2">
        <div class="col-4"><div class="card shadow-sm border-primary border-opacity-25"><div class="card-body p-2 text-center">
            <div class="small text-muted">Comandas</div>
            <div class="fs-5 fw-bold text-primary" id="rres-kpi-comandas">0</div>
        </div></div></div>
        <div class="col-4"><div class="card shadow-sm border-secondary border-opacity-25"><div class="card-body p-2 text-center">
            <div class="small text-muted">Documentos</div>
            <div class="fs-5 fw-bold text-secondary" id="rres-kpi-documentos">0</div>
        </div></div></div>
        <div class="col-4"><div class="card shadow-sm border-success border-opacity-25"><div class="card-body p-2 text-center">
            <div class="small text-muted">Total vendido</div>
            <div class="fs-5 fw-bold text-success" id="rres-kpi-total">$0.00</div>
        </div></div></div>
    </div>

    <!-- Tabla -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="reporte-restaurante-scroll" style="max-height:calc(100vh - 350px);overflow:auto;">
                <table class="table table-sm table-hover table-striped mb-0" style="font-size:0.82rem;">
                    <thead class="table-light sticky-top" style="top:0;z-index:1;">
                        <tr id="rres-head-mesas">
                            <th class="ps-3">Mesa</th><th>Ubicación</th><th class="text-center">Comandas</th>
                            <th class="text-center">Documentos</th><th class="text-end pe-3">Total</th>
                        </tr>
                        <tr id="rres-head-mesero" class="d-none">
                            <th class="ps-3">Mesero</th><th class="text-center">Comandas</th>
                            <th class="text-center">Documentos</th><th class="text-end pe-3">Total</th>
                        </tr>
                        <tr id="rres-head-menu" class="d-none">
                            <th class="ps-3">Ítem</th><th>Categoría</th><th class="text-center">Cant. Vendida</th><th class="text-end pe-3">Total</th>
                        </tr>
                        <tr id="rres-head-categoria" class="d-none">
                            <th class="ps-3">Categoría</th><th class="text-center">Cant. Vendida</th><th class="text-end pe-3">Total</th>
                        </tr>
                    </thead>
                    <tbody id="rres-tbody">
                        <tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-funnel fs-3 d-block mb-2"></i>Ajuste los filtros y presione <strong>Generar</strong>.</td></tr>
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
    const COLSPAN = { MESAS: 5, MESERO: 4, MENU: 4, CATEGORIA: 3 };

    function filtros() {
        return {
            ver_por: $('rres-ver-por').value,
            fecha_desde: $('rres-fecha-desde').value,
            fecha_hasta: $('rres-fecha-hasta').value,
            id_mesa: $('rres-mesa').value,
            id_usuario: $('rres-mesero').value,
            id_menu_item: $('rres-menu-item').value,
            id_categoria: $('rres-categoria').value,
        };
    }

    async function generar() {
        const f = filtros();
        const tbody = $('rres-tbody');
        const vp = f.ver_por;
        $('rres-head-mesas').classList.toggle('d-none', vp !== 'MESAS');
        $('rres-head-mesero').classList.toggle('d-none', vp !== 'MESERO');
        $('rres-head-menu').classList.toggle('d-none', vp !== 'MENU');
        $('rres-head-categoria').classList.toggle('d-none', vp !== 'CATEGORIA');

        const colspan = COLSPAN[vp] || 5;
        tbody.innerHTML = `<tr><td colspan="${colspan}" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Generando…</td></tr>`;
        $('rresBtnPdf').disabled = true; $('rresBtnExcel').disabled = true;

        try {
            const params = new URLSearchParams(f);
            const res = await fetch(`${BASE}/${RUTA}/generarAjax?${params.toString()}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json();
            if (!json.ok) { tbody.innerHTML = `<tr><td colspan="${colspan}" class="text-center text-danger py-4">${json.error || 'Error'}</td></tr>`; return; }

            tbody.innerHTML = json.rows;
            $('rres-kpi-comandas').textContent   = json.stats.cantidad_comandas ?? 0;
            $('rres-kpi-documentos').textContent = json.stats.cantidad_documentos ?? 0;
            $('rres-kpi-total').textContent      = money(json.stats.total_vendido);

            $('rresBtnPdf').disabled = json.total === 0;
            $('rresBtnExcel').disabled = json.total === 0;
            $('rresBtnPdf').onclick   = () => window.open(json.pdf_url, '_blank');
            $('rresBtnExcel').onclick = () => window.open(json.excel_url, '_blank');
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="${colspan}" class="text-center text-danger py-4">Error de comunicación.</td></tr>`;
        }
    }

    $('rresBtnGenerar').addEventListener('click', generar);
    $('rres-ver-por').addEventListener('change', generar);
    $('rresBtnLimpiar').addEventListener('click', () => {
        $('rres-fecha-desde').value = '<?= date('Y-m-01') ?>';
        $('rres-fecha-hasta').value = '<?= date('Y-m-d') ?>';
        $('rres-ver-por').value = 'MESAS';
        $('rres-mesa').value = '';
        $('rres-mesero').value = '';
        $('rres-menu-item').value = '';
        $('rres-categoria').value = '';
        generar();
    });

    generar(); // primera carga
})();
</script>
