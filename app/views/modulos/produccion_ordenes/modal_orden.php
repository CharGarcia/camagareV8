<?php
/** @var array $vistaConfig @var string $rutaModulo */
$pestanasOrden = [
    'pane-por-plan'     => 'Plan',
    'pane-por-consumir' => 'Consumir',
    'pane-por-cerrar'   => 'Cerrar',
    'pane-por-movs'     => 'Movimientos',
    'pane-por-costos'   => 'Costos',
    'pane-por-traza'    => 'Trazabilidad',
    'pane-por-asiento'  => 'Asiento',
];
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
                            <li class="nav-item"><button class="nav-link py-1 small" data-bs-toggle="tab" data-bs-target="#pane-por-asiento" type="button"><i class="bi bi-journal-text me-1"></i>Asiento</button></li>
                        </ul>
                        <?= \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasOrden, $vistaConfig ?? [], $rutaModulo) ?>
                    </div>

                    <div class="tab-content pt-2">
                        <!-- Plan: teórico vs real -->
                        <div class="tab-pane fade show active" id="pane-por-plan">
                            <div class="table-responsive border rounded">
                                <table class="table table-sm mb-0" style="font-size:.78rem;">
                                    <thead class="table-light"><tr>
                                        <th style="width:110px;">Tipo</th><th>Producto</th><th style="width:80px;">Unidad</th>
                                        <th style="width:110px;" class="text-end">Teórico</th><th style="width:110px;" class="text-end">Real</th>
                                        <th style="width:110px;" class="text-end">Diferencia</th><th style="width:110px;" class="text-end">Stock insumos</th>
                                        <th style="width:110px;" class="text-end">Costo</th>
                                    </tr></thead>
                                    <tbody id="por-tbody-plan"></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Consumir -->
                        <div class="tab-pane fade" id="pane-por-consumir">
                            <div class="form-text mb-1">
                                Indique lo que salió de la bodega de insumos (puede ser más o menos que lo teórico). Con <b>Por peso</b>, el consumo es
                                peso inicial − peso final (bobinas, sacos, tanques). Sin lote elegido se toma primero el que vence antes.
                            </div>
                            <div class="table-responsive border rounded">
                                <table class="table table-sm mb-0 por-lineas" style="font-size:.78rem;">
                                    <thead class="table-light"><tr>
                                        <th>Insumo</th><th style="width:100px;" class="text-end">Pendiente</th>
                                        <th style="width:70px;" class="text-center">Por peso</th>
                                        <th style="width:110px;" class="text-end">Cantidad</th>
                                        <th style="width:100px;" class="text-end">Peso inicial</th><th style="width:100px;" class="text-end">Peso final</th>
                                        <th style="width:200px;">Lote</th><th style="width:30px;"></th>
                                    </tr></thead>
                                    <tbody id="por-tbody-consumir"></tbody>
                                </table>
                            </div>
                            <div class="d-flex flex-wrap align-items-end gap-2 mt-2">
                                <div class="position-relative" style="width:360px;">
                                    <label class="form-label small fw-bold mb-1">Agregar insumo fuera de fórmula</label>
                                    <input type="text" id="por-extra-txt" class="form-control form-control-sm" placeholder="Buscar producto…" autocomplete="off">
                                </div>
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
                            <div class="table-responsive border rounded">
                                <table class="table table-sm mb-0 por-lineas" style="font-size:.78rem;">
                                    <thead class="table-light"><tr>
                                        <th style="width:110px;">Tipo</th><th>Producto</th><th style="width:110px;" class="text-end">Teórico</th>
                                        <th style="width:120px;" class="text-end">Obtenido</th><th style="width:150px;">Lote</th><th style="width:130px;">Caducidad</th>
                                    </tr></thead>
                                    <tbody id="por-tbody-cerrar"></tbody>
                                </table>
                            </div>

                            <div class="small fw-bold mt-3 mb-1">Mano de obra y costos indirectos</div>
                            <div class="table-responsive border rounded">
                                <table class="table table-sm mb-0 por-lineas" style="font-size:.78rem;">
                                    <thead class="table-light"><tr>
                                        <th style="width:150px;">Tipo</th><th>Descripción</th><th style="width:200px;">Base</th>
                                        <th style="width:100px;" class="text-end">Cantidad</th><th style="width:100px;" class="text-end">Tarifa</th><th style="width:30px;"></th>
                                    </tr></thead>
                                    <tbody id="por-tbody-recursos"></tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="window.POR_agregarRecurso()"><i class="bi bi-plus-lg me-1"></i>Agregar costo</button>

                            <div class="d-flex flex-wrap align-items-end gap-3 mt-3">
                                <div>
                                    <label class="form-label small fw-bold mb-1">Merma anormal del principal</label>
                                    <input type="number" id="por-merma" class="form-control form-control-sm text-end" style="width:140px;" min="0" step="any" value="0">
                                </div>
                                <div>
                                    <label class="form-label small fw-bold mb-1">Fecha de cierre</label>
                                    <input type="date" id="por-fecha-cierre" class="form-control form-control-sm" max="<?= date('Y-m-d') ?>">
                                </div>
                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="checkbox" id="por-consumir-pendiente">
                                    <label class="form-check-label small" for="por-consumir-pendiente">Consumir lo pendiente según la fórmula</label>
                                </div>
                                <button type="button" id="por-btn-cerrar" class="btn btn-success btn-sm px-3" onclick="window.POR_cerrar()">
                                    <i class="bi bi-flag me-1"></i> Cerrar orden
                                </button>
                            </div>
                        </div>

                        <!-- Movimientos -->
                        <div class="tab-pane fade" id="pane-por-movs">
                            <div class="table-responsive border rounded">
                                <table class="table table-sm mb-0" style="font-size:.78rem;">
                                    <thead class="table-light"><tr>
                                        <th style="width:90px;">Fecha</th><th style="width:90px;">Tipo</th><th>Producto</th><th style="width:140px;">Bodega</th>
                                        <th style="width:120px;">Lote</th><th style="width:100px;" class="text-end">Cantidad</th>
                                        <th style="width:100px;" class="text-end">Costo unit.</th><th style="width:100px;" class="text-end">Costo</th><th style="width:120px;">Registró</th>
                                    </tr></thead>
                                    <tbody id="por-tbody-movs"></tbody>
                                </table>
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
                        <div class="tab-pane fade" id="pane-por-asiento"><div id="por-asientos" class="small"></div></div>
                    </div>

                    <div class="small text-muted mt-3" id="por-auditoria"></div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2 d-flex justify-content-between">
                <div class="d-flex gap-2">
                    <button type="button" id="por-btn-anular" class="btn btn-outline-danger btn-sm d-none" onclick="window.POR_anular()">
                        <i class="bi bi-x-circle me-1"></i> Anular
                    </button>
                    <button type="button" id="por-btn-eliminar" class="btn btn-outline-danger btn-sm d-none" onclick="window.POR_eliminar()">
                        <i class="bi bi-trash3 me-1"></i> Eliminar
                    </button>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
