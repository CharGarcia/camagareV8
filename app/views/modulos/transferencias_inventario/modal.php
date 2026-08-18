<?php
/** @var array $bodegas */
?>
<div class="modal fade" id="modalTransferencia" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">

            <div class="modal-header bg-light py-2 px-3">
                <h5 class="modal-title fs-6 fw-bold">
                    <i class="bi bi-arrow-left-right text-primary me-2"></i>
                    <span id="tri-modal-titulo">Nueva Transferencia</span>
                    <span id="tri-modal-badge" class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 ms-2 d-none"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body p-0">
                <!-- Barra de Acciones Superior (documento ya guardado) -->
                <div id="tri-acciones" class="px-3 py-2 bg-light border-bottom d-none d-flex gap-1 align-items-center flex-wrap">
                    <button type="button" class="btn btn-outline-danger btn-sm px-2" onclick="window.TRI_abrirPdf()" title="Acta de transferencia (PDF)">
                        <i class="bi bi-file-earmark-pdf fs-6"></i>
                    </button>
                    <div class="vr mx-1"></div>
                    <button type="button" id="tri-btn-guia" class="btn btn-outline-primary btn-sm px-2 d-none" onclick="window.TRI_generarGuia()"
                            title="Generar guía de remisión con estos productos">
                        <i class="bi bi-truck fs-6 me-1"></i> Guía de remisión
                    </button>
                    <span id="tri-guia-emitida" class="small text-muted ms-1 d-none">
                        <i class="bi bi-check-circle text-success me-1"></i>Guía de remisión generada
                    </span>
                </div>

                <div class="border-top">
                    <div class="p-3" id="tri-tab-doc">
                        <input type="hidden" id="tri-id" value="">

                        <div class="row g-2">
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Fecha *</label>
                                <input type="date" id="tri-fecha" class="form-control form-control-sm" max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-danger"><i class="bi bi-box-arrow-up me-1"></i>Bodega de origen *</label>
                                <select id="tri-bodega-origen" class="form-select form-select-sm" onchange="window.TRI_cambioBodega('origen')">
                                    <option value="">Seleccione…</option>
                                    <?php foreach ($bodegas as $b): ?>
                                        <option value="<?= (int) $b['id'] ?>" data-est="<?= (int) ($b['id_establecimiento'] ?? 0) ?>" data-est-cod="<?= htmlspecialchars((string) ($b['establecimiento_codigo'] ?? '')) ?>">
                                            <?= htmlspecialchars($b['nombre']) ?><?= !empty($b['establecimiento_codigo']) ? ' — Est. ' . htmlspecialchars($b['establecimiento_codigo']) : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-1 text-center d-none d-md-block">
                                <label class="form-label small fw-bold d-block">&nbsp;</label>
                                <i class="bi bi-arrow-right fs-5 text-primary"></i>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-success"><i class="bi bi-box-arrow-in-down me-1"></i>Bodega de destino *</label>
                                <select id="tri-bodega-destino" class="form-select form-select-sm" onchange="window.TRI_cambioBodega('destino')">
                                    <option value="">Seleccione…</option>
                                    <?php foreach ($bodegas as $b): ?>
                                        <option value="<?= (int) $b['id'] ?>" data-est="<?= (int) ($b['id_establecimiento'] ?? 0) ?>" data-est-cod="<?= htmlspecialchars((string) ($b['establecimiento_codigo'] ?? '')) ?>">
                                            <?= htmlspecialchars($b['nombre']) ?><?= !empty($b['establecimiento_codigo']) ? ' — Est. ' . htmlspecialchars($b['establecimiento_codigo']) : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold d-block">&nbsp;</label>
                                <div id="tri-aviso-establecimiento" class="small d-none">
                                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">
                                        <i class="bi bi-signpost-split me-1"></i>Entre establecimientos
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="row g-2 mt-1">
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Entrega (responsable)</label>
                                <input type="text" id="tri-resp-envia" class="form-control form-control-sm" maxlength="150" placeholder="Quién despacha">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Recibe (responsable)</label>
                                <input type="text" id="tri-resp-recibe" class="form-control form-control-sm" maxlength="150" placeholder="Quién recibe">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Observaciones</label>
                                <input type="text" id="tri-observaciones" class="form-control form-control-sm" placeholder="Motivo de la transferencia (opcional)">
                            </div>
                        </div>

                        <hr class="my-3">

                        <!-- Buscador de productos -->
                        <div id="tri-zona-agregar" class="row g-2 align-items-end">
                            <div class="col-md-8 position-relative">
                                <label class="form-label small fw-bold">Agregar producto</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white text-muted"><i class="bi bi-search"></i></span>
                                    <input type="text" id="tri-buscar-producto" class="form-control" autocomplete="off"
                                           placeholder="Escriba código o nombre del producto…" disabled>
                                </div>
                                <div id="tri-dropdown-producto" class="list-group shadow position-absolute d-none tri-dropdown-prod" style="z-index:1060;width:calc(100% - 24px);"></div>
                                <div class="form-text small" id="tri-hint-producto">Seleccione primero la bodega de origen.</div>
                            </div>
                        </div>

                        <!-- Líneas -->
                        <div class="table-responsive mt-2 border rounded">
                            <table class="table table-sm table-hover mb-0 align-middle" style="font-size:.78rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:32px;" class="text-center">#</th>
                                        <th style="min-width:200px;">Producto</th>
                                        <th style="width:150px;">Lote</th>
                                        <th style="width:115px;">Caducidad</th>
                                        <th style="width:150px;">Serie / NUP</th>
                                        <th style="width:95px;" class="text-end">Disponible</th>
                                        <th style="width:95px;" class="text-end">Cantidad</th>
                                        <th style="width:95px;" class="text-end">Costo unit.</th>
                                        <th style="width:100px;" class="text-end">Costo total</th>
                                        <th style="width:36px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="tri-tbody-detalle">
                                    <tr id="tri-fila-vacia"><td colspan="10" class="text-center text-muted py-4">Sin productos agregados.</td></tr>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <th colspan="6" class="text-end">Totales</th>
                                        <th class="text-end" id="tri-total-items">0.00</th>
                                        <th></th>
                                        <th class="text-end" id="tri-total-costo">$0.00</th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Datos de registro del documento (solo al abrir uno existente) -->
                        <div class="row g-2 small mt-2 d-none" id="tri-info-auditoria"></div>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-light py-2 d-flex justify-content-between">
                <div class="d-flex gap-2">
                    <button type="button" id="tri-btn-anular" class="btn btn-outline-danger btn-sm d-none" onclick="window.TRI_anular()">
                        <i class="bi bi-x-circle me-1"></i> Anular
                    </button>
                    <button type="button" id="tri-btn-eliminar" class="btn btn-outline-danger btn-sm d-none" onclick="window.TRI_eliminar()">
                        <i class="bi bi-trash3 me-1"></i> Eliminar
                    </button>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" id="tri-btn-guardar" class="btn btn-primary btn-sm px-4" onclick="window.TRI_guardar()">
                        <i class="bi bi-check-lg me-1"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
