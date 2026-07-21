<?php /** @var string $rutaModulo @var array $conceptos @var array $anios @var int $anioActual */ ?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<div class="container-fluid py-2">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
        <h5 class="fw-bold mb-0"><i class="bi bi-file-earmark-minus text-primary me-2"></i>Reporte de Retenciones</h5>
        <div class="d-flex gap-1">
            <button type="button" class="btn btn-outline-danger btn-sm" id="rrBtnPdf" disabled><i class="bi bi-file-earmark-pdf me-1"></i>PDF</button>
            <button type="button" class="btn btn-outline-success btn-sm" id="rrBtnExcel" disabled><i class="bi bi-file-earmark-excel me-1"></i>Excel</button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm mb-2">
        <div class="card-body p-2">
            <div class="row g-2">
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold mb-1">Mostrar</label>
                    <select id="rr-tipo" class="form-select form-select-sm">
                        <option value="COMPRA">Retenciones de compras</option>
                        <option value="VENTA">Retenciones de ventas</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold mb-1">Ver por</label>
                    <select id="rr-ver-por" class="form-select form-select-sm">
                        <option value="DETALLE">Línea de impuesto (detalle)</option>
                        <option value="CABECERA">Comprobante (resumen)</option>
                        <option value="TERCERO">Sujeto retenido</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold mb-1">Año</label>
                    <select id="rr-anio" class="form-select form-select-sm">
                        <?php foreach ($anios as $a): ?>
                            <option value="<?= (int)$a ?>" <?= (int)$a === $anioActual ? 'selected' : '' ?>><?= (int)$a ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold mb-1">Mes</label>
                    <select id="rr-mes" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="1">Enero</option>
                        <option value="2">Febrero</option>
                        <option value="3">Marzo</option>
                        <option value="4">Abril</option>
                        <option value="5">Mayo</option>
                        <option value="6">Junio</option>
                        <option value="7">Julio</option>
                        <option value="8">Agosto</option>
                        <option value="9">Septiembre</option>
                        <option value="10">Octubre</option>
                        <option value="11">Noviembre</option>
                        <option value="12">Diciembre</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold mb-1">Desde</label>
                    <input type="date" id="rr-fecha-desde" class="form-control form-control-sm">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold mb-1">Hasta</label>
                    <input type="date" id="rr-fecha-hasta" class="form-control form-control-sm">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold mb-1">Impuesto</label>
                    <select id="rr-impuesto" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="RENTA">Renta</option>
                        <option value="IVA">IVA</option>
                        <option value="ISD">ISD</option>
                    </select>
                </div>
                <div class="col-6 col-md-3 position-relative">
                    <label class="form-label small fw-bold mb-1" id="rr-tercero-label">Proveedor</label>
                    <input type="text" id="rr-tercero-txt" class="form-control form-control-sm" placeholder="Nombre / RUC…" autocomplete="off">
                    <input type="hidden" id="rr-tercero-id">
                    <div id="rr-tercero-drop" class="list-group shadow position-absolute w-100 d-none" style="z-index:2000;max-height:220px;overflow:auto;"></div>
                </div>
                <div class="col-6 col-md-2" id="rr-estado-wrap">
                    <label class="form-label small fw-bold mb-1">Estado (compras)</label>
                    <select id="rr-estado" class="form-select form-select-sm">
                        <option value="TODOS">Todos</option>
                        <option value="borrador">Borrador</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="autorizada">Autorizada</option>
                        <option value="no_autorizada">No autorizada</option>
                        <option value="anulada">Anulada</option>
                    </select>
                </div>
                <div class="col-12 col-md-5">
                    <label class="form-label small fw-bold mb-1">Búsqueda libre</label>
                    <input type="text" id="rr-buscar" class="form-control form-control-sm" placeholder="Número, concepto, clave de acceso, sujeto…">
                </div>
                <div class="col-12 col-md-4 d-flex align-items-end gap-1">
                    <button type="button" class="btn btn-primary btn-sm flex-grow-1" id="rrBtnGenerar"><i class="bi bi-funnel me-1"></i>Generar</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="rrBtnLimpiar" title="Limpiar filtros"><i class="bi bi-eraser"></i></button>
                </div>
            </div>
        </div>
    </div>

    <!-- KPIs -->
    <div class="row g-2 mb-2">
        <div class="col-3"><div class="card shadow-sm border-secondary border-opacity-25"><div class="card-body p-2 text-center">
            <div class="small text-muted">Renta</div>
            <div class="fs-6 fw-bold" id="rr-kpi-renta">$0.00</div>
        </div></div></div>
        <div class="col-3"><div class="card shadow-sm border-secondary border-opacity-25"><div class="card-body p-2 text-center">
            <div class="small text-muted">IVA</div>
            <div class="fs-6 fw-bold" id="rr-kpi-iva">$0.00</div>
        </div></div></div>
        <div class="col-3"><div class="card shadow-sm border-secondary border-opacity-25"><div class="card-body p-2 text-center">
            <div class="small text-muted">ISD</div>
            <div class="fs-6 fw-bold" id="rr-kpi-isd">$0.00</div>
        </div></div></div>
        <div class="col-3"><div class="card shadow-sm border-primary border-opacity-25"><div class="card-body p-2 text-center">
            <div class="small text-muted">Total <span id="rr-n-total" class="text-secondary"></span></div>
            <div class="fs-6 fw-bold text-primary" id="rr-kpi-total">$0.00</div>
        </div></div></div>
    </div>

    <!-- Tabla -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="retenciones-scroll" style="max-height:calc(100vh - 360px);overflow:auto;">
                <table class="table table-sm table-hover table-striped mb-0" style="font-size:0.82rem;">
                    <thead class="table-light sticky-top" style="top:0;z-index:1;">
                        <tr id="rr-head-detalle">
                            <th class="ps-3">Tipo</th><th>Número</th><th>Fecha</th><th>Sujeto</th><th>Período</th>
                            <th>Impuesto</th><th>Cód.</th><th>Concepto</th>
                            <th class="text-end">Base</th><th class="text-end">%</th><th class="text-end pe-3">Valor</th>
                        </tr>
                        <tr id="rr-head-cabecera" class="d-none">
                            <th class="ps-3">Tipo</th><th>Número</th><th>Fecha</th><th>Sujeto</th>
                            <th class="text-end">Renta</th><th class="text-end">IVA</th><th class="text-end">ISD</th>
                            <th class="text-end">Total</th><th class="text-center pe-3">Líneas</th>
                        </tr>
                        <tr id="rr-head-tercero" class="d-none">
                            <th class="ps-3">Tipo</th><th>Sujeto</th><th></th>
                            <th class="text-center">Comprobantes</th><th class="text-center">Líneas</th>
                            <th class="text-end pe-3">Total</th>
                        </tr>
                    </thead>
                    <tbody id="rr-tbody">
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
    const BASE = '<?= $base ?>';
    const $ = id => document.getElementById(id);
    const money = n => '$' + (parseFloat(n) || 0).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // El tipo de sujeto queda implícito por "Mostrar": Compras → Proveedor, Ventas → Cliente.
    const tercerosTipo = () => $('rr-tipo').value === 'COMPRA' ? 'PROVEEDOR' : 'CLIENTE';

    function filtros() {
        return {
            tipo_retencion: $('rr-tipo').value,
            ver_por: $('rr-ver-por').value,
            anio: $('rr-anio').value,
            mes: $('rr-mes').value,
            fecha_desde: $('rr-fecha-desde').value,
            fecha_hasta: $('rr-fecha-hasta').value,
            codigo_impuesto: $('rr-impuesto').value,
            tercero_id: $('rr-tercero-id').value || 0,
            estado: $('rr-estado').value,
            buscar: $('rr-buscar').value.trim(),
        };
    }

    function actualizarTipoUI() {
        const esCompra = $('rr-tipo').value === 'COMPRA';
        $('rr-tercero-label').textContent = esCompra ? 'Proveedor' : 'Cliente';
        $('rr-tercero-txt').placeholder = (esCompra ? 'Proveedor' : 'Cliente') + ': Nombre / RUC…';
        $('rr-estado-wrap').classList.toggle('d-none', !esCompra);
        $('rr-tercero-txt').value = ''; $('rr-tercero-id').value = '';
    }

    // Autocompleta Desde/Hasta según el Año y (opcionalmente) el Mes elegidos.
    function actualizarFechas() {
        const anio = parseInt($('rr-anio').value, 10);
        if (!anio) return;
        const mes = $('rr-mes').value ? parseInt($('rr-mes').value, 10) : null;
        const hoy = new Date(); hoy.setHours(0, 0, 0, 0);
        let desde = mes ? new Date(anio, mes - 1, 1) : new Date(anio, 0, 1);
        let hasta = mes ? new Date(anio, mes, 0)     : new Date(anio, 11, 31);
        if (hasta > hoy) hasta = hoy;
        if (desde > hasta) desde = hasta;
        const fmt = d => d.toISOString().slice(0, 10);
        $('rr-fecha-desde').value = fmt(desde);
        $('rr-fecha-hasta').value = fmt(hasta);
    }

    async function generar() {
        const f = filtros();
        const tbody = $('rr-tbody');
        const vp = f.ver_por;
        $('rr-head-detalle').classList.toggle('d-none', vp !== 'DETALLE');
        $('rr-head-cabecera').classList.toggle('d-none', vp !== 'CABECERA');
        $('rr-head-tercero').classList.toggle('d-none', vp !== 'TERCERO');
        tbody.innerHTML = `<tr><td colspan="11" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Generando…</td></tr>`;
        $('rrBtnPdf').disabled = true; $('rrBtnExcel').disabled = true;

        try {
            const params = new URLSearchParams(f);
            const res = await fetch(`${BASE}/${RUTA}/generarAjax?${params.toString()}`);
            const json = await res.json();
            if (!json.ok) { tbody.innerHTML = `<tr><td colspan="11" class="text-center text-danger py-4">${json.mensaje || 'Error'}</td></tr>`; return; }

            tbody.innerHTML = json.rows;
            $('rr-kpi-renta').textContent = money(json.stats.total_renta);
            $('rr-kpi-iva').textContent   = money(json.stats.total_iva);
            $('rr-kpi-isd').textContent   = money(json.stats.total_isd);
            $('rr-kpi-total').textContent = money(json.stats.total_general);
            $('rr-n-total').textContent = '(' + json.stats.n_compras + ' compras / ' + json.stats.n_ventas + ' ventas)';

            $('rrBtnPdf').disabled = json.total === 0;
            $('rrBtnExcel').disabled = json.total === 0;
            $('rrBtnPdf').onclick   = () => window.open(json.pdf_url, '_blank');
            $('rrBtnExcel').onclick = () => window.open(json.excel_url, '_blank');
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="11" class="text-center text-danger py-4">Error de comunicación.</td></tr>`;
        }
    }

    // Autocomplete de sujeto retenido (proveedor o cliente según "Mostrar").
    let _t = null;
    $('rr-tercero-txt').addEventListener('input', function () {
        const q = this.value.trim();
        $('rr-tercero-id').value = '';
        const drop = $('rr-tercero-drop');
        if (q.length < 2) { drop.classList.add('d-none'); return; }
        clearTimeout(_t);
        _t = setTimeout(async () => {
            const res = await fetch(`${BASE}/${RUTA}/buscarTercerosAjax?tipo=${tercerosTipo()}&q=${encodeURIComponent(q)}`);
            const json = await res.json();
            drop.innerHTML = '';
            if (!json.data || !json.data.length) { drop.innerHTML = '<div class="list-group-item small text-muted">Sin resultados.</div>'; }
            else json.data.forEach(t => {
                const b = document.createElement('button');
                b.type = 'button'; b.className = 'list-group-item list-group-item-action small';
                b.innerHTML = `<strong>${t.nombre}</strong> <span class="text-muted">${t.ident || ''}</span>`;
                b.onclick = () => { $('rr-tercero-txt').value = t.nombre; $('rr-tercero-id').value = t.id; drop.classList.add('d-none'); };
                drop.appendChild(b);
            });
            drop.classList.remove('d-none');
        }, 300);
    });
    document.addEventListener('click', e => { if (!e.target.closest('#rr-tercero-txt') && !e.target.closest('#rr-tercero-drop')) $('rr-tercero-drop').classList.add('d-none'); });
    $('rr-tipo').addEventListener('change', () => { actualizarTipoUI(); generar(); });
    $('rr-anio').addEventListener('change', () => { actualizarFechas(); generar(); });
    $('rr-mes').addEventListener('change', () => { actualizarFechas(); generar(); });

    $('rrBtnGenerar').addEventListener('click', generar);
    $('rr-buscar').addEventListener('keydown', e => { if (e.key === 'Enter') generar(); });
    $('rrBtnLimpiar').addEventListener('click', () => {
        ['rr-tercero-txt','rr-tercero-id','rr-buscar'].forEach(id => $(id).value = '');
        $('rr-anio').value = '<?= $anioActual ?>'; $('rr-mes').value = '';
        $('rr-tipo').value = 'COMPRA'; $('rr-ver-por').value = 'DETALLE';
        $('rr-impuesto').value = ''; $('rr-estado').value = 'TODOS';
        actualizarTipoUI();
        actualizarFechas();
        generar();
    });

    actualizarTipoUI();
    actualizarFechas();
    generar(); // primera carga
})();
</script>
