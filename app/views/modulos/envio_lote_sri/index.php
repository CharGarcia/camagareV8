<?php

/**
 * Envío en lote de comprobantes electrónicos al SRI.
 *
 * @var string $titulo
 * @var array  $perm            ['ver','crear','actualizar','eliminar','todo']
 * @var string $ambienteEmpresa '1' pruebas | '2' producción
 * @var string $rutaModulo
 */
$base = rtrim(BASE_URL ?? '', '/');
$hoy  = date('Y-m-d');
$ambienteEmpresa = ($ambienteEmpresa ?? '1') === '2' ? '2' : '1';
$puedeCrear = !empty($perm['crear']);
?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<style>
    .els-scroll-wrap .envio-lote-sri-scroll { max-height: calc(100vh - 360px); overflow: auto; }
    .els-badge-tipo { font-size: .68rem; }
    #els-tabla td, #els-tabla th { font-size: .8rem; vertical-align: middle; }
    #els-tbody tr:has(.els-row) { cursor: pointer; }
    .els-estado-badge { font-size: .65rem; }
</style>

<div class="container-fluid py-3" id="els-app"
     data-base="<?= htmlspecialchars($base) ?>"
     data-ruta="<?= htmlspecialchars($rutaModulo) ?>"
     data-ambiente="<?= htmlspecialchars($ambienteEmpresa) ?>"
     data-puede-crear="<?= $puedeCrear ? '1' : '0' ?>">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 fw-bold text-dark">
            <i class="bi bi-send-check text-primary me-2"></i><?= htmlspecialchars($titulo) ?>
            <?php if ($ambienteEmpresa === '2'): ?>
                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 ms-2" style="font-size:.7rem;">PRODUCCIÓN</span>
            <?php else: ?>
                <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 ms-2" style="font-size:.7rem;">PRUEBAS</span>
            <?php endif; ?>
        </h5>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="ELS.abrirHistorial()">
            <i class="bi bi-clock-history me-1"></i> Historial de lotes
        </button>
    </div>

    <!-- ── Filtros ─────────────────────────────────────────────────────────── -->
    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-body py-3">
            <div class="row g-2 align-items-end">
                <div class="col-6 col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">Desde</label>
                    <input type="date" id="els-desde" class="form-control form-control-sm" value="<?= $hoy ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">Hasta</label>
                    <input type="date" id="els-hasta" class="form-control form-control-sm" value="<?= $hoy ?>">
                </div>
                <div class="col-8 col-md-4">
                    <label class="form-label small fw-bold text-muted mb-1">Buscar</label>
                    <input type="text" id="els-buscar" class="form-control form-control-sm" placeholder="Número o cliente/proveedor…">
                </div>
                <div class="col-4 col-md-2 d-flex gap-2">
                    <button type="button" class="btn btn-primary btn-sm flex-grow-1" onclick="ELS.buscar()">
                        <i class="bi bi-search me-1"></i> Buscar
                    </button>
                </div>
            </div>

            <div class="row g-2 mt-1">
                <div class="col-12">
                    <label class="form-label small fw-bold text-muted mb-1 d-block">Tipos de comprobante</label>
                    <div class="d-flex flex-wrap gap-3">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input els-tipo" type="checkbox" value="factura_venta" id="els-t-fv" checked>
                            <label class="form-check-label small" for="els-t-fv">Facturas de venta</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input els-tipo" type="checkbox" value="nota_credito" id="els-t-nc" checked>
                            <label class="form-check-label small" for="els-t-nc">Notas de crédito</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input els-tipo" type="checkbox" value="retencion_compra" id="els-t-rc" checked>
                            <label class="form-check-label small" for="els-t-rc">Retenciones de compra</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input els-tipo" type="checkbox" value="liquidacion_compra" id="els-t-lc" checked>
                            <label class="form-check-label small" for="els-t-lc">Liquidaciones de compra</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Barra de selección / acción ─────────────────────────────────────── -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="small text-muted">Seleccionar:</span>
            <div class="btn-group btn-group-sm" role="group">
                <button type="button" class="btn btn-outline-secondary" onclick="ELS.seleccionarN(20)">20</button>
                <button type="button" class="btn btn-outline-secondary" onclick="ELS.seleccionarN(50)">50</button>
                <button type="button" class="btn btn-outline-secondary" onclick="ELS.seleccionarN(100)">100</button>
                <button type="button" class="btn btn-outline-secondary" onclick="ELS.seleccionarTodos()">Todos</button>
                <button type="button" class="btn btn-outline-secondary" onclick="ELS.limpiarSeleccion()">Ninguno</button>
            </div>
            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">
                <span id="els-contador-sel">0</span> seleccionados
            </span>
            <span class="small text-muted">de <span id="els-contador-total">0</span> enviables</span>
        </div>
        <button type="button" class="btn btn-success btn-sm" id="els-btn-enviar" onclick="ELS.enviar()" <?= $puedeCrear ? '' : 'disabled' ?>>
            <i class="bi bi-cloud-upload me-1"></i> Enviar seleccionados al SRI
        </button>
    </div>

    <!-- ── Tabla de enviables ──────────────────────────────────────────────── -->
    <div class="card border-0 shadow-sm rounded-3 cmg-table-card els-scroll-wrap">
        <div class="card-body p-0">
            <div class="envio-lote-sri-scroll">
                <table class="table table-sm table-hover mb-0 align-middle" id="els-tabla">
                    <thead class="table-light" style="position:sticky;top:0;z-index:2;">
                        <tr>
                            <th style="width:36px;" class="text-center">
                                <input type="checkbox" class="form-check-input" id="els-check-all" onclick="ELS.toggleTodos(this.checked)">
                            </th>
                            <th data-col="tipo">Tipo</th>
                            <th data-col="numero">Número</th>
                            <th data-col="fecha">Fecha</th>
                            <th data-col="contraparte">Cliente / Proveedor</th>
                            <th data-col="total" class="text-end">Total</th>
                            <th data-col="estado">Estado</th>
                            <th data-col="ambiente">Ambiente</th>
                        </tr>
                    </thead>
                    <tbody id="els-tbody">
                        <tr><td colspan="8" class="text-center text-muted py-5">
                            <i class="bi bi-search fs-4 d-block mb-2"></i>Usa los filtros y presiona <strong>Buscar</strong>.
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal Progreso del lote ─────────────────────────────────────────────── -->
<div class="modal fade" id="els-modal-progreso" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="bi bi-hourglass-split me-2"></i>Procesando lote <span id="els-lote-id"></span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="progress mb-2" style="height:22px;">
                    <div id="els-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                         role="progressbar" style="width:0%;">0%</div>
                </div>
                <div class="d-flex justify-content-between small text-muted mb-3">
                    <span>Procesados: <strong id="els-p-proc">0</strong> / <span id="els-p-total">0</span></span>
                    <span class="text-success">Autorizados: <strong id="els-p-ok">0</strong></span>
                    <span class="text-danger">Con error: <strong id="els-p-err">0</strong></span>
                    <span class="badge bg-secondary" id="els-p-estado">pendiente</span>
                </div>
                <div class="table-responsive" style="max-height:340px;overflow:auto;">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light" style="position:sticky;top:0;">
                            <tr><th>Número</th><th>Tipo</th><th>Estado</th><th>Detalle</th></tr>
                        </thead>
                        <tbody id="els-progress-items"></tbody>
                    </table>
                </div>
                <p class="small text-muted mt-2 mb-0">
                    <i class="bi bi-info-circle me-1"></i>El proceso corre en segundo plano: puedes cerrar esta ventana o la
                    pestaña y el lote seguirá enviándose. Revisa el avance en el <em>Historial de lotes</em>.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-danger btn-sm" id="els-btn-cancelar" onclick="ELS.cancelar()">
                    <i class="bi bi-x-circle me-1"></i> Cancelar lote
                </button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal Historial de lotes ────────────────────────────────────────────── -->
<div class="modal fade" id="els-modal-historial" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="bi bi-clock-history me-2"></i>Historial de lotes</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0" style="font-size:.8rem;">
                        <thead class="table-light">
                            <tr>
                                <th>#</th><th>Creado</th><th>Usuario</th><th>Ambiente</th>
                                <th>Total</th><th>Autorizados</th><th>Errores</th><th>Estado</th><th></th>
                            </tr>
                        </thead>
                        <tbody id="els-historial-body">
                            <tr><td colspan="9" class="text-center text-muted py-4">Cargando…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= $base ?>/js/modulos/envio-lote-sri.js?v=<?= time() ?>"></script>
