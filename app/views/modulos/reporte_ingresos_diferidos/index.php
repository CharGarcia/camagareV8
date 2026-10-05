<?php /** @var string $rutaModulo @var string $mesDefecto @var string $mesMaximo @var array $perm @var array $vistaConfig @var string $base */
$idModulo = basename($rutaModulo);
$columnasTabla = [
    'cliente'      => 'Cliente',
    'ruc'          => 'RUC/Cédula',
    'suscripcion'  => 'Suscripción',
    'documento'    => 'Documento',
    'fecha'        => 'Fecha',
    'ultimo_mes'   => 'Último mes',
    'corriente'    => 'Corriente',
    'no_corriente' => 'No corriente',
    'por_facturar' => 'Por facturar',
];
?>
<script>document.body.classList.add('cmg-no-app-shell');</script>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<style>
    .rid-scroll { overflow-x:auto; }
    .rid-scroll thead th { background:#f8f9fa; box-shadow:0 1px 0 #dee2e6; white-space:nowrap; }
    .rid-scroll thead th[data-orden] { cursor:pointer; user-select:none; }
    .rid-scroll tbody tr[data-doc-id] { cursor:pointer; }
    .rid-scroll td { font-size:.8rem; white-space:nowrap; }
    .rid-scroll td[data-col="cliente"] { max-width:320px; overflow:hidden; text-overflow:ellipsis; }
    .rid-total td { font-weight:700; background:#f8f9fa; }
    #rid-chips-cliente:empty { margin-top:0; }
    /* Altura idéntica y explícita para todos los controles de filtros */
    #form-filtros-rid .form-select,
    #form-filtros-rid .form-control,
    #form-filtros-rid .input-group-text,
    #form-filtros-rid .btn { height:28px; font-size:.75rem; }
    /* La tabla se extiende libremente hacia abajo; hace scroll la página, no un contenedor interno */
    @media (max-width: 767.98px) {
        #modulo-<?= $idModulo ?> .rid-scroll { max-height:none !important; height:auto !important; overflow-y:visible !important; }
    }
</style>

<div class="container-fluid pt-0 pb-3 px-0 px-md-3" id="modulo-<?= $idModulo ?>">

    <!-- ── Tarjeta de control fija (título + filtros + KPIs) ── -->
    <div class="card cmg-control-card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-header bg-white border-bottom py-2 px-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-hourglass-split me-2 text-primary"></i>Ingresos Diferidos</h5>
        </div>
        <div class="card-body p-3">
            <form id="form-filtros-rid" onsubmit="event.preventDefault(); RID_cargar();" class="d-flex flex-wrap align-items-start gap-2">

                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;" for="rid-mes">Saldos al cierre de</label>
                    <input type="month" id="rid-mes" name="mes" class="form-control form-control-sm shadow-none border" style="width:140px;"
                           value="<?= htmlspecialchars($mesDefecto) ?>" max="<?= htmlspecialchars($mesMaximo) ?>">
                </div>

                <!-- Cliente + Botones: agrupados para que nunca se separen al hacer wrap -->
                <div class="d-flex flex-wrap align-items-start gap-2">
                    <div class="position-relative" style="width:440px;">
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;" for="rid-search-cliente">Cliente</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" id="rid-search-cliente" class="form-control border-start-0 px-1 shadow-none"
                                   placeholder="Nombre / RUC…" autocomplete="off">
                            <input type="hidden" id="rid-id-cliente" name="id_cliente" value="">
                        </div>
                        <div id="rid-chips-cliente" class="d-flex flex-wrap gap-1 mt-1"></div>
                        <div id="rid-dropdown-clientes" class="list-group shadow position-absolute d-none"
                             style="z-index:1050;width:100%;max-height:220px;overflow-y:auto;margin-top:2px;"></div>
                    </div>
                    <div>
                        <label class="form-label small fw-bold mb-1 d-block" style="font-size:.65rem;">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm px-2" onclick="RID_limpiarFiltros()">
                                <i class="bi bi-eraser me-1"></i>Limpiar
                            </button>
                            <button type="submit" class="btn btn-primary btn-sm px-3 shadow-sm">
                                <i class="bi bi-search me-1"></i>Mostrar
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-footer bg-white border-top py-2 px-3">
            <div class="cmg-control-card__stats">
                <div class="cmg-control-card__stat">
                    <i class="bi bi-hourglass-split bg-primary bg-opacity-10 text-primary"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-primary" id="rid-kpi-corriente">$0.00</div>
                        <div class="cmg-control-card__stat-label">Diferido corriente (12 meses)</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-calendar-range bg-secondary bg-opacity-10 text-secondary"></i>
                    <div>
                        <div class="cmg-control-card__stat-value" id="rid-kpi-no-corriente">$0.00</div>
                        <div class="cmg-control-card__stat-label">Diferido no corriente</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-receipt bg-success bg-opacity-10 text-success"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-success" id="rid-kpi-por-facturar">$0.00</div>
                        <div class="cmg-control-card__stat-label">Devengado por facturar</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-files bg-secondary bg-opacity-10 text-secondary"></i>
                    <div>
                        <div class="cmg-control-card__stat-value" id="rid-kpi-documentos">0</div>
                        <div class="cmg-control-card__stat-label">Documentos</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-people bg-secondary bg-opacity-10 text-secondary"></i>
                    <div>
                        <div class="cmg-control-card__stat-value" id="rid-kpi-clientes">0</div>
                        <div class="cmg-control-card__stat-label">Clientes</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Conciliación con el mayor ── -->
    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-header bg-white py-2 px-3 border-bottom">
            <h6 class="mb-0 fw-bold small"><i class="bi bi-check2-square me-1 text-primary"></i>Conciliación con el mayor</h6>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0 small align-middle">
                <thead class="table-light">
                    <tr><th class="ps-3">Concepto</th><th>Cuenta</th><th class="text-end">Según cronograma</th><th class="text-end">Según mayor</th><th class="text-end pe-3">Diferencia</th></tr>
                </thead>
                <tbody id="rid-conciliacion">
                    <tr><td colspan="5" class="text-center text-muted py-3">—</td></tr>
                </tbody>
            </table>
            <div id="rid-conciliacion-nota" class="text-muted px-3 py-2 border-top d-none" style="font-size:.75rem;"></div>
        </div>
    </div>

    <!-- ── Tabla ── -->
    <div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-2 px-3 border-bottom">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h6 class="mb-0 fw-bold small me-2">Detalle por documento</h6>
                    <div class="btn-group btn-group-sm">
                        <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>
                        <button type="button" class="btn btn-outline-danger" id="ridBtnPdf" disabled><i class="bi bi-file-earmark-pdf"></i> PDF</button>
                        <button type="button" class="btn btn-outline-success" id="ridBtnExcel" disabled><i class="bi bi-file-earmark-spreadsheet"></i> Excel</button>
                        <?php if (!empty($perm['crear']) || !empty($perm['eliminar'])): ?>
                            <button type="button" class="btn btn-outline-primary" onclick="SuscDevengoMes.abrir(document.getElementById('rid-mes').value)"
                                    title="El sistema devenga solo cada día hasta el mes anterior. Aquí puede adelantar un mes (p. ej. el mes en curso) o revertirlo.">
                                <i class="bi bi-calendar-check"></i> Devengo del mes
                            </button>
                            <button type="button" class="btn btn-outline-primary" onclick="SuscAperturaDevengo.abrir()"
                                    title="Facturas de suscripción emitidas antes de activar el devengado: pasar a ingresos diferidos los meses que les faltan">
                                <i class="bi bi-box-arrow-in-right"></i> Apertura
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <small class="text-muted fw-medium" id="rid-count-label"></small>
                    <input type="search" class="form-control form-control-sm shadow-none border" style="width:200px;"
                           id="rid-buscador" placeholder="Filtrar tabla..." aria-label="Filtrar tabla">
                    <div class="btn-group btn-group-sm" id="rid-paginacion">
                        <button type="button" class="btn btn-outline-secondary" data-pag="-1" disabled><i class="bi bi-chevron-left"></i></button>
                        <button type="button" class="btn btn-outline-secondary" data-pag="1" disabled><i class="bi bi-chevron-right"></i></button>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="rid-scroll w-100">
                <table class="table table-hover table-sm mb-0 align-middle" id="tabla-rid" style="min-width:1000px;">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" data-col="cliente" data-orden="cliente">Cliente</th>
                            <th data-col="ruc" data-orden="identificacion">RUC/Cédula</th>
                            <th class="text-center" data-col="suscripcion" data-orden="id_suscripcion">Suscripción</th>
                            <th data-col="documento" data-orden="documento">Documento</th>
                            <th data-col="fecha" data-orden="fecha">Fecha</th>
                            <th data-col="ultimo_mes" data-orden="ultimo_mes">Último mes</th>
                            <th class="text-end" data-col="corriente" data-orden="corriente">Corriente</th>
                            <th class="text-end" data-col="no_corriente" data-orden="no_corriente">No corriente</th>
                            <th class="text-end pe-3" data-col="por_facturar" data-orden="por_facturar">Por facturar</th>
                        </tr>
                    </thead>
                    <tbody id="rid-tbody">
                        <tr><td colspan="9" class="text-center py-5 text-muted">
                            <i class="bi bi-hourglass-split fs-3 d-block mb-2 text-primary opacity-50"></i>
                            Cargando saldos…
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php // Panel lateral con el detalle de la factura/recibo (clic sobre una fila)
require_once MVC_APP . '/views/partials/offcanvas_doc_preview.php'; ?>

<?php // Acciones del contador: adelantar/revertir el devengo de un mes y la apertura.
if (!empty($perm['crear']) || !empty($perm['eliminar'])) {
    include __DIR__ . '/modal_devengo_mes.php';
    include __DIR__ . '/modal_apertura.php';
} ?>

<?= \App\Helpers\PreferenciasHelper::getJavascriptVariables($rutaModulo) ?>
<script>
    const RUTA_MODULO_RID = "<?= $rutaModulo ?>";
    const RID_MES_DEFECTO = "<?= htmlspecialchars($mesDefecto) ?>";
</script>
<script src="<?= $base ?>/js/modulos/reporte_ingresos_diferidos.js?v=<?= asset_ver('/js/modulos/reporte_ingresos_diferidos.js') ?>"></script>
<script>
    // Tras generar/revertir un mes o la apertura, el reporte se recarga con los saldos nuevos.
    document.addEventListener('DOMContentLoaded', () => {
        window.SuscDevengoMes?.alCambiar(() => RID_cargar());
        window.SuscAperturaDevengo?.alCambiar(() => RID_cargar());
    });
</script>
