<?php
/** @var array $perm @var string $rutaModulo @var array $establecimientos @var array $tipos */
$base = BASE_URL;
$rutaUrl = $base . '/' . $rutaModulo;
$multiEst = count($establecimientos) > 1;
?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h4 class="mb-0 fw-bold"><i class="bi bi-shuffle text-primary me-2"></i>Reasignar establecimiento</h4>
        <p class="text-muted mb-0 small">Cambia la sucursal (establecimiento) a la que pertenece un documento ya registrado, en lote y por filtros. No cambia el número del documento. Si el documento tiene contabilidad, pagos o inventario generados, pide confirmación para anularlos antes de reasignar; si tiene una retención de compra asociada, queda bloqueado hasta que se anule desde su propio módulo.</p>
    </div>
</div>

<?php if (!$multiEst): ?>
    <div class="alert alert-info"><i class="bi bi-info-circle me-1"></i>Esta empresa tiene un solo establecimiento, así que no hay a dónde reasignar. Este módulo aplica a empresas con varios establecimientos.</div>
<?php else: ?>

<!-- Filtros -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label small fw-semibold mb-1">Tipo de documento</label>
                <select id="reTipo" class="form-select form-select-sm">
                    <?php foreach ($tipos as $k => $label): ?>
                        <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Año</label>
                <select id="reAnio" class="form-select form-select-sm">
                    <option value="">Todos</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Mes</label>
                <select id="reMes" class="form-select form-select-sm">
                    <option value="">Todos</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Desde</label>
                <input type="date" id="reDesde" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Hasta</label>
                <input type="date" id="reHasta" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Establecimiento origen</label>
                <select id="reEstOrigen" class="form-select form-select-sm">
                    <option value="0">Todos</option>
                    <?php foreach ($establecimientos as $e): ?>
                        <option value="<?= (int) $e['id'] ?>"><?= htmlspecialchars($e['codigo'] . ' - ' . $e['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label small fw-semibold mb-1">Buscar (proveedor/cliente/número)</label>
                <div class="d-flex gap-2">
                    <input type="text" id="reBuscar" class="form-control form-control-sm" placeholder="RUC, nombre o número…">
                    <button class="btn btn-primary btn-sm text-nowrap flex-shrink-0" id="reBtnBuscar"><i class="bi bi-search me-1"></i>Buscar</button>
                </div>
            </div>
            <div class="col-12">
                <span id="reResumen" class="small text-muted"></span>
            </div>
        </div>
    </div>
</div>

<!-- Barra de acción -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body d-flex align-items-center gap-2 flex-wrap">
        <span class="small"><a href="#" id="reSelTodos">Seleccionar todos</a> · <a href="#" id="reSelNinguno">Ninguno</a></span>
        <span class="small text-muted" id="reContadorSel">0 seleccionados</span>
        <div class="ms-auto d-flex align-items-center gap-2">
            <label class="form-label small fw-semibold mb-0 text-nowrap flex-shrink-0">Cambiar a:</label>
            <select id="reEstDestino" class="form-select form-select-sm" style="max-width:260px">
                <option value="">Establecimiento destino…</option>
                <?php foreach ($establecimientos as $e): ?>
                    <option value="<?= (int) $e['id'] ?>"><?= htmlspecialchars($e['codigo'] . ' - ' . $e['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-success btn-sm" id="reBtnReasignar" disabled <?= empty($perm['actualizar']) ? 'title="Sin permiso de actualización"' : '' ?>><i class="bi bi-shuffle me-1"></i>Reasignar seleccionados</button>
        </div>
    </div>
</div>

<!-- Resultados -->
<div class="card border-0 shadow-sm cmg-table-card">
    <div class="card-body p-0">
        <div class="reasignar-scroll" style="max-height:calc(100vh - 360px); overflow:auto;">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light" style="position:sticky;top:0;z-index:1;">
                    <tr>
                        <th style="width:38px"><input type="checkbox" id="reChkAll"></th>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Número</th>
                        <th>Proveedor / Cliente</th>
                        <th class="text-end">Total</th>
                        <th>Establecimiento actual</th>
                    </tr>
                </thead>
                <tbody id="reTbody">
                    <tr><td colspan="7" class="text-center text-muted py-4">Aplica filtros y presiona <b>Buscar</b>.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>window.REASIGNAR_RUTA = '<?= $rutaUrl ?>';</script>
<script src="<?= $base ?>/public/js/modulos/reasignar-establecimiento.js"></script>
<?php endif; ?>
