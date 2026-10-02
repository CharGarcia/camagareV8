<?php
/**
 * Modal común "Comprobación con Contabilidad" (solo lectura).
 *
 * Lo usan Reporte de Inventarios (pestaña Auditoría), Cuentas por Cobrar y Cuentas por
 * Pagar; se abre con CMG_comprobacionContable.abrir({...}) (public/js/comprobacion_contable.js).
 * Incluirlo una sola vez por página, a nivel de página (no dentro de otro modal). Trae el
 * modal de asiento contable para poder abrir cada asiento desde la lista.
 */
?>
<style>
    /* Mismo estilo que la comprobación de Control Bancario. La lista no tiene scroll propio:
       crece hacia abajo y scrollea el modal entero. */
    #modalComprobacionContable .cc-card { overflow: clip; }
    #modalComprobacionContable .cc-card thead th { background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; white-space: nowrap; }
    #modalComprobacionContable .cc-partidas { overflow: visible; }
    /* Sin "-scroll" en el nombre a propósito: app.css le impondría un alto máximo en el celular. */
    #modalComprobacionContable .cc-mayor-wrap { overflow-x: auto; border-radius: inherit; }
    #modalComprobacionContable .cc-mayor-wrap > .table { min-width: 100%; width: max-content; }
    #modalComprobacionContable .cc-total td { background: #f8f9fa; border-top: 2px solid #dee2e6; }
    #modalComprobacionContable .cc-col-saldo { background-color: rgba(13, 110, 253, .04); }
    #modalComprobacionContable .cc-fila-dif > td:first-child { box-shadow: inset 3px 0 0 #dc3545; }
    #modalComprobacionContable .cc-solo-dif tr.cc-fila-ok { display: none; }
    #form-filtros-comprobacion .form-control,
    #form-filtros-comprobacion .btn { height: 28px; font-size: .75rem; }
</style>

<div class="modal fade" id="modalComprobacionContable" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" style="max-width:min(1500px, 96vw);">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white py-2 px-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-journal-check me-2"></i>Comprobación con Contabilidad <span id="cc-titulo-modulo" class="fw-normal"></span></h6>
                <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3 position-relative">
                <div id="cc-loader" class="d-none position-absolute top-0 start-0 w-100 h-100 d-flex flex-column align-items-center justify-content-center bg-white bg-opacity-75" style="z-index: 1055;">
                    <div class="spinner-border text-primary mb-2" role="status"></div>
                    <div class="small text-muted">Comparando con la contabilidad...</div>
                </div>
                <form id="form-filtros-comprobacion" class="d-flex flex-wrap align-items-start gap-2 mb-3 pb-2 border-bottom"
                      onsubmit="event.preventDefault(); CMG_comprobacionContable.mostrar();">
                    <div style="width:130px;">
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;" for="cc-desde">Desde</label>
                        <input type="date" class="form-control form-control-sm" id="cc-desde">
                    </div>
                    <div style="width:130px;">
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;" for="cc-hasta">Hasta</label>
                        <input type="date" class="form-control form-control-sm" id="cc-hasta">
                    </div>
                    <div>
                        <label class="form-label small fw-bold mb-1 d-block" style="font-size:.65rem;">&nbsp;</label>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Mostrar</button>
                    </div>
                </form>
                <div id="cc-contenido"></div>
            </div>
            <div class="modal-footer py-2 px-3">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php if (empty($GLOBALS['__cmg_modal_asiento_incluido'])):
    $GLOBALS['__cmg_modal_asiento_incluido'] = true; ?>
<?php include MVC_APP . '/views/modulos/asientos_contables/modal_asiento.php'; ?>
<script src="<?= BASE_URL ?>/js/modulos/asientos_contables_modal.js?v=<?= asset_ver('/js/modulos/asientos_contables_modal.js') ?>"></script>
<?php endif; ?>
<script src="<?= BASE_URL ?>/js/comprobacion_contable.js?v=<?= asset_ver('/js/comprobacion_contable.js') ?>"></script>
