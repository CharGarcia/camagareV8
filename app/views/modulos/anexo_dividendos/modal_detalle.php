<?php
/**
 * Modal del dividendo distribuido (sección C.2 del anexo).
 * Se abre sobre el modal principal; el z-index lo eleva anexo_dividendos.js.
 *
 * @var array $catalogo
 */
?>
<div class="modal fade" id="modalAdiDetalle" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">

            <div class="modal-header bg-light py-2">
                <h6 class="modal-title fw-bold">
                    <i class="bi bi-cash-stack text-success me-2"></i>
                    <span id="adi-det-titulo">Nuevo dividendo distribuido</span>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body p-3">
                <input type="hidden" id="adi-det-id" value="">

                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label small fw-bold d-block">Beneficiario</label>
                        <select id="adi-det-beneficiario" class="form-select form-select-sm"></select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold d-block">Año que generó la utilidad</label>
                        <input type="number" id="adi-det-anio" class="form-control form-control-sm" min="2000" step="1">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold d-block">Fecha de registro contable</label>
                        <input type="date" id="adi-det-fecha" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold d-block">Tipo de dividendo distribuido</label>
                        <select id="adi-det-tipo" class="form-select form-select-sm"></select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold d-block">Monto del dividendo distribuido</label>
                        <input type="number" step="0.01" min="0" id="adi-det-monto" class="form-control form-control-sm text-end">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold d-block">Ingreso gravado por dividendos</label>
                        <div class="input-group input-group-sm">
                            <input type="number" step="0.01" min="0" id="adi-det-gravado" class="form-control text-end">
                            <button class="btn btn-outline-secondary" type="button" onclick="ADI_sugerirCalculo()"
                                    title="Sugerir con la normativa vigente">
                                <i class="bi bi-magic"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold d-block">Monto de la retención</label>
                        <input type="number" step="0.01" min="0" id="adi-det-retencion" class="form-control form-control-sm text-end">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold d-block">¿El dividendo está pagado?</label>
                        <select id="adi-det-pagado" class="form-select form-select-sm">
                            <?php foreach ($catalogo['respuesta'] as $cod => $nom): ?>
                                <option value="<?= $cod ?>"><?= htmlspecialchars($nom) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold d-block">ISD pagado</label>
                        <input type="number" step="0.01" min="0" id="adi-det-isd" class="form-control form-control-sm text-end">
                        <div class="form-text adi-nota">Solo si el dividendo está pagado.</div>
                    </div>
                </div>

                <div class="alert alert-light border adi-nota mt-3 mb-0" id="adi-det-ayuda"></div>
            </div>

            <div class="modal-footer bg-light py-2 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-danger btn-sm d-none" id="adi-det-btn-eliminar"
                        onclick="ADI_eliminarDetalle()">
                    <i class="bi bi-trash me-1"></i> Eliminar
                </button>
                <div class="ms-auto d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary btn-sm px-4" onclick="ADI_guardarDetalle()">
                        <i class="bi bi-check-lg me-1"></i> Guardar
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>
