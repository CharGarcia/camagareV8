<?php
/**
 * Modal de solo lectura con el documento que originó un asiento (factura, compra, egreso…).
 * Lo abre DOCORIGEN_abrirModal(modulo, id) desde la columna "Documento Ref." de Mayores y del
 * mayor auxiliar de Estados Financieros. La URL del endpoint la define cada vista en
 * window.DOCORIGEN_URL (respeta los permisos del módulo desde el que se abre).
 */
?>
<div class="modal fade" id="modalDocumentoOrigen" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow">
            <div class="modal-header bg-light py-2">
                <h5 class="modal-title fw-bold" id="tituloDocumentoOrigen">
                    <i class="bi bi-file-earmark-text text-primary me-2"></i> Documento
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div id="loaderDocumentoOrigen" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div>
                    <p class="text-muted mt-2 small">Cargando documento...</p>
                </div>

                <div id="errorDocumentoOrigen" class="alert alert-warning d-none mb-0"></div>

                <div id="cuerpoDocumentoOrigen" class="d-none">
                    <!-- Datos de cabecera -->
                    <div class="row g-2 mb-3" id="cabeceraDocumentoOrigen"></div>

                    <!-- Líneas -->
                    <div class="table-responsive" style="max-height: 45vh; overflow-y: auto;">
                        <table class="table table-sm table-bordered table-hover mb-0" style="font-size: 0.82rem;">
                            <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                                <tr id="theadDocumentoOrigen"></tr>
                            </thead>
                            <tbody id="tbodyDocumentoOrigen"></tbody>
                        </table>
                    </div>

                    <!-- Totales y observaciones -->
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mt-3">
                        <div class="small text-muted" id="observacionesDocumentoOrigen" style="max-width: 60%;"></div>
                        <div id="totalesDocumentoOrigen" style="min-width: 220px;"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2">
                <span class="text-muted small me-auto"><i class="bi bi-lock me-1"></i> Solo lectura</span>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
