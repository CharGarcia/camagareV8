<?php
/** @var array $vistaConfig @var string $rutaModulo */
$pestanasOrden = [
    'pane-por-plan'     => 'Plan',
    'pane-por-consumir' => 'Consumir',
    'pane-por-cerrar'   => 'Cerrar',
    'pane-por-movs'     => 'Movimientos',
    'pane-por-costos'   => 'Costos',
    'pane-por-traza'    => 'Trazabilidad',
];
// Pestaña «Asiento»: solo si el usuario ve Contabilidad → Asientos Contables (regla común, AsientoPestana).
$verAsientoPor = \App\Helpers\AsientoPestana::puedeVer();
if ($verAsientoPor) {
    $pestanasOrden['pane-por-asiento'] = 'Asiento';
}
// Pestaña «Información» (pieza común: quién creó/modificó, historial y movimientos de inventario).
$pestanasOrden += \App\Helpers\InformacionRegistro::pestanaConfigurable('por');
?>
<div class="modal fade por-modal" id="modalOrden" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-2 px-3">
                <h5 class="modal-title fs-6 fw-bold">
                    <i class="bi bi-gear-wide-connected text-primary me-2"></i><span id="por-titulo">Orden</span>
                    <span id="por-badge" class="ms-2"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0">
                <!-- Barra de acciones superior -->
                <div class="px-3 py-2 bg-light border-bottom d-flex gap-1 align-items-center flex-wrap">
                    <button type="button" class="btn btn-outline-danger btn-sm px-2" onclick="window.POR_pdf()" title="Orden de producción / registro de lote (PDF)">
                        <i class="bi bi-file-earmark-pdf fs-6"></i>
                    </button>
                    <?php // Anular junto al PDF, como en la barra de Facturas de Venta (revierte los movimientos). ?>
                    <button type="button" id="por-btn-anular" class="btn btn-outline-warning btn-sm d-none" onclick="window.POR_anular()" title="Anular la orden">
                        <i class="bi bi-slash-circle me-1"></i>Anular
                    </button>
                    <?php // Cerrar orden: separado de Anular. Pide la fecha y verifica todo antes de cerrar (POR_cerrar). ?>
                    <div class="vr mx-1 por-solo-abierta d-none"></div>
                    <button type="button" id="por-btn-cerrar" class="btn btn-success btn-sm px-3 por-solo-abierta d-none" onclick="window.POR_cerrar()"
                            title="Consume lo pendiente (si está marcado) y da entrada a lo producido en el inventario">
                        <i class="bi bi-flag me-1"></i>Cerrar orden
                    </button>
                    <div class="vr mx-1"></div>
                    <button type="button" id="por-btn-asiento" class="btn btn-outline-primary btn-sm d-none" onclick="window.POR_generarAsiento()" title="Volver a intentar generar los asientos">
                        <i class="bi bi-journal-plus me-1"></i> Generar asiento
                    </button>
                    <span id="por-aviso-asiento" class="small text-danger ms-2 d-none"></span>
                </div>

                <div class="p-3">
                    <input type="hidden" id="por-id" value="">
                    <div class="row g-2 small" id="por-cabecera"></div>

                    <div class="d-flex align-items-center mt-3 border-bottom">
                        <ul class="nav nav-tabs border-0 flex-grow-1" role="tablist">
                            <li class="nav-item"><button class="nav-link active py-1 small" data-bs-toggle="tab" data-bs-target="#pane-por-plan" type="button"><i class="bi bi-list-check me-1"></i>Plan</button></li>
                            <li class="nav-item por-solo-abierta"><button class="nav-link py-1 small" data-bs-toggle="tab" data-bs-target="#pane-por-consumir" type="button"><i class="bi bi-box-arrow-up me-1"></i>Consumir</button></li>
                            <li class="nav-item por-solo-abierta"><button class="nav-link py-1 small" data-bs-toggle="tab" data-bs-target="#pane-por-cerrar" type="button"><i class="bi bi-flag me-1"></i>Cerrar</button></li>
                            <li class="nav-item"><button class="nav-link py-1 small" data-bs-toggle="tab" data-bs-target="#pane-por-movs" type="button"><i class="bi bi-arrow-left-right me-1"></i>Movimientos</button></li>
                            <li class="nav-item"><button class="nav-link py-1 small" data-bs-toggle="tab" data-bs-target="#pane-por-costos" type="button"><i class="bi bi-cash-coin me-1"></i>Costos</button></li>
                            <li class="nav-item"><button class="nav-link py-1 small" data-bs-toggle="tab" data-bs-target="#pane-por-traza" type="button"><i class="bi bi-diagram-2 me-1"></i>Trazabilidad</button></li>
                            <?php if ($verAsientoPor): ?>
                            <li class="nav-item"><button class="nav-link py-1 small" data-bs-toggle="tab" data-bs-target="#pane-por-asiento" type="button" id="por-tab-asiento"><i class="bi bi-journal-text me-1"></i>Asiento</button></li>
                            <?php endif; ?>
                            <?= \App\Helpers\InformacionRegistro::boton('por') ?>
                        </ul>
                        <?= \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasOrden, $vistaConfig ?? [], $rutaModulo) ?>
                    </div>

                    <div class="tab-content pt-2">
                        <!-- Plan: teórico vs real -->
                        <div class="tab-pane fade show active" id="pane-por-plan">
                            <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                                <div class="table-responsive" style="max-height: 350px;">
                                <table class="table table-sm table-detalle por-lineas mb-0 text-nowrap">
                                    <thead><tr class="table-light border-bottom">
                                        <th class="ps-3 py-2 small fw-bold text-muted" style="width:120px;">Tipo</th><th class="py-2 small fw-bold text-muted">Producto</th><th class="py-2 small fw-bold text-muted" style="width:100px;">Unidad</th>
                                        <th class="py-2 small fw-bold text-muted text-end" style="width:110px;">Teórico</th><th class="py-2 small fw-bold text-muted text-end" style="width:110px;">Real</th>
                                        <th class="py-2 small fw-bold text-muted text-end" style="width:110px;">Diferencia</th><th class="py-2 small fw-bold text-muted text-end" style="width:110px;">Stock insumos</th>
                                        <th class="py-2 small fw-bold text-muted text-end pe-3" style="width:110px;">Costo</th>
                                    </tr></thead>
                                    <tbody id="por-tbody-plan"></tbody>
                                </table>
                                </div>
                            </div>
                        </div>

                        <!-- Consumir -->
                        <div class="tab-pane fade" id="pane-por-consumir">
                            <div class="form-text mb-1">
                                Indique lo que salió de la bodega de insumos (puede ser más o menos que lo teórico). Con <b>Por peso</b>, el consumo es
                                peso inicial − peso final (bobinas, sacos, tanques). Sin lote elegido se toma primero el que vence antes.
                            </div>
                            <!-- Sin overflow-hidden: la lista del buscador de «Agregar insumo» sale por debajo de la tarjeta. -->
                            <div class="border rounded-3 bg-white shadow-sm">
                                <div class="table-responsive" style="max-height: 350px;">
                                <table class="table table-sm table-detalle por-lineas mb-0 text-nowrap">
                                    <thead><tr class="table-light border-bottom">
                                        <th class="ps-3 py-2 small fw-bold text-muted">Insumo</th><th class="py-2 small fw-bold text-muted text-end" style="width:110px;">Pendiente</th>
                                        <th class="py-2 small fw-bold text-muted text-center" style="width:80px;">Por peso</th>
                                        <th class="py-2 small fw-bold text-muted text-end" style="width:120px;">Cantidad</th>
                                        <th class="py-2 small fw-bold text-muted text-end" style="width:110px;">Peso inicial</th><th class="py-2 small fw-bold text-muted text-end" style="width:110px;">Peso final</th>
                                        <th class="py-2 small fw-bold text-muted" style="width:220px;">Lote</th><th style="width:40px;"></th>
                                    </tr></thead>
                                    <tbody id="por-tbody-consumir"></tbody>
                                </table>
                                </div>
                                <div class="p-2 border-top bg-light d-flex justify-content-between align-items-center gap-2 rounded-bottom-3">
                                    <div class="position-relative" style="width:360px;">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-white border-0 text-primary"><i class="bi bi-plus-circle"></i></span>
                                            <input type="text" id="por-extra-txt" class="form-control border-0" placeholder="Agregar insumo fuera de fórmula…" autocomplete="off">
                                        </div>
                                    </div>
                                    <div class="small fw-bold text-muted pe-3">Items: <span id="por-count-consumir">0</span></div>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap align-items-end gap-2 mt-2">
                                <div>
                                    <label class="form-label small fw-bold mb-1">Fecha</label>
                                    <input type="date" id="por-fecha-consumo" class="form-control form-control-sm" max="<?= date('Y-m-d') ?>">
                                </div>
                                <button type="button" id="por-btn-consumir" class="btn btn-primary btn-sm px-3" onclick="window.POR_consumir()">
                                    <i class="bi bi-box-arrow-up me-1"></i> Registrar consumo
                                </button>
                            </div>
                        </div>

                        <!-- Cerrar -->
                        <div class="tab-pane fade" id="pane-por-cerrar">
                            <div class="form-text mb-1">Indique lo que realmente se obtuvo. Al cerrar, lo producido entra al inventario con su costo real y la orden ya no se puede modificar.</div>
                            <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                                <div class="table-responsive" style="max-height: 350px;">
                                <table class="table table-sm table-detalle por-lineas mb-0 text-nowrap">
                                    <thead><tr class="table-light border-bottom">
                                        <th class="ps-3 py-2 small fw-bold text-muted" style="width:120px;">Tipo</th><th class="py-2 small fw-bold text-muted">Producto</th><th class="py-2 small fw-bold text-muted text-end" style="width:120px;">Teórico</th>
                                        <th class="py-2 small fw-bold text-muted text-end" style="width:130px;">Obtenido</th><th class="py-2 small fw-bold text-muted" style="width:160px;">Lote</th><th class="py-2 small fw-bold text-muted" style="width:140px;">Caducidad</th>
                                    </tr></thead>
                                    <tbody id="por-tbody-cerrar"></tbody>
                                </table>
                                </div>
                            </div>

                            <div class="small fw-bold mt-3 mb-1">Mano de obra y costos indirectos</div>
                            <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                                <div class="table-responsive" style="max-height: 350px;">
                                <table class="table table-sm table-detalle por-lineas mb-0 text-nowrap">
                                    <thead><tr class="table-light border-bottom">
                                        <th class="ps-3 py-2 small fw-bold text-muted" style="width:160px;">Tipo</th><th class="py-2 small fw-bold text-muted">Descripción</th><th class="py-2 small fw-bold text-muted" style="width:230px;">Base</th>
                                        <th class="py-2 small fw-bold text-muted text-end" style="width:110px;">Cantidad</th><th class="py-2 small fw-bold text-muted text-end" style="width:110px;">Tarifa</th><th style="width:40px;"></th>
                                    </tr></thead>
                                    <tbody id="por-tbody-recursos"></tbody>
                                </table>
                                </div>
                                <div class="p-2 border-top bg-light d-flex justify-content-between align-items-center">
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" onclick="window.POR_agregarRecurso()">
                                        <i class="bi bi-plus-circle me-1"></i> Agregar línea
                                    </button>
                                    <div class="small fw-bold text-muted pe-3">Items: <span id="por-count-recursos">0</span></div>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap align-items-end gap-3 mt-3">
                                <div>
                                    <label class="form-label small fw-bold mb-1">Merma anormal del principal</label>
                                    <input type="number" id="por-merma" class="form-control form-control-sm text-end" style="width:140px;" min="0" step="any" value="0">
                                </div>
                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="checkbox" id="por-consumir-pendiente">
                                    <label class="form-check-label small" for="por-consumir-pendiente">Consumir lo pendiente según la fórmula</label>
                                </div>
                            </div>
                        </div>

                        <!-- Movimientos -->
                        <div class="tab-pane fade" id="pane-por-movs">
                            <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                                <div class="table-responsive" style="max-height: 350px;">
                                <table class="table table-sm table-detalle por-lineas mb-0 text-nowrap">
                                    <thead><tr class="table-light border-bottom">
                                        <th class="ps-3 py-2 small fw-bold text-muted" style="width:100px;">Fecha</th><th class="py-2 small fw-bold text-muted" style="width:100px;">Tipo</th><th class="py-2 small fw-bold text-muted">Producto</th><th class="py-2 small fw-bold text-muted" style="width:140px;">Bodega</th>
                                        <th class="py-2 small fw-bold text-muted" style="width:120px;">Lote</th><th class="py-2 small fw-bold text-muted text-end" style="width:100px;">Cantidad</th>
                                        <th class="py-2 small fw-bold text-muted text-end" style="width:100px;">Costo unit.</th><th class="py-2 small fw-bold text-muted text-end" style="width:100px;">Costo</th><th class="py-2 small fw-bold text-muted" style="width:120px;">Registró</th>
                                    </tr></thead>
                                    <tbody id="por-tbody-movs"></tbody>
                                </table>
                                </div>
                            </div>
                        </div>

                        <!-- Costos -->
                        <div class="tab-pane fade" id="pane-por-costos"><div id="por-costos" class="small"></div></div>

                        <!-- Trazabilidad -->
                        <div class="tab-pane fade" id="pane-por-traza">
                            <div class="form-text mb-1">Qué lotes de insumo se usaron y qué lotes salieron de esta orden.</div>
                            <div id="por-traza" class="small"></div>
                        </div>

                        <!-- Asiento -->
                        <?php if ($verAsientoPor): ?>
                        <!-- Asiento: la misma pieza editable de Compras, Ingresos, etc. Una orden tiene varios
                             asientos (uno por cada consumo y el de cierre): el selector elige cuál se ve y edita. -->
                        <div class="tab-pane fade" id="pane-por-asiento">
                            <div id="por-asiento-vacio" class="small text-muted py-2 d-none"></div>
                            <div id="por-asiento-contenido">
                                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                                    <label class="small fw-bold mb-0" for="por-asiento-sel">Asiento</label>
                                    <select id="por-asiento-sel" class="form-select form-select-sm" style="max-width:480px;"></select>
                                    <span class="small text-muted" id="por-asiento-info"></span>
                                </div>
                                <?php $prefijo = 'por'; require MVC_APP . '/views/partials/asiento_tab.php'; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?= \App\Helpers\InformacionRegistro::panel('por', $rutaModulo, 'por-id') ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2 d-flex justify-content-between">
                <div class="d-flex gap-2">
                    <button type="button" id="por-btn-eliminar" class="btn btn-outline-danger btn-sm d-none" onclick="window.POR_eliminar()">
                        <i class="bi bi-trash3 me-1"></i> Eliminar
                    </button>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
