<?php
/**
 * Declaración de Impuesto a la Renta — formulario 101 (sociedades) / 102 (personas naturales).
 * Página con tarjeta de control fija (año + ajustes) y pestañas de resultado: la página
 * completa hace scroll, por eso se desactiva el app-shell (§9).
 */
$idModulo = basename($rutaModulo);
$ctx = $contexto;
$emp = $ctx['empresa'];
$esPn = $ctx['tipo'] === 'pn';
$esPnoc = $ctx['tipo'] === 'pnoc';
$esSoc = $ctx['tipo'] === 'soc';
?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<style>
    #form-filtros-renta .form-select,
    #form-filtros-renta .form-control,
    #form-filtros-renta .input-group-text,
    #form-filtros-renta .btn { height:28px; font-size:.75rem; }
    #form-filtros-renta label { font-size:.65rem; }
    .renta-scroll { overflow-x:auto; }
    .renta-scroll thead th { background:#f8f9fa; box-shadow:0 1px 0 #dee2e6; white-space:nowrap; font-size:.72rem; }
    .renta-tabla td { font-size:.78rem; padding:3px 8px; vertical-align:middle; }
    .renta-tabla tr.renta-sec td { background:#dfe7f3; font-weight:700; font-size:.72rem; text-transform:uppercase; }
    .renta-tabla tr.renta-tot td { background:#f5f5f5; font-weight:700; }
    .renta-tabla tr.renta-sub td { color:#6c757d; font-size:.74rem; }
    .renta-tabla tr.renta-sub td:first-child { padding-left:28px; white-space:pre; }
    .renta-tabla input.renta-sri { width:58px; height:22px; font-size:.72rem; padding:0 4px; display:inline-block; text-align:center; font-family:monospace; }
    .renta-tabla select.renta-rubro { height:26px; font-size:.75rem; padding:0 6px; }
    .renta-tabla td.cas { color:#6c757d; font-family:monospace; text-align:center; white-space:nowrap; }
    .renta-tabla td.signo { text-align:center; color:#6c757d; width:30px; }
    .renta-tabla td.val { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
    .renta-tabla .nota { color:#6c757d; font-size:.68rem; }
    .renta-tabla input.renta-edit { width:120px; text-align:right; height:24px; font-size:.75rem; padding:0 6px; display:inline-block; }
    .nav-tabs .nav-link { font-weight:700; font-size:.78rem; color:#555; }
    .nav-tabs .nav-link.active { color:#0d6efd; border-bottom:2px solid #0d6efd; }
    .renta-kpi { font-size:1rem; }
    .renta-cuentas { color:#6c757d; font-size:.7rem; }
    @media (max-width: 767.98px) {
        #modulo-declaracion-renta .renta-scroll { max-height:none !important; height:auto !important; overflow-y:visible !important; }
    }
</style>

<div class="container-fluid pt-0 pb-3 px-0 px-md-3" id="modulo-<?= htmlspecialchars($idModulo) ?>">

    <!-- ── Tarjeta de control fija ── -->
    <div class="card cmg-control-card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-header bg-white border-bottom py-2 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0 fw-bold"><i class="bi bi-file-earmark-bar-graph me-2 text-primary"></i>Declaración de Impuesto a la Renta
                <span class="badge bg-primary bg-opacity-10 text-primary ms-2">Formulario <?= htmlspecialchars($ctx['formulario']) ?></span>
            </h5>
            <div class="small text-muted">
                <?= htmlspecialchars($ctx['tipo_nombre']) ?><?= $ctx['regimen'] ? ' · Régimen ' . htmlspecialchars($ctx['regimen']) : '' ?>
                · RUC <?= htmlspecialchars((string) ($emp['ruc'] ?? '')) ?>
                <?php if (!empty($ctx['grupo']['consolidado'])): ?>
                    · <span class="badge bg-info bg-opacity-10 text-info" title="<?= htmlspecialchars(implode(' | ', $ctx['grupo']['etiquetas'])) ?>"><i class="bi bi-diagram-3 me-1"></i>Consolidado: <?= count($ctx['grupo']['ids']) ?> establecimientos del RUC</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body p-3">
            <form id="form-filtros-renta" class="d-flex flex-wrap align-items-start gap-2" onsubmit="event.preventDefault(); RENTA_calcular();">
                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase">Ejercicio</label>
                    <select id="renta-anio" name="anio" class="form-select form-select-sm shadow-none border" style="width:100px;">
                        <?php foreach ($anios as $a): ?>
                            <option value="<?= (int) $a ?>" <?= (int) $a === (int) $anio ? 'selected' : '' ?>><?= (int) $a ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if (!$esSoc): ?>
                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" title="Cargas familiares para el tope de gastos personales">Cargas familiares</label>
                    <select id="renta-cargas" name="cargas_familiares" class="form-select form-select-sm shadow-none border" style="width:120px;">
                        <?php for ($i = 0; $i <= 5; $i++): ?>
                            <option value="<?= $i ?>"><?= $i === 5 ? '5 o más' : $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" title="Discapacidad o enfermedad catastrófica (tope especial)">Caso especial</label>
                    <select id="renta-especial" name="caso_especial" class="form-select form-select-sm shadow-none border" style="width:90px;">
                        <option value="0">No</option>
                        <option value="1">Sí</option>
                    </select>
                </div>
                <?php endif; ?>

                <?php if ($esPnoc): ?>
                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" title="Con qué datos se calcula la liquidación">Base de cálculo</label>
                    <select id="renta-fuente" name="fuente" class="form-select form-select-sm shadow-none border" style="width:150px;">
                        <option value="documentos">Documentos</option>
                        <option value="contabilidad">Contabilidad</option>
                    </select>
                </div>
                <?php endif; ?>

                <?php if ($esSoc || $esPnoc): ?>
                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" title="Participación a trabajadores">Part. trabajadores %</label>
                    <input type="number" id="renta-participacion" name="participacion_trabajadores_pct" class="form-control form-control-sm shadow-none border" style="width:110px;" value="15" min="0" max="100" step="0.01">
                </div>
                <?php endif; ?>
                <?php if ($esSoc): ?>
                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" title="Tarifa de impuesto a la renta de sociedades">Tarifa IR %</label>
                    <input type="number" id="renta-tarifa" name="tarifa_pct" class="form-control form-control-sm shadow-none border" style="width:90px;" value="25" min="0" max="100" step="0.01">
                </div>
                <?php endif; ?>

                <div class="d-flex flex-wrap align-items-start gap-2">
                    <div>
                        <label class="form-label small fw-bold mb-1 d-block">&nbsp;</label>
                        <div class="d-flex gap-1 flex-wrap">
                            <button type="submit" class="btn btn-primary btn-sm px-3" id="renta-btn-mostrar"><i class="bi bi-search me-1"></i>Mostrar</button>
                            <button type="button" class="btn btn-outline-danger btn-sm" title="Descargar PDF" onclick="RENTA_exportar('pdf')"><i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span></button>
                            <button type="button" class="btn btn-outline-success btn-sm" title="Descargar Excel" onclick="RENTA_exportar('excel')"><i class="bi bi-file-earmark-spreadsheet"></i><span class="d-none d-md-inline"> Excel</span></button>
                            <?php if (!$esPn): ?>
                            <button type="button" class="btn btn-outline-primary btn-sm" title="Descargar el XML de casilleros para cargar en el portal del SRI" onclick="RENTA_exportar('xml')"><i class="bi bi-file-earmark-code"></i><span class="d-none d-md-inline"> Renta SRI</span></button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </form>
            <div id="renta-avisos" class="mt-2"></div>
        </div>
        <div class="card-footer bg-white border-top py-2 px-3">
            <div class="cmg-control-card__stats">
                <div class="cmg-control-card__stat">
                    <span class="rounded-circle d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success" style="width:28px;height:28px;"><i class="bi bi-arrow-down-left-circle"></i></span>
                    <div><div class="cmg-control-card__stat-value renta-kpi" id="kpi-ingresos">0.00</div><div class="cmg-control-card__stat-label">Ingresos gravados</div></div>
                </div>
                <div class="cmg-control-card__stat">
                    <span class="rounded-circle d-inline-flex align-items-center justify-content-center bg-danger bg-opacity-10 text-danger" style="width:28px;height:28px;"><i class="bi bi-arrow-up-right-circle"></i></span>
                    <div><div class="cmg-control-card__stat-value renta-kpi" id="kpi-gastos">0.00</div><div class="cmg-control-card__stat-label">Costos y gastos</div></div>
                </div>
                <div class="cmg-control-card__stat">
                    <span class="rounded-circle d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary" style="width:28px;height:28px;"><i class="bi bi-calculator"></i></span>
                    <div><div class="cmg-control-card__stat-value renta-kpi" id="kpi-base">0.00</div><div class="cmg-control-card__stat-label">Base imponible</div></div>
                </div>
                <div class="cmg-control-card__stat">
                    <span class="rounded-circle d-inline-flex align-items-center justify-content-center bg-warning bg-opacity-10 text-warning" style="width:28px;height:28px;"><i class="bi bi-percent"></i></span>
                    <div><div class="cmg-control-card__stat-value renta-kpi" id="kpi-causado">0.00</div><div class="cmg-control-card__stat-label">Impuesto causado</div></div>
                </div>
                <div class="cmg-control-card__stat">
                    <span class="rounded-circle d-inline-flex align-items-center justify-content-center bg-info bg-opacity-10 text-info" style="width:28px;height:28px;"><i class="bi bi-receipt"></i></span>
                    <div><div class="cmg-control-card__stat-value renta-kpi" id="kpi-retenciones">0.00</div><div class="cmg-control-card__stat-label">Retenciones que le hicieron</div></div>
                </div>
                <div class="cmg-control-card__stat">
                    <span class="rounded-circle d-inline-flex align-items-center justify-content-center bg-dark bg-opacity-10 text-dark" style="width:28px;height:28px;"><i class="bi bi-cash-coin"></i></span>
                    <div><div class="cmg-control-card__stat-value renta-kpi" id="kpi-pagar">0.00</div><div class="cmg-control-card__stat-label" id="kpi-pagar-label">Impuesto a pagar</div></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Resultado ── -->
    <ul class="nav nav-tabs border-bottom-0 flex-grow-1" id="renta-tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-liquidacion" type="button" role="tab"><i class="bi bi-calculator me-1"></i>Liquidación del impuesto</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-resumen" type="button" role="tab"><i class="bi bi-list-check me-1"></i>Resumen de documentos</button>
        </li>
        <?php if (!$esSoc): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-clasificar" type="button" role="tab"><i class="bi bi-tags me-1"></i>Clasificar gastos personales <span class="badge bg-danger ms-1 d-none" id="renta-badge-sin-rubro">0</span></button>
        </li>
        <?php endif; ?>
        <?php if (!$esPn): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-casilleros" type="button" role="tab"><i class="bi bi-grid-3x3 me-1"></i>Casilleros (contabilidad)</button>
        </li>
        <?php endif; ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-detalle" type="button" role="tab"><i class="bi bi-files me-1"></i>Detalle de documentos</button>
        </li>
    </ul>

    <div class="tab-content border bg-white rounded-bottom shadow-sm" id="renta-tab-content">
        <div class="tab-pane fade show active" id="tab-liquidacion" role="tabpanel">
            <div class="renta-scroll">
                <table class="table table-sm table-hover mb-0 renta-tabla">
                    <thead><tr><th>Concepto</th><th class="text-center">Cas.</th><th class="text-center">+/−</th><th class="text-end">Valor</th></tr></thead>
                    <tbody id="renta-liquidacion"><tr><td colspan="4" class="text-center text-muted py-4">Presione <strong>Mostrar</strong> para calcular la declaración.</td></tr></tbody>
                </table>
            </div>
            <div class="px-3 py-2 small text-muted border-top">
                Los campos con fondo amarillo son valores que el sistema no conoce (anticipos, créditos de años anteriores, gastos no deducibles…): escríbalos y presione <strong>Mostrar</strong> para recalcular. Este reporte es un apoyo para preparar la declaración; no reemplaza la presentada en el portal del SRI.
            </div>
        </div>

        <div class="tab-pane fade" id="tab-resumen" role="tabpanel">
            <div class="renta-scroll">
                <table class="table table-sm table-hover mb-0 renta-tabla">
                    <thead><tr><th>Bloque</th><th class="text-center">Documentos</th><th class="text-end">Base (sin IVA)</th><th class="text-end">Total (con IVA)</th></tr></thead>
                    <tbody id="renta-resumen"></tbody>
                </table>
            </div>
        </div>

        <?php if (!$esSoc): ?>
        <div class="tab-pane fade" id="tab-clasificar" role="tabpanel">
            <div class="p-2 border-bottom d-flex align-items-center gap-2 flex-wrap">
                <label class="small fw-bold mb-0">Mostrar</label>
                <select id="renta-clasificar-filtro" class="form-select form-select-sm" style="width:220px;height:28px;font-size:.75rem;" onchange="RENTA_clasificar()">
                    <option value="sin_rubro">Solo sin rubro</option>
                    <option value="todas">Todas las de gasto personal</option>
                </select>
                <span class="small text-muted" id="renta-clasificar-resumen"></span>
                <span class="small text-muted ms-auto">Elija el rubro en cada fila; se guarda al instante. <strong>Aplicar al proveedor</strong> pone ese rubro a todas las compras sin rubro del mismo proveedor en el ejercicio.</span>
            </div>
            <div class="renta-scroll">
                <table class="table table-sm table-hover mb-0 renta-tabla">
                    <thead><tr><?php if (!empty($ctx['grupo']['consolidado'])): ?><th>Establecimiento</th><?php endif; ?><th>Fecha</th><th>Tipo</th><th>Número</th><th>Proveedor</th><th>Identificación</th><th class="text-end">Total</th><th style="width:230px;">Rubro</th><th></th></tr></thead>
                    <tbody id="renta-clasificar"><tr><td colspan="9" class="text-center text-muted py-3">Presione Mostrar para cargar.</td></tr></tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$esPn): ?>
        <div class="tab-pane fade" id="tab-casilleros" role="tabpanel">
            <div class="renta-scroll">
                <table class="table table-sm table-hover mb-0 renta-tabla">
                    <thead><tr><th class="text-center">Casillero</th><th>Sección</th><th>Cuentas del plan (código SRI)</th><th class="text-end">Valor</th></tr></thead>
                    <tbody id="renta-casilleros"></tbody>
                </table>
            </div>
            <div class="px-3 py-2 small text-muted border-top">
                Escriba el número de casillero en la casilla de cada cuenta y presione Enter (o salga del campo) para guardarlo; el reporte se recalcula solo. Solo entran los asientos contabilizados del ejercicio.
            </div>
        </div>
        <?php endif; ?>

        <div class="tab-pane fade" id="tab-detalle" role="tabpanel">
            <div class="p-2 border-bottom d-flex align-items-center gap-2 flex-wrap">
                <label class="small fw-bold mb-0">Fuente</label>
                <select id="renta-detalle-fuente" class="form-select form-select-sm" style="width:320px;height:28px;font-size:.75rem;" onchange="RENTA_detalle()"></select>
                <span class="small text-muted" id="renta-detalle-resumen"></span>
            </div>
            <div class="renta-scroll">
                <table class="table table-sm table-hover mb-0 renta-tabla">
                    <thead><tr><?php if (!empty($ctx['grupo']['consolidado'])): ?><th>Establecimiento</th><?php endif; ?><th>Fecha</th><th>Tipo</th><th>Rubro</th><th>Número</th><th>Tercero</th><th>Identificación</th><th class="text-end">Base (sin IVA)</th><th class="text-end">Total</th></tr></thead>
                    <tbody id="renta-detalle"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    window.RENTA_CFG = {
        base: <?= json_encode($base) ?>,
        ruta: <?= json_encode($rutaModulo) ?>,
        tipo: <?= json_encode($ctx['tipo']) ?>,
        consolidado: <?= !empty($ctx['grupo']['consolidado']) ? 'true' : 'false' ?>,
        puedeEditar: <?= !empty($perm['actualizar']) ? 'true' : 'false' ?>,
        rubros: <?= json_encode(\App\Helpers\RubrosGastoPersonal::CATALOGO, JSON_UNESCAPED_UNICODE) ?>,
        fuentes: <?= json_encode(\App\Services\modulos\DeclaracionRentaService::FUENTES_DETALLE, JSON_UNESCAPED_UNICODE) ?>
    };
</script>
<script src="<?= $base ?>/js/modulos/declaracion_renta.js?v=<?= asset_ver('/js/modulos/declaracion_renta.js') ?>"></script>
