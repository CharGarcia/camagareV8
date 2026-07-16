<?php /** @var string $rutaModulo @var array $formas @var array $conceptos @var array $anios */ ?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<div class="container-fluid py-2">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
        <h5 class="fw-bold mb-0"><i class="bi bi-arrow-down-up text-primary me-2"></i>Reporte de Ingresos y Egresos</h5>
        <div class="d-flex gap-1">
            <button type="button" class="btn btn-outline-danger btn-sm" id="rieBtnPdf" disabled><i class="bi bi-file-earmark-pdf me-1"></i>PDF</button>
            <button type="button" class="btn btn-outline-success btn-sm" id="rieBtnExcel" disabled><i class="bi bi-file-earmark-excel me-1"></i>Excel</button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm mb-2">
        <div class="card-body p-2">
            <div class="row g-2">
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold mb-1">Mostrar</label>
                    <select id="rie-tipo-flujo" class="form-select form-select-sm">
                        <option value="AMBOS">Ingresos y Egresos</option>
                        <option value="INGRESO">Solo Ingresos</option>
                        <option value="EGRESO">Solo Egresos</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold mb-1">Ver por</label>
                    <select id="rie-ver-por" class="form-select form-select-sm">
                        <option value="DETALLE">Documento (detalle)</option>
                        <option value="TERCERO">Tercero (resumen)</option>
                        <option value="FORMA">Forma de cobro/pago</option>
                        <option value="FECHA">Fecha (total por día)</option>
                        <option value="MES">Mes</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold mb-1">Desde</label>
                    <input type="date" id="rie-fecha-desde" class="form-control form-control-sm" value="<?= date('Y-m-01') ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold mb-1">Hasta</label>
                    <input type="date" id="rie-fecha-hasta" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold mb-1">Tipo de tercero</label>
                    <select id="rie-tercero-tipo" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="CLIENTE">Cliente</option>
                        <option value="PROVEEDOR">Proveedor</option>
                        <option value="EMPLEADO">Empleado</option>
                    </select>
                </div>
                <div class="col-6 col-md-2 position-relative">
                    <label class="form-label small fw-bold mb-1">Tercero</label>
                    <input type="text" id="rie-tercero-txt" class="form-control form-control-sm" placeholder="Nombre / RUC…" autocomplete="off">
                    <input type="hidden" id="rie-tercero-id">
                    <div id="rie-tercero-drop" class="list-group shadow position-absolute w-100 d-none" style="z-index:2000;max-height:220px;overflow:auto;"></div>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold mb-1">Forma de cobro/pago</label>
                    <select id="rie-forma" class="form-select form-select-sm">
                        <option value="0">Todas</option>
                        <?php foreach ($formas as $fp): ?>
                            <option value="<?= (int)$fp['id'] ?>"><?= htmlspecialchars($fp['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold mb-1">Operación bancaria</label>
                    <select id="rie-opbanc" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <option value="TRANSFERENCIA">Transferencia</option>
                        <option value="DEPOSITO">Depósito</option>
                        <option value="DEBITO">Débito</option>
                        <option value="CHEQUE">Cheque</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold mb-1">Concepto</label>
                    <select id="rie-concepto" class="form-select form-select-sm">
                        <option value="0">Todos</option>
                        <?php foreach ($conceptos as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold mb-1">Tipo de documento</label>
                    <select id="rie-tipo-doc" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="FACTURA">Factura</option>
                        <option value="RECIBO">Recibo</option>
                        <option value="COMPRA">Compra</option>
                        <option value="LIQUIDACION">Liquidación</option>
                        <option value="SALDO_INICIAL">Saldo inicial</option>
                        <option value="MANUAL">Manual</option>
                        <option value="OTRO">Otro</option>
                        <option value="ROL">Rol</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold mb-1">Estado</label>
                    <select id="rie-estado" class="form-select form-select-sm">
                        <option value="TODOS">Todos</option>
                        <option value="registrado">Registrado</option>
                        <option value="anulado">Anulado</option>
                    </select>
                </div>
                <div class="col-6 col-md-1">
                    <label class="form-label small fw-bold mb-1">Monto ≥</label>
                    <input type="number" id="rie-monto-min" class="form-control form-control-sm" step="0.01" placeholder="0">
                </div>
                <div class="col-6 col-md-1">
                    <label class="form-label small fw-bold mb-1">Monto ≤</label>
                    <input type="number" id="rie-monto-max" class="form-control form-control-sm" step="0.01" placeholder="∞">
                </div>
                <div class="col-12 col-md-8">
                    <label class="form-label small fw-bold mb-1">Búsqueda libre</label>
                    <input type="text" id="rie-buscar" class="form-control form-control-sm" placeholder="Número, documento, descripción, observaciones, tercero…">
                </div>
                <div class="col-12 col-md-4 d-flex align-items-end gap-1">
                    <button type="button" class="btn btn-primary btn-sm flex-grow-1" id="rieBtnGenerar"><i class="bi bi-funnel me-1"></i>Generar</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="rieBtnLimpiar" title="Limpiar filtros"><i class="bi bi-eraser"></i></button>
                </div>
            </div>
        </div>
    </div>

    <!-- KPIs -->
    <div class="row g-2 mb-2">
        <div class="col-4"><div class="card shadow-sm border-success border-opacity-25"><div class="card-body p-2 text-center">
            <div class="small text-muted">Ingresos <span id="rie-n-ing" class="text-secondary"></span></div>
            <div class="fs-5 fw-bold text-success" id="rie-kpi-ing">$0.00</div>
        </div></div></div>
        <div class="col-4"><div class="card shadow-sm border-danger border-opacity-25"><div class="card-body p-2 text-center">
            <div class="small text-muted">Egresos <span id="rie-n-egr" class="text-secondary"></span></div>
            <div class="fs-5 fw-bold text-danger" id="rie-kpi-egr">$0.00</div>
        </div></div></div>
        <div class="col-4"><div class="card shadow-sm border-primary border-opacity-25"><div class="card-body p-2 text-center">
            <div class="small text-muted">Neto (Ing − Egr)</div>
            <div class="fs-5 fw-bold text-primary" id="rie-kpi-neto">$0.00</div>
        </div></div></div>
    </div>

    <!-- Tabla -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="rie-scroll" style="max-height:calc(100vh - 340px);overflow:auto;">
                <table class="table table-sm table-hover table-striped mb-0" style="font-size:0.82rem;">
                    <thead class="table-light sticky-top" style="top:0;z-index:1;">
                        <tr id="rie-head-detalle">
                            <th class="ps-3">Flujo</th><th>Número</th><th>Fecha</th><th>Tercero</th>
                            <th>Documento</th><th>Descripción</th><th>Concepto</th><th class="text-center">Estado</th>
                            <th class="text-end pe-3">Monto</th>
                        </tr>
                        <tr id="rie-head-tercero" class="d-none">
                            <th class="ps-3">Flujo</th><th>Tipo</th><th>Tercero</th>
                            <th class="text-center">Comprobantes</th><th class="text-center">Documentos</th>
                            <th class="text-end pe-3">Total</th>
                        </tr>
                        <tr id="rie-head-forma" class="d-none">
                            <th class="ps-3">Flujo</th><th>Forma</th><th>Tipo</th>
                            <th class="text-center">Comprobantes</th><th class="text-center">Pagos</th>
                            <th class="text-end pe-3">Total</th>
                        </tr>
                        <tr id="rie-head-periodo" class="d-none">
                            <th class="ps-3" id="rie-th-periodo">Fecha</th>
                            <th class="text-end">Ingresos</th><th class="text-center">N° Ing.</th>
                            <th class="text-end">Egresos</th><th class="text-center">N° Egr.</th>
                            <th class="text-end pe-3">Neto</th>
                        </tr>
                    </thead>
                    <tbody id="rie-tbody">
                        <tr><td colspan="9" class="text-center py-5 text-muted"><i class="bi bi-funnel fs-3 d-block mb-2"></i>Ajuste los filtros y presione <strong>Generar</strong>.</td></tr>
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

    function filtros() {
        return {
            tipo_flujo: $('rie-tipo-flujo').value,
            ver_por: $('rie-ver-por').value,
            fecha_desde: $('rie-fecha-desde').value,
            fecha_hasta: $('rie-fecha-hasta').value,
            tercero_tipo: $('rie-tercero-tipo').value,
            tercero_id: $('rie-tercero-id').value || 0,
            id_forma: $('rie-forma').value,
            operacion_bancaria: $('rie-opbanc').value,
            id_concepto: $('rie-concepto').value,
            estado: $('rie-estado').value,
            tipo_documento: $('rie-tipo-doc').value,
            monto_min: $('rie-monto-min').value,
            monto_max: $('rie-monto-max').value,
            buscar: $('rie-buscar').value.trim(),
        };
    }

    async function generar() {
        const f = filtros();
        const tbody = $('rie-tbody');
        const vp = f.ver_por;
        $('rie-head-detalle').classList.toggle('d-none', vp !== 'DETALLE');
        $('rie-head-tercero').classList.toggle('d-none', vp !== 'TERCERO');
        $('rie-head-forma').classList.toggle('d-none', vp !== 'FORMA');
        const esPeriodo = (vp === 'FECHA' || vp === 'MES');
        $('rie-head-periodo').classList.toggle('d-none', !esPeriodo);
        if (esPeriodo) $('rie-th-periodo').textContent = (vp === 'MES') ? 'Mes' : 'Fecha';
        tbody.innerHTML = `<tr><td colspan="10" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Generando…</td></tr>`;
        $('rieBtnPdf').disabled = true; $('rieBtnExcel').disabled = true;

        try {
            const params = new URLSearchParams(f);
            const res = await fetch(`${BASE}/${RUTA}/generarAjax?${params.toString()}`);
            const json = await res.json();
            if (!json.ok) { tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4">${json.mensaje || 'Error'}</td></tr>`; return; }

            tbody.innerHTML = json.rows;
            $('rie-kpi-ing').textContent  = money(json.stats.total_ingresos);
            $('rie-kpi-egr').textContent  = money(json.stats.total_egresos);
            $('rie-kpi-neto').textContent = money(json.stats.neto);
            $('rie-n-ing').textContent = '(' + json.stats.n_ingresos + ')';
            $('rie-n-egr').textContent = '(' + json.stats.n_egresos + ')';
            $('rie-kpi-neto').className = 'fs-5 fw-bold ' + (json.stats.neto >= 0 ? 'text-primary' : 'text-danger');

            $('rieBtnPdf').disabled = json.total === 0;
            $('rieBtnExcel').disabled = json.total === 0;
            $('rieBtnPdf').onclick   = () => window.open(json.pdf_url, '_blank');
            $('rieBtnExcel').onclick = () => window.open(json.excel_url, '_blank');
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4">Error de comunicación.</td></tr>`;
        }
    }

    // Autocomplete de tercero (según tipo). Requiere elegir un tipo distinto de "Todos".
    let _t = null;
    $('rie-tercero-txt').addEventListener('input', function () {
        const q = this.value.trim();
        $('rie-tercero-id').value = '';
        const tipo = $('rie-tercero-tipo').value;
        const drop = $('rie-tercero-drop');
        if (!tipo) { drop.innerHTML = '<div class="list-group-item small text-muted">Elija primero el tipo de tercero.</div>'; drop.classList.remove('d-none'); return; }
        if (q.length < 2) { drop.classList.add('d-none'); return; }
        clearTimeout(_t);
        _t = setTimeout(async () => {
            const res = await fetch(`${BASE}/${RUTA}/buscarTercerosAjax?tipo=${tipo}&q=${encodeURIComponent(q)}`);
            const json = await res.json();
            drop.innerHTML = '';
            if (!json.data || !json.data.length) { drop.innerHTML = '<div class="list-group-item small text-muted">Sin resultados.</div>'; }
            else json.data.forEach(t => {
                const b = document.createElement('button');
                b.type = 'button'; b.className = 'list-group-item list-group-item-action small';
                b.innerHTML = `<strong>${t.nombre}</strong> <span class="text-muted">${t.ident || ''}</span>`;
                b.onclick = () => { $('rie-tercero-txt').value = t.nombre; $('rie-tercero-id').value = t.id; drop.classList.add('d-none'); };
                drop.appendChild(b);
            });
            drop.classList.remove('d-none');
        }, 300);
    });
    document.addEventListener('click', e => { if (!e.target.closest('#rie-tercero-txt') && !e.target.closest('#rie-tercero-drop')) $('rie-tercero-drop').classList.add('d-none'); });
    $('rie-tercero-tipo').addEventListener('change', () => { $('rie-tercero-txt').value = ''; $('rie-tercero-id').value = ''; });

    $('rieBtnGenerar').addEventListener('click', generar);
    $('rie-buscar').addEventListener('keydown', e => { if (e.key === 'Enter') generar(); });
    $('rieBtnLimpiar').addEventListener('click', () => {
        ['rie-tercero-txt','rie-tercero-id','rie-monto-min','rie-monto-max','rie-buscar'].forEach(id => $(id).value = '');
        $('rie-fecha-desde').value = '<?= date('Y-m-01') ?>';
        $('rie-fecha-hasta').value = '<?= date('Y-m-d') ?>';
        $('rie-tipo-flujo').value = 'AMBOS'; $('rie-ver-por').value = 'DETALLE'; $('rie-tercero-tipo').value = '';
        $('rie-forma').value = '0'; $('rie-opbanc').value = ''; $('rie-concepto').value = '0'; $('rie-tipo-doc').value = ''; $('rie-estado').value = 'TODOS';
        generar();
    });

    generar(); // primera carga
})();
</script>
