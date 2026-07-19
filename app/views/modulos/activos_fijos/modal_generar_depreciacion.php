<div class="modal fade" id="modalDepreciacion" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-graph-down-arrow text-primary me-2"></i> Generar Depreciación Mensual</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-3">
                <p class="text-muted small">Se calculará y contabilizará en un solo asiento la depreciación en línea recta de todos los activos fijos activos de la empresa para el período seleccionado.</p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Mes</label>
                        <select id="dep-mes" class="form-select form-select-sm"></select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Año</label>
                        <select id="dep-anio" class="form-select form-select-sm"></select>
                    </div>
                </div>
                <div id="dep-resultado" class="mt-3"></div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" id="dep-btn-generar" class="btn btn-primary btn-sm px-4" onclick="window.AF_generarDepreciacion()">
                    <i class="bi bi-check-lg me-1"></i> Generar
                </button>
            </div>
        </div>
    </div>
</div>
