<?php
/** Modal de solo lectura: detalle de una consignación (datos de entrega programada) y, si ya
 * fue entregada, su evidencia (mapa + firma). Datos ya vienen en el data-row de la fila (el
 * listado los trae todos), no requiere una llamada AJAX aparte. */
?>
<!-- Leaflet (mapas) — mismo vendor local que consignaciones-ventas/clientes -->
<link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/vendor/leaflet/leaflet.css">
<script src="<?= rtrim(BASE_URL, '/') ?>/vendor/leaflet/leaflet.js"></script>

<div class="modal fade" id="modalEntregaDetalle" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" style="z-index: 1060;">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold d-flex align-items-center gap-2 flex-wrap">
                    <i class="bi bi-geo-alt text-primary"></i>
                    <span><span id="entc_det_titulo">Entrega</span> — <span id="entc_det_numero">—</span></span>
                    <span id="entc_det_canal_badge"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-3">
                <div class="row g-3">
                    <div class="col-md-5">
                        <div class="border rounded-3 bg-white shadow-sm p-3 h-100">
                            <div class="small text-muted text-uppercase fw-semibold mb-1" style="font-size:.7rem;letter-spacing:.03em;">Consignación</div>
                            <table class="table table-sm mb-2 small">
                                <tr><td class="text-muted">Cliente</td><td class="text-end fw-medium" id="entc_det_cliente">—</td></tr>
                                <tr><td class="text-muted">Dirección</td><td class="text-end" id="entc_det_direccion">—</td></tr>
                                <tr><td class="text-muted">Emisión</td><td class="text-end" id="entc_det_emision">—</td></tr>
                                <tr><td class="text-muted">Entrega programada</td><td class="text-end" id="entc_det_programada">—</td></tr>
                                <tr><td class="text-muted">Responsable</td><td class="text-end" id="entc_det_responsable">—</td></tr>
                                <tr><td class="text-muted">Días en espera</td><td class="text-end" id="entc_det_dias">—</td></tr>
                            </table>
                            <div class="small text-muted mb-1">Observaciones de la consignación</div>
                            <div class="small mb-3" id="entc_det_obs_cons">—</div>

                            <div id="entc_det_sin_evidencia" class="alert alert-warning border small py-2 mb-0" style="display:none;"></div>

                            <div id="entc_det_evidencia">
                                <div class="small text-muted text-uppercase fw-semibold mb-1" style="font-size:.7rem;letter-spacing:.03em;">Evidencia de entrega</div>
                                <table class="table table-sm mb-2 small">
                                    <tr><td class="text-muted">Fecha/hora entrega</td><td class="text-end fw-medium" id="entc_det_fecha">—</td></tr>
                                    <tr><td class="text-muted">Registrado por</td><td class="text-end" id="entc_det_registrado_por">—</td></tr>
                                    <tr><td class="text-muted">Dispositivo</td><td class="text-end text-truncate" style="max-width:160px" id="entc_det_dispositivo">—</td></tr>
                                    <tr id="entc_det_fila_lat"><td class="text-muted">Latitud</td><td class="text-end font-monospace" id="entc_det_lat">—</td></tr>
                                    <tr id="entc_det_fila_lon"><td class="text-muted">Longitud</td><td class="text-end font-monospace" id="entc_det_lon">—</td></tr>
                                    <tr><td class="text-muted">Precisión</td><td class="text-end" id="entc_det_precision">—</td></tr>
                                </table>
                                <div class="small text-muted mb-1">Observaciones de la entrega</div>
                                <div class="small mb-3" id="entc_det_obs">—</div>
                                <div class="small text-muted mb-1">Firma de recepción</div>
                                <div class="text-center" id="entc_det_firma">—</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <div id="entc_det_mapa" style="height:340px;border-radius:8px;border:1px solid #dee2e6;background:#f8f9fa;display:none;"></div>
                        <div id="entc_det_sin_gps" class="alert alert-light border small mb-0 d-flex align-items-center justify-content-center text-center text-muted" style="height:340px;">Sin coordenadas GPS registradas para esta entrega.</div>
                        <div id="entc_det_gmaps" class="small mt-1 text-end" style="display:none;"><a href="#" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>Abrir en Google Maps</a></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-top p-2">
                <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i> Cerrar</button>
                <!-- Solo visible en consignaciones pendientes y con permiso Actualizar (lo controla el JS) -->
                <button type="button" class="btn btn-success btn-sm px-3" id="entc_det_btn_entregar" style="display:none;" onclick="entcMarcarEntregadaDesdeModal()"><i class="bi bi-check2-circle me-1"></i> Marcar como entregada</button>
            </div>
        </div>
    </div>
</div>
