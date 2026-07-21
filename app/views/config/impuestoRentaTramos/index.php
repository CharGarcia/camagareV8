<?php
/** @var string $titulo */
/** @var int $anio */
/** @var array $tramos */
/** @var float $gastoPersonalMaximo */
/** @var array $aniosConfigurados */
$base = BASE_URL;
$msg = $_SESSION['config_msg'] ?? null;
unset($_SESSION['config_msg']);
$anioActual = (int) date('Y');
$hayTramos = !empty($tramos);
?>
<script>document.body.classList.add('cmg-no-app-shell');</script>
<style>
.irt-wrap { max-width: 1400px; margin: 0 auto; }
.irt-scroll { max-height: calc(100dvh - 340px); overflow-y: auto; }
.irt-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
.irt-row:hover { background-color: rgba(0,0,0,.035); }
.irt-kpi { border: 1px solid #eef0f2; border-radius: .5rem; padding: .6rem .9rem; background: #fff; }
.irt-kpi .val { font-size: 1.05rem; font-weight: 700; line-height: 1.1; }
.irt-kpi .lbl { font-size: .68rem; text-transform: uppercase; letter-spacing: .03em; color: #8a94a6; font-weight: 600; }
</style>

<div class="irt-wrap">
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-percent text-primary"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">Catálogo global (no varía por empresa). Cargar aquí la tabla oficial que publica el SRI cada año antes de que el rol de pagos calcule retención de IR a empleados en relación de dependencia.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
        <button type="button" class="btn btn-primary btn-sm" onclick="abrirModalCrear()"><i class="bi bi-plus-lg"></i> Nuevo tramo</button>
    </div>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= htmlspecialchars($msg[0]) ?> alert-dismissible fade show py-2 small" role="alert">
    <?= htmlspecialchars($msg[1]) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-3">
    <form method="GET" action="<?= $base ?>/config/impuesto-renta-tramos" class="d-flex flex-wrap align-items-end gap-2">
        <div>
            <label class="form-label small fw-bold text-muted mb-1">Año</label>
            <input type="number" name="anio" value="<?= $anio ?>" class="form-control form-control-sm" style="width:110px" min="2000" max="2100">
        </div>
        <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-search"></i> Ver</button>
        <?php if (!empty($aniosConfigurados)): ?>
        <div class="ms-2 d-flex align-items-center flex-wrap gap-1">
            <span class="small text-muted me-1">Años configurados:</span>
            <?php foreach ($aniosConfigurados as $a): ?>
                <a href="<?= $base ?>/config/impuesto-renta-tramos?anio=<?= $a ?>"
                   class="badge <?= (int) $a === $anio ? 'bg-primary' : 'bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25' ?> text-decoration-none">
                   <?= $a ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </form>

    <div class="d-flex gap-2">
        <div class="irt-kpi text-center">
            <div class="val text-primary"><?= count($tramos) ?></div>
            <div class="lbl">Tramos <?= $anio ?></div>
        </div>
        <div class="irt-kpi text-center">
            <div class="val">$<?= number_format($gastoPersonalMaximo, 2) ?></div>
            <div class="lbl">Gasto personal</div>
        </div>
    </div>
</div>

<?php if ($anio == $anioActual && !$hayTramos): ?>
<div class="alert alert-warning small py-2">
    <i class="bi bi-exclamation-triangle me-1"></i>No hay tramos cargados para <?= $anio ?> (año en curso). Mientras esta tabla esté vacía, el rol de pagos calculará $0.00 de retención de Impuesto a la Renta para todos los empleados.
</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card cmg-table-card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="irt-scroll">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Orden</th>
                                <th class="text-end">Fracción básica (desde)</th>
                                <th class="text-end">Exceso hasta</th>
                                <th class="text-end">Impuesto fracción básica</th>
                                <th class="text-end">% sobre excedente</th>
                                <th class="text-center pe-3">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tramos as $t): ?>
                            <tr class="irt-row">
                                <td class="ps-3"><span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25"><?= (int) $t['orden'] ?></span></td>
                                <td class="text-end font-monospace">$<?= number_format((float) $t['fraccion_basica'], 2) ?></td>
                                <td class="text-end font-monospace"><?= $t['exceso_hasta'] !== null ? '$' . number_format((float) $t['exceso_hasta'], 2) : '<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">en adelante</span>' ?></td>
                                <td class="text-end font-monospace">$<?= number_format((float) $t['impuesto_fraccion_basica'], 2) ?></td>
                                <td class="text-end"><span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25"><?= number_format((float) $t['porcentaje_excedente'], 2) ?>%</span></td>
                                <td class="text-center pe-3">
                                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-1 border-0" title="Editar"
                                        onclick='abrirModalEditar(<?= json_encode($t, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <a href="<?= $base ?>/config/impuesto-renta-tramos-delete?id=<?= (int) $t['id'] ?>&anio=<?= $anio ?>"
                                       class="btn btn-sm btn-outline-danger py-0 px-1 border-0" title="Eliminar"
                                       onclick="return confirm('¿Eliminar este tramo?')"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (!$hayTramos): ?>
                            <tr><td colspan="6" class="text-center text-muted py-5">
                                <i class="bi bi-inbox d-block mb-2" style="font-size:1.5rem;"></i>
                                No hay tramos configurados para <?= $anio ?>.
                            </td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card cmg-table-card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-2">
                <h6 class="fw-bold small text-uppercase text-muted mb-0"><i class="bi bi-wallet2 me-1"></i>Gasto personal deducible (<?= $anio ?>)</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="<?= $base ?>/config/impuesto-renta-tramos-guardar-parametros">
                    <input type="hidden" name="anio" value="<?= $anio ?>">
                    <label class="form-label small mb-1">Tope de gasto personal máximo deducible (anual)</label>
                    <div class="input-group input-group-sm mb-2">
                        <span class="input-group-text">$</span>
                        <input type="number" step="0.01" name="gasto_personal_maximo" class="form-control" value="<?= number_format($gastoPersonalMaximo, 2, '.', '') ?>">
                    </div>
                    <button type="submit" class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-check-lg"></i> Guardar</button>
                </form>
                <hr class="my-3">
                <p class="text-muted small mb-0"><i class="bi bi-info-circle me-1"></i>Se resta del ingreso gravado anual proyectado antes de aplicar la tabla de tramos (método simplificado, ver <code>ImpuestoRentaEmpleadoService</code>). Dejar en $0.00 si no aplica deducción.</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tramo -->
<div class="modal fade" id="modalTramo" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light border-bottom-0">
                <h5 class="modal-title fw-bold" id="modalTramoTitle"><i class="bi bi-plus-circle"></i> Nuevo tramo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?= $base ?>/config/impuesto-renta-tramos-store">
                <div class="modal-body">
                    <input type="hidden" name="anio" value="<?= $anio ?>">
                    <div class="mb-2">
                        <label class="form-label small fw-bold text-muted mb-1">Orden (1, 2, 3... según la tabla oficial)</label>
                        <input type="number" class="form-control form-control-sm" name="orden" id="t_orden" required min="1">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold text-muted mb-1">Fracción básica (desde)</label>
                        <input type="number" step="0.01" class="form-control form-control-sm" name="fraccion_basica" id="t_fraccion_basica" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold text-muted mb-1">Exceso hasta (vacío = último tramo, "en adelante")</label>
                        <input type="number" step="0.01" class="form-control form-control-sm" name="exceso_hasta" id="t_exceso_hasta">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold text-muted mb-1">Impuesto de la fracción básica</label>
                        <input type="number" step="0.01" class="form-control form-control-sm" name="impuesto_fraccion_basica" id="t_impuesto_fraccion_basica" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small fw-bold text-muted mb-1">% sobre excedente</label>
                        <input type="number" step="0.01" class="form-control form-control-sm" name="porcentaje_excedente" id="t_porcentaje_excedente" required>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
</div>

<script>
function abrirModalCrear() {
    document.getElementById('modalTramoTitle').innerHTML = '<i class="bi bi-plus-circle"></i> Nuevo tramo';
    document.getElementById('t_orden').value = '';
    document.getElementById('t_fraccion_basica').value = '';
    document.getElementById('t_exceso_hasta').value = '';
    document.getElementById('t_impuesto_fraccion_basica').value = '';
    document.getElementById('t_porcentaje_excedente').value = '';
    new bootstrap.Modal(document.getElementById('modalTramo')).show();
}
function abrirModalEditar(t) {
    document.getElementById('modalTramoTitle').innerHTML = '<i class="bi bi-pencil"></i> Editar tramo';
    document.getElementById('t_orden').value = t.orden;
    document.getElementById('t_fraccion_basica').value = t.fraccion_basica;
    document.getElementById('t_exceso_hasta').value = t.exceso_hasta ?? '';
    document.getElementById('t_impuesto_fraccion_basica').value = t.impuesto_fraccion_basica;
    document.getElementById('t_porcentaje_excedente').value = t.porcentaje_excedente;
    new bootstrap.Modal(document.getElementById('modalTramo')).show();
}
</script>
