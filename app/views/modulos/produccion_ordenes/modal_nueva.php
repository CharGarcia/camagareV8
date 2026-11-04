<?php
/** @var array $bodegas */
$opcionesBodega = '';
foreach ($bodegas as $b) {
    $opcionesBodega .= '<option value="' . (int) $b['id'] . '">' . htmlspecialchars((string) $b['nombre'])
        . (!empty($b['establecimiento_codigo']) ? ' — Est. ' . htmlspecialchars((string) $b['establecimiento_codigo']) : '') . '</option>';
}
?>
<div class="modal fade por-modal" id="modalNuevaOrden" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-2 px-3">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-gear-wide-connected text-primary me-2" id="pon-icono"></i><span id="pon-titulo">Nueva orden de producción</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-3">
                <input type="hidden" id="pon-tipo" value="orden">
                <div id="pon-ayuda" class="alert alert-light border py-2 small mb-3"></div>

                <div class="row g-2">
                    <div class="col-md-6 position-relative">
                        <label class="form-label small fw-bold">Fórmula vigente *</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-diagram-3"></i></span>
                            <input type="text" id="pon-formula-txt" class="form-control" placeholder="Buscar por código, nombre o producto…" autocomplete="off">
                            <input type="hidden" id="pon-formula" value="">
                        </div>
                        <div id="pon-formula-dd" class="list-group position-absolute shadow por-dd d-none" style="width:calc(100% - 12px);"></div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">Cantidad a producir *</label>
                        <div class="input-group input-group-sm">
                            <input type="number" id="pon-cantidad" class="form-control text-end" min="0" step="any">
                            <span class="input-group-text" id="pon-medida">u</span>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">Fecha *</label>
                        <input type="date" id="pon-fecha" class="form-control form-control-sm" max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">Responsable</label>
                        <input type="text" id="pon-responsable" class="form-control form-control-sm" maxlength="150">
                    </div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-danger"><i class="bi bi-box-arrow-up me-1"></i>Bodega de insumos *</label>
                        <select id="pon-bodega-insumos" class="form-select form-select-sm"><option value="">Seleccione…</option><?= $opcionesBodega ?></select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-success"><i class="bi bi-box-arrow-in-down me-1"></i>Bodega de destino *</label>
                        <select id="pon-bodega-destino" class="form-select form-select-sm"><option value="">Seleccione…</option><?= $opcionesBodega ?></select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">Lote</label>
                        <input type="text" id="pon-lote" class="form-control form-control-sm" maxlength="50" placeholder="Número de la orden">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">Caducidad</label>
                        <input type="date" id="pon-caducidad" class="form-control form-control-sm">
                    </div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-12">
                        <label class="form-label small fw-bold">Observaciones</label>
                        <input type="text" id="pon-observaciones" class="form-control form-control-sm">
                    </div>
                </div>

                <hr class="my-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <div class="small fw-bold">Lo que se va a consumir y producir</div>
                    <div class="small" id="pon-faltantes"></div>
                </div>
                <div class="table-responsive border rounded">
                    <table class="table table-sm mb-0 por-lineas" style="font-size:.78rem;">
                        <thead class="table-light"><tr>
                            <th style="width:110px;">Tipo</th><th>Producto</th><th style="width:80px;">Unidad</th>
                            <th style="width:120px;" class="text-end">Teórico</th><th style="width:120px;" class="text-end">Stock</th>
                            <th style="width:130px;" class="text-end pon-col-real d-none">Producido real</th>
                        </tr></thead>
                        <tbody id="pon-tbody"><tr><td colspan="6" class="text-center text-muted py-3">Elija la fórmula y la cantidad.</td></tr></tbody>
                    </table>
                </div>
                <div class="row g-2 mt-2 pon-col-real d-none">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Merma anormal del producto principal</label>
                        <input type="number" id="pon-merma" class="form-control form-control-sm text-end" min="0" step="any" value="0">
                        <div class="form-text">Unidades perdidas fuera de lo normal: su costo va a gasto.</div>
                    </div>
                    <div class="col-md-9 small text-muted pt-4">
                        Los insumos se consumen según la fórmula, tomando primero el lote que vence antes. Si necesita elegir lotes o consumir
                        cantidades distintas, use <b>Nueva orden</b>.
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" id="pon-btn-guardar" class="btn btn-primary btn-sm px-4" onclick="window.POR_crear()">
                    <i class="bi bi-check-lg me-1"></i> Crear orden
                </button>
            </div>
        </div>
    </div>
</div>
